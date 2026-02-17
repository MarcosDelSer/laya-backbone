<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Hong Kong)

RL-24 Sample XML Validation Script
This script validates the sample_rl24_transmission.xml file against expected schema rules.

Usage:
  CLI:     php validate_sample_xml.php
  Browser: /modules/RL24Submission/tests/validate_sample_xml.php
*/

$isCLI = (php_sapi_name() === 'cli');

// Output formatting helpers
function output($text, $isCLI, $style = '') {
    if ($isCLI) {
        echo $text . "\n";
    } else {
        $styleAttr = $style ? " style='$style'" : '';
        echo "<div$styleAttr>" . htmlspecialchars($text) . "</div>\n";
    }
}

function outputSuccess($text, $isCLI) {
    output("✓ " . $text, $isCLI, 'color:green;margin:5px 0;');
}

function outputError($text, $isCLI) {
    output("✗ " . $text, $isCLI, 'color:red;margin:5px 0;font-weight:bold;');
}

function outputInfo($text, $isCLI) {
    output("  " . $text, $isCLI, 'color:#666;margin:5px 0 5px 20px;');
}

// Start output
if (!$isCLI) {
    echo "<!DOCTYPE html><html><head><title>RL-24 Sample XML Validation</title>";
    echo "<meta charset='UTF-8'>";
    echo "<style>body{font-family:sans-serif;max-width:900px;margin:40px auto;padding:20px;}</style>";
    echo "</head><body>";
    echo "<h1>RL-24 Sample XML Validation</h1>";
}

$errors = 0;
$passed = 0;

echo $isCLI ? "\n=== RL-24 Sample XML Validation ===\n\n" : "";

// Load the sample XML
$sampleFile = __DIR__ . '/sample_rl24_transmission.xml';

if (!file_exists($sampleFile)) {
    outputError("Sample XML file not found: $sampleFile", $isCLI);
    exit(1);
}

outputInfo("Loading: sample_rl24_transmission.xml", $isCLI);

// Load and parse XML
libxml_use_internal_errors(true);
$dom = new DOMDocument();
$loaded = $dom->load($sampleFile);

if (!$loaded) {
    outputError("Failed to load XML file", $isCLI);
    foreach (libxml_get_errors() as $error) {
        outputError("  - " . trim($error->message), $isCLI);
    }
    exit(1);
}

outputSuccess("XML file loaded successfully", $isCLI);
$passed++;

// Validate well-formedness
$wellFormed = $dom->loadXML($dom->saveXML());
if ($wellFormed) {
    outputSuccess("XML is well-formed", $isCLI);
    $passed++;
} else {
    outputError("XML is not well-formed", $isCLI);
    $errors++;
}

// Setup XPath
$xpath = new DOMXPath($dom);
$xpath->registerNamespace('rl24', 'http://www.revenuquebec.gouv.qc.ca/rl24');

// ============================================================================
// STRUCTURE VALIDATION
// ============================================================================

echo $isCLI ? "\n--- Structure Validation ---\n" : "<h2>Structure Validation</h2>";

// Check root element
$root = $dom->documentElement;
if ($root->localName === 'Transmission') {
    outputSuccess("Root element is 'Transmission'", $isCLI);
    $passed++;
} else {
    outputError("Root element should be 'Transmission', got: " . $root->localName, $isCLI);
    $errors++;
}

// Check namespace
$ns = $root->namespaceURI;
if ($ns === 'http://www.revenuquebec.gouv.qc.ca/rl24') {
    outputSuccess("Namespace is correct: $ns", $isCLI);
    $passed++;
} else {
    outputError("Incorrect namespace: $ns", $isCLI);
    $errors++;
}

// Check required sections
$sections = [
    'Entete' => '//rl24:Entete',
    'Groupe' => '//rl24:Groupe',
    'Emetteur' => '//rl24:Emetteur',
    'RL24 slips' => '//rl24:RL24',
    'Sommaire' => '//rl24:Sommaire',
];

foreach ($sections as $name => $xpathQuery) {
    $nodes = $xpath->query($xpathQuery);
    if ($nodes->length > 0) {
        outputSuccess("Found $name section (" . $nodes->length . " element(s))", $isCLI);
        $passed++;
    } else {
        outputError("Missing $name section", $isCLI);
        $errors++;
    }
}

// ============================================================================
// HEADER VALIDATION
// ============================================================================

echo $isCLI ? "\n--- Header (Entete) Validation ---\n" : "<h2>Header (Entete) Validation</h2>";

// Transmitter number
$noTransmetteur = $xpath->query('//rl24:NoTransmetteur')->item(0);
if ($noTransmetteur) {
    $value = $noTransmetteur->nodeValue;
    if (preg_match('/^NP\d{6}$/', $value)) {
        outputSuccess("Transmitter number format correct: $value", $isCLI);
        $passed++;
    } else {
        outputError("Invalid transmitter number format: $value (should be NP + 6 digits)", $isCLI);
        $errors++;
    }
}

// Tax year
$annee = $xpath->query('//rl24:Annee')->item(0);
if ($annee) {
    $value = $annee->nodeValue;
    if (preg_match('/^\d{4}$/', $value) && $value >= 2020 && $value <= 2030) {
        outputSuccess("Tax year format correct: $value", $isCLI);
        $passed++;
    } else {
        outputError("Invalid tax year: $value", $isCLI);
        $errors++;
    }
}

// Sequence number
$noSequence = $xpath->query('//rl24:NoSequence')->item(0);
if ($noSequence) {
    $value = $noSequence->nodeValue;
    if (preg_match('/^\d{3}$/', $value) || preg_match('/^\d{1,3}$/', $value)) {
        outputSuccess("Sequence number format correct: $value", $isCLI);
        $passed++;
    } else {
        outputError("Invalid sequence number: $value", $isCLI);
        $errors++;
    }
}

// Transmission type
$typeTransmission = $xpath->query('//rl24:TypeTransmission')->item(0);
if ($typeTransmission) {
    $value = $typeTransmission->nodeValue;
    if (in_array($value, ['O', 'A', 'D'])) {
        $typeNames = ['O' => 'Original', 'A' => 'Amended', 'D' => 'Cancelled'];
        outputSuccess("Transmission type valid: $value (" . $typeNames[$value] . ")", $isCLI);
        $passed++;
    } else {
        outputError("Invalid transmission type: $value (should be O, A, or D)", $isCLI);
        $errors++;
    }
}

// ============================================================================
// ISSUER VALIDATION
// ============================================================================

echo $isCLI ? "\n--- Issuer (Emetteur) Validation ---\n" : "<h2>Issuer (Emetteur) Validation</h2>";

// NEQ
$neq = $xpath->query('//rl24:Emetteur/rl24:NEQ')->item(0);
if ($neq) {
    $value = $neq->nodeValue;
    if (preg_match('/^\d{10}$/', $value)) {
        outputSuccess("NEQ format correct: $value", $isCLI);
        $passed++;
    } else {
        outputError("Invalid NEQ format: $value (should be 10 digits)", $isCLI);
        $errors++;
    }
}

// Issuer name
$issuerName = $xpath->query('//rl24:Emetteur/rl24:NomEmetteur/rl24:Ligne1')->item(0);
if ($issuerName && !empty($issuerName->nodeValue)) {
    outputSuccess("Issuer name present: " . $issuerName->nodeValue, $isCLI);
    $passed++;
} else {
    outputError("Missing issuer name", $isCLI);
    $errors++;
}

// Province
$province = $xpath->query('//rl24:Emetteur/rl24:AdresseEmetteur/rl24:Province')->item(0);
if ($province && $province->nodeValue === 'QC') {
    outputSuccess("Issuer province is QC", $isCLI);
    $passed++;
} else {
    outputError("Issuer province should be QC", $isCLI);
    $errors++;
}

// ============================================================================
// SLIP VALIDATION
// ============================================================================

echo $isCLI ? "\n--- RL-24 Slip Validation ---\n" : "<h2>RL-24 Slip Validation</h2>";

$slips = $xpath->query('//rl24:RL24');
outputInfo("Found " . $slips->length . " RL-24 slip(s)", $isCLI);

$slipNumber = 0;
$totalDays = 0;
$totalCase11 = 0;
$totalCase12 = 0;
$totalCase13 = 0;
$totalCase14 = 0;

foreach ($slips as $slip) {
    $slipNumber++;
    outputInfo("Validating Slip #$slipNumber...", $isCLI);

    // Get slip number from XML
    $noReleve = $xpath->query('rl24:IdentificationReleve/rl24:NoReleve', $slip)->item(0);
    if ($noReleve && $noReleve->nodeValue == $slipNumber) {
        outputSuccess("  Slip number matches: " . $noReleve->nodeValue, $isCLI);
        $passed++;
    }

    // Case A (slip type)
    $caseA = $xpath->query('rl24:IdentificationReleve/rl24:CaseA', $slip)->item(0);
    if ($caseA && in_array($caseA->nodeValue, ['O', 'A', 'D'])) {
        outputSuccess("  Case A type valid: " . $caseA->nodeValue, $isCLI);
        $passed++;
    } else {
        outputError("  Invalid Case A type", $isCLI);
        $errors++;
    }

    // SIN validation (Luhn algorithm)
    $nas = $xpath->query('rl24:Beneficiaire/rl24:NAS', $slip)->item(0);
    if ($nas) {
        $sin = $nas->nodeValue;
        if (preg_match('/^\d{9}$/', $sin)) {
            // Luhn validation
            $digits = str_split($sin);
            $sum = 0;
            for ($i = 0; $i < 9; $i++) {
                $digit = (int) $digits[$i];
                if ($i % 2 === 1) {
                    $digit *= 2;
                    if ($digit > 9) $digit -= 9;
                }
                $sum += $digit;
            }
            if ($sum % 10 === 0) {
                outputSuccess("  SIN passes Luhn validation: " . substr($sin, 0, 3) . "***" . substr($sin, 6), $isCLI);
                $passed++;
            } else {
                outputError("  SIN fails Luhn validation", $isCLI);
                $errors++;
            }
        } else {
            outputError("  Invalid SIN format", $isCLI);
            $errors++;
        }
    }

    // Child date of birth
    $childDob = $xpath->query('rl24:Enfant/rl24:DateNaissance', $slip)->item(0);
    if ($childDob && preg_match('/^\d{4}-\d{2}-\d{2}$/', $childDob->nodeValue)) {
        outputSuccess("  Child DOB format valid: " . $childDob->nodeValue, $isCLI);
        $passed++;
    } else {
        outputError("  Invalid child DOB format", $isCLI);
        $errors++;
    }

    // Service period dates
    $dateDebut = $xpath->query('rl24:PeriodeService/rl24:DateDebut', $slip)->item(0);
    $dateFin = $xpath->query('rl24:PeriodeService/rl24:DateFin', $slip)->item(0);
    if ($dateDebut && $dateFin) {
        $start = strtotime($dateDebut->nodeValue);
        $end = strtotime($dateFin->nodeValue);
        if ($start < $end) {
            outputSuccess("  Service period dates valid: {$dateDebut->nodeValue} to {$dateFin->nodeValue}", $isCLI);
            $passed++;
        } else {
            outputError("  Service period dates invalid (start >= end)", $isCLI);
            $errors++;
        }
    }

    // Amounts
    $case10 = $xpath->query('rl24:Case10', $slip)->item(0);
    $case11 = $xpath->query('rl24:Case11', $slip)->item(0);
    $case12 = $xpath->query('rl24:Case12', $slip)->item(0);
    $case13 = $xpath->query('rl24:Case13', $slip)->item(0);
    $case14 = $xpath->query('rl24:Case14', $slip)->item(0);

    if ($case10) $totalDays += (int) $case10->nodeValue;
    if ($case11) $totalCase11 += (float) $case11->nodeValue;
    if ($case12) $totalCase12 += (float) $case12->nodeValue;
    if ($case13) $totalCase13 += (float) $case13->nodeValue;
    if ($case14) $totalCase14 += (float) $case14->nodeValue;

    // Validate Box 14 = Box 12 - Box 13
    if ($case12 && $case14) {
        $c12 = (float) $case12->nodeValue;
        $c13 = $case13 ? (float) $case13->nodeValue : 0;
        $c14 = (float) $case14->nodeValue;
        $expected = $c12 - $c13;

        if (abs($c14 - $expected) < 0.01) {
            outputSuccess("  Box 14 calculation correct: $c14 = $c12 - $c13", $isCLI);
            $passed++;
        } else {
            outputError("  Box 14 calculation incorrect: expected $expected, got $c14", $isCLI);
            $errors++;
        }
    }
}

// ============================================================================
// SUMMARY VALIDATION
// ============================================================================

echo $isCLI ? "\n--- Summary (Sommaire) Validation ---\n" : "<h2>Summary (Sommaire) Validation</h2>";

// Verify slip count
$nombreReleves = $xpath->query('//rl24:Sommaire/rl24:NombreReleves')->item(0);
if ($nombreReleves && (int) $nombreReleves->nodeValue === $slips->length) {
    outputSuccess("Summary slip count matches: " . $nombreReleves->nodeValue, $isCLI);
    $passed++;
} else {
    outputError("Summary slip count mismatch: expected " . $slips->length . ", got " . ($nombreReleves ? $nombreReleves->nodeValue : 'N/A'), $isCLI);
    $errors++;
}

// Verify total days
$totalCase10Xml = $xpath->query('//rl24:Sommaire/rl24:TotalCase10')->item(0);
if ($totalCase10Xml && (int) $totalCase10Xml->nodeValue === $totalDays) {
    outputSuccess("Summary total days correct: " . $totalCase10Xml->nodeValue, $isCLI);
    $passed++;
} else {
    outputError("Summary total days mismatch: expected $totalDays, got " . ($totalCase10Xml ? $totalCase10Xml->nodeValue : 'N/A'), $isCLI);
    $errors++;
}

// Verify total amounts
$summaryChecks = [
    'TotalCase11' => $totalCase11,
    'TotalCase12' => $totalCase12,
    'TotalCase13' => $totalCase13,
    'TotalCase14' => $totalCase14,
];

foreach ($summaryChecks as $element => $expected) {
    $node = $xpath->query("//rl24:Sommaire/rl24:$element")->item(0);
    if ($node && abs((float) $node->nodeValue - $expected) < 0.01) {
        outputSuccess("Summary $element correct: " . $node->nodeValue, $isCLI);
        $passed++;
    } elseif ($expected == 0 && !$node) {
        outputSuccess("Summary $element correctly omitted (zero value)", $isCLI);
        $passed++;
    } else {
        outputError("Summary $element mismatch: expected $expected, got " . ($node ? $node->nodeValue : 'N/A'), $isCLI);
        $errors++;
    }
}

// Verify Box 14 summary calculation
$expectedSummaryCase14 = $totalCase12 - $totalCase13;
if (abs($totalCase14 - $expectedSummaryCase14) < 0.01) {
    outputSuccess("Summary Box 14 = Box 12 - Box 13: $totalCase14 = $totalCase12 - $totalCase13", $isCLI);
    $passed++;
} else {
    outputError("Summary Box 14 calculation incorrect: expected $expectedSummaryCase14, got $totalCase14", $isCLI);
    $errors++;
}

// ============================================================================
// RESULTS SUMMARY
// ============================================================================

echo $isCLI ? "\n=== Validation Results ===\n" : "<h2>Validation Results</h2>";

$total = $passed + $errors;
$passRate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;

outputInfo("Total Checks: $total", $isCLI);
outputInfo("Passed: $passed", $isCLI);
outputInfo("Failed: $errors", $isCLI);
outputInfo("Pass Rate: $passRate%", $isCLI);

echo "\n";

if ($errors === 0) {
    outputSuccess("ALL VALIDATION CHECKS PASSED - Sample XML is valid", $isCLI);
} else {
    outputError("VALIDATION FAILED - $errors error(s) found", $isCLI);
}

if (!$isCLI) {
    echo "</body></html>";
}

exit($errors > 0 ? 1 : 0);
