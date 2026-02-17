'use client';

import Link from 'next/link';
import { useTranslations, useLocale } from 'next-intl';
import { DailyReportCard } from '@/components/DailyReportCard';
import { useFormatting } from '@/lib/hooks/useFormatting';

// Mock data for daily reports - will be replaced with API calls
const mockReports = [
  {
    id: 'report-1',
    date: new Date().toISOString().split('T')[0], // Today
    childId: 'child-1',
    meals: [
      {
        id: 'meal-1',
        type: 'breakfast' as const,
        time: '8:45 AM',
        notes: 'Ate all of their oatmeal and fruit',
        amount: 'all' as const,
      },
      {
        id: 'meal-2',
        type: 'snack' as const,
        time: '10:30 AM',
        notes: 'Apple slices and crackers',
        amount: 'most' as const,
      },
      {
        id: 'meal-3',
        type: 'lunch' as const,
        time: '12:00 PM',
        notes: 'Chicken nuggets, vegetables, and milk',
        amount: 'some' as const,
      },
    ],
    naps: [
      {
        id: 'nap-1',
        startTime: '12:30 PM',
        endTime: '2:00 PM',
        quality: 'good' as const,
      },
    ],
    activities: [
      {
        id: 'activity-1',
        name: 'Art Time',
        time: '9:00 AM',
        description: 'Finger painting with watercolors',
      },
      {
        id: 'activity-2',
        name: 'Story Circle',
        time: '11:00 AM',
        description: 'Read "The Very Hungry Caterpillar"',
      },
      {
        id: 'activity-3',
        name: 'Music & Movement',
        time: '2:30 PM',
        description: 'Dancing and singing songs',
      },
      {
        id: 'activity-4',
        name: 'Outdoor Play',
        time: '3:30 PM',
        description: 'Playing on the playground',
      },
    ],
    photos: [
      {
        id: 'photo-1',
        url: '',
        caption: 'Finger painting during art time',
        taggedChildren: ['child-1'],
      },
      {
        id: 'photo-2',
        url: '',
        caption: 'Playing on the playground',
        taggedChildren: ['child-1'],
      },
    ],
  },
  {
    id: 'report-2',
    date: new Date(Date.now() - 86400000).toISOString().split('T')[0], // Yesterday
    childId: 'child-1',
    meals: [
      {
        id: 'meal-4',
        type: 'breakfast' as const,
        time: '8:30 AM',
        notes: 'Scrambled eggs and toast',
        amount: 'all' as const,
      },
      {
        id: 'meal-5',
        type: 'snack' as const,
        time: '10:15 AM',
        notes: 'Cheese and grapes',
        amount: 'all' as const,
      },
      {
        id: 'meal-6',
        type: 'lunch' as const,
        time: '12:00 PM',
        notes: 'Pasta with marinara sauce',
        amount: 'most' as const,
      },
    ],
    naps: [
      {
        id: 'nap-2',
        startTime: '1:00 PM',
        endTime: '2:30 PM',
        quality: 'fair' as const,
      },
    ],
    activities: [
      {
        id: 'activity-5',
        name: 'Building Blocks',
        time: '9:30 AM',
        description: 'Built a tall tower with friends',
      },
      {
        id: 'activity-6',
        name: 'Science Exploration',
        time: '11:00 AM',
        description: 'Learned about butterflies',
      },
      {
        id: 'activity-7',
        name: 'Outdoor Play',
        time: '3:00 PM',
        description: 'Sandbox and swing time',
      },
    ],
    photos: [
      {
        id: 'photo-3',
        url: '',
        caption: 'Building blocks activity',
        taggedChildren: ['child-1'],
      },
      {
        id: 'photo-4',
        url: '',
        caption: 'Learning about butterflies',
        taggedChildren: ['child-1'],
      },
      {
        id: 'photo-5',
        url: '',
        caption: 'Playing in the sandbox',
        taggedChildren: ['child-1'],
      },
    ],
  },
  {
    id: 'report-3',
    date: new Date(Date.now() - 172800000).toISOString().split('T')[0], // 2 days ago
    childId: 'child-1',
    meals: [
      {
        id: 'meal-7',
        type: 'breakfast' as const,
        time: '8:40 AM',
        notes: 'Pancakes and fruit',
        amount: 'most' as const,
      },
      {
        id: 'meal-8',
        type: 'snack' as const,
        time: '10:30 AM',
        notes: 'Yogurt and berries',
        amount: 'all' as const,
      },
      {
        id: 'meal-9',
        type: 'lunch' as const,
        time: '12:15 PM',
        notes: 'Turkey sandwich and veggies',
        amount: 'some' as const,
      },
    ],
    naps: [
      {
        id: 'nap-3',
        startTime: '12:45 PM',
        endTime: '2:15 PM',
        quality: 'good' as const,
      },
    ],
    activities: [
      {
        id: 'activity-8',
        name: 'Circle Time',
        time: '9:00 AM',
        description: 'Morning songs and calendar',
      },
      {
        id: 'activity-9',
        name: 'Sensory Play',
        time: '10:00 AM',
        description: 'Playing with playdough',
      },
    ],
    photos: [],
  },
];

/**
 * Daily Reports page with internationalization support.
 *
 * Displays a feed of daily reports for the parent's children,
 * showing meals, naps, activities, and photos for each day.
 * All text is translated using next-intl.
 */
export default function DailyReportsPage() {
  const t = useTranslations();
  const locale = useLocale();

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              {t('dailyReports.title')}
            </h1>
            <p className="mt-1 text-gray-600">
              {t('dailyReports.description')}
            </p>
          </div>
          <Link href={`/${locale}`} className="btn btn-outline">
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
            {t('common.back')}
          </Link>
        </div>
      </div>

      {/* Filter/Date Navigation - placeholder for future enhancement */}
      <div className="mb-6 flex items-center justify-between">
        <div className="flex items-center space-x-2">
          <span className="text-sm text-gray-500">
            {t('dailyReports.showingRecentReports')}
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
            {t('common.filter')}
          </button>
        </div>
      </div>

      {/* Reports Feed */}
      {mockReports.length > 0 ? (
        <div className="space-y-6">
          {mockReports.map((report) => (
            <DailyReportCard key={report.id} report={report} />
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
                d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
              />
            </svg>
          </div>
          <h3 className="text-lg font-medium text-gray-900">
            {t('dailyReports.noReportsTitle')}
          </h3>
          <p className="mt-2 text-gray-500">
            {t('dailyReports.noReportsDescription')}
          </p>
        </div>
      )}

      {/* Load More - placeholder for pagination */}
      {mockReports.length > 0 && (
        <div className="mt-8 text-center">
          <button
            type="button"
            className="btn btn-outline"
            disabled
          >
            {t('dailyReports.loadMoreReports')}
          </button>
        </div>
      )}
    </div>
  );
}
