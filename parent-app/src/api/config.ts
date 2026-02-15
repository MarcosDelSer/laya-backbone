/**
 * LAYA Parent App - API Configuration
 *
 * Configuration for connecting to the Gibbon backend API.
 * Configuration is set at build time via react-native-config or similar.
 */

// Development configuration - can be overridden at build time
const DEFAULT_API_BASE_URL = 'http://localhost/gibbon';

// In production, this would be replaced by environment configuration
// For now, use default development URL
const API_BASE_URL = DEFAULT_API_BASE_URL;

export const API_CONFIG = {
  baseUrl: API_BASE_URL,
  timeout: 30000, // 30 seconds
  endpoints: {
    // Authentication endpoints
    auth: {
      login: '/modules/ParentPortal/api/auth/login',
      logout: '/modules/ParentPortal/api/auth/logout',
      refreshToken: '/modules/ParentPortal/api/auth/refresh',
    },
    // Daily feed endpoints
    feed: {
      list: '/modules/ParentPortal/api/feed',
      details: '/modules/ParentPortal/api/feed/:id',
    },
    // Child endpoints
    children: {
      list: '/modules/ParentPortal/api/children',
      details: '/modules/ParentPortal/api/children/:id',
    },
    // Photo gallery endpoints
    photos: {
      list: '/modules/PhotoManagement/api/photos',
      details: '/modules/PhotoManagement/api/photos/:id',
      download: '/modules/PhotoManagement/api/photos/:id/download',
    },
    // Invoice endpoints
    invoices: {
      list: '/modules/Finance/api/invoices',
      details: '/modules/Finance/api/invoices/:id',
      downloadPdf: '/modules/Finance/api/invoices/:id/pdf',
    },
    // Messaging endpoints
    messaging: {
      conversations: '/modules/Messenger/api/conversations',
      messages: '/modules/Messenger/api/conversations/:conversationId/messages',
      send: '/modules/Messenger/api/conversations/:conversationId/messages',
      markRead: '/modules/Messenger/api/conversations/:conversationId/read',
    },
    // E-Signature endpoints
    signatures: {
      pending: '/modules/DataUpdater/api/signatures/pending',
      document: '/modules/DataUpdater/api/signatures/:id/document',
      sign: '/modules/DataUpdater/api/signatures/:id/sign',
    },
    // Push notification endpoints
    notifications: {
      registerToken: '/modules/NotificationEngine/api/register-token',
      preferences: '/modules/NotificationEngine/api/preferences',
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
