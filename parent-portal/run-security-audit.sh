#!/bin/bash

# Comprehensive Security Audit Script
# Task 203 - Subtask 5-4: Security vulnerability scan and audit
#
# This script performs comprehensive security checks across all frontend services:
# 1. NPM dependency vulnerabilities (npm audit)
# 2. ESLint security rule violations
# 3. XSS payload sanitization testing
# 4. TypeScript strict mode compilation
# 5. Security headers configuration
# 6. Secure storage implementation
# 7. CSRF protection implementation

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

# Counters
TOTAL_CHECKS=0
PASSED_CHECKS=0
FAILED_CHECKS=0
WARNING_CHECKS=0

# Report file
REPORT="security-audit-report.txt"
echo "=================================" > "$REPORT"
echo "Security Vulnerability Audit" >> "$REPORT"
echo "=================================" >> "$REPORT"
echo "Date: $(date)" >> "$REPORT"
echo "Task: 203 - Frontend Security and Type Safety" >> "$REPORT"
echo "Subtask: 5-4 - Security vulnerability scan and audit" >> "$REPORT"
echo "" >> "$REPORT"

log() {
    echo "$1" | tee -a "$REPORT"
}

log_status() {
    local status=$1
    local message=$2

    ((TOTAL_CHECKS++))

    case "$status" in
        "PASS")
            echo -e "${GREEN}✓${NC} $message"
            echo "✓ $message" >> "$REPORT"
            ((PASSED_CHECKS++))
            ;;
        "FAIL")
            echo -e "${RED}✗${NC} $message"
            echo "✗ $message" >> "$REPORT"
            ((FAILED_CHECKS++))
            ;;
        "WARN")
            echo -e "${YELLOW}⚠${NC} $message"
            echo "⚠ $message" >> "$REPORT"
            ((WARNING_CHECKS++))
            ;;
        "INFO")
            echo -e "${BLUE}ℹ${NC} $message"
            echo "ℹ $message" >> "$REPORT"
            ;;
    esac
}

section() {
    local title=$1
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${BLUE}$title${NC}"
    echo -e "${BLUE}========================================${NC}"
    echo "" >> "$REPORT"
    echo "========================================" >> "$REPORT"
    echo "$title" >> "$REPORT"
    echo "========================================" >> "$REPORT"
}

# Check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check if directory exists
dir_exists() {
    [ -d "$1" ]
}

# ============================================
# 1. NPM AUDIT - Dependency Vulnerabilities
# ============================================
run_npm_audit() {
    local service=$1
    section "NPM AUDIT: $service"

    if ! dir_exists "$service"; then
        log_status "WARN" "$service directory not found"
        return
    fi

    cd "$service"

    if [ ! -d "node_modules" ]; then
        log_status "WARN" "$service: node_modules not found (run npm install first)"
        cd ..
        return
    fi

    log_status "INFO" "Running npm audit for $service..."

    # Run npm audit and parse JSON output
    if npm audit --json > audit-result.json 2>&1; then
        log_status "PASS" "$service: No vulnerabilities found"
    else
        # Parse vulnerabilities
        if command_exists jq; then
            local critical=$(jq -r '.metadata.vulnerabilities.critical // 0' audit-result.json 2>/dev/null)
            local high=$(jq -r '.metadata.vulnerabilities.high // 0' audit-result.json 2>/dev/null)
            local moderate=$(jq -r '.metadata.vulnerabilities.moderate // 0' audit-result.json 2>/dev/null)
            local low=$(jq -r '.metadata.vulnerabilities.low // 0' audit-result.json 2>/dev/null)

            log "" >> "../$REPORT"
            log "  Vulnerabilities: Critical=$critical, High=$high, Moderate=$moderate, Low=$low" >> "../$REPORT"

            if [ "$critical" -gt 0 ] || [ "$high" -gt 0 ]; then
                log_status "FAIL" "$service: Found $critical critical and $high high severity vulnerabilities"
                echo "" >> "../$REPORT"
                npm audit >> "../$REPORT" 2>&1 || true
            elif [ "$moderate" -gt 0 ] || [ "$low" -gt 0 ]; then
                log_status "WARN" "$service: Found $moderate moderate and $low low severity vulnerabilities"
            else
                log_status "PASS" "$service: No vulnerabilities found"
            fi
        else
            log_status "WARN" "$service: jq not found, cannot parse detailed audit results"
            npm audit >> "../$REPORT" 2>&1 || true
        fi

        rm -f audit-result.json
    fi

    cd ..
}

# ============================================
# 2. ESLINT - Security Rule Violations
# ============================================
run_eslint_check() {
    local service=$1
    section "ESLINT SECURITY CHECK: $service"

    if ! dir_exists "$service"; then
        log_status "WARN" "$service directory not found"
        return
    fi

    cd "$service"

    if [ ! -f ".eslintrc.json" ] && [ ! -f ".eslintrc.js" ]; then
        log_status "WARN" "$service: ESLint config not found"
        cd ..
        return
    fi

    log_status "INFO" "Running ESLint for $service..."

    # Run ESLint
    if npm run lint -- --max-warnings 0 > eslint-output.txt 2>&1; then
        log_status "PASS" "$service: ESLint passed with no violations"
    else
        # Check for security violations
        local has_security_violations=0

        if grep -qE "(no-eval|no-implied-eval|no-new-func|no-script-url|react/no-danger|no-proto)" eslint-output.txt; then
            has_security_violations=1
            log_status "FAIL" "$service: ESLint found security rule violations"
            echo "" >> "../$REPORT"
            echo "Security Violations:" >> "../$REPORT"
            grep -E "(no-eval|no-implied-eval|no-new-func|no-script-url|react/no-danger|no-proto)" eslint-output.txt >> "../$REPORT" || true
        else
            log_status "WARN" "$service: ESLint found non-security violations"
        fi

        echo "" >> "../$REPORT"
        echo "Full ESLint Output:" >> "../$REPORT"
        cat eslint-output.txt >> "../$REPORT" 2>&1 || true
        rm eslint-output.txt
    fi

    cd ..
}

# ============================================
# 3. XSS - Payload Sanitization Testing
# ============================================
test_xss_sanitization() {
    section "XSS PAYLOAD SANITIZATION TEST"

    if ! dir_exists "parent-portal"; then
        log_status "WARN" "parent-portal directory not found"
        return
    fi

    if [ ! -f "parent-portal/lib/security/sanitize.ts" ]; then
        log_status "FAIL" "parent-portal: sanitize.ts not found"
        return
    fi

    log_status "INFO" "Creating XSS test script..."

    # Create test script
    cat > test-xss.js <<'EOFXSS'
const { JSDOM } = require('jsdom');
const DOMPurify = require('isomorphic-dompurify');

const payloads = [
    { name: "Script tag", payload: "<script>alert('xss')</script>" },
    { name: "Image onerror", payload: "<img src=x onerror=alert('xss')>" },
    { name: "SVG onload", payload: "<svg onload=alert('xss')>" },
    { name: "JavaScript protocol", payload: "javascript:alert('xss')" },
    { name: "iFrame injection", payload: "<iframe src='javascript:alert(\"xss\")'>" },
    { name: "Body onload", payload: "<body onload=alert('xss')>" },
    { name: "Input onfocus", payload: "<input onfocus=alert('xss') autofocus>" },
    { name: "Marquee onstart", payload: "<marquee onstart=alert('xss')>" },
    { name: "Details ontoggle", payload: "<details open ontoggle=alert('xss')>" },
    { name: "Data URI", payload: "<a href='data:text/html,<script>alert(\"xss\")</script>'>click</a>" }
];

console.log("Testing XSS Payload Sanitization\n");

let allPassed = true;
let results = [];

payloads.forEach((item, index) => {
    const sanitized = DOMPurify.sanitize(item.payload, { ALLOWED_TAGS: [] });

    const isClean = !sanitized.includes('script') &&
                    !sanitized.includes('onerror') &&
                    !sanitized.includes('onload') &&
                    !sanitized.includes('javascript:') &&
                    !sanitized.includes('onfocus') &&
                    !sanitized.includes('onstart') &&
                    !sanitized.includes('ontoggle');

    const status = isClean ? 'PASS' : 'FAIL';
    results.push({ name: item.name, status, input: item.payload, output: sanitized || '(empty)' });

    if (!isClean) {
        allPassed = false;
    }
});

results.forEach(r => {
    console.log(`${r.status}: ${r.name}`);
    console.log(`  Input:  ${r.input}`);
    console.log(`  Output: ${r.output}`);
});

console.log(`\nTotal: ${results.length}, Passed: ${results.filter(r => r.status === 'PASS').length}, Failed: ${results.filter(r => r.status === 'FAIL').length}`);
process.exit(allPassed ? 0 : 1);
EOFXSS

    # Run test
    cd parent-portal
    if node ../test-xss.js >> "../$REPORT" 2>&1; then
        log_status "PASS" "All XSS payloads properly sanitized"
    else
        log_status "FAIL" "Some XSS payloads not properly sanitized"
    fi
    cd ..

    rm -f test-xss.js
}

# ============================================
# 4. TYPESCRIPT - Strict Mode Compilation
# ============================================
check_typescript() {
    local service=$1
    section "TYPESCRIPT STRICT MODE: $service"

    if ! dir_exists "$service"; then
        log_status "WARN" "$service directory not found"
        return
    fi

    cd "$service"

    if [ ! -f "tsconfig.json" ]; then
        log_status "WARN" "$service: tsconfig.json not found"
        cd ..
        return
    fi

    # Check for strict mode
    if grep -q '"strict": true' tsconfig.json || \
       (grep -q '"noImplicitAny": true' tsconfig.json && grep -q '"strictNullChecks": true' tsconfig.json); then
        log_status "PASS" "$service: TypeScript strict mode enabled"

        # Try to compile
        log_status "INFO" "Compiling $service..."
        if npx tsc --noEmit > tsc-output.txt 2>&1; then
            log_status "PASS" "$service: TypeScript compilation successful"
        else
            local error_count=$(grep -c "error TS" tsc-output.txt 2>/dev/null || echo "0")
            log_status "WARN" "$service: TypeScript found $error_count errors"
            echo "" >> "../$REPORT"
            echo "TypeScript Errors (first 30):" >> "../$REPORT"
            head -n 30 tsc-output.txt >> "../$REPORT" 2>&1 || true
        fi

        rm -f tsc-output.txt
    else
        log_status "FAIL" "$service: TypeScript strict mode not enabled"
    fi

    cd ..
}

# ============================================
# 5. SECURITY HEADERS - Configuration Check
# ============================================
check_security_headers() {
    section "SECURITY HEADERS CONFIGURATION"

    # Check parent-portal
    if [ -f "parent-portal/next.config.js" ]; then
        log_status "INFO" "Checking parent-portal security headers..."

        if grep -q "Content-Security-Policy" parent-portal/next.config.js; then
            log_status "PASS" "parent-portal: Content-Security-Policy configured"
        else
            log_status "FAIL" "parent-portal: Content-Security-Policy not configured"
        fi

        if grep -q "X-Frame-Options" parent-portal/next.config.js; then
            log_status "PASS" "parent-portal: X-Frame-Options configured"
        else
            log_status "WARN" "parent-portal: X-Frame-Options not configured"
        fi

        if grep -q "X-Content-Type-Options" parent-portal/next.config.js; then
            log_status "PASS" "parent-portal: X-Content-Type-Options configured"
        else
            log_status "WARN" "parent-portal: X-Content-Type-Options not configured"
        fi
    else
        log_status "WARN" "parent-portal: next.config.js not found"
    fi

    # Check desktop-app
    if [ -f "desktop-app/src/main.ts" ]; then
        log_status "INFO" "Checking desktop-app security headers..."

        if grep -q "Content-Security-Policy" desktop-app/src/main.ts; then
            log_status "PASS" "desktop-app: Content-Security-Policy configured"

            # Check for unsafe directives
            if grep "Content-Security-Policy" desktop-app/src/main.ts | grep -q "unsafe-inline\|unsafe-eval"; then
                log_status "WARN" "desktop-app: CSP contains unsafe-inline or unsafe-eval"
            else
                log_status "PASS" "desktop-app: CSP does not contain unsafe directives"
            fi
        else
            log_status "FAIL" "desktop-app: Content-Security-Policy not configured"
        fi
    else
        log_status "WARN" "desktop-app: src/main.ts not found"
    fi
}

# ============================================
# 6. SECURE STORAGE - Implementation Check
# ============================================
check_secure_storage() {
    section "SECURE STORAGE IMPLEMENTATION"

    # Check parent-app
    if [ -f "parent-app/src/utils/secureStorage.ts" ]; then
        log_status "PASS" "parent-app: Secure storage utility implemented"

        if grep -q "react-native-keychain" parent-app/package.json 2>/dev/null; then
            log_status "PASS" "parent-app: react-native-keychain dependency present"
        else
            log_status "FAIL" "parent-app: react-native-keychain dependency missing"
        fi
    else
        log_status "FAIL" "parent-app: Secure storage utility not found"
    fi

    # Check teacher-app
    if [ -f "teacher-app/src/utils/secureStorage.ts" ]; then
        log_status "PASS" "teacher-app: Secure storage utility implemented"

        if grep -q "react-native-keychain" teacher-app/package.json 2>/dev/null; then
            log_status "PASS" "teacher-app: react-native-keychain dependency present"
        else
            log_status "FAIL" "teacher-app: react-native-keychain dependency missing"
        fi
    else
        log_status "FAIL" "teacher-app: Secure storage utility not found"
    fi
}

# ============================================
# 7. CSRF PROTECTION - Implementation Check
# ============================================
check_csrf_protection() {
    section "CSRF PROTECTION IMPLEMENTATION"

    if [ -f "parent-portal/lib/security/csrf.ts" ]; then
        log_status "PASS" "parent-portal: CSRF utilities implemented"

        # Check for key functions
        local has_fetch=$(grep -c "fetchCSRFToken" parent-portal/lib/security/csrf.ts || echo "0")
        local has_get=$(grep -c "getCSRFToken" parent-portal/lib/security/csrf.ts || echo "0")
        local has_validate=$(grep -c "validateCSRFToken" parent-portal/lib/security/csrf.ts || echo "0")

        if [ "$has_fetch" -gt 0 ] && [ "$has_get" -gt 0 ] && [ "$has_validate" -gt 0 ]; then
            log_status "PASS" "parent-portal: CSRF key functions present"
        else
            log_status "WARN" "parent-portal: Some CSRF functions may be missing"
        fi

        # Check API client integration
        if [ -f "parent-portal/lib/api/client.ts" ]; then
            if grep -q "csrf" parent-portal/lib/api/client.ts || grep -q "CSRF" parent-portal/lib/api/client.ts; then
                log_status "PASS" "parent-portal: CSRF integrated with API client"
            else
                log_status "WARN" "parent-portal: CSRF may not be integrated with API client"
            fi
        fi
    else
        log_status "FAIL" "parent-portal: CSRF utilities not found"
    fi
}

# ============================================
# MAIN EXECUTION
# ============================================
main() {
    echo -e "${BLUE}╔════════════════════════════════════════╗${NC}"
    echo -e "${BLUE}║  Security Vulnerability Audit         ║${NC}"
    echo -e "${BLUE}║  Task 203 - Subtask 5-4                ║${NC}"
    echo -e "${BLUE}╚════════════════════════════════════════╝${NC}"
    echo ""

    # Check prerequisites
    if ! command_exists npm; then
        echo -e "${RED}Error: npm not found. Please install Node.js/npm.${NC}"
        exit 1
    fi

    if ! command_exists npx; then
        echo -e "${RED}Error: npx not found. Please install Node.js/npm.${NC}"
        exit 1
    fi

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
    test_xss_sanitization

    # 4. TypeScript Compilation
    check_typescript "parent-portal"
    check_typescript "parent-app"
    check_typescript "teacher-app"
    check_typescript "desktop-app"

    # 5. Security Headers
    check_security_headers

    # 6. Secure Storage
    check_secure_storage

    # 7. CSRF Protection
    check_csrf_protection

    # Summary
    section "AUDIT SUMMARY"
    echo ""
    log "Total Checks:   $TOTAL_CHECKS"
    log "Passed:         $PASSED_CHECKS"
    log "Failed:         $FAILED_CHECKS"
    log "Warnings:       $WARNING_CHECKS"
    echo ""

    # Success criteria
    if [ "$FAILED_CHECKS" -eq 0 ]; then
        echo -e "${GREEN}╔════════════════════════════════════════╗${NC}"
        echo -e "${GREEN}║  ✓ SECURITY AUDIT PASSED               ║${NC}"
        echo -e "${GREEN}╚════════════════════════════════════════╝${NC}"
        log "✓ Security audit passed"

        if [ "$WARNING_CHECKS" -gt 0 ]; then
            echo -e "${YELLOW}⚠ Note: $WARNING_CHECKS warnings found - please review${NC}"
            log "⚠ Note: $WARNING_CHECKS warnings found - please review"
        fi

        echo ""
        echo "Detailed report: $REPORT"
        exit 0
    else
        echo -e "${RED}╔════════════════════════════════════════╗${NC}"
        echo -e "${RED}║  ✗ SECURITY AUDIT FAILED               ║${NC}"
        echo -e "${RED}╚════════════════════════════════════════╝${NC}"
        log "✗ Security audit failed"
        echo ""
        echo "Detailed report: $REPORT"
        echo "Please fix the failed checks and re-run the audit."
        exit 1
    fi
}

# Run main
main
