"""Authentication service for LAYA AI Service.

Provides business logic for user authentication, token generation, and management.
"""

from datetime import datetime, timezone, timedelta
from typing import Optional
from uuid import UUID
import secrets

from fastapi import HTTPException, status
from sqlalchemy import select
from sqlalchemy.ext.asyncio import AsyncSession

from app.auth.models import User, TokenBlacklist, PasswordResetToken
from app.auth.schemas import (
    LoginRequest,
    RefreshRequest,
    TokenResponse,
    LogoutRequest,
    LogoutResponse,
    PasswordResetRequest,
    PasswordResetRequestResponse,
    PasswordResetConfirm,
    PasswordResetConfirmResponse,
)
from app.auth.jwt import create_token as create_jwt_token, decode_token
from app.core.security import verify_password, hash_password
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
    PASSWORD_RESET_TOKEN_EXPIRE_SECONDS = 60 * 60  # 1 hour

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

    async def is_token_blacklisted(self, token: str) -> bool:
        """Check if a token is blacklisted.

        Args:
            token: JWT token to check

        Returns:
            bool: True if token is blacklisted, False otherwise
        """
        stmt = select(TokenBlacklist).where(TokenBlacklist.token == token)
        result = await self.db.execute(stmt)
        return result.scalar_one_or_none() is not None

    async def logout(self, logout_request: LogoutRequest) -> LogoutResponse:
        """Logout user by invalidating their tokens.

        This method adds the provided tokens to a blacklist to prevent their
        further use. Both access and refresh tokens can be invalidated.

        Args:
            logout_request: Request containing tokens to invalidate

        Returns:
            LogoutResponse confirming successful logout

        Raises:
            HTTPException: 401 Unauthorized if access token is invalid
        """
        # Decode and validate access token
        access_payload = decode_token(logout_request.access_token)

        # Verify token type
        token_type = access_payload.get("type")
        if token_type != "access":
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token type. Expected access token",
                headers={"WWW-Authenticate": "Bearer"},
            )

        # Extract user ID from token
        user_id_str = access_payload.get("sub")
        if not user_id_str:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token: missing subject",
                headers={"WWW-Authenticate": "Bearer"},
            )

        try:
            user_id = UUID(user_id_str)
        except ValueError:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token: invalid user ID",
                headers={"WWW-Authenticate": "Bearer"},
            )

        # Get token expiration timestamp
        exp_timestamp = access_payload.get("exp")
        if not exp_timestamp:
            raise HTTPException(
                status_code=status.HTTP_401_UNAUTHORIZED,
                detail="Invalid token: missing expiration",
                headers={"WWW-Authenticate": "Bearer"},
            )

        expires_at = datetime.fromtimestamp(exp_timestamp, tz=timezone.utc)
        tokens_invalidated = 0

        # Blacklist access token
        access_blacklist = TokenBlacklist(
            token=logout_request.access_token,
            user_id=user_id,
            expires_at=expires_at,
        )
        self.db.add(access_blacklist)
        tokens_invalidated += 1

        # Blacklist refresh token if provided
        if logout_request.refresh_token:
            try:
                refresh_payload = decode_token(logout_request.refresh_token)
                refresh_exp_timestamp = refresh_payload.get("exp")
                if refresh_exp_timestamp:
                    refresh_expires_at = datetime.fromtimestamp(
                        refresh_exp_timestamp, tz=timezone.utc
                    )
                    refresh_blacklist = TokenBlacklist(
                        token=logout_request.refresh_token,
                        user_id=user_id,
                        expires_at=refresh_expires_at,
                    )
                    self.db.add(refresh_blacklist)
                    tokens_invalidated += 1
            except HTTPException:
                # If refresh token is invalid, we just skip blacklisting it
                # The access token is still blacklisted, which is sufficient
                pass

        # Commit changes
        await self.db.commit()

        return LogoutResponse(
            message="Successfully logged out",
            tokens_invalidated=tokens_invalidated,
        )

    async def request_password_reset(
        self, reset_request: PasswordResetRequest
    ) -> PasswordResetRequestResponse:
        """Request a password reset by generating and storing a reset token.

        This method generates a secure reset token and stores it in the database.
        In a production environment, this token would be sent to the user's email.

        Args:
            reset_request: Request containing the user's email address

        Returns:
            PasswordResetRequestResponse confirming the request was processed

        Note:
            For security, this method always returns success even if the email
            doesn't exist in the system. This prevents email enumeration attacks.
        """
        # Query user by email
        stmt = select(User).where(User.email == reset_request.email)
        result = await self.db.execute(stmt)
        user = result.scalar_one_or_none()

        # Always return success to prevent email enumeration
        # Only create token if user exists and is active
        if user and user.is_active:
            # Generate secure random token
            reset_token = secrets.token_urlsafe(32)

            # Calculate expiration time
            expires_at = datetime.now(timezone.utc) + timedelta(
                seconds=self.PASSWORD_RESET_TOKEN_EXPIRE_SECONDS
            )

            # Store reset token in database
            password_reset = PasswordResetToken(
                token=reset_token,
                user_id=user.id,
                email=user.email,
                expires_at=expires_at,
                is_used=False,
            )
            self.db.add(password_reset)
            await self.db.commit()

            # In production, send email with reset token here
            # For now, the token would need to be retrieved from database or logs

        # Mask email for privacy (show first char and domain)
        email_parts = reset_request.email.split("@")
        masked_email = f"{email_parts[0][0]}***@{email_parts[1]}" if len(email_parts) == 2 else "***"

        return PasswordResetRequestResponse(
            message="If the email exists in our system, a password reset link has been sent",
            email=masked_email,
        )

    async def confirm_password_reset(
        self, confirm_request: PasswordResetConfirm
    ) -> PasswordResetConfirmResponse:
        """Confirm password reset and update user's password.

        This method validates the reset token and updates the user's password
        if the token is valid, not expired, and not already used.

        Args:
            confirm_request: Request containing reset token and new password

        Returns:
            PasswordResetConfirmResponse confirming successful password reset

        Raises:
            HTTPException: 400 Bad Request if:
                - Token is invalid or not found
                - Token has expired
                - Token has already been used
                - Associated user not found or inactive
        """
        # Query reset token
        stmt = select(PasswordResetToken).where(
            PasswordResetToken.token == confirm_request.token
        )
        result = await self.db.execute(stmt)
        reset_token = result.scalar_one_or_none()

        # Validate token exists
        if not reset_token:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Invalid or expired reset token",
            )

        # Validate token hasn't been used
        if reset_token.is_used:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Reset token has already been used",
            )

        # Validate token hasn't expired
        if datetime.now(timezone.utc) > reset_token.expires_at:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Reset token has expired",
            )

        # Get user
        user = await self.get_user_by_id(reset_token.user_id)
        if not user:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="User not found",
            )

        # Validate user is active
        if not user.is_active:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="User account is inactive",
            )

        # Hash new password
        new_password_hash = hash_password(confirm_request.new_password)

        # Update user's password
        user.password_hash = new_password_hash

        # Mark token as used
        reset_token.is_used = True

        # Commit changes
        await self.db.commit()

        return PasswordResetConfirmResponse(
            message="Password has been successfully reset",
        )
