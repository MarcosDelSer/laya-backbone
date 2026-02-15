/**
 * LAYA Parent App - Photo Gallery Screen
 *
 * Displays photo gallery with download and share capabilities.
 * Features include:
 * - Grid view of photos grouped by date
 * - Full-screen photo viewer with pinch-to-zoom
 * - Download to camera roll
 * - Native share sheet integration
 * - Pull-to-refresh for updates
 * - Child filter selector
 *
 * Adapted from parent-portal/components/PhotoGallery.tsx for React Native.
 */

import React, {useEffect, useCallback, useState, useMemo} from 'react';
import {
  SafeAreaView,
  View,
  Text,
  RefreshControl,
  StyleSheet,
  ActivityIndicator,
  TouchableOpacity,
  SectionList,
} from 'react-native';

import type {PhotosScreenProps} from '../types/navigation';
import type {Photo, Child} from '../types';
import type {PhotoWithMetadata, PhotosByDate} from '../api/photoApi';
import {useRefresh} from '../hooks/useRefresh';
import {groupPhotosByDate, sortPhotosByDate} from '../api/photoApi';
import PhotoGrid from '../components/PhotoGrid';
import PhotoViewer from '../components/PhotoViewer';
import {sharePhoto, savePhotoToGallery} from '../services/shareService';

// ============================================================================
// Mock Data (for development until API is connected)
// ============================================================================

const mockChild: Child = {
  id: 'child-1',
  firstName: 'Emma',
  lastName: 'Johnson',
  dateOfBirth: '2021-05-15',
  profilePhotoUrl: null,
  classroomId: 'classroom-1',
  classroomName: 'Sunshine Room',
};

const mockPhotos: PhotoWithMetadata[] = [
  {
    photo: {
      id: 'photo-1',
      url: 'https://images.unsplash.com/photo-1503454537195-1dcabb73ffb9?w=400',
      caption: 'Finger painting during art time',
      taggedChildren: ['child-1'],
    },
    child: mockChild,
    uploadedAt: new Date().toISOString(),
    reportId: 'report-1',
    reportDate: new Date().toISOString().split('T')[0],
  },
  {
    photo: {
      id: 'photo-2',
      url: 'https://images.unsplash.com/photo-1504439904031-93ded9f93e4e?w=400',
      caption: 'Playing on the playground',
      taggedChildren: ['child-1'],
    },
    child: mockChild,
    uploadedAt: new Date().toISOString(),
    reportId: 'report-1',
    reportDate: new Date().toISOString().split('T')[0],
  },
  {
    photo: {
      id: 'photo-3',
      url: 'https://images.unsplash.com/photo-1489710437720-ebb67ec84dd2?w=400',
      caption: 'Reading time with friends',
      taggedChildren: ['child-1'],
    },
    child: mockChild,
    uploadedAt: new Date().toISOString(),
    reportId: 'report-1',
    reportDate: new Date().toISOString().split('T')[0],
  },
  {
    photo: {
      id: 'photo-4',
      url: 'https://images.unsplash.com/photo-1587654780291-39c9404d746b?w=400',
      caption: 'Building blocks activity',
      taggedChildren: ['child-1'],
    },
    child: mockChild,
    uploadedAt: new Date(Date.now() - 86400000).toISOString(),
    reportId: 'report-2',
    reportDate: new Date(Date.now() - 86400000).toISOString().split('T')[0],
  },
  {
    photo: {
      id: 'photo-5',
      url: 'https://images.unsplash.com/photo-1560969184-10fe8719e047?w=400',
      caption: 'Music and movement class',
      taggedChildren: ['child-1'],
    },
    child: mockChild,
    uploadedAt: new Date(Date.now() - 86400000).toISOString(),
    reportId: 'report-2',
    reportDate: new Date(Date.now() - 86400000).toISOString().split('T')[0],
  },
  {
    photo: {
      id: 'photo-6',
      url: 'https://images.unsplash.com/photo-1484820540004-14229fe36ca4?w=400',
      caption: 'Learning about butterflies',
      taggedChildren: ['child-1'],
    },
    child: mockChild,
    uploadedAt: new Date(Date.now() - 172800000).toISOString(),
    reportId: 'report-3',
    reportDate: new Date(Date.now() - 172800000).toISOString().split('T')[0],
  },
  {
    photo: {
      id: 'photo-7',
      url: 'https://images.unsplash.com/photo-1485546246426-74dc88dec4d9?w=400',
      caption: 'Outdoor exploration',
      taggedChildren: ['child-1'],
    },
    child: mockChild,
    uploadedAt: new Date(Date.now() - 172800000).toISOString(),
    reportId: 'report-3',
    reportDate: new Date(Date.now() - 172800000).toISOString().split('T')[0],
  },
  {
    photo: {
      id: 'photo-8',
      url: 'https://images.unsplash.com/photo-1476234251651-f353703a034d?w=400',
      caption: 'Sensory play with playdough',
      taggedChildren: ['child-1'],
    },
    child: mockChild,
    uploadedAt: new Date(Date.now() - 259200000).toISOString(),
    reportId: 'report-4',
    reportDate: new Date(Date.now() - 259200000).toISOString().split('T')[0],
  },
];

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Fetches photos data.
 * Uses mock data in development, will connect to API in production.
 */
async function fetchGalleryPhotos(): Promise<PhotoWithMetadata[]> {
  // TODO: Replace with actual API call when backend is connected
  // const response = await fetchPhotos();
  // if (response.success && response.data) {
  //   return response.data.items;
  // }
  // throw new Error(response.error?.message || 'Failed to fetch photos');

  // Simulate network delay for realistic UX
  await new Promise<void>(resolve => setTimeout(resolve, 800));

  // Return sorted mock data
  return sortPhotosByDate(mockPhotos);
}

// ============================================================================
// Sub-components
// ============================================================================

interface HeaderProps {
  subtitle: string;
  photoCount: number;
}

/**
 * Header component with title, subtitle, and photo count.
 */
function Header({subtitle, photoCount}: HeaderProps): React.JSX.Element {
  return (
    <View style={headerStyles.container}>
      <View style={headerStyles.titleRow}>
        <Text style={headerStyles.title}>Photos</Text>
        {photoCount > 0 && (
          <View style={headerStyles.countBadge}>
            <Text style={headerStyles.countText}>{photoCount}</Text>
          </View>
        )}
      </View>
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
  titleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    marginBottom: 4,
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
    color: '#111827',
  },
  countBadge: {
    marginLeft: 12,
    backgroundColor: '#EEF2FF',
    paddingHorizontal: 10,
    paddingVertical: 4,
    borderRadius: 12,
  },
  countText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#6366F1',
  },
  subtitle: {
    fontSize: 15,
    color: '#6B7280',
  },
});

interface SectionHeaderProps {
  title: string;
  count: number;
}

/**
 * Section header for date groups.
 */
function SectionHeader({title, count}: SectionHeaderProps): React.JSX.Element {
  return (
    <View style={sectionHeaderStyles.container}>
      <Text style={sectionHeaderStyles.title}>{title}</Text>
      <Text style={sectionHeaderStyles.count}>
        {count} {count === 1 ? 'photo' : 'photos'}
      </Text>
    </View>
  );
}

const sectionHeaderStyles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#F9FAFB',
  },
  title: {
    fontSize: 17,
    fontWeight: '600',
    color: '#111827',
  },
  count: {
    fontSize: 14,
    color: '#6B7280',
  },
});

/**
 * Empty state component when no photos are available.
 */
function EmptyState(): React.JSX.Element {
  return (
    <View style={emptyStyles.container}>
      <View style={emptyStyles.iconContainer}>
        <Text style={emptyStyles.icon}>📷</Text>
      </View>
      <Text style={emptyStyles.title}>No photos yet</Text>
      <Text style={emptyStyles.message}>
        Photos of your child will appear here once they are taken by their
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
      <Text style={errorStyles.title}>Unable to load photos</Text>
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
      <Text style={loadingStyles.text}>Loading photos...</Text>
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
 * Photo Gallery Screen - displays photos with download and share capabilities.
 *
 * Features:
 * - SectionList for photos grouped by date
 * - Pull-to-refresh using RefreshControl
 * - Full-screen PhotoViewer modal
 * - Download and share functionality
 * - Loading, empty, and error states
 * - Automatic initial data load
 */
function PhotoGalleryScreen(_props: PhotosScreenProps): React.JSX.Element {
  const {refreshing, data, error, onRefresh} = useRefresh<PhotoWithMetadata[]>(
    fetchGalleryPhotos,
  );

  // Photo viewer state
  const [viewerVisible, setViewerVisible] = useState(false);
  const [selectedPhotoIndex, setSelectedPhotoIndex] = useState(0);

  // Initial load on mount
  useEffect(() => {
    onRefresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Process data for section list
  const sections = useMemo(() => {
    if (!data || data.length === 0) {
      return [];
    }

    const grouped = groupPhotosByDate(data);
    return grouped.map(group => ({
      title: group.displayDate,
      date: group.date,
      data: [group], // Each section contains one group for rendering
    }));
  }, [data]);

  // Flat list of all photos for the viewer
  const allPhotos = useMemo(() => {
    if (!data) {
      return [];
    }
    return data.map(item => item.photo);
  }, [data]);

  // Total photo count
  const totalPhotoCount = data?.length || 0;

  /**
   * Handle photo press in grid - open full-screen viewer
   */
  const handlePhotoPress = useCallback(
    (photo: Photo, _index: number) => {
      // Find the global index of this photo
      const globalIndex = data?.findIndex(item => item.photo.id === photo.id) ?? 0;
      setSelectedPhotoIndex(globalIndex);
      setViewerVisible(true);
    },
    [data],
  );

  /**
   * Handle close viewer
   */
  const handleCloseViewer = useCallback(() => {
    setViewerVisible(false);
  }, []);

  /**
   * Handle download action from viewer
   */
  const handleDownload = useCallback(async (photo: Photo) => {
    await savePhotoToGallery(photo, {showSuccessAlert: true});
  }, []);

  /**
   * Handle share action from viewer
   */
  const handleShare = useCallback(async (photo: Photo) => {
    await sharePhoto(photo, {includeCaption: true});
  }, []);

  /**
   * Render section containing photos for a date
   */
  const renderSection = useCallback(
    ({item}: {item: PhotosByDate}) => {
      const sectionPhotos = item.photos.map(p => p.photo);
      return (
        <View style={styles.sectionContent}>
          <PhotoGrid
            photos={sectionPhotos}
            maxDisplay={12}
            onPhotoPress={handlePhotoPress}
            containerPadding={16}
          />
        </View>
      );
    },
    [handlePhotoPress],
  );

  /**
   * Render section header
   */
  const renderSectionHeader = useCallback(
    ({section}: {section: {title: string; data: PhotosByDate[]}}) => {
      const photoCount = section.data[0]?.photos.length || 0;
      return <SectionHeader title={section.title} count={photoCount} />;
    },
    [],
  );

  /**
   * Key extractor for sections
   */
  const keyExtractor = useCallback(
    (item: PhotosByDate) => item.date,
    [],
  );

  /**
   * List header component
   */
  const ListHeaderComponent = useCallback(
    () => (
      <Header
        subtitle="Browse and share photos of your child"
        photoCount={totalPhotoCount}
      />
    ),
    [totalPhotoCount],
  );

  /**
   * List empty component
   */
  const ListEmptyComponent = useCallback(() => {
    if (refreshing || data === null) {
      return null;
    }
    return <EmptyState />;
  }, [refreshing, data]);

  /**
   * List footer for spacing
   */
  const ListFooterComponent = useCallback(
    () => <View style={styles.listFooter} />,
    [],
  );

  // Show loading state on initial load
  if (data === null && refreshing && !error) {
    return (
      <SafeAreaView style={styles.container}>
        <Header
          subtitle="Browse and share photos of your child"
          photoCount={0}
        />
        <LoadingState />
      </SafeAreaView>
    );
  }

  // Show error state if fetch failed and no cached data
  if (error && data === null) {
    return (
      <SafeAreaView style={styles.container}>
        <Header
          subtitle="Browse and share photos of your child"
          photoCount={0}
        />
        <ErrorState
          message={error.message || 'Please check your connection and try again.'}
          onRetry={onRefresh}
        />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      <SectionList
        sections={sections}
        renderItem={renderSection}
        renderSectionHeader={renderSectionHeader}
        keyExtractor={keyExtractor}
        contentContainerStyle={styles.listContent}
        ListHeaderComponent={ListHeaderComponent}
        ListEmptyComponent={ListEmptyComponent}
        ListFooterComponent={ListFooterComponent}
        showsVerticalScrollIndicator={false}
        stickySectionHeadersEnabled={true}
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

      {/* Full-screen photo viewer */}
      <PhotoViewer
        photos={allPhotos}
        initialIndex={selectedPhotoIndex}
        visible={viewerVisible}
        onClose={handleCloseViewer}
        onDownload={handleDownload}
        onShare={handleShare}
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
  sectionContent: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    backgroundColor: '#FFFFFF',
  },
  listFooter: {
    height: 24,
  },
});

export default PhotoGalleryScreen;
