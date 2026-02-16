/**
 * LAYA Teacher App - Navigation Type Definitions
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
};

// ============================================================================
// Main Tab Navigator
// ============================================================================

/**
 * Main bottom tab navigator parameter list.
 * Contains all the primary teacher screens accessible via tabs.
 */
export type MainTabParamList = {
  /** Attendance tracking screen */
  Attendance: undefined;
  /** Meal logging screen */
  MealLogging: { childId?: string };
  /** Nap tracking screen */
  NapTracking: { childId?: string };
  /** Diaper tracking screen */
  DiaperTracking: { childId?: string };
  /** Photo capture screen */
  PhotoCapture: undefined;
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
 * Props for Splash screen.
 */
export interface SplashScreenProps {
  navigation: RootStackNavigationProp<'Splash'>;
}

/**
 * Props for Login screen.
 */
export interface LoginScreenProps {
  navigation: RootStackNavigationProp<'Login'>;
}

/**
 * Props for Attendance screen.
 */
export interface AttendanceScreenProps {
  navigation: MainTabNavigationProp<'Attendance'>;
}

/**
 * Props for Meal Logging screen.
 */
export interface MealLoggingScreenProps {
  navigation: MainTabNavigationProp<'MealLogging'>;
  route: {
    params?: MainTabParamList['MealLogging'];
  };
}

/**
 * Props for Nap Tracking screen.
 */
export interface NapTrackingScreenProps {
  navigation: MainTabNavigationProp<'NapTracking'>;
  route: {
    params?: MainTabParamList['NapTracking'];
  };
}

/**
 * Props for Diaper Tracking screen.
 */
export interface DiaperTrackingScreenProps {
  navigation: MainTabNavigationProp<'DiaperTracking'>;
  route: {
    params?: MainTabParamList['DiaperTracking'];
  };
}

/**
 * Props for Photo Capture screen.
 */
export interface PhotoCaptureScreenProps {
  navigation: MainTabNavigationProp<'PhotoCapture'>;
}
