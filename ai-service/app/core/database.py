"""Database performance optimization utilities for LAYA AI Service.

Provides utilities for query performance analysis, index recommendations,
and database optimization monitoring.
"""

import re
from typing import Any, Dict, List, Optional

from sqlalchemy import text
from sqlalchemy.ext.asyncio import AsyncSession


async def explain_query(
    session: AsyncSession,
    query: str,
    analyze: bool = True,
) -> Dict[str, Any]:
    """Execute EXPLAIN (ANALYZE) on a query for performance analysis.

    Args:
        session: Database session
        query: SQL query to analyze
        analyze: Whether to include ANALYZE (executes the query)

    Returns:
        dict: Query execution plan with timing and cost information

    Example:
        >>> plan = await explain_query(
        ...     session,
        ...     "SELECT * FROM activities WHERE is_active = true",
        ...     analyze=True
        ... )
        >>> print(f"Execution time: {plan['execution_time_ms']}ms")
    """
    explain_cmd = "EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON)" if analyze else "EXPLAIN (FORMAT JSON)"
    result = await session.execute(text(f"{explain_cmd} {query}"))
    plan = result.fetchone()[0]

    # Extract key metrics from the plan
    if analyze and isinstance(plan, list) and len(plan) > 0:
        plan_data = plan[0]
        return {
            "query": query,
            "execution_plan": plan_data,
            "execution_time_ms": plan_data.get("Execution Time"),
            "planning_time_ms": plan_data.get("Planning Time"),
            "total_cost": plan_data.get("Plan", {}).get("Total Cost"),
            "rows_returned": plan_data.get("Plan", {}).get("Actual Rows"),
        }
    return {
        "query": query,
        "execution_plan": plan,
    }


async def find_slow_queries(
    session: AsyncSession,
    min_duration_ms: float = 100.0,
    limit: int = 20,
) -> List[Dict[str, Any]]:
    """Find slow queries from pg_stat_statements.

    Requires pg_stat_statements extension to be enabled in PostgreSQL.

    Args:
        session: Database session
        min_duration_ms: Minimum query duration in milliseconds
        limit: Maximum number of queries to return

    Returns:
        list: List of slow queries with their statistics

    Example:
        >>> slow_queries = await find_slow_queries(session, min_duration_ms=500)
        >>> for query in slow_queries:
        ...     print(f"Query: {query['query']}")
        ...     print(f"Avg time: {query['mean_time_ms']}ms")
    """
    query = text("""
        SELECT
            query,
            calls,
            total_exec_time,
            mean_exec_time,
            max_exec_time,
            stddev_exec_time,
            rows
        FROM pg_stat_statements
        WHERE mean_exec_time > :min_duration
        ORDER BY mean_exec_time DESC
        LIMIT :limit
    """)

    result = await session.execute(
        query,
        {"min_duration": min_duration_ms, "limit": limit}
    )

    slow_queries = []
    for row in result:
        slow_queries.append({
            "query": row[0],
            "calls": row[1],
            "total_time_ms": row[2],
            "mean_time_ms": row[3],
            "max_time_ms": row[4],
            "stddev_time_ms": row[5],
            "rows_returned": row[6],
        })

    return slow_queries


async def find_missing_indexes(
    session: AsyncSession,
    min_scans: int = 100,
) -> List[Dict[str, Any]]:
    """Identify tables that might benefit from additional indexes.

    Analyzes sequential scans and suggests tables that might need indexes.

    Args:
        session: Database session
        min_scans: Minimum number of sequential scans to consider

    Returns:
        list: Tables with high sequential scan counts

    Example:
        >>> missing_indexes = await find_missing_indexes(session)
        >>> for table in missing_indexes:
        ...     print(f"Table: {table['table_name']}")
        ...     print(f"Sequential scans: {table['seq_scans']}")
    """
    query = text("""
        SELECT
            schemaname,
            tablename,
            seq_scan,
            seq_tup_read,
            idx_scan,
            CASE
                WHEN seq_scan > 0 THEN seq_tup_read::float / seq_scan
                ELSE 0
            END AS avg_seq_tup_read
        FROM pg_stat_user_tables
        WHERE seq_scan > :min_scans
        ORDER BY seq_tup_read DESC
        LIMIT 20
    """)

    result = await session.execute(query, {"min_scans": min_scans})

    tables = []
    for row in result:
        tables.append({
            "schema": row[0],
            "table_name": row[1],
            "seq_scans": row[2],
            "seq_tuples_read": row[3],
            "index_scans": row[4],
            "avg_tuples_per_scan": row[5],
        })

    return tables


async def get_table_index_usage(
    session: AsyncSession,
    schema: str = "public",
) -> List[Dict[str, Any]]:
    """Get index usage statistics for all tables in a schema.

    Args:
        session: Database session
        schema: Database schema name

    Returns:
        list: Index usage statistics for each table

    Example:
        >>> index_stats = await get_table_index_usage(session)
        >>> for stat in index_stats:
        ...     print(f"Table: {stat['table_name']}")
        ...     print(f"Index usage: {stat['index_usage_pct']}%")
    """
    query = text("""
        SELECT
            t.tablename,
            COALESCE(i.indexrelname, 'No index') AS indexname,
            COALESCE(i.idx_scan, 0) AS idx_scans,
            t.seq_scan,
            CASE
                WHEN (t.seq_scan + COALESCE(i.idx_scan, 0)) > 0
                THEN ROUND(100.0 * COALESCE(i.idx_scan, 0) / (t.seq_scan + COALESCE(i.idx_scan, 0)), 2)
                ELSE 0
            END AS index_usage_pct
        FROM pg_stat_user_tables t
        LEFT JOIN pg_stat_user_indexes i ON t.relid = i.relid
        WHERE t.schemaname = :schema
        ORDER BY t.tablename, i.idx_scan DESC NULLS LAST
    """)

    result = await session.execute(query, {"schema": schema})

    stats = []
    for row in result:
        stats.append({
            "table_name": row[0],
            "index_name": row[1],
            "index_scans": row[2],
            "seq_scans": row[3],
            "index_usage_pct": row[4],
        })

    return stats


async def analyze_query_plan(
    session: AsyncSession,
    query: str,
) -> Dict[str, Any]:
    """Analyze a query execution plan and provide optimization suggestions.

    Args:
        session: Database session
        query: SQL query to analyze

    Returns:
        dict: Analysis results with optimization suggestions

    Example:
        >>> analysis = await analyze_query_plan(
        ...     session,
        ...     "SELECT * FROM activities WHERE name LIKE '%test%'"
        ... )
        >>> for suggestion in analysis['suggestions']:
        ...     print(suggestion)
    """
    plan = await explain_query(session, query, analyze=True)

    suggestions = []
    plan_text = str(plan.get("execution_plan", {}))

    # Check for sequential scans
    if "Seq Scan" in plan_text:
        suggestions.append(
            "Query uses sequential scan. Consider adding an index on the filtered columns."
        )

    # Check for high execution time
    exec_time = plan.get("execution_time_ms", 0)
    if exec_time > 100:
        suggestions.append(
            f"Query execution time ({exec_time:.2f}ms) is high. "
            "Review indexes and query structure."
        )

    # Check for large result sets
    rows = plan.get("rows_returned", 0)
    if rows > 1000:
        suggestions.append(
            f"Query returns {rows} rows. Consider adding pagination or limiting results."
        )

    # Check for missing indexes on joins
    if "Hash Join" in plan_text or "Nested Loop" in plan_text:
        suggestions.append(
            "Query uses joins. Ensure foreign key columns are indexed."
        )

    return {
        "query": query,
        "execution_time_ms": exec_time,
        "planning_time_ms": plan.get("planning_time_ms", 0),
        "total_cost": plan.get("total_cost", 0),
        "rows_returned": rows,
        "suggestions": suggestions,
        "full_plan": plan.get("execution_plan"),
    }


async def get_cache_hit_ratio(session: AsyncSession) -> Dict[str, float]:
    """Get database cache hit ratio statistics.

    A low cache hit ratio indicates that queries are reading from disk
    rather than memory, which can significantly impact performance.

    Args:
        session: Database session

    Returns:
        dict: Cache hit ratios for different cache types

    Example:
        >>> cache_stats = await get_cache_hit_ratio(session)
        >>> print(f"Buffer cache hit ratio: {cache_stats['buffer_cache_hit_ratio']:.2%}")
    """
    # Buffer cache hit ratio
    buffer_query = text("""
        SELECT
            sum(heap_blks_read) as heap_read,
            sum(heap_blks_hit) as heap_hit,
            sum(heap_blks_hit) / NULLIF(sum(heap_blks_hit) + sum(heap_blks_read), 0) as ratio
        FROM pg_statio_user_tables
    """)

    result = await session.execute(buffer_query)
    row = result.fetchone()

    buffer_cache_hit_ratio = row[2] if row and row[2] else 0.0

    # Index cache hit ratio
    index_query = text("""
        SELECT
            sum(idx_blks_read) as idx_read,
            sum(idx_blks_hit) as idx_hit,
            sum(idx_blks_hit) / NULLIF(sum(idx_blks_hit) + sum(idx_blks_read), 0) as ratio
        FROM pg_statio_user_indexes
    """)

    result = await session.execute(index_query)
    row = result.fetchone()

    index_cache_hit_ratio = row[2] if row and row[2] else 0.0

    return {
        "buffer_cache_hit_ratio": buffer_cache_hit_ratio,
        "index_cache_hit_ratio": index_cache_hit_ratio,
    }


async def vacuum_analyze_table(
    session: AsyncSession,
    table_name: str,
) -> None:
    """Run VACUUM ANALYZE on a specific table.

    VACUUM reclaims storage and ANALYZE updates statistics for the query planner.

    Args:
        session: Database session
        table_name: Name of the table to vacuum and analyze

    Example:
        >>> await vacuum_analyze_table(session, "activities")
    """
    # Note: VACUUM cannot run inside a transaction block
    # This should be called with autocommit enabled
    await session.execute(text(f"VACUUM ANALYZE {table_name}"))
    await session.commit()


async def get_database_size_stats(session: AsyncSession) -> Dict[str, Any]:
    """Get database and table size statistics.

    Args:
        session: Database session

    Returns:
        dict: Database size statistics

    Example:
        >>> stats = await get_database_size_stats(session)
        >>> print(f"Total database size: {stats['total_size_mb']:.2f} MB")
    """
    # Database size
    db_size_query = text("""
        SELECT pg_database_size(current_database()) as size_bytes
    """)
    result = await session.execute(db_size_query)
    db_size_bytes = result.fetchone()[0]

    # Top 10 largest tables
    table_size_query = text("""
        SELECT
            tablename,
            pg_total_relation_size(schemaname||'.'||tablename) AS size_bytes
        FROM pg_tables
        WHERE schemaname = 'public'
        ORDER BY size_bytes DESC
        LIMIT 10
    """)
    result = await session.execute(table_size_query)

    tables = []
    for row in result:
        tables.append({
            "table_name": row[0],
            "size_bytes": row[1],
            "size_mb": row[1] / (1024 * 1024),
        })

    return {
        "total_size_bytes": db_size_bytes,
        "total_size_mb": db_size_bytes / (1024 * 1024),
        "largest_tables": tables,
    }
