# Notification Queue Monitoring

## Overview

The notification queue monitoring system provides real-time visibility into the health and performance of LAYA's notification delivery infrastructure. This system monitors Redis-based queues for email, push notifications, and SMS messages, alerting operations teams to potential bottlenecks or delivery issues.

## Table of Contents

- [Architecture](#architecture)
- [Queue Types](#queue-types)
- [Health Status Levels](#health-status-levels)
- [Monitoring Endpoints](#monitoring-endpoints)
- [Metrics and Thresholds](#metrics-and-thresholds)
- [Monitoring Integration](#monitoring-integration)
- [Troubleshooting](#troubleshooting)
- [Best Practices](#best-practices)

## Architecture

### Overview

Notifications in LAYA are processed asynchronously using Redis lists as message queues. This architecture:

- **Decouples** notification generation from delivery
- **Enables** horizontal scaling of notification workers
- **Provides** resilience through queue persistence
- **Allows** priority-based processing

### Queue Infrastructure

```
┌─────────────────┐
│  Application    │
│  (Producers)    │
└────────┬────────┘
         │ LPUSH
         ▼
┌─────────────────┐      ┌──────────────────┐
│  Redis Queues   │      │  Workers         │
│                 │◄─────┤  (Consumers)     │
│  - Email        │ RPOP │                  │
│  - Push         │      │  - Email Sender  │
│  - SMS          │      │  - Push Sender   │
└─────────────────┘      │  - SMS Sender    │
                         └──────────────────┘
```

### Queue Pattern

LAYA uses the **List-based Queue** pattern in Redis:

1. **Producers** use `LPUSH` to add messages to the left of the list
2. **Consumers** use `RPOP` or `BRPOP` to remove messages from the right
3. **Monitoring** uses `LLEN` to check queue depth without removing messages

## Queue Types

### Email Queue

**Queue Name:** `laya:notifications:email`

**Purpose:** Queues email notifications for asynchronous delivery

**Use Cases:**
- Parent report delivery
- Activity summary emails
- System notifications
- Password reset emails

**Thresholds:**
- **Warning:** 1,000 messages
- **Critical:** 5,000 messages

### Push Notification Queue

**Queue Name:** `laya:notifications:push`

**Purpose:** Queues push notifications for mobile devices

**Use Cases:**
- Real-time activity updates
- Check-in/check-out notifications
- Urgent alerts
- Chat messages

**Thresholds:**
- **Warning:** 500 messages
- **Critical:** 2,000 messages

### SMS Queue

**Queue Name:** `laya:notifications:sms`

**Purpose:** Queues SMS messages for delivery

**Use Cases:**
- Critical alerts
- Verification codes
- Emergency notifications

**Thresholds:**
- **Warning:** 100 messages
- **Critical:** 500 messages

## Health Status Levels

### Healthy

**Status:** `healthy`

**Criteria:**
- Queue depth below warning threshold
- Redis connection successful
- All queues processing normally

**Action:** None required

**Example:**
```json
{
  "status": "healthy",
  "total_depth": 42,
  "queues": {
    "email": {"depth": 25, "status": "healthy"},
    "push": {"depth": 15, "status": "healthy"},
    "sms": {"depth": 2, "status": "healthy"}
  }
}
```

### Degraded

**Status:** `degraded`

**Criteria:**
- One or more queues above warning threshold
- Queue depth below critical threshold
- System still functioning but experiencing backlog

**Action:**
- Monitor queue depth trends
- Check worker health
- Consider scaling workers
- Review notification generation rate

**Example:**
```json
{
  "status": "degraded",
  "total_depth": 1260,
  "queues": {
    "email": {"depth": 1200, "status": "degraded"},
    "push": {"depth": 50, "status": "healthy"},
    "sms": {"depth": 10, "status": "healthy"}
  }
}
```

### Critical

**Status:** `critical`

**Criteria:**
- One or more queues above critical threshold
- Significant delivery delays likely
- Risk of queue overflow or memory issues

**Action:**
- **Immediate** investigation required
- Scale workers urgently
- Check for worker failures
- Verify Redis memory limits
- Review application logs for errors

**Example:**
```json
{
  "status": "critical",
  "total_depth": 6060,
  "queues": {
    "email": {"depth": 6000, "status": "critical"},
    "push": {"depth": 50, "status": "healthy"},
    "sms": {"depth": 10, "status": "healthy"}
  }
}
```

### Unhealthy

**Status:** `unhealthy`

**Criteria:**
- Cannot connect to Redis
- Queue monitoring system failure
- Infrastructure issues

**Action:**
- **Critical** - Check Redis service status
- Verify network connectivity
- Check Redis configuration
- Review Redis logs

**Example:**
```json
{
  "status": "unhealthy",
  "connected": false,
  "error": "Connection refused"
}
```

## Monitoring Endpoints

### Dedicated Queue Monitoring

**Endpoint:** `GET /api/v1/health/queues`

**Purpose:** Detailed notification queue metrics

**Response:**
```json
{
  "timestamp": "2024-02-15T10:30:00Z",
  "status": "healthy",
  "total_depth": 42,
  "connected": true,
  "queues": {
    "email": {
      "depth": 25,
      "status": "healthy",
      "queue_name": "laya:notifications:email",
      "warning_threshold": 1000,
      "critical_threshold": 5000
    },
    "push": {
      "depth": 15,
      "status": "healthy",
      "queue_name": "laya:notifications:push",
      "warning_threshold": 500,
      "critical_threshold": 2000
    },
    "sms": {
      "depth": 2,
      "status": "healthy",
      "queue_name": "laya:notifications:sms",
      "warning_threshold": 100,
      "critical_threshold": 500
    }
  }
}
```

### Comprehensive Health Check

**Endpoint:** `GET /api/v1/health`

**Purpose:** Overall system health including queues

**Queue Metrics Location:** `response.checks.notification_queues`

**Response:**
```json
{
  "status": "healthy",
  "timestamp": "2024-02-15T10:30:00Z",
  "service": "ai-service",
  "version": "0.1.0",
  "checks": {
    "database": {"status": "healthy"},
    "redis": {"status": "healthy"},
    "notification_queues": {
      "status": "healthy",
      "total_depth": 42,
      "queues": { ... }
    }
  }
}
```

## Metrics and Thresholds

### Key Metrics

#### Queue Depth
- **Metric:** `queues.<type>.depth`
- **Type:** Integer (number of messages)
- **Description:** Current number of pending messages in the queue
- **Interpretation:**
  - Low (0-100): Normal operation
  - Medium (100-1000): Moderate activity or slight backlog
  - High (>1000): Significant backlog, investigate

#### Total Depth
- **Metric:** `total_depth`
- **Type:** Integer
- **Description:** Sum of all queue depths
- **Use:** Overall notification system health indicator

#### Queue Status
- **Metric:** `queues.<type>.status`
- **Type:** String (healthy|degraded|critical)
- **Description:** Individual queue health based on thresholds

#### Overall Status
- **Metric:** `status`
- **Type:** String (healthy|degraded|critical|unhealthy)
- **Description:** Worst status among all queues

### Threshold Configuration

| Queue Type | Warning Threshold | Critical Threshold | Rationale |
|-----------|------------------|-------------------|-----------|
| Email | 1,000 | 5,000 | Batch delivery acceptable, higher volume expected |
| Push | 500 | 2,000 | Real-time expectation, lower tolerance for delays |
| SMS | 100 | 500 | Expensive, typically low volume, critical messages |

### Threshold Tuning

Adjust thresholds based on:
- **Notification Volume:** Higher volume systems need higher thresholds
- **Worker Capacity:** More workers = higher sustainable depth
- **SLA Requirements:** Stricter SLAs = lower thresholds
- **Historical Patterns:** Set based on 95th percentile of normal operation

**Configuration Location:** `ai-service/app/routers/health.py` in `check_notification_queues()`

```python
thresholds = {
    "email": {"warning": 1000, "critical": 5000},
    "push": {"warning": 500, "critical": 2000},
    "sms": {"warning": 100, "critical": 500},
}
```

## Monitoring Integration

### Prometheus Metrics

**Recommended Metrics to Export:**

```python
# Queue depth gauge
notification_queue_depth{queue_type="email"} 25
notification_queue_depth{queue_type="push"} 15
notification_queue_depth{queue_type="sms"} 2

# Queue status (0=healthy, 1=degraded, 2=critical, 3=unhealthy)
notification_queue_status{queue_type="email"} 0

# Total depth
notification_total_queue_depth 42
```

**Prometheus Scrape Configuration:**

```yaml
scrape_configs:
  - job_name: 'laya-ai-service'
    metrics_path: '/api/v1/health/queues'
    scrape_interval: 30s
    static_configs:
      - targets: ['ai-service:8000']
```

### Grafana Dashboard

**Recommended Visualizations:**

1. **Queue Depth Time Series**
   - Graph of queue depth over time for each queue type
   - Shows trends and patterns

2. **Queue Status Heatmap**
   - Color-coded status for each queue
   - Green (healthy), yellow (degraded), red (critical)

3. **Total Queue Depth Gauge**
   - Single metric showing total messages across all queues

4. **Queue Processing Rate**
   - Messages processed per minute (requires additional instrumentation)

**Sample Queries:**

```promql
# Queue depth by type
notification_queue_depth

# Average queue depth over 5 minutes
avg_over_time(notification_queue_depth[5m])

# Alert condition
notification_queue_depth{queue_type="email"} > 1000
```

### Alert Configuration

**PagerDuty/Opsgenie Integration:**

```yaml
# Degraded queue alert
- alert: NotificationQueueDegraded
  expr: notification_queue_status > 0
  for: 10m
  labels:
    severity: warning
  annotations:
    summary: "Notification queue degraded: {{ $labels.queue_type }}"
    description: "Queue {{ $labels.queue_type }} has {{ $value }} messages pending"

# Critical queue alert
- alert: NotificationQueueCritical
  expr: notification_queue_status >= 2
  for: 5m
  labels:
    severity: critical
  annotations:
    summary: "Notification queue critical: {{ $labels.queue_type }}"
    description: "Queue {{ $labels.queue_type }} is at critical depth"
```

### Datadog Integration

```yaml
# Monitor configuration
monitors:
  - name: "Notification Queue Depth"
    type: metric
    query: "max:notification.queue.depth{queue_type:email} > 1000"
    message: |
      Email notification queue is degraded with {{value}} messages.
      @pagerduty-laya-ops
```

### Direct HTTP Polling

**Bash Script Example:**

```bash
#!/bin/bash
# Monitor notification queues every 60 seconds

while true; do
  response=$(curl -s http://ai-service:8000/api/v1/health/queues)
  status=$(echo "$response" | jq -r '.status')
  total_depth=$(echo "$response" | jq -r '.total_depth')

  echo "[$(date)] Status: $status, Total Depth: $total_depth"

  if [ "$status" == "critical" ]; then
    echo "ALERT: Notification queues critical!"
    # Send alert (email, webhook, etc.)
  fi

  sleep 60
done
```

## Troubleshooting

### High Queue Depth

**Symptoms:**
- Queue depth increasing steadily
- Status degraded or critical
- Notification delivery delays

**Diagnosis:**

1. **Check Worker Status:**
   ```bash
   # Check if notification workers are running
   docker ps | grep notification-worker

   # Check worker logs
   docker logs notification-worker
   ```

2. **Verify Processing Rate:**
   ```bash
   # Monitor queue depth changes over time
   while true; do
     redis-cli LLEN laya:notifications:email
     sleep 5
   done
   ```

3. **Check Redis Memory:**
   ```bash
   redis-cli INFO memory
   ```

**Solutions:**

1. **Scale Workers:**
   ```bash
   # Increase worker count
   docker-compose up -d --scale notification-worker=5
   ```

2. **Investigate Worker Errors:**
   - Check application logs for exceptions
   - Verify SMTP/SMS/Push service credentials
   - Check network connectivity

3. **Temporary Rate Limiting:**
   - Reduce notification generation temporarily
   - Batch less critical notifications

### Queue Not Draining

**Symptoms:**
- Queue depth not decreasing
- Workers running but not processing
- Messages stuck in queue

**Diagnosis:**

1. **Verify Worker Configuration:**
   ```bash
   # Check worker environment variables
   docker exec notification-worker env | grep REDIS
   ```

2. **Test Queue Manually:**
   ```bash
   # Add test message
   redis-cli LPUSH laya:notifications:email '{"test": true}'

   # Check if it's consumed
   redis-cli LLEN laya:notifications:email
   ```

3. **Check for Deadlocks:**
   - Review worker logs for blocking operations
   - Check database connection pool utilization

**Solutions:**

1. **Restart Workers:**
   ```bash
   docker-compose restart notification-worker
   ```

2. **Clear Stuck Messages:**
   ```bash
   # CAUTION: This removes all messages
   redis-cli DEL laya:notifications:email
   ```

3. **Fix Configuration Issues:**
   - Correct Redis connection settings
   - Update worker code if buggy

### Redis Connection Failures

**Symptoms:**
- Status: unhealthy
- Error: "Connection refused"
- All queues unreachable

**Diagnosis:**

1. **Check Redis Service:**
   ```bash
   docker ps | grep redis
   redis-cli ping
   ```

2. **Verify Network:**
   ```bash
   # Test connectivity from AI service
   docker exec ai-service ping redis
   ```

3. **Check Redis Logs:**
   ```bash
   docker logs redis
   ```

**Solutions:**

1. **Restart Redis:**
   ```bash
   docker-compose restart redis
   ```

2. **Check Configuration:**
   - Verify `REDIS_HOST` and `REDIS_PORT` environment variables
   - Check Redis authentication settings

3. **Verify Resource Limits:**
   - Ensure Redis has sufficient memory
   - Check disk space for persistence

### Message Loss

**Symptoms:**
- Messages disappearing from queue
- Notifications not delivered
- Queue depth drops unexpectedly

**Diagnosis:**

1. **Check Redis Persistence:**
   ```bash
   redis-cli CONFIG GET save
   redis-cli LASTSAVE
   ```

2. **Verify TTL Settings:**
   ```bash
   # Check if keys have expiration
   redis-cli TTL laya:notifications:email
   ```

3. **Review Worker Logs:**
   - Look for exceptions during message processing
   - Check if messages are being acknowledged

**Solutions:**

1. **Enable Redis Persistence:**
   ```bash
   # In redis.conf
   save 900 1
   save 300 10
   save 60 10000
   ```

2. **Implement Dead Letter Queue:**
   - Move failed messages to separate queue
   - Review and retry manually

3. **Add Message Durability:**
   - Use Redis `BRPOPLPUSH` for atomic operations
   - Implement acknowledgment mechanism

## Best Practices

### Queue Management

1. **Monitor Continuously**
   - Set up automated monitoring with alerts
   - Review metrics during daily standups
   - Analyze trends weekly

2. **Set Appropriate Thresholds**
   - Based on historical data (95th percentile)
   - Account for peak periods
   - Adjust seasonally if needed

3. **Scale Proactively**
   - Add workers before reaching degraded state
   - Use autoscaling based on queue depth
   - Test scaling procedures regularly

### Worker Configuration

1. **Right-Size Worker Pool**
   - Start with 2-3 workers per queue type
   - Scale based on queue depth metrics
   - Consider peak loads (e.g., end of day reports)

2. **Implement Backoff**
   - Exponential backoff for transient failures
   - Maximum retry limits
   - Dead letter queue for permanent failures

3. **Batch When Appropriate**
   - Group similar notifications
   - Reduce API calls to external services
   - Balance latency vs. efficiency

### Infrastructure

1. **Redis High Availability**
   - Use Redis Sentinel for failover
   - Enable persistence (AOF or RDB)
   - Regular backups of critical queues

2. **Monitoring Integration**
   - Export metrics to Prometheus/Datadog
   - Set up alerts with escalation
   - Create runbooks for common issues

3. **Capacity Planning**
   - Estimate notification volume growth
   - Plan Redis memory accordingly
   - Test failure scenarios regularly

### Development

1. **Queue Name Conventions**
   - Use consistent naming: `laya:notifications:<type>`
   - Document all queue names
   - Avoid creating ad-hoc queues

2. **Message Format**
   - Use consistent JSON schema
   - Include metadata (timestamp, priority, retry count)
   - Version messages for backward compatibility

3. **Error Handling**
   - Log failed message processing
   - Implement retry logic
   - Alert on sustained failures

### Operations

1. **Regular Health Checks**
   - Review queue metrics daily
   - Investigate any degraded states
   - Document resolution steps

2. **Incident Response**
   - Runbooks for common scenarios
   - Escalation procedures
   - Post-incident reviews

3. **Performance Testing**
   - Load test notification system
   - Verify scaling procedures
   - Test failover scenarios

## Related Documentation

- [Connection Pool Monitoring](./CONNECTION_POOL_MONITORING.md)
- [Health Check Endpoints](./HEALTH_CHECKS.md)
- [Redis Configuration Guide](./REDIS_CONFIGURATION.md)
- [Notification Workers Setup](./NOTIFICATION_WORKERS.md)

## Support

For issues with notification queue monitoring:
- Check this documentation first
- Review logs in `/var/log/laya/ai-service/`
- Contact DevOps team via #laya-ops Slack channel
- Escalate critical issues to on-call engineer via PagerDuty
