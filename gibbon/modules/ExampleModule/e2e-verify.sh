#!/bin/bash
################################################################################
# ExampleModule - End-to-End Verification Script
#
# This script performs automated verification checks for ExampleModule.
# Run this script after manual E2E testing to verify technical requirements.
#
# Usage:
#   chmod +x e2e-verify.sh
#   ./e2e-verify.sh
#
# Prerequisites:
#   - Gibbon services running (docker-compose up -d gibbon mysql)
#   - Module installed via Gibbon web interface
#   - MySQL accessible
################################################################################

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test counters
PASSED=0
FAILED=0
WARNINGS=0

echo "======================================================================"
echo "  ExampleModule - End-to-End Verification Script"
echo "======================================================================"
echo ""

# Helper functions
print_test() {
    echo -e "${BLUE}[TEST]${NC} $1"
}

print_pass() {
    echo -e "${GREEN}[PASS]${NC} $1"
    ((PASSED++))
}

print_fail() {
    echo -e "${RED}[FAIL]${NC} $1"
    ((FAILED++))
}

print_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
    ((WARNINGS++))
}

print_section() {
    echo ""
    echo "======================================================================"
    echo "  $1"
    echo "======================================================================"
    echo ""
}

################################################################################
# Section 1: File Structure Verification
################################################################################
print_section "1. FILE STRUCTURE VERIFICATION"

print_test "Checking module directory structure..."
if [ -d "./gibbon/modules/ExampleModule" ]; then
    print_pass "Module directory exists"
else
    print_fail "Module directory not found"
    exit 1
fi

# Check required files
REQUIRED_FILES=(
    "manifest.php"
    "CHANGEDB.php"
    "version.php"
    "README.md"
    "src/Domain/ExampleEntityGateway.php"
    "exampleModule.php"
    "exampleModule_manage.php"
    "exampleModule_view.php"
    "exampleModule_settings.php"
)

for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "./gibbon/modules/ExampleModule/$file" ]; then
        print_pass "File exists: $file"
    else
        print_fail "File missing: $file"
    fi
done

################################################################################
# Section 2: PHP Syntax Verification
################################################################################
print_section "2. PHP SYNTAX VERIFICATION"

print_test "Checking PHP syntax for all module files..."

# Check if PHP is available
if command -v php &> /dev/null; then
    SYNTAX_ERRORS=0

    # Find all PHP files and check syntax
    while IFS= read -r php_file; do
        if php -l "$php_file" > /dev/null 2>&1; then
            print_pass "Syntax OK: $(basename $php_file)"
        else
            print_fail "Syntax error: $(basename $php_file)"
            ((SYNTAX_ERRORS++))
        fi
    done < <(find ./gibbon/modules/ExampleModule -name "*.php" -type f)

    if [ $SYNTAX_ERRORS -eq 0 ]; then
        print_pass "All PHP files have valid syntax"
    else
        print_fail "Found $SYNTAX_ERRORS PHP syntax errors"
    fi
else
    print_warn "PHP CLI not available - skipping syntax checks"
    print_warn "Run this script inside Gibbon container: docker-compose exec gibbon ./path/to/e2e-verify.sh"
fi

################################################################################
# Section 3: Security Verification
################################################################################
print_section "3. SECURITY VERIFICATION"

print_test "Checking for GPL-3.0 license headers..."
GPL_FILES=$(grep -l "GNU General Public License" ./gibbon/modules/ExampleModule/*.php 2>/dev/null | wc -l)
PHP_FILES=$(find ./gibbon/modules/ExampleModule -maxdepth 1 -name "*.php" | wc -l)

if [ "$GPL_FILES" -eq "$PHP_FILES" ]; then
    print_pass "All PHP files have GPL-3.0 license headers ($GPL_FILES/$PHP_FILES)"
else
    print_fail "Some files missing GPL license: $GPL_FILES/$PHP_FILES files"
fi

print_test "Checking for hard-coded permission addresses..."
# Check that isActionAccessible uses literal strings, not variables
if grep -n "isActionAccessible.*\\\$" ./gibbon/modules/ExampleModule/*.php 2>/dev/null; then
    print_fail "Found isActionAccessible() with variables - SECURITY RISK!"
    echo "       All addresses must be hard-coded strings"
else
    print_pass "All isActionAccessible() calls use hard-coded addresses"
fi

print_test "Checking for parameterized queries in Gateway..."
if grep -q "bindValue\|->insert\|->update\|->delete" ./gibbon/modules/ExampleModule/src/Domain/*.php; then
    print_pass "Gateway uses parameterized queries"
else
    print_warn "Could not verify parameterized queries in Gateway"
fi

print_test "Checking for direct SQL injection vulnerabilities..."
# Look for dangerous patterns: WHERE ... = $variable
if grep -rn "WHERE.*=.*\\\$" ./gibbon/modules/ExampleModule/src/Domain/ 2>/dev/null; then
    print_fail "Found potential SQL injection: direct variable in WHERE clause"
else
    print_pass "No obvious SQL injection patterns found"
fi

print_test "Checking for XSS vulnerabilities..."
# Look for direct echo of $_GET, $_POST, $_REQUEST
if grep -rn "echo.*\\\$_\(GET\|POST\|REQUEST\)" ./gibbon/modules/ExampleModule/*.php 2>/dev/null; then
    print_fail "Found potential XSS: direct output of user input"
else
    print_pass "No obvious XSS patterns found (direct echo of \$_GET/\$_POST)"
fi

################################################################################
# Section 4: Database Schema Verification (requires MySQL access)
################################################################################
print_section "4. DATABASE SCHEMA VERIFICATION"

print_test "Checking MySQL connection..."

# Check if we can access MySQL via docker-compose
if command -v docker-compose &> /dev/null; then
    MYSQL_CMD="docker-compose exec -T mysql mysql -u gibbon_user -pchangeme gibbon"
elif command -v docker &> /dev/null; then
    MYSQL_CMD="docker compose exec -T mysql mysql -u gibbon_user -pchangeme gibbon"
else
    print_warn "Docker not available - skipping database checks"
    MYSQL_CMD=""
fi

if [ -n "$MYSQL_CMD" ]; then
    # Test MySQL connection
    if echo "SELECT 1;" | $MYSQL_CMD &> /dev/null; then
        print_pass "MySQL connection successful"

        # Check if module table exists
        print_test "Checking if gibbonExampleEntity table exists..."
        TABLE_EXISTS=$( echo "SHOW TABLES LIKE 'gibbonExampleEntity';" | $MYSQL_CMD 2>/dev/null | grep -c "gibbonExampleEntity" || true)

        if [ "$TABLE_EXISTS" -eq 1 ]; then
            print_pass "Table gibbonExampleEntity exists"

            # Check table collation
            print_test "Checking table collation..."
            COLLATION=$(echo "SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA='gibbon' AND TABLE_NAME='gibbonExampleEntity';" | $MYSQL_CMD -N 2>/dev/null || echo "unknown")

            if [[ "$COLLATION" == "utf8mb4_unicode_ci" ]]; then
                print_pass "Table uses correct collation: utf8mb4_unicode_ci"
            else
                print_fail "Table collation incorrect: $COLLATION (expected utf8mb4_unicode_ci)"
            fi

            # Check module settings
            print_test "Checking module settings in gibbonSetting..."
            SETTINGS_COUNT=$(echo "SELECT COUNT(*) FROM gibbonSetting WHERE scope='Example Module';" | $MYSQL_CMD -N 2>/dev/null || echo "0")

            if [ "$SETTINGS_COUNT" -ge 2 ]; then
                print_pass "Module settings exist ($SETTINGS_COUNT settings)"
            else
                print_fail "Module settings not found or incomplete ($SETTINGS_COUNT/2)"
            fi

            # Check for orphaned records
            print_test "Checking for orphaned records (invalid foreign keys)..."
            ORPHANED=$(echo "SELECT COUNT(*) FROM gibbonExampleEntity e LEFT JOIN gibbonPerson p ON e.gibbonPersonID = p.gibbonPersonID WHERE p.gibbonPersonID IS NULL;" | $MYSQL_CMD -N 2>/dev/null || echo "unknown")

            if [ "$ORPHANED" = "0" ]; then
                print_pass "No orphaned records found"
            elif [ "$ORPHANED" = "unknown" ]; then
                print_warn "Could not check for orphaned records"
            else
                print_fail "Found $ORPHANED orphaned records with invalid gibbonPersonID"
            fi

        else
            print_fail "Table gibbonExampleEntity does not exist - module not installed?"
            print_warn "Install module via: System Admin > Manage Modules"
        fi

    else
        print_warn "Cannot connect to MySQL - skipping database checks"
        print_warn "Ensure Gibbon services are running: docker-compose up -d"
    fi
else
    print_warn "Docker commands not available - skipping database checks"
fi

################################################################################
# Section 5: Code Quality Checks
################################################################################
print_section "5. CODE QUALITY CHECKS"

print_test "Checking for TODO/FIXME comments..."
TODO_COUNT=$(grep -rn "TODO\|FIXME" ./gibbon/modules/ExampleModule/*.php 2>/dev/null | wc -l)
if [ "$TODO_COUNT" -gt 0 ]; then
    print_warn "Found $TODO_COUNT TODO/FIXME comments"
    grep -rn "TODO\|FIXME" ./gibbon/modules/ExampleModule/*.php 2>/dev/null | head -5
else
    print_pass "No TODO/FIXME comments found"
fi

print_test "Checking for debugging statements..."
DEBUG_COUNT=$(grep -rn "var_dump\|print_r\|console\.log" ./gibbon/modules/ExampleModule/*.php 2>/dev/null | wc -l)
if [ "$DEBUG_COUNT" -gt 0 ]; then
    print_fail "Found $DEBUG_COUNT debugging statements (var_dump, print_r)"
    grep -rn "var_dump\|print_r" ./gibbon/modules/ExampleModule/*.php 2>/dev/null
else
    print_pass "No debugging statements found"
fi

print_test "Checking for deprecated PHP functions..."
DEPRECATED_COUNT=$(grep -rn "mysql_\|ereg\|split" ./gibbon/modules/ExampleModule/*.php 2>/dev/null | wc -l)
if [ "$DEPRECATED_COUNT" -gt 0 ]; then
    print_fail "Found $DEPRECATED_COUNT deprecated PHP functions"
else
    print_pass "No deprecated PHP functions found"
fi

print_test "Checking CHANGEDB.php format..."
# Check for ;end terminators
END_COUNT=$(grep -c ";end" ./gibbon/modules/ExampleModule/CHANGEDB.php || echo "0")
if [ "$END_COUNT" -ge 1 ]; then
    print_pass "CHANGEDB.php has $END_COUNT SQL statement terminators (;end)"
else
    print_fail "CHANGEDB.php missing ;end terminators"
fi

# Check for utf8mb4_unicode_ci collation
if grep -q "utf8mb4_unicode_ci" ./gibbon/modules/ExampleModule/CHANGEDB.php; then
    print_pass "CHANGEDB.php uses correct collation (utf8mb4_unicode_ci)"
else
    print_fail "CHANGEDB.php missing or wrong collation"
fi

################################################################################
# Section 6: Documentation Verification
################################################################################
print_section "6. DOCUMENTATION VERIFICATION"

DOCS=(
    "README.md"
    "INSTALLATION_VERIFICATION.md"
    "E2E_TEST_GUIDE.md"
    "MIGRATION_GUIDE.md"
    "ACTION_PAGES_OVERVIEW.md"
    "FORM_API_VERIFICATION.md"
)

print_test "Checking for documentation files..."
for doc in "${DOCS[@]}"; do
    if [ -f "./gibbon/modules/ExampleModule/$doc" ]; then
        print_pass "Documentation exists: $doc"
    else
        print_warn "Documentation missing: $doc"
    fi
done

################################################################################
# Section 7: Verification Summary
################################################################################
print_section "VERIFICATION SUMMARY"

TOTAL_TESTS=$((PASSED + FAILED + WARNINGS))

echo "Total Tests: $TOTAL_TESTS"
echo -e "${GREEN}Passed:${NC}  $PASSED"
echo -e "${RED}Failed:${NC}  $FAILED"
echo -e "${YELLOW}Warnings:${NC} $WARNINGS"
echo ""

if [ $FAILED -eq 0 ]; then
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}  ✓ ALL CRITICAL TESTS PASSED${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo ""

    if [ $WARNINGS -gt 0 ]; then
        echo -e "${YELLOW}Note: $WARNINGS warnings found (non-critical)${NC}"
        echo ""
    fi

    echo "ExampleModule is ready for E2E testing!"
    echo ""
    echo "Next Steps:"
    echo "1. Follow E2E_TEST_GUIDE.md for manual browser testing"
    echo "2. Test all CRUD operations with different user roles"
    echo "3. Verify permissions work correctly"
    echo "4. Check for PHP errors in logs"
    echo ""

    exit 0
else
    echo -e "${RED}========================================${NC}"
    echo -e "${RED}  ✗ VERIFICATION FAILED${NC}"
    echo -e "${RED}========================================${NC}"
    echo ""
    echo "Please fix the failed tests before proceeding."
    echo ""

    exit 1
fi
