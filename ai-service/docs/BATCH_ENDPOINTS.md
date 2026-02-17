# Batch Endpoints Guide

## Overview

Batch endpoints allow clients to perform multiple API operations in a single HTTP request, significantly reducing network round-trips and improving application performance. This is particularly valuable for:

- **Mobile applications** with limited bandwidth
- **Dashboard views** loading data for multiple resources
- **Classroom interfaces** displaying information for multiple children
- **Bulk operations** on multiple resources

## Benefits

### Performance Improvements

- **Reduced Round-Trips**: One request instead of N requests
- **Lower Latency**: Avoid multiple TCP handshakes and SSL negotiations
- **Better Throughput**: Server can optimize batch processing
- **Network Efficiency**: Reduced overhead from HTTP headers

### Example Performance Gains

| Scenario | Individual Requests | Batch Request | Improvement |
|----------|-------------------|---------------|-------------|
| 10 activities | 10 × 150ms = 1500ms | 250ms | **83% faster** |
| 20 children recommendations | 20 × 200ms = 4000ms | 450ms | **89% faster** |
| 50 resources | 50 × 150ms = 7500ms | 600ms | **92% faster** |

## Available Batch Endpoints

### 1. Batch GET - Fetch Multiple Resources

Retrieve multiple resources by ID in a single request.

**Endpoint**: `POST /api/v1/batch/get`

**Supported Resource Types**:
- `activities` - Educational activities

**Request Schema**:
```json
{
  "resource_type": "activities",
  "ids": ["uuid1", "uuid2", "uuid3"],
  "fields": "id,name,description"  // Optional field selection
}
```

**Response Schema**:
```json
{
  "resource_type": "activities",
  "results": [
    {
      "id": "uuid1",
      "status": "success",
      "data": { /* activity data */ },
      "status_code": 200
    },
    {
      "id": "uuid2",
      "status": "error",
      "error": "Activity with id uuid2 not found",
      "status_code": 404
    }
  ],
  "total_requested": 3,
  "total_succeeded": 1,
  "total_failed": 1,
  "processed_at": "2026-02-16T20:00:00Z"
}
```

**Features**:
- Partial success: Returns results for all requested IDs
- Field selection: Use `fields` parameter to reduce payload
- Error handling: Each result has its own status
- Limits: Max 100 IDs per request

**Example Usage**:
```python
import httpx

# Fetch multiple activities
response = httpx.post(
    "http://localhost:8000/api/v1/batch/get",
    json={
        "resource_type": "activities",
        "ids": [
            "123e4567-e89b-12d3-a456-426614174000",
            "223e4567-e89b-12d3-a456-426614174001",
            "323e4567-e89b-12d3-a456-426614174002"
        ],
        "fields": "id,name,activity_type"
    },
    headers={"Authorization": f"Bearer {token}"}
)

data = response.json()
for result in data["results"]:
    if result["status"] == "success":
        print(f"Activity: {result['data']['name']}")
    else:
        print(f"Error: {result['error']}")
```

### 2. Batch Activity Recommendations

Get personalized activity recommendations for multiple children in one request.

**Endpoint**: `POST /api/v1/batch/activities/recommendations`

**Request Schema**:
```json
{
  "child_ids": ["child-uuid1", "child-uuid2"],
  "max_recommendations": 5,
  "activity_types": ["cognitive", "motor"],  // Optional
  "child_age_months": 36,  // Optional
  "weather": "sunny",  // Optional
  "group_size": 10,  // Optional
  "include_special_needs": true
}
```

**Response Schema**:
```json
{
  "results": [
    {
      "id": "child-uuid1",
      "status": "success",
      "data": {
        "child_id": "child-uuid1",
        "recommendations": [
          {
            "activity": { /* activity data */ },
            "relevance_score": 0.85,
            "reasoning": "Perfect age match; Fresh activity suggestion"
          }
        ],
        "generated_at": "2026-02-16T20:00:00Z"
      },
      "status_code": 200
    }
  ],
  "total_requested": 2,
  "total_succeeded": 2,
  "total_failed": 0,
  "processed_at": "2026-02-16T20:00:00Z"
}
```

**Features**:
- Batch recommendations for up to 50 children
- Same filters applied to all children
- Partial success: Returns results for all children
- Personalized scoring per child

**Example Usage**:
```python
# Get recommendations for classroom (10 children)
response = httpx.post(
    "http://localhost:8000/api/v1/batch/activities/recommendations",
    json={
        "child_ids": [
            "child-uuid-1",
            "child-uuid-2",
            "child-uuid-3",
            # ... up to 50 children
        ],
        "max_recommendations": 5,
        "weather": "sunny",
        "group_size": 10
    },
    headers={"Authorization": f"Bearer {token}"}
)

data = response.json()
for result in data["results"]:
    child_id = result["id"]
    if result["status"] == "success":
        recommendations = result["data"]["recommendations"]
        print(f"Child {child_id}: {len(recommendations)} recommendations")
```

## Request Limits

| Parameter | Limit | Reason |
|-----------|-------|--------|
| `ids` (batch GET) | 100 | Prevent excessive memory usage |
| `child_ids` (recommendations) | 50 | Balance performance vs. payload size |
| Request timeout | 30 seconds | Standard API timeout |
| Payload size | 10 MB | Standard FastAPI limit |

## Error Handling

### Partial Success

Batch endpoints support **partial success** - they return results for all requested items, marking failures individually:

```json
{
  "results": [
    {"id": "uuid1", "status": "success", "data": {...}},
    {"id": "uuid2", "status": "error", "error": "Not found"},
    {"id": "uuid3", "status": "success", "data": {...}}
  ],
  "total_succeeded": 2,
  "total_failed": 1
}
```

### Error Status Codes

- **200 OK**: Batch processed (even if some items failed)
- **400 Bad Request**: Invalid resource type or malformed request
- **401 Unauthorized**: Missing or invalid authentication
- **422 Unprocessable Entity**: Validation error (e.g., too many IDs)
- **500 Internal Server Error**: Server error processing batch

### Best Practices

1. **Check individual result status**: Don't assume all succeeded
2. **Handle partial failures gracefully**: Update UI for successful items
3. **Retry failed items**: Extract failed IDs and retry if needed
4. **Log errors**: Track which items failed and why

## Integration Examples

### React/TypeScript Frontend

```typescript
interface BatchGetRequest {
  resource_type: string;
  ids: string[];
  fields?: string;
}

interface BatchResult<T> {
  id: string;
  status: 'success' | 'error';
  data?: T;
  error?: string;
  status_code: number;
}

async function batchGetActivities(
  activityIds: string[],
  token: string
): Promise<Activity[]> {
  const response = await fetch('/api/v1/batch/get', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${token}`
    },
    body: JSON.stringify({
      resource_type: 'activities',
      ids: activityIds,
      fields: 'id,name,description,activity_type'
    })
  });

  const data = await response.json();

  // Extract successful results
  return data.results
    .filter((r: BatchResult<Activity>) => r.status === 'success')
    .map((r: BatchResult<Activity>) => r.data!);
}
```

### Python Client

```python
from typing import List, Dict, Any
import httpx

class BatchClient:
    def __init__(self, base_url: str, token: str):
        self.base_url = base_url
        self.token = token
        self.headers = {"Authorization": f"Bearer {token}"}

    async def batch_get_activities(
        self,
        activity_ids: List[str],
        fields: str = None
    ) -> List[Dict[str, Any]]:
        """Fetch multiple activities in batch."""
        response = await httpx.AsyncClient().post(
            f"{self.base_url}/api/v1/batch/get",
            json={
                "resource_type": "activities",
                "ids": activity_ids,
                "fields": fields
            },
            headers=self.headers
        )
        response.raise_for_status()

        data = response.json()

        # Return only successful results
        return [
            result["data"]
            for result in data["results"]
            if result["status"] == "success"
        ]

    async def batch_recommendations(
        self,
        child_ids: List[str],
        max_recommendations: int = 5
    ) -> Dict[str, List[Dict[str, Any]]]:
        """Get recommendations for multiple children."""
        response = await httpx.AsyncClient().post(
            f"{self.base_url}/api/v1/batch/activities/recommendations",
            json={
                "child_ids": child_ids,
                "max_recommendations": max_recommendations
            },
            headers=self.headers
        )
        response.raise_for_status()

        data = response.json()

        # Return recommendations by child_id
        return {
            result["id"]: result["data"]["recommendations"]
            for result in data["results"]
            if result["status"] == "success"
        }
```

## Performance Tips

### 1. Use Field Selection

Reduce payload size by requesting only needed fields:

```json
{
  "resource_type": "activities",
  "ids": [...],
  "fields": "id,name,activity_type"  // Only essential fields
}
```

**Savings**: 50-70% payload reduction for typical resources

### 2. Batch Size Optimization

Find the optimal batch size for your use case:

- **Mobile networks**: 10-20 items (balance latency vs. reliability)
- **Desktop/WiFi**: 50-100 items (maximize throughput)
- **Server-to-server**: 100 items (maximum batch size)

### 3. Parallel Batching

For very large datasets, use parallel batch requests:

```python
import asyncio

async def fetch_all_activities(activity_ids: List[str]) -> List[Activity]:
    # Split into batches of 100
    batches = [activity_ids[i:i+100] for i in range(0, len(activity_ids), 100)]

    # Fetch all batches in parallel
    tasks = [batch_get_activities(batch) for batch in batches]
    results = await asyncio.gather(*tasks)

    # Flatten results
    return [activity for batch_result in results for activity in batch_result]
```

### 4. Caching

Combine batch endpoints with caching:

```typescript
const activityCache = new Map<string, Activity>();

async function getCachedActivities(ids: string[]): Promise<Activity[]> {
  // Check cache first
  const uncached = ids.filter(id => !activityCache.has(id));

  if (uncached.length === 0) {
    return ids.map(id => activityCache.get(id)!);
  }

  // Fetch uncached via batch
  const fetched = await batchGetActivities(uncached);

  // Update cache
  fetched.forEach(activity => {
    activityCache.set(activity.id, activity);
  });

  // Return all (cached + fetched)
  return ids.map(id => activityCache.get(id)!);
}
```

## Testing

### Unit Tests

```python
@pytest.mark.asyncio
async def test_batch_get_activities(client, authenticated_headers, sample_activities):
    """Test batch GET endpoint."""
    activity_ids = [str(a.id) for a in sample_activities]

    response = await client.post(
        "/api/v1/batch/get",
        json={
            "resource_type": "activities",
            "ids": activity_ids
        },
        headers=authenticated_headers
    )

    assert response.status_code == 200
    data = response.json()
    assert data["total_succeeded"] == len(sample_activities)
```

### Integration Tests

```python
@pytest.mark.integration
async def test_batch_recommendations_integration():
    """Test batch recommendations with real data."""
    # Create test children and activities
    child_ids = await create_test_children(count=10)
    await create_test_activities(count=20)

    # Fetch batch recommendations
    response = await client.post(
        "/api/v1/batch/activities/recommendations",
        json={"child_ids": child_ids, "max_recommendations": 5}
    )

    assert response.status_code == 200
    data = response.json()
    assert data["total_succeeded"] == 10
```

## Migration Guide

### Converting Individual Requests to Batch

**Before** (Individual requests):
```typescript
// Fetch 10 activities individually
const activities = await Promise.all(
  activityIds.map(id => fetchActivity(id))
);
// Time: ~1500ms (10 × 150ms)
```

**After** (Batch request):
```typescript
// Fetch 10 activities in batch
const activities = await batchGetActivities(activityIds);
// Time: ~250ms (83% faster!)
```

### Gradual Migration

1. **Start with high-traffic endpoints**: Migrate most-used API calls first
2. **Keep backward compatibility**: Support both individual and batch
3. **Monitor performance**: Track latency and error rates
4. **Optimize batch sizes**: Tune based on actual usage patterns

## Troubleshooting

### Common Issues

**Issue**: Batch request timing out
- **Solution**: Reduce batch size or increase timeout
- **Recommended**: Use 50 items for recommendations, 100 for GET

**Issue**: High failure rate in batch results
- **Solution**: Validate IDs before batching, handle individual errors
- **Prevention**: Implement retry logic for failed items

**Issue**: Large response payload
- **Solution**: Use field selection to reduce data size
- **Example**: `fields: "id,name"` instead of all fields

## Security Considerations

1. **Authentication Required**: All batch endpoints require JWT authentication
2. **Rate Limiting**: Standard rate limits apply to batch endpoints
3. **Authorization**: Each item checked against user permissions
4. **Input Validation**: Batch size limits prevent abuse

## Future Enhancements

Planned improvements for batch endpoints:

- [ ] Batch UPDATE operations
- [ ] Batch DELETE operations
- [ ] More resource types (coaching, analytics)
- [ ] Webhook notifications for long-running batches
- [ ] Progress tracking for large batches

## Related Documentation

- [Field Selection Guide](./FIELD_SELECTION.md) - Optimize response payloads
- [API Performance Best Practices](./API_PERFORMANCE.md) - General optimization tips
- [Authentication Guide](./AUTHENTICATION.md) - JWT token usage

## Support

For questions or issues with batch endpoints:
- Review test cases in `tests/test_batch.py`
- Check FastAPI docs at `/docs` (Swagger UI)
- File issues in the project repository
