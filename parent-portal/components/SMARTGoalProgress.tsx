import type {
  InterventionGoal,
  InterventionProgress,
  GoalStatus,
  ProgressLevel,
} from '@/lib/types';

export interface SMARTGoalProgressProps {
  goal: InterventionGoal;
  progressRecords?: InterventionProgress[];
  showDetails?: boolean;
  compact?: boolean;
}

function getStatusBadgeClass(status: GoalStatus): string {
  switch (status) {
    case 'achieved':
      return 'badge-success';
    case 'in_progress':
      return 'badge-info';
    case 'not_started':
      return 'badge-neutral';
    case 'modified':
      return 'badge-warning';
    case 'discontinued':
      return 'badge-error';
    default:
      return 'badge-neutral';
  }
}

function getStatusLabel(status: GoalStatus): string {
  switch (status) {
    case 'achieved':
      return 'Achieved';
    case 'in_progress':
      return 'In Progress';
    case 'not_started':
      return 'Not Started';
    case 'modified':
      return 'Modified';
    case 'discontinued':
      return 'Discontinued';
    default:
      return status;
  }
}

function getProgressBarColor(percentage: number): string {
  if (percentage >= 100) return 'bg-success-500';
  if (percentage >= 75) return 'bg-success-400';
  if (percentage >= 50) return 'bg-info-500';
  if (percentage >= 25) return 'bg-warning-500';
  return 'bg-gray-400';
}

function getProgressLevelColor(level: ProgressLevel): string {
  switch (level) {
    case 'achieved':
      return 'text-success-600';
    case 'significant':
      return 'text-success-500';
    case 'moderate':
      return 'text-info-600';
    case 'minimal':
      return 'text-warning-600';
    case 'no_progress':
      return 'text-gray-500';
    default:
      return 'text-gray-500';
  }
}

function getProgressLevelIcon(level: ProgressLevel): React.ReactNode {
  switch (level) {
    case 'achieved':
      return (
        <svg
          className="h-4 w-4 text-success-600"
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
    case 'significant':
      return (
        <svg
          className="h-4 w-4 text-success-500"
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
      );
    case 'moderate':
      return (
        <svg
          className="h-4 w-4 text-info-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 19l-4-4m0 0l4-4m-4 4h12"
          />
        </svg>
      );
    case 'minimal':
      return (
        <svg
          className="h-4 w-4 text-warning-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M5 12h.01M12 12h.01M19 12h.01M6 12a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0zm7 0a1 1 0 11-2 0 1 1 0 012 0z"
          />
        </svg>
      );
    case 'no_progress':
    default:
      return (
        <svg
          className="h-4 w-4 text-gray-500"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M20 12H4"
          />
        </svg>
      );
  }
}

function formatProgressLevel(level: ProgressLevel): string {
  switch (level) {
    case 'achieved':
      return 'Achieved';
    case 'significant':
      return 'Significant Progress';
    case 'moderate':
      return 'Moderate Progress';
    case 'minimal':
      return 'Minimal Progress';
    case 'no_progress':
      return 'No Progress';
    default:
      return level;
  }
}

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  const today = new Date();
  const diffDays = Math.ceil(
    (date.getTime() - today.getTime()) / (1000 * 60 * 60 * 24)
  );

  if (diffDays === 0) {
    return 'Today';
  } else if (diffDays === 1) {
    return 'Tomorrow';
  } else if (diffDays === -1) {
    return 'Yesterday';
  } else if (diffDays < 0) {
    return `${Math.abs(diffDays)} days ago`;
  } else if (diffDays <= 7) {
    return `In ${diffDays} days`;
  }

  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
  });
}

function isDateOverdue(dateString: string): boolean {
  return new Date(dateString) < new Date();
}

function ProgressBar({ percentage }: { percentage: number }) {
  const clampedPercentage = Math.min(100, Math.max(0, percentage));

  return (
    <div className="w-full">
      <div className="flex items-center justify-between text-sm mb-1">
        <span className="text-gray-600">Progress</span>
        <span className="font-medium text-gray-900">{clampedPercentage}%</span>
      </div>
      <div className="h-3 bg-gray-200 rounded-full overflow-hidden">
        <div
          className={`h-full ${getProgressBarColor(clampedPercentage)} transition-all duration-300`}
          style={{ width: `${clampedPercentage}%` }}
        />
      </div>
    </div>
  );
}

function SMARTCriteriaItem({
  label,
  value,
  icon,
}: {
  label: string;
  value?: string;
  icon: React.ReactNode;
}) {
  if (!value) return null;

  return (
    <div className="flex items-start space-x-2 py-2">
      <div className="flex-shrink-0 mt-0.5">{icon}</div>
      <div>
        <p className="text-xs font-medium text-gray-500 uppercase tracking-wide">
          {label}
        </p>
        <p className="text-sm text-gray-700 mt-0.5">{value}</p>
      </div>
    </div>
  );
}

function SMARTCriteria({ goal }: { goal: InterventionGoal }) {
  return (
    <div className="grid grid-cols-1 md:grid-cols-2 gap-2 border-t border-gray-100 pt-4 mt-4">
      {/* Specific - Title and Description */}
      <SMARTCriteriaItem
        label="Specific"
        value={goal.description}
        icon={
          <svg
            className="h-5 w-5 text-primary-500"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"
            />
          </svg>
        }
      />

      {/* Measurable - Criteria and baseline/target */}
      <SMARTCriteriaItem
        label="Measurable"
        value={
          goal.measurementCriteria +
          (goal.measurementBaseline
            ? ` (Baseline: ${goal.measurementBaseline})`
            : '') +
          (goal.measurementTarget ? ` → Target: ${goal.measurementTarget}` : '')
        }
        icon={
          <svg
            className="h-5 w-5 text-info-500"
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
        }
      />

      {/* Achievable */}
      <SMARTCriteriaItem
        label="Achievable"
        value={goal.achievabilityNotes}
        icon={
          <svg
            className="h-5 w-5 text-success-500"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"
            />
          </svg>
        }
      />

      {/* Relevant */}
      <SMARTCriteriaItem
        label="Relevant"
        value={goal.relevanceNotes}
        icon={
          <svg
            className="h-5 w-5 text-warning-500"
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
        }
      />

      {/* Time-bound */}
      {goal.targetDate && (
        <SMARTCriteriaItem
          label="Time-bound"
          value={`Target: ${formatDate(goal.targetDate)}`}
          icon={
            <svg
              className={`h-5 w-5 ${
                isDateOverdue(goal.targetDate) && goal.status !== 'achieved'
                  ? 'text-error-500'
                  : 'text-primary-500'
              }`}
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
          }
        />
      )}
    </div>
  );
}

function ProgressTimeline({
  progressRecords,
}: {
  progressRecords: InterventionProgress[];
}) {
  if (progressRecords.length === 0) return null;

  // Sort by record date, most recent first
  const sortedRecords = [...progressRecords].sort(
    (a, b) => new Date(b.recordDate).getTime() - new Date(a.recordDate).getTime()
  );

  // Show only the 3 most recent
  const recentRecords = sortedRecords.slice(0, 3);

  return (
    <div className="border-t border-gray-100 pt-4 mt-4">
      <h4 className="text-sm font-medium text-gray-900 mb-3">Recent Progress</h4>
      <div className="space-y-3">
        {recentRecords.map((record, index) => (
          <div
            key={record.id}
            className={`flex items-start space-x-3 ${
              index !== recentRecords.length - 1 ? 'pb-3 border-b border-gray-50' : ''
            }`}
          >
            <div className="flex-shrink-0 mt-0.5">
              {getProgressLevelIcon(record.progressLevel)}
            </div>
            <div className="flex-1 min-w-0">
              <div className="flex items-center justify-between">
                <span
                  className={`text-sm font-medium ${getProgressLevelColor(
                    record.progressLevel
                  )}`}
                >
                  {formatProgressLevel(record.progressLevel)}
                </span>
                <span className="text-xs text-gray-500">
                  {formatDate(record.recordDate)}
                </span>
              </div>
              {record.progressNotes && (
                <p className="text-sm text-gray-600 mt-1 line-clamp-2">
                  {record.progressNotes}
                </p>
              )}
              {record.measurementValue && (
                <p className="text-xs text-gray-500 mt-1">
                  Measured: {record.measurementValue}
                </p>
              )}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function CompactView({ goal }: { goal: InterventionGoal }) {
  return (
    <div className="flex items-center space-x-4 py-3 px-4 bg-gray-50 rounded-lg">
      {/* Status Icon */}
      <div
        className={`flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center ${
          goal.status === 'achieved'
            ? 'bg-success-100'
            : goal.status === 'in_progress'
              ? 'bg-info-100'
              : goal.status === 'discontinued'
                ? 'bg-error-100'
                : 'bg-gray-100'
        }`}
      >
        {goal.status === 'achieved' ? (
          <svg
            className="h-5 w-5 text-success-600"
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
        ) : goal.status === 'in_progress' ? (
          <svg
            className="h-5 w-5 text-info-600"
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
        ) : (
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
              d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        )}
      </div>

      {/* Goal Info */}
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 truncate">{goal.title}</p>
        <div className="flex items-center space-x-2 mt-1">
          <span className={`badge badge-sm ${getStatusBadgeClass(goal.status)}`}>
            {getStatusLabel(goal.status)}
          </span>
          {goal.targetDate && (
            <span
              className={`text-xs ${
                isDateOverdue(goal.targetDate) && goal.status !== 'achieved'
                  ? 'text-error-600'
                  : 'text-gray-500'
              }`}
            >
              Due {formatDate(goal.targetDate)}
            </span>
          )}
        </div>
      </div>

      {/* Progress Percentage */}
      <div className="flex-shrink-0 w-16">
        <div className="text-right">
          <span className="text-lg font-bold text-gray-900">
            {goal.progressPercentage}%
          </span>
        </div>
        <div className="h-1.5 bg-gray-200 rounded-full overflow-hidden mt-1">
          <div
            className={`h-full ${getProgressBarColor(goal.progressPercentage)}`}
            style={{ width: `${Math.min(100, goal.progressPercentage)}%` }}
          />
        </div>
      </div>
    </div>
  );
}

export function SMARTGoalProgress({
  goal,
  progressRecords = [],
  showDetails = true,
  compact = false,
}: SMARTGoalProgressProps) {
  if (compact) {
    return <CompactView goal={goal} />;
  }

  const goalProgressRecords = progressRecords.filter(
    (record) => record.goalId === goal.id
  );

  return (
    <div className="card">
      {/* Goal Header */}
      <div className="card-header">
        <div className="flex items-start justify-between">
          <div className="flex items-start space-x-3">
            <div
              className={`flex h-10 w-10 items-center justify-center rounded-full ${
                goal.status === 'achieved'
                  ? 'bg-success-100'
                  : goal.status === 'in_progress'
                    ? 'bg-primary-100'
                    : goal.status === 'discontinued'
                      ? 'bg-error-100'
                      : 'bg-gray-100'
              }`}
            >
              <svg
                className={`h-5 w-5 ${
                  goal.status === 'achieved'
                    ? 'text-success-600'
                    : goal.status === 'in_progress'
                      ? 'text-primary-600'
                      : goal.status === 'discontinued'
                        ? 'text-error-600'
                        : 'text-gray-600'
                }`}
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"
                />
              </svg>
            </div>
            <div className="flex-1">
              <h3 className="text-lg font-semibold text-gray-900">{goal.title}</h3>
              {goal.targetDate && (
                <p
                  className={`text-sm ${
                    isDateOverdue(goal.targetDate) && goal.status !== 'achieved'
                      ? 'text-error-600'
                      : 'text-gray-500'
                  }`}
                >
                  Target: {formatDate(goal.targetDate)}
                  {isDateOverdue(goal.targetDate) &&
                    goal.status !== 'achieved' &&
                    ' (Overdue)'}
                </p>
              )}
            </div>
          </div>
          <span className={`badge ${getStatusBadgeClass(goal.status)}`}>
            {getStatusLabel(goal.status)}
          </span>
        </div>
      </div>

      {/* Goal Body */}
      <div className="card-body">
        {/* Progress Bar */}
        <ProgressBar percentage={goal.progressPercentage} />

        {/* SMART Criteria */}
        {showDetails && <SMARTCriteria goal={goal} />}

        {/* Progress Timeline */}
        {showDetails && goalProgressRecords.length > 0 && (
          <ProgressTimeline progressRecords={goalProgressRecords} />
        )}
      </div>
    </div>
  );
}

/**
 * Summary component for showing goal statistics.
 */
export interface GoalsSummaryProps {
  goals: InterventionGoal[];
}

export function GoalsSummary({ goals }: GoalsSummaryProps) {
  if (goals.length === 0) {
    return (
      <div className="text-center py-8 text-gray-500">
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
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
          />
        </svg>
        <p className="mt-2 text-sm">No goals defined yet</p>
      </div>
    );
  }

  const totalGoals = goals.length;
  const achievedGoals = goals.filter((g) => g.status === 'achieved').length;
  const inProgressGoals = goals.filter((g) => g.status === 'in_progress').length;
  const avgProgress =
    Math.round(
      goals.reduce((sum, g) => sum + g.progressPercentage, 0) / totalGoals
    ) || 0;

  return (
    <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
      <div className="text-center p-4 bg-gray-50 rounded-lg">
        <p className="text-2xl font-bold text-primary-600">{totalGoals}</p>
        <p className="text-xs text-gray-500">Total Goals</p>
      </div>
      <div className="text-center p-4 bg-success-50 rounded-lg">
        <p className="text-2xl font-bold text-success-600">{achievedGoals}</p>
        <p className="text-xs text-gray-500">Achieved</p>
      </div>
      <div className="text-center p-4 bg-info-50 rounded-lg">
        <p className="text-2xl font-bold text-info-600">{inProgressGoals}</p>
        <p className="text-xs text-gray-500">In Progress</p>
      </div>
      <div className="text-center p-4 bg-primary-50 rounded-lg">
        <p className="text-2xl font-bold text-primary-600">{avgProgress}%</p>
        <p className="text-xs text-gray-500">Avg. Progress</p>
      </div>
    </div>
  );
}
