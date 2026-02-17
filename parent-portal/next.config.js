const createNextIntlPlugin = require('next-intl/plugin');

/**
 * Create next-intl plugin with custom request config path.
 * This integrates i18n support throughout the Next.js application.
 */
const withNextIntl = createNextIntlPlugin('./i18n/request.ts');

/** @type {import('next').NextConfig} */

// Parse API host from environment variable for image optimization
const getRemotePatterns = () => {
  const patterns = [
    // Local development
    {
      protocol: 'http',
      hostname: 'localhost',
      port: '8000',
      pathname: '/**',
    },
    {
      protocol: 'http',
      hostname: 'localhost',
      port: '8080',
      pathname: '/**',
    },
  ];

  // Add production API host if configured
  const apiUrl = process.env.NEXT_PUBLIC_API_URL;
  if (apiUrl && !apiUrl.includes('localhost')) {
    try {
      const url = new URL(apiUrl);
      patterns.push({
        protocol: url.protocol.replace(':', ''),
        hostname: url.hostname,
        port: url.port || '',
        pathname: '/**',
      });
    } catch (e) {
      console.warn('Invalid NEXT_PUBLIC_API_URL for image patterns:', apiUrl);
    }
  }

  // Add production Gibbon host if configured
  const gibbonUrl = process.env.NEXT_PUBLIC_GIBBON_URL;
  if (gibbonUrl && !gibbonUrl.includes('localhost')) {
    try {
      const url = new URL(gibbonUrl);
      // Avoid duplicates
      const exists = patterns.some(p => p.hostname === url.hostname);
      if (!exists) {
        patterns.push({
          protocol: url.protocol.replace(':', ''),
          hostname: url.hostname,
          port: url.port || '',
          pathname: '/**',
        });
      }
    } catch (e) {
      console.warn('Invalid NEXT_PUBLIC_GIBBON_URL for image patterns:', gibbonUrl);
    }
  }

  return patterns;
};

const nextConfig = {
  // Enable React strict mode for better development experience
  reactStrictMode: true,

  // Image optimization configuration
  // Supports both localhost (development) and production API hosts
  images: {
    remotePatterns: getRemotePatterns(),
  },

  // Environment variables exposed to the browser
  env: {
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL,
    NEXT_PUBLIC_GIBBON_URL: process.env.NEXT_PUBLIC_GIBBON_URL,
  },

  // Security headers configuration
  async headers() {
    return [
      {
        // Apply security headers to all routes
        source: '/:path*',
        headers: [
          {
            key: 'Content-Security-Policy',
            value: [
              "default-src 'self'",
              "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
              "style-src 'self' 'unsafe-inline'",
              "img-src 'self' data: https:",
              "font-src 'self' data:",
              "connect-src 'self' http://localhost:* ws://localhost:* wss://localhost:*",
            ].join('; '),
          },
        ],
      },
    ];
  },
};

module.exports = withNextIntl(nextConfig);
