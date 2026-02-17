'use client';

import { useState, useCallback, useEffect, useMemo } from 'react';
import type {
  EnrollmentForm,
  EnrollmentParent,
  AuthorizedPickup,
  EmergencyContact,
  HealthInfo,
  NutritionInfo,
  AttendancePattern,
  EnrollmentSignature,
  CreateEnrollmentFormRequest,
} from '@/lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Wizard step identifiers.
 */
export type WizardStep =
  | 'child-info'
  | 'parent-1'
  | 'parent-2'
  | 'authorized-pickups'
  | 'emergency-contacts'
  | 'health'
  | 'nutrition'
  | 'attendance'
  | 'signatures'
  | 'review';

/**
 * Step configuration with metadata.
 */
interface StepConfig {
  id: WizardStep;
  title: string;
  description: string;
  isOptional: boolean;
}

/**
 * Child information form data.
 */
export interface ChildInfoData {
  childFirstName: string;
  childLastName: string;
  childDateOfBirth: string;
  childAddress?: string;
  childCity?: string;
  childPostalCode?: string;
  languagesSpoken?: string;
  admissionDate?: string;
  notes?: string;
}

/**
 * Complete form data structure for the wizard.
 */
export interface EnrollmentFormData {
  childInfo: ChildInfoData;
  parent1: Omit<EnrollmentParent, 'id' | 'formId'> | null;
  parent2: Omit<EnrollmentParent, 'id' | 'formId'> | null;
  authorizedPickups: Omit<AuthorizedPickup, 'id' | 'formId'>[];
  emergencyContacts: Omit<EmergencyContact, 'id' | 'formId'>[];
  healthInfo: Omit<HealthInfo, 'id' | 'formId'> | null;
  nutritionInfo: Omit<NutritionInfo, 'id' | 'formId'> | null;
  attendancePattern: Omit<AttendancePattern, 'id' | 'formId'> | null;
  signatures: Omit<EnrollmentSignature, 'id' | 'formId'>[];
}

/**
 * Props for EnrollmentFormWizard component.
 */
interface EnrollmentFormWizardProps {
  /** Initial form data for editing existing form */
  initialData?: Partial<EnrollmentFormData>;
  /** Existing form for edit mode */
  existingForm?: EnrollmentForm;
  /** Person ID for the child */
  personId: string;
  /** Family ID for the enrollment */
  familyId: string;
  /** Callback when form is submitted */
  onSubmit: (data: CreateEnrollmentFormRequest) => Promise<void>;
  /** Callback when form is cancelled */
  onCancel: () => void;
  /** Whether submission is in progress */
  isSubmitting?: boolean;
}

// ============================================================================
// Step Configuration
// ============================================================================

const WIZARD_STEPS: StepConfig[] = [
  {
    id: 'child-info',
    title: 'Child Information',
    description: 'Basic information about the child',
    isOptional: false,
  },
  {
    id: 'parent-1',
    title: 'Parent/Guardian 1',
    description: 'Primary parent or guardian information',
    isOptional: false,
  },
  {
    id: 'parent-2',
    title: 'Parent/Guardian 2',
    description: 'Second parent or guardian (optional)',
    isOptional: true,
  },
  {
    id: 'authorized-pickups',
    title: 'Authorized Pickups',
    description: 'People authorized to pick up the child',
    isOptional: false,
  },
  {
    id: 'emergency-contacts',
    title: 'Emergency Contacts',
    description: 'Emergency contact information',
    isOptional: false,
  },
  {
    id: 'health',
    title: 'Health Information',
    description: 'Medical and health details',
    isOptional: false,
  },
  {
    id: 'nutrition',
    title: 'Nutrition & Dietary',
    description: 'Dietary restrictions and preferences',
    isOptional: false,
  },
  {
    id: 'attendance',
    title: 'Attendance Pattern',
    description: 'Expected weekly attendance schedule',
    isOptional: false,
  },
  {
    id: 'signatures',
    title: 'Signatures',
    description: 'Electronic signatures',
    isOptional: false,
  },
  {
    id: 'review',
    title: 'Review & Submit',
    description: 'Review all information before submission',
    isOptional: false,
  },
];

// ============================================================================
// Initial State
// ============================================================================

const createInitialFormData = (
  initialData?: Partial<EnrollmentFormData>
): EnrollmentFormData => ({
  childInfo: {
    childFirstName: '',
    childLastName: '',
    childDateOfBirth: '',
    childAddress: '',
    childCity: '',
    childPostalCode: '',
    languagesSpoken: '',
    admissionDate: '',
    notes: '',
    ...initialData?.childInfo,
  },
  parent1: initialData?.parent1 ?? null,
  parent2: initialData?.parent2 ?? null,
  authorizedPickups: initialData?.authorizedPickups ?? [],
  emergencyContacts: initialData?.emergencyContacts ?? [],
  healthInfo: initialData?.healthInfo ?? null,
  nutritionInfo: initialData?.nutritionInfo ?? null,
  attendancePattern: initialData?.attendancePattern ?? null,
  signatures: initialData?.signatures ?? [],
});

// ============================================================================
// Component
// ============================================================================

export function EnrollmentFormWizard({
  initialData,
  existingForm,
  personId,
  familyId,
  onSubmit,
  onCancel,
  isSubmitting = false,
}: EnrollmentFormWizardProps) {
  // ---------------------------------------------------------------------------
  // State
  // ---------------------------------------------------------------------------

  const [currentStepIndex, setCurrentStepIndex] = useState(0);
  const [formData, setFormData] = useState<EnrollmentFormData>(() =>
    createInitialFormData(initialData)
  );
  const [stepErrors, setStepErrors] = useState<Record<WizardStep, string[]>>({
    'child-info': [],
    'parent-1': [],
    'parent-2': [],
    'authorized-pickups': [],
    'emergency-contacts': [],
    health: [],
    nutrition: [],
    attendance: [],
    signatures: [],
    review: [],
  });
  const [visitedSteps, setVisitedSteps] = useState<Set<WizardStep>>(
    new Set(['child-info'])
  );
  const [isValidating, setIsValidating] = useState(false);

  // ---------------------------------------------------------------------------
  // Computed Values
  // ---------------------------------------------------------------------------

  const currentStep = useMemo(
    () => WIZARD_STEPS[currentStepIndex],
    [currentStepIndex]
  );

  const isFirstStep = currentStepIndex === 0;
  const isLastStep = currentStepIndex === WIZARD_STEPS.length - 1;

  const progress = useMemo(
    () => ((currentStepIndex + 1) / WIZARD_STEPS.length) * 100,
    [currentStepIndex]
  );

  // ---------------------------------------------------------------------------
  // Validation
  // ---------------------------------------------------------------------------

  const validateStep = useCallback(
    (stepId: WizardStep): string[] => {
      const errors: string[] = [];

      switch (stepId) {
        case 'child-info':
          if (!formData.childInfo.childFirstName.trim()) {
            errors.push('First name is required');
          }
          if (!formData.childInfo.childLastName.trim()) {
            errors.push('Last name is required');
          }
          if (!formData.childInfo.childDateOfBirth) {
            errors.push('Date of birth is required');
          }
          break;

        case 'parent-1':
          if (!formData.parent1) {
            errors.push('Primary parent/guardian information is required');
          } else {
            if (!formData.parent1.name.trim()) {
              errors.push('Parent name is required');
            }
            if (!formData.parent1.relationship.trim()) {
              errors.push('Relationship is required');
            }
            if (!formData.parent1.cellPhone && !formData.parent1.homePhone) {
              errors.push('At least one phone number is required');
            }
          }
          break;

        case 'parent-2':
          // Parent 2 is optional, but if provided must be valid
          if (formData.parent2) {
            if (!formData.parent2.name.trim()) {
              errors.push('Parent name is required when adding second parent');
            }
          }
          break;

        case 'authorized-pickups':
          // At least one authorized pickup is recommended
          if (formData.authorizedPickups.length === 0) {
            errors.push('At least one authorized pickup person is recommended');
          }
          break;

        case 'emergency-contacts':
          if (formData.emergencyContacts.length < 2) {
            errors.push('At least 2 emergency contacts are required');
          }
          formData.emergencyContacts.forEach((contact, index) => {
            if (!contact.name.trim()) {
              errors.push(`Emergency contact ${index + 1}: Name is required`);
            }
            if (!contact.phone.trim()) {
              errors.push(`Emergency contact ${index + 1}: Phone is required`);
            }
          });
          break;

        case 'health':
          // Health info validation - basic checks
          break;

        case 'nutrition':
          // Nutrition info validation - basic checks
          break;

        case 'attendance':
          if (!formData.attendancePattern) {
            errors.push('Attendance pattern is required');
          }
          break;

        case 'signatures':
          // At least parent 1 signature is required
          const hasParent1Sig = formData.signatures.some(
            (s) => s.signatureType === 'Parent1'
          );
          if (!hasParent1Sig) {
            errors.push('Primary parent/guardian signature is required');
          }
          break;

        case 'review':
          // Aggregate all validation errors for review
          break;
      }

      return errors;
    },
    [formData]
  );

  const validateAllSteps = useCallback((): boolean => {
    const allErrors: Record<WizardStep, string[]> = {} as Record<
      WizardStep,
      string[]
    >;
    let hasErrors = false;

    WIZARD_STEPS.forEach((step) => {
      if (!step.isOptional || step.id === 'parent-2') {
        const errors = validateStep(step.id);
        allErrors[step.id] = errors;
        if (errors.length > 0 && !step.isOptional) {
          hasErrors = true;
        }
      } else {
        allErrors[step.id] = [];
      }
    });

    setStepErrors(allErrors);
    return !hasErrors;
  }, [validateStep]);

  // ---------------------------------------------------------------------------
  // Navigation Handlers
  // ---------------------------------------------------------------------------

  const goToStep = useCallback(
    (stepIndex: number) => {
      if (stepIndex >= 0 && stepIndex < WIZARD_STEPS.length) {
        setCurrentStepIndex(stepIndex);
        setVisitedSteps((prev) => new Set([...prev, WIZARD_STEPS[stepIndex].id]));
      }
    },
    []
  );

  const goNext = useCallback(() => {
    setIsValidating(true);
    const errors = validateStep(currentStep.id);
    setStepErrors((prev) => ({ ...prev, [currentStep.id]: errors }));

    // Allow navigation even with warnings for optional steps
    if (errors.length === 0 || currentStep.isOptional) {
      if (!isLastStep) {
        goToStep(currentStepIndex + 1);
      }
    }
    setIsValidating(false);
  }, [currentStep, currentStepIndex, isLastStep, validateStep, goToStep]);

  const goPrevious = useCallback(() => {
    if (!isFirstStep) {
      goToStep(currentStepIndex - 1);
    }
  }, [currentStepIndex, isFirstStep, goToStep]);

  // ---------------------------------------------------------------------------
  // Form Data Handlers
  // ---------------------------------------------------------------------------

  const updateChildInfo = useCallback((data: Partial<ChildInfoData>) => {
    setFormData((prev) => ({
      ...prev,
      childInfo: { ...prev.childInfo, ...data },
    }));
  }, []);

  const updateParent1 = useCallback(
    (data: Omit<EnrollmentParent, 'id' | 'formId'> | null) => {
      setFormData((prev) => ({ ...prev, parent1: data }));
    },
    []
  );

  const updateParent2 = useCallback(
    (data: Omit<EnrollmentParent, 'id' | 'formId'> | null) => {
      setFormData((prev) => ({ ...prev, parent2: data }));
    },
    []
  );

  const updateAuthorizedPickups = useCallback(
    (data: Omit<AuthorizedPickup, 'id' | 'formId'>[]) => {
      setFormData((prev) => ({ ...prev, authorizedPickups: data }));
    },
    []
  );

  const updateEmergencyContacts = useCallback(
    (data: Omit<EmergencyContact, 'id' | 'formId'>[]) => {
      setFormData((prev) => ({ ...prev, emergencyContacts: data }));
    },
    []
  );

  const updateHealthInfo = useCallback(
    (data: Omit<HealthInfo, 'id' | 'formId'> | null) => {
      setFormData((prev) => ({ ...prev, healthInfo: data }));
    },
    []
  );

  const updateNutritionInfo = useCallback(
    (data: Omit<NutritionInfo, 'id' | 'formId'> | null) => {
      setFormData((prev) => ({ ...prev, nutritionInfo: data }));
    },
    []
  );

  const updateAttendancePattern = useCallback(
    (data: Omit<AttendancePattern, 'id' | 'formId'> | null) => {
      setFormData((prev) => ({ ...prev, attendancePattern: data }));
    },
    []
  );

  const updateSignatures = useCallback(
    (data: Omit<EnrollmentSignature, 'id' | 'formId'>[]) => {
      setFormData((prev) => ({ ...prev, signatures: data }));
    },
    []
  );

  // ---------------------------------------------------------------------------
  // Submit Handler
  // ---------------------------------------------------------------------------

  const handleSubmit = useCallback(async () => {
    if (!validateAllSteps()) {
      return;
    }

    const parents: Omit<EnrollmentParent, 'id' | 'formId'>[] = [];
    if (formData.parent1) {
      parents.push({ ...formData.parent1, parentNumber: '1' });
    }
    if (formData.parent2) {
      parents.push({ ...formData.parent2, parentNumber: '2' });
    }

    const request: CreateEnrollmentFormRequest = {
      personId,
      familyId,
      ...formData.childInfo,
      parents,
      authorizedPickups: formData.authorizedPickups,
      emergencyContacts: formData.emergencyContacts,
      healthInfo: formData.healthInfo ?? undefined,
      nutritionInfo: formData.nutritionInfo ?? undefined,
      attendancePattern: formData.attendancePattern ?? undefined,
    };

    await onSubmit(request);
  }, [formData, personId, familyId, onSubmit, validateAllSteps]);

  // ---------------------------------------------------------------------------
  // Keyboard Navigation
  // ---------------------------------------------------------------------------

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !isSubmitting) {
        onCancel();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [isSubmitting, onCancel]);

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div className="enrollment-wizard min-h-screen bg-gray-50">
      {/* Header */}
      <div className="bg-white border-b border-gray-200 sticky top-0 z-10">
        <div className="max-w-4xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-xl font-semibold text-gray-900">
                {existingForm ? 'Edit Enrollment Form' : 'New Enrollment Form'}
              </h1>
              <p className="mt-1 text-sm text-gray-500">
                {currentStep.title} - {currentStep.description}
              </p>
            </div>
            <button
              type="button"
              onClick={onCancel}
              disabled={isSubmitting}
              className="rounded-full p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-50"
              aria-label="Cancel"
            >
              <svg
                className="h-6 w-6"
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

          {/* Progress Bar */}
          <div className="mt-4">
            <div className="flex justify-between text-xs text-gray-500 mb-1">
              <span>
                Step {currentStepIndex + 1} of {WIZARD_STEPS.length}
              </span>
              <span>{Math.round(progress)}% complete</span>
            </div>
            <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
              <div
                className="h-full bg-primary-600 transition-all duration-300 ease-out"
                style={{ width: `${progress}%` }}
              />
            </div>
          </div>

          {/* Step Indicators */}
          <div className="mt-4 flex items-center justify-center space-x-1 overflow-x-auto pb-2">
            {WIZARD_STEPS.map((step, index) => {
              const isActive = index === currentStepIndex;
              const isCompleted = index < currentStepIndex;
              const hasErrors = stepErrors[step.id]?.length > 0;
              const isVisited = visitedSteps.has(step.id);

              return (
                <button
                  key={step.id}
                  type="button"
                  onClick={() => goToStep(index)}
                  disabled={isSubmitting}
                  className={`
                    flex items-center justify-center w-8 h-8 rounded-full text-xs font-medium
                    transition-all duration-200 flex-shrink-0
                    ${
                      isActive
                        ? 'bg-primary-600 text-white ring-2 ring-primary-200'
                        : isCompleted
                          ? 'bg-green-500 text-white'
                          : hasErrors && isVisited
                            ? 'bg-red-100 text-red-600 border border-red-300'
                            : 'bg-gray-200 text-gray-600 hover:bg-gray-300'
                    }
                    disabled:cursor-not-allowed
                  `}
                  title={step.title}
                >
                  {isCompleted ? (
                    <svg
                      className="w-4 h-4"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path
                        fillRule="evenodd"
                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                        clipRule="evenodd"
                      />
                    </svg>
                  ) : (
                    index + 1
                  )}
                </button>
              );
            })}
          </div>
        </div>
      </div>

      {/* Content Area */}
      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
          {/* Step Errors */}
          {stepErrors[currentStep.id]?.length > 0 && (
            <div className="bg-red-50 border-b border-red-200 px-6 py-4">
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
                  <h3 className="text-sm font-medium text-red-800">
                    Please correct the following:
                  </h3>
                  <ul className="mt-1 text-sm text-red-700 list-disc list-inside">
                    {stepErrors[currentStep.id].map((error, index) => (
                      <li key={index}>{error}</li>
                    ))}
                  </ul>
                </div>
              </div>
            </div>
          )}

          {/* Step Content Placeholder */}
          <div className="px-6 py-8">
            <div className="text-center text-gray-500">
              <p className="text-lg font-medium">{currentStep.title}</p>
              <p className="mt-2 text-sm">{currentStep.description}</p>
              <p className="mt-4 text-xs text-gray-400">
                Step content component will be rendered here
              </p>
              {currentStep.isOptional && (
                <span className="inline-block mt-2 px-2 py-1 bg-gray-100 text-gray-600 text-xs rounded">
                  Optional Step
                </span>
              )}
            </div>
          </div>

          {/* Footer Navigation */}
          <div className="border-t border-gray-200 px-6 py-4 bg-gray-50">
            <div className="flex items-center justify-between">
              <button
                type="button"
                onClick={goPrevious}
                disabled={isFirstStep || isSubmitting}
                className="btn btn-outline disabled:opacity-50 disabled:cursor-not-allowed"
              >
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
                    d="M15 19l-7-7 7-7"
                  />
                </svg>
                Previous
              </button>

              <div className="flex items-center space-x-3">
                {currentStep.isOptional && (
                  <button
                    type="button"
                    onClick={goNext}
                    disabled={isSubmitting}
                    className="text-sm text-gray-500 hover:text-gray-700 underline"
                  >
                    Skip this step
                  </button>
                )}

                {isLastStep ? (
                  <button
                    type="button"
                    onClick={handleSubmit}
                    disabled={isSubmitting}
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
                        Submitting...
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
                        Submit Enrollment
                      </>
                    )}
                  </button>
                ) : (
                  <button
                    type="button"
                    onClick={goNext}
                    disabled={isSubmitting || isValidating}
                    className="btn btn-primary"
                  >
                    Next
                    <svg
                      className="ml-2 h-4 w-4"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M9 5l7 7-7 7"
                      />
                    </svg>
                  </button>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}

// ============================================================================
// Exports
// ============================================================================

export { WIZARD_STEPS };
export type { StepConfig, EnrollmentFormWizardProps };
