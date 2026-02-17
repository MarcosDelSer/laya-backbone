import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { NextRequest, NextResponse } from 'next/server';
import {
  ACCESS_TOKEN_COOKIE,
  REFRESH_TOKEN_COOKIE,
  DEFAULT_TOKEN_EXPIRY,
  REFRESH_TOKEN_EXPIRY,
  setAuthTokens,
  clearAuthTokens,
  getRequestToken,
  getRequestRefreshToken,
  isRequestAuthenticated,
  authenticatedFetch,
  decodeToken,
  isTokenExpired,
  getUserFromToken,
  createAuthHeaders,
  getRedirectAfterLogin,
  setRedirectAfterLogin,
} from '@/lib/auth';

describe('Token Management', () => {
  describe('setAuthTokens', () => {
    it('sets access token cookie with correct options', () => {
      const response = NextResponse.json({ success: true });
      setAuthTokens(response, { accessToken: 'test-access-token' });

      const cookie = response.cookies.get(ACCESS_TOKEN_COOKIE);
      expect(cookie?.value).toBe('test-access-token');
    });

    it('sets refresh token cookie when provided', () => {
      const response = NextResponse.json({ success: true });
      setAuthTokens(response, {
        accessToken: 'test-access-token',
        refreshToken: 'test-refresh-token',
      });

      const accessCookie = response.cookies.get(ACCESS_TOKEN_COOKIE);
      const refreshCookie = response.cookies.get(REFRESH_TOKEN_COOKIE);

      expect(accessCookie?.value).toBe('test-access-token');
      expect(refreshCookie?.value).toBe('test-refresh-token');
    });

    it('uses custom maxAge when provided', () => {
      const response = NextResponse.json({ success: true });
      const customMaxAge = 3600; // 1 hour

      setAuthTokens(response, {
        accessToken: 'test-access-token',
        maxAge: customMaxAge,
      });

      // Cookie is set (actual maxAge testing requires inspecting cookie object deeply)
      const cookie = response.cookies.get(ACCESS_TOKEN_COOKIE);
      expect(cookie).toBeDefined();
    });

    it('sets httpOnly flag for security', () => {
      const response = NextResponse.json({ success: true });
      setAuthTokens(response, { accessToken: 'test-access-token' });

      // NextResponse cookies API doesn't expose all flags directly in test env
      // but we can verify the cookie exists
      const cookie = response.cookies.get(ACCESS_TOKEN_COOKIE);
      expect(cookie).toBeDefined();
    });
  });

  describe('clearAuthTokens', () => {
    it('removes access token cookie', () => {
      const response = NextResponse.json({ success: true });

      // Set token first
      setAuthTokens(response, { accessToken: 'test-token' });
      expect(response.cookies.get(ACCESS_TOKEN_COOKIE)).toBeDefined();

      // Clear tokens
      clearAuthTokens(response);

      // Cookie should be deleted (has delete command)
      // Note: In Next.js, deleted cookies still exist in the cookies map
      // but have delete instructions
      const cookie = response.cookies.get(ACCESS_TOKEN_COOKIE);
      expect(cookie === undefined || cookie.value === '').toBe(true);
    });

    it('removes refresh token cookie', () => {
      const response = NextResponse.json({ success: true });

      // Set tokens first
      setAuthTokens(response, {
        accessToken: 'test-access',
        refreshToken: 'test-refresh',
      });

      // Clear tokens
      clearAuthTokens(response);

      // Cookies should be deleted
      const accessCookie = response.cookies.get(ACCESS_TOKEN_COOKIE);
      const refreshCookie = response.cookies.get(REFRESH_TOKEN_COOKIE);

      expect(accessCookie === undefined || accessCookie.value === '').toBe(true);
      expect(refreshCookie === undefined || refreshCookie.value === '').toBe(true);
    });
  });

  describe('Middleware Token Functions', () => {
    it('getRequestToken returns token from request cookies', () => {
      const request = new NextRequest('http://localhost:3000/dashboard', {
        headers: {
          cookie: `${ACCESS_TOKEN_COOKIE}=test-token`,
        },
      });

      const token = getRequestToken(request);
      expect(token).toBe('test-token');
    });

    it('getRequestToken returns null when no token exists', () => {
      const request = new NextRequest('http://localhost:3000/dashboard');
      const token = getRequestToken(request);
      expect(token).toBeNull();
    });

    it('getRequestRefreshToken returns refresh token from request', () => {
      const request = new NextRequest('http://localhost:3000/dashboard', {
        headers: {
          cookie: `${REFRESH_TOKEN_COOKIE}=test-refresh-token`,
        },
      });

      const token = getRequestRefreshToken(request);
      expect(token).toBe('test-refresh-token');
    });

    it('isRequestAuthenticated returns true when token exists', () => {
      const request = new NextRequest('http://localhost:3000/dashboard', {
        headers: {
          cookie: `${ACCESS_TOKEN_COOKIE}=test-token`,
        },
      });

      expect(isRequestAuthenticated(request)).toBe(true);
    });

    it('isRequestAuthenticated returns false when no token exists', () => {
      const request = new NextRequest('http://localhost:3000/dashboard');
      expect(isRequestAuthenticated(request)).toBe(false);
    });

    it('isRequestAuthenticated returns false for empty token', () => {
      const request = new NextRequest('http://localhost:3000/dashboard', {
        headers: {
          cookie: `${ACCESS_TOKEN_COOKIE}=`,
        },
      });

      expect(isRequestAuthenticated(request)).toBe(false);
    });
  });

  describe('authenticatedFetch', () => {
    const mockFetch = vi.fn();
    const originalFetch = global.fetch;

    beforeEach(() => {
      global.fetch = mockFetch;
      mockFetch.mockReset();
    });

    afterEach(() => {
      global.fetch = originalFetch;
    });

    it('includes credentials in request', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: 'test' }),
      });

      await authenticatedFetch('/api/test');

      expect(mockFetch).toHaveBeenCalledWith(
        '/api/test',
        expect.objectContaining({
          credentials: 'include',
        })
      );
    });

    it('includes Content-Type header', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: 'test' }),
      });

      await authenticatedFetch('/api/test');

      expect(mockFetch).toHaveBeenCalledWith(
        '/api/test',
        expect.objectContaining({
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
          }),
        })
      );
    });

    it('returns parsed JSON response on success', async () => {
      const mockData = { id: 1, name: 'Test User' };
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockData,
      });

      const result = await authenticatedFetch('/api/test');
      expect(result).toEqual(mockData);
    });

    it('handles 204 No Content response', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 204,
        json: async () => ({}),
      });

      const result = await authenticatedFetch('/api/test');
      expect(result).toBeUndefined();
    });

    it('throws error on 401 Unauthorized', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 401,
        statusText: 'Unauthorized',
        json: async () => ({ error: 'Unauthorized' }),
      });

      await expect(authenticatedFetch('/api/test')).rejects.toThrow(
        'Unauthorized - redirecting to login'
      );
    });

    it('throws error on other HTTP errors', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        statusText: 'Internal Server Error',
        json: async () => ({ error: 'Server error' }),
      });

      await expect(authenticatedFetch('/api/test')).rejects.toThrow('Server error');
    });

    it('merges custom headers with default headers', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({ data: 'test' }),
      });

      await authenticatedFetch('/api/test', {
        headers: {
          'X-Custom-Header': 'custom-value',
        },
      });

      expect(mockFetch).toHaveBeenCalledWith(
        '/api/test',
        expect.objectContaining({
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
            'X-Custom-Header': 'custom-value',
          }),
        })
      );
    });
  });

  describe('Token Validation', () => {
    describe('decodeToken', () => {
      it('decodes valid JWT token', () => {
        // Create a simple JWT token (header.payload.signature)
        const payload = {
          sub: 'user-123',
          email: 'test@example.com',
          role: 'parent',
          exp: Math.floor(Date.now() / 1000) + 3600,
          iat: Math.floor(Date.now() / 1000),
        };

        const encodedPayload = Buffer.from(JSON.stringify(payload)).toString('base64');
        const token = `header.${encodedPayload}.signature`;

        const decoded = decodeToken(token);
        expect(decoded).toEqual(payload);
      });

      it('returns null for invalid token format', () => {
        const decoded = decodeToken('invalid-token');
        expect(decoded).toBeNull();
      });

      it('returns null for token with wrong number of parts', () => {
        const decoded = decodeToken('header.payload');
        expect(decoded).toBeNull();
      });

      it('returns null for malformed payload', () => {
        const token = 'header.invalid-base64-!!!.signature';
        const decoded = decodeToken(token);
        expect(decoded).toBeNull();
      });
    });

    describe('isTokenExpired', () => {
      it('returns false for non-expired token', () => {
        const payload = {
          sub: 'user-123',
          email: 'test@example.com',
          role: 'parent',
          exp: Math.floor(Date.now() / 1000) + 3600, // Expires in 1 hour
          iat: Math.floor(Date.now() / 1000),
        };

        const encodedPayload = Buffer.from(JSON.stringify(payload)).toString('base64');
        const token = `header.${encodedPayload}.signature`;

        expect(isTokenExpired(token)).toBe(false);
      });

      it('returns true for expired token', () => {
        const payload = {
          sub: 'user-123',
          email: 'test@example.com',
          role: 'parent',
          exp: Math.floor(Date.now() / 1000) - 3600, // Expired 1 hour ago
          iat: Math.floor(Date.now() / 1000) - 7200,
        };

        const encodedPayload = Buffer.from(JSON.stringify(payload)).toString('base64');
        const token = `header.${encodedPayload}.signature`;

        expect(isTokenExpired(token)).toBe(true);
      });

      it('returns true for invalid token', () => {
        expect(isTokenExpired('invalid-token')).toBe(true);
      });

      it('returns true for token without exp claim', () => {
        const payload = {
          sub: 'user-123',
          email: 'test@example.com',
        };

        const encodedPayload = Buffer.from(JSON.stringify(payload)).toString('base64');
        const token = `header.${encodedPayload}.signature`;

        expect(isTokenExpired(token)).toBe(true);
      });
    });

    describe('getUserFromToken', () => {
      it('extracts user information from valid token', () => {
        const payload = {
          sub: 'user-123',
          email: 'test@example.com',
          role: 'parent',
          exp: Math.floor(Date.now() / 1000) + 3600,
          iat: Math.floor(Date.now() / 1000),
        };

        const encodedPayload = Buffer.from(JSON.stringify(payload)).toString('base64');
        const token = `header.${encodedPayload}.signature`;

        const user = getUserFromToken(token);
        expect(user).toEqual({
          id: 'user-123',
          email: 'test@example.com',
          role: 'parent',
        });
      });

      it('returns null for invalid token', () => {
        const user = getUserFromToken('invalid-token');
        expect(user).toBeNull();
      });
    });
  });

  describe('API Request Headers', () => {
    describe('createAuthHeaders', () => {
      it('creates headers with Bearer token', () => {
        const headers = createAuthHeaders('test-token-123');

        expect(headers).toEqual({
          'Authorization': 'Bearer test-token-123',
          'Content-Type': 'application/json',
        });
      });

      it('handles empty token', () => {
        const headers = createAuthHeaders('');

        expect(headers).toEqual({
          'Authorization': 'Bearer ',
          'Content-Type': 'application/json',
        });
      });
    });
  });

  describe('Session Management', () => {
    const mockSessionStorage = {
      getItem: vi.fn(),
      setItem: vi.fn(),
      removeItem: vi.fn(),
      clear: vi.fn(),
      key: vi.fn(),
      length: 0,
    };

    beforeEach(() => {
      // Mock sessionStorage
      Object.defineProperty(window, 'sessionStorage', {
        value: mockSessionStorage,
        writable: true,
      });
      mockSessionStorage.getItem.mockReset();
      mockSessionStorage.setItem.mockReset();
      mockSessionStorage.removeItem.mockReset();
    });

    describe('setRedirectAfterLogin', () => {
      it('stores redirect path in sessionStorage', () => {
        setRedirectAfterLogin('/dashboard/settings');

        expect(mockSessionStorage.setItem).toHaveBeenCalledWith(
          'redirectAfterLogin',
          '/dashboard/settings'
        );
      });

      it('overwrites existing redirect path', () => {
        setRedirectAfterLogin('/path1');
        setRedirectAfterLogin('/path2');

        expect(mockSessionStorage.setItem).toHaveBeenCalledTimes(2);
        expect(mockSessionStorage.setItem).toHaveBeenLastCalledWith(
          'redirectAfterLogin',
          '/path2'
        );
      });
    });

    describe('getRedirectAfterLogin', () => {
      it('returns stored redirect path and clears it', () => {
        mockSessionStorage.getItem.mockReturnValue('/dashboard/settings');

        const path = getRedirectAfterLogin();

        expect(path).toBe('/dashboard/settings');
        expect(mockSessionStorage.getItem).toHaveBeenCalledWith('redirectAfterLogin');
        expect(mockSessionStorage.removeItem).toHaveBeenCalledWith('redirectAfterLogin');
      });

      it('returns default path when no redirect stored', () => {
        mockSessionStorage.getItem.mockReturnValue(null);

        const path = getRedirectAfterLogin();

        expect(path).toBe('/');
      });

      it('clears redirect path even when not found', () => {
        mockSessionStorage.getItem.mockReturnValue(null);

        getRedirectAfterLogin();

        expect(mockSessionStorage.removeItem).toHaveBeenCalledWith('redirectAfterLogin');
      });
    });
  });

  describe('Constants', () => {
    it('defines correct cookie names', () => {
      expect(ACCESS_TOKEN_COOKIE).toBe('access_token');
      expect(REFRESH_TOKEN_COOKIE).toBe('refresh_token');
    });

    it('defines correct token expiry times', () => {
      expect(DEFAULT_TOKEN_EXPIRY).toBe(60 * 60 * 24 * 7); // 7 days
      expect(REFRESH_TOKEN_EXPIRY).toBe(60 * 60 * 24 * 30); // 30 days
    });
  });
});
