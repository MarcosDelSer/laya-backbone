/**
 * LAYA Parent App - Daily Feed Screen
 *
 * Displays daily reports for child(ren) with meals, naps, activities, and photos.
 * Supports pull-to-refresh for real-time updates using FlatList with RefreshControl.
 *
 * Adapted from parent-portal/app/daily-reports/page.tsx for React Native.
 */

import React, {useEffect, useCallback} from 'react';
import {
  SafeAreaView,
  View,
  Text,
  FlatList,
  RefreshControl,
  StyleSheet,
  ActivityIndicator,
  TouchableOpacity,
} from 'react-native';

import type {DailyFeedScreenProps} from '../types/navigation';
import type {DailyReport} from '../types';
import {useRefresh} from '../hooks/useRefresh';
import DailyReportCard from '../components/DailyReportCard';
import {sortReportsByDate} from '../api/dailyReportsApi';

// ============================================================================
// Mock Data (for development until API is connected)
// ============================================================================

const mockReports: DailyReport[] = [
  {
    id: 'report-1',
    date: new Date().toISOString().split('T')[0], // Today
    childId: 'child-1',
    meals: [
      {
        id: 'meal-1',
        type: 'breakfast',
        time: '8:45 AM',
        notes: 'Ate all of their oatmeal and fruit',
        amount: 'all',
      },
      {
        id: 'meal-2',
        type: 'snack',
        time: '10:30 AM',
        notes: 'Apple slices and crackers',
        amount: 'most',
      },
      {
        id: 'meal-3',
        type: 'lunch',
        time: '12:00 PM',
        notes: 'Chicken nuggets, vegetables, and milk',
        amount: 'some',
      },
    ],
    naps: [
      {
        id: 'nap-1',
        startTime: '12:30 PM',
        endTime: '2:00 PM',
        quality: 'good',
      },
    ],
    activities: [
      {
        id: 'activity-1',
        name: 'Art Time',
        time: '9:00 AM',
        description: 'Finger painting with watercolors',
      },
      {
        id: 'activity-2',
        name: 'Story Circle',
        time: '11:00 AM',
        description: 'Read "The Very Hungry Caterpillar"',
      },
      {
        id: 'activity-3',
        name: 'Music & Movement',
        time: '2:30 PM',
        description: 'Dancing and singing songs',
      },
      {
        id: 'activity-4',
        name: 'Outdoor Play',
        time: '3:30 PM',
        description: 'Playing on the playground',
      },
    ],
    photos: [
      {
        id: 'photo-1',
        url: '',
        caption: 'Finger painting during art time',
        taggedChildren: ['child-1'],
      },
      {
        id: 'photo-2',
        url: '',
        caption: 'Playing on the playground',
        taggedChildren: ['child-1'],
      },
    ],
  },
  {
    id: 'report-2',
    date: new Date(Date.now() - 86400000).toISOString().split('T')[0], // Yesterday
    childId: 'child-1',
    meals: [
      {
        id: 'meal-4',
        type: 'breakfast',
        time: '8:30 AM',
        notes: 'Scrambled eggs and toast',
        amount: 'all',
      },
      {
        id: 'meal-5',
        type: 'snack',
        time: '10:15 AM',
        notes: 'Cheese and grapes',
        amount: 'all',
      },
      {
        id: 'meal-6',
        type: 'lunch',
        time: '12:00 PM',
        notes: 'Pasta with marinara sauce',
        amount: 'most',
      },
    ],
    naps: [
      {
        id: 'nap-2',
        startTime: '1:00 PM',
        endTime: '2:30 PM',
        quality: 'fair',
      },
    ],
    activities: [
      {
        id: 'activity-5',
        name: 'Building Blocks',
        time: '9:30 AM',
        description: 'Built a tall tower with friends',
      },
      {
        id: 'activity-6',
        name: 'Science Exploration',
        time: '11:00 AM',
        description: 'Learned about butterflies',
      },
      {
        id: 'activity-7',
        name: 'Outdoor Play',
        time: '3:00 PM',
        description: 'Sandbox and swing time',
      },
    ],
    photos: [
      {
        id: 'photo-3',
        url: '',
        caption: 'Building blocks activity',
        taggedChildren: ['child-1'],
      },
    ],
  },
  {
    id: 'report-3',
    date: new Date(Date.now() - 172800000).toISOString().split('T')[0], // 2 days ago
    childId: 'child-1',
    meals: [
      {
        id: 'meal-7',
        type: 'breakfast',
        time: '8:40 AM',
        notes: 'Pancakes and fruit',
        amount: 'most',
      },
      {
        id: 'meal-8',
        type: 'snack',
        time: '10:30 AM',
        notes: 'Yogurt and berries',
        amount: 'all',
      },
      {
        id: 'meal-9',
        type: 'lunch',
        time: '12:15 PM',
        notes: 'Turkey sandwich and veggies',
        amount: 'some',
      },
    ],
    naps: [
      {
        id: 'nap-3',
        startTime: '12:45 PM',
        endTime: '2:15 PM',
        quality: 'good',
      },
    ],
    activities: [
      {
        id: 'activity-8',
        name: 'Circle Time',
        time: '9:00 AM',
        description: 'Morning songs and calendar',
      },
      {
        id: 'activity-9',
        name: 'Sensory Play',
        time: '10:00 AM',
        description: 'Playing with playdough',
      },
    ],
    photos: [],
  },
];

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Fetches daily reports data.
 * Uses mock data in development, will connect to API in production.
 */
async function fetchReports(): Promise<DailyReport[]> {
  // TODO: Replace with actual API call when backend is connected
  // const response = await fetchTodayReports();
  // if (response.success && response.data) {
  //   return response.data.reports.map(r => r.report);
  // }
  // throw new Error(response.error?.message || 'Failed to fetch reports');

  // Simulate network delay for realistic UX
  await new Promise<void>(resolve => setTimeout(resolve, 800));

  // Return sorted mock data
  return sortReportsByDate(mockReports);
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
      <Text style={headerStyles.title}>Daily Reports</Text>
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

/**
 * Empty state component when no reports are available.
 */
function EmptyState(): React.JSX.Element {
  return (
    <View style={emptyStyles.container}>
      <View style={emptyStyles.iconContainer}>
        <Text style={emptyStyles.icon}>📋</Text>
      </View>
      <Text style={emptyStyles.title}>No reports available</Text>
      <Text style={emptyStyles.message}>
        Daily reports will appear here once they are created by your child's
        teacher.
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
      <Text style={errorStyles.title}>Unable to load reports</Text>
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
      <Text style={loadingStyles.text}>Loading reports...</Text>
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
 * Daily Feed Screen - displays daily reports for children with pull-to-refresh.
 *
 * Features:
 * - FlatList for performant scrolling through reports
 * - Pull-to-refresh using RefreshControl
 * - Loading, empty, and error states
 * - Automatic initial data load
 */
function DailyFeedScreen(_props: DailyFeedScreenProps): React.JSX.Element {
  const {refreshing, data, error, onRefresh} = useRefresh<DailyReport[]>(
    fetchReports,
  );

  // Initial load on mount
  useEffect(() => {
    onRefresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Render individual report card
  const renderReport = useCallback(
    ({item}: {item: DailyReport}) => (
      <View style={styles.reportContainer}>
        <DailyReportCard report={item} />
      </View>
    ),
    [],
  );

  // Key extractor for FlatList
  const keyExtractor = useCallback((item: DailyReport) => item.id, []);

  // List header component
  const ListHeaderComponent = useCallback(
    () => (
      <Header subtitle="View your child's daily activities, meals, and naps" />
    ),
    [],
  );

  // List empty component (shown when data is empty array, not null)
  const ListEmptyComponent = useCallback(() => {
    // Don't show empty state while loading or if we haven't fetched yet
    if (refreshing || data === null) {
      return null;
    }
    return <EmptyState />;
  }, [refreshing, data]);

  // List footer for spacing
  const ListFooterComponent = useCallback(
    () => <View style={styles.listFooter} />,
    [],
  );

  // Show loading state on initial load
  if (data === null && refreshing && !error) {
    return (
      <SafeAreaView style={styles.container}>
        <Header subtitle="View your child's daily activities, meals, and naps" />
        <LoadingState />
      </SafeAreaView>
    );
  }

  // Show error state if fetch failed and no cached data
  if (error && data === null) {
    return (
      <SafeAreaView style={styles.container}>
        <Header subtitle="View your child's daily activities, meals, and naps" />
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
        data={data || []}
        renderItem={renderReport}
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
  reportContainer: {
    paddingHorizontal: 16,
    paddingBottom: 16,
  },
  listFooter: {
    height: 24,
  },
});

export default DailyFeedScreen;
