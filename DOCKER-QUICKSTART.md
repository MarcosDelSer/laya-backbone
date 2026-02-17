# Docker Quick Start Guide

This guide helps you get the LAYA stack running with Docker Compose including comprehensive health checks.

## Prerequisites

- Docker Engine 20.10+ installed
- Docker Compose 2.0+ installed
- At least 4GB RAM available
- At least 10GB disk space

## Quick Start

### 1. Configure Environment Variables

```bash
# Copy the example environment file
cp .env.example .env

# Edit .env and set secure passwords
nano .env  # or use your preferred editor
```

**Important**: Change all default passwords in the `.env` file before running in production!

### 2. Start All Services

```bash
# Start all services in detached mode
docker-compose up -d

# View startup progress
docker-compose logs -f
```

### 3. Check Service Health

```bash
# View service status (wait for all to show "healthy")
docker-compose ps

# Expected output:
# NAME                STATUS                    PORTS
# laya-ai-service     Up (healthy)             0.0.0.0:8000->8000/tcp
# laya-gibbon         Up (healthy)             0.0.0.0:8080->80/tcp
# laya-mysql          Up (healthy)             0.0.0.0:3306->3306/tcp
# laya-parent-portal  Up (healthy)             0.0.0.0:3000->3000/tcp
# laya-postgres       Up (healthy)             0.0.0.0:5432->5432/tcp
# laya-redis          Up (healthy)             0.0.0.0:6379->6379/tcp
```

### 4. Access Services

Once all services show as "healthy":

- **Parent Portal**: http://localhost:3000
- **AI Service API**: http://localhost:8000/docs (Swagger UI)
- **Gibbon CMS**: http://localhost:8080/gibbon

### 5. Test Health Endpoints

```bash
# AI Service health check
curl -i http://localhost:8000/api/v1/health/liveness

# Gibbon health check
curl -i http://localhost:8080/modules/System/health.php

# Parent Portal health check
curl -i http://localhost:3000/api/health
```

All should return HTTP 200 OK with JSON health status.

## Understanding Startup Order

Services start in dependency order with health checks:

1. **Infrastructure** (10-30 seconds)
   - PostgreSQL: ~10s to healthy
   - MySQL: ~30s to healthy
   - Redis: ~5s to healthy

2. **Backend Services** (40-60 seconds)
   - AI Service: Waits for PostgreSQL and Redis
   - Gibbon: Waits for MySQL

3. **Frontend** (60-90 seconds)
   - Parent Portal: Waits for AI Service and Gibbon

**Total startup time**: ~90 seconds for all services to be healthy

## Monitoring Health

### Watch Service Status
```bash
# Continuous monitoring
watch -n 2 'docker-compose ps'
```

### View Health Check Logs
```bash
# All services
docker-compose logs -f

# Specific service
docker-compose logs -f ai-service
```

### Detailed Health Inspection
```bash
# AI Service health details
docker inspect --format='{{json .State.Health}}' laya-ai-service | jq

# View last 5 health checks
docker inspect laya-ai-service | jq '.[0].State.Health.Log[-5:]'
```

## Common Operations

### Stop All Services
```bash
docker-compose down
```

### Stop and Remove Volumes
```bash
# WARNING: This deletes all data!
docker-compose down -v
```

### Restart a Single Service
```bash
docker-compose restart ai-service
```

### View Service Logs
```bash
# Real-time logs
docker-compose logs -f ai-service

# Last 100 lines
docker-compose logs --tail=100 ai-service
```

### Rebuild Services
```bash
# Rebuild all services
docker-compose build

# Rebuild specific service
docker-compose build ai-service

# Rebuild and restart
docker-compose up -d --build
```

### Scale Services (if configured)
```bash
# Scale AI Service to 3 instances
docker-compose up -d --scale ai-service=3
```

## Troubleshooting

### Service Stuck in "starting" State

```bash
# Check service logs
docker-compose logs ai-service

# Common causes:
# - Database not ready yet (extend start_period)
# - Missing environment variables
# - Port already in use
# - Application crash on startup
```

### Service Shows "unhealthy"

```bash
# Test health endpoint manually
docker-compose exec ai-service curl http://localhost:8000/api/v1/health/liveness

# Check application logs
docker-compose logs --tail=50 ai-service

# Restart the service
docker-compose restart ai-service
```

### Cannot Connect to Service

```bash
# Verify service is running
docker-compose ps

# Check port mappings
docker-compose port ai-service 8000

# Test from host
curl -v http://localhost:8000/api/v1/health/liveness

# Test from another container
docker-compose exec parent-portal curl http://ai-service:8000/api/v1/health/liveness
```

### Database Connection Errors

```bash
# Verify database is healthy
docker-compose ps postgres

# Check database logs
docker-compose logs postgres

# Test database connection
docker-compose exec postgres psql -U laya_user -d laya_db -c "SELECT 1;"
```

### Performance Issues

```bash
# Check resource usage
docker stats

# Common solutions:
# - Increase Docker memory limit (Docker Desktop -> Resources)
# - Reduce healthcheck interval
# - Check disk space: df -h
```

## Health Check Configuration

Each service has different health check timing:

| Service | Interval | Timeout | Retries | Start Period |
|---------|----------|---------|---------|--------------|
| AI Service | 30s | 10s | 3 | 40s |
| Gibbon | 30s | 10s | 3 | 60s |
| Parent Portal | 30s | 10s | 3 | 60s |
| PostgreSQL | 10s | 5s | 5 | 10s |
| MySQL | 10s | 5s | 5 | 30s |
| Redis | 10s | 3s | 5 | 5s |

See [DOCKER-HEALTHCHECKS.md](./DOCKER-HEALTHCHECKS.md) for detailed explanation.

## Production Deployment

For production deployments:

1. **Set Strong Passwords**
   ```bash
   # Generate secure passwords
   openssl rand -base64 32
   ```

2. **Use Production Environment**
   ```bash
   # In .env file:
   ENVIRONMENT=production
   NODE_ENV=production
   ```

3. **Configure External Volumes**
   - Use named volumes or bind mounts for data persistence
   - Regular backups of database volumes

4. **Enable HTTPS**
   - Add reverse proxy (nginx, Traefik)
   - Configure SSL certificates (Let's Encrypt)

5. **Monitor Health Checks**
   - Set up Prometheus + Grafana
   - Configure alerting for health check failures

6. **Resource Limits**
   ```yaml
   deploy:
     resources:
       limits:
         cpus: '0.50'
         memory: 512M
   ```

## Development Workflow

### Running with Hot Reload

```bash
# Development mode with volume mounts
docker-compose -f docker-compose.yml -f docker-compose.dev.yml up
```

### Running Tests

```bash
# Run AI Service tests
docker-compose exec ai-service pytest

# Run with coverage
docker-compose exec ai-service pytest --cov=app --cov-report=html
```

### Accessing Databases

```bash
# PostgreSQL
docker-compose exec postgres psql -U laya_user -d laya_db

# MySQL
docker-compose exec mysql mysql -u gibbon_user -p gibbon

# Redis
docker-compose exec redis redis-cli
```

## Cleanup

### Remove Stopped Containers
```bash
docker-compose rm -f
```

### Clean Up Unused Resources
```bash
# Remove unused images
docker image prune -a

# Remove unused volumes
docker volume prune

# Remove everything unused
docker system prune -a --volumes
```

## Getting Help

1. Check logs: `docker-compose logs <service>`
2. Review [DOCKER-HEALTHCHECKS.md](./DOCKER-HEALTHCHECKS.md)
3. Verify `.env` configuration
4. Test health endpoints manually
5. Check Docker daemon logs

## Related Documentation

- [Docker Healthcheck Configuration](./DOCKER-HEALTHCHECKS.md)
- [AI Service Documentation](./ai-service/README.md)
- [Gibbon Health Module](./gibbon/modules/System/README.md)
- [Parent Portal API](./parent-portal/app/api/health/README.md)

---

**Version**: 1.0.0
**Last Updated**: 2026-02-17
**Task**: 041-3-1 - Docker-compose healthcheck configuration
