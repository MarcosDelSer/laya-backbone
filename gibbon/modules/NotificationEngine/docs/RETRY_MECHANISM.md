# Notification Retry Mechanism

## Overview

The NotificationEngine module implements a sophisticated **exponential backoff retry mechanism** for failed notification deliveries. This ensures temporary failures (network issues, service outages, rate limits) don't result in lost notifications while preventing system overload from repeated immediate retries.

## How It Works

### Exponential Backoff Strategy

When a notification fails to deliver, the system automatically retries with progressively increasing delays:

| Attempt | Delay (base 5 min) | Formula |
|---------|-------------------|---------|
| 1 (initial) | 0 minutes | No delay |
| 2 (1st retry) | 5 minutes | 5 × 2^0 |
| 3 (2nd retry) | 10 minutes | 5 × 2^1 |
| 4 (3rd retry) | 20 minutes | 5 × 2^2 |

**Formula:** `retryDelayMinutes × 2^(attempts-1)`

### Configuration Settings

The retry mechanism is controlled by three settings in `gibbonSetting`:

```php
// Maximum number of retry attempts before marking as permanently failed
'maxRetryAttempts' => 3  // Default: 3

// Base delay in minutes (exponential backoff applied)
'retryDelayMinutes' => 5  // Default: 5 minutes

// Number of notifications to process per queue run
'queueBatchSize' => 50  // Default: 50
```

Configure these via the Gibbon admin panel:
**Admin** → **System Admin** → **Notification Engine** → **Settings**

## Retry Flow

### 1. Initial Delivery Attempt

```
[Event occurs] → [Notification queued] → [Queue worker runs]
  ↓
[Attempt 1: status=pending, attempts=0]
  ↓
[Delivery attempted] ----→ [SUCCESS] → status=sent ✓
  ↓
[FAILURE]
  ↓
status=pending, attempts=1, lastAttemptAt=NOW
errorMessage saved
```

### 2. Retry Attempts

```
[Queue worker runs 5 min later]
  ↓
[Check retry eligibility]
  - attempts < maxRetryAttempts? ✓
  - Sufficient time passed? (lastAttemptAt + 5 min ≤ NOW) ✓
  ↓
[Attempt 2: attempts=1]
  ↓
[Delivery attempted] ----→ [SUCCESS] → status=sent ✓
  ↓
[FAILURE]
  ↓
status=pending, attempts=2, lastAttemptAt=NOW
Next retry in 10 minutes
```

### 3. Permanent Failure

```
[Queue worker runs 20 min later]
  ↓
[Attempt 4: attempts=3]
  ↓
[FAILURE]
  ↓
attempts >= maxRetryAttempts
  ↓
status=failed (permanently) ✗
```

## Database Schema

### gibbonNotificationQueue

```sql
CREATE TABLE gibbonNotificationQueue (
    gibbonNotificationQueueID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gibbonPersonID INT UNSIGNED NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    data JSON NULL,
    channel ENUM('email','push','both') NOT NULL DEFAULT 'both',
    status ENUM('pending','processing','sent','failed') NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    lastAttemptAt DATETIME NULL,
    sentAt DATETIME NULL,
    errorMessage TEXT NULL,
    timestampCreated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY status_attempts (status, attempts)
);
```

**Key Fields:**
- `attempts`: Number of delivery attempts made (0 = first attempt pending)
- `lastAttemptAt`: Timestamp of last delivery attempt (used for backoff calculation)
- `status`: Current state (`pending`, `processing`, `sent`, `failed`)
- `errorMessage`: Error from last failed attempt

## API Methods

### NotificationGateway Methods

#### selectPendingNotifications($limit, $maxAttempts, $retryDelayMinutes)

Fetches notifications ready for delivery/retry, respecting exponential backoff.

```php
$notifications = $notificationGateway->selectPendingNotifications(
    50,  // limit
    3,   // maxAttempts
    5    // retryDelayMinutes
);

// Returns only notifications where:
// - attempts < maxAttempts
// - attempts = 0 OR lastAttemptAt + backoff_delay <= NOW
```

#### calculateRetryDelay($attemptNumber, $retryDelayMinutes)

Calculate delay for specific attempt number.

```php
$delay = $notificationGateway->calculateRetryDelay(2, 5);
// Returns: 10 (minutes) for 2nd attempt
```

#### getNextRetryTime($notification, $retryDelayMinutes)

Get timestamp when notification should be retried.

```php
$nextRetry = $notificationGateway->getNextRetryTime($notification, 5);
// Returns: '2026-02-16 14:30:00' or null if ready now
```

#### isReadyForRetry($notification, $retryDelayMinutes)

Check if notification is ready for retry.

```php
$isReady = $notificationGateway->isReadyForRetry($notification, 5);
// Returns: true/false
```

#### getRetryInfo($gibbonNotificationQueueID, $maxAttempts, $retryDelayMinutes)

Get detailed retry information for a notification.

```php
$info = $notificationGateway->getRetryInfo(123, 3, 5);

// Returns:
[
    'gibbonNotificationQueueID' => 123,
    'currentAttempts' => 2,
    'maxAttempts' => 3,
    'retriesRemaining' => 1,
    'hasMoreRetries' => true,
    'lastAttemptAt' => '2026-02-16 14:15:00',
    'nextRetryAt' => '2026-02-16 14:25:00',
    'isReadyForRetry' => false,
    'status' => 'pending',
    'errorMessage' => 'Connection timeout',
    'currentDelayMinutes' => 10,
    'nextDelayMinutes' => 20,
]
```

#### getRetryStatistics()

Get retry statistics grouped by attempt number.

```php
$stats = $notificationGateway->getRetryStatistics();

// Returns:
[
    ['attempts' => 1, 'count' => 12, 'pending_count' => 8, 'failed_count' => 4],
    ['attempts' => 2, 'count' => 5, 'pending_count' => 3, 'failed_count' => 2],
    ['attempts' => 3, 'count' => 2, 'pending_count' => 0, 'failed_count' => 2],
]
```

#### selectNotificationsPendingRetry($retryDelayMinutes)

Get notifications waiting for backoff delay.

```php
$waiting = $notificationGateway->selectNotificationsPendingRetry(5);

// Returns notifications with:
// - status='pending'
// - attempts > 0
// - Not yet ready for retry (still in backoff period)
// - Includes 'nextRetryAt' field
```

#### getRetryHealthMetrics()

Get overall retry system health metrics.

```php
$health = $notificationGateway->getRetryHealthMetrics();

// Returns:
[
    'total_retrying' => 25,
    'avg_attempts' => 1.8,
    'max_attempts' => 3,
    'oldest_retry' => '2026-02-16 10:00:00',
    'pending_retry_count' => 18,
    'permanently_failed_count' => 7,
    'total_notifications' => 1000,
    'sent_count' => 960,
    'failed_count' => 15,
    'recovered_by_retry_count' => 25,
    'success_rate' => 96.0,
    'failure_rate' => 1.5,
    'retry_recovery_rate' => 2.6,
]
```

## Usage Examples

### Example 1: Manual Retry Check

```php
use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;

$notificationGateway = $container->get(NotificationGateway::class);

// Get notification
$notification = $notificationGateway->getNotificationByID(123);

// Check if ready for retry
if ($notificationGateway->isReadyForRetry($notification, 5)) {
    echo "Ready for retry now";
} else {
    $nextRetry = $notificationGateway->getNextRetryTime($notification, 5);
    echo "Next retry at: " . $nextRetry;
}

// Get detailed retry info
$info = $notificationGateway->getRetryInfo(123, 3, 5);
echo "Retries remaining: " . $info['retriesRemaining'];
echo "Current delay: " . $info['currentDelayMinutes'] . " minutes";
```

### Example 2: Monitoring Retry Health

```php
$health = $notificationGateway->getRetryHealthMetrics();

// Check if retry queue is healthy
if ($health['pending_retry_count'] > 100) {
    // Alert: Large retry backlog
    error_log("WARNING: {$health['pending_retry_count']} notifications waiting for retry");
}

// Monitor recovery rate
echo "Retry recovery rate: {$health['retry_recovery_rate']}%\n";
echo "Success rate: {$health['success_rate']}%\n";
echo "Failure rate: {$health['failure_rate']}%\n";

// Check for stuck notifications
if ($health['oldest_retry']) {
    $oldestRetryAge = (time() - strtotime($health['oldest_retry'])) / 3600;
    if ($oldestRetryAge > 24) {
        error_log("WARNING: Oldest retry is {$oldestRetryAge} hours old");
    }
}
```

### Example 3: Custom Retry Logic

```php
// Process notifications with custom retry settings
$customDelay = 10; // 10 minute base delay
$customMaxAttempts = 5; // 5 total attempts

$notifications = $notificationGateway->selectPendingNotifications(
    100,
    $customMaxAttempts,
    $customDelay
);

foreach ($notifications as $notification) {
    $retryInfo = $notificationGateway->getRetryInfo(
        $notification['gibbonNotificationQueueID'],
        $customMaxAttempts,
        $customDelay
    );

    echo "Processing notification #{$notification['gibbonNotificationQueueID']}\n";
    echo "Attempt {$retryInfo['currentAttempts']} of {$retryInfo['maxAttempts']}\n";
    echo "Current delay: {$retryInfo['currentDelayMinutes']} minutes\n";

    // Process notification...
}
```

## CLI Queue Worker Integration

The retry mechanism is fully integrated into `cli/processQueue.php`:

```bash
# Process queue (respects retry delays automatically)
php cli/processQueue.php --verbose

# Output includes retry information:
# Retry settings - Max attempts: 3, Base delay: 5 minutes (exponential backoff)
# Queue status - Pending: 25, Processing: 0, Failed: 7
# Notifications waiting for retry backoff: 18
# Average attempts: 1.8
```

### Verbose Output

```bash
php cli/processQueue.php --verbose

# Sample output:
[2026-02-16 14:30:00] Notification Queue Processor started
[2026-02-16 14:30:00] Limit: 50, Dry Run: No
[2026-02-16 14:30:00] Queue status - Pending: 25, Processing: 0, Failed: 7
[2026-02-16 14:30:00] Retry settings - Max attempts: 3, Base delay: 5 minutes (exponential backoff)
[2026-02-16 14:30:00] Found 12 pending notification(s) to process
[2026-02-16 14:30:01] Processing notification #123 (checkIn, channel: both)
[2026-02-16 14:30:01]   Sending email to parent@example.com
[2026-02-16 14:30:02]   Email sent successfully
[2026-02-16 14:30:02]   Sending push notification
[2026-02-16 14:30:03]   Push sent successfully
[2026-02-16 14:30:03]   Notification #123 marked as sent
```

## Monitoring and Alerting

### Key Metrics to Monitor

1. **Retry Queue Size**
   ```php
   $health = $notificationGateway->getRetryHealthMetrics();
   if ($health['pending_retry_count'] > 100) {
       // Alert: Large retry backlog
   }
   ```

2. **Permanent Failure Rate**
   ```php
   if ($health['failure_rate'] > 5.0) {
       // Alert: High permanent failure rate
   }
   ```

3. **Retry Recovery Rate**
   ```php
   // Percentage of sent notifications that required retries
   echo "Retry recovery: {$health['retry_recovery_rate']}%";
   ```

4. **Oldest Retry Age**
   ```php
   $oldestRetry = $health['oldest_retry'];
   $ageHours = (time() - strtotime($oldestRetry)) / 3600;
   if ($ageHours > 24) {
       // Alert: Notifications stuck in retry queue
   }
   ```

### Database Queries for Monitoring

```sql
-- Get notifications in retry
SELECT gibbonNotificationQueueID, type, attempts, lastAttemptAt, errorMessage
FROM gibbonNotificationQueue
WHERE status = 'pending' AND attempts > 0
ORDER BY lastAttemptAt ASC;

-- Get permanently failed notifications
SELECT gibbonNotificationQueueID, type, attempts, errorMessage, timestampCreated
FROM gibbonNotificationQueue
WHERE status = 'failed'
ORDER BY timestampCreated DESC
LIMIT 50;

-- Retry success rate by attempt number
SELECT
    attempts,
    COUNT(*) as total,
    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as succeeded,
    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
    ROUND(SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as success_rate
FROM gibbonNotificationQueue
WHERE attempts > 0
GROUP BY attempts;
```

## Troubleshooting

### Problem: Notifications not retrying

**Symptoms:**
- Notifications stuck with status='pending', attempts > 0
- Queue worker runs but doesn't process them

**Solutions:**
1. Check if retry delay has passed:
   ```php
   $info = $notificationGateway->getRetryInfo($id, 3, 5);
   echo "Next retry at: " . $info['nextRetryAt'];
   echo "Ready: " . ($info['isReadyForRetry'] ? 'Yes' : 'No');
   ```

2. Verify settings:
   ```sql
   SELECT * FROM gibbonSetting
   WHERE scope = 'Notification Engine'
   AND name IN ('maxRetryAttempts', 'retryDelayMinutes');
   ```

3. Check queue worker is running:
   ```bash
   php cli/processQueue.php --verbose
   ```

### Problem: Too many retries failing

**Symptoms:**
- High `failure_rate` in retry metrics
- Many notifications reaching `maxRetryAttempts`

**Solutions:**
1. Check error messages:
   ```sql
   SELECT errorMessage, COUNT(*) as count
   FROM gibbonNotificationQueue
   WHERE status = 'failed'
   GROUP BY errorMessage
   ORDER BY count DESC;
   ```

2. Increase retry attempts or delay:
   ```sql
   UPDATE gibbonSetting
   SET value = '5'
   WHERE scope = 'Notification Engine'
   AND name = 'maxRetryAttempts';

   UPDATE gibbonSetting
   SET value = '10'
   WHERE scope = 'Notification Engine'
   AND name = 'retryDelayMinutes';
   ```

3. Check service health (email server, FCM)

### Problem: Retry queue growing

**Symptoms:**
- `pending_retry_count` continuously increasing
- Queue worker can't keep up

**Solutions:**
1. Increase batch size:
   ```sql
   UPDATE gibbonSetting
   SET value = '100'
   WHERE scope = 'Notification Engine'
   AND name = 'queueBatchSize';
   ```

2. Run queue worker more frequently:
   ```bash
   # Change from every 5 minutes to every minute
   * * * * * php /path/to/cli/processQueue.php
   ```

3. Use dedicated worker:
   ```bash
   php cli/worker.php --interval=30 --batch-size=100
   ```

## Best Practices

### 1. Configuration

- **Development:** Lower delays for faster testing
  ```
  retryDelayMinutes = 1
  maxRetryAttempts = 2
  ```

- **Production:** Standard settings for reliability
  ```
  retryDelayMinutes = 5
  maxRetryAttempts = 3
  ```

- **High-volume:** Optimize for throughput
  ```
  retryDelayMinutes = 10
  maxRetryAttempts = 5
  queueBatchSize = 200
  ```

### 2. Monitoring

- Monitor retry metrics daily
- Alert on failure_rate > 5%
- Alert on pending_retry_count > 100
- Review permanently failed notifications weekly

### 3. Error Handling

- Log detailed error messages for debugging
- Use delivery logging for analytics
- Regularly review common error patterns

### 4. Performance

- Keep batch size appropriate for cron frequency
- Use exponential backoff to avoid overwhelming external services
- Purge old notifications regularly:
  ```bash
  php cli/processQueue.php --purge --purge-days=30
  ```

## Advanced Configuration

### Custom Backoff Strategy

To implement custom backoff logic, modify `NotificationGateway::calculateRetryDelay()`:

```php
public function calculateRetryDelay($attemptNumber, $retryDelayMinutes = 5)
{
    if ($attemptNumber <= 0) {
        return 0;
    }

    // Linear backoff: attemptNumber * retryDelayMinutes
    // return $attemptNumber * $retryDelayMinutes;

    // Exponential backoff (default)
    return (int) ($retryDelayMinutes * pow(2, $attemptNumber - 1));

    // Fibonacci backoff
    // $fib = [1, 1, 2, 3, 5, 8, 13];
    // return $retryDelayMinutes * ($fib[$attemptNumber] ?? 13);
}
```

### Per-Type Retry Settings

To implement different retry settings per notification type:

```php
// Add to NotificationGateway
public function getRetrySettingsByType($type)
{
    $settings = [
        'incident' => ['maxAttempts' => 5, 'delayMinutes' => 10], // Critical
        'checkIn' => ['maxAttempts' => 3, 'delayMinutes' => 5],   // Standard
        'photo' => ['maxAttempts' => 2, 'delayMinutes' => 15],    // Non-critical
    ];

    return $settings[$type] ?? ['maxAttempts' => 3, 'delayMinutes' => 5];
}
```

## Related Documentation

- [CLI Queue Worker](../cli/README.md) - Queue processing documentation
- [Delivery Logging](DELIVERY_LOGGING.md) - Delivery analytics and tracking
- [NotificationEngine README](../README.md) - Main module documentation

## Version History

- **v1.0.00** - Initial retry mechanism with exponential backoff (2026-02-16)
