"""FastAPI application entry point for LAYA AI Service."""

from typing import Any

from fastapi import Depends, FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.dependencies import get_current_user
from app.routers import coaching
from app.routers.activities import router as activities_router
from app.routers.analytics import router as analytics_router
from app.routers.communication import router as communication_router
from app.routers.documents import router as documents_router
from app.routers.qa_diagnostics import router as qa_diagnostics_router
from app.routers.webhooks import router as webhooks_router

app = FastAPI(
    title="LAYA AI Service",
    description="AI-powered features for LAYA platform including activity recommendations, coaching guidance, and analytics",
    version="0.1.0",
)

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
app.include_router(documents_router)
app.include_router(webhooks_router, prefix="/api/v1/webhook", tags=["webhooks"])
app.include_router(qa_diagnostics_router, prefix="/api/v1/qa/diagnostics", tags=["qa-diagnostics"])


@app.get("/")
async def health_check() -> dict:
    """Health check endpoint.

    Returns:
        dict: Service status information
    """
    return {
        "status": "healthy",
        "service": "ai-service",
        "version": "0.1.0",
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
