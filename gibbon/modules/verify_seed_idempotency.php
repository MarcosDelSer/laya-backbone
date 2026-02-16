#!/usr/bin/env php
<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * Seed Data Idempotency Verification Script
 *
 * This script verifies that the seed_data.php script is truly idempotent
 * by running it multiple times and checking that:
 * 1. No duplicate records are created
 * 2. Record counts remain stable across multiple runs
 * 3. Data integrity is maintained
 *
 * Usage: php verify_seed_idempotency.php [--verbose]
 *
 * Options:
 *   --verbose     Show detailed output
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// Ensure CLI only
if (PHP_SAPI !== 'cli') {
    die('This script must be run from the command line.');
}

// Parse command line options
$options = getopt('', ['verbose']);
$verbose = isset($options['verbose']);

// Helper function for output
function output($message, $forceOutput = false) {
    global $verbose;
    if ($verbose || $forceOutput) {
        echo '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    }
}

// Find and load Gibbon bootstrap
$gibbonPath = realpath(__DIR__ . '/../..');
if (!$gibbonPath || !file_exists($gibbonPath . '/gibbon.php')) {
    $possiblePaths = [
        __DIR__ . '/../..',
        __DIR__ . '/../../..',
        dirname(__DIR__, 2),
    ];

    foreach ($possiblePaths as $path) {
        if (file_exists($path . '/gibbon.php')) {
            $gibbonPath = realpath($path);
            break;
        }
    }
}

if (!$gibbonPath || !file_exists($gibbonPath . '/gibbon.php')) {
    die("Error: Could not find Gibbon installation.\n");
}

output("Found Gibbon at: $gibbonPath", true);

// Load Gibbon core
$_SERVER['SCRIPT_NAME'] = '/index.php';
chdir($gibbonPath);
require $gibbonPath . '/gibbon.php';

// Get database connection
global $pdo;
if (!isset($pdo)) {
    die("Error: Database connection not available\n");
}

output("Database connection established", true);

/**
 * Count records matching a specific pattern
 */
function countSeedRecords($pdo, $table, $emailPattern = null) {
    if ($emailPattern) {
        $sql = "SELECT COUNT(*) FROM $table
                WHERE gibbonPersonID IN (
                    SELECT gibbonPersonID FROM gibbonPerson
                    WHERE email LIKE :pattern1 OR email LIKE :pattern2
                )";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'pattern1' => '%@example.com',
            'pattern2' => '%@laya.test'
        ]);
    } else {
        $sql = "SELECT COUNT(*) FROM $table";
        $stmt = $pdo->query($sql);
    }
    return (int) $stmt->fetchColumn();
}

/**
 * Get counts of all seed data
 */
function getSeedDataCounts($pdo) {
    return [
        'persons' => (int) $pdo->query("SELECT COUNT(*) FROM gibbonPerson WHERE email LIKE '%@example.com' OR email LIKE '%@laya.test'")->fetchColumn(),
        'families' => (int) $pdo->query("SELECT COUNT(*) FROM gibbonFamily WHERE name LIKE '%Test Family%'")->fetchColumn(),
        'staff' => (int) $pdo->query("SELECT COUNT(*) FROM gibbonStaff WHERE gibbonPersonID IN (SELECT gibbonPersonID FROM gibbonPerson WHERE email LIKE '%@laya.test')")->fetchColumn(),
        'enrollments' => (int) $pdo->query("SELECT COUNT(*) FROM gibbonStudentEnrolment WHERE gibbonPersonID IN (SELECT gibbonPersonID FROM gibbonPerson WHERE email LIKE '%@example.com')")->fetchColumn(),
        'attendance' => countSeedRecords($pdo, 'gibbonCareAttendance', true),
        'meals' => countSeedRecords($pdo, 'gibbonCareMeal', true),
        'naps' => countSeedRecords($pdo, 'gibbonCareNap', true),
        'diapers' => countSeedRecords($pdo, 'gibbonCareDiaper', true),
        'incidents' => countSeedRecords($pdo, 'gibbonCareIncident', true),
        'activities' => countSeedRecords($pdo, 'gibbonCareActivity', true),
    ];
}

/**
 * Check for duplicate records
 */
function checkForDuplicates($pdo) {
    $issues = [];

    // Check for duplicate persons by email
    $sql = "SELECT email, COUNT(*) as count
            FROM gibbonPerson
            WHERE email LIKE '%@example.com' OR email LIKE '%@laya.test'
            GROUP BY email
            HAVING count > 1";
    $result = $pdo->query($sql);
    $duplicates = $result->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($duplicates)) {
        $issues[] = "Duplicate person emails found: " . count($duplicates);
    }

    // Check for duplicate families
    $sql = "SELECT name, COUNT(*) as count
            FROM gibbonFamily
            WHERE name LIKE '%Test Family%'
            GROUP BY name
            HAVING count > 1";
    $result = $pdo->query($sql);
    $duplicates = $result->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($duplicates)) {
        $issues[] = "Duplicate families found: " . count($duplicates);
    }

    // Check for duplicate attendance records (same person, same date)
    $sql = "SELECT gibbonPersonID, date, COUNT(*) as count
            FROM gibbonCareAttendance
            GROUP BY gibbonPersonID, date
            HAVING count > 1";
    $result = $pdo->query($sql);
    $duplicates = $result->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($duplicates)) {
        $issues[] = "Duplicate attendance records found: " . count($duplicates);
    }

    return $issues;
}

try {
    output("\n" . str_repeat('=', 60), true);
    output("SEED DATA IDEMPOTENCY VERIFICATION", true);
    output(str_repeat('=', 60), true);

    // Get initial counts
    output("\nCounting existing seed data...", true);
    $initialCounts = getSeedDataCounts($pdo);
    output("Initial record counts:", true);
    foreach ($initialCounts as $type => $count) {
        output("  - $type: $count", true);
    }

    // Run seed script first time
    output("\n" . str_repeat('-', 60), true);
    output("Running seed script (1st time)...", true);
    output(str_repeat('-', 60), true);
    $seedScript = __DIR__ . '/seed_data.php';
    if (!file_exists($seedScript)) {
        throw new Exception("Seed script not found at: $seedScript");
    }

    $output1 = [];
    $returnCode1 = 0;
    exec("php \"$seedScript\" 2>&1", $output1, $returnCode1);

    if ($returnCode1 !== 0) {
        throw new Exception("Seed script failed on first run with code: $returnCode1");
    }

    if ($verbose) {
        foreach ($output1 as $line) {
            output($line);
        }
    }

    // Get counts after first run
    $countsAfterFirst = getSeedDataCounts($pdo);
    output("\nRecord counts after 1st run:", true);
    foreach ($countsAfterFirst as $type => $count) {
        $change = $count - $initialCounts[$type];
        $changeStr = $change > 0 ? " (+$change)" : "";
        output("  - $type: $count$changeStr", true);
    }

    // Check for duplicates after first run
    output("\nChecking for duplicates after 1st run...", true);
    $issues1 = checkForDuplicates($pdo);
    if (!empty($issues1)) {
        output("⚠️  WARNING: Issues found after 1st run:", true);
        foreach ($issues1 as $issue) {
            output("  - $issue", true);
        }
    } else {
        output("✓ No duplicates found", true);
    }

    // Run seed script second time
    output("\n" . str_repeat('-', 60), true);
    output("Running seed script (2nd time)...", true);
    output(str_repeat('-', 60), true);

    $output2 = [];
    $returnCode2 = 0;
    exec("php \"$seedScript\" 2>&1", $output2, $returnCode2);

    if ($returnCode2 !== 0) {
        throw new Exception("Seed script failed on second run with code: $returnCode2");
    }

    if ($verbose) {
        foreach ($output2 as $line) {
            output($line);
        }
    }

    // Get counts after second run
    $countsAfterSecond = getSeedDataCounts($pdo);
    output("\nRecord counts after 2nd run:", true);
    foreach ($countsAfterSecond as $type => $count) {
        $change = $count - $countsAfterFirst[$type];
        $changeStr = $change > 0 ? " (+$change)" : ($change < 0 ? " ($change)" : "");
        output("  - $type: $count$changeStr", true);
    }

    // Check for duplicates after second run
    output("\nChecking for duplicates after 2nd run...", true);
    $issues2 = checkForDuplicates($pdo);
    if (!empty($issues2)) {
        output("⚠️  WARNING: Issues found after 2nd run:", true);
        foreach ($issues2 as $issue) {
            output("  - $issue", true);
        }
    } else {
        output("✓ No duplicates found", true);
    }

    // Run seed script third time to be thorough
    output("\n" . str_repeat('-', 60), true);
    output("Running seed script (3rd time)...", true);
    output(str_repeat('-', 60), true);

    $output3 = [];
    $returnCode3 = 0;
    exec("php \"$seedScript\" 2>&1", $output3, $returnCode3);

    if ($returnCode3 !== 0) {
        throw new Exception("Seed script failed on third run with code: $returnCode3");
    }

    if ($verbose) {
        foreach ($output3 as $line) {
            output($line);
        }
    }

    // Get counts after third run
    $countsAfterThird = getSeedDataCounts($pdo);
    output("\nRecord counts after 3rd run:", true);
    foreach ($countsAfterThird as $type => $count) {
        $change = $count - $countsAfterSecond[$type];
        $changeStr = $change > 0 ? " (+$change)" : ($change < 0 ? " ($change)" : "");
        output("  - $type: $count$changeStr", true);
    }

    // Check for duplicates after third run
    output("\nChecking for duplicates after 3rd run...", true);
    $issues3 = checkForDuplicates($pdo);
    if (!empty($issues3)) {
        output("⚠️  WARNING: Issues found after 3rd run:", true);
        foreach ($issues3 as $issue) {
            output("  - $issue", true);
        }
    } else {
        output("✓ No duplicates found", true);
    }

    // Final verification
    output("\n" . str_repeat('=', 60), true);
    output("IDEMPOTENCY VERIFICATION RESULTS", true);
    output(str_repeat('=', 60), true);

    $allTestsPassed = true;
    $totalIssues = array_merge($issues1, $issues2, $issues3);

    // Check that counts remained stable between 2nd and 3rd run
    output("\nVerifying idempotency (counts between runs 2 and 3):", true);
    foreach ($countsAfterSecond as $type => $count) {
        $count3 = $countsAfterThird[$type];
        if ($count === $count3) {
            output("  ✓ $type: Stable ($count records)", true);
        } else {
            output("  ✗ $type: CHANGED from $count to $count3", true);
            $allTestsPassed = false;
        }
    }

    // Report on duplicates
    if (!empty($totalIssues)) {
        output("\n⚠️  DUPLICATE DETECTION:", true);
        output("  Total unique issues found: " . count(array_unique($totalIssues)), true);
        $allTestsPassed = false;
    } else {
        output("\n✓ No duplicates detected across all runs", true);
    }

    // Final result
    output("\n" . str_repeat('=', 60), true);
    if ($allTestsPassed) {
        output("✅ PASSED: Seed script is idempotent!", true);
        output("   - No duplicate records created", true);
        output("   - Record counts stable across multiple runs", true);
        output("   - Safe to run seed script multiple times", true);
    } else {
        output("❌ FAILED: Seed script is NOT idempotent!", true);
        output("   - Issues detected (see details above)", true);
        output("   - Do NOT run seed script multiple times until fixed", true);
    }
    output(str_repeat('=', 60), true);

    exit($allTestsPassed ? 0 : 1);

} catch (Exception $e) {
    output("\n❌ ERROR: " . $e->getMessage(), true);
    output("Trace: " . $e->getTraceAsString(), $verbose);
    exit(1);
}
