# Message Quality Coach - RBAC Policy Documentation

## Overview

The **Message Quality Coach** feature implements role-based access control (RBAC) to ensure only authorized educators and directors can access message quality scoring, rewrite suggestions, and analytics. This document describes the RBAC policies, ownership rules, and security controls for the message quality feature.

## Role Hierarchy

LAYA uses a role-based permission system with the following roles for message quality access:

| Role | Code Value | Message Quality Access Level |
|------|-----------|----------------------------|
| **Director** | `admin` | Full access to all message quality features, analytics, and settings |
| **Educator** | `teacher` | Personal access to own messages only (analyze, rewrite, history) |
| **Parent** | `parent` | No access to message quality features |
| **Staff** | `staff` | No access to message quality features |
| **Comptable (Accountant)** | `accountant` | No access to message quality features |

### Role-to-Code Mapping

```typescript
// Frontend (TypeScript)
const allowedRoles = ['teacher', 'admin'];

// Backend (Python)
from app.auth.models import UserRole

UserRole.ADMIN      # Director
UserRole.TEACHER    # Educator
UserRole.PARENT     # Parent
UserRole.STAFF      # Staff
UserRole.ACCOUNTANT # Comptable
```

## Access Control Matrix

The following table shows the complete access control matrix for all message quality endpoints:

| Endpoint | Method | Director | Educator | Parent | Staff | Comptable | Notes |
|----------|--------|----------|----------|--------|-------|-----------|-------|
| `/api/v1/message-quality/analyze` | POST | ✅ All messages | ✅ Own messages | ❌ 403 | ❌ 403 | ❌ 403 | Ownership validated |
| `/api/v1/message-quality/rewrite` | POST | ✅ All messages | ✅ Own messages | ❌ 403 | ❌ 403 | ❌ 403 | Ownership validated |
| `/api/v1/message-quality/history` | GET | ✅ All history | ✅ Own history | ❌ 403 | ❌ 403 | ❌ 403 | Role-filtered results |
| `/api/v1/message-quality/templates` | GET | ✅ All templates | ✅ All templates | ❌ 403 | ❌ 403 | ❌ 403 | Read-only for educators |
| `/api/v1/message-quality/templates` | POST | ✅ Create | ✅ Create | ❌ 403 | ❌ 403 | ❌ 403 | Both can create |
| `/api/v1/message-quality/training-examples` | GET | ✅ All examples | ✅ All examples | ❌ 403 | ❌ 403 | ❌ 403 | Read-only access |
| `/api/v1/message-quality/analytics` | GET | ✅ Full analytics | ❌ 403 | ❌ 403 | ❌ 403 | ❌ 403 | **Director-only** |
| `/api/v1/message-quality/settings` | PUT | ✅ Configure | ❌ 403 | ❌ 403 | ❌ 403 | ❌ 403 | **Director-only** |

### Legend
- ✅ = Access granted
- ❌ = Access denied (403 Forbidden)
- **Own** = User can only access their own resources
- **All** = User can access all resources

## Implementation Details

### Backend RBAC (FastAPI)

All message quality endpoints use the `require_role()` dependency factory to enforce role-based access control.

#### Educator Endpoints (Admin + Teacher)

Endpoints accessible to both directors and educators:

```python
from fastapi import APIRouter, Depends
from app.auth.dependencies import require_role
from app.auth.models import UserRole

router = APIRouter()

@router.post("/analyze")
async def analyze_message(
    current_user: dict = Depends(require_role(UserRole.ADMIN, UserRole.TEACHER))
):
    """Both directors and educators can analyze messages.

    Educators are restricted to their own messages via ownership validation.
    """
    # Ownership validation performed in service layer
    pass
```

**Endpoints:**
- `POST /analyze` - Analyze message quality
- `POST /rewrite` - Get rewrite suggestions
- `GET /history` - View analysis history
- `GET /templates` - View message templates
- `POST /templates` - Create message templates
- `GET /training-examples` - View training examples

#### Director-Only Endpoints (Admin Only)

Endpoints accessible only to directors:

```python
@router.get("/analytics")
async def get_analytics(
    current_user: dict = Depends(require_role(UserRole.ADMIN))
):
    """Only directors can access analytics.

    Returns aggregated metrics across all educators.
    """
    pass

@router.put("/settings")
async def update_settings(
    current_user: dict = Depends(require_role(UserRole.ADMIN))
):
    """Only directors can modify message quality settings.

    Settings include quality thresholds, feature toggles, notification preferences.
    """
    pass
```

**Endpoints:**
- `GET /analytics` - View message quality analytics
- `PUT /settings` - Update message quality configuration

### Ownership Validation

Educators can only access their own message quality data. This is enforced through ownership validation in the service layer.

#### Implementation

```python
# app/services/message_quality_service.py

class MessageQualityService:
    def check_ownership(
        self,
        resource_owner_id: str,
        current_user: dict
    ) -> None:
        """Validate that the current user can access the resource.

        Args:
            resource_owner_id: The user ID who owns the resource
            current_user: The authenticated user from JWT token

        Raises:
            HTTPException 403: If educator tries to access another user's resource

        Notes:
            - Admins (directors) can access all resources
            - Teachers (educators) can only access their own resources
        """
        user_id = current_user.get("sub")
        user_role = current_user.get("role")

        # Admins can access all resources
        if user_role == UserRole.ADMIN.value:
            return

        # Teachers can only access their own resources
        if user_id != resource_owner_id:
            raise HTTPException(
                status_code=403,
                detail="You can only access your own message quality data"
            )
```

#### Usage Example

```python
@router.post("/analyze")
async def analyze_message(
    request: MessageAnalysisRequest,
    current_user: dict = Depends(require_role(UserRole.ADMIN, UserRole.TEACHER)),
    db: AsyncSession = Depends(get_db)
):
    service = MessageQualityService(db)

    # Validate ownership before processing
    service.check_ownership(
        resource_owner_id=request.user_id,
        current_user=current_user
    )

    # Proceed with analysis
    result = await service.analyze_message(request)
    return result
```

### Frontend Role-Based UI

The frontend enforces role-based visibility to hide features from unauthorized users.

#### Quality Coach Panel Visibility

```typescript
// parent-portal/components/QualityCoachPanel.tsx

import { useAuth } from '@/context/AuthContext';

export default function QualityCoachPanel() {
  const { user } = useAuth();

  // Only show to educators and directors
  const allowedRoles = ['teacher', 'admin'];

  if (!user || !allowedRoles.includes(user.role)) {
    return null; // Hide component for unauthorized roles
  }

  return (
    <div className="quality-coach-panel">
      {/* Quality coach UI */}
    </div>
  );
}
```

**Result:** Parents, staff, and accountants will not see the Quality Coach panel.

#### Analytics Link (Director-Only)

```typescript
// parent-portal/components/EnhancedMessageComposer.tsx

import { useAuth } from '@/context/AuthContext';

export default function EnhancedMessageComposer() {
  const { user } = useAuth();

  return (
    <div className="message-composer">
      {/* Message composition UI */}

      {/* Analytics link - directors only */}
      {user?.role === 'admin' && (
        <a href="/message-quality/analytics" className="analytics-link">
          View Analytics
        </a>
      )}
    </div>
  );
}
```

**Result:** Only directors see the analytics link.

## Security Features

### 1. Role-Based Access Control

All message quality endpoints are protected with FastAPI dependencies:

```python
# Require admin or teacher role
Depends(require_role(UserRole.ADMIN, UserRole.TEACHER))

# Require admin role only
Depends(require_role(UserRole.ADMIN))
```

**Behavior:**
- ✅ Users with allowed roles proceed to endpoint handler
- ❌ Users without allowed roles receive `403 Forbidden` response
- ❌ Unauthenticated requests receive `401 Unauthorized` response

### 2. Ownership Validation

Prevents educators from accessing other educators' message quality data:

```python
service.check_ownership(
    resource_owner_id=message.user_id,
    current_user=current_user
)
```

**Behavior:**
- ✅ Directors can access all resources (ownership check bypassed)
- ✅ Educators can access their own resources
- ❌ Educators cannot access other educators' resources (`403 Forbidden`)

### 3. Audit Logging

All message quality access is logged for security monitoring and compliance.

#### Log Events

```python
from app.auth.audit_logger import audit_logger

# Log successful access
audit_logger.log_message_quality_access(
    user_id=current_user["sub"],
    user_email=current_user.get("email", "unknown"),
    role=current_user.get("role", "unknown"),
    endpoint=endpoint,
    ip_address=client_ip,
    user_agent=user_agent
)

# Log denied access
audit_logger.log_message_quality_denied(
    user_id=current_user["sub"],
    user_email=current_user.get("email", "unknown"),
    role=current_user.get("role", "unknown"),
    endpoint=endpoint,
    reason="Insufficient permissions",
    ip_address=client_ip,
    user_agent=user_agent
)
```

#### Logged Information

Every message quality access attempt logs:
- **User ID** (`sub` from JWT token)
- **User Email**
- **User Role** (admin, teacher, parent, staff, accountant)
- **Endpoint** (e.g., `/api/v1/message-quality/analyze`)
- **IP Address** (client IP from request)
- **User Agent** (browser/client information)
- **Timestamp** (ISO 8601 format with timezone)
- **Event Type** (`message_quality_access` or `message_quality_denied`)

#### Audit Log Use Cases

1. **Security Monitoring**: Detect unauthorized access attempts
2. **Compliance**: Demonstrate Quebec privacy compliance
3. **Debugging**: Troubleshoot access issues
4. **Analytics**: Track feature usage by role

### 4. HTTP Response Codes

| Code | Status | When |
|------|--------|------|
| 200 | OK | Request successful, access granted |
| 401 | Unauthorized | Missing or invalid JWT token |
| 403 | Forbidden | Valid token but insufficient role/permissions |
| 500 | Internal Server Error | Unexpected error during processing |

**Example 403 Response:**

```json
{
  "detail": "Access denied. Required role(s): admin, teacher. Your role: parent"
}
```

**Example 403 Ownership Violation:**

```json
{
  "detail": "You can only access your own message quality data"
}
```

## Usage Examples

### Example 1: Educator Analyzes Own Message (Success)

**Request:**
```bash
curl -X POST http://localhost:8000/api/v1/message-quality/analyze \
  -H "Authorization: Bearer eyJhbGc..." \
  -H "Content-Type: application/json" \
  -d '{
    "message_text": "Your child was very difficult today.",
    "language": "en",
    "user_id": "teacher_123"
  }'
```

**JWT Token Payload:**
```json
{
  "sub": "teacher_123",
  "email": "educator@example.com",
  "role": "teacher"
}
```

**Result:** ✅ Success (200 OK)
- Role check: teacher ✅ (allowed for /analyze)
- Ownership check: teacher_123 == teacher_123 ✅
- Analysis performed and returned

### Example 2: Educator Tries to Analyze Another Educator's Message (Denied)

**Request:**
```bash
curl -X POST http://localhost:8000/api/v1/message-quality/analyze \
  -H "Authorization: Bearer eyJhbGc..." \
  -H "Content-Type: application/json" \
  -d '{
    "message_text": "Test message",
    "language": "en",
    "user_id": "teacher_456"
  }'
```

**JWT Token Payload:**
```json
{
  "sub": "teacher_123",
  "email": "educator@example.com",
  "role": "teacher"
}
```

**Result:** ❌ Forbidden (403)
- Role check: teacher ✅ (allowed for /analyze)
- Ownership check: teacher_123 != teacher_456 ❌
- Response: `{"detail": "You can only access your own message quality data"}`

### Example 3: Director Accesses Analytics (Success)

**Request:**
```bash
curl -X GET http://localhost:8000/api/v1/message-quality/analytics \
  -H "Authorization: Bearer eyJhbGc..."
```

**JWT Token Payload:**
```json
{
  "sub": "director_001",
  "email": "director@example.com",
  "role": "admin"
}
```

**Result:** ✅ Success (200 OK)
- Role check: admin ✅ (allowed for /analytics)
- Analytics data returned

### Example 4: Educator Tries to Access Analytics (Denied)

**Request:**
```bash
curl -X GET http://localhost:8000/api/v1/message-quality/analytics \
  -H "Authorization: Bearer eyJhbGc..."
```

**JWT Token Payload:**
```json
{
  "sub": "teacher_123",
  "email": "educator@example.com",
  "role": "teacher"
}
```

**Result:** ❌ Forbidden (403)
- Role check: teacher ❌ (not allowed for /analytics, admin-only)
- Response: `{"detail": "Access denied. Required role(s): admin. Your role: teacher"}`

### Example 5: Parent Tries to Access Message Quality (Denied)

**Request:**
```bash
curl -X POST http://localhost:8000/api/v1/message-quality/analyze \
  -H "Authorization: Bearer eyJhbGc..." \
  -H "Content-Type: application/json" \
  -d '{
    "message_text": "Test message",
    "language": "en"
  }'
```

**JWT Token Payload:**
```json
{
  "sub": "parent_789",
  "email": "parent@example.com",
  "role": "parent"
}
```

**Result:** ❌ Forbidden (403)
- Role check: parent ❌ (not allowed for /analyze)
- Response: `{"detail": "Access denied. Required role(s): admin, teacher. Your role: parent"}`
- Audit log: `message_quality_denied` event recorded

## Testing RBAC

### Unit Tests

RBAC unit tests verify role-based access control for all endpoints:

```bash
cd ai-service
pytest tests/test_message_quality_rbac.py -v
```

**Test Coverage:**
- ✅ Admin access to all endpoints
- ✅ Teacher access to educator endpoints
- ✅ Parent/Staff/Accountant denied access
- ✅ Admin-only endpoint protection
- ✅ Edge cases (missing role, invalid role)

**Expected Results:** 20/20 tests passing

### Integration Tests

Integration tests verify end-to-end RBAC scenarios:

```bash
cd ai-service
pytest tests/integration/test_message_quality_access.py -v
```

**Test Scenarios:**
- ✅ Educator analyzes own message (success)
- ✅ Educator tries to analyze another's message (denied)
- ✅ Director accesses all data (success)
- ✅ Unauthorized roles blocked (denied)
- ✅ Audit logging integration

**Expected Results:** 23/23 tests passing

### Frontend Tests

Frontend tests verify role-based UI rendering:

```bash
cd parent-portal
npm test -- QualityCoachPanel
npm test -- EnhancedMessageComposer
```

**Test Cases:**
- ✅ Quality Coach visible for teacher
- ✅ Quality Coach visible for admin
- ✅ Quality Coach hidden for parent/staff/accountant
- ✅ Analytics link visible for admin only
- ✅ Analytics link hidden for teacher

## Troubleshooting

### Problem: "Access denied" error for valid educator

**Symptoms:**
```json
{
  "detail": "Access denied. Required role(s): admin, teacher. Your role: teacher"
}
```

**Possible Causes:**
1. JWT token role claim is missing or incorrect
2. Role value doesn't match expected values ("teacher" vs "TEACHER")
3. Token is expired or invalid

**Solution:**
1. Verify JWT token payload contains `"role": "teacher"`
2. Check that role is lowercase (backend expects lowercase)
3. Request a new token if expired
4. Verify token signature with `JWT_SECRET_KEY`

### Problem: Educator can't access own messages

**Symptoms:**
```json
{
  "detail": "You can only access your own message quality data"
}
```

**Possible Causes:**
1. `user_id` in request doesn't match `sub` claim in JWT token
2. Educator is passing wrong `user_id` parameter

**Solution:**
1. Verify `user_id` in request body matches token `sub` claim
2. Frontend should automatically use authenticated user's ID
3. Check audit logs for user_id mismatch

### Problem: Quality Coach not visible in frontend

**Symptoms:**
- Educator logged in but Quality Coach panel doesn't appear

**Possible Causes:**
1. User role not set in auth context
2. Role value doesn't match allowed roles
3. Component not rendered due to routing

**Solution:**
```bash
# Check browser console for errors
# Verify user object in React DevTools
{
  user: {
    id: "teacher_123",
    email: "educator@example.com",
    role: "teacher"  // Must be lowercase
  }
}
```

### Problem: 401 Unauthorized instead of 403 Forbidden

**Symptoms:**
- Getting 401 when expecting 403 for role denial

**Explanation:**
- 401 = No JWT token or invalid token (authentication failed)
- 403 = Valid token but insufficient role (authorization failed)

**Solution:**
1. Check that `Authorization: Bearer <token>` header is present
2. Verify token is not expired
3. Verify token signature with correct `JWT_SECRET_KEY`

## Quebec Compliance

The message quality RBAC implementation supports Quebec privacy and data protection compliance:

### Data Protection
- ✅ Role-based access prevents unauthorized viewing of communication data
- ✅ Ownership validation ensures educators only see their own data
- ✅ Directors can access all data for compliance oversight

### Audit Trail
- ✅ All access attempts logged with timestamp, user, role, IP
- ✅ Denied access attempts logged separately for security monitoring
- ✅ Audit logs support compliance reporting requirements

### Bilingual Support
- ✅ RBAC applies equally to English and French messages
- ✅ Error messages available in both languages
- ✅ Audit logs support bilingual Quebec deployment

## Configuration

### Role Values

Role values are defined in the authentication system and must match between frontend and backend:

**Backend (Python):**
```python
# app/auth/models.py
from enum import Enum

class UserRole(str, Enum):
    ADMIN = "admin"           # Director
    TEACHER = "teacher"       # Educator
    PARENT = "parent"         # Parent
    STAFF = "staff"          # Staff
    ACCOUNTANT = "accountant" # Comptable
```

**Frontend (TypeScript):**
```typescript
// types/auth.ts
export type UserRole = 'admin' | 'teacher' | 'parent' | 'staff' | 'accountant';
```

### Customization

To add or modify roles:

1. Update `app/auth/models.py` with new role
2. Update frontend type definitions
3. Update `require_role()` calls in affected endpoints
4. Update frontend role checks in components
5. Add tests for new role
6. Update this documentation

## Related Documentation

- [JWT Shared Secret Setup](./JWT_SHARED_SECRET_SETUP.md) - JWT authentication configuration
- [Project Documentation](./PROJECT_DOCUMENTATION.md) - Overall system architecture
- `ai-service/app/auth/README.md` - Authentication bridge documentation
- `ai-service/app/auth/dependencies.py` - RBAC implementation source code
- `ai-service/app/routers/message_quality.py` - Message quality endpoints

## Additional Resources

- **Backend Implementation:** `ai-service/app/auth/dependencies.py`
- **Frontend Implementation:** `parent-portal/components/QualityCoachPanel.tsx`
- **Unit Tests:** `ai-service/tests/test_message_quality_rbac.py`
- **Integration Tests:** `ai-service/tests/integration/test_message_quality_access.py`
- **Audit Logger:** `ai-service/app/auth/audit_logger.py`
- **E2E Verification:** `.auto-claude/specs/091-add-rbac-message-quality/e2e-verification-results.md`

---

**Document Version:** 1.0
**Last Updated:** 2026-02-17
**Task:** 091-add-rbac-message-quality
**Status:** ✅ Production Ready
