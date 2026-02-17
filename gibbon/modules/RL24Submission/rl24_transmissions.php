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
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\RL24Submission\Domain\RL24TransmissionGateway;

if (isActionAccessible($guid, $connection2, '/modules/RL24Submission/rl24_transmissions.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs->add(__('RL-24 Transmissions'));

    // Get school year
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get gateway
    $transmissionGateway = $container->get(RL24TransmissionGateway::class);

    // Get transmission statistics
    $stats = $transmissionGateway->getTransmissionSummaryBySchoolYear($gibbonSchoolYearID);

    // Display statistics
    echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-4 mb-6">';

    // Draft
    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-gray-600">' . ($stats['draftCount'] ?? 0) . '</div>';
    echo '<div class="text-sm text-gray-700">' . __('Draft') . '</div>';
    echo '</div>';

    // Generated
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-blue-600">' . ($stats['generatedCount'] ?? 0) . '</div>';
    echo '<div class="text-sm text-blue-700">' . __('Generated') . '</div>';
    echo '</div>';

    // Validated
    echo '<div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-purple-600">' . ($stats['validatedCount'] ?? 0) . '</div>';
    echo '<div class="text-sm text-purple-700">' . __('Validated') . '</div>';
    echo '</div>';

    // Submitted
    echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-yellow-600">' . ($stats['submittedCount'] ?? 0) . '</div>';
    echo '<div class="text-sm text-yellow-700">' . __('Submitted') . '</div>';
    echo '</div>';

    // Accepted
    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-green-600">' . ($stats['acceptedCount'] ?? 0) . '</div>';
    echo '<div class="text-sm text-green-700">' . __('Accepted') . '</div>';
    echo '</div>';

    // Rejected
    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-red-600">' . ($stats['rejectedCount'] ?? 0) . '</div>';
    echo '<div class="text-sm text-red-700">' . __('Rejected') . '</div>';
    echo '</div>';

    // Total
    echo '<div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-indigo-600">' . ($stats['totalTransmissions'] ?? 0) . '</div>';
    echo '<div class="text-sm text-indigo-700">' . __('Total') . '</div>';
    echo '</div>';

    echo '</div>';

    // Summary totals
    echo '<div class="bg-white border border-gray-200 rounded-lg p-4 mb-6">';
    echo '<h4 class="font-semibold text-gray-800 mb-3">' . __('Year Summary') . '</h4>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">';
    echo '<div><span class="text-gray-500">' . __('Total Slips:') . '</span> <span class="font-semibold">' . number_format($stats['totalSlips'] ?? 0) . '</span></div>';
    echo '<div><span class="text-gray-500">' . __('Total Days (Box 10):') . '</span> <span class="font-semibold">' . number_format($stats['totalDays'] ?? 0) . '</span></div>';
    echo '<div><span class="text-gray-500">' . __('Amount Case 11:') . '</span> <span class="font-semibold">$' . number_format($stats['totalAmountCase11'] ?? 0, 2) . '</span></div>';
    echo '<div><span class="text-gray-500">' . __('Amount Case 12:') . '</span> <span class="font-semibold">$' . number_format($stats['totalAmountCase12'] ?? 0, 2) . '</span></div>';
    echo '</div>';
    echo '</div>';

    // Get distinct tax years for filter
    $taxYearOptions = ['' => __('All Tax Years')];
    $taxYears = $transmissionGateway->selectDistinctTaxYears();
    while ($row = $taxYears->fetch()) {
        $taxYearOptions[$row['taxYear']] = $row['taxYear'];
    }

    // Filter form
    $form = Form::create('filter', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/RL24Submission/rl24_transmissions.php');

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray([
                '' => __('All'),
                'Draft' => __('Draft'),
                'Generated' => __('Generated'),
                'Validated' => __('Validated'),
                'Submitted' => __('Submitted'),
                'Accepted' => __('Accepted'),
                'Rejected' => __('Rejected'),
                'Cancelled' => __('Cancelled'),
            ])
            ->selected($_GET['status'] ?? '');

    $row = $form->addRow();
        $row->addLabel('taxYear', __('Tax Year'));
        $row->addSelect('taxYear')
            ->fromArray($taxYearOptions)
            ->selected($_GET['taxYear'] ?? '');

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    // Build query criteria
    $criteria = $transmissionGateway->newQueryCriteria(true)
        ->sortBy(['taxYear', 'sequenceNumber'], 'DESC')
        ->filterBy('status', $_GET['status'] ?? '')
        ->filterBy('taxYear', $_GET['taxYear'] ?? '')
        ->fromPOST();

    // Get transmissions
    $transmissions = $transmissionGateway->queryTransmissions($criteria, $gibbonSchoolYearID);

    // Create data table
    $table = DataTable::createPaginated('rl24Transmissions', $criteria);
    $table->setTitle(__('RL-24 Transmissions'));

    // Add header action for generating new transmission
    $table->addHeaderAction('add', __('Generate New Batch'))
        ->setURL('/modules/RL24Submission/rl24_transmissions_generate.php')
        ->setIcon('plus')
        ->displayLabel();

    // Status column
    $table->addColumn('status', __('Status'))
        ->width('10%')
        ->format(function ($row) {
            $statusClasses = [
                'Draft' => 'tag dull',
                'Generated' => 'tag message',
                'Validated' => 'tag',
                'Submitted' => 'tag warning',
                'Accepted' => 'tag success',
                'Rejected' => 'tag error',
                'Cancelled' => 'tag dull',
            ];
            $class = $statusClasses[$row['status']] ?? 'tag dull';
            return '<span class="' . $class . '">' . htmlspecialchars($row['status']) . '</span>';
        });

    // Tax Year column
    $table->addColumn('taxYear', __('Tax Year'))
        ->width('8%')
        ->format(function ($row) {
            return '<span class="tag dull">' . htmlspecialchars($row['taxYear']) . '</span>';
        });

    // Sequence column
    $table->addColumn('sequenceNumber', __('Seq.'))
        ->width('6%')
        ->format(function ($row) {
            return str_pad($row['sequenceNumber'], 3, '0', STR_PAD_LEFT);
        });

    // File Name column
    $table->addColumn('fileName', __('File Name'))
        ->width('15%')
        ->format(function ($row) {
            if (!empty($row['fileName'])) {
                return '<code class="text-xs">' . htmlspecialchars($row['fileName']) . '</code>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    // Provider column
    $table->addColumn('providerName', __('Provider'))
        ->width('15%')
        ->format(function ($row) {
            $name = htmlspecialchars($row['providerName'] ?? '-');
            $neq = $row['providerNEQ'] ?? '';
            if (!empty($neq)) {
                $formattedNEQ = substr($neq, 0, 4) . ' ' . substr($neq, 4, 3) . ' ' . substr($neq, 7, 3);
                return $name . '<br><span class="text-xs text-gray-500">NEQ: ' . $formattedNEQ . '</span>';
            }
            return $name;
        });

    // Slips column
    $table->addColumn('totalSlips', __('Slips'))
        ->width('6%')
        ->format(function ($row) {
            return '<span class="tag dull">' . ($row['totalSlips'] ?? 0) . '</span>';
        });

    // Amount column (Case 12 - Total amount for childcare expenses)
    $table->addColumn('totalAmountCase12', __('Amount'))
        ->width('10%')
        ->format(function ($row) {
            $amount = (float) ($row['totalAmountCase12'] ?? 0);
            return '$' . number_format($amount, 2);
        });

    // XML Validated column
    $table->addColumn('xmlValidated', __('Valid'))
        ->width('6%')
        ->format(function ($row) {
            if ($row['xmlValidated'] === 'Y') {
                return '<span class="tag success">' . __('Yes') . '</span>';
            } elseif ($row['xmlValidated'] === 'N') {
                return '<span class="tag error">' . __('No') . '</span>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    // Generated By column
    $table->addColumn('generatedBy', __('Generated By'))
        ->width('12%')
        ->format(function ($row) {
            if (!empty($row['generatedByName']) || !empty($row['generatedBySurname'])) {
                return Format::name('', $row['generatedByName'] ?? '', $row['generatedBySurname'] ?? '', 'Staff');
            }
            return '-';
        });

    // Created column
    $table->addColumn('timestampCreated', __('Created'))
        ->width('12%')
        ->format(function ($row) {
            return Format::dateTime($row['timestampCreated']);
        });

    // Submission Date column
    $table->addColumn('submissionDate', __('Submitted'))
        ->width('10%')
        ->format(function ($row) {
            if (!empty($row['submissionDate'])) {
                return Format::date($row['submissionDate']);
            }
            return '-';
        });

    // Actions column
    $table->addActionColumn()
        ->addParam('gibbonRL24TransmissionID')
        ->format(function ($row, $actions) {
            // View action - always available
            $actions->addAction('view', __('View Details'))
                ->setURL('/modules/RL24Submission/rl24_transmissions_view.php')
                ->setIcon('search');

            // Download XML - only if file exists
            if (!empty($row['fileName']) && in_array($row['status'], ['Generated', 'Validated', 'Submitted', 'Accepted'])) {
                $actions->addAction('download', __('Download XML'))
                    ->setURL('/modules/RL24Submission/rl24_transmissions_download.php')
                    ->setIcon('download')
                    ->directLink();
            }

            // Mark as submitted - only for validated transmissions
            if ($row['status'] === 'Validated') {
                $actions->addAction('submit', __('Mark as Submitted'))
                    ->setURL('/modules/RL24Submission/rl24_transmissions_submitProcess.php')
                    ->setIcon('iconExternalLink')
                    ->directLink()
                    ->addConfirmation(__('Are you sure you want to mark this transmission as submitted to Revenu Quebec?'));
            }

            // Cancel action - only for draft/generated transmissions
            if (in_array($row['status'], ['Draft', 'Generated'])) {
                $actions->addAction('delete', __('Cancel'))
                    ->setURL('/modules/RL24Submission/rl24_transmissions_cancelProcess.php')
                    ->setIcon('garbage')
                    ->directLink()
                    ->addConfirmation(__('Are you sure you want to cancel this transmission? This action cannot be undone.'));
            }
        });

    echo $table->render($transmissions);

    // Information box
    echo '<div class="message">';
    echo '<h4>' . __('RL-24 Submission Process') . '</h4>';
    echo '<ol class="list-decimal list-inside space-y-1 text-sm">';
    echo '<li>' . __('Ensure all FO-0601 eligibility forms are complete and approved.') . '</li>';
    echo '<li>' . __('Click "Generate New Batch" to create a new transmission with RL-24 slips.') . '</li>';
    echo '<li>' . __('Review the generated XML file and verify slip data.') . '</li>';
    echo '<li>' . __('Download the XML file and submit to Revenu Quebec via their online portal.') . '</li>';
    echo '<li>' . __('Record the confirmation number once the submission is accepted.') . '</li>';
    echo '</ol>';
    echo '<p class="mt-3 text-sm text-gray-600">' . __('XML files follow Revenu Quebec specifications: AAPPPPPPSSS.xml format (AA=tax year, PPPPPP=preparer number, SSS=sequence).') . '</p>';
    echo '</div>';
}
