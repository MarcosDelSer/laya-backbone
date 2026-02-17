#!/bin/bash

# LAYA Admin macOS App - Build, Sign, and Notarize Script
# ========================================================
#
# This script builds, signs, and notarizes the LAYA Admin macOS application
# for distribution outside the Mac App Store (Developer ID distribution).
#
# Prerequisites:
# - Xcode Command Line Tools installed
# - Apple Developer account with Developer ID certificate
# - App-specific password stored in Keychain (see NOTARIZATION SETUP below)
# - Team ID configured in DEVELOPMENT_TEAM below
#
# NOTARIZATION SETUP:
# 1. Generate an app-specific password at https://appleid.apple.com/account/manage
# 2. Store it in Keychain:
#    xcrun notarytool store-credentials "AC_PASSWORD" \
#        --apple-id "your-apple-id@example.com" \
#        --team-id "YOUR_TEAM_ID" \
#        --password "your-app-specific-password"
#
# Usage:
#   ./Scripts/build-and-notarize.sh [--skip-notarize] [--skip-archive]
#

set -e

# =============================================================================
# CONFIGURATION - Update these values for your environment
# =============================================================================

# Apple Developer Team ID (required)
# Find yours at: https://developer.apple.com/account/#/membership
DEVELOPMENT_TEAM="${DEVELOPMENT_TEAM:-}"

# Keychain profile name for notarization credentials
NOTARIZATION_PROFILE="${NOTARIZATION_PROFILE:-AC_PASSWORD}"

# Scheme and configuration
SCHEME="LAYAAdmin"
CONFIGURATION="Release"

# Output directories
PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
ARCHIVE_DIR="${PROJECT_DIR}/build/Archives"
EXPORT_DIR="${PROJECT_DIR}/build/Release"
DMG_DIR="${PROJECT_DIR}/build/DMG"

# Archive name with date
DATE_STAMP=$(date +%Y%m%d_%H%M%S)
ARCHIVE_NAME="${SCHEME}_${DATE_STAMP}"

# =============================================================================
# HELPER FUNCTIONS
# =============================================================================

log_info() {
    echo "ℹ️  $1"
}

log_success() {
    echo "✅ $1"
}

log_error() {
    echo "❌ $1" >&2
}

log_warning() {
    echo "⚠️  $1"
}

log_step() {
    echo ""
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "📦 $1"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
}

check_prerequisites() {
    log_step "Checking Prerequisites"

    # Check for Xcode
    if ! command -v xcodebuild &> /dev/null; then
        log_error "xcodebuild not found. Please install Xcode Command Line Tools."
        exit 1
    fi
    log_success "Xcode found: $(xcodebuild -version | head -1)"

    # Check for Team ID if not skipping archive
    if [[ "$SKIP_ARCHIVE" != "true" ]]; then
        if [[ -z "$DEVELOPMENT_TEAM" ]]; then
            log_warning "DEVELOPMENT_TEAM not set. Building without code signing."
            log_info "Set DEVELOPMENT_TEAM environment variable for signed builds."
        else
            log_success "Development Team: $DEVELOPMENT_TEAM"
        fi
    fi

    # Check for notarytool if notarizing
    if [[ "$SKIP_NOTARIZE" != "true" ]]; then
        if ! xcrun notarytool --version &> /dev/null; then
            log_error "notarytool not found. Requires Xcode 13+."
            exit 1
        fi
        log_success "notarytool found"
    fi
}

clean_build() {
    log_step "Cleaning Previous Build"

    rm -rf "${PROJECT_DIR}/build"
    mkdir -p "$ARCHIVE_DIR" "$EXPORT_DIR" "$DMG_DIR"

    log_success "Build directories cleaned"
}

build_archive() {
    log_step "Building Archive"

    local archive_path="${ARCHIVE_DIR}/${ARCHIVE_NAME}.xcarchive"

    # Build arguments
    local build_args=(
        -project "${PROJECT_DIR}/LAYAAdmin.xcodeproj"
        -scheme "$SCHEME"
        -configuration "$CONFIGURATION"
        -archivePath "$archive_path"
        archive
    )

    # Add team if set
    if [[ -n "$DEVELOPMENT_TEAM" ]]; then
        build_args+=(
            DEVELOPMENT_TEAM="$DEVELOPMENT_TEAM"
            CODE_SIGN_STYLE=Manual
            CODE_SIGN_IDENTITY="Developer ID Application"
        )
    else
        # Build without signing for CI/testing
        build_args+=(
            CODE_SIGN_IDENTITY="-"
            CODE_SIGNING_REQUIRED=NO
            CODE_SIGNING_ALLOWED=NO
        )
    fi

    log_info "Running: xcodebuild ${build_args[*]}"

    xcodebuild "${build_args[@]}"

    if [[ -d "$archive_path" ]]; then
        log_success "Archive created: $archive_path"
        echo "$archive_path" > "${PROJECT_DIR}/build/last_archive_path.txt"
    else
        log_error "Archive creation failed"
        exit 1
    fi
}

export_archive() {
    log_step "Exporting Archive"

    local archive_path
    archive_path=$(cat "${PROJECT_DIR}/build/last_archive_path.txt")

    if [[ ! -d "$archive_path" ]]; then
        log_error "Archive not found: $archive_path"
        exit 1
    fi

    # Use appropriate export options
    local export_options="${PROJECT_DIR}/ExportOptions.plist"

    # If no team ID, create temporary export options for unsigned export
    if [[ -z "$DEVELOPMENT_TEAM" ]]; then
        export_options="${PROJECT_DIR}/build/ExportOptions-unsigned.plist"
        cat > "$export_options" << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>method</key>
    <string>development</string>
    <key>signingStyle</key>
    <string>automatic</string>
</dict>
</plist>
EOF
        log_warning "Using unsigned export options (no DEVELOPMENT_TEAM set)"
    else
        # Update export options with team ID
        sed "s/TEAM_ID_HERE/$DEVELOPMENT_TEAM/g" "$export_options" > "${PROJECT_DIR}/build/ExportOptions-configured.plist"
        export_options="${PROJECT_DIR}/build/ExportOptions-configured.plist"
    fi

    xcodebuild -exportArchive \
        -archivePath "$archive_path" \
        -exportPath "$EXPORT_DIR" \
        -exportOptionsPlist "$export_options"

    local app_path="${EXPORT_DIR}/${SCHEME}.app"
    if [[ -d "$app_path" ]]; then
        log_success "Exported: $app_path"
    else
        log_error "Export failed"
        exit 1
    fi
}

notarize_app() {
    log_step "Notarizing Application"

    local app_path="${EXPORT_DIR}/${SCHEME}.app"
    local zip_path="${EXPORT_DIR}/${SCHEME}.zip"

    if [[ ! -d "$app_path" ]]; then
        log_error "App not found: $app_path"
        exit 1
    fi

    # Create ZIP for notarization
    log_info "Creating ZIP archive for notarization..."
    ditto -c -k --keepParent "$app_path" "$zip_path"

    # Submit for notarization
    log_info "Submitting to Apple notarization service..."
    log_info "This may take several minutes..."

    xcrun notarytool submit "$zip_path" \
        --keychain-profile "$NOTARIZATION_PROFILE" \
        --wait

    log_success "Notarization complete"

    # Staple the notarization ticket
    log_info "Stapling notarization ticket..."
    xcrun stapler staple "$app_path"

    log_success "Notarization ticket stapled"

    # Verify
    log_info "Verifying notarization..."
    spctl -a -t exec -vv "$app_path"

    # Clean up zip
    rm -f "$zip_path"

    log_success "App is notarized and ready for distribution"
}

create_dmg() {
    log_step "Creating DMG"

    local app_path="${EXPORT_DIR}/${SCHEME}.app"
    local dmg_path="${DMG_DIR}/${SCHEME}.dmg"
    local dmg_temp="${DMG_DIR}/${SCHEME}_temp.dmg"
    local volume_name="LAYA Admin"

    if [[ ! -d "$app_path" ]]; then
        log_error "App not found: $app_path"
        exit 1
    fi

    # Calculate size needed (app size + 20MB buffer)
    local app_size
    app_size=$(du -sm "$app_path" | cut -f1)
    local dmg_size=$((app_size + 20))

    # Create temporary DMG
    hdiutil create \
        -srcfolder "$app_path" \
        -volname "$volume_name" \
        -fs HFS+ \
        -fsargs "-c c=64,a=16,e=16" \
        -format UDRW \
        -size "${dmg_size}m" \
        "$dmg_temp"

    # Mount the temporary DMG
    local mount_output
    mount_output=$(hdiutil attach -readwrite -noverify -noautoopen "$dmg_temp")
    local mount_point
    mount_point=$(echo "$mount_output" | grep "Volumes" | cut -f3)

    # Create Applications symlink
    ln -s /Applications "${mount_point}/Applications"

    # Unmount
    hdiutil detach "$mount_point"

    # Convert to final compressed DMG
    hdiutil convert "$dmg_temp" \
        -format UDZO \
        -imagekey zlib-level=9 \
        -o "$dmg_path"

    # Clean up temp
    rm -f "$dmg_temp"

    # Sign the DMG if we have a team
    if [[ -n "$DEVELOPMENT_TEAM" ]]; then
        codesign --sign "Developer ID Application: LAYA ($DEVELOPMENT_TEAM)" "$dmg_path"
        log_success "DMG signed"
    fi

    # Notarize DMG if not skipped
    if [[ "$SKIP_NOTARIZE" != "true" && -n "$DEVELOPMENT_TEAM" ]]; then
        log_info "Notarizing DMG..."
        xcrun notarytool submit "$dmg_path" \
            --keychain-profile "$NOTARIZATION_PROFILE" \
            --wait
        xcrun stapler staple "$dmg_path"
        log_success "DMG notarized and stapled"
    fi

    log_success "DMG created: $dmg_path"
}

print_summary() {
    log_step "Build Summary"

    echo ""
    echo "🎉 Build completed successfully!"
    echo ""
    echo "Outputs:"
    echo "  Archive:  ${ARCHIVE_DIR}/${ARCHIVE_NAME}.xcarchive"
    echo "  App:      ${EXPORT_DIR}/${SCHEME}.app"

    if [[ -f "${DMG_DIR}/${SCHEME}.dmg" ]]; then
        echo "  DMG:      ${DMG_DIR}/${SCHEME}.dmg"
    fi

    echo ""

    if [[ -z "$DEVELOPMENT_TEAM" ]]; then
        log_warning "App was built WITHOUT code signing."
        echo ""
        echo "To build a signed and notarized version:"
        echo "  1. Set your Apple Developer Team ID:"
        echo "     export DEVELOPMENT_TEAM='YOUR_TEAM_ID'"
        echo ""
        echo "  2. Set up notarization credentials:"
        echo "     xcrun notarytool store-credentials 'AC_PASSWORD' \\"
        echo "         --apple-id 'your@email.com' \\"
        echo "         --team-id 'YOUR_TEAM_ID'"
        echo ""
        echo "  3. Re-run this script"
    else
        log_success "App is signed and ready for distribution!"
    fi
}

# =============================================================================
# MAIN
# =============================================================================

# Parse arguments
SKIP_NOTARIZE="false"
SKIP_ARCHIVE="false"
CREATE_DMG="false"

while [[ $# -gt 0 ]]; do
    case $1 in
        --skip-notarize)
            SKIP_NOTARIZE="true"
            shift
            ;;
        --skip-archive)
            SKIP_ARCHIVE="true"
            shift
            ;;
        --create-dmg)
            CREATE_DMG="true"
            shift
            ;;
        --help)
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --skip-notarize  Skip notarization step"
            echo "  --skip-archive   Skip archive step (use existing archive)"
            echo "  --create-dmg     Create DMG installer"
            echo "  --help           Show this help"
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            exit 1
            ;;
    esac
done

echo ""
echo "🍎 LAYA Admin - Build & Notarize"
echo "================================="
echo ""

check_prerequisites
clean_build

if [[ "$SKIP_ARCHIVE" != "true" ]]; then
    build_archive
fi

# Only export and notarize if we have a signed archive
if [[ -n "$DEVELOPMENT_TEAM" ]]; then
    export_archive

    if [[ "$SKIP_NOTARIZE" != "true" ]]; then
        notarize_app
    fi

    if [[ "$CREATE_DMG" == "true" ]]; then
        create_dmg
    fi
else
    log_warning "Skipping export and notarization (no DEVELOPMENT_TEAM set)"
fi

print_summary
