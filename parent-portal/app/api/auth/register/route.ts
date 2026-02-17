import { NextRequest, NextResponse } from 'next/server';
import { aiServiceClient } from '@/lib/api';
import { setAuthTokens } from '@/lib/auth';
import { withCsrfProtection } from '@/lib/csrf';

/**
 * Registration request payload interface.
 */
interface RegisterRequest {
  firstName: string;
  lastName: string;
  email: string;
  password: string;
  phone?: string;
}

/**
 * Registration response from AI service.
 */
interface RegisterResponse {
  accessToken: string;
  tokenType: string;
  user: {
    id: string;
    email: string;
    firstName: string;
    lastName: string;
    role: string;
  };
}

/**
 * POST /api/auth/register
 *
 * Registers a new parent user with the system.
 * Forwards the request to the AI service registration endpoint.
 * Sets the access token in an httpOnly cookie for security.
 *
 * CSRF Protection: This endpoint requires a valid CSRF token to prevent
 * cross-site request forgery attacks. The token must be included in the
 * X-CSRF-Token header or in the request body as 'csrf_token'.
 *
 * @param request - Next.js request object
 * @returns Registration response with user data
 */
async function handleRegister(request: NextRequest) {
  try {
    // Parse request body
    const body: RegisterRequest = await request.json();
    const { firstName, lastName, email, password, phone } = body;

    // Validate required fields
    if (!firstName || !lastName || !email || !password) {
      return NextResponse.json(
        { error: 'First name, last name, email, and password are required' },
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

    // Password length validation
    if (password.length < 8) {
      return NextResponse.json(
        { error: 'Password must be at least 8 characters long' },
        { status: 400 }
      );
    }

    // Phone number validation (if provided)
    if (phone && !/^[\d\s\-\+\(\)]+$/.test(phone)) {
      return NextResponse.json(
        { error: 'Invalid phone number format' },
        { status: 400 }
      );
    }

    // Call AI service registration endpoint
    const response = await aiServiceClient.post<RegisterResponse>(
      '/api/auth/register',
      {
        firstName,
        lastName,
        email,
        password,
        phone,
        role: 'parent', // Explicitly set role as parent
      }
    );

    // Create response with user data (excluding token)
    const responseData = {
      user: response.user,
      message: 'Registration successful',
    };

    // Create NextResponse and set authentication tokens
    const nextResponse = NextResponse.json(responseData, { status: 201 });
    setAuthTokens(nextResponse, { accessToken: response.accessToken });

    return nextResponse;
  } catch (error: any) {
    // Handle API errors
    if (error.status === 409) {
      return NextResponse.json(
        { error: 'An account with this email already exists' },
        { status: 409 }
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
        { error: 'Registration request timed out' },
        { status: 504 }
      );
    }

    // Generic server error
    console.error('Registration error:', error);
    return NextResponse.json(
      { error: 'An error occurred during registration' },
      { status: 500 }
    );
  }
}

/**
 * Export POST handler wrapped with CSRF protection.
 * This ensures that all registration requests are validated for CSRF tokens.
 */
export const POST = withCsrfProtection(handleRegister);
