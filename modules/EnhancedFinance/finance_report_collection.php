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
 * Enhanced Finance Module - Collection Report
 *
 * Displays collection tracking report for overdue accounts.
 * Tracks notices sent, follow-ups, and write-off candidates.
 *
 * Features:
 * - Summary cards showing collection stages (First Notice, Second Notice, Final Notice, Write-off)
 * - Action tracking for each overdue invoice
 * - Export to CSV functionality
 * - Family grouping option
 * - Write-off candidate identification
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
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_report_collection.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $filterStage = $_GET['stage'] ?? '';
    $groupByFamily = $_GET['groupByFamily'] ?? 'N';
    $export = $_GET['export'] ?? '';

    // Breadcrumbs
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Collection Report'));

    // Get gateways and settings
    $invoiceGateway = $container->get(InvoiceGateway::class);
    $paymentGateway = $container->get(PaymentGateway::class);
    $settingGateway = $container->get(SettingGateway::class);
    $schoolYearGateway = $container->get(SchoolYearGateway::class);
    $familyGateway = $container->get(FamilyGateway::class);

    // Get school year information
    $schoolYear = $schoolYearGateway->getByID($gibbonSchoolYearID);
    $schoolYearName = $schoolYear['name'] ?? __('Current Year');

    // Get settings
    $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

    // Fetch all overdue invoices with collection data
    $collectionData = fetchCollectionData($connection2, $gibbonSchoolYearID);

    // Calculate collection stage totals
    $collectionSummary = calculateCollectionSummary($collectionData);

    // Calculate payment statistics for the year
    $paymentStats = calculatePaymentStats($connection2, $gibbonSchoolYearID);

    // Apply stage filter if selected
    if (!empty($filterStage)) {
        $collectionData = array_filter($collectionData, function ($invoice) use ($filterStage) {
            return getCollectionStage($invoice['daysOverdue']) === $filterStage;
        });
        $collectionData = array_values($collectionData); // Re-index array
    }

    // Handle CSV export
    if ($export === 'csv') {
        exportCollectionCsv($collectionData, $collectionSummary, $paymentStats, $schoolYearName, $currency);
        exit;
    }

    // School Year Navigator
    echo '<div class="flex justify-between items-center mb-4">';
    echo '<h2>' . __('Collection Report') . ' - ' . htmlspecialchars($schoolYearName) . '</h2>';

    // School year selection dropdown
    $schoolYears = $schoolYearGateway->selectSchoolYears()->fetchAll();
    echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php" class="flex items-center gap-2">';
    echo '<input type="hidden" name="q" value="/modules/EnhancedFinance/finance_report_collection.php">';
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
    echo __('This report tracks collection activities for overdue accounts. Use it to manage payment reminders, follow-ups, and identify accounts that may require write-off consideration.');
    echo '</p>';

    // Collection Summary KPIs
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';

    // Total In Collection
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-red-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Total In Collection') . '</p>';
    echo '<p class="text-2xl font-bold text-red-600">' . Format::currency($collectionSummary['totalOverdue']) . '</p>';
    echo '</div>';
    echo '<div class="text-red-500 bg-red-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d overdue invoice(s)'), $collectionSummary['overdueCount']) . '</p>';
    echo '</div>';

    // Collection Rate
    $collectionRate = $paymentStats['collectionRate'] ?? 0;
    $collectionRateColor = $collectionRate >= 90 ? 'green' : ($collectionRate >= 70 ? 'yellow' : 'red');
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-' . $collectionRateColor . '-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Collection Rate') . '</p>';
    echo '<p class="text-2xl font-bold text-' . $collectionRateColor . '-600">' . number_format($collectionRate, 1) . '%</p>';
    echo '</div>';
    echo '<div class="text-' . $collectionRateColor . '-500 bg-' . $collectionRateColor . '-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" /></svg>';
    echo '</div>';
    echo '</div>';
    // Progress bar
    echo '<div class="mt-3">';
    echo '<div class="w-full bg-gray-200 rounded-full h-2">';
    echo '<div class="bg-' . $collectionRateColor . '-500 h-2 rounded-full" style="width: ' . min($collectionRate, 100) . '%"></div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Average Days to Collect
    $avgDays = $paymentStats['avgDaysToCollect'] ?? 0;
    $avgDaysColor = $avgDays <= 30 ? 'green' : ($avgDays <= 60 ? 'yellow' : 'red');
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-' . $avgDaysColor . '-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Avg. Days to Collect') . '</p>';
    echo '<p class="text-2xl font-bold text-' . $avgDaysColor . '-600">' . number_format($avgDays, 0) . ' ' . __('days') . '</p>';
    echo '</div>';
    echo '<div class="text-' . $avgDaysColor . '-500 bg-' . $avgDaysColor . '-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-400 mt-2">' . __('From invoice date to payment') . '</p>';
    echo '</div>';

    // Write-off Candidates
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-purple-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Write-off Candidates') . '</p>';
    echo '<p class="text-2xl font-bold text-purple-600">' . Format::currency($collectionSummary['writeOffAmount']) . '</p>';
    echo '</div>';
    echo '<div class="text-purple-500 bg-purple-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d invoice(s) over 90 days'), $collectionSummary['writeOffCount']) . '</p>';
    echo '</div>';

    echo '</div>'; // End KPI grid

    // Collection Stage Cards
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Collection Stages') . '</h3>';
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';

    // First Notice (1-30 days)
    $firstNoticeUrl = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_report_collection.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '&stage=first_notice';
    $firstNoticeBg = $filterStage === 'first_notice' ? 'bg-yellow-100 border-2 border-yellow-400' : 'bg-white';
    echo '<a href="' . $firstNoticeUrl . '" class="block ' . $firstNoticeBg . ' rounded-lg shadow p-4 hover:shadow-lg transition">';
    echo '<div class="flex items-center mb-2">';
    echo '<div class="text-yellow-500 mr-2">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l7-4.666a2 2 0 012.22 0l7 4.666A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-1.14.76a2 2 0 01-2.22 0l-1.14-.76" /></svg>';
    echo '</div>';
    echo '<div>';
    echo '<h4 class="font-semibold text-yellow-700">' . __('First Notice') . '</h4>';
    echo '<p class="text-xs text-gray-500">' . __('1-30 days overdue') . '</p>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-2xl font-bold text-yellow-600">' . Format::currency($collectionSummary['firstNotice']) . '</p>';
    echo '<p class="text-sm text-gray-500">' . sprintf(__('%d invoice(s)'), $collectionSummary['firstNoticeCount']) . '</p>';
    echo '</a>';

    // Second Notice (31-60 days)
    $secondNoticeUrl = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_report_collection.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '&stage=second_notice';
    $secondNoticeBg = $filterStage === 'second_notice' ? 'bg-orange-100 border-2 border-orange-400' : 'bg-white';
    echo '<a href="' . $secondNoticeUrl . '" class="block ' . $secondNoticeBg . ' rounded-lg shadow p-4 hover:shadow-lg transition">';
    echo '<div class="flex items-center mb-2">';
    echo '<div class="text-orange-500 mr-2">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" /></svg>';
    echo '</div>';
    echo '<div>';
    echo '<h4 class="font-semibold text-orange-700">' . __('Second Notice') . '</h4>';
    echo '<p class="text-xs text-gray-500">' . __('31-60 days overdue') . '</p>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-2xl font-bold text-orange-600">' . Format::currency($collectionSummary['secondNotice']) . '</p>';
    echo '<p class="text-sm text-gray-500">' . sprintf(__('%d invoice(s)'), $collectionSummary['secondNoticeCount']) . '</p>';
    echo '</a>';

    // Final Notice (61-90 days)
    $finalNoticeUrl = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_report_collection.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '&stage=final_notice';
    $finalNoticeBg = $filterStage === 'final_notice' ? 'bg-red-100 border-2 border-red-400' : 'bg-white';
    echo '<a href="' . $finalNoticeUrl . '" class="block ' . $finalNoticeBg . ' rounded-lg shadow p-4 hover:shadow-lg transition">';
    echo '<div class="flex items-center mb-2">';
    echo '<div class="text-red-400 mr-2">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>';
    echo '</div>';
    echo '<div>';
    echo '<h4 class="font-semibold text-red-500">' . __('Final Notice') . '</h4>';
    echo '<p class="text-xs text-gray-500">' . __('61-90 days overdue') . '</p>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-2xl font-bold text-red-400">' . Format::currency($collectionSummary['finalNotice']) . '</p>';
    echo '<p class="text-sm text-gray-500">' . sprintf(__('%d invoice(s)'), $collectionSummary['finalNoticeCount']) . '</p>';
    echo '</a>';

    // Write-off Review (90+ days)
    $writeOffUrl = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_report_collection.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '&stage=write_off';
    $writeOffBg = $filterStage === 'write_off' ? 'bg-purple-100 border-2 border-purple-400' : 'bg-white';
    echo '<a href="' . $writeOffUrl . '" class="block ' . $writeOffBg . ' rounded-lg shadow p-4 hover:shadow-lg transition">';
    echo '<div class="flex items-center mb-2">';
    echo '<div class="text-purple-500 mr-2">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>';
    echo '</div>';
    echo '<div>';
    echo '<h4 class="font-semibold text-purple-700">' . __('Write-off Review') . '</h4>';
    echo '<p class="text-xs text-gray-500">' . __('Over 90 days overdue') . '</p>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-2xl font-bold text-purple-600">' . Format::currency($collectionSummary['writeOffAmount']) . '</p>';
    echo '<p class="text-sm text-gray-500">' . sprintf(__('%d invoice(s)'), $collectionSummary['writeOffCount']) . '</p>';
    echo '</a>';

    echo '</div>'; // End stage cards

    // Write-off Alert
    if ($collectionSummary['writeOffCount'] > 0) {
        echo '<div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">';
        echo '<div class="flex items-center">';
        echo '<div class="text-purple-500 mr-3">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>';
        echo '</div>';
        echo '<div class="flex-1">';
        echo '<h4 class="font-semibold text-purple-800">' . __('Write-off Consideration Required') . '</h4>';
        echo '<p class="text-purple-700">' . sprintf(__('%d invoice(s) totaling %s have been overdue for more than 90 days. Review these accounts for potential write-off.'), $collectionSummary['writeOffCount'], Format::currency($collectionSummary['writeOffAmount'])) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // Filter and Export options
    echo '<div class="flex flex-wrap justify-between items-center mb-4 gap-4">';

    // Clear filter if applied
    if (!empty($filterStage)) {
        echo '<div class="flex items-center gap-2">';
        echo '<span class="text-sm text-gray-600">' . __('Filtered by') . ': <strong>' . getStageLabel($filterStage) . '</strong></span>';
        $clearUrl = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_report_collection.php&gibbonSchoolYearID=' . $gibbonSchoolYearID;
        echo '<a href="' . $clearUrl . '" class="text-sm text-red-600 hover:underline">' . __('Clear Filter') . '</a>';
        echo '</div>';
    } else {
        // Group by family option
        echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php" class="flex items-center gap-2">';
        echo '<input type="hidden" name="q" value="/modules/EnhancedFinance/finance_report_collection.php">';
        echo '<input type="hidden" name="gibbonSchoolYearID" value="' . $gibbonSchoolYearID . '">';
        echo '<label class="flex items-center gap-1">';
        $checked = ($groupByFamily === 'Y') ? 'checked' : '';
        echo '<input type="checkbox" name="groupByFamily" value="Y" onchange="this.form.submit()" ' . $checked . '>';
        echo '<span class="text-sm">' . __('Group by Family') . '</span>';
        echo '</label>';
        echo '</form>';
    }

    // Export button
    $exportUrl = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_report_collection.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '&export=csv';
    if (!empty($filterStage)) {
        $exportUrl .= '&stage=' . urlencode($filterStage);
    }
    echo '<a href="' . $exportUrl . '" class="inline-flex items-center bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded transition">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>';
    echo __('Export to CSV');
    echo '</a>';

    echo '</div>';

    // Collection Tracking Table
    if (!empty($collectionData)) {

        // Build the data table
        $table = DataTable::create('collectionReport');

        // Row modifier based on collection stage
        $table->modifyRows(function ($invoice, $row) {
            $stage = getCollectionStage($invoice['daysOverdue']);
            if ($stage === 'write_off') {
                $row->addClass('error');
            } else if ($stage === 'final_notice') {
                $row->addClass('warning');
            } else if ($stage === 'second_notice') {
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

        // Column: Due Date
        $table->addColumn('dueDate', __('Due Date'))
            ->format(function ($invoice) {
                $daysOverdue = (int) $invoice['daysOverdue'];
                $output = Format::date($invoice['dueDate']);
                if ($daysOverdue > 0) {
                    $class = $daysOverdue > 90 ? 'text-purple-700' : ($daysOverdue > 60 ? 'text-red-600' : ($daysOverdue > 30 ? 'text-orange-600' : 'text-yellow-600'));
                    $output .= '<br/><span class="text-xs font-semibold ' . $class . '">' . sprintf(__('%d days overdue'), $daysOverdue) . '</span>';
                }
                return $output;
            });

        // Column: Collection Stage
        $table->addColumn('stage', __('Stage'))
            ->format(function ($invoice) {
                $stage = getCollectionStage($invoice['daysOverdue']);
                return renderStageBadge($stage);
            });

        // Column: Suggested Action
        $table->addColumn('action', __('Suggested Action'))
            ->format(function ($invoice) {
                $stage = getCollectionStage($invoice['daysOverdue']);
                return getSuggestedAction($stage);
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

        echo $table->render($collectionData);

    } else {
        echo '<div class="bg-green-50 border border-green-200 rounded-lg p-6 text-center">';
        echo '<div class="text-green-500 mb-3">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
        echo '</div>';
        if (!empty($filterStage)) {
            echo '<p class="text-green-700 font-medium">' . __('No invoices found for this collection stage.') . '</p>';
        } else {
            echo '<p class="text-green-700 font-medium">' . __('No overdue invoices found for this school year.') . '</p>';
        }
        echo '<p class="text-green-600 text-sm mt-1">' . __('All accounts are current or there are no invoices to collect.') . '</p>';
        echo '</div>';
    }

    // Collection by Family Summary (if requested)
    if ($groupByFamily === 'Y' && !empty($collectionData) && empty($filterStage)) {
        $familySummary = calculateFamilyCollectionSummary($collectionData);

        echo '<div class="mt-8">';
        echo '<h3 class="text-lg font-semibold mb-4">' . __('Collection Summary by Family') . '</h3>';

        $familyTable = DataTable::create('familyCollectionSummary');

        $familyTable->addColumn('familyName', __('Family'));

        $familyTable->addColumn('invoiceCount', __('Invoices'))
            ->format(function ($row) {
                return $row['invoiceCount'];
            });

        $familyTable->addColumn('firstNotice', __('First Notice'))
            ->format(function ($row) {
                return $row['firstNotice'] > 0 ? '<span class="text-yellow-600">' . Format::currency($row['firstNotice']) . '</span>' : '-';
            });

        $familyTable->addColumn('secondNotice', __('Second Notice'))
            ->format(function ($row) {
                return $row['secondNotice'] > 0 ? '<span class="text-orange-600">' . Format::currency($row['secondNotice']) . '</span>' : '-';
            });

        $familyTable->addColumn('finalNotice', __('Final Notice'))
            ->format(function ($row) {
                return $row['finalNotice'] > 0 ? '<span class="text-red-400">' . Format::currency($row['finalNotice']) . '</span>' : '-';
            });

        $familyTable->addColumn('writeOff', __('Write-off'))
            ->format(function ($row) {
                return $row['writeOff'] > 0 ? '<span class="text-purple-600 font-semibold">' . Format::currency($row['writeOff']) . '</span>' : '-';
            });

        $familyTable->addColumn('total', __('Total'))
            ->format(function ($row) {
                return '<span class="font-semibold">' . Format::currency($row['total']) . '</span>';
            });

        echo $familyTable->render($familySummary);
        echo '</div>';
    }

    // Collection Tips Section
    echo '<div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-6">';
    echo '<h4 class="font-semibold text-blue-800 mb-3">' . __('Collection Best Practices') . '</h4>';
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-blue-700">';
    echo '<div>';
    echo '<h5 class="font-medium mb-1">' . __('First Notice (1-30 days)') . '</h5>';
    echo '<p>' . __('Send a friendly payment reminder via email. Include invoice details and payment options.') . '</p>';
    echo '</div>';
    echo '<div>';
    echo '<h5 class="font-medium mb-1">' . __('Second Notice (31-60 days)') . '</h5>';
    echo '<p>' . __('Send a more urgent reminder. Consider a phone call to discuss payment arrangements.') . '</p>';
    echo '</div>';
    echo '<div>';
    echo '<h5 class="font-medium mb-1">' . __('Final Notice (61-90 days)') . '</h5>';
    echo '<p>' . __('Send formal written notice. Discuss potential consequences and offer payment plan options.') . '</p>';
    echo '</div>';
    echo '<div>';
    echo '<h5 class="font-medium mb-1">' . __('Write-off Review (90+ days)') . '</h5>';
    echo '<p>' . __('Review account for potential write-off. Document all collection efforts before proceeding.') . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Last updated timestamp
    echo '<div class="mt-4 text-center text-xs text-gray-400">';
    echo __('Report generated') . ' ' . Format::dateTime(date('Y-m-d H:i:s'));
    echo '</div>';
}

/**
 * Get collection stage based on days overdue.
 *
 * @param int $daysOverdue Days past due date
 * @return string Stage identifier
 */
function getCollectionStage($daysOverdue)
{
    $daysOverdue = (int) $daysOverdue;

    if ($daysOverdue <= 0) {
        return 'current';
    } else if ($daysOverdue <= 30) {
        return 'first_notice';
    } else if ($daysOverdue <= 60) {
        return 'second_notice';
    } else if ($daysOverdue <= 90) {
        return 'final_notice';
    } else {
        return 'write_off';
    }
}

/**
 * Get human-readable stage label.
 *
 * @param string $stage Stage identifier
 * @return string Localized label
 */
function getStageLabel($stage)
{
    $labels = [
        'current' => __('Current'),
        'first_notice' => __('First Notice'),
        'second_notice' => __('Second Notice'),
        'final_notice' => __('Final Notice'),
        'write_off' => __('Write-off Review'),
    ];

    return $labels[$stage] ?? __('Unknown');
}

/**
 * Render stage badge HTML.
 *
 * @param string $stage Stage identifier
 * @return string HTML badge
 */
function renderStageBadge($stage)
{
    $badges = [
        'current' => '<span class="inline-block px-2 py-1 text-xs rounded bg-green-100 text-green-800">' . __('Current') . '</span>',
        'first_notice' => '<span class="inline-block px-2 py-1 text-xs rounded bg-yellow-100 text-yellow-800">' . __('First Notice') . '</span>',
        'second_notice' => '<span class="inline-block px-2 py-1 text-xs rounded bg-orange-100 text-orange-800">' . __('Second Notice') . '</span>',
        'final_notice' => '<span class="inline-block px-2 py-1 text-xs rounded bg-red-100 text-red-600">' . __('Final Notice') . '</span>',
        'write_off' => '<span class="inline-block px-2 py-1 text-xs rounded bg-purple-600 text-white font-semibold">' . __('Write-off') . '</span>',
    ];

    return $badges[$stage] ?? '<span class="inline-block px-2 py-1 text-xs rounded bg-gray-100 text-gray-800">' . __('Unknown') . '</span>';
}

/**
 * Get suggested action text for a collection stage.
 *
 * @param string $stage Stage identifier
 * @return string Suggested action text
 */
function getSuggestedAction($stage)
{
    $actions = [
        'current' => '<span class="text-green-600">' . __('No action required') . '</span>',
        'first_notice' => '<span class="text-yellow-600">' . __('Send payment reminder') . '</span>',
        'second_notice' => '<span class="text-orange-600">' . __('Phone call / formal notice') . '</span>',
        'final_notice' => '<span class="text-red-500">' . __('Final warning / payment plan') . '</span>',
        'write_off' => '<span class="text-purple-600 font-semibold">' . __('Review for write-off') . '</span>',
    ];

    return $actions[$stage] ?? '<span class="text-gray-500">' . __('Unknown') . '</span>';
}

/**
 * Fetch collection data for all overdue invoices.
 *
 * @param PDO $connection2 Database connection
 * @param int $gibbonSchoolYearID School year ID
 * @return array
 */
function fetchCollectionData($connection2, $gibbonSchoolYearID)
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
        AND i.dueDate < :today2
        ORDER BY DATEDIFF(:today3, i.dueDate) DESC, f.name ASC";

    try {
        $stmt = $connection2->prepare($sql);
        $stmt->execute([
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'today' => $today,
            'today2' => $today,
            'today3' => $today
        ]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * Calculate collection summary totals.
 *
 * @param array $collectionData Invoice data
 * @return array Summary totals
 */
function calculateCollectionSummary($collectionData)
{
    $summary = [
        'totalOverdue' => 0.0,
        'overdueCount' => 0,
        'firstNotice' => 0.0,
        'firstNoticeCount' => 0,
        'secondNotice' => 0.0,
        'secondNoticeCount' => 0,
        'finalNotice' => 0.0,
        'finalNoticeCount' => 0,
        'writeOffAmount' => 0.0,
        'writeOffCount' => 0,
    ];

    foreach ($collectionData as $invoice) {
        $balance = (float) $invoice['balanceRemaining'];
        $stage = getCollectionStage($invoice['daysOverdue']);

        $summary['totalOverdue'] += $balance;
        $summary['overdueCount']++;

        switch ($stage) {
            case 'first_notice':
                $summary['firstNotice'] += $balance;
                $summary['firstNoticeCount']++;
                break;
            case 'second_notice':
                $summary['secondNotice'] += $balance;
                $summary['secondNoticeCount']++;
                break;
            case 'final_notice':
                $summary['finalNotice'] += $balance;
                $summary['finalNoticeCount']++;
                break;
            case 'write_off':
                $summary['writeOffAmount'] += $balance;
                $summary['writeOffCount']++;
                break;
        }
    }

    return $summary;
}

/**
 * Calculate payment statistics for collection rate and avg days to collect.
 *
 * @param PDO $connection2 Database connection
 * @param int $gibbonSchoolYearID School year ID
 * @return array Payment statistics
 */
function calculatePaymentStats($connection2, $gibbonSchoolYearID)
{
    // Get total invoiced vs paid
    $sql = "SELECT
            SUM(totalAmount) AS totalInvoiced,
            SUM(paidAmount) AS totalPaid
        FROM gibbonEnhancedFinanceInvoice
        WHERE gibbonSchoolYearID = :gibbonSchoolYearID
        AND status NOT IN ('Cancelled', 'Refunded')";

    try {
        $stmt = $connection2->prepare($sql);
        $stmt->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $totalInvoiced = (float) ($result['totalInvoiced'] ?? 0);
        $totalPaid = (float) ($result['totalPaid'] ?? 0);

        $collectionRate = $totalInvoiced > 0 ? ($totalPaid / $totalInvoiced) * 100 : 0;
    } catch (\PDOException $e) {
        $collectionRate = 0;
    }

    // Get average days to collect (from invoice date to first payment)
    $sql2 = "SELECT
            AVG(DATEDIFF(p.paymentDate, i.invoiceDate)) AS avgDays
        FROM gibbonEnhancedFinancePayment p
        INNER JOIN gibbonEnhancedFinanceInvoice i ON p.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
        WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID";

    try {
        $stmt2 = $connection2->prepare($sql2);
        $stmt2->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
        $result2 = $stmt2->fetch(\PDO::FETCH_ASSOC);

        $avgDaysToCollect = (float) ($result2['avgDays'] ?? 0);
    } catch (\PDOException $e) {
        $avgDaysToCollect = 0;
    }

    return [
        'collectionRate' => $collectionRate,
        'avgDaysToCollect' => $avgDaysToCollect,
    ];
}

/**
 * Calculate family-level collection summary.
 *
 * @param array $collectionData Invoice data
 * @return array Family summary
 */
function calculateFamilyCollectionSummary($collectionData)
{
    $families = [];

    foreach ($collectionData as $invoice) {
        $familyID = $invoice['gibbonFamilyID'];
        $familyName = $invoice['familyName'] ?? __('Unknown Family');
        $balance = (float) $invoice['balanceRemaining'];
        $stage = getCollectionStage($invoice['daysOverdue']);

        if (!isset($families[$familyID])) {
            $families[$familyID] = [
                'familyName' => $familyName,
                'invoiceCount' => 0,
                'firstNotice' => 0.0,
                'secondNotice' => 0.0,
                'finalNotice' => 0.0,
                'writeOff' => 0.0,
                'total' => 0.0,
            ];
        }

        $families[$familyID]['invoiceCount']++;
        $families[$familyID]['total'] += $balance;

        switch ($stage) {
            case 'first_notice':
                $families[$familyID]['firstNotice'] += $balance;
                break;
            case 'second_notice':
                $families[$familyID]['secondNotice'] += $balance;
                break;
            case 'final_notice':
                $families[$familyID]['finalNotice'] += $balance;
                break;
            case 'write_off':
                $families[$familyID]['writeOff'] += $balance;
                break;
        }
    }

    // Sort by total balance descending
    usort($families, function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });

    return $families;
}

/**
 * Export collection data to CSV.
 *
 * @param array $collectionData Invoice data
 * @param array $collectionSummary Summary totals
 * @param array $paymentStats Payment statistics
 * @param string $schoolYearName School year name
 * @param string $currency Currency code
 */
function exportCollectionCsv($collectionData, $collectionSummary, $paymentStats, $schoolYearName, $currency)
{
    // Set headers for CSV download
    $fileName = 'collection_report_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Report header
    fputcsv($output, ['Collection Report']);
    fputcsv($output, ['School Year', $schoolYearName]);
    fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Currency', $currency]);
    fputcsv($output, []);

    // KPI Summary
    fputcsv($output, ['KEY PERFORMANCE INDICATORS']);
    fputcsv($output, ['Collection Rate', number_format($paymentStats['collectionRate'], 1) . '%']);
    fputcsv($output, ['Average Days to Collect', number_format($paymentStats['avgDaysToCollect'], 0)]);
    fputcsv($output, []);

    // Collection Stage Summary
    fputcsv($output, ['COLLECTION STAGE SUMMARY']);
    fputcsv($output, ['Stage', 'Amount', 'Invoice Count']);
    fputcsv($output, ['First Notice (1-30 days)', number_format($collectionSummary['firstNotice'], 2), $collectionSummary['firstNoticeCount']]);
    fputcsv($output, ['Second Notice (31-60 days)', number_format($collectionSummary['secondNotice'], 2), $collectionSummary['secondNoticeCount']]);
    fputcsv($output, ['Final Notice (61-90 days)', number_format($collectionSummary['finalNotice'], 2), $collectionSummary['finalNoticeCount']]);
    fputcsv($output, ['Write-off Review (90+ days)', number_format($collectionSummary['writeOffAmount'], 2), $collectionSummary['writeOffCount']]);
    fputcsv($output, ['TOTAL IN COLLECTION', number_format($collectionSummary['totalOverdue'], 2), $collectionSummary['overdueCount']]);
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
        'Collection Stage',
        'Suggested Action',
        'Total Amount',
        'Paid Amount',
        'Balance',
        'Status'
    ]);

    foreach ($collectionData as $invoice) {
        $daysOverdue = (int) $invoice['daysOverdue'];
        $stage = getCollectionStage($daysOverdue);
        $stageLabelCsv = getStageLabel($stage);

        // Plain text action for CSV
        $actionLabels = [
            'current' => 'No action required',
            'first_notice' => 'Send payment reminder',
            'second_notice' => 'Phone call / formal notice',
            'final_notice' => 'Final warning / payment plan',
            'write_off' => 'Review for write-off',
        ];
        $actionLabel = $actionLabels[$stage] ?? 'Unknown';

        fputcsv($output, [
            $invoice['invoiceNumber'],
            $invoice['familyName'] ?? '',
            trim(($invoice['childPreferredName'] ?? '') . ' ' . ($invoice['childSurname'] ?? '')),
            $invoice['invoiceDate'],
            $invoice['dueDate'],
            max(0, $daysOverdue),
            $stageLabelCsv,
            $actionLabel,
            number_format((float) $invoice['totalAmount'], 2),
            number_format((float) $invoice['paidAmount'], 2),
            number_format((float) $invoice['balanceRemaining'], 2),
            $invoice['status']
        ]);
    }

    fclose($output);
}
