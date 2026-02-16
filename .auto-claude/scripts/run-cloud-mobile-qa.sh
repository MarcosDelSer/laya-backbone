#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd -P)"

usage() {
  cat <<'USAGE'
Usage: run-cloud-mobile-qa.sh [--suite smoke|full] [--platform ios|android] [--provider browserstack|sauce|devicefarm] [--project-dir DIR]

Runs mobile QA via Appium-compatible provider endpoint and stores artifacts under:
  .auto-claude/qa/runs/mobile/<provider>/<platform>/<timestamp>/

Environment:
  APPIUM_SERVER_URL   Required Appium endpoint
  MOBILE_CAPS_FILE    Optional capabilities file path
USAGE
}

die() {
  echo "$*" >&2
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
  [[ -f "$input" ]] || die "Capabilities file not found: $input"
  local abs
  abs="$(cd "$(dirname "$input")" && pwd -P)/$(basename "$input")"
  case "$abs" in
    "$REPO_ROOT"|"$REPO_ROOT"/*) printf '%s\n' "$abs" ;;
    *) die "Capabilities file must be inside repository: $abs" ;;
  esac
}

mask_url_credentials() {
  local url="$1"
  if [[ "$url" =~ ^([^:]+://)([^/@:]+):([^/@]+)@(.+)$ ]]; then
    printf '%s***:***@%s\n' "${BASH_REMATCH[1]}" "${BASH_REMATCH[4]}"
    return
  fi
  printf '%s\n' "$url"
}

SUITE="smoke"
PLATFORM="android"
PROVIDER="${MOBILE_PROVIDER:-browserstack}"
PROJECT_DIR="${PWD}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --suite)
      require_value "$1" "${2-}"
      SUITE="$2"
      shift 2
      ;;
    --platform)
      require_value "$1" "${2-}"
      PLATFORM="$2"
      shift 2
      ;;
    --provider)
      require_value "$1" "${2-}"
      PROVIDER="$2"
      shift 2
      ;;
    --project-dir)
      require_value "$1" "${2-}"
      PROJECT_DIR="$2"
      shift 2
      ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage; exit 1 ;;
  esac
done

case "$SUITE" in
  smoke|full) ;;
  *) die "Invalid suite: $SUITE" ;;
esac

case "$PLATFORM" in
  ios|android) ;;
  *) die "Invalid platform: $PLATFORM" ;;
esac

case "$PROVIDER" in
  browserstack|sauce|devicefarm) ;;
  *) die "Invalid provider: $PROVIDER" ;;
esac

if [[ -z "${APPIUM_SERVER_URL:-}" ]]; then
  die "APPIUM_SERVER_URL is required"
fi

if [[ -n "${MOBILE_QA_COMMAND:-}" ]]; then
  die "MOBILE_QA_COMMAND is disabled for security. Use --suite and --platform."
fi

PROJECT_DIR_ABS="$(resolve_dir_within_repo "$PROJECT_DIR")"
MOBILE_CAPS_FILE_ABS=""
if [[ -n "${MOBILE_CAPS_FILE:-}" ]]; then
  MOBILE_CAPS_FILE_ABS="$(resolve_file_within_repo "$MOBILE_CAPS_FILE")"
fi

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
OUTDIR=".auto-claude/qa/runs/mobile/${PROVIDER}/${PLATFORM}/${TIMESTAMP}"
mkdir -p "$OUTDIR"

TEST_TAG="@mobile and @${PLATFORM}"
if [[ "$SUITE" == "smoke" ]]; then
  TEST_TAG="${TEST_TAG} and @smoke"
fi
CMD=(pnpm test:e2e:mobile --grep "$TEST_TAG")
APPIUM_SERVER_MASKED="$(mask_url_credentials "$APPIUM_SERVER_URL")"

{
  echo "provider=${PROVIDER}"
  echo "suite=${SUITE}"
  echo "platform=${PLATFORM}"
  echo "project_dir=${PROJECT_DIR_ABS}"
  echo "timestamp=${TIMESTAMP}"
  echo "appium_server=${APPIUM_SERVER_MASKED}"
  echo "caps_file=${MOBILE_CAPS_FILE_ABS}"
  printf 'command=%q ' "${CMD[@]}"
  echo
} > "$OUTDIR/metadata.env"

echo "[run-cloud-mobile-qa] Running: ${CMD[*]}"
set +e
(
  cd "$PROJECT_DIR_ABS"
  if [[ -n "$MOBILE_CAPS_FILE_ABS" ]]; then
    APPIUM_SERVER_URL="$APPIUM_SERVER_URL" MOBILE_CAPS_FILE="$MOBILE_CAPS_FILE_ABS" "${CMD[@]}"
  else
    APPIUM_SERVER_URL="$APPIUM_SERVER_URL" "${CMD[@]}"
  fi
) > "$OUTDIR/run.log" 2>&1
RC=$?
set -e

{
  echo "# Mobile QA Summary"
  echo
  echo "- Provider: \`${PROVIDER}\`"
  echo "- Platform: \`${PLATFORM}\`"
  echo "- Suite: \`${SUITE}\`"
  echo "- Exit code: \`${RC}\`"
  echo "- Log: \`$OUTDIR/run.log\`"
} > "$OUTDIR/summary.md"

if [[ $RC -ne 0 ]]; then
  echo "[run-cloud-mobile-qa] Failed (exit $RC). See $OUTDIR/run.log" >&2
  exit $RC
fi

echo "[run-cloud-mobile-qa] Completed. Artifacts: $OUTDIR"
