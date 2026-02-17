#!/usr/bin/env bash
set -euo pipefail

# =============================================================================
# collect-qa-artifacts.sh - QA Artifact Collector
#
# Collects and organizes QA artifacts with deterministic folder and file naming.
# Works with run-llm-qa.sh to standardize artifact output structure.
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
Usage: collect-qa-artifacts.sh [OPTIONS]

Collects and organizes QA artifacts with deterministic folder and file naming.
Supports collecting screenshots, logs, traces, and reports from QA runs.

Options:
  --source-dir DIR        Source directory containing raw artifacts
  --run-dir DIR           Target QA run directory (contains metadata.json)
  --task-id ID            Task identifier for organizing artifacts
  --run-id ID             Run identifier
  --platform PLATFORM     Platform: web, mobile-ios, mobile-android,
                          desktop-mac, desktop-windows, desktop-linux
  --screenshots DIR       Collect screenshots from specified directory
  --logs DIR              Collect logs from specified directory
  --traces DIR            Collect traces from specified directory
  --output-dir DIR        Base output directory
                          (default: .auto-claude/qa/runs)
  --dry-run               Show what would be collected without copying
  --list                  List artifacts in the specified run directory
  -h, --help              Show this help message

Artifact Types:
  screenshots/    PNG, JPG, WebP images with timestamp prefixes
  logs/           Console logs, network logs, error logs
  traces/         HAR files, execution traces, network recordings
  report.md       Markdown summary with findings and evidence links

Naming Convention:
  Screenshots: {timestamp}-{description}.{ext}
  Logs:        {type}-{timestamp}.log
  Traces:      {timestamp}-trace.{ext}

Examples:
  # Collect artifacts from a source directory into a run
  collect-qa-artifacts.sh --source-dir ./test-output --run-dir ./runs/067/abc123/web/20240101-120000

  # Collect only screenshots
  collect-qa-artifacts.sh --screenshots ./playwright-report/screenshots --run-dir ./runs/067/abc123/web/20240101-120000

  # List artifacts in a run directory
  collect-qa-artifacts.sh --list --run-dir ./runs/067/abc123/web/20240101-120000

  # Dry run to preview collection
  collect-qa-artifacts.sh --source-dir ./test-output --run-dir ./runs/my-run --dry-run

Environment:
  QA_OUTPUT_DIR           Default output directory (overridden by --output-dir)
USAGE
}

die() {
  echo "[collect-qa-artifacts] ERROR: $*" >&2
  exit 1
}

warn() {
  echo "[collect-qa-artifacts] WARN: $*" >&2
}

info() {
  echo "[collect-qa-artifacts] $*"
}

require_value() {
  local flag="$1"
  local value="${2-}"
  if [[ -z "$value" || "$value" == --* ]]; then
    die "Missing value for ${flag}"
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

# =============================================================================
# Deterministic Naming Functions
# =============================================================================

# Generate deterministic timestamp-based filename
generate_artifact_name() {
  local type="$1"      # screenshot, log, trace
  local source_file="$2"
  local timestamp="${3:-$(date +%Y%m%d-%H%M%S)}"

  local ext="${source_file##*.}"
  local base="${source_file%.*}"
  base="$(basename "$base")"

  # Sanitize filename (remove special chars, replace spaces with hyphens)
  base="$(echo "$base" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9._-]/-/g' | sed 's/--*/-/g')"

  case "$type" in
    screenshot)
      echo "${timestamp}-${base}.${ext}"
      ;;
    log)
      echo "${base}-${timestamp}.log"
      ;;
    trace)
      echo "${timestamp}-trace-${base}.${ext}"
      ;;
    *)
      echo "${timestamp}-${base}.${ext}"
      ;;
  esac
}

# =============================================================================
# Collection Functions
# =============================================================================

collect_screenshots() {
  local source_dir="$1"
  local target_dir="$2"
  local dry_run="$3"
  local timestamp="$4"
  local count=0

  if [[ ! -d "$source_dir" ]]; then
    warn "Screenshots directory not found: $source_dir"
    return 0
  fi

  mkdir -p "$target_dir"

  # Collect PNG, JPG, WebP files
  while IFS= read -r -d '' file; do
    local new_name
    new_name="$(generate_artifact_name screenshot "$file" "$timestamp")"

    if [[ -n "$dry_run" ]]; then
      info "[DRY RUN] Would copy: $file -> $target_dir/$new_name"
    else
      cp "$file" "$target_dir/$new_name"
      info "Collected screenshot: $new_name"
    fi
    ((count++))
  done < <(find "$source_dir" -type f \( -name "*.png" -o -name "*.jpg" -o -name "*.jpeg" -o -name "*.webp" \) -print0 2>/dev/null)

  info "Collected $count screenshot(s)"
  return 0
}

collect_logs() {
  local source_dir="$1"
  local target_dir="$2"
  local dry_run="$3"
  local timestamp="$4"
  local count=0

  if [[ ! -d "$source_dir" ]]; then
    warn "Logs directory not found: $source_dir"
    return 0
  fi

  mkdir -p "$target_dir"

  # Collect log and txt files
  while IFS= read -r -d '' file; do
    local new_name
    new_name="$(generate_artifact_name log "$file" "$timestamp")"

    if [[ -n "$dry_run" ]]; then
      info "[DRY RUN] Would copy: $file -> $target_dir/$new_name"
    else
      cp "$file" "$target_dir/$new_name"
      info "Collected log: $new_name"
    fi
    ((count++))
  done < <(find "$source_dir" -type f \( -name "*.log" -o -name "*.txt" \) -print0 2>/dev/null)

  info "Collected $count log(s)"
  return 0
}

collect_traces() {
  local source_dir="$1"
  local target_dir="$2"
  local dry_run="$3"
  local timestamp="$4"
  local count=0

  if [[ ! -d "$source_dir" ]]; then
    warn "Traces directory not found: $source_dir"
    return 0
  fi

  mkdir -p "$target_dir"

  # Collect HAR, JSON trace files, and zip archives
  while IFS= read -r -d '' file; do
    local new_name
    new_name="$(generate_artifact_name trace "$file" "$timestamp")"

    if [[ -n "$dry_run" ]]; then
      info "[DRY RUN] Would copy: $file -> $target_dir/$new_name"
    else
      cp "$file" "$target_dir/$new_name"
      info "Collected trace: $new_name"
    fi
    ((count++))
  done < <(find "$source_dir" -type f \( -name "*.har" -o -name "*trace*.json" -o -name "*.zip" \) -print0 2>/dev/null)

  info "Collected $count trace(s)"
  return 0
}

# =============================================================================
# List Artifacts Function
# =============================================================================

list_artifacts() {
  local run_dir="$1"

  if [[ ! -d "$run_dir" ]]; then
    die "Run directory not found: $run_dir"
  fi

  info "Artifacts in: $run_dir"
  echo ""

  echo "=== Screenshots ==="
  if [[ -d "$run_dir/screenshots" ]]; then
    ls -la "$run_dir/screenshots" 2>/dev/null || echo "  (empty)"
  else
    echo "  (not found)"
  fi
  echo ""

  echo "=== Logs ==="
  if [[ -d "$run_dir/logs" ]]; then
    ls -la "$run_dir/logs" 2>/dev/null || echo "  (empty)"
  else
    echo "  (not found)"
  fi
  echo ""

  echo "=== Traces ==="
  if [[ -d "$run_dir/traces" ]]; then
    ls -la "$run_dir/traces" 2>/dev/null || echo "  (empty)"
  else
    echo "  (not found)"
  fi
  echo ""

  echo "=== Report ==="
  if [[ -f "$run_dir/report.md" ]]; then
    ls -la "$run_dir/report.md"
  else
    echo "  (not found)"
  fi
  echo ""

  echo "=== Metadata ==="
  if [[ -f "$run_dir/metadata.json" ]]; then
    ls -la "$run_dir/metadata.json"
    echo ""
    cat "$run_dir/metadata.json"
  else
    echo "  (not found)"
  fi
}

# =============================================================================
# Main
# =============================================================================

# Defaults
SOURCE_DIR=""
RUN_DIR=""
TASK_ID=""
RUN_ID=""
PLATFORM=""
SCREENSHOTS_DIR=""
LOGS_DIR=""
TRACES_DIR=""
OUTPUT_DIR="${QA_OUTPUT_DIR:-.auto-claude/qa/runs}"
DRY_RUN=""
LIST_MODE=""

# Parse arguments
while [[ $# -gt 0 ]]; do
  case "$1" in
    --source-dir)
      require_value "$1" "${2-}"
      SOURCE_DIR="$2"
      shift 2
      ;;
    --run-dir)
      require_value "$1" "${2-}"
      RUN_DIR="$2"
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
    --platform)
      require_value "$1" "${2-}"
      PLATFORM="$2"
      shift 2
      ;;
    --screenshots)
      require_value "$1" "${2-}"
      SCREENSHOTS_DIR="$2"
      shift 2
      ;;
    --logs)
      require_value "$1" "${2-}"
      LOGS_DIR="$2"
      shift 2
      ;;
    --traces)
      require_value "$1" "${2-}"
      TRACES_DIR="$2"
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
    --list)
      LIST_MODE="true"
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

# List mode
if [[ -n "$LIST_MODE" ]]; then
  if [[ -z "$RUN_DIR" ]]; then
    die "--run-dir is required for --list mode"
  fi
  list_artifacts "$RUN_DIR"
  exit 0
fi

# Validate platform if provided
if [[ -n "$PLATFORM" ]] && ! validate_platform "$PLATFORM"; then
  die "Invalid platform: $PLATFORM"
fi

# Generate timestamp for deterministic naming
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"

# Determine target directory
if [[ -n "$RUN_DIR" ]]; then
  TARGET_DIR="$RUN_DIR"
elif [[ -n "$TASK_ID" && -n "$RUN_ID" && -n "$PLATFORM" ]]; then
  TARGET_DIR="${OUTPUT_DIR}/${TASK_ID}/${RUN_ID}/${PLATFORM}/${TIMESTAMP}"
else
  die "Either --run-dir or (--task-id, --run-id, --platform) must be provided"
fi

# Ensure target directory exists
mkdir -p "$TARGET_DIR"

# Collect from specific directories if provided
if [[ -n "$SCREENSHOTS_DIR" ]]; then
  collect_screenshots "$SCREENSHOTS_DIR" "$TARGET_DIR/screenshots" "$DRY_RUN" "$TIMESTAMP"
fi

if [[ -n "$LOGS_DIR" ]]; then
  collect_logs "$LOGS_DIR" "$TARGET_DIR/logs" "$DRY_RUN" "$TIMESTAMP"
fi

if [[ -n "$TRACES_DIR" ]]; then
  collect_traces "$TRACES_DIR" "$TARGET_DIR/traces" "$DRY_RUN" "$TIMESTAMP"
fi

# Collect from source directory if provided (auto-detect subdirectories)
if [[ -n "$SOURCE_DIR" ]]; then
  if [[ ! -d "$SOURCE_DIR" ]]; then
    die "Source directory not found: $SOURCE_DIR"
  fi

  info "Collecting artifacts from: $SOURCE_DIR"

  # Auto-detect common artifact subdirectories
  if [[ -d "$SOURCE_DIR/screenshots" ]]; then
    collect_screenshots "$SOURCE_DIR/screenshots" "$TARGET_DIR/screenshots" "$DRY_RUN" "$TIMESTAMP"
  fi

  if [[ -d "$SOURCE_DIR/logs" ]]; then
    collect_logs "$SOURCE_DIR/logs" "$TARGET_DIR/logs" "$DRY_RUN" "$TIMESTAMP"
  fi

  if [[ -d "$SOURCE_DIR/traces" ]]; then
    collect_traces "$SOURCE_DIR/traces" "$TARGET_DIR/traces" "$DRY_RUN" "$TIMESTAMP"
  fi

  # Also check for Playwright-specific directories
  if [[ -d "$SOURCE_DIR/test-results" ]]; then
    collect_screenshots "$SOURCE_DIR/test-results" "$TARGET_DIR/screenshots" "$DRY_RUN" "$TIMESTAMP"
    collect_traces "$SOURCE_DIR/test-results" "$TARGET_DIR/traces" "$DRY_RUN" "$TIMESTAMP"
  fi

  # Copy report if it exists
  if [[ -f "$SOURCE_DIR/report.md" && -z "$DRY_RUN" ]]; then
    cp "$SOURCE_DIR/report.md" "$TARGET_DIR/"
    info "Copied report.md"
  fi
fi

# Summary
if [[ -n "$DRY_RUN" ]]; then
  info "Dry run completed. No files were copied."
else
  info "Collection completed. Artifacts at: $TARGET_DIR"
fi

exit 0
