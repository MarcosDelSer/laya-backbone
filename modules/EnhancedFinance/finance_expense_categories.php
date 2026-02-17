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
 * Enhanced Finance Module - Expense Category Management
 *
 * Allows administrators to view, add, edit, and manage expense categories.
 * Categories are used for organizing expenses and mapping to accounting software account codes.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

use Gibbon\Forms\Form;
use Gibbon\Services\Format;
use Gibbon\Tables\DataTable;

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_expense_categories.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Enhanced Finance'), 'finance.php')
        ->add(__('Manage Expense Categories'));

    // Return messages
    $page->return->addReturns([
        'success0' => __('Your request was completed successfully.'),
        'success1' => __('Expense category created successfully.'),
        'success2' => __('Expense category updated successfully.'),
        'success3' => __('Expense category deleted successfully.'),
        'error1' => __('There was an error creating the expense category.'),
        'error2' => __('The selected expense category could not be found.'),
        'error3' => __('Required parameters were not provided.'),
        'error4' => __('There was an error updating the expense category.'),
        'error5' => __('Cannot delete this category because it has associated expenses.'),
        'error6' => __('A category with this name already exists.'),
    ]);

    // Description
    echo '<p>';
    echo __('This section allows you to configure expense categories for tracking facility expenses. Each category can be assigned an account code for integration with accounting software like Sage 50 or QuickBooks. Categories are used when recording expenses to organize and report on different types of spending.');
    echo '</p>';

    // Check for edit or delete actions
    $action = $_GET['action'] ?? '';
    $gibbonEnhancedFinanceExpenseCategoryID = $_GET['gibbonEnhancedFinanceExpenseCategoryID'] ?? '';

    // Handle edit form display
    if ($action == 'edit' && !empty($gibbonEnhancedFinanceExpenseCategoryID)) {
        // Fetch category data
        $categorySQL = "SELECT * FROM gibbonEnhancedFinanceExpenseCategory
                        WHERE gibbonEnhancedFinanceExpenseCategoryID = :gibbonEnhancedFinanceExpenseCategoryID";
        $categoryStmt = $pdo->prepare($categorySQL);
        $categoryStmt->execute(['gibbonEnhancedFinanceExpenseCategoryID' => $gibbonEnhancedFinanceExpenseCategoryID]);
        $category = $categoryStmt->fetch();

        if (empty($category)) {
            $page->addError(__('The selected expense category could not be found.'));
        } else {
            // Edit form
            echo '<h3>';
            echo __('Edit Expense Category');
            echo '</h3>';

            $form = Form::create('categoryEdit', $session->get('absoluteURL') . '/modules/EnhancedFinance/finance_expense_categories_addProcess.php');
            $form->setClass('w-full max-w-2xl');

            $form->addHiddenValue('address', $session->get('address'));
            $form->addHiddenValue('action', 'edit');
            $form->addHiddenValue('gibbonEnhancedFinanceExpenseCategoryID', $gibbonEnhancedFinanceExpenseCategoryID);

            // Name
            $row = $form->addRow();
                $row->addLabel('name', __('Category Name'))
                    ->description(__('Unique name for this expense category.'));
                $row->addTextField('name')
                    ->maxLength(100)
                    ->required()
                    ->setValue($category['name']);

            // Description
            $row = $form->addRow();
                $row->addLabel('description', __('Description'))
                    ->description(__('Optional description of what expenses belong in this category.'));
                $row->addTextArea('description')
                    ->setRows(3)
                    ->setValue($category['description']);

            // Account Code
            $row = $form->addRow();
                $row->addLabel('accountCode', __('Account Code'))
                    ->description(__('Account code for accounting software integration (e.g., 5100).'));
                $row->addTextField('accountCode')
                    ->maxLength(20)
                    ->setValue($category['accountCode']);

            // Sort Order
            $row = $form->addRow();
                $row->addLabel('sortOrder', __('Sort Order'))
                    ->description(__('Display order in dropdown menus and lists.'));
                $row->addNumber('sortOrder')
                    ->minimum(0)
                    ->maximum(999)
                    ->required()
                    ->setValue($category['sortOrder']);

            // Is Active
            $row = $form->addRow();
                $row->addLabel('isActive', __('Active'))
                    ->description(__('Inactive categories cannot be used for new expenses.'));
                $row->addYesNo('isActive')
                    ->selected($category['isActive'] ? 'Y' : 'N')
                    ->required();

            // Submit
            $row = $form->addRow();
                $row->addFooter();
                $row->addSubmit(__('Update'));

            echo $form->getOutput();

            // Cancel link
            echo '<div class="mt-4">';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_expense_categories.php" class="text-blue-600 hover:underline">';
            echo '&larr; ' . __('Back to Category List');
            echo '</a>';
            echo '</div>';
        }
    }
    // Handle delete confirmation
    elseif ($action == 'delete' && !empty($gibbonEnhancedFinanceExpenseCategoryID)) {
        // Check if category has associated expenses
        $checkSQL = "SELECT COUNT(*) as count FROM gibbonEnhancedFinanceExpense
                     WHERE gibbonEnhancedFinanceExpenseCategoryID = :gibbonEnhancedFinanceExpenseCategoryID";
        $checkStmt = $pdo->prepare($checkSQL);
        $checkStmt->execute(['gibbonEnhancedFinanceExpenseCategoryID' => $gibbonEnhancedFinanceExpenseCategoryID]);
        $expenseCount = $checkStmt->fetch()['count'] ?? 0;

        // Fetch category data
        $categorySQL = "SELECT * FROM gibbonEnhancedFinanceExpenseCategory
                        WHERE gibbonEnhancedFinanceExpenseCategoryID = :gibbonEnhancedFinanceExpenseCategoryID";
        $categoryStmt = $pdo->prepare($categorySQL);
        $categoryStmt->execute(['gibbonEnhancedFinanceExpenseCategoryID' => $gibbonEnhancedFinanceExpenseCategoryID]);
        $category = $categoryStmt->fetch();

        if (empty($category)) {
            $page->addError(__('The selected expense category could not be found.'));
        } else {
            echo '<h3>';
            echo __('Delete Expense Category');
            echo '</h3>';

            if ($expenseCount > 0) {
                echo '<div class="error">';
                echo '<p>' . sprintf(__('This category has %d associated expense(s) and cannot be deleted. Please reassign or delete the expenses first, or deactivate the category instead.'), $expenseCount) . '</p>';
                echo '</div>';
            } else {
                echo '<div class="warning">';
                echo '<p>' . sprintf(__('Are you sure you want to delete the category "%s"? This action cannot be undone.'), $category['name']) . '</p>';
                echo '</div>';

                // Delete form
                $form = Form::create('categoryDelete', $session->get('absoluteURL') . '/modules/EnhancedFinance/finance_expense_categories_addProcess.php');
                $form->setClass('w-full max-w-lg');

                $form->addHiddenValue('address', $session->get('address'));
                $form->addHiddenValue('action', 'delete');
                $form->addHiddenValue('gibbonEnhancedFinanceExpenseCategoryID', $gibbonEnhancedFinanceExpenseCategoryID);

                $row = $form->addRow();
                    $row->addFooter();
                    $row->addSubmit(__('Yes, Delete Category'));

                echo $form->getOutput();
            }

            // Cancel link
            echo '<div class="mt-4">';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_expense_categories.php" class="text-blue-600 hover:underline">';
            echo '&larr; ' . __('Back to Category List');
            echo '</a>';
            echo '</div>';
        }
    }
    // Default: show list and add form
    else {
        // Category list section
        echo '<h3>';
        echo __('Expense Categories');
        echo '</h3>';

        // Fetch all categories with expense counts
        $listSQL = "SELECT
                        c.gibbonEnhancedFinanceExpenseCategoryID,
                        c.name,
                        c.description,
                        c.accountCode,
                        c.isActive,
                        c.sortOrder,
                        c.timestampCreated,
                        c.timestampModified,
                        COUNT(e.gibbonEnhancedFinanceExpenseID) AS expenseCount,
                        COALESCE(SUM(e.totalAmount), 0) AS totalExpenseAmount
                    FROM gibbonEnhancedFinanceExpenseCategory c
                    LEFT JOIN gibbonEnhancedFinanceExpense e ON c.gibbonEnhancedFinanceExpenseCategoryID = e.gibbonEnhancedFinanceExpenseCategoryID
                    GROUP BY c.gibbonEnhancedFinanceExpenseCategoryID
                    ORDER BY c.sortOrder ASC, c.name ASC";
        $listResult = $connection2->query($listSQL);
        $categories = $listResult->fetchAll();

        // Create DataTable
        $table = DataTable::create('expenseCategories');

        // Row modifier for inactive categories
        $table->modifyRows(function ($category, $row) {
            if (!$category['isActive']) {
                $row->addClass('dull');
            }
            return $row;
        });

        // Column: Sort Order
        $table->addColumn('sortOrder', __('Order'))
            ->width('5%');

        // Column: Name
        $table->addColumn('name', __('Name'))
            ->description(__('Account Code'))
            ->format(function ($category) {
                $output = '<b>' . $category['name'] . '</b>';
                if (!empty($category['accountCode'])) {
                    $output .= '<br/><span class="text-xs italic text-gray-500">' . $category['accountCode'] . '</span>';
                }
                return $output;
            });

        // Column: Description
        $table->addColumn('description', __('Description'))
            ->format(function ($category) {
                if (empty($category['description'])) {
                    return '<span class="text-gray-400">-</span>';
                }
                // Truncate long descriptions
                $desc = $category['description'];
                if (strlen($desc) > 80) {
                    $desc = substr($desc, 0, 77) . '...';
                }
                return $desc;
            });

        // Column: Status
        $table->addColumn('isActive', __('Status'))
            ->width('10%')
            ->format(function ($category) {
                if ($category['isActive']) {
                    return '<span class="text-green-600">' . __('Active') . '</span>';
                }
                return '<span class="text-red-600">' . __('Inactive') . '</span>';
            });

        // Column: Expense Count
        $table->addColumn('expenseCount', __('Expenses'))
            ->description(__('Total Amount'))
            ->width('15%')
            ->format(function ($category) {
                $output = '<b>' . $category['expenseCount'] . '</b> ' . __('expenses');
                if ((float)$category['totalExpenseAmount'] > 0) {
                    $output .= '<br/><span class="text-xs italic text-gray-500">' . Format::currency($category['totalExpenseAmount']) . '</span>';
                }
                return $output;
            });

        // Actions column
        $table->addActionColumn()
            ->addParam('gibbonEnhancedFinanceExpenseCategoryID')
            ->format(function ($category, $actions) {
                // Edit action
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/EnhancedFinance/finance_expense_categories.php')
                    ->addParam('action', 'edit');

                // Delete action - only if no expenses
                if ($category['expenseCount'] == 0) {
                    $actions->addAction('delete', __('Delete'))
                        ->setURL('/modules/EnhancedFinance/finance_expense_categories.php')
                        ->addParam('action', 'delete')
                        ->setIcon('delete');
                }
            });

        echo $table->render($categories);

        // Add new category form
        echo '<h3>';
        echo __('Add New Category');
        echo '</h3>';

        echo '<p class="text-gray-600 mb-4">';
        echo __('Use this form to create a new expense category. Categories help organize expenses and can be mapped to account codes for accounting software integration.');
        echo '</p>';

        // Get next sort order
        $nextSortSQL = "SELECT COALESCE(MAX(sortOrder), 0) + 1 AS nextSort FROM gibbonEnhancedFinanceExpenseCategory";
        $nextSortResult = $connection2->query($nextSortSQL);
        $nextSort = $nextSortResult->fetch()['nextSort'] ?? 1;

        $form = Form::create('categoryAdd', $session->get('absoluteURL') . '/modules/EnhancedFinance/finance_expense_categories_addProcess.php');
        $form->setClass('w-full max-w-2xl');

        $form->addHiddenValue('address', $session->get('address'));
        $form->addHiddenValue('action', 'add');

        // Name
        $row = $form->addRow();
            $row->addLabel('name', __('Category Name'))
                ->description(__('Unique name for this expense category.'));
            $row->addTextField('name')
                ->maxLength(100)
                ->required();

        // Description
        $row = $form->addRow();
            $row->addLabel('description', __('Description'))
                ->description(__('Optional description of what expenses belong in this category.'));
            $row->addTextArea('description')
                ->setRows(3);

        // Account Code
        $row = $form->addRow();
            $row->addLabel('accountCode', __('Account Code'))
                ->description(__('Account code for accounting software integration (e.g., 5100).'));
            $row->addTextField('accountCode')
                ->maxLength(20);

        // Sort Order
        $row = $form->addRow();
            $row->addLabel('sortOrder', __('Sort Order'))
                ->description(__('Display order in dropdown menus and lists.'));
            $row->addNumber('sortOrder')
                ->minimum(0)
                ->maximum(999)
                ->required()
                ->setValue($nextSort);

        // Is Active
        $row = $form->addRow();
            $row->addLabel('isActive', __('Active'))
                ->description(__('Inactive categories cannot be used for new expenses.'));
            $row->addYesNo('isActive')
                ->selected('Y')
                ->required();

        // Submit
        $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit();

        echo $form->getOutput();

        // Account Code Reference
        echo '<div class="mt-6 p-4 bg-gray-50 border rounded-lg">';
        echo '<h4 class="font-semibold mb-3">' . __('Common Account Codes Reference') . '</h4>';
        echo '<p class="text-sm text-gray-600 mb-3">' . __('These are common expense account codes used in accounting software. Consult your accountant for the correct codes for your chart of accounts.') . '</p>';
        echo '<div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">';
        echo '<div><span class="font-mono text-blue-600">5100</span> - ' . __('Payroll/Wages') . '</div>';
        echo '<div><span class="font-mono text-blue-600">5200</span> - ' . __('Supplies') . '</div>';
        echo '<div><span class="font-mono text-blue-600">5300</span> - ' . __('Utilities') . '</div>';
        echo '<div><span class="font-mono text-blue-600">5400</span> - ' . __('Rent/Lease') . '</div>';
        echo '<div><span class="font-mono text-blue-600">5500</span> - ' . __('Insurance') . '</div>';
        echo '<div><span class="font-mono text-blue-600">5600</span> - ' . __('Food/Meals') . '</div>';
        echo '<div><span class="font-mono text-blue-600">5700</span> - ' . __('Equipment') . '</div>';
        echo '<div><span class="font-mono text-blue-600">5800</span> - ' . __('Training') . '</div>';
        echo '<div><span class="font-mono text-blue-600">5900</span> - ' . __('Maintenance') . '</div>';
        echo '</div>';
        echo '</div>';
    }
}
