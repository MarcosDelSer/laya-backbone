# System Module

System monitoring and health check utilities for Gibbon LAYA.

## Features

### Health Check Endpoint

The health check endpoint (`health.php`) provides comprehensive system health status information for monitoring and alerting purposes.

**URL:** `/modules/System/health.php`

**Response Format:** JSON

**HTTP Status Codes:**
- `200 OK` - System is healthy or degraded (with warnings)
- `503 Service Unavailable` - System is unhealthy or critical

### Health Checks Performed

1. **Database Connection**
   - Tests MySQL connection
   - Retrieves database version
   - Reports active connections

2. **PHP Extensions**
   - Verifies all required PHP extensions are loaded
   - Reports PHP version
   - Lists missing extensions (if any)

   Required extensions:
   - pdo
   - pdo_mysql
   - gd
   - curl
   - zip
   - xml
   - mbstring
   - json
   - openssl
   - fileinfo

3. **Disk Space**
   - Checks available disk space
   - Reports total, used, and free space in bytes and GB
   - Warns when disk usage exceeds 90%
   - Critical when disk usage exceeds 95%

4. **Upload Directory**
   - Verifies upload directory exists
   - Checks read and write permissions

5. **Session Configuration**
   - Verifies PHP sessions are enabled
   - Checks session save path is writable

## Response Format

```json
{
  "status": "healthy",
  "timestamp": "2026-02-17T12:00:00+00:00",
  "checks": {
    "database": {
      "status": "healthy",
      "message": "Database connection successful",
      "details": {
        "version": "8.0.33",
        "connections": "5"
      }
    },
    "php_extensions": {
      "status": "healthy",
      "message": "All required PHP extensions loaded",
      "details": {
        "php_version": "8.1.0",
        "loaded_extensions": ["pdo", "pdo_mysql", "gd", ...],
        "total_loaded": 10
      }
    },
    "disk_space": {
      "status": "healthy",
      "message": "Disk space is adequate",
      "details": {
        "total_gb": 500.0,
        "free_gb": 250.0,
        "used_gb": 250.0,
        "used_percent": 50.0,
        "free_percent": 50.0
      }
    },
    "upload_directory": {
      "status": "healthy",
      "message": "Upload directory accessible",
      "details": {
        "readable": true,
        "writable": true
      }
    },
    "session": {
      "status": "healthy",
      "message": "Session configuration is valid",
      "details": {
        "writable": true
      }
    }
  }
}
```

## Status Levels

- **healthy** - All checks passed
- **degraded** - One or more warnings (e.g., disk usage > 90%)
- **unhealthy** - One or more checks failed
- **critical** - Critical issues detected (e.g., disk usage > 95%)

## Usage

### Direct Access

```bash
curl http://gibbon.local/modules/System/health.php
```

### Docker Healthcheck

```yaml
healthcheck:
  test: ["CMD", "curl", "-f", "http://localhost/modules/System/health.php"]
  interval: 30s
  timeout: 10s
  retries: 3
  start_period: 40s
```

### Monitoring Integration

The endpoint can be integrated with monitoring systems like:
- Prometheus
- Datadog
- New Relic
- Custom monitoring scripts

## Security

The health endpoint is designed to be accessible without authentication for monitoring purposes. However, it does not expose sensitive information like credentials or user data.

## Version

Version: 1.0.00
Author: LAYA
