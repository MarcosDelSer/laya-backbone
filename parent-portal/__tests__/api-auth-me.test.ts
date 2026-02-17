import { describe, it, expect, vi, beforeEach } from 'vitest';
import { NextRequest } from 'next/server';
import { GET } from '@/app/api/auth/me/route';
import * as auth from '@/lib/auth';

// Mock the auth module
vi.mock('@/lib/auth', () => ({
  getServerToken: vi.fn(),
  getValidatedUserFromToken: vi.fn(),
}));

describe('GET /api/auth/me', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('returns user when authenticated with valid token', async () => {
    const mockUser = {
      id: '123',
      email: 'test@example.com',
      role: 'parent',
      firstName: 'John',
      lastName: 'Doe',
    };

    vi.mocked(auth.getServerToken).mockResolvedValue('valid-token');
    vi.mocked(auth.getValidatedUserFromToken).mockReturnValue(mockUser);

    const request = new NextRequest('http://localhost:3000/api/auth/me');
    const response = await GET(request);

    expect(response.status).toBe(200);

    const data = await response.json();
    expect(data).toEqual({
      user: mockUser,
    });

    expect(auth.getServerToken).toHaveBeenCalled();
    expect(auth.getValidatedUserFromToken).toHaveBeenCalledWith('valid-token');
  });

  it('returns user with minimal fields', async () => {
    const mockUser = {
      id: '123',
      email: 'test@example.com',
      role: 'parent',
    };

    vi.mocked(auth.getServerToken).mockResolvedValue('valid-token');
    vi.mocked(auth.getValidatedUserFromToken).mockReturnValue(mockUser);

    const request = new NextRequest('http://localhost:3000/api/auth/me');
    const response = await GET(request);

    expect(response.status).toBe(200);

    const data = await response.json();
    expect(data.user).toEqual(mockUser);
  });

  it('returns 401 when no token present', async () => {
    vi.mocked(auth.getServerToken).mockResolvedValue(null);

    const request = new NextRequest('http://localhost:3000/api/auth/me');
    const response = await GET(request);

    expect(response.status).toBe(401);

    const data = await response.json();
    expect(data).toEqual({
      error: 'Not authenticated',
    });

    expect(auth.getServerToken).toHaveBeenCalled();
    expect(auth.getValidatedUserFromToken).not.toHaveBeenCalled();
  });

  it('returns 401 when token is empty string', async () => {
    vi.mocked(auth.getServerToken).mockResolvedValue('');

    const request = new NextRequest('http://localhost:3000/api/auth/me');
    const response = await GET(request);

    expect(response.status).toBe(401);

    const data = await response.json();
    expect(data.error).toBe('Not authenticated');
  });

  it('returns 401 when token is invalid', async () => {
    vi.mocked(auth.getServerToken).mockResolvedValue('invalid-token');
    vi.mocked(auth.getValidatedUserFromToken).mockReturnValue(null);

    const request = new NextRequest('http://localhost:3000/api/auth/me');
    const response = await GET(request);

    expect(response.status).toBe(401);

    const data = await response.json();
    expect(data).toEqual({
      error: 'Invalid or expired token',
    });

    expect(auth.getServerToken).toHaveBeenCalled();
    expect(auth.getValidatedUserFromToken).toHaveBeenCalledWith('invalid-token');
  });

  it('returns 500 when getServerToken throws error', async () => {
    vi.mocked(auth.getServerToken).mockRejectedValue(new Error('Database error'));

    // Suppress console.error for this test
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});

    const request = new NextRequest('http://localhost:3000/api/auth/me');
    const response = await GET(request);

    expect(response.status).toBe(500);

    const data = await response.json();
    expect(data).toEqual({
      error: 'Internal server error',
    });

    consoleError.mockRestore();
  });

  it('returns 500 when getUserFromToken throws error', async () => {
    vi.mocked(auth.getServerToken).mockResolvedValue('valid-token');
    vi.mocked(auth.getValidatedUserFromToken).mockImplementation(() => {
      throw new Error('Token decoding error');
    });

    // Suppress console.error for this test
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});

    const request = new NextRequest('http://localhost:3000/api/auth/me');
    const response = await GET(request);

    expect(response.status).toBe(500);

    const data = await response.json();
    expect(data.error).toBe('Internal server error');

    consoleError.mockRestore();
  });

  it('handles different user roles correctly', async () => {
    const roles = ['parent', 'admin', 'teacher'];

    for (const role of roles) {
      vi.clearAllMocks();

      const mockUser = {
        id: '123',
        email: 'test@example.com',
        role,
      };

      vi.mocked(auth.getServerToken).mockResolvedValue('valid-token');
      vi.mocked(auth.getValidatedUserFromToken).mockReturnValue(mockUser);

      const request = new NextRequest('http://localhost:3000/api/auth/me');
      const response = await GET(request);

      expect(response.status).toBe(200);

      const data = await response.json();
      expect(data.user.role).toBe(role);
    }
  });

  it('logs error to console when exception occurs', async () => {
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {});

    vi.mocked(auth.getServerToken).mockRejectedValue(new Error('Test error'));

    const request = new NextRequest('http://localhost:3000/api/auth/me');
    await GET(request);

    expect(consoleError).toHaveBeenCalledWith(
      'Error in /api/auth/me:',
      expect.any(Error)
    );

    consoleError.mockRestore();
  });
});
