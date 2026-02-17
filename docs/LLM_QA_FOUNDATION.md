# LLM QA Foundation

This document describes the end-to-end workflow for LLM-driven exploratory QA across web, mobile, and desktop platforms. It provides setup instructions, execution examples, and guidance on consuming artifacts.

---

## Table of Contents

- [Overview](#overview)
- [Architecture](#architecture)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Directory Structure](#directory-structure)
- [Scenario Format](#scenario-format)
  - [Schema Reference](#schema-reference)
  - [Example Scenario](#example-scenario)
  - [Persona Definition](#persona-definition)
  - [Journey Steps](#journey-steps)
  - [Assertions](#assertions)
- [Runner Invocation](#runner-invocation)
  - [Basic Usage](#basic-usage)
  - [Dry Run Mode](#dry-run-mode)
  - [Scenario Validation](#scenario-validation)
  - [Executing Scenarios](#executing-scenarios)
- [Artifact Collection](#artifact-collection)
  - [Output Structure](#output-structure)
  - [Artifact Naming](#artifact-naming)
  - [Collecting Artifacts](#collecting-artifacts)
- [Severity Classification](#severity-classification)
  - [Quick Reference](#quick-reference)
  - [Risk Tag Mapping](#risk-tag-mapping)
- [CI/CD Integration](#cicd-integration)
  - [Quality Gates](#quality-gates)
  - [Pipeline Examples](#pipeline-examples)
- [Platform-Specific Runners](#platform-specific-runners)
- [Troubleshooting](#troubleshooting)
- [Related Documents](#related-documents)

---

## Overview

The LLM QA Foundation provides infrastructure for running human-like exploratory QA tests across multiple platforms:

- **Web applications** (via Playwright or similar)
- **Mobile iOS** applications
- **Mobile Android** applications
- **Desktop Mac** applications
- **Desktop Windows** applications
- **Desktop Linux** applications

This foundation enables LLM agents (like AutoClaude) to:
1. Execute scenario-based journeys as specific user personas
2. Validate UI state and behavior through assertions
3. Collect evidence (screenshots, logs, traces)
4. Generate structured reports with severity-classified findings

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     LLM QA Foundation                           │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐      │
│  │   Scenario   │    │   Severity   │    │   Artifact   │      │
│  │    Schema    │    │    Rubric    │    │   Contract   │      │
│  └──────┬───────┘    └──────┬───────┘    └──────┬───────┘      │
│         │                   │                   │               │
│         └───────────────────┼───────────────────┘               │
│                             │                                   │
│                     ┌───────▼───────┐                           │
│                     │  run-llm-qa   │                           │
│                     │     .sh       │                           │
│                     └───────┬───────┘                           │
│                             │                                   │
│         ┌───────────────────┼───────────────────┐               │
│         │                   │                   │               │
│         ▼                   ▼                   ▼               │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────┐      │
│  │     Web      │    │    Mobile    │    │   Desktop    │      │
│  │    Runner    │    │    Runner    │    │    Runner    │      │
│  └──────────────┘    └──────────────┘    └──────────────┘      │
│                                                                 │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                   Artifact Output                         │  │
│  │  .auto-claude/qa/runs/{task}/{run}/{platform}/{time}/    │  │
│  │    ├── metadata.json                                      │  │
│  │    ├── scenarios/                                         │  │
│  │    ├── screenshots/                                       │  │
│  │    ├── logs/                                              │  │
│  │    ├── traces/                                            │  │
│  │    └── report.md                                          │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Getting Started

### Prerequisites

- **Bash 4.0+** (for array features)
- **Python 3.6+** (for JSON validation)
- **jq** (optional, for JSON manipulation)
- **uuidgen** (optional, for UUID generation)

Platform-specific requirements:
- **Web**: Node.js 18+, Playwright
- **Mobile iOS**: Xcode, Appium
- **Mobile Android**: Android SDK, Appium
- **Desktop**: Platform-specific automation tools

### Directory Structure

```
.auto-claude/
├── qa/
│   ├── llm/
│   │   ├── scenario-schema.json    # Scenario validation schema
│   │   └── severity-rubric.md      # Issue severity definitions
│   └── runs/                        # QA execution output
│       └── {task_id}/
│           └── {run_id}/
│               └── {platform}/
│                   └── {timestamp}/
└── scripts/
    ├── run-llm-qa.sh               # Main orchestrator
    └── collect-qa-artifacts.sh     # Artifact collector
```

---

## Scenario Format

### Schema Reference

All scenarios must conform to the JSON schema at:
```
.auto-claude/qa/llm/scenario-schema.json
```

### Example Scenario

```json
{
  "scenario_id": "user-login-flow",
  "name": "User Login Flow",
  "description": "Validates end-to-end login experience for returning users",
  "platform": "web",
  "tags": ["@smoke", "@auth", "@critical"],
  "risk_tags": ["security", "ux-critical"],
  "persona": {
    "role": "returning customer",
    "goal": "Sign in to access my account dashboard and recent orders",
    "context": "User knows their credentials and expects quick access",
    "technical_level": "intermediate"
  },
  "preconditions": [
    {
      "condition": "User account exists with valid credentials",
      "setup_hint": "Use test account: test@example.com / TestPass123"
    }
  ],
  "journey": {
    "entry_point": "/login",
    "steps": [
      {
        "step_id": "navigate-login",
        "action": "Navigate to the login page",
        "wait_for": "page load",
        "timeout_ms": 5000
      },
      {
        "step_id": "enter-credentials",
        "action": "Enter email 'test@example.com' and password in the login form",
        "target_hint": "input[type='email'], input[type='password']",
        "assertions": [
          {
            "type": "element-exists",
            "description": "Login form is visible and accessible",
            "target": "form[data-testid='login-form']",
            "severity": "P1"
          }
        ]
      },
      {
        "step_id": "submit-login",
        "action": "Click the login/submit button",
        "target_hint": "button[type='submit']",
        "wait_for": "navigation or error message",
        "timeout_ms": 10000,
        "on_failure": "screenshot-and-continue"
      },
      {
        "step_id": "verify-dashboard",
        "action": "Verify successful login by checking for dashboard elements",
        "assertions": [
          {
            "type": "url-matches",
            "description": "URL should indicate successful login (dashboard or home)",
            "expected": "/dashboard|/home|/account",
            "severity": "P0"
          },
          {
            "type": "element-exists",
            "description": "User avatar or profile indicator is visible",
            "target": "[data-testid='user-avatar'], .user-profile",
            "severity": "P1"
          }
        ]
      }
    ],
    "max_duration_ms": 60000
  },
  "assertions": [
    {
      "type": "text-content",
      "description": "Welcome message or user name is displayed",
      "severity": "P2"
    }
  ],
  "artifacts": {
    "screenshots": "per-step",
    "video": false,
    "network_logs": true,
    "console_logs": true,
    "accessibility_audit": false
  },
  "retry_policy": {
    "max_retries": 1,
    "retry_delay_ms": 2000
  },
  "metadata": {
    "owner": "auth-team",
    "version": "1.0.0",
    "related_specs": ["067", "068"]
  }
}
```

### Persona Definition

The `persona` object defines who is performing the journey:

| Field | Required | Description |
|-------|----------|-------------|
| `role` | Yes | User type (e.g., "new user", "admin", "power user") |
| `goal` | Yes | What they're trying to accomplish |
| `context` | No | Situational context (e.g., "on mobile during commute") |
| `technical_level` | No | `novice`, `intermediate`, `advanced`, `expert` |
| `accessibility_needs` | No | Array of accessibility requirements |

### Journey Steps

Each step in the `journey.steps` array defines an action:

| Field | Required | Description |
|-------|----------|-------------|
| `step_id` | Yes | Unique identifier (kebab-case) |
| `action` | Yes | Natural language description of what to do |
| `target_hint` | No | Optional selector or UI hint |
| `wait_for` | No | Condition to wait for after action |
| `timeout_ms` | No | Max wait time (default: 10000ms) |
| `on_failure` | No | `fail`, `skip`, `retry`, `screenshot-and-continue` |
| `assertions` | No | Checks to perform after this step |

### Assertions

Assertions verify expected behavior:

| Type | Use Case |
|------|----------|
| `visual` | Screenshot comparison |
| `text-content` | Text appears on page |
| `element-exists` | Element is present |
| `element-not-exists` | Element is absent |
| `element-state` | Element is enabled/disabled/visible |
| `url-matches` | URL matches pattern |
| `api-response` | API returns expected data |
| `performance` | Load time within threshold |
| `accessibility` | A11y check passes |
| `custom` | Custom validation logic |

---

## Runner Invocation

### Basic Usage

```bash
# Show help
.auto-claude/scripts/run-llm-qa.sh --help

# Basic invocation
.auto-claude/scripts/run-llm-qa.sh \
  --platform web \
  --scenario ./scenarios/login-flow.json \
  --task-id 067
```

### Dry Run Mode

Create the output structure without executing scenarios:

```bash
# Dry run - creates folders and report template
.auto-claude/scripts/run-llm-qa.sh \
  --platform web \
  --task-id 067 \
  --dry-run
```

Output:
```
[run-llm-qa] Creating output structure...
[run-llm-qa] Created output structure: .auto-claude/qa/runs/067/abc123/web/20260216-143000
[run-llm-qa] Generating metadata...
[run-llm-qa] Generating report template...
[run-llm-qa] Dry run completed. Output structure created at:
[run-llm-qa]   .auto-claude/qa/runs/067/abc123/web/20260216-143000
```

### Scenario Validation

Validate scenarios against the schema without execution:

```bash
# Validate a single scenario
.auto-claude/scripts/run-llm-qa.sh \
  --validate-only \
  --scenario ./scenarios/login-flow.json

# Validate all scenarios in a directory
.auto-claude/scripts/run-llm-qa.sh \
  --validate-only \
  --scenario-dir ./scenarios/
```

### Executing Scenarios

```bash
# Execute a single scenario
.auto-claude/scripts/run-llm-qa.sh \
  --platform web \
  --scenario ./scenarios/login-flow.json \
  --task-id 067 \
  --output-dir .auto-claude/qa/runs

# Execute all scenarios in a directory
.auto-claude/scripts/run-llm-qa.sh \
  --platform mobile-ios \
  --scenario-dir ./scenarios/mobile/ \
  --task-id 068

# Execute with custom run ID
.auto-claude/scripts/run-llm-qa.sh \
  --platform web \
  --scenario-dir ./scenarios/ \
  --task-id 067 \
  --run-id pr-1234-smoke
```

### Environment Variables

| Variable | Description | Default |
|----------|-------------|---------|
| `LLM_QA_PLATFORM` | Default platform | `web` |
| `LLM_QA_OUTPUT_DIR` | Base output directory | `.auto-claude/qa/runs` |

---

## Artifact Collection

### Output Structure

Every QA run produces a standardized directory structure:

```
.auto-claude/qa/runs/{task_id}/{run_id}/{platform}/{timestamp}/
├── metadata.json       # Run configuration and environment
├── scenarios/          # Copies of executed scenario files
│   └── login-flow.json
├── screenshots/        # Captured screenshots
│   ├── 20260216-143001-login-page.png
│   └── 20260216-143005-dashboard.png
├── logs/               # Console and network logs
│   ├── console-20260216-143000.log
│   └── network-20260216-143000.log
├── traces/             # Execution traces
│   └── 20260216-trace-login-flow.zip
└── report.md           # Markdown summary report
```

### Artifact Naming

Deterministic naming convention ensures consistent organization:

| Artifact Type | Pattern | Example |
|---------------|---------|---------|
| Screenshots | `{timestamp}-{description}.{ext}` | `20260216-143001-login-page.png` |
| Logs | `{type}-{timestamp}.log` | `console-20260216-143000.log` |
| Traces | `{timestamp}-trace-{name}.{ext}` | `20260216-trace-login-flow.zip` |

### Collecting Artifacts

Use `collect-qa-artifacts.sh` to gather artifacts from test runners:

```bash
# Show help
.auto-claude/scripts/collect-qa-artifacts.sh --help

# Collect from a source directory
.auto-claude/scripts/collect-qa-artifacts.sh \
  --source-dir ./playwright-report \
  --run-dir .auto-claude/qa/runs/067/abc123/web/20260216-143000

# Collect specific artifact types
.auto-claude/scripts/collect-qa-artifacts.sh \
  --screenshots ./test-results/screenshots \
  --logs ./test-results/logs \
  --run-dir .auto-claude/qa/runs/067/abc123/web/20260216-143000

# Preview collection (dry run)
.auto-claude/scripts/collect-qa-artifacts.sh \
  --source-dir ./playwright-report \
  --run-dir .auto-claude/qa/runs/067/abc123/web/20260216-143000 \
  --dry-run

# List artifacts in a run
.auto-claude/scripts/collect-qa-artifacts.sh \
  --list \
  --run-dir .auto-claude/qa/runs/067/abc123/web/20260216-143000
```

---

## Severity Classification

Issue severity follows the rubric defined in `.auto-claude/qa/llm/severity-rubric.md`.

### Quick Reference

| Level | Name | Response Time | Blocks Release |
|-------|------|---------------|----------------|
| **P0** | Critical | Immediate (< 4h) | Yes |
| **P1** | High | Same day (< 24h) | Yes |
| **P2** | Medium | Within sprint | No (warn only) |
| **P3** | Low | Backlog | No |

### Severity Criteria

**P0 - Critical**
- Security breach (auth bypass, data exposure)
- Data loss or corruption
- Complete feature failure (login, checkout, payment)
- Financial impact (incorrect charges)
- Compliance violation

**P1 - High**
- Core feature broken for significant user segment
- No workaround available
- Performance critical (>10s load times)
- Accessibility blocker

**P2 - Medium**
- Feature partially broken
- Workaround available
- Edge case failures
- Visual/layout regression

**P3 - Low**
- Cosmetic defects
- Typos
- Minor polish opportunities

### Risk Tag Mapping

Scenarios with certain `risk_tags` have minimum severity floors:

| Risk Tag | Severity Floor |
|----------|----------------|
| `security` | P0 |
| `data-loss` | P0 |
| `financial` | P0 |
| `privacy` | P0 |
| `compliance` | P0 |
| `ux-critical` | P1 |
| `performance` | P1/P2 |

---

## CI/CD Integration

### Quality Gates

| Gate | P0 | P1 | P2 | P3 |
|------|----|----|----|----|
| PR Merge | Block | Block | Warn | Pass |
| Staging Deploy | Block | Warn | Pass | Pass |
| Production Deploy | Block | Block | Pass | Pass |
| Release Sign-off | 0 | 0 | ≤3 | No limit |

### Pipeline Examples

**GitHub Actions**

```yaml
name: LLM QA

on:
  pull_request:
    branches: [main]

jobs:
  llm-qa:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Run LLM QA
        run: |
          .auto-claude/scripts/run-llm-qa.sh \
            --platform web \
            --scenario-dir ./scenarios/smoke/ \
            --task-id ${{ github.run_id }}

      - name: Upload Artifacts
        uses: actions/upload-artifact@v4
        if: always()
        with:
          name: qa-artifacts
          path: .auto-claude/qa/runs/
          retention-days: 7

      - name: Check Quality Gates
        run: |
          # Check for P0/P1 issues in report
          if grep -q "### P0 — Critical" .auto-claude/qa/runs/*/report.md 2>/dev/null; then
            if ! grep -q "_No P0 issues found._" .auto-claude/qa/runs/*/report.md; then
              echo "P0 issues found - blocking PR"
              exit 1
            fi
          fi
```

**GitLab CI**

```yaml
llm-qa:
  stage: test
  script:
    - .auto-claude/scripts/run-llm-qa.sh
        --platform web
        --scenario-dir ./scenarios/
        --task-id $CI_PIPELINE_ID
  artifacts:
    when: always
    paths:
      - .auto-claude/qa/runs/
    expire_in: 7 days
  rules:
    - if: $CI_MERGE_REQUEST_ID
```

---

## Platform-Specific Runners

The LLM QA Foundation is designed to be extended by platform-specific runners:

| Platform | Runner Script | Task |
|----------|---------------|------|
| Web | `.auto-claude/scripts/run-cloud-web-qa.sh` | 068 |
| Mobile iOS | Platform-specific | 069 |
| Mobile Android | Platform-specific | 069 |
| Desktop Mac | Platform-specific | 070/071 |
| Desktop Windows | Platform-specific | 070/071 |
| Desktop Linux | Platform-specific | 070/071 |

### Creating a Platform Runner

Platform runners should:

1. Accept scenarios in the standard format
2. Execute the journey steps using platform tools
3. Capture artifacts per the scenario's `artifacts` config
4. Update `report.md` with findings
5. Return exit code based on severity (0 = pass, 1 = P0/P1 found)

Example integration:

```bash
#!/bin/bash
# run-cloud-web-qa.sh

# 1. Create output structure using foundation
.auto-claude/scripts/run-llm-qa.sh \
  --platform web \
  --scenario-dir "$SCENARIO_DIR" \
  --task-id "$TASK_ID"

# 2. Execute with Playwright
npx playwright test --config=qa.config.ts

# 3. Collect artifacts
.auto-claude/scripts/collect-qa-artifacts.sh \
  --source-dir ./playwright-report \
  --run-dir "$RUN_DIR"

# 4. Update report with results
# ... platform-specific logic
```

---

## Troubleshooting

### Common Issues

**Scenario validation fails**
```
[run-llm-qa] WARN: Missing required field 'persona' in: scenarios/test.json
```
→ Ensure scenario has all required fields: `scenario_id`, `name`, `platform`, `persona`, `journey`

**No scenarios found**
```
[run-llm-qa] WARN: No scenarios specified. Use --scenario or --scenario-dir
```
→ Provide either `--scenario <file>` or `--scenario-dir <directory>`

**Invalid platform**
```
[run-llm-qa] ERROR: Invalid platform: windows
```
→ Valid platforms: `web`, `mobile-ios`, `mobile-android`, `desktop-mac`, `desktop-windows`, `desktop-linux`

**Permission denied on scripts**
```
bash: .auto-claude/scripts/run-llm-qa.sh: Permission denied
```
→ Make scripts executable: `chmod +x .auto-claude/scripts/*.sh`

### Debug Mode

For verbose output, set `BASH_XTRACEFD`:

```bash
BASH_XTRACEFD=2 bash -x .auto-claude/scripts/run-llm-qa.sh --dry-run
```

---

## Related Documents

| Document | Location | Purpose |
|----------|----------|---------|
| Scenario Schema | `.auto-claude/qa/llm/scenario-schema.json` | JSON Schema for scenarios |
| Severity Rubric | `.auto-claude/qa/llm/severity-rubric.md` | P0-P3 definitions and triage |
| Orchestrator Guide | `.auto-claude/scripts/ORCHESTRATOR.md` | Task orchestration reference |

---

## Changelog

| Date | Change | Author |
|------|--------|--------|
| 2026-02-16 | Initial documentation | Auto-Claude |

---

*This document is part of the LLM QA Foundation (Task 067). For questions or updates, refer to the related spec at `.auto-claude/specs/067-llm-qa-orchestrator-foundation/`.*
