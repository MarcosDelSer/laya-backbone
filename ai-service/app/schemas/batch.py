"""Batch operation schemas for LAYA AI Service.

Provides schemas for batch API operations that allow clients to perform
multiple requests in a single HTTP call, reducing network round-trips
and improving application performance.
"""

from datetime import datetime
from enum import Enum
from typing import Any, Generic, Optional, TypeVar
from uuid import UUID

from pydantic import BaseModel, Field

from app.schemas.base import BaseSchema


class BatchOperationType(str, Enum):
    """Types of batch operations.

    Attributes:
        GET: Batch retrieval of resources
        CREATE: Batch creation of resources
        UPDATE: Batch update of resources
        DELETE: Batch deletion of resources
    """

    GET = "get"
    CREATE = "create"
    UPDATE = "update"
    DELETE = "delete"


class BatchOperationStatus(str, Enum):
    """Status of individual batch operation.

    Attributes:
        SUCCESS: Operation completed successfully
        ERROR: Operation failed with error
        PARTIAL: Operation partially succeeded (for nested operations)
    """

    SUCCESS = "success"
    ERROR = "error"
    PARTIAL = "partial"


class BatchGetRequest(BaseSchema):
    """Request schema for batch GET operations.

    Allows fetching multiple resources by their IDs in a single request.

    Attributes:
        resource_type: Type of resource to fetch (e.g., 'activities', 'coaching')
        ids: List of resource IDs to fetch
        fields: Optional field selection (comma-separated)
    """

    resource_type: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Type of resource to fetch (e.g., 'activities')",
    )
    ids: list[UUID] = Field(
        ...,
        min_items=1,
        max_items=100,
        description="List of resource IDs to fetch (max 100)",
    )
    fields: Optional[str] = Field(
        default=None,
        description="Optional comma-separated list of fields to include",
    )


T = TypeVar("T")


class BatchOperationResult(BaseSchema, Generic[T]):
    """Result of a single operation within a batch.

    Attributes:
        id: ID of the resource (request ID or resource ID)
        status: Status of the operation
        data: Result data if successful
        error: Error message if failed
        status_code: HTTP status code for this operation
    """

    id: UUID = Field(
        ...,
        description="ID of the resource or request",
    )
    status: BatchOperationStatus = Field(
        ...,
        description="Status of the operation",
    )
    data: Optional[Any] = Field(
        default=None,
        description="Result data if operation succeeded",
    )
    error: Optional[str] = Field(
        default=None,
        description="Error message if operation failed",
    )
    status_code: int = Field(
        default=200,
        ge=100,
        le=599,
        description="HTTP status code for this operation",
    )


class BatchGetResponse(BaseSchema):
    """Response schema for batch GET operations.

    Contains results for all requested resources with success/failure status.

    Attributes:
        resource_type: Type of resources fetched
        results: List of operation results
        total_requested: Total number of resources requested
        total_succeeded: Number of successful operations
        total_failed: Number of failed operations
        processed_at: When the batch was processed
    """

    resource_type: str = Field(
        ...,
        description="Type of resources fetched",
    )
    results: list[BatchOperationResult] = Field(
        ...,
        description="List of operation results",
    )
    total_requested: int = Field(
        ...,
        ge=0,
        description="Total number of resources requested",
    )
    total_succeeded: int = Field(
        ...,
        ge=0,
        description="Number of successful operations",
    )
    total_failed: int = Field(
        ...,
        ge=0,
        description="Number of failed operations",
    )
    processed_at: datetime = Field(
        ...,
        description="When the batch was processed",
    )


class BatchActivityRecommendationRequest(BaseSchema):
    """Request schema for batch activity recommendations.

    Allows fetching recommendations for multiple children in a single request.

    Attributes:
        child_ids: List of child IDs to get recommendations for
        max_recommendations: Maximum recommendations per child
        activity_types: Optional filter for activity types
        child_age_months: Optional child age for filtering
        weather: Optional weather condition
        group_size: Optional group size
        include_special_needs: Whether to include special needs adaptations
    """

    child_ids: list[UUID] = Field(
        ...,
        min_items=1,
        max_items=50,
        description="List of child IDs (max 50)",
    )
    max_recommendations: int = Field(
        default=5,
        ge=1,
        le=20,
        description="Maximum recommendations per child",
    )
    activity_types: Optional[list[str]] = Field(
        default=None,
        description="Optional filter for activity types",
    )
    child_age_months: Optional[int] = Field(
        default=None,
        ge=0,
        le=144,
        description="Optional child age in months",
    )
    weather: Optional[str] = Field(
        default=None,
        description="Optional weather condition",
    )
    group_size: Optional[int] = Field(
        default=None,
        ge=1,
        description="Optional group size",
    )
    include_special_needs: bool = Field(
        default=True,
        description="Include special needs adaptations",
    )


class BatchActivityRecommendationResponse(BaseSchema):
    """Response schema for batch activity recommendations.

    Contains recommendations for all requested children.

    Attributes:
        results: List of recommendation results per child
        total_requested: Total children requested
        total_succeeded: Number of successful recommendations
        total_failed: Number of failed recommendations
        processed_at: When the batch was processed
    """

    results: list[BatchOperationResult] = Field(
        ...,
        description="List of recommendation results per child",
    )
    total_requested: int = Field(
        ...,
        ge=0,
        description="Total children requested",
    )
    total_succeeded: int = Field(
        ...,
        ge=0,
        description="Number of successful recommendations",
    )
    total_failed: int = Field(
        ...,
        ge=0,
        description="Number of failed recommendations",
    )
    processed_at: datetime = Field(
        ...,
        description="When the batch was processed",
    )


class BatchCreateItem(BaseSchema):
    """Item in a batch create request.

    Attributes:
        request_id: Client-provided ID to correlate request/response
        data: Data for creating the resource
    """

    request_id: UUID = Field(
        ...,
        description="Client-provided ID to correlate request/response",
    )
    data: dict[str, Any] = Field(
        ...,
        description="Data for creating the resource",
    )


class BatchCreateRequest(BaseSchema):
    """Request schema for batch create operations.

    Allows creating multiple resources in a single request.

    Attributes:
        resource_type: Type of resource to create
        items: List of items to create
    """

    resource_type: str = Field(
        ...,
        min_length=1,
        max_length=100,
        description="Type of resource to create",
    )
    items: list[BatchCreateItem] = Field(
        ...,
        min_items=1,
        max_items=100,
        description="List of items to create (max 100)",
    )


class BatchCreateResponse(BaseSchema):
    """Response schema for batch create operations.

    Contains results for all create operations.

    Attributes:
        resource_type: Type of resources created
        results: List of creation results
        total_requested: Total number of creates requested
        total_succeeded: Number of successful creates
        total_failed: Number of failed creates
        processed_at: When the batch was processed
    """

    resource_type: str = Field(
        ...,
        description="Type of resources created",
    )
    results: list[BatchOperationResult] = Field(
        ...,
        description="List of creation results",
    )
    total_requested: int = Field(
        ...,
        ge=0,
        description="Total number of creates requested",
    )
    total_succeeded: int = Field(
        ...,
        ge=0,
        description="Number of successful creates",
    )
    total_failed: int = Field(
        ...,
        ge=0,
        description="Number of failed creates",
    )
    processed_at: datetime = Field(
        ...,
        description="When the batch was processed",
    )
