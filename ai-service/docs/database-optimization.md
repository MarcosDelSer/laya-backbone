# Database Performance Optimization Guide

This guide covers the database optimization features implemented in the LAYA AI Service.

## Overview

The database optimization implementation includes:

1. **Composite Indexes** - Multi-column indexes for common query patterns
2. **Performance Analysis Utilities** - Tools to analyze and optimize queries
3. **Index Usage Monitoring** - Track how indexes are being used
4. **Cache Hit Ratio Tracking** - Monitor database cache efficiency

## Implemented Indexes

### Activity Tables

#### `activity_recommendations`
- `(child_id, generated_at)` - Filter recent recommendations by child
- `(child_id, is_dismissed)` - Get active recommendations per child
- `(generated_at)` - Time-based queries

#### `activity_participations`
- `(child_id, started_at)` - Filter participation by child and time
- `(started_at)` - Time-based queries
- `(completion_status)` - Filter by status

### Analytics Tables

#### `analytics_metrics`
- `(category, period_start, period_end)` - Time-range queries by category
- `(facility_id, period_start)` - Facility-specific time queries
- `(metric_name, period_start)` - Metric time-series queries

#### `enrollment_forecasts`
- `(facility_id, forecast_date)` - Facility-specific forecasts
- `(model_version)` - Filter by model version

#### `compliance_checks`
- `(facility_id, checked_at)` - Recent checks by facility
- `(next_check_due)` - Find upcoming checks
- `(check_type, status)` - Status filtering by type

### Communication Tables

#### `parent_reports`
- `(child_id, report_date)` - Child + date queries (existing)
- `(language)` - Language-specific queries
- `(report_date, created_at)` - Date range filtering

#### `home_activities`
- `(child_id, is_completed)` - Filter by child and completion
- `(developmental_area)` - Filter by development area
- `(language)` - Language-specific content

## Performance Analysis Utilities

### Query Analysis

```python
from sqlalchemy.ext.asyncio import AsyncSession
from app.core.database import explain_query, analyze_query_plan

# Analyze a specific query
async def check_query_performance(session: AsyncSession):
    query = "SELECT * FROM activities WHERE is_active = true"

    # Get execution plan
    plan = await explain_query(session, query, analyze=True)
    print(f"Execution time: {plan['execution_time_ms']}ms")
    print(f"Planning time: {plan['planning_time_ms']}ms")
    print(f"Total cost: {plan['total_cost']}")

    # Get optimization suggestions
    analysis = await analyze_query_plan(session, query)
    for suggestion in analysis['suggestions']:
        print(f"💡 {suggestion}")
```

### Finding Slow Queries

```python
from app.core.database import find_slow_queries

async def identify_slow_queries(session: AsyncSession):
    # Find queries taking more than 100ms on average
    slow_queries = await find_slow_queries(
        session,
        min_duration_ms=100.0,
        limit=20
    )

    for query in slow_queries:
        print(f"Query: {query['query']}")
        print(f"Average time: {query['mean_time_ms']:.2f}ms")
        print(f"Max time: {query['max_time_ms']:.2f}ms")
        print(f"Calls: {query['calls']}")
        print("---")
```

**Note:** Requires `pg_stat_statements` extension:
```sql
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;
```

### Index Usage Statistics

```python
from app.core.database import get_table_index_usage, find_missing_indexes

async def check_index_usage(session: AsyncSession):
    # Get index usage for all tables
    stats = await get_table_index_usage(session, schema="public")

    for stat in stats:
        print(f"Table: {stat['table_name']}")
        print(f"Index: {stat['index_name']}")
        print(f"Usage: {stat['index_usage_pct']:.2f}%")
        print("---")

    # Find tables that might need additional indexes
    missing = await find_missing_indexes(session, min_scans=100)

    for table in missing:
        print(f"⚠️  Table '{table['table_name']}' has {table['seq_scans']} sequential scans")
        print(f"   Consider adding indexes for frequently filtered columns")
```

### Cache Hit Ratio

```python
from app.core.database import get_cache_hit_ratio

async def monitor_cache_performance(session: AsyncSession):
    cache_stats = await get_cache_hit_ratio(session)

    buffer_ratio = cache_stats['buffer_cache_hit_ratio']
    index_ratio = cache_stats['index_cache_hit_ratio']

    print(f"Buffer cache hit ratio: {buffer_ratio:.2%}")
    print(f"Index cache hit ratio: {index_ratio:.2%}")

    if buffer_ratio < 0.90:
        print("⚠️  Low buffer cache hit ratio - consider increasing shared_buffers")
    if index_ratio < 0.95:
        print("⚠️  Low index cache hit ratio - review index usage")
```

### Database Size Monitoring

```python
from app.core.database import get_database_size_stats

async def check_database_size(session: AsyncSession):
    stats = await get_database_size_stats(session)

    print(f"Total database size: {stats['total_size_mb']:.2f} MB")
    print("\nLargest tables:")

    for table in stats['largest_tables']:
        print(f"  {table['table_name']}: {table['size_mb']:.2f} MB")
```

## Running Migrations

To apply the performance indexes:

```bash
cd ai-service

# Run the migration
alembic upgrade head

# Verify indexes were created
alembic current
```

## Monitoring Performance

### Regular Checks

1. **Weekly** - Review slow queries and optimize
2. **Monthly** - Check cache hit ratios and adjust configuration
3. **Quarterly** - Analyze index usage and remove unused indexes

### Key Metrics

- **Cache Hit Ratio**: Should be > 95%
- **Query Execution Time**: P95 < 100ms for most queries
- **Index Usage**: Sequential scans should be minimal on large tables

### PostgreSQL Configuration

For optimal performance, ensure these settings in `postgresql.conf`:

```ini
# Memory Configuration
shared_buffers = 256MB              # 25% of RAM (for dedicated DB server)
effective_cache_size = 1GB          # 50-75% of RAM
work_mem = 16MB                     # For sorting and hash operations
maintenance_work_mem = 64MB         # For VACUUM, CREATE INDEX

# Connection Pooling (matches SQLAlchemy settings)
max_connections = 100

# Query Planning
random_page_cost = 1.1              # For SSD storage
effective_io_concurrency = 200      # For SSD storage

# Write Performance
wal_buffers = 16MB
checkpoint_completion_target = 0.9
```

## Best Practices

### Query Optimization

1. **Use Indexed Columns in WHERE Clauses**
   ```python
   # Good: Uses index on child_id
   query = select(ActivityRecommendation).where(
       ActivityRecommendation.child_id == child_id
   )

   # Bad: Doesn't use index effectively
   query = select(ActivityRecommendation).where(
       ActivityRecommendation.generated_at.cast(String).like('2026%')
   )
   ```

2. **Limit Result Sets**
   ```python
   # Good: Uses pagination
   query = select(Activity).limit(20).offset(page * 20)

   # Bad: Returns all rows
   query = select(Activity)
   ```

3. **Use Composite Indexes for Multi-Column Filters**
   ```python
   # Good: Uses composite index (child_id, generated_at)
   query = select(ActivityRecommendation).where(
       ActivityRecommendation.child_id == child_id,
       ActivityRecommendation.generated_at >= start_date
   )
   ```

4. **Avoid LIKE with Leading Wildcards**
   ```python
   # Good: Can use index
   query = select(Activity).where(Activity.name.like('Math%'))

   # Bad: Cannot use index efficiently
   query = select(Activity).where(Activity.name.like('%Math%'))
   ```

### Index Maintenance

1. **Regular VACUUM ANALYZE**
   ```bash
   # Run weekly or as needed
   VACUUM ANALYZE activities;
   VACUUM ANALYZE activity_recommendations;
   ```

2. **Monitor Index Bloat**
   ```sql
   SELECT
       tablename,
       pg_size_pretty(pg_relation_size(schemaname||'.'||tablename)) AS size,
       pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) AS total_size
   FROM pg_tables
   WHERE schemaname = 'public'
   ORDER BY pg_total_relation_size(schemaname||'.'||tablename) DESC;
   ```

3. **Remove Unused Indexes**
   - Use `get_table_index_usage()` to identify unused indexes
   - Drop indexes with < 1% usage that aren't unique constraints

## Troubleshooting

### Slow Queries

1. Use `explain_query()` to analyze the execution plan
2. Check if indexes are being used (should see "Index Scan" not "Seq Scan")
3. Verify statistics are up-to-date with `ANALYZE`
4. Consider adding or modifying indexes

### High Memory Usage

1. Check `work_mem` setting - may be too high
2. Review query complexity and result set sizes
3. Implement pagination for large result sets
4. Monitor connection pool size

### Lock Contention

1. Review long-running queries with `pg_stat_activity`
2. Ensure transactions are kept short
3. Use appropriate isolation levels
4. Consider table partitioning for very large tables

## References

- [PostgreSQL Query Performance](https://www.postgresql.org/docs/current/performance-tips.html)
- [SQLAlchemy Performance](https://docs.sqlalchemy.org/en/20/faq/performance.html)
- [PostgreSQL Index Types](https://www.postgresql.org/docs/current/indexes-types.html)
