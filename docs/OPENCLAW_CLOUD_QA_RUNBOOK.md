# OpenClaw Cloud QA Runbook

## Purpose

Run always-on, agent-orchestrated QA for web and mobile products with cloud execution and clear observability.

## Architecture

1. OpenClaw gateway runs on a cloud VM.
2. Dedicated nodes execute QA commands (`tools.exec.host=node`).
3. Browser tests run through Browserless/Browserbase.
4. Mobile tests run through BrowserStack/Sauce/Device Farm.
5. Results are published into artifacts, PR comments, and issue updates.

## Prerequisites

- OpenClaw installed on gateway host
- OpenClaw node(s) registered and approved
- Anthropic API key configured for model calls
- Langfuse keys configured for observability
- Provider credentials configured (browser/mobile)

## Setup Steps

### 1) Gateway config

1. Copy `.auto-claude/openclaw/openclaw.gateway.example.json` to your active gateway config location.
2. Replace all `REPLACE_...` tokens.
3. Set gateway auth token and bind mode (`tailnet` recommended).

### 2) Node security policy

1. Use `.auto-claude/openclaw/node-policy.example.json` as the source of truth.
2. Keep `security=allowlist`.
3. Register only required binaries via `openclaw approvals allowlist add`.

### 3) Browser provider profile

1. Copy `.auto-claude/openclaw/providers/browser.profile.example.json`.
2. Use either Browserless or Browserbase profile.
3. Validate cloud browser connectivity before full suite runs.

### 4) Mobile provider wiring

1. Choose one provider from `.auto-claude/openclaw/providers/mobile.provider-matrix.md`.
2. Export provider credentials and Appium endpoint variables.
3. Run one smoke test per platform first.

## Execution Commands

### Web QA

```bash
bash .auto-claude/scripts/run-cloud-web-qa.sh --suite smoke --provider browserless
```

### Mobile QA

```bash
export APPIUM_SERVER_URL="https://.../wd/hub"
bash .auto-claude/scripts/run-cloud-mobile-qa.sh --suite smoke --platform android --provider browserstack
```

## Artifacts

- Web: `.auto-claude/qa/runs/web/<provider>/<suite>/<timestamp>/`
- Mobile: `.auto-claude/qa/runs/mobile/<provider>/<platform>/<timestamp>/`

Each run should include:
- `metadata.env`
- `run.log`
- `summary.md`
- provider-native links for videos/screenshots/logs

## Merge Gate Policy

Minimum gate before auto-merge:
1. Required CI checks green.
2. Web smoke QA green.
3. Mobile smoke QA green for affected platform(s).
4. No open P0/P1 findings in report.

## Recovery and Incident Handling

### Incident: provider outage

1. Mark QA state as `blocked:provider`.
2. Retry once after backoff.
3. Switch to backup provider profile if available.
4. Require manual merge decision while provider degraded.

### Incident: node unavailable

1. Re-queue run on backup node.
2. Check node heartbeat and approvals cache.
3. If no backup node, downgrade to manual QA.

### Incident: model limits/failed calls

1. Confirm API key validity and rate-limit status.
2. Backoff and retry with jitter.
3. If persistent, pause autonomous merges and keep artifact generation active.

## Ownership

- Platform owner: DevOps
- QA owner: Product QA lead
- Escalation: Engineering manager

