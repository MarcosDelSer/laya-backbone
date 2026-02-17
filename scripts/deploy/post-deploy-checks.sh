#!/usr/bin/env bash
#
# post-deploy-checks.sh
# Post-deployment health checks for LAYA services
#
# Usage:
#   ./post-deploy-checks.sh <base-url>
#   ./post-deploy-checks.sh https://api.laya.example.com
#
# This script verifies:
#   - Health endpoints are responding
#   - SSL/TLS certificates are valid
#   - Response times are acceptable
#   - Services are properly configured
#

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
TIMEOUT="${TIMEOUT:-10}"
MAX_RESPONSE_TIME="${MAX_RESPONSE_TIME:-5000}" # milliseconds
RETRY_COUNT="${RETRY_COUNT:-3}"
RETRY_DELAY="${RETRY_DELAY:-2}"

# Test results tracking
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_WARNED=0

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[PASS]${NC} $1"
    ((TESTS_PASSED++)) || true
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
    ((TESTS_WARNED++)) || true
}

log_fail() {
    echo -e "${RED}[FAIL]${NC} $1"
    ((TESTS_FAILED++)) || true
}

# Print usage
usage() {
    echo "Usage: $0 <base-url> [frontend-url]"
    echo ""
    echo "Arguments:"
    echo "  base-url       Base URL of the backend API (required)"
    echo "  frontend-url   URL of the frontend (optional)"
    echo ""
    echo "Environment Variables:"
    echo "  TIMEOUT           Request timeout in seconds (default: 10)"
    echo "  MAX_RESPONSE_TIME Maximum acceptable response time in ms (default: 5000)"
    echo "  RETRY_COUNT       Number of retries for failed checks (default: 3)"
    echo ""
    echo "Examples:"
    echo "  $0 https://api.laya.example.com"
    echo "  $0 https://api.laya.example.com https://laya.vercel.app"
    exit 1
}

# Validate arguments
validate_args() {
    if [[ $# -lt 1 ]]; then
        log_fail "Missing required argument: base-url"
        usage
    fi

    BASE_URL="${1%/}"  # Remove trailing slash
    FRONTEND_URL="${2:-}"

    if [[ -n "$FRONTEND_URL" ]]; then
        FRONTEND_URL="${FRONTEND_URL%/}"
    fi
}

# Check if curl is available
check_dependencies() {
    if ! command -v curl &>/dev/null; then
        log_fail "curl is required but not installed"
        exit 1
    fi

    if ! command -v openssl &>/dev/null; then
        log_warn "openssl not found - SSL checks will be skipped"
    fi
}

# Make HTTP request with timing
http_check() {
    local url="$1"
    local expected_code="${2:-200}"
    local description="${3:-$url}"

    local response_code
    local response_time
    local attempt=1

    while [[ $attempt -le $RETRY_COUNT ]]; do
        # Get response code and time
        local result
        result=$(curl -s -o /dev/null -w "%{http_code}|%{time_total}" \
            --connect-timeout "$TIMEOUT" \
            --max-time "$TIMEOUT" \
            "$url" 2>/dev/null) || true

        response_code=$(echo "$result" | cut -d'|' -f1)
        response_time=$(echo "$result" | cut -d'|' -f2)

        # Convert response time to milliseconds
        response_time_ms=$(echo "$response_time * 1000" | bc 2>/dev/null || echo "0")
        response_time_ms=${response_time_ms%.*}

        if [[ "$response_code" == "$expected_code" ]]; then
            if [[ "$response_time_ms" -gt "$MAX_RESPONSE_TIME" ]]; then
                log_warn "$description - Response code: $response_code, Time: ${response_time_ms}ms (slow)"
            else
                log_success "$description - Response code: $response_code, Time: ${response_time_ms}ms"
            fi
            return 0
        fi

        if [[ $attempt -lt $RETRY_COUNT ]]; then
            sleep "$RETRY_DELAY"
        fi
        ((attempt++)) || true
    done

    log_fail "$description - Expected: $expected_code, Got: $response_code"
    return 1
}

# Check SSL certificate
ssl_check() {
    local url="$1"
    local domain
    domain=$(echo "$url" | sed -e 's|^[^/]*//||' -e 's|/.*$||')

    if ! command -v openssl &>/dev/null; then
        return 0
    fi

    log_info "Checking SSL certificate for $domain..."

    # Get certificate expiry
    local cert_info
    cert_info=$(echo | openssl s_client -servername "$domain" -connect "$domain:443" 2>/dev/null | openssl x509 -noout -dates 2>/dev/null) || true

    if [[ -z "$cert_info" ]]; then
        log_fail "SSL certificate check failed for $domain"
        return 1
    fi

    # Extract expiry date
    local expiry_date
    expiry_date=$(echo "$cert_info" | grep 'notAfter' | cut -d'=' -f2)

    if [[ -z "$expiry_date" ]]; then
        log_fail "Could not determine SSL certificate expiry for $domain"
        return 1
    fi

    # Check if certificate expires within 30 days
    local expiry_epoch
    local now_epoch
    local days_until_expiry

    expiry_epoch=$(date -j -f "%b %d %T %Y %Z" "$expiry_date" "+%s" 2>/dev/null || date -d "$expiry_date" "+%s" 2>/dev/null) || true
    now_epoch=$(date "+%s")

    if [[ -n "$expiry_epoch" ]]; then
        days_until_expiry=$(( (expiry_epoch - now_epoch) / 86400 ))

        if [[ $days_until_expiry -lt 0 ]]; then
            log_fail "SSL certificate for $domain has EXPIRED!"
        elif [[ $days_until_expiry -lt 7 ]]; then
            log_warn "SSL certificate for $domain expires in $days_until_expiry days!"
        elif [[ $days_until_expiry -lt 30 ]]; then
            log_warn "SSL certificate for $domain expires in $days_until_expiry days"
        else
            log_success "SSL certificate for $domain valid for $days_until_expiry days"
        fi
    else
        log_success "SSL certificate present for $domain (expiry: $expiry_date)"
    fi
}

# Check AI Service health
check_ai_service() {
    echo ""
    echo "=========================================="
    echo "AI SERVICE CHECKS"
    echo "=========================================="

    # Health endpoint
    http_check "$BASE_URL/health" "200" "AI Service Health"

    # API docs (if available)
    http_check "$BASE_URL/docs" "200" "AI Service API Docs" || true

    # OpenAPI spec (if available)
    http_check "$BASE_URL/openapi.json" "200" "AI Service OpenAPI Spec" || true
}

# Check Gibbon service
check_gibbon() {
    echo ""
    echo "=========================================="
    echo "GIBBON SERVICE CHECKS"
    echo "=========================================="

    # Gibbon endpoints typically under /gibbon path
    local gibbon_url="${GIBBON_URL:-$BASE_URL/gibbon}"

    # Basic availability check
    http_check "$gibbon_url" "200,302,301" "Gibbon Service" || true
}

# Check frontend
check_frontend() {
    if [[ -z "$FRONTEND_URL" ]]; then
        echo ""
        log_info "No frontend URL provided - skipping frontend checks"
        return 0
    fi

    echo ""
    echo "=========================================="
    echo "FRONTEND CHECKS"
    echo "=========================================="

    # Main page
    http_check "$FRONTEND_URL" "200" "Frontend Main Page"

    # SSL check
    if [[ "$FRONTEND_URL" == https://* ]]; then
        ssl_check "$FRONTEND_URL"
    fi
}

# Check SSL for backend
check_backend_ssl() {
    echo ""
    echo "=========================================="
    echo "SSL/TLS CHECKS"
    echo "=========================================="

    if [[ "$BASE_URL" == https://* ]]; then
        ssl_check "$BASE_URL"
    else
        log_warn "Backend URL is not HTTPS - SSL check skipped"
    fi
}

# Print summary
print_summary() {
    echo ""
    echo "=========================================="
    echo "DEPLOYMENT CHECK SUMMARY"
    echo "=========================================="
    echo ""
    echo -e "Passed:  ${GREEN}$TESTS_PASSED${NC}"
    echo -e "Warned:  ${YELLOW}$TESTS_WARNED${NC}"
    echo -e "Failed:  ${RED}$TESTS_FAILED${NC}"
    echo ""

    if [[ $TESTS_FAILED -gt 0 ]]; then
        echo -e "${RED}OVERALL STATUS: FAILED${NC}"
        echo ""
        echo "Some checks failed. Please review the issues above and fix them."
        return 1
    elif [[ $TESTS_WARNED -gt 0 ]]; then
        echo -e "${YELLOW}OVERALL STATUS: PASSED WITH WARNINGS${NC}"
        echo ""
        echo "Deployment is functional but some issues need attention."
        return 0
    else
        echo -e "${GREEN}OVERALL STATUS: PASSED${NC}"
        echo ""
        echo "All deployment checks passed successfully!"
        return 0
    fi
}

# Main function
main() {
    echo ""
    echo "=========================================="
    echo "LAYA - Post-Deployment Health Checks"
    echo "=========================================="
    echo ""

    validate_args "$@"
    check_dependencies

    log_info "Checking deployment at: $BASE_URL"
    if [[ -n "$FRONTEND_URL" ]]; then
        log_info "Frontend URL: $FRONTEND_URL"
    fi
    echo ""

    check_ai_service
    check_gibbon
    check_frontend
    check_backend_ssl

    print_summary
}

# Run main function
main "$@"
