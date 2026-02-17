"""QA Diagnostics schemas for LAYA AI Service.

Defines Pydantic schemas for iOS real-device QA diagnostics payloads.
These schemas validate diagnostic bundles uploaded during LLM-based
exploratory testing sessions on physical iOS devices.
"""

from datetime import datetime
from enum import Enum
from typing import Any, Optional

from pydantic import Field, field_validator

from app.schemas.base import BaseSchema


class LogLevel(str, Enum):
    """Log severity levels for diagnostic log entries.

    Attributes:
        DEBUG: Debug-level messages
        INFO: Informational messages
        WARNING: Warning messages
        ERROR: Error messages
        FATAL: Fatal/critical messages
    """

    DEBUG = "debug"
    INFO = "info"
    WARNING = "warning"
    ERROR = "error"
    FATAL = "fatal"


class BatteryState(str, Enum):
    """Device battery charging states.

    Attributes:
        UNPLUGGED: Device is not connected to power
        CHARGING: Device is actively charging
        FULL: Device battery is full
    """

    UNPLUGGED = "unplugged"
    CHARGING = "charging"
    FULL = "full"


class NetworkErrorType(str, Enum):
    """Classification of network error types.

    Attributes:
        TIMEOUT: Request timed out
        NETWORK_UNREACHABLE: No network connectivity
        SSL_ERROR: TLS/SSL handshake failure
        DNS_FAILURE: DNS resolution failed
        SERVER_ERROR: HTTP 5xx response
        CLIENT_ERROR: HTTP 4xx response
        UNKNOWN: Unclassified error
    """

    TIMEOUT = "timeout"
    NETWORK_UNREACHABLE = "network_unreachable"
    SSL_ERROR = "ssl_error"
    DNS_FAILURE = "dns_failure"
    SERVER_ERROR = "server_error"
    CLIENT_ERROR = "client_error"
    UNKNOWN = "unknown"


class AppEnvironment(str, Enum):
    """Application deployment environments.

    Attributes:
        DEVELOPMENT: Development environment
        STAGING: Staging/QA environment
        PRODUCTION: Production environment
    """

    DEVELOPMENT = "development"
    STAGING = "staging"
    PRODUCTION = "production"


class DiagnosticsStatus(str, Enum):
    """Processing status of diagnostics bundles.

    Attributes:
        ACCEPTED: Diagnostics bundle was accepted
        REJECTED: Diagnostics bundle was rejected (validation failure)
        PROCESSING: Diagnostics bundle is being processed
    """

    ACCEPTED = "accepted"
    REJECTED = "rejected"
    PROCESSING = "processing"


class AppMetadata(BaseSchema):
    """Application metadata for diagnostics bundle.

    Contains identifying information about the iOS app that
    generated the diagnostics.

    Attributes:
        app_name: Application display name (e.g., "TeacherApp")
        app_version: Semantic version string (e.g., "2.1.0")
        build_number: CI/CD build identifier
        bundle_id: iOS bundle identifier (e.g., "com.laya.teacherapp")
        environment: Deployment environment
    """

    app_name: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Application display name",
    )
    app_version: str = Field(
        ...,
        min_length=1,
        max_length=50,
        pattern=r"^\d+\.\d+(\.\d+)?(-[a-zA-Z0-9]+)?$",
        description="Semantic version string (e.g., '2.1.0')",
    )
    build_number: str = Field(
        ...,
        min_length=1,
        max_length=50,
        description="CI/CD build identifier",
    )
    bundle_id: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="iOS bundle identifier (e.g., 'com.laya.teacherapp')",
    )
    environment: AppEnvironment = Field(
        ...,
        description="Deployment environment",
    )


class DeviceMetadata(BaseSchema):
    """Device metadata for diagnostics bundle.

    Contains information about the physical iOS device that
    generated the diagnostics.

    Attributes:
        device_model: Human-readable device model
        device_identifier: Hashed device ID (redacted)
        ios_version: iOS version string
        locale: Device locale
        timezone: Device timezone
        is_simulator: Should be false for real-device runs
        available_storage_mb: Free storage in MB
        total_memory_mb: Total device memory in MB
        battery_level: Battery percentage (0.0-1.0)
        battery_state: Battery charging state
    """

    device_model: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Human-readable device model (e.g., 'iPhone 15 Pro')",
    )
    device_identifier: str = Field(
        ...,
        min_length=1,
        max_length=64,
        description="Hashed device ID (redacted UDID)",
    )
    ios_version: str = Field(
        ...,
        min_length=1,
        max_length=20,
        pattern=r"^\d+\.\d+(\.\d+)?$",
        description="iOS version string (e.g., '17.2.1')",
    )
    locale: str = Field(
        ...,
        min_length=2,
        max_length=20,
        description="Device locale (e.g., 'en_US')",
    )
    timezone: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Device timezone (e.g., 'America/New_York')",
    )
    is_simulator: bool = Field(
        ...,
        description="Whether device is simulator (should be false for real-device runs)",
    )
    available_storage_mb: int = Field(
        ...,
        ge=0,
        description="Free storage in MB",
    )
    total_memory_mb: int = Field(
        ...,
        ge=0,
        description="Total device memory in MB",
    )
    battery_level: Optional[float] = Field(
        default=None,
        ge=0.0,
        le=1.0,
        description="Battery percentage (0.0-1.0)",
    )
    battery_state: Optional[BatteryState] = Field(
        default=None,
        description="Battery charging state",
    )


class LogEntry(BaseSchema):
    """Log entry in diagnostics bundle.

    Represents a single log message captured during the QA session.

    Attributes:
        timestamp: When the log was recorded (ISO 8601)
        level: Log severity level
        tag: Module/component identifier
        message: Redacted log message (max 1000 chars)
        metadata: Additional structured data
    """

    timestamp: datetime = Field(
        ...,
        description="When the log was recorded",
    )
    level: LogLevel = Field(
        ...,
        description="Log severity level",
    )
    tag: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Module/component identifier",
    )
    message: str = Field(
        ...,
        min_length=1,
        max_length=1000,
        description="Redacted log message",
    )
    metadata: Optional[dict[str, Any]] = Field(
        default=None,
        description="Additional structured data",
    )


class NetworkErrorDigest(BaseSchema):
    """Network error entry in diagnostics bundle.

    Captures details of network failures during the QA session.

    Attributes:
        timestamp: When the error occurred (ISO 8601)
        request_url: Redacted request URL
        request_method: HTTP method (GET, POST, etc.)
        status_code: HTTP response code (null if no response)
        error_type: Error classification
        error_message: Redacted error description
        request_duration_ms: Request duration in milliseconds
        retry_count: Number of retry attempts
    """

    timestamp: datetime = Field(
        ...,
        description="When the error occurred",
    )
    request_url: str = Field(
        ...,
        min_length=1,
        max_length=2048,
        description="Redacted request URL",
    )
    request_method: str = Field(
        ...,
        min_length=1,
        max_length=10,
        description="HTTP method (GET, POST, etc.)",
    )
    status_code: Optional[int] = Field(
        default=None,
        ge=100,
        le=599,
        description="HTTP response code (null if no response)",
    )
    error_type: NetworkErrorType = Field(
        ...,
        description="Error classification",
    )
    error_message: str = Field(
        ...,
        min_length=1,
        max_length=500,
        description="Redacted error description",
    )
    request_duration_ms: int = Field(
        ...,
        ge=0,
        description="Request duration in milliseconds",
    )
    retry_count: int = Field(
        ...,
        ge=0,
        description="Number of retry attempts",
    )


class ThreadInfo(BaseSchema):
    """Thread information in crash report.

    Attributes:
        thread_id: Thread identifier
        name: Thread name
        crashed: Whether this thread crashed
        stack_frames: Stack frames for this thread
    """

    thread_id: int = Field(
        ...,
        description="Thread identifier",
    )
    name: Optional[str] = Field(
        default=None,
        max_length=100,
        description="Thread name",
    )
    crashed: bool = Field(
        default=False,
        description="Whether this thread crashed",
    )
    stack_frames: list[str] = Field(
        default_factory=list,
        max_length=50,
        description="Stack frames for this thread",
    )


class CrashReport(BaseSchema):
    """Crash report entry in diagnostics bundle.

    Captures details of app crashes during the QA session.

    Attributes:
        timestamp: When the crash occurred
        exception_type: Type of exception
        exception_message: Redacted exception message
        stack_trace: Redacted stack frames (max 50)
        thread_info: Thread information
    """

    timestamp: datetime = Field(
        ...,
        description="When the crash occurred",
    )
    exception_type: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Type of exception",
    )
    exception_message: str = Field(
        ...,
        min_length=1,
        max_length=1000,
        description="Redacted exception message",
    )
    stack_trace: list[str] = Field(
        default_factory=list,
        max_length=50,
        description="Redacted stack frames",
    )
    thread_info: Optional[list[ThreadInfo]] = Field(
        default=None,
        description="Thread information",
    )


class ScreenshotRef(BaseSchema):
    """Screenshot reference in diagnostics bundle.

    References a screenshot uploaded separately.

    Attributes:
        id: Reference ID
        timestamp: When captured
        screen_name: Current screen identifier
        file_size_bytes: Screenshot file size
    """

    id: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Reference ID",
    )
    timestamp: datetime = Field(
        ...,
        description="When captured",
    )
    screen_name: str = Field(
        ...,
        min_length=1,
        max_length=200,
        description="Current screen identifier",
    )
    file_size_bytes: int = Field(
        ...,
        ge=0,
        description="Screenshot file size",
    )


class QADiagnosticsRequest(BaseSchema):
    """Request schema for QA diagnostics ingestion.

    The main diagnostics payload sent by iOS apps during or after
    QA sessions. Contains device state, logs, network errors, and
    crash reports to enable triage of issues found by LLM testing.

    Size Limits:
    - Total payload: 5 MB
    - Log entries: 500 max
    - Network errors: 100 max
    - Crash reports: 5 max
    - Screenshot refs: 20 max
    - Custom data: 100 KB

    Attributes:
        test_run_id: UUID linking diagnostics to QA run
        app_metadata: Application metadata
        device_metadata: Device metadata
        timestamp_collected: When diagnostics were collected
        logs: Log entries (max 500)
        network_errors: Network error digests (max 100)
        crash_reports: Crash reports (max 5)
        screenshots: Screenshot references (max 20)
        custom_data: App-specific diagnostic data
    """

    test_run_id: str = Field(
        ...,
        min_length=36,
        max_length=36,
        pattern=r"^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$",
        description="UUID v4 linking this diagnostics bundle to a specific QA run",
    )
    app_metadata: AppMetadata = Field(
        ...,
        description="Application metadata",
    )
    device_metadata: DeviceMetadata = Field(
        ...,
        description="Device metadata",
    )
    timestamp_collected: datetime = Field(
        ...,
        description="When the diagnostics bundle was collected (ISO 8601)",
    )
    logs: Optional[list[LogEntry]] = Field(
        default=None,
        max_length=500,
        description="Log entries (max 500)",
    )
    network_errors: Optional[list[NetworkErrorDigest]] = Field(
        default=None,
        max_length=100,
        description="Network error digests (max 100)",
    )
    crash_reports: Optional[list[CrashReport]] = Field(
        default=None,
        max_length=5,
        description="Crash reports (max 5)",
    )
    screenshots: Optional[list[ScreenshotRef]] = Field(
        default=None,
        max_length=20,
        description="Screenshot references (max 20)",
    )
    custom_data: Optional[dict[str, Any]] = Field(
        default=None,
        description="App-specific diagnostic data (max 100KB serialized)",
    )

    @field_validator("custom_data")
    @classmethod
    def validate_custom_data_size(cls, v: Optional[dict[str, Any]]) -> Optional[dict[str, Any]]:
        """Validate custom_data does not exceed 100KB when serialized."""
        if v is None:
            return v
        import json

        serialized = json.dumps(v)
        if len(serialized) > 100 * 1024:  # 100KB limit
            raise ValueError("custom_data exceeds 100KB size limit")
        return v


class QADiagnosticsResponse(BaseSchema):
    """Response schema for successful diagnostics ingestion.

    Returned when a diagnostics bundle is successfully received
    and validated.

    Attributes:
        diagnostics_id: Unique identifier for the stored diagnostics
        test_run_id: The test run ID from the request
        received_at: When the diagnostics were received
        status: Processing status
    """

    diagnostics_id: str = Field(
        ...,
        description="Unique identifier for the stored diagnostics bundle",
    )
    test_run_id: str = Field(
        ...,
        description="The test run ID from the request",
    )
    received_at: datetime = Field(
        ...,
        description="When the diagnostics were received",
    )
    status: DiagnosticsStatus = Field(
        ...,
        description="Processing status",
    )


class QADiagnosticsErrorResponse(BaseSchema):
    """Error response schema for diagnostics ingestion failures.

    Returned when diagnostics validation or processing fails.

    Attributes:
        error: Error type or code
        message: Human-readable error message
        details: List of specific validation errors
    """

    error: str = Field(
        ...,
        max_length=100,
        description="Error type or code",
    )
    message: str = Field(
        ...,
        max_length=500,
        description="Human-readable error message",
    )
    details: Optional[list[str]] = Field(
        default=None,
        description="List of specific validation errors",
    )


class PayloadTooLargeErrorResponse(BaseSchema):
    """Error response for payload size limit exceeded.

    Returned when the diagnostics payload exceeds the 5MB limit.

    Attributes:
        error: Always 'payload_too_large'
        message: Human-readable error message
        max_size_bytes: Maximum allowed payload size
    """

    error: str = Field(
        default="payload_too_large",
        description="Error type",
    )
    message: str = Field(
        ...,
        description="Human-readable error message",
    )
    max_size_bytes: int = Field(
        default=5242880,  # 5MB
        description="Maximum allowed payload size in bytes",
    )
