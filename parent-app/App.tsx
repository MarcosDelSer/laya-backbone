/**
 * LAYA Parent App - Main Entry Point
 *
 * React Native application for parents to view their child's daily
 * activities, photos, invoices, messages, and receive push notifications.
 *
 * Supports both iOS and Android with platform-specific adaptations.
 * Features bottom tab navigation for quick access to all screens.
 */

import React, {useEffect} from 'react';
import {StatusBar, BackHandler, Platform} from 'react-native';
import {NavigationContainer} from '@react-navigation/native';
import {SafeAreaProvider} from 'react-native-safe-area-context';

import TabNavigator from './src/navigation/TabNavigator';

// Platform detection helper
const isAndroid = Platform.OS === 'android';

/**
 * Main App component with platform-specific configurations for Android/iOS
 *
 * Features:
 * - Platform-specific status bar configuration
 * - Android hardware back button handling
 * - SafeArea support for notch/cutout displays
 * - Navigation container for screen management
 */
function App(): React.JSX.Element {
  /**
   * Configure platform-specific status bar appearance
   * Android uses translucent status bar with dark background
   * iOS relies on SafeAreaView for proper layout
   */
  useEffect(() => {
    // Android-specific: Set status bar to translucent for immersive UI
    if (Platform.OS === 'android') {
      StatusBar.setTranslucent(true);
      StatusBar.setBackgroundColor('transparent');
      StatusBar.setBarStyle('light-content');
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

  return (
    <SafeAreaProvider>
      {/* Platform-specific status bar configuration */}
      <StatusBar
        barStyle="light-content"
        backgroundColor={isAndroid ? '#6B5B95' : 'transparent'}
        translucent={isAndroid}
      />
      <NavigationContainer>
        <TabNavigator />
      </NavigationContainer>
    </SafeAreaProvider>
  );
}

export default App;
