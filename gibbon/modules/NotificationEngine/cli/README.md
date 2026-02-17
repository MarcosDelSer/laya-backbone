# NotificationEngine CLI Tools

This directory contains command-line tools for the NotificationEngine module.

## Overview

The NotificationEngine CLI tools provide automated background processing for notification delivery. These tools are designed to be run via cron jobs for reliable, scheduled notification processing.

## Available Commands

### processQueue.php

The main queue processor that handles sending queued notifications via email and push channels.

**Purpose:**
- Process pending notifications from the queue
- Send notifications via email (SMTP) and/or push (FCM)
- Respect user notification preferences
- Handle failed deliveries with retry logic
- Purge old notifications to prevent database bloat

**Usage:**

```bash
# Basic usage - process up to 50 notifications
php /path/to/gibbon/modules/NotificationEngine/cli/processQueue.php

# Process with custom limit
php /path/to/gibbon/modules/NotificationEngine/cli/processQueue.php --limit=100

# Dry run (show what would be processed without sending)
php /path/to/gibbon/modules/NotificationEngine/cli/processQueue.php --dry-run --verbose

# Verbose output for debugging
php /path/to/gibbon/modules/NotificationEngine/cli/processQueue.php --verbose

# Purge old notifications (older than 30 days)
php /path/to/gibbon/modules/NotificationEngine/cli/processQueue.php --purge

# Purge with custom retention period
php /path/to/gibbon/modules/NotificationEngine/cli/processQueue.php --purge --purge-days=60

# Show help
php /path/to/gibbon/modules/NotificationEngine/cli/processQueue.php --help
```

**Options:**

| Option | Description | Default |
|--------|-------------|---------|
| `--limit=N` | Process maximum N notifications per run | 50 |
| `--dry-run` | Show what would be processed without actually sending | false |
| `--verbose` | Show detailed output including each notification processed | false |
| `--purge` | Purge old sent/failed notifications | false |
| `--purge-days=N` | Days to keep notifications before purging | 30 |
| `--help` | Show help message | - |

**How It Works:**

1. **Fetch Pending Notifications:** Queries the `gibbonNotificationQueue` table for notifications with `status='pending'` and `attempts < maxAttempts`
2. **Process Each Notification:**
   - Marks notification as `processing`
   - Checks user notification preferences
   - Sends via email if channel is `email` or `both`
   - Sends via push if channel is `push` or `both`
   - Handles FCM token validation and invalid token cleanup
3. **Update Status:**
   - Marks as `sent` if successful
   - Marks as `failed` if all delivery attempts fail
   - Increments `attempts` counter
   - Records error messages for debugging
4. **Cleanup:**
   - Deactivates invalid FCM tokens
   - Optionally purges old notifications

**Exit Codes:**

- `0` - Success (all notifications processed successfully)
- `1` - Partial failure (one or more notifications failed to send)

## Cron Setup

### Recommended Schedule

For optimal notification delivery, run the queue processor every minute:

```cron
# Process notification queue every minute
* * * * * /usr/bin/php /var/www/gibbon/modules/NotificationEngine/cli/processQueue.php >> /var/log/gibbon/notifications.log 2>&1
```

### Alternative Schedules

**Every 5 minutes** (lighter server load, slightly delayed notifications):
```cron
*/5 * * * * /usr/bin/php /var/www/gibbon/modules/NotificationEngine/cli/processQueue.php >> /var/log/gibbon/notifications.log 2>&1
```

**Hourly with purge** (run every hour and purge monthly):
```cron
# Process queue hourly
0 * * * * /usr/bin/php /var/www/gibbon/modules/NotificationEngine/cli/processQueue.php >> /var/log/gibbon/notifications.log 2>&1

# Purge old notifications daily at 2 AM
0 2 * * * /usr/bin/php /var/www/gibbon/modules/NotificationEngine/cli/processQueue.php --purge --purge-days=30 >> /var/log/gibbon/notifications.log 2>&1
```

### Docker Environments

If running Gibbon in Docker, you have two options:

**Option 1: Host-based cron**
```bash
# Run cron on the host, executing inside the container
* * * * * docker exec gibbon_php /usr/local/bin/php /var/www/html/modules/NotificationEngine/cli/processQueue.php >> /var/log/gibbon/notifications.log 2>&1
```

**Option 2: Container-based cron**
```dockerfile
# Add to your Dockerfile
RUN apt-get update && apt-get install -y cron

# Add crontab entry
RUN echo "* * * * * /usr/local/bin/php /var/www/html/modules/NotificationEngine/cli/processQueue.php >> /var/log/cron.log 2>&1" | crontab -

# Start cron in entrypoint
CMD cron && php-fpm
```

### Installation Steps

1. **Determine PHP Path:**
   ```bash
   which php
   # Output: /usr/bin/php (or /usr/local/bin/php)
   ```

2. **Determine Gibbon Path:**
   ```bash
   # Usually /var/www/gibbon or /var/www/html
   pwd
   ```

3. **Create Log Directory:**
   ```bash
   sudo mkdir -p /var/log/gibbon
   sudo chown www-data:www-data /var/log/gibbon
   ```

4. **Edit Crontab:**
   ```bash
   # For www-data user (recommended)
   sudo crontab -u www-data -e

   # Or for current user
   crontab -e
   ```

5. **Add Cron Entry:**
   ```cron
   * * * * * /usr/bin/php /var/www/gibbon/modules/NotificationEngine/cli/processQueue.php >> /var/log/gibbon/notifications.log 2>&1
   ```

6. **Verify Cron Job:**
   ```bash
   # List cron jobs for www-data user
   sudo crontab -u www-data -l

   # Check cron is running
   sudo systemctl status cron

   # Watch the log file
   tail -f /var/log/gibbon/notifications.log
   ```

### Testing

**Manual Test:**
```bash
# Run once to verify it works
php /var/www/gibbon/modules/NotificationEngine/cli/processQueue.php --verbose

# Expected output:
# [2026-02-16 12:00:00] Notification Queue Processor started
# [2026-02-16 12:00:00] Found 5 pending notification(s) to process
# [2026-02-16 12:00:01] Processing complete
```

**Dry Run Test:**
```bash
# See what would be processed without sending
php /var/www/gibbon/modules/NotificationEngine/cli/processQueue.php --dry-run --verbose
```

**Queue a Test Notification:**
```sql
-- Insert a test notification into the queue
INSERT INTO gibbonNotificationQueue (
    gibbonPersonID, type, title, body, channel, status, timestampCreated
) VALUES (
    1, -- Replace with valid gibbonPersonID
    'test',
    'Test Notification',
    'This is a test notification from the queue worker.',
    'both', -- email and push
    'pending',
    NOW()
);
```

Then run the processor and check the logs.

## Configuration

The queue processor uses settings from `Notification Engine` module:

| Setting | Description | Default |
|---------|-------------|---------|
| `queueBatchSize` | Maximum notifications to process per run | 50 |
| `maxRetryAttempts` | Maximum delivery attempts before giving up | 3 |
| `fcmEnabled` | Enable Firebase Cloud Messaging | Y |
| `smtpEnabled` | Enable email delivery | Y |

These can be configured via Gibbon admin panel: **System Admin > Third Party Settings > Notification Engine**

## Monitoring

### Check Queue Status

Via Gibbon UI:
- Navigate to **Modules > Notification Engine > Notification Queue**
- View pending, processing, sent, and failed notifications

Via MySQL:
```sql
-- Queue statistics
SELECT
    status,
    COUNT(*) as count,
    MIN(timestampCreated) as oldest,
    MAX(timestampCreated) as newest
FROM gibbonNotificationQueue
GROUP BY status;

-- Failed notifications
SELECT * FROM gibbonNotificationQueue
WHERE status = 'failed'
ORDER BY timestampCreated DESC
LIMIT 10;
```

### Log Files

Check the cron log for errors:
```bash
tail -f /var/log/gibbon/notifications.log
```

Look for:
- ✅ "Processing complete" - successful runs
- ❌ "Error:" - initialization or processing errors
- ⚠️ "Failed:" - individual notification failures

### Performance Metrics

The processor outputs statistics after each run:
```
[2026-02-16 12:00:00] Processing complete
==================
Processed: 25
Emails sent: 15
Push sent: 20
Skipped (user preferences): 5
Failed: 0

Queue status after - Pending: 0, Sent: 25, Failed: 0
```

## Troubleshooting

### Common Issues

**1. Cron job not running**
```bash
# Check cron service is running
sudo systemctl status cron

# Check crontab syntax
crontab -l

# Check system logs
grep CRON /var/log/syslog
```

**2. PHP errors**
```bash
# Check PHP path is correct
which php

# Test script manually
php /path/to/processQueue.php --verbose

# Check PHP error logs
tail -f /var/log/php-fpm/error.log
```

**3. Notifications stuck in 'pending'**
```bash
# Check if cron is running the processor
ps aux | grep processQueue

# Run manually with verbose output
php processQueue.php --verbose

# Check for errors in Gibbon logs
tail -f /var/www/gibbon/uploads/logs/*.log
```

**4. FCM push notifications failing**
```bash
# Verify FCM credentials are configured
echo $FIREBASE_CREDENTIALS_PATH

# Check FCM is enabled in settings
# Gibbon admin panel: Third Party Settings > Notification Engine

# Test FCM connectivity
php -r "var_dump(file_exists(getenv('FIREBASE_CREDENTIALS_PATH')));"
```

**5. Email notifications failing**
```bash
# Check SMTP settings in Gibbon
# System Admin > System Settings > Email

# Test email manually
# Use Gibbon's built-in email test tool
```

**6. High memory usage**
```bash
# Reduce batch size
php processQueue.php --limit=10

# Process in smaller batches
# Edit cron to run every 5 minutes instead of every minute
```

### Reset Stuck Notifications

If notifications are stuck in 'processing' state:

```sql
-- Reset stuck notifications older than 10 minutes
UPDATE gibbonNotificationQueue
SET status = 'pending', attempts = attempts + 1
WHERE status = 'processing'
  AND lastAttemptAt < DATE_SUB(NOW(), INTERVAL 10 MINUTE);
```

### Clear Failed Notifications

```sql
-- Delete failed notifications older than 7 days
DELETE FROM gibbonNotificationQueue
WHERE status = 'failed'
  AND timestampCreated < DATE_SUB(NOW(), INTERVAL 7 DAY);
```

## Production Recommendations

### High Volume Deployments

For schools with high notification volume:

1. **Increase Frequency:** Run every minute instead of every 5 minutes
2. **Increase Batch Size:** Set `queueBatchSize` to 100 or more
3. **Multiple Workers:** Run multiple parallel workers with smaller batches
4. **Database Optimization:** Add indexes on frequently queried columns
5. **Monitoring:** Set up alerts for failed notifications

### Example High-Volume Cron Setup

```cron
# Process queue every minute with higher batch size
* * * * * /usr/bin/php /var/www/gibbon/modules/NotificationEngine/cli/processQueue.php --limit=100 >> /var/log/gibbon/notifications.log 2>&1

# Purge old notifications weekly (Sunday at 3 AM)
0 3 * * 0 /usr/bin/php /var/www/gibbon/modules/NotificationEngine/cli/processQueue.php --purge --purge-days=14 >> /var/log/gibbon/notifications-purge.log 2>&1
```

### Monitoring Integration

Set up monitoring alerts for:
- Cron job failures
- High failure rate in notifications
- Growing queue backlog
- Invalid FCM tokens

Example with monitoring script:
```bash
#!/bin/bash
# /usr/local/bin/check_notification_queue.sh

PENDING=$(mysql -u gibbon -p'password' gibbon_db -se "SELECT COUNT(*) FROM gibbonNotificationQueue WHERE status='pending'")

if [ "$PENDING" -gt 100 ]; then
    echo "WARNING: $PENDING notifications pending in queue"
    # Send alert email or Slack message
fi
```

Add to cron:
```cron
*/15 * * * * /usr/local/bin/check_notification_queue.sh
```

## Security Considerations

1. **File Permissions:** Ensure CLI scripts are not web-accessible
   ```bash
   chmod 755 /var/www/gibbon/modules/NotificationEngine/cli/*.php
   ```

2. **Run as Appropriate User:** Use `www-data` or dedicated user, not root
   ```bash
   sudo crontab -u www-data -e
   ```

3. **Credential Protection:** Keep FCM credentials secure
   ```bash
   chmod 600 /path/to/firebase-credentials.json
   chown www-data:www-data /path/to/firebase-credentials.json
   ```

4. **Log Rotation:** Configure log rotation to prevent disk space issues
   ```bash
   # /etc/logrotate.d/gibbon-notifications
   /var/log/gibbon/notifications.log {
       daily
       rotate 7
       compress
       delaycompress
       missingok
       notifempty
   }
   ```

## Support

For issues or questions:
- Check Gibbon documentation: https://docs.gibbonedu.org
- Gibbon community forums: https://ask.gibbonedu.org
- NotificationEngine module documentation

## Version History

- **v1.0.00** - Initial release with queue processing, retry logic, and purge functionality
