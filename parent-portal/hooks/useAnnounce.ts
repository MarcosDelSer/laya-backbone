import { useCallback } from 'react';

/**
 * Hook for making screen reader announcements
 *
 * This hook provides a way to make announcements to screen readers
 * without changing the visual UI. Useful for notifying users of
 * dynamic changes like loading states, success/error messages, etc.
 *
 * @example
 * ```tsx
 * const announce = useAnnounce();
 *
 * // Polite announcement (waits for screen reader to finish)
 * announce('Message sent successfully', 'polite');
 *
 * // Assertive announcement (interrupts screen reader)
 * announce('Error: Form submission failed', 'assertive');
 * ```
 */
export function useAnnounce() {
  const announce = useCallback((message: string, priority: 'polite' | 'assertive' = 'polite') => {
    // Find or create the announcement container
    let announcer = document.getElementById(`sr-announcer-${priority}`);

    if (!announcer) {
      announcer = document.createElement('div');
      announcer.id = `sr-announcer-${priority}`;
      announcer.setAttribute('role', 'status');
      announcer.setAttribute('aria-live', priority);
      announcer.setAttribute('aria-atomic', 'true');
      announcer.className = 'sr-only';
      document.body.appendChild(announcer);
    }

    // Clear previous announcement
    announcer.textContent = '';

    // Add new announcement after a brief delay to ensure screen readers pick it up
    setTimeout(() => {
      if (announcer) {
        announcer.textContent = message;
      }
    }, 100);

    // Clear the announcement after it's been read (to avoid repetition)
    setTimeout(() => {
      if (announcer) {
        announcer.textContent = '';
      }
    }, 3000);
  }, []);

  return announce;
}

/**
 * Utility function to make screen reader announcements imperatively
 * Use the hook version (useAnnounce) when inside a React component
 */
export function announce(message: string, priority: 'polite' | 'assertive' = 'polite') {
  let announcer = document.getElementById(`sr-announcer-${priority}`);

  if (!announcer) {
    announcer = document.createElement('div');
    announcer.id = `sr-announcer-${priority}`;
    announcer.setAttribute('role', 'status');
    announcer.setAttribute('aria-live', priority);
    announcer.setAttribute('aria-atomic', 'true');
    announcer.className = 'sr-only';
    document.body.appendChild(announcer);
  }

  announcer.textContent = '';

  setTimeout(() => {
    if (announcer) {
      announcer.textContent = message;
    }
  }, 100);

  setTimeout(() => {
    if (announcer) {
      announcer.textContent = '';
    }
  }, 3000);
}
