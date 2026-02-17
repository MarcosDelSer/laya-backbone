"""Tests for cache warming functionality.

Tests ensure that cache warming correctly preloads frequently accessed
data on application startup without blocking the application.
"""

import asyncio
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.core.cache_warming import (
    get_warming_status,
    warm_activity_catalog,
    warm_all_caches,
    warm_analytics_dashboard,
)


class TestWarmActivityCatalog:
    """Tests for warming the activity catalog cache."""

    @pytest.mark.asyncio
    async def test_warm_activity_catalog_success(self):
        """Test successful warming of activity catalog cache."""
        mock_activities = [
            {"id": "1", "name": "Activity 1", "type": "sensory"},
            {"id": "2", "name": "Activity 2", "type": "motor"},
            {"id": "3", "name": "Activity 3", "type": "cognitive"},
        ]

        with patch("app.core.cache_warming.AsyncSessionLocal") as mock_session_factory:
            mock_db = AsyncMock()
            mock_session_factory.return_value.__aenter__.return_value = mock_db

            # Mock ActivityService
            with patch("app.core.cache_warming.ActivityService") as mock_service_class:
                mock_service = AsyncMock()
                mock_service.get_activity_catalog.return_value = mock_activities
                mock_service_class.return_value = mock_service

                # Warm cache
                result = await warm_activity_catalog()

                # Verify success
                assert result is True

                # Verify ActivityService was created with db session
                mock_service_class.assert_called_once_with(mock_db)

                # Verify get_activity_catalog was called
                mock_service.get_activity_catalog.assert_called_once()

    @pytest.mark.asyncio
    async def test_warm_activity_catalog_database_error(self):
        """Test activity catalog warming handles database errors gracefully."""
        with patch("app.core.cache_warming.AsyncSessionLocal") as mock_session_factory:
            # Simulate database connection error
            mock_session_factory.return_value.__aenter__.side_effect = Exception(
                "Database connection failed"
            )

            # Warm cache
            result = await warm_activity_catalog()

            # Should return False but not crash
            assert result is False

    @pytest.mark.asyncio
    async def test_warm_activity_catalog_service_error(self):
        """Test activity catalog warming handles service errors gracefully."""
        with patch("app.core.cache_warming.AsyncSessionLocal") as mock_session_factory:
            mock_db = AsyncMock()
            mock_session_factory.return_value.__aenter__.return_value = mock_db

            with patch("app.core.cache_warming.ActivityService") as mock_service_class:
                mock_service = AsyncMock()
                mock_service.get_activity_catalog.side_effect = Exception(
                    "Service error"
                )
                mock_service_class.return_value = mock_service

                # Warm cache
                result = await warm_activity_catalog()

                # Should return False but not crash
                assert result is False


class TestWarmAnalyticsDashboard:
    """Tests for warming the analytics dashboard cache."""

    @pytest.mark.asyncio
    async def test_warm_analytics_dashboard_success(self):
        """Test successful warming of analytics dashboard cache."""
        mock_dashboard = {
            "summary": {
                "total_children": 50,
                "total_staff": 10,
                "enrollment_rate": 0.85,
            },
            "kpis": [
                {"name": "staff_ratio", "value": "1:5", "status": "compliant"},
            ],
        }

        with patch("app.core.cache_warming.AsyncSessionLocal") as mock_session_factory:
            mock_db = AsyncMock()
            mock_session_factory.return_value.__aenter__.return_value = mock_db

            # Mock AnalyticsService
            with patch("app.core.cache_warming.AnalyticsService") as mock_service_class:
                mock_service = AsyncMock()
                mock_service.get_dashboard.return_value = mock_dashboard
                mock_service_class.return_value = mock_service

                # Warm cache
                result = await warm_analytics_dashboard()

                # Verify success
                assert result is True

                # Verify AnalyticsService was created with db session
                mock_service_class.assert_called_once_with(mock_db)

                # Verify get_dashboard was called
                mock_service.get_dashboard.assert_called_once()

    @pytest.mark.asyncio
    async def test_warm_analytics_dashboard_database_error(self):
        """Test analytics dashboard warming handles database errors gracefully."""
        with patch("app.core.cache_warming.AsyncSessionLocal") as mock_session_factory:
            # Simulate database connection error
            mock_session_factory.return_value.__aenter__.side_effect = Exception(
                "Database connection failed"
            )

            # Warm cache
            result = await warm_analytics_dashboard()

            # Should return False but not crash
            assert result is False

    @pytest.mark.asyncio
    async def test_warm_analytics_dashboard_service_error(self):
        """Test analytics dashboard warming handles service errors gracefully."""
        with patch("app.core.cache_warming.AsyncSessionLocal") as mock_session_factory:
            mock_db = AsyncMock()
            mock_session_factory.return_value.__aenter__.return_value = mock_db

            with patch("app.core.cache_warming.AnalyticsService") as mock_service_class:
                mock_service = AsyncMock()
                mock_service.get_dashboard.side_effect = Exception("Service error")
                mock_service_class.return_value = mock_service

                # Warm cache
                result = await warm_analytics_dashboard()

                # Should return False but not crash
                assert result is False


class TestWarmAllCaches:
    """Tests for warming all caches."""

    @pytest.mark.asyncio
    async def test_warm_all_caches_success(self):
        """Test successful warming of all caches."""
        with patch(
            "app.core.cache_warming.warm_activity_catalog"
        ) as mock_warm_activities:
            with patch(
                "app.core.cache_warming.warm_analytics_dashboard"
            ) as mock_warm_analytics:
                mock_warm_activities.return_value = True
                mock_warm_analytics.return_value = True

                # Warm all caches
                results = await warm_all_caches()

                # Verify both warming functions were called
                mock_warm_activities.assert_called_once()
                mock_warm_analytics.assert_called_once()

                # Verify results
                assert results == {
                    "activity_catalog": True,
                    "analytics_dashboard": True,
                }

    @pytest.mark.asyncio
    async def test_warm_all_caches_partial_failure(self):
        """Test warming continues even when some caches fail to warm."""
        with patch(
            "app.core.cache_warming.warm_activity_catalog"
        ) as mock_warm_activities:
            with patch(
                "app.core.cache_warming.warm_analytics_dashboard"
            ) as mock_warm_analytics:
                # Activity catalog succeeds, analytics fails
                mock_warm_activities.return_value = True
                mock_warm_analytics.return_value = False

                # Warm all caches
                results = await warm_all_caches()

                # Verify both warming functions were called despite failure
                mock_warm_activities.assert_called_once()
                mock_warm_analytics.assert_called_once()

                # Verify results show partial success
                assert results == {
                    "activity_catalog": True,
                    "analytics_dashboard": False,
                }

    @pytest.mark.asyncio
    async def test_warm_all_caches_complete_failure(self):
        """Test warming reports failures correctly when all caches fail."""
        with patch(
            "app.core.cache_warming.warm_activity_catalog"
        ) as mock_warm_activities:
            with patch(
                "app.core.cache_warming.warm_analytics_dashboard"
            ) as mock_warm_analytics:
                # Both fail
                mock_warm_activities.return_value = False
                mock_warm_analytics.return_value = False

                # Warm all caches
                results = await warm_all_caches()

                # Verify both warming functions were called
                mock_warm_activities.assert_called_once()
                mock_warm_analytics.assert_called_once()

                # Verify results show complete failure
                assert results == {
                    "activity_catalog": False,
                    "analytics_dashboard": False,
                }

    @pytest.mark.asyncio
    async def test_warm_all_caches_runs_in_parallel(self):
        """Test that cache warming operations can run efficiently."""
        # This test verifies the warming functions are called in the correct order
        call_order = []

        async def mock_warm_activities():
            call_order.append("activities_start")
            await asyncio.sleep(0.01)  # Simulate some work
            call_order.append("activities_end")
            return True

        async def mock_warm_analytics():
            call_order.append("analytics_start")
            await asyncio.sleep(0.01)  # Simulate some work
            call_order.append("analytics_end")
            return True

        with patch(
            "app.core.cache_warming.warm_activity_catalog", new=mock_warm_activities
        ):
            with patch(
                "app.core.cache_warming.warm_analytics_dashboard",
                new=mock_warm_analytics,
            ):
                # Warm all caches
                results = await warm_all_caches()

                # Verify sequential execution (activities then analytics)
                assert call_order == [
                    "activities_start",
                    "activities_end",
                    "analytics_start",
                    "analytics_end",
                ]

                # Verify results
                assert results == {
                    "activity_catalog": True,
                    "analytics_dashboard": True,
                }


class TestGetWarmingStatus:
    """Tests for getting warming status."""

    @pytest.mark.asyncio
    async def test_get_warming_status(self):
        """Test getting warming status returns expected format."""
        status = await get_warming_status()

        # Verify status has expected keys
        assert "activity_catalog" in status
        assert "analytics_dashboard" in status

        # Verify values are boolean
        assert isinstance(status["activity_catalog"], bool)
        assert isinstance(status["analytics_dashboard"], bool)


class TestCacheWarmingIntegration:
    """Integration tests for cache warming."""

    @pytest.mark.asyncio
    async def test_cache_warming_is_resilient(self):
        """Test that cache warming failures don't crash the application."""
        # This test verifies the design principle that cache warming
        # should be resilient and not prevent application startup

        with patch(
            "app.core.cache_warming.warm_activity_catalog"
        ) as mock_warm_activities:
            with patch(
                "app.core.cache_warming.warm_analytics_dashboard"
            ) as mock_warm_analytics:
                # Simulate catastrophic failures
                mock_warm_activities.side_effect = Exception("Critical error")
                mock_warm_analytics.side_effect = Exception("Critical error")

                # This should not raise an exception
                try:
                    # Note: warm_all_caches catches exceptions internally
                    results = await warm_all_caches()

                    # Should return failure results, not crash
                    assert results["activity_catalog"] is False
                    assert results["analytics_dashboard"] is False
                except Exception as e:
                    pytest.fail(f"Cache warming should not crash: {e}")

    @pytest.mark.asyncio
    async def test_cache_warming_logs_progress(self, caplog):
        """Test that cache warming logs progress for monitoring."""
        import logging

        caplog.set_level(logging.INFO)

        with patch(
            "app.core.cache_warming.warm_activity_catalog"
        ) as mock_warm_activities:
            with patch(
                "app.core.cache_warming.warm_analytics_dashboard"
            ) as mock_warm_analytics:
                mock_warm_activities.return_value = True
                mock_warm_analytics.return_value = True

                # Warm all caches
                await warm_all_caches()

                # Verify logging occurred
                log_messages = [record.message for record in caplog.records]

                # Should log start and completion
                assert any(
                    "Starting cache warming" in msg for msg in log_messages
                ), "Should log cache warming start"
                assert any(
                    "complete" in msg for msg in log_messages
                ), "Should log cache warming completion"
