'use client';

import { useState, useEffect, useCallback } from 'react';
import type {
  NotificationType,
  NotificationChannelType,
  NotificationFrequency,
  NotificationPreference,
} from '@/lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * Props for the NotificationSettings component.
 */
interface NotificationSettingsProps {
  /** Parent ID for fetching/saving preferences */
  parentId: string;
  /** Whether the component is in a loading state */
  isLoading?: boolean;
  /** Callback when settings are saved successfully */
  onSave?: () => void;
  /** Callback when an error occurs */
  onError?: (error: string) => void;
}

/**
 * Grouped preference state for UI organization.
 */
interface PreferenceGroup {
  notificationType: NotificationType;
  channels: {
    channel: NotificationChannelType;
    isEnabled: boolean;
    frequency: NotificationFrequency;
    preferenceId?: string;
  }[];
}

// ============================================================================
// Constants
// ============================================================================

/**
 * Notification type labels for display.
 */
const NOTIFICATION_TYPE_LABELS: Record<NotificationType, { label: string; description: string }> = {
  message: {
    label: 'Messages',
    description: 'Notifications when you receive new messages',
  },
  daily_log: {
    label: 'Daily Logs',
    description: 'Updates about your child\'s daily activities',
  },
  urgent: {
    label: 'Urgent Alerts',
    description: 'Important notifications requiring immediate attention',
  },
  admin: {
    label: 'Administrative',
    description: 'Billing, policy updates, and other admin notices',
  },
};

/**
 * Channel type labels for display.
 */
const CHANNEL_LABELS: Record<NotificationChannelType, { label: string; icon: string }> = {
  email: { label: 'Email', icon: 'email' },
  push: { label: 'Push', icon: 'push' },
  sms: { label: 'SMS', icon: 'sms' },
};

/**
 * Frequency options for dropdown.
 */
const FREQUENCY_OPTIONS: { value: NotificationFrequency; label: string }[] = [
  { value: 'immediate', label: 'Immediately' },
  { value: 'hourly', label: 'Hourly digest' },
  { value: 'daily', label: 'Daily digest' },
  { value: 'weekly', label: 'Weekly digest' },
];

/**
 * Default channels for each notification type.
 */
const DEFAULT_CHANNELS: NotificationChannelType[] = ['email', 'push', 'sms'];

/**
 * All notification types.
 */
const ALL_NOTIFICATION_TYPES: NotificationType[] = ['message', 'daily_log', 'urgent', 'admin'];

// ============================================================================
// Component
// ============================================================================

export function NotificationSettings({
  parentId,
  isLoading: externalLoading = false,
  onSave,
  onError,
}: NotificationSettingsProps) {
  // State
  const [preferences, setPreferences] = useState<NotificationPreference[]>([]);
  const [quietHoursStart, setQuietHoursStart] = useState<string>('');
  const [quietHoursEnd, setQuietHoursEnd] = useState<string>('');
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);
  const [hasChanges, setHasChanges] = useState(false);

  // ============================================================================
  // Data Loading
  // ============================================================================

  /**
   * Fetch notification preferences from API.
   */
  const loadPreferences = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const { getNotificationPreferences, createDefaultPreferences } = await import(
        '@/lib/messaging-client'
      );

      let response = await getNotificationPreferences(parentId);

      // If no preferences exist, create defaults
      if (response.preferences.length === 0) {
        response = await createDefaultPreferences(parentId);
      }

      setPreferences(response.preferences);

      // Set quiet hours from first preference that has them
      const prefWithQuietHours = response.preferences.find(
        (p) => p.quietHoursStart && p.quietHoursEnd
      );
      if (prefWithQuietHours) {
        setQuietHoursStart(prefWithQuietHours.quietHoursStart || '');
        setQuietHoursEnd(prefWithQuietHours.quietHoursEnd || '');
      }
    } catch (err) {
      const errorMessage =
        err instanceof Error ? err.message : 'Failed to load notification preferences';
      setError(errorMessage);
      onError?.(errorMessage);
    } finally {
      setIsLoading(false);
    }
  }, [parentId, onError]);

  // Load preferences on mount
  useEffect(() => {
    loadPreferences();
  }, [loadPreferences]);

  // ============================================================================
  // Preference Grouping
  // ============================================================================

  /**
   * Group preferences by notification type for UI display.
   */
  const getPreferenceGroups = useCallback((): PreferenceGroup[] => {
    return ALL_NOTIFICATION_TYPES.map((notificationType) => {
      const channels = DEFAULT_CHANNELS.map((channel) => {
        const pref = preferences.find(
          (p) => p.notificationType === notificationType && p.channel === channel
        );
        return {
          channel,
          isEnabled: pref?.isEnabled ?? true,
          frequency: pref?.frequency ?? 'immediate',
          preferenceId: pref?.id,
        };
      });

      return {
        notificationType,
        channels,
      };
    });
  }, [preferences]);

  // ============================================================================
  // Handlers
  // ============================================================================

  /**
   * Toggle a specific channel for a notification type.
   */
  const handleToggleChannel = async (
    notificationType: NotificationType,
    channel: NotificationChannelType,
    isEnabled: boolean
  ) => {
    setHasChanges(true);
    setError(null);
    setSuccessMessage(null);

    try {
      const { createNotificationPreference, updateNotificationPreference } = await import(
        '@/lib/messaging-client'
      );

      const existingPref = preferences.find(
        (p) => p.notificationType === notificationType && p.channel === channel
      );

      if (existingPref) {
        // Update existing preference
        const updated = await updateNotificationPreference(existingPref.id, {
          isEnabled,
        });
        setPreferences((prev) =>
          prev.map((p) => (p.id === existingPref.id ? updated : p))
        );
      } else {
        // Create new preference
        const created = await createNotificationPreference({
          parentId,
          notificationType,
          channel,
          isEnabled,
          frequency: 'immediate',
        });
        setPreferences((prev) => [...prev, created]);
      }
    } catch (err) {
      const errorMessage =
        err instanceof Error ? err.message : 'Failed to update preference';
      setError(errorMessage);
      onError?.(errorMessage);
    }
  };

  /**
   * Update frequency for a specific channel.
   */
  const handleFrequencyChange = async (
    notificationType: NotificationType,
    channel: NotificationChannelType,
    frequency: NotificationFrequency
  ) => {
    setHasChanges(true);
    setError(null);
    setSuccessMessage(null);

    try {
      const { createNotificationPreference, updateNotificationPreference } = await import(
        '@/lib/messaging-client'
      );

      const existingPref = preferences.find(
        (p) => p.notificationType === notificationType && p.channel === channel
      );

      if (existingPref) {
        const updated = await updateNotificationPreference(existingPref.id, {
          frequency,
        });
        setPreferences((prev) =>
          prev.map((p) => (p.id === existingPref.id ? updated : p))
        );
      } else {
        const created = await createNotificationPreference({
          parentId,
          notificationType,
          channel,
          isEnabled: true,
          frequency,
        });
        setPreferences((prev) => [...prev, created]);
      }
    } catch (err) {
      const errorMessage =
        err instanceof Error ? err.message : 'Failed to update frequency';
      setError(errorMessage);
      onError?.(errorMessage);
    }
  };

  /**
   * Save quiet hours settings.
   */
  const handleSaveQuietHours = async () => {
    if (!quietHoursStart || !quietHoursEnd) {
      setError('Please select both start and end times for quiet hours');
      return;
    }

    setIsSaving(true);
    setError(null);
    setSuccessMessage(null);

    try {
      const { setQuietHours } = await import('@/lib/messaging-client');

      await setQuietHours({
        parentId,
        quietHoursStart,
        quietHoursEnd,
      });

      setSuccessMessage('Quiet hours saved successfully');
      setHasChanges(false);
      onSave?.();

      // Reload preferences to get updated quiet hours
      await loadPreferences();
    } catch (err) {
      const errorMessage =
        err instanceof Error ? err.message : 'Failed to save quiet hours';
      setError(errorMessage);
      onError?.(errorMessage);
    } finally {
      setIsSaving(false);
    }
  };

  /**
   * Clear quiet hours.
   */
  const handleClearQuietHours = async () => {
    setQuietHoursStart('');
    setQuietHoursEnd('');
    setHasChanges(true);

    try {
      const { setQuietHours } = await import('@/lib/messaging-client');

      await setQuietHours({
        parentId,
        quietHoursStart: '',
        quietHoursEnd: '',
      });

      setSuccessMessage('Quiet hours cleared');
      setHasChanges(false);
      onSave?.();
    } catch (err) {
      const errorMessage =
        err instanceof Error ? err.message : 'Failed to clear quiet hours';
      setError(errorMessage);
      onError?.(errorMessage);
    }
  };

  /**
   * Enable all notifications for a type.
   */
  const handleEnableAllForType = async (notificationType: NotificationType) => {
    setHasChanges(true);
    setError(null);

    for (const channel of DEFAULT_CHANNELS) {
      const pref = preferences.find(
        (p) => p.notificationType === notificationType && p.channel === channel
      );
      if (!pref?.isEnabled) {
        await handleToggleChannel(notificationType, channel, true);
      }
    }
  };

  /**
   * Disable all notifications for a type.
   */
  const handleDisableAllForType = async (notificationType: NotificationType) => {
    setHasChanges(true);
    setError(null);

    for (const channel of DEFAULT_CHANNELS) {
      const pref = preferences.find(
        (p) => p.notificationType === notificationType && p.channel === channel
      );
      if (pref?.isEnabled !== false) {
        await handleToggleChannel(notificationType, channel, false);
      }
    }
  };

  // ============================================================================
  // Render Helpers
  // ============================================================================

  /**
   * Render channel icon.
   */
  const renderChannelIcon = (channel: NotificationChannelType) => {
    switch (channel) {
      case 'email':
        return (
          <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
          </svg>
        );
      case 'push':
        return (
          <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"
            />
          </svg>
        );
      case 'sms':
        return (
          <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"
            />
          </svg>
        );
    }
  };

  // ============================================================================
  // Render
  // ============================================================================

  const loading = isLoading || externalLoading;
  const preferenceGroups = getPreferenceGroups();

  if (loading) {
    return (
      <div className="animate-pulse space-y-4 p-6">
        <div className="h-6 w-48 rounded bg-gray-200" />
        <div className="space-y-3">
          {[1, 2, 3, 4].map((i) => (
            <div key={i} className="h-24 rounded-lg bg-gray-100" />
          ))}
        </div>
      </div>
    );
  }

  return (
    <div className="divide-y divide-gray-200">
      {/* Header */}
      <div className="bg-white px-6 py-4">
        <h2 className="text-lg font-semibold text-gray-900">Notification Settings</h2>
        <p className="mt-1 text-sm text-gray-500">
          Manage how and when you receive notifications about your child&apos;s care.
        </p>
      </div>

      {/* Status messages */}
      {error && (
        <div className="bg-red-50 px-6 py-3">
          <div className="flex items-center space-x-2">
            <svg className="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <span className="text-sm text-red-700">{error}</span>
          </div>
        </div>
      )}

      {successMessage && (
        <div className="bg-green-50 px-6 py-3">
          <div className="flex items-center space-x-2">
            <svg className="h-5 w-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 13l4 4L19 7"
              />
            </svg>
            <span className="text-sm text-green-700">{successMessage}</span>
          </div>
        </div>
      )}

      {/* Notification Categories */}
      <div className="space-y-6 px-6 py-6">
        <h3 className="text-sm font-medium text-gray-900">Notification Categories</h3>

        {preferenceGroups.map((group) => {
          const typeConfig = NOTIFICATION_TYPE_LABELS[group.notificationType];
          const anyEnabled = group.channels.some((c) => c.isEnabled);

          return (
            <div
              key={group.notificationType}
              className="rounded-lg border border-gray-200 bg-white shadow-sm"
            >
              {/* Category header */}
              <div className="flex items-center justify-between border-b border-gray-100 px-4 py-3">
                <div>
                  <h4 className="text-sm font-medium text-gray-900">{typeConfig.label}</h4>
                  <p className="text-xs text-gray-500">{typeConfig.description}</p>
                </div>
                <div className="flex items-center space-x-2">
                  <button
                    type="button"
                    onClick={() =>
                      anyEnabled
                        ? handleDisableAllForType(group.notificationType)
                        : handleEnableAllForType(group.notificationType)
                    }
                    className="text-xs text-primary hover:text-primary-dark focus:outline-none"
                  >
                    {anyEnabled ? 'Disable all' : 'Enable all'}
                  </button>
                </div>
              </div>

              {/* Channel toggles */}
              <div className="divide-y divide-gray-50">
                {group.channels.map((channelPref) => {
                  const channelConfig = CHANNEL_LABELS[channelPref.channel];

                  return (
                    <div
                      key={channelPref.channel}
                      className="flex items-center justify-between px-4 py-3"
                    >
                      <div className="flex items-center space-x-3">
                        <span className="text-gray-400">
                          {renderChannelIcon(channelPref.channel)}
                        </span>
                        <span className="text-sm text-gray-700">{channelConfig.label}</span>
                      </div>

                      <div className="flex items-center space-x-4">
                        {/* Frequency selector (only show if enabled) */}
                        {channelPref.isEnabled && (
                          <select
                            value={channelPref.frequency}
                            onChange={(e) =>
                              handleFrequencyChange(
                                group.notificationType,
                                channelPref.channel,
                                e.target.value as NotificationFrequency
                              )
                            }
                            className="rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
                          >
                            {FREQUENCY_OPTIONS.map((option) => (
                              <option key={option.value} value={option.value}>
                                {option.label}
                              </option>
                            ))}
                          </select>
                        )}

                        {/* Toggle switch */}
                        <button
                          type="button"
                          role="switch"
                          aria-checked={channelPref.isEnabled}
                          onClick={() =>
                            handleToggleChannel(
                              group.notificationType,
                              channelPref.channel,
                              !channelPref.isEnabled
                            )
                          }
                          className={`relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 ${
                            channelPref.isEnabled ? 'bg-primary' : 'bg-gray-200'
                          }`}
                        >
                          <span
                            className={`pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out ${
                              channelPref.isEnabled ? 'translate-x-5' : 'translate-x-0'
                            }`}
                          />
                        </button>
                      </div>
                    </div>
                  );
                })}
              </div>
            </div>
          );
        })}
      </div>

      {/* Quiet Hours */}
      <div className="px-6 py-6">
        <h3 className="text-sm font-medium text-gray-900">Quiet Hours</h3>
        <p className="mt-1 text-xs text-gray-500">
          Set times when you don&apos;t want to receive notifications (except urgent alerts).
        </p>

        <div className="mt-4 flex flex-wrap items-end gap-4">
          <div>
            <label htmlFor="quietHoursStart" className="block text-xs font-medium text-gray-700">
              Start Time
            </label>
            <input
              type="time"
              id="quietHoursStart"
              value={quietHoursStart}
              onChange={(e) => {
                setQuietHoursStart(e.target.value);
                setHasChanges(true);
              }}
              className="mt-1 block rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            />
          </div>

          <div>
            <label htmlFor="quietHoursEnd" className="block text-xs font-medium text-gray-700">
              End Time
            </label>
            <input
              type="time"
              id="quietHoursEnd"
              value={quietHoursEnd}
              onChange={(e) => {
                setQuietHoursEnd(e.target.value);
                setHasChanges(true);
              }}
              className="mt-1 block rounded-lg border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
            />
          </div>

          <div className="flex space-x-2">
            <button
              type="button"
              onClick={handleSaveQuietHours}
              disabled={isSaving || !quietHoursStart || !quietHoursEnd}
              className="inline-flex items-center rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-gray-300"
            >
              {isSaving ? (
                <>
                  <svg
                    className="mr-2 h-4 w-4 animate-spin text-white"
                    fill="none"
                    viewBox="0 0 24 24"
                  >
                    <circle
                      className="opacity-25"
                      cx="12"
                      cy="12"
                      r="10"
                      stroke="currentColor"
                      strokeWidth="4"
                    />
                    <path
                      className="opacity-75"
                      fill="currentColor"
                      d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                    />
                  </svg>
                  Saving...
                </>
              ) : (
                'Save Quiet Hours'
              )}
            </button>

            {(quietHoursStart || quietHoursEnd) && (
              <button
                type="button"
                onClick={handleClearQuietHours}
                className="inline-flex items-center rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2"
              >
                Clear
              </button>
            )}
          </div>
        </div>

        {quietHoursStart && quietHoursEnd && (
          <p className="mt-3 text-xs text-gray-500">
            Notifications will be paused from {quietHoursStart} to {quietHoursEnd} daily.
          </p>
        )}
      </div>

      {/* Footer with unsaved changes indicator */}
      {hasChanges && (
        <div className="bg-yellow-50 px-6 py-3">
          <div className="flex items-center space-x-2">
            <svg
              className="h-5 w-5 text-yellow-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
            <span className="text-sm text-yellow-700">
              Changes are saved automatically as you make them.
            </span>
          </div>
        </div>
      )}
    </div>
  );
}
