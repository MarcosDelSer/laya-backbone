# Authorization & IDOR Protection

**Last Updated:** 2026-02-17
**Status:** Production-Ready
**Related Files:** `authorization_pattern.md`, `security_audit_report.md`

## Table of Contents

1. [Overview](#overview)
2. [Quick Start Guide](#quick-start-guide)
3. [Authorization Pattern](#authorization-pattern)
4. [Implementation Steps](#implementation-steps)
5. [Real-World Examples](#real-world-examples)
6. [Testing Requirements](#testing-requirements)
7. [Common Pitfalls](#common-pitfalls)
8. [Best Practices](#best-practices)
9. [Security Checklist](#security-checklist)

---

## Overview

### What is IDOR?

**IDOR (Insecure Direct Object Reference)** is a critical security vulnerability where authenticated users can access or modify resources belonging to other users by manipulating object IDs in API requests.

**Example Attack:**
```bash
# User A's document
GET /api/v1/documents/123-abc  # ✅ Returns document

# User A tries to access User B's document by guessing/enumerating IDs
GET /api/v1/documents/456-def  # ❌ Should return 403, not the document!
```

### Why Authorization Matters

- **Critical Security Risk:** CVSS Score 8.5 (High)
- **PII & Health Data:** Protects sensitive child development records
- **Compliance:** Required for GDPR, HIPAA, and data protection laws
- **Trust:** Users expect their data to remain private

### Authorization vs Authentication

| Concept | Question | Implementation |
|---------|----------|----------------|
| **Authentication** | "Who are you?" | JWT tokens, login flow |
| **Authorization** | "Can you access this?" | User ownership validation |

**All endpoints require BOTH** authentication and authorization.

---

## Quick Start Guide

### Adding Authorization to a New Endpoint (5 Steps)

**Step 1: Define UnauthorizedAccessError Exception**

```python
# In your_service.py

class YourServiceError(Exception):
    """Base exception for your service errors."""
    pass


class UnauthorizedAccessError(YourServiceError):
    """Raised when the user does not have permission to access a resource."""
    pass
```

**Step 2: Create Authorization Helper Method**

```python
def _user_has_resource_access(
    self,
    resource: YourModel,
    user_id: UUID,
) -> bool:
    """Check if a user has access to a resource.

    User has access if they are the owner/creator.

    Args:
        resource: The resource to check access for
        user_id: ID of the user to check

    Returns:
        True if user has access, False otherwise
    """
    # Example: Check if user is the creator
    return str(resource.created_by) == str(user_id)
```

**Step 3: Add Authorization Check to Service Method**

```python
async def get_resource_by_id(
    self,
    resource_id: UUID,
    user_id: UUID,  # ← Add this parameter
) -> YourModel:
    """Get a resource by ID."""
    # 1. Fetch resource
    query = select(YourModel).where(
        cast(YourModel.id, String) == str(resource_id)
    )
    result = await self.db.execute(query)
    resource = result.scalar_one_or_none()

    # 2. Check if found
    if not resource:
        raise ResourceNotFoundError(f"Resource {resource_id} not found")

    # 3. Check authorization
    if not self._user_has_resource_access(resource, user_id):
        raise UnauthorizedAccessError(
            "User does not have permission to access this resource"
        )

    # 4. Return if authorized
    return resource
```

**Step 4: Update Router to Pass user_id**

```python
# In your_router.py

from app.services.your_service import UnauthorizedAccessError

@router.get("/{resource_id}")
async def get_resource(
    resource_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict = Depends(get_current_user),
):
    """Get a resource by ID.

    Raises:
        HTTPException: 403 if unauthorized
        HTTPException: 404 if not found
    """
    try:
        # Extract user_id from JWT token
        user_id = UUID(current_user["sub"])

        # Call service with user_id
        service = YourService(db)
        resource = await service.get_resource_by_id(
            resource_id=resource_id,
            user_id=user_id,
        )

        return resource

    except UnauthorizedAccessError as e:
        # Return 403 Forbidden
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
```

**Step 5: Write Authorization Tests**

```python
# In tests/test_your_service_authorization.py

async def test_unauthorized_access(db_session):
    """Test that users cannot access other users' resources."""
    user_a_id = uuid4()
    user_b_id = uuid4()

    # Create resource owned by user A
    resource = YourModel(id=uuid4(), created_by=user_a_id)
    db_session.add(resource)
    await db_session.commit()

    # Try to access as user B
    service = YourService(db_session)

    with pytest.raises(UnauthorizedAccessError):
        await service.get_resource_by_id(
            resource_id=resource.id,
            user_id=user_b_id,  # Different user!
        )
```

---

## Authorization Pattern

### Pattern Components

The authorization pattern consists of four key components:

```
┌─────────────────────────────────────────────────────────────┐
│                    Authorization Pattern                     │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  1. UnauthorizedAccessError Exception                        │
│     └─ Custom exception for authorization failures           │
│                                                               │
│  2. _user_has_<resource>_access() Helper Method              │
│     └─ Encapsulates authorization logic                      │
│                                                               │
│  3. Service Method Authorization Checks                      │
│     └─ Fetch → Check Exists → Check Auth → Proceed           │
│                                                               │
│  4. Router Exception Handling                                │
│     └─ Catch UnauthorizedAccessError → Return 403            │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

### Authorization Flow

```
┌──────────────┐
│ HTTP Request │
└──────┬───────┘
       │
       ▼
┌──────────────────────────┐
│ Router                   │
│ • Extract user_id        │
│ • Call service method    │
└──────┬───────────────────┘
       │
       ▼
┌──────────────────────────┐
│ Service Method           │
│ 1. Fetch resource        │
│ 2. Check if exists       │  ─┐
│ 3. Check authorization   │   │ Authorization
│ 4. Raise if unauthorized │   │ Happens Here
│ 5. Proceed if authorized │  ─┘
└──────┬───────────────────┘
       │
       ▼
┌──────────────────────────┐
│ Authorization Helper     │
│ • _user_has_*_access()   │
│ • Returns bool           │
└──────┬───────────────────┘
       │
       ├─ True ─────────────────► Continue processing
       │
       └─ False ───► UnauthorizedAccessError ───► Router catches ───► 403 Forbidden
```

---

## Implementation Steps

### Step 1: Define Exception Class

**Location:** Top of service file, after other exceptions

```python
# =========================================================================
# Exceptions
# =========================================================================

class DocumentServiceError(Exception):
    """Base exception for document service errors."""
    pass


class UnauthorizedAccessError(DocumentServiceError):
    """Raised when the user does not have permission to access a resource."""
    pass
```

**Key Points:**
- Inherit from your service's base exception class
- Keep the error message generic in the class definition
- Use descriptive docstrings

---

### Step 2: Create Authorization Helper Method

**Location:** Private methods section (bottom of service class)

**Template:**

```python
def _user_has_<resource>_access(
    self,
    resource: ResourceModel,
    user_id: UUID,
) -> bool:
    """Check if a user has access to a resource.

    User has access if [describe criteria here].

    Args:
        resource: The resource to check access for
        user_id: ID of the user to check

    Returns:
        True if user has access, False otherwise
    """
    # Implementation depends on your authorization criteria
    # Common patterns below
```

**Common Authorization Criteria:**

#### Owner/Creator Pattern

```python
def _user_has_document_access(
    self,
    document: Document,
    user_id: UUID,
) -> bool:
    """User has access if they created the document."""
    return str(document.created_by) == str(user_id)
```

#### Assigned User Pattern

```python
def _user_has_profile_access(
    self,
    profile: DevelopmentProfile,
    user_id: UUID,
) -> bool:
    """User has access if they are the assigned educator."""
    if profile is None:
        return False

    return str(profile.educator_id) == str(user_id)
```

#### Participant Pattern

```python
def _user_has_thread_access(
    self,
    thread: MessageThread,
    user_id: UUID,
) -> bool:
    """User has access if they are creator or participant."""
    # Creator always has access
    if str(thread.created_by) == str(user_id):
        return True

    # Check if user is in participants list
    if thread.participants:
        for participant in thread.participants:
            if participant.get("user_id") == str(user_id):
                return True

    return False
```

#### Relationship Pattern

```python
def _user_has_child_access(
    self,
    child: Child,
    user_id: UUID,
) -> bool:
    """User has access if they are the parent."""
    # Check parent-child relationship
    query = select(ParentChild).where(
        ParentChild.child_id == child.id,
        cast(ParentChild.parent_id, String) == str(user_id),
    )
    result = await self.db.execute(query)
    relationship = result.scalar_one_or_none()

    return relationship is not None
```

---

### Step 3: Add Authorization to Service Methods

**Which methods need authorization?**

✅ **ALWAYS require authorization:**
- `get_*_by_id()` - Retrieving specific resources
- `update_*()` - Modifying resources
- `delete_*()` - Deleting resources
- Any method returning user-specific data

❌ **Usually DON'T require authorization:**
- `create_*()` - Creating new resources (user becomes owner)
- `list_*()` - Already filtered by user_id in query
- Public endpoints (rare in this system)

**Authorization Implementation Pattern:**

```python
async def get_resource_by_id(
    self,
    resource_id: UUID,
    user_id: UUID,  # ← ALWAYS add this parameter
) -> ResourceResponse:
    """Get a resource by ID.

    Args:
        resource_id: Unique identifier of the resource
        user_id: ID of the user requesting the resource

    Returns:
        ResourceResponse with the resource data

    Raises:
        ResourceNotFoundError: When the resource is not found
        UnauthorizedAccessError: When the user doesn't have access
    """
    # Step 1: Fetch the resource
    query = select(Resource).where(
        cast(Resource.id, String) == str(resource_id)
    )
    result = await self.db.execute(query)
    resource = result.scalar_one_or_none()

    # Step 2: Check if resource exists
    if not resource:
        raise ResourceNotFoundError(f"Resource with ID {resource_id} not found")

    # Step 3: Verify user has access to the resource
    if not self._user_has_resource_access(resource, user_id):
        raise UnauthorizedAccessError(
            "User does not have permission to access this resource"
        )

    # Step 4: Proceed with operation if authorized
    return await self._build_resource_response(resource)
```

**Update Methods:**

```python
async def update_resource(
    self,
    resource_id: UUID,
    user_id: UUID,  # ← Add this
    update_data: UpdateRequest,
) -> ResourceResponse:
    """Update a resource.

    This method leverages get_resource_by_id which performs authorization.
    """
    # get_resource_by_id checks authorization
    resource = await self.get_resource_by_id(
        resource_id=resource_id,
        user_id=user_id,  # ← Pass it through
    )

    # Proceed with update
    # ...
```

**Child Resource Authorization:**

For resources that belong to a parent resource (e.g., observations belong to profiles):

```python
async def get_observation_by_id(
    self,
    profile_id: UUID,
    observation_id: UUID,
    user_id: UUID,
) -> Observation:
    """Get observation - checks parent profile access."""
    # First verify user has access to parent profile
    profile = await self.get_profile_by_id(
        profile_id=profile_id,
        user_id=user_id,  # ← This raises UnauthorizedAccessError if no access
    )

    # Now fetch the child resource
    query = select(Observation).where(
        Observation.id == observation_id,
        Observation.profile_id == profile_id,
    )
    result = await self.db.execute(query)
    observation = result.scalar_one_or_none()

    if not observation:
        raise ObservationNotFoundError(f"Observation {observation_id} not found")

    return observation
```

---

### Step 4: Update Router to Handle Authorization

**Import the Exception:**

```python
from app.services.your_service import UnauthorizedAccessError
```

**Extract user_id from JWT:**

```python
user_id = UUID(current_user["sub"])
```

**Pass user_id to Service:**

```python
resource = await service.get_resource_by_id(
    resource_id=resource_id,
    user_id=user_id,  # ← Pass user_id
)
```

**Catch UnauthorizedAccessError:**

```python
try:
    # Service call
    resource = await service.get_resource_by_id(...)
    return resource

except UnauthorizedAccessError as e:
    raise HTTPException(
        status_code=status.HTTP_403_FORBIDDEN,
        detail=str(e),
    )
```

**Complete Router Example:**

```python
@router.get(
    "/{document_id}",
    response_model=DocumentResponse,
    summary="Get document by ID",
    description="Retrieve a single document by its unique identifier.",
)
async def get_document(
    document_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
) -> DocumentResponse:
    """Get a document by ID.

    Args:
        document_id: Unique identifier of the document.
        db: Async database session (injected).
        current_user: Authenticated user information (injected).

    Returns:
        DocumentResponse with document details.

    Raises:
        HTTPException: 404 if document not found.
        HTTPException: 403 if user does not have access to the document.
        HTTPException: 401 if not authenticated.
    """
    service = DocumentService(db)

    try:
        user_id = UUID(current_user["sub"])

        document = await service.get_document_by_id(
            document_id=document_id,
            user_id=user_id,
        )

        if document is None:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Document with id {document_id} not found",
            )

        return service._document_to_response(document)

    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
```

**Update Docstrings:**

Always document the 403 response in your endpoint docstrings:

```python
"""
Raises:
    HTTPException: 403 if user does not have access to the resource.
    HTTPException: 404 if resource not found.
    HTTPException: 401 if not authenticated.
"""
```

---

## Real-World Examples

### Example 1: Document Service

**Service Implementation:**

```python
# app/services/document_service.py

class UnauthorizedAccessError(DocumentServiceError):
    """Raised when the user does not have permission to access a resource."""
    pass


class DocumentService:

    async def get_document_by_id(
        self,
        document_id: UUID,
        user_id: UUID,
    ) -> Optional[Document]:
        """Get a document by ID with authorization check."""
        query = select(Document).where(
            cast(Document.id, String) == str(document_id)
        )
        result = await self.db.execute(query)
        document = result.scalar_one_or_none()

        if not document:
            return None

        # Check authorization
        if not self._user_has_document_access(document, user_id):
            raise UnauthorizedAccessError(
                "User does not have permission to access this document"
            )

        return document

    def _user_has_document_access(
        self,
        document: Document,
        user_id: UUID,
    ) -> bool:
        """Check if a user has access to a document."""
        # User has access if they created the document
        return str(document.created_by) == str(user_id)
```

**Router Implementation:**

```python
# app/routers/documents.py

from app.services.document_service import UnauthorizedAccessError

@router.get("/{document_id}")
async def get_document(
    document_id: UUID,
    db: AsyncSession = Depends(get_db),
    current_user: dict[str, Any] = Depends(get_current_user),
):
    try:
        user_id = UUID(current_user["sub"])

        service = DocumentService(db)
        document = await service.get_document_by_id(
            document_id=document_id,
            user_id=user_id,
        )

        if document is None:
            raise HTTPException(
                status_code=status.HTTP_404_NOT_FOUND,
                detail=f"Document with id {document_id} not found",
            )

        return service._document_to_response(document)

    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e),
        )
```

---

### Example 2: Development Profile Service

**Service Implementation:**

```python
# app/services/development_profile_service.py

class UnauthorizedAccessError(DevelopmentProfileServiceError):
    """Raised when the user does not have permission to access a resource."""
    pass


class DevelopmentProfileService:

    async def get_profile_by_id(
        self,
        profile_id: UUID,
        user_id: Optional[UUID] = None,
    ) -> Optional[DevelopmentProfile]:
        """Get a profile by ID with optional authorization.

        If user_id is None, skips authorization (for internal calls).
        """
        query = select(DevelopmentProfile).where(
            cast(DevelopmentProfile.id, String) == str(profile_id)
        )
        result = await self.db.execute(query)
        profile = result.scalar_one_or_none()

        if not profile:
            return None

        # Check authorization (skip if internal call)
        if user_id is not None:
            if not self._user_has_profile_access(profile, user_id):
                raise UnauthorizedAccessError(
                    "User does not have permission to access this profile"
                )

        return profile

    def _user_has_profile_access(
        self,
        profile: Optional[DevelopmentProfile],
        user_id: UUID,
    ) -> bool:
        """Check if a user has access to a development profile."""
        if profile is None:
            return False

        # User has access if they are the assigned educator
        return str(profile.educator_id) == str(user_id)
```

**Child Resource Authorization:**

```python
async def get_skill_assessment_by_id(
    self,
    profile_id: UUID,
    assessment_id: UUID,
    user_id: Optional[UUID] = None,
) -> Optional[SkillAssessment]:
    """Get skill assessment with parent profile authorization."""
    # First verify access to parent profile
    profile = await self.get_profile_by_id(
        profile_id=profile_id,
        user_id=user_id,  # ← Raises UnauthorizedAccessError if no access
    )

    if not profile:
        return None

    # Now fetch the assessment
    query = select(SkillAssessment).where(
        cast(SkillAssessment.id, String) == str(assessment_id),
        SkillAssessment.profile_id == profile.id,
    )
    result = await self.db.execute(query)
    return result.scalar_one_or_none()
```

---

## Testing Requirements

### Test Coverage Checklist

Every endpoint with authorization MUST have these tests:

- [ ] Owner/creator can access their own resource
- [ ] Non-owner cannot access other users' resources (raises UnauthorizedAccessError)
- [ ] Router returns 403 Forbidden for unauthorized access
- [ ] Router returns 404 Not Found for non-existent resources
- [ ] Authorization helper method works correctly
- [ ] Multiple users cannot access each other's resources (isolation test)

### Unit Test Template

```python
# tests/test_your_service_authorization.py

import pytest
from uuid import uuid4
from app.services.your_service import (
    YourService,
    UnauthorizedAccessError,
)
from app.models.your_model import YourModel


@pytest.mark.asyncio
async def test_owner_can_access_resource(db_session):
    """Test that resource owner can access their own resource."""
    user_id = uuid4()

    # Create resource owned by user
    resource = YourModel(
        id=uuid4(),
        created_by=user_id,
        title="Test Resource",
    )
    db_session.add(resource)
    await db_session.commit()

    # Access as owner
    service = YourService(db_session)
    result = await service.get_resource_by_id(
        resource_id=resource.id,
        user_id=user_id,
    )

    assert result is not None
    assert result.id == resource.id


@pytest.mark.asyncio
async def test_non_owner_cannot_access_resource(db_session):
    """Test that non-owner cannot access other users' resources."""
    owner_id = uuid4()
    other_user_id = uuid4()

    # Create resource owned by owner_id
    resource = YourModel(
        id=uuid4(),
        created_by=owner_id,
        title="Test Resource",
    )
    db_session.add(resource)
    await db_session.commit()

    # Try to access as different user
    service = YourService(db_session)

    with pytest.raises(UnauthorizedAccessError):
        await service.get_resource_by_id(
            resource_id=resource.id,
            user_id=other_user_id,  # ← Different user!
        )


@pytest.mark.asyncio
async def test_owner_can_update_resource(db_session):
    """Test that owner can update their own resource."""
    user_id = uuid4()

    resource = YourModel(
        id=uuid4(),
        created_by=user_id,
        title="Original Title",
    )
    db_session.add(resource)
    await db_session.commit()

    # Update as owner
    service = YourService(db_session)
    updated = await service.update_resource(
        resource_id=resource.id,
        user_id=user_id,
        update_data={"title": "New Title"},
    )

    assert updated.title == "New Title"


@pytest.mark.asyncio
async def test_non_owner_cannot_update_resource(db_session):
    """Test that non-owner cannot update other users' resources."""
    owner_id = uuid4()
    other_user_id = uuid4()

    resource = YourModel(
        id=uuid4(),
        created_by=owner_id,
        title="Original Title",
    )
    db_session.add(resource)
    await db_session.commit()

    # Try to update as different user
    service = YourService(db_session)

    with pytest.raises(UnauthorizedAccessError):
        await service.update_resource(
            resource_id=resource.id,
            user_id=other_user_id,  # ← Different user!
            update_data={"title": "Hacked Title"},
        )


@pytest.mark.asyncio
async def test_resource_isolation_between_users(db_session):
    """Test that multiple users cannot access each other's resources."""
    user_a_id = uuid4()
    user_b_id = uuid4()

    # Create resources for both users
    resource_a = YourModel(id=uuid4(), created_by=user_a_id, title="User A Resource")
    resource_b = YourModel(id=uuid4(), created_by=user_b_id, title="User B Resource")
    db_session.add_all([resource_a, resource_b])
    await db_session.commit()

    service = YourService(db_session)

    # User A can access their own resource
    result_a = await service.get_resource_by_id(resource_a.id, user_a_id)
    assert result_a.id == resource_a.id

    # User B can access their own resource
    result_b = await service.get_resource_by_id(resource_b.id, user_b_id)
    assert result_b.id == resource_b.id

    # User A cannot access User B's resource
    with pytest.raises(UnauthorizedAccessError):
        await service.get_resource_by_id(resource_b.id, user_a_id)

    # User B cannot access User A's resource
    with pytest.raises(UnauthorizedAccessError):
        await service.get_resource_by_id(resource_a.id, user_b_id)


@pytest.mark.asyncio
async def test_authorization_helper_method(db_session):
    """Test the authorization helper method directly."""
    user_id = uuid4()
    other_user_id = uuid4()

    resource = YourModel(id=uuid4(), created_by=user_id, title="Test")

    service = YourService(db_session)

    # Owner has access
    assert service._user_has_resource_access(resource, user_id) is True

    # Non-owner does not have access
    assert service._user_has_resource_access(resource, other_user_id) is False
```

### Integration Test Template

```python
# tests/test_idor_protection_integration.py

import pytest
from httpx import AsyncClient
from uuid import uuid4


@pytest.mark.asyncio
async def test_get_resource_idor_protection(client: AsyncClient, db_session):
    """Test that API returns 403 for unauthorized access attempts."""
    # Create two users with tokens
    user_a_id = uuid4()
    user_b_id = uuid4()

    user_a_token = create_test_token(user_a_id)
    user_b_token = create_test_token(user_b_id)

    # User A creates a resource
    response = await client.post(
        "/api/v1/resources",
        json={"title": "User A Resource"},
        headers={"Authorization": f"Bearer {user_a_token}"},
    )
    assert response.status_code == 201
    resource_id = response.json()["id"]

    # User A can access their own resource
    response = await client.get(
        f"/api/v1/resources/{resource_id}",
        headers={"Authorization": f"Bearer {user_a_token}"},
    )
    assert response.status_code == 200

    # User B cannot access User A's resource
    response = await client.get(
        f"/api/v1/resources/{resource_id}",
        headers={"Authorization": f"Bearer {user_b_token}"},
    )
    assert response.status_code == 403
    assert "permission" in response.json()["detail"].lower()
```

### Running Authorization Tests

```bash
cd ai-service

# Run all authorization tests
pytest tests/test_document_authorization.py -v
pytest tests/test_development_profile_authorization.py -v
pytest tests/test_idor_protection_integration.py -v

# Run specific test
pytest tests/test_document_authorization.py::test_non_creator_cannot_access_document -v

# Run with coverage
pytest tests/test_document_authorization.py --cov=app.services.document_service
```

---

## Common Pitfalls

### ❌ Pitfall 1: UUID Comparison Issues

**Problem:** UUID comparison fails due to type mismatch

```python
# ❌ WRONG - May fail if types don't match
if resource.created_by == user_id:
    return True
```

**Solution:** Always cast to string for comparison

```python
# ✅ CORRECT - Consistent string comparison
if str(resource.created_by) == str(user_id):
    return True
```

---

### ❌ Pitfall 2: Skipping Authorization in Service Layer

**Problem:** Only checking authorization in router

```python
# ❌ WRONG - Authorization only in router
@router.get("/{resource_id}")
async def get_resource(resource_id: UUID, current_user: dict):
    user_id = UUID(current_user["sub"])

    # Authorization check here only
    if resource.created_by != user_id:
        raise HTTPException(403)

    # Service has no authorization
    return await service.get_resource_by_id(resource_id)
```

**Solution:** Always enforce authorization in service layer

```python
# ✅ CORRECT - Authorization in service
async def get_resource_by_id(self, resource_id: UUID, user_id: UUID):
    resource = await self._fetch_resource(resource_id)

    # Authorization check in service
    if not self._user_has_resource_access(resource, user_id):
        raise UnauthorizedAccessError(...)

    return resource
```

**Why?** Service methods may be called from:
- Multiple routers
- Background jobs
- Internal service-to-service calls
- CLI commands

Authorization must be enforced at the service layer to protect all entry points.

---

### ❌ Pitfall 3: Information Leakage

**Problem:** Different error messages reveal resource existence

```python
# ❌ WRONG - Leaks information
if not resource:
    raise HTTPException(404, "Resource not found")

if resource.created_by != user_id:
    raise HTTPException(403, "You don't own this resource")  # Leaks existence!
```

**Solution:** Same error handling for not found and unauthorized

```python
# ✅ CORRECT - Consistent error handling
if not resource:
    raise ResourceNotFoundError("Resource not found")

if not self._user_has_resource_access(resource, user_id):
    raise UnauthorizedAccessError("Access denied")  # Generic message
```

---

### ❌ Pitfall 4: Forgetting Child Resource Authorization

**Problem:** Not checking parent resource access for nested resources

```python
# ❌ WRONG - Only checks if observation exists
async def get_observation(self, observation_id: UUID):
    return await self.db.get(Observation, observation_id)
```

**Solution:** Check parent resource authorization first

```python
# ✅ CORRECT - Checks profile access first
async def get_observation(self, profile_id: UUID, observation_id: UUID, user_id: UUID):
    # First verify access to parent profile
    profile = await self.get_profile_by_id(profile_id, user_id)

    # Then fetch child resource
    observation = await self._fetch_observation(observation_id, profile_id)
    return observation
```

---

### ❌ Pitfall 5: Not Testing Authorization

**Problem:** Implementing authorization but not testing it

```python
# Implementation exists but no tests!
# One code change could break authorization
```

**Solution:** Always write authorization tests

```python
# ✅ CORRECT - Comprehensive test coverage
async def test_non_owner_cannot_access():
    with pytest.raises(UnauthorizedAccessError):
        await service.get_resource(resource_id, other_user_id)
```

---

### ❌ Pitfall 6: Forgetting to Pass user_id

**Problem:** Adding authorization but forgetting to update callers

```python
# Service method updated
async def get_resource(self, resource_id: UUID, user_id: UUID):
    # Authorization check...

# ❌ WRONG - Router not updated
@router.get("/{resource_id}")
async def get_resource(resource_id: UUID):
    # Forgot to pass user_id!
    return await service.get_resource(resource_id)
```

**Solution:** Update all callers when adding user_id parameter

```python
# ✅ CORRECT - Router updated
@router.get("/{resource_id}")
async def get_resource(resource_id: UUID, current_user: dict = Depends(get_current_user)):
    user_id = UUID(current_user["sub"])
    return await service.get_resource(resource_id, user_id)
```

---

## Best Practices

### ✅ 1. Always Use the Helper Method Pattern

**Good:**
```python
if not self._user_has_resource_access(resource, user_id):
    raise UnauthorizedAccessError(...)
```

**Why?**
- Encapsulates authorization logic
- Easy to update authorization rules in one place
- Testable in isolation
- Consistent across codebase

---

### ✅ 2. Make user_id Required in Service Methods

**Good:**
```python
async def get_resource(
    self,
    resource_id: UUID,
    user_id: UUID,  # ← Required parameter
):
```

**Exception:** Internal service calls can use optional user_id:

```python
async def get_resource(
    self,
    resource_id: UUID,
    user_id: Optional[UUID] = None,  # ← Optional for internal calls
):
    # Skip authorization if user_id is None (internal call)
    if user_id is not None:
        if not self._user_has_resource_access(resource, user_id):
            raise UnauthorizedAccessError(...)
```

---

### ✅ 3. Document Authorization in Docstrings

**Good:**
```python
async def get_resource(self, resource_id: UUID, user_id: UUID):
    """Get a resource by ID.

    Args:
        resource_id: Unique identifier of the resource
        user_id: ID of the user requesting the resource

    Returns:
        Resource object

    Raises:
        ResourceNotFoundError: When resource doesn't exist
        UnauthorizedAccessError: When user doesn't have access  # ← Document this
    """
```

---

### ✅ 4. Test Both Success and Failure Cases

**Good:**
```python
# Test authorized access
async def test_owner_can_access():
    result = await service.get_resource(resource_id, owner_id)
    assert result is not None

# Test unauthorized access
async def test_non_owner_cannot_access():
    with pytest.raises(UnauthorizedAccessError):
        await service.get_resource(resource_id, other_user_id)
```

---

### ✅ 5. Handle None Resources Safely

**Good:**
```python
def _user_has_resource_access(
    self,
    resource: Optional[Resource],
    user_id: UUID,
) -> bool:
    """Check access - handles None safely."""
    if resource is None:
        return False  # ← Explicit None handling

    return str(resource.created_by) == str(user_id)
```

---

## Security Checklist

Use this checklist when adding authorization to a new endpoint:

### Service Layer
- [ ] `UnauthorizedAccessError` exception defined
- [ ] `_user_has_<resource>_access()` helper method implemented
- [ ] Authorization criteria clearly documented in helper docstring
- [ ] All `get_*_by_id()` methods have `user_id` parameter
- [ ] All `update_*()` methods have `user_id` parameter
- [ ] All `delete_*()` methods have `user_id` parameter
- [ ] Child resources check parent resource authorization
- [ ] Authorization check happens AFTER existence check
- [ ] `UnauthorizedAccessError` raised when access denied
- [ ] UUID comparisons use `str()` casting

### Router Layer
- [ ] `UnauthorizedAccessError` imported from service
- [ ] `user_id` extracted from `current_user["sub"]`
- [ ] `user_id` passed to all service method calls
- [ ] `try/except` block catches `UnauthorizedAccessError`
- [ ] 403 Forbidden returned for unauthorized access
- [ ] Endpoint docstring documents 403 response
- [ ] No authorization logic in router (all in service)

### Testing
- [ ] Test: Owner can access their own resource
- [ ] Test: Non-owner cannot access (raises `UnauthorizedAccessError`)
- [ ] Test: Owner can update their own resource
- [ ] Test: Non-owner cannot update (raises `UnauthorizedAccessError`)
- [ ] Test: Resource isolation between multiple users
- [ ] Test: Authorization helper method works correctly
- [ ] Integration test: API returns 403 for unauthorized access
- [ ] Integration test: API returns 200/404 for authorized access
- [ ] All authorization tests passing

### Documentation
- [ ] Authorization criteria documented in helper method
- [ ] Service method docstrings list `UnauthorizedAccessError`
- [ ] Router endpoint docstrings document 403 response
- [ ] Any special authorization rules documented

---

## Additional Resources

### Related Documentation
- `.auto-claude/specs/087-fix-idor-7-endpoints/authorization_pattern.md` - Detailed pattern definition
- `.auto-claude/specs/087-fix-idor-7-endpoints/security_audit_report.md` - Security audit results
- `tests/test_document_authorization.py` - Unit test examples
- `tests/test_idor_protection_integration.py` - Integration test examples

### Reference Implementations
- `app/services/document_service.py` - Document authorization (simple owner pattern)
- `app/services/development_profile_service.py` - Profile authorization (assigned user pattern)
- `app/services/messaging_service.py` - Thread authorization (participant pattern)

### Security Resources
- [OWASP IDOR Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Insecure_Direct_Object_Reference_Prevention_Cheat_Sheet.html)
- [CWE-639: Authorization Bypass Through User-Controlled Key](https://cwe.mitre.org/data/definitions/639.html)

---

## FAQ

### Q: Should I check authorization in the router or service?

**A:** Always in the service layer. Routers only extract `user_id` and handle exceptions. Authorization logic belongs in the service.

### Q: What if I need multiple authorization criteria (e.g., owner OR admin)?

**A:** Update the helper method to include all criteria:

```python
def _user_has_resource_access(self, resource, user_id) -> bool:
    # Owner has access
    if str(resource.created_by) == str(user_id):
        return True

    # Admin has access (future RBAC)
    if self._user_is_admin(user_id):
        return True

    return False
```

### Q: What about list endpoints that return multiple resources?

**A:** List endpoints should filter by `user_id` in the query:

```python
async def list_resources(self, user_id: UUID):
    """List resources - automatically filtered by user."""
    query = select(Resource).where(
        cast(Resource.created_by, String) == str(user_id)
    )
    # No authorization check needed - query already filtered
```

### Q: Should I return 403 or 404 for unauthorized access?

**A:** Return **403 Forbidden**. It's more explicit and helps with debugging. Returning 404 could hide authorization bugs.

### Q: What if the resource doesn't have a `created_by` field?

**A:** Use whatever field indicates ownership:
- `educator_id` for development profiles
- `owner_id` for generic ownership
- Check relationship tables for parent-child, user-group, etc.

### Q: How do I handle internal service-to-service calls?

**A:** Make `user_id` optional and skip authorization when `None`:

```python
async def get_resource(
    self,
    resource_id: UUID,
    user_id: Optional[UUID] = None,
):
    resource = await self._fetch_resource(resource_id)

    if not resource:
        raise ResourceNotFoundError(...)

    # Skip authorization for internal calls
    if user_id is not None:
        if not self._user_has_resource_access(resource, user_id):
            raise UnauthorizedAccessError(...)

    return resource
```

---

## Summary

### The Authorization Pattern in 30 Seconds

1. **Define Exception:** `class UnauthorizedAccessError(YourServiceError)`
2. **Create Helper:** `def _user_has_<resource>_access(resource, user_id) -> bool`
3. **Check in Service:** `if not self._user_has_access(...): raise UnauthorizedAccessError`
4. **Handle in Router:** `except UnauthorizedAccessError: raise HTTPException(403)`
5. **Test Everything:** Owner succeeds, non-owner raises exception, API returns 403

### Key Principles

- ✅ **Authorization is REQUIRED for all user-specific resources**
- ✅ **Enforce authorization in the SERVICE layer, not routers**
- ✅ **Use consistent helper method pattern across all services**
- ✅ **Always test both authorized and unauthorized access**
- ✅ **Return 403 Forbidden for unauthorized access attempts**

### Security First

IDOR vulnerabilities are **CRITICAL** security issues. When in doubt:
- **Add authorization** rather than skip it
- **Test thoroughly** before deploying
- **Follow the pattern** exactly as documented
- **Ask for review** if authorization logic is complex

---

**Questions?** Refer to reference implementations or security audit report.

**Last Updated:** 2026-02-17
**Maintained By:** Engineering Team
