/**
 * LAYA Parent App - Authentication Service
 *
 * Handles authentication API calls, biometric authentication,
 * and secure credential storage.
 *
 * Follows pattern from:
 * - pushNotifications.ts for service structure
 * - API_CONFIG.endpoints.auth for endpoint configuration
 */

import {Platform} from 'react-native';
import {api, setSessionToken, getSessionToken} from '../api/client';
import {API_CONFIG} from '../api/config';
import type {ApiResponse, Parent} from '../types';

/**
 * Authentication error codes
 */
export type AuthErrorCode =
  | 'INVALID_CREDENTIALS'
  | 'NETWORK_ERROR'
  | 'SESSION_EXPIRED'
  | 'BIOMETRIC_NOT_AVAILABLE'
  | 'BIOMETRIC_FAILED'
  | 'BIOMETRIC_CANCELED'
  | 'STORAGE_ERROR'
  | 'UNKNOWN_ERROR';

/**
 * Authentication error
 */
export interface AuthError {
  code: AuthErrorCode;
  message: string;
}

/**
 * Authentication result type
 */
export interface AuthResult<T = void> {
  success: boolean;
  data?: T;
  error?: AuthError;
}

/**
 * Login credentials
 */
export interface LoginCredentials {
  email: string;
  password: string;
  rememberMe?: boolean;
}

/**
 * Login response from API
 */
export interface LoginResponse {
  token: string;
  refreshToken: string;
  expiresAt: string;
  user: Parent;
}

/**
 * Biometric authentication type
 */
export type BiometricType = 'fingerprint' | 'face' | 'iris' | 'none';

/**
 * Biometric availability status
 */
export interface BiometricStatus {
  isAvailable: boolean;
  biometricType: BiometricType;
  isEnrolled: boolean;
}

/**
 * Stored credentials for biometric login
 */
interface StoredCredentials {
  email: string;
  token: string;
  userId: string;
}

// Internal state
let currentUser: Parent | null = null;
let storedCredentials: StoredCredentials | null = null;
let biometricEnabled = false;

/**
 * Check if a valid session exists
 */
export function isAuthenticated(): boolean {
  return getSessionToken() !== null && currentUser !== null;
}

/**
 * Get the current authenticated user
 */
export function getCurrentUser(): Parent | null {
  return currentUser;
}

/**
 * Check biometric availability on the device
 *
 * Note: This is a mock implementation. In production, use
 * react-native-biometrics or expo-local-authentication.
 */
export async function checkBiometricAvailability(): Promise<BiometricStatus> {
  // Mock implementation - in production, use react-native-biometrics
  // For Android, this would check for fingerprint, face unlock, or iris
  // For iOS, this would check for Touch ID or Face ID

  if (Platform.OS === 'android') {
    // Android typically has fingerprint support on most modern devices
    return {
      isAvailable: true,
      biometricType: 'fingerprint',
      isEnrolled: true,
    };
  } else if (Platform.OS === 'ios') {
    // iOS devices have Touch ID or Face ID
    return {
      isAvailable: true,
      biometricType: 'face',
      isEnrolled: true,
    };
  }

  return {
    isAvailable: false,
    biometricType: 'none',
    isEnrolled: false,
  };
}

/**
 * Authenticate using biometrics
 *
 * Note: This is a mock implementation. In production, use
 * react-native-biometrics for proper biometric authentication.
 */
export async function authenticateWithBiometrics(
  promptMessage = 'Authenticate to access your account',
): Promise<AuthResult<boolean>> {
  const status = await checkBiometricAvailability();

  if (!status.isAvailable) {
    return {
      success: false,
      error: {
        code: 'BIOMETRIC_NOT_AVAILABLE',
        message: 'Biometric authentication is not available on this device',
      },
    };
  }

  if (!status.isEnrolled) {
    return {
      success: false,
      error: {
        code: 'BIOMETRIC_NOT_AVAILABLE',
        message: 'No biometrics enrolled on this device',
      },
    };
  }

  // Mock implementation - in production, this would show the system biometric prompt
  // Using react-native-biometrics:
  // const { success } = await ReactNativeBiometrics.simplePrompt({ promptMessage });

  // For development, always succeed
  return {
    success: true,
    data: true,
  };
}

/**
 * Login with email and password
 */
export async function login(
  credentials: LoginCredentials,
): Promise<AuthResult<LoginResponse>> {
  try {
    const response = await api.post<LoginResponse>(
      API_CONFIG.endpoints.auth.login,
      {
        email: credentials.email,
        password: credentials.password,
      },
    );

    if (response.success && response.data) {
      // Set session token for authenticated requests
      setSessionToken(response.data.token);
      currentUser = response.data.user;

      // Store credentials for biometric login if remember me is enabled
      if (credentials.rememberMe) {
        storedCredentials = {
          email: credentials.email,
          token: response.data.refreshToken,
          userId: response.data.user.id,
        };
        biometricEnabled = true;
      }

      return {
        success: true,
        data: response.data,
      };
    }

    // Handle login failure from API
    return {
      success: false,
      error: {
        code: 'INVALID_CREDENTIALS',
        message: response.error?.message || 'Invalid email or password',
      },
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'NETWORK_ERROR',
        message: error instanceof Error ? error.message : 'Network error during login',
      },
    };
  }
}

/**
 * Login using stored biometric credentials
 */
export async function loginWithBiometrics(): Promise<AuthResult<LoginResponse>> {
  // Check if biometric login is enabled
  if (!biometricEnabled || !storedCredentials) {
    return {
      success: false,
      error: {
        code: 'BIOMETRIC_NOT_AVAILABLE',
        message: 'Biometric login is not enabled. Please login with your password first.',
      },
    };
  }

  // Authenticate with biometrics
  const biometricResult = await authenticateWithBiometrics(
    'Confirm your identity to sign in',
  );

  if (!biometricResult.success) {
    return {
      success: false,
      error: biometricResult.error,
    };
  }

  // Use refresh token to get new session
  try {
    const response = await api.post<LoginResponse>(
      API_CONFIG.endpoints.auth.refreshToken,
      {
        refreshToken: storedCredentials.token,
      },
    );

    if (response.success && response.data) {
      setSessionToken(response.data.token);
      currentUser = response.data.user;

      // Update stored refresh token
      storedCredentials.token = response.data.refreshToken;

      return {
        success: true,
        data: response.data,
      };
    }

    // Refresh token might be expired
    biometricEnabled = false;
    storedCredentials = null;

    return {
      success: false,
      error: {
        code: 'SESSION_EXPIRED',
        message: 'Your session has expired. Please login with your password.',
      },
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'NETWORK_ERROR',
        message: error instanceof Error ? error.message : 'Network error during login',
      },
    };
  }
}

/**
 * Logout the current user
 */
export async function logout(): Promise<AuthResult> {
  try {
    // Call logout endpoint to invalidate token
    await api.post(API_CONFIG.endpoints.auth.logout);
  } catch {
    // Ignore logout API errors - we'll clear local state anyway
  }

  // Clear local state
  setSessionToken(null);
  currentUser = null;

  // Optionally clear biometric credentials
  // Keeping them allows users to login with biometrics next time

  return {
    success: true,
  };
}

/**
 * Enable biometric login for the current user
 */
export async function enableBiometricLogin(): Promise<AuthResult> {
  const status = await checkBiometricAvailability();

  if (!status.isAvailable || !status.isEnrolled) {
    return {
      success: false,
      error: {
        code: 'BIOMETRIC_NOT_AVAILABLE',
        message: 'Biometric authentication is not available or not set up on this device',
      },
    };
  }

  // Verify biometrics before enabling
  const biometricResult = await authenticateWithBiometrics(
    'Verify your identity to enable biometric login',
  );

  if (!biometricResult.success) {
    return {
      success: false,
      error: biometricResult.error,
    };
  }

  biometricEnabled = true;

  return {
    success: true,
  };
}

/**
 * Disable biometric login
 */
export async function disableBiometricLogin(): Promise<AuthResult> {
  biometricEnabled = false;
  storedCredentials = null;

  return {
    success: true,
  };
}

/**
 * Check if biometric login is enabled for the current user
 */
export function isBiometricLoginEnabled(): boolean {
  return biometricEnabled && storedCredentials !== null;
}

/**
 * Get stored email for biometric login
 */
export function getStoredEmail(): string | null {
  return storedCredentials?.email || null;
}

/**
 * Refresh the current session token
 */
export async function refreshSession(): Promise<AuthResult<string>> {
  if (!storedCredentials) {
    return {
      success: false,
      error: {
        code: 'SESSION_EXPIRED',
        message: 'No refresh token available. Please login again.',
      },
    };
  }

  try {
    const response = await api.post<{token: string; refreshToken: string}>(
      API_CONFIG.endpoints.auth.refreshToken,
      {
        refreshToken: storedCredentials.token,
      },
    );

    if (response.success && response.data) {
      setSessionToken(response.data.token);
      storedCredentials.token = response.data.refreshToken;

      return {
        success: true,
        data: response.data.token,
      };
    }

    return {
      success: false,
      error: {
        code: 'SESSION_EXPIRED',
        message: 'Failed to refresh session. Please login again.',
      },
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'NETWORK_ERROR',
        message: error instanceof Error ? error.message : 'Network error during token refresh',
      },
    };
  }
}

// Mock data for development when API is unavailable
const MOCK_USER: Parent = {
  id: 'parent-1',
  firstName: 'Sarah',
  lastName: 'Johnson',
  email: 'sarah.johnson@example.com',
  phone: '+1 (555) 123-4567',
  childIds: ['child-1', 'child-2'],
};

const MOCK_LOGIN_RESPONSE: LoginResponse = {
  token: 'mock-jwt-token-12345',
  refreshToken: 'mock-refresh-token-67890',
  expiresAt: new Date(Date.now() + 24 * 60 * 60 * 1000).toISOString(),
  user: MOCK_USER,
};

/**
 * Development-only: Login with mock credentials
 */
export async function loginWithMockCredentials(
  email: string,
  password: string,
): Promise<AuthResult<LoginResponse>> {
  // Simulate network delay
  await new Promise(resolve => setTimeout(resolve, 1000));

  // Accept any non-empty credentials for development
  if (email && password && password.length >= 1) {
    setSessionToken(MOCK_LOGIN_RESPONSE.token);
    currentUser = {
      ...MOCK_USER,
      email: email,
    };

    storedCredentials = {
      email: email,
      token: MOCK_LOGIN_RESPONSE.refreshToken,
      userId: MOCK_USER.id,
    };
    biometricEnabled = true;

    return {
      success: true,
      data: {
        ...MOCK_LOGIN_RESPONSE,
        user: {
          ...MOCK_USER,
          email: email,
        },
      },
    };
  }

  return {
    success: false,
    error: {
      code: 'INVALID_CREDENTIALS',
      message: 'Invalid email or password',
    },
  };
}

export default {
  isAuthenticated,
  getCurrentUser,
  checkBiometricAvailability,
  authenticateWithBiometrics,
  login,
  loginWithBiometrics,
  logout,
  enableBiometricLogin,
  disableBiometricLogin,
  isBiometricLoginEnabled,
  getStoredEmail,
  refreshSession,
  loginWithMockCredentials,
};
