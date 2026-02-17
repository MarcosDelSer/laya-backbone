"""Example usage of child profile caching.

This example demonstrates how to use the ChildService to fetch
and cache child profiles with a 5-minute TTL.
"""

from uuid import UUID
from app.services.child_service import ChildService


async def example_get_child_profile():
    """Example: Fetch child profile (will be cached for 5 minutes)."""
    service = ChildService()
    child_id = UUID("12345678-1234-5678-1234-567812345678")

    # First call - fetches from Gibbon and caches
    profile = await service.get_child_profile(child_id)
    print(f"Fetched profile for {profile['first_name']} {profile['last_name']}")

    # Second call within 5 minutes - served from cache
    profile_cached = await service.get_child_profile(child_id)
    print("Second call served from cache!")

    return profile


async def example_get_validated_profile():
    """Example: Fetch and validate child profile."""
    service = ChildService()
    child_id = UUID("12345678-1234-5678-1234-567812345678")

    # Returns validated ChildProfileSchema
    profile = await service.get_child_profile_validated(child_id)

    print(f"Child: {profile.first_name} {profile.last_name}")
    print(f"Age: {profile.date_of_birth}")
    print(f"Special needs: {len(profile.special_needs)}")
    print(f"Allergies: {', '.join(profile.allergies)}")

    return profile


async def example_invalidate_cache():
    """Example: Invalidate cache when child profile is updated."""
    service = ChildService()
    child_id = UUID("12345678-1234-5678-1234-567812345678")

    # When child profile is updated via webhook, invalidate cache
    deleted = await service.invalidate_child_profile_cache(child_id)
    print(f"Invalidated {deleted} cache entries")


async def example_refresh_cache():
    """Example: Force refresh of cached data."""
    service = ChildService()
    child_id = UUID("12345678-1234-5678-1234-567812345678")
    auth_token = "your_jwt_token"

    # Force refresh (invalidate + fetch)
    profile = await service.refresh_child_profile_cache(
        child_id,
        auth_token=auth_token
    )
    print("Cache refreshed with latest data")

    return profile


# Cache details:
# - TTL: 5 minutes (300 seconds)
# - Key prefix: "child_profile"
# - Automatic cache key generation based on child_id
# - Graceful fallback if Redis is unavailable
