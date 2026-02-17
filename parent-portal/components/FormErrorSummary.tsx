'use client';

/**
 * Form Error Summary Component
 *
 * Displays a summary of all form validation errors at the top of a form.
 * Automatically receives focus when errors appear, helping keyboard and
 * screen reader users identify issues quickly.
 *
 * Features:
 * - Automatic focus management
 * - Links to individual error fields
 * - Screen reader announcements (role="alert")
 * - Descriptive error count
 *
 * WCAG Compliance:
 * - 3.3.1 Error Identification (Level A)
 * - 3.3.3 Error Suggestion (Level AA)
 * - 2.4.3 Focus Order (Level A)
 *
 * Usage:
 * ```tsx
 * const errors = [
 *   { field: 'email', message: 'Email address is required' },
 *   { field: 'password', message: 'Password must be at least 8 characters' }
 * ];
 * <FormErrorSummary errors={errors} />
 * ```
 */

import { useEffect, useRef } from 'react';

export interface FormError {
  /** ID of the field with the error (used for linking) */
  field: string;
  /** Human-readable error message */
  message: string;
}

export interface FormErrorSummaryProps {
  /** Array of form errors to display */
  errors: FormError[];
  /** Optional title for the error summary */
  title?: string;
  /** Optional className for custom styling */
  className?: string;
}

export function FormErrorSummary({
  errors,
  title,
  className = '',
}: FormErrorSummaryProps) {
  const summaryRef = useRef<HTMLDivElement>(null);

  // Focus the error summary when errors appear
  useEffect(() => {
    if (errors.length > 0 && summaryRef.current) {
      summaryRef.current.focus();
      // Scroll to error summary
      summaryRef.current.scrollIntoView({
        behavior: 'smooth',
        block: 'start',
      });
    }
  }, [errors]);

  // Don't render if no errors
  if (errors.length === 0) return null;

  const errorCount = errors.length;
  const defaultTitle = `There ${errorCount === 1 ? 'is' : 'are'} ${errorCount} error${errorCount !== 1 ? 's' : ''} with your submission`;

  return (
    <div
      ref={summaryRef}
      tabIndex={-1}
      role="alert"
      aria-labelledby="error-summary-title"
      className={`mb-6 rounded-md bg-red-50 border-l-4 border-red-400 p-4 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 ${className}`}
    >
      <div className="flex">
        {/* Error Icon */}
        <div className="flex-shrink-0">
          <svg
            className="h-5 w-5 text-red-400"
            viewBox="0 0 20 20"
            fill="currentColor"
            aria-hidden="true"
          >
            <path
              fillRule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
              clipRule="evenodd"
            />
          </svg>
        </div>

        {/* Error Content */}
        <div className="ml-3 flex-1">
          <h3
            id="error-summary-title"
            className="text-sm font-medium text-red-800"
          >
            {title || defaultTitle}
          </h3>

          <div className="mt-2 text-sm text-red-700">
            <ul className="list-disc list-inside space-y-1">
              {errors.map((error, index) => (
                <li key={index}>
                  <a
                    href={`#${error.field}`}
                    className="font-medium underline hover:text-red-900 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded"
                    onClick={(e) => {
                      e.preventDefault();
                      const element = document.getElementById(error.field);
                      if (element) {
                        element.focus();
                        element.scrollIntoView({
                          behavior: 'smooth',
                          block: 'center',
                        });
                      }
                    }}
                  >
                    {error.message}
                  </a>
                </li>
              ))}
            </ul>
          </div>

          {/* Accessibility hint */}
          <p className="mt-3 text-xs text-red-600">
            Please correct the errors above and resubmit the form.
          </p>
        </div>
      </div>
    </div>
  );
}

export default FormErrorSummary;
