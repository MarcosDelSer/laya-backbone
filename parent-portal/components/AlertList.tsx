'use client';

import type { AlertItem, AlertPriority, AlertType } from '../lib/types';

export interface AlertListProps {
  alerts: AlertItem[];
  onAcknowledge?: (alertId: string) => void;
  showAcknowledgeButton?: boolean;
  maxItems?: number;
  emptyMessage?: string;
}

export interface AlertItemRowProps {
  alert: AlertItem;
  onAcknowledge?: (alertId: string) => void;
  showAcknowledgeButton?: boolean;
}

/**
 * Get priority-based color configuration for alert styling.
 */
function getPriorityColors(priority: AlertPriority): {
  bg: string;
  border: string;
  text: string;
  badge: string;
  dot: string;
  icon: string;
} {
  switch (priority) {
    case 'critical':
      return {
        bg: 'bg-red-50',
        border: 'border-red-200',
        text: 'text-red-800',
        badge: 'bg-red-100 text-red-800',
        dot: 'bg-red-500',
        icon: 'text-red-500',
      };
    case 'high':
      return {
        bg: 'bg-orange-50',
        border: 'border-orange-200',
        text: 'text-orange-800',
        badge: 'bg-orange-100 text-orange-800',
        dot: 'bg-orange-500',
        icon: 'text-orange-500',
      };
    case 'medium':
      return {
        bg: 'bg-yellow-50',
        border: 'border-yellow-200',
        text: 'text-yellow-800',
        badge: 'bg-yellow-100 text-yellow-800',
        dot: 'bg-yellow-500',
        icon: 'text-yellow-500',
      };
    case 'low':
    default:
      return {
        bg: 'bg-blue-50',
        border: 'border-blue-200',
        text: 'text-blue-800',
        badge: 'bg-blue-100 text-blue-800',
        dot: 'bg-blue-500',
        icon: 'text-blue-500',
      };
  }
}

/**
 * Get icon for alert type.
 */
function getAlertTypeIcon(alertType: AlertType, colorClass: string): JSX.Element {
  const iconClass = `h-5 w-5 ${colorClass}`;

  switch (alertType) {
    case 'occupancy':
      return (
        <svg className={iconClass} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
          />
        </svg>
      );
    case 'staffing':
      return (
        <svg className={iconClass} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
          />
        </svg>
      );
    case 'compliance':
      return (
        <svg className={iconClass} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
          />
        </svg>
      );
    case 'attendance':
      return (
        <svg className={iconClass} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
          />
        </svg>
      );
    case 'general':
    default:
      return (
        <svg className={iconClass} fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
 * Get user-friendly label for priority level.
 */
function getPriorityLabel(priority: AlertPriority): string {
  switch (priority) {
    case 'critical':
      return 'Critical';
    case 'high':
      return 'High';
    case 'medium':
      return 'Medium';
    case 'low':
    default:
      return 'Low';
  }
}

/**
 * Format timestamp for display.
 */
function formatTimestamp(dateString: string): string {
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);
  const diffDays = Math.floor(diffHours / 24);

  if (diffMins < 1) {
    return 'Just now';
  } else if (diffMins < 60) {
    return `${diffMins}m ago`;
  } else if (diffHours < 24) {
    return `${diffHours}h ago`;
  } else if (diffDays < 7) {
    return `${diffDays}d ago`;
  } else {
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
    });
  }
}

/**
 * AlertItemRow component displays a single alert with priority styling.
 */
export function AlertItemRow({
  alert,
  onAcknowledge,
  showAcknowledgeButton = true,
}: AlertItemRowProps) {
  const colors = getPriorityColors(alert.priority);
  const priorityLabel = getPriorityLabel(alert.priority);
  const typeIcon = getAlertTypeIcon(alert.alertType, colors.icon);

  return (
    <div
      className={`flex items-start gap-3 p-4 rounded-lg border ${colors.bg} ${colors.border} ${
        alert.isAcknowledged ? 'opacity-60' : ''
      }`}
    >
      {/* Alert Type Icon */}
      <div className="flex-shrink-0 mt-0.5">{typeIcon}</div>

      {/* Alert Content */}
      <div className="flex-1 min-w-0">
        <div className="flex items-start justify-between gap-2">
          <div className="flex-1">
            <div className="flex items-center gap-2 flex-wrap">
              <h4 className={`text-sm font-medium ${colors.text}`}>{alert.title}</h4>
              <span
                className={`inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium ${colors.badge}`}
              >
                {priorityLabel}
              </span>
              {alert.isAcknowledged && (
                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                  Acknowledged
                </span>
              )}
            </div>
            <p className="mt-1 text-sm text-gray-600">{alert.message}</p>
            {alert.groupName && (
              <p className="mt-1 text-xs text-gray-500">Group: {alert.groupName}</p>
            )}
          </div>

          {/* Timestamp */}
          <span className="flex-shrink-0 text-xs text-gray-400">
            {formatTimestamp(alert.createdAt)}
          </span>
        </div>

        {/* Action Button */}
        {showAcknowledgeButton && !alert.isAcknowledged && onAcknowledge && (
          <div className="mt-3">
            <button
              type="button"
              onClick={() => onAcknowledge(alert.alertId)}
              className="text-xs font-medium text-gray-600 hover:text-gray-900 underline"
            >
              Acknowledge
            </button>
          </div>
        )}
      </div>
    </div>
  );
}

/**
 * AlertItemCompact - A more compact version for dense layouts.
 */
export function AlertItemCompact({ alert }: { alert: AlertItem }) {
  const colors = getPriorityColors(alert.priority);

  return (
    <div className="flex items-center gap-2 py-2">
      {/* Priority Indicator */}
      <span className={`flex-shrink-0 h-2 w-2 rounded-full ${colors.dot}`} />

      {/* Title */}
      <span className={`flex-1 text-sm truncate ${colors.text}`}>{alert.title}</span>

      {/* Timestamp */}
      <span className="flex-shrink-0 text-xs text-gray-400">
        {formatTimestamp(alert.createdAt)}
      </span>
    </div>
  );
}

/**
 * AlertList component displays a list of dashboard alerts with priority styling.
 * Supports acknowledging alerts and filtering by priority.
 */
export function AlertList({
  alerts,
  onAcknowledge,
  showAcknowledgeButton = true,
  maxItems,
  emptyMessage = 'No alerts at this time',
}: AlertListProps) {
  // Apply maxItems limit if specified
  const displayedAlerts = maxItems ? alerts.slice(0, maxItems) : alerts;

  if (displayedAlerts.length === 0) {
    return (
      <div className="text-center py-8">
        <svg
          className="mx-auto h-12 w-12 text-gray-400"
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
        <p className="mt-2 text-sm text-gray-500">{emptyMessage}</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {displayedAlerts.map((alert) => (
        <AlertItemRow
          key={alert.alertId}
          alert={alert}
          onAcknowledge={onAcknowledge}
          showAcknowledgeButton={showAcknowledgeButton}
        />
      ))}
      {maxItems && alerts.length > maxItems && (
        <p className="text-center text-sm text-gray-500 pt-2">
          +{alerts.length - maxItems} more alert{alerts.length - maxItems > 1 ? 's' : ''}
        </p>
      )}
    </div>
  );
}

/**
 * AlertSummaryBadge - Shows a count badge for alert summary.
 */
export function AlertSummaryBadge({
  count,
  priority,
}: {
  count: number;
  priority: AlertPriority;
}) {
  const colors = getPriorityColors(priority);
  const label = getPriorityLabel(priority);

  if (count === 0) return null;

  return (
    <span
      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colors.badge}`}
    >
      <span className={`mr-1.5 h-2 w-2 rounded-full ${colors.dot}`} />
      {count} {label}
    </span>
  );
}
