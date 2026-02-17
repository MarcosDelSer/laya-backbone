#!/bin/bash
################################################################################
# MySQL Backup Script for LAYA Daycare Management System
#
# Description:
#   Creates compressed MySQL database backups using mysqldump with
#   --single-transaction flag for consistent backups without table locking.
#   Scheduled to run daily at 2:00 AM via cron.
#
# Features:
#   - Single-transaction backup (InnoDB-safe, no locking)
#   - Gzip compression to save storage space
#   - Timestamped backup files
#   - Error handling and logging
#   - Notification on failure
#
# Usage:
#   ./mysql_backup.sh
#
# Cron schedule (add to crontab):
#   0 2 * * * /path/to/scripts/backup/mysql_backup.sh >> /var/log/mysql_backup.log 2>&1
#
# Environment variables required:
#   MYSQL_HOST - MySQL server hostname (default: localhost)
#   MYSQL_PORT - MySQL server port (default: 3306)
#   MYSQL_USER - MySQL username
#   MYSQL_PASSWORD - MySQL password
#   MYSQL_DATABASE - Database name to backup
#   BACKUP_DIR - Directory to store backups (default: /var/backups/mysql)
#
################################################################################

set -euo pipefail

# Configuration
MYSQL_HOST="${MYSQL_HOST:-localhost}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"
MYSQL_DATABASE="${MYSQL_DATABASE:-laya_db}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/mysql}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/${MYSQL_DATABASE}_${TIMESTAMP}.sql.gz"
LOG_FILE="${BACKUP_DIR}/mysql_backup.log"

# Ensure backup directory exists for logging (created early to allow logging to work)
mkdir -p "$BACKUP_DIR" 2>/dev/null || true

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Error handler
error_exit() {
    log "${RED}ERROR: $1${NC}"
    # Send notification (can be extended with email/Slack integration)
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

# Main backup function
main() {
    log "==================================="
    log "Starting MySQL backup"
    log "==================================="

    # Validate environment variables
    if [ -z "$MYSQL_PASSWORD" ]; then
        error_exit "MYSQL_PASSWORD environment variable is not set"
    fi

    if [ -z "$MYSQL_DATABASE" ]; then
        error_exit "MYSQL_DATABASE environment variable is not set"
    fi

    # Create backup directory if it doesn't exist
    if [ ! -d "$BACKUP_DIR" ]; then
        log "Creating backup directory: $BACKUP_DIR"
        mkdir -p "$BACKUP_DIR" || error_exit "Failed to create backup directory"
    fi

    # Check if mysqldump is available
    if ! command -v mysqldump &> /dev/null; then
        error_exit "mysqldump command not found. Please install MySQL client tools."
    fi

    # Check if gzip is available
    if ! command -v gzip &> /dev/null; then
        error_exit "gzip command not found. Please install gzip."
    fi

    # Check MySQL connectivity
    log "Testing MySQL connection..."
    if ! mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SELECT 1" &> /dev/null; then
        error_exit "Cannot connect to MySQL server at $MYSQL_HOST:$MYSQL_PORT"
    fi
    success "MySQL connection successful"

    # Check if database exists
    log "Verifying database exists..."
    if ! mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "USE $MYSQL_DATABASE" &> /dev/null; then
        error_exit "Database $MYSQL_DATABASE does not exist"
    fi
    success "Database $MYSQL_DATABASE verified"

    # Get database size
    DB_SIZE=$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size_MB'
        FROM information_schema.tables
        WHERE table_schema = '$MYSQL_DATABASE'
    " -N)
    # Handle NULL/empty result for empty databases
    if [ -z "$DB_SIZE" ] || [ "$DB_SIZE" = "NULL" ]; then
        DB_SIZE="0"
    fi
    log "Database size: ${DB_SIZE} MB"

    # Check available disk space
    AVAILABLE_SPACE=$(df -m "$BACKUP_DIR" | tail -1 | awk '{print $4}')
    # Convert DB_SIZE (which may be decimal) to integer for arithmetic
    REQUIRED_SPACE=$(echo "$DB_SIZE" | awk '{printf "%.0f", $1 * 2}') # Rough estimate with compression

    if [ "$AVAILABLE_SPACE" -lt "$REQUIRED_SPACE" ]; then
        warning "Low disk space: ${AVAILABLE_SPACE}MB available, ~${REQUIRED_SPACE}MB required"
    fi

    # Perform backup
    log "Starting backup to: $BACKUP_FILE"
    log "Using --single-transaction for consistent backup without locking"

    START_TIME=$(date +%s)

    # Run mysqldump with options:
    # --single-transaction: Consistent backup for InnoDB without locking
    # --quick: Retrieve rows one at a time (memory efficient)
    # --lock-tables=false: Don't lock tables (using --single-transaction instead)
    # --routines: Include stored procedures and functions
    # --triggers: Include triggers
    # --events: Include scheduled events
    # --hex-blob: Dump binary columns using hexadecimal notation
    # --default-character-set=utf8mb4: Use UTF-8 character set

    if mysqldump \
        -h "$MYSQL_HOST" \
        -P "$MYSQL_PORT" \
        -u "$MYSQL_USER" \
        -p"$MYSQL_PASSWORD" \
        --single-transaction \
        --quick \
        --lock-tables=false \
        --routines \
        --triggers \
        --events \
        --hex-blob \
        --default-character-set=utf8mb4 \
        "$MYSQL_DATABASE" | gzip > "$BACKUP_FILE"; then

        END_TIME=$(date +%s)
        DURATION=$((END_TIME - START_TIME))

        # Get backup file size
        BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)

        success "Backup completed successfully"
        log "Backup duration: ${DURATION} seconds"
        log "Backup file: $BACKUP_FILE"
        log "Backup size: $BACKUP_SIZE"

        # Verify backup file is not empty
        if [ ! -s "$BACKUP_FILE" ]; then
            error_exit "Backup file is empty"
        fi

        # Test gzip integrity
        log "Verifying backup file integrity..."
        if gzip -t "$BACKUP_FILE" 2>&1; then
            success "Backup file integrity verified"
        else
            error_exit "Backup file is corrupted"
        fi

        # Set secure permissions
        chmod 600 "$BACKUP_FILE"
        log "Set secure permissions (600) on backup file"

        log "==================================="
        log "Backup completed successfully"
        log "==================================="

    else
        error_exit "mysqldump failed"
    fi
}

# Run main function
main "$@"
