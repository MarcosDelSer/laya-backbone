/**
 * LAYA Parent App - API Configuration
 *
 * Configuration for connecting to the backend APIs (AI Service and Gibbon).
 * Configuration can be overridden at build time via react-native-config or similar.
 */

// Development configuration - can be overridden at build time
const DEFAULT_AI_SERVICE_URL = 'http://localhost:8000';
const DEFAULT_GIBBON_URL = 'http://localhost:8080/gibbon';

// In production, these would be replaced by environment configuration
const AI_SERVICE_URL = DEFAULT_AI_SERVICE_URL;
const GIBBON_URL = DEFAULT_GIBBON_URL;

export const API_CONFIG = {
  aiServiceUrl: AI_SERVICE_URL,
  gibbonUrl: GIBBON_URL,
  timeout: 30000, // 30 seconds
  retryConfig: {
    maxRetries: 3,
    initialDelayMs: 1000, // 1 second
    maxDelayMs: 10000, // 10 seconds
    backoffMultiplier: 2,
  },
  endpoints: {
    // Health check
    health: '/health',

    // Children endpoints
    children: {
      list: '/api/parent/children',
      details: '/api/parent/children/:id',
    },

    // Daily reports endpoints
    dailyReports: {
      list: '/api/parent/daily-reports',
      byChild: '/api/parent/children/:childId/daily-reports',
      details: '/api/parent/daily-reports/:id',
    },

    // Photo endpoints
    photos: {
      list: '/api/parent/photos',
      byChild: '/api/parent/children/:childId/photos',
      download: '/api/parent/photos/:id/download',
    },

    // Message endpoints
    messages: {
      threads: '/api/parent/messages/threads',
      threadMessages: '/api/parent/messages/threads/:threadId/messages',
      send: '/api/parent/messages/send',
      createThread: '/api/parent/messages/threads',
      markRead: '/api/parent/messages/:id/read',
    },

    // Invoice endpoints
    invoices: {
      list: '/api/parent/invoices',
      details: '/api/parent/invoices/:id',
      downloadPdf: '/api/parent/invoices/:id/pdf',
    },

    // Document endpoints
    documents: {
      list: '/api/parent/documents',
      details: '/api/parent/documents/:id',
      sign: '/api/parent/documents/:id/sign',
    },

    // Activity recommendations endpoints (AI Service)
    activities: {
      recommendations: '/api/parent/activities/recommendations',
    },

    // Coaching guidance endpoints (AI Service)
    coaching: {
      guidance: '/api/parent/coaching/guidance',
    },

    // Push notification endpoints
    notifications: {
      registerToken: '/api/notifications/register-token',
      unregisterToken: '/api/notifications/unregister-token',
    },
  },
} as const;

export type ApiEndpoints = typeof API_CONFIG.endpoints;

/**
 * Build a full URL for an API endpoint
 * @param baseUrl - The base URL (AI service or Gibbon)
 * @param endpoint - The endpoint path
 * @param params - Path and query parameters
 */
export function buildApiUrl(
  baseUrl: string,
  endpoint: string,
  params?: Record<string, string>,
): string {
  let url = `${baseUrl}${endpoint}`;

  if (params) {
    // Replace path parameters like :id, :childId, etc.
    Object.entries(params).forEach(([key, value]) => {
      const pathParam = `:${key}`;
      if (url.includes(pathParam)) {
        url = url.replace(pathParam, encodeURIComponent(value));
      }
    });

    // Add remaining parameters as query string
    const queryParams = Object.entries(params)
      .filter(([key]) => !endpoint.includes(`:${key}`))
      .map(([key, value]) => `${encodeURIComponent(key)}=${encodeURIComponent(value)}`)
      .join('&');

    if (queryParams) {
      url += `?${queryParams}`;
    }
  }

  return url;
}

/**
 * Build URL for AI Service endpoints
 */
export function buildAiServiceUrl(endpoint: string, params?: Record<string, string>): string {
  return buildApiUrl(API_CONFIG.aiServiceUrl, endpoint, params);
}

/**
 * Build URL for Gibbon endpoints
 */
export function buildGibbonUrl(endpoint: string, params?: Record<string, string>): string {
  return buildApiUrl(API_CONFIG.gibbonUrl, endpoint, params);
}
