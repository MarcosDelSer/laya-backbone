/**
 * Tests for Parent Portal Health Check API endpoint
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { GET } from '@/app/api/health/route';
import { NextRequest } from 'next/server';

// Mock package.json
vi.mock('../package.json', () => ({
  default: {
    version: '0.1.0',
  },
}));

// Mock fetch globally
const originalFetch = global.fetch;

describe('Health Check API - GET /api/health', () => {
  beforeEach(() => {
    // Set up environment variables
    process.env.NEXT_PUBLIC_API_URL = 'http://localhost:8000';
    process.env.NEXT_PUBLIC_GIBBON_URL = 'http://localhost:8080/gibbon';
  });

  afterEach(() => {
    // Restore original fetch
    global.fetch = originalFetch;
    vi.clearAllMocks();
  });

  it('should return healthy status when all services are healthy', async () => {
    // Mock successful responses from both services
    global.fetch = vi.fn((url) => {
      if (typeof url === 'string' && url.includes('/api/v1/health')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'healthy',
            service: 'ai-service',
          }),
        } as Response);
      } else if (typeof url === 'string' && url.includes('/modules/System/health.php')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'healthy',
            service: 'gibbon',
          }),
        } as Response);
      }
      return Promise.reject(new Error('Unknown URL'));
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');
    const response = await GET(request);
    const data = await response.json();

    expect(response.status).toBe(200);
    expect(data.status).toBe('healthy');
    expect(data.service).toBe('parent-portal');
    expect(data.version).toBe('0.1.0');
    expect(data.checks.aiService.status).toBe('healthy');
    expect(data.checks.aiService.connected).toBe(true);
    expect(data.checks.gibbon.status).toBe('healthy');
    expect(data.checks.gibbon.connected).toBe(true);
    expect(data.timestamp).toBeDefined();
  });

  it('should return degraded status when AI service is degraded', async () => {
    global.fetch = vi.fn((url) => {
      if (typeof url === 'string' && url.includes('/api/v1/health')) {
        return Promise.resolve({
          ok: false,
          status: 500,
          statusText: 'Internal Server Error',
          json: async () => ({}),
        } as Response);
      } else if (typeof url === 'string' && url.includes('/modules/System/health.php')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'healthy',
            service: 'gibbon',
          }),
        } as Response);
      }
      return Promise.reject(new Error('Unknown URL'));
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');
    const response = await GET(request);
    const data = await response.json();

    expect(response.status).toBe(200);
    expect(data.status).toBe('degraded');
    expect(data.checks.aiService.status).toBe('degraded');
    expect(data.checks.aiService.connected).toBe(false);
    expect(data.checks.aiService.error).toContain('500');
    expect(data.checks.gibbon.status).toBe('healthy');
  });

  it('should return degraded status when Gibbon is degraded', async () => {
    global.fetch = vi.fn((url) => {
      if (typeof url === 'string' && url.includes('/api/v1/health')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'healthy',
            service: 'ai-service',
          }),
        } as Response);
      } else if (typeof url === 'string' && url.includes('/modules/System/health.php')) {
        return Promise.resolve({
          ok: false,
          status: 503,
          statusText: 'Service Unavailable',
          json: async () => ({}),
        } as Response);
      }
      return Promise.reject(new Error('Unknown URL'));
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');
    const response = await GET(request);
    const data = await response.json();

    expect(response.status).toBe(200);
    expect(data.status).toBe('degraded');
    expect(data.checks.aiService.status).toBe('healthy');
    expect(data.checks.gibbon.status).toBe('degraded');
    expect(data.checks.gibbon.connected).toBe(false);
    expect(data.checks.gibbon.error).toContain('503');
  });

  it('should return unhealthy status when AI service is unreachable', async () => {
    global.fetch = vi.fn((url) => {
      if (typeof url === 'string' && url.includes('/api/v1/health')) {
        return Promise.reject(new Error('Connection refused'));
      } else if (typeof url === 'string' && url.includes('/modules/System/health.php')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'healthy',
            service: 'gibbon',
          }),
        } as Response);
      }
      return Promise.reject(new Error('Unknown URL'));
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');
    const response = await GET(request);
    const data = await response.json();

    expect(response.status).toBe(503);
    expect(data.status).toBe('unhealthy');
    expect(data.checks.aiService.status).toBe('unhealthy');
    expect(data.checks.aiService.connected).toBe(false);
    expect(data.checks.aiService.error).toContain('Connection refused');
  });

  it('should return unhealthy status when Gibbon is unreachable', async () => {
    global.fetch = vi.fn((url) => {
      if (typeof url === 'string' && url.includes('/api/v1/health')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'healthy',
            service: 'ai-service',
          }),
        } as Response);
      } else if (typeof url === 'string' && url.includes('/modules/System/health.php')) {
        return Promise.reject(new Error('Network error'));
      }
      return Promise.reject(new Error('Unknown URL'));
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');
    const response = await GET(request);
    const data = await response.json();

    expect(response.status).toBe(503);
    expect(data.status).toBe('unhealthy');
    expect(data.checks.gibbon.status).toBe('unhealthy');
    expect(data.checks.gibbon.connected).toBe(false);
    expect(data.checks.gibbon.error).toContain('Network error');
  });

  it('should return unhealthy status when both services are down', async () => {
    global.fetch = vi.fn(() => {
      return Promise.reject(new Error('Connection failed'));
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');
    const response = await GET(request);
    const data = await response.json();

    expect(response.status).toBe(503);
    expect(data.status).toBe('unhealthy');
    expect(data.checks.aiService.status).toBe('unhealthy');
    expect(data.checks.aiService.connected).toBe(false);
    expect(data.checks.gibbon.status).toBe('unhealthy');
    expect(data.checks.gibbon.connected).toBe(false);
  });

  it('should include API URLs in the response', async () => {
    global.fetch = vi.fn((url) => {
      if (typeof url === 'string' && url.includes('/api/v1/health')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'healthy',
            service: 'ai-service',
          }),
        } as Response);
      } else if (typeof url === 'string' && url.includes('/modules/System/health.php')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'healthy',
            service: 'gibbon',
          }),
        } as Response);
      }
      return Promise.reject(new Error('Unknown URL'));
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');
    const response = await GET(request);
    const data = await response.json();

    expect(data.checks.aiService.apiUrl).toBe('http://localhost:8000');
    expect(data.checks.gibbon.gibbonUrl).toBe('http://localhost:8080/gibbon');
  });

  it('should include service details when available', async () => {
    const mockAIServiceDetails = {
      status: 'healthy',
      service: 'ai-service',
      version: '0.1.0',
      checks: {
        database: { status: 'healthy' },
        redis: { status: 'healthy' },
      },
    };

    global.fetch = vi.fn((url) => {
      if (typeof url === 'string' && url.includes('/api/v1/health')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => mockAIServiceDetails,
        } as Response);
      } else if (typeof url === 'string' && url.includes('/modules/System/health.php')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'healthy',
            service: 'gibbon',
          }),
        } as Response);
      }
      return Promise.reject(new Error('Unknown URL'));
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');
    const response = await GET(request);
    const data = await response.json();

    expect(data.checks.aiService.details).toEqual(mockAIServiceDetails);
  });

  it('should handle timeout errors gracefully', async () => {
    // Mock a delayed fetch that will timeout
    global.fetch = vi.fn((url) => {
      return new Promise((resolve) => {
        setTimeout(() => {
          resolve({
            ok: true,
            status: 200,
            json: async () => ({ status: 'healthy' }),
          } as Response);
        }, 10000); // 10 seconds - longer than our 5 second timeout
      });
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');
    const response = await GET(request);
    const data = await response.json();

    // The request should fail due to timeout
    expect(response.status).toBe(503);
    expect(data.status).toBe('unhealthy');
  }, 10000); // Increase test timeout to 10 seconds

  it('should use default URLs when environment variables are not set', async () => {
    // Clear environment variables
    delete process.env.NEXT_PUBLIC_API_URL;
    delete process.env.NEXT_PUBLIC_GIBBON_URL;

    global.fetch = vi.fn((url) => {
      if (typeof url === 'string' && url.includes('/api/v1/health')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'healthy',
            service: 'ai-service',
          }),
        } as Response);
      } else if (typeof url === 'string' && url.includes('/modules/System/health.php')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'healthy',
            service: 'gibbon',
          }),
        } as Response);
      }
      return Promise.reject(new Error('Unknown URL'));
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');
    const response = await GET(request);
    const data = await response.json();

    expect(response.status).toBe(200);
    expect(data.checks.aiService.apiUrl).toBe('http://localhost:8000');
    expect(data.checks.gibbon.gibbonUrl).toBe('http://localhost:8080/gibbon');
  });

  it('should return proper timestamp format', async () => {
    global.fetch = vi.fn(() => {
      return Promise.resolve({
        ok: true,
        status: 200,
        json: async () => ({ status: 'healthy' }),
      } as Response);
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');
    const response = await GET(request);
    const data = await response.json();

    // Timestamp should be a valid ISO 8601 string
    expect(data.timestamp).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}Z$/);

    // Should be a recent timestamp (within last minute)
    const timestampDate = new Date(data.timestamp);
    const now = new Date();
    const diffSeconds = (now.getTime() - timestampDate.getTime()) / 1000;
    expect(diffSeconds).toBeLessThan(60);
  });

  it('should handle Gibbon degraded status correctly', async () => {
    global.fetch = vi.fn((url) => {
      if (typeof url === 'string' && url.includes('/api/v1/health')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'healthy',
            service: 'ai-service',
          }),
        } as Response);
      } else if (typeof url === 'string' && url.includes('/modules/System/health.php')) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: async () => ({
            status: 'degraded', // Gibbon returns degraded status
            service: 'gibbon',
          }),
        } as Response);
      }
      return Promise.reject(new Error('Unknown URL'));
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');
    const response = await GET(request);
    const data = await response.json();

    expect(response.status).toBe(200);
    expect(data.status).toBe('healthy'); // Still healthy as Gibbon is accessible
    expect(data.checks.gibbon.status).toBe('healthy');
    expect(data.checks.gibbon.details.status).toBe('degraded');
  });

  it('should handle unexpected errors gracefully', async () => {
    // Mock a fetch that throws an unexpected error
    global.fetch = vi.fn(() => {
      throw new Error('Unexpected error during fetch');
    }) as typeof fetch;

    const request = new NextRequest('http://localhost:3000/api/health');

    // The GET handler should catch this and return a proper error response
    const response = await GET(request);
    const data = await response.json();

    expect(response.status).toBe(503);
    expect(data.status).toBe('unhealthy');
    expect(data.service).toBe('parent-portal');
    expect(data.version).toBe('0.1.0');
  });
});
