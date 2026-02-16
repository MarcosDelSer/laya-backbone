# Authentication & Token Management

## Overview

The LAYA Parent Portal implements a secure authentication system using **httpOnly cookies** for token storage and Next.js server-side features for token validation.

## Architecture

### Token Storage Strategy

We use **httpOnly cookies** instead of localStorage or sessionStorage for the following security benefits:

1. **XSS Protection**: Tokens stored in httpOnly cookies cannot be accessed via JavaScript, preventing theft through XSS attacks
2. **CSRF Protection**: Combined with SameSite cookies, provides protection against CSRF attacks
3. **Automatic Inclusion**: Cookies are automatically included in requests, simplifying client-side code
4. **Server-Side Validation**: Tokens can be validated on the server before rendering pages

### Cookie Configuration

```typescript
{
  httpOnly: true,                              // Prevents JavaScript access
  secure: process.env.NODE_ENV === 'production', // HTTPS only in production
  sameSite: 'lax',                              // CSRF protection
  maxAge: 60 * 60 * 24 * 7,                     // 7 days
  path: '/',                                    // Available site-wide
}
```

## Components

### 1. Token Management Utilities (`lib/auth.ts`)

Core authentication utilities for managing tokens and user sessions.

**Server-Side Functions**:
- `getServerToken()` - Get access token in server components/routes
- `getServerRefreshToken()` - Get refresh token
- `setAuthTokens()` - Set authentication cookies
- `clearAuthTokens()` - Clear authentication cookies
- `isAuthenticated()` - Check if user is authenticated
- `getAuthHeaders()` - Get Authorization headers for API calls

**Middleware Functions**:
- `getRequestToken()` - Get token from NextRequest
- `isRequestAuthenticated()` - Check request authentication

**Client-Side Functions**:
- `authenticatedFetch()` - Fetch wrapper with auto cookie inclusion and 401 handling

**Token Utilities**:
- `decodeToken()` - Decode JWT payload (no verification)
- `isTokenExpired()` - Check if token is expired
- `getUserFromToken()` - Extract user info from token
- `createAuthHeaders()` - Create Bearer token headers

**Session Management**:
- `getRedirectAfterLogin()` - Get stored redirect path
- `setRedirectAfterLogin()` - Store redirect path

### 2. API Routes

**Authentication Endpoints**:
- `POST /api/auth/login` - User login, sets httpOnly cookie
- `POST /api/auth/register` - User registration, sets httpOnly cookie
- `POST /api/auth/logout` - Logout, clears cookies
- `GET /api/auth/logout` - Alternative logout endpoint

### 3. Test Coverage (`__tests__/auth-token-management.test.ts`)

Comprehensive test suite covering:
- Cookie setting and clearing
- Token extraction from requests
- Authenticated fetch behavior
- Token decoding and validation
- Session management
- Error handling

## Usage Examples

### Server Component

```tsx
import { isAuthenticated } from '@/lib/auth';
import { redirect } from 'next/navigation';

export default async function DashboardPage() {
  if (!await isAuthenticated()) {
    redirect('/auth/login');
  }

  return <div>Protected Content</div>;
}
```

### API Route

```tsx
import { getServerToken, getAuthHeaders } from '@/lib/auth';

export async function GET() {
  const token = await getServerToken();
  if (!token) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  const headers = await getAuthHeaders();
  // Use headers for external API calls
}
```

### Client Component

```tsx
import { authenticatedFetch } from '@/lib/auth';

async function loadData() {
  const data = await authenticatedFetch('/api/user/profile');
  // Automatically includes cookies and handles 401
}
```

### Middleware

```tsx
import { isRequestAuthenticated } from '@/lib/auth';

export function middleware(request: NextRequest) {
  if (!isRequestAuthenticated(request)) {
    return NextResponse.redirect(new URL('/auth/login', request.url));
  }
  return NextResponse.next();
}
```

## Security Features

### 1. HttpOnly Cookies
Tokens stored in httpOnly cookies cannot be accessed via JavaScript, preventing XSS-based token theft.

### 2. Secure Flag
In production, cookies are only sent over HTTPS connections.

### 3. SameSite Protection
`sameSite: 'lax'` prevents CSRF attacks by restricting when cookies are sent with cross-site requests.

### 4. Automatic Token Inclusion
`authenticatedFetch()` automatically includes `credentials: 'include'` to send cookies with requests.

### 5. 401 Handling
Client-side fetch wrapper automatically redirects to login on 401 responses.

### 6. Path-Based Redirect
Stores intended destination and redirects after login using `getRedirectAfterLogin()`.

## Token Lifecycle

### 1. Login Flow

```
User submits credentials
  ↓
POST /api/auth/login
  ↓
Authenticate with AI service
  ↓
setAuthTokens() - Set httpOnly cookie
  ↓
Return user data (no token in response body)
  ↓
Client redirects to dashboard
```

### 2. Authenticated Request Flow

```
Client makes request
  ↓
Browser automatically includes cookies
  ↓
Server reads token with getServerToken()
  ↓
Validate token (check expiry, signature)
  ↓
Process request or return 401
```

### 3. Logout Flow

```
User clicks logout
  ↓
POST /api/auth/logout
  ↓
clearAuthTokens() - Delete cookies
  ↓
Return success response
  ↓
Client redirects to login
```

## Token Format

### Access Token (JWT)

```json
{
  "sub": "user-id",
  "email": "user@example.com",
  "role": "parent",
  "exp": 1234567890,
  "iat": 1234567890
}
```

### Refresh Token (Optional)

Used for obtaining new access tokens without re-authentication. Stored in separate httpOnly cookie.

## Error Handling

### Client-Side

```tsx
try {
  const data = await authenticatedFetch('/api/endpoint');
} catch (error) {
  // 401 → Automatic redirect to login
  // Other errors → Display error message
}
```

### Server-Side

```tsx
const token = await getServerToken();
if (!token) {
  return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
}

if (isTokenExpired(token)) {
  return NextResponse.json({ error: 'Token expired' }, { status: 401 });
}
```

## Testing Strategy

### Unit Tests
- Token encoding/decoding
- Cookie operations
- Validation logic

### Integration Tests
- Login flow
- Protected route access
- Token refresh
- Logout cleanup

### E2E Tests (Playwright)
- Full authentication flow
- Session persistence
- Auto-redirect on 401

## Future Enhancements

### 1. Token Refresh
Implement automatic token refresh using refresh tokens:
- Monitor token expiry
- Request new access token before expiry
- Handle refresh token rotation

### 2. Remember Me
Extended session duration for "remember me" option:
- Separate cookie with longer maxAge
- Different token expiry

### 3. Session Timeout Warning
Warn users before session expires:
- Modal warning at 5 minutes remaining
- Option to extend session
- Auto-logout on expiry

### 4. Multi-Factor Authentication
Add 2FA support:
- TOTP codes
- SMS verification
- Backup codes

### 5. Device Management
Track and manage active sessions:
- List active devices
- Remote logout
- Session history

## API Reference

See [auth-usage-examples.md](./auth-usage-examples.md) for detailed examples and patterns.

## Troubleshooting

### Cookies Not Being Sent

**Problem**: Cookies not included in cross-origin requests

**Solution**:
1. Ensure `credentials: 'include'` in fetch options
2. Check CORS configuration allows credentials
3. Verify SameSite settings

### Token Expired Errors

**Problem**: Getting 401 errors with valid token

**Solution**:
1. Check token expiry time
2. Verify server/client clock synchronization
3. Implement token refresh logic

### Redirect Loop

**Problem**: Continuous redirect between login and dashboard

**Solution**:
1. Ensure `isAuthenticated()` checks are correct
2. Verify middleware doesn't protect login routes
3. Check redirect logic in login success handler

## Standards & Compliance

- **OWASP**: Follows OWASP guidelines for session management
- **OAuth 2.0**: Compatible with OAuth token formats
- **JWT**: Standard JWT implementation (though signature verification should be added)

## Dependencies

- `next`: 14.2.20
- `react`: 18.3.1

No additional authentication libraries required - uses native Next.js features.

## Performance

- **Server-Side**: Zero runtime overhead (cookies handled by browser)
- **Client-Side**: Minimal overhead (standard fetch wrapper)
- **Memory**: No token storage in JavaScript memory
- **Network**: Cookies automatically included (no extra headers needed)

## Maintenance

### Regular Tasks
1. Rotate signing keys periodically
2. Monitor token expiry times
3. Review session durations
4. Audit authentication logs

### Security Updates
1. Keep Next.js updated
2. Review security advisories
3. Test authentication flows after updates
4. Validate cookie configurations

## Contact & Support

For questions or issues:
1. Check [auth-usage-examples.md](./auth-usage-examples.md)
2. Review test files for examples
3. Consult Next.js authentication documentation
