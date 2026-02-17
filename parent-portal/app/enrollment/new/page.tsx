'use client';

import { useState, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import {
  EnrollmentFormWizard,
} from '@/components/enrollment/EnrollmentFormWizard';
import type { CreateEnrollmentFormRequest, EnrollmentForm } from '@/lib/types';

// ============================================================================
// Mock Data - will be replaced with API calls or context
// ============================================================================

// In production, these would come from user context/session
const MOCK_PERSON_ID = 'person-new-1';
const MOCK_FAMILY_ID = 'family-1';

// ============================================================================
// Page Component
// ============================================================================

export default function NewEnrollmentPage() {
  const router = useRouter();

  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState(false);
  const [createdForm, setCreatedForm] = useState<EnrollmentForm | null>(null);

  // Handle form submission
  const handleSubmit = useCallback(
    async (data: CreateEnrollmentFormRequest) => {
      setIsSubmitting(true);
      setSubmitError(null);

      try {
        // In production, this would call:
        // const form = await createEnrollmentForm(data);

        // Simulate API call
        await new Promise((resolve) => setTimeout(resolve, 1500));

        // Mock response
        const mockCreatedForm: EnrollmentForm = {
          id: `enroll-${Date.now()}`,
          personId: data.personId,
          familyId: data.familyId,
          schoolYearId: 'sy-2024',
          formNumber: `ENR-2024-${String(Math.floor(Math.random() * 1000)).padStart(3, '0')}`,
          status: 'Draft',
          version: 1,
          admissionDate: data.admissionDate,
          childFirstName: data.childFirstName,
          childLastName: data.childLastName,
          childDateOfBirth: data.childDateOfBirth,
          childAddress: data.childAddress,
          childCity: data.childCity,
          childPostalCode: data.childPostalCode,
          languagesSpoken: data.languagesSpoken,
          notes: data.notes,
          createdById: 'current-user-id',
          createdAt: new Date().toISOString(),
          updatedAt: new Date().toISOString(),
        };

        setCreatedForm(mockCreatedForm);
        setSubmitSuccess(true);

        // Redirect to the created form's detail page after a short delay
        setTimeout(() => {
          router.push(`/enrollment/${mockCreatedForm.id}`);
        }, 2000);
      } catch (err) {
        setSubmitError(
          err instanceof Error
            ? err.message
            : 'Failed to create enrollment form. Please try again.'
        );
        setIsSubmitting(false);
      }
    },
    [router]
  );

  // Handle cancellation
  const handleCancel = useCallback(() => {
    router.push('/enrollment');
  }, [router]);

  // Show success state
  if (submitSuccess && createdForm) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
        <div className="max-w-md w-full">
          <div className="bg-white rounded-xl shadow-lg p-8 text-center">
            {/* Success Icon */}
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 mb-6">
              <svg
                className="h-8 w-8 text-green-600"
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
            </div>

            {/* Success Message */}
            <h2 className="text-xl font-semibold text-gray-900 mb-2">
              Enrollment Form Created!
            </h2>
            <p className="text-sm text-gray-600 mb-4">
              Your enrollment form for{' '}
              <span className="font-medium">
                {createdForm.childFirstName} {createdForm.childLastName}
              </span>{' '}
              has been saved as a draft.
            </p>

            {/* Form Number */}
            <div className="bg-gray-50 rounded-lg p-3 mb-6">
              <p className="text-xs text-gray-500">Form Number</p>
              <p className="text-lg font-mono font-semibold text-gray-900">
                {createdForm.formNumber}
              </p>
            </div>

            {/* Status Badge */}
            <div className="flex justify-center mb-6">
              <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                <svg
                  className="mr-1.5 h-4 w-4 text-gray-500"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                  />
                </svg>
                Draft
              </span>
            </div>

            {/* Next Steps */}
            <div className="text-left bg-blue-50 rounded-lg p-4 mb-6">
              <h3 className="text-sm font-semibold text-blue-800 mb-2">
                Next Steps
              </h3>
              <ul className="text-sm text-blue-700 space-y-1.5">
                <li className="flex items-start">
                  <svg
                    className="mr-2 h-4 w-4 mt-0.5 flex-shrink-0"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clipRule="evenodd"
                    />
                  </svg>
                  Review all information for accuracy
                </li>
                <li className="flex items-start">
                  <svg
                    className="mr-2 h-4 w-4 mt-0.5 flex-shrink-0"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clipRule="evenodd"
                    />
                  </svg>
                  Add e-signatures from all required parties
                </li>
                <li className="flex items-start">
                  <svg
                    className="mr-2 h-4 w-4 mt-0.5 flex-shrink-0"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clipRule="evenodd"
                    />
                  </svg>
                  Submit the form for approval
                </li>
              </ul>
            </div>

            {/* Redirecting message */}
            <div className="flex items-center justify-center text-sm text-gray-500">
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
              Redirecting to your form...
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Show error state with retry option
  if (submitError) {
    return (
      <div className="min-h-screen bg-gray-50 flex items-center justify-center px-4">
        <div className="max-w-md w-full">
          <div className="bg-white rounded-xl shadow-lg p-8 text-center">
            {/* Error Icon */}
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100 mb-6">
              <svg
                className="h-8 w-8 text-red-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                />
              </svg>
            </div>

            {/* Error Message */}
            <h2 className="text-xl font-semibold text-gray-900 mb-2">
              Submission Failed
            </h2>
            <p className="text-sm text-gray-600 mb-6">{submitError}</p>

            {/* Action Buttons */}
            <div className="flex flex-col sm:flex-row gap-3 justify-center">
              <button
                type="button"
                onClick={() => {
                  setSubmitError(null);
                  setIsSubmitting(false);
                }}
                className="btn btn-primary"
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
                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                  />
                </svg>
                Try Again
              </button>
              <Link href="/enrollment" className="btn btn-outline">
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
                    d="M10 19l-7-7m0 0l7-7m-7 7h18"
                  />
                </svg>
                Back to Enrollment List
              </Link>
            </div>
          </div>
        </div>
      </div>
    );
  }

  // Main wizard view
  return (
    <EnrollmentFormWizard
      personId={MOCK_PERSON_ID}
      familyId={MOCK_FAMILY_ID}
      onSubmit={handleSubmit}
      onCancel={handleCancel}
      isSubmitting={isSubmitting}
    />
  );
}
