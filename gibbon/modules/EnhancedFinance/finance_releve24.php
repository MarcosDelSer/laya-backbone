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
 * Enhanced Finance Module - RL-24 List View
 *
 * Displays paginated list of Quebec RL-24 (Relevé 24) tax slips with filtering
 * by tax year, status, family, and slip type. RL-24 is required by Revenu Québec
 * for childcare expense deductions.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Module\EnhancedFinance\Domain\Releve24Gateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_releve24.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Manage RL-24'));

    // Return messages
    $page->return->addReturns([
        'success0' => __('Your request was completed successfully.'),
        'success1' => __('RL-24 slip generated successfully.'),
        'success2' => __('RL-24 slip sent successfully.'),
        'success3' => __('RL-24 slip marked as filed.'),
        'error1' => __('There was an error generating the RL-24 slip.'),
        'error2' => __('The selected RL-24 slip could not be found.'),
        'error3' => __('Required parameters were not provided.'),
        'error4' => __('There was an error sending the RL-24 slip.'),
    ]);

    // Description
    echo '<p>';
    echo __('This section allows you to manage Quebec RL-24 (Relevé 24) tax slips. The RL-24 slip is required by Revenu Québec for parents to claim childcare expense deductions. Use the filters below to view slips by tax year, status, or family. You can generate, send, and track the status of all RL-24 slips from this page.');
    echo '</p>';

    // Get gateways
    $releve24Gateway = $container->get(Releve24Gateway::class);
    $familyGateway = $container->get(FamilyGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    // Get current school year ID for family filter
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Default tax year to previous year (RL-24 typically generated in new year for previous year)
    $currentYear = (int) date('Y');
    $defaultTaxYear = (date('n') <= 2) ? $currentYear - 1 : $currentYear;

    // Request parameters for filters
    $request = [
        'taxYear'         => $_GET['taxYear'] ?? $defaultTaxYear,
        'status'          => $_GET['status'] ?? '',
        'slipType'        => $_GET['slipType'] ?? '',
        'gibbonFamilyID'  => $_GET['gibbonFamilyID'] ?? '',
    ];

    // Validate tax year
    $taxYear = (int) $request['taxYear'];
    if ($taxYear < 2000 || $taxYear > $currentYear + 1) {
        $taxYear = $defaultTaxYear;
        $request['taxYear'] = $taxYear;
    }

    // Get currency from settings
    $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

    // Tax year options (last 5 years + available from database)
    $taxYearOptions = [];
    for ($year = $currentYear; $year >= $currentYear - 5; $year--) {
        $taxYearOptions[$year] = $year;
    }

    // Get additional years from database
    $availableYears = $releve24Gateway->selectAvailableTaxYears();
    foreach ($availableYears as $yearRow) {
        $taxYearOptions[$yearRow['taxYear']] = $yearRow['taxYear'];
    }
    krsort($taxYearOptions);

    // Build filter form
    $form = Form::create('releve24Filters', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filters'));
    $form->setClass('noIntBorder w-full');

    $form->addHiddenValue('q', '/modules/EnhancedFinance/finance_releve24.php');

    // Tax Year filter
    $row = $form->addRow();
        $row->addLabel('taxYear', __('Tax Year'));
        $row->addSelect('taxYear')
            ->fromArray($taxYearOptions)
            ->selected($request['taxYear']);

    // Status filter
    $statusOptions = [
        ''          => __('All'),
        'Draft'     => __('Draft'),
        'Generated' => __('Generated'),
        'Sent'      => __('Sent'),
        'Filed'     => __('Filed'),
        'Amended'   => __('Amended'),
    ];

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray($statusOptions)
            ->selected($request['status']);

    // Slip Type filter (R = Original, A = Amended, D = Cancelled)
    $slipTypeOptions = [
        ''  => __('All'),
        'R' => __('Original (R)'),
        'A' => __('Amended (A)'),
        'D' => __('Cancelled (D)'),
    ];

    $row = $form->addRow();
        $row->addLabel('slipType', __('Slip Type'));
        $row->addSelect('slipType')
            ->fromArray($slipTypeOptions)
            ->selected($request['slipType']);

    // Family filter
    if (!empty($gibbonSchoolYearID)) {
        $families = $familyGateway->selectFamiliesWithActiveStudents($gibbonSchoolYearID)->fetchAll();
        $familyOptions = array_column($families, 'name', 'gibbonFamilyID');

        $row = $form->addRow();
            $row->addLabel('gibbonFamilyID', __('Family'));
            $row->addSelect('gibbonFamilyID')
                ->fromArray($familyOptions)
                ->placeholder()
                ->selected($request['gibbonFamilyID']);
    }

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    // RL-24 list section
    echo '<h3>';
    echo __('RL-24 Slips') . ' - ' . $taxYear;
    echo '</h3>';

    // Build query criteria
    $criteria = $releve24Gateway->newQueryCriteria(true)
        ->sortBy(['defaultSortOrder', 'recipientName', 'childName'])
        ->filterBy('status', $request['status'])
        ->filterBy('slipType', $request['slipType'])
        ->filterBy('family', $request['gibbonFamilyID'])
        ->fromPOST();

    // Execute query
    $releve24Slips = $releve24Gateway->queryReleve24ByYear($criteria, $taxYear);

    // Create DataTable
    $table = DataTable::createPaginated('releve24', $criteria);

    // Add batch generation button
    $table->addHeaderAction('batch', __('Batch Generate'))
        ->setURL('/modules/EnhancedFinance/finance_releve24_batch.php')
        ->addParam('taxYear', $taxYear)
        ->setIcon('run')
        ->displayLabel();

    // Add single generation button
    $table->addHeaderAction('add', __('Generate Single'))
        ->setURL('/modules/EnhancedFinance/finance_releve24_generate.php')
        ->addParam('taxYear', $taxYear)
        ->displayLabel();

    // Row modifier for status highlighting
    $table->modifyRows(function ($slip, $row) {
        // Highlight based on status
        if ($slip['status'] == 'Sent' || $slip['status'] == 'Filed') {
            $row->addClass('current');
        } else if ($slip['status'] == 'Generated') {
            $row->addClass('message');
        } else if ($slip['status'] == 'Draft') {
            $row->addClass('dull');
        } else if ($slip['status'] == 'Amended') {
            $row->addClass('warning');
        }
        return $row;
    });

    // Filter options for quick access
    $table->addMetaData('filterOptions', [
        'status:Draft'     => __('Status') . ': ' . __('Draft'),
        'status:Generated' => __('Status') . ': ' . __('Generated'),
        'status:Sent'      => __('Status') . ': ' . __('Sent'),
        'status:Filed'     => __('Status') . ': ' . __('Filed'),
        'status:Amended'   => __('Status') . ': ' . __('Amended'),
        'slipType:R'       => __('Type') . ': ' . __('Original'),
        'slipType:A'       => __('Type') . ': ' . __('Amended'),
        'slipType:D'       => __('Type') . ': ' . __('Cancelled'),
    ]);

    // Column: Recipient / Child
    $table->addColumn('recipient', __('Recipient'))
        ->description(__('Child'))
        ->sortable(['recipientName', 'childName'])
        ->format(function ($slip) {
            $output = '<b>' . htmlspecialchars($slip['recipientName'] ?? '') . '</b>';
            // Child name
            if (!empty($slip['childPreferredName']) || !empty($slip['childSurname'])) {
                $childName = Format::name('', $slip['childPreferredName'] ?? '', $slip['childSurname'] ?? '', 'Student', true);
            } else {
                $childName = htmlspecialchars($slip['childName'] ?? '');
            }
            $output .= '<br/><span class="text-xs italic">' . $childName . '</span>';
            return $output;
        });

    // Column: Family
    $table->addColumn('familyName', __('Family'))
        ->sortable(['familyName'])
        ->width('12%');

    // Column: Slip Type
    $table->addColumn('slipType', __('Type'))
        ->sortable(['slipType'])
        ->width('8%')
        ->format(function ($slip) {
            $types = [
                'R' => ['label' => __('Original'), 'class' => 'bg-green-100 text-green-800'],
                'A' => ['label' => __('Amended'), 'class' => 'bg-orange-100 text-orange-800'],
                'D' => ['label' => __('Cancelled'), 'class' => 'bg-red-100 text-red-800'],
            ];
            $type = $slip['slipType'] ?? 'R';
            $typeInfo = $types[$type] ?? ['label' => $type, 'class' => 'bg-gray-100 text-gray-800'];
            return '<span class="text-xs px-2 py-1 rounded ' . $typeInfo['class'] . '">' . $typeInfo['label'] . '</span>';
        });

    // Column: Days of Care
    $table->addColumn('daysOfCare', __('Days'))
        ->sortable(['daysOfCare'])
        ->width('7%')
        ->format(function ($slip) {
            return $slip['daysOfCare'] ?? '-';
        });

    // Column: Total Paid
    $table->addColumn('totalAmountsPaid', __('Total Paid') . ' <small><i>(' . $currency . ')</i></small>')
        ->sortable(['totalAmountsPaid'])
        ->width('10%')
        ->format(function ($slip) {
            return Format::currency($slip['totalAmountsPaid'] ?? 0);
        });

    // Column: Qualifying Expenses
    $table->addColumn('qualifyingExpenses', __('Qualifying') . ' <small><i>(' . $currency . ')</i></small>')
        ->description(__('Non-Qualifying'))
        ->sortable(['qualifyingExpenses'])
        ->width('12%')
        ->format(function ($slip) {
            $output = '<span class="text-green-700 font-semibold">' . Format::currency($slip['qualifyingExpenses'] ?? 0) . '</span>';
            if (!empty($slip['nonQualifyingExpenses']) && (float)$slip['nonQualifyingExpenses'] > 0) {
                $output .= '<br/><span class="text-xs text-gray-500">' . Format::currency($slip['nonQualifyingExpenses']) . '</span>';
            }
            return $output;
        });

    // Column: Status
    $table->addColumn('status', __('Status'))
        ->sortable(['status'])
        ->width('10%')
        ->format(function ($slip) {
            $status = $slip['status'] ?? 'Draft';
            $classes = [
                'Draft'     => 'text-gray-500',
                'Generated' => 'text-blue-600',
                'Sent'      => 'text-green-600 font-semibold',
                'Filed'     => 'text-green-700 font-bold',
                'Amended'   => 'text-orange-600',
            ];
            $class = $classes[$status] ?? 'text-gray-500';
            return '<span class="' . $class . '">' . __($status) . '</span>';
        });

    // Column: Generated / Sent Dates
    $table->addColumn('dates', __('Generated'))
        ->description(__('Sent'))
        ->notSortable()
        ->width('10%')
        ->format(function ($slip) {
            $output = !empty($slip['generatedAt']) ? Format::date($slip['generatedAt']) : '-';
            $output .= '<br/><span class="text-xs italic">';
            $output .= !empty($slip['sentAt']) ? Format::date($slip['sentAt']) : '-';
            $output .= '</span>';
            return $output;
        });

    // Actions column
    $table->addActionColumn()
        ->addParam('gibbonEnhancedFinanceReleve24ID')
        ->addParam('taxYear', $taxYear)
        ->format(function ($slip, $actions) {
            // View action - always available
            $actions->addAction('view', __('View'))
                ->setURL('/modules/EnhancedFinance/finance_releve24_view.php');

            // Print/Download PDF
            $actions->addAction('print', __('Download PDF'))
                ->setURL('/modules/EnhancedFinance/finance_releve24_pdf.php')
                ->setIcon('print')
                ->directLink();

            // Edit action - only for Draft or Generated
            if (in_array($slip['status'], ['Draft', 'Generated'])) {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/EnhancedFinance/finance_releve24_edit.php');
            }

            // Send action - only for Generated (not yet sent)
            if ($slip['status'] == 'Generated' && empty($slip['sentAt'])) {
                $actions->addAction('send', __('Send'))
                    ->setURL('/modules/EnhancedFinance/finance_releve24_send.php')
                    ->setIcon('delivery');
            }

            // Mark as Filed - only for Sent
            if ($slip['status'] == 'Sent') {
                $actions->addAction('file', __('Mark as Filed'))
                    ->setURL('/modules/EnhancedFinance/finance_releve24_file.php')
                    ->setIcon('copyback');
            }

            // Amend action - only for Sent or Filed
            if (in_array($slip['status'], ['Sent', 'Filed'])) {
                $actions->addAction('amend', __('Create Amendment'))
                    ->setURL('/modules/EnhancedFinance/finance_releve24_amend.php')
                    ->setIcon('refresh');
            }
        });

    echo $table->render($releve24Slips);

    // Summary statistics
    $summary = $releve24Gateway->selectReleve24SummaryByYear($taxYear);

    if (!empty($summary) && $summary['totalSlips'] > 0) {
        echo '<div class="mt-6 p-4 bg-gray-50 border rounded-lg">';
        echo '<h4 class="font-semibold mb-3">' . __('RL-24 Summary for Tax Year') . ' ' . $taxYear . '</h4>';
        echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4">';

        // Total Slips
        echo '<div class="text-center">';
        echo '<div class="text-sm text-gray-500">' . __('Total Slips') . '</div>';
        echo '<div class="text-xl font-semibold text-blue-600">' . ($summary['totalSlips'] ?? 0) . '</div>';
        echo '</div>';

        // By Type breakdown
        echo '<div class="text-center">';
        echo '<div class="text-sm text-gray-500">' . __('Original') . '</div>';
        echo '<div class="text-xl font-semibold text-green-600">' . ($summary['originalSlips'] ?? 0) . '</div>';
        echo '</div>';

        // Amended
        echo '<div class="text-center">';
        echo '<div class="text-sm text-gray-500">' . __('Amended') . '</div>';
        echo '<div class="text-xl font-semibold text-orange-600">' . ($summary['amendedSlips'] ?? 0) . '</div>';
        echo '</div>';

        // Sent
        echo '<div class="text-center">';
        echo '<div class="text-sm text-gray-500">' . __('Sent') . '</div>';
        echo '<div class="text-xl font-semibold text-green-700">' . ($summary['sentCount'] ?? 0) . '</div>';
        echo '</div>';

        // Pending (Draft + Generated)
        $pendingCount = ($summary['draftCount'] ?? 0) + ($summary['generatedCount'] ?? 0);
        echo '<div class="text-center">';
        echo '<div class="text-sm text-gray-500">' . __('Pending') . '</div>';
        echo '<div class="text-xl font-semibold text-blue-600">' . $pendingCount . '</div>';
        echo '</div>';

        echo '</div>'; // End grid

        // Financial totals
        echo '<div class="mt-4 pt-3 border-t grid grid-cols-2 md:grid-cols-4 gap-4">';

        // Total Days of Care
        echo '<div class="text-center">';
        echo '<div class="text-sm text-gray-500">' . __('Total Days of Care') . '</div>';
        echo '<div class="text-lg font-semibold text-blue-600">' . number_format($summary['totalDaysOfCare'] ?? 0) . '</div>';
        echo '</div>';

        // Total Amounts Paid
        echo '<div class="text-center">';
        echo '<div class="text-sm text-gray-500">' . __('Total Amounts Paid') . '</div>';
        echo '<div class="text-lg font-semibold text-green-600">' . Format::currency($summary['totalAmountsPaid'] ?? 0) . '</div>';
        echo '</div>';

        // Total Qualifying Expenses
        echo '<div class="text-center">';
        echo '<div class="text-sm text-gray-500">' . __('Qualifying Expenses') . '</div>';
        echo '<div class="text-lg font-semibold text-green-700">' . Format::currency($summary['totalQualifyingExpenses'] ?? 0) . '</div>';
        echo '</div>';

        // Total Non-Qualifying
        echo '<div class="text-center">';
        echo '<div class="text-sm text-gray-500">' . __('Non-Qualifying') . '</div>';
        echo '<div class="text-lg font-semibold text-gray-600">' . Format::currency($summary['totalNonQualifyingExpenses'] ?? 0) . '</div>';
        echo '</div>';

        echo '</div>'; // End financial totals
        echo '</div>'; // End summary box
    }

    // Status breakdown by count
    $statusCounts = $releve24Gateway->selectReleve24CountByStatus($taxYear);
    if ($statusCounts->rowCount() > 0) {
        echo '<div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">';
        echo '<h4 class="font-semibold text-blue-800 mb-3">' . __('Status Breakdown') . '</h4>';
        echo '<div class="flex flex-wrap gap-4">';

        foreach ($statusCounts as $statusRow) {
            $statusColors = [
                'Draft'     => 'bg-gray-200 text-gray-800',
                'Generated' => 'bg-blue-200 text-blue-800',
                'Sent'      => 'bg-green-200 text-green-800',
                'Filed'     => 'bg-green-300 text-green-900',
                'Amended'   => 'bg-orange-200 text-orange-800',
            ];
            $colorClass = $statusColors[$statusRow['status']] ?? 'bg-gray-200 text-gray-800';

            echo '<div class="px-3 py-2 rounded ' . $colorClass . '">';
            echo '<span class="font-semibold">' . __($statusRow['status']) . ':</span> ';
            echo $statusRow['slipCount'];
            if (!empty($statusRow['totalQualifyingExpenses'])) {
                echo ' <span class="text-xs">(' . Format::currency($statusRow['totalQualifyingExpenses']) . ')</span>';
            }
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Information notice about RL-24
    echo '<div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">';
    echo '<h4 class="font-semibold text-yellow-800 mb-2">' . __('About Quebec RL-24') . '</h4>';
    echo '<ul class="list-disc list-inside text-yellow-700 space-y-1">';
    echo '<li>' . __('The RL-24 slip (Relevé 24) is required by Revenu Québec for childcare expense tax deductions.') . '</li>';
    echo '<li>' . __('You must issue an RL-24 to parents by the last day of February for the previous tax year.') . '</li>';
    echo '<li>' . __('Box A reports total amounts paid, Box B reports qualifying childcare expenses.') . '</li>';
    echo '<li>' . __('Original slips have type "R", amendments have type "A", and cancellations have type "D".') . '</li>';
    echo '<li>' . __('Use "Batch Generate" to create RL-24 slips for all families with payments in the tax year.') . '</li>';
    echo '</ul>';
    echo '</div>';
}
