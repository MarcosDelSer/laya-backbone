'use client';

/**
 * ScreenReaderAnnouncer Component
 *
 * A global component that provides aria-live regions for screen reader announcements.
 * This component should be included once in the app layout and will be used by
 * the useAnnounce hook to make announcements.
 *
 * The component creates two live regions:
 * - polite: Announcements that wait for the screen reader to finish speaking
 * - assertive: Announcements that interrupt the screen reader (use sparingly)
 *
 * @example
 * ```tsx
 * // In your layout.tsx
 * <ScreenReaderAnnouncer />
 *
 * // In any component
 * const announce = useAnnounce();
 * announce('Data loaded successfully', 'polite');
 * ```
 */
export function ScreenReaderAnnouncer() {
  return (
    <>
      {/* Polite announcer - waits for screen reader to finish */}
      <div
        id="sr-announcer-polite"
        role="status"
        aria-live="polite"
        aria-atomic="true"
        className="sr-only"
      />

      {/* Assertive announcer - interrupts screen reader (use sparingly) */}
      <div
        id="sr-announcer-assertive"
        role="alert"
        aria-live="assertive"
        aria-atomic="true"
        className="sr-only"
      />
    </>
  );
}
