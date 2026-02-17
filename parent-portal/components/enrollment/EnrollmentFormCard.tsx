'use client';

import type { EnrollmentFormSummary, EnrollmentFormStatus } from '@/lib/types';

// ============================================================================
// Types
// ============================================================================

interface EnrollmentFormCardProps {
  form: EnrollmentFormSummary;
  onView: (formId: string) => void;
  onEdit?: (formId: string) => void;
  onContinue?: (formId: string) => void;
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

function calculateAge(dateOfBirth: string): string {
  const today = new Date();
  const birth = new Date(dateOfBirth);
  let years = today.getFullYear() - birth.getFullYear();
  let months = today.getMonth() - birth.getMonth();

  if (months < 0) {
    years--;
    months += 12;
  }

  if (years === 0) {
    return `${months} month${months !== 1 ? 's' : ''} old`;
  }
  if (months === 0) {
    return `${years} year${years !== 1 ? 's' : ''} old`;
  }
  return `${years}y ${months}m old`;
}

function getStatusIcon(status: EnrollmentFormStatus): React.ReactNode {
  switch (status) {
    case 'Draft':
      return (
        <svg
          className="h-6 w-6 text-gray-500"
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
    case 'Submitted':
      return (
        <svg
          className="h-6 w-6 text-blue-600"
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
      );
    case 'Approved':
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
            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      );
    case 'Rejected':
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
            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      );
    case 'Expired':
      return (
        <svg
          className="h-6 w-6 text-amber-600"
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
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>
      );
  }
}

function getStatusBadgeClasses(status: EnrollmentFormStatus): string {
  switch (status) {
    case 'Draft':
      return 'badge badge-default';
    case 'Submitted':
      return 'badge badge-info';
    case 'Approved':
      return 'badge badge-success';
    case 'Rejected':
      return 'badge badge-error';
    case 'Expired':
      return 'badge badge-warning';
    default:
      return 'badge badge-default';
  }
}

function getStatusBadgeIcon(status: EnrollmentFormStatus): React.ReactNode {
  switch (status) {
    case 'Draft':
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
    case 'Submitted':
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
            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      );
    case 'Approved':
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
    case 'Rejected':
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
    case 'Expired':
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
            d="M12 9v2m0 4h.01"
          />
        </svg>
      );
    default:
      return null;
  }
}

// ============================================================================
// Component
// ============================================================================

export function EnrollmentFormCard({
  form,
  onView,
  onEdit,
  onContinue,
}: EnrollmentFormCardProps) {
  const childFullName = `${form.childFirstName} ${form.childLastName}`;
  const canEdit = form.status === 'Draft' || form.status === 'Rejected';
  const canContinue = form.status === 'Draft';

  return (
    <div className="card">
      <div className="card-body">
        <div className="flex items-start justify-between">
          {/* Form info */}
          <div className="flex items-start space-x-4">
            {/* Status icon */}
            <div className="flex-shrink-0">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                {getStatusIcon(form.status)}
              </div>
            </div>

            {/* Form details */}
            <div className="flex-1 min-w-0">
              <h3 className="text-base font-semibold text-gray-900 truncate">
                {childFullName}
              </h3>
              <p className="mt-1 text-sm text-gray-500">
                Form #{form.formNumber}
                {form.version > 1 && (
                  <span className="ml-2 text-xs text-gray-400">
                    (v{form.version})
                  </span>
                )}
              </p>
              <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-400">
                <span>DOB: {formatDate(form.childDateOfBirth)}</span>
                <span>{calculateAge(form.childDateOfBirth)}</span>
              </div>

              {/* Admission date if available */}
              {form.admissionDate && (
                <p className="mt-1 text-xs text-gray-400">
                  Admission: {formatDate(form.admissionDate)}
                </p>
              )}

              {/* Created info */}
              {form.createdAt && (
                <div className="mt-2 flex items-center text-xs text-gray-400">
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
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                    />
                  </svg>
                  Created {formatDate(form.createdAt)}
                  {form.createdByName && (
                    <span className="ml-1">by {form.createdByName}</span>
                  )}
                </div>
              )}
            </div>
          </div>

          {/* Status badge */}
          <div className="flex-shrink-0">
            <span className={getStatusBadgeClasses(form.status)}>
              {getStatusBadgeIcon(form.status)}
              {form.status}
            </span>
          </div>
        </div>

        {/* Actions */}
        <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
          {/* View button */}
          <button
            type="button"
            onClick={() => onView(form.id)}
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
            View Details
          </button>

          {/* Continue Draft button (only for drafts) */}
          {canContinue && onContinue && (
            <button
              type="button"
              onClick={() => onContinue(form.id)}
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
                  d="M14 5l7 7m0 0l-7 7m7-7H3"
                />
              </svg>
              Continue
            </button>
          )}

          {/* Edit button (only for editable statuses) */}
          {canEdit && onEdit && !canContinue && (
            <button
              type="button"
              onClick={() => onEdit(form.id)}
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
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
              </svg>
              Edit
            </button>
          )}
        </div>
      </div>
    </div>
  );
}

// ============================================================================
// Exports
// ============================================================================

export type { EnrollmentFormCardProps };
