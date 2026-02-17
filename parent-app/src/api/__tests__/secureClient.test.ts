/**
 * LAYA Parent App - Secure API Client Tests
 *
 * Comprehensive test suite for the secure API client with token management.
 */

import {
  secureApiRequest,
  secureApi,
  authApi,
  setAuthTokens,
  clearAuthTokens,
} from '../secureClient';
import * as secureStorage from '../../utils/secureStorage';

// Mock dependencies
jest.mock('../../utils/secureStorage');
const mockedSecureStorage = secureStorage as jest.Mocked<typeof secureStorage>;

// Mock fetch
global.fetch = jest.fn();
const mockedFetch = global.fetch as jest.MockedFunction<typeof fetch>;

describe('secureClient', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    mockedFetch.mockClear();

    // Default mock implementations
    mockedSecureStorage.getItem.mockResolvedValue({
      success: true,
      data: null,
    });
    mockedSecureStorage.setItem.mockResolvedValue({success: true});
    mockedSecureStorage.removeItem.mockResolvedValue({success: true});
  });

  describe('secureApiRequest', () => {
    it('should make a successful GET request without auth', async () => {
      const mockData = {id: '123', name: 'Test'};
      mockedFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockData,
      } as Response);

      const result = await secureApiRequest('/test', {skipAuth: true});

      expect(result).toEqual({
        success: true,
        data: mockData,
        error: null,
      });
      expect(mockedFetch).toHaveBeenCalledWith(
        expect.stringContaining('/test'),
        expect.objectContaining({
          method: 'GET',
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
            Accept: 'application/json',
          }),
        }),
      );
    });

    it('should include Authorization header when token is available', async () => {
      const mockToken = 'test-token-123';
      mockedSecureStorage.getItem.mockResolvedValueOnce({
        success: true,
        data: mockToken,
      });

      mockedFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({success: true}),
      } as Response);

      await secureApiRequest('/test');

      expect(mockedFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          headers: expect.objectContaining({
            Authorization: `Bearer ${mockToken}`,
          }),
        }),
      );
    });

    it('should make POST request with body', async () => {
      const requestBody = {email: 'test@example.com', password: 'password'};
      const mockResponse = {success: true, userId: '123'};

      mockedFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => mockResponse,
      } as Response);

      const result = await secureApiRequest('/auth/login', {
        method: 'POST',
        body: requestBody,
        skipAuth: true,
      });

      expect(result.success).toBe(true);
      expect(result.data).toEqual(mockResponse);
      expect(mockedFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify(requestBody),
        }),
      );
    });

    it('should handle 401 error and attempt token refresh', async () => {
      const oldToken = 'old-token';
      const newToken = 'new-token';
      const refreshToken = 'refresh-token';
      const mockData = {success: true};

      // First getItem returns old token
      mockedSecureStorage.getItem
        .mockResolvedValueOnce({success: true, data: oldToken})
        .mockResolvedValueOnce({success: true, data: refreshToken});

      // First request returns 401
      mockedFetch
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          json: async () => ({error: {code: 'UNAUTHORIZED', message: 'Token expired'}}),
        } as Response)
        // Token refresh succeeds
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: async () => ({accessToken: newToken, refreshToken: 'new-refresh-token'}),
        } as Response)
        // Retry request succeeds
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: async () => mockData,
        } as Response);

      const result = await secureApiRequest('/test');

      expect(result.success).toBe(true);
      expect(result.data).toEqual(mockData);
      expect(mockedFetch).toHaveBeenCalledTimes(3); // Original + refresh + retry
      expect(mockedSecureStorage.setItem).toHaveBeenCalledWith(
        secureStorage.STORAGE_KEYS.ACCESS_TOKEN,
        newToken,
      );
    });

    it('should return error when token refresh fails', async () => {
      const oldToken = 'old-token';
      const refreshToken = 'refresh-token';

      mockedSecureStorage.getItem
        .mockResolvedValueOnce({success: true, data: oldToken})
        .mockResolvedValueOnce({success: true, data: refreshToken});

      // First request returns 401
      mockedFetch
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          json: async () => ({error: {code: 'UNAUTHORIZED'}}),
        } as Response)
        // Token refresh fails
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          json: async () => ({error: {code: 'INVALID_REFRESH_TOKEN'}}),
        } as Response);

      const result = await secureApiRequest('/test');

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('AUTH_REQUIRED');
      expect(mockedSecureStorage.removeItem).toHaveBeenCalled();
    });

    it('should handle 404 error', async () => {
      mockedFetch.mockResolvedValueOnce({
        ok: false,
        status: 404,
        json: async () => ({error: {code: 'NOT_FOUND', message: 'Resource not found'}}),
      } as Response);

      const result = await secureApiRequest('/test', {skipAuth: true});

      expect(result.success).toBe(false);
      expect(result.error).toEqual({
        code: 'NOT_FOUND',
        message: 'Resource not found',
      });
    });

    it('should handle 500 error', async () => {
      mockedFetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
        json: async () => ({}),
      } as Response);

      const result = await secureApiRequest('/test', {skipAuth: true});

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('HTTP_500');
      expect(result.error?.message).toContain('Server error');
    });

    it('should handle network error', async () => {
      mockedFetch.mockRejectedValueOnce(new Error('Network request failed'));

      const result = await secureApiRequest('/test', {skipAuth: true});

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('NETWORK_ERROR');
      expect(result.error?.message).toContain('Network request failed');
    });

    it('should handle timeout error', async () => {
      mockedFetch.mockRejectedValueOnce(
        Object.assign(new Error('Aborted'), {name: 'AbortError'}),
      );

      const result = await secureApiRequest('/test', {skipAuth: true, timeout: 5000});

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('TIMEOUT');
      expect(result.error?.message).toContain('timed out');
    });

    it('should handle malformed JSON response', async () => {
      mockedFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => {
          throw new Error('Invalid JSON');
        },
      } as Response);

      const result = await secureApiRequest('/test', {skipAuth: true});

      expect(result.success).toBe(true);
      expect(result.data).toBeNull();
    });

    it('should include custom headers', async () => {
      mockedFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({success: true}),
      } as Response);

      await secureApiRequest('/test', {
        skipAuth: true,
        headers: {'X-Custom-Header': 'test-value'},
      });

      expect(mockedFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          headers: expect.objectContaining({
            'X-Custom-Header': 'test-value',
          }),
        }),
      );
    });

    it('should handle query parameters', async () => {
      mockedFetch.mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({success: true}),
      } as Response);

      await secureApiRequest('/test', {
        skipAuth: true,
        params: {page: '1', limit: '10'},
      });

      expect(mockedFetch).toHaveBeenCalledWith(
        expect.stringContaining('page=1'),
        expect.any(Object),
      );
    });
  });

  describe('secureApi convenience methods', () => {
    beforeEach(() => {
      mockedFetch.mockResolvedValue({
        ok: true,
        status: 200,
        json: async () => ({success: true}),
      } as Response);
    });

    it('should make GET request', async () => {
      await secureApi.get('/test');

      expect(mockedFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({method: 'GET'}),
      );
    });

    it('should make POST request', async () => {
      const body = {name: 'Test'};
      await secureApi.post('/test', body);

      expect(mockedFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          method: 'POST',
          body: JSON.stringify(body),
        }),
      );
    });

    it('should make PUT request', async () => {
      const body = {name: 'Updated'};
      await secureApi.put('/test', body);

      expect(mockedFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          method: 'PUT',
          body: JSON.stringify(body),
        }),
      );
    });

    it('should make PATCH request', async () => {
      const body = {status: 'active'};
      await secureApi.patch('/test', body);

      expect(mockedFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          method: 'PATCH',
          body: JSON.stringify(body),
        }),
      );
    });

    it('should make DELETE request', async () => {
      await secureApi.delete('/test/123');

      expect(mockedFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({method: 'DELETE'}),
      );
    });
  });

  describe('authApi', () => {
    describe('login', () => {
      it('should login and store tokens', async () => {
        const mockResponse = {
          user: {id: '123', email: 'test@example.com'},
          accessToken: 'access-token',
          refreshToken: 'refresh-token',
        };

        mockedFetch.mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: async () => mockResponse,
        } as Response);

        const result = await authApi.login('test@example.com', 'password');

        expect(result.success).toBe(true);
        expect(result.data).toEqual(mockResponse);
        expect(mockedSecureStorage.setItem).toHaveBeenCalledWith(
          secureStorage.STORAGE_KEYS.ACCESS_TOKEN,
          'access-token',
        );
        expect(mockedSecureStorage.setItem).toHaveBeenCalledWith(
          secureStorage.STORAGE_KEYS.REFRESH_TOKEN,
          'refresh-token',
        );
        expect(mockedSecureStorage.setItem).toHaveBeenCalledWith(
          secureStorage.STORAGE_KEYS.USER_ID,
          '123',
        );
      });

      it('should handle login failure', async () => {
        mockedFetch.mockResolvedValueOnce({
          ok: false,
          status: 401,
          json: async () => ({
            error: {code: 'INVALID_CREDENTIALS', message: 'Invalid email or password'},
          }),
        } as Response);

        const result = await authApi.login('test@example.com', 'wrong-password');

        expect(result.success).toBe(false);
        expect(result.error?.code).toBe('INVALID_CREDENTIALS');
        expect(mockedSecureStorage.setItem).not.toHaveBeenCalled();
      });
    });

    describe('logout', () => {
      it('should logout and clear tokens', async () => {
        mockedFetch.mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: async () => ({success: true}),
        } as Response);

        const result = await authApi.logout();

        expect(result.success).toBe(true);
        expect(mockedSecureStorage.removeItem).toHaveBeenCalledWith(
          secureStorage.STORAGE_KEYS.ACCESS_TOKEN,
        );
        expect(mockedSecureStorage.removeItem).toHaveBeenCalledWith(
          secureStorage.STORAGE_KEYS.REFRESH_TOKEN,
        );
      });

      it('should clear tokens even if logout endpoint fails', async () => {
        mockedFetch.mockRejectedValueOnce(new Error('Network error'));

        const result = await authApi.logout();

        expect(result.success).toBe(true);
        expect(mockedSecureStorage.removeItem).toHaveBeenCalled();
      });
    });

    describe('getCurrentUser', () => {
      it('should fetch current user', async () => {
        const mockUser = {id: '123', email: 'test@example.com', name: 'Test User'};
        mockedSecureStorage.getItem.mockResolvedValueOnce({
          success: true,
          data: 'test-token',
        });

        mockedFetch.mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: async () => mockUser,
        } as Response);

        const result = await authApi.getCurrentUser();

        expect(result.success).toBe(true);
        expect(result.data).toEqual(mockUser);
      });

      it('should return error if not authenticated', async () => {
        mockedSecureStorage.getItem.mockResolvedValueOnce({
          success: true,
          data: null,
        });

        mockedFetch.mockResolvedValueOnce({
          ok: false,
          status: 401,
          json: async () => ({error: {code: 'UNAUTHORIZED'}}),
        } as Response);

        const result = await authApi.getCurrentUser();

        expect(result.success).toBe(false);
      });
    });

    describe('isAuthenticated', () => {
      it('should return true when token exists', async () => {
        mockedSecureStorage.getItem.mockResolvedValueOnce({
          success: true,
          data: 'test-token',
        });

        const result = await authApi.isAuthenticated();

        expect(result).toBe(true);
      });

      it('should return false when no token exists', async () => {
        mockedSecureStorage.getItem.mockResolvedValueOnce({
          success: true,
          data: null,
        });

        const result = await authApi.isAuthenticated();

        expect(result).toBe(false);
      });
    });
  });

  describe('setAuthTokens', () => {
    it('should store access token', async () => {
      await setAuthTokens('access-token');

      expect(mockedSecureStorage.setItem).toHaveBeenCalledWith(
        secureStorage.STORAGE_KEYS.ACCESS_TOKEN,
        'access-token',
      );
    });

    it('should store both access and refresh tokens', async () => {
      await setAuthTokens('access-token', 'refresh-token');

      expect(mockedSecureStorage.setItem).toHaveBeenCalledWith(
        secureStorage.STORAGE_KEYS.ACCESS_TOKEN,
        'access-token',
      );
      expect(mockedSecureStorage.setItem).toHaveBeenCalledWith(
        secureStorage.STORAGE_KEYS.REFRESH_TOKEN,
        'refresh-token',
      );
    });
  });

  describe('clearAuthTokens', () => {
    it('should remove all auth-related tokens', async () => {
      await clearAuthTokens();

      expect(mockedSecureStorage.removeItem).toHaveBeenCalledWith(
        secureStorage.STORAGE_KEYS.ACCESS_TOKEN,
      );
      expect(mockedSecureStorage.removeItem).toHaveBeenCalledWith(
        secureStorage.STORAGE_KEYS.REFRESH_TOKEN,
      );
      expect(mockedSecureStorage.removeItem).toHaveBeenCalledWith(
        secureStorage.STORAGE_KEYS.USER_ID,
      );
      expect(mockedSecureStorage.removeItem).toHaveBeenCalledWith(
        secureStorage.STORAGE_KEYS.USER_EMAIL,
      );
    });
  });

  describe('Token Refresh Logic', () => {
    it('should not refresh token if skipAuth is true', async () => {
      mockedFetch.mockResolvedValueOnce({
        ok: false,
        status: 401,
        json: async () => ({error: {code: 'UNAUTHORIZED'}}),
      } as Response);

      const result = await secureApiRequest('/test', {skipAuth: true});

      expect(result.success).toBe(false);
      expect(mockedFetch).toHaveBeenCalledTimes(1); // No retry
    });

    it('should clear tokens when no refresh token is available', async () => {
      const oldToken = 'old-token';
      mockedSecureStorage.getItem
        .mockResolvedValueOnce({success: true, data: oldToken})
        .mockResolvedValueOnce({success: true, data: null}); // No refresh token

      mockedFetch.mockResolvedValueOnce({
        ok: false,
        status: 401,
        json: async () => ({error: {code: 'UNAUTHORIZED'}}),
      } as Response);

      const result = await secureApiRequest('/test');

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('AUTH_REQUIRED');
    });

    it('should handle refresh endpoint returning invalid response', async () => {
      const oldToken = 'old-token';
      const refreshToken = 'refresh-token';

      mockedSecureStorage.getItem
        .mockResolvedValueOnce({success: true, data: oldToken})
        .mockResolvedValueOnce({success: true, data: refreshToken});

      mockedFetch
        .mockResolvedValueOnce({
          ok: false,
          status: 401,
          json: async () => ({error: {code: 'UNAUTHORIZED'}}),
        } as Response)
        .mockResolvedValueOnce({
          ok: true,
          status: 200,
          json: async () => ({success: true}), // No accessToken in response
        } as Response);

      const result = await secureApiRequest('/test');

      expect(result.success).toBe(false);
      expect(mockedSecureStorage.removeItem).toHaveBeenCalled();
    });
  });

  describe('Error Handling', () => {
    it('should handle 400 Bad Request', async () => {
      mockedFetch.mockResolvedValueOnce({
        ok: false,
        status: 400,
        json: async () => ({error: {code: 'VALIDATION_ERROR', message: 'Invalid data'}}),
      } as Response);

      const result = await secureApiRequest('/test', {skipAuth: true});

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('VALIDATION_ERROR');
    });

    it('should handle 403 Forbidden', async () => {
      mockedFetch.mockResolvedValueOnce({
        ok: false,
        status: 403,
        json: async () => ({}),
      } as Response);

      const result = await secureApiRequest('/test', {skipAuth: true});

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('HTTP_403');
      expect(result.error?.message).toContain('Access denied');
    });

    it('should handle 429 Too Many Requests', async () => {
      mockedFetch.mockResolvedValueOnce({
        ok: false,
        status: 429,
        json: async () => ({}),
      } as Response);

      const result = await secureApiRequest('/test', {skipAuth: true});

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('HTTP_429');
      expect(result.error?.message).toContain('Too many requests');
    });

    it('should handle unknown error', async () => {
      mockedFetch.mockRejectedValueOnce('Unknown error');

      const result = await secureApiRequest('/test', {skipAuth: true});

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('UNKNOWN_ERROR');
    });
  });
});
