'use client';

import { useState, useEffect, useCallback } from 'react';
import {
  getNotifications,
  markNotificationsAsRead,
  groupNotificationsByDate,
  formatNotificationTime,
  type Notification,
  type NotificationParams,
} from '../lib/notifications/FCMClient';

// ============================================================================
// Types
// ============================================================================

interface NotificationInboxProps {
  /** User ID to fetch notifications for */
  gibbonPersonID: string;
  /** Maximum number of notifications to display per page */
  pageSize?: number;
  /** Whether to auto-refresh notifications */
  autoRefresh?: boolean;
  /** Auto-refresh interval in milliseconds */
  refreshInterval?: number;
  /** Callback when notification is clicked */
  onNotificationClick?: (notification: Notification) => void;
}

// ============================================================================
// Helper Components
// ============================================================================

/**
 * Loading skeleton for notifications.
 */
function NotificationSkeleton() {
  return (
    <div className="animate-pulse">
      {[1, 2, 3].map((i) => (
        <div key={i} className="flex items-start space-x-4 p-4 border-b border-gray-100">
          <div className="flex-shrink-0">
            <div className="h-10 w-10 rounded-full bg-gray-200"></div>
          </div>
          <div className="flex-1 space-y-2">
            <div className="h-4 bg-gray-200 rounded w-3/4"></div>
            <div className="h-3 bg-gray-200 rounded w-full"></div>
            <div className="h-3 bg-gray-200 rounded w-5/6"></div>
          </div>
        </div>
      ))}
    </div>
  );
}

/**
 * Empty state when no notifications.
 */
function EmptyState() {
  return (
    <div className="flex flex-col items-center justify-center py-12 px-4">
      <svg
        className="h-16 w-16 text-gray-300 mb-4"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
        />
      </svg>
      <h3 className="text-lg font-medium text-gray-900 mb-1">No notifications</h3>
      <p className="text-sm text-gray-500">
        You're all caught up! New notifications will appear here.
      </p>
    </div>
  );
}

/**
 * Error state component.
 */
function ErrorState({ message, onRetry }: { message: string; onRetry: () => void }) {
  return (
    <div className="flex flex-col items-center justify-center py-12 px-4">
      <svg
        className="h-16 w-16 text-red-300 mb-4"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
        />
      </svg>
      <h3 className="text-lg font-medium text-gray-900 mb-1">Failed to load</h3>
      <p className="text-sm text-gray-500 mb-4">{message}</p>
      <button onClick={onRetry} className="btn btn-primary text-sm">
        Try Again
      </button>
    </div>
  );
}

/**
 * Get icon for notification type.
 */
function getNotificationTypeIcon(type: string): React.ReactNode {
  const iconClass = "h-6 w-6";

  switch (type) {
    case 'checkIn':
      return (
        <svg className={`${iconClass} text-green-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
        </svg>
      );
    case 'checkOut':
      return (
        <svg className={`${iconClass} text-orange-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
        </svg>
      );
    case 'photo':
      return (
        <svg className={`${iconClass} text-purple-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
        </svg>
      );
    case 'incident':
      return (
        <svg className={`${iconClass} text-red-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
        </svg>
      );
    case 'meal':
      return (
        <svg className={`${iconClass} text-yellow-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      );
    case 'nap':
      return (
        <svg className={`${iconClass} text-indigo-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
        </svg>
      );
    case 'message':
      return (
        <svg className={`${iconClass} text-blue-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      );
    case 'dailyReport':
      return (
        <svg className={`${iconClass} text-teal-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
      );
    default:
      return (
        <svg className={`${iconClass} text-gray-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>
      );
  }
}

/**
 * Individual notification item.
 */
function NotificationItem({
  notification,
  onMarkRead,
  onClick,
}: {
  notification: Notification;
  onMarkRead: (id: string) => void;
  onClick?: (notification: Notification) => void;
}) {
  const handleClick = () => {
    if (!notification.read) {
      onMarkRead(notification.id);
    }
    onClick?.(notification);
  };

  return (
    <div
      onClick={handleClick}
      className={`flex items-start space-x-4 p-4 border-b border-gray-100 cursor-pointer transition-colors hover:bg-gray-50 ${
        !notification.read ? 'bg-blue-50' : ''
      }`}
    >
      {/* Icon */}
      <div className="flex-shrink-0">
        <div className={`flex h-10 w-10 items-center justify-center rounded-full ${
          !notification.read ? 'bg-white' : 'bg-gray-100'
        }`}>
          {getNotificationTypeIcon(notification.type)}
        </div>
      </div>

      {/* Content */}
      <div className="flex-1 min-w-0">
        <div className="flex items-start justify-between">
          <div className="flex-1 min-w-0">
            <p className={`text-sm font-medium text-gray-900 ${
              !notification.read ? 'font-semibold' : ''
            }`}>
              {notification.title}
            </p>
            <p className="mt-1 text-sm text-gray-600 line-clamp-2">
              {notification.body}
            </p>
          </div>
          {!notification.read && (
            <span className="ml-2 flex-shrink-0">
              <span className="flex h-2 w-2 rounded-full bg-primary-600"></span>
            </span>
          )}
        </div>
        <div className="mt-2 flex items-center space-x-4">
          <span className="text-xs text-gray-500">
            {formatNotificationTime(notification.createdAt)}
          </span>
          {notification.status === 'failed' && (
            <span className="badge badge-error text-xs">Failed</span>
          )}
        </div>
      </div>
    </div>
  );
}

// ============================================================================
// Main Component
// ============================================================================

/**
 * Notification inbox component.
 *
 * Displays a list of notifications with infinite scroll, mark as read,
 * and auto-refresh capabilities.
 */
export function NotificationInbox({
  gibbonPersonID,
  pageSize = 20,
  autoRefresh = true,
  refreshInterval = 30000,
  onNotificationClick,
}: NotificationInboxProps) {
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [unreadCount, setUnreadCount] = useState(0);
  const [filter, setFilter] = useState<'all' | 'unread'>('all');

  // Fetch notifications
  const fetchNotifications = useCallback(
    async (append = false) => {
      try {
        if (!append) {
          setLoading(true);
          setError(null);
        }

        const params: NotificationParams = {
          gibbonPersonID,
          limit: pageSize,
          skip: append ? notifications.length : 0,
          unreadOnly: filter === 'unread',
        };

        const response = await getNotifications(params);

        if (append) {
          setNotifications((prev) => [...prev, ...response.items]);
        } else {
          setNotifications(response.items);
        }

        setUnreadCount(response.unreadCount);
        setHasMore(response.items.length === pageSize);
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to load notifications');
      } finally {
        setLoading(false);
      }
    },
    [gibbonPersonID, pageSize, filter, notifications.length]
  );

  // Initial load
  useEffect(() => {
    fetchNotifications();
  }, [filter]);

  // Auto-refresh
  useEffect(() => {
    if (!autoRefresh) return;

    const interval = setInterval(() => {
      fetchNotifications();
    }, refreshInterval);

    return () => clearInterval(interval);
  }, [autoRefresh, refreshInterval, fetchNotifications]);

  // Mark notification as read
  const handleMarkRead = async (notificationId: string) => {
    try {
      await markNotificationsAsRead({
        gibbonPersonID,
        notificationIds: [notificationId],
      });

      setNotifications((prev) =>
        prev.map((n) =>
          n.id === notificationId ? { ...n, read: true, readAt: new Date().toISOString() } : n
        )
      );
      setUnreadCount((prev) => Math.max(0, prev - 1));
    } catch (err) {
      // Silently fail - notification will be marked read on next refresh
    }
  };

  // Mark all as read
  const handleMarkAllRead = async () => {
    const unreadIds = notifications.filter((n) => !n.read).map((n) => n.id);
    if (unreadIds.length === 0) return;

    try {
      await markNotificationsAsRead({
        gibbonPersonID,
        notificationIds: unreadIds,
      });

      setNotifications((prev) =>
        prev.map((n) => ({ ...n, read: true, readAt: new Date().toISOString() }))
      );
      setUnreadCount(0);
    } catch (err) {
      // Silently fail
    }
  };

  // Group notifications by date
  const groupedNotifications = groupNotificationsByDate(notifications);

  return (
    <div className="card">
      {/* Header */}
      <div className="card-header">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100">
              <svg
                className="h-6 w-6 text-primary-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
                />
              </svg>
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900">Notifications</h3>
              {unreadCount > 0 && (
                <p className="text-sm text-gray-600">
                  {unreadCount} unread notification{unreadCount !== 1 ? 's' : ''}
                </p>
              )}
            </div>
          </div>

          {/* Actions */}
          <div className="flex items-center space-x-2">
            {unreadCount > 0 && (
              <button
                onClick={handleMarkAllRead}
                className="btn btn-outline text-sm"
                type="button"
              >
                Mark all read
              </button>
            )}
          </div>
        </div>

        {/* Filter tabs */}
        <div className="mt-4 flex space-x-2 border-b border-gray-200">
          <button
            onClick={() => setFilter('all')}
            className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${
              filter === 'all'
                ? 'border-primary-600 text-primary-600'
                : 'border-transparent text-gray-600 hover:text-gray-900'
            }`}
            type="button"
          >
            All
          </button>
          <button
            onClick={() => setFilter('unread')}
            className={`px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors ${
              filter === 'unread'
                ? 'border-primary-600 text-primary-600'
                : 'border-transparent text-gray-600 hover:text-gray-900'
            }`}
            type="button"
          >
            Unread {unreadCount > 0 && `(${unreadCount})`}
          </button>
        </div>
      </div>

      {/* Body */}
      <div className="card-body p-0">
        {loading && <NotificationSkeleton />}

        {!loading && error && (
          <ErrorState message={error} onRetry={() => fetchNotifications()} />
        )}

        {!loading && !error && notifications.length === 0 && <EmptyState />}

        {!loading && !error && notifications.length > 0 && (
          <div>
            {Array.from(groupedNotifications.entries()).map(([date, items]) => (
              <div key={date}>
                {/* Date header */}
                <div className="bg-gray-50 px-4 py-2 border-b border-gray-200">
                  <h4 className="text-xs font-semibold text-gray-600 uppercase tracking-wide">
                    {date}
                  </h4>
                </div>

                {/* Notifications */}
                {items.map((notification) => (
                  <NotificationItem
                    key={notification.id}
                    notification={notification}
                    onMarkRead={handleMarkRead}
                    onClick={onNotificationClick}
                  />
                ))}
              </div>
            ))}

            {/* Load more */}
            {hasMore && (
              <div className="p-4 text-center border-t border-gray-100">
                <button
                  onClick={() => fetchNotifications(true)}
                  className="btn btn-outline text-sm"
                  type="button"
                >
                  Load more
                </button>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
