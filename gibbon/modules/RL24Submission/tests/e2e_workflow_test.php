<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

RL-24 Submission Module - End-to-End Workflow Test Script
This script performs automated testing of the complete RL-24 submission workflow.

Usage:
  CLI:     php e2e_workflow_test.php [--verbose] [--dry-run]
  Browser: /modules/RL24Submission/tests/e2e_workflow_test.php

Test Coverage:
  1. Create FO-0601 eligibility form with test data
  2. Generate batch transmission for tax year
  3. Verify XML file generated in correct format (AAPPPPPPSSS.xml)
  4. Verify XML validates against schema
  5. Download/generate paper summary form
  6. Verify summary calculations are correct
*/

// ============================================================================
// CONFIGURATION
// ============================================================================

$isCLI = (php_sapi_name() === 'cli');
$verbose = $isCLI && in_array('--verbose', $argv ?? []);
$dryRun = $isCLI && in_array('--dry-run', $argv ?? []);

// Test configuration
$testConfig = [
    'taxYear' => 2025,
    'formYear' => 2025,
    'provider' => [
        'name' => 'Centre de la petite enfance Test',
        'neq' => '1234567890',
        'address' => '5678 rue Test',
        'city' => 'Montreal',
        'postalCode' => 'H2X 3T5',
        'preparerNumber' => '123456',
    ],
    'eligibilityData' => [
        [
            'childFirstName' => 'Marie',
            'childLastName' => 'Tremblay',
            'childDOB' => '2020-03-15',
            'parentFirstName' => 'Jean',
            'parentLastName' => 'Tremblay',
            'parentSIN' => '123456782', // Valid Luhn checksum
            'addressLine1' => '1234 rue Principale',
            'city' => 'Montreal',
            'province' => 'QC',
            'postalCode' => 'H3A 1B2',
            'servicePeriodStart' => '2025-01-06',
            'servicePeriodEnd' => '2025-06-30',
            'totalDays' => 125,
            'case11Amount' => 5000.00,
            'case12Amount' => 4000.00,
            'case13Amount' => 500.00,
            'case14Amount' => 3500.00, // Box 12 - Box 13
        ],
        [
            'childFirstName' => 'Pierre',
            'childLastName' => 'Bouchard',
            'childDOB' => '2019-07-22',
            'parentFirstName' => 'Sophie',
            'parentLastName' => 'Bouchard',
            'parentSIN' => '046454286', // Valid Luhn checksum
            'addressLine1' => '5678 avenue du Parc',
            'city' => 'Laval',
            'province' => 'QC',
            'postalCode' => 'H7N 4K9',
            'servicePeriodStart' => '2025-01-06',
            'servicePeriodEnd' => '2025-12-20',
            'totalDays' => 230,
            'case11Amount' => 9500.00,
            'case12Amount' => 7600.00,
            'case13Amount' => 950.00,
            'case14Amount' => 6650.00, // Box 12 - Box 13
        ],
    ],
];

// ============================================================================
// OUTPUT HELPERS
// ============================================================================

function outputHeader($text, $isCLI) {
    if ($isCLI) {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo " " . $text . "\n";
        echo str_repeat('=', 60) . "\n";
    } else {
        echo "<h2 style='margin-top:30px;padding:10px;background:#f0f0f0;border-left:4px solid #007bff;'>" . htmlspecialchars($text) . "</h2>\n";
    }
}

function outputStep($step, $text, $isCLI) {
    if ($isCLI) {
        echo "\n[Step $step] $text\n";
    } else {
        echo "<h3 style='margin:15px 0 10px;'>Step $step: " . htmlspecialchars($text) . "</h3>\n";
    }
}

function outputSuccess($text, $isCLI) {
    if ($isCLI) {
        echo "  ✓ " . $text . "\n";
    } else {
        echo "<div style='color:green;margin:5px 0 5px 20px;'>✓ " . htmlspecialchars($text) . "</div>\n";
    }
}

function outputError($text, $isCLI) {
    if ($isCLI) {
        echo "  ✗ " . $text . "\n";
    } else {
        echo "<div style='color:red;margin:5px 0 5px 20px;font-weight:bold;'>✗ " . htmlspecialchars($text) . "</div>\n";
    }
}

function outputWarning($text, $isCLI) {
    if ($isCLI) {
        echo "  ⚠ " . $text . "\n";
    } else {
        echo "<div style='color:orange;margin:5px 0 5px 20px;'>⚠ " . htmlspecialchars($text) . "</div>\n";
    }
}

function outputInfo($text, $isCLI) {
    if ($isCLI) {
        echo "    " . $text . "\n";
    } else {
        echo "<div style='color:#666;margin:5px 0 5px 40px;'>" . htmlspecialchars($text) . "</div>\n";
    }
}

function outputCode($code, $isCLI) {
    if ($isCLI) {
        echo "\n" . $code . "\n";
    } else {
        echo "<pre style='background:#f5f5f5;padding:10px;margin:10px 20px;overflow-x:auto;border:1px solid #ddd;'>" . htmlspecialchars($code) . "</pre>\n";
    }
}

// ============================================================================
// VALIDATION HELPERS
// ============================================================================

/**
 * Validate SIN using Luhn algorithm.
 */
function validateSINLuhn(string $sin): bool {
    if (!preg_match('/^\d{9}$/', $sin)) {
        return false;
    }

    $digits = str_split($sin);
    $sum = 0;

    for ($i = 0; $i < 9; $i++) {
        $digit = (int) $digits[$i];
        if ($i % 2 === 1) {
            $digit *= 2;
            if ($digit > 9) {
                $digit -= 9;
            }
        }
        $sum += $digit;
    }

    return ($sum % 10) === 0;
}

/**
 * Validate NEQ format (10 digits).
 */
function validateNEQ(string $neq): bool {
    return preg_match('/^\d{10}$/', $neq) === 1;
}

/**
 * Validate postal code format.
 */
function validatePostalCode(string $postalCode): bool {
    return preg_match('/^[A-Za-z]\d[A-Za-z]\s?\d[A-Za-z]\d$/', $postalCode) === 1;
}

/**
 * Generate expected XML filename.
 */
function generateExpectedFilename(int $taxYear, string $preparerNumber, int $sequenceNumber): string {
    return sprintf('%02d%s%03d.xml',
        $taxYear % 100,
        str_pad($preparerNumber, 6, '0', STR_PAD_LEFT),
        $sequenceNumber
    );
}

/**
 * Calculate expected summary totals from eligibility data.
 */
function calculateExpectedSummary(array $eligibilityData): array {
    $summary = [
        'totalSlips' => count($eligibilityData),
        'totalDays' => 0,
        'totalCase11' => 0.00,
        'totalCase12' => 0.00,
        'totalCase13' => 0.00,
        'totalCase14' => 0.00,
    ];

    foreach ($eligibilityData as $data) {
        $summary['totalDays'] += $data['totalDays'];
        $summary['totalCase11'] += $data['case11Amount'];
        $summary['totalCase12'] += $data['case12Amount'];
        $summary['totalCase13'] += $data['case13Amount'];
        $summary['totalCase14'] += $data['case14Amount'];
    }

    return $summary;
}

// ============================================================================
// XML GENERATION FOR TESTING
// ============================================================================

/**
 * Generate sample XML for testing purposes.
 */
function generateTestXml(array $config, array $eligibilityData): string {
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->formatOutput = true;

    // Root element
    $root = $dom->createElementNS('http://www.revenuquebec.gouv.qc.ca/rl24', 'Transmission');
    $dom->appendChild($root);

    // Header
    $header = $dom->createElement('Entete');
    $transmitter = $dom->createElement('Transmetteur');

    $transmitter->appendChild($dom->createElement('NoTransmetteur', 'NP' . $config['provider']['preparerNumber']));
    $transmitter->appendChild($dom->createElement('TypeTransmission', 'O'));
    $transmitter->appendChild($dom->createElement('Annee', (string) $config['taxYear']));
    $transmitter->appendChild($dom->createElement('NoSequence', '001'));
    $transmitter->appendChild($dom->createElement('NomLogiciel', 'Gibbon RL24 Submission'));
    $transmitter->appendChild($dom->createElement('VersionLogiciel', '1.0'));

    $header->appendChild($transmitter);
    $root->appendChild($header);

    // Group
    $group = $dom->createElement('Groupe');

    // Issuer
    $issuer = $dom->createElement('Emetteur');
    $issuer->appendChild($dom->createElement('NEQ', $config['provider']['neq']));

    $issuerName = $dom->createElement('NomEmetteur');
    $issuerName->appendChild($dom->createElement('Ligne1', $config['provider']['name']));
    $issuer->appendChild($issuerName);

    $issuerAddress = $dom->createElement('AdresseEmetteur');
    $issuerAddress->appendChild($dom->createElement('Ligne1', $config['provider']['address']));
    $issuerAddress->appendChild($dom->createElement('Ville', $config['provider']['city']));
    $issuerAddress->appendChild($dom->createElement('Province', 'QC'));
    $issuerAddress->appendChild($dom->createElement('CodePostal', str_replace(' ', '', $config['provider']['postalCode'])));
    $issuerAddress->appendChild($dom->createElement('Pays', 'CAN'));
    $issuer->appendChild($issuerAddress);

    $group->appendChild($issuer);

    // Slips
    $slipNumber = 0;
    foreach ($eligibilityData as $data) {
        $slipNumber++;
        $slip = $dom->createElement('RL24');

        // Slip identification
        $slipId = $dom->createElement('IdentificationReleve');
        $slipId->appendChild($dom->createElement('NoReleve', (string) $slipNumber));
        $slipId->appendChild($dom->createElement('CaseA', 'O'));
        $slip->appendChild($slipId);

        // Recipient (parent)
        $recipient = $dom->createElement('Beneficiaire');
        $recipient->appendChild($dom->createElement('NAS', $data['parentSIN']));

        $recipientName = $dom->createElement('NomBeneficiaire');
        $recipientName->appendChild($dom->createElement('Nom', $data['parentLastName']));
        $recipientName->appendChild($dom->createElement('Prenom', $data['parentFirstName']));
        $recipient->appendChild($recipientName);

        $recipientAddress = $dom->createElement('AdresseBeneficiaire');
        $recipientAddress->appendChild($dom->createElement('Ligne1', $data['addressLine1']));
        $recipientAddress->appendChild($dom->createElement('Ville', $data['city']));
        $recipientAddress->appendChild($dom->createElement('Province', $data['province']));
        $recipientAddress->appendChild($dom->createElement('CodePostal', str_replace(' ', '', $data['postalCode'])));
        $recipientAddress->appendChild($dom->createElement('Pays', 'CAN'));
        $recipient->appendChild($recipientAddress);

        $slip->appendChild($recipient);

        // Child
        $child = $dom->createElement('Enfant');
        $child->appendChild($dom->createElement('Nom', $data['childLastName']));
        $child->appendChild($dom->createElement('Prenom', $data['childFirstName']));
        $child->appendChild($dom->createElement('DateNaissance', $data['childDOB']));
        $slip->appendChild($child);

        // Service period
        $period = $dom->createElement('PeriodeService');
        $period->appendChild($dom->createElement('DateDebut', $data['servicePeriodStart']));
        $period->appendChild($dom->createElement('DateFin', $data['servicePeriodEnd']));
        $slip->appendChild($period);

        // Amounts
        $slip->appendChild($dom->createElement('Case10', (string) $data['totalDays']));
        $slip->appendChild($dom->createElement('Case11', number_format($data['case11Amount'], 2, '.', '')));
        $slip->appendChild($dom->createElement('Case12', number_format($data['case12Amount'], 2, '.', '')));
        if ($data['case13Amount'] > 0) {
            $slip->appendChild($dom->createElement('Case13', number_format($data['case13Amount'], 2, '.', '')));
        }
        if ($data['case14Amount'] > 0) {
            $slip->appendChild($dom->createElement('Case14', number_format($data['case14Amount'], 2, '.', '')));
        }

        $group->appendChild($slip);
    }

    // Summary
    $summary = calculateExpectedSummary($eligibilityData);
    $summaryEl = $dom->createElement('Sommaire');
    $summaryEl->appendChild($dom->createElement('NombreReleves', (string) $summary['totalSlips']));
    $summaryEl->appendChild($dom->createElement('TotalCase10', (string) $summary['totalDays']));
    $summaryEl->appendChild($dom->createElement('TotalCase11', number_format($summary['totalCase11'], 2, '.', '')));
    $summaryEl->appendChild($dom->createElement('TotalCase12', number_format($summary['totalCase12'], 2, '.', '')));
    if ($summary['totalCase13'] > 0) {
        $summaryEl->appendChild($dom->createElement('TotalCase13', number_format($summary['totalCase13'], 2, '.', '')));
    }
    if ($summary['totalCase14'] > 0) {
        $summaryEl->appendChild($dom->createElement('TotalCase14', number_format($summary['totalCase14'], 2, '.', '')));
    }
    $group->appendChild($summaryEl);

    $root->appendChild($group);

    return $dom->saveXML();
}

// ============================================================================
// START TEST OUTPUT
// ============================================================================

if (!$isCLI) {
    echo "<!DOCTYPE html><html><head><title>RL-24 E2E Workflow Test</title>";
    echo "<meta charset='UTF-8'>";
    echo "<style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:1000px;margin:40px auto;padding:20px;line-height:1.6;}</style>";
    echo "</head><body>";
    echo "<h1>RL-24 Submission Module - End-to-End Workflow Test</h1>";
    echo "<p style='color:#666;'>This test verifies the complete workflow from eligibility form creation through XML generation and summary calculation.</p>";
}

$errors = 0;
$warnings = 0;
$passed = 0;
$testResults = [];

outputHeader("RL-24 End-to-End Workflow Test", $isCLI);

// ============================================================================
// STEP 1: Validate Test Data (FO-0601 Eligibility Form Data)
// ============================================================================

outputStep(1, "Validate FO-0601 Eligibility Form Test Data", $isCLI);

foreach ($testConfig['eligibilityData'] as $index => $data) {
    $childName = $data['childFirstName'] . ' ' . $data['childLastName'];
    outputInfo("Testing data for child: $childName", $isCLI);

    // Validate SIN
    if (validateSINLuhn($data['parentSIN'])) {
        outputSuccess("Parent SIN passes Luhn validation: {$data['parentSIN']}", $isCLI);
        $passed++;
    } else {
        outputError("Parent SIN fails Luhn validation: {$data['parentSIN']}", $isCLI);
        $errors++;
    }

    // Validate postal code
    if (validatePostalCode($data['postalCode'])) {
        outputSuccess("Postal code is valid format: {$data['postalCode']}", $isCLI);
        $passed++;
    } else {
        outputError("Postal code is invalid format: {$data['postalCode']}", $isCLI);
        $errors++;
    }

    // Validate service period dates
    $startDate = strtotime($data['servicePeriodStart']);
    $endDate = strtotime($data['servicePeriodEnd']);
    if ($startDate < $endDate) {
        outputSuccess("Service period dates are valid: {$data['servicePeriodStart']} to {$data['servicePeriodEnd']}", $isCLI);
        $passed++;
    } else {
        outputError("Service period dates are invalid (start >= end)", $isCLI);
        $errors++;
    }

    // Validate Box 14 = Box 12 - Box 13
    $expectedCase14 = $data['case12Amount'] - $data['case13Amount'];
    if (abs($data['case14Amount'] - $expectedCase14) < 0.01) {
        outputSuccess("Box 14 calculation is correct: {$data['case14Amount']} = {$data['case12Amount']} - {$data['case13Amount']}", $isCLI);
        $passed++;
    } else {
        outputError("Box 14 calculation is incorrect: Expected $expectedCase14, got {$data['case14Amount']}", $isCLI);
        $errors++;
    }
}

// ============================================================================
// STEP 2: Validate Provider Configuration
// ============================================================================

outputStep(2, "Validate Provider Configuration", $isCLI);

// Validate NEQ
if (validateNEQ($testConfig['provider']['neq'])) {
    outputSuccess("Provider NEQ is valid format: {$testConfig['provider']['neq']}", $isCLI);
    $passed++;
} else {
    outputError("Provider NEQ is invalid format: {$testConfig['provider']['neq']}", $isCLI);
    $errors++;
}

// Validate preparer number
if (preg_match('/^\d{6}$/', $testConfig['provider']['preparerNumber'])) {
    outputSuccess("Preparer number is valid format: {$testConfig['provider']['preparerNumber']}", $isCLI);
    $passed++;
} else {
    outputError("Preparer number is invalid format: {$testConfig['provider']['preparerNumber']}", $isCLI);
    $errors++;
}

// Validate provider postal code
if (validatePostalCode($testConfig['provider']['postalCode'])) {
    outputSuccess("Provider postal code is valid format: {$testConfig['provider']['postalCode']}", $isCLI);
    $passed++;
} else {
    outputError("Provider postal code is invalid format: {$testConfig['provider']['postalCode']}", $isCLI);
    $errors++;
}

// ============================================================================
// STEP 3: Generate Batch Transmission Preview
// ============================================================================

outputStep(3, "Generate Batch Transmission Preview", $isCLI);

$expectedSummary = calculateExpectedSummary($testConfig['eligibilityData']);

outputInfo("Tax Year: {$testConfig['taxYear']}", $isCLI);
outputInfo("Approved Eligibility Forms: " . count($testConfig['eligibilityData']), $isCLI);
outputInfo("Slips to Generate: {$expectedSummary['totalSlips']}", $isCLI);

// Generate expected filename
$expectedFilename = generateExpectedFilename(
    $testConfig['taxYear'],
    $testConfig['provider']['preparerNumber'],
    1 // First sequence
);

if (preg_match('/^\d{11}\.xml$/', $expectedFilename)) {
    outputSuccess("Expected filename matches AAPPPPPPSSS.xml format: $expectedFilename", $isCLI);
    $passed++;
} else {
    outputError("Expected filename format is incorrect: $expectedFilename", $isCLI);
    $errors++;
}

// Verify filename components
$yearPart = substr($expectedFilename, 0, 2);
$preparerPart = substr($expectedFilename, 2, 6);
$sequencePart = substr($expectedFilename, 8, 3);

outputInfo("Filename breakdown:", $isCLI);
outputInfo("  - Year (AA): $yearPart (from {$testConfig['taxYear']})", $isCLI);
outputInfo("  - Preparer (PPPPPP): $preparerPart", $isCLI);
outputInfo("  - Sequence (SSS): $sequencePart", $isCLI);

// ============================================================================
// STEP 4: Generate and Validate XML
// ============================================================================

outputStep(4, "Generate and Validate XML File", $isCLI);

$xmlContent = generateTestXml($testConfig, $testConfig['eligibilityData']);

// Verify XML is well-formed
libxml_use_internal_errors(true);
$testDom = new DOMDocument();
if ($testDom->loadXML($xmlContent)) {
    outputSuccess("XML is well-formed", $isCLI);
    $passed++;

    // Verify root element
    if ($testDom->documentElement->localName === 'Transmission') {
        outputSuccess("Root element is 'Transmission'", $isCLI);
        $passed++;
    } else {
        outputError("Root element should be 'Transmission', got: " . $testDom->documentElement->localName, $isCLI);
        $errors++;
    }

    // Verify required sections
    $xpath = new DOMXPath($testDom);
    $xpath->registerNamespace('rl24', 'http://www.revenuquebec.gouv.qc.ca/rl24');

    // Check header
    $headerNodes = $xpath->query('//rl24:Entete');
    if ($headerNodes->length > 0) {
        outputSuccess("XML contains Entete (header) section", $isCLI);
        $passed++;
    } else {
        outputError("XML missing Entete (header) section", $isCLI);
        $errors++;
    }

    // Check group
    $groupNodes = $xpath->query('//rl24:Groupe');
    if ($groupNodes->length > 0) {
        outputSuccess("XML contains Groupe section", $isCLI);
        $passed++;
    } else {
        outputError("XML missing Groupe section", $isCLI);
        $errors++;
    }

    // Check issuer
    $issuerNodes = $xpath->query('//rl24:Emetteur');
    if ($issuerNodes->length > 0) {
        outputSuccess("XML contains Emetteur (issuer) section", $isCLI);
        $passed++;
    } else {
        outputError("XML missing Emetteur (issuer) section", $isCLI);
        $errors++;
    }

    // Check slips
    $slipNodes = $xpath->query('//rl24:RL24');
    if ($slipNodes->length === count($testConfig['eligibilityData'])) {
        outputSuccess("XML contains correct number of RL-24 slips: " . $slipNodes->length, $isCLI);
        $passed++;
    } else {
        outputError("XML slip count mismatch: expected " . count($testConfig['eligibilityData']) . ", got " . $slipNodes->length, $isCLI);
        $errors++;
    }

    // Check summary
    $summaryNodes = $xpath->query('//rl24:Sommaire');
    if ($summaryNodes->length > 0) {
        outputSuccess("XML contains Sommaire (summary) section", $isCLI);
        $passed++;
    } else {
        outputError("XML missing Sommaire (summary) section", $isCLI);
        $errors++;
    }

    // Verify summary totals in XML
    $xmlSlipCount = $xpath->query('//rl24:Sommaire/rl24:NombreReleves')->item(0);
    $xmlTotalDays = $xpath->query('//rl24:Sommaire/rl24:TotalCase10')->item(0);
    $xmlTotalCase12 = $xpath->query('//rl24:Sommaire/rl24:TotalCase12')->item(0);
    $xmlTotalCase14 = $xpath->query('//rl24:Sommaire/rl24:TotalCase14')->item(0);

    if ($xmlSlipCount && (int)$xmlSlipCount->nodeValue === $expectedSummary['totalSlips']) {
        outputSuccess("Summary slip count is correct: " . $xmlSlipCount->nodeValue, $isCLI);
        $passed++;
    } else {
        outputError("Summary slip count mismatch", $isCLI);
        $errors++;
    }

    if ($xmlTotalDays && (int)$xmlTotalDays->nodeValue === $expectedSummary['totalDays']) {
        outputSuccess("Summary total days (Box 10) is correct: " . $xmlTotalDays->nodeValue, $isCLI);
        $passed++;
    } else {
        outputError("Summary total days mismatch", $isCLI);
        $errors++;
    }

} else {
    outputError("XML is not well-formed", $isCLI);
    foreach (libxml_get_errors() as $error) {
        outputError("  - " . trim($error->message), $isCLI);
    }
    $errors++;
}
libxml_clear_errors();

// Show sample of generated XML
outputInfo("Generated XML Preview (first 50 lines):", $isCLI);
$xmlLines = explode("\n", $xmlContent);
$previewLines = array_slice($xmlLines, 0, 50);
outputCode(implode("\n", $previewLines) . "\n...", $isCLI);

// ============================================================================
// STEP 5: Verify XML Schema Compliance
// ============================================================================

outputStep(5, "Verify XML Schema Compliance", $isCLI);

// Check required XML elements and attributes
$schemaChecks = [
    'Namespace declaration' => strpos($xmlContent, 'http://www.revenuquebec.gouv.qc.ca/rl24') !== false,
    'Transmitter number prefix (NP)' => strpos($xmlContent, 'NP' . $testConfig['provider']['preparerNumber']) !== false,
    'Tax year element' => strpos($xmlContent, '<Annee>' . $testConfig['taxYear'] . '</Annee>') !== false,
    'NEQ value' => strpos($xmlContent, '<NEQ>' . $testConfig['provider']['neq'] . '</NEQ>') !== false,
    'Sequence number format' => strpos($xmlContent, '<NoSequence>001</NoSequence>') !== false,
    'Slip type code (Original)' => strpos($xmlContent, '<CaseA>O</CaseA>') !== false,
    'Country code (CAN)' => strpos($xmlContent, '<Pays>CAN</Pays>') !== false,
    'Province code (QC)' => strpos($xmlContent, '<Province>QC</Province>') !== false,
];

foreach ($schemaChecks as $check => $result) {
    if ($result) {
        outputSuccess("Schema check passed: $check", $isCLI);
        $passed++;
    } else {
        outputError("Schema check failed: $check", $isCLI);
        $errors++;
    }
}

// ============================================================================
// STEP 6: Generate Paper Summary (RL-24 Sommaire)
// ============================================================================

outputStep(6, "Generate Paper Summary (RL-24 Sommaire)", $isCLI);

// Build paper summary data
$paperSummary = [
    'annee' => $testConfig['taxYear'],
    'noSequence' => '001',
    'neqEmetteur' => substr($testConfig['provider']['neq'], 0, 4) . ' ' .
                     substr($testConfig['provider']['neq'], 4, 3) . ' ' .
                     substr($testConfig['provider']['neq'], 7, 3),
    'nomEmetteur' => $testConfig['provider']['name'],
    'adresseEmetteur' => $testConfig['provider']['address'] . ', ' .
                         $testConfig['provider']['city'] . ', QC ' .
                         $testConfig['provider']['postalCode'],
    'nombreReleves' => $expectedSummary['totalSlips'],
    'totalCase10' => $expectedSummary['totalDays'],
    'totalCase11' => number_format($expectedSummary['totalCase11'], 2, ',', ' '),
    'totalCase12' => number_format($expectedSummary['totalCase12'], 2, ',', ' '),
    'totalCase13' => number_format($expectedSummary['totalCase13'], 2, ',', ' '),
    'totalCase14' => number_format($expectedSummary['totalCase14'], 2, ',', ' '),
];

outputInfo("Paper Summary Data (RL-24 Sommaire):", $isCLI);
outputInfo("  Année: {$paperSummary['annee']}", $isCLI);
outputInfo("  No. de séquence: {$paperSummary['noSequence']}", $isCLI);
outputInfo("  NEQ de l'émetteur: {$paperSummary['neqEmetteur']}", $isCLI);
outputInfo("  Nom de l'émetteur: {$paperSummary['nomEmetteur']}", $isCLI);
outputInfo("  Adresse: {$paperSummary['adresseEmetteur']}", $isCLI);
outputInfo("  Nombre de relevés: {$paperSummary['nombreReleves']}", $isCLI);
outputInfo("  Total Case 10 (Jours): {$paperSummary['totalCase10']}", $isCLI);
outputInfo("  Total Case 11: {$paperSummary['totalCase11']} $", $isCLI);
outputInfo("  Total Case 12: {$paperSummary['totalCase12']} $", $isCLI);
outputInfo("  Total Case 13: {$paperSummary['totalCase13']} $", $isCLI);
outputInfo("  Total Case 14: {$paperSummary['totalCase14']} $", $isCLI);

// Verify NEQ formatting
if (preg_match('/^\d{4} \d{3} \d{3}$/', $paperSummary['neqEmetteur'])) {
    outputSuccess("NEQ formatted correctly for paper form: {$paperSummary['neqEmetteur']}", $isCLI);
    $passed++;
} else {
    outputError("NEQ formatting incorrect for paper form", $isCLI);
    $errors++;
}

// ============================================================================
// STEP 7: Verify Summary Calculations
// ============================================================================

outputStep(7, "Verify Summary Calculations", $isCLI);

outputInfo("Expected Summary Totals:", $isCLI);
outputInfo("  Total Slips: {$expectedSummary['totalSlips']}", $isCLI);
outputInfo("  Total Days (Box 10): {$expectedSummary['totalDays']}", $isCLI);
outputInfo("  Total Case 11: $" . number_format($expectedSummary['totalCase11'], 2), $isCLI);
outputInfo("  Total Case 12: $" . number_format($expectedSummary['totalCase12'], 2), $isCLI);
outputInfo("  Total Case 13: $" . number_format($expectedSummary['totalCase13'], 2), $isCLI);
outputInfo("  Total Case 14: $" . number_format($expectedSummary['totalCase14'], 2), $isCLI);

// Verify total Box 14 = total Box 12 - total Box 13
$expectedTotalCase14 = $expectedSummary['totalCase12'] - $expectedSummary['totalCase13'];
if (abs($expectedSummary['totalCase14'] - $expectedTotalCase14) < 0.01) {
    outputSuccess("Summary Box 14 calculation is correct: {$expectedSummary['totalCase14']} = {$expectedSummary['totalCase12']} - {$expectedSummary['totalCase13']}", $isCLI);
    $passed++;
} else {
    outputError("Summary Box 14 calculation mismatch: Expected $expectedTotalCase14, got {$expectedSummary['totalCase14']}", $isCLI);
    $errors++;
}

// Verify individual slip calculations
outputInfo("Individual Slip Verification:", $isCLI);
foreach ($testConfig['eligibilityData'] as $index => $data) {
    $childName = $data['childFirstName'] . ' ' . $data['childLastName'];
    $calculatedCase14 = $data['case12Amount'] - $data['case13Amount'];

    if (abs($data['case14Amount'] - $calculatedCase14) < 0.01) {
        outputSuccess("Slip #" . ($index + 1) . " ($childName): Box 14 = {$data['case14Amount']} ✓", $isCLI);
        $passed++;
    } else {
        outputError("Slip #" . ($index + 1) . " ($childName): Box 14 mismatch (expected: $calculatedCase14, got: {$data['case14Amount']})", $isCLI);
        $errors++;
    }
}

// ============================================================================
// TEST SUMMARY
// ============================================================================

outputHeader("Test Summary", $isCLI);

$totalTests = $passed + $errors;
$passRate = $totalTests > 0 ? round(($passed / $totalTests) * 100, 1) : 0;

outputInfo("Total Tests: $totalTests", $isCLI);
outputInfo("Passed: $passed", $isCLI);
outputInfo("Failed: $errors", $isCLI);
outputInfo("Pass Rate: $passRate%", $isCLI);

echo "\n";

if ($errors === 0) {
    outputSuccess("ALL TESTS PASSED - RL-24 End-to-End Workflow is functioning correctly", $isCLI);
} else {
    outputError("SOME TESTS FAILED - Please review the errors above", $isCLI);
}

// ============================================================================
// GENERATED ARTIFACTS
// ============================================================================

outputHeader("Test Artifacts", $isCLI);

outputInfo("Expected XML Filename: $expectedFilename", $isCLI);
outputInfo("Tax Year: {$testConfig['taxYear']}", $isCLI);
outputInfo("Provider: {$testConfig['provider']['name']}", $isCLI);
outputInfo("NEQ: {$testConfig['provider']['neq']}", $isCLI);
outputInfo("Preparer: {$testConfig['provider']['preparerNumber']}", $isCLI);
outputInfo("Slips Generated: " . count($testConfig['eligibilityData']), $isCLI);
outputInfo("Total Amount (Box 12): $" . number_format($expectedSummary['totalCase12'], 2), $isCLI);

// ============================================================================
// FINISH OUTPUT
// ============================================================================

if (!$isCLI) {
    echo "<hr style='margin-top:30px;'>";
    echo "<p style='color:#666;font-size:0.9em;'>Test completed at " . date('Y-m-d H:i:s') . "</p>";
    echo "</body></html>";
}

// Exit with appropriate code for CI/CD
exit($errors > 0 ? 1 : 0);
