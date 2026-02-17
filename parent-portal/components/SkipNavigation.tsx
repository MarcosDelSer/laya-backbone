/**
 * Skip Navigation Component
 * Provides a keyboard-accessible link to skip to main content
 * WCAG 2.1 AA compliant - should be the first focusable element on the page
 */

export function SkipNavigation() {
  return (
    <a
      href="#main-content"
      className="skip-to-main sr-only-focusable fixed left-4 top-4 z-[9999] rounded-md bg-primary-900 px-4 py-2 font-semibold text-white shadow-lg transition-transform focus:translate-y-0"
    >
      Skip to main content
    </a>
  );
}
