#!/bin/bash
# Manual verification script for Makefile seed commands
# This script verifies that the Makefile is correctly structured

set -e

echo "========================================="
echo "Verifying Makefile Seed Commands"
echo "========================================="
echo ""

# Colors for output
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Counter for tests
TESTS_PASSED=0
TESTS_FAILED=0

# Helper function to run tests
test_assertion() {
    local description="$1"
    local command="$2"
    local expected="$3"

    echo -n "Testing: $description... "

    if eval "$command" | grep -q "$expected"; then
        echo -e "${GREEN}✓ PASS${NC}"
        ((TESTS_PASSED++))
    else
        echo -e "${RED}✗ FAIL${NC}"
        ((TESTS_FAILED++))
    fi
}

# Test 1: Makefile exists
echo "Test 1: Checking if Makefile exists..."
if [ -f "Makefile" ]; then
    echo -e "${GREEN}✓ PASS${NC} - Makefile exists"
    ((TESTS_PASSED++))
else
    echo -e "${RED}✗ FAIL${NC} - Makefile not found"
    ((TESTS_FAILED++))
    exit 1
fi
echo ""

# Test 2: Help target works
echo "Test 2: Verifying help target..."
test_assertion "make help shows LAYA commands" "make help" "LAYA Development Seed Data Commands"
test_assertion "make help shows seed target" "make help" "make seed"
test_assertion "make help shows seed-reset target" "make help" "make seed-reset"
echo ""

# Test 3: All targets exist in Makefile
echo "Test 3: Verifying all targets exist in Makefile..."
for target in "seed" "seed-reset" "seed-ai" "seed-ai-reset" "seed-gibbon" "seed-gibbon-reset"; do
    test_assertion "Target $target exists" "cat Makefile" "$target:"
done
echo ""

# Test 4: PHONY declaration
echo "Test 4: Verifying .PHONY declaration..."
test_assertion ".PHONY declaration exists" "cat Makefile" ".PHONY:"
echo ""

# Test 5: Dry-run tests
echo "Test 5: Verifying make dry-run commands..."
test_assertion "seed-ai dry-run" "make -n seed-ai" "python scripts/seed.py"
test_assertion "seed-ai-reset uses alembic" "make -n seed-ai-reset" "alembic downgrade base"
test_assertion "seed-gibbon dry-run" "make -n seed-gibbon" "seed_data.php"
test_assertion "seed-gibbon-reset uses --reset" "make -n seed-gibbon-reset" "\\-\\-reset"
echo ""

# Test 6: Documentation exists
echo "Test 6: Verifying documentation..."
if [ -f "SEED_CLI_COMMANDS.md" ]; then
    echo -e "${GREEN}✓ PASS${NC} - SEED_CLI_COMMANDS.md exists"
    ((TESTS_PASSED++))
else
    echo -e "${RED}✗ FAIL${NC} - SEED_CLI_COMMANDS.md not found"
    ((TESTS_FAILED++))
fi
echo ""

# Test 7: Test files exist
echo "Test 7: Verifying test files..."
if [ -f "ai-service/tests/test_makefile_commands.py" ]; then
    echo -e "${GREEN}✓ PASS${NC} - test_makefile_commands.py exists"
    ((TESTS_PASSED++))
else
    echo -e "${RED}✗ FAIL${NC} - test_makefile_commands.py not found"
    ((TESTS_FAILED++))
fi
echo ""

# Test 8: Seed scripts exist
echo "Test 8: Verifying seed scripts exist..."
if [ -f "ai-service/scripts/seed.py" ]; then
    echo -e "${GREEN}✓ PASS${NC} - ai-service/scripts/seed.py exists"
    ((TESTS_PASSED++))
else
    echo -e "${RED}✗ FAIL${NC} - ai-service/scripts/seed.py not found"
    ((TESTS_FAILED++))
fi

if [ -f "gibbon/modules/seed_data.php" ]; then
    echo -e "${GREEN}✓ PASS${NC} - gibbon/modules/seed_data.php exists"
    ((TESTS_PASSED++))
else
    echo -e "${RED}✗ FAIL${NC} - gibbon/modules/seed_data.php not found"
    ((TESTS_FAILED++))
fi
echo ""

# Summary
echo "========================================="
echo "Test Summary"
echo "========================================="
echo "Tests passed: $TESTS_PASSED"
echo "Tests failed: $TESTS_FAILED"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}✓ All tests passed!${NC}"
    echo ""
    echo "The Makefile seed commands are correctly implemented."
    echo ""
    echo "You can now use:"
    echo "  make seed              - Seed both databases"
    echo "  make seed-reset        - Reset and seed both databases"
    echo "  make help              - Show all available commands"
    echo ""
    exit 0
else
    echo -e "${RED}✗ Some tests failed${NC}"
    exit 1
fi
