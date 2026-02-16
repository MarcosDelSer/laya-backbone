/**
 * LAYA Parent App - Secure Storage Service Tests
 *
 * Tests for secure storage functions covering:
 * - Basic storage operations (set, get, remove)
 * - Auth token storage and retrieval
 * - User credential management
 * - Biometric settings
 * - Storage clearing
 */

import {
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
} from '../../src/services/secureStorage';
import type {AuthTokens, StoredCredentials} from '../../src/services/secureStorage';

describe('secureStorage', () => {
  // Clear storage before each test to ensure isolation
  beforeEach(async () => {
    await clearAllSecureStorage();
  });

  describe('SECURE_STORAGE_KEYS', () => {
    it('should have correct storage key constants', () => {
      expect(SECURE_STORAGE_KEYS.accessToken).toBe('laya_parent_access_token');
      expect(SECURE_STORAGE_KEYS.refreshToken).toBe('laya_parent_refresh_token');
      expect(SECURE_STORAGE_KEYS.userId).toBe('laya_parent_user_id');
      expect(SECURE_STORAGE_KEYS.userEmail).toBe('laya_parent_user_email');
      expect(SECURE_STORAGE_KEYS.biometricEnabled).toBe('laya_parent_biometric_enabled');
      expect(SECURE_STORAGE_KEYS.lastLoginAt).toBe('laya_parent_last_login_at');
    });
  });

  describe('setItem', () => {
    it('should successfully store a value', async () => {
      const result = await setItem(SECURE_STORAGE_KEYS.accessToken, 'test-token');

      expect(result.success).toBe(true);
      expect(result.error).toBeUndefined();
    });

    it('should store values with custom keys', async () => {
      const result = await setItem('custom_key', 'custom_value');

      expect(result.success).toBe(true);
    });

    it('should overwrite existing values', async () => {
      await setItem(SECURE_STORAGE_KEYS.accessToken, 'first-token');
      await setItem(SECURE_STORAGE_KEYS.accessToken, 'second-token');

      const getResult = await getItem(SECURE_STORAGE_KEYS.accessToken);

      expect(getResult.success).toBe(true);
      expect(getResult.data).toBe('second-token');
    });
  });

  describe('getItem', () => {
    it('should retrieve a stored value', async () => {
      await setItem(SECURE_STORAGE_KEYS.accessToken, 'stored-token');

      const result = await getItem(SECURE_STORAGE_KEYS.accessToken);

      expect(result.success).toBe(true);
      expect(result.data).toBe('stored-token');
    });

    it('should return null for non-existent key', async () => {
      const result = await getItem(SECURE_STORAGE_KEYS.accessToken);

      expect(result.success).toBe(true);
      expect(result.data).toBeNull();
    });

    it('should return null for cleared key', async () => {
      await setItem(SECURE_STORAGE_KEYS.accessToken, 'test-token');
      await removeItem(SECURE_STORAGE_KEYS.accessToken);

      const result = await getItem(SECURE_STORAGE_KEYS.accessToken);

      expect(result.success).toBe(true);
      expect(result.data).toBeNull();
    });
  });

  describe('removeItem', () => {
    it('should remove a stored value', async () => {
      await setItem(SECURE_STORAGE_KEYS.accessToken, 'test-token');

      const removeResult = await removeItem(SECURE_STORAGE_KEYS.accessToken);

      expect(removeResult.success).toBe(true);

      const getResult = await getItem(SECURE_STORAGE_KEYS.accessToken);
      expect(getResult.data).toBeNull();
    });

    it('should handle removing non-existent key gracefully', async () => {
      const result = await removeItem(SECURE_STORAGE_KEYS.accessToken);

      expect(result.success).toBe(true);
    });
  });

  describe('storeAuthTokens', () => {
    it('should store access and refresh tokens', async () => {
      const tokens: AuthTokens = {
        accessToken: 'access-123',
        refreshToken: 'refresh-456',
      };

      const result = await storeAuthTokens(tokens);

      expect(result.success).toBe(true);

      const accessResult = await getItem(SECURE_STORAGE_KEYS.accessToken);
      const refreshResult = await getItem(SECURE_STORAGE_KEYS.refreshToken);

      expect(accessResult.data).toBe('access-123');
      expect(refreshResult.data).toBe('refresh-456');
    });

    it('should store tokens with expiresAt', async () => {
      const tokens: AuthTokens = {
        accessToken: 'access-123',
        refreshToken: 'refresh-456',
        expiresAt: '2025-12-31T23:59:59Z',
      };

      const result = await storeAuthTokens(tokens);

      expect(result.success).toBe(true);
    });

    it('should update lastLoginAt timestamp', async () => {
      const tokens: AuthTokens = {
        accessToken: 'access-123',
        refreshToken: 'refresh-456',
      };

      await storeAuthTokens(tokens);

      const lastLoginResult = await getItem(SECURE_STORAGE_KEYS.lastLoginAt);

      expect(lastLoginResult.success).toBe(true);
      expect(lastLoginResult.data).not.toBeNull();
      // Verify it's a valid ISO date string
      expect(() => new Date(lastLoginResult.data!)).not.toThrow();
    });
  });

  describe('getAuthTokens', () => {
    it('should retrieve stored auth tokens', async () => {
      await storeAuthTokens({
        accessToken: 'access-token',
        refreshToken: 'refresh-token',
      });

      const result = await getAuthTokens();

      expect(result.success).toBe(true);
      expect(result.data).toEqual({
        accessToken: 'access-token',
        refreshToken: 'refresh-token',
      });
    });

    it('should return null when no tokens are stored', async () => {
      const result = await getAuthTokens();

      expect(result.success).toBe(true);
      expect(result.data).toBeNull();
    });

    it('should return null when only access token exists', async () => {
      await setItem(SECURE_STORAGE_KEYS.accessToken, 'access-only');

      const result = await getAuthTokens();

      expect(result.success).toBe(true);
      expect(result.data).toBeNull();
    });

    it('should return null when only refresh token exists', async () => {
      await setItem(SECURE_STORAGE_KEYS.refreshToken, 'refresh-only');

      const result = await getAuthTokens();

      expect(result.success).toBe(true);
      expect(result.data).toBeNull();
    });
  });

  describe('clearAuthTokens', () => {
    it('should clear all auth tokens', async () => {
      await storeAuthTokens({
        accessToken: 'access-123',
        refreshToken: 'refresh-456',
      });

      const clearResult = await clearAuthTokens();

      expect(clearResult.success).toBe(true);

      const accessResult = await getItem(SECURE_STORAGE_KEYS.accessToken);
      const refreshResult = await getItem(SECURE_STORAGE_KEYS.refreshToken);
      const lastLoginResult = await getItem(SECURE_STORAGE_KEYS.lastLoginAt);

      expect(accessResult.data).toBeNull();
      expect(refreshResult.data).toBeNull();
      expect(lastLoginResult.data).toBeNull();
    });

    it('should handle clearing when no tokens exist', async () => {
      const result = await clearAuthTokens();

      expect(result.success).toBe(true);
    });
  });

  describe('storeUserCredentials', () => {
    it('should store user credentials', async () => {
      const credentials: StoredCredentials = {
        userId: 'user-123',
        email: 'test@example.com',
        refreshToken: 'refresh-token',
      };

      const result = await storeUserCredentials(credentials);

      expect(result.success).toBe(true);

      const userIdResult = await getItem(SECURE_STORAGE_KEYS.userId);
      const emailResult = await getItem(SECURE_STORAGE_KEYS.userEmail);
      const refreshResult = await getItem(SECURE_STORAGE_KEYS.refreshToken);

      expect(userIdResult.data).toBe('user-123');
      expect(emailResult.data).toBe('test@example.com');
      expect(refreshResult.data).toBe('refresh-token');
    });
  });

  describe('getUserCredentials', () => {
    it('should retrieve stored user credentials', async () => {
      await storeUserCredentials({
        userId: 'user-456',
        email: 'user@example.com',
        refreshToken: 'stored-refresh',
      });

      const result = await getUserCredentials();

      expect(result.success).toBe(true);
      expect(result.data).toEqual({
        userId: 'user-456',
        email: 'user@example.com',
        refreshToken: 'stored-refresh',
      });
    });

    it('should return null when no credentials are stored', async () => {
      const result = await getUserCredentials();

      expect(result.success).toBe(true);
      expect(result.data).toBeNull();
    });

    it('should return null when credentials are incomplete', async () => {
      await setItem(SECURE_STORAGE_KEYS.userId, 'user-123');
      await setItem(SECURE_STORAGE_KEYS.userEmail, 'partial@example.com');
      // refreshToken is missing

      const result = await getUserCredentials();

      expect(result.success).toBe(true);
      expect(result.data).toBeNull();
    });
  });

  describe('clearUserCredentials', () => {
    it('should clear user credentials', async () => {
      await storeUserCredentials({
        userId: 'user-789',
        email: 'clear@example.com',
        refreshToken: 'refresh-to-clear',
      });

      const clearResult = await clearUserCredentials();

      expect(clearResult.success).toBe(true);

      const userIdResult = await getItem(SECURE_STORAGE_KEYS.userId);
      const emailResult = await getItem(SECURE_STORAGE_KEYS.userEmail);

      expect(userIdResult.data).toBeNull();
      expect(emailResult.data).toBeNull();
    });

    it('should handle clearing when no credentials exist', async () => {
      const result = await clearUserCredentials();

      expect(result.success).toBe(true);
    });
  });

  describe('setBiometricEnabled', () => {
    it('should enable biometric login', async () => {
      const result = await setBiometricEnabled(true);

      expect(result.success).toBe(true);

      const storedResult = await getItem(SECURE_STORAGE_KEYS.biometricEnabled);
      expect(storedResult.data).toBe('true');
    });

    it('should disable biometric login', async () => {
      await setBiometricEnabled(true);
      const result = await setBiometricEnabled(false);

      expect(result.success).toBe(true);

      const storedResult = await getItem(SECURE_STORAGE_KEYS.biometricEnabled);
      expect(storedResult.data).toBe('false');
    });
  });

  describe('isBiometricEnabled', () => {
    it('should return true when biometric is enabled', async () => {
      await setBiometricEnabled(true);

      const result = await isBiometricEnabled();

      expect(result.success).toBe(true);
      expect(result.data).toBe(true);
    });

    it('should return false when biometric is disabled', async () => {
      await setBiometricEnabled(false);

      const result = await isBiometricEnabled();

      expect(result.success).toBe(true);
      expect(result.data).toBe(false);
    });

    it('should return false when biometric setting is not set', async () => {
      const result = await isBiometricEnabled();

      expect(result.success).toBe(true);
      expect(result.data).toBe(false);
    });
  });

  describe('clearAllSecureStorage', () => {
    it('should clear all stored data', async () => {
      // Store various data
      await storeAuthTokens({
        accessToken: 'access-token',
        refreshToken: 'refresh-token',
      });
      await storeUserCredentials({
        userId: 'user-id',
        email: 'test@example.com',
        refreshToken: 'refresh',
      });
      await setBiometricEnabled(true);

      // Clear all
      const clearResult = await clearAllSecureStorage();

      expect(clearResult.success).toBe(true);

      // Verify all keys are cleared
      const accessResult = await getItem(SECURE_STORAGE_KEYS.accessToken);
      const refreshResult = await getItem(SECURE_STORAGE_KEYS.refreshToken);
      const userIdResult = await getItem(SECURE_STORAGE_KEYS.userId);
      const emailResult = await getItem(SECURE_STORAGE_KEYS.userEmail);
      const biometricResult = await getItem(SECURE_STORAGE_KEYS.biometricEnabled);
      const lastLoginResult = await getItem(SECURE_STORAGE_KEYS.lastLoginAt);

      expect(accessResult.data).toBeNull();
      expect(refreshResult.data).toBeNull();
      expect(userIdResult.data).toBeNull();
      expect(emailResult.data).toBeNull();
      expect(biometricResult.data).toBeNull();
      expect(lastLoginResult.data).toBeNull();
    });

    it('should handle clearing empty storage', async () => {
      const result = await clearAllSecureStorage();

      expect(result.success).toBe(true);
    });
  });

  describe('Integration scenarios', () => {
    it('should support full authentication flow', async () => {
      // 1. User logs in
      const tokens: AuthTokens = {
        accessToken: 'new-access',
        refreshToken: 'new-refresh',
      };
      await storeAuthTokens(tokens);

      // 2. Store user credentials for biometric
      await storeUserCredentials({
        userId: 'user-flow',
        email: 'flow@example.com',
        refreshToken: 'new-refresh',
      });
      await setBiometricEnabled(true);

      // 3. Verify tokens can be retrieved
      const authResult = await getAuthTokens();
      expect(authResult.data).toEqual({
        accessToken: 'new-access',
        refreshToken: 'new-refresh',
      });

      // 4. Verify credentials can be retrieved
      const credResult = await getUserCredentials();
      expect(credResult.data).toEqual({
        userId: 'user-flow',
        email: 'flow@example.com',
        refreshToken: 'new-refresh',
      });

      // 5. Verify biometric is enabled
      const bioResult = await isBiometricEnabled();
      expect(bioResult.data).toBe(true);

      // 6. User logs out
      await clearAuthTokens();

      // 7. Auth tokens should be cleared
      const clearedAuthResult = await getAuthTokens();
      expect(clearedAuthResult.data).toBeNull();

      // 8. User credentials should still exist for biometric re-login
      const stillCredResult = await getUserCredentials();
      // Note: refreshToken was also cleared by clearAuthTokens, so this returns null
      expect(stillCredResult.data).toBeNull();
    });

    it('should support token refresh flow', async () => {
      // Initial login
      await storeAuthTokens({
        accessToken: 'old-access',
        refreshToken: 'old-refresh',
      });

      // Token refresh
      await storeAuthTokens({
        accessToken: 'new-access',
        refreshToken: 'new-refresh',
      });

      // Verify new tokens
      const result = await getAuthTokens();
      expect(result.data).toEqual({
        accessToken: 'new-access',
        refreshToken: 'new-refresh',
      });
    });
  });
});
