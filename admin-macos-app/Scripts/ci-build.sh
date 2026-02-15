#!/bin/bash

# LAYA Admin macOS App - CI Build Script
# ======================================
#
# This script builds the LAYA Admin app for CI/CD environments without code signing.
# It's designed to verify the build compiles successfully.
#
# Usage:
#   ./Scripts/ci-build.sh
#

set -e

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
SCHEME="LAYAAdmin"

echo ""
echo "🔨 LAYA Admin - CI Build"
echo "========================"
echo ""

# Clean derived data
echo "ℹ️  Cleaning derived data..."
rm -rf ~/Library/Developer/Xcode/DerivedData/LAYAAdmin-*

# Build for testing (no signing required)
echo "ℹ️  Building for CI verification..."

xcodebuild \
    -project "${PROJECT_DIR}/LAYAAdmin.xcodeproj" \
    -scheme "$SCHEME" \
    -configuration Release \
    -destination "platform=macOS" \
    CODE_SIGN_IDENTITY="-" \
    CODE_SIGNING_REQUIRED=NO \
    CODE_SIGNING_ALLOWED=NO \
    build

echo ""
echo "✅ CI Build completed successfully!"
echo ""
