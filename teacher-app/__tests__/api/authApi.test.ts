/**
 * LAYA Teacher App - authApi Unit Tests
 *
 * Unit tests for the authentication API functions.
 */

import {
  login,
  logout,
  refreshAccessToken,
  getCurrentUser,
  initializeAuth,
  hasActiveSession,
  clearSession,
} from '../../src/api/authApi';
import type {
  LoginCredentials,
  LoginResponse,
  RefreshResponse,
  Teacher,
  ApiResponse,
} from '../../src/types';

// Mock the client module
jest.mock('../../src/api/client', () => {
  let sessionToken: string | null = null;

  return {
    api: {
      post: jest.fn(),
      get: jest.fn(),
    },
    setSessionToken: jest.fn((token: string | null) => {
      sessionToken = token;
    }),
    getSessionToken: jest.fn(() => sessionToken),
  };
});

// Import the mocked client functions after mocking
import {api, setSessionToken, getSessionToken} from '../../src/api/client';

// Type assertion for mocked functions
const mockApiPost = api.post as jest.MockedFunction<typeof api.post>;
const mockApiGet = api.get as jest.MockedFunction<typeof api.get>;
const mockSetSessionToken = setSessionToken as jest.MockedFunction<
  typeof setSessionToken
>;
const mockGetSessionToken = getSessionToken as jest.MockedFunction<
  typeof getSessionToken
>;

// Test fixtures
const mockTeacher: Teacher = {
  id: 'teacher-123',
  firstName: 'Jane',
  lastName: 'Smith',
  email: 'jane.smith@school.edu',
  classroomIds: ['classroom-1', 'classroom-2'],
};

const mockLoginCredentials: LoginCredentials = {
  email: 'jane.smith@school.edu',
  password: 'securePassword123',
};

const mockLoginResponse: LoginResponse = {
  user: mockTeacher,
  accessToken: 'mock-access-token-xyz',
  refreshToken: 'mock-refresh-token-abc',
  expiresIn: 3600,
};

const mockRefreshResponse: RefreshResponse = {
  accessToken: 'new-access-token-123',
  refreshToken: 'new-refresh-token-456',
  expiresIn: 3600,
};

describe('authApi', () => {
  beforeEach(() => {
    // Clear all mocks before each test
    jest.clearAllMocks();
    // Reset the session token
    mockSetSessionToken(null);
  });

  describe('login', () => {
    it('should call api.post with correct endpoint and credentials', async () => {
      const successResponse: ApiResponse<LoginResponse> = {
        success: true,
        data: mockLoginResponse,
        error: null,
      };
      mockApiPost.mockResolvedValueOnce(successResponse);

      await login(mockLoginCredentials);

      expect(mockApiPost).toHaveBeenCalledWith(
        '/modules/TeacherPortal/api/auth/login',
        mockLoginCredentials,
      );
    });

    it('should set session token on successful login', async () => {
      const successResponse: ApiResponse<LoginResponse> = {
        success: true,
        data: mockLoginResponse,
        error: null,
      };
      mockApiPost.mockResolvedValueOnce(successResponse);

      const result = await login(mockLoginCredentials);

      expect(result.success).toBe(true);
      expect(result.data).toEqual(mockLoginResponse);
      expect(mockSetSessionToken).toHaveBeenCalledWith(
        mockLoginResponse.accessToken,
      );
    });

    it('should not set session token on failed login', async () => {
      const errorResponse: ApiResponse<LoginResponse> = {
        success: false,
        data: null,
        error: {code: 'INVALID_CREDENTIALS', message: 'Invalid email or password'},
      };
      mockApiPost.mockResolvedValueOnce(errorResponse);

      const result = await login(mockLoginCredentials);

      expect(result.success).toBe(false);
      expect(result.error).toEqual({
        code: 'INVALID_CREDENTIALS',
        message: 'Invalid email or password',
      });
      // setSessionToken should not be called for failed logins
      expect(mockSetSessionToken).not.toHaveBeenCalled();
    });

    it('should return error response when login fails', async () => {
      const errorResponse: ApiResponse<LoginResponse> = {
        success: false,
        data: null,
        error: {code: 'NETWORK_ERROR', message: 'Network request failed'},
      };
      mockApiPost.mockResolvedValueOnce(errorResponse);

      const result = await login(mockLoginCredentials);

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('NETWORK_ERROR');
    });
  });

  describe('logout', () => {
    it('should call api.post with correct endpoint', async () => {
      const successResponse: ApiResponse<void> = {
        success: true,
        data: null,
        error: null,
      };
      mockApiPost.mockResolvedValueOnce(successResponse);

      await logout();

      expect(mockApiPost).toHaveBeenCalledWith(
        '/modules/TeacherPortal/api/auth/logout',
      );
    });

    it('should clear session token on successful logout', async () => {
      const successResponse: ApiResponse<void> = {
        success: true,
        data: null,
        error: null,
      };
      mockApiPost.mockResolvedValueOnce(successResponse);

      await logout();

      expect(mockSetSessionToken).toHaveBeenCalledWith(null);
    });

    it('should clear session token even on failed logout', async () => {
      const errorResponse: ApiResponse<void> = {
        success: false,
        data: null,
        error: {code: 'SERVER_ERROR', message: 'Internal server error'},
      };
      mockApiPost.mockResolvedValueOnce(errorResponse);

      await logout();

      // Session token should be cleared regardless of server response
      expect(mockSetSessionToken).toHaveBeenCalledWith(null);
    });

    it('should return the API response', async () => {
      const successResponse: ApiResponse<void> = {
        success: true,
        data: null,
        error: null,
      };
      mockApiPost.mockResolvedValueOnce(successResponse);

      const result = await logout();

      expect(result).toEqual(successResponse);
    });
  });

  describe('refreshAccessToken', () => {
    const mockRefreshToken = 'current-refresh-token';

    it('should call api.post with correct endpoint and refresh token', async () => {
      const successResponse: ApiResponse<RefreshResponse> = {
        success: true,
        data: mockRefreshResponse,
        error: null,
      };
      mockApiPost.mockResolvedValueOnce(successResponse);

      await refreshAccessToken(mockRefreshToken);

      expect(mockApiPost).toHaveBeenCalledWith(
        '/modules/TeacherPortal/api/auth/refresh',
        {refreshToken: mockRefreshToken},
      );
    });

    it('should set new session token on successful refresh', async () => {
      const successResponse: ApiResponse<RefreshResponse> = {
        success: true,
        data: mockRefreshResponse,
        error: null,
      };
      mockApiPost.mockResolvedValueOnce(successResponse);

      const result = await refreshAccessToken(mockRefreshToken);

      expect(result.success).toBe(true);
      expect(result.data).toEqual(mockRefreshResponse);
      expect(mockSetSessionToken).toHaveBeenCalledWith(
        mockRefreshResponse.accessToken,
      );
    });

    it('should not set session token on failed refresh', async () => {
      const errorResponse: ApiResponse<RefreshResponse> = {
        success: false,
        data: null,
        error: {code: 'INVALID_REFRESH_TOKEN', message: 'Refresh token expired'},
      };
      mockApiPost.mockResolvedValueOnce(errorResponse);

      const result = await refreshAccessToken(mockRefreshToken);

      expect(result.success).toBe(false);
      expect(mockSetSessionToken).not.toHaveBeenCalled();
    });

    it('should return new tokens on successful refresh', async () => {
      const successResponse: ApiResponse<RefreshResponse> = {
        success: true,
        data: mockRefreshResponse,
        error: null,
      };
      mockApiPost.mockResolvedValueOnce(successResponse);

      const result = await refreshAccessToken(mockRefreshToken);

      expect(result.data?.accessToken).toBe('new-access-token-123');
      expect(result.data?.refreshToken).toBe('new-refresh-token-456');
    });
  });

  describe('getCurrentUser', () => {
    it('should call api.get with correct endpoint', async () => {
      const successResponse: ApiResponse<Teacher> = {
        success: true,
        data: mockTeacher,
        error: null,
      };
      mockApiGet.mockResolvedValueOnce(successResponse);

      await getCurrentUser();

      expect(mockApiGet).toHaveBeenCalledWith(
        '/modules/TeacherPortal/api/auth/me',
      );
    });

    it('should return teacher data on success', async () => {
      const successResponse: ApiResponse<Teacher> = {
        success: true,
        data: mockTeacher,
        error: null,
      };
      mockApiGet.mockResolvedValueOnce(successResponse);

      const result = await getCurrentUser();

      expect(result.success).toBe(true);
      expect(result.data).toEqual(mockTeacher);
    });

    it('should return error on unauthorized access', async () => {
      const errorResponse: ApiResponse<Teacher> = {
        success: false,
        data: null,
        error: {code: 'UNAUTHORIZED', message: 'Authentication required'},
      };
      mockApiGet.mockResolvedValueOnce(errorResponse);

      const result = await getCurrentUser();

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('UNAUTHORIZED');
    });
  });

  describe('initializeAuth', () => {
    const storedAccessToken = 'stored-access-token';
    const storedRefreshToken = 'stored-refresh-token';

    it('should set the stored access token and fetch user profile', async () => {
      const userResponse: ApiResponse<Teacher> = {
        success: true,
        data: mockTeacher,
        error: null,
      };
      mockApiGet.mockResolvedValueOnce(userResponse);

      const result = await initializeAuth(storedAccessToken, storedRefreshToken);

      expect(mockSetSessionToken).toHaveBeenCalledWith(storedAccessToken);
      expect(mockApiGet).toHaveBeenCalledWith(
        '/modules/TeacherPortal/api/auth/me',
      );
      expect(result).toEqual({
        user: mockTeacher,
        accessToken: storedAccessToken,
        refreshToken: storedRefreshToken,
      });
    });

    it('should return auth state when access token is valid', async () => {
      const userResponse: ApiResponse<Teacher> = {
        success: true,
        data: mockTeacher,
        error: null,
      };
      mockApiGet.mockResolvedValueOnce(userResponse);

      const result = await initializeAuth(storedAccessToken, storedRefreshToken);

      expect(result).not.toBeNull();
      expect(result?.user).toEqual(mockTeacher);
      expect(result?.accessToken).toBe(storedAccessToken);
      expect(result?.refreshToken).toBe(storedRefreshToken);
    });

    it('should refresh token when access token is expired', async () => {
      // First call - getCurrentUser fails
      const userErrorResponse: ApiResponse<Teacher> = {
        success: false,
        data: null,
        error: {code: 'TOKEN_EXPIRED', message: 'Access token expired'},
      };
      // Second call - refreshAccessToken succeeds
      const refreshResponse: ApiResponse<RefreshResponse> = {
        success: true,
        data: mockRefreshResponse,
        error: null,
      };
      // Third call - getCurrentUser succeeds with new token
      const userSuccessResponse: ApiResponse<Teacher> = {
        success: true,
        data: mockTeacher,
        error: null,
      };

      mockApiGet
        .mockResolvedValueOnce(userErrorResponse)
        .mockResolvedValueOnce(userSuccessResponse);
      mockApiPost.mockResolvedValueOnce(refreshResponse);

      const result = await initializeAuth(storedAccessToken, storedRefreshToken);

      expect(result).not.toBeNull();
      expect(result?.accessToken).toBe(mockRefreshResponse.accessToken);
      expect(result?.refreshToken).toBe(mockRefreshResponse.refreshToken);
      expect(result?.user).toEqual(mockTeacher);
    });

    it('should return null when both access token and refresh fail', async () => {
      // First call - getCurrentUser fails
      const userErrorResponse: ApiResponse<Teacher> = {
        success: false,
        data: null,
        error: {code: 'TOKEN_EXPIRED', message: 'Access token expired'},
      };
      // Second call - refreshAccessToken fails
      const refreshErrorResponse: ApiResponse<RefreshResponse> = {
        success: false,
        data: null,
        error: {code: 'REFRESH_TOKEN_EXPIRED', message: 'Refresh token expired'},
      };

      mockApiGet.mockResolvedValueOnce(userErrorResponse);
      mockApiPost.mockResolvedValueOnce(refreshErrorResponse);

      const result = await initializeAuth(storedAccessToken, storedRefreshToken);

      expect(result).toBeNull();
      // Session should be cleared when both tokens fail
      expect(mockSetSessionToken).toHaveBeenLastCalledWith(null);
    });

    it('should return null when refresh succeeds but user fetch fails', async () => {
      // First call - getCurrentUser fails
      const userErrorResponse: ApiResponse<Teacher> = {
        success: false,
        data: null,
        error: {code: 'TOKEN_EXPIRED', message: 'Access token expired'},
      };
      // Second call - refreshAccessToken succeeds
      const refreshResponse: ApiResponse<RefreshResponse> = {
        success: true,
        data: mockRefreshResponse,
        error: null,
      };
      // Third call - getCurrentUser still fails
      const userStillFailsResponse: ApiResponse<Teacher> = {
        success: false,
        data: null,
        error: {code: 'UNKNOWN_ERROR', message: 'Unknown error'},
      };

      mockApiGet
        .mockResolvedValueOnce(userErrorResponse)
        .mockResolvedValueOnce(userStillFailsResponse);
      mockApiPost.mockResolvedValueOnce(refreshResponse);

      const result = await initializeAuth(storedAccessToken, storedRefreshToken);

      expect(result).toBeNull();
    });
  });

  describe('hasActiveSession', () => {
    it('should return true when session token exists', () => {
      mockGetSessionToken.mockReturnValueOnce('some-token');

      const result = hasActiveSession();

      expect(result).toBe(true);
      expect(mockGetSessionToken).toHaveBeenCalled();
    });

    it('should return false when session token is null', () => {
      mockGetSessionToken.mockReturnValueOnce(null);

      const result = hasActiveSession();

      expect(result).toBe(false);
      expect(mockGetSessionToken).toHaveBeenCalled();
    });
  });

  describe('clearSession', () => {
    it('should clear the session token', () => {
      clearSession();

      expect(mockSetSessionToken).toHaveBeenCalledWith(null);
    });
  });

  describe('default export', () => {
    it('should export all functions as object', async () => {
      // Import default export
      const authApi = await import('../../src/api/authApi').then(m => m.default);

      expect(authApi).toHaveProperty('login');
      expect(authApi).toHaveProperty('logout');
      expect(authApi).toHaveProperty('refreshAccessToken');
      expect(authApi).toHaveProperty('getCurrentUser');
      expect(authApi).toHaveProperty('initializeAuth');
      expect(authApi).toHaveProperty('hasActiveSession');
      expect(authApi).toHaveProperty('clearSession');
    });
  });

  describe('TOKEN_STORAGE_KEYS export', () => {
    it('should export TOKEN_STORAGE_KEYS from types', async () => {
      const {TOKEN_STORAGE_KEYS} = await import('../../src/api/authApi');

      expect(TOKEN_STORAGE_KEYS).toBeDefined();
      expect(TOKEN_STORAGE_KEYS.accessToken).toBe('laya_teacher_access_token');
      expect(TOKEN_STORAGE_KEYS.refreshToken).toBe('laya_teacher_refresh_token');
      expect(TOKEN_STORAGE_KEYS.biometricEnabled).toBe(
        'laya_teacher_biometric_enabled',
      );
      expect(TOKEN_STORAGE_KEYS.storedEmail).toBe('laya_teacher_stored_email');
    });
  });
});
