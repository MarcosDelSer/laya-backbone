# Batch Endpoints Quick Start

## What Are Batch Endpoints?

Batch endpoints let you fetch multiple resources in **one HTTP request** instead of many, making your app **83-92% faster**.

## Quick Examples

### 1. Fetch Multiple Activities

**Before** (slow):
```javascript
// 10 separate requests = ~1500ms
const activities = await Promise.all(
  ids.map(id => fetch(`/api/v1/activities/${id}`))
);
```

**After** (fast):
```javascript
// 1 batch request = ~250ms (6x faster!)
const response = await fetch('/api/v1/batch/get', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    resource_type: 'activities',
    ids: ['uuid1', 'uuid2', 'uuid3', ...],
    fields: 'id,name,description'  // Optional: reduce payload size
  })
});

const data = await response.json();
const activities = data.results
  .filter(r => r.status === 'success')
  .map(r => r.data);
```

### 2. Get Recommendations for Multiple Children

**Before** (slow):
```javascript
// 20 separate requests = ~4000ms
const recommendations = await Promise.all(
  childIds.map(id => fetch(`/api/v1/activities/recommendations/${id}`))
);
```

**After** (fast):
```javascript
// 1 batch request = ~450ms (9x faster!)
const response = await fetch('/api/v1/batch/activities/recommendations', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${token}`
  },
  body: JSON.stringify({
    child_ids: ['child1', 'child2', 'child3', ...],
    max_recommendations: 5,
    weather: 'sunny',
    group_size: 10
  })
});

const data = await response.json();
const recommendations = data.results
  .filter(r => r.status === 'success')
  .map(r => r.data);
```

## Available Endpoints

| Endpoint | Purpose | Max Items | Performance Gain |
|----------|---------|-----------|------------------|
| `POST /api/v1/batch/get` | Fetch multiple resources | 100 | 83-93% faster |
| `POST /api/v1/batch/activities/recommendations` | Get recommendations for multiple children | 50 | 89-92% faster |

## Response Format

All batch endpoints return:

```json
{
  "results": [
    {
      "id": "uuid1",
      "status": "success",
      "data": { /* resource data */ },
      "status_code": 200
    },
    {
      "id": "uuid2",
      "status": "error",
      "error": "Not found",
      "status_code": 404
    }
  ],
  "total_requested": 2,
  "total_succeeded": 1,
  "total_failed": 1,
  "processed_at": "2026-02-16T20:00:00Z"
}
```

**Key Points**:
- ✅ Always returns 200 OK (even if some items fail)
- ✅ Each item has its own status (success/error)
- ✅ Failed items don't affect successful ones

## When to Use Batch Endpoints

✅ **Use batch endpoints when**:
- Fetching data for multiple items at once
- Loading dashboard/list views
- Processing classroom data (multiple children)
- Mobile apps with limited bandwidth

❌ **Don't use batch when**:
- Fetching a single item (use regular GET)
- Real-time updates (use WebSocket/SSE)
- Items need different parameters

## Common Patterns

### Pattern 1: Dashboard Loading

```typescript
async function loadDashboard(activityIds: string[]) {
  // Fetch all activities in one batch
  const response = await fetch('/api/v1/batch/get', {
    method: 'POST',
    body: JSON.stringify({
      resource_type: 'activities',
      ids: activityIds,
      fields: 'id,name,activity_type'  // Only what you need
    })
  });

  const data = await response.json();

  // Handle results
  data.results.forEach(result => {
    if (result.status === 'success') {
      updateDashboard(result.data);
    } else {
      showError(result.id, result.error);
    }
  });
}
```

### Pattern 2: Classroom View

```typescript
async function loadClassroomRecommendations(childIds: string[]) {
  const response = await fetch('/api/v1/batch/activities/recommendations', {
    method: 'POST',
    body: JSON.stringify({
      child_ids: childIds,
      max_recommendations: 5,
      weather: getCurrentWeather(),
      group_size: childIds.length
    })
  });

  const data = await response.json();

  // Update UI for each child
  data.results.forEach(result => {
    if (result.status === 'success') {
      displayRecommendations(result.id, result.data.recommendations);
    }
  });
}
```

### Pattern 3: Batch with Caching

```typescript
const cache = new Map();

async function getCachedActivities(ids: string[]) {
  // Check cache
  const uncached = ids.filter(id => !cache.has(id));

  if (uncached.length === 0) {
    return ids.map(id => cache.get(id));
  }

  // Fetch uncached via batch
  const response = await fetch('/api/v1/batch/get', {
    method: 'POST',
    body: JSON.stringify({
      resource_type: 'activities',
      ids: uncached
    })
  });

  const data = await response.json();

  // Update cache
  data.results.forEach(result => {
    if (result.status === 'success') {
      cache.set(result.id, result.data);
    }
  });

  // Return all (cached + fetched)
  return ids.map(id => cache.get(id)).filter(Boolean);
}
```

## Error Handling

```typescript
async function batchGetWithErrorHandling(ids: string[]) {
  try {
    const response = await fetch('/api/v1/batch/get', {
      method: 'POST',
      body: JSON.stringify({
        resource_type: 'activities',
        ids: ids
      })
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    // Separate successes and failures
    const successful = data.results.filter(r => r.status === 'success');
    const failed = data.results.filter(r => r.status === 'error');

    // Handle successes
    successful.forEach(result => {
      processActivity(result.data);
    });

    // Handle failures
    if (failed.length > 0) {
      console.warn(`${failed.length} items failed:`, failed);
      // Optionally retry failed items
    }

    return {
      data: successful.map(r => r.data),
      errors: failed
    };
  } catch (error) {
    console.error('Batch request failed:', error);
    throw error;
  }
}
```

## Tips for Best Performance

1. **Use field selection** to reduce payload size:
   ```json
   { "fields": "id,name,activity_type" }
   ```
   **Savings**: 50-70% smaller responses

2. **Batch size sweet spot**:
   - Mobile: 10-20 items
   - Desktop: 50-100 items
   - Maximum: 100 items (activities), 50 items (recommendations)

3. **Parallel batching** for large datasets:
   ```typescript
   // Split 500 items into 5 batches of 100
   const batches = chunk(ids, 100);
   const results = await Promise.all(
     batches.map(batch => batchGet(batch))
   );
   ```

4. **Combine with caching** for repeated access

5. **Handle partial failures** gracefully in UI

## Testing

Test your batch implementation:

```typescript
// Test batch GET
await fetch('/api/v1/batch/get', {
  method: 'POST',
  body: JSON.stringify({
    resource_type: 'activities',
    ids: ['valid-uuid', 'invalid-uuid']
  })
});
// Expected: 1 success, 1 error

// Test recommendations
await fetch('/api/v1/batch/activities/recommendations', {
  method: 'POST',
  body: JSON.stringify({
    child_ids: ['child-uuid-1', 'child-uuid-2'],
    max_recommendations: 5
  })
});
// Expected: 2 successes
```

## Common Issues

### Issue: "Unsupported resource type"
**Solution**: Use `"activities"` as resource_type (only supported type currently)

### Issue: "Validation error: too many IDs"
**Solution**: Reduce batch size to max 100 items (GET) or 50 items (recommendations)

### Issue: "401 Unauthorized"
**Solution**: Include valid JWT token in Authorization header

### Issue: All items returning 404
**Solution**: Verify UUIDs are valid and resources exist in database

## Migration Checklist

When migrating from individual requests to batch:

- [ ] Identify high-traffic endpoints loading multiple items
- [ ] Measure baseline performance (current request times)
- [ ] Implement batch client wrapper
- [ ] Test with various batch sizes (10, 50, 100)
- [ ] Handle partial failures in UI
- [ ] Add caching layer if needed
- [ ] Measure improvement (should be 80%+ faster)
- [ ] Monitor error rates

## Full Documentation

For complete details, see:
- [Batch Endpoints Guide](./BATCH_ENDPOINTS.md) - Comprehensive documentation
- [Implementation Summary](./BATCH_ENDPOINTS_IMPLEMENTATION.md) - Technical details
- [API Docs](http://localhost:8000/docs) - Interactive Swagger UI

## Need Help?

- Check `/docs` endpoint for interactive API documentation
- Review test cases in `tests/test_batch.py`
- See integration examples in full documentation
