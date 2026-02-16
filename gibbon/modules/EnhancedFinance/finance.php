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
 * Enhanced Finance Module - Main Entry Point
 *
 * This is the main entry point for the Enhanced Finance module.
 * It provides navigation to the financial dashboard and other module features.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Services\Format;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Enhanced Finance'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get the absolute URL for redirects
    $absoluteURL = $session->get('absoluteURL');

    // Check if the user has access to the dashboard
    $hasDashboardAccess = isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_dashboard.php');

    // Display the module home page with navigation links
    echo '<h2>' . __('Enhanced Finance') . '</h2>';

    echo '<p class="text-lg mb-4">' . __('Comprehensive invoicing, payment tracking, and financial reporting for childcare facilities. Includes Quebec Relevé 24 (RL-24) tax document generation for regulatory compliance.') . '</p>';

    // Navigation cards grid
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mt-6">';

    // Dashboard Card (if accessible)
    if ($hasDashboardAccess) {
        echo '<div class="bg-white rounded-lg shadow p-4 border-l-4 border-blue-500">';
        echo '<h3 class="text-lg font-semibold mb-2">' . __('Financial Dashboard') . '</h3>';
        echo '<p class="text-gray-600 mb-3">' . __('Overview of revenue, outstanding invoices, payment trends, and key financial metrics.') . '</p>';
        echo '<a href="' . $absoluteURL . '/index.php?q=/modules/EnhancedFinance/finance_dashboard.php" class="text-blue-600 hover:underline font-medium">' . __('View Dashboard') . ' &rarr;</a>';
        echo '</div>';
    }

    // Invoice Management Card
    $hasInvoiceAccess = isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_invoices.php');
    if ($hasInvoiceAccess) {
        echo '<div class="bg-white rounded-lg shadow p-4 border-l-4 border-green-500">';
        echo '<h3 class="text-lg font-semibold mb-2">' . __('Manage Invoices') . '</h3>';
        echo '<p class="text-gray-600 mb-3">' . __('Create, view, and manage childcare invoices. Track payments and outstanding balances.') . '</p>';
        echo '<a href="' . $absoluteURL . '/index.php?q=/modules/EnhancedFinance/finance_invoices.php" class="text-green-600 hover:underline font-medium">' . __('View Invoices') . ' &rarr;</a>';
        echo '</div>';
    }

    // Contracts Card
    $hasContractAccess = isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_contracts.php');
    if ($hasContractAccess) {
        echo '<div class="bg-white rounded-lg shadow p-4 border-l-4 border-purple-500">';
        echo '<h3 class="text-lg font-semibold mb-2">' . __('Manage Contracts') . '</h3>';
        echo '<p class="text-gray-600 mb-3">' . __('View and manage childcare contracts linked to families and invoicing.') . '</p>';
        echo '<a href="' . $absoluteURL . '/index.php?q=/modules/EnhancedFinance/finance_contracts.php" class="text-purple-600 hover:underline font-medium">' . __('View Contracts') . ' &rarr;</a>';
        echo '</div>';
    }

    // Quebec RL-24 Card
    $hasRL24Access = isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_releve24.php');
    if ($hasRL24Access) {
        echo '<div class="bg-white rounded-lg shadow p-4 border-l-4 border-red-500">';
        echo '<h3 class="text-lg font-semibold mb-2">' . __('Quebec Relevé 24 (RL-24)') . '</h3>';
        echo '<p class="text-gray-600 mb-3">' . __('Generate and manage Quebec RL-24 tax documents for childcare expenses. Critical for regulatory compliance.') . '</p>';
        echo '<a href="' . $absoluteURL . '/index.php?q=/modules/EnhancedFinance/finance_releve24.php" class="text-red-600 hover:underline font-medium">' . __('Manage RL-24') . ' &rarr;</a>';
        echo '</div>';
    }

    // Settings Card (Admin only)
    $hasSettingsAccess = isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_settings.php');
    if ($hasSettingsAccess) {
        echo '<div class="bg-white rounded-lg shadow p-4 border-l-4 border-gray-500">';
        echo '<h3 class="text-lg font-semibold mb-2">' . __('Finance Settings') . '</h3>';
        echo '<p class="text-gray-600 mb-3">' . __('Configure provider information, tax rates, invoice prefixes, and RL-24 defaults.') . '</p>';
        echo '<a href="' . $absoluteURL . '/index.php?q=/modules/EnhancedFinance/finance_settings.php" class="text-gray-600 hover:underline font-medium">' . __('Configure Settings') . ' &rarr;</a>';
        echo '</div>';
    }

    echo '</div>'; // End grid

    // Quick Action Buttons
    echo '<div class="mt-8">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Actions') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';

    // Create Invoice button
    if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_invoice_add.php')) {
        echo '<a href="' . $absoluteURL . '/index.php?q=/modules/EnhancedFinance/finance_invoice_add.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Create Invoice') . '</a>';
    }

    // Record Payment button
    if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_payment_add.php')) {
        echo '<a href="' . $absoluteURL . '/index.php?q=/modules/EnhancedFinance/finance_payment_add.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Record Payment') . '</a>';
    }

    // Generate RL-24 button
    if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_releve24_generate.php')) {
        echo '<a href="' . $absoluteURL . '/index.php?q=/modules/EnhancedFinance/finance_releve24_generate.php" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">' . __('Generate RL-24') . '</a>';
    }

    // View Dashboard button
    if ($hasDashboardAccess) {
        echo '<a href="' . $absoluteURL . '/index.php?q=/modules/EnhancedFinance/finance_dashboard.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">' . __('View Dashboard') . '</a>';
    }

    echo '</div>';
    echo '</div>';

    // Information box about module features
    echo '<div class="mt-8 p-4 bg-blue-50 border border-blue-200 rounded-lg">';
    echo '<h4 class="font-semibold text-blue-800 mb-2">' . __('Module Features') . '</h4>';
    echo '<ul class="list-disc list-inside text-blue-700 space-y-1">';
    echo '<li>' . __('Create and manage childcare invoices with automatic invoice numbering') . '</li>';
    echo '<li>' . __('Record payments using multiple methods (Cash, Cheque, E-Transfer, Credit/Debit Card)') . '</li>';
    echo '<li>' . __('Track partial payments and remaining balances') . '</li>';
    echo '<li>' . __('Generate Quebec Relevé 24 (RL-24) tax documents for regulatory compliance') . '</li>';
    echo '<li>' . __('Financial dashboard with key performance indicators (KPIs)') . '</li>';
    echo '<li>' . __('Support for GST and QST tax calculations') . '</li>';
    echo '</ul>';
    echo '</div>';

    // Quebec RL-24 Compliance Notice
    if ($hasRL24Access) {
        echo '<div class="mt-4 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">';
        echo '<h4 class="font-semibold text-yellow-800 mb-2">' . __('Quebec RL-24 Compliance') . '</h4>';
        echo '<p class="text-yellow-700">' . __('Important: RL-24 tax slips must be issued to parents by the end of February following the tax year. The RL-24 amounts reflect PAID amounts at filing time, not invoiced amounts.') . '</p>';
        echo '</div>';
    }
}
