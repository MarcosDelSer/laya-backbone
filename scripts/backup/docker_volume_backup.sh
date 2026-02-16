#!/bin/bash
################################################################################
# Docker Volume Backup Script for LAYA Daycare Management System
#
# Description:
#   Creates compressed backups of all Docker volumes using docker run with
#   tar compression. Scheduled to run daily at 2:30 AM via cron.
#
# Features:
#   - Automatic detection of all Docker volumes
#   - Tar+gzip compression to save storage space
#   - Timestamped backup files
#   - Error handling and logging
#   - Pre-flight checks (Docker availability, disk space)
#   - Selective volume backup support
#   - Backup integrity verification
#
# Usage:
#   ./docker_volume_backup.sh                    # Backup all volumes
#   ./docker_volume_backup.sh volume_name        # Backup specific volume
#   ./docker_volume_backup.sh --list             # List all volumes
#
# Cron schedule (add to crontab):
#   30 2 * * * /path/to/scripts/backup/docker_volume_backup.sh >> /var/log/docker_volume_backup.log 2>&1
#
# Environment variables:
#   DOCKER_BACKUP_DIR - Directory to store backups (default: /var/backups/docker-volumes)
#   DOCKER_IMAGE - Docker image to use for backup (default: alpine:latest)
#   MIN_DISK_SPACE_GB - Minimum free disk space in GB (default: 5)
#
################################################################################

set -euo pipefail

# Configuration
DOCKER_BACKUP_DIR="${DOCKER_BACKUP_DIR:-/var/backups/docker-volumes}"
DOCKER_IMAGE="${DOCKER_IMAGE:-alpine:latest}"
MIN_DISK_SPACE_GB="${MIN_DISK_SPACE_GB:-5}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
LOG_FILE="${DOCKER_BACKUP_DIR}/docker_volume_backup.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Error handler
error_exit() {
    log "${RED}ERROR: $1${NC}"
    exit 1
}

# Success handler
success() {
    log "${GREEN}SUCCESS: $1${NC}"
}

# Warning handler
warning() {
    log "${YELLOW}WARNING: $1${NC}"
}

# Info handler
info() {
    log "${BLUE}INFO: $1${NC}"
}

# Show help
show_help() {
    cat << EOF
Docker Volume Backup Script for LAYA Daycare Management System

Usage:
  $0 [OPTIONS] [VOLUME_NAME]

Options:
  --list          List all Docker volumes and exit
  --help          Show this help message

Arguments:
  VOLUME_NAME     (Optional) Backup only the specified volume
                  If not provided, all volumes are backed up

Environment Variables:
  DOCKER_BACKUP_DIR     Directory to store backups (default: /var/backups/docker-volumes)
  DOCKER_IMAGE          Docker image for backup (default: alpine:latest)
  MIN_DISK_SPACE_GB     Minimum free disk space in GB (default: 5)

Examples:
  # Backup all Docker volumes
  $0

  # Backup a specific volume
  $0 my_volume_name

  # List all volumes
  $0 --list

Scheduled via cron:
  30 2 * * * /path/to/scripts/backup/docker_volume_backup.sh >> /var/log/docker_volume_backup.log 2>&1

EOF
}

# List all Docker volumes
list_volumes() {
    log "==================================="
    log "Docker Volumes"
    log "==================================="

    if ! docker volume ls --format "table {{.Name}}\t{{.Driver}}\t{{.Mountpoint}}" 2>/dev/null; then
        error_exit "Failed to list Docker volumes"
    fi

    exit 0
}

# Check if Docker is available and running
check_docker() {
    log "Checking Docker availability..."

    if ! command -v docker &> /dev/null; then
        error_exit "Docker is not installed or not in PATH"
    fi

    if ! docker info &> /dev/null; then
        error_exit "Docker daemon is not running or permission denied. Try: sudo usermod -aG docker \$USER"
    fi

    success "Docker is available and running"
}

# Check available disk space
check_disk_space() {
    log "Checking available disk space..."

    # Get available space in GB (cross-platform: macOS and Linux)
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        AVAILABLE_SPACE_GB=$(df -g "$DOCKER_BACKUP_DIR" 2>/dev/null | tail -1 | awk '{print $4}' || echo "0")
    else
        # Linux
        AVAILABLE_SPACE_GB=$(df -BG "$DOCKER_BACKUP_DIR" 2>/dev/null | tail -1 | awk '{print $4}' | sed 's/G//' || echo "0")
    fi

    if [ -z "$AVAILABLE_SPACE_GB" ] || [ "$AVAILABLE_SPACE_GB" = "0" ]; then
        warning "Could not determine available disk space"
        return
    fi

    if [ "$AVAILABLE_SPACE_GB" -lt "$MIN_DISK_SPACE_GB" ]; then
        warning "Low disk space: ${AVAILABLE_SPACE_GB}GB available, minimum ${MIN_DISK_SPACE_GB}GB recommended"
    else
        log "Available disk space: ${AVAILABLE_SPACE_GB}GB"
    fi
}

# Pull Docker image if not present
ensure_docker_image() {
    log "Ensuring Docker image is available: $DOCKER_IMAGE"

    if ! docker image inspect "$DOCKER_IMAGE" &> /dev/null; then
        log "Pulling Docker image: $DOCKER_IMAGE"
        if ! docker pull "$DOCKER_IMAGE" &> /dev/null; then
            error_exit "Failed to pull Docker image: $DOCKER_IMAGE"
        fi
    fi

    success "Docker image ready: $DOCKER_IMAGE"
}

# Backup a single Docker volume
backup_volume() {
    local volume_name="$1"
    local backup_file="${DOCKER_BACKUP_DIR}/${volume_name}_${TIMESTAMP}.tar.gz"

    log "-----------------------------------"
    log "Backing up volume: $volume_name"

    # Check if volume exists
    if ! docker volume inspect "$volume_name" &> /dev/null; then
        error_exit "Volume does not exist: $volume_name"
    fi

    # Get volume information
    local volume_driver=$(docker volume inspect --format '{{.Driver}}' "$volume_name" 2>/dev/null || echo "unknown")
    local volume_mountpoint=$(docker volume inspect --format '{{.Mountpoint}}' "$volume_name" 2>/dev/null || echo "unknown")

    info "Volume driver: $volume_driver"
    info "Volume mountpoint: $volume_mountpoint"

    # Create backup using docker run
    # This method works regardless of volume driver and permissions
    log "Creating backup: $backup_file"

    START_TIME=$(date +%s)

    # Run a temporary container that mounts the volume and creates a tar archive
    # --rm: Remove container after completion
    # -v: Mount the volume to /volume in the container
    # tar czf: Create compressed tar archive
    if docker run --rm \
        -v "${volume_name}:/volume:ro" \
        -v "${DOCKER_BACKUP_DIR}:/backup" \
        "$DOCKER_IMAGE" \
        tar czf "/backup/$(basename "$backup_file")" -C /volume . 2>&1 | tee -a "$LOG_FILE"; then

        END_TIME=$(date +%s)
        DURATION=$((END_TIME - START_TIME))

        # Get backup file size
        if [ -f "$backup_file" ]; then
            BACKUP_SIZE=$(du -h "$backup_file" | cut -f1)

            success "Volume backed up: $volume_name"
            log "Backup duration: ${DURATION} seconds"
            log "Backup file: $backup_file"
            log "Backup size: $BACKUP_SIZE"

            # Verify backup file is not empty
            if [ ! -s "$backup_file" ]; then
                error_exit "Backup file is empty: $backup_file"
            fi

            # Test tar.gz integrity
            log "Verifying backup file integrity..."
            if tar -tzf "$backup_file" > /dev/null 2>&1; then
                success "Backup file integrity verified"
            else
                error_exit "Backup file is corrupted: $backup_file"
            fi

            # Set secure permissions
            chmod 600 "$backup_file"
            log "Set secure permissions (600) on backup file"

            return 0
        else
            error_exit "Backup file was not created: $backup_file"
        fi
    else
        error_exit "Failed to backup volume: $volume_name"
    fi
}

# Get list of all Docker volumes
get_volumes() {
    docker volume ls --format "{{.Name}}" 2>/dev/null || echo ""
}

# Main backup function
main() {
    local specific_volume=""

    # Parse command line arguments
    while [[ $# -gt 0 ]]; do
        case $1 in
            --list)
                list_volumes
                ;;
            --help)
                show_help
                exit 0
                ;;
            -*)
                error_exit "Unknown option: $1. Use --help for usage information."
                ;;
            *)
                specific_volume="$1"
                shift
                ;;
        esac
    done

    log "==================================="
    log "Starting Docker Volume Backup"
    log "==================================="

    # Create backup directory if it doesn't exist
    if [ ! -d "$DOCKER_BACKUP_DIR" ]; then
        log "Creating backup directory: $DOCKER_BACKUP_DIR"
        mkdir -p "$DOCKER_BACKUP_DIR" || error_exit "Failed to create backup directory"
    fi

    # Pre-flight checks
    check_docker
    check_disk_space
    ensure_docker_image

    # Determine which volumes to backup
    local volumes_to_backup=""
    if [ -n "$specific_volume" ]; then
        log "Backing up specific volume: $specific_volume"
        volumes_to_backup="$specific_volume"
    else
        log "Discovering Docker volumes..."
        volumes_to_backup=$(get_volumes)

        if [ -z "$volumes_to_backup" ]; then
            warning "No Docker volumes found"
            log "==================================="
            log "Backup completed (no volumes)"
            log "==================================="
            exit 0
        fi

        local volume_count=$(echo "$volumes_to_backup" | wc -l | tr -d ' ')
        log "Found $volume_count Docker volume(s) to backup"
    fi

    # Backup each volume
    local success_count=0
    local failure_count=0
    local total_size=0

    for volume in $volumes_to_backup; do
        if backup_volume "$volume"; then
            ((success_count++))
        else
            ((failure_count++))
            warning "Failed to backup volume: $volume"
        fi
    done

    # Summary
    log "==================================="
    log "Backup Summary"
    log "==================================="
    log "Total volumes: $((success_count + failure_count))"
    log "Successful: $success_count"
    log "Failed: $failure_count"

    if [ -d "$DOCKER_BACKUP_DIR" ]; then
        TOTAL_BACKUP_SIZE=$(du -sh "$DOCKER_BACKUP_DIR" 2>/dev/null | cut -f1 || echo "unknown")
        log "Total backup size: $TOTAL_BACKUP_SIZE"
    fi

    log "==================================="

    if [ $failure_count -gt 0 ]; then
        error_exit "Some volume backups failed"
    else
        success "All Docker volume backups completed successfully"
    fi
}

# Run main function with all arguments
main "$@"
