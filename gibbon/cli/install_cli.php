#!/usr/bin/env php
<?php
/*
Gibbon CLI Installer
LAYA Kindergarten & Childcare Management Platform

This script provides automated database installation for Docker-based deployments.
It reads configuration from environment variables and sets up the Gibbon database.

Usage:
  php install_cli.php [options]

Options:
  --admin-username=USERNAME   Admin username (default: admin)
  --admin-password=PASSWORD   Admin password (required, or use GIBBON_ADMIN_PASSWORD env)
  --admin-email=EMAIL        Admin email (required, or use GIBBON_ADMIN_EMAIL env)
  --admin-firstname=NAME     Admin first name (default: System)
  --admin-surname=NAME       Admin surname (default: Administrator)
  --org-name=NAME            Organization name (default: LAYA Childcare)
  --timezone=TZ              Timezone (default: America/Toronto)
  --locale=LOCALE            Default locale (default: en_GB)
  --demo-data                Install demo data
  --skip-if-exists           Skip installation if database already populated
  --help                     Show this help message

Environment Variables:
  DB_HOST             MySQL server hostname
  DB_PORT             MySQL server port (default: 3306)
  DB_NAME             Database name (default: gibbon)
  DB_USER             Database username
  DB_PASSWORD         Database password
  GIBBON_ADMIN_PASSWORD   Admin user password
  GIBBON_ADMIN_EMAIL      Admin user email
  GIBBON_GUID             Installation GUID (auto-generated if not set)

Example:
  php install_cli.php --admin-password=secure123 --admin-email=admin@laya.ca

Copyright (c) 2026 LAYA
Licensed under the GNU General Public License v3.0
*/

// Ensure running from CLI
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

// Parse command line options
$options = getopt('h', [
    'admin-username:',
    'admin-password:',
    'admin-email:',
    'admin-firstname:',
    'admin-surname:',
    'org-name:',
    'timezone:',
    'locale:',
    'demo-data',
    'skip-if-exists',
    'help',
]);

// Show help
if (isset($options['h']) || isset($options['help'])) {
    echo file_get_contents(__FILE__);
    preg_match('/\/\*\n(.+?)\*\//s', file_get_contents(__FILE__), $matches);
    echo "\n";
    exit(0);
}

// Configuration from environment and CLI options
$config = [
    'db' => [
        'host' => getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: 'mysql',
        'port' => (int)(getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: 3306),
        'name' => getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'gibbon',
        'user' => getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'gibbon',
        'pass' => getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '',
    ],
    'admin' => [
        'username' => $options['admin-username'] ?? 'admin',
        'password' => $options['admin-password'] ?? getenv('GIBBON_ADMIN_PASSWORD'),
        'email' => $options['admin-email'] ?? getenv('GIBBON_ADMIN_EMAIL'),
        'firstname' => $options['admin-firstname'] ?? 'System',
        'surname' => $options['admin-surname'] ?? 'Administrator',
    ],
    'org' => [
        'name' => $options['org-name'] ?? getenv('GIBBON_ORG_NAME') ?: 'LAYA Childcare',
    ],
    'timezone' => $options['timezone'] ?? getenv('GIBBON_TIMEZONE') ?: 'America/Toronto',
    'locale' => $options['locale'] ?? getenv('GIBBON_LOCALE') ?: 'en_GB',
    'guid' => getenv('GIBBON_GUID') ?: generateGuid(),
    'demoData' => isset($options['demo-data']),
    'skipIfExists' => isset($options['skip-if-exists']),
];

// Validate required fields
$errors = [];
if (empty($config['admin']['password'])) {
    $errors[] = "Admin password is required. Use --admin-password or GIBBON_ADMIN_PASSWORD environment variable.";
}
if (empty($config['admin']['email'])) {
    $errors[] = "Admin email is required. Use --admin-email or GIBBON_ADMIN_EMAIL environment variable.";
}
if (empty($config['db']['pass'])) {
    $errors[] = "Database password is required. Use DB_PASSWORD environment variable.";
}

if (!empty($errors)) {
    fwrite(STDERR, "ERROR: Configuration errors:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  - $error\n");
    }
    exit(1);
}

// Get Gibbon base path
$gibbonPath = dirname(__DIR__);

// Validate SQL files exist
$sqlFile = $gibbonPath . '/gibbon.sql';
$demoSqlFile = $gibbonPath . '/gibbon_demo.sql';

if (!file_exists($sqlFile)) {
    fwrite(STDERR, "ERROR: gibbon.sql not found at: $sqlFile\n");
    exit(1);
}

echo "=== Gibbon CLI Installer ===\n";
echo "Database: {$config['db']['host']}:{$config['db']['port']}/{$config['db']['name']}\n";
echo "Admin user: {$config['admin']['username']} <{$config['admin']['email']}>\n";
echo "Organization: {$config['org']['name']}\n";
echo "Timezone: {$config['timezone']}\n";
echo "Locale: {$config['locale']}\n";
echo "GUID: {$config['guid']}\n";
echo "\n";

// Connect to MySQL server (without database first)
try {
    echo "Connecting to MySQL server...\n";
    $dsn = "mysql:host={$config['db']['host']};port={$config['db']['port']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    echo "  Connected successfully.\n";
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: Could not connect to MySQL server: " . $e->getMessage() . "\n");
    exit(1);
}

// Check if database exists, create if not
try {
    echo "Checking database...\n";
    $stmt = $pdo->prepare("SHOW DATABASES LIKE :name");
    $stmt->execute([':name' => $config['db']['name']]);

    if ($stmt->rowCount() === 0) {
        echo "  Creating database: {$config['db']['name']}...\n";
        $dbName = $pdo->quote($config['db']['name']);
        $dbName = substr($dbName, 1, -1); // Remove quotes for CREATE DATABASE
        $pdo->exec("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        echo "  Database created.\n";
    } else {
        echo "  Database exists.\n";
    }
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: Could not create database: " . $e->getMessage() . "\n");
    exit(1);
}

// Select the database
$pdo->exec("USE `{$config['db']['name']}`");

// Check if already installed
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'gibbonPerson'");
    $tableExists = $stmt->rowCount() > 0;

    if ($tableExists) {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM gibbonPerson");
        $row = $stmt->fetch();
        $hasUsers = $row['count'] > 0;

        if ($hasUsers && $config['skipIfExists']) {
            echo "Database already populated. Skipping installation (--skip-if-exists).\n";
            exit(0);
        } elseif ($hasUsers) {
            fwrite(STDERR, "ERROR: Database already contains users. Use --skip-if-exists to skip.\n");
            exit(1);
        }
    }
} catch (PDOException $e) {
    // Table doesn't exist, continue with installation
}

// Import SQL schema
try {
    echo "Importing database schema from gibbon.sql...\n";
    $sql = file_get_contents($sqlFile);

    // Remove SQL remarks/comments
    $lines = explode("\n", $sql);
    $sql = '';
    foreach ($lines as $line) {
        if (!preg_match('/^(--|#)/', trim($line))) {
            $sql .= $line . "\n";
        }
    }

    // Split and execute statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    $total = count($statements);
    $current = 0;

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
            $current++;
            if ($current % 100 === 0) {
                echo "  Executed $current / $total statements...\n";
            }
        }
    }
    echo "  Schema imported successfully ($total statements).\n";
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: Could not import schema: " . $e->getMessage() . "\n");
    exit(1);
}

// Set system settings
try {
    echo "Configuring system settings...\n";

    $settings = [
        ['System', 'absoluteURL', 'http://localhost/gibbon'],
        ['System', 'absolutePath', $gibbonPath],
        ['System', 'systemName', 'LAYA Childcare'],
        ['System', 'organisationName', $config['org']['name']],
        ['System', 'organisationNameShort', 'LAYA'],
        ['System', 'timezone', $config['timezone']],
        ['System', 'currency', 'CAD'],
        ['System', 'country', 'Canada'],
    ];

    $updateStmt = $pdo->prepare("UPDATE gibbonSetting SET value = :value WHERE scope = :scope AND name = :name");

    foreach ($settings as [$scope, $name, $value]) {
        $updateStmt->execute([':scope' => $scope, ':name' => $name, ':value' => $value]);
        echo "  Set $scope.$name = $value\n";
    }

    // Set default locale
    $pdo->exec("UPDATE gibboni18n SET systemDefault = 'N' WHERE systemDefault = 'Y'");
    $stmt = $pdo->prepare("UPDATE gibboni18n SET systemDefault = 'Y' WHERE code = :code");
    $stmt->execute([':code' => $config['locale']]);
    echo "  Set default locale: {$config['locale']}\n";

} catch (PDOException $e) {
    fwrite(STDERR, "WARNING: Could not set some settings: " . $e->getMessage() . "\n");
}

// Create admin user
try {
    echo "Creating administrator account...\n";

    // Generate password hash
    $salt = bin2hex(random_bytes(16));
    $passwordHash = hash('sha256', $salt . $config['admin']['password']);

    $stmt = $pdo->prepare("INSERT INTO gibbonPerson SET
        gibbonPersonID = 1,
        title = 'Mr.',
        surname = :surname,
        firstName = :firstName,
        preferredName = :preferredName,
        officialName = :officialName,
        username = :username,
        passwordStrong = :passwordStrong,
        passwordStrongSalt = :passwordStrongSalt,
        status = 'Full',
        canLogin = 'Y',
        passwordForceReset = 'N',
        gibbonRoleIDPrimary = '001',
        gibbonRoleIDAll = '001',
        email = :email
    ");

    $stmt->execute([
        ':surname' => $config['admin']['surname'],
        ':firstName' => $config['admin']['firstname'],
        ':preferredName' => $config['admin']['firstname'],
        ':officialName' => $config['admin']['firstname'] . ' ' . $config['admin']['surname'],
        ':username' => $config['admin']['username'],
        ':passwordStrong' => $passwordHash,
        ':passwordStrongSalt' => $salt,
        ':email' => $config['admin']['email'],
    ]);

    // Set as staff member
    $pdo->exec("INSERT INTO gibbonStaff SET gibbonPersonID = 1, type = 'Teaching'");

    echo "  Administrator account created: {$config['admin']['username']}\n";

} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: Could not create admin user: " . $e->getMessage() . "\n");
    exit(1);
}

// Install demo data if requested
if ($config['demoData'] && file_exists($demoSqlFile)) {
    try {
        echo "Installing demo data...\n";
        $sql = file_get_contents($demoSqlFile);

        // Remove SQL remarks/comments
        $lines = explode("\n", $sql);
        $sql = '';
        foreach ($lines as $line) {
            if (!preg_match('/^(--|#)/', trim($line))) {
                $sql .= $line . "\n";
            }
        }

        // Split and execute statements
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if (!empty($statement)) {
                try {
                    $pdo->exec($statement);
                } catch (PDOException $e) {
                    // Skip errors in demo data (e.g., duplicate keys)
                }
            }
        }
        echo "  Demo data installed.\n";
    } catch (Exception $e) {
        echo "  WARNING: Could not install demo data: " . $e->getMessage() . "\n";
    }
}

// Create initial school year
try {
    echo "Setting up school year...\n";

    // Check if school year exists
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM gibbonSchoolYear WHERE status = 'Current'");
    $row = $stmt->fetch();

    if ($row['count'] == 0) {
        // Create a school year
        $currentYear = date('Y');
        $nextYear = $currentYear + 1;
        $yearName = "$currentYear-$nextYear";

        $stmt = $pdo->prepare("INSERT INTO gibbonSchoolYear SET
            name = :name,
            status = 'Current',
            firstDay = :firstDay,
            lastDay = :lastDay,
            sequenceNumber = 1
        ");

        $stmt->execute([
            ':name' => $yearName,
            ':firstDay' => "$currentYear-09-01",
            ':lastDay' => "$nextYear-06-30",
        ]);

        echo "  Created school year: $yearName\n";
    } else {
        echo "  School year already exists.\n";
    }
} catch (PDOException $e) {
    echo "  WARNING: Could not create school year: " . $e->getMessage() . "\n";
}

echo "\n";
echo "=== Installation Complete ===\n";
echo "\n";
echo "You can now access Gibbon at: http://localhost/gibbon/\n";
echo "Login with:\n";
echo "  Username: {$config['admin']['username']}\n";
echo "  Password: (the password you provided)\n";
echo "\n";

exit(0);

/**
 * Generate a unique GUID for this installation
 */
function generateGuid(): string {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
