/**
 * Next.js Middleware for i18n locale detection and routing.
 *
 * This middleware handles:
 * - Automatic locale detection from Accept-Language header
 * - Locale prefix routing (e.g., /en/dashboard, /fr/dashboard)
 * - Locale persistence via cookies
 * - Redirect to default locale when none is specified
 */

import createMiddleware from 'next-intl/middleware';
import { locales, defaultLocale } from './i18n';

/**
 * i18n middleware configuration.
 *
 * Uses next-intl's createMiddleware to handle locale routing:
 * - Detects user's preferred locale from Accept-Language header
 * - Prefixes all routes with the locale (e.g., /en, /fr)
 * - Stores locale preference in a cookie for subsequent visits
 * - Redirects to the default locale if no locale is specified
 */
export default createMiddleware({
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
