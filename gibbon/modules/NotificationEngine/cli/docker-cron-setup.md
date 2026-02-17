# Docker Cron Setup for NotificationEngine

This guide explains how to set up the notification queue processor in Docker environments.

## Overview

The NotificationEngine queue processor needs to run periodically to send queued notifications. In Docker environments, there are several approaches to achieve this.

## Approach 1: Supervisor (Recommended)

Use Supervisor to manage a loop-based queue worker inside the container.

### 1. Create Worker Script

Create `cli/worker.php`:

```php
#!/usr/bin/env php
<?php
/**
 * Queue Worker Loop
 * Continuously processes notification queue
 */

// Infinite loop with sleep
while (true) {
    // Execute queue processor
    echo "[" . date('Y-m-d H:i:s') . "] Starting queue processing...\n";

    $output = [];
    $returnCode = 0;
    exec('php ' . __DIR__ . '/processQueue.php --limit=50 2>&1', $output, $returnCode);

    foreach ($output as $line) {
        echo $line . "\n";
    }

    if ($returnCode !== 0) {
        echo "[ERROR] Queue processor exited with code $returnCode\n";
    }

    // Sleep for 60 seconds before next run
    sleep(60);
}
```

### 2. Create Supervisor Configuration

Create `/etc/supervisor/conf.d/notification-worker.conf`:

```ini
[program:notification-worker]
command=/usr/local/bin/php /var/www/html/modules/NotificationEngine/cli/worker.php
directory=/var/www/html
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/supervisor/notification-worker.log
stdout_logfile_maxbytes=10MB
stdout_logfile_backups=3
```

### 3. Update Dockerfile

```dockerfile
# Install supervisor
RUN apt-get update && apt-get install -y supervisor

# Copy supervisor config
COPY docker/supervisor/notification-worker.conf /etc/supervisor/conf.d/

# Create log directory
RUN mkdir -p /var/log/supervisor && \
    chown -R www-data:www-data /var/log/supervisor
```

### 4. Update Docker Entrypoint

```bash
#!/bin/bash
set -e

# Start supervisor
supervisord -c /etc/supervisor/supervisord.conf

# Start PHP-FPM
exec php-fpm
```

### 5. Manage Worker

```bash
# Check status
docker exec gibbon_php supervisorctl status notification-worker

# Start worker
docker exec gibbon_php supervisorctl start notification-worker

# Stop worker
docker exec gibbon_php supervisorctl stop notification-worker

# Restart worker
docker exec gibbon_php supervisorctl restart notification-worker

# View logs
docker exec gibbon_php tail -f /var/log/supervisor/notification-worker.log
```

## Approach 2: Host-Based Cron

Run cron on the host system, executing commands inside the container.

### Setup

```bash
# Edit host crontab
crontab -e

# Add entry (update container name and paths as needed)
* * * * * docker exec gibbon_php php /var/www/html/modules/NotificationEngine/cli/processQueue.php >> /var/log/gibbon/notifications.log 2>&1
```

### Pros & Cons

**Pros:**
- Simple to set up
- Cron managed outside container
- Easy to monitor from host

**Cons:**
- Requires host cron to be running
- Container must be running
- Less portable across environments

## Approach 3: Container-Based Cron

Install and run cron inside the PHP container.

### 1. Update Dockerfile

```dockerfile
# Install cron
RUN apt-get update && apt-get install -y cron

# Copy crontab file
COPY docker/crontab /etc/cron.d/gibbon-notifications

# Give execution rights
RUN chmod 0644 /etc/cron.d/gibbon-notifications

# Apply cron job
RUN crontab -u www-data /etc/cron.d/gibbon-notifications

# Create log file
RUN touch /var/log/cron.log && chown www-data:www-data /var/log/cron.log
```

### 2. Create Crontab File

Create `docker/crontab`:

```cron
# Gibbon NotificationEngine Queue Processor
* * * * * /usr/local/bin/php /var/www/html/modules/NotificationEngine/cli/processQueue.php >> /var/log/cron.log 2>&1

# Purge old notifications daily at 2 AM
0 2 * * * /usr/local/bin/php /var/www/html/modules/NotificationEngine/cli/processQueue.php --purge --purge-days=30 >> /var/log/cron.log 2>&1
```

### 3. Update Entrypoint

```bash
#!/bin/bash
set -e

# Start cron
service cron start

# Start PHP-FPM
exec php-fpm
```

### 4. Monitor Logs

```bash
# View cron logs
docker exec gibbon_php tail -f /var/log/cron.log

# Check if cron is running
docker exec gibbon_php service cron status
```

## Approach 4: Docker Compose with Separate Worker Service

Create a dedicated worker container for processing notifications.

### docker-compose.yml

```yaml
services:
  php-fpm:
    image: gibbon-php:latest
    # ... existing config ...

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
      - FIREBASE_CREDENTIALS_PATH=/var/www/html/config/firebase-credentials.json
    restart: unless-stopped
```

### Worker Script

Same as Approach 1, Step 1.

### Benefits

- Clean separation of concerns
- Easy to scale (multiple workers)
- Independent restart/management
- Clear logs per service

## Kubernetes Setup

For Kubernetes deployments, use a CronJob resource.

### notification-queue-cronjob.yaml

```yaml
apiVersion: batch/v1
kind: CronJob
metadata:
  name: notification-queue-processor
  namespace: gibbon
spec:
  # Run every minute
  schedule: "* * * * *"
  successfulJobsHistoryLimit: 3
  failedJobsHistoryLimit: 3
  concurrencyPolicy: Forbid
  jobTemplate:
    spec:
      template:
        spec:
          containers:
          - name: queue-processor
            image: gibbon-php:latest
            command:
              - /usr/local/bin/php
              - /var/www/html/modules/NotificationEngine/cli/processQueue.php
              - --limit=100
            env:
            - name: DB_HOST
              value: mysql-service
            - name: DB_NAME
              value: gibbon
            - name: DB_USER
              valueFrom:
                secretKeyRef:
                  name: gibbon-db-secret
                  key: username
            - name: DB_PASSWORD
              valueFrom:
                secretKeyRef:
                  name: gibbon-db-secret
                  key: password
            - name: FIREBASE_CREDENTIALS_PATH
              value: /etc/firebase/credentials.json
            volumeMounts:
            - name: firebase-credentials
              mountPath: /etc/firebase
              readOnly: true
          volumes:
          - name: firebase-credentials
            secret:
              secretName: firebase-credentials
          restartPolicy: OnFailure
```

### Deploy

```bash
kubectl apply -f notification-queue-cronjob.yaml

# Check status
kubectl get cronjobs -n gibbon

# View jobs
kubectl get jobs -n gibbon

# View logs
kubectl logs -n gibbon job/notification-queue-processor-xxxxx
```

## Testing

### Test Queue Processor

```bash
# Manual run (any approach)
docker exec gibbon_php php /var/www/html/modules/NotificationEngine/cli/processQueue.php --verbose

# Dry run
docker exec gibbon_php php /var/www/html/modules/NotificationEngine/cli/processQueue.php --dry-run --verbose
```

### Insert Test Notification

```bash
# Connect to MySQL container
docker exec -it mysql mysql -u gibbon -pgibbon gibbon

# Insert test notification
INSERT INTO gibbonNotificationQueue (
    gibbonPersonID, type, title, body, channel, status, timestampCreated
) VALUES (
    1,
    'test',
    'Test Notification',
    'This is a test notification.',
    'both',
    'pending',
    NOW()
);

# Verify it's queued
SELECT * FROM gibbonNotificationQueue WHERE status='pending';
```

### Monitor Processing

```bash
# Watch logs (Supervisor approach)
docker exec gibbon_php tail -f /var/log/supervisor/notification-worker.log

# Watch logs (Cron approach)
docker exec gibbon_php tail -f /var/log/cron.log

# Check queue status
docker exec -it mysql mysql -u gibbon -pgibbon gibbon -e "SELECT status, COUNT(*) FROM gibbonNotificationQueue GROUP BY status;"
```

## Troubleshooting

### Worker Not Starting (Supervisor)

```bash
# Check supervisor status
docker exec gibbon_php supervisorctl status

# Check supervisor logs
docker exec gibbon_php tail -f /var/log/supervisor/supervisord.log

# Restart supervisor
docker exec gibbon_php supervisorctl restart notification-worker
```

### Cron Not Running

```bash
# Check cron service
docker exec gibbon_php service cron status

# Check cron is installed
docker exec gibbon_php which cron

# List crontab
docker exec gibbon_php crontab -u www-data -l

# Check system cron logs
docker exec gibbon_php grep CRON /var/log/syslog
```

### Database Connection Issues

```bash
# Test database connectivity from worker
docker exec gibbon_php php -r "
\$pdo = new PDO('mysql:host=mysql;dbname=gibbon', 'gibbon', 'gibbon');
echo 'Connected successfully';
"
```

### FCM Issues

```bash
# Check FCM credentials exist
docker exec gibbon_php ls -la /var/www/html/config/firebase-credentials.json

# Check environment variable
docker exec gibbon_php printenv FIREBASE_CREDENTIALS_PATH

# Test FCM connectivity
docker exec gibbon_php php /var/www/html/modules/NotificationEngine/cli/processQueue.php --verbose --limit=1
```

## Production Recommendations

1. **Use Supervisor or Separate Worker Service**
   - More reliable than cron
   - Better logging and monitoring
   - Automatic restart on failure

2. **Set Resource Limits**
   ```yaml
   notification-worker:
     deploy:
       resources:
         limits:
           memory: 256M
           cpus: '0.5'
         reservations:
           memory: 128M
           cpus: '0.25'
   ```

3. **Configure Health Checks**
   ```yaml
   notification-worker:
     healthcheck:
       test: ["CMD", "php", "-v"]
       interval: 30s
       timeout: 10s
       retries: 3
   ```

4. **Monitor Performance**
   - Track queue depth
   - Monitor processing time
   - Alert on failures
   - Set up log aggregation

5. **Scale Horizontally**
   ```bash
   # Scale to 3 workers
   docker-compose up -d --scale notification-worker=3
   ```

## Recommended Approach by Environment

| Environment | Recommended Approach | Reason |
|-------------|---------------------|---------|
| Development | Host-based cron | Simple, easy to debug |
| Docker Compose | Supervisor or Separate Service | Reliable, good logging |
| Kubernetes | CronJob | Native K8s resource |
| Production (Docker) | Supervisor with Separate Service | Best reliability and scalability |

## Summary

Choose the approach that best fits your infrastructure:
- **Supervisor**: Best for production Docker deployments
- **Host Cron**: Simplest for development
- **Container Cron**: Self-contained but less reliable
- **Separate Service**: Best for scaling and isolation
- **Kubernetes CronJob**: Best for K8s environments
