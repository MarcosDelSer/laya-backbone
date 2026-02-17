'use client';

import type {
  GovernmentDocument,
  GovernmentDocumentCategory,
  GovernmentDocumentStatus,
} from '@/lib/types';

interface GovernmentDocumentCardProps {
  document: GovernmentDocument;
  onUpload?: (documentTypeId: string, personId: string) => void;
  onView?: (documentId: string) => void;
  onDelete?: (documentId: string) => void;
}

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function getDaysUntilExpiration(expirationDate: string): number {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const expiry = new Date(expirationDate);
  expiry.setHours(0, 0, 0, 0);
  const diffTime = expiry.getTime() - today.getTime();
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

function getExpirationColor(expirationDate: string | undefined): string {
  if (!expirationDate) return 'text-gray-500';
  const daysUntil = getDaysUntilExpiration(expirationDate);
  if (daysUntil < 0) return 'text-red-600';
  if (daysUntil <= 30) return 'text-amber-600';
  return 'text-gray-500';
}

function getExpirationText(expirationDate: string | undefined): string {
  if (!expirationDate) return 'No expiration';
  const daysUntil = getDaysUntilExpiration(expirationDate);
  if (daysUntil < 0) return `Expired ${Math.abs(daysUntil)} days ago`;
  if (daysUntil === 0) return 'Expires today';
  if (daysUntil === 1) return 'Expires tomorrow';
  if (daysUntil <= 30) return `Expires in ${daysUntil} days`;
  return `Expires: ${formatDate(expirationDate)}`;
}

function getCategoryIcon(category: GovernmentDocumentCategory): React.ReactNode {
  switch (category) {
    case 'child_identity':
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
            d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"
          />
        </svg>
      );
    case 'parent_identity':
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
            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
          />
        </svg>
      );
    case 'health':
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
            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
          />
        </svg>
      );
    case 'immigration':
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
            d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
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
            d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
          />
        </svg>
      );
  }
}

function getStatusBadge(status: GovernmentDocumentStatus): React.ReactNode {
  switch (status) {
    case 'verified':
      return (
        <span className="badge badge-success">
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
          Verified
        </span>
      );
    case 'pending_verification':
      return (
        <span className="badge badge-warning">
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
          Pending
        </span>
      );
    case 'rejected':
      return (
        <span className="badge badge-error">
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
          Rejected
        </span>
      );
    case 'expired':
      return (
        <span className="badge badge-error">
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
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
          </svg>
          Expired
        </span>
      );
    case 'missing':
    default:
      return (
        <span className="badge badge-ghost">
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
              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
            />
          </svg>
          Missing
        </span>
      );
  }
}

export function GovernmentDocumentCard({
  document,
  onUpload,
  onView,
  onDelete,
}: GovernmentDocumentCardProps) {
  const handleView = () => {
    if (document.fileUrl) {
      window.open(document.fileUrl, '_blank');
    }
    onView?.(document.id);
  };

  const handleUpload = () => {
    onUpload?.(document.documentTypeId, document.personId);
  };

  const handleDelete = () => {
    onDelete?.(document.id);
  };

  const isMissing = document.status === 'missing';
  const hasFile = !!document.fileUrl;
  const expirationColor = getExpirationColor(document.expirationDate);
  const expirationText = getExpirationText(document.expirationDate);

  return (
    <div className="card">
      <div className="card-body">
        <div className="flex items-start justify-between">
          {/* Document info */}
          <div className="flex items-start space-x-4">
            {/* Category icon */}
            <div className="flex-shrink-0">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                {getCategoryIcon(document.category)}
              </div>
            </div>

            {/* Document details */}
            <div className="flex-1 min-w-0">
              <h3 className="text-base font-semibold text-gray-900 truncate">
                {document.documentTypeName}
              </h3>
              <p className="mt-1 text-sm text-gray-500">
                For: {document.personName}
              </p>

              {/* Uploaded/Received date */}
              {document.uploadedAt && (
                <p className="mt-1 text-xs text-gray-400">
                  Received: {formatDate(document.uploadedAt)}
                </p>
              )}

              {/* Expiration info */}
              {document.status !== 'missing' && (
                <p className={`mt-1 text-xs ${expirationColor}`}>
                  {expirationText}
                </p>
              )}

              {/* Verified info */}
              {document.status === 'verified' && document.verifiedAt && (
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
                  Verified on {formatDate(document.verifiedAt)}
                </div>
              )}

              {/* Rejection reason */}
              {document.status === 'rejected' && document.rejectionReason && (
                <div className="mt-2 text-xs text-red-600">
                  <span className="font-medium">Reason:</span> {document.rejectionReason}
                </div>
              )}

              {/* Document number */}
              {document.documentNumber && (
                <p className="mt-1 text-xs text-gray-400">
                  Doc #: {document.documentNumber}
                </p>
              )}
            </div>
          </div>

          {/* Status badge */}
          <div className="flex-shrink-0">
            {getStatusBadge(document.status)}
          </div>
        </div>

        {/* Actions */}
        <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
          {/* View Document (only for documents with files) */}
          {hasFile && (
            <button
              type="button"
              onClick={handleView}
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
              View Document
            </button>
          )}

          {/* Upload button (for missing, rejected, or expired documents) */}
          {(isMissing || document.status === 'rejected' || document.status === 'expired') && onUpload && (
            <button
              type="button"
              onClick={handleUpload}
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
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"
                />
              </svg>
              {isMissing ? 'Upload Document' : 'Re-upload Document'}
            </button>
          )}

          {/* Delete button (only for non-verified documents with files) */}
          {hasFile && document.status !== 'verified' && onDelete && (
            <button
              type="button"
              onClick={handleDelete}
              className="btn btn-outline text-sm text-red-600 border-red-300 hover:bg-red-50"
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
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
              </svg>
              Delete
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
