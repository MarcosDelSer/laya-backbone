# Aggregated Health Dashboard

## Overview

The Aggregated Health Dashboard provides real-time monitoring of all LAYA system services, including:

- **Parent Portal** - The Next.js application serving the parent interface
- **AI Service** - The FastAPI backend providing AI-powered features
- **Gibbon CMS** - The PHP-based school management system

## Features

### Real-time Monitoring
- Automatically checks health status of all services every 60 seconds
- Manual refresh option for immediate status updates
- Toggle to enable/disable auto-refresh

### Visual Status Indicators
- **Healthy** (Green) - All systems operating normally
- **Degraded** (Yellow) - Service is running but experiencing issues
- **Unhealthy** (Red) - Service is down or unreachable

### Detailed Service Information
Each service card displays:
- Connection status
- Service URL
- Response time
- Detailed health metrics
- Error messages (if any)
- Version information
- Last check timestamp

## Usage

### Accessing the Dashboard

Navigate to `/health` in your browser:

```bash
http://localhost:3000/health
```

### For Monitoring Integration

The dashboard is designed for both human viewing and integration with monitoring tools:

1. **Visual Monitoring**: Access the dashboard directly in a web browser
2. **Automated Monitoring**: Poll the `/api/health` endpoint programmatically

### API Integration

The dashboard consumes the `/api/health` endpoint:

```bash
# Check overall system health
curl http://localhost:3000/api/health
```

Example response:
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
      "responseTime": 123,
      "apiUrl": "http://localhost:8000",
      "details": {
        "status": "healthy",
        "timestamp": "2024-02-15T10:30:00.000Z"
      }
    },
    "gibbon": {
      "status": "healthy",
      "connected": true,
      "responseTime": 456,
      "gibbonUrl": "http://localhost:8080/gibbon",
      "details": {
        "status": "healthy",
        "timestamp": "2024-02-15T10:30:00.000Z"
      }
    }
  }
}
```

## Components

### HealthDashboardPage
Location: `parent-portal/app/health/page.tsx`

The main dashboard page that:
- Fetches health data from the `/api/health` endpoint
- Displays overall system status
- Shows individual service health cards
- Provides auto-refresh functionality
- Handles loading and error states

### HealthStatusCard
Location: `parent-portal/components/HealthStatusCard.tsx`

A reusable component for displaying service health status:
- Visual status indicators with color coding
- Connection status display
- Error message display
- Service details (version, timestamp, etc.)
- Flexible configuration for different service types

## Status Determination

### Overall Status
The overall system status is determined by the worst status of all critical services:

- **Healthy**: All services are healthy
- **Degraded**: At least one service is degraded (but reachable)
- **Unhealthy**: At least one service is unreachable

### Service-Level Status
Each service reports its own status:

- **AI Service**: Checks database, Redis, disk space, and memory
- **Gibbon CMS**: Checks MySQL, PHP extensions, disk space, and permissions
- **Parent Portal**: Aggregates the status of both AI Service and Gibbon

## Configuration

### Environment Variables

The dashboard uses the following environment variables (defined in `.env.local`):

```bash
# AI Service URL
NEXT_PUBLIC_API_URL=http://localhost:8000

# Gibbon CMS URL
NEXT_PUBLIC_GIBBON_URL=http://localhost:8080/gibbon
```

### Refresh Interval

The auto-refresh interval is set to 60 seconds (60000ms). To modify:

```typescript
// In parent-portal/app/health/page.tsx
const interval = setInterval(() => {
  fetchHealthData();
}, 60000); // Change this value (in milliseconds)
```

## Testing

Run the test suite:

```bash
cd parent-portal
npm test health-dashboard
```

The test suite includes:
- Component rendering tests
- Health status display tests
- Auto-refresh functionality tests
- Manual refresh tests
- Error handling tests
- Loading state tests

## Monitoring Integration

### Uptime Monitoring

The dashboard works seamlessly with uptime monitoring tools:

1. **UptimeRobot**: Poll the `/api/health` endpoint
2. **Datadog**: Use synthetic monitoring to check dashboard accessibility
3. **Prometheus**: Scrape health metrics from the API endpoint
4. **Custom Scripts**: Use the provided uptime monitoring script (Task 041-3-2)

### Alerting

Configure alerts based on the health status:

- **Critical Alert**: Overall status is "unhealthy"
- **Warning Alert**: Overall status is "degraded"
- **Recovery Alert**: Overall status returns to "healthy"

Example monitoring script:

```bash
#!/bin/bash
HEALTH_URL="http://localhost:3000/api/health"

while true; do
  STATUS=$(curl -s $HEALTH_URL | jq -r '.status')

  if [ "$STATUS" == "unhealthy" ]; then
    echo "CRITICAL: System is unhealthy"
    # Send alert notification
  elif [ "$STATUS" == "degraded" ]; then
    echo "WARNING: System is degraded"
    # Send warning notification
  else
    echo "OK: System is healthy"
  fi

  sleep 60
done
```

## Troubleshooting

### Dashboard shows all services as unhealthy

1. Check that all services are running:
   ```bash
   docker-compose ps
   ```

2. Verify environment variables are set correctly in `.env.local`

3. Check service URLs are accessible:
   ```bash
   curl http://localhost:8000/api/v1/health
   curl http://localhost:8080/gibbon/modules/System/health.php
   ```

### Auto-refresh not working

1. Ensure the auto-refresh checkbox is enabled
2. Check browser console for JavaScript errors
3. Verify the `/api/health` endpoint is accessible

### Services show as degraded

1. Check the error message in the service card
2. Review service logs for issues
3. Verify dependencies (database, Redis, etc.) are running

## Related Documentation

- [Parent Portal Health API](../api/health/README.md)
- [AI Service Health Endpoint](../../ai-service/app/routers/health.py)
- [Gibbon Health Module](../../gibbon/modules/System/README.md)
- [Uptime Monitoring Script](../../scripts/uptime_monitoring.py)
- [Docker Compose Health Checks](../../docker-compose.yml)

## Future Enhancements

Potential improvements for future iterations:

1. **Historical Data**: Store and display health metrics over time
2. **Performance Graphs**: Visualize response times and resource usage
3. **Custom Alerts**: Configure email/SMS notifications
4. **Service Dependencies**: Show dependency graph between services
5. **Detailed Metrics**: Drill down into specific service metrics (CPU, memory, etc.)
6. **Export Functionality**: Download health reports as PDF/CSV
