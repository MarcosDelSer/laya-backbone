"""FastAPI application entry point for LAYA AI Service."""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

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
