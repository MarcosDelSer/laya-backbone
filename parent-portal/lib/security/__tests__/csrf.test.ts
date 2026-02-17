/**
 * Tests for CSRF token management utilities
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import {
  initCSRFProtection,
  fetchCSRFToken,
  storeCSRFToken,
  getCSRFToken,
  isCSRFTokenExpired,
  getValidCSRFToken,
  clearCSRFToken,
  getCSRFProtectedMethods,
  getCSRFExemptPaths,
  requiresCSRFProtection,
  validateCSRFTokenStructure,
  createCSRFHeaders,
  fetchWithCSRF,
  __internal,
} from '../csrf';

/**
 * Create a mock JWT token for testing
 * Format: header.payload.signature (all base64url encoded)
 */
function createMockJWT(payload: Record<string, any>): string {
  const header = { alg: 'HS256', typ: 'JWT' };

  const encodeBase64Url = (obj: any) => {
    const str = JSON.stringify(obj);
    return Buffer.from(str).toString('base64url');
  };

  const headerEncoded = encodeBase64Url(header);
  const payloadEncoded = encodeBase64Url(payload);
  const signature = 'mock-signature';

  return `${headerEncoded}.${payloadEncoded}.${signature}`;
}

/**
 * Generate a test CSRF token (mimicking backend behavior)
 */
function generateTestCSRFToken(expiresInMinutes: number = 60): string {
  const now = Math.floor(Date.now() / 1000);
  const exp = now + expiresInMinutes * 60;

  return createMockJWT({
    nonce: 'test-nonce-' + Math.random(),
    type: 'csrf',
    exp,
    iat: now,
  });
}

/**
 * Generate an expired test CSRF token
 */
function generateExpiredCSRFToken(): string {
  const now = Math.floor(Date.now() / 1000);
  const exp = now - 3600; // Expired 1 hour ago

  return createMockJWT({
    nonce: 'test-nonce-expired',
    type: 'csrf',
    exp,
    iat: now - 7200,
  });
}

/**
 * Generate an invalid token (wrong type)
 */
function generateInvalidTypeToken(): string {
  const now = Math.floor(Date.now() / 1000);
  const exp = now + 3600;

  return createMockJWT({
    nonce: 'test-nonce-invalid',
    type: 'not-csrf', // Wrong type
    exp,
    iat: now,
  });
}

describe('CSRF Token Management', () => {
  beforeEach(() => {
    // Reset config and token store before each test
    __internal.resetConfig();
    clearCSRFToken();

    // Clear all mocks
    vi.clearAllMocks();
  });

  afterEach(() => {
    // Restore all mocks
    vi.restoreAllMocks();
  });

  describe('initCSRFProtection', () => {
    it('should initialize with default configuration', () => {
      initCSRFProtection({});
      const config = __internal.config();

      expect(config.apiUrl).toBe('http://localhost:8000');
      expect(config.expirationBufferMinutes).toBe(5);
    });

    it('should initialize with custom configuration', () => {
      initCSRFProtection({
        apiUrl: 'https://api.example.com',
        expirationBufferMinutes: 10,
      });

      const config = __internal.config();
      expect(config.apiUrl).toBe('https://api.example.com');
      expect(config.expirationBufferMinutes).toBe(10);
    });

    it('should merge partial configuration with defaults', () => {
      initCSRFProtection({
        apiUrl: 'https://custom-api.com',
      });

      const config = __internal.config();
      expect(config.apiUrl).toBe('https://custom-api.com');
      expect(config.expirationBufferMinutes).toBe(5); // Default
    });
  });

  describe('fetchCSRFToken', () => {
    it('should fetch and store a valid CSRF token', async () => {
      const mockToken = generateTestCSRFToken();

      // Mock fetch
      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ csrf_token: mockToken }),
      });

      const token = await fetchCSRFToken();

      expect(token).toBe(mockToken);
      expect(getCSRFToken()).toBe(mockToken);
      expect(global.fetch).toHaveBeenCalledWith(
        'http://localhost:8000/api/v1/csrf-token',
        expect.objectContaining({
          method: 'GET',
          credentials: 'include',
        })
      );
    });

    it('should throw error on failed fetch', async () => {
      // Mock failed fetch
      global.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 500,
        statusText: 'Internal Server Error',
      });

      await expect(fetchCSRFToken()).rejects.toThrow(
        'CSRF token fetch error: Failed to fetch CSRF token: 500 Internal Server Error'
      );
    });

    it('should throw error if token not in response', async () => {
      // Mock fetch without token
      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({}),
      });

      await expect(fetchCSRFToken()).rejects.toThrow(
        'CSRF token fetch error: CSRF token not found in response'
      );
    });

    it('should throw error on network failure', async () => {
      // Mock network error
      global.fetch = vi.fn().mockRejectedValue(new Error('Network error'));

      await expect(fetchCSRFToken()).rejects.toThrow(
        'CSRF token fetch error: Network error'
      );
    });
  });

  describe('storeCSRFToken', () => {
    it('should store token and extract expiration', async () => {
      const mockToken = generateTestCSRFToken(30);

      storeCSRFToken(mockToken);

      const storedToken = getCSRFToken();
      expect(storedToken).toBe(mockToken);

      const tokenStore = __internal.tokenStore;
      expect(tokenStore.expiresAt).toBeGreaterThan(Date.now());
    });

    it('should handle malformed token gracefully', () => {
      const malformedToken = 'not-a-valid-jwt-token';

      storeCSRFToken(malformedToken);

      expect(getCSRFToken()).toBe(malformedToken);
      expect(__internal.tokenStore.expiresAt).toBeNull();
    });
  });

  describe('getCSRFToken', () => {
    it('should return null when no token is stored', () => {
      expect(getCSRFToken()).toBeNull();
    });

    it('should return stored token', async () => {
      const mockToken = generateTestCSRFToken();
      storeCSRFToken(mockToken);

      expect(getCSRFToken()).toBe(mockToken);
    });
  });

  describe('isCSRFTokenExpired', () => {
    it('should return true when no token is stored', () => {
      expect(isCSRFTokenExpired()).toBe(true);
    });

    it('should return false for valid token', async () => {
      const mockToken = generateTestCSRFToken(60);
      storeCSRFToken(mockToken);

      expect(isCSRFTokenExpired()).toBe(false);
    });

    it('should return true for expired token', async () => {
      const mockToken = generateExpiredCSRFToken();
      storeCSRFToken(mockToken);

      expect(isCSRFTokenExpired()).toBe(true);
    });

    it('should return true when token expires within buffer time', async () => {
      // Token expires in 3 minutes, buffer is 5 minutes
      const mockToken = generateTestCSRFToken(3 / 60); // 3 minutes
      storeCSRFToken(mockToken);

      initCSRFProtection({ expirationBufferMinutes: 5 });

      expect(isCSRFTokenExpired()).toBe(true);
    });

    it('should return false when token expires after buffer time', async () => {
      // Token expires in 10 minutes, buffer is 5 minutes
      const mockToken = generateTestCSRFToken(10);
      storeCSRFToken(mockToken);

      initCSRFProtection({ expirationBufferMinutes: 5 });

      expect(isCSRFTokenExpired()).toBe(false);
    });
  });

  describe('getValidCSRFToken', () => {
    it('should return existing valid token', async () => {
      const mockToken = generateTestCSRFToken(60);
      storeCSRFToken(mockToken);

      const token = await getValidCSRFToken();
      expect(token).toBe(mockToken);
    });

    it('should fetch new token when expired', async () => {
      const expiredToken = generateExpiredCSRFToken();
      const newToken = generateTestCSRFToken();

      storeCSRFToken(expiredToken);

      // Mock fetch for new token
      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ csrf_token: newToken }),
      });

      const token = await getValidCSRFToken();
      expect(token).toBe(newToken);
      expect(token).not.toBe(expiredToken);
    });

    it('should fetch new token when no token exists', async () => {
      const mockToken = generateTestCSRFToken();

      // Mock fetch
      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ csrf_token: mockToken }),
      });

      const token = await getValidCSRFToken();
      expect(token).toBe(mockToken);
    });
  });

  describe('clearCSRFToken', () => {
    it('should clear stored token', async () => {
      const mockToken = generateTestCSRFToken();
      storeCSRFToken(mockToken);

      expect(getCSRFToken()).toBe(mockToken);

      clearCSRFToken();

      expect(getCSRFToken()).toBeNull();
      expect(__internal.tokenStore.expiresAt).toBeNull();
    });
  });

  describe('getCSRFProtectedMethods', () => {
    it('should return state-changing HTTP methods', () => {
      const methods = getCSRFProtectedMethods();

      expect(methods).toEqual(['POST', 'PUT', 'DELETE', 'PATCH']);
      expect(methods).not.toContain('GET');
      expect(methods).not.toContain('HEAD');
      expect(methods).not.toContain('OPTIONS');
    });
  });

  describe('getCSRFExemptPaths', () => {
    it('should return exempt paths', () => {
      const paths = getCSRFExemptPaths();

      expect(paths).toContain('/api/health');
      expect(paths).toContain('/api/v1/webhook');
      expect(paths.length).toBeGreaterThan(0);
    });
  });

  describe('requiresCSRFProtection', () => {
    it('should require CSRF for POST requests', () => {
      expect(requiresCSRFProtection('/api/v1/users', 'POST')).toBe(true);
    });

    it('should require CSRF for PUT requests', () => {
      expect(requiresCSRFProtection('/api/v1/users/123', 'PUT')).toBe(true);
    });

    it('should require CSRF for DELETE requests', () => {
      expect(requiresCSRFProtection('/api/v1/users/123', 'DELETE')).toBe(true);
    });

    it('should require CSRF for PATCH requests', () => {
      expect(requiresCSRFProtection('/api/v1/users/123', 'PATCH')).toBe(true);
    });

    it('should not require CSRF for GET requests', () => {
      expect(requiresCSRFProtection('/api/v1/users', 'GET')).toBe(false);
    });

    it('should not require CSRF for HEAD requests', () => {
      expect(requiresCSRFProtection('/api/v1/users', 'HEAD')).toBe(false);
    });

    it('should not require CSRF for OPTIONS requests', () => {
      expect(requiresCSRFProtection('/api/v1/users', 'OPTIONS')).toBe(false);
    });

    it('should not require CSRF for exempt paths', () => {
      expect(requiresCSRFProtection('/', 'POST')).toBe(false);
      expect(requiresCSRFProtection('/api/health', 'POST')).toBe(false);
      expect(requiresCSRFProtection('/api/v1/webhook', 'POST')).toBe(false);
    });

    it('should default to POST method', () => {
      expect(requiresCSRFProtection('/api/v1/users')).toBe(true);
    });
  });

  describe('validateCSRFTokenStructure', () => {
    it('should validate a valid CSRF token', async () => {
      const mockToken = generateTestCSRFToken();

      expect(validateCSRFTokenStructure(mockToken)).toBe(true);
    });

    it('should reject expired token', async () => {
      const expiredToken = generateExpiredCSRFToken();

      expect(validateCSRFTokenStructure(expiredToken)).toBe(false);
    });

    it('should reject token with wrong type', async () => {
      const invalidToken = generateInvalidTypeToken();

      expect(validateCSRFTokenStructure(invalidToken)).toBe(false);
    });

    it('should reject malformed token', () => {
      const malformedToken = 'not-a-valid-jwt';

      expect(validateCSRFTokenStructure(malformedToken)).toBe(false);
    });
  });

  describe('createCSRFHeaders', () => {
    it('should create headers with CSRF token', async () => {
      const mockToken = generateTestCSRFToken();

      // Mock fetch
      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ csrf_token: mockToken }),
      });

      const headers = await createCSRFHeaders();

      expect(headers.get('X-CSRF-Token')).toBe(mockToken);
    });

    it('should merge with existing headers', async () => {
      const mockToken = generateTestCSRFToken();
      storeCSRFToken(mockToken);

      const existingHeaders = {
        'Content-Type': 'application/json',
        Authorization: 'Bearer test-token',
      };

      const headers = await createCSRFHeaders(existingHeaders);

      expect(headers.get('X-CSRF-Token')).toBe(mockToken);
      expect(headers.get('Content-Type')).toBe('application/json');
      expect(headers.get('Authorization')).toBe('Bearer test-token');
    });
  });

  describe('fetchWithCSRF', () => {
    it('should add CSRF token for POST requests', async () => {
      const mockToken = generateTestCSRFToken();
      storeCSRFToken(mockToken);

      // Mock fetch
      const mockFetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ success: true }),
      });
      global.fetch = mockFetch;

      await fetchWithCSRF('http://localhost:8000/api/v1/users', {
        method: 'POST',
        body: JSON.stringify({ name: 'Test' }),
      });

      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8000/api/v1/users',
        expect.objectContaining({
          method: 'POST',
          credentials: 'include',
        })
      );

      const callArgs = mockFetch.mock.calls[0];
      const headers = callArgs[1]?.headers as Headers;
      expect(headers.get('X-CSRF-Token')).toBe(mockToken);
    });

    it('should not add CSRF token for GET requests', async () => {
      // Mock fetch
      const mockFetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ data: [] }),
      });
      global.fetch = mockFetch;

      await fetchWithCSRF('http://localhost:8000/api/v1/users', {
        method: 'GET',
      });

      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8000/api/v1/users',
        expect.objectContaining({
          method: 'GET',
          credentials: 'include',
        })
      );

      // Should not have X-CSRF-Token header
      const callArgs = mockFetch.mock.calls[0];
      const options = callArgs[1];
      expect(options?.headers).toBeUndefined();
    });

    it('should not add CSRF token for exempt paths', async () => {
      // Mock fetch
      const mockFetch = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ status: 'ok' }),
      });
      global.fetch = mockFetch;

      await fetchWithCSRF('http://localhost:8000/', {
        method: 'POST',
      });

      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8000/',
        expect.objectContaining({
          method: 'POST',
          credentials: 'include',
        })
      );

      // Should not have X-CSRF-Token header for exempt path
      const callArgs = mockFetch.mock.calls[0];
      const options = callArgs[1];
      expect(options?.headers).toBeUndefined();
    });

    it('should fetch new token if expired', async () => {
      const expiredToken = generateExpiredCSRFToken();
      const newToken = generateTestCSRFToken();

      storeCSRFToken(expiredToken);

      // Mock fetch - first for token refresh, second for actual request
      const mockFetch = vi
        .fn()
        .mockResolvedValueOnce({
          ok: true,
          json: async () => ({ csrf_token: newToken }),
        })
        .mockResolvedValueOnce({
          ok: true,
          json: async () => ({ success: true }),
        });
      global.fetch = mockFetch;

      await fetchWithCSRF('http://localhost:8000/api/v1/users', {
        method: 'POST',
      });

      // Should have called fetch twice: once for token, once for request
      expect(mockFetch).toHaveBeenCalledTimes(2);

      // First call should be for CSRF token
      expect(mockFetch.mock.calls[0][0]).toContain('/api/v1/csrf-token');

      // Second call should include new token
      const secondCallArgs = mockFetch.mock.calls[1];
      const headers = secondCallArgs[1]?.headers as Headers;
      expect(headers.get('X-CSRF-Token')).toBe(newToken);
    });
  });
});
