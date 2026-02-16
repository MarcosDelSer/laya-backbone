#!/usr/bin/env bash
set -euo pipefail

cat <<'OUT'
OpenClaw Cloud QA Cost Snapshot (fixed baseline + variable meters)

Fixed monthly baseline (typical starter configuration)
- OpenClaw self-hosted software: $0
- OpenClaw gateway VM (small cloud VM): ~$10-$30
- Langfuse Core (observability): $29
- Browser cloud (choose one):
  - Browserbase Developer: $20
  - Browserless Prototyping (annual): $25
- Mobile cloud (choose one):
  - BrowserStack Automate (starter desktop/web): $59
  - BrowserStack Device Cloud (starter): $199
  - Sauce Virtual Cloud (starter annual): $149
  - Sauce Real Device Cloud (starter annual): $199

Reference fixed totals
- Lean web-only baseline: ~$59-$84 + model/API spend
  (VM + Langfuse + Browserbase/Browserless)
- Web + mobile baseline: ~$208-$258 + model/API spend
  (VM + Langfuse + browser cloud + Sauce/BrowserStack entry mobile plan)

Variable usage meters
- Model tokens / API usage (Anthropic API key usage)
- Browser session minutes/hours over included quota
- Mobile device minutes / parallel sessions over plan allowance
- CI minutes and artifact storage

Operational reminder
- Claude Max subscription can be useful for prototyping, but API keys are usually preferred for reliable 24/7 automation and clear usage accounting.
OUT
