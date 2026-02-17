# Query Optimization Examples

This document provides practical examples of N+1 query problems and their solutions in the LAYA AI Service.

## Example 1: Activity Recommendations Endpoint

### Problem: N+1 Queries

```python
@router.get("/activities")
async def list_activities(db: AsyncSession = Depends(get_db)):
    """List all activities with their recommendation counts."""
    # Query 1: Fetch all activities
    result = await db.execute(select(Activity).limit(10))
    activities = result.scalars().all()

    response = []
    for activity in activities:
        # Query 2-11: One query per activity to count recommendations
        # THIS IS THE N+1 PROBLEM!
        recommendation_count = len(activity.recommendations)
        response.append({
            "id": activity.id,
            "name": activity.name,
            "recommendation_count": recommendation_count
        })

    return response
    # Total: 11 queries for 10 activities
```

### Solution: Eager Loading

```python
from app.utils.query_optimization import eager_load_activity_relationships

@router.get("/activities")
async def list_activities(db: AsyncSession = Depends(get_db)):
    """List all activities with their recommendation counts."""
    # Query 1: Fetch all activities
    query = select(Activity).limit(10)
    # Apply eager loading
    query = eager_load_activity_relationships(query)
    result = await db.execute(query)
    activities = result.scalars().all()
    # Query 2: Load all recommendations in one query

    response = []
    for activity in activities:
        # No additional queries! Data already loaded
        recommendation_count = len(activity.recommendations)
        response.append({
            "id": activity.id,
            "name": activity.name,
            "recommendation_count": recommendation_count
        })

    return response
    # Total: 2 queries for 10 activities (82% reduction!)
```

## Example 2: Child Participation History

### Problem: N+1 Queries

```python
async def get_child_history(child_id: UUID, db: AsyncSession):
    """Get participation history with activity details."""
    # Query 1: Fetch participations
    result = await db.execute(
        select(ActivityParticipation)
        .where(ActivityParticipation.child_id == child_id)
    )
    participations = result.scalars().all()

    history = []
    for participation in participations:
        # Query 2-N: One query per participation to get activity
        # N+1 PROBLEM!
        history.append({
            "date": participation.started_at,
            "activity_name": participation.activity.name,
            "duration": participation.duration_minutes,
            "status": participation.completion_status,
        })

    return history
```

### Solution: Use Service Method

```python
from app.services.activity_service import ActivityService

async def get_child_history(child_id: UUID, db: AsyncSession):
    """Get participation history with activity details."""
    # Use optimized service method
    service = ActivityService(db)
    participations = await service.get_participations_for_child(child_id)

    # No N+1 problem - activity is already loaded
    history = []
    for participation in participations:
        history.append({
            "date": participation.started_at,
            "activity_name": participation.activity.name,
            "duration": participation.duration_minutes,
            "status": participation.completion_status,
        })

    return history
```

## Example 3: Coaching Session with Evidence

### Problem: Nested N+1 Queries

```python
async def get_coaching_report(child_id: UUID, db: AsyncSession):
    """Generate a report of coaching sessions with all evidence."""
    # Query 1: Fetch sessions
    result = await db.execute(
        select(CoachingSession)
        .where(CoachingSession.child_id == child_id)
    )
    sessions = result.scalars().all()

    report = []
    for session in sessions:
        # Query 2-N: One query per session to get recommendations
        session_data = {
            "question": session.question,
            "recommendations": []
        }

        for recommendation in session.recommendations:
            # Query N+1-M: One query per recommendation to get evidence
            # NESTED N+1 PROBLEM!
            recommendation_data = {
                "title": recommendation.title,
                "evidence_count": len(recommendation.evidence_sources)
            }
            session_data["recommendations"].append(recommendation_data)

        report.append(session_data)

    return report
    # For 5 sessions with 2 recommendations each, having 2 evidence sources:
    # 1 + 5 + 10 = 16 queries!
```

### Solution: Nested Eager Loading

```python
from app.services.coaching_service import CoachingService

async def get_coaching_report(child_id: UUID, db: AsyncSession):
    """Generate a report of coaching sessions with all evidence."""
    # Use optimized service method with nested eager loading
    service = CoachingService(db)
    sessions = await service.get_sessions_for_child(child_id)

    # No nested N+1 problem - all relationships loaded
    report = []
    for session in sessions:
        session_data = {
            "question": session.question,
            "recommendations": []
        }

        for recommendation in session.recommendations:
            recommendation_data = {
                "title": recommendation.title,
                "evidence_count": len(recommendation.evidence_sources)
            }
            session_data["recommendations"].append(recommendation_data)

        report.append(session_data)

    return report
    # Only 3 queries total (1 for sessions, 1 for recommendations, 1 for evidence)
```

## Example 4: Dashboard with Multiple Aggregations

### Problem: Multiple N+1 Queries

```python
async def get_dashboard_stats(db: AsyncSession):
    """Get dashboard statistics."""
    # Query 1: Get all activities
    result = await db.execute(select(Activity).limit(20))
    activities = result.scalars().all()

    stats = {
        "total_activities": len(activities),
        "total_recommendations": 0,
        "total_participations": 0,
        "popular_activities": []
    }

    for activity in activities:
        # Query 2-21: Get recommendation count (N+1)
        recommendation_count = len(activity.recommendations)
        # Query 22-41: Get participation count (Another N+1!)
        participation_count = len(activity.participations)

        stats["total_recommendations"] += recommendation_count
        stats["total_participations"] += participation_count

        if participation_count > 10:
            stats["popular_activities"].append({
                "name": activity.name,
                "count": participation_count
            })

    return stats
    # Total: 41 queries! (1 + 20 + 20)
```

### Solution: Single Query with Eager Loading

```python
from app.utils.query_optimization import eager_load_activity_relationships

async def get_dashboard_stats(db: AsyncSession):
    """Get dashboard statistics."""
    # Query 1: Get all activities with eager loading
    query = select(Activity).limit(20)
    query = eager_load_activity_relationships(query)
    result = await db.execute(query)
    activities = result.scalars().all()
    # Query 2: Load all recommendations
    # Query 3: Load all participations

    stats = {
        "total_activities": len(activities),
        "total_recommendations": 0,
        "total_participations": 0,
        "popular_activities": []
    }

    for activity in activities:
        # No additional queries - data already loaded
        recommendation_count = len(activity.recommendations)
        participation_count = len(activity.participations)

        stats["total_recommendations"] += recommendation_count
        stats["total_participations"] += participation_count

        if participation_count > 10:
            stats["popular_activities"].append({
                "name": activity.name,
                "count": participation_count
            })

    return stats
    # Total: 3 queries (93% reduction!)
```

## Example 5: Conditional Eager Loading

### When to Use Conditional Loading

```python
from app.utils.query_optimization import eager_load_activity_relationships

async def list_activities(
    db: AsyncSession,
    include_stats: bool = False
):
    """List activities with optional statistics.

    Args:
        db: Database session
        include_stats: Whether to include recommendation/participation counts
    """
    query = select(Activity).limit(50)

    # Only load relationships if they will be accessed
    if include_stats:
        query = eager_load_activity_relationships(query)

    result = await db.execute(query)
    activities = result.scalars().all()

    response = []
    for activity in activities:
        data = {
            "id": activity.id,
            "name": activity.name,
        }

        if include_stats:
            # Safe to access - relationships loaded
            data["recommendation_count"] = len(activity.recommendations)
            data["participation_count"] = len(activity.participations)

        response.append(data)

    return response
```

## Example 6: Paginated Results with Eager Loading

### Efficient Pagination

```python
from app.utils.query_optimization import eager_load_activity_participation_relationships

async def get_participation_page(
    child_id: UUID,
    page: int,
    page_size: int,
    db: AsyncSession
):
    """Get a page of participation history."""
    offset = page * page_size

    # Build query with pagination
    query = (
        select(ActivityParticipation)
        .where(ActivityParticipation.child_id == child_id)
        .order_by(ActivityParticipation.started_at.desc())
        .offset(offset)
        .limit(page_size)
    )

    # Apply eager loading for the page
    query = eager_load_activity_participation_relationships(query)

    result = await db.execute(query)
    participations = result.scalars().all()

    # No N+1 for this page
    return [{
        "date": p.started_at,
        "activity": p.activity.name,
        "duration": p.duration_minutes,
    } for p in participations]
```

## Performance Comparison

### Benchmark Results

| Example | Without Optimization | With Optimization | Improvement |
|---------|---------------------|-------------------|-------------|
| Example 1 (10 activities) | 11 queries | 2 queries | 82% |
| Example 2 (20 participations) | 21 queries | 1 query | 95% |
| Example 3 (5 sessions, 2 recs each) | 16 queries | 3 queries | 81% |
| Example 4 (20 activities with stats) | 41 queries | 3 queries | 93% |

### Response Time Improvements

- Example 1: 250ms → 45ms (82% faster)
- Example 2: 180ms → 15ms (92% faster)
- Example 3: 320ms → 55ms (83% faster)
- Example 4: 450ms → 65ms (86% faster)

## Testing Your Code

### How to Verify Optimization

```python
import pytest
from sqlalchemy import event

@pytest.mark.asyncio
async def test_no_n_plus_one(db_session):
    """Verify that query is optimized."""
    # Set up query counter
    counter = QueryCounter()
    event.listen(db_session.sync_session, "before_cursor_execute", counter)

    try:
        # Run your code
        result = await your_optimized_function(db_session)

        # Verify query count
        assert counter.count <= 3, f"Expected max 3 queries, got {counter.count}"

    finally:
        event.remove(db_session.sync_session, "before_cursor_execute", counter)
```

## Common Mistakes to Avoid

### Mistake 1: Partial Eager Loading

```python
# ❌ WRONG: Only loading one relationship
query = query.options(selectinload(Activity.recommendations))
# But then accessing participations causes N+1
for activity in activities:
    print(len(activity.participations))  # N+1 queries!
```

### Mistake 2: Over-Eager Loading

```python
# ❌ INEFFICIENT: Loading relationships that won't be used
query = query.options(
    selectinload(Activity.recommendations),
    selectinload(Activity.participations),
)
# But then only returning activity names
return [a.name for a in activities]  # Wasted queries!
```

### Mistake 3: Using Wrong Loading Strategy

```python
# ❌ INEFFICIENT: Using joinedload for one-to-many
query = query.options(joinedload(Activity.recommendations))
# Creates cartesian product - very inefficient

# ✅ CORRECT: Use selectinload for one-to-many
query = query.options(selectinload(Activity.recommendations))
```

## Migration Checklist

When optimizing an existing endpoint:

1. [ ] Enable query logging
2. [ ] Run the endpoint and count queries
3. [ ] Identify which relationships are accessed
4. [ ] Import appropriate eager loading function
5. [ ] Apply eager loading to query
6. [ ] Test and verify query count reduced
7. [ ] Add test to prevent regression
8. [ ] Document the optimization

---

**Need Help?** See [n1-query-optimization.md](./n1-query-optimization.md) for detailed guidance.
