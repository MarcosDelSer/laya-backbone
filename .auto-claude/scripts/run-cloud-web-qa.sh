#!/usr/bin/env bash
set -euo pipefail

usage() {
  cat <<'USAGE'
Usage: run-cloud-web-qa.sh [--suite smoke|full] [--provider browserless|browserbase] [--project-dir DIR]

Runs Playwright web QA against a cloud browser provider and stores artifacts under:
  .auto-claude/qa/runs/web/<provider>/<suite>/<timestamp>/

Environment:
  WEB_QA_COMMAND        Optional override command
  BROWSERLESS_TOKEN     Required for provider=browserless
  BROWSERBASE_API_KEY   Required for provider=browserbase
USAGE
}

SUITE="smoke"
PROVIDER="${WEB_QA_PROVIDER:-browserless}"
PROJECT_DIR="${PWD}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --suite) SUITE="$2"; shift 2 ;;
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

TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
OUTDIR=".auto-claude/qa/runs/web/${PROVIDER}/${SUITE}/${TIMESTAMP}"
mkdir -p "$OUTDIR"

if [[ "$PROVIDER" == "browserless" && -z "${BROWSERLESS_TOKEN:-}" ]]; then
  echo "BROWSERLESS_TOKEN is required for browserless provider" >&2
  exit 1
fi
if [[ "$PROVIDER" == "browserbase" && -z "${BROWSERBASE_API_KEY:-}" ]]; then
  echo "BROWSERBASE_API_KEY is required for browserbase provider" >&2
  exit 1
fi

if [[ -n "${WEB_QA_COMMAND:-}" ]]; then
  CMD="$WEB_QA_COMMAND"
elif [[ "$SUITE" == "smoke" ]]; then
  CMD="pnpm playwright test --grep @smoke"
else
  CMD="pnpm playwright test"
fi

{
  echo "provider=${PROVIDER}"
  echo "suite=${SUITE}"
  echo "project_dir=${PROJECT_DIR}"
  echo "timestamp=${TIMESTAMP}"
  echo "command=${CMD}"
} > "$OUTDIR/metadata.env"

echo "[run-cloud-web-qa] Running: $CMD"
set +e
(
  cd "$PROJECT_DIR"
  bash -lc "$CMD"
) > "$OUTDIR/run.log" 2>&1
RC=$?
set -e

cat > "$OUTDIR/summary.md" <<SUMMARY
# Web QA Summary

- Provider: `${PROVIDER}`
- Suite: `${SUITE}`
- Exit code: `${RC}`
- Log: `$OUTDIR/run.log`
SUMMARY

if [[ $RC -ne 0 ]]; then
  echo "[run-cloud-web-qa] Failed (exit $RC). See $OUTDIR/run.log" >&2
  exit $RC
fi

echo "[run-cloud-web-qa] Completed. Artifacts: $OUTDIR"
