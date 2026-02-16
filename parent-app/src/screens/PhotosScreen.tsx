/**
 * LAYA Parent App - Photos Screen
 *
 * Displays photo gallery with grid layout, date grouping, and pull-to-refresh.
 * Tapping a photo opens the photo viewer modal for full-screen viewing.
 */

import React, {useState, useCallback, useEffect} from 'react';
import {
  StyleSheet,
  Text,
  View,
  SectionList,
  RefreshControl,
  ActivityIndicator,
  Image,
  TouchableOpacity,
  Dimensions,
} from 'react-native';
import {
  fetchPhotos,
  getMockPhotos,
  groupPhotosByDate,
  formatSectionDate,
} from '../api/photoApi';
import type {Photo} from '../types';
import type {PhotosScreenProps} from '../types/navigation';

/**
 * Section data structure for SectionList
 */
interface PhotoSection {
  title: string;
  date: string;
  data: Photo[][];
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
};

/**
 * Number of photos per row in the grid
 */
const GRID_COLUMNS = 3;

/**
 * Spacing between photos
 */
const PHOTO_SPACING = 2;

/**
 * Calculate photo thumbnail size based on screen width
 */
const screenWidth = Dimensions.get('window').width;
const photoSize = (screenWidth - PHOTO_SPACING * (GRID_COLUMNS + 1)) / GRID_COLUMNS;

/**
 * PhotosScreen displays all photos in a grid layout grouped by date
 */
function PhotosScreen({navigation}: PhotosScreenProps): React.JSX.Element {
  const [sections, setSections] = useState<PhotoSection[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  /**
   * Load photos from API
   */
  const loadPhotos = useCallback(async (showRefreshIndicator = false) => {
    if (showRefreshIndicator) {
      setIsRefreshing(true);
    } else {
      setIsLoading(true);
    }
    setError(null);

    try {
      const response = await fetchPhotos();

      if (response.success && response.data) {
        const photoSections = transformPhotosToSections(response.data.photos);
        setSections(photoSections);
      } else {
        // Use mock data for development
        const mockData = getMockPhotos();
        const photoSections = transformPhotosToSections(mockData.photos);
        setSections(photoSections);
      }
    } catch (err) {
      // Use mock data for development when API is not available
      const mockData = getMockPhotos();
      const photoSections = transformPhotosToSections(mockData.photos);
      setSections(photoSections);
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, []);

  /**
   * Transform photos into section list format with grid rows
   */
  const transformPhotosToSections = (photos: Photo[]): PhotoSection[] => {
    const groupedPhotos = groupPhotosByDate(photos);
    const photoSections: PhotoSection[] = [];

    // Sort dates in descending order (newest first)
    const sortedDates = Array.from(groupedPhotos.keys()).sort(
      (a, b) => new Date(b).getTime() - new Date(a).getTime(),
    );

    sortedDates.forEach(date => {
      const datePhotos = groupedPhotos.get(date) || [];
      // Sort photos within date by time (newest first)
      const sortedPhotos = [...datePhotos].sort(
        (a, b) => new Date(b.takenAt).getTime() - new Date(a.takenAt).getTime(),
      );

      // Group photos into rows for grid display
      const rows: Photo[][] = [];
      for (let i = 0; i < sortedPhotos.length; i += GRID_COLUMNS) {
        rows.push(sortedPhotos.slice(i, i + GRID_COLUMNS));
      }

      photoSections.push({
        title: formatSectionDate(date),
        date,
        data: rows,
      });
    });

    return photoSections;
  };

  /**
   * Initial load
   */
  useEffect(() => {
    loadPhotos();
  }, [loadPhotos]);

  /**
   * Handle pull-to-refresh
   */
  const handleRefresh = useCallback(() => {
    loadPhotos(true);
  }, [loadPhotos]);

  /**
   * Handle photo press - navigate to photo viewer
   */
  const handlePhotoPress = useCallback(
    (photo: Photo) => {
      navigation.navigate('PhotoViewer', {
        photoId: photo.id,
        photoUrl: photo.uri,
        caption: photo.caption || undefined,
      });
    },
    [navigation],
  );

  /**
   * Render section header with date
   */
  const renderSectionHeader = ({
    section,
  }: {
    section: PhotoSection;
  }): React.JSX.Element => (
    <View style={styles.sectionHeader}>
      <Text style={styles.sectionTitle}>{section.title}</Text>
      <Text style={styles.photoCount}>
        {section.data.reduce((count, row) => count + row.length, 0)} photos
      </Text>
    </View>
  );

  /**
   * Render a row of photos in the grid
   */
  const renderItem = ({item}: {item: Photo[]}): React.JSX.Element => (
    <View style={styles.photoRow}>
      {item.map(photo => (
        <TouchableOpacity
          key={photo.id}
          style={styles.photoContainer}
          onPress={() => handlePhotoPress(photo)}
          activeOpacity={0.8}>
          <Image
            source={{uri: photo.thumbnailUri}}
            style={styles.photo}
            resizeMode="cover"
          />
        </TouchableOpacity>
      ))}
      {/* Fill empty spaces in last row */}
      {item.length < GRID_COLUMNS &&
        Array(GRID_COLUMNS - item.length)
          .fill(null)
          .map((_, index) => (
            <View key={`empty-${index}`} style={styles.emptyPhotoSpace} />
          ))}
    </View>
  );

  /**
   * Render empty state
   */
  const renderEmptyState = (): React.JSX.Element => (
    <View style={styles.emptyState}>
      <Text style={styles.emptyStateIcon}>📷</Text>
      <Text style={styles.emptyStateTitle}>No Photos Yet</Text>
      <Text style={styles.emptyStateText}>
        Photos of your child will appear here when they are shared by the school.
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
  const keyExtractor = useCallback(
    (item: Photo[], index: number) =>
      `row-${item.map(p => p.id).join('-')}-${index}`,
    [],
  );

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
        <Text style={styles.loadingText}>Loading photos...</Text>
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
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 12,
    backgroundColor: COLORS.background,
  },
  sectionTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: COLORS.text,
  },
  photoCount: {
    fontSize: 14,
    color: COLORS.textSecondary,
  },
  sectionFooter: {
    height: 8,
  },
  photoRow: {
    flexDirection: 'row',
    paddingHorizontal: PHOTO_SPACING,
  },
  photoContainer: {
    width: photoSize,
    height: photoSize,
    margin: PHOTO_SPACING / 2,
  },
  photo: {
    width: '100%',
    height: '100%',
    borderRadius: 4,
    backgroundColor: COLORS.border,
  },
  emptyPhotoSpace: {
    width: photoSize,
    height: photoSize,
    margin: PHOTO_SPACING / 2,
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

export default PhotosScreen;
