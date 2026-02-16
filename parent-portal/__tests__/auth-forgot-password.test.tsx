import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import ForgotPasswordPage from '@/app/auth/forgot-password/page';

// Mock fetch globally
const mockFetch = vi.fn();
global.fetch = mockFetch;

describe('ForgotPasswordPage', () => {
  beforeEach(() => {
    // Reset mocks before each test
    mockFetch.mockReset();
  });

  it('renders forgot password form with all elements', () => {
    render(<ForgotPasswordPage />);

    // Check for header
    expect(screen.getByText('LAYA Parent Portal')).toBeInTheDocument();
    expect(screen.getByText('Reset your password')).toBeInTheDocument();

    // Check for form fields
    expect(screen.getByLabelText(/email address/i)).toBeInTheDocument();

    // Check for buttons and links
    expect(screen.getByRole('button', { name: /send reset instructions/i })).toBeInTheDocument();
    expect(screen.getByText(/sign in here/i)).toBeInTheDocument();
  });

  it('updates email field on input', () => {
    render(<ForgotPasswordPage />);

    const emailInput = screen.getByLabelText(/email address/i) as HTMLInputElement;

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });

    expect(emailInput.value).toBe('test@example.com');
  });

  it('shows error when submitting empty form', async () => {
    render(<ForgotPasswordPage />);

    const submitButton = screen.getByRole('button', { name: /send reset instructions/i });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/please enter your email address/i)).toBeInTheDocument();
    });
  });

  it('shows error when email format is invalid', async () => {
    render(<ForgotPasswordPage />);

    const emailInput = screen.getByLabelText(/email address/i);
    const submitButton = screen.getByRole('button', { name: /send reset instructions/i });

    fireEvent.change(emailInput, { target: { value: 'invalid-email' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/please enter a valid email address/i)).toBeInTheDocument();
    });
  });

  it('shows loading state during form submission', async () => {
    // Mock a delayed response
    mockFetch.mockImplementation(() =>
      new Promise(resolve =>
        setTimeout(() => resolve({
          ok: true,
          json: async () => ({ message: 'Reset email sent', success: true }),
        }), 100)
      )
    );

    render(<ForgotPasswordPage />);

    const emailInput = screen.getByLabelText(/email address/i);
    const submitButton = screen.getByRole('button', { name: /send reset instructions/i });

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.click(submitButton);

    // Check for loading state
    await waitFor(() => {
      expect(screen.getByText(/sending reset instructions/i)).toBeInTheDocument();
    });
  });

  it('successfully sends reset email with valid email', async () => {
    // Mock successful response
    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        message: 'Password reset instructions have been sent to your email',
        success: true,
      }),
    });

    render(<ForgotPasswordPage />);

    const emailInput = screen.getByLabelText(/email address/i);
    const submitButton = screen.getByRole('button', { name: /send reset instructions/i });

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(mockFetch).toHaveBeenCalledWith(
        '/api/auth/forgot-password',
        expect.objectContaining({
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            email: 'test@example.com',
          }),
        })
      );
    });

    // Check for success message
    await waitFor(() => {
      expect(screen.getByText(/check your email/i)).toBeInTheDocument();
      expect(screen.getByText(/we've sent password reset instructions/i)).toBeInTheDocument();
    });
  });

  it('displays success message after successful submission', async () => {
    // Mock successful response
    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        message: 'Reset email sent',
        success: true,
      }),
    });

    render(<ForgotPasswordPage />);

    const emailInput = screen.getByLabelText(/email address/i);
    const submitButton = screen.getByRole('button', { name: /send reset instructions/i });

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/check your email/i)).toBeInTheDocument();
      expect(screen.getByText(/check your spam or junk folder/i)).toBeInTheDocument();
    });

    // Check for action buttons
    expect(screen.getByText(/send another email/i)).toBeInTheDocument();
    expect(screen.getByText(/back to login/i)).toBeInTheDocument();
  });

  it('allows sending another email after success', async () => {
    // Mock successful response
    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        message: 'Reset email sent',
        success: true,
      }),
    });

    render(<ForgotPasswordPage />);

    const emailInput = screen.getByLabelText(/email address/i);
    const submitButton = screen.getByRole('button', { name: /send reset instructions/i });

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.click(submitButton);

    // Wait for success message
    await waitFor(() => {
      expect(screen.getByText(/check your email/i)).toBeInTheDocument();
    });

    // Click "Send another email"
    const sendAnotherButton = screen.getByText(/send another email/i);
    fireEvent.click(sendAnotherButton);

    // Should show form again
    await waitFor(() => {
      expect(screen.getByLabelText(/email address/i)).toBeInTheDocument();
    });
  });

  it('shows error message on API failure', async () => {
    // Mock failed response
    mockFetch.mockResolvedValueOnce({
      ok: false,
      json: async () => ({
        error: 'Service unavailable',
      }),
    });

    render(<ForgotPasswordPage />);

    const emailInput = screen.getByLabelText(/email address/i);
    const submitButton = screen.getByRole('button', { name: /send reset instructions/i });

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/service unavailable/i)).toBeInTheDocument();
    });
  });

  it('shows error message on network error', async () => {
    // Mock network error
    mockFetch.mockRejectedValueOnce(new Error('Network error'));

    render(<ForgotPasswordPage />);

    const emailInput = screen.getByLabelText(/email address/i);
    const submitButton = screen.getByRole('button', { name: /send reset instructions/i });

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/network error/i)).toBeInTheDocument();
    });
  });

  it('has link to login page', () => {
    render(<ForgotPasswordPage />);

    const loginLink = screen.getByText(/sign in here/i).closest('a');

    expect(loginLink).toHaveAttribute('href', '/auth/login');
  });

  it('disables form inputs during submission', async () => {
    // Mock a delayed response
    mockFetch.mockImplementation(() =>
      new Promise(resolve =>
        setTimeout(() => resolve({
          ok: true,
          json: async () => ({ message: 'Reset email sent', success: true }),
        }), 100)
      )
    );

    render(<ForgotPasswordPage />);

    const emailInput = screen.getByLabelText(/email address/i) as HTMLInputElement;
    const submitButton = screen.getByRole('button', { name: /send reset instructions/i }) as HTMLButtonElement;

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.click(submitButton);

    // Check that inputs are disabled during submission
    await waitFor(() => {
      expect(emailInput.disabled).toBe(true);
      expect(submitButton.disabled).toBe(true);
    });
  });

  it('clears email field after successful submission', async () => {
    // Mock successful response
    mockFetch.mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        message: 'Reset email sent',
        success: true,
      }),
    });

    render(<ForgotPasswordPage />);

    const emailInput = screen.getByLabelText(/email address/i) as HTMLInputElement;
    const submitButton = screen.getByRole('button', { name: /send reset instructions/i });

    fireEvent.change(emailInput, { target: { value: 'test@example.com' } });
    fireEvent.click(submitButton);

    await waitFor(() => {
      expect(screen.getByText(/check your email/i)).toBeInTheDocument();
    });

    // Click "Send another email" to return to form
    const sendAnotherButton = screen.getByText(/send another email/i);
    fireEvent.click(sendAnotherButton);

    // Email field should be cleared
    await waitFor(() => {
      const newEmailInput = screen.getByLabelText(/email address/i) as HTMLInputElement;
      expect(newEmailInput.value).toBe('');
    });
  });
});
