'use client';

import Image from 'next/image';
import { WorkSample, WorkSampleType, DevelopmentalDomain } from '../lib/types';

export interface WorkSampleCardProps {
  workSample: WorkSample;
  onView?: (workSample: WorkSample) => void;
  onEdit?: (workSample: WorkSample) => void;
  onDelete?: (workSample: WorkSample) => void;
}

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  if (date.toDateString() === today.toDateString()) {
    return 'Today';
  }

  if (date.toDateString() === yesterday.toDateString()) {
    return 'Yesterday';
  }

  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
  });
}

function getTypeIcon(type: WorkSampleType): React.ReactNode {
  switch (type) {
    case 'drawing':
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
    case 'writing':
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
    case 'craft':
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
            d="M14.121 14.121L19 19m-7-7l7-7m-7 7l-2.879 2.879M12 12L9.121 9.121m0 5.758a3 3 0 10-4.243 4.243 3 3 0 004.243-4.243zm0-5.758a3 3 0 10-4.243-4.243 3 3 0 004.243 4.243z"
          />
        </svg>
      );
    case 'photo':
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
            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"
          />
        </svg>
      );
    case 'recording':
      return (
        <svg
          className="h-6 w-6 text-red-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"
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
            d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"
          />
        </svg>
      );
  }
}

function getTypeBadgeColor(type: WorkSampleType): string {
  switch (type) {
    case 'drawing':
      return 'badge-primary';
    case 'writing':
      return 'badge-info';
    case 'craft':
      return 'badge-secondary';
    case 'photo':
      return 'badge-success';
    case 'recording':
      return 'badge-warning';
    default:
      return 'badge-secondary';
  }
}

function getTypeLabel(type: WorkSampleType): string {
  switch (type) {
    case 'drawing':
      return 'Drawing';
    case 'writing':
      return 'Writing';
    case 'craft':
      return 'Craft';
    case 'photo':
      return 'Photo';
    case 'recording':
      return 'Recording';
    default:
      return 'Other';
  }
}

function getDomainBadgeColor(domain: DevelopmentalDomain): string {
  switch (domain) {
    case 'cognitive':
      return 'bg-blue-100 text-blue-800';
    case 'physical':
      return 'bg-green-100 text-green-800';
    case 'social_emotional':
      return 'bg-pink-100 text-pink-800';
    case 'language':
      return 'bg-yellow-100 text-yellow-800';
    case 'creative':
      return 'bg-purple-100 text-purple-800';
    default:
      return 'bg-gray-100 text-gray-800';
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

export function WorkSampleCard({
  workSample,
  onView,
  onEdit,
  onDelete,
}: WorkSampleCardProps) {
  const formattedDate = formatDate(workSample.date);
  const hasMediaPreview = workSample.type === 'photo' || workSample.type === 'drawing';

  return (
    <div className="card">
      {/* Media Preview (for photos and drawings) */}
      {hasMediaPreview && workSample.mediaUrl && (
        <div className="relative aspect-video w-full overflow-hidden rounded-t-lg bg-gray-100">
          <Image
            src={workSample.thumbnailUrl || workSample.mediaUrl}
            alt={workSample.title}
            fill
            className="object-cover"
            sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
          />
          {/* Privacy indicator overlay */}
          {workSample.isPrivate && (
            <div className="absolute right-2 top-2">
              <span className="flex items-center rounded-full bg-gray-900/75 px-2 py-1 text-xs text-white">
                <svg
                  className="mr-1 h-3 w-3"
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
            </div>
          )}
          {/* Type badge overlay */}
          <div className="absolute bottom-2 left-2">
            <span className={`badge ${getTypeBadgeColor(workSample.type)}`}>
              {getTypeLabel(workSample.type)}
            </span>
          </div>
        </div>
      )}

      <div className="card-body">
        {/* Header for non-media items */}
        {!hasMediaPreview && (
          <div className="mb-4 flex items-start justify-between">
            <div className="flex items-center space-x-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                {getTypeIcon(workSample.type)}
              </div>
              <div>
                <span className={`badge ${getTypeBadgeColor(workSample.type)}`}>
                  {getTypeLabel(workSample.type)}
                </span>
              </div>
            </div>
            {workSample.isPrivate && (
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
        )}

        {/* Title and date */}
        <div className="mb-2">
          <h3 className="text-lg font-semibold text-gray-900">{workSample.title}</h3>
          <p className="text-sm text-gray-500">{formattedDate}</p>
        </div>

        {/* Description */}
        {workSample.description && (
          <p className="mb-4 text-sm text-gray-600 line-clamp-3">
            {workSample.description}
          </p>
        )}

        {/* Developmental Domains */}
        {workSample.domains && workSample.domains.length > 0 && (
          <div className="mb-4 flex flex-wrap gap-1">
            {workSample.domains.map((domain) => (
              <span
                key={domain}
                className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${getDomainBadgeColor(domain)}`}
              >
                {getDomainLabel(domain)}
              </span>
            ))}
          </div>
        )}

        {/* Teacher Notes */}
        {workSample.teacherNotes && (
          <div className="mb-4 rounded-lg bg-blue-50 p-3">
            <div className="flex items-start">
              <svg
                className="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-blue-600"
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
              <div>
                <p className="text-xs font-medium text-blue-800">Teacher Notes</p>
                <p className="mt-1 text-xs text-blue-700 line-clamp-2">
                  {workSample.teacherNotes}
                </p>
              </div>
            </div>
          </div>
        )}

        {/* Family Contribution */}
        {workSample.familyContribution && (
          <div className="mb-4 rounded-lg bg-green-50 p-3">
            <div className="flex items-start">
              <svg
                className="mr-2 mt-0.5 h-4 w-4 flex-shrink-0 text-green-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"
                />
              </svg>
              <div>
                <p className="text-xs font-medium text-green-800">Family Contribution</p>
                <p className="mt-1 text-xs text-green-700 line-clamp-2">
                  {workSample.familyContribution}
                </p>
              </div>
            </div>
          </div>
        )}

        {/* Actions */}
        <div className="flex flex-wrap gap-2 border-t border-gray-100 pt-4">
          {onView && (
            <button
              type="button"
              onClick={() => onView(workSample)}
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
              onClick={() => onEdit(workSample)}
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
              onClick={() => onDelete(workSample)}
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
