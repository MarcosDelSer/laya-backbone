import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { Navigation } from '@/components/Navigation';
import { performLogout } from '@/lib/logout';

// Mock next/navigation
vi.mock('next/navigation', () => ({
  usePathname: () => '/',
  useRouter: () => ({
    push: vi.fn(),
  }),
}));

// Mock ChildSelector component
vi.mock('@/components/ChildSelector', () => ({
  ChildSelector: () => <div data-testid="child-selector">Child Selector</div>,
}));

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch;

describe('Logout Flow', () => {
  beforeEach(() => {
    // Reset mocks before each test
    mockFetch.mockReset();

    // Mock sessionStorage
    Object.defineProperty(window, 'sessionStorage', {
      value: {
        getItem: vi.fn(),
        setItem: vi.fn(),
        removeItem: vi.fn(),
        clear: vi.fn(),
      },
      writable: true,
    });

    // Mock window.location
    delete (window as any).location;
    window.location = { href: '' } as any;
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('performLogout utility', () => {
    it('calls logout API with correct parameters', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Logged out successfully' }),
      });

      // Mock window.location.href to prevent actual redirect
      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      await performLogout();

      expect(mockFetch).toHaveBeenCalledWith(
        '/api/auth/logout',
        expect.objectContaining({
          method: 'POST',
          credentials: 'include',
        })
      );
    });

    it('clears session storage before API call', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Logged out successfully' }),
      });

      // Mock sessionStorage.removeItem
      const removeItemSpy = vi.spyOn(window.sessionStorage, 'removeItem');

      // Mock window.location.href to prevent actual redirect
      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      await performLogout();

      expect(removeItemSpy).toHaveBeenCalledWith('redirectAfterLogin');
    });

    it('redirects to login page after successful logout', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Logged out successfully' }),
      });

      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      await performLogout();

      expect(redirectUrl).toBe('/auth/login');
    });

    it('redirects to login even if API call fails', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Network error'));

      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      await performLogout();

      expect(redirectUrl).toBe('/auth/login');
    });

    it('clears session data even if API call fails', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Network error'));

      const removeItemSpy = vi.spyOn(window.sessionStorage, 'removeItem');

      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      await performLogout();

      expect(removeItemSpy).toHaveBeenCalledWith('redirectAfterLogin');
    });
  });

  describe('Navigation Component Logout Button', () => {
    it('renders logout button', () => {
      render(<Navigation />);

      const logoutButton = screen.getByRole('button', { name: /logout/i });
      expect(logoutButton).toBeInTheDocument();
    });

    it('shows logout icon', () => {
      render(<Navigation />);

      const logoutButton = screen.getByRole('button', { name: /logout/i });
      const svg = logoutButton.querySelector('svg');

      expect(svg).toBeInTheDocument();
    });

    it('calls performLogout when logout button is clicked', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Logged out successfully' }),
      });

      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      render(<Navigation />);

      const logoutButton = screen.getByRole('button', { name: /logout/i });
      fireEvent.click(logoutButton);

      await waitFor(() => {
        expect(mockFetch).toHaveBeenCalledWith(
          '/api/auth/logout',
          expect.objectContaining({
            method: 'POST',
            credentials: 'include',
          })
        );
      });
    });

    it('shows loading state during logout', async () => {
      // Mock a delayed response
      mockFetch.mockImplementation(() =>
        new Promise(resolve =>
          setTimeout(() => resolve({
            ok: true,
            json: async () => ({ message: 'Logged out successfully' }),
          }), 100)
        )
      );

      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      render(<Navigation />);

      const logoutButton = screen.getByRole('button', { name: /logout/i }) as HTMLButtonElement;
      fireEvent.click(logoutButton);

      // Check for loading state
      await waitFor(() => {
        expect(screen.getByText(/logging out/i)).toBeInTheDocument();
      });
    });

    it('disables logout button during logout', async () => {
      // Mock a delayed response
      mockFetch.mockImplementation(() =>
        new Promise(resolve =>
          setTimeout(() => resolve({
            ok: true,
            json: async () => ({ message: 'Logged out successfully' }),
          }), 100)
        )
      );

      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      render(<Navigation />);

      const logoutButton = screen.getByRole('button', { name: /logout/i }) as HTMLButtonElement;
      fireEvent.click(logoutButton);

      // Check that button is disabled during logout
      await waitFor(() => {
        expect(logoutButton.disabled).toBe(true);
      });
    });

    it('prevents multiple logout calls when button is clicked multiple times', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Logged out successfully' }),
      });

      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      render(<Navigation />);

      const logoutButton = screen.getByRole('button', { name: /logout/i });

      // Click multiple times rapidly
      fireEvent.click(logoutButton);
      fireEvent.click(logoutButton);
      fireEvent.click(logoutButton);

      await waitFor(() => {
        // Should only be called once due to isLoggingOut check
        expect(mockFetch).toHaveBeenCalledTimes(1);
      });
    });
  });

  describe('Integration Tests', () => {
    it('completes full logout flow: button click -> API call -> session clear -> redirect', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Logged out successfully' }),
      });

      const removeItemSpy = vi.spyOn(window.sessionStorage, 'removeItem');

      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      render(<Navigation />);

      // Click logout button
      const logoutButton = screen.getByRole('button', { name: /logout/i });
      fireEvent.click(logoutButton);

      // Wait for all async operations to complete
      await waitFor(() => {
        // Verify API was called
        expect(mockFetch).toHaveBeenCalledWith(
          '/api/auth/logout',
          expect.objectContaining({
            method: 'POST',
            credentials: 'include',
          })
        );

        // Verify session storage was cleared
        expect(removeItemSpy).toHaveBeenCalledWith('redirectAfterLogin');

        // Verify redirect happened
        expect(redirectUrl).toBe('/auth/login');
      });
    });

    it('handles logout gracefully when user is already logged out', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 401,
        json: async () => ({ error: 'Not authenticated' }),
      });

      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      render(<Navigation />);

      const logoutButton = screen.getByRole('button', { name: /logout/i });
      fireEvent.click(logoutButton);

      // Should still redirect even if logout API returns 401
      await waitFor(() => {
        expect(redirectUrl).toBe('/auth/login');
      });
    });

    it('clears stored redirect path during logout', async () => {
      // Setup: User had a stored redirect path
      const setItemSpy = vi.spyOn(window.sessionStorage, 'setItem');
      const removeItemSpy = vi.spyOn(window.sessionStorage, 'removeItem');

      // Simulate stored redirect path
      window.sessionStorage.setItem('redirectAfterLogin', '/messages');

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Logged out successfully' }),
      });

      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      await performLogout();

      // Verify the redirect path was cleared
      expect(removeItemSpy).toHaveBeenCalledWith('redirectAfterLogin');
    });
  });

  describe('Edge Cases', () => {
    it('handles sessionStorage errors gracefully', async () => {
      // Mock sessionStorage to throw an error
      vi.spyOn(window.sessionStorage, 'removeItem').mockImplementation(() => {
        throw new Error('SessionStorage error');
      });

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ message: 'Logged out successfully' }),
      });

      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      // Should not throw error
      await expect(performLogout()).resolves.not.toThrow();

      // Should still redirect
      expect(redirectUrl).toBe('/auth/login');
    });

    it('handles network timeout during logout', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Timeout'));

      let redirectUrl = '';
      Object.defineProperty(window, 'location', {
        value: {
          href: '',
          get href() {
            return redirectUrl;
          },
          set href(url: string) {
            redirectUrl = url;
          },
        },
        writable: true,
      });

      await performLogout();

      // Should still redirect even on timeout
      expect(redirectUrl).toBe('/auth/login');
    });
  });
});
