# Orchestrator on Top of Auto-Claude

This document describes the **orchestrator layer** built on [Auto-Claude](https://github.com/AndyMik90/Auto-Claude): scripts that decide **which** tasks to run, **when**, and **how** (stop → move → start), without relying on manual board interaction. The base is **`stop-then-start-in-progress.sh`**; the scheduler **`schedule-recover-in-3h.sh`** adds delayed execution.

## What Is the Orchestrator?

Auto-Claude provides:

- **Task specs** (`.auto-claude/specs/<spec-id>/`) with `implementation_plan.json`, `review_state.json`, and optional `PAUSE`
- **Worktrees** (`.auto-claude/worktrees/tasks/<spec-id>/`) for isolated builds and `.auto-claude-status` for run state
- **CLI:** `run.py --spec <id> --auto-continue` to start a task; **stop** by creating a `PAUSE` file in the spec dir
- **UI:** Kanban board in Cursor (Human Review, In Progress, etc.)

The **orchestrator** sits on top and:

1. **Discovers** which tasks should be moved or started (human review, in progress but not running, building/paused for restart).
2. **Stops** running tasks when a clean restart is needed (PAUSE file, wait, remove).
3. **Moves** task state via metadata so the backend/UI treats them as In Progress and approved.
4. **Starts** tasks via Auto-Claude CLI or, if CLI is missing, via UI automation (Reload Window, Move/Recover, Start).
5. **Schedules** the whole flow after a delay (e.g. 1 hour or 3 hours) for credit reset or unattended runs.

So the orchestrator is the **single entry point** for “run these tasks in this order, at this time,” using Auto-Claude as the execution backend.

## Base Script: `stop-then-start-in-progress.sh`

**Role:** The core orchestrator loop: **stop → move → start** for all target specs.

| Phase   | What it does |
|--------|----------------|
| **Discovery** | Union of: (1) worktrees with state building/paused, (2) specs in human_review, (3) specs in_progress but not running. |
| **Stop**      | For each target: create `PAUSE` in main and worktree spec dirs → wait 10s → remove `PAUSE` so agents exit cleanly before restart. |
| **Move**      | Patch `implementation_plan.json` (status/planStatus = in_progress) and `review_state.json` (approved = true) in main and worktree copies. |
| **Start**     | If `run.py` exists: `python run.py --spec <id> --auto-continue --project-dir <root>` for each (background). Else: Cursor Reload Window, then click Move to In Progress / Recover, then Start. |

**Usage:**

```bash
# Run now
./.auto-claude/scripts/stop-then-start-in-progress.sh

# Run after delay (e.g. 1 hour = 3600, 3 hours = 10800)
./.auto-claude/scripts/stop-then-start-in-progress.sh 3600
```

**Integration points:**

- Reads: `.auto-claude/specs/*/implementation_plan.json`, `.auto-claude/worktrees/tasks/*/.auto-claude-status`, worktree `implementation_plan.json`.
- Writes: same files (status, planStatus, approved), and `PAUSE` (temporarily).
- Invokes: `run.py` (when present) or Cursor via AppleScript.

## Scheduler: `schedule-recover-in-3h.sh`

**Role:** Run the base script after a configurable delay in the background (e.g. after credit limits reset).

```bash
# 1 hour
./.auto-claude/scripts/schedule-recover-in-3h.sh 3600

# 3 hours (default)
./.auto-claude/scripts/schedule-recover-in-3h.sh
./.auto-claude/scripts/schedule-recover-in-3h.sh 10800
```

- PID is stored in `.auto-claude/.recover-scheduled.pid`; log in `.auto-claude/recover-tasks.log`.
- Cancel: `kill $(cat .auto-claude/.recover-scheduled.pid)`.

## Data Model the Orchestrator Relies On

| Artifact | Location | Orchestrator use |
|----------|----------|-------------------|
| Spec list & status | `.auto-claude/specs/<id>/implementation_plan.json` | Discovery (human_review, in_progress); move (status, planStatus). |
| Review gate | `.auto-claude/specs/<id>/review_state.json` | Move (approved) so start is not blocked. |
| Stop signal | `.auto-claude/specs/<id>/PAUSE` (and worktree copy) | Stop phase: create → wait → remove. |
| Run state | `.auto-claude/worktrees/tasks/<id>/.auto-claude-status` | Discovery (building/paused). |
| Worktree spec copy | `.auto-claude/worktrees/tasks/<id>/.auto-claude/specs/<id>/` | Move and stop applied here too so UI and backend stay in sync. |

The board has no dedicated refresh CLI; the script triggers Cursor’s **Reload Window** so the board re-reads from disk after the move.

## Extending the Orchestrator

This script set is intended as the **base** for a fuller orchestrator. Possible extensions:

- **Policies:** Run only specs matching labels, phases, or a allowlist/blocklist (e.g. from a config file or env).
- **Concurrency:** Limit how many specs run at once (e.g. start N, wait for one to finish before starting the next).
- **Ordering:** Start by dependency order (using `implementation_plan.json` or a separate graph).
- **Multi-project:** Point at multiple project roots and run stop-move-start per project (e.g. loop over `AUTO_CLAUDE_PROJECTS`).
- **Health / retries:** Poll worktree status or logs; restart failed or stuck specs and cap retries.
- **Notifications:** On completion or failure (e.g. Slack, email, or a webhook) using the same log/PID output.
- **Cron / systemd:** Schedule `schedule-recover-in-3h.sh 3600` or the base script with a fixed delay from cron or a timer.

Keeping **stop → move → start** and **discovery → stop → move → start** as the core loop preserves compatibility with Auto-Claude while adding policy and scheduling on top.

## OpenClaw Cloud QA Orchestration

For cloud-based QA execution (web + mobile), use the OpenClaw layer as an external execution plane while keeping Auto-Claude as the task planner.

### Flow

1. Auto-Claude moves task to `in_progress` and prepares scenario context.
2. OpenClaw receives the run trigger and executes test jobs on approved nodes.
3. Web suites run via `.auto-claude/scripts/run-cloud-web-qa.sh`.
4. Mobile suites run via `.auto-claude/scripts/run-cloud-mobile-qa.sh`.
5. Artifacts are written under `.auto-claude/qa/runs/...` and summarized back to task/PR.

### Operational Notes

- Keep OpenClaw `tools.exec.security` in `allowlist` mode.
- Prefer Anthropic API key auth for 24/7 automation reliability.
- Use Langfuse tracing to track run latency, failures, and token spend.
- If provider outage occurs, mark task blocked and switch to backup provider profile.

## Desktop LLM QA Orchestration

For desktop application QA (Windows VM/emulator and macOS installed app), use the desktop LLM QA runner.

### Flow

1. Auto-Claude moves task to `in_progress` and prepares scenario context.
2. Desktop QA receives the run trigger and executes test scenarios.
3. Windows suites run via `.auto-claude/scripts/run-desktop-llm-qa.sh --platform windows`.
4. macOS suites run via `.auto-claude/scripts/run-desktop-llm-qa.sh --platform macos`.
5. Artifacts are written under `.auto-claude/qa/runs/desktop/...` and summarized.

### Operational Notes

- Windows requires `WINDOWS_VM_HOST`, `WINDOWS_VM_USER` for VM connectivity.
- macOS requires `MACOS_APP_BUNDLE_ID` or `MACOS_APP_PATH` for app identification.
- Use `DESKTOP_QA_DRY_RUN=true` for configuration validation without execution.
- Evidence (screenshots, logs) is captured per-run under timestamped directories.
- Scenario packs are located at:
  - Windows: `.auto-claude/qa/desktop/windows/scenarios.json`
  - macOS: `.auto-claude/qa/desktop/macos/scenarios.json`

## Related Docs

| Doc | Purpose |
|-----|--------|
| **MOVE_THEN_START.md** | Why and how move (metadata) + start (CLI) gives autonomous move-then-start. |
| **AUTO_CLAUDE_CLI.md** | Auto-Claude CLI: start (`run.py --spec`), stop (PAUSE file). |
| **README-recover.md** | How to run and schedule the recover flow; UI fallback and Accessibility. |
| **REFRESH_BOARD.md** | No Auto-Claude CLI to refresh the board; script uses Cursor Reload Window. |
| **`docs/OPENCLAW_CLOUD_QA_RUNBOOK.md`** | OpenClaw cloud QA setup, execution, and incident runbook. |
| **`docs/OPENCLAW_COST_BASELINE.md`** | Fixed and variable cost baseline for cloud QA system. |
| **`docs/LLM_QA_DESKTOP.md`** | Desktop LLM QA setup, execution, and troubleshooting. |

## Summary

- **Orchestrator base:** `stop-then-start-in-progress.sh` — discovery, stop, move, start for all target specs.
- **Scheduler:** `schedule-recover-in-3h.sh [delay_seconds]` — run that script after a delay (e.g. 1h or 3h).
- **Data:** Spec and worktree dirs, `implementation_plan.json`, `review_state.json`, `PAUSE`, `.auto-claude-status`.
- **Extension:** Use this as the base for policies, concurrency, ordering, multi-project, health, retries, and notifications on top of Auto-Claude.
