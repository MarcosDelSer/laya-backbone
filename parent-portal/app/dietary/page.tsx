'use client';

import { useState } from 'react';
import Link from 'next/link';
import { DietaryForm } from '@/components/DietaryForm';
import { NutritionalReport } from '@/components/NutritionalReport';
import type {
  DietaryProfile,
  NutritionalReport as NutritionalReportData,
  UpdateDietaryProfileRequest,
} from '@/lib/types';

// Type definitions for child
interface Child {
  id: string;
  name: string;
  classroom: string;
}

// Mock children data
const mockChildren: Child[] = [
  {
    id: 'child-1',
    name: 'Emma Johnson',
    classroom: 'Butterfly Room',
  },
  {
    id: 'child-2',
    name: 'Oliver Johnson',
    classroom: 'Sunshine Room',
  },
];

// Mock dietary profile data - will be replaced with API calls
const mockDietaryProfiles: Record<string, DietaryProfile> = {
  'child-1': {
    id: 'dp-1',
    childId: 'child-1',
    childName: 'Emma Johnson',
    dietaryType: 'vegetarian',
    allergies: [
      { allergen: 'Peanuts', severity: 'severe', notes: 'Carries EpiPen' },
      { allergen: 'Tree Nuts', severity: 'severe' },
    ],
    restrictions: 'No spicy foods, prefers soft textures',
    notes: 'Enjoys fruits and vegetables. Small portions preferred.',
    parentNotified: true,
    lastUpdated: '2024-01-15T10:30:00Z',
  },
  'child-2': {
    id: 'dp-2',
    childId: 'child-2',
    childName: 'Oliver Johnson',
    dietaryType: 'regular',
    allergies: [{ allergen: 'Milk', severity: 'moderate' }],
    restrictions: '',
    notes: 'Lactose-free alternatives for dairy products',
    parentNotified: true,
    lastUpdated: '2024-02-01T14:45:00Z',
  },
};

// Mock nutritional report data
const mockNutritionalReports: Record<string, NutritionalReportData> = {
  'child-1': {
    childId: 'child-1',
    childName: 'Emma Johnson',
    startDate: '2024-02-05',
    endDate: '2024-02-11',
    totals: {
      totalCalories: 4250,
      totalProtein: 142,
      totalCarbohydrates: 520,
      totalFat: 168,
      mealsTracked: 15,
      averageCaloriesPerDay: 607,
    },
    dailyBreakdown: [
      {
        date: '2024-02-11',
        mealType: 'breakfast',
        calories: 280,
        protein: 10,
        carbohydrates: 35,
        fat: 12,
        appetiteLevel: 'all',
      },
      {
        date: '2024-02-11',
        mealType: 'lunch',
        calories: 320,
        protein: 15,
        carbohydrates: 40,
        fat: 14,
        appetiteLevel: 'most',
      },
      {
        date: '2024-02-11',
        mealType: 'snack',
        calories: 150,
        protein: 4,
        carbohydrates: 20,
        fat: 6,
        appetiteLevel: 'all',
      },
      {
        date: '2024-02-10',
        mealType: 'breakfast',
        calories: 260,
        protein: 9,
        carbohydrates: 32,
        fat: 11,
        appetiteLevel: 'most',
      },
      {
        date: '2024-02-10',
        mealType: 'lunch',
        calories: 340,
        protein: 16,
        carbohydrates: 42,
        fat: 15,
        appetiteLevel: 'all',
      },
    ],
    appetiteTrends: {
      all: 8,
      most: 5,
      some: 2,
      none: 0,
    },
  },
  'child-2': {
    childId: 'child-2',
    childName: 'Oliver Johnson',
    startDate: '2024-02-05',
    endDate: '2024-02-11',
    totals: {
      totalCalories: 4800,
      totalProtein: 165,
      totalCarbohydrates: 580,
      totalFat: 190,
      mealsTracked: 15,
      averageCaloriesPerDay: 686,
    },
    dailyBreakdown: [
      {
        date: '2024-02-11',
        mealType: 'breakfast',
        calories: 310,
        protein: 12,
        carbohydrates: 38,
        fat: 14,
        appetiteLevel: 'all',
      },
      {
        date: '2024-02-11',
        mealType: 'lunch',
        calories: 380,
        protein: 18,
        carbohydrates: 45,
        fat: 18,
        appetiteLevel: 'all',
      },
      {
        date: '2024-02-11',
        mealType: 'snack',
        calories: 180,
        protein: 6,
        carbohydrates: 24,
        fat: 8,
        appetiteLevel: 'most',
      },
    ],
    appetiteTrends: {
      all: 10,
      most: 4,
      some: 1,
      none: 0,
    },
  },
};

type TabType = 'accommodations' | 'reports';

export default function DietaryPage() {
  const [selectedChildId, setSelectedChildId] = useState<string>(mockChildren[0].id);
  const [activeTab, setActiveTab] = useState<TabType>('accommodations');
  const [profiles, setProfiles] = useState<Record<string, DietaryProfile>>(mockDietaryProfiles);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [saveSuccess, setSaveSuccess] = useState(false);
  const [isChildSelectorOpen, setIsChildSelectorOpen] = useState(false);

  const selectedChild = mockChildren.find((c) => c.id === selectedChildId) || mockChildren[0];
  const currentProfile = profiles[selectedChildId];
  const currentReport = mockNutritionalReports[selectedChildId];

  // Count allergies across all children
  const totalAllergies = Object.values(profiles).reduce(
    (sum, profile) => sum + (profile.allergies?.length || 0),
    0
  );

  // Handle form submission
  const handleSubmit = async (data: UpdateDietaryProfileRequest) => {
    setIsSubmitting(true);
    setSaveSuccess(false);

    // Simulate API call
    await new Promise((resolve) => setTimeout(resolve, 1000));

    setProfiles((prev) => ({
      ...prev,
      [selectedChildId]: {
        ...prev[selectedChildId],
        ...data,
        lastUpdated: new Date().toISOString(),
      },
    }));

    setIsSubmitting(false);
    setSaveSuccess(true);

    // Clear success message after 3 seconds
    setTimeout(() => setSaveSuccess(false), 3000);
  };

  // Handle child selection
  const handleSelectChild = (childId: string) => {
    setSelectedChildId(childId);
    setIsChildSelectorOpen(false);
    setSaveSuccess(false);
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Dietary Management</h1>
            <p className="mt-1 text-sm text-gray-600">
              Manage dietary accommodations and view nutritional reports
            </p>
          </div>
          <Link href="/" className="btn btn-outline self-start">
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
            Back to Dashboard
          </Link>
        </div>
      </div>

      {/* Child Selector */}
      <div className="mb-6">
        <div className="flex items-center justify-between">
          <p className="text-sm font-medium text-gray-700">Select Child</p>
          <div className="relative">
            <button
              type="button"
              onClick={() => setIsChildSelectorOpen(!isChildSelectorOpen)}
              className="flex items-center space-x-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
            >
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary-100 text-primary-700">
                {selectedChild.name.charAt(0)}
              </div>
              <div className="text-left">
                <p className="text-sm font-medium text-gray-900">{selectedChild.name}</p>
                <p className="text-xs text-gray-500">{selectedChild.classroom}</p>
              </div>
              <svg
                className={`h-4 w-4 text-gray-400 transition-transform ${
                  isChildSelectorOpen ? 'rotate-180' : ''
                }`}
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
            </button>

            {isChildSelectorOpen && (
              <div className="absolute right-0 z-10 mt-2 w-64 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                <div className="p-2">
                  {mockChildren.map((child) => (
                    <button
                      key={child.id}
                      onClick={() => handleSelectChild(child.id)}
                      className={`flex w-full items-center space-x-3 rounded-md px-3 py-2 text-sm ${
                        selectedChildId === child.id
                          ? 'bg-primary-50 text-primary-700'
                          : 'text-gray-700 hover:bg-gray-50'
                      }`}
                    >
                      <div
                        className={`flex h-8 w-8 items-center justify-center rounded-full ${
                          selectedChildId === child.id
                            ? 'bg-primary-200 text-primary-800'
                            : 'bg-gray-100 text-gray-600'
                        }`}
                      >
                        {child.name.charAt(0)}
                      </div>
                      <div className="text-left">
                        <p className="font-medium">{child.name}</p>
                        <p className="text-xs text-gray-500">{child.classroom}</p>
                      </div>
                      {selectedChildId === child.id && (
                        <svg
                          className="ml-auto h-5 w-5 text-primary-600"
                          fill="currentColor"
                          viewBox="0 0 20 20"
                        >
                          <path
                            fillRule="evenodd"
                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                            clipRule="evenodd"
                          />
                        </svg>
                      )}
                    </button>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {/* Dietary Type */}
        <div className="card p-4">
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-green-100">
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
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
            <div>
              <p className="text-sm text-gray-500">Dietary Type</p>
              <p className="text-lg font-bold text-gray-900 capitalize">
                {currentProfile?.dietaryType.replace('_', ' ') || 'Not set'}
              </p>
            </div>
          </div>
        </div>

        {/* Allergies */}
        <div className="card p-4">
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-100">
              <svg
                className="h-5 w-5 text-red-600"
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
            </div>
            <div>
              <p className="text-sm text-gray-500">Active Allergies</p>
              <p className="text-lg font-bold text-red-600">
                {currentProfile?.allergies?.length || 0}
              </p>
            </div>
          </div>
        </div>

        {/* Meals Tracked */}
        <div className="card p-4">
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100">
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
                  d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"
                />
              </svg>
            </div>
            <div>
              <p className="text-sm text-gray-500">Meals This Week</p>
              <p className="text-lg font-bold text-blue-600">
                {currentReport?.totals.mealsTracked || 0}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Tab Navigation */}
      <div className="mb-6 flex items-center justify-between border-b border-gray-200">
        <div className="flex space-x-4">
          <button
            type="button"
            onClick={() => setActiveTab('accommodations')}
            className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'accommodations'
                ? 'border-primary text-primary'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Dietary Accommodations
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('reports')}
            className={`pb-3 text-sm font-medium border-b-2 transition-colors ${
              activeTab === 'reports'
                ? 'border-primary text-primary'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'
            }`}
          >
            Nutritional Reports
          </button>
        </div>
      </div>

      {/* Success Message */}
      {saveSuccess && (
        <div className="mb-6 rounded-lg bg-green-50 border border-green-200 p-4">
          <div className="flex">
            <svg
              className="h-5 w-5 text-green-400 flex-shrink-0"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 13l4 4L19 7"
              />
            </svg>
            <div className="ml-3">
              <h3 className="text-sm font-medium text-green-800">Changes Saved</h3>
              <p className="mt-1 text-sm text-green-700">
                Dietary profile for {selectedChild.name} has been updated successfully.
                The care team has been notified.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Tab Content */}
      {activeTab === 'accommodations' ? (
        <div className="card">
          <div className="card-body">
            <DietaryForm
              initialProfile={currentProfile}
              childName={selectedChild.name}
              onSubmit={handleSubmit}
              isSubmitting={isSubmitting}
            />
          </div>
        </div>
      ) : (
        <div>
          {currentReport ? (
            <NutritionalReport report={currentReport} expandedByDefault={false} />
          ) : (
            <div className="card">
              <div className="card-body">
                <div className="rounded-lg border-2 border-dashed border-gray-200 p-12 text-center">
                  <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
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
                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                      />
                    </svg>
                  </div>
                  <h3 className="mt-4 text-lg font-medium text-gray-900">
                    No Nutritional Reports Available
                  </h3>
                  <p className="mt-2 text-sm text-gray-500">
                    Nutritional data will appear here once meals are tracked for{' '}
                    {selectedChild.name}.
                  </p>
                </div>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Last Updated Info */}
      {currentProfile?.lastUpdated && (
        <div className="mt-6 text-center">
          <p className="text-xs text-gray-400">
            Last updated:{' '}
            {new Date(currentProfile.lastUpdated).toLocaleDateString('en-US', {
              year: 'numeric',
              month: 'long',
              day: 'numeric',
              hour: '2-digit',
              minute: '2-digit',
            })}
          </p>
        </div>
      )}
    </div>
  );
}
