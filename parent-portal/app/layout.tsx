import './globals.css';

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
  return children;
}
