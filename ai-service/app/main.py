"""FastAPI application entry point for LAYA AI Service."""

import os
from typing import Any

from fastapi import Depends, FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.middleware.cors import CORSMiddleware
from pydantic import ValidationError
from slowapi import _rate_limit_exceeded_handler
from slowapi.errors import RateLimitExceeded

from app.config import settings
from app.core.context import get_correlation_id, get_request_id
from app.core.logging import configure_logging, get_logger
from app.dependencies import get_current_user
from app.middleware.correlation import CorrelationMiddleware
from app.middleware.error_handler import ErrorHandlerMiddleware
from app.routers import coaching
from app.routers.activities import router as activities_router
from app.routers.analytics import router as analytics_router
from app.routers.cache import router as cache_router
from app.routers.communication import router as communication_router
from app.routers.health import router as health_router
from app.routers.webhooks import router as webhooks_router
from app.security import generate_csrf_token
from app.security.csrf import CSRFProtectionMiddleware

logger = logging.getLogger(__name__)

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
    allow_origins=get_cors_origins(),
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS"],
    allow_headers=["Content-Type", "Authorization", "X-Requested-With", "X-CSRF-Token"],
)

# Register API routers
app.include_router(health_router, prefix="/api/v1", tags=["health"])
app.include_router(coaching.router, prefix="/api/v1/coaching", tags=["coaching"])
app.include_router(rbac.router, prefix="/api/v1/rbac", tags=["rbac"])
app.include_router(activities_router)
app.include_router(analytics_router, prefix="/api/v1/analytics", tags=["analytics"])
app.include_router(cache_router, prefix="/api/v1/cache", tags=["cache"])
app.include_router(communication_router, prefix="/api/v1/communication", tags=["communication"])
app.include_router(search_router)
app.include_router(webhooks_router, prefix="/api/v1/webhook", tags=["webhooks"])


@app.on_event("startup")
async def startup_event() -> None:
    """Application startup event handler.

    Performs initialization tasks:
    - Warms frequently accessed caches
    """
    logger.info("LAYA AI Service starting up...")

    # Warm caches in the background (non-blocking)
    # This ensures the app starts quickly even if cache warming is slow
    asyncio.create_task(warm_all_caches())

    logger.info("LAYA AI Service startup complete")


@app.on_event("shutdown")
async def shutdown_event() -> None:
    """Application shutdown event handler.

    Performs cleanup tasks:
    - Closes Redis connection
    """
    logger.info("LAYA AI Service shutting down...")

    # Close Redis connection
    await close_redis()

    logger.info("LAYA AI Service shutdown complete")


@app.get("/")
@limiter.limit("100 per minute")
async def health_check(request: Request) -> dict:
    """Health check endpoint.

    Rate limited to 100 requests per minute per client.

    Args:
        request: The incoming request (required for rate limiting)

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


@app.get("/api/v1/csrf-token")
@limiter.limit("100 per minute")
async def get_csrf_token(request: Request) -> dict:
    """Generate and return a CSRF token for form submissions.

    This endpoint generates a cryptographically secure CSRF token that clients
    must include in the X-CSRF-Token header for state-changing requests
    (POST, PUT, DELETE, PATCH).

    Token expiration is configurable via CSRF_TOKEN_EXPIRE_MINUTES environment variable.
    Tokens are signed using JWT to ensure authenticity.

    Rate limited to 100 requests per minute per client.

    Args:
        request: The incoming request (required for rate limiting)

    Returns:
        dict: CSRF token and metadata
    """
    from app.config import settings

    token = generate_csrf_token()
    return {
        "csrf_token": token,
        "expires_in_minutes": settings.csrf_token_expire_minutes,
        "header_name": "X-CSRF-Token",
    }


@app.post("/api/v1/test-csrf")
@limiter.limit("100 per minute")
async def test_csrf_endpoint(request: Request, data: dict[str, Any]) -> dict:
    """Test endpoint for CSRF protection validation.

    This endpoint requires a valid CSRF token in the X-CSRF-Token header.
    Used for testing CSRF protection functionality.

    Rate limited to 100 requests per minute per client.

    Args:
        request: The incoming request (required for rate limiting)
        data: Request payload

    Returns:
        dict: Success message and received data
    """
    return {
        "message": "CSRF validation passed",
        "data": data,
    }


@app.get("/protected")
@limiter.limit(get_auth_limit())
async def protected_endpoint(
    request: Request,
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict:
    """Protected endpoint requiring JWT authentication.

    This endpoint demonstrates JWT authentication middleware.
    Requests without a valid Bearer token will receive a 401 Unauthorized response.
    Rate limited to 10 requests per minute per client for auth endpoints.

    Args:
        request: The incoming request (required for rate limiting)
        current_user: Decoded JWT payload containing user information

    Returns:
        dict: User information from the JWT token
    """
    return {
        "message": "Access granted",
        "user": current_user.get("sub"),
        "token_data": current_user,
    }
