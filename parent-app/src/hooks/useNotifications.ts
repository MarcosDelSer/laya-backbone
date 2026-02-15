/**
 * LAYA Parent App - useNotifications Hook
 *
 * A custom hook for managing push notification state and registration.
 * Handles permission checking, token registration, and notification handling.
 *
 * Follows the pattern from the teacher-app useNotifications.ts.
 */

import {useState, useEffect, useCallback, useRef} from 'react';
import {AppState, AppStateStatus} from 'react-native';

import {
  NotificationPermissionStatus,
  NotificationPayload,
  PushNotificationError,
  isPushNotificationSupported,
  getPermissionStatus,
  initializePushNotifications,
  registerForPushNotifications,
  getCurrentToken,
  setForegroundNotificationHandler,
  showLocalNotification,
} from '../services/pushNotifications';

/**
 * Notification state managed by the hook
 */
export interface NotificationState {
  /** Current permission status */
  permissionStatus: NotificationPermissionStatus;
  /** Whether push notifications are supported on this device */
  isSupported: boolean;
  /** Whether notifications are enabled (permission granted) */
  isEnabled: boolean;
  /** Current FCM token (null if not registered) */
  token: string | null;
  /** Whether currently loading/registering */
  isLoading: boolean;
  /** Whether registration is complete */
  isRegistered: boolean;
  /** Error message if registration failed */
  error: PushNotificationError | null;
}

/**
 * Notification actions provided by the hook
 */
export interface NotificationActions {
  /** Request notification permission and register token */
  register: () => Promise<boolean>;
  /** Re-check current permission status */
  checkPermission: () => Promise<void>;
  /** Handle a received notification */
  handleNotification: (payload: NotificationPayload) => void;
}

/**
 * Return type for the useNotifications hook
 */
export type UseNotificationsReturn = [NotificationState, NotificationActions];

/**
 * Options for the useNotifications hook
 */
export interface UseNotificationsOptions {
  /** Whether to register on mount automatically (default: false) */
  registerOnMount?: boolean;
  /** Whether to check permission on mount (default: true) */
  checkOnMount?: boolean;
  /** Callback when a notification is received */
  onNotificationReceived?: (payload: NotificationPayload) => void;
}

/**
 * Custom hook for managing push notification state and registration
 *
 * Provides a clean interface for registering for push notifications,
 * handling permission requests, and receiving notification events.
 *
 * @param options - Hook configuration options
 * @returns [state, actions] - Notification state and control actions
 *
 * @example
 * ```tsx
 * const [notifications, actions] = useNotifications({
 *   onNotificationReceived: (payload) => {
 *     // Handle notification (e.g., navigate to relevant screen)
 *   },
 * });
 *
 * if (!notifications.isRegistered && !notifications.isLoading) {
 *   return (
 *     <Button
 *       title="Enable Notifications"
 *       onPress={actions.register}
 *     />
 *   );
 * }
 * ```
 */
export function useNotifications(
  options: UseNotificationsOptions = {},
): UseNotificationsReturn {
  const {
    registerOnMount = false,
    checkOnMount = true,
    onNotificationReceived,
  } = options;

  // State
  const [permissionStatus, setPermissionStatus] =
    useState<NotificationPermissionStatus>('not_determined');
  const [token, setToken] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [isRegistered, setIsRegistered] = useState(false);
  const [error, setError] = useState<PushNotificationError | null>(null);

  // Refs for callback stability
  const onNotificationReceivedRef = useRef(onNotificationReceived);
  onNotificationReceivedRef.current = onNotificationReceived;

  // Check if push notifications are supported
  const isSupported = isPushNotificationSupported();

  // Derived state
  const isEnabled = permissionStatus === 'granted';

  /**
   * Check current permission status
   */
  const checkPermission = useCallback(async () => {
    if (!isSupported) {
      return;
    }

    try {
      const status = await getPermissionStatus();
      setPermissionStatus(status);

      // Update token if we have one
      const currentToken = getCurrentToken();
      if (currentToken) {
        setToken(currentToken);
        setIsRegistered(true);
      }
    } catch (err) {
      setError({
        code: 'PERMISSION_DENIED',
        message: err instanceof Error ? err.message : 'Failed to check permission',
      });
    }
  }, [isSupported]);

  /**
   * Handle received notification
   */
  const handleNotification = useCallback((payload: NotificationPayload) => {
    // Show local notification if in foreground
    showLocalNotification(payload.title, payload.body);

    // Call callback if provided
    if (onNotificationReceivedRef.current) {
      onNotificationReceivedRef.current(payload);
    }
  }, []);

  /**
   * Register for push notifications
   */
  const register = useCallback(async (): Promise<boolean> => {
    if (!isSupported) {
      setError({
        code: 'SDK_NOT_AVAILABLE',
        message: 'Push notifications are not supported on this device',
      });
      return false;
    }

    setIsLoading(true);
    setError(null);

    try {
      // Initialize FCM and set up handlers
      const initResult = await initializePushNotifications();
      if (!initResult.success) {
        setError(initResult.error || {
          code: 'INITIALIZATION_FAILED',
          message: 'Failed to initialize push notifications',
        });
        setIsLoading(false);
        return false;
      }

      // Set up notification handler
      setForegroundNotificationHandler(handleNotification);

      // Register for push notifications
      const result = await registerForPushNotifications();

      if (result.success && result.data) {
        setToken(result.data);
        setIsRegistered(true);
        setPermissionStatus('granted');

        // If there was a non-critical error (e.g., backend registration failed),
        // still consider it a success but store the error for monitoring
        if (result.error) {
          setError(result.error);
        }

        setIsLoading(false);
        return true;
      }

      // Registration failed
      setError(result.error || {
        code: 'TOKEN_REGISTRATION_FAILED',
        message: 'Failed to register for push notifications',
      });

      // Update permission status
      const status = await getPermissionStatus();
      setPermissionStatus(status);

      setIsLoading(false);
      return false;
    } catch (err) {
      setError({
        code: 'INITIALIZATION_FAILED',
        message: err instanceof Error ? err.message : 'Failed to register',
      });
      setIsLoading(false);
      return false;
    }
  }, [isSupported, handleNotification]);

  // Check permission on mount
  useEffect(() => {
    if (checkOnMount) {
      checkPermission();
    }
  }, [checkOnMount, checkPermission]);

  // Register on mount if requested
  useEffect(() => {
    if (registerOnMount && !isRegistered && !isLoading) {
      register();
    }
  }, [registerOnMount, isRegistered, isLoading, register]);

  // Re-check permission when app comes to foreground
  useEffect(() => {
    const handleAppStateChange = (nextAppState: AppStateStatus) => {
      if (nextAppState === 'active') {
        checkPermission();
      }
    };

    const subscription = AppState.addEventListener('change', handleAppStateChange);

    return () => {
      subscription.remove();
    };
  }, [checkPermission]);

  // Build state object
  const state: NotificationState = {
    permissionStatus,
    isSupported,
    isEnabled,
    token,
    isLoading,
    isRegistered,
    error,
  };

  // Build actions object
  const actions: NotificationActions = {
    register,
    checkPermission,
    handleNotification,
  };

  return [state, actions];
}

export default useNotifications;
