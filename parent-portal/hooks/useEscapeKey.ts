import { useEffect } from 'react';

/**
 * Hook to handle Escape key press
 *
 * @param onEscape - Callback function to execute when Escape is pressed
 * @param isActive - Whether the escape handler should be active (default: true)
 */
export function useEscapeKey(onEscape: () => void, isActive = true) {
  useEffect(() => {
    if (!isActive) return;

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onEscape();
      }
    };

    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [onEscape, isActive]);
}
