'use client';

import Link from 'next/link';
import { useState } from 'react';
import { PortfolioCard } from '@/components/PortfolioCard';
import {
  Child,
  PortfolioItem,
  PortfolioSummary,
} from '@/lib/types';

// Mock children data - will be replaced with API calls
const mockChildren: Child[] = [
  {
    id: 'child-1',
    firstName: 'Emma',
    lastName: 'Johnson',
    dateOfBirth: '2021-03-15',
    classroomId: 'classroom-1',
    classroomName: 'Sunshine Room',
    profilePhotoUrl: '',
  },
  {
    id: 'child-2',
    firstName: 'Liam',
    lastName: 'Johnson',
    dateOfBirth: '2022-08-20',
    classroomId: 'classroom-2',
    classroomName: 'Rainbow Room',
    profilePhotoUrl: '',
  },
];

// Mock portfolio summary data - will be replaced with API calls
const mockPortfolioSummary: Record<string, PortfolioSummary> = {
  'child-1': {
    childId: 'child-1',
    totalItems: 24,
    totalObservations: 12,
    totalMilestones: 15,
    milestonesAchieved: 8,
    totalWorkSamples: 18,
    recentActivity: new Date().toISOString(),
  },
  'child-2': {
    childId: 'child-2',
    totalItems: 16,
    totalObservations: 8,
    totalMilestones: 10,
    milestonesAchieved: 4,
    totalWorkSamples: 10,
    recentActivity: new Date(Date.now() - 86400000).toISOString(),
  },
};

// Mock portfolio items - will be replaced with API calls
const mockPortfolioItems: Record<string, PortfolioItem[]> = {
  'child-1': [
    {
      id: 'item-1',
      childId: 'child-1',
      type: 'photo',
      title: 'First Day at Preschool',
      caption: 'Emma was so excited on her first day! She made new friends right away.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date().toISOString().split('T')[0],
      uploadedBy: 'Ms. Sarah',
      tags: ['milestone', 'first-day'],
      isPrivate: false,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'item-2',
      childId: 'child-1',
      type: 'artwork',
      title: 'Family Portrait Drawing',
      caption: 'A beautiful drawing of her family during art time.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date(Date.now() - 86400000).toISOString().split('T')[0],
      uploadedBy: 'Ms. Sarah',
      tags: ['art', 'creative', 'family'],
      isPrivate: false,
      createdAt: new Date(Date.now() - 86400000).toISOString(),
    },
    {
      id: 'item-3',
      childId: 'child-1',
      type: 'video',
      title: 'Learning to Count',
      caption: 'Emma practicing counting to 20 during circle time.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date(Date.now() - 172800000).toISOString().split('T')[0],
      uploadedBy: 'Ms. Sarah',
      tags: ['learning', 'math', 'achievement'],
      isPrivate: false,
      createdAt: new Date(Date.now() - 172800000).toISOString(),
    },
    {
      id: 'item-4',
      childId: 'child-1',
      type: 'document',
      title: 'Progress Report - Q1',
      caption: 'Quarterly progress report documenting development milestones.',
      mediaUrl: '',
      date: new Date(Date.now() - 259200000).toISOString().split('T')[0],
      uploadedBy: 'Ms. Sarah',
      tags: ['report', 'progress'],
      isPrivate: true,
      createdAt: new Date(Date.now() - 259200000).toISOString(),
    },
  ],
  'child-2': [
    {
      id: 'item-5',
      childId: 'child-2',
      type: 'photo',
      title: 'Playing with Blocks',
      caption: 'Liam built an impressive tower during free play.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date().toISOString().split('T')[0],
      uploadedBy: 'Ms. Katie',
      tags: ['play', 'building'],
      isPrivate: false,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'item-6',
      childId: 'child-2',
      type: 'artwork',
      title: 'Finger Painting',
      caption: 'Colorful exploration with finger paints.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date(Date.now() - 86400000).toISOString().split('T')[0],
      uploadedBy: 'Ms. Katie',
      tags: ['art', 'sensory'],
      isPrivate: false,
      createdAt: new Date(Date.now() - 86400000).toISOString(),
    },
  ],
};

export default function PortfolioPage() {
  const [selectedChildId, setSelectedChildId] = useState<string>(mockChildren[0]?.id || '');

  const selectedChild = mockChildren.find((child) => child.id === selectedChildId);
  const summary = mockPortfolioSummary[selectedChildId];
  const portfolioItems = mockPortfolioItems[selectedChildId] || [];

  const handleViewItem = (item: PortfolioItem) => {
    // Will navigate to item detail view
  };

  const handleEditItem = (item: PortfolioItem) => {
    // Will open edit modal
  };

  const handleDeleteItem = (item: PortfolioItem) => {
    // Will show confirmation and delete
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Portfolio</h1>
            <p className="mt-1 text-gray-600">
              View your child&apos;s educational journey, milestones, and memories
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

      {/* Child Selector */}
      {mockChildren.length > 1 && (
        <div className="mb-6">
          <label htmlFor="child-select" className="block text-sm font-medium text-gray-700 mb-2">
            Select Child
          </label>
          <div className="relative">
            <select
              id="child-select"
              value={selectedChildId}
              onChange={(e) => setSelectedChildId(e.target.value)}
              className="block w-full rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-10 text-base focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500 sm:text-sm"
            >
              {mockChildren.map((child) => (
                <option key={child.id} value={child.id}>
                  {child.firstName} {child.lastName} - {child.classroomName}
                </option>
              ))}
            </select>
            <div className="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-2">
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
                  d="M19 9l-7 7-7-7"
                />
              </svg>
            </div>
          </div>
        </div>
      )}

      {/* Portfolio Summary Stats */}
      {summary && (
        <div className="mb-8 grid grid-cols-2 gap-4 sm:grid-cols-4">
          {/* Total Media Items */}
          <div className="card p-4 text-center">
            <div className="flex h-12 w-12 mx-auto items-center justify-center rounded-full bg-green-100">
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
            </div>
            <p className="mt-2 text-2xl font-bold text-gray-900">{summary.totalItems}</p>
            <p className="text-sm text-gray-500">Media Items</p>
          </div>

          {/* Observations */}
          <div className="card p-4 text-center">
            <div className="flex h-12 w-12 mx-auto items-center justify-center rounded-full bg-blue-100">
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
            </div>
            <p className="mt-2 text-2xl font-bold text-gray-900">{summary.totalObservations}</p>
            <p className="text-sm text-gray-500">Observations</p>
          </div>

          {/* Milestones */}
          <div className="card p-4 text-center">
            <div className="flex h-12 w-12 mx-auto items-center justify-center rounded-full bg-purple-100">
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
                  d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"
                />
              </svg>
            </div>
            <p className="mt-2 text-2xl font-bold text-gray-900">
              {summary.milestonesAchieved}/{summary.totalMilestones}
            </p>
            <p className="text-sm text-gray-500">Milestones</p>
          </div>

          {/* Work Samples */}
          <div className="card p-4 text-center">
            <div className="flex h-12 w-12 mx-auto items-center justify-center rounded-full bg-orange-100">
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
            </div>
            <p className="mt-2 text-2xl font-bold text-gray-900">{summary.totalWorkSamples}</p>
            <p className="text-sm text-gray-500">Work Samples</p>
          </div>
        </div>
      )}

      {/* Quick Actions */}
      <div className="mb-8">
        <h2 className="text-lg font-semibold text-gray-900 mb-4">Quick Actions</h2>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <Link
            href={`/portfolio/${selectedChildId}`}
            className="card p-4 text-center hover:bg-gray-50 transition-colors"
          >
            <div className="flex h-10 w-10 mx-auto items-center justify-center rounded-full bg-primary-100">
              <svg
                className="h-5 w-5 text-primary-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                />
              </svg>
            </div>
            <p className="mt-2 text-sm font-medium text-gray-700">View Full Portfolio</p>
          </Link>

          <Link
            href={`/portfolio/${selectedChildId}?tab=photos`}
            className="card p-4 text-center hover:bg-gray-50 transition-colors"
          >
            <div className="flex h-10 w-10 mx-auto items-center justify-center rounded-full bg-green-100">
              <svg
                className="h-5 w-5 text-green-600"
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
            </div>
            <p className="mt-2 text-sm font-medium text-gray-700">Photos & Videos</p>
          </Link>

          <Link
            href={`/portfolio/${selectedChildId}?tab=milestones`}
            className="card p-4 text-center hover:bg-gray-50 transition-colors"
          >
            <div className="flex h-10 w-10 mx-auto items-center justify-center rounded-full bg-purple-100">
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
                  d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"
                />
              </svg>
            </div>
            <p className="mt-2 text-sm font-medium text-gray-700">Milestones</p>
          </Link>

          <Link
            href={`/portfolio/${selectedChildId}?tab=observations`}
            className="card p-4 text-center hover:bg-gray-50 transition-colors"
          >
            <div className="flex h-10 w-10 mx-auto items-center justify-center rounded-full bg-blue-100">
              <svg
                className="h-5 w-5 text-blue-600"
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
            </div>
            <p className="mt-2 text-sm font-medium text-gray-700">Observations</p>
          </Link>
        </div>
      </div>

      {/* Recent Portfolio Items */}
      <div className="mb-8">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-gray-900">Recent Items</h2>
          {portfolioItems.length > 0 && (
            <Link
              href={`/portfolio/${selectedChildId}`}
              className="text-sm font-medium text-primary-600 hover:text-primary-700"
            >
              View all
            </Link>
          )}
        </div>

        {portfolioItems.length > 0 ? (
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
            {portfolioItems.slice(0, 4).map((item) => (
              <PortfolioCard
                key={item.id}
                item={item}
                onView={handleViewItem}
                onEdit={handleEditItem}
                onDelete={handleDeleteItem}
              />
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
                  d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
                />
              </svg>
            </div>
            <h3 className="text-lg font-medium text-gray-900">
              No portfolio items yet
            </h3>
            <p className="mt-2 text-gray-500">
              Portfolio items will appear here once they are added by your
              child&apos;s teacher.
            </p>
          </div>
        )}
      </div>

      {/* Child Info Card */}
      {selectedChild && (
        <div className="card p-6">
          <div className="flex items-center space-x-4">
            <div className="flex h-16 w-16 items-center justify-center rounded-full bg-primary-100">
              {selectedChild.profilePhotoUrl ? (
                <img
                  src={selectedChild.profilePhotoUrl}
                  alt={`${selectedChild.firstName} ${selectedChild.lastName}`}
                  className="h-16 w-16 rounded-full object-cover"
                />
              ) : (
                <span className="text-2xl font-bold text-primary-600">
                  {selectedChild.firstName[0]}{selectedChild.lastName[0]}
                </span>
              )}
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900">
                {selectedChild.firstName} {selectedChild.lastName}
              </h3>
              <p className="text-sm text-gray-500">{selectedChild.classroomName}</p>
              <p className="text-sm text-gray-500">
                Age: {calculateAge(selectedChild.dateOfBirth)}
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// Helper function to calculate age
function calculateAge(dateOfBirth: string): string {
  const today = new Date();
  const birthDate = new Date(dateOfBirth);
  let years = today.getFullYear() - birthDate.getFullYear();
  let months = today.getMonth() - birthDate.getMonth();

  if (months < 0) {
    years--;
    months += 12;
  }

  if (years < 1) {
    return `${months} month${months !== 1 ? 's' : ''}`;
  } else if (years < 2) {
    return months > 0 ? `${years} year, ${months} month${months !== 1 ? 's' : ''}` : `${years} year`;
  } else {
    return `${years} years${months > 0 ? `, ${months} month${months !== 1 ? 's' : ''}` : ''}`;
  }
}
