<?php
/*
Gibbon Configuration File
Generated for LAYA Kindergarten & Childcare Management Platform

This configuration is designed for use with Docker Compose.
Update these values according to your environment settings.

See .env.example for environment variable documentation.
*/

/**
 * Generate a unique GUID for this installation
 * Used for session management and security
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

// Load environment variables with fallbacks for Docker Compose deployment
$databaseServer = getenv('DB_HOST') ?: getenv('MYSQL_HOST') ?: 'mysql';
$databaseName = getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: 'gibbon';
$databaseUsername = getenv('DB_USER') ?: getenv('MYSQL_USER') ?: 'gibbon';
$databasePassword = getenv('DB_PASSWORD') ?: getenv('MYSQL_PASSWORD') ?: '';
$databasePort = (int)(getenv('DB_PORT') ?: getenv('MYSQL_PORT') ?: 3306);

// Session encryption key for enhanced security
$sessionEncryptionKey = getenv('SESSION_ENCRYPTION_KEY') ?: null;

// Return configuration array (preferred Gibbon v25+ format)
return [
    // Database Connection Settings
    'databaseServer'       => $databaseServer,
    'databaseUsername'     => $databaseUsername,
    'databasePassword'     => $databasePassword,
    'databaseName'         => $databaseName,
    'databasePort'         => $databasePort,

    // Unique installation identifier
    // Note: This GUID should be persisted after first generation in production
    // For Docker deployments, consider setting GIBBON_GUID environment variable
    'guid'                 => getenv('GIBBON_GUID') ?: 'laya-' . substr(md5(__FILE__ . $_SERVER['HTTP_HOST'] ?? 'localhost'), 0, 16),

    // Cache duration in minutes (0 = no caching)
    'caching'              => (int)(getenv('GIBBON_CACHING') ?: 10),

    // Session configuration
    // Options: null (default PHP), 'database', 'redis'
    'sessionHandler'       => getenv('SESSION_HANDLER') ?: null,

    // Session encryption key (32+ characters recommended)
    'sessionEncryptionKey' => $sessionEncryptionKey,

    // Require HTTPS for session cookies in production
    'sessionSecure'        => getenv('ENVIRONMENT') === 'production' ? true : null,

    // List of usernames that can impersonate other users (for debugging)
    // Use with caution - empty array in production
    'allowImpersonateUser' => [],
];
