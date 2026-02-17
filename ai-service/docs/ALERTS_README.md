# Alerts Quick Reference

## Quick Start

### Enable Alerts

```bash
export ALERTS_ENABLED=true
export ALERT_CHANNELS=email
export ALERT_EMAIL_TO=your-email@example.com
export ALERT_SMTP_HOST=localhost
```

### Test Configuration

```bash
curl -X POST http://localhost:8000/api/v1/health/alerts/test
```

## Environment Variables

- ALERTS_ENABLED: Enable/disable alerts (true/false)
- ALERT_CHANNELS: Comma-separated channels (email,slack,webhook)
- ALERT_MIN_SEVERITY: Minimum severity (info, warning, error, critical)
- ALERT_EMAIL_TO: Recipient emails (comma-separated)
- ALERT_SMTP_HOST: SMTP server hostname
- ALERT_SMTP_PORT: SMTP port (default: 587)
- ALERT_SMTP_USER: SMTP username
- ALERT_SMTP_PASSWORD: SMTP password
- ALERT_EMAIL_FROM: From address (default: alerts@laya.local)
- ALERT_WEBHOOK_URL: Generic webhook URL
- ALERT_SLACK_WEBHOOK_URL: Slack webhook URL

## Automatic Alert Triggers

Service unhealthy (CRITICAL): Database connection failed, critical errors
Service degraded (WARNING): Disk usage >90%, resource warnings
Pool >95% (CRITICAL): Database pool exhausted
Pool 80-95% (WARNING): Database pool high usage
Queue critical: Email queue >5000, Push >2000, SMS >500 messages
Queue degraded: Email queue >1000, Push >500, SMS >100 messages

## API Endpoints

GET /api/v1/health/alerts - View configuration and history
POST /api/v1/health/alerts/test - Send test alert

For detailed documentation, see ALERT_ON_FAILURE.md
