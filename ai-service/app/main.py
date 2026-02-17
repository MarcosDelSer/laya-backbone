"""FastAPI application entry point for LAYA AI Service."""

from typing import Any

from fastapi import Depends, FastAPI, Request
from fastapi.middleware.cors import CORSMiddleware
from slowapi import _rate_limit_exceeded_handler
from slowapi.errors import RateLimitExceeded

from app.dependencies import get_current_user
from app.middleware.rate_limit import get_auth_limit, limiter
from app.middleware.security import get_cors_origins
from app.routers import coaching
from app.routers.activities import router as activities_router
from app.routers.analytics import router as analytics_router
from app.routers.communication import router as communication_router
from app.routers.webhooks import router as webhooks_router

app = FastAPI(
    title="LAYA AI Service",
    description="AI-powered features for LAYA platform including activity recommendations, coaching guidance, and analytics",
    version="0.1.0",
)

# Configure rate limiting
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)

# Configure CORS middleware with security lockdown for production
# Only allows whitelisted origins from environment configuration
app.add_middleware(
    CORSMiddleware,
    allow_origins=get_cors_origins(),
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS"],
    allow_headers=["Content-Type", "Authorization", "X-Requested-With"],
)

# Register API routers
app.include_router(coaching.router, prefix="/api/v1/coaching", tags=["coaching"])
app.include_router(activities_router)
app.include_router(analytics_router, prefix="/api/v1/analytics", tags=["analytics"])
app.include_router(communication_router, prefix="/api/v1/communication", tags=["communication"])
app.include_router(webhooks_router, prefix="/api/v1/webhook", tags=["webhooks"])


@app.get("/")
@limiter.limit("100 per minute")
async def health_check(request: Request) -> dict:
    """Health check endpoint.

    Rate limited to 100 requests per minute per client.

    Args:
        request: The incoming request (required for rate limiting)

    Returns:
        dict: Service status information
    """
    return {
        "status": "healthy",
        "service": "ai-service",
        "version": "0.1.0",
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
