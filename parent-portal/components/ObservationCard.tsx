'use client';

import { Observation, ObservationType, DevelopmentalDomain } from '../lib/types';

export interface ObservationCardProps {
  observation: Observation;
  onView?: (observation: Observation) => void;
  onEdit?: (observation: Observation) => void;
  onDelete?: (observation: Observation) => void;
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

function getTypeIcon(type: ObservationType): React.ReactNode {
  switch (type) {
    case 'anecdotal':
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
            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
          />
        </svg>
      );
    case 'running_record':
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
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
          />
        </svg>
      );
    case 'learning_story':
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
            d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
          />
        </svg>
      );
    case 'checklist':
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
            d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
          />
        </svg>
      );
    case 'time_sample':
      return (
        <svg
          className="h-6 w-6 text-teal-600"
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
            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
          />
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
          />
        </svg>
      );
  }
}

function getTypeBadgeColor(type: ObservationType): string {
  switch (type) {
    case 'anecdotal':
      return 'badge-info';
    case 'running_record':
      return 'badge-success';
    case 'learning_story':
      return 'badge-primary';
    case 'checklist':
      return 'badge-warning';
    case 'time_sample':
      return 'badge-secondary';
    default:
      return 'badge-secondary';
  }
}

function getTypeLabel(type: ObservationType): string {
  switch (type) {
    case 'anecdotal':
      return 'Anecdotal';
    case 'running_record':
      return 'Running Record';
    case 'learning_story':
      return 'Learning Story';
    case 'checklist':
      return 'Checklist';
    case 'time_sample':
      return 'Time Sample';
    default:
      return type;
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

export function ObservationCard({
  observation,
  onView,
  onEdit,
  onDelete,
}: ObservationCardProps) {
  const formattedDate = formatDate(observation.date);
  const hasLinks =
    (observation.linkedMilestones && observation.linkedMilestones.length > 0) ||
    (observation.linkedWorkSamples && observation.linkedWorkSamples.length > 0);

  return (
    <div className="card">
      <div className="card-body">
        {/* Header */}
        <div className="mb-4 flex items-start justify-between">
          <div className="flex items-center space-x-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
              {getTypeIcon(observation.type)}
            </div>
            <div>
              <span className={`badge ${getTypeBadgeColor(observation.type)}`}>
                {getTypeLabel(observation.type)}
              </span>
            </div>
          </div>
          {observation.isPrivate && (
            <span className="flex items-center text-xs text-gray-500">
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
                  d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"
                />
              </svg>
              Private
            </span>
          )}
        </div>

        {/* Title and date */}
        <div className="mb-2">
          <h3 className="text-lg font-semibold text-gray-900">
            {observation.title}
          </h3>
          <p className="text-sm text-gray-500">{formattedDate}</p>
        </div>

        {/* Observation content */}
        <div className="mb-4">
          <p className="text-sm text-gray-600 line-clamp-4 whitespace-pre-wrap">
            {observation.content}
          </p>
        </div>

        {/* Developmental domains */}
        {observation.domains && observation.domains.length > 0 && (
          <div className="mb-4">
            <p className="mb-2 text-xs font-medium uppercase tracking-wide text-gray-500">
              Developmental Domains
            </p>
            <div className="flex flex-wrap gap-1">
              {observation.domains.map((domain) => (
                <span
                  key={domain}
                  className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${getDomainBadgeColor(domain)}`}
                >
                  {getDomainLabel(domain)}
                </span>
              ))}
            </div>
          </div>
        )}

        {/* Linked items indicator */}
        {hasLinks && (
          <div className="mb-4 flex flex-wrap gap-2">
            {observation.linkedMilestones &&
              observation.linkedMilestones.length > 0 && (
                <span className="inline-flex items-center text-xs text-gray-500">
                  <svg
                    className="mr-1 h-4 w-4 text-amber-500"
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
                  {observation.linkedMilestones.length} milestone
                  {observation.linkedMilestones.length > 1 ? 's' : ''} linked
                </span>
              )}
            {observation.linkedWorkSamples &&
              observation.linkedWorkSamples.length > 0 && (
                <span className="inline-flex items-center text-xs text-gray-500">
                  <svg
                    className="mr-1 h-4 w-4 text-blue-500"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                    />
                  </svg>
                  {observation.linkedWorkSamples.length} work sample
                  {observation.linkedWorkSamples.length > 1 ? 's' : ''} linked
                </span>
              )}
          </div>
        )}

        {/* Metadata */}
        <div className="mb-4 border-t border-gray-100 pt-3 text-xs text-gray-400">
          <span>Observed by {observation.observedBy}</span>
        </div>

        {/* Actions */}
        <div className="flex flex-wrap gap-2 border-t border-gray-100 pt-4">
          {onView && (
            <button
              type="button"
              onClick={() => onView(observation)}
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
              View
            </button>
          )}
          {onEdit && (
            <button
              type="button"
              onClick={() => onEdit(observation)}
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
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
              </svg>
              Edit
            </button>
          )}
          {onDelete && (
            <button
              type="button"
              onClick={() => onDelete(observation)}
              className="btn btn-outline text-sm text-red-600 border-red-300 hover:bg-red-50"
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
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"
                />
              </svg>
              Delete
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
