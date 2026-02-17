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
 * Enhanced Finance Module - Aging Report
 *
 * Displays accounts receivable aging report with 30/60/90+ day buckets.
 * Shows overdue invoices grouped by age with export functionality.
 *
 * Features:
 * - Summary cards showing totals by aging bucket (Current, 30, 60, 90+ days)
 * - Detailed invoice listing with aging calculations
 * - Export to CSV functionality
 * - Family grouping option
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\School\SchoolYearGateway;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_report_aging.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $groupByFamily = $_GET['groupByFamily'] ?? 'N';
    $export = $_GET['export'] ?? '';

    // Breadcrumbs
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Aging Report'));

    // Get gateways and settings
    $invoiceGateway = $container->get(InvoiceGateway::class);
    $settingGateway = $container->get(SettingGateway::class);
    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $familyGateway = $container->get(FamilyGateway::class);

    // Get school year information
    $schoolYear = $schoolYearGateway->getByID($gibbonSchoolYearID);
    $schoolYearName = $schoolYear['name'] ?? __('Current Year');

    // Get settings
    $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

    // Fetch all outstanding invoices with aging data
    $agingData = fetchAgingData($connection2, $gibbonSchoolYearID);

    // Calculate aging bucket totals
    $agingBuckets = calculateAgingBuckets($agingData);

    // Handle CSV export
    if ($export === 'csv') {
        exportAgingCsv($agingData, $agingBuckets, $schoolYearName, $currency);
        exit;
    }

    // School Year Navigator
    echo '<div class="flex justify-between items-center mb-4">';
    echo '<h2>' . __('Accounts Receivable Aging Report') . ' - ' . htmlspecialchars($schoolYearName) . '</h2>';

    // School year selection dropdown
    $schoolYears = $schoolYearGateway->selectSchoolYears()->fetchAll();
    echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php" class="flex items-center gap-2">';
    echo '<input type="hidden" name="q" value="/modules/EnhancedFinance/finance_report_aging.php">';
    echo '<label class="text-sm text-gray-600">' . __('School Year') . ':</label>';
    echo '<select name="gibbonSchoolYearID" onchange="this.form.submit()" class="border rounded px-2 py-1">';
    foreach ($schoolYears as $year) {
        $selected = ($year['gibbonSchoolYearID'] == $gibbonSchoolYearID) ? 'selected' : '';
        echo '<option value="' . $year['gibbonSchoolYearID'] . '" ' . $selected . '>' . htmlspecialchars($year['name']) . '</option>';
    }
    echo '</select>';
    echo '</form>';
    echo '</div>';

    // Description
    echo '<p class="mb-4">';
    echo __('This report shows outstanding invoices organized by how long they have been overdue. Use this report to identify and follow up on aging receivables.');
    echo '</p>';

    // Aging Summary Cards
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-6">';

    // Total Outstanding
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-gray-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Total Outstanding') . '</p>';
    echo '<p class="text-2xl font-bold text-gray-700">' . Format::currency($agingBuckets['total']) . '</p>';
    echo '</div>';
    echo '<div class="text-gray-500 bg-gray-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d invoices'), $agingBuckets['totalCount']) . '</p>';
    echo '</div>';

    // Current (Not Yet Due)
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Current') . '</p>';
    echo '<p class="text-2xl font-bold text-green-600">' . Format::currency($agingBuckets['current']) . '</p>';
    echo '</div>';
    echo '<div class="text-green-500 bg-green-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d invoices'), $agingBuckets['currentCount']) . ' - ' . __('Not yet due') . '</p>';
    echo '</div>';

    // 1-30 Days
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-yellow-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('1-30 Days') . '</p>';
    echo '<p class="text-2xl font-bold text-yellow-600">' . Format::currency($agingBuckets['days30']) . '</p>';
    echo '</div>';
    echo '<div class="text-yellow-500 bg-yellow-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d invoices'), $agingBuckets['days30Count']) . '</p>';
    echo '</div>';

    // 31-60 Days
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-orange-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('31-60 Days') . '</p>';
    echo '<p class="text-2xl font-bold text-orange-600">' . Format::currency($agingBuckets['days60']) . '</p>';
    echo '</div>';
    echo '<div class="text-orange-500 bg-orange-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d invoices'), $agingBuckets['days60Count']) . '</p>';
    echo '</div>';

    // 61-90 Days
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-red-400">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('61-90 Days') . '</p>';
    echo '<p class="text-2xl font-bold text-red-400">' . Format::currency($agingBuckets['days90']) . '</p>';
    echo '</div>';
    echo '<div class="text-red-400 bg-red-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d invoices'), $agingBuckets['days90Count']) . '</p>';
    echo '</div>';

    echo '</div>'; // End summary grid

    // 90+ Days Alert (Critical)
    if ($agingBuckets['over90Count'] > 0) {
        echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">';
        echo '<div class="flex items-center">';
        echo '<div class="text-red-500 mr-3">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
        echo '</div>';
        echo '<div class="flex-1">';
        echo '<h4 class="font-semibold text-red-800">' . __('Critical: Invoices Over 90 Days') . '</h4>';
        echo '<p class="text-red-700">' . sprintf(__('%d invoice(s) totaling %s are over 90 days overdue and require immediate attention.'), $agingBuckets['over90Count'], Format::currency($agingBuckets['over90'])) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // Export and filter options
    echo '<div class="flex flex-wrap justify-between items-center mb-4 gap-4">';

    // Filter options form
    echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php" class="flex items-center gap-2">';
    echo '<input type="hidden" name="q" value="/modules/EnhancedFinance/finance_report_aging.php">';
    echo '<input type="hidden" name="gibbonSchoolYearID" value="' . $gibbonSchoolYearID . '">';

    echo '<label class="flex items-center gap-1">';
    $checked = ($groupByFamily === 'Y') ? 'checked' : '';
    echo '<input type="checkbox" name="groupByFamily" value="Y" onchange="this.form.submit()" ' . $checked . '>';
    echo '<span class="text-sm">' . __('Group by Family') . '</span>';
    echo '</label>';
    echo '</form>';

    // Export buttons
    echo '<div class="flex items-center gap-2">';

    // CSV Export button
    $csvExportUrl = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_report_aging.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '&export=csv';
    echo '<a href="' . $csvExportUrl . '" class="inline-flex items-center bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded transition">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>';
    echo __('Export to CSV');
    echo '</a>';

    // Excel Export button
    $excelExportUrl = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_report_export.php&type=aging&gibbonSchoolYearID=' . $gibbonSchoolYearID;
    echo '<a href="' . $excelExportUrl . '" class="inline-flex items-center bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded transition">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>';
    echo __('Export to Excel');
    echo '</a>';

    echo '</div>';

    echo '</div>';

    // Aging Detail Table
    if (!empty($agingData)) {

        // Build the data table
        $table = DataTable::create('agingReport');

        // Row modifier based on aging bucket
        $table->modifyRows(function ($invoice, $row) {
            $daysOverdue = (int) $invoice['daysOverdue'];
            if ($daysOverdue > 90) {
                $row->addClass('error');
            } else if ($daysOverdue > 60) {
                $row->addClass('warning');
            } else if ($daysOverdue > 30) {
                $row->addClass('warning');
            }
            return $row;
        });

        // Column: Invoice Number
        $table->addColumn('invoiceNumber', __('Invoice #'))
            ->format(function ($invoice) use ($session, $gibbonSchoolYearID) {
                return '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_invoice_view.php&gibbonEnhancedFinanceInvoiceID=' . $invoice['gibbonEnhancedFinanceInvoiceID'] . '&gibbonSchoolYearID=' . $gibbonSchoolYearID . '" class="text-blue-600 hover:underline">' . htmlspecialchars($invoice['invoiceNumber']) . '</a>';
            });

        // Column: Family / Child
        $table->addColumn('familyName', __('Family'))
            ->description(__('Child'))
            ->format(function ($invoice) {
                $output = '<b>' . htmlspecialchars($invoice['familyName']) . '</b>';
                $childName = Format::name('', $invoice['childPreferredName'], $invoice['childSurname'], 'Student', false);
                $output .= '<br/><span class="text-xs italic">' . $childName . '</span>';
                return $output;
            });

        // Column: Invoice Date
        $table->addColumn('invoiceDate', __('Invoice Date'))
            ->format(function ($invoice) {
                return Format::date($invoice['invoiceDate']);
            });

        // Column: Due Date
        $table->addColumn('dueDate', __('Due Date'))
            ->format(function ($invoice) {
                $daysOverdue = (int) $invoice['daysOverdue'];
                $output = Format::date($invoice['dueDate']);
                if ($daysOverdue > 0) {
                    $class = $daysOverdue > 90 ? 'text-red-700' : ($daysOverdue > 60 ? 'text-red-600' : ($daysOverdue > 30 ? 'text-orange-600' : 'text-yellow-600'));
                    $output .= '<br/><span class="text-xs font-semibold ' . $class . '">' . sprintf(__('%d days overdue'), $daysOverdue) . '</span>';
                }
                return $output;
            });

        // Column: Aging Bucket
        $table->addColumn('agingBucket', __('Aging'))
            ->format(function ($invoice) {
                $daysOverdue = (int) $invoice['daysOverdue'];

                if ($daysOverdue <= 0) {
                    return '<span class="inline-block px-2 py-1 text-xs rounded bg-green-100 text-green-800">' . __('Current') . '</span>';
                } else if ($daysOverdue <= 30) {
                    return '<span class="inline-block px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800">' . __('1-30 Days') . '</span>';
                } else if ($daysOverdue <= 60) {
                    return '<span class="inline-block px-2 py-1 text-xs rounded bg-orange-100 text-orange-800">' . __('31-60 Days') . '</span>';
                } else if ($daysOverdue <= 90) {
                    return '<span class="inline-block px-2 py-1 text-xs rounded bg-red-100 text-red-600">' . __('61-90 Days') . '</span>';
                } else {
                    return '<span class="inline-block px-2 py-1 text-xs rounded bg-red-600 text-white font-semibold">' . __('90+ Days') . '</span>';
                }
            });

        // Column: Balance
        $table->addColumn('balanceRemaining', __('Balance'))
            ->format(function ($invoice) {
                return '<span class="font-semibold text-red-600">' . Format::currency($invoice['balanceRemaining']) . '</span>';
            });

        // Column: Status
        $table->addColumn('status', __('Status'))
            ->format(function ($invoice) {
                $status = $invoice['status'];
                $class = $status === 'Partial' ? 'text-orange-600' : 'text-blue-600';
                return '<span class="' . $class . '">' . __($status) . '</span>';
            });

        // Actions column
        $table->addActionColumn()
            ->addParam('gibbonEnhancedFinanceInvoiceID')
            ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->format(function ($invoice, $actions) {
                // View action
                $actions->addAction('view', __('View'))
                    ->setURL('/modules/EnhancedFinance/finance_invoice_view.php');

                // Add payment action
                $actions->addAction('payment', __('Add Payment'))
                    ->setURL('/modules/EnhancedFinance/finance_payment_add.php')
                    ->setIcon('dollar');
            });

        echo $table->render($agingData);

    } else {
        echo '<div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">';
        echo '<div class="text-green-500 mb-3">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
        echo '</div>';
        echo '<p class="text-green-700 font-medium">' . __('No outstanding invoices found for this school year.') . '</p>';
        echo '<p class="text-green-600 text-sm mt-1">' . __('All invoices have been paid or there are no invoices to display.') . '</p>';
        echo '</div>';
    }

    // Aging by Family Summary (if requested)
    if ($groupByFamily === 'Y' && !empty($agingData)) {
        $familySummary = calculateFamilySummary($agingData);

        echo '<div class="mt-8">';
        echo '<h3 class="text-lg font-semibold mb-4">' . __('Summary by Family') . '</h3>';

        $familyTable = DataTable::create('familyAgingSummary');

        $familyTable->addColumn('familyName', __('Family'));

        $familyTable->addColumn('invoiceCount', __('Invoices'))
            ->format(function ($row) {
                return $row['invoiceCount'];
            });

        $familyTable->addColumn('current', __('Current'))
            ->format(function ($row) {
                return $row['current'] > 0 ? Format::currency($row['current']) : '-';
            });

        $familyTable->addColumn('days30', __('1-30 Days'))
            ->format(function ($row) {
                return $row['days30'] > 0 ? '<span class="text-yellow-600">' . Format::currency($row['days30']) . '</span>' : '-';
            });

        $familyTable->addColumn('days60', __('31-60 Days'))
            ->format(function ($row) {
                return $row['days60'] > 0 ? '<span class="text-orange-600">' . Format::currency($row['days60']) . '</span>' : '-';
            });

        $familyTable->addColumn('days90', __('61-90 Days'))
            ->format(function ($row) {
                return $row['days90'] > 0 ? '<span class="text-red-400">' . Format::currency($row['days90']) . '</span>' : '-';
            });

        $familyTable->addColumn('over90', __('90+ Days'))
            ->format(function ($row) {
                return $row['over90'] > 0 ? '<span class="text-red-600 font-semibold">' . Format::currency($row['over90']) . '</span>' : '-';
            });

        $familyTable->addColumn('total', __('Total'))
            ->format(function ($row) {
                return '<span class="font-semibold">' . Format::currency($row['total']) . '</span>';
            });

        echo $familyTable->render($familySummary);
        echo '</div>';
    }

    // Last updated timestamp
    echo '<div class="mt-4 text-center text-xs text-gray-400">';
    echo __('Report generated') . ' ' . Format::dateTime(date('Y-m-d H:i:s'));
    echo '</div>';
}

/**
 * Fetch aging data for all outstanding invoices.
 *
 * @param PDO $connection2 Database connection
 * @param int $gibbonSchoolYearID School year ID
 * @return array
 */
function fetchAgingData($connection2, $gibbonSchoolYearID)
{
    $today = date('Y-m-d');
    $sql = "SELECT
            i.gibbonEnhancedFinanceInvoiceID,
            i.invoiceNumber,
            i.invoiceDate,
            i.dueDate,
            i.totalAmount,
            i.paidAmount,
            (i.totalAmount - i.paidAmount) AS balanceRemaining,
            i.status,
            DATEDIFF(:today, i.dueDate) AS daysOverdue,
            p.surname AS childSurname,
            p.preferredName AS childPreferredName,
            f.gibbonFamilyID,
            f.name AS familyName
        FROM gibbonEnhancedFinanceInvoice i
        LEFT JOIN gibbonPerson p ON i.gibbonPersonID = p.gibbonPersonID
        LEFT JOIN gibbonFamily f ON i.gibbonFamilyID = f.gibbonFamilyID
        WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID
        AND i.status IN ('Issued', 'Partial')
        ORDER BY DATEDIFF(:today2, i.dueDate) DESC, i.dueDate ASC";

    try {
        $stmt = $connection2->prepare($sql);
        $stmt->execute([
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'today' => $today,
            'today2' => $today
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * Calculate aging bucket totals from invoice data.
 *
 * @param array $agingData Invoice data with daysOverdue
 * @return array Bucket totals
 */
function calculateAgingBuckets($agingData)
{
    $buckets = [
        'total' => 0.0,
        'totalCount' => 0,
        'current' => 0.0,
        'currentCount' => 0,
        'days30' => 0.0,
        'days30Count' => 0,
        'days60' => 0.0,
        'days60Count' => 0,
        'days90' => 0.0,
        'days90Count' => 0,
        'over90' => 0.0,
        'over90Count' => 0,
    ];

    foreach ($agingData as $invoice) {
        $balance = (float) $invoice['balanceRemaining'];
        $daysOverdue = (int) $invoice['daysOverdue'];

        $buckets['total'] += $balance;
        $buckets['totalCount']++;

        if ($daysOverdue <= 0) {
            $buckets['current'] += $balance;
            $buckets['currentCount']++;
        } else if ($daysOverdue <= 30) {
            $buckets['days30'] += $balance;
            $buckets['days30Count']++;
        } else if ($daysOverdue <= 60) {
            $buckets['days60'] += $balance;
            $buckets['days60Count']++;
        } else if ($daysOverdue <= 90) {
            $buckets['days90'] += $balance;
            $buckets['days90Count']++;
        } else {
            $buckets['over90'] += $balance;
            $buckets['over90Count']++;
        }
    }

    return $buckets;
}

/**
 * Calculate family-level aging summary.
 *
 * @param array $agingData Invoice data
 * @return array Family summary
 */
function calculateFamilySummary($agingData)
{
    $families = [];

    foreach ($agingData as $invoice) {
        $familyID = $invoice['gibbonFamilyID'];
        $familyName = $invoice['familyName'] ?? __('Unknown Family');
        $balance = (float) $invoice['balanceRemaining'];
        $daysOverdue = (int) $invoice['daysOverdue'];

        if (!isset($families[$familyID])) {
            $families[$familyID] = [
                'familyName' => $familyName,
                'invoiceCount' => 0,
                'current' => 0.0,
                'days30' => 0.0,
                'days60' => 0.0,
                'days90' => 0.0,
                'over90' => 0.0,
                'total' => 0.0,
            ];
        }

        $families[$familyID]['invoiceCount']++;
        $families[$familyID]['total'] += $balance;

        if ($daysOverdue <= 0) {
            $families[$familyID]['current'] += $balance;
        } else if ($daysOverdue <= 30) {
            $families[$familyID]['days30'] += $balance;
        } else if ($daysOverdue <= 60) {
            $families[$familyID]['days60'] += $balance;
        } else if ($daysOverdue <= 90) {
            $families[$familyID]['days90'] += $balance;
        } else {
            $families[$familyID]['over90'] += $balance;
        }
    }

    // Sort by total balance descending
    usort($families, function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });

    return $families;
}

/**
 * Export aging data to CSV.
 *
 * @param array $agingData Invoice data
 * @param array $agingBuckets Bucket totals
 * @param string $schoolYearName School year name
 * @param string $currency Currency code
 */
function exportAgingCsv($agingData, $agingBuckets, $schoolYearName, $currency)
{
    // Set headers for CSV download
    $fileName = 'aging_report_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Report header
    fputcsv($output, ['Accounts Receivable Aging Report']);
    fputcsv($output, ['School Year', $schoolYearName]);
    fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Currency', $currency]);
    fputcsv($output, []);

    // Summary section
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Bucket', 'Amount', 'Invoice Count']);
    fputcsv($output, ['Current (Not Yet Due)', number_format($agingBuckets['current'], 2), $agingBuckets['currentCount']]);
    fputcsv($output, ['1-30 Days Overdue', number_format($agingBuckets['days30'], 2), $agingBuckets['days30Count']]);
    fputcsv($output, ['31-60 Days Overdue', number_format($agingBuckets['days60'], 2), $agingBuckets['days60Count']]);
    fputcsv($output, ['61-90 Days Overdue', number_format($agingBuckets['days90'], 2), $agingBuckets['days90Count']]);
    fputcsv($output, ['Over 90 Days Overdue', number_format($agingBuckets['over90'], 2), $agingBuckets['over90Count']]);
    fputcsv($output, ['TOTAL OUTSTANDING', number_format($agingBuckets['total'], 2), $agingBuckets['totalCount']]);
    fputcsv($output, []);

    // Detail section
    fputcsv($output, ['DETAIL']);
    fputcsv($output, [
        'Invoice #',
        'Family',
        'Child',
        'Invoice Date',
        'Due Date',
        'Days Overdue',
        'Aging Bucket',
        'Total Amount',
        'Paid Amount',
        'Balance',
        'Status'
    ]);

    foreach ($agingData as $invoice) {
        $daysOverdue = (int) $invoice['daysOverdue'];

        // Determine aging bucket label
        if ($daysOverdue <= 0) {
            $bucket = 'Current';
        } else if ($daysOverdue <= 30) {
            $bucket = '1-30 Days';
        } else if ($daysOverdue <= 60) {
            $bucket = '31-60 Days';
        } else if ($daysOverdue <= 90) {
            $bucket = '61-90 Days';
        } else {
            $bucket = '90+ Days';
        }

        fputcsv($output, [
            $invoice['invoiceNumber'],
            $invoice['familyName'] ?? '',
            trim(($invoice['childPreferredName'] ?? '') . ' ' . ($invoice['childSurname'] ?? '')),
            $invoice['invoiceDate'],
            $invoice['dueDate'],
            max(0, $daysOverdue),
            $bucket,
            number_format((float) $invoice['totalAmount'], 2),
            number_format((float) $invoice['paidAmount'], 2),
            number_format((float) $invoice['balanceRemaining'], 2),
            $invoice['status']
        ]);
    }

    fclose($output);
}
