import Link from 'next/link';
import type { InterventionPlanSummary, InterventionPlanStatus } from '@/lib/types';

// Mock data for intervention plans - will be replaced with API calls
const mockPlans: InterventionPlanSummary[] = [
  {
    id: 'plan-1',
    childId: 'child-1',
    childName: 'Emma Thompson',
    title: 'Speech and Language Development Plan',
    status: 'active',
    version: 2,
    reviewSchedule: 'quarterly',
    nextReviewDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 30 days from now
    parentSigned: true,
    goalsCount: 4,
    progressCount: 8,
    createdAt: new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString(), // 90 days ago
    updatedAt: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString(), // 7 days ago
  },
  {
    id: 'plan-2',
    childId: 'child-1',
    childName: 'Emma Thompson',
    title: 'Social Skills Development Plan',
    status: 'active',
    version: 1,
    reviewSchedule: 'monthly',
    nextReviewDate: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 14 days from now
    parentSigned: false,
    goalsCount: 3,
    progressCount: 2,
    createdAt: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString(), // 30 days ago
    updatedAt: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString(), // 3 days ago
  },
  {
    id: 'plan-3',
    childId: 'child-2',
    childName: 'Lucas Martin',
    title: 'Motor Skills Intervention Plan',
    status: 'under_review',
    version: 3,
    reviewSchedule: 'quarterly',
    nextReviewDate: new Date(Date.now() - 5 * 24 * 60 * 60 * 1000).toISOString().split('T')[0], // 5 days ago (overdue)
    parentSigned: true,
    goalsCount: 5,
    progressCount: 15,
    createdAt: new Date(Date.now() - 180 * 24 * 60 * 60 * 1000).toISOString(), // 180 days ago
    updatedAt: new Date(Date.now() - 1 * 24 * 60 * 60 * 1000).toISOString(), // 1 day ago
  },
];

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
  const diffDays = Math.ceil((date.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

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

function formatReviewSchedule(schedule: string): string {
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

function InterventionPlanCard({ plan }: { plan: InterventionPlanSummary }) {
  const isOverdue = plan.nextReviewDate && new Date(plan.nextReviewDate) < new Date();

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
          <div className="hidden sm:flex items-center space-x-2">
            <span className={`badge ${getStatusBadgeClass(plan.status)}`}>
              {getStatusLabel(plan.status)}
            </span>
            {!plan.parentSigned && plan.status === 'active' && (
              <span className="badge badge-warning">
                Signature Required
              </span>
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
            <span className="badge badge-warning">
              Signature Required
            </span>
          )}
        </div>

        {/* Plan Statistics */}
        <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-4">
          <div className="text-center p-3 bg-gray-50 rounded-lg">
            <p className="text-2xl font-bold text-primary-600">{plan.goalsCount}</p>
            <p className="text-xs text-gray-500">
              Goal{plan.goalsCount !== 1 ? 's' : ''}
            </p>
          </div>
          <div className="text-center p-3 bg-gray-50 rounded-lg">
            <p className="text-2xl font-bold text-success-600">{plan.progressCount}</p>
            <p className="text-xs text-gray-500">
              Progress Record{plan.progressCount !== 1 ? 's' : ''}
            </p>
          </div>
          <div className="text-center p-3 bg-gray-50 rounded-lg">
            <p className="text-2xl font-bold text-info-600">v{plan.version}</p>
            <p className="text-xs text-gray-500">Version</p>
          </div>
          <div className="text-center p-3 bg-gray-50 rounded-lg">
            <p className="text-sm font-medium text-gray-700">
              {formatReviewSchedule(plan.reviewSchedule)}
            </p>
            <p className="text-xs text-gray-500">Review Schedule</p>
          </div>
        </div>

        {/* Next Review Date */}
        {plan.nextReviewDate && (
          <div className={`flex items-center justify-between p-3 rounded-lg ${
            isOverdue ? 'bg-error-50' : 'bg-info-50'
          }`}>
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
              <span className={`text-sm font-medium ${isOverdue ? 'text-error-700' : 'text-info-700'}`}>
                Next Review: {formatDate(plan.nextReviewDate)}
              </span>
            </div>
            {isOverdue && (
              <span className="badge badge-error">Overdue</span>
            )}
          </div>
        )}

        {/* Parent Signature Status */}
        <div className="mt-4 flex items-center justify-between text-sm">
          <div className="flex items-center space-x-2">
            {plan.parentSigned ? (
              <>
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
              </>
            ) : (
              <>
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
              </>
            )}
          </div>
          <span className="text-gray-500">
            Updated {new Date(plan.updatedAt || plan.createdAt).toLocaleDateString()}
          </span>
        </div>
      </div>
    </Link>
  );
}

export default function InterventionPlansPage() {
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Intervention Plans</h1>
            <p className="mt-1 text-gray-600">
              View and track your child&apos;s intervention plans and progress
            </p>
          </div>
          <Link href="/" className="btn btn-outline">
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
                d="M10 19l-7-7m0 0l7-7m-7 7h18"
              />
            </svg>
            Back
          </Link>
        </div>
      </div>

      {/* Filter/Status Navigation */}
      <div className="mb-6 flex items-center justify-between">
        <div className="flex items-center space-x-2">
          <span className="text-sm text-gray-500">
            Showing {mockPlans.length} plan{mockPlans.length !== 1 ? 's' : ''}
          </span>
        </div>
        <div className="flex items-center space-x-2">
          <button
            type="button"
            className="btn btn-outline btn-sm"
            disabled
          >
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
                d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
              />
            </svg>
            Filter
          </button>
        </div>
      </div>

      {/* Plans Feed */}
      {mockPlans.length > 0 ? (
        <div className="space-y-6">
          {mockPlans.map((plan) => (
            <InterventionPlanCard key={plan.id} plan={plan} />
          ))}
        </div>
      ) : (
        <div className="card p-12 text-center">
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
            <svg
              className="h-8 w-8 text-gray-400"
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
          <h3 className="text-lg font-medium text-gray-900">
            No intervention plans available
          </h3>
          <p className="mt-2 text-gray-500">
            Intervention plans will appear here once they are created for your
            child by their care team.
          </p>
        </div>
      )}

      {/* Load More - placeholder for pagination */}
      {mockPlans.length > 0 && (
        <div className="mt-8 text-center">
          <button
            type="button"
            className="btn btn-outline"
            disabled
          >
            Load More Plans
          </button>
        </div>
      )}
    </div>
  );
}
