# Authentication Context Provider

## Overview

The Authentication Context Provider (`AuthContext`) is a React Context that provides authentication state and user information to all client components in the LAYA Parent Portal.

## Features

- ✅ **User State Management** - Centralized user information storage
- ✅ **Authentication Status** - Easy access to authentication state
- ✅ **Loading States** - Track authentication verification progress
- ✅ **User Updates** - Update user information across the app
- ✅ **Session Validation** - Automatic session checking on mount
- ✅ **Refresh Capability** - Manual auth state refresh

## Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                      AuthProvider                            │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  State: user, isLoading                                 │ │
│  │  Methods: updateUser(), refreshAuth()                   │ │
│  └────────────────────────────────────────────────────────┘ │
│                           │                                  │
│                           ▼                                  │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  On Mount: GET /api/auth/me                            │ │
│  │  Returns: { user } or 401                              │ │
│  └────────────────────────────────────────────────────────┘ │
│                           │                                  │
│                           ▼                                  │
│  ┌────────────────────────────────────────────────────────┐ │
│  │  Provides: AuthContextValue via Context                │ │
│  └────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
                            │
                            ▼
         ┌──────────────────────────────────────┐
         │   useAuth() hook                      │
         │   Used by client components           │
         └──────────────────────────────────────┘
```

## Installation

### 1. Wrap Your App

Update `app/layout.tsx` to wrap your application with the `AuthProvider`:

```tsx
// app/layout.tsx
import { AuthProvider } from '@/contexts/AuthContext';

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <AuthProvider>
          {children}
        </AuthProvider>
      </body>
    </html>
  );
}
```

### 2. Use the Hook

Use the `useAuth()` hook in any client component:

```tsx
'use client';

import { useAuth } from '@/contexts/AuthContext';

export function MyComponent() {
  const { user, isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return <div>Loading...</div>;
  }

  if (!isAuthenticated) {
    return <div>Please log in</div>;
  }

  return <div>Welcome, {user?.firstName}!</div>;
}
```

## API Reference

### AuthContextValue

The value provided by the context:

```typescript
interface AuthContextValue {
  // Current authenticated user or null
  user: User | null;

  // Whether user is authenticated
  isAuthenticated: boolean;

  // Whether auth state is being loaded
  isLoading: boolean;

  // Update user information
  updateUser: (user: User | null) => void;

  // Refresh authentication state
  refreshAuth: () => Promise<void>;
}
```

### User Interface

```typescript
interface User {
  id: string;          // User ID
  email: string;       // User email
  role: string;        // User role (e.g., 'parent')
  firstName?: string;  // Optional first name
  lastName?: string;   // Optional last name
}
```

### useAuth Hook

```typescript
function useAuth(): AuthContextValue
```

**Throws:** Error if used outside of `AuthProvider`

**Returns:** Authentication context value

## Usage Examples

### Basic Authentication Check

```tsx
'use client';

import { useAuth } from '@/contexts/AuthContext';

export function Dashboard() {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return <LoadingSpinner />;
  }

  if (!isAuthenticated) {
    return <div>Access denied. Please log in.</div>;
  }

  return <div>Dashboard content</div>;
}
```

### Display User Information

```tsx
'use client';

import { useAuth } from '@/contexts/AuthContext';

export function UserProfile() {
  const { user, isLoading } = useAuth();

  if (isLoading) return <div>Loading...</div>;
  if (!user) return null;

  return (
    <div>
      <h1>{user.firstName} {user.lastName}</h1>
      <p>Email: {user.email}</p>
      <p>Role: {user.role}</p>
    </div>
  );
}
```

### Update User Information

```tsx
'use client';

import { useAuth } from '@/contexts/AuthContext';

export function ProfileEditor() {
  const { user, updateUser } = useAuth();

  const handleSave = async (newData: Partial<User>) => {
    // Save to API first
    const response = await fetch('/api/user/profile', {
      method: 'PATCH',
      body: JSON.stringify(newData),
    });

    if (response.ok) {
      const updatedUser = await response.json();
      // Update context
      updateUser(updatedUser);
    }
  };

  return <ProfileForm onSave={handleSave} initialData={user} />;
}
```

### Refresh Auth State

```tsx
'use client';

import { useAuth } from '@/contexts/AuthContext';

export function RefreshButton() {
  const { refreshAuth } = useAuth();
  const [isRefreshing, setIsRefreshing] = useState(false);

  const handleRefresh = async () => {
    setIsRefreshing(true);
    await refreshAuth();
    setIsRefreshing(false);
  };

  return (
    <button onClick={handleRefresh} disabled={isRefreshing}>
      {isRefreshing ? 'Refreshing...' : 'Refresh Session'}
    </button>
  );
}
```

### Conditional Rendering

```tsx
'use client';

import { useAuth } from '@/contexts/AuthContext';

export function Navigation() {
  const { user, isAuthenticated } = useAuth();

  return (
    <nav>
      <Link href="/">Home</Link>
      {isAuthenticated ? (
        <>
          <Link href="/dashboard">Dashboard</Link>
          <span>Welcome, {user?.firstName}!</span>
          <LogoutButton />
        </>
      ) : (
        <Link href="/auth/login">Login</Link>
      )}
    </nav>
  );
}
```

### Integration with Forms

```tsx
'use client';

import { useAuth } from '@/contexts/AuthContext';
import { useEffect } from 'react';

export function SettingsForm() {
  const { user, updateUser } = useAuth();
  const [formData, setFormData] = useState(user);

  // Sync form with user changes
  useEffect(() => {
    if (user) {
      setFormData(user);
    }
  }, [user]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    const response = await fetch('/api/user/settings', {
      method: 'PUT',
      body: JSON.stringify(formData),
    });

    if (response.ok) {
      const updated = await response.json();
      updateUser(updated);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      {/* Form fields */}
    </form>
  );
}
```

## Server-Side Integration

### API Route: `/api/auth/me`

The AuthContext calls this endpoint to validate the session and get user data.

**Request:**
```
GET /api/auth/me
Credentials: include (httpOnly cookies)
```

**Success Response (200):**
```json
{
  "user": {
    "id": "123",
    "email": "user@example.com",
    "role": "parent",
    "firstName": "John",
    "lastName": "Doe"
  }
}
```

**Unauthenticated Response (401):**
```json
{
  "error": "Not authenticated"
}
```

**Invalid Token Response (401):**
```json
{
  "error": "Invalid token"
}
```

## Loading States

The context provides three loading states:

1. **Initial Load** (`isLoading: true`)
   - Shown on first render while checking auth status
   - Lasts until `/api/auth/me` responds

2. **Authenticated** (`isLoading: false`, `isAuthenticated: true`)
   - User is logged in
   - `user` object is available

3. **Not Authenticated** (`isLoading: false`, `isAuthenticated: false`)
   - User is not logged in
   - `user` is `null`

## Error Handling

The AuthContext handles errors gracefully:

```typescript
try {
  const response = await fetch('/api/auth/me', { ... });
  if (response.ok) {
    setUser(data.user);
  } else {
    setUser(null); // Not authenticated
  }
} catch (error) {
  console.error('Error checking authentication:', error);
  setUser(null); // Assume not authenticated on error
} finally {
  setIsLoading(false); // Always stop loading
}
```

**Error Cases:**
- Network errors → User set to `null`
- 401/403 responses → User set to `null`
- 500 errors → User set to `null`
- Malformed responses → User set to `null`

## Security Considerations

### Token Storage
- Tokens are stored in **httpOnly cookies** (not accessible via JavaScript)
- Cookies are automatically included in requests via `credentials: 'include'`
- No tokens are stored in localStorage or sessionStorage

### XSS Protection
- User data in context is read-only to components
- Tokens cannot be extracted by malicious scripts
- All auth operations go through secure API routes

### CSRF Protection
- Cookies use `SameSite: 'lax'` attribute
- Protected against cross-site request forgery
- Tokens are never exposed in URL parameters

### Session Validation
- Auth state is verified on mount
- Server validates token on every `/api/auth/me` call
- Expired tokens automatically result in unauthenticated state

## Testing

### Unit Tests

Test files are located in `__tests__/`:
- `auth-context.test.tsx` - AuthContext and useAuth hook tests
- `api-auth-me.test.ts` - API route tests

**Run tests:**
```bash
npm test
```

### Example Test

```tsx
import { renderHook, waitFor } from '@testing-library/react';
import { AuthProvider, useAuth } from '@/contexts/AuthContext';

it('sets authenticated state when user is logged in', async () => {
  global.fetch = vi.fn().mockResolvedValue({
    ok: true,
    json: async () => ({
      user: { id: '123', email: 'test@example.com', role: 'parent' }
    }),
  });

  const { result } = renderHook(() => useAuth(), {
    wrapper: AuthProvider,
  });

  await waitFor(() => {
    expect(result.current.isAuthenticated).toBe(true);
    expect(result.current.user?.email).toBe('test@example.com');
  });
});
```

## Best Practices

### ✅ DO

- Use `useAuth()` hook in client components
- Check `isLoading` before rendering auth-dependent UI
- Handle both authenticated and unauthenticated states
- Update context when user data changes
- Use `refreshAuth()` after login/logout

### ❌ DON'T

- Don't use context in server components (use `getServerToken()` instead)
- Don't store sensitive data in context (only user metadata)
- Don't mutate `user` object directly (use `updateUser()`)
- Don't skip loading state checks
- Don't forget error handling

## Common Patterns

### Protected Component

```tsx
'use client';

import { useAuth } from '@/contexts/AuthContext';
import { useRouter } from 'next/navigation';
import { useEffect } from 'react';

export function ProtectedComponent() {
  const { isAuthenticated, isLoading } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (!isLoading && !isAuthenticated) {
      router.push('/auth/login');
    }
  }, [isLoading, isAuthenticated, router]);

  if (isLoading) return <LoadingSpinner />;
  if (!isAuthenticated) return null;

  return <div>Protected content</div>;
}
```

### Role-Based Access

```tsx
'use client';

import { useAuth } from '@/contexts/AuthContext';

export function AdminPanel() {
  const { user, isAuthenticated, isLoading } = useAuth();

  if (isLoading) return <LoadingSpinner />;
  if (!isAuthenticated) return <div>Please log in</div>;
  if (user?.role !== 'admin') return <div>Access denied</div>;

  return <div>Admin panel</div>;
}
```

## Integration with Other Features

### With Navigation Component

```tsx
// components/Navigation.tsx
'use client';

import { useAuth } from '@/contexts/AuthContext';
import { performLogout } from '@/lib/logout';

export function Navigation() {
  const { user, isAuthenticated, refreshAuth } = useAuth();

  const handleLogout = async () => {
    await performLogout();
    await refreshAuth(); // Update context after logout
  };

  return (
    <nav>
      {isAuthenticated && (
        <>
          <span>Welcome, {user?.firstName}!</span>
          <button onClick={handleLogout}>Logout</button>
        </>
      )}
    </nav>
  );
}
```

### With Login Flow

```tsx
// app/auth/login/page.tsx
'use client';

import { useAuth } from '@/contexts/AuthContext';
import { useRouter } from 'next/navigation';

export default function LoginPage() {
  const { refreshAuth } = useAuth();
  const router = useRouter();

  const handleLogin = async (email: string, password: string) => {
    const response = await fetch('/api/auth/login', {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    });

    if (response.ok) {
      // Refresh auth context to load user data
      await refreshAuth();
      // Redirect to dashboard
      router.push('/');
    }
  };

  return <LoginForm onSubmit={handleLogin} />;
}
```

## Troubleshooting

### Context Not Available

**Error:** `useAuth must be used within an AuthProvider`

**Solution:** Ensure `AuthProvider` wraps your component tree in `layout.tsx`

### Loading Never Completes

**Issue:** `isLoading` stays `true` indefinitely

**Causes:**
- `/api/auth/me` endpoint not responding
- Network connectivity issues
- CORS or cookie issues

**Debug:**
```tsx
useEffect(() => {
  console.log('Auth state:', { user, isAuthenticated, isLoading });
}, [user, isAuthenticated, isLoading]);
```

### User State Not Updating

**Issue:** User data doesn't update after profile changes

**Solution:** Call `updateUser()` or `refreshAuth()` after mutations:

```tsx
const handleProfileUpdate = async (data) => {
  await updateProfile(data);
  await refreshAuth(); // Fetch fresh user data
};
```

### Authentication State Out of Sync

**Issue:** Context shows authenticated but API returns 401

**Solution:** Refresh auth state:

```tsx
useEffect(() => {
  refreshAuth(); // Re-validate session
}, []);
```

## Performance Considerations

- Context re-renders only when auth state changes
- `updateUser` and `refreshAuth` are memoized with `useCallback`
- Only one `/api/auth/me` call on mount (not per component)
- Loading state prevents unnecessary re-renders

## Future Enhancements

Potential improvements for future versions:

- [ ] Token refresh logic with retry
- [ ] Automatic re-authentication on 401
- [ ] Optimistic updates for user data
- [ ] Session timeout warnings
- [ ] Multi-tab synchronization
- [ ] Remember me functionality
- [ ] User preferences caching

## Related Documentation

- [Token Management](./token-management.md)
- [Protected Routes Middleware](./middleware.md)
- [Logout Flow](./logout-flow.md)
- [Password Reset Flow](./password-reset-flow.md)

## Support

For issues or questions:
- Check test files for usage examples
- Review error messages in browser console
- Ensure `/api/auth/me` endpoint is working
- Verify cookies are being set correctly
