#!/usr/bin/env bash
set -euo pipefail

# =============================================================================
# run-llm-qa.sh - LLM QA Orchestrator
#
# Orchestrates LLM-driven exploratory QA scenario execution across platforms.
# Creates standardized output directory structure and report templates.
#
# Artifact Layout: .auto-claude/qa/runs/{task_id}/{run_id}/{platform}/{timestamp}/
# =============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd -P)"

# =============================================================================
# Helpers
# =============================================================================

usage() {
  cat <<'USAGE'
Usage: run-llm-qa.sh [OPTIONS]

Orchestrates LLM-driven exploratory QA scenario execution and creates
standardized artifact output structure.

Options:
  --platform PLATFORM     Target platform: web, mobile-ios, mobile-android,
                          desktop-mac, desktop-windows, desktop-linux
                          (default: web)
  --scenario FILE         Path to scenario JSON file (validates against schema)
  --scenario-dir DIR      Directory containing scenario JSON files
  --task-id ID            Task identifier for organizing artifacts
                          (default: generated from timestamp)
  --run-id ID             Run identifier (default: auto-generated UUID)
  --output-dir DIR        Base output directory
                          (default: .auto-claude/qa/runs)
  --dry-run               Create output structure and report template without
                          executing scenarios
  --validate-only         Only validate scenario files, don't execute
  -h, --help              Show this help message

Output Structure:
  {output-dir}/{task_id}/{run_id}/{platform}/{timestamp}/
    ├── metadata.json       Run configuration and environment
    ├── scenarios/          Copy of executed scenarios
    ├── screenshots/        Captured screenshots
    ├── logs/               Console and network logs
    ├── traces/             Execution traces
    └── report.md           Markdown summary report

Environment:
  LLM_QA_PLATFORM         Default platform (overridden by --platform)
  LLM_QA_OUTPUT_DIR       Default output directory (overridden by --output-dir)

Examples:
  # Dry run to create output structure
  run-llm-qa.sh --platform web --task-id 067 --dry-run

  # Execute a single scenario
  run-llm-qa.sh --platform web --scenario scenarios/login-flow.json

  # Execute all scenarios in a directory
  run-llm-qa.sh --platform mobile-ios --scenario-dir scenarios/mobile/

  # Validate scenarios without execution
  run-llm-qa.sh --validate-only --scenario-dir scenarios/
USAGE
}

die() {
  echo "[run-llm-qa] ERROR: $*" >&2
  exit 1
}

warn() {
  echo "[run-llm-qa] WARN: $*" >&2
}

info() {
  echo "[run-llm-qa] $*"
}

require_value() {
  local flag="$1"
  local value="${2-}"
  if [[ -z "$value" || "$value" == --* ]]; then
    die "Missing value for ${flag}"
  fi
}

generate_uuid() {
  if command -v uuidgen &>/dev/null; then
    uuidgen | tr '[:upper:]' '[:lower:]'
  elif [[ -r /proc/sys/kernel/random/uuid ]]; then
    cat /proc/sys/kernel/random/uuid
  else
    # Fallback: timestamp + random
    printf '%s-%04x%04x' "$(date +%s)" $RANDOM $RANDOM
  fi
}

validate_platform() {
  local platform="$1"
  case "$platform" in
    web|mobile-ios|mobile-android|desktop-mac|desktop-windows|desktop-linux)
      return 0
      ;;
    *)
      return 1
      ;;
  esac
}

validate_scenario() {
  local scenario_file="$1"

  if [[ ! -f "$scenario_file" ]]; then
    warn "Scenario file not found: $scenario_file"
    return 1
  fi

  # Check if it's valid JSON
  if ! python3 -c "import json; json.load(open('$scenario_file'))" 2>/dev/null; then
    warn "Invalid JSON in scenario: $scenario_file"
    return 1
  fi

  # Check required fields
  local required_fields=("scenario_id" "name" "platform" "persona" "journey")
  for field in "${required_fields[@]}"; do
    if ! python3 -c "import json; d=json.load(open('$scenario_file')); assert '$field' in d" 2>/dev/null; then
      warn "Missing required field '$field' in: $scenario_file"
      return 1
    fi
  done

  info "Validated: $scenario_file"
  return 0
}

# =============================================================================
# Report Template Generation
# =============================================================================

generate_report_template() {
  local output_dir="$1"
  local platform="$2"
  local task_id="$3"
  local run_id="$4"
  local timestamp="$5"
  local scenario_count="$6"
  local dry_run="$7"

  cat > "${output_dir}/report.md" <<REPORT
# LLM QA Run Report

## Summary

| Field | Value |
|-------|-------|
| **Task ID** | \`${task_id}\` |
| **Run ID** | \`${run_id}\` |
| **Platform** | \`${platform}\` |
| **Timestamp** | \`${timestamp}\` |
| **Scenarios** | ${scenario_count} |
| **Status** | ${dry_run:+DRY RUN - }Pending |

## Execution Overview

<!-- Auto-populated during execution -->

| Metric | Value |
|--------|-------|
| Total Scenarios | ${scenario_count} |
| Passed | — |
| Failed | — |
| Skipped | — |
| Duration | — |

## Findings

<!-- Issues discovered during QA execution -->

### P0 — Critical

_No P0 issues found._

### P1 — High

_No P1 issues found._

### P2 — Medium

_No P2 issues found._

### P3 — Low

_No P3 issues found._

## Scenario Results

<!-- Detailed results per scenario -->

| Scenario ID | Name | Status | Duration | Findings |
|-------------|------|--------|----------|----------|
| _pending_ | — | — | — | — |

## Evidence Links

### Screenshots

\`${output_dir}/screenshots/\`

### Logs

- Console: \`${output_dir}/logs/console.log\`
- Network: \`${output_dir}/logs/network.log\`

### Traces

\`${output_dir}/traces/\`

## Reproduction Steps

<!-- For any failed scenarios, include exact reproduction steps -->

_No failures to reproduce._

## Environment

\`\`\`json
$(cat "${output_dir}/metadata.json" 2>/dev/null || echo '{}')
\`\`\`

## Notes

<!-- Additional context or observations from the QA run -->

---

_Generated by run-llm-qa.sh on ${timestamp}_
_Refer to [severity-rubric.md](../../qa/llm/severity-rubric.md) for severity definitions_
REPORT
}

# =============================================================================
# Output Structure Creation
# =============================================================================

create_output_structure() {
  local output_dir="$1"

  mkdir -p "${output_dir}/scenarios"
  mkdir -p "${output_dir}/screenshots"
  mkdir -p "${output_dir}/logs"
  mkdir -p "${output_dir}/traces"

  info "Created output structure: ${output_dir}"
}

generate_metadata() {
  local output_dir="$1"
  local platform="$2"
  local task_id="$3"
  local run_id="$4"
  local timestamp="$5"
  local scenario_files="$6"
  local dry_run="$7"

  cat > "${output_dir}/metadata.json" <<METADATA
{
  "task_id": "${task_id}",
  "run_id": "${run_id}",
  "platform": "${platform}",
  "timestamp": "${timestamp}",
  "created_at": "$(date -u +%Y-%m-%dT%H:%M:%SZ)",
  "dry_run": ${dry_run:-false},
  "scenario_files": ${scenario_files},
  "environment": {
    "hostname": "$(hostname)",
    "user": "${USER:-unknown}",
    "pwd": "${PWD}",
    "script_version": "1.0.0"
  },
  "schema_ref": ".auto-claude/qa/llm/scenario-schema.json",
  "severity_rubric_ref": ".auto-claude/qa/llm/severity-rubric.md"
}
METADATA
}

# =============================================================================
# Main
# =============================================================================

# Defaults
PLATFORM="${LLM_QA_PLATFORM:-web}"
SCENARIO_FILE=""
SCENARIO_DIR=""
TASK_ID=""
RUN_ID=""
OUTPUT_DIR="${LLM_QA_OUTPUT_DIR:-.auto-claude/qa/runs}"
DRY_RUN=""
VALIDATE_ONLY=""

# Parse arguments
while [[ $# -gt 0 ]]; do
  case "$1" in
    --platform)
      require_value "$1" "${2-}"
      PLATFORM="$2"
      shift 2
      ;;
    --scenario)
      require_value "$1" "${2-}"
      SCENARIO_FILE="$2"
      shift 2
      ;;
    --scenario-dir)
      require_value "$1" "${2-}"
      SCENARIO_DIR="$2"
      shift 2
      ;;
    --task-id)
      require_value "$1" "${2-}"
      TASK_ID="$2"
      shift 2
      ;;
    --run-id)
      require_value "$1" "${2-}"
      RUN_ID="$2"
      shift 2
      ;;
    --output-dir)
      require_value "$1" "${2-}"
      OUTPUT_DIR="$2"
      shift 2
      ;;
    --dry-run)
      DRY_RUN="true"
      shift
      ;;
    --validate-only)
      VALIDATE_ONLY="true"
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      die "Unknown argument: $1"
      ;;
  esac
done

# Validate platform
if ! validate_platform "$PLATFORM"; then
  die "Invalid platform: $PLATFORM. Valid options: web, mobile-ios, mobile-android, desktop-mac, desktop-windows, desktop-linux"
fi

# Generate task_id and run_id if not provided
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
if [[ -z "$TASK_ID" ]]; then
  TASK_ID="qa-${TIMESTAMP%%-*}"
fi
if [[ -z "$RUN_ID" ]]; then
  RUN_ID="$(generate_uuid)"
fi

# Collect scenario files
SCENARIO_FILES_JSON="[]"
SCENARIO_COUNT=0

if [[ -n "$SCENARIO_FILE" ]]; then
  if [[ -f "$SCENARIO_FILE" ]]; then
    SCENARIO_FILES_JSON="[\"${SCENARIO_FILE}\"]"
    SCENARIO_COUNT=1
  else
    die "Scenario file not found: $SCENARIO_FILE"
  fi
elif [[ -n "$SCENARIO_DIR" ]]; then
  if [[ ! -d "$SCENARIO_DIR" ]]; then
    die "Scenario directory not found: $SCENARIO_DIR"
  fi
  # Find all JSON files in directory
  mapfile -t scenarios < <(find "$SCENARIO_DIR" -name "*.json" -type f 2>/dev/null)
  SCENARIO_COUNT=${#scenarios[@]}
  if [[ $SCENARIO_COUNT -gt 0 ]]; then
    SCENARIO_FILES_JSON=$(printf '%s\n' "${scenarios[@]}" | python3 -c "import sys, json; print(json.dumps([l.strip() for l in sys.stdin]))")
  fi
fi

# Validate-only mode
if [[ -n "$VALIDATE_ONLY" ]]; then
  info "Validating scenarios..."

  if [[ -n "$SCENARIO_FILE" ]]; then
    validate_scenario "$SCENARIO_FILE" || exit 1
  elif [[ -n "$SCENARIO_DIR" ]]; then
    errors=0
    for scenario in "${scenarios[@]}"; do
      validate_scenario "$scenario" || ((errors++))
    done
    if [[ $errors -gt 0 ]]; then
      die "Validation failed for $errors scenario(s)"
    fi
  else
    die "No scenarios specified. Use --scenario or --scenario-dir"
  fi

  info "All scenarios validated successfully"
  exit 0
fi

# Build output path
RUN_OUTPUT_DIR="${OUTPUT_DIR}/${TASK_ID}/${RUN_ID}/${PLATFORM}/${TIMESTAMP}"

# Create output structure
info "Creating output structure..."
create_output_structure "$RUN_OUTPUT_DIR"

# Generate metadata
info "Generating metadata..."
generate_metadata "$RUN_OUTPUT_DIR" "$PLATFORM" "$TASK_ID" "$RUN_ID" "$TIMESTAMP" "$SCENARIO_FILES_JSON" "${DRY_RUN:-false}"

# Copy scenarios to output directory
if [[ -n "$SCENARIO_FILE" && -f "$SCENARIO_FILE" ]]; then
  cp "$SCENARIO_FILE" "${RUN_OUTPUT_DIR}/scenarios/"
  info "Copied scenario: $SCENARIO_FILE"
elif [[ -n "$SCENARIO_DIR" && ${#scenarios[@]} -gt 0 ]]; then
  for scenario in "${scenarios[@]}"; do
    cp "$scenario" "${RUN_OUTPUT_DIR}/scenarios/"
  done
  info "Copied ${#scenarios[@]} scenarios"
fi

# Generate report template
info "Generating report template..."
generate_report_template "$RUN_OUTPUT_DIR" "$PLATFORM" "$TASK_ID" "$RUN_ID" "$TIMESTAMP" "$SCENARIO_COUNT" "$DRY_RUN"

# Dry-run mode - exit here
if [[ -n "$DRY_RUN" ]]; then
  info "Dry run completed. Output structure created at:"
  info "  ${RUN_OUTPUT_DIR}"
  info ""
  info "Contents:"
  ls -la "$RUN_OUTPUT_DIR"
  info ""
  info "To execute scenarios, run without --dry-run"
  exit 0
fi

# Execution placeholder
if [[ $SCENARIO_COUNT -eq 0 ]]; then
  warn "No scenarios specified. Use --scenario or --scenario-dir to provide scenarios."
  info "Output structure created at: ${RUN_OUTPUT_DIR}"
  exit 0
fi

info "Ready to execute $SCENARIO_COUNT scenario(s) on platform: $PLATFORM"
info "Output directory: ${RUN_OUTPUT_DIR}"
info ""
info "Note: Actual scenario execution requires platform-specific runners:"
info "  - Web: .auto-claude/scripts/run-cloud-web-qa.sh"
info "  - Mobile: Platform-specific scripts (tasks 068, 069)"
info "  - Desktop: Platform-specific scripts (tasks 070, 071)"
info ""
info "This script creates the artifact structure and report template."
info "Platform-specific runners will populate results."

exit 0
