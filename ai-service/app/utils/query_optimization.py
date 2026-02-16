"""SQLAlchemy query optimization utilities for N+1 query prevention.

This module provides reusable eager loading strategies to prevent N+1 query problems
in SQLAlchemy. It uses selectinload and joinedload strategies to efficiently load
related data in a single query or minimal additional queries.

Usage:
    from app.utils.query_optimization import eager_load_activity_relationships

    # Apply eager loading to a query
    query = select(Activity)
    query = eager_load_activity_relationships(query)
    result = await db.execute(query)
"""

from typing import TypeVar

from sqlalchemy import Select
from sqlalchemy.orm import joinedload, selectinload

from app.models.activity import Activity, ActivityParticipation, ActivityRecommendation
from app.models.coaching import CoachingRecommendation, CoachingSession, EvidenceSource
from app.models.communication import HomeActivity, ParentReport

# Type variable for generic query types
T = TypeVar("T")


def eager_load_activity_relationships(query: Select[tuple[Activity]]) -> Select[tuple[Activity]]:
    """Apply eager loading for Activity model relationships.

    Prevents N+1 queries when accessing activity.recommendations or
    activity.participations by loading them efficiently using selectinload.

    Use selectinload for one-to-many relationships with potentially many
    related records, as it loads them in a separate query rather than
    creating a large cartesian product with joinedload.

    Args:
        query: SQLAlchemy select query for Activity model

    Returns:
        Query with eager loading options applied

    Example:
        >>> query = select(Activity).where(Activity.is_active == True)
        >>> query = eager_load_activity_relationships(query)
        >>> result = await db.execute(query)
        >>> activities = result.scalars().all()
        >>> # Accessing activities[0].recommendations won't trigger additional queries
    """
    return query.options(
        selectinload(Activity.recommendations),
        selectinload(Activity.participations),
    )


def eager_load_activity_participation_relationships(
    query: Select[tuple[ActivityParticipation]]
) -> Select[tuple[ActivityParticipation]]:
    """Apply eager loading for ActivityParticipation model relationships.

    Prevents N+1 queries when accessing participation.activity by loading
    the activity using joinedload (many-to-one, so efficient with join).

    Use joinedload for many-to-one relationships as there's only one
    related record per participation.

    Args:
        query: SQLAlchemy select query for ActivityParticipation model

    Returns:
        Query with eager loading options applied

    Example:
        >>> query = select(ActivityParticipation).where(
        ...     ActivityParticipation.child_id == child_id
        ... )
        >>> query = eager_load_activity_participation_relationships(query)
        >>> result = await db.execute(query)
        >>> participations = result.scalars().all()
        >>> # Accessing participations[0].activity won't trigger additional queries
    """
    return query.options(
        joinedload(ActivityParticipation.activity),
    )


def eager_load_activity_recommendation_relationships(
    query: Select[tuple[ActivityRecommendation]]
) -> Select[tuple[ActivityRecommendation]]:
    """Apply eager loading for ActivityRecommendation model relationships.

    Prevents N+1 queries when accessing recommendation.activity by loading
    the activity using joinedload.

    Args:
        query: SQLAlchemy select query for ActivityRecommendation model

    Returns:
        Query with eager loading options applied
    """
    return query.options(
        joinedload(ActivityRecommendation.activity),
    )


def eager_load_coaching_session_relationships(
    query: Select[tuple[CoachingSession]]
) -> Select[tuple[CoachingSession]]:
    """Apply eager loading for CoachingSession model relationships.

    Prevents N+1 queries when accessing session.recommendations and
    recommendation.evidence_sources by loading them efficiently.

    Uses nested selectinload to load the full relationship tree:
    - Session -> Recommendations (selectinload for one-to-many)
    - Recommendation -> EvidenceSources (selectinload for one-to-many)

    Args:
        query: SQLAlchemy select query for CoachingSession model

    Returns:
        Query with eager loading options applied

    Example:
        >>> query = select(CoachingSession).where(
        ...     CoachingSession.child_id == child_id
        ... )
        >>> query = eager_load_coaching_session_relationships(query)
        >>> result = await db.execute(query)
        >>> sessions = result.scalars().all()
        >>> # Accessing sessions[0].recommendations[0].evidence_sources won't trigger queries
    """
    return query.options(
        selectinload(CoachingSession.recommendations).selectinload(
            CoachingRecommendation.evidence_sources
        ),
    )


def eager_load_coaching_recommendation_relationships(
    query: Select[tuple[CoachingRecommendation]]
) -> Select[tuple[CoachingRecommendation]]:
    """Apply eager loading for CoachingRecommendation model relationships.

    Prevents N+1 queries when accessing recommendation.session or
    recommendation.evidence_sources.

    Args:
        query: SQLAlchemy select query for CoachingRecommendation model

    Returns:
        Query with eager loading options applied
    """
    return query.options(
        joinedload(CoachingRecommendation.session),
        selectinload(CoachingRecommendation.evidence_sources),
    )


def eager_load_evidence_source_relationships(
    query: Select[tuple[EvidenceSource]]
) -> Select[tuple[EvidenceSource]]:
    """Apply eager loading for EvidenceSource model relationships.

    Prevents N+1 queries when accessing evidence.recommendation.

    Args:
        query: SQLAlchemy select query for EvidenceSource model

    Returns:
        Query with eager loading options applied
    """
    return query.options(
        joinedload(EvidenceSource.recommendation),
    )


# Best Practices Documentation
"""
N+1 Query Optimization Best Practices for LAYA AI Service
===========================================================

What is an N+1 Query Problem?
------------------------------
An N+1 query problem occurs when:
1. You fetch N parent records with 1 query
2. For each parent, you access a relationship, triggering N additional queries
3. Total: 1 + N queries instead of 1-2 queries

Example of N+1 Problem:
```python
# BAD: N+1 queries
activities = await db.execute(select(Activity))
for activity in activities.scalars():
    # Each access to .recommendations triggers a new query!
    print(len(activity.recommendations))  # Query 1, 2, 3, ..., N
```

Solution: Eager Loading
-----------------------
Use selectinload or joinedload to load relationships upfront:

```python
# GOOD: 2 queries total (1 for activities, 1 for all recommendations)
query = select(Activity).options(selectinload(Activity.recommendations))
activities = await db.execute(query)
for activity in activities.scalars():
    # No additional query - data already loaded
    print(len(activity.recommendations))
```

When to Use selectinload vs joinedload:
----------------------------------------
1. **selectinload** - Use for one-to-many relationships:
   - Loads related records in a separate SELECT IN query
   - Avoids cartesian product for large result sets
   - Example: Activity.recommendations (1 activity -> many recommendations)

2. **joinedload** - Use for many-to-one relationships:
   - Loads related records using a SQL JOIN in the same query
   - Efficient when there's only one related record
   - Example: ActivityRecommendation.activity (many recommendations -> 1 activity)

Implementation Checklist:
-------------------------
✓ Identify all ORM relationship accesses in service methods
✓ Add eager loading using the appropriate strategy
✓ Test with query logging enabled to verify optimization
✓ Monitor production query counts with APM tools
✓ Document eager loading in service method docstrings

Query Monitoring:
-----------------
Enable SQLAlchemy logging to monitor query counts:

```python
import logging
logging.basicConfig()
logging.getLogger('sqlalchemy.engine').setLevel(logging.INFO)
```

This will show all SQL queries executed, making N+1 problems obvious.

Performance Impact:
-------------------
Typical improvements from fixing N+1 queries:
- 10-100 queries reduced to 1-3 queries
- Response time improved by 50-95%
- Database load significantly reduced
- Better scalability under high traffic

Further Reading:
----------------
- SQLAlchemy Loading Techniques: https://docs.sqlalchemy.org/en/20/orm/queryguide/relationships.html
- Relationship Loading Techniques: https://docs.sqlalchemy.org/en/20/orm/loading_relationships.html
"""
