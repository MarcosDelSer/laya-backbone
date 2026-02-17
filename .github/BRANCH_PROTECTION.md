# Branch Protection Rules Recommendation

This document outlines the recommended branch protection rules for the LAYA Backbone repository to ensure code quality and prevent accidental changes to critical branches.

## 🔒 Protection Rules for `main` Branch

### Required Status Checks
Configure the following status checks to be **required** before merging:

- ✅ `PR Ready for Review` (from pr-checks.yml workflow)
- ✅ `All CI Checks Must Pass` (from pr-checks.yml workflow)
- ✅ `Validate PR Metadata` (from pr-checks.yml workflow)
- ✅ `Check for Merge Conflicts` (from pr-checks.yml workflow)

The individual service workflows will run conditionally based on file changes:
- `AI Service CI` (when ai-service/ files change)
- `Parent Portal CI` (when parent-portal/ files change)
- `Gibbon Modules CI` (when gibbon/ or modules/ files change)
- `Docker Compose Build` (when docker-compose or Dockerfile changes)

### Settings

#### 1. Require Pull Request Reviews Before Merging
- **Enabled**: ✅ Yes
- **Required approving reviews**: 1 (minimum)
- **Dismiss stale pull request approvals**: ✅ Yes
- **Require review from Code Owners**: ⚠️ Optional (recommended if CODEOWNERS file exists)
- **Restrict who can dismiss reviews**: ⚠️ Optional (limit to team leads)

#### 2. Require Status Checks to Pass
- **Enabled**: ✅ Yes
- **Require branches to be up to date**: ✅ Yes
- **Status checks required**:
  - `PR Ready for Review`
  - `All CI Checks Must Pass`
  - `Validate PR Metadata`
  - `Check for Merge Conflicts`

#### 3. Require Conversation Resolution
- **Enabled**: ✅ Yes
- **Require conversation resolution before merging**: ✅ Yes

#### 4. Require Linear History
- **Enabled**: ⚠️ Optional
- **Require linear history**: Consider enabling to prevent merge commits
- **Alternative**: Use "Squash and merge" or "Rebase and merge" instead of "Create a merge commit"

#### 5. Require Deployments to Succeed
- **Enabled**: ⚠️ Optional
- **Required deployment environments**: Add `staging` when deployment workflows are active

#### 6. Lock Branch
- **Enabled**: ❌ No (allows authorized pushes)

#### 7. Do Not Allow Bypassing
- **Enabled**: ✅ Yes
- **Do not allow bypassing the above settings**: ✅ Yes
- **Exceptions**: Only repository admins in emergency situations

#### 8. Restrict Force Pushes
- **Enabled**: ✅ Yes
- **Specify who can force push**: ❌ Nobody (recommended)

#### 9. Allow Deletions
- **Enabled**: ❌ No
- **Allow users with push access to delete**: ❌ No

---

## 🔒 Protection Rules for `develop` Branch

Use similar rules as `main`, but with slightly relaxed requirements:

### Required Status Checks
Same as main branch:
- ✅ `PR Ready for Review`
- ✅ `All CI Checks Must Pass`
- ✅ `Validate PR Metadata`
- ✅ `Check for Merge Conflicts`

### Settings

#### 1. Require Pull Request Reviews Before Merging
- **Enabled**: ✅ Yes
- **Required approving reviews**: 1 (minimum)
- **Dismiss stale pull request approvals**: ⚠️ Optional

#### 2. Require Status Checks to Pass
- **Enabled**: ✅ Yes
- **Require branches to be up to date**: ✅ Yes

#### 3. Require Conversation Resolution
- **Enabled**: ✅ Yes

#### 4. Restrict Force Pushes
- **Enabled**: ⚠️ Optional
- **Specify who can force push**: ⚠️ Consider allowing team leads for rebasing

#### 5. Allow Deletions
- **Enabled**: ❌ No

---

## 🚀 How to Configure Branch Protection

### Via GitHub Web Interface

1. Navigate to **Settings** → **Branches**
2. Click **Add branch protection rule**
3. Enter branch name pattern: `main`
4. Configure the settings as outlined above
5. Click **Create** or **Save changes**
6. Repeat for `develop` branch

### Via GitHub CLI

```bash
# Protect main branch
gh api repos/:owner/:repo/branches/main/protection \
  --method PUT \
  --field required_status_checks='{"strict":true,"contexts":["PR Ready for Review","All CI Checks Must Pass","Validate PR Metadata","Check for Merge Conflicts"]}' \
  --field enforce_admins=true \
  --field required_pull_request_reviews='{"dismiss_stale_reviews":true,"require_code_owner_reviews":false,"required_approving_review_count":1}' \
  --field restrictions=null \
  --field required_conversation_resolution=true \
  --field allow_force_pushes=false \
  --field allow_deletions=false

# Protect develop branch (similar command with develop branch name)
```

### Via Terraform (Infrastructure as Code)

```hcl
resource "github_branch_protection" "main" {
  repository_id = github_repository.laya_backbone.node_id
  pattern       = "main"

  required_status_checks {
    strict   = true
    contexts = [
      "PR Ready for Review",
      "All CI Checks Must Pass",
      "Validate PR Metadata",
      "Check for Merge Conflicts"
    ]
  }

  required_pull_request_reviews {
    dismiss_stale_reviews      = true
    require_code_owner_reviews = false
    required_approving_review_count = 1
  }

  enforce_admins              = true
  require_conversation_resolution = true
  allows_force_pushes         = false
  allows_deletions            = false
}
```

---

## 📋 Verification Checklist

After configuring branch protection, verify:

- [ ] Cannot push directly to `main` branch
- [ ] Cannot push directly to `develop` branch
- [ ] Cannot merge PR without required reviews
- [ ] Cannot merge PR with failing status checks
- [ ] Cannot merge PR with unresolved conversations
- [ ] Cannot force push to protected branches
- [ ] Cannot delete protected branches
- [ ] PR checks workflow runs on all pull requests
- [ ] Status check results display correctly in PR

---

## 🔄 Workflow Integration

The PR checks workflow (`pr-checks.yml`) automatically:

1. **Monitors all required CI workflows** for the PR
2. **Validates PR metadata** (title, description)
3. **Checks for merge conflicts** with base branch
4. **Provides status comments** on the PR with detailed results
5. **Blocks merge** if any required check fails

### Required Workflows Tracked

- **AI Service CI**: Python linting (ruff), type checking (mypy), tests (pytest), 80% coverage
- **Parent Portal CI**: ESLint, Vitest with coverage, Next.js build verification
- **Gibbon Modules CI**: PHP CodeSniffer (PSR12), PHPUnit tests, 80% coverage
- **Docker Compose Build**: Service build verification, health checks

### Conditional Execution

Workflows run conditionally based on file changes:
- AI Service workflows run only when `ai-service/**` files change
- Parent Portal workflows run only when `parent-portal/**` files change
- Gibbon Modules workflows run only when `gibbon/**` or `modules/**` files change
- Docker workflows run when Docker-related files change

This ensures fast PR checks by running only relevant tests.

---

## 🛡️ Security Considerations

1. **Require signed commits** (optional): Consider requiring GPG-signed commits for additional security
2. **CODEOWNERS file**: Create `.github/CODEOWNERS` to automatically request reviews from specific teams
3. **Secret scanning**: Enable GitHub secret scanning alerts
4. **Dependency review**: Enable dependency review to catch vulnerable dependencies in PRs
5. **Dependabot**: Configure Dependabot for automatic security updates (see separate configuration)

---

## 📚 Additional Resources

- [GitHub Branch Protection Documentation](https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches)
- [GitHub Status Checks](https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/collaborating-on-repositories-with-code-quality-features/about-status-checks)
- [GitHub Actions Security Best Practices](https://docs.github.com/en/actions/security-guides/security-hardening-for-github-actions)

---

**Last Updated**: 2026-02-17
**Maintained By**: LAYA DevOps Team
