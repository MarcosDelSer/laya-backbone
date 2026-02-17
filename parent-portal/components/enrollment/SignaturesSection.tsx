'use client';

import { useCallback, useState, useEffect } from 'react';
import { SignatureCanvas } from '../SignatureCanvas';
import type { SignatureType, EnrollmentSignature } from '@/lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Individual signature data for the wizard.
 */
export interface SignatureData {
  signatureType: SignatureType;
  signatureData: string | null;
  signerName: string;
  signedAt: string | null;
  agreedToTerms: boolean;
}

/**
 * All signatures collected in the section.
 */
export interface SignaturesData {
  parent1: SignatureData;
  parent2: SignatureData | null;
  director: SignatureData | null;
}

interface SignaturesSectionProps {
  /** Current signatures data */
  data: SignaturesData | null;
  /** Callback when data changes */
  onChange: (data: SignaturesData | null) => void;
  /** Whether the form is disabled */
  disabled?: boolean;
  /** Validation errors to display */
  errors?: string[];
  /** Whether to show parent 2 signature (based on whether parent 2 exists) */
  showParent2?: boolean;
  /** Whether to require director signature */
  requireDirectorSignature?: boolean;
  /** Parent 1 name for display */
  parent1Name?: string;
  /** Parent 2 name for display */
  parent2Name?: string;
}

// ============================================================================
// Default Data
// ============================================================================

const createDefaultSignatureData = (signatureType: SignatureType): SignatureData => ({
  signatureType,
  signatureData: null,
  signerName: '',
  signedAt: null,
  agreedToTerms: false,
});

const createDefaultSignaturesData = (): SignaturesData => ({
  parent1: createDefaultSignatureData('Parent1'),
  parent2: null,
  director: null,
});

// ============================================================================
// Helper Components
// ============================================================================

interface SignatureBlockProps {
  title: string;
  subtitle?: string;
  signature: SignatureData;
  onChange: (signature: SignatureData) => void;
  disabled: boolean;
  required?: boolean;
  showClear?: boolean;
  signerNamePlaceholder?: string;
}

function SignatureBlock({
  title,
  subtitle,
  signature,
  onChange,
  disabled,
  required = false,
  signerNamePlaceholder = 'Enter your full legal name',
}: SignatureBlockProps) {
  const hasSignature = !!signature.signatureData;

  // Handle signature canvas change
  const handleSignatureChange = useCallback(
    (hasSig: boolean, dataUrl: string | null) => {
      onChange({
        ...signature,
        signatureData: dataUrl,
        signedAt: hasSig ? new Date().toISOString() : null,
      });
    },
    [signature, onChange]
  );

  // Handle signer name change
  const handleNameChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      onChange({
        ...signature,
        signerName: e.target.value,
      });
    },
    [signature, onChange]
  );

  // Handle agreement checkbox change
  const handleAgreementChange = useCallback(
    (e: React.ChangeEvent<HTMLInputElement>) => {
      onChange({
        ...signature,
        agreedToTerms: e.target.checked,
      });
    },
    [signature, onChange]
  );

  const isComplete = hasSignature && signature.signerName.trim() && signature.agreedToTerms;

  return (
    <div className="border border-gray-200 rounded-lg p-6 bg-white">
      {/* Header */}
      <div className="mb-4">
        <div className="flex items-center justify-between">
          <h4 className="text-base font-medium text-gray-900">
            {title}
            {required && <span className="text-red-500 ml-1">*</span>}
          </h4>
          {isComplete && (
            <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
              <svg
                className="mr-1 h-3 w-3"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                  clipRule="evenodd"
                />
              </svg>
              Signed
            </span>
          )}
        </div>
        {subtitle && (
          <p className="mt-1 text-sm text-gray-500">{subtitle}</p>
        )}
      </div>

      {/* Signer Name Input */}
      <div className="mb-4">
        <label
          htmlFor={`signerName-${signature.signatureType}`}
          className="block text-sm font-medium text-gray-700"
        >
          Full Legal Name <span className="text-red-500">*</span>
        </label>
        <input
          type="text"
          id={`signerName-${signature.signatureType}`}
          value={signature.signerName}
          onChange={handleNameChange}
          disabled={disabled}
          placeholder={signerNamePlaceholder}
          className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
        />
      </div>

      {/* Signature Canvas */}
      <div className="mb-4">
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Signature <span className="text-red-500">*</span>
        </label>
        <SignatureCanvas
          onSignatureChange={handleSignatureChange}
          width={380}
          height={150}
          penColor="#1f2937"
          penWidth={2}
        />
      </div>

      {/* Agreement Checkbox */}
      <div className="mb-4">
        <label className="flex items-start space-x-3">
          <input
            type="checkbox"
            checked={signature.agreedToTerms}
            onChange={handleAgreementChange}
            disabled={disabled}
            className="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed"
          />
          <span className="text-sm text-gray-600">
            I acknowledge that by signing this form, I certify that all information
            provided is true and accurate to the best of my knowledge. I understand
            that this electronic signature has the same legal effect as a handwritten
            signature.
          </span>
        </label>
      </div>

      {/* Timestamp */}
      {signature.signedAt && (
        <div className="text-xs text-gray-400">
          Signed at: {new Date(signature.signedAt).toLocaleString()}
        </div>
      )}
    </div>
  );
}

// ============================================================================
// Main Component
// ============================================================================

/**
 * Signatures section for enrollment form wizard.
 * Captures e-signatures from Parent 1 (required), Parent 2 (optional),
 * and Director (optional based on settings).
 */
export function SignaturesSection({
  data,
  onChange,
  disabled = false,
  errors = [],
  showParent2 = false,
  requireDirectorSignature = false,
  parent1Name = 'Parent/Guardian 1',
  parent2Name = 'Parent/Guardian 2',
}: SignaturesSectionProps) {
  // ---------------------------------------------------------------------------
  // Initialize Data
  // ---------------------------------------------------------------------------

  const signaturesData = data ?? createDefaultSignaturesData();

  // Ensure parent2 and director signatures exist when needed
  useEffect(() => {
    let updated = false;
    let newData = { ...signaturesData };

    if (showParent2 && !signaturesData.parent2) {
      newData = {
        ...newData,
        parent2: createDefaultSignatureData('Parent2'),
      };
      updated = true;
    }

    if (requireDirectorSignature && !signaturesData.director) {
      newData = {
        ...newData,
        director: createDefaultSignatureData('Director'),
      };
      updated = true;
    }

    if (updated) {
      onChange(newData);
    }
  }, [showParent2, requireDirectorSignature, signaturesData, onChange]);

  // ---------------------------------------------------------------------------
  // Handlers
  // ---------------------------------------------------------------------------

  const handleParent1Change = useCallback(
    (signature: SignatureData) => {
      onChange({
        ...signaturesData,
        parent1: signature,
      });
    },
    [signaturesData, onChange]
  );

  const handleParent2Change = useCallback(
    (signature: SignatureData) => {
      onChange({
        ...signaturesData,
        parent2: signature,
      });
    },
    [signaturesData, onChange]
  );

  const handleDirectorChange = useCallback(
    (signature: SignatureData) => {
      onChange({
        ...signaturesData,
        director: signature,
      });
    },
    [signaturesData, onChange]
  );

  // ---------------------------------------------------------------------------
  // Computed Values
  // ---------------------------------------------------------------------------

  const parent1Complete =
    signaturesData.parent1.signatureData &&
    signaturesData.parent1.signerName.trim() &&
    signaturesData.parent1.agreedToTerms;

  const parent2Complete =
    !showParent2 ||
    (signaturesData.parent2?.signatureData &&
      signaturesData.parent2?.signerName.trim() &&
      signaturesData.parent2?.agreedToTerms);

  const directorComplete =
    !requireDirectorSignature ||
    (signaturesData.director?.signatureData &&
      signaturesData.director?.signerName.trim() &&
      signaturesData.director?.agreedToTerms);

  const allRequired = parent1Complete;
  const allOptionalComplete = parent2Complete && directorComplete;

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div className="space-y-6">
      {/* Section Header */}
      <div>
        <h3 className="text-lg font-medium text-gray-900">
          Signatures
        </h3>
        <p className="mt-1 text-sm text-gray-500">
          Please provide your electronic signature to confirm the accuracy of the
          information provided in this enrollment form. All signatures are legally
          binding.
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
                Please complete the following:
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

      {/* Progress Indicator */}
      <div className="rounded-lg bg-gray-50 p-4">
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm font-medium text-gray-700">
            Signature Progress
          </span>
          <span className="text-sm text-gray-500">
            {[parent1Complete, showParent2 ? !!signaturesData.parent2?.signatureData : null, requireDirectorSignature ? !!signaturesData.director?.signatureData : null]
              .filter((v) => v !== null)
              .filter(Boolean).length}
            {' / '}
            {[true, showParent2, requireDirectorSignature].filter(Boolean).length}
            {' completed'}
          </span>
        </div>
        <div className="flex space-x-2">
          <div
            className={`flex-1 h-2 rounded-full ${
              parent1Complete ? 'bg-green-500' : 'bg-gray-200'
            }`}
          />
          {showParent2 && (
            <div
              className={`flex-1 h-2 rounded-full ${
                signaturesData.parent2?.signatureData &&
                signaturesData.parent2?.signerName.trim() &&
                signaturesData.parent2?.agreedToTerms
                  ? 'bg-green-500'
                  : 'bg-gray-200'
              }`}
            />
          )}
          {requireDirectorSignature && (
            <div
              className={`flex-1 h-2 rounded-full ${
                signaturesData.director?.signatureData &&
                signaturesData.director?.signerName.trim() &&
                signaturesData.director?.agreedToTerms
                  ? 'bg-green-500'
                  : 'bg-gray-200'
              }`}
            />
          )}
        </div>
      </div>

      {/* ================================================================== */}
      {/* Parent 1 Signature (Required) */}
      {/* ================================================================== */}
      <div>
        <SignatureBlock
          title={parent1Name}
          subtitle="Primary parent/guardian signature is required to submit this enrollment form."
          signature={signaturesData.parent1}
          onChange={handleParent1Change}
          disabled={disabled}
          required={true}
          signerNamePlaceholder="Enter primary parent/guardian full legal name"
        />
      </div>

      {/* ================================================================== */}
      {/* Parent 2 Signature (Optional) */}
      {/* ================================================================== */}
      {showParent2 && signaturesData.parent2 && (
        <div className="border-t border-gray-200 pt-6">
          <SignatureBlock
            title={parent2Name}
            subtitle="Second parent/guardian signature (optional but recommended)."
            signature={signaturesData.parent2}
            onChange={handleParent2Change}
            disabled={disabled}
            required={false}
            signerNamePlaceholder="Enter second parent/guardian full legal name"
          />
        </div>
      )}

      {/* ================================================================== */}
      {/* Director Signature (When Required) */}
      {/* ================================================================== */}
      {requireDirectorSignature && signaturesData.director && (
        <div className="border-t border-gray-200 pt-6">
          <div className="rounded-lg bg-amber-50 border border-amber-200 p-4 mb-4">
            <div className="flex">
              <svg
                className="h-5 w-5 text-amber-400 flex-shrink-0"
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
                <p className="text-sm text-amber-700">
                  <strong>Director Approval Required:</strong> This enrollment form
                  requires director approval before final submission. The director
                  will be notified to review and sign the form.
                </p>
              </div>
            </div>
          </div>

          <SignatureBlock
            title="Director Signature"
            subtitle="Director approval and signature for enrollment verification."
            signature={signaturesData.director}
            onChange={handleDirectorChange}
            disabled={disabled}
            required={true}
            signerNamePlaceholder="Enter director full legal name"
          />
        </div>
      )}

      {/* ================================================================== */}
      {/* Legal Notice */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
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
              <h4 className="text-sm font-medium text-blue-800">
                Electronic Signature Notice
              </h4>
              <div className="mt-2 text-sm text-blue-700 space-y-2">
                <p>
                  By providing your electronic signature, you acknowledge that:
                </p>
                <ul className="list-disc list-inside space-y-1 ml-2">
                  <li>
                    All information provided in this enrollment form is true and accurate
                  </li>
                  <li>
                    You have read and understood all sections of this form
                  </li>
                  <li>
                    Your electronic signature is legally equivalent to a handwritten signature
                  </li>
                  <li>
                    You consent to the collection and use of the information provided for
                    childcare services as outlined in our privacy policy
                  </li>
                  <li>
                    You agree to notify the daycare of any changes to the information
                    provided in this form
                  </li>
                </ul>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* ================================================================== */}
      {/* Quebec Compliance Notice */}
      {/* ================================================================== */}
      <div className="rounded-lg bg-gray-50 p-4">
        <div className="flex">
          <div className="flex-shrink-0">
            <svg
              className="h-5 w-5 text-gray-400"
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
          <div className="ml-3">
            <p className="text-sm text-gray-600">
              <strong>Quebec Compliance:</strong> This enrollment form complies with
              Quebec childcare regulations (Loi sur les services de garde educatifs
              a l&apos;enfance). Your signature confirms your agreement with the
              daycare&apos;s policies and your commitment to the enrollment terms.
            </p>
          </div>
        </div>
      </div>

      {/* ================================================================== */}
      {/* Timestamp Notice */}
      {/* ================================================================== */}
      <div className="text-center">
        <p className="text-xs text-gray-400">
          All signatures are timestamped and recorded with your IP address for
          verification purposes. A copy of this signed form will be available for
          download after submission.
        </p>
      </div>
    </div>
  );
}

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Convert SignaturesData to EnrollmentSignature array for API submission.
 */
export function signaturesDataToApiFormat(
  data: SignaturesData,
  formId: string
): EnrollmentSignature[] {
  const signatures: EnrollmentSignature[] = [];

  // Parent 1 signature (required)
  if (data.parent1.signatureData && data.parent1.signedAt) {
    signatures.push({
      formId,
      signatureType: 'Parent1',
      signatureData: data.parent1.signatureData,
      signerName: data.parent1.signerName,
      signedAt: data.parent1.signedAt,
    });
  }

  // Parent 2 signature (optional)
  if (data.parent2?.signatureData && data.parent2?.signedAt) {
    signatures.push({
      formId,
      signatureType: 'Parent2',
      signatureData: data.parent2.signatureData,
      signerName: data.parent2.signerName,
      signedAt: data.parent2.signedAt,
    });
  }

  // Director signature (optional)
  if (data.director?.signatureData && data.director?.signedAt) {
    signatures.push({
      formId,
      signatureType: 'Director',
      signatureData: data.director.signatureData,
      signerName: data.director.signerName,
      signedAt: data.director.signedAt,
    });
  }

  return signatures;
}

/**
 * Validate signatures data and return validation errors.
 */
export function validateSignaturesData(
  data: SignaturesData | null,
  options: {
    requireParent2?: boolean;
    requireDirector?: boolean;
  } = {}
): string[] {
  const errors: string[] = [];

  if (!data) {
    errors.push('Signature data is required');
    return errors;
  }

  // Validate Parent 1 signature (always required)
  if (!data.parent1.signerName.trim()) {
    errors.push('Parent/Guardian 1 name is required');
  }
  if (!data.parent1.signatureData) {
    errors.push('Parent/Guardian 1 signature is required');
  }
  if (!data.parent1.agreedToTerms) {
    errors.push('Parent/Guardian 1 must agree to the terms');
  }

  // Validate Parent 2 signature (if required)
  if (options.requireParent2 && data.parent2) {
    if (!data.parent2.signerName.trim()) {
      errors.push('Parent/Guardian 2 name is required');
    }
    if (!data.parent2.signatureData) {
      errors.push('Parent/Guardian 2 signature is required');
    }
    if (!data.parent2.agreedToTerms) {
      errors.push('Parent/Guardian 2 must agree to the terms');
    }
  }

  // Validate Director signature (if required)
  if (options.requireDirector && data.director) {
    if (!data.director.signerName.trim()) {
      errors.push('Director name is required');
    }
    if (!data.director.signatureData) {
      errors.push('Director signature is required');
    }
    if (!data.director.agreedToTerms) {
      errors.push('Director must agree to the terms');
    }
  }

  return errors;
}

// ============================================================================
// Exports
// ============================================================================

export type { SignaturesSectionProps };
