# Mobile Cloud Provider Matrix

This matrix defines how to run native mobile QA from OpenClaw orchestration.

## Providers

| Provider | Best For | iOS | Android | Notes |
|---|---|---|---|---|
| BrowserStack | Fastest setup + broad real-device matrix | Yes | Yes | Good default for mixed app teams |
| Sauce Labs | Enterprise test ops + device cloud | Yes | Yes | Strong reporting + enterprise support |
| AWS Device Farm | AWS-native teams | Yes | Yes | Good fit if infra/security is already in AWS |

## Required Secrets

| Provider | Required Environment Variables |
|---|---|
| BrowserStack | `BROWSERSTACK_USERNAME`, `BROWSERSTACK_ACCESS_KEY` |
| Sauce Labs | `SAUCE_USERNAME`, `SAUCE_ACCESS_KEY` |
| AWS Device Farm | `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_REGION`, `DEVICE_FARM_PROJECT_ARN` |

## Command Patterns

Security note:
- Do not embed credentials directly in command strings or commit them in files.
- Provide credentials via environment variables from your secret manager/CI vault.
- Keep `APPIUM_SERVER_URL` host-only when possible and let the test harness attach auth from provider-specific env vars.

### BrowserStack (Appium)

```bash
export MOBILE_PROVIDER=browserstack
export BROWSERSTACK_USERNAME="***"
export BROWSERSTACK_ACCESS_KEY="***"
export APPIUM_SERVER_URL="https://hub-cloud.browserstack.com/wd/hub"
export MOBILE_CAPS_FILE=".auto-claude/qa/mobile/caps.browserstack.json"
bash .auto-claude/scripts/run-cloud-mobile-qa.sh --suite smoke --platform android
```

### Sauce Labs (Appium)

```bash
export MOBILE_PROVIDER=sauce
export SAUCE_USERNAME="***"
export SAUCE_ACCESS_KEY="***"
export APPIUM_SERVER_URL="https://ondemand.us-west-1.saucelabs.com/wd/hub"
export MOBILE_CAPS_FILE=".auto-claude/qa/mobile/caps.sauce.json"
bash .auto-claude/scripts/run-cloud-mobile-qa.sh --suite smoke --platform ios
```

### AWS Device Farm (Appium endpoint)

```bash
export MOBILE_PROVIDER=devicefarm
export APPIUM_SERVER_URL="${DEVICE_FARM_APPIUM_ENDPOINT}"
export MOBILE_CAPS_FILE=".auto-claude/qa/mobile/caps.devicefarm.json"
bash .auto-claude/scripts/run-cloud-mobile-qa.sh --suite smoke --platform android
```

## Artifacts and Outputs

Expected outputs per run:
- JUnit XML (`results.xml`)
- Appium logs (`appium.log`)
- Screenshots/videos (provider-side links + local references)
- Run summary markdown (`summary.md`)

Store all outputs in:
- `.auto-claude/qa/runs/mobile/<provider>/<platform>/<timestamp>/`

## Known Limitations

- OpenClaw iOS app distribution remains limited; use provider-hosted iOS device labs for production CI.
- Mobile cloud providers may throttle parallel sessions on starter plans.
- Provider capability schemas differ; keep one caps file per provider.
