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

## resync.php

Manual re-sync command for backfilling data and recovering from sync failures.

### Purpose

Manually triggers re-synchronization of specific entities or entity types to the AI service. This is useful for:

- **Backfilling data**: Sync historical data that existed before AISync was enabled
- **Recovery**: Re-sync entities after fixing AI service issues
- **Testing**: Validate webhook integrations with real data
- **Selective sync**: Re-sync specific entities or date ranges

### Features

- **Entity Type Selection**: Re-sync activities, meals, naps, attendance, or photos
- **Flexible Filtering**: By ID, date range, or all entities of a type
- **Batch Processing**: Process multiple entities with configurable limits
- **Dry Run Mode**: Preview what would be synced without actually syncing
- **Synchronous Execution**: Waits for webhook responses for immediate feedback
- **Detailed Logging**: Verbose mode for debugging and monitoring

### Usage

```bash
# Re-sync a specific activity
php /path/to/gibbon/modules/AISync/cli/resync.php --type=activity --id=123

# Re-sync all meals from the last week
php resync.php --type=meal --since=2026-02-09

# Re-sync all photos (dry run to see what would be synced)
php resync.php --type=photo --dry-run --verbose

# Re-sync attendance records between two dates
php resync.php --type=attendance --since=2026-02-01 --until=2026-02-15

# Re-sync naps with a limit
php resync.php --type=nap --limit=50

# Show help
php resync.php --help
```

### Options

- `--type=TYPE` (required): Entity type to re-sync
  - Valid types: `activity`, `meal`, `nap`, `attendance`, `photo`
- `--id=ID`: Specific entity ID to re-sync
- `--since=DATE`: Re-sync entities created/modified since date (YYYY-MM-DD)
- `--until=DATE`: Re-sync entities created/modified until date (YYYY-MM-DD)
- `--limit=N`: Maximum number of entities to re-sync (default: 100)
- `--dry-run`: Show what would be synced without actually syncing
- `--verbose`: Show detailed output with timestamps
- `--force`: Re-sync even if already successfully synced (future enhancement)
- `--help`: Show help message

### Entity Types

**activity**
- Table: `gibbonCareActivity`
- Event: `care_activity_created`
- Includes: Activity name, type, duration, participation, notes

**meal**
- Table: `gibbonCareMeal`
- Event: `meal_logged`
- Includes: Meal type, food items, amount eaten, notes

**nap**
- Table: `gibbonCareNap`
- Event: `nap_logged`
- Includes: Start/end time, duration, quality, notes

**attendance**
- Table: `gibbonCareAttendance`
- Events: `child_checked_in`, `child_checked_out`
- Includes: Check-in/out times, checked by, notes

**photo**
- Table: `gibbonPhoto`
- Event: `photo_uploaded`
- Includes: File path, caption, timestamp, uploaded by

### Examples

**Backfill all activities from January 2026:**
```bash
php resync.php --type=activity --since=2026-01-01 --until=2026-01-31 --verbose
```

**Test photo sync with dry run:**
```bash
php resync.php --type=photo --limit=10 --dry-run --verbose
```

**Re-sync a specific meal that failed:**
```bash
php resync.php --type=meal --id=456
```

**Backfill all data types for a specific date:**
```bash
php resync.php --type=activity --since=2026-02-15 --until=2026-02-15
php resync.php --type=meal --since=2026-02-15 --until=2026-02-15
php resync.php --type=nap --since=2026-02-15 --until=2026-02-15
php resync.php --type=attendance --since=2026-02-15 --until=2026-02-15
```

### Exit Codes

- `0`: Success (all re-syncs succeeded or no entities to process)
- `1`: Failure (one or more re-syncs failed)

### Requirements

- PHP 7.4 or higher
- Gibbon CMS installation
- GuzzleHttp library
- Database access
- AI Sync enabled in settings

### Troubleshooting

**No entities found:**
- Check date format is YYYY-MM-DD
- Verify entity ID exists in the database
- Check the date range includes actual data

**Re-sync fails:**
- Verify AI service URL is correct
- Check network connectivity
- Ensure JWT_SECRET_KEY environment variable is set
- Review error messages in output

**Script can't find gibbon.php:**
- Run from the Gibbon root directory
- Or provide the full path to the script

### Best Practices

1. **Use dry-run first**: Always test with `--dry-run --verbose` before syncing
2. **Limit batch sizes**: Start with small limits (10-50) for testing
3. **Sync chronologically**: Use date ranges to sync data in order
4. **Monitor output**: Use `--verbose` to track progress
5. **Check sync logs**: Review `gibbonAISyncLog` table after re-sync

### Monitoring

Check re-sync results:

```sql
-- Check recent syncs
SELECT eventType, entityType, entityID, status, timestampProcessed
FROM gibbonAISyncLog
ORDER BY timestampProcessed DESC
LIMIT 20;

-- Check success rate by entity type
SELECT entityType, status, COUNT(*) as count
FROM gibbonAISyncLog
GROUP BY entityType, status
ORDER BY entityType, status;
```

### See Also

- [AISync Module Documentation](../README.md)
- [Webhook Integration Guide](../docs/webhook-integration.md)
- [Retry Queue Processor](./retryQueue.php)
