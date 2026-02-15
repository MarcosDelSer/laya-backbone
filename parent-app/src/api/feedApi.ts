/**
 * LAYA Parent App - Feed API
 *
 * API functions for fetching daily feed data including activities,
 * meals, naps, photos, and other events for the parent's children.
 * Follows patterns from ParentPortal feed endpoints.
 */

import {api} from './client';
import {API_CONFIG} from './config';
import type {
  ApiResponse,
  FeedEvent,
  DailyFeed,
  DailySummary,
  PaginatedResponse,
} from '../types';

/**
 * Response type for feed list endpoint
 */
interface FeedListResponse {
  feeds: DailyFeed[];
  childNames: Record<string, string>;
}

/**
 * Parameters for fetching feed data
 */
interface FetchFeedParams {
  childId?: string;
  date?: string;
  page?: number;
  pageSize?: number;
}

/**
 * Get the current date in YYYY-MM-DD format
 */
function getCurrentDate(): string {
  const now = new Date();
  return now.toISOString().split('T')[0];
}

/**
 * Fetch the daily feed for all children or a specific child
 */
export async function fetchDailyFeed(
  params?: FetchFeedParams,
): Promise<ApiResponse<FeedListResponse>> {
  const queryParams: Record<string, string> = {};

  if (params?.childId) {
    queryParams.childId = params.childId;
  }
  if (params?.date) {
    queryParams.date = params.date;
  } else {
    queryParams.date = getCurrentDate();
  }
  if (params?.page !== undefined) {
    queryParams.page = params.page.toString();
  }
  if (params?.pageSize !== undefined) {
    queryParams.pageSize = params.pageSize.toString();
  }

  return api.get<FeedListResponse>(
    API_CONFIG.endpoints.feed.list,
    queryParams,
  );
}

/**
 * Fetch feed events for a specific child on a specific date
 */
export async function fetchChildFeed(
  childId: string,
  date?: string,
): Promise<ApiResponse<DailyFeed>> {
  const endpoint = API_CONFIG.endpoints.feed.details.replace(':id', childId);
  const queryParams: Record<string, string> = {
    date: date || getCurrentDate(),
  };

  return api.get<DailyFeed>(endpoint, queryParams);
}

/**
 * Fetch paginated feed events across multiple days
 */
export async function fetchFeedHistory(
  childId?: string,
  page: number = 1,
  pageSize: number = 10,
): Promise<ApiResponse<PaginatedResponse<DailyFeed>>> {
  const queryParams: Record<string, string> = {
    page: page.toString(),
    pageSize: pageSize.toString(),
  };

  if (childId) {
    queryParams.childId = childId;
  }

  return api.get<PaginatedResponse<DailyFeed>>(
    API_CONFIG.endpoints.feed.list,
    queryParams,
  );
}

/**
 * Get the icon name for a feed event type
 */
export function getEventIcon(eventType: string): string {
  const icons: Record<string, string> = {
    check_in: '🏫',
    check_out: '🚗',
    meal: '🍽️',
    nap: '😴',
    diaper: '👶',
    activity: '🎨',
    photo: '📷',
    incident: '⚠️',
    note: '📝',
  };
  return icons[eventType] || '📋';
}

/**
 * Get the color for a feed event type
 */
export function getEventColor(eventType: string): string {
  const colors: Record<string, string> = {
    check_in: '#4CAF50',
    check_out: '#2196F3',
    meal: '#FF9800',
    nap: '#9C27B0',
    diaper: '#00BCD4',
    activity: '#E91E63',
    photo: '#3F51B5',
    incident: '#F44336',
    note: '#607D8B',
  };
  return colors[eventType] || '#757575';
}

/**
 * Format a timestamp for display (relative or absolute)
 */
export function formatEventTime(timestamp: string): string {
  const date = new Date(timestamp);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMins / 60);

  // If less than an hour ago, show relative time
  if (diffMins < 60) {
    if (diffMins < 1) {
      return 'Just now';
    }
    return `${diffMins}m ago`;
  }

  // If today, show time only
  if (date.toDateString() === now.toDateString()) {
    return date.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
  }

  // Otherwise show date and time
  return date.toLocaleDateString([], {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

/**
 * Format a date for section headers
 */
export function formatDateHeader(dateString: string): string {
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
 * Generate mock feed data for development
 */
export function getMockFeedData(): FeedListResponse {
  const today = getCurrentDate();
  const yesterday = new Date(Date.now() - 86400000).toISOString().split('T')[0];

  const mockEvents: FeedEvent[] = [
    {
      id: 'event-1',
      childId: 'child-1',
      type: 'check_in',
      title: 'Checked In',
      description: 'Emma arrived at school',
      timestamp: `${today}T08:30:00Z`,
      photoUrl: null,
      metadata: null,
    },
    {
      id: 'event-2',
      childId: 'child-1',
      type: 'meal',
      title: 'Breakfast',
      description: 'Ate all of their oatmeal and fruit',
      timestamp: `${today}T08:45:00Z`,
      photoUrl: null,
      metadata: {amount: 'all', mealType: 'breakfast'},
    },
    {
      id: 'event-3',
      childId: 'child-1',
      type: 'activity',
      title: 'Art Time',
      description: 'Finger painting with watercolors. Emma created a beautiful rainbow picture!',
      timestamp: `${today}T09:30:00Z`,
      photoUrl: null,
      metadata: {activityType: 'creative'},
    },
    {
      id: 'event-4',
      childId: 'child-1',
      type: 'meal',
      title: 'Morning Snack',
      description: 'Apple slices and crackers',
      timestamp: `${today}T10:30:00Z`,
      photoUrl: null,
      metadata: {amount: 'most', mealType: 'snack'},
    },
    {
      id: 'event-5',
      childId: 'child-1',
      type: 'activity',
      title: 'Story Circle',
      description: 'Read "The Very Hungry Caterpillar" together',
      timestamp: `${today}T11:00:00Z`,
      photoUrl: null,
      metadata: {activityType: 'literacy'},
    },
    {
      id: 'event-6',
      childId: 'child-1',
      type: 'meal',
      title: 'Lunch',
      description: 'Chicken nuggets, vegetables, and milk',
      timestamp: `${today}T12:00:00Z`,
      photoUrl: null,
      metadata: {amount: 'some', mealType: 'lunch'},
    },
    {
      id: 'event-7',
      childId: 'child-1',
      type: 'nap',
      title: 'Nap Time',
      description: 'Slept well for 1.5 hours',
      timestamp: `${today}T12:30:00Z`,
      photoUrl: null,
      metadata: {duration: 90, quality: 'good'},
    },
    {
      id: 'event-8',
      childId: 'child-1',
      type: 'photo',
      title: 'Photo Added',
      description: 'Playing during outdoor time',
      timestamp: `${today}T15:30:00Z`,
      photoUrl: 'https://example.com/photo1.jpg',
      metadata: null,
    },
  ];

  const mockFeed: DailyFeed = {
    date: today,
    childId: 'child-1',
    events: mockEvents,
    summary: {
      mealsCount: 3,
      napMinutes: 90,
      activitiesCount: 2,
      photosCount: 1,
    },
  };

  const yesterdayEvents: FeedEvent[] = [
    {
      id: 'event-y1',
      childId: 'child-1',
      type: 'check_in',
      title: 'Checked In',
      description: 'Emma arrived at school',
      timestamp: `${yesterday}T08:15:00Z`,
      photoUrl: null,
      metadata: null,
    },
    {
      id: 'event-y2',
      childId: 'child-1',
      type: 'activity',
      title: 'Music & Movement',
      description: 'Dancing and singing songs',
      timestamp: `${yesterday}T10:00:00Z`,
      photoUrl: null,
      metadata: {activityType: 'music'},
    },
    {
      id: 'event-y3',
      childId: 'child-1',
      type: 'nap',
      title: 'Nap Time',
      description: 'Rested quietly for 1 hour',
      timestamp: `${yesterday}T13:00:00Z`,
      photoUrl: null,
      metadata: {duration: 60, quality: 'fair'},
    },
    {
      id: 'event-y4',
      childId: 'child-1',
      type: 'check_out',
      title: 'Checked Out',
      description: 'Picked up by parent',
      timestamp: `${yesterday}T17:00:00Z`,
      photoUrl: null,
      metadata: null,
    },
  ];

  const yesterdayFeed: DailyFeed = {
    date: yesterday,
    childId: 'child-1',
    events: yesterdayEvents,
    summary: {
      mealsCount: 3,
      napMinutes: 60,
      activitiesCount: 1,
      photosCount: 0,
    },
  };

  return {
    feeds: [mockFeed, yesterdayFeed],
    childNames: {
      'child-1': 'Emma Johnson',
    },
  };
}
