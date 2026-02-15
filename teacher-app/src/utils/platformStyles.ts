/**
 * LAYA Teacher App - Platform-Specific Styles Utilities
 *
 * Provides platform-specific styling utilities for Android and iOS,
 * including elevation, shadows, status bar configuration, and
 * Material Design adaptations for Android.
 */

import {Platform, StatusBar, StyleSheet, Dimensions} from 'react-native';
import type {ViewStyle, TextStyle} from 'react-native';

/**
 * Platform detection helpers
 */
export const isAndroid = Platform.OS === 'android';
export const isIOS = Platform.OS === 'ios';

/**
 * Android API level (SDK version)
 * Useful for conditional styling based on Android version
 */
export const androidApiLevel = Platform.OS === 'android' ? Platform.Version : 0;

/**
 * StatusBar height for layout calculations
 * Android status bar height varies by device
 */
export const STATUS_BAR_HEIGHT = Platform.select({
  android: StatusBar.currentHeight ?? 24,
  ios: 0, // iOS uses SafeAreaView
  default: 0,
});

/**
 * Navigation bar height estimate for Android
 * Used for bottom padding calculations
 */
export const ANDROID_NAV_BAR_HEIGHT = Platform.OS === 'android' ? 48 : 0;

/**
 * Screen dimensions with status bar consideration
 */
export const getScreenDimensions = () => {
  const {width, height} = Dimensions.get('window');
  return {
    width,
    height,
    // Available height accounting for status bar on Android
    availableHeight:
      Platform.OS === 'android' ? height - (StatusBar.currentHeight ?? 0) : height,
  };
};

/**
 * Elevation style configuration
 * Creates platform-appropriate shadow/elevation effects
 */
interface ElevationConfig {
  elevation: number;
  shadowColor?: string;
}

/**
 * Create elevation style that works on both platforms
 *
 * @param level - Elevation level (0-24, following Material Design)
 * @param shadowColor - Shadow color (default: black)
 * @returns Platform-specific style object
 *
 * @example
 * ```typescript
 * const cardStyle = {
 *   ...createElevation(4),
 *   backgroundColor: '#FFFFFF',
 * };
 * ```
 */
export function createElevation(
  level: number,
  shadowColor = '#000000',
): ViewStyle {
  if (level === 0) {
    return {};
  }

  return Platform.select({
    android: {
      elevation: level,
    },
    ios: {
      shadowColor,
      shadowOffset: {
        width: 0,
        height: Math.round(level / 2),
      },
      shadowOpacity: 0.1 + level * 0.01,
      shadowRadius: level * 0.5,
    },
    default: {},
  }) as ViewStyle;
}

/**
 * Pre-defined elevation levels following Material Design
 */
export const elevations = {
  /** No elevation - flat surface */
  none: createElevation(0),
  /** Subtle elevation for cards on scrollable content */
  card: createElevation(2),
  /** Standard card elevation */
  raised: createElevation(4),
  /** Elevated card, floating action buttons */
  floating: createElevation(6),
  /** App bar level elevation */
  appBar: createElevation(4),
  /** Modal/dialog elevation */
  modal: createElevation(16),
  /** Navigation drawer elevation */
  drawer: createElevation(16),
  /** Snackbar/toast elevation */
  snackbar: createElevation(6),
} as const;

/**
 * Status bar configuration for different screen types
 */
interface StatusBarConfig {
  barStyle: 'light-content' | 'dark-content';
  backgroundColor: string;
  translucent: boolean;
}

/**
 * Pre-defined status bar configurations
 */
export const statusBarConfigs: Record<string, StatusBarConfig> = {
  /** Default light background with dark icons */
  light: {
    barStyle: 'dark-content',
    backgroundColor: '#FFFFFF',
    translucent: false,
  },
  /** Dark/primary background with light icons */
  dark: {
    barStyle: 'light-content',
    backgroundColor: '#4A90D9',
    translucent: false,
  },
  /** Translucent overlay for full-screen content */
  translucent: {
    barStyle: 'light-content',
    backgroundColor: 'transparent',
    translucent: true,
  },
  /** Camera/media capture mode */
  camera: {
    barStyle: 'light-content',
    backgroundColor: '#000000',
    translucent: true,
  },
};

/**
 * Configure the status bar for the current screen
 *
 * @param config - Status bar configuration key or custom config
 *
 * @example
 * ```typescript
 * // Using preset
 * configureStatusBar('dark');
 *
 * // Using custom config
 * configureStatusBar({
 *   barStyle: 'light-content',
 *   backgroundColor: '#FF5722',
 *   translucent: false,
 * });
 * ```
 */
export function configureStatusBar(
  config: keyof typeof statusBarConfigs | StatusBarConfig,
): void {
  const statusBarConfig =
    typeof config === 'string' ? statusBarConfigs[config] : config;

  if (!statusBarConfig) {
    return;
  }

  StatusBar.setBarStyle(statusBarConfig.barStyle, true);

  if (Platform.OS === 'android') {
    StatusBar.setBackgroundColor(statusBarConfig.backgroundColor, true);
    StatusBar.setTranslucent(statusBarConfig.translucent);
  }
}

/**
 * Android hardware back button handler type
 */
export type BackHandlerCallback = () => boolean;

/**
 * Platform-specific container padding for safe area
 * Accounts for status bar on Android, used alongside SafeAreaView on iOS
 */
export const containerPadding = Platform.select({
  android: {
    paddingTop: StatusBar.currentHeight ?? 0,
  },
  ios: {
    // iOS uses SafeAreaView, no padding needed here
  },
  default: {},
}) as ViewStyle;

/**
 * Material Design ripple effect configuration
 * Only applicable on Android
 */
interface RippleConfig {
  color: string;
  borderless: boolean;
  radius?: number;
}

/**
 * Get platform-appropriate touchable feedback configuration
 *
 * @param color - Ripple color for Android
 * @param borderless - Whether ripple should extend beyond component bounds
 * @returns Configuration object for TouchableNativeFeedback or null for iOS
 */
export function getTouchableFeedback(
  color = 'rgba(0, 0, 0, 0.1)',
  borderless = false,
): RippleConfig | null {
  if (Platform.OS !== 'android') {
    return null;
  }

  return {
    color,
    borderless,
  };
}

/**
 * Platform-specific font weights
 * Android has limited support for font weights
 */
export const fontWeights = {
  light: Platform.select({
    android: '300' as TextStyle['fontWeight'],
    ios: '300' as TextStyle['fontWeight'],
    default: '300' as TextStyle['fontWeight'],
  }),
  regular: Platform.select({
    android: '400' as TextStyle['fontWeight'],
    ios: '400' as TextStyle['fontWeight'],
    default: '400' as TextStyle['fontWeight'],
  }),
  medium: Platform.select({
    android: '500' as TextStyle['fontWeight'],
    ios: '500' as TextStyle['fontWeight'],
    default: '500' as TextStyle['fontWeight'],
  }),
  semiBold: Platform.select({
    android: '600' as TextStyle['fontWeight'],
    ios: '600' as TextStyle['fontWeight'],
    default: '600' as TextStyle['fontWeight'],
  }),
  bold: Platform.select({
    android: '700' as TextStyle['fontWeight'],
    ios: '700' as TextStyle['fontWeight'],
    default: '700' as TextStyle['fontWeight'],
  }),
};

/**
 * Platform-specific header styles
 * Applies Android-specific adjustments for app headers
 */
export const headerStyles = Platform.select({
  android: {
    elevation: 4,
    shadowOpacity: 0,
    // Android uses elevation instead of shadows
  },
  ios: {
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.1,
    shadowRadius: 4,
  },
  default: {},
}) as ViewStyle;

/**
 * Common platform-specific styles
 */
export const platformStyles = StyleSheet.create({
  /**
   * Container with status bar padding for Android
   */
  safeContainer: Platform.select({
    android: {
      flex: 1,
      paddingTop: StatusBar.currentHeight ?? 0,
    },
    ios: {
      flex: 1,
    },
    default: {
      flex: 1,
    },
  }) as ViewStyle,

  /**
   * Card style with platform-appropriate elevation
   */
  card: Platform.select({
    android: {
      backgroundColor: '#FFFFFF',
      borderRadius: 8,
      elevation: 2,
      marginVertical: 4,
      marginHorizontal: 8,
    },
    ios: {
      backgroundColor: '#FFFFFF',
      borderRadius: 8,
      shadowColor: '#000',
      shadowOffset: {width: 0, height: 1},
      shadowOpacity: 0.1,
      shadowRadius: 2,
      marginVertical: 4,
      marginHorizontal: 8,
    },
    default: {
      backgroundColor: '#FFFFFF',
      borderRadius: 8,
      marginVertical: 4,
      marginHorizontal: 8,
    },
  }) as ViewStyle,

  /**
   * Button style with platform-appropriate elevation
   */
  elevatedButton: Platform.select({
    android: {
      elevation: 2,
      borderRadius: 4,
    },
    ios: {
      shadowColor: '#000',
      shadowOffset: {width: 0, height: 1},
      shadowOpacity: 0.15,
      shadowRadius: 2,
      borderRadius: 4,
    },
    default: {
      borderRadius: 4,
    },
  }) as ViewStyle,

  /**
   * Floating action button style
   */
  fab: Platform.select({
    android: {
      elevation: 6,
      borderRadius: 28,
      width: 56,
      height: 56,
    },
    ios: {
      shadowColor: '#000',
      shadowOffset: {width: 0, height: 3},
      shadowOpacity: 0.2,
      shadowRadius: 4,
      borderRadius: 28,
      width: 56,
      height: 56,
    },
    default: {
      borderRadius: 28,
      width: 56,
      height: 56,
    },
  }) as ViewStyle,

  /**
   * Header/app bar style
   */
  header: Platform.select({
    android: {
      height: 56,
      elevation: 4,
      backgroundColor: '#4A90D9',
    },
    ios: {
      height: 44,
      shadowColor: '#000',
      shadowOffset: {width: 0, height: 1},
      shadowOpacity: 0.1,
      shadowRadius: 2,
      backgroundColor: '#4A90D9',
    },
    default: {
      height: 56,
      backgroundColor: '#4A90D9',
    },
  }) as ViewStyle,

  /**
   * Modal container style
   */
  modal: Platform.select({
    android: {
      elevation: 24,
      borderRadius: 12,
      backgroundColor: '#FFFFFF',
    },
    ios: {
      shadowColor: '#000',
      shadowOffset: {width: 0, height: 8},
      shadowOpacity: 0.25,
      shadowRadius: 16,
      borderRadius: 12,
      backgroundColor: '#FFFFFF',
    },
    default: {
      borderRadius: 12,
      backgroundColor: '#FFFFFF',
    },
  }) as ViewStyle,

  /**
   * Text input container style
   */
  textInputContainer: Platform.select({
    android: {
      // Android text inputs have built-in underline
      borderBottomWidth: 0,
      paddingHorizontal: 0,
    },
    ios: {
      borderBottomWidth: 1,
      borderBottomColor: '#E0E0E0',
      paddingHorizontal: 4,
    },
    default: {
      paddingHorizontal: 4,
    },
  }) as ViewStyle,
});

/**
 * Navigation options for platform-specific header styling
 * Use with React Navigation's screenOptions
 */
export const navigationHeaderOptions = Platform.select({
  android: {
    headerStyle: {
      backgroundColor: '#4A90D9',
      elevation: 4,
    },
    headerTintColor: '#FFFFFF',
    headerTitleStyle: {
      fontWeight: '500' as TextStyle['fontWeight'],
    },
    headerPressColor: 'rgba(255, 255, 255, 0.3)',
  },
  ios: {
    headerStyle: {
      backgroundColor: '#4A90D9',
      shadowColor: '#000',
      shadowOffset: {width: 0, height: 2},
      shadowOpacity: 0.1,
      shadowRadius: 4,
    },
    headerTintColor: '#FFFFFF',
    headerTitleStyle: {
      fontWeight: '600' as TextStyle['fontWeight'],
    },
  },
  default: {
    headerStyle: {
      backgroundColor: '#4A90D9',
    },
    headerTintColor: '#FFFFFF',
    headerTitleStyle: {
      fontWeight: '500' as TextStyle['fontWeight'],
    },
  },
});

/**
 * Select a value based on platform
 * Type-safe wrapper around Platform.select
 *
 * @param config - Object with ios, android, and optional default values
 * @returns The value for the current platform
 */
export function selectByPlatform<T>(config: {
  ios: T;
  android: T;
  default?: T;
}): T {
  return Platform.select({
    ios: config.ios,
    android: config.android,
    default: config.default ?? config.ios,
  }) as T;
}

/**
 * Android-specific back button behavior types
 */
export type AndroidBackBehavior = 'none' | 'history' | 'firstRoute' | 'order';

/**
 * Get default back behavior for navigation
 */
export function getDefaultBackBehavior(): AndroidBackBehavior {
  return Platform.OS === 'android' ? 'history' : 'none';
}

/**
 * Check if device has a notch (approximate check)
 * Useful for layout adjustments
 */
export function hasNotch(): boolean {
  const {height, width} = Dimensions.get('window');
  const aspectRatio = height / width;

  if (Platform.OS === 'ios') {
    // iPhone X and later have aspect ratio > 2
    return aspectRatio > 2;
  }

  // Android: Many modern phones have notches
  // This is an approximation - for precise detection,
  // use react-native-device-info
  return aspectRatio > 2;
}

/**
 * Get safe area insets for current platform
 * Returns estimates - for precise values, use SafeAreaProvider context
 */
export function getSafeAreaInsets() {
  const hasDeviceNotch = hasNotch();

  return {
    top: Platform.select({
      android: StatusBar.currentHeight ?? 24,
      ios: hasDeviceNotch ? 44 : 20,
      default: 0,
    }),
    bottom: Platform.select({
      android: 0,
      ios: hasDeviceNotch ? 34 : 0,
      default: 0,
    }),
    left: 0,
    right: 0,
  };
}
