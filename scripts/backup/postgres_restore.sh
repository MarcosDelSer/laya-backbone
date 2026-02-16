#!/bin/bash
################################################################################
# PostgreSQL Restore Script for LAYA Daycare Management System
#
# Description:
#   Restores PostgreSQL database from compressed backup files created by
#   postgres_backup.sh. Includes safety checks and confirmation prompts.
#
# Features:
#   - Interactive and non-interactive modes
#   - Safety confirmations before destructive operations
#   - Backup file integrity verification
#   - Pre-flight checks (connectivity, file existence)
#   - Comprehensive error handling and logging
#   - Progress reporting
#
# Usage:
#   # Interactive mode (with confirmation prompts)
#   ./postgres_restore.sh /path/to/backup/laya_db_20260216_021500.sql.gz
#
#   # Non-interactive mode (skip confirmations - USE WITH CAUTION)
#   ./postgres_restore.sh /path/to/backup/laya_db_20260216_021500.sql.gz --yes
#
#   # List available backups
#   ./postgres_restore.sh --list
#
# Environment variables required:
#   PGHOST - PostgreSQL server hostname (default: localhost)
#   PGPORT - PostgreSQL server port (default: 5432)
#   PGUSER - PostgreSQL username (needs CREATEDB privileges)
#   PGPASSWORD - PostgreSQL password
#   PGDATABASE - Database name to restore (default: extracted from filename)
#   BACKUP_DIR - Directory where backups are stored (default: /var/backups/postgres)
#
################################################################################

set -euo pipefail

# Configuration
PGHOST="${PGHOST:-localhost}"
PGPORT="${PGPORT:-5432}"
PGUSER="${PGUSER:-postgres}"
PGPASSWORD="${PGPASSWORD:-}"
PGDATABASE="${PGDATABASE:-}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/postgres}"
LOG_DIR="${LOG_DIR:-/var/log}"
LOG_FILE="${LOG_DIR}/postgres_restore.log"

# Export PostgreSQL password for psql/pg_restore
export PGPASSWORD

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Flags
SKIP_CONFIRMATION=false
LIST_BACKUPS=false

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

# Show usage
usage() {
    cat << EOF
Usage: $0 [OPTIONS] BACKUP_FILE

Restore PostgreSQL database from compressed backup file.

Arguments:
  BACKUP_FILE              Path to the backup file (.sql.gz)

Options:
  --yes, -y               Skip confirmation prompts (non-interactive mode)
  --list, -l              List available backup files
  --help, -h              Show this help message

Environment Variables:
  PGHOST                  PostgreSQL server hostname (default: localhost)
  PGPORT                  PostgreSQL server port (default: 5432)
  PGUSER                  PostgreSQL username with restore privileges (default: postgres)
  PGPASSWORD              PostgreSQL password
  PGDATABASE              Target database name (default: extracted from filename)
  BACKUP_DIR              Backup directory (default: /var/backups/postgres)

Examples:
  # Interactive restore with confirmation
  $0 /var/backups/postgres/laya_db_20260216_021500.sql.gz

  # Non-interactive restore (USE WITH CAUTION)
  $0 /var/backups/postgres/laya_db_20260216_021500.sql.gz --yes

  # List available backups
  $0 --list

EOF
    exit 0
}

# List available backups
list_backups() {
    log "==================================="
    log "Available PostgreSQL Backups"
    log "==================================="

    if [ ! -d "$BACKUP_DIR" ]; then
        error_exit "Backup directory does not exist: $BACKUP_DIR"
    fi

    local backups=$(find "$BACKUP_DIR" -name "*.sql.gz" -type f | sort -r)

    if [ -z "$backups" ]; then
        warning "No backup files found in $BACKUP_DIR"
        exit 0
    fi

    echo ""
    echo "Backup files (newest first):"
    echo ""
    printf "%-50s %-15s %-20s\n" "Filename" "Size" "Modified"
    printf "%-50s %-15s %-20s\n" "--------" "----" "--------"

    while IFS= read -r backup; do
        local filename=$(basename "$backup")
        local size=$(du -h "$backup" | cut -f1)
        local modified=$(stat -f "%Sm" -t "%Y-%m-%d %H:%M" "$backup" 2>/dev/null || stat -c "%y" "$backup" 2>/dev/null | cut -d'.' -f1)
        printf "%-50s %-15s %-20s\n" "$filename" "$size" "$modified"
    done <<< "$backups"

    echo ""
    log "Total backups found: $(echo "$backups" | wc -l | xargs)"
    exit 0
}

# Parse command line arguments
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --yes|-y)
                SKIP_CONFIRMATION=true
                shift
                ;;
            --list|-l)
                LIST_BACKUPS=true
                shift
                ;;
            --help|-h)
                usage
                ;;
            -*)
                error_exit "Unknown option: $1. Use --help for usage information."
                ;;
            *)
                BACKUP_FILE="$1"
                shift
                ;;
        esac
    done
}

# Confirm action with user
confirm_action() {
    local prompt="$1"

    if [ "$SKIP_CONFIRMATION" = true ]; then
        return 0
    fi

    echo -e "${YELLOW}${prompt}${NC}"
    read -p "Type 'yes' to continue: " response

    if [ "$response" != "yes" ]; then
        log "Operation cancelled by user"
        exit 0
    fi
}

# Extract database name from backup filename
extract_database_name() {
    local filename=$(basename "$1")
    # Extract database name from pattern: dbname_YYYYMMDD_HHMMSS.sql.gz
    echo "$filename" | sed -E 's/(.+)_[0-9]{8}_[0-9]{6}\.sql\.gz/\1/'
}

# Main restore function
main() {
    log "==================================="
    log "Starting PostgreSQL Restore"
    log "==================================="

    # Validate backup file
    if [ -z "${BACKUP_FILE:-}" ]; then
        error_exit "No backup file specified. Use --help for usage information."
    fi

    if [ ! -f "$BACKUP_FILE" ]; then
        error_exit "Backup file does not exist: $BACKUP_FILE"
    fi

    # Determine database name
    if [ -z "$PGDATABASE" ]; then
        PGDATABASE=$(extract_database_name "$BACKUP_FILE")
        info "Database name extracted from filename: $PGDATABASE"
    fi

    # Validate environment variables
    if [ -z "$PGPASSWORD" ]; then
        error_exit "PGPASSWORD environment variable is not set"
    fi

    if [ -z "$PGDATABASE" ]; then
        error_exit "Could not determine database name"
    fi

    # Check if psql is available
    if ! command -v psql &> /dev/null; then
        error_exit "psql command not found. Please install PostgreSQL client tools."
    fi

    # Check if gunzip is available
    if ! command -v gunzip &> /dev/null; then
        error_exit "gunzip command not found. Please install gzip."
    fi

    # Verify backup file integrity
    log "Verifying backup file integrity..."
    if ! gzip -t "$BACKUP_FILE" 2>&1; then
        error_exit "Backup file is corrupted: $BACKUP_FILE"
    fi
    success "Backup file integrity verified"

    # Get backup file info
    BACKUP_SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
    BACKUP_DATE=$(stat -f "%Sm" -t "%Y-%m-%d %H:%M:%S" "$BACKUP_FILE" 2>/dev/null || stat -c "%y" "$BACKUP_FILE" 2>/dev/null | cut -d'.' -f1)

    info "Backup file: $BACKUP_FILE"
    info "Backup size: $BACKUP_SIZE"
    info "Backup date: $BACKUP_DATE"

    # Check PostgreSQL connectivity
    log "Testing PostgreSQL connection..."
    if ! psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d postgres -c "SELECT 1" &> /dev/null; then
        error_exit "Cannot connect to PostgreSQL server at $PGHOST:$PGPORT"
    fi
    success "PostgreSQL connection successful"

    # Check if database exists
    DB_EXISTS=$(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d postgres -t -c "SELECT 1 FROM pg_database WHERE datname='$PGDATABASE'" | xargs)

    if [ "$DB_EXISTS" = "1" ]; then
        warning "Database '$PGDATABASE' already exists and will be DROPPED and recreated"

        # Get current database size
        CURRENT_SIZE=$(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$PGDATABASE" -t -c "
            SELECT ROUND(pg_database_size('$PGDATABASE') / 1024.0 / 1024.0, 2)
        " | xargs)
        warning "Current database size: ${CURRENT_SIZE} MB will be LOST"

        # Terminate existing connections
        warning "All active connections to '$PGDATABASE' will be terminated"

        confirm_action "⚠️  WARNING: This will DROP the existing database '$PGDATABASE' and restore from backup.
All current data will be PERMANENTLY LOST!"
    else
        info "Database '$PGDATABASE' does not exist - will be created from backup"
        confirm_action "Restore database '$PGDATABASE' from backup?"
    fi

    # Check available disk space
    AVAILABLE_SPACE=$(df -m "$BACKUP_DIR" | tail -1 | awk '{print $4}')
    info "Available disk space: ${AVAILABLE_SPACE} MB"

    # Perform restore
    log "Starting restore from: $BACKUP_FILE"
    log "Target database: $PGDATABASE"

    START_TIME=$(date +%s)

    # Terminate existing connections if database exists
    if [ "$DB_EXISTS" = "1" ]; then
        log "Terminating existing connections to database..."
        psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d postgres -c "
            SELECT pg_terminate_backend(pg_stat_activity.pid)
            FROM pg_stat_activity
            WHERE pg_stat_activity.datname = '$PGDATABASE'
            AND pid <> pg_backend_pid();
        " &> /dev/null || true
        success "Connections terminated"

        # Drop database
        log "Dropping existing database..."
        if dropdb -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" --if-exists "$PGDATABASE"; then
            success "Database dropped successfully"
        else
            error_exit "Failed to drop database"
        fi
    fi

    # Restore from backup (backup file includes CREATE DATABASE command)
    log "Restoring database from backup..."
    if gunzip -c "$BACKUP_FILE" | psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d postgres 2>&1 | grep -v "^CREATE DATABASE$" | grep -v "^ALTER DATABASE$" | grep -v "^COMMENT$" | grep -v "^SET$" | tee -a "$LOG_FILE"; then
        END_TIME=$(date +%s)
        DURATION=$((END_TIME - START_TIME))

        success "Restore completed successfully"
        log "Restore duration: ${DURATION} seconds"

        # Verify restore
        log "Verifying restored database..."

        # Check if database exists
        DB_CREATED=$(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d postgres -t -c "SELECT 1 FROM pg_database WHERE datname='$PGDATABASE'" | xargs)

        if [ "$DB_CREATED" != "1" ]; then
            error_exit "Database was not created during restore"
        fi

        # Get table count
        TABLE_COUNT=$(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$PGDATABASE" -t -c "
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = 'public'
        " | xargs)

        # Get database size
        RESTORED_SIZE=$(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$PGDATABASE" -t -c "
            SELECT ROUND(pg_database_size('$PGDATABASE') / 1024.0 / 1024.0, 2)
        " | xargs)

        success "Database verification complete"
        log "Tables restored: $TABLE_COUNT"
        log "Database size: ${RESTORED_SIZE} MB"

        log "==================================="
        log "Restore completed successfully"
        log "==================================="

    else
        error_exit "Restore failed"
    fi
}

# Parse arguments
parse_args "$@"

# Handle --list option
if [ "$LIST_BACKUPS" = true ]; then
    list_backups
fi

# Create log directory if it doesn't exist
mkdir -p "$LOG_DIR" || error_exit "Failed to create log directory"

# Run main function
main
