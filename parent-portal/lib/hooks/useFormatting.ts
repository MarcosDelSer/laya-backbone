/**
 * useFormatting Hook for LAYA Parent Portal.
 *
 * A React hook that provides locale-aware formatting utilities that integrate
 * with the next-intl locale context. This hook automatically uses the current
 * locale from the NextIntlClientProvider, eliminating the need to manually
 * pass the locale to each formatting function.
 *
 * @example
 * ```tsx
 * function InvoiceDetails({ amount, dueDate }) {
 *   const { formatCurrency, formatDate, formatRelativeTime } = useFormatting();
 *
 *   return (
 *     <div>
 *       <p>Amount: {formatCurrency(amount)}</p>
 *       <p>Due: {formatDate(dueDate, 'long')}</p>
 *       <p>Due in: {formatRelativeTime(dueDate)}</p>
 *     </div>
 *   );
 * }
 * ```
 */

'use client';

import { useLocale } from 'next-intl';
import { useMemo } from 'react';
import { Locale, defaultLocale, isValidLocale } from '@/i18n';
import {
  formatCurrency as formatCurrencyBase,
  formatCompactCurrency as formatCompactCurrencyBase,
  formatDate as formatDateBase,
  formatDateISO,
  formatTime as formatTimeBase,
  formatDateTime as formatDateTimeBase,
  formatRelativeTime as formatRelativeTimeBase,
  formatNumber as formatNumberBase,
  formatPercent as formatPercentBase,
  getDaysUntil,
  getDaysUntilDue,
  isToday,
  isPast,
  isFuture,
  DateFormatStyle,
  TimeFormatStyle,
} from '../formatting';

// ============================================================================
// Types
// ============================================================================

/**
 * Return type for the useFormatting hook.
 * Provides all formatting functions bound to the current locale.
 */
export interface UseFormattingReturn {
  /**
   * The current locale being used for formatting.
   */
  locale: Locale;

  /**
   * Formats a number as currency with locale-appropriate formatting.
   * Uses Canadian Dollar (CAD) with Quebec formatting conventions.
   *
   * @param amount - The numeric amount to format
   * @returns A formatted currency string (e.g., "$1,234.56" or "1 234,56 $")
   */
  formatCurrency: (amount: number) => string;

  /**
   * Formats a number as a compact currency value for display in tight spaces.
   * E.g., "$1.2K" instead of "$1,234.00"
   *
   * @param amount - The numeric amount to format
   * @returns A compact formatted currency string
   */
  formatCompactCurrency: (amount: number) => string;

  /**
   * Formats a date with locale-appropriate formatting.
   * For French locale, dates follow Quebec DD/MM/YYYY convention.
   *
   * @param date - The date to format (Date object or ISO string)
   * @param style - The format style to use ('short' | 'medium' | 'long' | 'full')
   * @returns A formatted date string
   */
  formatDate: (date: Date | string, style?: DateFormatStyle) => string;

  /**
   * Formats a date in ISO format (YYYY-MM-DD).
   * Useful for form inputs and API communication.
   * Note: This function is locale-independent.
   *
   * @param date - The date to format
   * @returns An ISO date string (YYYY-MM-DD)
   */
  formatDateISO: (date: Date | string) => string;

  /**
   * Formats a time with locale-appropriate formatting.
   *
   * @param date - The date/time to format
   * @param style - The format style to use ('short' | 'medium')
   * @returns A formatted time string
   */
  formatTime: (date: Date | string, style?: TimeFormatStyle) => string;

  /**
   * Formats a date and time together with locale-appropriate formatting.
   *
   * @param date - The date/time to format
   * @returns A formatted date and time string
   */
  formatDateTime: (date: Date | string) => string;

  /**
   * Formats a date as a relative time string (e.g., "2 days ago", "in 3 hours").
   *
   * @param date - The date to format
   * @param baseDate - The base date to calculate relative to (defaults to now)
   * @returns A relative time string
   */
  formatRelativeTime: (date: Date | string, baseDate?: Date) => string;

  /**
   * Formats a number with locale-appropriate thousands separators.
   *
   * @param value - The number to format
   * @param decimals - Number of decimal places (default: 0)
   * @returns A formatted number string
   */
  formatNumber: (value: number, decimals?: number) => string;

  /**
   * Formats a number as a percentage.
   *
   * @param value - The number to format (0.5 = 50%)
   * @param decimals - Number of decimal places
   * @returns A formatted percentage string
   */
  formatPercent: (value: number, decimals?: number) => string;

  /**
   * Calculates the number of days between a date and today.
   * Note: This function is locale-independent.
   *
   * @param date - The date to compare
   * @returns Number of days until the date (negative if past)
   */
  getDaysUntil: (date: Date | string) => number;

  /**
   * Alias for getDaysUntil that's more semantically clear for due dates.
   * Note: This function is locale-independent.
   *
   * @param dueDate - The due date
   * @returns Number of days until the due date (negative if overdue)
   */
  getDaysUntilDue: (dueDate: Date | string) => number;

  /**
   * Checks if a date is today.
   * Note: This function is locale-independent.
   *
   * @param date - The date to check
   * @returns True if the date is today
   */
  isToday: (date: Date | string) => boolean;

  /**
   * Checks if a date is in the past (before today).
   * Note: This function is locale-independent.
   *
   * @param date - The date to check
   * @returns True if the date is in the past
   */
  isPast: (date: Date | string) => boolean;

  /**
   * Checks if a date is in the future (after today).
   * Note: This function is locale-independent.
   *
   * @param date - The date to check
   * @returns True if the date is in the future
   */
  isFuture: (date: Date | string) => boolean;
}

// ============================================================================
// Hook Implementation
// ============================================================================

/**
 * React hook that provides locale-aware formatting utilities.
 *
 * This hook integrates with next-intl's locale context to automatically
 * use the current locale for all formatting functions. It eliminates the
 * need to manually pass the locale to each formatting function, making
 * components cleaner and less error-prone.
 *
 * @returns An object containing all formatting functions bound to the current locale
 *
 * @example
 * ```tsx
 * 'use client';
 *
 * import { useFormatting } from '@/lib/hooks/useFormatting';
 *
 * function PaymentSummary({ payment }) {
 *   const { formatCurrency, formatDate, formatRelativeTime, isPast } = useFormatting();
 *
 *   const isOverdue = isPast(payment.dueDate);
 *
 *   return (
 *     <div className={isOverdue ? 'text-red-600' : ''}>
 *       <p>Amount: {formatCurrency(payment.amount)}</p>
 *       <p>Due: {formatDate(payment.dueDate, 'long')}</p>
 *       <p>{formatRelativeTime(payment.dueDate)}</p>
 *     </div>
 *   );
 * }
 * ```
 */
export function useFormatting(): UseFormattingReturn {
  const localeFromContext = useLocale();

  // Validate the locale and fall back to default if invalid
  const locale: Locale = isValidLocale(localeFromContext)
    ? localeFromContext
    : defaultLocale;

  // Memoize the formatting functions to prevent unnecessary re-renders
  return useMemo<UseFormattingReturn>(
    () => ({
      locale,

      // Currency formatting
      formatCurrency: (amount: number) => formatCurrencyBase(amount, locale),
      formatCompactCurrency: (amount: number) =>
        formatCompactCurrencyBase(amount, locale),

      // Date formatting
      formatDate: (date: Date | string, style?: DateFormatStyle) =>
        formatDateBase(date, locale, style),
      formatDateISO,

      // Time formatting
      formatTime: (date: Date | string, style?: TimeFormatStyle) =>
        formatTimeBase(date, locale, style),
      formatDateTime: (date: Date | string) =>
        formatDateTimeBase(date, locale),

      // Relative time
      formatRelativeTime: (date: Date | string, baseDate?: Date) =>
        formatRelativeTimeBase(date, locale, baseDate),

      // Number formatting
      formatNumber: (value: number, decimals?: number) =>
        formatNumberBase(value, locale, decimals),
      formatPercent: (value: number, decimals?: number) =>
        formatPercentBase(value, locale, decimals),

      // Date calculation utilities (locale-independent)
      getDaysUntil,
      getDaysUntilDue,
      isToday,
      isPast,
      isFuture,
    }),
    [locale]
  );
}

// ============================================================================
// Re-exports for Convenience
// ============================================================================

/**
 * Re-export types from formatting module for convenience.
 */
export type { DateFormatStyle, TimeFormatStyle };
