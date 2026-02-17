# Authorization Patterns and Best Practices

**Version:** 1.0
**Last Updated:** 2026-02-17
**Applies To:** LAYA AI Service (FastAPI + SQLAlchemy)

---

## Table of Contents

1. [Overview](#overview)
2. [Architecture](#architecture)
3. [Core Components](#core-components)
4. [Service-Level Patterns](#service-level-patterns)
5. [Router-Level Patterns](#router-level-patterns)
6. [Authorization Rules](#authorization-rules)
7. [Best Practices](#best-practices)
8. [Testing Patterns](#testing-patterns)
9. [Security Considerations](#security-considerations)
10. [Examples](#examples)

---

## Overview

This document describes the authorization patterns implemented across the AI service to prevent Insecure Direct Object Reference (IDOR) vulnerabilities and ensure proper access control. The patterns follow a defense-in-depth approach with multiple layers of authorization checks.

### Key Principles

1. **Never Trust Client Input**: Always verify ownership and permissions server-side
2. **Fail Securely**: Default to denying access when in doubt
3. **Defense in Depth**: Implement authorization at multiple layers (router, service, database)
4. **Clear Error Messages**: Provide meaningful errors without leaking sensitive information
5. **Consistent Patterns**: Use the same authorization approach across all services

### Security Model

- **Authentication**: JWT tokens identify users and their roles
- **Authorization**: Resource ownership and role-based access control (RBAC)
- **Resource Ownership**: Users can only access resources they own or are explicitly granted access to
- **Role-Based Access**: Administrators and certain roles have broader access

---

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                         Router Layer                         │
│  - Extract user from JWT token                              │
│  - Pass user_id and user_role to service                    │
│  - Catch authorization exceptions                           │
│  - Return HTTP 403/404                                      │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                        Service Layer                         │
│  - Accept user_id and user_role as parameters               │
│  - Call authorization helper methods                        │
│  - Raise authorization exceptions                           │
│  - Perform business logic only after authorization          │
└─────────────────────────────────────────────────────────────┘
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                   Authorization Helpers                      │
│  - verify_resource_owner()                                  │
│  - verify_child_access()                                    │
│  - Service-specific helpers (_verify_*_access)              │
└─────────────────────────────────────────────────────────────┘
```

---

## Core Components

### 1. Exception Classes

Located in `app/auth/exceptions.py`, these exceptions are used throughout the application to signal authorization failures:

```python
# Base exception
class AuthorizationError(Exception):
    """Base exception for authorization errors."""
    pass

# Resource not found (404)
class ResourceNotFoundError(AuthorizationError):
    """Raised when the requested resource is not found."""
    pass

# Access denied (403)
class UnauthorizedAccessError(AuthorizationError):
    """Raised when the user does not have permission to access a resource."""
    pass

# Permission denied (403)
class ForbiddenError(AuthorizationError):
    """Raised when access to a resource is forbidden."""
    pass

# Ownership check failed (403)
class OwnershipVerificationError(AuthorizationError):
    """Raised when resource ownership verification fails."""
    pass
```

**Usage Guidelines:**
- Use `ResourceNotFoundError` when a resource doesn't exist (maps to HTTP 404)
- Use `UnauthorizedAccessError` when user lacks permission (maps to HTTP 403)
- Use `ForbiddenError` for role-based access denials (maps to HTTP 403)
- All services should define their own service-specific exception classes that inherit from these base classes

### 2. Authorization Helper Functions

Located in `app/auth/dependencies.py`:

#### `verify_resource_owner()`

Generic helper for verifying resource ownership:

```python
async def verify_resource_owner(
    db: AsyncSession,
    model: Type[ModelType],
    resource_id: UUID,
    user_id: UUID,
    owner_field: str = "owner_id",
    resource_name: str = "Resource",
) -> ModelType:
    """Verify that a user owns a specific resource.

    Args:
        db: Async database session
        model: SQLAlchemy model class to query
        resource_id: UUID of the resource to verify
        user_id: UUID of the user claiming ownership
        owner_field: Name of the ownership field in the model
        resource_name: Human-readable resource name for error messages

    Returns:
        The resource instance if ownership is verified

    Raises:
        ResourceNotFoundError: When the resource is not found
        UnauthorizedAccessError: When the user does not own the resource
    """
```

**When to Use:**
- Simple ownership verification where one user owns one resource
- Resources with a single owner field (owner_id, created_by, user_id, etc.)
- Cases where role-based access is NOT needed

**Example:**
```python
# Verify document ownership
document = await verify_resource_owner(
    db=self.db,
    model=Document,
    resource_id=document_id,
    user_id=current_user_id,
    owner_field="created_by",
    resource_name="Document"
)
```

#### `verify_child_access()`

Specialized helper for child-related resources with role-based access:

```python
async def verify_child_access(
    db: AsyncSession,
    child_id: UUID,
    user_id: UUID,
    user_role: str,
    allow_educators: bool = True,
) -> bool:
    """Verify that a user has access to a child's data.

    Access rules:
    - Admins: Always have access
    - Educators/Teachers: Have access if allow_educators=True
    - Parents: Have access only to their own children

    Args:
        db: Async database session
        child_id: UUID of the child
        user_id: UUID of the user requesting access
        user_role: Role of the user (from JWT token)
        allow_educators: Whether educators/teachers should have access

    Returns:
        True if user has access

    Raises:
        UnauthorizedAccessError: When the user does not have access
    """
```

**When to Use:**
- Any resource associated with a child (profiles, care tracking, development records, etc.)
- Resources where educators and admins need broader access
- Parent-child relationship validation

**Example:**
```python
# Verify parent can access child's profile
await verify_child_access(
    db=self.db,
    child_id=profile.child_id,
    user_id=current_user_id,
    user_role=current_user_role,
    allow_educators=True
)
```

### 3. JWT Token Dependencies

Located in `app/auth/dependencies.py`:

```python
async def get_current_user(
    credentials: HTTPAuthorizationCredentials = Depends(security),
    db: AsyncSession = Depends(get_db),
) -> dict[str, Any]:
    """Extract and validate JWT token, return user payload."""

def require_role(*allowed_roles: UserRole) -> Callable:
    """Create a dependency that requires specific roles."""
```

**Usage:**
```python
# Get current user in any endpoint
@router.get("/profile")
async def get_profile(current_user: dict = Depends(get_current_user)):
    user_id = UUID(current_user["sub"])
    user_role = current_user["role"]

# Require specific role
@router.delete("/admin/users/{user_id}")
async def delete_user(
    user_id: str,
    current_user: dict = Depends(require_role(UserRole.ADMIN))
):
    pass
```

---

## Service-Level Patterns

### Pattern 1: Define Service-Specific Exceptions

Every service should define its own exception classes:

```python
# app/services/document_service.py

class DocumentServiceError(Exception):
    """Base exception for document service errors."""
    pass

class DocumentNotFoundError(DocumentServiceError):
    """Raised when the specified document is not found."""
    pass

class UnauthorizedAccessError(DocumentServiceError):
    """Raised when the user does not have permission to access a resource."""
    pass

class TemplateNotFoundError(DocumentServiceError):
    """Raised when the specified template is not found."""
    pass
```

**Why?**
- Service-specific exceptions are easier to catch and handle
- Allows for service-specific error messages
- Maintains separation of concerns

### Pattern 2: Create Authorization Helper Methods

Each service should have private helper methods for authorization checks:

```python
class DocumentService:

    def _verify_document_access(
        self,
        document: Document,
        user_id: UUID,
    ) -> bool:
        """Verify if a user has access to a document.

        User has access if they are the creator of the document.

        Args:
            document: The document to check access for
            user_id: User requesting access

        Returns:
            True if user has access

        Raises:
            UnauthorizedAccessError: If user lacks access
        """
        if str(document.created_by) != str(user_id):
            raise UnauthorizedAccessError(
                "User does not have permission to access this document"
            )
        return True

    def _verify_template_access(
        self,
        template: DocumentTemplate,
        user_id: UUID,
    ) -> bool:
        """Verify if a user has access to a document template."""
        if str(template.created_by) != str(user_id):
            raise UnauthorizedAccessError(
                "User does not have permission to access this template"
            )
        return True
```

**Naming Convention:**
- Prefix with underscore (`_`) to indicate private/internal method
- Use descriptive names: `_verify_{resource}_access`
- Return `bool` or raise exception
- Include docstring explaining access rules

### Pattern 3: Add user_id and user_role Parameters

All service methods that access user-specific resources must accept authentication parameters:

```python
# ❌ BAD - No authorization parameters
async def get_document(self, document_id: UUID) -> DocumentResponse:
    document = await self._get_document_by_id(document_id)
    return self._to_response(document)

# ✅ GOOD - Includes user parameters
async def get_document(
    self,
    document_id: UUID,
    user_id: UUID,
    user_role: Optional[str] = None,
) -> DocumentResponse:
    document = await self._get_document_by_id(document_id)
    self._verify_document_access(document, user_id)
    return self._to_response(document)
```

**Guidelines:**
- Always require `user_id: UUID` parameter
- Include `user_role: Optional[str]` for role-based access
- Place auth parameters after resource identifiers
- Verify authorization BEFORE performing any business logic

### Pattern 4: Query Filtering for List Operations

For list/query operations, filter by user ownership:

```python
async def list_documents(
    self,
    user_id: UUID,
    skip: int = 0,
    limit: int = 50,
) -> List[DocumentResponse]:
    """List documents owned by the user."""

    # Query filters by user_id automatically
    query = select(Document).where(
        cast(Document.created_by, String) == str(user_id)
    ).offset(skip).limit(limit)

    result = await self.db.execute(query)
    documents = result.scalars().all()

    return [self._to_response(doc) for doc in documents]
```

**Why?**
- Prevents users from discovering other users' resource IDs
- Ensures users only see their own data
- Simplifies authorization logic (no per-item checks needed)

### Pattern 5: Authorization-First Approach

Always check authorization BEFORE executing business logic:

```python
async def update_document(
    self,
    document_id: UUID,
    update_data: DocumentUpdate,
    user_id: UUID,
) -> DocumentResponse:
    # 1. Fetch the resource
    document = await self._get_document_by_id(document_id)

    # 2. Verify authorization FIRST
    self._verify_document_access(document, user_id)

    # 3. Perform business logic ONLY after authorization passes
    document.title = update_data.title
    document.content = update_data.content
    document.updated_at = datetime.utcnow()

    await self.db.commit()
    await self.db.refresh(document)

    return self._to_response(document)
```

**Key Points:**
- Fetch resource first to check if it exists
- Verify authorization before any modifications
- Raise exceptions early to prevent wasted computation
- Never perform side effects before authorization

---

## Router-Level Patterns

### Pattern 1: Extract User Information from JWT

Every protected endpoint should extract user credentials:

```python
from app.auth.dependencies import get_current_user

@router.get("/documents/{document_id}")
async def get_document(
    document_id: UUID,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    # Extract user ID and role
    user_id = UUID(current_user["sub"])
    user_role = current_user.get("role")

    # Pass to service
    service = DocumentService(db)
    return await service.get_document(document_id, user_id, user_role)
```

### Pattern 2: Handle Authorization Exceptions

Catch service exceptions and convert to HTTP responses:

```python
from fastapi import HTTPException, status
from app.services.document_service import (
    DocumentService,
    DocumentNotFoundError,
    UnauthorizedAccessError,
)

@router.get("/documents/{document_id}")
async def get_document(
    document_id: UUID,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    user_id = UUID(current_user["sub"])
    service = DocumentService(db)

    try:
        return await service.get_document(document_id, user_id)

    except DocumentNotFoundError as e:
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail=str(e)
        )

    except UnauthorizedAccessError as e:
        raise HTTPException(
            status_code=status.HTTP_403_FORBIDDEN,
            detail=str(e)
        )
```

**HTTP Status Code Mapping:**
- `ResourceNotFoundError` → `404 Not Found`
- `UnauthorizedAccessError` → `403 Forbidden`
- `ForbiddenError` → `403 Forbidden`
- `OwnershipVerificationError` → `403 Forbidden`

### Pattern 3: Security Through Obscurity for Sensitive Resources

For highly sensitive resources, return 404 instead of 403 to avoid leaking resource existence:

```python
@router.get("/storage/files/{file_id}")
async def get_file(
    file_id: UUID,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    user_id = UUID(current_user["sub"])
    service = StorageService(db)

    try:
        return await service.get_file(file_id, user_id)

    except (FileNotFoundError, UnauthorizedAccessError):
        # Return 404 for both cases to avoid leaking file existence
        raise HTTPException(
            status_code=status.HTTP_404_NOT_FOUND,
            detail="File not found"
        )
```

**When to Use:**
- Financial records (invoices, payments)
- Medical records
- Storage files
- Any resource where knowing it exists is sensitive

---

## Authorization Rules

### Resource Ownership Rules

| Resource Type | Owner Field | Access Rules |
|--------------|-------------|--------------|
| Documents | `created_by` | Creator only |
| Templates | `created_by` | Creator + Admins |
| Messages | `sender_id` | Thread participants only |
| Threads | `created_by` | Creator + Participants |
| Notification Preferences | `parent_id` | Parent owner + Admins/Directors |
| Files | `uploaded_by` | Owner + Public files accessible to all |
| Development Profiles | `child_id` | Parents of child + Educators + Admins |
| Intervention Plans | `child_id` | Parents of child + Educators + Admins |
| Communication Preferences | `parent_id` | Parent owner only |

### Role-Based Access Rules

| Role | Access Level | Special Permissions |
|------|-------------|-------------------|
| Admin | Full access | Can access all resources |
| Director | Broad access | Can access educator and parent resources |
| Educator | Limited access | Can access assigned children's data |
| Teacher | Limited access | Can access assigned children's data |
| Parent | Restricted | Can only access own children's data |

### Child Data Access Matrix

| User Role | Can Access Child Data? | Verification Method |
|-----------|------------------------|-------------------|
| Admin | ✅ Always | Role check only |
| Director | ✅ Always (if allowed) | Role check + allow_educators flag |
| Educator | ✅ Always (if allowed) | Role check + allow_educators flag |
| Teacher | ✅ Always (if allowed) | Role check + allow_educators flag |
| Parent | ✅ Only their children | Parent-child relationship lookup |
| Other | ❌ Never | Deny by default |

---

## Best Practices

### 1. Always Validate Ownership

**❌ BAD:**
```python
# Trusts resource_id from client without verification
async def delete_document(self, document_id: UUID):
    await self.db.delete(Document).where(Document.id == document_id)
    await self.db.commit()
```

**✅ GOOD:**
```python
async def delete_document(self, document_id: UUID, user_id: UUID):
    document = await self._get_document_by_id(document_id)
    self._verify_document_access(document, user_id)
    await self.db.delete(document)
    await self.db.commit()
```

### 2. Filter Queries by User

**❌ BAD:**
```python
# Returns all documents regardless of ownership
async def list_documents(self):
    query = select(Document).limit(50)
    result = await self.db.execute(query)
    return result.scalars().all()
```

**✅ GOOD:**
```python
async def list_documents(self, user_id: UUID, limit: int = 50):
    query = select(Document).where(
        cast(Document.created_by, String) == str(user_id)
    ).limit(limit)
    result = await self.db.execute(query)
    return result.scalars().all()
```

### 3. Verify Before Modifying

**❌ BAD:**
```python
# Updates without checking ownership
async def update_document(self, document_id: UUID, title: str):
    stmt = update(Document).where(
        cast(Document.id, String) == str(document_id)
    ).values(title=title)
    await self.db.execute(stmt)
    await self.db.commit()
```

**✅ GOOD:**
```python
async def update_document(
    self, document_id: UUID, title: str, user_id: UUID
):
    document = await self._get_document_by_id(document_id)
    self._verify_document_access(document, user_id)
    document.title = title
    await self.db.commit()
```

### 4. Use Type-Safe UUIDs

**❌ BAD:**
```python
# Comparing string to UUID can cause issues
if document.created_by == user_id:
    pass
```

**✅ GOOD:**
```python
# Always convert to string for comparison
if str(document.created_by) == str(user_id):
    pass
```

### 5. Provide Clear Error Messages

**❌ BAD:**
```python
raise UnauthorizedAccessError("Access denied")
```

**✅ GOOD:**
```python
raise UnauthorizedAccessError(
    "User does not have permission to access this document"
)
```

**⚠️ CAUTION:** Don't leak sensitive information in error messages:
```python
# DON'T expose owner information
raise UnauthorizedAccessError(
    f"Document owned by user {document.owner_id}, you are {user_id}"
)
```

### 6. Handle Related Resources

When a resource has related resources, verify access to all of them:

```python
async def add_signature_to_document(
    self,
    document_id: UUID,
    signature_data: SignatureCreate,
    user_id: UUID,
):
    # Verify access to document
    document = await self._get_document_by_id(document_id)
    self._verify_document_access(document, user_id)

    # Verify access to signature request (if referenced)
    if signature_data.request_id:
        request = await self._get_signature_request_by_id(
            signature_data.request_id
        )
        self._verify_signature_request_access(request, user_id)

    # Proceed with operation
    signature = Signature(**signature_data.dict(), document_id=document_id)
    self.db.add(signature)
    await self.db.commit()
```

### 7. Use Role Hierarchy

Implement role checks that respect role hierarchy:

```python
def _user_has_admin_access(self, user_role: str) -> bool:
    """Check if user has admin-level access."""
    admin_roles = ["admin", "administrator", "director"]
    return user_role.lower() in admin_roles

def _verify_template_access(
    self,
    template: DocumentTemplate,
    user_id: UUID,
    user_role: Optional[str] = None,
):
    """Verify template access (creator or admin)."""

    # Admins can access all templates
    if user_role and self._user_has_admin_access(user_role):
        return True

    # Otherwise, must be the creator
    if str(template.created_by) != str(user_id):
        raise UnauthorizedAccessError(
            "User does not have permission to access this template"
        )

    return True
```

### 8. Consistent Exception Handling

Use consistent exception handling across all routers:

```python
# Create a reusable pattern
try:
    result = await service.method(resource_id, user_id)
    return result
except ResourceNotFoundError as e:
    raise HTTPException(status_code=404, detail=str(e))
except UnauthorizedAccessError as e:
    raise HTTPException(status_code=403, detail=str(e))
except ServiceError as e:
    raise HTTPException(status_code=500, detail=str(e))
```

---

## Testing Patterns

### 1. Unit Tests for Authorization Helpers

Test each authorization helper method:

```python
# tests/test_document_authorization.py

import pytest
from uuid import uuid4
from app.services.document_service import DocumentService, UnauthorizedAccessError

@pytest.mark.asyncio
async def test_verify_document_access_owner(db_session):
    """Test that document owner has access."""
    service = DocumentService(db_session)
    user_id = uuid4()

    # Create document owned by user
    document = Document(id=uuid4(), created_by=user_id, title="Test")
    db_session.add(document)
    await db_session.commit()

    # Should not raise exception
    assert service._verify_document_access(document, user_id)

@pytest.mark.asyncio
async def test_verify_document_access_non_owner(db_session):
    """Test that non-owner cannot access document."""
    service = DocumentService(db_session)
    owner_id = uuid4()
    other_user_id = uuid4()

    # Create document owned by owner_id
    document = Document(id=uuid4(), created_by=owner_id, title="Test")
    db_session.add(document)
    await db_session.commit()

    # Should raise UnauthorizedAccessError
    with pytest.raises(UnauthorizedAccessError):
        service._verify_document_access(document, other_user_id)
```

### 2. Integration Tests for IDOR Protection

Test complete request flows:

```python
# tests/integration/test_idor_protection.py

@pytest.mark.asyncio
async def test_cannot_access_other_user_document(
    client, db_session, auth_headers_user1, auth_headers_user2
):
    """Test that user cannot access another user's document."""

    # User 1 creates a document
    response = client.post(
        "/api/v1/documents",
        json={"title": "User 1 Doc", "content": "Private"},
        headers=auth_headers_user1,
    )
    assert response.status_code == 201
    document_id = response.json()["id"]

    # User 2 attempts to access User 1's document
    response = client.get(
        f"/api/v1/documents/{document_id}",
        headers=auth_headers_user2,
    )

    # Should return 403 Forbidden
    assert response.status_code == 403
    assert "permission" in response.json()["detail"].lower()
```

### 3. Negative Test Cases

Create tests for unauthorized access scenarios:

```python
@pytest.mark.asyncio
async def test_parent_cannot_access_other_parent_child(db_session):
    """Test that parent cannot access another parent's child data."""

    parent1_id = uuid4()
    parent2_id = uuid4()
    child1_id = uuid4()
    child2_id = uuid4()

    # Parent 1 linked to Child 1
    # Parent 2 linked to Child 2

    # Parent 1 tries to access Child 2's data
    with pytest.raises(UnauthorizedAccessError):
        await verify_child_access(
            db=db_session,
            child_id=child2_id,
            user_id=parent1_id,
            user_role="parent"
        )
```

### 4. Role-Based Access Tests

Test role hierarchy and permissions:

```python
@pytest.mark.asyncio
async def test_admin_can_access_all_documents(client, admin_auth_headers):
    """Test that admin can access any document."""

    # Create document as regular user
    user_id = uuid4()
    document = Document(id=uuid4(), created_by=user_id, title="Test")
    # ... save document

    # Admin should be able to access it
    response = client.get(
        f"/api/v1/documents/{document.id}",
        headers=admin_auth_headers,
    )

    assert response.status_code == 200

@pytest.mark.asyncio
async def test_educator_can_access_child_data(db_session):
    """Test that educator can access child data."""

    educator_id = uuid4()
    child_id = uuid4()

    # Should not raise exception
    assert await verify_child_access(
        db=db_session,
        child_id=child_id,
        user_id=educator_id,
        user_role="educator",
        allow_educators=True
    )
```

---

## Security Considerations

### 1. IDOR Prevention Checklist

- [ ] All endpoints that accept resource IDs verify ownership
- [ ] List operations filter by user_id
- [ ] Authorization checks happen before business logic
- [ ] Services accept user_id parameter for all operations
- [ ] Routers extract user from JWT and pass to services
- [ ] Authorization exceptions are caught and converted to HTTP errors
- [ ] Error messages don't leak sensitive information
- [ ] Tests cover unauthorized access scenarios

### 2. Common IDOR Vulnerabilities

**Vulnerable Pattern: Direct ID Access**
```python
# ❌ Allows accessing any document by ID
@router.get("/documents/{document_id}")
async def get_document(document_id: UUID, db: AsyncSession = Depends(get_db)):
    query = select(Document).where(Document.id == document_id)
    result = await db.execute(query)
    document = result.scalar_one_or_none()
    if not document:
        raise HTTPException(status_code=404, detail="Not found")
    return document
```

**Secure Pattern: Ownership Verification**
```python
# ✅ Verifies user owns the document
@router.get("/documents/{document_id}")
async def get_document(
    document_id: UUID,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    service = DocumentService(db)
    user_id = UUID(current_user["sub"])

    try:
        return await service.get_document(document_id, user_id)
    except UnauthorizedAccessError:
        raise HTTPException(status_code=403, detail="Access denied")
```

### 3. Information Leakage Prevention

**Don't leak resource existence:**
```python
# ❌ BAD - Reveals document exists but user can't access it
if not user_owns_document:
    raise HTTPException(
        status_code=403,
        detail=f"Document {document_id} exists but you can't access it"
    )

# ✅ GOOD - Generic message
if not user_owns_document:
    raise HTTPException(
        status_code=403,
        detail="User does not have permission to access this document"
    )
```

**For sensitive resources, use 404:**
```python
# ✅ BEST - Returns 404 for both not found and unauthorized
try:
    return await service.get_medical_record(record_id, user_id)
except (RecordNotFoundError, UnauthorizedAccessError):
    raise HTTPException(status_code=404, detail="Record not found")
```

### 4. Token Security

**Always validate tokens:**
```python
# JWT token is validated by get_current_user dependency
# Never skip this validation
@router.get("/secure-endpoint")
async def secure_endpoint(
    current_user: dict = Depends(get_current_user)  # Required!
):
    pass
```

**Check token hasn't been revoked:**
```python
# get_current_user automatically checks token blacklist
# Implemented in app/auth/jwt.py verify_token()
```

### 5. SQL Injection Prevention

**Always use parameterized queries:**
```python
# ✅ GOOD - SQLAlchemy prevents SQL injection
query = select(Document).where(
    cast(Document.created_by, String) == str(user_id)
)

# ❌ BAD - String formatting is vulnerable
query = f"SELECT * FROM documents WHERE created_by = '{user_id}'"
```

---

## Examples

### Example 1: Simple Resource with Single Owner

**Service Implementation:**
```python
class DocumentService:
    def __init__(self, db: AsyncSession):
        self.db = db

    def _verify_document_access(self, document: Document, user_id: UUID) -> bool:
        """Verify user owns the document."""
        if str(document.created_by) != str(user_id):
            raise UnauthorizedAccessError(
                "User does not have permission to access this document"
            )
        return True

    async def get_document(
        self, document_id: UUID, user_id: UUID
    ) -> DocumentResponse:
        """Get a document by ID with ownership verification."""
        # Fetch document
        query = select(Document).where(
            cast(Document.id, String) == str(document_id)
        )
        result = await self.db.execute(query)
        document = result.scalar_one_or_none()

        if not document:
            raise DocumentNotFoundError(
                f"Document with ID {document_id} not found"
            )

        # Verify ownership
        self._verify_document_access(document, user_id)

        return self._to_response(document)
```

**Router Implementation:**
```python
@router.get("/documents/{document_id}", response_model=DocumentResponse)
async def get_document(
    document_id: UUID,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """Get a document by ID."""
    user_id = UUID(current_user["sub"])
    service = DocumentService(db)

    try:
        return await service.get_document(document_id, user_id)
    except DocumentNotFoundError as e:
        raise HTTPException(status_code=404, detail=str(e))
    except UnauthorizedAccessError as e:
        raise HTTPException(status_code=403, detail=str(e))
```

### Example 2: Child-Related Resource with Role-Based Access

**Service Implementation:**
```python
class DevelopmentProfileService:
    def __init__(self, db: AsyncSession):
        self.db = db

    async def _verify_profile_access(
        self,
        profile: DevelopmentProfile,
        user_id: UUID,
        user_role: str,
    ) -> bool:
        """Verify user can access the profile."""
        from app.auth.dependencies import verify_child_access

        # Use shared child access verification
        await verify_child_access(
            db=self.db,
            child_id=profile.child_id,
            user_id=user_id,
            user_role=user_role,
            allow_educators=True,
        )
        return True

    async def get_profile(
        self,
        profile_id: UUID,
        user_id: UUID,
        user_role: str,
    ) -> ProfileResponse:
        """Get a development profile with authorization."""
        # Fetch profile
        query = select(DevelopmentProfile).where(
            cast(DevelopmentProfile.id, String) == str(profile_id)
        )
        result = await self.db.execute(query)
        profile = result.scalar_one_or_none()

        if not profile:
            raise ProfileNotFoundError(f"Profile {profile_id} not found")

        # Verify access (checks parent-child relationship and roles)
        await self._verify_profile_access(profile, user_id, user_role)

        return self._to_response(profile)
```

**Router Implementation:**
```python
@router.get("/profiles/{profile_id}", response_model=ProfileResponse)
async def get_profile(
    profile_id: UUID,
    current_user: dict = Depends(get_current_user),
    db: AsyncSession = Depends(get_db),
):
    """Get a development profile."""
    user_id = UUID(current_user["sub"])
    user_role = current_user["role"]
    service = DevelopmentProfileService(db)

    try:
        return await service.get_profile(profile_id, user_id, user_role)
    except ProfileNotFoundError as e:
        raise HTTPException(status_code=404, detail=str(e))
    except UnauthorizedAccessError as e:
        raise HTTPException(status_code=403, detail=str(e))
```

### Example 3: Resource with Participant-Based Access

**Service Implementation:**
```python
class MessagingService:
    def _user_has_thread_access(
        self, thread: MessageThread, user_id: UUID
    ) -> bool:
        """Check if user is a thread participant."""
        # Creator has access
        if str(thread.created_by) == str(user_id):
            return True

        # Check if user is in participants
        if thread.participants:
            for participant in thread.participants:
                if participant.get("user_id") == str(user_id):
                    return True

        return False

    async def get_thread(
        self, thread_id: UUID, user_id: UUID
    ) -> ThreadResponse:
        """Get a thread with participant verification."""
        # Fetch thread
        query = select(MessageThread).where(
            cast(MessageThread.id, String) == str(thread_id)
        )
        result = await self.db.execute(query)
        thread = result.scalar_one_or_none()

        if not thread:
            raise ThreadNotFoundError(f"Thread {thread_id} not found")

        # Verify user is a participant
        if not self._user_has_thread_access(thread, user_id):
            raise UnauthorizedAccessError(
                "User does not have permission to access this thread"
            )

        return self._to_response(thread)
```

### Example 4: Admin Override Pattern

**Service Implementation:**
```python
class StorageService:
    def _user_has_admin_access(self, user_role: str) -> bool:
        """Check if user has admin access."""
        return user_role.lower() in ["admin", "administrator", "director"]

    def _verify_file_access(
        self,
        file: StorageFile,
        user_id: UUID,
        user_role: Optional[str] = None,
    ) -> bool:
        """Verify user can access the file."""

        # Admins can access all files
        if user_role and self._user_has_admin_access(user_role):
            return True

        # Public files are accessible to everyone
        if file.is_public:
            return True

        # Otherwise, must be the uploader
        if str(file.uploaded_by) != str(user_id):
            raise UnauthorizedAccessError(
                "User does not have permission to access this file"
            )

        return True
```

---

## Migration Guide

### Adding Authorization to Existing Endpoints

**Step 1: Update Service Method Signature**
```python
# Before
async def get_document(self, document_id: UUID):
    pass

# After
async def get_document(self, document_id: UUID, user_id: UUID):
    pass
```

**Step 2: Add Authorization Helper**
```python
def _verify_document_access(self, document: Document, user_id: UUID) -> bool:
    if str(document.created_by) != str(user_id):
        raise UnauthorizedAccessError("Access denied")
    return True
```

**Step 3: Use Helper in Method**
```python
async def get_document(self, document_id: UUID, user_id: UUID):
    document = await self._get_document_by_id(document_id)
    self._verify_document_access(document, user_id)  # Add this
    return self._to_response(document)
```

**Step 4: Update Router**
```python
@router.get("/documents/{document_id}")
async def get_document(
    document_id: UUID,
    current_user: dict = Depends(get_current_user),  # Add this
    db: AsyncSession = Depends(get_db),
):
    user_id = UUID(current_user["sub"])  # Add this
    service = DocumentService(db)

    try:
        return await service.get_document(document_id, user_id)  # Pass user_id
    except UnauthorizedAccessError as e:
        raise HTTPException(status_code=403, detail=str(e))
```

---

## Conclusion

Following these authorization patterns ensures:

1. **Security**: IDOR vulnerabilities are prevented
2. **Consistency**: All services follow the same patterns
3. **Maintainability**: Authorization logic is centralized and reusable
4. **Testability**: Authorization can be unit tested independently
5. **Auditability**: Clear authorization checks are easy to review

For questions or clarifications, refer to:
- Security Audit: `ai-service/IDOR_SECURITY_AUDIT.md`
- Exception Classes: `app/auth/exceptions.py`
- Authorization Helpers: `app/auth/dependencies.py`
- Example Services: `app/services/messaging_service.py`, `app/services/document_service.py`

**Last Updated:** 2026-02-17
**Maintained By:** AI Service Security Team
