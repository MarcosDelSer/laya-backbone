/**
 * LAYA Parent App - Invoices Screen
 *
 * Displays invoice listing with summary statistics, status filtering,
 * and payment status badges. Parents can view invoices, see payment
 * status, and access PDF downloads.
 *
 * Adapted from parent-portal/app/invoices/page.tsx for React Native.
 */

import React, {useEffect, useCallback, useMemo, useState} from 'react';
import {
  SafeAreaView,
  View,
  Text,
  FlatList,
  RefreshControl,
  StyleSheet,
  ActivityIndicator,
  TouchableOpacity,
  ScrollView,
} from 'react-native';

import type {InvoicesScreenProps} from '../types/navigation';
import type {Invoice, InvoiceStatus} from '../types';
import {useRefresh} from '../hooks/useRefresh';
import InvoiceCard from '../components/InvoiceCard';
import PaymentStatusBadge from '../components/PaymentStatusBadge';

// ============================================================================
// Mock Data (for development until API is connected)
// ============================================================================

const mockInvoices: Invoice[] = [
  {
    id: 'inv-001',
    number: 'INV-2026-001',
    date: '2026-02-01',
    dueDate: '2026-02-15',
    amount: 1250.0,
    status: 'pending',
    pdfUrl: '/invoices/INV-2026-001.pdf',
    items: [
      {
        description: 'Monthly Tuition - February 2026',
        quantity: 1,
        unitPrice: 1100.0,
        total: 1100.0,
      },
      {
        description: 'Lunch Program',
        quantity: 1,
        unitPrice: 100.0,
        total: 100.0,
      },
      {
        description: 'Activity Fee',
        quantity: 1,
        unitPrice: 50.0,
        total: 50.0,
      },
    ],
  },
  {
    id: 'inv-002',
    number: 'INV-2026-002',
    date: '2026-01-01',
    dueDate: '2026-01-15',
    amount: 1250.0,
    status: 'paid',
    pdfUrl: '/invoices/INV-2026-002.pdf',
    items: [
      {
        description: 'Monthly Tuition - January 2026',
        quantity: 1,
        unitPrice: 1100.0,
        total: 1100.0,
      },
      {
        description: 'Lunch Program',
        quantity: 1,
        unitPrice: 100.0,
        total: 100.0,
      },
      {
        description: 'Activity Fee',
        quantity: 1,
        unitPrice: 50.0,
        total: 50.0,
      },
    ],
  },
  {
    id: 'inv-003',
    number: 'INV-2025-012',
    date: '2025-12-01',
    dueDate: '2025-12-15',
    amount: 1350.0,
    status: 'paid',
    pdfUrl: '/invoices/INV-2025-012.pdf',
    items: [
      {
        description: 'Monthly Tuition - December 2025',
        quantity: 1,
        unitPrice: 1100.0,
        total: 1100.0,
      },
      {
        description: 'Lunch Program',
        quantity: 1,
        unitPrice: 100.0,
        total: 100.0,
      },
      {
        description: 'Activity Fee',
        quantity: 1,
        unitPrice: 50.0,
        total: 50.0,
      },
      {
        description: 'Holiday Party Contribution',
        quantity: 1,
        unitPrice: 100.0,
        total: 100.0,
      },
    ],
  },
  {
    id: 'inv-004',
    number: 'INV-2025-011',
    date: '2025-11-01',
    dueDate: '2025-11-15',
    amount: 1250.0,
    status: 'paid',
    pdfUrl: '/invoices/INV-2025-011.pdf',
    items: [
      {
        description: 'Monthly Tuition - November 2025',
        quantity: 1,
        unitPrice: 1100.0,
        total: 1100.0,
      },
      {
        description: 'Lunch Program',
        quantity: 1,
        unitPrice: 100.0,
        total: 100.0,
      },
      {
        description: 'Activity Fee',
        quantity: 1,
        unitPrice: 50.0,
        total: 50.0,
      },
    ],
  },
];

// ============================================================================
// Helper Functions
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
 * Fetches invoices data.
 * Uses mock data in development, will connect to API in production.
 */
async function fetchInvoices(): Promise<Invoice[]> {
  // TODO: Replace with actual API call when backend is connected
  // Simulate network delay for realistic UX
  await new Promise(resolve => setTimeout(resolve, 800));

  // Return mock data sorted by date (most recent first)
  return [...mockInvoices].sort(
    (a, b) => new Date(b.date).getTime() - new Date(a.date).getTime(),
  );
}

// ============================================================================
// Types
// ============================================================================

type FilterStatus = 'all' | InvoiceStatus;

interface InvoiceSummary {
  totalPaid: number;
  totalPending: number;
  totalOverdue: number;
  paidCount: number;
  pendingCount: number;
  overdueCount: number;
}

// ============================================================================
// Sub-components
// ============================================================================

interface HeaderProps {
  subtitle: string;
}

/**
 * Header component with title and subtitle.
 */
function Header({subtitle}: HeaderProps): React.JSX.Element {
  return (
    <View style={headerStyles.container}>
      <Text style={headerStyles.title}>Invoices</Text>
      <Text style={headerStyles.subtitle}>{subtitle}</Text>
    </View>
  );
}

const headerStyles = StyleSheet.create({
  container: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 12,
    backgroundColor: '#FFFFFF',
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
    color: '#111827',
    marginBottom: 4,
  },
  subtitle: {
    fontSize: 15,
    color: '#6B7280',
  },
});

interface SummaryCardsProps {
  summary: InvoiceSummary;
}

/**
 * Summary cards showing totals for pending, overdue, and paid invoices.
 */
function SummaryCards({summary}: SummaryCardsProps): React.JSX.Element {
  return (
    <ScrollView
      horizontal
      showsHorizontalScrollIndicator={false}
      contentContainerStyle={summaryStyles.container}>
      {/* Pending Card */}
      <View style={summaryStyles.card}>
        <View style={summaryStyles.cardContent}>
          <View style={summaryStyles.cardInfo}>
            <Text style={summaryStyles.cardLabel}>Pending</Text>
            <Text style={[summaryStyles.cardAmount, summaryStyles.pendingAmount]}>
              {formatCurrency(summary.totalPending)}
            </Text>
            <Text style={summaryStyles.cardCount}>
              {summary.pendingCount} invoice{summary.pendingCount !== 1 ? 's' : ''}
            </Text>
          </View>
          <View style={[summaryStyles.iconContainer, summaryStyles.pendingIcon]}>
            <Text style={summaryStyles.icon}>⏱</Text>
          </View>
        </View>
      </View>

      {/* Overdue Card */}
      <View style={summaryStyles.card}>
        <View style={summaryStyles.cardContent}>
          <View style={summaryStyles.cardInfo}>
            <Text style={summaryStyles.cardLabel}>Overdue</Text>
            <Text style={[summaryStyles.cardAmount, summaryStyles.overdueAmount]}>
              {formatCurrency(summary.totalOverdue)}
            </Text>
            <Text style={summaryStyles.cardCount}>
              {summary.overdueCount} invoice{summary.overdueCount !== 1 ? 's' : ''}
            </Text>
          </View>
          <View style={[summaryStyles.iconContainer, summaryStyles.overdueIcon]}>
            <Text style={summaryStyles.icon}>⚠</Text>
          </View>
        </View>
      </View>

      {/* Paid Card */}
      <View style={summaryStyles.card}>
        <View style={summaryStyles.cardContent}>
          <View style={summaryStyles.cardInfo}>
            <Text style={summaryStyles.cardLabel}>Paid (This Year)</Text>
            <Text style={[summaryStyles.cardAmount, summaryStyles.paidAmount]}>
              {formatCurrency(summary.totalPaid)}
            </Text>
            <Text style={summaryStyles.cardCount}>
              {summary.paidCount} invoice{summary.paidCount !== 1 ? 's' : ''}
            </Text>
          </View>
          <View style={[summaryStyles.iconContainer, summaryStyles.paidIcon]}>
            <Text style={summaryStyles.icon}>✓</Text>
          </View>
        </View>
      </View>
    </ScrollView>
  );
}

const summaryStyles = StyleSheet.create({
  container: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    gap: 12,
  },
  card: {
    width: 160,
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 16,
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.05,
    shadowRadius: 2,
    elevation: 2,
    marginRight: 12,
  },
  cardContent: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
  },
  cardInfo: {
    flex: 1,
  },
  cardLabel: {
    fontSize: 13,
    color: '#6B7280',
    marginBottom: 4,
  },
  cardAmount: {
    fontSize: 20,
    fontWeight: '700',
    marginBottom: 2,
  },
  pendingAmount: {
    color: '#D97706',
  },
  overdueAmount: {
    color: '#DC2626',
  },
  paidAmount: {
    color: '#059669',
  },
  cardCount: {
    fontSize: 11,
    color: '#9CA3AF',
  },
  iconContainer: {
    width: 36,
    height: 36,
    borderRadius: 18,
    justifyContent: 'center',
    alignItems: 'center',
  },
  pendingIcon: {
    backgroundColor: '#FEF3C7',
  },
  overdueIcon: {
    backgroundColor: '#FEE2E2',
  },
  paidIcon: {
    backgroundColor: '#D1FAE5',
  },
  icon: {
    fontSize: 16,
  },
});

interface FilterPillsProps {
  activeFilter: FilterStatus;
  onFilterChange: (filter: FilterStatus) => void;
  summary: InvoiceSummary;
  totalCount: number;
}

/**
 * Filter pills for filtering invoices by status.
 */
function FilterPills({
  activeFilter,
  onFilterChange,
  summary,
  totalCount,
}: FilterPillsProps): React.JSX.Element {
  return (
    <View style={filterStyles.container}>
      <Text style={filterStyles.sectionTitle}>Payment History</Text>
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={filterStyles.pillsContainer}>
        {/* All Filter */}
        <TouchableOpacity
          style={[
            filterStyles.pill,
            activeFilter === 'all' && filterStyles.pillActive,
          ]}
          onPress={() => onFilterChange('all')}
          activeOpacity={0.7}>
          <Text
            style={[
              filterStyles.pillText,
              activeFilter === 'all' && filterStyles.pillTextActive,
            ]}>
            All ({totalCount})
          </Text>
        </TouchableOpacity>

        {/* Pending Filter */}
        {summary.pendingCount > 0 && (
          <TouchableOpacity
            style={[
              filterStyles.pillWithBadge,
              activeFilter === 'pending' && filterStyles.pillActive,
            ]}
            onPress={() => onFilterChange('pending')}
            activeOpacity={0.7}>
            <PaymentStatusBadge status="pending" size="sm" />
            <Text style={filterStyles.pillCount}>({summary.pendingCount})</Text>
          </TouchableOpacity>
        )}

        {/* Overdue Filter */}
        {summary.overdueCount > 0 && (
          <TouchableOpacity
            style={[
              filterStyles.pillWithBadge,
              activeFilter === 'overdue' && filterStyles.pillActive,
            ]}
            onPress={() => onFilterChange('overdue')}
            activeOpacity={0.7}>
            <PaymentStatusBadge status="overdue" size="sm" />
            <Text style={filterStyles.pillCount}>({summary.overdueCount})</Text>
          </TouchableOpacity>
        )}

        {/* Paid Filter */}
        {summary.paidCount > 0 && (
          <TouchableOpacity
            style={[
              filterStyles.pillWithBadge,
              activeFilter === 'paid' && filterStyles.pillActive,
            ]}
            onPress={() => onFilterChange('paid')}
            activeOpacity={0.7}>
            <PaymentStatusBadge status="paid" size="sm" />
            <Text style={filterStyles.pillCount}>({summary.paidCount})</Text>
          </TouchableOpacity>
        )}
      </ScrollView>
    </View>
  );
}

const filterStyles = StyleSheet.create({
  container: {
    paddingTop: 16,
    paddingBottom: 8,
    backgroundColor: '#F9FAFB',
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: '#111827',
    paddingHorizontal: 16,
    marginBottom: 12,
  },
  pillsContainer: {
    paddingHorizontal: 16,
    gap: 8,
    flexDirection: 'row',
  },
  pill: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    backgroundColor: '#E5E7EB',
    marginRight: 8,
  },
  pillActive: {
    backgroundColor: '#DBEAFE',
    borderWidth: 1,
    borderColor: '#3B82F6',
  },
  pillWithBadge: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 16,
    backgroundColor: '#F3F4F6',
    marginRight: 8,
  },
  pillText: {
    fontSize: 13,
    fontWeight: '500',
    color: '#4B5563',
  },
  pillTextActive: {
    color: '#1D4ED8',
  },
  pillCount: {
    fontSize: 11,
    color: '#6B7280',
    marginLeft: 4,
  },
});

/**
 * Empty state component when no invoices are available.
 */
function EmptyState(): React.JSX.Element {
  return (
    <View style={emptyStyles.container}>
      <View style={emptyStyles.iconContainer}>
        <Text style={emptyStyles.icon}>📄</Text>
      </View>
      <Text style={emptyStyles.title}>No invoices yet</Text>
      <Text style={emptyStyles.message}>
        Your invoices will appear here once they are generated.
      </Text>
    </View>
  );
}

const emptyStyles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
    marginTop: 48,
  },
  iconContainer: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  icon: {
    fontSize: 40,
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 8,
    textAlign: 'center',
  },
  message: {
    fontSize: 15,
    color: '#6B7280',
    textAlign: 'center',
    lineHeight: 22,
  },
});

/**
 * Empty state for filtered results.
 */
function FilteredEmptyState({
  filter,
}: {
  filter: FilterStatus;
}): React.JSX.Element {
  const filterLabel =
    filter === 'pending'
      ? 'pending'
      : filter === 'overdue'
        ? 'overdue'
        : filter === 'paid'
          ? 'paid'
          : '';

  return (
    <View style={emptyStyles.container}>
      <View style={emptyStyles.iconContainer}>
        <Text style={emptyStyles.icon}>🔍</Text>
      </View>
      <Text style={emptyStyles.title}>No {filterLabel} invoices</Text>
      <Text style={emptyStyles.message}>
        You don't have any {filterLabel} invoices at this time.
      </Text>
    </View>
  );
}

interface ErrorStateProps {
  message: string;
  onRetry: () => void;
}

/**
 * Error state component with retry button.
 */
function ErrorState({message, onRetry}: ErrorStateProps): React.JSX.Element {
  return (
    <View style={errorStyles.container}>
      <View style={errorStyles.iconContainer}>
        <Text style={errorStyles.icon}>!</Text>
      </View>
      <Text style={errorStyles.title}>Unable to load invoices</Text>
      <Text style={errorStyles.message}>{message}</Text>
      <TouchableOpacity
        style={errorStyles.retryButton}
        onPress={onRetry}
        activeOpacity={0.7}>
        <Text style={errorStyles.retryText}>Try Again</Text>
      </TouchableOpacity>
    </View>
  );
}

const errorStyles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
    marginTop: 48,
  },
  iconContainer: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: '#FEE2E2',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  icon: {
    fontSize: 32,
    fontWeight: '700',
    color: '#DC2626',
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 8,
    textAlign: 'center',
  },
  message: {
    fontSize: 15,
    color: '#6B7280',
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 24,
  },
  retryButton: {
    backgroundColor: '#6366F1',
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 8,
  },
  retryText: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: '600',
  },
});

/**
 * Loading state component.
 */
function LoadingState(): React.JSX.Element {
  return (
    <View style={loadingStyles.container}>
      <ActivityIndicator size="large" color="#6366F1" />
      <Text style={loadingStyles.text}>Loading invoices...</Text>
    </View>
  );
}

const loadingStyles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
  },
  text: {
    marginTop: 16,
    fontSize: 15,
    color: '#6B7280',
  },
});

// ============================================================================
// Main Component
// ============================================================================

/**
 * Invoices Screen - displays invoice listing with summary and filtering.
 *
 * Features:
 * - Summary cards showing pending, overdue, and paid totals
 * - Filter pills for filtering by status
 * - FlatList for performant scrolling through invoices
 * - Pull-to-refresh using RefreshControl
 * - Loading, empty, and error states
 * - Automatic initial data load
 */
function InvoicesScreen(_props: InvoicesScreenProps): React.JSX.Element {
  const [activeFilter, setActiveFilter] = useState<FilterStatus>('all');
  const {refreshing, data, error, onRefresh} = useRefresh<Invoice[]>(
    fetchInvoices,
  );

  // Initial load on mount
  useEffect(() => {
    onRefresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Calculate summary statistics
  const summary = useMemo<InvoiceSummary>(() => {
    const invoices = data || [];
    return {
      totalPaid: invoices
        .filter(inv => inv.status === 'paid')
        .reduce((sum, inv) => sum + inv.amount, 0),
      totalPending: invoices
        .filter(inv => inv.status === 'pending')
        .reduce((sum, inv) => sum + inv.amount, 0),
      totalOverdue: invoices
        .filter(inv => inv.status === 'overdue')
        .reduce((sum, inv) => sum + inv.amount, 0),
      paidCount: invoices.filter(inv => inv.status === 'paid').length,
      pendingCount: invoices.filter(inv => inv.status === 'pending').length,
      overdueCount: invoices.filter(inv => inv.status === 'overdue').length,
    };
  }, [data]);

  // Filter invoices based on active filter
  const filteredInvoices = useMemo<Invoice[]>(() => {
    const invoices = data || [];
    if (activeFilter === 'all') {
      return invoices;
    }
    return invoices.filter(inv => inv.status === activeFilter);
  }, [data, activeFilter]);

  // Handle filter change
  const handleFilterChange = useCallback((filter: FilterStatus) => {
    setActiveFilter(filter);
  }, []);

  // Render individual invoice card
  const renderInvoice = useCallback(
    ({item}: {item: Invoice}) => (
      <View style={styles.invoiceContainer}>
        <InvoiceCard invoice={item} />
      </View>
    ),
    [],
  );

  // Key extractor for FlatList
  const keyExtractor = useCallback((item: Invoice) => item.id, []);

  // List header component with summary and filters
  const ListHeaderComponent = useCallback(
    () => (
      <>
        <Header subtitle="View and manage your billing history" />
        {data && data.length > 0 && (
          <>
            <SummaryCards summary={summary} />
            <FilterPills
              activeFilter={activeFilter}
              onFilterChange={handleFilterChange}
              summary={summary}
              totalCount={data.length}
            />
          </>
        )}
      </>
    ),
    [data, summary, activeFilter, handleFilterChange],
  );

  // List empty component
  const ListEmptyComponent = useCallback(() => {
    // Don't show empty state while loading or if we haven't fetched yet
    if (refreshing || data === null) {
      return null;
    }
    // Show filtered empty state if filter is active
    if (activeFilter !== 'all') {
      return <FilteredEmptyState filter={activeFilter} />;
    }
    return <EmptyState />;
  }, [refreshing, data, activeFilter]);

  // List footer for spacing
  const ListFooterComponent = useCallback(
    () => <View style={styles.listFooter} />,
    [],
  );

  // Show loading state on initial load
  if (data === null && refreshing && !error) {
    return (
      <SafeAreaView style={styles.container}>
        <Header subtitle="View and manage your billing history" />
        <LoadingState />
      </SafeAreaView>
    );
  }

  // Show error state if fetch failed and no cached data
  if (error && data === null) {
    return (
      <SafeAreaView style={styles.container}>
        <Header subtitle="View and manage your billing history" />
        <ErrorState
          message={error.message || 'Please check your connection and try again.'}
          onRetry={onRefresh}
        />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <FlatList
        data={filteredInvoices}
        renderItem={renderInvoice}
        keyExtractor={keyExtractor}
        contentContainerStyle={styles.listContent}
        ListHeaderComponent={ListHeaderComponent}
        ListEmptyComponent={ListEmptyComponent}
        ListFooterComponent={ListFooterComponent}
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={refreshing}
            onRefresh={onRefresh}
            tintColor="#6366F1"
            colors={['#6366F1']}
            title="Pull to refresh"
            titleColor="#6B7280"
          />
        }
        // Performance optimizations
        removeClippedSubviews={true}
        maxToRenderPerBatch={5}
        windowSize={5}
        initialNumToRender={3}
      />
    </SafeAreaView>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F9FAFB',
  },
  listContent: {
    flexGrow: 1,
  },
  invoiceContainer: {
    paddingHorizontal: 16,
    paddingBottom: 16,
  },
  listFooter: {
    height: 24,
  },
});

export default InvoicesScreen;
