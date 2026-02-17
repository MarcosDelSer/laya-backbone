'use client';

/**
 * LAYA Parent Portal - useAuthStatus Hook
 *
 * A minimal hook for checking authentication status.
 * Returns only authentication state without user data.
 *
 * Use this when you only need to know if a user is authenticated
 * and don't need the actual user data.
 */

import { useAuth } from '@/contexts/AuthContext';

/**
 * Return type for useAuthStatus hook
 */
export interface UseAuthStatusReturn {
  /**
   * Whether the user is authenticated
   */
  isAuthenticated: boolean;

  /**
   * Whether authentication state is being loaded
   */
  isLoading: boolean;
}

/**
 * Hook to check authentication status.
 *
 * @returns Authentication status and loading state
 *
 * @example
 * ```tsx
 * function LoginButton() {
 *   const { isAuthenticated, isLoading } = useAuthStatus();
 *
 *   if (isLoading) {
 *     return <Spinner />;
 *   }
 *
 *   return isAuthenticated ? (
 *     <LogoutButton />
 *   ) : (
 *     <Link href="/auth/login">Login</Link>
 *   );
 * }
 * ```
 *
 * @example
 * ```tsx
 * // Conditional content based on auth status
 * function PageHeader() {
 *   const { isAuthenticated } = useAuthStatus();
 *
 *   return (
 *     <header>
 *       <Logo />
 *       {isAuthenticated ? (
 *         <UserMenu />
 *       ) : (
 *         <GuestMenu />
 *       )}
 *     </header>
 *   );
 * }
 * ```
 */
export function useAuthStatus(): UseAuthStatusReturn {
  const { isAuthenticated, isLoading } = useAuth();

  return {
    isAuthenticated,
    isLoading,
  };
}
