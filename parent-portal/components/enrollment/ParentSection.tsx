'use client';

import { useCallback } from 'react';
import type { EnrollmentParent, ParentNumber } from '@/lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Parent data without id and formId for the wizard.
 */
export type ParentData = Omit<EnrollmentParent, 'id' | 'formId'>;

interface ParentSectionProps {
  /** Current parent data */
  data: ParentData | null;
  /** Callback when data changes */
  onChange: (data: ParentData | null) => void;
  /** Parent number (1 or 2) */
  parentNumber: ParentNumber;
  /** Whether this parent section is required */
  isRequired?: boolean;
  /** Whether the form is disabled */
  disabled?: boolean;
  /** Validation errors to display */
  errors?: string[];
}

// ============================================================================
// Default Data
// ============================================================================

const createDefaultParentData = (parentNumber: ParentNumber): ParentData => ({
  parentNumber,
  name: '',
  relationship: '',
  address: '',
  city: '',
  postalCode: '',
  homePhone: '',
  cellPhone: '',
  workPhone: '',
  email: '',
  employer: '',
  workAddress: '',
  workHours: '',
  isPrimaryContact: parentNumber === '1',
});

// ============================================================================
// Component
// ============================================================================

/**
 * Parent/Guardian information section for enrollment form wizard.
 * Collects contact and employment information for a parent or guardian.
 * Reusable for both Parent 1 (required) and Parent 2 (optional).
 */
export function ParentSection({
  data,
  onChange,
  parentNumber,
  isRequired = false,
  disabled = false,
  errors = [],
}: ParentSectionProps) {
  // ---------------------------------------------------------------------------
  // Handlers
  // ---------------------------------------------------------------------------

  /**
   * Initialize parent data if null (when user starts filling the form).
   */
  const getOrCreateData = useCallback((): ParentData => {
    return data ?? createDefaultParentData(parentNumber);
  }, [data, parentNumber]);

  /**
   * Handle input change for text fields.
   */
  const handleInputChange = useCallback(
    (field: keyof ParentData) =>
      (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
        const currentData = getOrCreateData();
        onChange({ ...currentData, [field]: e.target.value });
      },
    [onChange, getOrCreateData]
  );

  /**
   * Handle checkbox change for isPrimaryContact.
   */
  const handleCheckboxChange = useCallback(
    (field: keyof ParentData) =>
      (e: React.ChangeEvent<HTMLInputElement>) => {
        const currentData = getOrCreateData();
        onChange({ ...currentData, [field]: e.target.checked });
      },
    [onChange, getOrCreateData]
  );

  /**
   * Handle clearing the parent data (for optional Parent 2).
   */
  const handleClear = useCallback(() => {
    onChange(null);
  }, [onChange]);

  // Use current data or default for rendering
  const currentData = data ?? createDefaultParentData(parentNumber);

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div className="space-y-6">
      {/* Section Header */}
      <div className="flex items-start justify-between">
        <div>
          <h3 className="text-lg font-medium text-gray-900">
            Parent/Guardian {parentNumber}
            {isRequired && <span className="text-red-500 ml-1">*</span>}
            {!isRequired && (
              <span className="ml-2 text-sm font-normal text-gray-500">
                (Optional)
              </span>
            )}
          </h3>
          <p className="mt-1 text-sm text-gray-500">
            {parentNumber === '1'
              ? "Please provide the primary parent or guardian's contact information."
              : "Optionally provide a second parent or guardian's contact information."}
          </p>
        </div>

        {/* Clear button for optional Parent 2 */}
        {!isRequired && data !== null && (
          <button
            type="button"
            onClick={handleClear}
            disabled={disabled}
            className="text-sm text-gray-500 hover:text-gray-700 underline"
          >
            Clear this section
          </button>
        )}
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

      {/* Name and Relationship Fields */}
      <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
        {/* Full Name */}
        <div>
          <label
            htmlFor={`parent${parentNumber}Name`}
            className="block text-sm font-medium text-gray-700"
          >
            Full Name {isRequired && <span className="text-red-500">*</span>}
          </label>
          <input
            type="text"
            id={`parent${parentNumber}Name`}
            name={`parent${parentNumber}Name`}
            value={currentData.name}
            onChange={handleInputChange('name')}
            disabled={disabled}
            required={isRequired}
            placeholder="Enter full name"
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
        </div>

        {/* Relationship */}
        <div>
          <label
            htmlFor={`parent${parentNumber}Relationship`}
            className="block text-sm font-medium text-gray-700"
          >
            Relationship {isRequired && <span className="text-red-500">*</span>}
          </label>
          <select
            id={`parent${parentNumber}Relationship`}
            name={`parent${parentNumber}Relationship`}
            value={currentData.relationship}
            onChange={handleInputChange('relationship')}
            disabled={disabled}
            required={isRequired}
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          >
            <option value="">Select relationship</option>
            <option value="Mother">Mother</option>
            <option value="Father">Father</option>
            <option value="Stepmother">Stepmother</option>
            <option value="Stepfather">Stepfather</option>
            <option value="Guardian">Legal Guardian</option>
            <option value="Grandparent">Grandparent</option>
            <option value="Foster Parent">Foster Parent</option>
            <option value="Other">Other</option>
          </select>
        </div>
      </div>

      {/* Contact Information Section */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Contact Information
        </h4>

        {/* Phone Numbers */}
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
          {/* Home Phone */}
          <div>
            <label
              htmlFor={`parent${parentNumber}HomePhone`}
              className="block text-sm font-medium text-gray-700"
            >
              Home Phone
            </label>
            <input
              type="tel"
              id={`parent${parentNumber}HomePhone`}
              name={`parent${parentNumber}HomePhone`}
              value={currentData.homePhone || ''}
              onChange={handleInputChange('homePhone')}
              disabled={disabled}
              placeholder="(514) 555-0100"
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
          </div>

          {/* Cell Phone */}
          <div>
            <label
              htmlFor={`parent${parentNumber}CellPhone`}
              className="block text-sm font-medium text-gray-700"
            >
              Cell Phone
            </label>
            <input
              type="tel"
              id={`parent${parentNumber}CellPhone`}
              name={`parent${parentNumber}CellPhone`}
              value={currentData.cellPhone || ''}
              onChange={handleInputChange('cellPhone')}
              disabled={disabled}
              placeholder="(514) 555-0101"
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
          </div>

          {/* Work Phone */}
          <div>
            <label
              htmlFor={`parent${parentNumber}WorkPhone`}
              className="block text-sm font-medium text-gray-700"
            >
              Work Phone
            </label>
            <input
              type="tel"
              id={`parent${parentNumber}WorkPhone`}
              name={`parent${parentNumber}WorkPhone`}
              value={currentData.workPhone || ''}
              onChange={handleInputChange('workPhone')}
              disabled={disabled}
              placeholder="(514) 555-0102"
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
          </div>
        </div>

        {isRequired && (
          <p className="mt-2 text-xs text-gray-500">
            At least one phone number (home or cell) is required.
          </p>
        )}

        {/* Email */}
        <div className="mt-4">
          <label
            htmlFor={`parent${parentNumber}Email`}
            className="block text-sm font-medium text-gray-700"
          >
            Email Address
          </label>
          <input
            type="email"
            id={`parent${parentNumber}Email`}
            name={`parent${parentNumber}Email`}
            value={currentData.email || ''}
            onChange={handleInputChange('email')}
            disabled={disabled}
            placeholder="parent@example.com"
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
          <p className="mt-1 text-xs text-gray-500">
            Email will be used for important notifications and communications.
          </p>
        </div>
      </div>

      {/* Address Section */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Home Address
        </h4>

        {/* Street Address */}
        <div className="mb-4">
          <label
            htmlFor={`parent${parentNumber}Address`}
            className="block text-sm font-medium text-gray-700"
          >
            Street Address
          </label>
          <input
            type="text"
            id={`parent${parentNumber}Address`}
            name={`parent${parentNumber}Address`}
            value={currentData.address || ''}
            onChange={handleInputChange('address')}
            disabled={disabled}
            placeholder="123 Main Street, Apt 4B"
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
        </div>

        {/* City and Postal Code */}
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
          <div>
            <label
              htmlFor={`parent${parentNumber}City`}
              className="block text-sm font-medium text-gray-700"
            >
              City
            </label>
            <input
              type="text"
              id={`parent${parentNumber}City`}
              name={`parent${parentNumber}City`}
              value={currentData.city || ''}
              onChange={handleInputChange('city')}
              disabled={disabled}
              placeholder="Montreal"
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
          </div>

          <div>
            <label
              htmlFor={`parent${parentNumber}PostalCode`}
              className="block text-sm font-medium text-gray-700"
            >
              Postal Code
            </label>
            <input
              type="text"
              id={`parent${parentNumber}PostalCode`}
              name={`parent${parentNumber}PostalCode`}
              value={currentData.postalCode || ''}
              onChange={handleInputChange('postalCode')}
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

      {/* Employment Section */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Employment Information
        </h4>

        {/* Employer */}
        <div className="mb-4">
          <label
            htmlFor={`parent${parentNumber}Employer`}
            className="block text-sm font-medium text-gray-700"
          >
            Employer Name
          </label>
          <input
            type="text"
            id={`parent${parentNumber}Employer`}
            name={`parent${parentNumber}Employer`}
            value={currentData.employer || ''}
            onChange={handleInputChange('employer')}
            disabled={disabled}
            placeholder="Company name"
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
        </div>

        {/* Work Address and Hours */}
        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
          <div>
            <label
              htmlFor={`parent${parentNumber}WorkAddress`}
              className="block text-sm font-medium text-gray-700"
            >
              Work Address
            </label>
            <input
              type="text"
              id={`parent${parentNumber}WorkAddress`}
              name={`parent${parentNumber}WorkAddress`}
              value={currentData.workAddress || ''}
              onChange={handleInputChange('workAddress')}
              disabled={disabled}
              placeholder="Work location address"
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
          </div>

          <div>
            <label
              htmlFor={`parent${parentNumber}WorkHours`}
              className="block text-sm font-medium text-gray-700"
            >
              Work Hours
            </label>
            <input
              type="text"
              id={`parent${parentNumber}WorkHours`}
              name={`parent${parentNumber}WorkHours`}
              value={currentData.workHours || ''}
              onChange={handleInputChange('workHours')}
              disabled={disabled}
              placeholder="e.g., 9:00 AM - 5:00 PM"
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
            <p className="mt-1 text-xs text-gray-500">
              Helps us know the best times to reach you.
            </p>
          </div>
        </div>
      </div>

      {/* Primary Contact Toggle */}
      <div className="border-t border-gray-200 pt-6">
        <div className="flex items-start space-x-3">
          <input
            type="checkbox"
            id={`parent${parentNumber}IsPrimaryContact`}
            name={`parent${parentNumber}IsPrimaryContact`}
            checked={currentData.isPrimaryContact}
            onChange={handleCheckboxChange('isPrimaryContact')}
            disabled={disabled}
            className="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
          />
          <div>
            <label
              htmlFor={`parent${parentNumber}IsPrimaryContact`}
              className="text-sm font-medium text-gray-700"
            >
              Primary Contact
            </label>
            <p className="text-sm text-gray-500">
              Check this box if this parent/guardian should be the primary contact
              for all communications and emergencies.
            </p>
          </div>
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
              {parentNumber === '1'
                ? 'This information will be used for daily communications and emergencies. Please ensure contact numbers are current and accessible during daycare hours.'
                : 'Having a second contact ensures we can always reach someone regarding your child. This information is optional but recommended.'}
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

export type { ParentSectionProps };
