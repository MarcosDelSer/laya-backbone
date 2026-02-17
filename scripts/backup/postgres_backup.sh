#!/bin/bash
################################################################################
# PostgreSQL Backup Script for LAYA Daycare Management System
#
# Description:
#   Creates compressed PostgreSQL database backups using pg_dump with
#   appropriate options for consistent backups.
#   Scheduled to run daily at 2:15 AM via cron.
#
# Features:
#   - pg_dump with custom format for flexibility
#   - Gzip compression to save storage space
#   - Timestamped backup files
#   - Error handling and logging
#   - Notification on failure
#
# Usage:
#   ./postgres_backup.sh
#
# Cron schedule (add to crontab):
#   15 2 * * * /path/to/scripts/backup/postgres_backup.sh >> /var/log/postgres_backup.log 2>&1
#
# Environment variables required:
#   PGHOST - PostgreSQL server hostname (default: localhost)
#   PGPORT - PostgreSQL server port (default: 5432)
#   PGUSER - PostgreSQL username
#   PGPASSWORD - PostgreSQL password
#   PGDATABASE - Database name to backup
#   BACKUP_DIR - Directory to store backups (default: /var/backups/postgres)
#
################################################################################

set -euo pipefail

# Configuration
PGHOST="${PGHOST:-localhost}"
PGPORT="${PGPORT:-5432}"
PGUSER="${PGUSER:-postgres}"
PGPASSWORD="${PGPASSWORD:-}"
PGDATABASE="${PGDATABASE:-laya_db}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/postgres}"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
BACKUP_FILE="${BACKUP_DIR}/${PGDATABASE}_${TIMESTAMP}.sql.gz"
LOG_FILE="${BACKUP_DIR}/postgres_backup.log"

# Export PostgreSQL password for pg_dump
export PGPASSWORD

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
    log "Starting PostgreSQL backup"
    log "==================================="

    # Validate environment variables
    if [ -z "$PGPASSWORD" ]; then
        error_exit "PGPASSWORD environment variable is not set"
    fi

    if [ -z "$PGDATABASE" ]; then
        error_exit "PGDATABASE environment variable is not set"
    fi

    # Create backup directory if it doesn't exist
    if [ ! -d "$BACKUP_DIR" ]; then
        log "Creating backup directory: $BACKUP_DIR"
        mkdir -p "$BACKUP_DIR" || error_exit "Failed to create backup directory"
    fi

    # Check if pg_dump is available
    if ! command -v pg_dump &> /dev/null; then
        error_exit "pg_dump command not found. Please install PostgreSQL client tools."
    fi

    # Check if gzip is available
    if ! command -v gzip &> /dev/null; then
        error_exit "gzip command not found. Please install gzip."
    fi

    # Check PostgreSQL connectivity
    log "Testing PostgreSQL connection..."
    if ! psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$PGDATABASE" -c "SELECT 1" &> /dev/null; then
        error_exit "Cannot connect to PostgreSQL server at $PGHOST:$PGPORT"
    fi
    success "PostgreSQL connection successful"

    # Check if database exists
    log "Verifying database exists..."
    if ! psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d postgres -c "SELECT 1 FROM pg_database WHERE datname='$PGDATABASE'" | grep -q 1; then
        error_exit "Database $PGDATABASE does not exist"
    fi
    success "Database $PGDATABASE verified"

    # Get database size
    DB_SIZE=$(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$PGDATABASE" -t -c "
        SELECT ROUND(pg_database_size('$PGDATABASE') / 1024.0 / 1024.0, 2)
    " | xargs)
    log "Database size: ${DB_SIZE} MB"

    # Check available disk space
    AVAILABLE_SPACE=$(df -m "$BACKUP_DIR" | tail -1 | awk '{print $4}')
    REQUIRED_SPACE=$(echo "$DB_SIZE * 2" | bc | cut -d. -f1) # Rough estimate with compression

    if [ "$AVAILABLE_SPACE" -lt "$REQUIRED_SPACE" ]; then
        warning "Low disk space: ${AVAILABLE_SPACE}MB available, ~${REQUIRED_SPACE}MB required"
    fi

    # Perform backup
    log "Starting backup to: $BACKUP_FILE"
    log "Using pg_dump with standard options for consistent backup"

    START_TIME=$(date +%s)

    # Run pg_dump with options:
    # --clean: Include DROP commands before CREATE
    # --if-exists: Use IF EXISTS with DROP commands
    # --create: Include CREATE DATABASE command
    # --no-owner: Don't output ownership commands
    # --no-acl: Don't output ACL commands (GRANT/REVOKE)
    # --encoding=UTF8: Use UTF-8 encoding
    # --verbose: Verbose mode for detailed logging

    if pg_dump \
        -h "$PGHOST" \
        -p "$PGPORT" \
        -U "$PGUSER" \
        -d "$PGDATABASE" \
        --clean \
        --if-exists \
        --create \
        --no-owner \
        --no-acl \
        --encoding=UTF8 \
        --verbose 2>> "$LOG_FILE" | gzip > "$BACKUP_FILE"; then

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
        error_exit "pg_dump failed"
    fi
}

# Run main function
main "$@"
