/**
 * LAYA Teacher App - Main Entry Point
 *
 * React Native iOS application for teachers/educators to perform
 * daily childcare tracking activities including attendance, meals,
 * naps, diapers, and photo capture.
 */

import React, {useEffect, useCallback} from 'react';
import {NavigationContainer} from '@react-navigation/native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';
import {SafeAreaProvider} from 'react-native-safe-area-context';

import type {RootStackParamList} from './src/types';

// Import all screens
import AttendanceScreen from './src/screens/AttendanceScreen';
import MealLoggingScreen from './src/screens/MealLoggingScreen';
import NapTrackingScreen from './src/screens/NapTrackingScreen';
import DiaperTrackingScreen from './src/screens/DiaperTrackingScreen';
import PhotoCaptureScreen from './src/screens/PhotoCaptureScreen';

// Import push notification hook
import {useNotifications} from './src/hooks/useNotifications';
import type {NotificationPayload} from './src/services/pushNotifications';

const Stack = createNativeStackNavigator<RootStackParamList>();

/**
 * Main App component with push notification integration
 */
function App(): React.JSX.Element {
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
    <SafeAreaProvider>
      <NavigationContainer>
        <Stack.Navigator
          initialRouteName="Attendance"
          screenOptions={{
            headerStyle: {
              backgroundColor: '#4A90D9',
            },
            headerTintColor: '#FFFFFF',
            headerTitleStyle: {
              fontWeight: '600',
            },
          }}>
          <Stack.Screen
            name="Attendance"
            component={AttendanceScreen}
            options={{title: 'Attendance'}}
          />
          <Stack.Screen
            name="MealLogging"
            component={MealLoggingScreen}
            options={{title: 'Meal Logging'}}
          />
          <Stack.Screen
            name="NapTracking"
            component={NapTrackingScreen}
            options={{title: 'Nap Tracking'}}
          />
          <Stack.Screen
            name="DiaperTracking"
            component={DiaperTrackingScreen}
            options={{title: 'Diaper Tracking'}}
          />
          <Stack.Screen
            name="PhotoCapture"
            component={PhotoCaptureScreen}
            options={{title: 'Photo Capture'}}
          />
        </Stack.Navigator>
      </NavigationContainer>
    </SafeAreaProvider>
  );
}

export default App;
