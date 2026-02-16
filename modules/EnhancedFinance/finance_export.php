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
 * Enhanced Finance Module - Accounting Export Page
 *
 * Allows export of financial data to accounting software formats:
 * - Sage 50 (CSV format)
 * - QuickBooks (IIF format)
 *
 * Features:
 * - Format selection (Sage 50, QuickBooks)
 * - Export type selection (Invoices, Payments, Combined)
 * - Date range filtering
 * - Export preview
 * - Export history with download links
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\EnhancedFinance\Domain\ExportGateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_export.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Accounting Export'));

    // Return messages
    $page->return->addReturns([
        'success0' => __('Your request was completed successfully.'),
        'success1' => __('Export completed successfully. Your file is ready for download.'),
        'error1' => __('There was an error creating the export.'),
        'error2' => __('No data found for the specified criteria.'),
        'error3' => __('Required parameters were not provided.'),
        'error4' => __('Please configure the accounting settings before exporting.'),
        'error5' => __('Invalid export format specified.'),
    ]);

    // Description
    echo '<p>';
    echo __('Export financial data to your accounting software. Select the target format (Sage 50 or QuickBooks), choose what to export (invoices, payments, or both), and specify a date range. The export history below shows all previous exports with download links.');
    echo '</p>';

    // Get current school year
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    if (!empty($gibbonSchoolYearID)) {
        // School year navigation
        $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

        // Get gateways
        $exportGateway = $container->get(ExportGateway::class);
        $settingGateway = $container->get(SettingGateway::class);

        // Get currency from settings
        $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

        // Check if accounting settings are configured
        $sage50AR = $settingGateway->getSettingByScope('Enhanced Finance', 'sage50AccountsReceivable');
        $quickbooksAR = $settingGateway->getSettingByScope('Enhanced Finance', 'quickbooksARAccount');

        $settingsConfigured = !empty($sage50AR) || !empty($quickbooksAR);

        if (!$settingsConfigured) {
            echo '<div class="warning">';
            echo '<strong>' . __('Settings Required') . ':</strong> ';
            echo __('Please configure the accounting export settings in Finance Settings before using this feature. You need to specify account codes for your accounting software.');
            echo ' <a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_settings.php">' . __('Go to Settings') . '</a>';
            echo '</div>';
        }

        // ============================================
        // EXPORT FORM
        // ============================================
        echo '<h3>';
        echo __('Create New Export');
        echo '</h3>';

        $form = Form::create('accountingExport', $session->get('absoluteURL') . '/modules/EnhancedFinance/finance_exportProcess.php');
        $form->setClass('noIntBorder w-full');

        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        // Export Format selection
        $exportFormats = [
            'Sage50' => __('Sage 50 (CSV)'),
            'QuickBooks' => __('QuickBooks (IIF)'),
        ];

        $row = $form->addRow();
            $row->addLabel('exportFormat', __('Export Format'));
            $row->addSelect('exportFormat')
                ->fromArray($exportFormats)
                ->required()
                ->placeholder(__('Select Format...'));

        // Export Type selection
        $exportTypes = [
            'invoices' => __('Invoices Only'),
            'payments' => __('Payments Only'),
            'combined' => __('Both Invoices & Payments'),
        ];

        $row = $form->addRow();
            $row->addLabel('exportType', __('Export Type'));
            $row->addSelect('exportType')
                ->fromArray($exportTypes)
                ->required()
                ->selected('combined');

        // Date Range
        $row = $form->addRow();
            $row->addLabel('dateFrom', __('Date From'))
                ->description(__('Leave empty to include all records'));
            $row->addDate('dateFrom');

        $row = $form->addRow();
            $row->addLabel('dateTo', __('Date To'))
                ->description(__('Leave empty to include all records'));
            $row->addDate('dateTo');

        // Preview option
        $row = $form->addRow();
            $row->addLabel('preview', __('Preview First'))
                ->description(__('Show preview of data before exporting'));
            $row->addCheckbox('preview')
                ->setValue('Y');

        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Export'));

        echo $form->getOutput();

        // ============================================
        // FORMAT-SPECIFIC INFORMATION
        // ============================================
        echo '<div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">';
        echo '<h4 class="font-semibold mb-2">' . __('Export Format Information') . '</h4>';
        echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';

        // Sage 50 Info
        echo '<div>';
        echo '<strong class="text-blue-700">Sage 50 (CSV)</strong>';
        echo '<ul class="text-sm mt-1 list-disc list-inside text-gray-600">';
        echo '<li>' . __('Comma-separated values format') . '</li>';
        echo '<li>' . __('Import via Data Management > Import Records') . '</li>';
        echo '<li>' . __('Configure A/R and Revenue account codes in Settings') . '</li>';
        echo '</ul>';
        echo '</div>';

        // QuickBooks Info
        echo '<div>';
        echo '<strong class="text-blue-700">QuickBooks (IIF)</strong>';
        echo '<ul class="text-sm mt-1 list-disc list-inside text-gray-600">';
        echo '<li>' . __('Intuit Interchange Format (Desktop only)') . '</li>';
        echo '<li>' . __('Import via File > Utilities > Import > IIF Files') . '</li>';
        echo '<li>' . __('Create customers in QuickBooks first, or export customer list') . '</li>';
        echo '</ul>';
        echo '</div>';

        echo '</div>'; // End grid
        echo '</div>'; // End info box

        // ============================================
        // EXPORT HISTORY
        // ============================================
        echo '<h3 class="mt-8">';
        echo __('Export History');
        echo '</h3>';

        // Build query criteria
        $criteria = $exportGateway->newQueryCriteria(true)
            ->sortBy(['timestampCreated'], 'DESC')
            ->fromPOST();

        // Execute query
        $exports = $exportGateway->queryExportsByYear($criteria, $gibbonSchoolYearID);

        // Create DataTable
        $table = DataTable::createPaginated('exportHistory', $criteria);

        // Row modifier for status highlighting
        $table->modifyRows(function ($export, $row) {
            if ($export['status'] == 'Completed') {
                $row->addClass('success');
            } else if ($export['status'] == 'Failed') {
                $row->addClass('error');
            } else if ($export['status'] == 'Processing') {
                $row->addClass('warning');
            }
            return $row;
        });

        // Filter options
        $table->addMetaData('filterOptions', [
            'status:Completed' => __('Status') . ': ' . __('Completed'),
            'status:Failed' => __('Status') . ': ' . __('Failed'),
            'status:Processing' => __('Status') . ': ' . __('Processing'),
            'exportType:Sage50' => __('Format') . ': Sage 50',
            'exportType:QuickBooks' => __('Format') . ': QuickBooks',
        ]);

        // Column: Export Date/Time
        $table->addColumn('timestampCreated', __('Export Date'))
            ->sortable(['timestampCreated'])
            ->format(function ($export) {
                return Format::dateTime($export['timestampCreated']);
            });

        // Column: Format/Type
        $table->addColumn('exportType', __('Format'))
            ->description(__('File Type'))
            ->sortable(['exportType'])
            ->format(function ($export) {
                $output = '<b>' . $export['exportType'] . '</b>';
                $output .= '<br/><span class="text-xs italic">' . $export['exportFormat'] . '</span>';
                return $output;
            });

        // Column: Date Range
        $table->addColumn('dateRange', __('Date Range'))
            ->notSortable()
            ->format(function ($export) {
                if (empty($export['dateRangeStart']) && empty($export['dateRangeEnd'])) {
                    return '<span class="text-gray-500">' . __('All dates') . '</span>';
                }
                $from = !empty($export['dateRangeStart']) ? Format::date($export['dateRangeStart']) : __('Start');
                $to = !empty($export['dateRangeEnd']) ? Format::date($export['dateRangeEnd']) : __('End');
                return $from . ' - ' . $to;
            });

        // Column: Records / Amount
        $table->addColumn('recordCount', __('Records'))
            ->description(__('Total Amount'))
            ->notSortable()
            ->format(function ($export) use ($currency) {
                $output = $export['recordCount'] . ' ' . __('records');
                if (!empty($export['totalAmount']) && $export['totalAmount'] > 0) {
                    $output .= '<br/><span class="text-xs text-green-600">' . Format::currency($export['totalAmount']) . '</span>';
                }
                return $output;
            });

        // Column: Status
        $table->addColumn('status', __('Status'))
            ->sortable(['status'])
            ->format(function ($export) {
                $status = $export['status'];
                $class = '';
                $icon = '';

                switch ($status) {
                    case 'Completed':
                        $class = 'text-green-600';
                        $icon = '<span class="mr-1">&#10003;</span>';
                        break;
                    case 'Failed':
                        $class = 'text-red-600';
                        $icon = '<span class="mr-1">&#10007;</span>';
                        break;
                    case 'Processing':
                        $class = 'text-orange-600';
                        $icon = '<span class="mr-1">&#8635;</span>';
                        break;
                    case 'Pending':
                        $class = 'text-gray-500';
                        $icon = '<span class="mr-1">&#8226;</span>';
                        break;
                }

                $output = '<span class="' . $class . ' font-semibold">' . $icon . __($status) . '</span>';

                // Show error message for failed exports
                if ($status == 'Failed' && !empty($export['errorMessage'])) {
                    $output .= '<br/><span class="text-xs text-red-500">' . htmlspecialchars(substr($export['errorMessage'], 0, 50)) . '...</span>';
                }

                return $output;
            });

        // Column: Exported By
        $table->addColumn('exportedBy', __('Exported By'))
            ->notSortable()
            ->format(function ($export) {
                return Format::name('', $export['exportedByPreferredName'], $export['exportedBySurname'], 'Staff', false, true);
            });

        // Column: File
        $table->addColumn('fileName', __('File'))
            ->notSortable()
            ->format(function ($export) {
                if ($export['status'] != 'Completed' || empty($export['filePath'])) {
                    return '-';
                }

                $fileSize = !empty($export['fileSize']) ? Format::bytes($export['fileSize']) : '';
                return '<span class="text-xs">' . htmlspecialchars($export['fileName']) . '</span>' .
                       ($fileSize ? '<br/><span class="text-xs text-gray-500">' . $fileSize . '</span>' : '');
            });

        // Actions column
        $table->addActionColumn()
            ->addParam('gibbonEnhancedFinanceExportLogID')
            ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->format(function ($export, $actions) use ($session) {
                // Download action - only for completed exports
                if ($export['status'] == 'Completed' && !empty($export['filePath'])) {
                    $actions->addAction('download', __('Download'))
                        ->setURL('/modules/EnhancedFinance/finance_export_download.php')
                        ->setIcon('download')
                        ->directLink();
                }
            });

        echo $table->render($exports);

        // ============================================
        // EXPORT STATISTICS
        // ============================================
        $stats = $exportGateway->selectExportStatistics($gibbonSchoolYearID);

        if (!empty($stats) && $stats['totalExports'] > 0) {
            echo '<div class="mt-6 p-4 bg-gray-50 border rounded-lg">';
            echo '<h4 class="font-semibold mb-3">' . __('Export Statistics for School Year') . '</h4>';
            echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4">';

            // Total Exports
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Total Exports') . '</div>';
            echo '<div class="text-xl font-semibold text-blue-600">' . ($stats['totalExports'] ?? 0) . '</div>';
            echo '</div>';

            // Completed
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Completed') . '</div>';
            echo '<div class="text-xl font-semibold text-green-600">' . ($stats['completedExports'] ?? 0) . '</div>';
            echo '</div>';

            // Failed
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Failed') . '</div>';
            echo '<div class="text-xl font-semibold text-red-600">' . ($stats['failedExports'] ?? 0) . '</div>';
            echo '</div>';

            // Records Exported
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Records Exported') . '</div>';
            echo '<div class="text-xl font-semibold text-purple-600">' . number_format($stats['totalRecordsExported'] ?? 0) . '</div>';
            echo '</div>';

            // Amount Exported
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Amount Exported') . '</div>';
            echo '<div class="text-xl font-semibold text-green-600">' . Format::currency($stats['totalAmountExported'] ?? 0) . '</div>';
            echo '</div>';

            echo '</div>'; // End grid
            echo '</div>'; // End stats box
        }

        // ============================================
        // EXPORT BY TYPE BREAKDOWN
        // ============================================
        $statsByType = $exportGateway->selectExportStatisticsByType($gibbonSchoolYearID)->fetchAll();

        if (!empty($statsByType)) {
            echo '<div class="mt-4">';
            echo '<h5 class="font-semibold mb-2">' . __('Exports by Format') . '</h5>';
            echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';

            foreach ($statsByType as $typeStat) {
                $successRate = $typeStat['totalExports'] > 0
                    ? round(($typeStat['completedExports'] / $typeStat['totalExports']) * 100)
                    : 0;

                echo '<div class="p-3 bg-white border rounded">';
                echo '<div class="font-semibold">' . $typeStat['exportType'] . '</div>';
                echo '<div class="text-sm text-gray-600">';
                echo sprintf(__('%d exports, %d completed (%d%% success rate)'),
                    $typeStat['totalExports'],
                    $typeStat['completedExports'],
                    $successRate
                );
                echo '</div>';
                if (!empty($typeStat['lastExportDate'])) {
                    echo '<div class="text-xs text-gray-500 mt-1">';
                    echo __('Last export') . ': ' . Format::dateTime($typeStat['lastExportDate']);
                    echo '</div>';
                }
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
        }

    } else {
        $page->addError(__('School year has not been specified.'));
    }
}
