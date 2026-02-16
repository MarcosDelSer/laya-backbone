'use client';

import { useState, useRef, useEffect } from 'react';
import { useLocale } from 'next-intl';
import { useRouter, usePathname } from 'next/navigation';
import { locales, localeLabels, type Locale } from '../i18n';

/**
 * Language configuration for the switcher.
 */
interface Language {
  code: Locale;
  label: string;
  flag: string;
}

/**
 * Available languages with their display information.
 */
const languages: Language[] = locales.map((locale) => ({
  code: locale,
  label: localeLabels[locale],
  flag: locale === 'en' ? '🇬🇧' : '🇫🇷',
}));

/**
 * LanguageSwitcher component for switching between EN and FR locales.
 *
 * Provides a dropdown UI for language selection that persists the user's
 * preference via next-intl's routing (which stores it in a cookie).
 *
 * @example
 * ```tsx
 * <LanguageSwitcher />
 * ```
 */
export function LanguageSwitcher() {
  const locale = useLocale();
  const router = useRouter();
  const pathname = usePathname();
  const [isOpen, setIsOpen] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);

  // Find the current language configuration
  const currentLanguage =
    languages.find((lang) => lang.code === locale) || languages[0];

  /**
   * Handle language selection and navigate to the new locale.
   */
  const handleSelect = (language: Language) => {
    if (language.code !== locale) {
      // Get the current path without the locale prefix
      // The pathname from next/navigation in App Router includes the locale
      const pathWithoutLocale = pathname.replace(`/${locale}`, '') || '/';

      // Navigate to the same page with the new locale
      router.push(`/${language.code}${pathWithoutLocale}`);
    }
    setIsOpen(false);
  };

  /**
   * Close dropdown when clicking outside.
   */
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (
        dropdownRef.current &&
        !dropdownRef.current.contains(event.target as Node)
      ) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, []);

  /**
   * Close dropdown on escape key.
   */
  useEffect(() => {
    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setIsOpen(false);
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => {
      document.removeEventListener('keydown', handleEscape);
    };
  }, []);

  return (
    <div className="relative" ref={dropdownRef}>
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        className="flex items-center space-x-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
        aria-label="Select language"
        aria-expanded={isOpen}
        aria-haspopup="listbox"
      >
        <span className="text-base" role="img" aria-hidden="true">
          {currentLanguage.flag}
        </span>
        <span className="hidden sm:inline">{currentLanguage.label}</span>
        <span className="sm:hidden">{currentLanguage.code.toUpperCase()}</span>
        <svg
          className={`h-4 w-4 text-gray-400 transition-transform ${
            isOpen ? 'rotate-180' : ''
          }`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 9l-7 7-7-7"
          />
        </svg>
      </button>

      {isOpen && (
        <div
          className="absolute right-0 z-10 mt-2 w-40 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none"
          role="listbox"
          aria-label="Select language"
        >
          <div className="p-2">
            <p className="px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500">
              Language
            </p>
            {languages.map((language) => (
              <button
                key={language.code}
                onClick={() => handleSelect(language)}
                role="option"
                aria-selected={currentLanguage.code === language.code}
                className={`flex w-full items-center space-x-3 rounded-md px-3 py-2 text-sm ${
                  currentLanguage.code === language.code
                    ? 'bg-primary-50 text-primary-700'
                    : 'text-gray-700 hover:bg-gray-50'
                }`}
              >
                <span className="text-base" role="img" aria-hidden="true">
                  {language.flag}
                </span>
                <span className="font-medium">{language.label}</span>
                {currentLanguage.code === language.code && (
                  <svg
                    className="ml-auto h-5 w-5 text-primary-600"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                    aria-hidden="true"
                  >
                    <path
                      fillRule="evenodd"
                      d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                      clipRule="evenodd"
                    />
                  </svg>
                )}
              </button>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
