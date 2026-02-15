"""Messaging domain schemas for LAYA AI Service.

Defines Pydantic schemas for parent-educator messaging requests and responses.
The messaging system enables direct communication between parents and
educators/directors, with support for threads, attachments, and notification preferences.
"""

from datetime import datetime
from enum import Enum
from typing import Optional
from uuid import UUID

from pydantic import Field

from app.schemas.base import BaseResponse, BaseSchema, PaginatedResponse


class SenderType(str, Enum):
    """Types of message senders.

    Identifies the role of the user sending a message to determine
    display styling and permissions.

    Attributes:
        PARENT: Parent or guardian of a child
        EDUCATOR: Teacher or caregiver
        DIRECTOR: Center director or administrator
        ADMIN: System administrator
    """

    PARENT = "parent"
    EDUCATOR = "educator"
    DIRECTOR = "director"
    ADMIN = "admin"


class ThreadType(str, Enum):
    """Types of message threads.

    Categorizes conversation threads by their purpose to enable
    filtering and prioritization.

    Attributes:
        DAILY_LOG: Daily activity and progress updates
        URGENT: Time-sensitive communications
        SERIOUS: Important but non-urgent matters
        ADMIN: Administrative communications
    """

    DAILY_LOG = "daily_log"
    URGENT = "urgent"
    SERIOUS = "serious"
    ADMIN = "admin"


class MessageContentType(str, Enum):
    """Content types for messages.

    Defines the format of message content to enable proper rendering.

    Attributes:
        TEXT: Plain text content
        RICH_TEXT: Rich text with formatting (HTML/Markdown)
    """

    TEXT = "text"
    RICH_TEXT = "rich_text"


# =============================================================================
# Participant Schemas
# =============================================================================


class ThreadParticipant(BaseSchema):
    """Schema for a thread participant.

    Represents a user participating in a message thread.

    Attributes:
        user_id: Unique identifier of the participant
        user_type: Type/role of the participant
        display_name: Name to display for the participant
    """

    user_id: UUID = Field(
        ...,
        description="Unique identifier of the participant",
    )
    user_type: SenderType = Field(
        ...,
        description="Type/role of the participant",
    )
    display_name: Optional[str] = Field(
        default=None,
        max_length=200,
        description="Name to display for the participant",
    )


# =============================================================================
# Attachment Schemas
# =============================================================================


class AttachmentBase(BaseSchema):
    """Base schema for message attachments.

    Contains common fields for attachment data.

    Attributes:
        file_url: URL or path to the stored file
        file_type: MIME type or category of the file
        file_name: Original name of the uploaded file
        file_size: Size of the file in bytes
    """

    file_url: str = Field(
        ...,
        max_length=500,
        description="URL or path to the stored file",
    )
    file_type: str = Field(
        ...,
        max_length=100,
        description="MIME type or category of the file",
    )
    file_name: str = Field(
        ...,
        max_length=255,
        description="Original name of the uploaded file",
    )
    file_size: Optional[int] = Field(
        default=None,
        ge=0,
        description="Size of the file in bytes",
    )


class AttachmentCreate(AttachmentBase):
    """Request schema for creating a message attachment.

    Used when uploading files to attach to a message.
    """

    pass


class AttachmentResponse(AttachmentBase, BaseResponse):
    """Response schema for a message attachment.

    Includes all base attachment fields plus ID and timestamps.

    Attributes:
        message_id: ID of the message this attachment belongs to
    """

    message_id: UUID = Field(
        ...,
        description="ID of the message this attachment belongs to",
    )


# =============================================================================
# Request Schemas
# =============================================================================


class ThreadCreate(BaseSchema):
    """Request schema for creating a new message thread.

    Used to initiate a new conversation between parents and educators.

    Attributes:
        subject: Subject/title of the conversation thread
        thread_type: Type of thread (daily_log, urgent, serious, admin)
        child_id: ID of the child this thread is about (optional for admin threads)
        participants: List of participants to include in the thread
        initial_message: Optional initial message content to include
    """

    subject: str = Field(
        ...,
        min_length=1,
        max_length=255,
        description="Subject/title of the conversation thread",
    )
    thread_type: ThreadType = Field(
        default=ThreadType.DAILY_LOG,
        description="Type of thread",
    )
    child_id: Optional[UUID] = Field(
        default=None,
        description="ID of the child this thread is about (optional for admin threads)",
    )
    participants: list[ThreadParticipant] = Field(
        default_factory=list,
        description="List of participants to include in the thread",
    )
    initial_message: Optional[str] = Field(
        default=None,
        max_length=10000,
        description="Optional initial message content to include",
    )


class ThreadUpdate(BaseSchema):
    """Request schema for updating a message thread.

    Used to modify thread properties like subject or active status.

    Attributes:
        subject: Updated subject/title for the thread
        is_active: Whether the thread should be active (not archived)
    """

    subject: Optional[str] = Field(
        default=None,
        min_length=1,
        max_length=255,
        description="Updated subject/title for the thread",
    )
    is_active: Optional[bool] = Field(
        default=None,
        description="Whether the thread should be active (not archived)",
    )


class MessageCreate(BaseSchema):
    """Request schema for creating a new message.

    Used to send a message within an existing thread.

    Attributes:
        content: The message content
        content_type: Type of content (text, rich_text)
        attachments: Optional list of attachments to include
    """

    content: str = Field(
        ...,
        min_length=1,
        max_length=10000,
        description="The message content",
    )
    content_type: MessageContentType = Field(
        default=MessageContentType.TEXT,
        description="Type of content (text, rich_text)",
    )
    attachments: Optional[list[AttachmentCreate]] = Field(
        default=None,
        description="Optional list of attachments to include",
    )


class MarkAsReadRequest(BaseSchema):
    """Request schema for marking messages as read.

    Used to update read status for one or more messages.

    Attributes:
        message_ids: List of message IDs to mark as read
    """

    message_ids: list[UUID] = Field(
        ...,
        min_length=1,
        description="List of message IDs to mark as read",
    )


# =============================================================================
# Response Schemas
# =============================================================================


class MessageResponse(BaseResponse):
    """Response schema for a message.

    Contains the complete message data including sender information
    and attachments.

    Attributes:
        thread_id: ID of the thread this message belongs to
        sender_id: ID of the user who sent the message
        sender_type: Type of sender (parent, educator, director, admin)
        sender_name: Display name of the sender
        content: The message content
        content_type: Type of content (text, rich_text)
        is_read: Whether the message has been read by recipients
        attachments: List of attachments included with the message
    """

    thread_id: UUID = Field(
        ...,
        description="ID of the thread this message belongs to",
    )
    sender_id: UUID = Field(
        ...,
        description="ID of the user who sent the message",
    )
    sender_type: SenderType = Field(
        ...,
        description="Type of sender (parent, educator, director, admin)",
    )
    sender_name: Optional[str] = Field(
        default=None,
        max_length=200,
        description="Display name of the sender",
    )
    content: str = Field(
        ...,
        description="The message content",
    )
    content_type: MessageContentType = Field(
        ...,
        description="Type of content (text, rich_text)",
    )
    is_read: bool = Field(
        default=False,
        description="Whether the message has been read by recipients",
    )
    attachments: list[AttachmentResponse] = Field(
        default_factory=list,
        description="List of attachments included with the message",
    )


class ThreadResponse(BaseResponse):
    """Response schema for a message thread.

    Contains the complete thread data including participant information
    and optional message preview.

    Attributes:
        subject: Subject/title of the conversation thread
        thread_type: Type of thread (daily_log, urgent, serious, admin)
        child_id: ID of the child this thread is about
        created_by: ID of the user who created the thread
        participants: List of participants in the thread
        is_active: Whether the thread is active (not archived)
        unread_count: Number of unread messages in the thread
        last_message: Preview of the most recent message
        last_message_at: Timestamp of the most recent message
    """

    subject: str = Field(
        ...,
        description="Subject/title of the conversation thread",
    )
    thread_type: ThreadType = Field(
        ...,
        description="Type of thread",
    )
    child_id: Optional[UUID] = Field(
        default=None,
        description="ID of the child this thread is about",
    )
    created_by: UUID = Field(
        ...,
        description="ID of the user who created the thread",
    )
    participants: list[ThreadParticipant] = Field(
        default_factory=list,
        description="List of participants in the thread",
    )
    is_active: bool = Field(
        default=True,
        description="Whether the thread is active (not archived)",
    )
    unread_count: int = Field(
        default=0,
        ge=0,
        description="Number of unread messages in the thread",
    )
    last_message: Optional[str] = Field(
        default=None,
        max_length=500,
        description="Preview of the most recent message",
    )
    last_message_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp of the most recent message",
    )


class ThreadWithMessagesResponse(ThreadResponse):
    """Response schema for a thread with its messages.

    Extends ThreadResponse with the full list of messages.

    Attributes:
        messages: List of messages in the thread
    """

    messages: list[MessageResponse] = Field(
        default_factory=list,
        description="List of messages in the thread",
    )


class ThreadListResponse(PaginatedResponse):
    """Response schema for a list of message threads.

    Contains paginated list of threads with metadata.

    Attributes:
        threads: List of message threads
    """

    threads: list[ThreadResponse] = Field(
        default_factory=list,
        description="List of message threads",
    )


class MessageListResponse(PaginatedResponse):
    """Response schema for a list of messages.

    Contains paginated list of messages with metadata.

    Attributes:
        messages: List of messages
    """

    messages: list[MessageResponse] = Field(
        default_factory=list,
        description="List of messages",
    )


class UnreadCountResponse(BaseSchema):
    """Response schema for unread message count.

    Provides counts of unread messages for the user.

    Attributes:
        total_unread: Total number of unread messages
        threads_with_unread: Number of threads with unread messages
    """

    total_unread: int = Field(
        ...,
        ge=0,
        description="Total number of unread messages",
    )
    threads_with_unread: int = Field(
        ...,
        ge=0,
        description="Number of threads with unread messages",
    )
