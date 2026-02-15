/**
 * LAYA Parent App - AppNavigator
 *
 * Root navigation structure for the parent app. Manages the stack-based
 * navigation including authentication screens, main tab navigator, and
 * detail screens accessible from anywhere in the app.
 */

import React from 'react';
import {NavigationContainer} from '@react-navigation/native';
import {createNativeStackNavigator} from '@react-navigation/native-stack';

import TabNavigator from './TabNavigator';
import type {RootStackParamList} from '../types/navigation';

/**
 * Theme colors for consistent styling
 */
const COLORS = {
  primary: '#5B8DEF',
  background: '#FFFFFF',
  text: '#1F2937',
};

const Stack = createNativeStackNavigator<RootStackParamList>();

/**
 * AppNavigator provides the root navigation structure for the parent app.
 *
 * Navigation structure:
 * - Splash: Initial loading screen
 * - Login: Authentication screen
 * - Main: Bottom tab navigator with 5 main screens
 * - Detail screens: DailyReportDetail, PhotoViewer, MessageThread, etc.
 *
 * Future implementations will add:
 * - Authentication state management
 * - Deep linking configuration
 * - Screen transitions and animations
 */
function AppNavigator(): React.JSX.Element {
  return (
    <NavigationContainer>
      <Stack.Navigator
        initialRouteName="Main"
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
        {/* Main Tab Navigator - contains all bottom tab screens */}
        <Stack.Screen
          name="Main"
          component={TabNavigator}
          options={{
            headerShown: false,
          }}
        />

        {/*
         * Future detail screens will be added here:
         * - Splash
         * - Login
         * - DailyReportDetail
         * - PhotoViewer
         * - MessageThread
         * - InvoiceDetail
         * - DocumentSign
         * - ChildProfile
         * - Settings
         * - NotificationSettings
         */}
      </Stack.Navigator>
    </NavigationContainer>
  );
}

export default AppNavigator;
