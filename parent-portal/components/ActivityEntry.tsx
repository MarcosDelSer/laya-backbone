'use client';

import { useTranslations } from 'next-intl';

interface ActivityEntryProps {
  activity: {
    id: string;
    name: string;
    description: string;
    time: string;
  };
}

export function ActivityEntry({ activity }: ActivityEntryProps) {
  // Note: ActivityEntry doesn't have translatable strings in the current implementation
  // since activity.name and activity.description are dynamic data from the backend.
  // The 'use client' directive is added for consistency with other entry components.

  return (
    <div className="flex items-start space-x-3">
      <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-purple-100">
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
            d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      </div>
      <div className="flex-1 min-w-0">
        <div className="flex items-center justify-between">
          <p className="font-medium text-gray-900">{activity.name}</p>
          <span className="text-sm text-gray-500">{activity.time}</span>
        </div>
        {activity.description && (
          <p className="text-sm text-gray-600 mt-0.5">{activity.description}</p>
        )}
      </div>
    </div>
  );
}
