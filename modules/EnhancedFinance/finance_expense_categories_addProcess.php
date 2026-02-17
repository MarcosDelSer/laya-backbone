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
 * Enhanced Finance Module - Expense Category Add/Edit/Delete Process
 *
 * Processes expense category form submissions for creating, updating,
 * and deleting expense categories.
 *
 * @package    Gibbon\Module\EnhancedFinance
 * @author     LAYA
 * @license    GPL-3.0
 */

// Gibbon setup
require_once '../../gibbon.php';

// Build URL for return
$URL = $session->get('absoluteURL') . '/index.php?q=/modules/EnhancedFinance/finance_expense_categories.php';

// Access check
if (isActionAccessible($guid, $connection2, '/modules/EnhancedFinance/finance_expense_categories.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Get action type
$action = $_POST['action'] ?? '';

if (!in_array($action, ['add', 'edit', 'delete'])) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Handle DELETE action
if ($action == 'delete') {
    $gibbonEnhancedFinanceExpenseCategoryID = $_POST['gibbonEnhancedFinanceExpenseCategoryID'] ?? '';

    if (empty($gibbonEnhancedFinanceExpenseCategoryID)) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    }

    // Check if category exists
    $checkCategorySQL = "SELECT gibbonEnhancedFinanceExpenseCategoryID FROM gibbonEnhancedFinanceExpenseCategory
                         WHERE gibbonEnhancedFinanceExpenseCategoryID = :gibbonEnhancedFinanceExpenseCategoryID";
    $checkCategoryStmt = $pdo->prepare($checkCategorySQL);
    $checkCategoryStmt->execute(['gibbonEnhancedFinanceExpenseCategoryID' => $gibbonEnhancedFinanceExpenseCategoryID]);

    if ($checkCategoryStmt->rowCount() == 0) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Check if category has associated expenses
    $checkExpensesSQL = "SELECT COUNT(*) as count FROM gibbonEnhancedFinanceExpense
                         WHERE gibbonEnhancedFinanceExpenseCategoryID = :gibbonEnhancedFinanceExpenseCategoryID";
    $checkExpensesStmt = $pdo->prepare($checkExpensesSQL);
    $checkExpensesStmt->execute(['gibbonEnhancedFinanceExpenseCategoryID' => $gibbonEnhancedFinanceExpenseCategoryID]);
    $expenseCount = $checkExpensesStmt->fetch()['count'] ?? 0;

    if ($expenseCount > 0) {
        $URL .= '&return=error5';
        header("Location: {$URL}");
        exit;
    }

    // Delete the category
    try {
        $deleteSQL = "DELETE FROM gibbonEnhancedFinanceExpenseCategory
                      WHERE gibbonEnhancedFinanceExpenseCategoryID = :gibbonEnhancedFinanceExpenseCategoryID";
        $deleteStmt = $pdo->prepare($deleteSQL);
        $deleteStmt->execute(['gibbonEnhancedFinanceExpenseCategoryID' => $gibbonEnhancedFinanceExpenseCategoryID]);

        $URL .= '&return=success3';
        header("Location: {$URL}");
        exit;
    } catch (Exception $e) {
        $URL .= '&return=error4';
        header("Location: {$URL}");
        exit;
    }
}

// Handle ADD and EDIT actions
// Get form data
$gibbonEnhancedFinanceExpenseCategoryID = $_POST['gibbonEnhancedFinanceExpenseCategoryID'] ?? '';
$name = trim($_POST['name'] ?? '');
$description = trim($_POST['description'] ?? '');
$accountCode = trim($_POST['accountCode'] ?? '');
$sortOrder = (int) ($_POST['sortOrder'] ?? 0);
$isActive = ($_POST['isActive'] ?? 'Y') == 'Y' ? 1 : 0;

// Validate required fields
if (empty($name)) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Validate name length
if (strlen($name) > 100) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Validate accountCode length
if (strlen($accountCode) > 20) {
    $URL .= '&return=error3';
    header("Location: {$URL}");
    exit;
}

// Validate sortOrder range
if ($sortOrder < 0 || $sortOrder > 999) {
    $sortOrder = 0;
}

// Check for duplicate name (excluding current record if editing)
$checkNameSQL = "SELECT gibbonEnhancedFinanceExpenseCategoryID FROM gibbonEnhancedFinanceExpenseCategory
                 WHERE name = :name";
$checkNameParams = ['name' => $name];

if ($action == 'edit' && !empty($gibbonEnhancedFinanceExpenseCategoryID)) {
    $checkNameSQL .= " AND gibbonEnhancedFinanceExpenseCategoryID != :gibbonEnhancedFinanceExpenseCategoryID";
    $checkNameParams['gibbonEnhancedFinanceExpenseCategoryID'] = $gibbonEnhancedFinanceExpenseCategoryID;
}

$checkNameStmt = $pdo->prepare($checkNameSQL);
$checkNameStmt->execute($checkNameParams);

if ($checkNameStmt->rowCount() > 0) {
    $URL .= '&return=error6';
    header("Location: {$URL}");
    exit;
}

// Handle EDIT action
if ($action == 'edit') {
    if (empty($gibbonEnhancedFinanceExpenseCategoryID)) {
        $URL .= '&return=error3';
        header("Location: {$URL}");
        exit;
    }

    // Check if category exists
    $checkCategorySQL = "SELECT gibbonEnhancedFinanceExpenseCategoryID FROM gibbonEnhancedFinanceExpenseCategory
                         WHERE gibbonEnhancedFinanceExpenseCategoryID = :gibbonEnhancedFinanceExpenseCategoryID";
    $checkCategoryStmt = $pdo->prepare($checkCategorySQL);
    $checkCategoryStmt->execute(['gibbonEnhancedFinanceExpenseCategoryID' => $gibbonEnhancedFinanceExpenseCategoryID]);

    if ($checkCategoryStmt->rowCount() == 0) {
        $URL .= '&return=error2';
        header("Location: {$URL}");
        exit;
    }

    // Update the category
    try {
        $updateSQL = "UPDATE gibbonEnhancedFinanceExpenseCategory SET
                        name = :name,
                        description = :description,
                        accountCode = :accountCode,
                        sortOrder = :sortOrder,
                        isActive = :isActive
                      WHERE gibbonEnhancedFinanceExpenseCategoryID = :gibbonEnhancedFinanceExpenseCategoryID";

        $updateStmt = $pdo->prepare($updateSQL);
        $updateStmt->execute([
            'name' => $name,
            'description' => $description ?: null,
            'accountCode' => $accountCode ?: null,
            'sortOrder' => $sortOrder,
            'isActive' => $isActive,
            'gibbonEnhancedFinanceExpenseCategoryID' => $gibbonEnhancedFinanceExpenseCategoryID
        ]);

        $URL .= '&return=success2';
        header("Location: {$URL}");
        exit;
    } catch (Exception $e) {
        $URL .= '&return=error4';
        header("Location: {$URL}");
        exit;
    }
}

// Handle ADD action
if ($action == 'add') {
    // Insert the category
    try {
        $insertSQL = "INSERT INTO gibbonEnhancedFinanceExpenseCategory
                        (name, description, accountCode, sortOrder, isActive, createdByID)
                      VALUES
                        (:name, :description, :accountCode, :sortOrder, :isActive, :createdByID)";

        $insertStmt = $pdo->prepare($insertSQL);
        $insertStmt->execute([
            'name' => $name,
            'description' => $description ?: null,
            'accountCode' => $accountCode ?: null,
            'sortOrder' => $sortOrder,
            'isActive' => $isActive,
            'createdByID' => $session->get('gibbonPersonID')
        ]);

        $URL .= '&return=success1';
        header("Location: {$URL}");
        exit;
    } catch (Exception $e) {
        $URL .= '&return=error1';
        header("Location: {$URL}");
        exit;
    }
}

// Fallback - shouldn't reach here
$URL .= '&return=error3';
header("Location: {$URL}");
exit;
