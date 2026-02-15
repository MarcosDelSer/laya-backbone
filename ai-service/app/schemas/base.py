"""Base Pydantic schema classes for LAYA AI Service.

Provides common base classes and mixins for all domain schemas.
"""

from datetime import datetime
from typing import Optional
from uuid import UUID

from pydantic import BaseModel, ConfigDict, Field


class BaseSchema(BaseModel):
    """Base schema class for all LAYA AI Service schemas.

    Configures ORM mode for SQLAlchemy model compatibility.
    All domain schemas should inherit from this class.
    """

    model_config = ConfigDict(
        from_attributes=True,
        populate_by_name=True,
        str_strip_whitespace=True,
    )


class TimestampMixin(BaseModel):
    """Mixin providing timestamp fields for audit purposes.

    Attributes:
        created_at: Timestamp when the record was created
        updated_at: Timestamp when the record was last updated
    """

    created_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp when the record was created",
    )
    updated_at: Optional[datetime] = Field(
        default=None,
        description="Timestamp when the record was last updated",
    )


class IDMixin(BaseModel):
    """Mixin providing UUID identifier field.

    Attributes:
        id: Unique identifier for the record
    """

    id: UUID = Field(
        ...,
        description="Unique identifier for the record",
    )


class BaseResponse(BaseSchema, TimestampMixin, IDMixin):
    """Base response schema with common fields.

    Combines BaseSchema with timestamp and ID mixins for
    typical API response payloads.
    """

    pass


class PaginationParams(BaseModel):
    """Pagination parameters for list endpoints.

    Attributes:
        skip: Number of records to skip (offset)
        limit: Maximum number of records to return
    """

    skip: int = Field(
        default=0,
        ge=0,
        description="Number of records to skip (offset)",
    )
    limit: int = Field(
        default=100,
        ge=1,
        le=1000,
        description="Maximum number of records to return",
    )


class PaginatedResponse(BaseSchema):
    """Base schema for paginated responses.

    Attributes:
        total: Total number of records matching the query
        skip: Number of records skipped
        limit: Maximum number of records returned
    """

    total: int = Field(
        ...,
        ge=0,
        description="Total number of records matching the query",
    )
    skip: int = Field(
        ...,
        ge=0,
        description="Number of records skipped",
    )
    limit: int = Field(
        ...,
        ge=1,
        description="Maximum number of records returned",
    )
