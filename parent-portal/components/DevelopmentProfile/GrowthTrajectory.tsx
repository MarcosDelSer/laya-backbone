import {
  DevelopmentalDomain,
  GrowthDataPoint,
  GrowthTrajectory as GrowthTrajectoryType,
} from '../../lib/types';

/**
 * Props for the GrowthTrajectory component.
 */
export interface GrowthTrajectoryProps {
  trajectory: GrowthTrajectoryType;
  /** Optional click handler for when the card is clicked */
  onClick?: () => void;
  /** Whether to show expanded details with all data points */
  expanded?: boolean;
  /** Optional filter to show specific domains */
  selectedDomains?: DevelopmentalDomain[];
}

/**
 * Domain display information with bilingual support.
 */
interface DomainInfo {
  name: string;
  nameFr: string;
  colorClass: string;
  bgColorClass: string;
  lineColorClass: string;
  dotColorClass: string;
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
        lineColorClass: 'bg-pink-500',
        dotColorClass: 'bg-pink-600',
      };
    case 'social':
      return {
        name: 'Social',
        nameFr: 'Social',
        colorClass: 'text-blue-600',
        bgColorClass: 'bg-blue-100',
        lineColorClass: 'bg-blue-500',
        dotColorClass: 'bg-blue-600',
      };
    case 'language':
      return {
        name: 'Language',
        nameFr: 'Langage',
        colorClass: 'text-purple-600',
        bgColorClass: 'bg-purple-100',
        lineColorClass: 'bg-purple-500',
        dotColorClass: 'bg-purple-600',
      };
    case 'cognitive':
      return {
        name: 'Cognitive',
        nameFr: 'Cognitif',
        colorClass: 'text-amber-600',
        bgColorClass: 'bg-amber-100',
        lineColorClass: 'bg-amber-500',
        dotColorClass: 'bg-amber-600',
      };
    case 'gross_motor':
      return {
        name: 'Gross Motor',
        nameFr: 'Motricite globale',
        colorClass: 'text-green-600',
        bgColorClass: 'bg-green-100',
        lineColorClass: 'bg-green-500',
        dotColorClass: 'bg-green-600',
      };
    case 'fine_motor':
      return {
        name: 'Fine Motor',
        nameFr: 'Motricite fine',
        colorClass: 'text-teal-600',
        bgColorClass: 'bg-teal-100',
        lineColorClass: 'bg-teal-500',
        dotColorClass: 'bg-teal-600',
      };
    default:
      return {
        name: 'Unknown',
        nameFr: 'Inconnu',
        colorClass: 'text-gray-600',
        bgColorClass: 'bg-gray-100',
        lineColorClass: 'bg-gray-500',
        dotColorClass: 'bg-gray-600',
      };
  }
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
 * Format month string for display (e.g., "2026-02" -> "Feb 2026").
 */
function formatMonth(monthStr: string): string {
  const [year, month] = monthStr.split('-');
  const date = new Date(parseInt(year), parseInt(month) - 1);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    year: 'numeric',
  });
}

/**
 * Format month string for short display (e.g., "2026-02" -> "Feb").
 */
function formatMonthShort(monthStr: string): string {
  const [year, month] = monthStr.split('-');
  const date = new Date(parseInt(year), parseInt(month) - 1);
  return date.toLocaleDateString('en-US', {
    month: 'short',
  });
}

/**
 * Format age in months to a readable string.
 */
function formatAge(ageMonths: number): string {
  const years = Math.floor(ageMonths / 12);
  const months = ageMonths % 12;

  if (years === 0) {
    return `${months}mo`;
  }
  if (months === 0) {
    return `${years}yr`;
  }
  return `${years}y ${months}m`;
}

/**
 * Get trend direction and label based on score changes.
 */
function getTrendInfo(dataPoints: GrowthDataPoint[]): {
  direction: 'up' | 'down' | 'stable';
  label: string;
  colorClass: string;
  icon: React.ReactNode;
} {
  if (dataPoints.length < 2) {
    return {
      direction: 'stable',
      label: 'Not enough data',
      colorClass: 'text-gray-500',
      icon: (
        <svg className="h-5 w-5 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
          <path
            fillRule="evenodd"
            d="M7 9a1 1 0 000 2h6a1 1 0 100-2H7z"
            clipRule="evenodd"
          />
        </svg>
      ),
    };
  }

  const firstScore = dataPoints[0].overallScore;
  const lastScore = dataPoints[dataPoints.length - 1].overallScore;
  const change = lastScore - firstScore;

  if (change > 5) {
    return {
      direction: 'up',
      label: 'Improving',
      colorClass: 'text-green-600',
      icon: (
        <svg className="h-5 w-5 text-green-600" fill="currentColor" viewBox="0 0 20 20">
          <path
            fillRule="evenodd"
            d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z"
            clipRule="evenodd"
          />
        </svg>
      ),
    };
  }
  if (change < -5) {
    return {
      direction: 'down',
      label: 'Needs Attention',
      colorClass: 'text-amber-600',
      icon: (
        <svg className="h-5 w-5 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
          <path
            fillRule="evenodd"
            d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z"
            clipRule="evenodd"
          />
        </svg>
      ),
    };
  }

  return {
    direction: 'stable',
    label: 'Stable',
    colorClass: 'text-blue-600',
    icon: (
      <svg className="h-5 w-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
        <path
          fillRule="evenodd"
          d="M7 9a1 1 0 000 2h6a1 1 0 100-2H7z"
          clipRule="evenodd"
        />
      </svg>
    ),
  };
}

/**
 * Get age-appropriate expectation message based on age.
 */
function getAgeExpectation(ageMonths: number): string {
  if (ageMonths < 12) {
    return 'Infants typically focus on sensory exploration and early motor development';
  }
  if (ageMonths < 24) {
    return 'Toddlers develop language, walking, and begin social interactions';
  }
  if (ageMonths < 36) {
    return 'Two-year-olds expand vocabulary and refine motor skills';
  }
  if (ageMonths < 48) {
    return 'Preschoolers develop problem-solving and cooperative play skills';
  }
  if (ageMonths < 60) {
    return 'Children prepare for school with literacy and social-emotional growth';
  }
  return 'School-age children refine academic and complex social skills';
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
          {count} {count === 1 ? 'point' : 'points'}
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
 * Alert item component for displaying trajectory alerts.
 */
function AlertItem({ alert }: { alert: string }) {
  return (
    <li className="flex items-start space-x-2 py-2">
      <svg
        className="h-5 w-5 flex-shrink-0 text-amber-500 mt-0.5"
        fill="currentColor"
        viewBox="0 0 20 20"
      >
        <path
          fillRule="evenodd"
          d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
          clipRule="evenodd"
        />
      </svg>
      <span className="text-sm text-gray-700">{alert}</span>
    </li>
  );
}

/**
 * Simple bar visualization for a score.
 */
function ScoreBar({
  score,
  colorClass,
  label,
}: {
  score: number;
  colorClass: string;
  label?: string;
}) {
  return (
    <div className="flex items-center space-x-2">
      {label && (
        <span className="text-xs text-gray-600 w-20 truncate">{label}</span>
      )}
      <div className="flex-1 bg-gray-200 rounded-full h-2">
        <div
          className={`h-2 rounded-full transition-all duration-300 ${colorClass}`}
          style={{ width: `${Math.min(100, Math.max(0, score))}%` }}
        />
      </div>
      <span className="text-xs font-medium text-gray-700 w-8 text-right">
        {Math.round(score)}%
      </span>
    </div>
  );
}

/**
 * Timeline data point visualization.
 */
function TimelinePoint({
  dataPoint,
  isLast,
  domains,
}: {
  dataPoint: GrowthDataPoint;
  isLast: boolean;
  domains: DevelopmentalDomain[];
}) {
  return (
    <div className="flex items-start space-x-3">
      {/* Timeline dot and line */}
      <div className="flex flex-col items-center">
        <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100">
          <span className="text-xs font-semibold text-primary-700">
            {Math.round(dataPoint.overallScore)}
          </span>
        </div>
        {!isLast && <div className="w-0.5 h-full min-h-[60px] bg-gray-200" />}
      </div>

      {/* Data point content */}
      <div className="flex-1 pb-4">
        <div className="flex items-center justify-between mb-2">
          <span className="text-sm font-medium text-gray-900">
            {formatMonth(dataPoint.month)}
          </span>
          {dataPoint.ageMonths && (
            <span className="text-xs text-gray-500">
              Age: {formatAge(dataPoint.ageMonths)}
            </span>
          )}
        </div>

        {/* Domain scores */}
        <div className="space-y-1.5">
          {domains.map((domain) => {
            const domainInfo = getDomainInfo(domain);
            const score = dataPoint.domainScores[domain] ?? 0;
            return (
              <ScoreBar
                key={domain}
                score={score}
                colorClass={domainInfo.lineColorClass}
                label={domainInfo.name}
              />
            );
          })}
        </div>
      </div>
    </div>
  );
}

/**
 * Mini chart showing overall score trend across data points.
 */
function TrendChart({
  dataPoints,
  height = 80,
}: {
  dataPoints: GrowthDataPoint[];
  height?: number;
}) {
  if (dataPoints.length === 0) {
    return null;
  }

  const maxScore = 100;
  const minScore = 0;
  const width = 100; // percentage

  // Calculate positions for each data point
  const points = dataPoints.map((dp, index) => {
    const x = dataPoints.length > 1 ? (index / (dataPoints.length - 1)) * 100 : 50;
    const y = ((maxScore - dp.overallScore) / (maxScore - minScore)) * height;
    return { x, y, score: dp.overallScore, month: dp.month };
  });

  // Create SVG path for the line
  const pathD = points
    .map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`)
    .join(' ');

  // Create filled area path
  const areaPathD = `${pathD} L ${points[points.length - 1].x} ${height} L ${points[0].x} ${height} Z`;

  return (
    <div className="relative" style={{ height: `${height}px` }}>
      {/* Y-axis labels */}
      <div className="absolute left-0 top-0 h-full flex flex-col justify-between text-xs text-gray-400 pr-2">
        <span>100</span>
        <span>50</span>
        <span>0</span>
      </div>

      {/* Chart area */}
      <div className="ml-8 relative h-full">
        <svg
          className="w-full h-full"
          viewBox={`0 0 100 ${height}`}
          preserveAspectRatio="none"
        >
          {/* Grid lines */}
          <line
            x1="0"
            y1={height / 2}
            x2="100"
            y2={height / 2}
            stroke="#e5e7eb"
            strokeWidth="1"
            vectorEffect="non-scaling-stroke"
          />

          {/* Area fill */}
          <path
            d={areaPathD}
            fill="url(#gradient)"
            fillOpacity="0.3"
          />

          {/* Line */}
          <path
            d={pathD}
            fill="none"
            stroke="#6366f1"
            strokeWidth="2"
            vectorEffect="non-scaling-stroke"
          />

          {/* Dots */}
          {points.map((p, i) => (
            <circle
              key={i}
              cx={p.x}
              cy={p.y}
              r="4"
              fill="#4f46e5"
              stroke="white"
              strokeWidth="2"
              vectorEffect="non-scaling-stroke"
            />
          ))}

          {/* Gradient definition */}
          <defs>
            <linearGradient id="gradient" x1="0%" y1="0%" x2="0%" y2="100%">
              <stop offset="0%" stopColor="#6366f1" />
              <stop offset="100%" stopColor="#6366f1" stopOpacity="0" />
            </linearGradient>
          </defs>
        </svg>

        {/* X-axis labels */}
        <div className="absolute bottom-0 left-0 right-0 flex justify-between transform translate-y-4 text-xs text-gray-400">
          {dataPoints.length > 0 && (
            <>
              <span>{formatMonthShort(dataPoints[0].month)}</span>
              {dataPoints.length > 2 && (
                <span>
                  {formatMonthShort(dataPoints[Math.floor(dataPoints.length / 2)].month)}
                </span>
              )}
              {dataPoints.length > 1 && (
                <span>{formatMonthShort(dataPoints[dataPoints.length - 1].month)}</span>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}

/**
 * Domain legend component.
 */
function DomainLegend({ domains }: { domains: DevelopmentalDomain[] }) {
  return (
    <div className="flex flex-wrap gap-3 justify-center">
      {domains.map((domain) => {
        const info = getDomainInfo(domain);
        return (
          <div key={domain} className="flex items-center space-x-1.5">
            <div className={`w-3 h-3 rounded-full ${info.dotColorClass}`} />
            <span className="text-xs text-gray-600">{info.name}</span>
          </div>
        );
      })}
    </div>
  );
}

/**
 * GrowthTrajectory component visualizes a child's development trends
 * over time with age-appropriate expectations.
 *
 * Shows overall score trend, domain-specific progress, alerts,
 * and age-appropriate developmental expectations.
 */
export function GrowthTrajectory({
  trajectory,
  onClick,
  expanded = false,
  selectedDomains,
}: GrowthTrajectoryProps) {
  const isClickable = !!onClick;
  const domains = selectedDomains || ALL_DOMAINS;
  const dataPoints = trajectory.dataPoints || [];
  const displayDataPoints = expanded ? dataPoints : dataPoints.slice(-4);
  const trendInfo = getTrendInfo(dataPoints);

  // Get latest data point for current stats
  const latestDataPoint = dataPoints.length > 0 ? dataPoints[dataPoints.length - 1] : null;
  const currentAgeMonths = latestDataPoint?.ageMonths;

  // Calculate domain trends
  const domainTrends = domains.map((domain) => {
    if (dataPoints.length < 2) {
      return { domain, change: 0, latest: 0 };
    }
    const firstScore = dataPoints[0].domainScores[domain] ?? 0;
    const lastScore = dataPoints[dataPoints.length - 1].domainScores[domain] ?? 0;
    return { domain, change: lastScore - firstScore, latest: lastScore };
  });

  // Sort domains by latest score for display
  const sortedDomainTrends = [...domainTrends].sort((a, b) => b.latest - a.latest);

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
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100">
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
                  d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                />
              </svg>
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900">
                Growth Trajectory
              </h3>
              <p className="text-sm text-gray-600">
                Development trends over time
              </p>
            </div>
          </div>
          <div className="flex items-center space-x-2">
            {/* Trend Badge */}
            <span
              className={`inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-50 ${trendInfo.colorClass}`}
            >
              {trendInfo.icon}
              <span className="ml-1.5">{trendInfo.label}</span>
            </span>
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
        {dataPoints.length === 0 ? (
          <EmptyState message="No growth data available yet. Snapshots will appear here over time." />
        ) : (
          <>
            {/* Overall Trend Chart */}
            <div className="mb-6">
              <SectionHeader title="Overall Progress Trend" count={dataPoints.length} />
              <div className="bg-gray-50 rounded-lg p-4 mb-4">
                <TrendChart dataPoints={dataPoints} height={80} />
              </div>
              <DomainLegend domains={domains} />
            </div>

            {/* Current Stats Summary */}
            {latestDataPoint && (
              <div className="grid grid-cols-2 gap-4 mb-6 sm:grid-cols-4">
                <div className="text-center p-3 bg-indigo-50 rounded-lg">
                  <p className="text-2xl font-bold text-indigo-700">
                    {Math.round(latestDataPoint.overallScore)}%
                  </p>
                  <p className="text-xs text-indigo-600">Current Score</p>
                </div>
                <div className="text-center p-3 bg-gray-50 rounded-lg">
                  <p className="text-2xl font-bold text-gray-700">
                    {dataPoints.length}
                  </p>
                  <p className="text-xs text-gray-600">Data Points</p>
                </div>
                {currentAgeMonths && (
                  <div className="text-center p-3 bg-blue-50 rounded-lg">
                    <p className="text-2xl font-bold text-blue-700">
                      {formatAge(currentAgeMonths)}
                    </p>
                    <p className="text-xs text-blue-600">Current Age</p>
                  </div>
                )}
                <div className="text-center p-3 bg-green-50 rounded-lg">
                  <p className="text-2xl font-bold text-green-700">
                    {sortedDomainTrends.filter((d) => d.change > 0).length}
                  </p>
                  <p className="text-xs text-green-600">Improving Domains</p>
                </div>
              </div>
            )}

            {/* Age-Appropriate Expectations */}
            {currentAgeMonths && (
              <div className="mb-6">
                <SectionHeader title="Age-Appropriate Expectations" />
                <div className="bg-blue-50 border border-blue-100 rounded-lg p-4">
                  <div className="flex items-start space-x-3">
                    <svg
                      className="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5"
                      fill="currentColor"
                      viewBox="0 0 20 20"
                    >
                      <path
                        fillRule="evenodd"
                        d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                        clipRule="evenodd"
                      />
                    </svg>
                    <p className="text-sm text-blue-800">
                      {getAgeExpectation(currentAgeMonths)}
                    </p>
                  </div>
                </div>
              </div>
            )}

            {/* Domain Progress Summary */}
            <div className="mb-6">
              <SectionHeader title="Progress by Domain" />
              <div className="space-y-3">
                {sortedDomainTrends.map(({ domain, change, latest }) => {
                  const info = getDomainInfo(domain);
                  const trendIcon =
                    change > 5 ? (
                      <svg className="h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path
                          fillRule="evenodd"
                          d="M5.293 9.707a1 1 0 010-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 01-1.414 1.414L11 7.414V15a1 1 0 11-2 0V7.414L6.707 9.707a1 1 0 01-1.414 0z"
                          clipRule="evenodd"
                        />
                      </svg>
                    ) : change < -5 ? (
                      <svg className="h-4 w-4 text-amber-500" fill="currentColor" viewBox="0 0 20 20">
                        <path
                          fillRule="evenodd"
                          d="M14.707 10.293a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 111.414-1.414L9 12.586V5a1 1 0 012 0v7.586l2.293-2.293a1 1 0 011.414 0z"
                          clipRule="evenodd"
                        />
                      </svg>
                    ) : (
                      <svg className="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fillRule="evenodd" d="M7 9a1 1 0 000 2h6a1 1 0 100-2H7z" clipRule="evenodd" />
                      </svg>
                    );

                  return (
                    <div
                      key={domain}
                      className="flex items-center space-x-3 py-2 border-b border-gray-100 last:border-b-0"
                    >
                      <div
                        className={`flex h-8 w-8 items-center justify-center rounded-full ${info.bgColorClass}`}
                      >
                        <div className={`w-3 h-3 rounded-full ${info.dotColorClass}`} />
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center justify-between mb-1">
                          <p className={`text-sm font-medium ${info.colorClass}`}>
                            {info.name}
                          </p>
                          <div className="flex items-center space-x-2">
                            {trendIcon}
                            <span className="text-sm font-semibold text-gray-700">
                              {Math.round(latest)}%
                            </span>
                          </div>
                        </div>
                        <div className="w-full bg-gray-200 rounded-full h-1.5">
                          <div
                            className={`h-1.5 rounded-full transition-all duration-300 ${info.lineColorClass}`}
                            style={{ width: `${Math.min(100, Math.max(0, latest))}%` }}
                          />
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>

            {/* Trend Analysis */}
            {trajectory.trendAnalysis && (
              <div className="mb-6">
                <SectionHeader title="Trend Analysis" />
                <div className="bg-gray-50 rounded-lg p-4">
                  <p className="text-sm text-gray-700 whitespace-pre-wrap">
                    {trajectory.trendAnalysis}
                  </p>
                </div>
              </div>
            )}

            {/* Alerts Section */}
            {trajectory.alerts && trajectory.alerts.length > 0 && (
              <div className="mb-6">
                <SectionHeader title="Alerts & Recommendations" count={trajectory.alerts.length} />
                <div className="bg-amber-50 border border-amber-100 rounded-lg p-4">
                  <ul className="space-y-1">
                    {(expanded ? trajectory.alerts : trajectory.alerts.slice(0, 3)).map(
                      (alert, index) => (
                        <AlertItem key={index} alert={alert} />
                      )
                    )}
                    {!expanded && trajectory.alerts.length > 3 && (
                      <li className="text-sm text-amber-600 font-medium pt-2">
                        +{trajectory.alerts.length - 3} more alerts
                      </li>
                    )}
                  </ul>
                </div>
              </div>
            )}

            {/* Timeline View (Expanded) */}
            {expanded && displayDataPoints.length > 0 && (
              <div>
                <SectionHeader title="Detailed Timeline" count={displayDataPoints.length} />
                <div className="pl-2">
                  {displayDataPoints.map((dp, index) => (
                    <TimelinePoint
                      key={dp.month}
                      dataPoint={dp}
                      isLast={index === displayDataPoints.length - 1}
                      domains={domains}
                    />
                  ))}
                </div>
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}
