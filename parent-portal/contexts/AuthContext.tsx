'use client';

/**
 * Authentication Context Provider for LAYA Parent Portal.
 *
 * Provides authentication state, user information, and auth operations
 * to all client components in the application.
 *
 * Features:
 * - User state management
 * - Authentication status tracking
 * - Loading states
 * - User profile updates
 * - Token-based authentication
 * - Automatic session validation
 *
 * @example
 * ```tsx
 * // In app/layout.tsx
 * import { AuthProvider } from '@/contexts/AuthContext';
 *
 * export default function RootLayout({ children }) {
 *   return (
 *     <html>
 *       <body>
 *         <AuthProvider>
 *           {children}
 *         </AuthProvider>
 *       </body>
 *     </html>
 *   );
 * }
 * ```
 *
 * @example
 * ```tsx
 * // In a component
 * import { useAuth } from '@/contexts/AuthContext';
 *
 * function MyComponent() {
 *   const { user, isAuthenticated, isLoading, updateUser } = useAuth();
 *
 *   if (isLoading) return <div>Loading...</div>;
 *   if (!isAuthenticated) return <div>Please login</div>;
 *
 *   return <div>Welcome {user?.firstName}!</div>;
 * }
 * ```
 */

import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import { User } from '@/lib/auth';

// ============================================================================
// Types
// ============================================================================

/**
 * Authentication context value provided to consuming components.
 */
export interface AuthContextValue {
  /**
   * Current authenticated user or null if not authenticated.
   */
  user: User | null;

  /**
   * Whether the user is currently authenticated.
   */
  isAuthenticated: boolean;

  /**
   * Whether the auth state is being loaded/validated.
   * True on initial load, false after first check completes.
   */
  isLoading: boolean;

  /**
   * Update the current user information.
   * Use this when user profile data changes.
   *
   * @param user - Updated user information
   *
   * @example
   * ```ts
   * updateUser({
   *   ...currentUser,
   *   firstName: 'John',
   *   lastName: 'Doe',
   * });
   * ```
   */
  updateUser: (user: User | null) => void;

  /**
   * Refresh the authentication state by checking the server.
   * Useful after login, logout, or when you need to verify session.
   *
   * @returns Promise that resolves when refresh is complete
   */
  refreshAuth: () => Promise<void>;
}

// ============================================================================
// Context Creation
// ============================================================================

/**
 * Authentication context.
 * Use the useAuth hook instead of consuming this directly.
 */
const AuthContext = createContext<AuthContextValue | undefined>(undefined);

// ============================================================================
// Provider Component
// ============================================================================

interface AuthProviderProps {
  children: React.ReactNode;
  /**
   * Initial user state (optional).
   * Useful for server-side rendering or testing.
   */
  initialUser?: User | null;
}

/**
 * Authentication Provider component.
 * Wrap your app with this to provide auth context to all components.
 *
 * @param props - Provider props
 * @param props.children - Child components
 * @param props.initialUser - Optional initial user state
 */
export function AuthProvider({ children, initialUser = null }: AuthProviderProps) {
  const [user, setUser] = useState<User | null>(initialUser);
  const [isLoading, setIsLoading] = useState(true);

  /**
   * Check authentication status on mount.
   * Fetches current user from server if authenticated.
   */
  const checkAuth = useCallback(async () => {
    try {
      setIsLoading(true);

      // Call API to get current user session
      const response = await fetch('/api/auth/me', {
        method: 'GET',
        credentials: 'include', // Include httpOnly cookies
        headers: {
          'Content-Type': 'application/json',
        },
      });

      if (response.ok) {
        const data = await response.json();
        setUser(data.user || null);
      } else {
        // Not authenticated or session expired
        setUser(null);
      }
    } catch (error) {
      // Network error or server unavailable
      console.error('Error checking authentication:', error);
      setUser(null);
    } finally {
      setIsLoading(false);
    }
  }, []);

  /**
   * Check auth on mount.
   */
  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  /**
   * Update user information.
   */
  const updateUser = useCallback((newUser: User | null) => {
    setUser(newUser);
  }, []);

  /**
   * Refresh authentication state.
   */
  const refreshAuth = useCallback(async () => {
    await checkAuth();
  }, [checkAuth]);

  const value: AuthContextValue = {
    user,
    isAuthenticated: user !== null,
    isLoading,
    updateUser,
    refreshAuth,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

// ============================================================================
// Hook
// ============================================================================

/**
 * Hook to access authentication context.
 *
 * @returns Authentication context value
 * @throws Error if used outside of AuthProvider
 *
 * @example
 * ```tsx
 * function MyComponent() {
 *   const { user, isAuthenticated, isLoading } = useAuth();
 *
 *   if (isLoading) {
 *     return <LoadingSpinner />;
 *   }
 *
 *   if (!isAuthenticated) {
 *     return <div>Please log in</div>;
 *   }
 *
 *   return <div>Welcome, {user?.firstName}!</div>;
 * }
 * ```
 */
export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }

  return context;
}

// ============================================================================
// Exports
// ============================================================================

export default AuthContext;
