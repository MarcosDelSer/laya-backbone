"""Pydantic schemas for LAYA AI Service.

This package contains all Pydantic schema definitions for request/response
validation and serialization across the AI service domains.

Modules:
    base: Base schema classes and common mixins
    activity: Activity recommendation schemas
    analytics: Business intelligence and analytics schemas
    coaching: Special needs coaching guidance schemas
    communication: Parent communication schemas
    llm: LLM integration schemas
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
from app.schemas.llm import (
    LLMCompletionRequest,
    LLMCompletionResponse,
    LLMHealthResponse,
    LLMMessageRole,
    LLMMessageSchema,
    LLMModelInfo,
    LLMModelsListResponse,
    LLMProvider,
    LLMUsageListResponse,
    LLMUsageLogResponse,
    LLMUsageStats,
    LLMUsageSummary,
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
    # LLM schemas
    "LLMProvider",
    "LLMMessageRole",
    "LLMMessageSchema",
    "LLMCompletionRequest",
    "LLMCompletionResponse",
    "LLMUsageStats",
    "LLMUsageLogResponse",
    "LLMUsageListResponse",
    "LLMUsageSummary",
    "LLMHealthResponse",
    "LLMModelInfo",
    "LLMModelsListResponse",
]
