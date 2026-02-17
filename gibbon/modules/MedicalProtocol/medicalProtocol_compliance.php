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

use Gibbon\Forms\Form;
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\MedicalProtocol\Domain\ProtocolGateway;
use Gibbon\Module\MedicalProtocol\Domain\AdministrationGateway;
use Gibbon\Module\MedicalProtocol\Domain\AuthorizationGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Medical Protocol'), 'medicalProtocol.php');
$page->breadcrumbs->add(__('Quebec Compliance'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/MedicalProtocol/medicalProtocol_compliance.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get filter parameters with defaults for monthly reporting
    $dateFrom = $_GET['dateFrom'] ?? date('Y-m-01');
    $dateTo = $_GET['dateTo'] ?? date('Y-m-d');
    $gibbonMedicalProtocolID = $_GET['gibbonMedicalProtocolID'] ?? '';
    $formCode = $_GET['formCode'] ?? '';

    // Validate date formats
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
        $dateFrom = date('Y-m-01');
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
        $dateTo = date('Y-m-d');
    }

    // Get gateways via DI container
    $protocolGateway = $container->get(ProtocolGateway::class);
    $administrationGateway = $container->get(AdministrationGateway::class);
    $authorizationGateway = $container->get(AuthorizationGateway::class);

    // Get active protocols for filter dropdown
    $protocols = $protocolGateway->selectActiveProtocols()->fetchAll();

    // Page header
    echo '<h2>' . __('Quebec Compliance Report') . '</h2>';
    echo '<p class="text-sm text-gray-600 mb-4">' . __('Generate compliance reports for Quebec-mandated medical protocols (FO-0647 Acetaminophen, FO-0646 Insect Repellent).') . '</p>';

    // Quick date range buttons
    echo '<div class="mb-4">';
    echo '<div class="flex flex-wrap gap-2">';

    // This month
    $thisMonthStart = date('Y-m-01');
    $thisMonthEnd = date('Y-m-d');
    $thisMonthClass = ($dateFrom === $thisMonthStart && $dateTo === $thisMonthEnd) ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_compliance.php&dateFrom=' . $thisMonthStart . '&dateTo=' . $thisMonthEnd . '&gibbonMedicalProtocolID=' . $gibbonMedicalProtocolID . '&formCode=' . $formCode . '" class="px-3 py-1 rounded text-sm ' . $thisMonthClass . '">' . __('This Month') . '</a>';

    // Last month
    $lastMonthStart = date('Y-m-01', strtotime('first day of last month'));
    $lastMonthEnd = date('Y-m-t', strtotime('last day of last month'));
    $lastMonthClass = ($dateFrom === $lastMonthStart && $dateTo === $lastMonthEnd) ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_compliance.php&dateFrom=' . $lastMonthStart . '&dateTo=' . $lastMonthEnd . '&gibbonMedicalProtocolID=' . $gibbonMedicalProtocolID . '&formCode=' . $formCode . '" class="px-3 py-1 rounded text-sm ' . $lastMonthClass . '">' . __('Last Month') . '</a>';

    // This quarter
    $quarterMonth = floor((date('n') - 1) / 3) * 3 + 1;
    $thisQuarterStart = date('Y-' . str_pad($quarterMonth, 2, '0', STR_PAD_LEFT) . '-01');
    $thisQuarterEnd = date('Y-m-d');
    $thisQuarterClass = ($dateFrom === $thisQuarterStart && $dateTo === $thisQuarterEnd) ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_compliance.php&dateFrom=' . $thisQuarterStart . '&dateTo=' . $thisQuarterEnd . '&gibbonMedicalProtocolID=' . $gibbonMedicalProtocolID . '&formCode=' . $formCode . '" class="px-3 py-1 rounded text-sm ' . $thisQuarterClass . '">' . __('This Quarter') . '</a>';

    // Year to date
    $yearStart = date('Y-01-01');
    $yearToDateClass = ($dateFrom === $yearStart && $dateTo === date('Y-m-d')) ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_compliance.php&dateFrom=' . $yearStart . '&dateTo=' . date('Y-m-d') . '&gibbonMedicalProtocolID=' . $gibbonMedicalProtocolID . '&formCode=' . $formCode . '" class="px-3 py-1 rounded text-sm ' . $yearToDateClass . '">' . __('Year to Date') . '</a>';

    echo '</div>';
    echo '</div>';

    // Filter form
    $form = Form::create('complianceFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/MedicalProtocol/medicalProtocol_compliance.php');

    $row = $form->addRow();
    $row->addLabel('dateFrom', __('Date From'));
    $row->addDate('dateFrom')->setValue(Format::date($dateFrom))->required();

    $row = $form->addRow();
    $row->addLabel('dateTo', __('Date To'));
    $row->addDate('dateTo')->setValue(Format::date($dateTo))->required();

    // Protocol filter
    $protocolOptions = ['' => __('All Protocols')];
    foreach ($protocols as $protocol) {
        $protocolOptions[$protocol['gibbonMedicalProtocolID']] = $protocol['name'] . ' (' . $protocol['formCode'] . ')';
    }

    $row = $form->addRow();
    $row->addLabel('gibbonMedicalProtocolID', __('Protocol'));
    $row->addSelect('gibbonMedicalProtocolID')->fromArray($protocolOptions)->selected($gibbonMedicalProtocolID);

    // Form code filter (for quick filtering by Quebec form)
    $formCodeOptions = [
        '' => __('All Form Codes'),
        'FO-0647' => 'FO-0647 (' . __('Acetaminophen') . ')',
        'FO-0646' => 'FO-0646 (' . __('Insect Repellent') . ')',
    ];

    $row = $form->addRow();
    $row->addLabel('formCode', __('Form Code'));
    $row->addSelect('formCode')->fromArray($formCodeOptions)->selected($formCode);

    $row = $form->addRow();
    $row->addSubmit(__('Generate Report'));

    echo $form->getOutput();

    // Display report period
    echo '<p class="text-lg mb-4">' . __('Report Period') . ': <strong>' . Format::date($dateFrom) . '</strong> ' . __('to') . ' <strong>' . Format::date($dateTo) . '</strong></p>';

    // Generate compliance report
    $protocolIDFilter = !empty($gibbonMedicalProtocolID) ? $gibbonMedicalProtocolID : null;

    // If filtering by form code, get the protocol ID
    if (!empty($formCode) && empty($gibbonMedicalProtocolID)) {
        $protocolByCode = $protocolGateway->getProtocolByFormCode($formCode);
        if ($protocolByCode) {
            $protocolIDFilter = $protocolByCode['gibbonMedicalProtocolID'];
        }
    }

    $complianceReport = $administrationGateway->generateComplianceReport(
        $gibbonSchoolYearID,
        $dateFrom,
        $dateTo,
        $protocolIDFilter
    );

    // Summary by Protocol Section
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Protocol Summary') . '</h3>';

    if (!empty($complianceReport['summaryByProtocol'])) {
        echo '<div class="overflow-x-auto">';
        echo '<table class="w-full text-sm">';
        echo '<thead class="bg-gray-100">';
        echo '<tr>';
        echo '<th class="px-4 py-2 text-left">' . __('Protocol') . '</th>';
        echo '<th class="px-4 py-2 text-left">' . __('Form Code') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Administrations') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Children') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Staff') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Avg Dose (mg)') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Follow-ups') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Notification Rate') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Acknowledgment Rate') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $totalAdministrations = 0;
        $totalChildren = 0;
        $totalFollowUpsRequired = 0;
        $totalFollowUpsCompleted = 0;
        $totalNotified = 0;
        $totalAcknowledged = 0;

        foreach ($complianceReport['summaryByProtocol'] as $protocol) {
            $totalAdministrations += $protocol['totalAdministrations'];
            $totalFollowUpsRequired += $protocol['followUpsRequired'];
            $totalFollowUpsCompleted += $protocol['followUpsCompleted'];
            $totalNotified += $protocol['parentsNotified'];
            $totalAcknowledged += $protocol['parentsAcknowledged'];

            $followUpRate = $protocol['followUpsRequired'] > 0
                ? round(($protocol['followUpsCompleted'] / $protocol['followUpsRequired']) * 100)
                : 100;

            $notificationRate = $protocol['totalAdministrations'] > 0
                ? round(($protocol['parentsNotified'] / $protocol['totalAdministrations']) * 100)
                : 0;

            $acknowledgmentRate = $protocol['totalAdministrations'] > 0
                ? round(($protocol['parentsAcknowledged'] / $protocol['totalAdministrations']) * 100)
                : 0;

            $notificationClass = $notificationRate >= 90 ? 'text-green-600' : ($notificationRate >= 70 ? 'text-yellow-500' : 'text-red-500');
            $acknowledgmentClass = $acknowledgmentRate >= 90 ? 'text-green-600' : ($acknowledgmentRate >= 70 ? 'text-yellow-500' : 'text-red-500');
            $followUpClass = $followUpRate >= 90 ? 'text-green-600' : ($followUpRate >= 70 ? 'text-yellow-500' : 'text-red-500');

            echo '<tr class="border-b">';
            echo '<td class="px-4 py-2 font-medium">' . htmlspecialchars($protocol['protocolName']) . '</td>';
            echo '<td class="px-4 py-2"><span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">' . htmlspecialchars($protocol['formCode']) . '</span></td>';
            echo '<td class="px-4 py-2 text-center">' . $protocol['totalAdministrations'] . '</td>';
            echo '<td class="px-4 py-2 text-center">' . $protocol['uniqueChildren'] . '</td>';
            echo '<td class="px-4 py-2 text-center">' . $protocol['uniqueStaff'] . '</td>';
            echo '<td class="px-4 py-2 text-center">' . ($protocol['avgDoseMg'] ? round($protocol['avgDoseMg'], 1) . ' mg' : '-') . '</td>';
            echo '<td class="px-4 py-2 text-center"><span class="' . $followUpClass . '">' . $protocol['followUpsCompleted'] . '/' . $protocol['followUpsRequired'] . '</span></td>';
            echo '<td class="px-4 py-2 text-center"><span class="' . $notificationClass . '">' . $notificationRate . '%</span></td>';
            echo '<td class="px-4 py-2 text-center"><span class="' . $acknowledgmentClass . '">' . $acknowledgmentRate . '%</span></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500 text-center py-4">' . __('No administration records found for the selected period.') . '</p>';
    }

    echo '</div>';

    // Compliance Metrics Overview
    echo '<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">';

    // Total Administrations
    echo '<div class="bg-blue-50 rounded-lg shadow p-4 text-center">';
    echo '<span class="block text-3xl font-bold text-blue-600">' . $totalAdministrations . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Total Administrations') . '</span>';
    echo '</div>';

    // Follow-up Compliance
    $overallFollowUpRate = $totalFollowUpsRequired > 0
        ? round(($totalFollowUpsCompleted / $totalFollowUpsRequired) * 100)
        : 100;
    $followUpColorClass = $overallFollowUpRate >= 90 ? 'green' : ($overallFollowUpRate >= 70 ? 'yellow' : 'red');
    echo '<div class="bg-' . $followUpColorClass . '-50 rounded-lg shadow p-4 text-center">';
    echo '<span class="block text-3xl font-bold text-' . $followUpColorClass . '-600">' . $overallFollowUpRate . '%</span>';
    echo '<span class="text-sm text-gray-600">' . __('Follow-up Completion') . '</span>';
    echo '</div>';

    // Notification Rate
    $overallNotificationRate = $totalAdministrations > 0
        ? round(($totalNotified / $totalAdministrations) * 100)
        : 0;
    $notificationColorClass = $overallNotificationRate >= 90 ? 'green' : ($overallNotificationRate >= 70 ? 'yellow' : 'red');
    echo '<div class="bg-' . $notificationColorClass . '-50 rounded-lg shadow p-4 text-center">';
    echo '<span class="block text-3xl font-bold text-' . $notificationColorClass . '-600">' . $overallNotificationRate . '%</span>';
    echo '<span class="text-sm text-gray-600">' . __('Parent Notification Rate') . '</span>';
    echo '</div>';

    // Compliance Issues
    $violationCount = $complianceReport['complianceIssues']['violationCount'] ?? 0;
    $violationColorClass = $violationCount == 0 ? 'green' : ($violationCount <= 3 ? 'yellow' : 'red');
    echo '<div class="bg-' . $violationColorClass . '-50 rounded-lg shadow p-4 text-center">';
    echo '<span class="block text-3xl font-bold text-' . $violationColorClass . '-600">' . $violationCount . '</span>';
    echo '<span class="text-sm text-gray-600">' . __('Interval Violations') . '</span>';
    echo '</div>';

    echo '</div>';

    // Compliance Issues Section (Interval Violations)
    if (!empty($complianceReport['complianceIssues']['intervalViolations'])) {
        echo '<div class="bg-red-50 border border-red-200 rounded-lg shadow p-4 mb-4">';
        echo '<h3 class="text-lg font-semibold mb-3 text-red-700">' . __('Compliance Issues - Interval Violations') . '</h3>';
        echo '<p class="text-sm text-red-600 mb-3">' . __('These administrations were given before the minimum required interval had elapsed.') . '</p>';

        echo '<div class="overflow-x-auto">';
        echo '<table class="w-full text-sm">';
        echo '<thead class="bg-red-100">';
        echo '<tr>';
        echo '<th class="px-4 py-2 text-left">' . __('Child') . '</th>';
        echo '<th class="px-4 py-2 text-left">' . __('Protocol') . '</th>';
        echo '<th class="px-4 py-2 text-left">' . __('Date/Time') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Required Interval') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Actual Interval') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Difference') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($complianceReport['complianceIssues']['intervalViolations'] as $violation) {
            $childName = Format::name('', $violation['preferredName'], $violation['surname'], 'Student', false, true);
            $requiredMinutes = $violation['intervalMinutes'];
            $actualMinutes = $violation['minutesSincePrevious'];
            $difference = $requiredMinutes - $actualMinutes;

            echo '<tr class="border-b">';
            echo '<td class="px-4 py-2">' . htmlspecialchars($childName) . '</td>';
            echo '<td class="px-4 py-2">';
            echo '<span class="font-medium">' . htmlspecialchars($violation['protocolName']) . '</span>';
            echo '<br><span class="text-xs text-gray-500">' . htmlspecialchars($violation['formCode']) . '</span>';
            echo '</td>';
            echo '<td class="px-4 py-2">' . Format::date($violation['date']) . ' ' . Format::time($violation['time']) . '</td>';
            echo '<td class="px-4 py-2 text-center">' . $requiredMinutes . ' ' . __('min') . '</td>';
            echo '<td class="px-4 py-2 text-center text-red-600 font-medium">' . $actualMinutes . ' ' . __('min') . '</td>';
            echo '<td class="px-4 py-2 text-center text-red-600">-' . $difference . ' ' . __('min') . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        echo '</div>';
    } else {
        echo '<div class="bg-green-50 border border-green-200 rounded-lg shadow p-4 mb-4">';
        echo '<h3 class="text-lg font-semibold mb-2 text-green-700">' . __('No Interval Violations') . '</h3>';
        echo '<p class="text-sm text-green-600">' . __('All administrations during this period respected the minimum required intervals between doses.') . '</p>';
        echo '</div>';
    }

    // Daily Breakdown Section
    if (!empty($complianceReport['dailyBreakdown'])) {
        echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
        echo '<h3 class="text-lg font-semibold mb-3">' . __('Daily Breakdown') . '</h3>';

        // Organize data by date
        $dailyData = [];
        foreach ($complianceReport['dailyBreakdown'] as $day) {
            $date = $day['date'];
            if (!isset($dailyData[$date])) {
                $dailyData[$date] = [
                    'date' => $date,
                    'FO-0647' => 0,
                    'FO-0646' => 0,
                    'total' => 0,
                    'children' => 0,
                ];
            }
            $dailyData[$date][$day['formCode']] = $day['administrations'];
            $dailyData[$date]['total'] += $day['administrations'];
            $dailyData[$date]['children'] = max($dailyData[$date]['children'], $day['children']);
        }

        // Sort by date descending
        krsort($dailyData);

        echo '<div class="overflow-x-auto">';
        echo '<table class="w-full text-sm">';
        echo '<thead class="bg-gray-100">';
        echo '<tr>';
        echo '<th class="px-4 py-2 text-left">' . __('Date') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Total') . '</th>';
        echo '<th class="px-4 py-2 text-center"><span class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded">FO-0647</span></th>';
        echo '<th class="px-4 py-2 text-center"><span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">FO-0646</span></th>';
        echo '<th class="px-4 py-2 text-center">' . __('Children') . '</th>';
        echo '<th class="px-4 py-2 text-right">' . __('Actions') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        $dayCount = 0;
        $maxDaysToShow = 30;

        foreach ($dailyData as $date => $day) {
            if ($dayCount >= $maxDaysToShow) {
                break;
            }

            echo '<tr class="border-b hover:bg-gray-50">';
            echo '<td class="px-4 py-2">' . Format::date($day['date']) . '</td>';
            echo '<td class="px-4 py-2 text-center font-medium">' . $day['total'] . '</td>';
            echo '<td class="px-4 py-2 text-center text-blue-600">' . $day['FO-0647'] . '</td>';
            echo '<td class="px-4 py-2 text-center text-green-600">' . $day['FO-0646'] . '</td>';
            echo '<td class="px-4 py-2 text-center">' . $day['children'] . '</td>';
            echo '<td class="px-4 py-2 text-right">';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_log.php&viewMode=single&date=' . $day['date'] . '" class="text-blue-600 hover:underline text-xs">' . __('View Log') . '</a>';
            echo '</td>';
            echo '</tr>';

            $dayCount++;
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        if (count($dailyData) > $maxDaysToShow) {
            echo '<p class="text-sm text-gray-500 mt-2">' . __('Showing most recent %1$s days. Use the Administration Log for complete history.', $maxDaysToShow) . '</p>';
        }

        echo '</div>';
    }

    // Authorization Compliance Section - Expiring Authorizations
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Authorization Status') . '</h3>';

    $expiringAuthorizations = $authorizationGateway->selectExpiringAuthorizations($gibbonSchoolYearID, 14);

    if ($expiringAuthorizations->rowCount() > 0) {
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded p-3 mb-3">';
        echo '<p class="text-sm text-yellow-700 font-medium">' . __('Authorizations requiring attention:') . '</p>';
        echo '</div>';

        echo '<div class="overflow-x-auto">';
        echo '<table class="w-full text-sm">';
        echo '<thead class="bg-gray-100">';
        echo '<tr>';
        echo '<th class="px-4 py-2 text-left">' . __('Child') . '</th>';
        echo '<th class="px-4 py-2 text-left">' . __('Protocol') . '</th>';
        echo '<th class="px-4 py-2 text-left">' . __('Issue') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Expiry Date') . '</th>';
        echo '<th class="px-4 py-2 text-center">' . __('Days Remaining') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($expiringAuthorizations as $auth) {
            $childName = Format::name('', $auth['preferredName'], $auth['surname'], 'Student', false, true);
            $expiryDate = $auth['weightExpiryDate'] ?? $auth['expiryDate'] ?? '';
            $daysRemaining = '';
            $urgencyClass = '';

            if (!empty($expiryDate)) {
                $expiryDateTime = new DateTime($expiryDate);
                $now = new DateTime();
                $interval = $now->diff($expiryDateTime);
                $daysRemaining = $interval->invert ? -$interval->days : $interval->days;

                if ($daysRemaining <= 0) {
                    $urgencyClass = 'text-red-600 font-bold';
                    $daysRemaining = __('Expired');
                } elseif ($daysRemaining <= 7) {
                    $urgencyClass = 'text-orange-600 font-medium';
                    $daysRemaining = $daysRemaining . ' ' . __('days');
                } else {
                    $urgencyClass = 'text-yellow-600';
                    $daysRemaining = $daysRemaining . ' ' . __('days');
                }
            }

            $issue = '';
            if (!empty($auth['weightExpiryDate']) && strtotime($auth['weightExpiryDate']) <= strtotime('+14 days')) {
                $issue = __('Weight revalidation required');
            } elseif (!empty($auth['expiryDate']) && strtotime($auth['expiryDate']) <= strtotime('+14 days')) {
                $issue = __('Authorization expiring');
            }

            echo '<tr class="border-b">';
            echo '<td class="px-4 py-2">' . htmlspecialchars($childName) . '</td>';
            echo '<td class="px-4 py-2">' . htmlspecialchars($auth['protocolName'] ?? '') . '</td>';
            echo '<td class="px-4 py-2">' . $issue . '</td>';
            echo '<td class="px-4 py-2 text-center">' . Format::date($expiryDate) . '</td>';
            echo '<td class="px-4 py-2 text-center ' . $urgencyClass . '">' . $daysRemaining . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<div class="bg-green-50 border border-green-200 rounded p-3">';
        echo '<p class="text-sm text-green-700">' . __('All active authorizations are current and valid.') . '</p>';
        echo '</div>';
    }

    echo '</div>';

    // Export Section
    echo '<div class="bg-gray-50 rounded-lg p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Export Report') . '</h3>';
    echo '<p class="text-sm text-gray-600 mb-3">' . __('Download this compliance report for Quebec regulatory documentation (FO-0647, FO-0646).') . '</p>';

    echo '<div class="flex flex-wrap gap-2">';
    $exportUrl = $session->get('absoluteURL') . '/modules/MedicalProtocol/medicalProtocol_compliance_export.php';
    $exportParams = http_build_query([
        'dateFrom' => $dateFrom,
        'dateTo' => $dateTo,
        'gibbonMedicalProtocolID' => $gibbonMedicalProtocolID,
        'formCode' => $formCode,
    ]);

    echo '<a href="' . $exportUrl . '?format=csv&' . $exportParams . '" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 text-sm">';
    echo '<span class="mr-2">📥</span>' . __('Export CSV');
    echo '</a>';

    echo '<a href="' . $exportUrl . '?format=pdf&' . $exportParams . '" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 text-sm">';
    echo '<span class="mr-2">📄</span>' . __('Export PDF');
    echo '</a>';

    echo '</div>';
    echo '</div>';

    // Report metadata
    echo '<div class="text-xs text-gray-400 mt-4">';
    echo __('Report generated') . ': ' . Format::dateTime($complianceReport['generatedAt']);
    echo '</div>';

    // Quick navigation links
    echo '<div class="mt-4 flex flex-wrap gap-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_log.php&viewMode=range&dateFrom=' . $dateFrom . '&dateTo=' . $dateTo . '" class="text-blue-600 hover:underline">' . __('View Administration Log') . ' &rarr;</a>';
    echo '</div>';
}
