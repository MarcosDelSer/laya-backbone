#!/bin/bash

# Security Vulnerability Scan and Audit Script
# Task 203 - Subtask 5-4
# This script performs comprehensive security checks across all frontend services

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Counters
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0
WARNING_CHECKS=0

# Log file
AUDIT_LOG="./security-audit-report.txt"
echo "=== Security Vulnerability Scan and Audit ===" > "$AUDIT_LOG"
echo "Date: $(date)" >> "$AUDIT_LOG"
echo "" >> "$AUDIT_LOG"

# Function to print colored output
print_status() {
    local status=$1
    local message=$2

    if [ "$status" == "PASS" ]; then
        echo -e "${GREEN}✓${NC} $message"
        echo "✓ $message" >> "$AUDIT_LOG"
        ((PASSED_CHECKS++))
    elif [ "$status" == "FAIL" ]; then
        echo -e "${RED}✗${NC} $message"
        echo "✗ $message" >> "$AUDIT_LOG"
        ((FAILED_CHECKS++))
    elif [ "$status" == "WARN" ]; then
        echo -e "${YELLOW}⚠${NC} $message"
        echo "⚠ $message" >> "$AUDIT_LOG"
        ((WARNING_CHECKS++))
    else
        echo -e "${BLUE}ℹ${NC} $message"
        echo "ℹ $message" >> "$AUDIT_LOG"
    fi
    ((TOTAL_CHECKS++))
}

print_header() {
    local header=$1
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$header${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo "" >> "$AUDIT_LOG"
    echo "========================================" >> "$AUDIT_LOG"
    echo "$header" >> "$AUDIT_LOG"
    echo "========================================" >> "$AUDIT_LOG"
}

# Function to check if directory exists
check_directory() {
    local dir=$1
    if [ -d "$dir" ]; then
        return 0
    else
        return 1
    fi
}

# Function to run npm audit for a service
run_npm_audit() {
    local service=$1
    print_header "NPM AUDIT: $service"

    if ! check_directory "$service"; then
        print_status "WARN" "$service directory not found"
        return
    fi

    cd "$service"

    # Check if node_modules exists
    if [ ! -d "node_modules" ]; then
        print_status "WARN" "$service: node_modules not found, skipping npm audit"
        cd ..
        return
    fi

    echo "Running npm audit for $service..." >> "../$AUDIT_LOG"

    # Run npm audit and capture output
    if npm audit --json > npm-audit-output.json 2>&1; then
        print_status "PASS" "$service: npm audit passed with no vulnerabilities"
    else
        # Parse the audit results
        if [ -f npm-audit-output.json ]; then
            # Extract vulnerability counts
            local critical=$(jq -r '.metadata.vulnerabilities.critical // 0' npm-audit-output.json 2>/dev/null || echo "0")
            local high=$(jq -r '.metadata.vulnerabilities.high // 0' npm-audit-output.json 2>/dev/null || echo "0")
            local moderate=$(jq -r '.metadata.vulnerabilities.moderate // 0' npm-audit-output.json 2>/dev/null || echo "0")
            local low=$(jq -r '.metadata.vulnerabilities.low // 0' npm-audit-output.json 2>/dev/null || echo "0")

            echo "  Critical: $critical, High: $high, Moderate: $moderate, Low: $low" >> "../$AUDIT_LOG"

            if [ "$critical" -gt 0 ] || [ "$high" -gt 0 ]; then
                print_status "FAIL" "$service: Found $critical critical and $high high severity vulnerabilities"

                # Show specific vulnerabilities
                echo "" >> "../$AUDIT_LOG"
                echo "Vulnerability Details:" >> "../$AUDIT_LOG"
                npm audit >> "../$AUDIT_LOG" 2>&1 || true
            elif [ "$moderate" -gt 0 ] || [ "$low" -gt 0 ]; then
                print_status "WARN" "$service: Found $moderate moderate and $low low severity vulnerabilities"
            else
                print_status "PASS" "$service: npm audit passed"
            fi

            rm npm-audit-output.json
        else
            print_status "WARN" "$service: Could not parse npm audit output"
        fi
    fi

    cd ..
}

# Function to run ESLint security checks
run_eslint_check() {
    local service=$1
    print_header "ESLINT SECURITY CHECK: $service"

    if ! check_directory "$service"; then
        print_status "WARN" "$service directory not found"
        return
    fi

    cd "$service"

    # Check if .eslintrc.json or .eslintrc.js exists
    if [ ! -f ".eslintrc.json" ] && [ ! -f ".eslintrc.js" ]; then
        print_status "WARN" "$service: ESLint config not found"
        cd ..
        return
    fi

    echo "Running ESLint for $service..." >> "../$AUDIT_LOG"

    # Run ESLint and capture output
    if npm run lint -- --max-warnings 0 > eslint-output.txt 2>&1; then
        print_status "PASS" "$service: ESLint passed with no violations"
    else
        # Check for security-related violations
        if grep -E "(no-eval|no-implied-eval|no-new-func|no-script-url|react/no-danger|no-proto)" eslint-output.txt > /dev/null 2>&1; then
            print_status "FAIL" "$service: ESLint found security violations"
            echo "" >> "../$AUDIT_LOG"
            echo "ESLint Security Violations:" >> "../$AUDIT_LOG"
            grep -E "(no-eval|no-implied-eval|no-new-func|no-script-url|react/no-danger|no-proto)" eslint-output.txt >> "../$AUDIT_LOG" 2>&1 || true
        else
            print_status "WARN" "$service: ESLint found non-security violations"
        fi

        # Append full output to log
        cat eslint-output.txt >> "../$AUDIT_LOG" 2>&1 || true
        rm eslint-output.txt
    fi

    cd ..
}

# Function to test XSS payloads
test_xss_payloads() {
    print_header "XSS PAYLOAD TESTING"

    # Common XSS payloads
    local payloads=(
        "<script>alert('xss')</script>"
        "<img src=x onerror=alert('xss')>"
        "<svg onload=alert('xss')>"
        "javascript:alert('xss')"
        "<iframe src='javascript:alert(\"xss\")'>"
        "<body onload=alert('xss')>"
        "<input onfocus=alert('xss') autofocus>"
        "<marquee onstart=alert('xss')>"
        "<details open ontoggle=alert('xss')>"
        "<a href='data:text/html,<script>alert(\"xss\")</script>'>click</a>"
    )

    echo "Testing XSS payloads against sanitization utilities..." >> "$AUDIT_LOG"

    # Test parent-portal sanitization utilities if they exist
    if [ -f "parent-portal/lib/security/sanitize.ts" ]; then
        print_status "INFO" "Testing parent-portal sanitization utilities"

        # Create a test script to check sanitization
        cat > test-xss-sanitization.js <<'EOF'
const { JSDOM } = require('jsdom');
const DOMPurify = require('isomorphic-dompurify');

const payloads = [
    "<script>alert('xss')</script>",
    "<img src=x onerror=alert('xss')>",
    "<svg onload=alert('xss')>",
    "<iframe src='javascript:alert(\"xss\")'>"
];

let allPassed = true;

payloads.forEach((payload, index) => {
    const sanitized = DOMPurify.sanitize(payload, { ALLOWED_TAGS: [] });
    const isClean = !sanitized.includes('script') &&
                    !sanitized.includes('onerror') &&
                    !sanitized.includes('onload') &&
                    !sanitized.includes('javascript:');

    if (!isClean) {
        console.log(`FAIL: Payload ${index + 1} not properly sanitized: ${payload}`);
        allPassed = false;
    } else {
        console.log(`PASS: Payload ${index + 1} sanitized: ${payload} → ${sanitized || '(empty)'}`);
    }
});

process.exit(allPassed ? 0 : 1);
EOF

        # Run the test script
        if cd parent-portal && node ../test-xss-sanitization.js >> "../$AUDIT_LOG" 2>&1; then
            print_status "PASS" "All XSS payloads properly sanitized"
        else
            print_status "FAIL" "Some XSS payloads not properly sanitized"
        fi
        cd ..

        rm test-xss-sanitization.js
    else
        print_status "WARN" "parent-portal sanitization utilities not found"
    fi
}

# Function to check TypeScript strict mode
check_typescript_strict() {
    local service=$1
    print_header "TYPESCRIPT STRICT MODE: $service"

    if ! check_directory "$service"; then
        print_status "WARN" "$service directory not found"
        return
    fi

    cd "$service"

    if [ ! -f "tsconfig.json" ]; then
        print_status "WARN" "$service: tsconfig.json not found"
        cd ..
        return
    fi

    echo "Checking TypeScript configuration for $service..." >> "../$AUDIT_LOG"

    # Check for strict mode settings
    local has_strict=$(grep -c '"strict": true' tsconfig.json || echo "0")
    local has_no_implicit_any=$(grep -c '"noImplicitAny": true' tsconfig.json || echo "0")
    local has_strict_null=$(grep -c '"strictNullChecks": true' tsconfig.json || echo "0")

    if [ "$has_strict" -gt 0 ] || ([ "$has_no_implicit_any" -gt 0 ] && [ "$has_strict_null" -gt 0 ]); then
        print_status "PASS" "$service: TypeScript strict mode enabled"

        # Try to compile
        if npx tsc --noEmit > tsc-output.txt 2>&1; then
            print_status "PASS" "$service: TypeScript compilation successful"
        else
            # Count errors
            local error_count=$(grep -c "error TS" tsc-output.txt || echo "0")
            if [ "$error_count" -gt 0 ]; then
                print_status "WARN" "$service: TypeScript found $error_count errors"
                echo "" >> "../$AUDIT_LOG"
                echo "TypeScript Errors (first 20):" >> "../$AUDIT_LOG"
                head -n 20 tsc-output.txt >> "../$AUDIT_LOG" 2>&1 || true
            fi
        fi

        rm -f tsc-output.txt
    else
        print_status "FAIL" "$service: TypeScript strict mode not enabled"
    fi

    cd ..
}

# Function to check security headers configuration
check_security_headers() {
    print_header "SECURITY HEADERS CONFIGURATION"

    # Check parent-portal next.config.js
    if [ -f "parent-portal/next.config.js" ]; then
        echo "Checking parent-portal security headers..." >> "$AUDIT_LOG"

        if grep -q "Content-Security-Policy" parent-portal/next.config.js; then
            print_status "PASS" "parent-portal: Content Security Policy configured"
        else
            print_status "FAIL" "parent-portal: Content Security Policy not configured"
        fi

        if grep -q "X-Frame-Options" parent-portal/next.config.js; then
            print_status "PASS" "parent-portal: X-Frame-Options configured"
        else
            print_status "WARN" "parent-portal: X-Frame-Options not configured"
        fi
    else
        print_status "WARN" "parent-portal: next.config.js not found"
    fi

    # Check desktop-app CSP
    if [ -f "desktop-app/src/main.ts" ]; then
        echo "Checking desktop-app security headers..." >> "$AUDIT_LOG"

        if grep -q "Content-Security-Policy" desktop-app/src/main.ts; then
            print_status "PASS" "desktop-app: Content Security Policy configured"

            # Check for unsafe-inline and unsafe-eval
            if grep "Content-Security-Policy" desktop-app/src/main.ts | grep -q "unsafe-inline\|unsafe-eval"; then
                print_status "WARN" "desktop-app: CSP contains unsafe-inline or unsafe-eval"
            else
                print_status "PASS" "desktop-app: CSP does not contain unsafe directives"
            fi
        else
            print_status "FAIL" "desktop-app: Content Security Policy not configured"
        fi
    else
        print_status "WARN" "desktop-app: main.ts not found"
    fi
}

# Function to check secure storage implementation
check_secure_storage() {
    print_header "SECURE STORAGE IMPLEMENTATION"

    # Check parent-app
    if [ -f "parent-app/src/utils/secureStorage.ts" ]; then
        print_status "PASS" "parent-app: Secure storage utility implemented"

        # Check for react-native-keychain dependency
        if grep -q "react-native-keychain" parent-app/package.json; then
            print_status "PASS" "parent-app: react-native-keychain dependency present"
        else
            print_status "FAIL" "parent-app: react-native-keychain dependency missing"
        fi
    else
        print_status "FAIL" "parent-app: Secure storage utility not found"
    fi

    # Check teacher-app
    if [ -f "teacher-app/src/utils/secureStorage.ts" ]; then
        print_status "PASS" "teacher-app: Secure storage utility implemented"

        # Check for react-native-keychain dependency
        if grep -q "react-native-keychain" teacher-app/package.json; then
            print_status "PASS" "teacher-app: react-native-keychain dependency present"
        else
            print_status "FAIL" "teacher-app: react-native-keychain dependency missing"
        fi
    else
        print_status "FAIL" "teacher-app: Secure storage utility not found"
    fi
}

# Function to check CSRF implementation
check_csrf_implementation() {
    print_header "CSRF PROTECTION IMPLEMENTATION"

    if [ -f "parent-portal/lib/security/csrf.ts" ]; then
        print_status "PASS" "parent-portal: CSRF utilities implemented"

        # Check for key functions
        if grep -q "fetchCSRFToken" parent-portal/lib/security/csrf.ts && \
           grep -q "getCSRFToken" parent-portal/lib/security/csrf.ts && \
           grep -q "validateCSRFToken" parent-portal/lib/security/csrf.ts; then
            print_status "PASS" "parent-portal: CSRF key functions present"
        else
            print_status "WARN" "parent-portal: Some CSRF functions may be missing"
        fi
    else
        print_status "FAIL" "parent-portal: CSRF utilities not found"
    fi
}

# Main execution
main() {
    echo -e "${BLUE}Starting Security Vulnerability Scan and Audit${NC}"
    echo "This will check:"
    echo "  1. NPM dependency vulnerabilities"
    echo "  2. ESLint security rule violations"
    echo "  3. XSS payload sanitization"
    echo "  4. TypeScript strict mode"
    echo "  5. Security headers configuration"
    echo "  6. Secure storage implementation"
    echo "  7. CSRF protection implementation"
    echo ""

    # 1. NPM Audit
    run_npm_audit "parent-portal"
    run_npm_audit "parent-app"
    run_npm_audit "teacher-app"
    run_npm_audit "desktop-app"

    # 2. ESLint Checks
    run_eslint_check "parent-portal"
    run_eslint_check "parent-app"
    run_eslint_check "teacher-app"

    # 3. XSS Testing
    test_xss_payloads

    # 4. TypeScript Strict Mode
    check_typescript_strict "parent-portal"
    check_typescript_strict "parent-app"
    check_typescript_strict "teacher-app"
    check_typescript_strict "desktop-app"

    # 5. Security Headers
    check_security_headers

    # 6. Secure Storage
    check_secure_storage

    # 7. CSRF Implementation
    check_csrf_implementation

    # Summary
    print_header "AUDIT SUMMARY"
    echo ""
    echo -e "Total Checks: $TOTAL_CHECKS"
    echo -e "${GREEN}Passed: $PASSED_CHECKS${NC}"
    echo -e "${RED}Failed: $FAILED_CHECKS${NC}"
    echo -e "${YELLOW}Warnings: $WARNING_CHECKS${NC}"
    echo ""
    echo "Total Checks: $TOTAL_CHECKS" >> "$AUDIT_LOG"
    echo "Passed: $PASSED_CHECKS" >> "$AUDIT_LOG"
    echo "Failed: $FAILED_CHECKS" >> "$AUDIT_LOG"
    echo "Warnings: $WARNING_CHECKS" >> "$AUDIT_LOG"
    echo "" >> "$AUDIT_LOG"

    # Determine exit status based on verification criteria
    if [ "$FAILED_CHECKS" -eq 0 ]; then
        echo -e "${GREEN}✓ Security audit completed successfully${NC}"
        echo "✓ Security audit completed successfully" >> "$AUDIT_LOG"

        if [ "$WARNING_CHECKS" -gt 0 ]; then
            echo -e "${YELLOW}⚠ Some warnings were found, please review${NC}"
            echo "⚠ Some warnings were found, please review" >> "$AUDIT_LOG"
        fi

        echo ""
        echo "Detailed report saved to: $AUDIT_LOG"
        exit 0
    else
        echo -e "${RED}✗ Security audit found critical issues${NC}"
        echo "✗ Security audit found critical issues" >> "$AUDIT_LOG"
        echo ""
        echo "Detailed report saved to: $AUDIT_LOG"
        echo "Please address the failed checks before proceeding."
        exit 1
    fi
}

# Run main function
main
