/**
 * LAYA Parent App - Navigation Type Definitions
 *
 * Type definitions for React Navigation, including stack and tab
 * navigator parameter lists and navigation prop types.
 */

import type {
  CompositeNavigationProp,
  NavigatorScreenParams,
} from '@react-navigation/native';
import type { NativeStackNavigationProp } from '@react-navigation/native-stack';
import type { BottomTabNavigationProp } from '@react-navigation/bottom-tabs';

// ============================================================================
// Root Stack Navigator
// ============================================================================

/**
 * Root stack navigator parameter list.
 * Includes authentication screens and main app navigator.
 */
export type RootStackParamList = {
  /** Splash/loading screen during app initialization */
  Splash: undefined;
  /** Login screen for authentication */
  Login: undefined;
  /** Main app with bottom tab navigation */
  Main: NavigatorScreenParams<MainTabParamList>;
  /** Daily report detail view */
  DailyReportDetail: { reportId: string; childId: string };
  /** Photo viewer modal */
  PhotoViewer: { photoId: string; photoUrl: string; caption?: string };
  /** Message thread detail view */
  MessageThread: { threadId: string; subject: string };
  /** Invoice detail view */
  InvoiceDetail: { invoiceId: string };
  /** Document signing view */
  DocumentSign: { documentId: string };
  /** Child profile view */
  ChildProfile: { childId: string };
  /** Settings screen */
  Settings: undefined;
  /** Notification settings */
  NotificationSettings: undefined;
};

// ============================================================================
// Main Tab Navigator
// ============================================================================

/**
 * Main bottom tab navigator parameter list.
 */
export type MainTabParamList = {
  /** Daily reports feed screen */
  DailyFeed: undefined;
  /** Photo gallery screen */
  Photos: undefined;
  /** Messages list screen */
  Messages: undefined;
  /** Invoices list screen */
  Invoices: undefined;
  /** Profile/more menu screen */
  Profile: undefined;
};

// ============================================================================
// Navigation Props
// ============================================================================

/**
 * Navigation prop for root stack screens.
 */
export type RootStackNavigationProp<T extends keyof RootStackParamList> =
  NativeStackNavigationProp<RootStackParamList, T>;

/**
 * Navigation prop for main tab screens.
 */
export type MainTabNavigationProp<T extends keyof MainTabParamList> =
  CompositeNavigationProp<
    BottomTabNavigationProp<MainTabParamList, T>,
    NativeStackNavigationProp<RootStackParamList>
  >;

// ============================================================================
// Screen Props
// ============================================================================

/**
 * Props for Daily Feed screen.
 */
export interface DailyFeedScreenProps {
  navigation: MainTabNavigationProp<'DailyFeed'>;
}

/**
 * Props for Photos screen.
 */
export interface PhotosScreenProps {
  navigation: MainTabNavigationProp<'Photos'>;
}

/**
 * Props for Messages screen.
 */
export interface MessagesScreenProps {
  navigation: MainTabNavigationProp<'Messages'>;
}

/**
 * Props for Invoices screen.
 */
export interface InvoicesScreenProps {
  navigation: MainTabNavigationProp<'Invoices'>;
}

/**
 * Props for Profile screen.
 */
export interface ProfileScreenProps {
  navigation: MainTabNavigationProp<'Profile'>;
}

/**
 * Props for Daily Report Detail screen.
 */
export interface DailyReportDetailScreenProps {
  navigation: RootStackNavigationProp<'DailyReportDetail'>;
  route: {
    params: RootStackParamList['DailyReportDetail'];
  };
}

/**
 * Props for Photo Viewer screen.
 */
export interface PhotoViewerScreenProps {
  navigation: RootStackNavigationProp<'PhotoViewer'>;
  route: {
    params: RootStackParamList['PhotoViewer'];
  };
}

/**
 * Props for Message Thread screen.
 */
export interface MessageThreadScreenProps {
  navigation: RootStackNavigationProp<'MessageThread'>;
  route: {
    params: RootStackParamList['MessageThread'];
  };
}

/**
 * Props for Invoice Detail screen.
 */
export interface InvoiceDetailScreenProps {
  navigation: RootStackNavigationProp<'InvoiceDetail'>;
  route: {
    params: RootStackParamList['InvoiceDetail'];
  };
}

/**
 * Props for Document Sign screen.
 */
export interface DocumentSignScreenProps {
  navigation: RootStackNavigationProp<'DocumentSign'>;
  route: {
    params: RootStackParamList['DocumentSign'];
  };
}

/**
 * Props for Child Profile screen.
 */
export interface ChildProfileScreenProps {
  navigation: RootStackNavigationProp<'ChildProfile'>;
  route: {
    params: RootStackParamList['ChildProfile'];
  };
}

/**
 * Props for Settings screen.
 */
export interface SettingsScreenProps {
  navigation: RootStackNavigationProp<'Settings'>;
}

/**
 * Props for Notification Settings screen.
 */
export interface NotificationSettingsScreenProps {
  navigation: RootStackNavigationProp<'NotificationSettings'>;
}

// ============================================================================
// Navigation Helpers
// ============================================================================

/**
 * Declare global navigation types for useNavigation hook.
 */
declare global {
  namespace ReactNavigation {
    interface RootParamList extends RootStackParamList {}
  }
}
