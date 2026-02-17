# Authentication Hooks

## Overview

The LAYA Parent Portal provides a set of custom React hooks for handling authentication in components. These hooks build on top of the `AuthContext` and provide specialized functionality for common authentication patterns.

## Available Hooks

### Core Hooks

1. **`useAuth`** - Full access to authentication context
2. **`useUser`** - Simplified access to user data
3. **`useAuthStatus`** - Minimal authentication status check

### Protection Hooks

4. **`useRequireAuth`** - Enforce authentication requirement
5. **`useAuthRedirect`** - Handle authentication-based redirects

---

## Hook Reference

### `useAuth()`

The base authentication hook that provides full access to the authentication context.

**Import:**
```tsx
import { useAuth } from '@/hooks';
```

**Returns:**
```typescript
{
  user: User | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  updateUser: (user: User | null) => void;
  refreshAuth: () => Promise<void>;
}
```

**Example:**
```tsx
'use client';

import { useAuth } from '@/hooks';

function Dashboard() {
  const { user, isAuthenticated, isLoading, refreshAuth } = useAuth();

  if (isLoading) {
    return <LoadingSpinner />;
  }

  if (!isAuthenticated) {
    return <div>Please log in</div>;
  }

  return (
    <div>
      <h1>Welcome, {user?.firstName}!</h1>
      <button onClick={refreshAuth}>Refresh Session</button>
    </div>
  );
}
```

**When to use:**
- Need full authentication state and methods
- Need to update user data
- Need to refresh authentication state

---

### `useUser()`

A simplified hook that returns only user data and loading state.

**Import:**
```tsx
import { useUser } from '@/hooks';
```

**Returns:**
```typescript
{
  user: User | null;
  isLoading: boolean;
}
```

**Example:**
```tsx
'use client';

import { useUser } from '@/hooks';

function UserProfile() {
  const { user, isLoading } = useUser();

  if (isLoading) return <div>Loading...</div>;
  if (!user) return <div>Not logged in</div>;

  return (
    <div>
      <h1>{user.firstName} {user.lastName}</h1>
      <p>{user.email}</p>
      <p>Role: {user.role}</p>
    </div>
  );
}
```

**When to use:**
- Only need user data
- Don't need auth state or methods
- Building display components

**Benefits:**
- Cleaner component code
- Only re-renders on user changes
- Less verbose than `useAuth`

---

### `useAuthStatus()`

A minimal hook that returns only authentication status.

**Import:**
```tsx
import { useAuthStatus } from '@/hooks';
```

**Returns:**
```typescript
{
  isAuthenticated: boolean;
  isLoading: boolean;
}
```

**Example:**
```tsx
'use client';

import { useAuthStatus } from '@/hooks';

function Navigation() {
  const { isAuthenticated, isLoading } = useAuthStatus();

  if (isLoading) return <Spinner />;

  return (
    <nav>
      <Link href="/">Home</Link>
      {isAuthenticated ? (
        <>
          <Link href="/dashboard">Dashboard</Link>
          <LogoutButton />
        </>
      ) : (
        <Link href="/auth/login">Login</Link>
      )}
    </nav>
  );
}
```

**When to use:**
- Only need to know if user is authenticated
- Don't need actual user data
- Conditional rendering based on auth status

**Benefits:**
- Minimal re-renders
- Lightweight
- Perfect for navigation/conditional UI

---

### `useRequireAuth()`

A hook that enforces authentication and redirects unauthenticated users to login.

**Import:**
```tsx
import { useRequireAuth } from '@/hooks';
```

**Parameters:**
```typescript
{
  redirectTo?: string;           // Default: '/auth/login'
  preserveDestination?: boolean; // Default: true
}
```

**Returns:**
```typescript
AuthContextValue // Same as useAuth()
```

**Example:**
```tsx
'use client';

import { useRequireAuth } from '@/hooks';

function ProtectedPage() {
  const { user, isLoading } = useRequireAuth();

  if (isLoading) {
    return <LoadingSpinner />;
  }

  // User is guaranteed to be authenticated here
  return <div>Protected content for {user?.firstName}</div>;
}
```

**Custom Redirect:**
```tsx
function AdminPanel() {
  const { user } = useRequireAuth({
    redirectTo: '/unauthorized',
    preserveDestination: false,
  });

  return <div>Admin Content</div>;
}
```

**When to use:**
- Protected pages/components
- Pages that require authentication
- Components that should only render for logged-in users

**How it works:**
1. Checks authentication status
2. If not authenticated → redirects to login
3. Preserves current URL as destination for post-login redirect
4. Returns full auth context if authenticated

**Security Note:**
- Does NOT prevent server-side rendering
- Use middleware for true route protection
- This is for client-side UX only

---

### `useAuthRedirect()`

A hook that handles redirects based on authentication state.

**Import:**
```tsx
import { useAuthRedirect } from '@/hooks';
```

**Parameters:**
```typescript
{
  redirectTo?: string;               // Default: '/'
  checkDestination?: boolean;        // Default: true
  redirectIfAuthenticated?: boolean; // Default: true
  redirectIfNotAuthenticated?: boolean; // Default: false
}
```

**Returns:** `void` (side-effect only)

**Example - Redirect authenticated users away:**
```tsx
'use client';

import { useAuthRedirect } from '@/hooks';

function LoginPage() {
  // Redirect already-authenticated users to dashboard
  useAuthRedirect();

  return <LoginForm />;
}
```

**Example - Custom redirect:**
```tsx
function RegisterPage() {
  useAuthRedirect({
    redirectTo: '/onboarding',
    checkDestination: false,
  });

  return <RegistrationForm />;
}
```

**Example - Redirect unauthenticated users:**
```tsx
function ProtectedPage() {
  // Use this OR useRequireAuth (they do similar things)
  useAuthRedirect({
    redirectIfAuthenticated: false,
    redirectIfNotAuthenticated: true,
    redirectTo: '/auth/login',
  });

  return <ProtectedContent />;
}
```

**Example - Using destination parameter:**
```tsx
// URL: /auth/login?destination=/dashboard
function LoginPage() {
  // After login, will redirect to /dashboard instead of /
  useAuthRedirect();

  return <LoginForm />;
}
```

**When to use:**
- Login/register pages (redirect if already logged in)
- Post-logout redirects
- Conditional navigation based on auth state

**Security:**
- Validates destination is a safe relative path
- Prevents open redirect attacks
- Only accepts paths starting with `/`

---

## Common Patterns

### Protected Component

```tsx
'use client';

import { useRequireAuth } from '@/hooks';

export function DashboardPage() {
  const { user, isLoading } = useRequireAuth();

  if (isLoading) {
    return <LoadingSpinner />;
  }

  return (
    <div>
      <h1>Dashboard</h1>
      <p>Welcome, {user?.firstName}!</p>
    </div>
  );
}
```

### Role-Based Access

```tsx
'use client';

import { useRequireAuth } from '@/hooks';

export function AdminPanel() {
  const { user, isLoading } = useRequireAuth();

  if (isLoading) return <LoadingSpinner />;

  if (user?.role !== 'admin') {
    return <div>Access denied. Admin role required.</div>;
  }

  return <div>Admin panel content</div>;
}
```

### Conditional Navigation

```tsx
'use client';

import { useAuthStatus } from '@/hooks';

export function Header() {
  const { isAuthenticated, isLoading } = useAuthStatus();

  if (isLoading) return null;

  return (
    <header>
      <Logo />
      {isAuthenticated ? (
        <AuthenticatedNav />
      ) : (
        <GuestNav />
      )}
    </header>
  );
}
```

### User Profile Display

```tsx
'use client';

import { useUser } from '@/hooks';

export function UserBadge() {
  const { user } = useUser();

  if (!user) return null;

  return (
    <div className="user-badge">
      <Avatar src={user.avatar} />
      <span>{user.firstName}</span>
    </div>
  );
}
```

### Login Page with Redirect

```tsx
'use client';

import { useAuthRedirect } from '@/hooks';
import { useAuth } from '@/hooks';

export default function LoginPage() {
  // Redirect authenticated users
  useAuthRedirect();

  const { refreshAuth } = useAuth();

  const handleLogin = async (email: string, password: string) => {
    const response = await fetch('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });

    if (response.ok) {
      // Refresh auth state
      await refreshAuth();
      // useAuthRedirect will handle the redirect
    }
  };

  return <LoginForm onSubmit={handleLogin} />;
}
```

---

## Hook Comparison

| Hook | Use Case | Returns | Redirects |
|------|----------|---------|-----------|
| `useAuth` | Full auth control | Full context | No |
| `useUser` | Display user data | User + loading | No |
| `useAuthStatus` | Check auth state | Status + loading | No |
| `useRequireAuth` | Protected pages | Full context | Yes (if not auth) |
| `useAuthRedirect` | Auth-based routing | Nothing | Yes (configurable) |

---

## Best Practices

### ✅ DO

- Use `useAuthStatus` for simple auth checks
- Use `useUser` for displaying user information
- Use `useRequireAuth` for protected pages
- Use `useAuthRedirect` on login/register pages
- Check `isLoading` before rendering auth-dependent UI
- Handle both authenticated and unauthenticated states

### ❌ DON'T

- Don't use hooks in server components (use server utilities instead)
- Don't skip loading state checks (causes flashing UI)
- Don't use multiple auth hooks when one suffices
- Don't rely solely on client-side protection (use middleware too)
- Don't forget to handle edge cases (network errors, expired sessions)

---

## Performance Tips

1. **Use the right hook for the job:**
   - `useAuthStatus` is lighter than `useAuth`
   - `useUser` only re-renders on user changes

2. **Memoize derived values:**
   ```tsx
   const { user } = useUser();
   const fullName = useMemo(
     () => `${user?.firstName} ${user?.lastName}`,
     [user]
   );
   ```

3. **Avoid unnecessary refreshes:**
   ```tsx
   // ❌ Don't do this
   useEffect(() => {
     refreshAuth(); // Every render!
   });

   // ✅ Do this
   useEffect(() => {
     refreshAuth();
   }, []); // Only on mount
   ```

---

## Testing

### Testing Components Using Auth Hooks

```tsx
import { renderHook } from '@testing-library/react';
import { AuthProvider } from '@/contexts/AuthContext';
import { useUser } from '@/hooks';

const wrapper = ({ children }) => (
  <AuthProvider initialUser={mockUser}>
    {children}
  </AuthProvider>
);

it('should return user data', () => {
  const { result } = renderHook(() => useUser(), { wrapper });
  expect(result.current.user).toEqual(mockUser);
});
```

### Mocking Auth State

```tsx
vi.mock('@/hooks', () => ({
  useAuth: vi.fn(() => ({
    user: mockUser,
    isAuthenticated: true,
    isLoading: false,
  })),
}));
```

---

## Troubleshooting

### Hook used outside AuthProvider

**Error:** `useAuth must be used within an AuthProvider`

**Solution:** Ensure `AuthProvider` wraps your component tree in `app/layout.tsx`:

```tsx
// app/layout.tsx
import { AuthProvider } from '@/contexts/AuthContext';

export default function RootLayout({ children }) {
  return (
    <html>
      <body>
        <AuthProvider>
          {children}
        </AuthProvider>
      </body>
    </html>
  );
}
```

### Infinite redirect loop

**Problem:** `useRequireAuth` causes infinite redirects

**Cause:** Using `useRequireAuth` on the login page

**Solution:** Use `useAuthRedirect` on login pages instead:

```tsx
// ❌ Don't do this on login page
const { user } = useRequireAuth();

// ✅ Do this instead
useAuthRedirect();
```

### Loading state never completes

**Problem:** `isLoading` stays `true` indefinitely

**Causes:**
- `/api/auth/me` endpoint not responding
- Network connectivity issues
- CORS or cookie configuration issues

**Debug:**
```tsx
const { isLoading, user } = useAuth();

useEffect(() => {
  console.log('Auth state:', { isLoading, user });
}, [isLoading, user]);
```

---

## Advanced Usage

### Combining Hooks

```tsx
function ProtectedDashboard() {
  // Enforce authentication
  useRequireAuth();

  // Get just the user data we need
  const { user } = useUser();

  return <div>Welcome, {user?.firstName}!</div>;
}
```

### Custom Auth Hook

```tsx
// hooks/useIsAdmin.ts
import { useUser } from '@/hooks';

export function useIsAdmin() {
  const { user, isLoading } = useUser();

  return {
    isAdmin: user?.role === 'admin',
    isLoading,
  };
}
```

### Optimistic Updates

```tsx
function ProfileEditor() {
  const { user, updateUser } = useAuth();

  const handleSave = async (newData) => {
    // Optimistically update UI
    updateUser({ ...user, ...newData });

    try {
      // Save to server
      await fetch('/api/profile', {
        method: 'PATCH',
        body: JSON.stringify(newData),
      });
    } catch (error) {
      // Revert on error
      updateUser(user);
      alert('Failed to save');
    }
  };

  return <ProfileForm onSave={handleSave} />;
}
```

---

## Related Documentation

- [Authentication Context](./auth-context.md)
- [Protected Routes Middleware](./middleware.md)
- [Token Management](./token-management.md)
- [Logout Flow](./logout-flow.md)

---

## Migration Guide

### From Direct Context Usage

**Before:**
```tsx
import { useContext } from 'react';
import AuthContext from '@/contexts/AuthContext';

function MyComponent() {
  const context = useContext(AuthContext);
  const user = context?.user;
  // ...
}
```

**After:**
```tsx
import { useUser } from '@/hooks';

function MyComponent() {
  const { user } = useUser();
  // ...
}
```

### From Custom Auth Checks

**Before:**
```tsx
function ProtectedPage() {
  const { user, isLoading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!isLoading && !user) {
      router.push('/auth/login');
    }
  }, [user, isLoading, router]);

  if (isLoading) return <Loading />;
  if (!user) return null;

  return <div>Protected content</div>;
}
```

**After:**
```tsx
function ProtectedPage() {
  const { user, isLoading } = useRequireAuth();

  if (isLoading) return <Loading />;

  return <div>Protected content</div>;
}
```

---

## Summary

The auth hooks provide a clean, type-safe way to work with authentication in the LAYA Parent Portal:

- **`useAuth`** → Full control
- **`useUser`** → User data only
- **`useAuthStatus`** → Status check only
- **`useRequireAuth`** → Protect pages
- **`useAuthRedirect`** → Handle redirects

Choose the right hook for your use case, always handle loading states, and combine with server-side protection for security.
