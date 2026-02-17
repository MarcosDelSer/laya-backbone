# Cron Queue Worker - Quick Start Guide

Get the NotificationEngine queue worker running in under 5 minutes.

## What is this?

The queue worker processes notifications (email and push) that have been queued by the NotificationEngine. It runs in the background and delivers notifications to users.

## Quick Setup Options

### Option 1: Simple Cron (Production)

**Best for:** Traditional server setups

```bash
# Run the setup assistant
cd /var/www/gibbon/modules/NotificationEngine/cli
sudo bash setup-cron.sh
```

Follow the prompts. Done! ✅

### Option 2: Docker with Supervisor (Recommended)

**Best for:** Docker/containerized environments

1. Add to your `docker-compose.yml`:

```yaml
services:
  notification-worker:
    image: gibbon-php:latest
    command: php /var/www/html/modules/NotificationEngine/cli/worker.php
    volumes:
      - ./gibbon:/var/www/html
    depends_on:
      - mysql
    environment:
      - DB_HOST=mysql
      - DB_NAME=gibbon
      - DB_USER=gibbon
      - DB_PASSWORD=gibbon
    restart: unless-stopped
```

2. Start the worker:

```bash
docker-compose up -d notification-worker
```

Done! ✅

### Option 3: Host Cron with Docker (Simple)

**Best for:** Development environments

```bash
# Edit crontab
crontab -e

# Add this line (update container name if needed)
* * * * * docker exec gibbon_php php /var/www/html/modules/NotificationEngine/cli/processQueue.php >> /tmp/notifications.log 2>&1
```

Done! ✅

## Verify It's Working

### 1. Insert a test notification

```bash
# Connect to database
docker exec -it mysql mysql -u gibbon -pgibbon gibbon

# Or if not using Docker
mysql -u gibbon -p gibbon
```

```sql
-- Insert test notification (replace gibbonPersonID=1 with a valid ID)
INSERT INTO gibbonNotificationQueue (
    gibbonPersonID, type, title, body, channel, status, timestampCreated
) VALUES (
    1,
    'test',
    'Test Notification',
    'This is a test.',
    'both',
    'pending',
    NOW()
);

-- Check queue
SELECT * FROM gibbonNotificationQueue WHERE status='pending';
```

### 2. Wait 1 minute (or run manually)

**Manual run:**
```bash
# Docker
docker exec gibbon_php php /var/www/html/modules/NotificationEngine/cli/processQueue.php --verbose

# Traditional
php /var/www/gibbon/modules/NotificationEngine/cli/processQueue.php --verbose
```

### 3. Verify it was processed

```sql
-- Check if notification was sent
SELECT * FROM gibbonNotificationQueue WHERE type='test';
-- Status should be 'sent'
```

If status changed from `pending` to `sent`, it's working! ✅

## View Logs

**Docker (worker service):**
```bash
docker-compose logs -f notification-worker
```

**Docker (cron):**
```bash
tail -f /tmp/notifications.log
```

**Traditional:**
```bash
tail -f /var/log/gibbon/notifications.log
```

## Common Commands

### Check Queue Status

```sql
SELECT status, COUNT(*) as count
FROM gibbonNotificationQueue
GROUP BY status;
```

### Process Queue Manually

```bash
# Docker
docker exec gibbon_php php /var/www/html/modules/NotificationEngine/cli/processQueue.php --verbose

# Traditional
php /var/www/gibbon/modules/NotificationEngine/cli/processQueue.php --verbose
```

### Stop Worker (Docker Compose)

```bash
docker-compose stop notification-worker
```

### Restart Worker (Docker Compose)

```bash
docker-compose restart notification-worker
```

### View Cron Jobs

```bash
# Check current user
crontab -l

# Check www-data user
sudo crontab -u www-data -l
```

## Troubleshooting

### Notifications not being sent?

1. **Check queue worker is running:**
   ```bash
   # Docker
   docker-compose ps notification-worker

   # Cron
   sudo crontab -u www-data -l
   ```

2. **Check for errors:**
   ```bash
   # View logs
   docker-compose logs notification-worker
   # or
   tail -f /var/log/gibbon/notifications.log
   ```

3. **Test manually:**
   ```bash
   docker exec gibbon_php php /var/www/html/modules/NotificationEngine/cli/processQueue.php --dry-run --verbose
   ```

4. **Check FCM configuration** (for push notifications):
   - Verify `FIREBASE_CREDENTIALS_PATH` environment variable is set
   - Check credentials file exists and is readable
   - Ensure FCM is enabled in Notification Engine settings

5. **Check SMTP configuration** (for emails):
   - Verify SMTP settings in Gibbon admin panel
   - Test email delivery manually

### Need more help?

- **Full documentation:** See `README.md` in this directory
- **Docker setup:** See `docker-cron-setup.md`
- **Setup script:** Run `bash setup-cron.sh`

## Configuration

Key settings (Gibbon admin panel → Third Party Settings → Notification Engine):

- `queueBatchSize`: How many notifications to process per run (default: 50)
- `maxRetryAttempts`: Max delivery attempts before giving up (default: 3)
- `fcmEnabled`: Enable push notifications (Y/N)

## Next Steps

Once the queue worker is running:

1. ✅ Configure notification templates (via Gibbon admin)
2. ✅ Set up user notification preferences
3. ✅ Register mobile app FCM tokens (if using push)
4. ✅ Monitor the queue regularly
5. ✅ Set up log rotation
6. ✅ Configure purge schedule for old notifications

## Quick Reference

| Task | Command |
|------|---------|
| Process queue now | `php processQueue.php --verbose` |
| Dry run (no send) | `php processQueue.php --dry-run --verbose` |
| Purge old notifications | `php processQueue.php --purge --purge-days=30` |
| Run worker loop | `php worker.php --interval=60 --verbose` |
| Check queue stats | `SELECT status, COUNT(*) FROM gibbonNotificationQueue GROUP BY status;` |

---

**Need detailed documentation?** See `README.md` in this directory.

**Running in Docker?** See `docker-cron-setup.md` for container-specific setup.
