"""SQLAlchemy models for parent-educator messaging domain.

Defines database models for message threads, messages, and attachments.
These models support the Parent Portal Messaging system that enables
direct communication between parents and educators/directors.
"""

from datetime import datetime
from typing import Optional
from uuid import uuid4

from sqlalchemy import Boolean, DateTime, ForeignKey, Index, String, Text
from sqlalchemy.dialects.postgresql import JSON, UUID
from sqlalchemy.orm import Mapped, mapped_column, relationship

from app.models.base import Base


class MessageThread(Base):
    """Store message threads for parent-educator conversations.

    A thread represents a conversation context, grouping related messages
    together. Threads can be organized by type (daily_log, urgent, serious, admin)
    and are associated with a specific child.

    Attributes:
        id: Unique identifier for the thread
        subject: Subject/title of the conversation thread
        thread_type: Type of thread (daily_log, urgent, serious, admin)
        child_id: ID of the child this thread is about (optional for admin threads)
        created_by: ID of the user who created the thread
        participants: JSON list of participant user IDs and their types
        is_active: Whether the thread is active (not archived)
        created_at: Timestamp when the thread was created
        updated_at: Timestamp when the thread was last updated
    """

    __tablename__ = "message_threads"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    subject: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
    )
    thread_type: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        default="daily_log",
    )
    child_id: Mapped[Optional[UUID]] = mapped_column(
        UUID(as_uuid=True),
        nullable=True,
        index=True,
    )
    created_by: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
    )
    participants: Mapped[Optional[dict]] = mapped_column(
        JSON,
        nullable=True,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )
    updated_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
        nullable=True,
    )

    # Relationships
    messages: Mapped[list["Message"]] = relationship(
        "Message",
        back_populates="thread",
        cascade="all, delete-orphan",
        lazy="dynamic",
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        Index("ix_message_threads_created_by", "created_by"),
        Index("ix_message_threads_child_type", "child_id", "thread_type"),
    )


class Message(Base):
    """Store individual messages within a thread.

    Each message belongs to a thread and contains the actual content
    of the communication. Messages track sender information and read status.

    Attributes:
        id: Unique identifier for the message
        thread_id: ID of the thread this message belongs to
        sender_id: ID of the user who sent the message
        sender_type: Type of sender (parent, educator, director, admin)
        content: The message content
        content_type: Type of content (text, rich_text)
        is_read: Whether the message has been read by recipients
        created_at: Timestamp when the message was created
        updated_at: Timestamp when the message was last updated
    """

    __tablename__ = "messages"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    thread_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("message_threads.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    sender_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    sender_type: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
    )
    content: Mapped[str] = mapped_column(
        Text,
        nullable=False,
    )
    content_type: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        default="text",
    )
    is_read: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=False,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )
    updated_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
        nullable=True,
    )

    # Relationships
    thread: Mapped["MessageThread"] = relationship(
        "MessageThread",
        back_populates="messages",
    )
    attachments: Mapped[list["MessageAttachment"]] = relationship(
        "MessageAttachment",
        back_populates="message",
        cascade="all, delete-orphan",
        lazy="selectin",
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        Index("ix_messages_thread_created", "thread_id", "created_at"),
    )


class MessageAttachment(Base):
    """Store file attachments for messages.

    Attachments allow users to share files, images, and other media
    within their messages. Each attachment is linked to a specific message.

    Attributes:
        id: Unique identifier for the attachment
        message_id: ID of the message this attachment belongs to
        file_url: URL or path to the stored file
        file_type: MIME type or category of the file
        file_name: Original name of the uploaded file
        file_size: Size of the file in bytes (optional)
        created_at: Timestamp when the attachment was created
    """

    __tablename__ = "message_attachments"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    message_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        ForeignKey("messages.id", ondelete="CASCADE"),
        nullable=False,
        index=True,
    )
    file_url: Mapped[str] = mapped_column(
        String(500),
        nullable=False,
    )
    file_type: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
    )
    file_name: Mapped[str] = mapped_column(
        String(255),
        nullable=False,
    )
    file_size: Mapped[Optional[int]] = mapped_column(
        nullable=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )

    # Relationships
    message: Mapped["Message"] = relationship(
        "Message",
        back_populates="attachments",
    )


class NotificationChannel(Base):
    """Store available notification channels.

    Defines the different channels through which notifications can be
    delivered to parents (e.g., email, push notifications, SMS).

    Attributes:
        id: Unique identifier for the channel
        name: Name of the channel (email, push, sms)
        display_name: Human-readable name for the channel
        is_active: Whether this channel is currently available
        created_at: Timestamp when the channel was created
        updated_at: Timestamp when the channel was last updated
    """

    __tablename__ = "notification_channels"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    name: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
        unique=True,
    )
    display_name: Mapped[str] = mapped_column(
        String(100),
        nullable=False,
    )
    is_active: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )
    updated_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
        nullable=True,
    )


class NotificationPreference(Base):
    """Store parent notification preferences.

    Stores parent preferences for how they want to receive notifications
    about messages, daily logs, and urgent communications. Each parent
    can configure preferences per notification type and channel.

    Attributes:
        id: Unique identifier for the preference
        parent_id: ID of the parent user
        notification_type: Type of notification (message, daily_log, urgent, admin)
        channel: Notification channel (email, push, sms)
        is_enabled: Whether notifications are enabled for this type/channel
        quiet_hours_start: Start of quiet hours (no notifications)
        quiet_hours_end: End of quiet hours
        created_at: Timestamp when the preference was created
        updated_at: Timestamp when the preference was last updated
    """

    __tablename__ = "notification_preferences"

    id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        primary_key=True,
        default=uuid4,
    )
    parent_id: Mapped[UUID] = mapped_column(
        UUID(as_uuid=True),
        nullable=False,
        index=True,
    )
    notification_type: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
    )
    channel: Mapped[str] = mapped_column(
        String(50),
        nullable=False,
    )
    is_enabled: Mapped[bool] = mapped_column(
        Boolean,
        nullable=False,
        default=True,
    )
    quiet_hours_start: Mapped[Optional[str]] = mapped_column(
        String(5),
        nullable=True,
    )
    quiet_hours_end: Mapped[Optional[str]] = mapped_column(
        String(5),
        nullable=True,
    )
    created_at: Mapped[datetime] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        nullable=False,
    )
    updated_at: Mapped[Optional[datetime]] = mapped_column(
        DateTime,
        default=datetime.utcnow,
        onupdate=datetime.utcnow,
        nullable=True,
    )

    # Table-level indexes for common query patterns
    __table_args__ = (
        Index("ix_notification_preferences_parent", "parent_id"),
        Index("ix_notification_preferences_parent_type", "parent_id", "notification_type"),
    )