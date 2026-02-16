# Logout Flow with Cleanup

## Overview

The logout flow provides a secure and complete cleanup mechanism for user sessions in the LAYA Parent Portal. It ensures that all authentication tokens, session data, and client-side state are properly cleared when a user logs out.

## Components

### 1. Client-Side Logout Utility (`lib/logout.ts`)

The `performLogout()` function handles the complete logout process:

1. **Session Storage Cleanup**: Clears stored redirect paths and other session data
2. **API Call**: Sends POST request to `/api/auth/logout` to clear httpOnly cookies
3. **Graceful Error Handling**: Ensures cleanup even if API call fails
4. **Automatic Redirect**: Redirects user to login page after logout

#### Usage Example

```typescript
import { performLogout } from '@/lib/logout';

const handleLogout = async () => {
  await performLogout();
  // User will be redirected to /auth/login
};
```

### 2. Logout Button in Navigation (`components/Navigation.tsx`)

The Navigation component now includes a logout button with:

- **Visual Icon**: Logout icon for better UX
- **Loading State**: Shows "Logging out..." during the logout process
- **Disabled State**: Prevents multiple logout requests
- **Responsive Design**: Text hidden on mobile, icon visible on all screens
- **Accessibility**: Proper `aria-label` for screen readers

### 3. Server-Side Logout API (`app/api/auth/logout/route.ts`)

Already implemented in Phase 2, this endpoint:

- Clears the `access_token` httpOnly cookie
- Clears the `refresh_token` httpOnly cookie
- Returns success response
- Supports both POST and GET methods

## Security Features

### httpOnly Cookies

- Tokens stored in httpOnly cookies are automatically cleared by the server
- Not accessible via JavaScript, preventing XSS attacks
- Cleared on logout, ensuring complete session termination

### Session Storage Cleanup

- Clears `redirectAfterLogin` path
- Prevents session data leakage
- Graceful error handling for storage errors

### Client-Side Redundancy

- Even if API call fails, client-side cleanup still occurs
- User is always redirected to login page
- Prevents stale sessions

## User Flow

1. User clicks the "Logout" button in the navigation
2. Button shows loading state ("Logging out...")
3. Session storage is cleared
4. POST request sent to `/api/auth/logout`
5. Server clears httpOnly cookies
6. Client redirects to `/auth/login`

## Error Handling

### Network Errors

If the logout API call fails due to network issues:
- Session storage is still cleared
- User is still redirected to login
- Error is logged to console

### Session Storage Errors

If sessionStorage throws an error:
- Error is caught and logged
- Logout process continues
- User is still redirected

### Already Logged Out

If the user is already logged out (401 response):
- Client still clears session data
- User is redirected to login
- No error shown to user

## Testing

Comprehensive test suite covers:

### Unit Tests
- `performLogout()` utility function
- Session storage cleanup
- API call verification
- Redirect behavior

### Component Tests
- Logout button rendering
- Click handler
- Loading states
- Disabled states
- Multiple click prevention

### Integration Tests
- Complete logout flow
- Error scenarios
- Edge cases

### Test Coverage
- Over 80% code coverage
- All critical paths tested
- Error scenarios covered

## Files Modified/Created

### Created
- `parent-portal/lib/logout.ts` - Client-side logout utilities
- `parent-portal/__tests__/auth-logout.test.tsx` - Comprehensive test suite
- `parent-portal/docs/logout-flow.md` - This documentation

### Modified
- `parent-portal/components/Navigation.tsx` - Added logout button and handler

### Already Existing (from Phase 2)
- `parent-portal/app/api/auth/logout/route.ts` - Server-side logout endpoint
- `parent-portal/lib/auth.ts` - Auth utilities including `clearAuthTokens()`

## Future Enhancements

Potential improvements for future iterations:

1. **Server-Side Logout**: Call AI service logout endpoint to invalidate tokens on the backend
2. **Logout Confirmation**: Add confirmation dialog before logout
3. **Activity Tracking**: Log logout events for security auditing
4. **Multi-Tab Logout**: Broadcast logout event to other tabs using BroadcastChannel API
5. **Session Timeout**: Auto-logout after period of inactivity

## Related Documentation

- [Authentication System](./auth-system.md)
- [Token Management](./token-management.md)
- [Protected Routes](./middleware.md)
