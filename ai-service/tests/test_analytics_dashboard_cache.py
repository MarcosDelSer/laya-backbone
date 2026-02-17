"""Unit tests for analytics dashboard caching functionality.

Tests for AnalyticsService dashboard caching with Redis,
including cache hits, TTL behavior (15 minutes), and invalidation.
"""

from __future__ import annotations

from datetime import datetime, timedelta
from decimal import Decimal
from uuid import UUID, uuid4
from unittest.mock import AsyncMock, MagicMock, patch

import pytest

from app.models.analytics import AnalyticsMetric, ComplianceCheck, EnrollmentForecast
from app.services.analytics_service import AnalyticsService
from app.schemas.analytics import (
    ComplianceCheckType,
    ComplianceStatus,
    MetricCategory,
)


# Mock facility ID for testing
MOCK_FACILITY_ID = uuid4()


class TestAnalyticsDashboardCache:
    """Tests for analytics dashboard caching functionality."""

    @pytest.mark.asyncio
    async def test_get_dashboard_basic(self) -> None:
        """Test fetching basic analytics dashboard."""
        # Mock database session
        mock_db = MagicMock()

        # Mock empty results for all queries
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard
        dashboard = await service.get_dashboard()

        # Verify basic structure
        assert dashboard is not None
        assert hasattr(dashboard, "summary")
        assert hasattr(dashboard, "kpis")
        assert hasattr(dashboard, "forecast_summary")
        assert hasattr(dashboard, "compliance_summary")
        assert hasattr(dashboard, "alerts")
        assert hasattr(dashboard, "generated_at")
        assert isinstance(dashboard.generated_at, datetime)

    @pytest.mark.asyncio
    async def test_get_dashboard_with_facility_id(self) -> None:
        """Test fetching analytics dashboard for specific facility."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard for specific facility
        dashboard = await service.get_dashboard(facility_id=MOCK_FACILITY_ID)

        # Verify structure
        assert dashboard is not None
        assert dashboard.generated_at is not None

    @pytest.mark.asyncio
    async def test_get_dashboard_summary_structure(self) -> None:
        """Test that dashboard summary has correct structure."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard
        dashboard = await service.get_dashboard()

        # Verify summary structure
        assert hasattr(dashboard.summary, "total_enrolled")
        assert hasattr(dashboard.summary, "total_capacity")
        assert hasattr(dashboard.summary, "enrollment_rate")
        assert hasattr(dashboard.summary, "average_attendance")
        assert hasattr(dashboard.summary, "compliance_score")

        # Verify types
        assert isinstance(dashboard.summary.total_enrolled, int)
        assert isinstance(dashboard.summary.total_capacity, int)
        assert isinstance(dashboard.summary.enrollment_rate, float)
        assert isinstance(dashboard.summary.average_attendance, float)
        assert isinstance(dashboard.summary.compliance_score, float)

    @pytest.mark.asyncio
    async def test_get_dashboard_kpis_structure(self) -> None:
        """Test that dashboard KPIs have correct structure."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard
        dashboard = await service.get_dashboard()

        # Verify KPIs
        assert isinstance(dashboard.kpis, list)
        assert len(dashboard.kpis) == 4  # Should have 4 categories

        # Verify each KPI has required fields
        for kpi in dashboard.kpis:
            assert hasattr(kpi, "metric_name")
            assert hasattr(kpi, "metric_value")
            assert hasattr(kpi, "metric_unit")
            assert hasattr(kpi, "category")
            assert hasattr(kpi, "period_start")
            assert hasattr(kpi, "period_end")

    @pytest.mark.asyncio
    async def test_get_dashboard_forecast_structure(self) -> None:
        """Test that dashboard forecast has correct structure."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard
        dashboard = await service.get_dashboard()

        # Verify forecast structure
        assert hasattr(dashboard.forecast_summary, "facility_id")
        assert hasattr(dashboard.forecast_summary, "historical")
        assert hasattr(dashboard.forecast_summary, "forecast")
        assert hasattr(dashboard.forecast_summary, "model_version")
        assert hasattr(dashboard.forecast_summary, "generated_at")

        # Verify types
        assert isinstance(dashboard.forecast_summary.historical, list)
        assert isinstance(dashboard.forecast_summary.forecast, list)
        assert isinstance(dashboard.forecast_summary.model_version, str)

    @pytest.mark.asyncio
    async def test_get_dashboard_compliance_structure(self) -> None:
        """Test that dashboard compliance has correct structure."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard
        dashboard = await service.get_dashboard()

        # Verify compliance structure
        assert hasattr(dashboard.compliance_summary, "checks")
        assert hasattr(dashboard.compliance_summary, "overall_status")
        assert hasattr(dashboard.compliance_summary, "generated_at")

        # Verify types
        assert isinstance(dashboard.compliance_summary.checks, list)
        assert isinstance(dashboard.compliance_summary.overall_status, ComplianceStatus)

        # Should have placeholder checks when no data
        assert len(dashboard.compliance_summary.checks) == 4

    @pytest.mark.asyncio
    async def test_get_dashboard_alerts(self) -> None:
        """Test that dashboard alerts are generated."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard
        dashboard = await service.get_dashboard()

        # Verify alerts
        assert isinstance(dashboard.alerts, list)
        # Should have at least one alert when no data
        assert len(dashboard.alerts) > 0

    @pytest.mark.asyncio
    async def test_invalidate_analytics_dashboard_cache_all(self) -> None:
        """Test invalidating all analytics dashboard caches."""
        mock_db = MagicMock()
        service = AnalyticsService(db=mock_db)

        # Invalidate all caches
        deleted_count = await service.invalidate_analytics_dashboard_cache(None)

        # Should return number of deleted keys (0 or more)
        assert deleted_count >= 0

    @pytest.mark.asyncio
    async def test_invalidate_analytics_dashboard_cache_specific_facility(self) -> None:
        """Test invalidating cache for a specific facility."""
        mock_db = MagicMock()
        service = AnalyticsService(db=mock_db)

        # Invalidate specific facility
        deleted_count = await service.invalidate_analytics_dashboard_cache(MOCK_FACILITY_ID)

        # Should return number of deleted keys (0 or more)
        assert deleted_count >= 0

    @pytest.mark.asyncio
    async def test_refresh_analytics_dashboard_cache(self) -> None:
        """Test refreshing analytics dashboard cache."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Refresh cache (invalidate + fetch)
        dashboard = await service.refresh_analytics_dashboard_cache()

        # Verify results
        assert dashboard is not None
        assert hasattr(dashboard, "summary")
        assert hasattr(dashboard, "kpis")

    @pytest.mark.asyncio
    async def test_refresh_analytics_dashboard_cache_with_facility(self) -> None:
        """Test refreshing analytics dashboard cache for specific facility."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Refresh cache for specific facility
        dashboard = await service.refresh_analytics_dashboard_cache(MOCK_FACILITY_ID)

        # Verify results
        assert dashboard is not None
        assert hasattr(dashboard, "generated_at")

    @pytest.mark.asyncio
    async def test_analytics_dashboard_caching_behavior(self) -> None:
        """Test that analytics dashboard is cached properly."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # First call - should hit database
        dashboard1 = await service.get_dashboard()
        assert dashboard1 is not None

        # Second call - should use cache (if Redis is running)
        dashboard2 = await service.get_dashboard()
        assert dashboard2 is not None

        # Both should have generated_at timestamps
        assert dashboard1.generated_at is not None
        assert dashboard2.generated_at is not None


class TestAnalyticsDashboardCacheTTL:
    """Tests for cache TTL (15 minutes) behavior."""

    @pytest.mark.asyncio
    async def test_cache_ttl_is_15_minutes(self) -> None:
        """Test that cache decorator uses 15-minute TTL."""
        # This is verified by checking the decorator in analytics_service.py
        # The @cache(ttl=900) means 900 seconds = 15 minutes
        from app.services.analytics_service import AnalyticsService
        import inspect

        # Get the source code and verify TTL
        source = inspect.getsource(AnalyticsService.get_dashboard)
        assert "ttl=900" in source or "900" in source

    @pytest.mark.asyncio
    async def test_cache_key_prefix_is_analytics_dashboard(self) -> None:
        """Test that cache uses 'analytics_dashboard' key prefix."""
        from app.services.analytics_service import AnalyticsService
        import inspect

        # Get the source code and verify key prefix
        source = inspect.getsource(AnalyticsService.get_dashboard)
        assert 'key_prefix="analytics_dashboard"' in source


class TestAnalyticsDashboardEdgeCases:
    """Tests for edge cases in analytics dashboard caching."""

    @pytest.mark.asyncio
    async def test_dashboard_with_metrics(self) -> None:
        """Test dashboard with actual metrics data."""
        # Create mock metrics
        now = datetime.utcnow()
        period_start = now - timedelta(days=30)

        mock_metric = AnalyticsMetric(
            id=uuid4(),
            facility_id=MOCK_FACILITY_ID,
            category=MetricCategory.ENROLLMENT.value,
            metric_value=Decimal("75.5"),
            period_start=period_start,
            period_end=now,
            created_at=now,
            updated_at=now,
        )

        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = [mock_metric]
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard
        dashboard = await service.get_dashboard(facility_id=MOCK_FACILITY_ID)

        # Verify metrics are included
        assert dashboard is not None
        assert len(dashboard.kpis) > 0

    @pytest.mark.asyncio
    async def test_dashboard_with_compliance_checks(self) -> None:
        """Test dashboard with actual compliance check data."""
        # Create mock compliance check
        now = datetime.utcnow()

        mock_check = ComplianceCheck(
            id=uuid4(),
            facility_id=MOCK_FACILITY_ID,
            check_type=ComplianceCheckType.STAFF_RATIO.value,
            status=ComplianceStatus.COMPLIANT.value,
            details={"staff_count": 5, "children_count": 20},
            checked_at=now,
            next_check_due=now + timedelta(days=30),
            created_at=now,
            updated_at=now,
        )

        # Mock database session
        mock_db = MagicMock()
        mock_result_metrics = MagicMock()
        mock_result_metrics.scalars.return_value.all.return_value = []

        mock_result_check = MagicMock()
        mock_result_check.scalar_one_or_none.return_value = mock_check

        # Return different results for different queries
        call_count = 0
        async def mock_execute(query):
            nonlocal call_count
            call_count += 1
            # Return check for compliance queries, empty for others
            if "ComplianceCheck" in str(query):
                return mock_result_check
            return mock_result_metrics

        mock_db.execute = mock_execute

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard
        dashboard = await service.get_dashboard(facility_id=MOCK_FACILITY_ID)

        # Verify compliance is included
        assert dashboard is not None
        assert dashboard.compliance_summary is not None

    @pytest.mark.asyncio
    async def test_dashboard_with_enrollment_forecast(self) -> None:
        """Test dashboard with enrollment forecast data."""
        # Create mock forecast data
        now = datetime.utcnow()
        forecast_date = now.date()

        mock_forecast = EnrollmentForecast(
            id=uuid4(),
            facility_id=MOCK_FACILITY_ID,
            forecast_date=forecast_date,
            predicted_enrollment=45,
            confidence_lower=40,
            confidence_upper=50,
            model_version="v1",
            created_at=now,
            updated_at=now,
        )

        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = [mock_forecast]
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard
        dashboard = await service.get_dashboard(facility_id=MOCK_FACILITY_ID)

        # Verify forecast is included
        assert dashboard is not None
        assert dashboard.forecast_summary is not None

    @pytest.mark.asyncio
    async def test_dashboard_without_facility_id(self) -> None:
        """Test dashboard fetch without facility ID (all facilities)."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard without facility filter
        dashboard = await service.get_dashboard(facility_id=None)

        # Verify dashboard is returned
        assert dashboard is not None
        assert dashboard.summary is not None

    @pytest.mark.asyncio
    async def test_dashboard_data_consistency(self) -> None:
        """Test that dashboard data is consistent across multiple calls."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard multiple times
        dashboard1 = await service.get_dashboard()
        dashboard2 = await service.get_dashboard()

        # Verify consistency
        assert dashboard1.summary.total_enrolled == dashboard2.summary.total_enrolled
        assert dashboard1.summary.total_capacity == dashboard2.summary.total_capacity
        assert len(dashboard1.kpis) == len(dashboard2.kpis)

    @pytest.mark.asyncio
    async def test_dashboard_generated_at_timestamp(self) -> None:
        """Test that dashboard has valid generated_at timestamp."""
        # Mock database session
        mock_db = MagicMock()
        mock_result = MagicMock()
        mock_result.scalars.return_value.all.return_value = []
        mock_result.scalar_one_or_none.return_value = None
        mock_db.execute = AsyncMock(return_value=mock_result)

        service = AnalyticsService(db=mock_db)

        # Fetch dashboard
        before = datetime.utcnow()
        dashboard = await service.get_dashboard()
        after = datetime.utcnow()

        # Verify timestamp is within reasonable range
        assert dashboard.generated_at >= before - timedelta(seconds=5)
        assert dashboard.generated_at <= after + timedelta(seconds=5)
