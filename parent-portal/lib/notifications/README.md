# Notification Inbox Component

Complete notification system for LAYA Parent Portal with FCM (Firebase Cloud Messaging) integration.

## Features

- ✅ Real-time notification inbox UI
- ✅ Mark notifications as read/unread
- ✅ Filter by all/unread notifications
- ✅ Auto-refresh capability
- ✅ Infinite scroll / pagination
- ✅ Notification grouping by date
- ✅ FCM token registration/management
- ✅ Unread count badge
- ✅ Responsive design with Tailwind CSS

## Components

### NotificationInbox

Main notification inbox component that displays a list of notifications.

```tsx
import { NotificationInbox } from '@/components/NotificationInbox';

function MyPage() {
  return (
    <NotificationInbox
      gibbonPersonID="123"
      pageSize={20}
      autoRefresh={true}
      refreshInterval={30000}
      onNotificationClick={(notification) => {
        console.log('Clicked:', notification);
      }}
    />
  );
}
```

#### Props

- `gibbonPersonID` (required): User ID to fetch notifications for
- `pageSize` (optional): Number of notifications per page (default: 20)
- `autoRefresh` (optional): Enable auto-refresh (default: true)
- `refreshInterval` (optional): Refresh interval in ms (default: 30000)
- `onNotificationClick` (optional): Callback when notification is clicked

## API Client

### FCMClient

Type-safe API client for notifications and FCM tokens.

```typescript
import {
  getNotifications,
  markNotificationsAsRead,
  getUnreadCount,
  registerFCMToken,
  unregisterFCMToken,
  listFCMTokens,
} from '@/lib/notifications/FCMClient';

// Fetch notifications
const response = await getNotifications({
  gibbonPersonID: '123',
  limit: 20,
  skip: 0,
  unreadOnly: false,
});

// Mark as read
await markNotificationsAsRead({
  gibbonPersonID: '123',
  notificationIds: ['1', '2', '3'],
});

// Get unread count
const count = await getUnreadCount('123');

// Register FCM token
await registerFCMToken({
  gibbonPersonID: '123',
  deviceToken: 'fcm-token-here',
  deviceType: 'ios',
  deviceName: 'iPhone 12',
});
```

## Backend API Endpoints

### 1. List Notifications

**Endpoint:** `GET /modules/NotificationEngine/api/notifications_list.php`

**Query Parameters:**
- `gibbonPersonID` (required): User ID
- `skip` (optional): Pagination offset (default: 0)
- `limit` (optional): Max results (default: 20, max: 100)
- `status` (optional): Filter by status (pending, sent, failed)
- `type` (optional): Filter by notification type
- `unread_only` (optional): Only unread (true/false)

**Response:**
```json
{
  "items": [
    {
      "id": "123",
      "type": "checkIn",
      "title": "Check-In Confirmed",
      "body": "Child has arrived at school",
      "data": {...},
      "status": "sent",
      "read": false,
      "createdAt": "2024-01-15T10:30:00Z"
    }
  ],
  "total": 45,
  "skip": 0,
  "limit": 20,
  "unreadCount": 5
}
```

### 2. Mark Notifications as Read

**Endpoint:** `POST /modules/NotificationEngine/api/notifications_mark_read.php`

**Request Body:**
```json
{
  "gibbonPersonID": 123,
  "notificationIds": ["1", "2", "3"]
}
```

**Response:**
```json
{
  "success": true,
  "markedCount": 3,
  "unreadCount": 2
}
```

### 3. Register FCM Token

**Endpoint:** `POST /modules/NotificationEngine/api/fcm_token_register.php`

**Request Body:**
```json
{
  "gibbonPersonID": 123,
  "deviceToken": "fcm-token-here",
  "deviceType": "ios",
  "deviceName": "iPhone 12"
}
```

### 4. Unregister FCM Token

**Endpoint:** `POST /modules/NotificationEngine/api/fcm_token_unregister.php`

**Request Body:**
```json
{
  "gibbonPersonID": 123,
  "deviceToken": "fcm-token-here"
}
```

### 5. List FCM Tokens

**Endpoint:** `GET /modules/NotificationEngine/api/fcm_token_list.php`

**Query Parameters:**
- `gibbonPersonID` (required): User ID

## Database Schema

### gibbonNotificationQueue

Stores notification history for inbox display.

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
  sentAt DATETIME NULL,
  readAt DATETIME NULL,
  timestampCreated TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  KEY status (status),
  KEY gibbonPersonID (gibbonPersonID),
  KEY readAt (readAt)
);
```

## Notification Types

Supported notification types with icons:

- `checkIn` - Child check-in (green login icon)
- `checkOut` - Child check-out (orange logout icon)
- `photo` - New photo added (purple photo icon)
- `incident` - Incident report (red alert icon)
- `meal` - Meal update (yellow restaurant icon)
- `nap` - Nap time (indigo moon icon)
- `message` - New message (blue mail icon)
- `dailyReport` - Daily report (teal document icon)
- `announcement` - General announcement (gray bell icon)

## Helper Functions

### groupNotificationsByDate

Groups notifications by date (Today, Yesterday, or full date).

```typescript
import { groupNotificationsByDate } from '@/lib/notifications/FCMClient';

const grouped = groupNotificationsByDate(notifications);
// Map<string, Notification[]>
```

### formatNotificationTime

Formats notification timestamp as relative time.

```typescript
import { formatNotificationTime } from '@/lib/notifications/FCMClient';

const timeStr = formatNotificationTime('2024-01-15T10:30:00Z');
// "2h ago" or "Just now" or "Jan 15"
```

## Integration with Mobile Apps

Mobile apps (teacher-app, parent-app) should:

1. **Register FCM token on login:**
```typescript
await registerFCMToken({
  gibbonPersonID: currentUser.id,
  deviceToken: await getFCMToken(), // From Firebase SDK
  deviceType: Platform.OS === 'ios' ? 'ios' : 'android',
  deviceName: await getDeviceName(),
});
```

2. **Unregister on logout:**
```typescript
await unregisterFCMToken({
  gibbonPersonID: currentUser.id,
  deviceToken: currentDeviceToken,
});
```

3. **Handle incoming notifications:**
```typescript
messaging().onMessage(async remoteMessage => {
  // Show local notification
  // Refresh notification inbox
});
```

## Testing

### Manual Testing

1. **Test notification list:**
```bash
curl "http://localhost:8080/gibbon/modules/NotificationEngine/api/notifications_list.php?gibbonPersonID=123&limit=10"
```

2. **Test mark as read:**
```bash
curl -X POST http://localhost:8080/gibbon/modules/NotificationEngine/api/notifications_mark_read.php \
  -H "Content-Type: application/json" \
  -d '{"gibbonPersonID": 123, "notificationIds": ["1", "2"]}'
```

3. **Test FCM token registration:**
```bash
curl -X POST http://localhost:8080/gibbon/modules/NotificationEngine/api/fcm_token_register.php \
  -H "Content-Type: application/json" \
  -d '{"gibbonPersonID": 123, "deviceToken": "test-token", "deviceType": "ios"}'
```

## Production Considerations

1. **Authentication:** Add JWT-based authentication to API endpoints
2. **Rate Limiting:** Implement rate limiting to prevent abuse
3. **Caching:** Cache notification counts with Redis
4. **Real-time Updates:** Add WebSocket support for instant updates
5. **Push Notifications:** Configure Firebase Cloud Messaging server key
6. **Database Indexing:** Ensure proper indexes on gibbonNotificationQueue table
7. **Archival:** Archive old read notifications after 90 days

## Troubleshooting

### Notifications not appearing

1. Check database for queued notifications:
```sql
SELECT * FROM gibbonNotificationQueue WHERE gibbonPersonID = 123 ORDER BY timestampCreated DESC LIMIT 10;
```

2. Verify queue processor is running:
```bash
php gibbon/modules/NotificationEngine/cli/processQueue.php
```

### FCM tokens not working

1. Check token registration:
```sql
SELECT * FROM gibbonFCMToken WHERE gibbonPersonID = 123 AND active = 'Y';
```

2. Verify Firebase credentials are configured in Gibbon settings

3. Test token validity with Firebase Admin SDK

## License

Part of the LAYA daycare management system. See main LICENSE file.
