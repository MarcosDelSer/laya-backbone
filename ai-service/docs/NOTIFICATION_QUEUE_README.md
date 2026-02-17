# Notification Queue Monitoring - Quick Reference

## Quick Start

### Check Queue Status

```bash
# Dedicated queue monitoring endpoint
curl http://localhost:8000/api/v1/health/queues

# Comprehensive health check (includes queues)
curl http://localhost:8000/api/v1/health
```

### Key Metrics

| Queue Type | Warning Threshold | Critical Threshold |
|-----------|------------------|-------------------|
| Email | 1,000 | 5,000 |
| Push | 500 | 2,000 |
| SMS | 100 | 500 |

### Health Status Levels

- **healthy**: All queues below warning threshold
- **degraded**: One or more queues above warning threshold
- **critical**: One or more queues above critical threshold
- **unhealthy**: Cannot connect to Redis or system error

## Monitored Queues

1. **Email Queue** (`laya:notifications:email`)
   - Parent reports, activity summaries, system notifications

2. **Push Notification Queue** (`laya:notifications:push`)
   - Real-time activity updates, check-in/out, alerts

3. **SMS Queue** (`laya:notifications:sms`)
   - Critical alerts, verification codes, emergencies

## Quick Troubleshooting

### High Queue Depth
```bash
# Check worker status
docker ps | grep notification-worker

# Scale workers
docker-compose up -d --scale notification-worker=5
```

### Queue Not Draining
```bash
# Restart workers
docker-compose restart notification-worker

# Check Redis connection
redis-cli LLEN laya:notifications:email
```

### Connection Failures
```bash
# Check Redis service
docker ps | grep redis
redis-cli ping

# Restart Redis
docker-compose restart redis
```

## Complete Documentation

For detailed information, see [NOTIFICATION_QUEUE_MONITORING.md](./NOTIFICATION_QUEUE_MONITORING.md)

## Implementation Details

- **Location**: `app/routers/health.py`
- **Function**: `check_notification_queues()`
- **Endpoints**:
  - `/api/v1/health/queues` - Dedicated queue monitoring
  - `/api/v1/health` - Comprehensive health check
- **Tests**: `tests/test_health.py` (370+ lines, 11 test cases)
