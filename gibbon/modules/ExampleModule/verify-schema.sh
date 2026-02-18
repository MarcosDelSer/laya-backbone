#!/bin/bash
# Verification script for ExampleModule database schema
# This script should be run after migrations are applied through Gibbon's web interface

echo "=== ExampleModule Database Schema Verification ==="
echo ""

# Check if we can connect to MySQL
echo "Checking MySQL connection..."
if command -v mysql &> /dev/null; then
    echo "✓ mysql command found"
else
    echo "✗ mysql command not found. Install mysql-client or use docker exec"
    echo ""
    echo "Alternative: Run inside Docker container:"
    echo "  docker exec -it laya-mysql mysql -u gibbon_user -p"
    exit 1
fi

# Database credentials
DB_HOST="${MYSQL_HOST:-localhost}"
DB_PORT="${MYSQL_PORT:-3306}"
DB_NAME="${MYSQL_DATABASE:-gibbon}"
DB_USER="${MYSQL_USER:-gibbon_user}"
DB_PASS="${MYSQL_PASSWORD:-changeme}"

echo ""
echo "=== Checking for ExampleModule tables ==="
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLES LIKE 'gibbonExample%';" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✓ Successfully queried database"
else
    echo "✗ Failed to connect to database"
    echo "Please check credentials in environment variables or .env file"
    exit 1
fi

echo ""
echo "=== Checking table structure ==="
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "DESCRIBE gibbonExampleEntity;" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✓ gibbonExampleEntity table exists"
else
    echo "✗ gibbonExampleEntity table not found"
    echo "Please run migrations through Gibbon's module installer"
    exit 1
fi

echo ""
echo "=== Checking table collation ==="
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SHOW TABLE STATUS LIKE 'gibbonExampleEntity';" 2>/dev/null | grep -i "utf8mb4_unicode_ci"

if [ $? -eq 0 ]; then
    echo "✓ Correct collation (utf8mb4_unicode_ci)"
else
    echo "⚠ Warning: Collation may not be utf8mb4_unicode_ci"
fi

echo ""
echo "=== Checking module settings ==="
mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "SELECT scope, name, value FROM gibbonSetting WHERE scope='Example Module';" 2>/dev/null

if [ $? -eq 0 ]; then
    echo "✓ Module settings found"
else
    echo "✗ Module settings not found"
fi

echo ""
echo "=== Verification Summary ==="
echo "If all checks passed, the database schema is correctly installed."
echo "If checks failed, please:"
echo "  1. Start MySQL service: docker-compose up mysql"
echo "  2. Access Gibbon: http://localhost:8080"
echo "  3. Go to: System Admin > Manage Modules"
echo "  4. Install ExampleModule"
echo "  5. Run this verification script again"
