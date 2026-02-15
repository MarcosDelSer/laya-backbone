/**
 * LAYA Parent App - Daily Reports API
 *
 * API functions for fetching daily reports for children.
 * Provides meals, naps, activities, and photos information
 * for parents to stay informed about their child's day.
 */

import {api} from './client';
import {API_CONFIG} from './config';
import type {
  ApiResponse,
  DailyReport,
  MealEntry,
  NapEntry,
  Photo,
  Child,
  PaginatedResponse,
} from '../types';

// ============================================================================
// Response Types
// ============================================================================

/**
 * Response type for daily reports list endpoint
 */
export interface DailyReportsListResponse {
  reports: DailyReportWithChild[];
  children: Child[];
}

/**
 * Daily report combined with child information
 */
export interface DailyReportWithChild {
  report: DailyReport;
  child: Child;
}

/**
 * Summary statistics for a daily report
 */
export interface DailyReportSummary {
  totalMeals: number;
  totalNaps: number;
  totalActivities: number;
  totalPhotos: number;
  napDurationMinutes: number;
}

/**
 * Filter options for fetching daily reports
 */
export interface DailyReportsFilter {
  childId?: string;
  startDate?: string;
  endDate?: string;
  limit?: number;
  offset?: number;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get the current date in YYYY-MM-DD format
 */
function getCurrentDate(): string {
  const now = new Date();
  return now.toISOString().split('T')[0];
}

/**
 * Format date for display (e.g., "January 15, 2024")
 */
export function formatDateForDisplay(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Format time for display (e.g., "2:30 PM")
 */
export function formatTimeForDisplay(timeString: string): string {
  // Handle both ISO strings and time-only strings
  const date = timeString.includes('T')
    ? new Date(timeString)
    : new Date(`1970-01-01T${timeString}`);
  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

/**
 * Calculate nap duration in minutes
 */
export function calculateNapDuration(nap: NapEntry): number {
  const start = new Date(`1970-01-01T${nap.startTime}`);
  const end = new Date(`1970-01-01T${nap.endTime}`);
  return Math.round((end.getTime() - start.getTime()) / (1000 * 60));
}

/**
 * Calculate total nap duration for all naps
 */
export function calculateTotalNapDuration(naps: NapEntry[]): number {
  return naps.reduce((total, nap) => total + calculateNapDuration(nap), 0);
}

/**
 * Format nap duration for display (e.g., "1h 30m")
 */
export function formatNapDuration(minutes: number): string {
  if (minutes < 60) {
    return `${minutes}m`;
  }
  const hours = Math.floor(minutes / 60);
  const remainingMinutes = minutes % 60;
  if (remainingMinutes === 0) {
    return `${hours}h`;
  }
  return `${hours}h ${remainingMinutes}m`;
}

/**
 * Get meal amount display text
 */
export function getMealAmountDisplay(amount: MealEntry['amount']): string {
  const displayMap: Record<MealEntry['amount'], string> = {
    all: 'Ate everything',
    most: 'Ate most',
    some: 'Ate some',
    none: 'Did not eat',
  };
  return displayMap[amount];
}

/**
 * Get nap quality display text
 */
export function getNapQualityDisplay(quality: NapEntry['quality']): string {
  const displayMap: Record<NapEntry['quality'], string> = {
    good: 'Slept well',
    fair: 'Slept okay',
    poor: 'Had trouble sleeping',
  };
  return displayMap[quality];
}

/**
 * Get meal type display text
 */
export function getMealTypeDisplay(type: MealEntry['type']): string {
  const displayMap: Record<MealEntry['type'], string> = {
    breakfast: 'Breakfast',
    lunch: 'Lunch',
    snack: 'Snack',
  };
  return displayMap[type];
}

// ============================================================================
// API Functions
// ============================================================================

/**
 * Fetch today's daily reports for all children
 */
export async function fetchTodayReports(): Promise<ApiResponse<DailyReportsListResponse>> {
  const date = getCurrentDate();
  return api.get<DailyReportsListResponse>(
    API_CONFIG.endpoints.dailyReports.list,
    {date},
  );
}

/**
 * Fetch daily reports for a specific date
 */
export async function fetchReportsByDate(
  date: string,
): Promise<ApiResponse<DailyReportsListResponse>> {
  return api.get<DailyReportsListResponse>(
    API_CONFIG.endpoints.dailyReports.list,
    {date},
  );
}

/**
 * Fetch daily reports for a specific child
 */
export async function fetchReportsByChild(
  childId: string,
  options?: {
    startDate?: string;
    endDate?: string;
    limit?: number;
    offset?: number;
  },
): Promise<ApiResponse<PaginatedResponse<DailyReport>>> {
  const params: Record<string, string> = {
    childId,
  };

  if (options?.startDate) {
    params.startDate = options.startDate;
  }
  if (options?.endDate) {
    params.endDate = options.endDate;
  }
  if (options?.limit !== undefined) {
    params.limit = String(options.limit);
  }
  if (options?.offset !== undefined) {
    params.offset = String(options.offset);
  }

  return api.get<PaginatedResponse<DailyReport>>(
    API_CONFIG.endpoints.dailyReports.byChild,
    params,
  );
}

/**
 * Fetch a single daily report by ID
 */
export async function fetchReportById(
  reportId: string,
): Promise<ApiResponse<DailyReport>> {
  return api.get<DailyReport>(
    API_CONFIG.endpoints.dailyReports.details,
    {id: reportId},
  );
}

/**
 * Fetch daily reports with filters
 */
export async function fetchReportsWithFilters(
  filters: DailyReportsFilter,
): Promise<ApiResponse<PaginatedResponse<DailyReportWithChild>>> {
  const params: Record<string, string> = {};

  if (filters.childId) {
    params.childId = filters.childId;
  }
  if (filters.startDate) {
    params.startDate = filters.startDate;
  }
  if (filters.endDate) {
    params.endDate = filters.endDate;
  }
  if (filters.limit !== undefined) {
    params.limit = String(filters.limit);
  }
  if (filters.offset !== undefined) {
    params.offset = String(filters.offset);
  }

  return api.get<PaginatedResponse<DailyReportWithChild>>(
    API_CONFIG.endpoints.dailyReports.list,
    params,
  );
}

// ============================================================================
// Summary Functions
// ============================================================================

/**
 * Calculate summary statistics for a daily report
 */
export function calculateReportSummary(report: DailyReport): DailyReportSummary {
  return {
    totalMeals: report.meals.length,
    totalNaps: report.naps.length,
    totalActivities: report.activities.length,
    totalPhotos: report.photos.length,
    napDurationMinutes: calculateTotalNapDuration(report.naps),
  };
}

/**
 * Format daily report summary for display
 */
export function formatReportSummary(summary: DailyReportSummary): string {
  const parts: string[] = [];

  if (summary.totalMeals > 0) {
    parts.push(`${summary.totalMeals} meal${summary.totalMeals > 1 ? 's' : ''}`);
  }

  if (summary.totalNaps > 0) {
    const napText = summary.totalNaps === 1 ? '1 nap' : `${summary.totalNaps} naps`;
    const durationText = formatNapDuration(summary.napDurationMinutes);
    parts.push(`${napText} (${durationText})`);
  }

  if (summary.totalActivities > 0) {
    parts.push(`${summary.totalActivities} activit${summary.totalActivities > 1 ? 'ies' : 'y'}`);
  }

  if (summary.totalPhotos > 0) {
    parts.push(`${summary.totalPhotos} photo${summary.totalPhotos > 1 ? 's' : ''}`);
  }

  return parts.join(' • ');
}

/**
 * Check if a report is from today
 */
export function isToday(dateString: string): boolean {
  return dateString === getCurrentDate();
}

/**
 * Check if a report is from yesterday
 */
export function isYesterday(dateString: string): boolean {
  const yesterday = new Date();
  yesterday.setDate(yesterday.getDate() - 1);
  return dateString === yesterday.toISOString().split('T')[0];
}

/**
 * Get relative date display (e.g., "Today", "Yesterday", or formatted date)
 */
export function getRelativeDateDisplay(dateString: string): string {
  if (isToday(dateString)) {
    return 'Today';
  }
  if (isYesterday(dateString)) {
    return 'Yesterday';
  }
  return formatDateForDisplay(dateString);
}

/**
 * Sort daily reports by date (most recent first)
 */
export function sortReportsByDate(reports: DailyReport[]): DailyReport[] {
  return [...reports].sort((a, b) => {
    return new Date(b.date).getTime() - new Date(a.date).getTime();
  });
}

/**
 * Group daily reports by date
 */
export function groupReportsByDate(
  reports: DailyReport[],
): Map<string, DailyReport[]> {
  const grouped = new Map<string, DailyReport[]>();

  for (const report of reports) {
    const existing = grouped.get(report.date) || [];
    existing.push(report);
    grouped.set(report.date, existing);
  }

  return grouped;
}

/**
 * Check if report has any content (meals, naps, activities, or photos)
 */
export function hasContent(report: DailyReport): boolean {
  return (
    report.meals.length > 0 ||
    report.naps.length > 0 ||
    report.activities.length > 0 ||
    report.photos.length > 0
  );
}

/**
 * Get the most recent photo from a report
 */
export function getLatestPhoto(report: DailyReport): Photo | null {
  if (report.photos.length === 0) {
    return null;
  }
  return report.photos[report.photos.length - 1];
}
