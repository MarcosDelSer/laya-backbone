/**
 * LAYA Teacher App - useAuth Hook Unit Tests
 *
 * Unit tests for the useAuth hook, testing authentication state management,
 * login/logout flows, biometric authentication, and error handling.
 */

import {renderHook, act, waitFor} from '@testing-library/react-native';
import {useAuth} from '../../src/hooks/useAuth';
import type {Teacher, LoginCredentials, LoginResponse} from '../../src/types';

// Mock the authService module
jest.mock('../../src/services/authService', () => ({
  isAuthenticated: jest.fn(),
  getCurrentUser: jest.fn(),
  login: jest.fn(),
  logout: jest.fn(),
  loginWithBiometrics: jest.fn(),
  checkBiometricAvailability: jest.fn(),
  isBiometricLoginEnabled: jest.fn(),
  getStoredEmail: jest.fn(),
  enableBiometricLogin: jest.fn(),
  disableBiometricLogin: jest.fn(),
  loginWithMockCredentials: jest.fn(),
}));

// Import mocked functions after mocking
import {
  isAuthenticated as checkAuth,
  getCurrentUser,
  login as authLogin,
  logout as authLogout,
  loginWithBiometrics as authLoginBiometrics,
  checkBiometricAvailability,
  isBiometricLoginEnabled,
  getStoredEmail,
  enableBiometricLogin as authEnableBiometrics,
  disableBiometricLogin as authDisableBiometrics,
  loginWithMockCredentials,
} from '../../src/services/authService';

// Type assertions for mocked functions
const mockCheckAuth = checkAuth as jest.MockedFunction<typeof checkAuth>;
const mockGetCurrentUser = getCurrentUser as jest.MockedFunction<typeof getCurrentUser>;
const mockAuthLogin = authLogin as jest.MockedFunction<typeof authLogin>;
const mockAuthLogout = authLogout as jest.MockedFunction<typeof authLogout>;
const mockAuthLoginBiometrics = authLoginBiometrics as jest.MockedFunction<typeof authLoginBiometrics>;
const mockCheckBiometricAvailability = checkBiometricAvailability as jest.MockedFunction<typeof checkBiometricAvailability>;
const mockIsBiometricLoginEnabled = isBiometricLoginEnabled as jest.MockedFunction<typeof isBiometricLoginEnabled>;
const mockGetStoredEmail = getStoredEmail as jest.MockedFunction<typeof getStoredEmail>;
const mockAuthEnableBiometrics = authEnableBiometrics as jest.MockedFunction<typeof authEnableBiometrics>;
const mockAuthDisableBiometrics = authDisableBiometrics as jest.MockedFunction<typeof authDisableBiometrics>;
const mockLoginWithMockCredentials = loginWithMockCredentials as jest.MockedFunction<typeof loginWithMockCredentials>;

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

const mockBiometricStatus = {
  isAvailable: true,
  biometricType: 'fingerprint' as const,
  isEnrolled: true,
};

describe('useAuth hook', () => {
  beforeEach(() => {
    jest.clearAllMocks();

    // Default mock implementations
    mockCheckAuth.mockReturnValue(false);
    mockGetCurrentUser.mockReturnValue(null);
    mockCheckBiometricAvailability.mockResolvedValue(mockBiometricStatus);
    mockIsBiometricLoginEnabled.mockReturnValue(false);
    mockGetStoredEmail.mockReturnValue(null);
  });

  describe('initial state', () => {
    it('should start with isLoading true', async () => {
      const {result} = renderHook(() => useAuth());

      // Initial state should have isLoading true
      expect(result.current.isLoading).toBe(true);

      // Wait for initialization to complete
      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });
    });

    it('should set isAuthenticated to false when not authenticated', async () => {
      mockCheckAuth.mockReturnValue(false);

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.user).toBeNull();
    });

    it('should set isAuthenticated to true when already authenticated', async () => {
      mockCheckAuth.mockReturnValue(true);
      mockGetCurrentUser.mockReturnValue(mockTeacher);

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.isAuthenticated).toBe(true);
      expect(result.current.user).toEqual(mockTeacher);
    });

    it('should initialize biometric status on mount', async () => {
      mockIsBiometricLoginEnabled.mockReturnValue(true);
      mockGetStoredEmail.mockReturnValue('stored@email.com');

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(mockCheckBiometricAvailability).toHaveBeenCalled();
      expect(result.current.biometricStatus).toEqual(mockBiometricStatus);
      expect(result.current.biometricEnabled).toBe(true);
      expect(result.current.storedEmail).toBe('stored@email.com');
    });

    it('should have null error initially', async () => {
      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.error).toBeNull();
    });

    it('should have isLoggingIn false initially', async () => {
      const {result} = renderHook(() => useAuth());

      expect(result.current.isLoggingIn).toBe(false);

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.isLoggingIn).toBe(false);
    });
  });

  describe('login', () => {
    it('should set isLoggingIn to true during login', async () => {
      mockAuthLogin.mockImplementation(
        () => new Promise(resolve => setTimeout(() => resolve({success: true, data: mockLoginResponse}), 100)),
      );

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      act(() => {
        result.current.login(mockLoginCredentials, false);
      });

      expect(result.current.isLoggingIn).toBe(true);

      await waitFor(() => {
        expect(result.current.isLoggingIn).toBe(false);
      });
    });

    it('should successfully login and update state', async () => {
      mockAuthLogin.mockResolvedValue({success: true, data: mockLoginResponse});

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let loginResult: boolean | undefined;
      await act(async () => {
        loginResult = await result.current.login(mockLoginCredentials, false);
      });

      expect(loginResult).toBe(true);
      expect(result.current.isAuthenticated).toBe(true);
      expect(result.current.user).toEqual(mockTeacher);
      expect(result.current.error).toBeNull();
    });

    it('should call authLogin with credentials and rememberMe', async () => {
      mockAuthLogin.mockResolvedValue({success: true, data: mockLoginResponse});

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      await act(async () => {
        await result.current.login(mockLoginCredentials, true);
      });

      expect(mockAuthLogin).toHaveBeenCalledWith(mockLoginCredentials, true);
    });

    it('should enable biometric when rememberMe is true on successful login', async () => {
      mockAuthLogin.mockResolvedValue({success: true, data: mockLoginResponse});

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      await act(async () => {
        await result.current.login(mockLoginCredentials, true);
      });

      expect(result.current.biometricEnabled).toBe(true);
      expect(result.current.storedEmail).toBe(mockLoginCredentials.email);
    });

    it('should set error on failed login', async () => {
      const errorResponse = {
        success: false,
        error: {code: 'INVALID_CREDENTIALS' as const, message: 'Invalid email or password'},
      };
      mockAuthLogin.mockResolvedValue(errorResponse);

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let loginResult: boolean | undefined;
      await act(async () => {
        loginResult = await result.current.login(mockLoginCredentials, false);
      });

      expect(loginResult).toBe(false);
      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.error).toEqual(errorResponse.error);
    });

    it('should fall back to mock login on network error', async () => {
      mockAuthLogin.mockResolvedValue({
        success: false,
        error: {code: 'NETWORK_ERROR' as const, message: 'Network error'},
      });
      mockLoginWithMockCredentials.mockResolvedValue({
        success: true,
        data: mockLoginResponse,
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let loginResult: boolean | undefined;
      await act(async () => {
        loginResult = await result.current.login(mockLoginCredentials, false);
      });

      expect(mockLoginWithMockCredentials).toHaveBeenCalledWith(
        mockLoginCredentials.email,
        mockLoginCredentials.password,
      );
      expect(loginResult).toBe(true);
      expect(result.current.isAuthenticated).toBe(true);
    });

    it('should handle thrown error during login', async () => {
      mockAuthLogin.mockRejectedValue(new Error('Unexpected error'));

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let loginResult: boolean | undefined;
      await act(async () => {
        loginResult = await result.current.login(mockLoginCredentials, false);
      });

      expect(loginResult).toBe(false);
      expect(result.current.error).toEqual({
        code: 'UNKNOWN_ERROR',
        message: 'Unexpected error',
      });
    });

    it('should clear error before attempting login', async () => {
      // First fail to set an error
      mockAuthLogin.mockResolvedValueOnce({
        success: false,
        error: {code: 'INVALID_CREDENTIALS' as const, message: 'Invalid'},
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      await act(async () => {
        await result.current.login(mockLoginCredentials, false);
      });

      expect(result.current.error).not.toBeNull();

      // Second login attempt should clear the error first
      mockAuthLogin.mockResolvedValueOnce({success: true, data: mockLoginResponse});

      await act(async () => {
        await result.current.login(mockLoginCredentials, false);
      });

      expect(result.current.error).toBeNull();
    });

    it('should set default error when response has no error object', async () => {
      mockAuthLogin.mockResolvedValue({success: false});

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      await act(async () => {
        await result.current.login(mockLoginCredentials, false);
      });

      expect(result.current.error).toEqual({
        code: 'UNKNOWN_ERROR',
        message: 'Login failed',
      });
    });
  });

  describe('loginWithBiometrics', () => {
    it('should fail when biometric is not enabled', async () => {
      mockIsBiometricLoginEnabled.mockReturnValue(false);

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let loginResult: boolean | undefined;
      await act(async () => {
        loginResult = await result.current.loginWithBiometrics();
      });

      expect(loginResult).toBe(false);
      expect(result.current.error).toEqual({
        code: 'BIOMETRIC_NOT_AVAILABLE',
        message: 'Biometric login is not enabled',
      });
    });

    it('should successfully login with biometrics', async () => {
      mockIsBiometricLoginEnabled.mockReturnValue(true);
      mockGetStoredEmail.mockReturnValue('stored@email.com');
      mockAuthLoginBiometrics.mockResolvedValue({
        success: true,
        data: mockLoginResponse,
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
        expect(result.current.biometricEnabled).toBe(true);
      });

      let loginResult: boolean | undefined;
      await act(async () => {
        loginResult = await result.current.loginWithBiometrics();
      });

      expect(loginResult).toBe(true);
      expect(result.current.isAuthenticated).toBe(true);
      expect(result.current.user).toEqual(mockTeacher);
    });

    it('should set isLoggingIn during biometric login', async () => {
      mockIsBiometricLoginEnabled.mockReturnValue(true);
      mockAuthLoginBiometrics.mockImplementation(
        () => new Promise(resolve => setTimeout(() => resolve({success: true, data: mockLoginResponse}), 100)),
      );

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
        expect(result.current.biometricEnabled).toBe(true);
      });

      act(() => {
        result.current.loginWithBiometrics();
      });

      expect(result.current.isLoggingIn).toBe(true);

      await waitFor(() => {
        expect(result.current.isLoggingIn).toBe(false);
      });
    });

    it('should fall back to mock login when biometric succeeds but returns no data', async () => {
      mockIsBiometricLoginEnabled.mockReturnValue(true);
      mockGetStoredEmail.mockReturnValue('stored@email.com');
      mockAuthLoginBiometrics.mockResolvedValue({success: false});
      mockLoginWithMockCredentials.mockResolvedValue({
        success: true,
        data: mockLoginResponse,
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
        expect(result.current.biometricEnabled).toBe(true);
      });

      let loginResult: boolean | undefined;
      await act(async () => {
        loginResult = await result.current.loginWithBiometrics();
      });

      expect(mockLoginWithMockCredentials).toHaveBeenCalledWith('stored@email.com', 'biometric');
      expect(loginResult).toBe(true);
      expect(result.current.isAuthenticated).toBe(true);
    });

    it('should set error on biometric login failure', async () => {
      mockIsBiometricLoginEnabled.mockReturnValue(true);
      mockGetStoredEmail.mockReturnValue(null); // No stored email for fallback
      mockAuthLoginBiometrics.mockResolvedValue({
        success: false,
        error: {code: 'BIOMETRIC_FAILED' as const, message: 'Biometric verification failed'},
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
        expect(result.current.biometricEnabled).toBe(true);
      });

      let loginResult: boolean | undefined;
      await act(async () => {
        loginResult = await result.current.loginWithBiometrics();
      });

      expect(loginResult).toBe(false);
      expect(result.current.error).toEqual({
        code: 'BIOMETRIC_FAILED',
        message: 'Biometric verification failed',
      });
    });

    it('should handle thrown error during biometric login', async () => {
      mockIsBiometricLoginEnabled.mockReturnValue(true);
      mockAuthLoginBiometrics.mockRejectedValue(new Error('Biometric error'));

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
        expect(result.current.biometricEnabled).toBe(true);
      });

      let loginResult: boolean | undefined;
      await act(async () => {
        loginResult = await result.current.loginWithBiometrics();
      });

      expect(loginResult).toBe(false);
      expect(result.current.error).toEqual({
        code: 'UNKNOWN_ERROR',
        message: 'Biometric error',
      });
    });
  });

  describe('logout', () => {
    it('should clear authentication state on logout', async () => {
      // First authenticate
      mockCheckAuth.mockReturnValue(true);
      mockGetCurrentUser.mockReturnValue(mockTeacher);
      mockAuthLogout.mockResolvedValue({success: true});

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
        expect(result.current.isAuthenticated).toBe(true);
      });

      await act(async () => {
        await result.current.logout();
      });

      expect(mockAuthLogout).toHaveBeenCalled();
      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.user).toBeNull();
      expect(result.current.error).toBeNull();
    });

    it('should clear any existing error on logout', async () => {
      mockAuthLogin.mockResolvedValue({
        success: false,
        error: {code: 'INVALID_CREDENTIALS' as const, message: 'Invalid'},
      });
      mockAuthLogout.mockResolvedValue({success: true});

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      // Set an error
      await act(async () => {
        await result.current.login(mockLoginCredentials, false);
      });

      expect(result.current.error).not.toBeNull();

      // Logout should clear error
      await act(async () => {
        await result.current.logout();
      });

      expect(result.current.error).toBeNull();
    });
  });

  describe('enableBiometricLogin', () => {
    it('should enable biometric login on success', async () => {
      mockAuthEnableBiometrics.mockResolvedValue({success: true});
      mockGetStoredEmail.mockReturnValue('test@email.com');

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let enableResult: boolean | undefined;
      await act(async () => {
        enableResult = await result.current.enableBiometricLogin();
      });

      expect(enableResult).toBe(true);
      expect(result.current.biometricEnabled).toBe(true);
      expect(result.current.storedEmail).toBe('test@email.com');
    });

    it('should set error on enable failure', async () => {
      mockAuthEnableBiometrics.mockResolvedValue({
        success: false,
        error: {code: 'BIOMETRIC_NOT_AVAILABLE' as const, message: 'Biometrics not available'},
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let enableResult: boolean | undefined;
      await act(async () => {
        enableResult = await result.current.enableBiometricLogin();
      });

      expect(enableResult).toBe(false);
      expect(result.current.error).toEqual({
        code: 'BIOMETRIC_NOT_AVAILABLE',
        message: 'Biometrics not available',
      });
    });

    it('should clear error before attempting to enable', async () => {
      mockAuthEnableBiometrics.mockResolvedValueOnce({
        success: false,
        error: {code: 'BIOMETRIC_NOT_AVAILABLE' as const, message: 'Error'},
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      await act(async () => {
        await result.current.enableBiometricLogin();
      });

      expect(result.current.error).not.toBeNull();

      mockAuthEnableBiometrics.mockResolvedValueOnce({success: true});
      mockGetStoredEmail.mockReturnValue('test@email.com');

      await act(async () => {
        await result.current.enableBiometricLogin();
      });

      expect(result.current.error).toBeNull();
    });
  });

  describe('disableBiometricLogin', () => {
    it('should disable biometric login', async () => {
      mockIsBiometricLoginEnabled.mockReturnValue(true);
      mockGetStoredEmail.mockReturnValue('test@email.com');
      mockAuthDisableBiometrics.mockResolvedValue({success: true});

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
        expect(result.current.biometricEnabled).toBe(true);
      });

      await act(async () => {
        await result.current.disableBiometricLogin();
      });

      expect(mockAuthDisableBiometrics).toHaveBeenCalled();
      expect(result.current.biometricEnabled).toBe(false);
      expect(result.current.storedEmail).toBeNull();
    });
  });

  describe('clearError', () => {
    it('should clear the error state', async () => {
      mockAuthLogin.mockResolvedValue({
        success: false,
        error: {code: 'INVALID_CREDENTIALS' as const, message: 'Invalid'},
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      // Set an error
      await act(async () => {
        await result.current.login(mockLoginCredentials, false);
      });

      expect(result.current.error).not.toBeNull();

      // Clear the error
      act(() => {
        result.current.clearError();
      });

      expect(result.current.error).toBeNull();
    });
  });

  describe('refreshBiometricStatus', () => {
    it('should update biometric status', async () => {
      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      // Change mock return values
      const newStatus = {
        isAvailable: false,
        biometricType: 'none' as const,
        isEnrolled: false,
      };
      mockCheckBiometricAvailability.mockResolvedValue(newStatus);
      mockIsBiometricLoginEnabled.mockReturnValue(false);
      mockGetStoredEmail.mockReturnValue(null);

      await act(async () => {
        await result.current.refreshBiometricStatus();
      });

      expect(result.current.biometricStatus).toEqual(newStatus);
      expect(result.current.biometricEnabled).toBe(false);
      expect(result.current.storedEmail).toBeNull();
    });
  });

  describe('return value structure', () => {
    it('should return all expected state properties', async () => {
      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      // State properties
      expect(result.current).toHaveProperty('isAuthenticated');
      expect(result.current).toHaveProperty('isLoading');
      expect(result.current).toHaveProperty('isLoggingIn');
      expect(result.current).toHaveProperty('user');
      expect(result.current).toHaveProperty('error');
      expect(result.current).toHaveProperty('biometricStatus');
      expect(result.current).toHaveProperty('biometricEnabled');
      expect(result.current).toHaveProperty('storedEmail');
    });

    it('should return all expected action functions', async () => {
      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      // Action functions
      expect(typeof result.current.login).toBe('function');
      expect(typeof result.current.loginWithBiometrics).toBe('function');
      expect(typeof result.current.logout).toBe('function');
      expect(typeof result.current.enableBiometricLogin).toBe('function');
      expect(typeof result.current.disableBiometricLogin).toBe('function');
      expect(typeof result.current.clearError).toBe('function');
      expect(typeof result.current.refreshBiometricStatus).toBe('function');
    });
  });
});
