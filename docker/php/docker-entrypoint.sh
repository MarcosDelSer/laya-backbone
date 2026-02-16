#!/bin/bash
set -e

# LAYA Gibbon Docker Entrypoint
# This script handles Gibbon database initialization on container startup

echo "=== LAYA Gibbon Entrypoint ==="
echo "Waiting for MySQL to be ready..."

# Wait for MySQL to be available
max_retries=30
retry_count=0

while [ $retry_count -lt $max_retries ]; do
    if php -r "
        \$host = getenv('DB_HOST') ?: 'mysql';
        \$port = getenv('DB_PORT') ?: '3306';
        \$user = getenv('DB_USER') ?: 'gibbon';
        \$pass = getenv('DB_PASSWORD') ?: '';

        try {
            \$pdo = new PDO(\"mysql:host=\$host;port=\$port\", \$user, \$pass);
            echo 'MySQL is ready!';
            exit(0);
        } catch (PDOException \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        echo ""
        break
    fi

    retry_count=$((retry_count + 1))
    echo "Waiting for MySQL... ($retry_count/$max_retries)"
    sleep 2
done

if [ $retry_count -eq $max_retries ]; then
    echo "ERROR: MySQL did not become available in time"
    exit 1
fi

# Check if Gibbon installation is needed
GIBBON_PATH="/var/www/html/gibbon"

if [ -f "$GIBBON_PATH/cli/install_cli.php" ]; then
    # Check if database is already initialized
    if ! php -r "
        \$host = getenv('DB_HOST') ?: 'mysql';
        \$port = getenv('DB_PORT') ?: '3306';
        \$user = getenv('DB_USER') ?: 'gibbon';
        \$pass = getenv('DB_PASSWORD') ?: '';
        \$db = getenv('DB_NAME') ?: 'gibbon';

        try {
            \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass);
            \$stmt = \$pdo->query('SELECT COUNT(*) FROM gibbonPerson');
            \$row = \$stmt->fetch();
            if (\$row[0] > 0) {
                echo 'Database already initialized';
                exit(0);
            }
        } catch (PDOException \$e) {
            // Database or table doesn't exist
        }
        exit(1);
    " 2>/dev/null; then
        echo "Running Gibbon database installer..."

        # Set default admin credentials if not provided
        export GIBBON_ADMIN_PASSWORD="${GIBBON_ADMIN_PASSWORD:-laya_admin_2026}"
        export GIBBON_ADMIN_EMAIL="${GIBBON_ADMIN_EMAIL:-admin@laya.local}"

        cd "$GIBBON_PATH"
        php cli/install_cli.php \
            --admin-username="${GIBBON_ADMIN_USERNAME:-admin}" \
            --admin-password="${GIBBON_ADMIN_PASSWORD}" \
            --admin-email="${GIBBON_ADMIN_EMAIL}" \
            --admin-firstname="${GIBBON_ADMIN_FIRSTNAME:-System}" \
            --admin-surname="${GIBBON_ADMIN_SURNAME:-Administrator}" \
            --org-name="${GIBBON_ORG_NAME:-LAYA Childcare}" \
            --timezone="${GIBBON_TIMEZONE:-America/Toronto}" \
            --locale="${GIBBON_LOCALE:-en_GB}" \
            --skip-if-exists

        echo "Gibbon database installation complete!"
    else
        echo "Gibbon database already initialized, skipping installation."
    fi
else
    echo "WARNING: install_cli.php not found at $GIBBON_PATH/cli/install_cli.php"
fi

# Set proper permissions for uploads directory
if [ -d "$GIBBON_PATH/uploads" ]; then
    chown -R www-data:www-data "$GIBBON_PATH/uploads"
    chmod -R 755 "$GIBBON_PATH/uploads"
fi

# Execute the original PHP-FPM command
exec "$@"
