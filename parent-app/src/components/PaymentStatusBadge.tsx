/**
 * LAYA Parent App - Payment Status Badge Component
 *
 * Displays the payment status of an invoice with appropriate styling
 * and icons for paid, pending, and overdue states.
 *
 * Adapted from parent-portal/components/PaymentStatusBadge.tsx for React Native.
 */

import React from 'react';
import {View, Text, StyleSheet} from 'react-native';

import type {InvoiceStatus} from '../types';

// ============================================================================
// Props Interface
// ============================================================================

interface PaymentStatusBadgeProps {
  status: InvoiceStatus;
  size?: 'sm' | 'md';
}

// ============================================================================
// Configuration
// ============================================================================

interface StatusConfig {
  label: string;
  icon: string;
  backgroundColor: string;
  textColor: string;
}

const statusConfig: Record<InvoiceStatus, StatusConfig> = {
  paid: {
    label: 'Paid',
    icon: '✓',
    backgroundColor: '#DEF7EC',
    textColor: '#03543F',
  },
  pending: {
    label: 'Pending',
    icon: '⏱',
    backgroundColor: '#FDF6B2',
    textColor: '#723B13',
  },
  overdue: {
    label: 'Overdue',
    icon: '⚠',
    backgroundColor: '#FDE8E8',
    textColor: '#9B1C1C',
  },
};

// ============================================================================
// Component
// ============================================================================

/**
 * PaymentStatusBadge - displays the status of an invoice payment.
 */
function PaymentStatusBadge({
  status,
  size = 'md',
}: PaymentStatusBadgeProps): React.JSX.Element {
  const config = statusConfig[status];
  const isSmall = size === 'sm';

  return (
    <View
      style={[
        styles.badge,
        isSmall ? styles.badgeSmall : styles.badgeMedium,
        {backgroundColor: config.backgroundColor},
      ]}>
      <Text
        style={[
          styles.icon,
          isSmall ? styles.iconSmall : styles.iconMedium,
          {color: config.textColor},
        ]}>
        {config.icon}
      </Text>
      <Text
        style={[
          styles.label,
          isSmall ? styles.labelSmall : styles.labelMedium,
          {color: config.textColor},
        ]}>
        {config.label}
      </Text>
    </View>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    borderRadius: 4,
  },
  badgeSmall: {
    paddingHorizontal: 8,
    paddingVertical: 2,
  },
  badgeMedium: {
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  icon: {
    marginRight: 4,
  },
  iconSmall: {
    fontSize: 10,
  },
  iconMedium: {
    fontSize: 12,
  },
  label: {
    fontWeight: '500',
  },
  labelSmall: {
    fontSize: 10,
  },
  labelMedium: {
    fontSize: 12,
  },
});

export default PaymentStatusBadge;
