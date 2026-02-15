/**
 * LAYA Parent App - LoadingSpinner Component
 *
 * A reusable loading indicator component that provides consistent
 * loading states across the app with customizable size and colors.
 */

import React from 'react';
import {StyleSheet, View, ActivityIndicator, Text, ViewStyle} from 'react-native';

// ============================================================================
// Props Interface
// ============================================================================

interface LoadingSpinnerProps {
  /** Optional message to display below the spinner */
  message?: string;
  /** Size of the spinner */
  size?: 'small' | 'large';
  /** Custom color for the spinner */
  color?: string;
  /** Whether to fill the container */
  fullScreen?: boolean;
  /** Optional additional styles for the container */
  style?: ViewStyle;
}

// ============================================================================
// Configuration
// ============================================================================

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  text: '#666666',
  background: '#F5F5F5',
};

// ============================================================================
// Component
// ============================================================================

/**
 * LoadingSpinner displays a centered loading indicator with optional message.
 * Used consistently across all screens for loading states.
 */
function LoadingSpinner({
  message,
  size = 'large',
  color = COLORS.primary,
  fullScreen = false,
  style,
}: LoadingSpinnerProps): React.JSX.Element {
  return (
    <View
      style={[
        styles.container,
        fullScreen && styles.fullScreen,
        style,
      ]}
      accessibilityRole="progressbar"
      accessibilityLabel={message || 'Loading'}
      accessibilityState={{busy: true}}>
      <ActivityIndicator size={size} color={color} />
      {message && <Text style={styles.message}>{message}</Text>}
    </View>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  container: {
    justifyContent: 'center',
    alignItems: 'center',
    padding: 20,
  },
  fullScreen: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  message: {
    marginTop: 12,
    fontSize: 16,
    color: COLORS.text,
    textAlign: 'center',
  },
});

export default LoadingSpinner;
