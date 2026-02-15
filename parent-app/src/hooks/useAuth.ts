/**
 * LAYA Parent App - useAuth Hook
 *
 * A custom hook for managing authentication state throughout the app.
 * Handles login, logout, biometric authentication, and session management.
 *
 * Follows pattern from:
 * - useChildSelection.ts for hook structure
 * - authService.ts for authentication operations
 */

import {useState, useEffect, useCallback} from 'react';
import type {Parent} from '../types';
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
  type LoginCredentials,
  type LoginResponse,
  type BiometricStatus,
  type AuthError,
} from '../services/authService';

/**
 * Authentication state
 */
interface UseAuthState {
  /** Whether the user is currently authenticated */
  isAuthenticated: boolean;
  /** Whether authentication state is being loaded */
  isLoading: boolean;
  /** Whether a login operation is in progress */
  isLoggingIn: boolean;
  /** Current authenticated user */
  user: Parent | null;
  /** Authentication error from the last operation */
  error: AuthError | null;
  /** Biometric availability status */
  biometricStatus: BiometricStatus | null;
  /** Whether biometric login is enabled for this user */
  biometricEnabled: boolean;
  /** Stored email for biometric login display */
  storedEmail: string | null;
}

/**
 * Authentication actions
 */
interface UseAuthActions {
  /** Login with email and password */
  login: (credentials: LoginCredentials) => Promise<boolean>;
  /** Login using biometrics */
  loginWithBiometrics: () => Promise<boolean>;
  /** Logout the current user */
  logout: () => Promise<void>;
  /** Enable biometric login */
  enableBiometricLogin: () => Promise<boolean>;
  /** Disable biometric login */
  disableBiometricLogin: () => Promise<void>;
  /** Clear any authentication errors */
  clearError: () => void;
  /** Refresh biometric status */
  refreshBiometricStatus: () => Promise<void>;
}

export type UseAuthReturn = UseAuthState & UseAuthActions;

/**
 * Hook for managing authentication throughout the app
 *
 * @returns {UseAuthReturn} Authentication state and actions
 *
 * @example
 * ```tsx
 * function LoginScreen() {
 *   const {
 *     isAuthenticated,
 *     isLoggingIn,
 *     error,
 *     biometricEnabled,
 *     login,
 *     loginWithBiometrics,
 *   } = useAuth();
 *
 *   if (isAuthenticated) {
 *     // Navigate to main app
 *   }
 *
 *   const handleLogin = async () => {
 *     const success = await login({ email, password });
 *     if (!success) {
 *       // Show error message
 *     }
 *   };
 *
 *   return (
 *     <View>
 *       {biometricEnabled && (
 *         <Button title="Login with Fingerprint" onPress={loginWithBiometrics} />
 *       )}
 *       <TextInput placeholder="Email" onChangeText={setEmail} />
 *       <TextInput placeholder="Password" onChangeText={setPassword} secureTextEntry />
 *       <Button title="Login" onPress={handleLogin} disabled={isLoggingIn} />
 *       {error && <Text>{error.message}</Text>}
 *     </View>
 *   );
 * }
 * ```
 */
export function useAuth(): UseAuthReturn {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoggingIn, setIsLoggingIn] = useState(false);
  const [user, setUser] = useState<Parent | null>(null);
  const [error, setError] = useState<AuthError | null>(null);
  const [biometricStatus, setBiometricStatus] = useState<BiometricStatus | null>(null);
  const [biometricEnabled, setBiometricEnabled] = useState(false);
  const [storedEmail, setStoredEmail] = useState<string | null>(null);

  /**
   * Refresh biometric availability status
   */
  const refreshBiometricStatus = useCallback(async (): Promise<void> => {
    const status = await checkBiometricAvailability();
    setBiometricStatus(status);
    setBiometricEnabled(isBiometricLoginEnabled());
    setStoredEmail(getStoredEmail());
  }, []);

  /**
   * Initialize authentication state on mount
   */
  useEffect(() => {
    const initAuth = async () => {
      setIsLoading(true);

      // Check if already authenticated (session persisted)
      const authenticated = checkAuth();
      setIsAuthenticated(authenticated);

      if (authenticated) {
        setUser(getCurrentUser());
      }

      // Check biometric availability
      await refreshBiometricStatus();

      setIsLoading(false);
    };

    initAuth();
  }, [refreshBiometricStatus]);

  /**
   * Login with email and password
   */
  const login = useCallback(
    async (credentials: LoginCredentials): Promise<boolean> => {
      setIsLoggingIn(true);
      setError(null);

      try {
        // Try real API first, fall back to mock for development
        let result = await authLogin(credentials);

        // If API fails (development mode), use mock login
        if (!result.success && result.error?.code === 'NETWORK_ERROR') {
          result = await loginWithMockCredentials(credentials.email, credentials.password);
        }

        if (result.success && result.data) {
          setIsAuthenticated(true);
          setUser(result.data.user);

          // Update biometric state
          if (credentials.rememberMe) {
            setBiometricEnabled(true);
            setStoredEmail(credentials.email);
          }

          return true;
        }

        setError(result.error || {code: 'UNKNOWN_ERROR', message: 'Login failed'});
        return false;
      } catch (err) {
        setError({
          code: 'UNKNOWN_ERROR',
          message: err instanceof Error ? err.message : 'An unexpected error occurred',
        });
        return false;
      } finally {
        setIsLoggingIn(false);
      }
    },
    [],
  );

  /**
   * Login using biometrics
   */
  const loginWithBiometrics = useCallback(async (): Promise<boolean> => {
    if (!biometricEnabled) {
      setError({
        code: 'BIOMETRIC_NOT_AVAILABLE',
        message: 'Biometric login is not enabled',
      });
      return false;
    }

    setIsLoggingIn(true);
    setError(null);

    try {
      const result = await authLoginBiometrics();

      if (result.success && result.data) {
        setIsAuthenticated(true);
        setUser(result.data.user);
        return true;
      }

      // For development/mock: if biometric is enabled but no token,
      // just authenticate the user
      if (storedEmail) {
        const mockResult = await loginWithMockCredentials(storedEmail, 'biometric');
        if (mockResult.success && mockResult.data) {
          setIsAuthenticated(true);
          setUser(mockResult.data.user);
          return true;
        }
      }

      setError(result.error || {code: 'BIOMETRIC_FAILED', message: 'Biometric login failed'});
      return false;
    } catch (err) {
      setError({
        code: 'UNKNOWN_ERROR',
        message: err instanceof Error ? err.message : 'An unexpected error occurred',
      });
      return false;
    } finally {
      setIsLoggingIn(false);
    }
  }, [biometricEnabled, storedEmail]);

  /**
   * Logout the current user
   */
  const logout = useCallback(async (): Promise<void> => {
    await authLogout();
    setIsAuthenticated(false);
    setUser(null);
    setError(null);
  }, []);

  /**
   * Enable biometric login
   */
  const enableBiometricLogin = useCallback(async (): Promise<boolean> => {
    setError(null);

    const result = await authEnableBiometrics();

    if (result.success) {
      setBiometricEnabled(true);
      setStoredEmail(getStoredEmail());
      return true;
    }

    setError(result.error || {code: 'UNKNOWN_ERROR', message: 'Failed to enable biometric login'});
    return false;
  }, []);

  /**
   * Disable biometric login
   */
  const disableBiometricLogin = useCallback(async (): Promise<void> => {
    await authDisableBiometrics();
    setBiometricEnabled(false);
    setStoredEmail(null);
  }, []);

  /**
   * Clear authentication error
   */
  const clearError = useCallback((): void => {
    setError(null);
  }, []);

  return {
    // State
    isAuthenticated,
    isLoading,
    isLoggingIn,
    user,
    error,
    biometricStatus,
    biometricEnabled,
    storedEmail,
    // Actions
    login,
    loginWithBiometrics,
    logout,
    enableBiometricLogin,
    disableBiometricLogin,
    clearError,
    refreshBiometricStatus,
  };
}

export default useAuth;
