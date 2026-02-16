/**
 * Current User API Route - GET /api/auth/me
 *
 * Returns the currently authenticated user's information.
 * Used by AuthContext to validate session and get user data.
 *
 * Security:
 * - Requires valid authentication token in httpOnly cookie
 * - Returns 401 if not authenticated or token expired
 * - Does not expose sensitive information
 */

import { NextRequest, NextResponse } from 'next/server';
import { getServerToken, decodeToken, getUserFromToken } from '@/lib/auth';

/**
 * GET /api/auth/me
 *
 * Get current authenticated user information.
 *
 * @returns User object if authenticated, 401 error if not
 *
 * @example
 * ```ts
 * const response = await fetch('/api/auth/me', {
 *   credentials: 'include',
 * });
 * const { user } = await response.json();
 * ```
 */
export async function GET(request: NextRequest) {
  try {
    // Get token from httpOnly cookie
    const token = await getServerToken();

    if (!token) {
      return NextResponse.json(
        { error: 'Not authenticated' },
        { status: 401 }
      );
    }

    // Get user from token
    const user = getUserFromToken(token);

    if (!user) {
      return NextResponse.json(
        { error: 'Invalid token' },
        { status: 401 }
      );
    }

    // Return user information
    return NextResponse.json({
      user,
    });
  } catch (error) {
    console.error('Error in /api/auth/me:', error);
    return NextResponse.json(
      { error: 'Internal server error' },
      { status: 500 }
    );
  }
}
