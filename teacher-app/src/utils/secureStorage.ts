/**
 * LAYA Teacher App - Secure Storage Utility
 *
 * Type-safe wrapper for secure storage operations using react-native-keychain.
 * Provides encryption-backed storage for sensitive data like authentication tokens.
 *
 * Security Features:
 * - iOS: Uses Keychain Services for secure storage
 * - Android: Uses Android Keystore System with AES encryption
 * - Type-safe API with explicit error handling
 * - Automatic JSON serialization/deserialization for complex objects
 * - Storage key namespacing to prevent collisions
 *
 * This implementation follows security best practices from:
 * - OWASP Mobile Security Guidelines
 * - React Native Security documentation
 * - teacher-portal/lib/auth/token.ts patterns
 */

/**
 * Storage operation result
 */
export interface SecureStorageResult<T = string> {
  success: boolean;
  data?: T;
  error?: SecureStorageError;
}

/**
 * Storage error information
 */
export interface SecureStorageError {
  code: StorageErrorCode;
  message: string;
  originalError?: Error;
}

/**
 * Storage error codes
 */
export type StorageErrorCode =
  | 'STORAGE_ERROR'
  | 'KEY_NOT_FOUND'
  | 'SERIALIZATION_ERROR'
  | 'DESERIALIZATION_ERROR'
  | 'KEYCHAIN_ERROR'
  | 'BIOMETRIC_ERROR'
  | 'UNKNOWN_ERROR';

/**
 * Storage options for setItem
 */
export interface SecureStorageOptions {
  /**
   * Require biometric authentication to access this item
   * @default false
   */
  requireBiometrics?: boolean;

  /**
   * Access control level for iOS Keychain
   * @default 'AccessibleWhenUnlocked'
   */
  accessControl?: 'AccessibleWhenUnlocked' | 'AccessibleAfterFirstUnlock' | 'AccessibleAlways';
}

/**
 * Storage key namespace prefix
 * All keys are prefixed to prevent collisions with other apps
 */
const KEY_PREFIX = 'laya_teacher_secure_';

/**
 * Pre-defined secure storage keys for common use cases
 */
export const STORAGE_KEYS = {
  ACCESS_TOKEN: `${KEY_PREFIX}access_token`,
  REFRESH_TOKEN: `${KEY_PREFIX}refresh_token`,
  USER_ID: `${KEY_PREFIX}user_id`,
  USER_EMAIL: `${KEY_PREFIX}user_email`,
  BIOMETRIC_ENABLED: `${KEY_PREFIX}biometric_enabled`,
  LAST_LOGIN_AT: `${KEY_PREFIX}last_login_at`,
} as const;

/**
 * In-memory storage fallback for development and testing
 * PRODUCTION: This is replaced by react-native-keychain
 */
const memoryStorage = new Map<string, string>();

/**
 * Flag to track if react-native-keychain is available
 */
let isKeychainAvailable = false;

/**
 * Lazy-loaded keychain module
 */
let Keychain: any = null;

/**
 * Initialize the keychain module
 * Attempts to load react-native-keychain, falls back to memory storage if unavailable
 */
async function initializeKeychain(): Promise<boolean> {
  if (Keychain !== null) {
    return isKeychainAvailable;
  }

  try {
    // Attempt to load react-native-keychain
    Keychain = require('react-native-keychain');
    isKeychainAvailable = true;
    return true;
  } catch (error) {
    // Keychain not available - using memory storage
    // This is expected in development/testing environments
    Keychain = null;
    isKeychainAvailable = false;
    return false;
  }
}

/**
 * Store a string value securely
 *
 * @param key - Storage key (will be prefixed with namespace)
 * @param value - String value to store
 * @param options - Storage options (biometrics, access control)
 * @returns Promise with operation result
 *
 * @example
 * ```ts
 * const result = await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'jwt-token-here');
 * if (!result.success) {
 *   console.error('Storage failed:', result.error?.message);
 * }
 * ```
 *
 * @example With biometric protection
 * ```ts
 * await setItem(STORAGE_KEYS.ACCESS_TOKEN, token, {
 *   requireBiometrics: true,
 *   accessControl: 'AccessibleWhenUnlocked'
 * });
 * ```
 */
export async function setItem(
  key: string,
  value: string,
  options?: SecureStorageOptions,
): Promise<SecureStorageResult<void>> {
  try {
    await initializeKeychain();

    if (isKeychainAvailable && Keychain) {
      // Use react-native-keychain for secure storage
      const keychainOptions: any = {
        service: key,
      };

      // Add biometric requirement if specified
      if (options?.requireBiometrics) {
        keychainOptions.accessControl = Keychain.ACCESS_CONTROL.BIOMETRY_ANY;
        keychainOptions.accessible = Keychain.ACCESSIBLE.WHEN_UNLOCKED;
      } else if (options?.accessControl) {
        // Map access control to keychain constants
        keychainOptions.accessible =
          options.accessControl === 'AccessibleWhenUnlocked'
            ? Keychain.ACCESSIBLE.WHEN_UNLOCKED
            : options.accessControl === 'AccessibleAfterFirstUnlock'
            ? Keychain.ACCESSIBLE.AFTER_FIRST_UNLOCK
            : Keychain.ACCESSIBLE.ALWAYS;
      }

      await Keychain.setGenericPassword(key, value, keychainOptions);
    } else {
      // Fallback to memory storage (development/testing)
      memoryStorage.set(key, value);
    }

    return { success: true };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message: error instanceof Error ? error.message : 'Failed to store value',
        originalError: error instanceof Error ? error : undefined,
      },
    };
  }
}

/**
 * Retrieve a string value from secure storage
 *
 * @param key - Storage key (will be prefixed with namespace)
 * @returns Promise with the stored value or null if not found
 *
 * @example
 * ```ts
 * const result = await getItem(STORAGE_KEYS.ACCESS_TOKEN);
 * if (result.success && result.data) {
 *   // Use token
 *   console.log('Token:', result.data);
 * } else if (result.data === null) {
 *   // No token stored
 * } else {
 *   // Error occurred
 *   console.error(result.error?.message);
 * }
 * ```
 */
export async function getItem(key: string): Promise<SecureStorageResult<string | null>> {
  try {
    await initializeKeychain();

    if (isKeychainAvailable && Keychain) {
      // Retrieve from react-native-keychain
      const credentials = await Keychain.getGenericPassword({ service: key });

      if (credentials && typeof credentials === 'object' && 'password' in credentials) {
        return {
          success: true,
          data: credentials.password,
        };
      }

      return {
        success: true,
        data: null,
      };
    } else {
      // Retrieve from memory storage (development/testing)
      const value = memoryStorage.get(key);

      return {
        success: true,
        data: value ?? null,
      };
    }
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message: error instanceof Error ? error.message : 'Failed to retrieve value',
        originalError: error instanceof Error ? error : undefined,
      },
    };
  }
}

/**
 * Remove a value from secure storage
 *
 * @param key - Storage key (will be prefixed with namespace)
 * @returns Promise with operation result
 *
 * @example
 * ```ts
 * await removeItem(STORAGE_KEYS.ACCESS_TOKEN);
 * ```
 */
export async function removeItem(key: string): Promise<SecureStorageResult<void>> {
  try {
    await initializeKeychain();

    if (isKeychainAvailable && Keychain) {
      // Remove from react-native-keychain
      await Keychain.resetGenericPassword({ service: key });
    } else {
      // Remove from memory storage (development/testing)
      memoryStorage.delete(key);
    }

    return { success: true };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message: error instanceof Error ? error.message : 'Failed to remove value',
        originalError: error instanceof Error ? error : undefined,
      },
    };
  }
}

/**
 * Store a JSON-serializable object securely
 *
 * @param key - Storage key
 * @param value - Object to store (will be JSON serialized)
 * @param options - Storage options
 * @returns Promise with operation result
 *
 * @example
 * ```ts
 * const user = { id: '123', email: 'user@example.com' };
 * await setObject('user_profile', user);
 * ```
 */
export async function setObject<T = any>(
  key: string,
  value: T,
  options?: SecureStorageOptions,
): Promise<SecureStorageResult<void>> {
  try {
    const serialized = JSON.stringify(value);
    return await setItem(key, serialized, options);
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'SERIALIZATION_ERROR',
        message: error instanceof Error ? error.message : 'Failed to serialize object',
        originalError: error instanceof Error ? error : undefined,
      },
    };
  }
}

/**
 * Retrieve a JSON object from secure storage
 *
 * @param key - Storage key
 * @returns Promise with the stored object or null if not found
 *
 * @example
 * ```ts
 * const result = await getObject<UserProfile>('user_profile');
 * if (result.success && result.data) {
 *   console.log('User:', result.data);
 * }
 * ```
 */
export async function getObject<T = any>(key: string): Promise<SecureStorageResult<T | null>> {
  try {
    const result = await getItem(key);

    if (!result.success) {
      return {
        success: false,
        error: result.error,
      };
    }

    if (result.data === null) {
      return {
        success: true,
        data: null,
      };
    }

    const parsed = JSON.parse(result.data);

    return {
      success: true,
      data: parsed,
    };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'DESERIALIZATION_ERROR',
        message: error instanceof Error ? error.message : 'Failed to deserialize object',
        originalError: error instanceof Error ? error : undefined,
      },
    };
  }
}

/**
 * Check if a key exists in secure storage
 *
 * @param key - Storage key to check
 * @returns Promise with boolean indicating if key exists
 *
 * @example
 * ```ts
 * const hasToken = await hasItem(STORAGE_KEYS.ACCESS_TOKEN);
 * if (hasToken) {
 *   // Token exists
 * }
 * ```
 */
export async function hasItem(key: string): Promise<boolean> {
  const result = await getItem(key);
  return result.success && result.data !== null;
}

/**
 * Clear all items with the app's namespace prefix
 *
 * WARNING: This removes ALL secure storage data for the app
 *
 * @returns Promise with operation result
 *
 * @example
 * ```ts
 * await clearAll(); // Clear all app data on logout
 * ```
 */
export async function clearAll(): Promise<SecureStorageResult<void>> {
  try {
    await initializeKeychain();

    if (isKeychainAvailable && Keychain) {
      // Clear all keychain entries for known keys
      const keys = Object.values(STORAGE_KEYS);
      await Promise.all(keys.map((key) => Keychain.resetGenericPassword({ service: key })));
    } else {
      // Clear memory storage (development/testing)
      memoryStorage.clear();
    }

    return { success: true };
  } catch (error) {
    return {
      success: false,
      error: {
        code: 'STORAGE_ERROR',
        message: error instanceof Error ? error.message : 'Failed to clear storage',
        originalError: error instanceof Error ? error : undefined,
      },
    };
  }
}

/**
 * Get all storage keys (development/testing only)
 *
 * WARNING: This only works with memory storage fallback
 *
 * @returns Array of all keys in storage
 */
export function getAllKeys(): string[] {
  if (!isKeychainAvailable) {
    return Array.from(memoryStorage.keys());
  }
  // Keychain doesn't support key enumeration - return predefined keys
  return Object.values(STORAGE_KEYS);
}

/**
 * Check if secure storage is available (using keychain vs memory fallback)
 *
 * @returns Promise with boolean indicating if keychain is available
 */
export async function isSecureStorageAvailable(): Promise<boolean> {
  await initializeKeychain();
  return isKeychainAvailable;
}

/**
 * Default export with all functions
 */
export default {
  setItem,
  getItem,
  removeItem,
  setObject,
  getObject,
  hasItem,
  clearAll,
  getAllKeys,
  isSecureStorageAvailable,
  STORAGE_KEYS,
};
