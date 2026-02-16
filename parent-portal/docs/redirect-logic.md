# Redirect Logic Documentation

## Overview

The LAYA Parent Portal implements comprehensive redirect logic to provide a seamless authentication experience. This document explains how redirects work across different pages and scenarios.

## Table of Contents

1. [Architecture](#architecture)
2. [Redirect Flow](#redirect-flow)
3. [Components](#components)
4. [Security Considerations](#security-considerations)
5. [Usage Examples](#usage-examples)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)

---

## Architecture

The redirect system uses multiple layers to ensure proper navigation:

### Layers

1. **Server-Side Middleware** (`middleware.ts`)
   - First line of defense
   - Protects routes at the edge
   - Fastest redirect mechanism
   - Runs before page loads

2. **Client-Side Hooks** (`useAuthRedirect`, `useRequireAuth`)
   - Second line of defense
   - Handles dynamic redirects
   - Provides better UX with loading states
   - Works with React state

3. **Auth Context** (`AuthProvider`)
   - Centralized auth state
   - Shared across all components
   - Handles session validation
   - Provides user information

### Component Hierarchy

```
app/layout.tsx (AuthProvider)
├── app/page.tsx (DashboardWrapper)
│   └── useRequireAuth() → redirects to /auth/login
├── app/auth/login/page.tsx
│   └── useAuthRedirect() → redirects to /
└── app/auth/register/page.tsx
    └── useAuthRedirect() → redirects to /
```

---

## Redirect Flow

### Scenario 1: Unauthenticated User Visits Dashboard

```
User navigates to "/" (dashboard)
    ↓
Middleware checks token
    ↓
No token found
    ↓
Middleware redirects to "/auth/login?redirect=/"
    ↓
Login page loads
    ↓
useAuthRedirect() checks auth state
    ↓
Not authenticated → shows login form
    ↓
User submits credentials
    ↓
Token stored in httpOnly cookie
    ↓
Redirect to "/" (from redirect param)
    ↓
Dashboard loads with DashboardWrapper
    ↓
useRequireAuth() checks auth state
    ↓
Authenticated → renders dashboard
```

### Scenario 2: Authenticated User Visits Login Page

```
User navigates to "/auth/login"
    ↓
Middleware checks token
    ↓
Valid token found
    ↓
Middleware redirects to "/"
    ↓
Dashboard loads immediately
```

### Scenario 3: Login with Custom Redirect

```
User clicks link requiring auth (e.g., /messages)
    ↓
Middleware redirects to "/auth/login?redirect=/messages"
    ↓
Login page extracts redirect parameter
    ↓
User logs in successfully
    ↓
Redirects to "/messages" (not "/")
    ↓
Messages page loads
```

### Scenario 4: Session Expiry During Usage

```
User is on dashboard
    ↓
Session expires (token invalid/expired)
    ↓
User clicks link or refreshes
    ↓
Middleware detects invalid token
    ↓
Redirects to "/auth/login?redirect=/current-page"
    ↓
User logs in again
    ↓
Returns to the page they were on
```

---

## Components

### 1. AuthProvider Integration

**File:** `app/layout.tsx`

```tsx
import { AuthProvider } from '@/contexts/AuthContext';

export default function RootLayout({ children }) {
  return (
    <html>
      <body>
        <AuthProvider>
          {/* All pages have access to auth state */}
          {children}
        </AuthProvider>
      </body>
    </html>
  );
}
```

**Purpose:**
- Wraps entire app with auth context
- Provides auth state to all client components
- Handles session validation on mount
- Manages user data globally

### 2. Login Page Redirects

**File:** `app/auth/login/page.tsx`

```tsx
import { useAuthRedirect } from '@/hooks/useAuthRedirect';
import { useSearchParams } from 'next/navigation';

export default function LoginPage() {
  // Redirect authenticated users away
  useAuthRedirect();

  // Extract redirect parameter
  const searchParams = useSearchParams();
  const redirect = searchParams.get('redirect') || '/';

  const handleSubmit = async () => {
    // ... login logic ...
    router.push(redirect); // Redirect to intended destination
  };

  return <LoginForm />;
}
```

**Features:**
- Auto-redirects authenticated users to dashboard
- Preserves intended destination from URL params
- Validates redirect paths for security
- Shows login form for unauthenticated users

### 3. Register Page Redirects

**File:** `app/auth/register/page.tsx`

```tsx
import { useAuthRedirect } from '@/hooks/useAuthRedirect';

export default function RegisterPage() {
  // Redirect authenticated users away
  useAuthRedirect();

  const handleSubmit = async () => {
    // ... registration logic ...
    router.push('/'); // Redirect to dashboard after registration
  };

  return <RegistrationForm />;
}
```

**Features:**
- Auto-redirects authenticated users to dashboard
- Redirects to dashboard after successful registration
- Consistent behavior with login page

### 4. Dashboard Protection

**File:** `components/DashboardWrapper.tsx`

```tsx
import { useRequireAuth } from '@/hooks/useRequireAuth';

export function DashboardWrapper({ children }) {
  const { isLoading } = useRequireAuth();

  if (isLoading) {
    return <LoadingSpinner />;
  }

  return <>{children}</>;
}
```

**Features:**
- Enforces authentication requirement
- Shows loading state while checking auth
- Auto-redirects to login if not authenticated
- Preserves current path for post-login redirect

### 5. Middleware Protection

**File:** `middleware.ts`

```tsx
export function middleware(request: NextRequest) {
  const isAuthenticated = isRequestAuthenticated(request);

  if (isProtectedRoute && !isAuthenticated) {
    return redirect(`/auth/login?redirect=${pathname}`);
  }

  if (isAuthRoute && isAuthenticated) {
    return redirect('/');
  }

  return NextResponse.next();
}
```

**Features:**
- Server-side route protection
- Fastest redirect mechanism
- Preserves destination in URL param
- Redirects auth users away from login/register

---

## Security Considerations

### Redirect Parameter Validation

**Problem:** Open redirect vulnerability

Malicious URL: `https://portal.laya.com/auth/login?redirect=//evil.com`

**Solution:** Validate redirect paths

```tsx
const redirect = searchParams.get('redirect');
if (redirect) {
  // Only allow relative paths
  if (redirect.startsWith('/') && !redirect.startsWith('//')) {
    setRedirectPath(redirect);
  }
}
```

**Valid redirects:**
- ✅ `/dashboard`
- ✅ `/messages`
- ✅ `/documents/123`

**Invalid redirects:**
- ❌ `//evil.com`
- ❌ `http://evil.com`
- ❌ `javascript:alert(1)`
- ❌ `///evil.com`

### Token Storage

**Security:**
- Tokens stored in httpOnly cookies
- Not accessible via JavaScript
- Automatic inclusion in requests
- Protected against XSS attacks

### CSRF Protection

**Security:**
- SameSite: 'lax' cookie attribute
- Protects against cross-site attacks
- Cookies only sent to same origin

---

## Usage Examples

### Example 1: Basic Login Flow

```tsx
// User visits dashboard at "/"
// Not authenticated → middleware redirects to "/auth/login?redirect=/"
// User fills login form and submits
// Success → redirects to "/" (dashboard)
```

### Example 2: Deep Link Protection

```tsx
// User clicks email link to "/invoices/123"
// Not authenticated → middleware redirects to "/auth/login?redirect=/invoices/123"
// User logs in
// Success → redirects to "/invoices/123" (not dashboard)
```

### Example 3: Authenticated User on Login Page

```tsx
// Already logged in user navigates to "/auth/login"
// Middleware detects authentication
// Immediately redirects to "/" (dashboard)
// User never sees login page
```

### Example 4: Protected Component

```tsx
'use client';

import { useRequireAuth } from '@/hooks/useRequireAuth';

export function ProtectedComponent() {
  const { user, isLoading } = useRequireAuth();

  if (isLoading) return <Loading />;

  return <div>Welcome {user.firstName}!</div>;
}
```

### Example 5: Conditional Navigation

```tsx
'use client';

import { useAuthStatus } from '@/hooks/useAuthStatus';

export function Navigation() {
  const { isAuthenticated } = useAuthStatus();

  return (
    <nav>
      {isAuthenticated ? (
        <Link href="/dashboard">Dashboard</Link>
      ) : (
        <Link href="/auth/login">Login</Link>
      )}
    </nav>
  );
}
```

---

## Testing

### Test Coverage

The redirect logic test suite covers:

1. **Login Page Redirects**
   - Authenticated users → dashboard
   - Unauthenticated users → stay on login
   - Redirect parameter handling
   - Invalid redirect rejection

2. **Register Page Redirects**
   - Authenticated users → dashboard
   - Unauthenticated users → stay on register

3. **Dashboard Redirects**
   - Unauthenticated users → login
   - Authenticated users → render content
   - Loading states

4. **Security Tests**
   - Invalid redirect rejection
   - Valid redirect acceptance
   - XSS prevention

5. **Integration Tests**
   - AuthProvider integration
   - Router integration
   - State transitions

### Running Tests

```bash
# Run all redirect tests
npm test redirect-logic

# Run with coverage
npm test -- --coverage redirect-logic

# Watch mode
npm test -- --watch redirect-logic
```

### Expected Coverage

- Statements: >80%
- Branches: >80%
- Functions: >80%
- Lines: >80%

---

## Troubleshooting

### Issue: Redirect Loop

**Symptom:** Page keeps redirecting back and forth

**Causes:**
1. Middleware and client hooks conflict
2. Invalid token that's not cleared
3. Auth state not updating properly

**Solutions:**
1. Check middleware logic matches hook logic
2. Clear cookies and try again
3. Verify AuthProvider is in layout
4. Check browser console for errors

### Issue: Lost Destination After Login

**Symptom:** Always redirects to dashboard, not intended page

**Causes:**
1. Redirect parameter not preserved
2. Login page not reading redirect param
3. Invalid redirect path rejected

**Solutions:**
1. Check URL has `?redirect=` parameter
2. Verify login page extracts redirect param
3. Ensure redirect path is valid (starts with `/`, not `//`)

### Issue: Authenticated User Sees Login Page

**Symptom:** Logged-in user still sees login form

**Causes:**
1. Token not stored properly
2. AuthProvider not wrapping app
3. useAuthRedirect not working

**Solutions:**
1. Check browser cookies for auth token
2. Verify AuthProvider in layout.tsx
3. Check useAuth hook returns correct state
4. Verify middleware detects token

### Issue: Unauthenticated User Sees Dashboard

**Symptom:** Not logged in but dashboard visible

**Causes:**
1. Middleware not protecting route
2. DashboardWrapper not enforcing auth
3. Protected routes config missing route

**Solutions:**
1. Check middleware PROTECTED_ROUTES array
2. Verify DashboardWrapper uses useRequireAuth
3. Add route to protected routes list
4. Clear cache and reload

### Issue: Redirect Parameter Shows in URL

**Symptom:** URL shows `?redirect=/messages` after login

**Causes:**
1. Normal behavior (not an issue)
2. Router.push preserves all params

**Solutions:**
1. This is expected and harmless
2. Use router.replace() instead of router.push() to avoid history entry
3. Redirect param can be stripped with router.replace() after redirect

---

## Best Practices

### 1. Always Use Hooks

```tsx
// ✅ Good
function ProtectedPage() {
  const { user } = useRequireAuth();
  return <div>Welcome {user.firstName}</div>;
}

// ❌ Bad
function ProtectedPage() {
  // Manual auth checking is error-prone
  if (!isLoggedIn) {
    router.push('/auth/login');
  }
  return <div>Welcome</div>;
}
```

### 2. Validate Redirect Paths

```tsx
// ✅ Good
const redirect = params.get('redirect');
if (redirect?.startsWith('/') && !redirect.startsWith('//')) {
  router.push(redirect);
}

// ❌ Bad
const redirect = params.get('redirect');
router.push(redirect); // Open redirect vulnerability!
```

### 3. Handle Loading States

```tsx
// ✅ Good
function Page() {
  const { user, isLoading } = useRequireAuth();
  if (isLoading) return <LoadingSpinner />;
  return <Content user={user} />;
}

// ❌ Bad
function Page() {
  const { user } = useRequireAuth();
  // Flash of wrong content while loading!
  return <Content user={user} />;
}
```

### 4. Use Appropriate Hooks

```tsx
// ✅ For protected pages
const { user, isLoading } = useRequireAuth();

// ✅ For auth pages (login/register)
useAuthRedirect();

// ✅ For conditional UI
const { isAuthenticated } = useAuthStatus();

// ✅ For user display only
const { user } = useUser();
```

---

## Related Documentation

- [Auth Context Documentation](./auth-context.md)
- [Auth Hooks Documentation](./auth-hooks.md)
- [Middleware Documentation](./middleware.md)
- [Token Management Documentation](../lib/AUTH_README.md)

---

## Summary

The LAYA Parent Portal redirect logic provides:

✅ **Security** - Protected routes, validated redirects, httpOnly cookies
✅ **User Experience** - Seamless navigation, preserved destinations, loading states
✅ **Reliability** - Multi-layer protection, comprehensive testing, error handling
✅ **Developer Experience** - Simple hooks, clear patterns, extensive documentation

The system ensures users are always in the right place at the right time, with proper security and a smooth experience.
