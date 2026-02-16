/**
 * LAYA Parent App - Main Entry Point
 *
 * React Native application for parents to view their child's daily
 * activities, photos, invoices, messages, and receive push notifications.
 *
 * Supports both iOS and Android with platform-specific adaptations.
 * Features bottom tab navigation for quick access to all screens.
 * Authentication-gated: shows LoginScreen for unauthenticated users.
 */

import React, {useEffect, useCallback} from 'react';
import {
  StatusBar,
  BackHandler,
  Platform,
  View,
  ActivityIndicator,
  StyleSheet,
} from 'react-native';
import {NavigationContainer} from '@react-navigation/native';
import {SafeAreaProvider} from 'react-native-safe-area-context';

import {AuthProvider, useAuthContext} from './src/contexts/AuthContext';
import RootNavigator from './src/navigation/RootNavigator';
import LoginScreen from './src/screens/LoginScreen';
import {
  getAuthTokens,
  storeAuthTokens,
  clearAuthTokens,
} from './src/services/secureStorage';

// Platform detection helper
const isAndroid = Platform.OS === 'android';

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  background: '#F5F5F5',
  statusBar: '#6B5B95',
};

/**
 * AppNavigator handles auth-gated navigation.
 * Shows LoginScreen when not authenticated, RootNavigator when authenticated.
 * Displays a loading indicator while checking authentication state.
 */
function AppNavigator(): React.JSX.Element {
  const {state} = useAuthContext();

  // Show loading indicator while checking auth state
  if (state.loading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
      </View>
    );
  }

  // Show LoginScreen if not authenticated
  if (!state.isAuthenticated) {
    return <LoginScreen />;
  }

  // Show main app navigation if authenticated
  return (
    <NavigationContainer>
      <RootNavigator />
    </NavigationContainer>
  );
}

/**
 * Main App component with platform-specific configurations for Android/iOS
 *
 * Features:
 * - Platform-specific status bar configuration
 * - Android hardware back button handling
 * - SafeArea support for notch/cutout displays
 * - Authentication state management via AuthProvider
 * - Secure token storage integration
 * - Auth-gated navigation (LoginScreen vs main app)
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

  /**
   * Callback to retrieve stored authentication tokens from secure storage.
   * Used by AuthProvider to restore session on app launch.
   */
  const handleGetStoredTokens = useCallback(async () => {
    const result = await getAuthTokens();

    if (result.success && result.data) {
      return {
        accessToken: result.data.accessToken,
        refreshToken: result.data.refreshToken,
      };
    }

    return {
      accessToken: null,
      refreshToken: null,
    };
  }, []);

  /**
   * Callback to store authentication tokens securely.
   * Used by AuthProvider after successful login or token refresh.
   * Passing null values clears the stored tokens (logout).
   */
  const handleSetStoredTokens = useCallback(
    async (accessToken: string | null, refreshToken: string | null) => {
      if (accessToken && refreshToken) {
        await storeAuthTokens({
          accessToken,
          refreshToken,
        });
      } else {
        // Clear tokens on logout
        await clearAuthTokens();
      }
    },
    [],
  );

  /**
   * Callback when authentication state changes.
   * Can be used for analytics, logging, or other side effects.
   */
  const handleAuthStateChange = useCallback((isAuthenticated: boolean) => {
    // Log auth state changes for debugging/analytics
    // In production, this could trigger analytics events
    if (__DEV__) {
      // eslint-disable-next-line no-console
      console.log('[Auth] State changed:', isAuthenticated ? 'authenticated' : 'unauthenticated');
    }
  }, []);

  return (
    <SafeAreaProvider>
      {/* Platform-specific status bar configuration */}
      <StatusBar
        barStyle="light-content"
        backgroundColor={isAndroid ? COLORS.statusBar : 'transparent'}
        translucent={isAndroid}
      />
      <AuthProvider
        getStoredTokens={handleGetStoredTokens}
        setStoredTokens={handleSetStoredTokens}
        onAuthStateChange={handleAuthStateChange}>
        <AppNavigator />
      </AuthProvider>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: COLORS.background,
  },
});

export default App;
