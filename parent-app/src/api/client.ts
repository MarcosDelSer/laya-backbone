/**
 * LAYA Parent App - API Client
 *
 * HTTP client for communicating with the Gibbon backend.
 * Handles authentication, error handling, and response parsing.
 */

import {API_CONFIG, buildApiUrl} from './config';
import type {ApiResponse, ApiError} from '../types';

type HttpMethod = 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH';

interface RequestOptions {
  method?: HttpMethod;
  body?: unknown;
  headers?: Record<string, string>;
  params?: Record<string, string>;
  timeout?: number;
}

// Session token storage - will be set after authentication
let sessionToken: string | null = null;

/**
 * Set the session token for authenticated requests
 */
export function setSessionToken(token: string | null): void {
  sessionToken = token;
}

/**
 * Get the current session token
 */
export function getSessionToken(): string | null {
  return sessionToken;
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

  return {
    code: `HTTP_${status}`,
    message: `Request failed with status ${status}`,
  };
}

/**
 * Make an API request to the backend
 */
export async function apiRequest<T>(
  endpoint: string,
  options: RequestOptions = {},
): Promise<ApiResponse<T>> {
  const {
    method = 'GET',
    body,
    headers = {},
    params,
    timeout = API_CONFIG.timeout,
  } = options;

  const url = buildApiUrl(endpoint, params);
  const controller = createTimeoutController(timeout);

  const requestHeaders: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...headers,
  };

  // Add authentication token if available
  if (sessionToken) {
    requestHeaders['Authorization'] = `Bearer ${sessionToken}`;
  }

  try {
    const response = await fetch(url, {
      method,
      headers: requestHeaders,
      body: body ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });

    const responseBody = await response.json().catch(() => null);

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
 */
export const api = {
  get: <T>(endpoint: string, params?: Record<string, string>) =>
    apiRequest<T>(endpoint, {method: 'GET', params}),

  post: <T>(endpoint: string, body?: unknown, params?: Record<string, string>) =>
    apiRequest<T>(endpoint, {method: 'POST', body, params}),

  put: <T>(endpoint: string, body?: unknown, params?: Record<string, string>) =>
    apiRequest<T>(endpoint, {method: 'PUT', body, params}),

  patch: <T>(endpoint: string, body?: unknown, params?: Record<string, string>) =>
    apiRequest<T>(endpoint, {method: 'PATCH', body, params}),

  delete: <T>(endpoint: string, params?: Record<string, string>) =>
    apiRequest<T>(endpoint, {method: 'DELETE', params}),
};

export default api;
