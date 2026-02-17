import { describe, it, expect, beforeEach, vi } from 'vitest';
import {
  ACCESS_TOKEN_COOKIE,
  REFRESH_TOKEN_COOKIE,
  DEFAULT_TOKEN_EXPIRY,
  REFRESH_TOKEN_EXPIRY,
  safeDecodeToken,
  isTokenExpiringSoon,
  isTokenExpired,
  getTokenTimeToExpiry,
  extractUserFromToken,
  getValidatedUser,
  hasValidTokenFormat,
  hasRequiredClaims,
  getTokenIssuedAt,
  getTokenExpiresAt,
  sanitizeTokenForLogging,
  areTokensEqual,
  isBrowserEnvironment,
  areCookiesAvailable,
  createBearerHeaders,
  getTokenRole,
  hasRole,
  hasAnyRole,
  type TokenPayload,
  type User,
} from '../token';

// ============================================================================
// Test Helpers
// ============================================================================

function createTestToken(payload: Record<string, unknown>): string {
  const header = { alg: 'HS256', typ: 'JWT' };
  const encodedHeader = Buffer.from(JSON.stringify(header)).toString('base64');
  const encodedPayload = Buffer.from(JSON.stringify(payload)).toString('base64');
  const signature = 'test-signature';

  return `${encodedHeader}.${encodedPayload}.${signature}`;
}

function createValidPayload(overrides: Partial<TokenPayload> = {}): TokenPayload {
  const now = Math.floor(Date.now() / 1000);
  return {
    sub: 'user-123',
    email: 'test@example.com',
    role: 'parent',
    exp: now + 3600, // Expires in 1 hour
    iat: now,
    ...overrides,
  };
}

// ============================================================================
// Tests
// ============================================================================

describe('Token Storage Utilities', () => {
  describe('Constants Export', () => {
    it('exports correct cookie names', () => {
      expect(ACCESS_TOKEN_COOKIE).toBe('access_token');
      expect(REFRESH_TOKEN_COOKIE).toBe('refresh_token');
    });

    it('exports correct expiry times', () => {
      expect(DEFAULT_TOKEN_EXPIRY).toBe(60 * 60 * 24 * 7); // 7 days
      expect(REFRESH_TOKEN_EXPIRY).toBe(60 * 60 * 24 * 30); // 30 days
    });
  });

  describe('safeDecodeToken', () => {
    it('decodes valid JWT token', () => {
      const payload = createValidPayload();
      const token = createTestToken(payload);

      const decoded = safeDecodeToken(token);

      expect(decoded).toEqual(payload);
    });

    it('returns null for null token', () => {
      expect(safeDecodeToken(null)).toBeNull();
    });

    it('returns null for undefined token', () => {
      expect(safeDecodeToken(undefined)).toBeNull();
    });

    it('returns null for empty string', () => {
      expect(safeDecodeToken('')).toBeNull();
    });

    it('returns null for whitespace string', () => {
      expect(safeDecodeToken('   ')).toBeNull();
    });

    it('returns null for invalid token format (too few parts)', () => {
      expect(safeDecodeToken('header.payload')).toBeNull();
    });

    it('returns null for invalid token format (too many parts)', () => {
      expect(safeDecodeToken('part1.part2.part3.part4')).toBeNull();
    });

    it('returns null for token with empty parts', () => {
      expect(safeDecodeToken('header..signature')).toBeNull();
    });

    it('returns null for malformed base64', () => {
      const token = 'header.invalid-base64-!!!.signature';
      expect(safeDecodeToken(token)).toBeNull();
    });

    it('returns null for token missing required fields', () => {
      const payload = { sub: 'user-123' }; // Missing email and role
      const token = createTestToken(payload);

      expect(safeDecodeToken(token)).toBeNull();
    });

    it('handles tokens with padding correctly', () => {
      const payload = createValidPayload();
      const token = createTestToken(payload);

      const decoded = safeDecodeToken(token);

      expect(decoded).not.toBeNull();
      expect(decoded?.sub).toBe('user-123');
    });

    it('does not log errors in production', () => {
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'production';
      const consoleSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

      safeDecodeToken('invalid-token');

      expect(consoleSpy).not.toHaveBeenCalled();

      process.env.NODE_ENV = originalEnv;
      consoleSpy.mockRestore();
    });
  });

  describe('isTokenExpired', () => {
    it('returns false for non-expired token', () => {
      const payload = createValidPayload({ exp: Math.floor(Date.now() / 1000) + 3600 });
      const token = createTestToken(payload);

      expect(isTokenExpired(token)).toBe(false);
    });

    it('returns true for expired token', () => {
      const payload = createValidPayload({ exp: Math.floor(Date.now() / 1000) - 3600 });
      const token = createTestToken(payload);

      expect(isTokenExpired(token)).toBe(true);
    });

    it('returns true for null token', () => {
      expect(isTokenExpired(null)).toBe(true);
    });

    it('returns true for undefined token', () => {
      expect(isTokenExpired(undefined)).toBe(true);
    });

    it('returns true for token without exp claim', () => {
      const payload = { sub: 'user-123', email: 'test@example.com', role: 'parent', iat: Math.floor(Date.now() / 1000) };
      const token = createTestToken(payload);

      expect(isTokenExpired(token)).toBe(true);
    });

    it('returns true for invalid token', () => {
      expect(isTokenExpired('invalid-token')).toBe(true);
    });
  });

  describe('isTokenExpiringSoon', () => {
    it('returns false for token expiring in 2 hours (default buffer)', () => {
      const payload = createValidPayload({ exp: Math.floor(Date.now() / 1000) + 7200 });
      const token = createTestToken(payload);

      expect(isTokenExpiringSoon(token)).toBe(false);
    });

    it('returns true for token expiring in 30 seconds (default buffer)', () => {
      const payload = createValidPayload({ exp: Math.floor(Date.now() / 1000) + 30 });
      const token = createTestToken(payload);

      expect(isTokenExpiringSoon(token)).toBe(true);
    });

    it('returns false when token expires after custom buffer', () => {
      const payload = createValidPayload({ exp: Math.floor(Date.now() / 1000) + 400 });
      const token = createTestToken(payload);

      expect(isTokenExpiringSoon(token, 300)).toBe(false); // 5 minute buffer
    });

    it('returns true when token expires within custom buffer', () => {
      const payload = createValidPayload({ exp: Math.floor(Date.now() / 1000) + 200 });
      const token = createTestToken(payload);

      expect(isTokenExpiringSoon(token, 300)).toBe(true); // 5 minute buffer
    });

    it('returns true for null token', () => {
      expect(isTokenExpiringSoon(null)).toBe(true);
    });

    it('returns true for token without exp claim', () => {
      const payload = { sub: 'user-123', email: 'test@example.com', role: 'parent', iat: Math.floor(Date.now() / 1000) };
      const token = createTestToken(payload);

      expect(isTokenExpiringSoon(token)).toBe(true);
    });
  });

  describe('getTokenTimeToExpiry', () => {
    it('returns correct time for valid token', () => {
      const now = Math.floor(Date.now() / 1000);
      const payload = createValidPayload({ exp: now + 3600 });
      const token = createTestToken(payload);

      const timeLeft = getTokenTimeToExpiry(token);

      expect(timeLeft).toBeGreaterThan(3595); // Allow small time diff
      expect(timeLeft).toBeLessThanOrEqual(3600);
    });

    it('returns 0 for expired token', () => {
      const payload = createValidPayload({ exp: Math.floor(Date.now() / 1000) - 3600 });
      const token = createTestToken(payload);

      expect(getTokenTimeToExpiry(token)).toBe(0);
    });

    it('returns 0 for null token', () => {
      expect(getTokenTimeToExpiry(null)).toBe(0);
    });

    it('returns 0 for invalid token', () => {
      expect(getTokenTimeToExpiry('invalid-token')).toBe(0);
    });

    it('returns 0 for token without exp claim', () => {
      const payload = { sub: 'user-123', email: 'test@example.com', role: 'parent', iat: Math.floor(Date.now() / 1000) };
      const token = createTestToken(payload);

      expect(getTokenTimeToExpiry(token)).toBe(0);
    });
  });

  describe('extractUserFromToken', () => {
    it('extracts user information from valid token', () => {
      const payload = createValidPayload({
        sub: 'user-456',
        email: 'user@example.com',
        role: 'teacher',
        firstName: 'John',
        lastName: 'Doe',
      });
      const token = createTestToken(payload);

      const user = extractUserFromToken(token);

      expect(user).toEqual({
        id: 'user-456',
        email: 'user@example.com',
        role: 'teacher',
        firstName: 'John',
        lastName: 'Doe',
      });
    });

    it('handles token without optional fields', () => {
      const payload = createValidPayload();
      const token = createTestToken(payload);

      const user = extractUserFromToken(token);

      expect(user).toEqual({
        id: 'user-123',
        email: 'test@example.com',
        role: 'parent',
        firstName: undefined,
        lastName: undefined,
      });
    });

    it('returns null for null token', () => {
      expect(extractUserFromToken(null)).toBeNull();
    });

    it('returns null for invalid token', () => {
      expect(extractUserFromToken('invalid-token')).toBeNull();
    });
  });

  describe('getValidatedUser', () => {
    it('returns user for valid non-expired token', () => {
      const payload = createValidPayload({ exp: Math.floor(Date.now() / 1000) + 3600 });
      const token = createTestToken(payload);

      const user = getValidatedUser(token);

      expect(user).not.toBeNull();
      expect(user?.id).toBe('user-123');
    });

    it('returns null for expired token', () => {
      const payload = createValidPayload({ exp: Math.floor(Date.now() / 1000) - 3600 });
      const token = createTestToken(payload);

      const user = getValidatedUser(token);

      expect(user).toBeNull();
    });

    it('returns null for null token', () => {
      expect(getValidatedUser(null)).toBeNull();
    });

    it('returns null for invalid token', () => {
      expect(getValidatedUser('invalid-token')).toBeNull();
    });
  });

  describe('hasValidTokenFormat', () => {
    it('returns true for valid JWT format', () => {
      const token = 'header.payload.signature';
      expect(hasValidTokenFormat(token)).toBe(true);
    });

    it('returns false for null token', () => {
      expect(hasValidTokenFormat(null)).toBe(false);
    });

    it('returns false for undefined token', () => {
      expect(hasValidTokenFormat(undefined)).toBe(false);
    });

    it('returns false for empty string', () => {
      expect(hasValidTokenFormat('')).toBe(false);
    });

    it('returns false for token with too few parts', () => {
      expect(hasValidTokenFormat('header.payload')).toBe(false);
    });

    it('returns false for token with too many parts', () => {
      expect(hasValidTokenFormat('part1.part2.part3.part4')).toBe(false);
    });

    it('returns false for token with empty parts', () => {
      expect(hasValidTokenFormat('header..signature')).toBe(false);
    });

    it('handles tokens with whitespace', () => {
      expect(hasValidTokenFormat('  header.payload.signature  ')).toBe(true);
    });
  });

  describe('hasRequiredClaims', () => {
    it('returns true when all required claims present', () => {
      const payload = createValidPayload();
      const token = createTestToken(payload);

      expect(hasRequiredClaims(token, ['sub', 'email', 'role'])).toBe(true);
    });

    it('returns false when some claims missing', () => {
      const payload = createValidPayload();
      const token = createTestToken(payload);

      expect(hasRequiredClaims(token, ['sub', 'email', 'missing_claim'])).toBe(false);
    });

    it('returns false for null token', () => {
      expect(hasRequiredClaims(null, ['sub'])).toBe(false);
    });

    it('returns false for invalid token', () => {
      expect(hasRequiredClaims('invalid', ['sub'])).toBe(false);
    });

    it('returns false when claim is null', () => {
      const payload = { ...createValidPayload(), customClaim: null };
      const token = createTestToken(payload);

      expect(hasRequiredClaims(token, ['customClaim'])).toBe(false);
    });

    it('returns false when claim is undefined', () => {
      const payload = { ...createValidPayload(), customClaim: undefined };
      const token = createTestToken(payload);

      expect(hasRequiredClaims(token, ['customClaim'])).toBe(false);
    });
  });

  describe('getTokenIssuedAt', () => {
    it('returns correct issued-at date', () => {
      const now = Math.floor(Date.now() / 1000);
      const payload = createValidPayload({ iat: now });
      const token = createTestToken(payload);

      const issuedAt = getTokenIssuedAt(token);

      expect(issuedAt).toBeInstanceOf(Date);
      expect(issuedAt?.getTime()).toBe(now * 1000);
    });

    it('returns null for token without iat claim', () => {
      const payload = { sub: 'user-123', email: 'test@example.com', role: 'parent', exp: Math.floor(Date.now() / 1000) + 3600 };
      const token = createTestToken(payload);

      expect(getTokenIssuedAt(token)).toBeNull();
    });

    it('returns null for null token', () => {
      expect(getTokenIssuedAt(null)).toBeNull();
    });

    it('returns null for invalid token', () => {
      expect(getTokenIssuedAt('invalid-token')).toBeNull();
    });
  });

  describe('getTokenExpiresAt', () => {
    it('returns correct expiration date', () => {
      const now = Math.floor(Date.now() / 1000);
      const exp = now + 3600;
      const payload = createValidPayload({ exp });
      const token = createTestToken(payload);

      const expiresAt = getTokenExpiresAt(token);

      expect(expiresAt).toBeInstanceOf(Date);
      expect(expiresAt?.getTime()).toBe(exp * 1000);
    });

    it('returns null for token without exp claim', () => {
      const payload = { sub: 'user-123', email: 'test@example.com', role: 'parent', iat: Math.floor(Date.now() / 1000) };
      const token = createTestToken(payload);

      expect(getTokenExpiresAt(token)).toBeNull();
    });

    it('returns null for null token', () => {
      expect(getTokenExpiresAt(null)).toBeNull();
    });

    it('returns null for invalid token', () => {
      expect(getTokenExpiresAt('invalid-token')).toBeNull();
    });
  });

  describe('sanitizeTokenForLogging', () => {
    it('sanitizes valid token', () => {
      const token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.signature';
      const sanitized = sanitizeTokenForLogging(token);

      expect(sanitized).toMatch(/^eyJhbG\.\.\.nature$/);
      expect(sanitized).not.toContain('eyJzdWIiOiI');
    });

    it('returns [no token] for null', () => {
      expect(sanitizeTokenForLogging(null)).toBe('[no token]');
    });

    it('returns [no token] for undefined', () => {
      expect(sanitizeTokenForLogging(undefined)).toBe('[no token]');
    });

    it('returns [token too short] for short strings', () => {
      expect(sanitizeTokenForLogging('short')).toBe('[token too short]');
    });
  });

  describe('areTokensEqual', () => {
    it('returns true for identical tokens', () => {
      const token = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIn0.signature';
      expect(areTokensEqual(token, token)).toBe(true);
    });

    it('returns false for different tokens', () => {
      const token1 = 'token1.payload.signature';
      const token2 = 'token2.payload.signature';
      expect(areTokensEqual(token1, token2)).toBe(false);
    });

    it('returns false for tokens of different length', () => {
      const token1 = 'short.token';
      const token2 = 'longer.token.here';
      expect(areTokensEqual(token1, token2)).toBe(false);
    });

    it('returns true when both tokens are null', () => {
      expect(areTokensEqual(null, null)).toBe(true);
    });

    it('returns false when one token is null', () => {
      expect(areTokensEqual('token', null)).toBe(false);
      expect(areTokensEqual(null, 'token')).toBe(false);
    });

    it('uses timing-safe comparison', () => {
      // This is a basic test - true timing-safety would require specialized testing
      const token1 = 'a'.repeat(100);
      const token2 = 'a'.repeat(99) + 'b';

      expect(areTokensEqual(token1, token2)).toBe(false);
    });
  });

  describe('isBrowserEnvironment', () => {
    it('returns false in test environment', () => {
      // In vitest/node environment, window is not defined
      expect(isBrowserEnvironment()).toBe(false);
    });
  });

  describe('areCookiesAvailable', () => {
    it('returns false in test environment', () => {
      expect(areCookiesAvailable()).toBe(false);
    });
  });

  describe('createBearerHeaders', () => {
    it('creates headers with Bearer token', () => {
      const headers = createBearerHeaders('test-token-123');

      expect(headers).toEqual({
        'Authorization': 'Bearer test-token-123',
        'Content-Type': 'application/json',
      });
    });

    it('handles empty token', () => {
      const headers = createBearerHeaders('');

      expect(headers).toEqual({
        'Authorization': 'Bearer ',
        'Content-Type': 'application/json',
      });
    });
  });

  describe('getTokenRole', () => {
    it('extracts role from valid token', () => {
      const payload = createValidPayload({ role: 'admin' });
      const token = createTestToken(payload);

      expect(getTokenRole(token)).toBe('admin');
    });

    it('returns null for null token', () => {
      expect(getTokenRole(null)).toBeNull();
    });

    it('returns null for invalid token', () => {
      expect(getTokenRole('invalid-token')).toBeNull();
    });
  });

  describe('hasRole', () => {
    it('returns true when token has specified role', () => {
      const payload = createValidPayload({ role: 'parent' });
      const token = createTestToken(payload);

      expect(hasRole(token, 'parent')).toBe(true);
    });

    it('returns false when token has different role', () => {
      const payload = createValidPayload({ role: 'parent' });
      const token = createTestToken(payload);

      expect(hasRole(token, 'admin')).toBe(false);
    });

    it('returns false for null token', () => {
      expect(hasRole(null, 'admin')).toBe(false);
    });

    it('returns false for invalid token', () => {
      expect(hasRole('invalid', 'admin')).toBe(false);
    });
  });

  describe('hasAnyRole', () => {
    it('returns true when token has one of the roles', () => {
      const payload = createValidPayload({ role: 'teacher' });
      const token = createTestToken(payload);

      expect(hasAnyRole(token, ['admin', 'teacher', 'parent'])).toBe(true);
    });

    it('returns false when token has none of the roles', () => {
      const payload = createValidPayload({ role: 'parent' });
      const token = createTestToken(payload);

      expect(hasAnyRole(token, ['admin', 'teacher'])).toBe(false);
    });

    it('returns false for null token', () => {
      expect(hasAnyRole(null, ['admin'])).toBe(false);
    });

    it('returns false for invalid token', () => {
      expect(hasAnyRole('invalid', ['admin'])).toBe(false);
    });

    it('returns false for empty roles array', () => {
      const payload = createValidPayload({ role: 'parent' });
      const token = createTestToken(payload);

      expect(hasAnyRole(token, [])).toBe(false);
    });
  });
});
