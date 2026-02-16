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
use Gibbon\Module\ChildEnrollment\Domain\EnrollmentFormGateway;
use Gibbon\Domain\System\SettingGateway;

/**
 * Child Enrollment PDF Export
 *
 * Generates a printable Quebec-compliant Fiche d'Inscription (enrollment form) PDF.
 * This page outputs clean HTML designed for printing/PDF export from the browser.
 *
 * Usage: Opens in new browser tab and can be printed or saved as PDF using browser print.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// Get form ID from request
$gibbonChildEnrollmentFormID = $_GET['gibbonChildEnrollmentFormID'] ?? null;

// Validate form ID
if (empty($gibbonChildEnrollmentFormID)) {
    die('Error: No enrollment form ID specified.');
}

// Access check (must be logged in and have access to ChildEnrollment module)
if (!isset($session) || !$session->get('gibbonPersonID')) {
    die('Error: You must be logged in to access this resource.');
}

// Check module access
if (!isActionAccessible($guid, $connection2, '/modules/ChildEnrollment/enrollment_pdf.php')) {
    die('Error: You do not have access to this action.');
}

// Get gateway via DI container
$enrollmentFormGateway = $container->get(EnrollmentFormGateway::class);
$settingGateway = $container->get(SettingGateway::class);

// Get form with all related data
$form = $enrollmentFormGateway->getFormWithRelations($gibbonChildEnrollmentFormID);

if ($form === false) {
    die('Error: The specified enrollment form cannot be found.');
}

// Get organization settings for header
$organisationName = $session->get('organisationName') ?? '';
$organisationLogo = $session->get('organisationLogo') ?? '';
$organisationAddress = $settingGateway->getSettingByScope('System', 'organisationAddress') ?? '';

// Prepare dates for display
$generatedDate = date('Y-m-d H:i:s');
$childDOB = !empty($form['childDateOfBirth']) ? Format::date($form['childDateOfBirth']) : '-';
$admissionDate = !empty($form['admissionDate']) ? Format::date($form['admissionDate']) : '-';

// Helper function to escape HTML
function e($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

// Helper function to format phone numbers
function formatPhone($phone) {
    if (empty($phone)) return '-';
    return e($phone);
}

// Output clean HTML for print
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(__('Enrollment Form')) ?> - <?= e($form['formNumber']) ?></title>
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #333;
            background: #fff;
            padding: 10mm;
        }

        /* Print styles */
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

            .avoid-break {
                page-break-inside: avoid;
            }
        }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #333;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-logo {
            max-height: 60px;
            max-width: 150px;
        }

        .header-title {
            text-align: left;
        }

        .header-title h1 {
            font-size: 18pt;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .header-title h2 {
            font-size: 14pt;
            font-weight: normal;
            color: #666;
        }

        .header-right {
            text-align: right;
            font-size: 9pt;
        }

        .form-number {
            font-size: 11pt;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .form-status {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 9pt;
            font-weight: bold;
        }

        .status-draft { background-color: #e5e7eb; color: #374151; }
        .status-submitted { background-color: #fef3c7; color: #92400e; }
        .status-approved { background-color: #d1fae5; color: #065f46; }
        .status-rejected { background-color: #fee2e2; color: #991b1b; }
        .status-expired { background-color: #ffedd5; color: #9a3412; }

        /* Print actions */
        .print-actions {
            background: #f3f4f6;
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 5px;
            text-align: right;
        }

        .print-actions button {
            background: #3b82f6;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 10pt;
            margin-left: 10px;
        }

        .print-actions button:hover {
            background: #2563eb;
        }

        /* Sections */
        .section {
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            overflow: hidden;
        }

        .section-header {
            background: #f3f4f6;
            padding: 8px 12px;
            font-size: 11pt;
            font-weight: bold;
            border-bottom: 1px solid #ddd;
        }

        .section-content {
            padding: 10px 12px;
        }

        /* Grid layouts */
        .grid {
            display: table;
            width: 100%;
        }

        .grid-row {
            display: table-row;
        }

        .grid-cell {
            display: table-cell;
            padding: 5px 10px 5px 0;
            vertical-align: top;
        }

        .grid-2 .grid-cell { width: 50%; }
        .grid-3 .grid-cell { width: 33.33%; }
        .grid-4 .grid-cell { width: 25%; }

        .field-label {
            font-size: 8pt;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 2px;
        }

        .field-value {
            font-size: 10pt;
        }

        .field-value.mono {
            font-family: monospace;
        }

        /* Tables */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9pt;
        }

        table th, table td {
            border: 1px solid #ddd;
            padding: 5px 8px;
            text-align: left;
        }

        table th {
            background: #f9fafb;
            font-weight: bold;
            font-size: 8pt;
            text-transform: uppercase;
        }

        /* Attendance schedule */
        .schedule-table {
            margin: 10px 0;
        }

        .schedule-table th,
        .schedule-table td {
            text-align: center;
            width: 12.5%;
        }

        .schedule-check {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 1px solid #333;
            border-radius: 3px;
            text-align: center;
            line-height: 16px;
            font-weight: bold;
        }

        .schedule-check.checked {
            background: #333;
            color: white;
        }

        /* Alert boxes */
        .alert {
            padding: 8px 12px;
            border-radius: 5px;
            margin-bottom: 10px;
        }

        .alert-danger {
            background: #fee2e2;
            border: 1px solid #fca5a5;
            color: #991b1b;
        }

        .alert-info {
            background: #dbeafe;
            border: 1px solid #93c5fd;
            color: #1e40af;
        }

        /* Signatures */
        .signature-box {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
            min-height: 80px;
        }

        .signature-box img {
            max-height: 50px;
            max-width: 200px;
        }

        .signature-label {
            font-size: 8pt;
            color: #666;
            margin-top: 5px;
        }

        .signature-name {
            font-size: 9pt;
            font-weight: bold;
        }

        .signature-date {
            font-size: 8pt;
            color: #666;
        }

        /* Footer */
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 8pt;
            color: #666;
            text-align: center;
        }

        /* Two column layout for parents */
        .two-columns {
            display: flex;
            gap: 15px;
        }

        .two-columns > div {
            flex: 1;
        }

        .parent-box {
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
        }

        .parent-box.primary {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .parent-header {
            font-weight: bold;
            margin-bottom: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .primary-badge {
            background: #3b82f6;
            color: white;
            font-size: 7pt;
            padding: 2px 6px;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <!-- Print actions (hidden when printing) -->
    <div class="print-actions no-print">
        <button onclick="window.print()"><?= e(__('Print / Save as PDF')) ?></button>
        <button onclick="window.close()"><?= e(__('Close')) ?></button>
    </div>

    <!-- Header -->
    <div class="header">
        <div class="header-left">
            <?php if (!empty($organisationLogo)): ?>
            <img src="<?= $session->get('absoluteURL') ?>/<?= e($organisationLogo) ?>" alt="Logo" class="header-logo">
            <?php endif; ?>
            <div class="header-title">
                <h1><?= e($organisationName) ?></h1>
                <h2><?= e(__("Fiche d'Inscription / Enrollment Form")) ?></h2>
            </div>
        </div>
        <div class="header-right">
            <div class="form-number"><?= e($form['formNumber']) ?></div>
            <div class="form-status status-<?= strtolower($form['status']) ?>">
                <?= e(__($form['status'])) ?>
            </div>
            <div style="margin-top: 5px; font-size: 8pt;">
                <?= e(__('Version')) ?>: <?= (int)$form['version'] ?><br>
                <?= e(__('Generated')) ?>: <?= e($generatedDate) ?>
            </div>
        </div>
    </div>

    <!-- Section: Child Information -->
    <div class="section avoid-break">
        <div class="section-header"><?= e(__('Child Information / Informations sur l\'enfant')) ?></div>
        <div class="section-content">
            <div class="grid grid-3">
                <div class="grid-row">
                    <div class="grid-cell">
                        <div class="field-label"><?= e(__('Last Name / Nom')) ?></div>
                        <div class="field-value"><?= e($form['childLastName']) ?></div>
                    </div>
                    <div class="grid-cell">
                        <div class="field-label"><?= e(__('First Name / Prénom')) ?></div>
                        <div class="field-value"><?= e($form['childFirstName']) ?></div>
                    </div>
                    <div class="grid-cell">
                        <div class="field-label"><?= e(__('Date of Birth / Date de naissance')) ?></div>
                        <div class="field-value"><?= e($childDOB) ?></div>
                    </div>
                </div>
            </div>
            <div class="grid grid-2" style="margin-top: 8px;">
                <div class="grid-row">
                    <div class="grid-cell">
                        <div class="field-label"><?= e(__('Address / Adresse')) ?></div>
                        <div class="field-value">
                            <?php
                            $address = [];
                            if (!empty($form['childAddress'])) $address[] = $form['childAddress'];
                            if (!empty($form['childCity'])) $address[] = $form['childCity'];
                            if (!empty($form['childPostalCode'])) $address[] = $form['childPostalCode'];
                            echo !empty($address) ? e(implode(', ', $address)) : '-';
                            ?>
                        </div>
                    </div>
                    <div class="grid-cell">
                        <div class="field-label"><?= e(__('Languages Spoken / Langues parlées')) ?></div>
                        <div class="field-value"><?= e($form['languagesSpoken'] ?? '-') ?></div>
                    </div>
                </div>
            </div>
            <div class="grid grid-2" style="margin-top: 8px;">
                <div class="grid-row">
                    <div class="grid-cell">
                        <div class="field-label"><?= e(__('Admission Date / Date d\'admission')) ?></div>
                        <div class="field-value"><?= e($admissionDate) ?></div>
                    </div>
                    <div class="grid-cell">
                        <?php if (!empty($form['notes'])): ?>
                        <div class="field-label"><?= e(__('Notes')) ?></div>
                        <div class="field-value"><?= nl2br(e($form['notes'])) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section: Parent/Guardian Information -->
    <div class="section avoid-break">
        <div class="section-header"><?= e(__('Parent/Guardian Information / Informations sur les parents/tuteurs')) ?></div>
        <div class="section-content">
            <?php if (!empty($form['parents'])): ?>
            <div class="two-columns">
                <?php foreach ($form['parents'] as $parent): ?>
                    <?php $isPrimary = $parent['isPrimaryContact'] === 'Y'; ?>
                    <div class="parent-box <?= $isPrimary ? 'primary' : '' ?>">
                        <div class="parent-header">
                            <span><?= e(__('Parent')) ?> <?= (int)$parent['parentNumber'] ?></span>
                            <?php if ($isPrimary): ?>
                            <span class="primary-badge"><?= e(__('Primary Contact')) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="field-label"><?= e(__('Name / Nom')) ?></div>
                        <div class="field-value" style="margin-bottom: 5px;"><?= e($parent['name']) ?> (<?= e($parent['relationship']) ?>)</div>

                        <div class="grid grid-2">
                            <div class="grid-row">
                                <div class="grid-cell">
                                    <div class="field-label"><?= e(__('Cell Phone / Cellulaire')) ?></div>
                                    <div class="field-value"><?= formatPhone($parent['cellPhone']) ?></div>
                                </div>
                                <div class="grid-cell">
                                    <div class="field-label"><?= e(__('Home Phone / Domicile')) ?></div>
                                    <div class="field-value"><?= formatPhone($parent['homePhone']) ?></div>
                                </div>
                            </div>
                            <div class="grid-row">
                                <div class="grid-cell">
                                    <div class="field-label"><?= e(__('Work Phone / Travail')) ?></div>
                                    <div class="field-value"><?= formatPhone($parent['workPhone']) ?></div>
                                </div>
                                <div class="grid-cell">
                                    <div class="field-label"><?= e(__('Email / Courriel')) ?></div>
                                    <div class="field-value"><?= e($parent['email'] ?? '-') ?></div>
                                </div>
                            </div>
                        </div>

                        <?php
                        $parentAddress = [];
                        if (!empty($parent['address'])) $parentAddress[] = $parent['address'];
                        if (!empty($parent['city'])) $parentAddress[] = $parent['city'];
                        if (!empty($parent['postalCode'])) $parentAddress[] = $parent['postalCode'];
                        if (!empty($parentAddress)): ?>
                        <div class="field-label" style="margin-top: 5px;"><?= e(__('Address / Adresse')) ?></div>
                        <div class="field-value"><?= e(implode(', ', $parentAddress)) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($parent['employer'])): ?>
                        <div class="field-label" style="margin-top: 5px;"><?= e(__('Employer / Employeur')) ?></div>
                        <div class="field-value">
                            <?= e($parent['employer']) ?>
                            <?php if (!empty($parent['workHours'])): ?> (<?= e($parent['workHours']) ?>)<?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p><?= e(__('No parent information recorded.')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section: Authorized Pickups -->
    <div class="section avoid-break">
        <div class="section-header"><?= e(__('Authorized Pickup Persons / Personnes autorisées à récupérer l\'enfant')) ?></div>
        <div class="section-content">
            <?php if (!empty($form['authorizedPickups'])): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 8%;">#</th>
                        <th style="width: 25%;"><?= e(__('Name / Nom')) ?></th>
                        <th style="width: 20%;"><?= e(__('Relationship / Lien')) ?></th>
                        <th style="width: 20%;"><?= e(__('Phone / Téléphone')) ?></th>
                        <th><?= e(__('Notes')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($form['authorizedPickups'] as $pickup): ?>
                    <tr>
                        <td style="text-align: center;"><?= (int)$pickup['priority'] ?></td>
                        <td><?= e($pickup['name']) ?></td>
                        <td><?= e($pickup['relationship']) ?></td>
                        <td><?= formatPhone($pickup['phone']) ?></td>
                        <td><?= e($pickup['notes'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?= e(__('No authorized pickup persons recorded.')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section: Emergency Contacts -->
    <div class="section avoid-break">
        <div class="section-header"><?= e(__('Emergency Contacts / Contacts d\'urgence')) ?></div>
        <div class="section-content">
            <?php if (!empty($form['emergencyContacts'])): ?>
            <table>
                <thead>
                    <tr>
                        <th style="width: 8%;">#</th>
                        <th style="width: 25%;"><?= e(__('Name / Nom')) ?></th>
                        <th style="width: 18%;"><?= e(__('Relationship / Lien')) ?></th>
                        <th style="width: 18%;"><?= e(__('Phone / Téléphone')) ?></th>
                        <th style="width: 18%;"><?= e(__('Alternate / Autre')) ?></th>
                        <th><?= e(__('Notes')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($form['emergencyContacts'] as $contact): ?>
                    <tr>
                        <td style="text-align: center;"><?= (int)$contact['priority'] ?></td>
                        <td><?= e($contact['name']) ?></td>
                        <td><?= e($contact['relationship']) ?></td>
                        <td><?= formatPhone($contact['phone']) ?></td>
                        <td><?= formatPhone($contact['alternatePhone']) ?></td>
                        <td><?= e($contact['notes'] ?? '-') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p><?= e(__('No emergency contacts recorded.')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section: Health Information -->
    <div class="section avoid-break">
        <div class="section-header"><?= e(__('Health Information / Informations de santé')) ?></div>
        <div class="section-content">
            <?php if (!empty($form['health'])): ?>
                <?php $health = $form['health']; ?>

                <?php if (isset($health['hasEpiPen']) && $health['hasEpiPen'] === 'Y'): ?>
                <div class="alert alert-danger">
                    <strong><?= e(__('EPIPEN REQUIRED / ÉPIPEN REQUIS')) ?></strong>
                    <?php if (!empty($health['epiPenInstructions'])): ?>
                    <br><?= nl2br(e($health['epiPenInstructions'])) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="grid grid-2">
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Allergies / Allergies')) ?></div>
                            <div class="field-value">
                                <?php
                                if (!empty($health['allergies'])) {
                                    $allergies = json_decode($health['allergies'], true);
                                    if (is_array($allergies)) {
                                        foreach ($allergies as $allergy) {
                                            if (is_array($allergy)) {
                                                echo '• ' . e($allergy['name'] ?? '');
                                                if (!empty($allergy['severity'])) echo ' (' . e($allergy['severity']) . ')';
                                                echo '<br>';
                                            } else {
                                                echo '• ' . e($allergy) . '<br>';
                                            }
                                        }
                                    } else {
                                        echo e($health['allergies']);
                                    }
                                } else {
                                    echo e(__('None recorded'));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Medical Conditions / Conditions médicales')) ?></div>
                            <div class="field-value"><?= nl2br(e($health['medicalConditions'] ?? __('None recorded'))) ?></div>
                        </div>
                    </div>
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Medications / Médicaments')) ?></div>
                            <div class="field-value">
                                <?php
                                if (!empty($health['medications'])) {
                                    $medications = json_decode($health['medications'], true);
                                    if (is_array($medications)) {
                                        foreach ($medications as $med) {
                                            if (is_array($med)) {
                                                echo '• ' . e($med['name'] ?? '');
                                                if (!empty($med['dosage'])) echo ' - ' . e($med['dosage']);
                                                if (!empty($med['schedule'])) echo ' (' . e($med['schedule']) . ')';
                                                echo '<br>';
                                            } else {
                                                echo '• ' . e($med) . '<br>';
                                            }
                                        }
                                    } else {
                                        echo e($health['medications']);
                                    }
                                } else {
                                    echo e(__('None recorded'));
                                }
                                ?>
                            </div>
                        </div>
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Special Needs / Besoins spéciaux')) ?></div>
                            <div class="field-value"><?= nl2br(e($health['specialNeeds'] ?? __('None recorded'))) ?></div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                    <div class="grid grid-3">
                        <div class="grid-row">
                            <div class="grid-cell">
                                <div class="field-label"><?= e(__('Doctor Name / Nom du médecin')) ?></div>
                                <div class="field-value"><?= e($health['doctorName'] ?? '-') ?></div>
                            </div>
                            <div class="grid-cell">
                                <div class="field-label"><?= e(__('Doctor Phone / Téléphone du médecin')) ?></div>
                                <div class="field-value"><?= formatPhone($health['doctorPhone']) ?></div>
                            </div>
                            <div class="grid-cell">
                                <div class="field-label"><?= e(__('Doctor Address / Adresse du médecin')) ?></div>
                                <div class="field-value"><?= e($health['doctorAddress'] ?? '-') ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                    <div class="grid grid-2">
                        <div class="grid-row">
                            <div class="grid-cell">
                                <div class="field-label"><?= e(__('RAMQ Number / Numéro RAMQ')) ?></div>
                                <div class="field-value mono"><?= e($health['healthInsuranceNumber'] ?? '-') ?></div>
                            </div>
                            <div class="grid-cell">
                                <div class="field-label"><?= e(__('Insurance Expiry / Expiration assurance')) ?></div>
                                <div class="field-value"><?= !empty($health['healthInsuranceExpiry']) ? Format::date($health['healthInsuranceExpiry']) : '-' ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
            <p><?= e(__('No health information recorded.')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section: Nutrition Information -->
    <div class="section avoid-break">
        <div class="section-header"><?= e(__('Nutrition Information / Informations nutritionnelles')) ?></div>
        <div class="section-content">
            <?php if (!empty($form['nutrition'])): ?>
                <?php $nutrition = $form['nutrition']; ?>

                <?php if (isset($nutrition['isBottleFeeding']) && $nutrition['isBottleFeeding'] === 'Y'): ?>
                <div class="alert alert-info">
                    <strong><?= e(__('BOTTLE FEEDING / ALIMENTATION AU BIBERON')) ?></strong>
                    <?php if (!empty($nutrition['bottleFeedingInfo'])): ?>
                    <br><?= nl2br(e($nutrition['bottleFeedingInfo'])) ?>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="grid grid-2">
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Dietary Restrictions / Restrictions alimentaires')) ?></div>
                            <div class="field-value"><?= nl2br(e($nutrition['dietaryRestrictions'] ?? __('None recorded'))) ?></div>
                        </div>
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Food Allergies / Allergies alimentaires')) ?></div>
                            <div class="field-value"><?= nl2br(e($nutrition['foodAllergies'] ?? __('None recorded'))) ?></div>
                        </div>
                    </div>
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Food Preferences / Préférences alimentaires')) ?></div>
                            <div class="field-value"><?= nl2br(e($nutrition['foodPreferences'] ?? '-')) ?></div>
                        </div>
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Food Dislikes / Aliments non aimés')) ?></div>
                            <div class="field-value"><?= nl2br(e($nutrition['foodDislikes'] ?? '-')) ?></div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($nutrition['feedingInstructions'])): ?>
                <div style="margin-top: 10px;">
                    <div class="field-label"><?= e(__('Special Feeding Instructions / Instructions spéciales d\'alimentation')) ?></div>
                    <div class="field-value"><?= nl2br(e($nutrition['feedingInstructions'])) ?></div>
                </div>
                <?php endif; ?>
            <?php else: ?>
            <p><?= e(__('No nutrition information recorded.')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section: Attendance Schedule -->
    <div class="section avoid-break">
        <div class="section-header"><?= e(__('Weekly Attendance Schedule / Horaire hebdomadaire')) ?></div>
        <div class="section-content">
            <?php if (!empty($form['attendance'])): ?>
                <?php
                $attendance = $form['attendance'];
                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                $dayLabels = [
                    'monday' => __('Mon/Lun'),
                    'tuesday' => __('Tue/Mar'),
                    'wednesday' => __('Wed/Mer'),
                    'thursday' => __('Thu/Jeu'),
                    'friday' => __('Fri/Ven'),
                    'saturday' => __('Sat/Sam'),
                    'sunday' => __('Sun/Dim')
                ];
                ?>
                <table class="schedule-table">
                    <thead>
                        <tr>
                            <th></th>
                            <?php foreach ($days as $day): ?>
                            <th><?= e($dayLabels[$day]) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="font-weight: bold;"><?= e(__('AM')) ?></td>
                            <?php foreach ($days as $day): ?>
                                <?php $checked = isset($attendance[$day . 'Am']) && $attendance[$day . 'Am'] === 'Y'; ?>
                            <td><span class="schedule-check <?= $checked ? 'checked' : '' ?>"><?= $checked ? '✓' : '' ?></span></td>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <td style="font-weight: bold;"><?= e(__('PM')) ?></td>
                            <?php foreach ($days as $day): ?>
                                <?php $checked = isset($attendance[$day . 'Pm']) && $attendance[$day . 'Pm'] === 'Y'; ?>
                            <td><span class="schedule-check <?= $checked ? 'checked' : '' ?>"><?= $checked ? '✓' : '' ?></span></td>
                            <?php endforeach; ?>
                        </tr>
                    </tbody>
                </table>

                <div class="grid grid-3" style="margin-top: 10px;">
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Expected Arrival / Arrivée prévue')) ?></div>
                            <div class="field-value"><?= !empty($attendance['expectedArrivalTime']) ? Format::time($attendance['expectedArrivalTime']) : '-' ?></div>
                        </div>
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Expected Departure / Départ prévu')) ?></div>
                            <div class="field-value"><?= !empty($attendance['expectedDepartureTime']) ? Format::time($attendance['expectedDepartureTime']) : '-' ?></div>
                        </div>
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Hours/Week / Heures/Semaine')) ?></div>
                            <div class="field-value"><?= !empty($attendance['expectedHoursPerWeek']) ? $attendance['expectedHoursPerWeek'] . ' ' . __('hours') : '-' ?></div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
            <p><?= e(__('No attendance schedule recorded.')) ?></p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Section: Signatures -->
    <div class="section avoid-break">
        <div class="section-header"><?= e(__('Signatures / Signatures')) ?></div>
        <div class="section-content">
            <?php
            $signatureTypes = [
                'Parent1' => __('Parent 1 / Parent 1'),
                'Parent2' => __('Parent 2 / Parent 2'),
                'Director' => __('Director / Directeur(trice)')
            ];

            $signatureMap = [];
            if (!empty($form['signatures'])) {
                foreach ($form['signatures'] as $sig) {
                    $signatureMap[$sig['signatureType']] = $sig;
                }
            }
            ?>

            <div class="two-columns" style="gap: 20px;">
                <?php foreach ($signatureTypes as $type => $label): ?>
                <div class="signature-box">
                    <div class="field-label" style="margin-bottom: 5px;"><?= e($label) ?></div>
                    <?php if (isset($signatureMap[$type])): ?>
                        <?php $sig = $signatureMap[$type]; ?>
                        <?php if (!empty($sig['signatureData']) && strpos($sig['signatureData'], 'data:image') === 0): ?>
                        <img src="<?= $sig['signatureData'] ?>" alt="<?= e($label) ?>">
                        <?php else: ?>
                        <div style="font-style: italic; color: #666;"><?= e(__('Signature on file')) ?></div>
                        <?php endif; ?>
                        <div class="signature-name"><?= e($sig['signerName']) ?></div>
                        <div class="signature-date"><?= Format::dateTime($sig['signedAt']) ?></div>
                    <?php else: ?>
                        <div style="color: #999; font-style: italic; padding: 20px 0;">
                            <?= e(__('Not signed / Non signé')) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Section: Form Metadata -->
    <div class="section avoid-break">
        <div class="section-header"><?= e(__('Form Information / Informations du formulaire')) ?></div>
        <div class="section-content">
            <div class="grid grid-4">
                <div class="grid-row">
                    <div class="grid-cell">
                        <div class="field-label"><?= e(__('Form Number / Numéro')) ?></div>
                        <div class="field-value mono"><?= e($form['formNumber']) ?></div>
                    </div>
                    <div class="grid-cell">
                        <div class="field-label"><?= e(__('School Year / Année scolaire')) ?></div>
                        <div class="field-value"><?= e($form['schoolYearName'] ?? '-') ?></div>
                    </div>
                    <div class="grid-cell">
                        <div class="field-label"><?= e(__('Created / Créé')) ?></div>
                        <div class="field-value"><?= Format::dateTime($form['timestampCreated']) ?></div>
                    </div>
                    <div class="grid-cell">
                        <div class="field-label"><?= e(__('Modified / Modifié')) ?></div>
                        <div class="field-value"><?= Format::dateTime($form['timestampModified']) ?></div>
                    </div>
                </div>
            </div>

            <?php if ($form['status'] === 'Approved' && !empty($form['approvedAt'])): ?>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                <div class="grid grid-2">
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Approved / Approuvé')) ?></div>
                            <div class="field-value"><?= Format::dateTime($form['approvedAt']) ?></div>
                        </div>
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Approved By / Approuvé par')) ?></div>
                            <div class="field-value">
                                <?php if (!empty($form['approvedByName'])): ?>
                                <?= Format::name('', $form['approvedByName'], $form['approvedBySurname'], 'Staff') ?>
                                <?php else: ?>
                                -
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($form['status'] === 'Rejected' && !empty($form['rejectedAt'])): ?>
            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #eee;">
                <div class="grid grid-2">
                    <div class="grid-row">
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Rejected / Refusé')) ?></div>
                            <div class="field-value"><?= Format::dateTime($form['rejectedAt']) ?></div>
                        </div>
                        <div class="grid-cell">
                            <div class="field-label"><?= e(__('Rejection Reason / Raison du refus')) ?></div>
                            <div class="field-value" style="color: #991b1b;"><?= e($form['rejectionReason'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <div class="footer">
        <p>
            <?= e(__('This document was generated on')) ?> <?= e($generatedDate) ?>
            <?php if (!empty($organisationName)): ?>
            | <?= e($organisationName) ?>
            <?php endif; ?>
        </p>
        <p style="font-size: 7pt; margin-top: 3px;">
            <?= e(__('Quebec Fiche d\'Inscription - Child Enrollment Form')) ?> | <?= e($form['formNumber']) ?>
        </p>
    </div>
</body>
</html>
