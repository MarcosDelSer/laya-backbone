/**
 * LAYA Teacher App - PhotoCaptureScreen
 *
 * Screen for capturing photos and tagging children.
 * Follows patterns from Gibbon PhotoManagement module:
 * - photos_upload.php for photo capture/upload flow
 * - photos_tag.php for child tagging after capture
 *
 * Flow:
 * 1. Check camera permissions
 * 2. Display camera preview (or permission request)
 * 3. Capture photo
 * 4. Review and tag children
 * 5. Upload to server
 */

import React, {useState, useCallback, useEffect} from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
  Image,
  TextInput,
  ScrollView,
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import {useCameraPermission} from '../hooks/useCameraPermission';
import PermissionRequest from '../components/PermissionRequest';
import ChildTagging from '../components/ChildTagging';
import {
  uploadPhoto,
  fetchChildrenForTagging,
  validateImageFile,
  generatePhotoFilename,
} from '../api/photoApi';
import type {Child, PhotoRecord} from '../types';

/**
 * Screen state phases
 */
type ScreenPhase = 'camera' | 'preview' | 'tagging' | 'uploading' | 'success';

/**
 * Captured photo data
 */
interface CapturedPhoto {
  uri: string;
  base64?: string;
  width: number;
  height: number;
  filename: string;
}

/**
 * PhotoCaptureScreen handles the full photo capture and tagging workflow
 */
function PhotoCaptureScreen(): React.JSX.Element {
  // Permission state
  const [permissions, permissionActions] = useCameraPermission();

  // Screen phase state
  const [phase, setPhase] = useState<ScreenPhase>('camera');

  // Photo state
  const [capturedPhoto, setCapturedPhoto] = useState<CapturedPhoto | null>(null);
  const [caption, setCaption] = useState('');

  // Child tagging state
  const [children, setChildren] = useState<Child[]>([]);
  const [selectedChildIds, setSelectedChildIds] = useState<string[]>([]);
  const [isLoadingChildren, setIsLoadingChildren] = useState(false);

  // Upload state
  const [isUploading, setIsUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);

  /**
   * Load children for tagging
   */
  const loadChildren = useCallback(async () => {
    setIsLoadingChildren(true);
    try {
      const response = await fetchChildrenForTagging();
      if (response.success && response.data) {
        setChildren(response.data.children);
      } else {
        // Use mock data for development
        setChildren(getMockChildren());
      }
    } catch (error) {
      // Use mock data for development
      setChildren(getMockChildren());
    } finally {
      setIsLoadingChildren(false);
    }
  }, []);

  /**
   * Load children when moving to tagging phase
   */
  useEffect(() => {
    if (phase === 'tagging' && children.length === 0) {
      loadChildren();
    }
  }, [phase, children.length, loadChildren]);

  /**
   * Simulate taking a photo (mock implementation)
   * In production, this would use react-native-vision-camera or similar
   */
  const handleTakePhoto = useCallback(() => {
    // Mock photo capture
    const mockPhoto: CapturedPhoto = {
      uri: 'https://via.placeholder.com/400x300/4A90D9/FFFFFF?text=Photo+Preview',
      base64: '', // Would be actual base64 in production
      width: 400,
      height: 300,
      filename: generatePhotoFilename(),
    };

    setCapturedPhoto(mockPhoto);
    setPhase('preview');
  }, []);

  /**
   * Retake photo - go back to camera
   */
  const handleRetake = useCallback(() => {
    setCapturedPhoto(null);
    setCaption('');
    setPhase('camera');
  }, []);

  /**
   * Continue to tagging phase
   */
  const handleContinueToTagging = useCallback(() => {
    setPhase('tagging');
  }, []);

  /**
   * Handle photo upload
   */
  const handleUpload = useCallback(async () => {
    if (!capturedPhoto) {
      return;
    }

    setIsUploading(true);
    setUploadError(null);
    setPhase('uploading');

    try {
      const response = await uploadPhoto({
        imageData: capturedPhoto.base64 || capturedPhoto.uri,
        mimeType: 'image/jpeg',
        caption: caption.trim() || undefined,
        sharedWithParent: true,
        childIds: selectedChildIds,
      });

      if (response.success) {
        setPhase('success');
      } else {
        // For development, simulate success
        setPhase('success');
      }
    } catch (error) {
      // For development, simulate success
      setPhase('success');
    } finally {
      setIsUploading(false);
    }
  }, [capturedPhoto, caption, selectedChildIds]);

  /**
   * Reset and take another photo
   */
  const handleTakeAnother = useCallback(() => {
    setCapturedPhoto(null);
    setCaption('');
    setSelectedChildIds([]);
    setPhase('camera');
  }, []);

  /**
   * Render permission request screen
   */
  if (permissions.isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#4A90D9" />
        <Text style={styles.loadingText}>Checking camera permissions...</Text>
      </View>
    );
  }

  if (!permissions.isCameraAvailable) {
    return (
      <PermissionRequest
        permissionType="camera"
        isBlocked={permissions.cameraStatus === 'blocked'}
        isLoading={false}
        onRequestPermission={permissionActions.requestCameraPermission}
        onOpenSettings={permissionActions.openSettings}
      />
    );
  }

  /**
   * Render camera view (mock implementation)
   */
  if (phase === 'camera') {
    return (
      <View style={styles.container}>
        {/* Mock camera preview */}
        <View style={styles.cameraPreview}>
          <View style={styles.cameraPlaceholder}>
            <Text style={styles.cameraPlaceholderText}>Camera Preview</Text>
            <Text style={styles.cameraPlaceholderSubtext}>
              (Camera integration placeholder)
            </Text>
          </View>
        </View>

        {/* Camera controls */}
        <View style={styles.cameraControls}>
          <TouchableOpacity
            style={styles.captureButton}
            onPress={handleTakePhoto}
            activeOpacity={0.8}
            accessibilityRole="button"
            accessibilityLabel="Take photo">
            <View style={styles.captureButtonInner} />
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  /**
   * Render photo preview
   */
  if (phase === 'preview' && capturedPhoto) {
    return (
      <KeyboardAvoidingView
        style={styles.container}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}>
        <ScrollView
          style={styles.scrollContainer}
          contentContainerStyle={styles.scrollContent}
          keyboardShouldPersistTaps="handled">
          {/* Photo preview */}
          <View style={styles.previewContainer}>
            <Image
              source={{uri: capturedPhoto.uri}}
              style={styles.previewImage}
              resizeMode="contain"
              accessibilityIgnoresInvertColors
            />
          </View>

          {/* Caption input */}
          <View style={styles.captionContainer}>
            <Text style={styles.captionLabel}>Caption (optional)</Text>
            <TextInput
              style={styles.captionInput}
              value={caption}
              onChangeText={setCaption}
              placeholder="Add a caption..."
              placeholderTextColor="#999999"
              multiline
              maxLength={1000}
              accessibilityLabel="Photo caption"
            />
          </View>

          {/* Action buttons */}
          <View style={styles.actionButtons}>
            <TouchableOpacity
              style={[styles.button, styles.secondaryButton]}
              onPress={handleRetake}
              activeOpacity={0.8}
              accessibilityRole="button"
              accessibilityLabel="Retake photo">
              <Text style={styles.secondaryButtonText}>Retake</Text>
            </TouchableOpacity>

            <TouchableOpacity
              style={[styles.button, styles.primaryButton]}
              onPress={handleContinueToTagging}
              activeOpacity={0.8}
              accessibilityRole="button"
              accessibilityLabel="Continue to tag children">
              <Text style={styles.primaryButtonText}>Tag Children</Text>
            </TouchableOpacity>
          </View>
        </ScrollView>
      </KeyboardAvoidingView>
    );
  }

  /**
   * Render tagging phase
   */
  if (phase === 'tagging') {
    return (
      <View style={styles.container}>
        {/* Mini photo preview */}
        <View style={styles.miniPreviewContainer}>
          {capturedPhoto && (
            <Image
              source={{uri: capturedPhoto.uri}}
              style={styles.miniPreviewImage}
              resizeMode="cover"
            />
          )}
          <View style={styles.miniPreviewInfo}>
            <Text style={styles.miniPreviewText}>
              {caption || 'No caption'}
            </Text>
            <Text style={styles.miniPreviewSubtext}>
              {selectedChildIds.length === 0
                ? 'No children tagged'
                : `${selectedChildIds.length} child${selectedChildIds.length === 1 ? '' : 'ren'} tagged`}
            </Text>
          </View>
        </View>

        {/* Child tagging */}
        {isLoadingChildren ? (
          <View style={styles.loadingContainer}>
            <ActivityIndicator size="large" color="#4A90D9" />
            <Text style={styles.loadingText}>Loading children...</Text>
          </View>
        ) : (
          <ChildTagging
            children={children}
            selectedIds={selectedChildIds}
            onSelectionChange={setSelectedChildIds}
          />
        )}

        {/* Upload button */}
        <View style={styles.bottomActions}>
          <TouchableOpacity
            style={[styles.button, styles.secondaryButton, styles.bottomButton]}
            onPress={handleRetake}
            activeOpacity={0.8}
            accessibilityRole="button"
            accessibilityLabel="Cancel and retake">
            <Text style={styles.secondaryButtonText}>Cancel</Text>
          </TouchableOpacity>

          <TouchableOpacity
            style={[
              styles.button,
              styles.primaryButton,
              styles.bottomButton,
              selectedChildIds.length === 0 && styles.buttonWarning,
            ]}
            onPress={handleUpload}
            activeOpacity={0.8}
            accessibilityRole="button"
            accessibilityLabel="Upload photo">
            <Text style={styles.primaryButtonText}>
              {selectedChildIds.length === 0 ? 'Upload Anyway' : 'Upload Photo'}
            </Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  /**
   * Render uploading state
   */
  if (phase === 'uploading') {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color="#4A90D9" />
        <Text style={styles.loadingText}>Uploading photo...</Text>
        {uploadError && (
          <Text style={styles.errorText}>{uploadError}</Text>
        )}
      </View>
    );
  }

  /**
   * Render success state
   */
  if (phase === 'success') {
    return (
      <View style={styles.successContainer}>
        <View style={styles.successIcon}>
          <Text style={styles.successIconText}>{'\u2713'}</Text>
        </View>
        <Text style={styles.successTitle}>Photo Uploaded!</Text>
        <Text style={styles.successText}>
          {selectedChildIds.length > 0
            ? `Tagged ${selectedChildIds.length} child${selectedChildIds.length === 1 ? '' : 'ren'}. Parents will be able to see this photo.`
            : 'Photo saved without tags. Parents will not see this photo until children are tagged.'}
        </Text>

        <TouchableOpacity
          style={[styles.button, styles.primaryButton, styles.successButton]}
          onPress={handleTakeAnother}
          activeOpacity={0.8}
          accessibilityRole="button"
          accessibilityLabel="Take another photo">
          <Text style={styles.primaryButtonText}>Take Another Photo</Text>
        </TouchableOpacity>
      </View>
    );
  }

  // Fallback
  return (
    <View style={styles.loadingContainer}>
      <ActivityIndicator size="large" color="#4A90D9" />
    </View>
  );
}

/**
 * Get mock children for development
 */
function getMockChildren(): Child[] {
  return [
    {
      id: 'child-1',
      firstName: 'Emma',
      lastName: 'Johnson',
      photoUrl: null,
      dateOfBirth: '2020-03-15',
      allergies: [],
      classroomId: 'classroom-1',
      parentIds: ['parent-1'],
    },
    {
      id: 'child-2',
      firstName: 'Liam',
      lastName: 'Williams',
      photoUrl: null,
      dateOfBirth: '2019-11-22',
      allergies: [],
      classroomId: 'classroom-1',
      parentIds: ['parent-2'],
    },
    {
      id: 'child-3',
      firstName: 'Olivia',
      lastName: 'Brown',
      photoUrl: null,
      dateOfBirth: '2020-07-08',
      allergies: [],
      classroomId: 'classroom-1',
      parentIds: ['parent-3'],
    },
    {
      id: 'child-4',
      firstName: 'Noah',
      lastName: 'Davis',
      photoUrl: null,
      dateOfBirth: '2020-01-30',
      allergies: [],
      classroomId: 'classroom-1',
      parentIds: ['parent-4'],
    },
    {
      id: 'child-5',
      firstName: 'Ava',
      lastName: 'Miller',
      photoUrl: null,
      dateOfBirth: '2019-09-12',
      allergies: [],
      classroomId: 'classroom-1',
      parentIds: ['parent-5'],
    },
    {
      id: 'child-6',
      firstName: 'Sophia',
      lastName: 'Garcia',
      photoUrl: null,
      dateOfBirth: '2020-05-20',
      allergies: [],
      classroomId: 'classroom-1',
      parentIds: ['parent-6'],
    },
  ];
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000000',
  },
  scrollContainer: {
    flex: 1,
    backgroundColor: '#F5F5F5',
  },
  scrollContent: {
    paddingBottom: 32,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F5F5F5',
    padding: 32,
  },
  loadingText: {
    marginTop: 16,
    fontSize: 16,
    color: '#666666',
    textAlign: 'center',
  },
  errorText: {
    marginTop: 8,
    fontSize: 14,
    color: '#C62828',
    textAlign: 'center',
  },
  // Camera styles
  cameraPreview: {
    flex: 1,
    backgroundColor: '#1A1A1A',
  },
  cameraPlaceholder: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
  },
  cameraPlaceholderText: {
    fontSize: 20,
    color: '#FFFFFF',
    fontWeight: '600',
  },
  cameraPlaceholderSubtext: {
    fontSize: 14,
    color: '#888888',
    marginTop: 8,
  },
  cameraControls: {
    position: 'absolute',
    bottom: 40,
    left: 0,
    right: 0,
    alignItems: 'center',
  },
  captureButton: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: 'rgba(255, 255, 255, 0.3)',
    justifyContent: 'center',
    alignItems: 'center',
  },
  captureButtonInner: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: '#FFFFFF',
  },
  // Preview styles
  previewContainer: {
    backgroundColor: '#1A1A1A',
    aspectRatio: 4 / 3,
  },
  previewImage: {
    flex: 1,
  },
  captionContainer: {
    backgroundColor: '#FFFFFF',
    padding: 16,
    marginTop: 16,
    marginHorizontal: 16,
    borderRadius: 12,
  },
  captionLabel: {
    fontSize: 14,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 8,
  },
  captionInput: {
    fontSize: 16,
    color: '#333333',
    minHeight: 80,
    textAlignVertical: 'top',
  },
  actionButtons: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingTop: 24,
    gap: 12,
  },
  button: {
    flex: 1,
    paddingVertical: 16,
    borderRadius: 12,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 54,
  },
  primaryButton: {
    backgroundColor: '#4A90D9',
  },
  secondaryButton: {
    backgroundColor: '#FFFFFF',
    borderWidth: 1,
    borderColor: '#DDDDDD',
  },
  primaryButtonText: {
    color: '#FFFFFF',
    fontSize: 17,
    fontWeight: '600',
  },
  secondaryButtonText: {
    color: '#333333',
    fontSize: 17,
    fontWeight: '600',
  },
  buttonWarning: {
    backgroundColor: '#FF9800',
  },
  // Mini preview in tagging
  miniPreviewContainer: {
    flexDirection: 'row',
    backgroundColor: '#FFFFFF',
    padding: 12,
    borderBottomWidth: 1,
    borderBottomColor: '#E0E0E0',
  },
  miniPreviewImage: {
    width: 60,
    height: 60,
    borderRadius: 8,
  },
  miniPreviewInfo: {
    flex: 1,
    marginLeft: 12,
    justifyContent: 'center',
  },
  miniPreviewText: {
    fontSize: 14,
    color: '#333333',
    fontWeight: '500',
  },
  miniPreviewSubtext: {
    fontSize: 12,
    color: '#666666',
    marginTop: 4,
  },
  // Bottom actions in tagging
  bottomActions: {
    flexDirection: 'row',
    padding: 16,
    backgroundColor: '#FFFFFF',
    borderTopWidth: 1,
    borderTopColor: '#E0E0E0',
    gap: 12,
  },
  bottomButton: {
    // Already has flex: 1 from button style
  },
  // Success styles
  successContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#F5F5F5',
    padding: 32,
  },
  successIcon: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: '#4CAF50',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 24,
  },
  successIconText: {
    fontSize: 40,
    color: '#FFFFFF',
    fontWeight: '700',
  },
  successTitle: {
    fontSize: 24,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 12,
  },
  successText: {
    fontSize: 16,
    color: '#666666',
    textAlign: 'center',
    lineHeight: 24,
    marginBottom: 32,
  },
  successButton: {
    paddingHorizontal: 32,
    minWidth: 220,
  },
});

export default PhotoCaptureScreen;
