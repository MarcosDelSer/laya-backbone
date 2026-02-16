# Password Reset Flow

## Overview

The password reset functionality allows parents to securely reset their passwords through a token-based email verification flow. This document describes the implementation, security features, and usage.

## Architecture

### Flow Diagram

```
1. User requests password reset (forgot-password page)
   ↓
2. API sends reset token via email
   ↓
3. User clicks link in email with token
   ↓
4. User sets new password (reset-password page)
   ↓
5. Password updated, redirect to login
```

## Components

### 1. Forgot Password Page (`/auth/forgot-password`)

**Location:** `parent-portal/app/auth/forgot-password/page.tsx`

**Features:**
- Email input form
- Client-side email validation
- Success message display
- Error handling
- Loading states
- Link back to login

**User Flow:**
1. User enters email address
2. System validates email format
3. API request sent to `/api/auth/forgot-password`
4. Success message displayed (even if email not found for security)
5. User can request another email or return to login

**Security Considerations:**
- Always shows success message (prevents email enumeration)
- Rate limiting handled by AI service
- Email format validation on client and server

### 2. Reset Password Page (`/auth/reset-password`)

**Location:** `parent-portal/app/auth/reset-password/page.tsx`

**Features:**
- Token extraction from URL query parameters
- Password input with confirmation
- Password strength validation (min 8 characters)
- Error handling for invalid/expired tokens
- Success message with auto-redirect
- Loading states

**User Flow:**
1. User clicks link from email: `/auth/reset-password?token=xyz`
2. System validates token presence
3. User enters new password and confirmation
4. System validates passwords match and meet requirements
5. API request sent to `/api/auth/reset-password`
6. Success message displayed
7. Auto-redirect to login after 3 seconds

**Error Handling:**
- Missing token: Shows error page with link to request new reset
- Invalid/expired token: Shows error after API call
- Password validation errors: Client-side validation
- Network errors: User-friendly error messages

### 3. Forgot Password API (`/api/auth/forgot-password`)

**Location:** `parent-portal/app/api/auth/forgot-password/route.ts`

**Endpoint:** `POST /api/auth/forgot-password`

**Request Body:**
```json
{
  "email": "user@example.com"
}
```

**Response (Success):**
```json
{
  "message": "Password reset instructions have been sent to your email",
  "success": true
}
```

**Security Features:**
- Email format validation
- Always returns success (prevents enumeration)
- Rate limiting (via AI service)
- Forwards to AI service for processing

**Error Handling:**
- 400: Invalid email format
- 422: Invalid input data
- 429: Too many requests
- 503: Service unavailable
- 504: Request timeout

### 4. Reset Password API (`/api/auth/reset-password`)

**Location:** `parent-portal/app/api/auth/reset-password/route.ts`

**Endpoint:** `POST /api/auth/reset-password`

**Request Body:**
```json
{
  "token": "reset-token-from-email",
  "password": "newpassword123"
}
```

**Response (Success):**
```json
{
  "message": "Your password has been reset successfully",
  "success": true
}
```

**Security Features:**
- Token validation (via AI service)
- Password strength validation (min 8 characters)
- Token single-use enforcement (handled by AI service)
- Token expiration (handled by AI service)

**Error Handling:**
- 400: Invalid or expired token / Password too short
- 422: Invalid input data
- 429: Too many attempts
- 503: Service unavailable
- 504: Request timeout

## Testing

### Test Coverage

**Forgot Password Tests:** `parent-portal/__tests__/auth-forgot-password.test.tsx`
- Form rendering
- Email input validation
- Empty form submission
- Invalid email format
- Loading states
- Successful submission
- Success message display
- Send another email functionality
- API error handling
- Network error handling
- Form input disabling
- Email field clearing

**Reset Password Tests:** `parent-portal/__tests__/auth-reset-password.test.tsx`
- Missing token handling
- Form rendering with token
- Password input validation
- Empty form submission
- Password length validation
- Password mismatch validation
- Loading states
- Successful password reset
- Success message display
- Invalid token handling
- API error handling
- Network error handling
- Form input disabling
- Link navigation

### Running Tests

```bash
cd parent-portal
npm test auth-forgot-password.test.tsx
npm test auth-reset-password.test.tsx
```

Expected coverage: >80% for all components

## Security Best Practices

### 1. Token Security
- Tokens generated and validated by AI service
- Tokens are single-use (invalidated after successful reset)
- Tokens have expiration time (configured in AI service)
- Tokens transmitted via URL (short-lived, HTTPS only in production)

### 2. Email Enumeration Prevention
- Forgot password always returns success message
- Same response whether email exists or not
- Prevents attackers from discovering valid email addresses

### 3. Rate Limiting
- AI service enforces rate limits on reset requests
- 429 status code returned when limit exceeded
- Prevents brute force attacks

### 4. Password Requirements
- Minimum 8 characters
- Validated on both client and server
- Can be extended with additional requirements

### 5. HTTPS Only (Production)
- All password reset links use HTTPS in production
- Prevents token interception
- Cookies set with secure flag

## Integration with AI Service

The password reset flow integrates with the AI service authentication endpoints:

### AI Service Endpoints Used:
1. `POST /api/auth/forgot-password` - Generate and send reset token
2. `POST /api/auth/reset-password` - Validate token and reset password

### Expected AI Service Behavior:
- Generate secure random tokens
- Send email with reset link
- Store token with expiration
- Validate token on reset
- Invalidate token after use
- Hash new password securely

## User Experience

### Email Template (AI Service)

The reset email should contain:
- Clear subject line: "Reset Your LAYA Password"
- Brief explanation
- Reset link: `https://portal.laya.app/auth/reset-password?token={token}`
- Link expiration time (e.g., "Valid for 1 hour")
- Alternative text if link doesn't work
- Contact information for help

### Success Messages

**Forgot Password Success:**
```
✓ Check your email

We've sent password reset instructions to your email address.
Please check your inbox and follow the link to reset your password.

Didn't receive the email?
• Check your spam or junk folder
• Make sure you entered the correct email address
• Wait a few minutes and check again
```

**Reset Password Success:**
```
✓ Password reset successful!

Your password has been reset successfully.
You will be redirected to the login page in a few seconds.
```

## Error Handling

### User-Friendly Error Messages

| Scenario | Message |
|----------|---------|
| Invalid email format | "Please enter a valid email address" |
| Empty fields | "Please fill in all required fields" |
| Password too short | "Password must be at least 8 characters long" |
| Passwords don't match | "Passwords do not match" |
| Invalid token | "Invalid or expired reset token. Please request a new password reset." |
| Network error | "Unable to connect to authentication service" |
| Timeout | "Request timed out. Please try again." |
| Rate limit | "Too many password reset requests. Please try again later." |

## Maintenance

### Monitoring

Monitor the following metrics:
- Password reset request rate
- Success rate of password resets
- Token expiration rate (users not completing reset)
- Error rates by type

### Common Issues

**Issue:** Users not receiving emails
- Check AI service email configuration
- Verify email provider settings
- Check spam filters

**Issue:** Token expired errors
- Review token expiration time
- Consider extending if users report issues
- Improve email delivery speed

**Issue:** Too many reset requests
- Review rate limiting configuration
- Investigate potential abuse
- Consider CAPTCHA for reset requests

## Future Enhancements

Potential improvements:
1. CAPTCHA integration for reset requests
2. Multi-factor authentication for password reset
3. Password strength meter on reset page
4. Remember device functionality
5. Account lockout after multiple failed attempts
6. Security questions as alternative verification
7. Password history (prevent reuse of recent passwords)

## Links

- Login Page: `/auth/login`
- Registration Page: `/auth/register`
- Forgot Password: `/auth/forgot-password`
- Reset Password: `/auth/reset-password?token={token}`

## Related Documentation

- [Authentication Flow](./auth-flow.md) - Overall authentication system
- [Token Management](./token-management.md) - Token storage and validation
- [Middleware](./middleware.md) - Protected routes
- [Logout Flow](./logout-flow.md) - User logout process
