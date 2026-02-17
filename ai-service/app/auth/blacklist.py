"""Token blacklist service using Redis for LAYA AI Service.

Provides Redis-based token blacklist functionality for JWT token revocation.
Ensures proper TTL management matching JWT token expiration.
"""

from datetime import datetime, timezone
from typing import Optional

import redis.asyncio as redis
from fastapi import HTTPException, status

from app.config import settings
from app.auth.jwt import decode_token


class TokenBlacklistService:
    """Service class for Redis-based token blacklist operations.

    Manages blacklisting of JWT tokens using Redis for fast lookups.
    Automatically sets TTL on blacklist entries to match token expiration.

    Attributes:
        redis_client: Async Redis client for blacklist storage.
    """

    def __init__(self, redis_client: Optional[redis.Redis] = None) -> None:
        """Initialize TokenBlacklistService with Redis client.

        Args:
            redis_client: Optional async Redis client. If not provided, creates a new one.
        """
        if redis_client is None:
            redis_client = redis.Redis(
                host=settings.redis_host,
                port=settings.redis_port,
                decode_responses=True,
            )
        self.redis_client = redis_client

    async def add_token_to_blacklist(
        self, token: str, expires_at: Optional[datetime] = None
    ) -> bool:
        """Add a JWT token to the blacklist.

        Extracts the JTI (JWT ID) from the token and stores it in Redis with
        a TTL matching the token's expiration time. This prevents expired tokens
        from cluttering Redis while ensuring valid tokens remain blacklisted.

        Args:
            token: JWT token to blacklist
            expires_at: Optional expiration datetime. If not provided, extracted from token.

        Returns:
            bool: True if token was successfully blacklisted

        Raises:
            HTTPException: 401 Unauthorized if token is invalid or cannot be decoded

        Example:
            >>> service = TokenBlacklistService()
            >>> await service.add_token_to_blacklist(user_token)
            True
        """
        # Decode token to get JTI and expiration
        payload = decode_token(token)

        # Extract JTI from payload
        jti = payload.get("jti")
        if not jti:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token: missing JTI claim",
                headers={"WWW-Authenticate": "Bearer"},
            )

        # Calculate TTL (time until token expires)
        if expires_at is None:
            exp_timestamp = payload.get("exp")
            if not exp_timestamp:
                raise HTTPException(
                    status_code=status.HTTP_401_UNAUTHORIZED,
                    detail="Invalid token: missing expiration",
                    headers={"WWW-Authenticate": "Bearer"},
                )
            expires_at = datetime.fromtimestamp(exp_timestamp, tz=timezone.utc)

        # Calculate TTL in seconds
        now = datetime.now(timezone.utc)
        ttl_seconds = int((expires_at - now).total_seconds())

        # Only blacklist if token hasn't already expired
        if ttl_seconds <= 0:
            # Token already expired, no need to blacklist
            return True

        # Store in Redis with key format: "blacklist:{jti}"
        key = f"blacklist:{jti}"
        await self.redis_client.setex(key, ttl_seconds, "1")

        return True

    async def is_token_blacklisted(self, token: str) -> bool:
        """Check if a JWT token is blacklisted.

        Extracts the JTI from the token and checks Redis for the blacklist entry.

        Args:
            token: JWT token to check

        Returns:
            bool: True if token is blacklisted, False otherwise

        Raises:
            HTTPException: 401 Unauthorized if token is invalid or cannot be decoded

        Example:
            >>> service = TokenBlacklistService()
            >>> is_blacklisted = await service.is_token_blacklisted(user_token)
            >>> if is_blacklisted:
            ...     raise HTTPException(status_code=401, detail="Token revoked")
        """
        # Decode token to get JTI
        payload = decode_token(token)

        # Extract JTI from payload
        jti = payload.get("jti")
        if not jti:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token: missing JTI claim",
                headers={"WWW-Authenticate": "Bearer"},
            )

        # Check if key exists in Redis
        key = f"blacklist:{jti}"
        exists = await self.redis_client.exists(key)

        return bool(exists)

    async def close(self) -> None:
        """Close the Redis connection.

        Should be called when the service is no longer needed to properly
        release Redis connections.

        Example:
            >>> service = TokenBlacklistService()
            >>> # ... use service ...
            >>> await service.close()
        """
        await self.redis_client.close()
