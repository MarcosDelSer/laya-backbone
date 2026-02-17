"""Authentication dependency utilities for LAYA AI Service.

Provides role-based access control decorators and dependencies for FastAPI endpoints.
"""

from __future__ import annotations

from typing import Any, Callable, Optional, Type, TypeVar
from uuid import UUID

from fastapi import Depends, HTTPException, status
from fastapi.security import HTTPAuthorizationCredentials
from sqlalchemy import and_, cast, or_, select, String
from sqlalchemy.ext.asyncio import AsyncSession

from app.auth.exceptions import ResourceNotFoundError, UnauthorizedAccessError
from app.auth.jwt import verify_token, security
from app.auth.models import UserRole
from app.database import get_db

# Type variable for SQLAlchemy models
ModelType = TypeVar("ModelType")


async def get_current_user(
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: AsyncSession = Depends(get_db),
) -> dict[str, Any]:
    """Dependency to get the current authenticated user from JWT token.

    This dependency extracts and validates the JWT token from the Authorization
    header, checks that it has not been revoked, and returns the decoded payload
    containing user information.

    Args:
        credentials: HTTP Authorization credentials injected by FastAPI
        db: Async database session for blacklist lookup

    Returns:
        dict[str, Any]: Decoded token payload containing user information
            including 'sub' (user ID), 'email', 'role', and 'type'

    Raises:
        HTTPException: 401 Unauthorized if token is missing, invalid, expired, or revoked

    Example:
        @router.get("/profile")
        async def get_profile(current_user: dict = Depends(get_current_user)):
            return {"user_id": current_user["sub"], "email": current_user["email"]}
    """
    return await verify_token(credentials, db)


def require_role(*allowed_roles: UserRole) -> Callable:
    """Dependency factory for role-based access control.

    Creates a FastAPI dependency that validates the current user has one
    of the specified roles. Use this to protect endpoints that should only
    be accessible to users with specific roles.

    Args:
        *allowed_roles: One or more UserRole values that are allowed access

    Returns:
        Callable: A FastAPI dependency function that checks user roles

    Raises:
        HTTPException: 403 Forbidden if user doesn't have required role
        HTTPException: 401 Unauthorized if token is invalid

    Example:
        # Require admin role
        @router.delete("/users/{user_id}")
        async def delete_user(
            user_id: str,
            current_user: dict = Depends(require_role(UserRole.ADMIN))
        ):
            return {"message": "User deleted"}

        # Allow multiple roles
        @router.get("/reports")
        async def get_reports(
            current_user: dict = Depends(
                require_role(UserRole.ADMIN, UserRole.ACCOUNTANT)
            )
        ):
            return {"reports": [...]}

        # Protect entire router
        router = APIRouter(
            prefix="/admin",
            dependencies=[Depends(require_role(UserRole.ADMIN))]
        )
    """

    async def role_checker(
        current_user: dict[str, Any] = Depends(get_current_user),
    ) -> dict[str, Any]:
        """Check if the current user has one of the required roles.

        Args:
            current_user: Decoded JWT token payload from get_current_user dependency

        Returns:
            dict[str, Any]: The current user payload if authorized

        Raises:
            HTTPException: 403 Forbidden if user doesn't have required role
        """
        # Extract user role from token payload
        user_role = current_user.get("role")

        if not user_role:
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail="User role not found in token",
            )

        # Check if user role matches any of the allowed roles
        allowed_role_values = [role.value for role in allowed_roles]

        if user_role not in allowed_role_values:
            roles_str = ", ".join(allowed_role_values)
            raise HTTPException(
                status_code=status.HTTP_403_FORBIDDEN,
                detail=f"Access denied. Required role(s): {roles_str}. Your role: {user_role}",
            )

        return current_user

    return role_checker


# =============================================================================
# Authorization Helper Functions
# =============================================================================


async def verify_resource_owner(
    db: AsyncSession,
    model: Type[ModelType],
    resource_id: UUID,
    user_id: UUID,
    owner_field: str = "owner_id",
    resource_name: str = "Resource",
) -> ModelType:
    """Verify that a user owns a specific resource.

    This helper function queries a database model to verify that the resource
    with the given ID exists and is owned by the specified user. It is used
    to prevent Insecure Direct Object Reference (IDOR) vulnerabilities.

    Args:
        db: Async database session
        model: SQLAlchemy model class to query
        resource_id: UUID of the resource to verify
        user_id: UUID of the user claiming ownership
        owner_field: Name of the ownership field in the model (default: "owner_id")
        resource_name: Human-readable resource name for error messages

    Returns:
        ModelType: The resource instance if ownership is verified

    Raises:
        ResourceNotFoundError: When the resource is not found
        UnauthorizedAccessError: When the user does not own the resource

    Example:
        # In a service method
        document = await verify_resource_owner(
            db=self.db,
            model=Document,
            resource_id=document_id,
            user_id=current_user_id,
            owner_field="created_by",
            resource_name="Document"
        )
    """
    # Query the resource by ID
    query = select(model).where(
        cast(getattr(model, "id"), String) == str(resource_id)
    )
    result = await db.execute(query)
    resource = result.scalar_one_or_none()

    # Check if resource exists
    if not resource:
        raise ResourceNotFoundError(
            f"{resource_name} with ID {resource_id} not found"
        )

    # Verify ownership
    resource_owner_id = getattr(resource, owner_field, None)

    if resource_owner_id is None:
        raise UnauthorizedAccessError(
            f"{resource_name} does not have an owner field '{owner_field}'"
        )

    if str(resource_owner_id) != str(user_id):
        raise UnauthorizedAccessError(
            f"User does not have permission to access this {resource_name.lower()}"
        )

    return resource


async def verify_child_access(
    db: AsyncSession,
    child_id: UUID,
    user_id: UUID,
    user_role: str,
    allow_educators: bool = True,
) -> bool:
    """Verify that a user has access to a child's data.

    This helper function verifies that a user (parent, educator, or admin) has
    permission to access data associated with a specific child. Access rules:
    - Admins: Always have access
    - Educators/Teachers: Have access if allow_educators=True
    - Parents: Have access only to their own children (verified through relationships)

    Args:
        db: Async database session
        child_id: UUID of the child
        user_id: UUID of the user requesting access
        user_role: Role of the user (from JWT token)
        allow_educators: Whether educators/teachers should have access (default: True)

    Returns:
        bool: True if user has access, False otherwise

    Raises:
        UnauthorizedAccessError: When the user does not have access to the child

    Example:
        # In a service method
        await verify_child_access(
            db=self.db,
            child_id=child_id,
            user_id=current_user["sub"],
            user_role=current_user["role"],
            allow_educators=True
        )

        # In a router with current_user
        await verify_child_access(
            db=db,
            child_id=request.child_id,
            user_id=UUID(current_user["sub"]),
            user_role=current_user["role"]
        )
    """
    # Normalize role to lowercase for comparison
    role_lower = user_role.lower()

    # Admins always have access
    if role_lower in ["admin", "administrator"]:
        return True

    # Educators/teachers have access if allowed
    if allow_educators and role_lower in ["educator", "teacher", "director"]:
        return True

    # For parents, verify parent-child relationship
    # Note: In the current implementation, we check if there are any resources
    # (communication preferences, coaching sessions, etc.) that link this parent to this child
    if role_lower == "parent":
        # Check CommunicationPreference for parent-child relationship
        from app.models.communication import CommunicationPreference

        query = select(CommunicationPreference).where(
            and_(
                cast(CommunicationPreference.parent_id, String) == str(user_id),
                cast(CommunicationPreference.child_id, String) == str(child_id),
            )
        )
        result = await db.execute(query)
        comm_pref = result.scalar_one_or_none()

        if comm_pref:
            return True

        # Check CoachingSession for parent-child relationship
        from app.models.coaching import CoachingSession

        query = select(CoachingSession).where(
            and_(
                cast(CoachingSession.user_id, String) == str(user_id),
                cast(CoachingSession.child_id, String) == str(child_id),
            )
        )
        result = await db.execute(query)
        coaching_session = result.scalar_one_or_none()

        if coaching_session:
            return True

        # If no relationship found, deny access
        raise UnauthorizedAccessError(
            "User does not have permission to access this child's data"
        )

    # Default: deny access
    raise UnauthorizedAccessError(
        f"User with role '{user_role}' does not have permission to access child data"
    )
