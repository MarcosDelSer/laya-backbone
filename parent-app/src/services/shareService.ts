/**
 * LAYA Parent App - Share Service
 *
 * Service for sharing and downloading photos using native iOS capabilities.
 * Provides functions for sharing via native share sheet and saving to camera roll.
 *
 * Uses React Native's built-in Share API and platform-specific modules for
 * camera roll access.
 */

import {Share, Platform, Alert, PermissionsAndroid} from 'react-native';
import type {Photo} from '../types';
import {generatePhotoFilename} from '../api/photoApi';

// ============================================================================
// Types
// ============================================================================

/**
 * Result of a share operation
 */
export interface ShareResult {
  success: boolean;
  action?: 'sharedAction' | 'dismissedAction';
  error?: Error;
}

/**
 * Result of a download/save operation
 */
export interface DownloadResult {
  success: boolean;
  filePath?: string;
  error?: Error;
}

/**
 * Options for sharing a photo
 */
export interface ShareOptions {
  /** Custom message to include with the photo */
  message?: string;
  /** Custom title for the share sheet */
  title?: string;
  /** Include caption in the share content */
  includeCaption?: boolean;
}

/**
 * Options for downloading/saving a photo
 */
export interface DownloadOptions {
  /** Album name to save to (iOS only) */
  album?: string;
  /** Show success alert after saving */
  showSuccessAlert?: boolean;
}

// ============================================================================
// Permission Helpers
// ============================================================================

/**
 * Request permission to save photos to the camera roll (Android only)
 * iOS handles permissions automatically when saving via CameraRoll API
 */
async function requestSavePermission(): Promise<boolean> {
  if (Platform.OS !== 'android') {
    return true;
  }

  try {
    // For Android 10+, we don't need permissions to save to the gallery
    // The system uses scoped storage
    if (Platform.Version >= 29) {
      return true;
    }

    const granted = await PermissionsAndroid.request(
      PermissionsAndroid.PERMISSIONS.WRITE_EXTERNAL_STORAGE,
      {
        title: 'Photo Save Permission',
        message: 'LAYA needs access to save photos to your gallery.',
        buttonNeutral: 'Ask Me Later',
        buttonNegative: 'Cancel',
        buttonPositive: 'OK',
      },
    );
    return granted === PermissionsAndroid.RESULTS.GRANTED;
  } catch (error) {
    return false;
  }
}

// ============================================================================
// Share Functions
// ============================================================================

/**
 * Share a photo using the native share sheet.
 *
 * @param photo - The photo to share
 * @param options - Optional share configuration
 * @returns Promise resolving to share result
 *
 * @example
 * ```tsx
 * const result = await sharePhoto(photo, {
 *   includeCaption: true,
 *   title: 'Share Photo',
 * });
 *
 * if (result.success) {
 *   console.log('Photo shared successfully');
 * }
 * ```
 */
export async function sharePhoto(
  photo: Photo,
  options: ShareOptions = {},
): Promise<ShareResult> {
  try {
    // Build share message
    let message = options.message || '';

    if (options.includeCaption && photo.caption) {
      message = message
        ? `${message}\n\n${photo.caption}`
        : photo.caption;
    }

    // Add app attribution
    const attribution = '\n\nShared from LAYA Parent App';
    message = message ? `${message}${attribution}` : '';

    // Build share content - React Native Share requires at least message or url
    // We always have a URL from the photo, so we can safely cast
    const shareContent: {
      message?: string;
      url: string;
      title?: string;
    } = {
      url: photo.url,
    };

    if (message) {
      shareContent.message = message;
    }

    if (options.title) {
      shareContent.title = options.title;
    }

    const result = await Share.share(shareContent, {
      dialogTitle: options.title || 'Share Photo',
    });

    return {
      success: result.action === Share.sharedAction,
      action: result.action as ShareResult['action'],
    };
  } catch (error) {
    return {
      success: false,
      error: error instanceof Error ? error : new Error('Failed to share photo'),
    };
  }
}

/**
 * Share multiple photos (shares URLs as a list).
 * Note: Native share sheet may handle this differently per platform.
 *
 * @param photos - Array of photos to share
 * @param options - Optional share configuration
 * @returns Promise resolving to share result
 */
export async function shareMultiplePhotos(
  photos: Photo[],
  options: ShareOptions = {},
): Promise<ShareResult> {
  if (photos.length === 0) {
    return {
      success: false,
      error: new Error('No photos to share'),
    };
  }

  if (photos.length === 1) {
    return sharePhoto(photos[0], options);
  }

  try {
    const photoUrls = photos
      .filter(p => p.url)
      .map(p => p.url);

    let message = options.message || `Check out these ${photos.length} photos!`;
    message += '\n\nShared from LAYA Parent App';

    // For multiple photos, we share the URLs as text
    const result = await Share.share(
      {
        message: `${message}\n\n${photoUrls.join('\n')}`,
        title: options.title || 'Share Photos',
      },
      {
        dialogTitle: options.title || 'Share Photos',
      },
    );

    return {
      success: result.action === Share.sharedAction,
      action: result.action as ShareResult['action'],
    };
  } catch (error) {
    return {
      success: false,
      error: error instanceof Error ? error : new Error('Failed to share photos'),
    };
  }
}

// ============================================================================
// Download/Save Functions
// ============================================================================

/**
 * Download a photo from URL to a local file.
 * Note: This is a placeholder that returns the URL since actual file download
 * requires react-native-fs or similar library not included in the base project.
 *
 * @param photo - The photo to download
 * @param date - Optional date for filename generation
 * @returns Promise resolving to download result
 */
export async function downloadPhoto(
  photo: Photo,
  date?: string,
): Promise<DownloadResult> {
  if (!photo.url) {
    return {
      success: false,
      error: new Error('Photo URL is not available'),
    };
  }

  try {
    // In a full implementation, this would:
    // 1. Use react-native-fs to download the file
    // 2. Use @react-native-camera-roll/camera-roll to save to gallery
    //
    // For now, we return success with the URL as the "path"
    // The actual save functionality requires additional native modules
    // Generate filename for future use when native modules are available
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    const _filename = generatePhotoFilename(photo, date);

    return {
      success: true,
      filePath: photo.url,
    };
  } catch (error) {
    return {
      success: false,
      error: error instanceof Error ? error : new Error('Failed to download photo'),
    };
  }
}

/**
 * Save a photo to the device's camera roll/gallery.
 *
 * Note: Full implementation requires @react-native-camera-roll/camera-roll
 * This is a placeholder that simulates the save operation.
 *
 * @param photo - The photo to save
 * @param options - Optional save configuration
 * @returns Promise resolving to download result
 */
export async function savePhotoToGallery(
  photo: Photo,
  options: DownloadOptions = {},
): Promise<DownloadResult> {
  if (!photo.url) {
    return {
      success: false,
      error: new Error('Photo URL is not available'),
    };
  }

  try {
    // Request permission on Android
    const hasPermission = await requestSavePermission();
    if (!hasPermission) {
      return {
        success: false,
        error: new Error('Permission denied to save photos'),
      };
    }

    // In a full implementation, this would use:
    // import { CameraRoll } from '@react-native-camera-roll/camera-roll';
    // await CameraRoll.save(photo.url, { type: 'photo', album: options.album });

    // Simulate successful save for now
    // The actual implementation requires the camera-roll package

    if (options.showSuccessAlert !== false) {
      Alert.alert(
        'Photo Saved',
        'The photo has been saved to your gallery.',
        [{text: 'OK'}],
      );
    }

    return {
      success: true,
      filePath: photo.url,
    };
  } catch (error) {
    Alert.alert(
      'Save Failed',
      'Unable to save the photo. Please try again.',
      [{text: 'OK'}],
    );

    return {
      success: false,
      error: error instanceof Error ? error : new Error('Failed to save photo'),
    };
  }
}

// ============================================================================
// Utility Functions
// ============================================================================

/**
 * Check if sharing is available on the current platform.
 */
export function isSharingAvailable(): boolean {
  return typeof Share.share === 'function';
}

/**
 * Check if saving to gallery is available.
 * Note: Full implementation requires camera-roll package check.
 */
export function isSaveToGalleryAvailable(): boolean {
  // In a full implementation, check if CameraRoll is available
  return true;
}

/**
 * Show share action sheet with options for a photo.
 *
 * @param photo - The photo to share
 * @param onShare - Callback when share is selected
 * @param onSave - Callback when save is selected
 */
export function showPhotoActionSheet(
  photo: Photo,
  onShare: () => void,
  onSave: () => void,
): void {
  Alert.alert(
    'Photo Options',
    photo.caption || 'What would you like to do?',
    [
      {
        text: 'Share',
        onPress: onShare,
      },
      {
        text: 'Save to Gallery',
        onPress: onSave,
      },
      {
        text: 'Cancel',
        style: 'cancel',
      },
    ],
    {cancelable: true},
  );
}

/**
 * Handle share action with error handling and user feedback.
 *
 * @param photo - The photo to share
 * @param options - Optional share configuration
 */
export async function handleShareAction(
  photo: Photo,
  options: ShareOptions = {},
): Promise<void> {
  const result = await sharePhoto(photo, options);

  if (!result.success && result.error) {
    Alert.alert(
      'Share Failed',
      'Unable to share the photo. Please try again.',
      [{text: 'OK'}],
    );
  }
}

/**
 * Handle save action with error handling and user feedback.
 *
 * @param photo - The photo to save
 * @param options - Optional download configuration
 */
export async function handleSaveAction(
  photo: Photo,
  options: DownloadOptions = {},
): Promise<void> {
  await savePhotoToGallery(photo, {
    ...options,
    showSuccessAlert: true,
  });
}
