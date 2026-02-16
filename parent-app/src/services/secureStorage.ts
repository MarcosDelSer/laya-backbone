/**
 * LAYA Parent App - Secure Storage Service
 *
 * Handles secure storage for authentication tokens and sensitive data.
 * Currently uses in-memory storage for development.
 *
 * PRODUCTION NOTE: For production deployment, migrate to:
 * - iOS: Keychain via react-native-keychain
 * - Android: Android Keystore via react-native-keychain
 * - Alternative: expo-secure-store for Expo-based apps
 *
 * AsyncStorage alone is NOT suitable for production token storage
 * as it uses unencrypted storage on the device.
 *
 * Follows pattern from:
 * - authService.ts for service structure
 * - authApi.ts for token key constants
 */

/**
 * Storage error codes
 */
export type StorageErrorCode =
  | 'STORAGE_ERROR'
  | 'KEY_NOT_FOUND'
  | 'ENCRYPTION_ERROR'
  | 'DECRYPTION_ERROR'
  | 'UNKNOWN_ERROR';

/**
 * Storage error type
 */
export interface StorageError {
  code: StorageErrorCode;
  message: string;
}

/**
 * Storage operation result
 */
export interface StorageResult<T = void> {
  success: boolean;
  data?: T;
  error?: StorageError;
}

/**
 * Secure storage key constants
 * Keys are prefixed with 'laya_parent_' for namespacing
 */
export const SECURE_STORAGE_KEYS = {
  accessToken: 'laya_parent_access_token',
  refreshToken: 'laya_parent_refresh_token',
  userId: 'laya_parent_user_id',
  userEmail: 'laya_parent_user_email',
  biometricEnabled: 'laya_parent_biometric_enabled',
  lastLoginAt: 'laya_parent_last_login_at',
} as const;

export type SecureStorageKey =
  (typeof SECURE_STORAGE_KEYS)[keyof typeof SECURE_STORAGE_KEYS];

/**
 * Authentication tokens structure
 */
export interface AuthTokens {
  accessToken: string;
  refreshToken: string;
  expiresAt?: string;
}

/**
 * User credentials for biometric login
 */
export interface StoredCredentials {
  userId: string;
  email: string;
  refreshToken: string;
}

// In-memory storage for development
// PRODUCTION: Replace with react-native-keychain or expo-secure-store
const memoryStorage: Map<string, string> = new Map();

/**
 * Store a value securely
 *
 * @param key - Storage key
 * @param value - Value to store
 * @returns Promise with operation result
 *
 * PRODUCTION NOTE: In production, use react-native-keychain:
 * ```ts
 * import * as Keychain from 'react-native-keychain';
 * await Keychain.setGenericPassword(key, value, { service: key });
 * ```
 *
 * @example
 * ```ts
 * const result = await setItem(SECURE_STORAGE_KEYS.accessToken, 'jwt-token-here');
 * if (!result.success) {
 *   console.error('Failed to store token:', result.error);
 * }
 * ```
 */
export async function setItem(
  key: SecureStorageKey | string,
  value: string,
): Promise<StorageResult> {
  try {
    // PRODUCTION: Replace with Keychain.setGenericPassword(key, value)
    // For iOS: Keychain Services
    // For Android: Android Keystore (via react-native-keychain)
    memoryStorage.set(key, value);

    return {
      success: true,
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message:
          error instanceof Error ? error.message : 'Failed to store value',
      },
    };
  }
}

/**
 * Retrieve a value from secure storage
 *
 * @param key - Storage key
 * @returns Promise with the stored value or null
 *
 * PRODUCTION NOTE: In production, use react-native-keychain:
 * ```ts
 * const credentials = await Keychain.getGenericPassword({ service: key });
 * return credentials ? credentials.password : null;
 * ```
 *
 * @example
 * ```ts
 * const result = await getItem(SECURE_STORAGE_KEYS.accessToken);
 * if (result.success && result.data) {
 *   setAuthHeader(result.data);
 * }
 * ```
 */
export async function getItem(
  key: SecureStorageKey | string,
): Promise<StorageResult<string | null>> {
  try {
    // PRODUCTION: Replace with Keychain.getGenericPassword({ service: key })
    const value = memoryStorage.get(key) || null;

    return {
      success: true,
      data: value,
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message:
          error instanceof Error ? error.message : 'Failed to retrieve value',
      },
    };
  }
}

/**
 * Remove a value from secure storage
 *
 * @param key - Storage key
 * @returns Promise with operation result
 *
 * PRODUCTION NOTE: In production, use react-native-keychain:
 * ```ts
 * await Keychain.resetGenericPassword({ service: key });
 * ```
 *
 * @example
 * ```ts
 * await removeItem(SECURE_STORAGE_KEYS.accessToken);
 * ```
 */
export async function removeItem(
  key: SecureStorageKey | string,
): Promise<StorageResult> {
  try {
    // PRODUCTION: Replace with Keychain.resetGenericPassword({ service: key })
    memoryStorage.delete(key);

    return {
      success: true,
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message:
          error instanceof Error ? error.message : 'Failed to remove value',
      },
    };
  }
}

/**
 * Store authentication tokens securely
 *
 * @param tokens - Access and refresh tokens
 * @returns Promise with operation result
 *
 * @example
 * ```ts
 * const result = await storeAuthTokens({
 *   accessToken: response.accessToken,
 *   refreshToken: response.refreshToken,
 *   expiresAt: response.expiresAt,
 * });
 * ```
 */
export async function storeAuthTokens(
  tokens: AuthTokens,
): Promise<StorageResult> {
  try {
    const accessResult = await setItem(
      SECURE_STORAGE_KEYS.accessToken,
      tokens.accessToken,
    );
    if (!accessResult.success) {
      return accessResult;
    }

    const refreshResult = await setItem(
      SECURE_STORAGE_KEYS.refreshToken,
      tokens.refreshToken,
    );
    if (!refreshResult.success) {
      // Rollback access token if refresh fails
      await removeItem(SECURE_STORAGE_KEYS.accessToken);
      return refreshResult;
    }

    // Store last login time
    await setItem(SECURE_STORAGE_KEYS.lastLoginAt, new Date().toISOString());

    return {
      success: true,
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message:
          error instanceof Error ? error.message : 'Failed to store tokens',
      },
    };
  }
}

/**
 * Retrieve stored authentication tokens
 *
 * @returns Promise with stored tokens or null if not found
 *
 * @example
 * ```ts
 * const result = await getAuthTokens();
 * if (result.success && result.data) {
 *   const { accessToken, refreshToken } = result.data;
 *   // Initialize auth state
 * }
 * ```
 */
export async function getAuthTokens(): Promise<
  StorageResult<AuthTokens | null>
> {
  try {
    const accessResult = await getItem(SECURE_STORAGE_KEYS.accessToken);
    const refreshResult = await getItem(SECURE_STORAGE_KEYS.refreshToken);

    if (!accessResult.success || !refreshResult.success) {
      return {
        success: false,
        error: {
          code: 'STORAGE_ERROR',
          message: 'Failed to retrieve tokens',
        },
      };
    }

    // Both tokens must exist
    if (!accessResult.data || !refreshResult.data) {
      return {
        success: true,
        data: null,
      };
    }

    return {
      success: true,
      data: {
        accessToken: accessResult.data,
        refreshToken: refreshResult.data,
      },
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message:
          error instanceof Error ? error.message : 'Failed to retrieve tokens',
      },
    };
  }
}

/**
 * Clear all authentication tokens
 *
 * Should be called on logout to remove all stored credentials.
 *
 * @returns Promise with operation result
 *
 * @example
 * ```ts
 * await clearAuthTokens();
 * // Navigate to login screen
 * ```
 */
export async function clearAuthTokens(): Promise<StorageResult> {
  try {
    await removeItem(SECURE_STORAGE_KEYS.accessToken);
    await removeItem(SECURE_STORAGE_KEYS.refreshToken);
    await removeItem(SECURE_STORAGE_KEYS.lastLoginAt);

    return {
      success: true,
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message:
          error instanceof Error ? error.message : 'Failed to clear tokens',
      },
    };
  }
}

/**
 * Store user credentials for biometric login
 *
 * @param credentials - User credentials to store
 * @returns Promise with operation result
 *
 * @example
 * ```ts
 * await storeUserCredentials({
 *   userId: user.id,
 *   email: user.email,
 *   refreshToken: tokens.refreshToken,
 * });
 * ```
 */
export async function storeUserCredentials(
  credentials: StoredCredentials,
): Promise<StorageResult> {
  try {
    await setItem(SECURE_STORAGE_KEYS.userId, credentials.userId);
    await setItem(SECURE_STORAGE_KEYS.userEmail, credentials.email);
    await setItem(SECURE_STORAGE_KEYS.refreshToken, credentials.refreshToken);

    return {
      success: true,
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message:
          error instanceof Error
            ? error.message
            : 'Failed to store credentials',
      },
    };
  }
}

/**
 * Retrieve stored user credentials for biometric login
 *
 * @returns Promise with stored credentials or null
 *
 * @example
 * ```ts
 * const result = await getUserCredentials();
 * if (result.success && result.data) {
 *   // Show biometric prompt with stored email
 * }
 * ```
 */
export async function getUserCredentials(): Promise<
  StorageResult<StoredCredentials | null>
> {
  try {
    const userIdResult = await getItem(SECURE_STORAGE_KEYS.userId);
    const emailResult = await getItem(SECURE_STORAGE_KEYS.userEmail);
    const refreshResult = await getItem(SECURE_STORAGE_KEYS.refreshToken);

    if (
      !userIdResult.success ||
      !emailResult.success ||
      !refreshResult.success
    ) {
      return {
        success: false,
        error: {
          code: 'STORAGE_ERROR',
          message: 'Failed to retrieve credentials',
        },
      };
    }

    // All credentials must exist
    if (!userIdResult.data || !emailResult.data || !refreshResult.data) {
      return {
        success: true,
        data: null,
      };
    }

    return {
      success: true,
      data: {
        userId: userIdResult.data,
        email: emailResult.data,
        refreshToken: refreshResult.data,
      },
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message:
          error instanceof Error
            ? error.message
            : 'Failed to retrieve credentials',
      },
    };
  }
}

/**
 * Clear all stored user credentials
 *
 * Should be called when disabling biometric login or on full logout.
 *
 * @returns Promise with operation result
 */
export async function clearUserCredentials(): Promise<StorageResult> {
  try {
    await removeItem(SECURE_STORAGE_KEYS.userId);
    await removeItem(SECURE_STORAGE_KEYS.userEmail);
    // Note: refreshToken is also cleared via clearAuthTokens()

    return {
      success: true,
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message:
          error instanceof Error
            ? error.message
            : 'Failed to clear credentials',
      },
    };
  }
}

/**
 * Enable or disable biometric login preference
 *
 * @param enabled - Whether biometric login should be enabled
 * @returns Promise with operation result
 */
export async function setBiometricEnabled(
  enabled: boolean,
): Promise<StorageResult> {
  return setItem(SECURE_STORAGE_KEYS.biometricEnabled, String(enabled));
}

/**
 * Check if biometric login is enabled
 *
 * @returns Promise with biometric enabled status
 */
export async function isBiometricEnabled(): Promise<StorageResult<boolean>> {
  try {
    const result = await getItem(SECURE_STORAGE_KEYS.biometricEnabled);

    if (!result.success) {
      return {
        success: false,
        error: result.error,
      };
    }

    return {
      success: true,
      data: result.data === 'true',
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message:
          error instanceof Error
            ? error.message
            : 'Failed to check biometric status',
      },
    };
  }
}

/**
 * Clear all secure storage data
 *
 * Should only be called on complete app reset or uninstall.
 *
 * @returns Promise with operation result
 */
export async function clearAllSecureStorage(): Promise<StorageResult> {
  try {
    // Clear all known keys
    const keys = Object.values(SECURE_STORAGE_KEYS);
    await Promise.all(keys.map(key => removeItem(key)));

    // PRODUCTION: Also call Keychain.resetGenericPassword() for each service
    // or clear the entire keychain namespace

    return {
      success: true,
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message:
          error instanceof Error ? error.message : 'Failed to clear storage',
      },
    };
  }
}

export default {
  setItem,
  getItem,
  removeItem,
  storeAuthTokens,
  getAuthTokens,
  clearAuthTokens,
  storeUserCredentials,
  getUserCredentials,
  clearUserCredentials,
  setBiometricEnabled,
  isBiometricEnabled,
  clearAllSecureStorage,
  SECURE_STORAGE_KEYS,
};
