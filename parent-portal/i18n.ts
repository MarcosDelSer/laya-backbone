/**
 * Internationalization configuration for LAYA Parent Portal.
 *
 * Provides i18n setup using next-intl with support for English and French
 * locales, following Quebec regional formatting requirements.
 */

// ============================================================================
// Locale Configuration
// ============================================================================

/**
 * Supported locales for the parent portal.
 * - 'en': English
 * - 'fr': French (Quebec French)
 */
export const locales = ['en', 'fr'] as const;

/**
 * Type representing a supported locale.
 */
export type Locale = (typeof locales)[number];

/**
 * Default locale to use when none is specified.
 */
export const defaultLocale: Locale = 'en';

/**
 * Locale labels for display in language switcher.
 */
export const localeLabels: Record<Locale, string> = {
  en: 'English',
  fr: 'Français',
};

// ============================================================================
// Locale Validation
// ============================================================================

/**
 * Checks if a given string is a valid supported locale.
 *
 * @param locale - The locale string to validate
 * @returns True if the locale is supported, false otherwise
 */
export function isValidLocale(locale: string): locale is Locale {
  return locales.includes(locale as Locale);
}

/**
 * Returns a valid locale, falling back to default if invalid.
 *
 * @param locale - The locale string to validate
 * @returns A valid locale, defaulting to 'en' if invalid
 */
export function getValidLocale(locale: string | undefined): Locale {
  if (locale && isValidLocale(locale)) {
    return locale;
  }
  return defaultLocale;
}
