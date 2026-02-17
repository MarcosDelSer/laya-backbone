# Log Rotation Configuration

## Overview

The LAYA AI Service supports automatic log rotation to prevent log files from growing indefinitely and consuming disk space. This feature is essential for production deployments where long-running services generate large volumes of log data.

## Features

### Size-Based Rotation
- Rotates log files when they reach a specified size
- Creates numbered backup files (e.g., `app.log.1`, `app.log.2`)
- Automatically removes oldest backups when backup count is exceeded

### Time-Based Rotation
- Rotates log files at specified time intervals
- Supports multiple rotation schedules: seconds, minutes, hours, days, midnight
- Maintains a configurable number of historical log files

## Configuration

### Environment Variables

```bash
# Enable/disable log rotation
LOG_ROTATION_ENABLED=true

# Rotation type: "size" or "time"
LOG_ROTATION_TYPE=size

# Size-based rotation (when LOG_ROTATION_TYPE=size)
LOG_MAX_BYTES=10485760  # 10 MB in bytes
LOG_BACKUP_COUNT=5      # Keep 5 backup files

# Time-based rotation (when LOG_ROTATION_TYPE=time)
LOG_ROTATION_WHEN=midnight  # "S", "M", "H", "D", or "midnight"
LOG_ROTATION_INTERVAL=1     # Rotate every 1 unit
LOG_BACKUP_COUNT=7          # Keep 7 backup files (e.g., 7 days)

# Log file path
LOG_FILE=/var/log/laya/ai-service.log
```

### Settings Configuration

Configuration can be set in `app/config.py`:

```python
class Settings(BaseSettings):
    # Log rotation configuration
    log_rotation_enabled: bool = True
    log_rotation_type: str = "size"  # "size" or "time"
    log_max_bytes: int = 10 * 1024 * 1024  # 10 MB
    log_backup_count: int = 5
    log_rotation_when: str = "midnight"
    log_rotation_interval: int = 1
```

## Usage Examples

### Size-Based Rotation

```python
from app.core.logging import configure_logging

# Rotate when log file reaches 10 MB, keep 5 backups
configure_logging(
    log_level="INFO",
    json_logs=True,
    log_file="/var/log/laya/ai-service.log",
    rotation_enabled=True,
    rotation_type="size",
    max_bytes=10 * 1024 * 1024,  # 10 MB
    backup_count=5,
)
```

**Result:**
- `ai-service.log` - Current log file
- `ai-service.log.1` - Most recent backup
- `ai-service.log.2` - Second most recent backup
- ...
- `ai-service.log.5` - Oldest backup (deleted when rotation occurs)

### Time-Based Rotation

```python
from app.core.logging import configure_logging

# Rotate daily at midnight, keep 7 days of logs
configure_logging(
    log_level="INFO",
    json_logs=True,
    log_file="/var/log/laya/ai-service.log",
    rotation_enabled=True,
    rotation_type="time",
    when="midnight",
    interval=1,
    backup_count=7,
)
```

**Result:**
- `ai-service.log` - Current log file
- `ai-service.log.2026-02-17` - Previous day's log
- `ai-service.log.2026-02-16` - Two days ago
- ...
- Logs older than 7 days are automatically deleted

### Without Rotation

```python
from app.core.logging import configure_logging

# Simple file logging without rotation
configure_logging(
    log_level="INFO",
    json_logs=True,
    log_file="/var/log/laya/ai-service.log",
    rotation_enabled=False,
)
```

## Rotation Schedule Options

For time-based rotation, the `when` parameter supports:

| Value | Description | Example |
|-------|-------------|---------|
| `S` | Seconds | Rotate every N seconds |
| `M` | Minutes | Rotate every N minutes |
| `H` | Hours | Rotate every N hours |
| `D` | Days | Rotate every N days |
| `midnight` | Daily at midnight | Rotate at 00:00 each day |
| `W0`-`W6` | Weekday (0=Monday) | Rotate on specific weekday |

## Production Recommendations

### High-Traffic Services
```bash
LOG_ROTATION_TYPE=size
LOG_MAX_BYTES=50000000  # 50 MB
LOG_BACKUP_COUNT=10
```

### Standard Services
```bash
LOG_ROTATION_TYPE=time
LOG_ROTATION_WHEN=midnight
LOG_ROTATION_INTERVAL=1
LOG_BACKUP_COUNT=30  # Keep 30 days
```

### Low-Traffic/Development
```bash
LOG_ROTATION_TYPE=time
LOG_ROTATION_WHEN=midnight
LOG_ROTATION_INTERVAL=1
LOG_BACKUP_COUNT=7  # Keep 1 week
```

## Disk Space Calculation

### Size-Based Rotation
```
Total Disk Space = max_bytes * (backup_count + 1)
Example: 10 MB * (5 + 1) = 60 MB maximum
```

### Time-Based Rotation
```
Total Disk Space = (avg_daily_logs) * (backup_count + 1)
Example: 100 MB/day * (7 + 1) = 800 MB maximum
```

## Integration with Application

The log rotation is automatically configured when the application starts in `app/main.py`:

```python
from app.config import settings
from app.core.logging import configure_logging

configure_logging(
    log_level=settings.log_level,
    json_logs=settings.json_logs,
    log_file=settings.log_file,
    rotation_enabled=settings.log_rotation_enabled,
    rotation_type=settings.log_rotation_type,
    max_bytes=settings.log_max_bytes,
    backup_count=settings.log_backup_count,
    when=settings.log_rotation_when,
    interval=settings.log_rotation_interval,
)
```

## Docker Deployment

For Docker deployments, mount a volume for log persistence:

```yaml
services:
  ai-service:
    image: laya-ai-service:latest
    volumes:
      - ./logs:/var/log/laya
    environment:
      - LOG_FILE=/var/log/laya/ai-service.log
      - LOG_ROTATION_ENABLED=true
      - LOG_ROTATION_TYPE=size
      - LOG_MAX_BYTES=50000000
      - LOG_BACKUP_COUNT=10
```

## Monitoring and Maintenance

### Check Log File Sizes
```bash
ls -lh /var/log/laya/ai-service.log*
```

### View Rotation Status
```bash
# Count backup files
ls -1 /var/log/laya/ai-service.log.* | wc -l

# Total disk usage
du -sh /var/log/laya/
```

### Manual Cleanup
If needed, manually remove old log files:
```bash
# Remove logs older than 30 days
find /var/log/laya -name "ai-service.log.*" -mtime +30 -delete
```

## Troubleshooting

### Logs Not Rotating

1. **Check permissions:**
   ```bash
   ls -la /var/log/laya/
   # Ensure service has write permissions
   ```

2. **Verify configuration:**
   ```python
   from app.config import settings
   print(f"Rotation enabled: {settings.log_rotation_enabled}")
   print(f"Rotation type: {settings.log_rotation_type}")
   print(f"Log file: {settings.log_file}")
   ```

3. **Check disk space:**
   ```bash
   df -h /var/log/laya
   ```

### Too Many Backup Files

Reduce `backup_count` in configuration:
```bash
LOG_BACKUP_COUNT=3  # Keep fewer backups
```

### Logs Rotating Too Frequently

Increase rotation threshold:
```bash
# For size-based
LOG_MAX_BYTES=104857600  # Increase to 100 MB

# For time-based
LOG_ROTATION_WHEN=D  # Change to daily instead of hourly
LOG_ROTATION_INTERVAL=1
```

## Best Practices

1. **Set appropriate rotation thresholds** based on log volume
2. **Monitor disk space** regularly
3. **Use time-based rotation for predictable patterns**
4. **Use size-based rotation for variable traffic**
5. **Keep enough backups** for debugging but not too many to waste space
6. **Use JSON logs in production** for better parsing
7. **Configure log levels appropriately** (INFO or WARNING in production)
8. **Test rotation in staging** before deploying to production

## See Also

- [Logging Configuration](./logging.py) - Main logging module
- [Configuration Settings](../config.py) - Application settings
- [Error Handling](./error_responses.md) - Error handling documentation
