# Authentication Module

This module provides comprehensive authentication and authorization functionality for the LAYA AI Service.

## Features

- JWT-based authentication with access and refresh tokens
- Role-based access control (RBAC)
- Password hashing with bcrypt
- Password reset workflow
- Token blacklisting for logout
- FastAPI dependency injection for protected routes

## User Roles

The system supports five user roles:

- `ADMIN` - System administrator with full access
- `TEACHER` - Teacher with access to classroom features
- `PARENT` - Parent with access to their child's information
- `ACCOUNTANT` - Accountant with access to financial features
- `STAFF` - Staff member with limited access

## Role-Based Access Control

### Using `@require_role` Decorator

The `require_role` function is a dependency factory that creates FastAPI dependencies for protecting endpoints based on user roles.

#### Basic Usage - Single Role

Require a specific role for endpoint access:

```python
from fastapi import APIRouter, Depends
from app.auth.dependencies import require_role
from app.auth.models import UserRole

router = APIRouter()

@router.delete("/users/{user_id}")
async def delete_user(
    user_id: str,
    current_user: dict = Depends(require_role(UserRole.ADMIN))
):
    """Only admins can delete users."""
    return {"message": f"User {user_id} deleted"}
```

#### Multiple Roles

Allow access to users with any of several roles:

```python
@router.get("/reports")
async def get_financial_reports(
    current_user: dict = Depends(
        require_role(UserRole.ADMIN, UserRole.ACCOUNTANT)
    )
):
    """Admins and accountants can view financial reports."""
    return {"reports": [...]}
```

#### Protecting Entire Routers

Apply role requirements to all endpoints in a router:

```python
from fastapi import APIRouter, Depends
from app.auth.dependencies import require_role
from app.auth.models import UserRole

# All endpoints in this router require admin role
router = APIRouter(
    prefix="/admin",
    tags=["admin"],
    dependencies=[Depends(require_role(UserRole.ADMIN))]
)

@router.get("/settings")
async def get_settings():
    """Automatically requires admin role."""
    return {"settings": {...}}

@router.post("/settings")
async def update_settings(settings: dict):
    """Automatically requires admin role."""
    return {"message": "Settings updated"}
```

#### Accessing User Information

The dependency returns the decoded JWT payload containing user information:

```python
@router.get("/dashboard")
async def get_dashboard(
    current_user: dict = Depends(require_role(UserRole.TEACHER))
):
    user_id = current_user["sub"]  # User UUID
    email = current_user["email"]  # User email
    role = current_user["role"]    # User role

    return {
        "user_id": user_id,
        "email": email,
        "role": role,
        "dashboard_data": {...}
    }
```

#### Using `get_current_user`

For endpoints that don't require specific roles but need authentication:

```python
from app.auth.dependencies import get_current_user

@router.get("/profile")
async def get_profile(
    current_user: dict = Depends(get_current_user)
):
    """Any authenticated user can access their profile."""
    return {"profile": {...}}
```

### Error Handling

The decorator raises appropriate HTTP exceptions:

- **401 Unauthorized** - Token is missing, invalid, or expired
- **403 Forbidden** - User is authenticated but doesn't have required role

Example 403 response:
```json
{
    "detail": "Access denied. Required role(s): admin. Your role: teacher"
}
```

## Authentication Flow

### 1. Login

```bash
POST /api/v1/auth/login
Content-Type: application/json

{
    "email": "teacher@example.com",
    "password": "secure_password"
}
```

Response:
```json
{
    "access_token": "eyJhbGc...",
    "refresh_token": "eyJhbGc...",
    "expires_in": 900,
    "token_type": "bearer"
}
```

### 2. Access Protected Endpoints

Include the access token in the Authorization header:

```bash
GET /api/v1/some-endpoint
Authorization: Bearer <access_token>
```

### 3. Refresh Token

When access token expires, use refresh token to get new tokens:

```bash
POST /api/v1/auth/refresh
Content-Type: application/json

{
    "refresh_token": "eyJhbGc..."
}
```

### 4. Logout

Invalidate tokens:

```bash
POST /api/v1/auth/logout
Content-Type: application/json

{
    "access_token": "eyJhbGc...",
    "refresh_token": "eyJhbGc..."
}
```

## Token Structure

Access tokens include the following claims:

```json
{
    "sub": "user-uuid",
    "email": "user@example.com",
    "role": "teacher",
    "type": "access",
    "iat": 1234567890,
    "exp": 1234568790
}
```

- `sub` - User ID (UUID)
- `email` - User email address
- `role` - User role (admin, teacher, parent, accountant, staff)
- `type` - Token type (access or refresh)
- `iat` - Issued at timestamp
- `exp` - Expiration timestamp

## Password Reset

### 1. Request Reset

```bash
POST /api/v1/auth/password-reset/request
Content-Type: application/json

{
    "email": "user@example.com"
}
```

### 2. Confirm Reset

```bash
POST /api/v1/auth/password-reset/confirm
Content-Type: application/json

{
    "token": "reset-token-from-email",
    "new_password": "new_secure_password"
}
```

## Module Structure

```
app/auth/
├── __init__.py              # Module exports
├── dependencies.py          # Auth dependencies (require_role, get_current_user)
├── jwt.py                   # JWT token creation and validation
├── models.py                # User, UserRole, TokenBlacklist, PasswordResetToken
├── router.py                # Authentication endpoints
├── schemas.py               # Pydantic schemas for requests/responses
├── security.py              # Password hashing and verification
└── service.py               # Authentication business logic
```

## Examples

See the test endpoints in `router.py`:

- `/api/v1/auth/me` - Get current user info (any authenticated user)
- `/api/v1/auth/admin/test` - Admin-only endpoint
- `/api/v1/auth/financial/test` - Admin or Accountant endpoint

## Best Practices

1. **Always use HTTPS in production** to protect tokens in transit
2. **Store tokens securely** on the client (e.g., httpOnly cookies or secure storage)
3. **Implement token refresh** before access token expires for better UX
4. **Logout when needed** to invalidate tokens
5. **Use specific roles** - only grant the minimum necessary access
6. **Handle 401/403 errors** appropriately in your client application
7. **Don't include sensitive data** in JWT tokens (they're base64-encoded, not encrypted)

## Security Notes

- Access tokens expire after 15 minutes
- Refresh tokens expire after 7 days
- Passwords are hashed using bcrypt
- Tokens are validated on every request
- Blacklisted tokens are rejected
- Password reset tokens expire after 1 hour
- Reset tokens can only be used once
