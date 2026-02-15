/**
 * LAYA Parent App - API Client
 *
 * HTTP client for communicating with backend services.
 * Handles authentication, error handling, retry logic with exponential backoff,
 * and response parsing.
 */

import {API_CONFIG, buildAiServiceUrl, buildGibbonUrl} from './config';
import type {ApiResponse, ApiError} from '../types';

type HttpMethod = 'GET' | 'POST' | 'PUT' | 'DELETE' | 'PATCH';

type ApiTarget = 'aiService' | 'gibbon';

interface RequestOptions {
  method?: HttpMethod;
  body?: unknown;
  headers?: Record<string, string>;
  params?: Record<string, string>;
  timeout?: number;
  retries?: number;
  target?: ApiTarget;
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
 * Check if error is retryable (network errors, timeouts, 5xx errors)
 */
function isRetryableError(error: unknown, status?: number): boolean {
  // Network errors and timeouts are retryable
  if (error instanceof Error) {
    if (error.name === 'AbortError') {
      return true;
    }
    // Network errors (fetch failed)
    if (error.message.includes('network') || error.message.includes('fetch')) {
      return true;
    }
  }

  // 5xx server errors are retryable
  if (status && status >= 500 && status < 600) {
    return true;
  }

  // Rate limiting (429) is retryable
  if (status === 429) {
    return true;
  }

  return false;
}

/**
 * Calculate delay for retry with exponential backoff
 */
function calculateRetryDelay(attempt: number): number {
  const {initialDelayMs, maxDelayMs, backoffMultiplier} = API_CONFIG.retryConfig;
  const delay = initialDelayMs * Math.pow(backoffMultiplier, attempt);
  // Add jitter to prevent thundering herd
  const jitter = Math.random() * 0.1 * delay;
  return Math.min(delay + jitter, maxDelayMs);
}

/**
 * Sleep for a specified duration
 */
function sleep(ms: number): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, ms));
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
  if (typeof body === 'object' && body !== null) {
    // Check for error object format
    if ('error' in body) {
      const errorBody = body as {error: {code?: string; message?: string; details?: Record<string, unknown>}};
      return {
        code: errorBody.error.code || `HTTP_${status}`,
        message: errorBody.error.message || `Request failed with status ${status}`,
        details: errorBody.error.details,
      };
    }

    // Check for direct error format
    if ('code' in body && 'message' in body) {
      const errorBody = body as {code?: string; message?: string; details?: Record<string, unknown>};
      return {
        code: errorBody.code || `HTTP_${status}`,
        message: errorBody.message || `Request failed with status ${status}`,
        details: errorBody.details,
      };
    }

    // Check for detail format (FastAPI style)
    if ('detail' in body) {
      const errorBody = body as {detail: string | unknown[]};
      const message = typeof errorBody.detail === 'string'
        ? errorBody.detail
        : JSON.stringify(errorBody.detail);
      return {
        code: `HTTP_${status}`,
        message,
      };
    }
  }

  return {
    code: `HTTP_${status}`,
    message: `Request failed with status ${status}`,
  };
}

/**
 * Build URL based on target API
 */
function buildUrl(endpoint: string, params?: Record<string, string>, target: ApiTarget = 'aiService'): string {
  return target === 'gibbon'
    ? buildGibbonUrl(endpoint, params)
    : buildAiServiceUrl(endpoint, params);
}

/**
 * Make a single API request attempt
 */
async function makeRequest(
  url: string,
  options: RequestOptions,
): Promise<{response: Response | null; body: unknown; error: Error | null}> {
  const {
    method = 'GET',
    body,
    headers = {},
    timeout = API_CONFIG.timeout,
  } = options;

  const controller = createTimeoutController(timeout);

  const requestHeaders: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    ...headers,
  };

  // Add authentication token if available
  if (sessionToken) {
    requestHeaders.Authorization = `Bearer ${sessionToken}`;
  }

  try {
    const response = await fetch(url, {
      method,
      headers: requestHeaders,
      body: body ? JSON.stringify(body) : undefined,
      signal: controller.signal,
    });

    const responseBody = await response.json().catch(() => null);
    return {response, body: responseBody, error: null};
  } catch (error) {
    return {response: null, body: null, error: error instanceof Error ? error : new Error('Unknown error')};
  }
}

/**
 * Make an API request with retry logic
 */
export async function apiRequest<T>(
  endpoint: string,
  options: RequestOptions = {},
): Promise<ApiResponse<T>> {
  const {
    params,
    retries = API_CONFIG.retryConfig.maxRetries,
    target = 'aiService',
  } = options;

  const url = buildUrl(endpoint, params, target);
  let lastError: ApiError | null = null;
  let attempt = 0;

  while (attempt <= retries) {
    const {response, body, error} = await makeRequest(url, options);

    // Handle fetch errors (network issues, timeouts)
    if (error) {
      const apiError: ApiError = error.name === 'AbortError'
        ? {code: 'TIMEOUT', message: `Request timed out after ${options.timeout || API_CONFIG.timeout}ms`}
        : {code: 'NETWORK_ERROR', message: error.message || 'Network request failed'};

      if (isRetryableError(error) && attempt < retries) {
        lastError = apiError;
        const delay = calculateRetryDelay(attempt);
        await sleep(delay);
        attempt++;
        continue;
      }

      return {
        success: false,
        data: null,
        error: apiError,
      };
    }

    // Handle HTTP error responses
    if (response && !response.ok) {
      const apiError = parseApiError(response.status, body);

      // Handle authentication errors - don't retry
      if (response.status === 401 || response.status === 403) {
        return {
          success: false,
          data: null,
          error: apiError,
        };
      }

      // Retry on 5xx or 429 errors
      if (isRetryableError(null, response.status) && attempt < retries) {
        lastError = apiError;
        const delay = calculateRetryDelay(attempt);
        await sleep(delay);
        attempt++;
        continue;
      }

      return {
        success: false,
        data: null,
        error: apiError,
      };
    }

    // Success
    return {
      success: true,
      data: body as T,
      error: null,
    };
  }

  // All retries exhausted
  return {
    success: false,
    data: null,
    error: lastError || {code: 'UNKNOWN_ERROR', message: 'An unknown error occurred'},
  };
}

/**
 * Convenience methods for common HTTP methods
 */
export const api = {
  /**
   * Make a GET request
   */
  get: <T>(endpoint: string, params?: Record<string, string>, target?: ApiTarget) =>
    apiRequest<T>(endpoint, {method: 'GET', params, target}),

  /**
   * Make a POST request
   */
  post: <T>(endpoint: string, body?: unknown, params?: Record<string, string>, target?: ApiTarget) =>
    apiRequest<T>(endpoint, {method: 'POST', body, params, target}),

  /**
   * Make a PUT request
   */
  put: <T>(endpoint: string, body?: unknown, params?: Record<string, string>, target?: ApiTarget) =>
    apiRequest<T>(endpoint, {method: 'PUT', body, params, target}),

  /**
   * Make a PATCH request
   */
  patch: <T>(endpoint: string, body?: unknown, params?: Record<string, string>, target?: ApiTarget) =>
    apiRequest<T>(endpoint, {method: 'PATCH', body, params, target}),

  /**
   * Make a DELETE request
   */
  delete: <T>(endpoint: string, params?: Record<string, string>, target?: ApiTarget) =>
    apiRequest<T>(endpoint, {method: 'DELETE', params, target}),
};

export default api;
