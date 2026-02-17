import type { Metadata } from 'next';
import { Inter } from 'next/font/google';
import { notFound } from 'next/navigation';
import { NextIntlClientProvider } from 'next-intl';
import { getMessages, setRequestLocale } from 'next-intl/server';
import { locales, type Locale, isValidLocale } from '@/i18n';
import { Navigation } from '@/components/Navigation';

const inter = Inter({ subsets: ['latin'], variable: '--font-inter' });

/**
 * Props for the locale-aware layout component.
 */
interface LocaleLayoutProps {
  children: React.ReactNode;
  params: Promise<{ locale: string }>;
}

/**
 * Generate metadata for the locale-specific pages.
 */
export function generateMetadata(): Metadata {
  return {
    title: 'LAYA Parent Portal',
    description: 'Parent portal for LAYA Kindergarten & Childcare Management Platform',
  };
}

/**
 * Generate static params for all supported locales.
 * This enables static generation for each locale.
 */
export function generateStaticParams() {
  return locales.map((locale) => ({ locale }));
}

/**
 * Locale-aware layout component.
 *
 * This layout wraps all pages within the [locale] segment and provides:
 * - Locale validation (redirects to 404 if invalid)
 * - next-intl provider with messages for the current locale
 * - HTML lang attribute for accessibility and SEO
 * - Full page structure with Navigation and footer
 */
export default async function LocaleLayout({
  children,
  params,
}: LocaleLayoutProps) {
  const { locale } = await params;

  // Validate locale
  if (!isValidLocale(locale)) {
    notFound();
  }

  // Enable static rendering for this locale
  setRequestLocale(locale);

  // Load messages for the current locale
  const messages = await getMessages();

  return (
    <html lang={locale}>
      <body className={`${inter.variable} font-sans antialiased bg-gray-50`}>
        <NextIntlClientProvider locale={locale as Locale} messages={messages}>
          <div className="min-h-screen flex flex-col">
            <Navigation />
            <main className="flex-1">
              {children}
            </main>
            <footer className="border-t border-gray-200 bg-white py-6">
              <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <p className="text-center text-sm text-gray-500">
                  &copy; {new Date().getFullYear()} LAYA Kindergarten & Childcare. All rights reserved.
                </p>
              </div>
            </footer>
          </div>
        </NextIntlClientProvider>
      </body>
    </html>
  );
}
