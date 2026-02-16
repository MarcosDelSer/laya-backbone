'use client';

import { useState, useCallback, useEffect } from 'react';
import { SignatureCanvas } from './SignatureCanvas';
import {
  Incident,
  IncidentCategory,
  IncidentSeverity,
} from '../lib/types';

export interface IncidentAcknowledgeProps {
  incident: Incident | null;
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (incidentId: string, signatureDataUrl: string, notes?: string) => void;
}

/**
 * Get severity configuration for styling.
 */
function getSeverityConfig(severity: IncidentSeverity): {
  label: string;
  badgeClass: string;
  bgClass: string;
} {
  switch (severity) {
    case 'minor':
      return {
        label: 'Minor',
        badgeClass: 'badge badge-info',
        bgClass: 'bg-blue-100',
      };
    case 'moderate':
      return {
        label: 'Moderate',
        badgeClass: 'badge badge-warning',
        bgClass: 'bg-yellow-100',
      };
    case 'serious':
      return {
        label: 'Serious',
        badgeClass: 'badge badge-error',
        bgClass: 'bg-orange-100',
      };
    case 'severe':
      return {
        label: 'Severe',
        badgeClass: 'badge bg-red-700 text-white',
        bgClass: 'bg-red-100',
      };
    default:
      return {
        label: 'Unknown',
        badgeClass: 'badge badge-neutral',
        bgClass: 'bg-gray-100',
      };
  }
}

/**
 * Get formatted category label.
 */
function getCategoryLabel(category: IncidentCategory): string {
  const labels: Record<IncidentCategory, string> = {
    bump: 'Bump',
    fall: 'Fall',
    bite: 'Bite',
    scratch: 'Scratch',
    behavioral: 'Behavioral',
    medical: 'Medical',
    allergic_reaction: 'Allergic Reaction',
    other: 'Other',
  };
  return labels[category] || 'Unknown';
}

/**
 * Format a date string for display.
 */
function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
}

/**
 * Format a time string for display.
 */
function formatTime(timeString: string): string {
  const date = timeString.includes('T')
    ? new Date(timeString)
    : new Date(`1970-01-01T${timeString}`);

  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

/**
 * IncidentAcknowledge component provides a modal for parents to acknowledge
 * an incident report with their signature.
 */
export function IncidentAcknowledge({
  incident,
  isOpen,
  onClose,
  onSubmit,
}: IncidentAcknowledgeProps) {
  const [hasSignature, setHasSignature] = useState(false);
  const [signatureDataUrl, setSignatureDataUrl] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [agreedToAcknowledge, setAgreedToAcknowledge] = useState(false);
  const [notes, setNotes] = useState('');

  // Reset state when modal opens/closes
  useEffect(() => {
    if (!isOpen) {
      setHasSignature(false);
      setSignatureDataUrl(null);
      setIsSubmitting(false);
      setAgreedToAcknowledge(false);
      setNotes('');
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

    if (!incident || !signatureDataUrl || !agreedToAcknowledge) return;

    setIsSubmitting(true);

    try {
      // Simulate API call delay
      await new Promise((resolve) => setTimeout(resolve, 1000));
      onSubmit(incident.id, signatureDataUrl, notes || undefined);
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

  if (!isOpen || !incident) return null;

  const severityConfig = getSeverityConfig(incident.severity);
  const canSubmit = hasSignature && agreedToAcknowledge && !isSubmitting;

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
                  Acknowledge Incident Report
                </h2>
                <p className="mt-1 text-sm text-gray-500">
                  {incident.childName} - {formatDate(incident.date)}
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
            <div className="max-h-[60vh] overflow-y-auto px-6 py-4">
              {/* Incident Summary */}
              <div className={`mb-6 rounded-lg ${severityConfig.bgClass} p-4`}>
                <div className="flex items-start space-x-4">
                  {/* Incident Icon */}
                  <div className="flex-shrink-0">
                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-white">
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
                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                        />
                      </svg>
                    </div>
                  </div>

                  {/* Incident Details */}
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center space-x-2 flex-wrap">
                      <h3 className="text-base font-semibold text-gray-900">
                        {getCategoryLabel(incident.category)} Incident
                      </h3>
                      <span className={severityConfig.badgeClass}>
                        {severityConfig.label}
                      </span>
                    </div>

                    <div className="mt-2 text-sm text-gray-600 space-y-1">
                      <p>
                        <span className="font-medium">Date & Time:</span>{' '}
                        {formatDate(incident.date)} at {formatTime(incident.time)}
                      </p>
                      <p>
                        <span className="font-medium">Location:</span>{' '}
                        {incident.location}
                      </p>
                      <p>
                        <span className="font-medium">Reported by:</span>{' '}
                        {incident.reportedByName}
                      </p>
                    </div>
                  </div>
                </div>
              </div>

              {/* Description Section */}
              <div className="mb-6">
                <h4 className="text-sm font-medium text-gray-700 mb-2">
                  Incident Description
                </h4>
                <p className="text-sm text-gray-600 bg-gray-50 rounded-lg p-4">
                  {incident.description}
                </p>
              </div>

              {/* Action Taken Section */}
              <div className="mb-6">
                <h4 className="text-sm font-medium text-gray-700 mb-2">
                  Action Taken
                </h4>
                <p className="text-sm text-gray-600 bg-gray-50 rounded-lg p-4">
                  {incident.actionTaken}
                </p>
              </div>

              {/* Follow-up Notice */}
              {incident.requiresFollowUp && (
                <div className="mb-6 rounded-lg bg-amber-50 border border-amber-200 p-4">
                  <div className="flex items-center">
                    <svg
                      className="h-5 w-5 text-amber-500 mr-2"
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
                    <span className="text-sm font-medium text-amber-800">
                      This incident requires follow-up
                    </span>
                  </div>
                  {incident.followUpNotes && (
                    <p className="mt-2 text-sm text-amber-700">
                      {incident.followUpNotes}
                    </p>
                  )}
                </div>
              )}

              {/* Parent Notes (optional) */}
              <div className="mb-6">
                <label
                  htmlFor="parent-notes"
                  className="block text-sm font-medium text-gray-700 mb-2"
                >
                  Additional Notes (Optional)
                </label>
                <textarea
                  id="parent-notes"
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  disabled={isSubmitting}
                  rows={3}
                  className="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 disabled:opacity-50"
                  placeholder="Add any questions or concerns you'd like to share..."
                />
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

              {/* Acknowledgment checkbox */}
              <div className="mb-4">
                <label className="flex items-start space-x-3">
                  <input
                    type="checkbox"
                    checked={agreedToAcknowledge}
                    onChange={(e) => setAgreedToAcknowledge(e.target.checked)}
                    disabled={isSubmitting}
                    className="mt-1 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  />
                  <span className="text-sm text-gray-600">
                    I acknowledge that I have reviewed this incident report and
                    understand the details of what occurred. By signing below, I
                    confirm that I have been informed of this incident involving
                    my child.
                  </span>
                </label>
              </div>

              {/* Timestamp notice */}
              <p className="text-xs text-gray-400">
                Your acknowledgment will be timestamped with the current date
                and time for record-keeping purposes.
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
                      Acknowledge Incident
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
