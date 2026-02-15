/**
 * LAYA Parent App - DailyFeedScreen
 *
 * Main screen displaying a timeline of child activities throughout the day.
 * Shows meals, naps, activities, photos, and other events in chronological
 * order with pull-to-refresh functionality.
 */

import React, {useState, useCallback, useEffect} from 'react';
import {
  StyleSheet,
  Text,
  View,
  SectionList,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import ActivityCard from '../components/ActivityCard';
import {
  fetchDailyFeed,
  getMockFeedData,
  formatDateHeader,
} from '../api/feedApi';
import type {FeedEvent, DailyFeed, DailySummary} from '../types';

/**
 * Section data structure for SectionList
 */
interface FeedSection {
  title: string;
  date: string;
  summary: DailySummary | null;
  data: FeedEvent[];
}

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
  success: '#4CAF50',
  warning: '#FF9800',
  info: '#2196F3',
  purple: '#9C27B0',
};

/**
 * DailyFeedScreen displays all child activities in a timeline format
 */
function DailyFeedScreen(): React.JSX.Element {
  const [sections, setSections] = useState<FeedSection[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  /**
   * Load feed data from API
   */
  const loadFeed = useCallback(async (showRefreshIndicator = false) => {
    if (showRefreshIndicator) {
      setIsRefreshing(true);
    } else {
      setIsLoading(true);
    }
    setError(null);

    try {
      const response = await fetchDailyFeed();

      if (response.success && response.data) {
        const feedSections = transformFeedToSections(response.data.feeds);
        setSections(feedSections);
      } else {
        // Use mock data for development
        const mockData = getMockFeedData();
        const feedSections = transformFeedToSections(mockData.feeds);
        setSections(feedSections);
      }
    } catch (err) {
      // Use mock data for development when API is not available
      const mockData = getMockFeedData();
      const feedSections = transformFeedToSections(mockData.feeds);
      setSections(feedSections);
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, []);

  /**
   * Transform feed data into section list format
   */
  const transformFeedToSections = (feeds: DailyFeed[]): FeedSection[] => {
    return feeds.map(feed => ({
      title: formatDateHeader(feed.date),
      date: feed.date,
      summary: feed.summary,
      data: [...feed.events].sort(
        (a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime(),
      ),
    }));
  };

  /**
   * Initial load
   */
  useEffect(() => {
    loadFeed();
  }, [loadFeed]);

  /**
   * Handle pull-to-refresh
   */
  const handleRefresh = useCallback(() => {
    loadFeed(true);
  }, [loadFeed]);

  /**
   * Handle event card press
   */
  const handleEventPress = useCallback((event: FeedEvent) => {
    // Navigate to event detail or expand - to be implemented
    // For now, this is a placeholder for future functionality
  }, []);

  /**
   * Render section header with date and summary
   */
  const renderSectionHeader = ({
    section,
  }: {
    section: FeedSection;
  }): React.JSX.Element => (
    <View style={styles.sectionHeader}>
      <Text style={styles.sectionTitle}>{section.title}</Text>
      {section.summary && (
        <View style={styles.summaryRow}>
          {section.summary.mealsCount > 0 && (
            <View style={styles.summaryItem}>
              <Text style={styles.summaryIcon}>🍽️</Text>
              <Text style={styles.summaryText}>{section.summary.mealsCount}</Text>
            </View>
          )}
          {section.summary.napMinutes > 0 && (
            <View style={styles.summaryItem}>
              <Text style={styles.summaryIcon}>😴</Text>
              <Text style={styles.summaryText}>
                {section.summary.napMinutes >= 60
                  ? `${Math.floor(section.summary.napMinutes / 60)}h ${section.summary.napMinutes % 60}m`
                  : `${section.summary.napMinutes}m`}
              </Text>
            </View>
          )}
          {section.summary.activitiesCount > 0 && (
            <View style={styles.summaryItem}>
              <Text style={styles.summaryIcon}>🎨</Text>
              <Text style={styles.summaryText}>{section.summary.activitiesCount}</Text>
            </View>
          )}
          {section.summary.photosCount > 0 && (
            <View style={styles.summaryItem}>
              <Text style={styles.summaryIcon}>📷</Text>
              <Text style={styles.summaryText}>{section.summary.photosCount}</Text>
            </View>
          )}
        </View>
      )}
    </View>
  );

  /**
   * Render an activity card item
   */
  const renderItem = ({
    item,
    index,
    section,
  }: {
    item: FeedEvent;
    index: number;
    section: FeedSection;
  }): React.JSX.Element => (
    <ActivityCard
      event={item}
      showConnector={true}
      isFirst={index === 0}
      isLast={index === section.data.length - 1}
      onPress={handleEventPress}
    />
  );

  /**
   * Render empty state
   */
  const renderEmptyState = (): React.JSX.Element => (
    <View style={styles.emptyState}>
      <Text style={styles.emptyStateIcon}>📋</Text>
      <Text style={styles.emptyStateTitle}>No Activities Yet</Text>
      <Text style={styles.emptyStateText}>
        Check back later to see your child's daily activities and updates.
      </Text>
    </View>
  );

  /**
   * Render section footer spacing
   */
  const renderSectionFooter = (): React.JSX.Element => (
    <View style={styles.sectionFooter} />
  );

  /**
   * Key extractor for list items
   */
  const keyExtractor = useCallback((item: FeedEvent) => item.id, []);

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
        <Text style={styles.loadingText}>Loading activities...</Text>
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

  return (
    <View style={styles.container}>
      <SectionList
        sections={sections}
        renderItem={renderItem}
        renderSectionHeader={renderSectionHeader}
        renderSectionFooter={renderSectionFooter}
        keyExtractor={keyExtractor}
        ListEmptyComponent={renderEmptyState}
        contentContainerStyle={styles.listContent}
        stickySectionHeadersEnabled={false}
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
    paddingTop: 8,
    paddingBottom: 20,
  },
  sectionHeader: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 12,
    backgroundColor: COLORS.background,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: COLORS.text,
    marginBottom: 8,
  },
  summaryRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 12,
  },
  summaryItem: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: COLORS.cardBackground,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  summaryIcon: {
    fontSize: 14,
    marginRight: 4,
  },
  summaryText: {
    fontSize: 13,
    fontWeight: '600',
    color: COLORS.textSecondary,
  },
  sectionFooter: {
    height: 8,
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    marginTop: 60,
  },
  emptyStateIcon: {
    fontSize: 48,
    marginBottom: 16,
  },
  emptyStateTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: COLORS.text,
    marginBottom: 8,
  },
  emptyStateText: {
    fontSize: 14,
    color: COLORS.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
  },
});

export default DailyFeedScreen;
