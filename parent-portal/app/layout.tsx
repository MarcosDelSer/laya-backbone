import './globals.css';
import { Navigation } from '@/components/Navigation';
import { SkipNavigation } from '@/components/SkipNavigation';
import { ScreenReaderAnnouncer } from '@/components/ScreenReaderAnnouncer';

const inter = Inter({ subsets: ['latin'], variable: '--font-inter' });

export const metadata: Metadata = {
  title: 'LAYA Parent Portal',
  description: 'Parent portal for LAYA Kindergarten & Childcare Management Platform',
};

/**
 * Root layout serves as a minimal pass-through.
 *
 * The full HTML structure with dynamic lang attribute is in the
 * [locale]/layout.tsx to support i18n with next-intl.
 */
export default function RootLayout({
  children,
}: Readonly<{
  children: React.ReactNode;
}>) {
  return (
    <html lang="en">
      <body className={`${inter.variable} font-sans antialiased bg-gray-50`}>
        <ScreenReaderAnnouncer />
        <SkipNavigation />
        <div className="min-h-screen flex flex-col">
          <Navigation />
          <main id="main-content" className="flex-1" tabIndex={-1}>
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
      </body>
    </html>
  );
}
