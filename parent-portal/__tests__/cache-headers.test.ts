/**
 * Tests for static asset cache headers configuration.
 *
 * This test suite verifies that the Next.js configuration includes
 * proper cache control headers for different types of static assets.
 */

import { describe, it, expect } from 'vitest';

// Import the next.config.js file
const nextConfig = require('../next.config.js');

describe('Static Asset Cache Headers', () => {
  describe('Next.js Configuration', () => {
    it('should have headers function defined', () => {
      expect(nextConfig).toHaveProperty('headers');
      expect(typeof nextConfig.headers).toBe('function');
    });

    it('should return headers configuration', async () => {
      const headers = await nextConfig.headers();
      expect(Array.isArray(headers)).toBe(true);
      expect(headers.length).toBeGreaterThan(0);
    });
  });

  describe('Cache Headers Configuration', () => {
    let headers: any[];

    beforeEach(async () => {
      headers = await nextConfig.headers();
    });

    it('should configure cache for _next/static with immutable', () => {
      const nextStaticConfig = headers.find(h => h.source === '/_next/static/:path*');
      expect(nextStaticConfig).toBeDefined();
      expect(nextStaticConfig.headers).toBeDefined();

      const cacheControl = nextStaticConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );
      expect(cacheControl).toBeDefined();
      expect(cacheControl.value).toContain('public');
      expect(cacheControl.value).toContain('max-age=31536000'); // 1 year
      expect(cacheControl.value).toContain('immutable');
    });

    it('should configure cache for public static assets', () => {
      const staticConfig = headers.find(h => h.source === '/static/:path*');
      expect(staticConfig).toBeDefined();
      expect(staticConfig.headers).toBeDefined();

      const cacheControl = staticConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );
      expect(cacheControl).toBeDefined();
      expect(cacheControl.value).toContain('public');
      expect(cacheControl.value).toContain('max-age=2592000'); // 1 month
      expect(cacheControl.value).toContain('must-revalidate');
    });

    it('should configure cache for images directory', () => {
      const imagesConfig = headers.find(h => h.source === '/images/:path*');
      expect(imagesConfig).toBeDefined();
      expect(imagesConfig.headers).toBeDefined();

      const cacheControl = imagesConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );
      expect(cacheControl).toBeDefined();
      expect(cacheControl.value).toContain('public');
      expect(cacheControl.value).toContain('max-age=2592000'); // 1 month
      expect(cacheControl.value).toContain('must-revalidate');
    });

    it('should configure cache for favicon and manifest', () => {
      const metaConfig = headers.find(
        h => h.source === '/(favicon.ico|manifest.json|robots.txt)'
      );
      expect(metaConfig).toBeDefined();
      expect(metaConfig.headers).toBeDefined();

      const cacheControl = metaConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );
      expect(cacheControl).toBeDefined();
      expect(cacheControl.value).toContain('public');
      expect(cacheControl.value).toContain('max-age=604800'); // 1 week
      expect(cacheControl.value).toContain('must-revalidate');
    });

    it('should configure no-cache for API routes', () => {
      const apiConfig = headers.find(h => h.source === '/api/:path*');
      expect(apiConfig).toBeDefined();
      expect(apiConfig.headers).toBeDefined();

      const cacheControl = apiConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );
      expect(cacheControl).toBeDefined();
      expect(cacheControl.value).toContain('no-cache');
      expect(cacheControl.value).toContain('no-store');
      expect(cacheControl.value).toContain('must-revalidate');

      const pragma = apiConfig.headers.find((h: any) => h.key === 'Pragma');
      expect(pragma).toBeDefined();
      expect(pragma.value).toBe('no-cache');

      const expires = apiConfig.headers.find((h: any) => h.key === 'Expires');
      expect(expires).toBeDefined();
      expect(expires.value).toBe('0');
    });
  });

  describe('Cache Duration Values', () => {
    let headers: any[];

    beforeEach(async () => {
      headers = await nextConfig.headers();
    });

    it('should use correct cache duration for immutable assets (1 year)', () => {
      const nextStaticConfig = headers.find(h => h.source === '/_next/static/:path*');
      const cacheControl = nextStaticConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );

      // 1 year = 365 days * 24 hours * 60 minutes * 60 seconds = 31536000
      expect(cacheControl.value).toContain('max-age=31536000');
    });

    it('should use correct cache duration for static assets (1 month)', () => {
      const staticConfig = headers.find(h => h.source === '/static/:path*');
      const cacheControl = staticConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );

      // 1 month = 30 days * 24 hours * 60 minutes * 60 seconds = 2592000
      expect(cacheControl.value).toContain('max-age=2592000');
    });

    it('should use correct cache duration for favicon/manifest (1 week)', () => {
      const metaConfig = headers.find(
        h => h.source === '/(favicon.ico|manifest.json|robots.txt)'
      );
      const cacheControl = metaConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );

      // 1 week = 7 days * 24 hours * 60 minutes * 60 seconds = 604800
      expect(cacheControl.value).toContain('max-age=604800');
    });
  });

  describe('Cache Control Directives', () => {
    let headers: any[];

    beforeEach(async () => {
      headers = await nextConfig.headers();
    });

    it('should mark versioned assets as immutable', () => {
      const nextStaticConfig = headers.find(h => h.source === '/_next/static/:path*');
      const cacheControl = nextStaticConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );

      expect(cacheControl.value).toContain('immutable');
    });

    it('should mark public assets as public', () => {
      const configs = [
        headers.find(h => h.source === '/_next/static/:path*'),
        headers.find(h => h.source === '/static/:path*'),
        headers.find(h => h.source === '/images/:path*'),
      ];

      configs.forEach(config => {
        const cacheControl = config.headers.find(
          (h: any) => h.key === 'Cache-Control'
        );
        expect(cacheControl.value).toContain('public');
      });
    });

    it('should require revalidation for non-immutable assets', () => {
      const configs = [
        headers.find(h => h.source === '/static/:path*'),
        headers.find(h => h.source === '/images/:path*'),
        headers.find(h => h.source === '/(favicon.ico|manifest.json|robots.txt)'),
      ];

      configs.forEach(config => {
        const cacheControl = config.headers.find(
          (h: any) => h.key === 'Cache-Control'
        );
        expect(cacheControl.value).toContain('must-revalidate');
      });
    });

    it('should prevent caching for API routes', () => {
      const apiConfig = headers.find(h => h.source === '/api/:path*');
      const cacheControl = apiConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );

      expect(cacheControl.value).toContain('no-cache');
      expect(cacheControl.value).toContain('no-store');
    });
  });

  describe('Header Coverage', () => {
    let headers: any[];

    beforeEach(async () => {
      headers = await nextConfig.headers();
    });

    it('should have at least 5 header configurations', () => {
      expect(headers.length).toBeGreaterThanOrEqual(5);
    });

    it('should cover all static asset types', () => {
      const sources = headers.map(h => h.source);

      expect(sources).toContain('/_next/static/:path*'); // Next.js assets
      expect(sources).toContain('/static/:path*');        // Static files
      expect(sources).toContain('/images/:path*');        // Images
      expect(sources).toContain('/api/:path*');           // API routes
    });

    it('should have Cache-Control header for all configurations', () => {
      headers.forEach(config => {
        const hasCacheControl = config.headers.some(
          (h: any) => h.key === 'Cache-Control'
        );
        expect(hasCacheControl).toBe(true);
      });
    });
  });

  describe('Best Practices', () => {
    let headers: any[];

    beforeEach(async () => {
      headers = await nextConfig.headers();
    });

    it('should use long cache times for versioned assets', () => {
      const nextStaticConfig = headers.find(h => h.source === '/_next/static/:path*');
      const cacheControl = nextStaticConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );

      // Max-age should be at least 1 year for versioned assets
      const maxAgeMatch = cacheControl.value.match(/max-age=(\d+)/);
      expect(maxAgeMatch).toBeTruthy();
      const maxAge = parseInt(maxAgeMatch[1], 10);
      expect(maxAge).toBeGreaterThanOrEqual(31536000); // 1 year
    });

    it('should use reasonable cache times for static assets', () => {
      const staticConfig = headers.find(h => h.source === '/static/:path*');
      const cacheControl = staticConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );

      // Max-age should be between 1 day and 1 year for static assets
      const maxAgeMatch = cacheControl.value.match(/max-age=(\d+)/);
      expect(maxAgeMatch).toBeTruthy();
      const maxAge = parseInt(maxAgeMatch[1], 10);
      expect(maxAge).toBeGreaterThanOrEqual(86400);    // At least 1 day
      expect(maxAge).toBeLessThanOrEqual(31536000);    // At most 1 year
    });

    it('should prevent API caching', () => {
      const apiConfig = headers.find(h => h.source === '/api/:path*');
      const cacheControl = apiConfig.headers.find(
        (h: any) => h.key === 'Cache-Control'
      );

      expect(cacheControl.value).toMatch(/no-cache|no-store/);
    });

    it('should include additional no-cache headers for API', () => {
      const apiConfig = headers.find(h => h.source === '/api/:path*');

      const headerKeys = apiConfig.headers.map((h: any) => h.key);
      expect(headerKeys).toContain('Pragma');
      expect(headerKeys).toContain('Expires');
    });
  });
});
