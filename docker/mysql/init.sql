-- LAYA Kindergarten & Childcare Management Platform
-- MySQL 8.0 Initialization Script for Gibbon CMS
--
-- This script runs on first container startup to configure the database.
-- It is mounted at /docker-entrypoint-initdb.d/init.sql
--
-- Requirements:
-- - MySQL 8.0+ for Gibbon CMS v30.0.01
-- - UTF8MB4 character set for full Unicode support
-- - Proper collation for multi-language (EN/FR) Quebec compliance

-- Ensure the gibbon database is properly configured
-- Note: Database is created via MYSQL_DATABASE env var, but we set charset/collation here
ALTER DATABASE IF EXISTS gibbon
    CHARACTER SET = utf8mb4
    COLLATE = utf8mb4_unicode_ci;

-- Grant full privileges to gibbon user on gibbon database
-- Note: User is created via MYSQL_USER/MYSQL_PASSWORD env vars
GRANT ALL PRIVILEGES ON gibbon.* TO 'gibbon'@'%';

-- Grant SELECT on mysql.time_zone_name for timezone support
-- Required for proper datetime handling in Gibbon
GRANT SELECT ON mysql.time_zone_name TO 'gibbon'@'%';

-- Flush privileges to apply changes
FLUSH PRIVILEGES;

-- Set session variables for optimal Gibbon CMS compatibility
-- These settings help with legacy code compatibility in Gibbon
SET GLOBAL sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- Enable event scheduler for scheduled tasks (attendance reminders, etc.)
SET GLOBAL event_scheduler = ON;

-- Log successful initialization
SELECT 'LAYA MySQL initialization complete for Gibbon CMS' AS status;
