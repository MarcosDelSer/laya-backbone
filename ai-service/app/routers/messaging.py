"""FastAPI router for parent-educator messaging endpoints.

Provides endpoints for managing message threads between parents and educators.
Supports thread creation, listing, retrieval, and archival with participant
access control and unread message tracking.
"""

from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.messaging import (
    MarkAsReadRequest,
    MessageCreate,
    MessageListResponse,
    MessageResponse,
    NotificationChannelType,
    NotificationPreferenceListResponse,
    NotificationPreferenceRequest,
    NotificationPreferenceResponse,
    NotificationType,
    SenderType,
    ThreadCreate,
    ThreadListResponse,
    ThreadResponse,
    ThreadType,
    ThreadUpdate,
    ThreadWithMessagesResponse,
)
from app.services.messaging_service import (
    InvalidThreadError,
    MessageNotFoundError,
    MessagingService,
    MessagingServiceError,
    NotificationPreferenceNotFoundError,
    ThreadNotFoundError,
    UnauthorizedAccessError,
)

router = APIRouter()


def _get_user_type(current_user: dict[str, Any]) -> SenderType:
    """Extract sender type from user token payload.

    Maps the user's role from the JWT token to the appropriate SenderType enum.

    Args:
        current_user: Decoded JWT token payload

    Returns:
        SenderType corresponding to the user's role
    """
    role = current_user.get("role", "parent").lower()

    role_mapping = {
        "parent": SenderType.PARENT,
        "educator": SenderType.EDUCATOR,
        "director": SenderType.DIRECTOR,
        "admin": SenderType.ADMIN,
    }

    return role_mapping.get(role, SenderType.PARENT)


@router.post("/threads", response_model=ThreadResponse)
async def create_thread(
    request: ThreadCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ThreadResponse:
    """Create a new message thread.

    Creates a new conversation thread between parents and educators.
    The creator is automatically added as a participant if not already
    included in the participants list.

    Args:
        request: The thread creation request containing subject, type,
                 participants, and optional initial message
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ThreadResponse containing:
        - id: Unique identifier of the created thread
        - subject: Thread subject/title
        - thread_type: Type of thread (daily_log, urgent, serious, admin)
        - child_id: Associated child ID (if applicable)
        - participants: List of thread participants
        - unread_count: Number of unread messages (0 for new thread)
        - created_at: Thread creation timestamp

    Raises:
        HTTPException 400: When thread data is invalid
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])
        user_type = _get_user_type(current_user)

        return await service.create_thread(
            request=request,
            user_id=user_id,
            user_type=user_type,
        )
    except InvalidThreadError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.get("/threads", response_model=ThreadListResponse)
async def list_threads(
    child_id: Optional[UUID] = Query(
        default=None,
        description="Filter threads by child ID",
    ),
    thread_type: Optional[ThreadType] = Query(
        default=None,
        description="Filter threads by type",
    ),
    include_archived: bool = Query(
        default=False,
        description="Include archived (inactive) threads",
    ),
    limit: int = Query(
        default=50,
        ge=1,
        le=100,
        description="Maximum number of threads to return",
    ),
    offset: int = Query(
        default=0,
        ge=0,
        description="Number of threads to skip for pagination",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ThreadListResponse:
    """List message threads for the current user.

    Retrieves all threads the user participates in, with optional
    filtering by child, type, and active status. Results are ordered
    by most recent activity.

    Args:
        child_id: Optional filter by child ID
        thread_type: Optional filter by thread type (daily_log, urgent, etc.)
        include_archived: Whether to include archived threads (default: False)
        limit: Maximum number of threads to return (default: 50, max: 100)
        offset: Number of threads to skip for pagination (default: 0)
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ThreadListResponse containing:
        - threads: List of ThreadResponse objects
        - total: Total number of matching threads
        - limit: Number of items per page
        - offset: Current offset

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])

        threads = await service.list_threads_for_user(
            user_id=user_id,
            child_id=child_id,
            thread_type=thread_type,
            include_archived=include_archived,
            limit=limit,
            offset=offset,
        )

        return ThreadListResponse(
            threads=threads,
            total=len(threads),
            limit=limit,
            skip=offset,
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.get("/threads/{thread_id}", response_model=ThreadWithMessagesResponse)
async def get_thread(
    thread_id: UUID,
    include_messages: bool = Query(
        default=True,
        description="Include messages in the response",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ThreadWithMessagesResponse:
    """Get a message thread by ID.

    Retrieves a thread and optionally its messages. Validates that the
    current user has permission to access the thread.

    Args:
        thread_id: Unique identifier of the thread
        include_messages: Whether to include messages (default: True)
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ThreadWithMessagesResponse containing:
        - id: Thread identifier
        - subject: Thread subject/title
        - thread_type: Type of thread
        - participants: List of participants
        - messages: List of messages (if include_messages=True)
        - unread_count: Number of unread messages
        - last_message_at: Timestamp of most recent message

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user does not have access to the thread
        HTTPException 404: When thread is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])

        result = await service.get_thread(
            thread_id=thread_id,
            user_id=user_id,
            include_messages=include_messages,
        )

        # Always return ThreadWithMessagesResponse for consistency
        if isinstance(result, ThreadWithMessagesResponse):
            return result

        # Convert ThreadResponse to ThreadWithMessagesResponse
        return ThreadWithMessagesResponse(
            id=result.id,
            subject=result.subject,
            thread_type=result.thread_type,
            child_id=result.child_id,
            created_by=result.created_by,
            participants=result.participants,
            is_active=result.is_active,
            unread_count=result.unread_count,
            last_message=result.last_message,
            last_message_at=result.last_message_at,
            created_at=result.created_at,
            updated_at=result.updated_at,
            messages=[],
        )
    except ThreadNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.patch("/threads/{thread_id}", response_model=ThreadResponse)
async def update_thread(
    thread_id: UUID,
    request: ThreadUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ThreadResponse:
    """Update a message thread.

    Updates thread properties like subject or active status.
    Only participants can update a thread.

    Args:
        thread_id: Unique identifier of the thread
        request: The thread update request with fields to update
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ThreadResponse containing the updated thread data

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user does not have access to the thread
        HTTPException 404: When thread is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])

        return await service.update_thread(
            thread_id=thread_id,
            request=request,
            user_id=user_id,
        )
    except ThreadNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.delete("/threads/{thread_id}", response_model=ThreadResponse)
async def archive_thread(
    thread_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ThreadResponse:
    """Archive a message thread.

    Marks a thread as archived (inactive). Archived threads are hidden
    from the default thread list but can still be retrieved with the
    include_archived parameter.

    Args:
        thread_id: Unique identifier of the thread to archive
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ThreadResponse containing the archived thread data

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user does not have access to the thread
        HTTPException 404: When thread is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])

        return await service.archive_thread(
            thread_id=thread_id,
            user_id=user_id,
        )
    except ThreadNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


# =============================================================================
# Message Endpoints
# =============================================================================


@router.post("/threads/{thread_id}/messages", response_model=MessageResponse)
async def send_message(
    thread_id: UUID,
    request: MessageCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MessageResponse:
    """Send a message in a thread.

    Creates a new message within an existing conversation thread. The message
    is associated with the current user as the sender and can include optional
    attachments.

    Args:
        thread_id: Unique identifier of the thread to send the message in
        request: The message creation request containing content and attachments
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        MessageResponse containing:
        - id: Unique identifier of the created message
        - thread_id: Thread the message belongs to
        - sender_id: ID of the message sender
        - sender_type: Type of sender (parent, educator, director, admin)
        - content: The message content
        - content_type: Type of content (text, rich_text)
        - is_read: Whether the message has been read (False for new messages)
        - attachments: List of attachments included with the message
        - created_at: Message creation timestamp

    Raises:
        HTTPException 400: When the thread is archived or message data is invalid
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user does not have access to the thread
        HTTPException 404: When thread is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        sender_id = UUID(current_user["sub"])
        sender_type = _get_user_type(current_user)

        return await service.send_message(
            thread_id=thread_id,
            request=request,
            sender_id=sender_id,
            sender_type=sender_type,
        )
    except ThreadNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except InvalidThreadError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.get("/threads/{thread_id}/messages", response_model=MessageListResponse)
async def list_messages(
    thread_id: UUID,
    limit: int = Query(
        default=50,
        ge=1,
        le=100,
        description="Maximum number of messages to return",
    ),
    offset: int = Query(
        default=0,
        ge=0,
        description="Number of messages to skip for pagination",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MessageListResponse:
    """List messages in a thread.

    Retrieves messages from a conversation thread with pagination support.
    Messages are returned in chronological order (oldest first).

    Args:
        thread_id: Unique identifier of the thread
        limit: Maximum number of messages to return (default: 50, max: 100)
        offset: Number of messages to skip for pagination (default: 0)
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        MessageListResponse containing:
        - messages: List of MessageResponse objects
        - total: Total number of messages in the thread
        - limit: Number of items per page
        - offset: Current offset

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user does not have access to the thread
        HTTPException 404: When thread is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])

        messages = await service.list_messages(
            thread_id=thread_id,
            user_id=user_id,
            limit=limit,
            offset=offset,
        )

        return MessageListResponse(
            messages=messages,
            total=len(messages),
            limit=limit,
            skip=offset,
        )
    except ThreadNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.patch("/messages/read")
async def mark_messages_as_read(
    request: MarkAsReadRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict[str, int]:
    """Mark messages as read.

    Updates the read status of one or more messages. Only marks messages
    that the user has access to and that were not sent by them.

    Args:
        request: The mark-as-read request containing message IDs
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Dictionary containing:
        - marked_count: Number of messages successfully marked as read

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])

        marked_count = await service.mark_messages_as_read(
            message_ids=request.message_ids,
            user_id=user_id,
        )

        return {"marked_count": marked_count}
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.patch("/threads/{thread_id}/read")
async def mark_thread_as_read(
    thread_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict[str, int]:
    """Mark all messages in a thread as read.

    Updates the read status of all unread messages in a thread that were
    not sent by the current user.

    Args:
        thread_id: Unique identifier of the thread
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Dictionary containing:
        - marked_count: Number of messages successfully marked as read

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user does not have access to the thread
        HTTPException 404: When thread is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])

        marked_count = await service.mark_thread_as_read(
            thread_id=thread_id,
            user_id=user_id,
        )

        return {"marked_count": marked_count}
    except ThreadNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.get("/messages/{message_id}", response_model=MessageResponse)
async def get_message(
    message_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> MessageResponse:
    """Get a message by ID.

    Retrieves a single message by its unique identifier. Validates that
    the current user has permission to access the message.

    Args:
        message_id: Unique identifier of the message
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        MessageResponse containing all message data

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user does not have access to the message
        HTTPException 404: When message is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])

        return await service.get_message(
            message_id=message_id,
            user_id=user_id,
        )
    except MessageNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except ThreadNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


# =============================================================================
# Notification Preference Endpoints
# =============================================================================


@router.post("/notifications/preferences", response_model=NotificationPreferenceResponse)
async def create_or_update_notification_preference(
    request: NotificationPreferenceRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> NotificationPreferenceResponse:
    """Create or update a notification preference.

    Sets a parent's notification preference for a specific notification type
    and delivery channel. If a preference already exists for the same
    type/channel combination, it will be updated.

    Args:
        request: The notification preference request containing parent_id,
                 notification_type, channel, is_enabled, frequency, and quiet hours
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        NotificationPreferenceResponse containing:
        - id: Unique identifier of the preference
        - parent_id: The parent's identifier
        - notification_type: Type of notification configured
        - channel: Notification delivery channel
        - is_enabled: Whether notifications are enabled
        - frequency: Notification delivery frequency
        - quiet_hours_start: Start of quiet hours (if set)
        - quiet_hours_end: End of quiet hours (if set)

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])
        user_role = current_user.get("role")
        return await service.create_notification_preference(request, user_id, user_role)
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.get(
    "/notifications/preferences/{parent_id}",
    response_model=NotificationPreferenceListResponse,
)
async def get_notification_preferences(
    parent_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> NotificationPreferenceListResponse:
    """Get all notification preferences for a parent.

    Retrieves all notification preference configurations for a parent user
    across all notification types and channels.

    Args:
        parent_id: Unique identifier of the parent user
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        NotificationPreferenceListResponse containing:
        - parent_id: The parent's identifier
        - preferences: List of notification preference configurations

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])
        user_role = current_user.get("role")
        return await service.get_notification_preferences(parent_id, user_id, user_role)
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.get(
    "/notifications/preferences/{parent_id}/{notification_type}/{channel}",
    response_model=NotificationPreferenceResponse,
)
async def get_notification_preference(
    parent_id: UUID,
    notification_type: NotificationType,
    channel: NotificationChannelType,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> NotificationPreferenceResponse:
    """Get a specific notification preference.

    Retrieves a single notification preference for a specific notification
    type and channel combination.

    Args:
        parent_id: Unique identifier of the parent user
        notification_type: Type of notification (message, daily_log, urgent, admin)
        channel: Notification delivery channel (email, push, sms)
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        NotificationPreferenceResponse with the preference configuration

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the preference is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])
        user_role = current_user.get("role")
        return await service.get_notification_preference(
            parent_id=parent_id,
            notification_type=notification_type,
            channel=channel,
            user_id=user_id,
            user_role=user_role,
        )
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except NotificationPreferenceNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.delete(
    "/notifications/preferences/{parent_id}/{notification_type}/{channel}",
)
async def delete_notification_preference(
    parent_id: UUID,
    notification_type: NotificationType,
    channel: NotificationChannelType,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict[str, bool]:
    """Delete a notification preference.

    Removes a notification preference for a specific type/channel combination.
    After deletion, the system will use default notification behavior for
    that type/channel.

    Args:
        parent_id: Unique identifier of the parent user
        notification_type: Type of notification (message, daily_log, urgent, admin)
        channel: Notification delivery channel (email, push, sms)
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Dictionary containing:
        - deleted: True if the preference was successfully deleted

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 404: When the preference is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])
        user_role = current_user.get("role")
        await service.delete_notification_preference(
            parent_id=parent_id,
            notification_type=notification_type,
            channel=channel,
            user_id=user_id,
            user_role=user_role,
        )
        return {"deleted": True}
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except NotificationPreferenceNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.post(
    "/notifications/preferences/{parent_id}/defaults",
    response_model=NotificationPreferenceListResponse,
)
async def get_or_create_default_preferences(
    parent_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> NotificationPreferenceListResponse:
    """Get existing or create default notification preferences.

    If preferences exist for the parent, returns them. Otherwise, creates
    default preferences for all notification types and channels with
    sensible defaults:
    - Email and push enabled for all notification types
    - SMS enabled only for urgent notifications
    - No quiet hours configured

    Args:
        parent_id: Unique identifier of the parent user
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        NotificationPreferenceListResponse containing:
        - parent_id: The parent's identifier
        - preferences: List of notification preference configurations

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])
        user_role = current_user.get("role")
        return await service.get_or_create_default_preferences(parent_id, user_id, user_role)
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.patch("/notifications/preferences/{parent_id}/quiet-hours")
async def set_quiet_hours(
    parent_id: UUID,
    quiet_hours_start: Optional[str] = Query(
        default=None,
        pattern=r"^([01]?[0-9]|2[0-3]):[0-5][0-9]$",
        description="Start of quiet hours in HH:MM format (e.g., '22:00')",
    ),
    quiet_hours_end: Optional[str] = Query(
        default=None,
        pattern=r"^([01]?[0-9]|2[0-3]):[0-5][0-9]$",
        description="End of quiet hours in HH:MM format (e.g., '07:00')",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict[str, int]:
    """Set quiet hours for all notification preferences.

    Updates quiet hours across all notification preferences for a parent.
    During quiet hours, notifications may be delayed or silenced depending
    on the notification type priority.

    Args:
        parent_id: Unique identifier of the parent user
        quiet_hours_start: Start of quiet hours (HH:MM format, e.g., "22:00")
        quiet_hours_end: End of quiet hours (HH:MM format, e.g., "07:00")
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Dictionary containing:
        - updated_count: Number of preferences updated

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])
        user_role = current_user.get("role")
        updated_count = await service.set_quiet_hours(
            parent_id=parent_id,
            quiet_hours_start=quiet_hours_start,
            quiet_hours_end=quiet_hours_end,
            user_id=user_id,
            user_role=user_role,
        )
        return {"updated_count": updated_count}
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )
