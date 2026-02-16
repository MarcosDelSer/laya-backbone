#!/bin/bash
#
# Gibbon NotificationEngine - Cron Setup Assistant
#
# This script helps configure a cron job for the notification queue processor.
# Run with: bash setup-cron.sh
#
# Copyright © 2010, Gibbon Foundation
# Licensed under GPL v3 or later
#

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print colored output
print_info() {
    echo -e "${BLUE}ℹ${NC} $1"
}

print_success() {
    echo -e "${GREEN}✓${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}⚠${NC} $1"
}

print_error() {
    echo -e "${RED}✗${NC} $1"
}

print_header() {
    echo ""
    echo -e "${BLUE}════════════════════════════════════════════════════${NC}"
    echo -e "${BLUE}  Gibbon NotificationEngine - Cron Setup Assistant${NC}"
    echo -e "${BLUE}════════════════════════════════════════════════════${NC}"
    echo ""
}

# Detect script location
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
PROCESSOR_SCRIPT="$SCRIPT_DIR/processQueue.php"

# Detect Gibbon root
GIBBON_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)"

# Detect PHP binary
PHP_BIN=$(which php 2>/dev/null || echo "/usr/bin/php")

# Default values
CRON_USER="www-data"
CRON_SCHEDULE="* * * * *"  # Every minute
LOG_DIR="/var/log/gibbon"
LOG_FILE="$LOG_DIR/notifications.log"

print_header

# Check if script exists
if [ ! -f "$PROCESSOR_SCRIPT" ]; then
    print_error "Error: processQueue.php not found at $PROCESSOR_SCRIPT"
    exit 1
fi

print_success "Found queue processor: $PROCESSOR_SCRIPT"

# Check PHP
if [ ! -x "$PHP_BIN" ]; then
    print_warning "PHP binary not found or not executable at $PHP_BIN"
    read -p "Enter PHP binary path: " PHP_BIN
    if [ ! -x "$PHP_BIN" ]; then
        print_error "Invalid PHP binary path"
        exit 1
    fi
fi

PHP_VERSION=$($PHP_BIN -v | head -n1)
print_success "PHP found: $PHP_VERSION"

# Check if running as root (needed for creating cron jobs for other users)
if [ "$EUID" -ne 0 ]; then
    print_warning "This script is not running as root."
    print_info "Some operations may require sudo password."
    echo ""
fi

# Interactive setup
echo ""
print_info "Cron Job Configuration"
echo ""

# 1. Select cron user
echo "1. Which user should run the cron job?"
echo "   Common options: www-data, apache, nginx, $(whoami)"
read -p "   Cron user [$CRON_USER]: " user_input
CRON_USER=${user_input:-$CRON_USER}

# Verify user exists
if ! id "$CRON_USER" >/dev/null 2>&1; then
    print_error "User '$CRON_USER' does not exist"
    exit 1
fi
print_success "Using cron user: $CRON_USER"

# 2. Select schedule
echo ""
echo "2. How often should the queue be processed?"
echo "   1) Every minute (recommended for real-time notifications)"
echo "   2) Every 5 minutes (lighter server load)"
echo "   3) Every 10 minutes"
echo "   4) Custom schedule"
read -p "   Select option [1]: " schedule_option

case ${schedule_option:-1} in
    1)
        CRON_SCHEDULE="* * * * *"
        print_success "Schedule: Every minute"
        ;;
    2)
        CRON_SCHEDULE="*/5 * * * *"
        print_success "Schedule: Every 5 minutes"
        ;;
    3)
        CRON_SCHEDULE="*/10 * * * *"
        print_success "Schedule: Every 10 minutes"
        ;;
    4)
        read -p "   Enter cron schedule (e.g., */5 * * * *): " CRON_SCHEDULE
        print_success "Schedule: $CRON_SCHEDULE"
        ;;
    *)
        print_error "Invalid option"
        exit 1
        ;;
esac

# 3. Configure logging
echo ""
echo "3. Configure logging"
read -p "   Log directory [$LOG_DIR]: " log_input
LOG_DIR=${log_input:-$LOG_DIR}
LOG_FILE="$LOG_DIR/notifications.log"

# Create log directory if it doesn't exist
if [ ! -d "$LOG_DIR" ]; then
    print_info "Creating log directory: $LOG_DIR"
    if sudo mkdir -p "$LOG_DIR" 2>/dev/null; then
        sudo chown "$CRON_USER:$CRON_USER" "$LOG_DIR"
        print_success "Log directory created"
    else
        print_warning "Could not create log directory. Using /tmp instead."
        LOG_DIR="/tmp"
        LOG_FILE="$LOG_DIR/gibbon-notifications.log"
    fi
fi

# 4. Additional options
echo ""
echo "4. Additional options"
read -p "   Enable verbose output? (y/N): " verbose
read -p "   Processing limit per run [50]: " limit
limit=${limit:-50}

# Build cron command
CRON_OPTIONS=""
if [ "$verbose" = "y" ] || [ "$verbose" = "Y" ]; then
    CRON_OPTIONS="$CRON_OPTIONS --verbose"
fi
if [ "$limit" != "50" ]; then
    CRON_OPTIONS="$CRON_OPTIONS --limit=$limit"
fi

CRON_COMMAND="$PHP_BIN $PROCESSOR_SCRIPT$CRON_OPTIONS >> $LOG_FILE 2>&1"
CRON_ENTRY="$CRON_SCHEDULE $CRON_COMMAND"

# 5. Purge configuration
echo ""
echo "5. Configure automatic purge of old notifications?"
read -p "   Enable automatic purge? (Y/n): " enable_purge

if [ "$enable_purge" != "n" ] && [ "$enable_purge" != "N" ]; then
    read -p "   Purge notifications older than X days [30]: " purge_days
    purge_days=${purge_days:-30}

    read -p "   Purge schedule (e.g., '0 2 * * *' for daily at 2 AM) [0 2 * * *]: " purge_schedule
    purge_schedule=${purge_schedule:-"0 2 * * *"}

    PURGE_COMMAND="$PHP_BIN $PROCESSOR_SCRIPT --purge --purge-days=$purge_days >> $LOG_DIR/notifications-purge.log 2>&1"
    PURGE_ENTRY="$purge_schedule $PURGE_COMMAND"
fi

# Summary
echo ""
print_info "Configuration Summary"
echo "─────────────────────────────────────────────────────"
echo "Cron User:        $CRON_USER"
echo "PHP Binary:       $PHP_BIN"
echo "Processor Script: $PROCESSOR_SCRIPT"
echo "Schedule:         $CRON_SCHEDULE"
echo "Log File:         $LOG_FILE"
if [ -n "$PURGE_ENTRY" ]; then
    echo "Purge Schedule:   $purge_schedule (keep $purge_days days)"
fi
echo ""
echo "Cron Entry:"
echo "  $CRON_ENTRY"
if [ -n "$PURGE_ENTRY" ]; then
    echo "  $PURGE_ENTRY"
fi
echo ""

# Confirm
read -p "Install this cron job? (Y/n): " confirm
if [ "$confirm" = "n" ] || [ "$confirm" = "N" ]; then
    print_warning "Installation cancelled"
    exit 0
fi

# Install cron job
echo ""
print_info "Installing cron job..."

# Get current crontab
TEMP_CRON=$(mktemp)
if sudo crontab -u "$CRON_USER" -l 2>/dev/null > "$TEMP_CRON"; then
    print_info "Existing crontab retrieved"
else
    print_info "No existing crontab for $CRON_USER"
    touch "$TEMP_CRON"
fi

# Check if entry already exists
if grep -q "processQueue.php" "$TEMP_CRON"; then
    print_warning "Existing NotificationEngine cron job found"
    read -p "Replace existing entry? (Y/n): " replace
    if [ "$replace" != "n" ] && [ "$replace" != "N" ]; then
        # Remove old entries
        grep -v "processQueue.php" "$TEMP_CRON" > "$TEMP_CRON.new"
        mv "$TEMP_CRON.new" "$TEMP_CRON"
        print_info "Removed old cron entries"
    else
        print_warning "Installation cancelled"
        rm "$TEMP_CRON"
        exit 0
    fi
fi

# Add new entries
echo "" >> "$TEMP_CRON"
echo "# Gibbon NotificationEngine - Queue Processor" >> "$TEMP_CRON"
echo "$CRON_ENTRY" >> "$TEMP_CRON"

if [ -n "$PURGE_ENTRY" ]; then
    echo "" >> "$TEMP_CRON"
    echo "# Gibbon NotificationEngine - Purge Old Notifications" >> "$TEMP_CRON"
    echo "$PURGE_ENTRY" >> "$TEMP_CRON"
fi

# Install crontab
if sudo crontab -u "$CRON_USER" "$TEMP_CRON"; then
    print_success "Cron job installed successfully!"
else
    print_error "Failed to install cron job"
    rm "$TEMP_CRON"
    exit 1
fi

# Cleanup
rm "$TEMP_CRON"

# Verify installation
echo ""
print_info "Verifying installation..."
echo ""
echo "Current crontab for $CRON_USER:"
echo "─────────────────────────────────────────────────────"
sudo crontab -u "$CRON_USER" -l | tail -10
echo "─────────────────────────────────────────────────────"

# Test run
echo ""
print_info "Testing queue processor..."
if sudo -u "$CRON_USER" $PHP_BIN "$PROCESSOR_SCRIPT" --dry-run --verbose; then
    print_success "Test run completed successfully!"
else
    print_error "Test run failed. Check the error messages above."
    exit 1
fi

# Final instructions
echo ""
print_success "Setup complete!"
echo ""
print_info "Next steps:"
echo "  1. Monitor the log file: tail -f $LOG_FILE"
echo "  2. Check cron execution: grep CRON /var/log/syslog"
echo "  3. Verify notifications are being processed"
echo ""
print_info "Useful commands:"
echo "  • List cron jobs:    sudo crontab -u $CRON_USER -l"
echo "  • Edit cron jobs:    sudo crontab -u $CRON_USER -e"
echo "  • Remove cron job:   sudo crontab -u $CRON_USER -r"
echo "  • Test processor:    $PHP_BIN $PROCESSOR_SCRIPT --dry-run --verbose"
echo "  • View logs:         tail -f $LOG_FILE"
echo ""
print_success "NotificationEngine cron setup complete! 🎉"
echo ""
