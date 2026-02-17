"""Redis-based token blacklist service for LAYA AI Service.

Provides token blacklisting functionality with automatic TTL expiration
matching JWT token expiration times for optimal performance and storage efficiency.
"""

from datetime import datetime, timezone
from typing import Optional

from redis.asyncio import Redis

from app.redis_client import get_redis_client


class TokenBlacklistService:
    """Service for managing token blacklist using Redis with TTL.

    This service provides high-performance token blacklisting with automatic
    expiration. Tokens are stored in Redis with TTL matching their JWT expiration,
    ensuring efficient memory usage and eliminating the need for manual cleanup.

    Performance characteristics:
        - Blacklist check: < 5ms (Redis GET operation)
        - Blacklist add: < 5ms (Redis SETEX operation)
        - Automatic expiration via Redis TTL (no cleanup needed)

    Example:
        >>> blacklist_service = TokenBlacklistService()
        >>> # Blacklist a token
        >>> await blacklist_service.add_to_blacklist(
        ...     token="eyJhbG...",
        ...     user_id="user123",
        ...     expires_at=datetime(2026, 2, 17, 12, 0, 0, tzinfo=timezone.utc)
        ... )
        >>> # Check if token is blacklisted
        >>> is_blacklisted = await blacklist_service.is_blacklisted("eyJhbG...")
        >>> print(is_blacklisted)
        True
    """

    def __init__(self, redis_client: Optional[Redis] = None):
        """Initialize the blacklist service.

        Args:
            redis_client: Optional Redis client instance. If not provided,
                will be fetched from get_redis_client() when needed.
        """
        self._redis_client = redis_client

    async def _get_client(self) -> Redis:
        """Get Redis client instance.

        Returns:
            Redis: Async Redis client

        Raises:
            Exception: If Redis connection fails
        """
        if self._redis_client is None:
            self._redis_client = await get_redis_client()
        return self._redis_client

    def _make_key(self, token: str) -> str:
        """Create Redis key for a token.

        Args:
            token: JWT token string

        Returns:
            str: Redis key for the token

        Note:
            Uses prefix "blacklist:" for namespace isolation and easy identification
        """
        return f"blacklist:{token}"

    async def add_to_blacklist(
        self,
        token: str,
        user_id: str,
        expires_at: datetime,
    ) -> bool:
        """Add a token to the blacklist with automatic TTL expiration.

        The token will be stored in Redis with a TTL matching its JWT expiration
        time. When the token would naturally expire, Redis will automatically
        remove it from the blacklist, ensuring efficient memory usage.

        Args:
            token: JWT token to blacklist
            user_id: User ID associated with the token (stored for audit purposes)
            expires_at: Token expiration datetime (must be timezone-aware)

        Returns:
            bool: True if token was successfully blacklisted, False otherwise

        Raises:
            ValueError: If expires_at is not timezone-aware or is in the past
            Exception: If Redis operation fails

        Example:
            >>> expires_at = datetime.now(timezone.utc) + timedelta(hours=1)
            >>> success = await blacklist_service.add_to_blacklist(
            ...     token="eyJhbG...",
            ...     user_id="user123",
            ...     expires_at=expires_at
            ... )
            >>> print(success)
            True

        Security:
            - Tokens are stored with their user_id for audit trail
            - Automatic expiration prevents indefinite storage
            - Uses SETEX for atomic set-with-expiry operation
        """
        # Validate expires_at is timezone-aware
        if expires_at.tzinfo is None:
            raise ValueError("expires_at must be timezone-aware")

        # Calculate TTL in seconds
        now = datetime.now(timezone.utc)
        ttl_seconds = int((expires_at - now).total_seconds())

        # Don't blacklist tokens that are already expired
        if ttl_seconds <= 0:
            raise ValueError("Cannot blacklist token that has already expired")

        # Store token in Redis with TTL
        # Value includes user_id for audit purposes and timestamp for debugging
        client = await self._get_client()
        key = self._make_key(token)
        value = f"{user_id}:{int(now.timestamp())}"

        # SETEX is atomic: sets value and TTL in one operation
        await client.setex(key, ttl_seconds, value)

        return True

    async def is_blacklisted(self, token: str) -> bool:
        """Check if a token is blacklisted.

        This is a high-performance operation (< 5ms) that checks if the token
        exists in the Redis blacklist. If the token has expired, Redis will
        have automatically removed it, and this will return False.

        Args:
            token: JWT token to check

        Returns:
            bool: True if token is blacklisted, False otherwise

        Example:
            >>> is_blacklisted = await blacklist_service.is_blacklisted("eyJhbG...")
            >>> if is_blacklisted:
            ...     print("Token is revoked")
            ... else:
            ...     print("Token is valid")

        Performance:
            - Average: < 5ms
            - Redis GET operation with O(1) complexity
        """
        client = await self._get_client()
        key = self._make_key(token)

        # Redis GET returns None if key doesn't exist or has expired
        value = await client.get(key)
        return value is not None

    async def get_blacklist_info(self, token: str) -> Optional[dict[str, str]]:
        """Get detailed information about a blacklisted token.

        Args:
            token: JWT token to get information about

        Returns:
            dict: Token blacklist information with keys:
                - user_id: User ID who owned the token
                - blacklisted_at: Timestamp when token was blacklisted
                - ttl: Remaining time-to-live in seconds
            None: If token is not blacklisted

        Example:
            >>> info = await blacklist_service.get_blacklist_info("eyJhbG...")
            >>> if info:
            ...     print(f"Token was blacklisted by user {info['user_id']}")
        """
        client = await self._get_client()
        key = self._make_key(token)

        # Get value and TTL in a pipeline for efficiency
        pipe = client.pipeline()
        pipe.get(key)
        pipe.ttl(key)
        results = await pipe.execute()

        value = results[0]
        ttl = results[1]

        if value is None:
            return None

        # Parse stored value: "user_id:timestamp"
        parts = value.split(":", 1)
        user_id = parts[0] if len(parts) > 0 else "unknown"
        blacklisted_at = parts[1] if len(parts) > 1 else "unknown"

        return {
            "user_id": user_id,
            "blacklisted_at": blacklisted_at,
            "ttl": str(ttl),
        }

    async def remove_from_blacklist(self, token: str) -> bool:
        """Remove a token from the blacklist.

        This is typically not needed as tokens automatically expire via TTL,
        but can be used in special cases (e.g., token unbanning).

        Args:
            token: JWT token to remove from blacklist

        Returns:
            bool: True if token was removed, False if it wasn't blacklisted

        Example:
            >>> removed = await blacklist_service.remove_from_blacklist("eyJhbG...")
            >>> print(f"Token removed: {removed}")
        """
        client = await self._get_client()
        key = self._make_key(token)

        # DEL returns number of keys deleted (0 or 1)
        deleted_count = await client.delete(key)
        return deleted_count > 0

    async def cleanup_expired(self) -> int:
        """Clean up expired tokens from the blacklist.

        Note:
            This method is provided for compatibility but is NOT needed in practice.
            Redis automatically removes expired keys via TTL, so manual cleanup
            is unnecessary. This returns 0 to indicate no manual cleanup was performed.

        Returns:
            int: Number of tokens cleaned up (always 0 for Redis-based blacklist)

        Example:
            >>> cleaned = await blacklist_service.cleanup_expired()
            >>> print(f"Cleaned up {cleaned} tokens (Redis handles this automatically)")
        """
        # Redis automatically removes expired keys via TTL
        # No manual cleanup needed
        return 0
