# Refreshing the Auto-Claude Kanban Board

This is about the **Auto-Claude CLI** (`run.py` from [AndyMik90/Auto-Claude](https://github.com/AndyMik90/Auto-Claude)), not the Cursor IDE CLI.

## Auto-Claude CLI: no refresh-board command

**The Auto-Claude CLI does not provide a command to refresh the kanban board.** Commands like `run.py --spec <id>`, `run.py --list`, `run.py --auto-continue` control task execution and listing; there is no documented `--refresh-board` or similar. The board's columns and cards are driven by state on disk (`.auto-claude/specs/*/implementation_plan.json` and worktree copies). After the script (or anything else) updates those files, the board will show the new state only after it re-reads from disk.

## How to refresh the board

1. **Manually:** Close the Auto-Claude/planning view and open it again, or switch to another tab and back.
2. **Reload Window (Cursor):** Reloading the Cursor window forces the board to re-read from disk. Command Palette: **Cmd+Shift+P** → type **Reload Window** → **Developer: Reload Window**.
3. **From the script:** When using the UI fallback, `stop-then-start-in-progress.sh` triggers **Developer: Reload Window** via AppleScript so the board shows updated columns before clicking Move/Start.

## OpenClaw External QA Status Sync

When QA runs execute outside Auto-Claude (for example OpenClaw cloud web/mobile runs), refresh behavior has two layers:

1. **Auto-Claude board refresh:** still requires board re-read from disk (manual reopen or Reload Window).
2. **External run status sync:** write run summaries/artifacts into `.auto-claude/qa/runs/...` and post a task/PR update so the board context stays aligned with external execution state.

Recommended pattern:
- Start cloud run (`run-cloud-web-qa.sh` or `run-cloud-mobile-qa.sh`)
- Persist `summary.md` and `run.log`
- Add a concise status comment to the related task/PR
- Reload board only when task metadata changes

## Summary

| Method | How |
|--------|-----|
| Auto-Claude CLI | No refresh-board command; `run.py` is for run/list/start, not UI refresh. |
| Manual | Reopen the board or Command Palette → Developer: Reload Window in Cursor. |
| Script | Triggers Cursor's Reload Window via AppleScript so the board re-reads from disk. |
