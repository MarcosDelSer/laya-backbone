# LAYA Deployment Guide

## Overview

This guide covers the automated deployment workflows for the LAYA daycare management system. Deployments are managed through GitHub Actions with different strategies for staging and production environments.

## Deployment Workflows

### Automatic Staging Deployments

**Trigger:** Automatic on merge to `main` branch

Staging deployments happen automatically whenever code is merged to the `main` branch. This provides continuous delivery to the staging environment for testing and validation.

**Workflow:**
1. Code is merged to `main` branch
2. Pre-deployment validation checks run
3. All services are tested (AI Service, Parent Portal, Gibbon)
4. Docker images are built and pushed to registry
5. Deployment to staging environment occurs automatically
6. Health checks verify all services are running
7. Smoke tests validate basic functionality
8. Team is notified of deployment status

**Staging URL:** `https://staging.laya.example.com`

### Manual Production Deployments

**Trigger:** Manual workflow dispatch only

Production deployments require explicit manual approval and are triggered through the GitHub Actions UI.

**Workflow:**
1. Navigate to Actions → Deploy to Environments
2. Click "Run workflow"
3. Select:
   - **Environment:** `production`
   - **Skip tests:** `false` (recommended)
   - **Force deploy:** Use only if necessary
4. Click "Run workflow"
5. GitHub environment protection rules require approval
6. After approval, deployment proceeds with same validation as staging
7. Production backup is created before deployment
8. Deployment executes with health checks
9. Git tag is created for the production release
10. Team is notified of deployment status

**Production URL:** `https://laya.example.com`

## Deployment Architecture

### Services Deployed

Each deployment includes the following services:

1. **AI Service** (`ai-service`)
   - FastAPI-based microservice
   - Python 3.9+
   - Handles AI-powered features and analysis

2. **Parent Portal** (`parent-portal`)
   - Next.js frontend application
   - React-based UI for parents
   - Server-side rendering

3. **Gibbon Integration** (`gibbon`)
   - PHP-based school management system
   - Custom modules for LAYA integration
   - Student/staff data synchronization

### Deployment Strategy

**Container Registry:** GitHub Container Registry (ghcr.io)

**Image Tagging:**
- `latest` - Latest production release
- `main-{sha}` - Main branch commits
- `v{date}-{sha}` - Versioned releases (e.g., `v2026.02.17-a1b2c3d`)

**Deployment Methods:**

The workflow supports multiple deployment targets (configure based on your infrastructure):

- **Kubernetes:** Update deployment images
- **Docker Compose:** SSH to server and update containers
- **AWS ECS:** Update service definitions
- **Other:** Customize deployment steps in workflow

## Environment Configuration

### Required Secrets

Configure these secrets in GitHub repository settings:

#### Deployment Credentials
- `DEPLOY_SSH_KEY` - SSH key for deployment servers (if using SSH)
- `KUBECONFIG` - Kubernetes config (if using K8s)
- `AWS_ACCESS_KEY_ID` - AWS credentials (if using ECS)
- `AWS_SECRET_ACCESS_KEY` - AWS secret (if using ECS)

#### Notification Services (Optional)
- `SLACK_WEBHOOK` - Slack webhook for deployment notifications
- `DISCORD_WEBHOOK` - Discord webhook for alerts

#### Application Secrets
Ensure these are configured in your deployment environment:
- `DATABASE_URL` - Database connection string
- `JWT_SECRET_KEY` - JWT signing key
- `OPENAI_API_KEY` - OpenAI API credentials
- `REDIS_URL` - Redis connection string
- Other service-specific secrets

### Environment Protection Rules

**Staging:**
- No required approvals
- Auto-deploys on main merge
- Can be deployed by any contributor

**Production:**
- Requires manual workflow trigger
- Requires approval from designated reviewers
- Deploy team members only

**To configure protection rules:**
1. Go to Settings → Environments
2. Click on `production` environment
3. Add required reviewers (recommended: 2+ senior developers)
4. Set deployment branch restrictions to `main` only
5. Enable required reviewers option

## Pre-Deployment Checks

Before any deployment, the workflow validates:

### Code Quality
- ✅ All CI tests pass (AI Service, Parent Portal, Gibbon)
- ✅ Code coverage meets 80% threshold
- ✅ Linting and type checking pass
- ✅ Build succeeds for all services

### Infrastructure
- ✅ Docker images build successfully
- ✅ Configuration files are valid
- ✅ Required secrets are available

### Change Detection
- ✅ Actual code changes detected (not just docs/CI)
- ✅ Version tag generated
- ✅ Previous deployment state recorded

## Deployment Process

### Build Phase

1. **Version Generation**
   ```
   v{YYYY.MM.DD}-{git-short-sha}
   Example: v2026.02.17-a1b2c3d
   ```

2. **Docker Image Building**
   - Multi-stage builds for optimization
   - Layer caching enabled for faster builds
   - Images tagged with version and branch

3. **Image Publishing**
   - Pushed to GitHub Container Registry
   - Tagged appropriately for environment
   - Metadata labels added

### Deployment Phase

1. **Service Update**
   - New images deployed to target environment
   - Rolling update strategy (zero downtime)
   - Service health monitored during rollout

2. **Health Verification**
   - Each service health endpoint checked
   - Maximum 30 attempts with 10-second intervals
   - Automatic rollback on failure

3. **Smoke Testing**
   - Critical user flows tested
   - API endpoints validated
   - Frontend rendering verified

### Post-Deployment

1. **Monitoring**
   - Service logs monitored for errors
   - Performance metrics tracked
   - User impact assessed

2. **Notifications**
   - Team notified via configured channels
   - Deployment summary generated
   - Release notes published

3. **Documentation**
   - Git tag created (production only)
   - Deployment recorded in history
   - Changelog updated

## Rollback Procedures

### Automatic Rollback

The workflow automatically rolls back if:
- Health checks fail after deployment
- Smoke tests fail
- Critical errors detected

### Manual Rollback

If you need to manually rollback a deployment:

#### Option 1: Redeploy Previous Version
```bash
# Trigger workflow with previous version tag
gh workflow run deploy.yml \
  -f environment=production \
  -f version=v2026.02.16-xyz123
```

#### Option 2: Kubernetes Rollback
```bash
kubectl rollout undo deployment/ai-service -n production
kubectl rollout undo deployment/parent-portal -n production
kubectl rollout undo deployment/gibbon -n production
```

#### Option 3: Docker Compose Rollback
```bash
# SSH to deployment server
ssh deploy@production.laya.example.com

# Pull previous version
docker-compose pull
docker-compose up -d --force-recreate
```

## Monitoring Deployments

### GitHub Actions UI

1. Navigate to Actions → Deploy to Environments
2. View running/completed workflows
3. Check individual job logs
4. Review deployment summaries

### Deployment Logs

Each deployment creates detailed logs:
- Pre-deployment validation results
- Build output for each service
- Deployment execution steps
- Health check results
- Smoke test outcomes

### Health Endpoints

Monitor service health at:
- **Staging:**
  - AI Service: `https://staging.laya.example.com/api/health`
  - Parent Portal: `https://staging.laya.example.com/health`
  - Gibbon: `https://staging.laya.example.com/gibbon/health`

- **Production:**
  - AI Service: `https://laya.example.com/api/health`
  - Parent Portal: `https://laya.example.com/health`
  - Gibbon: `https://laya.example.com/gibbon/health`

## Troubleshooting

### Deployment Fails with "No changes detected"

**Cause:** Only documentation or CI files were modified.

**Solution:**
- Use `force_deploy: true` if deployment is truly needed
- Or make actual code changes to trigger deployment

### Health Checks Timeout

**Cause:** Services taking too long to start or are unhealthy.

**Solutions:**
1. Check service logs for startup errors
2. Verify database connections are working
3. Ensure all required environment variables are set
4. Check resource limits (CPU, memory)

### Image Build Fails

**Cause:** Docker build errors or dependency issues.

**Solutions:**
1. Review build logs for specific errors
2. Test build locally: `docker build -t test ./ai-service`
3. Check for missing dependencies in requirements files
4. Verify base image availability

### Tests Fail in Pre-Deployment

**Cause:** Code quality issues or failing tests.

**Solutions:**
1. Run tests locally before merging
2. Review test failure logs
3. Fix failing tests before deployment
4. Use `skip_tests: true` only for emergency deployments (not recommended)

### Production Approval Pending

**Cause:** Waiting for required reviewers to approve.

**Solutions:**
1. Notify assigned reviewers
2. Provide context in PR description
3. Ensure all pre-deployment checks passed
4. Follow up if approval is time-sensitive

## Best Practices

### Before Merging to Main

1. ✅ All tests pass locally
2. ✅ Code reviewed and approved
3. ✅ PR checks are green
4. ✅ No merge conflicts
5. ✅ Documentation updated

### Staging Deployments

1. ✅ Merge to main during business hours
2. ✅ Monitor deployment progress
3. ✅ Test critical features in staging
4. ✅ Verify no errors in logs
5. ✅ Share staging URL with QA team

### Production Deployments

1. ✅ Test thoroughly in staging first
2. ✅ Schedule during low-traffic windows
3. ✅ Notify team of deployment window
4. ✅ Have rollback plan ready
5. ✅ Monitor metrics post-deployment
6. ✅ Keep team on standby for 1 hour
7. ✅ Document any issues encountered

### Emergency Deployments

For critical hotfixes:

1. Create hotfix branch from `main`
2. Make minimal, focused changes
3. Get expedited code review
4. Merge to `main` (triggers staging)
5. Verify fix in staging
6. Trigger production deployment
7. Use approval override if necessary
8. Monitor closely post-deployment

## Configuration Reference

### Workflow Inputs

When triggering manual deployments:

| Input | Type | Options | Default | Description |
|-------|------|---------|---------|-------------|
| `environment` | choice | staging, production | - | Target environment |
| `skip_tests` | boolean | true, false | false | Skip pre-deployment tests |
| `force_deploy` | boolean | true, false | false | Force deployment regardless of changes |

### Environment Variables

Set in `.github/workflows/deploy.yml`:

```yaml
env:
  DOCKER_BUILDKIT: 1                    # Enable BuildKit
  COMPOSE_DOCKER_CLI_BUILD: 1           # Use BuildKit for compose
  REGISTRY: ghcr.io                      # Container registry
  IMAGE_PREFIX: ${{ github.repository }} # Image name prefix
```

### Job Outputs

The workflow provides these outputs:

- `version` - Generated version tag
- `environment` - Target environment
- `deployment_id` - Unique deployment identifier
- `should_deploy` - Whether deployment is needed

## Security Considerations

### Image Security

1. **Base Images:** Use official, minimal base images
2. **Scanning:** Enable vulnerability scanning in registry
3. **Secrets:** Never include secrets in images
4. **Updates:** Regularly update dependencies

### Deployment Security

1. **Authentication:** Use secure authentication methods
2. **RBAC:** Implement role-based access control
3. **Encryption:** Use TLS/SSL for all communications
4. **Secrets:** Rotate secrets regularly
5. **Audit:** Log all deployment activities

### Network Security

1. **Firewall:** Configure appropriate firewall rules
2. **VPC:** Deploy in isolated network segments
3. **Load Balancer:** Use load balancer for SSL termination
4. **WAF:** Consider Web Application Firewall

## Support

### Getting Help

- **Deployment Issues:** Contact DevOps team
- **Application Errors:** Contact development team
- **Infrastructure:** Contact cloud operations team

### Useful Commands

```bash
# View deployment status
gh run list --workflow=deploy.yml

# View specific deployment logs
gh run view <run-id>

# Trigger manual deployment
gh workflow run deploy.yml -f environment=production

# List recent deployments
git tag | grep production
```

## Changelog

### Version History

Track deployment versions and changes:

- `v2026.02.17-*` - Initial deployment workflow
  - Auto staging deployment on main
  - Manual production deployment with approval
  - Health checks and smoke tests
  - Automatic rollback on failure

---

**Last Updated:** 2026-02-17
**Maintained By:** DevOps Team
**Review Cycle:** Monthly
