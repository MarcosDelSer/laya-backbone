import { NextRequest, NextResponse } from 'next/server';
import { aiServiceClient } from '@/lib/api';

/**
 * Forgot password request payload interface.
 */
interface ForgotPasswordRequest {
  email: string;
}

/**
 * Forgot password response from AI service.
 */
interface ForgotPasswordResponse {
  message: string;
  resetTokenSent?: boolean;
}

/**
 * POST /api/auth/forgot-password
 *
 * Initiates a password reset flow by sending a reset token to the user's email.
 * Forwards the request to the AI service forgot password endpoint.
 *
 * @param request - Next.js request object
 * @returns Success response indicating reset email was sent
 */
export async function POST(request: NextRequest) {
  try {
    // Parse request body
    const body: ForgotPasswordRequest = await request.json();
    const { email } = body;

    // Validate input
    if (!email) {
      return NextResponse.json(
        { error: 'Email is required' },
        { status: 400 }
      );
    }

    // Email format validation
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      return NextResponse.json(
        { error: 'Invalid email format' },
        { status: 400 }
      );
    }

    // Call AI service forgot password endpoint
    const response = await aiServiceClient.post<ForgotPasswordResponse>(
      '/api/auth/forgot-password',
      { email }
    );

    // Return success response
    return NextResponse.json(
      {
        message: response.message || 'Password reset instructions have been sent to your email',
        success: true,
      },
      { status: 200 }
    );
  } catch (error: any) {
    // Handle API errors
    if (error.status === 404) {
      // For security, don't reveal if email exists
      return NextResponse.json(
        {
          message: 'If an account exists with this email, you will receive password reset instructions',
          success: true
        },
        { status: 200 }
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
        { error: 'Too many password reset requests. Please try again later.' },
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
    console.error('Forgot password error:', error);
    return NextResponse.json(
      { error: 'An error occurred while processing your request' },
      { status: 500 }
    );
  }
}
