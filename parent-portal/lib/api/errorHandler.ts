/**
 * Centralized API error handling utilities for LAYA Parent Portal.
 *
 * This module provides:
 * - Standardized error response parsing
 * - Error classification and categorization
 * - User-friendly error message generation
 * - Request/correlation ID tracking
 * - Error logging with context
 * - Integration with ErrorBoundary component
 */

import { ApiError } from '../api';

// ============================================================================
// Types and Interfaces
// ============================================================================

/**
 * Standardized error response from AI service error middleware.
 */
export interface StandardErrorResponse {
  error: {
    type: string;
    message: string;
    request_id: string;
    correlation_id: string;
    details?: string;
  };
}

/**
 * Legacy error response format (for backwards compatibility).
 */
export interface LegacyErrorResponse {
  detail: string;
  statusCode?: number;
}

/**
 * Combined error response type.
 */
export type ApiErrorBody = StandardErrorResponse | LegacyErrorResponse;

/**
 * Error categories for classification.
 */
export enum ErrorCategory {
  AUTHENTICATION = 'authentication',
  AUTHORIZATION = 'authorization',
  VALIDATION = 'validation',
  NOT_FOUND = 'not_found',
  RATE_LIMIT = 'rate_limit',
  SERVER_ERROR = 'server_error',
  NETWORK_ERROR = 'network_error',
  TIMEOUT = 'timeout',
  UNKNOWN = 'unknown',
}

/**
 * Error severity levels.
 */
export enum ErrorSeverity {
  LOW = 'low',
  MEDIUM = 'medium',
  HIGH = 'high',
  CRITICAL = 'critical',
}

/**
 * Structured error information.
 */
export interface ErrorInfo {
  category: ErrorCategory;
  severity: ErrorSeverity;
  message: string;
  userMessage: string;
  requestId?: string;
  correlationId?: string;
  statusCode: number;
  isRetryable: boolean;
  details?: string;
  originalError: unknown;
}

/**
 * Error logging options.
 */
export interface ErrorLogOptions {
  /** Include stack trace in logs */
  includeStack?: boolean;
  /** Additional context to log */
  context?: Record<string, unknown>;
  /** Whether to send error to external monitoring service */
  sendToMonitoring?: boolean;
}

// ============================================================================
// Error Classification
// ============================================================================

/**
 * Classify an error into a category based on status code and error details.
 */
export function classifyError(error: unknown): ErrorCategory {
  if (!(error instanceof ApiError)) {
    if (error instanceof Error && error.message.includes('network')) {
      return ErrorCategory.NETWORK_ERROR;
    }
    return ErrorCategory.UNKNOWN;
  }

  // Network and timeout errors
  if (error.isNetworkError) {
    return ErrorCategory.NETWORK_ERROR;
  }
  if (error.isTimeout) {
    return ErrorCategory.TIMEOUT;
  }

  // HTTP status code based classification
  switch (error.status) {
    case 401:
      return ErrorCategory.AUTHENTICATION;
    case 403:
      return ErrorCategory.AUTHORIZATION;
    case 404:
      return ErrorCategory.NOT_FOUND;
    case 422:
      return ErrorCategory.VALIDATION;
    case 429:
      return ErrorCategory.RATE_LIMIT;
    case 500:
    case 502:
    case 503:
    case 504:
      return ErrorCategory.SERVER_ERROR;
    default:
      return ErrorCategory.UNKNOWN;
  }
}

/**
 * Determine error severity based on category and status code.
 */
export function determineErrorSeverity(
  category: ErrorCategory,
  statusCode: number
): ErrorSeverity {
  // Critical errors requiring immediate attention
  if (category === ErrorCategory.AUTHENTICATION && statusCode === 401) {
    return ErrorSeverity.HIGH;
  }
  if (category === ErrorCategory.SERVER_ERROR) {
    return ErrorSeverity.CRITICAL;
  }

  // High severity errors
  if (category === ErrorCategory.AUTHORIZATION) {
    return ErrorSeverity.HIGH;
  }
  if (category === ErrorCategory.NETWORK_ERROR) {
    return ErrorSeverity.HIGH;
  }

  // Medium severity errors
  if (category === ErrorCategory.TIMEOUT) {
    return ErrorSeverity.MEDIUM;
  }
  if (category === ErrorCategory.RATE_LIMIT) {
    return ErrorSeverity.MEDIUM;
  }
  if (category === ErrorCategory.VALIDATION) {
    return ErrorSeverity.MEDIUM;
  }

  // Low severity errors
  if (category === ErrorCategory.NOT_FOUND) {
    return ErrorSeverity.LOW;
  }

  return ErrorSeverity.LOW;
}

// ============================================================================
// Error Response Parsing
// ============================================================================

/**
 * Check if error response is in standard format.
 */
function isStandardErrorResponse(
  body: unknown
): body is StandardErrorResponse {
  return (
    typeof body === 'object' &&
    body !== null &&
    'error' in body &&
    typeof (body as StandardErrorResponse).error === 'object' &&
    'type' in (body as StandardErrorResponse).error &&
    'message' in (body as StandardErrorResponse).error
  );
}

/**
 * Check if error response is in legacy format.
 */
function isLegacyErrorResponse(body: unknown): body is LegacyErrorResponse {
  return (
    typeof body === 'object' &&
    body !== null &&
    'detail' in body &&
    typeof (body as LegacyErrorResponse).detail === 'string'
  );
}

/**
 * Extract error message from various error response formats.
 */
export function extractErrorMessage(body: unknown): string {
  if (isStandardErrorResponse(body)) {
    return body.error.message;
  }
  if (isLegacyErrorResponse(body)) {
    return body.detail;
  }
  return 'An unexpected error occurred';
}

/**
 * Extract request ID from error response.
 */
export function extractRequestId(body: unknown): string | undefined {
  if (isStandardErrorResponse(body)) {
    return body.error.request_id;
  }
  return undefined;
}

/**
 * Extract correlation ID from error response.
 */
export function extractCorrelationId(body: unknown): string | undefined {
  if (isStandardErrorResponse(body)) {
    return body.error.correlation_id;
  }
  return undefined;
}

/**
 * Extract error details from error response.
 */
export function extractErrorDetails(body: unknown): string | undefined {
  if (isStandardErrorResponse(body)) {
    return body.error.details;
  }
  return undefined;
}

// ============================================================================
// User-Friendly Error Messages
// ============================================================================

/**
 * Get user-friendly error message based on error category.
 */
export function getUserFriendlyMessage(
  category: ErrorCategory,
  originalMessage?: string
): string {
  switch (category) {
    case ErrorCategory.AUTHENTICATION:
      return 'Your session has expired. Please log in again to continue.';

    case ErrorCategory.AUTHORIZATION:
      return 'You do not have permission to perform this action. Please contact your administrator if you believe this is an error.';

    case ErrorCategory.VALIDATION:
      return originalMessage || 'The information you provided is invalid. Please check your input and try again.';

    case ErrorCategory.NOT_FOUND:
      return 'The requested information could not be found. It may have been removed or you may not have access to it.';

    case ErrorCategory.RATE_LIMIT:
      return 'You have made too many requests. Please wait a moment and try again.';

    case ErrorCategory.SERVER_ERROR:
      return 'We encountered a problem on our end. Our team has been notified and is working to fix it. Please try again later.';

    case ErrorCategory.NETWORK_ERROR:
      return 'Unable to connect to the server. Please check your internet connection and try again.';

    case ErrorCategory.TIMEOUT:
      return 'The request took too long to complete. Please try again.';

    case ErrorCategory.UNKNOWN:
    default:
      return originalMessage || 'An unexpected error occurred. Please try again or contact support if the problem persists.';
  }
}

// ============================================================================
// Error Information Extraction
// ============================================================================

/**
 * Extract comprehensive error information from an error.
 */
export function extractErrorInfo(error: unknown): ErrorInfo {
  const category = classifyError(error);

  if (error instanceof ApiError) {
    const severity = determineErrorSeverity(category, error.status);
    const message = extractErrorMessage(error.body);
    const userMessage = getUserFriendlyMessage(category, error.userMessage);
    const requestId = extractRequestId(error.body);
    const correlationId = extractCorrelationId(error.body);
    const details = extractErrorDetails(error.body);

    return {
      category,
      severity,
      message,
      userMessage,
      requestId,
      correlationId,
      statusCode: error.status,
      isRetryable: error.isRetryable,
      details,
      originalError: error,
    };
  }

  // Handle non-ApiError errors
  const message = error instanceof Error ? error.message : String(error);
  const severity = determineErrorSeverity(category, 0);

  return {
    category,
    severity,
    message,
    userMessage: getUserFriendlyMessage(category, message),
    statusCode: 0,
    isRetryable: category === ErrorCategory.NETWORK_ERROR || category === ErrorCategory.TIMEOUT,
    originalError: error,
  };
}

// ============================================================================
// Error Logging
// ============================================================================

/**
 * Log level mapping based on error severity.
 */
const SEVERITY_TO_LOG_LEVEL: Record<ErrorSeverity, 'error' | 'warn' | 'info'> = {
  [ErrorSeverity.CRITICAL]: 'error',
  [ErrorSeverity.HIGH]: 'error',
  [ErrorSeverity.MEDIUM]: 'warn',
  [ErrorSeverity.LOW]: 'info',
};

/**
 * Log an error with structured information.
 */
export function logError(
  error: unknown,
  options: ErrorLogOptions = {}
): void {
  const errorInfo = extractErrorInfo(error);
  const logLevel = SEVERITY_TO_LOG_LEVEL[errorInfo.severity];

  const logData: Record<string, unknown> = {
    category: errorInfo.category,
    severity: errorInfo.severity,
    message: errorInfo.message,
    statusCode: errorInfo.statusCode,
    requestId: errorInfo.requestId,
    correlationId: errorInfo.correlationId,
    timestamp: new Date().toISOString(),
    ...options.context,
  };

  // Add stack trace if requested and available
  if (options.includeStack && error instanceof Error && error.stack) {
    logData.stack = error.stack;
  }

  // Add error details if available
  if (errorInfo.details) {
    logData.details = errorInfo.details;
  }

  // Log to console (in production, this would go to a logging service)
  console[logLevel]('[API Error]', logData);

  // Send to external monitoring service if configured
  if (options.sendToMonitoring && typeof window !== 'undefined') {
    // Integration point for external monitoring services (e.g., Sentry, LogRocket)
    sendToMonitoringService(errorInfo, logData);
  }
}

/**
 * Send error to external monitoring service.
 * This is a placeholder for integration with services like Sentry.
 */
function sendToMonitoringService(
  errorInfo: ErrorInfo,
  logData: Record<string, unknown>
): void {
  // TODO: Integrate with monitoring service
  // Example: Sentry.captureException(errorInfo.originalError, { extra: logData });

  // For now, just log that we would send to monitoring
  if (process.env.NODE_ENV === 'development') {
    console.debug('[Monitoring]', 'Would send error to monitoring service:', {
      errorInfo,
      logData,
    });
  }
}

// ============================================================================
// Error Handler Utilities
// ============================================================================

/**
 * Handle an API error with logging and optional callback.
 */
export function handleApiError(
  error: unknown,
  options: ErrorLogOptions & {
    onError?: (errorInfo: ErrorInfo) => void;
    showToUser?: boolean;
  } = {}
): ErrorInfo {
  // Extract error information
  const errorInfo = extractErrorInfo(error);

  // Log the error
  logError(error, {
    includeStack: options.includeStack,
    context: options.context,
    sendToMonitoring: options.sendToMonitoring,
  });

  // Call custom error handler if provided
  if (options.onError) {
    options.onError(errorInfo);
  }

  // Show error to user if requested (could trigger toast notification)
  if (options.showToUser && typeof window !== 'undefined') {
    // Integration point for toast notifications or user feedback
    showErrorToUser(errorInfo);
  }

  return errorInfo;
}

/**
 * Show error message to user.
 * This is a placeholder for integration with UI notification system.
 */
function showErrorToUser(errorInfo: ErrorInfo): void {
  // TODO: Integrate with toast notification system
  // Example: toast.error(errorInfo.userMessage);

  // For now, just log to console in development
  if (process.env.NODE_ENV === 'development') {
    console.info('[User Notification]', errorInfo.userMessage);
  }
}

/**
 * Check if an error should trigger automatic retry.
 */
export function shouldRetry(error: unknown, attemptNumber: number, maxAttempts: number = 3): boolean {
  if (attemptNumber >= maxAttempts) {
    return false;
  }

  const errorInfo = extractErrorInfo(error);
  return errorInfo.isRetryable;
}

/**
 * Calculate retry delay with exponential backoff.
 */
export function calculateRetryDelay(attemptNumber: number, baseDelay: number = 1000): number {
  const delay = baseDelay * Math.pow(2, attemptNumber - 1);
  // Add jitter to prevent thundering herd
  const jitter = Math.random() * delay * 0.1;
  return Math.min(delay + jitter, 30000); // Max 30 seconds
}

/**
 * Retry an async operation with exponential backoff.
 */
export async function retryWithBackoff<T>(
  operation: () => Promise<T>,
  options: {
    maxAttempts?: number;
    baseDelay?: number;
    onRetry?: (error: unknown, attempt: number) => void;
  } = {}
): Promise<T> {
  const maxAttempts = options.maxAttempts ?? 3;
  const baseDelay = options.baseDelay ?? 1000;

  let lastError: unknown;

  for (let attempt = 1; attempt <= maxAttempts; attempt++) {
    try {
      return await operation();
    } catch (error) {
      lastError = error;

      if (!shouldRetry(error, attempt, maxAttempts)) {
        throw error;
      }

      if (options.onRetry) {
        options.onRetry(error, attempt);
      }

      const delay = calculateRetryDelay(attempt, baseDelay);
      await new Promise(resolve => setTimeout(resolve, delay));
    }
  }

  throw lastError;
}

// ============================================================================
// Error Boundary Integration
// ============================================================================

/**
 * Format error information for ErrorBoundary component.
 */
export function formatErrorForBoundary(error: unknown): {
  message: string;
  details?: string;
  requestId?: string;
  retryable: boolean;
} {
  const errorInfo = extractErrorInfo(error);

  return {
    message: errorInfo.userMessage,
    details: errorInfo.details || errorInfo.message,
    requestId: errorInfo.requestId,
    retryable: errorInfo.isRetryable,
  };
}

/**
 * Check if error requires user re-authentication.
 */
export function requiresReauth(error: unknown): boolean {
  const errorInfo = extractErrorInfo(error);
  return errorInfo.category === ErrorCategory.AUTHENTICATION;
}

/**
 * Get recovery actions for an error.
 */
export function getRecoveryActions(error: unknown): string[] {
  const errorInfo = extractErrorInfo(error);

  switch (errorInfo.category) {
    case ErrorCategory.AUTHENTICATION:
      return ['Log in again', 'Contact support if problem persists'];

    case ErrorCategory.AUTHORIZATION:
      return ['Contact your administrator for access', 'Check your permissions'];

    case ErrorCategory.VALIDATION:
      return ['Review your input', 'Check required fields', 'Try again'];

    case ErrorCategory.NOT_FOUND:
      return ['Go back to previous page', 'Search for the item', 'Contact support'];

    case ErrorCategory.RATE_LIMIT:
      return ['Wait a few moments', 'Try again later'];

    case ErrorCategory.SERVER_ERROR:
      return ['Wait a moment and try again', 'Contact support if problem persists'];

    case ErrorCategory.NETWORK_ERROR:
      return ['Check your internet connection', 'Try again', 'Contact IT support'];

    case ErrorCategory.TIMEOUT:
      return ['Try again', 'Check your internet connection'];

    case ErrorCategory.UNKNOWN:
    default:
      return ['Try again', 'Refresh the page', 'Contact support if problem persists'];
  }
}

// ============================================================================
// Exports
// ============================================================================

export {
  ApiError,
  type ApiErrorBody,
  type StandardErrorResponse,
  type LegacyErrorResponse,
};
