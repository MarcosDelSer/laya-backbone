const createNextIntlPlugin = require('next-intl/plugin');

/**
 * Create next-intl plugin with custom request config path.
 * This integrates i18n support throughout the Next.js application.
 */
const withNextIntl = createNextIntlPlugin('./i18n/request.ts');

/** @type {import('next').NextConfig} */
const nextConfig = {
  // Enable React strict mode for better development experience
  reactStrictMode: true,

  // Image optimization configuration
  images: {
    remotePatterns: [
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
    ],
  },

  // Environment variables exposed to the browser
  env: {
    NEXT_PUBLIC_API_URL: process.env.NEXT_PUBLIC_API_URL,
    NEXT_PUBLIC_GIBBON_URL: process.env.NEXT_PUBLIC_GIBBON_URL,
  },
};

module.exports = withNextIntl(nextConfig);
