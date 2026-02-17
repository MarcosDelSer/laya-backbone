/**
 * LAYA Parent App - Secure Storage Tests
 *
 * Comprehensive test suite for secure storage utility
 *
 * Tests cover:
 * - Basic storage operations (set, get, remove)
 * - Object serialization/deserialization
 * - Storage options (biometrics, access control)
 * - Error handling and edge cases
 * - Key existence checks
 * - Bulk operations
 */

import {
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
} from '../secureStorage';
import type {SecureStorageOptions} from '../secureStorage';

describe('SecureStorage', () => {
  // Clear storage before each test for isolation
  beforeEach(async () => {
    await clearAll();
  });

  // Clean up after all tests
  afterAll(async () => {
    await clearAll();
  });

  describe('STORAGE_KEYS', () => {
    it('should have correct predefined storage keys', () => {
      expect(STORAGE_KEYS.ACCESS_TOKEN).toBe('laya_parent_secure_access_token');
      expect(STORAGE_KEYS.REFRESH_TOKEN).toBe('laya_parent_secure_refresh_token');
      expect(STORAGE_KEYS.USER_ID).toBe('laya_parent_secure_user_id');
      expect(STORAGE_KEYS.USER_EMAIL).toBe('laya_parent_secure_user_email');
      expect(STORAGE_KEYS.BIOMETRIC_ENABLED).toBe('laya_parent_secure_biometric_enabled');
      expect(STORAGE_KEYS.LAST_LOGIN_AT).toBe('laya_parent_secure_last_login_at');
    });

    it('should have namespace prefix for all keys', () => {
      const allKeys = Object.values(STORAGE_KEYS);
      allKeys.forEach((key) => {
        expect(key).toMatch(/^laya_parent_secure_/);
      });
    });
  });

  describe('isSecureStorageAvailable', () => {
    it('should return boolean indicating storage availability', async () => {
      const available = await isSecureStorageAvailable();
      expect(typeof available).toBe('boolean');
    });
  });

  describe('setItem', () => {
    it('should successfully store a string value', async () => {
      const result = await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'test-token-123');

      expect(result.success).toBe(true);
      expect(result.error).toBeUndefined();
    });

    it('should store values with custom keys', async () => {
      const result = await setItem('custom_key', 'custom_value');

      expect(result.success).toBe(true);
    });

    it('should overwrite existing values', async () => {
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'first-token');
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'second-token');

      const getResult = await getItem(STORAGE_KEYS.ACCESS_TOKEN);

      expect(getResult.success).toBe(true);
      expect(getResult.data).toBe('second-token');
    });

    it('should handle empty string values', async () => {
      const result = await setItem(STORAGE_KEYS.ACCESS_TOKEN, '');

      expect(result.success).toBe(true);

      const getResult = await getItem(STORAGE_KEYS.ACCESS_TOKEN);
      expect(getResult.data).toBe('');
    });

    it('should handle long string values', async () => {
      const longValue = 'a'.repeat(10000);
      const result = await setItem('long_value', longValue);

      expect(result.success).toBe(true);

      const getResult = await getItem('long_value');
      expect(getResult.data).toBe(longValue);
    });

    it('should accept storage options', async () => {
      const options: SecureStorageOptions = {
        requireBiometrics: false,
        accessControl: 'AccessibleWhenUnlocked',
      };

      const result = await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'token', options);

      expect(result.success).toBe(true);
    });

    it('should accept biometric requirement option', async () => {
      const options: SecureStorageOptions = {
        requireBiometrics: true,
      };

      const result = await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'token', options);

      // Should succeed or fail based on environment
      expect(typeof result.success).toBe('boolean');
    });
  });

  describe('getItem', () => {
    it('should retrieve a stored value', async () => {
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'stored-token');

      const result = await getItem(STORAGE_KEYS.ACCESS_TOKEN);

      expect(result.success).toBe(true);
      expect(result.data).toBe('stored-token');
    });

    it('should return null for non-existent key', async () => {
      const result = await getItem('non_existent_key');

      expect(result.success).toBe(true);
      expect(result.data).toBeNull();
    });

    it('should return null after removing a key', async () => {
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'test-token');
      await removeItem(STORAGE_KEYS.ACCESS_TOKEN);

      const result = await getItem(STORAGE_KEYS.ACCESS_TOKEN);

      expect(result.success).toBe(true);
      expect(result.data).toBeNull();
    });

    it('should retrieve empty string values', async () => {
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, '');

      const result = await getItem(STORAGE_KEYS.ACCESS_TOKEN);

      expect(result.success).toBe(true);
      expect(result.data).toBe('');
    });

    it('should retrieve long string values', async () => {
      const longValue = 'b'.repeat(10000);
      await setItem('long_key', longValue);

      const result = await getItem('long_key');

      expect(result.success).toBe(true);
      expect(result.data).toBe(longValue);
    });
  });

  describe('removeItem', () => {
    it('should remove a stored value', async () => {
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'test-token');

      const removeResult = await removeItem(STORAGE_KEYS.ACCESS_TOKEN);

      expect(removeResult.success).toBe(true);

      const getResult = await getItem(STORAGE_KEYS.ACCESS_TOKEN);
      expect(getResult.data).toBeNull();
    });

    it('should handle removing non-existent key gracefully', async () => {
      const result = await removeItem('non_existent_key');

      expect(result.success).toBe(true);
    });

    it('should not affect other keys when removing one', async () => {
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'token1');
      await setItem(STORAGE_KEYS.REFRESH_TOKEN, 'token2');

      await removeItem(STORAGE_KEYS.ACCESS_TOKEN);

      const accessResult = await getItem(STORAGE_KEYS.ACCESS_TOKEN);
      const refreshResult = await getItem(STORAGE_KEYS.REFRESH_TOKEN);

      expect(accessResult.data).toBeNull();
      expect(refreshResult.data).toBe('token2');
    });
  });

  describe('setObject', () => {
    it('should store a simple object', async () => {
      const obj = { name: 'John', age: 30 };
      const result = await setObject('user_object', obj);

      expect(result.success).toBe(true);
    });

    it('should store a complex nested object', async () => {
      const obj = {
        user: {
          id: '123',
          profile: {
            name: 'Alice',
            settings: {
              theme: 'dark',
              notifications: true,
            },
          },
        },
      };

      const result = await setObject('complex_object', obj);

      expect(result.success).toBe(true);
    });

    it('should store an array', async () => {
      const arr = [1, 2, 3, 'four', { five: 5 }];
      const result = await setObject('array_data', arr);

      expect(result.success).toBe(true);
    });

    it('should store null value', async () => {
      const result = await setObject('null_value', null);

      expect(result.success).toBe(true);
    });

    it('should store boolean values', async () => {
      const result = await setObject('bool_value', true);

      expect(result.success).toBe(true);
    });

    it('should store number values', async () => {
      const result = await setObject('number_value', 42);

      expect(result.success).toBe(true);
    });

    it('should accept storage options', async () => {
      const obj = { secure: true };
      const options: SecureStorageOptions = {
        accessControl: 'AccessibleWhenUnlocked',
      };

      const result = await setObject('secure_object', obj, options);

      expect(result.success).toBe(true);
    });
  });

  describe('getObject', () => {
    it('should retrieve a stored object', async () => {
      const obj = { name: 'Bob', email: 'bob@example.com' };
      await setObject('user_data', obj);

      const result = await getObject('user_data');

      expect(result.success).toBe(true);
      expect(result.data).toEqual(obj);
    });

    it('should retrieve a complex nested object', async () => {
      const obj = {
        settings: {
          preferences: {
            theme: 'light',
            language: 'en',
          },
        },
      };
      await setObject('settings', obj);

      const result = await getObject('settings');

      expect(result.success).toBe(true);
      expect(result.data).toEqual(obj);
    });

    it('should retrieve an array', async () => {
      const arr = ['a', 'b', 'c'];
      await setObject('array_key', arr);

      const result = await getObject('array_key');

      expect(result.success).toBe(true);
      expect(result.data).toEqual(arr);
    });

    it('should return null for non-existent key', async () => {
      const result = await getObject('non_existent_object');

      expect(result.success).toBe(true);
      expect(result.data).toBeNull();
    });

    it('should retrieve null value', async () => {
      await setObject('null_key', null);

      const result = await getObject('null_key');

      expect(result.success).toBe(true);
      expect(result.data).toBeNull();
    });

    it('should retrieve boolean values', async () => {
      await setObject('bool_key', false);

      const result = await getObject('bool_key');

      expect(result.success).toBe(true);
      expect(result.data).toBe(false);
    });

    it('should retrieve number values', async () => {
      await setObject('number_key', 123);

      const result = await getObject('number_key');

      expect(result.success).toBe(true);
      expect(result.data).toBe(123);
    });

    it('should handle invalid JSON gracefully', async () => {
      // Manually set invalid JSON
      await setItem('invalid_json', '{invalid json}');

      const result = await getObject('invalid_json');

      expect(result.success).toBe(false);
      expect(result.error?.code).toBe('DESERIALIZATION_ERROR');
    });
  });

  describe('hasItem', () => {
    it('should return true for existing key', async () => {
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'token');

      const exists = await hasItem(STORAGE_KEYS.ACCESS_TOKEN);

      expect(exists).toBe(true);
    });

    it('should return false for non-existent key', async () => {
      const exists = await hasItem('non_existent_key');

      expect(exists).toBe(false);
    });

    it('should return false after removing a key', async () => {
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'token');
      await removeItem(STORAGE_KEYS.ACCESS_TOKEN);

      const exists = await hasItem(STORAGE_KEYS.ACCESS_TOKEN);

      expect(exists).toBe(false);
    });

    it('should return true for empty string values', async () => {
      await setItem('empty_key', '');

      const exists = await hasItem('empty_key');

      expect(exists).toBe(true);
    });
  });

  describe('clearAll', () => {
    it('should clear all stored data', async () => {
      // Store multiple items
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'access-token');
      await setItem(STORAGE_KEYS.REFRESH_TOKEN, 'refresh-token');
      await setItem(STORAGE_KEYS.USER_ID, 'user-123');
      await setItem(STORAGE_KEYS.USER_EMAIL, 'test@example.com');

      // Clear all
      const clearResult = await clearAll();

      expect(clearResult.success).toBe(true);

      // Verify all keys are cleared
      const accessResult = await getItem(STORAGE_KEYS.ACCESS_TOKEN);
      const refreshResult = await getItem(STORAGE_KEYS.REFRESH_TOKEN);
      const userIdResult = await getItem(STORAGE_KEYS.USER_ID);
      const emailResult = await getItem(STORAGE_KEYS.USER_EMAIL);

      expect(accessResult.data).toBeNull();
      expect(refreshResult.data).toBeNull();
      expect(userIdResult.data).toBeNull();
      expect(emailResult.data).toBeNull();
    });

    it('should handle clearing empty storage', async () => {
      const result = await clearAll();

      expect(result.success).toBe(true);
    });

    it('should clear object data', async () => {
      await setObject('obj1', { data: 'value1' });
      await setObject('obj2', { data: 'value2' });

      await clearAll();

      const obj1Result = await getObject('obj1');
      const obj2Result = await getObject('obj2');

      expect(obj1Result.data).toBeNull();
      expect(obj2Result.data).toBeNull();
    });
  });

  describe('getAllKeys', () => {
    it('should return an array of keys', () => {
      const keys = getAllKeys();

      expect(Array.isArray(keys)).toBe(true);
    });

    it('should include predefined storage keys', () => {
      const keys = getAllKeys();

      expect(keys).toContain(STORAGE_KEYS.ACCESS_TOKEN);
      expect(keys).toContain(STORAGE_KEYS.REFRESH_TOKEN);
      expect(keys).toContain(STORAGE_KEYS.USER_ID);
      expect(keys).toContain(STORAGE_KEYS.USER_EMAIL);
    });
  });

  describe('Integration scenarios', () => {
    it('should support full authentication flow', async () => {
      // 1. Store access token
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'access-token-xyz');

      // 2. Store refresh token
      await setItem(STORAGE_KEYS.REFRESH_TOKEN, 'refresh-token-abc');

      // 3. Store user data as object
      await setObject(STORAGE_KEYS.USER_ID, { id: '123', email: 'user@example.com' });

      // 4. Verify tokens can be retrieved
      const accessResult = await getItem(STORAGE_KEYS.ACCESS_TOKEN);
      const refreshResult = await getItem(STORAGE_KEYS.REFRESH_TOKEN);
      const userResult = await getObject(STORAGE_KEYS.USER_ID);

      expect(accessResult.data).toBe('access-token-xyz');
      expect(refreshResult.data).toBe('refresh-token-abc');
      expect(userResult.data).toEqual({ id: '123', email: 'user@example.com' });

      // 5. Clear on logout
      await clearAll();

      // 6. Verify all cleared
      const clearedAccess = await getItem(STORAGE_KEYS.ACCESS_TOKEN);
      const clearedRefresh = await getItem(STORAGE_KEYS.REFRESH_TOKEN);

      expect(clearedAccess.data).toBeNull();
      expect(clearedRefresh.data).toBeNull();
    });

    it('should support token refresh flow', async () => {
      // Initial token storage
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'old-access');
      await setItem(STORAGE_KEYS.REFRESH_TOKEN, 'old-refresh');

      // Token refresh - update tokens
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'new-access');
      await setItem(STORAGE_KEYS.REFRESH_TOKEN, 'new-refresh');

      // Verify new tokens
      const accessResult = await getItem(STORAGE_KEYS.ACCESS_TOKEN);
      const refreshResult = await getItem(STORAGE_KEYS.REFRESH_TOKEN);

      expect(accessResult.data).toBe('new-access');
      expect(refreshResult.data).toBe('new-refresh');
    });

    it('should support storing complex user profile', async () => {
      const userProfile = {
        id: 'user-456',
        email: 'alice@example.com',
        firstName: 'Alice',
        lastName: 'Smith',
        preferences: {
          theme: 'dark',
          notifications: {
            email: true,
            push: true,
            sms: false,
          },
        },
        children: [
          { id: 'child-1', name: 'Bob' },
          { id: 'child-2', name: 'Carol' },
        ],
      };

      await setObject('user_profile', userProfile);

      const result = await getObject('user_profile');

      expect(result.success).toBe(true);
      expect(result.data).toEqual(userProfile);
    });

    it('should handle partial storage failures gracefully', async () => {
      // Store some items successfully
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'token1');
      await setItem(STORAGE_KEYS.REFRESH_TOKEN, 'token2');

      // Remove one
      await removeItem(STORAGE_KEYS.ACCESS_TOKEN);

      // Verify partial state
      const accessResult = await getItem(STORAGE_KEYS.ACCESS_TOKEN);
      const refreshResult = await getItem(STORAGE_KEYS.REFRESH_TOKEN);

      expect(accessResult.data).toBeNull();
      expect(refreshResult.data).toBe('token2');
    });

    it('should support checking existence before retrieval', async () => {
      // Check before storing
      const existsBefore = await hasItem(STORAGE_KEYS.ACCESS_TOKEN);
      expect(existsBefore).toBe(false);

      // Store token
      await setItem(STORAGE_KEYS.ACCESS_TOKEN, 'token');

      // Check after storing
      const existsAfter = await hasItem(STORAGE_KEYS.ACCESS_TOKEN);
      expect(existsAfter).toBe(true);

      // Retrieve only if exists
      if (existsAfter) {
        const result = await getItem(STORAGE_KEYS.ACCESS_TOKEN);
        expect(result.data).toBe('token');
      }
    });
  });

  describe('Edge cases', () => {
    it('should handle rapid successive writes', async () => {
      // Rapidly update the same key
      await setItem('rapid_key', 'value1');
      await setItem('rapid_key', 'value2');
      await setItem('rapid_key', 'value3');

      const result = await getItem('rapid_key');

      expect(result.data).toBe('value3');
    });

    it('should handle concurrent operations', async () => {
      // Concurrent writes to different keys
      await Promise.all([
        setItem('key1', 'value1'),
        setItem('key2', 'value2'),
        setItem('key3', 'value3'),
      ]);

      // Concurrent reads
      const [result1, result2, result3] = await Promise.all([
        getItem('key1'),
        getItem('key2'),
        getItem('key3'),
      ]);

      expect(result1.data).toBe('value1');
      expect(result2.data).toBe('value2');
      expect(result3.data).toBe('value3');
    });

    it('should handle special characters in keys', async () => {
      const specialKey = 'key_with-special.chars@123';
      await setItem(specialKey, 'special-value');

      const result = await getItem(specialKey);

      expect(result.data).toBe('special-value');
    });

    it('should handle special characters in values', async () => {
      const specialValue = 'value with\nnewlines\ttabs and "quotes"';
      await setItem('special_value_key', specialValue);

      const result = await getItem('special_value_key');

      expect(result.data).toBe(specialValue);
    });

    it('should handle unicode characters', async () => {
      const unicodeValue = '👨‍👩‍👧‍👦 测试 עברית العربية';
      await setItem('unicode_key', unicodeValue);

      const result = await getItem('unicode_key');

      expect(result.data).toBe(unicodeValue);
    });

    it('should preserve data types in objects', async () => {
      const mixedTypes = {
        string: 'text',
        number: 42,
        boolean: true,
        null: null,
        array: [1, 2, 3],
        nested: { key: 'value' },
      };

      await setObject('mixed_types', mixedTypes);

      const result = await getObject('mixed_types');

      expect(result.data).toEqual(mixedTypes);
    });
  });
});
