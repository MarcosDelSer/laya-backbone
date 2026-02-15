"""API routers for LAYA AI Service.

This package contains all FastAPI router implementations that define
the API endpoints for the AI service.

Modules:
    coaching: Router for special needs coaching guidance endpoints
    analytics: Router for business intelligence and analytics endpoints
"""

from app.routers.coaching import router as coaching_router
from app.routers.analytics import router as analytics_router

__all__: list[str] = ["coaching_router", "analytics_router"]
