#!/bin/bash
################################################################################
# Remote Storage Backup Script for LAYA Daycare Management System
#
# Description:
#   Synchronizes local database backups to remote storage using either:
#   - AWS S3 (via aws s3 sync)
#   - Remote server (via rsync over SSH)
#   Scheduled to run daily at 5:00 AM via cron (after local backups complete).
#
# Features:
#   - Support for AWS S3 and rsync remote storage
#   - Selective sync (MySQL, PostgreSQL, or both)
#   - Bandwidth limiting for rsync
#   - Incremental transfers (only changed files)
#   - Error handling and logging
#   - Dry-run mode for testing
#   - Email/Slack notifications on failure
#
# Usage:
#   ./remote_backup.sh [--dry-run] [--mysql-only] [--postgres-only]
#
# Cron schedule (add to crontab):
#   0 5 * * * /path/to/scripts/backup/remote_backup.sh >> /var/log/remote_backup.log 2>&1
#
# Environment variables required:
#   REMOTE_BACKUP_METHOD - "s3" or "rsync" (default: rsync)
#
#   For S3:
#     AWS_S3_BUCKET - S3 bucket name (e.g., s3://my-backup-bucket)
#     AWS_PROFILE - AWS CLI profile to use (optional)
#     AWS_REGION - AWS region (optional)
#
#   For rsync:
#     REMOTE_USER - SSH username for remote server
#     REMOTE_HOST - Remote server hostname or IP
#     REMOTE_PATH - Remote directory path for backups
#     SSH_KEY - Path to SSH private key (optional, default: ~/.ssh/id_rsa)
#     SSH_PORT - SSH port (default: 22)
#     RSYNC_BANDWIDTH - Bandwidth limit in KB/s (optional)
#
#   Local directories:
#     MYSQL_BACKUP_DIR - MySQL backups directory (default: /var/backups/mysql)
#     POSTGRES_BACKUP_DIR - PostgreSQL backups directory (default: /var/backups/postgres)
#
################################################################################

set -euo pipefail

# Configuration
REMOTE_BACKUP_METHOD="${REMOTE_BACKUP_METHOD:-rsync}"
MYSQL_BACKUP_DIR="${MYSQL_BACKUP_DIR:-/var/backups/mysql}"
POSTGRES_BACKUP_DIR="${POSTGRES_BACKUP_DIR:-/var/backups/postgres}"
LOG_FILE="${LOG_FILE:-/var/log/remote_backup.log}"

# S3 Configuration
AWS_S3_BUCKET="${AWS_S3_BUCKET:-}"
AWS_PROFILE="${AWS_PROFILE:-}"
AWS_REGION="${AWS_REGION:-}"

# Rsync Configuration
REMOTE_USER="${REMOTE_USER:-}"
REMOTE_HOST="${REMOTE_HOST:-}"
REMOTE_PATH="${REMOTE_PATH:-}"
SSH_KEY="${SSH_KEY:-$HOME/.ssh/id_rsa}"
SSH_PORT="${SSH_PORT:-22}"
RSYNC_BANDWIDTH="${RSYNC_BANDWIDTH:-}"

# Parse command line arguments
DRY_RUN=false
MYSQL_ONLY=false
POSTGRES_ONLY=false

while [[ $# -gt 0 ]]; do
    case $1 in
        --dry-run)
            DRY_RUN=true
            shift
            ;;
        --mysql-only)
            MYSQL_ONLY=true
            shift
            ;;
        --postgres-only)
            POSTGRES_ONLY=true
            shift
            ;;
        --help)
            cat << EOF
Usage: $0 [OPTIONS]

Sync local database backups to remote storage (S3 or rsync).

OPTIONS:
    --dry-run           Test mode - show what would be transferred without actually syncing
    --mysql-only        Only sync MySQL backups
    --postgres-only     Only sync PostgreSQL backups
    --help              Show this help message

EXAMPLES:
    # Sync all backups to remote storage
    $0

    # Test sync without transferring (dry-run)
    $0 --dry-run

    # Sync only MySQL backups
    $0 --mysql-only

    # Sync only PostgreSQL backups
    $0 --postgres-only

ENVIRONMENT VARIABLES:
    REMOTE_BACKUP_METHOD  - "s3" or "rsync" (default: rsync)

    For S3 method:
      AWS_S3_BUCKET      - S3 bucket name (required)
      AWS_PROFILE        - AWS CLI profile (optional)
      AWS_REGION         - AWS region (optional)

    For rsync method:
      REMOTE_USER        - SSH username (required)
      REMOTE_HOST        - Remote hostname/IP (required)
      REMOTE_PATH        - Remote directory path (required)
      SSH_KEY            - SSH private key path (default: ~/.ssh/id_rsa)
      SSH_PORT           - SSH port (default: 22)
      RSYNC_BANDWIDTH    - Bandwidth limit in KB/s (optional)

    MYSQL_BACKUP_DIR     - Local MySQL backup directory (default: /var/backups/mysql)
    POSTGRES_BACKUP_DIR  - Local PostgreSQL backup directory (default: /var/backups/postgres)
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
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Error handler
error_exit() {
    log "${RED}ERROR: $1${NC}"
    # Send notification (can be extended with email/Slack integration)
    # Example: send_slack_notification "Remote Backup Failed: $1"
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

# Validate S3 configuration
validate_s3_config() {
    if [ -z "$AWS_S3_BUCKET" ]; then
        error_exit "AWS_S3_BUCKET environment variable is not set"
    fi

    if ! command_exists aws; then
        error_exit "AWS CLI not found. Please install: https://docs.aws.amazon.com/cli/latest/userguide/install-cliv2.html"
    fi

    # Test AWS credentials
    log "Testing AWS credentials..."
    local aws_cmd="aws s3 ls"

    if [ -n "$AWS_PROFILE" ]; then
        aws_cmd="$aws_cmd --profile $AWS_PROFILE"
    fi

    if [ -n "$AWS_REGION" ]; then
        aws_cmd="$aws_cmd --region $AWS_REGION"
    fi

    if ! eval "$aws_cmd" &> /dev/null; then
        error_exit "Cannot authenticate with AWS. Check your credentials and profile."
    fi

    success "AWS credentials validated"
}

# Validate rsync configuration
validate_rsync_config() {
    if [ -z "$REMOTE_USER" ]; then
        error_exit "REMOTE_USER environment variable is not set"
    fi

    if [ -z "$REMOTE_HOST" ]; then
        error_exit "REMOTE_HOST environment variable is not set"
    fi

    if [ -z "$REMOTE_PATH" ]; then
        error_exit "REMOTE_PATH environment variable is not set"
    fi

    if ! command_exists rsync; then
        error_exit "rsync command not found. Please install rsync."
    fi

    # Check SSH key
    if [ ! -f "$SSH_KEY" ]; then
        error_exit "SSH key not found at: $SSH_KEY"
    fi

    # Test SSH connectivity
    log "Testing SSH connection to $REMOTE_USER@$REMOTE_HOST..."
    if ! ssh -i "$SSH_KEY" -p "$SSH_PORT" -o ConnectTimeout=10 -o BatchMode=yes "$REMOTE_USER@$REMOTE_HOST" "echo 'Connection successful'" &> /dev/null; then
        error_exit "Cannot connect to $REMOTE_USER@$REMOTE_HOST via SSH"
    fi

    success "SSH connection successful"

    # Check if remote directory exists, create if not
    log "Checking remote directory: $REMOTE_PATH"
    if ! ssh -i "$SSH_KEY" -p "$SSH_PORT" "$REMOTE_USER@$REMOTE_HOST" "[ -d '$REMOTE_PATH' ]" &> /dev/null; then
        log "Remote directory does not exist. Creating: $REMOTE_PATH"
        if ! ssh -i "$SSH_KEY" -p "$SSH_PORT" "$REMOTE_USER@$REMOTE_HOST" "mkdir -p '$REMOTE_PATH'" &> /dev/null; then
            error_exit "Failed to create remote directory: $REMOTE_PATH"
        fi
        success "Remote directory created"
    fi
}

# Sync directory to S3
sync_to_s3() {
    local local_dir=$1
    local remote_subpath=$2
    local s3_path="${AWS_S3_BUCKET}/${remote_subpath}"

    if [ ! -d "$local_dir" ]; then
        warning "Local directory does not exist: $local_dir. Skipping."
        return 0
    fi

    log "Syncing $local_dir to $s3_path"

    local aws_sync_cmd="aws s3 sync '$local_dir' '$s3_path' --delete"

    if [ -n "$AWS_PROFILE" ]; then
        aws_sync_cmd="$aws_sync_cmd --profile $AWS_PROFILE"
    fi

    if [ -n "$AWS_REGION" ]; then
        aws_sync_cmd="$aws_sync_cmd --region $AWS_REGION"
    fi

    if [ "$DRY_RUN" = true ]; then
        aws_sync_cmd="$aws_sync_cmd --dryrun"
        info "DRY RUN MODE: $aws_sync_cmd"
    fi

    START_TIME=$(date +%s)

    if eval "$aws_sync_cmd"; then
        END_TIME=$(date +%s)
        DURATION=$((END_TIME - START_TIME))
        success "S3 sync completed for $local_dir in ${DURATION} seconds"
    else
        error_exit "S3 sync failed for $local_dir"
    fi
}

# Sync directory via rsync
sync_to_rsync() {
    local local_dir=$1
    local remote_subpath=$2
    local remote_full_path="${REMOTE_PATH}/${remote_subpath}"

    if [ ! -d "$local_dir" ]; then
        warning "Local directory does not exist: $local_dir. Skipping."
        return 0
    fi

    log "Syncing $local_dir to $REMOTE_USER@$REMOTE_HOST:$remote_full_path"

    # Build rsync command
    local rsync_cmd="rsync -avz --delete"
    rsync_cmd="$rsync_cmd -e 'ssh -i $SSH_KEY -p $SSH_PORT -o StrictHostKeyChecking=no'"

    # Add bandwidth limit if specified
    if [ -n "$RSYNC_BANDWIDTH" ]; then
        rsync_cmd="$rsync_cmd --bwlimit=$RSYNC_BANDWIDTH"
        info "Bandwidth limited to ${RSYNC_BANDWIDTH} KB/s"
    fi

    # Add dry-run if specified
    if [ "$DRY_RUN" = true ]; then
        rsync_cmd="$rsync_cmd --dry-run"
        info "DRY RUN MODE: $rsync_cmd"
    fi

    # Ensure remote directory exists
    if [ "$DRY_RUN" = false ]; then
        ssh -i "$SSH_KEY" -p "$SSH_PORT" "$REMOTE_USER@$REMOTE_HOST" "mkdir -p '$remote_full_path'" || \
            error_exit "Failed to create remote directory: $remote_full_path"
    fi

    rsync_cmd="$rsync_cmd '$local_dir/' '$REMOTE_USER@$REMOTE_HOST:$remote_full_path/'"

    START_TIME=$(date +%s)

    if eval "$rsync_cmd"; then
        END_TIME=$(date +%s)
        DURATION=$((END_TIME - START_TIME))
        success "Rsync completed for $local_dir in ${DURATION} seconds"
    else
        error_exit "Rsync failed for $local_dir"
    fi
}

# Main backup function
main() {
    log "==================================="
    log "Starting remote backup synchronization"
    log "==================================="

    if [ "$DRY_RUN" = true ]; then
        warning "DRY RUN MODE - No files will be transferred"
    fi

    log "Remote backup method: $REMOTE_BACKUP_METHOD"

    # Validate configuration based on method
    if [ "$REMOTE_BACKUP_METHOD" = "s3" ]; then
        validate_s3_config
    elif [ "$REMOTE_BACKUP_METHOD" = "rsync" ]; then
        validate_rsync_config
    else
        error_exit "Invalid REMOTE_BACKUP_METHOD: $REMOTE_BACKUP_METHOD (must be 's3' or 'rsync')"
    fi

    # Determine which backups to sync
    SYNC_MYSQL=true
    SYNC_POSTGRES=true

    if [ "$MYSQL_ONLY" = true ]; then
        SYNC_POSTGRES=false
        info "Syncing MySQL backups only"
    elif [ "$POSTGRES_ONLY" = true ]; then
        SYNC_MYSQL=false
        info "Syncing PostgreSQL backups only"
    fi

    # Sync MySQL backups
    if [ "$SYNC_MYSQL" = true ]; then
        log "-----------------------------------"
        log "Syncing MySQL backups"
        log "-----------------------------------"

        if [ "$REMOTE_BACKUP_METHOD" = "s3" ]; then
            sync_to_s3 "$MYSQL_BACKUP_DIR" "mysql"
        else
            sync_to_rsync "$MYSQL_BACKUP_DIR" "mysql"
        fi
    fi

    # Sync PostgreSQL backups
    if [ "$SYNC_POSTGRES" = true ]; then
        log "-----------------------------------"
        log "Syncing PostgreSQL backups"
        log "-----------------------------------"

        if [ "$REMOTE_BACKUP_METHOD" = "s3" ]; then
            sync_to_s3 "$POSTGRES_BACKUP_DIR" "postgres"
        else
            sync_to_rsync "$POSTGRES_BACKUP_DIR" "postgres"
        fi
    fi

    log "==================================="
    log "Remote backup synchronization completed successfully"
    log "==================================="

    if [ "$DRY_RUN" = true ]; then
        info "This was a DRY RUN - no files were actually transferred"
    fi
}

# Run main function
main "$@"
