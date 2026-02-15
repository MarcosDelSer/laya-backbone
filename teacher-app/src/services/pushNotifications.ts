/**
 * LAYA Teacher App - Push Notifications Service
 *
 * Firebase Cloud Messaging (FCM) push notification service.
 * Handles initialization, permission requests, token management,
 * and notification event handling.
 *
 * This service follows the pattern from:
 * gibbon/modules/NotificationEngine/Domain/PushDelivery.php
 */

import {Platform, Alert} from 'react-native';
import {api} from '../api/client';
import {API_CONFIG} from '../api/config';

/**
 * Push notification permission status
 */
export type NotificationPermissionStatus =
  | 'granted'
  | 'denied'
  | 'blocked'
  | 'not_determined';

/**
 * Push notification error codes
 */
export type PushNotificationErrorCode =
  | 'PERMISSION_DENIED'
  | 'TOKEN_REGISTRATION_FAILED'
  | 'INITIALIZATION_FAILED'
  | 'SDK_NOT_AVAILABLE'
  | 'NETWORK_ERROR';

/**
 * Push notification error
 */
export interface PushNotificationError {
  code: PushNotificationErrorCode;
  message: string;
}

/**
 * Push notification result
 */
export interface PushNotificationResult<T = void> {
  success: boolean;
  data?: T;
  error?: PushNotificationError;
}

/**
 * FCM token registration payload
 */
export interface TokenRegistrationPayload {
  deviceToken: string;
  platform: 'ios' | 'android';
  deviceInfo: {
    model: string;
    osVersion: string;
    appVersion: string;
  };
}

/**
 * Notification payload received from FCM
 */
export interface NotificationPayload {
  type: string;
  title: string;
  body: string;
  data?: Record<string, string>;
  notificationId?: string;
}

/**
 * Notification event handler type
 */
export type NotificationEventHandler = (payload: NotificationPayload) => void;

// Internal state
let isInitialized = false;
let currentToken: string | null = null;
let foregroundHandler: NotificationEventHandler | null = null;
let backgroundHandler: NotificationEventHandler | null = null;

/**
 * Mock FCM SDK check - in production, this would check if Firebase is properly configured
 */
function isFCMSDKAvailable(): boolean {
  // Mock implementation - in production would check:
  // return typeof messaging !== 'undefined' && messaging !== null;
  return true;
}

/**
 * Check if push notifications are supported on this device
 */
export function isPushNotificationSupported(): boolean {
  return Platform.OS === 'ios' || Platform.OS === 'android';
}

/**
 * Get current push notification permission status
 *
 * In production, this would use @react-native-firebase/messaging
 * to check actual permission status
 */
export async function getPermissionStatus(): Promise<NotificationPermissionStatus> {
  // Mock implementation - in production would use:
  // import messaging from '@react-native-firebase/messaging';
  // const authStatus = await messaging().hasPermission();
  // return mapAuthStatusToPermissionStatus(authStatus);

  // For development, simulate granted permission
  return 'granted';
}

/**
 * Request push notification permission from the user
 *
 * Returns the resulting permission status after the request
 */
export async function requestPermission(): Promise<PushNotificationResult<NotificationPermissionStatus>> {
  if (!isPushNotificationSupported()) {
    return {
      success: false,
      error: {
        code: 'SDK_NOT_AVAILABLE',
        message: 'Push notifications are not supported on this platform',
      },
    };
  }

  try {
    // Mock implementation - in production would use:
    // import messaging from '@react-native-firebase/messaging';
    // const authStatus = await messaging().requestPermission();
    // const status = mapAuthStatusToPermissionStatus(authStatus);

    // For development, simulate granted permission
    const status: NotificationPermissionStatus = 'granted';

    return {
      success: status === 'granted',
      data: status,
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'PERMISSION_DENIED',
        message: error instanceof Error ? error.message : 'Failed to request permission',
      },
    };
  }
}

/**
 * Initialize Firebase Cloud Messaging
 *
 * This should be called early in the app lifecycle.
 * Sets up message handlers and requests permission if needed.
 */
export async function initializePushNotifications(): Promise<PushNotificationResult> {
  if (isInitialized) {
    return {success: true};
  }

  if (!isFCMSDKAvailable()) {
    return {
      success: false,
      error: {
        code: 'SDK_NOT_AVAILABLE',
        message: 'Firebase Cloud Messaging SDK is not available. Ensure @react-native-firebase/messaging is installed.',
      },
    };
  }

  try {
    // Mock implementation - in production would:
    // 1. Set up foreground message handler
    // import messaging from '@react-native-firebase/messaging';
    // messaging().onMessage(async remoteMessage => {
    //   if (foregroundHandler) {
    //     foregroundHandler(parseRemoteMessage(remoteMessage));
    //   }
    // });

    // 2. Set up background message handler
    // messaging().setBackgroundMessageHandler(async remoteMessage => {
    //   if (backgroundHandler) {
    //     backgroundHandler(parseRemoteMessage(remoteMessage));
    //   }
    // });

    // 3. Handle notification open events
    // messaging().onNotificationOpenedApp(remoteMessage => {
    //   // Handle navigation based on notification type
    // });

    // 4. Check if app was opened from a notification
    // const initialNotification = await messaging().getInitialNotification();
    // if (initialNotification) {
    //   // Handle initial notification
    // }

    isInitialized = true;

    return {success: true};
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'INITIALIZATION_FAILED',
        message: error instanceof Error ? error.message : 'Failed to initialize FCM',
      },
    };
  }
}

/**
 * Get the FCM device token
 *
 * Returns the current FCM token for this device.
 * The token may change periodically, so listeners should be set up.
 */
export async function getDeviceToken(): Promise<PushNotificationResult<string>> {
  if (!isFCMSDKAvailable()) {
    return {
      success: false,
      error: {
        code: 'SDK_NOT_AVAILABLE',
        message: 'Firebase Cloud Messaging SDK is not available',
      },
    };
  }

  try {
    // Mock implementation - in production would use:
    // import messaging from '@react-native-firebase/messaging';
    // const token = await messaging().getToken();

    // For development, generate a mock token
    const mockToken = `mock_fcm_token_${Date.now()}_${Platform.OS}`;
    currentToken = mockToken;

    return {
      success: true,
      data: mockToken,
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'TOKEN_REGISTRATION_FAILED',
        message: error instanceof Error ? error.message : 'Failed to get FCM token',
      },
    };
  }
}

/**
 * Register FCM token with the backend
 *
 * Sends the device token to the Gibbon backend for storage.
 * The backend will use this token to send push notifications.
 */
export async function registerTokenWithBackend(
  token: string,
): Promise<PushNotificationResult> {
  const payload: TokenRegistrationPayload = {
    deviceToken: token,
    platform: Platform.OS as 'ios' | 'android',
    deviceInfo: {
      model: 'iOS Device', // In production, use device-info package
      osVersion: Platform.Version.toString(),
      appVersion: '1.0.0', // In production, get from app config
    },
  };

  try {
    const response = await api.post(
      API_CONFIG.endpoints.notifications.registerToken,
      payload,
    );

    if (!response.success) {
      return {
        success: false,
        error: {
          code: 'TOKEN_REGISTRATION_FAILED',
          message: response.error?.message || 'Failed to register token with backend',
        },
      };
    }

    return {success: true};
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'NETWORK_ERROR',
        message: error instanceof Error ? error.message : 'Network error during token registration',
      },
    };
  }
}

/**
 * Full token registration flow
 *
 * Requests permission, gets token, and registers with backend.
 * This is the main entry point for setting up push notifications.
 */
export async function registerForPushNotifications(): Promise<PushNotificationResult<string>> {
  // Step 1: Initialize FCM
  const initResult = await initializePushNotifications();
  if (!initResult.success) {
    return {
      success: false,
      error: initResult.error,
    };
  }

  // Step 2: Request permission
  const permissionResult = await requestPermission();
  if (!permissionResult.success || permissionResult.data !== 'granted') {
    return {
      success: false,
      error: {
        code: 'PERMISSION_DENIED',
        message: 'Push notification permission was not granted',
      },
    };
  }

  // Step 3: Get device token
  const tokenResult = await getDeviceToken();
  if (!tokenResult.success || !tokenResult.data) {
    return {
      success: false,
      error: tokenResult.error,
    };
  }

  // Step 4: Register token with backend
  const registerResult = await registerTokenWithBackend(tokenResult.data);
  if (!registerResult.success) {
    // Token registration with backend failed, but we have the token
    // Log this error but don't fail the whole operation
    // In production, this could be retried later
    return {
      success: true, // Still return success since we have the token
      data: tokenResult.data,
      error: registerResult.error, // Include error for monitoring
    };
  }

  return {
    success: true,
    data: tokenResult.data,
  };
}

/**
 * Get the current FCM token (cached)
 */
export function getCurrentToken(): string | null {
  return currentToken;
}

/**
 * Set handler for foreground notifications
 *
 * Called when a notification is received while the app is in the foreground.
 */
export function setForegroundNotificationHandler(
  handler: NotificationEventHandler,
): void {
  foregroundHandler = handler;
}

/**
 * Set handler for background notifications
 *
 * Called when a notification is received while the app is in the background.
 */
export function setBackgroundNotificationHandler(
  handler: NotificationEventHandler,
): void {
  backgroundHandler = handler;
}

/**
 * Subscribe to a notification topic
 *
 * Topics allow sending notifications to groups of users.
 */
export async function subscribeToTopic(
  topic: string,
): Promise<PushNotificationResult> {
  if (!isFCMSDKAvailable()) {
    return {
      success: false,
      error: {
        code: 'SDK_NOT_AVAILABLE',
        message: 'Firebase Cloud Messaging SDK is not available',
      },
    };
  }

  try {
    // Mock implementation - in production would use:
    // import messaging from '@react-native-firebase/messaging';
    // await messaging().subscribeToTopic(topic);

    return {success: true};
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'TOKEN_REGISTRATION_FAILED',
        message: error instanceof Error ? error.message : 'Failed to subscribe to topic',
      },
    };
  }
}

/**
 * Unsubscribe from a notification topic
 */
export async function unsubscribeFromTopic(
  topic: string,
): Promise<PushNotificationResult> {
  if (!isFCMSDKAvailable()) {
    return {
      success: false,
      error: {
        code: 'SDK_NOT_AVAILABLE',
        message: 'Firebase Cloud Messaging SDK is not available',
      },
    };
  }

  try {
    // Mock implementation - in production would use:
    // import messaging from '@react-native-firebase/messaging';
    // await messaging().unsubscribeFromTopic(topic);

    return {success: true};
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'TOKEN_REGISTRATION_FAILED',
        message: error instanceof Error ? error.message : 'Failed to unsubscribe from topic',
      },
    };
  }
}

/**
 * Delete the FCM token
 *
 * Use this when the user logs out to stop receiving notifications.
 */
export async function deleteToken(): Promise<PushNotificationResult> {
  if (!isFCMSDKAvailable()) {
    return {
      success: false,
      error: {
        code: 'SDK_NOT_AVAILABLE',
        message: 'Firebase Cloud Messaging SDK is not available',
      },
    };
  }

  try {
    // Mock implementation - in production would use:
    // import messaging from '@react-native-firebase/messaging';
    // await messaging().deleteToken();

    currentToken = null;
    return {success: true};
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'TOKEN_REGISTRATION_FAILED',
        message: error instanceof Error ? error.message : 'Failed to delete token',
      },
    };
  }
}

/**
 * Show a local notification
 *
 * Used to display notifications when the app is in the foreground.
 * In production, this would use a local notification library.
 */
export function showLocalNotification(
  title: string,
  body: string,
): void {
  // Mock implementation - in production would use:
  // import notifee from '@notifee/react-native';
  // await notifee.displayNotification({
  //   title,
  //   body,
  //   android: { channelId: 'default' },
  // });

  // For development, show an alert
  Alert.alert(title, body);
}

export default {
  isPushNotificationSupported,
  getPermissionStatus,
  requestPermission,
  initializePushNotifications,
  getDeviceToken,
  registerTokenWithBackend,
  registerForPushNotifications,
  getCurrentToken,
  setForegroundNotificationHandler,
  setBackgroundNotificationHandler,
  subscribeToTopic,
  unsubscribeFromTopic,
  deleteToken,
  showLocalNotification,
};
