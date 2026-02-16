#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd -P)"

usage() {
  cat <<'USAGE'
Usage: run-cloud-web-qa.sh [--suite smoke|full] [--provider browserless|browserbase] [--project-dir DIR]

Runs Playwright web QA against a cloud browser provider and stores artifacts under:
  .auto-claude/qa/runs/web/<provider>/<suite>/<timestamp>/

Environment:
  BROWSERLESS_TOKEN     Required for provider=browserless
  BROWSERBASE_API_KEY   Required for provider=browserbase
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

SUITE="smoke"
PROVIDER="${WEB_QA_PROVIDER:-browserless}"
PROJECT_DIR="${PWD}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --suite)
      require_value "$1" "${2-}"
      SUITE="$2"
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

case "$PROVIDER" in
  browserless|browserbase) ;;
  *) die "Invalid provider: $PROVIDER" ;;
esac

PROJECT_DIR_ABS="$(resolve_dir_within_repo "$PROJECT_DIR")"

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
  die "WEB_QA_COMMAND is disabled for security. Use --suite smoke|full."
fi

if [[ "$SUITE" == "smoke" ]]; then
  CMD=(pnpm playwright test --grep @smoke)
else
  CMD=(pnpm playwright test)
fi

{
  echo "provider=${PROVIDER}"
  echo "suite=${SUITE}"
  echo "project_dir=${PROJECT_DIR_ABS}"
  echo "timestamp=${TIMESTAMP}"
  printf 'command=%q ' "${CMD[@]}"
  echo
} > "$OUTDIR/metadata.env"

echo "[run-cloud-web-qa] Running: ${CMD[*]}"
set +e
(
  cd "$PROJECT_DIR_ABS"
  "${CMD[@]}"
) > "$OUTDIR/run.log" 2>&1
RC=$?
set -e

{
  echo "# Web QA Summary"
  echo
  echo "- Provider: \`${PROVIDER}\`"
  echo "- Suite: \`${SUITE}\`"
  echo "- Exit code: \`${RC}\`"
  echo "- Log: \`$OUTDIR/run.log\`"
} > "$OUTDIR/summary.md"

if [[ $RC -ne 0 ]]; then
  echo "[run-cloud-web-qa] Failed (exit $RC). See $OUTDIR/run.log" >&2
  exit $RC
fi

echo "[run-cloud-web-qa] Completed. Artifacts: $OUTDIR"
