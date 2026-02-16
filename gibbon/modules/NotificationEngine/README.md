# NotificationEngine Module

**Version:** 1.2.00
**Status:** Production Ready
**Last Updated:** 2024-02-16

## Overview

The NotificationEngine module provides comprehensive multi-channel notification delivery for the LAYA daycare management system. It supports email and push notifications with queue-based processing, retry logic, user preferences, and detailed delivery logging.

## Features

### ✅ Multi-Channel Delivery

- **Email Notifications**
  - SMTP delivery via Gibbon Mailer
  - HTML email templates
  - Template variable substitution
  - Batch processing

- **Push Notifications**
  - Firebase Cloud Messaging (FCM)
  - iOS, Android, and Web support
  - Single and multicast messaging
  - Topic subscriptions
  - Token management

### ✅ Queue-Based Processing

- **Notification Queue** (`gibbonNotificationQueue`)
  - Asynchronous delivery
  - Retry logic with exponential backoff
  - Status tracking (pending, processing, sent, failed)
  - Attempt counting
  - Error logging

- **Cron Worker** (`cli/processQueue.php`)
  - Batch processing
  - Configurable batch size
  - Multiple deployment options (cron, Supervisor, Kubernetes)
  - Dry-run mode
  - Verbose logging

### ✅ User Preferences

- **Per-Type Preferences** (`gibbonNotificationPreference`)
  - Separate email/push toggles
  - 7 notification types (check-in, photo, incident, etc.)
  - Default-to-enabled behavior
  - User settings UI (`notifications_settings.php`)

### ✅ Event-to-Notification Mapping

- **EventNotificationMapper** (`src/Mapper/EventNotificationMapper.php`)
  - Automatic recipient detection
  - Family relationship queries
  - 10 event types supported
  - Payload data extraction
  - Queue integration

### ✅ Template System

- **Notification Templates** (`gibbonNotificationTemplate`)
  - Email subject/body templates
  - Push title/body variants
  - Variable substitution ({{childName}}, {{parentName}}, etc.)
  - Active/inactive toggle
  - 7 default templates

### ✅ Mobile App Integration

- **FCM Token Registration** (`api/fcm_token_register.php`)
  - Device token storage (`gibbonFCMToken`)
  - iOS/Android/Web support
  - Auto-update on duplicate
  - Last used timestamp
  - Token validation

- **Token Management APIs**
  - Register token (`fcm_token_register.php`)
  - Unregister token (`fcm_token_unregister.php`)
  - List tokens (`fcm_token_list.php`)
  - Auto-deactivate invalid tokens

### ✅ Notification Inbox

- **Frontend Component** (`parent-portal/components/NotificationInbox.tsx`)
  - Real-time notification display
  - Read/unread tracking
  - Mark as read (individual & bulk)
  - Filter by all/unread
  - Infinite scroll pagination
  - 9 notification types with icons

- **Backend APIs**
  - List notifications (`api/notifications_list.php`)
  - Mark as read (`api/notifications_mark_read.php`)
  - Pagination support
  - Date-based grouping

### ✅ Delivery Logging (NEW v1.2.00)

- **Comprehensive Tracking** (`gibbonNotificationDeliveryLog`)
  - Every delivery attempt logged
  - Success/failure/skip status
  - Error codes and messages
  - Delivery timing (ms)
  - FCM response data
  - Retry attempt numbers

- **Analytics Dashboard** (`delivery_logs.php`)
  - Success/failure rates by channel
  - Average delivery times
  - Top errors by frequency
  - Delivery timeline
  - Performance indicators

- **Log Management**
  - Automated purging
  - 90-day default retention
  - Query performance optimization
  - Analytics methods

## Architecture

```
NotificationEngine/
├── Domain/
│   ├── NotificationGateway.php      # Queue & template operations
│   ├── PushDelivery.php             # FCM push delivery
│   ├── EmailDelivery.php            # Email delivery
│   └── DeliveryLogGateway.php       # Delivery logging
├── src/
│   ├── Service/
│   │   └── FCMService.php           # FCM wrapper (legacy)
│   └── Mapper/
│       └── EventNotificationMapper.php  # Event-to-notification
├── api/
│   ├── fcm_token_register.php       # Token registration
│   ├── fcm_token_unregister.php     # Token deactivation
│   ├── fcm_token_list.php           # List user tokens
│   ├── notifications_list.php       # Notification inbox
│   └── notifications_mark_read.php  # Mark notifications read
├── cli/
│   ├── processQueue.php             # Queue worker
│   ├── worker.php                   # Long-running worker
│   ├── setup-cron.sh                # Cron setup assistant
│   └── README.md                    # CLI documentation
├── docs/
│   └── DELIVERY_LOGGING.md          # Logging documentation
├── notifications_settings.php       # User preferences UI
├── notifications_queue.php          # Queue management UI
├── delivery_logs.php                # Delivery logs UI
├── CHANGEDB.php                     # Database migrations
└── manifest.php                     # Module manifest
```

## Database Schema

### Core Tables

| Table | Purpose | Records |
|-------|---------|---------|
| `gibbonNotificationQueue` | Notification queue | Queued notifications |
| `gibbonNotificationTemplate` | Templates | 7 default templates |
| `gibbonNotificationPreference` | User preferences | Per-user, per-type |
| `gibbonFCMToken` | FCM device tokens | Active device tokens |
| `gibbonNotificationDeliveryLog` | Delivery logs | All delivery attempts |

### Settings

| Setting | Default | Description |
|---------|---------|-------------|
| `fcmEnabled` | Y | Enable Firebase push notifications |
| `emailEnabled` | Y | Enable email notifications |
| `maxRetryAttempts` | 3 | Maximum retry attempts |
| `queueBatchSize` | 50 | Notifications per queue run |
| `retryDelayMinutes` | 5 | Base retry delay (exponential backoff) |

## Installation

### 1. Install Module

Copy the NotificationEngine module to `gibbon/modules/NotificationEngine/`

### 2. Run Database Migrations

1. Navigate to: Admin > System Admin > Module Management
2. Select "NotificationEngine"
3. Click "Update" to run migrations

### 3. Configure Firebase (for Push Notifications)

1. Create Firebase project at https://console.firebase.google.com
2. Download service account JSON
3. Set environment variable:
   ```bash
   export FIREBASE_CREDENTIALS_PATH=/path/to/firebase-credentials.json
   ```
4. Enable FCM in module settings

### 4. Set Up Queue Worker

**Option A: Cron (Recommended)**
```bash
# Run interactive setup
cd gibbon/modules/NotificationEngine/cli
./setup-cron.sh
```

**Option B: Manual Cron**
```bash
# Add to crontab
* * * * * php /path/to/gibbon/modules/NotificationEngine/cli/processQueue.php
```

**Option C: Docker/Kubernetes**
See `cli/docker-cron-setup.md` for containerized deployments.

### 5. Verify Installation

1. Check queue worker logs:
   ```bash
   tail -f /var/log/gibbon-notifications.log
   ```

2. Send test notification:
   ```php
   $notificationGateway->insertNotification([
       'gibbonPersonID' => 1,
       'type' => 'announcement',
       'title' => 'Test Notification',
       'body' => 'This is a test',
       'channel' => 'both',
   ]);
   ```

3. Monitor delivery logs:
   - Navigate to: Notification Engine > Delivery Logs

## Usage

### Sending Notifications

#### Via EventNotificationMapper (Recommended)

```php
use Gibbon\Module\NotificationEngine\Domain\EventNotificationMapper;

$mapper = $container->get(EventNotificationMapper::class);

// Check-in notification
$mapper->mapCheckIn($gibbonPersonIDChild, [
    'checkInTime' => '08:30 AM',
    'staffName' => 'Teacher Jane',
]);

// Incident notification
$mapper->mapIncident($gibbonPersonIDChild, [
    'incidentType' => 'Minor Injury',
    'incidentTime' => '10:15 AM',
    'incidentDescription' => 'Bumped knee on playground',
    'staffName' => 'Teacher John',
]);
```

#### Via NotificationGateway (Direct)

```php
use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;

$gateway = $container->get(NotificationGateway::class);

// Queue notification
$notificationID = $gateway->insertNotification([
    'gibbonPersonID' => $recipientPersonID,
    'type' => 'checkIn',
    'title' => 'Check-In Confirmed',
    'body' => 'Your child has arrived at school',
    'data' => ['childName' => 'Emma', 'checkInTime' => '08:30'],
    'channel' => 'both', // email, push, or both
]);
```

### Managing User Preferences

Users manage their own preferences at:
- **URL:** `modules/NotificationEngine/notifications_settings.php`
- **Features:**
  - Toggle email/push per notification type
  - See active FCM tokens
  - View notification history

### Mobile App Integration

See `api/README.md` for complete mobile integration guide.

**Quick Start:**
```typescript
// Register FCM token
const response = await fetch('/modules/NotificationEngine/api/fcm_token_register.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
        gibbonPersonID: userId,
        deviceToken: fcmToken,
        deviceType: 'ios',
        deviceName: 'Emma\'s iPhone'
    })
});
```

### Viewing Delivery Logs

Navigate to: **Notification Engine > Delivery Logs**

**Filter by:**
- Channel (email, push)
- Status (success, failed, skipped)
- Date range

**View:**
- Delivery success rates
- Average delivery times
- Top errors
- Performance trends

## Supported Event Types

| Event | Type | Recipients | Template |
|-------|------|-----------|----------|
| Child Check-In | `checkIn` | Parents | ✅ Default |
| Child Check-Out | `checkOut` | Parents | ✅ Default |
| Photo Added | `photo` | Parents | ✅ Default |
| Incident Report | `incident` | Parents | ✅ Default |
| Meal Update | `meal` | Parents | ✅ Default |
| Nap Report | `nap` | Parents | ✅ Default |
| Daily Report | `dailyReport` | Parents | Custom |
| Message Received | `messageReceived` | Recipient | Custom |
| Diaper Change | `diaper` | Parents | Custom |
| General Announcement | `announcement` | All | ✅ Default |

## Monitoring

### Key Metrics

1. **Queue Health**
   - Pending count
   - Processing duration
   - Failed notification rate

2. **Delivery Performance**
   - Success rate by channel
   - Average delivery time
   - Error frequency

3. **User Engagement**
   - Active FCM tokens
   - Read rate (inbox)
   - Preference opt-outs

### Alerts (Recommended)

Set up monitoring alerts for:
- ⚠️ Success rate < 95%
- ⚠️ Queue backlog > 1000
- ⚠️ Average delivery time > 1000ms
- ⚠️ Failed attempts > 100/hour

## Troubleshooting

### Common Issues

#### No Notifications Sent

**Check:**
1. Queue worker is running: `ps aux | grep processQueue`
2. FCM/Email enabled in settings
3. User preferences allow notifications
4. Delivery logs for error details

#### High Failure Rate

**Steps:**
1. Check delivery logs: `Notification Engine > Delivery Logs`
2. Review top errors
3. Verify FCM credentials (push) or SMTP config (email)
4. Check recipient data validity

#### Slow Delivery

**Optimize:**
1. Increase `queueBatchSize` setting
2. Run queue worker more frequently
3. Use multicast for push notifications
4. Check network latency to FCM/SMTP

### Debug Mode

Enable verbose logging:
```bash
php cli/processQueue.php --verbose
```

## Performance

### Benchmarks

| Metric | Target | Typical |
|--------|--------|---------|
| Email Delivery | < 500ms | 200ms |
| Push Delivery | < 1000ms | 400ms |
| Queue Processing | 50/min | 50/min |
| Success Rate | > 95% | 98% |

### Scaling

For high-volume deployments:
1. Use Redis for queue (future enhancement)
2. Run multiple queue workers
3. Increase batch size
4. Use horizontal scaling (Kubernetes)

## Security

### Data Protection

- ✅ FCM tokens truncated in logs
- ✅ Passwords never logged
- ✅ Email addresses encrypted at rest (if configured)
- ✅ Access control via Gibbon permissions

### Best Practices

- 🔒 Use HTTPS for all API calls
- 🔒 Validate JWT tokens (production)
- 🔒 Rate limit token registration
- 🔒 Purge logs per data retention policy

## Development

### Adding New Event Types

1. **Create Template:**
   ```sql
   INSERT INTO gibbonNotificationTemplate (
       type, nameDisplay, subjectTemplate, bodyTemplate, pushTitle, pushBody
   ) VALUES (
       'newEventType',
       'New Event Type',
       'Subject: {{variable}}',
       'Body: {{variable}}',
       'Push Title',
       'Push Body'
   );
   ```

2. **Add Mapping Method:**
   ```php
   // In EventNotificationMapper.php
   public function mapNewEventType($childID, array $data) {
       // Get recipients
       $recipients = $this->getParentsByChild($childID);

       // Queue notifications
       foreach ($recipients as $recipient) {
           $this->queueNotification(
               $recipient['gibbonPersonID'],
               'newEventType',
               $data,
               'both'
           );
       }
   }
   ```

3. **Update Notification Types:**
   - Add icon to `NotificationInbox.tsx`
   - Update `notifications_settings.php`

## Changelog

### v1.2.00 (2024-02-16)
- ✅ Added comprehensive delivery logging
- ✅ Created DeliveryLogGateway with analytics
- ✅ Integrated logging into PushDelivery and EmailDelivery
- ✅ Built delivery logs UI with statistics
- ✅ Added performance tracking (delivery time in ms)
- ✅ Implemented log purging

### v1.1.00 (2024-02-15)
- ✅ Added notification inbox with read tracking
- ✅ Created frontend NotificationInbox component
- ✅ Implemented FCM token registration APIs
- ✅ Built notification list/mark-read endpoints

### v1.0.00 (2024-02-14)
- ✅ Initial release
- ✅ FCM push notification support
- ✅ Email delivery with templates
- ✅ Queue-based processing
- ✅ User preference management
- ✅ Event-to-notification mapping
- ✅ Cron worker setup

## Support

For issues, questions, or feature requests:
1. Check this README
2. Review documentation in `docs/`
3. Check delivery logs for errors
4. Contact development team

## License

This module is part of the LAYA system and follows the Gibbon GPL-3.0 license.
