'use client';

import { useState, useCallback, useEffect } from 'react';
import { SignatureCanvas } from './SignatureCanvas';
import { WeightInput } from './WeightInput';
import type {
  MedicalProtocol,
  ProtocolType,
  CreateProtocolAuthorizationRequest,
} from '@/lib/types';

interface ProtocolAuthorizationFormProps {
  protocol: MedicalProtocol | null;
  childId: string;
  childName: string;
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (request: CreateProtocolAuthorizationRequest) => void;
}

/**
 * Get the agreement text for a specific protocol type.
 */
function getAgreementText(protocol: MedicalProtocol): string {
  switch (protocol.type) {
    case 'medication':
      return `I, as the parent/guardian, hereby authorize the childcare staff to administer acetaminophen to my child in accordance with Quebec protocol ${protocol.formCode}. I understand that:
• Medication will only be given when my child exhibits symptoms of fever (38°C or higher) or pain
• Dosing will be calculated based on my child's current weight using the approved dosing chart
• A minimum of 4 hours will be maintained between doses, with a maximum of 5 doses in 24 hours
• Staff will follow up with me within 60 minutes of administration
• I will be notified of each administration and must acknowledge receipt
• Weight must be updated every 3 months for accurate dosing`;
    case 'topical':
      return `I, as the parent/guardian, hereby authorize the childcare staff to apply insect repellent to my child in accordance with Quebec protocol ${protocol.formCode}. I understand that:
• Repellent will only be applied when outdoor activities are planned and insects are present
• Application will be limited to exposed skin areas as per protocol guidelines
• My child must be at least 6 months of age for insect repellent application
• Staff will use DEET-based products (up to 10% concentration) as approved for children
• I will be notified when repellent is applied to my child`;
    default:
      return `I, as the parent/guardian, hereby authorize the childcare staff to administer this protocol to my child in accordance with the applicable guidelines.`;
  }
}

/**
 * Get the protocol type icon.
 */
function getProtocolTypeIcon(type: ProtocolType): React.ReactNode {
  switch (type) {
    case 'medication':
      return (
        <svg
          className="h-6 w-6 text-red-600"
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
      );
    case 'topical':
      return (
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
            d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"
          />
        </svg>
      );
    default:
      return (
        <svg
          className="h-6 w-6 text-gray-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
          />
        </svg>
      );
  }
}

/**
 * Get the protocol type background color class.
 */
function getProtocolTypeBgColor(type: ProtocolType): string {
  switch (type) {
    case 'medication':
      return 'bg-red-100';
    case 'topical':
      return 'bg-green-100';
    default:
      return 'bg-gray-100';
  }
}

export function ProtocolAuthorizationForm({
  protocol,
  childId,
  childName,
  isOpen,
  onClose,
  onSubmit,
}: ProtocolAuthorizationFormProps) {
  const [hasSignature, setHasSignature] = useState(false);
  const [signatureDataUrl, setSignatureDataUrl] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [agreedToTerms, setAgreedToTerms] = useState(false);
  const [weightKg, setWeightKg] = useState<number | null>(null);
  const [isWeightValid, setIsWeightValid] = useState(false);

  // Reset state when modal opens/closes
  useEffect(() => {
    if (!isOpen) {
      setHasSignature(false);
      setSignatureDataUrl(null);
      setIsSubmitting(false);
      setAgreedToTerms(false);
      setWeightKg(null);
      setIsWeightValid(false);
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

  // Handle weight change from weight input
  const handleWeightChange = useCallback(
    (weight: number | null, isValid: boolean) => {
      setWeightKg(weight);
      setIsWeightValid(isValid);
    },
    []
  );

  // Handle form submission
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!protocol || !signatureDataUrl || !agreedToTerms) return;

    // For medication protocols, weight is required
    if (protocol.requiresWeight && (!weightKg || !isWeightValid)) return;

    setIsSubmitting(true);

    try {
      const agreementText = getAgreementText(protocol);

      const request: CreateProtocolAuthorizationRequest = {
        childId,
        protocolId: protocol.id,
        weightKg: weightKg || 0,
        signatureData: signatureDataUrl,
        agreementText,
      };

      // Simulate API call delay
      await new Promise((resolve) => setTimeout(resolve, 1000));
      onSubmit(request);
    } catch {
      // Error handling would go here
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

  if (!isOpen || !protocol) return null;

  // Determine if the form can be submitted
  const weightRequirementMet = !protocol.requiresWeight || (weightKg && isWeightValid);
  const canSubmit = hasSignature && agreedToTerms && weightRequirementMet && !isSubmitting;

  const agreementText = getAgreementText(protocol);

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
        onClick={!isSubmitting ? onClose : undefined}
      />

      {/* Modal */}
      <div className="flex min-h-full items-center justify-center p-4">
        <div className="relative w-full max-w-2xl transform rounded-xl bg-white shadow-2xl transition-all">
          {/* Header */}
          <div className="border-b border-gray-200 px-6 py-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-3">
                <div className={`flex h-10 w-10 items-center justify-center rounded-full ${getProtocolTypeBgColor(protocol.type)}`}>
                  {getProtocolTypeIcon(protocol.type)}
                </div>
                <div>
                  <h2 className="text-lg font-semibold text-gray-900">
                    Sign Authorization
                  </h2>
                  <p className="mt-1 text-sm text-gray-500">
                    {protocol.name} ({protocol.formCode})
                  </p>
                </div>
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
            <div className="px-6 py-4 max-h-[60vh] overflow-y-auto">
              {/* Child info */}
              <div className="mb-6 rounded-lg bg-blue-50 p-4">
                <div className="flex items-center space-x-3">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100">
                    <svg
                      className="h-5 w-5 text-blue-600"
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
                  <div>
                    <p className="text-sm font-medium text-blue-900">
                      Authorizing for: {childName}
                    </p>
                    <p className="text-xs text-blue-600">
                      Protocol: {protocol.description}
                    </p>
                  </div>
                </div>
              </div>

              {/* Agreement text */}
              <div className="mb-6">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Authorization Agreement
                </label>
                <div className="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700 whitespace-pre-line max-h-48 overflow-y-auto">
                  {agreementText}
                </div>
              </div>

              {/* Weight input - only show for protocols requiring weight */}
              {protocol.requiresWeight && (
                <div className="mb-6">
                  <WeightInput
                    onWeightChange={handleWeightChange}
                    disabled={isSubmitting}
                    label={`${childName}'s Current Weight`}
                    helpText="Enter your child's current weight for accurate dosing calculations"
                    showValidation={true}
                  />
                </div>
              )}

              {/* Signature canvas */}
              <div className="mb-6">
                <label className="block text-sm font-medium text-gray-700 mb-3">
                  Parent/Guardian Signature
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
                    I have read and agree to the terms of this authorization. By signing
                    below, I confirm that I am the parent or legal guardian of {childName}{' '}
                    and I authorize the childcare staff to administer the{' '}
                    {protocol.type === 'medication' ? 'medication' : 'treatment'} as described
                    above.
                  </span>
                </label>
              </div>

              {/* Timestamp notice */}
              <p className="text-xs text-gray-400">
                Your signature will be timestamped with the current date and time for
                verification purposes. This authorization will be stored securely in
                compliance with Quebec childcare regulations.
              </p>
            </div>

            {/* Footer */}
            <div className="border-t border-gray-200 px-6 py-4">
              <div className="flex items-center justify-between">
                {/* Validation summary */}
                <div className="text-xs text-gray-500">
                  {!agreedToTerms && (
                    <span className="text-red-500">Please agree to the terms</span>
                  )}
                  {agreedToTerms && !hasSignature && (
                    <span className="text-red-500">Please provide your signature</span>
                  )}
                  {agreedToTerms && hasSignature && protocol.requiresWeight && !isWeightValid && (
                    <span className="text-red-500">Please enter a valid weight</span>
                  )}
                  {canSubmit && (
                    <span className="text-green-600">Ready to submit</span>
                  )}
                </div>

                <div className="flex items-center space-x-3">
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
                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                          />
                        </svg>
                        Sign Authorization
                      </>
                    )}
                  </button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  );
}
