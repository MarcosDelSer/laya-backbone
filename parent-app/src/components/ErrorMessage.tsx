/**
 * LAYA Parent App - ErrorMessage Component
 *
 * A reusable error display component that provides consistent
 * error handling UI across the app with retry functionality.
 */

import React from 'react';
import {
  StyleSheet,
  View,
  Text,
  TouchableOpacity,
  ViewStyle,
} from 'react-native';

// ============================================================================
// Props Interface
// ============================================================================

interface ErrorMessageProps {
  /** The error message to display */
  message: string;
  /** Optional title for the error */
  title?: string;
  /** Optional callback for retry action */
  onRetry?: () => void;
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
  error: '#C62828',
  errorBackground: '#FFEBEE',
  primary: '#4A90D9',
  white: '#FFFFFF',
  text: '#333333',
  textSecondary: '#666666',
  background: '#F5F5F5',
};

// ============================================================================
// Component
// ============================================================================

/**
 * ErrorMessage displays an error state with optional retry button.
 * Used consistently across all screens for error handling.
 */
function ErrorMessage({
  message,
  title = 'Something went wrong',
  onRetry,
  fullScreen = false,
  style,
}: ErrorMessageProps): React.JSX.Element {
  return (
    <View
      style={[
        styles.container,
        fullScreen && styles.fullScreen,
        style,
      ]}
      accessibilityRole="alert"
      accessibilityLabel={`${title}: ${message}`}>
      <View style={styles.iconContainer}>
        <Text style={styles.icon}>!</Text>
      </View>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.message}>{message}</Text>
      {onRetry && (
        <TouchableOpacity
          style={styles.retryButton}
          onPress={onRetry}
          activeOpacity={0.8}
          accessibilityRole="button"
          accessibilityLabel="Retry">
          <Text style={styles.retryButtonText}>Try Again</Text>
        </TouchableOpacity>
      )}
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
  iconContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: COLORS.errorBackground,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  icon: {
    fontSize: 24,
    fontWeight: '700',
    color: COLORS.error,
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
    color: COLORS.text,
    marginBottom: 8,
    textAlign: 'center',
  },
  message: {
    fontSize: 14,
    color: COLORS.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
    maxWidth: 280,
  },
  retryButton: {
    marginTop: 20,
    backgroundColor: COLORS.primary,
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
  },
  retryButtonText: {
    color: COLORS.white,
    fontSize: 16,
    fontWeight: '600',
  },
});

export default ErrorMessage;
