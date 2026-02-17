"""FastAPI application entry point for LAYA AI Service."""

from typing import Any

from fastapi import Depends, FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.middleware.cors import CORSMiddleware
from pydantic import ValidationError
from slowapi import _rate_limit_exceeded_handler
from slowapi.errors import RateLimitExceeded

from app.dependencies import get_current_user
from app.middleware.rate_limit import get_auth_limit, limiter
from app.middleware.security import get_cors_origins, get_xss_protection_middleware
from app.middleware.validation import validation_exception_handler
from app.routers import coaching
from app.routers.activities import router as activities_router
from app.routers.analytics import router as analytics_router
from app.routers.communication import router as communication_router
from app.routers.webhooks import router as webhooks_router
from app.security import generate_csrf_token
from app.security.csrf import CSRFProtectionMiddleware

app = FastAPI(
    title="LAYA AI Service",
    description="AI-powered features for LAYA platform including activity recommendations, coaching guidance, and analytics",
    version="0.1.0",
)

# Configure rate limiting
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)

# Configure validation exception handler for strict mode
app.add_exception_handler(RequestValidationError, validation_exception_handler)
app.add_exception_handler(ValidationError, validation_exception_handler)

# Configure CSRF protection middleware
# Validates CSRF tokens on state-changing requests (POST, PUT, DELETE, PATCH)
# to prevent Cross-Site Request Forgery attacks
app.add_middleware(CSRFProtectionMiddleware)

# Configure XSS protection middleware to add security headers
# Adds Content-Security-Policy, X-Content-Type-Options, and X-Frame-Options
# to all responses for defense-in-depth protection against XSS attacks
app.middleware("http")(get_xss_protection_middleware())

# Configure CORS middleware with security lockdown for production
# Only allows whitelisted origins from environment configuration
app.add_middleware(
    CORSMiddleware,
    allow_origins=get_cors_origins(),
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS"],
    allow_headers=["Content-Type", "Authorization", "X-Requested-With", "X-CSRF-Token"],
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


@app.get("/api/v1/csrf-token")
@limiter.limit("100 per minute")
async def get_csrf_token(request: Request) -> dict:
    """Generate and return a CSRF token for form submissions.

    This endpoint generates a cryptographically secure CSRF token that clients
    must include in the X-CSRF-Token header for state-changing requests
    (POST, PUT, DELETE, PATCH).

    Tokens are valid for 60 minutes and are signed using JWT to ensure authenticity.

    Rate limited to 100 requests per minute per client.

    Args:
        request: The incoming request (required for rate limiting)

    Returns:
        dict: CSRF token and metadata
    """
    token = generate_csrf_token()
    return {
        "csrf_token": token,
        "expires_in_minutes": 60,
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
