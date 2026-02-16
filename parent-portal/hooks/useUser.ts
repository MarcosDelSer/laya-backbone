'use client';

/**
 * LAYA Parent Portal - useUser Hook
 *
 * A simplified hook for accessing user information.
 * Returns the current user or null if not authenticated.
 *
 * This is a convenience hook that extracts just the user
 * from the AuthContext. Use this when you only need user data
 * and don't need other auth state/methods.
 */

import { useAuth } from '@/contexts/AuthContext';
import type { User } from '@/lib/auth';

/**
 * Return type for useUser hook
 */
export interface UseUserReturn {
  /**
   * Current authenticated user or null
   */
  user: User | null;

  /**
   * Whether user data is being loaded
   */
  isLoading: boolean;
}

/**
 * Hook to access current user information.
 *
 * @returns Current user and loading state
 *
 * @example
 * ```tsx
 * function UserProfile() {
 *   const { user, isLoading } = useUser();
 *
 *   if (isLoading) {
 *     return <div>Loading...</div>;
 *   }
 *
 *   if (!user) {
 *     return <div>Not logged in</div>;
 *   }
 *
 *   return (
 *     <div>
 *       <h1>{user.firstName} {user.lastName}</h1>
 *       <p>{user.email}</p>
 *     </div>
 *   );
 * }
 * ```
 *
 * @example
 * ```tsx
 * // Just check if user exists
 * function WelcomeMessage() {
 *   const { user } = useUser();
 *
 *   return user ? (
 *     <p>Welcome back, {user.firstName}!</p>
 *   ) : (
 *     <p>Welcome, guest!</p>
 *   );
 * }
 * ```
 */
export function useUser(): UseUserReturn {
  const { user, isLoading } = useAuth();

  return {
    user,
    isLoading,
  };
}
