import { MealEntry } from './MealEntry';
import { NapEntry } from './NapEntry';
import { ActivityEntry } from './ActivityEntry';
import { PhotoGallery } from './PhotoGallery';

interface MealData {
  id: string;
  type: 'breakfast' | 'lunch' | 'snack';
  time: string;
  notes: string;
  amount: 'all' | 'most' | 'some' | 'none';
}

interface NapData {
  id: string;
  startTime: string;
  endTime: string;
  quality: 'good' | 'fair' | 'poor';
}

interface ActivityData {
  id: string;
  name: string;
  description: string;
  time: string;
}

interface PhotoData {
  id: string;
  url: string;
  caption: string;
  taggedChildren: string[];
}

export interface DailyReportCardProps {
  report: {
    id: string;
    date: string;
    childId: string;
    meals: MealData[];
    naps: NapData[];
    activities: ActivityData[];
    photos: PhotoData[];
  };
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
    weekday: 'long',
    month: 'long',
    day: 'numeric',
    year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
  });
}

function SectionHeader({ title, count }: { title: string; count?: number }) {
  return (
    <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-4">
      <h4 className="font-medium text-gray-900">{title}</h4>
      {count !== undefined && count > 0 && (
        <span className="text-sm text-gray-500">
          {count} {count === 1 ? 'entry' : 'entries'}
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

export function DailyReportCard({ report }: DailyReportCardProps) {
  const formattedDate = formatDate(report.date);

  return (
    <div className="card">
      {/* Report Header */}
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
                  d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                />
              </svg>
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900">
                Daily Report
              </h3>
              <p className="text-sm text-gray-600">{formattedDate}</p>
            </div>
          </div>
          {/* Summary badges */}
          <div className="hidden sm:flex items-center space-x-2">
            {report.meals.length > 0 && (
              <span className="badge badge-success">
                {report.meals.length} Meal{report.meals.length > 1 ? 's' : ''}
              </span>
            )}
            {report.naps.length > 0 && (
              <span className="badge badge-info">
                {report.naps.length} Nap{report.naps.length > 1 ? 's' : ''}
              </span>
            )}
            {report.activities.length > 0 && (
              <span className="badge badge-warning">
                {report.activities.length} Activit{report.activities.length > 1 ? 'ies' : 'y'}
              </span>
            )}
          </div>
        </div>
      </div>

      <div className="card-body">
        {/* Photos Section */}
        {report.photos.length > 0 && (
          <div className="mb-6">
            <SectionHeader title="Photos" count={report.photos.length} />
            <PhotoGallery photos={report.photos} maxDisplay={4} />
          </div>
        )}

        {/* Two-column layout for meals and naps */}
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 mb-6">
          {/* Meals Section */}
          <div>
            <SectionHeader title="Meals" count={report.meals.length} />
            {report.meals.length > 0 ? (
              <div className="space-y-4">
                {report.meals.map((meal) => (
                  <MealEntry key={meal.id} meal={meal} />
                ))}
              </div>
            ) : (
              <EmptyState message="No meals recorded" />
            )}
          </div>

          {/* Naps Section */}
          <div>
            <SectionHeader title="Nap Time" count={report.naps.length} />
            {report.naps.length > 0 ? (
              <div className="space-y-4">
                {report.naps.map((nap) => (
                  <NapEntry key={nap.id} nap={nap} />
                ))}
              </div>
            ) : (
              <EmptyState message="No naps recorded" />
            )}
          </div>
        </div>

        {/* Activities Section */}
        <div>
          <SectionHeader title="Activities" count={report.activities.length} />
          {report.activities.length > 0 ? (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              {report.activities.map((activity) => (
                <ActivityEntry key={activity.id} activity={activity} />
              ))}
            </div>
          ) : (
            <EmptyState message="No activities recorded" />
          )}
        </div>
      </div>
    </div>
  );
}
