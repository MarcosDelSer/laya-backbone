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
use Gibbon\Module\CareTracking\Domain\IncidentGateway;

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/careTracking_incidents_pdf.php')) {
    // Access denied - redirect or show error
    header('HTTP/1.1 403 Forbidden');
    exit;
}

// Get incident ID from request
$gibbonCareIncidentID = $_GET['gibbonCareIncidentID'] ?? null;

if (empty($gibbonCareIncidentID)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'No incident specified.';
    exit;
}

// Get gateway via DI container
$incidentGateway = $container->get(IncidentGateway::class);

// Get the incident details
$incident = $incidentGateway->getByID($gibbonCareIncidentID);

if (empty($incident)) {
    header('HTTP/1.1 404 Not Found');
    echo 'The specified incident cannot be found.';
    exit;
}

// Get child details
$childData = ['gibbonPersonID' => $incident['gibbonPersonID']];
$childSql = "SELECT gibbonPersonID, preferredName, surname, image_240, dob, gender
             FROM gibbonPerson
             WHERE gibbonPersonID = :gibbonPersonID";
$child = $pdo->selectOne($childSql, $childData);

// Get recorded by staff details
$recordedBy = null;
if (!empty($incident['recordedByID'])) {
    $staffData = ['gibbonPersonID' => $incident['recordedByID']];
    $staffSql = "SELECT gibbonPersonID, preferredName, surname
                 FROM gibbonPerson
                 WHERE gibbonPersonID = :gibbonPersonID";
    $recordedBy = $pdo->selectOne($staffSql, $staffData);
}

// Get organization name from settings
$organizationName = $session->get('organisationName') ?? 'LAYA Childcare';
$organizationLogo = $session->get('organisationLogo') ?? '';

// Child name formatting
$childName = Format::name('', $child['preferredName'] ?? '', $child['surname'] ?? '', 'Student', false, true);

// Staff name formatting
$recordedByName = $recordedBy ? Format::name('', $recordedBy['preferredName'], $recordedBy['surname'], 'Staff', false, true) : __('Unknown');

// Calculate child's age at incident time
$childAge = '';
if (!empty($child['dob'])) {
    $incidentDate = date_create($incident['date']);
    $birthDate = date_create($child['dob']);
    $age = date_diff($birthDate, $incidentDate);
    $childAge = $age->y . ' ' . __('years') . ', ' . $age->m . ' ' . __('months');
}

// Severity and type color mapping for print
$severityLabels = [
    'Critical' => __('Critical'),
    'High'     => __('High'),
    'Medium'   => __('Medium'),
    'Low'      => __('Low'),
];

$typeLabels = [
    'Minor Injury' => __('Minor Injury'),
    'Major Injury' => __('Major Injury'),
    'Illness'      => __('Illness'),
    'Behavioral'   => __('Behavioral'),
    'Other'        => __('Other'),
];

// Try to use TCPDF if available
$useTCPDF = false;
if (class_exists('TCPDF')) {
    $useTCPDF = true;
}

// Generate filename
$filename = 'incident_report_' . $gibbonCareIncidentID . '_' . date('Y-m-d', strtotime($incident['date'])) . '.pdf';

if ($useTCPDF) {
    // TCPDF PDF generation
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

    // Set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($organizationName);
    $pdf->SetTitle(__('Incident Report') . ' - ' . $childName);
    $pdf->SetSubject(__('Incident Report'));

    // Set default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(true);

    // Set margins
    $pdf->SetMargins(15, 15, 15);

    // Add a page
    $pdf->AddPage();

    // Generate HTML content for PDF
    $html = generateIncidentReportHTML($incident, $child, $childName, $childAge, $recordedByName, $organizationName, $organizationLogo, $session, true);

    // Output the HTML content
    $pdf->writeHTML($html, true, false, true, false, '');

    // Output PDF
    $pdf->Output($filename, 'D');
    exit;
} else {
    // Fall back to print-friendly HTML that can be saved as PDF via browser print dialog
    header('Content-Type: text/html; charset=utf-8');

    // Generate print-friendly HTML
    $html = generateIncidentReportHTML($incident, $child, $childName, $childAge, $recordedByName, $organizationName, $organizationLogo, $session, false);
    echo $html;
    exit;
}

/**
 * Generate incident report HTML content
 *
 * @param array $incident Incident data
 * @param array $child Child data
 * @param string $childName Formatted child name
 * @param string $childAge Calculated age at incident
 * @param string $recordedByName Staff who recorded the incident
 * @param string $organizationName Organization name
 * @param string $organizationLogo Organization logo path
 * @param object $session Session object for URLs
 * @param bool $forPDF Whether generating for TCPDF (simplified HTML) or browser
 * @return string HTML content
 */
function generateIncidentReportHTML($incident, $child, $childName, $childAge, $recordedByName, $organizationName, $organizationLogo, $session, $forPDF = false) {
    $dateFormatted = Format::date($incident['date']);
    $timeFormatted = Format::time($incident['time']);
    $timestampCreated = Format::dateTime($incident['timestampCreated']);

    // Build the HTML
    if ($forPDF) {
        // Simplified HTML for TCPDF
        $html = '<style>
            body { font-family: Arial, sans-serif; font-size: 11pt; }
            h1 { font-size: 18pt; color: #333; border-bottom: 2px solid #333; padding-bottom: 5px; }
            h2 { font-size: 14pt; color: #555; margin-top: 15px; }
            .header { text-align: center; margin-bottom: 20px; }
            .section { margin-bottom: 15px; }
            .label { font-weight: bold; color: #555; }
            .value { color: #000; }
            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
            td { padding: 5px; border: 1px solid #ddd; }
            .severity-critical { background-color: #fee2e2; color: #991b1b; font-weight: bold; }
            .severity-high { background-color: #ffedd5; color: #9a3412; font-weight: bold; }
            .severity-medium { background-color: #fef9c3; color: #854d0e; }
            .severity-low { background-color: #dcfce7; color: #166534; }
            .description-box { background-color: #f5f5f5; padding: 10px; border-radius: 5px; margin-top: 5px; }
            .footer { margin-top: 30px; padding-top: 10px; border-top: 1px solid #ddd; font-size: 9pt; color: #666; }
        </style>';
    } else {
        // Full HTML for browser with print styles
        $html = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . __('Incident Report') . ' - ' . htmlspecialchars($childName) . '</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .report-container {
            background: white;
            padding: 40px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #2563eb;
        }
        .header h1 {
            margin: 0;
            font-size: 24pt;
            color: #1e40af;
        }
        .header .organization {
            font-size: 14pt;
            color: #666;
            margin-top: 5px;
        }
        .header .report-id {
            font-size: 10pt;
            color: #999;
            margin-top: 10px;
        }
        h2 {
            font-size: 14pt;
            color: #1e40af;
            margin-top: 25px;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }
        .section { margin-bottom: 20px; }
        .grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; }
        .field { margin-bottom: 10px; }
        .label {
            font-weight: 600;
            color: #6b7280;
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 3px;
        }
        .value {
            color: #111827;
            font-size: 12pt;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 10pt;
        }
        .severity-critical { background-color: #fee2e2; color: #991b1b; }
        .severity-high { background-color: #ffedd5; color: #9a3412; }
        .severity-medium { background-color: #fef9c3; color: #854d0e; }
        .severity-low { background-color: #dcfce7; color: #166534; }
        .type-minor-injury { background-color: #fef9c3; color: #854d0e; }
        .type-major-injury { background-color: #fee2e2; color: #991b1b; }
        .type-illness { background-color: #dbeafe; color: #1e40af; }
        .type-behavioral { background-color: #ffedd5; color: #9a3412; }
        .type-other { background-color: #e5e7eb; color: #374151; }
        .description-box {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-top: 5px;
            border: 1px solid #e5e7eb;
        }
        .action-box {
            background-color: #ecfdf5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 5px;
            border: 1px solid #a7f3d0;
        }
        .notification-status {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-top: 10px;
        }
        .notification-item {
            padding: 10px;
            border-radius: 8px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
        }
        .notification-item.notified {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }
        .checkmark { color: #059669; }
        .pending { color: #9ca3af; }
        .signature-section {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e5e7eb;
        }
        .signature-line {
            margin-top: 40px;
            display: flex;
            gap: 40px;
        }
        .signature-block {
            flex: 1;
        }
        .signature-block .line {
            border-bottom: 1px solid #333;
            height: 40px;
            margin-bottom: 5px;
        }
        .signature-block .label {
            text-align: center;
        }
        .footer {
            margin-top: 40px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 9pt;
            color: #9ca3af;
            text-align: center;
        }
        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #2563eb;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        .print-button:hover {
            background: #1d4ed8;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .report-container {
                box-shadow: none;
                padding: 20px;
            }
            .print-button { display: none; }
            @page {
                margin: 15mm;
            }
        }
    </style>
</head>
<body>
<button class="print-button" onclick="window.print()">' . __('Print / Save as PDF') . '</button>
<div class="report-container">';
    }

    // Header
    $html .= '<div class="header">';
    $html .= '<h1>' . __('Incident Report') . '</h1>';
    $html .= '<div class="organization">' . htmlspecialchars($organizationName) . '</div>';
    $html .= '<div class="report-id">' . __('Report ID') . ': ' . $incident['gibbonCareIncidentID'] . ' | ' . __('Generated') . ': ' . Format::dateTime(date('Y-m-d H:i:s')) . '</div>';
    $html .= '</div>';

    // Child Information Section
    $html .= '<h2>' . __('Child Information') . '</h2>';
    $html .= '<div class="section">';
    if ($forPDF) {
        $html .= '<table>';
        $html .= '<tr><td class="label" width="30%">' . __('Name') . '</td><td class="value">' . htmlspecialchars($childName) . '</td></tr>';
        if (!empty($childAge)) {
            $html .= '<tr><td class="label">' . __('Age at Incident') . '</td><td class="value">' . htmlspecialchars($childAge) . '</td></tr>';
        }
        if (!empty($child['dob'])) {
            $html .= '<tr><td class="label">' . __('Date of Birth') . '</td><td class="value">' . Format::date($child['dob']) . '</td></tr>';
        }
        if (!empty($child['gender'])) {
            $html .= '<tr><td class="label">' . __('Gender') . '</td><td class="value">' . __($child['gender']) . '</td></tr>';
        }
        $html .= '</table>';
    } else {
        $html .= '<div class="grid">';
        $html .= '<div class="field"><div class="label">' . __('Name') . '</div><div class="value">' . htmlspecialchars($childName) . '</div></div>';
        if (!empty($childAge)) {
            $html .= '<div class="field"><div class="label">' . __('Age at Incident') . '</div><div class="value">' . htmlspecialchars($childAge) . '</div></div>';
        }
        if (!empty($child['dob'])) {
            $html .= '<div class="field"><div class="label">' . __('Date of Birth') . '</div><div class="value">' . Format::date($child['dob']) . '</div></div>';
        }
        if (!empty($child['gender'])) {
            $html .= '<div class="field"><div class="label">' . __('Gender') . '</div><div class="value">' . __($child['gender']) . '</div></div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    // Incident Details Section
    $html .= '<h2>' . __('Incident Details') . '</h2>';
    $html .= '<div class="section">';

    // Severity class
    $severityClass = 'severity-' . strtolower($incident['severity']);
    $typeClass = 'type-' . strtolower(str_replace(' ', '-', $incident['type']));

    if ($forPDF) {
        $html .= '<table>';
        $html .= '<tr><td class="label" width="30%">' . __('Date') . '</td><td class="value">' . $dateFormatted . '</td></tr>';
        $html .= '<tr><td class="label">' . __('Time') . '</td><td class="value">' . $timeFormatted . '</td></tr>';
        $html .= '<tr><td class="label">' . __('Type') . '</td><td class="value">' . __($incident['type']) . '</td></tr>';
        $html .= '<tr><td class="label">' . __('Severity') . '</td><td class="value ' . $severityClass . '">' . __($incident['severity']) . '</td></tr>';
        if (!empty($incident['incidentCategory'])) {
            $html .= '<tr><td class="label">' . __('Category') . '</td><td class="value">' . __($incident['incidentCategory']) . '</td></tr>';
        }
        if (!empty($incident['bodyPart'])) {
            $html .= '<tr><td class="label">' . __('Body Part Affected') . '</td><td class="value">' . __($incident['bodyPart']) . '</td></tr>';
        }
        $html .= '</table>';
    } else {
        $html .= '<div class="grid">';
        $html .= '<div class="field"><div class="label">' . __('Date') . '</div><div class="value">' . $dateFormatted . '</div></div>';
        $html .= '<div class="field"><div class="label">' . __('Time') . '</div><div class="value">' . $timeFormatted . '</div></div>';
        $html .= '<div class="field"><div class="label">' . __('Type') . '</div><div class="value"><span class="badge ' . $typeClass . '">' . __($incident['type']) . '</span></div></div>';
        $html .= '<div class="field"><div class="label">' . __('Severity') . '</div><div class="value"><span class="badge ' . $severityClass . '">' . __($incident['severity']) . '</span></div></div>';
        if (!empty($incident['incidentCategory'])) {
            $html .= '<div class="field"><div class="label">' . __('Category') . '</div><div class="value">' . __($incident['incidentCategory']) . '</div></div>';
        }
        if (!empty($incident['bodyPart'])) {
            $html .= '<div class="field"><div class="label">' . __('Body Part Affected') . '</div><div class="value">' . __($incident['bodyPart']) . '</div></div>';
        }
        $html .= '</div>';
    }
    $html .= '</div>';

    // Description
    $html .= '<h2>' . __('Description of Incident') . '</h2>';
    $html .= '<div class="section">';
    $html .= '<div class="description-box">' . nl2br(htmlspecialchars($incident['description'])) . '</div>';
    $html .= '</div>';

    // Action Taken
    if (!empty($incident['actionTaken'])) {
        $html .= '<h2>' . __('First Aid / Action Taken') . '</h2>';
        $html .= '<div class="section">';
        $html .= '<div class="action-box">' . nl2br(htmlspecialchars($incident['actionTaken'])) . '</div>';
        $html .= '</div>';
    }

    // Medical & Follow-up Section
    $html .= '<h2>' . __('Medical & Follow-up') . '</h2>';
    $html .= '<div class="section">';

    $medicalConsulted = ($incident['medicalConsulted'] ?? 'N') === 'Y' ? __('Yes') : __('No');
    $followUpRequired = ($incident['followUpRequired'] ?? 'N') === 'Y' ? __('Yes') : __('No');

    if ($forPDF) {
        $html .= '<table>';
        $html .= '<tr><td class="label" width="30%">' . __('Medical Consulted') . '</td><td class="value">' . $medicalConsulted . '</td></tr>';
        $html .= '<tr><td class="label">' . __('Follow-up Required') . '</td><td class="value">' . $followUpRequired . '</td></tr>';
        $html .= '</table>';
    } else {
        $html .= '<div class="grid">';
        $html .= '<div class="field"><div class="label">' . __('Medical Consulted') . '</div><div class="value">' . $medicalConsulted . '</div></div>';
        $html .= '<div class="field"><div class="label">' . __('Follow-up Required') . '</div><div class="value">' . $followUpRequired . '</div></div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    // Notification Status Section
    $html .= '<h2>' . __('Notification Status') . '</h2>';
    $html .= '<div class="section">';

    $parentNotified = ($incident['parentNotified'] ?? 'N') === 'Y';
    $parentAcknowledged = ($incident['parentAcknowledged'] ?? 'N') === 'Y';
    $directorNotified = ($incident['directorNotified'] ?? 'N') === 'Y';

    $parentNotifiedStatus = $parentNotified ? __('Yes') : __('No');
    $parentAcknowledgedStatus = $parentAcknowledged ? __('Yes') : __('Pending');
    $directorNotifiedStatus = $directorNotified ? __('Yes') : __('No');

    if ($forPDF) {
        $html .= '<table>';
        $html .= '<tr><td class="label" width="30%">' . __('Parent Notified') . '</td><td class="value">' . $parentNotifiedStatus;
        if ($parentNotified && !empty($incident['parentNotifiedTime'])) {
            $html .= ' (' . Format::dateTime($incident['parentNotifiedTime']) . ')';
        }
        $html .= '</td></tr>';
        $html .= '<tr><td class="label">' . __('Parent Acknowledged') . '</td><td class="value">' . $parentAcknowledgedStatus;
        if ($parentAcknowledged && !empty($incident['parentAcknowledgedTime'])) {
            $html .= ' (' . Format::dateTime($incident['parentAcknowledgedTime']) . ')';
        }
        $html .= '</td></tr>';
        $html .= '<tr><td class="label">' . __('Director Notified') . '</td><td class="value">' . $directorNotifiedStatus;
        if ($directorNotified && !empty($incident['directorNotifiedTime'])) {
            $html .= ' (' . Format::dateTime($incident['directorNotifiedTime']) . ')';
        }
        $html .= '</td></tr>';
        $html .= '</table>';
    } else {
        $html .= '<div class="notification-status">';
        $html .= '<div class="notification-item' . ($parentNotified ? ' notified' : '') . '">';
        $html .= '<div class="label">' . __('Parent Notified') . '</div>';
        $html .= '<div class="value">' . ($parentNotified ? '<span class="checkmark">&#10003;</span> ' : '<span class="pending">&#10007;</span> ') . $parentNotifiedStatus;
        if ($parentNotified && !empty($incident['parentNotifiedTime'])) {
            $html .= '<br><small>' . Format::dateTime($incident['parentNotifiedTime']) . '</small>';
        }
        $html .= '</div></div>';

        $html .= '<div class="notification-item' . ($parentAcknowledged ? ' notified' : '') . '">';
        $html .= '<div class="label">' . __('Parent Acknowledged') . '</div>';
        $html .= '<div class="value">' . ($parentAcknowledged ? '<span class="checkmark">&#10003;</span> ' : '<span class="pending">&#8987;</span> ') . $parentAcknowledgedStatus;
        if ($parentAcknowledged && !empty($incident['parentAcknowledgedTime'])) {
            $html .= '<br><small>' . Format::dateTime($incident['parentAcknowledgedTime']) . '</small>';
        }
        $html .= '</div></div>';

        $html .= '<div class="notification-item' . ($directorNotified ? ' notified' : '') . '">';
        $html .= '<div class="label">' . __('Director Notified') . '</div>';
        $html .= '<div class="value">' . ($directorNotified ? '<span class="checkmark">&#10003;</span> ' : '-') . ' ' . $directorNotifiedStatus;
        if ($directorNotified && !empty($incident['directorNotifiedTime'])) {
            $html .= '<br><small>' . Format::dateTime($incident['directorNotifiedTime']) . '</small>';
        }
        $html .= '</div></div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    // Recording Information Section
    $html .= '<h2>' . __('Recording Information') . '</h2>';
    $html .= '<div class="section">';
    if ($forPDF) {
        $html .= '<table>';
        $html .= '<tr><td class="label" width="30%">' . __('Recorded By') . '</td><td class="value">' . htmlspecialchars($recordedByName) . '</td></tr>';
        $html .= '<tr><td class="label">' . __('Recorded At') . '</td><td class="value">' . $timestampCreated . '</td></tr>';
        $html .= '</table>';
    } else {
        $html .= '<div class="grid">';
        $html .= '<div class="field"><div class="label">' . __('Recorded By') . '</div><div class="value">' . htmlspecialchars($recordedByName) . '</div></div>';
        $html .= '<div class="field"><div class="label">' . __('Recorded At') . '</div><div class="value">' . $timestampCreated . '</div></div>';
        $html .= '</div>';
    }
    $html .= '</div>';

    // Signature Section (for print)
    if (!$forPDF) {
        $html .= '<div class="signature-section">';
        $html .= '<h2>' . __('Signatures') . '</h2>';
        $html .= '<div class="signature-line">';
        $html .= '<div class="signature-block">';
        $html .= '<div class="line"></div>';
        $html .= '<div class="label">' . __('Staff Signature') . '</div>';
        $html .= '</div>';
        $html .= '<div class="signature-block">';
        $html .= '<div class="line"></div>';
        $html .= '<div class="label">' . __('Date') . '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="signature-line">';
        $html .= '<div class="signature-block">';
        $html .= '<div class="line"></div>';
        $html .= '<div class="label">' . __('Parent/Guardian Signature') . '</div>';
        $html .= '</div>';
        $html .= '<div class="signature-block">';
        $html .= '<div class="line"></div>';
        $html .= '<div class="label">' . __('Date') . '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
    }

    // Footer
    $html .= '<div class="footer">';
    $html .= __('This incident report is confidential and should be handled in accordance with privacy policies.') . '<br>';
    $html .= htmlspecialchars($organizationName) . ' | ' . __('Generated on') . ' ' . Format::dateTime(date('Y-m-d H:i:s'));
    $html .= '</div>';

    if (!$forPDF) {
        $html .= '</div></body></html>';
    }

    return $html;
}
