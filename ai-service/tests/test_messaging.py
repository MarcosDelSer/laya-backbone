"""Unit tests for parent-educator messaging functionality.

Tests for messaging service, thread operations, message operations,
notification preferences, @mention extraction, and API endpoints.

Tests cover:
- Thread CRUD operations (create, get, list, update, archive)
- Message CRUD operations (send, get, list, mark as read, delete)
- Notification preference management
- @mention extraction and formatting
- Private note visibility handling
- API endpoint response structure
- Authentication requirements on protected endpoints
- Error handling for various edge cases
"""

from __future__ import annotations

from datetime import datetime, timezone
from typing import Any, Dict, List, Optional
from uuid import UUID, uuid4

import pytest
import pytest_asyncio
from httpx import AsyncClient
from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession

from app.schemas.messaging import (
    MessageContentType,
    MessageCreate,
    NotificationChannelType,
    NotificationFrequency,
    NotificationPreferenceRequest,
    NotificationType,
    SenderType,
    ThreadCreate,
    ThreadParticipant,
    ThreadType,
    ThreadUpdate,
)
from app.services.messaging_service import (
    InvalidThreadError,
    MessageNotFoundError,
    MessagingService,
    NotificationPreferenceNotFoundError,
    ThreadNotFoundError,
    UnauthorizedAccessError,
)


# =============================================================================
# SQLite Table Creation for Tests
# =============================================================================


SQLITE_CREATE_MESSAGING_TABLES_SQL = """
CREATE TABLE IF NOT EXISTS message_threads (
    id TEXT PRIMARY KEY,
    subject VARCHAR(255) NOT NULL,
    thread_type VARCHAR(20) NOT NULL DEFAULT 'daily_log',
    child_id TEXT,
    created_by TEXT NOT NULL,
    participants TEXT NOT NULL DEFAULT '[]',
    is_active INTEGER NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS messages (
    id TEXT PRIMARY KEY,
    thread_id TEXT NOT NULL REFERENCES message_threads(id) ON DELETE CASCADE,
    sender_id TEXT NOT NULL,
    sender_type VARCHAR(20) NOT NULL,
    content TEXT NOT NULL,
    content_type VARCHAR(20) NOT NULL DEFAULT 'text',
    is_read INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE TABLE IF NOT EXISTS message_attachments (
    id TEXT PRIMARY KEY,
    message_id TEXT NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
    file_url VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_size INTEGER,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS notification_preferences (
    id TEXT PRIMARY KEY,
    parent_id TEXT NOT NULL,
    notification_type VARCHAR(20) NOT NULL,
    channel VARCHAR(20) NOT NULL,
    is_enabled INTEGER NOT NULL DEFAULT 1,
    quiet_hours_start VARCHAR(5),
    quiet_hours_end VARCHAR(5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_threads_created_by ON message_threads(created_by);
CREATE INDEX IF NOT EXISTS idx_threads_child ON message_threads(child_id);
CREATE INDEX IF NOT EXISTS idx_messages_thread ON messages(thread_id);
CREATE INDEX IF NOT EXISTS idx_messages_sender ON messages(sender_id);
CREATE INDEX IF NOT EXISTS idx_attachments_message ON message_attachments(message_id);
CREATE INDEX IF NOT EXISTS idx_notif_prefs_parent ON notification_preferences(parent_id);
"""


# =============================================================================
# Mock Classes
# =============================================================================


class MockMessageThread:
    """Mock MessageThread object for testing without SQLAlchemy ORM overhead."""

    def __init__(
        self,
        id: UUID,
        subject: str,
        thread_type: str,
        child_id: Optional[UUID],
        created_by: UUID,
        participants: List[Dict[str, Any]],
        is_active: bool,
        created_at: datetime,
        updated_at: Optional[datetime],
    ):
        self.id = id
        self.subject = subject
        self.thread_type = thread_type
        self.child_id = child_id
        self.created_by = created_by
        self.participants = participants
        self.is_active = is_active
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return f"<MessageThread(id={self.id}, subject='{self.subject}')>"


class MockMessage:
    """Mock Message object for testing without SQLAlchemy ORM overhead."""

    def __init__(
        self,
        id: UUID,
        thread_id: UUID,
        sender_id: UUID,
        sender_type: str,
        content: str,
        content_type: str,
        is_read: bool,
        created_at: datetime,
        updated_at: Optional[datetime],
    ):
        self.id = id
        self.thread_id = thread_id
        self.sender_id = sender_id
        self.sender_type = sender_type
        self.content = content
        self.content_type = content_type
        self.is_read = is_read
        self.created_at = created_at
        self.updated_at = updated_at
        self.attachments = []

    def __repr__(self) -> str:
        return f"<Message(id={self.id}, sender_id={self.sender_id})>"


class MockNotificationPreference:
    """Mock NotificationPreference object for testing."""

    def __init__(
        self,
        id: UUID,
        parent_id: UUID,
        notification_type: str,
        channel: str,
        is_enabled: bool,
        quiet_hours_start: Optional[str],
        quiet_hours_end: Optional[str],
        created_at: datetime,
        updated_at: Optional[datetime],
    ):
        self.id = id
        self.parent_id = parent_id
        self.notification_type = notification_type
        self.channel = channel
        self.is_enabled = is_enabled
        self.quiet_hours_start = quiet_hours_start
        self.quiet_hours_end = quiet_hours_end
        self.created_at = created_at
        self.updated_at = updated_at

    def __repr__(self) -> str:
        return f"<NotificationPreference(id={self.id}, parent_id={self.parent_id})>"


# =============================================================================
# Database Helper Functions
# =============================================================================


async def ensure_messaging_tables(session: AsyncSession) -> None:
    """Create messaging tables if they don't exist."""
    async with session.get_bind().begin() as conn:
        for statement in SQLITE_CREATE_MESSAGING_TABLES_SQL.strip().split(';'):
            statement = statement.strip()
            if statement:
                await conn.execute(text(statement))


async def create_thread_in_db(
    session: AsyncSession,
    subject: str,
    created_by: UUID,
    thread_type: str = "daily_log",
    child_id: Optional[UUID] = None,
    participants: Optional[List[Dict[str, Any]]] = None,
    is_active: bool = True,
) -> MockMessageThread:
    """Helper function to create a message thread directly in SQLite database."""
    import json

    await ensure_messaging_tables(session)

    thread_id = str(uuid4())
    now = datetime.now(timezone.utc)
    participants_json = json.dumps(participants or [])

    await session.execute(
        text("""
            INSERT INTO message_threads (
                id, subject, thread_type, child_id, created_by,
                participants, is_active, created_at, updated_at
            ) VALUES (
                :id, :subject, :thread_type, :child_id, :created_by,
                :participants, :is_active, :created_at, :updated_at
            )
        """),
        {
            "id": thread_id,
            "subject": subject,
            "thread_type": thread_type,
            "child_id": str(child_id) if child_id else None,
            "created_by": str(created_by),
            "participants": participants_json,
            "is_active": 1 if is_active else 0,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        }
    )
    await session.commit()

    return MockMessageThread(
        id=UUID(thread_id),
        subject=subject,
        thread_type=thread_type,
        child_id=child_id,
        created_by=created_by,
        participants=participants or [],
        is_active=is_active,
        created_at=now,
        updated_at=now,
    )


async def create_message_in_db(
    session: AsyncSession,
    thread_id: UUID,
    sender_id: UUID,
    content: str,
    sender_type: str = "educator",
    content_type: str = "text",
    is_read: bool = False,
) -> MockMessage:
    """Helper function to create a message directly in SQLite database."""
    await ensure_messaging_tables(session)

    message_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO messages (
                id, thread_id, sender_id, sender_type, content,
                content_type, is_read, created_at, updated_at
            ) VALUES (
                :id, :thread_id, :sender_id, :sender_type, :content,
                :content_type, :is_read, :created_at, :updated_at
            )
        """),
        {
            "id": message_id,
            "thread_id": str(thread_id),
            "sender_id": str(sender_id),
            "sender_type": sender_type,
            "content": content,
            "content_type": content_type,
            "is_read": 1 if is_read else 0,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        }
    )
    await session.commit()

    return MockMessage(
        id=UUID(message_id),
        thread_id=thread_id,
        sender_id=sender_id,
        sender_type=sender_type,
        content=content,
        content_type=content_type,
        is_read=is_read,
        created_at=now,
        updated_at=now,
    )


async def create_notification_preference_in_db(
    session: AsyncSession,
    parent_id: UUID,
    notification_type: str,
    channel: str,
    is_enabled: bool = True,
    quiet_hours_start: Optional[str] = None,
    quiet_hours_end: Optional[str] = None,
) -> MockNotificationPreference:
    """Helper function to create a notification preference in SQLite database."""
    await ensure_messaging_tables(session)

    pref_id = str(uuid4())
    now = datetime.now(timezone.utc)

    await session.execute(
        text("""
            INSERT INTO notification_preferences (
                id, parent_id, notification_type, channel, is_enabled,
                quiet_hours_start, quiet_hours_end, created_at, updated_at
            ) VALUES (
                :id, :parent_id, :notification_type, :channel, :is_enabled,
                :quiet_hours_start, :quiet_hours_end, :created_at, :updated_at
            )
        """),
        {
            "id": pref_id,
            "parent_id": str(parent_id),
            "notification_type": notification_type,
            "channel": channel,
            "is_enabled": 1 if is_enabled else 0,
            "quiet_hours_start": quiet_hours_start,
            "quiet_hours_end": quiet_hours_end,
            "created_at": now.isoformat(),
            "updated_at": now.isoformat(),
        }
    )
    await session.commit()

    return MockNotificationPreference(
        id=UUID(pref_id),
        parent_id=parent_id,
        notification_type=notification_type,
        channel=channel,
        is_enabled=is_enabled,
        quiet_hours_start=quiet_hours_start,
        quiet_hours_end=quiet_hours_end,
        created_at=now,
        updated_at=now,
    )


# =============================================================================
# Fixtures
# =============================================================================


@pytest.fixture
def test_educator_id() -> UUID:
    """Generate a consistent test educator ID."""
    return UUID("11111111-1111-1111-1111-111111111111")


@pytest.fixture
def test_parent_id_messaging() -> UUID:
    """Generate a consistent test parent ID for messaging tests."""
    return UUID("22222222-2222-2222-2222-222222222222")


@pytest.fixture
def sample_thread_create_request(test_child_id: UUID, test_parent_id_messaging: UUID) -> ThreadCreate:
    """Create a sample thread creation request."""
    return ThreadCreate(
        subject="Daily Activity Update",
        thread_type=ThreadType.DAILY_LOG,
        child_id=test_child_id,
        participants=[
            ThreadParticipant(
                user_id=test_parent_id_messaging,
                user_type=SenderType.PARENT,
                display_name="Parent User",
            ),
        ],
        initial_message="Hello! Here is today's update.",
    )


@pytest.fixture
def sample_message_create_request() -> MessageCreate:
    """Create a sample message creation request."""
    return MessageCreate(
        content="This is a test message.",
        content_type=MessageContentType.TEXT,
    )


@pytest_asyncio.fixture
async def sample_thread(
    db_session: AsyncSession,
    test_user_id: UUID,
    test_child_id: UUID,
    test_parent_id_messaging: UUID,
) -> MockMessageThread:
    """Create a sample message thread in the database."""
    return await create_thread_in_db(
        db_session,
        subject="Test Thread",
        created_by=test_user_id,
        thread_type="daily_log",
        child_id=test_child_id,
        participants=[
            {
                "user_id": str(test_user_id),
                "user_type": "educator",
                "display_name": "Test Educator",
            },
            {
                "user_id": str(test_parent_id_messaging),
                "user_type": "parent",
                "display_name": "Test Parent",
            },
        ],
    )


@pytest_asyncio.fixture
async def sample_message(
    db_session: AsyncSession,
    sample_thread: MockMessageThread,
    test_user_id: UUID,
) -> MockMessage:
    """Create a sample message in the database."""
    return await create_message_in_db(
        db_session,
        thread_id=sample_thread.id,
        sender_id=test_user_id,
        content="Test message content",
        sender_type="educator",
    )


@pytest_asyncio.fixture
async def sample_notification_preference(
    db_session: AsyncSession,
    test_parent_id_messaging: UUID,
) -> MockNotificationPreference:
    """Create a sample notification preference in the database."""
    return await create_notification_preference_in_db(
        db_session,
        parent_id=test_parent_id_messaging,
        notification_type="message",
        channel="email",
        is_enabled=True,
    )


# =============================================================================
# Service Tests - Thread Operations
# =============================================================================


class TestMessagingServiceThreads:
    """Tests for MessagingService thread operations."""

    @pytest.mark.asyncio
    async def test_create_thread_returns_valid_response(
        self,
        db_session: AsyncSession,
        test_user_id: UUID,
        sample_thread_create_request: ThreadCreate,
    ):
        """Test that create_thread returns a valid ThreadResponse."""
        await ensure_messaging_tables(db_session)
        service = MessagingService(db_session)

        response = await service.create_thread(
            request=sample_thread_create_request,
            user_id=test_user_id,
            user_type=SenderType.EDUCATOR,
        )

        assert response is not None
        assert response.subject == sample_thread_create_request.subject
        assert response.thread_type == sample_thread_create_request.thread_type
        assert response.child_id == sample_thread_create_request.child_id
        assert response.created_by == test_user_id
        assert response.is_active is True

    @pytest.mark.asyncio
    async def test_create_thread_adds_creator_as_participant(
        self,
        db_session: AsyncSession,
        test_user_id: UUID,
        test_child_id: UUID,
    ):
        """Test that creator is automatically added as participant if not in list."""
        await ensure_messaging_tables(db_session)
        service = MessagingService(db_session)

        request = ThreadCreate(
            subject="Test Subject",
            thread_type=ThreadType.DAILY_LOG,
            child_id=test_child_id,
            participants=[],  # Empty participants list
        )

        response = await service.create_thread(
            request=request,
            user_id=test_user_id,
            user_type=SenderType.EDUCATOR,
        )

        # Creator should be added as participant
        participant_ids = [str(p.user_id) for p in response.participants]
        assert str(test_user_id) in participant_ids

    @pytest.mark.asyncio
    async def test_create_thread_empty_subject_raises_error(
        self,
        db_session: AsyncSession,
        test_user_id: UUID,
        test_child_id: UUID,
    ):
        """Test that creating a thread with empty subject raises InvalidThreadError."""
        await ensure_messaging_tables(db_session)
        service = MessagingService(db_session)

        request = ThreadCreate(
            subject="   ",  # Whitespace-only subject
            thread_type=ThreadType.DAILY_LOG,
            child_id=test_child_id,
            participants=[],
        )

        with pytest.raises(InvalidThreadError) as exc_info:
            await service.create_thread(
                request=request,
                user_id=test_user_id,
                user_type=SenderType.EDUCATOR,
            )

        assert "empty" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_get_thread_returns_thread(
        self,
        db_session: AsyncSession,
        sample_thread: MockMessageThread,
        test_user_id: UUID,
    ):
        """Test that get_thread returns the correct thread."""
        service = MessagingService(db_session)

        response = await service.get_thread(
            thread_id=sample_thread.id,
            user_id=test_user_id,
            include_messages=False,
        )

        assert response is not None
        assert response.id == sample_thread.id
        assert response.subject == sample_thread.subject

    @pytest.mark.asyncio
    async def test_get_thread_not_found_raises_error(
        self,
        db_session: AsyncSession,
        test_user_id: UUID,
    ):
        """Test that get_thread raises ThreadNotFoundError for non-existent thread."""
        await ensure_messaging_tables(db_session)
        service = MessagingService(db_session)
        non_existent_id = uuid4()

        with pytest.raises(ThreadNotFoundError):
            await service.get_thread(
                thread_id=non_existent_id,
                user_id=test_user_id,
                include_messages=False,
            )

    @pytest.mark.asyncio
    async def test_get_thread_unauthorized_raises_error(
        self,
        db_session: AsyncSession,
        sample_thread: MockMessageThread,
    ):
        """Test that get_thread raises UnauthorizedAccessError for non-participant."""
        service = MessagingService(db_session)
        unauthorized_user = uuid4()

        with pytest.raises(UnauthorizedAccessError):
            await service.get_thread(
                thread_id=sample_thread.id,
                user_id=unauthorized_user,
                include_messages=False,
            )

    @pytest.mark.asyncio
    async def test_list_threads_for_user_returns_threads(
        self,
        db_session: AsyncSession,
        sample_thread: MockMessageThread,
        test_user_id: UUID,
    ):
        """Test that list_threads_for_user returns user's threads."""
        service = MessagingService(db_session)

        threads = await service.list_threads_for_user(
            user_id=test_user_id,
            limit=50,
            offset=0,
        )

        assert len(threads) >= 1
        thread_ids = [t.id for t in threads]
        assert sample_thread.id in thread_ids

    @pytest.mark.asyncio
    async def test_list_threads_for_user_filters_by_child(
        self,
        db_session: AsyncSession,
        sample_thread: MockMessageThread,
        test_user_id: UUID,
        test_child_id: UUID,
    ):
        """Test that list_threads_for_user can filter by child_id."""
        service = MessagingService(db_session)

        threads = await service.list_threads_for_user(
            user_id=test_user_id,
            child_id=test_child_id,
            limit=50,
            offset=0,
        )

        for thread in threads:
            assert thread.child_id == test_child_id

    @pytest.mark.asyncio
    async def test_list_threads_excludes_archived_by_default(
        self,
        db_session: AsyncSession,
        test_user_id: UUID,
        test_child_id: UUID,
    ):
        """Test that archived threads are excluded by default."""
        await ensure_messaging_tables(db_session)

        # Create an archived thread
        await create_thread_in_db(
            db_session,
            subject="Archived Thread",
            created_by=test_user_id,
            child_id=test_child_id,
            is_active=False,
            participants=[{"user_id": str(test_user_id), "user_type": "educator"}],
        )

        service = MessagingService(db_session)
        threads = await service.list_threads_for_user(
            user_id=test_user_id,
            include_archived=False,
        )

        for thread in threads:
            assert thread.is_active is True

    @pytest.mark.asyncio
    async def test_update_thread_changes_subject(
        self,
        db_session: AsyncSession,
        sample_thread: MockMessageThread,
        test_user_id: UUID,
    ):
        """Test that update_thread changes the thread subject."""
        service = MessagingService(db_session)
        new_subject = "Updated Subject"

        request = ThreadUpdate(subject=new_subject)
        response = await service.update_thread(
            thread_id=sample_thread.id,
            request=request,
            user_id=test_user_id,
        )

        assert response.subject == new_subject

    @pytest.mark.asyncio
    async def test_archive_thread_sets_inactive(
        self,
        db_session: AsyncSession,
        sample_thread: MockMessageThread,
        test_user_id: UUID,
    ):
        """Test that archive_thread sets is_active to False."""
        service = MessagingService(db_session)

        response = await service.archive_thread(
            thread_id=sample_thread.id,
            user_id=test_user_id,
        )

        assert response.is_active is False


# =============================================================================
# Service Tests - Message Operations
# =============================================================================


class TestMessagingServiceMessages:
    """Tests for MessagingService message operations."""

    @pytest.mark.asyncio
    async def test_send_message_returns_valid_response(
        self,
        db_session: AsyncSession,
        sample_thread: MockMessageThread,
        test_user_id: UUID,
        sample_message_create_request: MessageCreate,
    ):
        """Test that send_message returns a valid MessageResponse."""
        service = MessagingService(db_session)

        response = await service.send_message(
            thread_id=sample_thread.id,
            request=sample_message_create_request,
            sender_id=test_user_id,
            sender_type=SenderType.EDUCATOR,
        )

        assert response is not None
        assert response.thread_id == sample_thread.id
        assert response.sender_id == test_user_id
        assert response.content == sample_message_create_request.content
        assert response.is_read is False

    @pytest.mark.asyncio
    async def test_send_message_to_archived_thread_raises_error(
        self,
        db_session: AsyncSession,
        test_user_id: UUID,
        test_child_id: UUID,
    ):
        """Test that sending message to archived thread raises InvalidThreadError."""
        await ensure_messaging_tables(db_session)

        # Create an archived thread
        archived_thread = await create_thread_in_db(
            db_session,
            subject="Archived Thread",
            created_by=test_user_id,
            child_id=test_child_id,
            is_active=False,
            participants=[{"user_id": str(test_user_id), "user_type": "educator"}],
        )

        service = MessagingService(db_session)
        request = MessageCreate(content="Test", content_type=MessageContentType.TEXT)

        with pytest.raises(InvalidThreadError) as exc_info:
            await service.send_message(
                thread_id=archived_thread.id,
                request=request,
                sender_id=test_user_id,
                sender_type=SenderType.EDUCATOR,
            )

        assert "archived" in str(exc_info.value).lower()

    @pytest.mark.asyncio
    async def test_get_message_returns_message(
        self,
        db_session: AsyncSession,
        sample_message: MockMessage,
        test_user_id: UUID,
    ):
        """Test that get_message returns the correct message."""
        service = MessagingService(db_session)

        response = await service.get_message(
            message_id=sample_message.id,
            user_id=test_user_id,
        )

        assert response is not None
        assert response.id == sample_message.id
        assert response.content == sample_message.content

    @pytest.mark.asyncio
    async def test_get_message_not_found_raises_error(
        self,
        db_session: AsyncSession,
        test_user_id: UUID,
    ):
        """Test that get_message raises MessageNotFoundError for non-existent message."""
        await ensure_messaging_tables(db_session)
        service = MessagingService(db_session)
        non_existent_id = uuid4()

        with pytest.raises(MessageNotFoundError):
            await service.get_message(
                message_id=non_existent_id,
                user_id=test_user_id,
            )

    @pytest.mark.asyncio
    async def test_list_messages_returns_messages(
        self,
        db_session: AsyncSession,
        sample_thread: MockMessageThread,
        sample_message: MockMessage,
        test_user_id: UUID,
    ):
        """Test that list_messages returns messages in the thread."""
        service = MessagingService(db_session)

        messages = await service.list_messages(
            thread_id=sample_thread.id,
            user_id=test_user_id,
            limit=50,
            offset=0,
        )

        assert len(messages) >= 1
        message_ids = [m.id for m in messages]
        assert sample_message.id in message_ids

    @pytest.mark.asyncio
    async def test_mark_messages_as_read_updates_status(
        self,
        db_session: AsyncSession,
        sample_thread: MockMessageThread,
        test_parent_id_messaging: UUID,
        test_user_id: UUID,
    ):
        """Test that mark_messages_as_read updates message read status."""
        # Create an unread message from educator
        unread_message = await create_message_in_db(
            db_session,
            thread_id=sample_thread.id,
            sender_id=test_user_id,
            content="Unread message",
            sender_type="educator",
            is_read=False,
        )

        service = MessagingService(db_session)

        # Parent marks message as read
        marked_count = await service.mark_messages_as_read(
            message_ids=[unread_message.id],
            user_id=test_parent_id_messaging,
        )

        assert marked_count >= 1

    @pytest.mark.asyncio
    async def test_mark_own_messages_as_read_does_nothing(
        self,
        db_session: AsyncSession,
        sample_message: MockMessage,
        test_user_id: UUID,
    ):
        """Test that user cannot mark their own messages as read."""
        service = MessagingService(db_session)

        # Try to mark own message as read
        marked_count = await service.mark_messages_as_read(
            message_ids=[sample_message.id],
            user_id=test_user_id,  # Same as sender
        )

        assert marked_count == 0

    @pytest.mark.asyncio
    async def test_mark_thread_as_read_updates_all_messages(
        self,
        db_session: AsyncSession,
        sample_thread: MockMessageThread,
        test_user_id: UUID,
        test_parent_id_messaging: UUID,
    ):
        """Test that mark_thread_as_read updates all unread messages."""
        # Create multiple unread messages from educator
        await create_message_in_db(
            db_session,
            thread_id=sample_thread.id,
            sender_id=test_user_id,
            content="Message 1",
            is_read=False,
        )
        await create_message_in_db(
            db_session,
            thread_id=sample_thread.id,
            sender_id=test_user_id,
            content="Message 2",
            is_read=False,
        )

        service = MessagingService(db_session)

        # Parent marks all as read
        marked_count = await service.mark_thread_as_read(
            thread_id=sample_thread.id,
            user_id=test_parent_id_messaging,
        )

        assert marked_count >= 2

    @pytest.mark.asyncio
    async def test_delete_message_removes_message(
        self,
        db_session: AsyncSession,
        sample_thread: MockMessageThread,
        test_user_id: UUID,
    ):
        """Test that delete_message removes the message."""
        # Create a message to delete
        message = await create_message_in_db(
            db_session,
            thread_id=sample_thread.id,
            sender_id=test_user_id,
            content="Message to delete",
        )

        service = MessagingService(db_session)

        result = await service.delete_message(
            message_id=message.id,
            user_id=test_user_id,
        )

        assert result is True

        # Verify message is deleted
        with pytest.raises(MessageNotFoundError):
            await service.get_message(
                message_id=message.id,
                user_id=test_user_id,
            )

    @pytest.mark.asyncio
    async def test_delete_message_by_non_sender_raises_error(
        self,
        db_session: AsyncSession,
        sample_message: MockMessage,
        test_parent_id_messaging: UUID,
    ):
        """Test that non-sender cannot delete a message."""
        service = MessagingService(db_session)

        with pytest.raises(UnauthorizedAccessError):
            await service.delete_message(
                message_id=sample_message.id,
                user_id=test_parent_id_messaging,  # Not the sender
            )

    @pytest.mark.asyncio
    async def test_get_unread_count_returns_correct_counts(
        self,
        db_session: AsyncSession,
        sample_thread: MockMessageThread,
        test_user_id: UUID,
        test_parent_id_messaging: UUID,
    ):
        """Test that get_unread_count returns accurate counts."""
        # Create unread messages from educator
        await create_message_in_db(
            db_session,
            thread_id=sample_thread.id,
            sender_id=test_user_id,
            content="Unread 1",
            is_read=False,
        )
        await create_message_in_db(
            db_session,
            thread_id=sample_thread.id,
            sender_id=test_user_id,
            content="Unread 2",
            is_read=False,
        )

        service = MessagingService(db_session)

        # Get unread count for parent
        response = await service.get_unread_count(
            user_id=test_parent_id_messaging,
        )

        assert response.total_unread >= 2
        assert response.threads_with_unread >= 1


# =============================================================================
# Service Tests - Notification Preferences
# =============================================================================


class TestMessagingServiceNotifications:
    """Tests for MessagingService notification preference management."""

    @pytest.mark.asyncio
    async def test_create_notification_preference_success(
        self,
        db_session: AsyncSession,
        test_parent_id_messaging: UUID,
    ):
        """Test creating a new notification preference."""
        await ensure_messaging_tables(db_session)
        service = MessagingService(db_session)

        request = NotificationPreferenceRequest(
            parent_id=test_parent_id_messaging,
            notification_type=NotificationType.DAILY_LOG,
            channel=NotificationChannelType.PUSH,
            is_enabled=True,
            frequency=NotificationFrequency.IMMEDIATE,
        )

        response = await service.create_notification_preference(request)

        assert response is not None
        assert response.parent_id == test_parent_id_messaging
        assert response.notification_type == NotificationType.DAILY_LOG
        assert response.channel == NotificationChannelType.PUSH
        assert response.is_enabled is True

    @pytest.mark.asyncio
    async def test_create_notification_preference_updates_existing(
        self,
        db_session: AsyncSession,
        sample_notification_preference: MockNotificationPreference,
    ):
        """Test that creating duplicate preference updates the existing one."""
        service = MessagingService(db_session)

        # Create request with same type/channel but different enabled status
        request = NotificationPreferenceRequest(
            parent_id=sample_notification_preference.parent_id,
            notification_type=NotificationType(sample_notification_preference.notification_type),
            channel=NotificationChannelType(sample_notification_preference.channel),
            is_enabled=False,  # Different from original
            frequency=NotificationFrequency.IMMEDIATE,
        )

        response = await service.create_notification_preference(request)

        assert response.is_enabled is False

    @pytest.mark.asyncio
    async def test_get_notification_preferences_returns_all(
        self,
        db_session: AsyncSession,
        test_parent_id_messaging: UUID,
    ):
        """Test getting all notification preferences for a parent."""
        await ensure_messaging_tables(db_session)

        # Create multiple preferences
        await create_notification_preference_in_db(
            db_session, test_parent_id_messaging, "message", "email"
        )
        await create_notification_preference_in_db(
            db_session, test_parent_id_messaging, "message", "push"
        )

        service = MessagingService(db_session)
        response = await service.get_notification_preferences(test_parent_id_messaging)

        assert response.parent_id == test_parent_id_messaging
        assert len(response.preferences) >= 2

    @pytest.mark.asyncio
    async def test_get_notification_preference_not_found_raises_error(
        self,
        db_session: AsyncSession,
    ):
        """Test that getting non-existent preference raises error."""
        await ensure_messaging_tables(db_session)
        service = MessagingService(db_session)
        non_existent_parent = uuid4()

        with pytest.raises(NotificationPreferenceNotFoundError):
            await service.get_notification_preference(
                parent_id=non_existent_parent,
                notification_type=NotificationType.MESSAGE,
                channel=NotificationChannelType.EMAIL,
            )

    @pytest.mark.asyncio
    async def test_delete_notification_preference_success(
        self,
        db_session: AsyncSession,
        sample_notification_preference: MockNotificationPreference,
    ):
        """Test deleting a notification preference."""
        service = MessagingService(db_session)

        result = await service.delete_notification_preference(
            parent_id=sample_notification_preference.parent_id,
            notification_type=NotificationType(sample_notification_preference.notification_type),
            channel=NotificationChannelType(sample_notification_preference.channel),
        )

        assert result is True

    @pytest.mark.asyncio
    async def test_get_or_create_default_preferences(
        self,
        db_session: AsyncSession,
    ):
        """Test creating default preferences for new parent."""
        await ensure_messaging_tables(db_session)
        service = MessagingService(db_session)
        new_parent_id = uuid4()

        response = await service.get_or_create_default_preferences(new_parent_id)

        assert response.parent_id == new_parent_id
        # Should create default preferences for all type/channel combinations
        assert len(response.preferences) >= 4

    @pytest.mark.asyncio
    async def test_set_quiet_hours_updates_all_preferences(
        self,
        db_session: AsyncSession,
        test_parent_id_messaging: UUID,
    ):
        """Test setting quiet hours across all preferences."""
        await ensure_messaging_tables(db_session)

        # Create some preferences first
        await create_notification_preference_in_db(
            db_session, test_parent_id_messaging, "message", "email"
        )
        await create_notification_preference_in_db(
            db_session, test_parent_id_messaging, "urgent", "push"
        )

        service = MessagingService(db_session)
        updated_count = await service.set_quiet_hours(
            parent_id=test_parent_id_messaging,
            quiet_hours_start="22:00",
            quiet_hours_end="07:00",
        )

        assert updated_count >= 2

    @pytest.mark.asyncio
    async def test_is_notification_enabled_returns_correct_status(
        self,
        db_session: AsyncSession,
        sample_notification_preference: MockNotificationPreference,
    ):
        """Test checking if notification is enabled."""
        service = MessagingService(db_session)

        is_enabled = await service.is_notification_enabled(
            parent_id=sample_notification_preference.parent_id,
            notification_type=NotificationType(sample_notification_preference.notification_type),
            channel=NotificationChannelType(sample_notification_preference.channel),
        )

        assert is_enabled == sample_notification_preference.is_enabled

    @pytest.mark.asyncio
    async def test_is_notification_enabled_defaults_to_true(
        self,
        db_session: AsyncSession,
    ):
        """Test that is_notification_enabled defaults to True for missing prefs."""
        await ensure_messaging_tables(db_session)
        service = MessagingService(db_session)
        new_parent_id = uuid4()

        is_enabled = await service.is_notification_enabled(
            parent_id=new_parent_id,
            notification_type=NotificationType.MESSAGE,
            channel=NotificationChannelType.EMAIL,
        )

        assert is_enabled is True


# =============================================================================
# Service Tests - Mention Extraction
# =============================================================================


class TestMentionExtraction:
    """Tests for @mention extraction and formatting functions."""

    def test_extract_mentions_with_username(self):
        """Test extracting @username mentions."""
        content = "Hello @john_doe, how are you?"
        mentions = MessagingService.extract_mentions(content)
        assert "john_doe" in mentions

    def test_extract_mentions_with_bracket_name(self):
        """Test extracting @[Display Name] mentions."""
        content = "Hey @[Jane Smith], check this out!"
        mentions = MessagingService.extract_mentions(content)
        assert "Jane Smith" in mentions

    def test_extract_mentions_with_uuid(self):
        """Test extracting @uuid mentions."""
        test_uuid = "12345678-1234-1234-1234-123456789abc"
        content = f"Message for @{test_uuid}"
        mentions = MessagingService.extract_mentions(content)
        assert test_uuid in mentions

    def test_extract_mentions_multiple(self):
        """Test extracting multiple mentions from content."""
        content = "@alice and @[Bob Jones] should review this."
        mentions = MessagingService.extract_mentions(content)
        assert "alice" in mentions
        assert "Bob Jones" in mentions

    def test_extract_mentions_empty_content(self):
        """Test extracting mentions from empty content."""
        mentions = MessagingService.extract_mentions("")
        assert mentions == []

    def test_extract_mentions_no_mentions(self):
        """Test extracting mentions from content without mentions."""
        content = "This is a regular message without any mentions."
        mentions = MessagingService.extract_mentions(content)
        assert mentions == []

    def test_extract_mentions_removes_duplicates(self):
        """Test that duplicate mentions are removed."""
        content = "@john mentioned @john twice"
        mentions = MessagingService.extract_mentions(content)
        assert mentions.count("john") == 1

    def test_extract_mention_uuids_returns_valid_uuids(self):
        """Test extracting only valid UUIDs from mentions."""
        uuid1 = str(uuid4())
        content = f"@{uuid1} and @regular_user"
        uuids = MessagingService.extract_mention_uuids(content)
        assert len(uuids) == 1
        assert str(uuids[0]) == uuid1

    def test_is_staff_sender_educator(self):
        """Test is_staff_sender returns True for educator."""
        assert MessagingService.is_staff_sender("educator") is True

    def test_is_staff_sender_director(self):
        """Test is_staff_sender returns True for director."""
        assert MessagingService.is_staff_sender("director") is True

    def test_is_staff_sender_admin(self):
        """Test is_staff_sender returns True for admin."""
        assert MessagingService.is_staff_sender("admin") is True

    def test_is_staff_sender_parent(self):
        """Test is_staff_sender returns False for parent."""
        assert MessagingService.is_staff_sender("parent") is False

    def test_format_mentions_for_display_username(self):
        """Test formatting @username mentions for display."""
        content = "Hello @john"
        formatted = MessagingService.format_mentions_for_display(content)
        assert "<mention>@john</mention>" in formatted

    def test_format_mentions_for_display_bracket_name(self):
        """Test formatting @[Name] mentions for display."""
        content = "Hello @[Jane Smith]"
        formatted = MessagingService.format_mentions_for_display(content)
        assert "<mention>Jane Smith</mention>" in formatted

    def test_strip_mention_formatting(self):
        """Test stripping mention formatting."""
        content = "Hello <mention>John</mention>!"
        stripped = MessagingService.strip_mention_formatting(content)
        assert stripped == "Hello John!"


class TestPrivateNoteVisibility:
    """Tests for private note visibility handling."""

    def test_can_view_private_note_staff_always_sees(self):
        """Test that staff members can always view private notes."""
        service = MessagingService.__new__(MessagingService)
        service.db = None

        result = service.can_view_private_note(
            user_id=uuid4(),
            user_type="educator",
            message_visible_to=None,
        )
        assert result is True

    def test_can_view_private_note_parent_in_list(self):
        """Test that parent in visible_to list can view private note."""
        service = MessagingService.__new__(MessagingService)
        service.db = None
        user_id = uuid4()

        result = service.can_view_private_note(
            user_id=user_id,
            user_type="parent",
            message_visible_to=[str(user_id)],
        )
        assert result is True

    def test_can_view_private_note_parent_not_in_list(self):
        """Test that parent not in visible_to list cannot view private note."""
        service = MessagingService.__new__(MessagingService)
        service.db = None

        result = service.can_view_private_note(
            user_id=uuid4(),
            user_type="parent",
            message_visible_to=[str(uuid4())],  # Different user
        )
        assert result is False

    def test_resolve_mentions_to_participants(self):
        """Test resolving mentions to thread participants."""
        service = MessagingService.__new__(MessagingService)
        service.db = None

        participants = [
            {"user_id": "id1", "display_name": "Alice"},
            {"user_id": "id2", "display_name": "Bob"},
        ]
        content = "@Alice check this"

        matched = service.resolve_mentions_to_participants(content, participants)
        assert len(matched) == 1
        assert matched[0]["display_name"] == "Alice"


# =============================================================================
# API Endpoint Tests - Thread Endpoints
# =============================================================================


class TestThreadEndpoints:
    """Tests for thread-related API endpoints."""

    @pytest.mark.asyncio
    async def test_create_thread_endpoint_success(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        test_child_id: UUID,
    ):
        """Test successful thread creation via API."""
        response = await client.post(
            "/api/v1/messaging/threads",
            json={
                "subject": "Test Thread via API",
                "thread_type": "daily_log",
                "child_id": str(test_child_id),
                "participants": [],
            },
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["subject"] == "Test Thread via API"
        assert data["thread_type"] == "daily_log"

    @pytest.mark.asyncio
    async def test_create_thread_endpoint_requires_auth(
        self,
        client: AsyncClient,
        test_child_id: UUID,
    ):
        """Test that thread creation requires authentication."""
        response = await client.post(
            "/api/v1/messaging/threads",
            json={
                "subject": "Test Thread",
                "thread_type": "daily_log",
                "child_id": str(test_child_id),
            },
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_list_threads_endpoint_success(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
    ):
        """Test successful thread listing via API."""
        response = await client.get(
            "/api/v1/messaging/threads",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "threads" in data
        assert isinstance(data["threads"], list)

    @pytest.mark.asyncio
    async def test_list_threads_endpoint_with_filters(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
        test_child_id: UUID,
    ):
        """Test thread listing with filters via API."""
        response = await client.get(
            "/api/v1/messaging/threads",
            params={
                "child_id": str(test_child_id),
                "thread_type": "daily_log",
                "include_archived": "false",
            },
            headers=auth_headers,
        )

        assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_get_thread_endpoint_not_found(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
    ):
        """Test getting non-existent thread via API."""
        non_existent_id = uuid4()
        response = await client.get(
            f"/api/v1/messaging/threads/{non_existent_id}",
            headers=auth_headers,
        )

        assert response.status_code == 404


# =============================================================================
# API Endpoint Tests - Message Endpoints
# =============================================================================


class TestMessageEndpoints:
    """Tests for message-related API endpoints."""

    @pytest.mark.asyncio
    async def test_send_message_endpoint_thread_not_found(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
    ):
        """Test sending message to non-existent thread via API."""
        non_existent_id = uuid4()
        response = await client.post(
            f"/api/v1/messaging/threads/{non_existent_id}/messages",
            json={"content": "Test message", "content_type": "text"},
            headers=auth_headers,
        )

        assert response.status_code == 404

    @pytest.mark.asyncio
    async def test_list_messages_endpoint_thread_not_found(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
    ):
        """Test listing messages for non-existent thread via API."""
        non_existent_id = uuid4()
        response = await client.get(
            f"/api/v1/messaging/threads/{non_existent_id}/messages",
            headers=auth_headers,
        )

        assert response.status_code == 404

    @pytest.mark.asyncio
    async def test_mark_messages_as_read_endpoint_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test that marking messages as read requires authentication."""
        response = await client.patch(
            "/api/v1/messaging/messages/read",
            json={"message_ids": [str(uuid4())]},
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_get_message_endpoint_not_found(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
    ):
        """Test getting non-existent message via API."""
        non_existent_id = uuid4()
        response = await client.get(
            f"/api/v1/messaging/messages/{non_existent_id}",
            headers=auth_headers,
        )

        assert response.status_code == 404


# =============================================================================
# API Endpoint Tests - Notification Preference Endpoints
# =============================================================================


class TestNotificationPreferenceEndpoints:
    """Tests for notification preference API endpoints."""

    @pytest.mark.asyncio
    async def test_create_preference_endpoint_success(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
    ):
        """Test successful preference creation via API."""
        parent_id = uuid4()
        response = await client.post(
            "/api/v1/messaging/notifications/preferences",
            json={
                "parent_id": str(parent_id),
                "notification_type": "message",
                "channel": "email",
                "is_enabled": True,
                "frequency": "immediate",
            },
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["parent_id"] == str(parent_id)
        assert data["is_enabled"] is True

    @pytest.mark.asyncio
    async def test_get_preferences_endpoint_returns_list(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
    ):
        """Test getting all preferences via API returns a list."""
        parent_id = uuid4()
        response = await client.get(
            f"/api/v1/messaging/notifications/preferences/{parent_id}",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "preferences" in data
        assert isinstance(data["preferences"], list)

    @pytest.mark.asyncio
    async def test_get_specific_preference_endpoint_not_found(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
    ):
        """Test getting non-existent specific preference via API."""
        parent_id = uuid4()
        response = await client.get(
            f"/api/v1/messaging/notifications/preferences/{parent_id}/message/email",
            headers=auth_headers,
        )

        assert response.status_code == 404

    @pytest.mark.asyncio
    async def test_delete_preference_endpoint_not_found(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
    ):
        """Test deleting non-existent preference via API."""
        parent_id = uuid4()
        response = await client.delete(
            f"/api/v1/messaging/notifications/preferences/{parent_id}/message/email",
            headers=auth_headers,
        )

        assert response.status_code == 404

    @pytest.mark.asyncio
    async def test_create_default_preferences_endpoint(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
    ):
        """Test creating default preferences via API."""
        parent_id = uuid4()
        response = await client.post(
            f"/api/v1/messaging/notifications/preferences/{parent_id}/defaults",
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert data["parent_id"] == str(parent_id)
        assert len(data["preferences"]) >= 4

    @pytest.mark.asyncio
    async def test_set_quiet_hours_endpoint(
        self,
        client: AsyncClient,
        auth_headers: Dict[str, str],
    ):
        """Test setting quiet hours via API."""
        parent_id = uuid4()
        response = await client.patch(
            f"/api/v1/messaging/notifications/preferences/{parent_id}/quiet-hours",
            params={
                "quiet_hours_start": "22:00",
                "quiet_hours_end": "07:00",
            },
            headers=auth_headers,
        )

        assert response.status_code == 200
        data = response.json()
        assert "updated_count" in data
