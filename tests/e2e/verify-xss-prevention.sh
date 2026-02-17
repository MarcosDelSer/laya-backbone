#!/bin/bash

# Verification Script: XSS Prevention
#
# This script verifies that XSS prevention measures are working correctly
# by running automated Playwright E2E tests and manual curl-based tests.
#
# Requirements:
# - parent-portal running at http://localhost:3000
# - Node.js and npm installed
# - Playwright installed (npx playwright install)
#
# Usage:
#   ./tests/e2e/verify-xss-prevention.sh

set -e

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
PARENT_PORTAL_URL="${PARENT_PORTAL_URL:-http://localhost:3000}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo -e "${BLUE}========================================${NC}"
echo -e "${BLUE}XSS Prevention Verification${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""

# Step 1: Check prerequisites
echo -e "${YELLOW}Step 1: Checking prerequisites...${NC}"

# Check if parent-portal is running
if ! curl -s -o /dev/null -w "%{http_code}" "$PARENT_PORTAL_URL" | grep -qE "^(200|301|302|304)"; then
  echo -e "${RED}✗ parent-portal is not running at $PARENT_PORTAL_URL${NC}"
  echo -e "${YELLOW}  Please start parent-portal:${NC}"
  echo -e "${YELLOW}    cd parent-portal && npm run dev${NC}"
  exit 1
fi
echo -e "${GREEN}✓ parent-portal is running at $PARENT_PORTAL_URL${NC}"

# Check if Playwright is installed
if ! npx playwright --version &> /dev/null; then
  echo -e "${YELLOW}⚠ Playwright is not installed${NC}"
  echo -e "${YELLOW}  Installing Playwright...${NC}"
  npx playwright install
fi
echo -e "${GREEN}✓ Playwright is installed${NC}"

echo ""

# Step 2: Run XSS prevention E2E tests
echo -e "${YELLOW}Step 2: Running XSS prevention E2E tests...${NC}"
echo ""

# Run Playwright tests
if npx playwright test tests/e2e/xss-prevention.spec.js; then
  echo ""
  echo -e "${GREEN}✓ All XSS prevention E2E tests passed${NC}"
else
  echo ""
  echo -e "${RED}✗ Some XSS prevention E2E tests failed${NC}"
  echo -e "${YELLOW}  Check the test output above for details${NC}"
  exit 1
fi

echo ""

# Step 3: Manual verification steps
echo -e "${YELLOW}Step 3: Manual verification guide${NC}"
echo ""
echo -e "${BLUE}To manually verify XSS prevention in the browser:${NC}"
echo ""
echo -e "  1. Open parent-portal: ${PARENT_PORTAL_URL}"
echo -e "  2. Navigate to a form that accepts user input (e.g., profile, messages)"
echo -e "  3. Try submitting XSS payloads:"
echo -e "     - ${YELLOW}<script>alert('xss')</script>${NC}"
echo -e "     - ${YELLOW}<img src=x onerror=alert('xss')>${NC}"
echo -e "     - ${YELLOW}<a href='javascript:alert(\"xss\")'>Click</a>${NC}"
echo -e "  4. Verify that:"
echo -e "     - Script tags are removed from rendered content"
echo -e "     - No JavaScript alert boxes appear"
echo -e "     - Event handlers (onerror, onclick, etc.) are stripped"
echo -e "     - Safe HTML tags (p, strong, em, etc.) are preserved"
echo ""

# Step 4: Check sanitization utilities
echo -e "${YELLOW}Step 4: Checking sanitization utilities...${NC}"

# Check if sanitize.ts exists
if [ -f "parent-portal/lib/security/sanitize.ts" ]; then
  echo -e "${GREEN}✓ Sanitization utilities found at parent-portal/lib/security/sanitize.ts${NC}"
else
  echo -e "${RED}✗ Sanitization utilities not found${NC}"
  echo -e "${YELLOW}  Expected file: parent-portal/lib/security/sanitize.ts${NC}"
  exit 1
fi

# Check if unit tests exist
if [ -f "parent-portal/lib/security/__tests__/sanitize.test.ts" ]; then
  echo -e "${GREEN}✓ Sanitization unit tests found${NC}"
else
  echo -e "${YELLOW}⚠ Sanitization unit tests not found (recommended)${NC}"
fi

echo ""

# Step 5: Summary
echo -e "${BLUE}========================================${NC}"
echo -e "${GREEN}✅ XSS Prevention Verification Complete${NC}"
echo -e "${BLUE}========================================${NC}"
echo ""
echo -e "Summary:"
echo -e "  ✓ parent-portal is running"
echo -e "  ✓ Playwright E2E tests passed"
echo -e "  ✓ Sanitization utilities are in place"
echo ""
echo -e "${YELLOW}Next steps:${NC}"
echo -e "  1. Review test results in terminal output"
echo -e "  2. Perform manual browser testing (see guide above)"
echo -e "  3. Check for CSP violations in browser console"
echo -e "  4. Verify XSS sanitization in production builds"
echo ""
echo -e "${BLUE}For more information:${NC}"
echo -e "  - Test file: tests/e2e/xss-prevention.spec.js"
echo -e "  - Documentation: tests/e2e/README-xss-test.md"
echo -e "  - Sanitization utilities: parent-portal/lib/security/sanitize.ts"
echo ""
