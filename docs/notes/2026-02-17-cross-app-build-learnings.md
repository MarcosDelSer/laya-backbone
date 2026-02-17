# Cross-App Build Learnings (2026-02-17)

## Scope

This note captures build and verification results after wiring clients to the live Hetzner backend.

- Backend base: `https://ai.46-225-139-110.sslip.io`
- Gibbon path base: `https://ai.46-225-139-110.sslip.io/gibbon`
- Related commit: `44a7168`

## What Was Verified

## 1) Backend connectivity checks

- `https://ai.46-225-139-110.sslip.io/` responded.
- `https://ai.46-225-139-110.sslip.io/gibbon/health` responded `200`.
- Caddy/path routing now supports both AI service and Gibbon over one TLS host.

## 2) Admin macOS CI build

Command run:

```bash
cd admin-macos-app
./Scripts/ci-build.sh
```

Result:

- Build succeeded: `** BUILD SUCCEEDED **`.
- Warnings remain (mostly actor-isolation / Swift 6 future errors), but no build-blocking errors.

Learning:

- Current admin app still compiles successfully with the new production endpoint configuration.
- Swift concurrency warnings should be scheduled separately as technical debt before strict Swift 6 enforcement.

## 3) Teacher app typecheck

Command run:

```bash
cd teacher-app
npm run typecheck
```

Result:

- Typecheck passed after two fixes:
  - `Platform.Version` normalization in `useCameraPermission.ts`
  - notification `type` narrowing in `pushNotifications.ts`

Learning:

- RN platform types and FCM payload unions need explicit narrowing to keep TS strict checks stable.

## 4) Parent app typecheck

Commands run:

```bash
cd parent-app
npm install
npm run typecheck
```

Result:

- Typecheck fails with a large existing baseline of errors unrelated to endpoint-base changes.
- Failures are concentrated in:
  - API config/endpoint shape mismatches (`authApi`, `messagesApi`, `dailyReportsApi`)
  - missing/changed exported types (`types` module)
  - UI prop and navigation typing drift
  - service typing issues (`pushNotifications`, `shareService`)

Learning:

- `parent-app` needs a dedicated type-contract alignment pass before it can be used as a stable CI gate.
- Installing dependencies was necessary to expose the real baseline (previously `tsc` missing).

## Config Changes Introduced

- `admin-macos-app/LAYAAdmin/Resources/Config.xcconfig`
- `teacher-app/src/api/config.ts`
- `parent-app/src/api/config.ts`
- `parent-portal/lib/api.ts` (path-preserving URL build logic for `/gibbon` base)
- `docker/caddy/Caddyfile` (single-host path routing)

## Practical Next Step

1. Create a focused `parent-app` repair task for type-contract alignment.
2. Keep teacher/admin as current green checks while parent-app is remediated.
3. After parent-app typecheck is green, run simulator/device smoke tests against live backend.
