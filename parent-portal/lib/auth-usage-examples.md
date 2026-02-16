# Token Management Usage Examples

This document provides examples of how to use the token management utilities in the LAYA Parent Portal.

## Overview

The parent portal uses **httpOnly cookies** for secure token storage. Tokens are automatically included in requests and cannot be accessed via JavaScript, providing protection against XSS attacks.

## Table of Contents

1. [Server-Side Usage](#server-side-usage)
2. [Client-Side Usage](#client-side-usage)
3. [Middleware Usage](#middleware-usage)
4. [API Route Examples](#api-route-examples)
5. [Security Best Practices](#security-best-practices)

---

## Server-Side Usage

### Check Authentication in Server Components

```tsx
// app/dashboard/page.tsx
import { isAuthenticated } from '@/lib/auth';
import { redirect } from 'next/navigation';

export default async function DashboardPage() {
  // Check if user is authenticated
  const isAuth = await isAuthenticated();

  if (!isAuth) {
    redirect('/auth/login');
  }

  return (
    <div>
      <h1>Dashboard</h1>
      {/* Protected content */}
    </div>
  );
}
```

### Get Token for API Calls

```tsx
// app/api/profile/route.ts
import { getServerToken, getAuthHeaders } from '@/lib/auth';
import { NextRequest, NextResponse } from 'next/server';

export async function GET(request: NextRequest) {
  // Get token
  const token = await getServerToken();

  if (!token) {
    return NextResponse.json(
      { error: 'Unauthorized' },
      { status: 401 }
    );
  }

  // Use token to call external API
  const headers = await getAuthHeaders();
  const response = await fetch('http://ai-service/api/user/profile', {
    headers,
  });

  const data = await response.json();
  return NextResponse.json(data);
}
```

### Get User Information from Token

```tsx
// app/api/me/route.ts
import { getServerToken, getUserFromToken } from '@/lib/auth';
import { NextResponse } from 'next/server';

export async function GET() {
  const token = await getServerToken();

  if (!token) {
    return NextResponse.json(
      { error: 'Unauthorized' },
      { status: 401 }
    );
  }

  const user = getUserFromToken(token);

  if (!user) {
    return NextResponse.json(
      { error: 'Invalid token' },
      { status: 401 }
    );
  }

  return NextResponse.json({ user });
}
```

---

## Client-Side Usage

### Make Authenticated API Calls

```tsx
// components/UserProfile.tsx
'use client';

import { useState, useEffect } from 'react';
import { authenticatedFetch } from '@/lib/auth';

interface UserProfile {
  id: string;
  email: string;
  firstName: string;
  lastName: string;
}

export function UserProfile() {
  const [profile, setProfile] = useState<UserProfile | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadProfile() {
      try {
        // authenticatedFetch automatically includes cookies
        // and handles 401 errors by redirecting to login
        const data = await authenticatedFetch<UserProfile>('/api/user/profile');
        setProfile(data);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load profile');
      } finally {
        setLoading(false);
      }
    }

    loadProfile();
  }, []);

  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;
  if (!profile) return null;

  return (
    <div>
      <h2>{profile.firstName} {profile.lastName}</h2>
      <p>{profile.email}</p>
    </div>
  );
}
```

### Handle Logout

```tsx
// components/LogoutButton.tsx
'use client';

import { useRouter } from 'next/navigation';
import { useState } from 'react';

export function LogoutButton() {
  const router = useRouter();
  const [isLoading, setIsLoading] = useState(false);

  async function handleLogout() {
    setIsLoading(true);

    try {
      // Call logout API to clear cookies
      const response = await fetch('/api/auth/logout', {
        method: 'POST',
        credentials: 'include', // Include cookies
      });

      if (response.ok) {
        // Redirect to login page
        router.push('/auth/login');
      } else {
        console.error('Logout failed');
      }
    } catch (error) {
      console.error('Logout error:', error);
    } finally {
      setIsLoading(false);
    }
  }

  return (
    <button
      onClick={handleLogout}
      disabled={isLoading}
      className="btn btn-secondary"
    >
      {isLoading ? 'Logging out...' : 'Logout'}
    </button>
  );
}
```

### Redirect After Login

```tsx
// app/auth/login/page.tsx
'use client';

import { useRouter } from 'next/navigation';
import { getRedirectAfterLogin } from '@/lib/auth';

export default function LoginPage() {
  const router = useRouter();

  async function handleLogin(email: string, password: string) {
    const response = await fetch('/api/auth/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    });

    if (response.ok) {
      // Get the stored redirect path or default to '/'
      const redirectPath = getRedirectAfterLogin();
      router.push(redirectPath);
    }
  }

  // ... rest of component
}
```

---

## Middleware Usage

### Protect Routes with Middleware

```tsx
// middleware.ts
import { NextRequest, NextResponse } from 'next/server';
import {
  isRequestAuthenticated,
  getRequestToken,
  isTokenExpired,
  setRedirectAfterLogin,
} from '@/lib/auth';

export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  // Define protected routes
  const protectedRoutes = [
    '/dashboard',
    '/profile',
    '/messages',
    '/reports',
  ];

  // Check if current path is protected
  const isProtectedRoute = protectedRoutes.some(route =>
    pathname.startsWith(route)
  );

  if (isProtectedRoute) {
    // Check if user is authenticated
    if (!isRequestAuthenticated(request)) {
      // Store intended destination
      const loginUrl = new URL('/auth/login', request.url);

      // Redirect to login
      return NextResponse.redirect(loginUrl);
    }

    // Optional: Check if token is expired
    const token = getRequestToken(request);
    if (token && isTokenExpired(token)) {
      // Token expired, redirect to login
      const loginUrl = new URL('/auth/login', request.url);
      return NextResponse.redirect(loginUrl);
    }
  }

  // Allow request to proceed
  return NextResponse.next();
}

export const config = {
  matcher: [
    '/((?!api|_next/static|_next/image|favicon.ico).*)',
  ],
};
```

---

## API Route Examples

### Login Route

```tsx
// app/api/auth/login/route.ts
import { NextRequest, NextResponse } from 'next/server';
import { setAuthTokens } from '@/lib/auth';
import { aiServiceClient } from '@/lib/api';

export async function POST(request: NextRequest) {
  const { email, password } = await request.json();

  // Authenticate with AI service
  const response = await aiServiceClient.post('/api/auth/login', {
    email,
    password,
  });

  // Create response
  const nextResponse = NextResponse.json({
    user: response.user,
    message: 'Login successful',
  });

  // Set authentication tokens in httpOnly cookies
  setAuthTokens(nextResponse, {
    accessToken: response.accessToken,
    refreshToken: response.refreshToken, // Optional
  });

  return nextResponse;
}
```

### Logout Route

```tsx
// app/api/auth/logout/route.ts
import { NextRequest, NextResponse } from 'next/server';
import { clearAuthTokens } from '@/lib/auth';

export async function POST(request: NextRequest) {
  const response = NextResponse.json({
    message: 'Logged out successfully',
  });

  // Clear authentication tokens
  clearAuthTokens(response);

  return response;
}
```

### Protected API Route

```tsx
// app/api/user/profile/route.ts
import { NextRequest, NextResponse } from 'next/server';
import { getServerToken, getUserFromToken } from '@/lib/auth';

export async function GET(request: NextRequest) {
  // Get and validate token
  const token = await getServerToken();

  if (!token) {
    return NextResponse.json(
      { error: 'Unauthorized' },
      { status: 401 }
    );
  }

  // Get user info from token
  const user = getUserFromToken(token);

  if (!user) {
    return NextResponse.json(
      { error: 'Invalid token' },
      { status: 401 }
    );
  }

  // Fetch user profile from database or external API
  // ...

  return NextResponse.json({ user });
}
```

---

## Security Best Practices

### 1. Always Use httpOnly Cookies

✅ **Good**: Store tokens in httpOnly cookies
```tsx
setAuthTokens(response, { accessToken: token });
```

❌ **Bad**: Store tokens in localStorage or regular cookies
```tsx
// Don't do this!
localStorage.setItem('token', token);
```

### 2. Use Secure Flag in Production

The `setAuthTokens` function automatically sets the `secure` flag in production:

```tsx
// Automatically handled
secure: process.env.NODE_ENV === 'production'
```

### 3. Validate Tokens on Protected Routes

```tsx
export async function GET(request: NextRequest) {
  const token = await getServerToken();

  if (!token) {
    return NextResponse.json({ error: 'Unauthorized' }, { status: 401 });
  }

  if (isTokenExpired(token)) {
    return NextResponse.json({ error: 'Token expired' }, { status: 401 });
  }

  // Continue with protected logic
}
```

### 4. Handle Token Expiration

```tsx
// Client-side: authenticatedFetch automatically redirects on 401
const data = await authenticatedFetch('/api/protected');

// Server-side: Check expiration and refresh if needed
const token = await getServerToken();
if (token && isTokenExpired(token)) {
  // Implement token refresh logic
}
```

### 5. Clear Tokens on Logout

```tsx
export async function POST() {
  const response = NextResponse.json({ message: 'Logged out' });
  clearAuthTokens(response); // Always clear tokens
  return response;
}
```

### 6. Use SameSite Protection

The `setAuthTokens` function sets `sameSite: 'lax'` by default to protect against CSRF attacks.

### 7. Don't Expose Tokens in Responses

```tsx
// ✅ Good: Don't include token in response body
return NextResponse.json({
  user: response.user,
  message: 'Login successful',
});

// ❌ Bad: Exposing token in response
return NextResponse.json({
  user: response.user,
  token: response.accessToken, // Don't do this!
});
```

---

## Common Patterns

### Pattern 1: Fetch with Error Handling

```tsx
try {
  const data = await authenticatedFetch<T>('/api/endpoint');
  // Handle success
} catch (error) {
  if (error.message.includes('Unauthorized')) {
    // User will be redirected to login automatically
  } else {
    // Handle other errors
  }
}
```

### Pattern 2: Conditional Rendering Based on Auth

```tsx
'use client';

import { useEffect, useState } from 'react';

export function ConditionalContent() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  useEffect(() => {
    async function checkAuth() {
      try {
        await authenticatedFetch('/api/auth/check');
        setIsAuthenticated(true);
      } catch {
        setIsAuthenticated(false);
      }
    }
    checkAuth();
  }, []);

  return isAuthenticated ? (
    <ProtectedContent />
  ) : (
    <PublicContent />
  );
}
```

### Pattern 3: Server Action with Auth

```tsx
'use server';

import { getServerToken } from '@/lib/auth';

export async function updateProfile(formData: FormData) {
  const token = await getServerToken();

  if (!token) {
    throw new Error('Unauthorized');
  }

  // Process form data with authentication
  // ...
}
```

---

## Testing

### Mock authenticatedFetch

```tsx
import { vi } from 'vitest';

const mockFetch = vi.fn();
global.fetch = mockFetch;

mockFetch.mockResolvedValue({
  ok: true,
  status: 200,
  json: async () => ({ data: 'test' }),
});

const result = await authenticatedFetch('/api/test');
```

### Test Protected Routes

```tsx
import { isRequestAuthenticated } from '@/lib/auth';
import { NextRequest } from 'next/server';

const request = new NextRequest('http://localhost/dashboard', {
  headers: {
    cookie: 'access_token=test-token',
  },
});

expect(isRequestAuthenticated(request)).toBe(true);
```

---

## Troubleshooting

### Issue: Cookies not being sent with requests

**Solution**: Ensure `credentials: 'include'` is set in fetch options:

```tsx
fetch(url, {
  credentials: 'include',
  // ... other options
});
```

### Issue: 401 errors on authenticated routes

**Solution**: Check that:
1. Token cookie is being set correctly
2. Cookie name matches (`access_token`)
3. Token hasn't expired
4. SameSite and Secure settings are correct

### Issue: Redirect loop after login

**Solution**: Ensure protected routes check authentication correctly and don't redirect authenticated users to login.

---

## Next Steps

- Implement token refresh logic
- Add remember me functionality
- Implement session timeout warnings
- Add multi-factor authentication
