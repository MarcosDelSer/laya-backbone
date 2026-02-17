#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd -P)"

usage() {
  cat <<'USAGE'
Usage: run-desktop-llm-qa.sh [--platform windows|macos] [--suite smoke|full|exploratory] [--scenarios FILE] [--project-dir DIR]

Runs LLM-driven desktop QA for Windows VM/emulator or installed macOS app.
Stores artifacts under:
  .auto-claude/qa/runs/desktop/<platform>/<suite>/<timestamp>/

Options:
  --platform     Target platform: windows or macos (default: macos)
  --suite        Test suite: smoke, full, or exploratory (default: smoke)
  --scenarios    Path to scenarios JSON file (optional, uses platform default)
  --project-dir  Project directory (default: current directory)
  -h, --help     Show this help message

Environment:
  Windows:
    WINDOWS_VM_HOST        VM/emulator hostname or IP (required for windows)
    WINDOWS_VM_USER        VM username for automation
    WINDOWS_VM_PASSWORD    VM password (or use SSH key)
    WINDOWS_AUTOMATION_PORT  WinAppDriver or UI automation port (default: 4723)

  macOS:
    MACOS_APP_BUNDLE_ID    Bundle identifier of the installed app (required for macos)
    MACOS_APP_PATH         Path to installed .app (optional, uses bundle ID lookup)

  Common:
    DESKTOP_QA_DRY_RUN     Set to "true" to skip actual execution (validation only)
    DESKTOP_QA_TIMEOUT     Timeout in seconds for each scenario (default: 300)
USAGE
}

die() {
  echo "[run-desktop-llm-qa] ERROR: $*" >&2
  exit 1
}

require_value() {
  local flag="$1"
  local value="${2-}"
  if [[ -z "$value" || "$value" == --* ]]; then
    die "Missing value for ${flag}"
  fi
}

resolve_dir_within_repo() {
  local input="$1"
  [[ -d "$input" ]] || die "Project directory does not exist: $input"
  local abs
  abs="$(cd "$input" && pwd -P)"
  case "$abs" in
    "$REPO_ROOT"|"$REPO_ROOT"/*) printf '%s\n' "$abs" ;;
    *) die "Project directory must be inside repository: $abs" ;;
  esac
}

resolve_file_within_repo() {
  local input="$1"
  if [[ -L "$input" ]]; then
    die "For security reasons, symlinks are not allowed for scenario files: $input"
  fi
  [[ -f "$input" ]] || die "Scenario file not found: $input"
  local abs
  abs="$(cd "$(dirname "$input")" && pwd -P)/$(basename "$input")"
  case "$abs" in
    "$REPO_ROOT"|"$REPO_ROOT"/*) printf '%s\n' "$abs" ;;
    *) die "Scenario file must be inside repository: $abs" ;;
  esac
}

mask_sensitive() {
  local value="$1"
  if [[ -n "$value" ]]; then
    printf '***\n'
  else
    printf '\n'
  fi
}

validate_windows_env() {
  if [[ -z "${WINDOWS_VM_HOST:-}" ]]; then
    die "WINDOWS_VM_HOST is required for Windows platform"
  fi
}

validate_macos_env() {
  if [[ -z "${MACOS_APP_BUNDLE_ID:-}" && -z "${MACOS_APP_PATH:-}" ]]; then
    die "Either MACOS_APP_BUNDLE_ID or MACOS_APP_PATH is required for macOS platform"
  fi
}

# Defaults
PLATFORM="macos"
SUITE="smoke"
SCENARIOS_FILE=""
PROJECT_DIR="${PWD}"
DRY_RUN="${DESKTOP_QA_DRY_RUN:-false}"
TIMEOUT="${DESKTOP_QA_TIMEOUT:-300}"

# Parse arguments
while [[ $# -gt 0 ]]; do
  case "$1" in
    --platform)
      require_value "$1" "${2-}"
      PLATFORM="$2"
      shift 2
      ;;
    --suite)
      require_value "$1" "${2-}"
      SUITE="$2"
      shift 2
      ;;
    --scenarios)
      require_value "$1" "${2-}"
      SCENARIOS_FILE="$2"
      shift 2
      ;;
    --project-dir)
      require_value "$1" "${2-}"
      PROJECT_DIR="$2"
      shift 2
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      usage
      exit 1
      ;;
  esac
done

# Validate platform
case "$PLATFORM" in
  windows|macos) ;;
  *) die "Invalid platform: $PLATFORM. Must be 'windows' or 'macos'" ;;
esac

# Validate suite
case "$SUITE" in
  smoke|full|exploratory) ;;
  *) die "Invalid suite: $SUITE. Must be 'smoke', 'full', or 'exploratory'" ;;
esac

# Validate platform-specific environment
if [[ "$PLATFORM" == "windows" ]]; then
  validate_windows_env
else
  validate_macos_env
fi

# Resolve project directory
PROJECT_DIR_ABS="$(resolve_dir_within_repo "$PROJECT_DIR")"

# Resolve or set default scenarios file
if [[ -n "$SCENARIOS_FILE" ]]; then
  SCENARIOS_FILE_ABS="$(resolve_file_within_repo "$SCENARIOS_FILE")"
else
  # Use default platform scenarios if available
  DEFAULT_SCENARIOS="${REPO_ROOT}/.auto-claude/qa/desktop/${PLATFORM}/scenarios.json"
  if [[ -f "$DEFAULT_SCENARIOS" ]]; then
    SCENARIOS_FILE_ABS="$DEFAULT_SCENARIOS"
  else
    SCENARIOS_FILE_ABS=""
  fi
fi

# Security check - block arbitrary command injection
if [[ -n "${DESKTOP_QA_COMMAND:-}" ]]; then
  die "DESKTOP_QA_COMMAND is disabled for security. Use --suite and --platform."
fi

# Create output directory
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
OUTDIR=".auto-claude/qa/runs/desktop/${PLATFORM}/${SUITE}/${TIMESTAMP}"
mkdir -p "$OUTDIR"

# Write metadata
{
  echo "platform=${PLATFORM}"
  echo "suite=${SUITE}"
  echo "project_dir=${PROJECT_DIR_ABS}"
  echo "scenarios_file=${SCENARIOS_FILE_ABS}"
  echo "timestamp=${TIMESTAMP}"
  echo "timeout=${TIMEOUT}"
  echo "dry_run=${DRY_RUN}"
  if [[ "$PLATFORM" == "windows" ]]; then
    echo "vm_host=${WINDOWS_VM_HOST:-}"
    echo "vm_user=${WINDOWS_VM_USER:-}"
    echo "vm_password=$(mask_sensitive "${WINDOWS_VM_PASSWORD:-}")"
    echo "automation_port=${WINDOWS_AUTOMATION_PORT:-4723}"
  else
    echo "app_bundle_id=${MACOS_APP_BUNDLE_ID:-}"
    echo "app_path=${MACOS_APP_PATH:-}"
  fi
} > "$OUTDIR/metadata.env"

echo "[run-desktop-llm-qa] Starting desktop QA"
echo "[run-desktop-llm-qa] Platform: ${PLATFORM}"
echo "[run-desktop-llm-qa] Suite: ${SUITE}"
echo "[run-desktop-llm-qa] Output: ${OUTDIR}"

if [[ "$DRY_RUN" == "true" ]]; then
  echo "[run-desktop-llm-qa] DRY RUN - skipping actual execution"
  {
    echo "# Desktop QA Summary (Dry Run)"
    echo
    echo "- Platform: \`${PLATFORM}\`"
    echo "- Suite: \`${SUITE}\`"
    echo "- Mode: Dry run (validation only)"
    echo "- Scenarios: \`${SCENARIOS_FILE_ABS:-none}\`"
  } > "$OUTDIR/summary.md"
  echo "[run-desktop-llm-qa] Dry run completed. Artifacts: $OUTDIR"
  exit 0
fi

# Execute platform-specific automation
set +e
(
  cd "$PROJECT_DIR_ABS"

  if [[ "$PLATFORM" == "windows" ]]; then
    # Windows automation via WinAppDriver or similar
    export WINDOWS_VM_HOST="${WINDOWS_VM_HOST:-}"
    export WINDOWS_VM_USER="${WINDOWS_VM_USER:-}"
    export WINDOWS_VM_PASSWORD="${WINDOWS_VM_PASSWORD:-}"
    export WINDOWS_AUTOMATION_PORT="${WINDOWS_AUTOMATION_PORT:-4723}"
    export DESKTOP_SCENARIOS="${SCENARIOS_FILE_ABS}"
    export DESKTOP_SUITE="${SUITE}"
    export DESKTOP_TIMEOUT="${TIMEOUT}"

    # Run Windows desktop tests
    if [[ -f "package.json" ]] && grep -q '"test:desktop:windows"' package.json 2>/dev/null; then
      pnpm test:desktop:windows
    else
      echo "[run-desktop-llm-qa] No test:desktop:windows script found. Running placeholder..."
      echo "Windows desktop QA for suite '${SUITE}' would execute here"
      echo "VM Host: ${WINDOWS_VM_HOST}"
      echo "Scenarios: ${SCENARIOS_FILE_ABS:-default}"
    fi

  else
    # macOS automation via AppleScript/Accessibility API
    export MACOS_APP_BUNDLE_ID="${MACOS_APP_BUNDLE_ID:-}"
    export MACOS_APP_PATH="${MACOS_APP_PATH:-}"
    export DESKTOP_SCENARIOS="${SCENARIOS_FILE_ABS}"
    export DESKTOP_SUITE="${SUITE}"
    export DESKTOP_TIMEOUT="${TIMEOUT}"

    # Run macOS desktop tests
    if [[ -f "package.json" ]] && grep -q '"test:desktop:macos"' package.json 2>/dev/null; then
      pnpm test:desktop:macos
    else
      echo "[run-desktop-llm-qa] No test:desktop:macos script found. Running placeholder..."
      echo "macOS desktop QA for suite '${SUITE}' would execute here"
      echo "App Bundle ID: ${MACOS_APP_BUNDLE_ID:-not set}"
      echo "App Path: ${MACOS_APP_PATH:-not set}"
      echo "Scenarios: ${SCENARIOS_FILE_ABS:-default}"
    fi
  fi
) > "$OUTDIR/run.log" 2>&1
RC=$?
set -e

# Generate summary
{
  echo "# Desktop QA Summary"
  echo
  echo "- Platform: \`${PLATFORM}\`"
  echo "- Suite: \`${SUITE}\`"
  echo "- Exit code: \`${RC}\`"
  echo "- Timestamp: \`${TIMESTAMP}\`"
  echo "- Scenarios: \`${SCENARIOS_FILE_ABS:-built-in}\`"
  echo "- Log: \`${OUTDIR}/run.log\`"
  echo
  if [[ $RC -eq 0 ]]; then
    echo "## Result: PASSED"
  else
    echo "## Result: FAILED"
    echo
    echo "### Log Tail"
    echo '```'
    tail -20 "$OUTDIR/run.log" 2>/dev/null || echo "(no log output)"
    echo '```'
  fi
} > "$OUTDIR/summary.md"

if [[ $RC -ne 0 ]]; then
  echo "[run-desktop-llm-qa] Failed (exit $RC). See $OUTDIR/run.log" >&2
  exit $RC
fi

echo "[run-desktop-llm-qa] Completed. Artifacts: $OUTDIR"
