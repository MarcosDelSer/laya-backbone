/**
 * Secure token storage and management utilities for LAYA Parent Portal.
 *
 * This module provides enhanced token utilities that complement the core authentication
 * functionality in lib/auth.ts. It focuses on secure token handling, validation,
 * and client-side token management patterns.
 *
 * Security features:
 * - Token validation and expiration checking
 * - Secure token parsing with error handling
 * - Type-safe token payload extraction
 * - Protection against malformed tokens
 * - No storage of tokens in localStorage/sessionStorage (httpOnly cookies only)
 */

import {
  ACCESS_TOKEN_COOKIE,
  REFRESH_TOKEN_COOKIE,
  DEFAULT_TOKEN_EXPIRY,
  REFRESH_TOKEN_EXPIRY,
  type TokenPayload,
  type User,
} from '../auth';

// ============================================================================
// Re-export Constants for Convenience
// ============================================================================

export {
  ACCESS_TOKEN_COOKIE,
  REFRESH_TOKEN_COOKIE,
  DEFAULT_TOKEN_EXPIRY,
  REFRESH_TOKEN_EXPIRY,
  type TokenPayload,
  type User,
};

// ============================================================================
// Token Parsing and Validation
// ============================================================================

/**
 * Safely decode a JWT token payload without verification.
 * Enhanced version with additional validation and error handling.
 *
 * @param token - The JWT token to decode
 * @returns The decoded token payload or null if invalid
 *
 * @example
 * ```ts
 * const payload = safeDecodeToken(token);
 * if (payload) {
 *   console.log('User ID:', payload.sub);
 * }
 * ```
 */
export function safeDecodeToken(token: string | null | undefined): TokenPayload | null {
  if (!token || typeof token !== 'string' || token.trim() === '') {
    return null;
  }

  try {
    const parts = token.split('.');
    if (parts.length !== 3) {
      return null;
    }

    // Validate each part is non-empty
    if (parts.some(part => part === '')) {
      return null;
    }

    const payload = parts[1];

    // Add padding if needed for base64 decoding
    const paddedPayload = payload + '='.repeat((4 - (payload.length % 4)) % 4);

    const decoded = JSON.parse(
      Buffer.from(paddedPayload, 'base64').toString('utf-8')
    );

    // Validate required fields
    if (!decoded.sub || !decoded.email || !decoded.role) {
      return null;
    }

    return decoded as TokenPayload;
  } catch (error) {
    // Don't log in production to avoid exposing token content
    if (process.env.NODE_ENV === 'development') {
      console.error('Error decoding token:', error);
    }
    return null;
  }
}

/**
 * Check if a token is expired or will expire within a given buffer time.
 *
 * @param token - The JWT token to check
 * @param bufferSeconds - Buffer time in seconds to consider token expired (default: 60)
 * @returns True if token is expired or will expire soon, false otherwise
 *
 * @example
 * ```ts
 * // Check if token expires within 5 minutes
 * if (isTokenExpiringSoon(token, 300)) {
 *   await refreshToken();
 * }
 * ```
 */
export function isTokenExpiringSoon(
  token: string | null | undefined,
  bufferSeconds: number = 60
): boolean {
  const payload = safeDecodeToken(token);
  if (!payload || !payload.exp) {
    return true;
  }

  const currentTime = Math.floor(Date.now() / 1000);
  return payload.exp < (currentTime + bufferSeconds);
}

/**
 * Check if a token is currently expired (without buffer).
 *
 * @param token - The JWT token to check
 * @returns True if token is expired, false otherwise
 *
 * @example
 * ```ts
 * if (isTokenExpired(token)) {
 *   redirectToLogin();
 * }
 * ```
 */
export function isTokenExpired(token: string | null | undefined): boolean {
  const payload = safeDecodeToken(token);
  if (!payload || !payload.exp) {
    return true;
  }

  const currentTime = Math.floor(Date.now() / 1000);
  return payload.exp < currentTime;
}

/**
 * Get the remaining time until token expiration in seconds.
 *
 * @param token - The JWT token
 * @returns Seconds until expiration, or 0 if expired/invalid
 *
 * @example
 * ```ts
 * const secondsLeft = getTokenTimeToExpiry(token);
 * console.log(`Token expires in ${secondsLeft} seconds`);
 * ```
 */
export function getTokenTimeToExpiry(token: string | null | undefined): number {
  const payload = safeDecodeToken(token);
  if (!payload || !payload.exp) {
    return 0;
  }

  const currentTime = Math.floor(Date.now() / 1000);
  const timeLeft = payload.exp - currentTime;
  return Math.max(0, timeLeft);
}

/**
 * Extract user information from a token with validation.
 *
 * @param token - The JWT token
 * @returns User information or null if invalid
 *
 * @example
 * ```ts
 * const user = extractUserFromToken(token);
 * if (user) {
 *   console.log(`Welcome ${user.email}`);
 * }
 * ```
 */
export function extractUserFromToken(token: string | null | undefined): User | null {
  const payload = safeDecodeToken(token);
  if (!payload) {
    return null;
  }

  return {
    id: payload.sub,
    email: payload.email,
    role: payload.role,
    firstName: payload.firstName,
    lastName: payload.lastName,
  };
}

/**
 * Get validated user information from a non-expired token.
 * This function verifies the token is not expired before returning user info.
 *
 * @param token - The JWT token
 * @returns User information or null if invalid or expired
 *
 * @example
 * ```ts
 * const user = getValidatedUser(token);
 * if (user) {
 *   // Token is valid and not expired
 *   renderUserProfile(user);
 * }
 * ```
 */
export function getValidatedUser(token: string | null | undefined): User | null {
  // First check if token is expired
  if (isTokenExpired(token)) {
    return null;
  }

  // Get user from validated token
  return extractUserFromToken(token);
}

// ============================================================================
// Token Validation Utilities
// ============================================================================

/**
 * Validate token format without decoding.
 * Quick check to ensure token has the basic JWT structure.
 *
 * @param token - The token to validate
 * @returns True if token has valid JWT format, false otherwise
 *
 * @example
 * ```ts
 * if (!hasValidTokenFormat(token)) {
 *   throw new Error('Invalid token format');
 * }
 * ```
 */
export function hasValidTokenFormat(token: string | null | undefined): boolean {
  if (!token || typeof token !== 'string') {
    return false;
  }

  const parts = token.trim().split('.');
  return parts.length === 3 && parts.every(part => part.length > 0);
}

/**
 * Validate that a token contains required claims.
 *
 * @param token - The JWT token
 * @param requiredClaims - Array of claim names that must be present
 * @returns True if all required claims are present, false otherwise
 *
 * @example
 * ```ts
 * if (!hasRequiredClaims(token, ['sub', 'email', 'role'])) {
 *   throw new Error('Token missing required claims');
 * }
 * ```
 */
export function hasRequiredClaims(
  token: string | null | undefined,
  requiredClaims: string[]
): boolean {
  const payload = safeDecodeToken(token);
  if (!payload) {
    return false;
  }

  return requiredClaims.every(claim =>
    Object.prototype.hasOwnProperty.call(payload, claim) &&
    (payload as Record<string, unknown>)[claim] !== undefined &&
    (payload as Record<string, unknown>)[claim] !== null
  );
}

/**
 * Get the token issued-at time as a Date object.
 *
 * @param token - The JWT token
 * @returns Date object of when token was issued, or null if invalid
 *
 * @example
 * ```ts
 * const issuedAt = getTokenIssuedAt(token);
 * if (issuedAt) {
 *   console.log(`Token issued at: ${issuedAt.toISOString()}`);
 * }
 * ```
 */
export function getTokenIssuedAt(token: string | null | undefined): Date | null {
  const payload = safeDecodeToken(token);
  if (!payload || !payload.iat) {
    return null;
  }

  return new Date(payload.iat * 1000);
}

/**
 * Get the token expiration time as a Date object.
 *
 * @param token - The JWT token
 * @returns Date object of when token expires, or null if invalid
 *
 * @example
 * ```ts
 * const expiresAt = getTokenExpiresAt(token);
 * if (expiresAt) {
 *   console.log(`Token expires at: ${expiresAt.toISOString()}`);
 * }
 * ```
 */
export function getTokenExpiresAt(token: string | null | undefined): Date | null {
  const payload = safeDecodeToken(token);
  if (!payload || !payload.exp) {
    return null;
  }

  return new Date(payload.exp * 1000);
}

// ============================================================================
// Token Security Utilities
// ============================================================================

/**
 * Sanitize token for logging (shows only first/last 6 characters).
 * Use this when logging token information for debugging.
 *
 * @param token - The token to sanitize
 * @returns Sanitized token string safe for logging
 *
 * @example
 * ```ts
 * console.log('Token:', sanitizeTokenForLogging(token));
 * // Output: Token: eyJhbG...5cCI6I
 * ```
 */
export function sanitizeTokenForLogging(token: string | null | undefined): string {
  if (!token || typeof token !== 'string') {
    return '[no token]';
  }

  if (token.length <= 12) {
    return '[token too short]';
  }

  return `${token.substring(0, 6)}...${token.substring(token.length - 6)}`;
}

/**
 * Compare two tokens securely (timing-safe comparison).
 * Prevents timing attacks when comparing tokens.
 *
 * @param token1 - First token
 * @param token2 - Second token
 * @returns True if tokens match, false otherwise
 *
 * @example
 * ```ts
 * if (areTokensEqual(storedToken, receivedToken)) {
 *   // Tokens match
 * }
 * ```
 */
export function areTokensEqual(
  token1: string | null | undefined,
  token2: string | null | undefined
): boolean {
  // Handle null/undefined cases
  if (!token1 || !token2) {
    return token1 === token2;
  }

  // Length must match
  if (token1.length !== token2.length) {
    return false;
  }

  // Timing-safe comparison
  let result = 0;
  for (let i = 0; i < token1.length; i++) {
    result |= token1.charCodeAt(i) ^ token2.charCodeAt(i);
  }

  return result === 0;
}

// ============================================================================
// Client-side Token Management Helpers
// ============================================================================

/**
 * Check if we're running in a browser environment.
 * Useful for conditional client-side token operations.
 *
 * @returns True if running in browser, false otherwise
 */
export function isBrowserEnvironment(): boolean {
  return typeof window !== 'undefined' && typeof document !== 'undefined';
}

/**
 * Check if cookies are available and accessible.
 * Note: HttpOnly cookies are not accessible via JavaScript (by design for security).
 *
 * @returns True if cookies can be accessed (but httpOnly cookies won't be visible)
 */
export function areCookiesAvailable(): boolean {
  if (!isBrowserEnvironment()) {
    return false;
  }

  try {
    // Test if we can access document.cookie
    const test = document.cookie;
    return test !== undefined;
  } catch {
    return false;
  }
}

/**
 * Create authorization headers with Bearer token.
 * Utility for making authenticated API requests.
 *
 * @param token - The access token
 * @returns Headers object with Authorization header
 *
 * @example
 * ```ts
 * const headers = createBearerHeaders(token);
 * const response = await fetch(API_URL, { headers });
 * ```
 */
export function createBearerHeaders(token: string): Record<string, string> {
  return {
    'Authorization': `Bearer ${token}`,
    'Content-Type': 'application/json',
  };
}

/**
 * Parse token role for role-based access control.
 *
 * @param token - The JWT token
 * @returns The user role or null if invalid
 *
 * @example
 * ```ts
 * const role = getTokenRole(token);
 * if (role === 'admin') {
 *   // Show admin features
 * }
 * ```
 */
export function getTokenRole(token: string | null | undefined): string | null {
  const payload = safeDecodeToken(token);
  return payload?.role || null;
}

/**
 * Check if token has a specific role.
 *
 * @param token - The JWT token
 * @param role - The role to check for
 * @returns True if token has the specified role, false otherwise
 *
 * @example
 * ```ts
 * if (hasRole(token, 'admin')) {
 *   // User is an admin
 * }
 * ```
 */
export function hasRole(token: string | null | undefined, role: string): boolean {
  const tokenRole = getTokenRole(token);
  return tokenRole === role;
}

/**
 * Check if token has any of the specified roles.
 *
 * @param token - The JWT token
 * @param roles - Array of roles to check
 * @returns True if token has any of the specified roles, false otherwise
 *
 * @example
 * ```ts
 * if (hasAnyRole(token, ['admin', 'teacher'])) {
 *   // User is either admin or teacher
 * }
 * ```
 */
export function hasAnyRole(token: string | null | undefined, roles: string[]): boolean {
  const tokenRole = getTokenRole(token);
  return tokenRole !== null && roles.includes(tokenRole);
}
