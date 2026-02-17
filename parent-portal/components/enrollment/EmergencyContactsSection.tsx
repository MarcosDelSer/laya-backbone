'use client';

import { useCallback } from 'react';
import type { EmergencyContact } from '@/lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Emergency contact data without id and formId for the wizard.
 */
export type EmergencyContactData = Omit<EmergencyContact, 'id' | 'formId'>;

interface EmergencyContactsSectionProps {
  /** Current list of emergency contacts */
  data: EmergencyContactData[];
  /** Callback when data changes */
  onChange: (data: EmergencyContactData[]) => void;
  /** Whether the form is disabled */
  disabled?: boolean;
  /** Validation errors to display */
  errors?: string[];
  /** Minimum number of contacts required */
  minContacts?: number;
  /** Maximum number of contacts allowed */
  maxContacts?: number;
}

// ============================================================================
// Default Data
// ============================================================================

const createDefaultContactData = (priority: number): EmergencyContactData => ({
  name: '',
  relationship: '',
  phone: '',
  alternatePhone: '',
  priority,
  notes: '',
});

// ============================================================================
// Constants
// ============================================================================

const RELATIONSHIP_OPTIONS = [
  'Grandparent',
  'Aunt',
  'Uncle',
  'Sibling',
  'Family Friend',
  'Neighbor',
  'Godparent',
  'Cousin',
  'Other Relative',
  'Other',
];

// ============================================================================
// Component
// ============================================================================

/**
 * Emergency contacts section for enrollment form wizard.
 * Manages a dynamic list of emergency contacts for the child.
 * Supports add/remove functionality with priority ordering.
 * Quebec regulations typically require at least 2 emergency contacts.
 */
export function EmergencyContactsSection({
  data,
  onChange,
  disabled = false,
  errors = [],
  minContacts = 2,
  maxContacts = 5,
}: EmergencyContactsSectionProps) {
  // ---------------------------------------------------------------------------
  // Handlers
  // ---------------------------------------------------------------------------

  /**
   * Handle adding a new emergency contact.
   */
  const handleAddContact = useCallback(() => {
    if (data.length >= maxContacts) return;
    const nextPriority = data.length + 1;
    onChange([...data, createDefaultContactData(nextPriority)]);
  }, [data, onChange, maxContacts]);

  /**
   * Handle removing an emergency contact by index.
   */
  const handleRemoveContact = useCallback(
    (index: number) => {
      const newData = data.filter((_, i) => i !== index);
      // Recalculate priorities
      const reorderedData = newData.map((contact, i) => ({
        ...contact,
        priority: i + 1,
      }));
      onChange(reorderedData);
    },
    [data, onChange]
  );

  /**
   * Handle input change for a specific contact.
   */
  const handleInputChange = useCallback(
    (index: number, field: keyof EmergencyContactData) =>
      (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
        const newData = [...data];
        newData[index] = { ...newData[index], [field]: e.target.value };
        onChange(newData);
      },
    [data, onChange]
  );

  /**
   * Handle moving a contact up in priority.
   */
  const handleMoveUp = useCallback(
    (index: number) => {
      if (index === 0) return;
      const newData = [...data];
      // Swap with previous item
      [newData[index - 1], newData[index]] = [newData[index], newData[index - 1]];
      // Update priorities
      const reorderedData = newData.map((contact, i) => ({
        ...contact,
        priority: i + 1,
      }));
      onChange(reorderedData);
    },
    [data, onChange]
  );

  /**
   * Handle moving a contact down in priority.
   */
  const handleMoveDown = useCallback(
    (index: number) => {
      if (index === data.length - 1) return;
      const newData = [...data];
      // Swap with next item
      [newData[index], newData[index + 1]] = [newData[index + 1], newData[index]];
      // Update priorities
      const reorderedData = newData.map((contact, i) => ({
        ...contact,
        priority: i + 1,
      }));
      onChange(reorderedData);
    },
    [data, onChange]
  );

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div className="space-y-6">
      {/* Section Header */}
      <div>
        <h3 className="text-lg font-medium text-gray-900">
          Emergency Contacts
        </h3>
        <p className="mt-1 text-sm text-gray-500">
          Provide emergency contact information for people who can be reached
          when parents/guardians are not available. At least {minContacts} emergency
          contacts are required.
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

      {/* Minimum Contacts Warning */}
      {data.length < minContacts && (
        <div className="rounded-lg bg-amber-50 p-4">
          <div className="flex">
            <div className="flex-shrink-0">
              <svg
                className="h-5 w-5 text-amber-400"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                  clipRule="evenodd"
                />
              </svg>
            </div>
            <div className="ml-3">
              <p className="text-sm text-amber-700">
                <strong>Required:</strong> Please add at least {minContacts} emergency
                contacts. You currently have {data.length} contact{data.length !== 1 ? 's' : ''}.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Contact List */}
      {data.length > 0 ? (
        <div className="space-y-6">
          {data.map((contact, index) => (
            <div
              key={index}
              className="border border-gray-200 rounded-lg p-6 bg-white"
            >
              {/* Contact Header */}
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center space-x-3">
                  <span className="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-medium text-sm">
                    {contact.priority}
                  </span>
                  <h4 className="text-base font-medium text-gray-900">
                    Emergency Contact {index + 1}
                    {index < minContacts && (
                      <span className="ml-2 text-red-500 text-sm">*</span>
                    )}
                  </h4>
                </div>

                <div className="flex items-center space-x-2">
                  {/* Move Up Button */}
                  <button
                    type="button"
                    onClick={() => handleMoveUp(index)}
                    disabled={disabled || index === 0}
                    className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30 disabled:cursor-not-allowed"
                    title="Move up"
                    aria-label="Move up"
                  >
                    <svg
                      className="h-5 w-5"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M5 15l7-7 7 7"
                      />
                    </svg>
                  </button>

                  {/* Move Down Button */}
                  <button
                    type="button"
                    onClick={() => handleMoveDown(index)}
                    disabled={disabled || index === data.length - 1}
                    className="p-1 text-gray-400 hover:text-gray-600 disabled:opacity-30 disabled:cursor-not-allowed"
                    title="Move down"
                    aria-label="Move down"
                  >
                    <svg
                      className="h-5 w-5"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M19 9l-7 7-7-7"
                      />
                    </svg>
                  </button>

                  {/* Remove Button - only show if we have more than minimum */}
                  {data.length > minContacts && (
                    <button
                      type="button"
                      onClick={() => handleRemoveContact(index)}
                      disabled={disabled}
                      className="p-1 text-red-400 hover:text-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
                      title="Remove"
                      aria-label="Remove emergency contact"
                    >
                      <svg
                        className="h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                        />
                      </svg>
                    </button>
                  )}
                </div>
              </div>

              {/* Contact Fields */}
              <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                {/* Full Name */}
                <div>
                  <label
                    htmlFor={`contact${index}Name`}
                    className="block text-sm font-medium text-gray-700"
                  >
                    Full Name <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id={`contact${index}Name`}
                    name={`contact${index}Name`}
                    value={contact.name}
                    onChange={handleInputChange(index, 'name')}
                    disabled={disabled}
                    required
                    placeholder="Enter full name"
                    className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                  />
                </div>

                {/* Relationship */}
                <div>
                  <label
                    htmlFor={`contact${index}Relationship`}
                    className="block text-sm font-medium text-gray-700"
                  >
                    Relationship <span className="text-red-500">*</span>
                  </label>
                  <select
                    id={`contact${index}Relationship`}
                    name={`contact${index}Relationship`}
                    value={contact.relationship}
                    onChange={handleInputChange(index, 'relationship')}
                    disabled={disabled}
                    required
                    className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                  >
                    <option value="">Select relationship</option>
                    {RELATIONSHIP_OPTIONS.map((option) => (
                      <option key={option} value={option}>
                        {option}
                      </option>
                    ))}
                  </select>
                </div>

                {/* Primary Phone Number */}
                <div>
                  <label
                    htmlFor={`contact${index}Phone`}
                    className="block text-sm font-medium text-gray-700"
                  >
                    Primary Phone <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="tel"
                    id={`contact${index}Phone`}
                    name={`contact${index}Phone`}
                    value={contact.phone}
                    onChange={handleInputChange(index, 'phone')}
                    disabled={disabled}
                    required
                    placeholder="(514) 555-0100"
                    className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                  />
                </div>

                {/* Alternate Phone Number */}
                <div>
                  <label
                    htmlFor={`contact${index}AlternatePhone`}
                    className="block text-sm font-medium text-gray-700"
                  >
                    Alternate Phone (Optional)
                  </label>
                  <input
                    type="tel"
                    id={`contact${index}AlternatePhone`}
                    name={`contact${index}AlternatePhone`}
                    value={contact.alternatePhone || ''}
                    onChange={handleInputChange(index, 'alternatePhone')}
                    disabled={disabled}
                    placeholder="(514) 555-0101"
                    className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                  />
                  <p className="mt-1 text-xs text-gray-500">
                    A backup number in case the primary is unreachable.
                  </p>
                </div>
              </div>

              {/* Notes */}
              <div className="mt-4">
                <label
                  htmlFor={`contact${index}Notes`}
                  className="block text-sm font-medium text-gray-700"
                >
                  Notes (Optional)
                </label>
                <textarea
                  id={`contact${index}Notes`}
                  name={`contact${index}Notes`}
                  rows={2}
                  value={contact.notes || ''}
                  onChange={handleInputChange(index, 'notes')}
                  disabled={disabled}
                  placeholder="Any additional information about this contact (e.g., best times to call, work schedule)..."
                  className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                />
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="text-center py-8 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300">
          <svg
            className="mx-auto h-12 w-12 text-gray-400"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192l-3.536 3.536M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-5 0a4 4 0 11-8 0 4 4 0 018 0z"
            />
          </svg>
          <p className="mt-2 text-sm text-gray-600">
            No emergency contacts added yet.
          </p>
          <p className="text-xs text-gray-500">
            At least {minContacts} emergency contacts are required.
          </p>
        </div>
      )}

      {/* Add Button */}
      {data.length < maxContacts && (
        <button
          type="button"
          onClick={handleAddContact}
          disabled={disabled}
          className="w-full flex items-center justify-center px-4 py-3 border-2 border-dashed border-gray-300 rounded-lg text-sm font-medium text-gray-600 hover:border-primary-500 hover:text-primary-600 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
        >
          <svg
            className="mr-2 h-5 w-5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 6v6m0 0v6m0-6h6m-6 0H6"
            />
          </svg>
          Add Emergency Contact
        </button>
      )}

      {data.length >= maxContacts && (
        <p className="text-sm text-gray-500 text-center">
          Maximum of {maxContacts} emergency contacts reached.
        </p>
      )}

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
              <strong>Emergency Contact Policy:</strong> Emergency contacts will
              be called in order of priority if parents/guardians cannot be reached.
              Please ensure phone numbers are accurate and that contacts are aware
              they have been listed.
            </p>
          </div>
        </div>
      </div>

      {/* Priority Notice */}
      <div className="rounded-lg bg-gray-50 p-4">
        <div className="flex">
          <div className="flex-shrink-0">
            <svg
              className="h-5 w-5 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
              />
            </svg>
          </div>
          <div className="ml-3">
            <p className="text-sm text-gray-600">
              <strong>Contact Order:</strong> Use the up/down arrows to arrange
              contacts in order of preference. The first contact will be called
              first in case of emergency.
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

export type { EmergencyContactsSectionProps };
