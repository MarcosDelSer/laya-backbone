"""Field selection utilities for API response optimization.

This module provides utilities to allow clients to request only specific fields
in API responses using the ?fields= query parameter. This reduces payload size
and improves performance by transmitting only the data the client needs.

Features:
- Parse comma-separated field names from query parameter
- Filter Pydantic model responses to include only requested fields
- Support for nested field selection using dot notation (e.g., "user.name")
- Validation to ensure only valid fields are requested
- Automatic inclusion of required/critical fields (like 'id')

Usage:
    # As a dependency in FastAPI routes
    from app.utils.field_selection import get_field_selector, FieldSelector

    @router.get("/items/{item_id}")
    async def get_item(
        item_id: UUID,
        field_selector: FieldSelector = Depends(get_field_selector),
    ) -> dict:
        item = await service.get_item(item_id)
        return field_selector.filter_fields(item)

    # Direct usage
    from app.utils.field_selection import parse_fields, filter_response

    fields = parse_fields("id,name,created_at")
    filtered = filter_response(response_model, fields)

Examples:
    # Request only specific fields
    GET /api/v1/activities/123?fields=id,name,description

    # Request nested fields
    GET /api/v1/activities/123?fields=id,activity.name,activity.type

    # Multiple fields
    GET /api/v1/activities/123?fields=id,name,created_at,updated_at
"""

from typing import Any, Optional, Set, Union

from fastapi import Query
from pydantic import BaseModel


def parse_fields(fields_param: Optional[str]) -> Optional[Set[str]]:
    """Parse comma-separated fields parameter into a set of field names.

    Args:
        fields_param: Comma-separated field names (e.g., "id,name,email")

    Returns:
        Set of field names, or None if fields_param is None/empty

    Example:
        >>> parse_fields("id,name,email")
        {'id', 'name', 'email'}
        >>> parse_fields("id, name , email ")  # handles whitespace
        {'id', 'name', 'email'}
        >>> parse_fields("")
        None
        >>> parse_fields(None)
        None
    """
    if not fields_param or not fields_param.strip():
        return None

    # Split by comma and strip whitespace from each field
    fields = {field.strip() for field in fields_param.split(",") if field.strip()}

    return fields if fields else None


def filter_response(
    response: Union[BaseModel, dict, list],
    fields: Optional[Set[str]],
    always_include: Optional[Set[str]] = None,
) -> Union[dict, list]:
    """Filter response data to include only requested fields.

    Args:
        response: Pydantic model, dict, or list to filter
        fields: Set of field names to include (None = include all)
        always_include: Set of fields to always include (e.g., {'id'})

    Returns:
        Filtered dictionary or list with only requested fields

    Example:
        >>> from pydantic import BaseModel
        >>> class User(BaseModel):
        ...     id: int
        ...     name: str
        ...     email: str
        ...     age: int
        >>> user = User(id=1, name="John", email="john@example.com", age=30)
        >>> filter_response(user, {"id", "name"})
        {'id': 1, 'name': 'John'}
        >>> filter_response(user, None)  # No filtering
        {'id': 1, 'name': 'John', 'email': 'john@example.com', 'age': 30}
    """
    # If no fields specified, return full response
    if fields is None:
        if isinstance(response, BaseModel):
            return response.model_dump()
        return response

    # Combine requested fields with always_include fields
    final_fields = fields.copy()
    if always_include:
        final_fields.update(always_include)

    # Handle list responses
    if isinstance(response, list):
        return [filter_response(item, fields, always_include) for item in response]

    # Convert Pydantic model to dict
    if isinstance(response, BaseModel):
        response_dict = response.model_dump()
    elif isinstance(response, dict):
        response_dict = response
    else:
        # For other types, return as-is
        return response

    # Filter to requested fields
    filtered = {}
    for field in final_fields:
        # Handle nested field access (e.g., "user.name")
        if "." in field:
            parts = field.split(".", 1)
            parent_field = parts[0]
            nested_field = parts[1]

            if parent_field in response_dict:
                parent_value = response_dict[parent_field]
                # Initialize parent in filtered dict if not present
                if parent_field not in filtered:
                    if isinstance(parent_value, BaseModel):
                        filtered[parent_field] = {}
                    else:
                        filtered[parent_field] = parent_value

                # Handle nested filtering
                if isinstance(parent_value, BaseModel):
                    nested_filtered = filter_response(
                        parent_value, {nested_field}, always_include
                    )
                    if isinstance(filtered[parent_field], dict):
                        filtered[parent_field].update(nested_filtered)
                elif isinstance(parent_value, dict) and nested_field in parent_value:
                    if isinstance(filtered[parent_field], dict):
                        filtered[parent_field][nested_field] = parent_value[
                            nested_field
                        ]
        else:
            # Simple field access
            if field in response_dict:
                filtered[field] = response_dict[field]

    return filtered


def validate_fields(
    requested_fields: Set[str],
    valid_fields: Set[str],
    raise_on_invalid: bool = False,
) -> Set[str]:
    """Validate that requested fields exist in the model.

    Args:
        requested_fields: Fields requested by the client
        valid_fields: Valid field names for the model
        raise_on_invalid: Whether to raise ValueError for invalid fields

    Returns:
        Set of valid requested fields (filters out invalid ones)

    Raises:
        ValueError: If raise_on_invalid=True and invalid fields are present

    Example:
        >>> requested = {"id", "name", "invalid_field"}
        >>> valid = {"id", "name", "email", "age"}
        >>> validate_fields(requested, valid, raise_on_invalid=False)
        {'id', 'name'}
        >>> validate_fields(requested, valid, raise_on_invalid=True)
        Traceback (most recent call last):
            ...
        ValueError: Invalid fields requested: invalid_field
    """
    # Handle nested fields by checking only the top-level field
    top_level_requested = {
        field.split(".")[0] if "." in field else field for field in requested_fields
    }

    invalid_fields = top_level_requested - valid_fields

    if invalid_fields and raise_on_invalid:
        raise ValueError(f"Invalid fields requested: {', '.join(sorted(invalid_fields))}")

    # Return only valid requested fields (including nested)
    valid_requested = {
        field
        for field in requested_fields
        if field.split(".")[0] in valid_fields
    }

    return valid_requested


class FieldSelector:
    """Field selector for filtering API responses.

    This class encapsulates field selection logic and can be used as a
    FastAPI dependency to provide field filtering across routes.

    Attributes:
        fields: Set of field names to include in responses
        always_include: Fields to always include regardless of selection
    """

    def __init__(
        self,
        fields: Optional[Set[str]] = None,
        always_include: Optional[Set[str]] = None,
    ):
        """Initialize field selector.

        Args:
            fields: Set of field names to include (None = all)
            always_include: Fields to always include (e.g., 'id')
        """
        self.fields = fields
        self.always_include = always_include or {"id"}

    def filter_fields(
        self,
        response: Union[BaseModel, dict, list],
        model_class: Optional[type[BaseModel]] = None,
    ) -> Union[dict, list]:
        """Filter response to include only selected fields.

        Args:
            response: Response data to filter
            model_class: Optional Pydantic model class for validation

        Returns:
            Filtered response data

        Example:
            >>> selector = FieldSelector(fields={"id", "name"})
            >>> user = User(id=1, name="John", email="john@example.com")
            >>> selector.filter_fields(user)
            {'id': 1, 'name': 'John'}
        """
        # Validate fields if model class provided
        if self.fields and model_class:
            valid_fields = set(model_class.model_fields.keys())
            validated_fields = validate_fields(
                self.fields, valid_fields, raise_on_invalid=False
            )
            return filter_response(response, validated_fields, self.always_include)

        return filter_response(response, self.fields, self.always_include)

    def is_field_requested(self, field_name: str) -> bool:
        """Check if a specific field was requested.

        Args:
            field_name: Name of the field to check

        Returns:
            True if the field is requested or if no filtering is active

        Example:
            >>> selector = FieldSelector(fields={"id", "name"})
            >>> selector.is_field_requested("name")
            True
            >>> selector.is_field_requested("email")
            False
        """
        if self.fields is None:
            return True
        return field_name in self.fields or field_name in self.always_include


async def get_field_selector(
    fields: Optional[str] = Query(
        None,
        description="Comma-separated list of fields to include in response "
        "(e.g., 'id,name,email'). If not specified, all fields are returned.",
        examples=["id,name,created_at"],
        alias="fields",
    )
) -> FieldSelector:
    """FastAPI dependency for field selection.

    This dependency parses the ?fields= query parameter and returns a
    FieldSelector instance that can be used to filter response data.

    Args:
        fields: Comma-separated field names from query parameter

    Returns:
        FieldSelector configured with requested fields

    Example:
        @router.get("/users/{user_id}")
        async def get_user(
            user_id: UUID,
            selector: FieldSelector = Depends(get_field_selector),
        ) -> dict:
            user = await service.get_user(user_id)
            return selector.filter_fields(user, UserResponse)
    """
    parsed_fields = parse_fields(fields)
    return FieldSelector(fields=parsed_fields)
