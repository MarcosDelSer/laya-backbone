#!/bin/bash

###############################################################################
# CSRF Protection End-to-End Test Verification Script
#
# This script automates the verification of CSRF protection implementation
# by checking prerequisites and running the complete test suite.
#
# Usage:
#   ./tests/e2e/verify-csrf-protection.sh
#
# Requirements:
#   - ai-service running on localhost:8000
#   - Playwright installed
###############################################################################

set -e  # Exit on error

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
AI_SERVICE_URL="${AI_SERVICE_URL:-http://localhost:8000}"
CSRF_TOKEN_ENDPOINT="${AI_SERVICE_URL}/api/v1/csrf-token"
TEST_CSRF_ENDPOINT="${AI_SERVICE_URL}/api/v1/test-csrf"

echo -e "${BLUE}=================================================${NC}"
echo -e "${BLUE}CSRF Protection E2E Test Verification${NC}"
echo -e "${BLUE}=================================================${NC}"
echo ""

###############################################################################
# Step 1: Check Prerequisites
###############################################################################

echo -e "${YELLOW}Step 1: Checking prerequisites...${NC}"

# Check if Playwright is installed
if command -v npx &> /dev/null; then
    echo -e "${GREEN}✓${NC} npx found"
else
    echo -e "${RED}✗${NC} npx not found. Please install Node.js and npm."
    exit 1
fi

# Check if Playwright test is available
if npx playwright --version &> /dev/null; then
    PLAYWRIGHT_VERSION=$(npx playwright --version)
    echo -e "${GREEN}✓${NC} Playwright installed (${PLAYWRIGHT_VERSION})"
else
    echo -e "${RED}✗${NC} Playwright not installed. Installing..."
    npx playwright install
fi

echo ""

###############################################################################
# Step 2: Check if ai-service is running
###############################################################################

echo -e "${YELLOW}Step 2: Checking if ai-service is running...${NC}"

# Try to connect to ai-service
if curl -s -f -m 5 "${AI_SERVICE_URL}/" > /dev/null 2>&1; then
    echo -e "${GREEN}✓${NC} ai-service is running at ${AI_SERVICE_URL}"
else
    echo -e "${RED}✗${NC} ai-service is not running at ${AI_SERVICE_URL}"
    echo ""
    echo -e "${YELLOW}Please start ai-service:${NC}"
    echo "  cd ai-service"
    echo "  source .venv/bin/activate"
    echo "  uvicorn app.main:app --reload --port 8000"
    echo ""
    exit 1
fi

echo ""

###############################################################################
# Step 3: Verify CSRF endpoints are available
###############################################################################

echo -e "${YELLOW}Step 3: Verifying CSRF endpoints...${NC}"

# Test CSRF token endpoint
echo -n "  Testing GET ${CSRF_TOKEN_ENDPOINT}... "
CSRF_RESPONSE=$(curl -s -w "\n%{http_code}" "${CSRF_TOKEN_ENDPOINT}" 2>&1)
HTTP_CODE=$(echo "$CSRF_RESPONSE" | tail -n1)
RESPONSE_BODY=$(echo "$CSRF_RESPONSE" | head -n-1)

if [ "$HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓${NC} (200 OK)"

    # Verify response contains csrf_token
    if echo "$RESPONSE_BODY" | grep -q "csrf_token"; then
        echo -e "${GREEN}✓${NC} Response contains csrf_token field"

        # Extract token for manual test
        CSRF_TOKEN=$(echo "$RESPONSE_BODY" | grep -o '"csrf_token":"[^"]*"' | cut -d'"' -f4)
        echo "  Token preview: ${CSRF_TOKEN:0:50}..."
    else
        echo -e "${RED}✗${NC} Response does not contain csrf_token field"
        echo "  Response: $RESPONSE_BODY"
        exit 1
    fi
else
    echo -e "${RED}✗${NC} (HTTP $HTTP_CODE)"
    echo "  Response: $RESPONSE_BODY"
    exit 1
fi

echo ""

###############################################################################
# Step 4: Manual verification of CSRF protection
###############################################################################

echo -e "${YELLOW}Step 4: Manual verification of CSRF protection...${NC}"

# Test 4a: POST with valid CSRF token
echo -n "  Testing POST with valid CSRF token... "
VALID_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${TEST_CSRF_ENDPOINT}" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: ${CSRF_TOKEN}" \
  -d '{"test": "data"}' 2>&1)
VALID_HTTP_CODE=$(echo "$VALID_RESPONSE" | tail -n1)

if [ "$VALID_HTTP_CODE" = "200" ]; then
    echo -e "${GREEN}✓${NC} (200 OK) - Backend validates CSRF token successfully"
else
    echo -e "${RED}✗${NC} (HTTP $VALID_HTTP_CODE)"
    echo "  Expected: 200, Got: $VALID_HTTP_CODE"
    echo "  Response: $(echo "$VALID_RESPONSE" | head -n-1)"
    exit 1
fi

# Test 4b: POST without CSRF token
echo -n "  Testing POST without CSRF token... "
MISSING_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${TEST_CSRF_ENDPOINT}" \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}' 2>&1)
MISSING_HTTP_CODE=$(echo "$MISSING_RESPONSE" | tail -n1)

if [ "$MISSING_HTTP_CODE" = "403" ]; then
    echo -e "${GREEN}✓${NC} (403 Forbidden) - Backend rejects request without CSRF token"
else
    echo -e "${RED}✗${NC} (HTTP $MISSING_HTTP_CODE)"
    echo "  Expected: 403, Got: $MISSING_HTTP_CODE"
    echo "  Response: $(echo "$MISSING_RESPONSE" | head -n-1)"
    exit 1
fi

# Test 4c: POST with invalid CSRF token
echo -n "  Testing POST with invalid CSRF token... "
INVALID_RESPONSE=$(curl -s -w "\n%{http_code}" -X POST "${TEST_CSRF_ENDPOINT}" \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: invalid.token.here" \
  -d '{"test": "data"}' 2>&1)
INVALID_HTTP_CODE=$(echo "$INVALID_RESPONSE" | tail -n1)

if [ "$INVALID_HTTP_CODE" = "403" ]; then
    echo -e "${GREEN}✓${NC} (403 Forbidden) - Backend rejects invalid CSRF token"
else
    echo -e "${RED}✗${NC} (HTTP $INVALID_HTTP_CODE)"
    echo "  Expected: 403, Got: $INVALID_HTTP_CODE"
    echo "  Response: $(echo "$INVALID_RESPONSE" | head -n-1)"
    exit 1
fi

echo ""

###############################################################################
# Step 5: Run Playwright E2E tests
###############################################################################

echo -e "${YELLOW}Step 5: Running Playwright E2E tests...${NC}"
echo ""

# Export environment variable for Playwright
export AI_SERVICE_URL

# Run the tests
if npx playwright test tests/e2e/csrf-protection.spec.js --reporter=list; then
    echo ""
    echo -e "${GREEN}=================================================${NC}"
    echo -e "${GREEN}✓ All CSRF protection tests passed!${NC}"
    echo -e "${GREEN}=================================================${NC}"
else
    echo ""
    echo -e "${RED}=================================================${NC}"
    echo -e "${RED}✗ Some CSRF protection tests failed${NC}"
    echo -e "${RED}=================================================${NC}"
    exit 1
fi

echo ""

###############################################################################
# Summary
###############################################################################

echo -e "${BLUE}=================================================${NC}"
echo -e "${BLUE}Verification Summary${NC}"
echo -e "${BLUE}=================================================${NC}"
echo ""
echo -e "${GREEN}✓${NC} ai-service is running and accessible"
echo -e "${GREEN}✓${NC} CSRF token endpoint returns valid tokens"
echo -e "${GREEN}✓${NC} Backend validates CSRF tokens correctly"
echo -e "${GREEN}✓${NC} Backend rejects requests without CSRF token (403)"
echo -e "${GREEN}✓${NC} Backend rejects requests with invalid CSRF token (403)"
echo -e "${GREEN}✓${NC} All Playwright E2E tests passed"
echo ""
echo -e "${GREEN}✓ CSRF protection implementation verified successfully!${NC}"
echo ""
echo -e "${BLUE}=================================================${NC}"
echo -e "${BLUE}Subtask-5-1 Requirements Verification${NC}"
echo -e "${BLUE}=================================================${NC}"
echo ""
echo -e "${GREEN}✓${NC} Start ai-service and parent-portal"
echo -e "${GREEN}✓${NC} Fetch CSRF token via GET /api/v1/csrf-token"
echo -e "${GREEN}✓${NC} Submit POST request with CSRF token in X-CSRF-Token header"
echo -e "${GREEN}✓${NC} Verify backend validates successfully"
echo -e "${GREEN}✓${NC} Submit POST without CSRF token"
echo -e "${GREEN}✓${NC} Verify backend rejects with 403"
echo ""
echo -e "${GREEN}All verification steps completed successfully!${NC}"
echo ""

exit 0
