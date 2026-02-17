/**
 * @format
 * LAYA Parent App - useAuth Hook Tests
 *
 * Tests for the authentication hook covering:
 * - Login with credentials
 * - Logout
 * - Biometric authentication
 * - Error states
 * - Biometric enable/disable
 */

import {renderHook, act, waitFor} from '@testing-library/react-native';
import {useAuth} from '../../src/hooks/useAuth';
import type {Parent} from '../../src/types';
import type {
  LoginResponse,
  BiometricStatus,
  AuthError,
} from '../../src/services/authService';

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

// Mock the AuthContext module
jest.mock('../../src/contexts/AuthContext', () => ({
  useAuthContext: jest.fn(),
}));

// Import mocked functions
import * as authService from '../../src/services/authService';
import {useAuthContext} from '../../src/contexts/AuthContext';

const mockAuthService = authService as jest.Mocked<typeof authService>;
const mockUseAuthContext = useAuthContext as jest.MockedFunction<
  typeof useAuthContext
>;

// Test data
const mockUser: Parent = {
  id: 'parent-1',
  firstName: 'Sarah',
  lastName: 'Johnson',
  email: 'sarah.johnson@example.com',
  phone: '+1 (555) 123-4567',
  childIds: ['child-1', 'child-2'],
};

const mockLoginResponse: LoginResponse = {
  token: 'mock-jwt-token',
  refreshToken: 'mock-refresh-token',
  expiresAt: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
  user: mockUser,
};

const mockBiometricStatus: BiometricStatus = {
  isAvailable: true,
  biometricType: 'fingerprint',
  isEnrolled: true,
};

// Mock functions for AuthContext actions
const mockAuthContextLogin = jest.fn();
const mockAuthContextLogout = jest.fn();
const mockAuthContextRefresh = jest.fn();
const mockAuthContextClearError = jest.fn();

describe('useAuth', () => {
  beforeEach(() => {
    // Reset all mocks before each test
    jest.clearAllMocks();

    // Default mock implementations for authService
    mockAuthService.isAuthenticated.mockReturnValue(false);
    mockAuthService.getCurrentUser.mockReturnValue(null);
    mockAuthService.checkBiometricAvailability.mockResolvedValue(
      mockBiometricStatus,
    );
    mockAuthService.isBiometricLoginEnabled.mockReturnValue(false);
    mockAuthService.getStoredEmail.mockReturnValue(null);

    // Default mock for AuthContext (unauthenticated state)
    mockAuthContextLogin.mockResolvedValue(false);
    mockAuthContextLogout.mockResolvedValue(undefined);
    mockAuthContextRefresh.mockResolvedValue(true);
    mockAuthContextClearError.mockReturnValue(undefined);

    mockUseAuthContext.mockReturnValue({
      state: {
        isAuthenticated: false,
        user: null,
        token: null,
        loading: false,
      },
      actions: {
        login: mockAuthContextLogin,
        logout: mockAuthContextLogout,
        refresh: mockAuthContextRefresh,
        clearError: mockAuthContextClearError,
      },
    });
  });

  describe('Initial State', () => {
    it('should initialize with loading state', async () => {
      // Mock context to return loading state initially
      mockUseAuthContext.mockReturnValueOnce({
        state: {
          isAuthenticated: false,
          user: null,
          token: null,
          loading: true,
        },
        actions: {
          login: mockAuthContextLogin,
          logout: mockAuthContextLogout,
          refresh: mockAuthContextRefresh,
          clearError: mockAuthContextClearError,
        },
      });

      const {result} = renderHook(() => useAuth());

      // Initial state should show loading
      expect(result.current.isLoading).toBe(true);
      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.user).toBeNull();

      // Wait for initialization to complete
      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });
    });

    it('should restore authenticated session on mount', async () => {
      // Mock context to return authenticated state
      mockUseAuthContext.mockReturnValue({
        state: {
          isAuthenticated: true,
          user: mockUser,
          token: 'mock-token',
          loading: false,
        },
        actions: {
          login: mockAuthContextLogin,
          logout: mockAuthContextLogout,
          refresh: mockAuthContextRefresh,
          clearError: mockAuthContextClearError,
        },
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.isAuthenticated).toBe(true);
      expect(result.current.user).toEqual(mockUser);
    });

    it('should check biometric availability on mount', async () => {
      mockAuthService.isBiometricLoginEnabled.mockReturnValue(true);
      mockAuthService.getStoredEmail.mockReturnValue('test@example.com');

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(mockAuthService.checkBiometricAvailability).toHaveBeenCalled();
      expect(result.current.biometricStatus).toEqual(mockBiometricStatus);
      expect(result.current.biometricEnabled).toBe(true);
      expect(result.current.storedEmail).toBe('test@example.com');
    });
  });

  describe('Login', () => {
    it('should login successfully with valid credentials', async () => {
      // Mock context login to succeed and update context state
      mockAuthContextLogin.mockImplementation(async () => {
        // Update the context state to authenticated after login
        mockUseAuthContext.mockReturnValue({
          state: {
            isAuthenticated: true,
            user: mockUser,
            token: 'mock-token',
            loading: false,
          },
          actions: {
            login: mockAuthContextLogin,
            logout: mockAuthContextLogout,
            refresh: mockAuthContextRefresh,
            clearError: mockAuthContextClearError,
          },
        });
        return true;
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let success: boolean;
      await act(async () => {
        success = await result.current.login({
          email: 'test@example.com',
          password: 'password123',
        });
      });

      expect(success!).toBe(true);
      expect(result.current.isAuthenticated).toBe(true);
      expect(result.current.user).toEqual(mockUser);
      expect(result.current.error).toBeNull();
    });

    it('should set isLoggingIn during login', async () => {
      let resolveLogin: (value: boolean) => void;
      mockAuthContextLogin.mockReturnValue(
        new Promise(resolve => {
          resolveLogin = resolve;
        }),
      );

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      act(() => {
        result.current.login({
          email: 'test@example.com',
          password: 'password123',
        });
      });

      expect(result.current.isLoggingIn).toBe(true);

      await act(async () => {
        resolveLogin!(true);
      });

      expect(result.current.isLoggingIn).toBe(false);
    });

    it('should enable biometric login when rememberMe is true', async () => {
      // Mock context login to succeed
      mockAuthContextLogin.mockResolvedValue(true);

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      await act(async () => {
        await result.current.login({
          email: 'test@example.com',
          password: 'password123',
          rememberMe: true,
        });
      });

      expect(result.current.biometricEnabled).toBe(true);
      expect(result.current.storedEmail).toBe('test@example.com');
    });

    it('should call authContext login with credentials', async () => {
      // Mock context login to succeed
      mockAuthContextLogin.mockResolvedValue(true);

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      await act(async () => {
        await result.current.login({
          email: 'test@example.com',
          password: 'password123',
        });
      });

      // Verify authContext login was called with correct credentials
      expect(mockAuthContextLogin).toHaveBeenCalledWith({
        email: 'test@example.com',
        password: 'password123',
      });
    });

    it('should handle login failure with error', async () => {
      // Mock context login to fail
      mockAuthContextLogin.mockResolvedValue(false);

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let success: boolean;
      await act(async () => {
        success = await result.current.login({
          email: 'test@example.com',
          password: 'wrongpassword',
        });
      });

      expect(success!).toBe(false);
      expect(result.current.isAuthenticated).toBe(false);
      // useAuth sets its own error when context login fails
      expect(result.current.error).toEqual({
        code: 'LOGIN_FAILED',
        message: 'Login failed. Please check your credentials.',
      });
    });

    it('should handle login exception', async () => {
      // Mock context login to throw an exception
      mockAuthContextLogin.mockRejectedValue(new Error('Unexpected error'));

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let success: boolean;
      await act(async () => {
        success = await result.current.login({
          email: 'test@example.com',
          password: 'password123',
        });
      });

      expect(success!).toBe(false);
      expect(result.current.error).toEqual({
        code: 'UNKNOWN_ERROR',
        message: 'Unexpected error',
      });
    });
  });

  describe('Logout', () => {
    it('should logout successfully', async () => {
      // Start as authenticated
      mockUseAuthContext.mockReturnValue({
        state: {
          isAuthenticated: true,
          user: mockUser,
          token: 'mock-token',
          loading: false,
        },
        actions: {
          login: mockAuthContextLogin,
          logout: mockAuthContextLogout,
          refresh: mockAuthContextRefresh,
          clearError: mockAuthContextClearError,
        },
      });

      // Mock logout to succeed and update context state
      mockAuthContextLogout.mockImplementation(async () => {
        // Update the context state to unauthenticated after logout
        mockUseAuthContext.mockReturnValue({
          state: {
            isAuthenticated: false,
            user: null,
            token: null,
            loading: false,
          },
          actions: {
            login: mockAuthContextLogin,
            logout: mockAuthContextLogout,
            refresh: mockAuthContextRefresh,
            clearError: mockAuthContextClearError,
          },
        });
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isAuthenticated).toBe(true);
      });

      await act(async () => {
        await result.current.logout();
      });

      expect(mockAuthContextLogout).toHaveBeenCalled();
      expect(result.current.isAuthenticated).toBe(false);
      expect(result.current.user).toBeNull();
      expect(result.current.error).toBeNull();
    });
  });

  describe('Biometric Authentication', () => {
    it('should fail when biometric login is not enabled', async () => {
      mockAuthService.isBiometricLoginEnabled.mockReturnValue(false);

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let success: boolean;
      await act(async () => {
        success = await result.current.loginWithBiometrics();
      });

      expect(success!).toBe(false);
      expect(result.current.error).toEqual({
        code: 'BIOMETRIC_NOT_AVAILABLE',
        message: 'Biometric login is not enabled',
      });
    });

    it('should login successfully with biometrics', async () => {
      mockAuthService.isBiometricLoginEnabled.mockReturnValue(true);
      mockAuthService.getStoredEmail.mockReturnValue('test@example.com');
      mockAuthService.loginWithBiometrics.mockResolvedValue({
        success: true,
        data: mockLoginResponse,
      });

      // Mock context login to succeed and update context state
      mockAuthContextLogin.mockImplementation(async () => {
        mockUseAuthContext.mockReturnValue({
          state: {
            isAuthenticated: true,
            user: mockUser,
            token: 'mock-token',
            loading: false,
          },
          actions: {
            login: mockAuthContextLogin,
            logout: mockAuthContextLogout,
            refresh: mockAuthContextRefresh,
            clearError: mockAuthContextClearError,
          },
        });
        return true;
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.biometricEnabled).toBe(true);
      });

      let success: boolean;
      await act(async () => {
        success = await result.current.loginWithBiometrics();
      });

      expect(success!).toBe(true);
      expect(result.current.isAuthenticated).toBe(true);
      expect(result.current.user).toEqual(mockUser);
    });

    it('should fallback to context login when biometric API fails', async () => {
      mockAuthService.isBiometricLoginEnabled.mockReturnValue(true);
      mockAuthService.getStoredEmail.mockReturnValue('test@example.com');
      mockAuthService.loginWithBiometrics.mockResolvedValue({
        success: false,
      });

      // Mock context login to succeed (fallback path)
      mockAuthContextLogin.mockImplementation(async () => {
        mockUseAuthContext.mockReturnValue({
          state: {
            isAuthenticated: true,
            user: mockUser,
            token: 'mock-token',
            loading: false,
          },
          actions: {
            login: mockAuthContextLogin,
            logout: mockAuthContextLogout,
            refresh: mockAuthContextRefresh,
            clearError: mockAuthContextClearError,
          },
        });
        return true;
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.biometricEnabled).toBe(true);
      });

      let success: boolean;
      await act(async () => {
        success = await result.current.loginWithBiometrics();
      });

      expect(success!).toBe(true);
      // Verify context login was called with stored email and biometric password
      expect(mockAuthContextLogin).toHaveBeenCalledWith({
        email: 'test@example.com',
        password: 'biometric',
        rememberMe: true,
      });
    });

    it('should handle biometric authentication failure', async () => {
      const biometricError: AuthError = {
        code: 'BIOMETRIC_FAILED',
        message: 'Biometric authentication failed',
      };

      mockAuthService.isBiometricLoginEnabled.mockReturnValue(true);
      mockAuthService.getStoredEmail.mockReturnValue(null); // No fallback
      mockAuthService.loginWithBiometrics.mockResolvedValue({
        success: false,
        error: biometricError,
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.biometricEnabled).toBe(true);
      });

      let success: boolean;
      await act(async () => {
        success = await result.current.loginWithBiometrics();
      });

      expect(success!).toBe(false);
      expect(result.current.error).toEqual(biometricError);
    });

    it('should set isLoggingIn during biometric authentication', async () => {
      let resolveBiometric: (value: any) => void;
      mockAuthService.isBiometricLoginEnabled.mockReturnValue(true);
      mockAuthService.getStoredEmail.mockReturnValue('test@example.com');
      mockAuthService.loginWithBiometrics.mockReturnValue(
        new Promise(resolve => {
          resolveBiometric = resolve;
        }),
      );

      // Context login will be called after biometric succeeds
      mockAuthContextLogin.mockResolvedValue(true);

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.biometricEnabled).toBe(true);
      });

      act(() => {
        result.current.loginWithBiometrics();
      });

      expect(result.current.isLoggingIn).toBe(true);

      await act(async () => {
        resolveBiometric!({success: true, data: mockLoginResponse});
      });

      expect(result.current.isLoggingIn).toBe(false);
    });

    it('should handle biometric exception', async () => {
      mockAuthService.isBiometricLoginEnabled.mockReturnValue(true);
      mockAuthService.getStoredEmail.mockReturnValue('test@example.com');
      mockAuthService.loginWithBiometrics.mockRejectedValue(
        new Error('Biometric hardware error'),
      );

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.biometricEnabled).toBe(true);
      });

      let success: boolean;
      await act(async () => {
        success = await result.current.loginWithBiometrics();
      });

      expect(success!).toBe(false);
      expect(result.current.error).toEqual({
        code: 'UNKNOWN_ERROR',
        message: 'Biometric hardware error',
      });
    });
  });

  describe('Enable/Disable Biometric Login', () => {
    it('should enable biometric login successfully', async () => {
      mockAuthService.enableBiometricLogin.mockResolvedValue({success: true});
      mockAuthService.getStoredEmail.mockReturnValue('test@example.com');

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let success: boolean;
      await act(async () => {
        success = await result.current.enableBiometricLogin();
      });

      expect(success!).toBe(true);
      expect(result.current.biometricEnabled).toBe(true);
    });

    it('should handle enable biometric failure', async () => {
      const enableError: AuthError = {
        code: 'BIOMETRIC_NOT_AVAILABLE',
        message: 'Biometric not available',
      };

      mockAuthService.enableBiometricLogin.mockResolvedValue({
        success: false,
        error: enableError,
      });

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      let success: boolean;
      await act(async () => {
        success = await result.current.enableBiometricLogin();
      });

      expect(success!).toBe(false);
      expect(result.current.error).toEqual(enableError);
    });

    it('should disable biometric login successfully', async () => {
      mockAuthService.isBiometricLoginEnabled.mockReturnValue(true);
      mockAuthService.getStoredEmail.mockReturnValue('test@example.com');
      mockAuthService.disableBiometricLogin.mockResolvedValue({success: true});

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.biometricEnabled).toBe(true);
      });

      await act(async () => {
        await result.current.disableBiometricLogin();
      });

      expect(mockAuthService.disableBiometricLogin).toHaveBeenCalled();
      expect(result.current.biometricEnabled).toBe(false);
      expect(result.current.storedEmail).toBeNull();
    });
  });

  describe('Error Handling', () => {
    it('should clear error', async () => {
      // Mock context login to fail
      mockAuthContextLogin.mockResolvedValue(false);

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      await act(async () => {
        await result.current.login({
          email: 'test@example.com',
          password: 'wrong',
        });
      });

      expect(result.current.error).not.toBeNull();

      act(() => {
        result.current.clearError();
      });

      expect(result.current.error).toBeNull();
      expect(mockAuthContextClearError).toHaveBeenCalled();
    });

    it('should clear previous error on new login attempt', async () => {
      // First call fails, second succeeds
      mockAuthContextLogin
        .mockResolvedValueOnce(false)
        .mockResolvedValueOnce(true);

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      // First login fails
      await act(async () => {
        await result.current.login({
          email: 'test@example.com',
          password: 'wrong',
        });
      });

      expect(result.current.error).not.toBeNull();

      // Second login succeeds - error should be cleared
      await act(async () => {
        await result.current.login({
          email: 'test@example.com',
          password: 'correct',
        });
      });

      expect(result.current.error).toBeNull();
    });

    it('should set default error when login fails', async () => {
      // Mock context login to fail
      mockAuthContextLogin.mockResolvedValue(false);

      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      await act(async () => {
        await result.current.login({
          email: 'test@example.com',
          password: 'password',
        });
      });

      expect(result.current.error).toEqual({
        code: 'LOGIN_FAILED',
        message: 'Login failed. Please check your credentials.',
      });
    });
  });

  describe('Refresh Biometric Status', () => {
    it('should refresh biometric status', async () => {
      const {result} = renderHook(() => useAuth());

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      // Update mock to return different values
      const newBiometricStatus: BiometricStatus = {
        isAvailable: true,
        biometricType: 'face',
        isEnrolled: true,
      };
      mockAuthService.checkBiometricAvailability.mockResolvedValue(
        newBiometricStatus,
      );
      mockAuthService.isBiometricLoginEnabled.mockReturnValue(true);
      mockAuthService.getStoredEmail.mockReturnValue('new@example.com');

      await act(async () => {
        await result.current.refreshBiometricStatus();
      });

      expect(result.current.biometricStatus).toEqual(newBiometricStatus);
      expect(result.current.biometricEnabled).toBe(true);
      expect(result.current.storedEmail).toBe('new@example.com');
    });
  });
});
