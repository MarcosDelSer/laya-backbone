# Alert on Failure Mechanism

## Overview

The LAYA AI Service includes a comprehensive alert-on-failure mechanism that automatically detects health issues and sends notifications through multiple channels. This system integrates with all health check endpoints to provide real-time alerting when critical system events occur.

## Features

### Multi-Channel Alerting

The alert system supports three delivery channels:

1. **Email Alerts** - SMTP-based email notifications with HTML formatting
2. **Webhook Alerts** - HTTP POST to custom webhook URLs with JSON payload
3. **Slack Alerts** - Native Slack integration via incoming webhooks

### Severity Levels

Alerts are categorized by severity:

- **INFO** - Informational messages (e.g., test alerts, system events)
- **WARNING** - Non-critical issues that require attention (degraded state)
- **ERROR** - Significant issues affecting functionality
- **CRITICAL** - Severe issues requiring immediate attention (unhealthy state)

### Automatic Health Monitoring

The system automatically triggers alerts when:

- **Overall Health Degrades** - Service becomes degraded or unhealthy
- **Connection Pools Exhausted** - Database or Redis pool utilization >80% (warning) or >95% (critical)
- **Notification Queues Backed Up** - Email/push/SMS queues exceed thresholds

## Configuration

### Environment Variables

Configure the alert system using these environment variables:

```bash
# Enable/disable alerting
ALERTS_ENABLED=true

# Comma-separated list of enabled channels: email, webhook, slack
ALERT_CHANNELS=email,slack

# Minimum severity level to trigger alerts: info, warning, error, critical
ALERT_MIN_SEVERITY=warning

# Email Configuration
ALERT_SMTP_HOST=smtp.gmail.com
ALERT_SMTP_PORT=587
ALERT_SMTP_USER=your-email@gmail.com
ALERT_SMTP_PASSWORD=your-app-password
ALERT_EMAIL_FROM=alerts@laya.local
ALERT_EMAIL_TO=admin@example.com,ops@example.com

# Webhook Configuration
ALERT_WEBHOOK_URL=https://example.com/webhook

# Slack Configuration
ALERT_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

### Email Setup (Gmail Example)

For Gmail with 2-factor authentication:

1. Generate an [App Password](https://myaccount.google.com/apppasswords)
2. Set environment variables:
   ```bash
   ALERT_SMTP_HOST=smtp.gmail.com
   ALERT_SMTP_PORT=587
   ALERT_SMTP_USER=your-email@gmail.com
   ALERT_SMTP_PASSWORD=your-app-password
   ```

### Slack Setup

1. Create a Slack App at https://api.slack.com/apps
2. Enable Incoming Webhooks
3. Add webhook to your workspace
4. Copy the Webhook URL and set:
   ```bash
   ALERT_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
   ```

### Generic Webhook Setup

For integration with custom systems, PagerDuty, Datadog, etc.:

```bash
ALERT_WEBHOOK_URL=https://your-monitoring-system.com/webhook
```

The webhook receives a JSON payload:

```json
{
  "title": "AI Service Health DEGRADED",
  "message": "Service health is degraded. Failed checks: database_pool: degraded",
  "severity": "warning",
  "timestamp": "2024-02-15T10:30:00Z",
  "service": "laya-ai-service",
  "context": {
    "overall_status": "degraded",
    "failed_checks": ["database_pool: degraded"],
    "checks": { ... }
  }
}
```

## API Endpoints

### View Alert Configuration and History

```bash
GET /api/v1/health/alerts
```

Returns alert configuration status and recent alert history:

```json
{
  "timestamp": "2024-02-15T10:30:00Z",
  "config": {
    "enabled": true,
    "channels": ["email", "slack"],
    "min_severity": "warning",
    "email_configured": true,
    "webhook_configured": false,
    "slack_configured": true
  },
  "recent_alerts": [
    {
      "title": "Database Connection Pool CRITICAL",
      "message": "database connection pool is critical with 96% utilization",
      "severity": "critical",
      "timestamp": "2024-02-15T10:25:00Z",
      "service": "laya-ai-service",
      "context": {
        "pool_name": "database",
        "pool_status": "critical",
        "pool_data": { ... }
      }
    }
  ]
}
```

### Send Test Alert

```bash
POST /api/v1/health/alerts/test
```

Sends a test alert through all configured channels to verify configuration:

```json
{
  "sent": true,
  "timestamp": "2024-02-15T10:30:00Z",
  "channels": {
    "email": {
      "success": true,
      "recipients": ["admin@example.com"]
    },
    "slack": {
      "success": true,
      "status_code": 200
    }
  }
}
```

## Alert Triggers

### Health Check Alerts

The comprehensive health endpoint (`/api/v1/health`) automatically triggers alerts when:

- **Unhealthy**: Critical failures in database, disk, or connection pools
  - Severity: **CRITICAL**
  - Example: "AI Service Health UNHEALTHY"

- **Degraded**: Non-critical issues or resource warnings
  - Severity: **WARNING**
  - Example: "AI Service Health DEGRADED"

### Connection Pool Alerts

The connection pool monitoring automatically alerts when:

- **Degraded** (80-95% utilization): Sends WARNING alert
- **Critical** (>95% utilization): Sends CRITICAL alert

Example alert:
```
Title: Database Connection Pool CRITICAL
Message: database connection pool is critical with 96% utilization
Severity: critical
Context:
  - pool_name: database
  - utilization_percent: 96
  - checked_out: 24/25
```

### Notification Queue Alerts

Queue monitoring automatically alerts when queues exceed thresholds:

**Email Queue**:
- Warning: >1000 messages
- Critical: >5000 messages

**Push Queue**:
- Warning: >500 messages
- Critical: >2000 messages

**SMS Queue**:
- Warning: >100 messages
- Critical: >500 messages

Example alert:
```
Title: Notification Queues CRITICAL
Message: Notification queues are critical with 6200 total messages.
         Problem queues: email: 5500 messages
Severity: critical
```

## Usage Examples

### Basic Configuration

Enable email alerts only:

```bash
export ALERTS_ENABLED=true
export ALERT_CHANNELS=email
export ALERT_EMAIL_TO=ops@example.com
export ALERT_SMTP_HOST=localhost
export ALERT_MIN_SEVERITY=warning
```

### Production Configuration

Full multi-channel setup:

```bash
# Enable alerting
export ALERTS_ENABLED=true
export ALERT_CHANNELS=email,slack,webhook

# Only alert on errors and critical issues
export ALERT_MIN_SEVERITY=error

# Email alerts
export ALERT_EMAIL_TO=ops@example.com,oncall@example.com
export ALERT_SMTP_HOST=smtp.gmail.com
export ALERT_SMTP_PORT=587
export ALERT_SMTP_USER=alerts@example.com
export ALERT_SMTP_PASSWORD=your-app-password

# Slack alerts
export ALERT_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/XXX/YYY/ZZZ

# PagerDuty integration via webhook
export ALERT_WEBHOOK_URL=https://events.pagerduty.com/v2/enqueue
```

### Testing Configuration

Send a test alert:

```bash
curl -X POST http://localhost:8000/api/v1/health/alerts/test
```

Expected response:
```json
{
  "sent": true,
  "timestamp": "2024-02-15T10:30:00Z",
  "channels": {
    "email": {"success": true, "recipients": ["ops@example.com"]},
    "slack": {"success": true, "status_code": 200}
  }
}
```

### Viewing Alert History

```bash
curl http://localhost:8000/api/v1/health/alerts
```

## Integration Examples

### PagerDuty

Configure webhook to send alerts to PagerDuty:

```bash
export ALERT_WEBHOOK_URL=https://events.pagerduty.com/v2/enqueue
```

Transform the payload in your PagerDuty integration to match Events API v2 format.

### Datadog

Send alerts to Datadog via webhook:

```bash
export ALERT_WEBHOOK_URL=https://http-intake.logs.datadoghq.com/v1/input/YOUR_API_KEY
```

### Prometheus Alertmanager

Forward alerts to Alertmanager:

```bash
export ALERT_WEBHOOK_URL=http://alertmanager:9093/api/v1/alerts
```

### Discord

Use a Discord webhook for team notifications:

```bash
export ALERT_WEBHOOK_URL=https://discord.com/api/webhooks/YOUR/WEBHOOK/URL
```

## Alert Email Template

Alerts sent via email include:

**Plain Text Version**:
```
LAYA AI Service Alert

Severity: CRITICAL
Title: Database Connection Pool CRITICAL
Time: 2024-02-15T10:30:00Z
Service: laya-ai-service

Message:
database connection pool is critical with 96% utilization

Context:
{
  "pool_name": "database",
  "pool_status": "critical",
  "pool_data": { ... }
}
```

**HTML Version**:
- Color-coded severity badge (red/orange/blue)
- Formatted context data
- Professional layout with LAYA branding

## Alert Slack Message

Slack alerts include:

- Color-coded attachment based on severity
- Alert title and message
- Metadata fields (severity, service, time)
- Context data formatted as Slack fields
- LAYA AI Service footer

## Troubleshooting

### Alerts Not Sending

1. **Check if alerting is enabled**:
   ```bash
   curl http://localhost:8000/api/v1/health/alerts | jq '.config.enabled'
   ```

2. **Verify channels are configured**:
   ```bash
   curl http://localhost:8000/api/v1/health/alerts | jq '.config'
   ```

3. **Check logs for errors**:
   ```bash
   docker-compose logs ai-service | grep -i alert
   ```

4. **Send test alert**:
   ```bash
   curl -X POST http://localhost:8000/api/v1/health/alerts/test
   ```

### Email Alerts Failing

1. **Verify SMTP credentials**:
   - Check `ALERT_SMTP_USER` and `ALERT_SMTP_PASSWORD`
   - For Gmail, ensure you're using an App Password, not your account password

2. **Check SMTP connectivity**:
   ```bash
   telnet smtp.gmail.com 587
   ```

3. **Review email logs**:
   Look for "Failed to send email alert" in service logs

### Slack Alerts Failing

1. **Verify webhook URL**:
   - Ensure `ALERT_SLACK_WEBHOOK_URL` is correct
   - Test the webhook manually:
     ```bash
     curl -X POST -H 'Content-type: application/json' \
       --data '{"text":"Test message"}' \
       YOUR_WEBHOOK_URL
     ```

2. **Check for 404 errors**:
   - Webhook URL might be invalid or deleted
   - Recreate webhook in Slack app settings

### Webhook Alerts Failing

1. **Check URL accessibility**:
   ```bash
   curl -v YOUR_WEBHOOK_URL
   ```

2. **Verify webhook accepts JSON**:
   - Most webhooks expect `Content-Type: application/json`
   - Check webhook service documentation for payload format

3. **Check for firewall issues**:
   - Ensure outbound HTTPS (port 443) is allowed
   - Verify no proxy configuration is interfering

## Best Practices

### Production Deployment

1. **Use Multiple Channels** - Configure at least two channels for redundancy
2. **Set Appropriate Severity** - Use `ALERT_MIN_SEVERITY=warning` or `error` for production
3. **Test Before Deploying** - Always test with `/health/alerts/test` endpoint
4. **Monitor Alert History** - Regularly review `/health/alerts` for alert patterns
5. **Secure Credentials** - Store SMTP passwords and webhook URLs securely (e.g., secrets manager)

### Alert Fatigue Prevention

1. **Tune Severity Levels** - Set `ALERT_MIN_SEVERITY` appropriately
2. **Adjust Thresholds** - Fine-tune pool and queue thresholds based on your workload
3. **Group Related Alerts** - Single health check failure triggers one alert, not multiple
4. **Review Alert History** - Look for recurring issues that should be fixed

### Integration with Monitoring

The alert system complements:

- **Uptime Monitoring** (041-3-2) - External service monitoring
- **Docker Healthchecks** (041-3-1) - Container-level health
- **Prometheus/Grafana** - Metrics and dashboards
- **Datadog/New Relic** - APM and infrastructure monitoring

Use alerts for **immediate notification** of critical issues, while monitoring tools provide detailed diagnostics and historical analysis.

## Security Considerations

1. **Protect Webhook URLs** - Treat webhook URLs as sensitive credentials
2. **Secure SMTP Credentials** - Never commit passwords to version control
3. **Validate Inputs** - Alert content is sanitized before sending
4. **Rate Limiting** - Consider implementing rate limits for production use
5. **Access Control** - Restrict access to `/health/alerts/test` endpoint in production

## Future Enhancements

Potential improvements to the alert system:

- **Alert Rules Engine** - Define custom alert rules and thresholds
- **Alert Routing** - Route different alerts to different channels
- **Alert Aggregation** - Group multiple alerts into digest messages
- **Alert Suppression** - Temporarily suppress alerts during maintenance
- **Alert Escalation** - Escalate unacknowledged alerts after timeout
- **SMS Alerts** - Direct SMS notifications via Twilio/AWS SNS
- **Alert Dashboard** - Web UI for managing alerts and viewing history

## Related Documentation

- [Health Check Endpoints](./CONNECTION_POOL_MONITORING.md) - Connection pool monitoring
- [Queue Monitoring](./NOTIFICATION_QUEUE_MONITORING.md) - Notification queue monitoring
- [Uptime Monitoring Script](../scripts/README.md) - External uptime monitoring
- [Docker Healthchecks](../DOCKER-HEALTHCHECKS.md) - Container health configuration

## Support

For issues or questions about the alert system:

1. Check logs: `docker-compose logs ai-service`
2. Review configuration: `GET /api/v1/health/alerts`
3. Test alerts: `POST /api/v1/health/alerts/test`
4. Verify health: `GET /api/v1/health`
