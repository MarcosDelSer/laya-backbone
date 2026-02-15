/**
 * LAYA Teacher App - Nap API
 *
 * API functions for managing child nap tracking including starting,
 * stopping, and fetching nap records. Follows patterns from
 * Gibbon CareTracking NapGateway.
 */

import {api} from './client';
import {API_CONFIG} from './config';
import type {ApiResponse, Child, NapRecord} from '../types';

/**
 * Nap quality options
 */
export type NapQuality = 'Sound' | 'Light' | 'Restless';

/**
 * Response type for naps list endpoint
 */
interface NapsListResponse {
  children: ChildWithNaps[];
  summary: NapsSummary;
}

/**
 * Child data combined with their nap records for the day
 */
export interface ChildWithNaps {
  child: Child;
  naps: NapRecord[];
  activeNap: NapRecord | null;
}

/**
 * Daily naps summary statistics
 */
export interface NapsSummary {
  totalNaps: number;
  childrenNapped: number;
  currentlySleeping: number;
  completedNaps: number;
  avgDurationMinutes: number;
}

/**
 * Request payload for starting a nap
 */
interface StartNapRequest {
  childId: string;
  time?: string;
  notes?: string;
}

/**
 * Request payload for stopping a nap
 */
interface StopNapRequest {
  childId: string;
  napId: string;
  time?: string;
  quality?: NapQuality;
  notes?: string;
}

/**
 * Response from nap start/stop operations
 */
interface NapActionResponse {
  napRecord: NapRecord;
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
 * Get the current time in HH:MM:SS format
 */
function getCurrentTime(): string {
  const now = new Date();
  return now.toTimeString().split(' ')[0];
}

/**
 * Get the current time as ISO string
 */
function getCurrentTimeISO(): string {
  return new Date().toISOString();
}

/**
 * Fetch all children with their nap status for the current day
 */
export async function fetchTodayNaps(): Promise<ApiResponse<NapsListResponse>> {
  const date = getCurrentDate();
  return api.get<NapsListResponse>(API_CONFIG.endpoints.naps.list, {date});
}

/**
 * Fetch naps for a specific date
 */
export async function fetchNapsByDate(
  date: string,
): Promise<ApiResponse<NapsListResponse>> {
  return api.get<NapsListResponse>(API_CONFIG.endpoints.naps.list, {date});
}

/**
 * Start a nap for a child
 *
 * Creates a new nap record with start time.
 * Follows the pattern from NapGateway::startNap()
 */
export async function startNap(
  childId: string,
  options?: {
    time?: string;
    notes?: string;
  },
): Promise<ApiResponse<NapActionResponse>> {
  const request: StartNapRequest = {
    childId,
    time: options?.time || getCurrentTimeISO(),
    notes: options?.notes,
  };

  return api.post<NapActionResponse>(API_CONFIG.endpoints.naps.start, request);
}

/**
 * Stop/end a nap for a child
 *
 * Updates the nap record with end time and quality.
 * Follows the pattern from NapGateway::endNap()
 */
export async function stopNap(
  childId: string,
  napId: string,
  options?: {
    time?: string;
    quality?: NapQuality;
    notes?: string;
  },
): Promise<ApiResponse<NapActionResponse>> {
  const request: StopNapRequest = {
    childId,
    napId,
    time: options?.time || getCurrentTimeISO(),
    quality: options?.quality,
    notes: options?.notes,
  };

  return api.post<NapActionResponse>(API_CONFIG.endpoints.naps.stop, request);
}

/**
 * Check if a child has an active (in-progress) nap
 */
export function hasActiveNap(childNaps: ChildWithNaps): boolean {
  return childNaps.activeNap !== null;
}

/**
 * Get the active nap for a child
 */
export function getActiveNap(childNaps: ChildWithNaps): NapRecord | null {
  return childNaps.activeNap;
}

/**
 * Get completed naps for a child today
 */
export function getCompletedNaps(childNaps: ChildWithNaps): NapRecord[] {
  return childNaps.naps.filter(nap => nap.endTime !== null);
}

/**
 * Calculate total nap time for a child (completed naps only)
 */
export function getTotalNapMinutes(naps: NapRecord[]): number {
  return naps.reduce((total, nap) => {
    if (nap.durationMinutes !== null) {
      return total + nap.durationMinutes;
    }
    return total;
  }, 0);
}

/**
 * Format duration in minutes to human-readable string
 */
export function formatDuration(minutes: number): string {
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
 * Format time from ISO string for display
 */
export function formatNapTime(timeString: string): string {
  try {
    const date = new Date(timeString);
    return date.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
  } catch {
    return '';
  }
}

/**
 * Get nap quality options
 */
export function getNapQualityOptions(): NapQuality[] {
  return ['Sound', 'Light', 'Restless'];
}

/**
 * Get display label for nap quality
 */
export function getNapQualityLabel(quality: NapQuality): string {
  const labels: Record<NapQuality, string> = {
    Sound: 'Sound Sleep',
    Light: 'Light Sleep',
    Restless: 'Restless',
  };
  return labels[quality] || quality;
}

/**
 * Get emoji icon for nap quality
 */
export function getNapQualityEmoji(quality: NapQuality): string {
  const emojis: Record<NapQuality, string> = {
    Sound: 'zzz',
    Light: 'z',
    Restless: '~',
  };
  return emojis[quality] || '';
}

/**
 * Calculate elapsed time since nap started (in milliseconds)
 */
export function calculateElapsedTime(startTime: string): number {
  const start = new Date(startTime).getTime();
  const now = Date.now();
  return Math.max(0, now - start);
}

/**
 * Format elapsed time in milliseconds to MM:SS or HH:MM:SS
 */
export function formatElapsedTime(milliseconds: number): string {
  const totalSeconds = Math.floor(milliseconds / 1000);
  const hours = Math.floor(totalSeconds / 3600);
  const minutes = Math.floor((totalSeconds % 3600) / 60);
  const seconds = totalSeconds % 60;

  if (hours > 0) {
    return `${hours}:${minutes.toString().padStart(2, '0')}:${seconds
      .toString()
      .padStart(2, '0')}`;
  }
  return `${minutes}:${seconds.toString().padStart(2, '0')}`;
}
