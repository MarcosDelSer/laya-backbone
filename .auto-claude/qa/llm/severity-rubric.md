# LLM QA Severity Rubric and Triage Rules

This document defines the severity classification system for issues discovered during LLM-driven exploratory QA. It provides clear criteria, evidence requirements, and triage rules to ensure consistent prioritization across web, mobile, and desktop platforms.

---

## Severity Levels Overview

| Level | Name | Response Time | Description |
|-------|------|---------------|-------------|
| **P0** | Critical | Immediate (< 4h) | Complete blocker, data loss, security breach, or revenue-impacting failure |
| **P1** | High | Same day (< 24h) | Major functionality broken, significant user impact, no workaround |
| **P2** | Medium | Within sprint | Feature partially broken, workaround exists, moderate user impact |
| **P3** | Low | Backlog | Minor issues, cosmetic defects, edge cases with minimal impact |

---

## P0 — Critical

### Definition
Issues that completely block core functionality, cause data loss/corruption, expose security vulnerabilities, or directly impact revenue. The product cannot ship or remain live with P0 issues.

### Criteria (any one qualifies)
- [ ] **Security breach**: Authentication bypass, data exposure, injection vulnerability
- [ ] **Data loss or corruption**: User data deleted, corrupted, or inaccessible
- [ ] **Complete feature failure**: Core user journey entirely blocked (login, checkout, payment)
- [ ] **Financial impact**: Payment processing fails, incorrect charges, revenue loss
- [ ] **Legal/compliance violation**: GDPR, HIPAA, PCI-DSS, or regulatory breach
- [ ] **Production crash**: Application crashes on launch or within 30 seconds of normal use
- [ ] **Privacy violation**: PII leaked, unauthorized data access

### Risk Tags That Elevate to P0
When an issue involves these `risk_tags` from the scenario, consider P0 classification:
- `security` — Any security-related failure
- `data-loss` — Any data persistence failure
- `financial` — Payment or billing failures
- `privacy` — PII exposure or unauthorized access
- `compliance` — Regulatory requirement violations

### Required Evidence
All P0 issues **must** include:
- [ ] Screenshot of the failure state
- [ ] Console logs (browser/app) showing errors
- [ ] Network logs if API-related
- [ ] Exact reproduction steps
- [ ] Video clip if the issue involves timing or state transitions

### Examples
| Scenario | Issue | Why P0 |
|----------|-------|--------|
| User login flow | Password sent in URL query params | Security: credential exposure |
| Checkout flow | Payment button disabled after cart load | Revenue: cannot complete purchase |
| Data export | Export deletes source records | Data loss: destructive side effect |
| Profile update | Other user's data visible after save | Privacy: cross-user data leak |

### Triage Action
1. **Stop release/deployment immediately** if in CI/CD pipeline
2. **Page on-call engineer** if in production
3. **Create incident ticket** with `P0` and `incident` labels
4. **Assign to team lead** for immediate triage
5. **Block PR/merge** if discovered during review

---

## P1 — High

### Definition
Major functionality is broken affecting a significant portion of users. The feature does not work as designed and no reasonable workaround exists. Release should not proceed without resolution.

### Criteria (any one qualifies)
- [ ] **Core feature broken**: Primary user journey fails for a common user segment
- [ ] **No workaround**: User cannot accomplish their goal by any alternate path
- [ ] **Performance critical**: Page/screen load > 10 seconds, causing abandonment
- [ ] **Accessibility blocker**: Screen reader users cannot access essential content
- [ ] **Cross-platform inconsistency**: Feature works on iOS but completely fails on Android (or vice versa)
- [ ] **UX-critical failure**: User stuck in loop, can't navigate away, misleading error

### Risk Tags That Elevate to P1
When an issue involves these `risk_tags`, consider P1 classification:
- `ux-critical` — User experience completely broken
- `performance` — Performance degradation blocking usability

### Required Evidence
All P1 issues **must** include:
- [ ] Screenshot or video of the failure
- [ ] Console logs showing errors/warnings
- [ ] User persona and context (which user type is affected)
- [ ] Exact reproduction steps
- [ ] Browser/device/OS information

### Examples
| Scenario | Issue | Why P1 |
|----------|-------|--------|
| Search functionality | Search returns no results for valid queries | Core feature: search completely broken |
| Form submission | Submit button works but form data not saved | Major: action appears successful but fails |
| Mobile navigation | Hamburger menu doesn't open on Android | Platform: feature broken on major platform |
| Dashboard load | Dashboard takes 45 seconds to load | Performance: unusable wait time |

### Triage Action
1. **Block release** if issue affects primary user flows
2. **Assign to owning team** within 2 hours
3. **Add to current sprint** for immediate fix
4. **Notify QA lead and product owner**
5. **Consider hotfix** if already in production

---

## P2 — Medium

### Definition
Feature is partially broken or works incorrectly in specific conditions. A workaround exists that allows users to accomplish their goal, but the experience is degraded.

### Criteria (any one qualifies)
- [ ] **Partial failure**: Feature works but with incorrect output or missing elements
- [ ] **Workaround available**: User can accomplish goal via alternate path
- [ ] **Edge case failure**: Issue only occurs under specific, less common conditions
- [ ] **Visual/layout regression**: Misaligned elements, incorrect spacing, overlapping text
- [ ] **Moderate performance**: 3-10 second delays noticeable but not blocking
- [ ] **Inconsistent behavior**: Works 70% of the time, intermittent failures

### Required Evidence
All P2 issues **should** include:
- [ ] Screenshot showing the issue
- [ ] Reproduction steps
- [ ] Expected vs. actual behavior description
- [ ] Workaround documented (if known)

### Examples
| Scenario | Issue | Why P2 |
|----------|-------|--------|
| User profile | Avatar upload works but crops incorrectly | Partial: feature works, result suboptimal |
| Filter results | "Sort by date" sorts ascending instead of descending | Incorrect: works but wrong behavior |
| Form validation | Email validation too strict, rejects valid emails with + | Edge case: valid but uncommon format rejected |
| Responsive layout | Cards overlap on tablet width (768-1024px) | Visual: specific viewport affected |

### Triage Action
1. **Add to sprint backlog** for upcoming sprint
2. **Document workaround** in issue description
3. **Assign severity** may be upgraded if user reports increase
4. **Tag for design review** if visual issue

---

## P3 — Low

### Definition
Minor issues that don't meaningfully impact user experience or functionality. Cosmetic defects, typos, or behaviors that deviate slightly from specification but don't cause user confusion.

### Criteria (any one qualifies)
- [ ] **Cosmetic defect**: Minor visual imperfection (1px alignment, subtle color)
- [ ] **Typo or copy issue**: Spelling error, minor grammar issue
- [ ] **Polish opportunity**: "Nice to have" improvement, not a bug
- [ ] **Rare edge case**: Issue only reproducible with unusual input or configuration
- [ ] **Minor inconsistency**: Behavior differs slightly from spec but users unaffected

### Required Evidence
P3 issues **should** include:
- [ ] Screenshot (if visual)
- [ ] Brief description of issue vs. expected

### Examples
| Scenario | Issue | Why P3 |
|----------|-------|--------|
| Footer | Copyright year shows 2023 instead of 2024 | Cosmetic: minor text issue |
| Button | Button border radius 4px instead of 8px | Visual: minor design spec deviation |
| Tooltip | Tooltip appears 300ms slower than other tooltips | Polish: noticeable only if looking for it |
| Error message | Error says "occured" instead of "occurred" | Typo: doesn't affect understanding |

### Triage Action
1. **Add to backlog** with `low-priority` label
2. **Consider for polish sprint** or tech debt cleanup
3. **May be closed as "won't fix"** if cost exceeds value
4. **Bundle with related work** when touching same file/component

---

## Triage Decision Tree

Use this decision tree when assigning severity to a new issue:

```
START
  │
  ├─► Is there a security, data-loss, financial, privacy, or compliance impact?
  │     └─► YES ──────────────────────────────────────────────────► P0
  │     └─► NO ───► Continue
  │
  ├─► Does the issue completely block a core user journey with no workaround?
  │     └─► YES ──────────────────────────────────────────────────► P1
  │     └─► NO ───► Continue
  │
  ├─► Is a significant user segment affected with degraded experience?
  │     └─► YES ──────────────────────────────────────────────────► P1
  │     └─► NO ───► Continue
  │
  ├─► Does the issue affect functionality but have a workaround?
  │     └─► YES ──────────────────────────────────────────────────► P2
  │     └─► NO ───► Continue
  │
  ├─► Is it a visual, layout, or edge-case issue with minor impact?
  │     └─► YES ──────────────────────────────────────────────────► P2
  │     └─► NO ───► Continue
  │
  └─► Cosmetic, typo, or polish opportunity?
        └─► YES ──────────────────────────────────────────────────► P3
        └─► NO ───► Re-evaluate criteria above
```

---

## Risk Tag to Severity Mapping

When a scenario has specific `risk_tags`, issues found during that scenario should be evaluated with these default severity floors:

| Risk Tag | Default Floor | Rationale |
|----------|---------------|-----------|
| `security` | P0 | Any security issue is critical by default |
| `data-loss` | P0 | Data integrity failures are always critical |
| `financial` | P0 | Revenue and payment issues are critical |
| `privacy` | P0 | PII and privacy issues are critical |
| `compliance` | P0 | Regulatory failures are critical |
| `ux-critical` | P1 | UX-critical issues significantly impact users |
| `performance` | P1/P2 | Depends on severity of degradation |

**Note:** The floor is the minimum severity. Issues can always be elevated based on impact.

---

## Evidence Requirements Summary

| Severity | Screenshot | Console Logs | Network Logs | Repro Steps | Video | DOM Snapshot |
|----------|------------|--------------|--------------|-------------|-------|--------------|
| **P0** | Required | Required | If API | Required | If timing | Recommended |
| **P1** | Required | Required | If API | Required | Helpful | Optional |
| **P2** | Required | Helpful | Optional | Required | Optional | Optional |
| **P3** | Helpful | Optional | — | Brief | — | — |

### Evidence Type Definitions

| Type | Purpose | When to Capture |
|------|---------|-----------------|
| `screenshot` | Visual state at point of failure | Always on assertion failure |
| `dom-snapshot` | Full DOM for debugging CSS/JS issues | Layout bugs, element visibility issues |
| `network-log` | API request/response for backend issues | API errors, timeouts, unexpected responses |
| `console-log` | JavaScript errors, warnings, debug output | Any client-side errors |
| `video-clip` | Timing, animation, or state transition issues | Flaky tests, race conditions |

---

## LLM QA Agent Guidelines

When the LLM QA agent discovers an issue during exploratory testing:

### 1. Capture Evidence First
Before moving on, collect all evidence specified in the scenario's `artifacts` configuration and the severity-appropriate evidence above.

### 2. Apply Risk Tag Context
Check the scenario's `risk_tags` and apply the default severity floor from the mapping table.

### 3. Use the Decision Tree
Walk through the triage decision tree to confirm or adjust severity.

### 4. Document Clearly
Structure the finding as:
```markdown
## Finding: [Brief Title]

**Severity:** P[0-3]
**Risk Tags:** [from scenario]
**Scenario:** [scenario_id]
**Step:** [step_id where failure occurred]

### Description
[What happened vs. what was expected]

### Reproduction Steps
1. [Step 1]
2. [Step 2]
3. [Step 3]

### Evidence
- Screenshot: [link]
- Console Logs: [link or inline]
- [Additional evidence as appropriate]

### Suggested Fix
[If obvious, suggest remediation]
```

### 5. Avoid Common Mis-classifications

| Common Mistake | Correction |
|----------------|------------|
| Elevating visual bugs to P1 | Unless it blocks functionality, visual bugs are P2-P3 |
| Downgrading intermittent security issues | Security issues are P0 even if hard to reproduce |
| Missing platform context | Always note which platform(s) are affected |
| Vague repro steps | Include exact data, timing, and user state |

---

## Severity Change Protocol

Severity can be adjusted after initial classification:

### Upgrade Triggers
- Customer reports increase (volume indicates broader impact)
- Root cause reveals larger blast radius
- Workaround discovered to be incomplete
- Production monitoring shows increased error rates

### Downgrade Triggers
- Workaround discovered that fully mitigates issue
- Scope limited to very small user segment
- Root cause less severe than initially assessed

### Change Documentation
Any severity change must be documented with:
- Original severity
- New severity
- Reason for change
- Who approved the change

---

## Integration with CI/CD

### Quality Gate Thresholds

| Gate | P0 | P1 | P2 | P3 |
|------|----|----|----|----|
| PR Merge | Block | Block | Warn | Pass |
| Staging Deploy | Block | Warn | Pass | Pass |
| Production Deploy | Block | Block | Pass | Pass |
| Release Sign-off | 0 allowed | 0 allowed | ≤3 open | No limit |

### Automated Severity Assignment
The LLM QA runner should attempt automatic severity assignment based on:
1. Assertion's `severity` field (if specified in scenario)
2. Scenario's `risk_tags` (apply floor from mapping)
3. Failure type pattern matching (crash → P0, timeout → P1, visual → P2)

Human review should validate P0 and P1 classifications before they block pipelines.

---

## Related Documents

| Document | Purpose |
|----------|---------|
| `scenario-schema.json` | Defines scenario structure including risk_tags and assertion severity |
| `LLM_QA_FOUNDATION.md` | End-to-end QA workflow and runner invocation |
| `ORCHESTRATOR.md` | Task orchestration and recovery procedures |

---

## Changelog

| Date | Change | Author |
|------|--------|--------|
| 2026-02-16 | Initial rubric creation | Auto-Claude |
