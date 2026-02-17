# iOS Real Device LLM QA Runbook

## Purpose

This runbook provides end-to-end guidance for running LLM-based exploratory QA sessions on physical iOS devices. Real-device testing is essential because many issues (performance, hardware sensors, push notifications, background behavior) do not reproduce on simulators.

The diagnostics framework enables structured triage by capturing device state, logs, network errors, and crash reports that can be linked directly to QA findings.

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         QA Session Flow                              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌────────────┐      ┌────────────────┐      ┌──────────────────┐  │
│  │   LLM QA   │─────▶│  iOS App on    │─────▶│  Diagnostics     │  │
│  │   Agent    │      │  Real Device   │      │  Collection      │  │
│  └────────────┘      └────────────────┘      └────────────────────┘│
│         │                    │                         │            │
│         │                    │                         ▼            │
│         │                    │              ┌──────────────────┐   │
│         │                    │              │  POST /api/v1/   │   │
│         │                    │              │  qa/diagnostics  │   │
│         │                    │              └────────┬─────────┘   │
│         │                    │                       │             │
│         ▼                    ▼                       ▼             │
│  ┌────────────┐      ┌────────────────┐      ┌──────────────────┐  │
│  │  QA Report │◀─────│  Finding with  │◀─────│  Stored          │  │
│  │  Summary   │      │  diagnostics_id│      │  Diagnostics     │  │
│  └────────────┘      └────────────────┘      └──────────────────┘  │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### Key Components

| Component | Purpose | Location |
|-----------|---------|----------|
| **DiagnosticsService** | Collects logs, network errors, crashes with PII redaction | `teacher-app/src/services/diagnosticsService.ts` |
| **DiagnosticsApi** | Uploads bundles to backend | `teacher-app/src/api/diagnosticsApi.ts` |
| **useDiagnostics Hook** | React integration for screen tracking | `teacher-app/src/hooks/useDiagnostics.ts` |
| **QA Diagnostics Endpoint** | Ingests and stores bundles | `ai-service/app/routers/qa_diagnostics.py` |
| **Payload Contract** | Schema and validation rules | `teacher-app/docs/DIAGNOSTICS_PAYLOAD.md` |

## Prerequisites

### Device Requirements

- [ ] Physical iOS device (iPhone or iPad)
- [ ] iOS 15.0 or higher
- [ ] Provisioned with development profile (for debug builds)
- [ ] Sufficient storage (minimum 500 MB free)
- [ ] Stable network connection (Wi-Fi recommended)

### App Build Requirements

- [ ] Debug or QA build with diagnostics enabled
- [ ] App version matches backend API version
- [ ] DiagnosticsService integrated and initialized
- [ ] Network interceptors configured for error capture

### Backend Requirements

- [ ] AI Service running and accessible
- [ ] QA diagnostics endpoint registered (`POST /api/v1/qa/diagnostics`)
- [ ] Valid QA service authentication token
- [ ] Database configured for diagnostics storage

### QA Agent Requirements

- [ ] LLM QA agent configured with device provider credentials
- [ ] Appium or XCUITest driver available
- [ ] Test run ID generation capability
- [ ] Report generation configured

## Setup Steps

### 1. Prepare the iOS Device

```bash
# Connect device via USB
# Verify device is recognized
xcrun xctrace list devices

# Install the app (example with xcodebuild)
xcodebuild -project TeacherApp.xcodeproj \
  -scheme TeacherApp-QA \
  -destination 'platform=iOS,name=<DEVICE_NAME>' \
  install

# Or use ios-deploy
ios-deploy --bundle TeacherApp.app --debug
```

### 2. Configure Diagnostics Endpoint

Ensure the app's API configuration points to the correct backend:

```json
{
  "apiBaseUrl": "https://api.staging.laya.app",
  "qaEndpoint": "/api/v1/qa/diagnostics"
}
```

### 3. Initialize QA Session

Generate a unique test run ID before starting the session:

```bash
# Generate UUID for test run
export TEST_RUN_ID=$(uuidgen | tr '[:upper:]' '[:lower:]')
echo "Test Run ID: $TEST_RUN_ID"
```

### 4. Start Diagnostics Collection

In the app (via debug menu or programmatically):

```typescript
import DiagnosticsService from './services/diagnosticsService';

// Start collection with the test run ID
DiagnosticsService.startDiagnosticsSession(TEST_RUN_ID);
```

Or trigger via QA agent command:

```bash
# Using Appium/WebDriverIO
await driver.execute('mobile: startDiagnostics', { testRunId: TEST_RUN_ID });
```

## Execution Workflow

### Phase 1: Pre-Flight Checks

1. **Verify device connectivity**
   ```bash
   idevice_id -l  # List connected devices
   ideviceinfo -u <UDID> -k DeviceName
   ```

2. **Confirm app installation**
   ```bash
   ideviceinstaller -l | grep com.laya
   ```

3. **Check backend health**
   ```bash
   curl -s https://api.staging.laya.app/api/v1/qa/diagnostics/health | jq
   # Expected: {"status": "healthy", "service": "qa-diagnostics", ...}
   ```

### Phase 2: Run LLM QA Session

1. **Launch the QA agent** with the device target:
   ```bash
   # Example using OpenClaw mobile QA runner
   bash .auto-claude/scripts/run-cloud-mobile-qa.sh \
     --suite ios-exploratory \
     --platform ios \
     --provider local-device \
     --test-run-id $TEST_RUN_ID \
     --device-udid <DEVICE_UDID>
   ```

2. **Monitor session progress**:
   - Agent navigates through app screens
   - Diagnostics automatically collect logs and errors
   - Screen visits are tracked for coverage

3. **Capture findings**:
   - Agent records issues found (crashes, errors, UI bugs)
   - Each finding should include the `test_run_id` for linkage

### Phase 3: Export Diagnostics

Diagnostics are automatically uploaded on:
- **Session end**: When QA session completes normally
- **Error threshold**: After 5+ errors within 60 seconds
- **Crash detection**: Immediately before app termination
- **Periodic checkpoint**: Every 10 minutes during long sessions
- **Manual trigger**: Via debug menu or QA agent command

To manually trigger export:

```typescript
import { uploadDiagnostics } from './api/diagnosticsApi';

const result = await uploadDiagnostics();
// Returns: { diagnostics_id: "diag_abc123...", status: "accepted" }
```

### Phase 4: Generate QA Report

After the session completes:

1. Retrieve diagnostics summary:
   ```bash
   curl -H "Authorization: Bearer $QA_TOKEN" \
     "https://api.staging.laya.app/api/v1/qa/diagnostics/run/$TEST_RUN_ID" | jq
   ```

2. Link findings to diagnostics in the report:
   ```markdown
   ## Finding: App crashes on attendance submit

   - **Severity**: P1
   - **Test Run ID**: 550e8400-e29b-41d4-a716-446655440000
   - **Diagnostics ID**: diag_abc123def456
   - **Reproduction**: Navigate to Attendance > Select student > Submit

   ### Related Diagnostics
   - [View Diagnostics Bundle](https://admin.laya.app/qa/diagnostics/diag_abc123def456)
   - Device: iPhone 15 Pro, iOS 17.2.1
   - Environment: staging
   - Crash logs attached
   ```

## Linking Findings to Diagnostics

### Report Structure

Each QA finding should include:

| Field | Description | Example |
|-------|-------------|---------|
| `test_run_id` | UUID linking to QA session | `550e8400-e29b-41d4-a716-446655440000` |
| `diagnostics_id` | ID of related diagnostics bundle | `diag_abc123def456` |
| `timestamp` | When the issue was observed | `2026-02-17T10:30:00Z` |
| `screen_context` | Current screen when issue occurred | `AttendanceSubmitScreen` |

### Retrieval Commands

```bash
# Get diagnostics bundle by ID
curl -H "Authorization: Bearer $QA_TOKEN" \
  "https://api.staging.laya.app/api/v1/qa/diagnostics/diag_abc123def456" | jq

# Get all diagnostics for a test run
curl -H "Authorization: Bearer $QA_TOKEN" \
  "https://api.staging.laya.app/api/v1/qa/diagnostics/run/$TEST_RUN_ID" | jq

# Filter logs by level
curl -H "Authorization: Bearer $QA_TOKEN" \
  "https://api.staging.laya.app/api/v1/qa/diagnostics/diag_abc123def456?log_level=error" | jq
```

### Triage Workflow

```
┌───────────────────────────────────────────────────────────────┐
│                     Triage Decision Tree                       │
├───────────────────────────────────────────────────────────────┤
│                                                                │
│  Finding Reported                                              │
│       │                                                        │
│       ▼                                                        │
│  ┌─────────────────┐                                          │
│  │ Has diagnostics │──No──▶ Request manual repro              │
│  │    bundle?      │                                          │
│  └───────┬─────────┘                                          │
│          │ Yes                                                 │
│          ▼                                                     │
│  ┌─────────────────┐                                          │
│  │  Check crash    │──Yes──▶ Priority: P0/P1                  │
│  │  reports array  │         Assign to crash owner            │
│  └───────┬─────────┘                                          │
│          │ No                                                  │
│          ▼                                                     │
│  ┌─────────────────┐                                          │
│  │ Check network   │──Yes──▶ Check if backend issue           │
│  │ errors (5xx)    │         Escalate to backend team         │
│  └───────┬─────────┘                                          │
│          │ No                                                  │
│          ▼                                                     │
│  ┌─────────────────┐                                          │
│  │  Check error    │──Yes──▶ Review logs for root cause       │
│  │  level logs     │         Assign to feature owner          │
│  └───────┬─────────┘                                          │
│          │ No                                                  │
│          ▼                                                     │
│  Review custom_data.screens_visited                           │
│  for context and user journey                                 │
│                                                                │
└───────────────────────────────────────────────────────────────┘
```

## Diagnostics Bundle Contents

### Payload Structure

```json
{
  "test_run_id": "UUID",
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
    "total_memory_mb": 6144
  },
  "timestamp_collected": "2026-02-17T14:30:00.000Z",
  "logs": [...],
  "network_errors": [...],
  "crash_reports": [...],
  "custom_data": {
    "screens_visited": ["login", "dashboard", "attendance"]
  }
}
```

### Size Limits

| Component | Limit | Retention |
|-----------|-------|-----------|
| Total payload | 5 MB | Compressed JSON |
| Log entries | 500 | Most recent |
| Log message length | 1000 chars | Truncated |
| Network errors | 100 | Most recent |
| Crash reports | 5 | Most recent |
| Stack trace depth | 50 frames | Per crash |
| Custom data | 100 KB | Serialized |

### PII Redaction

All data is automatically redacted before upload:

| Data Type | Redaction |
|-----------|-----------|
| Emails | `user@example.com` → `[EMAIL_REDACTED]` |
| Tokens | `Bearer abc123...` → `Bearer [TOKEN_REDACTED]` |
| Phone numbers | `555-123-4567` → `[PHONE_REDACTED]` |
| IP addresses | `192.168.1.100` → `[IP_REDACTED]` |
| URL parameters | `?token=abc` → `?token=[REDACTED]` |
| Path IDs | `/users/12345` → `/users/[ID]` |

## Troubleshooting

### Common Issues

#### Diagnostics not uploading

1. **Check network connectivity**
   ```bash
   # From device or via proxy
   curl -v https://api.staging.laya.app/health
   ```

2. **Verify authentication**
   ```bash
   # Check if QA token is valid
   curl -H "Authorization: Bearer $QA_TOKEN" \
     https://api.staging.laya.app/api/v1/qa/diagnostics/health
   ```

3. **Check payload size**
   - Enable debug logging in DiagnosticsService
   - Verify payload is under 5 MB limit

#### Missing logs or network errors

1. **Confirm diagnostics collection is active**
   ```typescript
   const summary = DiagnosticsService.getDiagnosticsSummary();
   console.log('Active:', summary.isActive, 'Logs:', summary.logCount);
   ```

2. **Check if collection was started**
   - Ensure `startDiagnosticsSession()` was called with valid test run ID

#### Device not connecting

1. **Trust the computer on device**
   - Disconnect/reconnect USB
   - Accept "Trust This Computer" prompt

2. **Verify provisioning profile**
   ```bash
   security find-identity -v -p codesigning
   ```

3. **Check developer mode (iOS 16+)**
   - Settings > Privacy & Security > Developer Mode > Enable

### Error Codes

| HTTP Code | Error | Resolution |
|-----------|-------|------------|
| 400 | `validation_failed` | Check payload against schema |
| 401 | `unauthorized` | Refresh QA service token |
| 413 | `payload_too_large` | Reduce log/error counts |
| 500 | `processing_error` | Check backend logs |

## Recovery Procedures

### Session Interrupted

If the QA session is interrupted (device disconnect, crash, timeout):

1. **Check for partial diagnostics**
   ```bash
   curl -H "Authorization: Bearer $QA_TOKEN" \
     "https://api.staging.laya.app/api/v1/qa/diagnostics/run/$TEST_RUN_ID"
   ```

2. **Resume or restart session**
   - Same `TEST_RUN_ID` will aggregate diagnostics
   - New session should use new UUID

3. **Mark session as incomplete in report**

### Backend Unavailable

1. **Enable offline buffering** (if implemented)
   - Diagnostics stored locally on device
   - Retry upload when connectivity restored

2. **Manual export**
   - Use debug menu to export diagnostics JSON
   - Upload manually via curl or admin panel

### Device Storage Full

1. **Clear previous diagnostic data**
   ```typescript
   DiagnosticsService.clearDiagnosticsData();
   ```

2. **Reduce collection scope**
   - Lower log retention limit
   - Disable screenshot capture

## Artifacts

QA sessions produce the following artifacts:

```
.auto-claude/qa/runs/mobile/ios/<device-model>/<timestamp>/
├── metadata.json          # Session metadata
├── run.log                # Agent execution log
├── summary.md             # Human-readable report
├── diagnostics/
│   ├── bundle-001.json    # Diagnostics payload(s)
│   └── manifest.json      # Bundle list with IDs
├── screenshots/           # Captured screens
│   ├── screenshot-001.png
│   └── manifest.json
└── findings/
    ├── P0-crash-attendance.md
    └── P2-ui-alignment.md
```

## Merge Gate Policy

Before merging changes that affect iOS apps:

1. **Required checks**
   - [ ] CI build passes
   - [ ] Unit tests pass

2. **QA requirements**
   - [ ] Real-device QA session completed
   - [ ] No P0/P1 findings in report
   - [ ] Diagnostics uploaded and linked

3. **Review**
   - [ ] Diagnostics reviewed for new error patterns
   - [ ] Regression findings triaged

## Ownership

| Role | Responsibility | Contact |
|------|----------------|---------|
| QA Platform | Infrastructure, tooling | DevOps team |
| QA Execution | Running sessions, reports | QA team |
| Triage Lead | Finding prioritization | QA lead |
| App Owner | Bug fixes, regressions | iOS team |
| Backend Owner | Diagnostics API issues | Backend team |

## Appendix: Quick Reference Commands

```bash
# Start QA session
export TEST_RUN_ID=$(uuidgen | tr '[:upper:]' '[:lower:]')
bash .auto-claude/scripts/run-cloud-mobile-qa.sh --test-run-id $TEST_RUN_ID ...

# Check diagnostics health
curl https://api.staging.laya.app/api/v1/qa/diagnostics/health | jq

# Get diagnostics by run ID
curl -H "Authorization: Bearer $QA_TOKEN" \
  "https://api.staging.laya.app/api/v1/qa/diagnostics/run/$TEST_RUN_ID" | jq

# Get specific diagnostics bundle
curl -H "Authorization: Bearer $QA_TOKEN" \
  "https://api.staging.laya.app/api/v1/qa/diagnostics/$DIAG_ID" | jq

# List connected devices
idevice_id -l
xcrun xctrace list devices
```

## Changelog

### v1.0.0 (2026-02-17)
- Initial runbook for iOS real-device LLM QA
- Documented end-to-end execution workflow
- Added triage linkage and troubleshooting guides
