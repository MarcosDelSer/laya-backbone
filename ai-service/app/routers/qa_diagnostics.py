"""FastAPI router for QA diagnostics endpoints.

Provides endpoints for ingesting iOS real-device diagnostics bundles
during LLM-based exploratory testing sessions. These diagnostics
enable actionable triage of issues discovered on physical devices.
"""

import time
from datetime import datetime, timezone
from typing import Any
from uuid import uuid4

from fastapi import APIRouter, Depends, HTTPException, Request, status

from app.dependencies import get_current_user
from app.schemas.qa_diagnostics import (
    DiagnosticsStatus,
    PayloadTooLargeErrorResponse,
    QADiagnosticsErrorResponse,
    QADiagnosticsRequest,
    QADiagnosticsResponse,
)

router = APIRouter()

# Maximum payload size: 5MB
MAX_PAYLOAD_SIZE_BYTES = 5 * 1024 * 1024


async def validate_payload_size(request: Request) -> None:
    """Validate that the request payload does not exceed size limits.

    Args:
        request: FastAPI request object

    Raises:
        HTTPException 413: When payload exceeds 5MB limit
    """
    content_length = request.headers.get("content-length")
    if content_length:
        size = int(content_length)
        if size > MAX_PAYLOAD_SIZE_BYTES:
            raise HTTPException(
                status_code=status.HTTP_413_REQUEST_ENTITY_TOO_LARGE,
                detail={
                    "error": "payload_too_large",
                    "message": "Payload exceeds 5MB limit",
                    "max_size_bytes": MAX_PAYLOAD_SIZE_BYTES,
                },
            )


@router.post(
    "",
    response_model=QADiagnosticsResponse,
    status_code=status.HTTP_201_CREATED,
    summary="Ingest QA diagnostics bundle",
    description="Endpoint for receiving iOS real-device diagnostics bundles "
    "during LLM-based QA sessions. Validates payload structure and stores "
    "diagnostics for triage.",
    responses={
        400: {
            "model": QADiagnosticsErrorResponse,
            "description": "Validation failed",
        },
        413: {
            "model": PayloadTooLargeErrorResponse,
            "description": "Payload exceeds 5MB limit",
        },
    },
)
async def ingest_diagnostics(
    request: Request,
    diagnostics: QADiagnosticsRequest,
    current_user: dict[str, Any] = Depends(get_current_user),
) -> QADiagnosticsResponse:
    """Ingest QA diagnostics bundle from iOS app.

    This endpoint receives diagnostics bundles from iOS apps during or after
    LLM-based exploratory testing sessions on physical devices. The bundles
    contain device state, logs, network errors, and crash reports to enable
    actionable triage of issues.

    Required payload fields:
    - test_run_id: UUID linking to QA run
    - app_metadata: Application version and build info
    - device_metadata: Device model, iOS version, storage, etc.
    - timestamp_collected: When diagnostics were collected

    Optional payload fields:
    - logs: Log entries (max 500)
    - network_errors: Network error digests (max 100)
    - crash_reports: Crash reports (max 5)
    - screenshots: Screenshot references (max 20)
    - custom_data: App-specific data (max 100KB)

    All PII must be redacted before upload as per DIAGNOSTICS_PAYLOAD.md.

    Args:
        request: FastAPI request object for size validation
        diagnostics: The diagnostics payload
        current_user: Authenticated user from JWT token (injected)

    Returns:
        QADiagnosticsResponse confirming receipt

    Raises:
        HTTPException 400: When validation fails
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 413: When payload exceeds 5MB limit
        HTTPException 422: When request body validation fails
    """
    received_at = datetime.now(timezone.utc)

    # Validate payload size
    await validate_payload_size(request)

    try:
        # Generate unique diagnostics ID
        diagnostics_id = f"diag_{uuid4().hex[:12]}"

        # TODO: Store diagnostics in database
        # For now, we acknowledge receipt and return success
        # Future implementation will persist to database and
        # link to QA report findings

        return QADiagnosticsResponse(
            diagnostics_id=diagnostics_id,
            test_run_id=diagnostics.test_run_id,
            received_at=received_at,
            status=DiagnosticsStatus.ACCEPTED,
        )

    except ValueError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={
                "error": "validation_failed",
                "message": str(e),
                "details": [str(e)],
            },
        )
    except Exception as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail={
                "error": "processing_error",
                "message": f"Failed to process diagnostics: {str(e)}",
                "details": [str(e)],
            },
        )


@router.get(
    "/health",
    status_code=status.HTTP_200_OK,
    summary="QA diagnostics endpoint health check",
    description="Check if the QA diagnostics endpoint is operational.",
)
async def diagnostics_health() -> dict[str, str]:
    """Health check endpoint for the QA diagnostics service.

    This endpoint does not require authentication and can be used
    by monitoring systems to verify the service is running.

    Returns:
        dict: Health status of the QA diagnostics service
    """
    return {
        "status": "healthy",
        "service": "qa-diagnostics",
        "timestamp": datetime.now(timezone.utc).isoformat(),
    }


@router.get(
    "/{diagnostics_id}",
    status_code=status.HTTP_200_OK,
    summary="Get diagnostics bundle by ID",
    description="Retrieve a diagnostics bundle by its unique identifier.",
)
async def get_diagnostics(
    diagnostics_id: str,
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict[str, Any]:
    """Retrieve a diagnostics bundle by ID.

    Args:
        diagnostics_id: The unique diagnostics bundle ID
        current_user: Authenticated user from JWT token (injected)

    Returns:
        dict: Diagnostics bundle data

    Raises:
        HTTPException 404: When diagnostics not found
    """
    # TODO: Implement database lookup
    # For now, return 404 as no persistence layer is implemented yet
    raise HTTPException(
        status_code=status.HTTP_404_NOT_FOUND,
        detail={
            "error": "not_found",
            "message": f"Diagnostics bundle {diagnostics_id} not found",
        },
    )


@router.get(
    "/run/{test_run_id}",
    status_code=status.HTTP_200_OK,
    summary="Get diagnostics by test run ID",
    description="Retrieve all diagnostics bundles for a specific test run.",
)
async def get_diagnostics_by_run(
    test_run_id: str,
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict[str, Any]:
    """Retrieve diagnostics bundles for a test run.

    Args:
        test_run_id: The QA test run UUID
        current_user: Authenticated user from JWT token (injected)

    Returns:
        dict: List of diagnostics bundles for the test run

    Raises:
        HTTPException 404: When no diagnostics found for run
    """
    # TODO: Implement database lookup
    # For now, return empty list as no persistence layer is implemented yet
    return {
        "test_run_id": test_run_id,
        "diagnostics": [],
        "count": 0,
    }
