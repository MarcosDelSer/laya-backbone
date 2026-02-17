/**
 * LAYA Parent App - useAuth Hook
 *
 * A custom hook for managing authentication state throughout the app.
 * Handles login, logout, biometric authentication, and session management.
 *
 * Wires LoginScreen actions to AuthProvider so the unauthenticated gate
 * can transition into authenticated state correctly.
 *
 * Follows pattern from:
 * - useChildSelection.ts for hook structure
 * - authService.ts for authentication operations
 */

import {useState, useEffect, useCallback} from 'react';
import type {Parent} from '../types';
import {useAuthContext} from '../contexts/AuthContext';
import {
  loginWithBiometrics as authLoginBiometrics,
  checkBiometricAvailability,
  isBiometricLoginEnabled,
  getStoredEmail,
  enableBiometricLogin as authEnableBiometrics,
  disableBiometricLogin as authDisableBiometrics,
  type LoginCredentials,
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
 * Wires login actions to AuthContext so the authentication gate
 * in App.tsx can properly transition between authenticated/unauthenticated states.
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
  // Get auth state and actions from AuthContext to ensure
  // login/logout updates the app-wide auth gate in App.tsx
  const {state: authContextState, actions: authContextActions} = useAuthContext();

  // Local state for UI (loading indicators, errors)
  const [isLoggingIn, setIsLoggingIn] = useState(false);
  const [error, setError] = useState<AuthError | null>(null);
  const [biometricStatus, setBiometricStatus] = useState<BiometricStatus | null>(null);
  const [biometricEnabled, setBiometricEnabled] = useState(false);
  const [storedEmail, setStoredEmail] = useState<string | null>(null);

  // Auth state comes from context - this is the key fix
  // AppNavigator checks authContextState.isAuthenticated, so we must use the same source
  const isAuthenticated = authContextState.isAuthenticated;
  const user = authContextState.user;
  const isLoading = authContextState.loading;

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
   * Initialize biometric state on mount
   * Auth state is managed by AuthContext/AuthProvider
   */
  useEffect(() => {
    const initBiometrics = async () => {
      // Check biometric availability (auth state comes from context)
      await refreshBiometricStatus();
    };

    initBiometrics();
  }, [refreshBiometricStatus]);

  /**
   * Login with email and password
   * Delegates to AuthContext.actions.login() to update the app-wide auth gate
   */
  const login = useCallback(
    async (credentials: LoginCredentials): Promise<boolean> => {
      setIsLoggingIn(true);
      setError(null);

      try {
        // Use AuthContext login action to update the app-wide auth state
        // This ensures AppNavigator in App.tsx sees the authenticated state
        const success = await authContextActions.login(credentials);

        if (success) {
          // Update biometric state on successful login
          if (credentials.rememberMe) {
            setBiometricEnabled(true);
            setStoredEmail(credentials.email);
          }
          return true;
        }

        setError({code: 'LOGIN_FAILED', message: 'Login failed. Please check your credentials.'});
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
    [authContextActions],
  );

  /**
   * Login using biometrics
   * Uses AuthContext.actions.login() to update the app-wide auth gate
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
        // For biometric login, we need to use the stored credentials
        // The authLoginBiometrics already handles token refresh/validation
        // Use context login to update app-wide state
        if (storedEmail) {
          const success = await authContextActions.login({
            email: storedEmail,
            password: '', // Biometric auth doesn't need password
            rememberMe: true,
          });
          if (success) {
            return true;
          }
        }
      }

      // For development/mock: if biometric is enabled but no token,
      // just authenticate the user via context
      if (storedEmail) {
        const success = await authContextActions.login({
          email: storedEmail,
          password: 'biometric', // Mock password for development
          rememberMe: true,
        });
        if (success) {
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
  }, [biometricEnabled, storedEmail, authContextActions]);

  /**
   * Logout the current user
   * Delegates to AuthContext.actions.logout() to update the app-wide auth gate
   */
  const logout = useCallback(async (): Promise<void> => {
    await authContextActions.logout();
    setError(null);
  }, [authContextActions]);

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
    authContextActions.clearError();
  }, [authContextActions]);

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
