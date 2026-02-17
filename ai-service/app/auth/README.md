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

## JWT Security and Validation

### Overview

The LAYA AI Service implements comprehensive JWT security validation following OWASP best practices and RFC 7519 standards. This section documents the security measures implemented to prevent authentication bypass vulnerabilities.

### Required JWT Claims

All JWT tokens **MUST** include the following claims for validation to succeed:

| Claim | Name | Required | Description | Validation |
|-------|------|----------|-------------|------------|
| `exp` | Expiration Time | ✅ Yes | Unix timestamp when token expires | Automatically verified, must be in future |
| `sub` | Subject | ✅ Yes | User identifier (UUID or username) | Must be present |
| `iat` | Issued At | ✅ Yes | Unix timestamp when token was created | Must be present |
| `aud` | Audience | ✅ Yes | Intended recipient (`laya-ai-service`) | Must match configured value |
| `iss` | Issuer | ✅ Yes | Token issuer (`laya-ai-service`) | Must match configured value |

**Example Valid Token Payload:**
```json
{
  "sub": "user-12345",
  "exp": 1708185600,
  "iat": 1708182000,
  "aud": "laya-ai-service",
  "iss": "laya-ai-service",
  "email": "user@example.com",
  "role": "teacher"
}
```

### Algorithm Enforcement

The JWT validation enforces strict algorithm requirements:

- **Allowed Algorithm:** `HS256` (HMAC with SHA-256) only
- **Rejected Algorithms:**
  - `none` - Explicitly rejected to prevent unsigned token attacks
  - Any algorithm other than configured algorithm - Rejected with 401
- **Defense-in-Depth:**
  - Pre-validation check of token header before decoding
  - Algorithm whitelist in `jwt.decode()` call
  - Explicit rejection of `none` algorithm with clear error message

**Security Note:** The system validates the token's algorithm claim BEFORE signature verification to prevent algorithm confusion attacks.

### Audience and Issuer Validation

#### Audience Validation (`aud` claim)

Ensures tokens are intended for this specific service:

- **Expected Value:** `laya-ai-service` (configurable via `JWT_AUDIENCE` env var)
- **Purpose:** Prevents tokens from other services being used here
- **Rejection:** Tokens with wrong or missing audience return 401 Unauthorized

**Configuration:**
```python
# In app/config.py
jwt_audience: str = Field(default="laya-ai-service", env="JWT_AUDIENCE")
```

#### Issuer Validation (`iss` claim)

Ensures tokens come from trusted sources:

- **Expected Value:** `laya-ai-service` (configurable via `JWT_ISSUER` env var)
- **Purpose:** Prevents tokens from untrusted issuers
- **Rejection:** Tokens with wrong or missing issuer return 401 Unauthorized

**Configuration:**
```python
# In app/config.py
jwt_issuer: str = Field(default="laya-ai-service", env="JWT_ISSUER")
```

### JWT Validation Flow

```
1. Extract Bearer Token from Authorization Header
   ↓
2. Decode Token Header (without verification)
   ↓
3. Validate Algorithm
   - Reject if algorithm is 'none'
   - Reject if algorithm doesn't match HS256
   ↓
4. Decode and Verify Token
   - Verify signature with secret key
   - Verify expiration (exp) is in future
   - Require exp, sub, iat claims present
   - Validate audience matches expected value
   - Validate issuer matches expected value
   ↓
5. Check Token Blacklist
   - Reject if token has been revoked
   ↓
6. Return Validated Payload
```

### Security Best Practices

#### 1. Required Claims Enforcement

All tokens **MUST** include critical claims. Tokens missing any required claim are rejected:

```python
# In app/auth/jwt.py - decode_token()
payload = jwt.decode(
    token,
    settings.jwt_secret_key,
    algorithms=[settings.jwt_algorithm],
    audience=settings.jwt_audience,
    issuer=settings.jwt_issuer,
    options={
        "require": ["exp", "sub", "iat"],  # Critical claims
        "verify_exp": True,                # Verify expiration
    },
)
```

**Why this matters:**
- Prevents permanent tokens (missing `exp`)
- Prevents anonymous access (missing `sub`)
- Enables token age tracking (requires `iat`)

#### 2. Short Token Lifetimes

- **Default Expiration:** 1 hour (3600 seconds)
- **Maximum Recommended:** 24 hours for standard tokens
- **Rationale:** Limits exposure window if token is compromised

#### 3. Secure Secret Management

- **Secret Length:** Minimum 256 bits (32 bytes)
- **Storage:** Environment variables, never in code
- **Rotation:** Every 90 days recommended
- **Generation:** Use cryptographically secure random generator

```bash
# Generate secure JWT secret
./scripts/generate-jwt-secret.sh
```

#### 4. Token Revocation

The system implements a token blacklist for logout and security events:

```python
# Tokens are checked against blacklist after validation
stmt = select(TokenBlacklist).where(TokenBlacklist.token == token)
result = await db.execute(stmt)
if result.scalar_one_or_none() is not None:
    raise HTTPException(status_code=401, detail="Token has been revoked")
```

#### 5. Comprehensive Error Handling

All validation failures return consistent 401 responses:

- Invalid signature → 401 Unauthorized
- Expired token → 401 Unauthorized
- Missing required claims → 401 Unauthorized
- Wrong audience/issuer → 401 Unauthorized
- Invalid algorithm → 401 Unauthorized
- Blacklisted token → 401 Unauthorized

**Security Note:** Error messages are intentionally generic to prevent information leakage to attackers.

### Common Validation Errors

| Error | Cause | Solution |
|-------|-------|----------|
| `Missing required claim: exp` | Token doesn't have expiration | Include `exp` when creating token |
| `Missing required claim: sub` | Token doesn't have subject | Include `sub` when creating token |
| `Missing required claim: iat` | Token doesn't have issued-at | Include `iat` when creating token |
| `Invalid audience` | Token `aud` doesn't match expected | Ensure `aud` is `laya-ai-service` |
| `Invalid issuer` | Token `iss` doesn't match expected | Ensure `iss` is `laya-ai-service` |
| `Invalid token: 'none' algorithm is not allowed` | Token uses unsigned algorithm | Use HS256 algorithm |
| `Token has expired` | Current time > token `exp` | Request new token |
| `Token has been revoked` | Token in blacklist | Request new token via login |

### Testing JWT Security

Comprehensive security tests are available:

```bash
# Test JWT validation security
pytest tests/auth/test_jwt.py::TestJWTSecurityProperties -v

# Test that bypass vulnerabilities are fixed
pytest tests/auth/test_jwt_security_bypass.py -v

# Test middleware JWT validation
pytest tests/test_middleware_auth.py::TestMiddlewareSecurityValidation -v
```

### Creating Secure Tokens

Always use the `create_token()` function which automatically includes all required claims:

```python
from app.auth.jwt import create_token

# Create token with required claims
token = create_token(
    subject="user-12345",
    expires_delta_seconds=3600,  # 1 hour
    additional_claims={
        "email": "user@example.com",
        "role": "teacher"
    }
)

# Token automatically includes:
# - sub: "user-12345"
# - exp: current_time + 3600
# - iat: current_time
# - aud: "laya-ai-service"
# - iss: "laya-ai-service"
# - email: "user@example.com"
# - role: "teacher"
```

### Vulnerability Remediation

This implementation addresses critical JWT vulnerabilities identified in security audit (Task 086):

| Vulnerability | CVSS | Status | Mitigation |
|---------------|------|--------|------------|
| Missing expiration enforcement | 9.8 | ✅ Fixed | Required claims: `exp`, `verify_exp: True` |
| Missing subject enforcement | 9.8 | ✅ Fixed | Required claims: `sub` |
| Missing issued-at enforcement | 7.5 | ✅ Fixed | Required claims: `iat` |
| No audience validation | 8.1 | ✅ Fixed | `audience` parameter validation |
| No issuer validation | 8.1 | ✅ Fixed | `issuer` parameter validation |
| Algorithm confusion | 9.0 | ✅ Fixed | Explicit algorithm whitelist + pre-validation |

**Documentation:**
- Vulnerability Analysis: `.auto-claude/specs/086-fix-jwt-verification-auth-bypass/VULNERABILITY_ANALYSIS.md`
- Security Verification: `.auto-claude/specs/086-fix-jwt-verification-auth-bypass/SECURITY_VERIFICATION_REPORT.md`

### References

- [RFC 7519 - JSON Web Token (JWT)](https://datatracker.ietf.org/doc/html/rfc7519)
- [OWASP JWT Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/JSON_Web_Token_for_Java_Cheat_Sheet.html)
- [PyJWT Documentation](https://pyjwt.readthedocs.io/en/stable/)
- [JWT Claims Registry](https://www.iana.org/assignments/jwt/jwt.xhtml)

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
