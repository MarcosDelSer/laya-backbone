/**
 * Request configuration for next-intl.
 *
 * Provides locale and message loading for server components
 * in the Next.js App Router.
 */

import { getRequestConfig } from 'next-intl/server';
import { locales, defaultLocale, type Locale } from '../i18n';

/**
 * Configures internationalization for each request.
 *
 * This function is called for each request and returns the locale
 * and messages to use. It dynamically imports the translation files
 * based on the requested locale.
 */
export default getRequestConfig(async ({ requestLocale }) => {
  // Get the locale from the request (e.g., from the URL segment)
  const locale = await requestLocale;

  // Validate the locale parameter
  const validLocale: Locale = locale && locales.includes(locale as Locale)
    ? (locale as Locale)
    : defaultLocale;

  // Dynamically import the messages for the requested locale
  const messages = (await import(`../messages/${validLocale}.json`)).default;

  return {
    locale: validLocale,
    messages,
    // Configure time zone for Quebec
    timeZone: 'America/Toronto',
    // Configure date/time formatting
    now: new Date(),
  };
});
