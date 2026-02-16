# Note: Admin macOS App — Release Build Failure (2026-02-15)

## Summary

The LAYA Admin macOS app Release build **failed** due to Swift compilation errors. The wait-build worker ran 12 checks over ~2 hours and never saw a successful build; it did **not** run the install or DMG script. The Release app bundle in DerivedData is 0B (empty); any existing `/Applications/LAYAAdmin.app` or `LAYAAdmin-Installer.dmg` is from an earlier attempt, not from this build.

## What Was Run

- **Release build:** `admin-macos-app/Scripts/ci-build.sh` (background, log: `admin-macos-app/release-build.log`).
- **Worker:** `admin-macos-app/Scripts/wait-build-then-install-and-dmg.sh` — polls every 10 minutes for `~/Library/Developer/Xcode/DerivedData/LAYAAdmin-.../Build/Products/Release/LAYAAdmin.app` to exist and be &gt; 100 KB, then runs `install-and-create-dmg.sh`.

## Build Outcome

- **Result:** `BUILD FAILED`
- **Release app path:** `~/Library/Developer/Xcode/DerivedData/LAYAAdmin-defgbgapamawiigqwxtpljjekuto/Build/Products/Release/LAYAAdmin.app`
- **Artifact:** 0B; `Contents/MacOS/` empty (no main executable).

## Worker Outcome

- **Log:** `admin-macos-app/build-wait.log`
- **Checks:** 12 (from 10:43 to 12:33 local time).
- **Install/DMG:** Never executed (build never reported ready).
- **Exit:** Script stopped after check 12 (syntax error in log from old script variant, or max-checks limit).

## Compilation Errors (from `release-build.log`)

- **InvoiceListViewModel.swift:** Optional chaining on non-optional `[InvoiceItemRequest]`; `InvoiceRequest` missing `familyName`, `childName`, `status`.
- **AnalyticsViewModel.swift:** `APIError.serviceUnavailable` missing; main-actor isolation issue with `stopAutoRefresh()`.
- **SettingsViewModel.swift:** `RealmManager.clearAllCachedData`, `SyncService.syncAll` missing.
- **Views (ChildRowView, ChildDetailView, StaffRowView, StaffDetailView, ScheduleView, InvoiceRowView, InvoiceDetailView):** `Color?.tertiary` / `ShapeStyle` vs `Color?` type mismatch.
- **MenuBarView.swift:** Switch not exhaustive.

## Next Steps

1. Fix the Swift errors (e.g. via spec 019: `.auto-claude/specs/019-fix-macos-admin-build/`), then re-run Release build.
2. After a **successful** build, either:
   - Run install and DMG manually: `./Scripts/install-and-create-dmg.sh`, or
   - Restart the worker so it picks up the new build and runs install/DMG automatically.

## References

- Spec: `.auto-claude/specs/019-fix-macos-admin-build/spec.md`
- Build log: `admin-macos-app/release-build.log`
- Wait log: `admin-macos-app/build-wait.log`
