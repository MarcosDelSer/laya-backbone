/**
 * LAYA Parent App - Photo Viewer Component
 *
 * Full-screen photo viewer with pinch-to-zoom gestures and horizontal
 * swipe navigation between photos.
 *
 * Features:
 * - Pinch-to-zoom with double-tap to reset
 * - Horizontal swipe navigation between photos
 * - Photo counter and caption display
 * - Download and share functionality hooks
 *
 * Adapted from parent-portal/components/PhotoGallery.tsx for React Native.
 */

import React, {useCallback, useRef, useState, useMemo} from 'react';
import {
  View,
  Text,
  StyleSheet,
  Image,
  TouchableOpacity,
  Dimensions,
  Modal,
  FlatList,
  Animated,
  PanResponder,
  StatusBar,
} from 'react-native';
import {useSafeAreaInsets} from 'react-native-safe-area-context';

import type {Photo} from '../types';

// ============================================================================
// Constants
// ============================================================================

const {width: SCREEN_WIDTH, height: SCREEN_HEIGHT} = Dimensions.get('window');
const MIN_SCALE = 1;
const MAX_SCALE = 3;
const DOUBLE_TAP_DELAY = 300;

// ============================================================================
// Props Interface
// ============================================================================

interface PhotoViewerProps {
  /** Array of photos to display */
  photos: Photo[];
  /** Index of initially selected photo */
  initialIndex?: number;
  /** Whether the viewer is visible */
  visible: boolean;
  /** Callback when viewer is closed */
  onClose: () => void;
  /** Callback when download is requested */
  onDownload?: (photo: Photo) => void;
  /** Callback when share is requested */
  onShare?: (photo: Photo) => void;
}

// ============================================================================
// Sub-components
// ============================================================================

interface ZoomableImageProps {
  photo: Photo;
  width: number;
  height: number;
}

/**
 * ZoomableImage - displays a single photo with pinch-to-zoom and double-tap gestures.
 */
function ZoomableImage({photo, width, height}: ZoomableImageProps): React.JSX.Element {
  const scale = useRef(new Animated.Value(1)).current;
  const translateX = useRef(new Animated.Value(0)).current;
  const translateY = useRef(new Animated.Value(0)).current;

  const lastScale = useRef(1);
  const lastTranslateX = useRef(0);
  const lastTranslateY = useRef(0);
  const lastTapTime = useRef(0);
  const baseScale = useRef(1);
  const pinchScale = useRef(1);

  /**
   * Reset zoom and position to default.
   */
  const resetZoom = useCallback(() => {
    Animated.parallel([
      Animated.spring(scale, {
        toValue: 1,
        useNativeDriver: true,
        friction: 5,
      }),
      Animated.spring(translateX, {
        toValue: 0,
        useNativeDriver: true,
        friction: 5,
      }),
      Animated.spring(translateY, {
        toValue: 0,
        useNativeDriver: true,
        friction: 5,
      }),
    ]).start();

    lastScale.current = 1;
    lastTranslateX.current = 0;
    lastTranslateY.current = 0;
    baseScale.current = 1;
  }, [scale, translateX, translateY]);

  /**
   * Handle double-tap to toggle zoom.
   */
  const handleDoubleTap = useCallback(() => {
    if (lastScale.current > 1) {
      resetZoom();
    } else {
      // Zoom to 2x
      Animated.spring(scale, {
        toValue: 2,
        useNativeDriver: true,
        friction: 5,
      }).start();
      lastScale.current = 2;
      baseScale.current = 2;
    }
  }, [scale, resetZoom]);

  /**
   * Clamp scale within min/max bounds.
   */
  const clampScale = (value: number): number => {
    return Math.min(Math.max(value, MIN_SCALE), MAX_SCALE);
  };

  /**
   * Clamp translation within bounds based on current scale.
   */
  const clampTranslation = (
    value: number,
    dimension: number,
    currentScale: number,
  ): number => {
    const maxTranslate = Math.max(0, ((currentScale - 1) * dimension) / 2);
    return Math.min(Math.max(value, -maxTranslate), maxTranslate);
  };

  const panResponder = useMemo(
    () =>
      PanResponder.create({
        onStartShouldSetPanResponder: () => true,
        onMoveShouldSetPanResponder: () => true,
        onPanResponderGrant: () => {
          // Check for double-tap
          const now = Date.now();
          if (now - lastTapTime.current < DOUBLE_TAP_DELAY) {
            handleDoubleTap();
          }
          lastTapTime.current = now;

          // Store current state for gesture tracking
          baseScale.current = lastScale.current;
        },
        onPanResponderMove: (_, gestureState) => {
          const {dx, dy, numberActiveTouches} = gestureState;

          if (numberActiveTouches === 2) {
            // Pinch gesture - estimate scale from movement
            // This is a simplified pinch detection
            const pinchDelta = Math.sqrt(dx * dx + dy * dy) / 100;
            const direction = dy > 0 ? 1 : -1;
            pinchScale.current = baseScale.current + direction * pinchDelta * 0.5;
            const newScale = clampScale(pinchScale.current);
            scale.setValue(newScale);
            lastScale.current = newScale;
          } else if (lastScale.current > 1) {
            // Pan gesture when zoomed in
            const newTranslateX = clampTranslation(
              lastTranslateX.current + dx,
              width,
              lastScale.current,
            );
            const newTranslateY = clampTranslation(
              lastTranslateY.current + dy,
              height,
              lastScale.current,
            );
            translateX.setValue(newTranslateX);
            translateY.setValue(newTranslateY);
          }
        },
        onPanResponderRelease: (_, gestureState) => {
          const {dx, dy} = gestureState;

          // Save final translation
          if (lastScale.current > 1) {
            lastTranslateX.current = clampTranslation(
              lastTranslateX.current + dx,
              width,
              lastScale.current,
            );
            lastTranslateY.current = clampTranslation(
              lastTranslateY.current + dy,
              height,
              lastScale.current,
            );
          }

          // Snap back to MIN_SCALE if below threshold
          if (lastScale.current < MIN_SCALE) {
            resetZoom();
          }
        },
      }),
    [
      scale,
      translateX,
      translateY,
      width,
      height,
      handleDoubleTap,
      resetZoom,
    ],
  );

  return (
    <Animated.View
      style={[
        zoomableStyles.container,
        {width, height},
        {
          transform: [
            {scale},
            {translateX},
            {translateY},
          ],
        },
      ]}
      {...panResponder.panHandlers}>
      {photo.url ? (
        <Image
          source={{uri: photo.url}}
          style={zoomableStyles.image}
          resizeMode="contain"
          accessibilityIgnoresInvertColors
          accessibilityLabel={photo.caption || 'Photo'}
        />
      ) : (
        <View style={zoomableStyles.placeholder}>
          <Text style={zoomableStyles.placeholderIcon}>📷</Text>
          <Text style={zoomableStyles.placeholderText}>Image unavailable</Text>
        </View>
      )}
    </Animated.View>
  );
}

const zoomableStyles = StyleSheet.create({
  container: {
    justifyContent: 'center',
    alignItems: 'center',
  },
  image: {
    width: '100%',
    height: '100%',
  },
  placeholder: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  placeholderIcon: {
    fontSize: 64,
    marginBottom: 16,
  },
  placeholderText: {
    fontSize: 16,
    color: '#9CA3AF',
  },
});

interface ActionButtonProps {
  icon: string;
  label: string;
  onPress: () => void;
}

/**
 * ActionButton - bottom action button for download/share actions.
 */
function ActionButton({icon, label, onPress}: ActionButtonProps): React.JSX.Element {
  return (
    <TouchableOpacity
      style={actionStyles.button}
      onPress={onPress}
      activeOpacity={0.7}
      accessibilityRole="button"
      accessibilityLabel={label}>
      <Text style={actionStyles.icon}>{icon}</Text>
      <Text style={actionStyles.label}>{label}</Text>
    </TouchableOpacity>
  );
}

const actionStyles = StyleSheet.create({
  button: {
    alignItems: 'center',
    paddingVertical: 12,
    paddingHorizontal: 24,
  },
  icon: {
    fontSize: 24,
    marginBottom: 4,
  },
  label: {
    fontSize: 12,
    color: '#FFFFFF',
    fontWeight: '500',
  },
});

interface PageIndicatorProps {
  total: number;
  current: number;
}

/**
 * PageIndicator - displays dots for current photo position.
 */
function PageIndicator({total, current}: PageIndicatorProps): React.JSX.Element | null {
  // Don't show indicator for single photo
  if (total <= 1) {
    return null;
  }

  // For many photos, show text indicator instead of dots
  if (total > 10) {
    return (
      <View style={indicatorStyles.textContainer}>
        <Text style={indicatorStyles.text}>
          {current + 1} / {total}
        </Text>
      </View>
    );
  }

  return (
    <View style={indicatorStyles.container}>
      {Array.from({length: total}).map((_, index) => (
        <View
          key={index}
          style={[
            indicatorStyles.dot,
            index === current && indicatorStyles.activeDot,
          ]}
        />
      ))}
    </View>
  );
}

const indicatorStyles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    justifyContent: 'center',
    alignItems: 'center',
    gap: 8,
  },
  dot: {
    width: 8,
    height: 8,
    borderRadius: 4,
    backgroundColor: 'rgba(255, 255, 255, 0.4)',
  },
  activeDot: {
    backgroundColor: '#FFFFFF',
  },
  textContainer: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    backgroundColor: 'rgba(0, 0, 0, 0.5)',
    borderRadius: 12,
  },
  text: {
    color: '#FFFFFF',
    fontSize: 14,
    fontWeight: '500',
  },
});

// ============================================================================
// Main Component
// ============================================================================

/**
 * PhotoViewer - full-screen photo viewer with pinch-zoom and swipe navigation.
 *
 * Displays photos in a modal overlay with:
 * - Horizontal swipe between photos
 * - Pinch-to-zoom gesture support
 * - Double-tap to zoom toggle
 * - Download and share actions
 * - Caption display
 *
 * @example
 * ```tsx
 * <PhotoViewer
 *   photos={report.photos}
 *   initialIndex={selectedIndex}
 *   visible={viewerVisible}
 *   onClose={() => setViewerVisible(false)}
 *   onDownload={(photo) => handleDownload(photo)}
 *   onShare={(photo) => handleShare(photo)}
 * />
 * ```
 */
function PhotoViewer({
  photos,
  initialIndex = 0,
  visible,
  onClose,
  onDownload,
  onShare,
}: PhotoViewerProps): React.JSX.Element | null {
  const insets = useSafeAreaInsets();
  const flatListRef = useRef<FlatList<Photo>>(null);
  const [currentIndex, setCurrentIndex] = useState(initialIndex);

  // Calculate image dimensions
  const imageWidth = SCREEN_WIDTH;
  const imageHeight = SCREEN_HEIGHT - insets.top - insets.bottom - 120; // Leave room for header/footer

  const currentPhoto = photos[currentIndex];

  /**
   * Handle scroll end to update current index.
   */
  const handleMomentumScrollEnd = useCallback(
    (event: {nativeEvent: {contentOffset: {x: number}}}) => {
      const offsetX = event.nativeEvent.contentOffset.x;
      const newIndex = Math.round(offsetX / SCREEN_WIDTH);
      if (newIndex >= 0 && newIndex < photos.length) {
        setCurrentIndex(newIndex);
      }
    },
    [photos.length],
  );

  /**
   * Handle download action.
   */
  const handleDownload = useCallback(() => {
    if (currentPhoto && onDownload) {
      onDownload(currentPhoto);
    }
  }, [currentPhoto, onDownload]);

  /**
   * Handle share action.
   */
  const handleShare = useCallback(() => {
    if (currentPhoto && onShare) {
      onShare(currentPhoto);
    }
  }, [currentPhoto, onShare]);

  /**
   * Render individual photo item.
   */
  const renderItem = useCallback(
    ({item}: {item: Photo}) => (
      <View style={[styles.photoContainer, {width: SCREEN_WIDTH}]}>
        <ZoomableImage
          photo={item}
          width={imageWidth}
          height={imageHeight}
        />
      </View>
    ),
    [imageWidth, imageHeight],
  );

  const keyExtractor = useCallback((item: Photo) => item.id, []);

  const getItemLayout = useCallback(
    (_: ArrayLike<Photo> | null | undefined, index: number) => ({
      length: SCREEN_WIDTH,
      offset: SCREEN_WIDTH * index,
      index,
    }),
    [],
  );

  // Don't render if not visible or no photos
  if (!visible || photos.length === 0) {
    return null;
  }

  return (
    <Modal
      visible={visible}
      transparent={false}
      animationType="fade"
      onRequestClose={onClose}
      statusBarTranslucent>
      <StatusBar barStyle="light-content" backgroundColor="#000000" />
      <View style={styles.container}>
        {/* Header with close button */}
        <View style={[styles.header, {paddingTop: insets.top}]}>
          <TouchableOpacity
            style={styles.closeButton}
            onPress={onClose}
            activeOpacity={0.7}
            accessibilityRole="button"
            accessibilityLabel="Close photo viewer"
            accessibilityHint="Returns to the previous screen">
            <Text style={styles.closeIcon}>✕</Text>
          </TouchableOpacity>
          <View style={styles.headerCenter}>
            <PageIndicator total={photos.length} current={currentIndex} />
          </View>
          <View style={styles.headerSpacer} />
        </View>

        {/* Photo gallery */}
        <FlatList
          ref={flatListRef}
          data={photos}
          keyExtractor={keyExtractor}
          renderItem={renderItem}
          horizontal
          pagingEnabled
          showsHorizontalScrollIndicator={false}
          initialScrollIndex={initialIndex}
          getItemLayout={getItemLayout}
          onMomentumScrollEnd={handleMomentumScrollEnd}
          bounces={false}
          decelerationRate="fast"
          accessibilityRole="list"
          accessibilityLabel={`Photo viewer, showing photo ${currentIndex + 1} of ${photos.length}`}
        />

        {/* Footer with caption and actions */}
        <View style={[styles.footer, {paddingBottom: insets.bottom + 8}]}>
          {/* Caption */}
          {currentPhoto?.caption && (
            <View style={styles.captionContainer}>
              <Text style={styles.caption} numberOfLines={3}>
                {currentPhoto.caption}
              </Text>
            </View>
          )}

          {/* Action buttons */}
          {(onDownload || onShare) && (
            <View style={styles.actionsContainer}>
              {onDownload && (
                <ActionButton
                  icon="⬇️"
                  label="Save"
                  onPress={handleDownload}
                />
              )}
              {onShare && (
                <ActionButton
                  icon="📤"
                  label="Share"
                  onPress={handleShare}
                />
              )}
            </View>
          )}
        </View>
      </View>
    </Modal>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000000',
  },
  header: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingHorizontal: 16,
    paddingBottom: 12,
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    zIndex: 10,
  },
  closeButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    backgroundColor: 'rgba(255, 255, 255, 0.2)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  closeIcon: {
    fontSize: 18,
    color: '#FFFFFF',
    fontWeight: '600',
  },
  headerCenter: {
    flex: 1,
    alignItems: 'center',
  },
  headerSpacer: {
    width: 44,
  },
  photoContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  footer: {
    position: 'absolute',
    bottom: 0,
    left: 0,
    right: 0,
    backgroundColor: 'rgba(0, 0, 0, 0.7)',
    paddingTop: 12,
    paddingHorizontal: 16,
    zIndex: 10,
  },
  captionContainer: {
    marginBottom: 12,
  },
  caption: {
    fontSize: 14,
    color: '#FFFFFF',
    lineHeight: 20,
    textAlign: 'center',
  },
  actionsContainer: {
    flexDirection: 'row',
    justifyContent: 'center',
    gap: 32,
  },
});

export default PhotoViewer;
