'use client';

import { useCallback } from 'react';
import type { NutritionInfo } from '@/lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Nutrition data without id and formId for the wizard.
 */
export type NutritionData = Omit<NutritionInfo, 'id' | 'formId'>;

interface NutritionSectionProps {
  /** Current nutrition data */
  data: NutritionData | null;
  /** Callback when data changes */
  onChange: (data: NutritionData | null) => void;
  /** Whether the form is disabled */
  disabled?: boolean;
  /** Validation errors to display */
  errors?: string[];
}

// ============================================================================
// Default Data
// ============================================================================

const createDefaultNutritionData = (): NutritionData => ({
  dietaryRestrictions: '',
  foodAllergies: '',
  feedingInstructions: '',
  isBottleFeeding: false,
  bottleFeedingInfo: '',
  foodPreferences: '',
  foodDislikes: '',
  mealPlanNotes: '',
});

// ============================================================================
// Component
// ============================================================================

/**
 * Nutrition information section for enrollment form wizard.
 * Collects dietary restrictions, food allergies, feeding instructions,
 * bottle feeding info, food preferences, and meal plan notes.
 */
export function NutritionSection({
  data,
  onChange,
  disabled = false,
  errors = [],
}: NutritionSectionProps) {
  // ---------------------------------------------------------------------------
  // Initialize Data
  // ---------------------------------------------------------------------------

  const nutritionData = data ?? createDefaultNutritionData();

  // ---------------------------------------------------------------------------
  // Handlers
  // ---------------------------------------------------------------------------

  const handleInputChange = useCallback(
    (field: keyof NutritionData) =>
      (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        onChange({ ...nutritionData, [field]: e.target.value });
      },
    [nutritionData, onChange]
  );

  const handleCheckboxChange = useCallback(
    (field: keyof NutritionData) =>
      (e: React.ChangeEvent<HTMLInputElement>) => {
        onChange({ ...nutritionData, [field]: e.target.checked });
      },
    [nutritionData, onChange]
  );

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div className="space-y-6">
      {/* Section Header */}
      <div>
        <h3 className="text-lg font-medium text-gray-900">
          Nutrition & Dietary Information
        </h3>
        <p className="mt-1 text-sm text-gray-500">
          Provide dietary restrictions, food allergies, and feeding preferences
          for the child. This information helps ensure proper meal planning and
          safe food handling.
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

      {/* ================================================================== */}
      {/* Dietary Restrictions Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Dietary Restrictions
        </h4>

        <div>
          <label
            htmlFor="dietaryRestrictions"
            className="block text-sm font-medium text-gray-700"
          >
            Dietary Restrictions
          </label>
          <textarea
            id="dietaryRestrictions"
            rows={3}
            value={nutritionData.dietaryRestrictions ?? ''}
            onChange={handleInputChange('dietaryRestrictions')}
            disabled={disabled}
            placeholder="List any dietary restrictions (e.g., vegetarian, vegan, kosher, halal, lactose-free, gluten-free)..."
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
          <p className="mt-1 text-xs text-gray-500">
            Include religious, cultural, or personal dietary requirements.
          </p>
        </div>
      </div>

      {/* ================================================================== */}
      {/* Food Allergies Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Food Allergies
        </h4>

        <div>
          <label
            htmlFor="foodAllergies"
            className="block text-sm font-medium text-gray-700"
          >
            Food Allergies & Intolerances
          </label>
          <textarea
            id="foodAllergies"
            rows={3}
            value={nutritionData.foodAllergies ?? ''}
            onChange={handleInputChange('foodAllergies')}
            disabled={disabled}
            placeholder="List all food allergies and intolerances (e.g., peanuts, tree nuts, eggs, milk, shellfish, wheat)..."
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
          <p className="mt-1 text-xs text-gray-500">
            Please include severity and any required precautions.
          </p>
        </div>

        {/* Food Allergy Warning */}
        {nutritionData.foodAllergies && nutritionData.foodAllergies.trim() !== '' && (
          <div className="mt-4 rounded-lg bg-amber-50 border border-amber-200 p-4">
            <div className="flex">
              <svg
                className="h-5 w-5 text-amber-400 flex-shrink-0"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                  clipRule="evenodd"
                />
              </svg>
              <div className="ml-3">
                <p className="text-sm text-amber-700">
                  <strong>Important:</strong> All food allergies will be communicated
                  to kitchen staff and posted in the classroom for safety. Please also
                  complete the Health Information section with detailed allergy information.
                </p>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* ================================================================== */}
      {/* Bottle Feeding Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <div className="flex items-start space-x-3">
          <div className="flex items-center h-5 mt-1">
            <input
              type="checkbox"
              id="isBottleFeeding"
              checked={nutritionData.isBottleFeeding}
              onChange={handleCheckboxChange('isBottleFeeding')}
              disabled={disabled}
              className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed"
            />
          </div>
          <div className="flex-1">
            <label
              htmlFor="isBottleFeeding"
              className="text-base font-medium text-gray-900"
            >
              Child is Bottle Feeding
            </label>
            <p className="text-sm text-gray-500">
              Check this box if the child requires bottle feeding.
            </p>
          </div>
        </div>

        {nutritionData.isBottleFeeding && (
          <div className="mt-4 ml-7">
            <div className="rounded-lg bg-blue-50 border border-blue-200 p-4 mb-4">
              <div className="flex">
                <svg
                  className="h-5 w-5 text-blue-400 flex-shrink-0"
                  fill="currentColor"
                  viewBox="0 0 20 20"
                >
                  <path
                    fillRule="evenodd"
                    d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                    clipRule="evenodd"
                  />
                </svg>
                <div className="ml-3">
                  <p className="text-sm text-blue-700">
                    <strong>Note:</strong> Please provide pre-prepared bottles labeled
                    with the child&apos;s name and date. Breast milk and formula must be
                    stored according to health guidelines.
                  </p>
                </div>
              </div>
            </div>

            <label
              htmlFor="bottleFeedingInfo"
              className="block text-sm font-medium text-gray-700"
            >
              Bottle Feeding Instructions
            </label>
            <textarea
              id="bottleFeedingInfo"
              rows={3}
              value={nutritionData.bottleFeedingInfo ?? ''}
              onChange={handleInputChange('bottleFeedingInfo')}
              disabled={disabled}
              placeholder="Describe feeding schedule, preferred temperature, type of formula or breast milk, amount per feeding, any special instructions..."
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
          </div>
        )}
      </div>

      {/* ================================================================== */}
      {/* Feeding Instructions Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Special Feeding Instructions
        </h4>

        <div>
          <label
            htmlFor="feedingInstructions"
            className="block text-sm font-medium text-gray-700"
          >
            Feeding Instructions
          </label>
          <textarea
            id="feedingInstructions"
            rows={3}
            value={nutritionData.feedingInstructions ?? ''}
            onChange={handleInputChange('feedingInstructions')}
            disabled={disabled}
            placeholder="Any special feeding requirements or instructions (e.g., cut food into small pieces, requires assistance eating, texture modifications)..."
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
          <p className="mt-1 text-xs text-gray-500">
            Include any special utensils, seating requirements, or supervision needs.
          </p>
        </div>
      </div>

      {/* ================================================================== */}
      {/* Food Preferences Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Food Preferences
        </h4>

        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
          {/* Food Preferences (Likes) */}
          <div>
            <label
              htmlFor="foodPreferences"
              className="block text-sm font-medium text-gray-700"
            >
              Foods Child Enjoys
            </label>
            <textarea
              id="foodPreferences"
              rows={3}
              value={nutritionData.foodPreferences ?? ''}
              onChange={handleInputChange('foodPreferences')}
              disabled={disabled}
              placeholder="List foods the child enjoys eating..."
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
            <p className="mt-1 text-xs text-gray-500">
              Helps us ensure the child eats well during the day.
            </p>
          </div>

          {/* Food Dislikes */}
          <div>
            <label
              htmlFor="foodDislikes"
              className="block text-sm font-medium text-gray-700"
            >
              Foods Child Dislikes
            </label>
            <textarea
              id="foodDislikes"
              rows={3}
              value={nutritionData.foodDislikes ?? ''}
              onChange={handleInputChange('foodDislikes')}
              disabled={disabled}
              placeholder="List foods the child typically refuses or dislikes..."
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
            <p className="mt-1 text-xs text-gray-500">
              We will still encourage trying new foods.
            </p>
          </div>
        </div>
      </div>

      {/* ================================================================== */}
      {/* Meal Plan Notes Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Additional Notes
        </h4>

        <div>
          <label
            htmlFor="mealPlanNotes"
            className="block text-sm font-medium text-gray-700"
          >
            Meal Plan Notes
          </label>
          <textarea
            id="mealPlanNotes"
            rows={3}
            value={nutritionData.mealPlanNotes ?? ''}
            onChange={handleInputChange('mealPlanNotes')}
            disabled={disabled}
            placeholder="Any additional information about the child's eating habits, cultural considerations, or meal planning preferences..."
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
        </div>
      </div>

      {/* ================================================================== */}
      {/* Info Notices */}
      {/* ================================================================== */}
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
              <strong>Menus:</strong> Our weekly menus are posted and follow
              Quebec nutritional guidelines for childcare. Please let us know
              immediately if any new dietary restrictions or allergies develop.
            </p>
          </div>
        </div>
      </div>

      {/* Bottle Feeding Reminder */}
      {nutritionData.isBottleFeeding && (
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
                <strong>Bottle Feeding Reminder:</strong> Please label all
                bottles and containers with the child&apos;s name and date. Unused
                portions must be discarded after 2 hours at room temperature or
                24 hours if refrigerated.
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ============================================================================
// Exports
// ============================================================================

export type { NutritionSectionProps };
