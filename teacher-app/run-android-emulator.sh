#!/usr/bin/env bash
# LAYA Teacher App - Run on Android Emulator
# Prerequisites: Emulator running, Metro optional (for dev bundle)

set -e
export ANDROID_HOME="${ANDROID_HOME:-$HOME/Library/Android/sdk}"
export PATH="$ANDROID_HOME/platform-tools:$PATH"

echo "Devices:"
adb devices

echo "Port forward for Metro (8081)..."
adb reverse tcp:8081 tcp:8081

APK_DIR="$(cd "$(dirname "$0")/android/app/build/outputs/apk/debug" && pwd)"
if [[ -f "$APK_DIR/app-arm64-v8a-debug.apk" ]]; then
  echo "Installing arm64 APK..."
  adb install -r "$APK_DIR/app-arm64-v8a-debug.apk"
else
  echo "Installing universal APK..."
  adb install -r "$APK_DIR/app-universal-debug.apk"
fi

echo "Launching app..."
adb shell am start -n com.laya.teacherapp/com.teacherapp.MainActivity

echo "Done. Start Metro in another terminal with: npm start"
