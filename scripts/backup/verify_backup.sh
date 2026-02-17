#!/bin/bash
################################################################################
# Backup Verification Script for LAYA Daycare Management System
#
# Description:
#   Verifies database backup integrity by restoring to a temporary database,
#   running integrity checks, and cleaning up afterwards. Supports both MySQL
#   and PostgreSQL backups with automatic database type detection.
#
# Features:
#   - Automatic database type detection (MySQL/PostgreSQL)
#   - Restore to temporary database (_verify_temp suffix)
#   - Comprehensive integrity checks
#   - Automatic cleanup of temporary resources
#   - Non-destructive (no impact on production databases)
#   - Detailed logging and reporting
#
# Usage:
#   # Verify a MySQL backup
#   ./verify_backup.sh /var/backups/mysql/laya_db_20260215_020000.sql.gz
#
#   # Verify a PostgreSQL backup
#   ./verify_backup.sh /var/backups/postgres/laya_db_20260216_021500.sql.gz
#
#   # Verify latest backup
#   ./verify_backup.sh --latest
#
#   # Verify all recent backups (last 7 days)
#   ./verify_backup.sh --all
#
# Environment variables required:
#   For MySQL:
#     MYSQL_HOST - MySQL server hostname (default: localhost)
#     MYSQL_PORT - MySQL server port (default: 3306)
#     MYSQL_USER - MySQL username (needs CREATE/DROP privileges)
#     MYSQL_PASSWORD - MySQL password
#
#   For PostgreSQL:
#     PGHOST - PostgreSQL server hostname (default: localhost)
#     PGPORT - PostgreSQL server port (default: 5432)
#     PGUSER - PostgreSQL username (needs CREATEDB privileges)
#     PGPASSWORD - PostgreSQL password
#
#   Common:
#     MYSQL_BACKUP_DIR - MySQL backup directory (default: /var/backups/mysql)
#     POSTGRES_BACKUP_DIR - PostgreSQL backup directory (default: /var/backups/postgres)
#
################################################################################

set -euo pipefail

# Configuration
MYSQL_HOST="${MYSQL_HOST:-localhost}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_USER="${MYSQL_USER:-root}"
MYSQL_PASSWORD="${MYSQL_PASSWORD:-}"
MYSQL_BACKUP_DIR="${MYSQL_BACKUP_DIR:-/var/backups/mysql}"

PGHOST="${PGHOST:-localhost}"
PGPORT="${PGPORT:-5432}"
PGUSER="${PGUSER:-postgres}"
PGPASSWORD="${PGPASSWORD:-}"
POSTGRES_BACKUP_DIR="${POSTGRES_BACKUP_DIR:-/var/backups/postgres}"

# Export PostgreSQL password
export PGPASSWORD

LOG_DIR="${LOG_DIR:-/var/log}"
LOG_FILE="${LOG_DIR}/backup_verification.log"
TEMP_DB_SUFFIX="_verify_temp"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Flags
VERIFY_LATEST=false
VERIFY_ALL=false

# Statistics
TOTAL_VERIFIED=0
TOTAL_PASSED=0
TOTAL_FAILED=0

# Logging function
log() {
    echo -e "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"
}

# Error handler
error() {
    log "${RED}ERROR: $1${NC}"
}

error_exit() {
    error "$1"
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

# Debug handler
debug() {
    log "${CYAN}DEBUG: $1${NC}"
}

# Show usage
usage() {
    cat << EOF
Usage: $0 [OPTIONS] [BACKUP_FILE]

Verify database backup integrity by restoring to temporary database.

Arguments:
  BACKUP_FILE              Path to the backup file (.sql.gz)

Options:
  --latest                Verify the most recent backup
  --all                   Verify all backups from last 7 days
  --help, -h              Show this help message

Environment Variables:
  MySQL:
    MYSQL_HOST            MySQL server hostname (default: localhost)
    MYSQL_PORT            MySQL server port (default: 3306)
    MYSQL_USER            MySQL username with CREATE/DROP privileges (default: root)
    MYSQL_PASSWORD        MySQL password
    MYSQL_BACKUP_DIR      MySQL backup directory (default: /var/backups/mysql)

  PostgreSQL:
    PGHOST                PostgreSQL server hostname (default: localhost)
    PGPORT                PostgreSQL server port (default: 5432)
    PGUSER                PostgreSQL username with CREATEDB privileges (default: postgres)
    PGPASSWORD            PostgreSQL password
    POSTGRES_BACKUP_DIR   PostgreSQL backup directory (default: /var/backups/postgres)

Examples:
  # Verify a specific backup
  $0 /var/backups/mysql/laya_db_20260215_020000.sql.gz

  # Verify the latest backup
  $0 --latest

  # Verify all recent backups
  $0 --all

EOF
    exit 0
}

# Detect database type from backup file location
detect_db_type() {
    local backup_file="$1"

    if [[ "$backup_file" == *"/mysql/"* ]] || [[ "$backup_file" == "$MYSQL_BACKUP_DIR"* ]]; then
        echo "mysql"
    elif [[ "$backup_file" == *"/postgres"* ]] || [[ "$backup_file" == "$POSTGRES_BACKUP_DIR"* ]]; then
        echo "postgres"
    else
        # Try to detect from content (check first few lines after decompression)
        local first_lines=$(gunzip -c "$backup_file" 2>/dev/null | head -20)
        if echo "$first_lines" | grep -q "MySQL dump"; then
            echo "mysql"
        elif echo "$first_lines" | grep -q "PostgreSQL database dump"; then
            echo "postgres"
        else
            echo "unknown"
        fi
    fi
}

# Extract database name from backup filename
extract_database_name() {
    local filename=$(basename "$1")
    # Extract database name from pattern: dbname_YYYYMMDD_HHMMSS.sql.gz
    echo "$filename" | sed -E 's/(.+)_[0-9]{8}_[0-9]{6}\.sql\.gz/\1/'
}

# Cleanup function for temporary databases
cleanup_temp_db() {
    local db_type="$1"
    local temp_db_name="$2"

    info "Cleaning up temporary database: $temp_db_name"

    if [ "$db_type" = "mysql" ]; then
        if mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "DROP DATABASE IF EXISTS \`$temp_db_name\`" &> /dev/null; then
            success "Temporary MySQL database dropped: $temp_db_name"
        else
            warning "Failed to drop temporary MySQL database: $temp_db_name"
        fi
    elif [ "$db_type" = "postgres" ]; then
        # Terminate connections first
        psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d postgres -c "
            SELECT pg_terminate_backend(pg_stat_activity.pid)
            FROM pg_stat_activity
            WHERE pg_stat_activity.datname = '$temp_db_name'
            AND pid <> pg_backend_pid();
        " &> /dev/null || true

        if dropdb -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" --if-exists "$temp_db_name" &> /dev/null; then
            success "Temporary PostgreSQL database dropped: $temp_db_name"
        else
            warning "Failed to drop temporary PostgreSQL database: $temp_db_name"
        fi
    fi
}

# Verify MySQL backup
verify_mysql_backup() {
    local backup_file="$1"
    local db_name=$(extract_database_name "$backup_file")
    local temp_db_name="${db_name}${TEMP_DB_SUFFIX}"

    info "Verifying MySQL backup: $backup_file"
    info "Temporary database: $temp_db_name"

    # Check MySQL connectivity
    if ! mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SELECT 1" &> /dev/null; then
        error "Cannot connect to MySQL server at $MYSQL_HOST:$MYSQL_PORT"
        return 1
    fi

    # Cleanup any existing temporary database
    cleanup_temp_db "mysql" "$temp_db_name"

    # Create temporary database
    debug "Creating temporary database..."
    if ! mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "CREATE DATABASE \`$temp_db_name\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" &> /dev/null; then
        error "Failed to create temporary database"
        return 1
    fi

    # Restore backup to temporary database
    debug "Restoring backup to temporary database..."
    local restore_output
    # Capture mysql output and check exit code directly instead of relying on grep
    # (grep -v would fail with exit code 1 on silent success with no output)
    if ! restore_output=$(gunzip -c "$backup_file" | mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" "$temp_db_name" 2>&1); then
        error "Failed to restore backup to temporary database"
        [ -n "$restore_output" ] && echo "$restore_output" >> "$LOG_FILE"
        cleanup_temp_db "mysql" "$temp_db_name"
        return 1
    fi
    # Log any output (warnings, etc.) even on success
    if [ -n "$restore_output" ]; then
        echo "$restore_output" >> "$LOG_FILE"
    fi

    # Run integrity checks
    debug "Running integrity checks..."

    # Check 1: Verify tables exist
    local table_count=$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
        SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = '$temp_db_name'
    " -N 2>&1)

    if [ -z "$table_count" ] || [ "$table_count" = "0" ]; then
        error "No tables found in restored database"
        cleanup_temp_db "mysql" "$temp_db_name"
        return 1
    fi
    info "Tables found: $table_count"

    # Check 2: Verify database size
    local db_size=$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
        SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size_MB'
        FROM information_schema.tables
        WHERE table_schema = '$temp_db_name'
    " -N 2>&1)
    info "Database size: ${db_size} MB"

    # Check 3: Run CHECK TABLE on all tables
    debug "Running CHECK TABLE on all tables..."
    local tables=$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
        SELECT table_name FROM information_schema.tables
        WHERE table_schema = '$temp_db_name'
    " -N 2>&1)

    local check_failed=false
    while IFS= read -r table; do
        if [ -n "$table" ]; then
            local check_result=$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "CHECK TABLE \`$temp_db_name\`.\`$table\`" 2>&1 | grep -v "^Table" | grep -v "^+")
            if echo "$check_result" | grep -qi "error\|corrupt"; then
                error "Table check failed for: $table"
                check_failed=true
            fi
        fi
    done <<< "$tables"

    if [ "$check_failed" = true ]; then
        error "Integrity check failed: Some tables are corrupted"
        cleanup_temp_db "mysql" "$temp_db_name"
        return 1
    fi

    # Check 4: Verify row counts (basic sanity check)
    local total_rows=$(mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "
        SELECT SUM(table_rows) FROM information_schema.tables
        WHERE table_schema = '$temp_db_name'
    " -N 2>&1)
    info "Total rows (estimated): $total_rows"

    # Cleanup temporary database
    cleanup_temp_db "mysql" "$temp_db_name"

    success "MySQL backup verification passed"
    return 0
}

# Verify PostgreSQL backup
verify_postgres_backup() {
    local backup_file="$1"
    local db_name=$(extract_database_name "$backup_file")
    local temp_db_name="${db_name}${TEMP_DB_SUFFIX}"

    info "Verifying PostgreSQL backup: $backup_file"
    info "Temporary database: $temp_db_name"

    # Check PostgreSQL connectivity
    if ! psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d postgres -c "SELECT 1" &> /dev/null; then
        error "Cannot connect to PostgreSQL server at $PGHOST:$PGPORT"
        return 1
    fi

    # Cleanup any existing temporary database
    cleanup_temp_db "postgres" "$temp_db_name"

    # Restore backup (includes CREATE DATABASE command)
    debug "Restoring backup to temporary database..."

    # First, we need to modify the backup to use temp database name
    # Extract and modify CREATE DATABASE command
    local temp_backup="/tmp/verify_backup_$$.sql"
    gunzip -c "$backup_file" | sed "s/CREATE DATABASE $db_name/CREATE DATABASE $temp_db_name/g" | sed "s/\\\\connect $db_name/\\\\connect $temp_db_name/g" > "$temp_backup"

    if ! psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d postgres -f "$temp_backup" 2>&1 | grep -v "^CREATE DATABASE$" | grep -v "^ALTER DATABASE$" | grep -v "^COMMENT$" | grep -v "^SET$" | tee -a "$LOG_FILE" | grep -i "ERROR" > /dev/null; then
        # No errors found, continue
        :
    else
        error "Failed to restore backup to temporary database"
        rm -f "$temp_backup"
        cleanup_temp_db "postgres" "$temp_db_name"
        return 1
    fi

    rm -f "$temp_backup"

    # Verify database was created
    local db_exists=$(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d postgres -t -c "SELECT 1 FROM pg_database WHERE datname='$temp_db_name'" 2>&1 | xargs)

    if [ "$db_exists" != "1" ]; then
        error "Temporary database was not created"
        return 1
    fi

    # Run integrity checks
    debug "Running integrity checks..."

    # Check 1: Verify tables exist
    local table_count=$(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$temp_db_name" -t -c "
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = 'public'
    " 2>&1 | xargs)

    if [ -z "$table_count" ] || [ "$table_count" = "0" ]; then
        error "No tables found in restored database"
        cleanup_temp_db "postgres" "$temp_db_name"
        return 1
    fi
    info "Tables found: $table_count"

    # Check 2: Verify database size
    local db_size=$(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$temp_db_name" -t -c "
        SELECT ROUND(pg_database_size('$temp_db_name') / 1024.0 / 1024.0, 2)
    " 2>&1 | xargs)
    info "Database size: ${db_size} MB"

    # Check 3: Verify database connections work
    if ! psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$temp_db_name" -c "SELECT COUNT(*) FROM pg_tables WHERE schemaname = 'public'" &> /dev/null; then
        error "Failed to query restored database"
        cleanup_temp_db "postgres" "$temp_db_name"
        return 1
    fi

    # Check 4: Verify row counts (basic sanity check)
    local tables=$(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$temp_db_name" -t -c "
        SELECT tablename FROM pg_tables WHERE schemaname = 'public'
    " 2>&1)

    local total_rows=0
    while IFS= read -r table; do
        table=$(echo "$table" | xargs)  # trim whitespace
        if [ -n "$table" ]; then
            local row_count=$(psql -h "$PGHOST" -p "$PGPORT" -U "$PGUSER" -d "$temp_db_name" -t -c "SELECT COUNT(*) FROM \"$table\"" 2>&1 | xargs)
            if [ -n "$row_count" ] && [ "$row_count" != "0" ]; then
                total_rows=$((total_rows + row_count))
            fi
        fi
    done <<< "$tables"
    info "Total rows: $total_rows"

    # Cleanup temporary database
    cleanup_temp_db "postgres" "$temp_db_name"

    success "PostgreSQL backup verification passed"
    return 0
}

# Verify a single backup file
verify_backup() {
    local backup_file="$1"

    log "==================================="
    log "Backup Verification"
    log "==================================="

    # Validate backup file exists
    if [ ! -f "$backup_file" ]; then
        error "Backup file does not exist: $backup_file"
        return 1
    fi

    # Get backup file info
    local backup_size=$(du -h "$backup_file" | cut -f1)
    local backup_date=$(stat -f "%Sm" -t "%Y-%m-%d %H:%M:%S" "$backup_file" 2>/dev/null || stat -c "%y" "$backup_file" 2>/dev/null | cut -d'.' -f1)

    info "Backup file: $(basename "$backup_file")"
    info "Backup size: $backup_size"
    info "Backup date: $backup_date"

    # Verify backup file integrity
    debug "Verifying backup file integrity..."
    if ! gzip -t "$backup_file" 2>&1; then
        error "Backup file is corrupted: $backup_file"
        return 1
    fi
    success "Backup file integrity verified (gzip test passed)"

    # Detect database type
    local db_type=$(detect_db_type "$backup_file")
    info "Detected database type: $db_type"

    # Verify based on database type
    local start_time=$(date +%s)
    local result=0

    case "$db_type" in
        mysql)
            if [ -z "$MYSQL_PASSWORD" ]; then
                error "MYSQL_PASSWORD environment variable is not set"
                return 1
            fi
            verify_mysql_backup "$backup_file" || result=1
            ;;
        postgres)
            if [ -z "$PGPASSWORD" ]; then
                error "PGPASSWORD environment variable is not set"
                return 1
            fi
            verify_postgres_backup "$backup_file" || result=1
            ;;
        *)
            error "Unable to detect database type from backup file"
            return 1
            ;;
    esac

    local end_time=$(date +%s)
    local duration=$((end_time - start_time))

    if [ $result -eq 0 ]; then
        success "Backup verification completed successfully in ${duration} seconds"
        log "==================================="
        return 0
    else
        error "Backup verification failed after ${duration} seconds"
        log "==================================="
        return 1
    fi
}

# Find latest backup
find_latest_backup() {
    local mysql_latest=$(find "$MYSQL_BACKUP_DIR" -name "*.sql.gz" -type f 2>/dev/null | sort -r | head -1)
    local postgres_latest=$(find "$POSTGRES_BACKUP_DIR" -name "*.sql.gz" -type f 2>/dev/null | sort -r | head -1)

    # Return the most recent of both
    if [ -n "$mysql_latest" ] && [ -n "$postgres_latest" ]; then
        if [ "$mysql_latest" -nt "$postgres_latest" ]; then
            echo "$mysql_latest"
        else
            echo "$postgres_latest"
        fi
    elif [ -n "$mysql_latest" ]; then
        echo "$mysql_latest"
    elif [ -n "$postgres_latest" ]; then
        echo "$postgres_latest"
    fi
}

# Find all recent backups (last 7 days)
find_all_recent_backups() {
    find "$MYSQL_BACKUP_DIR" "$POSTGRES_BACKUP_DIR" -name "*.sql.gz" -type f -mtime -7 2>/dev/null | sort -r
}

# Verify all recent backups
verify_all_backups() {
    log "==================================="
    log "Verifying All Recent Backups"
    log "==================================="

    local backups=$(find_all_recent_backups)

    if [ -z "$backups" ]; then
        warning "No backups found in the last 7 days"
        return 0
    fi

    local backup_count=$(echo "$backups" | wc -l | xargs)
    info "Found $backup_count backup(s) to verify"
    echo ""

    while IFS= read -r backup; do
        if [ -n "$backup" ]; then
            TOTAL_VERIFIED=$((TOTAL_VERIFIED + 1))

            if verify_backup "$backup"; then
                TOTAL_PASSED=$((TOTAL_PASSED + 1))
            else
                TOTAL_FAILED=$((TOTAL_FAILED + 1))
            fi

            echo ""
        fi
    done <<< "$backups"

    # Print summary
    log "==================================="
    log "Verification Summary"
    log "==================================="
    log "Total backups verified: $TOTAL_VERIFIED"
    success "Passed: $TOTAL_PASSED"
    if [ $TOTAL_FAILED -gt 0 ]; then
        error "Failed: $TOTAL_FAILED"
        return 1
    else
        info "Failed: $TOTAL_FAILED"
    fi
    log "==================================="

    return 0
}

# Parse command line arguments
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --latest)
                VERIFY_LATEST=true
                shift
                ;;
            --all)
                VERIFY_ALL=true
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

# Ensure log directory exists before any logging (moved here to handle early error paths)
mkdir -p "$LOG_DIR" 2>/dev/null || true

# Main function
main() {
    # Verify log directory exists (fail early with clear error if creation silently failed)
    if [ ! -d "$LOG_DIR" ]; then
        echo "ERROR: Failed to create log directory: $LOG_DIR" >&2
        exit 1
    fi

    log "==================================="
    log "Backup Verification Tool"
    log "==================================="

    # Handle different modes
    if [ "$VERIFY_LATEST" = true ]; then
        BACKUP_FILE=$(find_latest_backup)
        if [ -z "$BACKUP_FILE" ]; then
            error_exit "No backups found"
        fi
        info "Latest backup: $BACKUP_FILE"
        verify_backup "$BACKUP_FILE"
        exit $?
    fi

    if [ "$VERIFY_ALL" = true ]; then
        verify_all_backups
        exit $?
    fi

    # Single backup verification
    if [ -z "${BACKUP_FILE:-}" ]; then
        error_exit "No backup file specified. Use --help for usage information."
    fi

    verify_backup "$BACKUP_FILE"
    exit $?
}

# Parse arguments
parse_args "$@"

# Run main function
main
