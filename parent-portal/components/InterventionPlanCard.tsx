import Link from 'next/link';
import type {
  InterventionPlanSummary,
  InterventionPlanStatus,
  ReviewSchedule,
} from '@/lib/types';

export interface InterventionPlanCardProps {
  plan: InterventionPlanSummary;
}

function getStatusBadgeClass(status: InterventionPlanStatus): string {
  switch (status) {
    case 'active':
      return 'badge-success';
    case 'draft':
      return 'badge-warning';
    case 'under_review':
      return 'badge-info';
    case 'completed':
      return 'badge-primary';
    case 'archived':
      return 'badge-neutral';
    default:
      return 'badge-neutral';
  }
}

function getStatusLabel(status: InterventionPlanStatus): string {
  switch (status) {
    case 'active':
      return 'Active';
    case 'draft':
      return 'Draft';
    case 'under_review':
      return 'Under Review';
    case 'completed':
      return 'Completed';
    case 'archived':
      return 'Archived';
    default:
      return status;
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
    return `${Math.abs(diffDays)} days overdue`;
  } else if (diffDays <= 7) {
    return `In ${diffDays} days`;
  }

  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
  });
}

function formatReviewSchedule(schedule: ReviewSchedule): string {
  switch (schedule) {
    case 'monthly':
      return 'Monthly';
    case 'quarterly':
      return 'Quarterly';
    case 'semi_annually':
      return 'Semi-Annually';
    case 'annually':
      return 'Annually';
    default:
      return schedule;
  }
}

function StatCard({
  value,
  label,
  colorClass = 'text-primary-600',
}: {
  value: string | number;
  label: string;
  colorClass?: string;
}) {
  return (
    <div className="text-center p-3 bg-gray-50 rounded-lg">
      <p className={`text-2xl font-bold ${colorClass}`}>{value}</p>
      <p className="text-xs text-gray-500">{label}</p>
    </div>
  );
}

function SignatureStatus({ signed }: { signed: boolean }) {
  if (signed) {
    return (
      <div className="flex items-center space-x-2">
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
            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
        <span className="text-success-700">Parent Signed</span>
      </div>
    );
  }

  return (
    <div className="flex items-center space-x-2">
      <svg
        className="h-5 w-5 text-warning-600"
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
      <span className="text-warning-700">Awaiting Signature</span>
    </div>
  );
}

function ReviewDateBanner({
  nextReviewDate,
  isOverdue,
}: {
  nextReviewDate: string;
  isOverdue: boolean;
}) {
  return (
    <div
      className={`flex items-center justify-between p-3 rounded-lg ${
        isOverdue ? 'bg-error-50' : 'bg-info-50'
      }`}
    >
      <div className="flex items-center space-x-2">
        <svg
          className={`h-5 w-5 ${isOverdue ? 'text-error-600' : 'text-info-600'}`}
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
        <span
          className={`text-sm font-medium ${
            isOverdue ? 'text-error-700' : 'text-info-700'
          }`}
        >
          Next Review: {formatDate(nextReviewDate)}
        </span>
      </div>
      {isOverdue && <span className="badge badge-error">Overdue</span>}
    </div>
  );
}

export function InterventionPlanCard({ plan }: InterventionPlanCardProps) {
  const isOverdue =
    plan.nextReviewDate && new Date(plan.nextReviewDate) < new Date();

  return (
    <Link
      href={`/intervention-plans/${plan.id}`}
      className="card hover:shadow-md transition-shadow duration-200"
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
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                />
              </svg>
            </div>
            <div className="flex-1 min-w-0">
              <h3 className="text-lg font-semibold text-gray-900 truncate">
                {plan.title}
              </h3>
              <p className="text-sm text-gray-600">{plan.childName}</p>
            </div>
          </div>
          {/* Desktop Status Badges */}
          <div className="hidden sm:flex items-center space-x-2">
            <span className={`badge ${getStatusBadgeClass(plan.status)}`}>
              {getStatusLabel(plan.status)}
            </span>
            {!plan.parentSigned && plan.status === 'active' && (
              <span className="badge badge-warning">Signature Required</span>
            )}
          </div>
        </div>
      </div>

      {/* Card Body */}
      <div className="card-body">
        {/* Mobile Status Badges */}
        <div className="flex sm:hidden items-center space-x-2 mb-4">
          <span className={`badge ${getStatusBadgeClass(plan.status)}`}>
            {getStatusLabel(plan.status)}
          </span>
          {!plan.parentSigned && plan.status === 'active' && (
            <span className="badge badge-warning">Signature Required</span>
          )}
        </div>

        {/* Plan Statistics */}
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-4">
          <StatCard
            value={plan.goalsCount}
            label={`Goal${plan.goalsCount !== 1 ? 's' : ''}`}
            colorClass="text-primary-600"
          />
          <StatCard
            value={plan.progressCount}
            label={`Progress Record${plan.progressCount !== 1 ? 's' : ''}`}
            colorClass="text-success-600"
          />
          <StatCard
            value={`v${plan.version}`}
            label="Version"
            colorClass="text-info-600"
          />
          <div className="text-center p-3 bg-gray-50 rounded-lg">
            <p className="text-sm font-medium text-gray-700">
              {formatReviewSchedule(plan.reviewSchedule)}
            </p>
            <p className="text-xs text-gray-500">Review Schedule</p>
          </div>
        </div>

        {/* Next Review Date */}
        {plan.nextReviewDate && (
          <ReviewDateBanner
            nextReviewDate={plan.nextReviewDate}
            isOverdue={isOverdue ?? false}
          />
        )}

        {/* Footer with Signature Status and Last Update */}
        <div className="mt-4 flex items-center justify-between text-sm">
          <SignatureStatus signed={plan.parentSigned} />
          <span className="text-gray-500">
            Updated{' '}
            {new Date(plan.updatedAt || plan.createdAt).toLocaleDateString()}
          </span>
        </div>
      </div>
    </Link>
  );
}
