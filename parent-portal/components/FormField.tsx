'use client';

/**
 * Accessible Form Field Component
 *
 * A reusable form field component that implements WCAG 2.1 AA standards
 * for form labels and error messages.
 *
 * Features:
 * - Proper label association (htmlFor/id)
 * - Required field indicators
 * - Error message association (aria-describedby)
 * - Help text support
 * - Validation state (aria-invalid)
 * - Screen reader announcements (role="alert")
 *
 * WCAG Compliance:
 * - 1.3.1 Info and Relationships (Level A)
 * - 3.3.1 Error Identification (Level A)
 * - 3.3.2 Labels or Instructions (Level A)
 * - 4.1.2 Name, Role, Value (Level A)
 */

import React from 'react';

export interface FormFieldProps
  extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'id'> {
  /** Unique identifier for the input field */
  id: string;
  /** Visible label text */
  label: string;
  /** Error message to display (when invalid) */
  error?: string;
  /** Help text to provide guidance */
  helpText?: string;
  /** Whether the field is required */
  required?: boolean;
  /** Custom className for the container */
  containerClassName?: string;
  /** Custom className for the label */
  labelClassName?: string;
  /** Custom className for the input */
  inputClassName?: string;
}

export function FormField({
  id,
  label,
  error,
  helpText,
  required,
  containerClassName = '',
  labelClassName = '',
  inputClassName = '',
  className,
  ...inputProps
}: FormFieldProps) {
  const errorId = `${id}-error`;
  const helpId = `${id}-help`;

  // Build aria-describedby value
  const describedBy = [error ? errorId : null, helpText ? helpId : null]
    .filter(Boolean)
    .join(' ');

  // Combine default and custom input classes
  const baseInputClasses =
    'mt-1 block w-full rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-0';
  const errorClasses = error
    ? 'border-red-500 text-red-900 placeholder-red-300 focus:ring-red-500 focus:border-red-500'
    : 'border-gray-300 focus:ring-primary focus:border-primary';
  const combinedInputClasses = `${baseInputClasses} ${errorClasses} ${
    inputClassName || className || ''
  }`;

  return (
    <div className={containerClassName}>
      {/* Label */}
      <label
        htmlFor={id}
        className={`block text-sm font-medium text-gray-700 ${labelClassName}`}
      >
        {label}
        {required && (
          <span className="text-red-600 ml-1" aria-label="required">
            *
          </span>
        )}
      </label>

      {/* Input */}
      <input
        id={id}
        name={id}
        required={required}
        aria-required={required}
        aria-invalid={!!error}
        aria-describedby={describedBy || undefined}
        className={combinedInputClasses}
        {...inputProps}
      />

      {/* Error Message */}
      {error && (
        <p
          id={errorId}
          className="mt-1 text-sm text-red-600 flex items-start"
          role="alert"
          aria-live="polite"
        >
          <svg
            className="h-5 w-5 text-red-500 mr-1 flex-shrink-0"
            fill="currentColor"
            viewBox="0 0 20 20"
            aria-hidden="true"
          >
            <path
              fillRule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z"
              clipRule="evenodd"
            />
          </svg>
          <span>{error}</span>
        </p>
      )}

      {/* Help Text (only shown when no error) */}
      {helpText && !error && (
        <p id={helpId} className="mt-1 text-sm text-gray-500">
          {helpText}
        </p>
      )}
    </div>
  );
}

export default FormField;
