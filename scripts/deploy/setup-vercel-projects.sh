#!/usr/bin/env bash
#
# setup-vercel-projects.sh
# Automates Vercel project linking and configuration for LAYA frontend services
#
# Usage: ./setup-vercel-projects.sh
#
# Prerequisites:
#   - Vercel CLI installed: npm install -g vercel
#   - Vercel account created and authenticated
#   - Git repository cloned locally
#

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"

# Logging functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Vercel CLI is installed
check_vercel_cli() {
    log_info "Checking Vercel CLI installation..."

    if ! command -v vercel &> /dev/null; then
        log_error "Vercel CLI is not installed."
        echo ""
        echo "Install it with: npm install -g vercel"
        echo "Or: yarn global add vercel"
        echo ""
        exit 1
    fi

    local version
    version=$(vercel --version 2>/dev/null || echo "unknown")
    log_success "Vercel CLI found (version: $version)"
}

# Check if user is logged in to Vercel
check_vercel_login() {
    log_info "Checking Vercel authentication..."

    if ! vercel whoami &> /dev/null; then
        log_warn "Not logged in to Vercel. Starting login process..."
        echo ""
        vercel login

        if ! vercel whoami &> /dev/null; then
            log_error "Vercel login failed. Please try again."
            exit 1
        fi
    fi

    local user
    user=$(vercel whoami 2>/dev/null)
    log_success "Authenticated as: $user"
}

# Link parent-portal to Vercel
link_parent_portal() {
    log_info "Linking parent-portal to Vercel..."

    local portal_dir="$PROJECT_ROOT/parent-portal"

    if [[ ! -d "$portal_dir" ]]; then
        log_error "parent-portal directory not found at: $portal_dir"
        exit 1
    fi

    cd "$portal_dir"

    # Check if already linked
    if [[ -d ".vercel" ]]; then
        log_warn "parent-portal is already linked to a Vercel project."
        echo ""
        read -p "Do you want to re-link? (y/N): " -n 1 -r
        echo ""
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            log_info "Skipping parent-portal linking."
            return 0
        fi
        rm -rf .vercel
    fi

    echo ""
    log_info "Starting Vercel project linking for parent-portal..."
    echo "You will be prompted to configure the project."
    echo ""

    # Run vercel link interactively
    vercel link

    log_success "parent-portal linked to Vercel successfully!"
}

# Display environment variable setup instructions
show_env_instructions() {
    log_info "Environment Variable Setup Instructions"
    echo ""
    echo "=========================================="
    echo "REQUIRED ENVIRONMENT VARIABLES FOR VERCEL"
    echo "=========================================="
    echo ""
    echo "Go to your Vercel project settings and add these environment variables:"
    echo ""
    echo "1. NEXT_PUBLIC_API_URL"
    echo "   Description: URL of the AI Service backend"
    echo "   Production: https://your-hetzner-server.example.com/api"
    echo "   Example: https://api.laya.example.com"
    echo ""
    echo "2. NEXT_PUBLIC_GIBBON_URL"
    echo "   Description: URL of the Gibbon CMS API"
    echo "   Production: https://your-hetzner-server.example.com/gibbon"
    echo "   Example: https://gibbon.laya.example.com"
    echo ""
    echo "You can set these via:"
    echo "  - Vercel Dashboard: Project Settings > Environment Variables"
    echo "  - Vercel CLI: vercel env add NEXT_PUBLIC_API_URL"
    echo ""
    echo "=========================================="
}

# Display next steps
show_next_steps() {
    echo ""
    echo "=========================================="
    echo "NEXT STEPS"
    echo "=========================================="
    echo ""
    echo "1. Set up environment variables in Vercel Dashboard"
    echo "   - Go to: https://vercel.com/dashboard"
    echo "   - Select your project"
    echo "   - Navigate to: Settings > Environment Variables"
    echo ""
    echo "2. Deploy to Vercel"
    echo "   Option A (Automatic): Push to your Git branch"
    echo "   Option B (Manual): Run 'vercel --prod' in parent-portal/"
    echo ""
    echo "3. Verify deployment"
    echo "   - Check the deployment URL"
    echo "   - Test authentication flow"
    echo "   - Verify API connectivity"
    echo ""
    echo "For detailed instructions, see:"
    echo "  docs/deployment/vercel-hetzner-deploy.md"
    echo ""
}

# Main function
main() {
    echo ""
    echo "=========================================="
    echo "LAYA - Vercel Project Setup"
    echo "=========================================="
    echo ""

    check_vercel_cli
    check_vercel_login
    link_parent_portal
    show_env_instructions
    show_next_steps

    log_success "Vercel project setup completed!"
}

# Run main function
main "$@"
