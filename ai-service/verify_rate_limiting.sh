#!/bin/bash
# Rate Limiting Verification Script
# Tests rate limiting behavior via API calls

set -e

BASE_URL="${BASE_URL:-http://localhost:8000}"
ENDPOINT="$BASE_URL/"

echo "========================================="
echo "Rate Limiting API Verification"
echo "========================================="
echo "Base URL: $BASE_URL"
echo ""

# Test 1: Basic endpoint accessibility
echo "Test 1: Basic Endpoint Accessibility"
echo "-------------------------------------"
echo "Making request to $ENDPOINT"

RESPONSE=$(curl -s -w "\n%{http_code}" "$ENDPOINT" -H "Content-Type: application/json")
HTTP_CODE=$(echo "$RESPONSE" | tail -n 1)
BODY=$(echo "$RESPONSE" | head -n -1)

if [ "$HTTP_CODE" = "200" ]; then
    echo "✓ Status: $HTTP_CODE"
    echo "✓ Response: $BODY"
else
    echo "✗ Status: $HTTP_CODE (expected 200)"
    echo "Response: $BODY"
    exit 1
fi

echo ""

# Test 2: Security Headers
echo "Test 2: Security Headers Verification"
echo "-------------------------------------"
HEADERS=$(curl -s -I "$ENDPOINT")

echo "Checking security headers:"

# Check X-Frame-Options
if echo "$HEADERS" | grep -i "X-Frame-Options" > /dev/null; then
    HEADER_VALUE=$(echo "$HEADERS" | grep -i "X-Frame-Options" | cut -d: -f2- | tr -d '\r\n' | xargs)
    echo "✓ X-Frame-Options: $HEADER_VALUE"
else
    echo "✗ X-Frame-Options: MISSING"
fi

# Check X-Content-Type-Options
if echo "$HEADERS" | grep -i "X-Content-Type-Options" > /dev/null; then
    HEADER_VALUE=$(echo "$HEADERS" | grep -i "X-Content-Type-Options" | cut -d: -f2- | tr -d '\r\n' | xargs)
    echo "✓ X-Content-Type-Options: $HEADER_VALUE"
else
    echo "✗ X-Content-Type-Options: MISSING"
fi

# Check X-XSS-Protection
if echo "$HEADERS" | grep -i "X-XSS-Protection" > /dev/null; then
    HEADER_VALUE=$(echo "$HEADERS" | grep -i "X-XSS-Protection" | cut -d: -f2- | tr -d '\r\n' | xargs)
    echo "✓ X-XSS-Protection: $HEADER_VALUE"
else
    echo "ℹ X-XSS-Protection: Not set"
fi

# Check Content-Security-Policy
if echo "$HEADERS" | grep -i "Content-Security-Policy" > /dev/null; then
    HEADER_VALUE=$(echo "$HEADERS" | grep -i "Content-Security-Policy" | cut -d: -f2- | tr -d '\r\n' | xargs)
    echo "✓ Content-Security-Policy: ${HEADER_VALUE:0:60}..."
else
    echo "ℹ Content-Security-Policy: Not set"
fi

echo ""

# Test 3: Rate Limiting (Sample)
echo "Test 3: Rate Limiting Behavior (Sample)"
echo "-------------------------------------"
echo "Making 5 rapid requests to test rate limiting..."

SUCCESS_COUNT=0
for i in {1..5}; do
    HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "$ENDPOINT")
    if [ "$HTTP_CODE" = "200" ]; then
        echo "  Request $i: ✓ Status $HTTP_CODE"
        SUCCESS_COUNT=$((SUCCESS_COUNT + 1))
    elif [ "$HTTP_CODE" = "429" ]; then
        echo "  Request $i: ⚠ Status $HTTP_CODE (rate limited)"
    else
        echo "  Request $i: ✗ Status $HTTP_CODE"
    fi
    sleep 0.1
done

echo ""
echo "Results: $SUCCESS_COUNT/5 requests succeeded"

if [ $SUCCESS_COUNT -ge 5 ]; then
    echo "✓ All requests succeeded (under rate limit of 100/min)"
else
    echo "ℹ Some requests were rate limited"
fi

echo ""
echo "========================================="
echo "Verification Complete"
echo "========================================="
echo ""
echo "Note: For comprehensive rate limiting testing including"
echo "429 status verification, run: python test_rate_limiting_api.py"
echo ""
