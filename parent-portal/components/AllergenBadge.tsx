import type { AllergenSeverity } from '../lib/types';

interface AllergenBadgeProps {
  /** Name of the allergen to display */
  allergen: string;
  /** Severity level of the allergy */
  severity: AllergenSeverity;
  /** Size variant for the badge */
  size?: 'sm' | 'md';
  /** Whether to show the severity icon */
  showIcon?: boolean;
}

interface SeverityConfig {
  label: string;
  badgeClass: string;
  textClass: string;
  icon: React.ReactNode | null;
}

const severityConfig: Record<AllergenSeverity, SeverityConfig> = {
  severe: {
    label: 'SEVERE',
    badgeClass: 'bg-red-100 border-red-600 text-red-700',
    textClass: 'text-red-700',
    icon: (
      <svg className="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
        <path
          fillRule="evenodd"
          d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
          clipRule="evenodd"
        />
      </svg>
    ),
  },
  moderate: {
    label: 'Moderate',
    badgeClass: 'bg-orange-100 border-orange-500 text-orange-700',
    textClass: 'text-orange-700',
    icon: (
      <svg className="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
        <path
          fillRule="evenodd"
          d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
          clipRule="evenodd"
        />
      </svg>
    ),
  },
  mild: {
    label: 'Mild',
    badgeClass: 'bg-yellow-100 border-yellow-500 text-yellow-700',
    textClass: 'text-yellow-700',
    icon: null,
  },
};

/**
 * AllergenBadge displays an allergen indicator with severity-based styling.
 * Used to highlight allergens in menu items and dietary profiles.
 */
export function AllergenBadge({
  allergen,
  severity,
  size = 'md',
  showIcon = true,
}: AllergenBadgeProps) {
  const config = severityConfig[severity];
  const sizeClasses = size === 'sm' ? 'text-xs px-2 py-0.5' : 'text-sm px-2.5 py-1';

  return (
    <span
      className={`inline-flex items-center rounded-full border font-medium ${config.badgeClass} ${sizeClasses}`}
      role="status"
      aria-label={`${allergen} allergy - ${config.label} severity`}
    >
      {showIcon && config.icon}
      <span>{allergen}</span>
      {severity === 'severe' && (
        <span className="ml-1 text-xs font-bold">(!)</span>
      )}
    </span>
  );
}
