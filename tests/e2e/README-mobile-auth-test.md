# Mobile Authentication Flow E2E Tests

This directory contains end-to-end tests for verifying the mobile authentication flow in the **parent-app** React Native application.

## Overview

Mobile authentication security is critical for protecting user data. These tests verify that:
1. Users can login via mobile app with secure credential handling
2. JWT tokens are stored in secure storage (iOS Keychain / Android Keystore), NOT AsyncStorage
3. Authenticated API requests automatically include JWT tokens
4. JWT tokens are properly formatted in Authorization headers
5. Logout completely clears all tokens from secure storage

## Test Coverage

The mobile authentication test suite (`mobile-auth-flow.test.ts`) includes:

### Step 1: Login via Mobile App
- ✅ Successful login with valid credentials
- ✅ Login failure with invalid credentials
- ✅ User information storage after login
- ✅ Token storage after successful authentication
- ✅ Error handling for network failures

### Step 2: Verify Token Stored in Secure Storage
- ✅ Access token stored in secure storage (not AsyncStorage)
- ✅ Refresh token stored in secure storage
- ✅ Secure storage API usage (iOS Keychain / Android Keystore)
- ✅ Storage error handling
- ✅ Verification that AsyncStorage is NOT used for tokens

### Step 3: Make Authenticated API Request
- ✅ JWT token automatically included in authenticated requests
- ✅ POST requests with authentication
- ✅ GET requests with authentication
- ✅ PUT/PATCH/DELETE requests with authentication
- ✅ Public endpoints without authentication (skipAuth)

### Step 4: Verify JWT Included in Headers
- ✅ Authorization header format: `Bearer <token>`
- ✅ Automatic token retrieval from secure storage
- ✅ Token refresh on 401 response
- ✅ Retry with new token after refresh
- ✅ Multiple requests with same token

### Step 5: Logout and Verify Token Cleared
- ✅ All tokens cleared from secure storage
- ✅ User information cleared
- ✅ isAuthenticated returns false after logout
- ✅ Subsequent requests without authentication
- ✅ Logout succeeds even if server call fails (best effort)

### Complete Integration Flow
- ✅ Full lifecycle: Login → Store → Request → Verify → Logout
- ✅ End-to-end workflow with all steps verified
- ✅ Console output for manual verification

### Security Validation
- ✅ Tokens not exposed in console or error messages
- ✅ Token refresh failure clears tokens (security fallback)
- ✅ Timing attack prevention
- ✅ Secure token comparison

## Prerequisites

### For Automated Tests (Jest)

1. **Node.js and npm:**
   ```bash
   node --version  # v18+ recommended
   npm --version
   ```

2. **parent-app dependencies:**
   ```bash
   cd parent-app
   npm install
   ```

3. **Required packages:**
   - `jest` - Test runner
   - `@testing-library/react-native` - React Native testing utilities
   - `react-native-keychain` - Secure storage (mocked in tests)

### For Manual Device Testing

4. **React Native development environment:**
   - For iOS: Xcode, CocoaPods
   - For Android: Android Studio, Android SDK

5. **Device debugging tools:**
   - Flipper (network inspector, secure storage viewer)
   - React Native Debugger (alternative)
   - Xcode Device Manager (iOS Keychain inspection)
   - Android Studio Device File Explorer (Android Keystore inspection)

## Running Tests

### Automated Tests (Recommended)

#### Quick Start

```bash
# Run automated verification script
./tests/e2e/verify-mobile-auth-flow.sh
```

This script will:
- Check all prerequisites
- Run the complete test suite
- Display manual verification instructions
- Provide detailed feedback

#### Run Tests Manually

```bash
# Run all mobile auth flow tests
npm test tests/e2e/mobile-auth-flow.test.ts

# Run with verbose output
npm test tests/e2e/mobile-auth-flow.test.ts -- --verbose

# Run with coverage
npm test tests/e2e/mobile-auth-flow.test.ts -- --coverage

# Run specific test suite
npm test tests/e2e/mobile-auth-flow.test.ts -- --testNamePattern="Step 1: Login"

# Run in watch mode (for development)
npm test tests/e2e/mobile-auth-flow.test.ts -- --watch
```

### Manual Device Testing

#### iOS Device Testing

1. **Build and run the app:**
   ```bash
   cd parent-app
   npx react-native run-ios
   # Or open ios/ParentApp.xcworkspace in Xcode and run
   ```

2. **Login with test credentials:**
   - Email: `parent@example.com`
   - Password: Your test password

3. **Verify token storage in Keychain:**
   - Open Xcode
   - Window → Devices and Simulators
   - Select your device/simulator
   - Select the app
   - Click the gear icon → Show Container
   - Navigate to Library/Preferences
   - **Verify:** No token files in plain text
   - **Verify:** Tokens stored in iOS Keychain (not visible in file system)

4. **Use Flipper to inspect network requests:**
   ```bash
   # Start Flipper
   open /Applications/Flipper.app
   ```
   - Connect to your device
   - Open "Network" plugin
   - Perform login
   - **Verify:** `POST /auth/login` request succeeds
   - Make an authenticated request (e.g., view children)
   - **Verify:** Request includes `Authorization: Bearer <token>` header
   - **Verify:** Token matches JWT format: `eyJhbGciOi...`

5. **Verify secure storage (Flipper):**
   - Open "React Native Keychain" plugin in Flipper
   - **Verify:** Keys exist for access_token and refresh_token
   - **Verify:** Tokens are encrypted (not plain text)

6. **Test logout:**
   - Logout from the app
   - Check Flipper "React Native Keychain" plugin
   - **Verify:** All tokens removed from Keychain
   - Make an authenticated request
   - **Verify:** Request fails with 401 or has no Authorization header

#### Android Device Testing

1. **Build and run the app:**
   ```bash
   cd parent-app
   npx react-native run-android
   ```

2. **Login with test credentials:**
   - Email: `parent@example.com`
   - Password: Your test password

3. **Verify token storage in Keystore:**
   - Open Android Studio
   - View → Tool Windows → Device File Explorer
   - Navigate to: `/data/data/com.parentapp/shared_prefs/`
   - **Verify:** No token in SharedPreferences XML files
   - **Verify:** Tokens stored in Android Keystore (encrypted, not visible)

4. **Use Flipper to inspect network requests:**
   ```bash
   # Start Flipper
   flipper
   ```
   - Connect to your Android device
   - Open "Network" plugin
   - Perform login
   - **Verify:** `POST /auth/login` request succeeds
   - Make an authenticated request
   - **Verify:** Request includes `Authorization: Bearer <token>` header

5. **Test logout:**
   - Logout from the app
   - Check Flipper "React Native Keychain" plugin
   - **Verify:** All tokens removed from Keystore
   - Try to make authenticated request
   - **Verify:** Request fails or has no Authorization header

## Verification Checklist

Use this checklist to ensure complete verification:

### Automated Tests (Jest)
- [ ] All Step 1 tests pass (Login via mobile app)
- [ ] All Step 2 tests pass (Token stored in secure storage)
- [ ] All Step 3 tests pass (Authenticated API requests)
- [ ] All Step 4 tests pass (JWT in headers)
- [ ] All Step 5 tests pass (Logout and token cleared)
- [ ] Complete integration flow test passes
- [ ] All security validation tests pass

### Manual iOS Verification
- [ ] App builds and runs successfully on iOS device/simulator
- [ ] Login works with valid credentials
- [ ] Login fails with invalid credentials (appropriate error message)
- [ ] Tokens NOT found in UserDefaults/file system
- [ ] Tokens stored in iOS Keychain (verified via Xcode or Flipper)
- [ ] Authenticated requests include `Authorization: Bearer <token>` header
- [ ] JWT token format is valid (three base64 segments separated by dots)
- [ ] Token refresh works on 401 response
- [ ] Logout clears all tokens from Keychain
- [ ] Requests after logout have no Authorization header

### Manual Android Verification
- [ ] App builds and runs successfully on Android device/emulator
- [ ] Login works with valid credentials
- [ ] Login fails with invalid credentials (appropriate error message)
- [ ] Tokens NOT found in SharedPreferences XML
- [ ] Tokens stored in Android Keystore (encrypted)
- [ ] Authenticated requests include `Authorization: Bearer <token>` header
- [ ] JWT token format is valid
- [ ] Token refresh works on 401 response
- [ ] Logout clears all tokens from Keystore
- [ ] Requests after logout have no Authorization header

### Security Verification
- [ ] Tokens never logged to console
- [ ] Tokens never in error messages
- [ ] Tokens NOT in AsyncStorage (check with React Native Debugger)
- [ ] Tokens NOT in plain text files
- [ ] Token refresh prevents multiple simultaneous attempts
- [ ] Failed refresh clears tokens (security fallback)
- [ ] Logout succeeds even if server unreachable

## Architecture

### Secure Storage Layer

**File:** `parent-app/src/utils/secureStorage.ts`

The secure storage layer provides:
- **iOS:** Uses `react-native-keychain` → iOS Keychain Services
- **Android:** Uses `react-native-keychain` → Android Keystore System with AES encryption
- **Development/Testing:** Falls back to in-memory storage (not persisted)

Key features:
- Type-safe API with explicit error handling
- Automatic JSON serialization/deserialization
- Storage key namespacing (`laya_parent_secure_*`)
- Support for biometric authentication
- Configurable access control levels

**IMPORTANT:** Tokens are NEVER stored in AsyncStorage, which is not encrypted and vulnerable to access by other apps or malware.

### API Client Layer

**File:** `parent-app/src/api/secureClient.ts`

The API client provides:
- Automatic JWT token management
- Token retrieval from secure storage before each request
- Automatic token refresh on 401 responses
- Retry logic with new token after refresh
- Error handling for all HTTP status codes
- Request timeout support
- Type-safe request/response handling

### Authentication Flow

```
┌─────────────────────────────────────────────────────────────┐
│                     Login Flow                              │
└─────────────────────────────────────────────────────────────┘
1. User enters credentials
2. authApi.login(email, password)
3. POST /auth/login (no token, skipAuth: true)
4. Server validates credentials
5. Server returns { user, accessToken, refreshToken }
6. Store tokens in secure storage (Keychain/Keystore)
7. Store user info (id, email)
8. Return success

┌─────────────────────────────────────────────────────────────┐
│                Authenticated Request Flow                    │
└─────────────────────────────────────────────────────────────┘
1. App makes request: secureApi.get('/api/v1/children')
2. Retrieve access token from secure storage
3. Add Authorization: Bearer <token> header
4. Send request to server
5. Server validates JWT signature and expiration
6. Server returns data
7. Return data to app

┌─────────────────────────────────────────────────────────────┐
│                   Token Refresh Flow                        │
└─────────────────────────────────────────────────────────────┘
1. App makes request with expired access token
2. Server returns 401 Unauthorized
3. Retrieve refresh token from secure storage
4. POST /auth/refresh with refreshToken
5. Server validates refresh token
6. Server returns new { accessToken, refreshToken }
7. Store new tokens in secure storage
8. Retry original request with new access token
9. Server returns data
10. Return data to app

┌─────────────────────────────────────────────────────────────┐
│                     Logout Flow                             │
└─────────────────────────────────────────────────────────────┘
1. User initiates logout
2. authApi.logout()
3. (Best effort) POST /auth/logout to server
4. Clear access token from secure storage
5. Clear refresh token from secure storage
6. Clear user info (id, email)
7. Return success (even if server call fails)
8. Future requests have no Authorization header
```

## Troubleshooting

### Automated Tests Failing

**Issue:** Tests fail with "react-native-keychain" import error

**Solution:**
```bash
# Verify mock is configured in jest.config.js or test file
# The test file already includes the mock at the top
```

**Issue:** Tests fail with network timeout

**Solution:**
- Tests use mocked fetch, not real network
- Check that `global.fetch = mockFetch` is set up
- Verify mock responses are configured correctly

### Manual Testing Issues

**Issue:** Cannot see tokens in Flipper

**Solution:**
```bash
# Ensure Flipper is installed and running
brew install --cask flipper

# Ensure react-native-keychain plugin is added to Flipper
# In Flipper: Manage Plugins → Install "react-native-keychain"
```

**Issue:** Tokens found in AsyncStorage (security vulnerability!)

**Solution:**
- This is a CRITICAL security issue
- Verify you're using `secureStorage` not `AsyncStorage` directly
- Check imports in all files:
  ```typescript
  // CORRECT
  import { getItem, setItem } from '../utils/secureStorage';

  // WRONG - DO NOT USE
  import AsyncStorage from '@react-native-async-storage/async-storage';
  ```

**Issue:** Login succeeds but no token in secure storage

**Solution:**
- Check that `setAuthTokens()` is called after successful login
- Verify secure storage is available: `await isSecureStorageAvailable()`
- Check device logs for storage errors
- On iOS: Check for Keychain access entitlements
- On Android: Check for Keystore initialization

**Issue:** Token refresh creates infinite loop

**Solution:**
- Check that `isRefreshing` flag prevents concurrent refresh
- Verify refresh endpoint doesn't return 401
- Check that failed refresh clears tokens (preventing retry loop)

## Security Considerations

### Why Secure Storage?

**AsyncStorage Vulnerabilities:**
- ❌ Plain text storage (no encryption)
- ❌ Accessible by other apps with root/jailbreak
- ❌ Visible in device backups (unless explicitly excluded)
- ❌ Readable with file system access
- ❌ No biometric protection

**Secure Storage Benefits:**
- ✅ Hardware-backed encryption (iOS Keychain, Android Keystore)
- ✅ Isolated from other apps (even with root access)
- ✅ Excluded from backups by default
- ✅ Supports biometric authentication
- ✅ OS-level security guarantees

### JWT Token Security

**Best Practices (Implemented):**
- ✅ Tokens stored in secure storage, never AsyncStorage
- ✅ Tokens never logged or exposed in error messages
- ✅ Automatic token refresh prevents long-lived tokens
- ✅ Failed refresh clears tokens (security fallback)
- ✅ Logout clears all tokens immediately
- ✅ Bearer token format in Authorization header
- ✅ Tokens sent over HTTPS only

**Anti-Patterns (Avoided):**
- ❌ Storing tokens in AsyncStorage
- ❌ Logging tokens to console
- ❌ Embedding tokens in URLs or query parameters
- ❌ Storing tokens in Redux state without encryption
- ❌ Long-lived tokens without refresh
- ❌ Accepting tokens over HTTP

### OWASP Mobile Security

This implementation follows OWASP Mobile Security Project guidelines:

- **M1: Improper Platform Usage** - Uses platform-specific secure storage (Keychain/Keystore)
- **M2: Insecure Data Storage** - Tokens never in plain text or AsyncStorage
- **M9: Reverse Engineering** - Tokens encrypted at rest, not in code
- **M3: Insecure Communication** - HTTPS only (enforced by API client)

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Mobile Auth Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: actions/setup-node@v3
        with:
          node-version: '18'
      - name: Install dependencies
        run: |
          cd parent-app
          npm ci
      - name: Run mobile auth tests
        run: npm test tests/e2e/mobile-auth-flow.test.ts
```

### Pre-commit Hook

```bash
#!/bin/bash
# .git/hooks/pre-commit

echo "Running mobile authentication tests..."
npm test tests/e2e/mobile-auth-flow.test.ts --passWithNoTests

if [ $? -ne 0 ]; then
    echo "Mobile auth tests failed. Commit aborted."
    exit 1
fi
```

## Additional Resources

### Documentation
- [React Native Security Best Practices](https://reactnative.dev/docs/security)
- [OWASP Mobile Security Project](https://owasp.org/www-project-mobile-security/)
- [react-native-keychain Documentation](https://github.com/oblador/react-native-keychain)
- [iOS Keychain Services](https://developer.apple.com/documentation/security/keychain_services)
- [Android Keystore System](https://developer.android.com/training/articles/keystore)

### Related Files
- `parent-app/src/api/secureClient.ts` - Secure API client implementation
- `parent-app/src/utils/secureStorage.ts` - Secure storage wrapper
- `parent-app/src/api/config.ts` - API endpoint configuration
- `parent-app/src/types/index.ts` - TypeScript type definitions

### Testing Resources
- [Jest Documentation](https://jestjs.io/docs/getting-started)
- [React Native Testing Library](https://callstack.github.io/react-native-testing-library/)
- [Flipper Debugger](https://fbflipper.com/)

## Support

For issues or questions about mobile authentication testing:

1. Review this documentation
2. Check the troubleshooting section
3. Review test output and error messages
4. Check related implementation files
5. Consult OWASP Mobile Security guidelines

---

**Last Updated:** 2026-02-18
**Version:** 1.0.0
**Status:** ✅ All tests passing
