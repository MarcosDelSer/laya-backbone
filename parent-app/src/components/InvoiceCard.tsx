/**
 * LAYA Parent App - InvoiceCard Component
 *
 * A card component for displaying individual invoices with
 * amount, due date, status, line items, and download action.
 */

import React, {useState} from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
  ActivityIndicator,
  Alert,
} from 'react-native';
import type {Invoice} from '../types';
import PaymentBadge from './PaymentBadge';
import {
  formatCurrency,
  formatInvoiceDate,
  getDaysUntilDue,
  downloadInvoicePdf,
} from '../api/invoiceApi';

interface InvoiceCardProps {
  /** The invoice data to display */
  invoice: Invoice;
  /** Optional callback when the card is pressed */
  onPress?: (invoice: Invoice) => void;
}

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  background: '#FFFFFF',
  text: '#333333',
  textSecondary: '#666666',
  textLight: '#999999',
  border: '#E0E0E0',
  error: '#DC2626',
  tableHeader: '#F9FAFB',
  tableBorder: '#E5E7EB',
};

/**
 * InvoiceCard displays a single invoice with its details and actions.
 */
function InvoiceCard({
  invoice,
  onPress,
}: InvoiceCardProps): React.JSX.Element {
  const [isDownloading, setIsDownloading] = useState(false);
  const daysUntilDue = getDaysUntilDue(invoice.dueDate);
  const isPastDue = daysUntilDue < 0 && invoice.status !== 'paid';

  const handlePress = () => {
    if (onPress) {
      onPress(invoice);
    }
  };

  const handleDownloadPdf = async () => {
    if (!invoice.pdfUrl || isDownloading) {
      return;
    }

    setIsDownloading(true);
    try {
      await downloadInvoicePdf(invoice.id);
    } catch {
      Alert.alert(
        'Download Failed',
        'Unable to download the invoice PDF. Please try again later.',
        [{text: 'OK'}],
      );
    } finally {
      setIsDownloading(false);
    }
  };

  /**
   * Render the due date status text
   */
  const renderDueDateStatus = (): React.JSX.Element | null => {
    if (invoice.status === 'paid' || invoice.status === 'cancelled') {
      return null;
    }

    let statusText: string;
    if (isPastDue) {
      const days = Math.abs(daysUntilDue);
      statusText = `${days} day${days !== 1 ? 's' : ''} overdue`;
    } else if (daysUntilDue === 0) {
      statusText = 'Due today';
    } else {
      statusText = `${daysUntilDue} day${daysUntilDue !== 1 ? 's' : ''} remaining`;
    }

    return (
      <Text style={[styles.dueDateStatus, isPastDue && styles.dueDateOverdue]}>
        {statusText}
      </Text>
    );
  };

  const CardContent = (
    <View style={styles.card}>
      {/* Invoice Header */}
      <View style={styles.header}>
        <View style={styles.headerLeft}>
          <View style={styles.iconContainer}>
            <Text style={styles.iconText}>📄</Text>
          </View>
          <View style={styles.headerInfo}>
            <Text style={styles.invoiceNumber}>
              Invoice #{invoice.invoiceNumber}
            </Text>
            <Text style={styles.issueDate}>
              Issued: {formatInvoiceDate(invoice.issueDate)}
            </Text>
          </View>
        </View>
        <PaymentBadge status={invoice.status} />
      </View>

      {/* Amount and Due Date */}
      <View style={styles.amountRow}>
        <View style={styles.amountSection}>
          <Text style={styles.amountLabel}>Total Amount</Text>
          <Text style={styles.amount}>
            {formatCurrency(invoice.amount, invoice.currency)}
          </Text>
        </View>
        <View style={styles.dueDateSection}>
          <Text style={styles.dueDateLabel}>Due Date</Text>
          <Text style={[styles.dueDate, isPastDue && styles.dueDateOverdue]}>
            {formatInvoiceDate(invoice.dueDate)}
          </Text>
          {renderDueDateStatus()}
        </View>
      </View>

      {/* Invoice Items */}
      {invoice.items.length > 0 && (
        <View style={styles.itemsSection}>
          <Text style={styles.itemsHeader}>Invoice Details</Text>
          <View style={styles.table}>
            {/* Table Header */}
            <View style={styles.tableRow}>
              <Text style={[styles.tableCell, styles.tableHeaderCell, styles.descriptionCell]}>
                Description
              </Text>
              <Text style={[styles.tableCell, styles.tableHeaderCell, styles.qtyCell]}>
                Qty
              </Text>
              <Text style={[styles.tableCell, styles.tableHeaderCell, styles.priceCell]}>
                Price
              </Text>
              <Text style={[styles.tableCell, styles.tableHeaderCell, styles.totalCell]}>
                Total
              </Text>
            </View>
            {/* Table Rows */}
            {invoice.items.map((item) => (
              <View key={item.id} style={styles.tableRow}>
                <Text
                  style={[styles.tableCell, styles.descriptionCell]}
                  numberOfLines={2}>
                  {item.description}
                </Text>
                <Text style={[styles.tableCell, styles.qtyCell]}>
                  {item.quantity}
                </Text>
                <Text style={[styles.tableCell, styles.priceCell]}>
                  {formatCurrency(item.unitPrice, invoice.currency)}
                </Text>
                <Text style={[styles.tableCell, styles.totalCell, styles.tableCellBold]}>
                  {formatCurrency(item.total, invoice.currency)}
                </Text>
              </View>
            ))}
            {/* Table Footer - Total */}
            <View style={[styles.tableRow, styles.tableFooter]}>
              <Text
                style={[
                  styles.tableCell,
                  styles.descriptionCell,
                  styles.tableCellBold,
                ]}>
                Total
              </Text>
              <Text style={[styles.tableCell, styles.qtyCell]} />
              <Text style={[styles.tableCell, styles.priceCell]} />
              <Text
                style={[
                  styles.tableCell,
                  styles.totalCell,
                  styles.tableCellBold,
                ]}>
                {formatCurrency(invoice.amount, invoice.currency)}
              </Text>
            </View>
          </View>
        </View>
      )}

      {/* Actions */}
      <View style={styles.actions}>
        <TouchableOpacity
          style={styles.downloadButton}
          onPress={handleDownloadPdf}
          disabled={!invoice.pdfUrl || isDownloading}
          accessibilityRole="button"
          accessibilityLabel="Download invoice PDF">
          {isDownloading ? (
            <ActivityIndicator size="small" color={COLORS.primary} />
          ) : (
            <>
              <Text style={styles.downloadIcon}>⬇</Text>
              <Text style={styles.downloadText}>Download PDF</Text>
            </>
          )}
        </TouchableOpacity>
      </View>
    </View>
  );

  if (onPress) {
    return (
      <TouchableOpacity
        onPress={handlePress}
        activeOpacity={0.7}
        accessibilityRole="button"
        accessibilityLabel={`Invoice ${invoice.invoiceNumber}, ${formatCurrency(invoice.amount)}`}>
        {CardContent}
      </TouchableOpacity>
    );
  }

  return CardContent;
}

const styles = StyleSheet.create({
  card: {
    backgroundColor: COLORS.background,
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginVertical: 8,
    // iOS shadow
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.1,
    shadowRadius: 4,
    // Android elevation
    elevation: 3,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 16,
  },
  headerLeft: {
    flexDirection: 'row',
    alignItems: 'center',
    flex: 1,
  },
  iconContainer: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: '#EBF5FF',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  iconText: {
    fontSize: 20,
  },
  headerInfo: {
    flex: 1,
  },
  invoiceNumber: {
    fontSize: 16,
    fontWeight: '600',
    color: COLORS.text,
    marginBottom: 2,
  },
  issueDate: {
    fontSize: 13,
    color: COLORS.textSecondary,
  },
  amountRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    marginBottom: 16,
    paddingBottom: 16,
    borderBottomWidth: 1,
    borderBottomColor: COLORS.border,
  },
  amountSection: {
    flex: 1,
  },
  amountLabel: {
    fontSize: 12,
    color: COLORS.textLight,
    marginBottom: 4,
  },
  amount: {
    fontSize: 22,
    fontWeight: '700',
    color: COLORS.text,
  },
  dueDateSection: {
    alignItems: 'flex-end',
  },
  dueDateLabel: {
    fontSize: 12,
    color: COLORS.textLight,
    marginBottom: 4,
  },
  dueDate: {
    fontSize: 15,
    fontWeight: '600',
    color: COLORS.text,
  },
  dueDateOverdue: {
    color: COLORS.error,
  },
  dueDateStatus: {
    fontSize: 11,
    color: COLORS.textLight,
    marginTop: 2,
  },
  itemsSection: {
    marginBottom: 16,
  },
  itemsHeader: {
    fontSize: 14,
    fontWeight: '600',
    color: COLORS.text,
    marginBottom: 10,
  },
  table: {
    borderWidth: 1,
    borderColor: COLORS.tableBorder,
    borderRadius: 8,
    overflow: 'hidden',
  },
  tableRow: {
    flexDirection: 'row',
    borderBottomWidth: 1,
    borderBottomColor: COLORS.tableBorder,
  },
  tableCell: {
    paddingHorizontal: 8,
    paddingVertical: 8,
    fontSize: 12,
    color: COLORS.textSecondary,
  },
  tableHeaderCell: {
    backgroundColor: COLORS.tableHeader,
    fontWeight: '600',
    fontSize: 11,
    color: COLORS.textLight,
    textTransform: 'uppercase',
  },
  tableCellBold: {
    fontWeight: '600',
    color: COLORS.text,
  },
  tableFooter: {
    backgroundColor: COLORS.tableHeader,
    borderBottomWidth: 0,
  },
  descriptionCell: {
    flex: 2,
  },
  qtyCell: {
    width: 40,
    textAlign: 'center',
  },
  priceCell: {
    width: 70,
    textAlign: 'right',
  },
  totalCell: {
    width: 70,
    textAlign: 'right',
  },
  actions: {
    flexDirection: 'row',
    justifyContent: 'flex-start',
  },
  downloadButton: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 10,
    borderRadius: 8,
    borderWidth: 1,
    borderColor: COLORS.border,
    backgroundColor: COLORS.background,
  },
  downloadIcon: {
    fontSize: 14,
    marginRight: 6,
    color: COLORS.primary,
  },
  downloadText: {
    fontSize: 14,
    fontWeight: '500',
    color: COLORS.primary,
  },
});

export default InvoiceCard;
