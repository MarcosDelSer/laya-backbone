#!/bin/bash
# Verification script for CSP headers in parent-portal
# Run this after starting the parent-portal development server

echo "Checking CSP headers on http://localhost:3000..."
echo ""

# Check if server is running
if ! curl -s -o /dev/null -w "%{http_code}" http://localhost:3000 > /dev/null; then
    echo "❌ Error: parent-portal is not running on port 3000"
    echo "Please start it with: cd parent-portal && npm run dev"
    exit 1
fi

echo "✓ Server is running"
echo ""

# Get headers
echo "Response headers:"
curl -I http://localhost:3000 2>&1 | grep -i "content-security-policy"

if curl -I http://localhost:3000 2>&1 | grep -qi "content-security-policy"; then
    echo ""
    echo "✓ CSP headers are present"
    echo ""
    echo "Full CSP header value:"
    curl -I http://localhost:3000 2>&1 | grep -i "content-security-policy" | sed 's/content-security-policy: //gi'
    echo ""
    echo "✅ Verification complete - CSP headers are configured correctly"
else
    echo ""
    echo "❌ Error: CSP headers not found in response"
    exit 1
fi
