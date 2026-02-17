# PostgreSQL tsvector Full-Text Search Implementation

## Overview

This document describes the implementation of PostgreSQL's tsvector full-text search for the LAYA AI Service. The implementation provides efficient, production-grade full-text search across activity records with automatic relevance ranking.

## Architecture

### Database Layer

#### Search Vector Column

The `activities` table includes a `search_vector` column of type `TSVECTOR` that stores pre-computed search vectors for each activity record.

```sql
-- Column definition
search_vector TSVECTOR
```

#### Automatic Maintenance

A PostgreSQL trigger automatically maintains the `search_vector` column whenever activity data is inserted or updated:

```sql
CREATE OR REPLACE FUNCTION activities_search_vector_update() RETURNS trigger AS $$
BEGIN
    NEW.search_vector :=
        setweight(to_tsvector('english', COALESCE(NEW.name, '')), 'A') ||
        setweight(to_tsvector('english', COALESCE(NEW.description, '')), 'B') ||
        setweight(to_tsvector('english', COALESCE(NEW.special_needs_adaptations, '')), 'C');
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
```

**Weight Hierarchy:**
- **A (1.0)**: Activity name - highest weight for best matches
- **B (0.4)**: Activity description - medium weight
- **C (0.2)**: Special needs adaptations - lower weight

#### GIN Index

A GIN (Generalized Inverted Index) index on the `search_vector` column provides fast full-text search:

```sql
CREATE INDEX ix_activities_search_vector
ON activities USING gin(search_vector);
```

### Application Layer

#### Database Detection

The `SearchService` automatically detects the database type and uses the appropriate search method:

- **PostgreSQL**: Uses tsvector full-text search with `ts_rank` relevance scoring
- **SQLite**: Falls back to ILIKE pattern matching (for testing only)

```python
def _is_postgresql(self) -> bool:
    """Check if the database is PostgreSQL."""
    dialect_name = self.db.bind.dialect.name if self.db.bind else ""
    return dialect_name == "postgresql"
```

#### PostgreSQL Search Implementation

The PostgreSQL implementation uses:

1. **plainto_tsquery**: Converts the search query to a tsquery, automatically handling:
   - Stop words (common words like "the", "and", "is")
   - Stemming (reduces words to their root form)
   - Special characters

2. **ts_rank**: Calculates relevance scores based on:
   - Term frequency (how often search terms appear)
   - Document length normalization
   - Weight hierarchy (name > description > adaptations)

```python
# Create tsquery from search term
tsquery = func.plainto_tsquery('english', search_term)

# Calculate relevance score
relevance = func.ts_rank(Activity.search_vector, tsquery).label('relevance')

# Execute search with ranking
stmt = (
    select(Activity, relevance)
    .where(Activity.is_active == True)
    .where(Activity.search_vector.op('@@')(tsquery))
    .order_by(relevance.desc())
)
```

#### SQLite Fallback Implementation

For testing with SQLite, the service falls back to ILIKE pattern matching:

```python
stmt = (
    select(Activity)
    .where(Activity.is_active == True)
    .where(
        or_(
            Activity.name.ilike(f"%{search_term}%"),
            Activity.description.ilike(f"%{search_term}%"),
            Activity.special_needs_adaptations.ilike(f"%{search_term}%"),
        )
    )
)
```

## Performance Characteristics

### PostgreSQL tsvector Search

- **Index Type**: GIN (Generalized Inverted Index)
- **Search Complexity**: O(log n) for index lookup
- **Space Overhead**: ~30-50% of text data size for index
- **Update Performance**: Trigger-based, automatic maintenance

**Benefits:**
- Fast searches even with millions of records
- Automatic stemming and stop word handling
- Relevance ranking based on term frequency and position
- Language-aware tokenization

### SQLite ILIKE Fallback

- **Search Complexity**: O(n) - full table scan
- **Use Case**: Testing only, not suitable for production

## Migration

The tsvector functionality is added via Alembic migration:

```bash
# Migration file: 20260217_094417_add_tsvector_search.py
alembic upgrade head
```

**Migration Steps:**
1. Adds `search_vector` column to `activities` table
2. Creates GIN index on `search_vector`
3. Creates trigger function for automatic updates
4. Creates trigger to call function on INSERT/UPDATE
5. Populates existing rows with search vectors

## Usage Examples

### Basic Search

```python
from app.services.search_service import SearchService

# Initialize service
search_service = SearchService(db)

# Search for activities
results, total = await search_service.search_activities(
    query="building blocks",
    skip=0,
    limit=20
)

# Results are automatically ranked by relevance
for result in results:
    print(f"{result.title}: {result.relevance_score}")
```

### Search API Endpoint

```bash
# Search for activities containing "blocks"
GET /api/v1/search?q=blocks&types=activities

# Response includes relevance scores
{
  "items": [
    {
      "type": "activity",
      "title": "Building Blocks Tower",
      "relevance_score": 0.95,
      ...
    }
  ],
  "total": 15,
  "page": 1,
  "per_page": 20
}
```

## Testing

The implementation includes comprehensive tests that work with both PostgreSQL and SQLite:

```bash
# Run search tests
pytest tests/test_search.py -v

# All 20 search tests should pass
```

**Test Coverage:**
- Basic search functionality
- Case-insensitive matching
- Relevance score ordering
- Pagination
- Empty results
- Special character handling
- Authentication requirements

## Best Practices

1. **Query Formatting**
   - Use natural language queries (no special syntax required)
   - Avoid very short queries (< 2 characters)
   - Maximum query length: 500 characters

2. **Performance Optimization**
   - The GIN index automatically handles optimization
   - Trigger-based updates keep search vectors current
   - No manual maintenance required

3. **Relevance Tuning**
   - Adjust weights in the trigger function to change ranking
   - Current weights: A=1.0, B=0.4, C=0.2
   - Higher weights = higher relevance for matches

4. **Language Support**
   - Currently configured for English
   - Can be changed by modifying 'english' parameter in trigger
   - Supported languages: english, spanish, french, german, etc.

## Maintenance

### Monitoring

```sql
-- Check index usage
SELECT schemaname, tablename, indexname, idx_scan
FROM pg_stat_user_indexes
WHERE indexname = 'ix_activities_search_vector';

-- Check index size
SELECT pg_size_pretty(pg_relation_size('ix_activities_search_vector'));
```

### Rebuilding Index

If needed, rebuild the index:

```sql
-- Reindex
REINDEX INDEX ix_activities_search_vector;

-- Or rebuild all activity search vectors
UPDATE activities
SET search_vector =
    setweight(to_tsvector('english', COALESCE(name, '')), 'A') ||
    setweight(to_tsvector('english', COALESCE(description, '')), 'B') ||
    setweight(to_tsvector('english', COALESCE(special_needs_adaptations, '')), 'C');
```

## References

- [PostgreSQL Full Text Search Documentation](https://www.postgresql.org/docs/current/textsearch.html)
- [PostgreSQL ts_rank Function](https://www.postgresql.org/docs/current/textsearch-controls.html#TEXTSEARCH-RANKING)
- [GIN Index Performance](https://www.postgresql.org/docs/current/gin-implementation.html)
