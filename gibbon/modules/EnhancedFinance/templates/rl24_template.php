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
 * Official Quebec RL-24 Tax Receipt Template
 *
 * Relevé 24 - Frais de garde d'enfants
 * (Childcare Expense Tax Receipt)
 *
 * This template matches the official Quebec government specifications
 * for the RL-24 tax form used to claim childcare expense tax credits.
 *
 * Variables available:
 * @var array $releve24    RL-24 document data (document_year, total_eligible, id, etc.)
 * @var array $familyData  Parent/guardian data (name, familyName, address, email)
 * @var array $childData   Child data (name, dob, gender)
 * @var array $schoolData  Institution data (name, address, neq, phone)
 */

// Extract data with defaults
$documentYear = $releve24['document_year'] ?? date('Y');
$totalEligible = isset($releve24['total_eligible']) ? (float) $releve24['total_eligible'] : 0;
$documentId = $releve24['id'] ?? '';
$emissionDate = date('Y-m-d');
$emissionDateFormatted = date('j F Y');

// Format child date of birth
$childDob = '';
if (!empty($childData['dob'])) {
    $dobTimestamp = strtotime($childData['dob']);
    if ($dobTimestamp !== false) {
        $childDob = date('Y-m-d', $dobTimestamp);
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relevé 24 - <?php echo htmlspecialchars($documentYear); ?> - <?php echo htmlspecialchars($childData['name'] ?? 'N/A'); ?></title>
    <style>
        /* Reset and base styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
            background: #fff;
            padding: 15mm;
        }

        /* Page setup for print */
        @page {
            size: letter;
            margin: 15mm;
        }

        @media print {
            body {
                padding: 0;
            }
        }

        /* Header section - Government branding */
        .rl24-header {
            border-bottom: 3px solid #003366;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .rl24-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }

        .rl24-gov-logo {
            font-size: 14pt;
            font-weight: bold;
            color: #003366;
        }

        .rl24-form-code {
            text-align: right;
            font-size: 10pt;
        }

        .rl24-form-code .code-box {
            display: inline-block;
            border: 2px solid #003366;
            padding: 5px 15px;
            font-weight: bold;
            font-size: 12pt;
            background: #f0f4f8;
        }

        .rl24-title {
            text-align: center;
            margin: 10px 0;
        }

        .rl24-title h1 {
            font-size: 16pt;
            font-weight: bold;
            color: #003366;
            margin-bottom: 5px;
        }

        .rl24-title h2 {
            font-size: 12pt;
            font-weight: normal;
            color: #333;
        }

        .rl24-year-badge {
            text-align: center;
            margin: 10px 0;
        }

        .rl24-year-badge span {
            display: inline-block;
            background: #003366;
            color: #fff;
            padding: 8px 30px;
            font-size: 14pt;
            font-weight: bold;
            border-radius: 3px;
        }

        /* Form sections */
        .rl24-section {
            margin: 15px 0;
            page-break-inside: avoid;
        }

        .rl24-section-header {
            background: #003366;
            color: #fff;
            padding: 6px 12px;
            font-weight: bold;
            font-size: 11pt;
            margin-bottom: 10px;
        }

        .rl24-section-content {
            padding: 0 5px;
        }

        /* Form rows */
        .rl24-row {
            display: flex;
            margin-bottom: 8px;
            align-items: baseline;
        }

        .rl24-label {
            width: 180px;
            font-weight: bold;
            color: #333;
            font-size: 10pt;
            flex-shrink: 0;
        }

        .rl24-value {
            flex: 1;
            border-bottom: 1px solid #999;
            padding: 2px 8px;
            min-height: 18px;
            font-size: 11pt;
        }

        .rl24-value.no-border {
            border-bottom: none;
        }

        /* Two-column layout */
        .rl24-two-col {
            display: flex;
            gap: 30px;
        }

        .rl24-col {
            flex: 1;
        }

        /* Amount section - highlighted */
        .rl24-amount-section {
            margin: 25px 0;
            border: 3px solid #003366;
            background: #f8f9fa;
        }

        .rl24-amount-header {
            background: #003366;
            color: #fff;
            padding: 10px 15px;
            text-align: center;
        }

        .rl24-amount-header h3 {
            font-size: 12pt;
            margin-bottom: 3px;
        }

        .rl24-amount-header .box-ref {
            font-size: 9pt;
            opacity: 0.9;
        }

        .rl24-amount-body {
            padding: 20px;
            text-align: center;
        }

        .rl24-amount-label {
            font-size: 11pt;
            color: #333;
            margin-bottom: 10px;
        }

        .rl24-amount-value {
            font-size: 28pt;
            font-weight: bold;
            color: #003366;
            letter-spacing: 1px;
        }

        .rl24-amount-value .currency {
            font-size: 18pt;
            vertical-align: super;
        }

        /* Box codes legend */
        .rl24-box-legend {
            margin: 20px 0;
            padding: 10px;
            background: #f5f5f5;
            border: 1px solid #ddd;
            font-size: 9pt;
        }

        .rl24-box-legend-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .rl24-box-item {
            margin: 3px 0;
        }

        .rl24-box-item .box-num {
            display: inline-block;
            width: 40px;
            font-weight: bold;
        }

        /* Official notice section */
        .rl24-official-notice {
            margin: 25px 0;
            padding: 15px;
            border: 1px solid #003366;
            background: #f0f4f8;
        }

        .rl24-official-notice h4 {
            font-size: 10pt;
            color: #003366;
            margin-bottom: 8px;
        }

        .rl24-official-notice p {
            font-size: 9pt;
            color: #333;
            margin-bottom: 5px;
        }

        .rl24-official-notice ul {
            font-size: 9pt;
            margin-left: 20px;
            color: #333;
        }

        /* Footer */
        .rl24-footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 2px solid #003366;
            font-size: 9pt;
            color: #666;
        }

        .rl24-footer-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }

        .rl24-document-id {
            font-family: 'Courier New', monospace;
            font-size: 8pt;
            color: #999;
        }

        /* Signature line */
        .rl24-signature {
            margin-top: 25px;
            padding-top: 15px;
        }

        .rl24-signature-line {
            display: flex;
            align-items: flex-end;
            gap: 20px;
        }

        .rl24-signature-box {
            flex: 1;
        }

        .rl24-signature-box .line {
            border-bottom: 1px solid #333;
            height: 30px;
            margin-bottom: 5px;
        }

        .rl24-signature-box .label {
            font-size: 9pt;
            color: #666;
        }

        /* Print-specific styles */
        @media print {
            .rl24-amount-section {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .rl24-section-header,
            .rl24-amount-header,
            .rl24-year-badge span {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="rl24-header">
        <div class="rl24-header-top">
            <div class="rl24-gov-logo">
                GOUVERNEMENT DU QUÉBEC<br>
                <span style="font-size: 10pt; font-weight: normal;">Ministère du Revenu</span>
            </div>
            <div class="rl24-form-code">
                <div class="code-box">RL-24</div>
                <div style="margin-top: 5px; font-size: 9pt;">Case / Box 46</div>
            </div>
        </div>

        <div class="rl24-title">
            <h1>RELEVÉ 24 - FRAIS DE GARDE D'ENFANTS</h1>
            <h2>Childcare Expense Tax Receipt</h2>
        </div>

        <div class="rl24-year-badge">
            <span>ANNÉE D'IMPOSITION / TAX YEAR: <?php echo htmlspecialchars($documentYear); ?></span>
        </div>
    </div>

    <!-- Institution Section -->
    <div class="rl24-section">
        <div class="rl24-section-header">
            A - IDENTIFICATION DE L'ÉTABLISSEMENT / INSTITUTION IDENTIFICATION
        </div>
        <div class="rl24-section-content">
            <div class="rl24-row">
                <span class="rl24-label">Nom / Name:</span>
                <span class="rl24-value"><?php echo htmlspecialchars($schoolData['name'] ?? 'N/A'); ?></span>
            </div>
            <div class="rl24-two-col">
                <div class="rl24-col">
                    <div class="rl24-row">
                        <span class="rl24-label">NEQ:</span>
                        <span class="rl24-value"><?php echo htmlspecialchars($schoolData['neq'] ?? ''); ?></span>
                    </div>
                </div>
                <div class="rl24-col">
                    <div class="rl24-row">
                        <span class="rl24-label">Téléphone / Phone:</span>
                        <span class="rl24-value"><?php echo htmlspecialchars($schoolData['phone'] ?? ''); ?></span>
                    </div>
                </div>
            </div>
            <div class="rl24-row">
                <span class="rl24-label">Adresse / Address:</span>
                <span class="rl24-value"><?php echo htmlspecialchars($schoolData['address'] ?? ''); ?></span>
            </div>
        </div>
    </div>

    <!-- Child Section -->
    <div class="rl24-section">
        <div class="rl24-section-header">
            B - IDENTIFICATION DE L'ENFANT / CHILD IDENTIFICATION
        </div>
        <div class="rl24-section-content">
            <div class="rl24-row">
                <span class="rl24-label">Nom de l'enfant / Child's Name:</span>
                <span class="rl24-value"><?php echo htmlspecialchars($childData['name'] ?? 'N/A'); ?></span>
            </div>
            <div class="rl24-two-col">
                <div class="rl24-col">
                    <div class="rl24-row">
                        <span class="rl24-label">Date de naissance / DOB:</span>
                        <span class="rl24-value"><?php echo htmlspecialchars($childDob); ?></span>
                    </div>
                </div>
                <div class="rl24-col">
                    <div class="rl24-row">
                        <span class="rl24-label">Sexe / Gender:</span>
                        <span class="rl24-value"><?php
                            $gender = $childData['gender'] ?? '';
                            if ($gender === 'M') {
                                echo 'Masculin / Male';
                            } elseif ($gender === 'F') {
                                echo 'Féminin / Female';
                            } else {
                                echo htmlspecialchars($gender);
                            }
                        ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Parent/Guardian Section -->
    <div class="rl24-section">
        <div class="rl24-section-header">
            C - IDENTIFICATION DU PARENT / TUTEUR - PARENT / GUARDIAN IDENTIFICATION
        </div>
        <div class="rl24-section-content">
            <div class="rl24-row">
                <span class="rl24-label">Nom / Name:</span>
                <span class="rl24-value"><?php echo htmlspecialchars($familyData['name'] ?? 'N/A'); ?></span>
            </div>
            <div class="rl24-row">
                <span class="rl24-label">Adresse / Address:</span>
                <span class="rl24-value"><?php echo htmlspecialchars($familyData['address'] ?? 'N/A'); ?></span>
            </div>
        </div>
    </div>

    <!-- Eligible Amount Section (Box 46) -->
    <div class="rl24-amount-section">
        <div class="rl24-amount-header">
            <h3>CASE 46 - FRAIS DE GARDE ADMISSIBLES</h3>
            <div class="box-ref">BOX 46 - ELIGIBLE CHILDCARE EXPENSES</div>
        </div>
        <div class="rl24-amount-body">
            <div class="rl24-amount-label">
                Montant total des frais de garde admissibles payés pour l'année d'imposition <?php echo htmlspecialchars($documentYear); ?><br>
                <em>Total eligible childcare expenses paid for tax year <?php echo htmlspecialchars($documentYear); ?></em>
            </div>
            <div class="rl24-amount-value">
                <span class="currency">$</span><?php echo number_format($totalEligible, 2); ?>
            </div>
        </div>
    </div>

    <!-- Box Legend -->
    <div class="rl24-box-legend">
        <div class="rl24-box-legend-title">Légende des cases / Box Legend:</div>
        <div class="rl24-box-item">
            <span class="box-num">Case 46:</span>
            Frais de garde admissibles pour la déduction fiscale / Eligible childcare expenses for tax deduction
        </div>
    </div>

    <!-- Official Notice -->
    <div class="rl24-official-notice">
        <h4>AVIS IMPORTANT / IMPORTANT NOTICE</h4>
        <p>
            Ce relevé est émis conformément aux exigences fiscales du Québec et du Canada.
            <em>This receipt is issued in accordance with Quebec and Canadian tax requirements.</em>
        </p>
        <ul>
            <li>Conservez ce document pour vos déclarations de revenus / Keep this document for your tax returns</li>
            <li>Le montant indiqué à la case 46 peut être utilisé pour la déduction des frais de garde / The amount in box 46 may be used for childcare expense deduction</li>
            <li>Consultez un professionnel fiscal pour connaître les limites de déduction applicables / Consult a tax professional for applicable deduction limits</li>
        </ul>
    </div>

    <!-- Signature Section (Optional for electronic documents) -->
    <div class="rl24-signature">
        <div class="rl24-signature-line">
            <div class="rl24-signature-box" style="flex: 2;">
                <div class="line"></div>
                <div class="label">Signature autorisée / Authorized Signature</div>
            </div>
            <div class="rl24-signature-box">
                <div class="line"></div>
                <div class="label">Date</div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="rl24-footer">
        <div class="rl24-footer-row">
            <div>
                <strong>Date d'émission / Issue Date:</strong> <?php echo htmlspecialchars($emissionDateFormatted); ?>
            </div>
            <div>
                <strong>Document généré électroniquement</strong><br>
                <em>Electronically generated document</em>
            </div>
        </div>
        <?php if (!empty($documentId)): ?>
        <div class="rl24-document-id">
            Document ID: <?php echo htmlspecialchars($documentId); ?>
        </div>
        <?php endif; ?>
        <div style="margin-top: 10px; text-align: center; font-size: 8pt; color: #999;">
            Ce relevé est émis par <?php echo htmlspecialchars($schoolData['name'] ?? 'l\'établissement'); ?> et peut être vérifié auprès de l'institution.
        </div>
    </div>
</body>
</html>
