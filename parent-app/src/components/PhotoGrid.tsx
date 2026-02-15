/**
 * LAYA Parent App - Photo Grid Component
 *
 * Displays a grid of photo thumbnails with support for maximum display limit,
 * remaining count indicator, and selection handling.
 *
 * Adapted from parent-portal/components/PhotoGallery.tsx for React Native.
 */

import React, {useCallback, useMemo} from 'react';
import {
  View,
  Text,
  StyleSheet,
  Image,
  TouchableOpacity,
  Dimensions,
  FlatList,
} from 'react-native';

import type {Photo} from '../types';

// ============================================================================
// Constants
// ============================================================================

const GRID_COLUMNS = 3;
const GRID_SPACING = 4;
const SCREEN_WIDTH = Dimensions.get('window').width;

// ============================================================================
// Props Interface
// ============================================================================

interface PhotoGridProps {
  /** Array of photos to display */
  photos: Photo[];
  /** Maximum number of photos to display (default: 6) */
  maxDisplay?: number;
  /** Callback when a photo is pressed */
  onPhotoPress?: (photo: Photo, index: number) => void;
  /** Horizontal padding from parent container (used for sizing calculation) */
  containerPadding?: number;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Calculate the size of each photo thumbnail based on available width.
 */
function calculateThumbnailSize(containerPadding: number): number {
  const availableWidth = SCREEN_WIDTH - containerPadding * 2;
  const totalSpacing = GRID_SPACING * (GRID_COLUMNS - 1);
  return (availableWidth - totalSpacing) / GRID_COLUMNS;
}

// ============================================================================
// Sub-components
// ============================================================================

interface EmptyStateProps {
  message?: string;
}

/**
 * EmptyState - displays a placeholder when no photos are available.
 */
function EmptyState({message}: EmptyStateProps): React.JSX.Element {
  return (
    <View style={emptyStyles.container}>
      <View style={emptyStyles.iconContainer}>
        <Text style={emptyStyles.icon}>📷</Text>
      </View>
      <Text style={emptyStyles.message}>{message || 'No photos for this day'}</Text>
    </View>
  );
}

const emptyStyles = StyleSheet.create({
  container: {
    alignItems: 'center',
    justifyContent: 'center',
    paddingVertical: 32,
    paddingHorizontal: 16,
    borderWidth: 2,
    borderStyle: 'dashed',
    borderColor: '#D1D5DB',
    borderRadius: 12,
    backgroundColor: '#F9FAFB',
  },
  iconContainer: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#E5E7EB',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  icon: {
    fontSize: 24,
  },
  message: {
    fontSize: 14,
    color: '#6B7280',
    textAlign: 'center',
  },
});

interface PhotoThumbnailProps {
  photo: Photo;
  index: number;
  size: number;
  isLast: boolean;
  remainingCount: number;
  onPress?: (photo: Photo, index: number) => void;
}

/**
 * PhotoThumbnail - displays a single photo thumbnail with optional remaining count overlay.
 */
function PhotoThumbnail({
  photo,
  index,
  size,
  isLast,
  remainingCount,
  onPress,
}: PhotoThumbnailProps): React.JSX.Element {
  const handlePress = useCallback(() => {
    onPress?.(photo, index);
  }, [photo, index, onPress]);

  const showRemainingOverlay = isLast && remainingCount > 0;

  return (
    <TouchableOpacity
      style={[
        thumbnailStyles.container,
        {width: size, height: size},
      ]}
      onPress={handlePress}
      activeOpacity={0.8}
      accessibilityRole="button"
      accessibilityLabel={
        photo.caption
          ? `Photo: ${photo.caption}`
          : `Photo ${index + 1}${showRemainingOverlay ? `, plus ${remainingCount} more` : ''}`
      }
      accessibilityHint="Double tap to view full photo">
      {photo.url ? (
        <Image
          source={{uri: photo.url}}
          style={thumbnailStyles.image}
          resizeMode="cover"
          accessibilityIgnoresInvertColors
        />
      ) : (
        <View style={thumbnailStyles.placeholder}>
          <Text style={thumbnailStyles.placeholderIcon}>📷</Text>
        </View>
      )}

      {/* Caption overlay on hover state (shown on press) */}
      {photo.caption && !showRemainingOverlay && (
        <View style={thumbnailStyles.captionOverlay}>
          <Text style={thumbnailStyles.captionText} numberOfLines={2}>
            {photo.caption}
          </Text>
        </View>
      )}

      {/* Remaining count overlay on last visible photo */}
      {showRemainingOverlay && (
        <View style={thumbnailStyles.remainingOverlay}>
          <Text style={thumbnailStyles.remainingText}>+{remainingCount}</Text>
        </View>
      )}
    </TouchableOpacity>
  );
}

const thumbnailStyles = StyleSheet.create({
  container: {
    borderRadius: 8,
    overflow: 'hidden',
    backgroundColor: '#E5E7EB',
  },
  image: {
    width: '100%',
    height: '100%',
  },
  placeholder: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F3F4F6',
  },
  placeholderIcon: {
    fontSize: 32,
    opacity: 0.5,
  },
  captionOverlay: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.6)',
    paddingVertical: 4,
    paddingHorizontal: 6,
  },
  captionText: {
    fontSize: 10,
    fontWeight: '500',
    color: '#FFFFFF',
  },
  remainingOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  remainingText: {
    fontSize: 24,
    fontWeight: '700',
    color: '#FFFFFF',
  },
});

// ============================================================================
// Main Component
// ============================================================================

/**
 * PhotoGrid - displays a grid of photo thumbnails.
 *
 * Shows up to `maxDisplay` photos in a grid layout. If there are more photos
 * than `maxDisplay`, shows a remaining count overlay on the last visible photo.
 *
 * @example
 * ```tsx
 * <PhotoGrid
 *   photos={report.photos}
 *   maxDisplay={6}
 *   onPhotoPress={(photo, index) => navigation.navigate('PhotoViewer', { photo, index })}
 * />
 * ```
 */
function PhotoGrid({
  photos,
  maxDisplay = 6,
  onPhotoPress,
  containerPadding = 16,
}: PhotoGridProps): React.JSX.Element {
  const thumbnailSize = useMemo(
    () => calculateThumbnailSize(containerPadding),
    [containerPadding],
  );

  const displayPhotos = useMemo(
    () => photos.slice(0, maxDisplay),
    [photos, maxDisplay],
  );

  const remainingCount = useMemo(
    () => Math.max(0, photos.length - maxDisplay),
    [photos.length, maxDisplay],
  );

  const keyExtractor = useCallback((item: Photo) => item.id, []);

  const renderItem = useCallback(
    ({item, index}: {item: Photo; index: number}) => {
      const isLast = index === displayPhotos.length - 1;
      return (
        <View style={[styles.thumbnailWrapper, {marginRight: (index + 1) % GRID_COLUMNS === 0 ? 0 : GRID_SPACING}]}>
          <PhotoThumbnail
            photo={item}
            index={index}
            size={thumbnailSize}
            isLast={isLast}
            remainingCount={remainingCount}
            onPress={onPhotoPress}
          />
        </View>
      );
    },
    [displayPhotos.length, thumbnailSize, remainingCount, onPhotoPress],
  );

  // Empty state
  if (photos.length === 0) {
    return <EmptyState />;
  }

  return (
    <View style={styles.container}>
      <FlatList
        data={displayPhotos}
        keyExtractor={keyExtractor}
        renderItem={renderItem}
        numColumns={GRID_COLUMNS}
        scrollEnabled={false}
        contentContainerStyle={styles.gridContent}
        columnWrapperStyle={styles.row}
        accessibilityRole="list"
        accessibilityLabel={`Photo gallery with ${photos.length} ${photos.length === 1 ? 'photo' : 'photos'}`}
      />
    </View>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  container: {
    width: '100%',
  },
  gridContent: {
    paddingBottom: GRID_SPACING,
  },
  row: {
    marginBottom: GRID_SPACING,
  },
  thumbnailWrapper: {
    marginBottom: 0,
  },
});

export default PhotoGrid;
