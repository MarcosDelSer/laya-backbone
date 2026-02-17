import {
  DevelopmentalDomain,
  DomainSummary,
  MonthlySnapshot as MonthlySnapshotType,
  OverallProgress,
} from '../../lib/types';

/**
 * Props for the MonthlySnapshot component.
 */
export interface MonthlySnapshotProps {
  snapshot: MonthlySnapshotType;
  /** Optional click handler for when the card is clicked */
  onClick?: () => void;
  /** Whether to show expanded details */
  expanded?: boolean;
}

/**
 * Domain display information with bilingual support.
 */
interface DomainInfo {
  name: string;
  nameFr: string;
  colorClass: string;
  bgColorClass: string;
  progressColorClass: string;
  icon: React.ReactNode;
}

/**
 * Get display information for a developmental domain.
 */
function getDomainInfo(domain: DevelopmentalDomain): DomainInfo {
  switch (domain) {
    case 'affective':
      return {
        name: 'Affective',
        nameFr: 'Affectif',
        colorClass: 'text-pink-600',
        bgColorClass: 'bg-pink-100',
        progressColorClass: 'bg-pink-500',
        icon: (
          <svg
            className="h-5 w-5 text-pink-600"
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
        ),
      };
    case 'social':
      return {
        name: 'Social',
        nameFr: 'Social',
        colorClass: 'text-blue-600',
        bgColorClass: 'bg-blue-100',
        progressColorClass: 'bg-blue-500',
        icon: (
          <svg
            className="h-5 w-5 text-blue-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
            />
          </svg>
        ),
      };
    case 'language':
      return {
        name: 'Language',
        nameFr: 'Langage',
        colorClass: 'text-purple-600',
        bgColorClass: 'bg-purple-100',
        progressColorClass: 'bg-purple-500',
        icon: (
          <svg
            className="h-5 w-5 text-purple-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
            />
          </svg>
        ),
      };
    case 'cognitive':
      return {
        name: 'Cognitive',
        nameFr: 'Cognitif',
        colorClass: 'text-amber-600',
        bgColorClass: 'bg-amber-100',
        progressColorClass: 'bg-amber-500',
        icon: (
          <svg
            className="h-5 w-5 text-amber-600"
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
        ),
      };
    case 'gross_motor':
      return {
        name: 'Gross Motor',
        nameFr: 'Motricite globale',
        colorClass: 'text-green-600',
        bgColorClass: 'bg-green-100',
        progressColorClass: 'bg-green-500',
        icon: (
          <svg
            className="h-5 w-5 text-green-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M13 10V3L4 14h7v7l9-11h-7z"
            />
          </svg>
        ),
      };
    case 'fine_motor':
      return {
        name: 'Fine Motor',
        nameFr: 'Motricite fine',
        colorClass: 'text-teal-600',
        bgColorClass: 'bg-teal-100',
        progressColorClass: 'bg-teal-500',
        icon: (
          <svg
            className="h-5 w-5 text-teal-600"
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
        ),
      };
    default:
      return {
        name: 'Unknown',
        nameFr: 'Inconnu',
        colorClass: 'text-gray-600',
        bgColorClass: 'bg-gray-100',
        progressColorClass: 'bg-gray-500',
        icon: (
          <svg
            className="h-5 w-5 text-gray-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        ),
      };
  }
}

/**
 * Get display information for overall progress status.
 */
function getProgressInfo(progress: OverallProgress): {
  label: string;
  colorClass: string;
  bgColorClass: string;
  icon: React.ReactNode;
} {
  switch (progress) {
    case 'on_track':
      return {
        label: 'On Track',
        colorClass: 'text-green-700',
        bgColorClass: 'bg-green-100',
        icon: (
          <svg className="h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
              clipRule="evenodd"
            />
          </svg>
        ),
      };
    case 'needs_support':
      return {
        label: 'Needs Support',
        colorClass: 'text-amber-700',
        bgColorClass: 'bg-amber-100',
        icon: (
          <svg className="h-5 w-5 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
              clipRule="evenodd"
            />
          </svg>
        ),
      };
    case 'excelling':
      return {
        label: 'Excelling',
        colorClass: 'text-purple-700',
        bgColorClass: 'bg-purple-100',
        icon: (
          <svg className="h-5 w-5 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z" />
          </svg>
        ),
      };
    default:
      return {
        label: 'Unknown',
        colorClass: 'text-gray-500',
        bgColorClass: 'bg-gray-100',
        icon: null,
      };
  }
}

/**
 * Format snapshot month for display (e.g., "2026-02" -> "February 2026").
 */
function formatSnapshotMonth(snapshotMonth: string): string {
  const [year, month] = snapshotMonth.split('-');
  const date = new Date(parseInt(year), parseInt(month) - 1);
  return date.toLocaleDateString('en-US', {
    month: 'long',
    year: 'numeric',
  });
}

/**
 * Format age in months to a readable string.
 */
function formatAge(ageMonths: number): string {
  const years = Math.floor(ageMonths / 12);
  const months = ageMonths % 12;

  if (years === 0) {
    return `${months} month${months !== 1 ? 's' : ''}`;
  }
  if (months === 0) {
    return `${years} year${years !== 1 ? 's' : ''}`;
  }
  return `${years}y ${months}m`;
}

/**
 * Progress bar for domain summary.
 */
function DomainProgressBar({
  percentage,
  colorClass,
}: {
  percentage: number;
  colorClass: string;
}) {
  return (
    <div className="w-full bg-gray-200 rounded-full h-2">
      <div
        className={`h-2 rounded-full transition-all duration-300 ${colorClass}`}
        style={{ width: `${Math.min(100, Math.max(0, percentage))}%` }}
      />
    </div>
  );
}

/**
 * Domain summary row component.
 */
function DomainSummaryRow({ summary }: { summary: DomainSummary }) {
  const domainInfo = getDomainInfo(summary.domain);

  return (
    <div className="flex items-center space-x-3 py-2">
      <div
        className={`flex-shrink-0 flex h-8 w-8 items-center justify-center rounded-full ${domainInfo.bgColorClass}`}
      >
        {domainInfo.icon}
      </div>
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between mb-1">
          <p className="text-sm font-medium text-gray-900 truncate">
            {domainInfo.name}
          </p>
          <span className="text-sm font-semibold text-gray-700">
            {summary.progressPercentage}%
          </span>
        </div>
        <DomainProgressBar
          percentage={summary.progressPercentage}
          colorClass={domainInfo.progressColorClass}
        />
        <div className="flex items-center space-x-3 mt-1 text-xs text-gray-500">
          <span className="text-green-600">{summary.skillsCan} mastered</span>
          <span className="text-blue-600">{summary.skillsLearning} learning</span>
          <span className="text-amber-600">{summary.skillsNotYet} not yet</span>
        </div>
      </div>
    </div>
  );
}

/**
 * Section header component.
 */
function SectionHeader({ title, count }: { title: string; count?: number }) {
  return (
    <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-3">
      <h4 className="font-medium text-gray-900">{title}</h4>
      {count !== undefined && count > 0 && (
        <span className="text-sm text-gray-500">
          {count} {count === 1 ? 'item' : 'items'}
        </span>
      )}
    </div>
  );
}

/**
 * Empty state for missing data.
 */
function EmptyState({ message }: { message: string }) {
  return (
    <p className="text-sm text-gray-500 italic text-center py-4">{message}</p>
  );
}

/**
 * List item component for strengths and growth areas.
 */
function ListItem({
  text,
  type,
}: {
  text: string;
  type: 'strength' | 'growth';
}) {
  const iconClass =
    type === 'strength' ? 'text-green-500' : 'text-amber-500';

  return (
    <li className="flex items-start space-x-2 py-1">
      {type === 'strength' ? (
        <svg
          className={`h-5 w-5 flex-shrink-0 ${iconClass}`}
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fillRule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clipRule="evenodd"
          />
        </svg>
      ) : (
        <svg
          className={`h-5 w-5 flex-shrink-0 ${iconClass}`}
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fillRule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v3.586L7.707 9.293a1 1 0 00-1.414 1.414l3 3a1 1 0 001.414 0l3-3a1 1 0 00-1.414-1.414L11 10.586V7z"
            clipRule="evenodd"
          />
        </svg>
      )}
      <span className="text-sm text-gray-700">{text}</span>
    </li>
  );
}

/**
 * The ordered list of all 6 Quebec developmental domains.
 */
const ALL_DOMAINS: DevelopmentalDomain[] = [
  'affective',
  'social',
  'language',
  'cognitive',
  'gross_motor',
  'fine_motor',
];

/**
 * MonthlySnapshot component displays a monthly developmental summary
 * across all 6 Quebec-aligned developmental domains.
 *
 * Shows overall progress status, domain-by-domain progress bars,
 * strengths, growth areas, and educator recommendations.
 */
export function MonthlySnapshot({
  snapshot,
  onClick,
  expanded = false,
}: MonthlySnapshotProps) {
  const progressInfo = getProgressInfo(snapshot.overallProgress);
  const formattedMonth = formatSnapshotMonth(snapshot.snapshotMonth);

  // Get domain summaries in the correct order
  const domainSummaries: DomainSummary[] = [];
  if (snapshot.domainSummaries) {
    for (const domain of ALL_DOMAINS) {
      const summary = snapshot.domainSummaries[domain];
      if (summary) {
        domainSummaries.push(summary);
      }
    }
  }

  const isClickable = !!onClick;

  // Calculate overall stats from domain summaries
  const totalMastered = domainSummaries.reduce(
    (sum, s) => sum + s.skillsCan,
    0
  );
  const totalLearning = domainSummaries.reduce(
    (sum, s) => sum + s.skillsLearning,
    0
  );
  const totalNotYet = domainSummaries.reduce(
    (sum, s) => sum + s.skillsNotYet,
    0
  );

  return (
    <div
      className={`card ${isClickable ? 'cursor-pointer hover:shadow-md transition-shadow' : ''}`}
      onClick={onClick}
      role={isClickable ? 'button' : undefined}
      tabIndex={isClickable ? 0 : undefined}
      onKeyDown={
        isClickable
          ? (e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                onClick?.();
              }
            }
          : undefined
      }
    >
      {/* Card Header */}
      <div className="card-header">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary-100">
              <svg
                className="h-6 w-6 text-primary-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                />
              </svg>
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900">
                Monthly Snapshot
              </h3>
              <p className="text-sm text-gray-600">{formattedMonth}</p>
            </div>
          </div>
          <div className="flex items-center space-x-2">
            {/* Overall Progress Badge */}
            <span
              className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${progressInfo.bgColorClass} ${progressInfo.colorClass}`}
            >
              {progressInfo.icon}
              <span className="ml-1.5">{progressInfo.label}</span>
            </span>
            {/* Age Badge */}
            {snapshot.ageMonths && (
              <span className="hidden sm:inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                Age: {formatAge(snapshot.ageMonths)}
              </span>
            )}
            {isClickable && (
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
                  d="M9 5l7 7-7 7"
                />
              </svg>
            )}
          </div>
        </div>
      </div>

      <div className="card-body">
        {/* Summary Stats */}
        <div className="grid grid-cols-3 gap-4 mb-6">
          <div className="text-center p-3 bg-green-50 rounded-lg">
            <p className="text-2xl font-bold text-green-700">{totalMastered}</p>
            <p className="text-xs text-green-600">Skills Mastered</p>
          </div>
          <div className="text-center p-3 bg-blue-50 rounded-lg">
            <p className="text-2xl font-bold text-blue-700">{totalLearning}</p>
            <p className="text-xs text-blue-600">Currently Learning</p>
          </div>
          <div className="text-center p-3 bg-amber-50 rounded-lg">
            <p className="text-2xl font-bold text-amber-700">{totalNotYet}</p>
            <p className="text-xs text-amber-600">Not Yet Started</p>
          </div>
        </div>

        {/* Domain Progress Section */}
        {domainSummaries.length > 0 && (
          <div className="mb-6">
            <SectionHeader
              title="Progress by Domain"
              count={domainSummaries.length}
            />
            <div className="space-y-1 divide-y divide-gray-100">
              {domainSummaries.map((summary) => (
                <DomainSummaryRow key={summary.domain} summary={summary} />
              ))}
            </div>
          </div>
        )}

        {/* Strengths and Growth Areas - Two Column Layout */}
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 mb-6">
          {/* Strengths Section */}
          <div>
            <SectionHeader
              title="Strengths"
              count={snapshot.strengths?.length}
            />
            {snapshot.strengths && snapshot.strengths.length > 0 ? (
              <ul className="space-y-1">
                {(expanded
                  ? snapshot.strengths
                  : snapshot.strengths.slice(0, 3)
                ).map((strength, index) => (
                  <ListItem key={index} text={strength} type="strength" />
                ))}
                {!expanded && snapshot.strengths.length > 3 && (
                  <li className="text-sm text-primary-600 font-medium pt-1">
                    +{snapshot.strengths.length - 3} more
                  </li>
                )}
              </ul>
            ) : (
              <EmptyState message="No strengths noted" />
            )}
          </div>

          {/* Growth Areas Section */}
          <div>
            <SectionHeader
              title="Growth Areas"
              count={snapshot.growthAreas?.length}
            />
            {snapshot.growthAreas && snapshot.growthAreas.length > 0 ? (
              <ul className="space-y-1">
                {(expanded
                  ? snapshot.growthAreas
                  : snapshot.growthAreas.slice(0, 3)
                ).map((area, index) => (
                  <ListItem key={index} text={area} type="growth" />
                ))}
                {!expanded && snapshot.growthAreas.length > 3 && (
                  <li className="text-sm text-primary-600 font-medium pt-1">
                    +{snapshot.growthAreas.length - 3} more
                  </li>
                )}
              </ul>
            ) : (
              <EmptyState message="No growth areas noted" />
            )}
          </div>
        </div>

        {/* Recommendations Section */}
        {snapshot.recommendations && (
          <div>
            <SectionHeader title="Educator Recommendations" />
            <div className="bg-gray-50 rounded-lg p-4">
              <p className="text-sm text-gray-700 whitespace-pre-wrap">
                {snapshot.recommendations}
              </p>
            </div>
          </div>
        )}

        {/* Parent Sharing Status */}
        {snapshot.isParentShared && (
          <div className="mt-4 flex items-center text-sm text-green-600">
            <svg
              className="h-4 w-4 mr-1.5"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fillRule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                clipRule="evenodd"
              />
            </svg>
            <span>Shared with parents</span>
          </div>
        )}
      </div>
    </div>
  );
}
