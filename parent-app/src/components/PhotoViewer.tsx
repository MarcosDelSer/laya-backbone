/**
 * LAYA Parent App - PhotoViewer Component
 *
 * A full-screen modal for viewing photos with swipe navigation,
 * zoom support, and photo details.
 */

import React, {useState, useCallback, useRef} from 'react';
import {
  StyleSheet,
  View,
  Image,
  Text,
  TouchableOpacity,
  Modal,
  SafeAreaView,
  Dimensions,
  FlatList,
  StatusBar,
  Platform,
  Share,
  Alert,
} from 'react-native';
import type {Photo} from '../types';
import {formatPhotoDate} from '../api/photoApi';

const {width: SCREEN_WIDTH, height: SCREEN_HEIGHT} = Dimensions.get('window');

interface PhotoViewerProps {
  /** Array of photos to display */
  photos: Photo[];
  /** Index of the initially selected photo */
  initialIndex: number;
  /** Whether the viewer is visible */
  visible: boolean;
  /** Callback to close the viewer */
  onClose: () => void;
}

/**
 * Theme colors
 */
const COLORS = {
  background: '#000000',
  text: '#FFFFFF',
  textSecondary: 'rgba(255, 255, 255, 0.7)',
  overlay: 'rgba(0, 0, 0, 0.5)',
  buttonBackground: 'rgba(0, 0, 0, 0.5)',
  indicatorActive: '#FFFFFF',
  indicatorInactive: 'rgba(255, 255, 255, 0.4)',
};

/**
 * PhotoViewer displays photos in a full-screen modal with swipe navigation
 */
function PhotoViewer({
  photos,
  initialIndex,
  visible,
  onClose,
}: PhotoViewerProps): React.JSX.Element {
  const [currentIndex, setCurrentIndex] = useState(initialIndex);
  const [showDetails, setShowDetails] = useState(true);
  const flatListRef = useRef<FlatList>(null);

  const currentPhoto = photos[currentIndex];

  /**
   * Handle scroll end to update current index
   */
  const handleMomentumScrollEnd = useCallback(
    (event: {nativeEvent: {contentOffset: {x: number}}}) => {
      const newIndex = Math.round(
        event.nativeEvent.contentOffset.x / SCREEN_WIDTH,
      );
      if (newIndex >= 0 && newIndex < photos.length) {
        setCurrentIndex(newIndex);
      }
    },
    [photos.length],
  );

  /**
   * Toggle details visibility
   */
  const toggleDetails = useCallback(() => {
    setShowDetails(prev => !prev);
  }, []);

  /**
   * Handle share action
   */
  const handleShare = useCallback(async () => {
    try {
      await Share.share({
        message: currentPhoto.caption || 'Check out this photo!',
        url: currentPhoto.downloadUrl,
      });
    } catch (error) {
      Alert.alert('Error', 'Could not share this photo');
    }
  }, [currentPhoto]);

  /**
   * Navigate to specific photo index
   */
  const navigateToIndex = useCallback(
    (index: number) => {
      if (index >= 0 && index < photos.length) {
        flatListRef.current?.scrollToIndex({index, animated: true});
        setCurrentIndex(index);
      }
    },
    [photos.length],
  );

  /**
   * Go to previous photo
   */
  const goToPrevious = useCallback(() => {
    navigateToIndex(currentIndex - 1);
  }, [currentIndex, navigateToIndex]);

  /**
   * Go to next photo
   */
  const goToNext = useCallback(() => {
    navigateToIndex(currentIndex + 1);
  }, [currentIndex, navigateToIndex]);

  /**
   * Render individual photo item
   */
  const renderPhotoItem = useCallback(
    ({item}: {item: Photo}) => (
      <TouchableOpacity
        style={styles.photoPage}
        activeOpacity={1}
        onPress={toggleDetails}>
        <Image
          source={{uri: item.uri}}
          style={styles.fullPhoto}
          resizeMode="contain"
          accessibilityLabel={item.caption || 'Photo'}
          accessibilityIgnoresInvertColors
        />
      </TouchableOpacity>
    ),
    [toggleDetails],
  );

  /**
   * Key extractor for FlatList
   */
  const keyExtractor = useCallback((item: Photo) => item.id, []);

  /**
   * Get item layout for performance
   */
  const getItemLayout = useCallback(
    (_: ArrayLike<Photo> | null | undefined, index: number) => ({
      length: SCREEN_WIDTH,
      offset: SCREEN_WIDTH * index,
      index,
    }),
    [],
  );

  /**
   * Render page indicator dots
   */
  const renderIndicators = () => {
    if (photos.length <= 1) {
      return null;
    }

    // Only show up to 7 indicators for better UX
    const maxIndicators = 7;
    const startIndex = Math.max(
      0,
      Math.min(
        currentIndex - Math.floor(maxIndicators / 2),
        photos.length - maxIndicators,
      ),
    );
    const endIndex = Math.min(startIndex + maxIndicators, photos.length);
    const visibleIndices = Array.from(
      {length: endIndex - startIndex},
      (_, i) => startIndex + i,
    );

    return (
      <View style={styles.indicatorContainer}>
        {visibleIndices.map(index => (
          <TouchableOpacity
            key={index}
            onPress={() => navigateToIndex(index)}
            style={[
              styles.indicator,
              index === currentIndex && styles.indicatorActive,
            ]}
            accessibilityRole="button"
            accessibilityLabel={`Go to photo ${index + 1}`}
          />
        ))}
      </View>
    );
  };

  return (
    <Modal
      visible={visible}
      animationType="fade"
      presentationStyle="fullScreen"
      onRequestClose={onClose}
      statusBarTranslucent>
      <StatusBar
        barStyle="light-content"
        backgroundColor={COLORS.background}
        translucent
      />
      <SafeAreaView style={styles.container}>
        {/* Header with close and share buttons */}
        {showDetails && (
          <View style={styles.header}>
            <TouchableOpacity
              style={styles.headerButton}
              onPress={onClose}
              accessibilityRole="button"
              accessibilityLabel="Close"
              hitSlop={{top: 10, bottom: 10, left: 10, right: 10}}>
              <Text style={styles.closeButtonText}>X</Text>
            </TouchableOpacity>

            <Text style={styles.counterText}>
              {currentIndex + 1} / {photos.length}
            </Text>

            <TouchableOpacity
              style={styles.headerButton}
              onPress={handleShare}
              accessibilityRole="button"
              accessibilityLabel="Share photo"
              hitSlop={{top: 10, bottom: 10, left: 10, right: 10}}>
              <Text style={styles.shareButtonText}>Share</Text>
            </TouchableOpacity>
          </View>
        )}

        {/* Photo carousel */}
        <FlatList
          ref={flatListRef}
          data={photos}
          renderItem={renderPhotoItem}
          keyExtractor={keyExtractor}
          horizontal
          pagingEnabled
          showsHorizontalScrollIndicator={false}
          onMomentumScrollEnd={handleMomentumScrollEnd}
          getItemLayout={getItemLayout}
          initialScrollIndex={initialIndex}
          removeClippedSubviews
          maxToRenderPerBatch={3}
          windowSize={3}
        />

        {/* Navigation arrows for larger screens */}
        {photos.length > 1 && showDetails && (
          <>
            {currentIndex > 0 && (
              <TouchableOpacity
                style={[styles.navButton, styles.navButtonLeft]}
                onPress={goToPrevious}
                accessibilityRole="button"
                accessibilityLabel="Previous photo">
                <Text style={styles.navButtonText}>{'<'}</Text>
              </TouchableOpacity>
            )}
            {currentIndex < photos.length - 1 && (
              <TouchableOpacity
                style={[styles.navButton, styles.navButtonRight]}
                onPress={goToNext}
                accessibilityRole="button"
                accessibilityLabel="Next photo">
                <Text style={styles.navButtonText}>{'>'}</Text>
              </TouchableOpacity>
            )}
          </>
        )}

        {/* Bottom details panel */}
        {showDetails && currentPhoto && (
          <View style={styles.detailsPanel}>
            {renderIndicators()}

            {currentPhoto.caption && (
              <Text style={styles.caption} numberOfLines={2}>
                {currentPhoto.caption}
              </Text>
            )}

            <View style={styles.metadataRow}>
              <Text style={styles.metadataText}>
                {formatPhotoDate(currentPhoto.takenAt)}
              </Text>
              {currentPhoto.takenBy && (
                <Text style={styles.metadataText}>
                  by {currentPhoto.takenBy}
                </Text>
              )}
            </View>
          </View>
        )}
      </SafeAreaView>
    </Modal>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  header: {
    position: 'absolute',
    top: Platform.OS === 'ios' ? 50 : 30,
    left: 0,
    right: 0,
    zIndex: 10,
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingHorizontal: 16,
  },
  headerButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: COLORS.buttonBackground,
    justifyContent: 'center',
    alignItems: 'center',
  },
  closeButtonText: {
    fontSize: 18,
    fontWeight: '700',
    color: COLORS.text,
  },
  shareButtonText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.text,
  },
  counterText: {
    fontSize: 16,
    fontWeight: '600',
    color: COLORS.text,
  },
  photoPage: {
    width: SCREEN_WIDTH,
    height: SCREEN_HEIGHT,
    justifyContent: 'center',
    alignItems: 'center',
  },
  fullPhoto: {
    width: SCREEN_WIDTH,
    height: SCREEN_HEIGHT * 0.7,
  },
  navButton: {
    position: 'absolute',
    top: '50%',
    marginTop: -25,
    width: 50,
    height: 50,
    borderRadius: 25,
    backgroundColor: COLORS.buttonBackground,
    justifyContent: 'center',
    alignItems: 'center',
  },
  navButtonLeft: {
    left: 10,
  },
  navButtonRight: {
    right: 10,
  },
  navButtonText: {
    fontSize: 24,
    fontWeight: '700',
    color: COLORS.text,
  },
  detailsPanel: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: Platform.OS === 'ios' ? 40 : 20,
    backgroundColor: COLORS.overlay,
  },
  indicatorContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 12,
  },
  indicator: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: COLORS.indicatorInactive,
    marginHorizontal: 4,
  },
  indicatorActive: {
    backgroundColor: COLORS.indicatorActive,
    width: 10,
    height: 10,
    borderRadius: 5,
  },
  caption: {
    fontSize: 16,
    fontWeight: '500',
    color: COLORS.text,
    marginBottom: 8,
    textAlign: 'center',
  },
  metadataRow: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 8,
  },
  metadataText: {
    fontSize: 13,
    color: COLORS.textSecondary,
  },
});

export default PhotoViewer;
