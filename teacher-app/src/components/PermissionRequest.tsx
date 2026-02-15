/**
 * LAYA Teacher App - PermissionRequest Component
 *
 * A reusable component for requesting app permissions with clear messaging
 * and user-friendly UI. Handles both initial permission requests and
 * blocked/denied state with settings redirect.
 */

import React from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
  ActivityIndicator,
} from 'react-native';

/**
 * Permission type for display customization
 */
export type PermissionType = 'camera' | 'photoLibrary' | 'both';

interface PermissionRequestProps {
  /** Type of permission being requested */
  permissionType: PermissionType;
  /** Whether the permission has been permanently blocked */
  isBlocked?: boolean;
  /** Whether a request is currently in progress */
  isLoading?: boolean;
  /** Callback when user taps request permission button */
  onRequestPermission: () => void;
  /** Callback when user taps open settings button */
  onOpenSettings: () => void;
  /** Custom title override */
  title?: string;
  /** Custom description override */
  description?: string;
}

/**
 * Get default title based on permission type
 */
function getDefaultTitle(
  permissionType: PermissionType,
  isBlocked: boolean,
): string {
  if (isBlocked) {
    switch (permissionType) {
      case 'camera':
        return 'Camera Access Blocked';
      case 'photoLibrary':
        return 'Photo Access Blocked';
      case 'both':
        return 'Camera & Photos Access Blocked';
    }
  }

  switch (permissionType) {
    case 'camera':
      return 'Camera Access Required';
    case 'photoLibrary':
      return 'Photo Library Access Required';
    case 'both':
      return 'Camera & Photos Access Required';
  }
}

/**
 * Get default description based on permission type
 */
function getDefaultDescription(
  permissionType: PermissionType,
  isBlocked: boolean,
): string {
  if (isBlocked) {
    switch (permissionType) {
      case 'camera':
        return 'Camera access has been blocked. To capture photos of classroom activities, please enable camera access in Settings.';
      case 'photoLibrary':
        return 'Photo library access has been blocked. To save and share photos, please enable photo access in Settings.';
      case 'both':
        return 'Camera and photo library access has been blocked. To capture and share photos of classroom activities, please enable these permissions in Settings.';
    }
  }

  switch (permissionType) {
    case 'camera':
      return 'To capture photos of classroom activities and share them with parents, we need access to your camera.';
    case 'photoLibrary':
      return 'To save and share photos, we need access to your photo library.';
    case 'both':
      return 'To capture and share photos of classroom activities with parents, we need access to your camera and photo library.';
  }
}

/**
 * Get icon character based on permission type
 */
function getIcon(permissionType: PermissionType): string {
  switch (permissionType) {
    case 'camera':
      return '\u{1F4F7}'; // Camera emoji
    case 'photoLibrary':
      return '\u{1F5BC}'; // Framed picture emoji
    case 'both':
      return '\u{1F4F8}'; // Camera with flash emoji
  }
}

/**
 * PermissionRequest displays a clear message and action button
 * for requesting app permissions.
 *
 * Shows different UI based on whether the permission is:
 * - Not yet requested (show request button)
 * - Blocked (show open settings button)
 */
function PermissionRequest({
  permissionType,
  isBlocked = false,
  isLoading = false,
  onRequestPermission,
  onOpenSettings,
  title,
  description,
}: PermissionRequestProps): React.JSX.Element {
  const displayTitle = title ?? getDefaultTitle(permissionType, isBlocked);
  const displayDescription =
    description ?? getDefaultDescription(permissionType, isBlocked);
  const icon = getIcon(permissionType);

  const handlePress = () => {
    if (isBlocked) {
      onOpenSettings();
    } else {
      onRequestPermission();
    }
  };

  return (
    <View style={styles.container}>
      <View style={styles.iconContainer}>
        <Text style={styles.icon}>{icon}</Text>
      </View>

      <Text style={styles.title}>{displayTitle}</Text>

      <Text style={styles.description}>{displayDescription}</Text>

      <TouchableOpacity
        style={[styles.button, isLoading && styles.buttonDisabled]}
        onPress={handlePress}
        disabled={isLoading}
        activeOpacity={0.8}
        accessibilityRole="button"
        accessibilityLabel={
          isBlocked ? 'Open settings to enable permissions' : 'Request access'
        }>
        {isLoading ? (
          <ActivityIndicator size="small" color="#FFFFFF" />
        ) : (
          <Text style={styles.buttonText}>
            {isBlocked ? 'Open Settings' : 'Allow Access'}
          </Text>
        )}
      </TouchableOpacity>

      {!isBlocked && (
        <Text style={styles.privacyNote}>
          Your privacy matters. Photos are only shared with parents of tagged
          children.
        </Text>
      )}

      {isBlocked && (
        <View style={styles.stepsContainer}>
          <Text style={styles.stepsTitle}>To enable access:</Text>
          <Text style={styles.stepText}>1. Tap "Open Settings" below</Text>
          <Text style={styles.stepText}>
            2. Find "LAYA Teacher" in the list
          </Text>
          <Text style={styles.stepText}>
            3. Enable{' '}
            {permissionType === 'camera'
              ? 'Camera'
              : permissionType === 'photoLibrary'
                ? 'Photos'
                : 'Camera and Photos'}
          </Text>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F5F5F5',
    padding: 32,
  },
  iconContainer: {
    width: 100,
    height: 100,
    borderRadius: 50,
    backgroundColor: '#E3F2FD',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 24,
  },
  icon: {
    fontSize: 48,
  },
  title: {
    fontSize: 22,
    fontWeight: '600',
    color: '#333333',
    textAlign: 'center',
    marginBottom: 12,
  },
  description: {
    fontSize: 16,
    color: '#666666',
    textAlign: 'center',
    lineHeight: 24,
    marginBottom: 32,
    paddingHorizontal: 16,
  },
  button: {
    backgroundColor: '#4A90D9',
    paddingVertical: 16,
    paddingHorizontal: 48,
    borderRadius: 12,
    minWidth: 200,
    alignItems: 'center',
    // iOS shadow
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.15,
    shadowRadius: 4,
    // Android elevation
    elevation: 3,
  },
  buttonDisabled: {
    opacity: 0.6,
  },
  buttonText: {
    color: '#FFFFFF',
    fontSize: 17,
    fontWeight: '600',
  },
  privacyNote: {
    fontSize: 13,
    color: '#999999',
    textAlign: 'center',
    marginTop: 24,
    paddingHorizontal: 24,
    lineHeight: 20,
  },
  stepsContainer: {
    marginTop: 32,
    padding: 16,
    backgroundColor: '#FFF8E1',
    borderRadius: 12,
    borderLeftWidth: 4,
    borderLeftColor: '#FFC107',
    width: '100%',
  },
  stepsTitle: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 8,
  },
  stepText: {
    fontSize: 14,
    color: '#666666',
    marginVertical: 2,
    lineHeight: 22,
  },
});

export default PermissionRequest;
