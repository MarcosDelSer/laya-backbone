# Database Connection Pooling Tuning

## Overview

Connection pooling is a critical performance optimization technique that manages a pool of database connections that can be reused, reducing the overhead of creating new connections for each database operation.

The LAYA AI Service uses SQLAlchemy's connection pooling with PostgreSQL's asyncpg driver. This document explains how to configure, monitor, and optimize connection pooling for production deployments.

## Configuration

### Environment Variables

Connection pool settings can be configured via environment variables or `.env` file:

```bash
# Database connection pool configuration
DB_POOL_SIZE=10              # Number of permanent connections (default: 10)
DB_MAX_OVERFLOW=20           # Additional overflow connections (default: 20)
DB_POOL_TIMEOUT=30           # Timeout in seconds for acquiring connection (default: 30)
DB_POOL_RECYCLE=3600         # Recycle connections after N seconds (default: 3600)
DB_POOL_PRE_PING=true        # Test connection health before use (default: true)
DB_ECHO=false                # Enable SQL query logging (default: false)
```

### Configuration Parameters Explained

#### `DB_POOL_SIZE`
- **Default**: 10
- **Description**: The number of permanent database connections maintained in the pool
- **Recommendation**: Set based on expected concurrent database operations
  - Low traffic (< 100 req/min): 5-10 connections
  - Medium traffic (100-1000 req/min): 10-20 connections
  - High traffic (> 1000 req/min): 20-50 connections

#### `DB_MAX_OVERFLOW`
- **Default**: 20
- **Description**: Maximum number of temporary connections beyond pool_size
- **Recommendation**: Set to 2x pool_size to handle traffic spikes
- **Note**: Overflow connections are closed after use, not returned to the pool

#### `DB_POOL_TIMEOUT`
- **Default**: 30 seconds
- **Description**: Maximum time to wait for an available connection
- **Recommendation**:
  - API services: 10-30 seconds
  - Background jobs: 60-120 seconds
  - If timeouts occur frequently, increase pool_size instead

#### `DB_POOL_RECYCLE`
- **Default**: 3600 seconds (1 hour)
- **Description**: Recycle (close and replace) connections after this time
- **Recommendation**: Keep at 3600 seconds (1 hour) to prevent stale connections
- **Note**: Prevents issues with database server connection limits and timeouts

#### `DB_POOL_PRE_PING`
- **Default**: true
- **Description**: Test connection health with a lightweight query before use
- **Recommendation**: Always enable in production to detect stale connections
- **Note**: Adds minimal overhead but prevents errors from broken connections

#### `DB_ECHO`
- **Default**: false
- **Description**: Log all SQL statements to console
- **Recommendation**:
  - Development: true (for debugging)
  - Production: false (performance and security)

## Monitoring Pool Health

### Check Pool Status

Get current pool statistics:

```python
from app.database import check_pool_status

# Get basic pool status
status = await check_pool_status()
print(f"Pool size: {status['pool_size']}")
print(f"Checked out: {status['checked_out']}")
print(f"Overflow: {status['overflow']}")
print(f"Total connections: {status['total_connections']}")
```

### Get Comprehensive Pool Health

Get detailed health metrics with warnings and recommendations:

```python
from app.database import get_pool_health

# Get comprehensive pool health
health = await get_pool_health()
print(f"Pool utilization: {health['utilization_pct']:.1f}%")

# Check for warnings
if health['warnings']:
    print("\nWarnings:")
    for warning in health['warnings']:
        print(f"  - {warning}")

# Check recommendations
if health['recommendations']:
    print("\nRecommendations:")
    for rec in health['recommendations']:
        print(f"  - {rec}")
```

### Monitor Active Connections

Get information about active database connections:

```python
from app.database import get_active_connections

async def monitor_connections(session):
    conn_info = await get_active_connections(session)
    print(f"Total connections: {conn_info['total_connections']}")
    print(f"Active queries: {conn_info['active_count']}")
    print(f"Idle connections: {conn_info['idle_count']}")
    print(f"Idle in transaction: {conn_info['idle_in_transaction']}")

    if conn_info['longest_query_seconds']:
        print(f"Longest query: {conn_info['longest_query_seconds']:.1f}s")
```

### Automatic Optimization Recommendations

Get AI-powered recommendations for pool settings:

```python
from app.database import optimize_pool_settings

async def get_optimization_advice(session):
    recommendations = await optimize_pool_settings(session)

    print(f"Current pool size: {recommendations['current_settings']['pool_size']}")
    print(f"Recommended pool size: {recommendations['recommended_pool_size']}")
    print(f"Recommended max overflow: {recommendations['recommended_max_overflow']}")

    print("\nRecommendations:")
    for rec in recommendations['recommendations']:
        print(f"  - {rec}")
```

## Best Practices

### 1. Start Conservative, Scale Based on Metrics

```bash
# Initial conservative settings
DB_POOL_SIZE=5
DB_MAX_OVERFLOW=10
```

Monitor pool utilization and scale up if:
- Utilization consistently > 70%
- Connection timeout errors occur
- Request latency increases during peak times

### 2. Calculate Pool Size Based on Traffic

**Formula**: `pool_size = (concurrent_requests * avg_db_time_per_request) / request_rate`

**Example**:
- Expected: 100 requests/second
- Average DB time: 50ms per request
- Calculation: (100 * 0.05) = 5 connections minimum
- Add 50% buffer: 5 * 1.5 = 7.5 → **8 connections**

### 3. Monitor Pool Health Regularly

Set up periodic health checks:

```python
import asyncio
from app.database import get_pool_health

async def periodic_health_check():
    while True:
        health = await get_pool_health()

        # Log warnings to monitoring system
        if health['warnings']:
            logger.warning("Pool health warnings", extra={
                'warnings': health['warnings'],
                'utilization': health['utilization_pct']
            })

        # Wait 5 minutes before next check
        await asyncio.sleep(300)
```

### 4. Handle Connection Timeouts Gracefully

```python
from sqlalchemy.exc import TimeoutError
from fastapi import HTTPException

async def safe_db_operation(db_operation):
    try:
        return await db_operation()
    except TimeoutError:
        raise HTTPException(
            status_code=503,
            detail="Database unavailable. Please try again."
        )
```

### 5. Avoid Idle-in-Transaction Connections

Always use context managers or explicitly commit/rollback:

```python
# Good: Automatic cleanup
async with AsyncSessionLocal() as session:
    result = await session.execute(query)
    await session.commit()
    # Session automatically closed

# Bad: Potential idle-in-transaction
session = AsyncSessionLocal()
result = await session.execute(query)
# If exception occurs, connection stays in transaction
```

### 6. Use Connection Pooling with Async Operations

Always use async/await for database operations:

```python
# Good: Non-blocking
async def get_user(user_id: str):
    async with AsyncSessionLocal() as session:
        result = await session.execute(
            select(User).where(User.id == user_id)
        )
        return result.scalar_one_or_none()

# Bad: Blocking (holds connection longer)
def get_user_blocking(user_id: str):
    # Synchronous code - don't do this
    pass
```

## Troubleshooting

### Problem: Connection Timeout Errors

**Symptoms**:
- `TimeoutError: QueuePool limit of size X overflow Y reached`
- Requests timing out during peak traffic

**Solutions**:
1. Increase `DB_POOL_SIZE` to handle more concurrent requests
2. Increase `DB_MAX_OVERFLOW` for burst traffic
3. Optimize slow queries to reduce connection hold time
4. Check for idle-in-transaction connections

### Problem: High Pool Utilization

**Symptoms**:
- Pool utilization consistently > 80%
- Frequent overflow connection usage

**Solutions**:
```bash
# Before
DB_POOL_SIZE=10
DB_MAX_OVERFLOW=20

# After (increase by 50%)
DB_POOL_SIZE=15
DB_MAX_OVERFLOW=30
```

### Problem: Stale Connections

**Symptoms**:
- Random connection errors
- "SSL connection has been closed unexpectedly"
- "server closed the connection unexpectedly"

**Solutions**:
1. Enable `DB_POOL_PRE_PING=true` (should always be enabled)
2. Reduce `DB_POOL_RECYCLE` to recycle connections more frequently:
   ```bash
   DB_POOL_RECYCLE=1800  # 30 minutes instead of 1 hour
   ```

### Problem: Too Many Database Connections

**Symptoms**:
- PostgreSQL error: "too many connections"
- Database connection limit reached

**Solutions**:
1. Check total connections across all application instances
2. Reduce pool size per instance:
   ```bash
   # If running 5 instances and DB limit is 100 connections
   # Reserve 20 for admin, 80 for app = 16 per instance
   DB_POOL_SIZE=8
   DB_MAX_OVERFLOW=8
   ```
3. Increase PostgreSQL `max_connections` setting

### Problem: Slow Database Operations

**Symptoms**:
- Long query times
- Pool exhaustion due to slow queries

**Solutions**:
1. Use query performance analysis:
   ```python
   from app.core.database import analyze_query_plan

   analysis = await analyze_query_plan(session, your_query)
   for suggestion in analysis['suggestions']:
       print(suggestion)
   ```
2. Optimize queries with indexes
3. Use eager loading to prevent N+1 queries
4. Consider query result caching

## Production Configuration Examples

### Small Application (< 100 req/min)

```bash
DB_POOL_SIZE=5
DB_MAX_OVERFLOW=10
DB_POOL_TIMEOUT=30
DB_POOL_RECYCLE=3600
DB_POOL_PRE_PING=true
DB_ECHO=false
```

### Medium Application (100-1000 req/min)

```bash
DB_POOL_SIZE=15
DB_MAX_OVERFLOW=30
DB_POOL_TIMEOUT=30
DB_POOL_RECYCLE=3600
DB_POOL_PRE_PING=true
DB_ECHO=false
```

### Large Application (> 1000 req/min)

```bash
DB_POOL_SIZE=30
DB_MAX_OVERFLOW=60
DB_POOL_TIMEOUT=20
DB_POOL_RECYCLE=3600
DB_POOL_PRE_PING=true
DB_ECHO=false
```

### Development Environment

```bash
DB_POOL_SIZE=3
DB_MAX_OVERFLOW=5
DB_POOL_TIMEOUT=30
DB_POOL_RECYCLE=3600
DB_POOL_PRE_PING=true
DB_ECHO=true  # Enable SQL logging for debugging
```

## Metrics to Monitor

Set up monitoring for these key metrics:

1. **Pool Utilization**
   - Target: < 70% average, < 90% peak
   - Alert: > 80% for more than 5 minutes

2. **Connection Checkout Time**
   - Target: < 10ms average
   - Alert: > 50ms average

3. **Pool Timeout Events**
   - Target: 0
   - Alert: > 1 per minute

4. **Overflow Usage**
   - Target: < 50% of max_overflow
   - Alert: > 80% of max_overflow

5. **Active Connections**
   - Track: Total, active, idle, idle-in-transaction
   - Alert: idle-in-transaction > 5

6. **Long-Running Queries**
   - Target: < 5 seconds
   - Alert: > 30 seconds

## Additional Resources

- [SQLAlchemy Connection Pooling](https://docs.sqlalchemy.org/en/20/core/pooling.html)
- [PostgreSQL Connection Tuning](https://wiki.postgresql.org/wiki/Number_Of_Database_Connections)
- [asyncpg Performance](https://github.com/MagicStack/asyncpg#performance)

## Related Documentation

- [Database Performance Optimization](./database_optimization.md)
- [Query Optimization Guide](./query_optimization.md)
- [Monitoring and Observability](./monitoring.md)
