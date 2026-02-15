/**
 * LAYA Parent App - Invoice Card Component
 *
 * Displays a complete invoice with header, amount, due date,
 * line items, and action buttons for downloading and payment.
 *
 * Adapted from parent-portal/components/InvoiceCard.tsx for React Native.
 */

import React from 'react';
import {
  View,
  Text,
  StyleSheet,
  TouchableOpacity,
  Linking,
  Alert,
} from 'react-native';

import PaymentStatusBadge from './PaymentStatusBadge';
import type {Invoice, InvoiceItem} from '../types';

// ============================================================================
// Props Interface
// ============================================================================

export interface InvoiceCardProps {
  invoice: Invoice;
  onPayPress?: (invoice: Invoice) => void;
}

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Format a number as currency (USD).
 */
function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount);
}

/**
 * Format a date string to a readable format.
 */
function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

/**
 * Calculate the number of days until the due date.
 */
function getDaysUntilDue(dueDate: string): number {
  const due = new Date(dueDate);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  due.setHours(0, 0, 0, 0);
  const diffTime = due.getTime() - today.getTime();
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

/**
 * Get the due date status text.
 */
function getDueDateStatusText(daysUntilDue: number, isPastDue: boolean): string {
  if (isPastDue) {
    const days = Math.abs(daysUntilDue);
    return `${days} day${days !== 1 ? 's' : ''} overdue`;
  }
  if (daysUntilDue === 0) {
    return 'Due today';
  }
  return `${daysUntilDue} day${daysUntilDue !== 1 ? 's' : ''} remaining`;
}

// ============================================================================
// Sub-Components
// ============================================================================

interface InvoiceItemRowProps {
  item: InvoiceItem;
  isLast: boolean;
}

function InvoiceItemRow({item, isLast}: InvoiceItemRowProps): React.JSX.Element {
  return (
    <View style={[styles.itemRow, !isLast && styles.itemRowBorder]}>
      <View style={styles.itemDescription}>
        <Text style={styles.itemDescriptionText} numberOfLines={2}>
          {item.description}
        </Text>
      </View>
      <View style={styles.itemDetails}>
        <Text style={styles.itemQuantity}>{item.quantity}</Text>
        <Text style={styles.itemUnitPrice}>{formatCurrency(item.unitPrice)}</Text>
        <Text style={styles.itemTotal}>{formatCurrency(item.total)}</Text>
      </View>
    </View>
  );
}

// ============================================================================
// Component
// ============================================================================

/**
 * InvoiceCard - displays a complete invoice with all details.
 */
function InvoiceCard({invoice, onPayPress}: InvoiceCardProps): React.JSX.Element {
  const daysUntilDue = getDaysUntilDue(invoice.dueDate);
  const isPastDue = daysUntilDue < 0 && invoice.status !== 'paid';

  const handleDownload = async () => {
    if (!invoice.pdfUrl) {
      Alert.alert('Error', 'PDF is not available for this invoice.');
      return;
    }

    try {
      const canOpen = await Linking.canOpenURL(invoice.pdfUrl);
      if (canOpen) {
        await Linking.openURL(invoice.pdfUrl);
      } else {
        Alert.alert('Error', 'Unable to open the PDF.');
      }
    } catch (error) {
      Alert.alert('Error', 'Failed to open the PDF. Please try again.');
    }
  };

  const handlePayPress = () => {
    if (onPayPress) {
      onPayPress(invoice);
    } else {
      Alert.alert(
        'Payment',
        'Online payment will be available soon.',
        [{text: 'OK'}],
      );
    }
  };

  return (
    <View style={styles.container}>
      {/* Invoice Header */}
      <View style={styles.header}>
        <View style={styles.headerLeft}>
          <View style={styles.iconContainer}>
            <Text style={styles.documentIcon}>📄</Text>
          </View>
          <View style={styles.headerInfo}>
            <Text style={styles.invoiceNumber}>Invoice #{invoice.number}</Text>
            <Text style={styles.issuedDate}>
              Issued: {formatDate(invoice.date)}
            </Text>
          </View>
        </View>
        <PaymentStatusBadge status={invoice.status} />
      </View>

      {/* Amount and Due Date */}
      <View style={styles.amountSection}>
        <View style={styles.amountContainer}>
          <Text style={styles.amountLabel}>Total Amount</Text>
          <Text style={styles.amountValue}>{formatCurrency(invoice.amount)}</Text>
        </View>
        <View style={styles.dueDateContainer}>
          <Text style={styles.dueDateLabel}>Due Date</Text>
          <Text style={[styles.dueDateValue, isPastDue && styles.dueDateOverdue]}>
            {formatDate(invoice.dueDate)}
          </Text>
          {invoice.status !== 'paid' && (
            <Text
              style={[
                styles.dueDateStatus,
                isPastDue && styles.dueDateStatusOverdue,
              ]}>
              {getDueDateStatusText(daysUntilDue, isPastDue)}
            </Text>
          )}
        </View>
      </View>

      {/* Invoice Items */}
      {invoice.items.length > 0 && (
        <View style={styles.itemsSection}>
          <Text style={styles.itemsSectionTitle}>Invoice Details</Text>
          {/* Table Header */}
          <View style={styles.tableHeader}>
            <Text style={[styles.tableHeaderText, styles.headerDescription]}>
              Description
            </Text>
            <View style={styles.tableHeaderDetails}>
              <Text style={styles.tableHeaderText}>Qty</Text>
              <Text style={styles.tableHeaderText}>Price</Text>
              <Text style={styles.tableHeaderText}>Total</Text>
            </View>
          </View>
          {/* Table Body */}
          {invoice.items.map((item, index) => (
            <InvoiceItemRow
              key={index}
              item={item}
              isLast={index === invoice.items.length - 1}
            />
          ))}
          {/* Table Footer */}
          <View style={styles.tableFooter}>
            <Text style={styles.tableFooterLabel}>Total</Text>
            <Text style={styles.tableFooterValue}>
              {formatCurrency(invoice.amount)}
            </Text>
          </View>
        </View>
      )}

      {/* Actions */}
      <View style={styles.actionsSection}>
        <TouchableOpacity
          style={styles.downloadButton}
          onPress={handleDownload}
          activeOpacity={0.7}>
          <Text style={styles.buttonIcon}>⬇️</Text>
          <Text style={styles.downloadButtonText}>Download PDF</Text>
        </TouchableOpacity>

        {invoice.status !== 'paid' && (
          <TouchableOpacity
            style={styles.payButton}
            onPress={handlePayPress}
            activeOpacity={0.7}>
            <Text style={styles.buttonIcon}>💳</Text>
            <Text style={styles.payButtonText}>Pay Now</Text>
          </TouchableOpacity>
        )}
      </View>
    </View>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  container: {
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
    overflow: 'hidden',
  },
  // Header
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 16,
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  headerLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  iconContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#DBEAFE',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  documentIcon: {
    fontSize: 24,
  },
  headerInfo: {
    flex: 1,
  },
  invoiceNumber: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 2,
  },
  issuedDate: {
    fontSize: 14,
    color: '#6B7280',
  },
  // Amount Section
  amountSection: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingVertical: 16,
  },
  amountContainer: {
    flex: 1,
  },
  amountLabel: {
    fontSize: 12,
    color: '#6B7280',
    marginBottom: 4,
  },
  amountValue: {
    fontSize: 24,
    fontWeight: '700',
    color: '#111827',
  },
  dueDateContainer: {
    alignItems: 'flex-end',
  },
  dueDateLabel: {
    fontSize: 12,
    color: '#6B7280',
    marginBottom: 4,
  },
  dueDateValue: {
    fontSize: 14,
    fontWeight: '500',
    color: '#111827',
  },
  dueDateOverdue: {
    color: '#DC2626',
  },
  dueDateStatus: {
    fontSize: 11,
    color: '#6B7280',
    marginTop: 2,
  },
  dueDateStatusOverdue: {
    color: '#EF4444',
  },
  // Items Section
  itemsSection: {
    paddingHorizontal: 16,
    paddingBottom: 16,
  },
  itemsSectionTitle: {
    fontSize: 14,
    fontWeight: '500',
    color: '#111827',
    marginBottom: 12,
  },
  tableHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    backgroundColor: '#F9FAFB',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 4,
    marginBottom: 4,
  },
  tableHeaderText: {
    fontSize: 10,
    fontWeight: '500',
    color: '#6B7280',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
  },
  headerDescription: {
    flex: 1,
  },
  tableHeaderDetails: {
    flexDirection: 'row',
    width: 140,
    justifyContent: 'space-between',
  },
  itemRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 10,
    backgroundColor: '#FFFFFF',
  },
  itemRowBorder: {
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  itemDescription: {
    flex: 1,
    marginRight: 12,
  },
  itemDescriptionText: {
    fontSize: 13,
    color: '#111827',
  },
  itemDetails: {
    flexDirection: 'row',
    width: 140,
    justifyContent: 'space-between',
  },
  itemQuantity: {
    fontSize: 13,
    color: '#6B7280',
    textAlign: 'center',
    width: 30,
  },
  itemUnitPrice: {
    fontSize: 13,
    color: '#6B7280',
    textAlign: 'right',
    width: 50,
  },
  itemTotal: {
    fontSize: 13,
    fontWeight: '500',
    color: '#111827',
    textAlign: 'right',
    width: 50,
  },
  tableFooter: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    alignItems: 'center',
    backgroundColor: '#F9FAFB',
    paddingHorizontal: 12,
    paddingVertical: 10,
    borderRadius: 4,
    marginTop: 4,
  },
  tableFooterLabel: {
    fontSize: 13,
    fontWeight: '500',
    color: '#111827',
    marginRight: 12,
  },
  tableFooterValue: {
    fontSize: 14,
    fontWeight: '700',
    color: '#111827',
    width: 60,
    textAlign: 'right',
  },
  // Actions Section
  actionsSection: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingVertical: 12,
    borderTopWidth: 1,
    borderTopColor: '#F3F4F6',
    gap: 12,
  },
  downloadButton: {
    flex: 1,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 10,
    borderWidth: 1,
    borderColor: '#D1D5DB',
    borderRadius: 8,
    backgroundColor: '#FFFFFF',
  },
  downloadButtonText: {
    fontSize: 14,
    fontWeight: '500',
    color: '#4B5563',
    marginLeft: 6,
  },
  payButton: {
    flex: 1,
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    paddingVertical: 10,
    borderRadius: 8,
    backgroundColor: '#2563EB',
  },
  payButtonText: {
    fontSize: 14,
    fontWeight: '500',
    color: '#FFFFFF',
    marginLeft: 6,
  },
  buttonIcon: {
    fontSize: 14,
  },
});

export default InvoiceCard;
