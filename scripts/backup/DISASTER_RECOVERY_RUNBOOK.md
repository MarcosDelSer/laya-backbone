# LAYA Disaster Recovery Runbook

**Document Version:** 1.0
**Last Updated:** 2026-02-16
**Maintained by:** LAYA Infrastructure Team
**Classification:** CONFIDENTIAL

---

## Table of Contents

1. [Overview](#overview)
2. [Emergency Contacts](#emergency-contacts)
3. [Recovery Objectives](#recovery-objectives)
4. [Backup Architecture](#backup-architecture)
5. [Disaster Scenarios](#disaster-scenarios)
6. [Pre-Recovery Checklist](#pre-recovery-checklist)
7. [Recovery Procedures](#recovery-procedures)
8. [Post-Recovery Verification](#post-recovery-verification)
9. [Testing Schedule](#testing-schedule)
10. [Appendices](#appendices)

---

## Overview

### Purpose

This runbook provides step-by-step procedures for recovering the LAYA Daycare Management System from catastrophic failures, including:
- Complete server/infrastructure failure
- Database corruption or data loss
- Ransomware or security incidents
- Natural disasters affecting primary datacenter
- Accidental data deletion

### Scope

This runbook covers recovery of:
- MySQL database
- PostgreSQL database (if applicable)
- User-uploaded photos and files
- Docker volumes and container data
- Application configuration files
- System state and settings

### When to Use This Runbook

Activate disaster recovery procedures when:
- ❌ Primary database is corrupted or inaccessible
- ❌ Critical data loss has occurred (>1 hour of data)
- ❌ Complete infrastructure failure
- ❌ Security incident requiring full system restore
- ❌ Data center outage with no estimated recovery time
- ❌ Multiple simultaneous system failures

**DO NOT** use for:
- Minor data corrections (use point-in-time restore instead)
- Single table recovery (use selective restore)
- Testing purposes (use dedicated test environment)

---

## Emergency Contacts

### Primary Contacts

| Role | Name | Phone | Email | Availability |
|------|------|-------|-------|--------------|
| Infrastructure Lead | [NAME] | [PHONE] | [EMAIL] | 24/7 |
| Database Administrator | [NAME] | [PHONE] | [EMAIL] | 24/7 |
| Security Officer | [NAME] | [PHONE] | [EMAIL] | 24/7 |
| Application Lead | [NAME] | [PHONE] | [EMAIL] | Business hours |
| Executive Sponsor | [NAME] | [PHONE] | [EMAIL] | Emergency only |

### Escalation Path

1. **Level 1 (0-15 minutes):** Infrastructure Lead
2. **Level 2 (15-30 minutes):** Database Administrator + Security Officer
3. **Level 3 (30-60 minutes):** Application Lead + Management
4. **Level 4 (60+ minutes):** Executive Sponsor

### External Vendors

| Vendor | Service | Contact | SLA |
|--------|---------|---------|-----|
| AWS Support | Cloud Infrastructure | [PHONE/CASE] | 1 hour |
| Database Vendor | MySQL/PostgreSQL | [SUPPORT] | 4 hours |
| Hosting Provider | Server/Network | [SUPPORT] | 2 hours |

---

## Recovery Objectives

### Recovery Time Objective (RTO)

**Target RTO:** 4 hours (from disaster declaration to system operational)

| Component | RTO Target | Priority |
|-----------|------------|----------|
| MySQL Database | 1 hour | Critical |
| PostgreSQL Database | 1 hour | Critical |
| Docker Services | 30 minutes | Critical |
| Photo/Upload Files | 2 hours | High |
| Application Configuration | 30 minutes | High |
| Full System Verification | 4 hours | Medium |

### Recovery Point Objective (RPO)

**Target RPO:** 24 hours (maximum acceptable data loss)

| Backup Type | Frequency | RPO |
|-------------|-----------|-----|
| Database Backups | Daily at 2:00 AM | 24 hours |
| Docker Volumes | Daily at 2:30 AM | 24 hours |
| Photo/Upload Files | Daily at 3:30 AM | 24 hours |
| Remote Backups | Daily at 5:00 AM | 24 hours |

**Best Case RPO:** Last successful backup (typically <24 hours)
**Worst Case RPO:** 24 hours + time since last backup

---

## Backup Architecture

### Backup Schedule

```
2:00 AM  → MySQL Backup (mysqldump + gzip)
2:15 AM  → PostgreSQL Backup (pg_dump + gzip)
2:30 AM  → Docker Volume Backup (tar + gzip)
3:00 AM  → Retention Policy Cleanup (7 daily, 4 weekly, 12 monthly)
3:30 AM  → Photo/Upload Backup (rsync --delete)
4:00 AM  → Backup Verification (restore to temp DB)
5:00 AM  → Remote Storage Sync (S3 or rsync to remote server)
```

### Backup Locations

| Backup Type | Primary Location | Remote Location | Retention |
|-------------|------------------|-----------------|-----------|
| MySQL | `/var/backups/mysql` | S3 or Remote Server | 7D/4W/12M |
| PostgreSQL | `/var/backups/postgres` | S3 or Remote Server | 7D/4W/12M |
| Docker Volumes | `/var/backups/docker-volumes` | S3 or Remote Server | 7D/4W/12M |
| Photos | `/var/backups/photos` | S3 or Remote Server | Mirror |
| Uploads | `/var/backups/uploads` | S3 or Remote Server | Mirror |

**Retention Policy:**
- **Daily:** Last 7 days (all backups)
- **Weekly:** Last 4 weeks (Sunday backups only)
- **Monthly:** Last 12 months (1st of month backups only)

### Backup Verification

All backups are automatically verified daily at 4:00 AM:
- File integrity checks (gzip -t)
- Restore to temporary database
- Table count verification
- Database size validation
- Row count sampling
- Automatic cleanup of temporary resources

---

## Disaster Scenarios

### Scenario 1: Complete Database Loss

**Symptoms:**
- Database server unresponsive
- Database files corrupted
- All data inaccessible

**Impact:** Critical - Complete service outage

**Recovery Procedure:** See [Section 7.1](#71-database-recovery)

---

### Scenario 2: Partial Data Loss/Corruption

**Symptoms:**
- Specific tables corrupted
- Recent transactions missing
- Data integrity errors

**Impact:** High - Service degraded or partial outage

**Recovery Procedure:** See [Section 7.2](#72-partial-database-recovery)

---

### Scenario 3: Complete Infrastructure Failure

**Symptoms:**
- Server hardware failure
- Complete datacenter outage
- All services down

**Impact:** Critical - Complete service outage

**Recovery Procedure:** See [Section 7.3](#73-complete-system-recovery)

---

### Scenario 4: Ransomware/Security Incident

**Symptoms:**
- Files encrypted or deleted
- Unauthorized access detected
- System compromise confirmed

**Impact:** Critical - Service outage + security breach

**Recovery Procedure:** See [Section 7.4](#74-security-incident-recovery)

---

### Scenario 5: Photo/Upload File Loss

**Symptoms:**
- User photos missing
- Upload directory corrupted
- File storage failure

**Impact:** Medium - Data loss but service operational

**Recovery Procedure:** See [Section 7.5](#75-file-storage-recovery)

---

## Pre-Recovery Checklist

### Before Starting Recovery

- [ ] **Declare Disaster** - Notify stakeholders and activate incident response
- [ ] **Assess Damage** - Document what is lost/corrupted
- [ ] **Stop Further Damage** - Isolate affected systems
- [ ] **Activate Team** - Contact emergency response team
- [ ] **Secure Environment** - Ensure recovery environment is safe
- [ ] **Document Everything** - Start incident log with timestamps
- [ ] **Identify Backup** - Locate most recent valid backup
- [ ] **Verify Backup** - Confirm backup integrity before restore
- [ ] **Prepare Environment** - Ensure sufficient disk space and resources
- [ ] **Communication Plan** - Notify users of expected downtime

### Security Considerations

- [ ] If security incident: Ensure attacker is locked out
- [ ] Change all passwords before recovery
- [ ] Review access logs for suspicious activity
- [ ] Verify backup files are not compromised
- [ ] Enable enhanced logging during recovery
- [ ] Prepare to enable 2FA/MFA after recovery

### Resource Requirements

- [ ] Sufficient disk space (2x backup size recommended)
- [ ] Database credentials (admin level)
- [ ] SSH access to all servers
- [ ] Access to backup locations (local + remote)
- [ ] Docker running and accessible
- [ ] Network connectivity verified
- [ ] DNS records accessible
- [ ] SSL certificates available

---

## Recovery Procedures

### 7.1 Database Recovery

#### 7.1.1 MySQL Database Recovery

**Recovery Time:** ~1 hour
**Prerequisites:** MySQL server installed and running

**Step 1: Identify Backup to Restore**

```bash
# List available MySQL backups
cd /var/backups/mysql
ls -lht laya_db_*.sql.gz | head -20

# Or use the restore script to list backups
/path/to/laya-backbone/scripts/backup/mysql_restore.sh --list

# Verify backup integrity
gzip -t /var/backups/mysql/laya_db_20260216_020000.sql.gz
echo "Exit code: $?"  # Should be 0 for success
```

**Step 2: Stop Application Services**

```bash
# Stop Docker containers accessing the database
docker-compose down

# Or stop specific containers
docker stop laya_web laya_api laya_worker

# Verify no connections to database
mysql -h localhost -u root -p -e "SHOW PROCESSLIST"
```

**Step 3: Restore Database (Interactive Method)**

```bash
# Set environment variables
export MYSQL_HOST=localhost
export MYSQL_PORT=3306
export MYSQL_USER=root
export MYSQL_PASSWORD=your_root_password

# Run restore script (interactive mode with safety checks)
/path/to/laya-backbone/scripts/backup/mysql_restore.sh \
  /var/backups/mysql/laya_db_20260216_020000.sql.gz

# The script will:
# - Show current database size
# - Warn about data loss
# - Ask for confirmation (type 'yes')
# - Verify backup integrity
# - Drop existing database
# - Create new database with UTF-8
# - Restore data
# - Verify table count and size
# - Show restore summary
```

**Step 4: Restore Database (Non-Interactive Method)**

```bash
# For automated recovery (USE WITH CAUTION)
export MYSQL_HOST=localhost
export MYSQL_PORT=3306
export MYSQL_USER=root
export MYSQL_PASSWORD=your_root_password

/path/to/laya-backbone/scripts/backup/mysql_restore.sh \
  /var/backups/mysql/laya_db_20260216_020000.sql.gz \
  --yes

# Monitor progress
tail -f /var/log/mysql_restore.log
```

**Step 5: Verify Database Restore**

```bash
# Connect to database
mysql -h localhost -u root -p laya_db

# Verify table count
SHOW TABLES;
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = 'laya_db';

# Check database size
SELECT
  table_schema AS 'Database',
  ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)'
FROM information_schema.tables
WHERE table_schema = 'laya_db'
GROUP BY table_schema;

# Verify recent data
SELECT COUNT(*) FROM users;
SELECT MAX(created_at) FROM users;
SELECT MAX(updated_at) FROM users;

# Check critical tables
SELECT COUNT(*) FROM children;
SELECT COUNT(*) FROM staff;
SELECT COUNT(*) FROM attendance;

# Exit MySQL
EXIT;
```

**Step 6: Restart Application Services**

```bash
# Start Docker containers
docker-compose up -d

# Verify containers are running
docker-compose ps

# Check application logs
docker-compose logs -f laya_api

# Test database connectivity
docker exec laya_api python -c "from app import db; print(db.session.execute('SELECT 1').scalar())"
```

**Step 7: Verify Application Functionality**

```bash
# Test API endpoints
curl http://localhost:8000/health
curl http://localhost:8000/api/v1/status

# Test database queries through API
curl http://localhost:8000/api/v1/users/count

# Check web interface
curl -I http://localhost:3000
```

---

#### 7.1.2 PostgreSQL Database Recovery

**Recovery Time:** ~1 hour
**Prerequisites:** PostgreSQL server installed and running

**Step 1: Identify Backup to Restore**

```bash
# List available PostgreSQL backups
cd /var/backups/postgres
ls -lht laya_db_*.sql.gz | head -20

# Or use the restore script
/path/to/laya-backbone/scripts/backup/postgres_restore.sh --list

# Verify backup integrity
gzip -t /var/backups/postgres/laya_db_20260216_021500.sql.gz
```

**Step 2: Stop Application Services**

```bash
# Stop all services accessing PostgreSQL
docker-compose down

# Verify no active connections
psql -h localhost -U postgres -c "SELECT * FROM pg_stat_activity WHERE datname = 'laya_db';"
```

**Step 3: Restore Database**

```bash
# Set environment variables
export PGHOST=localhost
export PGPORT=5432
export PGUSER=postgres
export PGPASSWORD=your_postgres_password

# Run restore script (interactive mode)
/path/to/laya-backbone/scripts/backup/postgres_restore.sh \
  /var/backups/postgres/laya_db_20260216_021500.sql.gz

# The script will:
# - Show current database size
# - Warn about data loss
# - Ask for confirmation (type 'yes')
# - Verify backup integrity
# - Terminate active connections
# - Drop existing database
# - Restore database from backup
# - Verify restoration
# - Show restore summary
```

**Step 4: Verify Database Restore**

```bash
# Connect to database
psql -h localhost -U postgres laya_db

# Verify table count
\dt
SELECT COUNT(*) FROM information_schema.tables
WHERE table_schema = 'public';

# Check database size
SELECT
  pg_size_pretty(pg_database_size('laya_db')) AS database_size;

# Verify recent data
SELECT COUNT(*) FROM users;
SELECT MAX(created_at) FROM users;

# Exit PostgreSQL
\q
```

**Step 5: Restart Services and Verify**

Follow the same steps as MySQL recovery (Step 6-7).

---

### 7.2 Partial Database Recovery

#### Scenario: Single Table Corruption or Data Loss

**Recovery Time:** ~30 minutes
**Use Case:** When only specific tables are corrupted, not entire database

**Step 1: Identify Affected Table(s)**

```bash
# List tables in database
mysql -h localhost -u root -p laya_db -e "SHOW TABLES;"

# Check table status
mysql -h localhost -u root -p laya_db -e "CHECK TABLE table_name;"

# Identify last known good data
mysql -h localhost -u root -p laya_db -e "SELECT MAX(updated_at) FROM table_name;"
```

**Step 2: Extract Table from Backup**

```bash
# Decompress backup to temporary location
mkdir -p /tmp/db_restore
gunzip -c /var/backups/mysql/laya_db_20260216_020000.sql.gz > /tmp/db_restore/backup.sql

# Extract specific table schema and data
grep -A 10000 "CREATE TABLE \`table_name\`" /tmp/db_restore/backup.sql > /tmp/db_restore/table_only.sql

# Or use mysqldump on the temp backup
# (First restore to temp database using verify_backup.sh)
/path/to/laya-backbone/scripts/backup/verify_backup.sh \
  /var/backups/mysql/laya_db_20260216_020000.sql.gz

# Extract table from temp database
mysqldump -h localhost -u root -p laya_db_verify_temp table_name > /tmp/db_restore/table_only.sql

# Clean up temp database
mysql -h localhost -u root -p -e "DROP DATABASE IF EXISTS laya_db_verify_temp;"
```

**Step 3: Backup Current Table (Safety)**

```bash
# Backup current table state before restore
mysqldump -h localhost -u root -p laya_db table_name > /tmp/db_restore/table_current_backup.sql
```

**Step 4: Restore Table**

```bash
# Option 1: Replace entire table
mysql -h localhost -u root -p laya_db << EOF
DROP TABLE IF EXISTS table_name;
SOURCE /tmp/db_restore/table_only.sql;
EOF

# Option 2: Restore specific rows (manual merge)
# Review the extracted data and selectively insert
mysql -h localhost -u root -p laya_db < /tmp/db_restore/table_only.sql
```

**Step 5: Verify Table Restore**

```bash
# Check row count
mysql -h localhost -u root -p laya_db -e "SELECT COUNT(*) FROM table_name;"

# Verify data integrity
mysql -h localhost -u root -p laya_db -e "SELECT * FROM table_name LIMIT 10;"

# Check relationships/foreign keys
mysql -h localhost -u root -p laya_db -e "CHECK TABLE table_name;"
```

**Step 6: Cleanup**

```bash
# Remove temporary files
rm -rf /tmp/db_restore
```

---

### 7.3 Complete System Recovery

#### Scenario: New Server/Infrastructure Recovery from Scratch

**Recovery Time:** ~4 hours
**Use Case:** Complete server failure, datacenter migration, or new infrastructure setup

**Phase 1: Infrastructure Setup (60 minutes)**

**Step 1: Provision New Server**

```bash
# Update system packages
sudo apt update && sudo apt upgrade -y

# Install essential tools
sudo apt install -y git curl wget vim build-essential

# Set hostname
sudo hostnamectl set-hostname laya-production

# Configure timezone
sudo timedatectl set-timezone America/New_York
```

**Step 2: Install Docker**

```bash
# Install Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sudo sh get-docker.sh

# Add user to docker group
sudo usermod -aG docker $USER

# Start and enable Docker
sudo systemctl start docker
sudo systemctl enable docker

# Verify Docker installation
docker --version
docker run hello-world
```

**Step 3: Install Database Servers**

For MySQL:
```bash
# Install MySQL 8.0
sudo apt install -y mysql-server mysql-client

# Secure MySQL installation
sudo mysql_secure_installation

# Start and enable MySQL
sudo systemctl start mysql
sudo systemctl enable mysql

# Verify MySQL
mysql --version
```

For PostgreSQL:
```bash
# Install PostgreSQL
sudo apt install -y postgresql postgresql-contrib

# Start and enable PostgreSQL
sudo systemctl start postgresql
sudo systemctl enable postgresql

# Verify PostgreSQL
psql --version
```

**Step 4: Install Backup Tools**

```bash
# Install required tools
sudo apt install -y rsync gzip bc

# Install AWS CLI (if using S3)
curl "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o "awscliv2.zip"
unzip awscliv2.zip
sudo ./aws/install

# Configure AWS CLI
aws configure
# Enter AWS Access Key ID
# Enter AWS Secret Access Key
# Enter region (e.g., us-east-1)
```

**Step 5: Clone Application Repository**

```bash
# Create application directory
sudo mkdir -p /opt/laya
sudo chown $USER:$USER /opt/laya

# Clone repository
cd /opt/laya
git clone https://github.com/your-org/laya-backbone.git
cd laya-backbone

# Checkout specific version/tag (production release)
git checkout production  # or specific tag like v1.2.3
```

---

**Phase 2: Restore Backups (120 minutes)**

**Step 1: Create Backup Directories**

```bash
# Create backup directories
sudo mkdir -p /var/backups/mysql
sudo mkdir -p /var/backups/postgres
sudo mkdir -p /var/backups/docker-volumes
sudo mkdir -p /var/backups/photos
sudo mkdir -p /var/backups/uploads

# Set permissions
sudo chmod 700 /var/backups/mysql
sudo chmod 700 /var/backups/postgres
sudo chmod 700 /var/backups/docker-volumes
sudo chmod 700 /var/backups/photos
sudo chmod 700 /var/backups/uploads

# Create log directory
sudo mkdir -p /var/log
sudo chmod 755 /var/log
```

**Step 2: Download Backups from Remote Storage**

**From AWS S3:**
```bash
# Download MySQL backups
aws s3 sync s3://my-laya-backups/mysql/ /var/backups/mysql/

# Download PostgreSQL backups
aws s3 sync s3://my-laya-backups/postgres/ /var/backups/postgres/

# Download Docker volume backups
aws s3 sync s3://my-laya-backups/docker-volumes/ /var/backups/docker-volumes/

# Verify downloads
ls -lh /var/backups/mysql/
ls -lh /var/backups/postgres/
```

**From Remote Server (rsync):**
```bash
# Set up SSH key
mkdir -p ~/.ssh
chmod 700 ~/.ssh
# Copy your SSH private key to ~/.ssh/id_rsa
chmod 600 ~/.ssh/id_rsa

# Download MySQL backups
rsync -avz -e "ssh -i ~/.ssh/id_rsa" \
  backup_user@backup.example.com:/backups/laya/mysql/ \
  /var/backups/mysql/

# Download PostgreSQL backups
rsync -avz -e "ssh -i ~/.ssh/id_rsa" \
  backup_user@backup.example.com:/backups/laya/postgres/ \
  /var/backups/postgres/

# Download Docker volume backups
rsync -avz -e "ssh -i ~/.ssh/id_rsa" \
  backup_user@backup.example.com:/backups/laya/docker-volumes/ \
  /var/backups/docker-volumes/
```

**Step 3: Restore MySQL Database**

```bash
# Find latest backup
LATEST_MYSQL_BACKUP=$(ls -t /var/backups/mysql/laya_db_*.sql.gz | head -1)
echo "Restoring: $LATEST_MYSQL_BACKUP"

# Verify backup integrity
gzip -t "$LATEST_MYSQL_BACKUP"

# Create database
mysql -h localhost -u root -p << EOF
CREATE DATABASE IF NOT EXISTS laya_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF

# Restore database
gunzip -c "$LATEST_MYSQL_BACKUP" | mysql -h localhost -u root -p laya_db

# Verify restoration
mysql -h localhost -u root -p laya_db -e "SHOW TABLES; SELECT COUNT(*) FROM users;"
```

**Step 4: Restore PostgreSQL Database**

```bash
# Find latest backup
LATEST_PG_BACKUP=$(ls -t /var/backups/postgres/laya_db_*.sql.gz | head -1)
echo "Restoring: $LATEST_PG_BACKUP"

# Verify backup integrity
gzip -t "$LATEST_PG_BACKUP"

# Restore database (backup includes CREATE DATABASE)
gunzip -c "$LATEST_PG_BACKUP" | psql -h localhost -U postgres

# Verify restoration
psql -h localhost -U postgres laya_db -c "\dt; SELECT COUNT(*) FROM users;"
```

**Step 5: Restore Docker Volumes**

```bash
# List available volume backups
ls -lh /var/backups/docker-volumes/

# Restore each volume
for BACKUP_FILE in /var/backups/docker-volumes/*.tar.gz; do
  # Extract volume name from filename
  VOLUME_NAME=$(basename "$BACKUP_FILE" | sed 's/_[0-9]*\.tar\.gz$//')

  echo "Restoring volume: $VOLUME_NAME"

  # Create volume
  docker volume create "$VOLUME_NAME"

  # Restore data
  docker run --rm \
    -v "$VOLUME_NAME:/volume" \
    -v /var/backups/docker-volumes:/backup \
    alpine:latest \
    tar xzf "/backup/$(basename "$BACKUP_FILE")" -C /volume

  # Verify restore
  docker run --rm \
    -v "$VOLUME_NAME:/volume" \
    alpine:latest \
    ls -la /volume
done
```

**Step 6: Restore Photo/Upload Files**

```bash
# Download photo/upload backups from remote storage
# From S3:
aws s3 sync s3://my-laya-backups/photos/ /var/www/laya/uploads/photos/
aws s3 sync s3://my-laya-backups/uploads/ /var/www/laya/uploads/files/

# From remote server:
rsync -avz -e "ssh -i ~/.ssh/id_rsa" \
  backup_user@backup.example.com:/backups/laya/photos/ \
  /var/www/laya/uploads/photos/

rsync -avz -e "ssh -i ~/.ssh/id_rsa" \
  backup_user@backup.example.com:/backups/laya/uploads/ \
  /var/www/laya/uploads/files/

# Set proper permissions
sudo chown -R www-data:www-data /var/www/laya/uploads
sudo chmod -R 755 /var/www/laya/uploads
```

---

**Phase 3: Application Configuration (60 minutes)**

**Step 1: Configure Environment Variables**

```bash
# Create .env file
cd /opt/laya/laya-backbone
cp .env.example .env

# Edit .env file with production values
vim .env

# Set critical variables:
# - Database credentials
# - API keys
# - Secret keys
# - External service URLs
# - Email configuration
# - Storage paths
```

**Step 2: Configure Database Users**

```bash
# Create application database user (MySQL)
mysql -h localhost -u root -p << EOF
CREATE USER 'laya_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON laya_db.* TO 'laya_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# Create backup user (MySQL)
mysql -h localhost -u root -p << EOF
CREATE USER 'backup_user'@'localhost' IDENTIFIED BY 'backup_password';
GRANT SELECT, LOCK TABLES, SHOW VIEW, EVENT, TRIGGER ON laya_db.* TO 'backup_user'@'localhost';
FLUSH PRIVILEGES;
EOF

# For PostgreSQL:
psql -h localhost -U postgres << EOF
CREATE USER laya_user WITH PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE laya_db TO laya_user;
\c laya_db
GRANT ALL PRIVILEGES ON ALL TABLES IN SCHEMA public TO laya_user;
GRANT ALL PRIVILEGES ON ALL SEQUENCES IN SCHEMA public TO laya_user;
EOF
```

**Step 3: Start Application Services**

```bash
# Build Docker containers
cd /opt/laya/laya-backbone
docker-compose build

# Start services
docker-compose up -d

# Verify containers are running
docker-compose ps

# Check logs
docker-compose logs -f
```

**Step 4: Configure SSL/TLS**

```bash
# Install certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d laya.example.com -d www.laya.example.com

# Verify SSL configuration
sudo nginx -t
sudo systemctl reload nginx

# Test HTTPS access
curl -I https://laya.example.com
```

**Step 5: Configure Backup Cron Jobs**

```bash
# Copy backup scripts to system location (optional)
sudo cp /opt/laya/laya-backbone/scripts/backup/*.sh /usr/local/bin/

# Make scripts executable
sudo chmod +x /usr/local/bin/*.sh

# Edit crontab
crontab -e

# Add backup schedules from crontab.example
# Copy content from /opt/laya/laya-backbone/scripts/backup/crontab.example
```

---

**Phase 4: Verification and Testing (60 minutes)**

Follow [Section 8: Post-Recovery Verification](#post-recovery-verification) procedures.

---

### 7.4 Security Incident Recovery

#### Scenario: Ransomware, Breach, or Compromise

**Recovery Time:** ~6 hours (includes security hardening)
**Prerequisites:** Incident contained, attacker access revoked

**⚠️ WARNING:** This procedure assumes the security incident has been contained and the attacker no longer has access. If unsure, consult security team before proceeding.

**Phase 1: Incident Assessment (30 minutes)**

**Step 1: Document Incident**

```bash
# Create incident report directory
mkdir -p /var/log/incident-$(date +%Y%m%d)

# Document timeline
cat > /var/log/incident-$(date +%Y%m%d)/timeline.txt << EOF
Incident Discovery: $(date)
Systems Affected: [LIST SYSTEMS]
Attack Vector: [DESCRIBE HOW BREACH OCCURRED]
Data Accessed: [LIST DATA POTENTIALLY ACCESSED]
Current Status: [CONTAINED/ONGOING]
EOF

# Preserve evidence
# Save system logs
sudo journalctl -xe > /var/log/incident-$(date +%Y%m%d)/system_logs.txt

# Save application logs
docker-compose logs --no-color > /var/log/incident-$(date +%Y%m%d)/application_logs.txt

# Save database logs (if accessible)
sudo cp /var/log/mysql/*.log /var/log/incident-$(date +%Y%m%d)/
```

**Step 2: Identify Clean Backup**

```bash
# Identify backup created BEFORE incident
# Review backup verification logs
grep "SUCCESS" /var/log/backup_verification.log | tail -20

# Determine last known good backup
# Choose backup from before incident timeline
CLEAN_BACKUP="/var/backups/mysql/laya_db_20260215_020000.sql.gz"

# Verify backup is not compromised
gzip -t "$CLEAN_BACKUP"
```

**Phase 2: Secure Environment (60 minutes)**

**Step 1: Isolate Systems**

```bash
# Stop all services
docker-compose down

# Disconnect from network (if needed)
# sudo ip link set eth0 down

# Kill all user sessions
sudo pkill -KILL -u www-data
sudo pkill -KILL -u laya_user
```

**Step 2: Change ALL Credentials**

```bash
# Generate new passwords
NEW_ROOT_PASS=$(openssl rand -base64 32)
NEW_DB_PASS=$(openssl rand -base64 32)
NEW_APP_PASS=$(openssl rand -base64 32)

# Change MySQL root password
mysql -h localhost -u root -p << EOF
ALTER USER 'root'@'localhost' IDENTIFIED BY '$NEW_ROOT_PASS';
FLUSH PRIVILEGES;
EOF

# Change application user password
mysql -h localhost -u root -p << EOF
ALTER USER 'laya_user'@'localhost' IDENTIFIED BY '$NEW_DB_PASS';
FLUSH PRIVILEGES;
EOF

# Update .env file with new credentials
sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$NEW_DB_PASS/" /opt/laya/laya-backbone/.env

# Store new passwords securely
echo "Root Password: $NEW_ROOT_PASS" >> /root/new_passwords.txt
echo "DB Password: $NEW_DB_PASS" >> /root/new_passwords.txt
chmod 600 /root/new_passwords.txt

# Rotate API keys
# Generate new JWT secret
NEW_JWT_SECRET=$(openssl rand -hex 64)
sed -i "s/JWT_SECRET=.*/JWT_SECRET=$NEW_JWT_SECRET/" /opt/laya/laya-backbone/.env

# Rotate other API keys
# - AWS keys
# - Third-party service keys
# - Email service keys
```

**Step 3: Security Hardening**

```bash
# Update all packages
sudo apt update && sudo apt upgrade -y

# Install security updates
sudo apt install -y unattended-upgrades
sudo dpkg-reconfigure -plow unattended-upgrades

# Configure firewall
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow 22/tcp  # SSH
sudo ufw allow 80/tcp  # HTTP
sudo ufw allow 443/tcp # HTTPS
sudo ufw allow 3306/tcp from 127.0.0.1 # MySQL (local only)
sudo ufw allow 5432/tcp from 127.0.0.1 # PostgreSQL (local only)
sudo ufw enable

# Install fail2ban
sudo apt install -y fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban

# Disable unused services
sudo systemctl disable telnet
sudo systemctl stop telnet

# Review SSH configuration
sudo vim /etc/ssh/sshd_config
# Ensure:
# - PasswordAuthentication no
# - PermitRootLogin no
# - PubkeyAuthentication yes
sudo systemctl restart sshd
```

**Phase 3: Clean System Recovery**

**Step 1: Wipe Compromised Data**

```bash
# Backup compromised data for forensics
sudo tar czf /var/log/incident-$(date +%Y%m%d)/compromised_data.tar.gz \
  /var/lib/mysql \
  /opt/laya \
  /var/www/laya

# Stop database
sudo systemctl stop mysql
sudo systemctl stop postgresql

# Remove compromised database files
sudo rm -rf /var/lib/mysql/*
sudo rm -rf /var/lib/postgresql/*/main/*

# Reinitialize databases
sudo mysql_install_db --user=mysql
sudo systemctl start mysql
```

**Step 2: Restore from Clean Backup**

Follow the procedures in [Section 7.3: Complete System Recovery](#73-complete-system-recovery), but use the clean backup identified in Phase 1.

**Step 3: Scan for Malware/Backdoors**

```bash
# Install ClamAV
sudo apt install -y clamav clamav-daemon

# Update virus definitions
sudo freshclam

# Scan application directory
sudo clamscan -r /opt/laya/laya-backbone

# Scan uploads directory
sudo clamscan -r /var/www/laya/uploads

# Review scan results
# Remove any infected files
```

**Phase 4: Enhanced Monitoring**

**Step 1: Enable Comprehensive Logging**

```bash
# Enable MySQL query logging (temporarily)
mysql -h localhost -u root -p << EOF
SET GLOBAL general_log = 'ON';
SET GLOBAL general_log_file = '/var/log/mysql/query.log';
EOF

# Enable PostgreSQL logging
sudo vim /etc/postgresql/*/main/postgresql.conf
# Set: log_statement = 'all'
sudo systemctl restart postgresql

# Configure application logging
# Edit .env to set LOG_LEVEL=DEBUG
```

**Step 2: Setup Intrusion Detection**

```bash
# Install AIDE (Advanced Intrusion Detection Environment)
sudo apt install -y aide

# Initialize AIDE database
sudo aideinit

# Copy database to production location
sudo cp /var/lib/aide/aide.db.new /var/lib/aide/aide.db

# Run integrity check daily
cat > /etc/cron.daily/aide << 'EOF'
#!/bin/bash
/usr/bin/aide --check | mail -s "AIDE Integrity Report" security@example.com
EOF
sudo chmod +x /etc/cron.daily/aide
```

**Phase 5: Verification and Return to Service**

Follow [Section 8: Post-Recovery Verification](#post-recovery-verification), with additional security checks:

```bash
# Verify no unauthorized users
mysql -h localhost -u root -p -e "SELECT User, Host FROM mysql.user;"

# Check for suspicious cron jobs
crontab -l
sudo cat /etc/crontab

# Review network connections
sudo netstat -tulpn

# Check for suspicious processes
ps aux | grep -E "nc|ncat|wget|curl|python" | grep -v grep

# Verify file permissions
find /opt/laya -type f -perm /o+w
find /var/www -type f -perm /o+w

# Review Docker containers
docker ps -a
docker images
```

---

### 7.5 File Storage Recovery

#### Scenario: Photo/Upload File Loss or Corruption

**Recovery Time:** ~2 hours
**Use Case:** User photos or uploaded files are missing or corrupted

**Step 1: Assess Damage**

```bash
# Check current file counts
find /var/www/laya/uploads/photos -type f | wc -l
find /var/www/laya/uploads/files -type f | wc -l

# Check disk usage
du -sh /var/www/laya/uploads/photos
du -sh /var/www/laya/uploads/files

# Compare with backup
ssh backup_user@backup.example.com "du -sh /backups/laya/photos"
ssh backup_user@backup.example.com "du -sh /backups/laya/uploads"
```

**Step 2: Restore from Local Backup**

```bash
# Backup current state (in case of partial corruption)
sudo tar czf /tmp/uploads_backup_$(date +%Y%m%d_%H%M%S).tar.gz \
  /var/www/laya/uploads/photos \
  /var/www/laya/uploads/files

# Restore photos from local backup
sudo rsync -avz --delete \
  /var/backups/photos/ \
  /var/www/laya/uploads/photos/

# Restore uploads from local backup
sudo rsync -avz --delete \
  /var/backups/uploads/ \
  /var/www/laya/uploads/files/

# Set proper permissions
sudo chown -R www-data:www-data /var/www/laya/uploads
sudo chmod -R 755 /var/www/laya/uploads
```

**Step 3: Restore from Remote Backup (if local backup unavailable)**

```bash
# From AWS S3
aws s3 sync s3://my-laya-backups/photos/ /var/www/laya/uploads/photos/
aws s3 sync s3://my-laya-backups/uploads/ /var/www/laya/uploads/files/

# From remote server
rsync -avz -e "ssh -i ~/.ssh/id_rsa" \
  backup_user@backup.example.com:/backups/laya/photos/ \
  /var/www/laya/uploads/photos/

rsync -avz -e "ssh -i ~/.ssh/id_rsa" \
  backup_user@backup.example.com:/backups/laya/uploads/ \
  /var/www/laya/uploads/files/

# Set permissions
sudo chown -R www-data:www-data /var/www/laya/uploads
sudo chmod -R 755 /var/www/laya/uploads
```

**Step 4: Verify File Restoration**

```bash
# Check file counts
find /var/www/laya/uploads/photos -type f | wc -l
find /var/www/laya/uploads/files -type f | wc -l

# Verify disk usage
du -sh /var/www/laya/uploads/photos
du -sh /var/www/laya/uploads/files

# Check file permissions
ls -la /var/www/laya/uploads/photos/ | head -20
ls -la /var/www/laya/uploads/files/ | head -20

# Test file access through application
curl -I http://localhost:8000/uploads/photos/test.jpg
```

**Step 5: Verify Application Access**

```bash
# Test photo upload through API
curl -X POST http://localhost:8000/api/v1/upload \
  -F "file=@/tmp/test_photo.jpg" \
  -H "Authorization: Bearer $API_TOKEN"

# Verify uploaded file exists
ls -la /var/www/laya/uploads/photos/ | tail -5

# Test file download
curl -O http://localhost:8000/uploads/photos/latest.jpg
```

---

## Post-Recovery Verification

### 8.1 Database Verification

**Critical Checks:**

```bash
# 1. Database Connectivity
mysql -h localhost -u root -p -e "SELECT 1" || echo "MySQL FAILED"
psql -h localhost -U postgres -c "SELECT 1" || echo "PostgreSQL FAILED"

# 2. Table Count Verification
mysql -h localhost -u root -p laya_db -e "
  SELECT COUNT(*) AS table_count
  FROM information_schema.tables
  WHERE table_schema = 'laya_db';
"

# 3. Row Count Verification (Critical Tables)
mysql -h localhost -u root -p laya_db -e "
  SELECT 'users' AS table_name, COUNT(*) AS row_count FROM users
  UNION ALL
  SELECT 'children', COUNT(*) FROM children
  UNION ALL
  SELECT 'staff', COUNT(*) FROM staff
  UNION ALL
  SELECT 'attendance', COUNT(*) FROM attendance
  UNION ALL
  SELECT 'invoices', COUNT(*) FROM invoices;
"

# 4. Data Freshness Check
mysql -h localhost -u root -p laya_db -e "
  SELECT
    'users' AS table_name,
    MAX(created_at) AS latest_created,
    MAX(updated_at) AS latest_updated
  FROM users
  UNION ALL
  SELECT 'attendance', MAX(created_at), MAX(updated_at) FROM attendance;
"

# 5. Database Size
mysql -h localhost -u root -p -e "
  SELECT
    table_schema AS 'Database',
    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)',
    COUNT(*) AS 'Tables'
  FROM information_schema.tables
  WHERE table_schema = 'laya_db'
  GROUP BY table_schema;
"

# 6. Index Integrity
mysql -h localhost -u root -p laya_db -e "
  SELECT
    table_name,
    COUNT(*) AS index_count
  FROM information_schema.statistics
  WHERE table_schema = 'laya_db'
  GROUP BY table_name;
"

# 7. Foreign Key Constraints
mysql -h localhost -u root -p laya_db -e "
  SELECT
    table_name,
    constraint_name,
    referenced_table_name
  FROM information_schema.key_column_usage
  WHERE table_schema = 'laya_db'
    AND referenced_table_name IS NOT NULL;
"
```

### 8.2 Application Verification

**Service Health Checks:**

```bash
# 1. Docker Container Status
docker-compose ps
# All services should be "Up"

# 2. Application Logs (No Critical Errors)
docker-compose logs --tail=100 | grep -i error

# 3. Health Endpoints
curl http://localhost:8000/health
# Expected: {"status": "healthy"}

curl http://localhost:8000/api/v1/status
# Expected: {"status": "ok", "database": "connected"}

# 4. Database Connection from Application
docker exec laya_api python << EOF
from app import db
from app.models import User
print(f"User count: {User.query.count()}")
print("Database connection: OK")
EOF

# 5. API Functionality
# Login test
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@example.com","password":"test"}'

# Get users (authenticated)
curl http://localhost:8000/api/v1/users \
  -H "Authorization: Bearer $TOKEN"

# 6. Web Interface
curl -I http://localhost:3000
# Expected: HTTP 200 OK

# Test static assets
curl -I http://localhost:3000/static/css/main.css
```

### 8.3 File Storage Verification

```bash
# 1. Photo Directory Check
ls -la /var/www/laya/uploads/photos/ | head -20
find /var/www/laya/uploads/photos -type f | wc -l

# 2. Upload Directory Check
ls -la /var/www/laya/uploads/files/ | head -20
find /var/www/laya/uploads/files -type f | wc -l

# 3. Disk Space
df -h /var/www/laya/uploads

# 4. Permissions
find /var/www/laya/uploads -type d -not -perm 755 -ls
find /var/www/laya/uploads -type f -not -perm 644 -ls

# 5. File Access Test
# Upload test file
echo "Test content" > /tmp/test_file.txt
curl -X POST http://localhost:8000/api/v1/upload \
  -F "file=@/tmp/test_file.txt" \
  -H "Authorization: Bearer $TOKEN"

# Download test file
curl -O http://localhost:8000/uploads/files/test_file.txt
cat test_file.txt  # Should show "Test content"
```

### 8.4 Docker Volume Verification

```bash
# 1. List Docker Volumes
docker volume ls

# 2. Verify Volume Contents
docker run --rm \
  -v laya_mysql_data:/volume \
  alpine:latest \
  ls -la /volume

# 3. Check Volume Sizes
docker system df -v | grep -A 20 "Volumes:"

# 4. Test Volume Persistence
# Create test data
docker run --rm \
  -v laya_test_volume:/data \
  alpine:latest \
  sh -c "echo 'test' > /data/test.txt"

# Verify test data
docker run --rm \
  -v laya_test_volume:/data \
  alpine:latest \
  cat /data/test.txt
# Expected: "test"
```

### 8.5 Backup System Verification

```bash
# 1. Verify Backup Scripts
ls -la /opt/laya/laya-backbone/scripts/backup/*.sh
# All scripts should be executable

# 2. Test Backup Creation
/opt/laya/laya-backbone/scripts/backup/mysql_backup.sh

# 3. Verify Backup Files
ls -lht /var/backups/mysql/ | head -5

# 4. Check Cron Jobs
crontab -l
# Should show all backup schedules

# 5. Test Backup Verification
/opt/laya/laya-backbone/scripts/backup/verify_backup.sh --latest

# 6. Check Backup Logs
tail -100 /var/log/mysql_backup.log
tail -100 /var/log/postgres_backup.log
tail -100 /var/log/backup_verification.log
```

### 8.6 Security Verification

```bash
# 1. SSL Certificate
curl -vI https://laya.example.com 2>&1 | grep -i "ssl certificate"

# 2. Firewall Rules
sudo ufw status verbose

# 3. Open Ports
sudo netstat -tulpn | grep LISTEN

# 4. Database Users
mysql -h localhost -u root -p -e "SELECT User, Host FROM mysql.user;"

# 5. File Permissions
find /opt/laya -type f -perm /o+w -ls
find /var/www/laya -type f -perm /o+w -ls

# 6. Password Strength
# Verify .env has secure passwords (not default values)
grep -i password /opt/laya/laya-backbone/.env

# 7. Failed Login Attempts
sudo fail2ban-client status sshd
```

### 8.7 Performance Verification

```bash
# 1. Database Query Performance
mysql -h localhost -u root -p laya_db -e "
  SHOW PROCESSLIST;
  SHOW ENGINE INNODB STATUS\G;
"

# 2. Slow Query Log
sudo tail -100 /var/log/mysql/slow-query.log

# 3. Application Response Time
time curl http://localhost:8000/api/v1/users
# Should be < 1 second

# 4. Memory Usage
free -h
docker stats --no-stream

# 5. Disk I/O
sudo iostat -x 1 5

# 6. CPU Usage
top -bn1 | head -20
```

### 8.8 End-to-End User Workflow Test

**Complete User Journey:**

```bash
# 1. User Registration
curl -X POST http://localhost:8000/api/v1/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email":"test@example.com",
    "password":"TestPass123",
    "name":"Test User"
  }'

# 2. User Login
TOKEN=$(curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email":"test@example.com",
    "password":"TestPass123"
  }' | jq -r '.token')

# 3. Create Child Profile
curl -X POST http://localhost:8000/api/v1/children \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "name":"Test Child",
    "date_of_birth":"2020-01-01",
    "parent_id":1
  }'

# 4. Record Attendance
curl -X POST http://localhost:8000/api/v1/attendance \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "child_id":1,
    "check_in":"2026-02-16T08:00:00",
    "check_out":"2026-02-16T17:00:00"
  }'

# 5. Generate Invoice
curl -X POST http://localhost:8000/api/v1/invoices \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "parent_id":1,
    "amount":500.00,
    "due_date":"2026-03-01"
  }'

# 6. Verify Data Persistence
curl http://localhost:8000/api/v1/children \
  -H "Authorization: Bearer $TOKEN"

curl http://localhost:8000/api/v1/attendance \
  -H "Authorization: Bearer $TOKEN"

curl http://localhost:8000/api/v1/invoices \
  -H "Authorization: Bearer $TOKEN"
```

### 8.9 Recovery Acceptance Criteria

**Sign-off Checklist:**

- [ ] ✅ MySQL database restored and accessible
- [ ] ✅ PostgreSQL database restored and accessible (if applicable)
- [ ] ✅ All tables present with correct row counts
- [ ] ✅ Data freshness acceptable (within RPO)
- [ ] ✅ All Docker containers running
- [ ] ✅ Application health endpoints responding
- [ ] ✅ API authentication working
- [ ] ✅ Web interface accessible
- [ ] ✅ Photo/upload files restored
- [ ] ✅ File permissions correct
- [ ] ✅ Docker volumes restored
- [ ] ✅ Backup scripts operational
- [ ] ✅ Cron jobs configured
- [ ] ✅ SSL certificates valid
- [ ] ✅ Firewall rules configured
- [ ] ✅ No critical errors in logs
- [ ] ✅ End-to-end user workflow successful
- [ ] ✅ Performance acceptable
- [ ] ✅ Security hardening complete (if security incident)
- [ ] ✅ Stakeholders notified of completion

**Final Sign-off:**

```
Recovery completed by: _____________________ Date: ____________
Verified by: _____________________ Date: ____________
Approved by: _____________________ Date: ____________

Total Recovery Time: _______ hours
Data Loss (RPO): _______ hours
Issues Encountered: _____________________________________
Lessons Learned: _______________________________________
```

---

## Testing Schedule

### 9.1 Backup Verification Testing

**Frequency:** Daily (Automated)

**Automated Tests:**
- Daily at 4:00 AM: Latest backup verification
- Restore to temporary database
- Integrity checks
- Automatic cleanup

**Monitoring:**
```bash
# Check verification logs daily
tail -50 /var/log/backup_verification.log

# Alert on failures
grep "FAILED" /var/log/backup_verification.log | tail -10
```

---

### 9.2 Monthly Recovery Drills

**Frequency:** Monthly (1st Sunday of each month)

**Drill Procedure:**

```bash
# Month: _____________
# Date: ______________
# Participants: _______________________

# 1. Select random backup from previous month
RANDOM_BACKUP=$(ls /var/backups/mysql/laya_db_*.sql.gz | shuf -n 1)
echo "Testing backup: $RANDOM_BACKUP"

# 2. Restore to test environment
# Create test database
mysql -h localhost -u root -p << EOF
CREATE DATABASE IF NOT EXISTS laya_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EOF

# Restore
gunzip -c "$RANDOM_BACKUP" | mysql -h localhost -u root -p laya_db_test

# 3. Verify restoration
mysql -h localhost -u root -p laya_db_test -e "
  SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema = 'laya_db_test';
  SELECT COUNT(*) AS user_count FROM users;
"

# 4. Performance test
time mysql -h localhost -u root -p laya_db_test -e "SELECT * FROM users LIMIT 1000;"

# 5. Cleanup
mysql -h localhost -u root -p -e "DROP DATABASE laya_db_test;"

# 6. Document results
cat > /var/log/recovery_drill_$(date +%Y%m%d).txt << EOF
Date: $(date)
Backup Tested: $RANDOM_BACKUP
Restoration: SUCCESS/FAILED
Time to Restore: _____ minutes
Issues: _____________________
Sign-off: __________________
EOF
```

**Success Criteria:**
- [ ] Backup file accessible and valid
- [ ] Restore completes without errors
- [ ] Table count matches expectations
- [ ] Data integrity verified
- [ ] Restore time within RTO targets
- [ ] Documented and signed off

---

### 9.3 Quarterly Disaster Recovery Simulation

**Frequency:** Quarterly (March, June, September, December)

**Full DR Simulation:**

**Scenario:** Simulate complete infrastructure failure

**Steps:**

1. **Preparation Week:**
   - Schedule 4-hour maintenance window
   - Notify stakeholders
   - Prepare test environment (separate server/VM)
   - Download latest backups to test environment

2. **Simulation Day:**
   - Perform complete system recovery on test environment
   - Follow Section 7.3: Complete System Recovery
   - Time each phase
   - Document all issues

3. **Verification:**
   - Complete all Post-Recovery Verification checks
   - Perform end-to-end user testing
   - Measure against RTO/RPO objectives

4. **Review:**
   - Team debrief within 48 hours
   - Document lessons learned
   - Update runbook based on findings
   - Implement improvements

**Metrics to Track:**
- Time to restore each component
- Total recovery time
- Data loss (if any)
- Issues encountered
- Automation effectiveness
- Team coordination effectiveness

**Report Template:**

```
Quarterly DR Simulation Report
Quarter: _______ Year: _______
Date: _______________
Participants: _____________________

Simulation Results:
- Total Recovery Time: _____ hours (Target: 4 hours)
- Database Restore Time: _____ minutes (Target: 60 minutes)
- Application Start Time: _____ minutes (Target: 30 minutes)
- Verification Time: _____ minutes (Target: 60 minutes)
- Data Loss: _____ hours (Target: <24 hours)

Issues Encountered:
1. __________________________________
2. __________________________________
3. __________________________________

Improvements Implemented:
1. __________________________________
2. __________________________________
3. __________________________________

Runbook Updates:
- Page/Section: _______ Change: _______
- Page/Section: _______ Change: _______

Next Review Date: _______________
Approved by: _______________
```

---

### 9.4 Annual Full-Scale DR Test

**Frequency:** Annually (May)

**Full Production Failover:**

**⚠️ WARNING:** This test involves actual production failover to DR site/infrastructure.

**Planning Phase (2 months before):**
1. Executive approval
2. Stakeholder notification (all users)
3. Communication plan
4. Detailed runbook review
5. Team training
6. Backup DR coordinator assignment

**Execution Phase:**
1. Failover to DR infrastructure
2. Run production workload on DR
3. Monitor for 24-48 hours
4. Failback to primary
5. Verify complete system

**Success Criteria:**
- [ ] Failover completed within RTO
- [ ] No data loss beyond RPO
- [ ] All services operational on DR
- [ ] Performance acceptable
- [ ] Failback successful
- [ ] No user-reported issues
- [ ] All monitoring operational

---

## Appendices

### Appendix A: Quick Reference Commands

**Database Backup:**
```bash
# MySQL
/path/to/laya-backbone/scripts/backup/mysql_backup.sh

# PostgreSQL
/path/to/laya-backbone/scripts/backup/postgres_backup.sh
```

**Database Restore:**
```bash
# MySQL
/path/to/laya-backbone/scripts/backup/mysql_restore.sh /var/backups/mysql/laya_db_YYYYMMDD_HHMMSS.sql.gz

# PostgreSQL
/path/to/laya-backbone/scripts/backup/postgres_restore.sh /var/backups/postgres/laya_db_YYYYMMDD_HHMMSS.sql.gz
```

**Backup Verification:**
```bash
# Verify latest backup
/path/to/laya-backbone/scripts/backup/verify_backup.sh --latest

# Verify specific backup
/path/to/laya-backbone/scripts/backup/verify_backup.sh /var/backups/mysql/laya_db_YYYYMMDD_HHMMSS.sql.gz
```

**Remote Backup:**
```bash
# Sync to remote storage
/path/to/laya-backbone/scripts/backup/remote_backup.sh

# Dry-run (test without transfer)
/path/to/laya-backbone/scripts/backup/remote_backup.sh --dry-run
```

**Docker Volume Backup:**
```bash
# Backup all volumes
/path/to/laya-backbone/scripts/backup/docker_volume_backup.sh

# Backup specific volume
/path/to/laya-backbone/scripts/backup/docker_volume_backup.sh volume_name
```

---

### Appendix B: Environment Variables Reference

**MySQL Backup/Restore:**
```bash
export MYSQL_HOST=localhost
export MYSQL_PORT=3306
export MYSQL_USER=backup_user
export MYSQL_PASSWORD=secure_password
export MYSQL_DATABASE=laya_db
export BACKUP_DIR=/var/backups/mysql
```

**PostgreSQL Backup/Restore:**
```bash
export PGHOST=localhost
export PGPORT=5432
export PGUSER=backup_user
export PGPASSWORD=secure_password
export PGDATABASE=laya_db
export BACKUP_DIR=/var/backups/postgres
```

**Remote Backup (S3):**
```bash
export REMOTE_BACKUP_METHOD=s3
export AWS_S3_BUCKET=s3://my-laya-backups
export AWS_PROFILE=default
export AWS_REGION=us-east-1
export MYSQL_BACKUP_DIR=/var/backups/mysql
export POSTGRES_BACKUP_DIR=/var/backups/postgres
```

**Remote Backup (Rsync):**
```bash
export REMOTE_BACKUP_METHOD=rsync
export REMOTE_USER=backup_user
export REMOTE_HOST=backup.example.com
export REMOTE_PATH=/backups/laya
export SSH_KEY=$HOME/.ssh/id_rsa
export SSH_PORT=22
export RSYNC_BANDWIDTH=5000
export MYSQL_BACKUP_DIR=/var/backups/mysql
export POSTGRES_BACKUP_DIR=/var/backups/postgres
```

**Photo/Upload Backup:**
```bash
export PHOTOS_SOURCE_DIR=/var/www/laya/uploads/photos
export UPLOADS_SOURCE_DIR=/var/www/laya/uploads/files
export PHOTOS_BACKUP_DIR=/var/backups/photos
export UPLOADS_BACKUP_DIR=/var/backups/uploads
export RSYNC_BANDWIDTH=5000
```

**Docker Volume Backup:**
```bash
export DOCKER_BACKUP_DIR=/var/backups/docker-volumes
export DOCKER_IMAGE=alpine:latest
export MIN_DISK_SPACE_GB=5
```

---

### Appendix C: Troubleshooting Guide

#### Issue: Backup File Corrupted

**Symptoms:**
```bash
gzip -t /var/backups/mysql/laya_db_20260216_020000.sql.gz
# Error: gzip: file is corrupted
```

**Solution:**
1. Use previous backup
2. Check disk health: `sudo smartctl -a /dev/sda`
3. Re-run backup to create new copy
4. Verify backup verification cron is running

---

#### Issue: Restore Takes Too Long

**Symptoms:**
- Restore exceeds RTO
- Database import hangs

**Solution:**
```bash
# Increase MySQL buffer sizes
mysql -h localhost -u root -p << EOF
SET GLOBAL innodb_buffer_pool_size = 2G;
SET GLOBAL max_allowed_packet = 1G;
EOF

# Use direct import (bypass script)
gunzip -c backup.sql.gz | mysql -h localhost -u root -p --max_allowed_packet=1G laya_db
```

---

#### Issue: Insufficient Disk Space

**Symptoms:**
```bash
df -h
# Filesystem shows 100% usage
```

**Solution:**
```bash
# Find large files
du -sh /var/backups/* | sort -hr | head -20

# Remove old backups (older than 90 days)
find /var/backups -name "*.gz" -mtime +90 -delete

# Run retention policy
/path/to/laya-backbone/scripts/backup/retention_policy.sh

# Clean Docker
docker system prune -af --volumes
```

---

#### Issue: Cannot Connect to Remote Backup

**Symptoms:**
- Remote backup sync fails
- SSH timeout errors

**Solution:**
```bash
# Test SSH connection
ssh -i ~/.ssh/id_rsa -v backup_user@backup.example.com

# Check SSH key permissions
chmod 600 ~/.ssh/id_rsa

# Test rsync manually
rsync -avz --dry-run -e "ssh -i ~/.ssh/id_rsa" \
  /var/backups/mysql/ \
  backup_user@backup.example.com:/backups/laya/mysql/

# For S3, verify credentials
aws s3 ls s3://my-laya-backups/
```

---

#### Issue: Database Restore Fails with Foreign Key Error

**Symptoms:**
```
ERROR 1215 (HY000): Cannot add foreign key constraint
```

**Solution:**
```bash
# Disable foreign key checks during restore
mysql -h localhost -u root -p laya_db << EOF
SET FOREIGN_KEY_CHECKS=0;
SOURCE /tmp/backup.sql;
SET FOREIGN_KEY_CHECKS=1;
EOF

# Verify foreign keys after restore
mysql -h localhost -u root -p laya_db -e "
  SELECT TABLE_NAME, CONSTRAINT_NAME
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_TYPE = 'FOREIGN KEY';
"
```

---

### Appendix D: Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2026-02-16 | Infrastructure Team | Initial release |
|  |  |  |  |
|  |  |  |  |

---

### Appendix E: Document Maintenance

**Review Schedule:**
- **Quarterly:** Review and update based on DR testing results
- **After Major Changes:** Update within 1 week of infrastructure changes
- **After Incidents:** Update within 48 hours of any disaster recovery event

**Approval Process:**
1. Infrastructure Team reviews and updates
2. Security Team reviews for security considerations
3. Management approves final version
4. All team members acknowledge receipt

**Distribution:**
- Infrastructure Team (mandatory)
- On-call rotation (mandatory)
- Development Team (recommended)
- Management (executive summary)
- Secure storage location (encrypted copy)

---

**END OF RUNBOOK**

**For emergencies, start with:**
1. [Emergency Contacts](#emergency-contacts)
2. [Pre-Recovery Checklist](#pre-recovery-checklist)
3. [Recovery Procedures](#recovery-procedures) (relevant scenario)

**Remember:** Stay calm, follow the procedures, document everything, and ask for help when needed.

---

**Document Control:**
- Classification: CONFIDENTIAL
- Storage: Secure backup location + printed copy in safe
- Access: Infrastructure team + management only
- Encryption: Required for digital copies
