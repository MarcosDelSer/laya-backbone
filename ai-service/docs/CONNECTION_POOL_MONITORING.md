# Connection Pool Monitoring

This document describes the connection pool monitoring feature for the LAYA AI Service, which provides real-time visibility into database and Redis connection pool health and utilization.

## Overview

Connection pool monitoring helps you:
- **Prevent resource exhaustion** by tracking pool utilization
- **Detect connection leaks** by monitoring checked-out connections
- **Optimize pool sizing** based on actual usage patterns
- **Identify performance bottlenecks** before they impact users

## Endpoints

### GET /api/v1/health/pools

Dedicated endpoint for connection pool metrics.

**Response:**
```json
{
  "timestamp": "2024-02-15T10:30:00Z",
  "pools": {
    "database": {
      "status": "healthy",
      "pool_size": 5,
      "checked_out": 2,
      "checked_in": 3,
      "overflow": 0,
      "total_capacity": 5,
      "utilization_percent": 40.0
    },
    "redis": {
      "status": "healthy",
      "max_connections": 10,
      "connected_clients": 5,
      "total_connections_received": 1250,
      "connection_kwargs": {
        "host": "localhost",
        "port": 6379,
        "db": 0
      }
    }
  }
}
```

### GET /api/v1/health

Comprehensive health check that includes pool metrics along with other health checks.

**Response includes:**
- `checks.database_pool` - Database connection pool status
- `checks.redis_pool` - Redis connection pool status

## Database Pool Metrics

### Status Levels

- **healthy** - Pool utilization < 80%
- **degraded** - Pool utilization 80-95% (warning threshold)
- **critical** - Pool utilization > 95% (urgent action needed)
- **error** - Unable to retrieve pool metrics

### Key Metrics

| Metric | Description | Interpretation |
|--------|-------------|----------------|
| `pool_size` | Configured pool size | Base number of connections |
| `checked_out` | Active connections in use | Current load |
| `checked_in` | Available connections | Idle connections ready for use |
| `overflow` | Connections beyond pool_size | Indicates high demand |
| `total_capacity` | pool_size + overflow | Maximum possible connections |
| `utilization_percent` | (checked_out / total_capacity) × 100 | Pool usage percentage |

### Interpreting Database Pool Status

**Healthy (< 80% utilization):**
```json
{
  "status": "healthy",
  "pool_size": 5,
  "checked_out": 2,
  "checked_in": 3,
  "overflow": 0,
  "utilization_percent": 40.0
}
```
✅ Pool has adequate capacity for current load

**Degraded (80-95% utilization):**
```json
{
  "status": "degraded",
  "pool_size": 5,
  "checked_out": 5,
  "checked_in": 0,
  "overflow": 1,
  "utilization_percent": 83.3
}
```
⚠️ Pool is under high load; consider increasing pool_size

**Critical (> 95% utilization):**
```json
{
  "status": "critical",
  "pool_size": 5,
  "checked_out": 10,
  "checked_in": 0,
  "overflow": 5,
  "utilization_percent": 100.0
}
```
🚨 Pool is saturated; immediate action required

## Redis Pool Metrics

### Key Metrics

| Metric | Description |
|--------|-------------|
| `max_connections` | Maximum allowed connections |
| `connected_clients` | Current active clients |
| `total_connections_received` | Total connections since startup |
| `connection_kwargs` | Connection configuration |

### Interpreting Redis Pool Status

**Healthy:**
```json
{
  "status": "healthy",
  "max_connections": 10,
  "connected_clients": 3
}
```
✅ Redis pool operating normally

**Unhealthy:**
```json
{
  "status": "unhealthy",
  "error": "Connection refused"
}
```
🚨 Redis connection issue; check Redis server

## Monitoring Integration

### Prometheus Metrics

You can export pool metrics to Prometheus for long-term monitoring:

```python
# Example Prometheus exporter
from prometheus_client import Gauge

db_pool_utilization = Gauge(
    'db_pool_utilization_percent',
    'Database connection pool utilization'
)

# Update from health check
pool_data = check_database_pool()
db_pool_utilization.set(pool_data['utilization_percent'])
```

### Alert Rules

**Degraded Pool (Warning):**
- Condition: `utilization_percent > 80`
- Action: Send warning notification
- Resolution: Monitor and plan capacity increase

**Critical Pool (Urgent):**
- Condition: `utilization_percent > 95`
- Action: Send urgent alert to on-call
- Resolution: Immediately increase pool size or investigate connection leaks

### Kubernetes Integration

Monitor pool health in Kubernetes readiness probes:

```yaml
readinessProbe:
  httpGet:
    path: /api/v1/health/pools
    port: 8000
  initialDelaySeconds: 10
  periodSeconds: 30
```

## Troubleshooting

### High Pool Utilization

**Symptoms:**
- Pool status shows "degraded" or "critical"
- `utilization_percent` consistently > 80%
- `overflow` count increasing

**Possible Causes:**
1. **Increased traffic** - More concurrent requests than expected
2. **Slow queries** - Connections held longer due to slow queries
3. **Connection leaks** - Connections not being properly released

**Solutions:**

1. **Increase pool size** (short-term):
   ```python
   # In database.py
   engine = create_async_engine(
       settings.database_url,
       pool_size=10,  # Increase from default 5
       max_overflow=20,  # Allow more overflow
   )
   ```

2. **Optimize queries** (medium-term):
   - Identify and optimize slow queries
   - Add database indexes
   - Use connection pooling efficiently

3. **Fix connection leaks** (long-term):
   - Ensure all database sessions are properly closed
   - Use context managers (`async with`) consistently
   - Review exception handling in database code

### Connection Leaks

**Detecting leaks:**
- `checked_out` stays high even during low traffic
- `overflow` continuously increases
- Pool never returns to healthy state

**Debugging steps:**

1. **Check for unclosed sessions:**
   ```python
   # Bad - connection leak
   db = await get_db()
   result = await db.execute(query)
   # Forgot to close!

   # Good - proper cleanup
   async with AsyncSessionLocal() as db:
       result = await db.execute(query)
       # Automatically closed
   ```

2. **Monitor long-running connections:**
   ```sql
   -- PostgreSQL query to find long-running connections
   SELECT pid, usename, application_name, state,
          now() - state_change as duration
   FROM pg_stat_activity
   WHERE state != 'idle'
   ORDER BY duration DESC;
   ```

3. **Enable connection logging:**
   ```python
   # In database.py
   engine = create_async_engine(
       settings.database_url,
       echo=True,  # Log all SQL
       echo_pool=True,  # Log pool events
   )
   ```

### Redis Connection Issues

**Symptoms:**
- Redis pool status shows "unhealthy"
- Error message indicates connection failure

**Solutions:**

1. **Verify Redis is running:**
   ```bash
   redis-cli ping
   # Should return: PONG
   ```

2. **Check connection settings:**
   ```python
   # Verify environment variables
   REDIS_HOST=localhost
   REDIS_PORT=6379
   REDIS_DB=0
   ```

3. **Test Redis connectivity:**
   ```bash
   redis-cli -h localhost -p 6379 ping
   ```

## Best Practices

### Pool Sizing Guidelines

**Database Pool:**
- **Small services** (< 100 req/s): `pool_size=5, max_overflow=10`
- **Medium services** (100-1000 req/s): `pool_size=10, max_overflow=20`
- **Large services** (> 1000 req/s): `pool_size=20, max_overflow=40`

**Formula:**
```
pool_size = (average_concurrent_requests × average_query_time) + buffer
```

**Redis Pool:**
- Default `max_connections=10` is sufficient for most use cases
- Increase if you have many concurrent workers or high throughput

### Monitoring Strategy

1. **Real-time monitoring**: Check `/health/pools` every 60 seconds
2. **Alert thresholds**:
   - Warning: 80% utilization
   - Critical: 95% utilization
3. **Trend analysis**: Track utilization over time to predict capacity needs
4. **Correlation**: Compare pool metrics with response times and error rates

### Configuration Example

```python
# ai-service/app/database.py

from sqlalchemy.ext.asyncio import create_async_engine

engine = create_async_engine(
    settings.database_url,
    # Connection pool settings
    pool_size=10,           # Base pool size
    max_overflow=20,        # Additional connections when needed
    pool_timeout=30,        # Seconds to wait for connection
    pool_recycle=3600,      # Recycle connections after 1 hour
    pool_pre_ping=True,     # Verify connections before use

    # Logging (disable in production)
    echo=False,             # SQL query logging
    echo_pool=False,        # Pool event logging
)
```

## Performance Impact

Connection pool monitoring has minimal performance impact:

- **Overhead**: < 1ms per check
- **Frequency**: Called during health checks (typically every 60s)
- **Resource usage**: Negligible CPU and memory

It's safe to call in production environments.

## Related Documentation

- [Health Check Overview](./HEALTH_CHECKS.md)
- [Monitoring Integration](./MONITORING.md)
- [Performance Tuning](./PERFORMANCE.md)
- [SQLAlchemy Connection Pooling](https://docs.sqlalchemy.org/en/20/core/pooling.html)
