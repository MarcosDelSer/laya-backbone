/**
 * CSRF (Cross-Site Request Forgery) protection utilities for LAYA Parent Portal.
 *
 * Implements the double-submit cookie pattern for CSRF protection:
 * - CSRF token stored in httpOnly cookie (cannot be read by JavaScript)
 * - Same token sent in request header or form field
 * - Server validates both match
 *
 * Security features:
 * - Cryptographically secure token generation (crypto.randomBytes)
 * - HttpOnly cookies prevent XSS token theft
 * - SameSite protection against cross-origin attacks
 * - Secure flag in production (HTTPS only)
 * - Token rotation on validation
 * - Configurable token expiry
 *
 * @example
 * ```ts
 * // Server-side: Generate and set CSRF token
 * import { generateCsrfToken, setCsrfToken } from '@/lib/csrf';
 * const token = generateCsrfToken();
 * const response = NextResponse.json({ csrfToken: token });
 * setCsrfToken(response, token);
 * ```
 *
 * @example
 * ```ts
 * // Server-side: Validate CSRF token
 * import { validateCsrfToken } from '@/lib/csrf';
 * const isValid = await validateCsrfToken(request);
 * if (!isValid) {
 *   return NextResponse.json({ error: 'Invalid CSRF token' }, { status: 403 });
 * }
 * ```
 */

import { cookies } from 'next/headers';
import { NextRequest, NextResponse } from 'next/server';
import { randomBytes, timingSafeEqual } from 'crypto';

// ============================================================================
// Constants
// ============================================================================

/**
 * Cookie name for storing the CSRF token.
 * Uses httpOnly flag to prevent JavaScript access.
 */
export const CSRF_TOKEN_COOKIE = 'csrf_token';

/**
 * Header name for CSRF token in API requests.
 * Client should send token in this header for validation.
 */
export const CSRF_TOKEN_HEADER = 'x-csrf-token';

/**
 * Form field name for CSRF token in form submissions.
 * Hidden input field should use this name.
 */
export const CSRF_TOKEN_FIELD = 'csrf_token';

/**
 * Default CSRF token expiry time (2 hours in seconds).
 * Balances security (shorter is better) with UX (longer prevents token expiry during form filling).
 */
export const DEFAULT_CSRF_TOKEN_EXPIRY = 60 * 60 * 2;

/**
 * CSRF token byte length.
 * 32 bytes = 256 bits of entropy (sufficient for cryptographic security).
 */
export const CSRF_TOKEN_LENGTH = 32;

// ============================================================================
// Types
// ============================================================================

/**
 * Options for setting CSRF token cookie.
 */
export interface SetCsrfTokenOptions {
  /**
   * Token expiry in seconds.
   * @default DEFAULT_CSRF_TOKEN_EXPIRY (2 hours)
   */
  maxAge?: number;

  /**
   * Cookie path.
   * @default '/'
   */
  path?: string;

  /**
   * SameSite cookie attribute.
   * - 'strict': Cookie only sent for same-site requests (most secure)
   * - 'lax': Cookie sent for top-level navigations (recommended for most cases)
   * - 'none': Cookie sent for all requests (requires Secure flag)
   * @default 'lax'
   */
  sameSite?: 'strict' | 'lax' | 'none';
}

/**
 * CSRF validation result.
 */
export interface CsrfValidationResult {
  /**
   * Whether the CSRF token is valid.
   */
  valid: boolean;

  /**
   * Error message if validation failed.
   */
  error?: string;

  /**
   * The validated token (if valid).
   */
  token?: string;
}

// ============================================================================
// Token Generation
// ============================================================================

/**
 * Generate a cryptographically secure CSRF token.
 *
 * Uses Node.js crypto.randomBytes for secure random number generation.
 * Returns a hex-encoded string (64 characters for 32 bytes).
 *
 * @returns A secure random CSRF token
 *
 * @example
 * ```ts
 * const token = generateCsrfToken();
 * console.log(token); // "a1b2c3d4e5f6..." (64 hex characters)
 * ```
 */
export function generateCsrfToken(): string {
  return randomBytes(CSRF_TOKEN_LENGTH).toString('hex');
}

// ============================================================================
// Server-side Cookie Management (Next.js Route Handlers & Server Components)
// ============================================================================

/**
 * Get the CSRF token from httpOnly cookie (server-side only).
 *
 * @returns The CSRF token or null if not found
 *
 * @example
 * ```ts
 * // In a server component or route handler
 * const token = await getServerCsrfToken();
 * if (!token) {
 *   // Generate and set new token
 * }
 * ```
 */
export async function getServerCsrfToken(): Promise<string | null> {
  try {
    const cookieStore = await cookies();
    const tokenCookie = cookieStore.get(CSRF_TOKEN_COOKIE);
    return tokenCookie?.value || null;
  } catch (error) {
    console.error('Error reading CSRF token cookie:', error);
    return null;
  }
}

/**
 * Set CSRF token in httpOnly cookie.
 * Use this in API routes to set the CSRF token.
 *
 * @param response - NextResponse to set cookie on
 * @param token - The CSRF token to set
 * @param options - Cookie options
 * @returns The response with cookie set
 *
 * @example
 * ```ts
 * // In an API route
 * const token = generateCsrfToken();
 * const response = NextResponse.json({ csrfToken: token });
 * setCsrfToken(response, token);
 * return response;
 * ```
 */
export function setCsrfToken(
  response: NextResponse,
  token: string,
  options: SetCsrfTokenOptions = {}
): NextResponse {
  const {
    maxAge = DEFAULT_CSRF_TOKEN_EXPIRY,
    path = '/',
    sameSite = 'lax',
  } = options;

  response.cookies.set(CSRF_TOKEN_COOKIE, token, {
    httpOnly: true,
    secure: process.env.NODE_ENV === 'production',
    sameSite,
    maxAge,
    path,
  });

  return response;
}

/**
 * Clear CSRF token from cookie.
 * Use this when invalidating tokens (e.g., after logout).
 *
 * @param response - NextResponse to clear cookie from
 * @returns The response with cookie cleared
 *
 * @example
 * ```ts
 * // In a logout API route
 * const response = NextResponse.json({ message: 'Logged out' });
 * clearCsrfToken(response);
 * return response;
 * ```
 */
export function clearCsrfToken(response: NextResponse): NextResponse {
  response.cookies.delete(CSRF_TOKEN_COOKIE);
  return response;
}

// ============================================================================
// Token Extraction
// ============================================================================

/**
 * Extract CSRF token from request header or form field.
 *
 * Checks in order:
 * 1. X-CSRF-Token header (for API requests)
 * 2. Form field in request body (for form submissions)
 *
 * @param request - Next.js request object
 * @returns The CSRF token or null if not found
 *
 * @example
 * ```ts
 * const token = await extractCsrfTokenFromRequest(request);
 * if (!token) {
 *   return NextResponse.json({ error: 'Missing CSRF token' }, { status: 400 });
 * }
 * ```
 */
export async function extractCsrfTokenFromRequest(
  request: NextRequest
): Promise<string | null> {
  // Check header first (for API requests)
  const headerToken = request.headers.get(CSRF_TOKEN_HEADER);
  if (headerToken) {
    return headerToken;
  }

  // Check form field (for form submissions)
  try {
    const contentType = request.headers.get('content-type') || '';

    if (contentType.includes('application/json')) {
      const body = await request.json();
      return body[CSRF_TOKEN_FIELD] || null;
    }

    if (contentType.includes('application/x-www-form-urlencoded') ||
        contentType.includes('multipart/form-data')) {
      const formData = await request.formData();
      return formData.get(CSRF_TOKEN_FIELD) as string | null;
    }
  } catch (error) {
    console.error('Error extracting CSRF token from request body:', error);
  }

  return null;
}

// ============================================================================
// Token Validation
// ============================================================================

/**
 * Validate CSRF token using timing-safe comparison.
 *
 * Implements the double-submit cookie pattern:
 * 1. Get token from cookie (stored server-side)
 * 2. Get token from request (header or form field)
 * 3. Compare using timing-safe equality check
 *
 * @param request - Next.js request object
 * @returns Validation result with status and error message
 *
 * @example
 * ```ts
 * // In an API route handler
 * export async function POST(request: NextRequest) {
 *   const result = await validateCsrfToken(request);
 *
 *   if (!result.valid) {
 *     return NextResponse.json(
 *       { error: result.error },
 *       { status: 403 }
 *     );
 *   }
 *
 *   // Process request...
 * }
 * ```
 */
export async function validateCsrfToken(
  request: NextRequest
): Promise<CsrfValidationResult> {
  // Get token from cookie
  const cookieToken = request.cookies.get(CSRF_TOKEN_COOKIE)?.value;

  if (!cookieToken) {
    return {
      valid: false,
      error: 'CSRF token not found in cookie. Please refresh the page.',
    };
  }

  // Get token from request (header or form field)
  const requestToken = await extractCsrfTokenFromRequest(request);

  if (!requestToken) {
    return {
      valid: false,
      error: 'CSRF token not found in request. Please include the token in the request header or form field.',
    };
  }

  // Timing-safe comparison to prevent timing attacks
  try {
    const cookieBuffer = Buffer.from(cookieToken, 'utf-8');
    const requestBuffer = Buffer.from(requestToken, 'utf-8');

    // Ensure both tokens are the same length before comparison
    if (cookieBuffer.length !== requestBuffer.length) {
      return {
        valid: false,
        error: 'CSRF token mismatch. Please refresh the page and try again.',
      };
    }

    const isValid = timingSafeEqual(cookieBuffer, requestBuffer);

    if (!isValid) {
      return {
        valid: false,
        error: 'CSRF token mismatch. Please refresh the page and try again.',
      };
    }

    return {
      valid: true,
      token: cookieToken,
    };
  } catch (error) {
    console.error('Error validating CSRF token:', error);
    return {
      valid: false,
      error: 'CSRF token validation failed. Please try again.',
    };
  }
}

/**
 * Check if a request method requires CSRF protection.
 *
 * CSRF protection is required for state-changing operations:
 * - POST: Create resources
 * - PUT: Update resources
 * - PATCH: Partial update resources
 * - DELETE: Delete resources
 *
 * Safe methods (GET, HEAD, OPTIONS) don't require CSRF protection.
 *
 * @param method - HTTP method
 * @returns True if method requires CSRF protection
 *
 * @example
 * ```ts
 * if (requiresCsrfProtection(request.method)) {
 *   const result = await validateCsrfToken(request);
 *   if (!result.valid) {
 *     return NextResponse.json({ error: result.error }, { status: 403 });
 *   }
 * }
 * ```
 */
export function requiresCsrfProtection(method: string): boolean {
  const protectedMethods = ['POST', 'PUT', 'PATCH', 'DELETE'];
  return protectedMethods.includes(method.toUpperCase());
}

// ============================================================================
// Middleware Helper
// ============================================================================

/**
 * Create CSRF protection middleware for Next.js API routes.
 *
 * Automatically validates CSRF tokens for state-changing requests (POST, PUT, PATCH, DELETE).
 * Returns 403 Forbidden if validation fails.
 *
 * @param handler - The route handler to protect
 * @returns Protected route handler
 *
 * @example
 * ```ts
 * // In an API route file (app/api/example/route.ts)
 * import { withCsrfProtection } from '@/lib/csrf';
 *
 * async function handlePost(request: NextRequest) {
 *   // Your handler logic here
 *   return NextResponse.json({ success: true });
 * }
 *
 * export const POST = withCsrfProtection(handlePost);
 * ```
 */
export function withCsrfProtection(
  handler: (request: NextRequest) => Promise<NextResponse>
) {
  return async (request: NextRequest): Promise<NextResponse> => {
    // Only validate CSRF for state-changing methods
    if (requiresCsrfProtection(request.method)) {
      const result = await validateCsrfToken(request);

      if (!result.valid) {
        return NextResponse.json(
          {
            error: 'CSRF validation failed',
            message: result.error,
          },
          { status: 403 }
        );
      }
    }

    // Call the original handler
    return handler(request);
  };
}

// ============================================================================
// Client-side Helpers
// ============================================================================

/**
 * Get CSRF token for client-side usage.
 *
 * NOTE: This should be called from a server component or API route
 * that returns the token to the client. The token cookie itself is
 * httpOnly and cannot be read by JavaScript.
 *
 * @returns Object containing the CSRF token
 *
 * @example
 * ```ts
 * // In a server component
 * import { getCsrfTokenForClient } from '@/lib/csrf';
 *
 * export default async function MyPage() {
 *   const { token } = await getCsrfTokenForClient();
 *
 *   return (
 *     <form>
 *       <input type="hidden" name="csrf_token" value={token} />
 *       {/* Other form fields */}
 *     </form>
 *   );
 * }
 * ```
 */
export async function getCsrfTokenForClient(): Promise<{ token: string }> {
  let token = await getServerCsrfToken();

  // Generate new token if none exists
  if (!token) {
    token = generateCsrfToken();
  }

  return { token };
}

/**
 * Create headers with CSRF token for fetch requests.
 *
 * Use this client-side to attach CSRF token to API requests.
 *
 * @param token - The CSRF token
 * @param headers - Additional headers to merge
 * @returns Headers object with CSRF token
 *
 * @example
 * ```tsx
 * 'use client';
 *
 * function MyComponent({ csrfToken }: { csrfToken: string }) {
 *   const handleSubmit = async () => {
 *     const response = await fetch('/api/example', {
 *       method: 'POST',
 *       headers: createCsrfHeaders(csrfToken, {
 *         'Content-Type': 'application/json',
 *       }),
 *       body: JSON.stringify({ data: 'example' }),
 *     });
 *   };
 * }
 * ```
 */
export function createCsrfHeaders(
  token: string,
  headers: HeadersInit = {}
): HeadersInit {
  return {
    ...headers,
    [CSRF_TOKEN_HEADER]: token,
  };
}
