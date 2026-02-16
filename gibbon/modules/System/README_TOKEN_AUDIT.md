# Token Exchange Logging and Audit Trail

## Overview

The Token Exchange Logging and Audit Trail system provides comprehensive security monitoring and analytics for JWT token generation from Gibbon PHP sessions. This system logs all token exchange attempts, both successful and failed, to enable security auditing, troubleshooting, and compliance reporting.

## Features

### Core Capabilities

1. **Comprehensive Audit Logging**
   - Every token exchange attempt is logged
   - Success and failure tracking
   - IP address and user agent capture
   - Session ID correlation
   - Role mapping audit trail

2. **Security Monitoring**
   - Failed attempt detection
   - Suspicious activity identification
   - Multi-login pattern analysis
   - IP-based access tracking

3. **Analytics and Reporting**
   - Token exchange statistics
   - Role-based usage analytics
   - Hourly activity charts
   - User-specific history

4. **Data Retention Management**
   - Automatic cleanup of old logs
   - Configurable retention periods
   - Efficient database indexing

## Database Schema

### Table: `gibbonAuthTokenLog`

```sql
CREATE TABLE gibbonAuthTokenLog (
    gibbonAuthTokenLogID INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    gibbonPersonID INT UNSIGNED NOT NULL,
    username VARCHAR(255) NOT NULL,
    sessionID VARCHAR(255) NOT NULL,
    tokenStatus ENUM('success', 'failed', 'expired') NOT NULL DEFAULT 'success',
    ipAddress VARCHAR(45) NULL,
    userAgent TEXT NULL,
    gibbonRoleIDPrimary CHAR(3) NULL,
    aiRole VARCHAR(50) NULL,
    errorMessage TEXT NULL,
    expiresAt DATETIME NULL,
    timestampCreated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Indexes for performance
    INDEX (gibbonPersonID),
    INDEX (username),
    INDEX (sessionID),
    INDEX (tokenStatus),
    INDEX (timestampCreated),
    INDEX (ipAddress)
);
```

### Field Descriptions

| Field | Type | Description |
|-------|------|-------------|
| `gibbonAuthTokenLogID` | INT | Primary key, auto-increment |
| `gibbonPersonID` | INT | Foreign key to gibbonPerson table |
| `username` | VARCHAR(255) | Username at time of exchange |
| `sessionID` | VARCHAR(255) | PHP session identifier |
| `tokenStatus` | ENUM | Status: success, failed, or expired |
| `ipAddress` | VARCHAR(45) | Client IP address (IPv4 or IPv6) |
| `userAgent` | TEXT | Browser/client user agent string |
| `gibbonRoleIDPrimary` | CHAR(3) | Gibbon role ID at time of exchange |
| `aiRole` | VARCHAR(50) | Mapped AI service role |
| `errorMessage` | TEXT | Error details if exchange failed |
| `expiresAt` | DATETIME | Token expiration timestamp |
| `timestampCreated` | DATETIME | Log entry creation time |

## Installation

### 1. Database Migration

Run the SQL migration to create the audit log table:

```bash
# Apply migration
mysql -u gibbon_user -p gibbon_db < gibbon/sql/023_auth_token_log.sql

# To rollback (if needed)
mysql -u gibbon_user -p gibbon_db < gibbon/sql/023_auth_token_log_rollback.sql
```

### 2. Gateway Integration

The `AuthTokenLogGateway` class is automatically available at:
```
gibbon/modules/System/Domain/AuthTokenLogGateway.php
```

### 3. Endpoint Integration

The `auth_token.php` endpoint automatically logs all token exchanges. No additional configuration required.

## Usage

### Automatic Logging

The system automatically logs every token exchange attempt when users call the `/modules/System/auth_token.php` endpoint:

```javascript
// Client-side token exchange
const response = await fetch('/modules/System/auth_token.php', {
  method: 'POST',
  credentials: 'include',  // Include session cookie
});

const data = await response.json();
// This exchange is automatically logged with:
// - User information
// - IP address
// - User agent
// - Token status (success/failed)
// - Role mapping
```

### Querying Audit Logs

#### Get Recent Token Exchanges

```php
use Gibbon\Module\System\Domain\AuthTokenLogGateway;

$gateway = $container->get(AuthTokenLogGateway::class);

// Get last 20 token exchanges
$recentExchanges = $gateway->getRecentTokenExchanges(20);

foreach ($recentExchanges as $exchange) {
    echo "User: {$exchange['username']}, ";
    echo "Status: {$exchange['tokenStatus']}, ";
    echo "Time: {$exchange['timestampCreated']}\n";
}
```

#### Get User Token History

```php
// Get specific user's token exchange history
$userHistory = $gateway->getUserTokenHistory($gibbonPersonID, 50);

foreach ($userHistory as $entry) {
    echo "IP: {$entry['ipAddress']}, ";
    echo "Status: {$entry['tokenStatus']}, ";
    echo "Agent: {$entry['userAgent']}\n";
}
```

#### Get Statistics

```php
// Overall statistics
$stats = $gateway->getTokenStatistics();
echo "Total Exchanges: {$stats['totalExchanges']}\n";
echo "Successful: {$stats['successfulExchanges']}\n";
echo "Failed: {$stats['failedExchanges']}\n";
echo "Unique Users: {$stats['uniqueUsers']}\n";
echo "Unique IPs: {$stats['uniqueIPs']}\n";

// Statistics for specific date range
$stats = $gateway->getTokenStatistics('2026-02-01', '2026-02-16');

// Statistics by role
$roleStats = $gateway->getTokenStatisticsByRole('2026-02-01', '2026-02-16');
foreach ($roleStats as $roleStat) {
    echo "Role: {$roleStat['aiRole']}, ";
    echo "Count: {$roleStat['count']}, ";
    echo "Success Rate: " . ($roleStat['successCount'] / $roleStat['count'] * 100) . "%\n";
}
```

#### Detect Suspicious Activity

```php
// Find accounts with multiple failed attempts (5+ failures in 30 minutes)
$suspicious = $gateway->getSuspiciousActivity(5, 30);

foreach ($suspicious as $activity) {
    echo "ALERT - Username: {$activity['username']}, ";
    echo "IP: {$activity['ipAddress']}, ";
    echo "Failed Attempts: {$activity['failedCount']}, ";
    echo "Last Attempt: {$activity['lastAttempt']}\n";

    // Take action: notify admin, block IP, etc.
}

// Check if specific user has recent failed attempts
$hasFailed = $gateway->hasRecentFailedAttempts($gibbonPersonID, 15, 3);
if ($hasFailed) {
    echo "Warning: User has 3+ failed attempts in last 15 minutes\n";
}
```

#### Get Hourly Activity

```php
// Get hourly breakdown for charts/analytics
$hourlyData = $gateway->getHourlyActivity('2026-02-16', '2026-02-16');

foreach ($hourlyData as $hour) {
    echo "Hour: {$hour['hour']}, ";
    echo "Total: {$hour['totalExchanges']}, ";
    echo "Success: {$hour['successCount']}, ";
    echo "Failed: {$hour['failedCount']}\n";
}
```

### Manual Logging

You can also manually log token exchanges:

```php
$gateway = $container->get(AuthTokenLogGateway::class);

// Log successful exchange
$logID = $gateway->logTokenExchange([
    'gibbonPersonID' => 123,
    'username' => 'john.doe',
    'sessionID' => session_id(),
    'tokenStatus' => 'success',
    'ipAddress' => '192.168.1.1',
    'userAgent' => $_SERVER['HTTP_USER_AGENT'],
    'gibbonRoleIDPrimary' => '002',
    'aiRole' => 'teacher',
    'expiresAt' => date('Y-m-d H:i:s', time() + 3600),
]);

// Log failed exchange
$logID = $gateway->logTokenExchange([
    'gibbonPersonID' => 0,
    'username' => 'unknown',
    'sessionID' => 'none',
    'tokenStatus' => 'failed',
    'ipAddress' => '203.0.113.1',
    'userAgent' => 'curl/7.68.0',
    'errorMessage' => 'Invalid session',
]);
```

## Security Features

### IP Address Tracking

The system captures client IP addresses from multiple sources (handling proxies and load balancers):

1. `CF-Connecting-IP` (Cloudflare)
2. `X-Real-IP` (Nginx proxy)
3. `X-Forwarded-For` (Standard proxy)
4. `REMOTE_ADDR` (Direct connection)

This enables:
- Geographic access analysis
- Multi-location login detection
- IP-based blocking/alerting

### User Agent Tracking

Browser and client information is logged to:
- Detect automated attacks
- Identify unusual access patterns
- Support forensic analysis

### Failed Attempt Monitoring

The system tracks:
- Total failed attempts per user
- Failed attempts from specific IPs
- Time-windowed failure patterns
- Brute force attack indicators

## Data Retention

### Automatic Cleanup

```php
// Delete successful logs older than 90 days (default)
$deletedCount = $gateway->deleteOldTokenLogs(90);
echo "Deleted $deletedCount old log entries\n";

// More aggressive cleanup (30 days)
$deletedCount = $gateway->deleteOldTokenLogs(30);
```

### Recommended Schedule

Set up a cron job for automatic cleanup:

```bash
# Add to crontab (run daily at 2 AM)
0 2 * * * php /path/to/gibbon/cli/cleanupTokenLogs.php
```

## Performance Considerations

### Indexes

The table includes indexes on:
- `gibbonPersonID` - Fast user lookups
- `username` - Username searches
- `sessionID` - Session correlation
- `tokenStatus` - Status filtering
- `timestampCreated` - Date range queries
- `ipAddress` - IP-based searches

### Query Optimization

For large datasets:
- Use date range filters
- Limit result sets with pagination
- Archive old data to separate tables
- Partition by date if volume is very high

## Compliance and Auditing

### Audit Trail Requirements

This system helps meet compliance requirements for:
- **GDPR**: Logging access to personal data
- **HIPAA**: Healthcare data access audit trails
- **SOC 2**: Security monitoring and logging
- **PCI DSS**: Authentication logging

### Log Retention Policies

Recommended retention periods:
- **Successful authentications**: 90 days
- **Failed attempts**: 180 days (security analysis)
- **Suspicious activity**: Indefinite (until resolved)

### Export for Analysis

```php
use Gibbon\Domain\QueryCriteria;

// Export logs for compliance reporting
$criteria = QueryCriteria::create();
$logs = $gateway->queryTokenLogs($criteria);

// Convert to CSV
$csv = fopen('token_audit_export.csv', 'w');
fputcsv($csv, ['User', 'IP', 'Status', 'Time', 'Role']);

foreach ($logs as $log) {
    fputcsv($csv, [
        $log['username'],
        $log['ipAddress'],
        $log['tokenStatus'],
        $log['timestampCreated'],
        $log['aiRole'],
    ]);
}

fclose($csv);
```

## Troubleshooting

### Common Issues

#### 1. Logs Not Being Created

**Problem**: Token exchanges succeed but no logs appear.

**Solution**:
```php
// Check if gateway is properly initialized
$gateway = getAuthTokenLogGateway();
if ($gateway === null) {
    // Gateway not available - check database connection
    // and dependency injection container
}
```

#### 2. IP Address Shows as NULL

**Problem**: IP addresses not being captured.

**Solution**: Check proxy configuration. Add appropriate headers to your web server:
```nginx
# Nginx
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
```

#### 3. High Database Growth

**Problem**: Table growing too large.

**Solution**:
- Implement regular cleanup schedule
- Consider partitioning by date
- Archive old data to compressed tables

## API Reference

See `AuthTokenLogGateway.php` for complete method documentation:

- `queryTokenLogs(QueryCriteria)` - Query with filters
- `queryTokenLogsByStatus(QueryCriteria, status)` - Filter by status
- `queryTokenLogsByPerson(QueryCriteria, personID)` - User-specific logs
- `getTokenStatistics(dateFrom, dateTo)` - Overall stats
- `getTokenStatisticsByRole(dateFrom, dateTo)` - Role breakdown
- `getSuspiciousActivity(failedAttempts, withinMinutes)` - Security alerts
- `logTokenExchange(data)` - Create log entry
- `getRecentTokenExchanges(limit)` - Recent activity
- `getUserTokenHistory(personID, limit)` - User history
- `hasRecentFailedAttempts(personID, withinMinutes, threshold)` - Failure check
- `deleteOldTokenLogs(daysOld)` - Cleanup
- `getHourlyActivity(dateFrom, dateTo)` - Time-based analytics

## Testing

Run the test suite:

```bash
./vendor/bin/phpunit tests/unit/Modules/System/AuthTokenLogGatewayTest.php
```

Expected: All tests pass with >80% code coverage.

## Support

For issues or questions:
1. Check the troubleshooting section above
2. Review the gateway class documentation
3. Check system logs for error messages
4. Contact system administrator

## Version History

- **v1.0.00** (2026-02-16)
  - Initial implementation
  - Comprehensive audit logging
  - Security monitoring features
  - Analytics and reporting
  - Data retention management
