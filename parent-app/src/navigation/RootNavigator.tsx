/**
 * LAYA Parent App - RootNavigator
 *
 * Root stack navigator that contains the tab navigator and
 * additional screens accessible via navigation (not tabs).
 */

import React from 'react';
import {createNativeStackNavigator} from '@react-navigation/native-stack';

import type {RootStackParamList} from './types';
import TabNavigator from './TabNavigator';
import ConversationScreen from '../screens/ConversationScreen';

const Stack = createNativeStackNavigator<RootStackParamList>();

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  headerBackground: '#4A90D9',
  headerText: '#FFFFFF',
};

/**
 * RootNavigator provides the main navigation structure.
 * Includes tab navigator as the main screen and additional
 * stack screens for detail views.
 */
function RootNavigator(): React.JSX.Element {
  return (
    <Stack.Navigator
      initialRouteName="MainTabs"
      screenOptions={{
        headerStyle: {
          backgroundColor: COLORS.headerBackground,
        },
        headerTintColor: COLORS.headerText,
        headerTitleStyle: {
          fontWeight: '600',
        },
        headerBackTitleVisible: false,
      }}>
      <Stack.Screen
        name="MainTabs"
        component={TabNavigator}
        options={{headerShown: false}}
      />
      <Stack.Screen
        name="Conversation"
        component={ConversationScreen}
        options={{
          title: 'Conversation',
          headerBackTitle: 'Back',
        }}
      />
    </Stack.Navigator>
  );
}

export default RootNavigator;
