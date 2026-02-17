/**
 * LAYA Teacher App - Auth Service Biometric Tests
 *
 * Unit tests for biometric authentication functionality in authService,
 * testing biometric availability checks, authentication, and credential storage.
 */

import ReactNativeBiometrics from 'react-native-biometrics';
import {
  checkBiometricAvailability,
  authenticateWithBiometrics,
  loginWithBiometrics,
  enableBiometricLogin,
  disableBiometricLogin,
  isBiometricLoginEnabled,
  getStoredEmail,
  setStoredCredentials,
  clearStoredCredentials,
  login,
  logout,
} from '../../src/services/authService';
import type {LoginResponse, Teacher} from '../../src/types';

// Mock react-native-biometrics
jest.mock('react-native-biometrics');

// Mock API client
jest.mock('../../src/api/client', () => ({
  api: {
    post: jest.fn(),
  },
  setSessionToken: jest.fn(),
  getSessionToken: jest.fn(),
}));

// Mock authApi
jest.mock('../../src/api/authApi', () => ({
  getCurrentUser: jest.fn(),
}));

// Import mocked modules
import {api, setSessionToken} from '../../src/api/client';
import {getCurrentUser as fetchCurrentUser} from '../../src/api/authApi';

// Type assertions for mocked functions
const mockApi = api as jest.Mocked<typeof api>;
const mockSetSessionToken = setSessionToken as jest.MockedFunction<typeof setSessionToken>;
const mockFetchCurrentUser = fetchCurrentUser as jest.MockedFunction<typeof fetchCurrentUser>;

// Test fixtures
const mockTeacher: Teacher = {
  id: 'teacher-123',
  firstName: 'Jane',
  lastName: 'Smith',
  email: 'jane.smith@school.edu',
  classroomIds: ['classroom-1', 'classroom-2'],
};

const mockLoginResponse: LoginResponse = {
  user: mockTeacher,
  accessToken: 'mock-access-token-xyz',
  refreshToken: 'mock-refresh-token-abc',
  expiresIn: 3600,
};

const mockRefreshResponse = {
  accessToken: 'new-access-token-xyz',
  refreshToken: 'new-refresh-token-abc',
  expiresIn: 3600,
};

describe('authService - Biometric Authentication', () => {
  beforeEach(() => {
    jest.clearAllMocks();

    // Reset biometric state
    clearStoredCredentials();
    logout();

    // Default mock for ReactNativeBiometrics constructor
    (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
      () =>
        ({
          isSensorAvailable: jest.fn(),
          simplePrompt: jest.fn(),
        } as any),
    );
  });

  describe('checkBiometricAvailability', () => {
    it('should return available status for fingerprint biometrics', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await checkBiometricAvailability();

      expect(result).toEqual({
        isAvailable: true,
        biometricType: 'fingerprint',
        isEnrolled: true,
      });
      expect(mockRnBiometrics.isSensorAvailable).toHaveBeenCalled();
    });

    it('should return available status for face biometrics', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.FaceID,
        }),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await checkBiometricAvailability();

      expect(result).toEqual({
        isAvailable: true,
        biometricType: 'face',
        isEnrolled: true,
      });
    });

    it('should return available status for generic biometrics (Android)', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.Biometrics,
        }),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await checkBiometricAvailability();

      expect(result).toEqual({
        isAvailable: true,
        biometricType: 'fingerprint',
        isEnrolled: true,
      });
    });

    it('should return unavailable status when biometrics not available', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: false,
          biometryType: undefined,
        }),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await checkBiometricAvailability();

      expect(result).toEqual({
        isAvailable: false,
        biometricType: 'none',
        isEnrolled: false,
      });
    });

    it('should handle errors gracefully and return unavailable status', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockRejectedValue(new Error('Biometric error')),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await checkBiometricAvailability();

      expect(result).toEqual({
        isAvailable: false,
        biometricType: 'none',
        isEnrolled: false,
      });
    });
  });

  describe('authenticateWithBiometrics', () => {
    it('should successfully authenticate with biometrics', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockResolvedValue({success: true}),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await authenticateWithBiometrics('Test prompt');

      expect(result).toEqual({
        success: true,
        data: true,
      });
      expect(mockRnBiometrics.simplePrompt).toHaveBeenCalledWith({
        promptMessage: 'Test prompt',
      });
    });

    it('should use default prompt message when none provided', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockResolvedValue({success: true}),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      await authenticateWithBiometrics();

      expect(mockRnBiometrics.simplePrompt).toHaveBeenCalledWith({
        promptMessage: 'Authenticate to access your account',
      });
    });

    it('should fail when biometrics not available', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: false,
          biometryType: undefined,
        }),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await authenticateWithBiometrics();

      expect(result).toEqual({
        success: false,
        error: {
          code: 'BIOMETRIC_NOT_AVAILABLE',
          message: 'Biometric authentication is not available on this device',
        },
      });
    });

    it('should fail when biometrics not enrolled', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      // Mock checkBiometricAvailability to return not enrolled
      const mockRnBiometricsNotEnrolled = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: false,
          biometryType: undefined,
        }),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometricsNotEnrolled as any,
      );

      const result = await authenticateWithBiometrics();

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('BIOMETRIC_NOT_AVAILABLE');
    });

    it('should fail when biometric prompt returns failure', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockResolvedValue({success: false}),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await authenticateWithBiometrics();

      expect(result).toEqual({
        success: false,
        error: {
          code: 'BIOMETRIC_FAILED',
          message: 'Biometric authentication failed',
        },
      });
    });

    it('should handle user cancellation', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockRejectedValue(new Error('User canceled authentication')),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await authenticateWithBiometrics();

      expect(result).toEqual({
        success: false,
        error: {
          code: 'BIOMETRIC_CANCELED',
          message: 'User canceled authentication',
        },
      });
    });

    it('should handle unknown errors during authentication', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockRejectedValue('Unknown error'),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await authenticateWithBiometrics();

      expect(result).toEqual({
        success: false,
        error: {
          code: 'BIOMETRIC_CANCELED',
          message: 'Biometric authentication was canceled',
        },
      });
    });
  });

  describe('enableBiometricLogin', () => {
    it('should successfully enable biometric login', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockResolvedValue({success: true}),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await enableBiometricLogin();

      expect(result).toEqual({
        success: true,
      });
      expect(mockRnBiometrics.simplePrompt).toHaveBeenCalledWith({
        promptMessage: 'Verify your identity to enable biometric login',
      });
    });

    it('should fail when biometrics not available', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: false,
          biometryType: undefined,
        }),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await enableBiometricLogin();

      expect(result).toEqual({
        success: false,
        error: {
          code: 'BIOMETRIC_NOT_AVAILABLE',
          message: 'Biometric authentication is not available or not set up on this device',
        },
      });
    });

    it('should fail when biometric verification fails', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockResolvedValue({success: false}),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await enableBiometricLogin();

      expect(result).toEqual({
        success: false,
        error: {
          code: 'BIOMETRIC_FAILED',
          message: 'Biometric authentication failed',
        },
      });
    });
  });

  describe('disableBiometricLogin', () => {
    it('should successfully disable biometric login', async () => {
      // First enable biometrics
      setStoredCredentials('test@email.com', 'refresh-token', 'user-123');

      expect(isBiometricLoginEnabled()).toBe(true);

      const result = await disableBiometricLogin();

      expect(result).toEqual({
        success: true,
      });
      expect(isBiometricLoginEnabled()).toBe(false);
      expect(getStoredEmail()).toBeNull();
    });

    it('should clear stored credentials when disabling', async () => {
      setStoredCredentials('test@email.com', 'refresh-token', 'user-123');

      expect(getStoredEmail()).toBe('test@email.com');

      await disableBiometricLogin();

      expect(getStoredEmail()).toBeNull();
    });
  });

  describe('loginWithBiometrics', () => {
    beforeEach(() => {
      // Setup successful login first to have credentials
      mockApi.post.mockResolvedValueOnce({
        success: true,
        data: mockLoginResponse,
      });

      return login({email: 'test@email.com', password: 'password'}, true);
    });

    it('should successfully login with biometrics', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockResolvedValue({success: true}),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      mockApi.post.mockResolvedValueOnce({
        success: true,
        data: mockRefreshResponse,
      });

      mockFetchCurrentUser.mockResolvedValueOnce({
        success: true,
        data: mockTeacher,
      });

      const result = await loginWithBiometrics();

      expect(result.success).toBe(true);
      expect(result.data).toEqual({
        accessToken: mockRefreshResponse.accessToken,
        refreshToken: mockRefreshResponse.refreshToken,
        expiresIn: mockRefreshResponse.expiresIn,
        user: mockTeacher,
      });
      expect(mockSetSessionToken).toHaveBeenCalledWith(mockRefreshResponse.accessToken);
    });

    it('should fail when biometric login is not enabled', async () => {
      clearStoredCredentials();

      const result = await loginWithBiometrics();

      expect(result).toEqual({
        success: false,
        error: {
          code: 'BIOMETRIC_NOT_AVAILABLE',
          message: 'Biometric login is not enabled. Please login with your password first.',
        },
      });
    });

    it('should fail when biometric authentication fails', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockResolvedValue({success: false}),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const result = await loginWithBiometrics();

      expect(result).toEqual({
        success: false,
        error: {
          code: 'BIOMETRIC_FAILED',
          message: 'Biometric authentication failed',
        },
      });
    });

    it('should update refresh token after successful login', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockResolvedValue({success: true}),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      const newRefreshToken = 'updated-refresh-token';
      mockApi.post.mockResolvedValueOnce({
        success: true,
        data: {
          ...mockRefreshResponse,
          refreshToken: newRefreshToken,
        },
      });

      mockFetchCurrentUser.mockResolvedValueOnce({
        success: true,
        data: mockTeacher,
      });

      const result = await loginWithBiometrics();

      expect(result.success).toBe(true);
      expect(result.data?.refreshToken).toBe(newRefreshToken);
    });

    it('should handle expired refresh token', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockResolvedValue({success: true}),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      mockApi.post.mockResolvedValueOnce({
        success: false,
        error: {code: 'TOKEN_EXPIRED', message: 'Refresh token expired'},
      });

      const result = await loginWithBiometrics();

      expect(result).toEqual({
        success: false,
        error: {
          code: 'SESSION_EXPIRED',
          message: 'Your session has expired. Please login with your password.',
        },
      });
      expect(isBiometricLoginEnabled()).toBe(false);
    });

    it('should handle network errors during token refresh', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockResolvedValue({success: true}),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      mockApi.post.mockRejectedValueOnce(new Error('Network error'));

      const result = await loginWithBiometrics();

      expect(result).toEqual({
        success: false,
        error: {
          code: 'NETWORK_ERROR',
          message: 'Network error',
        },
      });
    });

    it('should handle user fetch failure after token refresh', async () => {
      const mockRnBiometrics = {
        isSensorAvailable: jest.fn().mockResolvedValue({
          available: true,
          biometryType: ReactNativeBiometrics.TouchID,
        }),
        simplePrompt: jest.fn().mockResolvedValue({success: true}),
      };

      (ReactNativeBiometrics as jest.MockedClass<typeof ReactNativeBiometrics>).mockImplementation(
        () => mockRnBiometrics as any,
      );

      mockApi.post.mockResolvedValueOnce({
        success: true,
        data: mockRefreshResponse,
      });

      mockFetchCurrentUser.mockResolvedValueOnce({
        success: false,
        error: {code: 'USER_NOT_FOUND', message: 'User not found'},
      });

      const result = await loginWithBiometrics();

      expect(result).toEqual({
        success: false,
        error: {
          code: 'UNKNOWN_ERROR',
          message: 'Failed to fetch user profile after token refresh.',
        },
      });
    });
  });

  describe('isBiometricLoginEnabled', () => {
    it('should return false when no credentials stored', () => {
      clearStoredCredentials();

      expect(isBiometricLoginEnabled()).toBe(false);
    });

    it('should return true when credentials are stored', () => {
      setStoredCredentials('test@email.com', 'refresh-token', 'user-123');

      expect(isBiometricLoginEnabled()).toBe(true);
    });

    it('should return false after clearing credentials', () => {
      setStoredCredentials('test@email.com', 'refresh-token', 'user-123');
      clearStoredCredentials();

      expect(isBiometricLoginEnabled()).toBe(false);
    });
  });

  describe('getStoredEmail', () => {
    it('should return null when no credentials stored', () => {
      clearStoredCredentials();

      expect(getStoredEmail()).toBeNull();
    });

    it('should return stored email when credentials are stored', () => {
      const email = 'test@email.com';
      setStoredCredentials(email, 'refresh-token', 'user-123');

      expect(getStoredEmail()).toBe(email);
    });

    it('should return null after clearing credentials', () => {
      setStoredCredentials('test@email.com', 'refresh-token', 'user-123');
      clearStoredCredentials();

      expect(getStoredEmail()).toBeNull();
    });
  });

  describe('setStoredCredentials', () => {
    it('should store credentials and enable biometric login', () => {
      setStoredCredentials('test@email.com', 'refresh-token', 'user-123');

      expect(isBiometricLoginEnabled()).toBe(true);
      expect(getStoredEmail()).toBe('test@email.com');
    });

    it('should update credentials when called multiple times', () => {
      setStoredCredentials('first@email.com', 'token1', 'user1');
      expect(getStoredEmail()).toBe('first@email.com');

      setStoredCredentials('second@email.com', 'token2', 'user2');
      expect(getStoredEmail()).toBe('second@email.com');
    });
  });

  describe('clearStoredCredentials', () => {
    it('should clear credentials and disable biometric login', () => {
      setStoredCredentials('test@email.com', 'refresh-token', 'user-123');

      clearStoredCredentials();

      expect(isBiometricLoginEnabled()).toBe(false);
      expect(getStoredEmail()).toBeNull();
    });

    it('should be idempotent', () => {
      setStoredCredentials('test@email.com', 'refresh-token', 'user-123');

      clearStoredCredentials();
      clearStoredCredentials();

      expect(isBiometricLoginEnabled()).toBe(false);
      expect(getStoredEmail()).toBeNull();
    });
  });

  describe('login with rememberMe', () => {
    it('should enable biometric login when rememberMe is true', async () => {
      mockApi.post.mockResolvedValueOnce({
        success: true,
        data: mockLoginResponse,
      });

      await login({email: 'test@email.com', password: 'password'}, true);

      expect(isBiometricLoginEnabled()).toBe(true);
      expect(getStoredEmail()).toBe('test@email.com');
    });

    it('should not enable biometric login when rememberMe is false', async () => {
      mockApi.post.mockResolvedValueOnce({
        success: true,
        data: mockLoginResponse,
      });

      await login({email: 'test@email.com', password: 'password'}, false);

      expect(isBiometricLoginEnabled()).toBe(false);
      expect(getStoredEmail()).toBeNull();
    });
  });

  describe('logout', () => {
    it('should not clear biometric credentials on logout', async () => {
      // Login with rememberMe
      mockApi.post.mockResolvedValueOnce({
        success: true,
        data: mockLoginResponse,
      });

      await login({email: 'test@email.com', password: 'password'}, true);

      expect(isBiometricLoginEnabled()).toBe(true);

      // Logout
      mockApi.post.mockResolvedValueOnce({success: true});
      await logout();

      // Biometric credentials should still be available
      expect(isBiometricLoginEnabled()).toBe(true);
      expect(getStoredEmail()).toBe('test@email.com');
    });
  });
});
