import React from 'react'
import '@testing-library/jest-dom'
import { vi } from 'vitest'

// Mock next-intl for i18n support
// English translations for testing
const mockTranslations: Record<string, Record<string, unknown>> = {
  common: {
    appName: 'LAYA',
    loading: 'Loading...',
    back: 'Back',
    backToDashboard: 'Back to Dashboard',
    viewAll: 'View all',
    filter: 'Filter',
    export: 'Export',
    cancel: 'Cancel',
    submit: 'Submit',
    save: 'Save',
    delete: 'Delete',
    edit: 'Edit',
    close: 'Close',
    all: 'All',
    clear: 'Clear',
    loadMore: 'Load More',
    selectChild: 'Select Child',
    today: 'Today',
    yesterday: 'Yesterday',
    justNow: 'Just now',
    minutesAgo: '{count}m ago',
    status: {
      checkedIn: 'Checked In',
      checkedOut: 'Checked Out',
      absent: 'Absent',
      pending: 'Pending',
      signed: 'Signed',
      paid: 'Paid',
      overdue: 'Overdue',
    },
    time: {
      since: 'Since {time}',
      daysOverdue: '{count} days overdue',
      daysRemaining: '{count} days remaining',
      dueToday: 'Due today',
    },
    entry: '{count} entries',
    photo: 'Photo',
    photos: 'Photos',
    viewPhotoNumber: 'View photo {number}',
  },
}

/**
 * Gets a nested value from an object using dot notation
 * e.g., getNestedValue({ a: { b: 'value' } }, 'a.b') returns 'value'
 */
function getNestedValue(obj: Record<string, unknown>, path: string): unknown {
  const keys = path.split('.')
  let current: unknown = obj

  for (const key of keys) {
    if (current && typeof current === 'object' && key in (current as Record<string, unknown>)) {
      current = (current as Record<string, unknown>)[key]
    } else {
      return path // Return the key if not found
    }
  }

  return current
}

/**
 * Mock translation function that handles nested keys
 */
function createMockT(namespace?: string) {
  return (key: string, params?: Record<string, unknown>): string => {
    // Build the full key path
    const fullKey = namespace ? `${namespace}.${key}` : key

    // Get the translation value
    const value = getNestedValue(mockTranslations, fullKey)

    if (typeof value === 'string') {
      // Handle simple parameter replacement
      let result = value
      if (params) {
        Object.entries(params).forEach(([paramKey, paramValue]) => {
          result = result.replace(new RegExp(`\\{${paramKey}\\}`, 'g'), String(paramValue))
        })
      }
      return result
    }

    // Return the key if no translation found
    return fullKey
  }
}

vi.mock('next-intl', () => ({
  useTranslations: (namespace?: string) => createMockT(namespace),
  useLocale: () => 'en',
  useMessages: () => mockTranslations,
  useTimeZone: () => 'America/New_York',
  useNow: () => new Date(),
  NextIntlClientProvider: ({ children }: { children: React.ReactNode }) => children,
}))

vi.mock('next-intl/server', () => ({
  getTranslations: () => Promise.resolve(createMockT()),
  getMessages: () => Promise.resolve(mockTranslations),
  getLocale: () => Promise.resolve('en'),
  getTimeZone: () => Promise.resolve('America/New_York'),
  getNow: () => Promise.resolve(new Date()),
}))

// Mock Next.js router
vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: vi.fn(),
    replace: vi.fn(),
    prefetch: vi.fn(),
    back: vi.fn(),
    forward: vi.fn(),
  }),
  usePathname: () => '/',
  useSearchParams: () => new URLSearchParams(),
}))

// Mock Next.js Image component
vi.mock('next/image', () => ({
  default: ({ src, alt, ...props }: { src: string; alt: string; [key: string]: unknown }) => {
    // eslint-disable-next-line @next/next/no-img-element
    return <img src={src} alt={alt} {...props} />
  },
}))

// Suppress console errors during tests (optional - can be removed if you want to see errors)
// Uncomment below if you want to suppress console.error in tests:
// const originalError = console.error
// beforeAll(() => {
//   console.error = (...args) => {
//     if (typeof args[0] === 'string' && args[0].includes('Warning:')) return
//     originalError.call(console, ...args)
//   }
// })
// afterAll(() => {
//   console.error = originalError
// })
