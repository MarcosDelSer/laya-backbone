"""FastAPI router for webhook endpoints.

Provides endpoints for receiving webhook events from Gibbon via the
AISync module. These webhooks enable data synchronization between
Gibbon (PHP backend) and the AI service for care activities, meals,
naps, photos, and attendance records.
"""

import time
from datetime import datetime, timezone
from typing import Any

from fastapi import APIRouter, Depends, HTTPException, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.core.cache import invalidate_cache
from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.webhook import (
    WebhookEventType,
    WebhookRequest,
    WebhookResponse,
    WebhookStatus,
)

router = APIRouter()


async def process_care_activity_event(
    event_type: WebhookEventType,
    entity_id: str,
    payload: dict[str, Any],
    db: AsyncSession,
) -> str:
    """Process care activity webhook events.

    When activities are created, updated, or deleted, we need to invalidate
    the activity catalog cache to ensure the catalog reflects current data.

    Args:
        event_type: The type of care activity event
        entity_id: The ID of the care activity
        payload: Event payload with activity details
        db: Async database session

    Returns:
        Status message describing the processing result
    """
    # Invalidate activity catalog cache on any activity change
    await invalidate_cache("activity_catalog")

    if event_type == WebhookEventType.CARE_ACTIVITY_CREATED:
        return f"Care activity {entity_id} creation acknowledged and cache invalidated"
    elif event_type == WebhookEventType.CARE_ACTIVITY_UPDATED:
        return f"Care activity {entity_id} update acknowledged and cache invalidated"
    elif event_type == WebhookEventType.CARE_ACTIVITY_DELETED:
        return f"Care activity {entity_id} deletion acknowledged and cache invalidated"
    return f"Care activity event {event_type} for {entity_id} acknowledged and cache invalidated"


async def process_meal_event(
    entity_id: str,
    payload: dict[str, Any],
    db: AsyncSession,
) -> str:
    """Process meal logging webhook events.

    Args:
        entity_id: The ID of the meal record
        payload: Event payload with meal details
        db: Async database session

    Returns:
        Status message describing the processing result
    """
    child_id = payload.get("child_id", "unknown")
    meal_type = payload.get("meal_type", "unknown")
    return f"Meal ({meal_type}) for child {child_id} logged with ID {entity_id}"


async def process_nap_event(
    entity_id: str,
    payload: dict[str, Any],
    db: AsyncSession,
) -> str:
    """Process nap logging webhook events.

    Args:
        entity_id: The ID of the nap record
        payload: Event payload with nap details
        db: Async database session

    Returns:
        Status message describing the processing result
    """
    child_id = payload.get("child_id", "unknown")
    duration = payload.get("duration_minutes", "unknown")
    return f"Nap ({duration} minutes) for child {child_id} logged with ID {entity_id}"


async def process_photo_event(
    entity_id: str,
    payload: dict[str, Any],
    db: AsyncSession,
) -> str:
    """Process photo upload webhook events.

    Args:
        entity_id: The ID of the photo record
        payload: Event payload with photo details
        db: Async database session

    Returns:
        Status message describing the processing result
    """
    child_id = payload.get("child_id", "unknown")
    return f"Photo for child {child_id} uploaded with ID {entity_id}"


async def process_attendance_event(
    event_type: WebhookEventType,
    entity_id: str,
    payload: dict[str, Any],
    db: AsyncSession,
) -> str:
    """Process attendance webhook events.

    When attendance events occur (check-in/check-out), we need to invalidate
    the analytics dashboard cache since attendance affects KPI calculations.

    Args:
        event_type: The type of attendance event (check-in or check-out)
        entity_id: The ID of the attendance record
        payload: Event payload with attendance details
        db: Async database session

    Returns:
        Status message describing the processing result
    """
    child_id = payload.get("child_id", "unknown")
    action = "checked in" if event_type == WebhookEventType.ATTENDANCE_CHECKED_IN else "checked out"

    # Invalidate analytics dashboard cache since attendance affects metrics
    await invalidate_cache("analytics_dashboard")

    return f"Child {child_id} {action}, attendance record ID {entity_id}, cache invalidated"


async def process_child_profile_event(
    entity_id: str,
    payload: dict[str, Any],
    db: AsyncSession,
) -> str:
    """Process child profile update webhook events.

    When a child profile is updated in Gibbon, we need to invalidate
    the cached child profile data to ensure fresh data is fetched.

    Args:
        entity_id: The ID of the child
        payload: Event payload with profile details
        db: Async database session

    Returns:
        Status message describing the processing result
    """
    # Invalidate child profile cache for this specific child
    await invalidate_cache("child_profile", f"*{entity_id}*")

    return f"Child profile {entity_id} update acknowledged and cache invalidated"


@router.post(
    "",
    response_model=WebhookResponse,
    status_code=status.HTTP_200_OK,
    summary="Receive webhook event from Gibbon",
    description="Endpoint for receiving and processing webhook events from Gibbon's "
    "AISync module. Supports care activities, meals, naps, photos, and attendance events.",
)
async def receive_webhook(
    request: WebhookRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> WebhookResponse:
    """Receive and process a webhook event from Gibbon.

    This endpoint receives webhook events from Gibbon's AISync module
    and processes them accordingly. All events are authenticated via JWT
    to ensure only authorized systems can send webhooks.

    Supported event types:
    - care_activity_created/updated/deleted: Care activity events
    - meal_logged: Meal logging events
    - nap_logged: Nap session events
    - photo_uploaded: Photo upload events
    - attendance_checked_in/out: Attendance events
    - child_profile_updated: Profile update events
    - custom: Custom events for extensibility

    Args:
        request: The webhook request containing event details
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        WebhookResponse confirming receipt and processing status

    Raises:
        HTTPException 400: When event processing fails
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 422: When request validation fails
    """
    start_time = time.time()
    received_at = datetime.now(timezone.utc)

    try:
        # Process based on event type
        event_type = request.event_type
        entity_type = request.entity_type
        entity_id = request.entity_id
        payload = request.payload

        # Route to appropriate handler based on event type
        if event_type in (
            WebhookEventType.CARE_ACTIVITY_CREATED,
            WebhookEventType.CARE_ACTIVITY_UPDATED,
            WebhookEventType.CARE_ACTIVITY_DELETED,
        ):
            message = await process_care_activity_event(
                event_type, entity_id, payload, db
            )
        elif event_type == WebhookEventType.MEAL_LOGGED:
            message = await process_meal_event(entity_id, payload, db)
        elif event_type == WebhookEventType.NAP_LOGGED:
            message = await process_nap_event(entity_id, payload, db)
        elif event_type == WebhookEventType.PHOTO_UPLOADED:
            message = await process_photo_event(entity_id, payload, db)
        elif event_type in (
            WebhookEventType.ATTENDANCE_CHECKED_IN,
            WebhookEventType.ATTENDANCE_CHECKED_OUT,
        ):
            message = await process_attendance_event(
                event_type, entity_id, payload, db
            )
        elif event_type == WebhookEventType.CHILD_PROFILE_UPDATED:
            message = await process_child_profile_event(entity_id, payload, db)
        elif event_type == WebhookEventType.CUSTOM:
            # Handle custom events - just acknowledge receipt
            custom_type = payload.get("custom_event_type", "unknown")
            message = f"Custom event ({custom_type}) received for entity {entity_id}"
        else:
            message = f"Event {event_type} for entity {entity_id} received"

        # Calculate processing time
        processing_time_ms = (time.time() - start_time) * 1000

        return WebhookResponse(
            status=WebhookStatus.PROCESSED,
            message=message,
            event_type=event_type,
            entity_id=entity_id,
            received_at=received_at,
            processing_time_ms=round(processing_time_ms, 2),
        )

    except Exception as e:
        # Calculate processing time even for failures
        processing_time_ms = (time.time() - start_time) * 1000

        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={
                "status": "failed",
                "error": "processing_error",
                "message": f"Failed to process webhook: {str(e)}",
                "event_type": request.event_type.value if request.event_type else None,
                "entity_id": request.entity_id,
                "received_at": received_at.isoformat(),
                "processing_time_ms": round(processing_time_ms, 2),
            },
        )


@router.get(
    "/health",
    status_code=status.HTTP_200_OK,
    summary="Webhook endpoint health check",
    description="Check if the webhook endpoint is operational.",
)
async def webhook_health() -> dict[str, str]:
    """Health check endpoint for the webhook service.

    This endpoint does not require authentication and can be used
    by monitoring systems to verify the webhook service is running.

    Returns:
        dict: Health status of the webhook service
    """
    return {
        "status": "healthy",
        "service": "webhook",
        "timestamp": datetime.now(timezone.utc).isoformat(),
    }
