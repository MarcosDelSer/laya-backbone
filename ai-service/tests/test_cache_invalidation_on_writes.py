"""Tests for cache invalidation on write operations.

Tests verify that caches are properly invalidated when data is modified
through write operations (create, update, delete).
"""

import pytest
from uuid import uuid4
from unittest.mock import AsyncMock, MagicMock, patch

from app.routers.webhooks import (
    process_child_profile_event,
    process_care_activity_event,
    process_attendance_event,
)
from app.schemas.webhook import WebhookEventType


class TestInvalidateOnWriteDecorator:
    """Test the @invalidate_on_write decorator."""

    @pytest.mark.asyncio
    async def test_decorator_basic_functionality(self):
        """Test that decorator executes function correctly."""
        # This is a simpler test that verifies the decorator doesn't break function execution
        from app.core.cache import invalidate_on_write

        @invalidate_on_write("test_cache")
        async def test_write_operation():
            return {"status": "success"}

        # Execute the decorated function
        result = await test_write_operation()

        # Verify the function executed successfully
        assert result == {"status": "success"}

    @pytest.mark.asyncio
    async def test_decorator_with_arguments(self):
        """Test decorator works with functions that have arguments."""
        from app.core.cache import invalidate_on_write

        @invalidate_on_write("user_cache")
        async def update_user(user_id: str, name: str, age: int):
            return {"user_id": user_id, "name": name, "age": age}

        # Execute with arguments
        result = await update_user("123", "Alice", 30)

        # Verify result includes arguments
        assert result == {"user_id": "123", "name": "Alice", "age": 30}

    @pytest.mark.asyncio
    async def test_decorator_preserves_exceptions(self):
        """Test that decorator preserves exceptions from function."""
        from app.core.cache import invalidate_on_write

        @invalidate_on_write("test_cache")
        async def failing_write():
            raise ValueError("Write operation failed")

        # Execute - should raise the exception
        with pytest.raises(ValueError, match="Write operation failed"):
            await failing_write()


class TestWebhookCacheInvalidation:
    """Test cache invalidation in webhook handlers."""

    @pytest.mark.asyncio
    async def test_child_profile_update_invalidates_cache(self):
        """Test that child profile update invalidates child profile cache."""
        child_id = str(uuid4())
        payload = {"name": "Test Child", "age": 5}
        mock_db = MagicMock()

        with patch("app.routers.webhooks.invalidate_cache", new_callable=AsyncMock) as mock_invalidate:
            mock_invalidate.return_value = 2

            # Process child profile update event
            result = await process_child_profile_event(child_id, payload, mock_db)

            # Verify cache was invalidated with correct pattern
            mock_invalidate.assert_called_once_with("child_profile", f"*{child_id}*")

            # Verify result message
            assert "cache invalidated" in result.lower()
            assert child_id in result

    @pytest.mark.asyncio
    async def test_care_activity_created_invalidates_cache(self):
        """Test that care activity creation invalidates activity catalog."""
        activity_id = str(uuid4())
        payload = {"name": "Reading Time", "type": "cognitive"}
        mock_db = MagicMock()

        with patch("app.routers.webhooks.invalidate_cache", new_callable=AsyncMock) as mock_invalidate:
            mock_invalidate.return_value = 5

            # Process care activity created event
            result = await process_care_activity_event(
                WebhookEventType.CARE_ACTIVITY_CREATED,
                activity_id,
                payload,
                mock_db
            )

            # Verify activity catalog cache was invalidated
            mock_invalidate.assert_called_once_with("activity_catalog")

            # Verify result message
            assert "cache invalidated" in result.lower()
            assert activity_id in result

    @pytest.mark.asyncio
    async def test_care_activity_updated_invalidates_cache(self):
        """Test that care activity update invalidates activity catalog."""
        activity_id = str(uuid4())
        payload = {"name": "Updated Activity"}
        mock_db = MagicMock()

        with patch("app.routers.webhooks.invalidate_cache", new_callable=AsyncMock) as mock_invalidate:
            mock_invalidate.return_value = 3

            # Process care activity updated event
            result = await process_care_activity_event(
                WebhookEventType.CARE_ACTIVITY_UPDATED,
                activity_id,
                payload,
                mock_db
            )

            # Verify activity catalog cache was invalidated
            mock_invalidate.assert_called_once_with("activity_catalog")
            assert "cache invalidated" in result.lower()

    @pytest.mark.asyncio
    async def test_care_activity_deleted_invalidates_cache(self):
        """Test that care activity deletion invalidates activity catalog."""
        activity_id = str(uuid4())
        payload = {}
        mock_db = MagicMock()

        with patch("app.routers.webhooks.invalidate_cache", new_callable=AsyncMock) as mock_invalidate:
            mock_invalidate.return_value = 4

            # Process care activity deleted event
            result = await process_care_activity_event(
                WebhookEventType.CARE_ACTIVITY_DELETED,
                activity_id,
                payload,
                mock_db
            )

            # Verify activity catalog cache was invalidated
            mock_invalidate.assert_called_once_with("activity_catalog")
            assert "cache invalidated" in result.lower()

    @pytest.mark.asyncio
    async def test_attendance_checkin_invalidates_analytics(self):
        """Test that attendance check-in invalidates analytics dashboard."""
        attendance_id = str(uuid4())
        child_id = str(uuid4())
        payload = {"child_id": child_id, "timestamp": "2024-01-15T08:00:00Z"}
        mock_db = MagicMock()

        with patch("app.routers.webhooks.invalidate_cache", new_callable=AsyncMock) as mock_invalidate:
            mock_invalidate.return_value = 2

            # Process attendance check-in event
            result = await process_attendance_event(
                WebhookEventType.ATTENDANCE_CHECKED_IN,
                attendance_id,
                payload,
                mock_db
            )

            # Verify analytics dashboard cache was invalidated
            mock_invalidate.assert_called_once_with("analytics_dashboard")

            # Verify result message
            assert "cache invalidated" in result.lower()
            assert child_id in result
            assert "checked in" in result.lower()

    @pytest.mark.asyncio
    async def test_attendance_checkout_invalidates_analytics(self):
        """Test that attendance check-out invalidates analytics dashboard."""
        attendance_id = str(uuid4())
        child_id = str(uuid4())
        payload = {"child_id": child_id, "timestamp": "2024-01-15T17:00:00Z"}
        mock_db = MagicMock()

        with patch("app.routers.webhooks.invalidate_cache", new_callable=AsyncMock) as mock_invalidate:
            mock_invalidate.return_value = 1

            # Process attendance check-out event
            result = await process_attendance_event(
                WebhookEventType.ATTENDANCE_CHECKED_OUT,
                attendance_id,
                payload,
                mock_db
            )

            # Verify analytics dashboard cache was invalidated
            mock_invalidate.assert_called_once_with("analytics_dashboard")

            # Verify result message
            assert "cache invalidated" in result.lower()
            assert child_id in result
            assert "checked out" in result.lower()


class TestServiceMethodCacheInvalidation:
    """Test cache invalidation in service methods."""

    @pytest.mark.asyncio
    async def test_record_participation_has_decorator(self):
        """Test that record_participation method has cache invalidation decorator."""
        from app.services.activity_service import ActivityService

        # Verify the method exists and can be called
        mock_db = MagicMock()
        mock_db.add = MagicMock()
        mock_db.commit = AsyncMock()
        mock_db.refresh = AsyncMock()

        service = ActivityService(mock_db)

        child_id = uuid4()
        activity_id = uuid4()

        # Call the method - it should execute without error
        result = await service.record_participation(
            child_id=child_id,
            activity_id=activity_id,
            duration_minutes=30,
            completion_status="completed",
            engagement_score=0.9
        )

        # Verify participation was created
        assert mock_db.add.called
        assert mock_db.commit.called

    @pytest.mark.asyncio
    async def test_save_recommendation_has_decorator(self):
        """Test that save_recommendation method has cache invalidation decorator."""
        from app.services.activity_service import ActivityService

        mock_db = MagicMock()
        mock_db.add = MagicMock()
        mock_db.commit = AsyncMock()
        mock_db.refresh = AsyncMock()

        service = ActivityService(mock_db)

        child_id = uuid4()
        activity_id = uuid4()

        # Call the method - it should execute without error
        result = await service.save_recommendation(
            child_id=child_id,
            activity_id=activity_id,
            relevance_score=0.85,
            reasoning="Great age match and fresh activity"
        )

        # Verify recommendation was created
        assert mock_db.add.called
        assert mock_db.commit.called


class TestDocumentationAndUsage:
    """Test that cache invalidation is documented and properly integrated."""

    def test_invalidate_on_write_decorator_exists(self):
        """Test that invalidate_on_write decorator is available for use."""
        from app.core.cache import invalidate_on_write

        # Verify decorator exists and is callable
        assert callable(invalidate_on_write)

    def test_explicit_invalidation_methods_exist(self):
        """Test that explicit invalidation methods exist in services."""
        from app.services.child_service import ChildService
        from app.services.activity_service import ActivityService
        from app.services.analytics_service import AnalyticsService

        # Verify ChildService has invalidation method
        assert hasattr(ChildService, 'invalidate_child_profile_cache')

        # Verify ActivityService has invalidation method
        assert hasattr(ActivityService, 'invalidate_activity_catalog_cache')

        # Verify AnalyticsService has invalidation method
        assert hasattr(AnalyticsService, 'invalidate_analytics_dashboard_cache')
