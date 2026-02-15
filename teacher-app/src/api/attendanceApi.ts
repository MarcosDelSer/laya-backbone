/**
 * LAYA Teacher App - Attendance API
 *
 * API functions for managing child attendance including check-in,
 * check-out, and fetching attendance records. Follows patterns
 * from Gibbon CareTracking AttendanceGateway.
 */

import {api} from './client';
import {API_CONFIG} from './config';
import type {
  ApiResponse,
  Child,
  AttendanceRecord,
  AttendanceStatus,
} from '../types';

/**
 * Response type for attendance list endpoint
 */
interface AttendanceListResponse {
  children: ChildWithAttendance[];
  summary: AttendanceSummary;
}

/**
 * Child data combined with their attendance record for the day
 */
export interface ChildWithAttendance {
  child: Child;
  attendance: AttendanceRecord | null;
}

/**
 * Daily attendance summary statistics
 */
export interface AttendanceSummary {
  totalChildren: number;
  totalCheckedIn: number;
  totalCheckedOut: number;
  currentlyPresent: number;
  totalAbsent: number;
  totalLateArrivals: number;
}

/**
 * Request payload for check-in
 */
interface CheckInRequest {
  childId: string;
  time?: string;
  notes?: string;
  lateArrival?: boolean;
}

/**
 * Request payload for check-out
 */
interface CheckOutRequest {
  childId: string;
  attendanceId: string;
  time?: string;
  notes?: string;
  earlyDeparture?: boolean;
  pickupPersonName?: string;
}

/**
 * Response from check-in/check-out operations
 */
interface AttendanceActionResponse {
  attendanceRecord: AttendanceRecord;
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
 * Fetch all children with their attendance status for the current day
 */
export async function fetchTodayAttendance(): Promise<ApiResponse<AttendanceListResponse>> {
  const date = getCurrentDate();
  return api.get<AttendanceListResponse>(
    API_CONFIG.endpoints.attendance.list,
    {date},
  );
}

/**
 * Fetch attendance for a specific date
 */
export async function fetchAttendanceByDate(
  date: string,
): Promise<ApiResponse<AttendanceListResponse>> {
  return api.get<AttendanceListResponse>(
    API_CONFIG.endpoints.attendance.list,
    {date},
  );
}

/**
 * Check in a child
 *
 * Creates or updates an attendance record with check-in time.
 * Follows the pattern from AttendanceGateway::checkIn()
 */
export async function checkInChild(
  childId: string,
  options?: {
    time?: string;
    notes?: string;
    lateArrival?: boolean;
  },
): Promise<ApiResponse<AttendanceActionResponse>> {
  const request: CheckInRequest = {
    childId,
    time: options?.time || getCurrentTime(),
    notes: options?.notes,
    lateArrival: options?.lateArrival ?? false,
  };

  return api.post<AttendanceActionResponse>(
    API_CONFIG.endpoints.attendance.checkIn,
    request,
  );
}

/**
 * Check out a child
 *
 * Updates the attendance record with check-out time and pickup information.
 * Follows the pattern from AttendanceGateway::checkOut()
 */
export async function checkOutChild(
  childId: string,
  attendanceId: string,
  options?: {
    time?: string;
    notes?: string;
    earlyDeparture?: boolean;
    pickupPersonName?: string;
  },
): Promise<ApiResponse<AttendanceActionResponse>> {
  const request: CheckOutRequest = {
    childId,
    attendanceId,
    time: options?.time || getCurrentTime(),
    notes: options?.notes,
    earlyDeparture: options?.earlyDeparture ?? false,
    pickupPersonName: options?.pickupPersonName,
  };

  return api.post<AttendanceActionResponse>(
    API_CONFIG.endpoints.attendance.checkOut,
    request,
  );
}

/**
 * Calculate attendance status from an attendance record
 */
export function calculateAttendanceStatus(
  attendance: AttendanceRecord | null,
): AttendanceStatus {
  if (!attendance) {
    return 'absent';
  }

  if (attendance.checkOutTime) {
    if (attendance.status === 'early_pickup') {
      return 'early_pickup';
    }
    // Already checked out - show their arrival status
    return attendance.status === 'late' ? 'late' : 'present';
  }

  if (attendance.checkInTime) {
    return attendance.status === 'late' ? 'late' : 'present';
  }

  return 'absent';
}

/**
 * Format attendance summary for display
 */
export function formatAttendanceSummary(summary: AttendanceSummary): string {
  return `${summary.currentlyPresent}/${summary.totalChildren} present`;
}

/**
 * Check if it's late arrival time (after 9:00 AM by default)
 */
export function isLateArrival(
  time: string = getCurrentTime(),
  lateThreshold: string = '09:00:00',
): boolean {
  return time > lateThreshold;
}

/**
 * Check if it's early departure time (before 3:00 PM by default)
 */
export function isEarlyDeparture(
  time: string = getCurrentTime(),
  earlyThreshold: string = '15:00:00',
): boolean {
  return time < earlyThreshold;
}
