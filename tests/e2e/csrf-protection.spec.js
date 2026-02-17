/**
 * E2E Test: CSRF Protection Flow
 *
 * Tests the complete end-to-end CSRF protection workflow:
 * 1. Fetch CSRF token from ai-service
 * 2. Submit POST request with valid CSRF token
 * 3. Verify backend accepts request with valid token
 * 4. Submit POST request without CSRF token
 * 5. Verify backend rejects with 403 Forbidden
 *
 * Requirements:
 * - ai-service running at http://localhost:8000
 * - CSRF protection middleware enabled
 *
 * Run with:
 * - npx playwright test tests/e2e/csrf-protection.spec.js
 */

const { test, expect } = require('@playwright/test');

// Environment-configurable constants
const AI_SERVICE_URL = process.env.AI_SERVICE_URL || 'http://localhost:8000';
const CSRF_TOKEN_ENDPOINT = `${AI_SERVICE_URL}/api/v1/csrf-token`;
const TEST_CSRF_ENDPOINT = `${AI_SERVICE_URL}/api/v1/test-csrf`;

test.describe('CSRF Protection Flow', () => {
  test('should fetch CSRF token successfully', async ({ request }) => {
    // Step 1: Fetch CSRF token via GET /api/v1/csrf-token
    const response = await request.get(CSRF_TOKEN_ENDPOINT);

    // Verify response status
    expect(response.status()).toBe(200);

    // Verify response body contains CSRF token
    const data = await response.json();
    expect(data).toHaveProperty('csrf_token');
    expect(data.csrf_token).toBeTruthy();
    expect(typeof data.csrf_token).toBe('string');
    expect(data.csrf_token.length).toBeGreaterThan(0);

    // Verify token metadata
    expect(data).toHaveProperty('expires_in_minutes');
    expect(typeof data.expires_in_minutes).toBe('number');
    expect(data.expires_in_minutes).toBeGreaterThan(0);
  });

  test('should accept POST request with valid CSRF token', async ({ request }) => {
    // Step 1: Fetch CSRF token
    const tokenResponse = await request.get(CSRF_TOKEN_ENDPOINT);
    expect(tokenResponse.status()).toBe(200);

    const tokenData = await tokenResponse.json();
    const csrfToken = tokenData.csrf_token;

    // Step 2: Submit POST request with CSRF token in X-CSRF-Token header
    const testData = {
      test: 'data',
      message: 'Testing CSRF protection',
    };

    const response = await request.post(TEST_CSRF_ENDPOINT, {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      data: testData,
    });

    // Step 3: Verify backend validates successfully
    expect(response.status()).toBe(200);

    const responseData = await response.json();
    expect(responseData).toHaveProperty('message');
    expect(responseData.message).toContain('CSRF validation passed');
    expect(responseData).toHaveProperty('data');
    expect(responseData.data).toEqual(testData);
  });

  test('should reject POST request without CSRF token', async ({ request }) => {
    // Step 1: Submit POST request without CSRF token
    const testData = {
      test: 'data',
      message: 'Testing CSRF protection without token',
    };

    const response = await request.post(TEST_CSRF_ENDPOINT, {
      headers: {
        'Content-Type': 'application/json',
      },
      data: testData,
    });

    // Step 2: Verify backend rejects with 403 Forbidden
    expect(response.status()).toBe(403);

    const responseData = await response.json();
    expect(responseData).toHaveProperty('detail');
    expect(responseData.detail).toContain('CSRF token missing');
  });

  test('should reject POST request with invalid CSRF token', async ({ request }) => {
    // Step 1: Submit POST request with invalid CSRF token
    const invalidToken = 'invalid.csrf.token';
    const testData = {
      test: 'data',
      message: 'Testing CSRF protection with invalid token',
    };

    const response = await request.post(TEST_CSRF_ENDPOINT, {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': invalidToken,
      },
      data: testData,
    });

    // Step 2: Verify backend rejects with 403 Forbidden
    expect(response.status()).toBe(403);

    const responseData = await response.json();
    expect(responseData).toHaveProperty('detail');
    expect(responseData.detail).toContain('CSRF token invalid or expired');
  });

  test('should reject POST request with expired CSRF token', async ({ request }) => {
    // Note: This test uses a manually created expired token
    // In a real scenario, you would need to wait for token expiration
    // or use a token with a very short expiration time

    // Create a malformed token that will fail validation (simulates expired/invalid)
    const expiredToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJub25jZSI6InRlc3QiLCJleHAiOjAsInR5cGUiOiJjc3JmIn0.invalid';

    const testData = {
      test: 'data',
      message: 'Testing CSRF protection with expired token',
    };

    const response = await request.post(TEST_CSRF_ENDPOINT, {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': expiredToken,
      },
      data: testData,
    });

    // Verify backend rejects with 403 Forbidden
    expect(response.status()).toBe(403);

    const responseData = await response.json();
    expect(responseData).toHaveProperty('detail');
    expect(responseData.detail).toContain('CSRF token invalid or expired');
  });

  test('complete CSRF protection workflow', async ({ request }) => {
    // This is a comprehensive test that combines all steps from the verification requirements

    // Step 1: Fetch CSRF token via GET /api/v1/csrf-token
    console.log('Step 1: Fetching CSRF token from ai-service...');
    const tokenResponse = await request.get(CSRF_TOKEN_ENDPOINT);
    expect(tokenResponse.status()).toBe(200);

    const tokenData = await tokenResponse.json();
    expect(tokenData).toHaveProperty('csrf_token');
    const csrfToken = tokenData.csrf_token;
    console.log(`Step 1 ✓: CSRF token fetched successfully (length: ${csrfToken.length})`);

    // Step 2 & 3: Submit POST request with CSRF token and verify backend validates successfully
    console.log('Step 2: Submitting POST request with CSRF token in X-CSRF-Token header...');
    const validRequest = await request.post(TEST_CSRF_ENDPOINT, {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': csrfToken,
      },
      data: { message: 'Valid CSRF token test' },
    });

    expect(validRequest.status()).toBe(200);
    const validResponse = await validRequest.json();
    expect(validResponse.message).toContain('CSRF validation passed');
    console.log('Step 2-3 ✓: Backend validated CSRF token successfully');

    // Step 4 & 5: Submit POST without CSRF token and verify backend rejects with 403
    console.log('Step 4: Submitting POST request without CSRF token...');
    const invalidRequest = await request.post(TEST_CSRF_ENDPOINT, {
      headers: {
        'Content-Type': 'application/json',
      },
      data: { message: 'No CSRF token test' },
    });

    expect(invalidRequest.status()).toBe(403);
    const invalidResponse = await invalidRequest.json();
    expect(invalidResponse.detail).toContain('CSRF token missing');
    console.log('Step 4-5 ✓: Backend rejected request without CSRF token (403 Forbidden)');

    console.log('\n✅ Complete CSRF protection workflow verified successfully!');
  });
});

test.describe('CSRF Protection - Edge Cases', () => {
  test('should reject POST with empty CSRF token', async ({ request }) => {
    const response = await request.post(TEST_CSRF_ENDPOINT, {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': '',
      },
      data: { test: 'data' },
    });

    expect(response.status()).toBe(403);
    const responseData = await response.json();
    expect(responseData.detail).toContain('CSRF token');
  });

  test('should reject POST with whitespace-only CSRF token', async ({ request }) => {
    const response = await request.post(TEST_CSRF_ENDPOINT, {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': '   ',
      },
      data: { test: 'data' },
    });

    expect(response.status()).toBe(403);
    const responseData = await response.json();
    expect(responseData.detail).toContain('CSRF token');
  });

  test('should reject POST with malformed JWT CSRF token', async ({ request }) => {
    const malformedTokens = [
      'not.a.jwt',
      'only-one-part',
      'two.parts',
      'header.payload.signature.extra',
      'a'.repeat(1000), // Very long string
    ];

    for (const token of malformedTokens) {
      const response = await request.post(TEST_CSRF_ENDPOINT, {
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': token,
        },
        data: { test: 'data' },
      });

      expect(response.status()).toBe(403);
      const responseData = await response.json();
      expect(responseData.detail).toContain('CSRF token invalid or expired');
    }
  });

  test('CSRF token should be reusable within expiration time', async ({ request }) => {
    // Fetch a CSRF token once
    const tokenResponse = await request.get(CSRF_TOKEN_ENDPOINT);
    const tokenData = await tokenResponse.json();
    const csrfToken = tokenData.csrf_token;

    // Use the same token for multiple requests
    for (let i = 0; i < 3; i++) {
      const response = await request.post(TEST_CSRF_ENDPOINT, {
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': csrfToken,
        },
        data: { request_number: i + 1 },
      });

      expect(response.status()).toBe(200);
      const responseData = await response.json();
      expect(responseData.message).toContain('CSRF validation passed');
    }
  });
});

test.describe('CSRF Protection - Integration with Frontend', () => {
  test('should handle CSRF token refresh scenario', async ({ request }) => {
    // Simulate a frontend scenario where a CSRF token is fetched, used, and refreshed

    // Initial token fetch
    const firstTokenResponse = await request.get(CSRF_TOKEN_ENDPOINT);
    const firstTokenData = await firstTokenResponse.json();
    const firstToken = firstTokenData.csrf_token;

    // Use first token
    const firstRequest = await request.post(TEST_CSRF_ENDPOINT, {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': firstToken,
      },
      data: { sequence: 1 },
    });
    expect(firstRequest.status()).toBe(200);

    // Fetch a new token (simulating token refresh)
    const secondTokenResponse = await request.get(CSRF_TOKEN_ENDPOINT);
    const secondTokenData = await secondTokenResponse.json();
    const secondToken = secondTokenData.csrf_token;

    // Verify tokens are different (each generated token is unique)
    expect(firstToken).not.toBe(secondToken);

    // Use second token
    const secondRequest = await request.post(TEST_CSRF_ENDPOINT, {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': secondToken,
      },
      data: { sequence: 2 },
    });
    expect(secondRequest.status()).toBe(200);

    // Verify first token is still valid (tokens don't expire immediately)
    const thirdRequest = await request.post(TEST_CSRF_ENDPOINT, {
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': firstToken,
      },
      data: { sequence: 3 },
    });
    expect(thirdRequest.status()).toBe(200);
  });
});
