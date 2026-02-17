/**
 * Tests for type-safe API client with CSRF protection
 */

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { z } from 'zod';
import {
  TypeSafeApiClient,
  apiClient,
  isApiError,
  createApiClient,
  type ApiClientConfig,
  type ValidatedResponse,
} from '../client';
import { ApiError } from '../../api';
import * as csrf from '../../security/csrf';

// ============================================================================
// Mock Setup
// ============================================================================

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch as any;

// Mock CSRF functions
vi.mock('../../security/csrf', async () => {
  const actual = await vi.importActual('../../security/csrf');
  return {
    ...actual,
    fetchWithCSRF: vi.fn(),
    getValidCSRFToken: vi.fn(),
    initCSRFProtection: vi.fn(),
    clearCSRFToken: vi.fn(),
    requiresCSRFProtection: vi.fn(),
  };
});

// ============================================================================
// Test Schemas
// ============================================================================

const userSchema = z.object({
  id: z.string(),
  email: z.string().email(),
  role: z.enum(['parent', 'teacher', 'admin']),
  firstName: z.string().optional(),
  lastName: z.string().optional(),
});

type User = z.infer<typeof userSchema>;

const paginatedUsersSchema = z.object({
  items: z.array(userSchema),
  total: z.number().int().min(0),
  skip: z.number().int().min(0),
  limit: z.number().int().min(1),
});

// ============================================================================
// Test Helpers
// ============================================================================

/**
 * Create a mock Response object
 */
function createMockResponse(
  data: unknown,
  options: {
    status?: number;
    statusText?: string;
    headers?: Record<string, string>;
  } = {}
): Response {
  const {
    status = 200,
    statusText = 'OK',
    headers = { 'content-type': 'application/json' },
  } = options;

  return {
    ok: status >= 200 && status < 300,
    status,
    statusText,
    headers: new Headers(headers),
    json: async () => data,
    text: async () => JSON.stringify(data),
  } as Response;
}

/**
 * Create a mock error Response
 */
function createMockErrorResponse(
  status: number,
  detail: string,
  statusText: string = 'Error'
): Response {
  return createMockResponse(
    { detail },
    { status, statusText, headers: { 'content-type': 'application/json' } }
  );
}

// ============================================================================
// Tests
// ============================================================================

describe('TypeSafeApiClient', () => {
  let client: TypeSafeApiClient;

  beforeEach(() => {
    // Reset all mocks
    vi.clearAllMocks();

    // Create fresh client for each test
    client = new TypeSafeApiClient({
      baseUrl: 'http://localhost:8000',
      enableCSRF: true,
      enableLogging: false,
    });

    // Setup default CSRF mocks
    vi.mocked(csrf.requiresCSRFProtection).mockReturnValue(true);
    vi.mocked(csrf.getValidCSRFToken).mockResolvedValue('mock-csrf-token');
    vi.mocked(csrf.fetchWithCSRF).mockImplementation(
      (url: string, options?: RequestInit) => fetch(url, options)
    );
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  // ============================================================================
  // Constructor and Configuration Tests
  // ============================================================================

  describe('Constructor', () => {
    it('should create client with default configuration', () => {
      const defaultClient = new TypeSafeApiClient();
      const config = defaultClient.getConfig();

      expect(config.baseUrl).toBe(process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000');
      expect(config.enableCSRF).toBe(true);
      expect(config.timeout).toBe(30000);
      expect(config.maxRetries).toBe(3);
    });

    it('should create client with custom configuration', () => {
      const customClient = new TypeSafeApiClient({
        baseUrl: 'https://api.example.com',
        timeout: 10000,
        maxRetries: 5,
        enableCSRF: false,
      });

      const config = customClient.getConfig();

      expect(config.baseUrl).toBe('https://api.example.com');
      expect(config.timeout).toBe(10000);
      expect(config.maxRetries).toBe(5);
      expect(config.enableCSRF).toBe(false);
    });

    it('should initialize CSRF protection when enabled', () => {
      new TypeSafeApiClient({
        enableCSRF: true,
        csrfConfig: { apiUrl: 'http://localhost:8000' },
      });

      expect(csrf.initCSRFProtection).toHaveBeenCalledWith({
        apiUrl: 'http://localhost:8000',
      });
    });

    it('should not initialize CSRF when disabled', () => {
      vi.clearAllMocks();

      new TypeSafeApiClient({
        enableCSRF: false,
      });

      expect(csrf.initCSRFProtection).not.toHaveBeenCalled();
    });

    it('should merge custom headers with defaults', () => {
      const customClient = new TypeSafeApiClient({
        defaultHeaders: {
          'X-Custom-Header': 'custom-value',
        },
      });

      const config = customClient.getConfig();

      expect(config.defaultHeaders).toMatchObject({
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Custom-Header': 'custom-value',
      });
    });
  });

  describe('Configuration Management', () => {
    it('should return current configuration', () => {
      const config = client.getConfig();

      expect(config).toHaveProperty('baseUrl');
      expect(config).toHaveProperty('timeout');
      expect(config).toHaveProperty('maxRetries');
      expect(config).toHaveProperty('enableCSRF');
    });

    it('should update configuration', () => {
      client.updateConfig({
        timeout: 60000,
        maxRetries: 5,
      });

      const config = client.getConfig();

      expect(config.timeout).toBe(60000);
      expect(config.maxRetries).toBe(5);
    });

    it('should re-initialize CSRF when config updated', () => {
      vi.clearAllMocks();

      client.updateConfig({
        csrfConfig: { apiUrl: 'http://new-url:8000' },
      });

      expect(csrf.initCSRFProtection).toHaveBeenCalledWith({
        apiUrl: 'http://new-url:8000',
      });
    });
  });

  // ============================================================================
  // HTTP Method Tests
  // ============================================================================

  describe('GET requests', () => {
    it('should make successful GET request without schema', async () => {
      const mockData = { message: 'Hello' };
      mockFetch.mockResolvedValue(createMockResponse(mockData));

      const response = await client.get('/api/hello');

      expect(response.data).toEqual(mockData);
      expect(response.status).toBe(200);
      expect(response.validated).toBe(false);
      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8000/api/hello',
        expect.objectContaining({
          method: 'GET',
          credentials: 'include',
        })
      );
    });

    it('should make successful GET request with schema validation', async () => {
      const mockUser: User = {
        id: '123',
        email: 'user@example.com',
        role: 'parent',
        firstName: 'John',
        lastName: 'Doe',
      };
      mockFetch.mockResolvedValue(createMockResponse(mockUser));

      const response = await client.get<User>('/api/users/123', {
        schema: userSchema,
      });

      expect(response.data).toEqual(mockUser);
      expect(response.validated).toBe(true);
    });

    it('should reject GET request with invalid schema', async () => {
      const mockInvalidUser = {
        id: '123',
        email: 'invalid-email', // Invalid email format
        role: 'invalid-role', // Invalid role
      };
      mockFetch.mockResolvedValue(createMockResponse(mockInvalidUser));

      await expect(
        client.get<User>('/api/users/123', { schema: userSchema })
      ).rejects.toThrow(ApiError);
    });

    it('should include query parameters in GET request', async () => {
      mockFetch.mockResolvedValue(createMockResponse({ items: [] }));

      await client.get('/api/users', {
        params: {
          role: 'parent',
          active: true,
          limit: 10,
        },
      });

      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8000/api/users?role=parent&active=true&limit=10',
        expect.any(Object)
      );
    });

    it('should handle 204 No Content response', async () => {
      mockFetch.mockResolvedValue(
        createMockResponse(null, { status: 204, statusText: 'No Content' })
      );

      const response = await client.get('/api/ping');

      expect(response.data).toBeUndefined();
      expect(response.status).toBe(204);
    });
  });

  describe('POST requests', () => {
    it('should make successful POST request with body', async () => {
      const requestBody = {
        email: 'newuser@example.com',
        role: 'parent',
      };
      const responseUser: User = {
        id: '456',
        ...requestBody,
      };

      vi.mocked(csrf.requiresCSRFProtection).mockReturnValue(true);
      mockFetch.mockResolvedValue(createMockResponse(responseUser));

      const response = await client.post<User>('/api/users', requestBody, {
        schema: userSchema,
      });

      expect(response.data).toEqual(responseUser);
      expect(response.validated).toBe(true);
      expect(csrf.fetchWithCSRF).toHaveBeenCalled();
    });

    it('should include CSRF token for POST requests', async () => {
      mockFetch.mockResolvedValue(createMockResponse({ success: true }));
      vi.mocked(csrf.requiresCSRFProtection).mockReturnValue(true);

      await client.post('/api/users', { email: 'test@example.com' });

      expect(csrf.fetchWithCSRF).toHaveBeenCalled();
      expect(csrf.requiresCSRFProtection).toHaveBeenCalledWith('/api/users', 'POST');
    });

    it('should skip CSRF token when skipCSRF is true', async () => {
      mockFetch.mockResolvedValue(createMockResponse({ success: true }));

      await client.post('/api/webhook', { data: 'test' }, {
        skipCSRF: true,
      });

      expect(csrf.fetchWithCSRF).not.toHaveBeenCalled();
      expect(mockFetch).toHaveBeenCalled();
    });

    it('should stringify request body as JSON', async () => {
      const requestBody = { name: 'Test', value: 123 };
      mockFetch.mockResolvedValue(createMockResponse({ success: true }));

      await client.post('/api/data', requestBody);

      expect(mockFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          body: JSON.stringify(requestBody),
        })
      );
    });
  });

  describe('PUT requests', () => {
    it('should make successful PUT request with CSRF', async () => {
      const updateData = { firstName: 'Jane' };
      const updatedUser: User = {
        id: '123',
        email: 'user@example.com',
        role: 'parent',
        firstName: 'Jane',
      };

      vi.mocked(csrf.requiresCSRFProtection).mockReturnValue(true);
      mockFetch.mockResolvedValue(createMockResponse(updatedUser));

      const response = await client.put<User>('/api/users/123', updateData, {
        schema: userSchema,
      });

      expect(response.data).toEqual(updatedUser);
      expect(csrf.fetchWithCSRF).toHaveBeenCalled();
    });
  });

  describe('PATCH requests', () => {
    it('should make successful PATCH request with CSRF', async () => {
      const patchData = { firstName: 'John' };
      const patchedUser: User = {
        id: '123',
        email: 'user@example.com',
        role: 'parent',
        firstName: 'John',
      };

      vi.mocked(csrf.requiresCSRFProtection).mockReturnValue(true);
      mockFetch.mockResolvedValue(createMockResponse(patchedUser));

      const response = await client.patch<User>('/api/users/123', patchData, {
        schema: userSchema,
      });

      expect(response.data).toEqual(patchedUser);
      expect(csrf.fetchWithCSRF).toHaveBeenCalled();
    });
  });

  describe('DELETE requests', () => {
    it('should make successful DELETE request with CSRF', async () => {
      vi.mocked(csrf.requiresCSRFProtection).mockReturnValue(true);
      mockFetch.mockResolvedValue(
        createMockResponse(null, { status: 204, statusText: 'No Content' })
      );

      const response = await client.delete('/api/users/123');

      expect(response.status).toBe(204);
      expect(csrf.fetchWithCSRF).toHaveBeenCalled();
    });
  });

  // ============================================================================
  // Convenience Method Tests
  // ============================================================================

  describe('getList', () => {
    it('should fetch paginated list with schema validation', async () => {
      const mockUsers: User[] = [
        { id: '1', email: 'user1@example.com', role: 'parent' },
        { id: '2', email: 'user2@example.com', role: 'teacher' },
      ];
      const paginatedResponse = {
        items: mockUsers,
        total: 100,
        skip: 0,
        limit: 20,
      };

      mockFetch.mockResolvedValue(createMockResponse(paginatedResponse));

      const response = await client.getList('/api/users', userSchema, {
        pagination: { skip: 0, limit: 20 },
      });

      expect(response.data.items).toEqual(mockUsers);
      expect(response.data.total).toBe(100);
      expect(response.validated).toBe(true);
    });

    it('should include pagination params in query string', async () => {
      mockFetch.mockResolvedValue(
        createMockResponse({
          items: [],
          total: 0,
          skip: 10,
          limit: 25,
        })
      );

      await client.getList('/api/users', userSchema, {
        pagination: { skip: 10, limit: 25 },
      });

      expect(mockFetch).toHaveBeenCalledWith(
        'http://localhost:8000/api/users?skip=10&limit=25',
        expect.any(Object)
      );
    });
  });

  describe('getArray', () => {
    it('should fetch array of items with schema validation', async () => {
      const mockUsers: User[] = [
        { id: '1', email: 'user1@example.com', role: 'parent' },
        { id: '2', email: 'user2@example.com', role: 'teacher' },
      ];

      mockFetch.mockResolvedValue(createMockResponse(mockUsers));

      const response = await client.getArray('/api/users/active', userSchema);

      expect(response.data).toEqual(mockUsers);
      expect(Array.isArray(response.data)).toBe(true);
      expect(response.validated).toBe(true);
    });

    it('should reject invalid array items', async () => {
      const mockInvalidUsers = [
        { id: '1', email: 'valid@example.com', role: 'parent' },
        { id: '2', email: 'invalid', role: 'invalid-role' },
      ];

      mockFetch.mockResolvedValue(createMockResponse(mockInvalidUsers));

      await expect(
        client.getArray('/api/users', userSchema)
      ).rejects.toThrow(ApiError);
    });
  });

  // ============================================================================
  // Error Handling Tests
  // ============================================================================

  describe('Error Handling', () => {
    it('should handle 401 Unauthorized error', async () => {
      mockFetch.mockResolvedValue(
        createMockErrorResponse(401, 'Unauthorized', 'Unauthorized')
      );

      await expect(client.get('/api/protected')).rejects.toThrow(ApiError);

      try {
        await client.get('/api/protected');
      } catch (error) {
        expect(isApiError(error)).toBe(true);
        if (isApiError(error)) {
          expect(error.isUnauthorized).toBe(true);
          expect(error.status).toBe(401);
        }
      }
    });

    it('should handle 403 Forbidden error', async () => {
      mockFetch.mockResolvedValue(
        createMockErrorResponse(403, 'Forbidden', 'Forbidden')
      );

      await expect(client.get('/api/admin')).rejects.toThrow(ApiError);

      try {
        await client.get('/api/admin');
      } catch (error) {
        if (isApiError(error)) {
          expect(error.isForbidden).toBe(true);
          expect(error.status).toBe(403);
        }
      }
    });

    it('should handle 404 Not Found error', async () => {
      mockFetch.mockResolvedValue(
        createMockErrorResponse(404, 'Not Found', 'Not Found')
      );

      await expect(client.get('/api/users/999')).rejects.toThrow(ApiError);

      try {
        await client.get('/api/users/999');
      } catch (error) {
        if (isApiError(error)) {
          expect(error.isNotFound).toBe(true);
          expect(error.status).toBe(404);
        }
      }
    });

    it('should handle 422 Validation Error', async () => {
      mockFetch.mockResolvedValue(
        createMockErrorResponse(422, 'Validation failed', 'Unprocessable Entity')
      );

      await expect(
        client.post('/api/users', { email: 'invalid' })
      ).rejects.toThrow(ApiError);

      try {
        await client.post('/api/users', { email: 'invalid' });
      } catch (error) {
        if (isApiError(error)) {
          expect(error.isValidationError).toBe(true);
          expect(error.status).toBe(422);
        }
      }
    });

    it('should handle 429 Rate Limit error', async () => {
      mockFetch.mockResolvedValue(
        createMockErrorResponse(429, 'Too many requests', 'Too Many Requests')
      );

      await expect(client.get('/api/data')).rejects.toThrow(ApiError);

      try {
        await client.get('/api/data');
      } catch (error) {
        if (isApiError(error)) {
          expect(error.status).toBe(429);
        }
      }
    });

    it('should handle 500 Server Error', async () => {
      mockFetch.mockResolvedValue(
        createMockErrorResponse(500, 'Internal server error', 'Internal Server Error')
      );

      await expect(client.get('/api/data')).rejects.toThrow(ApiError);

      try {
        await client.get('/api/data');
      } catch (error) {
        if (isApiError(error)) {
          expect(error.isServerError).toBe(true);
          expect(error.status).toBe(500);
        }
      }
    });

    it('should handle network errors', async () => {
      mockFetch.mockRejectedValue(new Error('Network error'));

      await expect(client.get('/api/data')).rejects.toThrow(ApiError);

      try {
        await client.get('/api/data');
      } catch (error) {
        if (isApiError(error)) {
          expect(error.isNetworkError).toBe(true);
        }
      }
    });

    it('should handle timeout errors', async () => {
      mockFetch.mockImplementation(
        () => new Promise(resolve => setTimeout(resolve, 60000))
      );

      await expect(
        client.get('/api/slow', { timeout: 100 })
      ).rejects.toThrow(ApiError);

      try {
        await client.get('/api/slow', { timeout: 100 });
      } catch (error) {
        if (isApiError(error)) {
          expect(error.isTimeout).toBe(true);
        }
      }
    });

    it('should parse error response body', async () => {
      const errorDetail = 'Invalid email format';
      mockFetch.mockResolvedValue(
        createMockErrorResponse(422, errorDetail, 'Unprocessable Entity')
      );

      try {
        await client.post('/api/users', { email: 'invalid' });
      } catch (error) {
        if (isApiError(error)) {
          expect(error.body?.detail).toBe(errorDetail);
        }
      }
    });

    it('should handle non-JSON error responses', async () => {
      mockFetch.mockResolvedValue({
        ok: false,
        status: 500,
        statusText: 'Internal Server Error',
        headers: new Headers({ 'content-type': 'text/plain' }),
        text: async () => 'Server error occurred',
        json: async () => {
          throw new Error('Not JSON');
        },
      } as Response);

      await expect(client.get('/api/data')).rejects.toThrow(ApiError);
    });
  });

  // ============================================================================
  // CSRF Token Management Tests
  // ============================================================================

  describe('CSRF Token Management', () => {
    it('should refresh CSRF token manually', async () => {
      vi.mocked(csrf.getValidCSRFToken).mockResolvedValue('new-csrf-token');

      const token = await client.refreshCSRFToken();

      expect(token).toBe('new-csrf-token');
      expect(csrf.getValidCSRFToken).toHaveBeenCalled();
    });

    it('should clear CSRF token', () => {
      client.clearCSRFToken();

      expect(csrf.clearCSRFToken).toHaveBeenCalled();
    });

    it('should use CSRF token for state-changing requests', async () => {
      vi.mocked(csrf.requiresCSRFProtection).mockReturnValue(true);
      mockFetch.mockResolvedValue(createMockResponse({ success: true }));

      await client.post('/api/users', { email: 'test@example.com' });

      expect(csrf.requiresCSRFProtection).toHaveBeenCalledWith('/api/users', 'POST');
      expect(csrf.fetchWithCSRF).toHaveBeenCalled();
    });

    it('should not use CSRF token for GET requests', async () => {
      vi.mocked(csrf.requiresCSRFProtection).mockReturnValue(false);
      mockFetch.mockResolvedValue(createMockResponse({ data: 'test' }));

      await client.get('/api/data');

      expect(csrf.fetchWithCSRF).not.toHaveBeenCalled();
      expect(mockFetch).toHaveBeenCalled();
    });

    it('should respect skipCSRF option', async () => {
      mockFetch.mockResolvedValue(createMockResponse({ success: true }));

      await client.post('/api/webhook', { data: 'test' }, {
        skipCSRF: true,
      });

      expect(csrf.fetchWithCSRF).not.toHaveBeenCalled();
    });
  });

  // ============================================================================
  // Utility Function Tests
  // ============================================================================

  describe('Utility Functions', () => {
    it('should identify ApiError instances', () => {
      const apiError = new ApiError('Test error', 500, 'Server Error');
      const regularError = new Error('Regular error');

      expect(isApiError(apiError)).toBe(true);
      expect(isApiError(regularError)).toBe(false);
      expect(isApiError('string')).toBe(false);
      expect(isApiError(null)).toBe(false);
      expect(isApiError(undefined)).toBe(false);
    });

    it('should create API client for specific service', () => {
      const gibbonClient = createApiClient('http://localhost:8080/gibbon', {
        enableCSRF: false,
        timeout: 15000,
      });

      const config = gibbonClient.getConfig();

      expect(config.baseUrl).toBe('http://localhost:8080/gibbon');
      expect(config.enableCSRF).toBe(false);
      expect(config.timeout).toBe(15000);
    });
  });

  // ============================================================================
  // Singleton Instance Tests
  // ============================================================================

  describe('Singleton Instance', () => {
    it('should export pre-configured apiClient', () => {
      expect(apiClient).toBeInstanceOf(TypeSafeApiClient);

      const config = apiClient.getConfig();

      expect(config.baseUrl).toBe(process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000');
      expect(config.enableCSRF).toBe(true);
    });
  });

  // ============================================================================
  // Request Header Tests
  // ============================================================================

  describe('Request Headers', () => {
    it('should include default headers', async () => {
      mockFetch.mockResolvedValue(createMockResponse({ data: 'test' }));

      await client.get('/api/data');

      expect(mockFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
            'Accept': 'application/json',
          }),
        })
      );
    });

    it('should include credentials for httpOnly cookies', async () => {
      mockFetch.mockResolvedValue(createMockResponse({ data: 'test' }));

      await client.get('/api/protected');

      expect(mockFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          credentials: 'include',
        })
      );
    });

    it('should merge custom headers with defaults', async () => {
      mockFetch.mockResolvedValue(createMockResponse({ data: 'test' }));

      await client.get('/api/data', {
        headers: {
          'X-Custom-Header': 'custom-value',
        },
      });

      expect(mockFetch).toHaveBeenCalledWith(
        expect.any(String),
        expect.objectContaining({
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Custom-Header': 'custom-value',
          }),
        })
      );
    });
  });

  // ============================================================================
  // Response Format Tests
  // ============================================================================

  describe('Response Formats', () => {
    it('should return ValidatedResponse format', async () => {
      const mockUser: User = {
        id: '123',
        email: 'user@example.com',
        role: 'parent',
      };
      mockFetch.mockResolvedValue(createMockResponse(mockUser));

      const response = await client.get<User>('/api/users/123', {
        schema: userSchema,
      });

      expect(response).toHaveProperty('data');
      expect(response).toHaveProperty('status');
      expect(response).toHaveProperty('headers');
      expect(response).toHaveProperty('validated');
      expect(response.validated).toBe(true);
    });

    it('should mark response as not validated when no schema provided', async () => {
      mockFetch.mockResolvedValue(createMockResponse({ data: 'test' }));

      const response = await client.get('/api/data');

      expect(response.validated).toBe(false);
    });

    it('should include response headers', async () => {
      const mockResponse = createMockResponse(
        { data: 'test' },
        {
          headers: {
            'content-type': 'application/json',
            'x-request-id': 'abc-123',
          },
        }
      );
      mockFetch.mockResolvedValue(mockResponse);

      const response = await client.get('/api/data');

      expect(response.headers).toBeInstanceOf(Headers);
      expect(response.headers.get('x-request-id')).toBe('abc-123');
    });
  });
});
