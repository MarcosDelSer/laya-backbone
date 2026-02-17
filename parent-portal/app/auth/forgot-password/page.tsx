'use client';

import { useState, FormEvent } from 'react';
import Link from 'next/link';

/**
 * Forgot password page component for initiating password reset.
 *
 * Features:
 * - Email input for password reset request
 * - Client-side email validation
 * - Error handling and display
 * - Success message display
 * - Loading states during submission
 * - Link back to login page
 */
export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  /**
   * Handle form submission and request password reset.
   */
  const handleSubmit = async (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    setError(null);
    setSuccess(false);
    setIsLoading(true);

    try {
      // Validate input
      if (!email) {
        setError('Please enter your email address');
        setIsLoading(false);
        return;
      }

      // Email format validation
      const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email)) {
        setError('Please enter a valid email address');
        setIsLoading(false);
        return;
      }

      // Call password reset API
      const response = await fetch('/api/auth/forgot-password', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email }),
      });

      const data = await response.json();

      if (!response.ok) {
        throw new Error(data.error || 'Failed to send password reset email');
      }

      // Show success message
      setSuccess(true);
      setEmail('');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'An error occurred while requesting password reset');
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4 py-12 sm:px-6 lg:px-8">
      <div className="w-full max-w-md space-y-8">
        {/* Header */}
        <div className="text-center">
          <h1 className="text-3xl font-bold text-gray-900">
            LAYA Parent Portal
          </h1>
          <h2 className="mt-6 text-2xl font-semibold text-gray-900">
            Reset your password
          </h2>
          <p className="mt-2 text-sm text-gray-600">
            Enter your email address and we&apos;ll send you instructions to reset your password
          </p>
        </div>

        {/* Reset Password Form */}
        <div className="card mt-8">
          <div className="card-body">
            {success ? (
              // Success Message
              <div className="space-y-6">
                <div className="rounded-md bg-green-50 border border-green-200 p-4">
                  <div className="flex">
                    <div className="flex-shrink-0">
                      <svg
                        className="h-5 w-5 text-green-400"
                        viewBox="0 0 20 20"
                        fill="currentColor"
                      >
                        <path
                          fillRule="evenodd"
                          d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                          clipRule="evenodd"
                        />
                      </svg>
                    </div>
                    <div className="ml-3">
                      <h3 className="text-sm font-medium text-green-800">
                        Check your email
                      </h3>
                      <div className="mt-2 text-sm text-green-700">
                        <p>
                          We&apos;ve sent password reset instructions to your email address.
                          Please check your inbox and follow the link to reset your password.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>

                <div className="text-sm text-gray-600">
                  <p className="mb-2">Didn&apos;t receive the email?</p>
                  <ul className="list-disc list-inside space-y-1 text-xs">
                    <li>Check your spam or junk folder</li>
                    <li>Make sure you entered the correct email address</li>
                    <li>Wait a few minutes and check again</li>
                  </ul>
                </div>

                <div className="flex gap-3">
                  <button
                    onClick={() => setSuccess(false)}
                    className="btn btn-secondary flex-1"
                  >
                    Send another email
                  </button>
                  <Link
                    href="/auth/login"
                    className="btn btn-primary flex-1 text-center"
                  >
                    Back to login
                  </Link>
                </div>
              </div>
            ) : (
              // Reset Form
              <form onSubmit={handleSubmit} className="space-y-6">
                {/* Error Alert */}
                {error && (
                  <div className="rounded-md bg-red-50 border border-red-200 p-4">
                    <div className="flex">
                      <div className="flex-shrink-0">
                        <svg
                          className="h-5 w-5 text-red-400"
                          viewBox="0 0 20 20"
                          fill="currentColor"
                        >
                          <path
                            fillRule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                            clipRule="evenodd"
                          />
                        </svg>
                      </div>
                      <div className="ml-3">
                        <p className="text-sm font-medium text-red-800">{error}</p>
                      </div>
                    </div>
                  </div>
                )}

                {/* Email Field */}
                <div>
                  <label
                    htmlFor="email"
                    className="block text-sm font-medium text-gray-700"
                  >
                    Email address
                  </label>
                  <div className="mt-1">
                    <input
                      id="email"
                      name="email"
                      type="email"
                      autoComplete="email"
                      required
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      disabled={isLoading}
                      className="block w-full appearance-none rounded-md border border-gray-300 px-3 py-2 placeholder-gray-400 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-primary-500 disabled:bg-gray-100 disabled:cursor-not-allowed sm:text-sm"
                      placeholder="your.email@example.com"
                    />
                  </div>
                  <p className="mt-1 text-xs text-gray-500">
                    Enter the email address associated with your account
                  </p>
                </div>

                {/* Submit Button */}
                <div>
                  <button
                    type="submit"
                    disabled={isLoading}
                    className="btn btn-primary w-full"
                  >
                    {isLoading ? (
                      <span className="flex items-center justify-center">
                        <svg
                          className="animate-spin -ml-1 mr-3 h-5 w-5 text-white"
                          xmlns="http://www.w3.org/2000/svg"
                          fill="none"
                          viewBox="0 0 24 24"
                        >
                          <circle
                            className="opacity-25"
                            cx="12"
                            cy="12"
                            r="10"
                            stroke="currentColor"
                            strokeWidth="4"
                          />
                          <path
                            className="opacity-75"
                            fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                          />
                        </svg>
                        Sending reset instructions...
                      </span>
                    ) : (
                      'Send reset instructions'
                    )}
                  </button>
                </div>
              </form>
            )}

            {/* Login Link */}
            {!success && (
              <div className="mt-6 border-t border-gray-200 pt-6">
                <p className="text-center text-sm text-gray-600">
                  Remember your password?{' '}
                  <Link
                    href="/auth/login"
                    className="font-medium text-primary-600 hover:text-primary-500"
                  >
                    Sign in here
                  </Link>
                </p>
              </div>
            )}
          </div>
        </div>

        {/* Help Text */}
        <p className="text-center text-xs text-gray-500">
          Need help? Contact your childcare center administrator
        </p>
      </div>
    </div>
  );
}
