import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor, renderHook, act } from '@testing-library/react';
import { AuthProvider, useAuth } from '@/contexts/AuthContext';
import type { User } from '@/lib/auth';

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch;

// Test component that uses the auth context
function TestComponent() {
  const { user, isAuthenticated, isLoading, updateUser } = useAuth();

  if (isLoading) {
    return <div>Loading...</div>;
  }

  return (
    <div>
      <div data-testid="auth-status">
        {isAuthenticated ? 'Authenticated' : 'Not Authenticated'}
      </div>
      {user && (
        <div>
          <div data-testid="user-email">{user.email}</div>
          <div data-testid="user-id">{user.id}</div>
          <div data-testid="user-role">{user.role}</div>
          {user.firstName && <div data-testid="user-firstname">{user.firstName}</div>}
          {user.lastName && <div data-testid="user-lastname">{user.lastName}</div>}
        </div>
      )}
      <button
        onClick={() =>
          updateUser({
            id: '456',
            email: 'updated@example.com',
            role: 'parent',
            firstName: 'Updated',
          })
        }
      >
        Update User
      </button>
    </div>
  );
}

describe('AuthContext', () => {
  beforeEach(() => {
    // Reset mocks before each test
    mockFetch.mockReset();
  });

  afterEach(() => {
    vi.clearAllMocks();
  });

  describe('Provider initialization', () => {
    it('renders children correctly', () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: null }),
      });

      render(
        <AuthProvider>
          <div>Test Child</div>
        </AuthProvider>
      );

      expect(screen.getByText('Test Child')).toBeInTheDocument();
    });

    it('shows loading state on initial mount', () => {
      mockFetch.mockImplementation(
        () =>
          new Promise((resolve) =>
            setTimeout(
              () =>
                resolve({
                  ok: true,
                  json: async () => ({ user: null }),
                }),
              100
            )
          )
      );

      render(
        <AuthProvider>
          <TestComponent />
        </AuthProvider>
      );

      expect(screen.getByText('Loading...')).toBeInTheDocument();
    });

    it('accepts initial user prop', async () => {
      const initialUser: User = {
        id: '123',
        email: 'test@example.com',
        role: 'parent',
        firstName: 'Test',
        lastName: 'User',
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: initialUser }),
      });

      render(
        <AuthProvider initialUser={initialUser}>
          <TestComponent />
        </AuthProvider>
      );

      // Wait for loading to complete
      await waitFor(() => {
        expect(screen.queryByText('Loading...')).not.toBeInTheDocument();
      });
    });
  });

  describe('Authentication state', () => {
    it('sets authenticated state when user is logged in', async () => {
      const mockUser: User = {
        id: '123',
        email: 'test@example.com',
        role: 'parent',
        firstName: 'John',
        lastName: 'Doe',
      };

      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: mockUser }),
      });

      render(
        <AuthProvider>
          <TestComponent />
        </AuthProvider>
      );

      await waitFor(() => {
        expect(screen.getByTestId('auth-status')).toHaveTextContent('Authenticated');
        expect(screen.getByTestId('user-email')).toHaveTextContent('test@example.com');
        expect(screen.getByTestId('user-id')).toHaveTextContent('123');
        expect(screen.getByTestId('user-role')).toHaveTextContent('parent');
        expect(screen.getByTestId('user-firstname')).toHaveTextContent('John');
        expect(screen.getByTestId('user-lastname')).toHaveTextContent('Doe');
      });
    });

    it('sets unauthenticated state when no user', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 401,
      });

      render(
        <AuthProvider>
          <TestComponent />
        </AuthProvider>
      );

      await waitFor(() => {
        expect(screen.getByTestId('auth-status')).toHaveTextContent('Not Authenticated');
        expect(screen.queryByTestId('user-email')).not.toBeInTheDocument();
      });
    });

    it('handles server returning null user', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: null }),
      });

      render(
        <AuthProvider>
          <TestComponent />
        </AuthProvider>
      );

      await waitFor(() => {
        expect(screen.getByTestId('auth-status')).toHaveTextContent('Not Authenticated');
      });
    });
  });

  describe('API integration', () => {
    it('calls /api/auth/me on mount', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: null }),
      });

      render(
        <AuthProvider>
          <TestComponent />
        </AuthProvider>
      );

      await waitFor(() => {
        expect(mockFetch).toHaveBeenCalledWith(
          '/api/auth/me',
          expect.objectContaining({
            method: 'GET',
            credentials: 'include',
            headers: {
              'Content-Type': 'application/json',
            },
          })
        );
      });
    });

    it('handles network errors gracefully', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Network error'));

      // Suppress console.error for this test
      const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <AuthProvider>
          <TestComponent />
        </AuthProvider>
      );

      await waitFor(() => {
        expect(screen.getByTestId('auth-status')).toHaveTextContent('Not Authenticated');
      });

      consoleError.mockRestore();
    });

    it('handles 500 errors gracefully', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: false,
        status: 500,
      });

      render(
        <AuthProvider>
          <TestComponent />
        </AuthProvider>
      );

      await waitFor(() => {
        expect(screen.getByTestId('auth-status')).toHaveTextContent('Not Authenticated');
      });
    });
  });

  describe('updateUser function', () => {
    it('updates user state when called', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          user: {
            id: '123',
            email: 'test@example.com',
            role: 'parent',
          },
        }),
      });

      render(
        <AuthProvider>
          <TestComponent />
        </AuthProvider>
      );

      await waitFor(() => {
        expect(screen.getByTestId('user-email')).toHaveTextContent('test@example.com');
      });

      const updateButton = screen.getByText('Update User');
      act(() => {
        updateButton.click();
      });

      await waitFor(() => {
        expect(screen.getByTestId('user-email')).toHaveTextContent('updated@example.com');
        expect(screen.getByTestId('user-id')).toHaveTextContent('456');
        expect(screen.getByTestId('user-firstname')).toHaveTextContent('Updated');
      });
    });

    it('can set user to null', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          user: {
            id: '123',
            email: 'test@example.com',
            role: 'parent',
          },
        }),
      });

      const { result } = renderHook(() => useAuth(), {
        wrapper: ({ children }) => <AuthProvider>{children}</AuthProvider>,
      });

      await waitFor(() => {
        expect(result.current.isAuthenticated).toBe(true);
      });

      act(() => {
        result.current.updateUser(null);
      });

      await waitFor(() => {
        expect(result.current.isAuthenticated).toBe(false);
        expect(result.current.user).toBeNull();
      });
    });
  });

  describe('refreshAuth function', () => {
    it('re-fetches user data when called', async () => {
      mockFetch
        .mockResolvedValueOnce({
          ok: true,
          json: async () => ({
            user: {
              id: '123',
              email: 'old@example.com',
              role: 'parent',
            },
          }),
        })
        .mockResolvedValueOnce({
          ok: true,
          json: async () => ({
            user: {
              id: '123',
              email: 'new@example.com',
              role: 'parent',
            },
          }),
        });

      const { result } = renderHook(() => useAuth(), {
        wrapper: ({ children }) => <AuthProvider>{children}</AuthProvider>,
      });

      await waitFor(() => {
        expect(result.current.user?.email).toBe('old@example.com');
      });

      await act(async () => {
        await result.current.refreshAuth();
      });

      await waitFor(() => {
        expect(result.current.user?.email).toBe('new@example.com');
      });

      expect(mockFetch).toHaveBeenCalledTimes(2);
    });

    it('sets loading state during refresh', async () => {
      mockFetch
        .mockResolvedValueOnce({
          ok: true,
          json: async () => ({ user: null }),
        })
        .mockImplementation(
          () =>
            new Promise((resolve) =>
              setTimeout(
                () =>
                  resolve({
                    ok: true,
                    json: async () => ({ user: null }),
                  }),
                100
              )
            )
        );

      const { result } = renderHook(() => useAuth(), {
        wrapper: ({ children }) => <AuthProvider>{children}</AuthProvider>,
      });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      act(() => {
        result.current.refreshAuth();
      });

      expect(result.current.isLoading).toBe(true);

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });
    });
  });

  describe('useAuth hook', () => {
    it('throws error when used outside of provider', () => {
      // Suppress console.error for this test
      const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});

      expect(() => {
        renderHook(() => useAuth());
      }).toThrow('useAuth must be used within an AuthProvider');

      consoleError.mockRestore();
    });

    it('returns auth context value when used within provider', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          user: {
            id: '123',
            email: 'test@example.com',
            role: 'parent',
          },
        }),
      });

      const { result } = renderHook(() => useAuth(), {
        wrapper: ({ children }) => <AuthProvider>{children}</AuthProvider>,
      });

      await waitFor(() => {
        expect(result.current).toHaveProperty('user');
        expect(result.current).toHaveProperty('isAuthenticated');
        expect(result.current).toHaveProperty('isLoading');
        expect(result.current).toHaveProperty('updateUser');
        expect(result.current).toHaveProperty('refreshAuth');
      });
    });
  });

  describe('Loading states', () => {
    it('shows loading initially and then completes', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({ user: null }),
      });

      const { result } = renderHook(() => useAuth(), {
        wrapper: ({ children }) => <AuthProvider>{children}</AuthProvider>,
      });

      expect(result.current.isLoading).toBe(true);

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });
    });

    it('sets isLoading to false even on error', async () => {
      mockFetch.mockRejectedValueOnce(new Error('Network error'));

      // Suppress console.error for this test
      const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});

      const { result } = renderHook(() => useAuth(), {
        wrapper: ({ children }) => <AuthProvider>{children}</AuthProvider>,
      });

      await waitFor(() => {
        expect(result.current.isLoading).toBe(false);
      });

      consoleError.mockRestore();
    });
  });

  describe('Edge cases', () => {
    it('handles malformed JSON response', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => {
          throw new Error('Invalid JSON');
        },
      });

      // Suppress console.error for this test
      const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});

      render(
        <AuthProvider>
          <TestComponent />
        </AuthProvider>
      );

      await waitFor(() => {
        expect(screen.getByTestId('auth-status')).toHaveTextContent('Not Authenticated');
      });

      consoleError.mockRestore();
    });

    it('handles user object without optional fields', async () => {
      mockFetch.mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          user: {
            id: '123',
            email: 'test@example.com',
            role: 'parent',
          },
        }),
      });

      render(
        <AuthProvider>
          <TestComponent />
        </AuthProvider>
      );

      await waitFor(() => {
        expect(screen.getByTestId('user-email')).toHaveTextContent('test@example.com');
        expect(screen.queryByTestId('user-firstname')).not.toBeInTheDocument();
        expect(screen.queryByTestId('user-lastname')).not.toBeInTheDocument();
      });
    });
  });
});
