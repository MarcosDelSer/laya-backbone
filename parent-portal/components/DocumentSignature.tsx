'use client';

import { useState, useCallback, useEffect } from 'react';
import { SignatureCanvas } from './SignatureCanvas';
import { useFocusTrap } from '../hooks';

interface DocumentData {
  id: string;
  title: string;
  type: string;
  uploadDate: string;
  status: 'pending' | 'signed';
  signedAt?: string;
  signatureUrl?: string;
  pdfUrl: string;
}

interface DocumentSignatureProps {
  documentToSign: DocumentData | null;
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (documentId: string, signatureDataUrl: string) => void;
}

export function DocumentSignature({
  documentToSign,
  isOpen,
  onClose,
  onSubmit,
}: DocumentSignatureProps) {
  const [hasSignature, setHasSignature] = useState(false);
  const [signatureDataUrl, setSignatureDataUrl] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [agreedToTerms, setAgreedToTerms] = useState(false);

  // Focus trap for modal
  const modalRef = useFocusTrap<HTMLDivElement>(isOpen && !isSubmitting);

  // Reset state when modal opens/closes
  useEffect(() => {
    if (!isOpen) {
      setHasSignature(false);
      setSignatureDataUrl(null);
      setIsSubmitting(false);
      setAgreedToTerms(false);
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

    if (!documentToSign || !signatureDataUrl || !agreedToTerms) return;

    setIsSubmitting(true);

    try {
      // Simulate API call delay
      await new Promise((resolve) => setTimeout(resolve, 1000));
      onSubmit(documentToSign.id, signatureDataUrl);
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

  if (!isOpen || !documentToSign) return null;

  const canSubmit = hasSignature && agreedToTerms && !isSubmitting;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="signature-modal-title">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
        onClick={!isSubmitting ? onClose : undefined}
        aria-hidden="true"
      />

      {/* Modal */}
      <div className="flex min-h-full items-center justify-center p-4">
        <div ref={modalRef} className="relative w-full max-w-lg transform rounded-xl bg-white shadow-2xl transition-all">
          {/* Header */}
          <div className="border-b border-gray-200 px-6 py-4">
            <div className="flex items-center justify-between">
              <div>
                <h2 id="signature-modal-title" className="text-lg font-semibold text-gray-900">
                  Sign Document
                </h2>
                <p className="mt-1 text-sm text-gray-500">{documentToSign.title}</p>
              </div>
              <button
                type="button"
                onClick={onClose}
                disabled={isSubmitting}
                aria-label="Close signature dialog"
                className="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 disabled:opacity-50"
              >
                <svg
                  className="h-6 w-6"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                  aria-hidden="true"
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
              {/* Document preview link */}
              <div className="mb-6 rounded-lg bg-gray-50 p-4">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-red-100" aria-hidden="true">
                      <svg
                        className="h-5 w-5 text-red-600"
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
                    <div>
                      <p className="text-sm font-medium text-gray-900">
                        {documentToSign.title}
                      </p>
                      <p className="text-xs text-gray-500">{documentToSign.type}</p>
                    </div>
                  </div>
                  <a
                    href={documentToSign.pdfUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label={`View PDF of ${documentToSign.title} in new window`}
                    className="text-sm font-medium text-primary-600 hover:text-primary-700"
                  >
                    View PDF
                  </a>
                </div>
              </div>

              {/* Signature canvas */}
              <div className="mb-6">
                <label id="signature-label" className="block text-sm font-medium text-gray-700 mb-3">
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
                    aria-label="I acknowledge that I have read and understand this document"
                    className="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  />
                  <span className="text-sm text-gray-600">
                    I acknowledge that I have read and understand this document.
                    By signing below, I agree to be legally bound by its terms
                    and conditions.
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
                  aria-label="Cancel signature"
                  className="btn btn-outline"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  disabled={!canSubmit}
                  aria-label={isSubmitting ? 'Submitting signature' : 'Submit signature'}
                  aria-busy={isSubmitting}
                  className="btn btn-primary"
                >
                  {isSubmitting ? (
                    <>
                      <svg
                        className="mr-2 h-4 w-4 animate-spin"
                        fill="none"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
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
                        aria-hidden="true"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M5 13l4 4L19 7"
                        />
                      </svg>
                      Submit Signature
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
