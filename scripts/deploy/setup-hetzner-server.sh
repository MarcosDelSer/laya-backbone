#!/usr/bin/env bash
#
# setup-hetzner-server.sh
# Bootstrap script for setting up a Hetzner server for LAYA backend services
#
# Usage:
#   ./setup-hetzner-server.sh <server-ip>
#   ./setup-hetzner-server.sh 192.168.1.100
#
# Prerequisites:
#   - SSH access to the server (root or sudo user)
#   - SSH key configured for passwordless login
#   - Ubuntu 22.04 LTS or Debian 12 on the server
#

set -euo pipefail

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SSH_USER="${SSH_USER:-root}"
SSH_PORT="${SSH_PORT:-22}"
DOCKER_COMPOSE_VERSION="v2.24.5"

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

# Print usage
usage() {
    echo "Usage: $0 <server-ip>"
    echo ""
    echo "Arguments:"
    echo "  server-ip    IP address or hostname of the Hetzner server"
    echo ""
    echo "Environment Variables:"
    echo "  SSH_USER     SSH username (default: root)"
    echo "  SSH_PORT     SSH port (default: 22)"
    echo ""
    echo "Examples:"
    echo "  $0 192.168.1.100"
    echo "  SSH_USER=admin $0 myserver.example.com"
    exit 1
}

# Validate arguments
validate_args() {
    if [[ $# -lt 1 ]]; then
        log_error "Missing required argument: server-ip"
        usage
    fi

    SERVER_IP="$1"
}

# Test SSH connection
test_ssh_connection() {
    log_info "Testing SSH connection to $SERVER_IP..."

    if ! ssh -o ConnectTimeout=10 -o BatchMode=yes -p "$SSH_PORT" "$SSH_USER@$SERVER_IP" "echo 'SSH connection successful'" 2>/dev/null; then
        log_error "Cannot connect to server via SSH."
        echo ""
        echo "Please ensure:"
        echo "  1. Server is running and accessible"
        echo "  2. SSH key is added to the server's authorized_keys"
        echo "  3. SSH port ($SSH_PORT) is open"
        echo "  4. SSH user ($SSH_USER) has login permissions"
        echo ""
        echo "To add your SSH key to the server:"
        echo "  ssh-copy-id -p $SSH_PORT $SSH_USER@$SERVER_IP"
        exit 1
    fi

    log_success "SSH connection verified"
}

# Execute command on remote server
remote_exec() {
    ssh -p "$SSH_PORT" "$SSH_USER@$SERVER_IP" "$@"
}

# Update system packages
update_system() {
    log_info "Updating system packages..."

    remote_exec "DEBIAN_FRONTEND=noninteractive apt-get update && apt-get upgrade -y"

    log_success "System packages updated"
}

# Install required dependencies
install_dependencies() {
    log_info "Installing required dependencies..."

    remote_exec "DEBIAN_FRONTEND=noninteractive apt-get install -y \
        curl \
        wget \
        git \
        ca-certificates \
        gnupg \
        lsb-release \
        ufw \
        htop \
        vim \
        jq \
        unzip"

    log_success "Dependencies installed"
}

# Install Docker
install_docker() {
    log_info "Checking Docker installation..."

    if remote_exec "command -v docker" &>/dev/null; then
        local docker_version
        docker_version=$(remote_exec "docker --version" 2>/dev/null || echo "unknown")
        log_success "Docker already installed: $docker_version"
        return 0
    fi

    log_info "Installing Docker..."

    # Install Docker using official script
    remote_exec "curl -fsSL https://get.docker.com | sh"

    # Start and enable Docker
    remote_exec "systemctl start docker && systemctl enable docker"

    # Add user to docker group (if not root)
    if [[ "$SSH_USER" != "root" ]]; then
        remote_exec "usermod -aG docker $SSH_USER" || true
    fi

    log_success "Docker installed successfully"
}

# Install Docker Compose
install_docker_compose() {
    log_info "Checking Docker Compose installation..."

    if remote_exec "docker compose version" &>/dev/null; then
        local compose_version
        compose_version=$(remote_exec "docker compose version --short" 2>/dev/null || echo "unknown")
        log_success "Docker Compose already installed: $compose_version"
        return 0
    fi

    log_info "Installing Docker Compose..."

    remote_exec "mkdir -p /usr/local/lib/docker/cli-plugins"
    remote_exec "curl -SL https://github.com/docker/compose/releases/download/$DOCKER_COMPOSE_VERSION/docker-compose-linux-x86_64 -o /usr/local/lib/docker/cli-plugins/docker-compose"
    remote_exec "chmod +x /usr/local/lib/docker/cli-plugins/docker-compose"

    log_success "Docker Compose installed successfully"
}

# Configure firewall
configure_firewall() {
    log_info "Configuring firewall (UFW)..."

    # Allow SSH
    remote_exec "ufw allow $SSH_PORT/tcp"

    # Allow HTTP/HTTPS
    remote_exec "ufw allow 80/tcp"
    remote_exec "ufw allow 443/tcp"

    # Allow Docker Swarm ports (if needed later)
    # remote_exec "ufw allow 2377/tcp"
    # remote_exec "ufw allow 7946/tcp"
    # remote_exec "ufw allow 7946/udp"
    # remote_exec "ufw allow 4789/udp"

    # Enable firewall
    remote_exec "ufw --force enable"

    log_success "Firewall configured"
}

# Create application directories
create_app_directories() {
    log_info "Creating application directories..."

    remote_exec "mkdir -p /opt/laya/{ai-service,gibbon,nginx,data}"
    remote_exec "mkdir -p /opt/laya/data/{postgres,redis}"
    remote_exec "chmod -R 755 /opt/laya"

    log_success "Application directories created at /opt/laya/"
}

# Setup swap space (recommended for smaller instances)
setup_swap() {
    log_info "Checking swap space..."

    if remote_exec "swapon --show | grep -q swap"; then
        log_success "Swap space already configured"
        return 0
    fi

    log_info "Setting up 2GB swap space..."

    remote_exec "fallocate -l 2G /swapfile || dd if=/dev/zero of=/swapfile bs=1M count=2048"
    remote_exec "chmod 600 /swapfile"
    remote_exec "mkswap /swapfile"
    remote_exec "swapon /swapfile"
    remote_exec "echo '/swapfile none swap sw 0 0' >> /etc/fstab"

    log_success "Swap space configured"
}

# Verify installation
verify_installation() {
    log_info "Verifying installation..."

    echo ""
    echo "=========================================="
    echo "INSTALLATION VERIFICATION"
    echo "=========================================="
    echo ""

    # Check Docker
    if remote_exec "docker --version" &>/dev/null; then
        echo -e "${GREEN}[OK]${NC} Docker: $(remote_exec 'docker --version')"
    else
        echo -e "${RED}[FAIL]${NC} Docker not found"
    fi

    # Check Docker Compose
    if remote_exec "docker compose version" &>/dev/null; then
        echo -e "${GREEN}[OK]${NC} Docker Compose: $(remote_exec 'docker compose version --short')"
    else
        echo -e "${RED}[FAIL]${NC} Docker Compose not found"
    fi

    # Check firewall
    if remote_exec "ufw status | grep -q 'Status: active'"; then
        echo -e "${GREEN}[OK]${NC} UFW firewall: active"
    else
        echo -e "${YELLOW}[WARN]${NC} UFW firewall: inactive"
    fi

    # Check directories
    if remote_exec "test -d /opt/laya"; then
        echo -e "${GREEN}[OK]${NC} Application directory: /opt/laya/"
    else
        echo -e "${RED}[FAIL]${NC} Application directory not created"
    fi

    echo ""
}

# Show next steps
show_next_steps() {
    echo ""
    echo "=========================================="
    echo "SERVER SETUP COMPLETE"
    echo "=========================================="
    echo ""
    echo "Your Hetzner server is ready for LAYA deployment!"
    echo ""
    echo "Server: $SERVER_IP"
    echo "App Directory: /opt/laya/"
    echo ""
    echo "NEXT STEPS:"
    echo ""
    echo "1. Copy your docker-compose.yml to the server:"
    echo "   scp -P $SSH_PORT docker-compose.yml $SSH_USER@$SERVER_IP:/opt/laya/"
    echo ""
    echo "2. Copy environment files:"
    echo "   scp -P $SSH_PORT .env.production $SSH_USER@$SERVER_IP:/opt/laya/.env"
    echo ""
    echo "3. SSH into the server and start services:"
    echo "   ssh -p $SSH_PORT $SSH_USER@$SERVER_IP"
    echo "   cd /opt/laya && docker compose up -d"
    echo ""
    echo "4. Set up SSL with Let's Encrypt (optional):"
    echo "   See docs/deployment/vercel-hetzner-deploy.md"
    echo ""
    echo "5. Run post-deploy checks:"
    echo "   ./scripts/deploy/post-deploy-checks.sh https://your-domain.com"
    echo ""
    echo "=========================================="
}

# Rollback function (in case of failure)
rollback() {
    log_warn "Rolling back changes..."

    # This is a minimal rollback - mainly disables firewall to prevent lockout
    remote_exec "ufw disable" || true

    log_info "Rollback completed. Firewall disabled."
}

# Main function
main() {
    echo ""
    echo "=========================================="
    echo "LAYA - Hetzner Server Setup"
    echo "=========================================="
    echo ""

    validate_args "$@"

    # Set up error handling for rollback
    trap rollback ERR

    test_ssh_connection
    update_system
    install_dependencies
    install_docker
    install_docker_compose
    setup_swap
    configure_firewall
    create_app_directories

    # Remove trap after successful completion
    trap - ERR

    verify_installation
    show_next_steps

    log_success "Hetzner server setup completed successfully!"
}

# Run main function
main "$@"
