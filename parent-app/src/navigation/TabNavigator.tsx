/**
 * LAYA Parent App - TabNavigator
 *
 * Bottom tab navigation component that provides quick access to all
 * main screens in the parent app. Uses custom tab bar styling with
 * icons and labels for each screen.
 */

import React from 'react';
import {StyleSheet, View, Text} from 'react-native';
import {createBottomTabNavigator} from '@react-navigation/bottom-tabs';

import DailyFeedScreen from '../screens/DailyFeedScreen';
import PhotoGalleryScreen from '../screens/PhotoGalleryScreen';
import MessagesScreen from '../screens/MessagesScreen';
import InvoicesScreen from '../screens/InvoicesScreen';
import ProfileScreen from '../screens/ProfileScreen';

import type {MainTabParamList} from '../types/navigation';

/**
 * Props for tab bar icon function
 */
interface TabIconProps {
  focused: boolean;
  color: string;
  size: number;
}

/**
 * Theme colors used across the parent app
 */
const COLORS = {
  primary: '#5B8DEF',
  tabBarBackground: '#FFFFFF',
  tabBarBorder: '#E5E5E5',
  inactive: '#9CA3AF',
  dailyFeed: '#10B981',
  photos: '#F59E0B',
  messages: '#6366F1',
  invoices: '#8B5CF6',
  profile: '#EC4899',
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
  DailyFeed: '\u2606', // Star outline (daily activities)
  Photos: '\u25A1', // Square (photo frame)
  Messages: '\u2709', // Envelope
  Invoices: '\u2630', // Trigram (document)
  Profile: '\u263A', // Smiling face
};

const Tab = createBottomTabNavigator<MainTabParamList>();

/**
 * TabNavigator provides the main navigation structure for the parent app.
 * All five main screens are accessible via bottom tabs for quick access.
 */
function TabNavigator(): React.JSX.Element {
  return (
    <Tab.Navigator
      initialRouteName="DailyFeed"
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
        name="DailyFeed"
        component={DailyFeedScreen}
        options={{
          title: 'Daily Feed',
          tabBarLabel: 'Feed',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.DailyFeed}
              color={focused ? COLORS.dailyFeed : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Daily Feed tab',
        }}
      />
      <Tab.Screen
        name="Photos"
        component={PhotoGalleryScreen}
        options={{
          title: 'Photos',
          tabBarLabel: 'Photos',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.Photos}
              color={focused ? COLORS.photos : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Photos tab',
        }}
      />
      <Tab.Screen
        name="Messages"
        component={MessagesScreen}
        options={{
          title: 'Messages',
          tabBarLabel: 'Messages',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.Messages}
              color={focused ? COLORS.messages : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Messages tab',
        }}
      />
      <Tab.Screen
        name="Invoices"
        component={InvoicesScreen}
        options={{
          title: 'Invoices',
          tabBarLabel: 'Invoices',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.Invoices}
              color={focused ? COLORS.invoices : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Invoices tab',
        }}
      />
      <Tab.Screen
        name="Profile"
        component={ProfileScreen}
        options={{
          title: 'Profile',
          tabBarLabel: 'Profile',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.Profile}
              color={focused ? COLORS.profile : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Profile tab',
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
    backgroundColor: 'rgba(91, 141, 239, 0.1)',
  },
  iconText: {
    fontSize: 18,
    fontWeight: '600',
  },
});

export default TabNavigator;
