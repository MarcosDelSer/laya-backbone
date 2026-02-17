'use client';

import { useCallback, useMemo } from 'react';
import type { AttendancePattern } from '@/lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Attendance pattern data without id and formId for the wizard.
 */
export type AttendanceData = Omit<AttendancePattern, 'id' | 'formId'>;

interface AttendancePatternSectionProps {
  /** Current attendance pattern data */
  data: AttendanceData | null;
  /** Callback when data changes */
  onChange: (data: AttendanceData | null) => void;
  /** Whether the form is disabled */
  disabled?: boolean;
  /** Validation errors to display */
  errors?: string[];
}

// ============================================================================
// Constants
// ============================================================================

/**
 * Days of the week configuration.
 */
const DAYS_OF_WEEK = [
  { key: 'monday', label: 'Monday', labelFr: 'Lundi' },
  { key: 'tuesday', label: 'Tuesday', labelFr: 'Mardi' },
  { key: 'wednesday', label: 'Wednesday', labelFr: 'Mercredi' },
  { key: 'thursday', label: 'Thursday', labelFr: 'Jeudi' },
  { key: 'friday', label: 'Friday', labelFr: 'Vendredi' },
  { key: 'saturday', label: 'Saturday', labelFr: 'Samedi' },
  { key: 'sunday', label: 'Sunday', labelFr: 'Dimanche' },
] as const;

type DayKey = (typeof DAYS_OF_WEEK)[number]['key'];

// ============================================================================
// Default Data
// ============================================================================

const createDefaultAttendanceData = (): AttendanceData => ({
  mondayAm: false,
  mondayPm: false,
  tuesdayAm: false,
  tuesdayPm: false,
  wednesdayAm: false,
  wednesdayPm: false,
  thursdayAm: false,
  thursdayPm: false,
  fridayAm: false,
  fridayPm: false,
  saturdayAm: false,
  saturdayPm: false,
  sundayAm: false,
  sundayPm: false,
  expectedHoursPerWeek: undefined,
  expectedArrivalTime: '',
  expectedDepartureTime: '',
  notes: '',
});

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get the AM field key for a day.
 */
const getAmKey = (day: DayKey): keyof AttendanceData => {
  return `${day}Am` as keyof AttendanceData;
};

/**
 * Get the PM field key for a day.
 */
const getPmKey = (day: DayKey): keyof AttendanceData => {
  return `${day}Pm` as keyof AttendanceData;
};

// ============================================================================
// Component
// ============================================================================

/**
 * Attendance pattern section for enrollment form wizard.
 * Provides a 7-day grid with AM/PM checkboxes, expected arrival/departure times,
 * expected hours per week, and additional notes.
 */
export function AttendancePatternSection({
  data,
  onChange,
  disabled = false,
  errors = [],
}: AttendancePatternSectionProps) {
  // ---------------------------------------------------------------------------
  // Initialize Data
  // ---------------------------------------------------------------------------

  const attendanceData = data ?? createDefaultAttendanceData();

  // ---------------------------------------------------------------------------
  // Computed Values
  // ---------------------------------------------------------------------------

  /**
   * Calculate the number of scheduled days and periods.
   */
  const scheduleStats = useMemo(() => {
    let scheduledDays = 0;
    let scheduledPeriods = 0;
    let hasWeekendAttendance = false;

    DAYS_OF_WEEK.forEach(({ key }) => {
      const amKey = getAmKey(key);
      const pmKey = getPmKey(key);
      const amSelected = attendanceData[amKey] as boolean;
      const pmSelected = attendanceData[pmKey] as boolean;

      if (amSelected || pmSelected) {
        scheduledDays++;
        if (amSelected) scheduledPeriods++;
        if (pmSelected) scheduledPeriods++;

        if (key === 'saturday' || key === 'sunday') {
          hasWeekendAttendance = true;
        }
      }
    });

    const isFullTime = scheduledPeriods >= 10; // 5 days x 2 periods

    return {
      scheduledDays,
      scheduledPeriods,
      hasWeekendAttendance,
      isFullTime,
    };
  }, [attendanceData]);

  // ---------------------------------------------------------------------------
  // Handlers
  // ---------------------------------------------------------------------------

  const handleCheckboxChange = useCallback(
    (field: keyof AttendanceData) =>
      (e: React.ChangeEvent<HTMLInputElement>) => {
        onChange({ ...attendanceData, [field]: e.target.checked });
      },
    [attendanceData, onChange]
  );

  const handleInputChange = useCallback(
    (field: keyof AttendanceData) =>
      (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => {
        onChange({ ...attendanceData, [field]: e.target.value });
      },
    [attendanceData, onChange]
  );

  const handleNumberChange = useCallback(
    (field: keyof AttendanceData) =>
      (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value;
        onChange({
          ...attendanceData,
          [field]: value === '' ? undefined : parseFloat(value),
        });
      },
    [attendanceData, onChange]
  );

  /**
   * Select all weekday periods (Mon-Fri, AM and PM).
   */
  const handleSelectAllWeekdays = useCallback(() => {
    onChange({
      ...attendanceData,
      mondayAm: true,
      mondayPm: true,
      tuesdayAm: true,
      tuesdayPm: true,
      wednesdayAm: true,
      wednesdayPm: true,
      thursdayAm: true,
      thursdayPm: true,
      fridayAm: true,
      fridayPm: true,
    });
  }, [attendanceData, onChange]);

  /**
   * Clear all schedule selections.
   */
  const handleClearAll = useCallback(() => {
    onChange({
      ...attendanceData,
      mondayAm: false,
      mondayPm: false,
      tuesdayAm: false,
      tuesdayPm: false,
      wednesdayAm: false,
      wednesdayPm: false,
      thursdayAm: false,
      thursdayPm: false,
      fridayAm: false,
      fridayPm: false,
      saturdayAm: false,
      saturdayPm: false,
      sundayAm: false,
      sundayPm: false,
    });
  }, [attendanceData, onChange]);

  // ---------------------------------------------------------------------------
  // Render
  // ---------------------------------------------------------------------------

  return (
    <div className="space-y-6">
      {/* Section Header */}
      <div>
        <h3 className="text-lg font-medium text-gray-900">
          Weekly Attendance Schedule
        </h3>
        <p className="mt-1 text-sm text-gray-500">
          Select the days and times the child will attend the childcare center.
          This helps us plan staffing and activities.
        </p>
      </div>

      {/* Validation Errors */}
      {errors.length > 0 && (
        <div className="rounded-lg bg-red-50 p-4">
          <div className="flex items-start space-x-3">
            <svg
              className="h-5 w-5 text-red-400 flex-shrink-0 mt-0.5"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fillRule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                clipRule="evenodd"
              />
            </svg>
            <div>
              <h4 className="text-sm font-medium text-red-800">
                Please correct the following:
              </h4>
              <ul className="mt-1 text-sm text-red-700 list-disc list-inside">
                {errors.map((error, index) => (
                  <li key={index}>{error}</li>
                ))}
              </ul>
            </div>
          </div>
        </div>
      )}

      {/* ================================================================== */}
      {/* Quick Actions */}
      {/* ================================================================== */}
      <div className="flex flex-wrap gap-3">
        <button
          type="button"
          onClick={handleSelectAllWeekdays}
          disabled={disabled}
          className="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <svg
            className="h-4 w-4 mr-1.5"
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
          Select All Weekdays
        </button>
        <button
          type="button"
          onClick={handleClearAll}
          disabled={disabled}
          className="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        >
          <svg
            className="h-4 w-4 mr-1.5"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
          Clear All
        </button>
      </div>

      {/* ================================================================== */}
      {/* Weekly Schedule Grid */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Weekly Schedule
        </h4>

        {/* Schedule Grid */}
        <div className="overflow-x-auto">
          <table className="min-w-full divide-y divide-gray-200 border border-gray-200 rounded-lg">
            <thead className="bg-gray-50">
              <tr>
                <th
                  scope="col"
                  className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"
                >
                  Day
                </th>
                <th
                  scope="col"
                  className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider"
                >
                  AM
                  <span className="block text-[10px] font-normal normal-case text-gray-400">
                    (Morning)
                  </span>
                </th>
                <th
                  scope="col"
                  className="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider"
                >
                  PM
                  <span className="block text-[10px] font-normal normal-case text-gray-400">
                    (Afternoon)
                  </span>
                </th>
              </tr>
            </thead>
            <tbody className="bg-white divide-y divide-gray-200">
              {DAYS_OF_WEEK.map(({ key, label }, index) => {
                const amKey = getAmKey(key);
                const pmKey = getPmKey(key);
                const isWeekend = key === 'saturday' || key === 'sunday';

                return (
                  <tr
                    key={key}
                    className={
                      isWeekend
                        ? 'bg-gray-50'
                        : index % 2 === 0
                          ? 'bg-white'
                          : 'bg-gray-50/50'
                    }
                  >
                    <td className="px-4 py-3 whitespace-nowrap">
                      <span
                        className={`text-sm font-medium ${isWeekend ? 'text-gray-500' : 'text-gray-900'}`}
                      >
                        {label}
                      </span>
                      {isWeekend && (
                        <span className="ml-2 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-500">
                          Weekend
                        </span>
                      )}
                    </td>
                    <td className="px-4 py-3 text-center">
                      <input
                        type="checkbox"
                        id={amKey}
                        checked={attendanceData[amKey] as boolean}
                        onChange={handleCheckboxChange(amKey)}
                        disabled={disabled}
                        className="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed"
                        aria-label={`${label} AM`}
                      />
                    </td>
                    <td className="px-4 py-3 text-center">
                      <input
                        type="checkbox"
                        id={pmKey}
                        checked={attendanceData[pmKey] as boolean}
                        onChange={handleCheckboxChange(pmKey)}
                        disabled={disabled}
                        className="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500 disabled:cursor-not-allowed"
                        aria-label={`${label} PM`}
                      />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>

        {/* Schedule Summary */}
        <div className="mt-4 flex flex-wrap gap-4 text-sm">
          <div className="flex items-center space-x-2">
            <span className="text-gray-500">Scheduled Days:</span>
            <span className="font-medium text-gray-900">
              {scheduleStats.scheduledDays}
            </span>
          </div>
          <div className="flex items-center space-x-2">
            <span className="text-gray-500">Total Periods:</span>
            <span className="font-medium text-gray-900">
              {scheduleStats.scheduledPeriods}
            </span>
          </div>
          {scheduleStats.isFullTime && (
            <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
              Full-Time
            </span>
          )}
          {scheduleStats.hasWeekendAttendance && (
            <span className="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
              Weekend Care
            </span>
          )}
        </div>
      </div>

      {/* ================================================================== */}
      {/* Expected Times */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Expected Times
        </h4>

        <div className="grid grid-cols-1 gap-6 sm:grid-cols-3">
          {/* Expected Arrival Time */}
          <div>
            <label
              htmlFor="expectedArrivalTime"
              className="block text-sm font-medium text-gray-700"
            >
              Expected Arrival Time
            </label>
            <input
              type="time"
              id="expectedArrivalTime"
              value={attendanceData.expectedArrivalTime ?? ''}
              onChange={handleInputChange('expectedArrivalTime')}
              disabled={disabled}
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
            <p className="mt-1 text-xs text-gray-500">
              Typical drop-off time each day.
            </p>
          </div>

          {/* Expected Departure Time */}
          <div>
            <label
              htmlFor="expectedDepartureTime"
              className="block text-sm font-medium text-gray-700"
            >
              Expected Departure Time
            </label>
            <input
              type="time"
              id="expectedDepartureTime"
              value={attendanceData.expectedDepartureTime ?? ''}
              onChange={handleInputChange('expectedDepartureTime')}
              disabled={disabled}
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
            <p className="mt-1 text-xs text-gray-500">
              Typical pick-up time each day.
            </p>
          </div>

          {/* Expected Hours Per Week */}
          <div>
            <label
              htmlFor="expectedHoursPerWeek"
              className="block text-sm font-medium text-gray-700"
            >
              Expected Hours Per Week
            </label>
            <input
              type="number"
              id="expectedHoursPerWeek"
              value={attendanceData.expectedHoursPerWeek ?? ''}
              onChange={handleNumberChange('expectedHoursPerWeek')}
              disabled={disabled}
              min={0}
              max={60}
              step={0.5}
              placeholder="e.g., 40"
              className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
            />
            <p className="mt-1 text-xs text-gray-500">
              Total weekly hours expected.
            </p>
          </div>
        </div>
      </div>

      {/* ================================================================== */}
      {/* Additional Notes */}
      {/* ================================================================== */}
      <div className="border-t border-gray-200 pt-6">
        <h4 className="text-base font-medium text-gray-900 mb-4">
          Additional Notes
        </h4>

        <div>
          <label
            htmlFor="attendanceNotes"
            className="block text-sm font-medium text-gray-700"
          >
            Schedule Notes
          </label>
          <textarea
            id="attendanceNotes"
            rows={3}
            value={attendanceData.notes ?? ''}
            onChange={handleInputChange('notes')}
            disabled={disabled}
            placeholder="Any additional information about the child's schedule (e.g., alternating weeks, early pickup on certain days, flexible schedule)..."
            className="mt-1 block w-full rounded-lg border border-gray-300 px-4 py-2.5 text-gray-900 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:ring-primary-500 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-500"
          />
          <p className="mt-1 text-xs text-gray-500">
            Include any schedule variations or special arrangements.
          </p>
        </div>
      </div>

      {/* ================================================================== */}
      {/* Info Notices */}
      {/* ================================================================== */}
      <div className="rounded-lg bg-blue-50 p-4">
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
            <p className="text-sm text-blue-700">
              <strong>Scheduling:</strong> Please notify us at least 24 hours in
              advance if your child will be absent or if there are any changes
              to the regular schedule. This helps us plan activities and meals
              appropriately.
            </p>
          </div>
        </div>
      </div>

      {/* Weekend Care Notice */}
      {scheduleStats.hasWeekendAttendance && (
        <div className="rounded-lg bg-amber-50 p-4">
          <div className="flex">
            <div className="flex-shrink-0">
              <svg
                className="h-5 w-5 text-amber-400"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                  clipRule="evenodd"
                />
              </svg>
            </div>
            <div className="ml-3">
              <p className="text-sm text-amber-700">
                <strong>Weekend Care:</strong> Weekend attendance requires
                additional arrangements. Please confirm weekend availability
                with the center administrator. Additional fees may apply for
                weekend care.
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}

// ============================================================================
// Exports
// ============================================================================

export type { AttendancePatternSectionProps };
