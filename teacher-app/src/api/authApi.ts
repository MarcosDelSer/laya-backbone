/**
 * LAYA Teacher App - Authentication API
 *
 * API functions for teacher authentication including login, logout,
 * token refresh, and user profile fetching.
 */

import {api, setSessionToken, getSessionToken} from './client';
import {API_CONFIG} from './config';
import type {
  ApiResponse,
  Teacher,
  LoginCredentials,
  LoginResponse,
  RefreshResponse,
} from '../types';

/**
 * Secure token storage key constants.
 * In production, use secure storage (Keychain for iOS, Keystore for Android).
 */
export {TOKEN_STORAGE_KEYS} from '../types';

/**
 * Auth endpoint for current user profile (not in base config).
 */
const AUTH_ME_ENDPOINT = '/modules/TeacherPortal/api/auth/me';

/**
 * Authenticate a teacher user with email and password.
 *
 * @param credentials - Login credentials (email, password)
 * @returns Promise with login response containing user and tokens
 *
 * @example
 * ```ts
 * const result = await login({
 *   email: 'teacher@example.com',
 *   password: 'secretpassword',
 * });
 *
 * if (result.success) {
 *   // Store tokens securely and update auth state
 *   await storeTokensSecurely(result.data.accessToken, result.data.refreshToken);
 * }
 * ```
 */
export async function login(
  credentials: LoginCredentials,
): Promise<ApiResponse<LoginResponse>> {
  const response = await api.post<LoginResponse>(
    API_CONFIG.endpoints.auth.login,
    credentials,
  );

  // If login successful, set the session token for subsequent requests
  if (response.success && response.data) {
    setSessionToken(response.data.accessToken);
  }

  return response;
}

/**
 * Log out the current user.
 *
 * Invalidates the current session on the server and clears local tokens.
 *
 * @returns Promise with logout status
 *
 * @example
 * ```ts
 * const result = await logout();
 * if (result.success) {
 *   // Navigate to login screen
 * }
 * ```
 */
export async function logout(): Promise<ApiResponse<void>> {
  const response = await api.post<void>(API_CONFIG.endpoints.auth.logout);

  // Clear session token regardless of server response
  setSessionToken(null);

  return response;
}

/**
 * Refresh the access token using a refresh token.
 *
 * Call this when the access token is expired or about to expire.
 *
 * @param refreshToken - The refresh token from initial login
 * @returns Promise with new tokens
 *
 * @example
 * ```ts
 * const result = await refreshAccessToken(storedRefreshToken);
 * if (result.success) {
 *   await updateStoredTokens(result.data.accessToken, result.data.refreshToken);
 * } else {
 *   // Refresh failed, redirect to login
 *   navigateToLogin();
 * }
 * ```
 */
export async function refreshAccessToken(
  refreshToken: string,
): Promise<ApiResponse<RefreshResponse>> {
  const response = await api.post<RefreshResponse>(
    API_CONFIG.endpoints.auth.refreshToken,
    {refreshToken},
  );

  // Update session token on successful refresh
  if (response.success && response.data) {
    setSessionToken(response.data.accessToken);
  }

  return response;
}

/**
 * Fetch the current authenticated user's profile.
 *
 * @returns Promise with teacher user profile
 *
 * @example
 * ```ts
 * const result = await getCurrentUser();
 * if (result.success) {
 *   setUserState(result.data);
 * }
 * ```
 */
export async function getCurrentUser(): Promise<ApiResponse<Teacher>> {
  return api.get<Teacher>(AUTH_ME_ENDPOINT);
}

/**
 * Initialize authentication state from stored tokens.
 *
 * Call this on app startup to restore authentication state.
 * This function should be used with a secure storage solution.
 *
 * @param storedAccessToken - Previously stored access token
 * @param storedRefreshToken - Previously stored refresh token
 * @returns Promise with restored auth state or null if restoration fails
 *
 * @example
 * ```ts
 * const accessToken = await secureStorage.get(TOKEN_STORAGE_KEYS.accessToken);
 * const refreshToken = await secureStorage.get(TOKEN_STORAGE_KEYS.refreshToken);
 *
 * if (accessToken && refreshToken) {
 *   const authState = await initializeAuth(accessToken, refreshToken);
 *   if (authState) {
 *     setAuthState(authState);
 *   }
 * }
 * ```
 */
export async function initializeAuth(
  storedAccessToken: string,
  storedRefreshToken: string,
): Promise<{user: Teacher; accessToken: string; refreshToken: string} | null> {
  // Set the stored token for the API client
  setSessionToken(storedAccessToken);

  // Try to get the current user with the stored token
  const userResult = await getCurrentUser();

  if (userResult.success && userResult.data) {
    // Token is valid, return the auth state
    return {
      user: userResult.data,
      accessToken: storedAccessToken,
      refreshToken: storedRefreshToken,
    };
  }

  // Access token might be expired, try to refresh
  const refreshResult = await refreshAccessToken(storedRefreshToken);

  if (refreshResult.success && refreshResult.data) {
    // Refresh successful, get user profile with new token
    const newUserResult = await getCurrentUser();

    if (newUserResult.success && newUserResult.data) {
      return {
        user: newUserResult.data,
        accessToken: refreshResult.data.accessToken,
        refreshToken: refreshResult.data.refreshToken,
      };
    }
  }

  // Both attempts failed, clear token
  setSessionToken(null);
  return null;
}

/**
 * Check if a session token is currently set.
 *
 * @returns boolean indicating if there's an active session token
 */
export function hasActiveSession(): boolean {
  return getSessionToken() !== null;
}

/**
 * Clear the current session token.
 *
 * Use this when handling authentication errors or logging out.
 */
export function clearSession(): void {
  setSessionToken(null);
}

export default {
  login,
  logout,
  refreshAccessToken,
  getCurrentUser,
  initializeAuth,
  hasActiveSession,
  clearSession,
};
