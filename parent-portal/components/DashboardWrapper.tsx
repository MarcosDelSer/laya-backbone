'use client';

/**
 * Dashboard wrapper component with authentication.
 *
 * This component wraps the dashboard content and ensures the user is authenticated.
 * It uses the useRequireAuth hook to automatically redirect unauthenticated users to login.
 */

import { useRequireAuth } from '@/hooks/useRequireAuth';

/**
 * Props for DashboardWrapper component
 */
interface DashboardWrapperProps {
  children: React.ReactNode;
}

/**
 * DashboardWrapper component.
 *
 * Ensures user is authenticated before rendering dashboard content.
 * Shows loading state while checking authentication.
 * Automatically redirects to login if not authenticated.
 *
 * @param props - Component props
 * @returns Dashboard wrapper component
 */
export function DashboardWrapper({ children }: DashboardWrapperProps) {
  const { isLoading } = useRequireAuth();

  // Show loading state while checking authentication
  if (isLoading) {
    return (
      <div className="flex h-screen items-center justify-center">
        <div className="text-center">
          <div className="inline-block h-8 w-8 animate-spin rounded-full border-4 border-solid border-primary-600 border-r-transparent"></div>
          <p className="mt-4 text-sm text-gray-600">Loading...</p>
        </div>
      </div>
    );
  }

  // Render dashboard content once authenticated
  return <>{children}</>;
}
