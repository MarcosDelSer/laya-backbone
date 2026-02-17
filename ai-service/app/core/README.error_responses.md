# Error Response Standardization

This document explains the standardized error handling system for the LAYA AI Service.

## Overview

The error response standardization ensures that all errors returned by the AI service follow a consistent format that aligns with the parent-portal error handler expectations. This enables proper error handling, categorization, and user-friendly error messages across the entire LAYA platform.

## Standard Error Response Format

All errors follow this structure:

```json
{
  "error": {
    "type": "error_category",
    "message": "Human-readable error message",
    "request_id": "uuid-request-id",
    "correlation_id": "uuid-correlation-id",
    "details": "Optional detailed information (development mode only)"
  }
}
```

### Fields

- **type**: Error category for classification (see Error Types below)
- **message**: Human-readable error message suitable for logging
- **request_id**: Unique ID for this specific request (for tracing)
- **correlation_id**: ID for tracking related requests across services
- **details**: Additional error information (only included in development mode)

## Error Types

The following error types are supported, aligned with parent-portal error categories:

| Error Type | HTTP Status | Use Case |
|------------|-------------|----------|
| `authentication_error` | 401 | Authentication failures (invalid token, expired session) |
| `authorization_error` | 403 | Authorization failures (insufficient permissions) |
| `validation_error` | 422 | Input validation failures |
| `not_found_error` | 404 | Resource not found |
| `rate_limit_error` | 429 | Rate limiting |
| `server_error` | 500 | Generic server errors |
| `database_error` | 500 | Database operation failures |
| `network_error` | 500 | Network/communication errors |
| `timeout_error` | 504 | Request timeout |
| `http_error` | varies | Generic HTTP errors |
| `internal_error` | 500 | Internal/unexpected errors |
| `unknown_error` | 500 | Unknown error types |

## Usage

### 1. Using Custom Exception Classes

The recommended approach is to use custom exception classes:

```python
from app.core.errors import (
    NotFoundError,
    ValidationError,
    AuthenticationError,
    AuthorizationError,
)

# Raise a not found error
raise NotFoundError("User not found", details="user_id: 123")

# Raise a validation error
raise ValidationError("Invalid email format")

# Raise an authentication error
raise AuthenticationError("Token expired")
```

These exceptions are automatically caught by the error handler middleware and converted to standardized responses.

### 2. Using Error Response Functions

You can also create error responses directly:

```python
from app.core.error_responses import (
    not_found_error_response,
    validation_error_response,
    authentication_error_response,
)

# In a route handler
from fastapi import Request

@router.get("/users/{user_id}")
async def get_user(user_id: str):
    user = await find_user(user_id)
    if not user:
        return not_found_error_response(
            message=f"User {user_id} not found"
        )
    return user
```

### 3. Automatic Handling

The error handler middleware automatically handles:

- **Pydantic Validation Errors**: Converted to `validation_error` type with detailed field-level error messages
- **FastAPI HTTPExceptions**: Mapped to appropriate error types based on status code
- **Generic Exceptions**: Converted to `internal_error` type

## Examples

### Example 1: Not Found Error

**Request:**
```bash
GET /api/v1/users/999
```

**Response:**
```json
{
  "error": {
    "type": "not_found_error",
    "message": "User not found",
    "request_id": "550e8400-e29b-41d4-a716-446655440000",
    "correlation_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

**Status Code:** 404 Not Found

### Example 2: Validation Error

**Request:**
```bash
POST /api/v1/users
Content-Type: application/json

{
  "name": "John"
  // missing required "email" field
}
```

**Response:**
```json
{
  "error": {
    "type": "validation_error",
    "message": "Validation failed: email (field required)",
    "request_id": "550e8400-e29b-41d4-a716-446655440001",
    "correlation_id": "550e8400-e29b-41d4-a716-446655440001",
    "details": "[\n  {\n    \"field\": \"email\",\n    \"message\": \"field required\",\n    \"type\": \"value_error.missing\"\n  }\n]"
  }
}
```

**Status Code:** 422 Unprocessable Entity

### Example 3: Authentication Error

**Request:**
```bash
GET /api/v1/protected
Authorization: Bearer invalid-token
```

**Response:**
```json
{
  "error": {
    "type": "authentication_error",
    "message": "Invalid or expired token",
    "request_id": "550e8400-e29b-41d4-a716-446655440002",
    "correlation_id": "550e8400-e29b-41d4-a716-446655440002"
  }
}
```

**Status Code:** 401 Unauthorized

### Example 4: Server Error (Development Mode)

**Request:**
```bash
GET /api/v1/data
```

**Response (Development):**
```json
{
  "error": {
    "type": "internal_error",
    "message": "An unexpected error occurred",
    "request_id": "550e8400-e29b-41d4-a716-446655440003",
    "correlation_id": "550e8400-e29b-41d4-a716-446655440003",
    "details": "ValueError: Database connection failed"
  }
}
```

**Response (Production):**
```json
{
  "error": {
    "type": "internal_error",
    "message": "An unexpected error occurred",
    "request_id": "550e8400-e29b-41d4-a716-446655440003",
    "correlation_id": "550e8400-e29b-41d4-a716-446655440003"
  }
}
```

**Status Code:** 500 Internal Server Error

## Parent-Portal Integration

The error format is designed to work seamlessly with the parent-portal error handler:

```typescript
// parent-portal/lib/api/errorHandler.ts
interface StandardErrorResponse {
  error: {
    type: string;
    message: string;
    request_id: string;
    correlation_id: string;
    details?: string;
  };
}
```

The parent-portal automatically:
- Classifies errors based on the `type` field
- Generates user-friendly messages
- Logs errors with request/correlation IDs
- Determines retry strategies
- Shows appropriate error UI

## Environment Configuration

Error response behavior changes based on environment:

### Development Mode
- Detailed error information included in `details` field
- Stack traces logged
- Validation errors include field-level details

### Production Mode
- `details` field excluded from responses
- Only high-level error information exposed
- Detailed errors logged server-side only

**Environment Variable:**
```bash
ENVIRONMENT=production  # or "development"
```

## Best Practices

### 1. Use Specific Error Types
```python
# Good: Specific error type
raise NotFoundError("Resource not found")

# Avoid: Generic HTTPException
raise HTTPException(status_code=404, detail="Not found")
```

### 2. Provide Context in Error Messages
```python
# Good: Contextual message
raise NotFoundError(f"User {user_id} not found")

# Avoid: Generic message
raise NotFoundError("Not found")
```

### 3. Use Details for Debugging
```python
# Good: Add debugging details
raise ValidationError(
    "Invalid user data",
    details=f"Field 'email' value '{email}' is not a valid email address"
)
```

### 4. Don't Expose Sensitive Information
```python
# Good: Generic message, details in logs
raise AuthenticationError("Authentication failed")

# Avoid: Exposing internal details
raise AuthenticationError("User password hash doesn't match stored hash")
```

## Testing

The error standardization includes comprehensive tests:

```bash
# Run error response tests
pytest ai-service/tests/test_error_responses.py -v

# Run error handler middleware tests
pytest ai-service/tests/test_error_handler.py -v
```

## Migration Guide

### Updating Existing Code

**Before:**
```python
from fastapi import HTTPException

raise HTTPException(status_code=404, detail="User not found")
```

**After:**
```python
from app.core.errors import NotFoundError

raise NotFoundError("User not found")
```

### Backward Compatibility

The system maintains backward compatibility with legacy error formats:
- HTTPException errors still work but are converted to standardized format
- Parent-portal handles both standard and legacy formats

## Related Files

- `ai-service/app/core/errors.py` - Error types and exception classes
- `ai-service/app/core/error_responses.py` - Error response utilities
- `ai-service/app/middleware/error_handler.py` - Error handling middleware
- `parent-portal/lib/api/errorHandler.ts` - Frontend error handler
- `parent-portal/components/ErrorBoundary.tsx` - React error boundary

## Support

For questions or issues with error handling:
1. Check this documentation
2. Review test files for examples
3. Check error logs with request_id for tracing
4. Contact the LAYA development team
