'use client';

import type { ServiceAgreementSummary, ServiceAgreementStatus } from '../lib/types';

interface ServiceAgreementCardProps {
  agreement: ServiceAgreementSummary;
  onView: (agreementId: string) => void;
  onSign: (agreementId: string) => void;
}

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatDateTime(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

function getStatusBadgeClasses(status: ServiceAgreementStatus): string {
  switch (status) {
    case 'active':
      return 'badge badge-success';
    case 'pending_signature':
      return 'badge badge-warning';
    case 'draft':
      return 'badge badge-info';
    case 'expired':
      return 'badge badge-error';
    case 'terminated':
      return 'badge badge-error';
    case 'cancelled':
      return 'badge badge-neutral';
    default:
      return 'badge badge-neutral';
  }
}

function getStatusLabel(status: ServiceAgreementStatus): string {
  switch (status) {
    case 'active':
      return 'Active';
    case 'pending_signature':
      return 'Pending Signature';
    case 'draft':
      return 'Draft';
    case 'expired':
      return 'Expired';
    case 'terminated':
      return 'Terminated';
    case 'cancelled':
      return 'Cancelled';
    default:
      return status;
  }
}

function getStatusIcon(status: ServiceAgreementStatus): React.ReactNode {
  switch (status) {
    case 'active':
      return (
        <svg
          className="mr-1 h-3 w-3"
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
      );
    case 'pending_signature':
      return (
        <svg
          className="mr-1 h-3 w-3"
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
      );
    case 'draft':
      return (
        <svg
          className="mr-1 h-3 w-3"
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
      );
    case 'expired':
    case 'terminated':
    case 'cancelled':
      return (
        <svg
          className="mr-1 h-3 w-3"
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
      );
    default:
      return null;
  }
}

function getAgreementIcon(): React.ReactNode {
  return (
    <svg
      className="h-6 w-6 text-purple-600"
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
  );
}

export function ServiceAgreementCard({
  agreement,
  onView,
  onSign,
}: ServiceAgreementCardProps) {
  const requiresSignature =
    agreement.status === 'pending_signature' && !agreement.parentSignedAt;

  return (
    <div className="card">
      <div className="card-body">
        <div className="flex items-start justify-between">
          {/* Agreement info */}
          <div className="flex items-start space-x-4">
            {/* Agreement icon */}
            <div className="flex-shrink-0">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-purple-100">
                {getAgreementIcon()}
              </div>
            </div>

            {/* Agreement details */}
            <div className="flex-1 min-w-0">
              <h3 className="text-base font-semibold text-gray-900 truncate">
                Service Agreement
              </h3>
              <p className="mt-1 text-sm text-gray-500">
                {agreement.childName}
              </p>
              <p className="mt-1 text-xs text-gray-400">
                #{agreement.agreementNumber}
              </p>
              <p className="mt-1 text-xs text-gray-400">
                {formatDate(agreement.startDate)} - {formatDate(agreement.endDate)}
              </p>

              {/* Signature status */}
              {agreement.parentSignedAt && (
                <div className="mt-2 flex items-center text-xs text-green-600">
                  <svg
                    className="mr-1 h-4 w-4"
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
                  Signed on {formatDateTime(agreement.parentSignedAt)}
                </div>
              )}

              {/* Pending signature notice */}
              {requiresSignature && (
                <div className="mt-2 flex items-center text-xs text-amber-600">
                  <svg
                    className="mr-1 h-4 w-4"
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
                  Your signature is required
                </div>
              )}
            </div>
          </div>

          {/* Status badge */}
          <div className="flex-shrink-0">
            <span className={getStatusBadgeClasses(agreement.status)}>
              {getStatusIcon(agreement.status)}
              {getStatusLabel(agreement.status)}
            </span>
          </div>
        </div>

        {/* Actions */}
        <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
          {/* View Agreement */}
          <button
            type="button"
            onClick={() => onView(agreement.id)}
            className="btn btn-outline text-sm"
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
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
              />
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
              />
            </svg>
            View Agreement
          </button>

          {/* Sign button (only for agreements requiring signature) */}
          {requiresSignature && (
            <button
              type="button"
              onClick={() => onSign(agreement.id)}
              className="btn btn-primary text-sm"
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
                  d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                />
              </svg>
              Sign Agreement
            </button>
          )}

          {/* View signed agreement (only for fully signed agreements) */}
          {agreement.allSignaturesComplete && (
            <button
              type="button"
              className="btn btn-outline text-sm text-green-600 border-green-300 hover:bg-green-50"
              onClick={() => onView(agreement.id)}
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
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              View Signed Copy
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
