# Authentication Bridge Module

This module provides cross-service authentication and role synchronization between Gibbon (PHP-based school management system) and the LAYA AI Service.

## Overview

The authentication bridge enables seamless user authentication across both systems by:

1. **Token Exchange**: Gibbon users can exchange their PHP session for a JWT token
2. **Role Mapping**: Gibbon roles are automatically mapped to AI service roles
3. **Unified Authentication**: Both token types are verified using the same shared secret

## Role Synchronization Mapping

The `bridges.py` module implements bidirectional role mapping between Gibbon and AI Service:

### Gibbon → AI Service

| Gibbon Role | Role ID | AI Service Role | Description |
|-------------|---------|-----------------|-------------|
| Administrator | 001 | admin | Full administrative access |
| Teacher | 002 | teacher | Educator with AI features |
| Student | 003 | student | Student with learning features |
| Parent | 004 | parent | Parent with child monitoring |
| Support Staff | 006 | staff | Support staff with limited admin |
| Unknown/Other | * | user | Default role with basic access |

### Role Hierarchy

The module implements a hierarchical access control system:

```
Level 4: admin          (full access)
Level 3: teacher        (educator access)
Level 2: staff          (staff access)
Level 1: parent/student (limited access)
Level 0: user           (basic access)
```

Higher-level roles can access resources meant for lower-level roles.

## Usage

### Basic Role Mapping

```python
from app.auth.bridges import get_ai_role_from_gibbon, get_gibbon_role_from_ai

# Convert Gibbon role to AI role
ai_role = get_ai_role_from_gibbon("002")  # Returns "teacher"

# Convert AI role back to Gibbon role
gibbon_role = get_gibbon_role_from_ai("teacher")  # Returns "002"
```

### Role Validation

```python
from app.auth.bridges import validate_role_mapping, RoleMapping

# Validate a role mapping is correct
is_valid = validate_role_mapping("001", "admin")  # Returns True

# Check if a Gibbon role is recognized
is_known = RoleMapping.is_valid_gibbon_role("002")  # Returns True

# Check if an AI role is valid
is_valid = RoleMapping.is_valid_ai_role("teacher")  # Returns True
```

### Access Control Helpers

```python
from app.auth.bridges import (
    has_admin_access,
    has_educator_access,
    has_staff_access,
    can_access_role
)

# Check specific access levels
if has_admin_access(user_role):
    # Administrator-only functionality
    pass

if has_educator_access(user_role):
    # Teacher and admin functionality
    pass

# Check hierarchical access
if can_access_role(user_role, "student"):
    # Can access student-level resources
    pass
```

### In FastAPI Routes

```python
from fastapi import Depends
from app.middleware.auth import get_current_user_multi_source
from app.auth.bridges import has_educator_access, get_ai_role_from_gibbon

@app.get("/api/v1/classroom")
async def get_classroom(
    current_user: dict = Depends(get_current_user_multi_source)
):
    user_role = current_user.get("role")

    # Check if user is an educator
    if not has_educator_access(user_role):
        raise HTTPException(status_code=403, detail="Educator access required")

    # If token came from Gibbon, you can also access the original role
    if current_user.get("source") == "gibbon":
        gibbon_role_id = current_user.get("gibbon_role_id")
        # Process Gibbon-specific logic

    return {"classroom_data": "..."}
```

## Token Flow

1. **User logs into Gibbon** via PHP session
2. **Frontend requests JWT token** from `/modules/System/auth_token.php`
3. **Gibbon validates session** and generates JWT with mapped role
4. **Frontend sends JWT** to AI Service in Authorization header
5. **AI Service middleware** verifies token and extracts user info
6. **Route handlers** use role for authorization

## Testing

Comprehensive tests are available in `tests/test_auth_bridges.py`:

```bash
# Run role mapping tests
pytest tests/test_auth_bridges.py -v

# Run all authentication tests
pytest tests/test_middleware_auth.py tests/test_auth_bridges.py -v
```

## Configuration

Role mappings are defined in `app/auth/bridges.py` and can be extended:

```python
class RoleMapping:
    _GIBBON_TO_AI = {
        GibbonRoleID.ADMINISTRATOR: AIServiceRole.ADMIN,
        GibbonRoleID.TEACHER: AIServiceRole.TEACHER,
        # Add custom role mappings here
    }
```

## Security Considerations

1. **Shared Secret**: Both Gibbon and AI Service must use the same `JWT_SECRET_KEY`
   - See [JWT Shared Secret Setup Guide](../../../docs/JWT_SHARED_SECRET_SETUP.md) for detailed configuration
   - Use `./scripts/generate-jwt-secret.sh` to generate a secure secret
   - Store secrets in environment variables (`.env` files)
   - Never commit secrets to version control
   - Rotate secrets every 90 days
2. **Token Expiration**: JWT tokens expire after 1 hour (3600 seconds)
3. **Source Tracking**: Tokens include a `source` claim to identify origin
4. **Role Validation**: Always validate roles before granting access
5. **Audit Trail**: Token exchanges are logged for security auditing

## Related Files

- `app/auth/bridges.py` - Role mapping implementation (this module)
- `app/middleware/auth.py` - Multi-source JWT verification middleware
- `gibbon/modules/System/auth_token.php` - Gibbon token exchange endpoint
- `tests/test_auth_bridges.py` - Role mapping tests
- `tests/test_middleware_auth.py` - Middleware tests
- `docs/JWT_SHARED_SECRET_SETUP.md` - **Shared secret configuration guide**
- `scripts/generate-jwt-secret.sh` - **Secure secret generator**
- `.env.example` files - **Environment configuration templates**

## Future Enhancements

Potential improvements for future iterations:

- Dynamic role mapping from configuration/database
- Custom role permissions and capabilities
- Role-based rate limiting
- Integration with OAuth2/OIDC
- Support for multiple active roles per user
