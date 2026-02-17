<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

RL-24 Submission Module - Installation Verification Script
Run this script after installing the module to verify all components are correctly set up.

Usage:
  - Via browser: /modules/RL24Submission/tests/verify_installation.php
  - Via CLI: php modules/RL24Submission/tests/verify_installation.php
*/

// Determine if running from CLI or browser
$isCLI = (php_sapi_name() === 'cli');

// Output formatting helpers
function outputHeader($text, $isCLI) {
    if ($isCLI) {
        echo "\n=== " . $text . " ===\n";
    } else {
        echo "<h2>" . htmlspecialchars($text) . "</h2>\n";
    }
}

function outputSuccess($text, $isCLI) {
    if ($isCLI) {
        echo "✓ " . $text . "\n";
    } else {
        echo "<div style='color: green;'>✓ " . htmlspecialchars($text) . "</div>\n";
    }
}

function outputError($text, $isCLI) {
    if ($isCLI) {
        echo "✗ " . $text . "\n";
    } else {
        echo "<div style='color: red;'>✗ " . htmlspecialchars($text) . "</div>\n";
    }
}

function outputInfo($text, $isCLI) {
    if ($isCLI) {
        echo "  " . $text . "\n";
    } else {
        echo "<div style='color: #666; margin-left: 20px;'>" . htmlspecialchars($text) . "</div>\n";
    }
}

// Start output
if (!$isCLI) {
    echo "<!DOCTYPE html><html><head><title>RL-24 Module Installation Verification</title>";
    echo "<style>body{font-family:sans-serif;max-width:800px;margin:40px auto;padding:20px;}</style>";
    echo "</head><body>";
    echo "<h1>RL-24 Submission Module - Installation Verification</h1>";
}

$errors = 0;
$warnings = 0;

// ============================================
// 1. Check Database Tables
// ============================================
outputHeader("Database Tables", $isCLI);

$requiredTables = [
    'gibbonRL24Transmission' => [
        'description' => 'Batch transmission tracking',
        'required_columns' => ['gibbonRL24TransmissionID', 'gibbonSchoolYearID', 'taxYear', 'sequenceNumber', 'fileName', 'status', 'totalSlips', 'xmlFilePath'],
    ],
    'gibbonRL24Slip' => [
        'description' => 'Individual RL-24 slips',
        'required_columns' => ['gibbonRL24SlipID', 'gibbonRL24TransmissionID', 'gibbonPersonIDChild', 'slipNumber', 'taxYear', 'case11Amount', 'case12Amount', 'case13Amount', 'case14Amount'],
    ],
    'gibbonRL24Eligibility' => [
        'description' => 'FO-0601 eligibility forms',
        'required_columns' => ['gibbonRL24EligibilityID', 'gibbonSchoolYearID', 'gibbonPersonIDChild', 'formYear', 'parentFirstName', 'parentLastName', 'approvalStatus'],
    ],
    'gibbonRL24EligibilityDocument' => [
        'description' => 'Supporting documents',
        'required_columns' => ['gibbonRL24EligibilityDocumentID', 'gibbonRL24EligibilityID', 'documentType', 'filePath', 'verificationStatus'],
    ],
];

// Try to connect to database if in Gibbon context
$pdo = null;
try {
    // Check if we're in Gibbon context
    if (file_exists('../../../../config.php')) {
        include '../../../../config.php';
        if (isset($databaseServer) && isset($databaseName) && isset($databaseUsername) && isset($databasePassword)) {
            $pdo = new PDO(
                "mysql:host={$databaseServer};dbname={$databaseName};charset=utf8mb4",
                $databaseUsername,
                $databasePassword,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        }
    }
} catch (Exception $e) {
    outputInfo("Could not connect to database: " . $e->getMessage(), $isCLI);
}

if ($pdo) {
    foreach ($requiredTables as $tableName => $tableInfo) {
        try {
            // Check if table exists
            $stmt = $pdo->query("SHOW TABLES LIKE '{$tableName}'");
            if ($stmt->rowCount() > 0) {
                outputSuccess("{$tableName} - {$tableInfo['description']}", $isCLI);

                // Check required columns
                $stmt = $pdo->query("DESCRIBE {$tableName}");
                $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $missingColumns = array_diff($tableInfo['required_columns'], $columns);
                if (!empty($missingColumns)) {
                    outputError("  Missing columns: " . implode(', ', $missingColumns), $isCLI);
                    $errors++;
                }
            } else {
                outputError("{$tableName} - TABLE NOT FOUND", $isCLI);
                $errors++;
            }
        } catch (Exception $e) {
            outputError("{$tableName} - Error: " . $e->getMessage(), $isCLI);
            $errors++;
        }
    }
} else {
    outputInfo("Database connection not available - run this script from within Gibbon or configure manually.", $isCLI);
    outputInfo("Tables to verify:", $isCLI);
    foreach ($requiredTables as $tableName => $tableInfo) {
        outputInfo("  - {$tableName}: {$tableInfo['description']}", $isCLI);
    }
}

// ============================================
// 2. Check Module Settings
// ============================================
outputHeader("Module Settings", $isCLI);

$requiredSettings = [
    'preparerNumber' => 'Revenu Quebec preparer identification number',
    'providerName' => 'Childcare provider official name',
    'providerNEQ' => 'Quebec Enterprise Number (NEQ)',
    'providerAddress' => 'Official street address',
    'providerCity' => 'City where provider is located',
    'providerPostalCode' => 'Postal code in Canadian format',
    'xmlOutputPath' => 'Directory path for XML files',
    'autoCalculateDays' => 'Auto-calculate attendance days',
    'requireSINValidation' => 'Validate SIN format',
    'documentRetentionYears' => 'Document retention period',
];

if ($pdo) {
    foreach ($requiredSettings as $settingName => $description) {
        try {
            $stmt = $pdo->prepare("SELECT value FROM gibbonSetting WHERE scope = 'RL-24 Submission' AND name = ?");
            $stmt->execute([$settingName]);
            if ($stmt->rowCount() > 0) {
                $value = $stmt->fetchColumn();
                $displayValue = strlen($value) > 30 ? substr($value, 0, 30) . '...' : ($value ?: '(empty)');
                outputSuccess("{$settingName}: {$displayValue}", $isCLI);
            } else {
                outputError("{$settingName} - SETTING NOT FOUND", $isCLI);
                $errors++;
            }
        } catch (Exception $e) {
            outputError("{$settingName} - Error: " . $e->getMessage(), $isCLI);
            $errors++;
        }
    }
} else {
    outputInfo("Settings to verify in gibbonSetting table (scope='RL-24 Submission'):", $isCLI);
    foreach ($requiredSettings as $settingName => $description) {
        outputInfo("  - {$settingName}: {$description}", $isCLI);
    }
}

// ============================================
// 3. Check Module Actions (Menu Items)
// ============================================
outputHeader("Module Actions (Menu Items)", $isCLI);

$requiredActions = [
    'RL-24 Transmissions' => [
        'entryURL' => 'rl24_transmissions.php',
        'category' => 'Tax Forms',
    ],
    'FO-0601 Eligibility Forms' => [
        'entryURL' => 'rl24_eligibility.php',
        'category' => 'Tax Forms',
    ],
    'RL-24 Slips' => [
        'entryURL' => 'rl24_slips.php',
        'category' => 'Tax Forms',
    ],
    'RL-24 Settings' => [
        'entryURL' => 'rl24_settings.php',
        'category' => 'Tax Forms',
    ],
];

if ($pdo) {
    try {
        // Get module ID
        $stmt = $pdo->query("SELECT gibbonModuleID FROM gibbonModule WHERE name = 'RL-24 Submission'");
        $moduleRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($moduleRow) {
            outputSuccess("Module 'RL-24 Submission' is installed (ID: {$moduleRow['gibbonModuleID']})", $isCLI);

            // Check actions
            foreach ($requiredActions as $actionName => $actionInfo) {
                $stmt = $pdo->prepare("SELECT * FROM gibbonAction WHERE gibbonModuleID = ? AND name = ?");
                $stmt->execute([$moduleRow['gibbonModuleID'], $actionName]);

                if ($stmt->rowCount() > 0) {
                    $action = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($action['entryURL'] === $actionInfo['entryURL'] && $action['category'] === $actionInfo['category']) {
                        outputSuccess("{$actionName} → {$actionInfo['entryURL']}", $isCLI);
                    } else {
                        outputError("{$actionName} - Incorrect configuration", $isCLI);
                        $errors++;
                    }
                } else {
                    outputError("{$actionName} - ACTION NOT FOUND", $isCLI);
                    $errors++;
                }
            }
        } else {
            outputError("Module 'RL-24 Submission' is NOT installed", $isCLI);
            $errors++;
        }
    } catch (Exception $e) {
        outputError("Error checking actions: " . $e->getMessage(), $isCLI);
        $errors++;
    }
} else {
    outputInfo("Actions to verify in gibbonAction table:", $isCLI);
    foreach ($requiredActions as $actionName => $actionInfo) {
        outputInfo("  - {$actionName} → {$actionInfo['entryURL']} (Category: {$actionInfo['category']})", $isCLI);
    }
}

// ============================================
// 4. Check Module Files
// ============================================
outputHeader("Module Files", $isCLI);

$requiredFiles = [
    // Core files
    'manifest.php' => 'Module manifest',
    'CHANGEDB.php' => 'Database change log',

    // Domain gateways
    'Domain/RL24TransmissionGateway.php' => 'Transmission gateway',
    'Domain/RL24SlipGateway.php' => 'Slip gateway',
    'Domain/RL24EligibilityGateway.php' => 'Eligibility gateway',

    // XML classes
    'Xml/RL24XmlSchema.php' => 'XML schema constants',
    'Xml/RL24XmlGenerator.php' => 'XML generator',
    'Xml/RL24XmlValidator.php' => 'XML validator',
    'Xml/RL24SlipBuilder.php' => 'Slip builder',

    // Services
    'Services/RL24BatchProcessor.php' => 'Batch processor',
    'Services/RL24SummaryCalculator.php' => 'Summary calculator',
    'Services/RL24TransmissionFileNamer.php' => 'File namer',
    'Services/RL24PaperSummaryGenerator.php' => 'Paper summary generator',

    // UI pages - Eligibility
    'rl24_eligibility.php' => 'Eligibility list page',
    'rl24_eligibility_add.php' => 'Add eligibility form',
    'rl24_eligibility_addProcess.php' => 'Add eligibility processor',
    'rl24_eligibility_edit.php' => 'Edit eligibility form',
    'rl24_eligibility_editProcess.php' => 'Edit eligibility processor',
    'rl24_eligibility_documents.php' => 'Document management',

    // UI pages - Transmissions
    'rl24_transmissions.php' => 'Transmissions dashboard',
    'rl24_transmissions_generate.php' => 'Generate transmission page',
    'rl24_transmissions_generateProcess.php' => 'Generate transmission processor',
    'rl24_transmissions_view.php' => 'View transmission details',
    'rl24_transmissions_download.php' => 'Download XML/summaries',

    // UI pages - Other
    'rl24_slips.php' => 'Slips listing page',
    'rl24_settings.php' => 'Module settings page',
];

$moduleDir = __DIR__ . '/..';
foreach ($requiredFiles as $filePath => $description) {
    $fullPath = $moduleDir . '/' . $filePath;
    if (file_exists($fullPath)) {
        outputSuccess("{$filePath} - {$description}", $isCLI);
    } else {
        outputError("{$filePath} - FILE NOT FOUND", $isCLI);
        $errors++;
    }
}

// ============================================
// 5. Check Directory Permissions
// ============================================
outputHeader("Directory Permissions", $isCLI);

$uploadDir = __DIR__ . '/../../../../uploads/rl24';
if (file_exists($uploadDir)) {
    if (is_writable($uploadDir)) {
        outputSuccess("uploads/rl24 directory exists and is writable", $isCLI);
    } else {
        outputError("uploads/rl24 directory exists but is NOT writable", $isCLI);
        $errors++;
    }
} else {
    outputInfo("uploads/rl24 directory does not exist (will be created on first use)", $isCLI);
}

// ============================================
// Summary
// ============================================
outputHeader("Summary", $isCLI);

if ($errors === 0) {
    outputSuccess("All verification checks passed!", $isCLI);
} else {
    outputError("Found {$errors} error(s) that need attention.", $isCLI);
}

if (!$isCLI) {
    echo "</body></html>";
}

// Exit with appropriate code for CI/CD
exit($errors > 0 ? 1 : 0);
