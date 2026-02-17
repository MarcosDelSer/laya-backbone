'use client';

import { useState, useCallback, useEffect } from 'react';
import { SignatureCanvas } from './SignatureCanvas';
import type {
  ServiceAgreement,
  ServiceAgreementAnnex,
  SignServiceAgreementRequest,
} from '../lib/types';

// ============================================================================
// Types
// ============================================================================

interface ServiceAgreementSignatureProps {
  agreement: ServiceAgreement | null;
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (request: SignServiceAgreementRequest) => Promise<void>;
}

// ============================================================================
// Helper Functions
// ============================================================================

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-CA', {
    style: 'currency',
    currency: 'CAD',
  }).format(amount);
}

function getAnnexTitle(annex: ServiceAgreementAnnex): string {
  switch (annex.type) {
    case 'A':
      return 'Annex A - Field Trips';
    case 'B':
      return 'Annex B - Hygiene Items';
    case 'C':
      return 'Annex C - Meals';
    case 'D':
      return 'Annex D - Extended Hours';
    default:
      return `Annex ${annex.type}`;
  }
}

// ============================================================================
// Main Component
// ============================================================================

export function ServiceAgreementSignature({
  agreement,
  isOpen,
  onClose,
  onSubmit,
}: ServiceAgreementSignatureProps) {
  // Signature state
  const [hasSignature, setHasSignature] = useState(false);
  const [signatureDataUrl, setSignatureDataUrl] = useState<string | null>(null);
  const [signatureType, setSignatureType] = useState<'drawn' | 'typed'>('drawn');
  const [typedSignature, setTypedSignature] = useState('');

  // Acknowledgment state
  const [consumerProtectionAcknowledged, setConsumerProtectionAcknowledged] = useState(false);
  const [termsAccepted, setTermsAccepted] = useState(false);
  const [legalAcknowledged, setLegalAcknowledged] = useState(false);

  // Annex signatures state
  const [annexSignatures, setAnnexSignatures] = useState<Record<string, boolean>>({});

  // UI state
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Reset state when modal opens/closes
  useEffect(() => {
    if (!isOpen) {
      setHasSignature(false);
      setSignatureDataUrl(null);
      setSignatureType('drawn');
      setTypedSignature('');
      setConsumerProtectionAcknowledged(false);
      setTermsAccepted(false);
      setLegalAcknowledged(false);
      setAnnexSignatures({});
      setIsSubmitting(false);
      setError(null);
    } else if (agreement) {
      // Initialize annex signatures as true (signed) for pending annexes
      const initialAnnexSignatures: Record<string, boolean> = {};
      agreement.annexes
        .filter((annex) => annex.status === 'pending')
        .forEach((annex) => {
          initialAnnexSignatures[annex.id] = true;
        });
      setAnnexSignatures(initialAnnexSignatures);
    }
  }, [isOpen, agreement]);

  // Handle signature change from canvas
  const handleSignatureChange = useCallback(
    (hasSig: boolean, dataUrl: string | null) => {
      setHasSignature(hasSig);
      setSignatureDataUrl(dataUrl);
    },
    []
  );

  // Handle typed signature change
  const handleTypedSignatureChange = (value: string) => {
    setTypedSignature(value);
    setHasSignature(value.trim().length > 0);
    // For typed signatures, we store the text as the "data URL"
    setSignatureDataUrl(value.trim() || null);
  };

  // Handle annex signature toggle
  const handleAnnexSignatureChange = (annexId: string, signed: boolean) => {
    setAnnexSignatures((prev) => ({
      ...prev,
      [annexId]: signed,
    }));
  };

  // Handle form submission
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!agreement) return;

    // Validate required fields
    if (!hasSignature) {
      setError('Please provide your signature.');
      return;
    }
    if (!consumerProtectionAcknowledged) {
      setError('Please acknowledge the Consumer Protection Act notice.');
      return;
    }
    if (!termsAccepted) {
      setError('Please accept the terms and conditions.');
      return;
    }
    if (!legalAcknowledged) {
      setError('Please acknowledge the legal binding agreement.');
      return;
    }

    setIsSubmitting(true);
    setError(null);

    try {
      // Build the request payload
      const request: SignServiceAgreementRequest = {
        agreementId: agreement.id,
        signatureData: signatureType === 'drawn' ? (signatureDataUrl || '') : typedSignature,
        signatureType,
        consumerProtectionAcknowledged,
        termsAccepted,
        legalAcknowledged,
        annexSignatures: Object.entries(annexSignatures).map(([annexId, signed]) => ({
          annexId,
          signed,
        })),
      };

      await onSubmit(request);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to submit signature. Please try again.');
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
      document.body.style.overflow = 'hidden';
    }

    return () => {
      window.removeEventListener('keydown', handleEscape);
      document.body.style.overflow = 'unset';
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isOpen, isSubmitting, onClose]);

  if (!isOpen || !agreement) return null;

  const pendingAnnexes = agreement.annexes.filter((annex) => annex.status === 'pending');
  const canSubmit =
    hasSignature &&
    consumerProtectionAcknowledged &&
    termsAccepted &&
    legalAcknowledged &&
    !isSubmitting;

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
              <div>
                <h2 className="text-lg font-semibold text-gray-900">
                  Sign Service Agreement
                </h2>
                <p className="mt-1 text-sm text-gray-500">
                  Agreement #{agreement.agreementNumber}
                </p>
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
            <div className="max-h-[70vh] overflow-y-auto px-6 py-4">
              {/* Error message */}
              {error && (
                <div className="mb-4 rounded-lg bg-red-50 border border-red-200 p-3">
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

              {/* Agreement Summary */}
              <div className="mb-6 rounded-lg bg-gray-50 p-4">
                <h3 className="text-sm font-semibold text-gray-900 mb-3">
                  Agreement Summary
                </h3>
                <div className="space-y-2 text-sm">
                  <div className="flex justify-between">
                    <span className="text-gray-500">Child:</span>
                    <span className="text-gray-900 font-medium">{agreement.childName}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Period:</span>
                    <span className="text-gray-900">
                      {formatDate(agreement.startDate)} - {formatDate(agreement.endDate)}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Monthly Amount:</span>
                    <span className="text-gray-900 font-medium">
                      {formatCurrency(agreement.paymentTerms.monthlyAmount)}
                    </span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-gray-500">Provider:</span>
                    <span className="text-gray-900">{agreement.providerName}</span>
                  </div>
                </div>
              </div>

              {/* Consumer Protection Act Notice */}
              <div className="mb-6 rounded-lg bg-blue-50 border border-blue-200 p-4">
                <div className="flex items-start">
                  <div className="flex-shrink-0">
                    <svg
                      className="h-5 w-5 text-blue-500"
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
                  </div>
                  <div className="ml-3">
                    <h4 className="text-sm font-semibold text-blue-900">
                      Quebec Consumer Protection Act Notice
                    </h4>
                    <div className="mt-2 text-sm text-blue-800 space-y-2">
                      <p>
                        <strong>Important:</strong> Under the Quebec Consumer Protection Act, you have
                        a <strong>10-day cooling-off period</strong> during which you may cancel this
                        agreement without penalty.
                      </p>
                      <p>
                        This period begins from the date you receive a copy of the signed agreement.
                        To cancel, you must notify the service provider in writing within this period.
                      </p>
                      <p className="text-xs">
                        Loi sur la protection du consommateur (L.R.Q., c. P-40.1)
                      </p>
                    </div>

                    {/* Consumer Protection Acknowledgment */}
                    <div className="mt-4">
                      <label className="flex items-start space-x-3">
                        <input
                          type="checkbox"
                          checked={consumerProtectionAcknowledged}
                          onChange={(e) => setConsumerProtectionAcknowledged(e.target.checked)}
                          disabled={isSubmitting}
                          className="mt-1 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                        />
                        <span className="text-sm text-blue-900">
                          I acknowledge that I have read and understand the Consumer Protection Act
                          notice and my right to cancel within the 10-day cooling-off period.
                        </span>
                      </label>
                    </div>
                  </div>
                </div>
              </div>

              {/* Pending Annexes */}
              {pendingAnnexes.length > 0 && (
                <div className="mb-6">
                  <h3 className="text-sm font-semibold text-gray-900 mb-3">
                    Optional Annexes
                  </h3>
                  <p className="text-sm text-gray-500 mb-3">
                    The following optional annexes will be signed along with the main agreement:
                  </p>
                  <div className="space-y-2">
                    {pendingAnnexes.map((annex) => (
                      <label
                        key={annex.id}
                        className="flex items-center space-x-3 p-3 rounded-lg border border-gray-200 hover:bg-gray-50"
                      >
                        <input
                          type="checkbox"
                          checked={annexSignatures[annex.id] ?? true}
                          onChange={(e) => handleAnnexSignatureChange(annex.id, e.target.checked)}
                          disabled={isSubmitting}
                          className="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                        />
                        <span className="text-sm text-gray-900">{getAnnexTitle(annex)}</span>
                      </label>
                    ))}
                  </div>
                </div>
              )}

              {/* Signature Type Selection */}
              <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-2">
                  Signature Method
                </label>
                <div className="flex space-x-4">
                  <label className="flex items-center">
                    <input
                      type="radio"
                      value="drawn"
                      checked={signatureType === 'drawn'}
                      onChange={() => {
                        setSignatureType('drawn');
                        setTypedSignature('');
                        setHasSignature(false);
                        setSignatureDataUrl(null);
                      }}
                      disabled={isSubmitting}
                      className="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span className="ml-2 text-sm text-gray-700">Draw signature</span>
                  </label>
                  <label className="flex items-center">
                    <input
                      type="radio"
                      value="typed"
                      checked={signatureType === 'typed'}
                      onChange={() => {
                        setSignatureType('typed');
                        setHasSignature(false);
                        setSignatureDataUrl(null);
                      }}
                      disabled={isSubmitting}
                      className="h-4 w-4 border-gray-300 text-primary-600 focus:ring-primary-500"
                    />
                    <span className="ml-2 text-sm text-gray-700">Type signature</span>
                  </label>
                </div>
              </div>

              {/* Signature Input */}
              <div className="mb-6">
                <label className="block text-sm font-medium text-gray-700 mb-3">
                  Your Signature
                </label>
                {signatureType === 'drawn' ? (
                  <SignatureCanvas
                    onSignatureChange={handleSignatureChange}
                    width={500}
                    height={150}
                  />
                ) : (
                  <div>
                    <input
                      type="text"
                      value={typedSignature}
                      onChange={(e) => handleTypedSignatureChange(e.target.value)}
                      placeholder="Type your full legal name"
                      disabled={isSubmitting}
                      className="w-full rounded-lg border border-gray-300 px-4 py-3 text-lg font-signature focus:border-primary-500 focus:ring-primary-500 disabled:bg-gray-100"
                      style={{ fontFamily: "'Dancing Script', cursive" }}
                    />
                    <p className="mt-2 text-xs text-gray-500">
                      By typing your name, you agree that this constitutes your electronic signature.
                    </p>
                  </div>
                )}
              </div>

              {/* Terms and Conditions */}
              <div className="mb-4">
                <label className="flex items-start space-x-3">
                  <input
                    type="checkbox"
                    checked={termsAccepted}
                    onChange={(e) => setTermsAccepted(e.target.checked)}
                    disabled={isSubmitting}
                    className="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  />
                  <span className="text-sm text-gray-600">
                    I have read and accept all terms and conditions of this service agreement,
                    including the payment terms, operating hours, attendance requirements, and
                    termination conditions.
                  </span>
                </label>
              </div>

              {/* Legal Acknowledgment */}
              <div className="mb-6">
                <label className="flex items-start space-x-3">
                  <input
                    type="checkbox"
                    checked={legalAcknowledged}
                    onChange={(e) => setLegalAcknowledged(e.target.checked)}
                    disabled={isSubmitting}
                    className="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  />
                  <span className="text-sm text-gray-600">
                    I understand that by signing below, I am entering into a legally binding
                    agreement with the service provider. I confirm that I am the parent or legal
                    guardian of the child named in this agreement and have the authority to enter
                    into this contract.
                  </span>
                </label>
              </div>

              {/* Timestamp notice */}
              <div className="rounded-lg bg-gray-100 p-3">
                <div className="flex items-center">
                  <svg
                    className="h-4 w-4 text-gray-500 mr-2"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                  <p className="text-xs text-gray-500">
                    Your signature will be timestamped with the current date, time, and IP address
                    for verification and audit purposes. This information creates a secure, legally
                    binding electronic signature record.
                  </p>
                </div>
              </div>
            </div>

            {/* Footer */}
            <div className="border-t border-gray-200 px-6 py-4">
              <div className="flex items-center justify-between">
                <p className="text-xs text-gray-500">
                  {hasSignature &&
                    consumerProtectionAcknowledged &&
                    termsAccepted &&
                    legalAcknowledged
                    ? 'Ready to submit'
                    : 'Please complete all required fields'}
                </p>
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
                            d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                          />
                        </svg>
                        Sign Agreement
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
