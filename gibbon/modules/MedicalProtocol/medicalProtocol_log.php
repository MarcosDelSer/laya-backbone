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

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Medical Protocol'), 'medicalProtocol.php');
$page->breadcrumbs->add(__('Administration Log'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/MedicalProtocol/medicalProtocol_log.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get filter parameters
    $date = $_GET['date'] ?? date('Y-m-d');
    $dateFrom = $_GET['dateFrom'] ?? '';
    $dateTo = $_GET['dateTo'] ?? '';
    $gibbonMedicalProtocolID = $_GET['gibbonMedicalProtocolID'] ?? '';
    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';
    $formCode = $_GET['formCode'] ?? '';
    $viewMode = $_GET['viewMode'] ?? 'single'; // 'single' or 'range'

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    // Get gateways via DI container
    $protocolGateway = $container->get(ProtocolGateway::class);
    $administrationGateway = $container->get(AdministrationGateway::class);

    // Get active protocols for filter dropdown
    $protocols = $protocolGateway->selectActiveProtocols()->fetchAll();

    // Page header
    echo '<h2>' . __('Administration Log') . '</h2>';

    // View mode tabs
    echo '<div class="mb-4">';
    echo '<div class="flex gap-2">';
    $singleModeClass = $viewMode === 'single' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700';
    $rangeModeClass = $viewMode === 'range' ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-700';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_log.php&viewMode=single&date=' . $date . '" class="px-4 py-2 rounded ' . $singleModeClass . '">' . __('Single Day') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_log.php&viewMode=range&dateFrom=' . ($dateFrom ?: date('Y-m-01')) . '&dateTo=' . ($dateTo ?: date('Y-m-d')) . '" class="px-4 py-2 rounded ' . $rangeModeClass . '">' . __('Date Range') . '</a>';
    echo '</div>';
    echo '</div>';

    // Filter form
    $form = Form::create('logFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/MedicalProtocol/medicalProtocol_log.php');
    $form->addHiddenValue('viewMode', $viewMode);

    if ($viewMode === 'single') {
        $row = $form->addRow();
        $row->addLabel('date', __('Date'));
        $row->addDate('date')->setValue(Format::date($date))->required();
    } else {
        $row = $form->addRow();
        $row->addLabel('dateFrom', __('Date From'));
        $row->addDate('dateFrom')->setValue($dateFrom ? Format::date($dateFrom) : Format::date(date('Y-m-01')))->required();

        $row = $form->addRow();
        $row->addLabel('dateTo', __('Date To'));
        $row->addDate('dateTo')->setValue($dateTo ? Format::date($dateTo) : Format::date(date('Y-m-d')))->required();
    }

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
    $row->addSubmit(__('Filter'));

    echo $form->getOutput();

    // Build query criteria with filters
    $criteria = $administrationGateway->newQueryCriteria()
        ->sortBy(['date', 'time'], 'DESC')
        ->fromPOST();

    // Apply filters based on view mode
    if ($viewMode === 'single') {
        $criteria->filterBy('date', $date);
        echo '<p class="text-lg mb-4">' . __('Showing administrations for') . ': <strong>' . Format::date($date) . '</strong></p>';
    } else {
        $dateFromValue = $dateFrom ?: date('Y-m-01');
        $dateToValue = $dateTo ?: date('Y-m-d');
        $criteria->filterBy('dateFrom', $dateFromValue);
        $criteria->filterBy('dateTo', $dateToValue);
        echo '<p class="text-lg mb-4">' . __('Showing administrations from') . ' <strong>' . Format::date($dateFromValue) . '</strong> ' . __('to') . ' <strong>' . Format::date($dateToValue) . '</strong></p>';
    }

    // Apply optional filters
    if (!empty($gibbonMedicalProtocolID)) {
        $criteria->filterBy('protocol', $gibbonMedicalProtocolID);
    }
    if (!empty($formCode)) {
        $criteria->filterBy('formCode', $formCode);
    }
    if (!empty($gibbonPersonID)) {
        $criteria->filterBy('child', $gibbonPersonID);
    }

    // Get summary statistics
    if ($viewMode === 'single') {
        $summary = $administrationGateway->getAdministrationSummaryByDate($gibbonSchoolYearID, $date);
    } else {
        // For date range, calculate aggregate summary
        $dateFromValue = $dateFrom ?: date('Y-m-01');
        $dateToValue = $dateTo ?: date('Y-m-d');

        // Use the queryAdministrations to get counts for date range
        $rangeCriteria = $administrationGateway->newQueryCriteria()
            ->filterBy('dateFrom', $dateFromValue)
            ->filterBy('dateTo', $dateToValue);

        if (!empty($gibbonMedicalProtocolID)) {
            $rangeCriteria->filterBy('protocol', $gibbonMedicalProtocolID);
        }
        if (!empty($formCode)) {
            $rangeCriteria->filterBy('formCode', $formCode);
        }

        $rangeData = $administrationGateway->queryAdministrations($rangeCriteria, $gibbonSchoolYearID);

        // Calculate summary from range data
        $summary = [
            'totalAdministrations' => $rangeData->count(),
            'childrenCount' => count(array_unique(array_column($rangeData->toArray(), 'gibbonPersonID'))),
            'acetaminophenCount' => 0,
            'insectRepellentCount' => 0,
            'followUpsPending' => 0,
            'followUpsCompleted' => 0,
            'parentsNotified' => 0,
            'parentsAcknowledged' => 0,
        ];

        foreach ($rangeData as $row) {
            if ($row['formCode'] === 'FO-0647') {
                $summary['acetaminophenCount']++;
            } elseif ($row['formCode'] === 'FO-0646') {
                $summary['insectRepellentCount']++;
            }
            if ($row['followUpCompleted'] === 'Y') {
                $summary['followUpsCompleted']++;
            } elseif (!empty($row['followUpTime'])) {
                $summary['followUpsPending']++;
            }
            if ($row['parentNotified'] === 'Y') {
                $summary['parentsNotified']++;
            }
            if ($row['parentAcknowledged'] === 'Y') {
                $summary['parentsAcknowledged']++;
            }
        }
    }

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-center">';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($summary['totalAdministrations'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total') . '</span>';
    echo '</div>';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($summary['childrenCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Children') . '</span>';
    echo '</div>';

    echo '<div class="bg-blue-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-blue-600">' . ($summary['acetaminophenCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Acetaminophen') . '</span>';
    echo '</div>';

    echo '<div class="bg-green-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-green-600">' . ($summary['insectRepellentCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Insect Repellent') . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-yellow-600">' . ($summary['followUpsPending'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Follow-ups Pending') . '</span>';
    echo '</div>';

    echo '<div class="bg-purple-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-purple-600">' . ($summary['parentsNotified'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Parents Notified') . '</span>';
    echo '</div>';

    echo '</div>';

    // Parent notification and follow-up completion rates
    $total = $summary['totalAdministrations'] ?? 0;
    if ($total > 0) {
        $notifiedRate = round(($summary['parentsNotified'] / $total) * 100);
        $acknowledgedRate = round(($summary['parentsAcknowledged'] / $total) * 100);
        $followUpsRequired = ($summary['followUpsPending'] ?? 0) + ($summary['followUpsCompleted'] ?? 0);
        $followUpCompletionRate = $followUpsRequired > 0 ? round(($summary['followUpsCompleted'] / $followUpsRequired) * 100) : 100;

        echo '<div class="mt-3 pt-3 border-t grid grid-cols-3 gap-4 text-center">';
        echo '<div>';
        echo '<span class="text-sm text-gray-600">' . __('Notification Rate') . ': </span>';
        $notifiedClass = $notifiedRate >= 90 ? 'text-green-600' : ($notifiedRate >= 70 ? 'text-yellow-500' : 'text-red-500');
        echo '<span class="font-semibold ' . $notifiedClass . '">' . $notifiedRate . '%</span>';
        echo '</div>';
        echo '<div>';
        echo '<span class="text-sm text-gray-600">' . __('Acknowledgment Rate') . ': </span>';
        $acknowledgedClass = $acknowledgedRate >= 90 ? 'text-green-600' : ($acknowledgedRate >= 70 ? 'text-yellow-500' : 'text-red-500');
        echo '<span class="font-semibold ' . $acknowledgedClass . '">' . $acknowledgedRate . '%</span>';
        echo '</div>';
        echo '<div>';
        echo '<span class="text-sm text-gray-600">' . __('Follow-up Completion') . ': </span>';
        $followUpClass = $followUpCompletionRate >= 90 ? 'text-green-600' : ($followUpCompletionRate >= 70 ? 'text-yellow-500' : 'text-red-500');
        echo '<span class="font-semibold ' . $followUpClass . '">' . $followUpCompletionRate . '%</span>';
        echo '</div>';
        echo '</div>';
    }

    echo '</div>';

    // Get administration data
    $administrations = $administrationGateway->queryAdministrations($criteria, $gibbonSchoolYearID);

    // Build DataTable
    $table = DataTable::createPaginated('administrationLog', $criteria);
    $table->setTitle(__('Administration Records'));

    // Add export action
    $table->addHeaderAction('export', __('Export'))
        ->setIcon('download')
        ->directLink()
        ->setURL($session->get('absoluteURL') . '/modules/MedicalProtocol/medicalProtocol_log_export.php')
        ->addParam('viewMode', $viewMode)
        ->addParam('date', $date)
        ->addParam('dateFrom', $dateFrom)
        ->addParam('dateTo', $dateTo)
        ->addParam('gibbonMedicalProtocolID', $gibbonMedicalProtocolID)
        ->addParam('formCode', $formCode)
        ->displayLabel();

    // Add columns
    $table->addColumn('image', __('Photo'))
        ->notSortable()
        ->format(function ($row) use ($session) {
            $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
        });

    $table->addColumn('name', __('Child'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
        });

    // Show date column for range view
    if ($viewMode === 'range') {
        $table->addColumn('date', __('Date'))
            ->sortable()
            ->format(function ($row) {
                return Format::date($row['date']);
            });
    }

    $table->addColumn('time', __('Time'))
        ->sortable()
        ->format(function ($row) {
            return Format::time($row['time']);
        });

    $table->addColumn('protocolName', __('Protocol'))
        ->sortable()
        ->format(function ($row) {
            $formCode = $row['formCode'] ?? '';
            $type = $row['protocolType'] ?? '';
            $typeIcon = $type === 'Medication' ? '💊' : '🧴';
            return $typeIcon . ' ' . htmlspecialchars($row['protocolName']) . '<br><span class="text-xs text-gray-500">' . htmlspecialchars($formCode) . '</span>';
        });

    $table->addColumn('doseGiven', __('Dose'))
        ->format(function ($row) {
            $dose = htmlspecialchars($row['doseGiven']);
            if (!empty($row['doseMg'])) {
                $dose .= '<br><span class="text-xs text-gray-500">' . $row['doseMg'] . ' mg</span>';
            }
            if (!empty($row['concentration'])) {
                $dose .= '<br><span class="text-xs text-gray-400">' . htmlspecialchars($row['concentration']) . '</span>';
            }
            return $dose;
        });

    $table->addColumn('weightAtTimeKg', __('Weight'))
        ->format(function ($row) {
            return $row['weightAtTimeKg'] . ' kg';
        });

    $table->addColumn('temperatureC', __('Temp'))
        ->format(function ($row) {
            if (!empty($row['temperatureC'])) {
                return $row['temperatureC'] . '°C<br><span class="text-xs text-gray-500">' . ($row['temperatureMethod'] ?? '') . '</span>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    $table->addColumn('reason', __('Reason'))
        ->notSortable()
        ->format(function ($row) {
            if (!empty($row['reason'])) {
                return '<span class="text-sm text-gray-600" title="' . htmlspecialchars($row['reason']) . '">' .
                       htmlspecialchars(substr($row['reason'], 0, 30)) .
                       (strlen($row['reason']) > 30 ? '...' : '') . '</span>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    $table->addColumn('followUpTime', __('Follow-up'))
        ->notSortable()
        ->format(function ($row) {
            if (!empty($row['followUpTime'])) {
                if ($row['followUpCompleted'] === 'Y') {
                    return '<span class="text-green-600" title="' . __('Completed') . '">✓ ' . Format::time($row['followUpTime']) . '</span>';
                }
                // Check if overdue (only relevant for single day view with current date)
                $currentTime = date('H:i:s');
                $isOverdue = $row['date'] == date('Y-m-d') && $row['followUpTime'] < $currentTime;
                $class = $isOverdue ? 'text-red-600 font-bold' : 'text-yellow-600';
                return '<span class="' . $class . '">' . Format::time($row['followUpTime']) . '</span>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    $table->addColumn('administeredByName', __('Staff'))
        ->notSortable()
        ->format(function ($row) {
            $staff = '';
            if (!empty($row['administeredByName'])) {
                $staff = Format::name('', $row['administeredByName'], $row['administeredBySurname'], 'Staff', false, false);
            }
            if (!empty($row['witnessedByName'])) {
                $witness = Format::name('', $row['witnessedByName'], $row['witnessedBySurname'], 'Staff', false, false);
                $staff .= '<br><span class="text-xs text-gray-500">' . __('Witness') . ': ' . $witness . '</span>';
            }
            return $staff ?: '<span class="text-gray-400">-</span>';
        });

    $table->addColumn('parentNotified', __('Notified'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['parentNotified'] === 'Y') {
                $time = !empty($row['parentNotifiedTime']) ? '<br><span class="text-xs text-gray-500">' . Format::time($row['parentNotifiedTime']) . '</span>' : '';
                return '<span class="text-green-600" title="' . __('Parent Notified') . '">✓</span>' . $time;
            }
            return '<span class="text-red-600" title="' . __('Not Notified') . '">✗</span>';
        });

    $table->addColumn('parentAcknowledged', __('Ack'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['parentAcknowledged'] === 'Y') {
                return '<span class="text-green-600" title="' . __('Parent Acknowledged') . '">✓</span>';
            }
            return '<span class="text-gray-400" title="' . __('Not Acknowledged') . '">-</span>';
        });

    // Add row action for viewing details
    $table->addActionColumn()
        ->addParam('gibbonMedicalProtocolAdministrationID')
        ->format(function ($row, $actions) use ($session, $date, $viewMode) {
            $actions->addAction('view', __('View Details'))
                ->setIcon('search')
                ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_log_view.php')
                ->addParam('date', $viewMode === 'single' ? $date : $row['date'])
                ->addParam('viewMode', $viewMode);
        });

    // Output table
    if ($administrations->count() > 0) {
        echo $table->render($administrations);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No administration records found for the selected criteria.');
        echo '</div>';
    }

    // Quick navigation links
    echo '<div class="mt-4 flex flex-wrap gap-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol.php&date=' . $date . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_administer.php&date=' . $date . '" class="text-blue-600 hover:underline">' . __('Administer Protocol') . ' &rarr;</a>';
    echo '</div>';
}
