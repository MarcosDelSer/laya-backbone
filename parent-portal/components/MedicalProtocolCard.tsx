'use client';

import type {
  ProtocolType,
  ProtocolAuthorizationStatus,
  ProtocolSummary,
} from '@/lib/types';

interface MedicalProtocolCardProps {
  protocol: ProtocolSummary;
  childName: string;
  onAuthorize: (protocolId: string) => void;
  onUpdateWeight: (protocolId: string) => void;
  onViewDetails: (protocolId: string) => void;
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

function getProtocolTypeIcon(type: ProtocolType): React.ReactNode {
  switch (type) {
    case 'medication':
      // Medication/pill icon for Acetaminophen
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
      // Spray/lotion icon for Insect Repellent
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
      // Default medical icon
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

function getStatusBadge(status: ProtocolAuthorizationStatus | null): React.ReactNode {
  if (!status) {
    return (
      <span className="badge badge-neutral">
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
        Not Authorized
      </span>
    );
  }

  switch (status) {
    case 'active':
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
          Authorized
        </span>
      );
    case 'pending':
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
              d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          Expired
        </span>
      );
    case 'revoked':
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
              d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"
            />
          </svg>
          Revoked
        </span>
      );
    default:
      return null;
  }
}

function getProtocolDescription(type: ProtocolType): string {
  switch (type) {
    case 'medication':
      return 'Acetaminophen administration for fever/pain (FO-0647)';
    case 'topical':
      return 'Insect repellent application (FO-0646)';
    default:
      return 'Medical protocol';
  }
}

function formatWeight(weightKg: number | undefined): string {
  if (!weightKg) return 'Not recorded';
  return `${weightKg.toFixed(1)} kg`;
}

export function MedicalProtocolCard({
  protocol,
  childName,
  onAuthorize,
  onUpdateWeight,
  onViewDetails,
}: MedicalProtocolCardProps) {
  const isAuthorized = protocol.authorizationStatus === 'active';
  const needsWeightUpdate = protocol.isWeightExpired === true;
  const canAuthorize = !isAuthorized || protocol.authorizationStatus === 'expired';

  return (
    <div className="card">
      <div className="card-body">
        <div className="flex items-start justify-between">
          {/* Protocol info */}
          <div className="flex items-start space-x-4">
            {/* Protocol type icon */}
            <div className="flex-shrink-0">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                {getProtocolTypeIcon(protocol.protocolType)}
              </div>
            </div>

            {/* Protocol details */}
            <div className="flex-1 min-w-0">
              <h3 className="text-base font-semibold text-gray-900 truncate">
                {protocol.protocolName}
              </h3>
              <p className="mt-1 text-sm text-gray-500">
                {getProtocolDescription(protocol.protocolType)}
              </p>
              <p className="mt-1 text-xs text-gray-400">
                Form: {protocol.protocolFormCode}
              </p>

              {/* Weight info */}
              {protocol.weightKg && (
                <div className={`mt-2 flex items-center text-xs ${needsWeightUpdate ? 'text-orange-600' : 'text-gray-600'}`}>
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
                      d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"
                    />
                  </svg>
                  Weight: {formatWeight(protocol.weightKg)}
                  {needsWeightUpdate && (
                    <span className="ml-2 text-orange-600 font-medium">
                      (Update required)
                    </span>
                  )}
                </div>
              )}

              {/* Authorization date */}
              {isAuthorized && protocol.lastAuthorizedAt && (
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
                  Authorized on {formatDate(protocol.lastAuthorizedAt)}
                </div>
              )}

              {/* Last administration */}
              {protocol.lastAdministeredAt && (
                <div className="mt-1 flex items-center text-xs text-blue-600">
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
                      d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                  Last administered: {formatDateTime(protocol.lastAdministeredAt)}
                </div>
              )}

              {/* Next allowed administration */}
              {!protocol.canAdminister && protocol.nextAllowedAdministrationAt && (
                <div className="mt-1 flex items-center text-xs text-amber-600">
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
                  Next allowed: {formatDateTime(protocol.nextAllowedAdministrationAt)}
                </div>
              )}
            </div>
          </div>

          {/* Status badge */}
          <div className="flex-shrink-0">
            {getStatusBadge(protocol.authorizationStatus)}
          </div>
        </div>

        {/* Actions */}
        <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
          {/* View Details */}
          <button
            type="button"
            onClick={() => onViewDetails(protocol.protocolId)}
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

          {/* Update Weight button (only for authorized protocols with expired weight) */}
          {isAuthorized && needsWeightUpdate && (
            <button
              type="button"
              onClick={() => onUpdateWeight(protocol.protocolId)}
              className="btn btn-outline text-sm text-orange-600 border-orange-300 hover:bg-orange-50"
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
              Update Weight
            </button>
          )}

          {/* Authorize/Renew button */}
          {canAuthorize && (
            <button
              type="button"
              onClick={() => onAuthorize(protocol.protocolId)}
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
              {protocol.authorizationStatus === 'expired' ? 'Renew Authorization' : 'Sign Authorization'}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
