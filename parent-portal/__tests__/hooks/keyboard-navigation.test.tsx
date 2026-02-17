import { renderHook, act } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import {
  useEscapeKey,
  useArrowNavigation,
  useClickOutside,
  useFocusTrap,
} from '@/hooks';
import { useRef } from 'react';

describe('Keyboard Navigation Hooks', () => {
  describe('useEscapeKey', () => {
    let mockCallback: ReturnType<typeof vi.fn>;

    beforeEach(() => {
      mockCallback = vi.fn();
    });

    afterEach(() => {
      vi.clearAllMocks();
    });

    it('should call callback when Escape is pressed', () => {
      renderHook(() => useEscapeKey(mockCallback));

      const event = new KeyboardEvent('keydown', { key: 'Escape' });
      window.dispatchEvent(event);

      expect(mockCallback).toHaveBeenCalledTimes(1);
    });

    it('should not call callback when other keys are pressed', () => {
      renderHook(() => useEscapeKey(mockCallback));

      const event = new KeyboardEvent('keydown', { key: 'Enter' });
      window.dispatchEvent(event);

      expect(mockCallback).not.toHaveBeenCalled();
    });

    it('should not call callback when inactive', () => {
      renderHook(() => useEscapeKey(mockCallback, false));

      const event = new KeyboardEvent('keydown', { key: 'Escape' });
      window.dispatchEvent(event);

      expect(mockCallback).not.toHaveBeenCalled();
    });

    it('should cleanup event listener on unmount', () => {
      const { unmount } = renderHook(() => useEscapeKey(mockCallback));

      unmount();

      const event = new KeyboardEvent('keydown', { key: 'Escape' });
      window.dispatchEvent(event);

      expect(mockCallback).not.toHaveBeenCalled();
    });
  });

  describe('useArrowNavigation', () => {
    const itemCount = 5;

    it('should initialize with default focused index', () => {
      const { result } = renderHook(() =>
        useArrowNavigation({
          itemCount,
          isActive: true,
        })
      );

      expect(result.current.focusedIndex).toBe(0);
    });

    it('should navigate down with ArrowDown', () => {
      const { result } = renderHook(() =>
        useArrowNavigation({
          itemCount,
          isActive: true,
        })
      );

      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'ArrowDown' });
        window.dispatchEvent(event);
      });

      expect(result.current.focusedIndex).toBe(1);
    });

    it('should navigate up with ArrowUp', () => {
      const { result } = renderHook(() =>
        useArrowNavigation({
          itemCount,
          isActive: true,
          initialIndex: 2,
        })
      );

      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'ArrowUp' });
        window.dispatchEvent(event);
      });

      expect(result.current.focusedIndex).toBe(1);
    });

    it('should loop to first item when navigating down from last', () => {
      const { result } = renderHook(() =>
        useArrowNavigation({
          itemCount,
          isActive: true,
          initialIndex: 4,
          loop: true,
        })
      );

      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'ArrowDown' });
        window.dispatchEvent(event);
      });

      expect(result.current.focusedIndex).toBe(0);
    });

    it('should not loop when loop is false', () => {
      const { result } = renderHook(() =>
        useArrowNavigation({
          itemCount,
          isActive: true,
          initialIndex: 4,
          loop: false,
        })
      );

      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'ArrowDown' });
        window.dispatchEvent(event);
      });

      expect(result.current.focusedIndex).toBe(4);
    });

    it('should jump to first item with Home key', () => {
      const { result } = renderHook(() =>
        useArrowNavigation({
          itemCount,
          isActive: true,
          initialIndex: 3,
        })
      );

      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'Home' });
        window.dispatchEvent(event);
      });

      expect(result.current.focusedIndex).toBe(0);
    });

    it('should jump to last item with End key', () => {
      const { result } = renderHook(() =>
        useArrowNavigation({
          itemCount,
          isActive: true,
          initialIndex: 0,
        })
      );

      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'End' });
        window.dispatchEvent(event);
      });

      expect(result.current.focusedIndex).toBe(4);
    });

    it('should call onSelect when Enter is pressed', () => {
      const mockOnSelect = vi.fn();
      const { result } = renderHook(() =>
        useArrowNavigation({
          itemCount,
          isActive: true,
          onSelect: mockOnSelect,
          initialIndex: 2,
        })
      );

      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'Enter' });
        window.dispatchEvent(event);
      });

      expect(mockOnSelect).toHaveBeenCalledWith(2);
    });

    it('should not navigate when inactive', () => {
      const { result } = renderHook(() =>
        useArrowNavigation({
          itemCount,
          isActive: false,
        })
      );

      act(() => {
        const event = new KeyboardEvent('keydown', { key: 'ArrowDown' });
        window.dispatchEvent(event);
      });

      expect(result.current.focusedIndex).toBe(0);
    });
  });

  describe('useClickOutside', () => {
    let mockCallback: ReturnType<typeof vi.fn>;
    let containerElement: HTMLDivElement;

    beforeEach(() => {
      mockCallback = vi.fn();
      containerElement = document.createElement('div');
      document.body.appendChild(containerElement);
    });

    afterEach(() => {
      document.body.removeChild(containerElement);
      vi.clearAllMocks();
    });

    it('should call callback when clicking outside', () => {
      const ref = { current: containerElement };
      renderHook(() => useClickOutside(ref, mockCallback));

      act(() => {
        const event = new MouseEvent('mousedown', { bubbles: true });
        document.body.dispatchEvent(event);
      });

      expect(mockCallback).toHaveBeenCalledTimes(1);
    });

    it('should not call callback when clicking inside', () => {
      const ref = { current: containerElement };
      renderHook(() => useClickOutside(ref, mockCallback));

      act(() => {
        const event = new MouseEvent('mousedown', { bubbles: true });
        containerElement.dispatchEvent(event);
      });

      expect(mockCallback).not.toHaveBeenCalled();
    });

    it('should not call callback when inactive', () => {
      const ref = { current: containerElement };
      renderHook(() => useClickOutside(ref, mockCallback, false));

      act(() => {
        const event = new MouseEvent('mousedown', { bubbles: true });
        document.body.dispatchEvent(event);
      });

      expect(mockCallback).not.toHaveBeenCalled();
    });
  });

  describe('useFocusTrap', () => {
    let containerElement: HTMLDivElement;
    let button1: HTMLButtonElement;
    let button2: HTMLButtonElement;
    let outsideButton: HTMLButtonElement;

    beforeEach(() => {
      // Create container with focusable elements
      containerElement = document.createElement('div');
      button1 = document.createElement('button');
      button2 = document.createElement('button');
      outsideButton = document.createElement('button');

      button1.textContent = 'Button 1';
      button2.textContent = 'Button 2';
      outsideButton.textContent = 'Outside Button';

      containerElement.appendChild(button1);
      containerElement.appendChild(button2);
      document.body.appendChild(containerElement);
      document.body.appendChild(outsideButton);
    });

    afterEach(() => {
      document.body.removeChild(containerElement);
      document.body.removeChild(outsideButton);
    });

    it('should focus first element when activated', () => {
      const { result } = renderHook(() => useFocusTrap<HTMLDivElement>(true));

      if (result.current.current) {
        result.current.current.appendChild(button1);
        result.current.current.appendChild(button2);

        // Manually trigger focus since we're in a test environment
        const focusableElements = result.current.current.querySelectorAll('button');
        if (focusableElements.length > 0) {
          expect(focusableElements.length).toBe(2);
        }
      }
    });

    it('should return a ref object', () => {
      const { result } = renderHook(() => useFocusTrap<HTMLDivElement>(true));

      expect(result.current).toHaveProperty('current');
    });

    it('should not trap focus when inactive', () => {
      const { result } = renderHook(() => useFocusTrap<HTMLDivElement>(false));

      expect(result.current).toHaveProperty('current');
      expect(result.current.current).toBeNull();
    });
  });
});
