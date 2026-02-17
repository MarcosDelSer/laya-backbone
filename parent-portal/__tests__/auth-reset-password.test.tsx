import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import ResetPasswordPage from '@/app/auth/reset-password/page';

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch;

// Mock next/navigation
const mockPush = vi.fn();
const mockSearchParams = new URLSearchParams();

vi.mock('next/navigation', () => ({
  useRouter: () => ({
    push: mockPush,
  }),
  useSearchParams: () => mockSearchParams,
}));

describe('ResetPasswordPage', () => {
  beforeEach(() => {
    // Reset mocks before each test
    mockFetch.mockReset();
    mockPush.mockReset();
    mockSearchParams.delete('token');
  });

  it('shows error when no token is present in URL', async () => {
    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByText(/invalid reset link/i)).toBeInTheDocument();
      expect(screen.getByText(/invalid or missing reset token/i)).toBeInTheDocument();
    });

    // Check for action buttons
    expect(screen.getByText(/request new reset/i)).toBeInTheDocument();
    expect(screen.getByText(/back to login/i)).toBeInTheDocument();
  });

  it('renders reset password form when token is present', async () => {
    mockSearchParams.set('token', 'valid-reset-token-123');

    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByText('Set new password')).toBeInTheDocument();
    });

    // Check for form fields
    expect(screen.getByLabelText(/new password/i)).toBeInTheDocument();
    expect(screen.getByLabelText(/confirm new password/i)).toBeInTheDocument();

    // Check for submit button
    expect(screen.getByRole('button', { name: /reset password/i })).toBeInTheDocument();
  });

  it('updates password fields on input', async () => {
    mockSearchParams.set('token', 'valid-reset-token-123');

    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument();
    });

    const passwordInput = screen.getByLabelText(/^new password/i) as HTMLInputElement;
    const confirmPasswordInput = screen.getByLabelText(/confirm new password/i) as HTMLInputElement;

    fireEvent.change(passwordInput, { target: { value: 'newpassword123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'newpassword123' } });

    expect(passwordInput.value).toBe('newpassword123');
    expect(confirmPasswordInput.value).toBe('newpassword123');
  });

  it('shows error when submitting empty form', async () => {
    mockSearchParams.set('token', 'valid-reset-token-123');

    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /reset password/i })).toBeInTheDocument();
    });

    const submitButton = screen.getByRole('button', { name: /reset password/i });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/please fill in both password fields/i)).toBeInTheDocument();
    });
  });

  it('shows error when password is too short', async () => {
    mockSearchParams.set('token', 'valid-reset-token-123');

    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument();
    });

    const passwordInput = screen.getByLabelText(/^new password/i);
    const confirmPasswordInput = screen.getByLabelText(/confirm new password/i);
    const submitButton = screen.getByRole('button', { name: /reset password/i });

    fireEvent.change(passwordInput, { target: { value: 'short' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'short' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/password must be at least 8 characters long/i)).toBeInTheDocument();
    });
  });

  it('shows error when passwords do not match', async () => {
    mockSearchParams.set('token', 'valid-reset-token-123');

    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument();
    });

    const passwordInput = screen.getByLabelText(/^new password/i);
    const confirmPasswordInput = screen.getByLabelText(/confirm new password/i);
    const submitButton = screen.getByRole('button', { name: /reset password/i });

    fireEvent.change(passwordInput, { target: { value: 'password123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'different123' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/passwords do not match/i)).toBeInTheDocument();
    });
  });

  it('shows loading state during form submission', async () => {
    mockSearchParams.set('token', 'valid-reset-token-123');

    // Mock a delayed response
    mockFetch.mockImplementation(() =>
      new Promise(resolve =>
        setTimeout(() => resolve({
          ok: true,
          json: async () => ({ message: 'Password reset successful', success: true }),
        }), 100)
      )
    );

    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument();
    });

    const passwordInput = screen.getByLabelText(/^new password/i);
    const confirmPasswordInput = screen.getByLabelText(/confirm new password/i);
    const submitButton = screen.getByRole('button', { name: /reset password/i });

    fireEvent.change(passwordInput, { target: { value: 'newpassword123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.click(submitButton);

    // Check for loading state
    await waitFor(() => {
      expect(screen.getByText(/resetting password/i)).toBeInTheDocument();
    });
  });

  it('successfully resets password with valid data', async () => {
    mockSearchParams.set('token', 'valid-reset-token-123');

    // Mock successful response
    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        message: 'Your password has been reset successfully',
        success: true,
      }),
    });

    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument();
    });

    const passwordInput = screen.getByLabelText(/^new password/i);
    const confirmPasswordInput = screen.getByLabelText(/confirm new password/i);
    const submitButton = screen.getByRole('button', { name: /reset password/i });

    fireEvent.change(passwordInput, { target: { value: 'newpassword123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(mockFetch).toHaveBeenCalledWith(
        '/api/auth/reset-password',
        expect.objectContaining({
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            token: 'valid-reset-token-123',
            password: 'newpassword123',
          }),
        })
      );
    });

    // Check for success message
    await waitFor(() => {
      expect(screen.getByText(/password reset successful/i)).toBeInTheDocument();
    });
  });

  it('displays success message after successful password reset', async () => {
    mockSearchParams.set('token', 'valid-reset-token-123');

    // Mock successful response
    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        message: 'Password reset successful',
        success: true,
      }),
    });

    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument();
    });

    const passwordInput = screen.getByLabelText(/^new password/i);
    const confirmPasswordInput = screen.getByLabelText(/confirm new password/i);
    const submitButton = screen.getByRole('button', { name: /reset password/i });

    fireEvent.change(passwordInput, { target: { value: 'newpassword123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/password reset successful/i)).toBeInTheDocument();
      expect(screen.getByText(/you will be redirected/i)).toBeInTheDocument();
    });

    // Check for action button
    expect(screen.getByText(/continue to login/i)).toBeInTheDocument();
  });

  it('shows error message on API failure with invalid token', async () => {
    mockSearchParams.set('token', 'invalid-token');

    // Mock failed response
    mockFetch.mockResolvedValueOnce({
      ok: false,
      json: async () => ({
        error: 'Invalid or expired reset token. Please request a new password reset.',
      }),
    });

    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument();
    });

    const passwordInput = screen.getByLabelText(/^new password/i);
    const confirmPasswordInput = screen.getByLabelText(/confirm new password/i);
    const submitButton = screen.getByRole('button', { name: /reset password/i });

    fireEvent.change(passwordInput, { target: { value: 'newpassword123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/invalid or expired reset token/i)).toBeInTheDocument();
    });
  });

  it('shows error message on network error', async () => {
    mockSearchParams.set('token', 'valid-reset-token-123');

    // Mock network error
    mockFetch.mockRejectedValueOnce(new Error('Network error'));

    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument();
    });

    const passwordInput = screen.getByLabelText(/^new password/i);
    const confirmPasswordInput = screen.getByLabelText(/confirm new password/i);
    const submitButton = screen.getByRole('button', { name: /reset password/i });

    fireEvent.change(passwordInput, { target: { value: 'newpassword123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/network error/i)).toBeInTheDocument();
    });
  });

  it('has link to login page', async () => {
    mockSearchParams.set('token', 'valid-reset-token-123');

    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByText(/sign in here/i)).toBeInTheDocument();
    });

    const loginLink = screen.getByText(/sign in here/i).closest('a');
    expect(loginLink).toHaveAttribute('href', '/auth/login');
  });

  it('disables form inputs during submission', async () => {
    mockSearchParams.set('token', 'valid-reset-token-123');

    // Mock a delayed response
    mockFetch.mockImplementation(() =>
      new Promise(resolve =>
        setTimeout(() => resolve({
          ok: true,
          json: async () => ({ message: 'Password reset successful', success: true }),
        }), 100)
      )
    );

    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByLabelText(/new password/i)).toBeInTheDocument();
    });

    const passwordInput = screen.getByLabelText(/^new password/i) as HTMLInputElement;
    const confirmPasswordInput = screen.getByLabelText(/confirm new password/i) as HTMLInputElement;
    const submitButton = screen.getByRole('button', { name: /reset password/i }) as HTMLButtonElement;

    fireEvent.change(passwordInput, { target: { value: 'newpassword123' } });
    fireEvent.change(confirmPasswordInput, { target: { value: 'newpassword123' } });
    fireEvent.click(submitButton);

    // Check that inputs are disabled during submission
    await waitFor(() => {
      expect(passwordInput.disabled).toBe(true);
      expect(confirmPasswordInput.disabled).toBe(true);
      expect(submitButton.disabled).toBe(true);
    });
  });

  it('has links to forgot password and login on error page', async () => {
    render(<ResetPasswordPage />);

    await waitFor(() => {
      expect(screen.getByText(/invalid reset link/i)).toBeInTheDocument();
    });

    const requestNewResetLink = screen.getByText(/request new reset/i).closest('a');
    const loginLink = screen.getByText(/back to login/i).closest('a');

    expect(requestNewResetLink).toHaveAttribute('href', '/auth/forgot-password');
    expect(loginLink).toHaveAttribute('href', '/auth/login');
  });
});
