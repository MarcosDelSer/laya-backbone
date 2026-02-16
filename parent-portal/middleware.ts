/**
 * Next.js middleware for protected routes in LAYA Parent Portal.
 *
 * This middleware:
 * - Protects authenticated routes (dashboard, messages, documents, etc.)
 * - Redirects unauthenticated users to login
 * - Redirects authenticated users away from auth pages
 * - Preserves intended destination for post-login redirect
 *
 * @see https://nextjs.org/docs/app/building-your-application/routing/middleware
 */

import { NextRequest, NextResponse } from 'next/server';
import { isRequestAuthenticated } from '@/lib/auth';

// ============================================================================
// Configuration
// ============================================================================

/**
 * Routes that require authentication.
 * Users without valid tokens will be redirected to login.
 */
const PROTECTED_ROUTES = [
  '/',
  '/daily-reports',
  '/messages',
  '/documents',
  '/invoices',
  '/api/user',
];

/**
 * Authentication routes (login, register, etc.).
 * Authenticated users accessing these will be redirected to dashboard.
 */
const AUTH_ROUTES = ['/auth/login', '/auth/register'];

/**
 * Public routes that don't require authentication.
 * These routes are accessible to everyone.
 */
const PUBLIC_ROUTES = [
  '/auth/forgot-password',
  '/auth/reset-password',
  '/api/auth/login',
  '/api/auth/register',
  '/api/auth/logout',
];

/**
 * Static asset paths that should bypass middleware.
 */
const STATIC_PATHS = [
  '/_next',
  '/favicon.ico',
  '/static',
  '/images',
  '/fonts',
];

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Check if a path matches any of the given route patterns.
 *
 * @param pathname - The request pathname
 * @param routes - Array of route patterns to match against
 * @returns True if pathname matches any route pattern
 */
function matchesRoute(pathname: string, routes: string[]): boolean {
  return routes.some(route => {
    // Exact match
    if (pathname === route) return true;
    // Prefix match (e.g., /api/user matches /api/user/profile)
    if (pathname.startsWith(route + '/')) return true;
    return false;
  });
}

/**
 * Check if a path is a static asset.
 *
 * @param pathname - The request pathname
 * @returns True if pathname is a static asset
 */
function isStaticAsset(pathname: string): boolean {
  return STATIC_PATHS.some(path => pathname.startsWith(path));
}

/**
 * Create a redirect response with the intended destination stored.
 *
 * @param request - The incoming request
 * @param destination - The redirect destination URL
 * @param storeRedirect - Whether to store the current path for post-login redirect
 * @returns NextResponse redirect
 */
function createRedirect(
  request: NextRequest,
  destination: string,
  storeRedirect: boolean = false
): NextResponse {
  const url = new URL(destination, request.url);

  // Store the intended destination in the redirect URL
  if (storeRedirect && request.nextUrl.pathname !== '/') {
    url.searchParams.set('redirect', request.nextUrl.pathname);
  }

  return NextResponse.redirect(url);
}

// ============================================================================
// Middleware Function
// ============================================================================

/**
 * Next.js middleware function.
 *
 * Runs on every request to check authentication and handle redirects.
 *
 * @param request - The incoming request
 * @returns NextResponse (either continue, redirect, or rewrite)
 */
export function middleware(request: NextRequest) {
  const { pathname } = request.nextUrl;

  // Skip middleware for static assets and Next.js internals
  if (isStaticAsset(pathname)) {
    return NextResponse.next();
  }

  // Check authentication status
  const isAuthenticated = isRequestAuthenticated(request);

  // Allow public routes for everyone
  if (matchesRoute(pathname, PUBLIC_ROUTES)) {
    return NextResponse.next();
  }

  // Handle authentication routes (login, register)
  if (matchesRoute(pathname, AUTH_ROUTES)) {
    if (isAuthenticated) {
      // Redirect authenticated users away from auth pages to dashboard
      return createRedirect(request, '/');
    }
    // Allow unauthenticated users to access auth pages
    return NextResponse.next();
  }

  // Handle protected routes
  if (matchesRoute(pathname, PROTECTED_ROUTES)) {
    if (!isAuthenticated) {
      // Redirect unauthenticated users to login with redirect parameter
      return createRedirect(request, '/auth/login', true);
    }
    // Allow authenticated users to access protected routes
    return NextResponse.next();
  }

  // Default: allow all other routes
  return NextResponse.next();
}

// ============================================================================
// Middleware Configuration
// ============================================================================

/**
 * Middleware matcher configuration.
 *
 * Specifies which routes the middleware should run on.
 * This helps optimize performance by skipping middleware for static assets.
 *
 * @see https://nextjs.org/docs/app/building-your-application/routing/middleware#matcher
 */
export const config = {
  matcher: [
    /*
     * Match all request paths except for the ones starting with:
     * - _next/static (static files)
     * - _next/image (image optimization files)
     * - favicon.ico (favicon file)
     * - public folder files
     */
    '/((?!_next/static|_next/image|favicon.ico|.*\\.(?:svg|png|jpg|jpeg|gif|webp)$).*)',
  ],
};
