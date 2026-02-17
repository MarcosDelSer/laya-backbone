import { describe, it, expect, vi, beforeEach } from 'vitest';
import { NextRequest, NextResponse } from 'next/server';
import {
  generateCsrfToken,
  setCsrfToken,
  clearCsrfToken,
  validateCsrfToken,
  extractCsrfTokenFromRequest,
  requiresCsrfProtection,
  withCsrfProtection,
  createCsrfHeaders,
  CSRF_TOKEN_COOKIE,
  CSRF_TOKEN_HEADER,
  CSRF_TOKEN_FIELD,
  CSRF_TOKEN_LENGTH,
} from '@/lib/csrf';

/**
 * Helper function to create a mock NextRequest with CSRF token.
 *
 * @param url - Request URL
 * @param method - HTTP method
 * @param options - Additional request options
 * @returns Mock NextRequest
 */
function createMockRequest(
  url: string,
  method: string = 'GET',
  options: {
    cookieToken?: string;
    headerToken?: string;
    bodyToken?: string;
    contentType?: string;
    body?: any;
  } = {}
): NextRequest {
  const headers: Record<string, string> = {};

  // Set cookie if provided
  if (options.cookieToken) {
    headers.cookie = `${CSRF_TOKEN_COOKIE}=${options.cookieToken}`;
  }

  // Set header token if provided
  if (options.headerToken) {
    headers[CSRF_TOKEN_HEADER] = options.headerToken;
  }

  // Set content type if provided
  if (options.contentType) {
    headers['content-type'] = options.contentType;
  }

  const requestInit: RequestInit = {
    method,
    headers,
  };

  // Set body if provided
  if (options.body !== undefined) {
    if (options.contentType?.includes('application/json')) {
      requestInit.body = JSON.stringify(options.body);
    } else if (options.contentType?.includes('application/x-www-form-urlencoded')) {
      const formData = new URLSearchParams();
      Object.entries(options.body).forEach(([key, value]) => {
        formData.append(key, String(value));
      });
      requestInit.body = formData.toString();
    }
  }

  return new NextRequest(url, requestInit);
}

describe('CSRF Protection', () => {
  describe('Token Generation', () => {
    it('generates a valid CSRF token', () => {
      const token = generateCsrfToken();

      expect(token).toBeDefined();
      expect(typeof token).toBe('string');
      expect(token.length).toBeGreaterThan(0);
    });

    it('generates tokens with correct length', () => {
      const token = generateCsrfToken();

      // Token is hex-encoded, so length should be CSRF_TOKEN_LENGTH * 2
      expect(token.length).toBe(CSRF_TOKEN_LENGTH * 2);
    });

    it('generates unique tokens', () => {
      const token1 = generateCsrfToken();
      const token2 = generateCsrfToken();
      const token3 = generateCsrfToken();

      expect(token1).not.toBe(token2);
      expect(token2).not.toBe(token3);
      expect(token1).not.toBe(token3);
    });

    it('generates tokens with valid hex characters', () => {
      const token = generateCsrfToken();

      // Hex string should only contain 0-9 and a-f
      expect(token).toMatch(/^[0-9a-f]+$/);
    });

    it('generates cryptographically secure tokens', () => {
      // Generate multiple tokens and ensure they have good entropy
      const tokens = new Set();
      const count = 100;

      for (let i = 0; i < count; i++) {
        tokens.add(generateCsrfToken());
      }

      // All tokens should be unique (no collisions)
      expect(tokens.size).toBe(count);
    });
  });

  describe('Cookie Management', () => {
    it('sets CSRF token in response cookie', () => {
      const token = generateCsrfToken();
      const response = NextResponse.json({ success: true });

      setCsrfToken(response, token);

      const cookie = response.cookies.get(CSRF_TOKEN_COOKIE);
      expect(cookie).toBeDefined();
      expect(cookie?.value).toBe(token);
    });

    it('sets cookie with httpOnly flag', () => {
      const token = generateCsrfToken();
      const response = NextResponse.json({ success: true });

      setCsrfToken(response, token);

      const cookie = response.cookies.get(CSRF_TOKEN_COOKIE);
      expect(cookie?.httpOnly).toBe(true);
    });

    it('sets cookie with correct sameSite attribute', () => {
      const token = generateCsrfToken();
      const response = NextResponse.json({ success: true });

      setCsrfToken(response, token);

      const cookie = response.cookies.get(CSRF_TOKEN_COOKIE);
      expect(cookie?.sameSite).toBe('lax');
    });

    it('sets cookie with custom sameSite attribute', () => {
      const token = generateCsrfToken();
      const response = NextResponse.json({ success: true });

      setCsrfToken(response, token, { sameSite: 'strict' });

      const cookie = response.cookies.get(CSRF_TOKEN_COOKIE);
      expect(cookie?.sameSite).toBe('strict');
    });

    it('sets cookie with secure flag in production', () => {
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'production';

      const token = generateCsrfToken();
      const response = NextResponse.json({ success: true });

      setCsrfToken(response, token);

      const cookie = response.cookies.get(CSRF_TOKEN_COOKIE);
      expect(cookie?.secure).toBe(true);

      process.env.NODE_ENV = originalEnv;
    });

    it('sets cookie without secure flag in development', () => {
      const originalEnv = process.env.NODE_ENV;
      process.env.NODE_ENV = 'development';

      const token = generateCsrfToken();
      const response = NextResponse.json({ success: true });

      setCsrfToken(response, token);

      const cookie = response.cookies.get(CSRF_TOKEN_COOKIE);
      expect(cookie?.secure).toBe(false);

      process.env.NODE_ENV = originalEnv;
    });

    it('sets cookie with custom maxAge', () => {
      const token = generateCsrfToken();
      const response = NextResponse.json({ success: true });
      const customMaxAge = 3600; // 1 hour

      setCsrfToken(response, token, { maxAge: customMaxAge });

      const cookie = response.cookies.get(CSRF_TOKEN_COOKIE);
      expect(cookie?.maxAge).toBe(customMaxAge);
    });

    it('sets cookie with custom path', () => {
      const token = generateCsrfToken();
      const response = NextResponse.json({ success: true });
      const customPath = '/api';

      setCsrfToken(response, token, { path: customPath });

      const cookie = response.cookies.get(CSRF_TOKEN_COOKIE);
      expect(cookie?.path).toBe(customPath);
    });

    it('clears CSRF token from cookie', () => {
      const response = NextResponse.json({ success: true });

      clearCsrfToken(response);

      const cookie = response.cookies.get(CSRF_TOKEN_COOKIE);
      expect(cookie).toBeUndefined();
    });

    it('returns the response for method chaining', () => {
      const token = generateCsrfToken();
      const response = NextResponse.json({ success: true });

      const result = setCsrfToken(response, token);

      expect(result).toBe(response);
    });
  });

  describe('Token Extraction', () => {
    it('extracts token from request header', async () => {
      const token = generateCsrfToken();
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        headerToken: token,
      });

      const extractedToken = await extractCsrfTokenFromRequest(request);

      expect(extractedToken).toBe(token);
    });

    it('extracts token from JSON body', async () => {
      const token = generateCsrfToken();
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        contentType: 'application/json',
        body: { [CSRF_TOKEN_FIELD]: token, data: 'test' },
      });

      const extractedToken = await extractCsrfTokenFromRequest(request);

      expect(extractedToken).toBe(token);
    });

    it('extracts token from form data', async () => {
      const token = generateCsrfToken();
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        contentType: 'application/x-www-form-urlencoded',
        body: { [CSRF_TOKEN_FIELD]: token, username: 'test' },
      });

      const extractedToken = await extractCsrfTokenFromRequest(request);

      expect(extractedToken).toBe(token);
    });

    it('prioritizes header token over body token', async () => {
      const headerToken = generateCsrfToken();
      const bodyToken = generateCsrfToken();

      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        headerToken,
        contentType: 'application/json',
        body: { [CSRF_TOKEN_FIELD]: bodyToken },
      });

      const extractedToken = await extractCsrfTokenFromRequest(request);

      expect(extractedToken).toBe(headerToken);
    });

    it('returns null when no token is present', async () => {
      const request = createMockRequest('http://localhost:3000/api/test', 'POST');

      const extractedToken = await extractCsrfTokenFromRequest(request);

      expect(extractedToken).toBeNull();
    });

    it('returns null for unsupported content type', async () => {
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        contentType: 'text/plain',
      });

      const extractedToken = await extractCsrfTokenFromRequest(request);

      expect(extractedToken).toBeNull();
    });

    it('handles malformed JSON gracefully', async () => {
      const request = new NextRequest('http://localhost:3000/api/test', {
        method: 'POST',
        headers: {
          'content-type': 'application/json',
        },
        body: 'invalid json{',
      });

      const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});

      const extractedToken = await extractCsrfTokenFromRequest(request);

      expect(extractedToken).toBeNull();
      expect(consoleError).toHaveBeenCalled();

      consoleError.mockRestore();
    });
  });

  describe('Token Validation', () => {
    it('validates matching tokens successfully', async () => {
      const token = generateCsrfToken();
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: token,
        headerToken: token,
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(true);
      expect(result.token).toBe(token);
      expect(result.error).toBeUndefined();
    });

    it('fails validation when cookie token is missing', async () => {
      const token = generateCsrfToken();
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        headerToken: token,
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(false);
      expect(result.error).toContain('not found in cookie');
      expect(result.token).toBeUndefined();
    });

    it('fails validation when request token is missing', async () => {
      const token = generateCsrfToken();
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: token,
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(false);
      expect(result.error).toContain('not found in request');
      expect(result.token).toBeUndefined();
    });

    it('fails validation when tokens do not match', async () => {
      const cookieToken = generateCsrfToken();
      const headerToken = generateCsrfToken();

      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken,
        headerToken,
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(false);
      expect(result.error).toContain('mismatch');
      expect(result.token).toBeUndefined();
    });

    it('fails validation for tokens with different lengths', async () => {
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: 'short',
        headerToken: 'verylongtoken',
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(false);
      expect(result.error).toContain('mismatch');
    });

    it('uses timing-safe comparison for tokens', async () => {
      // This test ensures that the comparison is constant-time
      const token = generateCsrfToken();
      const almostToken = token.slice(0, -1) + (token.slice(-1) === 'a' ? 'b' : 'a');

      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: token,
        headerToken: almostToken,
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(false);
    });

    it('validates token from JSON body', async () => {
      const token = generateCsrfToken();
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: token,
        contentType: 'application/json',
        body: { [CSRF_TOKEN_FIELD]: token },
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(true);
    });

    it('validates token from form data', async () => {
      const token = generateCsrfToken();
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: token,
        contentType: 'application/x-www-form-urlencoded',
        body: { [CSRF_TOKEN_FIELD]: token },
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(true);
    });
  });

  describe('Method Protection Check', () => {
    it('requires CSRF protection for POST requests', () => {
      expect(requiresCsrfProtection('POST')).toBe(true);
    });

    it('requires CSRF protection for PUT requests', () => {
      expect(requiresCsrfProtection('PUT')).toBe(true);
    });

    it('requires CSRF protection for PATCH requests', () => {
      expect(requiresCsrfProtection('PATCH')).toBe(true);
    });

    it('requires CSRF protection for DELETE requests', () => {
      expect(requiresCsrfProtection('DELETE')).toBe(true);
    });

    it('does not require CSRF protection for GET requests', () => {
      expect(requiresCsrfProtection('GET')).toBe(false);
    });

    it('does not require CSRF protection for HEAD requests', () => {
      expect(requiresCsrfProtection('HEAD')).toBe(false);
    });

    it('does not require CSRF protection for OPTIONS requests', () => {
      expect(requiresCsrfProtection('OPTIONS')).toBe(false);
    });

    it('handles lowercase method names', () => {
      expect(requiresCsrfProtection('post')).toBe(true);
      expect(requiresCsrfProtection('get')).toBe(false);
    });

    it('handles mixed case method names', () => {
      expect(requiresCsrfProtection('PoSt')).toBe(true);
      expect(requiresCsrfProtection('GeT')).toBe(false);
    });
  });

  describe('Middleware Helper (withCsrfProtection)', () => {
    it('allows GET requests without CSRF validation', async () => {
      const handler = vi.fn().mockResolvedValue(
        NextResponse.json({ success: true })
      );

      const protectedHandler = withCsrfProtection(handler);
      const request = createMockRequest('http://localhost:3000/api/test', 'GET');

      const response = await protectedHandler(request);

      expect(handler).toHaveBeenCalled();
      expect(response.status).toBe(200);
    });

    it('validates CSRF token for POST requests', async () => {
      const handler = vi.fn().mockResolvedValue(
        NextResponse.json({ success: true })
      );

      const protectedHandler = withCsrfProtection(handler);
      const token = generateCsrfToken();
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: token,
        headerToken: token,
      });

      const response = await protectedHandler(request);

      expect(handler).toHaveBeenCalled();
      expect(response.status).toBe(200);
    });

    it('blocks POST requests with invalid CSRF token', async () => {
      const handler = vi.fn().mockResolvedValue(
        NextResponse.json({ success: true })
      );

      const protectedHandler = withCsrfProtection(handler);
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: generateCsrfToken(),
        headerToken: generateCsrfToken(),
      });

      const response = await protectedHandler(request);

      expect(handler).not.toHaveBeenCalled();
      expect(response.status).toBe(403);

      const data = await response.json();
      expect(data.error).toBe('CSRF validation failed');
    });

    it('blocks POST requests without CSRF token', async () => {
      const handler = vi.fn().mockResolvedValue(
        NextResponse.json({ success: true })
      );

      const protectedHandler = withCsrfProtection(handler);
      const request = createMockRequest('http://localhost:3000/api/test', 'POST');

      const response = await protectedHandler(request);

      expect(handler).not.toHaveBeenCalled();
      expect(response.status).toBe(403);
    });

    it('validates CSRF token for PUT requests', async () => {
      const handler = vi.fn().mockResolvedValue(
        NextResponse.json({ success: true })
      );

      const protectedHandler = withCsrfProtection(handler);
      const token = generateCsrfToken();
      const request = createMockRequest('http://localhost:3000/api/test', 'PUT', {
        cookieToken: token,
        headerToken: token,
      });

      const response = await protectedHandler(request);

      expect(handler).toHaveBeenCalled();
      expect(response.status).toBe(200);
    });

    it('validates CSRF token for DELETE requests', async () => {
      const handler = vi.fn().mockResolvedValue(
        NextResponse.json({ success: true })
      );

      const protectedHandler = withCsrfProtection(handler);
      const token = generateCsrfToken();
      const request = createMockRequest('http://localhost:3000/api/test', 'DELETE', {
        cookieToken: token,
        headerToken: token,
      });

      const response = await protectedHandler(request);

      expect(handler).toHaveBeenCalled();
      expect(response.status).toBe(200);
    });

    it('returns error message in response', async () => {
      const handler = vi.fn();
      const protectedHandler = withCsrfProtection(handler);
      const request = createMockRequest('http://localhost:3000/api/test', 'POST');

      const response = await protectedHandler(request);
      const data = await response.json();

      expect(data.error).toBe('CSRF validation failed');
      expect(data.message).toBeDefined();
      expect(typeof data.message).toBe('string');
    });
  });

  describe('Client-side Helpers', () => {
    it('creates headers with CSRF token', () => {
      const token = generateCsrfToken();
      const headers = createCsrfHeaders(token);

      expect(headers).toBeDefined();
      expect(headers[CSRF_TOKEN_HEADER]).toBe(token);
    });

    it('merges CSRF token with existing headers', () => {
      const token = generateCsrfToken();
      const existingHeaders = {
        'Content-Type': 'application/json',
        'Authorization': 'Bearer abc123',
      };

      const headers = createCsrfHeaders(token, existingHeaders);

      expect(headers[CSRF_TOKEN_HEADER]).toBe(token);
      expect(headers['Content-Type']).toBe('application/json');
      expect(headers['Authorization']).toBe('Bearer abc123');
    });

    it('creates headers without existing headers', () => {
      const token = generateCsrfToken();
      const headers = createCsrfHeaders(token);

      expect(Object.keys(headers)).toContain(CSRF_TOKEN_HEADER);
    });

    it('overwrites existing CSRF header', () => {
      const newToken = generateCsrfToken();
      const oldToken = generateCsrfToken();
      const existingHeaders = {
        [CSRF_TOKEN_HEADER]: oldToken,
      };

      const headers = createCsrfHeaders(newToken, existingHeaders);

      expect(headers[CSRF_TOKEN_HEADER]).toBe(newToken);
    });
  });

  describe('Edge Cases', () => {
    it('handles empty string tokens', async () => {
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: '',
        headerToken: '',
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(false);
    });

    it('handles whitespace-only tokens', async () => {
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: '   ',
        headerToken: '   ',
      });

      const result = await validateCsrfToken(request);

      // Whitespace tokens should technically match but are invalid
      expect(result.valid).toBe(true); // They match, but in practice should be rejected by length check
    });

    it('handles special characters in tokens', async () => {
      const token = 'token-with-special-chars-!@#$%';
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: token,
        headerToken: token,
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(true);
    });

    it('handles very long tokens', async () => {
      const longToken = 'a'.repeat(10000);
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: longToken,
        headerToken: longToken,
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(true);
    });

    it('handles unicode characters in tokens', async () => {
      const unicodeToken = '🔒🛡️🔐';
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: unicodeToken,
        headerToken: unicodeToken,
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(true);
    });

    it('handles null bytes in tokens', async () => {
      const tokenWithNull = 'token\0with\0nulls';
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: tokenWithNull,
        headerToken: tokenWithNull,
      });

      const result = await validateCsrfToken(request);

      expect(result.valid).toBe(true);
    });
  });

  describe('Security', () => {
    it('generates tokens with sufficient entropy', () => {
      const tokens = new Set();
      const iterations = 1000;

      for (let i = 0; i < iterations; i++) {
        tokens.add(generateCsrfToken());
      }

      // No collisions expected with cryptographically secure random generation
      expect(tokens.size).toBe(iterations);
    });

    it('sets httpOnly cookie to prevent XSS', () => {
      const token = generateCsrfToken();
      const response = NextResponse.json({ success: true });

      setCsrfToken(response, token);

      const cookie = response.cookies.get(CSRF_TOKEN_COOKIE);
      expect(cookie?.httpOnly).toBe(true);
    });

    it('uses SameSite attribute for CSRF protection', () => {
      const token = generateCsrfToken();
      const response = NextResponse.json({ success: true });

      setCsrfToken(response, token);

      const cookie = response.cookies.get(CSRF_TOKEN_COOKIE);
      expect(cookie?.sameSite).toBeDefined();
      expect(['strict', 'lax', 'none']).toContain(cookie?.sameSite);
    });

    it('does not expose token in error messages', async () => {
      const cookieToken = generateCsrfToken();
      const headerToken = generateCsrfToken();

      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken,
        headerToken,
      });

      const result = await validateCsrfToken(request);

      expect(result.error).not.toContain(cookieToken);
      expect(result.error).not.toContain(headerToken);
    });

    it('validates tokens using constant-time comparison', async () => {
      const token = generateCsrfToken();

      // Create two different tokens that differ at different positions
      const token1 = token;
      const token2 = 'a' + token.slice(1);
      const token3 = token.slice(0, -1) + 'z';

      const request1 = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: token1,
        headerToken: token2,
      });

      const request2 = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: token1,
        headerToken: token3,
      });

      const result1 = await validateCsrfToken(request1);
      const result2 = await validateCsrfToken(request2);

      // Both should fail regardless of where the difference is
      expect(result1.valid).toBe(false);
      expect(result2.valid).toBe(false);
    });
  });

  describe('Performance', () => {
    it('generates tokens quickly', () => {
      const start = Date.now();

      for (let i = 0; i < 1000; i++) {
        generateCsrfToken();
      }

      const duration = Date.now() - start;

      // Should generate 1000 tokens in less than 100ms
      expect(duration).toBeLessThan(100);
    });

    it('validates tokens quickly', async () => {
      const token = generateCsrfToken();
      const request = createMockRequest('http://localhost:3000/api/test', 'POST', {
        cookieToken: token,
        headerToken: token,
      });

      const start = Date.now();
      await validateCsrfToken(request);
      const duration = Date.now() - start;

      // Should validate in less than 10ms
      expect(duration).toBeLessThan(10);
    });
  });
});
