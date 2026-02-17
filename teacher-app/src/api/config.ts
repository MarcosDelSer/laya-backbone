/**
 * LAYA Teacher App - API Configuration
 *
 * Configuration for connecting to the Gibbon backend API.
 * Configuration is set at build time via react-native-config or similar.
 */

// Shared production backend configuration (Hetzner + Caddy path routing)
const DEFAULT_API_BASE_URL = 'https://ai.46-225-139-110.sslip.io/gibbon';

// In production, this would be replaced by environment configuration
// For now, use default development URL
const API_BASE_URL = DEFAULT_API_BASE_URL;

export const API_CONFIG = {
  baseUrl: API_BASE_URL,
  timeout: 30000, // 30 seconds
  endpoints: {
    // Attendance endpoints
    attendance: {
      list: '/modules/CareTracking/api/attendance',
      checkIn: '/modules/CareTracking/api/attendance/checkin',
      checkOut: '/modules/CareTracking/api/attendance/checkout',
    },
    // Meal endpoints
    meals: {
      list: '/modules/CareTracking/api/meals',
      log: '/modules/CareTracking/api/meals/log',
    },
    // Nap endpoints
    naps: {
      list: '/modules/CareTracking/api/naps',
      start: '/modules/CareTracking/api/naps/start',
      stop: '/modules/CareTracking/api/naps/stop',
    },
    // Diaper endpoints
    diapers: {
      list: '/modules/CareTracking/api/diapers',
      log: '/modules/CareTracking/api/diapers/log',
    },
    // Photo endpoints
    photos: {
      upload: '/modules/PhotoManagement/api/photos/upload',
      tag: '/modules/PhotoManagement/api/photos/tag',
    },
    // Child endpoints
    children: {
      list: '/modules/CareTracking/api/children',
      details: '/modules/CareTracking/api/children/:id',
    },
    // Push notification endpoints
    notifications: {
      registerToken: '/modules/NotificationEngine/api/register-token',
    },
  },
} as const;

export type ApiEndpoints = typeof API_CONFIG.endpoints;

/**
 * Build a full URL for an API endpoint
 */
export function buildApiUrl(endpoint: string, params?: Record<string, string>): string {
  let url = `${API_CONFIG.baseUrl}${endpoint}`;

  if (params) {
    // Replace path parameters like :id
    Object.entries(params).forEach(([key, value]) => {
      url = url.replace(`:${key}`, encodeURIComponent(value));
    });

    // Add query parameters
    const queryParams = Object.entries(params)
      .filter(([key]) => !url.includes(`:${key}`))
      .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
      .join('&');

    if (queryParams) {
      url += `?${queryParams}`;
    }
  }

  return url;
}
