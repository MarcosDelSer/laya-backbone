#!/bin/bash
################################################################################
# Photo and Upload Backup Script for LAYA Daycare Management System
#
# Description:
#   Backs up photos and uploaded files using rsync with --delete flag.
#   This ensures the backup directory is an exact mirror of the source,
#   removing files from backup that have been deleted from the source.
#   Scheduled to run daily at 3:30 AM via cron.
#
# Features:
#   - Rsync with --delete for exact mirroring
#   - Incremental transfers (only changed files)
#   - Bandwidth limiting option
#   - Dry-run mode for testing
#   - Comprehensive error handling and logging
#   - Pre-flight checks (connectivity, disk space, directory existence)
#   - Support for local and remote backup destinations
#   - Progress reporting with transfer statistics
#   - Secure file permissions
#
# Usage:
#   ./photo_backup.sh [--dry-run] [--verify]
#
# Cron schedule (add to crontab):
#   30 3 * * * /path/to/scripts/backup/photo_backup.sh >> /var/log/photo_backup.log 2>&1
#
# Environment variables required:
#   PHOTOS_SOURCE_DIR - Source directory containing photos (default: /var/www/laya/uploads/photos)
#   UPLOADS_SOURCE_DIR - Source directory containing uploads (default: /var/www/laya/uploads/files)
#   PHOTOS_BACKUP_DIR - Backup destination for photos (default: /var/backups/photos)
#   UPLOADS_BACKUP_DIR - Backup destination for uploads (default: /var/backups/uploads)
#   RSYNC_BANDWIDTH - Bandwidth limit in KB/s (optional)
#   MIN_DISK_SPACE_GB - Minimum free disk space required in GB (default: 5)
#
################################################################################

set -euo pipefail

# Configuration
PHOTOS_SOURCE_DIR="${PHOTOS_SOURCE_DIR:-/var/www/laya/uploads/photos}"
UPLOADS_SOURCE_DIR="${UPLOADS_SOURCE_DIR:-/var/www/laya/uploads/files}"
PHOTOS_BACKUP_DIR="${PHOTOS_BACKUP_DIR:-/var/backups/photos}"
UPLOADS_BACKUP_DIR="${UPLOADS_BACKUP_DIR:-/var/backups/uploads}"
LOG_FILE="${LOG_FILE:-/var/log/photo_backup.log}"
MIN_DISK_SPACE_GB="${MIN_DISK_SPACE_GB:-5}"
RSYNC_BANDWIDTH="${RSYNC_BANDWIDTH:-}"

# Parse command line arguments
DRY_RUN=false
VERIFY_ONLY=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --verify)
            VERIFY_ONLY=true
            shift
            ;;
        --help)
            cat << EOF
Usage: $0 [OPTIONS]

Backup photos and uploaded files using rsync with --delete flag.

OPTIONS:
    --dry-run           Test mode - show what would be transferred without actually syncing
    --verify            Verify existing backups (compare source and destination)
    --help              Show this help message

EXAMPLES:
    # Run photo and upload backup
    $0

    # Test backup without transferring (dry-run)
    $0 --dry-run

    # Verify existing backups
    $0 --verify

ENVIRONMENT VARIABLES:
    PHOTOS_SOURCE_DIR    - Source directory for photos (default: /var/www/laya/uploads/photos)
    UPLOADS_SOURCE_DIR   - Source directory for uploads (default: /var/www/laya/uploads/files)
    PHOTOS_BACKUP_DIR    - Backup destination for photos (default: /var/backups/photos)
    UPLOADS_BACKUP_DIR   - Backup destination for uploads (default: /var/backups/uploads)
    RSYNC_BANDWIDTH      - Bandwidth limit in KB/s (optional)
    MIN_DISK_SPACE_GB    - Minimum free disk space in GB (default: 5)
    LOG_FILE             - Log file path (default: /var/log/photo_backup.log)
EOF
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
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

# Check if command exists
command_exists() {
    command -v "$1" &> /dev/null
}

# Check disk space
check_disk_space() {
    local backup_dir=$1
    local backup_parent=$(dirname "$backup_dir")

    # Create backup directory if it doesn't exist
    if [ ! -d "$backup_dir" ]; then
        mkdir -p "$backup_dir" || error_exit "Failed to create backup directory: $backup_dir"
        chmod 700 "$backup_dir"
    fi

    # Check available disk space
    if command_exists df; then
        local available_space

        # macOS vs Linux df differences
        if [[ "$OSTYPE" == "darwin"* ]]; then
            # macOS: df -g gives gigabytes
            available_space=$(df -g "$backup_parent" | awk 'NR==2 {print $4}')
        else
            # Linux: df -BG gives gigabytes
            available_space=$(df -BG "$backup_parent" | awk 'NR==2 {print $4}' | sed 's/G//')
        fi

        if [ "$available_space" -lt "$MIN_DISK_SPACE_GB" ]; then
            error_exit "Insufficient disk space. Available: ${available_space}GB, Required: ${MIN_DISK_SPACE_GB}GB"
        fi

        info "Available disk space: ${available_space}GB"
    fi
}

# Verify backup integrity
verify_backup() {
    local source_dir=$1
    local backup_dir=$2
    local name=$3

    log "-----------------------------------"
    log "Verifying $name backup"
    log "-----------------------------------"

    if [ ! -d "$source_dir" ]; then
        warning "Source directory does not exist: $source_dir"
        return 1
    fi

    if [ ! -d "$backup_dir" ]; then
        warning "Backup directory does not exist: $backup_dir"
        return 1
    fi

    log "Comparing $source_dir with $backup_dir"

    # Use rsync in dry-run mode to check differences
    local rsync_cmd="rsync -avzn --delete --stats"
    rsync_cmd="$rsync_cmd '$source_dir/' '$backup_dir/'"

    local output
    output=$(eval "$rsync_cmd" 2>&1)

    # Check if any files would be transferred
    if echo "$output" | grep -q "Number of files transferred: 0"; then
        success "$name backup is in sync with source"
        return 0
    else
        warning "$name backup is out of sync with source"
        echo "$output"
        return 1
    fi
}

# Backup directory using rsync
backup_directory() {
    local source_dir=$1
    local backup_dir=$2
    local name=$3

    log "-----------------------------------"
    log "Backing up $name"
    log "-----------------------------------"

    # Validate source directory
    if [ ! -d "$source_dir" ]; then
        warning "Source directory does not exist: $source_dir. Skipping $name backup."
        return 0
    fi

    log "Source: $source_dir"
    log "Destination: $backup_dir"

    # Get source directory size
    local source_size
    if command_exists du; then
        if [[ "$OSTYPE" == "darwin"* ]]; then
            source_size=$(du -sh "$source_dir" | cut -f1)
        else
            source_size=$(du -sh "$source_dir" | cut -f1)
        fi
        info "Source size: $source_size"
    fi

    # Check disk space
    check_disk_space "$backup_dir"

    # Build rsync command
    local rsync_cmd="rsync -avz --delete --stats"

    # Add bandwidth limit if specified
    if [ -n "$RSYNC_BANDWIDTH" ]; then
        rsync_cmd="$rsync_cmd --bwlimit=$RSYNC_BANDWIDTH"
        info "Bandwidth limited to ${RSYNC_BANDWIDTH} KB/s"
    fi

    # Add dry-run if specified
    if [ "$DRY_RUN" = true ]; then
        rsync_cmd="$rsync_cmd --dry-run"
        info "DRY RUN MODE - No files will be transferred"
    fi

    # Add progress reporting
    rsync_cmd="$rsync_cmd --progress"

    # Add source and destination
    rsync_cmd="$rsync_cmd '$source_dir/' '$backup_dir/'"

    log "Executing: rsync with --delete flag"

    START_TIME=$(date +%s)

    # Execute rsync and capture output
    local rsync_output
    if rsync_output=$(eval "$rsync_cmd" 2>&1); then
        END_TIME=$(date +%s)
        DURATION=$((END_TIME - START_TIME))

        # Extract statistics from rsync output
        local files_transferred=$(echo "$rsync_output" | grep "Number of files transferred:" | awk '{print $5}' || echo "N/A")
        local total_size=$(echo "$rsync_output" | grep "Total transferred file size:" | awk '{print $5, $6}' || echo "N/A")

        success "$name backup completed in ${DURATION} seconds"
        info "Files transferred: $files_transferred"
        info "Total size: $total_size"

        # Set secure permissions on backup directory
        chmod 700 "$backup_dir"

        return 0
    else
        error_exit "$name backup failed: $rsync_output"
    fi
}

# Pre-flight checks
preflight_checks() {
    log "Running pre-flight checks..."

    # Check if rsync is installed
    if ! command_exists rsync; then
        error_exit "rsync command not found. Please install rsync."
    fi

    success "rsync is installed"

    # Check rsync version
    local rsync_version=$(rsync --version | head -1)
    info "Rsync version: $rsync_version"

    # Verify at least one source directory exists
    local has_source=false

    if [ -d "$PHOTOS_SOURCE_DIR" ]; then
        has_source=true
        info "Photos source directory exists: $PHOTOS_SOURCE_DIR"
    else
        warning "Photos source directory does not exist: $PHOTOS_SOURCE_DIR"
    fi

    if [ -d "$UPLOADS_SOURCE_DIR" ]; then
        has_source=true
        info "Uploads source directory exists: $UPLOADS_SOURCE_DIR"
    else
        warning "Uploads source directory does not exist: $UPLOADS_SOURCE_DIR"
    fi

    if [ "$has_source" = false ]; then
        error_exit "No source directories found. Cannot proceed with backup."
    fi

    success "Pre-flight checks passed"
}

# Main backup function
main() {
    log "==================================="
    log "Starting photo and upload backup"
    log "==================================="

    if [ "$DRY_RUN" = true ]; then
        warning "DRY RUN MODE - No files will be transferred"
    fi

    # Run pre-flight checks
    preflight_checks

    # Verify mode
    if [ "$VERIFY_ONLY" = true ]; then
        log "==================================="
        log "Verification mode"
        log "==================================="

        verify_backup "$PHOTOS_SOURCE_DIR" "$PHOTOS_BACKUP_DIR" "Photos"
        verify_backup "$UPLOADS_SOURCE_DIR" "$UPLOADS_BACKUP_DIR" "Uploads"

        log "==================================="
        log "Verification completed"
        log "==================================="
        exit 0
    fi

    # Backup photos
    if [ -d "$PHOTOS_SOURCE_DIR" ]; then
        backup_directory "$PHOTOS_SOURCE_DIR" "$PHOTOS_BACKUP_DIR" "Photos"
    fi

    # Backup uploads
    if [ -d "$UPLOADS_SOURCE_DIR" ]; then
        backup_directory "$UPLOADS_SOURCE_DIR" "$UPLOADS_BACKUP_DIR" "Uploads"
    fi

    log "==================================="
    log "Photo and upload backup completed successfully"
    log "==================================="

    if [ "$DRY_RUN" = true ]; then
        info "This was a DRY RUN - no files were actually transferred"
    fi

    # Print backup summary
    log "Backup Summary:"
    log "  Photos backup: $PHOTOS_BACKUP_DIR"
    log "  Uploads backup: $UPLOADS_BACKUP_DIR"

    if command_exists du; then
        if [ -d "$PHOTOS_BACKUP_DIR" ]; then
            local photos_size=$(du -sh "$PHOTOS_BACKUP_DIR" 2>/dev/null | cut -f1 || echo "N/A")
            log "  Photos backup size: $photos_size"
        fi

        if [ -d "$UPLOADS_BACKUP_DIR" ]; then
            local uploads_size=$(du -sh "$UPLOADS_BACKUP_DIR" 2>/dev/null | cut -f1 || echo "N/A")
            log "  Uploads backup size: $uploads_size"
        fi
    fi
}

# Run main function
main "$@"
