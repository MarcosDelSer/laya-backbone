/**
 * Tests for Auth Hooks
 *
 * Tests all custom auth hooks for the Parent Portal:
 * - useRequireAuth
 * - useUser
 * - useAuthStatus
 * - useAuthRedirect
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';
import { AuthProvider } from '@/contexts/AuthContext';
import { useRequireAuth } from '@/hooks/useRequireAuth';
import { useUser } from '@/hooks/useUser';
import { useAuthStatus } from '@/hooks/useAuthStatus';
import { useAuthRedirect } from '@/hooks/useAuthRedirect';

// Mock Next.js navigation
const mockPush = vi.fn();
const mockRouter = {
  push: mockPush,
  replace: vi.fn(),
  prefetch: vi.fn(),
};

vi.mock('next/navigation', () => ({
  useRouter: () => mockRouter,
  usePathname: () => '/test-path',
  useSearchParams: () => new URLSearchParams(),
}));

// Mock user for testing
const mockUser = {
  id: '123',
  email: 'test@example.com',
  role: 'parent',
  firstName: 'John',
  lastName: 'Doe',
};

// Helper to create wrapper with AuthProvider
const createWrapper = (initialUser: any = null) => {
  return ({ children }: { children: React.ReactNode }) => (
    <AuthProvider initialUser={initialUser}>{children}</AuthProvider>
  );
};

describe('Auth Hooks', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    global.fetch = vi.fn();
  });

  afterEach(() => {
    vi.restoreAllMocks();
  });

  // ========================================================================
  // useUser Hook Tests
  // ========================================================================

  describe('useUser', () => {
    it('should return user when authenticated', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: mockUser }),
      });

      const { result } = renderHook(() => useUser(), {
        wrapper: createWrapper(),
      });

      // Initially loading
      expect(result.current.isLoading).toBe(true);
      expect(result.current.user).toBe(null);

      // Wait for auth check
      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.user).toEqual(mockUser);
    });

    it('should return null when not authenticated', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: false,
        status: 401,
      });

      const { result } = renderHook(() => useUser(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.user).toBe(null);
    });

    it('should handle loading state correctly', async () => {
      (global.fetch as any).mockImplementationOnce(
        () =>
          new Promise((resolve) =>
            setTimeout(() => resolve({ ok: true, json: async () => ({ user: mockUser }) }), 100)
          )
      );

      const { result } = renderHook(() => useUser(), {
        wrapper: createWrapper(),
      });

      // Should start loading
      expect(result.current.isLoading).toBe(true);

      // Should eventually finish loading
      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });
    });
  });

  // ========================================================================
  // useAuthStatus Hook Tests
  // ========================================================================

  describe('useAuthStatus', () => {
    it('should return isAuthenticated true when user is logged in', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: mockUser }),
      });

      const { result } = renderHook(() => useAuthStatus(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.isAuthenticated).toBe(true);
    });

    it('should return isAuthenticated false when not logged in', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: false,
        status: 401,
      });

      const { result } = renderHook(() => useAuthStatus(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.isAuthenticated).toBe(false);
    });

    it('should handle loading state', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: mockUser }),
      });

      const { result } = renderHook(() => useAuthStatus(), {
        wrapper: createWrapper(),
      });

      expect(result.current.isLoading).toBe(true);

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });
    });
  });

  // ========================================================================
  // useRequireAuth Hook Tests
  // ========================================================================

  describe('useRequireAuth', () => {
    beforeEach(() => {
      // Mock window.location
      delete (window as any).location;
      (window as any).location = {
        pathname: '/dashboard',
        search: '',
      };
    });

    it('should not redirect when user is authenticated', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: mockUser }),
      });

      const { result } = renderHook(() => useRequireAuth(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(mockPush).not.toHaveBeenCalled();
      expect(result.current.isAuthenticated).toBe(true);
    });

    it('should redirect to login when not authenticated', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: false,
        status: 401,
      });

      renderHook(() => useRequireAuth(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(mockPush).toHaveBeenCalled();
      });

      expect(mockPush).toHaveBeenCalledWith(
        expect.stringContaining('/auth/login')
      );
    });

    it('should preserve destination when redirecting', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: false,
        status: 401,
      });

      (window as any).location = {
        pathname: '/protected-page',
        search: '?foo=bar',
      };

      renderHook(() => useRequireAuth(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(mockPush).toHaveBeenCalled();
      });

      const redirectUrl = mockPush.mock.calls[0][0];
      expect(redirectUrl).toContain('/auth/login');
      expect(redirectUrl).toContain('destination=');
      expect(redirectUrl).toContain(encodeURIComponent('/protected-page?foo=bar'));
    });

    it('should redirect to custom path', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: false,
        status: 401,
      });

      renderHook(() => useRequireAuth({ redirectTo: '/custom-login' }), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(mockPush).toHaveBeenCalled();
      });

      expect(mockPush).toHaveBeenCalledWith(
        expect.stringContaining('/custom-login')
      );
    });

    it('should not preserve destination when disabled', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: false,
        status: 401,
      });

      renderHook(
        () =>
          useRequireAuth({
            preserveDestination: false,
          }),
        {
          wrapper: createWrapper(),
        }
      );

      await waitFor(() => {
        expect(mockPush).toHaveBeenCalled();
      });

      expect(mockPush).toHaveBeenCalledWith('/auth/login');
    });

    it('should not redirect while loading', async () => {
      (global.fetch as any).mockImplementationOnce(
        () => new Promise((resolve) => setTimeout(() => resolve({ ok: false }), 100))
      );

      renderHook(() => useRequireAuth(), {
        wrapper: createWrapper(),
      });

      // Should not redirect immediately
      expect(mockPush).not.toHaveBeenCalled();

      // Wait and verify redirect happens after loading
      await waitFor(() => {
        expect(mockPush).toHaveBeenCalled();
      });
    });
  });

  // ========================================================================
  // useAuthRedirect Hook Tests
  // ========================================================================

  describe('useAuthRedirect', () => {
    beforeEach(() => {
      delete (window as any).location;
      (window as any).location = {
        pathname: '/auth/login',
        search: '',
      };
    });

    it('should redirect authenticated users away from login page', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: mockUser }),
      });

      renderHook(() => useAuthRedirect(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(mockPush).toHaveBeenCalled();
      });

      expect(mockPush).toHaveBeenCalledWith('/');
    });

    it('should not redirect unauthenticated users', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: false,
        status: 401,
      });

      renderHook(() => useAuthRedirect(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(mockPush).not.toHaveBeenCalled();
      });
    });

    it('should redirect to custom path', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: mockUser }),
      });

      renderHook(() => useAuthRedirect({ redirectTo: '/dashboard' }), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(mockPush).toHaveBeenCalledWith('/dashboard');
      });
    });

    it('should use destination query parameter', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: mockUser }),
      });

      (window as any).location = {
        pathname: '/auth/login',
        search: '?destination=/dashboard',
      };

      renderHook(() => useAuthRedirect(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(mockPush).toHaveBeenCalledWith('/dashboard');
      });
    });

    it('should ignore destination parameter when disabled', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: mockUser }),
      });

      (window as any).location = {
        pathname: '/auth/login',
        search: '?destination=/dashboard',
      };

      renderHook(() => useAuthRedirect({ checkDestination: false }), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(mockPush).toHaveBeenCalledWith('/');
      });
    });

    it('should validate destination is a safe relative path', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: mockUser }),
      });

      // Test with absolute URL (should be ignored)
      (window as any).location = {
        pathname: '/auth/login',
        search: '?destination=https://evil.com',
      };

      renderHook(() => useAuthRedirect(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(mockPush).toHaveBeenCalledWith('/');
      });
    });

    it('should support redirectIfNotAuthenticated option', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: false,
        status: 401,
      });

      renderHook(
        () =>
          useAuthRedirect({
            redirectIfAuthenticated: false,
            redirectIfNotAuthenticated: true,
            redirectTo: '/auth/login',
          }),
        {
          wrapper: createWrapper(),
        }
      );

      await waitFor(() => {
        expect(mockPush).toHaveBeenCalledWith('/auth/login');
      });
    });

    it('should not redirect while loading', async () => {
      (global.fetch as any).mockImplementationOnce(
        () =>
          new Promise((resolve) =>
            setTimeout(() => resolve({ ok: true, json: async () => ({ user: mockUser }) }), 100)
          )
      );

      renderHook(() => useAuthRedirect(), {
        wrapper: createWrapper(),
      });

      // Should not redirect immediately while loading
      expect(mockPush).not.toHaveBeenCalled();

      // Should redirect after loading completes
      await waitFor(() => {
        expect(mockPush).toHaveBeenCalled();
      });
    });
  });

  // ========================================================================
  // Integration Tests
  // ========================================================================

  describe('Integration', () => {
    it('should all hooks share the same auth state', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: mockUser }),
      });

      const Wrapper = createWrapper();

      const { result: userResult } = renderHook(() => useUser(), { wrapper: Wrapper });
      const { result: statusResult } = renderHook(() => useAuthStatus(), { wrapper: Wrapper });

      await waitFor(() => {
        expect(userResult.current.isLoading).toBe(false);
        expect(statusResult.current.isLoading).toBe(false);
      });

      // Both should reflect the same state
      expect(userResult.current.user).toEqual(mockUser);
      expect(statusResult.current.isAuthenticated).toBe(true);
    });
  });

  // ========================================================================
  // Error Handling Tests
  // ========================================================================

  describe('Error Handling', () => {
    it('should handle network errors gracefully', async () => {
      (global.fetch as any).mockRejectedValueOnce(new Error('Network error'));

      const { result } = renderHook(() => useUser(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.user).toBe(null);
    });

    it('should handle malformed responses', async () => {
      (global.fetch as any).mockResolvedValueOnce({
        ok: true,
        json: async () => ({}), // Missing user field
      });

      const { result } = renderHook(() => useUser(), {
        wrapper: createWrapper(),
      });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      expect(result.current.user).toBe(null);
    });
  });
});
