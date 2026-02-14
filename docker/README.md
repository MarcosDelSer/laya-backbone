# LAYA Docker Configuration

This directory contains Docker configuration files for the LAYA Kindergarten & Childcare Management Platform.

## Quick Start

```bash
# From the project root directory
cp .env.example .env

# Edit .env and update passwords
# (especially GIBBON_ADMIN_PASSWORD and database passwords)

# Start the services
docker-compose up -d

# View logs to monitor installation
docker-compose logs -f php-fpm
```

## Services

| Service | Container | Port | Description |
|---------|-----------|------|-------------|
| MySQL | laya-mysql | 3306 | Gibbon CMS database |
| PostgreSQL | laya-postgres | 5432 | AI Service database |
| Redis | laya-redis | 6379 | Session storage & caching |
| PHP-FPM | laya-php | 9000 | PHP processor for Gibbon |
| Nginx | laya-nginx | 8080 | Web server for Gibbon CMS |

## Access URLs

- **Gibbon CMS**: http://localhost:8080/gibbon/
- **AI Service**: http://localhost:8000/ (requires `--profile full`)
- **Parent Portal**: http://localhost:3000/ (requires `--profile full`)

## Automatic Installation

When the PHP-FPM container starts for the first time, it automatically:

1. Waits for MySQL to be ready
2. Creates the `gibbon` database if it doesn't exist
3. Imports the Gibbon schema from `gibbon.sql`
4. Creates an administrator account using environment variables
5. Configures system settings (organization name, timezone, etc.)

### Default Credentials

After installation, log in with:
- **Username**: `admin` (or value of `GIBBON_ADMIN_USERNAME`)
- **Password**: Value of `GIBBON_ADMIN_PASSWORD`

## Configuration

All configuration is done through environment variables in `.env`:

### Database

| Variable | Default | Description |
|----------|---------|-------------|
| `MYSQL_DATABASE` | gibbon | Gibbon database name |
| `MYSQL_USER` | gibbon | Database username |
| `MYSQL_PASSWORD` | - | Database password |
| `MYSQL_ROOT_PASSWORD` | - | MySQL root password |

### Gibbon Administrator

| Variable | Default | Description |
|----------|---------|-------------|
| `GIBBON_ADMIN_USERNAME` | admin | Admin username |
| `GIBBON_ADMIN_PASSWORD` | - | Admin password (required) |
| `GIBBON_ADMIN_EMAIL` | admin@laya.local | Admin email |
| `GIBBON_ADMIN_FIRSTNAME` | System | Admin first name |
| `GIBBON_ADMIN_SURNAME` | Administrator | Admin surname |

### Gibbon Settings

| Variable | Default | Description |
|----------|---------|-------------|
| `GIBBON_ORG_NAME` | LAYA Childcare | Organization name |
| `GIBBON_TIMEZONE` | America/Toronto | Default timezone |
| `GIBBON_LOCALE` | en_GB | Default locale code |
| `GIBBON_GUID` | (auto-generated) | Installation GUID |

## Manual Installation

If you need to run the installer manually:

```bash
# Enter the PHP container
docker-compose exec php-fpm bash

# Run the CLI installer
php /var/www/html/gibbon/cli/install_cli.php \
    --admin-password=your_password \
    --admin-email=admin@example.com
```

## Verification

### Check Services Are Running

```bash
docker-compose ps
```

All services should show `healthy` status.

### Check Gibbon Installation

1. Open http://localhost:8080/gibbon/
2. You should see the Gibbon login page
3. No PHP errors should be displayed
4. Log in with admin credentials

### Check Database

```bash
# Connect to MySQL
docker-compose exec mysql mysql -u gibbon -p gibbon

# Check tables exist
SHOW TABLES;

# Check admin user exists
SELECT username, email FROM gibbonPerson WHERE gibbonPersonID = 1;
```

## Troubleshooting

### PHP-FPM Won't Start

Check the logs:
```bash
docker-compose logs php-fpm
```

Common issues:
- MySQL not ready: Wait for MySQL health check to pass
- Missing environment variables: Check `.env` file
- File permissions: The container runs as `www-data`

### Gibbon Shows Error

Check Nginx and PHP-FPM logs:
```bash
docker-compose logs nginx php-fpm
```

### Reset Installation

To completely reset:
```bash
# Stop all containers
docker-compose down

# Remove volumes (WARNING: Deletes all data!)
docker-compose down -v

# Rebuild and start
docker-compose up -d --build
```

## Directory Structure

```
docker/
├── mysql/
│   └── init.sql          # MySQL initialization script
├── nginx/
│   └── gibbon.conf       # Nginx configuration for Gibbon
├── php/
│   ├── Dockerfile        # PHP-FPM image with extensions
│   ├── php.ini           # Custom PHP configuration
│   └── docker-entrypoint.sh  # Auto-installation script
├── postgres/
│   └── init.sql          # PostgreSQL initialization script
└── README.md             # This file
```

## Full Stack Deployment

To start all services including AI Service and Parent Portal:

```bash
docker-compose --profile full up -d
```

Note: This requires additional Dockerfiles in `ai-service/` and `parent-portal/` directories.
