'use client';

import { useMemo } from 'react';
import type {
  WeeklyMenu,
  WeeklyMenuEntry,
  MenuItem,
  MealType,
  AllergenWarning,
  ChildAllergy,
} from '../lib/types';
import { MenuItemCard } from './MenuItemCard';
import { AllergenBadge } from './AllergenBadge';

interface WeeklyMenuViewProps {
  /** Weekly menu data to display */
  menu: WeeklyMenu;
  /** Current week start date for display */
  weekStartDate: Date;
  /** Child's allergies to highlight warnings */
  childAllergies?: ChildAllergy[];
  /** Allergen warnings for this child */
  allergenWarnings?: AllergenWarning[];
  /** Callback for navigating to previous week */
  onPreviousWeek?: () => void;
  /** Callback for navigating to next week */
  onNextWeek?: () => void;
  /** Whether to show week navigation controls */
  showNavigation?: boolean;
  /** Callback when a menu item is clicked */
  onMenuItemClick?: (menuItem: MenuItem) => void;
}

const mealTypeConfig: Record<MealType, { label: string; icon: React.ReactNode }> = {
  breakfast: {
    label: 'Breakfast',
    icon: (
      <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
        />
      </svg>
    ),
  },
  lunch: {
    label: 'Lunch',
    icon: (
      <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
        />
      </svg>
    ),
  },
  snack: {
    label: 'Snack',
    icon: (
      <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.701 2.701 0 00-1.5-.454M9 6v2m3-2v2m3-2v2M9 3h.01M12 3h.01M15 3h.01M21 21v-7a2 2 0 00-2-2H5a2 2 0 00-2 2v7h18z"
        />
      </svg>
    ),
  },
};

const MEAL_TYPES: MealType[] = ['breakfast', 'lunch', 'snack'];

function formatWeekRange(startDate: Date, endDate: Date): string {
  const options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' };
  const yearOptions: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric', year: 'numeric' };

  const startYear = startDate.getFullYear();
  const endYear = endDate.getFullYear();
  const currentYear = new Date().getFullYear();

  if (startYear !== endYear) {
    return `${startDate.toLocaleDateString('en-US', yearOptions)} - ${endDate.toLocaleDateString('en-US', yearOptions)}`;
  }

  if (startYear !== currentYear) {
    return `${startDate.toLocaleDateString('en-US', options)} - ${endDate.toLocaleDateString('en-US', yearOptions)}`;
  }

  return `${startDate.toLocaleDateString('en-US', options)} - ${endDate.toLocaleDateString('en-US', options)}`;
}

function getDayName(date: Date): string {
  return date.toLocaleDateString('en-US', { weekday: 'short' });
}

function getDateLabel(date: Date): string {
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}

function isToday(date: Date): boolean {
  const today = new Date();
  return date.toDateString() === today.toDateString();
}

function getWeekDays(startDate: Date): Date[] {
  const days: Date[] = [];
  for (let i = 0; i < 5; i++) {
    const date = new Date(startDate);
    date.setDate(startDate.getDate() + i);
    days.push(date);
  }
  return days;
}

function formatDateKey(date: Date): string {
  return date.toISOString().split('T')[0];
}

function SectionHeader({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-4">
      <div>
        <h4 className="font-medium text-gray-900">{title}</h4>
        {subtitle && <p className="text-sm text-gray-500">{subtitle}</p>}
      </div>
    </div>
  );
}

function EmptyState({ message }: { message: string }) {
  return (
    <p className="text-sm text-gray-400 italic text-center py-4">{message}</p>
  );
}

interface MenuCellProps {
  date: Date;
  mealType: MealType;
  items: MenuItem[];
  warnings: AllergenWarning[];
  onMenuItemClick?: (menuItem: MenuItem) => void;
}

function MenuCell({ date, mealType, items, warnings, onMenuItemClick }: MenuCellProps) {
  const hasWarnings = warnings.length > 0;

  return (
    <div
      className={`min-h-[120px] p-2 ${
        hasWarnings ? 'bg-red-50 border-red-200' : 'bg-white'
      }`}
    >
      {/* Allergen warnings for this cell */}
      {hasWarnings && (
        <div className="mb-2">
          <div className="flex flex-wrap gap-1">
            {warnings.map((warning, idx) => (
              <AllergenBadge
                key={`${warning.menuItemId}-${warning.allergen}-${idx}`}
                allergen={warning.allergen}
                severity={warning.severity}
                size="sm"
              />
            ))}
          </div>
        </div>
      )}

      {/* Menu items */}
      {items.length > 0 ? (
        <div className="space-y-2">
          {items.map((item) => (
            <MenuItemCard
              key={item.id}
              menuItem={item}
              compact
              onClick={onMenuItemClick}
            />
          ))}
        </div>
      ) : (
        <div className="flex items-center justify-center h-full min-h-[80px]">
          <span className="text-xs text-gray-400">No items</span>
        </div>
      )}
    </div>
  );
}

/**
 * WeeklyMenuView displays a weekly menu calendar with days as columns and meal types as rows.
 * Each cell contains menu items for that day/meal combination.
 * Highlights allergen warnings for children with matching allergies.
 */
export function WeeklyMenuView({
  menu,
  weekStartDate,
  childAllergies = [],
  allergenWarnings = [],
  onPreviousWeek,
  onNextWeek,
  showNavigation = true,
  onMenuItemClick,
}: WeeklyMenuViewProps) {
  // Get the week end date (Friday)
  const weekEndDate = useMemo(() => {
    const end = new Date(weekStartDate);
    end.setDate(weekStartDate.getDate() + 4);
    return end;
  }, [weekStartDate]);

  // Get all days of the week (Mon-Fri)
  const weekDays = useMemo(() => getWeekDays(weekStartDate), [weekStartDate]);

  // Organize menu entries by date and meal type for quick lookup
  const menuByDateAndType = useMemo(() => {
    const lookup: Record<string, Record<MealType, MenuItem[]>> = {};

    menu.entries.forEach((entry) => {
      const dateKey = entry.date;
      if (!lookup[dateKey]) {
        lookup[dateKey] = { breakfast: [], lunch: [], snack: [] };
      }
      lookup[dateKey][entry.mealType] = entry.menuItems;
    });

    return lookup;
  }, [menu.entries]);

  // Organize warnings by date and meal type
  const warningsByDateAndType = useMemo(() => {
    const lookup: Record<string, Record<MealType, AllergenWarning[]>> = {};

    allergenWarnings.forEach((warning) => {
      const dateKey = warning.date;
      if (!lookup[dateKey]) {
        lookup[dateKey] = { breakfast: [], lunch: [], snack: [] };
      }
      lookup[dateKey][warning.mealType].push(warning);
    });

    return lookup;
  }, [allergenWarnings]);

  // Count total allergen warnings
  const totalWarnings = allergenWarnings.length;

  // Get items and warnings for a specific cell
  const getCellData = (date: Date, mealType: MealType) => {
    const dateKey = formatDateKey(date);
    const items = menuByDateAndType[dateKey]?.[mealType] || [];
    const warnings = warningsByDateAndType[dateKey]?.[mealType] || [];
    return { items, warnings };
  };

  return (
    <div className="card">
      {/* Header with week navigation */}
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
              <h3 className="text-lg font-semibold text-gray-900">Weekly Menu</h3>
              <p className="text-sm text-gray-600">
                {formatWeekRange(weekStartDate, weekEndDate)}
              </p>
            </div>
          </div>

          {/* Navigation controls */}
          {showNavigation && (
            <div className="flex items-center space-x-2">
              <button
                type="button"
                onClick={onPreviousWeek}
                className="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                aria-label="Previous week"
              >
                <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                </svg>
              </button>
              <button
                type="button"
                onClick={onNextWeek}
                className="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50"
                aria-label="Next week"
              >
                <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
                </svg>
              </button>
            </div>
          )}
        </div>

        {/* Warning banner if child has allergens in this week's menu */}
        {totalWarnings > 0 && (
          <div className="mt-4 rounded-md bg-red-50 p-3">
            <div className="flex">
              <div className="flex-shrink-0">
                <svg className="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                  <path
                    fillRule="evenodd"
                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                    clipRule="evenodd"
                  />
                </svg>
              </div>
              <div className="ml-3">
                <h3 className="text-sm font-medium text-red-800">
                  Allergen Warning
                </h3>
                <p className="mt-1 text-sm text-red-700">
                  {totalWarnings} menu item{totalWarnings > 1 ? 's' : ''} contain{totalWarnings === 1 ? 's' : ''} allergens
                  that match your child&apos;s profile. Please review highlighted items below.
                </p>
              </div>
            </div>
          </div>
        )}
      </div>

      <div className="card-body p-0">
        {/* Desktop/Tablet: Calendar grid view */}
        <div className="hidden md:block overflow-x-auto">
          <table className="w-full border-collapse">
            <thead>
              <tr>
                {/* Empty corner cell */}
                <th className="w-24 border-b border-r border-gray-200 bg-gray-50 p-2" />
                {/* Day headers */}
                {weekDays.map((date) => (
                  <th
                    key={date.toISOString()}
                    className={`border-b border-r border-gray-200 p-3 text-center ${
                      isToday(date) ? 'bg-primary-50' : 'bg-gray-50'
                    }`}
                  >
                    <div className="text-sm font-semibold text-gray-900">
                      {getDayName(date)}
                    </div>
                    <div
                      className={`text-xs ${
                        isToday(date) ? 'text-primary-600 font-medium' : 'text-gray-500'
                      }`}
                    >
                      {getDateLabel(date)}
                      {isToday(date) && (
                        <span className="ml-1 inline-flex items-center rounded-full bg-primary-100 px-1.5 py-0.5 text-xs font-medium text-primary-700">
                          Today
                        </span>
                      )}
                    </div>
                  </th>
                ))}
              </tr>
            </thead>
            <tbody>
              {MEAL_TYPES.map((mealType) => {
                const config = mealTypeConfig[mealType];
                return (
                  <tr key={mealType}>
                    {/* Meal type label */}
                    <td className="border-b border-r border-gray-200 bg-gray-50 p-3">
                      <div className="flex items-center space-x-2">
                        <span className="text-gray-500">{config.icon}</span>
                        <span className="text-sm font-medium text-gray-700">
                          {config.label}
                        </span>
                      </div>
                    </td>
                    {/* Menu cells for each day */}
                    {weekDays.map((date) => {
                      const { items, warnings } = getCellData(date, mealType);
                      return (
                        <td
                          key={`${date.toISOString()}-${mealType}`}
                          className={`border-b border-r border-gray-200 align-top ${
                            isToday(date) ? 'bg-primary-50/30' : ''
                          }`}
                        >
                          <MenuCell
                            date={date}
                            mealType={mealType}
                            items={items}
                            warnings={warnings}
                            onMenuItemClick={onMenuItemClick}
                          />
                        </td>
                      );
                    })}
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Mobile: Stacked day view */}
        <div className="md:hidden divide-y divide-gray-200">
          {weekDays.map((date) => (
            <div key={date.toISOString()} className="p-4">
              <div
                className={`flex items-center justify-between mb-3 ${
                  isToday(date) ? 'text-primary-600' : 'text-gray-900'
                }`}
              >
                <div>
                  <span className="font-semibold">{getDayName(date)}</span>
                  <span className="ml-2 text-sm text-gray-500">
                    {getDateLabel(date)}
                  </span>
                </div>
                {isToday(date) && (
                  <span className="inline-flex items-center rounded-full bg-primary-100 px-2 py-0.5 text-xs font-medium text-primary-700">
                    Today
                  </span>
                )}
              </div>

              <div className="space-y-4">
                {MEAL_TYPES.map((mealType) => {
                  const { items, warnings } = getCellData(date, mealType);
                  const config = mealTypeConfig[mealType];

                  return (
                    <div key={mealType}>
                      <div className="flex items-center space-x-2 mb-2">
                        <span className="text-gray-400">{config.icon}</span>
                        <span className="text-sm font-medium text-gray-700">
                          {config.label}
                        </span>
                        {warnings.length > 0 && (
                          <span className="ml-auto inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">
                            ⚠ {warnings.length} warning{warnings.length > 1 ? 's' : ''}
                          </span>
                        )}
                      </div>

                      {/* Warnings */}
                      {warnings.length > 0 && (
                        <div className="mb-2 flex flex-wrap gap-1">
                          {warnings.map((warning, idx) => (
                            <AllergenBadge
                              key={`${warning.menuItemId}-${warning.allergen}-${idx}`}
                              allergen={warning.allergen}
                              severity={warning.severity}
                              size="sm"
                            />
                          ))}
                        </div>
                      )}

                      {/* Items */}
                      {items.length > 0 ? (
                        <div className="space-y-2 pl-7">
                          {items.map((item) => (
                            <MenuItemCard
                              key={item.id}
                              menuItem={item}
                              compact
                              onClick={onMenuItemClick}
                            />
                          ))}
                        </div>
                      ) : (
                        <p className="text-sm text-gray-400 italic pl-7">
                          No items scheduled
                        </p>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          ))}
        </div>

        {/* Legend for child allergies */}
        {childAllergies.length > 0 && (
          <div className="border-t border-gray-200 p-4 bg-gray-50">
            <SectionHeader title="Your Child's Allergies" />
            <div className="flex flex-wrap gap-2">
              {childAllergies.map((allergy, idx) => (
                <AllergenBadge
                  key={`${allergy.allergen}-${idx}`}
                  allergen={allergy.allergen}
                  severity={allergy.severity}
                  size="md"
                />
              ))}
            </div>
            <p className="mt-2 text-xs text-gray-500">
              Menu items containing these allergens are highlighted in red.
            </p>
          </div>
        )}
      </div>
    </div>
  );
}
