import { NextRequest, NextResponse } from 'next/server';
import { aiServiceClient } from '@/lib/api';
import { setAuthTokens } from '@/lib/auth';

/**
 * Login request payload interface.
 */
interface LoginRequest {
  email: string;
  password: string;
}

/**
 * Login response from AI service.
 */
interface LoginResponse {
  accessToken: string;
  tokenType: string;
  user: {
    id: string;
    email: string;
    role: string;
  };
}

/**
 * POST /api/auth/login
 *
 * Authenticates a parent user with email and password.
 * Forwards the request to the AI service authentication endpoint.
 * Sets the access token in an httpOnly cookie for security.
 *
 * @param request - Next.js request object
 * @returns Authentication response with user data
 */
export async function POST(request: NextRequest) {
  try {
    // Parse request body
    const body: LoginRequest = await request.json();
    const { email, password } = body;

    // Validate input
    if (!email || !password) {
      return NextResponse.json(
        { error: 'Email and password are required' },
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

    // Call AI service authentication endpoint
    const response = await aiServiceClient.post<LoginResponse>(
      '/api/auth/login',
      {
        email,
        password,
      }
    );

    // Create response with user data (excluding token)
    const responseData = {
      user: response.user,
      message: 'Login successful',
    };

    // Create NextResponse and set authentication tokens
    const nextResponse = NextResponse.json(responseData, { status: 200 });
    setAuthTokens(nextResponse, { accessToken: response.accessToken });

    return nextResponse;
  } catch (error: any) {
    // Handle API errors
    if (error.status === 401) {
      return NextResponse.json(
        { error: 'Invalid email or password' },
        { status: 401 }
      );
    }

    if (error.status === 422) {
      return NextResponse.json(
        { error: error.body?.detail || 'Invalid input data' },
        { status: 422 }
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
        { error: 'Authentication request timed out' },
        { status: 504 }
      );
    }

    // Generic server error
    console.error('Login error:', error);
    return NextResponse.json(
      { error: 'An error occurred during authentication' },
      { status: 500 }
    );
  }
}
