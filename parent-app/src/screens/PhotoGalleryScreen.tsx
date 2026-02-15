/**
 * LAYA Parent App - PhotoGalleryScreen
 *
 * Main screen for viewing photos of children shared by teachers.
 * Displays photos in a grid layout with full-screen preview capability.
 */

import React, {useState, useCallback, useEffect} from 'react';
import {
  StyleSheet,
  Text,
  View,
  ActivityIndicator,
  RefreshControl,
} from 'react-native';
import PhotoGrid, {PhotoGridEmptyState} from '../components/PhotoGrid';
import PhotoViewer from '../components/PhotoViewer';
import {fetchPhotos, getMockPhotos} from '../api/photoApi';
import type {Photo} from '../types';

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
 * PhotoGalleryScreen displays all photos for the parent's children
 */
function PhotoGalleryScreen(): React.JSX.Element {
  const [photos, setPhotos] = useState<Photo[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [selectedPhotoIndex, setSelectedPhotoIndex] = useState<number | null>(
    null,
  );

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
        setPhotos(response.data.photos);
      } else {
        // Use mock data for development
        const mockData = getMockPhotos();
        setPhotos(mockData.photos);
      }
    } catch (err) {
      // Use mock data for development when API is not available
      const mockData = getMockPhotos();
      setPhotos(mockData.photos);
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, []);

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
   * Handle photo press to open viewer
   */
  const handlePhotoPress = useCallback((photo: Photo, index: number) => {
    setSelectedPhotoIndex(index);
  }, []);

  /**
   * Close photo viewer
   */
  const handleCloseViewer = useCallback(() => {
    setSelectedPhotoIndex(null);
  }, []);

  /**
   * Render header with photo count
   */
  const renderHeader = useCallback(
    () => (
      <View style={styles.header}>
        <Text style={styles.headerTitle}>Photos</Text>
        <Text style={styles.headerSubtitle}>
          {photos.length} {photos.length === 1 ? 'photo' : 'photos'}
        </Text>
      </View>
    ),
    [photos.length],
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
      <PhotoGrid
        photos={photos}
        onPhotoPress={handlePhotoPress}
        ListHeaderComponent={photos.length > 0 ? renderHeader : null}
        ListEmptyComponent={<PhotoGridEmptyState />}
        refreshing={isRefreshing}
        onRefresh={handleRefresh}
      />

      {/* Full-screen photo viewer modal */}
      {selectedPhotoIndex !== null && (
        <PhotoViewer
          photos={photos}
          initialIndex={selectedPhotoIndex}
          visible={selectedPhotoIndex !== null}
          onClose={handleCloseViewer}
        />
      )}
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
  header: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 12,
    backgroundColor: COLORS.background,
  },
  headerTitle: {
    fontSize: 24,
    fontWeight: '700',
    color: COLORS.text,
    marginBottom: 4,
  },
  headerSubtitle: {
    fontSize: 14,
    color: COLORS.textSecondary,
  },
});

export default PhotoGalleryScreen;
