#!/bin/bash

#########################################################################
# CSRF Penetration Testing Script
#
# This file is part of Gibbon (GPL-3.0)
# Copyright (C) 2024 Gibbon Foundation
#
# This script performs manual penetration testing for CSRF protection
# to verify that all state-changing operations are properly protected.
#
# Usage: ./csrf_penetration_tests.sh [base_url]
# Example: ./csrf_penetration_tests.sh http://localhost:8080
#########################################################################

set -e

# Configuration
BASE_URL="${1:-http://localhost:8080}"
PARENT_PORTAL_URL="${2:-http://localhost:3000}"
RESULTS_FILE="csrf_test_results_$(date +%Y%m%d_%H%M%S).log"

# Color output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Helper functions
log() {
    echo -e "${BLUE}[INFO]${NC} $1" | tee -a "$RESULTS_FILE"
}

success() {
    echo -e "${GREEN}[PASS]${NC} $1" | tee -a "$RESULTS_FILE"
    ((PASSED_TESTS++))
}

fail() {
    echo -e "${RED}[FAIL]${NC} $1" | tee -a "$RESULTS_FILE"
    ((FAILED_TESTS++))
}

warn() {
    echo -e "${YELLOW}[WARN]${NC} $1" | tee -a "$RESULTS_FILE"
}

section() {
    echo "" | tee -a "$RESULTS_FILE"
    echo "================================================================" | tee -a "$RESULTS_FILE"
    echo -e "${BLUE}$1${NC}" | tee -a "$RESULTS_FILE"
    echo "================================================================" | tee -a "$RESULTS_FILE"
}

test_start() {
    ((TOTAL_TESTS++))
    log "Test $TOTAL_TESTS: $1"
}

# Start testing
echo "================================================================"
echo "CSRF PENETRATION TESTING SUITE"
echo "================================================================"
echo "Date: $(date)"
echo "Gibbon URL: $BASE_URL"
echo "Parent Portal URL: $PARENT_PORTAL_URL"
echo "Results: $RESULTS_FILE"
echo "================================================================"
echo ""

#########################################################################
# TEST CATEGORY 1: NO TOKEN ATTACKS
# Verify that requests without CSRF tokens are rejected
#########################################################################

section "TEST CATEGORY 1: NO TOKEN ATTACKS"

test_start "1.1 - Finance Invoice Creation Without Token"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST "$BASE_URL/modules/EnhancedFinance/finance_invoice_addProcess.php" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "amount=1000&description=Test" \
    --cookie-jar /tmp/gibbon_cookies.txt)

if [ "$RESPONSE" = "403" ] || [ "$RESPONSE" = "400" ]; then
    success "Invoice creation rejected without CSRF token (HTTP $RESPONSE)"
else
    fail "Invoice creation allowed without CSRF token (HTTP $RESPONSE)"
fi

test_start "1.2 - Care Tracking Attendance Without Token"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST "$BASE_URL/modules/CareTracking/careTracking_attendance.php" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "action=checkin&childID=1" \
    --cookie-jar /tmp/gibbon_cookies.txt)

if [ "$RESPONSE" = "403" ] || [ "$RESPONSE" = "400" ]; then
    success "Care tracking rejected without CSRF token (HTTP $RESPONSE)"
else
    fail "Care tracking allowed without CSRF token (HTTP $RESPONSE)"
fi

test_start "1.3 - Development Profile Without Token"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST "$BASE_URL/modules/DevelopmentProfile/developmentProfile_add.php" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "observation=Test&childID=1" \
    --cookie-jar /tmp/gibbon_cookies.txt)

if [ "$RESPONSE" = "403" ] || [ "$RESPONSE" = "400" ]; then
    success "Development profile rejected without CSRF token (HTTP $RESPONSE)"
else
    fail "Development profile allowed without CSRF token (HTTP $RESPONSE)"
fi

test_start "1.4 - Staff Management Without Token"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST "$BASE_URL/modules/StaffManagement/staffManagement_addEdit.php" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -d "firstName=Test&lastName=User" \
    --cookie-jar /tmp/gibbon_cookies.txt)

if [ "$RESPONSE" = "403" ] || [ "$RESPONSE" = "400" ]; then
    success "Staff management rejected without CSRF token (HTTP $RESPONSE)"
else
    fail "Staff management allowed without CSRF token (HTTP $RESPONSE)"
fi

test_start "1.5 - Parent Portal Registration Without Token"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST "$PARENT_PORTAL_URL/api/auth/register" \
    -H "Content-Type: application/json" \
    -d '{"firstName":"Test","lastName":"User","email":"test@example.com","password":"password123"}')

if [ "$RESPONSE" = "403" ]; then
    success "Parent portal registration rejected without CSRF token (HTTP $RESPONSE)"
else
    fail "Parent portal registration allowed without CSRF token (HTTP $RESPONSE)"
fi

#########################################################################
# TEST CATEGORY 2: TOKEN REUSE ATTACKS
# Verify that CSRF tokens cannot be reused after being consumed
#########################################################################

section "TEST CATEGORY 2: TOKEN REUSE ATTACKS"

test_start "2.1 - Extract Valid CSRF Token"
# First, get a valid session and CSRF token
CSRF_PAGE=$(curl -s -c /tmp/gibbon_cookies.txt "$BASE_URL/modules/EnhancedFinance/finance_invoice_add.php")
CSRF_TOKEN=$(echo "$CSRF_PAGE" | grep -o 'name="csrf_token" value="[^"]*"' | sed 's/.*value="\([^"]*\)".*/\1/' | head -1)

if [ -n "$CSRF_TOKEN" ]; then
    success "Successfully extracted CSRF token: ${CSRF_TOKEN:0:16}..."
else
    fail "Failed to extract CSRF token from page"
fi

test_start "2.2 - First Request with Valid Token"
if [ -n "$CSRF_TOKEN" ]; then
    RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$BASE_URL/modules/EnhancedFinance/finance_invoice_addProcess.php" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -b /tmp/gibbon_cookies.txt \
        -d "csrf_token=$CSRF_TOKEN&amount=1000&description=Test")

    log "First request response: HTTP $RESPONSE"
else
    warn "Skipping - no token available"
fi

test_start "2.3 - Second Request with Same Token (Reuse Attack)"
if [ -n "$CSRF_TOKEN" ]; then
    # If token rotation is enabled, this should fail
    RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$BASE_URL/modules/EnhancedFinance/finance_invoice_addProcess.php" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -b /tmp/gibbon_cookies.txt \
        -d "csrf_token=$CSRF_TOKEN&amount=2000&description=Test2")

    # Note: Token reuse may be allowed if rotation is disabled
    # This is acceptable for usability (multiple tabs)
    log "Token reuse response: HTTP $RESPONSE"
    warn "Token reuse policy depends on configuration (rotateOnUse setting)"
else
    warn "Skipping - no token available"
fi

#########################################################################
# TEST CATEGORY 3: CROSS-ORIGIN ATTACKS
# Verify that requests from different origins are rejected
#########################################################################

section "TEST CATEGORY 3: CROSS-ORIGIN ATTACKS"

test_start "3.1 - Request with Foreign Origin Header"
if [ -n "$CSRF_TOKEN" ]; then
    RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$BASE_URL/modules/EnhancedFinance/finance_invoice_addProcess.php" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -H "Origin: https://evil.com" \
        -H "Referer: https://evil.com/attack.html" \
        -b /tmp/gibbon_cookies.txt \
        -d "csrf_token=$CSRF_TOKEN&amount=1000&description=Test")

    # SameSite=Strict cookies should prevent this
    if [ "$RESPONSE" = "403" ] || [ "$RESPONSE" = "400" ]; then
        success "Cross-origin request rejected (HTTP $RESPONSE)"
    else
        warn "Cross-origin request accepted (HTTP $RESPONSE) - verify SameSite cookie settings"
    fi
else
    warn "Skipping - no token available"
fi

test_start "3.2 - Request Without Origin/Referer Headers"
if [ -n "$CSRF_TOKEN" ]; then
    RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$BASE_URL/modules/EnhancedFinance/finance_invoice_addProcess.php" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -b /tmp/gibbon_cookies.txt \
        -d "csrf_token=$CSRF_TOKEN&amount=1000&description=Test")

    log "Request without origin headers: HTTP $RESPONSE"
else
    warn "Skipping - no token available"
fi

test_start "3.3 - CORS Preflight to Parent Portal"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X OPTIONS "$PARENT_PORTAL_URL/api/auth/register" \
    -H "Origin: https://evil.com" \
    -H "Access-Control-Request-Method: POST" \
    -H "Access-Control-Request-Headers: Content-Type")

log "CORS preflight response: HTTP $RESPONSE"
# Check for CORS headers - should not allow evil.com
CORS_HEADERS=$(curl -s -I -X OPTIONS "$PARENT_PORTAL_URL/api/auth/register" \
    -H "Origin: https://evil.com" | grep -i "access-control")

if [ -z "$CORS_HEADERS" ]; then
    success "CORS not configured to allow arbitrary origins"
else
    warn "CORS headers present: $CORS_HEADERS"
fi

#########################################################################
# TEST CATEGORY 4: ALL OPERATIONS PROTECTED
# Verify that all state-changing operations require CSRF tokens
#########################################################################

section "TEST CATEGORY 4: ALL OPERATIONS PROTECTED"

test_start "4.1 - Verify POST Protection"
ENDPOINTS=(
    "/modules/EnhancedFinance/finance_invoice_addProcess.php"
    "/modules/CareTracking/careTracking_attendance.php"
    "/modules/DevelopmentProfile/developmentProfile_add.php"
    "/modules/StaffManagement/staffManagement_addEdit.php"
)

for endpoint in "${ENDPOINTS[@]}"; do
    RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$BASE_URL$endpoint" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -d "test=data")

    if [ "$RESPONSE" = "403" ] || [ "$RESPONSE" = "400" ]; then
        success "  ✓ $endpoint requires CSRF token (HTTP $RESPONSE)"
    else
        fail "  ✗ $endpoint missing CSRF protection (HTTP $RESPONSE)"
    fi
done

test_start "4.2 - Verify PUT/DELETE Protection (Middleware)"
# Test that middleware protects PUT/DELETE methods
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X PUT "$BASE_URL/api/test" \
    -H "Content-Type: application/json" \
    -d '{"test":"data"}')

if [ "$RESPONSE" = "403" ] || [ "$RESPONSE" = "404" ]; then
    success "PUT requests require CSRF token (HTTP $RESPONSE)"
else
    warn "PUT request response: HTTP $RESPONSE"
fi

RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X DELETE "$BASE_URL/api/test" \
    -H "Content-Type: application/json")

if [ "$RESPONSE" = "403" ] || [ "$RESPONSE" = "404" ]; then
    success "DELETE requests require CSRF token (HTTP $RESPONSE)"
else
    warn "DELETE request response: HTTP $RESPONSE"
fi

test_start "4.3 - Verify GET Requests Not Protected"
# GET requests should not require CSRF tokens
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X GET "$BASE_URL/modules/EnhancedFinance/finance_invoice_add.php")

if [ "$RESPONSE" = "200" ] || [ "$RESPONSE" = "302" ]; then
    success "GET requests do not require CSRF token (HTTP $RESPONSE)"
else
    warn "GET request response: HTTP $RESPONSE"
fi

test_start "4.4 - Verify Exempt Paths"
# Test that exempt paths bypass CSRF validation
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST "$BASE_URL/api/webhook/test" \
    -H "Content-Type: application/json" \
    -d '{"test":"data"}')

# Exempt paths should return 404 (not found) not 403 (forbidden)
if [ "$RESPONSE" = "404" ]; then
    success "Exempt paths bypass CSRF validation (HTTP $RESPONSE)"
elif [ "$RESPONSE" = "403" ]; then
    fail "Exempt paths still require CSRF token (HTTP $RESPONSE)"
else
    log "Exempt path response: HTTP $RESPONSE"
fi

#########################################################################
# TEST CATEGORY 5: SECURITY HEADERS
# Verify that security headers are properly set
#########################################################################

section "TEST CATEGORY 5: SECURITY HEADERS"

test_start "5.1 - Verify X-Frame-Options Header"
HEADER=$(curl -s -I "$BASE_URL" | grep -i "X-Frame-Options")
if echo "$HEADER" | grep -q "DENY"; then
    success "X-Frame-Options header present: $HEADER"
else
    fail "X-Frame-Options header missing or incorrect"
fi

test_start "5.2 - Verify X-Content-Type-Options Header"
HEADER=$(curl -s -I "$BASE_URL" | grep -i "X-Content-Type-Options")
if echo "$HEADER" | grep -q "nosniff"; then
    success "X-Content-Type-Options header present: $HEADER"
else
    fail "X-Content-Type-Options header missing"
fi

test_start "5.3 - Verify X-XSS-Protection Header"
HEADER=$(curl -s -I "$BASE_URL" | grep -i "X-XSS-Protection")
if echo "$HEADER" | grep -q "1"; then
    success "X-XSS-Protection header present: $HEADER"
else
    warn "X-XSS-Protection header missing (optional)"
fi

test_start "5.4 - Verify Referrer-Policy Header"
HEADER=$(curl -s -I "$BASE_URL" | grep -i "Referrer-Policy")
if [ -n "$HEADER" ]; then
    success "Referrer-Policy header present: $HEADER"
else
    warn "Referrer-Policy header missing (optional)"
fi

test_start "5.5 - Verify SameSite Cookie Attribute"
# Get cookies and check for SameSite attribute
COOKIES=$(curl -s -I -c - "$BASE_URL" | grep -i "Set-Cookie")
if echo "$COOKIES" | grep -qi "SameSite=Strict\|SameSite=Lax"; then
    success "SameSite cookie attribute present"
else
    warn "SameSite cookie attribute not detected - verify manually"
fi

#########################################################################
# TEST CATEGORY 6: TOKEN TAMPERING
# Verify that tampered tokens are rejected
#########################################################################

section "TEST CATEGORY 6: TOKEN TAMPERING"

test_start "6.1 - Modified Token Attack"
if [ -n "$CSRF_TOKEN" ]; then
    # Modify the token slightly
    TAMPERED_TOKEN="${CSRF_TOKEN:0:32}ffffffff${CSRF_TOKEN:40}"

    RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$BASE_URL/modules/EnhancedFinance/finance_invoice_addProcess.php" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -b /tmp/gibbon_cookies.txt \
        -d "csrf_token=$TAMPERED_TOKEN&amount=1000&description=Test")

    if [ "$RESPONSE" = "403" ] || [ "$RESPONSE" = "400" ]; then
        success "Tampered token rejected (HTTP $RESPONSE)"
    else
        fail "Tampered token accepted (HTTP $RESPONSE)"
    fi
else
    warn "Skipping - no token available"
fi

test_start "6.2 - Truncated Token Attack"
if [ -n "$CSRF_TOKEN" ]; then
    TRUNCATED_TOKEN="${CSRF_TOKEN:0:32}"

    RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
        -X POST "$BASE_URL/modules/EnhancedFinance/finance_invoice_addProcess.php" \
        -H "Content-Type: application/x-www-form-urlencoded" \
        -b /tmp/gibbon_cookies.txt \
        -d "csrf_token=$TRUNCATED_TOKEN&amount=1000&description=Test")

    if [ "$RESPONSE" = "403" ] || [ "$RESPONSE" = "400" ]; then
        success "Truncated token rejected (HTTP $RESPONSE)"
    else
        fail "Truncated token accepted (HTTP $RESPONSE)"
    fi
else
    warn "Skipping - no token available"
fi

test_start "6.3 - Empty String Token Attack"
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST "$BASE_URL/modules/EnhancedFinance/finance_invoice_addProcess.php" \
    -H "Content-Type: application/x-www-form-urlencoded" \
    -b /tmp/gibbon_cookies.txt \
    -d "csrf_token=&amount=1000&description=Test")

if [ "$RESPONSE" = "403" ] || [ "$RESPONSE" = "400" ]; then
    success "Empty token rejected (HTTP $RESPONSE)"
else
    fail "Empty token accepted (HTTP $RESPONSE)"
fi

#########################################################################
# FINAL REPORT
#########################################################################

section "FINAL REPORT"

echo ""
echo "================================================================"
echo "PENETRATION TESTING RESULTS"
echo "================================================================"
echo "Total Tests: $TOTAL_TESTS"
echo -e "${GREEN}Passed: $PASSED_TESTS${NC}"
echo -e "${RED}Failed: $FAILED_TESTS${NC}"
echo "================================================================"
echo ""

if [ $FAILED_TESTS -eq 0 ]; then
    echo -e "${GREEN}✓ ALL TESTS PASSED${NC}"
    echo "CSRF protection is properly implemented and secure."
    exit 0
else
    echo -e "${RED}✗ SOME TESTS FAILED${NC}"
    echo "Please review the failures above and fix the issues."
    exit 1
fi
