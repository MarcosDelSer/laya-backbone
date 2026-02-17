"""Pydantic schemas for LAYA AI Service.

This package contains all Pydantic schema definitions for request/response
validation and serialization across the AI service domains.

Modules:
    base: Base schema classes and common mixins
    activity: Activity recommendation schemas
    analytics: Business intelligence and analytics schemas
    coaching: Special needs coaching guidance schemas
    communication: Parent communication schemas
    intervention_plan: Intervention plan schemas for special needs support
"""

from app.schemas.activity import (
    ActivityBase,
    ActivityDifficulty,
    ActivityListResponse,
    ActivityRecommendation,
    ActivityRecommendationRequest,
    ActivityRecommendationResponse,
    ActivityRequest,
    ActivityResponse,
    ActivityType,
    AgeRange,
)
from app.schemas.base import (
    BaseResponse,
    BaseSchema,
    IDMixin,
    PaginatedResponse,
    PaginationParams,
    TimestampMixin,
)
from app.schemas.analytics import (
    ComplianceCheckResponse,
    ComplianceCheckType,
    ComplianceCheckWithID,
    ComplianceListResponse,
    ComplianceStatus,
    DashboardResponse,
    DashboardSummary,
    ForecastData,
    ForecastDataPoint,
    KPIMetric,
    KPIMetricResponse,
    KPIMetricsListResponse,
    MetricCategory,
)
from app.schemas.coaching import (
    CoachingBase,
    CoachingCategory,
    CoachingGuidance,
    CoachingGuidanceRequest,
    CoachingGuidanceResponse,
    CoachingListResponse,
    CoachingPriority,
    CoachingRequest,
    CoachingResponse,
    SpecialNeedType,
)
from app.schemas.communication import (
    CommunicationPreferenceRequest,
    CommunicationPreferenceResponse,
    DevelopmentalArea,
    GenerateReportRequest,
    HomeActivitiesListResponse,
    HomeActivitiesRequest,
    HomeActivityBase,
    HomeActivityResponse,
    Language,
    ParentReportListResponse,
    ParentReportResponse,
    ReportFrequency,
)
from app.schemas.intervention_plan import (
    # Enums
    GoalStatus,
    InterventionPlanStatus,
    MonitoringMethod,
    NeedCategory,
    NeedPriority,
    ParentActivityType,
    ProgressLevel,
    ResponsibleParty,
    ReviewSchedule,
    SpecialistType,
    StrengthCategory,
    # Part 2 - Strengths
    StrengthBase,
    StrengthCreate,
    StrengthResponse,
    StrengthUpdate,
    # Part 3 - Needs
    NeedBase,
    NeedCreate,
    NeedResponse,
    NeedUpdate,
    # Part 4 - SMART Goals
    SMARTGoalBase,
    SMARTGoalCreate,
    SMARTGoalResponse,
    SMARTGoalUpdate,
    # Part 5 - Strategies
    StrategyBase,
    StrategyCreate,
    StrategyResponse,
    StrategyUpdate,
    # Part 6 - Monitoring
    MonitoringBase,
    MonitoringCreate,
    MonitoringResponse,
    MonitoringUpdate,
    # Part 7 - Parent Involvement
    ParentInvolvementBase,
    ParentInvolvementCreate,
    ParentInvolvementResponse,
    ParentInvolvementUpdate,
    # Part 8 - Consultations
    ConsultationBase,
    ConsultationCreate,
    ConsultationResponse,
    ConsultationUpdate,
    # Progress Tracking
    ProgressBase,
    ProgressCreate,
    ProgressResponse,
    ProgressUpdate,
    # Version History
    VersionBase,
    VersionCreate,
    VersionResponse,
    # Main Intervention Plan
    InterventionPlanBase,
    InterventionPlanCreate,
    InterventionPlanListResponse,
    InterventionPlanResponse,
    InterventionPlanSummary,
    InterventionPlanUpdate,
    # Parent Signature
    ParentSignatureRequest,
    ParentSignatureResponse,
    # Review Reminders
    PlanReviewReminder,
    PlanReviewReminderListResponse,
)

__all__ = [
    # Base schemas
    "BaseSchema",
    "BaseResponse",
    "TimestampMixin",
    "IDMixin",
    "PaginationParams",
    "PaginatedResponse",
    # Activity schemas
    "ActivityType",
    "ActivityDifficulty",
    "AgeRange",
    "ActivityBase",
    "ActivityRequest",
    "ActivityResponse",
    "ActivityRecommendationRequest",
    "ActivityRecommendation",
    "ActivityRecommendationResponse",
    "ActivityListResponse",
    # Analytics schemas
    "MetricCategory",
    "ComplianceStatus",
    "ComplianceCheckType",
    "KPIMetric",
    "KPIMetricResponse",
    "KPIMetricsListResponse",
    "ForecastDataPoint",
    "ForecastData",
    "ComplianceCheckResponse",
    "ComplianceCheckWithID",
    "ComplianceListResponse",
    "DashboardSummary",
    "DashboardResponse",
    # Coaching schemas
    "SpecialNeedType",
    "CoachingCategory",
    "CoachingPriority",
    "CoachingBase",
    "CoachingRequest",
    "CoachingResponse",
    "CoachingGuidanceRequest",
    "CoachingGuidance",
    "CoachingGuidanceResponse",
    "CoachingListResponse",
    # Communication schemas
    "Language",
    "ReportFrequency",
    "DevelopmentalArea",
    "GenerateReportRequest",
    "HomeActivitiesRequest",
    "CommunicationPreferenceRequest",
    "ParentReportResponse",
    "HomeActivityBase",
    "HomeActivityResponse",
    "HomeActivitiesListResponse",
    "CommunicationPreferenceResponse",
    "ParentReportListResponse",
    # Intervention plan schemas - Enums
    "InterventionPlanStatus",
    "ReviewSchedule",
    "GoalStatus",
    "ProgressLevel",
    "StrengthCategory",
    "NeedCategory",
    "NeedPriority",
    "ResponsibleParty",
    "MonitoringMethod",
    "ParentActivityType",
    "SpecialistType",
    # Intervention plan schemas - Part 2 Strengths
    "StrengthBase",
    "StrengthCreate",
    "StrengthUpdate",
    "StrengthResponse",
    # Intervention plan schemas - Part 3 Needs
    "NeedBase",
    "NeedCreate",
    "NeedUpdate",
    "NeedResponse",
    # Intervention plan schemas - Part 4 SMART Goals
    "SMARTGoalBase",
    "SMARTGoalCreate",
    "SMARTGoalUpdate",
    "SMARTGoalResponse",
    # Intervention plan schemas - Part 5 Strategies
    "StrategyBase",
    "StrategyCreate",
    "StrategyUpdate",
    "StrategyResponse",
    # Intervention plan schemas - Part 6 Monitoring
    "MonitoringBase",
    "MonitoringCreate",
    "MonitoringUpdate",
    "MonitoringResponse",
    # Intervention plan schemas - Part 7 Parent Involvement
    "ParentInvolvementBase",
    "ParentInvolvementCreate",
    "ParentInvolvementUpdate",
    "ParentInvolvementResponse",
    # Intervention plan schemas - Part 8 Consultations
    "ConsultationBase",
    "ConsultationCreate",
    "ConsultationUpdate",
    "ConsultationResponse",
    # Intervention plan schemas - Progress Tracking
    "ProgressBase",
    "ProgressCreate",
    "ProgressUpdate",
    "ProgressResponse",
    # Intervention plan schemas - Version History
    "VersionBase",
    "VersionCreate",
    "VersionResponse",
    # Intervention plan schemas - Main Plan
    "InterventionPlanBase",
    "InterventionPlanCreate",
    "InterventionPlanUpdate",
    "InterventionPlanResponse",
    "InterventionPlanSummary",
    "InterventionPlanListResponse",
    # Intervention plan schemas - Parent Signature
    "ParentSignatureRequest",
    "ParentSignatureResponse",
    # Intervention plan schemas - Review Reminders
    "PlanReviewReminder",
    "PlanReviewReminderListResponse",
]
