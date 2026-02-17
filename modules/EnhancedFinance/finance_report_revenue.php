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
 * Enhanced Finance Module - Revenue Report
 *
 * Displays monthly revenue report with YTD totals and historical comparison.
 * Shows invoiced amounts, collected amounts, and trends over time.
 *
 * Features:
 * - YTD revenue summary (invoiced vs collected)
 * - Monthly revenue breakdown table
 * - Historical comparison with previous school year
 * - Revenue by payment method breakdown
 * - Export to CSV functionality
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
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_report_revenue.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');
    $export = $_GET['export'] ?? '';

    // Breadcrumbs
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Revenue Report'));

    // Get gateways and settings
    $invoiceGateway = $container->get(InvoiceGateway::class);
    $paymentGateway = $container->get(PaymentGateway::class);
    $settingGateway = $container->get(SettingGateway::class);
    $schoolYearGateway = $container->get(SchoolYearGateway::class);

    // Get school year information
    $schoolYear = $schoolYearGateway->getByID($gibbonSchoolYearID);
    $schoolYearName = $schoolYear['name'] ?? __('Current Year');

    // Get previous school year for comparison
    $previousSchoolYear = getPreviousSchoolYear($connection2, $gibbonSchoolYearID);
    $previousSchoolYearID = $previousSchoolYear['gibbonSchoolYearID'] ?? null;
    $previousSchoolYearName = $previousSchoolYear['name'] ?? __('Previous Year');

    // Get settings
    $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

    // Fetch revenue data
    $ytdSummary = fetchYTDSummary($connection2, $gibbonSchoolYearID);
    $monthlyRevenue = fetchMonthlyRevenue($connection2, $gibbonSchoolYearID);
    $paymentMethodBreakdown = fetchPaymentMethodBreakdown($connection2, $gibbonSchoolYearID);

    // Fetch previous year data for comparison
    $previousYtdSummary = $previousSchoolYearID ? fetchYTDSummary($connection2, $previousSchoolYearID) : null;
    $previousMonthlyRevenue = $previousSchoolYearID ? fetchMonthlyRevenue($connection2, $previousSchoolYearID) : [];

    // Calculate comparison metrics
    $comparison = calculateComparison($ytdSummary, $previousYtdSummary);

    // Handle CSV export
    if ($export === 'csv') {
        exportRevenueCsv($ytdSummary, $monthlyRevenue, $paymentMethodBreakdown, $comparison, $schoolYearName, $previousSchoolYearName, $currency);
        exit;
    }

    // School Year Navigator
    echo '<div class="flex justify-between items-center mb-4">';
    echo '<h2>' . __('Revenue Report') . ' - ' . htmlspecialchars($schoolYearName) . '</h2>';

    // School year selection dropdown
    $schoolYears = $schoolYearGateway->selectSchoolYears()->fetchAll();
    echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php" class="flex items-center gap-2">';
    echo '<input type="hidden" name="q" value="/modules/EnhancedFinance/finance_report_revenue.php">';
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
    echo __('This report shows revenue performance with month-by-month breakdown and year-over-year comparison. Use it to track invoicing trends and collection efficiency.');
    echo '</p>';

    // YTD Summary KPIs
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';

    // Total Invoiced YTD
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Total Invoiced YTD') . '</p>';
    echo '<p class="text-2xl font-bold text-blue-600">' . Format::currency($ytdSummary['totalInvoiced']) . '</p>';
    echo '</div>';
    echo '<div class="text-blue-500 bg-blue-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>';
    echo '</div>';
    echo '</div>';
    if ($comparison['invoicedChange'] !== null) {
        $changeClass = $comparison['invoicedChange'] >= 0 ? 'text-green-600' : 'text-red-600';
        $changeIcon = $comparison['invoicedChange'] >= 0 ? '↑' : '↓';
        echo '<p class="text-xs ' . $changeClass . ' mt-2">' . $changeIcon . ' ' . number_format(abs($comparison['invoicedChange']), 1) . '% ' . __('vs previous year') . '</p>';
    } else {
        echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d invoices'), $ytdSummary['invoiceCount']) . '</p>';
    }
    echo '</div>';

    // Total Collected YTD
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Total Collected YTD') . '</p>';
    echo '<p class="text-2xl font-bold text-green-600">' . Format::currency($ytdSummary['totalCollected']) . '</p>';
    echo '</div>';
    echo '<div class="text-green-500 bg-green-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
    echo '</div>';
    echo '</div>';
    if ($comparison['collectedChange'] !== null) {
        $changeClass = $comparison['collectedChange'] >= 0 ? 'text-green-600' : 'text-red-600';
        $changeIcon = $comparison['collectedChange'] >= 0 ? '↑' : '↓';
        echo '<p class="text-xs ' . $changeClass . ' mt-2">' . $changeIcon . ' ' . number_format(abs($comparison['collectedChange']), 1) . '% ' . __('vs previous year') . '</p>';
    } else {
        echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d payments'), $ytdSummary['paymentCount']) . '</p>';
    }
    echo '</div>';

    // Outstanding Balance
    $outstandingAmount = $ytdSummary['totalInvoiced'] - $ytdSummary['totalCollected'];
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-orange-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Outstanding') . '</p>';
    echo '<p class="text-2xl font-bold text-orange-600">' . Format::currency($outstandingAmount) . '</p>';
    echo '</div>';
    echo '<div class="text-orange-500 bg-orange-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d invoices outstanding'), $ytdSummary['outstandingCount']) . '</p>';
    echo '</div>';

    // Collection Rate
    $collectionRate = $ytdSummary['totalInvoiced'] > 0
        ? round(($ytdSummary['totalCollected'] / $ytdSummary['totalInvoiced']) * 100, 1)
        : 0;
    $collectionRateColor = $collectionRate >= 90 ? 'green' : ($collectionRate >= 70 ? 'yellow' : 'red');
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-' . $collectionRateColor . '-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Collection Rate') . '</p>';
    echo '<p class="text-2xl font-bold text-' . $collectionRateColor . '-600">' . $collectionRate . '%</p>';
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

    echo '</div>'; // End KPI grid

    // Export buttons
    echo '<div class="flex justify-end mb-4 gap-2">';

    // CSV Export button
    $csvExportUrl = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_report_revenue.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '&export=csv';
    echo '<a href="' . $csvExportUrl . '" class="inline-flex items-center bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded transition">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>';
    echo __('Export to CSV');
    echo '</a>';

    // Excel Export button
    $excelExportUrl = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_report_export.php&type=revenue&gibbonSchoolYearID=' . $gibbonSchoolYearID;
    echo '<a href="' . $excelExportUrl . '" class="inline-flex items-center bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded transition">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>';
    echo __('Export to Excel');
    echo '</a>';

    echo '</div>';

    // Monthly Revenue Table with Historical Comparison
    echo '<div class="bg-white rounded-lg shadow mb-6">';
    echo '<div class="px-5 py-4 border-b">';
    echo '<h3 class="text-lg font-semibold">' . __('Monthly Revenue Breakdown') . '</h3>';
    echo '</div>';
    echo '<div class="overflow-x-auto">';

    if (!empty($monthlyRevenue)) {

        // Prepare monthly comparison data
        $comparisonData = prepareMonthlyComparison($monthlyRevenue, $previousMonthlyRevenue);

        // Build the data table
        $table = DataTable::create('monthlyRevenue');

        // Column: Month
        $table->addColumn('monthName', __('Month'));

        // Column: Invoiced (Current Year)
        $table->addColumn('invoiced', __('Invoiced'))
            ->format(function ($row) {
                return Format::currency($row['invoiced']);
            });

        // Column: Collected (Current Year)
        $table->addColumn('collected', __('Collected'))
            ->format(function ($row) {
                return '<span class="text-green-600 font-medium">' . Format::currency($row['collected']) . '</span>';
            });

        // Column: Collection Rate
        $table->addColumn('rate', __('Rate'))
            ->format(function ($row) {
                $rate = $row['invoiced'] > 0 ? round(($row['collected'] / $row['invoiced']) * 100, 1) : 0;
                $rateClass = $rate >= 90 ? 'text-green-600' : ($rate >= 70 ? 'text-yellow-600' : 'text-red-600');
                return '<span class="' . $rateClass . ' font-medium">' . $rate . '%</span>';
            });

        // Column: Previous Year (if available)
        if (!empty($previousMonthlyRevenue)) {
            $table->addColumn('prevCollected', __('Prev Year'))
                ->description($previousSchoolYearName)
                ->format(function ($row) {
                    if ($row['prevCollected'] > 0) {
                        return '<span class="text-gray-500">' . Format::currency($row['prevCollected']) . '</span>';
                    }
                    return '<span class="text-gray-300">-</span>';
                });

            // Column: Change %
            $table->addColumn('change', __('Change'))
                ->format(function ($row) {
                    if ($row['prevCollected'] > 0) {
                        $change = (($row['collected'] - $row['prevCollected']) / $row['prevCollected']) * 100;
                        $changeClass = $change >= 0 ? 'text-green-600' : 'text-red-600';
                        $changeIcon = $change >= 0 ? '↑' : '↓';
                        return '<span class="' . $changeClass . ' font-medium">' . $changeIcon . ' ' . number_format(abs($change), 1) . '%</span>';
                    }
                    return '<span class="text-gray-300">-</span>';
                });
        }

        // Column: Invoice Count
        $table->addColumn('invoiceCount', __('Invoices'))
            ->format(function ($row) {
                return $row['invoiceCount'];
            });

        echo $table->render($comparisonData);

        // YTD Total Row
        echo '<div class="px-5 py-3 bg-gray-100 border-t font-semibold">';
        echo '<div class="grid grid-cols-6 gap-4">';
        echo '<div>' . __('YTD Total') . '</div>';
        echo '<div>' . Format::currency($ytdSummary['totalInvoiced']) . '</div>';
        echo '<div class="text-green-600">' . Format::currency($ytdSummary['totalCollected']) . '</div>';
        echo '<div class="' . ($collectionRate >= 90 ? 'text-green-600' : ($collectionRate >= 70 ? 'text-yellow-600' : 'text-red-600')) . '">' . $collectionRate . '%</div>';
        if (!empty($previousMonthlyRevenue)) {
            echo '<div class="text-gray-500">' . Format::currency($previousYtdSummary['totalCollected'] ?? 0) . '</div>';
            if ($comparison['collectedChange'] !== null) {
                $changeClass = $comparison['collectedChange'] >= 0 ? 'text-green-600' : 'text-red-600';
                $changeIcon = $comparison['collectedChange'] >= 0 ? '↑' : '↓';
                echo '<div class="' . $changeClass . '">' . $changeIcon . ' ' . number_format(abs($comparison['collectedChange']), 1) . '%</div>';
            } else {
                echo '<div>-</div>';
            }
        }
        echo '</div>';
        echo '</div>';

    } else {
        echo '<div class="p-6 text-center text-gray-500">';
        echo '<p>' . __('No revenue data available for this school year.') . '</p>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';

    // Two-column layout for additional info
    echo '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">';

    // Payment Method Breakdown
    echo '<div class="bg-white rounded-lg shadow">';
    echo '<div class="px-5 py-4 border-b">';
    echo '<h3 class="text-lg font-semibold">' . __('Revenue by Payment Method') . '</h3>';
    echo '</div>';
    echo '<div class="p-5">';

    if (!empty($paymentMethodBreakdown)) {
        $totalPayments = array_sum(array_column($paymentMethodBreakdown, 'totalAmount'));
        echo '<div class="space-y-3">';
        foreach ($paymentMethodBreakdown as $method) {
            $percentage = $totalPayments > 0 ? round(($method['totalAmount'] / $totalPayments) * 100, 1) : 0;
            $methodIcons = [
                'Cash' => '💵',
                'Cheque' => '📄',
                'ETransfer' => '💸',
                'CreditCard' => '💳',
                'DebitCard' => '💳',
                'Other' => '📋',
            ];
            $icon = $methodIcons[$method['method']] ?? '📋';

            echo '<div>';
            echo '<div class="flex justify-between items-center mb-1">';
            echo '<span class="font-medium">' . $icon . ' ' . __($method['method']) . '</span>';
            echo '<span class="text-sm text-gray-600">' . Format::currency($method['totalAmount']) . ' (' . $method['paymentCount'] . ')</span>';
            echo '</div>';
            echo '<div class="w-full bg-gray-200 rounded-full h-2">';
            echo '<div class="bg-green-500 h-2 rounded-full" style="width: ' . $percentage . '%"></div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="text-center py-8 text-gray-500">';
        echo '<p>' . __('No payment data available.') . '</p>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';

    // Revenue Trend Visualization
    echo '<div class="bg-white rounded-lg shadow">';
    echo '<div class="px-5 py-4 border-b">';
    echo '<h3 class="text-lg font-semibold">' . __('Monthly Revenue Trend') . '</h3>';
    echo '</div>';
    echo '<div class="p-5">';

    if (!empty($monthlyRevenue)) {
        // Find max for scaling
        $maxRevenue = max(array_column($monthlyRevenue, 'collected'));
        $maxRevenue = max($maxRevenue, 1); // Prevent division by zero

        echo '<div class="flex items-end justify-between space-x-2" style="height: 200px;">';
        foreach ($monthlyRevenue as $month) {
            $height = $maxRevenue > 0 ? round(($month['collected'] / $maxRevenue) * 100) : 0;
            $monthName = date('M', mktime(0, 0, 0, $month['month'], 1));

            echo '<div class="flex-1 flex flex-col items-center">';
            echo '<div class="w-full bg-green-500 hover:bg-green-600 transition rounded-t cursor-pointer" style="height: ' . max($height, 2) . '%" title="' . Format::currency($month['collected']) . '"></div>';
            echo '<div class="text-xs text-gray-500 mt-1 text-center">' . $monthName . '</div>';
            echo '</div>';
        }
        echo '</div>';

        // Legend
        echo '<div class="mt-4 flex justify-between text-sm text-gray-500">';
        echo '<span>' . __('Total collected this year') . ': ' . Format::currency($ytdSummary['totalCollected']) . '</span>';
        echo '<span>' . sprintf(__('%d payments'), $ytdSummary['paymentCount']) . '</span>';
        echo '</div>';
    } else {
        echo '<div class="text-center py-8 text-gray-500">';
        echo '<p>' . __('No revenue data available for visualization.') . '</p>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';

    echo '</div>'; // End two-column grid

    // Year-over-Year Comparison Summary (if previous year data available)
    if ($previousYtdSummary && $previousYtdSummary['totalInvoiced'] > 0) {
        echo '<div class="bg-white rounded-lg shadow mb-6">';
        echo '<div class="px-5 py-4 border-b">';
        echo '<h3 class="text-lg font-semibold">' . __('Year-over-Year Comparison') . '</h3>';
        echo '</div>';
        echo '<div class="p-5">';

        echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-6">';

        // Invoiced Comparison
        $invoicedChange = $comparison['invoicedChange'];
        $invoicedClass = $invoicedChange >= 0 ? 'text-green-600' : 'text-red-600';
        $invoicedIcon = $invoicedChange >= 0 ? '↑' : '↓';
        echo '<div class="text-center p-4 bg-gray-50 rounded-lg">';
        echo '<p class="text-sm text-gray-500 mb-2">' . __('Total Invoiced') . '</p>';
        echo '<div class="flex justify-center items-center gap-4">';
        echo '<div>';
        echo '<p class="text-xs text-gray-400">' . htmlspecialchars($previousSchoolYearName) . '</p>';
        echo '<p class="text-lg font-semibold text-gray-500">' . Format::currency($previousYtdSummary['totalInvoiced']) . '</p>';
        echo '</div>';
        echo '<div class="text-2xl ' . $invoicedClass . '">' . $invoicedIcon . '</div>';
        echo '<div>';
        echo '<p class="text-xs text-gray-400">' . htmlspecialchars($schoolYearName) . '</p>';
        echo '<p class="text-lg font-semibold text-blue-600">' . Format::currency($ytdSummary['totalInvoiced']) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<p class="mt-2 ' . $invoicedClass . ' font-medium">' . number_format(abs($invoicedChange), 1) . '% ' . ($invoicedChange >= 0 ? __('increase') : __('decrease')) . '</p>';
        echo '</div>';

        // Collected Comparison
        $collectedChange = $comparison['collectedChange'];
        $collectedClass = $collectedChange >= 0 ? 'text-green-600' : 'text-red-600';
        $collectedIcon = $collectedChange >= 0 ? '↑' : '↓';
        echo '<div class="text-center p-4 bg-gray-50 rounded-lg">';
        echo '<p class="text-sm text-gray-500 mb-2">' . __('Total Collected') . '</p>';
        echo '<div class="flex justify-center items-center gap-4">';
        echo '<div>';
        echo '<p class="text-xs text-gray-400">' . htmlspecialchars($previousSchoolYearName) . '</p>';
        echo '<p class="text-lg font-semibold text-gray-500">' . Format::currency($previousYtdSummary['totalCollected']) . '</p>';
        echo '</div>';
        echo '<div class="text-2xl ' . $collectedClass . '">' . $collectedIcon . '</div>';
        echo '<div>';
        echo '<p class="text-xs text-gray-400">' . htmlspecialchars($schoolYearName) . '</p>';
        echo '<p class="text-lg font-semibold text-green-600">' . Format::currency($ytdSummary['totalCollected']) . '</p>';
        echo '</div>';
        echo '</div>';
        echo '<p class="mt-2 ' . $collectedClass . ' font-medium">' . number_format(abs($collectedChange), 1) . '% ' . ($collectedChange >= 0 ? __('increase') : __('decrease')) . '</p>';
        echo '</div>';

        // Collection Rate Comparison
        $prevCollectionRate = $previousYtdSummary['totalInvoiced'] > 0
            ? round(($previousYtdSummary['totalCollected'] / $previousYtdSummary['totalInvoiced']) * 100, 1)
            : 0;
        $rateChange = $collectionRate - $prevCollectionRate;
        $rateClass = $rateChange >= 0 ? 'text-green-600' : 'text-red-600';
        $rateIcon = $rateChange >= 0 ? '↑' : '↓';
        echo '<div class="text-center p-4 bg-gray-50 rounded-lg">';
        echo '<p class="text-sm text-gray-500 mb-2">' . __('Collection Rate') . '</p>';
        echo '<div class="flex justify-center items-center gap-4">';
        echo '<div>';
        echo '<p class="text-xs text-gray-400">' . htmlspecialchars($previousSchoolYearName) . '</p>';
        echo '<p class="text-lg font-semibold text-gray-500">' . $prevCollectionRate . '%</p>';
        echo '</div>';
        echo '<div class="text-2xl ' . $rateClass . '">' . $rateIcon . '</div>';
        echo '<div>';
        echo '<p class="text-xs text-gray-400">' . htmlspecialchars($schoolYearName) . '</p>';
        echo '<p class="text-lg font-semibold text-' . $collectionRateColor . '-600">' . $collectionRate . '%</p>';
        echo '</div>';
        echo '</div>';
        echo '<p class="mt-2 ' . $rateClass . ' font-medium">' . number_format(abs($rateChange), 1) . ' ' . __('percentage points') . '</p>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
        echo '</div>';
    }

    // Last updated timestamp
    echo '<div class="mt-4 text-center text-xs text-gray-400">';
    echo __('Report generated') . ' ' . Format::dateTime(date('Y-m-d H:i:s'));
    echo '</div>';
}

/**
 * Get previous school year information.
 *
 * @param PDO $connection2 Database connection
 * @param int $currentSchoolYearID Current school year ID
 * @return array|null Previous school year data
 */
function getPreviousSchoolYear($connection2, $currentSchoolYearID)
{
    $sql = "SELECT y2.gibbonSchoolYearID, y2.name
            FROM gibbonSchoolYear y1
            INNER JOIN gibbonSchoolYear y2 ON y2.sequenceNumber = y1.sequenceNumber - 1
            WHERE y1.gibbonSchoolYearID = :gibbonSchoolYearID";

    try {
        $stmt = $connection2->prepare($sql);
        $stmt->execute(['gibbonSchoolYearID' => $currentSchoolYearID]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    } catch (\PDOException $e) {
        return null;
    }
}

/**
 * Fetch YTD summary data.
 *
 * @param PDO $connection2 Database connection
 * @param int $gibbonSchoolYearID School year ID
 * @return array YTD summary
 */
function fetchYTDSummary($connection2, $gibbonSchoolYearID)
{
    // Get invoice totals
    $sql = "SELECT
            COALESCE(SUM(totalAmount), 0) AS totalInvoiced,
            COALESCE(SUM(paidAmount), 0) AS totalCollected,
            COUNT(*) AS invoiceCount,
            SUM(CASE WHEN status IN ('Issued', 'Partial') THEN 1 ELSE 0 END) AS outstandingCount
        FROM gibbonEnhancedFinanceInvoice
        WHERE gibbonSchoolYearID = :gibbonSchoolYearID
        AND status NOT IN ('Cancelled', 'Refunded')";

    try {
        $stmt = $connection2->prepare($sql);
        $stmt->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
        $invoiceData = $stmt->fetch(\PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        $invoiceData = [
            'totalInvoiced' => 0,
            'totalCollected' => 0,
            'invoiceCount' => 0,
            'outstandingCount' => 0,
        ];
    }

    // Get payment count
    $sql2 = "SELECT COUNT(*) AS paymentCount
        FROM gibbonEnhancedFinancePayment p
        INNER JOIN gibbonEnhancedFinanceInvoice i ON p.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
        WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID";

    try {
        $stmt2 = $connection2->prepare($sql2);
        $stmt2->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
        $paymentData = $stmt2->fetch(\PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        $paymentData = ['paymentCount' => 0];
    }

    return [
        'totalInvoiced' => (float) ($invoiceData['totalInvoiced'] ?? 0),
        'totalCollected' => (float) ($invoiceData['totalCollected'] ?? 0),
        'invoiceCount' => (int) ($invoiceData['invoiceCount'] ?? 0),
        'outstandingCount' => (int) ($invoiceData['outstandingCount'] ?? 0),
        'paymentCount' => (int) ($paymentData['paymentCount'] ?? 0),
    ];
}

/**
 * Fetch monthly revenue breakdown.
 *
 * @param PDO $connection2 Database connection
 * @param int $gibbonSchoolYearID School year ID
 * @return array Monthly revenue data
 */
function fetchMonthlyRevenue($connection2, $gibbonSchoolYearID)
{
    $sql = "SELECT
            MONTH(p.paymentDate) AS month,
            YEAR(p.paymentDate) AS year,
            COALESCE(SUM(p.amount), 0) AS collected,
            COUNT(p.gibbonEnhancedFinancePaymentID) AS paymentCount
        FROM gibbonEnhancedFinancePayment p
        INNER JOIN gibbonEnhancedFinanceInvoice i ON p.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
        WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID
        GROUP BY YEAR(p.paymentDate), MONTH(p.paymentDate)
        ORDER BY YEAR(p.paymentDate), MONTH(p.paymentDate)";

    // Also get invoiced amounts by month
    $sql2 = "SELECT
            MONTH(invoiceDate) AS month,
            YEAR(invoiceDate) AS year,
            COALESCE(SUM(totalAmount), 0) AS invoiced,
            COUNT(*) AS invoiceCount
        FROM gibbonEnhancedFinanceInvoice
        WHERE gibbonSchoolYearID = :gibbonSchoolYearID
        AND status NOT IN ('Cancelled', 'Refunded')
        GROUP BY YEAR(invoiceDate), MONTH(invoiceDate)
        ORDER BY YEAR(invoiceDate), MONTH(invoiceDate)";

    try {
        // Get collected by month
        $stmt = $connection2->prepare($sql);
        $stmt->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
        $collectedData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Get invoiced by month
        $stmt2 = $connection2->prepare($sql2);
        $stmt2->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
        $invoicedData = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        // Merge data by month
        $monthlyData = [];

        // Index collected by month-year
        $collectedByMonth = [];
        foreach ($collectedData as $row) {
            $key = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
            $collectedByMonth[$key] = $row;
        }

        // Build combined data from invoiced
        foreach ($invoicedData as $row) {
            $key = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
            $monthlyData[$key] = [
                'month' => (int) $row['month'],
                'year' => (int) $row['year'],
                'monthName' => date('F Y', mktime(0, 0, 0, $row['month'], 1, $row['year'])),
                'invoiced' => (float) $row['invoiced'],
                'invoiceCount' => (int) $row['invoiceCount'],
                'collected' => isset($collectedByMonth[$key]) ? (float) $collectedByMonth[$key]['collected'] : 0,
                'paymentCount' => isset($collectedByMonth[$key]) ? (int) $collectedByMonth[$key]['paymentCount'] : 0,
            ];
        }

        // Add any months with collections but no invoices
        foreach ($collectedByMonth as $key => $row) {
            if (!isset($monthlyData[$key])) {
                $monthlyData[$key] = [
                    'month' => (int) $row['month'],
                    'year' => (int) $row['year'],
                    'monthName' => date('F Y', mktime(0, 0, 0, $row['month'], 1, $row['year'])),
                    'invoiced' => 0,
                    'invoiceCount' => 0,
                    'collected' => (float) $row['collected'],
                    'paymentCount' => (int) $row['paymentCount'],
                ];
            }
        }

        // Sort by date
        ksort($monthlyData);

        return array_values($monthlyData);

    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * Fetch payment method breakdown.
 *
 * @param PDO $connection2 Database connection
 * @param int $gibbonSchoolYearID School year ID
 * @return array Payment method data
 */
function fetchPaymentMethodBreakdown($connection2, $gibbonSchoolYearID)
{
    $sql = "SELECT
            p.method,
            COALESCE(SUM(p.amount), 0) AS totalAmount,
            COUNT(p.gibbonEnhancedFinancePaymentID) AS paymentCount
        FROM gibbonEnhancedFinancePayment p
        INNER JOIN gibbonEnhancedFinanceInvoice i ON p.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
        WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID
        GROUP BY p.method
        ORDER BY SUM(p.amount) DESC";

    try {
        $stmt = $connection2->prepare($sql);
        $stmt->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        return [];
    }
}

/**
 * Calculate year-over-year comparison metrics.
 *
 * @param array $currentYtd Current year YTD summary
 * @param array|null $previousYtd Previous year YTD summary
 * @return array Comparison metrics
 */
function calculateComparison($currentYtd, $previousYtd)
{
    if (!$previousYtd || $previousYtd['totalInvoiced'] == 0) {
        return [
            'invoicedChange' => null,
            'collectedChange' => null,
        ];
    }

    $invoicedChange = (($currentYtd['totalInvoiced'] - $previousYtd['totalInvoiced']) / $previousYtd['totalInvoiced']) * 100;
    $collectedChange = $previousYtd['totalCollected'] > 0
        ? (($currentYtd['totalCollected'] - $previousYtd['totalCollected']) / $previousYtd['totalCollected']) * 100
        : null;

    return [
        'invoicedChange' => $invoicedChange,
        'collectedChange' => $collectedChange,
    ];
}

/**
 * Prepare monthly comparison data for the table.
 *
 * @param array $currentMonthly Current year monthly data
 * @param array $previousMonthly Previous year monthly data
 * @return array Combined comparison data
 */
function prepareMonthlyComparison($currentMonthly, $previousMonthly)
{
    // Index previous year by month number
    $prevByMonth = [];
    foreach ($previousMonthly as $row) {
        $prevByMonth[$row['month']] = $row;
    }

    // Add previous year data to current
    foreach ($currentMonthly as &$row) {
        $month = $row['month'];
        $row['prevCollected'] = isset($prevByMonth[$month]) ? (float) $prevByMonth[$month]['collected'] : 0;
        $row['prevInvoiced'] = isset($prevByMonth[$month]) ? (float) $prevByMonth[$month]['invoiced'] : 0;
    }

    return $currentMonthly;
}

/**
 * Export revenue data to CSV.
 *
 * @param array $ytdSummary YTD summary data
 * @param array $monthlyRevenue Monthly revenue data
 * @param array $paymentMethodBreakdown Payment method data
 * @param array $comparison Comparison metrics
 * @param string $schoolYearName Current school year name
 * @param string $previousSchoolYearName Previous school year name
 * @param string $currency Currency code
 */
function exportRevenueCsv($ytdSummary, $monthlyRevenue, $paymentMethodBreakdown, $comparison, $schoolYearName, $previousSchoolYearName, $currency)
{
    // Set headers for CSV download
    $fileName = 'revenue_report_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Report header
    fputcsv($output, ['Revenue Report']);
    fputcsv($output, ['School Year', $schoolYearName]);
    fputcsv($output, ['Generated', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Currency', $currency]);
    fputcsv($output, []);

    // YTD Summary
    fputcsv($output, ['YTD SUMMARY']);
    fputcsv($output, ['Metric', 'Amount']);
    fputcsv($output, ['Total Invoiced', number_format($ytdSummary['totalInvoiced'], 2)]);
    fputcsv($output, ['Total Collected', number_format($ytdSummary['totalCollected'], 2)]);
    fputcsv($output, ['Outstanding', number_format($ytdSummary['totalInvoiced'] - $ytdSummary['totalCollected'], 2)]);
    $collectionRate = $ytdSummary['totalInvoiced'] > 0
        ? round(($ytdSummary['totalCollected'] / $ytdSummary['totalInvoiced']) * 100, 1)
        : 0;
    fputcsv($output, ['Collection Rate', $collectionRate . '%']);
    fputcsv($output, ['Invoice Count', $ytdSummary['invoiceCount']]);
    fputcsv($output, ['Payment Count', $ytdSummary['paymentCount']]);
    fputcsv($output, []);

    // Year-over-Year Comparison
    if ($comparison['invoicedChange'] !== null) {
        fputcsv($output, ['YEAR-OVER-YEAR COMPARISON']);
        fputcsv($output, ['Metric', 'Change %']);
        fputcsv($output, ['Invoiced Change', number_format($comparison['invoicedChange'], 1) . '%']);
        if ($comparison['collectedChange'] !== null) {
            fputcsv($output, ['Collected Change', number_format($comparison['collectedChange'], 1) . '%']);
        }
        fputcsv($output, []);
    }

    // Monthly Breakdown
    fputcsv($output, ['MONTHLY BREAKDOWN']);
    fputcsv($output, ['Month', 'Invoiced', 'Collected', 'Collection Rate', 'Invoice Count', 'Payment Count']);

    foreach ($monthlyRevenue as $month) {
        $rate = $month['invoiced'] > 0 ? round(($month['collected'] / $month['invoiced']) * 100, 1) : 0;
        fputcsv($output, [
            $month['monthName'],
            number_format($month['invoiced'], 2),
            number_format($month['collected'], 2),
            $rate . '%',
            $month['invoiceCount'],
            $month['paymentCount']
        ]);
    }
    fputcsv($output, []);

    // Payment Method Breakdown
    fputcsv($output, ['PAYMENT METHOD BREAKDOWN']);
    fputcsv($output, ['Method', 'Total Amount', 'Payment Count']);

    foreach ($paymentMethodBreakdown as $method) {
        fputcsv($output, [
            $method['method'],
            number_format((float) $method['totalAmount'], 2),
            $method['paymentCount']
        ]);
    }

    fclose($output);
}
