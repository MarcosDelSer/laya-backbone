/**
 * LAYA Parent App - InvoicesScreen
 *
 * Main screen displaying a list of invoices with summary statistics,
 * status filters, and PDF download functionality.
 * Pull-to-refresh to update invoice list.
 */

import React, {useState, useCallback, useEffect} from 'react';
import {
  StyleSheet,
  Text,
  View,
  FlatList,
  RefreshControl,
  ActivityIndicator,
  TouchableOpacity,
} from 'react-native';
import InvoiceCard from '../components/InvoiceCard';
import PaymentBadge from '../components/PaymentBadge';
import {
  fetchInvoices,
  getMockInvoiceData,
  formatCurrency,
  calculateSummary,
} from '../api/invoiceApi';
import type {Invoice, InvoiceStatus} from '../types';
import type {InvoiceSummary} from '../api/invoiceApi';

/**
 * Status filter options
 */
type FilterStatus = 'all' | InvoiceStatus;

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  background: '#F5F5F5',
  cardBackground: '#FFFFFF',
  text: '#333333',
  textSecondary: '#666666',
  textLight: '#999999',
  border: '#E0E0E0',
  success: '#16A34A',
  warning: '#D97706',
  error: '#DC2626',
};

/**
 * InvoicesScreen displays all invoices with summary and filtering
 */
function InvoicesScreen(): React.JSX.Element {
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [summary, setSummary] = useState<InvoiceSummary | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<FilterStatus>('all');

  /**
   * Load invoices from API
   */
  const loadInvoices = useCallback(async (showRefreshIndicator = false) => {
    if (showRefreshIndicator) {
      setIsRefreshing(true);
    } else {
      setIsLoading(true);
    }
    setError(null);

    try {
      const response = await fetchInvoices();

      if (response.success && response.data) {
        setInvoices(response.data.invoices);
        setSummary(response.data.summary);
      } else {
        // Use mock data for development
        const mockData = getMockInvoiceData();
        setInvoices(mockData.invoices);
        setSummary(mockData.summary);
      }
    } catch {
      // Use mock data for development when API is not available
      const mockData = getMockInvoiceData();
      setInvoices(mockData.invoices);
      setSummary(mockData.summary);
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, []);

  /**
   * Initial load
   */
  useEffect(() => {
    loadInvoices();
  }, [loadInvoices]);

  /**
   * Handle pull-to-refresh
   */
  const handleRefresh = useCallback(() => {
    loadInvoices(true);
  }, [loadInvoices]);

  /**
   * Handle invoice card press
   */
  const handleInvoicePress = useCallback((invoice: Invoice) => {
    // Navigate to invoice detail - to be implemented
    // For now, this is a placeholder for future functionality
  }, []);

  /**
   * Filter invoices by status
   */
  const getFilteredInvoices = useCallback((): Invoice[] => {
    if (filter === 'all') {
      return invoices;
    }
    // Map 'sent' status to 'pending' for filter purposes
    if (filter === 'sent') {
      return invoices.filter(inv => inv.status === 'sent' || inv.status === 'draft');
    }
    return invoices.filter(inv => inv.status === filter);
  }, [invoices, filter]);

  /**
   * Get count for a filter status
   */
  const getFilterCount = (status: FilterStatus): number => {
    if (status === 'all') {
      return invoices.length;
    }
    if (status === 'sent') {
      return invoices.filter(inv => inv.status === 'sent' || inv.status === 'draft').length;
    }
    return invoices.filter(inv => inv.status === status).length;
  };

  /**
   * Render summary cards
   */
  const renderSummary = (): React.JSX.Element | null => {
    if (!summary) {
      return null;
    }

    return (
      <View style={styles.summaryContainer}>
        {/* Pending Card */}
        <View style={styles.summaryCard}>
          <View style={styles.summaryCardContent}>
            <View>
              <Text style={styles.summaryLabel}>Pending</Text>
              <Text style={[styles.summaryAmount, {color: COLORS.warning}]}>
                {formatCurrency(summary.totalPending)}
              </Text>
              <Text style={styles.summaryCount}>
                {summary.pendingCount} invoice{summary.pendingCount !== 1 ? 's' : ''}
              </Text>
            </View>
            <View style={[styles.summaryIcon, {backgroundColor: '#FEF3C7'}]}>
              <Text style={styles.summaryIconText}>⏱</Text>
            </View>
          </View>
        </View>

        {/* Overdue Card */}
        <View style={styles.summaryCard}>
          <View style={styles.summaryCardContent}>
            <View>
              <Text style={styles.summaryLabel}>Overdue</Text>
              <Text style={[styles.summaryAmount, {color: COLORS.error}]}>
                {formatCurrency(summary.totalOverdue)}
              </Text>
              <Text style={styles.summaryCount}>
                {summary.overdueCount} invoice{summary.overdueCount !== 1 ? 's' : ''}
              </Text>
            </View>
            <View style={[styles.summaryIcon, {backgroundColor: '#FEE2E2'}]}>
              <Text style={styles.summaryIconText}>!</Text>
            </View>
          </View>
        </View>

        {/* Paid Card */}
        <View style={styles.summaryCard}>
          <View style={styles.summaryCardContent}>
            <View>
              <Text style={styles.summaryLabel}>Paid</Text>
              <Text style={[styles.summaryAmount, {color: COLORS.success}]}>
                {formatCurrency(summary.totalPaid)}
              </Text>
              <Text style={styles.summaryCount}>
                {summary.paidCount} invoice{summary.paidCount !== 1 ? 's' : ''}
              </Text>
            </View>
            <View style={[styles.summaryIcon, {backgroundColor: '#DCFCE7'}]}>
              <Text style={styles.summaryIconText}>{'\u2713'}</Text>
            </View>
          </View>
        </View>
      </View>
    );
  };

  /**
   * Render filter pills
   */
  const renderFilters = (): React.JSX.Element => (
    <View style={styles.filterContainer}>
      <TouchableOpacity
        style={[styles.filterPill, filter === 'all' && styles.filterPillActive]}
        onPress={() => setFilter('all')}
        accessibilityRole="button"
        accessibilityLabel={`All invoices, ${getFilterCount('all')}`}
        accessibilityState={{selected: filter === 'all'}}>
        <Text style={[styles.filterText, filter === 'all' && styles.filterTextActive]}>
          All ({getFilterCount('all')})
        </Text>
      </TouchableOpacity>

      {summary && summary.pendingCount > 0 && (
        <TouchableOpacity
          style={[styles.filterPill, filter === 'sent' && styles.filterPillActive]}
          onPress={() => setFilter('sent')}
          accessibilityRole="button"
          accessibilityLabel={`Pending invoices, ${getFilterCount('sent')}`}
          accessibilityState={{selected: filter === 'sent'}}>
          <PaymentBadge status="sent" size="sm" />
          <Text style={styles.filterCount}>({getFilterCount('sent')})</Text>
        </TouchableOpacity>
      )}

      {summary && summary.overdueCount > 0 && (
        <TouchableOpacity
          style={[styles.filterPill, filter === 'overdue' && styles.filterPillActive]}
          onPress={() => setFilter('overdue')}
          accessibilityRole="button"
          accessibilityLabel={`Overdue invoices, ${getFilterCount('overdue')}`}
          accessibilityState={{selected: filter === 'overdue'}}>
          <PaymentBadge status="overdue" size="sm" />
          <Text style={styles.filterCount}>({getFilterCount('overdue')})</Text>
        </TouchableOpacity>
      )}

      {summary && summary.paidCount > 0 && (
        <TouchableOpacity
          style={[styles.filterPill, filter === 'paid' && styles.filterPillActive]}
          onPress={() => setFilter('paid')}
          accessibilityRole="button"
          accessibilityLabel={`Paid invoices, ${getFilterCount('paid')}`}
          accessibilityState={{selected: filter === 'paid'}}>
          <PaymentBadge status="paid" size="sm" />
          <Text style={styles.filterCount}>({getFilterCount('paid')})</Text>
        </TouchableOpacity>
      )}
    </View>
  );

  /**
   * Render list header with summary and filters
   */
  const renderListHeader = (): React.JSX.Element => (
    <View>
      {/* Header */}
      <View style={styles.header}>
        <Text style={styles.title}>Invoices</Text>
        <Text style={styles.subtitle}>View and manage your billing history</Text>
      </View>

      {/* Summary Cards */}
      {renderSummary()}

      {/* Section Title */}
      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>Payment History</Text>
      </View>

      {/* Filters */}
      {renderFilters()}
    </View>
  );

  /**
   * Render empty state
   */
  const renderEmptyState = (): React.JSX.Element => (
    <View style={styles.emptyState}>
      <View style={styles.emptyIcon}>
        <Text style={styles.emptyIconText}>📄</Text>
      </View>
      <Text style={styles.emptyTitle}>No invoices yet</Text>
      <Text style={styles.emptyText}>
        Your invoices will appear here once they are generated.
      </Text>
    </View>
  );

  /**
   * Render an invoice item
   */
  const renderInvoiceItem = ({item}: {item: Invoice}): React.JSX.Element => (
    <InvoiceCard invoice={item} onPress={handleInvoicePress} />
  );

  /**
   * Key extractor for list items
   */
  const keyExtractor = useCallback((item: Invoice) => item.id, []);

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
        <Text style={styles.loadingText}>Loading invoices...</Text>
      </View>
    );
  }

  if (error) {
    return (
      <View style={styles.errorContainer}>
        <Text style={styles.errorIcon}>!</Text>
        <Text style={styles.errorTitle}>Something went wrong</Text>
        <Text style={styles.errorText}>{error}</Text>
      </View>
    );
  }

  const filteredInvoices = getFilteredInvoices();

  return (
    <View style={styles.container}>
      <FlatList
        data={filteredInvoices}
        renderItem={renderInvoiceItem}
        keyExtractor={keyExtractor}
        ListHeaderComponent={renderListHeader}
        ListEmptyComponent={renderEmptyState}
        contentContainerStyle={styles.listContent}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing}
            onRefresh={handleRefresh}
            tintColor={COLORS.primary}
            colors={[COLORS.primary]}
          />
        }
      />
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: COLORS.background,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: COLORS.textSecondary,
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: COLORS.background,
    padding: 20,
  },
  errorIcon: {
    fontSize: 32,
    fontWeight: '700',
    color: '#C62828',
    marginBottom: 12,
  },
  errorTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: COLORS.text,
    marginBottom: 8,
  },
  errorText: {
    fontSize: 14,
    color: COLORS.textSecondary,
    textAlign: 'center',
  },
  listContent: {
    paddingBottom: 24,
  },
  header: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 12,
  },
  title: {
    fontSize: 24,
    fontWeight: '700',
    color: COLORS.text,
    marginBottom: 4,
  },
  subtitle: {
    fontSize: 14,
    color: COLORS.textSecondary,
  },
  summaryContainer: {
    paddingHorizontal: 16,
    marginBottom: 16,
  },
  summaryCard: {
    backgroundColor: COLORS.cardBackground,
    borderRadius: 12,
    padding: 16,
    marginBottom: 8,
    borderWidth: 1,
    borderColor: COLORS.border,
    // iOS shadow
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.05,
    shadowRadius: 2,
    // Android elevation
    elevation: 1,
  },
  summaryCardContent: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
  },
  summaryLabel: {
    fontSize: 13,
    color: COLORS.textLight,
    marginBottom: 4,
  },
  summaryAmount: {
    fontSize: 22,
    fontWeight: '700',
  },
  summaryCount: {
    fontSize: 11,
    color: COLORS.textLight,
    marginTop: 2,
  },
  summaryIcon: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
  },
  summaryIconText: {
    fontSize: 18,
    fontWeight: '600',
  },
  sectionHeader: {
    paddingHorizontal: 16,
    paddingTop: 8,
    paddingBottom: 8,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: COLORS.text,
  },
  filterContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    paddingHorizontal: 16,
    paddingBottom: 12,
    gap: 8,
  },
  filterPill: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 12,
    paddingVertical: 8,
    borderRadius: 16,
    backgroundColor: COLORS.cardBackground,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  filterPillActive: {
    backgroundColor: '#EBF5FF',
    borderColor: COLORS.primary,
  },
  filterText: {
    fontSize: 13,
    fontWeight: '500',
    color: COLORS.textSecondary,
  },
  filterTextActive: {
    color: COLORS.primary,
    fontWeight: '600',
  },
  filterCount: {
    fontSize: 11,
    color: COLORS.textLight,
    marginLeft: 4,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    marginTop: 40,
  },
  emptyIcon: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  emptyIconText: {
    fontSize: 28,
  },
  emptyTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: COLORS.text,
    marginBottom: 8,
  },
  emptyText: {
    fontSize: 14,
    color: COLORS.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
  },
});

export default InvoicesScreen;
