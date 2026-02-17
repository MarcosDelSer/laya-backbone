/**
 * LAYA Teacher App - Secure API Client
 *
 * Type-safe HTTP client with secure token management for the mobile app.
 * Integrates with secureStorage for automatic JWT token handling.
 *
 * Security Features:
 * - Automatic token retrieval from secure storage (iOS Keychain / Android Keystore)
 * - Secure token storage after authentication
 * - Automatic token refresh on 401 responses
 * - No tokens exposed in logs or error messages
 * - Type-safe request/response handling
 *
 * This implementation follows patterns from:
 * - teacher-app/src/api/client.ts (base API client)
 * - teacher-app/src/utils/secureStorage.ts (secure storage)
 * - parent-app/src/api/secureClient.ts (secure client pattern)
 */

import {API_CONFIG, buildApiUrl} from './config';
import type {ApiResponse, ApiError} from '../types';
import {
  getItem,
  setItem,
  removeItem,
  STORAGE_KEYS,
} from '../utils/secureStorage';

type HttpMethod = 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH';

interface RequestOptions {
  method?: HttpMethod;
  body?: unknown;
  headers?: Record<string, string>;
  params?: Record<string, string>;
  timeout?: number;
  skipAuth?: boolean; // Skip automatic token inclusion (for login/public endpoints)
}

/**
 * Token refresh state to prevent multiple simultaneous refresh attempts
 */
let isRefreshing = false;
let refreshPromise: Promise<string | null> | null = null;

/**
 * Get the current access token from secure storage
 */
async function getAccessToken(): Promise<string | null> {
  const result = await getItem(STORAGE_KEYS.ACCESS_TOKEN);
  return result.success ? result.data : null;
}

/**
 * Get the current refresh token from secure storage
 */
async function getRefreshToken(): Promise<string | null> {
  const result = await getItem(STORAGE_KEYS.REFRESH_TOKEN);
  return result.success ? result.data : null;
}

/**
 * Store authentication tokens securely
 */
export async function setAuthTokens(
  accessToken: string,
  refreshToken?: string,
): Promise<void> {
  await setItem(STORAGE_KEYS.ACCESS_TOKEN, accessToken);
  if (refreshToken) {
    await setItem(STORAGE_KEYS.REFRESH_TOKEN, refreshToken);
  }
}

/**
 * Clear all authentication tokens from secure storage
 */
export async function clearAuthTokens(): Promise<void> {
  await removeItem(STORAGE_KEYS.ACCESS_TOKEN);
  await removeItem(STORAGE_KEYS.REFRESH_TOKEN);
  await removeItem(STORAGE_KEYS.USER_ID);
  await removeItem(STORAGE_KEYS.USER_EMAIL);
}

/**
 * Attempt to refresh the access token using the refresh token
 * Returns the new access token or null if refresh failed
 */
async function refreshAccessToken(): Promise<string | null> {
  // Prevent multiple simultaneous refresh attempts
  if (isRefreshing && refreshPromise) {
    return refreshPromise;
  }

  isRefreshing = true;
  refreshPromise = (async () => {
    try {
      const refreshToken = await getRefreshToken();
      if (!refreshToken) {
        return null;
      }

      const refreshUrl = buildApiUrl(API_CONFIG.endpoints.auth.refreshToken);
      const response = await fetch(refreshUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify({refreshToken}),
      });

      if (!response.ok) {
        // Refresh failed - clear tokens
        await clearAuthTokens();
        return null;
      }

      const data = await response.json();
      const newAccessToken = data?.accessToken;
      const newRefreshToken = data?.refreshToken;

      if (newAccessToken) {
        await setAuthTokens(newAccessToken, newRefreshToken);
        return newAccessToken;
      }

      return null;
    } catch (error) {
      // Refresh failed - clear tokens
      await clearAuthTokens();
      return null;
    } finally {
      isRefreshing = false;
      refreshPromise = null;
    }
  })();

  return refreshPromise;
}

/**
 * Create an AbortController with timeout
 */
function createTimeoutController(timeout: number): AbortController {
  const controller = new AbortController();
  setTimeout(() => controller.abort(), timeout);
  return controller;
}

/**
 * Parse API error from response
 */
function parseApiError(status: number, body: unknown): ApiError {
  if (typeof body === 'object' && body !== null && 'error' in body) {
    const errorBody = body as {error: {code?: string; message?: string}};
    return {
      code: errorBody.error.code || `HTTP_${status}`,
      message: errorBody.error.message || `Request failed with status ${status}`,
    };
  }

  // Default error messages based on status code
  const defaultMessages: Record<number, string> = {
    400: 'Bad request - invalid data provided',
    401: 'Authentication required - please log in',
    403: 'Access denied - insufficient permissions',
    404: 'Resource not found',
    429: 'Too many requests - please try again later',
    500: 'Server error - please try again later',
    503: 'Service temporarily unavailable',
  };

  return {
    code: `HTTP_${status}`,
    message: defaultMessages[status] || `Request failed with status ${status}`,
  };
}

/**
 * Make an API request with automatic token management
 */
export async function secureApiRequest<T>(
  endpoint: string,
  options: RequestOptions = {},
): Promise<ApiResponse<T>> {
  const {
    method = 'GET',
    body,
    headers = {},
    params,
    timeout = API_CONFIG.timeout,
    skipAuth = false,
  } = options;

  const url = buildApiUrl(endpoint, params);
  const controller = createTimeoutController(timeout);

  const requestHeaders: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...headers,
  };

  // Add authentication token if available and not skipped
  if (!skipAuth) {
    const token = await getAccessToken();
    if (token) {
      requestHeaders['Authorization'] = `Bearer ${token}`;
    }
  }

  try {
    const response = await fetch(url, {
      method,
      headers: requestHeaders,
      body: body ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });

    const responseBody = await response.json().catch(() => null);

    // Handle 401 - attempt token refresh and retry once
    if (response.status === 401 && !skipAuth) {
      const newToken = await refreshAccessToken();
      if (newToken) {
        // Retry the request with the new token
        requestHeaders['Authorization'] = `Bearer ${newToken}`;
        const retryResponse = await fetch(url, {
          method,
          headers: requestHeaders,
          body: body ? JSON.stringify(body) : undefined,
          signal: controller.signal,
        });

        const retryBody = await retryResponse.json().catch(() => null);

        if (!retryResponse.ok) {
          return {
            success: false,
            data: null,
            error: parseApiError(retryResponse.status, retryBody),
          };
        }

        return {
          success: true,
          data: retryBody as T,
          error: null,
        };
      }

      // Refresh failed or no refresh token - return 401 error
      return {
        success: false,
        data: null,
        error: {
          code: 'AUTH_REQUIRED',
          message: 'Authentication required - please log in',
        },
      };
    }

    if (!response.ok) {
      return {
        success: false,
        data: null,
        error: parseApiError(response.status, responseBody),
      };
    }

    return {
      success: true,
      data: responseBody as T,
      error: null,
    };
  } catch (error) {
    if (error instanceof Error) {
      if (error.name === 'AbortError') {
        return {
          success: false,
          data: null,
          error: {
            code: 'TIMEOUT',
            message: `Request timed out after ${timeout}ms`,
          },
        };
      }

      return {
        success: false,
        data: null,
        error: {
          code: 'NETWORK_ERROR',
          message: error.message || 'Network request failed',
        },
      };
    }

    return {
      success: false,
      data: null,
      error: {
        code: 'UNKNOWN_ERROR',
        message: 'An unknown error occurred',
      },
    };
  }
}

/**
 * Convenience methods for common HTTP methods
 * All methods use secure token management by default
 */
export const secureApi = {
  /**
   * GET request with automatic token management
   */
  get: <T>(endpoint: string, params?: Record<string, string>, skipAuth?: boolean) =>
    secureApiRequest<T>(endpoint, {method: 'GET', params, skipAuth}),

  /**
   * POST request with automatic token management
   */
  post: <T>(
    endpoint: string,
    body?: unknown,
    params?: Record<string, string>,
    skipAuth?: boolean,
  ) => secureApiRequest<T>(endpoint, {method: 'POST', body, params, skipAuth}),

  /**
   * PUT request with automatic token management
   */
  put: <T>(
    endpoint: string,
    body?: unknown,
    params?: Record<string, string>,
    skipAuth?: boolean,
  ) => secureApiRequest<T>(endpoint, {method: 'PUT', body, params, skipAuth}),

  /**
   * PATCH request with automatic token management
   */
  patch: <T>(
    endpoint: string,
    body?: unknown,
    params?: Record<string, string>,
    skipAuth?: boolean,
  ) => secureApiRequest<T>(endpoint, {method: 'PATCH', body, params, skipAuth}),

  /**
   * DELETE request with automatic token management
   */
  delete: <T>(endpoint: string, params?: Record<string, string>, skipAuth?: boolean) =>
    secureApiRequest<T>(endpoint, {method: 'DELETE', params, skipAuth}),
};

/**
 * Authentication helper methods
 */
export const authApi = {
  /**
   * Login and store tokens securely
   */
  login: async (
    email: string,
    password: string,
  ): Promise<ApiResponse<{user: any; accessToken: string; refreshToken: string}>> => {
    const response = await secureApiRequest<{
      user: any;
      accessToken: string;
      refreshToken: string;
    }>(API_CONFIG.endpoints.auth.login, {
      method: 'POST',
      body: {email, password},
      skipAuth: true,
    });

    // Store tokens on successful login
    if (response.success && response.data) {
      await setAuthTokens(response.data.accessToken, response.data.refreshToken);
      // Store user ID and email for convenience
      if (response.data.user?.id) {
        await setItem(STORAGE_KEYS.USER_ID, response.data.user.id);
      }
      if (response.data.user?.email) {
        await setItem(STORAGE_KEYS.USER_EMAIL, response.data.user.email);
      }
    }

    return response;
  },

  /**
   * Logout and clear all tokens
   */
  logout: async (): Promise<ApiResponse<void>> => {
    try {
      // Call logout endpoint (best effort - continue even if it fails)
      await secureApiRequest<void>(API_CONFIG.endpoints.auth.logout, {
        method: 'POST',
      });
    } catch (error) {
      // Ignore logout endpoint errors
    }

    // Always clear local tokens
    await clearAuthTokens();

    return {
      success: true,
      data: null,
      error: null,
    };
  },

  /**
   * Check if user is authenticated (has valid tokens)
   */
  isAuthenticated: async (): Promise<boolean> => {
    const token = await getAccessToken();
    return token !== null;
  },
};

export default secureApi;
