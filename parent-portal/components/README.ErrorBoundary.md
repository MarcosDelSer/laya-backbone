# ErrorBoundary Component

A React Error Boundary component that catches JavaScript errors anywhere in the child component tree, logs those errors, and displays a fallback UI.

## Features

- ✅ Catches React errors using Error Boundary pattern
- ✅ Displays user-friendly error messages
- ✅ Provides retry functionality
- ✅ Logs errors with detailed information
- ✅ Integrates with LAYA error handling system
- ✅ Supports custom fallback UI
- ✅ Development mode shows detailed error information
- ✅ Includes `useErrorBoundary` hook for programmatic error handling

## Basic Usage

### Wrap your app or specific components

```tsx
import { ErrorBoundary } from '@/components/ErrorBoundary';

function App() {
  return (
    <ErrorBoundary>
      <YourApp />
    </ErrorBoundary>
  );
}
```

### With custom error handler

```tsx
<ErrorBoundary
  onError={(error, errorInfo) => {
    // Send to logging service
    console.error('Error caught:', error, errorInfo);
  }}
>
  <YourApp />
</ErrorBoundary>
```

### With custom fallback UI

```tsx
<ErrorBoundary
  fallback={(error, errorInfo, reset) => (
    <div>
      <h1>Custom Error UI</h1>
      <p>{error.message}</p>
      <button onClick={reset}>Try Again</button>
    </div>
  )}
>
  <YourApp />
</ErrorBoundary>
```

### Show error details in development

```tsx
<ErrorBoundary showDetails={true}>
  <YourApp />
</ErrorBoundary>
```

## Using the useErrorBoundary Hook

The `useErrorBoundary` hook allows you to programmatically throw errors that will be caught by the nearest ErrorBoundary.

```tsx
import { useErrorBoundary } from '@/components/ErrorBoundary';

function MyComponent() {
  const throwError = useErrorBoundary();

  const handleClick = async () => {
    try {
      await riskyOperation();
    } catch (error) {
      // This will be caught by the nearest ErrorBoundary
      throwError(error as Error);
    }
  };

  return <button onClick={handleClick}>Do Risky Operation</button>;
}
```

## Integration with Next.js App Router

For Next.js 13+ with app router, use the ErrorBoundary component in your layout:

```tsx
// app/layout.tsx
import { ErrorBoundary } from '@/components/ErrorBoundary';

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body>
        <ErrorBoundary>
          {children}
        </ErrorBoundary>
      </body>
    </html>
  );
}
```

For page-specific error handling:

```tsx
// app/some-page/page.tsx
import { ErrorBoundary } from '@/components/ErrorBoundary';

export default function SomePage() {
  return (
    <ErrorBoundary>
      <PageContent />
    </ErrorBoundary>
  );
}
```

## Nested Error Boundaries

You can nest multiple error boundaries to handle errors at different levels:

```tsx
<ErrorBoundary fallback={(error) => <AppLevelError error={error} />}>
  <App>
    <ErrorBoundary fallback={(error) => <PageLevelError error={error} />}>
      <Page />
    </ErrorBoundary>
  </App>
</ErrorBoundary>
```

## Error Logging

The ErrorBoundary automatically logs errors with the following information:

- Error message and stack trace
- Component stack (which components were rendering)
- Timestamp
- User agent
- Current URL
- Unique error ID for tracking

In production, errors can be sent to a logging service by modifying the `logErrorToService` method.

## Props

### ErrorBoundaryProps

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `children` | `ReactNode` | Required | Child components to render |
| `fallback` | `(error: Error, errorInfo: ErrorInfo \| null, reset: () => void) => ReactNode` | `undefined` | Custom fallback UI |
| `onError` | `(error: Error, errorInfo: ErrorInfo) => void` | `undefined` | Callback when error is caught |
| `showDetails` | `boolean` | `false` (production), `true` (development) | Show detailed error information |

## Testing

Run the test suite:

```bash
npm test ErrorBoundary.test.tsx
```

The test suite includes:

- ✅ Basic error catching functionality
- ✅ Default fallback UI rendering
- ✅ Custom fallback UI support
- ✅ Error details visibility in dev/prod modes
- ✅ Error reset functionality
- ✅ Error callback invocation
- ✅ useErrorBoundary hook functionality
- ✅ Nested error boundaries
- ✅ Integration tests

## Best Practices

1. **Place at appropriate levels**: Don't wrap every component; place boundaries at logical application sections
2. **Provide context-appropriate fallbacks**: Show different fallback UIs based on where the error occurred
3. **Always provide recovery options**: Include "Try Again" or "Go Home" buttons
4. **Log errors appropriately**: Send errors to monitoring services in production
5. **Don't catch all errors**: Some errors (like authentication failures) might be better handled differently
6. **Test error boundaries**: Write tests to ensure they catch and handle errors correctly

## Limitations

Error Boundaries do NOT catch errors in:

- Event handlers (use try-catch instead)
- Asynchronous code (setTimeout, requestAnimationFrame callbacks)
- Server-side rendering
- Errors thrown in the error boundary itself

For these cases, use traditional try-catch blocks or the `useErrorBoundary` hook.

## Example: Complete Integration

```tsx
// app/layout.tsx
'use client';

import { ErrorBoundary } from '@/components/ErrorBoundary';
import { Navigation } from '@/components/Navigation';

export default function RootLayout({ children }) {
  return (
    <html lang="en">
      <body>
        <ErrorBoundary
          onError={(error, errorInfo) => {
            // Log to external service in production
            if (process.env.NODE_ENV === 'production') {
              // sendToLoggingService({ error, errorInfo });
            }
          }}
        >
          <Navigation />
          <main>
            {children}
          </main>
        </ErrorBoundary>
      </body>
    </html>
  );
}
```

## Related Components

- `error.tsx` - Next.js built-in error handling for app router
- API error handling - See `lib/api.ts` for API-level error handling
- `ApiError` class - Structured error handling for API requests
