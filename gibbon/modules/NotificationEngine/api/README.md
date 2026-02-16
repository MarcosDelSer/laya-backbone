# FCM Token Management API

This directory contains API endpoints for managing Firebase Cloud Messaging (FCM) device tokens from mobile applications.

## Endpoints

### 1. Register FCM Token

**Endpoint:** `POST /modules/NotificationEngine/api/fcm_token_register.php`

**Description:** Registers or updates a Firebase Cloud Messaging (FCM) device token for a user.

**Request Body:**
```json
{
  "gibbonPersonID": 123,
  "deviceToken": "fcm_token_string_here",
  "deviceType": "ios",
  "deviceName": "John's iPhone"
}
```

**Request Fields:**
- `gibbonPersonID` (integer, required): The user's Gibbon person ID
- `deviceToken` (string, required): The FCM device token (10-500 characters)
- `deviceType` (string, required): Device platform - must be one of: `ios`, `android`, `web`
- `deviceName` (string, optional): User-friendly device name

**Success Response (200):**
```json
{
  "success": true,
  "message": "Device token registered successfully.",
  "data": {
    "tokenID": 1,
    "deviceToken": "fcm_token_string_here",
    "deviceType": "ios",
    "deviceName": "John's iPhone",
    "active": "Y",
    "lastUsedAt": "2026-02-16 12:34:56"
  }
}
```

**Error Responses:**
- `400`: Missing required field or invalid data
- `500`: Internal server error

---

### 2. Unregister FCM Token

**Endpoint:** `POST /modules/NotificationEngine/api/fcm_token_unregister.php`

**Description:** Deactivates a Firebase Cloud Messaging (FCM) device token.

**Request Body:**
```json
{
  "deviceToken": "fcm_token_string_here"
}
```

**Request Fields:**
- `deviceToken` (string, required): The FCM device token to deactivate

**Success Response (200):**
```json
{
  "success": true,
  "message": "Device token deactivated successfully."
}
```

**Error Responses:**
- `400`: Missing required field
- `500`: Internal server error

---

### 3. List User's FCM Tokens

**Endpoint:** `GET/POST /modules/NotificationEngine/api/fcm_token_list.php`

**Description:** Retrieves all active FCM device tokens for a user.

**Request (POST Body or GET Query):**
```json
{
  "gibbonPersonID": 123
}
```

Or as GET query:
```
GET /modules/NotificationEngine/api/fcm_token_list.php?gibbonPersonID=123
```

**Request Fields:**
- `gibbonPersonID` (integer, required): The user's Gibbon person ID

**Success Response (200):**
```json
{
  "success": true,
  "message": "Tokens retrieved successfully.",
  "data": {
    "tokens": [
      {
        "tokenID": 1,
        "deviceToken": "fcm_token_string_here",
        "deviceType": "ios",
        "deviceName": "John's iPhone",
        "active": "Y",
        "lastUsedAt": "2026-02-16 12:34:56",
        "createdAt": "2026-02-15 10:00:00"
      }
    ],
    "count": 1
  }
}
```

**Error Responses:**
- `400`: Missing required field
- `500`: Internal server error

---

## CORS Support

All endpoints support Cross-Origin Resource Sharing (CORS) to allow mobile app requests:
- Responds to preflight `OPTIONS` requests
- Sets appropriate CORS headers
- Allows credentials for session-based authentication (future enhancement)

## Security Notes

**Current Implementation (MVP):**
- Endpoints accept `gibbonPersonID` in the request body
- Authentication is handled at the mobile app level
- Mobile apps should ensure users can only register tokens for their own account

**Production Recommendations:**
- Implement JWT-based authentication
- Extract `gibbonPersonID` from validated JWT token instead of request body
- Add rate limiting to prevent abuse
- Implement request signing for additional security
- Use HTTPS only in production

## Database Schema

The API endpoints interact with the `gibbonFCMToken` table:

```sql
CREATE TABLE `gibbonFCMToken` (
    `gibbonFCMTokenID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `deviceToken` VARCHAR(255) NOT NULL,
    `deviceType` ENUM('ios','android','web') NOT NULL,
    `deviceName` VARCHAR(100) NULL,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `lastUsedAt` DATETIME NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `token` (`deviceToken`),
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `active_person` (`active`, `gibbonPersonID`)
);
```

## Integration Example

### iOS (Swift)
```swift
import FirebaseMessaging

// Get FCM token
Messaging.messaging().token { token, error in
    guard let token = token else { return }

    // Register with LAYA backend
    let url = URL(string: "https://your-laya-instance.com/modules/NotificationEngine/api/fcm_token_register.php")!
    var request = URLRequest(url: url)
    request.httpMethod = "POST"
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")

    let body: [String: Any] = [
        "gibbonPersonID": currentUserID,
        "deviceToken": token,
        "deviceType": "ios",
        "deviceName": UIDevice.current.name
    ]

    request.httpBody = try? JSONSerialization.data(withJSONObject: body)

    URLSession.shared.dataTask(with: request) { data, response, error in
        // Handle response
    }.resume()
}
```

### Android (Kotlin)
```kotlin
import com.google.firebase.messaging.FirebaseMessaging

// Get FCM token
FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
    if (!task.isSuccessful) return@addOnCompleteListener

    val token = task.result

    // Register with LAYA backend
    val url = "https://your-laya-instance.com/modules/NotificationEngine/api/fcm_token_register.php"
    val json = JSONObject().apply {
        put("gibbonPersonID", currentUserID)
        put("deviceToken", token)
        put("deviceType", "android")
        put("deviceName", Build.MODEL)
    }

    // Make POST request using OkHttp or Retrofit
    // Handle response
}
```

## Testing

### Using cURL

**Register Token:**
```bash
curl -X POST \
  http://localhost:8080/modules/NotificationEngine/api/fcm_token_register.php \
  -H 'Content-Type: application/json' \
  -d '{
    "gibbonPersonID": 1,
    "deviceToken": "test_token_12345",
    "deviceType": "ios",
    "deviceName": "Test iPhone"
  }'
```

**List Tokens:**
```bash
curl http://localhost:8080/modules/NotificationEngine/api/fcm_token_list.php?gibbonPersonID=1
```

**Unregister Token:**
```bash
curl -X POST \
  http://localhost:8080/modules/NotificationEngine/api/fcm_token_unregister.php \
  -H 'Content-Type: application/json' \
  -d '{
    "deviceToken": "test_token_12345"
  }'
```

## Maintenance

### Token Cleanup

Stale tokens (not used for 90+ days) can be cleaned up using the NotificationGateway:

```php
$notificationGateway = $container->get(NotificationGateway::class);
$deletedCount = $notificationGateway->cleanupStaleTokens(90);
```

This should be run periodically via a cron job.
