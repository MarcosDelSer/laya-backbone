/**
 * LAYA Parent App - Photo API
 *
 * API functions for fetching, downloading, and managing photos.
 * Provides photo gallery data, individual photo details, and download URLs
 * for parents to view and save their children's photos.
 */

import {api} from './client';
import {API_CONFIG} from './config';
import type {
  ApiResponse,
  Photo,
  Child,
  PaginatedResponse,
} from '../types';

// ============================================================================
// Response Types
// ============================================================================

/**
 * Response type for photos list endpoint
 */
export interface PhotosListResponse {
  photos: PhotoWithMetadata[];
  children: Child[];
}

/**
 * Photo with additional metadata
 */
export interface PhotoWithMetadata {
  photo: Photo;
  child: Child;
  uploadedAt: string;
  reportId?: string;
  reportDate?: string;
}

/**
 * Filter options for fetching photos
 */
export interface PhotosFilter {
  childId?: string;
  startDate?: string;
  endDate?: string;
  limit?: number;
  offset?: number;
}

/**
 * Photo download response with URL and filename
 */
export interface PhotoDownloadResponse {
  downloadUrl: string;
  filename: string;
  contentType: string;
  size: number;
}

/**
 * Grouped photos by date
 */
export interface PhotosByDate {
  date: string;
  displayDate: string;
  photos: PhotoWithMetadata[];
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get the current date in YYYY-MM-DD format
 */
function getCurrentDate(): string {
  const now = new Date();
  return now.toISOString().split('T')[0];
}

/**
 * Format date for display (e.g., "January 15, 2024")
 */
export function formatDateForDisplay(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Check if a date is today
 */
export function isToday(dateString: string): boolean {
  return dateString === getCurrentDate();
}

/**
 * Check if a date is yesterday
 */
export function isYesterday(dateString: string): boolean {
  const yesterday = new Date();
  yesterday.setDate(yesterday.getDate() - 1);
  return dateString === yesterday.toISOString().split('T')[0];
}

/**
 * Get relative date display (e.g., "Today", "Yesterday", or formatted date)
 */
export function getRelativeDateDisplay(dateString: string): string {
  if (isToday(dateString)) {
    return 'Today';
  }
  if (isYesterday(dateString)) {
    return 'Yesterday';
  }
  return formatDateForDisplay(dateString);
}

/**
 * Generate a filename for a photo download
 */
export function generatePhotoFilename(photo: Photo, date?: string): string {
  const timestamp = date || new Date().toISOString().split('T')[0];
  const sanitizedCaption = photo.caption
    ? photo.caption.slice(0, 20).replace(/[^a-zA-Z0-9]/g, '_')
    : 'photo';
  return `laya_${sanitizedCaption}_${timestamp}_${photo.id.slice(-6)}.jpg`;
}

// ============================================================================
// API Functions
// ============================================================================

/**
 * Fetch all photos for the parent's children
 */
export async function fetchPhotos(
  options?: PhotosFilter,
): Promise<ApiResponse<PaginatedResponse<PhotoWithMetadata>>> {
  const params: Record<string, string> = {};

  if (options?.childId) {
    params.childId = options.childId;
  }
  if (options?.startDate) {
    params.startDate = options.startDate;
  }
  if (options?.endDate) {
    params.endDate = options.endDate;
  }
  if (options?.limit !== undefined) {
    params.limit = String(options.limit);
  }
  if (options?.offset !== undefined) {
    params.offset = String(options.offset);
  }

  return api.get<PaginatedResponse<PhotoWithMetadata>>(
    API_CONFIG.endpoints.photos.list,
    params,
  );
}

/**
 * Fetch photos for a specific child
 */
export async function fetchPhotosByChild(
  childId: string,
  options?: {
    startDate?: string;
    endDate?: string;
    limit?: number;
    offset?: number;
  },
): Promise<ApiResponse<PaginatedResponse<PhotoWithMetadata>>> {
  const params: Record<string, string> = {
    childId,
  };

  if (options?.startDate) {
    params.startDate = options.startDate;
  }
  if (options?.endDate) {
    params.endDate = options.endDate;
  }
  if (options?.limit !== undefined) {
    params.limit = String(options.limit);
  }
  if (options?.offset !== undefined) {
    params.offset = String(options.offset);
  }

  return api.get<PaginatedResponse<PhotoWithMetadata>>(
    API_CONFIG.endpoints.photos.byChild,
    params,
  );
}

/**
 * Get download URL for a specific photo
 */
export async function getPhotoDownloadUrl(
  photoId: string,
): Promise<ApiResponse<PhotoDownloadResponse>> {
  return api.get<PhotoDownloadResponse>(
    API_CONFIG.endpoints.photos.download,
    {id: photoId},
  );
}

// ============================================================================
// Data Processing Functions
// ============================================================================

/**
 * Group photos by date
 */
export function groupPhotosByDate(
  photos: PhotoWithMetadata[],
): PhotosByDate[] {
  const grouped = new Map<string, PhotoWithMetadata[]>();

  for (const photo of photos) {
    const date = photo.reportDate || photo.uploadedAt.split('T')[0];
    const existing = grouped.get(date) || [];
    existing.push(photo);
    grouped.set(date, existing);
  }

  // Convert to array and sort by date (most recent first)
  const result: PhotosByDate[] = [];
  const sortedDates = Array.from(grouped.keys()).sort((a, b) => {
    return new Date(b).getTime() - new Date(a).getTime();
  });

  for (const date of sortedDates) {
    const datePhotos = grouped.get(date) || [];
    result.push({
      date,
      displayDate: getRelativeDateDisplay(date),
      photos: datePhotos,
    });
  }

  return result;
}

/**
 * Sort photos by upload date (most recent first)
 */
export function sortPhotosByDate(
  photos: PhotoWithMetadata[],
): PhotoWithMetadata[] {
  return [...photos].sort((a, b) => {
    return new Date(b.uploadedAt).getTime() - new Date(a.uploadedAt).getTime();
  });
}

/**
 * Filter photos by child ID
 */
export function filterPhotosByChild(
  photos: PhotoWithMetadata[],
  childId: string,
): PhotoWithMetadata[] {
  return photos.filter(p => p.child.id === childId);
}

/**
 * Extract unique children from photos
 */
export function extractUniqueChildren(photos: PhotoWithMetadata[]): Child[] {
  const childMap = new Map<string, Child>();
  for (const photo of photos) {
    if (!childMap.has(photo.child.id)) {
      childMap.set(photo.child.id, photo.child);
    }
  }
  return Array.from(childMap.values());
}

/**
 * Count photos per child
 */
export function countPhotosPerChild(
  photos: PhotoWithMetadata[],
): Map<string, number> {
  const counts = new Map<string, number>();
  for (const photo of photos) {
    const current = counts.get(photo.child.id) || 0;
    counts.set(photo.child.id, current + 1);
  }
  return counts;
}

/**
 * Get photos from the last N days
 */
export function getRecentPhotos(
  photos: PhotoWithMetadata[],
  days: number = 7,
): PhotoWithMetadata[] {
  const cutoffDate = new Date();
  cutoffDate.setDate(cutoffDate.getDate() - days);
  const cutoffString = cutoffDate.toISOString().split('T')[0];

  return photos.filter(photo => {
    const photoDate = photo.reportDate || photo.uploadedAt.split('T')[0];
    return photoDate >= cutoffString;
  });
}
