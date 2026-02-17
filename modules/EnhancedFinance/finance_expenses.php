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
 * Enhanced Finance Module - Expense List View
 *
 * Displays paginated expense list with filtering by status, category, vendor, date range.
 * Provides links to view, edit, and add expenses.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\EnhancedFinance\Domain\ExpenseGateway;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_expenses.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Manage Expenses'));

    // Return messages
    $page->return->addReturns([
        'success0' => __('Your request was completed successfully.'),
        'success1' => __('Expense created successfully.'),
        'success2' => __('Expense updated successfully.'),
        'success3' => __('Expense approved successfully.'),
        'error1' => __('There was an error creating the expense.'),
        'error2' => __('The selected expense could not be found.'),
        'error3' => __('Required parameters were not provided.'),
        'error4' => __('There was an error updating the expense.'),
    ]);

    // Description
    echo '<p>';
    echo __('This section allows you to view, create, and manage facility expenses. Use the filters below to find specific expenses by status, category, vendor, or date range. You can track expenses for payroll, supplies, utilities, and other operating costs.');
    echo '</p>';

    // Get current school year
    $gibbonSchoolYearID = $_REQUEST['gibbonSchoolYearID'] ?? $session->get('gibbonSchoolYearID');

    if (!empty($gibbonSchoolYearID)) {
        // School year navigation
        $page->navigator->addSchoolYearNavigation($gibbonSchoolYearID);

        // Request parameters for filters
        $request = [
            'gibbonSchoolYearID'                      => $gibbonSchoolYearID,
            'status'                                  => $_GET['status'] ?? '',
            'gibbonEnhancedFinanceExpenseCategoryID'  => $_GET['gibbonEnhancedFinanceExpenseCategoryID'] ?? '',
            'vendor'                                  => $_GET['vendor'] ?? '',
            'paymentMethod'                           => $_GET['paymentMethod'] ?? '',
            'dateFrom'                                => $_GET['dateFrom'] ?? '',
            'dateTo'                                  => $_GET['dateTo'] ?? '',
        ];

        // Default to Pending status if no filter set
        if (empty($_POST) && !isset($_GET['status'])) {
            $request['status'] = '';
        }

        // Get gateways
        $expenseGateway = $container->get(ExpenseGateway::class);
        $settingGateway = $container->get(SettingGateway::class);

        // Get currency from settings
        $currency = $settingGateway->getSettingByScope('System', 'currency') ?: 'CAD';

        // Get expense categories for filter dropdown
        $categoryData = [];
        $categorySQL = "SELECT gibbonEnhancedFinanceExpenseCategoryID, name
                        FROM gibbonEnhancedFinanceExpenseCategory
                        WHERE isActive = 1
                        ORDER BY sortOrder ASC, name ASC";
        $categoryResult = $connection2->query($categorySQL);
        $categoryOptions = [];
        while ($row = $categoryResult->fetch()) {
            $categoryOptions[$row['gibbonEnhancedFinanceExpenseCategoryID']] = $row['name'];
        }

        // Build filter form
        $form = Form::create('expenseFilters', $session->get('absoluteURL') . '/index.php', 'get');
        $form->setTitle(__('Filters'));
        $form->setClass('noIntBorder w-full');

        $form->addHiddenValue('q', '/modules/EnhancedFinance/finance_expenses.php');
        $form->addHiddenValue('gibbonSchoolYearID', $gibbonSchoolYearID);

        // Status filter
        $statusOptions = [
            ''         => __('All'),
            'Pending'  => __('Pending'),
            'Approved' => __('Approved'),
            'Paid'     => __('Paid'),
            'Rejected' => __('Rejected'),
        ];

        $row = $form->addRow();
            $row->addLabel('status', __('Status'));
            $row->addSelect('status')
                ->fromArray($statusOptions)
                ->selected($request['status']);

        // Category filter
        $row = $form->addRow();
            $row->addLabel('gibbonEnhancedFinanceExpenseCategoryID', __('Category'));
            $row->addSelect('gibbonEnhancedFinanceExpenseCategoryID')
                ->fromArray($categoryOptions)
                ->placeholder()
                ->selected($request['gibbonEnhancedFinanceExpenseCategoryID']);

        // Vendor filter
        $row = $form->addRow();
            $row->addLabel('vendor', __('Vendor'));
            $row->addTextField('vendor')
                ->setValue($request['vendor'])
                ->maxLength(150);

        // Payment method filter
        $paymentMethodOptions = [
            ''           => __('All'),
            'Cash'       => __('Cash'),
            'Cheque'     => __('Cheque'),
            'ETransfer'  => __('E-Transfer'),
            'CreditCard' => __('Credit Card'),
            'DebitCard'  => __('Debit Card'),
            'Other'      => __('Other'),
        ];

        $row = $form->addRow();
            $row->addLabel('paymentMethod', __('Payment Method'));
            $row->addSelect('paymentMethod')
                ->fromArray($paymentMethodOptions)
                ->selected($request['paymentMethod']);

        // Date range filters
        $row = $form->addRow();
            $row->addLabel('dateFrom', __('Expense Date From'));
            $row->addDate('dateFrom')
                ->setValue($request['dateFrom']);

        $row = $form->addRow();
            $row->addLabel('dateTo', __('Expense Date To'));
            $row->addDate('dateTo')
                ->setValue($request['dateTo']);

        $row = $form->addRow();
            $row->addSearchSubmit($session, __('Clear Filters'), ['gibbonSchoolYearID']);

        echo $form->getOutput();

        // Expense list section
        echo '<h3>';
        echo __('Expenses');
        echo '</h3>';

        // Build query criteria
        $criteria = $expenseGateway->newQueryCriteria(true)
            ->sortBy(['defaultSortOrder', 'expenseDate', 'timestampCreated'])
            ->filterBy('status', $request['status'])
            ->filterBy('category', $request['gibbonEnhancedFinanceExpenseCategoryID'])
            ->filterBy('vendor', $request['vendor'])
            ->filterBy('paymentMethod', $request['paymentMethod'])
            ->filterBy('dateFrom', $request['dateFrom'])
            ->filterBy('dateTo', $request['dateTo'])
            ->fromPOST();

        // Execute query
        $expenses = $expenseGateway->queryExpensesByYear($criteria, $gibbonSchoolYearID);

        // Create DataTable
        $table = DataTable::createPaginated('expenses', $criteria);

        // Add expense button
        $table->addHeaderAction('add', __('Add'))
            ->setURL('/modules/EnhancedFinance/finance_expense_add.php')
            ->addParams($request)
            ->displayLabel();

        // Row modifier for status highlighting
        $table->modifyRows(function ($expense, $row) {
            // Highlight pending expenses
            if ($expense['status'] == 'Pending') {
                $row->addClass('warning');
            }
            // Highlight paid expenses
            else if ($expense['status'] == 'Paid') {
                $row->addClass('current');
            }
            // Rejected expenses
            else if ($expense['status'] == 'Rejected') {
                $row->addClass('dull');
            }
            return $row;
        });

        // Filter options for quick access
        $table->addMetaData('filterOptions', [
            'status:Pending'  => __('Status') . ': ' . __('Pending'),
            'status:Approved' => __('Status') . ': ' . __('Approved'),
            'status:Paid'     => __('Status') . ': ' . __('Paid'),
            'status:Rejected' => __('Status') . ': ' . __('Rejected'),
        ]);

        // Column: Date
        $table->addColumn('expenseDate', __('Date'))
            ->sortable(['expenseDate'])
            ->width('10%')
            ->format(function ($expense) {
                return Format::date($expense['expenseDate']);
            });

        // Column: Category
        $table->addColumn('categoryName', __('Category'))
            ->sortable(['categoryName'])
            ->width('15%');

        // Column: Vendor
        $table->addColumn('vendor', __('Vendor'))
            ->description(__('Reference'))
            ->sortable(['vendor'])
            ->format(function ($expense) {
                $output = '<b>' . ($expense['vendor'] ?: '-') . '</b>';
                if (!empty($expense['reference'])) {
                    $output .= '<br/><span class="text-xs italic">' . $expense['reference'] . '</span>';
                }
                return $output;
            });

        // Column: Payment Method
        $table->addColumn('paymentMethod', __('Payment'))
            ->sortable(['paymentMethod'])
            ->width('10%')
            ->format(function ($expense) {
                $methodLabels = [
                    'Cash'       => __('Cash'),
                    'Cheque'     => __('Cheque'),
                    'ETransfer'  => __('E-Transfer'),
                    'CreditCard' => __('Credit Card'),
                    'DebitCard'  => __('Debit Card'),
                    'Other'      => __('Other'),
                ];
                return $methodLabels[$expense['paymentMethod']] ?? $expense['paymentMethod'];
            });

        // Column: Status
        $table->addColumn('status', __('Status'))
            ->sortable(['status'])
            ->width('10%')
            ->format(function ($expense) {
                $status = $expense['status'];
                $class = '';

                switch ($status) {
                    case 'Pending':
                        $class = 'text-orange-600';
                        break;
                    case 'Approved':
                        $class = 'text-blue-600';
                        break;
                    case 'Paid':
                        $class = 'text-green-600';
                        break;
                    case 'Rejected':
                        $class = 'text-red-600';
                        break;
                }

                return '<span class="' . $class . '">' . __($status) . '</span>';
            });

        // Column: Amount
        $table->addColumn('totalAmount', __('Amount') . ' <small><i>(' . $currency . ')</i></small>')
            ->description(__('Tax'))
            ->notSortable()
            ->format(function ($expense) {
                $output = '<b>' . Format::currency($expense['totalAmount']) . '</b>';
                if ((float)$expense['taxAmount'] > 0) {
                    $output .= '<br/><span class="text-xs italic text-gray-500">' . __('Tax') . ': ' . Format::currency($expense['taxAmount']) . '</span>';
                }
                return $output;
            });

        // Column: Created By
        $table->addColumn('createdBy', __('Created By'))
            ->notSortable()
            ->width('12%')
            ->format(function ($expense) {
                return Format::name('', $expense['createdByPreferredName'], $expense['createdBySurname'], 'Staff', false, true);
            });

        // Column: Receipt
        $table->addColumn('receipt', __('Receipt'))
            ->notSortable()
            ->width('5%')
            ->format(function ($expense) {
                if (!empty($expense['receiptPath'])) {
                    return '<span class="text-green-600" title="' . __('Receipt attached') . '">&#10004;</span>';
                }
                return '<span class="text-gray-400">-</span>';
            });

        // Expandable description column
        $table->addExpandableColumn('description');

        // Actions column
        $table->addActionColumn()
            ->addParam('gibbonEnhancedFinanceExpenseID')
            ->addParams($request)
            ->format(function ($expense, $actions) {
                // View action - always available
                $actions->addAction('view', __('View'))
                    ->setURL('/modules/EnhancedFinance/finance_expense_view.php');

                // Edit action - not for rejected expenses
                if ($expense['status'] != 'Rejected') {
                    $actions->addAction('edit', __('Edit'))
                        ->setURL('/modules/EnhancedFinance/finance_expense_edit.php');
                }

                // Approve action - only for pending expenses
                if ($expense['status'] == 'Pending') {
                    $actions->addAction('approve', __('Approve'))
                        ->setURL('/modules/EnhancedFinance/finance_expense_approve.php')
                        ->setIcon('iconTick');
                }
            });

        echo $table->render($expenses);

        // Summary statistics
        $summary = $expenseGateway->selectExpenseTotalsByYear($gibbonSchoolYearID);

        if (!empty($summary) && $summary['totalExpenses'] > 0) {
            echo '<div class="mt-6 p-4 bg-gray-50 border rounded-lg">';
            echo '<h4 class="font-semibold mb-3">' . __('Expense Summary for School Year') . '</h4>';
            echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4">';

            // Total Expenses
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Total Expenses') . '</div>';
            echo '<div class="text-xl font-semibold text-blue-600">' . Format::currency($summary['totalWithTax'] ?? 0) . '</div>';
            echo '<div class="text-xs text-gray-400">' . ($summary['totalExpenses'] ?? 0) . ' ' . __('expenses') . '</div>';
            echo '</div>';

            // Pending
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Pending') . '</div>';
            echo '<div class="text-xl font-semibold text-orange-600">' . Format::currency($summary['pendingAmount'] ?? 0) . '</div>';
            echo '<div class="text-xs text-gray-400">' . ($summary['pendingCount'] ?? 0) . ' ' . __('expenses') . '</div>';
            echo '</div>';

            // Approved
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Approved') . '</div>';
            echo '<div class="text-xl font-semibold text-blue-600">' . Format::currency($summary['approvedAmount'] ?? 0) . '</div>';
            echo '<div class="text-xs text-gray-400">' . ($summary['approvedCount'] ?? 0) . ' ' . __('expenses') . '</div>';
            echo '</div>';

            // Paid
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Paid') . '</div>';
            echo '<div class="text-xl font-semibold text-green-600">' . Format::currency($summary['paidAmount'] ?? 0) . '</div>';
            echo '<div class="text-xs text-gray-400">' . ($summary['paidCount'] ?? 0) . ' ' . __('expenses') . '</div>';
            echo '</div>';

            // Total Tax
            echo '<div class="text-center">';
            echo '<div class="text-sm text-gray-500">' . __('Total Tax') . '</div>';
            echo '<div class="text-xl font-semibold text-gray-600">' . Format::currency($summary['totalTax'] ?? 0) . '</div>';
            echo '</div>';

            echo '</div>'; // End grid
            echo '</div>'; // End summary box

            // Category breakdown
            $categorySummary = $expenseGateway->selectExpenseSummaryByCategory($gibbonSchoolYearID)->fetchAll();

            if (!empty($categorySummary)) {
                echo '<div class="mt-4 p-4 bg-gray-50 border rounded-lg">';
                echo '<h4 class="font-semibold mb-3">' . __('Expenses by Category') . '</h4>';
                echo '<div class="overflow-x-auto">';
                echo '<table class="w-full text-sm">';
                echo '<thead>';
                echo '<tr class="border-b">';
                echo '<th class="text-left py-2 px-3">' . __('Category') . '</th>';
                echo '<th class="text-center py-2 px-3">' . __('Account Code') . '</th>';
                echo '<th class="text-center py-2 px-3">' . __('Count') . '</th>';
                echo '<th class="text-right py-2 px-3">' . __('Amount') . '</th>';
                echo '<th class="text-right py-2 px-3">' . __('Tax') . '</th>';
                echo '<th class="text-right py-2 px-3">' . __('Total') . '</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';

                $grandTotal = 0;
                foreach ($categorySummary as $category) {
                    $grandTotal += (float)$category['totalWithTax'];
                    echo '<tr class="border-b hover:bg-gray-100">';
                    echo '<td class="py-2 px-3">' . $category['categoryName'] . '</td>';
                    echo '<td class="text-center py-2 px-3 text-gray-500">' . ($category['accountCode'] ?: '-') . '</td>';
                    echo '<td class="text-center py-2 px-3">' . $category['expenseCount'] . '</td>';
                    echo '<td class="text-right py-2 px-3">' . Format::currency($category['totalAmount']) . '</td>';
                    echo '<td class="text-right py-2 px-3 text-gray-500">' . Format::currency($category['totalTax']) . '</td>';
                    echo '<td class="text-right py-2 px-3 font-semibold">' . Format::currency($category['totalWithTax']) . '</td>';
                    echo '</tr>';
                }

                // Grand total row
                echo '<tr class="bg-gray-200 font-semibold">';
                echo '<td colspan="5" class="py-2 px-3 text-right">' . __('Grand Total') . '</td>';
                echo '<td class="text-right py-2 px-3">' . Format::currency($grandTotal) . '</td>';
                echo '</tr>';

                echo '</tbody>';
                echo '</table>';
                echo '</div>'; // End overflow
                echo '</div>'; // End category box
            }
        }
    } else {
        $page->addError(__('School year has not been specified.'));
    }
}
