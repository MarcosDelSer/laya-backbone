<?php
/*
 * PHPUnit Bootstrap for AISync Module Tests
 *
 * This file is loaded before running tests and sets up the testing environment.
 */

// Set error reporting for tests
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define testing environment
define('AISYNC_TEST_MODE', true);

// Load Composer autoloader
$autoloadPaths = [
    __DIR__ . '/../../../../vendor/autoload.php',  // Project root
    __DIR__ . '/../../../vendor/autoload.php',      // gibbon/modules level
    __DIR__ . '/../../vendor/autoload.php',         // gibbon level
];

$autoloaderLoaded = false;
foreach ($autoloadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $autoloaderLoaded = true;
        break;
    }
}

if (!$autoloaderLoaded) {
    echo "Warning: Composer autoloader not found. Run 'composer install' before running tests.\n";
}

// Load test helpers
require_once __DIR__ . '/TestCase.php';

// Set up test environment variables
if (!getenv('JWT_SECRET_KEY')) {
    putenv('JWT_SECRET_KEY=test-secret-key-for-phpunit-testing-only');
}
if (!getenv('JWT_ALGORITHM')) {
    putenv('JWT_ALGORITHM=HS256');
}
if (!getenv('AI_SERVICE_URL')) {
    putenv('AI_SERVICE_URL=http://localhost:8000');
}

echo "AISync Test Bootstrap Loaded\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "PHPUnit Bootstrap Complete\n\n";
