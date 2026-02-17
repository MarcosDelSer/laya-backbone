# iOS Diagnostics Payload Contract

## Overview

This document defines the diagnostics payload format for real-device iOS QA sessions in the Teacher App. The payload enables actionable triage of issues discovered during LLM-based exploratory testing on physical iOS devices.

## Purpose

Real-device issues often do not reproduce on simulators. This structured diagnostics bundle captures device state, logs, and network errors during QA sessions, enabling engineers to triage failures efficiently.

## Payload Schema

### Root Structure

```typescript
interface DiagnosticsPayload {
  // Required fields
  test_run_id: string;
  app_metadata: AppMetadata;
  device_metadata: DeviceMetadata;
  timestamp_collected: string; // ISO 8601 format

  // Optional fields
  logs: LogEntry[];
  network_errors: NetworkErrorDigest[];
  crash_reports: CrashReport[];
  screenshots: ScreenshotRef[];
  custom_data: Record<string, unknown>;
}
```

### Required Fields

#### test_run_id
- **Type:** `string`
- **Format:** UUID v4
- **Description:** Unique identifier linking this diagnostics bundle to a specific QA run
- **Example:** `"550e8400-e29b-41d4-a716-446655440000"`

#### app_metadata

```typescript
interface AppMetadata {
  app_name: string;           // "TeacherApp"
  app_version: string;        // Semantic version (e.g., "1.2.3")
  build_number: string;       // Build identifier (e.g., "456")
  bundle_id: string;          // iOS bundle identifier
  environment: string;        // "development" | "staging" | "production"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `app_name` | string | Yes | Application display name |
| `app_version` | string | Yes | Semantic version string |
| `build_number` | string | Yes | CI/CD build identifier |
| `bundle_id` | string | Yes | iOS bundle identifier (e.g., `com.laya.teacherapp`) |
| `environment` | string | Yes | Deployment environment |

#### device_metadata

```typescript
interface DeviceMetadata {
  device_model: string;       // e.g., "iPhone 15 Pro"
  device_identifier: string;  // Redacted device ID (see Redaction Rules)
  ios_version: string;        // e.g., "17.2.1"
  locale: string;             // e.g., "en_US"
  timezone: string;           // e.g., "America/New_York"
  is_simulator: boolean;      // Should be false for real-device runs
  available_storage_mb: number;
  total_memory_mb: number;
  battery_level: number;      // 0.0 - 1.0
  battery_state: string;      // "unplugged" | "charging" | "full"
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `device_model` | string | Yes | Human-readable device model |
| `device_identifier` | string | Yes | Hashed device ID (see Redaction) |
| `ios_version` | string | Yes | iOS version string |
| `locale` | string | Yes | Device locale |
| `timezone` | string | Yes | Device timezone |
| `is_simulator` | boolean | Yes | Always `false` for real-device runs |
| `available_storage_mb` | number | Yes | Free storage in MB |
| `total_memory_mb` | number | Yes | Total device memory |
| `battery_level` | number | No | Battery percentage (0.0-1.0) |
| `battery_state` | string | No | Battery charging state |

#### timestamp_collected
- **Type:** `string`
- **Format:** ISO 8601 with timezone
- **Description:** When the diagnostics bundle was collected
- **Example:** `"2026-02-16T14:30:00.000Z"`

### Optional Fields

#### logs

```typescript
interface LogEntry {
  timestamp: string;          // ISO 8601
  level: "debug" | "info" | "warning" | "error" | "fatal";
  tag: string;                // Log category/module
  message: string;            // Redacted log message
  metadata?: Record<string, unknown>;
}
```

| Field | Type | Description |
|-------|------|-------------|
| `timestamp` | string | When the log was recorded |
| `level` | string | Log severity level |
| `tag` | string | Module/component identifier |
| `message` | string | Redacted log message |
| `metadata` | object | Additional structured data (optional) |

**Log Limits:**
- Maximum entries: 500
- Maximum message length: 1000 characters
- Retain most recent logs when exceeding limits

#### network_errors

```typescript
interface NetworkErrorDigest {
  timestamp: string;          // ISO 8601
  request_url: string;        // Redacted URL (see Redaction Rules)
  request_method: string;     // HTTP method
  status_code: number | null; // HTTP status code (null if no response)
  error_type: string;         // Error classification
  error_message: string;      // Redacted error message
  request_duration_ms: number;
  retry_count: number;
}
```

| Field | Type | Description |
|-------|------|-------------|
| `timestamp` | string | When the error occurred |
| `request_url` | string | Redacted request URL |
| `request_method` | string | HTTP method (GET, POST, etc.) |
| `status_code` | number/null | HTTP response code |
| `error_type` | string | Classification (timeout, network_unreachable, etc.) |
| `error_message` | string | Redacted error description |
| `request_duration_ms` | number | Request duration in milliseconds |
| `retry_count` | number | Number of retry attempts |

**Error Types:**
- `timeout` - Request timed out
- `network_unreachable` - No network connectivity
- `ssl_error` - TLS/SSL handshake failure
- `dns_failure` - DNS resolution failed
- `server_error` - HTTP 5xx response
- `client_error` - HTTP 4xx response
- `unknown` - Unclassified error

#### crash_reports

```typescript
interface CrashReport {
  timestamp: string;
  exception_type: string;
  exception_message: string;  // Redacted
  stack_trace: string[];      // Redacted stack frames
  thread_info: ThreadInfo[];
}
```

#### screenshots

```typescript
interface ScreenshotRef {
  id: string;                 // Reference ID
  timestamp: string;          // When captured
  screen_name: string;        // Current screen identifier
  file_size_bytes: number;
}
```

**Note:** Screenshots are uploaded separately and referenced by ID. They must not contain sensitive PII visible on screen.

#### custom_data

A flexible key-value store for app-specific diagnostic data:

```typescript
{
  "feature_flags": {
    "new_dashboard": true,
    "beta_camera": false
  },
  "session_duration_seconds": 120,
  "screens_visited": ["login", "dashboard", "attendance"]
}
```

## Redaction Rules

All sensitive data MUST be redacted before upload. The following rules apply:

### Mandatory Redaction

| Data Type | Redaction Method | Example |
|-----------|------------------|---------|
| **User emails** | Hash or replace | `user@example.com` -> `[EMAIL_REDACTED]` |
| **Passwords** | Remove entirely | `password=secret` -> `password=[REDACTED]` |
| **API tokens** | Truncate + mask | `Bearer abc123xyz...` -> `Bearer [TOKEN:abc1...]` |
| **Phone numbers** | Partial mask | `+1-555-123-4567` -> `+1-555-***-****` |
| **Device UDID** | SHA-256 hash | Full UDID -> first 8 chars of hash |
| **IP addresses** | Remove last octets | `192.168.1.100` -> `192.168.x.x` |
| **Child names** | Remove/anonymize | `Tommy Smith` -> `[CHILD_REDACTED]` |
| **Parent names** | Remove/anonymize | `Jane Doe` -> `[PARENT_REDACTED]` |
| **Class/room names** | Remove if identifiable | `Room 101` -> `[ROOM_REDACTED]` |
| **GPS coordinates** | Remove entirely | Latitude/longitude -> `[LOCATION_REDACTED]` |
| **FCM tokens** | Truncate | Full token -> first 20 chars + `...` |
| **Session IDs** | Hash | Full ID -> SHA-256 hash prefix |

### URL Redaction

Request URLs must have query parameters and path segments redacted:

```
Original:  https://api.laya.app/v1/children/12345/reports?token=abc123
Redacted:  https://api.laya.app/v1/children/[ID]/reports?token=[REDACTED]
```

### Log Message Redaction

Apply pattern matching to redact sensitive data in log messages:

```javascript
// Patterns to detect and redact
const REDACTION_PATTERNS = [
  /email[:=]\s*[\w.-]+@[\w.-]+/gi,           // Emails
  /password[:=]\s*\S+/gi,                     // Passwords
  /token[:=]\s*[\w.-]{10,}/gi,               // Tokens
  /Bearer\s+[\w.-]+/gi,                       // Bearer tokens
  /\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/g,          // Phone numbers
  /\b\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}\b/g, // IP addresses
];
```

### Redaction Implementation

```typescript
// Example redaction utility
function redactSensitiveData(text: string): string {
  let redacted = text;

  // Email redaction
  redacted = redacted.replace(
    /[\w.-]+@[\w.-]+\.[a-z]{2,}/gi,
    '[EMAIL_REDACTED]'
  );

  // Token redaction (long alphanumeric strings)
  redacted = redacted.replace(
    /\b[A-Za-z0-9]{32,}\b/g,
    (match) => `[TOKEN:${match.substring(0, 4)}...]`
  );

  // Add more patterns as needed
  return redacted;
}
```

## Size Limits

| Component | Maximum Size | Notes |
|-----------|--------------|-------|
| **Total payload** | 5 MB | Compressed JSON |
| **Log entries** | 500 entries | Most recent retained |
| **Log message** | 1000 chars | Truncated if exceeded |
| **Network errors** | 100 entries | Most recent retained |
| **Crash reports** | 5 reports | Most recent retained |
| **Stack trace depth** | 50 frames | Per crash |
| **Screenshot refs** | 20 refs | Most recent retained |
| **Custom data** | 100 KB | Serialized JSON |

## Upload Endpoint

### Endpoint
```
POST /api/v1/qa/diagnostics
```

### Headers
```
Content-Type: application/json
Authorization: Bearer <qa_service_token>
X-Test-Run-ID: <test_run_id>
X-App-Version: <app_version>
```

### Response

**Success (201 Created):**
```json
{
  "diagnostics_id": "diag_abc123",
  "test_run_id": "550e8400-e29b-41d4-a716-446655440000",
  "received_at": "2026-02-16T14:30:05.000Z",
  "status": "accepted"
}
```

**Error (400 Bad Request):**
```json
{
  "error": "validation_failed",
  "message": "Missing required field: test_run_id",
  "details": ["test_run_id is required"]
}
```

**Error (413 Payload Too Large):**
```json
{
  "error": "payload_too_large",
  "message": "Payload exceeds 5MB limit",
  "max_size_bytes": 5242880
}
```

## Collection Triggers

The diagnostics bundle should be collected and uploaded when:

1. **QA session ends** - Normal completion of exploratory testing
2. **Error threshold reached** - 5+ errors within 60 seconds
3. **Crash detected** - Immediately before app termination (if possible)
4. **Manual trigger** - QA engineer requests via debug menu
5. **Periodic checkpoint** - Every 10 minutes during long sessions

## Example Payload

```json
{
  "test_run_id": "550e8400-e29b-41d4-a716-446655440000",
  "app_metadata": {
    "app_name": "TeacherApp",
    "app_version": "2.1.0",
    "build_number": "1234",
    "bundle_id": "com.laya.teacherapp",
    "environment": "staging"
  },
  "device_metadata": {
    "device_model": "iPhone 15 Pro",
    "device_identifier": "a1b2c3d4",
    "ios_version": "17.2.1",
    "locale": "en_US",
    "timezone": "America/New_York",
    "is_simulator": false,
    "available_storage_mb": 45678,
    "total_memory_mb": 6144,
    "battery_level": 0.85,
    "battery_state": "unplugged"
  },
  "timestamp_collected": "2026-02-16T14:30:00.000Z",
  "logs": [
    {
      "timestamp": "2026-02-16T14:29:45.123Z",
      "level": "error",
      "tag": "AttendanceAPI",
      "message": "Failed to fetch attendance: HTTP 500",
      "metadata": {
        "endpoint": "/api/v1/attendance",
        "retry_count": 3
      }
    }
  ],
  "network_errors": [
    {
      "timestamp": "2026-02-16T14:29:45.100Z",
      "request_url": "https://api.laya.app/v1/attendance?date=[REDACTED]",
      "request_method": "GET",
      "status_code": 500,
      "error_type": "server_error",
      "error_message": "Internal Server Error",
      "request_duration_ms": 1234,
      "retry_count": 3
    }
  ],
  "custom_data": {
    "feature_flags": {
      "new_attendance_ui": true
    },
    "screens_visited": ["login", "dashboard", "attendance"]
  }
}
```

## Security Considerations

1. **Transport Security** - All uploads must use HTTPS/TLS 1.3+
2. **Authentication** - QA service token required for upload
3. **Redaction Verification** - Pre-upload validation must confirm redaction
4. **Storage** - Diagnostics stored encrypted at rest
5. **Retention** - Diagnostics auto-deleted after 30 days
6. **Access Control** - Only QA and engineering teams can access

## Implementation Checklist

- [ ] Implement `DiagnosticsCollector` service
- [ ] Add redaction utilities for all PII types
- [ ] Integrate with app logging system
- [ ] Add network request interceptor for error capture
- [ ] Implement crash handler hook (pre-termination)
- [ ] Add size limit enforcement and truncation
- [ ] Create debug menu trigger for manual collection
- [ ] Add unit tests for redaction patterns
- [ ] Add integration tests for upload flow

## Changelog

### v1.0.0 (2026-02-16)
- Initial diagnostics payload contract definition
- Defined required and optional fields
- Established redaction policy for PII
- Set size limits and upload endpoint specification
