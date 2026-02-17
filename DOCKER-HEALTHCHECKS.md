# Docker Healthcheck Configuration

This document describes the Docker Compose healthcheck configuration for all LAYA services.

## Overview

All services in the LAYA stack have comprehensive healthcheck configurations to ensure:
- Services start in the correct order
- Dependencies are ready before dependent services start
- Container orchestrators (Docker, Kubernetes) can monitor service health
- Automatic restart of unhealthy containers

## Healthcheck Endpoints

### AI Service (FastAPI)
- **Endpoint**: `http://localhost:8000/api/v1/health/liveness`
- **Method**: GET
- **Checks**:
  - Database connectivity (PostgreSQL)
  - Redis cache connectivity
  - Disk space usage (warning at 90%)
  - Memory usage
- **Response Codes**:
  - `200 OK` - All checks pass
  - `503 Service Unavailable` - One or more critical checks fail

**Healthcheck Configuration**:
```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost:8000/api/v1/health/liveness"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 40s
```

### Gibbon CMS (PHP)
- **Endpoint**: `http://localhost:80/modules/System/health.php`
- **Method**: GET
- **Checks**:
  - MySQL database connectivity
  - Required PHP extensions (10 extensions)
  - Disk space usage (warning at 90%, critical at 95%)
  - Upload directory permissions
  - Session configuration
- **Response Codes**:
  - `200 OK` - Service healthy or degraded
  - `503 Service Unavailable` - Service unhealthy or critical

**Healthcheck Configuration**:
```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost:80/modules/System/health.php"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 60s
```

### Parent Portal (Next.js)
- **Endpoint**: `http://localhost:3000/api/health`
- **Method**: GET
- **Checks**:
  - Build version information
  - AI Service connectivity
  - Gibbon CMS connectivity
- **Response Codes**:
  - `200 OK` - All services reachable
  - `503 Service Unavailable` - One or more services unreachable

**Healthcheck Configuration**:
```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost:3000/api/health"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 60s
```

### PostgreSQL Database
- **Healthcheck**: `pg_isready -U laya_user -d laya_db`
- **Interval**: 10s
- **Timeout**: 5s
- **Retries**: 5
- **Start Period**: 10s

### MySQL Database (Gibbon)
- **Healthcheck**: `mysqladmin ping`
- **Interval**: 10s
- **Timeout**: 5s
- **Retries**: 5
- **Start Period**: 30s

### Redis Cache
- **Healthcheck**: `redis-cli ping`
- **Interval**: 10s
- **Timeout**: 3s
- **Retries**: 5
- **Start Period**: 5s

## Healthcheck Parameters Explained

### `test`
The command to execute for the health check. If it returns 0, the container is healthy.

### `interval`
Time between running health checks. Default: 30s for application services, 10s for databases.

### `timeout`
Maximum time allowed for the health check to complete. If exceeded, the check is considered failed.

### `retries`
Number of consecutive failures needed to consider the container unhealthy.

### `start_period`
Grace period during which health check failures are not counted toward the maximum retries. This gives services time to start up.

## Service Dependencies

The `depends_on` configuration ensures services start in the correct order:

```
postgres → ai-service → parent-portal
mysql → gibbon → parent-portal
redis → ai-service
```

### Example Dependency Configuration:
```yaml
parent-portal:
  depends_on:
    ai-service:
      condition: service_healthy
    gibbon:
      condition: service_healthy
```

This ensures Parent Portal only starts after AI Service and Gibbon are healthy.

## Usage

### Starting All Services
```bash
# Start all services with health checks
docker-compose up -d

# View service status
docker-compose ps

# Watch logs for health check results
docker-compose logs -f
```

### Checking Service Health
```bash
# Check overall health status
docker-compose ps

# Inspect specific service health
docker inspect --format='{{json .State.Health}}' laya-ai-service | jq

# View health check logs
docker inspect laya-ai-service | jq '.[0].State.Health'
```

### Testing Health Endpoints Manually
```bash
# AI Service
curl -i http://localhost:8000/api/v1/health/liveness

# Gibbon CMS
curl -i http://localhost:8080/modules/System/health.php

# Parent Portal
curl -i http://localhost:3000/api/health
```

## Health Status States

Docker Compose recognizes three health states:

1. **starting** - Container is starting, within the `start_period`
2. **healthy** - Health check is passing
3. **unhealthy** - Health check has failed `retries` consecutive times

## Automatic Recovery

When a container becomes unhealthy:
- Docker logs the health check failure
- After `retries` consecutive failures, the container is marked unhealthy
- With `restart: unless-stopped`, Docker will restart the container
- The container goes through the startup grace period again

## Monitoring Integration

### Prometheus
Health endpoints return JSON that can be scraped by Prometheus exporters.

### Docker Swarm
```yaml
deploy:
  replicas: 3
  update_config:
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8000/api/v1/health/liveness"]
```

### Kubernetes
Convert healthchecks to Kubernetes liveness and readiness probes:

```yaml
livenessProbe:
  httpGet:
    path: /api/v1/health/liveness
    port: 8000
  initialDelaySeconds: 40
  periodSeconds: 30
  timeoutSeconds: 10
  failureThreshold: 3

readinessProbe:
  httpGet:
    path: /api/v1/health/readiness
    port: 8000
  initialDelaySeconds: 20
  periodSeconds: 10
  timeoutSeconds: 5
  failureThreshold: 3
```

## Troubleshooting

### Container Stuck in "starting" State
- Increase `start_period` to give the service more time to start
- Check service logs: `docker-compose logs <service-name>`
- Verify dependencies are healthy: `docker-compose ps`

### Health Check Always Failing
- Test the health endpoint manually: `curl http://localhost:<port>/<health-path>`
- Verify the service is actually running: `docker-compose exec <service> ps aux`
- Check if firewall/network issues: `docker-compose exec <service> curl localhost:<port>`

### Slow Health Checks
- Reduce `interval` if checks complete quickly
- Increase `timeout` if checks legitimately take time
- Optimize health check endpoint to be faster

### Dependencies Not Starting
- Check if required environment variables are set
- Verify volumes are accessible
- Review Docker Compose logs for errors

## Best Practices

1. **Keep health checks lightweight** - They run frequently, so avoid expensive operations
2. **Use appropriate intervals** - Balance between fast detection and resource usage
3. **Set realistic timeouts** - Account for network latency and service response time
4. **Configure adequate start periods** - Give services enough time to initialize
5. **Test health endpoints** - Verify they work correctly in isolation
6. **Monitor health check logs** - Watch for patterns of intermittent failures
7. **Use readiness vs liveness** - Readiness checks can be more comprehensive

## Environment Variables

Create a `.env` file in the project root:

```bash
# Database Passwords
POSTGRES_PASSWORD=secure_password_here
MYSQL_ROOT_PASSWORD=secure_root_password_here
MYSQL_PASSWORD=secure_mysql_password_here

# Environment
ENVIRONMENT=production
NODE_ENV=production
```

## Related Documentation

- [AI Service Health Endpoint](./ai-service/README.md#health-checks)
- [Gibbon Health Module](./gibbon/modules/System/README.md)
- [Parent Portal Health API](./parent-portal/app/api/health/README.md)
- [Uptime Monitoring Script](./scripts/uptime_monitoring.py)
- [Health Dashboard](./parent-portal/app/health/README.md)

## Support

For issues with health checks:
1. Check service logs: `docker-compose logs <service>`
2. Test health endpoints manually
3. Verify network connectivity between containers
4. Review environment variable configuration
5. Consult service-specific documentation

---

**Last Updated**: 2026-02-17
**Version**: 1.0.0
**Task**: 041-3-1 - Docker-compose healthcheck configuration
