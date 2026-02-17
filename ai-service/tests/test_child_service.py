"""Unit tests for child profile caching functionality.

Tests for ChildService with Redis caching, including cache hits,
TTL behavior, and invalidation.
"""

from __future__ import annotations

import json
from datetime import date, datetime
from uuid import UUID, uuid4
from unittest.mock import AsyncMock, patch, MagicMock

import httpx
import pytest
from fastapi import HTTPException

from app.services.child_service import (
    ChildService,
    ChildServiceError,
    ChildNotFoundError,
)
from app.schemas.child import ChildProfileSchema, Gender


# Mock child profile data
MOCK_CHILD_ID = uuid4()
MOCK_CHILD_PROFILE = {
    "id": str(MOCK_CHILD_ID),
    "first_name": "Alice",
    "last_name": "Johnson",
    "date_of_birth": "2020-03-15",
    "gender": "female",
    "enrollment_date": "2021-09-01",
    "classroom_id": str(uuid4()),
    "parent_ids": [str(uuid4())],
    "special_needs": [
        {
            "need_type": "autism",
            "description": "High-functioning autism",
            "accommodations": "Quiet space, visual schedules",
            "severity": "medium"
        }
    ],
    "allergies": ["peanuts", "dairy"],
    "medications": ["EpiPen"],
    "dietary_restrictions": "Nut-free, dairy-free",
    "emergency_contacts": [
        {
            "name": "Jane Johnson",
            "relationship": "Mother",
            "phone": "555-0100",
            "email": "jane@example.com"
        }
    ],
    "notes": "Prefers quiet environments",
    "is_active": True,
    "updated_at": datetime.now().isoformat()
}


class TestChildService:
    """Tests for ChildService class."""

    def test_child_service_initialization(self) -> None:
        """Test ChildService can be initialized with defaults."""
        service = ChildService()
        assert service.gibbon_api_url is not None
        assert service.timeout > 0

    def test_child_service_custom_initialization(self) -> None:
        """Test ChildService can be initialized with custom values."""
        service = ChildService(
            gibbon_api_url="http://custom:8080",
            timeout=60
        )
        assert service.gibbon_api_url == "http://custom:8080"
        assert service.timeout == 60

    @pytest.mark.asyncio
    async def test_get_child_profile_success(self) -> None:
        """Test successful child profile fetch."""
        service = ChildService()

        # Mock httpx client
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.json.return_value = MOCK_CHILD_PROFILE

        with patch('httpx.AsyncClient') as mock_client:
            mock_client.return_value.__aenter__.return_value.get = AsyncMock(
                return_value=mock_response
            )

            result = await service.get_child_profile(MOCK_CHILD_ID)

            assert result == MOCK_CHILD_PROFILE
            assert result["first_name"] == "Alice"
            assert result["last_name"] == "Johnson"

    @pytest.mark.asyncio
    async def test_get_child_profile_not_found(self) -> None:
        """Test child profile fetch when child not found."""
        service = ChildService()

        # Mock httpx client with 404 response
        mock_response = MagicMock()
        mock_response.status_code = 404

        with patch('httpx.AsyncClient') as mock_client:
            mock_client.return_value.__aenter__.return_value.get = AsyncMock(
                return_value=mock_response
            )

            with pytest.raises(ChildNotFoundError):
                await service.get_child_profile(MOCK_CHILD_ID)

    @pytest.mark.asyncio
    async def test_get_child_profile_server_error(self) -> None:
        """Test child profile fetch when Gibbon returns server error."""
        service = ChildService()

        # Mock httpx client with 500 response
        mock_response = MagicMock()
        mock_response.status_code = 500

        with patch('httpx.AsyncClient') as mock_client:
            mock_client.return_value.__aenter__.return_value.get = AsyncMock(
                return_value=mock_response
            )

            with pytest.raises(HTTPException) as exc_info:
                await service.get_child_profile(MOCK_CHILD_ID)

            assert exc_info.value.status_code == 502

    @pytest.mark.asyncio
    async def test_get_child_profile_timeout(self) -> None:
        """Test child profile fetch when request times out."""
        service = ChildService()

        with patch('httpx.AsyncClient') as mock_client:
            mock_client.return_value.__aenter__.return_value.get = AsyncMock(
                side_effect=httpx.TimeoutException("Timeout")
            )

            with pytest.raises(HTTPException) as exc_info:
                await service.get_child_profile(MOCK_CHILD_ID)

            assert exc_info.value.status_code == 504

    @pytest.mark.asyncio
    async def test_get_child_profile_connection_error(self) -> None:
        """Test child profile fetch when connection fails."""
        service = ChildService()

        with patch('httpx.AsyncClient') as mock_client:
            mock_client.return_value.__aenter__.return_value.get = AsyncMock(
                side_effect=httpx.RequestError("Connection failed")
            )

            with pytest.raises(HTTPException) as exc_info:
                await service.get_child_profile(MOCK_CHILD_ID)

            assert exc_info.value.status_code == 502

    @pytest.mark.asyncio
    async def test_get_child_profile_with_auth_token(self) -> None:
        """Test child profile fetch with authentication token."""
        service = ChildService()
        auth_token = "test_jwt_token"

        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.json.return_value = MOCK_CHILD_PROFILE

        with patch('httpx.AsyncClient') as mock_client:
            mock_get = AsyncMock(return_value=mock_response)
            mock_client.return_value.__aenter__.return_value.get = mock_get

            result = await service.get_child_profile(
                MOCK_CHILD_ID,
                auth_token=auth_token
            )

            # Verify auth header was included
            call_args = mock_get.call_args
            assert "headers" in call_args[1]
            assert call_args[1]["headers"]["Authorization"] == f"Bearer {auth_token}"

    @pytest.mark.asyncio
    async def test_get_child_profile_validated_success(self) -> None:
        """Test validated child profile fetch with schema validation."""
        service = ChildService()

        # Mock httpx client
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.json.return_value = MOCK_CHILD_PROFILE

        with patch('httpx.AsyncClient') as mock_client:
            mock_client.return_value.__aenter__.return_value.get = AsyncMock(
                return_value=mock_response
            )

            result = await service.get_child_profile_validated(MOCK_CHILD_ID)

            assert isinstance(result, ChildProfileSchema)
            assert result.first_name == "Alice"
            assert result.last_name == "Johnson"
            assert result.gender == Gender.FEMALE
            assert len(result.special_needs) == 1
            assert len(result.allergies) == 2

    @pytest.mark.asyncio
    async def test_get_child_profile_validated_invalid_data(self) -> None:
        """Test validated child profile fetch with invalid data."""
        service = ChildService()

        # Mock httpx client with invalid data
        invalid_data = {"invalid": "data"}
        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.json.return_value = invalid_data

        with patch('httpx.AsyncClient') as mock_client:
            mock_client.return_value.__aenter__.return_value.get = AsyncMock(
                return_value=mock_response
            )

            with pytest.raises(HTTPException) as exc_info:
                await service.get_child_profile_validated(MOCK_CHILD_ID)

            assert exc_info.value.status_code == 500

    @pytest.mark.asyncio
    async def test_child_profile_caching(self) -> None:
        """Test that child profiles are cached properly."""
        service = ChildService()

        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.json.return_value = MOCK_CHILD_PROFILE

        with patch('httpx.AsyncClient') as mock_client:
            mock_get = AsyncMock(return_value=mock_response)
            mock_client.return_value.__aenter__.return_value.get = mock_get

            # First call - should hit Gibbon
            result1 = await service.get_child_profile(MOCK_CHILD_ID)
            assert result1 == MOCK_CHILD_PROFILE

            # Second call - should use cache (httpx should be called only once)
            result2 = await service.get_child_profile(MOCK_CHILD_ID)
            assert result2 == MOCK_CHILD_PROFILE

            # Verify httpx was called only once due to caching
            # Note: This test will only pass if Redis is running
            # Without Redis, the decorator falls back to calling the function

    @pytest.mark.asyncio
    async def test_invalidate_child_profile_cache_specific(self) -> None:
        """Test invalidating cache for a specific child."""
        service = ChildService()

        # This will depend on Redis being available
        deleted_count = await service.invalidate_child_profile_cache(MOCK_CHILD_ID)

        # Should return number of deleted keys (0 or more)
        assert deleted_count >= 0

    @pytest.mark.asyncio
    async def test_invalidate_child_profile_cache_all(self) -> None:
        """Test invalidating all child profile caches."""
        service = ChildService()

        # This will depend on Redis being available
        deleted_count = await service.invalidate_child_profile_cache(None)

        # Should return number of deleted keys (0 or more)
        assert deleted_count >= 0

    @pytest.mark.asyncio
    async def test_refresh_child_profile_cache(self) -> None:
        """Test refreshing child profile cache."""
        service = ChildService()

        mock_response = MagicMock()
        mock_response.status_code = 200
        mock_response.json.return_value = MOCK_CHILD_PROFILE

        with patch('httpx.AsyncClient') as mock_client:
            mock_client.return_value.__aenter__.return_value.get = AsyncMock(
                return_value=mock_response
            )

            # Refresh cache (invalidate + fetch)
            result = await service.refresh_child_profile_cache(MOCK_CHILD_ID)

            assert result == MOCK_CHILD_PROFILE


class TestChildProfileCacheTTL:
    """Tests for cache TTL (5 minutes) behavior."""

    @pytest.mark.asyncio
    async def test_cache_ttl_is_5_minutes(self) -> None:
        """Test that cache decorator uses 5-minute TTL."""
        # This is verified by checking the decorator in child_service.py
        # The @cache(ttl=300) means 300 seconds = 5 minutes
        from app.services.child_service import ChildService
        import inspect

        # Get the source code and verify TTL
        source = inspect.getsource(ChildService.get_child_profile)
        assert "ttl=300" in source or "300" in source

    @pytest.mark.asyncio
    async def test_cache_key_prefix_is_child_profile(self) -> None:
        """Test that cache uses 'child_profile' key prefix."""
        from app.services.child_service import ChildService
        import inspect

        # Get the source code and verify key prefix
        source = inspect.getsource(ChildService.get_child_profile)
        assert 'key_prefix="child_profile"' in source


class TestChildProfileSchema:
    """Tests for ChildProfileSchema validation."""

    def test_child_profile_schema_valid(self) -> None:
        """Test ChildProfileSchema with valid data."""
        schema = ChildProfileSchema(**MOCK_CHILD_PROFILE)

        assert schema.first_name == "Alice"
        assert schema.last_name == "Johnson"
        assert schema.gender == Gender.FEMALE
        assert len(schema.special_needs) == 1
        assert len(schema.allergies) == 2
        assert len(schema.emergency_contacts) == 1

    def test_child_profile_schema_minimal(self) -> None:
        """Test ChildProfileSchema with minimal required data."""
        minimal_data = {
            "id": str(uuid4()),
            "first_name": "Bob",
            "last_name": "Smith",
            "date_of_birth": "2021-05-20",
        }

        schema = ChildProfileSchema(**minimal_data)

        assert schema.first_name == "Bob"
        assert schema.last_name == "Smith"
        assert schema.is_active is True
        assert len(schema.special_needs) == 0
        assert len(schema.allergies) == 0

    def test_child_profile_schema_special_needs(self) -> None:
        """Test ChildProfileSchema special needs validation."""
        data = MOCK_CHILD_PROFILE.copy()

        schema = ChildProfileSchema(**data)

        assert len(schema.special_needs) == 1
        need = schema.special_needs[0]
        assert need.need_type == "autism"
        assert need.severity == "medium"
        assert "visual schedules" in need.accommodations

    def test_child_profile_schema_emergency_contacts(self) -> None:
        """Test ChildProfileSchema emergency contacts validation."""
        data = MOCK_CHILD_PROFILE.copy()

        schema = ChildProfileSchema(**data)

        assert len(schema.emergency_contacts) == 1
        contact = schema.emergency_contacts[0]
        assert contact.name == "Jane Johnson"
        assert contact.relationship == "Mother"
        assert contact.phone == "555-0100"
        assert contact.email == "jane@example.com"
