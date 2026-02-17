/**
 * Tests for API error handler utilities.
 */

import { ApiError } from '../api';
import {
  ErrorCategory,
  ErrorSeverity,
  classifyError,
  determineErrorSeverity,
  extractErrorMessage,
  extractRequestId,
  extractCorrelationId,
  extractErrorDetails,
  getUserFriendlyMessage,
  extractErrorInfo,
  logError,
  handleApiError,
  shouldRetry,
  calculateRetryDelay,
  retryWithBackoff,
  formatErrorForBoundary,
  requiresReauth,
  getRecoveryActions,
  type StandardErrorResponse,
  type LegacyErrorResponse,
} from './errorHandler';

// ============================================================================
// Test Data
// ============================================================================

const mockStandardErrorResponse: StandardErrorResponse = {
  error: {
    type: 'validation_error',
    message: 'Invalid input data',
    request_id: 'req-123',
    correlation_id: 'corr-456',
    details: 'Field "email" is required',
  },
};

const mockLegacyErrorResponse: LegacyErrorResponse = {
  detail: 'Resource not found',
  statusCode: 404,
};

// ============================================================================
// Error Classification Tests
// ============================================================================

describe('classifyError', () => {
  it('should classify network errors', () => {
    const error = new ApiError(
      'Network error',
      0,
      '',
      undefined,
      { isNetworkError: true }
    );
    expect(classifyError(error)).toBe(ErrorCategory.NETWORK_ERROR);
  });

  it('should classify timeout errors', () => {
    const error = new ApiError(
      'Timeout',
      0,
      '',
      undefined,
      { isTimeout: true }
    );
    expect(classifyError(error)).toBe(ErrorCategory.TIMEOUT);
  });

  it('should classify 401 as authentication error', () => {
    const error = new ApiError('Unauthorized', 401);
    expect(classifyError(error)).toBe(ErrorCategory.AUTHENTICATION);
  });

  it('should classify 403 as authorization error', () => {
    const error = new ApiError('Forbidden', 403);
    expect(classifyError(error)).toBe(ErrorCategory.AUTHORIZATION);
  });

  it('should classify 404 as not found error', () => {
    const error = new ApiError('Not Found', 404);
    expect(classifyError(error)).toBe(ErrorCategory.NOT_FOUND);
  });

  it('should classify 422 as validation error', () => {
    const error = new ApiError('Unprocessable Entity', 422);
    expect(classifyError(error)).toBe(ErrorCategory.VALIDATION);
  });

  it('should classify 429 as rate limit error', () => {
    const error = new ApiError('Too Many Requests', 429);
    expect(classifyError(error)).toBe(ErrorCategory.RATE_LIMIT);
  });

  it('should classify 500 as server error', () => {
    const error = new ApiError('Internal Server Error', 500);
    expect(classifyError(error)).toBe(ErrorCategory.SERVER_ERROR);
  });

  it('should classify 502 as server error', () => {
    const error = new ApiError('Bad Gateway', 502);
    expect(classifyError(error)).toBe(ErrorCategory.SERVER_ERROR);
  });

  it('should classify 503 as server error', () => {
    const error = new ApiError('Service Unavailable', 503);
    expect(classifyError(error)).toBe(ErrorCategory.SERVER_ERROR);
  });

  it('should classify 504 as server error', () => {
    const error = new ApiError('Gateway Timeout', 504);
    expect(classifyError(error)).toBe(ErrorCategory.SERVER_ERROR);
  });

  it('should classify unknown status codes as unknown', () => {
    const error = new ApiError('Unknown Error', 418);
    expect(classifyError(error)).toBe(ErrorCategory.UNKNOWN);
  });

  it('should classify non-ApiError errors as unknown', () => {
    const error = new Error('Generic error');
    expect(classifyError(error)).toBe(ErrorCategory.UNKNOWN);
  });
});

describe('determineErrorSeverity', () => {
  it('should return HIGH for authentication errors', () => {
    expect(determineErrorSeverity(ErrorCategory.AUTHENTICATION, 401)).toBe(
      ErrorSeverity.HIGH
    );
  });

  it('should return CRITICAL for server errors', () => {
    expect(determineErrorSeverity(ErrorCategory.SERVER_ERROR, 500)).toBe(
      ErrorSeverity.CRITICAL
    );
  });

  it('should return HIGH for authorization errors', () => {
    expect(determineErrorSeverity(ErrorCategory.AUTHORIZATION, 403)).toBe(
      ErrorSeverity.HIGH
    );
  });

  it('should return HIGH for network errors', () => {
    expect(determineErrorSeverity(ErrorCategory.NETWORK_ERROR, 0)).toBe(
      ErrorSeverity.HIGH
    );
  });

  it('should return MEDIUM for timeout errors', () => {
    expect(determineErrorSeverity(ErrorCategory.TIMEOUT, 0)).toBe(
      ErrorSeverity.MEDIUM
    );
  });

  it('should return MEDIUM for rate limit errors', () => {
    expect(determineErrorSeverity(ErrorCategory.RATE_LIMIT, 429)).toBe(
      ErrorSeverity.MEDIUM
    );
  });

  it('should return MEDIUM for validation errors', () => {
    expect(determineErrorSeverity(ErrorCategory.VALIDATION, 422)).toBe(
      ErrorSeverity.MEDIUM
    );
  });

  it('should return LOW for not found errors', () => {
    expect(determineErrorSeverity(ErrorCategory.NOT_FOUND, 404)).toBe(
      ErrorSeverity.LOW
    );
  });

  it('should return LOW for unknown errors', () => {
    expect(determineErrorSeverity(ErrorCategory.UNKNOWN, 0)).toBe(
      ErrorSeverity.LOW
    );
  });
});

// ============================================================================
// Error Response Parsing Tests
// ============================================================================

describe('extractErrorMessage', () => {
  it('should extract message from standard error response', () => {
    expect(extractErrorMessage(mockStandardErrorResponse)).toBe(
      'Invalid input data'
    );
  });

  it('should extract message from legacy error response', () => {
    expect(extractErrorMessage(mockLegacyErrorResponse)).toBe(
      'Resource not found'
    );
  });

  it('should return default message for unknown format', () => {
    expect(extractErrorMessage({ foo: 'bar' })).toBe(
      'An unexpected error occurred'
    );
  });

  it('should return default message for null', () => {
    expect(extractErrorMessage(null)).toBe('An unexpected error occurred');
  });
});

describe('extractRequestId', () => {
  it('should extract request ID from standard error response', () => {
    expect(extractRequestId(mockStandardErrorResponse)).toBe('req-123');
  });

  it('should return undefined for legacy error response', () => {
    expect(extractRequestId(mockLegacyErrorResponse)).toBeUndefined();
  });

  it('should return undefined for unknown format', () => {
    expect(extractRequestId({ foo: 'bar' })).toBeUndefined();
  });
});

describe('extractCorrelationId', () => {
  it('should extract correlation ID from standard error response', () => {
    expect(extractCorrelationId(mockStandardErrorResponse)).toBe('corr-456');
  });

  it('should return undefined for legacy error response', () => {
    expect(extractCorrelationId(mockLegacyErrorResponse)).toBeUndefined();
  });

  it('should return undefined for unknown format', () => {
    expect(extractCorrelationId({ foo: 'bar' })).toBeUndefined();
  });
});

describe('extractErrorDetails', () => {
  it('should extract details from standard error response', () => {
    expect(extractErrorDetails(mockStandardErrorResponse)).toBe(
      'Field "email" is required'
    );
  });

  it('should return undefined for legacy error response', () => {
    expect(extractErrorDetails(mockLegacyErrorResponse)).toBeUndefined();
  });

  it('should return undefined for unknown format', () => {
    expect(extractErrorDetails({ foo: 'bar' })).toBeUndefined();
  });
});

// ============================================================================
// User-Friendly Message Tests
// ============================================================================

describe('getUserFriendlyMessage', () => {
  it('should return authentication message', () => {
    const message = getUserFriendlyMessage(ErrorCategory.AUTHENTICATION);
    expect(message).toContain('session has expired');
  });

  it('should return authorization message', () => {
    const message = getUserFriendlyMessage(ErrorCategory.AUTHORIZATION);
    expect(message).toContain('do not have permission');
  });

  it('should return validation message with original message', () => {
    const message = getUserFriendlyMessage(
      ErrorCategory.VALIDATION,
      'Email is invalid'
    );
    expect(message).toBe('Email is invalid');
  });

  it('should return default validation message without original', () => {
    const message = getUserFriendlyMessage(ErrorCategory.VALIDATION);
    expect(message).toContain('invalid');
  });

  it('should return not found message', () => {
    const message = getUserFriendlyMessage(ErrorCategory.NOT_FOUND);
    expect(message).toContain('could not be found');
  });

  it('should return rate limit message', () => {
    const message = getUserFriendlyMessage(ErrorCategory.RATE_LIMIT);
    expect(message).toContain('too many requests');
  });

  it('should return server error message', () => {
    const message = getUserFriendlyMessage(ErrorCategory.SERVER_ERROR);
    expect(message).toContain('problem on our end');
  });

  it('should return network error message', () => {
    const message = getUserFriendlyMessage(ErrorCategory.NETWORK_ERROR);
    expect(message).toContain('Unable to connect');
  });

  it('should return timeout message', () => {
    const message = getUserFriendlyMessage(ErrorCategory.TIMEOUT);
    expect(message).toContain('took too long');
  });

  it('should return default message for unknown errors', () => {
    const message = getUserFriendlyMessage(ErrorCategory.UNKNOWN);
    expect(message).toContain('unexpected error');
  });
});

// ============================================================================
// Error Information Extraction Tests
// ============================================================================

describe('extractErrorInfo', () => {
  it('should extract info from ApiError with standard response', () => {
    const error = new ApiError(
      'Validation failed',
      422,
      'Unprocessable Entity',
      mockStandardErrorResponse
    );

    const info = extractErrorInfo(error);

    expect(info.category).toBe(ErrorCategory.VALIDATION);
    expect(info.severity).toBe(ErrorSeverity.MEDIUM);
    expect(info.message).toBe('Invalid input data');
    expect(info.requestId).toBe('req-123');
    expect(info.correlationId).toBe('corr-456');
    expect(info.statusCode).toBe(422);
    expect(info.isRetryable).toBe(false);
    expect(info.details).toBe('Field "email" is required');
  });

  it('should extract info from ApiError with legacy response', () => {
    const error = new ApiError(
      'Not found',
      404,
      'Not Found',
      mockLegacyErrorResponse
    );

    const info = extractErrorInfo(error);

    expect(info.category).toBe(ErrorCategory.NOT_FOUND);
    expect(info.severity).toBe(ErrorSeverity.LOW);
    expect(info.message).toBe('Resource not found');
    expect(info.requestId).toBeUndefined();
    expect(info.statusCode).toBe(404);
  });

  it('should extract info from network error', () => {
    const error = new ApiError(
      'Network error',
      0,
      '',
      undefined,
      { isNetworkError: true }
    );

    const info = extractErrorInfo(error);

    expect(info.category).toBe(ErrorCategory.NETWORK_ERROR);
    expect(info.severity).toBe(ErrorSeverity.HIGH);
    expect(info.isRetryable).toBe(true);
  });

  it('should extract info from generic Error', () => {
    const error = new Error('Something went wrong');

    const info = extractErrorInfo(error);

    expect(info.category).toBe(ErrorCategory.UNKNOWN);
    expect(info.message).toBe('Something went wrong');
    expect(info.statusCode).toBe(0);
  });

  it('should handle non-Error objects', () => {
    const info = extractErrorInfo('string error');

    expect(info.category).toBe(ErrorCategory.UNKNOWN);
    expect(info.message).toBe('string error');
  });
});

// ============================================================================
// Error Logging Tests
// ============================================================================

describe('logError', () => {
  let consoleErrorSpy: jest.SpyInstance;
  let consoleWarnSpy: jest.SpyInstance;
  let consoleInfoSpy: jest.SpyInstance;

  beforeEach(() => {
    consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();
    consoleWarnSpy = jest.spyOn(console, 'warn').mockImplementation();
    consoleInfoSpy = jest.spyOn(console, 'info').mockImplementation();
  });

  afterEach(() => {
    consoleErrorSpy.mockRestore();
    consoleWarnSpy.mockRestore();
    consoleInfoSpy.mockRestore();
  });

  it('should log critical errors with error level', () => {
    const error = new ApiError('Server error', 500);
    logError(error);

    expect(consoleErrorSpy).toHaveBeenCalledWith(
      '[API Error]',
      expect.objectContaining({
        category: ErrorCategory.SERVER_ERROR,
        severity: ErrorSeverity.CRITICAL,
      })
    );
  });

  it('should log medium severity errors with warn level', () => {
    const error = new ApiError('Validation error', 422);
    logError(error);

    expect(consoleWarnSpy).toHaveBeenCalledWith(
      '[API Error]',
      expect.objectContaining({
        category: ErrorCategory.VALIDATION,
        severity: ErrorSeverity.MEDIUM,
      })
    );
  });

  it('should log low severity errors with info level', () => {
    const error = new ApiError('Not found', 404);
    logError(error);

    expect(consoleInfoSpy).toHaveBeenCalledWith(
      '[API Error]',
      expect.objectContaining({
        category: ErrorCategory.NOT_FOUND,
        severity: ErrorSeverity.LOW,
      })
    );
  });

  it('should include request and correlation IDs', () => {
    const error = new ApiError(
      'Error',
      422,
      '',
      mockStandardErrorResponse
    );
    logError(error);

    expect(consoleWarnSpy).toHaveBeenCalledWith(
      '[API Error]',
      expect.objectContaining({
        requestId: 'req-123',
        correlationId: 'corr-456',
      })
    );
  });

  it('should include additional context', () => {
    const error = new ApiError('Error', 500);
    logError(error, {
      context: { userId: 'user-123', action: 'create' },
    });

    expect(consoleErrorSpy).toHaveBeenCalledWith(
      '[API Error]',
      expect.objectContaining({
        userId: 'user-123',
        action: 'create',
      })
    );
  });

  it('should include stack trace when requested', () => {
    const error = new Error('Test error');
    logError(error, { includeStack: true });

    expect(consoleInfoSpy).toHaveBeenCalledWith(
      '[API Error]',
      expect.objectContaining({
        stack: expect.any(String),
      })
    );
  });
});

// ============================================================================
// Error Handler Tests
// ============================================================================

describe('handleApiError', () => {
  let consoleErrorSpy: jest.SpyInstance;

  beforeEach(() => {
    consoleErrorSpy = jest.spyOn(console, 'error').mockImplementation();
  });

  afterEach(() => {
    consoleErrorSpy.mockRestore();
  });

  it('should extract and return error info', () => {
    const error = new ApiError('Not found', 404);
    const info = handleApiError(error);

    expect(info.category).toBe(ErrorCategory.NOT_FOUND);
    expect(info.statusCode).toBe(404);
  });

  it('should call custom error handler', () => {
    const error = new ApiError('Error', 500);
    const onError = jest.fn();

    handleApiError(error, { onError });

    expect(onError).toHaveBeenCalledWith(
      expect.objectContaining({
        category: ErrorCategory.SERVER_ERROR,
      })
    );
  });

  it('should log error with context', () => {
    const error = new ApiError('Error', 422);

    handleApiError(error, {
      context: { field: 'email' },
    });

    expect(consoleErrorSpy).toHaveBeenCalledWith(
      '[API Error]',
      expect.objectContaining({
        field: 'email',
      })
    );
  });
});

// ============================================================================
// Retry Logic Tests
// ============================================================================

describe('shouldRetry', () => {
  it('should return false when max attempts reached', () => {
    const error = new ApiError('Network error', 0, '', undefined, {
      isNetworkError: true,
    });
    expect(shouldRetry(error, 3, 3)).toBe(false);
  });

  it('should return true for retryable errors within limit', () => {
    const error = new ApiError('Timeout', 0, '', undefined, {
      isTimeout: true,
    });
    expect(shouldRetry(error, 1, 3)).toBe(true);
  });

  it('should return false for non-retryable errors', () => {
    const error = new ApiError('Not found', 404);
    expect(shouldRetry(error, 1, 3)).toBe(false);
  });
});

describe('calculateRetryDelay', () => {
  it('should calculate exponential backoff', () => {
    const delay1 = calculateRetryDelay(1, 1000);
    const delay2 = calculateRetryDelay(2, 1000);
    const delay3 = calculateRetryDelay(3, 1000);

    expect(delay1).toBeGreaterThanOrEqual(1000);
    expect(delay1).toBeLessThanOrEqual(1100); // 1000 + 10% jitter

    expect(delay2).toBeGreaterThanOrEqual(2000);
    expect(delay2).toBeLessThanOrEqual(2200);

    expect(delay3).toBeGreaterThanOrEqual(4000);
    expect(delay3).toBeLessThanOrEqual(4400);
  });

  it('should cap at 30 seconds', () => {
    const delay = calculateRetryDelay(10, 1000);
    expect(delay).toBeLessThanOrEqual(30000);
  });
});

describe('retryWithBackoff', () => {
  jest.setTimeout(10000);

  it('should succeed on first attempt', async () => {
    const operation = jest.fn().mockResolvedValue('success');

    const result = await retryWithBackoff(operation);

    expect(result).toBe('success');
    expect(operation).toHaveBeenCalledTimes(1);
  });

  it('should retry on retryable error', async () => {
    const operation = jest
      .fn()
      .mockRejectedValueOnce(
        new ApiError('Timeout', 0, '', undefined, { isTimeout: true })
      )
      .mockResolvedValueOnce('success');

    const result = await retryWithBackoff(operation, {
      baseDelay: 10,
    });

    expect(result).toBe('success');
    expect(operation).toHaveBeenCalledTimes(2);
  });

  it('should not retry on non-retryable error', async () => {
    const error = new ApiError('Not found', 404);
    const operation = jest.fn().mockRejectedValue(error);

    await expect(
      retryWithBackoff(operation, { baseDelay: 10 })
    ).rejects.toThrow(error);
    expect(operation).toHaveBeenCalledTimes(1);
  });

  it('should throw after max attempts', async () => {
    const error = new ApiError('Timeout', 0, '', undefined, {
      isTimeout: true,
    });
    const operation = jest.fn().mockRejectedValue(error);

    await expect(
      retryWithBackoff(operation, {
        maxAttempts: 2,
        baseDelay: 10,
      })
    ).rejects.toThrow(error);
    expect(operation).toHaveBeenCalledTimes(2);
  });

  it('should call onRetry callback', async () => {
    const onRetry = jest.fn();
    const operation = jest
      .fn()
      .mockRejectedValueOnce(
        new ApiError('Timeout', 0, '', undefined, { isTimeout: true })
      )
      .mockResolvedValueOnce('success');

    await retryWithBackoff(operation, {
      baseDelay: 10,
      onRetry,
    });

    expect(onRetry).toHaveBeenCalledTimes(1);
    expect(onRetry).toHaveBeenCalledWith(expect.any(ApiError), 1);
  });
});

// ============================================================================
// Error Boundary Integration Tests
// ============================================================================

describe('formatErrorForBoundary', () => {
  it('should format ApiError for error boundary', () => {
    const error = new ApiError(
      'Validation error',
      422,
      '',
      mockStandardErrorResponse
    );

    const formatted = formatErrorForBoundary(error);

    expect(formatted.message).toBeTruthy();
    expect(formatted.details).toBe('Field "email" is required');
    expect(formatted.requestId).toBe('req-123');
    expect(formatted.retryable).toBe(false);
  });

  it('should format generic Error for error boundary', () => {
    const error = new Error('Something went wrong');

    const formatted = formatErrorForBoundary(error);

    expect(formatted.message).toBeTruthy();
    expect(formatted.retryable).toBe(false);
  });
});

describe('requiresReauth', () => {
  it('should return true for authentication errors', () => {
    const error = new ApiError('Unauthorized', 401);
    expect(requiresReauth(error)).toBe(true);
  });

  it('should return false for other errors', () => {
    const error = new ApiError('Not found', 404);
    expect(requiresReauth(error)).toBe(false);
  });
});

describe('getRecoveryActions', () => {
  it('should return auth recovery actions for 401', () => {
    const error = new ApiError('Unauthorized', 401);
    const actions = getRecoveryActions(error);

    expect(actions).toContain('Log in again');
  });

  it('should return authorization recovery actions for 403', () => {
    const error = new ApiError('Forbidden', 403);
    const actions = getRecoveryActions(error);

    expect(actions).toContain('Contact your administrator for access');
  });

  it('should return validation recovery actions for 422', () => {
    const error = new ApiError('Validation error', 422);
    const actions = getRecoveryActions(error);

    expect(actions).toContain('Review your input');
  });

  it('should return not found recovery actions for 404', () => {
    const error = new ApiError('Not found', 404);
    const actions = getRecoveryActions(error);

    expect(actions).toContain('Go back to previous page');
  });

  it('should return rate limit recovery actions for 429', () => {
    const error = new ApiError('Too many requests', 429);
    const actions = getRecoveryActions(error);

    expect(actions).toContain('Wait a few moments');
  });

  it('should return server error recovery actions for 500', () => {
    const error = new ApiError('Server error', 500);
    const actions = getRecoveryActions(error);

    expect(actions).toContain('Wait a moment and try again');
  });

  it('should return network error recovery actions', () => {
    const error = new ApiError('Network error', 0, '', undefined, {
      isNetworkError: true,
    });
    const actions = getRecoveryActions(error);

    expect(actions).toContain('Check your internet connection');
  });

  it('should return timeout recovery actions', () => {
    const error = new ApiError('Timeout', 0, '', undefined, {
      isTimeout: true,
    });
    const actions = getRecoveryActions(error);

    expect(actions).toContain('Try again');
  });
});
