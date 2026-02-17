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

use Gibbon\Services\Format;
use Gibbon\Module\ServiceAgreement\Domain\ServiceAgreementGateway;
use Gibbon\Module\ServiceAgreement\Domain\AnnexGateway;
use Gibbon\Module\ServiceAgreement\Domain\SignatureGateway;

// Direct access - no index.php wrapper for PDF output
if (!isset($guid) || !isset($connection2) || !isset($container) || !isset($session)) {
    die('Module access denied.');
}

// Get agreement ID from request
$gibbonServiceAgreementID = $_GET['gibbonServiceAgreementID'] ?? '';

if (empty($gibbonServiceAgreementID)) {
    die('No service agreement specified.');
}

// Get gateways via DI container
$serviceAgreementGateway = $container->get(ServiceAgreementGateway::class);
$annexGateway = $container->get(AnnexGateway::class);
$signatureGateway = $container->get(SignatureGateway::class);

// Get agreement with details
$agreement = $serviceAgreementGateway->getAgreementWithDetails($gibbonServiceAgreementID);

if (!$agreement) {
    die('The specified service agreement could not be found.');
}

// Security: Only allow PDF generation for signed agreements
if ($agreement['allSignaturesComplete'] !== 'Y') {
    die('PDF generation is only available for fully signed agreements.');
}

// Get annexes and signatures
$annexes = $annexGateway->selectAnnexesByAgreement($gibbonServiceAgreementID)->fetchAll();
$signatures = $signatureGateway->selectSignaturesByAgreement($gibbonServiceAgreementID)->fetchAll();

// Get annex type names (French for Quebec compliance)
$annexTypeNames = AnnexGateway::getAnnexTypeNames();
$annexTypeNamesFr = AnnexGateway::getAnnexTypeNamesFr();

// Helper functions
function formatDateFr($date) {
    if (empty($date)) return '-';
    return date('d/m/Y', strtotime($date));
}

function formatDateTimeFr($datetime) {
    if (empty($datetime)) return '-';
    return date('d/m/Y H:i', strtotime($datetime));
}

function formatCurrency($amount) {
    if (empty($amount)) return '-';
    return '$' . number_format($amount, 2);
}

function yesNo($value, $useFr = true) {
    if ($useFr) {
        return ($value === 'Y') ? 'Oui' : 'Non';
    }
    return ($value === 'Y') ? 'Yes' : 'No';
}

// Check if TCPDF is available
$tcpdfAvailable = false;
if (class_exists('TCPDF')) {
    $tcpdfAvailable = true;
}

// Generate filename
$filename = 'ServiceAgreement_' . preg_replace('/[^A-Za-z0-9\-]/', '', $agreement['agreementNumber']) . '.pdf';

// If TCPDF is available, use it for PDF generation
if ($tcpdfAvailable) {
    // TCPDF implementation would go here
    // For now, fall back to HTML
    $tcpdfAvailable = false;
}

// Output HTML for browser printing (fallback / primary method)
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($agreement['languagePreference'] ?? 'fr') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entente de Services - <?= htmlspecialchars($agreement['agreementNumber']) ?></title>
    <style>
        /* Print-optimized styles */
        @page {
            size: letter;
            margin: 1cm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            margin: 0;
            padding: 20px;
            background: #fff;
        }

        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
        }

        /* Header */
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }

        .header h1 {
            font-size: 18pt;
            margin: 0 0 5px 0;
        }

        .header h2 {
            font-size: 14pt;
            font-weight: normal;
            margin: 0 0 5px 0;
            color: #333;
        }

        .header .form-id {
            font-size: 10pt;
            color: #666;
        }

        .header .agreement-number {
            font-size: 12pt;
            font-weight: bold;
            margin-top: 10px;
        }

        /* Sections */
        .section {
            margin-bottom: 15px;
            border: 1px solid #ccc;
            padding: 10px;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            background: #f5f5f5;
            margin: -10px -10px 10px -10px;
            padding: 8px 10px;
            border-bottom: 1px solid #ccc;
        }

        .section-title-fr {
            font-style: italic;
            font-weight: normal;
            font-size: 10pt;
            color: #666;
        }

        /* Data rows */
        .data-row {
            display: flex;
            margin-bottom: 5px;
        }

        .data-label {
            font-weight: bold;
            width: 40%;
            flex-shrink: 0;
        }

        .data-value {
            width: 60%;
        }

        .data-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .data-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        th, td {
            border: 1px solid #ccc;
            padding: 6px 8px;
            text-align: left;
            font-size: 10pt;
        }

        th {
            background: #f5f5f5;
            font-weight: bold;
        }

        /* Signatures */
        .signature-box {
            border: 1px solid #000;
            padding: 15px;
            margin: 10px 0;
            min-height: 80px;
        }

        .signature-image {
            max-width: 200px;
            max-height: 60px;
        }

        .signature-typed {
            font-family: 'Brush Script MT', cursive;
            font-size: 20pt;
            color: #000080;
        }

        .signature-meta {
            font-size: 9pt;
            color: #666;
            margin-top: 5px;
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 3px;
            font-size: 10pt;
            font-weight: bold;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .status-signed {
            background: #d4edda;
            color: #155724;
        }

        .status-declined {
            background: #f8d7da;
            color: #721c24;
        }

        .status-na {
            background: #e2e3e5;
            color: #383d41;
        }

        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            font-size: 9pt;
            color: #666;
            text-align: center;
        }

        /* Notice box */
        .notice-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            margin: 10px 0;
        }

        .notice-box.important {
            background: #f8d7da;
            border-color: #f5c6cb;
        }

        /* Print button */
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #007bff;
            color: #fff;
            border: none;
            padding: 10px 20px;
            font-size: 14pt;
            cursor: pointer;
            border-radius: 5px;
            z-index: 1000;
        }

        .print-button:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <!-- Print button (hidden when printing) -->
    <button class="print-button no-print" onclick="window.print()">Print / Save as PDF</button>

    <!-- Document Header -->
    <div class="header">
        <h1>ENTENTE DE SERVICES DE GARDE</h1>
        <h2>Service Agreement / Quebec FO-0659</h2>
        <div class="form-id">Ministère de la Famille - Child Care Services Agreement</div>
        <div class="agreement-number">
            Agreement #: <?= htmlspecialchars($agreement['agreementNumber']) ?>
            <span class="status-badge status-active"><?= htmlspecialchars($agreement['status']) ?></span>
        </div>
    </div>

    <!-- ARTICLE 1: Identification of Parties -->
    <div class="section">
        <div class="section-title">
            Article 1: Identification of Parties
            <span class="section-title-fr">/ Identification des parties</span>
        </div>

        <div class="data-grid-3">
            <!-- Provider -->
            <div>
                <h4 style="margin: 0 0 10px 0; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Provider / Prestataire</h4>
                <div class="data-row">
                    <span class="data-label">Name:</span>
                    <span class="data-value"><?= htmlspecialchars($agreement['providerName'] ?? '-') ?></span>
                </div>
                <?php if (!empty($agreement['providerPermitNumber'])): ?>
                <div class="data-row">
                    <span class="data-label">Permit #:</span>
                    <span class="data-value"><?= htmlspecialchars($agreement['providerPermitNumber']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($agreement['providerAddress'])): ?>
                <div class="data-row">
                    <span class="data-label">Address:</span>
                    <span class="data-value"><?= nl2br(htmlspecialchars($agreement['providerAddress'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($agreement['providerPhone'])): ?>
                <div class="data-row">
                    <span class="data-label">Phone:</span>
                    <span class="data-value"><?= htmlspecialchars($agreement['providerPhone']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($agreement['providerEmail'])): ?>
                <div class="data-row">
                    <span class="data-label">Email:</span>
                    <span class="data-value"><?= htmlspecialchars($agreement['providerEmail']) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Child -->
            <div>
                <h4 style="margin: 0 0 10px 0; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Child / Enfant</h4>
                <div class="data-row">
                    <span class="data-label">Name:</span>
                    <span class="data-value"><?= htmlspecialchars($agreement['childName'] ?? Format::name('', $agreement['childPreferredName'], $agreement['childSurname'], 'Student')) ?></span>
                </div>
                <?php if (!empty($agreement['childDateOfBirth']) || !empty($agreement['childDob'])): ?>
                <div class="data-row">
                    <span class="data-label">Date of Birth:</span>
                    <span class="data-value"><?= formatDateFr($agreement['childDateOfBirth'] ?? $agreement['childDob'] ?? '') ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Parent -->
            <div>
                <h4 style="margin: 0 0 10px 0; border-bottom: 1px solid #ccc; padding-bottom: 5px;">Parent / Guardian</h4>
                <div class="data-row">
                    <span class="data-label">Name:</span>
                    <span class="data-value"><?= htmlspecialchars($agreement['parentName'] ?? Format::name('', $agreement['parentPreferredName'], $agreement['parentSurname'], 'Parent')) ?></span>
                </div>
                <?php if (!empty($agreement['parentAddress'])): ?>
                <div class="data-row">
                    <span class="data-label">Address:</span>
                    <span class="data-value"><?= nl2br(htmlspecialchars($agreement['parentAddress'])) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($agreement['parentPhone'])): ?>
                <div class="data-row">
                    <span class="data-label">Phone:</span>
                    <span class="data-value"><?= htmlspecialchars($agreement['parentPhone']) ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($agreement['parentEmail'])): ?>
                <div class="data-row">
                    <span class="data-label">Email:</span>
                    <span class="data-value"><?= htmlspecialchars($agreement['parentEmail']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ARTICLE 2 & 3: Services & Operating Hours -->
    <div class="section">
        <div class="section-title">
            Articles 2-3: Services & Operating Hours
            <span class="section-title-fr">/ Services et heures d'ouverture</span>
        </div>

        <div class="data-grid">
            <div>
                <h4 style="margin: 0 0 10px 0;">Services Provided</h4>
                <?php if (!empty($agreement['maxHoursPerDay'])): ?>
                <div class="data-row">
                    <span class="data-label">Max Hours/Day:</span>
                    <span class="data-value"><?= $agreement['maxHoursPerDay'] ?> hours</span>
                </div>
                <?php endif; ?>

                <?php
                $meals = [];
                if ($agreement['includesBreakfast'] === 'Y') $meals[] = 'Breakfast';
                if ($agreement['includesLunch'] === 'Y') $meals[] = 'Lunch';
                if ($agreement['includesSnacks'] === 'Y') $meals[] = 'Snacks';
                if ($agreement['includesDinner'] === 'Y') $meals[] = 'Dinner';
                ?>
                <?php if (!empty($meals)): ?>
                <div class="data-row">
                    <span class="data-label">Meals Included:</span>
                    <span class="data-value"><?= implode(', ', $meals) ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($agreement['serviceDescription'])): ?>
                <div class="data-row">
                    <span class="data-label">Additional Services:</span>
                    <span class="data-value"><?= nl2br(htmlspecialchars($agreement['serviceDescription'])) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <div>
                <h4 style="margin: 0 0 10px 0;">Operating Hours</h4>
                <?php if (!empty($agreement['operatingHoursStart']) && !empty($agreement['operatingHoursEnd'])): ?>
                <div class="data-row">
                    <span class="data-label">Hours:</span>
                    <span class="data-value"><?= $agreement['operatingHoursStart'] ?> - <?= $agreement['operatingHoursEnd'] ?></span>
                </div>
                <?php endif; ?>

                <?php if (!empty($agreement['operatingDays'])): ?>
                <?php
                $daysMap = [
                    'Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday',
                    'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday',
                ];
                $operatingDays = is_array($agreement['operatingDays']) ? $agreement['operatingDays'] : explode(',', $agreement['operatingDays']);
                $dayNames = array_map(function($day) use ($daysMap) {
                    return $daysMap[trim($day)] ?? trim($day);
                }, $operatingDays);
                ?>
                <div class="data-row">
                    <span class="data-label">Operating Days:</span>
                    <span class="data-value"><?= implode(', ', $dayNames) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ARTICLE 4: Attendance Pattern -->
    <?php if (!empty($agreement['attendancePattern']) || !empty($agreement['hoursPerWeek'])): ?>
    <div class="section">
        <div class="section-title">
            Article 4: Attendance Pattern
            <span class="section-title-fr">/ Mode de fréquentation</span>
        </div>

        <?php if (!empty($agreement['hoursPerWeek'])): ?>
        <div class="data-row">
            <span class="data-label">Hours Per Week:</span>
            <span class="data-value"><?= $agreement['hoursPerWeek'] ?> hours</span>
        </div>
        <?php endif; ?>

        <?php if (!empty($agreement['attendancePattern'])): ?>
        <div class="data-row">
            <span class="data-label">Schedule:</span>
            <span class="data-value"><?= nl2br(htmlspecialchars($agreement['attendancePattern'])) ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ARTICLE 5: Payment Terms -->
    <div class="section">
        <div class="section-title">
            Article 5: Payment Terms
            <span class="section-title-fr">/ Modalités de paiement</span>
        </div>

        <div class="data-grid">
            <div class="data-row">
                <span class="data-label">Contribution Type:</span>
                <span class="data-value"><?= htmlspecialchars($agreement['contributionType'] ?? '-') ?></span>
            </div>

            <?php if (!empty($agreement['dailyReducedContribution'])): ?>
            <div class="data-row">
                <span class="data-label">Daily Reduced Contribution:</span>
                <span class="data-value"><?= formatCurrency($agreement['dailyReducedContribution']) ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($agreement['additionalDailyRate'])): ?>
            <div class="data-row">
                <span class="data-label">Additional Daily Rate:</span>
                <span class="data-value"><?= formatCurrency($agreement['additionalDailyRate']) ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($agreement['paymentFrequency'])): ?>
            <div class="data-row">
                <span class="data-label">Payment Frequency:</span>
                <span class="data-value"><?= htmlspecialchars($agreement['paymentFrequency']) ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($agreement['paymentMethod'])): ?>
            <div class="data-row">
                <span class="data-label">Payment Method:</span>
                <span class="data-value"><?= htmlspecialchars($agreement['paymentMethod']) ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($agreement['paymentDueDay'])): ?>
            <div class="data-row">
                <span class="data-label">Payment Due Day:</span>
                <span class="data-value">Day <?= $agreement['paymentDueDay'] ?> of month</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ARTICLE 6: Late Pickup Fees -->
    <?php if (!empty($agreement['latePickupFeePerMinute']) || !empty($agreement['latePickupGracePeriod'])): ?>
    <div class="section">
        <div class="section-title">
            Article 6: Late Pickup Fees
            <span class="section-title-fr">/ Frais de retard</span>
        </div>

        <div class="data-grid-3">
            <?php if (!empty($agreement['latePickupFeePerMinute'])): ?>
            <div class="data-row">
                <span class="data-label">Fee Per Minute:</span>
                <span class="data-value"><?= formatCurrency($agreement['latePickupFeePerMinute']) ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($agreement['latePickupGracePeriod'])): ?>
            <div class="data-row">
                <span class="data-label">Grace Period:</span>
                <span class="data-value"><?= $agreement['latePickupGracePeriod'] ?> minutes</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($agreement['latePickupMaxFee'])): ?>
            <div class="data-row">
                <span class="data-label">Maximum Fee:</span>
                <span class="data-value"><?= formatCurrency($agreement['latePickupMaxFee']) ?></span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ARTICLE 7: Closure Days -->
    <div class="section">
        <div class="section-title">
            Article 7: Closure Days
            <span class="section-title-fr">/ Jours de fermeture</span>
        </div>

        <div class="data-grid">
            <div class="data-row">
                <span class="data-label">Statutory Holidays Closed:</span>
                <span class="data-value"><?= yesNo($agreement['statutoryHolidaysClosed'] ?? 'N', false) ?></span>
            </div>

            <?php if (!empty($agreement['summerClosureWeeks'])): ?>
            <div class="data-row">
                <span class="data-label">Summer Closure:</span>
                <span class="data-value"><?= $agreement['summerClosureWeeks'] ?> weeks</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($agreement['winterClosureWeeks'])): ?>
            <div class="data-row">
                <span class="data-label">Winter Closure:</span>
                <span class="data-value"><?= $agreement['winterClosureWeeks'] ?> weeks</span>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($agreement['closureDatesText'])): ?>
        <div class="data-row">
            <span class="data-label">Specific Closure Dates:</span>
            <span class="data-value"><?= nl2br(htmlspecialchars($agreement['closureDatesText'])) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ARTICLE 8: Absence Policy -->
    <div class="section">
        <div class="section-title">
            Article 8: Absence Policy
            <span class="section-title-fr">/ Politique d'absence</span>
        </div>

        <div class="data-grid-3">
            <?php if (!empty($agreement['maxAbsenceDaysPerYear'])): ?>
            <div class="data-row">
                <span class="data-label">Max Absence Days/Year:</span>
                <span class="data-value"><?= $agreement['maxAbsenceDaysPerYear'] ?> days</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($agreement['absenceNoticeRequired'])): ?>
            <div class="data-row">
                <span class="data-label">Notice Required:</span>
                <span class="data-value"><?= $agreement['absenceNoticeRequired'] ?> hours</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($agreement['absenceChargePolicy'])): ?>
            <?php
            $chargePolicies = [
                'ChargeAll' => 'Charge for all absences',
                'ChargePartial' => 'Charge partial for absences',
                'NoCharge' => 'No charge for absences',
            ];
            ?>
            <div class="data-row">
                <span class="data-label">Charge Policy:</span>
                <span class="data-value"><?= $chargePolicies[$agreement['absenceChargePolicy']] ?? $agreement['absenceChargePolicy'] ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($agreement['medicalAbsencePolicy'])): ?>
        <div class="data-row">
            <span class="data-label">Medical Absence Policy:</span>
            <span class="data-value"><?= nl2br(htmlspecialchars($agreement['medicalAbsencePolicy'])) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ARTICLE 9: Agreement Duration -->
    <div class="section">
        <div class="section-title">
            Article 9: Agreement Duration
            <span class="section-title-fr">/ Durée de l'entente</span>
        </div>

        <div class="data-grid">
            <div class="data-row">
                <span class="data-label">Effective Date:</span>
                <span class="data-value"><?= formatDateFr($agreement['effectiveDate'] ?? '') ?></span>
            </div>

            <div class="data-row">
                <span class="data-label">Expiration Date:</span>
                <span class="data-value"><?= !empty($agreement['expirationDate']) ? formatDateFr($agreement['expirationDate']) : 'No expiration' ?></span>
            </div>

            <?php if (!empty($agreement['renewalType'])): ?>
            <?php
            $renewalTypes = [
                'AutoRenew' => 'Automatic Annual Renewal',
                'RequiresRenewal' => 'Requires Explicit Renewal',
                'FixedTerm' => 'Fixed Term',
            ];
            ?>
            <div class="data-row">
                <span class="data-label">Renewal Type:</span>
                <span class="data-value"><?= $renewalTypes[$agreement['renewalType']] ?? $agreement['renewalType'] ?></span>
            </div>
            <?php endif; ?>

            <?php if (!empty($agreement['renewalNoticeRequired'])): ?>
            <div class="data-row">
                <span class="data-label">Renewal Notice:</span>
                <span class="data-value"><?= $agreement['renewalNoticeRequired'] ?> days</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ARTICLE 10: Termination Conditions -->
    <div class="section">
        <div class="section-title">
            Article 10: Termination Conditions
            <span class="section-title-fr">/ Conditions de résiliation</span>
        </div>

        <div class="data-grid">
            <?php if (!empty($agreement['parentTerminationNotice'])): ?>
            <div class="data-row">
                <span class="data-label">Parent Termination Notice:</span>
                <span class="data-value"><?= $agreement['parentTerminationNotice'] ?> days</span>
            </div>
            <?php endif; ?>

            <?php if (!empty($agreement['providerTerminationNotice'])): ?>
            <div class="data-row">
                <span class="data-label">Provider Termination Notice:</span>
                <span class="data-value"><?= $agreement['providerTerminationNotice'] ?> days</span>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($agreement['immediateTerminationConditions'])): ?>
        <div class="data-row">
            <span class="data-label">Immediate Termination Conditions:</span>
            <span class="data-value"><?= nl2br(htmlspecialchars($agreement['immediateTerminationConditions'])) ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($agreement['terminationRefundPolicy'])): ?>
        <div class="data-row">
            <span class="data-label">Termination Refund Policy:</span>
            <span class="data-value"><?= nl2br(htmlspecialchars($agreement['terminationRefundPolicy'])) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- ARTICLE 11: Special Conditions -->
    <?php if (!empty($agreement['specialConditions'])): ?>
    <div class="section">
        <div class="section-title">
            Article 11: Special Conditions
            <span class="section-title-fr">/ Conditions particulières</span>
        </div>
        <div><?= nl2br(htmlspecialchars($agreement['specialConditions'])) ?></div>
    </div>
    <?php endif; ?>

    <!-- ARTICLE 12: Consumer Protection Act Notice -->
    <div class="section">
        <div class="section-title">
            Article 12: Quebec Consumer Protection Act
            <span class="section-title-fr">/ Loi sur la protection du consommateur</span>
        </div>

        <div class="notice-box">
            <p><strong>IMPORTANT NOTICE / AVIS IMPORTANT:</strong></p>
            <p>This service agreement is subject to the Quebec Consumer Protection Act (Loi sur la protection du consommateur).</p>
            <p><em>Cette entente de services est assujettie à la Loi sur la protection du consommateur du Québec.</em></p>
            <ul>
                <li>The parent has 10 days to cancel the contract without penalty after receiving the signed copy</li>
                <li>All fees and charges must be clearly stated in the contract</li>
                <li>The provider must give written notice before any fee increases</li>
            </ul>
            <?php if ($agreement['consumerProtectionAcknowledged'] === 'Y'): ?>
            <p style="color: #155724;"><strong>&#10003; Consumer Protection Act acknowledged on <?= formatDateTimeFr($agreement['consumerProtectionAcknowledgedDate'] ?? '') ?></strong></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Page break before annexes -->
    <div class="page-break"></div>

    <!-- ANNEXES A-D -->
    <div class="section">
        <div class="section-title">
            Annexes A-D
            <span class="section-title-fr">/ Annexes A-D</span>
        </div>

        <?php foreach ($annexes as $annex): ?>
        <?php
        $annexType = $annex['annexType'];
        $annexName = $annexTypeNames[$annexType] ?? 'Annex ' . $annexType;
        $annexNameFr = $annexTypeNamesFr[$annexType] ?? '';

        $statusClass = 'status-na';
        if ($annex['status'] === 'Signed') $statusClass = 'status-signed';
        elseif ($annex['status'] === 'Declined') $statusClass = 'status-declined';
        ?>
        <div style="border: 1px solid #ccc; padding: 10px; margin: 10px 0;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h4 style="margin: 0;">Annex <?= $annexType ?>: <?= $annexName ?> <span style="font-weight: normal; font-style: italic; color: #666;">/ <?= $annexNameFr ?></span></h4>
                <span class="status-badge <?= $statusClass ?>"><?= htmlspecialchars($annex['status']) ?></span>
            </div>

            <?php if ($annex['status'] !== 'NotApplicable'): ?>
            <?php switch ($annexType):
                case 'A': // Field Trips ?>
                    <div class="data-row">
                        <span class="data-label">Field Trips Authorized:</span>
                        <span class="data-value"><?= yesNo($annex['fieldTripsAuthorized'] ?? 'N', false) ?></span>
                    </div>
                    <?php if (!empty($annex['fieldTripsConditions'])): ?>
                    <div class="data-row">
                        <span class="data-label">Conditions:</span>
                        <span class="data-value"><?= htmlspecialchars($annex['fieldTripsConditions']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php break;

                case 'B': // Hygiene Items ?>
                    <div class="data-row">
                        <span class="data-label">Hygiene Items Included:</span>
                        <span class="data-value"><?= yesNo($annex['hygieneItemsIncluded'] ?? 'N', false) ?></span>
                    </div>
                    <?php if (!empty($annex['hygieneItemsDescription'])): ?>
                    <div class="data-row">
                        <span class="data-label">Description:</span>
                        <span class="data-value"><?= htmlspecialchars($annex['hygieneItemsDescription']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($annex['hygieneItemsMonthlyFee'])): ?>
                    <div class="data-row">
                        <span class="data-label">Monthly Fee:</span>
                        <span class="data-value"><?= formatCurrency($annex['hygieneItemsMonthlyFee']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php break;

                case 'C': // Supplementary Meals ?>
                    <div class="data-row">
                        <span class="data-label">Supplementary Meals Included:</span>
                        <span class="data-value"><?= yesNo($annex['supplementaryMealsIncluded'] ?? 'N', false) ?></span>
                    </div>
                    <?php if (!empty($annex['supplementaryMealsDays'])): ?>
                    <div class="data-row">
                        <span class="data-label">Days:</span>
                        <span class="data-value"><?= htmlspecialchars($annex['supplementaryMealsDays']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($annex['supplementaryMealsDescription'])): ?>
                    <div class="data-row">
                        <span class="data-label">Description:</span>
                        <span class="data-value"><?= htmlspecialchars($annex['supplementaryMealsDescription']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($annex['supplementaryMealsFee'])): ?>
                    <div class="data-row">
                        <span class="data-label">Fee:</span>
                        <span class="data-value"><?= formatCurrency($annex['supplementaryMealsFee']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php break;

                case 'D': // Extended Hours ?>
                    <div class="data-row">
                        <span class="data-label">Extended Hours Included:</span>
                        <span class="data-value"><?= yesNo($annex['extendedHoursIncluded'] ?? 'N', false) ?></span>
                    </div>
                    <?php if (!empty($annex['extendedHoursStart']) && !empty($annex['extendedHoursEnd'])): ?>
                    <div class="data-row">
                        <span class="data-label">Extended Hours:</span>
                        <span class="data-value"><?= $annex['extendedHoursStart'] ?> - <?= $annex['extendedHoursEnd'] ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($annex['extendedHoursHourlyRate'])): ?>
                    <div class="data-row">
                        <span class="data-label">Hourly Rate:</span>
                        <span class="data-value"><?= formatCurrency($annex['extendedHoursHourlyRate']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($annex['extendedHoursMaxDaily'])): ?>
                    <div class="data-row">
                        <span class="data-label">Max Daily Fee:</span>
                        <span class="data-value"><?= formatCurrency($annex['extendedHoursMaxDaily']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php break;
            endswitch; ?>

            <?php if ($annex['status'] === 'Signed' && !empty($annex['signedDate'])): ?>
            <div class="signature-meta">
                Signed on <?= formatDateTimeFr($annex['signedDate']) ?>
                <?php if (!empty($annex['signedByName']) || !empty($annex['signedBySurname'])): ?>
                by <?= Format::name('', $annex['signedByName'] ?? '', $annex['signedBySurname'] ?? '', 'Staff') ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Page break before signatures -->
    <div class="page-break"></div>

    <!-- ARTICLE 13: Signatures -->
    <div class="section">
        <div class="section-title">
            Article 13: Signatures
            <span class="section-title-fr">/ Signatures</span>
        </div>

        <p><em>This agreement has been electronically signed by the following parties. Electronic signatures are legally binding under Quebec law.</em></p>
        <p><em>Cette entente a été signée électroniquement par les parties suivantes. Les signatures électroniques sont juridiquement contraignantes en vertu de la loi québécoise.</em></p>

        <?php foreach ($signatures as $signature): ?>
        <div class="signature-box">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <div><strong><?= htmlspecialchars($signature['signerType']) ?> / <?= ($signature['signerType'] === 'Parent' ? 'Parent/Tuteur' : ($signature['signerType'] === 'Provider' ? 'Prestataire' : 'Témoin')) ?></strong></div>
                    <div style="margin: 5px 0; font-size: 12pt;"><?= htmlspecialchars($signature['signerName']) ?></div>
                    <?php if (!empty($signature['signerEmail'])): ?>
                    <div style="font-size: 10pt; color: #666;"><?= htmlspecialchars($signature['signerEmail']) ?></div>
                    <?php endif; ?>
                </div>
                <div style="text-align: right;">
                    <!-- Signature display -->
                    <?php if ($signature['signatureType'] === 'Drawn' && !empty($signature['signatureData'])): ?>
                        <img src="<?= htmlspecialchars($signature['signatureData']) ?>" alt="Signature" class="signature-image" />
                    <?php elseif ($signature['signatureType'] === 'Typed'): ?>
                        <div class="signature-typed"><?= htmlspecialchars($signature['signerName']) ?></div>
                    <?php else: ?>
                        <div style="font-style: italic; color: #666;">[Electronic Signature on File]</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="signature-meta">
                <strong>Signed:</strong> <?= formatDateTimeFr($signature['signedDate']) ?> |
                <strong>IP Address:</strong> <?= htmlspecialchars($signature['ipAddress'] ?? '-') ?>
                <?php if ($signature['verified'] === 'Y'): ?>
                | <strong style="color: #155724;">&#10003; Verified</strong>
                <?php endif; ?>
            </div>

            <?php if ($signature['consumerProtectionAcknowledged'] === 'Y'): ?>
            <div class="signature-meta" style="color: #856404; background: #fff3cd; padding: 3px 5px; margin-top: 5px;">
                &#10003; Consumer Protection Act acknowledged at time of signature
            </div>
            <?php endif; ?>

            <?php if ($signature['legalAcknowledgment'] === 'Y'): ?>
            <div class="signature-meta" style="color: #155724;">
                &#10003; Legal acknowledgment provided - signer confirmed understanding of legally binding nature
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <!-- Signature verification info -->
        <div class="notice-box" style="margin-top: 15px; background: #e7f3ff; border-color: #007bff;">
            <p><strong>Electronic Signature Verification / Vérification de signature électronique</strong></p>
            <p style="font-size: 9pt;">
                This document contains electronic signatures that have been captured with full audit trail including
                IP addresses, timestamps, browser information, and verification hashes. These signatures are legally
                binding under Quebec's Act to establish a legal framework for information technology (LCCJTI) and
                Canada's Personal Information Protection and Electronic Documents Act (PIPEDA).
            </p>
        </div>
    </div>

    <!-- Document Footer -->
    <div class="footer">
        <div>
            <strong>Agreement Number:</strong> <?= htmlspecialchars($agreement['agreementNumber']) ?> |
            <strong>Generated:</strong> <?= date('d/m/Y H:i:s') ?> |
            <strong>Status:</strong> <?= htmlspecialchars($agreement['status']) ?>
        </div>
        <div style="margin-top: 5px;">
            Quebec FO-0659 Service Agreement (Entente de Services de Garde)
        </div>
        <?php if (!empty($agreement['agreementCompletedDate'])): ?>
        <div style="margin-top: 5px;">
            <strong>Agreement Completed:</strong> <?= formatDateTimeFr($agreement['agreementCompletedDate']) ?>
        </div>
        <?php endif; ?>
        <div style="margin-top: 10px; font-size: 8pt; color: #999;">
            This document was generated electronically and is a true representation of the signed service agreement.
            <br>Ce document a été généré électroniquement et représente fidèlement l'entente de services signée.
        </div>
    </div>

    <script>
        // Auto-focus on print dialog if URL has autoprint parameter
        if (window.location.search.includes('autoprint=1')) {
            window.onload = function() {
                window.print();
            };
        }
    </script>
</body>
</html>
