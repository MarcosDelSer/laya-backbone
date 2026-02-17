'use client';

import { useState } from 'react';
import type {
  NutritionalReport as NutritionalReportData,
  DailyNutritionalBreakdown,
  MealType,
  MealAmount,
} from '../lib/types';

export interface NutritionalReportProps {
  /** Nutritional report data to display */
  report: NutritionalReportData;
  /** Whether to show detailed daily breakdown by default */
  expandedByDefault?: boolean;
}

const mealTypeLabels: Record<MealType, string> = {
  breakfast: 'Breakfast',
  lunch: 'Lunch',
  snack: 'Snack',
};

const appetiteLabels: Record<MealAmount, { label: string; color: string }> = {
  all: { label: 'Ate All', color: 'bg-green-500' },
  most: { label: 'Ate Most', color: 'bg-blue-500' },
  some: { label: 'Ate Some', color: 'bg-yellow-500' },
  none: { label: 'Did Not Eat', color: 'bg-gray-400' },
};

function formatDateRange(startDate: string, endDate: string): string {
  const start = new Date(startDate);
  const end = new Date(endDate);
  const options: Intl.DateTimeFormatOptions = { month: 'short', day: 'numeric' };

  if (start.getFullYear() !== end.getFullYear()) {
    return `${start.toLocaleDateString('en-US', { ...options, year: 'numeric' })} - ${end.toLocaleDateString('en-US', { ...options, year: 'numeric' })}`;
  }

  return `${start.toLocaleDateString('en-US', options)} - ${end.toLocaleDateString('en-US', options)}, ${end.getFullYear()}`;
}

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
}

function SectionHeader({ title, action }: { title: string; action?: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-4">
      <h4 className="font-medium text-gray-900">{title}</h4>
      {action}
    </div>
  );
}

function EmptyState({ message }: { message: string }) {
  return (
    <p className="text-sm text-gray-500 italic text-center py-4">{message}</p>
  );
}

function MacroNutrientBar({
  label,
  value,
  unit,
  percentage,
  color,
}: {
  label: string;
  value: number;
  unit: string;
  percentage: number;
  color: string;
}) {
  return (
    <div className="space-y-1">
      <div className="flex justify-between text-sm">
        <span className="text-gray-600">{label}</span>
        <span className="font-medium text-gray-900">
          {value.toFixed(1)}{unit}
        </span>
      </div>
      <div className="h-2 bg-gray-100 rounded-full overflow-hidden">
        <div
          className={`h-full rounded-full transition-all duration-300 ${color}`}
          style={{ width: `${Math.min(percentage, 100)}%` }}
        />
      </div>
    </div>
  );
}

function StatCard({
  label,
  value,
  unit,
  icon,
}: {
  label: string;
  value: string | number;
  unit?: string;
  icon: React.ReactNode;
}) {
  return (
    <div className="flex items-center space-x-3 rounded-lg bg-gray-50 px-4 py-3">
      <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-primary-100">
        {icon}
      </div>
      <div className="min-w-0 flex-1">
        <p className="text-sm text-gray-500 truncate">{label}</p>
        <p className="text-lg font-semibold text-gray-900">
          {typeof value === 'number' ? value.toFixed(0) : value}
          {unit && <span className="text-sm font-normal text-gray-500 ml-1">{unit}</span>}
        </p>
      </div>
    </div>
  );
}

function AppetiteTrendChart({ trends }: { trends: NutritionalReportData['appetiteTrends'] }) {
  const total = trends.all + trends.most + trends.some + trends.none;

  if (total === 0) {
    return <EmptyState message="No appetite data available" />;
  }

  const segments = [
    { key: 'all' as const, ...appetiteLabels.all, count: trends.all },
    { key: 'most' as const, ...appetiteLabels.most, count: trends.most },
    { key: 'some' as const, ...appetiteLabels.some, count: trends.some },
    { key: 'none' as const, ...appetiteLabels.none, count: trends.none },
  ].filter((s) => s.count > 0);

  return (
    <div className="space-y-4">
      {/* Horizontal bar chart */}
      <div className="flex h-8 rounded-lg overflow-hidden">
        {segments.map((segment) => {
          const percentage = (segment.count / total) * 100;
          return (
            <div
              key={segment.key}
              className={`${segment.color} transition-all duration-300`}
              style={{ width: `${percentage}%` }}
              title={`${segment.label}: ${segment.count} meals (${percentage.toFixed(0)}%)`}
            />
          );
        })}
      </div>

      {/* Legend */}
      <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
        {segments.map((segment) => {
          const percentage = (segment.count / total) * 100;
          return (
            <div key={segment.key} className="flex items-center space-x-2">
              <span className={`h-3 w-3 rounded-full ${segment.color}`} />
              <div className="text-sm">
                <span className="text-gray-600">{segment.label}</span>
                <span className="ml-1 font-medium text-gray-900">
                  {percentage.toFixed(0)}%
                </span>
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

function DailyBreakdownRow({ entry }: { entry: DailyNutritionalBreakdown }) {
  const appetiteInfo = appetiteLabels[entry.appetiteLevel];

  return (
    <div className="flex items-center justify-between rounded-lg bg-gray-50 px-4 py-3">
      <div className="min-w-0 flex-1">
        <div className="flex items-center space-x-2">
          <p className="font-medium text-gray-900">{formatDate(entry.date)}</p>
          <span className="text-sm text-gray-500">•</span>
          <p className="text-sm text-gray-600">{mealTypeLabels[entry.mealType]}</p>
        </div>
        <div className="mt-1 flex items-center space-x-4 text-sm text-gray-500">
          <span>{entry.calories} cal</span>
          <span>{entry.protein}g protein</span>
          <span>{entry.carbohydrates}g carbs</span>
          <span>{entry.fat}g fat</span>
        </div>
      </div>
      <span
        className={`ml-3 flex h-6 w-6 items-center justify-center rounded-full ${appetiteInfo.color}`}
        title={appetiteInfo.label}
      >
        <span className="sr-only">{appetiteInfo.label}</span>
        <svg className="h-4 w-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          {entry.appetiteLevel === 'all' && (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
          )}
          {entry.appetiteLevel === 'most' && (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 12H4" />
          )}
          {entry.appetiteLevel === 'some' && (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20 12H4" />
          )}
          {entry.appetiteLevel === 'none' && (
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
          )}
        </svg>
      </span>
    </div>
  );
}

/**
 * NutritionalReport displays nutrition summaries and trends for a child.
 * Shows totals, macro breakdown, appetite trends, and optional daily details.
 */
export function NutritionalReport({
  report,
  expandedByDefault = false,
}: NutritionalReportProps) {
  const [showDailyBreakdown, setShowDailyBreakdown] = useState(expandedByDefault);

  const { totals, dailyBreakdown, appetiteTrends } = report;
  const dateRange = formatDateRange(report.startDate, report.endDate);

  // Calculate macro percentages for visualization (based on calories)
  const totalMacroCalories =
    totals.totalProtein * 4 + totals.totalCarbohydrates * 4 + totals.totalFat * 9;
  const proteinPercentage = totalMacroCalories > 0
    ? ((totals.totalProtein * 4) / totalMacroCalories) * 100
    : 0;
  const carbsPercentage = totalMacroCalories > 0
    ? ((totals.totalCarbohydrates * 4) / totalMacroCalories) * 100
    : 0;
  const fatPercentage = totalMacroCalories > 0
    ? ((totals.totalFat * 9) / totalMacroCalories) * 100
    : 0;

  return (
    <div className="card">
      {/* Report Header */}
      <div className="card-header">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-green-100">
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
                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                />
              </svg>
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900">
                Nutritional Report
                {report.childName && (
                  <span className="ml-2 text-gray-500 font-normal">
                    for {report.childName}
                  </span>
                )}
              </h3>
              <p className="text-sm text-gray-600">{dateRange}</p>
            </div>
          </div>
          {/* Summary badges */}
          <div className="hidden sm:flex items-center space-x-2">
            <span className="badge badge-success">
              {totals.mealsTracked} Meal{totals.mealsTracked !== 1 ? 's' : ''} Tracked
            </span>
          </div>
        </div>
      </div>

      <div className="card-body space-y-6">
        {/* Summary Stats */}
        <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
          <StatCard
            label="Total Calories"
            value={totals.totalCalories}
            unit="kcal"
            icon={
              <svg className="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17.657 18.657A8 8 0 016.343 7.343S7 9 9 10c0-2 .5-5 2.986-7C14 5 16.09 5.777 17.656 7.343A7.975 7.975 0 0120 13a7.975 7.975 0 01-2.343 5.657z" />
              </svg>
            }
          />
          <StatCard
            label="Avg Calories/Day"
            value={totals.averageCaloriesPerDay}
            unit="kcal"
            icon={
              <svg className="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
              </svg>
            }
          />
          <StatCard
            label="Total Protein"
            value={totals.totalProtein}
            unit="g"
            icon={
              <svg className="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
              </svg>
            }
          />
          <StatCard
            label="Meals Tracked"
            value={totals.mealsTracked}
            icon={
              <svg className="h-5 w-5 text-primary-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
              </svg>
            }
          />
        </div>

        {/* Macronutrient Breakdown */}
        <div>
          <SectionHeader title="Macronutrient Breakdown" />
          <div className="space-y-4">
            <MacroNutrientBar
              label="Protein"
              value={totals.totalProtein}
              unit="g"
              percentage={proteinPercentage}
              color="bg-blue-500"
            />
            <MacroNutrientBar
              label="Carbohydrates"
              value={totals.totalCarbohydrates}
              unit="g"
              percentage={carbsPercentage}
              color="bg-yellow-500"
            />
            <MacroNutrientBar
              label="Fat"
              value={totals.totalFat}
              unit="g"
              percentage={fatPercentage}
              color="bg-red-400"
            />
          </div>
        </div>

        {/* Appetite Trends */}
        <div>
          <SectionHeader title="Appetite Trends" />
          <AppetiteTrendChart trends={appetiteTrends} />
        </div>

        {/* Daily Breakdown */}
        <div>
          <SectionHeader
            title="Daily Breakdown"
            action={
              dailyBreakdown.length > 0 && (
                <button
                  type="button"
                  onClick={() => setShowDailyBreakdown(!showDailyBreakdown)}
                  className="text-sm font-medium text-primary-600 hover:text-primary-700"
                >
                  {showDailyBreakdown ? 'Hide Details' : 'Show Details'}
                </button>
              )
            }
          />
          {dailyBreakdown.length === 0 ? (
            <EmptyState message="No daily breakdown data available" />
          ) : showDailyBreakdown ? (
            <div className="space-y-2 max-h-96 overflow-y-auto">
              {dailyBreakdown.map((entry, index) => (
                <DailyBreakdownRow key={`${entry.date}-${entry.mealType}-${index}`} entry={entry} />
              ))}
            </div>
          ) : (
            <p className="text-sm text-gray-500">
              {dailyBreakdown.length} meal{dailyBreakdown.length !== 1 ? 's' : ''} recorded.
              Click &quot;Show Details&quot; to view daily breakdown.
            </p>
          )}
        </div>
      </div>
    </div>
  );
}
