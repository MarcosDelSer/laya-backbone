/**
 * LAYA Teacher App - Photo API
 *
 * API functions for photo upload and tagging functionality.
 * Follows patterns from Gibbon PhotoManagement module:
 * - photos_upload.php for file upload
 * - photos_tag.php for child tagging
 */

import {api} from './client';
import {API_CONFIG} from './config';
import type {ApiResponse, PhotoRecord, Child} from '../types';

/**
 * Request payload for photo upload
 */
export interface PhotoUploadRequest {
  /** Base64 encoded image data */
  imageData: string;
  /** MIME type of the image */
  mimeType: string;
  /** Optional caption for the photo */
  caption?: string;
  /** Whether to share with parents (default: true) */
  sharedWithParent?: boolean;
  /** Child IDs to tag in the photo */
  childIds?: string[];
}

/**
 * Response from photo upload
 */
export interface PhotoUploadResponse {
  photo: PhotoRecord;
  message: string;
}

/**
 * Request payload for tagging children in a photo
 */
export interface PhotoTagRequest {
  /** Photo ID to tag */
  photoId: string;
  /** Child IDs to tag in the photo */
  childIds: string[];
}

/**
 * Response from photo tag operation
 */
export interface PhotoTagResponse {
  photoId: string;
  taggedChildren: string[];
  message: string;
}

/**
 * Response containing children available for tagging
 */
export interface ChildrenForTaggingResponse {
  children: Child[];
}

/**
 * Upload a photo to the server
 *
 * Follows the pattern from photos_upload.php:
 * 1. Validate file type and size
 * 2. Upload to storage
 * 3. Create database record
 * 4. Optionally tag children
 *
 * @param request - Photo upload request data
 * @returns API response with uploaded photo record
 */
export async function uploadPhoto(
  request: PhotoUploadRequest,
): Promise<ApiResponse<PhotoUploadResponse>> {
  const payload = {
    imageData: request.imageData,
    mimeType: request.mimeType,
    caption: request.caption || '',
    sharedWithParent: request.sharedWithParent ?? true,
    childIds: request.childIds || [],
  };

  return api.post<PhotoUploadResponse>(
    API_CONFIG.endpoints.photos.upload,
    payload,
  );
}

/**
 * Tag children in an existing photo
 *
 * Follows the pattern from photos_tag.php:
 * 1. Get existing tags
 * 2. Determine additions and removals
 * 3. Update tags in database
 *
 * @param photoId - ID of the photo to tag
 * @param childIds - Array of child IDs to tag
 * @returns API response with tag operation result
 */
export async function tagPhotoWithChildren(
  photoId: string,
  childIds: string[],
): Promise<ApiResponse<PhotoTagResponse>> {
  const request: PhotoTagRequest = {
    photoId,
    childIds,
  };

  return api.post<PhotoTagResponse>(
    API_CONFIG.endpoints.photos.tag,
    request,
  );
}

/**
 * Fetch children available for tagging
 * Returns all enrolled children in the current classroom
 */
export async function fetchChildrenForTagging(): Promise<
  ApiResponse<ChildrenForTaggingResponse>
> {
  return api.get<ChildrenForTaggingResponse>(
    API_CONFIG.endpoints.children.list,
  );
}

/**
 * Supported image MIME types for upload
 */
export const SUPPORTED_IMAGE_TYPES = [
  'image/jpeg',
  'image/png',
  'image/gif',
  'image/heic',
] as const;

export type SupportedImageType = (typeof SUPPORTED_IMAGE_TYPES)[number];

/**
 * Maximum file size in bytes (10MB)
 */
export const MAX_FILE_SIZE_BYTES = 10 * 1024 * 1024;

/**
 * Maximum file size in MB (for display)
 */
export const MAX_FILE_SIZE_MB = 10;

/**
 * Validate image file before upload
 *
 * @param mimeType - MIME type of the image
 * @param sizeBytes - Size of the image in bytes
 * @returns Validation result with error message if invalid
 */
export function validateImageFile(
  mimeType: string,
  sizeBytes: number,
): {valid: boolean; error?: string} {
  // Check MIME type
  if (!SUPPORTED_IMAGE_TYPES.includes(mimeType as SupportedImageType)) {
    return {
      valid: false,
      error: `Unsupported file type. Please use JPEG, PNG, GIF, or HEIC images.`,
    };
  }

  // Check file size
  if (sizeBytes > MAX_FILE_SIZE_BYTES) {
    return {
      valid: false,
      error: `File too large. Maximum size is ${MAX_FILE_SIZE_MB}MB.`,
    };
  }

  return {valid: true};
}

/**
 * Convert a file URI to base64 encoded string
 * Note: Actual implementation depends on react-native-fs or similar library
 * This is a placeholder that would be implemented with native module
 *
 * @param uri - Local file URI
 * @returns Promise resolving to base64 encoded string
 */
export async function fileUriToBase64(uri: string): Promise<string> {
  // In a real implementation, this would use react-native-fs or similar
  // For now, we return the URI as-is for mock API calls
  // The actual implementation would be:
  // import RNFS from 'react-native-fs';
  // return await RNFS.readFile(uri, 'base64');

  // Placeholder - extract base64 from data URI if present
  if (uri.startsWith('data:')) {
    const parts = uri.split(',');
    if (parts.length > 1) {
      return parts[1];
    }
  }

  // Return URI for mock purposes
  return uri;
}

/**
 * Get MIME type from file extension
 */
export function getMimeTypeFromExtension(filename: string): string {
  const extension = filename.split('.').pop()?.toLowerCase();

  switch (extension) {
    case 'jpg':
    case 'jpeg':
      return 'image/jpeg';
    case 'png':
      return 'image/png';
    case 'gif':
      return 'image/gif';
    case 'heic':
      return 'image/heic';
    default:
      return 'image/jpeg'; // Default to JPEG
  }
}

/**
 * Generate a unique filename for a captured photo
 */
export function generatePhotoFilename(): string {
  const timestamp = Date.now();
  const random = Math.random().toString(36).substring(2, 8);
  return `photo_${timestamp}_${random}.jpg`;
}
