import { NextRequest, NextResponse } from 'next/server';
import { clearAuthTokens } from '@/lib/auth';

/**
 * POST /api/auth/logout
 *
 * Logs out the current user by clearing authentication tokens.
 * This invalidates the user's session on the client side.
 *
 * @param request - Next.js request object
 * @returns Success response with cleared cookies
 */
export async function POST(request: NextRequest) {
  try {
    // Create success response
    const response = NextResponse.json(
      { message: 'Logged out successfully' },
      { status: 200 }
    );

    // Clear authentication tokens
    clearAuthTokens(response);

    return response;
  } catch (error) {
    console.error('Logout error:', error);
    return NextResponse.json(
      { error: 'An error occurred during logout' },
      { status: 500 }
    );
  }
}

/**
 * GET /api/auth/logout
 *
 * Alternative logout endpoint for GET requests.
 * Supports logout via direct navigation or link.
 */
export async function GET(request: NextRequest) {
  return POST(request);
}
