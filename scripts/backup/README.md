# LAYA Backup System

Comprehensive backup and recovery system for the LAYA Daycare Management System.

## Overview

This directory contains automated backup scripts for all critical data:
- MySQL database backups
- PostgreSQL database backups (if applicable)
- File storage backups (photos, uploads)
- Docker volume backups
- Configuration backups

## Quick Start

### 1. MySQL Backup Setup

#### Prerequisites
- MySQL client tools (`mysqldump`) installed
- `gzip` installed (usually pre-installed on Linux/macOS)
- Sufficient disk space for backups
- MySQL user with backup privileges

#### Environment Variables

Create a `.env` file or set environment variables:

```bash
export MYSQL_HOST=localhost
export MYSQL_PORT=3306
export MYSQL_USER=backup_user
export MYSQL_PASSWORD=your_secure_password
export MYSQL_DATABASE=laya_db
export BACKUP_DIR=/var/backups/mysql
```

#### Grant Backup Privileges

Create a dedicated MySQL backup user:

```sql
CREATE USER 'backup_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON laya_db.* TO 'backup_user'@'localhost';
FLUSH PRIVILEGES;
```

#### Manual Backup

Run the backup script manually:

```bash
cd /path/to/laya-backbone
./scripts/backup/mysql_backup.sh
```

#### Automated Backup (Cron)

1. Create backup directory:
```bash
sudo mkdir -p /var/backups/mysql
sudo chmod 700 /var/backups/mysql
```

2. Create log directory:
```bash
sudo mkdir -p /var/log
sudo chmod 755 /var/log
```

3. Edit crontab:
```bash
crontab -e
```

4. Add the following line (adjust paths as needed):
```
0 2 * * * MYSQL_HOST=localhost MYSQL_PORT=3306 MYSQL_USER=backup_user MYSQL_PASSWORD=secure_password MYSQL_DATABASE=laya_db BACKUP_DIR=/var/backups/mysql /path/to/laya-backbone/scripts/backup/mysql_backup.sh >> /var/log/mysql_backup.log 2>&1
```

Or use an environment file:
```
0 2 * * * /bin/bash -c 'source /path/to/.env && /path/to/laya-backbone/scripts/backup/mysql_backup.sh' >> /var/log/mysql_backup.log 2>&1
```

### 2. Verify Backup

Check if backup was created:

```bash
ls -lh /var/backups/mysql/
```

View backup log:

```bash
tail -f /var/log/mysql_backup.log
```

Test backup integrity:

```bash
gzip -t /var/backups/mysql/laya_db_*.sql.gz
```

### 3. Restore from Backup

To restore a backup:

```bash
# Decompress and restore
gunzip -c /var/backups/mysql/laya_db_20260215_020000.sql.gz | mysql -h localhost -u root -p laya_db
```

## Backup Features

### MySQL Backup Script (`mysql_backup.sh`)

**Features:**
- ✅ Single-transaction backup (InnoDB-safe, no table locking)
- ✅ Gzip compression for space efficiency
- ✅ Timestamped backup files (`YYYYMMDD_HHMMSS`)
- ✅ Comprehensive error handling and logging
- ✅ Pre-flight checks (connectivity, disk space, database existence)
- ✅ Backup integrity verification
- ✅ Secure file permissions (600)
- ✅ Includes stored procedures, triggers, and events
- ✅ UTF-8 character set support
- ✅ Memory-efficient streaming

**Backup Options:**
- `--single-transaction`: Consistent backup without locking tables
- `--quick`: Stream rows instead of buffering entire result set
- `--lock-tables=false`: No table locking (relies on --single-transaction)
- `--routines`: Include stored procedures and functions
- `--triggers`: Include triggers
- `--events`: Include scheduled events
- `--hex-blob`: Binary data in hexadecimal format
- `--default-character-set=utf8mb4`: UTF-8 encoding

**Scheduled Time:** Daily at 2:00 AM

## Security Best Practices

1. **Use Dedicated Backup User**
   - Create a MySQL user with minimal required privileges
   - Use strong, unique password

2. **Secure Storage**
   - Set restrictive permissions on backup directory (700)
   - Set restrictive permissions on backup files (600)
   - Consider encrypting backups at rest

3. **Environment Variables**
   - Never commit passwords to version control
   - Use environment variables or secure secrets management
   - Consider using `.my.cnf` file for MySQL credentials

4. **Network Security**
   - Use SSL/TLS for remote database connections
   - Restrict backup user to specific IP addresses

5. **Monitoring**
   - Monitor backup logs regularly
   - Set up alerts for backup failures
   - Test restore procedures regularly

## Backup Retention (To Be Implemented)

The backup retention policy will maintain:
- **Daily backups:** Keep last 7 days
- **Weekly backups:** Keep last 4 weeks
- **Monthly backups:** Keep last 12 months

## Troubleshooting

### Backup Script Fails

1. **Check MySQL connectivity:**
```bash
mysql -h localhost -u backup_user -p -e "SELECT 1"
```

2. **Check disk space:**
```bash
df -h /var/backups/mysql
```

3. **Check log file:**
```bash
tail -100 /var/log/mysql_backup.log
```

4. **Verify environment variables:**
```bash
echo $MYSQL_HOST $MYSQL_PORT $MYSQL_USER $MYSQL_DATABASE
```

### Permission Denied

```bash
# Fix directory permissions
sudo chown -R $(whoami) /var/backups/mysql
sudo chmod 700 /var/backups/mysql

# Fix script permissions
chmod +x /path/to/scripts/backup/mysql_backup.sh
```

### Backup File Corrupted

```bash
# Test gzip integrity
gzip -t /var/backups/mysql/laya_db_*.sql.gz

# If corrupted, check disk health and re-run backup
sudo smartctl -a /dev/sda
```

## Future Enhancements

- [ ] PostgreSQL backup script (scheduled at 2:15 AM)
- [ ] Automated backup retention/cleanup
- [ ] Backup verification script (restore to temp DB)
- [ ] Remote backup to S3 or rsync to remote server
- [ ] Photo and upload file backups
- [ ] Docker volume backups
- [ ] Email/Slack notifications on failure
- [ ] Backup encryption
- [ ] Incremental backups
- [ ] Disaster recovery runbook

## References

- [mysqldump Documentation](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html)
- [MySQL Backup and Recovery](https://dev.mysql.com/doc/refman/8.0/en/backup-and-recovery.html)
- [Cron Configuration Guide](https://crontab.guru/)

## Support

For issues or questions, please refer to the LAYA documentation or contact the infrastructure team.

---
**Last Updated:** 2026-02-15
**Maintained by:** LAYA Infrastructure Team
