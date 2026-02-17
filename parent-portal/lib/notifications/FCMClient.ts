/**
 * FCM (Firebase Cloud Messaging) Client for LAYA Parent Portal.
 *
 * Provides methods for:
 * - Fetching notification history
 * - Marking notifications as read
 * - Getting unread count
 * - Registering/unregistering FCM tokens
 */

import { gibbonClient } from '../api';
import type { PaginatedResponse, PaginationParams } from '../types';

// ============================================================================
// Types
// ============================================================================

/**
 * Notification status in queue.
 */
export type NotificationStatus = 'pending' | 'processing' | 'sent' | 'failed';

/**
 * Notification delivery channel.
 */
export type NotificationChannel = 'email' | 'push' | 'both';

/**
 * Device type for FCM token.
 */
export type DeviceType = 'ios' | 'android' | 'web';

/**
 * Individual notification in inbox.
 */
export interface Notification {
  id: string;
  type: string;
  title: string;
  body: string;
  data?: Record<string, unknown>;
  channel: NotificationChannel;
  status: NotificationStatus;
  attempts: number;
  sentAt?: string;
  read: boolean;
  readAt?: string;
  createdAt: string;
}

/**
 * Paginated notification response.
 */
export interface NotificationListResponse extends PaginatedResponse<Notification> {
  unreadCount: number;
}

/**
 * FCM device token registration.
 */
export interface FCMToken {
  tokenID: string;
  deviceToken: string;
  deviceType: DeviceType;
  deviceName?: string;
  active: boolean;
  lastUsedAt?: string;
  createdAt: string;
}

/**
 * Request to register FCM token.
 */
export interface RegisterTokenRequest {
  gibbonPersonID: string;
  deviceToken: string;
  deviceType: DeviceType;
  deviceName?: string;
}

/**
 * Request to unregister FCM token.
 */
export interface UnregisterTokenRequest {
  gibbonPersonID: string;
  deviceToken: string;
}

/**
 * Request to mark notifications as read.
 */
export interface MarkReadRequest {
  gibbonPersonID: string;
  notificationIds: string[];
}

/**
 * Response from mark read operation.
 */
export interface MarkReadResponse {
  success: boolean;
  markedCount: number;
  unreadCount: number;
}

// ============================================================================
// API Endpoints
// ============================================================================

const ENDPOINTS = {
  NOTIFICATIONS_LIST: '/modules/NotificationEngine/api/notifications_list.php',
  NOTIFICATIONS_MARK_READ: '/modules/NotificationEngine/api/notifications_mark_read.php',
  FCM_TOKEN_REGISTER: '/modules/NotificationEngine/api/fcm_token_register.php',
  FCM_TOKEN_UNREGISTER: '/modules/NotificationEngine/api/fcm_token_unregister.php',
  FCM_TOKEN_LIST: '/modules/NotificationEngine/api/fcm_token_list.php',
} as const;

// ============================================================================
// Notification API
// ============================================================================

/**
 * Parameters for fetching notifications.
 */
export interface NotificationParams extends PaginationParams {
  gibbonPersonID: string;
  status?: NotificationStatus;
  type?: string;
  unreadOnly?: boolean;
}

/**
 * Fetch notifications for a user.
 */
export async function getNotifications(
  params: NotificationParams
): Promise<NotificationListResponse> {
  return gibbonClient.get<NotificationListResponse>(ENDPOINTS.NOTIFICATIONS_LIST, {
    params: {
      gibbonPersonID: params.gibbonPersonID,
      skip: params.skip,
      limit: params.limit,
      status: params.status,
      type: params.type,
      unread_only: params.unreadOnly,
    },
  });
}

/**
 * Mark one or more notifications as read.
 */
export async function markNotificationsAsRead(
  request: MarkReadRequest
): Promise<MarkReadResponse> {
  return gibbonClient.post<MarkReadResponse>(
    ENDPOINTS.NOTIFICATIONS_MARK_READ,
    request
  );
}

/**
 * Get total unread notification count for a user.
 */
export async function getUnreadCount(gibbonPersonID: string): Promise<number> {
  const response = await getNotifications({
    gibbonPersonID,
    limit: 1,
    unreadOnly: true,
  });
  return response.unreadCount;
}

// ============================================================================
// FCM Token API
// ============================================================================

/**
 * Register or update an FCM device token.
 */
export async function registerFCMToken(
  request: RegisterTokenRequest
): Promise<FCMToken> {
  return gibbonClient.post<FCMToken>(ENDPOINTS.FCM_TOKEN_REGISTER, request);
}

/**
 * Unregister (deactivate) an FCM device token.
 */
export async function unregisterFCMToken(
  request: UnregisterTokenRequest
): Promise<{ success: boolean }> {
  return gibbonClient.post<{ success: boolean }>(
    ENDPOINTS.FCM_TOKEN_UNREGISTER,
    request
  );
}

/**
 * FCM token list API response envelope.
 */
interface FCMTokenListResponse {
  success: boolean;
  message: string;
  data: {
    tokens: FCMToken[];
    count: number;
  };
}

/**
 * List all active FCM tokens for a user.
 */
export async function listFCMTokens(gibbonPersonID: string): Promise<FCMToken[]> {
  const response = await gibbonClient.get<FCMTokenListResponse>(
    ENDPOINTS.FCM_TOKEN_LIST,
    {
      params: { gibbonPersonID },
    }
  );
  return response.data?.tokens ?? [];
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Group notifications by date.
 */
export function groupNotificationsByDate(
  notifications: Notification[]
): Map<string, Notification[]> {
  const groups = new Map<string, Notification[]>();

  notifications.forEach((notification) => {
    const date = new Date(notification.createdAt);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    let groupKey: string;
    if (date.toDateString() === today.toDateString()) {
      groupKey = 'Today';
    } else if (date.toDateString() === yesterday.toDateString()) {
      groupKey = 'Yesterday';
    } else {
      groupKey = date.toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
      });
    }

    if (!groups.has(groupKey)) {
      groups.set(groupKey, []);
    }
    groups.get(groupKey)!.push(notification);
  });

  return groups;
}

/**
 * Format notification time.
 */
export function formatNotificationTime(timestamp: string): string {
  const date = new Date(timestamp);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) {
    return 'Just now';
  } else if (diffMins < 60) {
    return `${diffMins}m ago`;
  } else if (diffHours < 24) {
    return `${diffHours}h ago`;
  } else if (diffDays < 7) {
    return `${diffDays}d ago`;
  } else {
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
    });
  }
}

/**
 * Get icon name for notification type.
 * Handles both simple types (e.g., 'checkIn') and dotted types (e.g., 'attendance.checkIn').
 */
export function getNotificationIcon(type: string): string {
  const iconMap: Record<string, string> = {
    // Simple type keys
    checkIn: 'login',
    checkOut: 'logout',
    photo: 'photo',
    incident: 'alert',
    meal: 'restaurant',
    nap: 'bedtime',
    announcement: 'campaign',
    message: 'mail',
    dailyReport: 'assignment',
    diaper: 'baby_changing_station',
    // Dotted type keys (from EventNotificationMapper)
    'attendance.checkIn': 'login',
    'attendance.checkOut': 'logout',
    'photo.uploaded': 'photo',
    'incident.created': 'alert',
    'incident.updated': 'alert',
    'meal.recorded': 'restaurant',
    'nap.recorded': 'bedtime',
    'message.received': 'mail',
    'dailyReport.ready': 'assignment',
    'diaper.recorded': 'baby_changing_station',
  };
  return iconMap[type] || 'notifications';
}
