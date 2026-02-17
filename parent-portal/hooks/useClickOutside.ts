import { useEffect, RefObject } from 'react';

/**
 * Hook to detect clicks outside of a referenced element
 *
 * @param ref - React ref to the element to monitor
 * @param onClickOutside - Callback function to execute when clicking outside
 * @param isActive - Whether the click outside handler should be active (default: true)
 */
export function useClickOutside<T extends HTMLElement>(
  ref: RefObject<T>,
  onClickOutside: () => void,
  isActive = true
) {
  useEffect(() => {
    if (!isActive) return;

    const handleClickOutside = (event: MouseEvent) => {
      if (ref.current && !ref.current.contains(event.target as Node)) {
        onClickOutside();
      }
    };

    // Use capture phase to ensure we get the event before any stopPropagation
    document.addEventListener('mousedown', handleClickOutside, true);

    return () => {
      document.removeEventListener('mousedown', handleClickOutside, true);
    };
  }, [ref, onClickOutside, isActive]);
}
