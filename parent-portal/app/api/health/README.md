# Parent Portal Health Check Endpoint

## Overview

The Parent Portal health check endpoint provides comprehensive monitoring of the portal's critical dependencies and overall service health.

## Endpoint

```
GET /api/health
```

## Response Format

### Healthy Response (200 OK)

```json
{
  "status": "healthy",
  "timestamp": "2024-02-15T10:30:00.000Z",
  "service": "parent-portal",
  "version": "0.1.0",
  "checks": {
    "aiService": {
      "status": "healthy",
      "connected": true,
      "responseTime": 1708000000000,
      "apiUrl": "http://localhost:8000",
      "details": {
        "status": "healthy",
        "service": "ai-service",
        "version": "0.1.0",
        "checks": {
          "database": { "status": "healthy" },
          "redis": { "status": "healthy" }
        }
      }
    },
    "gibbon": {
      "status": "healthy",
      "connected": true,
      "responseTime": 1708000000000,
      "gibbonUrl": "http://localhost:8080/gibbon",
      "details": {
        "status": "healthy",
        "service": "gibbon"
      }
    }
  }
}
```

### Degraded Response (200 OK)

When one or more services return HTTP errors but are reachable:

```json
{
  "status": "degraded",
  "timestamp": "2024-02-15T10:30:00.000Z",
  "service": "parent-portal",
  "version": "0.1.0",
  "checks": {
    "aiService": {
      "status": "degraded",
      "connected": false,
      "error": "HTTP 500: Internal Server Error",
      "apiUrl": "http://localhost:8000"
    },
    "gibbon": {
      "status": "healthy",
      "connected": true,
      "gibbonUrl": "http://localhost:8080/gibbon"
    }
  }
}
```

### Unhealthy Response (503 Service Unavailable)

When one or more critical services are unreachable:

```json
{
  "status": "unhealthy",
  "timestamp": "2024-02-15T10:30:00.000Z",
  "service": "parent-portal",
  "version": "0.1.0",
  "checks": {
    "aiService": {
      "status": "unhealthy",
      "connected": false,
      "error": "Connection refused",
      "apiUrl": "http://localhost:8000"
    },
    "gibbon": {
      "status": "unhealthy",
      "connected": false,
      "error": "Network error",
      "gibbonUrl": "http://localhost:8080/gibbon"
    }
  }
}
```

## Health Checks Performed

### 1. AI Service Connectivity
- **Check**: HTTP GET to `${NEXT_PUBLIC_API_URL}/api/v1/health`
- **Timeout**: 5 seconds
- **Critical**: Yes
- **Status Mapping**:
  - `healthy`: Service responds with 200 OK
  - `degraded`: Service responds with non-200 status
  - `unhealthy`: Service unreachable or timeout

### 2. Gibbon CMS Connectivity
- **Check**: HTTP GET to `${NEXT_PUBLIC_GIBBON_URL}/modules/System/health.php`
- **Timeout**: 5 seconds
- **Critical**: Yes
- **Status Mapping**:
  - `healthy`: Service responds with 200 OK
  - `degraded`: Service responds with non-200 status
  - `unhealthy`: Service unreachable or timeout

## Overall Status Logic

The overall status is determined by:

1. **healthy**: All critical services are healthy
2. **degraded**: One or more services are degraded (reachable but returning errors)
3. **unhealthy**: One or more services are unhealthy (unreachable)

## Environment Variables

```bash
# AI Service API URL (default: http://localhost:8000)
NEXT_PUBLIC_API_URL=http://localhost:8000

# Gibbon CMS URL (default: http://localhost:8080/gibbon)
NEXT_PUBLIC_GIBBON_URL=http://localhost:8080/gibbon
```

## Usage Examples

### cURL

```bash
# Check health status
curl http://localhost:3000/api/health

# Check health with formatted output
curl -s http://localhost:3000/api/health | jq .

# Check only the overall status
curl -s http://localhost:3000/api/health | jq -r .status
```

### Monitoring Integration

```bash
#!/bin/bash
# Simple uptime monitoring script

HEALTH_URL="http://localhost:3000/api/health"
STATUS=$(curl -s $HEALTH_URL | jq -r .status)

if [ "$STATUS" = "healthy" ]; then
  echo "✓ Parent Portal is healthy"
  exit 0
elif [ "$STATUS" = "degraded" ]; then
  echo "⚠ Parent Portal is degraded"
  exit 1
else
  echo "✗ Parent Portal is unhealthy"
  exit 2
fi
```

### Docker Compose Healthcheck

```yaml
services:
  parent-portal:
    image: laya/parent-portal:latest
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:3000/api/health"]
      interval: 30s
      timeout: 10s
      retries: 3
      start_period: 40s
```

### Kubernetes Liveness/Readiness Probes

```yaml
apiVersion: v1
kind: Pod
metadata:
  name: parent-portal
spec:
  containers:
  - name: parent-portal
    image: laya/parent-portal:latest
    livenessProbe:
      httpGet:
        path: /api/health
        port: 3000
      initialDelaySeconds: 30
      periodSeconds: 10
      timeoutSeconds: 5
      failureThreshold: 3
    readinessProbe:
      httpGet:
        path: /api/health
        port: 3000
      initialDelaySeconds: 10
      periodSeconds: 5
      timeoutSeconds: 3
      successThreshold: 1
      failureThreshold: 2
```

## Testing

Run the comprehensive test suite:

```bash
npm test -- __tests__/api-health.test.ts
```

Test coverage includes:
- All services healthy
- Individual service degraded/unhealthy scenarios
- Both services down
- Timeout handling
- Default environment variable fallback
- Error handling
- Response format validation

## Implementation Details

- **Framework**: Next.js 14 App Router API Routes
- **Language**: TypeScript
- **Testing**: Vitest with comprehensive test coverage
- **Error Handling**: All network errors caught and returned with proper status codes
- **Timeout**: 5-second timeout per service check to prevent hanging
- **Parallel Checks**: All service checks run concurrently for optimal performance

## Related Endpoints

- [AI Service Health](../../../ai-service/app/routers/health.py): `/api/v1/health`
- [Gibbon Health](../../../gibbon/modules/System/health.php): `/modules/System/health.php`
