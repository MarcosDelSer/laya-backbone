/**
 * Authentication and token management utilities for LAYA Parent Portal.
 *
 * Provides secure token storage using httpOnly cookies, token validation,
 * and authentication state management for both client and server components.
 *
 * Security features:
 * - Tokens stored in httpOnly cookies (not accessible via JavaScript)
 * - Secure flag in production
 * - SameSite protection against CSRF
 * - Automatic token attachment to API requests
 */

import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';

// ============================================================================
// Constants
// ============================================================================

/**
 * Cookie name for storing the access token.
 */
export const ACCESS_TOKEN_COOKIE = 'access_token';

/**
 * Cookie name for storing the refresh token (if used).
 */
export const REFRESH_TOKEN_COOKIE = 'refresh_token';

/**
 * Default token expiry time (7 days in seconds).
 */
export const DEFAULT_TOKEN_EXPIRY = 60 * 60 * 24 * 7;

/**
 * Refresh token expiry time (30 days in seconds).
 */
export const REFRESH_TOKEN_EXPIRY = 60 * 60 * 24 * 30;

// ============================================================================
// Types
// ============================================================================

/**
 * User information decoded from token.
 */
export interface User {
  id: string;
  email: string;
  role: string;
  firstName?: string;
  lastName?: string;
}

/**
 * Token payload structure.
 */
export interface TokenPayload {
  sub: string; // User ID
  email: string;
  role: string;
  exp: number; // Expiration timestamp
  iat: number; // Issued at timestamp
}

/**
 * Options for setting authentication tokens.
 */
export interface SetTokenOptions {
  accessToken: string;
  refreshToken?: string;
  maxAge?: number;
}

// ============================================================================
// Server-side Token Management (Next.js Route Handlers & Server Components)
// ============================================================================

/**
 * Get the access token from httpOnly cookie (server-side only).
 *
 * @returns The access token or null if not found
 *
 * @example
 * ```ts
 * // In a server component or route handler
 * const token = await getServerToken();
 * if (!token) {
 *   redirect('/auth/login');
 * }
 * ```
 */
export async function getServerToken(): Promise<string | null> {
  try {
    const cookieStore = await cookies();
    const tokenCookie = cookieStore.get(ACCESS_TOKEN_COOKIE);
    return tokenCookie?.value || null;
  } catch (error) {
    console.error('Error reading token cookie:', error);
    return null;
  }
}

/**
 * Get the refresh token from httpOnly cookie (server-side only).
 *
 * @returns The refresh token or null if not found
 */
export async function getServerRefreshToken(): Promise<string | null> {
  try {
    const cookieStore = await cookies();
    const tokenCookie = cookieStore.get(REFRESH_TOKEN_COOKIE);
    return tokenCookie?.value || null;
  } catch (error) {
    console.error('Error reading refresh token cookie:', error);
    return null;
  }
}

/**
 * Set authentication tokens in httpOnly cookies.
 * Use this in API routes after successful login/registration.
 *
 * @param response - NextResponse to set cookies on
 * @param options - Token and expiry options
 * @returns The response with cookies set
 *
 * @example
 * ```ts
 * // In an API route
 * const response = NextResponse.json({ user });
 * setAuthTokens(response, {
 *   accessToken: 'jwt-token-here',
 *   refreshToken: 'refresh-token-here',
 * });
 * return response;
 * ```
 */
export function setAuthTokens(
  response: NextResponse,
  options: SetTokenOptions
): NextResponse {
  const { accessToken, refreshToken, maxAge = DEFAULT_TOKEN_EXPIRY } = options;

  // Set access token cookie
  response.cookies.set(ACCESS_TOKEN_COOKIE, accessToken, {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite: 'lax',
    maxAge,
    path: '/',
  });

  // Set refresh token cookie if provided
  if (refreshToken) {
    response.cookies.set(REFRESH_TOKEN_COOKIE, refreshToken, {
      httpOnly: true,
      secure: process.env.NODE_ENV === 'production',
      sameSite: 'lax',
      maxAge: REFRESH_TOKEN_EXPIRY,
      path: '/',
    });
  }

  return response;
}

/**
 * Clear all authentication tokens from cookies.
 * Use this for logout functionality.
 *
 * @param response - NextResponse to clear cookies from
 * @returns The response with cookies cleared
 *
 * @example
 * ```ts
 * // In a logout API route
 * const response = NextResponse.json({ message: 'Logged out' });
 * clearAuthTokens(response);
 * return response;
 * ```
 */
export function clearAuthTokens(response: NextResponse): NextResponse {
  response.cookies.delete(ACCESS_TOKEN_COOKIE);
  response.cookies.delete(REFRESH_TOKEN_COOKIE);
  return response;
}

/**
 * Check if user is authenticated by verifying token exists (server-side).
 *
 * @returns True if authenticated, false otherwise
 *
 * @example
 * ```ts
 * // In a server component
 * const isAuth = await isAuthenticated();
 * if (!isAuth) {
 *   redirect('/auth/login');
 * }
 * ```
 */
export async function isAuthenticated(): Promise<boolean> {
  const token = await getServerToken();
  return token !== null && token.length > 0;
}

// ============================================================================
// Middleware Token Management
// ============================================================================

/**
 * Get the access token from a NextRequest (for middleware).
 *
 * @param request - Next.js request object
 * @returns The access token or null if not found
 *
 * @example
 * ```ts
 * // In middleware.ts
 * export function middleware(request: NextRequest) {
 *   const token = getRequestToken(request);
 *   if (!token) {
 *     return NextResponse.redirect(new URL('/auth/login', request.url));
 *   }
 * }
 * ```
 */
export function getRequestToken(request: NextRequest): string | null {
  return request.cookies.get(ACCESS_TOKEN_COOKIE)?.value || null;
}

/**
 * Get the refresh token from a NextRequest (for middleware).
 *
 * @param request - Next.js request object
 * @returns The refresh token or null if not found
 */
export function getRequestRefreshToken(request: NextRequest): string | null {
  return request.cookies.get(REFRESH_TOKEN_COOKIE)?.value || null;
}

/**
 * Check if a request has a valid authentication token.
 *
 * @param request - Next.js request object
 * @returns True if request has a token, false otherwise
 */
export function isRequestAuthenticated(request: NextRequest): boolean {
  const token = getRequestToken(request);
  return token !== null && token.length > 0;
}

// ============================================================================
// Client-side Fetch Wrapper
// ============================================================================

/**
 * Fetch wrapper that automatically includes cookies and handles auth errors.
 * Use this for client-side API calls that require authentication.
 *
 * Features:
 * - Automatically includes cookies (credentials: 'include')
 * - Handles 401 errors by redirecting to login
 * - Type-safe response handling
 *
 * @param url - The URL to fetch
 * @param options - Fetch options
 * @returns The parsed response data
 *
 * @example
 * ```ts
 * // In a client component
 * const data = await authenticatedFetch<UserData>('/api/user/profile');
 * ```
 */
export async function authenticatedFetch<T = unknown>(
  url: string,
  options: RequestInit = {}
): Promise<T> {
  const response = await fetch(url, {
    ...options,
    credentials: 'include', // Include cookies in request
    headers: {
      'Content-Type': 'application/json',
      ...options.headers,
    },
  });

  // Handle unauthorized - redirect to login
  if (response.status === 401) {
    // Store intended destination for redirect after login
    if (typeof window !== 'undefined') {
      sessionStorage.setItem('redirectAfterLogin', window.location.pathname);
      window.location.href = '/auth/login';
    }
    throw new Error('Unauthorized - redirecting to login');
  }

  // Handle other errors
  if (!response.ok) {
    const errorData = await response.json().catch(() => ({}));
    throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
  }

  // Handle empty responses
  if (response.status === 204) {
    return undefined as T;
  }

  return response.json() as Promise<T>;
}

// ============================================================================
// Token Validation (Basic)
// ============================================================================

/**
 * Decode a JWT token payload without verification.
 * Note: This does NOT verify the token signature - use only for reading claims.
 *
 * @param token - The JWT token to decode
 * @returns The decoded token payload or null if invalid
 */
export function decodeToken(token: string): TokenPayload | null {
  try {
    const parts = token.split('.');
    if (parts.length !== 3) {
      return null;
    }

    const payload = parts[1];
    const decoded = JSON.parse(
      Buffer.from(payload, 'base64').toString('utf-8')
    );

    return decoded as TokenPayload;
  } catch (error) {
    console.error('Error decoding token:', error);
    return null;
  }
}

/**
 * Check if a token is expired.
 *
 * @param token - The JWT token to check
 * @returns True if token is expired, false otherwise
 */
export function isTokenExpired(token: string): boolean {
  const payload = decodeToken(token);
  if (!payload || !payload.exp) {
    return true;
  }

  const currentTime = Math.floor(Date.now() / 1000);
  return payload.exp < currentTime;
}

/**
 * Get user information from a token.
 *
 * @param token - The JWT token
 * @returns User information or null if invalid
 */
export function getUserFromToken(token: string): User | null {
  const payload = decodeToken(token);
  if (!payload) {
    return null;
  }

  return {
    id: payload.sub,
    email: payload.email,
    role: payload.role,
  };
}

// ============================================================================
// API Request Headers
// ============================================================================

/**
 * Create authorization headers with Bearer token.
 * Use this when making API requests to external services.
 *
 * @param token - The access token
 * @returns Headers object with Authorization header
 *
 * @example
 * ```ts
 * const headers = createAuthHeaders(token);
 * const response = await fetch(API_URL, { headers });
 * ```
 */
export function createAuthHeaders(token: string): Record<string, string> {
  return {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  };
}

/**
 * Get authorization headers from server-side token.
 * Convenience function for server components and API routes.
 *
 * @returns Headers object with Authorization header or empty object
 *
 * @example
 * ```ts
 * const headers = await getAuthHeaders();
 * const response = await fetch(AI_SERVICE_URL, { headers });
 * ```
 */
export async function getAuthHeaders(): Promise<Record<string, string>> {
  const token = await getServerToken();
  if (!token) {
    return {};
  }
  return createAuthHeaders(token);
}

// ============================================================================
// Session Management
// ============================================================================

/**
 * Get the redirect path stored after authentication.
 * Use this after login to redirect user to their intended destination.
 *
 * @returns The stored redirect path or default path
 */
export function getRedirectAfterLogin(): string {
  if (typeof window === 'undefined') {
    return '/';
  }

  const redirectPath = sessionStorage.getItem('redirectAfterLogin');
  sessionStorage.removeItem('redirectAfterLogin');
  return redirectPath || '/';
}

/**
 * Store the current path for redirect after login.
 * Use this when redirecting unauthenticated users to login.
 *
 * @param path - The path to redirect to after login
 */
export function setRedirectAfterLogin(path: string): void {
  if (typeof window !== 'undefined') {
    sessionStorage.setItem('redirectAfterLogin', path);
  }
}
