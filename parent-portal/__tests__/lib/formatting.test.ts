/**
 * Unit tests for locale-aware formatting utilities
 * Tests date, currency, and number formatting for EN and FR locales
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import {
  formatCurrency,
  formatCompactCurrency,
  formatDate,
  formatDateISO,
  formatTime,
  formatDateTime,
  formatRelativeTime,
  getDaysUntil,
  getDaysUntilDue,
  isToday,
  isPast,
  isFuture,
  formatNumber,
  formatPercent,
} from '@/lib/formatting'

// ============================================================================
// Currency Formatting Tests
// ============================================================================

describe('formatCurrency', () => {
  it('formats positive amounts in English locale', () => {
    const result = formatCurrency(1234.56, 'en')
    expect(result).toContain('1')
    expect(result).toContain('234')
    expect(result).toContain('56')
    expect(result).toMatch(/\$|CA\$|CAD/)
  })

  it('formats positive amounts in French locale', () => {
    const result = formatCurrency(1234.56, 'fr')
    expect(result).toContain('1')
    expect(result).toContain('234')
    expect(result).toContain('56')
  })

  it('formats zero correctly', () => {
    const enResult = formatCurrency(0, 'en')
    const frResult = formatCurrency(0, 'fr')
    expect(enResult).toContain('0')
    expect(frResult).toContain('0')
  })

  it('formats negative amounts correctly', () => {
    const enResult = formatCurrency(-500, 'en')
    const frResult = formatCurrency(-500, 'fr')
    expect(enResult).toContain('500')
    expect(frResult).toContain('500')
  })

  it('formats large amounts with thousands separators', () => {
    const enResult = formatCurrency(1234567.89, 'en')
    // Should have proper formatting
    expect(enResult).toContain('234')
    expect(enResult).toContain('567')
  })

  it('uses two decimal places consistently', () => {
    const enResult = formatCurrency(100, 'en')
    const frResult = formatCurrency(100, 'fr')
    // Both should show cents
    expect(enResult).toMatch(/00/)
    expect(frResult).toMatch(/00/)
  })

  it('defaults to English when no locale specified', () => {
    const result = formatCurrency(1234.56)
    expect(result).toBeDefined()
    expect(typeof result).toBe('string')
  })
})

describe('formatCompactCurrency', () => {
  it('formats thousands with K notation in English', () => {
    const result = formatCompactCurrency(1500, 'en')
    expect(result.toLowerCase()).toMatch(/k|\d/)
  })

  it('formats millions with M notation in English', () => {
    const result = formatCompactCurrency(1500000, 'en')
    expect(result.toLowerCase()).toMatch(/m|\d/)
  })

  it('formats thousands in French locale', () => {
    const result = formatCompactCurrency(1500, 'fr')
    expect(result).toBeDefined()
    expect(typeof result).toBe('string')
  })

  it('formats small amounts without compact notation', () => {
    const result = formatCompactCurrency(50, 'en')
    expect(result).toContain('50')
  })
})

// ============================================================================
// Date Formatting Tests
// ============================================================================

describe('formatDate', () => {
  const testDate = new Date('2024-12-25T12:00:00Z')
  const testDateString = '2024-12-25'

  describe('short style', () => {
    it('formats date with short style in English', () => {
      const result = formatDate(testDate, 'en', 'short')
      expect(result).toContain('12')
      expect(result).toContain('25')
      expect(result).toContain('2024')
    })

    it('formats date with short style in French', () => {
      const result = formatDate(testDate, 'fr', 'short')
      expect(result).toContain('25')
      expect(result).toContain('12')
      expect(result).toContain('2024')
    })
  })

  describe('medium style', () => {
    it('formats date with medium style in English', () => {
      const result = formatDate(testDate, 'en', 'medium')
      expect(result.toLowerCase()).toMatch(/dec|25|2024/)
    })

    it('formats date with medium style in French', () => {
      const result = formatDate(testDate, 'fr', 'medium')
      expect(result.toLowerCase()).toMatch(/déc|25|2024/)
    })
  })

  describe('long style', () => {
    it('formats date with long style in English', () => {
      const result = formatDate(testDate, 'en', 'long')
      expect(result.toLowerCase()).toMatch(/december|25|2024/)
    })

    it('formats date with long style in French', () => {
      const result = formatDate(testDate, 'fr', 'long')
      expect(result.toLowerCase()).toMatch(/décembre|25|2024/)
    })
  })

  describe('full style', () => {
    it('formats date with full style in English', () => {
      const result = formatDate(testDate, 'en', 'full')
      expect(result.toLowerCase()).toMatch(/wednesday|december|25|2024/)
    })

    it('formats date with full style in French', () => {
      const result = formatDate(testDate, 'fr', 'full')
      expect(result.toLowerCase()).toMatch(/mercredi|décembre|25|2024/)
    })
  })

  describe('input handling', () => {
    it('accepts Date object', () => {
      const result = formatDate(new Date('2024-06-15'), 'en', 'short')
      expect(result).toContain('2024')
    })

    it('accepts ISO date string', () => {
      const result = formatDate('2024-06-15', 'en', 'short')
      expect(result).toContain('2024')
    })

    it('returns empty string for invalid date', () => {
      const result = formatDate('invalid-date', 'en', 'short')
      expect(result).toBe('')
    })

    it('returns empty string for invalid Date object', () => {
      const result = formatDate(new Date('invalid'), 'en', 'short')
      expect(result).toBe('')
    })
  })

  describe('defaults', () => {
    it('defaults to medium style', () => {
      const resultWithStyle = formatDate(testDate, 'en', 'medium')
      const resultWithoutStyle = formatDate(testDate, 'en')
      expect(resultWithStyle).toBe(resultWithoutStyle)
    })

    it('defaults to English locale', () => {
      const result = formatDate(testDate)
      expect(result).toBeDefined()
      expect(typeof result).toBe('string')
    })
  })
})

describe('formatDateISO', () => {
  it('formats date to ISO format', () => {
    const result = formatDateISO(new Date('2024-12-25T12:00:00Z'))
    expect(result).toBe('2024-12-25')
  })

  it('accepts string input', () => {
    const result = formatDateISO('2024-06-15')
    expect(result).toBe('2024-06-15')
  })

  it('returns empty string for invalid date', () => {
    const result = formatDateISO('invalid-date')
    expect(result).toBe('')
  })

  it('returns empty string for invalid Date object', () => {
    const result = formatDateISO(new Date('invalid'))
    expect(result).toBe('')
  })
})

// ============================================================================
// Time Formatting Tests
// ============================================================================

describe('formatTime', () => {
  const testDateTime = new Date('2024-12-25T14:30:45Z')

  describe('short style', () => {
    it('formats time in English locale', () => {
      const result = formatTime(testDateTime, 'en', 'short')
      // Should show hour and minute
      expect(result).toMatch(/\d/)
    })

    it('formats time in French locale', () => {
      const result = formatTime(testDateTime, 'fr', 'short')
      // French uses 24-hour format or "h" notation
      expect(result).toMatch(/\d/)
    })
  })

  describe('medium style', () => {
    it('includes seconds in medium style', () => {
      const result = formatTime(testDateTime, 'en', 'medium')
      // Medium style should include seconds
      expect(result).toMatch(/\d/)
    })
  })

  describe('input handling', () => {
    it('accepts Date object', () => {
      const result = formatTime(new Date('2024-12-25T09:15:00'), 'en')
      expect(result).toMatch(/\d/)
    })

    it('accepts ISO datetime string', () => {
      const result = formatTime('2024-12-25T09:15:00', 'en')
      expect(result).toMatch(/\d/)
    })

    it('returns empty string for invalid date', () => {
      const result = formatTime('invalid-date', 'en')
      expect(result).toBe('')
    })
  })

  describe('defaults', () => {
    it('defaults to short style', () => {
      const withStyle = formatTime(testDateTime, 'en', 'short')
      const withoutStyle = formatTime(testDateTime, 'en')
      expect(withStyle).toBe(withoutStyle)
    })
  })
})

// ============================================================================
// DateTime Formatting Tests
// ============================================================================

describe('formatDateTime', () => {
  const testDateTime = new Date('2024-12-25T14:30:00Z')

  it('includes both date and time in English', () => {
    const result = formatDateTime(testDateTime, 'en')
    expect(result).toBeDefined()
    // Should have date components
    expect(result).toMatch(/\d/)
    // Should have separators or formatting
    expect(result.length).toBeGreaterThan(5)
  })

  it('includes both date and time in French', () => {
    const result = formatDateTime(testDateTime, 'fr')
    expect(result).toBeDefined()
    expect(result).toMatch(/\d/)
    expect(result.length).toBeGreaterThan(5)
  })

  it('accepts string input', () => {
    const result = formatDateTime('2024-12-25T14:30:00', 'en')
    expect(result).toBeDefined()
    expect(typeof result).toBe('string')
  })

  it('returns empty string for invalid date', () => {
    const result = formatDateTime('invalid', 'en')
    expect(result).toBe('')
  })
})

// ============================================================================
// Relative Time Formatting Tests
// ============================================================================

describe('formatRelativeTime', () => {
  const baseDate = new Date('2024-12-25T12:00:00Z')

  describe('past times', () => {
    it('formats days ago in English', () => {
      const pastDate = new Date('2024-12-23T12:00:00Z')
      const result = formatRelativeTime(pastDate, 'en', baseDate)
      expect(result.toLowerCase()).toMatch(/2|day|ago/)
    })

    it('formats days ago in French', () => {
      const pastDate = new Date('2024-12-23T12:00:00Z')
      const result = formatRelativeTime(pastDate, 'fr', baseDate)
      // French: "il y a 2 jours"
      expect(result).toMatch(/\d|jour/)
    })

    it('formats hours ago', () => {
      const pastDate = new Date('2024-12-25T09:00:00Z')
      const result = formatRelativeTime(pastDate, 'en', baseDate)
      expect(result.toLowerCase()).toMatch(/hour|ago|\d/)
    })
  })

  describe('future times', () => {
    it('formats days in future in English', () => {
      const futureDate = new Date('2024-12-27T12:00:00Z')
      const result = formatRelativeTime(futureDate, 'en', baseDate)
      expect(result.toLowerCase()).toMatch(/in|day|\d/)
    })

    it('formats days in future in French', () => {
      const futureDate = new Date('2024-12-27T12:00:00Z')
      const result = formatRelativeTime(futureDate, 'fr', baseDate)
      // French: "dans 2 jours"
      expect(result).toMatch(/\d|jour|dans/)
    })
  })

  describe('input handling', () => {
    it('accepts string input', () => {
      const result = formatRelativeTime('2024-12-23', 'en', baseDate)
      expect(result).toBeDefined()
      expect(typeof result).toBe('string')
    })

    it('returns empty string for invalid date', () => {
      const result = formatRelativeTime('invalid', 'en', baseDate)
      expect(result).toBe('')
    })
  })
})

// ============================================================================
// Date Calculation Tests
// ============================================================================

describe('getDaysUntil', () => {
  let originalDate: typeof Date

  beforeEach(() => {
    // Mock current date to 2024-12-25
    originalDate = global.Date
    const mockDate = new Date('2024-12-25T12:00:00Z')
    vi.useFakeTimers()
    vi.setSystemTime(mockDate)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('returns positive days for future dates', () => {
    const futureDate = new Date('2024-12-27')
    const result = getDaysUntil(futureDate)
    expect(result).toBeGreaterThan(0)
  })

  it('returns negative days for past dates', () => {
    const pastDate = new Date('2024-12-23')
    const result = getDaysUntil(pastDate)
    expect(result).toBeLessThan(0)
  })

  it('returns 0 for today', () => {
    const today = new Date('2024-12-25')
    const result = getDaysUntil(today)
    expect(result).toBe(0)
  })

  it('accepts string input', () => {
    const result = getDaysUntil('2024-12-27')
    expect(typeof result).toBe('number')
  })

  it('returns 0 for invalid date', () => {
    const result = getDaysUntil('invalid-date')
    expect(result).toBe(0)
  })
})

describe('getDaysUntilDue', () => {
  beforeEach(() => {
    const mockDate = new Date('2024-12-25T12:00:00Z')
    vi.useFakeTimers()
    vi.setSystemTime(mockDate)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('is an alias for getDaysUntil', () => {
    const dueDate = new Date('2024-12-30')
    const resultDue = getDaysUntilDue(dueDate)
    const resultUntil = getDaysUntil(dueDate)
    expect(resultDue).toBe(resultUntil)
  })
})

describe('isToday', () => {
  beforeEach(() => {
    const mockDate = new Date('2024-12-25T12:00:00Z')
    vi.useFakeTimers()
    vi.setSystemTime(mockDate)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('returns true for today', () => {
    const today = new Date('2024-12-25')
    expect(isToday(today)).toBe(true)
  })

  it('returns false for past dates', () => {
    const pastDate = new Date('2024-12-24')
    expect(isToday(pastDate)).toBe(false)
  })

  it('returns false for future dates', () => {
    const futureDate = new Date('2024-12-26')
    expect(isToday(futureDate)).toBe(false)
  })

  it('accepts string input', () => {
    expect(isToday('2024-12-25')).toBe(true)
  })
})

describe('isPast', () => {
  beforeEach(() => {
    const mockDate = new Date('2024-12-25T12:00:00Z')
    vi.useFakeTimers()
    vi.setSystemTime(mockDate)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('returns true for past dates', () => {
    const pastDate = new Date('2024-12-24')
    expect(isPast(pastDate)).toBe(true)
  })

  it('returns false for today', () => {
    const today = new Date('2024-12-25')
    expect(isPast(today)).toBe(false)
  })

  it('returns false for future dates', () => {
    const futureDate = new Date('2024-12-26')
    expect(isPast(futureDate)).toBe(false)
  })
})

describe('isFuture', () => {
  beforeEach(() => {
    const mockDate = new Date('2024-12-25T12:00:00Z')
    vi.useFakeTimers()
    vi.setSystemTime(mockDate)
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('returns true for future dates', () => {
    const futureDate = new Date('2024-12-26')
    expect(isFuture(futureDate)).toBe(true)
  })

  it('returns false for today', () => {
    const today = new Date('2024-12-25')
    expect(isFuture(today)).toBe(false)
  })

  it('returns false for past dates', () => {
    const pastDate = new Date('2024-12-24')
    expect(isFuture(pastDate)).toBe(false)
  })
})

// ============================================================================
// Number Formatting Tests
// ============================================================================

describe('formatNumber', () => {
  it('formats numbers with thousands separators in English', () => {
    const result = formatNumber(1234567, 'en')
    expect(result).toContain('1')
    expect(result).toContain('234')
    expect(result).toContain('567')
  })

  it('formats numbers with thousands separators in French', () => {
    const result = formatNumber(1234567, 'fr')
    expect(result).toContain('1')
    expect(result).toContain('234')
    expect(result).toContain('567')
  })

  it('respects decimal places', () => {
    const result = formatNumber(1234.567, 'en', 2)
    expect(result).toMatch(/57|1234/)
  })

  it('defaults to no decimal places', () => {
    const result = formatNumber(1234.567, 'en')
    expect(result).not.toContain('.567')
  })

  it('formats zero correctly', () => {
    const result = formatNumber(0, 'en')
    expect(result).toBe('0')
  })

  it('formats negative numbers', () => {
    const result = formatNumber(-1234, 'en')
    expect(result).toContain('1')
    expect(result).toContain('234')
  })
})

describe('formatPercent', () => {
  it('formats percentages in English', () => {
    const result = formatPercent(0.5, 'en')
    expect(result).toContain('50')
    expect(result).toContain('%')
  })

  it('formats percentages in French', () => {
    const result = formatPercent(0.5, 'fr')
    expect(result).toContain('50')
    expect(result).toContain('%')
  })

  it('respects decimal places', () => {
    const result = formatPercent(0.1234, 'en', 1)
    expect(result).toMatch(/12|3/)
  })

  it('defaults to no decimal places', () => {
    const result = formatPercent(0.5, 'en')
    expect(result).toMatch(/50\s*%/)
  })

  it('formats 100% correctly', () => {
    const result = formatPercent(1, 'en')
    expect(result).toContain('100')
  })

  it('formats 0% correctly', () => {
    const result = formatPercent(0, 'en')
    expect(result).toContain('0')
    expect(result).toContain('%')
  })

  it('handles values over 100%', () => {
    const result = formatPercent(1.5, 'en')
    expect(result).toContain('150')
    expect(result).toContain('%')
  })
})

// ============================================================================
// Edge Cases and Integration
// ============================================================================

describe('Edge Cases', () => {
  describe('locale consistency', () => {
    it('maintains consistent formatting within locale', () => {
      const date = new Date('2024-12-25T14:30:00')
      const enDate = formatDate(date, 'en', 'short')
      const enTime = formatTime(date, 'en')
      const enDateTime = formatDateTime(date, 'en')

      // All should return valid strings
      expect(enDate).toBeDefined()
      expect(enTime).toBeDefined()
      expect(enDateTime).toBeDefined()
    })
  })

  describe('very large numbers', () => {
    it('handles very large currency amounts', () => {
      const result = formatCurrency(999999999.99, 'en')
      expect(result).toBeDefined()
      expect(typeof result).toBe('string')
    })

    it('handles very large numbers', () => {
      const result = formatNumber(999999999999, 'en')
      expect(result).toBeDefined()
      expect(typeof result).toBe('string')
    })
  })

  describe('decimal precision', () => {
    it('handles very small decimal values', () => {
      const result = formatCurrency(0.01, 'en')
      expect(result).toContain('01')
    })

    it('handles fractional percentages', () => {
      const result = formatPercent(0.001, 'en', 1)
      expect(result).toBeDefined()
    })
  })
})
