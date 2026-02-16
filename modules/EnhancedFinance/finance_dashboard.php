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
 * Enhanced Finance Module - Financial Dashboard
 *
 * Displays financial KPIs and overview including:
 * - Total revenue for current year
 * - Outstanding balance (unpaid invoices)
 * - Payment collection rate percentage
 * - Recent transactions list
 * - Overdue invoices count and amount
 * - Quick links to invoice management
 * - RL-24 status overview (if tax season)
 * - YTD (Year-to-Date) revenue with monthly breakdown
 * - Outstanding balances by family
 * - Payment method breakdown with percentages
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Domain\School\SchoolYearGateway;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Module\EnhancedFinance\Domain\Releve24Gateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_dashboard.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $gibbonSchoolYearID = $_GET['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    // Breadcrumbs
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Financial Dashboard'));

    // Get gateways and settings
    $invoiceGateway = $container->get(InvoiceGateway::class);
    $paymentGateway = $container->get(PaymentGateway::class);
    $releve24Gateway = $container->get(Releve24Gateway::class);
    $settingGateway = $container->get(SettingGateway::class);
    $schoolYearGateway = $container->get(SchoolYearGateway::class);

    // Get school year information
    $schoolYear = $schoolYearGateway->getByID($gibbonSchoolYearID);
    $schoolYearName = $schoolYear['name'] ?? __('Current Year');
    $currentSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get settings
    $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

    // Get financial summary
    $financialSummary = $invoiceGateway->selectFinancialSummaryByYear($gibbonSchoolYearID);

    // Calculate KPIs
    $totalInvoiced = (float) ($financialSummary['totalInvoiced'] ?? 0);
    $totalPaid = (float) ($financialSummary['totalPaid'] ?? 0);
    $totalOutstanding = (float) ($financialSummary['totalOutstanding'] ?? 0);
    $overdueAmount = (float) ($financialSummary['overdueAmount'] ?? 0);
    $totalInvoices = (int) ($financialSummary['totalInvoices'] ?? 0);
    $paidCount = (int) ($financialSummary['paidCount'] ?? 0);
    $outstandingCount = (int) ($financialSummary['outstandingCount'] ?? 0);
    $overdueCount = (int) ($financialSummary['overdueCount'] ?? 0);

    // Calculate collection rate
    $collectionRate = $totalInvoiced > 0 ? round(($totalPaid / $totalInvoiced) * 100, 1) : 0;

    // Get payment summary by method
    $paymentByMethod = $paymentGateway->selectPaymentSummaryByMethod($gibbonSchoolYearID)->fetchAll();

    // Get recent payments
    $recentPayments = $paymentGateway->selectRecentPayments($gibbonSchoolYearID, 10)->fetchAll();

    // Get overdue invoices
    $overdueInvoices = $invoiceGateway->selectOverdueByYear($gibbonSchoolYearID)->fetchAll();

    // Get payment summary by month
    $paymentsByMonth = $paymentGateway->selectPaymentSummaryByMonth($gibbonSchoolYearID)->fetchAll();

    // Get YTD (Year-to-Date) revenue for current calendar year
    $currentCalendarYear = (int) date('Y');
    $ytdRevenue = $paymentGateway->selectYTDRevenue($currentCalendarYear);
    $ytdRevenueByMonth = $paymentGateway->selectYTDRevenueByMonth($currentCalendarYear)->fetchAll();

    // Get outstanding balances by family
    $outstandingByFamily = $invoiceGateway->selectOutstandingByFamily($gibbonSchoolYearID, 10)->fetchAll();
    $outstandingFamilySummary = $invoiceGateway->selectOutstandingByFamilySummary($gibbonSchoolYearID);

    // Check if it's tax season (January-February for previous year's RL-24)
    $currentMonth = (int) date('m');
    $isTaxSeason = ($currentMonth >= 1 && $currentMonth <= 2);
    $previousTaxYear = (int) date('Y') - 1;
    $releve24Summary = null;

    if ($isTaxSeason || true) { // Always show RL-24 section for visibility
        $taxYear = $isTaxSeason ? $previousTaxYear : (int) date('Y');
        $releve24Summary = $releve24Gateway->selectReleve24SummaryByYear($taxYear);
    }

    // School Year Navigator
    echo '<div class="flex justify-between items-center mb-4">';
    echo '<h2>' . __('Financial Dashboard') . ' - ' . htmlspecialchars($schoolYearName) . '</h2>';

    // School year selection dropdown
    $schoolYears = $schoolYearGateway->selectSchoolYears()->fetchAll();
    echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php" class="flex items-center gap-2">';
    echo '<input type="hidden" name="q" value="/modules/EnhancedFinance/finance_dashboard.php">';
    echo '<label class="text-sm text-gray-600">' . __('School Year') . ':</label>';
    echo '<select name="gibbonSchoolYearID" onchange="this.form.submit()" class="border rounded px-2 py-1">';
    foreach ($schoolYears as $year) {
        $selected = ($year['gibbonSchoolYearID'] == $gibbonSchoolYearID) ? 'selected' : '';
        echo '<option value="' . $year['gibbonSchoolYearID'] . '" ' . $selected . '>' . htmlspecialchars($year['name']) . '</option>';
    }
    echo '</select>';
    echo '</form>';
    echo '</div>';

    // Key Performance Indicators (KPIs) Grid
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';

    // Total Revenue (Collected)
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-green-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Total Collected') . '</p>';
    echo '<p class="text-2xl font-bold text-green-600">' . Format::currency($totalPaid) . '</p>';
    echo '</div>';
    echo '<div class="text-green-500 bg-green-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d of %d invoices paid'), $paidCount, $totalInvoices) . '</p>';
    echo '</div>';

    // Total Invoiced
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-blue-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Total Invoiced') . '</p>';
    echo '<p class="text-2xl font-bold text-blue-600">' . Format::currency($totalInvoiced) . '</p>';
    echo '</div>';
    echo '<div class="text-blue-500 bg-blue-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d invoices this year'), $totalInvoices) . '</p>';
    echo '</div>';

    // Outstanding Balance
    echo '<div class="bg-white rounded-lg shadow p-5 border-l-4 border-orange-500">';
    echo '<div class="flex justify-between items-start">';
    echo '<div>';
    echo '<p class="text-sm text-gray-500 uppercase tracking-wide">' . __('Outstanding') . '</p>';
    echo '<p class="text-2xl font-bold text-orange-600">' . Format::currency($totalOutstanding) . '</p>';
    echo '</div>';
    echo '<div class="text-orange-500 bg-orange-100 p-2 rounded-full">';
    echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>';
    echo '</div>';
    echo '</div>';
    echo '<p class="text-xs text-gray-400 mt-2">' . sprintf(__('%d invoices outstanding'), $outstandingCount) . '</p>';
    echo '</div>';

    // Collection Rate
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

    // YTD (Year-to-Date) Revenue Section
    $ytdAmount = (float) ($ytdRevenue['totalAmount'] ?? 0);
    $ytdPaymentCount = (int) ($ytdRevenue['paymentCount'] ?? 0);
    $ytdFamilyCount = (int) ($ytdRevenue['familyCount'] ?? 0);
    $currentMonthNum = (int) date('m');

    echo '<div class="bg-white rounded-lg shadow mb-6">';
    echo '<div class="px-5 py-4 border-b flex justify-between items-center">';
    echo '<h3 class="text-lg font-semibold">';
    echo '<span class="text-indigo-600 mr-2">📈</span>';
    echo sprintf(__('Year-to-Date Revenue (%d)'), $currentCalendarYear);
    echo '</h3>';
    echo '<span class="bg-indigo-100 text-indigo-800 text-sm px-3 py-1 rounded-full">' . __('Calendar Year') . '</span>';
    echo '</div>';
    echo '<div class="p-5">';

    // YTD Summary Stats
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">';

    // Total YTD Revenue
    echo '<div class="text-center p-4 bg-indigo-50 rounded-lg">';
    echo '<div class="text-2xl font-bold text-indigo-700">' . Format::currency($ytdAmount) . '</div>';
    echo '<div class="text-sm text-indigo-500">' . __('Total Revenue') . '</div>';
    echo '</div>';

    // Payment Count
    echo '<div class="text-center p-4 bg-green-50 rounded-lg">';
    echo '<div class="text-2xl font-bold text-green-700">' . $ytdPaymentCount . '</div>';
    echo '<div class="text-sm text-green-500">' . __('Payments Received') . '</div>';
    echo '</div>';

    // Families Served
    echo '<div class="text-center p-4 bg-blue-50 rounded-lg">';
    echo '<div class="text-2xl font-bold text-blue-700">' . $ytdFamilyCount . '</div>';
    echo '<div class="text-sm text-blue-500">' . __('Families Served') . '</div>';
    echo '</div>';

    // Monthly Average
    $monthlyAverage = $currentMonthNum > 0 ? $ytdAmount / $currentMonthNum : 0;
    echo '<div class="text-center p-4 bg-purple-50 rounded-lg">';
    echo '<div class="text-2xl font-bold text-purple-700">' . Format::currency($monthlyAverage) . '</div>';
    echo '<div class="text-sm text-purple-500">' . __('Monthly Average') . '</div>';
    echo '</div>';

    echo '</div>';

    // YTD Monthly Breakdown (mini chart)
    if (count($ytdRevenueByMonth) > 0) {
        $maxYtdMonth = max(array_column($ytdRevenueByMonth, 'totalAmount'));
        $monthNames = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

        echo '<div class="mt-4 pt-4 border-t">';
        echo '<div class="text-sm text-gray-600 mb-2">' . __('Monthly Revenue Breakdown') . '</div>';
        echo '<div class="flex items-end justify-between space-x-1" style="height: 80px;">';

        // Create array for all months up to current
        for ($m = 1; $m <= $currentMonthNum; $m++) {
            $monthData = array_filter($ytdRevenueByMonth, function ($r) use ($m) {
                return (int) $r['paymentMonth'] == $m;
            });
            $monthData = reset($monthData);
            $amount = $monthData ? (float) $monthData['totalAmount'] : 0;
            $height = $maxYtdMonth > 0 ? round(($amount / $maxYtdMonth) * 100) : 0;

            echo '<div class="flex-1 flex flex-col items-center">';
            echo '<div class="w-full bg-indigo-500 hover:bg-indigo-600 transition rounded-t cursor-pointer" style="height: ' . max($height, 2) . '%" title="' . $monthNames[$m] . ': ' . Format::currency($amount) . '"></div>';
            echo '<div class="text-xs text-gray-400 mt-1">' . $monthNames[$m] . '</div>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';

    // Overdue Alert (if any)
    if ($overdueCount > 0) {
        echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">';
        echo '<div class="flex items-center">';
        echo '<div class="text-red-500 mr-3">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>';
        echo '</div>';
        echo '<div class="flex-1">';
        echo '<h4 class="font-semibold text-red-800">' . sprintf(__('%d Overdue Invoice(s)'), $overdueCount) . '</h4>';
        echo '<p class="text-red-700">' . sprintf(__('Total overdue amount: %s'), Format::currency($overdueAmount)) . '</p>';
        echo '</div>';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_invoices.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '&status=Overdue" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">';
        echo __('View Overdue');
        echo '</a>';
        echo '</div>';
        echo '</div>';
    }

    // Main content grid
    echo '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">';

    // Recent Payments
    echo '<div class="bg-white rounded-lg shadow">';
    echo '<div class="px-5 py-4 border-b flex justify-between items-center">';
    echo '<h3 class="text-lg font-semibold">' . __('Recent Payments') . '</h3>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_payments.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '" class="text-sm text-blue-600 hover:underline">' . __('View All') . '</a>';
    echo '</div>';
    echo '<div class="p-5">';

    if (count($recentPayments) > 0) {
        echo '<div class="space-y-3">';
        foreach ($recentPayments as $payment) {
            echo '<div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition">';
            echo '<div class="flex-1">';
            echo '<div class="font-medium">' . Format::name('', $payment['childPreferredName'], $payment['childSurname'], 'Student', false) . '</div>';
            echo '<div class="text-sm text-gray-500">';
            echo '<span class="mr-2">' . htmlspecialchars($payment['invoiceNumber']) . '</span>';
            echo '<span class="text-xs bg-gray-200 px-2 py-0.5 rounded">' . __($payment['method']) . '</span>';
            echo '</div>';
            echo '</div>';
            echo '<div class="text-right">';
            echo '<div class="font-semibold text-green-600">' . Format::currency($payment['amount']) . '</div>';
            echo '<div class="text-xs text-gray-400">' . Format::date($payment['paymentDate']) . '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<div class="text-center py-8 text-gray-500">';
        echo '<p>' . __('No payments recorded for this school year.') . '</p>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';

    // Payment Methods Breakdown
    echo '<div class="bg-white rounded-lg shadow">';
    echo '<div class="px-5 py-4 border-b">';
    echo '<h3 class="text-lg font-semibold">' . __('Payments by Method') . '</h3>';
    echo '</div>';
    echo '<div class="p-5">';

    if (count($paymentByMethod) > 0) {
        $totalPayments = array_sum(array_column($paymentByMethod, 'totalAmount'));
        echo '<div class="space-y-3">';
        foreach ($paymentByMethod as $method) {
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
            echo '<div class="bg-blue-500 h-2 rounded-full" style="width: ' . $percentage . '%"></div>';
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

    echo '</div>'; // End main grid

    // Outstanding by Family Section
    if (count($outstandingByFamily) > 0) {
        $outstandingFamilyTotal = (float) ($outstandingFamilySummary['totalOutstanding'] ?? 0);
        $outstandingFamilyCount = (int) ($outstandingFamilySummary['familyCount'] ?? 0);
        $outstandingInvoiceCount = (int) ($outstandingFamilySummary['invoiceCount'] ?? 0);

        echo '<div class="bg-white rounded-lg shadow mb-6">';
        echo '<div class="px-5 py-4 border-b flex justify-between items-center">';
        echo '<h3 class="text-lg font-semibold">';
        echo '<span class="text-orange-600 mr-2">👨‍👩‍👧‍👦</span>';
        echo __('Outstanding by Family');
        echo '</h3>';
        echo '<div class="flex items-center space-x-4">';
        echo '<span class="text-sm text-gray-500">' . sprintf(__('%d families'), $outstandingFamilyCount) . '</span>';
        echo '<span class="bg-orange-100 text-orange-800 text-sm px-3 py-1 rounded-full font-semibold">' . Format::currency($outstandingFamilyTotal) . '</span>';
        echo '</div>';
        echo '</div>';
        echo '<div class="p-5">';

        // Family Outstanding Table
        echo '<div class="overflow-x-auto">';
        echo '<table class="w-full">';
        echo '<thead>';
        echo '<tr class="text-left text-sm text-gray-500 border-b">';
        echo '<th class="pb-3 font-medium">' . __('Family') . '</th>';
        echo '<th class="pb-3 font-medium text-center">' . __('Invoices') . '</th>';
        echo '<th class="pb-3 font-medium text-right">' . __('Outstanding') . '</th>';
        echo '<th class="pb-3 font-medium text-right">' . __('Overdue') . '</th>';
        echo '<th class="pb-3 font-medium text-center">' . __('Status') . '</th>';
        echo '<th class="pb-3 font-medium text-center">' . __('Action') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($outstandingByFamily as $family) {
            $familyOutstanding = (float) $family['totalOutstanding'];
            $familyOverdue = (float) $family['overdueAmount'];
            $hasOverdue = (int) $family['hasOverdue'];
            $invoiceCount = (int) $family['invoiceCount'];

            // Row styling based on overdue status
            $rowClass = $hasOverdue ? 'bg-red-50' : '';

            echo '<tr class="border-b hover:bg-gray-50 ' . $rowClass . '">';

            // Family Name
            echo '<td class="py-3">';
            echo '<div class="font-medium">' . htmlspecialchars($family['familyName']) . '</div>';
            if (!empty($family['oldestDueDate'])) {
                echo '<div class="text-xs text-gray-400">' . __('Oldest due') . ': ' . Format::date($family['oldestDueDate']) . '</div>';
            }
            echo '</td>';

            // Invoice Count
            echo '<td class="py-3 text-center">';
            echo '<span class="bg-gray-100 text-gray-700 px-2 py-1 rounded text-sm">' . $invoiceCount . '</span>';
            echo '</td>';

            // Outstanding Amount
            echo '<td class="py-3 text-right font-semibold text-orange-600">' . Format::currency($familyOutstanding) . '</td>';

            // Overdue Amount
            echo '<td class="py-3 text-right">';
            if ($familyOverdue > 0) {
                echo '<span class="font-semibold text-red-600">' . Format::currency($familyOverdue) . '</span>';
            } else {
                echo '<span class="text-gray-400">-</span>';
            }
            echo '</td>';

            // Status Badge
            echo '<td class="py-3 text-center">';
            if ($hasOverdue) {
                echo '<span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full">' . __('Overdue') . '</span>';
            } else {
                echo '<span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded-full">' . __('Pending') . '</span>';
            }
            echo '</td>';

            // Action Link
            echo '<td class="py-3 text-center">';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_invoices.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '&family=' . $family['gibbonFamilyID'] . '" class="text-blue-600 hover:text-blue-800 text-sm">';
            echo __('View');
            echo '</a>';
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        // Link to view all
        if ($outstandingFamilyCount > 10) {
            echo '<div class="mt-4 text-center">';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_invoices.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '&status=Outstanding" class="text-blue-600 hover:underline text-sm">';
            echo sprintf(__('View all %d families with outstanding balances'), $outstandingFamilyCount) . ' &rarr;';
            echo '</a>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Overdue Invoices Table (if any)
    if (count($overdueInvoices) > 0) {
        echo '<div class="bg-white rounded-lg shadow mb-6">';
        echo '<div class="px-5 py-4 border-b flex justify-between items-center">';
        echo '<h3 class="text-lg font-semibold text-red-700">' . __('Overdue Invoices') . '</h3>';
        echo '<span class="bg-red-100 text-red-800 text-sm px-3 py-1 rounded-full">' . sprintf(__('%d overdue'), count($overdueInvoices)) . '</span>';
        echo '</div>';
        echo '<div class="overflow-x-auto">';

        // Create overdue invoices table
        $table = DataTable::create('overdueInvoices');

        $table->addColumn('invoiceNumber', __('Invoice'))
            ->format(function ($invoice) use ($session, $gibbonSchoolYearID) {
                return '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_invoice_view.php&gibbonEnhancedFinanceInvoiceID=' . $invoice['gibbonEnhancedFinanceInvoiceID'] . '&gibbonSchoolYearID=' . $gibbonSchoolYearID . '" class="text-blue-600 hover:underline">' . htmlspecialchars($invoice['invoiceNumber']) . '</a>';
            });

        $table->addColumn('child', __('Child'))
            ->format(function ($invoice) {
                return Format::name('', $invoice['childPreferredName'], $invoice['childSurname'], 'Student', true);
            });

        $table->addColumn('familyName', __('Family'));

        $table->addColumn('dueDate', __('Due Date'))
            ->format(function ($invoice) {
                return Format::date($invoice['dueDate']);
            });

        $table->addColumn('daysOverdue', __('Days Overdue'))
            ->format(function ($invoice) {
                $days = (int) $invoice['daysOverdue'];
                $class = $days > 30 ? 'bg-red-600' : 'bg-orange-500';
                return '<span class="' . $class . ' text-white px-2 py-1 rounded text-sm">' . $days . ' ' . __('days') . '</span>';
            });

        $table->addColumn('balanceRemaining', __('Balance'))
            ->format(function ($invoice) {
                return '<span class="font-semibold text-red-600">' . Format::currency($invoice['balanceRemaining']) . '</span>';
            });

        $table->addActionColumn()
            ->addParam('gibbonEnhancedFinanceInvoiceID')
            ->addParam('gibbonSchoolYearID', $gibbonSchoolYearID)
            ->format(function ($invoice, $actions) {
                $actions->addAction('view', __('View'))
                    ->setURL('/modules/EnhancedFinance/finance_invoice_view.php');
            });

        echo $table->render($overdueInvoices);
        echo '</div>';
        echo '</div>';
    }

    // Monthly Payment Trend (if data available)
    if (count($paymentsByMonth) > 0) {
        echo '<div class="bg-white rounded-lg shadow mb-6">';
        echo '<div class="px-5 py-4 border-b">';
        echo '<h3 class="text-lg font-semibold">' . __('Monthly Payment Trend') . '</h3>';
        echo '</div>';
        echo '<div class="p-5">';

        // Find max for scaling
        $maxPayment = max(array_column($paymentsByMonth, 'totalAmount'));

        echo '<div class="flex items-end justify-between space-x-2" style="height: 200px;">';
        foreach ($paymentsByMonth as $month) {
            $height = $maxPayment > 0 ? round(($month['totalAmount'] / $maxPayment) * 100) : 0;
            $monthName = date('M', mktime(0, 0, 0, $month['paymentMonth'], 1));

            echo '<div class="flex-1 flex flex-col items-center">';
            echo '<div class="w-full bg-blue-500 hover:bg-blue-600 transition rounded-t cursor-pointer" style="height: ' . $height . '%" title="' . Format::currency($month['totalAmount']) . '"></div>';
            echo '<div class="text-xs text-gray-500 mt-1 text-center">' . $monthName . '</div>';
            echo '</div>';
        }
        echo '</div>';

        // Legend
        echo '<div class="mt-4 flex justify-between text-sm text-gray-500">';
        echo '<span>' . __('Total this year') . ': ' . Format::currency($totalPaid) . '</span>';
        echo '<span>' . sprintf(__('%d payments'), array_sum(array_column($paymentsByMonth, 'paymentCount'))) . '</span>';
        echo '</div>';

        echo '</div>';
        echo '</div>';
    }

    // Quebec RL-24 Status Section
    if ($releve24Summary && ($releve24Summary['totalSlips'] > 0 || $isTaxSeason)) {
        $taxYear = $isTaxSeason ? $previousTaxYear : (int) date('Y');

        echo '<div class="bg-white rounded-lg shadow mb-6">';
        echo '<div class="px-5 py-4 border-b flex justify-between items-center">';
        echo '<h3 class="text-lg font-semibold">';
        echo '<span class="text-red-600 mr-2">🍁</span>';
        echo sprintf(__('Quebec RL-24 Status (%d)'), $taxYear);
        echo '</h3>';
        if ($isTaxSeason) {
            echo '<span class="bg-yellow-100 text-yellow-800 text-sm px-3 py-1 rounded-full animate-pulse">' . __('Tax Season') . '</span>';
        }
        echo '</div>';
        echo '<div class="p-5">';

        if ($releve24Summary['totalSlips'] > 0) {
            // RL-24 Summary Stats
            echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">';

            // Total Slips
            echo '<div class="text-center p-3 bg-gray-50 rounded-lg">';
            echo '<div class="text-2xl font-bold text-gray-700">' . (int) $releve24Summary['totalSlips'] . '</div>';
            echo '<div class="text-sm text-gray-500">' . __('Total Slips') . '</div>';
            echo '</div>';

            // Generated
            echo '<div class="text-center p-3 bg-blue-50 rounded-lg">';
            echo '<div class="text-2xl font-bold text-blue-600">' . (int) $releve24Summary['generatedCount'] . '</div>';
            echo '<div class="text-sm text-blue-500">' . __('Generated') . '</div>';
            echo '</div>';

            // Sent
            echo '<div class="text-center p-3 bg-green-50 rounded-lg">';
            echo '<div class="text-2xl font-bold text-green-600">' . (int) $releve24Summary['sentCount'] . '</div>';
            echo '<div class="text-sm text-green-500">' . __('Sent') . '</div>';
            echo '</div>';

            // Filed
            echo '<div class="text-center p-3 bg-purple-50 rounded-lg">';
            echo '<div class="text-2xl font-bold text-purple-600">' . (int) $releve24Summary['filedCount'] . '</div>';
            echo '<div class="text-sm text-purple-500">' . __('Filed') . '</div>';
            echo '</div>';

            echo '</div>';

            // Qualifying expenses total
            echo '<div class="bg-gray-50 rounded-lg p-4">';
            echo '<div class="flex justify-between items-center">';
            echo '<span class="text-gray-600">' . __('Total Qualifying Expenses (Box E)') . ':</span>';
            echo '<span class="text-xl font-bold text-green-600">' . Format::currency($releve24Summary['totalQualifyingExpenses'] ?? 0) . '</span>';
            echo '</div>';
            if ((int) $releve24Summary['draftCount'] > 0) {
                echo '<div class="mt-2 text-sm text-orange-600">';
                echo sprintf(__('%d draft slip(s) pending generation'), (int) $releve24Summary['draftCount']);
                echo '</div>';
            }
            echo '</div>';

            // Link to RL-24 management
            echo '<div class="mt-4">';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_releve24.php&taxYear=' . $taxYear . '" class="inline-flex items-center text-red-600 hover:underline font-medium">';
            echo __('Manage RL-24 Slips') . ' &rarr;';
            echo '</a>';
            echo '</div>';

        } else {
            echo '<div class="text-center py-6">';
            echo '<div class="text-gray-400 text-4xl mb-3">📋</div>';
            echo '<p class="text-gray-500">' . sprintf(__('No RL-24 slips generated for tax year %d.'), $taxYear) . '</p>';
            if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_releve24_generate.php')) {
                echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_releve24_generate.php&taxYear=' . $taxYear . '" class="inline-block mt-3 bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 transition">';
                echo __('Generate RL-24 Slips');
                echo '</a>';
            }
            echo '</div>';
        }

        // Tax season deadline reminder
        if ($isTaxSeason) {
            $deadline = date('Y') . '-02-28';
            $daysUntilDeadline = (strtotime($deadline) - time()) / (60 * 60 * 24);

            echo '<div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">';
            echo '<div class="flex items-center">';
            echo '<span class="text-yellow-500 mr-2">⚠️</span>';
            echo '<div>';
            echo '<p class="font-semibold text-yellow-800">' . __('RL-24 Filing Deadline Reminder') . '</p>';
            echo '<p class="text-sm text-yellow-700">';
            if ($daysUntilDeadline > 0) {
                echo sprintf(__('RL-24 slips must be issued to parents by %s (%d days remaining).'), Format::date($deadline), (int) $daysUntilDeadline);
            } else {
                echo '<span class="text-red-600 font-bold">' . __('The RL-24 filing deadline has passed!') . '</span>';
            }
            echo '</p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Quick Actions Section
    echo '<div class="bg-white rounded-lg shadow">';
    echo '<div class="px-5 py-4 border-b">';
    echo '<h3 class="text-lg font-semibold">' . __('Quick Actions') . '</h3>';
    echo '</div>';
    echo '<div class="p-5">';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4">';

    // Create Invoice
    if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_invoice_add.php')) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_invoice_add.php" class="flex flex-col items-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition">';
        echo '<div class="text-green-500 mb-2">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>';
        echo '</div>';
        echo '<span class="text-sm font-medium text-green-700">' . __('Create Invoice') . '</span>';
        echo '</a>';
    }

    // View Invoices
    if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_invoices.php')) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_invoices.php&gibbonSchoolYearID=' . $gibbonSchoolYearID . '" class="flex flex-col items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition">';
        echo '<div class="text-blue-500 mb-2">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>';
        echo '</div>';
        echo '<span class="text-sm font-medium text-blue-700">' . __('View Invoices') . '</span>';
        echo '</a>';
    }

    // Record Payment
    if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_payment_add.php')) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_payment_add.php" class="flex flex-col items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition">';
        echo '<div class="text-purple-500 mb-2">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" /></svg>';
        echo '</div>';
        echo '<span class="text-sm font-medium text-purple-700">' . __('Record Payment') . '</span>';
        echo '</a>';
    }

    // Generate RL-24
    if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_releve24_generate.php')) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_releve24_generate.php" class="flex flex-col items-center p-4 bg-red-50 hover:bg-red-100 rounded-lg transition">';
        echo '<div class="text-red-500 mb-2">';
        echo '<svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>';
        echo '</div>';
        echo '<span class="text-sm font-medium text-red-700">' . __('Generate RL-24') . '</span>';
        echo '</a>';
    }

    echo '</div>';
    echo '</div>';
    echo '</div>';

    // Last updated timestamp
    echo '<div class="mt-4 text-center text-xs text-gray-400">';
    echo __('Dashboard data as of') . ' ' . Format::dateTime(date('Y-m-d H:i:s'));
    echo '</div>';
}
