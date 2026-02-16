"""Authentication domain schemas for LAYA AI Service.

Defines Pydantic schemas for authentication requests and responses.
"""

from pydantic import EmailStr, Field

from app.schemas.base import BaseSchema


class LoginRequest(BaseSchema):
    """Request schema for user login.

    Used to authenticate a user with email and password credentials.

    Attributes:
        email: User's email address
        password: User's password (plain text, will be verified against hash)
    """

    email: EmailStr = Field(
        ...,
        description="User's email address",
    )
    password: str = Field(
        ...,
        min_length=8,
        max_length=100,
        description="User's password",
    )


class RefreshRequest(BaseSchema):
    """Request schema for token refresh.

    Used to obtain a new access token using a valid refresh token.

    Attributes:
        refresh_token: Valid JWT refresh token
    """

    refresh_token: str = Field(
        ...,
        min_length=1,
        description="Valid JWT refresh token",
    )


class TokenResponse(BaseSchema):
    """Response schema for authentication token.

    Contains JWT tokens and metadata returned after successful authentication.

    Attributes:
        access_token: JWT access token for API authentication
        refresh_token: JWT refresh token for obtaining new access tokens
        expires_in: Time in seconds until the access token expires
        token_type: Type of token (always "bearer" for JWT)
    """

    access_token: str = Field(
        ...,
        description="JWT access token for API authentication",
    )
    refresh_token: str = Field(
        ...,
        description="JWT refresh token for obtaining new access tokens",
    )
    expires_in: int = Field(
        ...,
        gt=0,
        description="Time in seconds until the access token expires",
    )
    token_type: str = Field(
        default="bearer",
        description="Type of token (always 'bearer' for JWT)",
    )


class LogoutRequest(BaseSchema):
    """Request schema for user logout.

    Used to invalidate authentication tokens and log out the user.

    Attributes:
        access_token: The JWT access token to invalidate
        refresh_token: Optional refresh token to invalidate
    """

    access_token: str = Field(
        ...,
        min_length=1,
        description="JWT access token to invalidate",
    )
    refresh_token: str | None = Field(
        default=None,
        description="Optional JWT refresh token to invalidate",
    )


class LogoutResponse(BaseSchema):
    """Response schema for user logout.

    Contains confirmation of successful logout.

    Attributes:
        message: Success message
        tokens_invalidated: Number of tokens invalidated
    """

    message: str = Field(
        ...,
        description="Success message",
    )
    tokens_invalidated: int = Field(
        ...,
        ge=0,
        description="Number of tokens invalidated",
    )
