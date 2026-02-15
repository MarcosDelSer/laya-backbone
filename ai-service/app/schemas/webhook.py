"""Webhook schemas for LAYA AI Service.

Defines Pydantic schemas for webhook payloads received from Gibbon
via the AISync module. These webhooks enable bidirectional data
synchronization between Gibbon (PHP backend) and the AI service.
"""

from datetime import datetime
from enum import Enum
from typing import Any, Optional

from pydantic import Field

from app.schemas.base import BaseSchema


class WebhookEventType(str, Enum):
    """Types of webhook events that can be received from Gibbon.

    Attributes:
        CARE_ACTIVITY_CREATED: A new care activity was recorded
        CARE_ACTIVITY_UPDATED: An existing care activity was updated
        CARE_ACTIVITY_DELETED: A care activity was deleted
        MEAL_LOGGED: A meal was logged for a child
        NAP_LOGGED: A nap session was logged for a child
        PHOTO_UPLOADED: A photo was uploaded
        ATTENDANCE_CHECKED_IN: A child was checked in
        ATTENDANCE_CHECKED_OUT: A child was checked out
        CHILD_PROFILE_UPDATED: A child's profile was updated
        CUSTOM: A custom event type for extensibility
    """

    CARE_ACTIVITY_CREATED = "care_activity_created"
    CARE_ACTIVITY_UPDATED = "care_activity_updated"
    CARE_ACTIVITY_DELETED = "care_activity_deleted"
    MEAL_LOGGED = "meal_logged"
    NAP_LOGGED = "nap_logged"
    PHOTO_UPLOADED = "photo_uploaded"
    ATTENDANCE_CHECKED_IN = "attendance_checked_in"
    ATTENDANCE_CHECKED_OUT = "attendance_checked_out"
    CHILD_PROFILE_UPDATED = "child_profile_updated"
    CUSTOM = "custom"


class WebhookEntityType(str, Enum):
    """Types of entities that webhook events can reference.

    Attributes:
        ACTIVITY: A care activity entity
        MEAL: A meal log entity
        NAP: A nap session entity
        PHOTO: A photo entity
        ATTENDANCE: An attendance record entity
        CHILD: A child profile entity
        CUSTOM: A custom entity type for extensibility
    """

    ACTIVITY = "activity"
    MEAL = "meal"
    NAP = "nap"
    PHOTO = "photo"
    ATTENDANCE = "attendance"
    CHILD = "child"
    CUSTOM = "custom"


class WebhookStatus(str, Enum):
    """Processing status of a webhook event.

    Attributes:
        RECEIVED: Webhook was received and acknowledged
        PROCESSING: Webhook is being processed
        PROCESSED: Webhook was successfully processed
        FAILED: Webhook processing failed
    """

    RECEIVED = "received"
    PROCESSING = "processing"
    PROCESSED = "processed"
    FAILED = "failed"


class WebhookRequest(BaseSchema):
    """Request schema for incoming webhook events from Gibbon.

    Contains the event type, entity information, and payload data
    for synchronization between Gibbon and the AI service.

    Attributes:
        event_type: Type of event that triggered the webhook
        entity_type: Type of entity the event relates to
        entity_id: Unique identifier of the entity in Gibbon
        payload: Event-specific data payload
        timestamp: When the event occurred in Gibbon
        gibbon_sync_log_id: Optional ID of the sync log entry in Gibbon
    """

    event_type: WebhookEventType = Field(
        ...,
        description="Type of event that triggered the webhook",
    )
    entity_type: WebhookEntityType = Field(
        ...,
        description="Type of entity the event relates to",
    )
    entity_id: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Unique identifier of the entity in Gibbon",
    )
    payload: dict[str, Any] = Field(
        default_factory=dict,
        description="Event-specific data payload containing entity details",
    )
    timestamp: Optional[datetime] = Field(
        default=None,
        description="When the event occurred in Gibbon (defaults to current time)",
    )
    gibbon_sync_log_id: Optional[int] = Field(
        default=None,
        ge=1,
        description="ID of the sync log entry in Gibbon for status updates",
    )


class WebhookResponse(BaseSchema):
    """Response schema for processed webhook events.

    Returned to Gibbon after the webhook is received and processed.

    Attributes:
        status: Processing status of the webhook
        message: Human-readable status message
        event_type: The event type that was processed
        entity_id: The entity ID that was processed
        received_at: When the webhook was received
        processing_time_ms: Time taken to process the webhook in milliseconds
    """

    status: WebhookStatus = Field(
        ...,
        description="Processing status of the webhook",
    )
    message: str = Field(
        ...,
        max_length=500,
        description="Human-readable status message",
    )
    event_type: WebhookEventType = Field(
        ...,
        description="The event type that was processed",
    )
    entity_id: str = Field(
        ...,
        description="The entity ID that was processed",
    )
    received_at: datetime = Field(
        ...,
        description="When the webhook was received",
    )
    processing_time_ms: Optional[float] = Field(
        default=None,
        ge=0,
        description="Time taken to process the webhook in milliseconds",
    )


class WebhookErrorResponse(BaseSchema):
    """Error response schema for failed webhook processing.

    Returned when webhook processing encounters an error.

    Attributes:
        status: Always 'failed' for error responses
        error: Error type or code
        message: Human-readable error message
        event_type: The event type that failed (if parseable)
        entity_id: The entity ID that failed (if parseable)
        received_at: When the webhook was received
    """

    status: WebhookStatus = Field(
        default=WebhookStatus.FAILED,
        description="Processing status (always 'failed' for errors)",
    )
    error: str = Field(
        ...,
        max_length=100,
        description="Error type or code",
    )
    message: str = Field(
        ...,
        max_length=1000,
        description="Human-readable error message",
    )
    event_type: Optional[WebhookEventType] = Field(
        default=None,
        description="The event type that failed (if parseable)",
    )
    entity_id: Optional[str] = Field(
        default=None,
        description="The entity ID that failed (if parseable)",
    )
    received_at: datetime = Field(
        ...,
        description="When the webhook was received",
    )
