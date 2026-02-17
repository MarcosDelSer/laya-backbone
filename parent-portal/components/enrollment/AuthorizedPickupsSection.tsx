'use client';

import { useCallback } from 'react';
import type { AuthorizedPickup } from '@/lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Pickup data without id and formId for the wizard.
 */
export type PickupData = Omit<AuthorizedPickup, 'id' | 'formId'>;

interface AuthorizedPickupsSectionProps {
  /** Current list of authorized pickups */
  data: PickupData[];
  /** Callback when data changes */
  onChange: (data: PickupData[]) => void;
  /** Whether the form is disabled */
  disabled?: boolean;
  /** Validation errors to display */
  errors?: string[];
  /** Maximum number of pickups allowed */
  maxPickups?: number;
}

// ============================================================================
// Default Data
// ============================================================================

const createDefaultPickupData = (priority: number): PickupData => ({
  name: '',
  relationship: '',
  phone: '',
  photoPath: undefined,
  photoUrl: undefined,
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
  'Babysitter',
  'Nanny',
  'Other',
];

// ============================================================================
// Component
// ============================================================================

/**
 * Authorized pickups section for enrollment form wizard.
 * Manages a dynamic list of people authorized to pick up the child.
 * Supports add/remove functionality with priority ordering.
 */
export function AuthorizedPickupsSection({
  data,
  onChange,
  disabled = false,
  errors = [],
  maxPickups = 5,
}: AuthorizedPickupsSectionProps) {
  // ---------------------------------------------------------------------------
  // Handlers
  // ---------------------------------------------------------------------------

  /**
   * Handle adding a new pickup person.
   */
  const handleAddPickup = useCallback(() => {
    if (data.length >= maxPickups) return;
    const nextPriority = data.length + 1;
    onChange([...data, createDefaultPickupData(nextPriority)]);
  }, [data, onChange, maxPickups]);

  /**
   * Handle removing a pickup person by index.
   */
  const handleRemovePickup = useCallback(
    (index: number) => {
      const newData = data.filter((_, i) => i !== index);
      // Recalculate priorities
      const reorderedData = newData.map((pickup, i) => ({
        ...pickup,
        priority: i + 1,
      }));
      onChange(reorderedData);
    },
    [data, onChange]
  );

  /**
   * Handle input change for a specific pickup.
   */
  const handleInputChange = useCallback(
    (index: number, field: keyof PickupData) =>
      (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
        const newData = [...data];
        newData[index] = { ...newData[index], [field]: e.target.value };
        onChange(newData);
      },
    [data, onChange]
  );

  /**
   * Handle moving a pickup up in priority.
   */
  const handleMoveUp = useCallback(
    (index: number) => {
      if (index === 0) return;
      const newData = [...data];
      // Swap with previous item
      [newData[index - 1], newData[index]] = [newData[index], newData[index - 1]];
      // Update priorities
      const reorderedData = newData.map((pickup, i) => ({
        ...pickup,
        priority: i + 1,
      }));
      onChange(reorderedData);
    },
    [data, onChange]
  );

  /**
   * Handle moving a pickup down in priority.
   */
  const handleMoveDown = useCallback(
    (index: number) => {
      if (index === data.length - 1) return;
      const newData = [...data];
      // Swap with next item
      [newData[index], newData[index + 1]] = [newData[index + 1], newData[index]];
      // Update priorities
      const reorderedData = newData.map((pickup, i) => ({
        ...pickup,
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
          Authorized Pickup Persons
        </h3>
        <p className="mt-1 text-sm text-gray-500">
          Add people who are authorized to pick up your child from the daycare.
          Parents and guardians are automatically authorized.
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

      {/* Pickup List */}
      {data.length > 0 ? (
        <div className="space-y-6">
          {data.map((pickup, index) => (
            <div
              key={index}
              className="border border-gray-200 rounded-lg p-6 bg-white"
            >
              {/* Pickup Header */}
              <div className="flex items-center justify-between mb-4">
                <div className="flex items-center space-x-3">
                  <span className="flex items-center justify-center w-8 h-8 rounded-full bg-primary-100 text-primary-700 font-medium text-sm">
                    {pickup.priority}
                  </span>
                  <h4 className="text-base font-medium text-gray-900">
                    Authorized Person {index + 1}
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

                  {/* Remove Button */}
                  <button
                    type="button"
                    onClick={() => handleRemovePickup(index)}
                    disabled={disabled}
                    className="p-1 text-red-400 hover:text-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Remove"
                    aria-label="Remove authorized person"
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
                </div>
              </div>

              {/* Pickup Fields */}
              <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                {/* Full Name */}
                <div>
                  <label
                    htmlFor={`pickup${index}Name`}
                    className="block text-sm font-medium text-gray-700"
                  >
                    Full Name <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="text"
                    id={`pickup${index}Name`}
                    name={`pickup${index}Name`}
                    value={pickup.name}
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
                    htmlFor={`pickup${index}Relationship`}
                    className="block text-sm font-medium text-gray-700"
                  >
                    Relationship <span className="text-red-500">*</span>
                  </label>
                  <select
                    id={`pickup${index}Relationship`}
                    name={`pickup${index}Relationship`}
                    value={pickup.relationship}
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

                {/* Phone Number */}
                <div>
                  <label
                    htmlFor={`pickup${index}Phone`}
                    className="block text-sm font-medium text-gray-700"
                  >
                    Phone Number <span className="text-red-500">*</span>
                  </label>
                  <input
                    type="tel"
                    id={`pickup${index}Phone`}
                    name={`pickup${index}Phone`}
                    value={pickup.phone}
                    onChange={handleInputChange(index, 'phone')}
                    disabled={disabled}
                    required
                    placeholder="(514) 555-0100"
                    className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                  />
                </div>

                {/* Photo Upload Placeholder */}
                <div>
                  <label className="block text-sm font-medium text-gray-700">
                    Photo (Optional)
                  </label>
                  <div className="mt-1 flex items-center space-x-4">
                    {pickup.photoUrl ? (
                      <div className="relative">
                        <img
                          src={pickup.photoUrl}
                          alt={`${pickup.name || 'Pickup person'} photo`}
                          className="h-12 w-12 rounded-full object-cover"
                        />
                      </div>
                    ) : (
                      <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                        <svg
                          className="h-6 w-6 text-gray-400"
                          fill="none"
                          stroke="currentColor"
                          viewBox="0 0 24 24"
                        >
                          <path
                            strokeLinecap="round"
                            strokeLinejoin="round"
                            strokeWidth={2}
                            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                          />
                        </svg>
                      </div>
                    )}
                    <button
                      type="button"
                      disabled={disabled}
                      className="text-sm font-medium text-primary-600 hover:text-primary-700 disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                      Upload photo
                    </button>
                  </div>
                  <p className="mt-1 text-xs text-gray-500">
                    A photo helps staff identify authorized persons.
                  </p>
                </div>
              </div>

              {/* Notes */}
              <div className="mt-4">
                <label
                  htmlFor={`pickup${index}Notes`}
                  className="block text-sm font-medium text-gray-700"
                >
                  Notes (Optional)
                </label>
                <textarea
                  id={`pickup${index}Notes`}
                  name={`pickup${index}Notes`}
                  rows={2}
                  value={pickup.notes || ''}
                  onChange={handleInputChange(index, 'notes')}
                  disabled={disabled}
                  placeholder="Any additional information about this person..."
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
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
            />
          </svg>
          <p className="mt-2 text-sm text-gray-600">
            No authorized pickup persons added yet.
          </p>
          <p className="text-xs text-gray-500">
            Add people who are authorized to pick up your child.
          </p>
        </div>
      )}

      {/* Add Button */}
      {data.length < maxPickups && (
        <button
          type="button"
          onClick={handleAddPickup}
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
          Add Authorized Person
        </button>
      )}

      {data.length >= maxPickups && (
        <p className="text-sm text-gray-500 text-center">
          Maximum of {maxPickups} authorized pickup persons reached.
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
              <strong>Important:</strong> Only people listed here (along with
              parents/guardians) will be permitted to pick up your child.
              Staff will verify identity before releasing any child. Please
              ensure contact phone numbers are accurate for verification purposes.
            </p>
          </div>
        </div>
      </div>

      {/* Additional Instructions */}
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
              If someone not listed needs to pick up your child in an emergency,
              you must call the daycare in advance and provide verbal authorization
              along with a description of the person for identification.
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

export type { AuthorizedPickupsSectionProps };
