"""Analytics service for LAYA AI Service.

Implements business logic for KPI calculations, enrollment forecasting,
and Quebec regulatory compliance monitoring.
"""

from __future__ import annotations

from datetime import date, datetime, timedelta
from decimal import Decimal
from typing import Any, Optional
from uuid import UUID

from sqlalchemy import and_, desc, select
from sqlalchemy.ext.asyncio import AsyncSession

from app.models.analytics import (
    AnalyticsMetric,
    ComplianceCheck,
    EnrollmentForecast,
)
from app.schemas.analytics import (
    ComplianceCheckResponse,
    ComplianceCheckType,
    ComplianceListResponse,
    ComplianceStatus,
    DashboardResponse,
    DashboardSummary,
    ForecastData,
    ForecastDataPoint,
    KPIMetric,
    KPIMetricsListResponse,
    MetricCategory,
)


# Quebec childcare staff-to-child ratio requirements
QUEBEC_STAFF_RATIOS = {
    "0-18_months": 5,    # 1:5 for infants
    "18-36_months": 8,   # 1:8 for toddlers
    "36-48_months": 10,  # 1:10 for preschool
    "48-60_months": 10,  # 1:10 for pre-K
    "60+_months": 20,    # 1:20 for school-age
}


class AnalyticsService:
    """Service for analytics operations.

    Provides methods for calculating KPIs, generating enrollment forecasts,
    and monitoring Quebec regulatory compliance.

    Attributes:
        db: Async database session for data access
    """

    def __init__(self, db: AsyncSession) -> None:
        """Initialize AnalyticsService.

        Args:
            db: Async database session
        """
        self.db = db

    async def get_dashboard(
        self,
        facility_id: Optional[UUID] = None,
    ) -> DashboardResponse:
        """Get aggregated dashboard with all key metrics.

        Combines KPIs, enrollment forecast summary, and compliance status
        into a single dashboard view for facility directors.

        Args:
            facility_id: Optional facility to filter by

        Returns:
            DashboardResponse: Combined dashboard view
        """
        now = datetime.utcnow()

        # Get KPIs for the dashboard
        kpis_response = await self.get_kpis(facility_id=facility_id)
        kpis = kpis_response.metrics

        # Calculate summary from KPIs
        summary = await self._calculate_summary(kpis, facility_id)

        # Get forecast summary (3 months for dashboard)
        forecast_summary = await self.get_enrollment_forecast(
            months_ahead=3,
            include_historical=True,
            facility_id=facility_id,
        )

        # Get compliance summary
        compliance_summary = await self.get_compliance(facility_id=facility_id)

        # Generate alerts based on metrics
        alerts = await self._generate_alerts(
            summary=summary,
            compliance=compliance_summary,
        )

        return DashboardResponse(
            summary=summary,
            kpis=kpis,
            forecast_summary=forecast_summary,
            compliance_summary=compliance_summary,
            alerts=alerts,
            generated_at=now,
        )

    async def get_kpis(
        self,
        facility_id: Optional[UUID] = None,
    ) -> KPIMetricsListResponse:
        """Get detailed KPI metrics with period comparisons.

        Calculates enrollment rate, attendance percentage, revenue metrics,
        and staff-to-child ratios from stored analytics data.

        Args:
            facility_id: Optional facility to filter by

        Returns:
            KPIMetricsListResponse: List of KPI metrics
        """
        now = datetime.utcnow()
        period_start = now - timedelta(days=30)  # Last 30 days
        previous_period_start = period_start - timedelta(days=30)

        metrics: list[KPIMetric] = []

        # Fetch current period metrics from database
        current_metrics = await self._fetch_metrics_for_period(
            period_start=period_start,
            period_end=now,
            facility_id=facility_id,
        )

        # Fetch previous period metrics for comparison
        previous_metrics = await self._fetch_metrics_for_period(
            period_start=previous_period_start,
            period_end=period_start,
            facility_id=facility_id,
        )

        # Build KPI list for all categories
        for category in MetricCategory:
            kpi = await self._build_kpi_for_category(
                category=category,
                current_metrics=current_metrics,
                previous_metrics=previous_metrics,
                period_start=period_start,
                period_end=now,
                facility_id=facility_id,
            )
            metrics.append(kpi)

        return KPIMetricsListResponse(
            metrics=metrics,
            generated_at=now,
        )

    async def get_enrollment_forecast(
        self,
        months_ahead: int = 6,
        include_historical: bool = True,
        facility_id: Optional[UUID] = None,
    ) -> ForecastData:
        """Get enrollment forecast with historical context.

        Generates time-series enrollment predictions based on historical data
        using linear projection. Maximum forecast period is 12 months.

        Args:
            months_ahead: Number of months to forecast (1-12)
            include_historical: Whether to include historical enrollment data
            facility_id: Optional facility to filter by

        Returns:
            ForecastData: Historical and projected enrollment data
        """
        now = datetime.utcnow()
        today = date.today()

        # Clamp months_ahead to valid range
        months_ahead = max(1, min(12, months_ahead))

        historical: list[ForecastDataPoint] = []
        forecast: list[ForecastDataPoint] = []
        confidence_note: Optional[str] = None

        # Fetch historical enrollment data (last 12 months)
        if include_historical:
            historical = await self._fetch_historical_enrollment(
                facility_id=facility_id,
                months_back=12,
            )

        # Generate forecast based on historical data
        if len(historical) >= 6:
            # Sufficient data for forecasting
            forecast = await self._generate_forecast(
                historical=historical,
                months_ahead=months_ahead,
            )
        else:
            # Insufficient data - generate placeholder forecast
            forecast = self._generate_placeholder_forecast(
                start_date=today,
                months_ahead=months_ahead,
            )
            confidence_note = (
                "Insufficient historical data (< 6 months). "
                "Forecast confidence is limited."
            )

        return ForecastData(
            facility_id=facility_id,
            historical=historical,
            forecast=forecast,
            model_version="v1",
            generated_at=now,
            confidence_note=confidence_note,
        )

    async def get_compliance(
        self,
        facility_id: Optional[UUID] = None,
    ) -> ComplianceListResponse:
        """Get Quebec regulatory compliance status.

        Returns compliance checks for staff-to-child ratios,
        staff certifications, facility capacity, and safety inspections.

        Args:
            facility_id: Optional facility to filter by

        Returns:
            ComplianceListResponse: List of compliance checks with overall status
        """
        now = datetime.utcnow()

        # Fetch latest compliance checks from database
        checks = await self._fetch_compliance_checks(facility_id=facility_id)

        # If no checks found, return placeholder checks
        if not checks:
            checks = self._build_placeholder_compliance_checks(now=now)

        # Determine overall compliance status
        overall_status = self._calculate_overall_compliance_status(checks)

        return ComplianceListResponse(
            checks=checks,
            overall_status=overall_status,
            generated_at=now,
        )

    async def _calculate_summary(
        self,
        kpis: list[KPIMetric],
        facility_id: Optional[UUID] = None,
    ) -> DashboardSummary:
        """Calculate dashboard summary from KPIs.

        Args:
            kpis: List of KPI metrics
            facility_id: Optional facility identifier

        Returns:
            DashboardSummary: Aggregated summary metrics
        """
        # Extract values from KPIs
        enrollment_rate = Decimal("0.0")
        attendance_rate = Decimal("0.0")

        for kpi in kpis:
            if kpi.category == MetricCategory.ENROLLMENT:
                enrollment_rate = kpi.metric_value
            elif kpi.category == MetricCategory.ATTENDANCE:
                attendance_rate = kpi.metric_value

        # Calculate total enrolled and capacity from enrollment rate
        # For now, use placeholder values until actual data is available
        total_enrolled = 0
        total_capacity = 0

        # Calculate compliance score from latest compliance checks
        compliance_response = await self.get_compliance(facility_id=facility_id)
        compliance_score = self._calculate_compliance_score(
            compliance_response.checks
        )

        return DashboardSummary(
            total_enrolled=total_enrolled,
            total_capacity=total_capacity,
            enrollment_rate=float(enrollment_rate),
            average_attendance=float(attendance_rate),
            compliance_score=compliance_score,
        )

    async def _fetch_metrics_for_period(
        self,
        period_start: datetime,
        period_end: datetime,
        facility_id: Optional[UUID] = None,
    ) -> list[AnalyticsMetric]:
        """Fetch analytics metrics for a specific period.

        Args:
            period_start: Start of the period
            period_end: End of the period
            facility_id: Optional facility filter

        Returns:
            list[AnalyticsMetric]: List of metrics for the period
        """
        query = select(AnalyticsMetric).where(
            and_(
                AnalyticsMetric.period_start >= period_start,
                AnalyticsMetric.period_end <= period_end,
            )
        )

        if facility_id is not None:
            query = query.where(AnalyticsMetric.facility_id == facility_id)

        query = query.order_by(desc(AnalyticsMetric.period_end))

        result = await self.db.execute(query)
        return list(result.scalars().all())

    async def _build_kpi_for_category(
        self,
        category: MetricCategory,
        current_metrics: list[AnalyticsMetric],
        previous_metrics: list[AnalyticsMetric],
        period_start: datetime,
        period_end: datetime,
        facility_id: Optional[UUID] = None,
    ) -> KPIMetric:
        """Build a KPI metric for a specific category.

        Args:
            category: The metric category
            current_metrics: Metrics from current period
            previous_metrics: Metrics from previous period
            period_start: Start of current period
            period_end: End of current period
            facility_id: Optional facility identifier

        Returns:
            KPIMetric: KPI for the category
        """
        # Find metrics for this category
        current_value = self._get_category_value(current_metrics, category)
        previous_value = self._get_category_value(previous_metrics, category)

        # Calculate change percentage
        change_percentage: Optional[float] = None
        if previous_value is not None and previous_value != Decimal("0"):
            change = ((current_value - previous_value) / previous_value) * 100
            change_percentage = float(change)

        # Get metric name and unit for category
        metric_name, metric_unit = self._get_category_metadata(category)

        return KPIMetric(
            metric_name=metric_name,
            metric_value=current_value,
            metric_unit=metric_unit,
            category=category,
            period_start=period_start,
            period_end=period_end,
            previous_value=previous_value,
            change_percentage=change_percentage,
            facility_id=facility_id,
        )

    def _get_category_value(
        self,
        metrics: list[AnalyticsMetric],
        category: MetricCategory,
    ) -> Decimal:
        """Get the aggregated value for a category from metrics.

        Args:
            metrics: List of analytics metrics
            category: Category to extract

        Returns:
            Decimal: Aggregated value for the category
        """
        category_metrics = [
            m for m in metrics if m.category == category.value
        ]

        if not category_metrics:
            return Decimal("0.0")

        # Return the most recent metric value
        return category_metrics[0].metric_value

    def _get_category_metadata(
        self,
        category: MetricCategory,
    ) -> tuple[str, str]:
        """Get display name and unit for a metric category.

        Args:
            category: The metric category

        Returns:
            tuple[str, str]: (metric_name, metric_unit)
        """
        metadata = {
            MetricCategory.ENROLLMENT: ("Enrollment Rate", "%"),
            MetricCategory.ATTENDANCE: ("Attendance Rate", "%"),
            MetricCategory.REVENUE: ("Monthly Revenue", "CAD"),
            MetricCategory.STAFFING: ("Staff-to-Child Ratio", "ratio"),
        }
        return metadata.get(category, (category.value.title(), ""))

    async def _fetch_historical_enrollment(
        self,
        facility_id: Optional[UUID] = None,
        months_back: int = 12,
    ) -> list[ForecastDataPoint]:
        """Fetch historical enrollment data from forecasts table.

        Args:
            facility_id: Optional facility filter
            months_back: Number of months of history to fetch

        Returns:
            list[ForecastDataPoint]: Historical enrollment data points
        """
        today = date.today()
        start_date = today - timedelta(days=months_back * 30)

        query = select(EnrollmentForecast).where(
            and_(
                EnrollmentForecast.forecast_date >= start_date,
                EnrollmentForecast.forecast_date <= today,
            )
        )

        if facility_id is not None:
            query = query.where(EnrollmentForecast.facility_id == facility_id)

        query = query.order_by(EnrollmentForecast.forecast_date)

        result = await self.db.execute(query)
        forecasts = result.scalars().all()

        return [
            ForecastDataPoint(
                forecast_date=f.forecast_date,
                predicted_enrollment=f.predicted_enrollment,
                confidence_lower=f.confidence_lower,
                confidence_upper=f.confidence_upper,
                is_historical=True,
            )
            for f in forecasts
        ]

    async def _generate_forecast(
        self,
        historical: list[ForecastDataPoint],
        months_ahead: int,
    ) -> list[ForecastDataPoint]:
        """Generate enrollment forecast using linear projection.

        Args:
            historical: Historical enrollment data points
            months_ahead: Number of months to forecast

        Returns:
            list[ForecastDataPoint]: Forecasted enrollment data points
        """
        if not historical:
            return []

        # Simple linear regression for trend
        n = len(historical)
        x_values = list(range(n))
        y_values = [h.predicted_enrollment for h in historical]

        # Calculate slope and intercept
        x_mean = sum(x_values) / n
        y_mean = sum(y_values) / n

        numerator = sum(
            (x - x_mean) * (y - y_mean)
            for x, y in zip(x_values, y_values)
        )
        denominator = sum((x - x_mean) ** 2 for x in x_values)

        if denominator == 0:
            slope = 0.0
        else:
            slope = numerator / denominator

        intercept = y_mean - slope * x_mean

        # Generate forecast points
        forecasts: list[ForecastDataPoint] = []
        last_date = historical[-1].forecast_date
        std_dev = self._calculate_std_dev(y_values, y_mean)

        for i in range(1, months_ahead + 1):
            forecast_date = last_date + timedelta(days=i * 30)
            predicted = intercept + slope * (n + i - 1)
            predicted_int = max(0, int(round(predicted)))

            # Confidence interval widens with time
            margin = int(std_dev * (1 + 0.1 * i))
            confidence_lower = max(0, predicted_int - margin)
            confidence_upper = predicted_int + margin

            forecasts.append(
                ForecastDataPoint(
                    forecast_date=forecast_date,
                    predicted_enrollment=predicted_int,
                    confidence_lower=confidence_lower,
                    confidence_upper=confidence_upper,
                    is_historical=False,
                )
            )

        return forecasts

    def _generate_placeholder_forecast(
        self,
        start_date: date,
        months_ahead: int,
    ) -> list[ForecastDataPoint]:
        """Generate placeholder forecast when insufficient historical data.

        Args:
            start_date: Starting date for forecast
            months_ahead: Number of months to forecast

        Returns:
            list[ForecastDataPoint]: Placeholder forecast data points
        """
        forecasts: list[ForecastDataPoint] = []

        for i in range(1, months_ahead + 1):
            forecast_date = start_date + timedelta(days=i * 30)
            forecasts.append(
                ForecastDataPoint(
                    forecast_date=forecast_date,
                    predicted_enrollment=0,
                    confidence_lower=None,
                    confidence_upper=None,
                    is_historical=False,
                )
            )

        return forecasts

    def _calculate_std_dev(
        self,
        values: list[int],
        mean: float,
    ) -> float:
        """Calculate standard deviation for confidence intervals.

        Args:
            values: List of values
            mean: Mean of values

        Returns:
            float: Standard deviation
        """
        if len(values) < 2:
            return 0.0

        variance = sum((v - mean) ** 2 for v in values) / (len(values) - 1)
        return variance ** 0.5

    async def _fetch_compliance_checks(
        self,
        facility_id: Optional[UUID] = None,
    ) -> list[ComplianceCheckResponse]:
        """Fetch latest compliance checks from database.

        Args:
            facility_id: Optional facility filter

        Returns:
            list[ComplianceCheckResponse]: List of compliance check responses
        """
        # Fetch the most recent check for each check type
        checks: list[ComplianceCheckResponse] = []

        for check_type in ComplianceCheckType:
            query = select(ComplianceCheck).where(
                ComplianceCheck.check_type == check_type.value
            )

            if facility_id is not None:
                query = query.where(ComplianceCheck.facility_id == facility_id)

            query = query.order_by(desc(ComplianceCheck.checked_at)).limit(1)

            result = await self.db.execute(query)
            check = result.scalar_one_or_none()

            if check:
                checks.append(
                    ComplianceCheckResponse(
                        check_type=ComplianceCheckType(check.check_type),
                        status=ComplianceStatus(check.status),
                        details=check.details,
                        checked_at=check.checked_at,
                        next_check_due=check.next_check_due,
                        facility_id=check.facility_id,
                        recommendation=self._get_recommendation_for_status(
                            ComplianceCheckType(check.check_type),
                            ComplianceStatus(check.status),
                        ),
                    )
                )

        return checks

    def _build_placeholder_compliance_checks(
        self,
        now: datetime,
    ) -> list[ComplianceCheckResponse]:
        """Build placeholder compliance checks when no data exists.

        Args:
            now: Current timestamp

        Returns:
            list[ComplianceCheckResponse]: Placeholder compliance checks
        """
        return [
            ComplianceCheckResponse(
                check_type=ComplianceCheckType.STAFF_RATIO,
                status=ComplianceStatus.UNKNOWN,
                details={"message": "No staff ratio data available"},
                checked_at=now,
                next_check_due=None,
                facility_id=None,
                recommendation="Configure staff and enrollment data to enable ratio monitoring",
            ),
            ComplianceCheckResponse(
                check_type=ComplianceCheckType.CERTIFICATION,
                status=ComplianceStatus.UNKNOWN,
                details={"message": "No certification data available"},
                checked_at=now,
                next_check_due=None,
                facility_id=None,
                recommendation="Upload staff certification records",
            ),
            ComplianceCheckResponse(
                check_type=ComplianceCheckType.CAPACITY,
                status=ComplianceStatus.UNKNOWN,
                details={"message": "No capacity data available"},
                checked_at=now,
                next_check_due=None,
                facility_id=None,
                recommendation="Set facility capacity limits",
            ),
            ComplianceCheckResponse(
                check_type=ComplianceCheckType.SAFETY,
                status=ComplianceStatus.UNKNOWN,
                details={"message": "No safety inspection data available"},
                checked_at=now,
                next_check_due=None,
                facility_id=None,
                recommendation="Record safety inspection results",
            ),
        ]

    def _get_recommendation_for_status(
        self,
        check_type: ComplianceCheckType,
        status: ComplianceStatus,
    ) -> Optional[str]:
        """Get recommendation based on compliance status.

        Args:
            check_type: Type of compliance check
            status: Current status

        Returns:
            Optional[str]: Recommendation if applicable
        """
        if status == ComplianceStatus.COMPLIANT:
            return None

        recommendations = {
            ComplianceCheckType.STAFF_RATIO: {
                ComplianceStatus.WARNING: (
                    "Staff ratio approaching limits. Consider hiring additional staff."
                ),
                ComplianceStatus.VIOLATION: (
                    "Staff ratio exceeds Quebec regulations. "
                    "Immediate action required to hire additional staff."
                ),
                ComplianceStatus.UNKNOWN: (
                    "Configure staff and enrollment data to enable ratio monitoring"
                ),
            },
            ComplianceCheckType.CERTIFICATION: {
                ComplianceStatus.WARNING: (
                    "Staff certifications expiring soon. Schedule renewals."
                ),
                ComplianceStatus.VIOLATION: (
                    "Staff certifications have expired. "
                    "Immediate renewal required."
                ),
                ComplianceStatus.UNKNOWN: (
                    "Upload staff certification records"
                ),
            },
            ComplianceCheckType.CAPACITY: {
                ComplianceStatus.WARNING: (
                    "Approaching capacity limits. Monitor enrollment closely."
                ),
                ComplianceStatus.VIOLATION: (
                    "Facility capacity exceeded. "
                    "Reduce enrollment to comply with permit."
                ),
                ComplianceStatus.UNKNOWN: (
                    "Set facility capacity limits"
                ),
            },
            ComplianceCheckType.SAFETY: {
                ComplianceStatus.WARNING: (
                    "Safety inspection due soon. Schedule inspection."
                ),
                ComplianceStatus.VIOLATION: (
                    "Safety inspection overdue or failed. "
                    "Schedule immediate inspection."
                ),
                ComplianceStatus.UNKNOWN: (
                    "Record safety inspection results"
                ),
            },
        }

        check_recommendations = recommendations.get(check_type, {})
        return check_recommendations.get(status)

    def _calculate_overall_compliance_status(
        self,
        checks: list[ComplianceCheckResponse],
    ) -> ComplianceStatus:
        """Calculate overall compliance status from individual checks.

        Args:
            checks: List of compliance check responses

        Returns:
            ComplianceStatus: Overall compliance status
        """
        if not checks:
            return ComplianceStatus.UNKNOWN

        statuses = [check.status for check in checks]

        if ComplianceStatus.VIOLATION in statuses:
            return ComplianceStatus.VIOLATION
        elif ComplianceStatus.WARNING in statuses:
            return ComplianceStatus.WARNING
        elif all(s == ComplianceStatus.COMPLIANT for s in statuses):
            return ComplianceStatus.COMPLIANT
        else:
            return ComplianceStatus.UNKNOWN

    def _calculate_compliance_score(
        self,
        checks: list[ComplianceCheckResponse],
    ) -> float:
        """Calculate compliance score as percentage (0-100).

        Args:
            checks: List of compliance check responses

        Returns:
            float: Compliance score from 0 to 100
        """
        if not checks:
            return 0.0

        status_scores = {
            ComplianceStatus.COMPLIANT: 100.0,
            ComplianceStatus.WARNING: 75.0,
            ComplianceStatus.VIOLATION: 25.0,
            ComplianceStatus.UNKNOWN: 50.0,
        }

        total_score = sum(
            status_scores.get(check.status, 50.0) for check in checks
        )

        return total_score / len(checks)

    async def _generate_alerts(
        self,
        summary: DashboardSummary,
        compliance: ComplianceListResponse,
    ) -> list[str]:
        """Generate alert messages based on dashboard data.

        Args:
            summary: Dashboard summary metrics
            compliance: Compliance check results

        Returns:
            list[str]: List of alert messages
        """
        alerts: list[str] = []

        # Check enrollment rate
        if summary.total_capacity == 0:
            alerts.append("No capacity data available - please configure facility capacity")
        elif summary.enrollment_rate < 50:
            alerts.append(
                f"Low enrollment rate ({summary.enrollment_rate:.1f}%). "
                "Consider marketing initiatives."
            )

        # Check attendance
        if summary.average_attendance < 70 and summary.average_attendance > 0:
            alerts.append(
                f"Below average attendance ({summary.average_attendance:.1f}%). "
                "Review attendance patterns."
            )

        # Check compliance
        for check in compliance.checks:
            if check.status == ComplianceStatus.VIOLATION:
                alerts.append(
                    f"Compliance violation: {check.check_type.value}. "
                    "Immediate action required."
                )
            elif check.status == ComplianceStatus.WARNING:
                alerts.append(
                    f"Compliance warning: {check.check_type.value}. "
                    "Review recommended."
                )

        # Default alert if no data
        if not alerts and summary.total_enrolled == 0:
            alerts.append("No data available - please configure data sources")

        return alerts

    @staticmethod
    def check_staff_ratio_compliance(
        staff_count: int,
        children_count: int,
        age_group: str,
    ) -> ComplianceStatus:
        """Check if staff-to-child ratio meets Quebec regulations.

        Args:
            staff_count: Number of staff
            children_count: Number of children
            age_group: Age group key (e.g., '0-18_months')

        Returns:
            ComplianceStatus: Compliance status for the ratio
        """
        if staff_count == 0 and children_count > 0:
            return ComplianceStatus.VIOLATION

        if children_count == 0:
            return ComplianceStatus.COMPLIANT

        required_ratio = QUEBEC_STAFF_RATIOS.get(age_group)
        if required_ratio is None:
            return ComplianceStatus.UNKNOWN

        actual_ratio = children_count / staff_count

        if actual_ratio <= required_ratio:
            return ComplianceStatus.COMPLIANT
        elif actual_ratio <= required_ratio * 1.1:  # 10% tolerance as warning
            return ComplianceStatus.WARNING
        else:
            return ComplianceStatus.VIOLATION
