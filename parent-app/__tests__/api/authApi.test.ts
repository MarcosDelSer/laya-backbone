/**
 * LAYA Parent App - Authentication API Tests
 *
 * Tests for authentication API functions including login, logout,
 * token refresh, and error handling.
 */

import {
  login,
  logout,
  refreshAccessToken,
  getCurrentUser,
  initializeAuth,
  hasActiveSession,
  clearSession,
  TOKEN_STORAGE_KEYS,
} from '../../src/api/authApi';
import {setSessionToken, getSessionToken} from '../../src/api/client';
import type {Parent, ApiResponse} from '../../src/types';
import type {LoginResponse, RefreshResponse} from '../../src/api/authApi';

// Mock the API client module
jest.mock('../../src/api/client', () => ({
  api: {
    post: jest.fn(),
    get: jest.fn(),
  },
  setSessionToken: jest.fn(),
  getSessionToken: jest.fn(),
}));

// Mock the config module
jest.mock('../../src/api/config', () => ({
  API_CONFIG: {
    endpoints: {
      auth: {
        login: '/modules/ParentPortal/api/auth/login',
        logout: '/modules/ParentPortal/api/auth/logout',
        refresh: '/modules/ParentPortal/api/auth/refresh',
        me: '/modules/ParentPortal/api/auth/me',
      },
    },
  },
}));

// Import the mocked api module to access mock functions
const {api} = require('../../src/api/client');

// Test data fixtures
const mockParent: Parent = {
  id: 'parent-123',
  firstName: 'Jane',
  lastName: 'Doe',
  email: 'jane.doe@example.com',
  phone: '+1-555-123-4567',
  childIds: ['child-1', 'child-2'],
};

const mockLoginResponse: LoginResponse = {
  user: mockParent,
  accessToken: 'access-token-123',
  refreshToken: 'refresh-token-456',
  expiresIn: 3600,
};

const mockRefreshResponse: RefreshResponse = {
  accessToken: 'new-access-token-789',
  refreshToken: 'new-refresh-token-012',
  expiresIn: 3600,
};

describe('authApi', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  describe('login', () => {
    it('should successfully login with valid credentials', async () => {
      const credentials = {email: 'jane.doe@example.com', password: 'password123'};
      const successResponse: ApiResponse<LoginResponse> = {
        success: true,
        data: mockLoginResponse,
        error: null,
      };

      api.post.mockResolvedValue(successResponse);

      const result = await login(credentials);

      expect(result.success).toBe(true);
      expect(result.data).toEqual(mockLoginResponse);
      expect(result.error).toBeNull();
      expect(api.post).toHaveBeenCalledWith(
        '/modules/ParentPortal/api/auth/login',
        credentials,
        undefined,
        'gibbon',
      );
      expect(setSessionToken).toHaveBeenCalledWith('access-token-123');
    });

    it('should handle login with invalid credentials', async () => {
      const credentials = {email: 'jane.doe@example.com', password: 'wrongpassword'};
      const errorResponse: ApiResponse<LoginResponse> = {
        success: false,
        data: null,
        error: {
          code: 'INVALID_CREDENTIALS',
          message: 'Invalid email or password',
        },
      };

      api.post.mockResolvedValue(errorResponse);

      const result = await login(credentials);

      expect(result.success).toBe(false);
      expect(result.data).toBeNull();
      expect(result.error?.code).toBe('INVALID_CREDENTIALS');
      expect(setSessionToken).not.toHaveBeenCalled();
    });

    it('should not set session token on login failure', async () => {
      const credentials = {email: 'jane.doe@example.com', password: 'password123'};
      const errorResponse: ApiResponse<LoginResponse> = {
        success: false,
        data: null,
        error: {
          code: 'SERVER_ERROR',
          message: 'Internal server error',
        },
      };

      api.post.mockResolvedValue(errorResponse);

      await login(credentials);

      expect(setSessionToken).not.toHaveBeenCalled();
    });

    it('should handle network errors during login', async () => {
      const credentials = {email: 'jane.doe@example.com', password: 'password123'};
      const networkError: ApiResponse<LoginResponse> = {
        success: false,
        data: null,
        error: {
          code: 'NETWORK_ERROR',
          message: 'Network request failed',
        },
      };

      api.post.mockResolvedValue(networkError);

      const result = await login(credentials);

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('NETWORK_ERROR');
    });

    it('should handle timeout errors during login', async () => {
      const credentials = {email: 'jane.doe@example.com', password: 'password123'};
      const timeoutError: ApiResponse<LoginResponse> = {
        success: false,
        data: null,
        error: {
          code: 'TIMEOUT',
          message: 'Request timed out after 30000ms',
        },
      };

      api.post.mockResolvedValue(timeoutError);

      const result = await login(credentials);

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('TIMEOUT');
    });
  });

  describe('logout', () => {
    it('should successfully logout and clear session token', async () => {
      const successResponse: ApiResponse<void> = {
        success: true,
        data: undefined,
        error: null,
      };

      api.post.mockResolvedValue(successResponse);

      const result = await logout();

      expect(result.success).toBe(true);
      expect(api.post).toHaveBeenCalledWith(
        '/modules/ParentPortal/api/auth/logout',
        undefined,
        undefined,
        'gibbon',
      );
      expect(setSessionToken).toHaveBeenCalledWith(null);
    });

    it('should clear session token even when server logout fails', async () => {
      const errorResponse: ApiResponse<void> = {
        success: false,
        data: null,
        error: {
          code: 'SERVER_ERROR',
          message: 'Internal server error',
        },
      };

      api.post.mockResolvedValue(errorResponse);

      const result = await logout();

      expect(result.success).toBe(false);
      // Session token should be cleared regardless of server response
      expect(setSessionToken).toHaveBeenCalledWith(null);
    });

    it('should clear session token on network error during logout', async () => {
      const networkError: ApiResponse<void> = {
        success: false,
        data: null,
        error: {
          code: 'NETWORK_ERROR',
          message: 'Network request failed',
        },
      };

      api.post.mockResolvedValue(networkError);

      await logout();

      expect(setSessionToken).toHaveBeenCalledWith(null);
    });
  });

  describe('refreshAccessToken', () => {
    it('should successfully refresh tokens', async () => {
      const refreshToken = 'refresh-token-456';
      const successResponse: ApiResponse<RefreshResponse> = {
        success: true,
        data: mockRefreshResponse,
        error: null,
      };

      api.post.mockResolvedValue(successResponse);

      const result = await refreshAccessToken(refreshToken);

      expect(result.success).toBe(true);
      expect(result.data).toEqual(mockRefreshResponse);
      expect(api.post).toHaveBeenCalledWith(
        '/modules/ParentPortal/api/auth/refresh',
        {refreshToken},
        undefined,
        'gibbon',
      );
      expect(setSessionToken).toHaveBeenCalledWith('new-access-token-789');
    });

    it('should handle expired refresh token', async () => {
      const refreshToken = 'expired-refresh-token';
      const errorResponse: ApiResponse<RefreshResponse> = {
        success: false,
        data: null,
        error: {
          code: 'TOKEN_EXPIRED',
          message: 'Refresh token has expired',
        },
      };

      api.post.mockResolvedValue(errorResponse);

      const result = await refreshAccessToken(refreshToken);

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('TOKEN_EXPIRED');
      expect(setSessionToken).not.toHaveBeenCalled();
    });

    it('should handle invalid refresh token', async () => {
      const refreshToken = 'invalid-token';
      const errorResponse: ApiResponse<RefreshResponse> = {
        success: false,
        data: null,
        error: {
          code: 'INVALID_TOKEN',
          message: 'Invalid refresh token',
        },
      };

      api.post.mockResolvedValue(errorResponse);

      const result = await refreshAccessToken(refreshToken);

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('INVALID_TOKEN');
    });

    it('should not update session token on refresh failure', async () => {
      const refreshToken = 'refresh-token-456';
      const errorResponse: ApiResponse<RefreshResponse> = {
        success: false,
        data: null,
        error: {
          code: 'SERVER_ERROR',
          message: 'Internal server error',
        },
      };

      api.post.mockResolvedValue(errorResponse);

      await refreshAccessToken(refreshToken);

      expect(setSessionToken).not.toHaveBeenCalled();
    });
  });

  describe('getCurrentUser', () => {
    it('should successfully fetch current user profile', async () => {
      const successResponse: ApiResponse<Parent> = {
        success: true,
        data: mockParent,
        error: null,
      };

      api.get.mockResolvedValue(successResponse);

      const result = await getCurrentUser();

      expect(result.success).toBe(true);
      expect(result.data).toEqual(mockParent);
      expect(api.get).toHaveBeenCalledWith(
        '/modules/ParentPortal/api/auth/me',
        undefined,
        'gibbon',
      );
    });

    it('should handle unauthorized access', async () => {
      const errorResponse: ApiResponse<Parent> = {
        success: false,
        data: null,
        error: {
          code: 'UNAUTHORIZED',
          message: 'Authentication required',
        },
      };

      api.get.mockResolvedValue(errorResponse);

      const result = await getCurrentUser();

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('UNAUTHORIZED');
    });

    it('should handle expired access token', async () => {
      const errorResponse: ApiResponse<Parent> = {
        success: false,
        data: null,
        error: {
          code: 'TOKEN_EXPIRED',
          message: 'Access token has expired',
        },
      };

      api.get.mockResolvedValue(errorResponse);

      const result = await getCurrentUser();

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('TOKEN_EXPIRED');
    });
  });

  describe('initializeAuth', () => {
    const storedAccessToken = 'stored-access-token';
    const storedRefreshToken = 'stored-refresh-token';

    it('should successfully initialize with valid access token', async () => {
      const userResponse: ApiResponse<Parent> = {
        success: true,
        data: mockParent,
        error: null,
      };

      api.get.mockResolvedValue(userResponse);

      const result = await initializeAuth(storedAccessToken, storedRefreshToken);

      expect(result).not.toBeNull();
      expect(result?.user).toEqual(mockParent);
      expect(result?.accessToken).toBe(storedAccessToken);
      expect(result?.refreshToken).toBe(storedRefreshToken);
      expect(setSessionToken).toHaveBeenCalledWith(storedAccessToken);
    });

    it('should refresh token and initialize when access token is expired', async () => {
      const expiredTokenResponse: ApiResponse<Parent> = {
        success: false,
        data: null,
        error: {
          code: 'TOKEN_EXPIRED',
          message: 'Access token has expired',
        },
      };

      const refreshResponse: ApiResponse<RefreshResponse> = {
        success: true,
        data: mockRefreshResponse,
        error: null,
      };

      const userResponse: ApiResponse<Parent> = {
        success: true,
        data: mockParent,
        error: null,
      };

      // First getCurrentUser call fails, then refresh succeeds, then getCurrentUser succeeds
      api.get.mockResolvedValueOnce(expiredTokenResponse).mockResolvedValueOnce(userResponse);
      api.post.mockResolvedValue(refreshResponse);

      const result = await initializeAuth(storedAccessToken, storedRefreshToken);

      expect(result).not.toBeNull();
      expect(result?.user).toEqual(mockParent);
      expect(result?.accessToken).toBe(mockRefreshResponse.accessToken);
      expect(result?.refreshToken).toBe(mockRefreshResponse.refreshToken);
    });

    it('should return null when both access token and refresh token are invalid', async () => {
      const expiredTokenResponse: ApiResponse<Parent> = {
        success: false,
        data: null,
        error: {
          code: 'TOKEN_EXPIRED',
          message: 'Access token has expired',
        },
      };

      const refreshFailedResponse: ApiResponse<RefreshResponse> = {
        success: false,
        data: null,
        error: {
          code: 'TOKEN_EXPIRED',
          message: 'Refresh token has expired',
        },
      };

      api.get.mockResolvedValue(expiredTokenResponse);
      api.post.mockResolvedValue(refreshFailedResponse);

      const result = await initializeAuth(storedAccessToken, storedRefreshToken);

      expect(result).toBeNull();
      // Session token should be cleared when initialization fails
      expect(setSessionToken).toHaveBeenLastCalledWith(null);
    });

    it('should return null when refresh succeeds but fetching user fails', async () => {
      const expiredTokenResponse: ApiResponse<Parent> = {
        success: false,
        data: null,
        error: {
          code: 'TOKEN_EXPIRED',
          message: 'Access token has expired',
        },
      };

      const refreshResponse: ApiResponse<RefreshResponse> = {
        success: true,
        data: mockRefreshResponse,
        error: null,
      };

      const userFetchError: ApiResponse<Parent> = {
        success: false,
        data: null,
        error: {
          code: 'SERVER_ERROR',
          message: 'Failed to fetch user',
        },
      };

      api.get.mockResolvedValueOnce(expiredTokenResponse).mockResolvedValueOnce(userFetchError);
      api.post.mockResolvedValue(refreshResponse);

      const result = await initializeAuth(storedAccessToken, storedRefreshToken);

      expect(result).toBeNull();
      expect(setSessionToken).toHaveBeenLastCalledWith(null);
    });

    it('should handle network error during initialization', async () => {
      const networkError: ApiResponse<Parent> = {
        success: false,
        data: null,
        error: {
          code: 'NETWORK_ERROR',
          message: 'Network request failed',
        },
      };

      const refreshNetworkError: ApiResponse<RefreshResponse> = {
        success: false,
        data: null,
        error: {
          code: 'NETWORK_ERROR',
          message: 'Network request failed',
        },
      };

      api.get.mockResolvedValue(networkError);
      api.post.mockResolvedValue(refreshNetworkError);

      const result = await initializeAuth(storedAccessToken, storedRefreshToken);

      expect(result).toBeNull();
    });
  });

  describe('hasActiveSession', () => {
    it('should return true when session token is set', () => {
      (getSessionToken as jest.Mock).mockReturnValue('valid-token');

      const result = hasActiveSession();

      expect(result).toBe(true);
      expect(getSessionToken).toHaveBeenCalled();
    });

    it('should return false when session token is null', () => {
      (getSessionToken as jest.Mock).mockReturnValue(null);

      const result = hasActiveSession();

      expect(result).toBe(false);
    });
  });

  describe('clearSession', () => {
    it('should clear the session token', () => {
      clearSession();

      expect(setSessionToken).toHaveBeenCalledWith(null);
    });
  });

  describe('TOKEN_STORAGE_KEYS', () => {
    it('should have correct storage key constants', () => {
      expect(TOKEN_STORAGE_KEYS.accessToken).toBe('laya_parent_access_token');
      expect(TOKEN_STORAGE_KEYS.refreshToken).toBe('laya_parent_refresh_token');
    });
  });
});
