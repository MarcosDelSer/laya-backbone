'use client';

import { useCallback } from 'react';
import type { ChildInfoData } from './EnrollmentFormWizard';

// ============================================================================
// Types
// ============================================================================

interface ChildInfoSectionProps {
  /** Current child info data */
  data: ChildInfoData;
  /** Callback when data changes */
  onChange: (data: Partial<ChildInfoData>) => void;
  /** Whether the form is disabled */
  disabled?: boolean;
  /** Validation errors to display */
  errors?: string[];
}

// ============================================================================
// Component
// ============================================================================

/**
 * Child information section for enrollment form wizard.
 * Collects basic identification information about the child including
 * name, date of birth, address, and languages spoken.
 */
export function ChildInfoSection({
  data,
  onChange,
  disabled = false,
  errors = [],
}: ChildInfoSectionProps) {
  // ---------------------------------------------------------------------------
  // Handlers
  // ---------------------------------------------------------------------------

  const handleInputChange = useCallback(
    (field: keyof ChildInfoData) =>
      (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        onChange({ [field]: e.target.value });
      },
    [onChange]
  );

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div className="space-y-6">
      {/* Section Header */}
      <div>
        <h3 className="text-lg font-medium text-gray-900">
          Child Identification
        </h3>
        <p className="mt-1 text-sm text-gray-500">
          Please provide the child&apos;s basic identification information.
          Fields marked with an asterisk (*) are required.
        </p>
      </div>

      {/* Validation Errors */}
      {errors.length > 0 && (
        <div className="rounded-lg bg-red-50 p-4">
          <div className="flex items-start space-x-3">
            <svg
              className="h-5 w-5 text-red-400 flex-shrink-0 mt-0.5"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fillRule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                clipRule="evenodd"
              />
            </svg>
            <div>
              <h4 className="text-sm font-medium text-red-800">
                Please correct the following:
              </h4>
              <ul className="mt-1 text-sm text-red-700 list-disc list-inside">
                {errors.map((error, index) => (
                  <li key={index}>{error}</li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      )}

      {/* Name Fields */}
      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
        {/* First Name */}
        <div>
          <label
            htmlFor="childFirstName"
            className="block text-sm font-medium text-gray-700"
          >
            First Name <span className="text-red-500">*</span>
          </label>
          <input
            type="text"
            id="childFirstName"
            name="childFirstName"
            value={data.childFirstName}
            onChange={handleInputChange('childFirstName')}
            disabled={disabled}
            required
            placeholder="Enter child's first name"
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
        </div>

        {/* Last Name */}
        <div>
          <label
            htmlFor="childLastName"
            className="block text-sm font-medium text-gray-700"
          >
            Last Name <span className="text-red-500">*</span>
          </label>
          <input
            type="text"
            id="childLastName"
            name="childLastName"
            value={data.childLastName}
            onChange={handleInputChange('childLastName')}
            disabled={disabled}
            required
            placeholder="Enter child's last name"
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
        </div>
      </div>

      {/* Date of Birth */}
      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
          <label
            htmlFor="childDateOfBirth"
            className="block text-sm font-medium text-gray-700"
          >
            Date of Birth <span className="text-red-500">*</span>
          </label>
          <input
            type="date"
            id="childDateOfBirth"
            name="childDateOfBirth"
            value={data.childDateOfBirth}
            onChange={handleInputChange('childDateOfBirth')}
            disabled={disabled}
            required
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
          <p className="mt-1 text-xs text-gray-500">
            Child must be within the accepted age range for enrollment.
          </p>
        </div>

        {/* Admission Date */}
        <div>
          <label
            htmlFor="admissionDate"
            className="block text-sm font-medium text-gray-700"
          >
            Expected Admission Date
          </label>
          <input
            type="date"
            id="admissionDate"
            name="admissionDate"
            value={data.admissionDate || ''}
            onChange={handleInputChange('admissionDate')}
            disabled={disabled}
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
          <p className="mt-1 text-xs text-gray-500">
            When the child is expected to start attending.
          </p>
        </div>
      </div>

      {/* Address Section */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Address Information
        </h4>

        {/* Street Address */}
        <div className="mb-4">
          <label
            htmlFor="childAddress"
            className="block text-sm font-medium text-gray-700"
          >
            Street Address
          </label>
          <input
            type="text"
            id="childAddress"
            name="childAddress"
            value={data.childAddress || ''}
            onChange={handleInputChange('childAddress')}
            disabled={disabled}
            placeholder="123 Main Street, Apt 4B"
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
        </div>

        {/* City and Postal Code */}
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
          <div>
            <label
              htmlFor="childCity"
              className="block text-sm font-medium text-gray-700"
            >
              City
            </label>
            <input
              type="text"
              id="childCity"
              name="childCity"
              value={data.childCity || ''}
              onChange={handleInputChange('childCity')}
              disabled={disabled}
              placeholder="Montreal"
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
          </div>

          <div>
            <label
              htmlFor="childPostalCode"
              className="block text-sm font-medium text-gray-700"
            >
              Postal Code
            </label>
            <input
              type="text"
              id="childPostalCode"
              name="childPostalCode"
              value={data.childPostalCode || ''}
              onChange={handleInputChange('childPostalCode')}
              disabled={disabled}
              placeholder="H1A 2B3"
              maxLength={7}
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
            <p className="mt-1 text-xs text-gray-500">
              Canadian postal code format (e.g., H1A 2B3)
            </p>
          </div>
        </div>
      </div>

      {/* Languages Section */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Language Information
        </h4>

        <div>
          <label
            htmlFor="languagesSpoken"
            className="block text-sm font-medium text-gray-700"
          >
            Languages Spoken at Home
          </label>
          <input
            type="text"
            id="languagesSpoken"
            name="languagesSpoken"
            value={data.languagesSpoken || ''}
            onChange={handleInputChange('languagesSpoken')}
            disabled={disabled}
            placeholder="French, English"
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
          <p className="mt-1 text-xs text-gray-500">
            List all languages spoken at home, separated by commas.
          </p>
        </div>
      </div>

      {/* Notes Section */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Additional Notes
        </h4>

        <div>
          <label
            htmlFor="notes"
            className="block text-sm font-medium text-gray-700"
          >
            Notes or Comments
          </label>
          <textarea
            id="notes"
            name="notes"
            rows={4}
            value={data.notes || ''}
            onChange={handleInputChange('notes')}
            disabled={disabled}
            placeholder="Any additional information about the child that staff should know..."
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
          <p className="mt-1 text-xs text-gray-500">
            Include any relevant information such as nicknames, special
            circumstances, or anything else we should know.
          </p>
        </div>
      </div>

      {/* Info Notice */}
      <div className="rounded-lg bg-blue-50 p-4">
        <div className="flex">
          <div className="flex-shrink-0">
            <svg
              className="h-5 w-5 text-blue-400"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fillRule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                clipRule="evenodd"
              />
            </svg>
          </div>
          <div className="ml-3">
            <p className="text-sm text-blue-700">
              This information will be used to identify your child and will be
              included in the official enrollment record. Please ensure all
              information is accurate and up-to-date.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

// ============================================================================
// Exports
// ============================================================================

export type { ChildInfoSectionProps };
