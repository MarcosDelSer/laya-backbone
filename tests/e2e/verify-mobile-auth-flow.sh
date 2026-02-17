#!/bin/bash

###############################################################################
# Mobile Authentication Flow Verification Script
#
# This script verifies the end-to-end mobile authentication flow for parent-app.
# It checks prerequisites, runs automated tests, and provides manual verification
# instructions for device-specific secure storage.
#
# Usage:
#   ./tests/e2e/verify-mobile-auth-flow.sh
#
# Prerequisites:
#   - Node.js and npm installed
#   - parent-app dependencies installed (npm install in parent-app/)
#   - Jest configured for testing
#   - (Optional) React Native development environment for manual testing
###############################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
PARENT_APP_DIR="$PROJECT_ROOT/parent-app"

echo ""
echo "=========================================="
echo "Mobile Authentication Flow Verification"
echo "=========================================="
echo ""

###############################################################################
# Step 1: Check Prerequisites
###############################################################################

echo -e "${BLUE}Step 1: Checking prerequisites...${NC}"
echo ""

# Check if Node.js is installed
if ! command -v node &> /dev/null; then
    echo -e "${RED}✗ Node.js is not installed${NC}"
    echo "  Please install Node.js from https://nodejs.org/"
    exit 1
fi
echo -e "${GREEN}✓ Node.js installed:${NC} $(node --version)"

# Check if npm is installed
if ! command -v npm &> /dev/null; then
    echo -e "${RED}✗ npm is not installed${NC}"
    echo "  Please install npm"
    exit 1
fi
echo -e "${GREEN}✓ npm installed:${NC} $(npm --version)"

# Check if parent-app directory exists
if [ ! -d "$PARENT_APP_DIR" ]; then
    echo -e "${RED}✗ parent-app directory not found${NC}"
    echo "  Expected at: $PARENT_APP_DIR"
    exit 1
fi
echo -e "${GREEN}✓ parent-app directory found${NC}"

# Check if parent-app has node_modules
if [ ! -d "$PARENT_APP_DIR/node_modules" ]; then
    echo -e "${YELLOW}⚠ parent-app dependencies not installed${NC}"
    echo "  Installing dependencies..."
    cd "$PARENT_APP_DIR"
    npm install
    cd "$PROJECT_ROOT"
fi
echo -e "${GREEN}✓ parent-app dependencies installed${NC}"

# Check if secureClient.ts exists
if [ ! -f "$PARENT_APP_DIR/src/api/secureClient.ts" ]; then
    echo -e "${RED}✗ secureClient.ts not found${NC}"
    echo "  Expected at: $PARENT_APP_DIR/src/api/secureClient.ts"
    exit 1
fi
echo -e "${GREEN}✓ secureClient.ts found${NC}"

# Check if secureStorage.ts exists
if [ ! -f "$PARENT_APP_DIR/src/utils/secureStorage.ts" ]; then
    echo -e "${RED}✗ secureStorage.ts not found${NC}"
    echo "  Expected at: $PARENT_APP_DIR/src/utils/secureStorage.ts"
    exit 1
fi
echo -e "${GREEN}✓ secureStorage.ts found${NC}"

echo ""
echo -e "${GREEN}✓ All prerequisites met${NC}"
echo ""

###############################################################################
# Step 2: Run Automated Tests
###############################################################################

echo -e "${BLUE}Step 2: Running automated E2E tests...${NC}"
echo ""

cd "$PROJECT_ROOT"

# Run Jest tests for mobile auth flow
if npm test tests/e2e/mobile-auth-flow.test.ts; then
    echo ""
    echo -e "${GREEN}✓ All automated tests passed${NC}"
else
    echo ""
    echo -e "${RED}✗ Some tests failed${NC}"
    echo "  Please review the test output above"
    exit 1
fi

echo ""

###############################################################################
# Step 3: Manual Verification Instructions
###############################################################################

echo -e "${BLUE}Step 3: Manual verification instructions${NC}"
echo ""
echo "The automated tests verify the API contract and secure storage integration."
echo "For complete verification on a real device, perform these manual checks:"
echo ""

echo -e "${YELLOW}=== iOS Device Verification ===${NC}"
echo ""
echo "1. Build and run parent-app on iOS device/simulator:"
echo "   cd parent-app"
echo "   npx react-native run-ios"
echo ""
echo "2. Login to the app with valid credentials"
echo ""
echo "3. Verify token stored in iOS Keychain (not UserDefaults):"
echo "   - Open Xcode"
echo "   - Window > Devices and Simulators"
echo "   - Select your device/simulator"
echo "   - View container data"
echo "   - Check: Tokens should be in Keychain, NOT in UserDefaults"
echo ""
echo "4. Use Flipper Network inspector:"
echo "   - View API requests"
echo "   - Verify Authorization: Bearer <token> header on authenticated requests"
echo "   - Verify no token in request body or URL parameters"
echo ""
echo "5. Logout and verify:"
echo "   - Tokens removed from Keychain"
echo "   - Subsequent requests have no Authorization header"
echo ""

echo -e "${YELLOW}=== Android Device Verification ===${NC}"
echo ""
echo "1. Build and run parent-app on Android device/emulator:"
echo "   cd parent-app"
echo "   npx react-native run-android"
echo ""
echo "2. Login to the app with valid credentials"
echo ""
echo "3. Verify token stored in Android Keystore (not SharedPreferences):"
echo "   - Use Android Studio Device File Explorer"
echo "   - Navigate to app's data directory"
echo "   - Check: Tokens should be in secure storage, NOT in SharedPreferences XML"
echo ""
echo "4. Use Flipper Network inspector:"
echo "   - View API requests"
echo "   - Verify Authorization: Bearer <token> header on authenticated requests"
echo ""
echo "5. Logout and verify:"
echo "   - Tokens removed from Keystore"
echo "   - Subsequent requests have no Authorization header"
echo ""

echo -e "${YELLOW}=== Security Checks ===${NC}"
echo ""
echo "1. Token not in AsyncStorage:"
echo "   - Use React Native Debugger or Flipper"
echo "   - Check AsyncStorage contents"
echo "   - Verify NO tokens stored in AsyncStorage"
echo ""
echo "2. Token not in logs:"
echo "   - Review Metro bundler logs"
echo "   - Verify tokens are NOT printed in console"
echo "   - Verify tokens are NOT in error messages"
echo ""
echo "3. Token refresh on 401:"
echo "   - Modify access token to invalid value"
echo "   - Make authenticated request"
echo "   - Verify app automatically refreshes token"
echo "   - Verify request retries with new token"
echo ""
echo "4. Token cleared on logout:"
echo "   - Logout from app"
echo "   - Try to make authenticated request"
echo "   - Verify request fails with auth error"
echo "   - Verify no token in secure storage"
echo ""

###############################################################################
# Step 4: Summary
###############################################################################

echo ""
echo "=========================================="
echo -e "${GREEN}Verification Complete${NC}"
echo "=========================================="
echo ""
echo "Automated tests: PASSED"
echo ""
echo "Next steps:"
echo "1. Review manual verification instructions above"
echo "2. Test on real iOS device with Xcode Keychain verification"
echo "3. Test on real Android device with Android Studio verification"
echo "4. Use Flipper to inspect network requests and secure storage"
echo ""
echo "For detailed documentation, see:"
echo "  tests/e2e/README-mobile-auth-test.md"
echo ""
