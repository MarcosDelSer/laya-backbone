/**
 * LAYA Parent App - Photo API
 *
 * API functions for fetching photos from the PhotoManagement module.
 * Parents can view photos of their children taken at the school.
 */

import {api} from './client';
import {API_CONFIG} from './config';
import type {ApiResponse, Photo, PaginatedResponse} from '../types';

/**
 * Response type for photo list endpoint
 */
interface PhotoListResponse {
  photos: Photo[];
  total: number;
}

/**
 * Parameters for fetching photos
 */
interface FetchPhotosParams {
  childId?: string;
  date?: string;
  startDate?: string;
  endDate?: string;
  page?: number;
  pageSize?: number;
}

/**
 * Fetch photos for the parent's children
 */
export async function fetchPhotos(
  params?: FetchPhotosParams,
): Promise<ApiResponse<PhotoListResponse>> {
  const queryParams: Record<string, string> = {};

  if (params?.childId) {
    queryParams.childId = params.childId;
  }
  if (params?.date) {
    queryParams.date = params.date;
  }
  if (params?.startDate) {
    queryParams.startDate = params.startDate;
  }
  if (params?.endDate) {
    queryParams.endDate = params.endDate;
  }
  if (params?.page !== undefined) {
    queryParams.page = params.page.toString();
  }
  if (params?.pageSize !== undefined) {
    queryParams.pageSize = params.pageSize.toString();
  }

  return api.get<PhotoListResponse>(
    API_CONFIG.endpoints.photos.list,
    queryParams,
  );
}

/**
 * Fetch a single photo by ID
 */
export async function fetchPhotoById(
  photoId: string,
): Promise<ApiResponse<Photo>> {
  const endpoint = API_CONFIG.endpoints.photos.details.replace(':id', photoId);
  return api.get<Photo>(endpoint);
}

/**
 * Fetch paginated photos
 */
export async function fetchPhotosPaginated(
  page: number = 1,
  pageSize: number = 20,
  childId?: string,
): Promise<ApiResponse<PaginatedResponse<Photo>>> {
  const queryParams: Record<string, string> = {
    page: page.toString(),
    pageSize: pageSize.toString(),
  };

  if (childId) {
    queryParams.childId = childId;
  }

  return api.get<PaginatedResponse<Photo>>(
    API_CONFIG.endpoints.photos.list,
    queryParams,
  );
}

/**
 * Get photo download URL
 */
export function getPhotoDownloadUrl(photoId: string): string {
  const endpoint = API_CONFIG.endpoints.photos.download.replace(':id', photoId);
  return `${API_CONFIG.baseUrl}${endpoint}`;
}

/**
 * Format photo date for display
 */
export function formatPhotoDate(dateString: string): string {
  const date = new Date(dateString);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  if (date.toDateString() === today.toDateString()) {
    return date.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
  }

  if (date.toDateString() === yesterday.toDateString()) {
    return `Yesterday ${date.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'})}`;
  }

  return date.toLocaleDateString(undefined, {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

/**
 * Group photos by date
 */
export function groupPhotosByDate(
  photos: Photo[],
): Map<string, Photo[]> {
  const grouped = new Map<string, Photo[]>();

  photos.forEach(photo => {
    const dateKey = new Date(photo.takenAt).toISOString().split('T')[0];
    const existing = grouped.get(dateKey) || [];
    grouped.set(dateKey, [...existing, photo]);
  });

  return grouped;
}

/**
 * Format date for section headers
 */
export function formatSectionDate(dateString: string): string {
  const date = new Date(dateString);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  if (date.toDateString() === today.toDateString()) {
    return 'Today';
  }

  if (date.toDateString() === yesterday.toDateString()) {
    return 'Yesterday';
  }

  return date.toLocaleDateString(undefined, {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Generate mock photo data for development
 */
export function getMockPhotos(): PhotoListResponse {
  const today = new Date().toISOString();
  const yesterday = new Date(Date.now() - 86400000).toISOString();
  const twoDaysAgo = new Date(Date.now() - 172800000).toISOString();

  const mockPhotos: Photo[] = [
    {
      id: 'photo-1',
      uri: 'https://picsum.photos/seed/photo1/800/600',
      thumbnailUri: 'https://picsum.photos/seed/photo1/200/150',
      childIds: ['child-1'],
      caption: 'Playing in the sandbox during outdoor time',
      takenAt: today,
      takenBy: 'Ms. Johnson',
      downloadUrl: 'https://picsum.photos/seed/photo1/800/600',
    },
    {
      id: 'photo-2',
      uri: 'https://picsum.photos/seed/photo2/800/600',
      thumbnailUri: 'https://picsum.photos/seed/photo2/200/150',
      childIds: ['child-1'],
      caption: 'Art project - finger painting',
      takenAt: today,
      takenBy: 'Ms. Johnson',
      downloadUrl: 'https://picsum.photos/seed/photo2/800/600',
    },
    {
      id: 'photo-3',
      uri: 'https://picsum.photos/seed/photo3/800/600',
      thumbnailUri: 'https://picsum.photos/seed/photo3/200/150',
      childIds: ['child-1'],
      caption: 'Story time with friends',
      takenAt: yesterday,
      takenBy: 'Ms. Smith',
      downloadUrl: 'https://picsum.photos/seed/photo3/800/600',
    },
    {
      id: 'photo-4',
      uri: 'https://picsum.photos/seed/photo4/800/600',
      thumbnailUri: 'https://picsum.photos/seed/photo4/200/150',
      childIds: ['child-1'],
      caption: 'Building blocks tower',
      takenAt: yesterday,
      takenBy: 'Ms. Johnson',
      downloadUrl: 'https://picsum.photos/seed/photo4/800/600',
    },
    {
      id: 'photo-5',
      uri: 'https://picsum.photos/seed/photo5/800/600',
      thumbnailUri: 'https://picsum.photos/seed/photo5/200/150',
      childIds: ['child-1'],
      caption: 'Music class - playing drums',
      takenAt: yesterday,
      takenBy: 'Ms. Davis',
      downloadUrl: 'https://picsum.photos/seed/photo5/800/600',
    },
    {
      id: 'photo-6',
      uri: 'https://picsum.photos/seed/photo6/800/600',
      thumbnailUri: 'https://picsum.photos/seed/photo6/200/150',
      childIds: ['child-1'],
      caption: 'Snack time smiles',
      takenAt: twoDaysAgo,
      takenBy: 'Ms. Johnson',
      downloadUrl: 'https://picsum.photos/seed/photo6/800/600',
    },
    {
      id: 'photo-7',
      uri: 'https://picsum.photos/seed/photo7/800/600',
      thumbnailUri: 'https://picsum.photos/seed/photo7/200/150',
      childIds: ['child-1'],
      caption: 'Learning letters',
      takenAt: twoDaysAgo,
      takenBy: 'Ms. Smith',
      downloadUrl: 'https://picsum.photos/seed/photo7/800/600',
    },
    {
      id: 'photo-8',
      uri: 'https://picsum.photos/seed/photo8/800/600',
      thumbnailUri: 'https://picsum.photos/seed/photo8/200/150',
      childIds: ['child-1'],
      caption: 'Outdoor exploration',
      takenAt: twoDaysAgo,
      takenBy: 'Ms. Johnson',
      downloadUrl: 'https://picsum.photos/seed/photo8/800/600',
    },
  ];

  return {
    photos: mockPhotos,
    total: mockPhotos.length,
  };
}
