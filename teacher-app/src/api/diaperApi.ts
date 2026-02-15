/**
 * LAYA Teacher App - Diaper API
 *
 * API functions for managing child diaper tracking including logging
 * diaper changes and fetching diaper records. Follows patterns from
 * Gibbon CareTracking DiaperGateway.
 */

import {api} from './client';
import {API_CONFIG} from './config';
import type {ApiResponse, Child, DiaperRecord, DiaperType} from '../types';

/**
 * Response type for diapers list endpoint
 */
interface DiapersListResponse {
  children: ChildWithDiapers[];
  summary: DiapersSummary;
}

/**
 * Child data combined with their diaper records for the day
 */
export interface ChildWithDiapers {
  child: Child;
  diapers: DiaperRecord[];
  lastChange: DiaperRecord | null;
}

/**
 * Daily diapers summary statistics
 */
export interface DiapersSummary {
  totalChanges: number;
  wetChanges: number;
  soiledChanges: number;
  dryChanges: number;
  childrenChanged: number;
}

/**
 * Request payload for logging a diaper change
 */
interface LogDiaperRequest {
  childId: string;
  type: DiaperType;
  notes?: string;
  time?: string;
}

/**
 * Response from diaper logging operation
 */
interface DiaperLogResponse {
  diaperRecord: DiaperRecord;
  message: string;
}

/**
 * Get the current date in YYYY-MM-DD format
 */
function getCurrentDate(): string {
  const now = new Date();
  return now.toISOString().split('T')[0];
}

/**
 * Get the current time in ISO format
 */
function getCurrentTimeISO(): string {
  return new Date().toISOString();
}

/**
 * Fetch all children with their diaper records for the current day
 */
export async function fetchTodayDiapers(): Promise<ApiResponse<DiapersListResponse>> {
  const date = getCurrentDate();
  return api.get<DiapersListResponse>(API_CONFIG.endpoints.diapers.list, {date});
}

/**
 * Fetch diapers for a specific date
 */
export async function fetchDiapersByDate(
  date: string,
): Promise<ApiResponse<DiapersListResponse>> {
  return api.get<DiapersListResponse>(API_CONFIG.endpoints.diapers.list, {date});
}

/**
 * Log a diaper change for a child
 *
 * Creates a new diaper record with the specified type.
 * Follows the pattern from DiaperGateway::insert()
 */
export async function logDiaper(
  childId: string,
  type: DiaperType,
  options?: {
    notes?: string;
    time?: string;
  },
): Promise<ApiResponse<DiaperLogResponse>> {
  const request: LogDiaperRequest = {
    childId,
    type,
    notes: options?.notes,
    time: options?.time || getCurrentTimeISO(),
  };

  return api.post<DiaperLogResponse>(API_CONFIG.endpoints.diapers.log, request);
}

/**
 * Get the last diaper change for a child
 */
export function getLastDiaperChange(
  diapers: DiaperRecord[],
): DiaperRecord | null {
  if (diapers.length === 0) {
    return null;
  }
  // Assume diapers are sorted by time, return the last one
  return diapers[diapers.length - 1];
}

/**
 * Get count of diaper changes by type
 */
export function getDiaperCountByType(
  diapers: DiaperRecord[],
  type: DiaperType,
): number {
  return diapers.filter(diaper => diaper.type === type).length;
}

/**
 * Get display label for diaper type
 */
export function getDiaperTypeLabel(type: DiaperType): string {
  const labels: Record<DiaperType, string> = {
    wet: 'Wet',
    soiled: 'Soiled',
    dry: 'Dry',
  };
  return labels[type] || type;
}

/**
 * Get all available diaper types
 */
export function getDiaperTypes(): DiaperType[] {
  return ['wet', 'soiled', 'dry'];
}

/**
 * Format time from ISO string for display
 */
export function formatDiaperTime(timeString: string): string {
  try {
    const date = new Date(timeString);
    return date.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
  } catch {
    return '';
  }
}

/**
 * Calculate time since last diaper change
 */
export function getTimeSinceLastChange(lastChangeTime: string): string {
  try {
    const lastChange = new Date(lastChangeTime).getTime();
    const now = Date.now();
    const diffMinutes = Math.floor((now - lastChange) / 60000);

    if (diffMinutes < 1) {
      return 'Just now';
    } else if (diffMinutes < 60) {
      return `${diffMinutes}m ago`;
    } else {
      const hours = Math.floor(diffMinutes / 60);
      const minutes = diffMinutes % 60;
      if (minutes === 0) {
        return `${hours}h ago`;
      }
      return `${hours}h ${minutes}m ago`;
    }
  } catch {
    return '';
  }
}
