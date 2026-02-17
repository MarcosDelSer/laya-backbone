# Desktop LLM QA Runbook

## Purpose

Run LLM-driven QA for desktop products: Windows app in VM/emulator and installed macOS admin app. This enables deterministic smoke flows, comprehensive feature coverage, and exploratory scenario runs with evidence capture.

## Architecture

```
┌──────────────────────────────────────────────────────────────────┐
│                    Desktop LLM QA Pipeline                       │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────┐         ┌─────────────────┐                │
│  │  Windows Lane   │         │   macOS Lane    │                │
│  │  (VM/Emulator)  │         │ (Installed App) │                │
│  └────────┬────────┘         └────────┬────────┘                │
│           │                           │                          │
│           ▼                           ▼                          │
│  ┌─────────────────┐         ┌─────────────────┐                │
│  │  WinAppDriver   │         │    XCUITest     │                │
│  │  (UI Automation)│         │  (AppleScript)  │                │
│  └────────┬────────┘         └────────┬────────┘                │
│           │                           │                          │
│           └───────────┬───────────────┘                          │
│                       ▼                                          │
│              ┌─────────────────┐                                │
│              │  Scenario Packs │                                │
│              │  (JSON-defined) │                                │
│              └────────┬────────┘                                │
│                       ▼                                          │
│              ┌─────────────────┐                                │
│              │ Evidence Capture│                                │
│              │ (Screenshots,   │                                │
│              │  Logs, Reports) │                                │
│              └─────────────────┘                                │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

1. **Windows Lane**: LAYA Desktop (Electron) runs in a Windows VM or emulator, automated via WinAppDriver.
2. **macOS Lane**: LAYA Admin (native) runs on the local machine, automated via XCUITest/Accessibility APIs.
3. **Scenario Packs**: JSON-defined test scenarios consumed by the LLM runner.
4. **Evidence Capture**: Screenshots, logs, and summary reports stored per run.

## Prerequisites

### Common Requirements

- Bash shell (bash 4.0+)
- `pnpm` package manager installed
- Access to the LAYA codebase
- Test credentials configured in environment or `.env` file

### Windows Platform Requirements

| Requirement | Description |
|-------------|-------------|
| Windows VM/Emulator | Parallels Desktop, VMware Fusion, VirtualBox, or cloud VM |
| WinAppDriver | Windows Application Driver v1.2+ installed in VM |
| Network Access | VM must be reachable from host machine |
| LAYA Desktop | Windows app installed in VM |
| Test Account | Valid test user credentials |

### macOS Platform Requirements

| Requirement | Description |
|-------------|-------------|
| macOS 12+ | Monterey or later |
| Xcode Command Line Tools | Required for XCUITest automation |
| LAYA Admin | Native app installed at `/Applications/LAYA Admin.app` or via bundle ID |
| Accessibility Permissions | Terminal/IDE granted accessibility access in System Preferences |
| Test Account | Valid admin test credentials |

## Environment Variables

### Windows Configuration

```bash
# Required
export WINDOWS_VM_HOST="192.168.1.100"      # VM hostname or IP address
export WINDOWS_VM_USER="testuser"            # VM username for automation
export WINDOWS_VM_PASSWORD="password"        # VM password (or use SSH key)

# Optional
export WINDOWS_AUTOMATION_PORT="4723"        # WinAppDriver port (default: 4723)
```

### macOS Configuration

```bash
# Required (one of these)
export MACOS_APP_BUNDLE_ID="com.laya.admin"  # Bundle identifier
export MACOS_APP_PATH="/Applications/LAYA Admin.app"  # Or explicit path

# Optional
# (Bundle ID is preferred; path is used for non-standard installations)
```

### Common Configuration

```bash
# Optional
export DESKTOP_QA_DRY_RUN="true"             # Skip actual execution (validation only)
export DESKTOP_QA_TIMEOUT="300"              # Timeout per scenario in seconds (default: 300)

# Test Credentials (used by scenario templates)
export TEST_USER_EMAIL="parent@test.com"
export TEST_USER_PASSWORD="testpassword"
export TEST_ADMIN_EMAIL="admin@test.com"
export TEST_ADMIN_PASSWORD="adminpassword"
export TEST_CHILD_ID="child-123"
export TEST_CHILD_NAME="Test Child"
export TEST_CENTER_ID="center-456"
export TEST_CENTER_NAME="Test Center"
```

## Setup Steps

### 1) Windows VM Setup

1. **Create Windows VM** with Windows 10/11.

2. **Install WinAppDriver**:
   ```powershell
   # In Windows VM
   # Download from: https://github.com/microsoft/WinAppDriver/releases
   # Install and enable Developer Mode in Windows Settings
   ```

3. **Start WinAppDriver**:
   ```powershell
   # Run as Administrator
   "C:\Program Files\Windows Application Driver\WinAppDriver.exe" 0.0.0.0 4723
   ```

4. **Install LAYA Desktop**:
   - Download and install the Windows app in the VM
   - Ensure first-run setup is completed

5. **Configure Network**:
   - Note the VM's IP address
   - Ensure port 4723 is accessible from host

### 2) macOS App Setup

1. **Install LAYA Admin**:
   ```bash
   # If distributed via .dmg:
   open ~/Downloads/LAYA-Admin.dmg
   # Drag to Applications folder
   ```

2. **Grant Accessibility Permissions**:
   - System Preferences > Security & Privacy > Privacy > Accessibility
   - Add Terminal.app (or your IDE) to the list

3. **Verify Installation**:
   ```bash
   # Check bundle ID
   mdls -name kMDItemCFBundleIdentifier "/Applications/LAYA Admin.app"

   # Or launch via bundle ID
   open -b com.laya.admin
   ```

### 3) Configure Test Credentials

Create a `.env.desktop-qa` file (gitignored):

```bash
# .env.desktop-qa
TEST_USER_EMAIL="parent@test.com"
TEST_USER_PASSWORD="testpassword123"
TEST_ADMIN_EMAIL="admin@test.com"
TEST_ADMIN_PASSWORD="adminpassword123"
TEST_CHILD_ID="child-abc123"
TEST_CHILD_NAME="Emma Johnson"
TEST_CENTER_ID="center-xyz789"
TEST_CENTER_NAME="Sunshine Childcare"
```

Source before running:
```bash
source .env.desktop-qa
```

## Execution Commands

### Basic Usage

```bash
# Show help
bash .auto-claude/scripts/run-desktop-llm-qa.sh --help
```

### Windows QA

```bash
# Smoke tests (critical paths)
export WINDOWS_VM_HOST="192.168.1.100"
export WINDOWS_VM_USER="testuser"
export WINDOWS_VM_PASSWORD="password"
bash .auto-claude/scripts/run-desktop-llm-qa.sh --platform windows --suite smoke

# Full feature coverage
bash .auto-claude/scripts/run-desktop-llm-qa.sh --platform windows --suite full

# Exploratory (edge cases, error handling)
bash .auto-claude/scripts/run-desktop-llm-qa.sh --platform windows --suite exploratory

# Custom scenario file
bash .auto-claude/scripts/run-desktop-llm-qa.sh --platform windows --scenarios ./custom-scenarios.json
```

### macOS QA

```bash
# Smoke tests
export MACOS_APP_BUNDLE_ID="com.laya.admin"
bash .auto-claude/scripts/run-desktop-llm-qa.sh --platform macos --suite smoke

# Full feature coverage
bash .auto-claude/scripts/run-desktop-llm-qa.sh --platform macos --suite full

# Exploratory
bash .auto-claude/scripts/run-desktop-llm-qa.sh --platform macos --suite exploratory
```

### Dry Run (Validation Only)

```bash
# Validate configuration without executing tests
export DESKTOP_QA_DRY_RUN="true"
bash .auto-claude/scripts/run-desktop-llm-qa.sh --platform macos --suite smoke
```

## Test Suites

### Smoke Suite

**Purpose**: Critical path validation for deployment gates.

| Characteristic | Value |
|----------------|-------|
| Timeout | 300 seconds |
| Scenarios | 4 per platform |
| Priority | Critical only |
| Coverage | Launch, Login, Core Navigation, Logout |

**When to Run**:
- Pre-deployment checks
- CI/CD pipeline gates
- Quick sanity validation

### Full Suite

**Purpose**: Comprehensive feature coverage.

| Characteristic | Value |
|----------------|-------|
| Timeout | 600 seconds |
| Scenarios | 12 per platform |
| Priority | Critical + High + Medium |
| Coverage | All major features and workflows |

**When to Run**:
- Release candidate validation
- Weekly regression testing
- Feature branch validation

### Exploratory Suite

**Purpose**: Edge cases, error states, and LLM-guided discovery.

| Characteristic | Value |
|----------------|-------|
| Timeout | 900 seconds |
| Scenarios | 10 per platform |
| Priority | Varies |
| Coverage | Error handling, security, accessibility, performance |

**Focus Areas**:
- Network disconnection handling
- Session timeout behavior
- Concurrent operations
- Platform-specific behaviors
- Accessibility compliance
- Data edge cases

**When to Run**:
- Pre-release exploratory testing
- After major refactors
- Security audits

## Scenario Structure

Scenarios are defined in JSON format:

**Windows**: `.auto-claude/qa/desktop/windows/scenarios.json`
**macOS**: `.auto-claude/qa/desktop/macos/scenarios.json`

### Scenario Format

```json
{
  "id": "smoke-001",
  "name": "Application Launch",
  "category": "launch",
  "priority": "critical",
  "preconditions": ["Application is installed"],
  "steps": [
    {
      "action": "launch_app",
      "description": "Launch application"
    },
    {
      "action": "wait_for_element",
      "selector": "[data-testid='app-container']",
      "timeout_ms": 10000,
      "description": "Wait for main container"
    },
    {
      "action": "capture_screenshot",
      "name": "app_launched"
    }
  ],
  "expected_outcomes": [
    "Application window opens without crash",
    "Login page or dashboard is displayed",
    "No error dialogs appear"
  ]
}
```

### Available Actions

| Action | Parameters | Description |
|--------|------------|-------------|
| `launch_app` | `bundle_id` | Launch the desktop application |
| `click` | `selector` | Click on an element |
| `input_text` | `selector`, `value` | Type text into a field |
| `wait_for_element` | `selector`, `timeout_ms` | Wait for element to appear |
| `wait_for_navigation` | `target`, `timeout_ms` | Wait for URL/route change |
| `capture_screenshot` | `name` | Capture evidence screenshot |
| `navigate` | `target` | Navigate to a route |
| `scroll` | `direction`, `distance` | Scroll the view |
| `press_key` | `key`, `repeat` | Press keyboard key |
| `resize_window` | `width`, `height` | Resize application window |
| `simulate_network` | `state` | Toggle network (offline/online) |
| `click_menu` | `menu`, `item` | Click macOS menu bar item |

### Template Variables

Use `{{VARIABLE}}` syntax for dynamic values:

```json
{
  "action": "input_text",
  "selector": "[data-testid='email-input']",
  "value": "{{TEST_USER_EMAIL}}"
}
```

Available variables are pulled from environment.

## Artifacts

Each run generates artifacts at:

```
.auto-claude/qa/runs/desktop/<platform>/<suite>/<timestamp>/
```

### Generated Files

| File | Description |
|------|-------------|
| `metadata.env` | Run configuration and environment |
| `run.log` | Full execution log |
| `summary.md` | Human-readable summary with pass/fail status |
| `screenshots/` | Evidence screenshots (if enabled) |
| `logs/` | Console and network logs (if enabled) |

### Example Summary

```markdown
# Desktop QA Summary

- Platform: `macos`
- Suite: `smoke`
- Exit code: `0`
- Timestamp: `20260216-143022`
- Scenarios: `.auto-claude/qa/desktop/macos/scenarios.json`
- Log: `.auto-claude/qa/runs/desktop/macos/smoke/20260216-143022/run.log`

## Result: PASSED
```

## Report Interpretation

### Exit Codes

| Code | Meaning |
|------|---------|
| `0` | All scenarios passed |
| `1` | One or more scenarios failed |
| `2` | Configuration or setup error |

### Severity Levels in Reports

| Severity | Action Required |
|----------|-----------------|
| **Critical** | Blocks deployment; must fix immediately |
| **High** | Fix before release; may block feature |
| **Medium** | Should fix in current sprint |
| **Low** | Nice to have; can defer |

### Reading the Summary

1. **Check exit code** - Non-zero indicates failures
2. **Review summary.md** - Quick pass/fail overview
3. **Examine run.log** - Detailed step-by-step execution
4. **Review screenshots** - Visual evidence of failures
5. **Check metadata.env** - Verify configuration was correct

## Troubleshooting

### Windows Issues

| Problem | Solution |
|---------|----------|
| Cannot connect to VM | Verify `WINDOWS_VM_HOST` is correct; check VM network settings |
| WinAppDriver not responding | Restart WinAppDriver as Administrator on port 4723 |
| App not found | Ensure LAYA Desktop is installed and the app name matches |
| Permission denied | Enable Developer Mode in Windows Settings |
| Timeout errors | Increase `DESKTOP_QA_TIMEOUT`; check VM performance |

### macOS Issues

| Problem | Solution |
|---------|----------|
| App not launching | Verify `MACOS_APP_BUNDLE_ID` or `MACOS_APP_PATH` |
| Accessibility denied | Grant Terminal accessibility permissions in System Preferences |
| Element not found | Check `data-testid` attributes in app; update selectors |
| Slow execution | Close other apps; check CPU/memory usage |
| Sandbox errors | Run from non-sandboxed terminal |

### Common Issues

| Problem | Solution |
|---------|----------|
| Missing environment variables | Source `.env.desktop-qa` before running |
| Scenarios file not found | Check path; use `--scenarios` to specify custom location |
| Dry run always | Unset `DESKTOP_QA_DRY_RUN` or set to "false" |
| Test credentials invalid | Update credentials; ensure test account exists |

### Debug Mode

For verbose output, run with bash debug:

```bash
bash -x .auto-claude/scripts/run-desktop-llm-qa.sh --platform macos --suite smoke 2>&1 | tee debug.log
```

## Integration with CI/CD

### GitHub Actions Example

```yaml
jobs:
  desktop-qa-macos:
    runs-on: macos-latest
    steps:
      - uses: actions/checkout@v4
      - name: Install LAYA Admin
        run: |
          # Download and install app
      - name: Run Desktop QA
        env:
          MACOS_APP_BUNDLE_ID: com.laya.admin
          TEST_ADMIN_EMAIL: ${{ secrets.TEST_ADMIN_EMAIL }}
          TEST_ADMIN_PASSWORD: ${{ secrets.TEST_ADMIN_PASSWORD }}
        run: |
          bash .auto-claude/scripts/run-desktop-llm-qa.sh --platform macos --suite smoke
      - name: Upload Artifacts
        if: always()
        uses: actions/upload-artifact@v4
        with:
          name: desktop-qa-results
          path: .auto-claude/qa/runs/desktop/
```

### Merge Gate Policy

Minimum requirements before auto-merge:

1. CI checks pass
2. Desktop smoke QA passes for affected platform(s)
3. No Critical or High severity findings
4. Evidence artifacts are archived

## Best Practices

### Writing Scenarios

1. **Keep scenarios focused** - One user journey per scenario
2. **Use meaningful IDs** - `smoke-001`, `full-002`, `exp-003`
3. **Add descriptive names** - "Login with Valid Credentials"
4. **Include preconditions** - Document required state
5. **Capture evidence** - Screenshot on success and failure
6. **Define expected outcomes** - Clear pass/fail criteria

### Running QA

1. **Start with smoke** - Quick validation before deeper testing
2. **Run full suite weekly** - Comprehensive regression coverage
3. **Use exploratory for releases** - Find edge cases before users do
4. **Review artifacts** - Don't just check exit codes
5. **Update scenarios** - Keep them in sync with app changes

### Maintenance

1. **Update selectors** when UI changes
2. **Add new scenarios** for new features
3. **Remove obsolete scenarios** for deprecated features
4. **Test scenario changes** in dry run first
5. **Document changes** in commit messages

## Ownership

| Role | Responsibility |
|------|----------------|
| **Platform Owner** | DevOps - Infrastructure, CI/CD integration |
| **QA Owner** | Product QA Lead - Scenario maintenance, report review |
| **Escalation** | Engineering Manager - Blocker resolution |

## Related Documentation

- [OpenClaw Cloud QA Runbook](./OPENCLAW_CLOUD_QA_RUNBOOK.md) - Web and mobile cloud QA
- Scenario Schemas:
  - `.auto-claude/qa/desktop/windows/scenarios.json`
  - `.auto-claude/qa/desktop/macos/scenarios.json`
- Runner Script: `.auto-claude/scripts/run-desktop-llm-qa.sh`
