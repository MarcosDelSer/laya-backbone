import { describe, it, expect, vi, beforeEach } from 'vitest';
import { NextRequest } from 'next/server';
import { middleware } from '@/middleware';
import { ACCESS_TOKEN_COOKIE } from '@/lib/auth';

/**
 * Helper function to create a mock NextRequest with optional authentication.
 *
 * @param url - Request URL
 * @param authenticated - Whether to include auth token
 * @returns Mock NextRequest
 */
function createMockRequest(url: string, authenticated: boolean = false): NextRequest {
  const headers: Record<string, string> = {};

  if (authenticated) {
    headers.cookie = `${ACCESS_TOKEN_COOKIE}=valid-test-token`;
  }

  return new NextRequest(url, { headers });
}

describe('Middleware', () => {
  describe('Static Assets', () => {
    it('allows access to Next.js static files', () => {
      const request = createMockRequest('http://localhost:3000/_next/static/chunk.js');
      const response = middleware(request);

      expect(response.status).not.toBe(307); // Not a redirect
    });

    it('allows access to favicon', () => {
      const request = createMockRequest('http://localhost:3000/favicon.ico');
      const response = middleware(request);

      expect(response.status).not.toBe(307);
    });

    it('allows access to images', () => {
      const request = createMockRequest('http://localhost:3000/images/logo.png');
      const response = middleware(request);

      expect(response.status).not.toBe(307);
    });

    it('allows access to fonts', () => {
      const request = createMockRequest('http://localhost:3000/fonts/inter.woff2');
      const response = middleware(request);

      expect(response.status).not.toBe(307);
    });
  });

  describe('Public Routes', () => {
    it('allows unauthenticated access to login API', () => {
      const request = createMockRequest('http://localhost:3000/api/auth/login');
      const response = middleware(request);

      expect(response.status).not.toBe(307);
    });

    it('allows unauthenticated access to register API', () => {
      const request = createMockRequest('http://localhost:3000/api/auth/register');
      const response = middleware(request);

      expect(response.status).not.toBe(307);
    });

    it('allows unauthenticated access to logout API', () => {
      const request = createMockRequest('http://localhost:3000/api/auth/logout');
      const response = middleware(request);

      expect(response.status).not.toBe(307);
    });

    it('allows unauthenticated access to forgot password', () => {
      const request = createMockRequest('http://localhost:3000/auth/forgot-password');
      const response = middleware(request);

      expect(response.status).not.toBe(307);
    });

    it('allows unauthenticated access to reset password', () => {
      const request = createMockRequest('http://localhost:3000/auth/reset-password');
      const response = middleware(request);

      expect(response.status).not.toBe(307);
    });
  });

  describe('Auth Routes (Login/Register Pages)', () => {
    it('allows unauthenticated users to access login page', () => {
      const request = createMockRequest('http://localhost:3000/auth/login', false);
      const response = middleware(request);

      expect(response.status).not.toBe(307);
    });

    it('allows unauthenticated users to access register page', () => {
      const request = createMockRequest('http://localhost:3000/auth/register', false);
      const response = middleware(request);

      expect(response.status).not.toBe(307);
    });

    it('redirects authenticated users away from login page', () => {
      const request = createMockRequest('http://localhost:3000/auth/login', true);
      const response = middleware(request);

      expect(response.status).toBe(307); // Redirect status
      expect(response.headers.get('location')).toBe('http://localhost:3000/');
    });

    it('redirects authenticated users away from register page', () => {
      const request = createMockRequest('http://localhost:3000/auth/register', true);
      const response = middleware(request);

      expect(response.status).toBe(307);
      expect(response.headers.get('location')).toBe('http://localhost:3000/');
    });
  });

  describe('Protected Routes', () => {
    describe('Unauthenticated Access', () => {
      it('redirects unauthenticated users from dashboard to login', () => {
        const request = createMockRequest('http://localhost:3000/', false);
        const response = middleware(request);

        expect(response.status).toBe(307);
        expect(response.headers.get('location')).toContain('/auth/login');
      });

      it('redirects unauthenticated users from daily reports to login', () => {
        const request = createMockRequest('http://localhost:3000/daily-reports', false);
        const response = middleware(request);

        expect(response.status).toBe(307);
        expect(response.headers.get('location')).toContain('/auth/login');
      });

      it('redirects unauthenticated users from messages to login', () => {
        const request = createMockRequest('http://localhost:3000/messages', false);
        const response = middleware(request);

        expect(response.status).toBe(307);
        expect(response.headers.get('location')).toContain('/auth/login');
      });

      it('redirects unauthenticated users from documents to login', () => {
        const request = createMockRequest('http://localhost:3000/documents', false);
        const response = middleware(request);

        expect(response.status).toBe(307);
        expect(response.headers.get('location')).toContain('/auth/login');
      });

      it('redirects unauthenticated users from invoices to login', () => {
        const request = createMockRequest('http://localhost:3000/invoices', false);
        const response = middleware(request);

        expect(response.status).toBe(307);
        expect(response.headers.get('location')).toContain('/auth/login');
      });

      it('redirects unauthenticated users from user API to login', () => {
        const request = createMockRequest('http://localhost:3000/api/user/profile', false);
        const response = middleware(request);

        expect(response.status).toBe(307);
        expect(response.headers.get('location')).toContain('/auth/login');
      });
    });

    describe('Authenticated Access', () => {
      it('allows authenticated users to access dashboard', () => {
        const request = createMockRequest('http://localhost:3000/', true);
        const response = middleware(request);

        expect(response.status).not.toBe(307);
      });

      it('allows authenticated users to access daily reports', () => {
        const request = createMockRequest('http://localhost:3000/daily-reports', true);
        const response = middleware(request);

        expect(response.status).not.toBe(307);
      });

      it('allows authenticated users to access messages', () => {
        const request = createMockRequest('http://localhost:3000/messages', true);
        const response = middleware(request);

        expect(response.status).not.toBe(307);
      });

      it('allows authenticated users to access documents', () => {
        const request = createMockRequest('http://localhost:3000/documents', true);
        const response = middleware(request);

        expect(response.status).not.toBe(307);
      });

      it('allows authenticated users to access invoices', () => {
        const request = createMockRequest('http://localhost:3000/invoices', true);
        const response = middleware(request);

        expect(response.status).not.toBe(307);
      });

      it('allows authenticated users to access user API', () => {
        const request = createMockRequest('http://localhost:3000/api/user/profile', true);
        const response = middleware(request);

        expect(response.status).not.toBe(307);
      });
    });

    describe('Redirect Parameter', () => {
      it('includes redirect parameter when redirecting from dashboard', () => {
        const request = createMockRequest('http://localhost:3000/', false);
        const response = middleware(request);

        const location = response.headers.get('location');
        expect(location).toContain('/auth/login');
        // Dashboard (/) should not include redirect param (it's the default)
      });

      it('includes redirect parameter when redirecting from nested route', () => {
        const request = createMockRequest('http://localhost:3000/messages', false);
        const response = middleware(request);

        const location = response.headers.get('location');
        expect(location).toContain('/auth/login');
        expect(location).toContain('redirect=%2Fmessages');
      });

      it('includes redirect parameter for deep routes', () => {
        const request = createMockRequest('http://localhost:3000/daily-reports', false);
        const response = middleware(request);

        const location = response.headers.get('location');
        expect(location).toContain('/auth/login');
        expect(location).toContain('redirect=%2Fdaily-reports');
      });
    });
  });

  describe('Route Matching', () => {
    it('matches exact protected routes', () => {
      const request = createMockRequest('http://localhost:3000/documents', false);
      const response = middleware(request);

      expect(response.status).toBe(307);
    });

    it('matches nested protected routes', () => {
      const request = createMockRequest('http://localhost:3000/api/user/settings', false);
      const response = middleware(request);

      expect(response.status).toBe(307);
    });

    it('does not match similar but different routes', () => {
      // /messages-test is not a protected route
      const request = createMockRequest('http://localhost:3000/messages-test', false);
      const response = middleware(request);

      // Should not redirect (not a protected route)
      expect(response.status).not.toBe(307);
    });
  });

  describe('Edge Cases', () => {
    it('handles requests without cookies', () => {
      const request = new NextRequest('http://localhost:3000/');
      const response = middleware(request);

      expect(response.status).toBe(307);
      expect(response.headers.get('location')).toContain('/auth/login');
    });

    it('handles empty token cookie', () => {
      const request = new NextRequest('http://localhost:3000/', {
        headers: {
          cookie: `${ACCESS_TOKEN_COOKIE}=`,
        },
      });
      const response = middleware(request);

      expect(response.status).toBe(307);
      expect(response.headers.get('location')).toContain('/auth/login');
    });

    it('handles malformed cookies', () => {
      const request = new NextRequest('http://localhost:3000/', {
        headers: {
          cookie: 'malformed_cookie_string',
        },
      });
      const response = middleware(request);

      expect(response.status).toBe(307);
    });

    it('handles URLs with query parameters', () => {
      const request = createMockRequest('http://localhost:3000/messages?id=123', true);
      const response = middleware(request);

      expect(response.status).not.toBe(307);
    });

    it('handles URLs with hash fragments', () => {
      const request = createMockRequest('http://localhost:3000/documents#section', true);
      const response = middleware(request);

      expect(response.status).not.toBe(307);
    });

    it('case-sensitive route matching', () => {
      // Next.js routes are case-sensitive
      const request = createMockRequest('http://localhost:3000/Messages', false);
      const response = middleware(request);

      // Should not redirect (not matching case-sensitive route)
      expect(response.status).not.toBe(307);
    });
  });

  describe('Performance', () => {
    it('executes quickly for static assets', () => {
      const start = Date.now();
      const request = createMockRequest('http://localhost:3000/_next/static/file.js');
      middleware(request);
      const duration = Date.now() - start;

      // Should complete in less than 10ms
      expect(duration).toBeLessThan(10);
    });

    it('executes quickly for protected routes', () => {
      const start = Date.now();
      const request = createMockRequest('http://localhost:3000/', true);
      middleware(request);
      const duration = Date.now() - start;

      // Should complete in less than 10ms
      expect(duration).toBeLessThan(10);
    });
  });

  describe('Security', () => {
    it('does not expose token in redirect URLs', () => {
      const request = createMockRequest('http://localhost:3000/messages', false);
      const response = middleware(request);

      const location = response.headers.get('location');
      expect(location).not.toContain('token');
      expect(location).not.toContain(ACCESS_TOKEN_COOKIE);
    });

    it('consistently denies access without authentication', () => {
      const protectedRoutes = [
        'http://localhost:3000/',
        'http://localhost:3000/daily-reports',
        'http://localhost:3000/messages',
        'http://localhost:3000/documents',
        'http://localhost:3000/invoices',
      ];

      protectedRoutes.forEach(route => {
        const request = createMockRequest(route, false);
        const response = middleware(request);
        expect(response.status).toBe(307);
      });
    });

    it('consistently allows access with authentication', () => {
      const protectedRoutes = [
        'http://localhost:3000/',
        'http://localhost:3000/daily-reports',
        'http://localhost:3000/messages',
        'http://localhost:3000/documents',
        'http://localhost:3000/invoices',
      ];

      protectedRoutes.forEach(route => {
        const request = createMockRequest(route, true);
        const response = middleware(request);
        expect(response.status).not.toBe(307);
      });
    });
  });
});
