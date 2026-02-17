'use client';

import { Milestone, MilestoneStatus, DevelopmentalDomain } from '../lib/types';

export interface MilestoneTrackerProps {
  milestones: Milestone[];
  childName?: string;
  onView?: (milestone: Milestone) => void;
  onEdit?: (milestone: Milestone) => void;
  onDelete?: (milestone: Milestone) => void;
  onAddEvidence?: (milestone: Milestone) => void;
}

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
    month: 'short',
    day: 'numeric',
    year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
  });
}

function getDomainIcon(domain: DevelopmentalDomain): React.ReactNode {
  switch (domain) {
    case 'cognitive':
      return (
        <svg
          className="h-6 w-6 text-blue-600"
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
    case 'physical':
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
            d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      );
    case 'social_emotional':
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
            d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
          />
        </svg>
      );
    case 'language':
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
            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
          />
        </svg>
      );
    case 'creative':
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
            d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"
          />
        </svg>
      );
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
            d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"
          />
        </svg>
      );
  }
}

function getDomainLabel(domain: DevelopmentalDomain): string {
  switch (domain) {
    case 'cognitive':
      return 'Cognitive';
    case 'physical':
      return 'Physical';
    case 'social_emotional':
      return 'Social-Emotional';
    case 'language':
      return 'Language';
    case 'creative':
      return 'Creative';
    default:
      return domain;
  }
}

function getDomainBadgeColor(domain: DevelopmentalDomain): string {
  switch (domain) {
    case 'cognitive':
      return 'bg-blue-100 text-blue-700';
    case 'physical':
      return 'bg-green-100 text-green-700';
    case 'social_emotional':
      return 'bg-pink-100 text-pink-700';
    case 'language':
      return 'bg-purple-100 text-purple-700';
    case 'creative':
      return 'bg-orange-100 text-orange-700';
    default:
      return 'bg-gray-100 text-gray-700';
  }
}

function getStatusBadgeClass(status: MilestoneStatus): string {
  switch (status) {
    case 'achieved':
      return 'badge-success';
    case 'in_progress':
      return 'badge-warning';
    case 'not_started':
      return 'badge-secondary';
    default:
      return 'badge-secondary';
  }
}

function getStatusLabel(status: MilestoneStatus): string {
  switch (status) {
    case 'achieved':
      return 'Achieved';
    case 'in_progress':
      return 'In Progress';
    case 'not_started':
      return 'Not Started';
    default:
      return status;
  }
}

function getStatusIcon(status: MilestoneStatus): React.ReactNode {
  switch (status) {
    case 'achieved':
      return (
        <svg
          className="h-5 w-5 text-green-500"
          fill="currentColor"
          viewBox="0 0 20 20"
        >
          <path
            fillRule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
            clipRule="evenodd"
          />
        </svg>
      );
    case 'in_progress':
      return (
        <svg
          className="h-5 w-5 text-yellow-500"
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
      );
    case 'not_started':
      return (
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
            d="M8 12h.01M12 12h.01M16 12h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      );
    default:
      return null;
  }
}

function SectionHeader({ title, count }: { title: string; count?: number }) {
  return (
    <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-4">
      <h4 className="font-medium text-gray-900">{title}</h4>
      {count !== undefined && count > 0 && (
        <span className="text-sm text-gray-500">
          {count} {count === 1 ? 'milestone' : 'milestones'}
        </span>
      )}
    </div>
  );
}

function EmptyState({ message }: { message: string }) {
  return (
    <p className="text-sm text-gray-500 italic text-center py-4">{message}</p>
  );
}

function ProgressSummary({ milestones }: { milestones: Milestone[] }) {
  const achieved = milestones.filter((m) => m.status === 'achieved').length;
  const inProgress = milestones.filter((m) => m.status === 'in_progress').length;
  const notStarted = milestones.filter((m) => m.status === 'not_started').length;
  const total = milestones.length;
  const progressPercent = total > 0 ? Math.round((achieved / total) * 100) : 0;

  return (
    <div className="mb-6 rounded-lg bg-gradient-to-r from-primary-50 to-primary-100 p-4">
      <div className="flex items-center justify-between mb-3">
        <div>
          <h4 className="font-semibold text-gray-900">Overall Progress</h4>
          <p className="text-sm text-gray-600">
            {achieved} of {total} milestones achieved
          </p>
        </div>
        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-white shadow">
          <span className="text-xl font-bold text-primary-600">
            {progressPercent}%
          </span>
        </div>
      </div>
      {/* Progress bar */}
      <div className="h-3 w-full overflow-hidden rounded-full bg-gray-200">
        <div className="flex h-full">
          {achieved > 0 && (
            <div
              className="bg-green-500 transition-all duration-300"
              style={{ width: `${(achieved / total) * 100}%` }}
            />
          )}
          {inProgress > 0 && (
            <div
              className="bg-yellow-400 transition-all duration-300"
              style={{ width: `${(inProgress / total) * 100}%` }}
            />
          )}
          {notStarted > 0 && (
            <div
              className="bg-gray-300 transition-all duration-300"
              style={{ width: `${(notStarted / total) * 100}%` }}
            />
          )}
        </div>
      </div>
      {/* Legend */}
      <div className="mt-3 flex flex-wrap gap-4 text-xs text-gray-600">
        <div className="flex items-center">
          <span className="mr-1.5 h-2.5 w-2.5 rounded-full bg-green-500" />
          Achieved ({achieved})
        </div>
        <div className="flex items-center">
          <span className="mr-1.5 h-2.5 w-2.5 rounded-full bg-yellow-400" />
          In Progress ({inProgress})
        </div>
        <div className="flex items-center">
          <span className="mr-1.5 h-2.5 w-2.5 rounded-full bg-gray-300" />
          Not Started ({notStarted})
        </div>
      </div>
    </div>
  );
}

interface MilestoneItemProps {
  milestone: Milestone;
  onView?: (milestone: Milestone) => void;
  onEdit?: (milestone: Milestone) => void;
  onDelete?: (milestone: Milestone) => void;
  onAddEvidence?: (milestone: Milestone) => void;
}

function MilestoneItem({
  milestone,
  onView,
  onEdit,
  onDelete,
  onAddEvidence,
}: MilestoneItemProps) {
  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4 hover:border-gray-300 hover:shadow-sm transition-all">
      <div className="flex items-start justify-between">
        <div className="flex items-start space-x-3">
          <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100">
            {getDomainIcon(milestone.domain)}
          </div>
          <div className="flex-1">
            <div className="flex items-center space-x-2 mb-1">
              <h4 className="font-medium text-gray-900">{milestone.title}</h4>
              {getStatusIcon(milestone.status)}
            </div>
            <span
              className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${getDomainBadgeColor(milestone.domain)}`}
            >
              {getDomainLabel(milestone.domain)}
            </span>
          </div>
        </div>
        <span className={`badge ${getStatusBadgeClass(milestone.status)}`}>
          {getStatusLabel(milestone.status)}
        </span>
      </div>

      {/* Description */}
      <p className="mt-3 text-sm text-gray-600 line-clamp-2">
        {milestone.description}
      </p>

      {/* Metadata */}
      <div className="mt-3 flex flex-wrap items-center gap-3 text-xs text-gray-500">
        {milestone.expectedAgeMonths && (
          <span className="flex items-center">
            <svg
              className="mr-1 h-3.5 w-3.5"
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
            Expected: {milestone.expectedAgeMonths} months
          </span>
        )}
        {milestone.achievedDate && (
          <span className="flex items-center">
            <svg
              className="mr-1 h-3.5 w-3.5 text-green-500"
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
            Achieved: {formatDate(milestone.achievedDate)}
          </span>
        )}
        {milestone.evidenceIds && milestone.evidenceIds.length > 0 && (
          <span className="flex items-center">
            <svg
              className="mr-1 h-3.5 w-3.5 text-blue-500"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
              />
            </svg>
            {milestone.evidenceIds.length} evidence{' '}
            {milestone.evidenceIds.length === 1 ? 'item' : 'items'}
          </span>
        )}
      </div>

      {/* Notes */}
      {milestone.notes && (
        <div className="mt-3 rounded-md bg-gray-50 p-2 text-xs text-gray-600">
          <span className="font-medium">Notes:</span> {milestone.notes}
        </div>
      )}

      {/* Actions */}
      <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-3">
        {onView && (
          <button
            type="button"
            onClick={() => onView(milestone)}
            className="btn btn-outline text-xs py-1 px-2"
          >
            <svg
              className="mr-1 h-3.5 w-3.5"
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
            View
          </button>
        )}
        {onAddEvidence && milestone.status !== 'achieved' && (
          <button
            type="button"
            onClick={() => onAddEvidence(milestone)}
            className="btn btn-outline text-xs py-1 px-2 text-blue-600 border-blue-300 hover:bg-blue-50"
          >
            <svg
              className="mr-1 h-3.5 w-3.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 4v16m8-8H4"
              />
            </svg>
            Add Evidence
          </button>
        )}
        {onEdit && (
          <button
            type="button"
            onClick={() => onEdit(milestone)}
            className="btn btn-outline text-xs py-1 px-2"
          >
            <svg
              className="mr-1 h-3.5 w-3.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
              />
            </svg>
            Edit
          </button>
        )}
        {onDelete && (
          <button
            type="button"
            onClick={() => onDelete(milestone)}
            className="btn btn-outline text-xs py-1 px-2 text-red-600 border-red-300 hover:bg-red-50"
          >
            <svg
              className="mr-1 h-3.5 w-3.5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
              />
            </svg>
            Delete
          </button>
        )}
      </div>
    </div>
  );
}

export function MilestoneTracker({
  milestones,
  childName,
  onView,
  onEdit,
  onDelete,
  onAddEvidence,
}: MilestoneTrackerProps) {
  // Group milestones by domain
  const milestonesByDomain = milestones.reduce(
    (acc, milestone) => {
      const domain = milestone.domain;
      if (!acc[domain]) {
        acc[domain] = [];
      }
      acc[domain].push(milestone);
      return acc;
    },
    {} as Record<DevelopmentalDomain, Milestone[]>
  );

  // Define domain order
  const domainOrder: DevelopmentalDomain[] = [
    'cognitive',
    'physical',
    'social_emotional',
    'language',
    'creative',
  ];

  return (
    <div className="card">
      {/* Header */}
      <div className="card-header">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-amber-100">
              <svg
                className="h-6 w-6 text-amber-600"
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
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900">
                Developmental Milestones
              </h3>
              {childName && (
                <p className="text-sm text-gray-600">{childName}&apos;s Progress</p>
              )}
            </div>
          </div>
        </div>
      </div>

      <div className="card-body">
        {milestones.length === 0 ? (
          <EmptyState message="No milestones tracked yet" />
        ) : (
          <>
            {/* Progress Summary */}
            <ProgressSummary milestones={milestones} />

            {/* Milestones by Domain */}
            <div className="space-y-6">
              {domainOrder.map((domain) => {
                const domainMilestones = milestonesByDomain[domain];
                if (!domainMilestones || domainMilestones.length === 0) {
                  return null;
                }

                return (
                  <div key={domain}>
                    <SectionHeader
                      title={getDomainLabel(domain)}
                      count={domainMilestones.length}
                    />
                    <div className="space-y-3">
                      {domainMilestones.map((milestone) => (
                        <MilestoneItem
                          key={milestone.id}
                          milestone={milestone}
                          onView={onView}
                          onEdit={onEdit}
                          onDelete={onDelete}
                          onAddEvidence={onAddEvidence}
                        />
                      ))}
                    </div>
                  </div>
                );
              })}
            </div>
          </>
        )}
      </div>
    </div>
  );
}
