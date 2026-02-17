'use client';

import Image from 'next/image';
import { PortfolioItem, PortfolioItemType } from '../lib/types';

export interface PortfolioCardProps {
  item: PortfolioItem;
  onView?: (item: PortfolioItem) => void;
  onEdit?: (item: PortfolioItem) => void;
  onDelete?: (item: PortfolioItem) => void;
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

function getTypeIcon(type: PortfolioItemType): React.ReactNode {
  switch (type) {
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
    case 'video':
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
            d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"
          />
        </svg>
      );
    case 'document':
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
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>
      );
    case 'artwork':
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
            d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"
          />
        </svg>
      );
  }
}

function getTypeBadgeColor(type: PortfolioItemType): string {
  switch (type) {
    case 'photo':
      return 'badge-success';
    case 'video':
      return 'badge-info';
    case 'document':
      return 'badge-warning';
    case 'artwork':
      return 'badge-primary';
    default:
      return 'badge-secondary';
  }
}

function getTypeLabel(type: PortfolioItemType): string {
  switch (type) {
    case 'photo':
      return 'Photo';
    case 'video':
      return 'Video';
    case 'document':
      return 'Document';
    case 'artwork':
      return 'Artwork';
    default:
      return type;
  }
}

export function PortfolioCard({
  item,
  onView,
  onEdit,
  onDelete,
}: PortfolioCardProps) {
  const formattedDate = formatDate(item.date);
  const isMediaItem = item.type === 'photo' || item.type === 'video';

  return (
    <div className="card">
      {/* Media Preview (for photos and videos) */}
      {isMediaItem && item.mediaUrl && (
        <div className="relative aspect-video w-full overflow-hidden rounded-t-lg bg-gray-100">
          {item.type === 'photo' ? (
            <Image
              src={item.thumbnailUrl || item.mediaUrl}
              alt={item.title}
              fill
              className="object-cover"
              sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 33vw"
            />
          ) : (
            <div className="flex h-full w-full items-center justify-center bg-gray-200">
              <div className="flex flex-col items-center text-gray-500">
                <svg
                  className="h-12 w-12"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"
                  />
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
                <span className="mt-2 text-sm">Video</span>
              </div>
            </div>
          )}
          {/* Privacy indicator overlay */}
          {item.isPrivate && (
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
            <span className={`badge ${getTypeBadgeColor(item.type)}`}>
              {getTypeLabel(item.type)}
            </span>
          </div>
        </div>
      )}

      <div className="card-body">
        {/* Header for non-media items */}
        {!isMediaItem && (
          <div className="mb-4 flex items-start justify-between">
            <div className="flex items-center space-x-3">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                {getTypeIcon(item.type)}
              </div>
              <div>
                <span className={`badge ${getTypeBadgeColor(item.type)}`}>
                  {getTypeLabel(item.type)}
                </span>
              </div>
            </div>
            {item.isPrivate && (
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
          <h3 className="text-lg font-semibold text-gray-900">{item.title}</h3>
          <p className="text-sm text-gray-500">{formattedDate}</p>
        </div>

        {/* Caption */}
        {item.caption && (
          <p className="mb-4 text-sm text-gray-600 line-clamp-3">
            {item.caption}
          </p>
        )}

        {/* Tags */}
        {item.tags && item.tags.length > 0 && (
          <div className="mb-4 flex flex-wrap gap-1">
            {item.tags.map((tag, index) => (
              <span
                key={index}
                className="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600"
              >
                #{tag}
              </span>
            ))}
          </div>
        )}

        {/* Metadata */}
        <div className="mb-4 border-t border-gray-100 pt-3 text-xs text-gray-400">
          <span>Uploaded by {item.uploadedBy}</span>
        </div>

        {/* Actions */}
        <div className="flex flex-wrap gap-2 border-t border-gray-100 pt-4">
          {onView && (
            <button
              type="button"
              onClick={() => onView(item)}
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
              onClick={() => onEdit(item)}
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
              onClick={() => onDelete(item)}
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
