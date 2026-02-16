#!/bin/bash
#
# Install LAYA Admin to /Applications and create an installable DMG.
# Run this after a successful Release build (e.g. ./Scripts/ci-build.sh).
#
# Usage: ./Scripts/install-and-create-dmg.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
# Default DerivedData path (same as Xcode when not overridden)
DERIVED_DATA="$HOME/Library/Developer/Xcode/DerivedData/LAYAAdmin-defgbgapamawiigqwxtpljjekuto"
APP_SRC="$DERIVED_DATA/Build/Products/Release/LAYAAdmin.app"
DMG_OUT="$PROJECT_DIR/LAYAAdmin-Installer.dmg"

if [ ! -d "$APP_SRC" ]; then
  echo "Release app not found at: $APP_SRC"
  echo "Run a Release build first: ./Scripts/ci-build.sh"
  exit 1
fi

echo "Using app: $APP_SRC"
echo "Size: $(du -sh "$APP_SRC" | cut -f1)"
echo ""

# Install to /Applications
echo "Installing to /Applications/LAYAAdmin.app ..."
rm -rf /Applications/LAYAAdmin.app
cp -R "$APP_SRC" /Applications/
echo "Done."
echo ""

# Create DMG
echo "Creating installer DMG ..."
DMG_ROOT="$PROJECT_DIR/dmg-root"
rm -rf "$DMG_ROOT"
mkdir -p "$DMG_ROOT"
cp -R "$APP_SRC" "$DMG_ROOT/"
ln -s /Applications "$DMG_ROOT/Applications"
hdiutil create -volname "LAYA Admin" -srcfolder "$DMG_ROOT" -ov -format UDZO "$DMG_OUT"
rm -rf "$DMG_ROOT"
echo "DMG created: $DMG_OUT"
ls -la "$DMG_OUT"
echo ""
echo "You can open the app from /Applications or distribute $DMG_OUT"
