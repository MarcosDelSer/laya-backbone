/**
 * LAYA Teacher App - useCameraPermission Hook
 *
 * A custom hook for managing camera and photo library permissions.
 * Handles permission checking, requesting, and tracking permission state.
 *
 * Uses react-native-permissions for consistent cross-platform permission handling.
 */

import {useState, useEffect, useCallback} from 'react';
import {Platform, Linking, Alert} from 'react-native';
import {
  check,
  request,
  PERMISSIONS,
  RESULTS,
  openSettings as openNativeSettings,
  Permission,
} from 'react-native-permissions';

/**
 * Permission status values
 */
export type PermissionStatus =
  | 'unavailable'
  | 'denied'
  | 'limited'
  | 'granted'
  | 'blocked';

/**
 * Camera permission state
 */
export interface CameraPermissionState {
  /** Camera permission status */
  cameraStatus: PermissionStatus;
  /** Photo library permission status */
  photoLibraryStatus: PermissionStatus;
  /** Whether camera is available and granted */
  isCameraAvailable: boolean;
  /** Whether photo library is available and granted */
  isPhotoLibraryAvailable: boolean;
  /** Whether currently checking permissions */
  isLoading: boolean;
  /** Error message if permission check failed */
  error: string | null;
}

/**
 * Camera permission actions
 */
export interface CameraPermissionActions {
  /** Request camera permission */
  requestCameraPermission: () => Promise<PermissionStatus>;
  /** Request photo library permission */
  requestPhotoLibraryPermission: () => Promise<PermissionStatus>;
  /** Request both camera and photo library permissions */
  requestAllPermissions: () => Promise<{
    camera: PermissionStatus;
    photoLibrary: PermissionStatus;
  }>;
  /** Re-check current permission status */
  checkPermissions: () => Promise<void>;
  /** Open app settings (for blocked permissions) */
  openSettings: () => Promise<void>;
}

/**
 * Return type for the useCameraPermission hook
 */
export type UseCameraPermissionReturn = [
  CameraPermissionState,
  CameraPermissionActions,
];

/**
 * Options for the useCameraPermission hook
 */
export interface UseCameraPermissionOptions {
  /** Whether to check permissions on mount (default: true) */
  checkOnMount?: boolean;
}

/**
 * Map react-native-permissions RESULTS to our PermissionStatus type
 */
function mapPermissionResult(result: string): PermissionStatus {
  switch (result) {
    case RESULTS.UNAVAILABLE:
      return 'unavailable';
    case RESULTS.DENIED:
      return 'denied';
    case RESULTS.LIMITED:
      return 'limited';
    case RESULTS.GRANTED:
      return 'granted';
    case RESULTS.BLOCKED:
      return 'blocked';
    default:
      return 'denied';
  }
}

/**
 * Get the camera permission for the current platform
 */
function getCameraPermission(): Permission {
  return Platform.OS === 'ios'
    ? PERMISSIONS.IOS.CAMERA
    : PERMISSIONS.ANDROID.CAMERA;
}

/**
 * Get the photo library permission for the current platform
 * Note: Android 13+ (API 33) uses READ_MEDIA_IMAGES instead of READ_EXTERNAL_STORAGE
 */
function getPhotoLibraryPermission(): Permission {
  if (Platform.OS === 'ios') {
    return PERMISSIONS.IOS.PHOTO_LIBRARY;
  }

  // Android 13+ (API 33) uses granular media permissions
  // READ_MEDIA_IMAGES for accessing photos
  // For older versions, fall back to READ_EXTERNAL_STORAGE
  // Note: The library handles API level detection internally
  return Platform.Version >= 33
    ? PERMISSIONS.ANDROID.READ_MEDIA_IMAGES
    : PERMISSIONS.ANDROID.READ_EXTERNAL_STORAGE;
}

/**
 * Check camera permission using react-native-permissions
 */
async function checkCameraPermission(): Promise<PermissionStatus> {
  const permission = getCameraPermission();
  const result = await check(permission);
  return mapPermissionResult(result);
}

/**
 * Check photo library permission using react-native-permissions
 */
async function checkPhotoLibraryPermission(): Promise<PermissionStatus> {
  const permission = getPhotoLibraryPermission();
  const result = await check(permission);
  return mapPermissionResult(result);
}

/**
 * Request camera permission using react-native-permissions
 */
async function requestCameraPermissionNative(): Promise<PermissionStatus> {
  const permission = getCameraPermission();
  const result = await request(permission);
  return mapPermissionResult(result);
}

/**
 * Request photo library permission using react-native-permissions
 */
async function requestPhotoLibraryPermissionNative(): Promise<PermissionStatus> {
  const permission = getPhotoLibraryPermission();
  const result = await request(permission);
  return mapPermissionResult(result);
}

/**
 * Custom hook for managing camera and photo library permissions
 *
 * Provides a clean interface for checking and requesting permissions
 * with proper error handling and loading states.
 *
 * @param options - Hook configuration options
 * @returns [state, actions] - Permission state and control actions
 *
 * @example
 * ```tsx
 * const [permissions, actions] = useCameraPermission();
 *
 * if (!permissions.isCameraAvailable) {
 *   return (
 *     <PermissionRequest
 *       onRequest={actions.requestCameraPermission}
 *       onOpenSettings={actions.openSettings}
 *     />
 *   );
 * }
 *
 * // Camera is available, show camera view
 * return <CameraView />;
 * ```
 */
export function useCameraPermission(
  options: UseCameraPermissionOptions = {},
): UseCameraPermissionReturn {
  const {checkOnMount = true} = options;

  const [cameraStatus, setCameraStatus] = useState<PermissionStatus>('denied');
  const [photoLibraryStatus, setPhotoLibraryStatus] =
    useState<PermissionStatus>('denied');
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  /**
   * Check all permissions
   */
  const checkPermissions = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const [camera, photoLibrary] = await Promise.all([
        checkCameraPermission(),
        checkPhotoLibraryPermission(),
      ]);

      setCameraStatus(camera);
      setPhotoLibraryStatus(photoLibrary);
    } catch (err) {
      const message =
        err instanceof Error ? err.message : 'Failed to check permissions';
      setError(message);
    } finally {
      setIsLoading(false);
    }
  }, []);

  /**
   * Request camera permission
   */
  const requestCameraPermission =
    useCallback(async (): Promise<PermissionStatus> => {
      setIsLoading(true);
      setError(null);

      try {
        const status = await requestCameraPermissionNative();
        setCameraStatus(status);
        return status;
      } catch (err) {
        const message =
          err instanceof Error ? err.message : 'Failed to request permission';
        setError(message);
        return 'denied';
      } finally {
        setIsLoading(false);
      }
    }, []);

  /**
   * Request photo library permission
   */
  const requestPhotoLibraryPermission =
    useCallback(async (): Promise<PermissionStatus> => {
      setIsLoading(true);
      setError(null);

      try {
        const status = await requestPhotoLibraryPermissionNative();
        setPhotoLibraryStatus(status);
        return status;
      } catch (err) {
        const message =
          err instanceof Error ? err.message : 'Failed to request permission';
        setError(message);
        return 'denied';
      } finally {
        setIsLoading(false);
      }
    }, []);

  /**
   * Request all permissions at once
   */
  const requestAllPermissions = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const [camera, photoLibrary] = await Promise.all([
        requestCameraPermissionNative(),
        requestPhotoLibraryPermissionNative(),
      ]);

      setCameraStatus(camera);
      setPhotoLibraryStatus(photoLibrary);

      return {camera, photoLibrary};
    } catch (err) {
      const message =
        err instanceof Error ? err.message : 'Failed to request permissions';
      setError(message);
      return {camera: 'denied' as PermissionStatus, photoLibrary: 'denied' as PermissionStatus};
    } finally {
      setIsLoading(false);
    }
  }, []);

  /**
   * Open app settings using react-native-permissions
   */
  const openSettings = useCallback(async () => {
    try {
      await openNativeSettings();
    } catch (err) {
      // Fallback to Linking if react-native-permissions fails
      try {
        if (Platform.OS === 'ios') {
          await Linking.openURL('app-settings:');
        } else {
          await Linking.openSettings();
        }
      } catch {
        Alert.alert(
          'Unable to Open Settings',
          'Please open Settings manually and grant camera permissions to this app.',
        );
      }
    }
  }, []);

  // Check permissions on mount if enabled
  useEffect(() => {
    if (checkOnMount) {
      checkPermissions();
    } else {
      setIsLoading(false);
    }
  }, [checkOnMount, checkPermissions]);

  // Calculate derived state
  const isCameraAvailable =
    cameraStatus === 'granted' || cameraStatus === 'limited';
  const isPhotoLibraryAvailable =
    photoLibraryStatus === 'granted' || photoLibraryStatus === 'limited';

  const state: CameraPermissionState = {
    cameraStatus,
    photoLibraryStatus,
    isCameraAvailable,
    isPhotoLibraryAvailable,
    isLoading,
    error,
  };

  const actions: CameraPermissionActions = {
    requestCameraPermission,
    requestPhotoLibraryPermission,
    requestAllPermissions,
    checkPermissions,
    openSettings,
  };

  return [state, actions];
}

export default useCameraPermission;
