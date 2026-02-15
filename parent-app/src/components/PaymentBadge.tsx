/**
 * LAYA Parent App - PaymentBadge Component
 *
 * A badge component for displaying invoice payment status.
 * Shows colored badge with icon for paid, pending, sent, overdue, etc.
 */

import React from 'react';
import {StyleSheet, Text, View} from 'react-native';
import type {InvoiceStatus} from '../types';

interface PaymentBadgeProps {
  /** The payment status to display */
  status: InvoiceStatus;
  /** Size of the badge */
  size?: 'sm' | 'md';
}

/**
 * Status configuration with colors and labels
 */
const statusConfig: Record<
  InvoiceStatus,
  {label: string; backgroundColor: string; textColor: string}
> = {
  paid: {
    label: 'Paid',
    backgroundColor: '#DCFCE7',
    textColor: '#166534',
  },
  sent: {
    label: 'Pending',
    backgroundColor: '#FEF3C7',
    textColor: '#92400E',
  },
  draft: {
    label: 'Draft',
    backgroundColor: '#F3F4F6',
    textColor: '#4B5563',
  },
  overdue: {
    label: 'Overdue',
    backgroundColor: '#FEE2E2',
    textColor: '#991B1B',
  },
  cancelled: {
    label: 'Cancelled',
    backgroundColor: '#F3F4F6',
    textColor: '#6B7280',
  },
};

/**
 * Get the icon for a status
 */
function getStatusIcon(status: InvoiceStatus): string {
  const icons: Record<InvoiceStatus, string> = {
    paid: '\u2713', // Checkmark
    sent: '\u25CF', // Circle
    draft: '\u25CB', // Empty circle
    overdue: '!',
    cancelled: '\u2715', // X
  };
  return icons[status] || '\u25CF';
}

/**
 * PaymentBadge displays a colored badge indicating invoice payment status.
 */
function PaymentBadge({
  status,
  size = 'md',
}: PaymentBadgeProps): React.JSX.Element {
  const config = statusConfig[status] || statusConfig.draft;
  const isSmall = size === 'sm';

  return (
    <View
      style={[
        styles.badge,
        {backgroundColor: config.backgroundColor},
        isSmall && styles.badgeSmall,
      ]}
      accessibilityRole="text"
      accessibilityLabel={`Payment status: ${config.label}`}>
      <Text
        style={[
          styles.icon,
          {color: config.textColor},
          isSmall && styles.iconSmall,
        ]}>
        {getStatusIcon(status)}
      </Text>
      <Text
        style={[
          styles.label,
          {color: config.textColor},
          isSmall && styles.labelSmall,
        ]}>
        {config.label}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  badge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 16,
  },
  badgeSmall: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 12,
  },
  icon: {
    fontSize: 12,
    fontWeight: '700',
    marginRight: 4,
  },
  iconSmall: {
    fontSize: 10,
    marginRight: 3,
  },
  label: {
    fontSize: 13,
    fontWeight: '600',
  },
  labelSmall: {
    fontSize: 11,
  },
});

export default PaymentBadge;
