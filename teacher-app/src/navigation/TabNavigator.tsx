/**
 * LAYA Teacher App - TabNavigator
 *
 * Bottom tab navigation component that provides quick access to all
 * main screens in the teacher app. Uses custom tab bar styling with
 * icons and labels for each screen.
 */

import React from 'react';
import {StyleSheet, View, Text} from 'react-native';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';

import AttendanceScreen from '../screens/AttendanceScreen';
import MealLoggingScreen from '../screens/MealLoggingScreen';
import NapTrackingScreen from '../screens/NapTrackingScreen';
import DiaperTrackingScreen from '../screens/DiaperTrackingScreen';
import PhotoCaptureScreen from '../screens/PhotoCaptureScreen';

import type {RootStackParamList} from '../types';

/**
 * Tab icon properties
 */
interface TabIconProps {
  focused: boolean;
  color: string;
  size: number;
}

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  tabBarBackground: '#FFFFFF',
  tabBarBorder: '#E0E0E0',
  inactive: '#999999',
  attendance: '#4CAF50',
  meals: '#FF9800',
  naps: '#9C27B0',
  diapers: '#00BCD4',
  photos: '#E91E63',
};

/**
 * Custom tab icon component using text-based icons
 * Since react-native-vector-icons requires additional setup,
 * we use simple text-based icons for reliability
 */
function TabIcon({
  iconText,
  color,
  focused,
}: {
  iconText: string;
  color: string;
  focused: boolean;
}): React.JSX.Element {
  return (
    <View style={[styles.iconContainer, focused && styles.iconContainerFocused]}>
      <Text style={[styles.iconText, {color}]}>{iconText}</Text>
    </View>
  );
}

/**
 * Tab icons mapping - using emoji/unicode for visual representation
 * These are simple and work without additional dependencies
 */
const TAB_ICONS = {
  Attendance: '\u2713', // Checkmark
  MealLogging: '\u2615', // Coffee/food cup
  NapTracking: '\u263E', // Moon
  DiaperTracking: '\u2661', // Heart (baby care)
  PhotoCapture: '\u25CB', // Circle (camera)
};

const Tab = createBottomTabNavigator<RootStackParamList>();

/**
 * TabNavigator provides the main navigation structure for the teacher app.
 * All five main screens are accessible via bottom tabs for quick access.
 */
function TabNavigator(): React.JSX.Element {
  return (
    <Tab.Navigator
      initialRouteName="Attendance"
      screenOptions={{
        headerStyle: {
          backgroundColor: COLORS.primary,
        },
        headerTintColor: '#FFFFFF',
        headerTitleStyle: {
          fontWeight: '600',
        },
        tabBarActiveTintColor: COLORS.primary,
        tabBarInactiveTintColor: COLORS.inactive,
        tabBarStyle: styles.tabBar,
        tabBarLabelStyle: styles.tabBarLabel,
        tabBarHideOnKeyboard: true,
      }}>
      <Tab.Screen
        name="Attendance"
        component={AttendanceScreen}
        options={{
          title: 'Attendance',
          tabBarLabel: 'Attendance',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.Attendance}
              color={focused ? COLORS.attendance : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Attendance tab',
        }}
      />
      <Tab.Screen
        name="MealLogging"
        component={MealLoggingScreen}
        options={{
          title: 'Meals',
          tabBarLabel: 'Meals',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.MealLogging}
              color={focused ? COLORS.meals : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Meals tab',
        }}
      />
      <Tab.Screen
        name="NapTracking"
        component={NapTrackingScreen}
        options={{
          title: 'Naps',
          tabBarLabel: 'Naps',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.NapTracking}
              color={focused ? COLORS.naps : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Naps tab',
        }}
      />
      <Tab.Screen
        name="DiaperTracking"
        component={DiaperTrackingScreen}
        options={{
          title: 'Diapers',
          tabBarLabel: 'Diapers',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.DiaperTracking}
              color={focused ? COLORS.diapers : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Diapers tab',
        }}
      />
      <Tab.Screen
        name="PhotoCapture"
        component={PhotoCaptureScreen}
        options={{
          title: 'Camera',
          tabBarLabel: 'Camera',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.PhotoCapture}
              color={focused ? COLORS.photos : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Camera tab',
        }}
      />
    </Tab.Navigator>
  );
}

const styles = StyleSheet.create({
  tabBar: {
    backgroundColor: COLORS.tabBarBackground,
    borderTopWidth: 1,
    borderTopColor: COLORS.tabBarBorder,
    paddingTop: 4,
    paddingBottom: 4,
    height: 60,
  },
  tabBarLabel: {
    fontSize: 10,
    fontWeight: '500',
    marginBottom: 4,
  },
  iconContainer: {
    width: 28,
    height: 28,
    justifyContent: 'center',
    alignItems: 'center',
    borderRadius: 14,
  },
  iconContainerFocused: {
    backgroundColor: 'rgba(74, 144, 217, 0.1)',
  },
  iconText: {
    fontSize: 18,
    fontWeight: '600',
  },
});

export default TabNavigator;
