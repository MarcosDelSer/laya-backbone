# LAYA Uptime Monitoring

Continuous uptime monitoring for all LAYA service health endpoints. Checks all services every 60 seconds and alerts on failures.

## Features

- **Continuous Monitoring**: Checks all LAYA services every 60 seconds
- **Multi-Service Support**: Monitors AI Service, Gibbon CMS, and Parent Portal
- **Failure Alerts**: Sends alerts via email and webhooks when services go down
- **Recovery Notifications**: Alerts when services recover
- **Status Reports**: Periodic status summaries with uptime statistics
- **Response Time Tracking**: Monitors average response times
- **Uptime Statistics**: Calculates uptime percentage for each service
- **Detailed Logging**: Console and file logging with timestamps

## Services Monitored

1. **AI Service** - `/api/v1/health/liveness`
   - Database connectivity (PostgreSQL)
   - Redis connection
   - Disk space
   - Memory usage

2. **Gibbon CMS** - `/modules/System/health.php`
   - MySQL connection
   - PHP extensions
   - Disk space
   - Upload directory permissions
   - Session configuration

3. **Parent Portal** - `/api/health`
   - Build version
   - AI Service connectivity
   - Gibbon CMS connectivity

## Installation

### Prerequisites

- Python 3.7 or higher
- pip (Python package manager)

### Install Dependencies

```bash
cd scripts
pip install -r requirements.txt
```

Or install directly:

```bash
pip install requests
```

## Usage

### Basic Usage

Run with default settings (checks every 60 seconds):

```bash
python scripts/uptime_monitoring.py
```

### With Custom Interval

Check every 30 seconds:

```bash
python scripts/uptime_monitoring.py --interval 30
```

### Dry Run Mode

Run a single check and exit (useful for testing):

```bash
python scripts/uptime_monitoring.py --dry-run
```

### With Email Alerts

```bash
python scripts/uptime_monitoring.py --email alerts@example.com
```

### With Webhook Alerts

```bash
python scripts/uptime_monitoring.py --webhook https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

### With Custom Log File

```bash
python scripts/uptime_monitoring.py --log-file /var/log/laya-uptime.log
```

## Environment Variables

Configure the monitoring script using environment variables:

| Variable | Description | Default |
|----------|-------------|---------|
| `AI_SERVICE_URL` | AI Service base URL | `http://localhost:8000` |
| `GIBBON_URL` | Gibbon CMS base URL | `http://localhost:80` |
| `PARENT_PORTAL_URL` | Parent Portal base URL | `http://localhost:3000` |
| `CHECK_INTERVAL` | Check interval in seconds | `60` |
| `ALERT_EMAIL` | Email address for alerts | (none) |
| `ALERT_WEBHOOK` | Webhook URL for alerts | (none) |
| `LOG_FILE` | Log file path | `uptime_monitoring.log` |
| `SMTP_SERVER` | SMTP server for email alerts | `localhost` |
| `SMTP_PORT` | SMTP port | `587` |
| `SMTP_USER` | SMTP username | (none) |
| `SMTP_PASSWORD` | SMTP password | (none) |
| `ALERT_FROM_EMAIL` | From email address | `monitoring@laya.local` |

### Example with Environment Variables

```bash
export AI_SERVICE_URL=https://ai.laya.example.com
export GIBBON_URL=https://lms.laya.example.com
export PARENT_PORTAL_URL=https://portal.laya.example.com
export CHECK_INTERVAL=60
export ALERT_EMAIL=alerts@example.com
export SMTP_SERVER=smtp.gmail.com
export SMTP_PORT=587
export SMTP_USER=monitoring@example.com
export SMTP_PASSWORD=your-password

python scripts/uptime_monitoring.py
```

## Running as a Service

### Using systemd (Linux)

Create a systemd service file at `/etc/systemd/system/laya-uptime-monitor.service`:

```ini
[Unit]
Description=LAYA Uptime Monitoring Service
After=network.target

[Service]
Type=simple
User=laya
WorkingDirectory=/opt/laya-backbone
Environment="AI_SERVICE_URL=http://localhost:8000"
Environment="GIBBON_URL=http://localhost:80"
Environment="PARENT_PORTAL_URL=http://localhost:3000"
Environment="ALERT_EMAIL=alerts@example.com"
Environment="LOG_FILE=/var/log/laya-uptime.log"
ExecStart=/usr/bin/python3 /opt/laya-backbone/scripts/uptime_monitoring.py
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Enable and start the service:

```bash
sudo systemctl enable laya-uptime-monitor
sudo systemctl start laya-uptime-monitor
sudo systemctl status laya-uptime-monitor
```

View logs:

```bash
sudo journalctl -u laya-uptime-monitor -f
```

### Using Docker

Create a `Dockerfile` for the monitoring script:

```dockerfile
FROM python:3.11-slim

WORKDIR /app

COPY scripts/requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY scripts/uptime_monitoring.py .

ENV AI_SERVICE_URL=http://ai-service:8000
ENV GIBBON_URL=http://gibbon:80
ENV PARENT_PORTAL_URL=http://parent-portal:3000
ENV CHECK_INTERVAL=60

CMD ["python", "uptime_monitoring.py"]
```

Add to `docker-compose.yml`:

```yaml
  uptime-monitor:
    build:
      context: .
      dockerfile: scripts/Dockerfile
    container_name: laya-uptime-monitor
    restart: unless-stopped
    environment:
      AI_SERVICE_URL: http://ai-service:8000
      GIBBON_URL: http://gibbon:80
      PARENT_PORTAL_URL: http://parent-portal:3000
      CHECK_INTERVAL: 60
      ALERT_EMAIL: ${ALERT_EMAIL}
      ALERT_WEBHOOK: ${ALERT_WEBHOOK}
    depends_on:
      ai-service:
        condition: service_healthy
      gibbon:
        condition: service_healthy
      parent-portal:
        condition: service_healthy
    networks:
      - laya-network
    volumes:
      - ./logs:/app/logs
```

### Using Supervisor

Create a supervisor config at `/etc/supervisor/conf.d/laya-uptime-monitor.conf`:

```ini
[program:laya-uptime-monitor]
command=/usr/bin/python3 /opt/laya-backbone/scripts/uptime_monitoring.py
directory=/opt/laya-backbone
user=laya
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/laya-uptime-monitor.log
environment=AI_SERVICE_URL="http://localhost:8000",GIBBON_URL="http://localhost:80",PARENT_PORTAL_URL="http://localhost:3000"
```

Start the supervisor job:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start laya-uptime-monitor
sudo supervisorctl status laya-uptime-monitor
```

## Alert Mechanisms

### Email Alerts

Configure SMTP settings to send email alerts:

```bash
export SMTP_SERVER=smtp.gmail.com
export SMTP_PORT=587
export SMTP_USER=your-email@gmail.com
export SMTP_PASSWORD=your-app-password
export ALERT_FROM_EMAIL=monitoring@laya.local
export ALERT_EMAIL=alerts@example.com

python scripts/uptime_monitoring.py
```

**Note**: For Gmail, use an [App Password](https://support.google.com/accounts/answer/185833) instead of your regular password.

### Webhook Alerts

Send alerts to any webhook endpoint (Slack, Discord, PagerDuty, etc.):

#### Slack Example

1. Create a Slack Incoming Webhook: https://api.slack.com/messaging/webhooks
2. Use the webhook URL:

```bash
export ALERT_WEBHOOK=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
python scripts/uptime_monitoring.py
```

#### PagerDuty Example

Use PagerDuty Events API v2:

```bash
export ALERT_WEBHOOK=https://events.pagerduty.com/v2/enqueue
python scripts/uptime_monitoring.py
```

#### Custom Webhook Payload

The script sends a JSON payload:

```json
{
  "service": "AI Service",
  "status": "failure",
  "url": "http://localhost:8000/api/v1/health/liveness",
  "message": "Service is down (3 consecutive failures)",
  "timestamp": "2026-02-17T03:30:00",
  "statistics": {
    "total_checks": 150,
    "total_failures": 3,
    "consecutive_failures": 3,
    "uptime_percentage": 98.0,
    "avg_response_time_ms": 45
  }
}
```

## Output Examples

### Healthy Services

```
2026-02-17 03:25:00 - INFO - ✓ AI Service: healthy (200) - 42ms
2026-02-17 03:25:01 - INFO - ✓ Gibbon CMS: healthy (200) - 38ms
2026-02-17 03:25:02 - INFO - ✓ Parent Portal: healthy (200) - 125ms
```

### Service Failure

```
2026-02-17 03:26:00 - ERROR - ✗ AI Service: Connection failed - Connection refused
2026-02-17 03:26:00 - ERROR - 🚨 AI Service: ALERT - 1 consecutive failures
2026-02-17 03:26:00 - INFO - 📧 Email alert sent to alerts@example.com
2026-02-17 03:26:00 - INFO - 📡 Webhook alert sent successfully
```

### Service Recovery

```
2026-02-17 03:28:00 - INFO - ✓ AI Service: healthy (200) - 45ms
2026-02-17 03:28:00 - INFO - 🔄 AI Service: RECOVERED after 2 failures (downtime: 0:02:00)
2026-02-17 03:28:00 - INFO - 📧 Email alert sent to alerts@example.com
```

### Degraded Service

```
2026-02-17 03:27:00 - WARNING - ⚠ Gibbon CMS: degraded (503) - 120ms
```

### Status Report (Every 10 Minutes)

```
================================================================================
Status Report - Uptime: 1:00:00
================================================================================
✓ AI Service           | Status: healthy    | Uptime:  99.50% | Failures:   1/ 60 | Avg RT:    43ms
✓ Gibbon CMS           | Status: healthy    | Uptime: 100.00% | Failures:   0/ 60 | Avg RT:    38ms
✓ Parent Portal        | Status: healthy    | Uptime: 100.00% | Failures:   0/ 60 | Avg RT:   122ms
================================================================================
```

## Monitoring Best Practices

### Production Deployment

1. **Run as a Service**: Use systemd, supervisor, or Docker to ensure automatic restarts
2. **Configure Alerts**: Set up email and webhook alerts for immediate notification
3. **Log Rotation**: Configure log rotation to prevent disk space issues
4. **Monitoring Intervals**: Use 60-second intervals for production (default)
5. **Multiple Monitors**: Run monitors from different locations for redundancy

### Alert Thresholds

The script sends alerts at these times:
- **First Failure**: Immediate alert when a service goes down
- **Every 5 Failures**: Reminder alerts for prolonged outages
- **Status Changes**: When service status changes (healthy ↔ degraded ↔ unhealthy)
- **Recovery**: When service recovers from downtime

### Integration with External Monitoring

#### Prometheus Integration

Create a metrics endpoint in the monitoring script or use the existing health endpoints with Prometheus scrapers.

#### Grafana Dashboards

Import health metrics into Grafana for visualization.

#### UptimeRobot / StatusPage

Use the health endpoints directly with external uptime monitoring services.

## Troubleshooting

### Connection Refused

**Symptom**: `Connection failed - Connection refused`

**Solutions**:
- Verify services are running: `docker-compose ps`
- Check URLs are correct
- Ensure network connectivity
- Check firewall rules

### Request Timeout

**Symptom**: `Request timeout (>10s)`

**Solutions**:
- Services may be under heavy load
- Increase timeout in the script if needed
- Check database connection pool settings
- Review application performance

### No Email Alerts

**Symptom**: Email alerts not being sent

**Solutions**:
- Verify SMTP credentials: `SMTP_USER` and `SMTP_PASSWORD`
- Check SMTP server and port settings
- Test SMTP connection separately
- Review application logs for errors

### High Failure Rate

**Symptom**: Multiple services showing failures

**Solutions**:
- Check if all services are running
- Review system resources (CPU, memory, disk)
- Check database and Redis availability
- Review application logs

## Integration with Task 041

This uptime monitoring script integrates with the health check endpoints implemented in:

- **041-1-1**: AI Service health endpoint (`/api/v1/health/liveness`)
- **041-1-2**: Gibbon health module (`/modules/System/health.php`)
- **041-2-1**: Parent Portal health API (`/api/health`)
- **041-3-1**: Docker Compose healthcheck configuration

## Security Considerations

- **Credentials**: Store SMTP passwords securely (use environment variables or secrets management)
- **Network**: Run monitoring from trusted networks only
- **Logs**: Ensure log files don't contain sensitive information
- **Webhooks**: Use HTTPS webhooks with authentication tokens
- **Access**: Restrict access to monitoring script and logs

## Future Enhancements

Potential improvements for the monitoring script:

1. **Metrics Export**: Prometheus metrics endpoint
2. **Database Storage**: Store historical uptime data
3. **Web Dashboard**: Real-time web interface for monitoring
4. **Custom Checks**: Support for custom health check logic
5. **Alert Escalation**: Multi-level alert escalation policies
6. **SLA Tracking**: Calculate and report on SLA compliance
7. **Anomaly Detection**: ML-based anomaly detection
8. **Multi-Region**: Support for monitoring across regions

## License

Part of the LAYA Daycare Management System.

## Support

For issues or questions about the uptime monitoring script:
1. Check the troubleshooting section above
2. Review the logs: `tail -f uptime_monitoring.log`
3. Test with dry-run mode: `python scripts/uptime_monitoring.py --dry-run`
4. Contact the LAYA development team
