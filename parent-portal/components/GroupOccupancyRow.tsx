'use client';

import type { GroupOccupancy, OccupancyStatus, AgeGroupType } from '../lib/types';

export interface GroupOccupancyRowProps {
  group: GroupOccupancy;
  showStaffInfo?: boolean;
  showRoomNumber?: boolean;
}

/**
 * Get display-friendly label for age group type.
 * Based on Quebec childcare classifications.
 */
function getAgeGroupLabel(ageGroup: AgeGroupType): string {
  switch (ageGroup) {
    case 'poupon':
      return 'Poupon (0-18m)';
    case 'bambin':
      return 'Bambin (18-36m)';
    case 'prescolaire':
      return 'Préscolaire (3-4y)';
    case 'scolaire':
      return 'Scolaire (5+y)';
    case 'mixed':
      return 'Mixed Ages';
    default:
      return ageGroup;
  }
}

/**
 * Get status color configuration based on occupancy status.
 */
function getStatusColors(status: OccupancyStatus): {
  bg: string;
  fill: string;
  text: string;
  badge: string;
  dot: string;
} {
  switch (status) {
    case 'over_capacity':
      return {
        bg: 'bg-red-100',
        fill: 'bg-red-500',
        text: 'text-red-700',
        badge: 'bg-red-100 text-red-800',
        dot: 'bg-red-500',
      };
    case 'at_capacity':
      return {
        bg: 'bg-orange-100',
        fill: 'bg-orange-500',
        text: 'text-orange-700',
        badge: 'bg-orange-100 text-orange-800',
        dot: 'bg-orange-500',
      };
    case 'near_capacity':
      return {
        bg: 'bg-yellow-100',
        fill: 'bg-yellow-500',
        text: 'text-yellow-700',
        badge: 'bg-yellow-100 text-yellow-800',
        dot: 'bg-yellow-500',
      };
    case 'empty':
      return {
        bg: 'bg-gray-100',
        fill: 'bg-gray-400',
        text: 'text-gray-500',
        badge: 'bg-gray-100 text-gray-600',
        dot: 'bg-gray-400',
      };
    case 'normal':
    default:
      return {
        bg: 'bg-green-100',
        fill: 'bg-green-500',
        text: 'text-green-700',
        badge: 'bg-green-100 text-green-800',
        dot: 'bg-green-500',
      };
  }
}

/**
 * Get user-friendly status label.
 */
function getStatusLabel(status: OccupancyStatus): string {
  switch (status) {
    case 'over_capacity':
      return 'Over Capacity';
    case 'at_capacity':
      return 'At Capacity';
    case 'near_capacity':
      return 'Near Capacity';
    case 'empty':
      return 'Empty';
    case 'normal':
    default:
      return 'Available';
  }
}

/**
 * Format time for display.
 */
function formatTime(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

/**
 * Calculate occupancy percentage, capped at 100.
 */
function getOccupancyPercentage(current: number, capacity: number): number {
  if (capacity === 0) return 0;
  return Math.min(Math.round((current / capacity) * 100), 100);
}

/**
 * GroupOccupancyRow component displays individual group occupancy data
 * in a row format with progress bar, status indicators, and optional
 * staffing information.
 */
export function GroupOccupancyRow({
  group,
  showStaffInfo = true,
  showRoomNumber = true,
}: GroupOccupancyRowProps) {
  const colors = getStatusColors(group.status);
  const statusLabel = getStatusLabel(group.status);
  const ageGroupLabel = getAgeGroupLabel(group.ageGroup);
  const percentage = getOccupancyPercentage(group.currentCount, group.capacity);

  return (
    <div className="flex flex-col sm:flex-row sm:items-center gap-3 p-4 bg-white rounded-lg border border-gray-200 hover:border-gray-300 transition-colors">
      {/* Group Info Section */}
      <div className="flex items-center space-x-3 sm:w-1/4">
        {/* Status Indicator Dot */}
        <div className="flex-shrink-0">
          <span className={`inline-block h-3 w-3 rounded-full ${colors.dot}`} />
        </div>

        {/* Group Name and Details */}
        <div className="min-w-0 flex-1">
          <div className="flex items-center space-x-2">
            <h4 className="text-sm font-medium text-gray-900 truncate">
              {group.groupName}
            </h4>
            {showRoomNumber && group.roomNumber && (
              <span className="text-xs text-gray-400">
                Room {group.roomNumber}
              </span>
            )}
          </div>
          <p className="text-xs text-gray-500">{ageGroupLabel}</p>
        </div>
      </div>

      {/* Occupancy Progress Section */}
      <div className="flex-1 sm:px-4">
        <div className="flex items-center space-x-3">
          {/* Progress Bar */}
          <div className="flex-1">
            <div className={`h-2.5 w-full rounded-full ${colors.bg} overflow-hidden`}>
              <div
                className={`h-full rounded-full transition-all duration-300 ${colors.fill}`}
                style={{ width: `${percentage}%` }}
                role="progressbar"
                aria-valuenow={group.currentCount}
                aria-valuemin={0}
                aria-valuemax={group.capacity}
                aria-label={`${group.groupName} occupancy: ${group.currentCount} of ${group.capacity}`}
              />
            </div>
          </div>

          {/* Count/Capacity Display */}
          <div className="flex-shrink-0 text-right">
            <span className="text-sm font-semibold text-gray-900">
              {group.currentCount}
            </span>
            <span className="text-sm text-gray-400 mx-0.5">/</span>
            <span className="text-sm text-gray-500">{group.capacity}</span>
          </div>
        </div>

        {/* Percentage and Staff Info Row */}
        <div className="flex items-center justify-between mt-1.5">
          <div className="flex items-center space-x-3">
            {/* Percentage */}
            <span className={`text-xs font-medium ${colors.text}`}>
              {percentage}%
            </span>

            {/* Staff Information */}
            {showStaffInfo && group.staffCount !== undefined && group.staffCount > 0 && (
              <div className="flex items-center text-xs text-gray-500">
                <svg
                  className="mr-1 h-3 w-3"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                  />
                </svg>
                <span>
                  {group.staffCount} staff
                  {group.staffRatio && ` (${group.staffRatio})`}
                </span>
              </div>
            )}
          </div>

          {/* Last Updated Time */}
          <span className="text-xs text-gray-400">
            {formatTime(group.lastUpdated)}
          </span>
        </div>
      </div>

      {/* Status Badge Section */}
      <div className="flex-shrink-0 sm:w-auto">
        <span
          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${colors.badge}`}
        >
          {group.status === 'over_capacity' && (
            <svg
              className="mr-1 h-3 w-3"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fillRule="evenodd"
                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                clipRule="evenodd"
              />
            </svg>
          )}
          {statusLabel}
        </span>
      </div>
    </div>
  );
}

/**
 * GroupOccupancyRowCompact - A more compact version for dense layouts.
 */
export function GroupOccupancyRowCompact({
  group,
}: {
  group: GroupOccupancy;
}) {
  const colors = getStatusColors(group.status);
  const percentage = getOccupancyPercentage(group.currentCount, group.capacity);

  return (
    <div className="flex items-center space-x-3 py-2">
      {/* Status Dot */}
      <span className={`flex-shrink-0 h-2 w-2 rounded-full ${colors.dot}`} />

      {/* Group Name */}
      <span className="flex-1 text-sm font-medium text-gray-700 truncate">
        {group.groupName}
      </span>

      {/* Progress Bar */}
      <div className="w-24 flex-shrink-0">
        <div className={`h-1.5 w-full rounded-full ${colors.bg} overflow-hidden`}>
          <div
            className={`h-full rounded-full ${colors.fill}`}
            style={{ width: `${percentage}%` }}
          />
        </div>
      </div>

      {/* Count */}
      <span className={`flex-shrink-0 text-xs font-medium ${colors.text} w-12 text-right`}>
        {group.currentCount}/{group.capacity}
      </span>
    </div>
  );
}

/**
 * GroupOccupancyList - Container component for rendering multiple group rows.
 */
export function GroupOccupancyList({
  groups,
  showStaffInfo = true,
  showRoomNumber = true,
  emptyMessage = 'No groups available',
}: {
  groups: GroupOccupancy[];
  showStaffInfo?: boolean;
  showRoomNumber?: boolean;
  emptyMessage?: string;
}) {
  if (groups.length === 0) {
    return (
      <div className="text-center py-8">
        <svg
          className="mx-auto h-12 w-12 text-gray-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
          />
        </svg>
        <p className="mt-2 text-sm text-gray-500">{emptyMessage}</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      {groups.map((group) => (
        <GroupOccupancyRow
          key={group.groupId}
          group={group}
          showStaffInfo={showStaffInfo}
          showRoomNumber={showRoomNumber}
        />
      ))}
    </div>
  );
}
