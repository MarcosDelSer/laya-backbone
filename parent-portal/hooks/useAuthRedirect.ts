'use client';

/**
 * LAYA Parent Portal - useAuthRedirect Hook
 *
 * A hook that handles redirects based on authentication state.
 * Useful for login/register pages that should redirect away if already authenticated.
 */

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/contexts/AuthContext';

/**
 * Options for useAuthRedirect hook
 */
export interface UseAuthRedirectOptions {
  /**
   * Path to redirect to if authenticated.
   * @default '/'
   */
  redirectTo?: string;

  /**
   * Whether to check for a 'destination' query parameter.
   * If true and param exists, redirects there instead of redirectTo.
   * @default true
   */
  checkDestination?: boolean;

  /**
   * Whether the redirect should only happen if authenticated.
   * @default true
   */
  redirectIfAuthenticated?: boolean;

  /**
   * Whether the redirect should only happen if NOT authenticated.
   * @default false
   */
  redirectIfNotAuthenticated?: boolean;
}

/**
 * Hook that redirects based on authentication state.
 * Typically used on login/register pages to redirect authenticated users.
 *
 * @param options - Configuration options
 *
 * @example
 * ```tsx
 * // Redirect authenticated users away from login page
 * function LoginPage() {
 *   useAuthRedirect(); // Uses defaults
 *
 *   return <LoginForm />;
 * }
 * ```
 *
 * @example
 * ```tsx
 * // Redirect to custom path
 * function RegisterPage() {
 *   useAuthRedirect({
 *     redirectTo: '/dashboard',
 *     checkDestination: false,
 *   });
 *
 *   return <RegistrationForm />;
 * }
 * ```
 *
 * @example
 * ```tsx
 * // Redirect unauthenticated users (for protected content)
 * function ProtectedPage() {
 *   useAuthRedirect({
 *     redirectIfAuthenticated: false,
 *     redirectIfNotAuthenticated: true,
 *     redirectTo: '/auth/login',
 *   });
 *
 *   return <ProtectedContent />;
 * }
 * ```
 */
export function useAuthRedirect(options: UseAuthRedirectOptions = {}) {
  const {
    redirectTo = '/',
    checkDestination = true,
    redirectIfAuthenticated = true,
    redirectIfNotAuthenticated = false,
  } = options;

  const router = useRouter();
  const { isAuthenticated, isLoading } = useAuth();

  useEffect(() => {
    // Don't redirect while still loading
    if (isLoading) {
      return;
    }

    // Determine if we should redirect
    const shouldRedirect =
      (redirectIfAuthenticated && isAuthenticated) ||
      (redirectIfNotAuthenticated && !isAuthenticated);

    if (!shouldRedirect) {
      return;
    }

    // Determine redirect destination
    let destination = redirectTo;

    // Check for destination query parameter
    if (checkDestination && typeof window !== 'undefined') {
      const params = new URLSearchParams(window.location.search);
      const queryDestination = params.get('destination');

      if (queryDestination) {
        // Validate the destination is a relative path (security)
        if (queryDestination.startsWith('/') && !queryDestination.startsWith('//')) {
          destination = queryDestination;
        }
      }
    }

    router.push(destination);
  }, [
    isAuthenticated,
    isLoading,
    router,
    redirectTo,
    checkDestination,
    redirectIfAuthenticated,
    redirectIfNotAuthenticated,
  ]);
}
