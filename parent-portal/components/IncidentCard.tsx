'use client';

import {
  IncidentCategory,
  IncidentListItem,
  IncidentSeverity,
  IncidentStatus,
} from '../lib/types';

export interface IncidentCardProps {
  incident: IncidentListItem;
  onAcknowledge?: (incidentId: string) => void;
  onViewDetails?: (incidentId: string) => void;
}

/**
 * Format a date string for display.
 */
function formatDate(dateString: string): string {
  const date = new Date(dateString);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  // Check if it's today
  if (date.toDateString() === today.toDateString()) {
    return 'Today';
  }

  // Check if it's yesterday
  if (date.toDateString() === yesterday.toDateString()) {
    return 'Yesterday';
  }

  // Otherwise return formatted date
  return date.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
}

/**
 * Format a time string for display.
 */
function formatTime(timeString: string): string {
  // Handle both full datetime and time-only strings
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
 * Get severity configuration for styling.
 */
function getSeverityConfig(severity: IncidentSeverity): {
  label: string;
  badgeClass: string;
  indicatorClass: string;
} {
  switch (severity) {
    case 'minor':
      return {
        label: 'Minor',
        badgeClass: 'badge badge-info',
        indicatorClass: 'bg-blue-500',
      };
    case 'moderate':
      return {
        label: 'Moderate',
        badgeClass: 'badge badge-warning',
        indicatorClass: 'bg-yellow-500',
      };
    case 'serious':
      return {
        label: 'Serious',
        badgeClass: 'badge badge-error',
        indicatorClass: 'bg-orange-500',
      };
    case 'severe':
      return {
        label: 'Severe',
        badgeClass: 'badge bg-red-700 text-white',
        indicatorClass: 'bg-red-700',
      };
    default:
      return {
        label: 'Unknown',
        badgeClass: 'badge badge-neutral',
        indicatorClass: 'bg-gray-500',
      };
  }
}

/**
 * Get status configuration for styling.
 */
function getStatusConfig(status: IncidentStatus): {
  label: string;
  badgeClass: string;
} {
  switch (status) {
    case 'pending':
      return {
        label: 'Pending Review',
        badgeClass: 'badge badge-warning',
      };
    case 'acknowledged':
      return {
        label: 'Acknowledged',
        badgeClass: 'badge badge-success',
      };
    case 'resolved':
      return {
        label: 'Resolved',
        badgeClass: 'badge badge-neutral',
      };
    default:
      return {
        label: 'Unknown',
        badgeClass: 'badge badge-neutral',
      };
  }
}

/**
 * Get icon component for incident category.
 */
function getCategoryIcon(category: IncidentCategory): React.ReactNode {
  switch (category) {
    case 'bump':
      return (
        <svg
          className="h-6 w-6 text-orange-600"
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
    case 'fall':
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
            d="M19 14l-7 7m0 0l-7-7m7 7V3"
          />
        </svg>
      );
    case 'bite':
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
            d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      );
    case 'scratch':
      return (
        <svg
          className="h-6 w-6 text-yellow-600"
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
    case 'behavioral':
      return (
        <svg
          className="h-6 w-6 text-indigo-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
          />
        </svg>
      );
    case 'medical':
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
    case 'allergic_reaction':
      return (
        <svg
          className="h-6 w-6 text-pink-600"
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
      );
    case 'other':
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
            d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      );
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
 * IncidentCard component displays an incident summary with severity indicator,
 * type icon, time, and acknowledge button.
 */
export function IncidentCard({
  incident,
  onAcknowledge,
  onViewDetails,
}: IncidentCardProps) {
  const severityConfig = getSeverityConfig(incident.severity);
  const statusConfig = getStatusConfig(incident.status);
  const isPending = incident.status === 'pending';

  return (
    <div className="card relative overflow-hidden">
      {/* Severity indicator bar */}
      <div
        className={`absolute left-0 top-0 bottom-0 w-1 ${severityConfig.indicatorClass}`}
      />

      <div className="card-body pl-5">
        <div className="flex items-start justify-between">
          {/* Incident info */}
          <div className="flex items-start space-x-4">
            {/* Category icon */}
            <div className="flex-shrink-0">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                {getCategoryIcon(incident.category)}
              </div>
            </div>

            {/* Incident details */}
            <div className="flex-1 min-w-0">
              <div className="flex items-center space-x-2 flex-wrap">
                <h3 className="text-base font-semibold text-gray-900">
                  {getCategoryLabel(incident.category)} Incident
                </h3>
                <span className={severityConfig.badgeClass}>
                  {severityConfig.label}
                </span>
              </div>

              <p className="mt-1 text-sm text-gray-600">
                {incident.childName}
              </p>

              <div className="mt-1 flex items-center text-sm text-gray-500 space-x-2">
                <svg
                  className="h-4 w-4"
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
                <span>
                  {formatDate(incident.date)} at {formatTime(incident.time)}
                </span>
              </div>

              {/* Description preview */}
              <p className="mt-2 text-sm text-gray-700 line-clamp-2">
                {incident.description}
              </p>

              {/* Follow-up indicator */}
              {incident.requiresFollowUp && (
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
                  Follow-up required
                </div>
              )}
            </div>
          </div>

          {/* Status badge */}
          <div className="flex-shrink-0 hidden sm:block">
            <span className={statusConfig.badgeClass}>
              {statusConfig.label}
            </span>
          </div>
        </div>

        {/* Actions */}
        <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
          {/* View Details button */}
          {onViewDetails && (
            <button
              type="button"
              onClick={() => onViewDetails(incident.id)}
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
          )}

          {/* Acknowledge button (only for pending incidents) */}
          {isPending && onAcknowledge && (
            <button
              type="button"
              onClick={() => onAcknowledge(incident.id)}
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
                  d="M5 13l4 4L19 7"
                />
              </svg>
              Acknowledge
            </button>
          )}

          {/* Acknowledged indicator */}
          {incident.status === 'acknowledged' && (
            <div className="flex items-center text-sm text-green-600">
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
              Acknowledged
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
