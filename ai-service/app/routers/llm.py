"""FastAPI router for LLM service endpoints.

Provides endpoints for LLM completions, health checks, model listing,
and usage statistics. Supports multiple LLM providers (OpenAI, Anthropic)
with automatic fallback and caching.
"""

from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, status
from fastapi.responses import StreamingResponse
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.llm import (
    LLMCompletionRequest,
    LLMCompletionResponse,
    LLMHealthResponse,
    LLMModelsListResponse,
    LLMUsageSummary,
)
from app.services.llm_service import (
    CompletionError,
    LLMService,
    LLMServiceError,
    ProviderUnavailableError,
)

router = APIRouter()


@router.post("/completions", response_model=LLMCompletionResponse)
async def create_completion(
    request: LLMCompletionRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> LLMCompletionResponse:
    """Generate an LLM completion.

    Creates a completion using the specified or default LLM provider.
    Supports caching for improved performance and cost reduction.

    Args:
        request: The completion request containing messages and parameters
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        LLMCompletionResponse containing:
        - content: The generated text
        - model: The model used
        - provider: The provider used
        - usage: Token usage statistics
        - latency_ms: Response time in milliseconds

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 503: When no LLM providers are available
        HTTPException 500: When completion fails
    """
    service = LLMService(db)

    # Extract user_id from JWT claims if available
    user_id: Optional[UUID] = None
    user_sub = current_user.get("sub")
    if user_sub:
        try:
            user_id = UUID(user_sub)
        except (ValueError, TypeError):
            pass

    try:
        return await service.complete(request, user_id=user_id)
    except ProviderUnavailableError as e:
        raise HTTPException(
            status_code=status.HTTP_503_SERVICE_UNAVAILABLE,
            detail=str(e.message),
        )
    except CompletionError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=str(e.message),
        )
    except LLMServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"LLM service error: {str(e)}",
        )


@router.post("/completions/stream")
async def create_streaming_completion(
    request: LLMCompletionRequest,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> StreamingResponse:
    """Generate a streaming LLM completion.

    Creates a streaming completion that returns content chunks as they
    are generated. Useful for real-time display in chat interfaces.

    Args:
        request: The completion request containing messages and parameters
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        StreamingResponse with text/event-stream content type

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 503: When no LLM providers are available
    """
    service = LLMService(db)

    async def generate():
        try:
            async for chunk in service.complete_stream(request):
                yield f"data: {chunk}\n\n"
            yield "data: [DONE]\n\n"
        except ProviderUnavailableError as e:
            yield f"data: [ERROR] {e.message}\n\n"

    return StreamingResponse(
        generate(),
        media_type="text/event-stream",
    )


@router.get("/health", response_model=LLMHealthResponse)
async def get_llm_health(
    db: AsyncSession = Depends(get_db),
) -> LLMHealthResponse:
    """Get LLM service health status.

    Returns health information about the LLM service including
    provider availability and cache status. This endpoint does not
    require authentication for monitoring purposes.

    Args:
        db: Async database session (injected)

    Returns:
        LLMHealthResponse containing:
        - status: Overall health (healthy, degraded, unhealthy)
        - providers: Health status of each provider
        - default_provider: The default provider configured
        - cache_available: Whether caching is enabled
    """
    service = LLMService(db)
    return await service.get_health()


@router.get("/models", response_model=LLMModelsListResponse)
async def list_models(
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> LLMModelsListResponse:
    """List available LLM models.

    Returns information about all available models from configured
    providers, including pricing and context window information.

    Args:
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        LLMModelsListResponse containing list of model information

    Raises:
        HTTPException 401: When JWT token is missing or invalid
    """
    service = LLMService(db)
    return await service.get_models()


@router.get("/usage", response_model=LLMUsageSummary)
async def get_usage_summary(
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
    provider: Optional[str] = Query(
        default=None,
        description="Filter by provider name (openai, anthropic)",
    ),
    model: Optional[str] = Query(
        default=None,
        description="Filter by model name",
    ),
) -> LLMUsageSummary:
    """Get LLM usage statistics for the current user.

    Returns aggregated usage statistics including token counts,
    costs, and request counts for the authenticated user.

    Args:
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)
        provider: Optional filter by provider name
        model: Optional filter by model name

    Returns:
        LLMUsageSummary with aggregated statistics

    Raises:
        HTTPException 401: When JWT token is missing or invalid
    """
    service = LLMService(db)

    # Extract user_id from JWT claims
    user_id: Optional[UUID] = None
    user_sub = current_user.get("sub")
    if user_sub:
        try:
            user_id = UUID(user_sub)
        except (ValueError, TypeError):
            pass

    return await service.get_usage_summary(
        user_id=user_id,
        provider=provider,
        model=model,
    )


@router.get("/cache/stats")
async def get_cache_stats(
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict[str, Any]:
    """Get LLM response cache statistics.

    Returns cache hit rates and other caching metrics for
    monitoring and optimization purposes.

    Args:
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Dictionary with cache statistics

    Raises:
        HTTPException 401: When JWT token is missing or invalid
    """
    service = LLMService(db)
    return await service.get_cache_stats()


@router.post("/cache/invalidate")
async def invalidate_cache(
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
    cache_key: Optional[str] = Query(
        default=None,
        description="Specific cache key to invalidate",
    ),
    provider: Optional[str] = Query(
        default=None,
        description="Invalidate all entries for this provider",
    ),
    model: Optional[str] = Query(
        default=None,
        description="Invalidate all entries for this model",
    ),
) -> dict[str, Any]:
    """Invalidate LLM response cache entries.

    Removes cached responses matching the specified criteria.
    Useful for forcing fresh responses or clearing stale data.

    Args:
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)
        cache_key: Optional specific cache key
        provider: Optional provider filter
        model: Optional model filter

    Returns:
        Dictionary with count of invalidated entries

    Raises:
        HTTPException 401: When JWT token is missing or invalid
    """
    service = LLMService(db)
    count = await service.invalidate_cache(
        cache_key=cache_key,
        provider=provider,
        model=model,
    )
    return {"invalidated_count": count}


@router.post("/cache/cleanup")
async def cleanup_expired_cache(
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> dict[str, Any]:
    """Remove expired cache entries.

    Cleans up expired cache entries from the database. This
    operation should be run periodically for maintenance.

    Args:
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        Dictionary with count of removed entries

    Raises:
        HTTPException 401: When JWT token is missing or invalid
    """
    service = LLMService(db)
    count = await service.cleanup_expired_cache()
    return {"removed_count": count}
