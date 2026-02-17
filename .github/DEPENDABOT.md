# Dependabot Configuration Guide

## Overview

Dependabot is GitHub's automated dependency update tool that helps keep the LAYA project secure and up-to-date. It automatically:

- Scans for dependency vulnerabilities
- Creates pull requests for updates
- Provides security advisories
- Keeps GitHub Actions up-to-date

## How It Works

### 1. Automated Scanning

Dependabot runs on a weekly schedule (configured per ecosystem):

| Ecosystem | Day | Time | Services |
|-----------|-----|------|----------|
| GitHub Actions | Monday | 9:00 AM | All workflows |
| Python (pip) | Monday | 10:00 AM | ai-service |
| npm | Tuesday | 10:00 AM - 1:00 PM | parent-portal, parent-app, teacher-app, desktop-app |
| PHP (Composer) | Wednesday | 10:00 AM - 11:00 AM | gibbon, modules |
| Docker | Thursday | 10:00 AM - 12:00 PM | All Dockerfiles |

### 2. Pull Request Creation

When Dependabot detects an update, it:

1. Creates a pull request with:
   - Dependency name and version change
   - Release notes and changelog links
   - Compatibility score
   - Security advisory (if applicable)

2. Triggers CI workflows automatically:
   - Service-specific tests (ai-service, parent-portal, gibbon-modules)
   - Docker build verification
   - PR checks workflow

3. Assigns reviewers and labels:
   - Team: `laya-devops-team`, `laya-backend-team`, `laya-frontend-team`
   - Labels: `dependencies`, `security`, ecosystem-specific, service-specific

### 3. Update Strategy

#### Grouping (Reduces PR Noise)

Minor and patch updates are grouped together:
- ✅ `axios 1.4.0 → 1.5.2` (grouped)
- ✅ `pytest 7.3.1 → 7.3.2` (grouped)
- ❌ `react 17.0.0 → 18.0.0` (individual PR for major update)

#### Security Updates

Security vulnerabilities **always** create individual PRs with high priority:
- Label: `security`
- Auto-generated from GitHub Advisory Database
- Includes CVE details and severity

## Configuration

### Enable Dependabot

The configuration is already in `.github/dependabot.yml`. To enable:

1. **Enable Dependabot Security Updates:**
   ```bash
   # Via GitHub CLI
   gh api -X PATCH /repos/{owner}/{repo} \
     -f security_and_analysis='{"dependabot_security_updates": {"status": "enabled"}}'
   ```

   Or in GitHub UI:
   - Settings → Security → Code security and analysis
   - Enable "Dependabot security updates"

2. **Enable Dependabot Alerts:**
   ```bash
   # Via GitHub CLI
   gh api -X PATCH /repos/{owner}/{repo} \
     -f security_and_analysis='{"dependabot_alerts": {"status": "enabled"}}'
   ```

   Or in GitHub UI:
   - Settings → Security → Code security and analysis
   - Enable "Dependabot alerts"

3. **Verify Configuration:**
   ```bash
   # Check Dependabot status
   gh api /repos/{owner}/{repo}/automated-security-fixes

   # View Dependabot alerts
   gh api /repos/{owner}/{repo}/dependabot/alerts
   ```

### GitHub Teams Setup

Configure teams in repository settings for automatic PR reviews:

```yaml
# Required teams (configure in Settings → Collaborators and teams)
- laya-devops-team     # For GitHub Actions and Docker updates
- laya-backend-team    # For Python and PHP updates
- laya-frontend-team   # For npm updates
```

If teams don't exist, create them:
```bash
# Create teams via GitHub CLI
gh api orgs/{org}/teams -f name="laya-devops-team" -f privacy="closed"
gh api orgs/{org}/teams -f name="laya-backend-team" -f privacy="closed"
gh api orgs/{org}/teams -f name="laya-frontend-team" -f privacy="closed"
```

Or update `.github/dependabot.yml` to use individual reviewers:
```yaml
reviewers:
  - "username1"
  - "username2"
```

## Managing Dependabot PRs

### Review Process

1. **Check the PR:**
   - Review the changelog and release notes
   - Check CI status (all checks must pass)
   - Review the diff for breaking changes

2. **Test Locally (for major updates):**
   ```bash
   # Fetch the Dependabot branch
   gh pr checkout <PR-number>

   # Run tests locally
   cd ai-service && pytest tests/
   cd parent-portal && npm test
   cd gibbon && composer test

   # Test Docker build
   docker-compose build
   ```

3. **Approve and Merge:**
   ```bash
   # Approve via CLI
   gh pr review <PR-number> --approve

   # Merge via CLI
   gh pr merge <PR-number> --squash
   ```

### Auto-Merge for Low-Risk Updates

Enable auto-merge for minor/patch updates after CI passes:

```bash
# Enable auto-merge for a specific PR
gh pr merge <PR-number> --auto --squash

# Or configure in PR settings
gh pr merge <PR-number> --auto --merge
```

**Recommended auto-merge criteria:**
- ✅ Minor and patch updates
- ✅ CI tests pass
- ✅ No breaking changes in changelog
- ❌ Major version updates (review manually)
- ❌ Security updates (review carefully)

### Ignore Specific Dependencies

If a dependency update is problematic, you can ignore it:

1. **Add to `.github/dependabot.yml`:**
   ```yaml
   ignore:
     - dependency-name: "package-name"
       update-types: ["version-update:semver-major"]
     - dependency-name: "another-package"
       versions: ["1.x", "2.x"]
   ```

2. **Or comment on the PR:**
   ```
   @dependabot ignore this major version
   @dependabot ignore this minor version
   @dependabot ignore this dependency
   ```

## Security Alerts

### Viewing Alerts

```bash
# List all Dependabot alerts
gh api /repos/{owner}/{repo}/dependabot/alerts

# View specific alert
gh api /repos/{owner}/{repo}/dependabot/alerts/{alert-number}
```

Or in GitHub UI:
- Security → Dependabot alerts

### Alert Severity Levels

| Severity | Action | Timeline |
|----------|--------|----------|
| **Critical** | Immediate fix required | < 24 hours |
| **High** | Fix as soon as possible | < 1 week |
| **Medium** | Schedule fix | < 2 weeks |
| **Low** | Include in next update cycle | Next sprint |

### Responding to Alerts

1. **Review the advisory:**
   - Check CVE details
   - Understand the vulnerability
   - Identify affected services

2. **Update immediately:**
   ```bash
   # Dependabot creates a PR automatically
   # Review and merge quickly for critical vulnerabilities
   gh pr list --label security
   gh pr merge <PR-number> --squash
   ```

3. **If no fix available:**
   - Document the issue
   - Implement workarounds
   - Monitor for updates
   - Consider alternative packages

## Integration with CI/CD

### Automatic Workflow Triggers

All Dependabot PRs trigger CI workflows:

```yaml
# Example: PR checks run automatically
on:
  pull_request:
    branches: [main, develop]

# Dependabot PRs are treated like regular PRs
```

### Required Checks

Before merging Dependabot PRs, these checks must pass:

| Service | Required Checks |
|---------|----------------|
| **ai-service** | Ruff lint, mypy type check, pytest 80%+ coverage |
| **parent-portal** | ESLint, Vitest 80%+ coverage, Next.js build |
| **gibbon-modules** | PHP CodeSniffer, PHPUnit 80%+ coverage |
| **All** | Docker build verification, PR checks |

### Branch Protection

Configure branch protection to enforce checks:

```bash
# Require Dependabot PRs to pass all checks
gh api -X PUT /repos/{owner}/{repo}/branches/main/protection \
  -f required_status_checks='{"strict":true,"contexts":["AI Service CI","Parent Portal CI","Gibbon Modules CI","PR Checks"]}'
```

See `.github/BRANCH_PROTECTION.md` for detailed configuration.

## Best Practices

### 1. Regular Review Cadence

- **Daily:** Check for security alerts
- **Weekly:** Review and merge grouped minor/patch updates
- **Monthly:** Review major version updates

### 2. Testing Strategy

- **Automated:** CI must pass before merge
- **Manual (for major updates):**
  - Test locally
  - Review breaking changes
  - Update code if needed
  - Test in staging environment

### 3. Communication

- **Notify team** of major dependency updates
- **Document** breaking changes in commit messages
- **Update** service documentation if APIs change

### 4. Dependency Hygiene

- Keep dependencies up-to-date regularly
- Remove unused dependencies
- Audit dependencies periodically:
  ```bash
  # Python
  pip-audit
  safety check

  # npm
  npm audit
  npm outdated

  # PHP
  composer audit
  ```

### 5. Security First

- **Prioritize** security updates over feature updates
- **Test thoroughly** before deploying security patches
- **Monitor** security advisories actively
- **Subscribe** to GitHub Security Advisories for dependencies

## Troubleshooting

### Dependabot PRs Not Created

**Check Dependabot status:**
```bash
gh api /repos/{owner}/{repo}/automated-security-fixes
```

**Common issues:**
- Dependabot not enabled in settings
- Configuration syntax error in `dependabot.yml`
- No new updates available
- Open PR limit reached

**Solutions:**
1. Validate `dependabot.yml`:
   ```bash
   # No official validator, but check YAML syntax
   yamllint .github/dependabot.yml
   ```

2. Check logs in Settings → Security → Dependabot

3. Merge existing PRs to free up slots

### CI Failures on Dependabot PRs

**Investigate failures:**
```bash
gh pr view <PR-number> --json statusCheckRollup
```

**Common causes:**
- Breaking API changes
- Incompatible dependency versions
- Test failures due to behavior changes

**Solutions:**
1. Review the changelog for breaking changes
2. Update code to handle new API
3. Adjust tests if behavior changed legitimately
4. If incompatible, ignore the update (see above)

### Merge Conflicts

Dependabot rebases automatically, but sometimes conflicts occur:

```bash
# Comment on the PR to trigger rebase
@dependabot rebase

# Or resolve manually
gh pr checkout <PR-number>
git rebase main
git push --force-with-lease
```

## Monitoring and Reporting

### Dependabot Metrics

Track Dependabot effectiveness:

```bash
# Count open Dependabot PRs
gh pr list --author app/dependabot --json number --jq 'length'

# List security alerts
gh api /repos/{owner}/{repo}/dependabot/alerts --jq '.[].security_advisory.severity' | sort | uniq -c

# Check update frequency
gh pr list --author app/dependabot --state merged --json mergedAt --jq '.[].mergedAt'
```

### Dashboard

Monitor in GitHub Security tab:
- Dependabot alerts
- Security advisories
- Dependency graph
- Code scanning results

## Additional Resources

- [GitHub Dependabot Documentation](https://docs.github.com/en/code-security/dependabot)
- [Dependabot Configuration Options](https://docs.github.com/en/code-security/dependabot/dependabot-version-updates/configuration-options-for-the-dependabot.yml-file)
- [GitHub Security Advisories](https://github.com/advisories)
- [LAYA Branch Protection Guide](.github/BRANCH_PROTECTION.md)
- [LAYA CI/CD Workflows](.github/workflows/)

## Support

For questions or issues:
1. Check GitHub Dependabot logs (Settings → Security → Dependabot)
2. Review this documentation
3. Contact DevOps team
4. Create an issue in the repository

---

**Last Updated:** 2026-02-17
**Maintained By:** LAYA DevOps Team
