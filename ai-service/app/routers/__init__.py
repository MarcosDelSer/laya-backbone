"""FastAPI routers for LAYA AI Service.

Provides organized API endpoints grouped by domain functionality.
"""

from app.routers.activities import router as activities_router

__all__ = ["activities_router"]
