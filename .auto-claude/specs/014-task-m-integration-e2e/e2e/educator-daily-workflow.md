# Educator Daily Workflow E2E Documentation

## Overview

This document describes the end-to-end flow for an educator's typical daily workflow in the LAYA childcare management system. The workflow covers five major interactions: child check-in, meal logging, nap tracking, photo upload, and parent notification delivery.

## System Architecture

```
┌─────────────────┐     ┌─────────────────┐     ┌─────────────────┐
│   Teacher App   │     │     Gibbon      │     │   AI Service    │
│  (React Native) │────▶│   (PHP/MySQL)   │────▶│    (FastAPI)    │
└─────────────────┘     └────────┬────────┘     └─────────────────┘
                                 │
                                 │ AISync Webhooks
                                 ▼
                        ┌─────────────────┐
                        │  Parent Portal  │
                        │   (Next.js)     │
                        └─────────────────┘
```

### Services Involved

| Service | Tech Stack | Port | Role |
|---------|------------|------|------|
| teacher-app | React Native | N/A (mobile) | Educator UI for daily activities |
| gibbon | PHP 8.1+ / MySQL | 80/8080 | Backend, database, AISync module |
| ai-service | FastAPI / PostgreSQL | 8000 | Receives webhooks, AI recommendations |
| parent-portal | Next.js 14 | 3000 | Parent-facing dashboard |

---

## Step 1: Child Check-In

### User Action
Educator opens the Teacher App and checks in a child at arrival time.

### Flow Diagram

```
Educator taps "Check In"
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Teacher App                                                      │
│ POST /api/attendance/check-in                                   │
│ Body: { gibbonPersonID, timestamp, notes?, dropOffPerson? }     │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Gibbon Backend                                                   │
│ 1. Validate educator session/permissions                        │
│ 2. Insert into gibbonAttendance table                           │
│ 3. Trigger AISync webhook via AISyncService::syncCheckIn()     │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ AISync Module (sync.php)                                        │
│ 1. Create sync log entry in gibbonAISyncLog (status: pending)   │
│ 2. Generate JWT token for authentication                        │
│ 3. POST /api/v1/webhook (async via Guzzle postAsync)            │
│ 4. Update sync log on success/failure callbacks                 │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ AI Service (webhooks.py)                                         │
│ 1. Validate JWT token via get_current_user dependency           │
│ 2. Parse WebhookRequest (event_type: attendance_checked_in)     │
│ 3. Process via process_attendance_event()                       │
│ 4. Return WebhookResponse with status: processed                │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Teacher App → Gibbon

```http
POST /modules/Attendance/attendance_checkIn.php
Content-Type: application/json
Authorization: Bearer <session_token>

{
  "gibbonPersonID": 12345,
  "gibbonSchoolYearID": 2025,
  "date": "2026-02-15",
  "time": "08:30:00",
  "type": "Present",
  "notes": "Arrived with grandmother",
  "dropOffPerson": "Maria Garcia (Grandmother)"
}
```

**Response:**
```json
{
  "success": true,
  "gibbonAttendanceID": 98765,
  "message": "Check-in recorded successfully"
}
```

#### 2. Gibbon → AI Service (AISync Webhook)

```http
POST /api/v1/webhook
Content-Type: application/json
Authorization: Bearer <jwt_token>
X-Webhook-Event: attendance_checked_in

{
  "event_type": "attendance_checked_in",
  "entity_type": "attendance",
  "entity_id": "98765",
  "payload": {
    "child_id": 12345,
    "child_name": "Sophie Martin",
    "timestamp": "2026-02-15T08:30:00-05:00",
    "drop_off_person": "Maria Garcia (Grandmother)",
    "educator_id": 1001,
    "classroom_id": 5
  },
  "timestamp": "2026-02-15T08:30:15-05:00"
}
```

**Response:**
```json
{
  "status": "processed",
  "message": "Child 12345 checked in, attendance record ID 98765",
  "event_type": "attendance_checked_in",
  "entity_id": "98765",
  "received_at": "2026-02-15T13:30:15.123Z",
  "processing_time_ms": 12.45
}
```

### Database Changes

| Table | Action | Fields |
|-------|--------|--------|
| `gibbonAttendance` | INSERT | gibbonPersonID, date, type, time, gibbonSchoolYearID |
| `gibbonAISyncLog` | INSERT | eventType='attendance_checked_in', entityType='attendance', status='pending' → 'success' |

### Expected Outcome
- Child appears as "Present" in today's attendance list
- Parent receives notification in Parent Portal
- Sync log shows successful webhook delivery

---

## Step 2: Meal Logging

### User Action
Educator logs the child's lunch consumption during the meal period.

### Flow Diagram

```
Educator selects child, taps "Log Meal"
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Teacher App                                                      │
│ POST /api/care-tracking/meal                                    │
│ Body: { childID, mealType, portions, timestamp, notes }         │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Gibbon Backend (CareTracking Module)                            │
│ 1. Validate educator permissions                                │
│ 2. Insert into gibbonCareActivity table (type: meal)            │
│ 3. Trigger AISync webhook via AISyncService::syncMealEvent()    │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ AI Service                                                       │
│ 1. Validate JWT, parse meal_logged event                        │
│ 2. Process via process_meal_event()                             │
│ 3. Store for daily report aggregation                           │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Teacher App → Gibbon

```http
POST /modules/CareTracking/careTracking_addActivity.php
Content-Type: application/json
Authorization: Bearer <session_token>

{
  "gibbonPersonID": 12345,
  "activityType": "meal",
  "mealType": "lunch",
  "portions": {
    "main": "all",
    "vegetable": "half",
    "fruit": "all",
    "milk": "all"
  },
  "timestamp": "2026-02-15T12:15:00-05:00",
  "notes": "Really enjoyed the pasta today!",
  "gibbonPersonIDCreated": 1001
}
```

**Response:**
```json
{
  "success": true,
  "gibbonCareActivityID": 55001,
  "message": "Meal logged successfully"
}
```

#### 2. Gibbon → AI Service (AISync Webhook)

```http
POST /api/v1/webhook
Content-Type: application/json
Authorization: Bearer <jwt_token>
X-Webhook-Event: meal_logged

{
  "event_type": "meal_logged",
  "entity_type": "meal",
  "entity_id": "55001",
  "payload": {
    "child_id": 12345,
    "child_name": "Sophie Martin",
    "meal_type": "lunch",
    "portions": {
      "main": "all",
      "vegetable": "half",
      "fruit": "all",
      "milk": "all"
    },
    "timestamp": "2026-02-15T12:15:00-05:00",
    "educator_id": 1001,
    "notes": "Really enjoyed the pasta today!"
  },
  "timestamp": "2026-02-15T12:15:05-05:00"
}
```

**Response:**
```json
{
  "status": "processed",
  "message": "Meal (lunch) for child 12345 logged with ID 55001",
  "event_type": "meal_logged",
  "entity_id": "55001",
  "received_at": "2026-02-15T17:15:05.234Z",
  "processing_time_ms": 8.32
}
```

### Database Changes

| Table | Action | Fields |
|-------|--------|--------|
| `gibbonCareActivity` | INSERT | gibbonPersonID, activityType='meal', details (JSON), timestamp |
| `gibbonAISyncLog` | INSERT | eventType='meal_logged', entityType='meal', status='success' |

### Portion Codes

| Code | Meaning |
|------|---------|
| `all` | Child ate everything |
| `most` | Child ate 75%+ |
| `half` | Child ate approximately 50% |
| `some` | Child ate 25% or less |
| `none` | Child did not eat |
| `refused` | Child refused to try |

### Expected Outcome
- Meal appears in child's daily care log
- Parent can view meal details in Daily Reports section
- Nutritional tracking updated

---

## Step 3: Nap Tracking

### User Action
Educator logs when a child goes to sleep and wakes up during nap time.

### Flow Diagram

```
Educator taps "Start Nap" → Later taps "End Nap"
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Teacher App                                                      │
│ 1. POST /api/care-tracking/nap/start (initial)                  │
│ 2. POST /api/care-tracking/nap/end (when child wakes)           │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Gibbon Backend                                                   │
│ 1. Create/update gibbonCareActivity (type: nap)                 │
│ 2. Calculate duration on end                                    │
│ 3. Trigger AISync webhook via AISyncService::syncNapEvent()     │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ AI Service                                                       │
│ 1. Process nap_logged event                                     │
│ 2. Update sleep pattern analytics                               │
│ 3. Flag if nap is outside normal range (AI insight)             │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Start Nap: Teacher App → Gibbon

```http
POST /modules/CareTracking/careTracking_addActivity.php
Content-Type: application/json
Authorization: Bearer <session_token>

{
  "gibbonPersonID": 12345,
  "activityType": "nap",
  "napAction": "start",
  "startTime": "2026-02-15T13:00:00-05:00",
  "gibbonPersonIDCreated": 1001
}
```

#### 2. End Nap: Teacher App → Gibbon

```http
POST /modules/CareTracking/careTracking_updateActivity.php
Content-Type: application/json
Authorization: Bearer <session_token>

{
  "gibbonCareActivityID": 55010,
  "napAction": "end",
  "endTime": "2026-02-15T14:30:00-05:00",
  "quality": "good",
  "notes": "Slept soundly the whole time"
}
```

**Response:**
```json
{
  "success": true,
  "gibbonCareActivityID": 55010,
  "duration_minutes": 90,
  "message": "Nap logged successfully"
}
```

#### 3. Gibbon → AI Service (AISync Webhook)

```http
POST /api/v1/webhook
Content-Type: application/json
Authorization: Bearer <jwt_token>
X-Webhook-Event: nap_logged

{
  "event_type": "nap_logged",
  "entity_type": "nap",
  "entity_id": "55010",
  "payload": {
    "child_id": 12345,
    "child_name": "Sophie Martin",
    "start_time": "2026-02-15T13:00:00-05:00",
    "end_time": "2026-02-15T14:30:00-05:00",
    "duration_minutes": 90,
    "quality": "good",
    "educator_id": 1001,
    "notes": "Slept soundly the whole time"
  },
  "timestamp": "2026-02-15T14:30:05-05:00"
}
```

**Response:**
```json
{
  "status": "processed",
  "message": "Nap (90 minutes) for child 12345 logged with ID 55010",
  "event_type": "nap_logged",
  "entity_id": "55010",
  "received_at": "2026-02-15T19:30:05.456Z",
  "processing_time_ms": 10.18
}
```

### Database Changes

| Table | Action | Fields |
|-------|--------|--------|
| `gibbonCareActivity` | INSERT/UPDATE | activityType='nap', startTime, endTime, duration, quality |
| `gibbonAISyncLog` | INSERT | eventType='nap_logged', status='success' |

### Nap Quality Codes

| Code | Description |
|------|-------------|
| `excellent` | Deep, uninterrupted sleep for full duration |
| `good` | Slept well with minor stirring |
| `fair` | Woke briefly but returned to sleep |
| `poor` | Restless, difficulty staying asleep |
| `did_not_sleep` | Child did not sleep during rest time |

### Expected Outcome
- Nap duration visible in daily activity timeline
- Parent sees nap start/end times in Daily Report
- Sleep pattern analytics updated for wellness insights

---

## Step 4: Photo Upload

### User Action
Educator takes a photo of the child during an activity and uploads it.

### Flow Diagram

```
Educator takes photo → Selects children to tag → Adds caption → Uploads
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Teacher App                                                      │
│ 1. Capture/select image from device                             │
│ 2. POST /api/photos/upload (multipart/form-data)                │
│ 3. Tag children, add caption and activity context               │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Gibbon Backend (PhotoManagement Module)                         │
│ 1. Validate file (type, size, dimensions)                       │
│ 2. Generate thumbnails and store securely                       │
│ 3. Insert into gibbonPhoto table with child associations        │
│ 4. Trigger AISync webhook via AISyncService::syncPhotoUpload()  │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ AI Service                                                       │
│ 1. Process photo_uploaded event                                 │
│ 2. Queue for AI content moderation (safety check)               │
│ 3. Store metadata for parent gallery access                     │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Teacher App → Gibbon

```http
POST /modules/PhotoManagement/photo_upload.php
Content-Type: multipart/form-data
Authorization: Bearer <session_token>

------WebKitFormBoundary
Content-Disposition: form-data; name="photo"; filename="activity_20260215.jpg"
Content-Type: image/jpeg

[binary image data]
------WebKitFormBoundary
Content-Disposition: form-data; name="taggedChildren"

[12345, 12346, 12347]
------WebKitFormBoundary
Content-Disposition: form-data; name="caption"

Playing with blocks in the learning center!
------WebKitFormBoundary
Content-Disposition: form-data; name="activityType"

free_play
------WebKitFormBoundary
Content-Disposition: form-data; name="visibility"

parents_only
------WebKitFormBoundary--
```

**Response:**
```json
{
  "success": true,
  "gibbonPhotoID": 7890,
  "thumbnailUrl": "/uploads/photos/thumbs/7890_thumb.jpg",
  "fullUrl": "/uploads/photos/7890.jpg",
  "taggedCount": 3,
  "message": "Photo uploaded successfully"
}
```

#### 2. Gibbon → AI Service (AISync Webhook)

```http
POST /api/v1/webhook
Content-Type: application/json
Authorization: Bearer <jwt_token>
X-Webhook-Event: photo_uploaded

{
  "event_type": "photo_uploaded",
  "entity_type": "photo",
  "entity_id": "7890",
  "payload": {
    "child_id": 12345,
    "tagged_children": [12345, 12346, 12347],
    "caption": "Playing with blocks in the learning center!",
    "activity_type": "free_play",
    "visibility": "parents_only",
    "file_name": "7890.jpg",
    "thumbnail_url": "/uploads/photos/thumbs/7890_thumb.jpg",
    "educator_id": 1001,
    "classroom_id": 5,
    "timestamp": "2026-02-15T10:45:00-05:00"
  },
  "timestamp": "2026-02-15T10:45:10-05:00"
}
```

**Response:**
```json
{
  "status": "processed",
  "message": "Photo for child 12345 uploaded with ID 7890",
  "event_type": "photo_uploaded",
  "entity_id": "7890",
  "received_at": "2026-02-15T15:45:10.789Z",
  "processing_time_ms": 15.67
}
```

### Database Changes

| Table | Action | Fields |
|-------|--------|--------|
| `gibbonPhoto` | INSERT | filename, thumbnailPath, caption, visibility, uploadedBy |
| `gibbonPhotoChild` | INSERT (multiple) | gibbonPhotoID, gibbonPersonID (for each tagged child) |
| `gibbonAISyncLog` | INSERT | eventType='photo_uploaded', status='success' |

### Photo Visibility Settings

| Setting | Who Can View |
|---------|--------------|
| `parents_only` | Only parents of tagged children |
| `classroom` | All parents in the classroom |
| `center_wide` | All parents at the childcare center |
| `private` | Educators only (not shared with parents) |

### Expected Outcome
- Photo appears in child's gallery in Parent Portal
- Parents receive photo notification
- Photo available in Daily Report compilation

---

## Step 5: Parent Notification

### User Action
System automatically notifies parents of their child's daily activities.

### Notification Triggers

Notifications are sent to parents in the following scenarios:

| Event | Notification Type | Timing |
|-------|------------------|--------|
| Check-in | Push + In-app | Immediate |
| Meal logged | In-app only | Batched (end of meal period) |
| Nap completed | Push + In-app | When child wakes |
| Photo uploaded | Push + In-app | Immediate |
| Daily summary | Email + In-app | End of day (17:00) |

### Flow Diagram

```
Activity logged in Gibbon
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ NotificationEngine Module (Gibbon)                              │
│ 1. Determine notification recipients (linked parents)          │
│ 2. Check parent notification preferences                       │
│ 3. Create gibbonNotification records                            │
│ 4. Queue for delivery (push, email, or in-app)                 │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Push Delivery Service                                           │
│ 1. Send to Firebase Cloud Messaging (FCM) / APNs               │
│ 2. Log delivery status                                          │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│ Parent Portal (Next.js)                                         │
│ 1. Fetch notifications via GET /api/notifications               │
│ 2. Display in notification center                               │
│ 3. Update badge count                                           │
│ 4. Allow click-through to activity details                      │
└─────────────────────────────────────────────────────────────────┘
```

### API Calls

#### 1. Parent Portal → Gibbon (Fetch Notifications)

```http
GET /api/notifications?limit=20&unread=true
Authorization: Bearer <parent_session_token>
```

**Response:**
```json
{
  "success": true,
  "notifications": [
    {
      "gibbonNotificationID": 50001,
      "type": "check_in",
      "title": "Sophie has arrived!",
      "body": "Sophie was checked in at 8:30 AM by Maria Garcia (Grandmother)",
      "timestamp": "2026-02-15T08:30:15-05:00",
      "read": false,
      "childID": 12345,
      "childName": "Sophie Martin",
      "actionUrl": "/daily-reports/2026-02-15"
    },
    {
      "gibbonNotificationID": 50002,
      "type": "photo",
      "title": "New photo of Sophie!",
      "body": "Playing with blocks in the learning center!",
      "timestamp": "2026-02-15T10:45:10-05:00",
      "read": false,
      "childID": 12345,
      "childName": "Sophie Martin",
      "actionUrl": "/photos/7890",
      "thumbnailUrl": "/uploads/photos/thumbs/7890_thumb.jpg"
    },
    {
      "gibbonNotificationID": 50003,
      "type": "nap",
      "title": "Sophie woke up from nap",
      "body": "Nap time: 1h 30min (13:00 - 14:30). Quality: Good",
      "timestamp": "2026-02-15T14:30:05-05:00",
      "read": false,
      "childID": 12345,
      "childName": "Sophie Martin",
      "actionUrl": "/daily-reports/2026-02-15"
    }
  ],
  "unreadCount": 3,
  "totalCount": 15
}
```

#### 2. Parent Portal → Gibbon (Mark as Read)

```http
POST /api/notifications/mark-read
Content-Type: application/json
Authorization: Bearer <parent_session_token>

{
  "notificationIDs": [50001, 50002, 50003]
}
```

**Response:**
```json
{
  "success": true,
  "message": "3 notifications marked as read"
}
```

### Parent Portal UI Components

#### Notification Center (`/components/NotificationCenter.tsx`)

```typescript
// Expected notification data structure
interface Notification {
  gibbonNotificationID: number;
  type: 'check_in' | 'check_out' | 'meal' | 'nap' | 'photo' | 'daily_summary';
  title: string;
  body: string;
  timestamp: string;
  read: boolean;
  childID: number;
  childName: string;
  actionUrl: string;
  thumbnailUrl?: string;
}
```

#### Daily Report View (`/daily-reports/[date]/page.tsx`)

Displays a timeline of the day's activities:

```
┌──────────────────────────────────────────────────────────────┐
│ Daily Report for Sophie Martin - February 15, 2026          │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│ ○ 8:30 AM - Check-in                                        │
│   Arrived with Maria Garcia (Grandmother)                   │
│                                                              │
│ ○ 10:45 AM - Photo                                          │
│   [Thumbnail] Playing with blocks in the learning center!   │
│                                                              │
│ ○ 12:15 PM - Lunch                                          │
│   Main: All | Vegetable: Half | Fruit: All | Milk: All      │
│   "Really enjoyed the pasta today!"                         │
│                                                              │
│ ○ 1:00 PM - 2:30 PM - Nap (1h 30min)                        │
│   Quality: Good                                              │
│   "Slept soundly the whole time"                            │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

### Database Changes

| Table | Action | Fields |
|-------|--------|--------|
| `gibbonNotification` | INSERT | type, gibbonPersonID (parent), title, body, actionUrl |
| `gibbonNotificationRead` | INSERT | When parent views notification |

### Expected Outcome
- Parent receives push notification on mobile device
- Parent Portal shows unread badge
- Clicking notification navigates to relevant content
- Daily Report compiles all activities into timeline view

---

## Error Handling

### Common Error Scenarios

| Scenario | Error Code | Handling |
|----------|------------|----------|
| Network timeout | `CONNECTION_ERROR` | Retry with exponential backoff (max 3 attempts) |
| Invalid JWT | `401 Unauthorized` | Refresh token and retry |
| Rate limited | `429 Too Many Requests` | Wait and retry after rate limit window |
| Invalid payload | `422 Validation Error` | Log error, alert admin, do not retry |
| Service unavailable | `503 Service Unavailable` | Queue for later retry |

### AISync Retry Logic

```php
// From AISyncService::retryFailedSync()
// Exponential backoff: base_delay * 2^retry_count
// Default: 30s, 60s, 120s (max 3 retries)
```

### Monitoring

Check sync status via:

```sql
-- Failed syncs in last 24 hours
SELECT eventType, entityType, COUNT(*) as failed_count
FROM gibbonAISyncLog
WHERE status = 'failed'
  AND timestampCreated > NOW() - INTERVAL 1 DAY
GROUP BY eventType, entityType;

-- Sync statistics
SELECT
  status,
  COUNT(*) as count,
  AVG(TIMESTAMPDIFF(SECOND, timestampCreated, timestampProcessed)) as avg_processing_seconds
FROM gibbonAISyncLog
WHERE timestampCreated > NOW() - INTERVAL 1 DAY
GROUP BY status;
```

---

## Testing Checklist

### Manual Verification Steps

- [ ] **Check-in Flow**
  - [ ] Open Teacher App, select a child
  - [ ] Tap "Check In" and confirm success
  - [ ] Verify child appears as "Present" in attendance
  - [ ] Verify parent receives notification in Parent Portal

- [ ] **Meal Logging**
  - [ ] Select child, tap "Log Meal"
  - [ ] Enter portion amounts for each food category
  - [ ] Add optional notes
  - [ ] Verify meal appears in child's activity feed
  - [ ] Verify parent can view meal details

- [ ] **Nap Tracking**
  - [ ] Start nap timer for child
  - [ ] Wait or simulate time passing
  - [ ] End nap, record quality
  - [ ] Verify duration calculated correctly
  - [ ] Verify parent receives nap summary notification

- [ ] **Photo Upload**
  - [ ] Take photo or select from gallery
  - [ ] Tag one or more children
  - [ ] Add caption and visibility setting
  - [ ] Verify photo appears in child's gallery
  - [ ] Verify parents of tagged children receive notification

- [ ] **Parent Notification Delivery**
  - [ ] Check notification center shows all events
  - [ ] Verify push notifications received on mobile
  - [ ] Verify Daily Report shows full timeline
  - [ ] Verify mark-as-read functionality works

### AISync Webhook Verification

```bash
# Check webhook endpoint health
curl http://localhost:8000/api/v1/webhook/health

# Verify sync logs (requires DB access)
mysql -e "SELECT * FROM gibbonAISyncLog ORDER BY timestampCreated DESC LIMIT 10;"
```

---

## Appendix

### Environment Variables

| Variable | Service | Description |
|----------|---------|-------------|
| `AI_SERVICE_URL` | gibbon | URL to AI service (e.g., `http://ai-service:8000`) |
| `JWT_SECRET_KEY` | gibbon, ai-service | Shared secret for JWT tokens (min 32 chars) |
| `JWT_ALGORITHM` | gibbon, ai-service | JWT algorithm (default: `HS256`) |

### Related Documentation

- [AISync Module README](../../../gibbon/modules/AISync/README.md)
- [AI Service Webhook API](../../../ai-service/docs/webhooks.md)
- [Parent Portal Daily Reports](../../../parent-portal/docs/daily-reports.md)

### Change Log

| Date | Version | Author | Changes |
|------|---------|--------|---------|
| 2026-02-15 | 1.0 | auto-claude | Initial E2E documentation |
