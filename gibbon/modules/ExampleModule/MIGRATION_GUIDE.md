# ExampleModule - Database Migration Guide

## Overview

This guide explains how to run database migrations for the ExampleModule and verify the schema installation.

## Prerequisites

- Docker and docker-compose installed
- MySQL service running (via docker-compose)
- Gibbon web interface accessible at http://localhost:8080

## Migration Process

### Method 1: Through Gibbon Web Interface (Recommended)

This is the standard Gibbon approach for installing modules and running migrations:

1. **Start Services**
   ```bash
   # Start MySQL and Gibbon services
   docker-compose up mysql gibbon
   ```

2. **Access Gibbon**
   - Open browser to: http://localhost:8080
   - Login as Admin

3. **Install Module**
   - Navigate to: **System Admin > Manage Modules**
   - Find "Example Module" in the list
   - Click **Install** button
   - Gibbon will automatically run the CHANGEDB.php migrations

4. **Verify Installation**
   - Module should appear as "Installed"
   - Check for any error messages during installation
   - Module actions should appear in the permission matrix

### Method 2: Using Verification Script

After installing through the web interface, you can verify the schema:

```bash
cd gibbon/modules/ExampleModule
./verify-schema.sh
```

The script will check:
- ✓ MySQL connection
- ✓ Table existence (gibbonExampleEntity)
- ✓ Table structure
- ✓ Correct collation (utf8mb4_unicode_ci)
- ✓ Module settings in gibbonSetting

### Method 3: Manual Database Verification

If you have direct database access:

```bash
# Using docker exec
docker exec -it laya-mysql mysql -u gibbon_user -p

# Then in MySQL prompt:
USE gibbon;

-- Check tables
SHOW TABLES LIKE 'gibbonExample%';

-- Verify structure
DESCRIBE gibbonExampleEntity;

-- Check collation
SHOW TABLE STATUS LIKE 'gibbonExampleEntity';

-- Verify settings
SELECT * FROM gibbonSetting WHERE scope='Example Module';
```

## Expected Database Schema

After successful migration, you should have:

### Table: gibbonExampleEntity

| Column | Type | Constraints |
|--------|------|-------------|
| gibbonExampleEntityID | INT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT |
| gibbonPersonID | INT UNSIGNED | NOT NULL, INDEXED |
| gibbonSchoolYearID | INT UNSIGNED | NOT NULL, INDEXED |
| title | VARCHAR(100) | NOT NULL |
| description | TEXT | NULL |
| status | ENUM | 'Active', 'Inactive', 'Pending' |
| createdByID | INT UNSIGNED | NOT NULL |
| timestampCreated | TIMESTAMP | DEFAULT CURRENT_TIMESTAMP |
| timestampModified | TIMESTAMP | ON UPDATE CURRENT_TIMESTAMP |

**Collation**: utf8mb4_unicode_ci (InnoDB engine)

### Settings in gibbonSetting

| Scope | Name | Display Name | Value |
|-------|------|--------------|-------|
| Example Module | enableFeature | Enable Feature | Y |
| Example Module | maxItems | Maximum Items | 50 |

## Troubleshooting

### Migration Fails

**Problem**: CHANGEDB.php migration fails during installation

**Solutions**:
1. Check MySQL service is running: `docker-compose ps`
2. Verify database credentials in Gibbon's config.php
3. Check MySQL logs: `docker-compose logs mysql`
4. Ensure database collation supports utf8mb4
5. Backup database before retrying

### Table Already Exists

**Problem**: Error "Table 'gibbonExampleEntity' already exists"

**Solution**:
- CHANGEDB.php uses `CREATE TABLE IF NOT EXISTS` so this shouldn't happen
- If it does, manually drop the table and retry:
  ```sql
  DROP TABLE IF EXISTS gibbonExampleEntity;
  ```
- Then reinstall module through Gibbon interface

### Wrong Collation

**Problem**: Table has utf8mb3_general_ci instead of utf8mb4_unicode_ci

**Solution**:
- Check MySQL default collation settings
- Verify CHANGEDB.php specifies: `CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
- If needed, alter table:
  ```sql
  ALTER TABLE gibbonExampleEntity CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  ```

### Docker Connection Issues

**Problem**: Cannot connect to MySQL via docker-compose

**Solutions**:
1. Check services are running: `docker-compose ps`
2. Check network: `docker network ls | grep laya`
3. Restart services: `docker-compose restart mysql gibbon`
4. Check port conflicts: `lsof -i :3306`

## Database Backup

**CRITICAL**: Always backup before running migrations!

```bash
# Backup before migration
docker exec laya-mysql mysqldump -u gibbon_user -pchangeme gibbon > backup-$(date +%Y%m%d-%H%M%S).sql

# Restore if needed
docker exec -i laya-mysql mysql -u gibbon_user -pchangeme gibbon < backup-YYYYMMDD-HHMMSS.sql
```

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.00 | Initial | Created gibbonExampleEntity table, added module settings |
| 1.0.01 | Planned | Placeholder for future migrations |

## Migration Best Practices

1. **One-Way Only**: Never edit existing migration lines in CHANGEDB.php
2. **Always Backup**: Backup database before running migrations
3. **Test First**: Test migrations in development before production
4. **Version Increment**: Increment version numbers sequentially
5. **Statement Termination**: Always end SQL statements with `;end`
6. **Collation Standard**: Use utf8mb4_unicode_ci for all tables
7. **Idempotency**: Use `IF NOT EXISTS` and `ON DUPLICATE KEY UPDATE`

## References

- [Gibbon Module Development](https://docs.gibbonedu.org/developers/getting-started/module-development/)
- [Gibbon CHANGEDB Pattern](https://github.com/GibbonEdu/core/wiki/Database-Migrations)
- [MySQL UTF8MB4 Documentation](https://dev.mysql.com/doc/refman/8.0/en/charset-unicode-utf8mb4.html)
