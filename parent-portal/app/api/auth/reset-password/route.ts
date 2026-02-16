import { NextRequest, NextResponse } from 'next/server';
import { aiServiceClient } from '@/lib/api';

/**
 * Reset password request payload interface.
 */
interface ResetPasswordRequest {
  token: string;
  password: string;
}

/**
 * Reset password response from AI service.
 */
interface ResetPasswordResponse {
  message: string;
  success?: boolean;
}

/**
 * POST /api/auth/reset-password
 *
 * Confirms a password reset by validating the reset token and setting a new password.
 * Forwards the request to the AI service reset password endpoint.
 *
 * @param request - Next.js request object
 * @returns Success response indicating password was reset
 */
export async function POST(request: NextRequest) {
  try {
    // Parse request body
    const body: ResetPasswordRequest = await request.json();
    const { token, password } = body;

    // Validate input
    if (!token || !password) {
      return NextResponse.json(
        { error: 'Reset token and new password are required' },
        { status: 400 }
      );
    }

    // Password length validation
    if (password.length < 8) {
      return NextResponse.json(
        { error: 'Password must be at least 8 characters long' },
        { status: 400 }
      );
    }

    // Call AI service reset password endpoint
    const response = await aiServiceClient.post<ResetPasswordResponse>(
      '/api/auth/reset-password',
      {
        token,
        password,
      }
    );

    // Return success response
    return NextResponse.json(
      {
        message: response.message || 'Your password has been reset successfully',
        success: true,
      },
      { status: 200 }
    );
  } catch (error: any) {
    // Handle API errors
    if (error.status === 400 || error.status === 401) {
      return NextResponse.json(
        { error: 'Invalid or expired reset token. Please request a new password reset.' },
        { status: 400 }
      );
    }

    if (error.status === 422) {
      return NextResponse.json(
        { error: error.body?.detail || 'Invalid input data' },
        { status: 422 }
      );
    }

    if (error.status === 429) {
      return NextResponse.json(
        { error: 'Too many password reset attempts. Please try again later.' },
        { status: 429 }
      );
    }

    // Handle network/timeout errors
    if (error.isNetworkError) {
      return NextResponse.json(
        { error: 'Unable to connect to authentication service' },
        { status: 503 }
      );
    }

    if (error.isTimeout) {
      return NextResponse.json(
        { error: 'Request timed out. Please try again.' },
        { status: 504 }
      );
    }

    // Generic server error
    console.error('Reset password error:', error);
    return NextResponse.json(
      { error: 'An error occurred while resetting your password' },
      { status: 500 }
    );
  }
}
