"""Authentication service for LAYA AI Service.

Provides business logic for user authentication, token generation, and management.
"""

from datetime import datetime, timezone
from typing import Optional
from uuid import UUID

from fastapi import HTTPException, status
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.auth.models import User
from app.auth.schemas import LoginRequest, RefreshRequest, TokenResponse
from app.auth.jwt import create_token as create_jwt_token, decode_token
from app.core.security import verify_password
from app.config import settings


class AuthService:
    """Service class for authentication business logic.

    Handles user login, token generation, and authentication-related operations.

    Attributes:
        db: Async database session for database operations.
    """

    # Token expiration times (in seconds)
    ACCESS_TOKEN_EXPIRE_SECONDS = 15 * 60  # 15 minutes
    REFRESH_TOKEN_EXPIRE_SECONDS = 7 * 24 * 60 * 60  # 7 days

    def __init__(self, db: AsyncSession) -> None:
        """Initialize AuthService with database session.

        Args:
            db: Async database session for database operations.
        """
        self.db = db

    async def authenticate_user(
        self, email: str, password: str
    ) -> Optional[User]:
        """Authenticate a user by email and password.

        Args:
            email: User's email address
            password: User's plain text password

        Returns:
            User object if authentication successful, None otherwise
        """
        # Query user by email
        stmt = select(User).where(User.email == email)
        result = await self.db.execute(stmt)
        user = result.scalar_one_or_none()

        # Return None if user not found
        if user is None:
            return None

        # Verify password
        if not verify_password(password, user.password_hash):
            return None

        # Check if user is active
        if not user.is_active:
            return None

        return user

    async def get_user_by_id(self, user_id: UUID) -> Optional[User]:
        """Get a user by their ID.

        Args:
            user_id: User's unique identifier

        Returns:
            User object if found, None otherwise
        """
        stmt = select(User).where(User.id == user_id)
        result = await self.db.execute(stmt)
        return result.scalar_one_or_none()

    async def login(self, login_request: LoginRequest) -> TokenResponse:
        """Process user login and generate authentication tokens.

        Args:
            login_request: Login credentials (email and password)

        Returns:
            TokenResponse containing access token, refresh token, and metadata

        Raises:
            HTTPException: 401 Unauthorized if credentials are invalid
        """
        # Authenticate user
        user = await self.authenticate_user(
            email=login_request.email,
            password=login_request.password,
        )

        if user is None:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Incorrect email or password",
                headers={"WWW-Authenticate": "Bearer"},
            )

        # Generate access token
        access_token = create_jwt_token(
            subject=str(user.id),
            expires_delta_seconds=self.ACCESS_TOKEN_EXPIRE_SECONDS,
            additional_claims={
                "email": user.email,
                "role": user.role.value,
                "type": "access",
            },
        )

        # Generate refresh token
        refresh_token = create_jwt_token(
            subject=str(user.id),
            expires_delta_seconds=self.REFRESH_TOKEN_EXPIRE_SECONDS,
            additional_claims={
                "type": "refresh",
            },
        )

        return TokenResponse(
            access_token=access_token,
            refresh_token=refresh_token,
            expires_in=self.ACCESS_TOKEN_EXPIRE_SECONDS,
            token_type="bearer",
        )

    async def refresh_tokens(self, refresh_request: RefreshRequest) -> TokenResponse:
        """Refresh access token using a valid refresh token.

        Args:
            refresh_request: Request containing the refresh token

        Returns:
            TokenResponse containing new access and refresh tokens

        Raises:
            HTTPException: 401 Unauthorized if refresh token is invalid or user not found
        """
        # Decode and validate refresh token
        payload = decode_token(refresh_request.refresh_token)

        # Verify token type
        token_type = payload.get("type")
        if token_type != "refresh":
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token type. Expected refresh token",
                headers={"WWW-Authenticate": "Bearer"},
            )

        # Extract user ID from token
        user_id_str = payload.get("sub")
        if not user_id_str:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token: missing subject",
                headers={"WWW-Authenticate": "Bearer"},
            )

        # Get user from database
        try:
            user_id = UUID(user_id_str)
        except ValueError:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token: invalid user ID",
                headers={"WWW-Authenticate": "Bearer"},
            )

        user = await self.get_user_by_id(user_id)
        if user is None:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="User not found",
                headers={"WWW-Authenticate": "Bearer"},
            )

        # Check if user is still active
        if not user.is_active:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="User account is inactive",
                headers={"WWW-Authenticate": "Bearer"},
            )

        # Generate new access token
        access_token = create_jwt_token(
            subject=str(user.id),
            expires_delta_seconds=self.ACCESS_TOKEN_EXPIRE_SECONDS,
            additional_claims={
                "email": user.email,
                "role": user.role.value,
                "type": "access",
            },
        )

        # Generate new refresh token
        new_refresh_token = create_jwt_token(
            subject=str(user.id),
            expires_delta_seconds=self.REFRESH_TOKEN_EXPIRE_SECONDS,
            additional_claims={
                "type": "refresh",
            },
        )

        return TokenResponse(
            access_token=access_token,
            refresh_token=new_refresh_token,
            expires_in=self.ACCESS_TOKEN_EXPIRE_SECONDS,
            token_type="bearer",
        )
