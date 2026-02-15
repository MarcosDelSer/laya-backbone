"""API routers for LAYA AI Service.

This package contains all FastAPI router implementations that define
the API endpoints for the AI service.

Modules:
    coaching: Router for special needs coaching guidance endpoints
    activities: Router for activity intelligence endpoints
"""

from app.routers.coaching import router as coaching_router
from app.routers.activities import router as activities_router

__all__: list[str] = ["coaching_router", "activities_router"]
