/**
 * LAYA Parent App - Main Entry Point
 *
 * React Native iOS application for parents to view their children's
 * daily reports, photos, messages, and invoices.
 *
 * This component wraps the app with necessary providers and
 * initializes the navigation structure.
 */

import React from 'react';
import {SafeAreaProvider} from 'react-native-safe-area-context';

import AppNavigator from './src/navigation/AppNavigator';

/**
 * Main App component - entry point for the parent application.
 *
 * Wraps the entire app with:
 * - SafeAreaProvider: Handles safe area insets for iOS devices
 * - AppNavigator: Root navigation structure
 *
 * Future additions:
 * - AuthContext provider
 * - NotificationContext provider
 * - Error boundary
 * - Splash screen handling
 */
function App(): React.JSX.Element {
  return (
    <SafeAreaProvider>
      <AppNavigator />
    </SafeAreaProvider>
  );
}

export default App;
