/**
 * Tests for ErrorBoundary component
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { ErrorBoundary, useErrorBoundary } from '@/components/ErrorBoundary';
import React from 'react';

// Component that throws an error
function ThrowError({ shouldThrow = false, error }: { shouldThrow?: boolean; error?: Error }) {
  if (shouldThrow) {
    throw error || new Error('Test error');
  }
  return <div>No error</div>;
}

// Component that uses the useErrorBoundary hook
function ComponentWithHook() {
  const throwError = useErrorBoundary();

  const handleClick = () => {
    throwError(new Error('Hook error'));
  };

  return <button onClick={handleClick}>Trigger Error</button>;
}

describe('ErrorBoundary Component', () => {
  // Suppress console.error during tests to keep output clean
  const originalError = console.error;
  beforeEach(() => {
    console.error = vi.fn();
  });

  afterEach(() => {
    console.error = originalError;
  });

  it('renders children when there is no error', () => {
    render(
      <ErrorBoundary>
        <div>Child component</div>
      </ErrorBoundary>
    );

    expect(screen.getByText('Child component')).toBeInTheDocument();
  });

  it('catches errors and displays default fallback UI', () => {
    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} error={new Error('Test error message')} />
      </ErrorBoundary>
    );

    expect(screen.getByText('Something went wrong')).toBeInTheDocument();
    expect(screen.getByText(/An unexpected error has occurred/)).toBeInTheDocument();
  });

  it('displays error message in development mode', () => {
    const originalEnv = process.env.NODE_ENV;
    process.env.NODE_ENV = 'development';

    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} error={new Error('Custom error message')} />
      </ErrorBoundary>
    );

    // Check for details element
    const details = screen.getByText('Error Details').closest('details');
    expect(details).toBeInTheDocument();

    process.env.NODE_ENV = originalEnv;
  });

  it('hides error details when showDetails is false', () => {
    render(
      <ErrorBoundary showDetails={false}>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    );

    expect(screen.queryByText('Error Details')).not.toBeInTheDocument();
  });

  it('shows error details when showDetails is true', () => {
    render(
      <ErrorBoundary showDetails={true}>
        <ThrowError shouldThrow={true} error={new Error('Detailed error')} />
      </ErrorBoundary>
    );

    expect(screen.getByText('Error Details')).toBeInTheDocument();
    expect(screen.getByText('Detailed error')).toBeInTheDocument();
  });

  it('calls onError callback when an error is caught', () => {
    const onError = vi.fn();

    render(
      <ErrorBoundary onError={onError}>
        <ThrowError shouldThrow={true} error={new Error('Callback test')} />
      </ErrorBoundary>
    );

    expect(onError).toHaveBeenCalledTimes(1);
    expect(onError).toHaveBeenCalledWith(
      expect.objectContaining({ message: 'Callback test' }),
      expect.objectContaining({ componentStack: expect.any(String) })
    );
  });

  it('resets error state when Try Again button is clicked', () => {
    let shouldThrow = true;

    const { rerender } = render(
      <ErrorBoundary>
        <ThrowError shouldThrow={shouldThrow} />
      </ErrorBoundary>
    );

    // Error should be displayed
    expect(screen.getByText('Something went wrong')).toBeInTheDocument();

    // Reset the error
    shouldThrow = false;

    // Click Try Again button
    const tryAgainButton = screen.getByRole('button', { name: /try again/i });
    fireEvent.click(tryAgainButton);

    // Re-render with shouldThrow = false
    rerender(
      <ErrorBoundary>
        <ThrowError shouldThrow={shouldThrow} />
      </ErrorBoundary>
    );

    // Should render children again
    expect(screen.getByText('No error')).toBeInTheDocument();
  });

  it('displays action buttons', () => {
    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    );

    expect(screen.getByRole('button', { name: /try again/i })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: /back to dashboard/i })).toBeInTheDocument();
  });

  it('Back to Dashboard link navigates to home', () => {
    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    );

    const backLink = screen.getByRole('link', { name: /back to dashboard/i });
    expect(backLink).toHaveAttribute('href', '/');
  });

  it('renders custom fallback when provided', () => {
    const customFallback = (error: Error, errorInfo: any, reset: () => void) => (
      <div>
        <h1>Custom Error UI</h1>
        <p>{error.message}</p>
        <button onClick={reset}>Custom Reset</button>
      </div>
    );

    render(
      <ErrorBoundary fallback={customFallback}>
        <ThrowError shouldThrow={true} error={new Error('Custom fallback test')} />
      </ErrorBoundary>
    );

    expect(screen.getByText('Custom Error UI')).toBeInTheDocument();
    expect(screen.getByText('Custom fallback test')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /custom reset/i })).toBeInTheDocument();
  });

  it('includes error icon in default fallback', () => {
    const { container } = render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    );

    const svg = container.querySelector('svg');
    expect(svg).toBeInTheDocument();
  });

  it('displays help text at the bottom', () => {
    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} />
      </ErrorBoundary>
    );

    expect(screen.getByText(/If this problem persists/)).toBeInTheDocument();
  });
});

describe('useErrorBoundary Hook', () => {
  const originalError = console.error;
  beforeEach(() => {
    console.error = vi.fn();
  });

  afterEach(() => {
    console.error = originalError;
  });

  it('throws error that is caught by ErrorBoundary', () => {
    render(
      <ErrorBoundary>
        <ComponentWithHook />
      </ErrorBoundary>
    );

    // Initially, no error
    expect(screen.getByRole('button', { name: /trigger error/i })).toBeInTheDocument();

    // Click button to trigger error
    const button = screen.getByRole('button', { name: /trigger error/i });
    fireEvent.click(button);

    // Error boundary should catch it
    expect(screen.getByText('Something went wrong')).toBeInTheDocument();
  });

  it('hook returns a function', () => {
    let throwErrorFn: any;

    function TestComponent() {
      throwErrorFn = useErrorBoundary();
      return <div>Test</div>;
    }

    render(
      <ErrorBoundary>
        <TestComponent />
      </ErrorBoundary>
    );

    expect(typeof throwErrorFn).toBe('function');
  });
});

describe('ErrorBoundary Integration', () => {
  const originalError = console.error;
  beforeEach(() => {
    console.error = vi.fn();
  });

  afterEach(() => {
    console.error = originalError;
  });

  it('works with nested ErrorBoundaries', () => {
    render(
      <ErrorBoundary fallback={() => <div>Outer Error</div>}>
        <div>
          <ErrorBoundary fallback={() => <div>Inner Error</div>}>
            <ThrowError shouldThrow={true} />
          </ErrorBoundary>
        </div>
      </ErrorBoundary>
    );

    // Inner error boundary should catch the error
    expect(screen.getByText('Inner Error')).toBeInTheDocument();
    expect(screen.queryByText('Outer Error')).not.toBeInTheDocument();
  });

  it('logs errors to console in development', () => {
    const originalEnv = process.env.NODE_ENV;
    process.env.NODE_ENV = 'development';
    const consoleErrorSpy = vi.spyOn(console, 'error');

    render(
      <ErrorBoundary>
        <ThrowError shouldThrow={true} error={new Error('Development error')} />
      </ErrorBoundary>
    );

    expect(consoleErrorSpy).toHaveBeenCalled();

    process.env.NODE_ENV = originalEnv;
  });
});
