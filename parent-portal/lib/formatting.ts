/**
 * Locale-Aware Formatting Utilities for LAYA Parent Portal.
 *
 * Provides date, time, and currency formatting functions that support
 * both English (en) and French (fr) locales, with Quebec-specific
 * formatting requirements:
 * - Dates in DD/MM/YYYY format for French locale
 * - Currency in $X,XXX.XX format
 */

import { Locale, defaultLocale } from '../i18n';

// ============================================================================
// Locale-Specific Configuration
// ============================================================================

/**
 * Maps our application locales to Intl locale codes.
 * For Quebec French, we use 'fr-CA' to get Canadian formatting conventions.
 */
const intlLocaleMap: Record<Locale, string> = {
  en: 'en-CA',
  fr: 'fr-CA',
};

/**
 * Gets the Intl locale code for a given application locale.
 *
 * @param locale - The application locale ('en' or 'fr')
 * @returns The Intl locale code
 */
function getIntlLocale(locale: Locale): string {
  return intlLocaleMap[locale] ?? intlLocaleMap[defaultLocale];
}

// ============================================================================
// Currency Formatting
// ============================================================================

/**
 * Formats a number as currency with locale-appropriate formatting.
 * Uses Canadian Dollar (CAD) with Quebec formatting conventions.
 *
 * @param amount - The numeric amount to format
 * @param locale - The locale to use for formatting ('en' or 'fr')
 * @returns A formatted currency string (e.g., "$1,234.56" or "1 234,56 $")
 *
 * @example
 * formatCurrency(1234.56, 'en') // "$1,234.56"
 * formatCurrency(1234.56, 'fr') // "1 234,56 $"
 */
export function formatCurrency(amount: number, locale: Locale = defaultLocale): string {
  const intlLocale = getIntlLocale(locale);

  return new Intl.NumberFormat(intlLocale, {
    style: 'currency',
    currency: 'CAD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
}

/**
 * Formats a number as a compact currency value for display in tight spaces.
 * E.g., "$1.2K" instead of "$1,234.00"
 *
 * @param amount - The numeric amount to format
 * @param locale - The locale to use for formatting
 * @returns A compact formatted currency string
 *
 * @example
 * formatCompactCurrency(1234.56, 'en') // "$1.2K"
 * formatCompactCurrency(1234567, 'en') // "$1.2M"
 */
export function formatCompactCurrency(amount: number, locale: Locale = defaultLocale): string {
  const intlLocale = getIntlLocale(locale);

  return new Intl.NumberFormat(intlLocale, {
    style: 'currency',
    currency: 'CAD',
    notation: 'compact',
    maximumFractionDigits: 1,
  }).format(amount);
}

// ============================================================================
// Date Formatting
// ============================================================================

/**
 * Date formatting options for different display contexts.
 */
export type DateFormatStyle = 'short' | 'medium' | 'long' | 'full';

/**
 * Configuration for date format options per locale and style.
 */
const dateFormatOptions: Record<DateFormatStyle, Intl.DateTimeFormatOptions> = {
  short: {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  },
  medium: {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
  },
  long: {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  },
  full: {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  },
};

/**
 * Formats a date with locale-appropriate formatting.
 * For French locale, dates follow Quebec DD/MM/YYYY convention.
 *
 * @param date - The date to format (Date object or ISO string)
 * @param locale - The locale to use for formatting
 * @param style - The format style to use
 * @returns A formatted date string
 *
 * @example
 * formatDate(new Date('2024-12-25'), 'en', 'short') // "12/25/2024"
 * formatDate(new Date('2024-12-25'), 'fr', 'short') // "25/12/2024"
 * formatDate('2024-12-25', 'en', 'medium') // "Dec 25, 2024"
 * formatDate('2024-12-25', 'fr', 'medium') // "25 déc. 2024"
 */
export function formatDate(
  date: Date | string,
  locale: Locale = defaultLocale,
  style: DateFormatStyle = 'medium'
): string {
  const dateObj = typeof date === 'string' ? new Date(date) : date;

  if (isNaN(dateObj.getTime())) {
    return '';
  }

  const intlLocale = getIntlLocale(locale);
  const options = dateFormatOptions[style];

  return new Intl.DateTimeFormat(intlLocale, options).format(dateObj);
}

/**
 * Formats a date in ISO format (YYYY-MM-DD).
 * Useful for form inputs and API communication.
 *
 * @param date - The date to format
 * @returns An ISO date string (YYYY-MM-DD)
 *
 * @example
 * formatDateISO(new Date('2024-12-25')) // "2024-12-25"
 */
export function formatDateISO(date: Date | string): string {
  const dateObj = typeof date === 'string' ? new Date(date) : date;

  if (isNaN(dateObj.getTime())) {
    return '';
  }

  return dateObj.toISOString().split('T')[0];
}

// ============================================================================
// Time Formatting
// ============================================================================

/**
 * Time formatting options for different display contexts.
 */
export type TimeFormatStyle = 'short' | 'medium';

/**
 * Configuration for time format options.
 */
const timeFormatOptions: Record<TimeFormatStyle, Intl.DateTimeFormatOptions> = {
  short: {
    hour: 'numeric',
    minute: '2-digit',
  },
  medium: {
    hour: 'numeric',
    minute: '2-digit',
    second: '2-digit',
  },
};

/**
 * Formats a time with locale-appropriate formatting.
 *
 * @param date - The date/time to format
 * @param locale - The locale to use for formatting
 * @param style - The format style to use
 * @returns A formatted time string
 *
 * @example
 * formatTime(new Date('2024-12-25T14:30:00'), 'en') // "2:30 PM"
 * formatTime(new Date('2024-12-25T14:30:00'), 'fr') // "14 h 30"
 */
export function formatTime(
  date: Date | string,
  locale: Locale = defaultLocale,
  style: TimeFormatStyle = 'short'
): string {
  const dateObj = typeof date === 'string' ? new Date(date) : date;

  if (isNaN(dateObj.getTime())) {
    return '';
  }

  const intlLocale = getIntlLocale(locale);
  const options = timeFormatOptions[style];

  return new Intl.DateTimeFormat(intlLocale, options).format(dateObj);
}

// ============================================================================
// DateTime Formatting
// ============================================================================

/**
 * Formats a date and time together with locale-appropriate formatting.
 *
 * @param date - The date/time to format
 * @param locale - The locale to use for formatting
 * @returns A formatted date and time string
 *
 * @example
 * formatDateTime(new Date('2024-12-25T14:30:00'), 'en') // "Dec 25, 2024, 2:30 PM"
 * formatDateTime(new Date('2024-12-25T14:30:00'), 'fr') // "25 déc. 2024, 14 h 30"
 */
export function formatDateTime(date: Date | string, locale: Locale = defaultLocale): string {
  const dateObj = typeof date === 'string' ? new Date(date) : date;

  if (isNaN(dateObj.getTime())) {
    return '';
  }

  const intlLocale = getIntlLocale(locale);

  return new Intl.DateTimeFormat(intlLocale, {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  }).format(dateObj);
}

// ============================================================================
// Relative Time Formatting
// ============================================================================

/**
 * Relative time units and their thresholds in milliseconds.
 */
const relativeTimeUnits: Array<{
  unit: Intl.RelativeTimeFormatUnit;
  ms: number;
}> = [
  { unit: 'year', ms: 365 * 24 * 60 * 60 * 1000 },
  { unit: 'month', ms: 30 * 24 * 60 * 60 * 1000 },
  { unit: 'week', ms: 7 * 24 * 60 * 60 * 1000 },
  { unit: 'day', ms: 24 * 60 * 60 * 1000 },
  { unit: 'hour', ms: 60 * 60 * 1000 },
  { unit: 'minute', ms: 60 * 1000 },
  { unit: 'second', ms: 1000 },
];

/**
 * Formats a date as a relative time string (e.g., "2 days ago", "in 3 hours").
 *
 * @param date - The date to format
 * @param locale - The locale to use for formatting
 * @param baseDate - The base date to calculate relative to (defaults to now)
 * @returns A relative time string
 *
 * @example
 * // Assuming today is 2024-12-25
 * formatRelativeTime(new Date('2024-12-23'), 'en') // "2 days ago"
 * formatRelativeTime(new Date('2024-12-23'), 'fr') // "il y a 2 jours"
 * formatRelativeTime(new Date('2024-12-27'), 'en') // "in 2 days"
 */
export function formatRelativeTime(
  date: Date | string,
  locale: Locale = defaultLocale,
  baseDate: Date = new Date()
): string {
  const dateObj = typeof date === 'string' ? new Date(date) : date;

  if (isNaN(dateObj.getTime())) {
    return '';
  }

  const intlLocale = getIntlLocale(locale);
  const diffMs = dateObj.getTime() - baseDate.getTime();
  const absDiffMs = Math.abs(diffMs);

  // Find the appropriate unit
  for (const { unit, ms } of relativeTimeUnits) {
    if (absDiffMs >= ms || unit === 'second') {
      const value = Math.round(diffMs / ms);
      const formatter = new Intl.RelativeTimeFormat(intlLocale, {
        numeric: 'auto',
      });
      return formatter.format(value, unit);
    }
  }

  return new Intl.RelativeTimeFormat(intlLocale, { numeric: 'auto' }).format(0, 'second');
}

// ============================================================================
// Date Calculation Utilities
// ============================================================================

/**
 * Calculates the number of days between a date and today.
 * Positive values mean the date is in the future.
 * Negative values mean the date is in the past.
 *
 * @param date - The date to compare
 * @returns Number of days until the date (negative if past)
 *
 * @example
 * // Assuming today is 2024-12-25
 * getDaysUntil(new Date('2024-12-27')) // 2
 * getDaysUntil(new Date('2024-12-23')) // -2
 * getDaysUntil(new Date('2024-12-25')) // 0
 */
export function getDaysUntil(date: Date | string): number {
  const targetDate = typeof date === 'string' ? new Date(date) : date;

  if (isNaN(targetDate.getTime())) {
    return 0;
  }

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  targetDate.setHours(0, 0, 0, 0);

  const diffTime = targetDate.getTime() - today.getTime();
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

/**
 * Alias for getDaysUntil that's more semantically clear for due dates.
 * Same as getDaysUntil but named for invoice/due date contexts.
 *
 * @param dueDate - The due date
 * @returns Number of days until the due date (negative if overdue)
 */
export function getDaysUntilDue(dueDate: Date | string): number {
  return getDaysUntil(dueDate);
}

/**
 * Checks if a date is today.
 *
 * @param date - The date to check
 * @returns True if the date is today
 */
export function isToday(date: Date | string): boolean {
  return getDaysUntil(date) === 0;
}

/**
 * Checks if a date is in the past (before today).
 *
 * @param date - The date to check
 * @returns True if the date is in the past
 */
export function isPast(date: Date | string): boolean {
  return getDaysUntil(date) < 0;
}

/**
 * Checks if a date is in the future (after today).
 *
 * @param date - The date to check
 * @returns True if the date is in the future
 */
export function isFuture(date: Date | string): boolean {
  return getDaysUntil(date) > 0;
}

// ============================================================================
// Number Formatting
// ============================================================================

/**
 * Formats a number with locale-appropriate thousands separators.
 *
 * @param value - The number to format
 * @param locale - The locale to use for formatting
 * @param decimals - Number of decimal places (default: 0)
 * @returns A formatted number string
 *
 * @example
 * formatNumber(1234567, 'en') // "1,234,567"
 * formatNumber(1234567, 'fr') // "1 234 567"
 * formatNumber(1234.567, 'en', 2) // "1,234.57"
 */
export function formatNumber(
  value: number,
  locale: Locale = defaultLocale,
  decimals: number = 0
): string {
  const intlLocale = getIntlLocale(locale);

  return new Intl.NumberFormat(intlLocale, {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(value);
}

/**
 * Formats a number as a percentage.
 *
 * @param value - The number to format (0.5 = 50%)
 * @param locale - The locale to use for formatting
 * @param decimals - Number of decimal places
 * @returns A formatted percentage string
 *
 * @example
 * formatPercent(0.5, 'en') // "50%"
 * formatPercent(0.123, 'fr', 1) // "12,3 %"
 */
export function formatPercent(
  value: number,
  locale: Locale = defaultLocale,
  decimals: number = 0
): string {
  const intlLocale = getIntlLocale(locale);

  return new Intl.NumberFormat(intlLocale, {
    style: 'percent',
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(value);
}
