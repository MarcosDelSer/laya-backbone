'use client';

import { useState, useCallback, useEffect } from 'react';
import { SignatureCanvas } from './SignatureCanvas';
import { signInterventionPlan, getErrorMessage } from '../lib/intervention-plan-client';
import type {
  InterventionPlan,
  InterventionPlanSummary,
  SignInterventionPlanResponse,
} from '../lib/types';

/**
 * Plan data required for signature workflow.
 * Accepts either a full plan or a plan summary.
 */
type SignablePlan = Pick<
  InterventionPlan | InterventionPlanSummary,
  'id' | 'title' | 'childName' | 'status' | 'parentSigned'
>;

interface ParentSignatureProps {
  /** The intervention plan to sign */
  plan: SignablePlan | null;
  /** Whether the modal is open */
  isOpen: boolean;
  /** Callback when modal is closed */
  onClose: () => void;
  /** Callback when signature is successfully submitted */
  onSuccess: (response: SignInterventionPlanResponse) => void;
  /** Optional callback for submission errors */
  onError?: (error: string) => void;
}

/**
 * ParentSignature component for signing intervention plans.
 *
 * Provides a modal interface for parents to review and sign
 * intervention plans with a digital signature canvas.
 *
 * Features:
 * - Plan information display
 * - Digital signature canvas
 * - Terms agreement checkbox
 * - Timestamp notification
 * - Error handling with user feedback
 * - Escape key and backdrop click to close
 * - Loading state during submission
 */
export function ParentSignature({
  plan,
  isOpen,
  onClose,
  onSuccess,
  onError,
}: ParentSignatureProps) {
  const [hasSignature, setHasSignature] = useState(false);
  const [signatureDataUrl, setSignatureDataUrl] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [agreedToTerms, setAgreedToTerms] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Reset state when modal opens/closes
  useEffect(() => {
    if (!isOpen) {
      setHasSignature(false);
      setSignatureDataUrl(null);
      setIsSubmitting(false);
      setAgreedToTerms(false);
      setError(null);
    }
  }, [isOpen]);

  // Handle signature change from canvas
  const handleSignatureChange = useCallback(
    (hasSig: boolean, dataUrl: string | null) => {
      setHasSignature(hasSig);
      setSignatureDataUrl(dataUrl);
    },
    []
  );

  // Handle form submission
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!plan || !signatureDataUrl || !agreedToTerms) return;

    setIsSubmitting(true);
    setError(null);

    try {
      const response = await signInterventionPlan(plan.id, {
        signatureData: signatureDataUrl,
        agreedToTerms: true,
      });
      onSuccess(response);
    } catch (err) {
      const errorMessage = getErrorMessage(err);
      setError(errorMessage);
      onError?.(errorMessage);
    } finally {
      setIsSubmitting(false);
    }
  };

  // Handle escape key and body scroll lock
  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !isSubmitting) {
        onClose();
      }
    };

    if (isOpen) {
      window.addEventListener('keydown', handleEscape);
      // Prevent body scroll when modal is open
      document.body.style.overflow = 'hidden';
    }

    return () => {
      window.removeEventListener('keydown', handleEscape);
      document.body.style.overflow = 'unset';
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen, isSubmitting, onClose]);

  if (!isOpen || !plan) return null;

  // Check if plan is already signed
  if (plan.parentSigned) {
    return (
      <div className="fixed inset-0 z-50 overflow-y-auto">
        {/* Backdrop */}
        <div
          className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
          onClick={onClose}
        />

        {/* Modal */}
        <div className="flex min-h-full items-center justify-center p-4">
          <div className="relative w-full max-w-md transform rounded-xl bg-white shadow-2xl transition-all">
            <div className="px-6 py-8 text-center">
              <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-100 mb-4">
                <svg
                  className="h-6 w-6 text-green-600"
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
              <h3 className="text-lg font-semibold text-gray-900 mb-2">
                Already Signed
              </h3>
              <p className="text-sm text-gray-500 mb-6">
                This intervention plan has already been signed.
              </p>
              <button
                type="button"
                onClick={onClose}
                className="btn btn-primary"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      </div>
    );
  }

  const canSubmit = hasSignature && agreedToTerms && !isSubmitting;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
        onClick={!isSubmitting ? onClose : undefined}
      />

      {/* Modal */}
      <div className="flex min-h-full items-center justify-center p-4">
        <div className="relative w-full max-w-lg transform rounded-xl bg-white shadow-2xl transition-all">
          {/* Header */}
          <div className="border-b border-gray-200 px-6 py-4">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-lg font-semibold text-gray-900">
                  Sign Intervention Plan
                </h2>
                <p className="mt-1 text-sm text-gray-500">{plan.title}</p>
              </div>
              <button
                type="button"
                onClick={onClose}
                disabled={isSubmitting}
                className="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-50"
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
          </div>

          {/* Content */}
          <form onSubmit={handleSubmit}>
            <div className="px-6 py-4">
              {/* Error message */}
              {error && (
                <div className="mb-4 rounded-lg bg-red-50 p-4">
                  <div className="flex">
                    <svg
                      className="h-5 w-5 text-red-400 flex-shrink-0"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                      />
                    </svg>
                    <p className="ml-3 text-sm text-red-700">{error}</p>
                  </div>
                </div>
              )}

              {/* Plan info card */}
              <div className="mb-6 rounded-lg bg-gray-50 p-4">
                <div className="flex items-start space-x-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100 flex-shrink-0">
                    <svg
                      className="h-5 w-5 text-primary-600"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                      />
                    </svg>
                  </div>
                  <div className="flex-1 min-w-0">
                    <p className="text-sm font-medium text-gray-900 truncate">
                      {plan.title}
                    </p>
                    <p className="text-xs text-gray-500">
                      For: {plan.childName}
                    </p>
                    <p className="mt-1 text-xs text-gray-400">
                      Status:{' '}
                      <span className="capitalize">
                        {plan.status.replace('_', ' ')}
                      </span>
                    </p>
                  </div>
                </div>
              </div>

              {/* Information notice */}
              <div className="mb-6 rounded-lg bg-blue-50 p-4">
                <div className="flex">
                  <svg
                    className="h-5 w-5 text-blue-400 flex-shrink-0"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                  <div className="ml-3">
                    <p className="text-sm text-blue-700">
                      Please review the intervention plan carefully before
                      signing. Your signature indicates that you have read and
                      agree to the goals, strategies, and monitoring approaches
                      outlined in this plan.
                    </p>
                  </div>
                </div>
              </div>

              {/* Signature canvas */}
              <div className="mb-6">
                <label className="block text-sm font-medium text-gray-700 mb-3">
                  Your Signature
                </label>
                <SignatureCanvas
                  onSignatureChange={handleSignatureChange}
                  width={400}
                  height={150}
                />
              </div>

              {/* Agreement checkbox */}
              <div className="mb-4">
                <label className="flex items-start space-x-3">
                  <input
                    type="checkbox"
                    checked={agreedToTerms}
                    onChange={(e) => setAgreedToTerms(e.target.checked)}
                    disabled={isSubmitting}
                    className="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  />
                  <span className="text-sm text-gray-600">
                    I have reviewed this intervention plan and agree to support
                    its implementation. I understand that my involvement is
                    essential for my child&apos;s progress and commit to the
                    parent involvement activities outlined in the plan.
                  </span>
                </label>
              </div>

              {/* Timestamp notice */}
              <p className="text-xs text-gray-400">
                Your signature will be timestamped with the current date and
                time for verification purposes.
              </p>
            </div>

            {/* Footer */}
            <div className="border-t border-gray-200 px-6 py-4">
              <div className="flex items-center justify-end space-x-3">
                <button
                  type="button"
                  onClick={onClose}
                  disabled={isSubmitting}
                  className="btn btn-outline"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={!canSubmit}
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
                      Signing...
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
                      Sign Plan
                    </>
                  )}
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}

/**
 * Success state component for displaying post-signature confirmation.
 * Can be used as a standalone component or within a parent component.
 */
export interface SignatureSuccessProps {
  plan: SignablePlan;
  response: SignInterventionPlanResponse;
  onClose: () => void;
}

export function SignatureSuccess({
  plan,
  response,
  onClose,
}: SignatureSuccessProps) {
  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
        onClick={onClose}
      />

      {/* Modal */}
      <div className="flex min-h-full items-center justify-center p-4">
        <div className="relative w-full max-w-md transform rounded-xl bg-white shadow-2xl transition-all">
          <div className="px-6 py-8 text-center">
            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-100 mb-4">
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
            <h3 className="text-lg font-semibold text-gray-900 mb-2">
              Plan Signed Successfully
            </h3>
            <p className="text-sm text-gray-500 mb-2">
              {plan.title}
            </p>
            <p className="text-xs text-gray-400 mb-6">
              Signed on {new Date(response.parentSignatureDate).toLocaleDateString()}
              {' at '}
              {new Date(response.parentSignatureDate).toLocaleTimeString()}
            </p>
            <p className="text-sm text-gray-600 mb-6">
              Thank you for signing the intervention plan for {plan.childName}.
              Your commitment to supporting this plan is greatly appreciated.
            </p>
            <button
              type="button"
              onClick={onClose}
              className="btn btn-primary"
            >
              Done
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
