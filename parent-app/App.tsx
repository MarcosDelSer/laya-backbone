/**
 * LAYA Parent App - Main Entry Point
 *
 * React Native iOS application for parents to view their children's
 * daily reports, photos, messages, and invoices.
 *
 * This component wraps the app with necessary providers and
 * initializes the navigation structure.
 */

import React, {useCallback} from 'react';
import {SafeAreaProvider} from 'react-native-safe-area-context';

import AppNavigator from './src/navigation/AppNavigator';
import {NotificationProvider} from './src/contexts/NotificationContext';
import {NotificationPayload} from './src/services/pushNotifications';

/**
 * Main App component - entry point for the parent application.
 *
 * Wraps the entire app with:
 * - SafeAreaProvider: Handles safe area insets for iOS devices
 * - NotificationProvider: App-wide push notification state management
 * - AppNavigator: Root navigation structure
 *
 * Future additions:
 * - AuthContext provider
 * - Error boundary
 * - Splash screen handling
 */
function App(): React.JSX.Element {
  /**
   * Handle received push notifications
   *
   * This callback is called when a notification is received while
   * the app is in the foreground. Use this to navigate to the
   * relevant screen based on notification type.
   */
  const handleNotificationReceived = useCallback(
    (payload: NotificationPayload) => {
      // Handle navigation based on notification type
      // In the future, this can navigate to specific screens:
      // - 'daily_report' -> DailyFeedScreen
      // - 'message' -> MessagesScreen
      // - 'invoice' -> InvoicesScreen
      // - 'photo' -> PhotosScreen
    },
    [],
  );

  return (
    <SafeAreaProvider>
      <NotificationProvider onNotificationReceived={handleNotificationReceived}>
        <AppNavigator />
      </NotificationProvider>
    </SafeAreaProvider>
  );
}

export default App;
