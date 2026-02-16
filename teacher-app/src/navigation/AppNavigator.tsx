/**
 * LAYA Teacher App - AppNavigator
 *
 * Root navigation structure for the teacher app. Manages the stack-based
 * navigation with authentication-conditional routing - showing login screens
 * when not authenticated and the main tab navigator when authenticated.
 */

import React from 'react';
import {View, Text, StyleSheet, ActivityIndicator} from 'react-native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';

import TabNavigator from './TabNavigator';
import LoginScreen from '../screens/LoginScreen';
import {useAuth} from '../hooks/useAuth';
import type {RootStackParamList} from './types';

/**
 * Theme colors for consistent styling
 */
const COLORS = {
  primary: '#4A90D9',
  background: '#FFFFFF',
  text: '#1F2937',
};

const Stack = createNativeStackNavigator<RootStackParamList>();


/**
 * Splash/Loading Screen
 * Shown while checking authentication state
 */
function SplashScreen(): React.JSX.Element {
  return (
    <View style={styles.splashContainer}>
      <Text style={styles.splashTitle}>LAYA Teacher</Text>
      <ActivityIndicator size="large" color={COLORS.primary} style={styles.spinner} />
      <Text style={styles.splashSubtext}>Loading...</Text>
    </View>
  );
}

/**
 * AppNavigator provides the root navigation structure for the teacher app.
 *
 * Navigation structure:
 * - When loading: Shows Splash screen
 * - When not authenticated: Shows Login screen
 * - When authenticated: Shows Main tab navigator
 *
 * The navigator uses conditional screen groups to separate auth and app screens,
 * enabling proper animated transitions between auth states.
 */
function AppNavigator(): React.JSX.Element {
  const {isAuthenticated, isLoading} = useAuth();

  // Show splash screen while checking auth state
  if (isLoading) {
    return (
      <Stack.Navigator
        screenOptions={{
          headerShown: false,
          contentStyle: {
            backgroundColor: COLORS.background,
          },
        }}>
        <Stack.Screen name="Splash" component={SplashScreen} />
      </Stack.Navigator>
    );
  }

  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: {
          backgroundColor: COLORS.primary,
        },
        headerTintColor: COLORS.background,
        headerTitleStyle: {
          fontWeight: '600',
        },
        headerBackTitle: 'Back',
        contentStyle: {
          backgroundColor: COLORS.background,
        },
      }}>
      {isAuthenticated ? (
        // Authenticated screens
        <Stack.Group>
          <Stack.Screen
            name="Main"
            component={TabNavigator}
            options={{
              headerShown: false,
            }}
          />
          {/*
           * Future detail screens will be added here:
           * - ChildDetail
           * - ActivityDetail
           * - PhotoGallery
           * - Settings
           * - NotificationSettings
           */}
        </Stack.Group>
      ) : (
        // Authentication screens
        <Stack.Group
          screenOptions={{
            headerShown: false,
            animation: 'fade',
          }}>
          <Stack.Screen
            name="Login"
            component={LoginScreen}
            options={{
              title: 'Login',
            }}
          />
        </Stack.Group>
      )}
    </Stack.Navigator>
  );
}

const styles = StyleSheet.create({
  splashContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: COLORS.background,
  },
  splashTitle: {
    fontSize: 32,
    fontWeight: '700',
    color: COLORS.primary,
    marginBottom: 24,
  },
  spinner: {
    marginVertical: 16,
  },
  splashSubtext: {
    fontSize: 16,
    color: COLORS.text,
    opacity: 0.6,
  },
});

export default AppNavigator;
