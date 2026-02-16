/**
 * Type-safe Director Dashboard API client for LAYA Parent Portal.
 *
 * Provides methods for interacting with the director dashboard backend
 * for real-time occupancy monitoring, group statistics, and alerts.
 */

import { aiServiceClient, ApiError } from './api';
import type {
  AlertItem,
  DirectorDashboard,
  GroupOccupancy,
  OccupancyHistoryResponse,
  OccupancySummary,
} from './types';

// ============================================================================
// API Endpoints
// ============================================================================

const ENDPOINTS = {
  // Director Dashboard
  DASHBOARD: '/api/v1/director/dashboard',
  OCCUPANCY: '/api/v1/director/occupancy',
  OCCUPANCY_HISTORY: '/api/v1/director/occupancy/history',
} as const;

// ============================================================================
// Request Parameters
// ============================================================================

/**
 * Parameters for fetching occupancy history.
 */
export interface OccupancyHistoryParams {
  /** Number of hours to look back for history data (default: 24) */
  hours?: number;
  /** Time interval between data points in minutes (default: 30) */
  intervalMinutes?: number;
}

/**
 * Parameters for fetching current occupancy.
 */
export interface OccupancyParams {
  /** Filter by specific group ID */
  groupId?: string;
  /** Include only groups at or near capacity */
  atCapacityOnly?: boolean;
}

// ============================================================================
// Director Dashboard API
// ============================================================================

/**
 * Fetch the complete director dashboard with real-time occupancy,
 * group details, and active alerts.
 *
 * @returns Complete director dashboard data including summary, groups, and alerts
 */
export async function getDirectorDashboard(): Promise<DirectorDashboard> {
  return aiServiceClient.get<DirectorDashboard>(ENDPOINTS.DASHBOARD);
}

/**
 * Fetch current occupancy data by group.
 *
 * @param params - Optional filters for occupancy data
 * @returns Array of group occupancy records
 */
export async function getOccupancy(
  params?: OccupancyParams
): Promise<GroupOccupancy[]> {
  return aiServiceClient.get<GroupOccupancy[]>(ENDPOINTS.OCCUPANCY, {
    params: {
      group_id: params?.groupId,
      at_capacity_only: params?.atCapacityOnly,
    },
  });
}

/**
 * Fetch occupancy history for trend analysis and visualization.
 *
 * @param params - Parameters controlling the history period and interval
 * @returns Historical occupancy data points for charting
 */
export async function getOccupancyHistory(
  params?: OccupancyHistoryParams
): Promise<OccupancyHistoryResponse> {
  return aiServiceClient.get<OccupancyHistoryResponse>(ENDPOINTS.OCCUPANCY_HISTORY, {
    params: {
      hours: params?.hours,
      interval_minutes: params?.intervalMinutes,
    },
  });
}

// ============================================================================
// Convenience Methods
// ============================================================================

/**
 * Fetch only the occupancy summary without full dashboard data.
 * Useful for quick status checks or compact views.
 *
 * @returns Facility-wide occupancy summary
 */
export async function getOccupancySummary(): Promise<OccupancySummary> {
  const dashboard = await getDirectorDashboard();
  return dashboard.summary;
}

/**
 * Fetch only active alerts from the dashboard.
 * Useful for notification displays or alert widgets.
 *
 * @returns Array of active alert items
 */
export async function getActiveAlerts(): Promise<AlertItem[]> {
  const dashboard = await getDirectorDashboard();
  return dashboard.alerts;
}

/**
 * Fetch alerts filtered by priority level.
 *
 * @param priority - Minimum priority level to include
 * @returns Array of alerts at or above the specified priority
 */
export async function getAlertsByPriority(
  priority: 'low' | 'medium' | 'high' | 'critical'
): Promise<AlertItem[]> {
  const priorityLevels = ['low', 'medium', 'high', 'critical'];
  const minIndex = priorityLevels.indexOf(priority);

  const dashboard = await getDirectorDashboard();
  return dashboard.alerts.filter(
    (alert) => priorityLevels.indexOf(alert.priority) >= minIndex
  );
}

/**
 * Fetch groups that are at or near capacity.
 * Useful for capacity management and highlighting potential issues.
 *
 * @returns Array of groups that need attention due to capacity
 */
export async function getCapacityAlertGroups(): Promise<GroupOccupancy[]> {
  const dashboard = await getDirectorDashboard();
  return dashboard.groups.filter(
    (group) =>
      group.status === 'at_capacity' ||
      group.status === 'near_capacity' ||
      group.status === 'over_capacity'
  );
}

// ============================================================================
// Real-time Polling Support
// ============================================================================

/**
 * Options for dashboard polling.
 */
export interface PollingOptions {
  /** Polling interval in milliseconds (default: 30000) */
  intervalMs?: number;
  /** Callback when dashboard data is updated */
  onUpdate: (dashboard: DirectorDashboard) => void;
  /** Callback when an error occurs */
  onError?: (error: Error) => void;
}

/**
 * Start polling the director dashboard for real-time updates.
 * Returns a stop function to cancel polling.
 *
 * @param options - Polling configuration including callbacks
 * @returns Function to stop polling
 *
 * @example
 * ```typescript
 * const stopPolling = startDashboardPolling({
 *   intervalMs: 15000, // 15 seconds
 *   onUpdate: (dashboard) => setDashboard(dashboard),
 *   onError: (error) => console.error('Poll failed:', error),
 * });
 *
 * // Later, to stop:
 * stopPolling();
 * ```
 */
export function startDashboardPolling(options: PollingOptions): () => void {
  const { intervalMs = 30000, onUpdate, onError } = options;
  let isActive = true;
  let timeoutId: ReturnType<typeof setTimeout> | null = null;

  const poll = async () => {
    if (!isActive) return;

    try {
      const dashboard = await getDirectorDashboard();
      if (isActive) {
        onUpdate(dashboard);
      }
    } catch (error) {
      if (isActive && onError) {
        onError(error instanceof Error ? error : new Error(String(error)));
      }
    }

    if (isActive) {
      timeoutId = setTimeout(poll, intervalMs);
    }
  };

  // Start initial poll
  poll();

  // Return stop function
  return () => {
    isActive = false;
    if (timeoutId !== null) {
      clearTimeout(timeoutId);
    }
  };
}

// ============================================================================
// Error Handling Helpers
// ============================================================================

/**
 * Check if an error is an API error.
 */
export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}

/**
 * Get user-friendly error message for director dashboard operations.
 */
export function getErrorMessage(error: unknown): string {
  if (isApiError(error)) {
    return error.userMessage;
  }
  if (error instanceof Error) {
    return error.message;
  }
  return 'An unexpected error occurred while loading dashboard data.';
}

/**
 * Wrap a dashboard operation with fallback behavior.
 *
 * @param operation - The async operation to execute
 * @param fallback - Value to return if operation fails with server error
 * @returns Result of operation or fallback value
 */
export async function withFallback<T>(
  operation: () => Promise<T>,
  fallback: T
): Promise<T> {
  try {
    return await operation();
  } catch (error) {
    if (isApiError(error) && error.isServerError) {
      return fallback;
    }
    throw error;
  }
}
