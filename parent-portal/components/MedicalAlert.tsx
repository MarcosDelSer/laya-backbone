/**
 * LAYA Parent Portal - MedicalAlert Component
 *
 * A component for displaying important medical alerts prominently.
 * Shows different visual styles based on alert level (info, warning, critical).
 */

import type { MedicalAlert as MedicalAlertType, AlertType, AlertLevel } from '../lib/types';

interface MedicalAlertProps {
  /** The medical alert to display */
  alert: MedicalAlertType;
  /** Whether to show in compact mode */
  compact?: boolean;
  /** Optional additional CSS classes */
  className?: string;
}

interface AlertLevelConfig {
  containerClass: string;
  iconClass: string;
  textClass: string;
  badgeClass: string;
  label: string;
}

const alertLevelConfig: Record<AlertLevel, AlertLevelConfig> = {
  info: {
    containerClass: 'bg-blue-50 border-blue-200',
    iconClass: 'bg-blue-100 text-blue-600',
    textClass: 'text-blue-800',
    badgeClass: 'badge-info',
    label: 'Info',
  },
  warning: {
    containerClass: 'bg-yellow-50 border-yellow-200',
    iconClass: 'bg-yellow-100 text-yellow-600',
    textClass: 'text-yellow-800',
    badgeClass: 'badge-warning',
    label: 'Warning',
  },
  critical: {
    containerClass: 'bg-red-50 border-red-200',
    iconClass: 'bg-red-100 text-red-600',
    textClass: 'text-red-800',
    badgeClass: 'badge-error',
    label: 'Critical',
  },
};

const alertTypeLabels: Record<AlertType, string> = {
  allergy: 'Allergy',
  medication: 'Medication',
  condition: 'Condition',
  dietary: 'Dietary',
  emergency: 'Emergency',
  general: 'General',
};

/**
 * Get the appropriate icon based on alert type.
 */
function AlertTypeIcon({ alertType, className }: { alertType: AlertType; className?: string }) {
  switch (alertType) {
    case 'allergy':
      return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
          />
        </svg>
      );
    case 'medication':
      return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
          />
        </svg>
      );
    case 'emergency':
      return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      );
    case 'dietary':
      return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
          />
        </svg>
      );
    case 'condition':
      return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
          />
        </svg>
      );
    case 'general':
    default:
      return (
        <svg className={className} fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
 * MedicalAlert displays important medical alerts for a child.
 * Shows different visual styles based on alert level.
 */
export function MedicalAlert({ alert, compact = false, className = '' }: MedicalAlertProps) {
  const levelConfig = alertLevelConfig[alert.alertLevel];

  if (compact) {
    return (
      <div
        className={`flex items-center space-x-2 rounded-md border px-3 py-2 ${levelConfig.containerClass} ${className}`}
        role="alert"
        aria-label={`${levelConfig.label} medical alert: ${alert.title}`}
      >
        <AlertTypeIcon alertType={alert.alertType} className={`h-4 w-4 ${levelConfig.textClass}`} />
        <span className={`text-sm font-medium ${levelConfig.textClass}`}>{alert.title}</span>
        <span className={`badge ${levelConfig.badgeClass} badge-sm`}>
          {alertTypeLabels[alert.alertType]}
        </span>
      </div>
    );
  }

  return (
    <div
      className={`rounded-lg border-2 p-4 ${levelConfig.containerClass} ${className}`}
      role="alert"
      aria-label={`${levelConfig.label} medical alert: ${alert.title}`}
    >
      <div className="flex items-start space-x-3">
        <div
          className={`flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full ${levelConfig.iconClass}`}
        >
          <AlertTypeIcon alertType={alert.alertType} className="h-5 w-5" />
        </div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between">
            <p className={`font-semibold ${levelConfig.textClass}`}>{alert.title}</p>
            <div className="flex items-center space-x-1">
              <span className={`badge ${levelConfig.badgeClass}`}>
                {levelConfig.label}
              </span>
              <span className="badge badge-outline badge-sm">
                {alertTypeLabels[alert.alertType]}
              </span>
            </div>
          </div>
          <p className="text-sm text-gray-700 mt-1">{alert.description}</p>
          {alert.actionRequired && (
            <div className="mt-2 p-2 bg-white bg-opacity-50 rounded border border-current border-opacity-20">
              <p className="text-sm">
                <span className="font-semibold">Action Required:</span> {alert.actionRequired}
              </p>
            </div>
          )}
          <div className="flex flex-wrap items-center gap-1 mt-2">
            {alert.displayOnDashboard && (
              <span className="badge badge-ghost badge-sm">Dashboard</span>
            )}
            {alert.displayOnAttendance && (
              <span className="badge badge-ghost badge-sm">Attendance</span>
            )}
            {alert.displayOnReports && (
              <span className="badge badge-ghost badge-sm">Reports</span>
            )}
            {alert.notifyOnCheckIn && (
              <span className="badge badge-ghost badge-sm">Check-in Alert</span>
            )}
          </div>
          {(alert.effectiveDate || alert.expirationDate) && (
            <p className="text-xs text-gray-500 mt-2">
              {alert.effectiveDate && (
                <span>Effective: {new Date(alert.effectiveDate).toLocaleDateString()}</span>
              )}
              {alert.effectiveDate && alert.expirationDate && <span className="mx-1">•</span>}
              {alert.expirationDate && (
                <span>Expires: {new Date(alert.expirationDate).toLocaleDateString()}</span>
              )}
            </p>
          )}
        </div>
      </div>
    </div>
  );
}
