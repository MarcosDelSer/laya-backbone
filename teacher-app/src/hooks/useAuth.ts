/**
 * LAYA Teacher App - useAuth Hook
 *
 * A custom hook for managing authentication state throughout the app.
 * Handles login, logout, biometric authentication, and session management.
 *
 * Uses React Context to share auth state across all hook consumers,
 * ensuring consistent authentication state throughout the app.
 *
 * Follows pattern from:
 * - parent-app/src/hooks/useAuth.ts for hook structure
 * - authService.ts for authentication operations
 */

import {
  useState,
  useEffect,
  useCallback,
  createContext,
  useContext,
  useMemo,
  type ReactNode,
} from 'react';
import type {Teacher, LoginCredentials} from '../types';
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
  type AuthError,
  type BiometricStatus,
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
  user: Teacher | null;
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
  login: (credentials: LoginCredentials, rememberMe?: boolean) => Promise<boolean>;
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
 * Auth Context for sharing state across hook consumers
 */
const AuthContext = createContext<UseAuthReturn | null>(null);

/**
 * Props for AuthProvider component
 */
interface AuthProviderProps {
  children: ReactNode;
}

/**
 * Internal hook that manages the actual auth state.
 * This should only be used by AuthProvider.
 */
function useAuthState(): UseAuthReturn {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isLoggingIn, setIsLoggingIn] = useState(false);
  const [user, setUser] = useState<Teacher | null>(null);
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
    async (credentials: LoginCredentials, rememberMe = false): Promise<boolean> => {
      setIsLoggingIn(true);
      setError(null);

      try {
        // Try real API first
        let result = await authLogin(credentials, rememberMe);

        // If API fails (development mode only), use mock login
        if (__DEV__ && !result.success && result.error?.code === 'NETWORK_ERROR') {
          result = await loginWithMockCredentials(credentials.email, credentials.password);
        }

        if (result.success && result.data) {
          setIsAuthenticated(true);
          setUser(result.data.user);

          // Update biometric state
          if (rememberMe) {
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

      // For development/mock only: if biometric is enabled but no token,
      // just authenticate the user with mock credentials
      if (__DEV__ && storedEmail) {
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

  return useMemo(
    () => ({
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
    }),
    [
      isAuthenticated,
      isLoading,
      isLoggingIn,
      user,
      error,
      biometricStatus,
      biometricEnabled,
      storedEmail,
      login,
      loginWithBiometrics,
      logout,
      enableBiometricLogin,
      disableBiometricLogin,
      clearError,
      refreshBiometricStatus,
    ],
  );
}

/**
 * Provider component for auth state.
 * Wrap your app with this to enable shared auth state across all useAuth consumers.
 *
 * @example
 * ```tsx
 * function App() {
 *   return (
 *     <AuthProvider>
 *       <NavigationContainer>
 *         <RootNavigator />
 *       </NavigationContainer>
 *     </AuthProvider>
 *   );
 * }
 * ```
 */
export function AuthProvider({children}: AuthProviderProps): JSX.Element {
  const auth = useAuthState();

  return <AuthContext.Provider value={auth}>{children}</AuthContext.Provider>;
}

/**
 * Hook for managing authentication throughout the app.
 *
 * When used within an AuthProvider, all consumers share the same auth state.
 * This ensures consistent authentication state across the entire app.
 *
 * @returns {UseAuthReturn} Authentication state and actions
 * @throws {Error} If used outside of an AuthProvider
 *
 * @example
 * ```tsx
 * // First, wrap your app with AuthProvider
 * function App() {
 *   return (
 *     <AuthProvider>
 *       <NavigationContainer>
 *         <RootNavigator />
 *       </NavigationContainer>
 *     </AuthProvider>
 *   );
 * }
 *
 * // Then use the hook in any component
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
 *     const success = await login({ email, password }, rememberMe);
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
  const context = useContext(AuthContext);

  if (context === null) {
    throw new Error('useAuth must be used within an AuthProvider');
  }

  return context;
}

export default useAuth;
