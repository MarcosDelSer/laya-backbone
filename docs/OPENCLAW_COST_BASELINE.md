# OpenClaw Cloud QA Cost Baseline

## Date

Baseline prepared for planning as of February 16, 2026.

## Fixed Monthly Costs (Starter)

| Component | Typical Cost |
|---|---:|
| OpenClaw software (self-hosted) | $0 |
| Gateway cloud VM | ~$10-$30 |
| Langfuse Core | $29 |
| Browserbase Developer OR Browserless Prototyping | $20-$25 |
| Mobile cloud (starter plan) | $149-$199 |

## Reference Fixed Totals

1. Lean web-only: **~$59-$84/month** + model/API spend
2. Web + mobile: **~$208-$258/month** + model/API spend

## Variable Cost Drivers

1. Model token/API consumption
2. Browser session minutes/hours beyond included quota
3. Mobile device minutes/parallel session overages
4. CI minutes + artifact storage

## Model Auth Choice

1. Prototype mode: Claude Max account token can work.
2. Production mode: Anthropic API key is recommended for stable automation and clearer usage accounting.

## Budget Guardrails

1. Set monthly soft/hard budget for model usage.
2. Enforce smoke-first test policy before full regression.
3. Restrict concurrent mobile sessions on starter plans.
4. Auto-disable full regression when burn-rate exceeds threshold.

## Snapshot Helper

Run:

```bash
bash .auto-claude/scripts/qa-cost-snapshot.sh
```

