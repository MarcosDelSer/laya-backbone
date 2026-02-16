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

#### Quick Integrity Check

Check if backup was created:

```bash
ls -lh /var/backups/mysql/
```

View backup log:

```bash
tail -f /var/log/mysql_backup.log
```

Test backup file integrity (gzip test):

```bash
gzip -t /var/backups/mysql/laya_db_*.sql.gz
```

#### Comprehensive Backup Verification

Use the automated verification script to restore backups to a temporary database and run integrity checks:

```bash
# Verify a specific backup
./scripts/backup/verify_backup.sh /var/backups/mysql/laya_db_20260215_020000.sql.gz

# Verify the latest backup
./scripts/backup/verify_backup.sh --latest

# Verify all backups from the last 7 days
./scripts/backup/verify_backup.sh --all
```

The verification script:
- ✅ Automatically detects database type (MySQL/PostgreSQL)
- ✅ Restores backup to temporary database with `_verify_temp` suffix
- ✅ Runs comprehensive integrity checks (table count, database size, table checks, row counts)
- ✅ Cleans up temporary database automatically
- ✅ Non-destructive (no impact on production databases)
- ✅ Detailed logging to `/var/log/backup_verification.log`

### 3. Restore from Backup

#### Using the Restore Script (Recommended)

The automated restore script provides safety checks and confirmation prompts:

```bash
# Interactive restore (with confirmation prompts)
./scripts/backup/mysql_restore.sh /var/backups/mysql/laya_db_20260215_020000.sql.gz

# List available backups
./scripts/backup/mysql_restore.sh --list

# Non-interactive restore (skip confirmations - USE WITH CAUTION)
MYSQL_PASSWORD=your_password ./scripts/backup/mysql_restore.sh /var/backups/mysql/laya_db_20260215_020000.sql.gz --yes
```

#### Manual Restore (Alternative)

For manual restoration without the script:

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

#### Using the Restore Script (Recommended)

The automated restore script provides safety checks and confirmation prompts:

```bash
# Interactive restore (with confirmation prompts)
./scripts/backup/postgres_restore.sh /var/backups/postgres/laya_db_20260216_021500.sql.gz

# List available backups
./scripts/backup/postgres_restore.sh --list

# Non-interactive restore (skip confirmations - USE WITH CAUTION)
PGPASSWORD=your_password ./scripts/backup/postgres_restore.sh /var/backups/postgres/laya_db_20260216_021500.sql.gz --yes
```

#### Manual Restore (Alternative)

For manual restoration without the script:

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

## Restore Scripts

### MySQL Restore Script (`mysql_restore.sh`)

**Features:**
- ✅ Interactive and non-interactive modes
- ✅ Safety confirmations before destructive operations
- ✅ Backup file integrity verification (gzip test)
- ✅ Pre-flight checks (connectivity, file existence, disk space)
- ✅ Automatic database name extraction from backup filename
- ✅ Database drop and recreate with proper character set
- ✅ Post-restore verification (table count, database size)
- ✅ Comprehensive error handling and logging
- ✅ List available backups with metadata
- ✅ Progress reporting and timing

**Usage:**

```bash
# Interactive restore with confirmation prompts
./scripts/backup/mysql_restore.sh /var/backups/mysql/laya_db_20260215_020000.sql.gz

# List available backups
./scripts/backup/mysql_restore.sh --list

# Non-interactive mode (skip confirmations)
./scripts/backup/mysql_restore.sh /var/backups/mysql/laya_db_20260215_020000.sql.gz --yes

# Show help
./scripts/backup/mysql_restore.sh --help
```

**Environment Variables:**
```bash
export MYSQL_HOST=localhost
export MYSQL_PORT=3306
export MYSQL_USER=root
export MYSQL_PASSWORD=secure_password
export MYSQL_DATABASE=laya_db  # Optional: extracted from filename if not set
export BACKUP_DIR=/var/backups/mysql  # For --list option
```

**Safety Features:**
- Warns about existing database and data loss
- Shows current database size before dropping
- Requires typing 'yes' to confirm in interactive mode
- Verifies backup integrity before restore
- Validates MySQL connectivity before starting
- Logs all operations with timestamps

### PostgreSQL Restore Script (`postgres_restore.sh`)

**Features:**
- ✅ Interactive and non-interactive modes
- ✅ Safety confirmations before destructive operations
- ✅ Backup file integrity verification (gzip test)
- ✅ Pre-flight checks (connectivity, file existence, disk space)
- ✅ Automatic database name extraction from backup filename
- ✅ Terminates active connections before restore
- ✅ Database drop and recreate from backup
- ✅ Post-restore verification (table count, database size)
- ✅ Comprehensive error handling and logging
- ✅ List available backups with metadata
- ✅ Progress reporting and timing

**Usage:**

```bash
# Interactive restore with confirmation prompts
./scripts/backup/postgres_restore.sh /var/backups/postgres/laya_db_20260216_021500.sql.gz

# List available backups
./scripts/backup/postgres_restore.sh --list

# Non-interactive mode (skip confirmations)
./scripts/backup/postgres_restore.sh /var/backups/postgres/laya_db_20260216_021500.sql.gz --yes

# Show help
./scripts/backup/postgres_restore.sh --help
```

**Environment Variables:**
```bash
export PGHOST=localhost
export PGPORT=5432
export PGUSER=postgres
export PGPASSWORD=secure_password
export PGDATABASE=laya_db  # Optional: extracted from filename if not set
export BACKUP_DIR=/var/backups/postgres  # For --list option
```

**Safety Features:**
- Warns about existing database and data loss
- Shows current database size before dropping
- Requires typing 'yes' to confirm in interactive mode
- Terminates active connections before dropping database
- Verifies backup integrity before restore
- Validates PostgreSQL connectivity before starting
- Logs all operations with timestamps

## Restore Best Practices

1. **Always Test Backups**
   - Regularly test restore procedures to ensure backups are valid
   - Use the --list option to verify backup files exist
   - Test gzip integrity: `gzip -t /var/backups/mysql/*.sql.gz`

2. **Use Interactive Mode**
   - Always use interactive mode for production restores
   - Review warnings about data loss before confirming
   - Only use --yes flag in automated recovery scripts

3. **Verify After Restore**
   - Check table counts and database size after restore
   - Run application smoke tests to verify data integrity
   - Compare restored data with expected values

4. **Disaster Recovery**
   - Document restore procedures in runbook
   - Practice restore process regularly (quarterly)
   - Maintain off-site backups for disaster scenarios
   - Keep restore scripts versioned with backups

5. **Access Control**
   - Use dedicated restore user with minimal privileges
   - Never commit passwords to version control
   - Use environment files or secrets management
   - Audit restore operations in production

## Backup Verification

### Verification Script (`verify_backup.sh`)

**Features:**
- ✅ Automatic database type detection (MySQL/PostgreSQL)
- ✅ Restore to temporary database (non-destructive)
- ✅ Comprehensive integrity checks
- ✅ Automatic cleanup of temporary resources
- ✅ Support for single backup or batch verification
- ✅ Detailed logging and reporting

**Verification Process:**

1. **File Integrity Check**: Verifies gzip compression integrity
2. **Database Type Detection**: Automatically detects MySQL or PostgreSQL
3. **Temporary Restore**: Restores backup to `{database_name}_verify_temp`
4. **Integrity Checks**:
   - Table count verification
   - Database size calculation
   - Table structure validation (MySQL: CHECK TABLE)
   - Row count verification
   - Connection and query testing
5. **Cleanup**: Automatically drops temporary database

**Usage:**

```bash
# Verify a specific backup (auto-detects MySQL or PostgreSQL)
./scripts/backup/verify_backup.sh /var/backups/mysql/laya_db_20260215_020000.sql.gz

# Verify the latest backup
./scripts/backup/verify_backup.sh --latest

# Verify all backups from last 7 days
./scripts/backup/verify_backup.sh --all

# Show help
./scripts/backup/verify_backup.sh --help
```

**Environment Variables:**

For MySQL verification:
```bash
export MYSQL_HOST=localhost
export MYSQL_PORT=3306
export MYSQL_USER=root
export MYSQL_PASSWORD=secure_password
export MYSQL_BACKUP_DIR=/var/backups/mysql
```

For PostgreSQL verification:
```bash
export PGHOST=localhost
export PGPORT=5432
export PGUSER=postgres
export PGPASSWORD=secure_password
export POSTGRES_BACKUP_DIR=/var/backups/postgres
```

**Automated Verification (Cron):**

Add to crontab to verify latest backup daily at 4:00 AM:

```bash
crontab -e
```

Add this line:
```
0 4 * * * /path/to/laya-backbone/scripts/backup/verify_backup.sh --latest >> /var/log/backup_verification.log 2>&1
```

**Monitoring:**

Check verification logs:
```bash
tail -f /var/log/backup_verification.log
```

View verification results:
```bash
grep "SUCCESS\|FAILED" /var/log/backup_verification.log
```

## Remote Storage Backup

### Remote Backup Script (`remote_backup.sh`)

**Features:**
- ✅ Support for AWS S3 and rsync remote storage
- ✅ Selective sync (MySQL, PostgreSQL, or both)
- ✅ Bandwidth limiting for rsync transfers
- ✅ Incremental transfers (only changed files)
- ✅ Dry-run mode for testing before actual sync
- ✅ Comprehensive error handling and logging
- ✅ SSH key-based authentication for rsync
- ✅ Automatic remote directory creation
- ✅ Pre-flight connectivity checks

**Supported Methods:**

1. **AWS S3**: Uses AWS CLI to sync backups to S3 bucket
2. **Rsync**: Uses rsync over SSH to sync to remote server

**Usage:**

```bash
# Sync all backups to remote storage (using configured method)
./scripts/backup/remote_backup.sh

# Test sync without transferring (dry-run)
./scripts/backup/remote_backup.sh --dry-run

# Sync only MySQL backups
./scripts/backup/remote_backup.sh --mysql-only

# Sync only PostgreSQL backups
./scripts/backup/remote_backup.sh --postgres-only

# Show help
./scripts/backup/remote_backup.sh --help
```

### Setup for AWS S3

#### Prerequisites
- AWS CLI installed and configured
- AWS account with S3 bucket created
- IAM user with S3 write permissions

#### Environment Variables

```bash
export REMOTE_BACKUP_METHOD=s3
export AWS_S3_BUCKET=s3://my-laya-backups
export AWS_PROFILE=default  # Optional
export AWS_REGION=us-east-1  # Optional
export MYSQL_BACKUP_DIR=/var/backups/mysql
export POSTGRES_BACKUP_DIR=/var/backups/postgres
```

#### Install AWS CLI

macOS:
```bash
brew install awscli
```

Linux:
```bash
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install
```

#### Configure AWS CLI

```bash
aws configure
# Enter your AWS Access Key ID
# Enter your AWS Secret Access Key
# Enter default region (e.g., us-east-1)
# Enter default output format (json)
```

#### Create S3 Bucket

```bash
aws s3 mb s3://my-laya-backups --region us-east-1
```

#### Test S3 Sync

```bash
# Dry-run to see what would be synced
./scripts/backup/remote_backup.sh --dry-run

# Actual sync
./scripts/backup/remote_backup.sh
```

### Setup for Rsync

#### Prerequisites
- Remote server with SSH access
- rsync installed on both local and remote systems
- SSH key-based authentication configured

#### Environment Variables

```bash
export REMOTE_BACKUP_METHOD=rsync
export REMOTE_USER=backup_user
export REMOTE_HOST=backup.example.com
export REMOTE_PATH=/backups/laya
export SSH_KEY=$HOME/.ssh/id_rsa
export SSH_PORT=22
export RSYNC_BANDWIDTH=5000  # Optional: limit to 5 MB/s
export MYSQL_BACKUP_DIR=/var/backups/mysql
export POSTGRES_BACKUP_DIR=/var/backups/postgres
```

#### Generate SSH Key (if not exists)

```bash
ssh-keygen -t rsa -b 4096 -f ~/.ssh/id_rsa -N ""
```

#### Copy SSH Key to Remote Server

```bash
ssh-copy-id -i ~/.ssh/id_rsa.pub backup_user@backup.example.com
```

#### Test SSH Connection

```bash
ssh -i ~/.ssh/id_rsa backup_user@backup.example.com "echo 'SSH connection successful'"
```

#### Test Rsync Sync

```bash
# Dry-run to see what would be synced
./scripts/backup/remote_backup.sh --dry-run

# Actual sync
./scripts/backup/remote_backup.sh
```

### Automated Remote Backup (Cron)

Add to crontab to run daily at 5:00 AM (after local backups and retention policy):

```bash
crontab -e
```

Add this line:

**For S3:**
```
0 5 * * * REMOTE_BACKUP_METHOD=s3 AWS_S3_BUCKET=s3://my-laya-backups /path/to/laya-backbone/scripts/backup/remote_backup.sh >> /var/log/remote_backup.log 2>&1
```

**For Rsync:**
```
0 5 * * * REMOTE_BACKUP_METHOD=rsync REMOTE_USER=backup_user REMOTE_HOST=backup.example.com REMOTE_PATH=/backups/laya SSH_KEY=/home/user/.ssh/id_rsa /path/to/laya-backbone/scripts/backup/remote_backup.sh >> /var/log/remote_backup.log 2>&1
```

Or use an environment file:
```
0 5 * * * /bin/bash -c 'source /path/to/.env && /path/to/laya-backbone/scripts/backup/remote_backup.sh' >> /var/log/remote_backup.log 2>&1
```

### Monitoring Remote Backups

Check remote backup logs:
```bash
tail -f /var/log/remote_backup.log
```

View sync results:
```bash
grep "SUCCESS\|ERROR" /var/log/remote_backup.log
```

For S3, verify uploaded files:
```bash
aws s3 ls s3://my-laya-backups/mysql/ --recursive
aws s3 ls s3://my-laya-backups/postgres/ --recursive
```

For rsync, verify remote files:
```bash
ssh backup_user@backup.example.com "ls -lh /backups/laya/mysql/"
ssh backup_user@backup.example.com "ls -lh /backups/laya/postgres/"
```

### Bandwidth Considerations

For rsync, you can limit bandwidth to avoid saturating your network:

```bash
# Limit to 1 MB/s (1000 KB/s)
export RSYNC_BANDWIDTH=1000
./scripts/backup/remote_backup.sh
```

This is especially useful for:
- Limited bandwidth connections
- Syncing during business hours
- Avoiding network congestion

### Security Best Practices

1. **S3 Security**
   - Use IAM roles with minimal required permissions
   - Enable S3 bucket versioning for backup history
   - Enable S3 bucket encryption at rest
   - Use S3 lifecycle policies for cost optimization
   - Enable S3 access logging

2. **Rsync Security**
   - Use SSH key-based authentication (no passwords)
   - Restrict SSH key permissions (chmod 600)
   - Use dedicated backup user with minimal privileges
   - Consider using SSH jump hosts for additional security
   - Enable SSH key passphrase protection

3. **Network Security**
   - Use VPN or private networks for rsync transfers
   - Enable firewall rules to restrict SSH access
   - Monitor remote access logs
   - Use fail2ban to prevent brute-force attacks

4. **Monitoring**
   - Set up alerts for sync failures
   - Monitor remote storage capacity
   - Regularly test restore from remote backups
   - Audit remote backup access logs

### Troubleshooting

#### S3 Sync Fails

1. **Check AWS credentials:**
```bash
aws s3 ls
```

2. **Verify S3 bucket exists:**
```bash
aws s3 ls s3://my-laya-backups/
```

3. **Check IAM permissions:**
Ensure your IAM user has these permissions:
```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "s3:ListBucket",
        "s3:GetObject",
        "s3:PutObject",
        "s3:DeleteObject"
      ],
      "Resource": [
        "arn:aws:s3:::my-laya-backups",
        "arn:aws:s3:::my-laya-backups/*"
      ]
    }
  ]
}
```

#### Rsync Fails

1. **Check SSH connectivity:**
```bash
ssh -i ~/.ssh/id_rsa -v backup_user@backup.example.com
```

2. **Verify remote directory permissions:**
```bash
ssh backup_user@backup.example.com "ls -ld /backups/laya"
```

3. **Check rsync installation:**
```bash
rsync --version
ssh backup_user@backup.example.com "rsync --version"
```

4. **Test manual rsync:**
```bash
rsync -avz -e "ssh -i ~/.ssh/id_rsa" /var/backups/mysql/ backup_user@backup.example.com:/backups/laya/mysql/
```

## Photo and Upload Backup

### Photo Backup Script (`photo_backup.sh`)

**Features:**
- ✅ Rsync with --delete for exact mirroring
- ✅ Incremental transfers (only changed files)
- ✅ Bandwidth limiting option
- ✅ Dry-run mode for testing
- ✅ Comprehensive error handling and logging
- ✅ Pre-flight checks (connectivity, disk space, directory existence)
- ✅ Support for local and remote backup destinations
- ✅ Progress reporting with transfer statistics
- ✅ Secure file permissions (700)
- ✅ Verification mode to check backup integrity

**Scheduled Time:** Daily at 3:30 AM

**What Gets Backed Up:**
- Photos directory (`/var/www/laya/uploads/photos` by default)
- Uploaded files directory (`/var/www/laya/uploads/files` by default)

**Important Note on --delete Flag:**
The `--delete` flag ensures the backup directory is an exact mirror of the source directory. This means:
- Files deleted from the source will be deleted from the backup
- This prevents the backup from growing larger than the source
- Ensures consistency between source and backup
- Critical for maintaining an accurate snapshot of current files

### Setup

#### Prerequisites
- `rsync` installed (usually pre-installed on Linux/macOS)
- Sufficient disk space for photo/upload backups
- Write permissions for backup directories

#### Environment Variables

```bash
export PHOTOS_SOURCE_DIR=/var/www/laya/uploads/photos
export UPLOADS_SOURCE_DIR=/var/www/laya/uploads/files
export PHOTOS_BACKUP_DIR=/var/backups/photos
export UPLOADS_BACKUP_DIR=/var/backups/uploads
export RSYNC_BANDWIDTH=5000  # Optional: limit to 5 MB/s
export MIN_DISK_SPACE_GB=5   # Minimum free disk space required
```

#### Manual Backup

Run the backup script manually:

```bash
cd /path/to/laya-backbone
./scripts/backup/photo_backup.sh
```

#### Test Before Running

Always test with dry-run first:

```bash
# Test what would be backed up without actually transferring
./scripts/backup/photo_backup.sh --dry-run
```

#### Verify Existing Backups

Check if backups are in sync with source:

```bash
./scripts/backup/photo_backup.sh --verify
```

#### Automated Backup (Cron)

1. Create backup directories:
```bash
sudo mkdir -p /var/backups/photos
sudo mkdir -p /var/backups/uploads
sudo chmod 700 /var/backups/photos
sudo chmod 700 /var/backups/uploads
```

2. Edit crontab:
```bash
crontab -e
```

3. Add the following line (adjust paths as needed):
```
30 3 * * * PHOTOS_SOURCE_DIR=/var/www/laya/uploads/photos UPLOADS_SOURCE_DIR=/var/www/laya/uploads/files PHOTOS_BACKUP_DIR=/var/backups/photos UPLOADS_BACKUP_DIR=/var/backups/uploads /path/to/laya-backbone/scripts/backup/photo_backup.sh >> /var/log/photo_backup.log 2>&1
```

Or use an environment file:
```
30 3 * * * /bin/bash -c 'source /path/to/.env && /path/to/laya-backbone/scripts/backup/photo_backup.sh' >> /var/log/photo_backup.log 2>&1
```

### Usage

**Basic Usage:**

```bash
# Run photo and upload backup
./scripts/backup/photo_backup.sh

# Test backup without transferring (dry-run)
./scripts/backup/photo_backup.sh --dry-run

# Verify existing backups
./scripts/backup/photo_backup.sh --verify

# Show help
./scripts/backup/photo_backup.sh --help
```

**With Bandwidth Limiting:**

```bash
# Limit to 1 MB/s (1000 KB/s) to avoid network saturation
RSYNC_BANDWIDTH=1000 ./scripts/backup/photo_backup.sh
```

### Monitoring

Check photo backup logs:
```bash
tail -f /var/log/photo_backup.log
```

View backup results:
```bash
grep "SUCCESS\|ERROR" /var/log/photo_backup.log
```

Check backup size:
```bash
du -sh /var/backups/photos
du -sh /var/backups/uploads
```

### Understanding --delete Flag

The `--delete` flag is crucial for maintaining an accurate backup:

**With --delete (Recommended):**
- Source has 100 photos → Backup has 100 photos ✅
- Delete 10 photos from source → Backup now has 90 photos ✅
- Backup is always an exact mirror of source ✅

**Without --delete (Not Recommended):**
- Source has 100 photos → Backup has 100 photos
- Delete 10 photos from source → Backup still has 100 photos ❌
- Backup grows indefinitely and becomes inconsistent ❌

### Backup Schedule Integration

The photo backup runs at 3:30 AM, between the retention policy (3:00 AM) and backup verification (4:00 AM):

```
2:00 AM  → MySQL backup
2:15 AM  → PostgreSQL backup
3:00 AM  → Retention policy cleanup
3:30 AM  → Photo/upload backup ← This script
4:00 AM  → Backup verification
5:00 AM  → Remote storage sync
```

### Security Best Practices

1. **Access Control**
   - Set restrictive permissions on backup directories (700)
   - Use dedicated backup user with minimal privileges
   - Protect backup directories from unauthorized access

2. **Storage Security**
   - Store backups on separate physical drives when possible
   - Consider encrypting sensitive photo/upload backups
   - Implement access logging for backup directories

3. **Monitoring**
   - Monitor backup logs regularly
   - Set up alerts for backup failures
   - Check disk space to prevent backup failures
   - Verify backups periodically using --verify flag

4. **Disaster Recovery**
   - Keep copies of backups off-site (use remote_backup.sh)
   - Test restore procedures regularly
   - Document restore process
   - Maintain backup retention policies

### Troubleshooting

#### Rsync Fails

1. **Check rsync installation:**
```bash
rsync --version
```

2. **Check source directory permissions:**
```bash
ls -ld /var/www/laya/uploads/photos
ls -ld /var/www/laya/uploads/files
```

3. **Check destination directory permissions:**
```bash
ls -ld /var/backups/photos
ls -ld /var/backups/uploads
```

4. **Check disk space:**
```bash
df -h /var/backups
```

5. **Test manual rsync:**
```bash
rsync -avz --dry-run /var/www/laya/uploads/photos/ /var/backups/photos/
```

#### Backup Size Issues

If backups are too large:

1. **Use bandwidth limiting:**
```bash
export RSYNC_BANDWIDTH=1000  # 1 MB/s
./scripts/backup/photo_backup.sh
```

2. **Check for large files:**
```bash
find /var/www/laya/uploads/photos -type f -size +100M
```

3. **Review what's being backed up:**
```bash
./scripts/backup/photo_backup.sh --dry-run
```

#### Permission Denied

```bash
# Fix source directory permissions (read access)
sudo chmod -R 755 /var/www/laya/uploads

# Fix backup directory permissions (write access)
sudo chown -R $(whoami) /var/backups/photos
sudo chown -R $(whoami) /var/backups/uploads
sudo chmod 700 /var/backups/photos
sudo chmod 700 /var/backups/uploads

# Fix script permissions
chmod +x /path/to/scripts/backup/photo_backup.sh
```

## Docker Volume Backup

### Docker Volume Backup Script (`docker_volume_backup.sh`)

**Features:**
- ✅ Automatic detection of all Docker volumes
- ✅ Tar+gzip compression for space efficiency
- ✅ Timestamped backup files (`YYYYMMDD_HHMMSS`)
- ✅ Comprehensive error handling and logging
- ✅ Pre-flight checks (Docker availability, disk space)
- ✅ Selective volume backup support
- ✅ Backup integrity verification
- ✅ Secure file permissions (600)
- ✅ Cross-platform compatibility (macOS/Linux)
- ✅ Uses docker run for consistent backups

**Scheduled Time:** Daily at 2:30 AM

**What Gets Backed Up:**
- All Docker volumes defined in your Docker environment
- Persistent data from Docker containers
- Named volumes and their complete contents

### How It Works

The script uses a Docker container with the Alpine Linux image to create backups:

1. **Volume Discovery**: Lists all Docker volumes in the system
2. **Container Method**: Runs a temporary Alpine container that:
   - Mounts the target volume as read-only
   - Creates a tar.gz archive of the volume contents
   - Saves the archive to the backup directory
   - Automatically removes itself after completion
3. **Verification**: Tests tar.gz integrity after creation
4. **Security**: Sets restrictive permissions (600) on backup files

**Why This Method:**
- Works with any volume driver (local, nfs, etc.)
- Doesn't require direct filesystem access to volume mountpoints
- Handles permission issues gracefully
- Platform-independent (works on any Docker host)
- No need for volume-specific backup tools

### Setup

#### Prerequisites
- Docker installed and running
- User has permission to run Docker commands (or use sudo)
- Sufficient disk space for volume backups
- Alpine Docker image (automatically pulled if needed)

#### Environment Variables

```bash
export DOCKER_BACKUP_DIR=/var/backups/docker-volumes
export DOCKER_IMAGE=alpine:latest
export MIN_DISK_SPACE_GB=5
```

#### Grant Docker Permissions

If you get permission errors, add your user to the docker group:

```bash
sudo usermod -aG docker $USER
# Log out and back in for changes to take effect
```

#### Manual Backup

Run the backup script manually:

```bash
cd /path/to/laya-backbone

# Backup all Docker volumes
./scripts/backup/docker_volume_backup.sh

# Backup a specific volume
./scripts/backup/docker_volume_backup.sh my_volume_name

# List all Docker volumes
./scripts/backup/docker_volume_backup.sh --list
```

#### Automated Backup (Cron)

1. Create backup directory:
```bash
sudo mkdir -p /var/backups/docker-volumes
sudo chmod 700 /var/backups/docker-volumes
```

2. Edit crontab:
```bash
crontab -e
```

3. Add the following line (adjust paths as needed):
```
30 2 * * * /path/to/laya-backbone/scripts/backup/docker_volume_backup.sh >> /var/log/docker_volume_backup.log 2>&1
```

Or use an environment file:
```
30 2 * * * /bin/bash -c 'source /path/to/.env && /path/to/laya-backbone/scripts/backup/docker_volume_backup.sh' >> /var/log/docker_volume_backup.log 2>&1
```

### Usage

**Basic Usage:**

```bash
# Backup all Docker volumes
./scripts/backup/docker_volume_backup.sh

# Backup specific volume
./scripts/backup/docker_volume_backup.sh laya_mysql_data

# List available volumes
./scripts/backup/docker_volume_backup.sh --list

# Show help
./scripts/backup/docker_volume_backup.sh --help
```

### Restore Docker Volumes

To restore a Docker volume from backup:

```bash
# Create the volume if it doesn't exist
docker volume create my_volume_name

# Restore from backup using a temporary container
docker run --rm \
  -v my_volume_name:/volume \
  -v /var/backups/docker-volumes:/backup \
  alpine:latest \
  tar xzf /backup/my_volume_name_20260216_023000.tar.gz -C /volume

# Verify restored data
docker run --rm \
  -v my_volume_name:/volume \
  alpine:latest \
  ls -la /volume
```

**Important Restore Notes:**
- Always stop containers using the volume before restoring
- Existing data in the volume will be overwritten
- Test restores in a non-production environment first
- Verify data integrity after restore

### Backup Schedule Integration

The Docker volume backup runs at 2:30 AM, between database backups and retention policy:

```
2:00 AM  → MySQL backup
2:15 AM  → PostgreSQL backup
2:30 AM  → Docker volume backup ← This script
3:00 AM  → Retention policy cleanup
3:30 AM  → Photo/upload backup
4:00 AM  → Backup verification
5:00 AM  → Remote storage sync
```

### Monitoring

Check Docker volume backup logs:
```bash
tail -f /var/log/docker_volume_backup.log
```

View backup results:
```bash
grep "SUCCESS\|ERROR" /var/log/docker_volume_backup.log
```

Check backup files:
```bash
ls -lh /var/backups/docker-volumes/
```

View backup summary:
```bash
grep "Backup Summary" -A 5 /var/log/docker_volume_backup.log
```

### Security Best Practices

1. **Access Control**
   - Set restrictive permissions on backup directory (700)
   - Use dedicated backup user with minimal Docker privileges
   - Protect backup files from unauthorized access (600)

2. **Docker Security**
   - Keep Docker daemon updated
   - Use official Alpine image for backups
   - Verify image signatures before use
   - Audit Docker access logs

3. **Storage Security**
   - Store backups on separate physical drives when possible
   - Consider encrypting sensitive volume backups
   - Implement access logging for backup directories
   - Use remote backup for off-site copies

4. **Monitoring**
   - Monitor backup logs regularly
   - Set up alerts for backup failures
   - Check disk space to prevent backup failures
   - Verify backups periodically

### Troubleshooting

#### Docker Not Running

```bash
# Check Docker status
docker info

# Start Docker (Linux systemd)
sudo systemctl start docker

# Start Docker (macOS)
open -a Docker
```

#### Permission Denied

```bash
# Add user to docker group
sudo usermod -aG docker $USER

# Or run with sudo (not recommended for cron)
sudo ./scripts/backup/docker_volume_backup.sh
```

#### No Volumes Found

```bash
# List all Docker volumes
docker volume ls

# Create a test volume
docker volume create test_volume

# Backup the test volume
./scripts/backup/docker_volume_backup.sh test_volume
```

#### Backup File Corrupted

```bash
# Test tar.gz integrity
tar -tzf /var/backups/docker-volumes/my_volume_20260216_023000.tar.gz

# If corrupted, check disk health and re-run backup
df -h /var/backups/docker-volumes
```

#### Low Disk Space

```bash
# Check available space
df -h /var/backups/docker-volumes

# Clean up old backups manually
find /var/backups/docker-volumes -name "*.tar.gz" -mtime +30 -delete

# Or run retention policy
./scripts/backup/retention_policy.sh
```

#### Alpine Image Pull Fails

```bash
# Manually pull Alpine image
docker pull alpine:latest

# Use a different image if needed
export DOCKER_IMAGE=busybox:latest
./scripts/backup/docker_volume_backup.sh
```

### Common Volume Backup Examples

**Database Volumes:**
```bash
# Backup MySQL data volume
./scripts/backup/docker_volume_backup.sh mysql_data

# Backup PostgreSQL data volume
./scripts/backup/docker_volume_backup.sh postgres_data
```

**Application Volumes:**
```bash
# Backup application uploads
./scripts/backup/docker_volume_backup.sh app_uploads

# Backup configuration
./scripts/backup/docker_volume_backup.sh app_config
```

**Multiple Volumes:**
```bash
# Backup all volumes at once
./scripts/backup/docker_volume_backup.sh
```

### Integration with Retention Policy

Docker volume backups are automatically included in the retention policy:

- The retention_policy.sh script can be extended to handle Docker volume backups
- Backups follow the same naming convention: `{volume_name}_{timestamp}.tar.gz`
- Subject to the same retention rules: 7 daily, 4 weekly, 12 monthly

## Future Enhancements

- [x] PostgreSQL backup script (scheduled at 2:15 AM)
- [x] Automated backup retention/cleanup (7 daily, 4 weekly, 12 monthly)
- [x] MySQL and PostgreSQL restore scripts with safety checks
- [x] Backup verification script (restore to temp DB)
- [x] Remote backup to S3 or rsync to remote server
- [x] Photo and upload file backups
- [x] Docker volume backups
- [ ] Email/Slack notifications on failure
- [ ] Backup encryption
- [ ] Incremental backups
- [ ] Disaster recovery runbook

## References

- [mysqldump Documentation](https://dev.mysql.com/doc/refman/8.0/en/mysqldump.html)
- [MySQL Backup and Recovery](https://dev.mysql.com/doc/refman/8.0/en/backup-and-recovery.html)
- [pg_dump Documentation](https://www.postgresql.org/docs/current/app-pgdump.html)
- [PostgreSQL Backup and Restore](https://www.postgresql.org/docs/current/backup.html)
- [AWS CLI S3 Sync Documentation](https://docs.aws.amazon.com/cli/latest/reference/s3/sync.html)
- [AWS CLI Installation Guide](https://docs.aws.amazon.com/cli/latest/userguide/install-cliv2.html)
- [Rsync Documentation](https://rsync.samba.org/documentation.html)
- [SSH Key-Based Authentication](https://www.ssh.com/academy/ssh/keygen)
- [Cron Configuration Guide](https://crontab.guru/)

## Support

For issues or questions, please refer to the LAYA documentation or contact the infrastructure team.

---
**Last Updated:** 2026-02-16
**Maintained by:** LAYA Infrastructure Team
