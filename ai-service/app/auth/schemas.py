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
