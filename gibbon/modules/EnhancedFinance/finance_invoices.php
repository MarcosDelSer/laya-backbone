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
 * Enhanced Finance Module - Invoice List View
 *
 * Displays paginated invoice list with filtering by status, family, date range.
 * Provides links to view, edit, and add invoices.
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
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_invoices.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Manage Invoices'));

    // Return messages
    $page->return->addReturns([
        'success0' => __('Your request was completed successfully.'),
        'success1' => __('Invoice created successfully.'),
        'error1' => __('There was an error creating the invoice.'),
        'error2' => __('The selected invoice could not be found.'),
        'error3' => __('Required parameters were not provided.'),
    ]);

    // Description
    echo '<p>';
    echo __('This section allows you to view, create, and manage childcare invoices. Use the filters below to find specific invoices by status, family, or date range. You can create new invoices for families and track payment status.');
    echo '</p>';

    // Get current school year
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    if (!empty($gibbonSchoolYearID)) {
        // School year navigation
        $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

        // Request parameters for filters
        $request = [
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'status'             => $_GET['status'] ?? '',
            'gibbonFamilyID'     => $_GET['gibbonFamilyID'] ?? '',
            'dateFrom'           => $_GET['dateFrom'] ?? '',
            'dateTo'             => $_GET['dateTo'] ?? '',
        ];

        // Default to Outstanding status if no filter set
        if (empty($_POST) && !isset($_GET['status'])) {
            $request['status'] = 'Outstanding';
        }

        // Get gateways
        $invoiceGateway = $container->get(InvoiceGateway::class);
        $familyGateway = $container->get(FamilyGateway::class);
        $settingGateway = $container->get(SettingGateway::class);

        // Get currency from settings
        $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

        // Build filter form
        $form = Form::create('invoiceFilters', $session->get('absoluteURL') . '/index.php', 'get');
        $form->setTitle(__('Filters'));
        $form->setClass('noIntBorder w-full');

        $form->addHiddenValue('q', '/modules/EnhancedFinance/finance_invoices.php');
        $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        // Status filter
        $statusOptions = [
            ''            => __('All'),
            'Pending'     => __('Pending'),
            'Issued'      => __('Issued'),
            'Partial'     => __('Partial'),
            'Outstanding' => __('Outstanding (Issued + Partial)'),
            'Overdue'     => __('Overdue'),
            'Paid'        => __('Paid'),
            'Cancelled'   => __('Cancelled'),
            'Refunded'    => __('Refunded'),
        ];

        $row = $form->addRow();
            $row->addLabel('status', __('Status'));
            $row->addSelect('status')
                ->fromArray($statusOptions)
                ->selected($request['status']);

        // Family filter
        $families = $familyGateway->selectFamiliesWithActiveStudents($gibbonSchoolYearID)->fetchAll();
        $familyOptions = array_column($families, 'name', 'gibbonFamilyID');

        $row = $form->addRow();
            $row->addLabel('gibbonFamilyID', __('Family'));
            $row->addSelect('gibbonFamilyID')
                ->fromArray($familyOptions)
                ->placeholder()
                ->selected($request['gibbonFamilyID']);

        // Date range filters
        $row = $form->addRow();
            $row->addLabel('dateFrom', __('Invoice Date From'));
            $row->addDate('dateFrom')
                ->setValue($request['dateFrom']);

        $row = $form->addRow();
            $row->addLabel('dateTo', __('Invoice Date To'));
            $row->addDate('dateTo')
                ->setValue($request['dateTo']);

        $row = $form->addRow();
            $row->addSearchSubmit($session, __('Clear Filters'), ['gibbonSchoolYearID']);

        echo $form->getOutput();

        // Invoice list section
        echo '<h3>';
        echo __('Invoices');
        echo '</h3>';

        // Build query criteria
        $criteria = $invoiceGateway->newQueryCriteria(true)
            ->sortBy(['defaultSortOrder', 'invoiceDate', 'invoiceNumber'])
            ->filterBy('status', $request['status'])
            ->filterBy('family', $request['gibbonFamilyID'])
            ->filterBy('dateFrom', $request['dateFrom'])
            ->filterBy('dateTo', $request['dateTo'])
            ->fromPOST();

        // Execute query
        $invoices = $invoiceGateway->queryInvoicesByYear($criteria, $gibbonSchoolYearID);

        // Create DataTable
        $table = DataTable::createPaginated('invoices', $criteria);

        // Add invoice button
        $table->addHeaderAction('add', __('Add'))
            ->setURL('/modules/EnhancedFinance/finance_invoice_add.php')
            ->addParams($request)
            ->displayLabel();

        // Row modifier for status highlighting
        $table->modifyRows(function ($invoice, $row) {
            // Highlight overdue invoices
            if (in_array($invoice['status'], ['Issued', 'Partial']) && $invoice['dueDate'] < date('Y-m-d')) {
                $row->addClass('error');
            }
            // Highlight paid invoices
            else if ($invoice['status'] == 'Paid') {
                $row->addClass('current');
            }
            // Cancelled or refunded
            else if (in_array($invoice['status'], ['Cancelled', 'Refunded'])) {
                $row->addClass('dull');
            }
            return $row;
        });

        // Filter options for quick access
        $table->addMetaData('filterOptions', [
            'status:Pending'     => __('Status') . ': ' . __('Pending'),
            'status:Issued'      => __('Status') . ': ' . __('Issued'),
            'status:Partial'     => __('Status') . ': ' . __('Partial'),
            'status:Outstanding' => __('Status') . ': ' . __('Outstanding'),
            'status:Overdue'     => __('Status') . ': ' . __('Overdue'),
            'status:Paid'        => __('Status') . ': ' . __('Paid'),
            'status:Cancelled'   => __('Status') . ': ' . __('Cancelled'),
            'status:Refunded'    => __('Status') . ': ' . __('Refunded'),
        ]);

        // Column: Invoice Number
        $table->addColumn('invoiceNumber', __('Invoice #'))
            ->sortable(['invoiceNumber'])
            ->width('10%');

        // Column: Child / Family
        $table->addColumn('child', __('Child'))
            ->description(__('Family'))
            ->sortable(['childSurname', 'childPreferredName'])
            ->format(function ($invoice) {
                $output = '<b>' . Format::name('', $invoice['childPreferredName'], $invoice['childSurname'], 'Student', true) . '</b>';
                $output .= '<br/><span class="text-xs italic">' . $invoice['familyName'] . '</span>';
                return $output;
            });

        // Column: Invoice Date / Due Date
        $table->addColumn('invoiceDate', __('Invoice Date'))
            ->description(__('Due Date'))
            ->sortable(['invoiceDate'])
            ->format(function ($invoice) {
                $output = Format::date($invoice['invoiceDate']);
                $output .= '<br/><span class="text-xs italic">' . Format::date($invoice['dueDate']) . '</span>';
                return $output;
            });

        // Column: Status
        $table->addColumn('status', __('Status'))
            ->sortable(['status'])
            ->format(function ($invoice) {
                $status = $invoice['status'];
                $class = '';

                // Check for overdue
                if (in_array($status, ['Issued', 'Partial']) && $invoice['dueDate'] < date('Y-m-d')) {
                    $status = __('Overdue');
                    $class = 'text-red-600 font-semibold';
                } else if ($status == 'Paid') {
                    $class = 'text-green-600';
                } else if (in_array($status, ['Cancelled', 'Refunded'])) {
                    $class = 'text-gray-500';
                } else if ($status == 'Partial') {
                    $class = 'text-orange-600';
                }

                return '<span class="' . $class . '">' . __($status) . '</span>';
            });

        // Column: Total / Paid
        $table->addColumn('totalAmount', __('Total') . ' <small><i>(' . $currency . ')</i></small>')
            ->description(__('Paid') . ' (' . $currency . ')')
            ->notSortable()
            ->format(function ($invoice) {
                $output = Format::currency($invoice['totalAmount']);
                if (!empty($invoice['paidAmount']) && $invoice['paidAmount'] > 0) {
                    $class = (float)$invoice['paidAmount'] < (float)$invoice['totalAmount'] ? 'text-orange-600' : 'text-green-600';
                    $output .= '<br/><span class="text-xs italic ' . $class . '">' . Format::currency($invoice['paidAmount']) . '</span>';
                }
                return $output;
            });

        // Column: Balance
        $table->addColumn('balanceRemaining', __('Balance'))
            ->notSortable()
            ->format(function ($invoice) {
                $balance = (float)$invoice['balanceRemaining'];
                if ($balance <= 0) {
                    return '<span class="text-green-600 font-semibold">-</span>';
                }
                return '<span class="text-red-600 font-semibold">' . Format::currency($balance) . '</span>';
            });

        // Expandable notes column
        $table->addExpandableColumn('notes');

        // Actions column
        $table->addActionColumn()
            ->addParam('gibbonEnhancedFinanceInvoiceID')
            ->addParams($request)
            ->format(function ($invoice, $actions) {
                // View action - always available
                $actions->addAction('view', __('View'))
                    ->setURL('/modules/EnhancedFinance/finance_invoice_view.php');

                // Edit action - not for cancelled or refunded
                if (!in_array($invoice['status'], ['Cancelled', 'Refunded'])) {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/EnhancedFinance/finance_invoice_edit.php');
                }

                // Add payment action - for issued or partial invoices
                if (in_array($invoice['status'], ['Issued', 'Partial'])) {
                    $actions->addAction('payment', __('Add Payment'))
                        ->setURL('/modules/EnhancedFinance/finance_payment_add.php')
                        ->setIcon('dollar');
                }

                // Print action - for issued and paid invoices
                if (!in_array($invoice['status'], ['Pending', 'Cancelled'])) {
                    $actions->addAction('print', __('Print Invoice'))
                        ->setURL('/modules/EnhancedFinance/finance_invoice_print.php')
                        ->setIcon('print')
                        ->directLink()
                        ->addParam('type', 'invoice');
                }
            });

        echo $table->render($invoices);

        // Summary statistics
        $summary = $invoiceGateway->selectFinancialSummaryByYear($gibbonSchoolYearID);

        if (!empty($summary) && $summary['totalInvoices'] > 0) {
            echo '<div class="mt-6 p-4 bg-gray-50 border rounded-lg">';
            echo '<h4 class="font-semibold mb-3">' . __('Financial Summary for School Year') . '</h4>';
            echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4">';

            // Total Invoiced
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Total Invoiced') . '</div>';
            echo '<div class="text-xl font-semibold text-blue-600">' . Format::currency($summary['totalInvoiced'] ?? 0) . '</div>';
            echo '</div>';

            // Total Collected
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Total Collected') . '</div>';
            echo '<div class="text-xl font-semibold text-green-600">' . Format::currency($summary['totalPaid'] ?? 0) . '</div>';
            echo '</div>';

            // Outstanding
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Outstanding') . '</div>';
            echo '<div class="text-xl font-semibold text-orange-600">' . Format::currency($summary['totalOutstanding'] ?? 0) . '</div>';
            echo '</div>';

            // Overdue
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Overdue') . '</div>';
            echo '<div class="text-xl font-semibold text-red-600">' . Format::currency($summary['overdueAmount'] ?? 0) . '</div>';
            echo '</div>';

            echo '</div>'; // End grid
            echo '</div>'; // End summary box
        }
    } else {
        $page->addError(__('School year has not been specified.'));
    }
}
