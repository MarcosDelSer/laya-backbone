/**
 * Client-side logout utilities for LAYA Parent Portal.
 *
 * Provides functions to handle user logout, session cleanup, and redirect logic.
 */

/**
 * Perform logout operation with complete cleanup.
 *
 * Steps performed:
 * 1. Clear any redirect paths stored in session storage
 * 2. Call logout API to clear httpOnly cookies
 * 3. Clear any other session data
 * 4. Redirect to login page
 *
 * @returns Promise that resolves when logout is complete
 *
 * @example
 * ```ts
 * // In a client component
 * const handleLogout = async () => {
 *   await performLogout();
 * };
 * ```
 */
export async function performLogout(): Promise<void> {
  try {
    // Clear session storage before making API call
    clearSessionData();

    // Call logout API to clear httpOnly cookies
    const response = await fetch('/api/auth/logout', {
      method: 'POST',
      credentials: 'include', // Include cookies
    });

    if (!response.ok) {
      throw new Error('Logout request failed');
    }

    // Redirect to login page
    window.location.href = '/auth/login';
  } catch (error) {
    console.error('Logout error:', error);

    // Even if API call fails, clear local state and redirect
    // This ensures user is logged out from the client side
    clearSessionData();
    window.location.href = '/auth/login';
  }
}

/**
 * Clear all session data stored in sessionStorage.
 * This includes redirect paths and any other temporary session data.
 */
function clearSessionData(): void {
  if (typeof window === 'undefined') {
    return;
  }

  try {
    // Clear specific session items
    sessionStorage.removeItem('redirectAfterLogin');

    // Optionally clear all session storage
    // Note: Only clear items that belong to the app
    // sessionStorage.clear();
  } catch (error) {
    console.error('Error clearing session data:', error);
  }
}

/**
 * Check if we're in a browser environment.
 * @returns True if running in browser, false otherwise
 */
export function isBrowser(): boolean {
  return typeof window !== 'undefined';
}
