'use client';

import React, { Component, ErrorInfo, ReactNode } from 'react';

/**
 * Props for the ErrorBoundary component.
 */
interface ErrorBoundaryProps {
  /** Child components to render */
  children: ReactNode;
  /** Optional fallback UI to render when an error occurs */
  fallback?: (error: Error, errorInfo: ErrorInfo | null, reset: () => void) => ReactNode;
  /** Optional callback when an error is caught */
  onError?: (error: Error, errorInfo: ErrorInfo) => void;
  /** Optional flag to show detailed error info (defaults to false in production) */
  showDetails?: boolean;
}

/**
 * State for the ErrorBoundary component.
 */
interface ErrorBoundaryState {
  hasError: boolean;
  error: Error | null;
  errorInfo: ErrorInfo | null;
}

/**
 * ErrorBoundary component that catches JavaScript errors anywhere in the child
 * component tree, logs those errors, and displays a fallback UI.
 *
 * This component follows the React Error Boundary pattern and integrates with
 * the LAYA error handling system, including request ID tracking and logging.
 *
 * @example
 * ```tsx
 * <ErrorBoundary>
 *   <App />
 * </ErrorBoundary>
 * ```
 *
 * @example With custom fallback
 * ```tsx
 * <ErrorBoundary
 *   fallback={(error, errorInfo, reset) => (
 *     <CustomErrorUI error={error} onReset={reset} />
 *   )}
 * >
 *   <App />
 * </ErrorBoundary>
 * ```
 */
export class ErrorBoundary extends Component<ErrorBoundaryProps, ErrorBoundaryState> {
  constructor(props: ErrorBoundaryProps) {
    super(props);
    this.state = {
      hasError: false,
      error: null,
      errorInfo: null,
    };
  }

  /**
   * Static method called when an error is thrown in a child component.
   * Updates state to render fallback UI on the next render.
   */
  static getDerivedStateFromError(error: Error): Partial<ErrorBoundaryState> {
    return {
      hasError: true,
      error,
    };
  }

  /**
   * Lifecycle method called after an error is caught.
   * Used for logging and error reporting.
   */
  componentDidCatch(error: Error, errorInfo: ErrorInfo): void {
    // Store error info in state
    this.setState({ errorInfo });

    // Log error to console in development
    if (process.env.NODE_ENV === 'development') {
      console.error('ErrorBoundary caught an error:', error, errorInfo);
    }

    // Call optional error callback
    if (this.props.onError) {
      this.props.onError(error, errorInfo);
    }

    // In production, send error to logging service
    // This would typically send to a service like Sentry or custom logging endpoint
    this.logErrorToService(error, errorInfo);
  }

  /**
   * Log error to external logging service.
   * In production, this would send to an error tracking service.
   */
  private logErrorToService(error: Error, errorInfo: ErrorInfo): void {
    try {
      // Generate a client-side error ID for tracking
      const errorId = `client-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;

      // Prepare error payload
      const errorPayload = {
        errorId,
        timestamp: new Date().toISOString(),
        message: error.message,
        stack: error.stack,
        componentStack: errorInfo.componentStack,
        userAgent: typeof navigator !== 'undefined' ? navigator.userAgent : 'unknown',
        url: typeof window !== 'undefined' ? window.location.href : 'unknown',
      };

      // In production, send to logging service
      // For now, just log to console
      if (process.env.NODE_ENV === 'production') {
        // TODO: Send to logging service endpoint
        // Example: fetch('/api/log-error', { method: 'POST', body: JSON.stringify(errorPayload) })
        console.error('Error logged:', errorPayload);
      }
    } catch (loggingError) {
      // Silently fail if logging fails to avoid error loop
      console.error('Failed to log error:', loggingError);
    }
  }

  /**
   * Reset the error boundary state to retry rendering the children.
   */
  private resetErrorBoundary = (): void => {
    this.setState({
      hasError: false,
      error: null,
      errorInfo: null,
    });
  };

  render(): ReactNode {
    const { hasError, error, errorInfo } = this.state;
    const { children, fallback, showDetails } = this.props;

    if (hasError && error) {
      // Use custom fallback if provided
      if (fallback) {
        return fallback(error, errorInfo, this.resetErrorBoundary);
      }

      // Default fallback UI
      return (
        <DefaultErrorFallback
          error={error}
          errorInfo={errorInfo}
          onReset={this.resetErrorBoundary}
          showDetails={showDetails ?? process.env.NODE_ENV === 'development'}
        />
      );
    }

    return children;
  }
}

/**
 * Props for the default error fallback component.
 */
interface DefaultErrorFallbackProps {
  error: Error;
  errorInfo: ErrorInfo | null;
  onReset: () => void;
  showDetails: boolean;
}

/**
 * Default fallback UI displayed when an error is caught.
 */
function DefaultErrorFallback({
  error,
  errorInfo,
  onReset,
  showDetails,
}: DefaultErrorFallbackProps) {
  return (
    <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4 py-16">
      <div className="max-w-2xl w-full">
        <div className="bg-white rounded-lg shadow-lg p-8">
          {/* Error Icon */}
          <div className="flex justify-center mb-6">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
              <svg
                className="h-8 w-8 text-red-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
            </div>
          </div>

          {/* Error Message */}
          <div className="text-center mb-6">
            <h2 className="text-2xl font-bold text-gray-900 mb-2">
              Something went wrong
            </h2>
            <p className="text-gray-600">
              We apologize for the inconvenience. An unexpected error has occurred
              in the application.
            </p>
          </div>

          {/* Error Details (Development Only) */}
          {showDetails && (
            <div className="mb-6">
              <details className="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <summary className="cursor-pointer font-medium text-gray-900 mb-2">
                  Error Details
                </summary>
                <div className="space-y-4 mt-4">
                  <div>
                    <h4 className="text-sm font-semibold text-gray-700 mb-1">
                      Error Message:
                    </h4>
                    <p className="text-sm text-red-600 font-mono bg-red-50 p-2 rounded">
                      {error.message}
                    </p>
                  </div>
                  {error.stack && (
                    <div>
                      <h4 className="text-sm font-semibold text-gray-700 mb-1">
                        Stack Trace:
                      </h4>
                      <pre className="text-xs text-gray-700 font-mono bg-gray-100 p-3 rounded overflow-x-auto max-h-48 overflow-y-auto">
                        {error.stack}
                      </pre>
                    </div>
                  )}
                  {errorInfo?.componentStack && (
                    <div>
                      <h4 className="text-sm font-semibold text-gray-700 mb-1">
                        Component Stack:
                      </h4>
                      <pre className="text-xs text-gray-700 font-mono bg-gray-100 p-3 rounded overflow-x-auto max-h-48 overflow-y-auto">
                        {errorInfo.componentStack}
                      </pre>
                    </div>
                  )}
                </div>
              </details>
            </div>
          )}

          {/* Action Buttons */}
          <div className="flex flex-col sm:flex-row items-center justify-center gap-4">
            <button
              onClick={onReset}
              className="btn btn-primary w-full sm:w-auto"
            >
              <svg
                className="mr-2 h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                />
              </svg>
              Try Again
            </button>

            <a
              href="/"
              className="btn btn-outline w-full sm:w-auto"
            >
              <svg
                className="mr-2 h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                />
              </svg>
              Back to Dashboard
            </a>
          </div>

          {/* Help Text */}
          <div className="mt-6 pt-6 border-t border-gray-200">
            <p className="text-sm text-gray-500 text-center">
              If this problem persists, please contact support with the error
              information above.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

/**
 * Hook to programmatically trigger error boundary from function components.
 * Use this to throw errors that will be caught by the nearest ErrorBoundary.
 *
 * @example
 * ```tsx
 * function MyComponent() {
 *   const throwError = useErrorBoundary();
 *
 *   const handleClick = () => {
 *     try {
 *       // Some operation that might fail
 *     } catch (error) {
 *       throwError(error);
 *     }
 *   };
 *
 *   return <button onClick={handleClick}>Click me</button>;
 * }
 * ```
 */
export function useErrorBoundary(): (error: Error) => never {
  const [, setError] = React.useState();

  return React.useCallback((error: Error) => {
    setError(() => {
      throw error;
    });
    // TypeScript: Never returns
    throw error;
  }, []);
}
