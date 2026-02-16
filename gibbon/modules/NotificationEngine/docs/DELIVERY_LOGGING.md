# Notification Delivery Logging

## Overview

The Notification Delivery Logging system provides comprehensive tracking and analytics for all notification deliveries (email and push) in the LAYA NotificationEngine. It captures detailed information about each delivery attempt, enabling debugging, monitoring, and performance analysis.

## Features

### Core Logging Capabilities

1. **Detailed Delivery Tracking**
   - Records every delivery attempt (success, failure, skipped)
   - Tracks delivery timing in milliseconds
   - Stores error codes and messages
   - Captures provider responses (FCM message IDs, etc.)
   - Tracks attempt numbers for retries

2. **Multi-Channel Support**
   - Separate tracking for email and push notifications
   - Channel-specific analytics
   - Cross-channel comparison

3. **Performance Metrics**
   - Delivery time tracking in milliseconds
   - Average, min, and max delivery times
   - Identifies slow deliveries
   - Trending analysis

4. **Error Tracking**
   - Categorized error codes
   - Detailed error messages
   - Top errors by frequency
   - Last occurrence timestamps

5. **Analytics Dashboard**
   - Success/failure/skip rates
   - Delivery timeline (hourly breakdown)
   - Channel-specific statistics
   - Visual indicators for performance

## Database Schema

### gibbonNotificationDeliveryLog Table

```sql
CREATE TABLE `gibbonNotificationDeliveryLog` (
    `gibbonNotificationDeliveryLogID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonNotificationQueueID` INT UNSIGNED NOT NULL,
    `channel` ENUM('email','push') NOT NULL,
    `status` ENUM('success','failed','skipped') NOT NULL,
    `recipientIdentifier` VARCHAR(255) NULL,
    `attemptNumber` INT UNSIGNED NOT NULL DEFAULT 1,
    `errorCode` VARCHAR(50) NULL,
    `errorMessage` TEXT NULL,
    `responseData` JSON NULL,
    `deliveryTimeMs` INT UNSIGNED NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Indexes for performance
    KEY `idx_queue` (`gibbonNotificationQueueID`),
    KEY `idx_status` (`status`),
    KEY `idx_channel` (`channel`),
    KEY `idx_timestamp` (`timestampCreated`),
    KEY `idx_queue_channel` (`gibbonNotificationQueueID`, `channel`),
    FOREIGN KEY (`gibbonNotificationQueueID`)
        REFERENCES `gibbonNotificationQueue` (`gibbonNotificationQueueID`)
        ON DELETE CASCADE
) ENGINE=InnoDB;
```

### Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| `gibbonNotificationDeliveryLogID` | INT | Primary key |
| `gibbonNotificationQueueID` | INT | Reference to notification queue |
| `channel` | ENUM | 'email' or 'push' |
| `status` | ENUM | 'success', 'failed', or 'skipped' |
| `recipientIdentifier` | VARCHAR | Email address or FCM token (truncated) |
| `attemptNumber` | INT | Retry attempt number (1, 2, 3...) |
| `errorCode` | VARCHAR | Error code if failed |
| `errorMessage` | TEXT | Detailed error message |
| `responseData` | JSON | Provider response (FCM message ID, etc.) |
| `deliveryTimeMs` | INT | Time taken to deliver in milliseconds |
| `timestampCreated` | TIMESTAMP | When the attempt was made |

## Architecture

### Components

1. **DeliveryLogGateway** (`Domain/DeliveryLogGateway.php`)
   - Database operations for delivery logs
   - Query methods with filtering
   - Analytics methods
   - Purge old logs

2. **PushDelivery Integration** (`Domain/PushDelivery.php`)
   - Logs every push delivery attempt
   - Tracks FCM responses
   - Records token validation failures

3. **EmailDelivery Integration** (`Domain/EmailDelivery.php`)
   - Logs every email delivery attempt
   - Captures SMTP errors
   - Records user preference skips

4. **UI Dashboard** (`delivery_logs.php`)
   - Browse delivery logs with pagination
   - Filter by channel, status, date range
   - View statistics and analytics
   - Identify trends and issues

## Usage

### Automatic Logging

Delivery logging is **automatic** when using the queue worker. No code changes are required to enable it.

**When logs are created:**
- ✅ Queue worker processes notifications (`cli/processQueue.php`)
- ✅ Email delivery via `EmailDelivery::send()`
- ✅ Push delivery via `PushDelivery::send()`, `sendMulticast()`, `sendToUser()`

**What gets logged:**
- Every delivery attempt (1st attempt, retries)
- Successful deliveries with timing
- Failed deliveries with error details
- Skipped deliveries (user preferences)

### Viewing Delivery Logs

1. **Via Web UI:**
   - Navigate to: `Notification Engine > Delivery Logs`
   - Filter by channel, status, or date range
   - View detailed statistics
   - Click "View Details" for full response data

2. **Via Database:**
   ```sql
   -- Recent failures
   SELECT * FROM gibbonNotificationDeliveryLog
   WHERE status = 'failed'
   ORDER BY timestampCreated DESC
   LIMIT 20;

   -- Success rate by channel
   SELECT
       channel,
       COUNT(*) as total,
       SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
       ROUND(100.0 * SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) / COUNT(*), 2) as success_rate
   FROM gibbonNotificationDeliveryLog
   GROUP BY channel;
   ```

### API Methods

#### DeliveryLogGateway Methods

```php
// Log a delivery attempt
$deliveryLogGateway->logDelivery([
    'gibbonNotificationQueueID' => 123,
    'channel' => 'email',
    'status' => 'success',
    'recipientIdentifier' => 'user@example.com',
    'attemptNumber' => 1,
    'deliveryTimeMs' => 245,
]);

// Get logs for a notification
$logs = $deliveryLogGateway->getLogsByNotificationID(123);

// Get delivery statistics
$stats = $deliveryLogGateway->getDeliveryStatistics('2024-01-01', '2024-01-31');

// Get success rates
$rates = $deliveryLogGateway->getSuccessRates('2024-01-01', '2024-01-31');

// Get top errors
$errors = $deliveryLogGateway->getTopErrors(10, 'push');

// Get delivery timeline
$timeline = $deliveryLogGateway->getDeliveryTimeline('2024-01-01', '2024-01-31');

// Get average delivery times
$avgTimes = $deliveryLogGateway->getAverageDeliveryTimes();

// Get recent failures
$failures = $deliveryLogGateway->getRecentFailures(20);

// Purge old logs (older than 90 days)
$purged = $deliveryLogGateway->purgeOldLogs(90);
```

## Analytics

### Success Rate Calculation

```
Success Rate = (Successful Deliveries / Total Deliveries) * 100
```

**Note:** Skipped deliveries (due to user preferences) are **not** counted as failures. They represent successful enforcement of user preferences.

### Performance Benchmarks

| Channel | Good | Moderate | Slow |
|---------|------|----------|------|
| Email   | < 100ms | 100-500ms | > 500ms |
| Push    | < 200ms | 200-1000ms | > 1000ms |

### Common Error Codes

#### Email Channel
- `GLOBAL_DISABLED` - Email notifications disabled in settings
- `INVALID_EMAIL` - Recipient has no valid email address
- `USER_DISABLED` - User disabled email notifications
- `TYPE_DISABLED` - User disabled this notification type
- `SEND_FAILED` - SMTP send failed
- `EXCEPTION` - Unexpected error

#### Push Channel
- `FCM_DISABLED` - FCM disabled in settings
- `CREDENTIALS_MISSING` - Firebase credentials not configured
- `TOKEN_NOT_FOUND` - Invalid/expired FCM token
- `INVALID_MESSAGE` - Message format error
- `SEND_FAILED` - FCM send failed
- `NO_TOKENS` - User has no registered devices
- `TYPE_DISABLED` - User disabled push for this type

## Maintenance

### Log Retention

Delivery logs can accumulate quickly in high-volume environments. Recommended retention:

- **Development:** 7-30 days
- **Production:** 30-90 days
- **Compliance:** As required by data retention policies

### Purging Old Logs

**Manual Purge (SQL):**
```sql
DELETE FROM gibbonNotificationDeliveryLog
WHERE timestampCreated < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

**Via API:**
```php
$deliveryLogGateway = $container->get(DeliveryLogGateway::class);
$purged = $deliveryLogGateway->purgeOldLogs(90); // Days to keep
echo "Purged {$purged} old log entries";
```

**Automated (Cron):**
```bash
# Add to crontab - purge logs older than 90 days daily at 2 AM
0 2 * * * php /path/to/gibbon/modules/NotificationEngine/cli/purgeLogs.php --days=90
```

### Database Indexes

The following indexes optimize query performance:

- `idx_queue` - Filter by notification ID
- `idx_status` - Filter by status
- `idx_channel` - Filter by channel
- `idx_timestamp` - Date range queries
- `idx_queue_channel` - Combined filter queries

**Monitor index usage:**
```sql
SHOW INDEX FROM gibbonNotificationDeliveryLog;
```

## Troubleshooting

### High Failure Rate

1. **Check error codes in delivery logs**
   ```php
   $errors = $deliveryLogGateway->getTopErrors(10);
   ```

2. **Review system settings**
   - Email: Check SMTP configuration
   - Push: Verify Firebase credentials

3. **Validate recipient data**
   - Ensure email addresses are valid
   - Check FCM tokens are current

### Slow Delivery Times

1. **Identify slow channels**
   ```php
   $avgTimes = $deliveryLogGateway->getAverageDeliveryTimes();
   ```

2. **Check for network issues**
   - SMTP server response time
   - FCM API latency

3. **Optimize batch size**
   - Reduce queue batch size if needed
   - Use multicast for push notifications

### Missing Logs

**Possible causes:**
1. DeliveryLogGateway not injected
2. Logging disabled in code
3. Database migration not run

**Verification:**
```php
// Check if table exists
$query = "SHOW TABLES LIKE 'gibbonNotificationDeliveryLog'";
$result = $pdo->query($query);
if ($result->rowCount() === 0) {
    echo "Table missing - run database migration";
}
```

## Best Practices

### Do's ✅

- **Monitor delivery success rates regularly**
- **Set up alerts for high failure rates**
- **Purge old logs periodically**
- **Review top errors weekly**
- **Use logs for capacity planning**
- **Track performance trends**

### Don'ts ❌

- **Don't store sensitive data in logs** (passwords, tokens)
- **Don't disable logging in production** (needed for debugging)
- **Don't let logs grow indefinitely** (impacts performance)
- **Don't ignore high failure rates** (indicates systemic issues)

## Performance Impact

### Storage Requirements

Approximate log entry size: **500 bytes**

| Volume | Entries/Day | Storage/Month |
|--------|-------------|---------------|
| Low    | 100         | ~1.5 MB       |
| Medium | 1,000       | ~15 MB        |
| High   | 10,000      | ~150 MB       |
| Very High | 100,000  | ~1.5 GB       |

### Query Performance

With proper indexes, typical queries:
- Filter by channel/status: **< 50ms**
- Date range query: **< 100ms**
- Analytics aggregation: **< 200ms**
- Purge operation: **< 500ms**

## Security Considerations

1. **PII Protection**
   - Email addresses are stored (required for tracking)
   - FCM tokens are truncated (first 20 chars + ...)
   - No passwords or credentials logged

2. **Access Control**
   - Logs visible only to authorized staff
   - Filter by user permissions if needed
   - Audit log access for compliance

3. **Data Retention**
   - Follow organizational data retention policies
   - Purge logs when no longer needed
   - Anonymize if required by regulation

## Migration

The delivery logging table is created automatically via CHANGEDB.php (v1.2.00).

**To apply migration:**
1. Navigate to: Admin > System Admin > Module Management
2. Select "NotificationEngine"
3. Click "Update" to run migrations

**Verify migration:**
```sql
SELECT * FROM gibbonSetting
WHERE scope = 'Notification Engine'
AND name = 'version';
-- Should show version >= 1.2.00
```

## Support

For issues or questions:
1. Check this documentation
2. Review delivery logs for error details
3. Check common error codes section
4. Contact system administrator

## Changelog

### v1.2.00 (2024-02-16)
- ✅ Added gibbonNotificationDeliveryLog table
- ✅ Implemented DeliveryLogGateway
- ✅ Integrated logging into PushDelivery
- ✅ Integrated logging into EmailDelivery
- ✅ Created delivery_logs.php UI
- ✅ Added analytics methods
- ✅ Documented system
