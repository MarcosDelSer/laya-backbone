"""FastAPI application entry point for LAYA AI Service."""

import os
from typing import Any

from fastapi import Depends, FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.config import settings
from app.core.context import get_correlation_id, get_request_id
from app.core.logging import configure_logging, get_logger
from app.dependencies import get_current_user
from app.middleware.correlation import CorrelationMiddleware
from app.middleware.error_handler import ErrorHandlerMiddleware
from app.routers import coaching
from app.routers.activities import router as activities_router
from app.routers.analytics import router as analytics_router
from app.routers.communication import router as communication_router
from app.routers.webhooks import router as webhooks_router

# Configure logging on application startup
# Use JSON logs in production, human-readable in development
log_level = os.getenv("LOG_LEVEL", settings.log_level)
json_logs = os.getenv("JSON_LOGS", str(settings.json_logs)).lower() == "true"
log_file = os.getenv("LOG_FILE", settings.log_file)

# Configure with log rotation support
configure_logging(
    log_level=log_level,
    json_logs=json_logs,
    log_file=log_file,
    rotation_enabled=settings.log_rotation_enabled,
    rotation_type=settings.log_rotation_type,
    max_bytes=settings.log_max_bytes,
    backup_count=settings.log_backup_count,
    when=settings.log_rotation_when,
    interval=settings.log_rotation_interval,
)

logger = get_logger(__name__, service="ai-service")

app = FastAPI(
    title="LAYA AI Service",
    description="AI-powered features for LAYA platform including activity recommendations, coaching guidance, and analytics",
    version="0.1.0",
)

logger.info("Starting LAYA AI Service", version="0.1.0", log_level=log_level)

# Configure middleware (order matters - first added is last executed)
# 1. Correlation middleware sets request/correlation IDs in context
app.add_middleware(CorrelationMiddleware)

# 2. Error handler middleware uses IDs from context for error responses
app.add_middleware(ErrorHandlerMiddleware)

# Configure CORS middleware for frontend integration
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Register API routers
app.include_router(coaching.router, prefix="/api/v1/coaching", tags=["coaching"])
app.include_router(activities_router)
app.include_router(analytics_router, prefix="/api/v1/analytics", tags=["analytics"])
app.include_router(communication_router, prefix="/api/v1/communication", tags=["communication"])
app.include_router(webhooks_router, prefix="/api/v1/webhook", tags=["webhooks"])


@app.get("/")
async def health_check() -> dict:
    """Health check endpoint.

    Returns:
        dict: Service status information with request tracking IDs
    """
    return {
        "status": "healthy",
        "service": "ai-service",
        "version": "0.1.0",
        "request_id": get_request_id(),
        "correlation_id": get_correlation_id(),
    }


@app.get("/protected")
async def protected_endpoint(
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict:
    """Protected endpoint requiring JWT authentication.

    This endpoint demonstrates JWT authentication middleware.
    Requests without a valid Bearer token will receive a 401 Unauthorized response.

    Args:
        current_user: Decoded JWT payload containing user information

    Returns:
        dict: User information from the JWT token
    """
    return {
        "message": "Access granted",
        "user": current_user.get("sub"),
        "token_data": current_user,
    }
