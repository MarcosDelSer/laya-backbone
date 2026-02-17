/**
 * CSRF (Cross-Site Request Forgery) protection for LAYA Parent Portal.
 *
 * This module provides CSRF token management for protecting against
 * CSRF attacks on state-changing operations (POST, PUT, DELETE, PATCH).
 *
 * CSRF tokens are fetched from the backend and included in request headers.
 * Tokens are JWT-based and validated server-side for authenticity.
 */

import { jwtVerify, decodeJwt } from 'jose';

/**
 * CSRF token storage interface
 */
interface CSRFTokenStore {
  token: string | null;
  expiresAt: number | null;
}

// In-memory token storage (tokens are also stored in HttpOnly cookies on backend)
const tokenStore: CSRFTokenStore = {
  token: null,
  expiresAt: null,
};

/**
 * Configuration for CSRF token management
 */
export interface CSRFConfig {
  /**
   * Backend API URL for fetching CSRF tokens
   */
  apiUrl: string;
  /**
   * Token expiration buffer in minutes (refresh token this many minutes before expiry)
   */
  expirationBufferMinutes?: number;
}

/**
 * Default configuration
 */
const DEFAULT_CONFIG: Required<CSRFConfig> = {
  apiUrl: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000',
  expirationBufferMinutes: 5,
};

let config: Required<CSRFConfig> = DEFAULT_CONFIG;

/**
 * Initialize CSRF protection with custom configuration
 *
 * @param userConfig - Custom configuration options
 */
export function initCSRFProtection(userConfig: Partial<CSRFConfig>): void {
  config = {
    ...DEFAULT_CONFIG,
    ...userConfig,
  };
}

/**
 * Fetch a new CSRF token from the backend
 *
 * Makes a GET request to the /api/v1/csrf-token endpoint.
 * The backend also sets an HttpOnly cookie for additional security.
 *
 * @returns Promise resolving to the CSRF token
 * @throws Error if token fetch fails
 */
export async function fetchCSRFToken(): Promise<string> {
  try {
    const response = await fetch(`${config.apiUrl}/api/v1/csrf-token`, {
      method: 'GET',
      credentials: 'include', // Include cookies for HttpOnly cookie
      headers: {
        'Content-Type': 'application/json',
      },
    });

    if (!response.ok) {
      throw new Error(
        `Failed to fetch CSRF token: ${response.status} ${response.statusText}`
      );
    }

    const data = await response.json();

    if (!data.csrf_token) {
      throw new Error('CSRF token not found in response');
    }

    // Store token and expiration
    const token = data.csrf_token as string;
    storeCSRFToken(token);

    return token;
  } catch (error) {
    if (error instanceof Error) {
      throw new Error(`CSRF token fetch error: ${error.message}`);
    }
    throw new Error('CSRF token fetch error: Unknown error');
  }
}

/**
 * Store a CSRF token and calculate its expiration
 *
 * Decodes the JWT token to extract the expiration timestamp.
 *
 * @param token - The CSRF token to store
 */
export function storeCSRFToken(token: string): void {
  try {
    // Decode JWT to get expiration (without verification - backend handles that)
    const payload = decodeJwt(token);

    // Extract expiration timestamp
    const expiresAt = payload.exp ? payload.exp * 1000 : null; // Convert to milliseconds

    tokenStore.token = token;
    tokenStore.expiresAt = expiresAt;
  } catch (error) {
    // If decoding fails, store token without expiration info
    tokenStore.token = token;
    tokenStore.expiresAt = null;
  }
}

/**
 * Get the current CSRF token from storage
 *
 * @returns The current CSRF token or null if not set
 */
export function getCSRFToken(): string | null {
  return tokenStore.token;
}

/**
 * Check if the current CSRF token is expired or about to expire
 *
 * Returns true if:
 * - No token is stored
 * - Token is expired
 * - Token will expire within the configured buffer time
 *
 * @returns True if token needs refresh, false otherwise
 */
export function isCSRFTokenExpired(): boolean {
  if (!tokenStore.token || !tokenStore.expiresAt) {
    return true;
  }

  const now = Date.now();
  const bufferMs = config.expirationBufferMinutes * 60 * 1000;
  const expiresWithBuffer = tokenStore.expiresAt - bufferMs;

  return now >= expiresWithBuffer;
}

/**
 * Get a valid CSRF token, fetching a new one if necessary
 *
 * This is the main function to use when you need a CSRF token for a request.
 * It automatically handles token refresh if the current token is expired.
 *
 * @returns Promise resolving to a valid CSRF token
 * @throws Error if token fetch fails
 */
export async function getValidCSRFToken(): Promise<string> {
  if (isCSRFTokenExpired()) {
    return await fetchCSRFToken();
  }

  return tokenStore.token as string;
}

/**
 * Clear the stored CSRF token
 *
 * Call this on logout or when you want to force a token refresh.
 */
export function clearCSRFToken(): void {
  tokenStore.token = null;
  tokenStore.expiresAt = null;
}

/**
 * Get list of HTTP methods that require CSRF protection
 *
 * These are state-changing methods that should include a CSRF token.
 *
 * @returns Array of HTTP methods requiring CSRF protection
 */
export function getCSRFProtectedMethods(): string[] {
  return ['POST', 'PUT', 'DELETE', 'PATCH'];
}

/**
 * Get list of paths exempt from CSRF protection
 *
 * These paths are exempt because they:
 * - Are read-only (GET, HEAD, OPTIONS)
 * - Are used by external webhooks (can't provide CSRF tokens)
 * - Are health checks or monitoring endpoints
 *
 * @returns Array of path prefixes exempt from CSRF protection
 */
export function getCSRFExemptPaths(): string[] {
  return [
    '/api/health',
    '/api/docs',
    '/api/openapi.json',
    '/api/v1/webhook',
  ];
}

/**
 * Check if a given path requires CSRF protection
 *
 * @param path - The URL path to check
 * @param method - The HTTP method (defaults to 'POST')
 * @returns True if path requires CSRF protection, false otherwise
 */
export function requiresCSRFProtection(
  path: string,
  method: string = 'POST'
): boolean {
  // Check if method requires CSRF protection
  const protectedMethods = getCSRFProtectedMethods();
  if (!protectedMethods.includes(method.toUpperCase())) {
    return false;
  }

  // Root path is exempt (health check)
  if (path === '/') {
    return false;
  }

  // Check if path is exempt (using exact match or prefix match)
  const exemptPaths = getCSRFExemptPaths();
  const isExempt = exemptPaths.some((exemptPath) =>
    path === exemptPath || path.startsWith(exemptPath + '/')
  );

  return !isExempt;
}

/**
 * Validate a CSRF token structure (client-side check only)
 *
 * This performs a basic validation by decoding the JWT and checking:
 * - Token signature structure (not cryptographic verification)
 * - Token has not expired
 * - Token type is 'csrf'
 *
 * Note: Full validation happens server-side. This is just for client-side
 * checks to avoid sending obviously invalid tokens.
 *
 * @param token - The CSRF token to validate
 * @returns True if token appears valid, false otherwise
 */
export function validateCSRFTokenStructure(token: string): boolean {
  try {
    const payload = decodeJwt(token);

    // Check token type
    if (payload.type !== 'csrf') {
      return false;
    }

    // Check expiration
    if (!payload.exp) {
      return false;
    }

    const now = Math.floor(Date.now() / 1000);
    if (payload.exp < now) {
      return false;
    }

    return true;
  } catch {
    // Token is malformed
    return false;
  }
}

/**
 * Create request headers with CSRF token included
 *
 * Use this helper to automatically include the CSRF token in your requests.
 *
 * @param existingHeaders - Existing headers to merge with
 * @returns Promise resolving to headers object with CSRF token included
 * @throws Error if token fetch fails
 */
export async function createCSRFHeaders(
  existingHeaders: HeadersInit = {}
): Promise<Headers> {
  const token = await getValidCSRFToken();
  const headers = new Headers(existingHeaders);

  headers.set('X-CSRF-Token', token);

  return headers;
}

/**
 * Fetch wrapper that automatically includes CSRF token for state-changing requests
 *
 * Use this instead of the standard fetch() for API calls that require CSRF protection.
 *
 * @param url - The URL to fetch
 * @param options - Fetch options (method, headers, body, etc.)
 * @returns Promise resolving to the Response
 */
export async function fetchWithCSRF(
  url: string,
  options: RequestInit = {}
): Promise<Response> {
  const method = options.method?.toUpperCase() || 'GET';

  // Extract path from URL for checking
  const urlObj = new URL(url, config.apiUrl);
  const path = urlObj.pathname;

  // Only add CSRF token for protected methods and paths
  if (requiresCSRFProtection(path, method)) {
    const headers = await createCSRFHeaders(options.headers);

    return fetch(url, {
      ...options,
      headers,
      credentials: 'include', // Include cookies
    });
  }

  // For non-protected requests, just pass through
  return fetch(url, {
    ...options,
    credentials: 'include', // Still include cookies for auth
  });
}

/**
 * React hook for accessing CSRF token utilities
 *
 * @returns Object with CSRF token utilities
 */
export function useCSRF() {
  return {
    fetchToken: fetchCSRFToken,
    getToken: getCSRFToken,
    getValidToken: getValidCSRFToken,
    clearToken: clearCSRFToken,
    isExpired: isCSRFTokenExpired,
    validateStructure: validateCSRFTokenStructure,
    createHeaders: createCSRFHeaders,
    fetchWithCSRF,
  };
}

/**
 * Export token store for testing purposes only
 * @internal
 */
export const __internal = {
  tokenStore,
  config: () => config,
  resetConfig: () => {
    config = DEFAULT_CONFIG;
  },
};
