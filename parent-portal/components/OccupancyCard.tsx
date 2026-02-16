'use client';

interface GroupOccupancy {
  id: string;
  name: string;
  currentCount: number;
  capacity: number;
}

export interface OccupancyCardProps {
  occupancy: {
    facilityName: string;
    totalCurrent: number;
    totalCapacity: number;
    groups: GroupOccupancy[];
    lastUpdated: string;
  };
}

function formatTime(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

function getOccupancyPercentage(current: number, capacity: number): number {
  if (capacity === 0) return 0;
  return Math.min(Math.round((current / capacity) * 100), 100);
}

function getOccupancyStatus(percentage: number): 'low' | 'medium' | 'high' | 'full' {
  if (percentage >= 100) return 'full';
  if (percentage >= 85) return 'high';
  if (percentage >= 50) return 'medium';
  return 'low';
}

function getStatusColor(status: 'low' | 'medium' | 'high' | 'full'): {
  bg: string;
  fill: string;
  text: string;
  badge: string;
} {
  switch (status) {
    case 'full':
      return {
        bg: 'bg-red-100',
        fill: 'bg-red-500',
        text: 'text-red-700',
        badge: 'badge-error',
      };
    case 'high':
      return {
        bg: 'bg-yellow-100',
        fill: 'bg-yellow-500',
        text: 'text-yellow-700',
        badge: 'badge-warning',
      };
    case 'medium':
      return {
        bg: 'bg-blue-100',
        fill: 'bg-blue-500',
        text: 'text-blue-700',
        badge: 'badge-info',
      };
    case 'low':
    default:
      return {
        bg: 'bg-green-100',
        fill: 'bg-green-500',
        text: 'text-green-700',
        badge: 'badge-success',
      };
  }
}

function getStatusLabel(status: 'low' | 'medium' | 'high' | 'full'): string {
  switch (status) {
    case 'full':
      return 'At Capacity';
    case 'high':
      return 'Nearly Full';
    case 'medium':
      return 'Moderate';
    case 'low':
    default:
      return 'Available';
  }
}

function OccupancyProgressBar({
  current,
  capacity,
  showLabel = true,
}: {
  current: number;
  capacity: number;
  showLabel?: boolean;
}) {
  const percentage = getOccupancyPercentage(current, capacity);
  const status = getOccupancyStatus(percentage);
  const colors = getStatusColor(status);

  return (
    <div className="w-full">
      <div className={`h-3 w-full rounded-full ${colors.bg} overflow-hidden`}>
        <div
          className={`h-full rounded-full transition-all duration-300 ${colors.fill}`}
          style={{ width: `${percentage}%` }}
        />
      </div>
      {showLabel && (
        <div className="flex justify-between mt-1 text-xs text-gray-500">
          <span>{current} / {capacity}</span>
          <span>{percentage}%</span>
        </div>
      )}
    </div>
  );
}

export function OccupancyCard({ occupancy }: OccupancyCardProps) {
  const totalPercentage = getOccupancyPercentage(
    occupancy.totalCurrent,
    occupancy.totalCapacity
  );
  const overallStatus = getOccupancyStatus(totalPercentage);
  const statusColors = getStatusColor(overallStatus);
  const statusLabel = getStatusLabel(overallStatus);

  return (
    <div className="card">
      {/* Occupancy Header */}
      <div className="card-header">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-100">
              <svg
                className="h-6 w-6 text-indigo-600"
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
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900">
                Current Occupancy
              </h3>
              <p className="text-sm text-gray-600">{occupancy.facilityName}</p>
            </div>
          </div>
          <div className="flex items-center space-x-3">
            <span className={`badge ${statusColors.badge} inline-flex items-center`}>
              {overallStatus === 'full' && (
                <svg className="mr-1 h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
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
      </div>

      <div className="card-body">
        {/* Total Occupancy Overview */}
        <div className="mb-6">
          <div className="flex items-center justify-between mb-2">
            <p className="text-sm text-gray-500">Total Facility Occupancy</p>
            <p className={`text-sm font-medium ${statusColors.text}`}>
              {totalPercentage}% Full
            </p>
          </div>
          <div className="flex items-center space-x-4">
            <div className="flex-1">
              <OccupancyProgressBar
                current={occupancy.totalCurrent}
                capacity={occupancy.totalCapacity}
                showLabel={false}
              />
            </div>
            <div className="text-right">
              <p className="text-2xl font-bold text-gray-900">
                {occupancy.totalCurrent}
              </p>
              <p className="text-sm text-gray-500">of {occupancy.totalCapacity}</p>
            </div>
          </div>
        </div>

        {/* Group Breakdown */}
        {occupancy.groups.length > 0 && (
          <div className="mb-6">
            <h4 className="font-medium text-gray-900 mb-3">Occupancy by Group</h4>
            <div className="space-y-4">
              {occupancy.groups.map((group) => {
                const groupPercentage = getOccupancyPercentage(
                  group.currentCount,
                  group.capacity
                );
                const groupStatus = getOccupancyStatus(groupPercentage);
                const groupColors = getStatusColor(groupStatus);

                return (
                  <div key={group.id} className="flex items-center space-x-4">
                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between mb-1">
                        <span className="text-sm font-medium text-gray-700 truncate">
                          {group.name}
                        </span>
                        <span className={`text-xs font-medium ${groupColors.text}`}>
                          {group.currentCount}/{group.capacity}
                        </span>
                      </div>
                      <div className={`h-2 w-full rounded-full ${groupColors.bg} overflow-hidden`}>
                        <div
                          className={`h-full rounded-full transition-all duration-300 ${groupColors.fill}`}
                          style={{ width: `${groupPercentage}%` }}
                        />
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        )}

        {/* Last Updated */}
        <div className="flex items-center justify-between pt-4 border-t border-gray-200">
          <div className="flex items-center text-sm text-gray-500">
            <svg
              className="mr-1.5 h-4 w-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            Last updated: {formatTime(occupancy.lastUpdated)}
          </div>
          <div className="flex items-center text-sm text-green-600">
            <span className="relative flex h-2 w-2 mr-2">
              <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
              <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
            </span>
            Live
          </div>
        </div>
      </div>
    </div>
  );
}
