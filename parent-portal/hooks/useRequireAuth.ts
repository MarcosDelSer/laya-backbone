'use client';

/**
 * LAYA Parent Portal - useRequireAuth Hook
 *
 * A hook that enforces authentication requirement for components.
 * Automatically redirects to login if user is not authenticated.
 *
 * Use this in protected pages/components that should only be accessible
 * to authenticated users.
 */

import { useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/contexts/AuthContext';

/**
 * Options for useRequireAuth hook
 */
export interface UseRequireAuthOptions {
  /**
   * Path to redirect to if not authenticated.
   * @default '/auth/login'
   */
  redirectTo?: string;

  /**
   * Whether to preserve the current path as a redirect destination.
   * @default true
   */
  preserveDestination?: boolean;
}

/**
 * Hook that requires authentication and redirects if not authenticated.
 *
 * @param options - Configuration options
 * @returns Authentication context value
 *
 * @example
 * ```tsx
 * function ProtectedPage() {
 *   const { user, isLoading } = useRequireAuth();
 *
 *   if (isLoading) {
 *     return <LoadingSpinner />;
 *   }
 *
 *   return <div>Welcome, {user?.firstName}!</div>;
 * }
 * ```
 *
 * @example
 * ```tsx
 * // Custom redirect path
 * function AdminPanel() {
 *   const { user } = useRequireAuth({
 *     redirectTo: '/unauthorized',
 *     preserveDestination: false,
 *   });
 *
 *   return <div>Admin Content</div>;
 * }
 * ```
 */
export function useRequireAuth(options: UseRequireAuthOptions = {}) {
  const {
    redirectTo = '/auth/login',
    preserveDestination = true,
  } = options;

  const router = useRouter();
  const authContext = useAuth();
  const { isAuthenticated, isLoading } = authContext;

  useEffect(() => {
    // Don't redirect while still loading
    if (isLoading) {
      return;
    }

    // Redirect if not authenticated
    if (!isAuthenticated) {
      let redirectUrl = redirectTo;

      // Preserve current path for redirect after login
      if (preserveDestination && typeof window !== 'undefined') {
        const currentPath = window.location.pathname + window.location.search;
        // Don't preserve if already on login page
        if (currentPath !== redirectTo) {
          redirectUrl = `${redirectTo}?destination=${encodeURIComponent(currentPath)}`;
        }
      }

      router.push(redirectUrl);
    }
  }, [isAuthenticated, isLoading, router, redirectTo, preserveDestination]);

  return authContext;
}
