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

## Backup Retention Policy

The retention policy script (`retention_policy.sh`) automatically manages backup lifecycle:

### Retention Rules

- **Daily backups:** Keep last 7 days (all backups from past week)
- **Weekly backups:** Keep last 4 weeks (Sunday backups)
- **Monthly backups:** Keep last 12 months (1st day of month backups)

### Features

- ✅ Intelligent backup classification (daily/weekly/monthly)
- ✅ Safe deletion with comprehensive logging
- ✅ Dry-run mode for testing before actual deletion
- ✅ Separate retention for MySQL and PostgreSQL backups
- ✅ Automatic disk space recovery
- ✅ Protection against accidental deletion of critical backups

### Setup

#### Manual Execution

Test with dry-run first:
```bash
./scripts/backup/retention_policy.sh --dry-run
```

Execute actual cleanup:
```bash
./scripts/backup/retention_policy.sh
```

#### Automated Cleanup (Cron)

Add to crontab to run daily at 3:00 AM (after backups complete):

```bash
crontab -e
```

Add this line:
```
0 3 * * * /path/to/laya-backbone/scripts/backup/retention_policy.sh >> /var/log/retention_policy.log 2>&1
```

#### Configuration Options

**Environment Variables:**
```bash
export MYSQL_BACKUP_DIR=/var/backups/mysql
export POSTGRES_BACKUP_DIR=/var/backups/postgres
export RETENTION_LOG=/var/log/retention_policy.log
export DRY_RUN=true  # Set to true for dry-run mode
```

**Command Line Options:**
```bash
# Test retention policy without deleting files
./retention_policy.sh --dry-run

# Use custom backup directories
./retention_policy.sh --mysql-dir /custom/mysql/backups --postgres-dir /custom/postgres/backups

# Show help
./retention_policy.sh --help
```

### How It Works

1. **Daily Retention**: All backups from the last 7 days are kept automatically
2. **Weekly Retention**: After 7 days, only Sunday backups are kept for 4 weeks
3. **Monthly Retention**: After 4 weeks, only 1st-of-month backups are kept for 12 months
4. **Deletion**: Backups older than the retention periods are safely deleted

### Example Timeline

Given today is March 15, 2026:

- **Kept as Daily**: March 9-15 (all backups)
- **Kept as Weekly**: Feb 16 (Sun), Feb 23 (Sun), Mar 2 (Sun), Mar 9 (Sun)
- **Kept as Monthly**: Apr 1, May 1, Jun 1, Jul 1, Aug 1, Sep 1, Oct 1, Nov 1, Dec 1, Jan 1, Feb 1, Mar 1
- **Deleted**: All other backups older than 7 days that aren't weekly/monthly snapshots

### Monitoring

Check retention policy logs:
```bash
tail -f /var/log/retention_policy.log
```

View retention statistics:
```bash
grep "Retention summary" /var/log/retention_policy.log
```

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

### PostgreSQL Backup Script (`postgres_backup.sh`)

**Features:**
- ✅ pg_dump with comprehensive options
- ✅ Gzip compression for space efficiency
- ✅ Timestamped backup files (`YYYYMMDD_HHMMSS`)
- ✅ Comprehensive error handling and logging
- ✅ Pre-flight checks (connectivity, disk space, database existence)
- ✅ Backup integrity verification
- ✅ Secure file permissions (600)
- ✅ Includes schema and data
- ✅ UTF-8 character set support
- ✅ Clean restore with DROP/CREATE commands

**Backup Options:**
- `--clean`: Include DROP commands before CREATE
- `--if-exists`: Use IF EXISTS with DROP commands
- `--create`: Include CREATE DATABASE command
- `--no-owner`: Don't output ownership commands
- `--no-acl`: Don't output ACL commands
- `--encoding=UTF8`: UTF-8 encoding

**Scheduled Time:** Daily at 2:15 AM

### 4. PostgreSQL Backup Setup

#### Prerequisites
- PostgreSQL client tools (`pg_dump`, `psql`) installed
- `gzip` and `bc` installed
- Sufficient disk space for backups
- PostgreSQL user with backup privileges

#### Environment Variables

Create a `.env` file or set environment variables:

```bash
export PGHOST=localhost
export PGPORT=5432
export PGUSER=backup_user
export PGPASSWORD=your_secure_password
export PGDATABASE=laya_db
export BACKUP_DIR=/var/backups/postgres
```

#### Grant Backup Privileges

Create a dedicated PostgreSQL backup user:

```sql
CREATE USER backup_user WITH PASSWORD 'secure_password';
GRANT CONNECT ON DATABASE laya_db TO backup_user;
\c laya_db
GRANT USAGE ON SCHEMA public TO backup_user;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO backup_user;
GRANT SELECT ON ALL SEQUENCES IN SCHEMA public TO backup_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO backup_user;
ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON SEQUENCES TO backup_user;
```

#### Manual Backup

Run the backup script manually:

```bash
cd /path/to/laya-backbone
./scripts/backup/postgres_backup.sh
```

#### Automated Backup (Cron)

1. Create backup directory:
```bash
sudo mkdir -p /var/backups/postgres
sudo chmod 700 /var/backups/postgres
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
15 2 * * * PGHOST=localhost PGPORT=5432 PGUSER=backup_user PGPASSWORD=secure_password PGDATABASE=laya_db BACKUP_DIR=/var/backups/postgres /path/to/laya-backbone/scripts/backup/postgres_backup.sh >> /var/log/postgres_backup.log 2>&1
```

Or use an environment file:
```
15 2 * * * /bin/bash -c 'source /path/to/.env && /path/to/laya-backbone/scripts/backup/postgres_backup.sh' >> /var/log/postgres_backup.log 2>&1
```

### 5. Verify PostgreSQL Backup

Check if backup was created:

```bash
ls -lh /var/backups/postgres/
```

View backup log:

```bash
tail -f /var/log/postgres_backup.log
```

Test backup integrity:

```bash
gzip -t /var/backups/postgres/laya_db_*.sql.gz
```

### 6. Restore PostgreSQL Backup

To restore a backup:

```bash
# Decompress and restore
gunzip -c /var/backups/postgres/laya_db_20260216_021500.sql.gz | psql -h localhost -U postgres
```

Or restore to a specific database:

```bash
# Drop existing database and restore
dropdb -h localhost -U postgres laya_db
gunzip -c /var/backups/postgres/laya_db_20260216_021500.sql.gz | psql -h localhost -U postgres
```

## Future Enhancements

- [x] PostgreSQL backup script (scheduled at 2:15 AM)
- [x] Automated backup retention/cleanup (7 daily, 4 weekly, 12 monthly)
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
- [pg_dump Documentation](https://www.postgresql.org/docs/current/app-pgdump.html)
- [PostgreSQL Backup and Restore](https://www.postgresql.org/docs/current/backup.html)
- [Cron Configuration Guide](https://crontab.guru/)

## Support

For issues or questions, please refer to the LAYA documentation or contact the infrastructure team.

---
**Last Updated:** 2026-02-16
**Maintained by:** LAYA Infrastructure Team
