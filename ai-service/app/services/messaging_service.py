"""Service for parent-educator messaging operations.

Provides CRUD operations for message threads, messages, and notification
preferences. Supports direct communication between parents and educators/directors
with thread organization by type (daily_log, urgent, serious, admin).

Features:
- @mention extraction from message content
- Private note handling for staff-only visibility
- Thread and message CRUD operations
- Notification preference management
"""

from __future__ import annotations

import re
from datetime import datetime
from typing import Optional
from uuid import UUID

from sqlalchemy import and_, cast, func, or_, select, String, update
from sqlalchemy.ext.asyncio import AsyncSession
from sqlalchemy.orm import selectinload

from app.models.messaging import (
    Message,
    MessageAttachment,
    MessageThread,
    NotificationPreference,
)
from app.schemas.messaging import (
    AttachmentCreate,
    AttachmentResponse,
    MessageContentType,
    MessageCreate,
    MessageResponse,
    NotificationChannelType,
    NotificationFrequency,
    NotificationPreferenceListResponse,
    NotificationPreferenceRequest,
    NotificationPreferenceResponse,
    NotificationType,
    SenderType,
    ThreadCreate,
    ThreadParticipant,
    ThreadResponse,
    ThreadType,
    ThreadUpdate,
    ThreadWithMessagesResponse,
    UnreadCountResponse,
)


# =============================================================================
# Exception Classes
# =============================================================================


class MessagingServiceError(Exception):
    """Base exception for messaging service errors."""

    pass


class ThreadNotFoundError(MessagingServiceError):
    """Raised when the specified thread is not found."""

    pass


class MessageNotFoundError(MessagingServiceError):
    """Raised when the specified message is not found."""

    pass


class UnauthorizedAccessError(MessagingServiceError):
    """Raised when the user does not have permission to access a resource."""

    pass


class InvalidThreadError(MessagingServiceError):
    """Raised when thread data is invalid."""

    pass


class NotificationPreferenceNotFoundError(MessagingServiceError):
    """Raised when the specified notification preference is not found."""

    pass


# =============================================================================
# Messaging Service
# =============================================================================


class MessagingService:
    """Service for managing parent-educator messaging.

    This service provides CRUD operations for message threads and messages,
    enabling direct communication between parents and educators/directors.
    Threads are organized by type and associated with specific children.

    Attributes:
        db: Async database session for database operations
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize the messaging service.

        Args:
            db: Async database session
        """
        self.db = db

    # =========================================================================
    # Thread Operations
    # =========================================================================

    async def create_thread(
        self,
        request: ThreadCreate,
        user_id: UUID,
        user_type: SenderType,
    ) -> ThreadResponse:
        """Create a new message thread.

        Creates a new conversation thread between parents and educators.
        Optionally includes an initial message if provided.

        Args:
            request: The thread creation request with subject, type, and participants
            user_id: ID of the user creating the thread
            user_type: Type of the user creating the thread

        Returns:
            ThreadResponse with the created thread data

        Raises:
            InvalidThreadError: When thread data is invalid
        """
        # Validate thread data
        if not request.subject.strip():
            raise InvalidThreadError("Thread subject cannot be empty")

        # Build participants list including the creator
        participants_data = []
        creator_included = False

        for participant in request.participants:
            participants_data.append({
                "user_id": str(participant.user_id),
                "user_type": participant.user_type.value,
                "display_name": participant.display_name,
            })
            if participant.user_id == user_id:
                creator_included = True

        # Add creator if not already in participants
        if not creator_included:
            participants_data.append({
                "user_id": str(user_id),
                "user_type": user_type.value,
                "display_name": None,
            })

        # Create the thread
        thread = MessageThread(
            subject=request.subject.strip(),
            thread_type=request.thread_type.value,
            child_id=request.child_id,
            created_by=user_id,
            participants=participants_data,
            is_active=True,
        )

        self.db.add(thread)
        await self.db.commit()
        await self.db.refresh(thread)

        # Create initial message if provided
        if request.initial_message:
            initial_message = Message(
                thread_id=thread.id,
                sender_id=user_id,
                sender_type=user_type.value,
                content=request.initial_message,
                content_type=MessageContentType.TEXT.value,
                is_read=False,
            )
            self.db.add(initial_message)
            await self.db.commit()

        return await self._build_thread_response(thread, user_id)

    async def get_thread(
        self,
        thread_id: UUID,
        user_id: UUID,
        include_messages: bool = False,
    ) -> ThreadResponse | ThreadWithMessagesResponse:
        """Get a message thread by ID.

        Retrieves a thread and optionally its messages. Validates that the
        user has permission to access the thread.

        Args:
            thread_id: Unique identifier of the thread
            user_id: ID of the user requesting the thread
            include_messages: Whether to include messages in the response

        Returns:
            ThreadResponse or ThreadWithMessagesResponse with the thread data

        Raises:
            ThreadNotFoundError: When the thread is not found
            UnauthorizedAccessError: When the user doesn't have access
        """
        # Query the thread
        query = select(MessageThread).where(
            cast(MessageThread.id, String) == str(thread_id)
        )
        result = await self.db.execute(query)
        thread = result.scalar_one_or_none()

        if not thread:
            raise ThreadNotFoundError(f"Thread with ID {thread_id} not found")

        # Verify user has access to the thread
        if not self._user_has_thread_access(thread, user_id):
            raise UnauthorizedAccessError(
                "User does not have permission to access this thread"
            )

        if include_messages:
            return await self._build_thread_with_messages_response(thread, user_id)

        return await self._build_thread_response(thread, user_id)

    async def list_threads_for_user(
        self,
        user_id: UUID,
        child_id: Optional[UUID] = None,
        thread_type: Optional[ThreadType] = None,
        include_archived: bool = False,
        limit: int = 50,
        offset: int = 0,
    ) -> list[ThreadResponse]:
        """List message threads for a user.

        Retrieves all threads the user participates in, optionally filtered
        by child, type, and active status.

        Args:
            user_id: ID of the user requesting threads
            child_id: Optional filter by child ID
            thread_type: Optional filter by thread type
            include_archived: Whether to include archived (inactive) threads
            limit: Maximum number of threads to return
            offset: Number of threads to skip for pagination

        Returns:
            List of ThreadResponse objects
        """
        # Build the query
        query = select(MessageThread)

        # Filter conditions
        conditions = []

        # User must be a participant or creator
        # Check if user is in participants JSON or is the creator
        conditions.append(
            or_(
                cast(MessageThread.created_by, String) == str(user_id),
                MessageThread.participants.contains([{"user_id": str(user_id)}]),
            )
        )

        if child_id:
            conditions.append(
                cast(MessageThread.child_id, String) == str(child_id)
            )

        if thread_type:
            conditions.append(MessageThread.thread_type == thread_type.value)

        if not include_archived:
            conditions.append(MessageThread.is_active == True)  # noqa: E712

        if conditions:
            query = query.where(and_(*conditions))

        # Order by most recent activity (updated_at or created_at)
        query = query.order_by(
            MessageThread.updated_at.desc().nulls_last(),
            MessageThread.created_at.desc(),
        )

        # Apply pagination
        query = query.offset(offset).limit(limit)

        result = await self.db.execute(query)
        threads = result.scalars().all()

        # Build responses
        responses = []
        for thread in threads:
            response = await self._build_thread_response(thread, user_id)
            responses.append(response)

        return responses

    async def update_thread(
        self,
        thread_id: UUID,
        request: ThreadUpdate,
        user_id: UUID,
    ) -> ThreadResponse:
        """Update a message thread.

        Updates thread properties like subject or active status.

        Args:
            thread_id: Unique identifier of the thread
            request: The thread update request
            user_id: ID of the user updating the thread

        Returns:
            ThreadResponse with the updated thread data

        Raises:
            ThreadNotFoundError: When the thread is not found
            UnauthorizedAccessError: When the user doesn't have access
        """
        # Get the thread
        query = select(MessageThread).where(
            cast(MessageThread.id, String) == str(thread_id)
        )
        result = await self.db.execute(query)
        thread = result.scalar_one_or_none()

        if not thread:
            raise ThreadNotFoundError(f"Thread with ID {thread_id} not found")

        # Verify user has access
        if not self._user_has_thread_access(thread, user_id):
            raise UnauthorizedAccessError(
                "User does not have permission to update this thread"
            )

        # Apply updates
        if request.subject is not None:
            thread.subject = request.subject.strip()

        if request.is_active is not None:
            thread.is_active = request.is_active

        thread.updated_at = datetime.utcnow()

        await self.db.commit()
        await self.db.refresh(thread)

        return await self._build_thread_response(thread, user_id)

    async def archive_thread(
        self,
        thread_id: UUID,
        user_id: UUID,
    ) -> ThreadResponse:
        """Archive a message thread.

        Sets the thread's is_active flag to False, effectively archiving it.

        Args:
            thread_id: Unique identifier of the thread
            user_id: ID of the user archiving the thread

        Returns:
            ThreadResponse with the archived thread data

        Raises:
            ThreadNotFoundError: When the thread is not found
            UnauthorizedAccessError: When the user doesn't have access
        """
        update_request = ThreadUpdate(is_active=False)
        return await self.update_thread(thread_id, update_request, user_id)

    # =========================================================================
    # Message Operations
    # =========================================================================

    async def send_message(
        self,
        thread_id: UUID,
        request: MessageCreate,
        sender_id: UUID,
        sender_type: SenderType,
    ) -> MessageResponse:
        """Send a new message in a thread.

        Creates a new message within an existing thread. Validates that the
        sender has permission to post in the thread and creates any attachments.

        Args:
            thread_id: Unique identifier of the thread
            request: The message creation request with content and attachments
            sender_id: ID of the user sending the message
            sender_type: Type of the user sending the message

        Returns:
            MessageResponse with the created message data

        Raises:
            ThreadNotFoundError: When the thread is not found
            UnauthorizedAccessError: When the user doesn't have access
            InvalidThreadError: When the thread is archived
        """
        # Get the thread
        query = select(MessageThread).where(
            cast(MessageThread.id, String) == str(thread_id)
        )
        result = await self.db.execute(query)
        thread = result.scalar_one_or_none()

        if not thread:
            raise ThreadNotFoundError(f"Thread with ID {thread_id} not found")

        # Verify user has access to the thread
        if not self._user_has_thread_access(thread, sender_id):
            raise UnauthorizedAccessError(
                "User does not have permission to post in this thread"
            )

        # Check thread is active
        if not thread.is_active:
            raise InvalidThreadError("Cannot send message to archived thread")

        # Create the message
        message = Message(
            thread_id=thread_id,
            sender_id=sender_id,
            sender_type=sender_type.value,
            content=request.content,
            content_type=request.content_type.value,
            is_read=False,
        )

        self.db.add(message)
        await self.db.commit()
        await self.db.refresh(message)

        # Create attachments if provided
        if request.attachments:
            for attachment_data in request.attachments:
                attachment = MessageAttachment(
                    message_id=message.id,
                    file_url=attachment_data.file_url,
                    file_type=attachment_data.file_type,
                    file_name=attachment_data.file_name,
                    file_size=attachment_data.file_size,
                )
                self.db.add(attachment)

            await self.db.commit()

        # Update thread's updated_at timestamp
        thread.updated_at = datetime.utcnow()
        await self.db.commit()

        return await self._build_message_response(message, thread)

    async def get_message(
        self,
        message_id: UUID,
        user_id: UUID,
    ) -> MessageResponse:
        """Get a message by ID.

        Retrieves a single message and validates user access.

        Args:
            message_id: Unique identifier of the message
            user_id: ID of the user requesting the message

        Returns:
            MessageResponse with the message data

        Raises:
            MessageNotFoundError: When the message is not found
            UnauthorizedAccessError: When the user doesn't have access
        """
        # Query the message with its thread
        message_query = select(Message).options(
            selectinload(Message.attachments)
        ).where(
            cast(Message.id, String) == str(message_id)
        )
        result = await self.db.execute(message_query)
        message = result.scalar_one_or_none()

        if not message:
            raise MessageNotFoundError(f"Message with ID {message_id} not found")

        # Get the thread to verify access
        thread_query = select(MessageThread).where(
            cast(MessageThread.id, String) == str(message.thread_id)
        )
        thread_result = await self.db.execute(thread_query)
        thread = thread_result.scalar_one_or_none()

        if not thread:
            raise ThreadNotFoundError(
                f"Thread for message {message_id} not found"
            )

        # Verify user has access
        if not self._user_has_thread_access(thread, user_id):
            raise UnauthorizedAccessError(
                "User does not have permission to view this message"
            )

        return await self._build_message_response(message, thread)

    async def list_messages(
        self,
        thread_id: UUID,
        user_id: UUID,
        limit: int = 50,
        offset: int = 0,
        before: Optional[datetime] = None,
        after: Optional[datetime] = None,
    ) -> list[MessageResponse]:
        """List messages in a thread.

        Retrieves messages in a thread with optional filtering and pagination.
        Messages are returned in chronological order (oldest first).

        Args:
            thread_id: Unique identifier of the thread
            user_id: ID of the user requesting messages
            limit: Maximum number of messages to return
            offset: Number of messages to skip for pagination
            before: Filter messages before this timestamp
            after: Filter messages after this timestamp

        Returns:
            List of MessageResponse objects

        Raises:
            ThreadNotFoundError: When the thread is not found
            UnauthorizedAccessError: When the user doesn't have access
        """
        # Get the thread to verify access
        thread_query = select(MessageThread).where(
            cast(MessageThread.id, String) == str(thread_id)
        )
        thread_result = await self.db.execute(thread_query)
        thread = thread_result.scalar_one_or_none()

        if not thread:
            raise ThreadNotFoundError(f"Thread with ID {thread_id} not found")

        # Verify user has access
        if not self._user_has_thread_access(thread, user_id):
            raise UnauthorizedAccessError(
                "User does not have permission to view messages in this thread"
            )

        # Build query
        conditions = [cast(Message.thread_id, String) == str(thread_id)]

        if before:
            conditions.append(Message.created_at < before)
        if after:
            conditions.append(Message.created_at > after)

        query = (
            select(Message)
            .options(selectinload(Message.attachments))
            .where(and_(*conditions))
            .order_by(Message.created_at.asc())
            .offset(offset)
            .limit(limit)
        )

        result = await self.db.execute(query)
        messages = result.scalars().all()

        # Build responses
        responses = []
        for message in messages:
            response = await self._build_message_response(message, thread)
            responses.append(response)

        return responses

    async def mark_messages_as_read(
        self,
        message_ids: list[UUID],
        user_id: UUID,
    ) -> int:
        """Mark messages as read.

        Updates the is_read flag for the specified messages. Only marks
        messages that the user has access to and that were not sent by them.

        Args:
            message_ids: List of message IDs to mark as read
            user_id: ID of the user marking messages as read

        Returns:
            Number of messages marked as read
        """
        if not message_ids:
            return 0

        # Convert UUIDs to strings for comparison
        message_id_strings = [str(mid) for mid in message_ids]

        # Get messages the user has access to
        messages_query = (
            select(Message)
            .where(
                and_(
                    cast(Message.id, String).in_(message_id_strings),
                    Message.is_read == False,  # noqa: E712
                    cast(Message.sender_id, String) != str(user_id),
                )
            )
        )
        result = await self.db.execute(messages_query)
        messages = result.scalars().all()

        # Verify access for each message
        marked_count = 0
        for message in messages:
            # Get the thread
            thread_query = select(MessageThread).where(
                cast(MessageThread.id, String) == str(message.thread_id)
            )
            thread_result = await self.db.execute(thread_query)
            thread = thread_result.scalar_one_or_none()

            if thread and self._user_has_thread_access(thread, user_id):
                message.is_read = True
                marked_count += 1

        if marked_count > 0:
            await self.db.commit()

        return marked_count

    async def mark_thread_as_read(
        self,
        thread_id: UUID,
        user_id: UUID,
    ) -> int:
        """Mark all messages in a thread as read.

        Updates the is_read flag for all unread messages in a thread
        that were not sent by the user.

        Args:
            thread_id: Unique identifier of the thread
            user_id: ID of the user marking messages as read

        Returns:
            Number of messages marked as read

        Raises:
            ThreadNotFoundError: When the thread is not found
            UnauthorizedAccessError: When the user doesn't have access
        """
        # Get the thread to verify access
        thread_query = select(MessageThread).where(
            cast(MessageThread.id, String) == str(thread_id)
        )
        thread_result = await self.db.execute(thread_query)
        thread = thread_result.scalar_one_or_none()

        if not thread:
            raise ThreadNotFoundError(f"Thread with ID {thread_id} not found")

        if not self._user_has_thread_access(thread, user_id):
            raise UnauthorizedAccessError(
                "User does not have permission to access this thread"
            )

        # Update all unread messages not sent by the user
        stmt = (
            update(Message)
            .where(
                and_(
                    cast(Message.thread_id, String) == str(thread_id),
                    Message.is_read == False,  # noqa: E712
                    cast(Message.sender_id, String) != str(user_id),
                )
            )
            .values(is_read=True)
        )

        result = await self.db.execute(stmt)
        await self.db.commit()

        return result.rowcount

    async def delete_message(
        self,
        message_id: UUID,
        user_id: UUID,
    ) -> bool:
        """Delete a message.

        Removes a message from a thread. Only the sender can delete their
        own messages.

        Args:
            message_id: Unique identifier of the message
            user_id: ID of the user deleting the message

        Returns:
            True if the message was deleted

        Raises:
            MessageNotFoundError: When the message is not found
            UnauthorizedAccessError: When the user is not the sender
        """
        # Query the message
        message_query = select(Message).where(
            cast(Message.id, String) == str(message_id)
        )
        result = await self.db.execute(message_query)
        message = result.scalar_one_or_none()

        if not message:
            raise MessageNotFoundError(f"Message with ID {message_id} not found")

        # Verify user is the sender
        if str(message.sender_id) != str(user_id):
            raise UnauthorizedAccessError(
                "Only the message sender can delete the message"
            )

        # Delete attachments first
        attachments_query = select(MessageAttachment).where(
            cast(MessageAttachment.message_id, String) == str(message_id)
        )
        attachments_result = await self.db.execute(attachments_query)
        attachments = attachments_result.scalars().all()

        for attachment in attachments:
            await self.db.delete(attachment)

        # Delete the message
        await self.db.delete(message)
        await self.db.commit()

        return True

    async def get_unread_count(
        self,
        user_id: UUID,
        child_id: Optional[UUID] = None,
    ) -> UnreadCountResponse:
        """Get unread message count for a user.

        Returns the total number of unread messages and the number of
        threads with unread messages.

        Args:
            user_id: ID of the user to get unread count for
            child_id: Optional filter by child ID

        Returns:
            UnreadCountResponse with unread counts
        """
        # Get all threads the user participates in
        thread_conditions = [
            or_(
                cast(MessageThread.created_by, String) == str(user_id),
                MessageThread.participants.contains([{"user_id": str(user_id)}]),
            ),
            MessageThread.is_active == True,  # noqa: E712
        ]

        if child_id:
            thread_conditions.append(
                cast(MessageThread.child_id, String) == str(child_id)
            )

        threads_query = select(MessageThread.id).where(and_(*thread_conditions))
        threads_result = await self.db.execute(threads_query)
        thread_ids = [str(t[0]) for t in threads_result.fetchall()]

        if not thread_ids:
            return UnreadCountResponse(
                total_unread=0,
                threads_with_unread=0,
            )

        # Count total unread messages
        total_unread_query = select(func.count(Message.id)).where(
            and_(
                cast(Message.thread_id, String).in_(thread_ids),
                Message.is_read == False,  # noqa: E712
                cast(Message.sender_id, String) != str(user_id),
            )
        )
        total_result = await self.db.execute(total_unread_query)
        total_unread = total_result.scalar() or 0

        # Count threads with unread messages
        threads_with_unread_query = (
            select(func.count(func.distinct(Message.thread_id)))
            .where(
                and_(
                    cast(Message.thread_id, String).in_(thread_ids),
                    Message.is_read == False,  # noqa: E712
                    cast(Message.sender_id, String) != str(user_id),
                )
            )
        )
        threads_result = await self.db.execute(threads_with_unread_query)
        threads_with_unread = threads_result.scalar() or 0

        return UnreadCountResponse(
            total_unread=total_unread,
            threads_with_unread=threads_with_unread,
        )

    # =========================================================================
    # Helper Methods
    # =========================================================================

    def _user_has_thread_access(
        self,
        thread: MessageThread,
        user_id: UUID,
    ) -> bool:
        """Check if a user has access to a thread.

        User has access if they are the creator or a participant.

        Args:
            thread: The thread to check access for
            user_id: ID of the user to check

        Returns:
            True if user has access, False otherwise
        """
        # Creator always has access
        if str(thread.created_by) == str(user_id):
            return True

        # Check if user is in participants
        if thread.participants:
            for participant in thread.participants:
                if participant.get("user_id") == str(user_id):
                    return True

        return False

    async def _build_message_response(
        self,
        message: Message,
        thread: MessageThread,
    ) -> MessageResponse:
        """Build a MessageResponse from a Message model.

        Args:
            message: The message model
            thread: The thread the message belongs to

        Returns:
            MessageResponse with all message data
        """
        # Load attachments if not already loaded
        if not hasattr(message, 'attachments') or message.attachments is None:
            attachments_query = select(MessageAttachment).where(
                cast(MessageAttachment.message_id, String) == str(message.id)
            )
            attachments_result = await self.db.execute(attachments_query)
            attachments = attachments_result.scalars().all()
        else:
            attachments = message.attachments

        # Build attachment responses
        attachment_responses = []
        for attachment in attachments:
            attachment_responses.append(
                AttachmentResponse(
                    id=attachment.id,
                    message_id=attachment.message_id,
                    file_url=attachment.file_url,
                    file_type=attachment.file_type,
                    file_name=attachment.file_name,
                    file_size=attachment.file_size,
                    created_at=attachment.created_at,
                    updated_at=None,
                )
            )

        # Find sender display name from thread participants
        sender_name = None
        if thread.participants:
            for p in thread.participants:
                if p.get("user_id") == str(message.sender_id):
                    sender_name = p.get("display_name")
                    break

        return MessageResponse(
            id=message.id,
            thread_id=message.thread_id,
            sender_id=message.sender_id,
            sender_type=SenderType(message.sender_type),
            sender_name=sender_name,
            content=message.content,
            content_type=MessageContentType(message.content_type),
            is_read=message.is_read,
            attachments=attachment_responses,
            created_at=message.created_at,
            updated_at=message.updated_at,
        )

    async def _build_thread_response(
        self,
        thread: MessageThread,
        user_id: UUID,
    ) -> ThreadResponse:
        """Build a ThreadResponse from a MessageThread model.

        Args:
            thread: The thread model
            user_id: ID of the user for unread count calculation

        Returns:
            ThreadResponse with all thread data
        """
        # Get unread count for this user
        unread_query = select(func.count(Message.id)).where(
            and_(
                cast(Message.thread_id, String) == str(thread.id),
                Message.is_read == False,  # noqa: E712
                cast(Message.sender_id, String) != str(user_id),
            )
        )
        unread_result = await self.db.execute(unread_query)
        unread_count = unread_result.scalar() or 0

        # Get last message
        last_message_query = (
            select(Message)
            .where(cast(Message.thread_id, String) == str(thread.id))
            .order_by(Message.created_at.desc())
            .limit(1)
        )
        last_message_result = await self.db.execute(last_message_query)
        last_message = last_message_result.scalar_one_or_none()

        # Build participants list
        participants = []
        if thread.participants:
            for p in thread.participants:
                participants.append(
                    ThreadParticipant(
                        user_id=UUID(p["user_id"]),
                        user_type=SenderType(p["user_type"]),
                        display_name=p.get("display_name"),
                    )
                )

        return ThreadResponse(
            id=thread.id,
            subject=thread.subject,
            thread_type=ThreadType(thread.thread_type),
            child_id=thread.child_id,
            created_by=thread.created_by,
            participants=participants,
            is_active=thread.is_active,
            unread_count=unread_count,
            last_message=last_message.content[:500] if last_message else None,
            last_message_at=last_message.created_at if last_message else None,
            created_at=thread.created_at,
            updated_at=thread.updated_at,
        )

    async def _build_thread_with_messages_response(
        self,
        thread: MessageThread,
        user_id: UUID,
    ) -> ThreadWithMessagesResponse:
        """Build a ThreadWithMessagesResponse from a MessageThread model.

        Args:
            thread: The thread model
            user_id: ID of the user for unread count calculation

        Returns:
            ThreadWithMessagesResponse with thread and messages data
        """
        # Get base thread response
        base_response = await self._build_thread_response(thread, user_id)

        # Get all messages for the thread
        messages_query = (
            select(Message)
            .options(selectinload(Message.attachments))
            .where(cast(Message.thread_id, String) == str(thread.id))
            .order_by(Message.created_at.asc())
        )
        messages_result = await self.db.execute(messages_query)
        messages = messages_result.scalars().all()

        # Build message responses
        message_responses = []
        for message in messages:
            # Build attachment responses
            attachment_responses = []
            for attachment in message.attachments:
                attachment_responses.append(
                    AttachmentResponse(
                        id=attachment.id,
                        message_id=attachment.message_id,
                        file_url=attachment.file_url,
                        file_type=attachment.file_type,
                        file_name=attachment.file_name,
                        file_size=attachment.file_size,
                        created_at=attachment.created_at,
                        updated_at=None,
                    )
                )

            # Find sender display name from participants
            sender_name = None
            if thread.participants:
                for p in thread.participants:
                    if p.get("user_id") == str(message.sender_id):
                        sender_name = p.get("display_name")
                        break

            message_responses.append(
                MessageResponse(
                    id=message.id,
                    thread_id=message.thread_id,
                    sender_id=message.sender_id,
                    sender_type=SenderType(message.sender_type),
                    sender_name=sender_name,
                    content=message.content,
                    content_type=MessageContentType(message.content_type),
                    is_read=message.is_read,
                    attachments=attachment_responses,
                    created_at=message.created_at,
                    updated_at=message.updated_at,
                )
            )

        return ThreadWithMessagesResponse(
            id=base_response.id,
            subject=base_response.subject,
            thread_type=base_response.thread_type,
            child_id=base_response.child_id,
            created_by=base_response.created_by,
            participants=base_response.participants,
            is_active=base_response.is_active,
            unread_count=base_response.unread_count,
            last_message=base_response.last_message,
            last_message_at=base_response.last_message_at,
            created_at=base_response.created_at,
            updated_at=base_response.updated_at,
            messages=message_responses,
        )

    # =========================================================================
    # Mention Extraction and Private Note Handling
    # =========================================================================

    @staticmethod
    def extract_mentions(content: str) -> list[str]:
        """Extract @mentions from message content.

        Parses the message content to find @mention patterns. Supports:
        - @username format (alphanumeric with underscores)
        - @[Display Name] format (names in square brackets)
        - @uuid format (UUID strings)

        Args:
            content: The message content to parse

        Returns:
            List of unique mention identifiers (usernames or UUIDs)
        """
        if not content:
            return []

        mentions = []

        # Pattern 1: @[Display Name] - names in square brackets
        bracket_pattern = r"@\[([^\]]+)\]"
        bracket_matches = re.findall(bracket_pattern, content)
        mentions.extend(bracket_matches)

        # Pattern 2: @username - alphanumeric with underscores and hyphens
        # Must not be immediately followed by [ to avoid double-matching
        username_pattern = r"@([a-zA-Z0-9_\-]+)(?!\[)"
        username_matches = re.findall(username_pattern, content)
        mentions.extend(username_matches)

        # Pattern 3: @uuid - UUID format
        uuid_pattern = (
            r"@([0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-"
            r"[0-9a-fA-F]{4}-[0-9a-fA-F]{12})"
        )
        uuid_matches = re.findall(uuid_pattern, content)
        mentions.extend(uuid_matches)

        # Remove duplicates while preserving order
        seen = set()
        unique_mentions = []
        for mention in mentions:
            if mention.lower() not in seen:
                seen.add(mention.lower())
                unique_mentions.append(mention)

        return unique_mentions

    @staticmethod
    def extract_mention_uuids(content: str) -> list[UUID]:
        """Extract @mentions that are valid UUIDs from message content.

        Args:
            content: The message content to parse

        Returns:
            List of valid UUIDs found in @mentions
        """
        mentions = MessagingService.extract_mentions(content)
        uuids = []

        for mention in mentions:
            try:
                # Try to parse as UUID
                uuid_val = UUID(mention)
                uuids.append(uuid_val)
            except (ValueError, AttributeError):
                # Not a valid UUID, skip
                continue

        return uuids

    def resolve_mentions_to_participants(
        self,
        content: str,
        participants: list[dict],
    ) -> list[dict]:
        """Resolve @mentions to thread participants.

        Matches @mentions in content against thread participants by
        display name or user_id.

        Args:
            content: The message content with @mentions
            participants: List of participant dictionaries from thread

        Returns:
            List of matched participant dictionaries
        """
        if not content or not participants:
            return []

        mentions = self.extract_mentions(content)
        if not mentions:
            return []

        matched_participants = []
        mention_lower_set = {m.lower() for m in mentions}

        for participant in participants:
            user_id = participant.get("user_id", "")
            display_name = participant.get("display_name", "")

            # Check if user_id matches any mention
            if user_id and user_id.lower() in mention_lower_set:
                matched_participants.append(participant)
                continue

            # Check if display_name matches any mention
            if display_name and display_name.lower() in mention_lower_set:
                matched_participants.append(participant)
                continue

        return matched_participants

    @staticmethod
    def is_staff_sender(sender_type: str) -> bool:
        """Check if the sender type indicates a staff member.

        Staff members include educators, directors, and admins.

        Args:
            sender_type: The sender type string

        Returns:
            True if the sender is a staff member
        """
        staff_types = {"educator", "director", "admin"}
        return sender_type.lower() in staff_types

    def can_view_private_note(
        self,
        user_id: UUID,
        user_type: str,
        message_visible_to: Optional[list[str]] = None,
    ) -> bool:
        """Check if a user can view a private note.

        Private notes are only visible to:
        - Staff members (educators, directors, admins)
        - Users explicitly listed in the visible_to list

        Args:
            user_id: ID of the user trying to view the note
            user_type: Type of the user (parent, educator, director, admin)
            message_visible_to: Optional list of user IDs who can view the note

        Returns:
            True if the user can view the private note
        """
        # Staff members can always view private notes
        if self.is_staff_sender(user_type):
            return True

        # If visible_to is specified, check if user is in the list
        if message_visible_to:
            user_id_str = str(user_id)
            return user_id_str in message_visible_to

        return False

    def filter_messages_by_visibility(
        self,
        messages: list,
        user_id: UUID,
        user_type: str,
    ) -> list:
        """Filter messages based on visibility rules.

        Removes private notes from the list if the user does not have
        permission to view them. Messages without visibility restrictions
        are always included.

        Args:
            messages: List of message objects
            user_id: ID of the user requesting messages
            user_type: Type of the user (parent, educator, director, admin)

        Returns:
            Filtered list of messages the user can view
        """
        visible_messages = []

        for message in messages:
            # Check if message has private note metadata
            # Private notes are typically stored in message metadata or a flag
            is_private = getattr(message, "is_private_note", False)

            if not is_private:
                # Public message - always visible
                visible_messages.append(message)
                continue

            # Private note - check visibility
            visible_to = getattr(message, "visible_to", None)
            if self.can_view_private_note(user_id, user_type, visible_to):
                visible_messages.append(message)

        return visible_messages

    @staticmethod
    def format_mentions_for_display(content: str) -> str:
        """Format @mentions for display with styling hints.

        Wraps @mentions in a format that can be styled by the frontend.
        Converts @[Name] to <mention>Name</mention> format.

        Args:
            content: The message content with @mentions

        Returns:
            Content with mentions wrapped for styling
        """
        if not content:
            return content

        # Replace @[Display Name] format
        bracket_pattern = r"@\[([^\]]+)\]"
        content = re.sub(bracket_pattern, r"<mention>\1</mention>", content)

        # Replace @username format (but not @[...] since already handled)
        username_pattern = r"@([a-zA-Z0-9_\-]+)(?!\[)"
        content = re.sub(username_pattern, r"<mention>@\1</mention>", content)

        return content

    @staticmethod
    def strip_mention_formatting(content: str) -> str:
        """Strip mention formatting from content.

        Removes <mention> tags while preserving the mention text.

        Args:
            content: The content with mention formatting

        Returns:
            Content with mention formatting removed
        """
        if not content:
            return content

        pattern = r"<mention>([^<]+)</mention>"
        return re.sub(pattern, r"\1", content)

    def get_notification_recipients_from_mentions(
        self,
        content: str,
        participants: list[dict],
        sender_id: UUID,
    ) -> list[UUID]:
        """Get user IDs that should receive notifications from mentions.

        Resolves @mentions to participant UUIDs for sending notifications.
        Excludes the sender from the notification list.

        Args:
            content: The message content with @mentions
            participants: List of participant dictionaries from thread
            sender_id: ID of the message sender (excluded from recipients)

        Returns:
            List of UUIDs for users who should be notified
        """
        matched_participants = self.resolve_mentions_to_participants(
            content, participants
        )

        recipient_ids = []
        sender_id_str = str(sender_id)

        for participant in matched_participants:
            user_id = participant.get("user_id")
            if user_id and user_id != sender_id_str:
                try:
                    recipient_ids.append(UUID(user_id))
                except (ValueError, AttributeError):
                    continue

        return recipient_ids

    # =========================================================================
    # Notification Preference Operations
    # =========================================================================

    async def get_notification_preferences(
        self,
        parent_id: UUID,
    ) -> NotificationPreferenceListResponse:
        """Get all notification preferences for a parent.

        Retrieves all notification preference configurations for a parent user
        across all notification types and channels.

        Args:
            parent_id: Unique identifier of the parent user

        Returns:
            NotificationPreferenceListResponse with all preferences
        """
        query = select(NotificationPreference).where(
            cast(NotificationPreference.parent_id, String) == str(parent_id)
        ).order_by(NotificationPreference.notification_type, NotificationPreference.channel)

        result = await self.db.execute(query)
        preferences = result.scalars().all()

        # Build response list
        preference_responses = []
        for pref in preferences:
            preference_responses.append(
                NotificationPreferenceResponse(
                    id=pref.id,
                    parent_id=pref.parent_id,
                    notification_type=NotificationType(pref.notification_type),
                    channel=NotificationChannelType(pref.channel),
                    is_enabled=pref.is_enabled,
                    frequency=NotificationFrequency.IMMEDIATE,
                    quiet_hours_start=pref.quiet_hours_start,
                    quiet_hours_end=pref.quiet_hours_end,
                    created_at=pref.created_at,
                    updated_at=pref.updated_at,
                )
            )

        return NotificationPreferenceListResponse(
            parent_id=parent_id,
            preferences=preference_responses,
        )

    async def get_notification_preference(
        self,
        parent_id: UUID,
        notification_type: NotificationType,
        channel: NotificationChannelType,
    ) -> NotificationPreferenceResponse:
        """Get a specific notification preference.

        Retrieves a single notification preference for a specific type/channel
        combination.

        Args:
            parent_id: Unique identifier of the parent user
            notification_type: Type of notification to retrieve
            channel: Notification delivery channel

        Returns:
            NotificationPreferenceResponse with the preference data

        Raises:
            NotificationPreferenceNotFoundError: When the preference is not found
        """
        query = select(NotificationPreference).where(
            and_(
                cast(NotificationPreference.parent_id, String) == str(parent_id),
                NotificationPreference.notification_type == notification_type.value,
                NotificationPreference.channel == channel.value,
            )
        )

        result = await self.db.execute(query)
        preference = result.scalar_one_or_none()

        if not preference:
            raise NotificationPreferenceNotFoundError(
                f"Notification preference for type '{notification_type.value}' "
                f"and channel '{channel.value}' not found for parent {parent_id}"
            )

        return NotificationPreferenceResponse(
            id=preference.id,
            parent_id=preference.parent_id,
            notification_type=NotificationType(preference.notification_type),
            channel=NotificationChannelType(preference.channel),
            is_enabled=preference.is_enabled,
            frequency=NotificationFrequency.IMMEDIATE,
            quiet_hours_start=preference.quiet_hours_start,
            quiet_hours_end=preference.quiet_hours_end,
            created_at=preference.created_at,
            updated_at=preference.updated_at,
        )

    async def create_notification_preference(
        self,
        request: NotificationPreferenceRequest,
    ) -> NotificationPreferenceResponse:
        """Create a new notification preference.

        Creates a new notification preference for a parent. If a preference
        already exists for the same type/channel combination, it will be updated.

        Args:
            request: The notification preference creation request

        Returns:
            NotificationPreferenceResponse with the created preference data
        """
        # Check if preference already exists
        existing_query = select(NotificationPreference).where(
            and_(
                cast(NotificationPreference.parent_id, String) == str(request.parent_id),
                NotificationPreference.notification_type == request.notification_type.value,
                NotificationPreference.channel == request.channel.value,
            )
        )
        existing_result = await self.db.execute(existing_query)
        existing = existing_result.scalar_one_or_none()

        if existing:
            # Update existing preference
            existing.is_enabled = request.is_enabled
            existing.quiet_hours_start = request.quiet_hours_start
            existing.quiet_hours_end = request.quiet_hours_end
            existing.updated_at = datetime.utcnow()

            await self.db.commit()
            await self.db.refresh(existing)

            return NotificationPreferenceResponse(
                id=existing.id,
                parent_id=existing.parent_id,
                notification_type=NotificationType(existing.notification_type),
                channel=NotificationChannelType(existing.channel),
                is_enabled=existing.is_enabled,
                frequency=request.frequency,
                quiet_hours_start=existing.quiet_hours_start,
                quiet_hours_end=existing.quiet_hours_end,
                created_at=existing.created_at,
                updated_at=existing.updated_at,
            )

        # Create new preference
        preference = NotificationPreference(
            parent_id=request.parent_id,
            notification_type=request.notification_type.value,
            channel=request.channel.value,
            is_enabled=request.is_enabled,
            quiet_hours_start=request.quiet_hours_start,
            quiet_hours_end=request.quiet_hours_end,
        )

        self.db.add(preference)
        await self.db.commit()
        await self.db.refresh(preference)

        return NotificationPreferenceResponse(
            id=preference.id,
            parent_id=preference.parent_id,
            notification_type=NotificationType(preference.notification_type),
            channel=NotificationChannelType(preference.channel),
            is_enabled=preference.is_enabled,
            frequency=request.frequency,
            quiet_hours_start=preference.quiet_hours_start,
            quiet_hours_end=preference.quiet_hours_end,
            created_at=preference.created_at,
            updated_at=preference.updated_at,
        )

    async def update_notification_preference(
        self,
        parent_id: UUID,
        notification_type: NotificationType,
        channel: NotificationChannelType,
        is_enabled: Optional[bool] = None,
        frequency: Optional[NotificationFrequency] = None,
        quiet_hours_start: Optional[str] = None,
        quiet_hours_end: Optional[str] = None,
    ) -> NotificationPreferenceResponse:
        """Update a notification preference.

        Updates an existing notification preference. Only provided fields
        will be updated.

        Args:
            parent_id: Unique identifier of the parent user
            notification_type: Type of notification to update
            channel: Notification delivery channel
            is_enabled: Whether notifications are enabled for this type/channel
            frequency: How often notifications should be delivered
            quiet_hours_start: Start of quiet hours (HH:MM format)
            quiet_hours_end: End of quiet hours (HH:MM format)

        Returns:
            NotificationPreferenceResponse with the updated preference data

        Raises:
            NotificationPreferenceNotFoundError: When the preference is not found
        """
        query = select(NotificationPreference).where(
            and_(
                cast(NotificationPreference.parent_id, String) == str(parent_id),
                NotificationPreference.notification_type == notification_type.value,
                NotificationPreference.channel == channel.value,
            )
        )

        result = await self.db.execute(query)
        preference = result.scalar_one_or_none()

        if not preference:
            raise NotificationPreferenceNotFoundError(
                f"Notification preference for type '{notification_type.value}' "
                f"and channel '{channel.value}' not found for parent {parent_id}"
            )

        # Apply updates only for provided values
        if is_enabled is not None:
            preference.is_enabled = is_enabled

        if quiet_hours_start is not None:
            preference.quiet_hours_start = quiet_hours_start

        if quiet_hours_end is not None:
            preference.quiet_hours_end = quiet_hours_end

        preference.updated_at = datetime.utcnow()

        await self.db.commit()
        await self.db.refresh(preference)

        return NotificationPreferenceResponse(
            id=preference.id,
            parent_id=preference.parent_id,
            notification_type=NotificationType(preference.notification_type),
            channel=NotificationChannelType(preference.channel),
            is_enabled=preference.is_enabled,
            frequency=frequency or NotificationFrequency.IMMEDIATE,
            quiet_hours_start=preference.quiet_hours_start,
            quiet_hours_end=preference.quiet_hours_end,
            created_at=preference.created_at,
            updated_at=preference.updated_at,
        )

    async def delete_notification_preference(
        self,
        parent_id: UUID,
        notification_type: NotificationType,
        channel: NotificationChannelType,
    ) -> bool:
        """Delete a notification preference.

        Removes a notification preference for a specific type/channel combination.

        Args:
            parent_id: Unique identifier of the parent user
            notification_type: Type of notification to delete
            channel: Notification delivery channel

        Returns:
            True if the preference was deleted

        Raises:
            NotificationPreferenceNotFoundError: When the preference is not found
        """
        query = select(NotificationPreference).where(
            and_(
                cast(NotificationPreference.parent_id, String) == str(parent_id),
                NotificationPreference.notification_type == notification_type.value,
                NotificationPreference.channel == channel.value,
            )
        )

        result = await self.db.execute(query)
        preference = result.scalar_one_or_none()

        if not preference:
            raise NotificationPreferenceNotFoundError(
                f"Notification preference for type '{notification_type.value}' "
                f"and channel '{channel.value}' not found for parent {parent_id}"
            )

        await self.db.delete(preference)
        await self.db.commit()

        return True

    async def get_or_create_default_preferences(
        self,
        parent_id: UUID,
    ) -> NotificationPreferenceListResponse:
        """Get existing or create default notification preferences for a parent.

        If preferences exist for the parent, returns them. Otherwise, creates
        default preferences for all notification types and channels with
        sensible defaults.

        Args:
            parent_id: Unique identifier of the parent user

        Returns:
            NotificationPreferenceListResponse with all preferences
        """
        # Check if any preferences exist
        existing_query = select(NotificationPreference).where(
            cast(NotificationPreference.parent_id, String) == str(parent_id)
        )
        existing_result = await self.db.execute(existing_query)
        existing_preferences = existing_result.scalars().all()

        if existing_preferences:
            # Return existing preferences
            return await self.get_notification_preferences(parent_id)

        # Create default preferences for all type/channel combinations
        default_preferences = []

        # Default settings: enable email and push for all types, SMS only for urgent
        default_config = {
            NotificationType.MESSAGE: {
                NotificationChannelType.EMAIL: True,
                NotificationChannelType.PUSH: True,
                NotificationChannelType.SMS: False,
            },
            NotificationType.DAILY_LOG: {
                NotificationChannelType.EMAIL: True,
                NotificationChannelType.PUSH: True,
                NotificationChannelType.SMS: False,
            },
            NotificationType.URGENT: {
                NotificationChannelType.EMAIL: True,
                NotificationChannelType.PUSH: True,
                NotificationChannelType.SMS: True,
            },
            NotificationType.ADMIN: {
                NotificationChannelType.EMAIL: True,
                NotificationChannelType.PUSH: True,
                NotificationChannelType.SMS: False,
            },
        }

        for notification_type, channels in default_config.items():
            for channel, is_enabled in channels.items():
                preference = NotificationPreference(
                    parent_id=parent_id,
                    notification_type=notification_type.value,
                    channel=channel.value,
                    is_enabled=is_enabled,
                    quiet_hours_start=None,
                    quiet_hours_end=None,
                )
                self.db.add(preference)
                default_preferences.append(preference)

        await self.db.commit()

        # Refresh all preferences to get IDs
        for pref in default_preferences:
            await self.db.refresh(pref)

        # Build response
        preference_responses = []
        for pref in default_preferences:
            preference_responses.append(
                NotificationPreferenceResponse(
                    id=pref.id,
                    parent_id=pref.parent_id,
                    notification_type=NotificationType(pref.notification_type),
                    channel=NotificationChannelType(pref.channel),
                    is_enabled=pref.is_enabled,
                    frequency=NotificationFrequency.IMMEDIATE,
                    quiet_hours_start=pref.quiet_hours_start,
                    quiet_hours_end=pref.quiet_hours_end,
                    created_at=pref.created_at,
                    updated_at=pref.updated_at,
                )
            )

        return NotificationPreferenceListResponse(
            parent_id=parent_id,
            preferences=preference_responses,
        )

    async def set_quiet_hours(
        self,
        parent_id: UUID,
        quiet_hours_start: Optional[str],
        quiet_hours_end: Optional[str],
    ) -> int:
        """Set quiet hours for all notification preferences.

        Updates quiet hours across all notification preferences for a parent.
        During quiet hours, notifications may be delayed or silenced.

        Args:
            parent_id: Unique identifier of the parent user
            quiet_hours_start: Start of quiet hours (HH:MM format, e.g., "22:00")
            quiet_hours_end: End of quiet hours (HH:MM format, e.g., "07:00")

        Returns:
            Number of preferences updated
        """
        stmt = (
            update(NotificationPreference)
            .where(
                cast(NotificationPreference.parent_id, String) == str(parent_id)
            )
            .values(
                quiet_hours_start=quiet_hours_start,
                quiet_hours_end=quiet_hours_end,
                updated_at=datetime.utcnow(),
            )
        )

        result = await self.db.execute(stmt)
        await self.db.commit()

        return result.rowcount

    async def is_notification_enabled(
        self,
        parent_id: UUID,
        notification_type: NotificationType,
        channel: NotificationChannelType,
    ) -> bool:
        """Check if a specific notification type/channel is enabled.

        Utility method to quickly check if notifications should be sent
        for a specific type and channel combination.

        Args:
            parent_id: Unique identifier of the parent user
            notification_type: Type of notification to check
            channel: Notification delivery channel

        Returns:
            True if notifications are enabled, False otherwise
        """
        try:
            preference = await self.get_notification_preference(
                parent_id=parent_id,
                notification_type=notification_type,
                channel=channel,
            )
            return preference.is_enabled
        except NotificationPreferenceNotFoundError:
            # Default to enabled if no preference exists
            return True
