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
use Gibbon\Tables\DataTable;
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\RL24Submission\Domain\RL24SlipGateway;
use Gibbon\Module\RL24Submission\Domain\RL24TransmissionGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('RL-24 Slips'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/RL24Submission/rl24_slips.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get filter values from request
    $taxYear = $_GET['taxYear'] ?? date('Y');
    $status = $_GET['status'] ?? '';
    $caseACode = $_GET['caseACode'] ?? '';
    $transmission = $_GET['transmission'] ?? '';
    $search = $_GET['search'] ?? '';

    // Validate tax year (must be 4-digit year)
    if (!preg_match('/^\d{4}$/', $taxYear)) {
        $taxYear = date('Y');
    }

    // Get gateways via DI container
    $slipGateway = $container->get(RL24SlipGateway::class);
    $transmissionGateway = $container->get(RL24TransmissionGateway::class);

    // Get slip summary statistics
    $summaryStats = $slipGateway->getSlipSummaryByTaxYear($taxYear);

    // Page header
    echo '<h2>' . __('RL-24 Slips') . '</h2>';

    // Display summary dashboard
    echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">';

    // Total Slips Card
    echo '<div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-indigo-600">' . ($summaryStats['totalSlips'] ?? 0) . '</div>';
    echo '<div class="text-sm text-indigo-700">' . __('Total Slips') . '</div>';
    echo '</div>';

    // Original Slips Card
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-blue-600">' . ($summaryStats['originalCount'] ?? 0) . '</div>';
    echo '<div class="text-sm text-blue-700">' . __('Original (O)') . '</div>';
    echo '</div>';

    // Amended Slips Card
    echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-yellow-600">' . ($summaryStats['amendedCount'] ?? 0) . '</div>';
    echo '<div class="text-sm text-yellow-700">' . __('Amended (A)') . '</div>';
    echo '</div>';

    // Cancelled Slips Card
    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-red-600">' . ($summaryStats['cancelledCount'] ?? 0) . '</div>';
    echo '<div class="text-sm text-red-700">' . __('Cancelled (D)') . '</div>';
    echo '</div>';

    // Draft Status Card
    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-gray-600">' . ($summaryStats['draftCount'] ?? 0) . '</div>';
    echo '<div class="text-sm text-gray-700">' . __('Draft') . '</div>';
    echo '</div>';

    // Included Status Card
    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-green-600">' . ($summaryStats['includedCount'] ?? 0) . '</div>';
    echo '<div class="text-sm text-green-700">' . __('Included') . '</div>';
    echo '</div>';

    // Total Amount Card
    echo '<div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">';
    echo '<div class="text-2xl font-bold text-purple-600">$' . number_format($summaryStats['totalCase12'] ?? 0, 0) . '</div>';
    echo '<div class="text-sm text-purple-700">' . __('Total Amount') . '</div>';
    echo '</div>';

    echo '</div>';

    // Summary totals row
    echo '<div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">';
    echo '<h4 class="font-semibold text-gray-800 mb-3">' . __('Tax Year Summary') . ' - ' . htmlspecialchars($taxYear) . '</h4>';
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">';
    echo '<div><span class="text-gray-500">' . __('Total Days (Box 10):') . '</span> <span class="font-semibold">' . number_format($summaryStats['totalDays'] ?? 0) . '</span></div>';
    echo '<div><span class="text-gray-500">' . __('Box 11 Amount:') . '</span> <span class="font-semibold">$' . number_format($summaryStats['totalCase11'] ?? 0, 2) . '</span></div>';
    echo '<div><span class="text-gray-500">' . __('Box 12 Amount:') . '</span> <span class="font-semibold">$' . number_format($summaryStats['totalCase12'] ?? 0, 2) . '</span></div>';
    echo '<div><span class="text-gray-500">' . __('Box 13 Amount:') . '</span> <span class="font-semibold">$' . number_format($summaryStats['totalCase13'] ?? 0, 2) . '</span></div>';
    echo '<div><span class="text-gray-500">' . __('Box 14 Amount:') . '</span> <span class="font-semibold">$' . number_format($summaryStats['totalCase14'] ?? 0, 2) . '</span></div>';
    echo '</div>';
    echo '</div>';

    // Get distinct tax years for filter
    $taxYearOptions = [];
    $currentYear = (int) date('Y');
    for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
        $taxYearOptions[$y] = $y;
    }

    // Get transmissions for filter
    $transmissionOptions = ['' => __('All Transmissions')];
    $transmissions = $transmissionGateway->selectTransmissionsByTaxYear($taxYear);
    while ($row = $transmissions->fetch()) {
        $label = $row['fileName'] ?? 'Transmission #' . $row['gibbonRL24TransmissionID'];
        $transmissionOptions[$row['gibbonRL24TransmissionID']] = $label . ' (' . $row['status'] . ')';
    }

    // Filter form
    $form = Form::create('slipsFilter', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/RL24Submission/rl24_slips.php');

    $row = $form->addRow();
        $row->addLabel('taxYear', __('Tax Year'));
        $row->addSelect('taxYear')
            ->fromArray($taxYearOptions)
            ->selected($taxYear)
            ->setClass('standardWidth');

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray([
                '' => __('All'),
                'Draft' => __('Draft'),
                'Included' => __('Included'),
                'Amended' => __('Amended'),
                'Cancelled' => __('Cancelled'),
            ])
            ->selected($status)
            ->setClass('standardWidth');

    $row = $form->addRow();
        $row->addLabel('caseACode', __('Slip Type'));
        $row->addSelect('caseACode')
            ->fromArray([
                '' => __('All Types'),
                'O' => __('Original (O)'),
                'A' => __('Amended (A)'),
                'D' => __('Cancelled (D)'),
            ])
            ->selected($caseACode)
            ->setClass('standardWidth');

    $row = $form->addRow();
        $row->addLabel('transmission', __('Transmission'));
        $row->addSelect('transmission')
            ->fromArray($transmissionOptions)
            ->selected($transmission)
            ->setClass('standardWidth');

    $row = $form->addRow();
        $row->addLabel('search', __('Search'));
        $row->addTextField('search')
            ->setClass('standardWidth')
            ->placeholder(__('Child or parent name...'))
            ->setValue($search);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    // Build query criteria
    $criteria = $slipGateway->newQueryCriteria(true)
        ->sortBy(['slipNumber'], 'DESC')
        ->filterBy('status', $status)
        ->filterBy('caseACode', $caseACode)
        ->filterBy('transmission', $transmission)
        ->fromPOST();

    // Handle search parameter
    if (!empty($search)) {
        $criteria->filterBy('search', $search);
    }

    // Query slips by tax year
    $slips = $slipGateway->querySlipsByTaxYear($criteria, $taxYear);

    // Create data table
    $table = DataTable::createPaginated('rl24Slips', $criteria);

    $table->setTitle(__('RL-24 Slips for Tax Year') . ' ' . $taxYear);

    // Slip Number column
    $table->addColumn('slipNumber', __('Slip #'))
        ->width('6%')
        ->format(function ($row) {
            return '<span class="font-mono">' . str_pad($row['slipNumber'], 4, '0', STR_PAD_LEFT) . '</span>';
        });

    // Type Code column
    $table->addColumn('caseACode', __('Type'))
        ->width('6%')
        ->format(function ($row) {
            $typeClasses = [
                'O' => 'tag message',
                'A' => 'tag warning',
                'D' => 'tag error',
            ];
            $typeLabels = [
                'O' => __('Original'),
                'A' => __('Amended'),
                'D' => __('Cancelled'),
            ];
            $class = $typeClasses[$row['caseACode']] ?? 'tag dull';
            $label = $typeLabels[$row['caseACode']] ?? $row['caseACode'];
            return '<span class="' . $class . '">' . $row['caseACode'] . '</span>';
        });

    // Status column
    $table->addColumn('status', __('Status'))
        ->width('8%')
        ->format(function ($row) {
            $statusClasses = [
                'Draft' => 'tag dull',
                'Included' => 'tag success',
                'Amended' => 'tag warning',
                'Cancelled' => 'tag error',
            ];
            $class = $statusClasses[$row['status']] ?? 'tag dull';
            return '<span class="' . $class . '">' . htmlspecialchars($row['status']) . '</span>';
        });

    // Child Name column
    $table->addColumn('childName', __('Child'))
        ->width('15%')
        ->format(function ($row) {
            return htmlspecialchars($row['childFirstName'] . ' ' . $row['childLastName']);
        });

    // Parent Name column
    $table->addColumn('parentName', __('Parent/Guardian'))
        ->width('15%')
        ->format(function ($row) {
            return htmlspecialchars($row['parentFirstName'] . ' ' . $row['parentLastName']);
        });

    // Service Period column
    $table->addColumn('servicePeriod', __('Service Period'))
        ->width('12%')
        ->format(function ($row) use ($slipGateway) {
            // Get full slip details for service period
            $slip = $slipGateway->getSlipByID($row['gibbonRL24SlipID']);
            if (!empty($slip['servicePeriodStart']) && !empty($slip['servicePeriodEnd'])) {
                return Format::date($slip['servicePeriodStart']) . ' - ' . Format::date($slip['servicePeriodEnd']);
            }
            return '<span class="text-gray-400">-</span>';
        });

    // Total Days column
    $table->addColumn('totalDays', __('Days'))
        ->width('6%')
        ->format(function ($row) {
            return '<span class="tag dull">' . ($row['totalDays'] ?? 0) . '</span>';
        });

    // Box 12 Amount column
    $table->addColumn('case12Amount', __('Box 12'))
        ->width('10%')
        ->format(function ($row) {
            $amount = (float) ($row['case12Amount'] ?? 0);
            return '$' . number_format($amount, 2);
        });

    // Transmission column
    $table->addColumn('transmission', __('Transmission'))
        ->width('12%')
        ->format(function ($row) use ($session) {
            if (!empty($row['transmissionFileName'])) {
                $statusClasses = [
                    'Draft' => 'text-gray-500',
                    'Generated' => 'text-blue-500',
                    'Validated' => 'text-purple-500',
                    'Submitted' => 'text-yellow-600',
                    'Accepted' => 'text-green-600',
                    'Rejected' => 'text-red-500',
                ];
                $class = $statusClasses[$row['transmissionStatus']] ?? 'text-gray-500';
                return '<code class="text-xs">' . htmlspecialchars($row['transmissionFileName']) . '</code>' .
                       '<br><span class="text-xs ' . $class . '">' . htmlspecialchars($row['transmissionStatus']) . '</span>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    // Created column
    $table->addColumn('timestampCreated', __('Created'))
        ->width('10%')
        ->format(function ($row) {
            return Format::dateTime($row['timestampCreated']);
        });

    // Actions column
    $table->addActionColumn()
        ->addParam('gibbonRL24SlipID')
        ->addParam('gibbonRL24TransmissionID')
        ->format(function ($row, $actions) use ($session) {
            // View details - link to transmission view
            $actions->addAction('view', __('View in Transmission'))
                ->setURL('/modules/RL24Submission/rl24_transmissions_view.php')
                ->addParam('gibbonRL24TransmissionID', $row['gibbonRL24TransmissionID'])
                ->setIcon('search');

            // Amend action - only for included original slips
            if ($row['status'] === 'Included' && $row['caseACode'] === 'O') {
                $actions->addAction('amend', __('Create Amendment'))
                    ->setURL('/modules/RL24Submission/rl24_slip_amend.php')
                    ->setIcon('copy');
            }

            // Cancel action - only for included slips
            if ($row['status'] === 'Included') {
                $actions->addAction('cancel', __('Cancel Slip'))
                    ->setURL('/modules/RL24Submission/rl24_slip_cancel.php')
                    ->setIcon('garbage')
                    ->addConfirmation(__('Are you sure you want to create a cancellation slip?'));
            }
        });

    echo $table->render($slips);

    // Information box with slip type legend
    echo '<div class="message">';
    echo '<h4>' . __('RL-24 Slip Types') . '</h4>';
    echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">';
    echo '<div>';
    echo '<span class="tag message">O</span> ';
    echo '<strong>' . __('Original') . '</strong> - ' . __('First-time slip for a child/parent combination.');
    echo '</div>';
    echo '<div>';
    echo '<span class="tag warning">A</span> ';
    echo '<strong>' . __('Amended') . '</strong> - ' . __('Correction to a previously submitted slip.');
    echo '</div>';
    echo '<div>';
    echo '<span class="tag error">D</span> ';
    echo '<strong>' . __('Cancelled') . '</strong> - ' . __('Cancellation of a previously submitted slip.');
    echo '</div>';
    echo '</div>';
    echo '<h4 class="mt-4">' . __('Slip Statuses') . '</h4>';
    echo '<div class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">';
    echo '<div>';
    echo '<span class="tag dull">Draft</span> - ' . __('Not yet included in transmission.');
    echo '</div>';
    echo '<div>';
    echo '<span class="tag success">Included</span> - ' . __('Included in generated XML transmission.');
    echo '</div>';
    echo '<div>';
    echo '<span class="tag warning">Amended</span> - ' . __('Has been replaced by an amendment.');
    echo '</div>';
    echo '<div>';
    echo '<span class="tag error">Cancelled</span> - ' . __('Has been cancelled.');
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
