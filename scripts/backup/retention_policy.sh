#!/bin/bash
################################################################################
# Backup Retention Policy Script for LAYA Daycare Management System
#
# Description:
#   Manages backup retention by removing old backups while preserving:
#   - Last 7 daily backups
#   - Last 4 weekly backups (Sundays)
#   - Last 12 monthly backups (first day of each month)
#
# Features:
#   - Intelligent retention based on backup age
#   - Separate retention for MySQL and PostgreSQL backups
#   - Dry-run mode for testing
#   - Comprehensive logging
#   - Safe deletion with validation
#
# Usage:
#   ./retention_policy.sh [--dry-run] [--mysql-dir PATH] [--postgres-dir PATH]
#
# Cron schedule (add to crontab - run daily at 3:00 AM):
#   0 3 * * * /path/to/scripts/backup/retention_policy.sh >> /var/log/retention_policy.log 2>&1
#
# Environment variables (optional):
#   MYSQL_BACKUP_DIR - MySQL backup directory (default: /var/backups/mysql)
#   POSTGRES_BACKUP_DIR - PostgreSQL backup directory (default: /var/backups/postgres)
#   RETENTION_LOG - Log file path (default: /var/log/retention_policy.log)
#   DRY_RUN - Set to "true" for dry-run mode (default: false)
#
################################################################################

set -euo pipefail

# Configuration
MYSQL_BACKUP_DIR="${MYSQL_BACKUP_DIR:-/var/backups/mysql}"
POSTGRES_BACKUP_DIR="${POSTGRES_BACKUP_DIR:-/var/backups/postgres}"
RETENTION_LOG="${RETENTION_LOG:-/var/log/retention_policy.log}"
DRY_RUN="${DRY_RUN:-false}"

# Retention settings
DAILY_RETENTION=7      # Keep last 7 daily backups
WEEKLY_RETENTION=4     # Keep last 4 weekly backups (Sundays)
MONTHLY_RETENTION=12   # Keep last 12 monthly backups (1st of month)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    local message="[$(date '+%Y-%m-%d %H:%M:%S')] $1"
    echo -e "$message" | tee -a "$RETENTION_LOG"
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

# Parse command line arguments
parse_args() {
    while [[ $# -gt 0 ]]; do
        case $1 in
            --dry-run)
                DRY_RUN=true
                shift
                ;;
            --mysql-dir)
                MYSQL_BACKUP_DIR="$2"
                shift 2
                ;;
            --postgres-dir)
                POSTGRES_BACKUP_DIR="$2"
                shift 2
                ;;
            -h|--help)
                show_help
                exit 0
                ;;
            *)
                error_exit "Unknown option: $1. Use --help for usage information."
                ;;
        esac
    done
}

# Show help message
show_help() {
    cat << EOF
Backup Retention Policy Script

Usage: $0 [OPTIONS]

Options:
    --dry-run              Show what would be deleted without actually deleting
    --mysql-dir PATH       MySQL backup directory (default: $MYSQL_BACKUP_DIR)
    --postgres-dir PATH    PostgreSQL backup directory (default: $POSTGRES_BACKUP_DIR)
    -h, --help             Show this help message

Retention Policy:
    - Daily backups:   Keep last $DAILY_RETENTION days
    - Weekly backups:  Keep last $WEEKLY_RETENTION weeks (Sundays)
    - Monthly backups: Keep last $MONTHLY_RETENTION months (1st day of month)

Environment Variables:
    MYSQL_BACKUP_DIR       MySQL backup directory
    POSTGRES_BACKUP_DIR    PostgreSQL backup directory
    RETENTION_LOG          Log file path
    DRY_RUN                Set to "true" for dry-run mode

Example:
    $0 --dry-run
    $0 --mysql-dir /custom/mysql/backups --postgres-dir /custom/postgres/backups

EOF
}

# Get backup date from filename
# Expected format: database_YYYYMMDD_HHMMSS.sql.gz
get_backup_date() {
    local filename="$1"
    # Extract date portion (YYYYMMDD) using sed
    echo "$filename" | sed -n 's/.*_\([0-9]\{8\}\)_[0-9]\{6\}\.sql\.gz/\1/p'
}

# Get backup timestamp from filename
get_backup_timestamp() {
    local filename="$1"
    # Extract full timestamp (YYYYMMDD_HHMMSS) using sed
    echo "$filename" | sed -n 's/.*_\([0-9]\{8\}_[0-9]\{6\}\)\.sql\.gz/\1/p'
}

# Check if date is Sunday (weekly backup)
is_sunday() {
    local datestr="$1"  # Format: YYYYMMDD
    local day_of_week
    # Try GNU date first (Linux), then BSD date (macOS)
    day_of_week=$(date -d "${datestr:0:4}-${datestr:4:2}-${datestr:6:2}" +%u 2>/dev/null || \
                  date -j -f "%Y%m%d" "$datestr" +%u 2>/dev/null || echo "0")
    [ "$day_of_week" = "7" ]  # 7 = Sunday
}

# Check if date is first day of month (monthly backup)
is_first_of_month() {
    local datestr="$1"  # Format: YYYYMMDD
    local day="${datestr:6:2}"
    [ "$day" = "01" ]
}

# Convert date string to epoch for comparison
date_to_epoch() {
    local datestr="$1"  # Format: YYYYMMDD
    # Try GNU date first (Linux), then BSD date (macOS)
    if date -d "${datestr:0:4}-${datestr:4:2}-${datestr:6:2}" +%s 2>/dev/null; then
        return 0
    elif date -j -f "%Y%m%d" "$datestr" +%s 2>/dev/null; then
        return 0
    else
        echo "0"
        return 1
    fi
}

# Process retention for a backup directory
process_retention() {
    local backup_dir="$1"
    local db_type="$2"  # "MySQL" or "PostgreSQL"

    info "Processing $db_type backups in: $backup_dir"

    # Check if directory exists
    if [ ! -d "$backup_dir" ]; then
        warning "$db_type backup directory does not exist: $backup_dir"
        return 0
    fi

    # Get list of backup files sorted by date (newest first)
    local backup_files
    backup_files=$(find "$backup_dir" -name "*.sql.gz" -type f -exec basename {} \; 2>/dev/null | sort -r || true)

    if [ -z "$backup_files" ]; then
        info "No backup files found in $backup_dir"
        return 0
    fi

    local total_files
    total_files=$(echo "$backup_files" | wc -l)
    info "Found $total_files backup file(s)"

    # String to track which backups to keep (newline-delimited)
    local keep_files=""
    local current_date
    current_date=$(date +%s)

    # Track counts for each category
    local daily_count=0
    local weekly_count=0
    local monthly_count=0
    local weekly_dates=""
    local monthly_dates=""

    # Process each backup file
    while IFS= read -r filename; do
        [ -z "$filename" ] && continue

        local backup_date
        backup_date=$(get_backup_date "$filename")

        if [ -z "$backup_date" ]; then
            warning "Could not extract date from filename: $filename"
            # Keep files we can't parse to be safe
            keep_files="${keep_files}${filename}"$'\n'
            continue
        fi

        local backup_epoch
        backup_epoch=$(date_to_epoch "$backup_date")

        if [ "$backup_epoch" = "0" ]; then
            warning "Invalid date in filename: $filename"
            keep_files="${keep_files}${filename}"$'\n'
            continue
        fi

        local age_days
        age_days=$(( (current_date - backup_epoch) / 86400 ))

        # Keep all backups from the last DAILY_RETENTION days
        if [ "$age_days" -lt "$DAILY_RETENTION" ]; then
            keep_files="${keep_files}${filename}"$'\n'
            daily_count=$((daily_count + 1))
            continue
        fi

        # Keep weekly backups (Sundays) for WEEKLY_RETENTION weeks
        if is_sunday "$backup_date"; then
            # Use ISO week number (YYYYWW) to properly key by week instead of month
            local week_marker
            week_marker=$(date -d "${backup_date:0:4}-${backup_date:4:2}-${backup_date:6:2}" +%G%V 2>/dev/null || \
                          date -j -f "%Y%m%d" "$backup_date" +%G%V 2>/dev/null || echo "")

            if [ -z "$week_marker" ]; then
                warning "Could not determine week number for: $filename"
                continue
            fi

            local already_have_week=false

            if echo "$weekly_dates" | grep -q "^${week_marker}$"; then
                already_have_week=true
            fi

            if [ "$already_have_week" = false ] && [ "$weekly_count" -lt "$WEEKLY_RETENTION" ]; then
                keep_files="${keep_files}${filename}"$'\n'
                weekly_dates="${weekly_dates}${week_marker}"$'\n'
                weekly_count=$((weekly_count + 1))
                continue
            fi
        fi

        # Keep monthly backups (1st of month) for MONTHLY_RETENTION months
        if is_first_of_month "$backup_date"; then
            local month_marker="${backup_date:0:6}"  # YYYYMM format
            local already_have_month=false

            if echo "$monthly_dates" | grep -q "^${month_marker}$"; then
                already_have_month=true
            fi

            if [ "$already_have_month" = false ] && [ "$monthly_count" -lt "$MONTHLY_RETENTION" ]; then
                keep_files="${keep_files}${filename}"$'\n'
                monthly_dates="${monthly_dates}${month_marker}"$'\n'
                monthly_count=$((monthly_count + 1))
                continue
            fi
        fi

    done <<< "$backup_files"

    # Count total files to keep
    local total_keep
    total_keep=$(echo "$keep_files" | grep -c "." || echo "0")

    info "Retention summary for $db_type:"
    info "  - Daily backups to keep: $daily_count"
    info "  - Weekly backups to keep: $weekly_count"
    info "  - Monthly backups to keep: $monthly_count"
    info "  - Total backups to keep: $total_keep"

    # Delete files not in keep list
    local deleted_count=0
    local deleted_size=0

    while IFS= read -r filename; do
        [ -z "$filename" ] && continue

        if ! echo "$keep_files" | grep -q "^${filename}$"; then
            local filepath="$backup_dir/$filename"
            local filesize
            filesize=$(stat -f%z "$filepath" 2>/dev/null || stat -c%s "$filepath" 2>/dev/null || echo "0")
            deleted_size=$((deleted_size + filesize))

            # Format file size in human-readable format
            local size_human
            if command -v numfmt &> /dev/null; then
                size_human=$(numfmt --to=iec-i --suffix=B $filesize 2>/dev/null || echo "${filesize} bytes")
            else
                size_human="${filesize} bytes"
            fi

            if [ "$DRY_RUN" = true ]; then
                info "[DRY-RUN] Would delete: $filename ($size_human)"
                deleted_count=$((deleted_count + 1))
            else
                log "Deleting old backup: $filename"
                if rm -f "$filepath"; then
                    deleted_count=$((deleted_count + 1))
                else
                    warning "Failed to delete: $filepath"
                fi
            fi
        fi
    done <<< "$backup_files"

    # Format total size in human-readable format
    local total_size_human
    if command -v numfmt &> /dev/null; then
        total_size_human=$(numfmt --to=iec-i --suffix=B $deleted_size 2>/dev/null || echo "${deleted_size} bytes")
    else
        total_size_human="${deleted_size} bytes"
    fi

    if [ "$DRY_RUN" = true ]; then
        info "[DRY-RUN] Would delete $deleted_count file(s), freeing $total_size_human"
    else
        success "Deleted $deleted_count old backup(s), freed $total_size_human"
    fi
}

# Main function
main() {
    # Create log directory first to ensure logging works
    local log_dir
    log_dir=$(dirname "$RETENTION_LOG")
    if [ ! -d "$log_dir" ]; then
        mkdir -p "$log_dir" || { echo "ERROR: Failed to create log directory: $log_dir" >&2; exit 1; }
    fi

    log "==================================="
    if [ "$DRY_RUN" = true ]; then
        log "Starting backup retention policy (DRY-RUN MODE)"
    else
        log "Starting backup retention policy"
    fi
    log "==================================="

    log "Retention policy configuration:"
    log "  - Daily retention: $DAILY_RETENTION days"
    log "  - Weekly retention: $WEEKLY_RETENTION weeks (Sundays)"
    log "  - Monthly retention: $MONTHLY_RETENTION months (1st day)"
    log ""

    # Process MySQL backups
    if [ -n "$MYSQL_BACKUP_DIR" ]; then
        log ""
        process_retention "$MYSQL_BACKUP_DIR" "MySQL"
    fi

    # Process PostgreSQL backups
    if [ -n "$POSTGRES_BACKUP_DIR" ]; then
        log ""
        process_retention "$POSTGRES_BACKUP_DIR" "PostgreSQL"
    fi

    log ""
    log "==================================="
    if [ "$DRY_RUN" = true ]; then
        success "Retention policy check completed (DRY-RUN MODE)"
    else
        success "Retention policy completed successfully"
    fi
    log "==================================="
}

# Parse command line arguments
parse_args "$@"

# Run main function
main
