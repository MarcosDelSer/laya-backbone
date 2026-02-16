# AISync CLI Tools

This directory contains command-line tools for the AISync module.

## retryQueue.php

Cron-based retry queue processor for failed webhook syncs.

### Purpose

Automatically retries failed webhook sync attempts using an exponential backoff strategy. This ensures that temporary network issues or service outages don't result in permanent data loss.

### Features

- **Exponential Backoff**: Retry delays increase exponentially (30s, 60s, 120s, etc.)
- **Configurable Retry Limits**: Respects max retry attempts from settings
- **Batch Processing**: Processes multiple failed syncs in a single run
- **Dry Run Mode**: Test the processor without actually retrying syncs
- **Purge Old Logs**: Cleanup old sync logs to prevent database bloat
- **Detailed Logging**: Verbose mode for debugging

### Usage

```bash
# Basic usage (process up to 50 failed syncs)
php /path/to/gibbon/modules/AISync/cli/retryQueue.php

# Process specific number of syncs
php retryQueue.php --limit=100

# Dry run to see what would be processed
php retryQueue.php --dry-run --verbose

# Force retry regardless of backoff period
php retryQueue.php --force

# Purge old logs (30 days)
php retryQueue.php --purge

# Purge logs older than 60 days
php retryQueue.php --purge --purge-days=60

# Show help
php retryQueue.php --help
```

### Cron Setup

Add to your crontab to run every 5 minutes:

```cron
# AISync retry queue processor - runs every 5 minutes
*/5 * * * * /usr/bin/php /path/to/gibbon/modules/AISync/cli/retryQueue.php >> /var/log/aisync-retry.log 2>&1
```

For more verbose output:

```cron
# AISync retry queue processor with verbose logging
*/5 * * * * /usr/bin/php /path/to/gibbon/modules/AISync/cli/retryQueue.php --verbose >> /var/log/aisync-retry.log 2>&1
```

Daily purge of old logs:

```cron
# AISync purge old logs - runs daily at 2 AM
0 2 * * * /usr/bin/php /path/to/gibbon/modules/AISync/cli/retryQueue.php --purge --purge-days=30 >> /var/log/aisync-purge.log 2>&1
```

### Retry Strategy

The processor uses exponential backoff to avoid overwhelming the AI service:

1. **First retry**: After 30 seconds (base delay)
2. **Second retry**: After 60 seconds (30 × 2^1)
3. **Third retry**: After 120 seconds (30 × 2^2)
4. **Max exceeded**: After 3 failed attempts, sync is marked as permanently failed

The base delay and max retry attempts are configurable in the AISync module settings.

### Configuration

Settings are managed in Gibbon's AISync module settings:

- **syncEnabled**: Enable/disable AI sync
- **aiServiceURL**: AI service base URL
- **webhookTimeout**: Timeout for webhook requests (seconds)
- **maxRetryAttempts**: Maximum number of retry attempts (default: 3)
- **retryDelaySeconds**: Base retry delay in seconds (default: 30)

### Exit Codes

- `0`: Success (all retries succeeded or no syncs to process)
- `1`: Failure (one or more retries failed)

### Requirements

- PHP 7.4 or higher
- Gibbon CMS installation
- GuzzleHttp library
- Database access
- Cron or task scheduler

### Troubleshooting

**No syncs being processed:**
- Check if AI Sync is enabled in settings
- Verify there are failed syncs in the database: `SELECT * FROM gibbonAISyncLog WHERE status = 'failed'`
- Ensure syncs are past their backoff period (use `--force` to override)

**Retries keep failing:**
- Verify AI service URL is correct
- Check network connectivity
- Review error messages in gibbonAISyncLog table
- Ensure JWT_SECRET_KEY environment variable is set

**Script can't find gibbon.php:**
- Run from the Gibbon root directory
- Or provide the full path to the script

### Database Schema

The processor uses the `gibbonAISyncLog` table:

```sql
CREATE TABLE gibbonAISyncLog (
    gibbonAISyncLogID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    eventType VARCHAR(100) NOT NULL,
    entityType VARCHAR(50) NOT NULL,
    entityID INT UNSIGNED NOT NULL,
    payload TEXT,
    status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
    response TEXT,
    errorMessage TEXT,
    retryCount INT UNSIGNED DEFAULT 0,
    timestampCreated DATETIME NOT NULL,
    timestampProcessed DATETIME,
    PRIMARY KEY (gibbonAISyncLogID),
    INDEX (status, retryCount),
    INDEX (timestampCreated)
);
```

### Monitoring

Monitor the retry queue with SQL queries:

```sql
-- Check failed syncs by event type
SELECT eventType, COUNT(*) as count
FROM gibbonAISyncLog
WHERE status = 'failed'
GROUP BY eventType;

-- Check syncs eligible for retry
SELECT COUNT(*) as eligible_for_retry
FROM gibbonAISyncLog
WHERE status = 'failed'
AND retryCount < 3
AND TIMESTAMPDIFF(SECOND, timestampProcessed, NOW()) >= (30 * POW(2, retryCount));

-- Check syncs that have exceeded max retries
SELECT eventType, entityType, entityID, errorMessage
FROM gibbonAISyncLog
WHERE status = 'failed'
AND retryCount >= 3
ORDER BY timestampCreated DESC;
```

### See Also

- [AISync Module Documentation](../README.md)
- [Webhook Integration Guide](../docs/webhook-integration.md)
- [Manual Re-sync CLI Command](./resync.php)
