"""Service for fetching and caching child profile data.

Provides methods to fetch child profile data from Gibbon with
Redis caching to improve performance.
"""

from typing import Optional
from uuid import UUID
import json

import httpx
from fastapi import HTTPException, status

from app.config import settings
from app.core.cache import cache, invalidate_cache
from app.schemas.child import ChildProfileSchema


class ChildServiceError(Exception):
    """Base exception for child service errors."""

    pass


class ChildNotFoundError(ChildServiceError):
    """Raised when a child profile is not found."""

    pass


class ChildService:
    """Service for fetching and managing child profile data.

    This service fetches child profile data from Gibbon and caches it
    in Redis with a 5-minute TTL for improved performance.

    Attributes:
        gibbon_api_url: Base URL for Gibbon API
        timeout: Request timeout in seconds
    """

    def __init__(
        self,
        gibbon_api_url: Optional[str] = None,
        timeout: Optional[int] = None
    ) -> None:
        """Initialize the child service.

        Args:
            gibbon_api_url: Base URL for Gibbon API (uses config default if None)
            timeout: Request timeout in seconds (uses config default if None)
        """
        self.gibbon_api_url = gibbon_api_url or settings.gibbon_api_url
        self.timeout = timeout or settings.gibbon_api_timeout

    @cache(ttl=300, key_prefix="child_profile")
    async def get_child_profile(
        self,
        child_id: UUID,
        auth_token: Optional[str] = None
    ) -> dict:
        """Fetch child profile from Gibbon with caching.

        This method fetches child profile data from Gibbon and caches
        it in Redis with a 5-minute (300 seconds) TTL. Subsequent requests
        for the same child within 5 minutes will be served from cache.

        Args:
            child_id: Unique identifier of the child
            auth_token: Optional JWT token for authentication

        Returns:
            dict: Child profile data as dictionary

        Raises:
            ChildNotFoundError: If child profile is not found
            HTTPException: If Gibbon API request fails
        """
        url = f"{self.gibbon_api_url}/api/v1/children/{child_id}"

        headers = {}
        if auth_token:
            headers["Authorization"] = f"Bearer {auth_token}"

        try:
            async with httpx.AsyncClient(timeout=self.timeout) as client:
                response = await client.get(url, headers=headers)

                if response.status_code == 404:
                    raise ChildNotFoundError(
                        f"Child profile not found for ID: {child_id}"
                    )

                if response.status_code != 200:
                    raise HTTPException(
                        status_code=status.HTTP_502_BAD_GATEWAY,
                        detail=f"Failed to fetch child profile from Gibbon: "
                               f"{response.status_code}"
                    )

                return response.json()

        except httpx.TimeoutException:
            raise HTTPException(
                status_code=status.HTTP_504_GATEWAY_TIMEOUT,
                detail="Request to Gibbon timed out"
            )
        except httpx.RequestError as e:
            raise HTTPException(
                status_code=status.HTTP_502_BAD_GATEWAY,
                detail=f"Failed to connect to Gibbon: {str(e)}"
            )

    async def get_child_profile_validated(
        self,
        child_id: UUID,
        auth_token: Optional[str] = None
    ) -> ChildProfileSchema:
        """Fetch and validate child profile from Gibbon.

        This method fetches child profile data and validates it against
        the ChildProfileSchema.

        Args:
            child_id: Unique identifier of the child
            auth_token: Optional JWT token for authentication

        Returns:
            ChildProfileSchema: Validated child profile data

        Raises:
            ChildNotFoundError: If child profile is not found
            HTTPException: If Gibbon API request fails or validation fails
        """
        profile_data = await self.get_child_profile(child_id, auth_token)

        try:
            return ChildProfileSchema(**profile_data)
        except Exception as e:
            raise HTTPException(
                status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
                detail=f"Failed to validate child profile data: {str(e)}"
            )

    async def invalidate_child_profile_cache(
        self,
        child_id: Optional[UUID] = None
    ) -> int:
        """Invalidate cached child profile(s).

        Args:
            child_id: Specific child ID to invalidate, or None to invalidate all

        Returns:
            int: Number of cache entries deleted
        """
        if child_id:
            # Invalidate specific child profile cache
            pattern = f"*{child_id}*"
        else:
            # Invalidate all child profile caches
            pattern = "*"

        return await invalidate_cache("child_profile", pattern)

    async def refresh_child_profile_cache(
        self,
        child_id: UUID,
        auth_token: Optional[str] = None
    ) -> dict:
        """Refresh child profile cache by invalidating and refetching.

        Args:
            child_id: Unique identifier of the child
            auth_token: Optional JWT token for authentication

        Returns:
            dict: Fresh child profile data

        Raises:
            ChildNotFoundError: If child profile is not found
            HTTPException: If Gibbon API request fails
        """
        # Invalidate existing cache
        await self.invalidate_child_profile_cache(child_id)

        # Fetch fresh data (which will be cached)
        return await self.get_child_profile(child_id, auth_token)
