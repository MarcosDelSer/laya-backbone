"""Integration tests for LLM API endpoints.

Tests cover:
- POST /api/v1/llm/completions - LLM completion generation
- POST /api/v1/llm/completions/stream - Streaming completions
- GET /api/v1/llm/health - Health check endpoint
- GET /api/v1/llm/models - List available models
- GET /api/v1/llm/usage - Usage statistics
- GET /api/v1/llm/cache/stats - Cache statistics
- POST /api/v1/llm/cache/invalidate - Cache invalidation
- POST /api/v1/llm/cache/cleanup - Cache cleanup
- Authentication requirements
- Error handling for provider failures
"""

from datetime import datetime, timezone
from typing import Any
from unittest.mock import AsyncMock, MagicMock, patch
from uuid import uuid4

import pytest
from httpx import AsyncClient

from app.schemas.llm import (
    LLMCompletionResponse,
    LLMHealthResponse,
    LLMMessageRole,
    LLMModelInfo,
    LLMModelsListResponse,
    LLMProvider,
    LLMUsageStats,
    LLMUsageSummary,
)
from app.services.llm_service import (
    CompletionError,
    LLMServiceError,
    ProviderUnavailableError,
)


# =============================================================================
# Test Fixtures
# =============================================================================


@pytest.fixture
def sample_completion_request() -> dict[str, Any]:
    """Create a sample LLM completion request."""
    return {
        "messages": [
            {"role": "system", "content": "You are a helpful assistant."},
            {"role": "user", "content": "What is 2 + 2?"},
        ],
        "provider": "openai",
        "model": "gpt-4",
        "temperature": 0.7,
        "max_tokens": 100,
        "use_cache": True,
    }


@pytest.fixture
def sample_minimal_completion_request() -> dict[str, Any]:
    """Create a minimal LLM completion request with only required fields."""
    return {
        "messages": [
            {"role": "user", "content": "Hello, world!"},
        ],
    }


@pytest.fixture
def mock_completion_response() -> LLMCompletionResponse:
    """Create a mock LLM completion response."""
    return LLMCompletionResponse(
        content="The answer is 4.",
        model="gpt-4",
        provider=LLMProvider.OPENAI,
        usage=LLMUsageStats(
            prompt_tokens=25,
            completion_tokens=5,
            total_tokens=30,
            estimated_cost=0.0012,
        ),
        finish_reason="stop",
        created_at=datetime.now(timezone.utc),
        request_id="req_123",
        cached=False,
        latency_ms=150.5,
    )


@pytest.fixture
def mock_health_response() -> LLMHealthResponse:
    """Create a mock LLM health response."""
    return LLMHealthResponse(
        status="healthy",
        providers={"openai": True, "anthropic": True},
        default_provider="openai",
        cache_available=True,
    )


@pytest.fixture
def mock_models_response() -> LLMModelsListResponse:
    """Create a mock models list response."""
    return LLMModelsListResponse(
        models=[
            LLMModelInfo(
                id="gpt-4",
                name="gpt-4",
                provider=LLMProvider.OPENAI,
                context_window=8192,
                cost_per_1k_input=0.03,
                cost_per_1k_output=0.06,
            ),
            LLMModelInfo(
                id="claude-3-sonnet-20240229",
                name="claude-3-sonnet-20240229",
                provider=LLMProvider.ANTHROPIC,
                context_window=200000,
                cost_per_1k_input=0.003,
                cost_per_1k_output=0.015,
            ),
        ],
    )


@pytest.fixture
def mock_usage_summary() -> LLMUsageSummary:
    """Create a mock usage summary response."""
    return LLMUsageSummary(
        total_requests=100,
        successful_requests=95,
        failed_requests=5,
        total_tokens=50000,
        total_prompt_tokens=40000,
        total_completion_tokens=10000,
        total_cost=1.5,
        average_latency_ms=200.0,
    )


@pytest.fixture
def mock_cache_stats() -> dict[str, Any]:
    """Create mock cache statistics."""
    return {
        "hits": 50,
        "misses": 150,
        "hit_rate": 0.25,
        "total_entries": 100,
        "expired_entries": 10,
    }


# =============================================================================
# Completions Endpoint Tests
# =============================================================================


class TestCompletionsEndpoint:
    """Tests for POST /api/v1/llm/completions endpoint."""

    @pytest.mark.asyncio
    async def test_completions_returns_200_with_valid_request(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_completion_request: dict,
        mock_completion_response: LLMCompletionResponse,
    ):
        """Test completions endpoint returns 200 with valid request."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.complete = AsyncMock(return_value=mock_completion_response)

            response = await client.post(
                "/api/v1/llm/completions",
                headers=auth_headers,
                json=sample_completion_request,
            )

            assert response.status_code == 200
            data = response.json()
            assert "content" in data
            assert "model" in data
            assert "provider" in data
            assert "usage" in data
            assert data["content"] == "The answer is 4."
            assert data["model"] == "gpt-4"

    @pytest.mark.asyncio
    async def test_completions_requires_auth(
        self,
        client: AsyncClient,
        sample_completion_request: dict,
    ):
        """Test completions endpoint requires authentication."""
        response = await client.post(
            "/api/v1/llm/completions",
            json=sample_completion_request,
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_completions_with_minimal_request(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_minimal_completion_request: dict,
        mock_completion_response: LLMCompletionResponse,
    ):
        """Test completions endpoint works with minimal request."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.complete = AsyncMock(return_value=mock_completion_response)

            response = await client.post(
                "/api/v1/llm/completions",
                headers=auth_headers,
                json=sample_minimal_completion_request,
            )

            assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_completions_validates_empty_messages(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test completions endpoint validates empty messages list."""
        response = await client.post(
            "/api/v1/llm/completions",
            headers=auth_headers,
            json={"messages": []},
        )

        assert response.status_code == 422  # Validation error

    @pytest.mark.asyncio
    async def test_completions_validates_message_role(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test completions endpoint validates message role."""
        response = await client.post(
            "/api/v1/llm/completions",
            headers=auth_headers,
            json={
                "messages": [
                    {"role": "invalid_role", "content": "Hello"},
                ],
            },
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_completions_validates_temperature_range(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test temperature must be between 0.0 and 2.0."""
        response = await client.post(
            "/api/v1/llm/completions",
            headers=auth_headers,
            json={
                "messages": [{"role": "user", "content": "Hello"}],
                "temperature": 3.0,  # Invalid: > 2.0
            },
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_completions_returns_503_when_provider_unavailable(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_completion_request: dict,
    ):
        """Test completions returns 503 when no providers available."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.complete = AsyncMock(
                side_effect=ProviderUnavailableError(
                    message="No LLM providers are currently available."
                )
            )

            response = await client.post(
                "/api/v1/llm/completions",
                headers=auth_headers,
                json=sample_completion_request,
            )

            assert response.status_code == 503

    @pytest.mark.asyncio
    async def test_completions_returns_500_on_completion_error(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_completion_request: dict,
    ):
        """Test completions returns 500 on completion failure."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.complete = AsyncMock(
                side_effect=CompletionError(message="API rate limit exceeded")
            )

            response = await client.post(
                "/api/v1/llm/completions",
                headers=auth_headers,
                json=sample_completion_request,
            )

            assert response.status_code == 500


# =============================================================================
# Streaming Completions Endpoint Tests
# =============================================================================


class TestStreamingCompletionsEndpoint:
    """Tests for POST /api/v1/llm/completions/stream endpoint."""

    @pytest.mark.asyncio
    async def test_streaming_completions_returns_stream(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_completion_request: dict,
    ):
        """Test streaming completions returns event stream."""
        async def mock_stream():
            yield "Hello"
            yield " World"

        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.complete_stream = MagicMock(return_value=mock_stream())

            response = await client.post(
                "/api/v1/llm/completions/stream",
                headers=auth_headers,
                json=sample_completion_request,
            )

            assert response.status_code == 200
            assert response.headers["content-type"].startswith("text/event-stream")

    @pytest.mark.asyncio
    async def test_streaming_completions_requires_auth(
        self,
        client: AsyncClient,
        sample_completion_request: dict,
    ):
        """Test streaming completions requires authentication."""
        response = await client.post(
            "/api/v1/llm/completions/stream",
            json=sample_completion_request,
        )

        assert response.status_code == 401


# =============================================================================
# Health Endpoint Tests
# =============================================================================


class TestHealthEndpoint:
    """Tests for GET /api/v1/llm/health endpoint."""

    @pytest.mark.asyncio
    async def test_health_returns_200_without_auth(
        self,
        client: AsyncClient,
        mock_health_response: LLMHealthResponse,
    ):
        """Test health endpoint works without authentication."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.get_health = AsyncMock(return_value=mock_health_response)

            response = await client.get("/api/v1/llm/health")

            assert response.status_code == 200
            data = response.json()
            assert "status" in data
            assert "providers" in data
            assert "default_provider" in data
            assert "cache_available" in data

    @pytest.mark.asyncio
    async def test_health_returns_healthy_status(
        self,
        client: AsyncClient,
        mock_health_response: LLMHealthResponse,
    ):
        """Test health endpoint returns correct status structure."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.get_health = AsyncMock(return_value=mock_health_response)

            response = await client.get("/api/v1/llm/health")

            assert response.status_code == 200
            data = response.json()
            assert data["status"] == "healthy"
            assert data["providers"]["openai"] is True
            assert data["providers"]["anthropic"] is True

    @pytest.mark.asyncio
    async def test_health_returns_degraded_status(
        self,
        client: AsyncClient,
    ):
        """Test health endpoint returns degraded when some providers down."""
        degraded_response = LLMHealthResponse(
            status="degraded",
            providers={"openai": True, "anthropic": False},
            default_provider="openai",
            cache_available=True,
        )

        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.get_health = AsyncMock(return_value=degraded_response)

            response = await client.get("/api/v1/llm/health")

            assert response.status_code == 200
            data = response.json()
            assert data["status"] == "degraded"


# =============================================================================
# Models Endpoint Tests
# =============================================================================


class TestModelsEndpoint:
    """Tests for GET /api/v1/llm/models endpoint."""

    @pytest.mark.asyncio
    async def test_models_returns_200(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mock_models_response: LLMModelsListResponse,
    ):
        """Test models endpoint returns 200 with valid token."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.get_models = AsyncMock(return_value=mock_models_response)

            response = await client.get(
                "/api/v1/llm/models",
                headers=auth_headers,
            )

            assert response.status_code == 200
            data = response.json()
            assert "models" in data
            assert isinstance(data["models"], list)

    @pytest.mark.asyncio
    async def test_models_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test models endpoint requires authentication."""
        response = await client.get("/api/v1/llm/models")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_models_returns_model_details(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mock_models_response: LLMModelsListResponse,
    ):
        """Test models endpoint returns model details."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.get_models = AsyncMock(return_value=mock_models_response)

            response = await client.get(
                "/api/v1/llm/models",
                headers=auth_headers,
            )

            assert response.status_code == 200
            data = response.json()
            assert len(data["models"]) == 2

            model = data["models"][0]
            assert "id" in model
            assert "name" in model
            assert "provider" in model


# =============================================================================
# Usage Endpoint Tests
# =============================================================================


class TestUsageEndpoint:
    """Tests for GET /api/v1/llm/usage endpoint."""

    @pytest.mark.asyncio
    async def test_usage_returns_200(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mock_usage_summary: LLMUsageSummary,
    ):
        """Test usage endpoint returns 200 with valid token."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.get_usage_summary = AsyncMock(
                return_value=mock_usage_summary
            )

            response = await client.get(
                "/api/v1/llm/usage",
                headers=auth_headers,
            )

            assert response.status_code == 200
            data = response.json()
            assert "total_requests" in data
            assert "successful_requests" in data
            assert "total_tokens" in data

    @pytest.mark.asyncio
    async def test_usage_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test usage endpoint requires authentication."""
        response = await client.get("/api/v1/llm/usage")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_usage_with_provider_filter(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mock_usage_summary: LLMUsageSummary,
    ):
        """Test usage endpoint with provider filter."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.get_usage_summary = AsyncMock(
                return_value=mock_usage_summary
            )

            response = await client.get(
                "/api/v1/llm/usage",
                headers=auth_headers,
                params={"provider": "openai"},
            )

            assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_usage_with_model_filter(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mock_usage_summary: LLMUsageSummary,
    ):
        """Test usage endpoint with model filter."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.get_usage_summary = AsyncMock(
                return_value=mock_usage_summary
            )

            response = await client.get(
                "/api/v1/llm/usage",
                headers=auth_headers,
                params={"model": "gpt-4"},
            )

            assert response.status_code == 200


# =============================================================================
# Cache Stats Endpoint Tests
# =============================================================================


class TestCacheStatsEndpoint:
    """Tests for GET /api/v1/llm/cache/stats endpoint."""

    @pytest.mark.asyncio
    async def test_cache_stats_returns_200(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mock_cache_stats: dict,
    ):
        """Test cache stats endpoint returns 200."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.get_cache_stats = AsyncMock(return_value=mock_cache_stats)

            response = await client.get(
                "/api/v1/llm/cache/stats",
                headers=auth_headers,
            )

            assert response.status_code == 200
            data = response.json()
            assert "hits" in data
            assert "misses" in data
            assert "hit_rate" in data

    @pytest.mark.asyncio
    async def test_cache_stats_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test cache stats endpoint requires authentication."""
        response = await client.get("/api/v1/llm/cache/stats")

        assert response.status_code == 401


# =============================================================================
# Cache Invalidate Endpoint Tests
# =============================================================================


class TestCacheInvalidateEndpoint:
    """Tests for POST /api/v1/llm/cache/invalidate endpoint."""

    @pytest.mark.asyncio
    async def test_cache_invalidate_returns_200(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test cache invalidate endpoint returns 200."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.invalidate_cache = AsyncMock(return_value=5)

            response = await client.post(
                "/api/v1/llm/cache/invalidate",
                headers=auth_headers,
            )

            assert response.status_code == 200
            data = response.json()
            assert "invalidated_count" in data
            assert data["invalidated_count"] == 5

    @pytest.mark.asyncio
    async def test_cache_invalidate_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test cache invalidate endpoint requires authentication."""
        response = await client.post("/api/v1/llm/cache/invalidate")

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_cache_invalidate_with_cache_key(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test cache invalidate with specific cache key."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.invalidate_cache = AsyncMock(return_value=1)

            response = await client.post(
                "/api/v1/llm/cache/invalidate",
                headers=auth_headers,
                params={"cache_key": "specific_cache_key"},
            )

            assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_cache_invalidate_with_provider_filter(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test cache invalidate with provider filter."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.invalidate_cache = AsyncMock(return_value=10)

            response = await client.post(
                "/api/v1/llm/cache/invalidate",
                headers=auth_headers,
                params={"provider": "openai"},
            )

            assert response.status_code == 200

    @pytest.mark.asyncio
    async def test_cache_invalidate_with_model_filter(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test cache invalidate with model filter."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.invalidate_cache = AsyncMock(return_value=3)

            response = await client.post(
                "/api/v1/llm/cache/invalidate",
                headers=auth_headers,
                params={"model": "gpt-4"},
            )

            assert response.status_code == 200


# =============================================================================
# Cache Cleanup Endpoint Tests
# =============================================================================


class TestCacheCleanupEndpoint:
    """Tests for POST /api/v1/llm/cache/cleanup endpoint."""

    @pytest.mark.asyncio
    async def test_cache_cleanup_returns_200(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test cache cleanup endpoint returns 200."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.cleanup_expired_cache = AsyncMock(return_value=15)

            response = await client.post(
                "/api/v1/llm/cache/cleanup",
                headers=auth_headers,
            )

            assert response.status_code == 200
            data = response.json()
            assert "removed_count" in data
            assert data["removed_count"] == 15

    @pytest.mark.asyncio
    async def test_cache_cleanup_requires_auth(
        self,
        client: AsyncClient,
    ):
        """Test cache cleanup endpoint requires authentication."""
        response = await client.post("/api/v1/llm/cache/cleanup")

        assert response.status_code == 401


# =============================================================================
# Edge Case and Error Handling Tests
# =============================================================================


class TestEdgeCases:
    """Tests for edge cases and error handling."""

    @pytest.mark.asyncio
    async def test_completions_with_expired_token(
        self,
        client: AsyncClient,
        expired_token: str,
        sample_completion_request: dict,
    ):
        """Test completions with expired token returns 401."""
        response = await client.post(
            "/api/v1/llm/completions",
            headers={"Authorization": f"Bearer {expired_token}"},
            json=sample_completion_request,
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_completions_with_invalid_token(
        self,
        client: AsyncClient,
        sample_completion_request: dict,
    ):
        """Test completions with invalid token returns 401."""
        response = await client.post(
            "/api/v1/llm/completions",
            headers={"Authorization": "Bearer invalid_token_123"},
            json=sample_completion_request,
        )

        assert response.status_code == 401

    @pytest.mark.asyncio
    async def test_completions_with_empty_message_content(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test completions rejects empty message content."""
        response = await client.post(
            "/api/v1/llm/completions",
            headers=auth_headers,
            json={
                "messages": [
                    {"role": "user", "content": ""},
                ],
            },
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_completions_with_invalid_max_tokens(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test completions validates max_tokens range."""
        response = await client.post(
            "/api/v1/llm/completions",
            headers=auth_headers,
            json={
                "messages": [{"role": "user", "content": "Hello"}],
                "max_tokens": 0,  # Invalid: must be >= 1
            },
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_completions_with_negative_max_tokens(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test completions rejects negative max_tokens."""
        response = await client.post(
            "/api/v1/llm/completions",
            headers=auth_headers,
            json={
                "messages": [{"role": "user", "content": "Hello"}],
                "max_tokens": -100,
            },
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_completions_with_invalid_provider(
        self,
        client: AsyncClient,
        auth_headers: dict,
    ):
        """Test completions rejects invalid provider."""
        response = await client.post(
            "/api/v1/llm/completions",
            headers=auth_headers,
            json={
                "messages": [{"role": "user", "content": "Hello"}],
                "provider": "invalid_provider",
            },
        )

        assert response.status_code == 422

    @pytest.mark.asyncio
    async def test_completions_service_error_returns_500(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_completion_request: dict,
    ):
        """Test completions returns 500 on generic service error."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.complete = AsyncMock(
                side_effect=LLMServiceError(message="Unexpected error occurred")
            )

            response = await client.post(
                "/api/v1/llm/completions",
                headers=auth_headers,
                json=sample_completion_request,
            )

            assert response.status_code == 500

    @pytest.mark.asyncio
    async def test_completions_with_all_optional_parameters(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mock_completion_response: LLMCompletionResponse,
    ):
        """Test completions with all optional parameters."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.complete = AsyncMock(return_value=mock_completion_response)

            response = await client.post(
                "/api/v1/llm/completions",
                headers=auth_headers,
                json={
                    "messages": [
                        {"role": "system", "content": "You are a helpful assistant."},
                        {"role": "user", "content": "Hello"},
                        {"role": "assistant", "content": "Hi there!"},
                        {"role": "user", "content": "How are you?"},
                    ],
                    "provider": "anthropic",
                    "model": "claude-3-sonnet-20240229",
                    "temperature": 0.5,
                    "max_tokens": 500,
                    "top_p": 0.9,
                    "frequency_penalty": 0.5,
                    "presence_penalty": 0.5,
                    "stop": ["END", "STOP"],
                    "use_cache": False,
                },
            )

            assert response.status_code == 200


# =============================================================================
# Response Structure Tests
# =============================================================================


class TestResponseStructure:
    """Tests for API response structure validation."""

    @pytest.mark.asyncio
    async def test_completion_response_contains_all_fields(
        self,
        client: AsyncClient,
        auth_headers: dict,
        sample_completion_request: dict,
        mock_completion_response: LLMCompletionResponse,
    ):
        """Test completion response contains all expected fields."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.complete = AsyncMock(return_value=mock_completion_response)

            response = await client.post(
                "/api/v1/llm/completions",
                headers=auth_headers,
                json=sample_completion_request,
            )

            assert response.status_code == 200
            data = response.json()

            # Verify required fields
            assert "content" in data
            assert "model" in data
            assert "provider" in data
            assert "usage" in data

            # Verify usage structure
            usage = data["usage"]
            assert "prompt_tokens" in usage
            assert "completion_tokens" in usage
            assert "total_tokens" in usage

    @pytest.mark.asyncio
    async def test_health_response_structure(
        self,
        client: AsyncClient,
        mock_health_response: LLMHealthResponse,
    ):
        """Test health response contains all expected fields."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.get_health = AsyncMock(return_value=mock_health_response)

            response = await client.get("/api/v1/llm/health")

            assert response.status_code == 200
            data = response.json()

            assert "status" in data
            assert "providers" in data
            assert "default_provider" in data
            assert "cache_available" in data
            assert data["status"] in ["healthy", "degraded", "unhealthy"]

    @pytest.mark.asyncio
    async def test_usage_response_structure(
        self,
        client: AsyncClient,
        auth_headers: dict,
        mock_usage_summary: LLMUsageSummary,
    ):
        """Test usage response contains all expected fields."""
        with patch(
            "app.routers.llm.LLMService"
        ) as MockService:
            mock_instance = MockService.return_value
            mock_instance.get_usage_summary = AsyncMock(
                return_value=mock_usage_summary
            )

            response = await client.get(
                "/api/v1/llm/usage",
                headers=auth_headers,
            )

            assert response.status_code == 200
            data = response.json()

            assert "total_requests" in data
            assert "successful_requests" in data
            assert "failed_requests" in data
            assert "total_tokens" in data
            assert "total_prompt_tokens" in data
            assert "total_completion_tokens" in data
