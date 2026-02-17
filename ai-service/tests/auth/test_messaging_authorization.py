"""Unit tests for messaging service notification preference authorization.

Tests verify that notification preference operations enforce proper authorization:
- Users can only access their own notification preferences
- Admins and directors can access any user's preferences
- Unauthorized access raises UnauthorizedAccessError
"""

from unittest.mock import AsyncMock, MagicMock
from uuid import UUID, uuid4

import pytest

from app.services.messaging_service import MessagingService, UnauthorizedAccessError
from app.schemas.messaging import (
    NotificationPreferenceRequest,
    NotificationType,
    NotificationChannelType,
    NotificationFrequency,
)


class TestNotificationPreferenceAuthorization:
    """Tests for notification preference authorization."""

    @pytest.fixture
    def parent_id(self):
        """Parent user ID."""
        return uuid4()

    @pytest.fixture
    def other_user_id(self):
        """Different user ID for unauthorized access tests."""
        return uuid4()

    @pytest.fixture
    def mock_db(self):
        """Mock database session."""
        return AsyncMock()

    @pytest.fixture
    def service(self, mock_db):
        """Create messaging service instance."""
        return MessagingService(mock_db)

    # =========================================================================
    # Authorization Helper Method Tests
    # =========================================================================

    def test_verify_access_same_user_allowed(self, service, parent_id):
        """Test that users can access their own preferences."""
        # Should not raise an exception
        service._verify_notification_preference_access(parent_id, parent_id, "parent")

    def test_verify_access_different_user_denied(self, service, parent_id, other_user_id):
        """Test that users cannot access other users' preferences."""
        with pytest.raises(UnauthorizedAccessError) as exc_info:
            service._verify_notification_preference_access(parent_id, other_user_id, "parent")

        assert "You can only access your own notification preferences" in str(exc_info.value)

    def test_verify_access_admin_allowed(self, service, parent_id, other_user_id):
        """Test that admins can access any user's preferences."""
        # Should not raise an exception
        service._verify_notification_preference_access(parent_id, other_user_id, "admin")

    def test_verify_access_director_allowed(self, service, parent_id, other_user_id):
        """Test that directors can access any user's preferences."""
        # Should not raise an exception
        service._verify_notification_preference_access(parent_id, other_user_id, "director")

    def test_verify_access_admin_uppercase_allowed(self, service, parent_id, other_user_id):
        """Test that admin role check is case-insensitive."""
        # Should not raise an exception
        service._verify_notification_preference_access(parent_id, other_user_id, "ADMIN")

    def test_verify_access_director_uppercase_allowed(self, service, parent_id, other_user_id):
        """Test that director role check is case-insensitive."""
        # Should not raise an exception
        service._verify_notification_preference_access(parent_id, other_user_id, "DIRECTOR")

    def test_verify_access_no_role_defaults_to_user_check(self, service, parent_id, other_user_id):
        """Test that missing role defaults to user ownership check."""
        with pytest.raises(UnauthorizedAccessError):
            service._verify_notification_preference_access(parent_id, other_user_id, None)

    # =========================================================================
    # get_notification_preferences Authorization Tests
    # =========================================================================

    @pytest.mark.asyncio
    async def test_get_preferences_same_user_allowed(self, service, mock_db, parent_id):
        """Test that users can get their own preferences."""
        # Mock database query
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_db.execute.return_value = mock_result

        # Should not raise an exception
        result = await service.get_notification_preferences(parent_id, parent_id, "parent")
        assert result.parent_id == parent_id
        assert result.preferences == []

    @pytest.mark.asyncio
    async def test_get_preferences_different_user_denied(self, service, parent_id, other_user_id):
        """Test that users cannot get other users' preferences."""
        with pytest.raises(UnauthorizedAccessError):
            await service.get_notification_preferences(parent_id, other_user_id, "parent")

    @pytest.mark.asyncio
    async def test_get_preferences_admin_allowed(self, service, mock_db, parent_id, other_user_id):
        """Test that admins can get any user's preferences."""
        # Mock database query
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_db.execute.return_value = mock_result

        # Should not raise an exception
        result = await service.get_notification_preferences(parent_id, other_user_id, "admin")
        assert result.parent_id == parent_id

    # =========================================================================
    # get_notification_preference Authorization Tests
    # =========================================================================

    @pytest.mark.asyncio
    async def test_get_preference_same_user_allowed(self, service, mock_db, parent_id):
        """Test that users can get their own specific preference."""
        # Mock database query to return a preference
        mock_pref = MagicMock()
        mock_pref.id = uuid4()
        mock_pref.parent_id = parent_id
        mock_pref.notification_type = "message"
        mock_pref.channel = "email"
        mock_pref.is_enabled = True
        mock_pref.quiet_hours_start = None
        mock_pref.quiet_hours_end = None
        mock_pref.created_at = None
        mock_pref.updated_at = None

        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_pref
        mock_db.execute.return_value = mock_result

        # Should not raise an exception
        result = await service.get_notification_preference(
            parent_id, NotificationType.MESSAGE, NotificationChannelType.EMAIL, parent_id, "parent"
        )
        assert result.parent_id == parent_id

    @pytest.mark.asyncio
    async def test_get_preference_different_user_denied(self, service, parent_id, other_user_id):
        """Test that users cannot get other users' specific preference."""
        with pytest.raises(UnauthorizedAccessError):
            await service.get_notification_preference(
                parent_id, NotificationType.MESSAGE, NotificationChannelType.EMAIL, other_user_id, "parent"
            )

    # =========================================================================
    # create_notification_preference Authorization Tests
    # =========================================================================

    @pytest.mark.asyncio
    async def test_create_preference_same_user_allowed(self, service, mock_db, parent_id):
        """Test that users can create their own preferences."""
        request = NotificationPreferenceRequest(
            parent_id=parent_id,
            notification_type=NotificationType.MESSAGE,
            channel=NotificationChannelType.EMAIL,
            is_enabled=True,
            frequency=NotificationFrequency.IMMEDIATE,
        )

        # Mock database queries
        mock_existing = MagicMock()
        mock_existing.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_existing

        # Mock commit and refresh
        mock_db.commit.return_value = None

        # Mock the preference object that will be created
        mock_pref = MagicMock()
        mock_pref.id = uuid4()
        mock_pref.parent_id = parent_id
        mock_pref.notification_type = "message"
        mock_pref.channel = "email"
        mock_pref.is_enabled = True
        mock_pref.quiet_hours_start = None
        mock_pref.quiet_hours_end = None
        mock_pref.created_at = None
        mock_pref.updated_at = None

        async def mock_refresh(obj):
            # Update the object with the mock data
            obj.id = mock_pref.id
            obj.created_at = mock_pref.created_at
            obj.updated_at = mock_pref.updated_at

        mock_db.refresh.side_effect = mock_refresh

        # Should not raise an exception
        result = await service.create_notification_preference(request, parent_id, "parent")
        assert result.parent_id == parent_id

    @pytest.mark.asyncio
    async def test_create_preference_different_user_denied(self, service, parent_id, other_user_id):
        """Test that users cannot create preferences for other users."""
        request = NotificationPreferenceRequest(
            parent_id=parent_id,
            notification_type=NotificationType.MESSAGE,
            channel=NotificationChannelType.EMAIL,
            is_enabled=True,
            frequency=NotificationFrequency.IMMEDIATE,
        )

        with pytest.raises(UnauthorizedAccessError):
            await service.create_notification_preference(request, other_user_id, "parent")

    @pytest.mark.asyncio
    async def test_create_preference_admin_allowed(self, service, mock_db, parent_id, other_user_id):
        """Test that admins can create preferences for any user."""
        request = NotificationPreferenceRequest(
            parent_id=parent_id,
            notification_type=NotificationType.MESSAGE,
            channel=NotificationChannelType.EMAIL,
            is_enabled=True,
            frequency=NotificationFrequency.IMMEDIATE,
        )

        # Mock database queries
        mock_existing = MagicMock()
        mock_existing.scalar_one_or_none.return_value = None
        mock_db.execute.return_value = mock_existing
        mock_db.commit.return_value = None

        # Mock the preference object
        mock_pref = MagicMock()
        mock_pref.id = uuid4()
        mock_pref.parent_id = parent_id
        mock_pref.notification_type = "message"
        mock_pref.channel = "email"
        mock_pref.is_enabled = True
        mock_pref.quiet_hours_start = None
        mock_pref.quiet_hours_end = None
        mock_pref.created_at = None
        mock_pref.updated_at = None

        async def mock_refresh(obj):
            obj.id = mock_pref.id
            obj.created_at = mock_pref.created_at
            obj.updated_at = mock_pref.updated_at

        mock_db.refresh.side_effect = mock_refresh

        # Should not raise an exception
        result = await service.create_notification_preference(request, other_user_id, "admin")
        assert result.parent_id == parent_id

    # =========================================================================
    # delete_notification_preference Authorization Tests
    # =========================================================================

    @pytest.mark.asyncio
    async def test_delete_preference_same_user_allowed(self, service, mock_db, parent_id):
        """Test that users can delete their own preferences."""
        # Mock database query to return a preference
        mock_pref = MagicMock()
        mock_result = MagicMock()
        mock_result.scalar_one_or_none.return_value = mock_pref
        mock_db.execute.return_value = mock_result
        mock_db.delete.return_value = None
        mock_db.commit.return_value = None

        # Should not raise an exception
        result = await service.delete_notification_preference(
            parent_id, NotificationType.MESSAGE, NotificationChannelType.EMAIL, parent_id, "parent"
        )
        assert result is True

    @pytest.mark.asyncio
    async def test_delete_preference_different_user_denied(self, service, parent_id, other_user_id):
        """Test that users cannot delete other users' preferences."""
        with pytest.raises(UnauthorizedAccessError):
            await service.delete_notification_preference(
                parent_id, NotificationType.MESSAGE, NotificationChannelType.EMAIL, other_user_id, "parent"
            )

    # =========================================================================
    # get_or_create_default_preferences Authorization Tests
    # =========================================================================

    @pytest.mark.asyncio
    async def test_get_or_create_defaults_same_user_allowed(self, service, mock_db, parent_id):
        """Test that users can get/create their own default preferences."""
        # Mock database query to return no existing preferences
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_db.execute.return_value = mock_result
        mock_db.commit.return_value = None

        # Mock refresh for created preferences
        async def mock_refresh(obj):
            obj.id = uuid4()
            obj.created_at = None
            obj.updated_at = None

        mock_db.refresh.side_effect = mock_refresh

        # Should not raise an exception
        result = await service.get_or_create_default_preferences(parent_id, parent_id, "parent")
        assert result.parent_id == parent_id

    @pytest.mark.asyncio
    async def test_get_or_create_defaults_different_user_denied(self, service, parent_id, other_user_id):
        """Test that users cannot get/create defaults for other users."""
        with pytest.raises(UnauthorizedAccessError):
            await service.get_or_create_default_preferences(parent_id, other_user_id, "parent")

    # =========================================================================
    # set_quiet_hours Authorization Tests
    # =========================================================================

    @pytest.mark.asyncio
    async def test_set_quiet_hours_same_user_allowed(self, service, mock_db, parent_id):
        """Test that users can set their own quiet hours."""
        # Mock database update
        mock_result = MagicMock()
        mock_result.rowcount = 3
        mock_db.execute.return_value = mock_result
        mock_db.commit.return_value = None

        # Should not raise an exception
        result = await service.set_quiet_hours(parent_id, "22:00", "07:00", parent_id, "parent")
        assert result == 3

    @pytest.mark.asyncio
    async def test_set_quiet_hours_different_user_denied(self, service, parent_id, other_user_id):
        """Test that users cannot set quiet hours for other users."""
        with pytest.raises(UnauthorizedAccessError):
            await service.set_quiet_hours(parent_id, "22:00", "07:00", other_user_id, "parent")

    @pytest.mark.asyncio
    async def test_set_quiet_hours_director_allowed(self, service, mock_db, parent_id, other_user_id):
        """Test that directors can set quiet hours for any user."""
        # Mock database update
        mock_result = MagicMock()
        mock_result.rowcount = 3
        mock_db.execute.return_value = mock_result
        mock_db.commit.return_value = None

        # Should not raise an exception
        result = await service.set_quiet_hours(parent_id, "22:00", "07:00", other_user_id, "director")
        assert result == 3
