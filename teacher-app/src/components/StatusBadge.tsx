/**
 * LAYA Teacher App - StatusBadge Component
 *
 * A reusable badge component for displaying attendance and activity statuses.
 * Provides consistent visual feedback for different states across the app.
 */

import React from 'react';
import {StyleSheet, Text, View, ViewStyle, TextStyle} from 'react-native';
import type {AttendanceStatus} from '../types';

type BadgeVariant = AttendanceStatus | 'default';

interface StatusBadgeProps {
  /** The status to display */
  status: BadgeVariant;
  /** Optional custom label (overrides default status text) */
  label?: string;
  /** Optional size variant */
  size?: 'small' | 'medium' | 'large';
  /** Optional additional styles for the container */
  style?: ViewStyle;
}

interface BadgeConfig {
  backgroundColor: string;
  textColor: string;
  label: string;
}

const BADGE_CONFIGS: Record<BadgeVariant, BadgeConfig> = {
  present: {
    backgroundColor: '#E8F5E9',
    textColor: '#2E7D32',
    label: 'Present',
  },
  absent: {
    backgroundColor: '#FFEBEE',
    textColor: '#C62828',
    label: 'Absent',
  },
  late: {
    backgroundColor: '#FFF3E0',
    textColor: '#EF6C00',
    label: 'Late',
  },
  early_pickup: {
    backgroundColor: '#E3F2FD',
    textColor: '#1565C0',
    label: 'Early Pickup',
  },
  default: {
    backgroundColor: '#F5F5F5',
    textColor: '#757575',
    label: 'Unknown',
  },
};

const SIZE_STYLES: Record<'small' | 'medium' | 'large', {container: ViewStyle; text: TextStyle}> = {
  small: {
    container: {
      paddingHorizontal: 6,
      paddingVertical: 2,
      borderRadius: 4,
    },
    text: {
      fontSize: 10,
    },
  },
  medium: {
    container: {
      paddingHorizontal: 8,
      paddingVertical: 4,
      borderRadius: 6,
    },
    text: {
      fontSize: 12,
    },
  },
  large: {
    container: {
      paddingHorizontal: 12,
      paddingVertical: 6,
      borderRadius: 8,
    },
    text: {
      fontSize: 14,
    },
  },
};

/**
 * StatusBadge displays a colored badge indicating the current status
 * of a child's attendance or activity state.
 */
function StatusBadge({
  status,
  label,
  size = 'medium',
  style,
}: StatusBadgeProps): React.JSX.Element {
  const config = BADGE_CONFIGS[status] || BADGE_CONFIGS.default;
  const sizeStyles = SIZE_STYLES[size];

  return (
    <View
      style={[
        styles.container,
        sizeStyles.container,
        {backgroundColor: config.backgroundColor},
        style,
      ]}
      accessibilityRole="text"
      accessibilityLabel={`Status: ${label || config.label}`}>
      <Text style={[styles.text, sizeStyles.text, {color: config.textColor}]}>
        {label || config.label}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignSelf: 'flex-start',
  },
  text: {
    fontWeight: '600',
  },
});

export default StatusBadge;
