/**
 * Health check API route for LAYA Parent Portal.
 *
 * Provides health check endpoints for monitoring service health,
 * including build version and API connectivity checks.
 */

import { NextRequest, NextResponse } from 'next/server';
import packageJson from '../../../package.json';

interface HealthCheck {
  status: 'healthy' | 'degraded' | 'unhealthy';
  [key: string]: any;
}

interface HealthResponse {
  status: 'healthy' | 'degraded' | 'unhealthy';
  timestamp: string;
  service: string;
  version: string;
  checks: {
    aiService: HealthCheck;
    gibbon: HealthCheck;
  };
}

/**
 * Check AI Service health and connectivity.
 *
 * @returns Health status of the AI service
 */
async function checkAIServiceHealth(): Promise<HealthCheck> {
  const apiUrl = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout

    const response = await fetch(`${apiUrl}/api/v1/health`, {
      method: 'GET',
      signal: controller.signal,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    clearTimeout(timeoutId);

    if (response.ok) {
      const data = await response.json();
      return {
        status: 'healthy',
        connected: true,
        responseTime: Date.now(),
        apiUrl,
        details: data,
      };
    } else {
      return {
        status: 'degraded',
        connected: false,
        error: `HTTP ${response.status}: ${response.statusText}`,
        apiUrl,
      };
    }
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    return {
      status: 'unhealthy',
      connected: false,
      error: errorMessage,
      apiUrl,
    };
  }
}

/**
 * Check Gibbon CMS health and connectivity.
 *
 * @returns Health status of Gibbon CMS
 */
async function checkGibbonHealth(): Promise<HealthCheck> {
  const gibbonUrl = process.env.NEXT_PUBLIC_GIBBON_URL || 'http://localhost:8080/gibbon';

  try {
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 second timeout

    const response = await fetch(`${gibbonUrl}/modules/System/health.php`, {
      method: 'GET',
      signal: controller.signal,
      headers: {
        'Content-Type': 'application/json',
      },
    });

    clearTimeout(timeoutId);

    if (response.ok) {
      const data = await response.json();
      return {
        status: data.status === 'healthy' || data.status === 'degraded' ? 'healthy' : 'degraded',
        connected: true,
        responseTime: Date.now(),
        gibbonUrl,
        details: data,
      };
    } else {
      return {
        status: 'degraded',
        connected: false,
        error: `HTTP ${response.status}: ${response.statusText}`,
        gibbonUrl,
      };
    }
  } catch (error) {
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';
    return {
      status: 'unhealthy',
      connected: false,
      error: errorMessage,
      gibbonUrl,
    };
  }
}

/**
 * GET /api/health
 *
 * Comprehensive health check endpoint that checks:
 * - Build version
 * - AI Service connectivity
 * - Gibbon CMS connectivity
 *
 * @returns Health status response
 *
 * @example
 * GET /api/health
 *
 * Response (200 OK):
 * {
 *   "status": "healthy",
 *   "timestamp": "2024-02-15T10:30:00.000Z",
 *   "service": "parent-portal",
 *   "version": "0.1.0",
 *   "checks": {
 *     "aiService": {
 *       "status": "healthy",
 *       "connected": true
 *     },
 *     "gibbon": {
 *       "status": "healthy",
 *       "connected": true
 *     }
 *   }
 * }
 *
 * Response (503 Service Unavailable) - when critical services are down:
 * {
 *   "status": "unhealthy",
 *   "timestamp": "2024-02-15T10:30:00.000Z",
 *   "service": "parent-portal",
 *   "version": "0.1.0",
 *   "checks": {
 *     "aiService": {
 *       "status": "unhealthy",
 *       "connected": false,
 *       "error": "Connection refused"
 *     },
 *     "gibbon": {
 *       "status": "healthy",
 *       "connected": true
 *     }
 *   }
 * }
 */
export async function GET(request: NextRequest) {
  try {
    // Run all health checks in parallel for better performance
    const [aiServiceHealth, gibbonHealth] = await Promise.all([
      checkAIServiceHealth(),
      checkGibbonHealth(),
    ]);

    const checks = {
      aiService: aiServiceHealth,
      gibbon: gibbonHealth,
    };

    // Determine overall health status
    // Both AI Service and Gibbon are critical for parent portal functionality
    const criticalChecks = [aiServiceHealth, gibbonHealth];

    const isHealthy = criticalChecks.every(
      (check) => check.status === 'healthy'
    );

    const isDegraded = criticalChecks.some(
      (check) => check.status === 'degraded'
    );

    const overallStatus: 'healthy' | 'degraded' | 'unhealthy' = isHealthy
      ? 'healthy'
      : isDegraded
      ? 'degraded'
      : 'unhealthy';

    // Build response
    const healthResponse: HealthResponse = {
      status: overallStatus,
      timestamp: new Date().toISOString(),
      service: 'parent-portal',
      version: packageJson.version,
      checks,
    };

    // Return appropriate HTTP status code
    const httpStatus = isHealthy ? 200 : isDegraded ? 200 : 503;

    return NextResponse.json(healthResponse, { status: httpStatus });
  } catch (error) {
    // Handle unexpected errors
    const errorMessage = error instanceof Error ? error.message : 'Unknown error';

    return NextResponse.json(
      {
        status: 'unhealthy',
        timestamp: new Date().toISOString(),
        service: 'parent-portal',
        version: packageJson.version,
        error: errorMessage,
      },
      { status: 503 }
    );
  }
}
