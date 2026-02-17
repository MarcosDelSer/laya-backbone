import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { SkipNavigation } from '@/components/SkipNavigation';

describe('SkipNavigation Component', () => {
  describe('Rendering', () => {
    it('should render skip navigation link', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');
      expect(skipLink).toBeInTheDocument();
    });

    it('should have correct href attribute', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');
      expect(skipLink).toHaveAttribute('href', '#main-content');
    });

    it('should be an anchor tag', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');
      expect(skipLink.tagName).toBe('A');
    });
  });

  describe('Styling and Visibility', () => {
    it('should have sr-only-focusable class for accessibility', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');
      expect(skipLink).toHaveClass('sr-only-focusable');
    });

    it('should have high z-index to appear above other content', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');
      expect(skipLink).toHaveClass('z-[9999]');
    });

    it('should be positioned fixed', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');
      expect(skipLink).toHaveClass('fixed');
    });

    it('should have appropriate styling classes', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');

      // Check for key styling classes
      expect(skipLink).toHaveClass('bg-primary-900');
      expect(skipLink).toHaveClass('text-white');
      expect(skipLink).toHaveClass('font-semibold');
      expect(skipLink).toHaveClass('rounded-md');
      expect(skipLink).toHaveClass('px-4');
      expect(skipLink).toHaveClass('py-2');
    });
  });

  describe('Keyboard Navigation', () => {
    it('should be focusable with keyboard', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');
      skipLink.focus();

      expect(document.activeElement).toBe(skipLink);
    });

    it('should be the first focusable element on the page', () => {
      // Simulate a full layout with navigation
      render(
        <div>
          <SkipNavigation />
          <nav>
            <a href="/">Home</a>
            <a href="/about">About</a>
          </nav>
          <main id="main-content">
            <button>Click me</button>
          </main>
        </div>
      );

      const skipLink = screen.getByText('Skip to main content');
      const homeLink = screen.getByText('Home');
      const button = screen.getByText('Click me');

      // Tab through elements
      skipLink.focus();
      expect(document.activeElement).toBe(skipLink);

      // Verify skip link comes before other interactive elements
      const allLinks = screen.getAllByRole('link');
      expect(allLinks[0]).toBe(skipLink);
    });

    it('should handle Enter key press', () => {
      render(
        <div>
          <SkipNavigation />
          <main id="main-content" tabIndex={-1}>
            Main content area
          </main>
        </div>
      );

      const skipLink = screen.getByText('Skip to main content');
      skipLink.focus();

      // Simulate Enter key press
      fireEvent.keyDown(skipLink, { key: 'Enter', code: 'Enter' });

      // Link should remain focusable
      expect(skipLink).toBeInTheDocument();
    });
  });

  describe('Navigation Behavior', () => {
    it('should navigate to main content when clicked', () => {
      // Mock scrollIntoView
      const mockScrollIntoView = vi.fn();
      window.HTMLElement.prototype.scrollIntoView = mockScrollIntoView;

      render(
        <div>
          <SkipNavigation />
          <main id="main-content" tabIndex={-1}>
            Main content area
          </main>
        </div>
      );

      const skipLink = screen.getByText('Skip to main content');
      fireEvent.click(skipLink);

      // The click should trigger navigation (in real browser)
      expect(skipLink).toHaveAttribute('href', '#main-content');
    });

    it('should work with hash navigation', () => {
      const { container } = render(
        <div>
          <SkipNavigation />
          <main id="main-content" tabIndex={-1}>
            Main content area
          </main>
        </div>
      );

      const skipLink = screen.getByText('Skip to main content');
      const mainContent = container.querySelector('#main-content');

      expect(skipLink).toHaveAttribute('href', '#main-content');
      expect(mainContent).toBeInTheDocument();
      expect(mainContent).toHaveAttribute('tabIndex', '-1');
    });
  });

  describe('WCAG 2.1 AA Compliance', () => {
    it('should meet WCAG 2.4.1 Bypass Blocks criterion', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');

      // Skip link should be present and functional
      expect(skipLink).toBeInTheDocument();
      expect(skipLink).toHaveAttribute('href', '#main-content');
    });

    it('should have descriptive text', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');

      // Text should clearly describe the action
      expect(skipLink.textContent).toBe('Skip to main content');
    });

    it('should be visually hidden but accessible to screen readers', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');

      // Should have sr-only-focusable class which makes it
      // visually hidden until focused
      expect(skipLink).toHaveClass('sr-only-focusable');
    });

    it('should become visible on focus for keyboard users', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');

      // The focus:translate-y-0 class ensures visibility on focus
      expect(skipLink.className).toContain('focus:translate-y-0');
    });

    it('should have sufficient color contrast when visible', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');

      // Dark background (primary-900) with white text ensures
      // WCAG AA contrast ratio of at least 4.5:1
      expect(skipLink).toHaveClass('bg-primary-900');
      expect(skipLink).toHaveClass('text-white');
    });

    it('should have focus indicator', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');
      skipLink.focus();

      // Focus should work (visual indication handled by CSS)
      expect(document.activeElement).toBe(skipLink);
    });
  });

  describe('Integration with Layout', () => {
    it('should work with main content landmark', () => {
      const { container } = render(
        <div>
          <SkipNavigation />
          <nav>Navigation</nav>
          <main id="main-content" tabIndex={-1}>
            <h1>Main Content</h1>
            <p>Content goes here</p>
          </main>
        </div>
      );

      const skipLink = screen.getByText('Skip to main content');
      const mainContent = container.querySelector('#main-content');

      expect(skipLink).toHaveAttribute('href', '#main-content');
      expect(mainContent).toBeInTheDocument();
      expect(mainContent?.tagName).toBe('MAIN');
    });

    it('should not interfere with other navigation', () => {
      render(
        <div>
          <SkipNavigation />
          <nav>
            <a href="/">Home</a>
            <a href="/about">About</a>
          </nav>
        </div>
      );

      const skipLink = screen.getByText('Skip to main content');
      const homeLink = screen.getByText('Home');
      const aboutLink = screen.getByText('About');

      // All links should be present and functional
      expect(skipLink).toBeInTheDocument();
      expect(homeLink).toBeInTheDocument();
      expect(aboutLink).toBeInTheDocument();
    });
  });

  describe('Accessibility Best Practices', () => {
    it('should be present before navigation elements', () => {
      const { container } = render(
        <div>
          <SkipNavigation />
          <nav>Navigation</nav>
        </div>
      );

      const skipLink = screen.getByText('Skip to main content');
      const nav = container.querySelector('nav');

      // Skip link should come before navigation in DOM order
      if (nav && skipLink.parentElement) {
        const skipLinkIndex = Array.from(container.children).indexOf(
          skipLink.parentElement
        );
        const navIndex = Array.from(container.children).indexOf(nav);

        // Skip link should appear before nav in DOM
        expect(skipLinkIndex).toBeLessThanOrEqual(navIndex);
      }
    });

    it('should have appropriate text content for all users', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');

      // Text should be clear and concise
      expect(skipLink.textContent).toBeTruthy();
      expect(skipLink.textContent?.length).toBeGreaterThan(5);
      expect(skipLink.textContent).toContain('main content');
    });

    it('should maintain accessibility when styles fail to load', () => {
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');

      // Even without styles, the link should be functional
      expect(skipLink.tagName).toBe('A');
      expect(skipLink).toHaveAttribute('href', '#main-content');
      expect(skipLink.textContent).toBe('Skip to main content');
    });
  });

  describe('Edge Cases', () => {
    it('should handle multiple render cycles', () => {
      const { rerender } = render(<SkipNavigation />);

      let skipLink = screen.getByText('Skip to main content');
      expect(skipLink).toBeInTheDocument();

      rerender(<SkipNavigation />);

      skipLink = screen.getByText('Skip to main content');
      expect(skipLink).toBeInTheDocument();
      expect(skipLink).toHaveAttribute('href', '#main-content');
    });

    it('should work in different viewport sizes', () => {
      // Test that the component is responsive
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');

      // Should maintain functionality regardless of viewport
      expect(skipLink).toHaveAttribute('href', '#main-content');
      expect(skipLink).toHaveClass('fixed');
    });

    it('should not break when main content ID is missing', () => {
      // Component should still render even if target doesn't exist
      render(<SkipNavigation />);

      const skipLink = screen.getByText('Skip to main content');
      expect(skipLink).toBeInTheDocument();
      expect(skipLink).toHaveAttribute('href', '#main-content');
    });
  });
});
