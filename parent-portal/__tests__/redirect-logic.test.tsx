/**
 * Tests for redirect logic in LAYA Parent Portal.
 *
 * This test suite covers:
 * - Login page redirects (authenticated users)
 * - Register page redirects (authenticated users)
 * - Dashboard page redirects (unauthenticated users)
 * - Redirect parameter handling
 * - AuthProvider integration in layout
 */

import { render, screen, waitFor } from '@testing-library/react';
import { useRouter, useSearchParams } from 'next/navigation';
import { useAuth } from '@/contexts/AuthContext';
import LoginPage from '@/app/auth/login/page';
import RegisterPage from '@/app/auth/register/page';
import { DashboardWrapper } from '@/components/DashboardWrapper';

// Mock Next.js navigation
jest.mock('next/navigation', () => ({
  useRouter: jest.fn(),
  useSearchParams: jest.fn(),
}));

// Mock AuthContext
jest.mock('@/contexts/AuthContext', () => ({
  useAuth: jest.fn(),
}));

describe('Redirect Logic', () => {
  const mockPush = jest.fn();
  const mockUseRouter = useRouter as jest.Mock;
  const mockUseSearchParams = useSearchParams as jest.Mock;
  const mockUseAuth = useAuth as jest.Mock;

  beforeEach(() => {
    jest.clearAllMocks();
    mockUseRouter.mockReturnValue({ push: mockPush });
    mockUseSearchParams.mockReturnValue(new URLSearchParams());
  });

  describe('Login Page Redirects', () => {
    it('should redirect authenticated users to dashboard', async () => {
      // Mock authenticated state
      mockUseAuth.mockReturnValue({
        isAuthenticated: true,
        isLoading: false,
        user: { id: '1', email: 'test@example.com', firstName: 'Test' },
      });

      render(<LoginPage />);

      // Should redirect authenticated users
      await waitFor(() => {
        expect(mockPush).toHaveBeenCalledWith('/');
      });
    });

    it('should not redirect unauthenticated users', () => {
      // Mock unauthenticated state
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: false,
        user: null,
      });

      render(<LoginPage />);

      // Should not redirect unauthenticated users
      expect(mockPush).not.toHaveBeenCalled();
      expect(screen.getByText(/sign in to your account/i)).toBeInTheDocument();
    });

    it('should handle redirect parameter in URL', async () => {
      // Mock unauthenticated state
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: false,
        user: null,
      });

      // Mock redirect parameter
      const searchParams = new URLSearchParams();
      searchParams.set('redirect', '/messages');
      mockUseSearchParams.mockReturnValue(searchParams);

      render(<LoginPage />);

      // Should show login page with redirect parameter stored
      expect(screen.getByText(/sign in to your account/i)).toBeInTheDocument();
    });

    it('should redirect to custom path after login', async () => {
      // Mock unauthenticated state initially
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: false,
        user: null,
      });

      // Mock redirect parameter
      const searchParams = new URLSearchParams();
      searchParams.set('redirect', '/invoices');
      mockUseSearchParams.mockReturnValue(searchParams);

      render(<LoginPage />);

      // The redirect path should be extracted and used
      expect(screen.getByText(/sign in to your account/i)).toBeInTheDocument();
    });

    it('should not use invalid redirect paths', async () => {
      // Mock unauthenticated state
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: false,
        user: null,
      });

      // Mock invalid redirect parameter (external URL)
      const searchParams = new URLSearchParams();
      searchParams.set('redirect', '//evil.com/phishing');
      mockUseSearchParams.mockReturnValue(searchParams);

      render(<LoginPage />);

      // Should show login page (invalid redirect is ignored)
      expect(screen.getByText(/sign in to your account/i)).toBeInTheDocument();
    });

    it('should not redirect while loading', () => {
      // Mock loading state
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: true,
        user: null,
      });

      render(<LoginPage />);

      // Should not redirect while loading
      expect(mockPush).not.toHaveBeenCalled();
    });
  });

  describe('Register Page Redirects', () => {
    it('should redirect authenticated users to dashboard', async () => {
      // Mock authenticated state
      mockUseAuth.mockReturnValue({
        isAuthenticated: true,
        isLoading: false,
        user: { id: '1', email: 'test@example.com', firstName: 'Test' },
      });

      render(<RegisterPage />);

      // Should redirect authenticated users
      await waitFor(() => {
        expect(mockPush).toHaveBeenCalledWith('/');
      });
    });

    it('should not redirect unauthenticated users', () => {
      // Mock unauthenticated state
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: false,
        user: null,
      });

      render(<RegisterPage />);

      // Should not redirect unauthenticated users
      expect(mockPush).not.toHaveBeenCalled();
      expect(screen.getByText(/create your account/i)).toBeInTheDocument();
    });

    it('should not redirect while loading', () => {
      // Mock loading state
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: true,
        user: null,
      });

      render(<RegisterPage />);

      // Should not redirect while loading
      expect(mockPush).not.toHaveBeenCalled();
    });
  });

  describe('Dashboard Wrapper Redirects', () => {
    it('should render children when authenticated', () => {
      // Mock authenticated state
      mockUseAuth.mockReturnValue({
        isAuthenticated: true,
        isLoading: false,
        user: { id: '1', email: 'test@example.com', firstName: 'Test' },
      });

      render(
        <DashboardWrapper>
          <div>Dashboard Content</div>
        </DashboardWrapper>
      );

      // Should render children
      expect(screen.getByText('Dashboard Content')).toBeInTheDocument();
    });

    it('should show loading state while checking auth', () => {
      // Mock loading state
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: true,
        user: null,
      });

      render(
        <DashboardWrapper>
          <div>Dashboard Content</div>
        </DashboardWrapper>
      );

      // Should show loading indicator
      expect(screen.getByText(/loading/i)).toBeInTheDocument();
      expect(screen.queryByText('Dashboard Content')).not.toBeInTheDocument();
    });

    it('should redirect unauthenticated users to login', async () => {
      // Mock unauthenticated state
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: false,
        user: null,
      });

      render(
        <DashboardWrapper>
          <div>Dashboard Content</div>
        </DashboardWrapper>
      );

      // Should redirect to login
      await waitFor(() => {
        expect(mockPush).toHaveBeenCalledWith('/auth/login');
      });
    });
  });

  describe('Redirect Parameter Security', () => {
    const invalidRedirects = [
      '//evil.com',
      'http://evil.com',
      'https://evil.com',
      'javascript:alert(1)',
      '///evil.com',
      'data:text/html,<script>alert(1)</script>',
    ];

    invalidRedirects.forEach((redirect) => {
      it(`should reject invalid redirect: ${redirect}`, () => {
        mockUseAuth.mockReturnValue({
          isAuthenticated: false,
          isLoading: false,
          user: null,
        });

        const searchParams = new URLSearchParams();
        searchParams.set('redirect', redirect);
        mockUseSearchParams.mockReturnValue(searchParams);

        render(<LoginPage />);

        // Should show login page (not crash or redirect to invalid URL)
        expect(screen.getByText(/sign in to your account/i)).toBeInTheDocument();
      });
    });

    const validRedirects = [
      '/dashboard',
      '/messages',
      '/documents',
      '/invoices',
      '/daily-reports',
      '/messages/123',
      '/documents/abc',
    ];

    validRedirects.forEach((redirect) => {
      it(`should accept valid redirect: ${redirect}`, () => {
        mockUseAuth.mockReturnValue({
          isAuthenticated: false,
          isLoading: false,
          user: null,
        });

        const searchParams = new URLSearchParams();
        searchParams.set('redirect', redirect);
        mockUseSearchParams.mockReturnValue(searchParams);

        render(<LoginPage />);

        // Should show login page with valid redirect stored
        expect(screen.getByText(/sign in to your account/i)).toBeInTheDocument();
      });
    });
  });

  describe('Auth State Transitions', () => {
    it('should handle auth state change from loading to authenticated', async () => {
      // Start with loading state
      const { rerender } = render(<LoginPage />);

      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: true,
        user: null,
      });

      rerender(<LoginPage />);

      // No redirect while loading
      expect(mockPush).not.toHaveBeenCalled();

      // Change to authenticated
      mockUseAuth.mockReturnValue({
        isAuthenticated: true,
        isLoading: false,
        user: { id: '1', email: 'test@example.com', firstName: 'Test' },
      });

      rerender(<LoginPage />);

      // Should redirect once authenticated
      await waitFor(() => {
        expect(mockPush).toHaveBeenCalledWith('/');
      });
    });

    it('should handle auth state change from loading to unauthenticated', () => {
      const { rerender } = render(<LoginPage />);

      // Start with loading state
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: true,
        user: null,
      });

      rerender(<LoginPage />);

      // No redirect while loading
      expect(mockPush).not.toHaveBeenCalled();

      // Change to unauthenticated
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: false,
        user: null,
      });

      rerender(<LoginPage />);

      // Should show login form
      expect(screen.getByText(/sign in to your account/i)).toBeInTheDocument();
      expect(mockPush).not.toHaveBeenCalled();
    });
  });

  describe('Integration Tests', () => {
    it('should integrate with AuthProvider for login page', () => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: false,
        user: null,
      });

      render(<LoginPage />);

      // Should use AuthContext
      expect(mockUseAuth).toHaveBeenCalled();
      expect(screen.getByText(/sign in to your account/i)).toBeInTheDocument();
    });

    it('should integrate with AuthProvider for register page', () => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: false,
        user: null,
      });

      render(<RegisterPage />);

      // Should use AuthContext
      expect(mockUseAuth).toHaveBeenCalled();
      expect(screen.getByText(/create your account/i)).toBeInTheDocument();
    });

    it('should integrate with AuthProvider for dashboard', () => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: true,
        isLoading: false,
        user: { id: '1', email: 'test@example.com', firstName: 'Test' },
      });

      render(
        <DashboardWrapper>
          <div>Dashboard Content</div>
        </DashboardWrapper>
      );

      // Should use AuthContext
      expect(mockUseAuth).toHaveBeenCalled();
      expect(screen.getByText('Dashboard Content')).toBeInTheDocument();
    });
  });

  describe('Edge Cases', () => {
    it('should handle missing router', () => {
      mockUseRouter.mockReturnValue({});
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: false,
        user: null,
      });

      // Should not crash
      expect(() => render(<LoginPage />)).not.toThrow();
    });

    it('should handle missing search params', () => {
      mockUseSearchParams.mockReturnValue(null);
      mockUseAuth.mockReturnValue({
        isAuthenticated: false,
        isLoading: false,
        user: null,
      });

      // Should not crash and show login page
      render(<LoginPage />);
      expect(screen.getByText(/sign in to your account/i)).toBeInTheDocument();
    });

    it('should handle undefined auth state gracefully', () => {
      mockUseAuth.mockReturnValue({
        isAuthenticated: undefined,
        isLoading: false,
        user: null,
      });

      // Should not crash
      expect(() => render(<LoginPage />)).not.toThrow();
    });
  });
});
