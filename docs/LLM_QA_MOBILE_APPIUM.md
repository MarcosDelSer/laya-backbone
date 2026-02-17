# Mobile LLM QA with Appium - Setup and Runbook

## Purpose

Enable LLM-driven mobile exploratory testing for iOS and Android native applications using Appium. This system provides automated user journey validation, delay-aware iOS simulator handling, and evidence collection for both Teacher and Parent apps.

## Architecture Overview

```
+------------------+     +-------------------+     +---------------------+
|   LLM QA Agent   | --> | Delay-Aware       | --> | Appium Server       |
|   (Orchestrator) |     | Actions Wrapper   |     | (Local/Cloud)       |
+------------------+     +-------------------+     +---------------------+
                                                            |
                         +----------------------------------+
                         |                                  |
              +----------v----------+          +-----------v-----------+
              | Android Emulator    |          | iOS Simulator         |
              | (UiAutomator2)      |          | (XCUITest + WDA)      |
              +---------------------+          +-----------------------+
                         |                                  |
              +----------v----------+          +-----------v-----------+
              | Teacher/Parent App  |          | Teacher/Parent App    |
              | (Android APK)       |          | (iOS .app)            |
              +---------------------+          +-----------------------+
```

**Key Components:**
- **LLM QA Agent**: Orchestrates exploratory test journeys using scenario packs
- **Delay-Aware Actions**: Handles iOS simulator flakiness with retries and explicit waits
- **Appium Server**: Bridges test commands to native device automation
- **Evidence Collection**: Screenshots, video recordings, and device logs for debugging

## Prerequisites

### System Requirements

| Requirement | Android | iOS |
|-------------|---------|-----|
| OS | macOS, Linux, Windows | macOS only |
| JDK | JDK 11+ | JDK 11+ |
| Xcode | N/A | Xcode 14.3+ |
| Android SDK | Required | N/A |
| Node.js | 18+ | 18+ |

### Software Installation

#### 1. Install Appium 2.x

```bash
# Install Appium globally
npm install -g appium

# Verify installation
appium --version
```

#### 2. Install Appium Drivers

```bash
# Android driver (UiAutomator2)
appium driver install uiautomator2

# iOS driver (XCUITest)
appium driver install xcuitest

# Verify installed drivers
appium driver list --installed
```

#### 3. Android Environment Setup

```bash
# Set ANDROID_HOME (add to ~/.bashrc or ~/.zshrc)
export ANDROID_HOME=$HOME/Library/Android/sdk
export PATH=$PATH:$ANDROID_HOME/emulator
export PATH=$PATH:$ANDROID_HOME/platform-tools
export PATH=$PATH:$ANDROID_HOME/cmdline-tools/latest/bin

# Create recommended emulator
sdkmanager "system-images;android-33;google_apis;arm64-v8a"
avdmanager create avd -n Pixel_6_API_33 -k "system-images;android-33;google_apis;arm64-v8a" -d "pixel_6"
```

#### 4. iOS Environment Setup (macOS only)

```bash
# Install Xcode command line tools
xcode-select --install

# Accept Xcode license
sudo xcodebuild -license accept

# Install ios-deploy for device deployment
brew install ios-deploy

# Install WebDriverAgent dependencies
cd $(appium driver run xcuitest print-wda-path)
xcodebuild -project WebDriverAgent.xcodeproj -scheme WebDriverAgentRunner -destination 'platform=iOS Simulator,name=iPhone 15 Pro' build
```

### Verify Setup

Run the Appium Doctor to verify configuration:

```bash
# Install appium-doctor
npm install -g @appium/doctor

# Check Android setup
appium-doctor --android

# Check iOS setup
appium-doctor --ios
```

## Configuration Files

### Android Emulator Configuration

**Location:** `.auto-claude/qa/mobile/appium.android.yaml`

Key settings for Android testing:

```yaml
capabilities:
  platformName: Android
  automationName: UiAutomator2
  deviceName: Pixel_6_API_33
  platformVersion: "13.0"
  avdLaunchTimeout: 180000      # 3 min for emulator cold start
  avdReadyTimeout: 120000       # 2 min for emulator ready
  autoGrantPermissions: true    # Auto-grant runtime permissions
```

**App Profiles:**
- `teacher-app`: `com.laya.teacherapp`
- `parent-app`: `com.laya.parentapp`

### iOS Simulator Configuration

**Location:** `.auto-claude/qa/mobile/appium.ios-sim.yaml`

Key high-latency tuning settings:

```yaml
capabilities:
  platformName: iOS
  automationName: XCUITest
  deviceName: iPhone 15 Pro
  platformVersion: "17.2"

  # Critical WDA timeouts for simulator stability
  wdaLaunchTimeout: 180000        # 3 min - WDA startup
  wdaConnectionTimeout: 120000    # 2 min - Connection establishment
  wdaStartupRetries: 4            # Number of WDA restart attempts

  # Simulator performance tuning
  waitForIdleTimeout: 10          # Wait for app idle state
  animationCoolOffTimeout: 3      # Post-animation delay
  maxTypingFrequency: 30          # Slower typing for reliability
```

### Delay-Aware Actions

**Location:** `.auto-claude/qa/mobile/lib/delayAwareActions.ts`

The delay-aware wrapper handles iOS simulator flakiness with:

| Feature | Description | Default |
|---------|-------------|---------|
| Explicit waits | Wait for element visibility/clickability | 30s |
| Stability checks | Element must be stable for N iterations | 3 |
| Exponential backoff | Retry with increasing delays | 2.0x multiplier |
| iOS quirks | Extra delays for taps, scrolls, keyboard | 300-1500ms |

## Scenario Packs

### Teacher App Scenarios

**Location:** `.auto-claude/qa/mobile/scenarios/teacher-app-core.json`

| Journey ID | Description | Priority |
|------------|-------------|----------|
| `teacher-login-happy-path` | Standard login with valid credentials | Critical |
| `teacher-login-invalid-credentials` | Error handling for invalid credentials | High |
| `teacher-login-empty-fields` | Field validation errors | Medium |
| `teacher-view-classes` | Navigate and view class list | Critical |
| `teacher-select-class-view-students` | Select class, view roster | High |
| `teacher-assign-activity` | Create and assign activity | Critical |
| `teacher-view-student-progress` | View student analytics | High |
| `teacher-logout` | Logout flow | High |
| `teacher-network-error-recovery` | Network error handling | Medium |
| `teacher-full-journey-smoke` | E2E smoke test (composite) | Critical |

### Parent App Scenarios

**Location:** `.auto-claude/qa/mobile/scenarios/parent-app-core.json`

| Journey ID | Description | Priority |
|------------|-------------|----------|
| `parent-login-happy-path` | Standard login with valid credentials | Critical |
| `parent-login-invalid-credentials` | Error handling for invalid credentials | High |
| `parent-login-forgot-password` | Password reset flow | Medium |
| `parent-view-child-progress` | View child's learning progress | Critical |
| `parent-view-child-activities` | View assigned/completed activities | High |
| `parent-message-teacher` | Send message to teacher | High |
| `parent-view-notifications` | Access notification center | Medium |
| `parent-logout` | Logout flow | High |
| `parent-network-error-handling` | Network error handling | Medium |
| `parent-full-journey-smoke` | E2E smoke test (composite) | Critical |

## Execution Commands

### Start Appium Server

```bash
# Start with default settings
appium

# Start with specific port and logging
appium --port 4723 --log-level debug --log appium.log

# Start with relaxed security (for local development only)
appium --relaxed-security --allow-insecure chromedriver_autodownload
```

### Run Android Tests

```bash
# Set required environment variables
export ANDROID_APP_PATH="/path/to/teacher-app.apk"
export ANDROID_APP_PACKAGE="com.laya.teacherapp"
export ANDROID_APP_ACTIVITY=".MainActivity"

# Start emulator (if not running)
emulator -avd Pixel_6_API_33 -no-snapshot-load -no-audio &

# Wait for emulator to boot
adb wait-for-device

# Run tests (example with WebDriverIO)
npx wdio run wdio.android.conf.ts --suite teacher-smoke
```

### Run iOS Simulator Tests

```bash
# Set required environment variables
export IOS_APP_PATH="/path/to/TeacherApp.app"
export IOS_BUNDLE_ID="com.laya.teacherapp"

# Boot simulator (if not running)
xcrun simctl boot "iPhone 15 Pro"

# Run tests (example with WebDriverIO)
npx wdio run wdio.ios-sim.conf.ts --suite teacher-smoke
```

### Run Specific Journey

```bash
# Run single journey
npx wdio run wdio.android.conf.ts --spec "teacher-login-happy-path"

# Run critical journeys only
npx wdio run wdio.ios-sim.conf.ts --suite critical

# Run with delay-aware settings for flaky simulator
DELAY_AWARE_ENABLED=true npx wdio run wdio.ios-sim.conf.ts
```

### Run Full E2E Smoke Test

```bash
# Android - Teacher App
TEST_TEACHER_EMAIL="teacher@laya.test" \
TEST_TEACHER_PASSWORD="TestPassword123!" \
npx wdio run wdio.android.conf.ts --spec "teacher-full-journey-smoke"

# iOS Simulator - Parent App
TEST_PARENT_EMAIL="parent@laya.test" \
TEST_PARENT_PASSWORD="TestPassword123!" \
npx wdio run wdio.ios-sim.conf.ts --spec "parent-full-journey-smoke"
```

## Evidence Collection

### Automatic Evidence

The following evidence is collected automatically:

| Type | On Success | On Failure | Format |
|------|------------|------------|--------|
| Screenshots | Optional | Always | PNG |
| Video Recording | E2E tests | Always | MP4 (Android), MJPEG (iOS) |
| Device Logs | No | Always | Text |
| Element Hierarchy | No | Always | XML |

### Evidence Output Location

```
.auto-claude/qa/runs/mobile/
├── android/
│   └── <timestamp>/
│       ├── screenshots/
│       ├── videos/
│       ├── logs/
│       └── report.html
└── ios-simulator/
    └── <timestamp>/
        ├── screenshots/
        ├── videos/
        ├── logs/
        └── report.html
```

### Accessing Device Logs

```bash
# Android - Get logcat
adb logcat -d > android_logs.txt

# Filter React Native logs
adb logcat -d | grep -E "(ReactNative|ReactNativeJS)"

# iOS Simulator - Get system log
xcrun simctl spawn booted log show --predicate 'process == "TeacherApp"' --last 1h
```

## Known Failure Patterns and Troubleshooting

### iOS Simulator Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| WDA Launch Timeout | "WebDriverAgent failed to start" | Increase `wdaLaunchTimeout` to 300000, rebuild WDA |
| Stale Element | "Element is no longer attached to DOM" | Use delay-aware wrapper, increase `stableIterations` |
| Tap Not Registered | Button tap has no effect | Add `tapDelay` (300ms+), use explicit wait for clickable |
| Keyboard Not Appearing | Input field not receiving text | Increase `keyboardDelay` to 2000ms |
| Scroll Not Completing | Content not fully scrolled | Increase `scrollSettleTime` to 1000ms |

### Android Emulator Issues

| Issue | Symptoms | Solution |
|-------|----------|----------|
| Emulator Boot Timeout | "Emulator did not boot" | Use `-no-snapshot-load`, increase `avdLaunchTimeout` |
| App Not Installing | "INSTALL_FAILED" error | Clear app data, check `autoGrantPermissions` |
| UiAutomator Crash | "UiAutomator2 is not running" | Restart ADB, reinstall UiAutomator2 server |
| Slow Element Location | Tests timing out finding elements | Enable `ignoreUnimportantViews` |

### Common Solutions

#### Rebuild WebDriverAgent (iOS)

```bash
# Navigate to WDA directory
cd $(appium driver run xcuitest print-wda-path)

# Clean and rebuild
xcodebuild clean build -project WebDriverAgent.xcodeproj \
  -scheme WebDriverAgentRunner \
  -destination 'platform=iOS Simulator,name=iPhone 15 Pro' \
  IPHONEOS_DEPLOYMENT_TARGET=15.0
```

#### Reset Android Emulator State

```bash
# Cold boot emulator (ignore snapshots)
emulator -avd Pixel_6_API_33 -no-snapshot-load -wipe-data

# Clear app data
adb shell pm clear com.laya.teacherapp
```

#### Clear Appium Cache

```bash
# Clear Appium temp files
rm -rf ~/.appium

# Clear derived data (iOS)
rm -rf ~/Library/Developer/Xcode/DerivedData
```

### Retry Configuration

For persistent flakiness, adjust retry settings in config:

```yaml
# Android - appium.android.yaml
retry:
  maxAttempts: 3
  delayMs: 1000
  exponentialBackoff: true
  backoffMultiplier: 1.5

# iOS - appium.ios-sim.yaml (more aggressive)
retry:
  maxAttempts: 5
  delayMs: 2000
  exponentialBackoff: true
  backoffMultiplier: 2.0
  maxDelayMs: 30000
```

## LLM Exploratory Testing

### How It Works

The LLM QA agent uses scenario packs to guide exploratory testing:

1. **Journey Loading**: Agent loads journey steps from JSON scenario pack
2. **Action Execution**: Each step is executed through delay-aware wrappers
3. **LLM Exploration**: Steps marked `llmExplore` allow the LLM to freely navigate
4. **Evidence Collection**: Screenshots and logs captured on failures
5. **Report Generation**: Summary report with pass/fail status and evidence links

### LLM Hints Configuration

Each scenario pack includes `llmExploratoryHints`:

```json
{
  "focusAreas": [
    "Authentication edge cases",
    "Navigation consistency",
    "Error states and recovery"
  ],
  "commonIssues": [
    "iOS simulator tap delays",
    "Stale element references",
    "Keyboard overlapping inputs"
  ],
  "explorationBoundaries": {
    "maxDepth": 5,
    "avoidScreens": ["payment", "admin"],
    "focusScreens": ["login", "dashboard", "classes"]
  }
}
```

## Test Data Management

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `TEST_TEACHER_EMAIL` | Teacher account email | `teacher@laya.test` |
| `TEST_TEACHER_PASSWORD` | Teacher account password | `TestPassword123!` |
| `TEST_PARENT_EMAIL` | Parent account email | `parent@laya.test` |
| `TEST_PARENT_PASSWORD` | Parent account password | `TestPassword123!` |
| `ANDROID_APP_PATH` | Path to Android APK | - |
| `IOS_APP_PATH` | Path to iOS .app bundle | - |
| `APPIUM_SERVER_URL` | Appium server endpoint | `http://localhost:4723` |

### Test Account Requirements

- **Teacher Account**: Must have at least one class with students
- **Parent Account**: Must have at least one linked child
- **Activities**: Pre-created activities for testing assignment flows

## CI/CD Integration

### GitHub Actions Example

```yaml
name: Mobile QA
on: [pull_request]

jobs:
  android-qa:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-java@v4
        with:
          java-version: '17'
          distribution: 'temurin'
      - name: Setup Android SDK
        uses: android-actions/setup-android@v3
      - name: Start Emulator
        uses: reactivecircus/android-emulator-runner@v2
        with:
          api-level: 33
          target: google_apis
          arch: x86_64
          script: npm run test:android:smoke

  ios-qa:
    runs-on: macos-latest
    steps:
      - uses: actions/checkout@v4
      - name: Start Simulator
        run: xcrun simctl boot "iPhone 15 Pro"
      - name: Run iOS Tests
        run: npm run test:ios:smoke
```

## Merge Gate Criteria

For PRs affecting mobile apps:

| Gate | Requirement |
|------|-------------|
| Smoke Test | `*-full-journey-smoke` passes |
| Critical Journeys | All `priority: critical` pass |
| No P0/P1 Failures | No critical or high priority regressions |
| Evidence Review | Screenshots available for failed steps |

## Ownership

- **Platform Owner**: DevOps / Mobile Infrastructure
- **QA Owner**: Mobile QA Lead
- **Scenario Maintenance**: Product QA
- **Escalation**: Engineering Manager

## Related Documentation

- [OpenClaw Cloud QA Runbook](./OPENCLAW_CLOUD_QA_RUNBOOK.md) - Cloud execution setup
- [Android App Setup](./ANDROID_APP_SETUP.md) - Android build configuration
- Appium Official Docs: https://appium.io/docs/en/2.0/
