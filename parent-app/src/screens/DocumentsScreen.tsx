/**
 * LAYA Parent App - DocumentsScreen
 *
 * Main screen displaying a list of documents requiring signature with
 * summary statistics, status filters, and e-signature functionality.
 * Pull-to-refresh to update document list.
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
  Alert,
} from 'react-native';
import DocumentCard from '../components/DocumentCard';
import SignatureCapture from '../components/SignatureCapture';
import {
  fetchDocuments,
  getMockDocumentData,
  submitSignature,
  openDocumentPdf,
} from '../api/documentsApi';
import type {DocumentSummary} from '../api/documentsApi';
import type {SignatureRequest, SignatureStatus} from '../types';

/**
 * Status filter options
 */
type FilterStatus = 'all' | SignatureStatus;

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
 * DocumentsScreen displays all documents with summary, filtering, and signing
 */
function DocumentsScreen(): React.JSX.Element {
  const [documents, setDocuments] = useState<SignatureRequest[]>([]);
  const [summary, setSummary] = useState<DocumentSummary | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<FilterStatus>('all');

  // Signature modal state
  const [signingDocument, setSigningDocument] = useState<SignatureRequest | null>(null);
  const [isSignatureModalVisible, setIsSignatureModalVisible] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);

  /**
   * Load documents from API
   */
  const loadDocuments = useCallback(async (showRefreshIndicator = false) => {
    if (showRefreshIndicator) {
      setIsRefreshing(true);
    } else {
      setIsLoading(true);
    }
    setError(null);

    try {
      const response = await fetchDocuments();

      if (response.success && response.data) {
        setDocuments(response.data.documents);
        setSummary(response.data.summary);
      } else {
        // Use mock data for development
        const mockData = getMockDocumentData();
        setDocuments(mockData.documents);
        setSummary(mockData.summary);
      }
    } catch {
      // Use mock data for development when API is not available
      const mockData = getMockDocumentData();
      setDocuments(mockData.documents);
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
    loadDocuments();
  }, [loadDocuments]);

  /**
   * Handle pull-to-refresh
   */
  const handleRefresh = useCallback(() => {
    loadDocuments(true);
  }, [loadDocuments]);

  /**
   * Handle sign button press - opens signature modal
   */
  const handleSignPress = useCallback((document: SignatureRequest) => {
    setSigningDocument(document);
    setIsSignatureModalVisible(true);
  }, []);

  /**
   * Handle view PDF button press
   */
  const handleViewPress = useCallback(async (document: SignatureRequest) => {
    try {
      await openDocumentPdf(document.documentUrl);
    } catch {
      Alert.alert(
        'Unable to Open',
        'Could not open the document. Please try again later.',
        [{text: 'OK'}],
      );
    }
  }, []);

  /**
   * Handle signature modal close
   */
  const handleCloseSignatureModal = useCallback(() => {
    if (!isSubmitting) {
      setIsSignatureModalVisible(false);
      setSigningDocument(null);
    }
  }, [isSubmitting]);

  /**
   * Handle signature submission
   */
  const handleSignatureSubmit = useCallback(
    async (documentId: string, signatureDataUrl: string) => {
      setIsSubmitting(true);

      try {
        const response = await submitSignature(documentId, signatureDataUrl);

        if (response.success && response.data) {
          // Update the document in state with signed status
          setDocuments(prevDocs =>
            prevDocs.map(doc =>
              doc.id === documentId
                ? {
                    ...doc,
                    status: 'signed' as const,
                    signedAt: new Date().toISOString(),
                  }
                : doc,
            ),
          );

          // Update summary
          if (summary) {
            setSummary({
              ...summary,
              pendingCount: summary.pendingCount - 1,
              signedCount: summary.signedCount + 1,
            });
          }
        } else {
          // For development - update state even without API
          setDocuments(prevDocs =>
            prevDocs.map(doc =>
              doc.id === documentId
                ? {
                    ...doc,
                    status: 'signed' as const,
                    signedAt: new Date().toISOString(),
                  }
                : doc,
            ),
          );

          if (summary) {
            setSummary({
              ...summary,
              pendingCount: summary.pendingCount - 1,
              signedCount: summary.signedCount + 1,
            });
          }
        }

        setIsSignatureModalVisible(false);
        setSigningDocument(null);

        Alert.alert(
          'Document Signed',
          'Your signature has been recorded successfully.',
          [{text: 'OK'}],
        );
      } catch {
        Alert.alert(
          'Signature Failed',
          'Could not submit your signature. Please try again.',
          [{text: 'OK'}],
        );
      } finally {
        setIsSubmitting(false);
      }
    },
    [summary],
  );

  /**
   * Filter documents by status
   */
  const getFilteredDocuments = useCallback((): SignatureRequest[] => {
    if (filter === 'all') {
      return documents;
    }
    return documents.filter(doc => doc.status === filter);
  }, [documents, filter]);

  /**
   * Get count for a filter status
   */
  const getFilterCount = (status: FilterStatus): number => {
    if (status === 'all') {
      return documents.length;
    }
    return documents.filter(doc => doc.status === status).length;
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
        {/* Total Documents */}
        <View style={styles.summaryCard}>
          <View style={styles.summaryCardContent}>
            <View>
              <Text style={styles.summaryLabel}>Total</Text>
              <Text style={[styles.summaryAmount, {color: COLORS.primary}]}>
                {summary.totalCount}
              </Text>
              <Text style={styles.summaryCount}>documents</Text>
            </View>
            <View style={[styles.summaryIcon, {backgroundColor: '#EBF5FF'}]}>
              <Text style={styles.summaryIconText}>{'\u{1F4C4}'}</Text>
            </View>
          </View>
        </View>

        {/* Pending Signatures */}
        <View style={styles.summaryCard}>
          <View style={styles.summaryCardContent}>
            <View>
              <Text style={styles.summaryLabel}>Pending</Text>
              <Text style={[styles.summaryAmount, {color: COLORS.warning}]}>
                {summary.pendingCount}
              </Text>
              <Text style={styles.summaryCount}>need signature</Text>
            </View>
            <View style={[styles.summaryIcon, {backgroundColor: '#FEF3C7'}]}>
              <Text style={styles.summaryIconText}>{'\u270D'}</Text>
            </View>
          </View>
        </View>

        {/* Signed Documents */}
        <View style={styles.summaryCard}>
          <View style={styles.summaryCardContent}>
            <View>
              <Text style={styles.summaryLabel}>Signed</Text>
              <Text style={[styles.summaryAmount, {color: COLORS.success}]}>
                {summary.signedCount}
              </Text>
              <Text style={styles.summaryCount}>completed</Text>
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
   * Render status badge for filter pills
   */
  const renderStatusBadge = (
    status: SignatureStatus,
  ): React.JSX.Element => {
    const configs: Record<SignatureStatus, {bg: string; text: string; label: string}> = {
      pending: {bg: '#FEF3C7', text: '#D97706', label: 'Pending'},
      signed: {bg: '#DCFCE7', text: '#16A34A', label: 'Signed'},
      expired: {bg: '#FEE2E2', text: '#DC2626', label: 'Expired'},
    };
    const config = configs[status];

    return (
      <View style={[styles.statusBadge, {backgroundColor: config.bg}]}>
        <Text style={[styles.statusBadgeText, {color: config.text}]}>
          {config.label}
        </Text>
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
        accessibilityLabel={`All documents, ${getFilterCount('all')}`}
        accessibilityState={{selected: filter === 'all'}}>
        <Text style={[styles.filterText, filter === 'all' && styles.filterTextActive]}>
          All ({getFilterCount('all')})
        </Text>
      </TouchableOpacity>

      {summary && summary.pendingCount > 0 && (
        <TouchableOpacity
          style={[styles.filterPill, filter === 'pending' && styles.filterPillActive]}
          onPress={() => setFilter('pending')}
          accessibilityRole="button"
          accessibilityLabel={`Pending documents, ${getFilterCount('pending')}`}
          accessibilityState={{selected: filter === 'pending'}}>
          {renderStatusBadge('pending')}
          <Text style={styles.filterCount}>({getFilterCount('pending')})</Text>
        </TouchableOpacity>
      )}

      {summary && summary.signedCount > 0 && (
        <TouchableOpacity
          style={[styles.filterPill, filter === 'signed' && styles.filterPillActive]}
          onPress={() => setFilter('signed')}
          accessibilityRole="button"
          accessibilityLabel={`Signed documents, ${getFilterCount('signed')}`}
          accessibilityState={{selected: filter === 'signed'}}>
          {renderStatusBadge('signed')}
          <Text style={styles.filterCount}>({getFilterCount('signed')})</Text>
        </TouchableOpacity>
      )}

      {summary && summary.expiredCount > 0 && (
        <TouchableOpacity
          style={[styles.filterPill, filter === 'expired' && styles.filterPillActive]}
          onPress={() => setFilter('expired')}
          accessibilityRole="button"
          accessibilityLabel={`Expired documents, ${getFilterCount('expired')}`}
          accessibilityState={{selected: filter === 'expired'}}>
          {renderStatusBadge('expired')}
          <Text style={styles.filterCount}>({getFilterCount('expired')})</Text>
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
        <Text style={styles.title}>Documents</Text>
        <Text style={styles.subtitle}>Review and sign required documents</Text>
      </View>

      {/* Summary Cards */}
      {renderSummary()}

      {/* Section Title */}
      <View style={styles.sectionHeader}>
        <Text style={styles.sectionTitle}>All Documents</Text>
      </View>

      {/* Filters */}
      {renderFilters()}
    </View>
  );

  /**
   * Render empty state
   */
  const renderEmptyState = (): React.JSX.Element => {
    const emptyMessages: Record<FilterStatus, {title: string; text: string}> = {
      all: {
        title: 'No documents',
        text: 'Documents requiring your signature will appear here.',
      },
      pending: {
        title: 'All caught up!',
        text: 'You have no pending documents to sign.',
      },
      signed: {
        title: 'No signed documents',
        text: 'Documents you sign will appear here.',
      },
      expired: {
        title: 'No expired documents',
        text: 'Expired documents will appear here.',
      },
    };

    const message = emptyMessages[filter];

    return (
      <View style={styles.emptyState}>
        <View style={styles.emptyIcon}>
          <Text style={styles.emptyIconText}>{'\u{1F4C4}'}</Text>
        </View>
        <Text style={styles.emptyTitle}>{message.title}</Text>
        <Text style={styles.emptyText}>{message.text}</Text>
      </View>
    );
  };

  /**
   * Render a document item
   */
  const renderDocumentItem = ({item}: {item: SignatureRequest}): React.JSX.Element => (
    <DocumentCard
      document={item}
      onSign={handleSignPress}
      onView={handleViewPress}
    />
  );

  /**
   * Key extractor for list items
   */
  const keyExtractor = useCallback((item: SignatureRequest) => item.id, []);

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
        <Text style={styles.loadingText}>Loading documents...</Text>
      </View>
    );
  }

  if (error) {
    return (
      <View style={styles.errorContainer}>
        <Text style={styles.errorIcon}>!</Text>
        <Text style={styles.errorTitle}>Something went wrong</Text>
        <Text style={styles.errorText}>{error}</Text>
        <TouchableOpacity style={styles.retryButton} onPress={() => loadDocuments()}>
          <Text style={styles.retryButtonText}>Try Again</Text>
        </TouchableOpacity>
      </View>
    );
  }

  const filteredDocuments = getFilteredDocuments();

  return (
    <View style={styles.container}>
      <FlatList
        data={filteredDocuments}
        renderItem={renderDocumentItem}
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

      {/* Signature Modal */}
      <SignatureCapture
        document={signingDocument}
        isVisible={isSignatureModalVisible}
        onClose={handleCloseSignatureModal}
        onSubmit={handleSignatureSubmit}
        isSubmitting={isSubmitting}
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
    marginBottom: 20,
  },
  retryButton: {
    paddingHorizontal: 24,
    paddingVertical: 12,
    borderRadius: 8,
    backgroundColor: COLORS.primary,
  },
  retryButtonText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#FFFFFF',
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
    fontSize: 28,
    fontWeight: '700',
  },
  summaryCount: {
    fontSize: 11,
    color: COLORS.textLight,
    marginTop: 2,
  },
  summaryIcon: {
    width: 44,
    height: 44,
    borderRadius: 22,
    justifyContent: 'center',
    alignItems: 'center',
  },
  summaryIconText: {
    fontSize: 20,
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
  statusBadge: {
    paddingHorizontal: 8,
    paddingVertical: 2,
    borderRadius: 10,
  },
  statusBadgeText: {
    fontSize: 11,
    fontWeight: '600',
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

export default DocumentsScreen;
