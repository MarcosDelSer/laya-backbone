'use client';

import { useState, useMemo, useCallback } from 'react';
import Link from 'next/link';
import { WeeklyMenuView } from '@/components/WeeklyMenuView';
import type {
  WeeklyMenu,
  MenuItem,
  ChildAllergy,
  AllergenWarning,
  AllergenSeverity,
} from '@/lib/types';

// Mock data for weekly menu - will be replaced with API calls
const mockMenuItems: MenuItem[] = [
  {
    id: 'item-1',
    name: 'Oatmeal with Berries',
    description: 'Warm oatmeal topped with fresh blueberries and strawberries',
    category: 'grain',
    allergens: [
      { id: 'a1', name: 'Gluten', severity: 'mild' },
    ],
    nutritionalInfo: {
      calories: 180,
      protein: 6,
      carbohydrates: 32,
      fat: 4,
      fiber: 5,
    },
    isActive: true,
  },
  {
    id: 'item-2',
    name: 'Scrambled Eggs',
    description: 'Fluffy scrambled eggs cooked with butter',
    category: 'main',
    allergens: [
      { id: 'a2', name: 'Eggs', severity: 'moderate' },
      { id: 'a3', name: 'Dairy', severity: 'mild' },
    ],
    nutritionalInfo: {
      calories: 150,
      protein: 12,
      carbohydrates: 2,
      fat: 10,
    },
    isActive: true,
  },
  {
    id: 'item-3',
    name: 'Chicken Nuggets',
    description: 'Baked chicken nuggets with whole grain breading',
    category: 'main',
    allergens: [
      { id: 'a4', name: 'Gluten', severity: 'mild' },
    ],
    nutritionalInfo: {
      calories: 220,
      protein: 18,
      carbohydrates: 15,
      fat: 10,
    },
    isActive: true,
  },
  {
    id: 'item-4',
    name: 'Steamed Vegetables',
    description: 'Mixed carrots, broccoli, and green beans',
    category: 'vegetable',
    allergens: [],
    nutritionalInfo: {
      calories: 45,
      protein: 2,
      carbohydrates: 9,
      fat: 0,
      fiber: 4,
    },
    isActive: true,
  },
  {
    id: 'item-5',
    name: 'Milk',
    description: 'Fresh whole milk',
    category: 'beverage',
    allergens: [
      { id: 'a5', name: 'Dairy', severity: 'moderate' },
    ],
    nutritionalInfo: {
      calories: 150,
      protein: 8,
      carbohydrates: 12,
      fat: 8,
    },
    isActive: true,
  },
  {
    id: 'item-6',
    name: 'Apple Slices',
    description: 'Fresh sliced apples',
    category: 'fruit',
    allergens: [],
    nutritionalInfo: {
      calories: 50,
      protein: 0,
      carbohydrates: 14,
      fat: 0,
      fiber: 2,
    },
    isActive: true,
  },
  {
    id: 'item-7',
    name: 'Pasta with Marinara',
    description: 'Penne pasta with tomato marinara sauce',
    category: 'main',
    allergens: [
      { id: 'a6', name: 'Gluten', severity: 'moderate' },
    ],
    nutritionalInfo: {
      calories: 280,
      protein: 9,
      carbohydrates: 52,
      fat: 4,
      fiber: 3,
    },
    isActive: true,
  },
  {
    id: 'item-8',
    name: 'Cheese Crackers',
    description: 'Whole grain crackers with cheese',
    category: 'snack',
    allergens: [
      { id: 'a7', name: 'Gluten', severity: 'mild' },
      { id: 'a8', name: 'Dairy', severity: 'moderate' },
    ],
    nutritionalInfo: {
      calories: 120,
      protein: 4,
      carbohydrates: 16,
      fat: 5,
    },
    isActive: true,
  },
  {
    id: 'item-9',
    name: 'Turkey Sandwich',
    description: 'Turkey slices on whole wheat bread with lettuce',
    category: 'main',
    allergens: [
      { id: 'a9', name: 'Gluten', severity: 'moderate' },
    ],
    nutritionalInfo: {
      calories: 250,
      protein: 20,
      carbohydrates: 28,
      fat: 6,
    },
    isActive: true,
  },
  {
    id: 'item-10',
    name: 'Yogurt with Granola',
    description: 'Vanilla yogurt topped with crunchy granola',
    category: 'dairy',
    allergens: [
      { id: 'a10', name: 'Dairy', severity: 'moderate' },
      { id: 'a11', name: 'Gluten', severity: 'mild' },
      { id: 'a12', name: 'Tree Nuts', severity: 'severe' },
    ],
    nutritionalInfo: {
      calories: 200,
      protein: 8,
      carbohydrates: 30,
      fat: 6,
    },
    isActive: true,
  },
  {
    id: 'item-11',
    name: 'Grilled Cheese',
    description: 'Classic grilled cheese sandwich',
    category: 'main',
    allergens: [
      { id: 'a13', name: 'Gluten', severity: 'moderate' },
      { id: 'a14', name: 'Dairy', severity: 'moderate' },
    ],
    nutritionalInfo: {
      calories: 300,
      protein: 12,
      carbohydrates: 30,
      fat: 14,
    },
    isActive: true,
  },
  {
    id: 'item-12',
    name: 'Banana',
    description: 'Fresh whole banana',
    category: 'fruit',
    allergens: [],
    nutritionalInfo: {
      calories: 100,
      protein: 1,
      carbohydrates: 27,
      fat: 0,
      fiber: 3,
    },
    isActive: true,
  },
];

// Mock child allergies - will be replaced with API calls
const mockChildAllergies: ChildAllergy[] = [
  { allergen: 'Tree Nuts', severity: 'severe', notes: 'Anaphylactic reaction risk' },
  { allergen: 'Dairy', severity: 'moderate', notes: 'Lactose intolerant' },
];

function getWeekStartDate(date: Date): Date {
  const d = new Date(date);
  const day = d.getDay();
  const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Adjust for Monday
  d.setDate(diff);
  d.setHours(0, 0, 0, 0);
  return d;
}

function formatDateKey(date: Date): string {
  return date.toISOString().split('T')[0];
}

function generateMockWeeklyMenu(weekStartDate: Date): WeeklyMenu {
  const entries = [];
  const mealTypes = ['breakfast', 'lunch', 'snack'] as const;

  for (let dayOffset = 0; dayOffset < 5; dayOffset++) {
    const date = new Date(weekStartDate);
    date.setDate(weekStartDate.getDate() + dayOffset);
    const dateStr = formatDateKey(date);

    for (const mealType of mealTypes) {
      // Select different items for each day/meal combination
      const itemIndices = [
        (dayOffset * 3 + mealTypes.indexOf(mealType)) % mockMenuItems.length,
        (dayOffset * 3 + mealTypes.indexOf(mealType) + 4) % mockMenuItems.length,
      ];

      entries.push({
        id: `entry-${dateStr}-${mealType}`,
        date: dateStr,
        mealType,
        menuItems: itemIndices.map((i) => mockMenuItems[i]),
      });
    }
  }

  const weekEndDate = new Date(weekStartDate);
  weekEndDate.setDate(weekStartDate.getDate() + 4);

  return {
    weekStartDate: formatDateKey(weekStartDate),
    weekEndDate: formatDateKey(weekEndDate),
    entries,
  };
}

function generateAllergenWarnings(
  menu: WeeklyMenu,
  childAllergies: ChildAllergy[]
): AllergenWarning[] {
  const warnings: AllergenWarning[] = [];

  for (const entry of menu.entries) {
    for (const item of entry.menuItems) {
      for (const allergen of item.allergens) {
        const childAllergy = childAllergies.find(
          (ca) => ca.allergen.toLowerCase() === allergen.name.toLowerCase()
        );
        if (childAllergy) {
          warnings.push({
            date: entry.date,
            mealType: entry.mealType,
            menuItemId: item.id,
            menuItemName: item.name,
            allergen: allergen.name,
            severity: childAllergy.severity,
          });
        }
      }
    }
  }

  return warnings;
}

export default function MenuPage() {
  const [currentWeekStart, setCurrentWeekStart] = useState(() =>
    getWeekStartDate(new Date())
  );

  const menu = useMemo(
    () => generateMockWeeklyMenu(currentWeekStart),
    [currentWeekStart]
  );

  const allergenWarnings = useMemo(
    () => generateAllergenWarnings(menu, mockChildAllergies),
    [menu]
  );

  const handlePreviousWeek = useCallback(() => {
    setCurrentWeekStart((prev) => {
      const newDate = new Date(prev);
      newDate.setDate(prev.getDate() - 7);
      return newDate;
    });
  }, []);

  const handleNextWeek = useCallback(() => {
    setCurrentWeekStart((prev) => {
      const newDate = new Date(prev);
      newDate.setDate(prev.getDate() + 7);
      return newDate;
    });
  }, []);

  const handleMenuItemClick = useCallback((menuItem: MenuItem) => {
    // Future: Open modal with full menu item details
  }, []);

  return (
    <div className="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Weekly Menu</h1>
            <p className="mt-1 text-gray-600">
              View upcoming meals and allergen information
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

      {/* Quick stats */}
      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <div className="flex items-center">
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
            <div className="ml-3">
              <p className="text-sm font-medium text-gray-500">Meals This Week</p>
              <p className="text-lg font-semibold text-gray-900">{menu.entries.length}</p>
            </div>
          </div>
        </div>

        <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <div className="flex items-center">
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
                  d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
                />
              </svg>
            </div>
            <div className="ml-3">
              <p className="text-sm font-medium text-gray-500">Menu Items</p>
              <p className="text-lg font-semibold text-gray-900">
                {new Set(menu.entries.flatMap((e) => e.menuItems.map((i) => i.id))).size}
              </p>
            </div>
          </div>
        </div>

        <div className="rounded-lg bg-white p-4 shadow-sm ring-1 ring-gray-200">
          <div className="flex items-center">
            <div
              className={`flex h-10 w-10 items-center justify-center rounded-full ${
                allergenWarnings.length > 0 ? 'bg-red-100' : 'bg-gray-100'
              }`}
            >
              <svg
                className={`h-5 w-5 ${
                  allergenWarnings.length > 0 ? 'text-red-600' : 'text-gray-400'
                }`}
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
            <div className="ml-3">
              <p className="text-sm font-medium text-gray-500">Allergen Alerts</p>
              <p
                className={`text-lg font-semibold ${
                  allergenWarnings.length > 0 ? 'text-red-600' : 'text-gray-900'
                }`}
              >
                {allergenWarnings.length}
              </p>
            </div>
          </div>
        </div>
      </div>

      {/* Weekly Menu View */}
      <WeeklyMenuView
        menu={menu}
        weekStartDate={currentWeekStart}
        childAllergies={mockChildAllergies}
        allergenWarnings={allergenWarnings}
        onPreviousWeek={handlePreviousWeek}
        onNextWeek={handleNextWeek}
        showNavigation
        onMenuItemClick={handleMenuItemClick}
      />

      {/* Help section */}
      <div className="mt-8 rounded-lg bg-blue-50 p-4">
        <div className="flex">
          <div className="flex-shrink-0">
            <svg
              className="h-5 w-5 text-blue-400"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fillRule="evenodd"
                d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                clipRule="evenodd"
              />
            </svg>
          </div>
          <div className="ml-3">
            <h3 className="text-sm font-medium text-blue-800">
              About Allergen Warnings
            </h3>
            <p className="mt-1 text-sm text-blue-700">
              Menu items containing allergens that match your child&apos;s dietary
              profile are highlighted in red. Please contact the school if you have
              any questions or concerns about specific menu items.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
