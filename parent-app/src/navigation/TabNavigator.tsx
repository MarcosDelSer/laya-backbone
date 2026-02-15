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

import type {RootTabParamList} from './types';
import DailyFeedScreen from '../screens/DailyFeedScreen';

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
  feed: '#4CAF50',
  photos: '#E91E63',
  invoices: '#FF9800',
  messages: '#2196F3',
  signatures: '#9C27B0',
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
  DailyFeed: '\u2302', // House/home
  PhotoGallery: '\u25A1', // Square (gallery)
  Invoices: '\u2750', // Document
  Messages: '\u2709', // Envelope
  Signatures: '\u270D', // Writing hand
};

/**
 * Placeholder screen component for screens not yet implemented
 * These will be replaced with actual screen components in later phases
 */
function PlaceholderScreen({
  title,
  description,
}: {
  title: string;
  description: string;
}): React.JSX.Element {
  return (
    <View style={styles.placeholderContainer}>
      <Text style={styles.placeholderTitle}>{title}</Text>
      <Text style={styles.placeholderDescription}>{description}</Text>
    </View>
  );
}

/**
 * Temporary placeholder screens
 * TODO: Replace with actual screen imports once created in phase 6 & 7:
 * - PhotoGalleryScreen (subtask-6-2)
 * - InvoicesScreen (subtask-6-3)
 * - MessagingScreen (subtask-7-1)
 * - DocumentsScreen (subtask-7-2)
 */

function PhotoGalleryScreen(): React.JSX.Element {
  return (
    <PlaceholderScreen
      title="Photo Gallery"
      description="Browse and download photos of your child"
    />
  );
}

function InvoicesScreen(): React.JSX.Element {
  return (
    <PlaceholderScreen
      title="Invoices"
      description="View and manage your invoices"
    />
  );
}

function MessagingScreen(): React.JSX.Element {
  return (
    <PlaceholderScreen
      title="Messages"
      description="Communicate with your child's educators"
    />
  );
}

function SignaturesScreen(): React.JSX.Element {
  return (
    <PlaceholderScreen
      title="Documents"
      description="Sign and view important documents"
    />
  );
}

const Tab = createBottomTabNavigator<RootTabParamList>();

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
          title: 'Feed',
          tabBarLabel: 'Feed',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.DailyFeed}
              color={focused ? COLORS.feed : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Daily feed tab',
        }}
      />
      <Tab.Screen
        name="PhotoGallery"
        component={PhotoGalleryScreen}
        options={{
          title: 'Photos',
          tabBarLabel: 'Photos',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.PhotoGallery}
              color={focused ? COLORS.photos : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Photo gallery tab',
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
        name="Messages"
        component={MessagingScreen}
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
        name="Signatures"
        component={SignaturesScreen}
        options={{
          title: 'Documents',
          tabBarLabel: 'Documents',
          tabBarIcon: ({color, focused}: TabIconProps) => (
            <TabIcon
              iconText={TAB_ICONS.Signatures}
              color={focused ? COLORS.signatures : color}
              focused={focused}
            />
          ),
          tabBarAccessibilityLabel: 'Documents tab',
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
  placeholderContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F5F5F5',
    padding: 20,
  },
  placeholderTitle: {
    fontSize: 24,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 12,
  },
  placeholderDescription: {
    fontSize: 16,
    color: '#666666',
    textAlign: 'center',
  },
});

export default TabNavigator;
