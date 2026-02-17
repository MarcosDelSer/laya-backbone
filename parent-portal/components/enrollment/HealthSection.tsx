'use client';

import { useCallback } from 'react';
import type { HealthInfo, AllergyInfo, MedicationInfo } from '@/lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Health data without id and formId for the wizard.
 */
export type HealthData = Omit<HealthInfo, 'id' | 'formId'>;

interface HealthSectionProps {
  /** Current health data */
  data: HealthData | null;
  /** Callback when data changes */
  onChange: (data: HealthData | null) => void;
  /** Whether the form is disabled */
  disabled?: boolean;
  /** Validation errors to display */
  errors?: string[];
}

// ============================================================================
// Default Data
// ============================================================================

const createDefaultHealthData = (): HealthData => ({
  allergies: [],
  medicalConditions: '',
  hasEpiPen: false,
  epiPenInstructions: '',
  medications: [],
  doctorName: '',
  doctorPhone: '',
  doctorAddress: '',
  healthInsuranceNumber: '',
  healthInsuranceExpiry: '',
  specialNeeds: '',
  developmentalNotes: '',
});

const createDefaultAllergyInfo = (): AllergyInfo => ({
  allergen: '',
  severity: undefined,
  reaction: '',
  treatment: '',
});

const createDefaultMedicationInfo = (): MedicationInfo => ({
  name: '',
  dosage: '',
  schedule: '',
  instructions: '',
});

// ============================================================================
// Constants
// ============================================================================

const SEVERITY_OPTIONS: Array<{ value: AllergyInfo['severity']; label: string }> = [
  { value: undefined, label: 'Select severity' },
  { value: 'mild', label: 'Mild' },
  { value: 'moderate', label: 'Moderate' },
  { value: 'severe', label: 'Severe' },
];

// ============================================================================
// Component
// ============================================================================

/**
 * Health information section for enrollment form wizard.
 * Collects comprehensive health data including allergies, medications,
 * medical conditions, doctor information, and health insurance details.
 */
export function HealthSection({
  data,
  onChange,
  disabled = false,
  errors = [],
}: HealthSectionProps) {
  // ---------------------------------------------------------------------------
  // Initialize Data
  // ---------------------------------------------------------------------------

  const healthData = data ?? createDefaultHealthData();

  // ---------------------------------------------------------------------------
  // Handlers - Basic Fields
  // ---------------------------------------------------------------------------

  const handleInputChange = useCallback(
    (field: keyof HealthData) =>
      (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        onChange({ ...healthData, [field]: e.target.value });
      },
    [healthData, onChange]
  );

  const handleCheckboxChange = useCallback(
    (field: keyof HealthData) =>
      (e: React.ChangeEvent<HTMLInputElement>) => {
        onChange({ ...healthData, [field]: e.target.checked });
      },
    [healthData, onChange]
  );

  // ---------------------------------------------------------------------------
  // Handlers - Allergies
  // ---------------------------------------------------------------------------

  const handleAddAllergy = useCallback(() => {
    const currentAllergies = healthData.allergies ?? [];
    onChange({
      ...healthData,
      allergies: [...currentAllergies, createDefaultAllergyInfo()],
    });
  }, [healthData, onChange]);

  const handleRemoveAllergy = useCallback(
    (index: number) => {
      const currentAllergies = healthData.allergies ?? [];
      const newAllergies = currentAllergies.filter((_, i) => i !== index);
      onChange({ ...healthData, allergies: newAllergies });
    },
    [healthData, onChange]
  );

  const handleAllergyChange = useCallback(
    (index: number, field: keyof AllergyInfo) =>
      (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
        const currentAllergies = healthData.allergies ?? [];
        const newAllergies = [...currentAllergies];
        if (field === 'severity') {
          newAllergies[index] = {
            ...newAllergies[index],
            severity: (e.target.value as AllergyInfo['severity']) || undefined,
          };
        } else {
          newAllergies[index] = { ...newAllergies[index], [field]: e.target.value };
        }
        onChange({ ...healthData, allergies: newAllergies });
      },
    [healthData, onChange]
  );

  // ---------------------------------------------------------------------------
  // Handlers - Medications
  // ---------------------------------------------------------------------------

  const handleAddMedication = useCallback(() => {
    const currentMedications = healthData.medications ?? [];
    onChange({
      ...healthData,
      medications: [...currentMedications, createDefaultMedicationInfo()],
    });
  }, [healthData, onChange]);

  const handleRemoveMedication = useCallback(
    (index: number) => {
      const currentMedications = healthData.medications ?? [];
      const newMedications = currentMedications.filter((_, i) => i !== index);
      onChange({ ...healthData, medications: newMedications });
    },
    [healthData, onChange]
  );

  const handleMedicationChange = useCallback(
    (index: number, field: keyof MedicationInfo) =>
      (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        const currentMedications = healthData.medications ?? [];
        const newMedications = [...currentMedications];
        newMedications[index] = { ...newMedications[index], [field]: e.target.value };
        onChange({ ...healthData, medications: newMedications });
      },
    [healthData, onChange]
  );

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div className="space-y-6">
      {/* Section Header */}
      <div>
        <h3 className="text-lg font-medium text-gray-900">
          Health Information
        </h3>
        <p className="mt-1 text-sm text-gray-500">
          Provide health and medical information for the child. This information
          helps ensure proper care and emergency response.
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
      {/* Allergies Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h4 className="text-base font-medium text-gray-900">
              Allergies
            </h4>
            <p className="mt-1 text-sm text-gray-500">
              List any known allergies including food, environmental, or medication allergies.
            </p>
          </div>
        </div>

        {/* Allergy List */}
        {(healthData.allergies ?? []).length > 0 ? (
          <div className="space-y-4 mb-4">
            {(healthData.allergies ?? []).map((allergy, index) => (
              <div
                key={index}
                className="border border-gray-200 rounded-lg p-4 bg-white"
              >
                <div className="flex items-center justify-between mb-3">
                  <span className="text-sm font-medium text-gray-700">
                    Allergy {index + 1}
                  </span>
                  <button
                    type="button"
                    onClick={() => handleRemoveAllergy(index)}
                    disabled={disabled}
                    className="p-1 text-red-400 hover:text-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Remove allergy"
                    aria-label="Remove allergy"
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

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  {/* Allergen */}
                  <div>
                    <label
                      htmlFor={`allergy${index}Allergen`}
                      className="block text-sm font-medium text-gray-700"
                    >
                      Allergen <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      id={`allergy${index}Allergen`}
                      value={allergy.allergen}
                      onChange={handleAllergyChange(index, 'allergen')}
                      disabled={disabled}
                      placeholder="e.g., Peanuts, Shellfish, Penicillin"
                      className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                    />
                  </div>

                  {/* Severity */}
                  <div>
                    <label
                      htmlFor={`allergy${index}Severity`}
                      className="block text-sm font-medium text-gray-700"
                    >
                      Severity
                    </label>
                    <select
                      id={`allergy${index}Severity`}
                      value={allergy.severity ?? ''}
                      onChange={handleAllergyChange(index, 'severity')}
                      disabled={disabled}
                      className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                    >
                      {SEVERITY_OPTIONS.map((option) => (
                        <option key={option.label} value={option.value ?? ''}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </div>

                  {/* Reaction */}
                  <div>
                    <label
                      htmlFor={`allergy${index}Reaction`}
                      className="block text-sm font-medium text-gray-700"
                    >
                      Reaction
                    </label>
                    <input
                      type="text"
                      id={`allergy${index}Reaction`}
                      value={allergy.reaction ?? ''}
                      onChange={handleAllergyChange(index, 'reaction')}
                      disabled={disabled}
                      placeholder="e.g., Hives, Swelling, Anaphylaxis"
                      className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                    />
                  </div>

                  {/* Treatment */}
                  <div>
                    <label
                      htmlFor={`allergy${index}Treatment`}
                      className="block text-sm font-medium text-gray-700"
                    >
                      Treatment
                    </label>
                    <input
                      type="text"
                      id={`allergy${index}Treatment`}
                      value={allergy.treatment ?? ''}
                      onChange={handleAllergyChange(index, 'treatment')}
                      disabled={disabled}
                      placeholder="e.g., Administer EpiPen, Call 911"
                      className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                    />
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-6 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300 mb-4">
            <svg
              className="mx-auto h-10 w-10 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <p className="mt-2 text-sm text-gray-600">
              No allergies listed. Click below to add if applicable.
            </p>
          </div>
        )}

        {/* Add Allergy Button */}
        <button
          type="button"
          onClick={handleAddAllergy}
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
          Add Allergy
        </button>
      </div>

      {/* ================================================================== */}
      {/* EpiPen Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <div className="flex items-start space-x-3">
          <div className="flex items-center h-5 mt-1">
            <input
              type="checkbox"
              id="hasEpiPen"
              checked={healthData.hasEpiPen}
              onChange={handleCheckboxChange('hasEpiPen')}
              disabled={disabled}
              className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed"
            />
          </div>
          <div className="flex-1">
            <label
              htmlFor="hasEpiPen"
              className="text-base font-medium text-gray-900"
            >
              Child has EpiPen (Epinephrine Auto-Injector)
            </label>
            <p className="text-sm text-gray-500">
              Check this box if the child carries an EpiPen for severe allergic reactions.
            </p>
          </div>
        </div>

        {healthData.hasEpiPen && (
          <div className="mt-4 ml-7">
            <div className="rounded-lg bg-amber-50 border border-amber-200 p-4 mb-4">
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
                    <strong>Important:</strong> Please ensure the EpiPen is provided to the
                    daycare with clear instructions and is kept up-to-date.
                  </p>
                </div>
              </div>
            </div>

            <label
              htmlFor="epiPenInstructions"
              className="block text-sm font-medium text-gray-700"
            >
              EpiPen Instructions
            </label>
            <textarea
              id="epiPenInstructions"
              rows={3}
              value={healthData.epiPenInstructions ?? ''}
              onChange={handleInputChange('epiPenInstructions')}
              disabled={disabled}
              placeholder="Describe when and how to administer the EpiPen, and any follow-up actions required..."
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
          </div>
        )}
      </div>

      {/* ================================================================== */}
      {/* Medical Conditions Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Medical Conditions
        </h4>

        <div>
          <label
            htmlFor="medicalConditions"
            className="block text-sm font-medium text-gray-700"
          >
            Known Medical Conditions
          </label>
          <textarea
            id="medicalConditions"
            rows={3}
            value={healthData.medicalConditions ?? ''}
            onChange={handleInputChange('medicalConditions')}
            disabled={disabled}
            placeholder="List any medical conditions such as asthma, diabetes, epilepsy, heart conditions, etc."
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
          <p className="mt-1 text-xs text-gray-500">
            Include any ongoing health conditions that require monitoring or special care.
          </p>
        </div>
      </div>

      {/* ================================================================== */}
      {/* Medications Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h4 className="text-base font-medium text-gray-900">
              Medications
            </h4>
            <p className="mt-1 text-sm text-gray-500">
              List any medications the child takes regularly or as needed.
            </p>
          </div>
        </div>

        {/* Medication List */}
        {(healthData.medications ?? []).length > 0 ? (
          <div className="space-y-4 mb-4">
            {(healthData.medications ?? []).map((medication, index) => (
              <div
                key={index}
                className="border border-gray-200 rounded-lg p-4 bg-white"
              >
                <div className="flex items-center justify-between mb-3">
                  <span className="text-sm font-medium text-gray-700">
                    Medication {index + 1}
                  </span>
                  <button
                    type="button"
                    onClick={() => handleRemoveMedication(index)}
                    disabled={disabled}
                    className="p-1 text-red-400 hover:text-red-600 disabled:opacity-50 disabled:cursor-not-allowed"
                    title="Remove medication"
                    aria-label="Remove medication"
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

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                  {/* Medication Name */}
                  <div>
                    <label
                      htmlFor={`medication${index}Name`}
                      className="block text-sm font-medium text-gray-700"
                    >
                      Medication Name <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      id={`medication${index}Name`}
                      value={medication.name}
                      onChange={handleMedicationChange(index, 'name')}
                      disabled={disabled}
                      placeholder="e.g., Ventolin, Tylenol"
                      className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                    />
                  </div>

                  {/* Dosage */}
                  <div>
                    <label
                      htmlFor={`medication${index}Dosage`}
                      className="block text-sm font-medium text-gray-700"
                    >
                      Dosage <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      id={`medication${index}Dosage`}
                      value={medication.dosage}
                      onChange={handleMedicationChange(index, 'dosage')}
                      disabled={disabled}
                      placeholder="e.g., 5ml, 1 puff, 10mg"
                      className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                    />
                  </div>

                  {/* Schedule */}
                  <div>
                    <label
                      htmlFor={`medication${index}Schedule`}
                      className="block text-sm font-medium text-gray-700"
                    >
                      Schedule <span className="text-red-500">*</span>
                    </label>
                    <input
                      type="text"
                      id={`medication${index}Schedule`}
                      value={medication.schedule}
                      onChange={handleMedicationChange(index, 'schedule')}
                      disabled={disabled}
                      placeholder="e.g., Twice daily, As needed, Before meals"
                      className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                    />
                  </div>

                  {/* Instructions */}
                  <div>
                    <label
                      htmlFor={`medication${index}Instructions`}
                      className="block text-sm font-medium text-gray-700"
                    >
                      Special Instructions
                    </label>
                    <input
                      type="text"
                      id={`medication${index}Instructions`}
                      value={medication.instructions ?? ''}
                      onChange={handleMedicationChange(index, 'instructions')}
                      disabled={disabled}
                      placeholder="e.g., Take with food, Keep refrigerated"
                      className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
                    />
                  </div>
                </div>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-center py-6 bg-gray-50 rounded-lg border-2 border-dashed border-gray-300 mb-4">
            <svg
              className="mx-auto h-10 w-10 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
              />
            </svg>
            <p className="mt-2 text-sm text-gray-600">
              No medications listed. Click below to add if applicable.
            </p>
          </div>
        )}

        {/* Add Medication Button */}
        <button
          type="button"
          onClick={handleAddMedication}
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
          Add Medication
        </button>
      </div>

      {/* ================================================================== */}
      {/* Doctor Information Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Doctor / Physician Information
        </h4>

        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
          {/* Doctor Name */}
          <div>
            <label
              htmlFor="doctorName"
              className="block text-sm font-medium text-gray-700"
            >
              Doctor&apos;s Name
            </label>
            <input
              type="text"
              id="doctorName"
              value={healthData.doctorName ?? ''}
              onChange={handleInputChange('doctorName')}
              disabled={disabled}
              placeholder="Dr. Jean Tremblay"
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
          </div>

          {/* Doctor Phone */}
          <div>
            <label
              htmlFor="doctorPhone"
              className="block text-sm font-medium text-gray-700"
            >
              Doctor&apos;s Phone
            </label>
            <input
              type="tel"
              id="doctorPhone"
              value={healthData.doctorPhone ?? ''}
              onChange={handleInputChange('doctorPhone')}
              disabled={disabled}
              placeholder="(514) 555-0100"
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
          </div>

          {/* Doctor Address */}
          <div className="sm:col-span-2">
            <label
              htmlFor="doctorAddress"
              className="block text-sm font-medium text-gray-700"
            >
              Doctor&apos;s Address / Clinic
            </label>
            <input
              type="text"
              id="doctorAddress"
              value={healthData.doctorAddress ?? ''}
              onChange={handleInputChange('doctorAddress')}
              disabled={disabled}
              placeholder="123 Medical Center Blvd, Montreal, QC"
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
          </div>
        </div>
      </div>

      {/* ================================================================== */}
      {/* Health Insurance Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Health Insurance (RAMQ)
        </h4>

        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
          {/* Health Insurance Number */}
          <div>
            <label
              htmlFor="healthInsuranceNumber"
              className="block text-sm font-medium text-gray-700"
            >
              Health Insurance Number (RAMQ)
            </label>
            <input
              type="text"
              id="healthInsuranceNumber"
              value={healthData.healthInsuranceNumber ?? ''}
              onChange={handleInputChange('healthInsuranceNumber')}
              disabled={disabled}
              placeholder="XXXX 0000 0000"
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
            <p className="mt-1 text-xs text-gray-500">
              Quebec health insurance card number (Carte d&apos;assurance maladie).
            </p>
          </div>

          {/* Health Insurance Expiry */}
          <div>
            <label
              htmlFor="healthInsuranceExpiry"
              className="block text-sm font-medium text-gray-700"
            >
              Card Expiry Date
            </label>
            <input
              type="date"
              id="healthInsuranceExpiry"
              value={healthData.healthInsuranceExpiry ?? ''}
              onChange={handleInputChange('healthInsuranceExpiry')}
              disabled={disabled}
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
            <p className="mt-1 text-xs text-gray-500">
              Please notify us when the card is renewed.
            </p>
          </div>
        </div>
      </div>

      {/* ================================================================== */}
      {/* Special Needs & Development Section */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Special Needs & Development
        </h4>

        <div className="space-y-6">
          {/* Special Needs */}
          <div>
            <label
              htmlFor="specialNeeds"
              className="block text-sm font-medium text-gray-700"
            >
              Special Needs or Accommodations
            </label>
            <textarea
              id="specialNeeds"
              rows={3}
              value={healthData.specialNeeds ?? ''}
              onChange={handleInputChange('specialNeeds')}
              disabled={disabled}
              placeholder="Describe any special needs, learning differences, or accommodations required..."
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
            <p className="mt-1 text-xs text-gray-500">
              Include any diagnoses (e.g., autism, ADHD, speech delays) and recommended accommodations.
            </p>
          </div>

          {/* Developmental Notes */}
          <div>
            <label
              htmlFor="developmentalNotes"
              className="block text-sm font-medium text-gray-700"
            >
              Developmental Notes
            </label>
            <textarea
              id="developmentalNotes"
              rows={3}
              value={healthData.developmentalNotes ?? ''}
              onChange={handleInputChange('developmentalNotes')}
              disabled={disabled}
              placeholder="Any additional notes about the child's development, therapy appointments, or support services..."
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
          </div>
        </div>
      </div>

      {/* ================================================================== */}
      {/* Info Notice */}
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
              <strong>Confidentiality:</strong> All health information is kept
              strictly confidential and is only shared with staff members who
              need to know for the child&apos;s care and safety. Please keep this
              information up-to-date.
            </p>
          </div>
        </div>
      </div>

      {/* Medication Authorization Notice */}
      {(healthData.medications ?? []).length > 0 && (
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
                <strong>Medication Authorization:</strong> A separate medication
                authorization form may be required for staff to administer
                medications. Please provide all medications in their original
                containers with pharmacy labels.
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

export type { HealthSectionProps };
