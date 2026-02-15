/**
 * Base API client with error handling for LAYA Parent Portal.
 *
 * Provides type-safe HTTP methods with standardized error handling,
 * request/response transformation, and retry logic.
 */

import type { ApiErrorResponse } from './types';

// ============================================================================
// Configuration
// ============================================================================

/**
 * Environment-based API URLs.
 */
export const API_CONFIG = {
  AI_SERVICE_URL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000',
  GIBBON_URL: process.env.NEXT_PUBLIC_GIBBON_URL || 'http://localhost:8080/gibbon',
} as const;

/**
 * Default request timeout in milliseconds.
 */
const DEFAULT_TIMEOUT = 30000;

/**
 * Maximum number of retry attempts for failed requests.
 */
const MAX_RETRIES = 3;

/**
 * HTTP status codes that should trigger a retry.
 */
const RETRYABLE_STATUS_CODES = [408, 429, 500, 502, 503, 504];

// ============================================================================
// Error Classes
// ============================================================================

/**
 * Custom error class for API errors.
 *
 * Provides structured error information including HTTP status code,
 * response body, and original error details.
 */
export class ApiError extends Error {
  public readonly status: number;
  public readonly statusText: string;
  public readonly body?: ApiErrorResponse;
  public readonly isNetworkError: boolean;
  public readonly isTimeout: boolean;

  constructor(
    message: string,
    status: number,
    statusText: string = '',
    body?: ApiErrorResponse,
    options: { isNetworkError?: boolean; isTimeout?: boolean } = {}
  ) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.statusText = statusText;
    this.body = body;
    this.isNetworkError = options.isNetworkError ?? false;
    this.isTimeout = options.isTimeout ?? false;

    // Maintain proper stack trace
    if (Error.captureStackTrace) {
      Error.captureStackTrace(this, ApiError);
    }
  }

  /**
   * Check if error is due to authentication failure.
   */
  get isUnauthorized(): boolean {
    return this.status === 401;
  }

  /**
   * Check if error is due to forbidden access.
   */
  get isForbidden(): boolean {
    return this.status === 403;
  }

  /**
   * Check if error is due to resource not found.
   */
  get isNotFound(): boolean {
    return this.status === 404;
  }

  /**
   * Check if error is a validation error.
   */
  get isValidationError(): boolean {
    return this.status === 422;
  }

  /**
   * Check if error is a server error.
   */
  get isServerError(): boolean {
    return this.status >= 500 && this.status < 600;
  }

  /**
   * Check if this error is retryable.
   */
  get isRetryable(): boolean {
    return this.isNetworkError || this.isTimeout || RETRYABLE_STATUS_CODES.includes(this.status);
  }

  /**
   * Get user-friendly error message.
   */
  get userMessage(): string {
    if (this.isNetworkError) {
      return 'Unable to connect to the server. Please check your internet connection.';
    }
    if (this.isTimeout) {
      return 'The request timed out. Please try again.';
    }
    if (this.isUnauthorized) {
      return 'Your session has expired. Please log in again.';
    }
    if (this.isForbidden) {
      return 'You do not have permission to perform this action.';
    }
    if (this.isNotFound) {
      return 'The requested resource was not found.';
    }
    if (this.isValidationError) {
      return this.body?.detail || 'The submitted data is invalid.';
    }
    if (this.isServerError) {
      return 'A server error occurred. Please try again later.';
    }
    return this.body?.detail || this.message || 'An unexpected error occurred.';
  }
}

// ============================================================================
// Request Options
// ============================================================================

/**
 * Options for API requests.
 */
export interface RequestOptions extends Omit<RequestInit, 'body'> {
  /** Request timeout in milliseconds */
  timeout?: number;
  /** Number of retry attempts */
  retries?: number;
  /** Custom query parameters */
  params?: Record<string, string | number | boolean | undefined>;
  /** Request body (will be JSON stringified) */
  body?: unknown;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Create a timeout promise that rejects after specified milliseconds.
 */
function createTimeoutPromise(ms: number): Promise<never> {
  return new Promise((_, reject) => {
    setTimeout(() => {
      reject(new ApiError('Request timed out', 0, '', undefined, { isTimeout: true }));
    }, ms);
  });
}

/**
 * Sleep for specified milliseconds.
 */
function sleep(ms: number): Promise<void> {
  return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Calculate exponential backoff delay.
 */
function getBackoffDelay(attempt: number, baseDelay: number = 1000): number {
  const delay = baseDelay * Math.pow(2, attempt);
  // Add jitter to prevent thundering herd
  const jitter = Math.random() * delay * 0.1;
  return Math.min(delay + jitter, 30000);
}

/**
 * Build URL with query parameters.
 */
function buildUrl(baseUrl: string, path: string, params?: Record<string, string | number | boolean | undefined>): string {
  const url = new URL(path, baseUrl);

  if (params) {
    Object.entries(params).forEach(([key, value]) => {
      if (value !== undefined) {
        url.searchParams.append(key, String(value));
      }
    });
  }

  return url.toString();
}

/**
 * Parse error response body.
 */
async function parseErrorBody(response: Response): Promise<ApiErrorResponse | undefined> {
  try {
    const contentType = response.headers.get('content-type');
    if (contentType?.includes('application/json')) {
      return await response.json() as ApiErrorResponse;
    }
    const text = await response.text();
    return { detail: text };
  } catch {
    return undefined;
  }
}

// ============================================================================
// API Client Class
// ============================================================================

/**
 * Base API client for making HTTP requests.
 *
 * Features:
 * - Type-safe request/response handling
 * - Automatic JSON serialization
 * - Request timeout support
 * - Retry logic with exponential backoff
 * - Structured error handling
 */
export class ApiClient {
  private readonly baseUrl: string;
  private readonly defaultHeaders: Record<string, string>;

  constructor(baseUrl: string, defaultHeaders: Record<string, string> = {}) {
    this.baseUrl = baseUrl;
    this.defaultHeaders = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      ...defaultHeaders,
    };
  }

  /**
   * Make an HTTP request with retry logic.
   */
  private async request<T>(
    method: string,
    path: string,
    options: RequestOptions = {}
  ): Promise<T> {
    const {
      timeout = DEFAULT_TIMEOUT,
      retries = MAX_RETRIES,
      params,
      body,
      headers,
      ...fetchOptions
    } = options;

    const url = buildUrl(this.baseUrl, path, params);

    const requestInit: RequestInit = {
      method,
      headers: {
        ...this.defaultHeaders,
        ...headers,
      },
      ...fetchOptions,
    };

    if (body !== undefined) {
      requestInit.body = JSON.stringify(body);
    }

    let lastError: ApiError | null = null;

    for (let attempt = 0; attempt <= retries; attempt++) {
      try {
        const response = await Promise.race([
          fetch(url, requestInit),
          createTimeoutPromise(timeout),
        ]);

        if (!response.ok) {
          const errorBody = await parseErrorBody(response);
          const error = new ApiError(
            errorBody?.detail || `HTTP ${response.status}: ${response.statusText}`,
            response.status,
            response.statusText,
            errorBody
          );

          if (error.isRetryable && attempt < retries) {
            lastError = error;
            await sleep(getBackoffDelay(attempt));
            continue;
          }

          throw error;
        }

        // Handle empty responses (204 No Content)
        if (response.status === 204) {
          return undefined as T;
        }

        const contentType = response.headers.get('content-type');
        if (contentType?.includes('application/json')) {
          return await response.json() as T;
        }

        // Return text for non-JSON responses
        return await response.text() as unknown as T;
      } catch (error) {
        if (error instanceof ApiError) {
          throw error;
        }

        // Handle network errors
        const networkError = new ApiError(
          'Network error: Unable to connect to server',
          0,
          '',
          undefined,
          { isNetworkError: true }
        );

        if (attempt < retries) {
          lastError = networkError;
          await sleep(getBackoffDelay(attempt));
          continue;
        }

        throw networkError;
      }
    }

    // Should not reach here, but throw last error if we do
    throw lastError || new ApiError('Request failed after retries', 0);
  }

  /**
   * Make a GET request.
   */
  async get<T>(path: string, options?: RequestOptions): Promise<T> {
    return this.request<T>('GET', path, options);
  }

  /**
   * Make a POST request.
   */
  async post<T>(path: string, body?: unknown, options?: RequestOptions): Promise<T> {
    return this.request<T>('POST', path, { ...options, body });
  }

  /**
   * Make a PUT request.
   */
  async put<T>(path: string, body?: unknown, options?: RequestOptions): Promise<T> {
    return this.request<T>('PUT', path, { ...options, body });
  }

  /**
   * Make a PATCH request.
   */
  async patch<T>(path: string, body?: unknown, options?: RequestOptions): Promise<T> {
    return this.request<T>('PATCH', path, { ...options, body });
  }

  /**
   * Make a DELETE request.
   */
  async delete<T>(path: string, options?: RequestOptions): Promise<T> {
    return this.request<T>('DELETE', path, options);
  }
}

// ============================================================================
// Singleton Instances
// ============================================================================

/**
 * Pre-configured API client for AI service.
 */
export const aiServiceClient = new ApiClient(API_CONFIG.AI_SERVICE_URL);

/**
 * Pre-configured API client for Gibbon CMS.
 */
export const gibbonClient = new ApiClient(API_CONFIG.GIBBON_URL);
