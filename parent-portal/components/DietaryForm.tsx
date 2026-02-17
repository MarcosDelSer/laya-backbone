'use client';

import { useState, useEffect, useCallback } from 'react';
import type {
  DietaryProfile,
  DietaryType,
  ChildAllergy,
  AllergenSeverity,
  UpdateDietaryProfileRequest,
} from '../lib/types';
import { AllergenBadge } from './AllergenBadge';

interface DietaryFormProps {
  /** Initial dietary profile data (if editing existing profile) */
  initialProfile?: DietaryProfile | null;
  /** Child's name for display */
  childName?: string;
  /** Callback when form is submitted */
  onSubmit: (data: UpdateDietaryProfileRequest) => Promise<void>;
  /** Callback when form is cancelled (optional) */
  onCancel?: () => void;
  /** Whether the form is currently submitting */
  isSubmitting?: boolean;
  /** Whether to show in compact mode */
  compact?: boolean;
}

/** Common allergens for quick selection */
const COMMON_ALLERGENS = [
  'Peanuts',
  'Tree Nuts',
  'Milk',
  'Eggs',
  'Wheat',
  'Soy',
  'Fish',
  'Shellfish',
  'Sesame',
  'Mustard',
  'Sulphites',
  'Gluten',
] as const;

/** Dietary type options with labels */
const DIETARY_TYPE_OPTIONS: { value: DietaryType; label: string }[] = [
  { value: 'regular', label: 'Regular' },
  { value: 'vegetarian', label: 'Vegetarian' },
  { value: 'vegan', label: 'Vegan' },
  { value: 'halal', label: 'Halal' },
  { value: 'kosher', label: 'Kosher' },
  { value: 'gluten_free', label: 'Gluten-Free' },
  { value: 'lactose_free', label: 'Lactose-Free' },
  { value: 'other', label: 'Other' },
];

/** Severity options with labels and colors */
const SEVERITY_OPTIONS: { value: AllergenSeverity; label: string }[] = [
  { value: 'mild', label: 'Mild' },
  { value: 'moderate', label: 'Moderate' },
  { value: 'severe', label: 'Severe' },
];

/**
 * DietaryForm provides a form for parents to submit or edit dietary
 * accommodations for their children, including dietary type, allergies,
 * and food restrictions.
 */
export function DietaryForm({
  initialProfile,
  childName,
  onSubmit,
  onCancel,
  isSubmitting = false,
  compact = false,
}: DietaryFormProps) {
  // Form state
  const [dietaryType, setDietaryType] = useState<DietaryType>(
    initialProfile?.dietaryType || 'regular'
  );
  const [allergies, setAllergies] = useState<ChildAllergy[]>(
    initialProfile?.allergies || []
  );
  const [restrictions, setRestrictions] = useState(
    initialProfile?.restrictions || ''
  );
  const [notes, setNotes] = useState(initialProfile?.notes || '');

  // New allergy input state
  const [newAllergen, setNewAllergen] = useState('');
  const [newSeverity, setNewSeverity] = useState<AllergenSeverity>('moderate');
  const [newAllergenNotes, setNewAllergenNotes] = useState('');
  const [showCustomAllergen, setShowCustomAllergen] = useState(false);

  // Form validation
  const [errors, setErrors] = useState<Record<string, string>>({});
  const [isDirty, setIsDirty] = useState(false);

  // Reset form when initialProfile changes
  useEffect(() => {
    if (initialProfile) {
      setDietaryType(initialProfile.dietaryType);
      setAllergies(initialProfile.allergies || []);
      setRestrictions(initialProfile.restrictions || '');
      setNotes(initialProfile.notes || '');
      setIsDirty(false);
    }
  }, [initialProfile]);

  // Mark form as dirty on any change
  const markDirty = useCallback(() => {
    if (!isDirty) {
      setIsDirty(true);
    }
  }, [isDirty]);

  // Add a new allergy to the list
  const handleAddAllergy = useCallback(() => {
    if (!newAllergen.trim()) {
      setErrors((prev) => ({ ...prev, newAllergen: 'Please enter an allergen name' }));
      return;
    }

    // Check for duplicates
    const exists = allergies.some(
      (a) => a.allergen.toLowerCase() === newAllergen.trim().toLowerCase()
    );
    if (exists) {
      setErrors((prev) => ({ ...prev, newAllergen: 'This allergen is already added' }));
      return;
    }

    setAllergies((prev) => [
      ...prev,
      {
        allergen: newAllergen.trim(),
        severity: newSeverity,
        notes: newAllergenNotes.trim() || undefined,
      },
    ]);
    setNewAllergen('');
    setNewAllergenNotes('');
    setNewSeverity('moderate');
    setShowCustomAllergen(false);
    setErrors((prev) => {
      const { newAllergen: _, ...rest } = prev;
      return rest;
    });
    markDirty();
  }, [newAllergen, newSeverity, newAllergenNotes, allergies, markDirty]);

  // Add common allergen quickly
  const handleQuickAddAllergen = useCallback(
    (allergenName: string) => {
      const exists = allergies.some(
        (a) => a.allergen.toLowerCase() === allergenName.toLowerCase()
      );
      if (exists) return;

      setAllergies((prev) => [
        ...prev,
        { allergen: allergenName, severity: 'moderate' },
      ]);
      markDirty();
    },
    [allergies, markDirty]
  );

  // Remove an allergy from the list
  const handleRemoveAllergy = useCallback(
    (allergen: string) => {
      setAllergies((prev) => prev.filter((a) => a.allergen !== allergen));
      markDirty();
    },
    [markDirty]
  );

  // Update allergy severity
  const handleUpdateSeverity = useCallback(
    (allergen: string, severity: AllergenSeverity) => {
      setAllergies((prev) =>
        prev.map((a) => (a.allergen === allergen ? { ...a, severity } : a))
      );
      markDirty();
    },
    [markDirty]
  );

  // Handle form submission
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    // Validate
    const newErrors: Record<string, string> = {};
    if (!dietaryType) {
      newErrors.dietaryType = 'Please select a dietary type';
    }
    setErrors(newErrors);

    if (Object.keys(newErrors).length > 0) return;

    const data: UpdateDietaryProfileRequest = {
      dietaryType,
      allergies,
      restrictions: restrictions.trim() || undefined,
      notes: notes.trim() || undefined,
    };

    await onSubmit(data);
    setIsDirty(false);
  };

  // Form field styling
  const inputClasses =
    'w-full rounded-lg border border-gray-300 px-4 py-2.5 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary disabled:bg-gray-100 disabled:text-gray-500';
  const labelClasses = 'block text-sm font-medium text-gray-700 mb-1.5';
  const errorClasses = 'mt-1 text-xs text-red-600';

  return (
    <form onSubmit={handleSubmit} className={compact ? 'space-y-4' : 'space-y-6'}>
      {/* Child name header */}
      {childName && (
        <div className="border-b border-gray-200 pb-4">
          <h3 className="text-lg font-semibold text-gray-900">
            Dietary Profile for {childName}
          </h3>
          <p className="mt-1 text-sm text-gray-500">
            Please provide dietary requirements and allergy information for your child.
          </p>
        </div>
      )}

      {/* Dietary Type Selection */}
      <div>
        <label htmlFor="dietaryType" className={labelClasses}>
          Dietary Type
        </label>
        <select
          id="dietaryType"
          value={dietaryType}
          onChange={(e) => {
            setDietaryType(e.target.value as DietaryType);
            markDirty();
          }}
          disabled={isSubmitting}
          className={inputClasses}
          aria-describedby={errors.dietaryType ? 'dietaryType-error' : undefined}
        >
          {DIETARY_TYPE_OPTIONS.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
        {errors.dietaryType && (
          <p id="dietaryType-error" className={errorClasses}>
            {errors.dietaryType}
          </p>
        )}
      </div>

      {/* Allergies Section */}
      <div>
        <label className={labelClasses}>Allergies</label>

        {/* Current allergies list */}
        {allergies.length > 0 && (
          <div className="mb-4 rounded-lg bg-gray-50 p-4">
            <div className="flex flex-wrap gap-2">
              {allergies.map((allergy) => (
                <div
                  key={allergy.allergen}
                  className="flex items-center gap-2 rounded-lg bg-white border border-gray-200 px-3 py-2"
                >
                  <AllergenBadge
                    allergen={allergy.allergen}
                    severity={allergy.severity}
                    size="sm"
                    showIcon={true}
                  />
                  <select
                    value={allergy.severity}
                    onChange={(e) =>
                      handleUpdateSeverity(
                        allergy.allergen,
                        e.target.value as AllergenSeverity
                      )
                    }
                    disabled={isSubmitting}
                    className="text-xs border border-gray-200 rounded px-1 py-0.5 focus:outline-none focus:ring-1 focus:ring-primary"
                    aria-label={`Severity for ${allergy.allergen}`}
                  >
                    {SEVERITY_OPTIONS.map((option) => (
                      <option key={option.value} value={option.value}>
                        {option.label}
                      </option>
                    ))}
                  </select>
                  <button
                    type="button"
                    onClick={() => handleRemoveAllergy(allergy.allergen)}
                    disabled={isSubmitting}
                    className="text-gray-400 hover:text-red-500 disabled:opacity-50"
                    aria-label={`Remove ${allergy.allergen}`}
                  >
                    <svg
                      className="h-4 w-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M6 18L18 6M6 6l12 12"
                      />
                    </svg>
                  </button>
                </div>
              ))}
            </div>
          </div>
        )}

        {/* Quick add common allergens */}
        <div className="mb-3">
          <p className="text-xs text-gray-500 mb-2">Quick add common allergens:</p>
          <div className="flex flex-wrap gap-1.5">
            {COMMON_ALLERGENS.filter(
              (a) => !allergies.some((allergy) => allergy.allergen === a)
            ).map((allergen) => (
              <button
                key={allergen}
                type="button"
                onClick={() => handleQuickAddAllergen(allergen)}
                disabled={isSubmitting}
                className="px-2.5 py-1 text-xs rounded-full border border-gray-300 text-gray-600 hover:bg-gray-100 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
              >
                + {allergen}
              </button>
            ))}
          </div>
        </div>

        {/* Add custom allergen */}
        {!showCustomAllergen ? (
          <button
            type="button"
            onClick={() => setShowCustomAllergen(true)}
            disabled={isSubmitting}
            className="text-sm text-primary hover:text-primary-dark focus:outline-none focus:underline disabled:opacity-50"
          >
            + Add custom allergen
          </button>
        ) : (
          <div className="rounded-lg border border-gray-200 bg-gray-50 p-4">
            <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
              <div>
                <label htmlFor="newAllergen" className="block text-xs font-medium text-gray-600 mb-1">
                  Allergen Name
                </label>
                <input
                  id="newAllergen"
                  type="text"
                  value={newAllergen}
                  onChange={(e) => {
                    setNewAllergen(e.target.value);
                    setErrors((prev) => {
                      const { newAllergen: _, ...rest } = prev;
                      return rest;
                    });
                  }}
                  disabled={isSubmitting}
                  placeholder="e.g., Avocado"
                  className={`${inputClasses} ${errors.newAllergen ? 'border-red-500' : ''}`}
                />
                {errors.newAllergen && (
                  <p className={errorClasses}>{errors.newAllergen}</p>
                )}
              </div>
              <div>
                <label htmlFor="newSeverity" className="block text-xs font-medium text-gray-600 mb-1">
                  Severity
                </label>
                <select
                  id="newSeverity"
                  value={newSeverity}
                  onChange={(e) => setNewSeverity(e.target.value as AllergenSeverity)}
                  disabled={isSubmitting}
                  className={inputClasses}
                >
                  {SEVERITY_OPTIONS.map((option) => (
                    <option key={option.value} value={option.value}>
                      {option.label}
                    </option>
                  ))}
                </select>
              </div>
            </div>
            <div className="mt-3">
              <label htmlFor="newAllergenNotes" className="block text-xs font-medium text-gray-600 mb-1">
                Notes (optional)
              </label>
              <input
                id="newAllergenNotes"
                type="text"
                value={newAllergenNotes}
                onChange={(e) => setNewAllergenNotes(e.target.value)}
                disabled={isSubmitting}
                placeholder="e.g., Can cause hives"
                className={inputClasses}
              />
            </div>
            <div className="mt-3 flex justify-end gap-2">
              <button
                type="button"
                onClick={() => {
                  setShowCustomAllergen(false);
                  setNewAllergen('');
                  setNewAllergenNotes('');
                  setErrors((prev) => {
                    const { newAllergen: _, ...rest } = prev;
                    return rest;
                  });
                }}
                disabled={isSubmitting}
                className="px-3 py-1.5 text-sm text-gray-600 hover:text-gray-800 focus:outline-none"
              >
                Cancel
              </button>
              <button
                type="button"
                onClick={handleAddAllergy}
                disabled={isSubmitting || !newAllergen.trim()}
                className="px-3 py-1.5 text-sm bg-primary text-white rounded-lg hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-1 disabled:opacity-50 disabled:cursor-not-allowed"
              >
                Add Allergen
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Food Restrictions */}
      <div>
        <label htmlFor="restrictions" className={labelClasses}>
          Food Restrictions
        </label>
        <textarea
          id="restrictions"
          value={restrictions}
          onChange={(e) => {
            setRestrictions(e.target.value);
            markDirty();
          }}
          disabled={isSubmitting}
          placeholder="List any specific foods to avoid (e.g., no red meat, no spicy foods)"
          rows={3}
          className={`${inputClasses} resize-none`}
        />
        <p className="mt-1 text-xs text-gray-400">
          Separate multiple restrictions with commas or new lines
        </p>
      </div>

      {/* Additional Notes */}
      <div>
        <label htmlFor="notes" className={labelClasses}>
          Additional Notes
        </label>
        <textarea
          id="notes"
          value={notes}
          onChange={(e) => {
            setNotes(e.target.value);
            markDirty();
          }}
          disabled={isSubmitting}
          placeholder="Any other information about your child's dietary needs (e.g., texture preferences, meal timing)"
          rows={3}
          className={`${inputClasses} resize-none`}
        />
      </div>

      {/* Information notice */}
      <div className="rounded-lg bg-blue-50 border border-blue-200 p-4">
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
              Your child&apos;s care team will be notified of any dietary updates.
              Please speak directly with staff for urgent dietary concerns.
            </p>
          </div>
        </div>
      </div>

      {/* Form Actions */}
      <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-4">
        {onCancel && (
          <button
            type="button"
            onClick={onCancel}
            disabled={isSubmitting}
            className="btn btn-outline"
          >
            Cancel
          </button>
        )}
        <button
          type="submit"
          disabled={isSubmitting || !isDirty}
          className="btn btn-primary"
        >
          {isSubmitting ? (
            <>
              <svg
                className="mr-2 h-4 w-4 animate-spin"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle
                  className="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  strokeWidth="4"
                />
                <path
                  className="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                />
              </svg>
              Saving...
            </>
          ) : (
            <>
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
                  d="M5 13l4 4L19 7"
                />
              </svg>
              Save Dietary Profile
            </>
          )}
        </button>
      </div>
    </form>
  );
}
