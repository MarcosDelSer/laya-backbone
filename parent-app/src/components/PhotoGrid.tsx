/**
 * LAYA Parent App - PhotoGrid Component
 *
 * A grid component for displaying photo thumbnails with support
 * for selection and navigation to full-screen view.
 */

import React, {useCallback} from 'react';
import {
  StyleSheet,
  View,
  Image,
  TouchableOpacity,
  Text,
  Dimensions,
  FlatList,
} from 'react-native';
import type {Photo} from '../types';

const {width: SCREEN_WIDTH} = Dimensions.get('window');
const NUM_COLUMNS = 3;
const SPACING = 2;
const PHOTO_SIZE = (SCREEN_WIDTH - SPACING * (NUM_COLUMNS + 1)) / NUM_COLUMNS;

interface PhotoGridProps {
  /** Array of photos to display */
  photos: Photo[];
  /** Callback when a photo is pressed */
  onPhotoPress: (photo: Photo, index: number) => void;
  /** Optional header component */
  ListHeaderComponent?: React.ComponentType | React.ReactElement | null;
  /** Optional empty state component */
  ListEmptyComponent?: React.ComponentType | React.ReactElement | null;
  /** Whether the list is refreshing */
  refreshing?: boolean;
  /** Callback for pull-to-refresh */
  onRefresh?: () => void;
  /** Callback when end of list is reached */
  onEndReached?: () => void;
  /** Threshold for triggering onEndReached */
  onEndReachedThreshold?: number;
}

/**
 * Theme colors
 */
const COLORS = {
  background: '#F5F5F5',
  cardBackground: '#FFFFFF',
  border: '#E0E0E0',
  text: '#333333',
  textSecondary: '#666666',
  overlay: 'rgba(0, 0, 0, 0.3)',
};

/**
 * PhotoGrid displays photos in a responsive grid layout
 */
function PhotoGrid({
  photos,
  onPhotoPress,
  ListHeaderComponent,
  ListEmptyComponent,
  refreshing = false,
  onRefresh,
  onEndReached,
  onEndReachedThreshold = 0.5,
}: PhotoGridProps): React.JSX.Element {
  /**
   * Render individual photo item
   */
  const renderPhotoItem = useCallback(
    ({item, index}: {item: Photo; index: number}) => (
      <TouchableOpacity
        style={styles.photoContainer}
        onPress={() => onPhotoPress(item, index)}
        activeOpacity={0.8}
        accessibilityRole="button"
        accessibilityLabel={item.caption || `Photo ${index + 1}`}
        accessibilityHint="Double tap to view full size">
        <Image
          source={{uri: item.thumbnailUri || item.uri}}
          style={styles.photo}
          resizeMode="cover"
          accessibilityIgnoresInvertColors
        />
        {/* Overlay gradient for visual effect */}
        <View style={styles.photoOverlay} pointerEvents="none" />
      </TouchableOpacity>
    ),
    [onPhotoPress],
  );

  /**
   * Key extractor for FlatList
   */
  const keyExtractor = useCallback((item: Photo) => item.id, []);

  /**
   * Get item layout for performance optimization
   */
  const getItemLayout = useCallback(
    (_: ArrayLike<Photo> | null | undefined, index: number) => ({
      length: PHOTO_SIZE + SPACING,
      offset: (PHOTO_SIZE + SPACING) * Math.floor(index / NUM_COLUMNS),
      index,
    }),
    [],
  );

  return (
    <FlatList
      data={photos}
      renderItem={renderPhotoItem}
      keyExtractor={keyExtractor}
      numColumns={NUM_COLUMNS}
      contentContainerStyle={styles.gridContainer}
      columnWrapperStyle={styles.row}
      showsVerticalScrollIndicator={false}
      ListHeaderComponent={ListHeaderComponent}
      ListEmptyComponent={ListEmptyComponent}
      refreshing={refreshing}
      onRefresh={onRefresh}
      onEndReached={onEndReached}
      onEndReachedThreshold={onEndReachedThreshold}
      getItemLayout={getItemLayout}
      removeClippedSubviews
      maxToRenderPerBatch={12}
      windowSize={5}
      initialNumToRender={12}
    />
  );
}

/**
 * Empty state component for when there are no photos
 */
export function PhotoGridEmptyState(): React.JSX.Element {
  return (
    <View style={styles.emptyState}>
      <Text style={styles.emptyStateIcon}>📷</Text>
      <Text style={styles.emptyStateTitle}>No Photos Yet</Text>
      <Text style={styles.emptyStateText}>
        Photos of your child will appear here once they are shared by their
        teachers.
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  gridContainer: {
    padding: SPACING,
    flexGrow: 1,
  },
  row: {
    justifyContent: 'flex-start',
  },
  photoContainer: {
    width: PHOTO_SIZE,
    height: PHOTO_SIZE,
    margin: SPACING / 2,
    borderRadius: 4,
    overflow: 'hidden',
    backgroundColor: COLORS.border,
  },
  photo: {
    width: '100%',
    height: '100%',
  },
  photoOverlay: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: 'transparent',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
    marginTop: 60,
  },
  emptyStateIcon: {
    fontSize: 64,
    marginBottom: 16,
  },
  emptyStateTitle: {
    fontSize: 20,
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

export default PhotoGrid;
