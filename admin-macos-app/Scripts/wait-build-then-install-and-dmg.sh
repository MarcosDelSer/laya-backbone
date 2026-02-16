#!/bin/bash
#
# Poll every 10 minutes for a successful Release build; when ready, install
# to /Applications and create LAYAAdmin-Installer.dmg.
# Runs until the build is ready (then installs and exits) or you stop it (Ctrl+C / kill).
#
# Usage: ./Scripts/wait-build-then-install-and-dmg.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
DERIVED_DATA="$HOME/Library/Developer/Xcode/DerivedData/LAYAAdmin-defgbgapamawiigqwxtpljjekuto"
APP_SRC="$DERIVED_DATA/Build/Products/Release/LAYAAdmin.app"
INTERVAL_SEC=600

is_build_ready() {
  [ -d "$APP_SRC" ] && [ "$(du -sk "$APP_SRC" | cut -f1)" -gt 100 ]
}

echo "Waiting for Release build to be ready..."
echo "  Checking: $APP_SRC"
echo "  Interval: $((INTERVAL_SEC/60)) minutes. Stop manually (Ctrl+C or kill) if needed."
echo ""

i=0
while true; do
  i=$((i + 1))
  if is_build_ready; then
    echo "Release build is ready (check $i)."
    exec "$SCRIPT_DIR/install-and-create-dmg.sh"
  fi
  echo "$(date '+%Y-%m-%d %H:%M:%S') Check $i: build not ready yet. Waiting ${INTERVAL_SEC}s..."
  sleep $INTERVAL_SEC
done
