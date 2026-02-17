#!/bin/bash
#
# Manual IDOR Penetration Testing Script
#
# This script provides curl commands for manually testing IDOR vulnerabilities.
# Each command attempts to access User A's resources with User B's token.
#
# Expected Result: All commands should return 403 Forbidden (or 404 for storage endpoints)
#

set -e

API_URL="${API_URL:-http://localhost:8000}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo "================================================================================"
echo "IDOR MANUAL PENETRATION TESTING"
echo "================================================================================"
echo "API URL: $API_URL"
echo "Date: $(date)"
echo "================================================================================"
echo ""

# Check if service is running
echo "Checking if API service is running..."
if curl -s -o /dev/null -w "%{http_code}" "$API_URL/health" | grep -q "200\|404"; then
    echo -e "${GREEN}✅ API service is accessible${NC}"
else
    echo -e "${RED}❌ API service is not accessible at $API_URL${NC}"
    echo -e "${YELLOW}⚠️  Start the service with: docker-compose up -d ai-service${NC}"
    exit 1
fi
echo ""

# Note about tokens
echo "================================================================================"
echo "SETUP REQUIRED"
echo "================================================================================"
echo ""
echo "Before running these tests, you need to:"
echo ""
echo "1. Create two test users (User A and User B)"
echo "2. Obtain JWT tokens for both users"
echo "3. Set environment variables:"
echo ""
echo "   export TOKEN_USER_A='eyJ...your_token_for_user_a'"
echo "   export TOKEN_USER_B='eyJ...your_token_for_user_b'"
echo ""
echo "4. Create test resources (documents, messages, etc.) for User A"
echo "5. Set resource IDs:"
echo ""
echo "   export USER_A_DOCUMENT_ID='uuid-here'"
echo "   export USER_A_THREAD_ID='uuid-here'"
echo "   export USER_A_CHILD_ID='uuid-here'"
echo "   # ... etc."
echo ""
echo "================================================================================"
echo ""

# Check if tokens are set
if [ -z "$TOKEN_USER_A" ] || [ -z "$TOKEN_USER_B" ]; then
    echo -e "${YELLOW}⚠️  Tokens not set. Showing example commands only.${NC}"
    echo ""
    EXAMPLE_MODE=true
else
    echo -e "${GREEN}✅ Tokens found. Running actual tests...${NC}"
    echo ""
    EXAMPLE_MODE=false
fi

# Function to test endpoint
test_endpoint() {
    local method=$1
    local endpoint=$2
    local token=$3
    local expected_status=$4
    local description=$5
    local data=$6

    echo "----------------------------------------"
    echo "Test: $description"
    echo "Endpoint: $method $endpoint"
    echo "Expected: HTTP $expected_status"

    if [ "$EXAMPLE_MODE" = true ]; then
        echo -e "${BLUE}Command:${NC}"
        if [ -z "$data" ]; then
            echo "  curl -X $method '$API_URL$endpoint' \\"
            echo "    -H 'Authorization: Bearer \$TOKEN_USER_B' \\"
            echo "    -w '\\nHTTP Status: %{http_code}\\n'"
        else
            echo "  curl -X $method '$API_URL$endpoint' \\"
            echo "    -H 'Authorization: Bearer \$TOKEN_USER_B' \\"
            echo "    -H 'Content-Type: application/json' \\"
            echo "    -d '$data' \\"
            echo "    -w '\\nHTTP Status: %{http_code}\\n'"
        fi
    else
        # Run actual test
        if [ -z "$data" ]; then
            response=$(curl -s -w "\n%{http_code}" -X "$method" "$API_URL$endpoint" \
                -H "Authorization: Bearer $token" 2>&1 || echo "000")
        else
            response=$(curl -s -w "\n%{http_code}" -X "$method" "$API_URL$endpoint" \
                -H "Authorization: Bearer $token" \
                -H "Content-Type: application/json" \
                -d "$data" 2>&1 || echo "000")
        fi

        actual_status=$(echo "$response" | tail -n 1)

        if [ "$actual_status" = "$expected_status" ]; then
            echo -e "${GREEN}✅ PASSED${NC} - Got HTTP $actual_status"
        else
            echo -e "${RED}❌ FAILED${NC} - Expected HTTP $expected_status, got HTTP $actual_status"
            echo "Response:"
            echo "$response" | head -n -1
        fi
    fi
    echo ""
}

echo "================================================================================"
echo "1. DOCUMENT SERVICE TESTS"
echo "================================================================================"
echo ""

USER_A_DOCUMENT_ID="${USER_A_DOCUMENT_ID:-00000000-0000-0000-0000-000000000001}"
USER_A_TEMPLATE_ID="${USER_A_TEMPLATE_ID:-00000000-0000-0000-0000-000000000002}"
USER_A_SIGNATURE_ID="${USER_A_SIGNATURE_ID:-00000000-0000-0000-0000-000000000003}"

test_endpoint "GET" "/api/v1/documents/$USER_A_DOCUMENT_ID" "$TOKEN_USER_B" "403" \
    "User B accessing User A's document"

test_endpoint "DELETE" "/api/v1/documents/$USER_A_DOCUMENT_ID" "$TOKEN_USER_B" "403" \
    "User B deleting User A's document"

test_endpoint "GET" "/api/v1/templates/$USER_A_TEMPLATE_ID" "$TOKEN_USER_B" "403" \
    "User B accessing User A's template"

test_endpoint "GET" "/api/v1/signature-requests/$USER_A_SIGNATURE_ID" "$TOKEN_USER_B" "403" \
    "User B accessing User A's signature request"

echo "================================================================================"
echo "2. MESSAGING SERVICE TESTS"
echo "================================================================================"
echo ""

USER_A_THREAD_ID="${USER_A_THREAD_ID:-00000000-0000-0000-0000-000000000004}"
USER_A_MESSAGE_ID="${USER_A_MESSAGE_ID:-00000000-0000-0000-0000-000000000005}"
USER_A_ID="${USER_A_ID:-00000000-0000-0000-0000-000000000100}"

test_endpoint "GET" "/api/v1/threads/$USER_A_THREAD_ID" "$TOKEN_USER_B" "403" \
    "User B accessing User A's thread"

test_endpoint "GET" "/api/v1/messages/$USER_A_MESSAGE_ID" "$TOKEN_USER_B" "403" \
    "User B reading User A's message"

test_endpoint "GET" "/api/v1/notifications/preferences/$USER_A_ID" "$TOKEN_USER_B" "403" \
    "User B accessing User A's notification preferences"

test_endpoint "PATCH" "/api/v1/notifications/preferences/$USER_A_ID/quiet-hours" "$TOKEN_USER_B" "403" \
    "User B modifying User A's quiet hours" \
    '{"start_time": "22:00", "end_time": "07:00"}'

echo "================================================================================"
echo "3. COMMUNICATION SERVICE TESTS"
echo "================================================================================"
echo ""

USER_A_CHILD_ID="${USER_A_CHILD_ID:-00000000-0000-0000-0000-000000000006}"

test_endpoint "GET" "/api/v1/home-activities/$USER_A_CHILD_ID" "$TOKEN_USER_B" "403" \
    "Parent B accessing Parent A's child home activities"

test_endpoint "POST" "/api/v1/generate-report" "$TOKEN_USER_B" "403" \
    "Parent B generating report for Parent A's child" \
    "{\"child_id\": \"$USER_A_CHILD_ID\", \"report_type\": \"daily\"}"

test_endpoint "GET" "/api/v1/preferences/$USER_A_ID" "$TOKEN_USER_B" "403" \
    "Parent B accessing Parent A's preferences"

echo "================================================================================"
echo "4. DEVELOPMENT PROFILE SERVICE TESTS"
echo "================================================================================"
echo ""

USER_A_PROFILE_ID="${USER_A_PROFILE_ID:-00000000-0000-0000-0000-000000000007}"
USER_A_MILESTONE_ID="${USER_A_MILESTONE_ID:-00000000-0000-0000-0000-000000000008}"

test_endpoint "GET" "/api/v1/children/$USER_A_CHILD_ID/profiles" "$TOKEN_USER_B" "403" \
    "Parent B accessing Parent A's child profiles"

test_endpoint "GET" "/api/v1/profiles/$USER_A_PROFILE_ID" "$TOKEN_USER_B" "403" \
    "Parent B accessing Parent A's development profile"

test_endpoint "PATCH" "/api/v1/milestones/$USER_A_MILESTONE_ID" "$TOKEN_USER_B" "403" \
    "Parent B modifying Parent A's milestone" \
    '{"status": "completed"}'

echo "================================================================================"
echo "5. INTERVENTION PLAN SERVICE TESTS"
echo "================================================================================"
echo ""

USER_A_PLAN_ID="${USER_A_PLAN_ID:-00000000-0000-0000-0000-000000000009}"
USER_A_GOAL_ID="${USER_A_GOAL_ID:-00000000-0000-0000-0000-000000000010}"

test_endpoint "GET" "/api/v1/children/$USER_A_CHILD_ID/intervention-plans" "$TOKEN_USER_B" "403" \
    "Parent B accessing Parent A's child intervention plans"

test_endpoint "GET" "/api/v1/intervention-plans/$USER_A_PLAN_ID" "$TOKEN_USER_B" "403" \
    "Parent B accessing Parent A's intervention plan"

test_endpoint "DELETE" "/api/v1/goals/$USER_A_GOAL_ID" "$TOKEN_USER_B" "403" \
    "Parent B deleting Parent A's goal"

echo "================================================================================"
echo "6. STORAGE SERVICE TESTS"
echo "================================================================================"
echo ""

USER_A_FILE_ID="${USER_A_FILE_ID:-00000000-0000-0000-0000-000000000011}"

# Note: Storage service returns 404 instead of 403 for security reasons
test_endpoint "GET" "/api/v1/files/$USER_A_FILE_ID" "$TOKEN_USER_B" "404" \
    "User B accessing User A's private file"

test_endpoint "GET" "/api/v1/files/$USER_A_FILE_ID/download" "$TOKEN_USER_B" "404" \
    "User B downloading User A's file"

test_endpoint "DELETE" "/api/v1/files/$USER_A_FILE_ID" "$TOKEN_USER_B" "404" \
    "User B deleting User A's file"

test_endpoint "POST" "/api/v1/files/$USER_A_FILE_ID/secure-url" "$TOKEN_USER_B" "404" \
    "User B generating secure URL for User A's file"

echo "================================================================================"
echo "TEST SUMMARY"
echo "================================================================================"
echo ""

if [ "$EXAMPLE_MODE" = true ]; then
    echo -e "${YELLOW}Tests run in EXAMPLE MODE${NC}"
    echo ""
    echo "To run actual tests:"
    echo "1. Set up test users and obtain tokens"
    echo "2. Create test resources and set resource IDs"
    echo "3. Export environment variables"
    echo "4. Run this script again"
    echo ""
    echo "Example setup:"
    echo ""
    echo "  # Set tokens"
    echo "  export TOKEN_USER_A='eyJ...'"
    echo "  export TOKEN_USER_B='eyJ...'"
    echo ""
    echo "  # Set resource IDs"
    echo "  export USER_A_DOCUMENT_ID='...'"
    echo "  export USER_A_CHILD_ID='...'"
    echo "  # ... etc."
    echo ""
    echo "  # Run tests"
    echo "  ./manual_idor_tests.sh"
else
    echo -e "${GREEN}All penetration tests completed!${NC}"
    echo ""
    echo "Review the results above to verify:"
    echo "- All unauthorized access attempts were blocked"
    echo "- HTTP 403 Forbidden returned for most endpoints"
    echo "- HTTP 404 Not Found returned for storage endpoints (security best practice)"
fi

echo ""
echo "================================================================================"
