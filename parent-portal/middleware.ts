/**
 * Next.js Middleware for i18n locale detection, routing, and CSRF protection.
 *
 * This middleware handles:
 * - CSRF token generation and validation for security
 * - Automatic locale detection from Accept-Language header
 * - Locale prefix routing (e.g., /en/dashboard, /fr/dashboard)
 * - Locale persistence via cookies
 * - Redirect to default locale when none is specified
 *
 * Security: All cookies set by this middleware use SameSite=Lax for CSRF protection:
 * - CSRF token cookie: SameSite=Lax (configured in lib/csrf.ts)
 * - Locale preference cookie: SameSite=Lax (next-intl default)
 */

import createMiddleware from 'next-intl/middleware';
import { NextRequest, NextResponse } from 'next/server';
import { locales, defaultLocale } from './i18n';
import {
  generateCsrfToken,
  setCsrfToken,
  validateCsrfToken,
  requiresCsrfProtection,
  CSRF_TOKEN_COOKIE,
} from './lib/csrf';

/**
 * i18n middleware configuration.
 *
 * Uses next-intl's createMiddleware to handle locale routing:
 * - Detects user's preferred locale from Accept-Language header
 * - Prefixes all routes with the locale (e.g., /en, /fr)
 * - Stores locale preference in a cookie for subsequent visits (SameSite=Lax by default)
 * - Redirects to the default locale if no locale is specified
 */
const i18nMiddleware = createMiddleware({
  // Supported locales
  locales,

  // Default locale when none is detected
  defaultLocale,

  // Locale detection strategy
  // 'always': Always show locale prefix in URL (e.g., /en/dashboard)
  // This ensures consistent URLs and better SEO
  localePrefix: 'always',

  // Locale detection configuration
  localeDetection: true,
});

/**
 * Combined middleware that handles both CSRF protection and i18n routing.
 *
 * Processing order:
 * 1. CSRF token validation (for state-changing requests)
 * 2. i18n locale detection and routing
 * 3. CSRF token generation (for GET requests without a token)
 *
 * @param request - Next.js request object
 * @returns Next.js response object
 */
export default async function middleware(request: NextRequest) {
  // ============================================================================
  // CSRF Protection - Validation
  // ============================================================================

  // Skip CSRF protection for public API routes (login, register, etc.)
  const pathname = request.nextUrl.pathname;
  const isPublicApiRoute = pathname.startsWith('/api/auth/');

  if (!isPublicApiRoute && requiresCsrfProtection(request.method)) {
    // Validate CSRF token for state-changing requests
    const validationResult = await validateCsrfToken(request);

    if (!validationResult.valid) {
      // Return 403 Forbidden for failed CSRF validation
      return NextResponse.json(
        {
          error: 'CSRF validation failed',
          message: validationResult.error,
        },
        { status: 403 }
      );
    }
  }

  // ============================================================================
  // i18n Routing
  // ============================================================================

  // Handle i18n routing (locale detection and redirects)
  const response = i18nMiddleware(request);

  // ============================================================================
  // CSRF Protection - Token Generation
  // ============================================================================

  // Generate and set CSRF token for GET requests if none exists
  if (request.method === 'GET') {
    const existingToken = request.cookies.get(CSRF_TOKEN_COOKIE)?.value;

    if (!existingToken) {
      // Generate new CSRF token
      const newToken = generateCsrfToken();

      // Set token in response cookie with secure defaults:
      // - httpOnly: true (prevents XSS access)
      // - secure: true in production (HTTPS only)
      // - sameSite: 'lax' (prevents CSRF attacks, allows top-level navigation)
      // - maxAge: 2 hours (token expiry)
      setCsrfToken(response, newToken);
    }
  }

  return response;
}

/**
 * Middleware matcher configuration.
 *
 * Configures which routes the middleware should apply to.
 * Excludes API routes, static files, and Next.js internals.
 */
export const config = {
  // Match all pathnames except:
  // - API routes (/api/...)
  // - Static files (/_next/static/..., /_next/image/...)
  // - Favicon and other static assets
  // - Files with extensions (e.g., .png, .jpg, .css, .js)
  matcher: [
    // Match all pathnames except those starting with:
    // - api (API routes)
    // - _next (Next.js internals)
    // - _vercel (Vercel internals)
    // - Files with extensions
    '/((?!api|_next|_vercel|.*\\..*).*)',
    // Also match the root
    '/',
  ],
};
