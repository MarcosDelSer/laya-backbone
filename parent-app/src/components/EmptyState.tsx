/**
 * LAYA Parent App - EmptyState Component
 *
 * A reusable empty state component that provides consistent
 * messaging when no data is available with optional action button.
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

interface EmptyStateProps {
  /** The main message to display */
  message: string;
  /** Optional title for the empty state */
  title?: string;
  /** Optional icon/emoji to display */
  icon?: string;
  /** Optional label for action button */
  actionLabel?: string;
  /** Optional callback for action button */
  onAction?: () => void;
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
  white: '#FFFFFF',
  text: '#333333',
  textSecondary: '#666666',
  background: '#F5F5F5',
  iconBackground: '#E8F4FD',
};

// ============================================================================
// Component
// ============================================================================

/**
 * EmptyState displays a placeholder when no data is available.
 * Used consistently across all screens for empty states.
 */
function EmptyState({
  message,
  title = 'No items found',
  icon = '📭',
  actionLabel,
  onAction,
  fullScreen = false,
  style,
}: EmptyStateProps): React.JSX.Element {
  return (
    <View
      style={[
        styles.container,
        fullScreen && styles.fullScreen,
        style,
      ]}
      accessibilityRole="text"
      accessibilityLabel={`${title}: ${message}`}>
      <View style={styles.iconContainer}>
        <Text style={styles.icon}>{icon}</Text>
      </View>
      <Text style={styles.title}>{title}</Text>
      <Text style={styles.message}>{message}</Text>
      {actionLabel && onAction && (
        <TouchableOpacity
          style={styles.actionButton}
          onPress={onAction}
          activeOpacity={0.8}
          accessibilityRole="button"
          accessibilityLabel={actionLabel}>
          <Text style={styles.actionButtonText}>{actionLabel}</Text>
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
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: COLORS.iconBackground,
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  icon: {
    fontSize: 32,
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
  actionButton: {
    marginTop: 20,
    backgroundColor: COLORS.primary,
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
  },
  actionButtonText: {
    color: COLORS.white,
    fontSize: 16,
    fontWeight: '600',
  },
});

export default EmptyState;
