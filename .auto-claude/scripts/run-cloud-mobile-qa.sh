#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: run-cloud-mobile-qa.sh [--suite smoke|full] [--platform ios|android] [--provider browserstack|sauce|devicefarm] [--project-dir DIR]

Runs mobile QA via Appium-compatible provider endpoint and stores artifacts under:
  .auto-claude/qa/runs/mobile/<provider>/<platform>/<timestamp>/

Environment:
  APPIUM_SERVER_URL   Required Appium endpoint
  MOBILE_CAPS_FILE    Optional capabilities file path
  MOBILE_QA_COMMAND   Optional full command override
USAGE
}

SUITE="smoke"
PLATFORM="android"
PROVIDER="${MOBILE_PROVIDER:-browserstack}"
PROJECT_DIR="${PWD}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --suite) SUITE="$2"; shift 2 ;;
    --platform) PLATFORM="$2"; shift 2 ;;
    --provider) PROVIDER="$2"; shift 2 ;;
    --project-dir) PROJECT_DIR="$2"; shift 2 ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown argument: $1" >&2; usage; exit 1 ;;
  esac
done

case "$SUITE" in
  smoke|full) ;;
  *) echo "Invalid suite: $SUITE" >&2; exit 1 ;;
esac

case "$PLATFORM" in
  ios|android) ;;
  *) echo "Invalid platform: $PLATFORM" >&2; exit 1 ;;
esac

if [[ -z "${APPIUM_SERVER_URL:-}" ]]; then
  echo "APPIUM_SERVER_URL is required" >&2
  exit 1
fi

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
OUTDIR=".auto-claude/qa/runs/mobile/${PROVIDER}/${PLATFORM}/${TIMESTAMP}"
mkdir -p "$OUTDIR"

if [[ -n "${MOBILE_QA_COMMAND:-}" ]]; then
  CMD="$MOBILE_QA_COMMAND"
else
  TEST_TAG="@mobile and @${PLATFORM}"
  if [[ "$SUITE" == "smoke" ]]; then
    TEST_TAG="${TEST_TAG} and @smoke"
  fi
  if [[ -n "${MOBILE_CAPS_FILE:-}" ]]; then
    CMD="APPIUM_SERVER_URL='${APPIUM_SERVER_URL}' MOBILE_CAPS_FILE='${MOBILE_CAPS_FILE}' pnpm test:e2e:mobile --grep \"${TEST_TAG}\""
  else
    CMD="APPIUM_SERVER_URL='${APPIUM_SERVER_URL}' pnpm test:e2e:mobile --grep \"${TEST_TAG}\""
  fi
fi

{
  echo "provider=${PROVIDER}"
  echo "suite=${SUITE}"
  echo "platform=${PLATFORM}"
  echo "project_dir=${PROJECT_DIR}"
  echo "timestamp=${TIMESTAMP}"
  echo "appium_server=${APPIUM_SERVER_URL}"
  echo "caps_file=${MOBILE_CAPS_FILE:-}"
  echo "command=${CMD}"
} > "$OUTDIR/metadata.env"

echo "[run-cloud-mobile-qa] Running: $CMD"
set +e
(
  cd "$PROJECT_DIR"
  bash -lc "$CMD"
) > "$OUTDIR/run.log" 2>&1
RC=$?
set -e

cat > "$OUTDIR/summary.md" <<SUMMARY
# Mobile QA Summary

- Provider: `${PROVIDER}`
- Platform: `${PLATFORM}`
- Suite: `${SUITE}`
- Exit code: `${RC}`
- Log: `$OUTDIR/run.log`
SUMMARY

if [[ $RC -ne 0 ]]; then
  echo "[run-cloud-mobile-qa] Failed (exit $RC). See $OUTDIR/run.log" >&2
  exit $RC
fi

echo "[run-cloud-mobile-qa] Completed. Artifacts: $OUTDIR"
