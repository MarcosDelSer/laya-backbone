# API Error Handler

Centralized error handling utilities for the LAYA Parent Portal.

## Overview

The API error handler provides comprehensive error handling capabilities including:

- **Error Classification**: Automatically categorize errors (authentication, validation, network, etc.)
- **Error Severity**: Determine severity levels (low, medium, high, critical)
- **User-Friendly Messages**: Convert technical errors into readable messages for users
- **Request Tracking**: Extract and track request IDs and correlation IDs for debugging
- **Retry Logic**: Automatic retry with exponential backoff for transient failures
- **Error Boundary Integration**: Seamless integration with React ErrorBoundary component
- **Structured Logging**: Log errors with full context for debugging and monitoring

## Quick Start

### Basic Error Handling

```typescript
import { handleApiError } from '@/lib/api/errorHandler';

try {
  await someApiCall();
} catch (error) {
  const errorInfo = handleApiError(error, {
    context: { userId: user.id, action: 'fetchData' },
    showToUser: true,
  });

  // errorInfo contains structured error information
  console.log(errorInfo.userMessage); // User-friendly message
  console.log(errorInfo.requestId); // Request ID for support
}
```

### With Custom Error Callback

```typescript
import { handleApiError } from '@/lib/api/errorHandler';

try {
  await someApiCall();
} catch (error) {
  handleApiError(error, {
    onError: (errorInfo) => {
      // Custom error handling logic
      if (errorInfo.category === ErrorCategory.AUTHENTICATION) {
        router.push('/login');
      }
    },
  });
}
```

## Error Response Formats

The error handler supports two response formats:

### Standard Format (New)

From the AI service error middleware:

```json
{
  "error": {
    "type": "validation_error",
    "message": "Invalid input data",
    "request_id": "req-123",
    "correlation_id": "corr-456",
    "details": "Field 'email' is required"
  }
}
```

### Legacy Format

For backwards compatibility:

```json
{
  "detail": "Resource not found",
  "statusCode": 404
}
```

## Error Categories

Errors are automatically classified into categories:

| Category | Description | HTTP Status Codes |
|----------|-------------|-------------------|
| `AUTHENTICATION` | Session expired or invalid credentials | 401 |
| `AUTHORIZATION` | Insufficient permissions | 403 |
| `VALIDATION` | Invalid input data | 422 |
| `NOT_FOUND` | Resource not found | 404 |
| `RATE_LIMIT` | Too many requests | 429 |
| `SERVER_ERROR` | Server-side error | 500, 502, 503, 504 |
| `NETWORK_ERROR` | Connection failure | - |
| `TIMEOUT` | Request timeout | - |
| `UNKNOWN` | Unclassified error | Other codes |

## Error Severity Levels

Each error is assigned a severity level:

| Severity | Description | Log Level |
|----------|-------------|-----------|
| `CRITICAL` | Server errors requiring immediate attention | error |
| `HIGH` | Authentication, authorization, network errors | error |
| `MEDIUM` | Validation, timeout, rate limit errors | warn |
| `LOW` | Not found and other minor errors | info |

## API Reference

### Error Information Extraction

#### `extractErrorInfo(error: unknown): ErrorInfo`

Extract comprehensive error information from any error.

```typescript
import { extractErrorInfo } from '@/lib/api/errorHandler';

const errorInfo = extractErrorInfo(error);
console.log(errorInfo.category); // ErrorCategory
console.log(errorInfo.severity); // ErrorSeverity
console.log(errorInfo.userMessage); // User-friendly message
console.log(errorInfo.requestId); // Request ID (if available)
console.log(errorInfo.isRetryable); // Whether error can be retried
```

**Returns**: `ErrorInfo` object containing:
- `category`: Error category (ErrorCategory enum)
- `severity`: Error severity (ErrorSeverity enum)
- `message`: Technical error message
- `userMessage`: User-friendly error message
- `requestId`: Request ID for tracing (optional)
- `correlationId`: Correlation ID for distributed tracing (optional)
- `statusCode`: HTTP status code
- `isRetryable`: Whether the error can be retried
- `details`: Additional error details (optional)
- `originalError`: The original error object

### Error Classification

#### `classifyError(error: unknown): ErrorCategory`

Classify an error into a category.

```typescript
import { classifyError, ErrorCategory } from '@/lib/api/errorHandler';

const category = classifyError(error);
if (category === ErrorCategory.AUTHENTICATION) {
  // Redirect to login
}
```

#### `determineErrorSeverity(category: ErrorCategory, statusCode: number): ErrorSeverity`

Determine error severity based on category and status code.

### Error Logging

#### `logError(error: unknown, options?: ErrorLogOptions): void`

Log an error with structured information.

```typescript
import { logError } from '@/lib/api/errorHandler';

logError(error, {
  includeStack: true,
  context: { userId: '123', action: 'submit' },
  sendToMonitoring: true,
});
```

**Options**:
- `includeStack`: Include stack trace in logs (default: false)
- `context`: Additional context to include in logs
- `sendToMonitoring`: Send error to external monitoring service (default: false)

### Error Handling

#### `handleApiError(error: unknown, options?): ErrorInfo`

Handle an API error with logging and optional callback.

```typescript
import { handleApiError } from '@/lib/api/errorHandler';

const errorInfo = handleApiError(error, {
  includeStack: true,
  context: { userId: '123' },
  sendToMonitoring: true,
  onError: (info) => {
    // Custom error handling
  },
  showToUser: true,
});
```

**Options**: All `ErrorLogOptions` plus:
- `onError`: Callback function called with ErrorInfo
- `showToUser`: Show error message to user (triggers UI notification)

### Retry Logic

#### `shouldRetry(error: unknown, attemptNumber: number, maxAttempts?: number): boolean`

Check if an error should trigger automatic retry.

```typescript
import { shouldRetry } from '@/lib/api/errorHandler';

if (shouldRetry(error, attempt, 3)) {
  // Retry the operation
}
```

#### `calculateRetryDelay(attemptNumber: number, baseDelay?: number): number`

Calculate retry delay with exponential backoff and jitter.

```typescript
import { calculateRetryDelay } from '@/lib/api/errorHandler';

const delay = calculateRetryDelay(attempt, 1000);
await sleep(delay);
```

#### `retryWithBackoff<T>(operation: () => Promise<T>, options?): Promise<T>`

Retry an async operation with exponential backoff.

```typescript
import { retryWithBackoff } from '@/lib/api/errorHandler';

const data = await retryWithBackoff(
  () => fetchData(),
  {
    maxAttempts: 3,
    baseDelay: 1000,
    onRetry: (error, attempt) => {
      console.log(`Retry attempt ${attempt}`);
    },
  }
);
```

**Options**:
- `maxAttempts`: Maximum number of retry attempts (default: 3)
- `baseDelay`: Base delay in milliseconds (default: 1000)
- `onRetry`: Callback called before each retry

### Error Boundary Integration

#### `formatErrorForBoundary(error: unknown)`

Format error information for the ErrorBoundary component.

```typescript
import { formatErrorForBoundary } from '@/lib/api/errorHandler';

const formatted = formatErrorForBoundary(error);
// Returns: { message, details, requestId, retryable }
```

#### `requiresReauth(error: unknown): boolean`

Check if error requires user re-authentication.

```typescript
import { requiresReauth } from '@/lib/api/errorHandler';

if (requiresReauth(error)) {
  router.push('/login');
}
```

#### `getRecoveryActions(error: unknown): string[]`

Get suggested recovery actions for an error.

```typescript
import { getRecoveryActions } from '@/lib/api/errorHandler';

const actions = getRecoveryActions(error);
// Returns: ['Try again', 'Refresh the page', 'Contact support']
```

## Usage Examples

### Example 1: API Call with Error Handling

```typescript
import { handleApiError, requiresReauth } from '@/lib/api/errorHandler';
import { getActivities } from '@/lib/ai-client';

async function loadActivities() {
  try {
    const activities = await getActivities();
    return activities;
  } catch (error) {
    const errorInfo = handleApiError(error, {
      context: { component: 'ActivityList' },
      showToUser: true,
    });

    // Redirect to login if authentication failed
    if (requiresReauth(error)) {
      router.push('/login');
    }

    // Return empty array as fallback
    return { items: [], total: 0 };
  }
}
```

### Example 2: Retry Failed Requests

```typescript
import { retryWithBackoff } from '@/lib/api/errorHandler';
import { getChildAnalytics } from '@/lib/ai-client';

async function loadAnalytics(childId: string) {
  return retryWithBackoff(
    () => getChildAnalytics(childId),
    {
      maxAttempts: 3,
      baseDelay: 1000,
      onRetry: (error, attempt) => {
        console.log(`Retrying analytics load (attempt ${attempt})...`);
      },
    }
  );
}
```

### Example 3: Form Submission with Validation

```typescript
import {
  handleApiError,
  ErrorCategory,
  extractErrorInfo
} from '@/lib/api/errorHandler';

async function submitForm(data: FormData) {
  try {
    const result = await api.post('/submit', data);
    return { success: true, data: result };
  } catch (error) {
    const errorInfo = handleApiError(error, {
      context: { form: 'userProfile' },
    });

    // Show validation errors inline
    if (errorInfo.category === ErrorCategory.VALIDATION) {
      return {
        success: false,
        validationErrors: errorInfo.details
      };
    }

    // Show general error
    return {
      success: false,
      error: errorInfo.userMessage
    };
  }
}
```

### Example 4: Error Boundary with Recovery Actions

```typescript
import {
  formatErrorForBoundary,
  getRecoveryActions
} from '@/lib/api/errorHandler';

function ErrorFallback({ error, resetErrorBoundary }) {
  const formatted = formatErrorForBoundary(error);
  const actions = getRecoveryActions(error);

  return (
    <div>
      <h2>Something went wrong</h2>
      <p>{formatted.message}</p>
      {formatted.requestId && (
        <p>Request ID: {formatted.requestId}</p>
      )}
      <div>
        <h3>What you can do:</h3>
        <ul>
          {actions.map((action, i) => (
            <li key={i}>{action}</li>
          ))}
        </ul>
      </div>
      {formatted.retryable && (
        <button onClick={resetErrorBoundary}>Try Again</button>
      )}
    </div>
  );
}
```

### Example 5: Monitoring Integration

```typescript
import { logError } from '@/lib/api/errorHandler';

try {
  await criticalOperation();
} catch (error) {
  // Log with full context and send to monitoring service
  logError(error, {
    includeStack: true,
    sendToMonitoring: true,
    context: {
      userId: user.id,
      operation: 'criticalOperation',
      environment: process.env.NODE_ENV,
      timestamp: new Date().toISOString(),
    },
  });

  throw error; // Re-throw for upstream handling
}
```

## Integration with Existing Code

The error handler is designed to work seamlessly with existing LAYA components:

### With ApiClient

The error handler automatically works with `ApiError` thrown by the base `ApiClient`:

```typescript
import { aiServiceClient } from '@/lib/api';
import { handleApiError } from '@/lib/api/errorHandler';

try {
  const data = await aiServiceClient.get('/some/endpoint');
} catch (error) {
  handleApiError(error); // Automatically extracts all ApiError information
}
```

### With AI Client Functions

```typescript
import { getActivities } from '@/lib/ai-client';
import { handleApiError, retryWithBackoff } from '@/lib/api/errorHandler';

const activities = await retryWithBackoff(
  () => getActivities({ limit: 10 }),
  { maxAttempts: 3 }
);
```

### With ErrorBoundary Component

```typescript
import { ErrorBoundary } from '@/components/ErrorBoundary';
import { formatErrorForBoundary } from '@/lib/api/errorHandler';

<ErrorBoundary
  fallback={(error, reset) => {
    const info = formatErrorForBoundary(error);
    return <ErrorUI {...info} onRetry={reset} />;
  }}
>
  <YourComponent />
</ErrorBoundary>
```

## Best Practices

1. **Always log errors with context**: Include relevant information like user ID, action, component name
2. **Use retry logic for transient failures**: Network errors and timeouts should be retried
3. **Show user-friendly messages**: Never expose technical error details to users
4. **Track request IDs**: Always log request IDs for debugging and support
5. **Handle authentication errors**: Redirect to login when session expires
6. **Provide recovery actions**: Give users clear next steps when errors occur
7. **Monitor critical errors**: Send high-severity errors to monitoring service
8. **Test error scenarios**: Write tests for error handling paths

## Testing

The error handler includes comprehensive tests covering:

- Error classification
- Severity determination
- Response parsing (both standard and legacy formats)
- User-friendly message generation
- Error information extraction
- Logging functionality
- Retry logic with exponential backoff
- Error boundary integration

Run tests with:

```bash
npm test errorHandler.test.ts
```

## Type Reference

See the source code for complete TypeScript type definitions:

- `ErrorCategory` - Error classification categories
- `ErrorSeverity` - Error severity levels
- `ErrorInfo` - Comprehensive error information
- `StandardErrorResponse` - Standard error response format
- `LegacyErrorResponse` - Legacy error response format
- `ErrorLogOptions` - Error logging options
