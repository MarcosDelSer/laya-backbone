/**
 * LAYA Parent App - Authentication Context
 *
 * Provides app-wide authentication state and actions through React Context.
 * Handles login, logout, token refresh, and session persistence.
 *
 * Usage:
 * 1. Wrap your app with AuthProvider in App.tsx
 * 2. Use useAuthContext() in any component to access auth state
 */

import React, {
  createContext,
  useContext,
  useCallback,
  useMemo,
  useReducer,
  useEffect,
} from 'react';

import type {Parent, AuthState} from '../types';
import {
  login as apiLogin,
  logout as apiLogout,
  getCurrentUser,
  initializeAuth,
  clearSession,
  LoginCredentials,
} from '../api/authApi';

/**
 * Authentication actions available through the context.
 */
export interface AuthActions {
  /**
   * Log in with email and password.
   * @param credentials - Login credentials
   * @returns Promise resolving to true on success, false on failure
   */
  login: (credentials: LoginCredentials) => Promise<boolean>;

  /**
   * Log out the current user.
   * @returns Promise resolving when logout is complete
   */
  logout: () => Promise<void>;

  /**
   * Refresh the authentication state.
   * Useful after token refresh or profile updates.
   * @returns Promise resolving to true if user is still authenticated
   */
  refresh: () => Promise<boolean>;

  /**
   * Clear any authentication error.
   */
  clearError: () => void;
}

/**
 * Context value type combining state and actions.
 */
export interface AuthContextValue {
  /** Current authentication state */
  state: AuthState;
  /** Authentication control actions */
  actions: AuthActions;
}

/**
 * Props for the AuthProvider component.
 */
export interface AuthProviderProps {
  /** Child components */
  children: React.ReactNode;
  /**
   * Callback when authentication state changes.
   * Useful for navigation or analytics.
   */
  onAuthStateChange?: (isAuthenticated: boolean) => void;
  /**
   * Function to retrieve stored tokens.
   * Should use secure storage (e.g., Keychain on iOS).
   */
  getStoredTokens?: () => Promise<{
    accessToken: string | null;
    refreshToken: string | null;
  }>;
  /**
   * Function to store tokens securely.
   * Should use secure storage (e.g., Keychain on iOS).
   */
  setStoredTokens?: (
    accessToken: string | null,
    refreshToken: string | null,
  ) => Promise<void>;
}

// ============================================================================
// Reducer
// ============================================================================

type AuthAction =
  | {type: 'AUTH_LOADING'}
  | {type: 'AUTH_SUCCESS'; payload: {user: Parent; token: string}}
  | {type: 'AUTH_FAILURE'; payload: {error: string}}
  | {type: 'AUTH_LOGOUT'}
  | {type: 'CLEAR_ERROR'};

const initialState: AuthState = {
  isAuthenticated: false,
  user: null,
  token: null,
  loading: true, // Start with loading to check stored auth
};

function authReducer(state: AuthState, action: AuthAction): AuthState {
  switch (action.type) {
    case 'AUTH_LOADING':
      return {
        ...state,
        loading: true,
      };

    case 'AUTH_SUCCESS':
      return {
        isAuthenticated: true,
        user: action.payload.user,
        token: action.payload.token,
        loading: false,
      };

    case 'AUTH_FAILURE':
      return {
        isAuthenticated: false,
        user: null,
        token: null,
        loading: false,
      };

    case 'AUTH_LOGOUT':
      return {
        isAuthenticated: false,
        user: null,
        token: null,
        loading: false,
      };

    case 'CLEAR_ERROR':
      return {
        ...state,
      };

    default:
      return state;
  }
}

// ============================================================================
// Context
// ============================================================================

/**
 * Create the auth context with undefined default value
 * to enforce usage within provider.
 */
const AuthContext = createContext<AuthContextValue | undefined>(undefined);

/**
 * Display name for debugging.
 */
AuthContext.displayName = 'AuthContext';

// ============================================================================
// Provider Component
// ============================================================================

/**
 * AuthProvider component
 *
 * Wraps the application with authentication state management.
 * Should be placed near the root of the component tree.
 *
 * @example
 * ```tsx
 * function App() {
 *   const getStoredTokens = async () => {
 *     // Use secure storage
 *     return {
 *       accessToken: await SecureStore.getItemAsync('accessToken'),
 *       refreshToken: await SecureStore.getItemAsync('refreshToken'),
 *     };
 *   };
 *
 *   const setStoredTokens = async (access, refresh) => {
 *     if (access && refresh) {
 *       await SecureStore.setItemAsync('accessToken', access);
 *       await SecureStore.setItemAsync('refreshToken', refresh);
 *     } else {
 *       await SecureStore.deleteItemAsync('accessToken');
 *       await SecureStore.deleteItemAsync('refreshToken');
 *     }
 *   };
 *
 *   return (
 *     <AuthProvider
 *       getStoredTokens={getStoredTokens}
 *       setStoredTokens={setStoredTokens}
 *       onAuthStateChange={(isAuth) => {
 *         // Handle auth state change
 *       }}
 *     >
 *       <AppNavigator />
 *     </AuthProvider>
 *   );
 * }
 * ```
 */
export function AuthProvider({
  children,
  onAuthStateChange,
  getStoredTokens,
  setStoredTokens,
}: AuthProviderProps): React.JSX.Element {
  const [state, dispatch] = useReducer(authReducer, initialState);

  // Store tokens securely when they change
  const storeTokens = useCallback(
    async (accessToken: string | null, refreshToken: string | null) => {
      if (setStoredTokens) {
        await setStoredTokens(accessToken, refreshToken);
      }
    },
    [setStoredTokens],
  );

  // Initialize auth state from stored tokens
  useEffect(() => {
    let mounted = true;

    const initAuth = async () => {
      if (!getStoredTokens) {
        // No token storage provided, user needs to log in
        if (mounted) {
          dispatch({type: 'AUTH_FAILURE', payload: {error: ''}});
        }
        return;
      }

      try {
        const {accessToken, refreshToken} = await getStoredTokens();

        if (!accessToken || !refreshToken) {
          // No stored tokens, user needs to log in
          if (mounted) {
            dispatch({type: 'AUTH_FAILURE', payload: {error: ''}});
          }
          return;
        }

        // Try to restore session from stored tokens
        const authState = await initializeAuth(accessToken, refreshToken);

        if (mounted) {
          if (authState) {
            // Session restored successfully
            dispatch({
              type: 'AUTH_SUCCESS',
              payload: {
                user: authState.user,
                token: authState.accessToken,
              },
            });

            // Update stored tokens if they were refreshed
            if (authState.accessToken !== accessToken) {
              await storeTokens(authState.accessToken, authState.refreshToken);
            }
          } else {
            // Could not restore session
            dispatch({type: 'AUTH_FAILURE', payload: {error: ''}});
            await storeTokens(null, null);
          }
        }
      } catch {
        if (mounted) {
          dispatch({type: 'AUTH_FAILURE', payload: {error: 'Failed to restore session'}});
        }
      }
    };

    initAuth();

    return () => {
      mounted = false;
    };
  }, [getStoredTokens, storeTokens]);

  // Notify when auth state changes
  useEffect(() => {
    if (!state.loading && onAuthStateChange) {
      onAuthStateChange(state.isAuthenticated);
    }
  }, [state.isAuthenticated, state.loading, onAuthStateChange]);

  // Login action
  const login = useCallback(
    async (credentials: LoginCredentials): Promise<boolean> => {
      dispatch({type: 'AUTH_LOADING'});

      const result = await apiLogin(credentials);

      if (result.success && result.data) {
        dispatch({
          type: 'AUTH_SUCCESS',
          payload: {
            user: result.data.user,
            token: result.data.accessToken,
          },
        });

        // Store tokens securely
        await storeTokens(result.data.accessToken, result.data.refreshToken);

        return true;
      }

      dispatch({
        type: 'AUTH_FAILURE',
        payload: {
          error: result.error?.message || 'Login failed',
        },
      });

      return false;
    },
    [storeTokens],
  );

  // Logout action
  const logout = useCallback(async (): Promise<void> => {
    dispatch({type: 'AUTH_LOADING'});

    // Call logout API (ignore errors, we'll clear local state anyway)
    await apiLogout();

    // Clear session and stored tokens
    clearSession();
    await storeTokens(null, null);

    dispatch({type: 'AUTH_LOGOUT'});
  }, [storeTokens]);

  // Refresh action - re-fetch user profile
  const refresh = useCallback(async (): Promise<boolean> => {
    if (!state.token) {
      return false;
    }

    const result = await getCurrentUser();

    if (result.success && result.data) {
      dispatch({
        type: 'AUTH_SUCCESS',
        payload: {
          user: result.data,
          token: state.token,
        },
      });
      return true;
    }

    // Token might be expired, clear session
    clearSession();
    await storeTokens(null, null);
    dispatch({type: 'AUTH_LOGOUT'});
    return false;
  }, [state.token, storeTokens]);

  // Clear error action
  const clearError = useCallback((): void => {
    dispatch({type: 'CLEAR_ERROR'});
  }, []);

  // Memoize actions object
  const actions: AuthActions = useMemo(
    () => ({
      login,
      logout,
      refresh,
      clearError,
    }),
    [login, logout, refresh, clearError],
  );

  // Memoize context value to prevent unnecessary re-renders
  const contextValue: AuthContextValue = useMemo(
    () => ({
      state,
      actions,
    }),
    [state, actions],
  );

  return (
    <AuthContext.Provider value={contextValue}>{children}</AuthContext.Provider>
  );
}

// ============================================================================
// Hook
// ============================================================================

/**
 * Hook to access the authentication context.
 *
 * Must be used within an AuthProvider.
 * Throws an error if used outside the provider.
 *
 * @returns AuthContextValue - The auth state and actions
 *
 * @example
 * ```tsx
 * function LoginScreen() {
 *   const { state, actions } = useAuthContext();
 *
 *   const handleLogin = async () => {
 *     const success = await actions.login({
 *       email: 'parent@example.com',
 *       password: 'password123',
 *     });
 *
 *     if (success) {
 *       navigation.navigate('Home');
 *     }
 *   };
 *
 *   if (state.loading) {
 *     return <LoadingSpinner />;
 *   }
 *
 *   return (
 *     <View>
 *       <Button title="Login" onPress={handleLogin} />
 *     </View>
 *   );
 * }
 * ```
 */
export function useAuthContext(): AuthContextValue {
  const context = useContext(AuthContext);

  if (context === undefined) {
    throw new Error(
      'useAuthContext must be used within an AuthProvider. ' +
        'Ensure your component is wrapped with <AuthProvider>.',
    );
  }

  return context;
}

/**
 * Re-export types for convenience.
 */
export type {Parent, AuthState} from '../types';
export type {LoginCredentials} from '../api/authApi';

export default AuthContext;
