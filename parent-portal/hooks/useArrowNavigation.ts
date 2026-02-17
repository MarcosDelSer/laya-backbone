import { useEffect, useState, useCallback } from 'react';

interface UseArrowNavigationProps {
  itemCount: number;
  isActive: boolean;
  onSelect?: (index: number) => void;
  loop?: boolean; // Whether to loop from last to first (default: true)
  initialIndex?: number;
}

/**
 * Hook to handle arrow key navigation in lists/menus
 *
 * @param itemCount - Total number of items in the list
 * @param isActive - Whether arrow navigation should be active
 * @param onSelect - Optional callback when Enter is pressed on an item
 * @param loop - Whether to loop from last to first item (default: true)
 * @param initialIndex - Initial selected index (default: 0)
 * @returns Object with current focused index and focus handlers
 */
export function useArrowNavigation({
  itemCount,
  isActive,
  onSelect,
  loop = true,
  initialIndex = 0,
}: UseArrowNavigationProps) {
  const [focusedIndex, setFocusedIndex] = useState(initialIndex);

  // Reset focused index when component becomes active
  useEffect(() => {
    if (isActive) {
      setFocusedIndex(initialIndex);
    }
  }, [isActive, initialIndex]);

  const handleKeyDown = useCallback(
    (event: KeyboardEvent) => {
      if (!isActive || itemCount === 0) return;

      switch (event.key) {
        case 'ArrowDown':
          event.preventDefault();
          setFocusedIndex((current) => {
            const next = current + 1;
            if (next >= itemCount) {
              return loop ? 0 : current;
            }
            return next;
          });
          break;

        case 'ArrowUp':
          event.preventDefault();
          setFocusedIndex((current) => {
            const prev = current - 1;
            if (prev < 0) {
              return loop ? itemCount - 1 : current;
            }
            return prev;
          });
          break;

        case 'Home':
          event.preventDefault();
          setFocusedIndex(0);
          break;

        case 'End':
          event.preventDefault();
          setFocusedIndex(itemCount - 1);
          break;

        case 'Enter':
        case ' ': // Space key
          event.preventDefault();
          if (onSelect) {
            onSelect(focusedIndex);
          }
          break;
      }
    },
    [isActive, itemCount, focusedIndex, onSelect, loop]
  );

  useEffect(() => {
    if (!isActive) return;

    window.addEventListener('keydown', handleKeyDown);

    return () => {
      window.removeEventListener('keydown', handleKeyDown);
    };
  }, [isActive, handleKeyDown]);

  return {
    focusedIndex,
    setFocusedIndex,
    resetFocus: () => setFocusedIndex(initialIndex),
  };
}
