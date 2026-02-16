"""FastAPI router for parent-educator messaging endpoints.

Provides endpoints for managing message threads between parents and educators.
Supports thread creation, listing, retrieval, and archival with participant
access control and unread message tracking.
"""

from typing import Any, Optional
from uuid import UUID

from fastapi import APIRouter, Depends, HTTPException, Query, status
from sqlalchemy.ext.asyncio import AsyncSession

from app.database import get_db
from app.dependencies import get_current_user
from app.schemas.messaging import (
    SenderType,
    ThreadCreate,
    ThreadListResponse,
    ThreadResponse,
    ThreadType,
    ThreadUpdate,
    ThreadWithMessagesResponse,
)
from app.services.messaging_service import (
    InvalidThreadError,
    MessagingService,
    MessagingServiceError,
    ThreadNotFoundError,
    UnauthorizedAccessError,
)

router = APIRouter()


def _get_user_type(current_user: dict[str, Any]) -> SenderType:
    """Extract sender type from user token payload.

    Maps the user's role from the JWT token to the appropriate SenderType enum.

    Args:
        current_user: Decoded JWT token payload

    Returns:
        SenderType corresponding to the user's role
    """
    role = current_user.get("role", "parent").lower()

    role_mapping = {
        "parent": SenderType.PARENT,
        "educator": SenderType.EDUCATOR,
        "director": SenderType.DIRECTOR,
        "admin": SenderType.ADMIN,
    }

    return role_mapping.get(role, SenderType.PARENT)


@router.post("/threads", response_model=ThreadResponse)
async def create_thread(
    request: ThreadCreate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ThreadResponse:
    """Create a new message thread.

    Creates a new conversation thread between parents and educators.
    The creator is automatically added as a participant if not already
    included in the participants list.

    Args:
        request: The thread creation request containing subject, type,
                 participants, and optional initial message
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ThreadResponse containing:
        - id: Unique identifier of the created thread
        - subject: Thread subject/title
        - thread_type: Type of thread (daily_log, urgent, serious, admin)
        - child_id: Associated child ID (if applicable)
        - participants: List of thread participants
        - unread_count: Number of unread messages (0 for new thread)
        - created_at: Thread creation timestamp

    Raises:
        HTTPException 400: When thread data is invalid
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])
        user_type = _get_user_type(current_user)

        return await service.create_thread(
            request=request,
            user_id=user_id,
            user_type=user_type,
        )
    except InvalidThreadError as e:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.get("/threads", response_model=ThreadListResponse)
async def list_threads(
    child_id: Optional[UUID] = Query(
        default=None,
        description="Filter threads by child ID",
    ),
    thread_type: Optional[ThreadType] = Query(
        default=None,
        description="Filter threads by type",
    ),
    include_archived: bool = Query(
        default=False,
        description="Include archived (inactive) threads",
    ),
    limit: int = Query(
        default=50,
        ge=1,
        le=100,
        description="Maximum number of threads to return",
    ),
    offset: int = Query(
        default=0,
        ge=0,
        description="Number of threads to skip for pagination",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ThreadListResponse:
    """List message threads for the current user.

    Retrieves all threads the user participates in, with optional
    filtering by child, type, and active status. Results are ordered
    by most recent activity.

    Args:
        child_id: Optional filter by child ID
        thread_type: Optional filter by thread type (daily_log, urgent, etc.)
        include_archived: Whether to include archived threads (default: False)
        limit: Maximum number of threads to return (default: 50, max: 100)
        offset: Number of threads to skip for pagination (default: 0)
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ThreadListResponse containing:
        - threads: List of ThreadResponse objects
        - total: Total number of matching threads
        - limit: Number of items per page
        - offset: Current offset

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])

        threads = await service.list_threads_for_user(
            user_id=user_id,
            child_id=child_id,
            thread_type=thread_type,
            include_archived=include_archived,
            limit=limit,
            offset=offset,
        )

        return ThreadListResponse(
            threads=threads,
            total=len(threads),
            limit=limit,
            skip=offset,
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.get("/threads/{thread_id}", response_model=ThreadWithMessagesResponse)
async def get_thread(
    thread_id: UUID,
    include_messages: bool = Query(
        default=True,
        description="Include messages in the response",
    ),
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ThreadWithMessagesResponse:
    """Get a message thread by ID.

    Retrieves a thread and optionally its messages. Validates that the
    current user has permission to access the thread.

    Args:
        thread_id: Unique identifier of the thread
        include_messages: Whether to include messages (default: True)
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ThreadWithMessagesResponse containing:
        - id: Thread identifier
        - subject: Thread subject/title
        - thread_type: Type of thread
        - participants: List of participants
        - messages: List of messages (if include_messages=True)
        - unread_count: Number of unread messages
        - last_message_at: Timestamp of most recent message

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user does not have access to the thread
        HTTPException 404: When thread is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])

        result = await service.get_thread(
            thread_id=thread_id,
            user_id=user_id,
            include_messages=include_messages,
        )

        # Always return ThreadWithMessagesResponse for consistency
        if isinstance(result, ThreadWithMessagesResponse):
            return result

        # Convert ThreadResponse to ThreadWithMessagesResponse
        return ThreadWithMessagesResponse(
            id=result.id,
            subject=result.subject,
            thread_type=result.thread_type,
            child_id=result.child_id,
            created_by=result.created_by,
            participants=result.participants,
            is_active=result.is_active,
            unread_count=result.unread_count,
            last_message=result.last_message,
            last_message_at=result.last_message_at,
            created_at=result.created_at,
            updated_at=result.updated_at,
            messages=[],
        )
    except ThreadNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.patch("/threads/{thread_id}", response_model=ThreadResponse)
async def update_thread(
    thread_id: UUID,
    request: ThreadUpdate,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ThreadResponse:
    """Update a message thread.

    Updates thread properties like subject or active status.
    Only participants can update a thread.

    Args:
        thread_id: Unique identifier of the thread
        request: The thread update request with fields to update
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ThreadResponse containing the updated thread data

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user does not have access to the thread
        HTTPException 404: When thread is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])

        return await service.update_thread(
            thread_id=thread_id,
            request=request,
            user_id=user_id,
        )
    except ThreadNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )


@router.delete("/threads/{thread_id}", response_model=ThreadResponse)
async def archive_thread(
    thread_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> ThreadResponse:
    """Archive a message thread.

    Marks a thread as archived (inactive). Archived threads are hidden
    from the default thread list but can still be retrieved with the
    include_archived parameter.

    Args:
        thread_id: Unique identifier of the thread to archive
        db: Async database session (injected)
        current_user: Authenticated user from JWT token (injected)

    Returns:
        ThreadResponse containing the archived thread data

    Raises:
        HTTPException 401: When JWT token is missing or invalid
        HTTPException 403: When user does not have access to the thread
        HTTPException 404: When thread is not found
        HTTPException 500: When an unexpected error occurs
    """
    service = MessagingService(db)

    try:
        user_id = UUID(current_user["sub"])

        return await service.archive_thread(
            thread_id=thread_id,
            user_id=user_id,
        )
    except ThreadNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e),
        )
    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
    except MessagingServiceError as e:
        raise HTTPException(
            status_code=status.HTTP_500_INTERNAL_SERVER_ERROR,
            detail=f"Messaging service error: {str(e)}",
        )
