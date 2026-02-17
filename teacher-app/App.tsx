/**
 * LAYA Teacher App - Main Entry Point
 *
 * React Native application for teachers/educators to perform
 * daily childcare tracking activities including attendance, meals,
 * naps, diapers, and photo capture.
 *
 * Supports both iOS and Android with platform-specific adaptations.
 * Features authentication-aware navigation with login screen and
 * bottom tab navigation for authenticated users.
 */

import React, {useEffect, useCallback} from 'react';
import {StatusBar, BackHandler, Platform} from 'react-native';
import {NavigationContainer} from '@react-navigation/native';
import {SafeAreaProvider} from 'react-native-safe-area-context';

import AppNavigator from './src/navigation/AppNavigator';
import {AuthProvider} from './src/hooks/useAuth';

// Import platform-specific styling utilities
import {
  configureStatusBar,
  isAndroid,
} from './src/utils/platformStyles';

// Import push notification hook
import {useNotifications} from './src/hooks/useNotifications';
import type {NotificationPayload} from './src/services/pushNotifications';

/**
 * Main App component with push notification integration
 * and platform-specific configurations for Android/iOS
 */
function App(): React.JSX.Element {
  /**
   * Configure platform-specific status bar appearance
   * Android uses translucent status bar with dark background
   * iOS relies on SafeAreaView for proper layout
   */
  useEffect(() => {
    // Configure status bar for both platforms
    configureStatusBar('dark');

    // Android-specific: Set status bar to translucent for immersive UI
    if (Platform.OS === 'android') {
      StatusBar.setTranslucent(true);
      StatusBar.setBackgroundColor('transparent');
    }
  }, []);

  /**
   * Handle Android hardware back button
   *
   * Returns false to allow default navigation behavior.
   * In screens where custom handling is needed (e.g., modals, forms),
   * this can be overridden at the screen level.
   */
  useEffect(() => {
    if (!isAndroid) {
      return;
    }

    const backHandler = BackHandler.addEventListener(
      'hardwareBackPress',
      () => {
        // Return false to let React Navigation handle the back action
        // Screens that need custom behavior should add their own handlers
        return false;
      },
    );

    return () => backHandler.remove();
  }, []);

  /**
   * Handle incoming push notifications
   *
   * Routes to the appropriate screen based on notification type.
   * In production, this would navigate to relevant screens.
   */
  const handleNotification = useCallback((payload: NotificationPayload) => {
    // Handle different notification types
    // In production, this would use navigation ref to navigate
    switch (payload.type) {
      case 'attendance':
        // Navigate to attendance screen
        break;
      case 'meal':
        // Navigate to meal logging screen
        break;
      case 'nap':
        // Navigate to nap tracking screen
        break;
      case 'diaper':
        // Navigate to diaper tracking screen
        break;
      case 'photo':
        // Navigate to photo capture screen
        break;
      default:
        // Default handling - notification is already shown via Alert
        break;
    }
  }, []);

  // Initialize push notifications
  const [notificationState, notificationActions] = useNotifications({
    checkOnMount: true,
    onNotificationReceived: handleNotification,
  });

  // Register for push notifications when app starts
  // Only register if not already registered and supported
  useEffect(() => {
    if (
      notificationState.isSupported &&
      !notificationState.isRegistered &&
      !notificationState.isLoading
    ) {
      // Delay registration slightly to not block initial render
      const timer = setTimeout(() => {
        notificationActions.register();
      }, 1000);

      return () => clearTimeout(timer);
    }
  }, [
    notificationState.isSupported,
    notificationState.isRegistered,
    notificationState.isLoading,
    notificationActions,
  ]);

  return (
    <AuthProvider>
      <SafeAreaProvider>
        {/* Platform-specific status bar configuration */}
        <StatusBar
          barStyle="light-content"
          backgroundColor={isAndroid ? '#4A90D9' : 'transparent'}
          translucent={isAndroid}
        />
        <NavigationContainer>
          <AppNavigator />
        </NavigationContainer>
      </SafeAreaProvider>
    </AuthProvider>
  );
}

export default App;
