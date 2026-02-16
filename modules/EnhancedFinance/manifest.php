<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuiber and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Licensing)

This is a Gibbon module for the LAYA childcare management platform.
*/

// Module Basic Information
$name        = 'Enhanced Finance';
$description = 'Comprehensive invoicing, payment tracking, financial dashboards, and Quebec Relevé 24 (RL-24) tax document generation for childcare facilities. Supports partial payments, multiple payment methods, and Quebec regulatory compliance.';
$entryURL    = 'finance.php';
$type        = 'Additional';
$category    = 'Finance';
$version     = '1.0.02';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module Tables - these will be created on module installation
$moduleTables = [];

// Invoice table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinanceInvoice` (
    `gibbonEnhancedFinanceInvoiceID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child being invoiced for',
    `gibbonFamilyID` INT UNSIGNED NOT NULL COMMENT 'Family for billing',
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `invoiceNumber` VARCHAR(50) NOT NULL UNIQUE,
    `invoiceDate` DATE NOT NULL,
    `dueDate` DATE NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `taxAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'GST/QST amount',
    `totalAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `paidAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `status` ENUM('Pending','Issued','Partial','Paid','Cancelled','Refunded') NOT NULL DEFAULT 'Pending',
    `notes` TEXT NULL,
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'Staff who created',
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_person` (`gibbonPersonID`),
    INDEX `idx_family` (`gibbonFamilyID`),
    INDEX `idx_school_year` (`gibbonSchoolYearID`),
    INDEX `idx_status` (`status`),
    INDEX `idx_invoice_date` (`invoiceDate`),
    INDEX `idx_due_date` (`dueDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Payment table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinancePayment` (
    `gibbonEnhancedFinancePaymentID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonEnhancedFinanceInvoiceID` INT UNSIGNED NOT NULL,
    `paymentDate` DATE NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `method` ENUM('Cash','Cheque','ETransfer','CreditCard','DebitCard','Other') NOT NULL DEFAULT 'Cash',
    `reference` VARCHAR(100) NULL COMMENT 'Payment reference/transaction ID',
    `notes` TEXT NULL,
    `recordedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who recorded',
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_invoice` (`gibbonEnhancedFinanceInvoiceID`),
    INDEX `idx_payment_date` (`paymentDate`),
    INDEX `idx_method` (`method`),
    CONSTRAINT `fk_payment_invoice` FOREIGN KEY (`gibbonEnhancedFinanceInvoiceID`)
        REFERENCES `gibbonEnhancedFinanceInvoice`(`gibbonEnhancedFinanceInvoiceID`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Quebec Releve 24 table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinanceReleve24` (
    `gibbonEnhancedFinanceReleve24ID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child',
    `gibbonFamilyID` INT UNSIGNED NOT NULL COMMENT 'Family/recipient',
    `taxYear` YEAR NOT NULL,
    `slipType` ENUM('R','A','D') NOT NULL DEFAULT 'R' COMMENT 'R=original, A=amended, D=cancelled',
    `slipNumber` VARCHAR(20) NULL UNIQUE,
    `daysOfCare` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Box B - actual paid days',
    `totalAmountsPaid` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Box C - total amounts paid',
    `nonQualifyingExpenses` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Box D - non-qualifying expenses',
    `qualifyingExpenses` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Box E - qualifying expenses (C - D)',
    `providerSIN` VARCHAR(11) NULL COMMENT 'Box H - provider SIN (XXX-XXX-XXX format)',
    `recipientSIN` VARCHAR(11) NULL COMMENT 'Recipient (parent) SIN',
    `recipientName` VARCHAR(100) NULL COMMENT 'Parent name',
    `childName` VARCHAR(100) NULL COMMENT 'Child name',
    `generatedAt` DATETIME NULL COMMENT 'When RL-24 was generated',
    `sentAt` DATETIME NULL COMMENT 'When RL-24 was sent to recipient',
    `status` ENUM('Draft','Generated','Sent','Filed','Amended') NOT NULL DEFAULT 'Draft',
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'Staff who created',
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_person` (`gibbonPersonID`),
    INDEX `idx_family` (`gibbonFamilyID`),
    INDEX `idx_tax_year` (`taxYear`),
    INDEX `idx_status` (`status`),
    INDEX `idx_slip_type` (`slipType`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Contract table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinanceContract` (
    `gibbonEnhancedFinanceContractID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child',
    `gibbonFamilyID` INT UNSIGNED NOT NULL COMMENT 'Family',
    `contractNumber` VARCHAR(50) NOT NULL UNIQUE,
    `startDate` DATE NOT NULL,
    `endDate` DATE NULL,
    `weeklyRate` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `daysPerWeek` INT UNSIGNED NOT NULL DEFAULT 5,
    `status` ENUM('Active','Suspended','Terminated','Expired') NOT NULL DEFAULT 'Active',
    `terms` TEXT NULL COMMENT 'Contract terms and conditions',
    `signedAt` DATETIME NULL COMMENT 'When contract was signed',
    `signedByID` INT UNSIGNED NULL COMMENT 'Parent who signed',
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'Staff who created',
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_person` (`gibbonPersonID`),
    INDEX `idx_family` (`gibbonFamilyID`),
    INDEX `idx_status` (`status`),
    INDEX `idx_start_date` (`startDate`),
    INDEX `idx_end_date` (`endDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Expense Category table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinanceExpenseCategory` (
    `gibbonEnhancedFinanceExpenseCategoryID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `accountCode` VARCHAR(20) NULL COMMENT 'Accounting software account code',
    `isActive` TINYINT(1) NOT NULL DEFAULT 1,
    `sortOrder` INT UNSIGNED NOT NULL DEFAULT 0,
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'Staff who created',
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE INDEX `idx_name` (`name`),
    INDEX `idx_active` (`isActive`),
    INDEX `idx_sort` (`sortOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Expense table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinanceExpense` (
    `gibbonEnhancedFinanceExpenseID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonEnhancedFinanceExpenseCategoryID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `expenseDate` DATE NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `taxAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'GST/QST amount if applicable',
    `totalAmount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'amount + taxAmount',
    `vendor` VARCHAR(150) NULL COMMENT 'Vendor/supplier name',
    `reference` VARCHAR(100) NULL COMMENT 'Invoice/receipt number',
    `paymentMethod` ENUM('Cash','Cheque','ETransfer','CreditCard','DebitCard','Other') NOT NULL DEFAULT 'Other',
    `description` TEXT NULL,
    `receiptPath` VARCHAR(255) NULL COMMENT 'Path to uploaded receipt file',
    `status` ENUM('Pending','Approved','Rejected','Paid') NOT NULL DEFAULT 'Pending',
    `approvedByID` INT UNSIGNED NULL COMMENT 'Staff who approved',
    `approvedAt` DATETIME NULL,
    `createdByID` INT UNSIGNED NOT NULL COMMENT 'Staff who created',
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_category` (`gibbonEnhancedFinanceExpenseCategoryID`),
    INDEX `idx_school_year` (`gibbonSchoolYearID`),
    INDEX `idx_expense_date` (`expenseDate`),
    INDEX `idx_status` (`status`),
    INDEX `idx_vendor` (`vendor`),
    CONSTRAINT `fk_expense_category` FOREIGN KEY (`gibbonEnhancedFinanceExpenseCategoryID`)
        REFERENCES `gibbonEnhancedFinanceExpenseCategory`(`gibbonEnhancedFinanceExpenseCategoryID`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Export Log table
$moduleTables[] = "CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinanceExportLog` (
    `gibbonEnhancedFinanceExportLogID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `exportType` ENUM('Sage50','QuickBooks','BankReconciliation','Revenue','Aging','Collection','Excel') NOT NULL,
    `exportFormat` VARCHAR(20) NOT NULL COMMENT 'File format: CSV, IIF, QBO, XLSX',
    `gibbonSchoolYearID` INT UNSIGNED NULL,
    `dateRangeStart` DATE NULL,
    `dateRangeEnd` DATE NULL,
    `recordCount` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of records exported',
    `totalAmount` DECIMAL(12,2) NULL COMMENT 'Total monetary value in export',
    `fileName` VARCHAR(255) NOT NULL,
    `filePath` VARCHAR(500) NOT NULL,
    `fileSize` INT UNSIGNED NULL COMMENT 'File size in bytes',
    `checksum` VARCHAR(64) NULL COMMENT 'SHA256 checksum for integrity',
    `status` ENUM('Pending','Processing','Completed','Failed') NOT NULL DEFAULT 'Pending',
    `errorMessage` TEXT NULL COMMENT 'Error details if export failed',
    `exportedByID` INT UNSIGNED NOT NULL COMMENT 'Staff who initiated export',
    `timestampCreated` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_export_type` (`exportType`),
    INDEX `idx_school_year` (`gibbonSchoolYearID`),
    INDEX `idx_date_range` (`dateRangeStart`, `dateRangeEnd`),
    INDEX `idx_status` (`status`),
    INDEX `idx_created` (`timestampCreated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

// Module Settings
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'providerSIN', 'Provider SIN', 'Social Insurance Number of the childcare provider for RL-24 Box H (format: XXX-XXX-XXX)', '')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'providerName', 'Provider Name', 'Name of the childcare provider/organization for RL-24 documents', '')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'providerAddress', 'Provider Address', 'Full address of the childcare provider for RL-24 documents', '')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'providerNEQ', 'Provider NEQ', 'Quebec Enterprise Number (NEQ) of the childcare provider', '')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'invoicePrefix', 'Invoice Number Prefix', 'Prefix for generated invoice numbers (e.g., INV-)', 'INV-')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'gstRate', 'GST Rate', 'Goods and Services Tax rate as decimal (e.g., 0.05 for 5%)', '0.05')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'qstRate', 'QST Rate', 'Quebec Sales Tax rate as decimal (e.g., 0.09975 for 9.975%)', '0.09975')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'defaultPaymentTermsDays', 'Default Payment Terms (Days)', 'Default number of days for payment due date from invoice date', '30')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'rl24FilingDeadline', 'RL-24 Filing Deadline', 'Default filing deadline for RL-24 slips (last day of February)', '02-28')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'expenseApprovalRequired', 'Expense Approval Required', 'Require approval for expenses before they can be marked as paid (Y/N)', 'Y')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'expenseReceiptRequired', 'Expense Receipt Required', 'Require receipt upload for all expenses (Y/N)', 'N')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'sage50AccountsReceivable', 'Sage 50 Accounts Receivable Account', 'Account code for Accounts Receivable in Sage 50 exports', '1200')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'sage50RevenueAccount', 'Sage 50 Revenue Account', 'Account code for Revenue in Sage 50 exports', '4100')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'quickbooksIncomeAccount', 'QuickBooks Income Account', 'Account name for Income in QuickBooks exports', 'Childcare Revenue')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'quickbooksARAccount', 'QuickBooks A/R Account', 'Account name for Accounts Receivable in QuickBooks exports', 'Accounts Receivable')
    ON DUPLICATE KEY UPDATE scope=scope;";

$gibbonSetting[] = "INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`)
    VALUES ('Enhanced Finance', 'exportRetentionDays', 'Export File Retention (Days)', 'Number of days to retain exported files before automatic cleanup', '90')
    ON DUPLICATE KEY UPDATE scope=scope;";

// Action Rows - define permissions for each page
$actionRows = [];

// Row 0 - Main entry / Finance Home
$actionRows[0]['name']                      = 'Finance Home';
$actionRows[0]['precedence']                = '0';
$actionRows[0]['category']                  = 'Finance';
$actionRows[0]['description']               = 'Main entry point for Enhanced Finance module';
$actionRows[0]['URLList']                   = 'finance.php';
$actionRows[0]['entryURL']                  = 'finance.php';
$actionRows[0]['entrySidebar']              = 'Y';
$actionRows[0]['menuShow']                  = 'Y';
$actionRows[0]['defaultPermissionAdmin']   = 'Y';
$actionRows[0]['defaultPermissionTeacher'] = 'N';
$actionRows[0]['defaultPermissionStudent'] = 'N';
$actionRows[0]['defaultPermissionParent']  = 'N';
$actionRows[0]['defaultPermissionSupport'] = 'Y';
$actionRows[0]['categoryPermissionStaff']  = 'Y';
$actionRows[0]['categoryPermissionStudent']= 'N';
$actionRows[0]['categoryPermissionParent'] = 'N';
$actionRows[0]['categoryPermissionOther']  = 'N';

// Row 1 - Financial Dashboard
$actionRows[1]['name']                      = 'Financial Dashboard';
$actionRows[1]['precedence']                = '0';
$actionRows[1]['category']                  = 'Finance';
$actionRows[1]['description']               = 'Overview of financial KPIs including revenue, outstanding invoices, and payment trends';
$actionRows[1]['URLList']                   = 'finance_dashboard.php';
$actionRows[1]['entryURL']                  = 'finance_dashboard.php';
$actionRows[1]['entrySidebar']              = 'Y';
$actionRows[1]['menuShow']                  = 'Y';
$actionRows[1]['defaultPermissionAdmin']   = 'Y';
$actionRows[1]['defaultPermissionTeacher'] = 'N';
$actionRows[1]['defaultPermissionStudent'] = 'N';
$actionRows[1]['defaultPermissionParent']  = 'N';
$actionRows[1]['defaultPermissionSupport'] = 'Y';
$actionRows[1]['categoryPermissionStaff']  = 'Y';
$actionRows[1]['categoryPermissionStudent']= 'N';
$actionRows[1]['categoryPermissionParent'] = 'N';
$actionRows[1]['categoryPermissionOther']  = 'N';

// Row 2 - Manage Invoices
$actionRows[2]['name']                      = 'Manage Invoices';
$actionRows[2]['precedence']                = '0';
$actionRows[2]['category']                  = 'Invoicing';
$actionRows[2]['description']               = 'View, create, and manage childcare invoices with filtering by status, date, and family';
$actionRows[2]['URLList']                   = 'finance_invoices.php,finance_invoice_add.php,finance_invoice_addProcess.php,finance_invoice_view.php,finance_invoice_edit.php,finance_invoice_editProcess.php';
$actionRows[2]['entryURL']                  = 'finance_invoices.php';
$actionRows[2]['entrySidebar']              = 'Y';
$actionRows[2]['menuShow']                  = 'Y';
$actionRows[2]['defaultPermissionAdmin']   = 'Y';
$actionRows[2]['defaultPermissionTeacher'] = 'N';
$actionRows[2]['defaultPermissionStudent'] = 'N';
$actionRows[2]['defaultPermissionParent']  = 'N';
$actionRows[2]['defaultPermissionSupport'] = 'Y';
$actionRows[2]['categoryPermissionStaff']  = 'Y';
$actionRows[2]['categoryPermissionStudent']= 'N';
$actionRows[2]['categoryPermissionParent'] = 'N';
$actionRows[2]['categoryPermissionOther']  = 'N';

// Row 3 - Record Payment
$actionRows[3]['name']                      = 'Record Payment';
$actionRows[3]['precedence']                = '0';
$actionRows[3]['category']                  = 'Invoicing';
$actionRows[3]['description']               = 'Record payments against invoices with multiple payment methods support';
$actionRows[3]['URLList']                   = 'finance_payment_add.php,finance_payment_addProcess.php';
$actionRows[3]['entryURL']                  = 'finance_payment_add.php';
$actionRows[3]['entrySidebar']              = 'N';
$actionRows[3]['menuShow']                  = 'N';
$actionRows[3]['defaultPermissionAdmin']   = 'Y';
$actionRows[3]['defaultPermissionTeacher'] = 'N';
$actionRows[3]['defaultPermissionStudent'] = 'N';
$actionRows[3]['defaultPermissionParent']  = 'N';
$actionRows[3]['defaultPermissionSupport'] = 'Y';
$actionRows[3]['categoryPermissionStaff']  = 'Y';
$actionRows[3]['categoryPermissionStudent']= 'N';
$actionRows[3]['categoryPermissionParent'] = 'N';
$actionRows[3]['categoryPermissionOther']  = 'N';

// Row 4 - Manage Contracts
$actionRows[4]['name']                      = 'Manage Contracts';
$actionRows[4]['precedence']                = '0';
$actionRows[4]['category']                  = 'Contracts';
$actionRows[4]['description']               = 'View and manage childcare contracts linked to families';
$actionRows[4]['URLList']                   = 'finance_contracts.php,finance_contract_add.php,finance_contract_addProcess.php,finance_contract_view.php,finance_contract_edit.php,finance_contract_editProcess.php';
$actionRows[4]['entryURL']                  = 'finance_contracts.php';
$actionRows[4]['entrySidebar']              = 'Y';
$actionRows[4]['menuShow']                  = 'Y';
$actionRows[4]['defaultPermissionAdmin']   = 'Y';
$actionRows[4]['defaultPermissionTeacher'] = 'N';
$actionRows[4]['defaultPermissionStudent'] = 'N';
$actionRows[4]['defaultPermissionParent']  = 'N';
$actionRows[4]['defaultPermissionSupport'] = 'Y';
$actionRows[4]['categoryPermissionStaff']  = 'Y';
$actionRows[4]['categoryPermissionStudent']= 'N';
$actionRows[4]['categoryPermissionParent'] = 'N';
$actionRows[4]['categoryPermissionOther']  = 'N';

// Row 5 - Quebec Releve 24 (RL-24)
$actionRows[5]['name']                      = 'Quebec Releve 24 (RL-24)';
$actionRows[5]['precedence']                = '0';
$actionRows[5]['category']                  = 'Tax Documents';
$actionRows[5]['description']               = 'Generate and manage Quebec RL-24 tax documents for childcare expenses. CRITICAL for Quebec regulatory compliance.';
$actionRows[5]['URLList']                   = 'finance_releve24.php,finance_releve24_generate.php,finance_releve24_generateProcess.php,finance_releve24_view.php';
$actionRows[5]['entryURL']                  = 'finance_releve24.php';
$actionRows[5]['entrySidebar']              = 'Y';
$actionRows[5]['menuShow']                  = 'Y';
$actionRows[5]['defaultPermissionAdmin']   = 'Y';
$actionRows[5]['defaultPermissionTeacher'] = 'N';
$actionRows[5]['defaultPermissionStudent'] = 'N';
$actionRows[5]['defaultPermissionParent']  = 'N';
$actionRows[5]['defaultPermissionSupport'] = 'N';
$actionRows[5]['categoryPermissionStaff']  = 'Y';
$actionRows[5]['categoryPermissionStudent']= 'N';
$actionRows[5]['categoryPermissionParent'] = 'N';
$actionRows[5]['categoryPermissionOther']  = 'N';

// Row 6 - Module Settings
$actionRows[6]['name']                      = 'Finance Settings';
$actionRows[6]['precedence']                = '0';
$actionRows[6]['category']                  = 'Settings';
$actionRows[6]['description']               = 'Configure Enhanced Finance module settings including provider information, tax rates, and RL-24 defaults';
$actionRows[6]['URLList']                   = 'finance_settings.php,finance_settingsProcess.php';
$actionRows[6]['entryURL']                  = 'finance_settings.php';
$actionRows[6]['entrySidebar']              = 'Y';
$actionRows[6]['menuShow']                  = 'Y';
$actionRows[6]['defaultPermissionAdmin']   = 'Y';
$actionRows[6]['defaultPermissionTeacher'] = 'N';
$actionRows[6]['defaultPermissionStudent'] = 'N';
$actionRows[6]['defaultPermissionParent']  = 'N';
$actionRows[6]['defaultPermissionSupport'] = 'N';
$actionRows[6]['categoryPermissionStaff']  = 'Y';
$actionRows[6]['categoryPermissionStudent']= 'N';
$actionRows[6]['categoryPermissionParent'] = 'N';
$actionRows[6]['categoryPermissionOther']  = 'N';

// Row 7 - Manage Expenses
$actionRows[7]['name']                      = 'Manage Expenses';
$actionRows[7]['precedence']                = '0';
$actionRows[7]['category']                  = 'Expenses';
$actionRows[7]['description']               = 'View, create, and manage facility expenses with filtering by status, category, vendor, and date range';
$actionRows[7]['URLList']                   = 'finance_expenses.php,finance_expense_add.php,finance_expense_addProcess.php,finance_expense_view.php,finance_expense_edit.php,finance_expense_editProcess.php,finance_expense_approve.php';
$actionRows[7]['entryURL']                  = 'finance_expenses.php';
$actionRows[7]['entrySidebar']              = 'Y';
$actionRows[7]['menuShow']                  = 'Y';
$actionRows[7]['defaultPermissionAdmin']   = 'Y';
$actionRows[7]['defaultPermissionTeacher'] = 'N';
$actionRows[7]['defaultPermissionStudent'] = 'N';
$actionRows[7]['defaultPermissionParent']  = 'N';
$actionRows[7]['defaultPermissionSupport'] = 'Y';
$actionRows[7]['categoryPermissionStaff']  = 'Y';
$actionRows[7]['categoryPermissionStudent']= 'N';
$actionRows[7]['categoryPermissionParent'] = 'N';
$actionRows[7]['categoryPermissionOther']  = 'N';

// Row 8 - Aging Report
$actionRows[8]['name']                      = 'Aging Report';
$actionRows[8]['precedence']                = '0';
$actionRows[8]['category']                  = 'Reports';
$actionRows[8]['description']               = 'Accounts receivable aging report with 30/60/90+ day buckets showing overdue invoices by age. Includes export to CSV functionality.';
$actionRows[8]['URLList']                   = 'finance_report_aging.php';
$actionRows[8]['entryURL']                  = 'finance_report_aging.php';
$actionRows[8]['entrySidebar']              = 'Y';
$actionRows[8]['menuShow']                  = 'Y';
$actionRows[8]['defaultPermissionAdmin']   = 'Y';
$actionRows[8]['defaultPermissionTeacher'] = 'N';
$actionRows[8]['defaultPermissionStudent'] = 'N';
$actionRows[8]['defaultPermissionParent']  = 'N';
$actionRows[8]['defaultPermissionSupport'] = 'Y';
$actionRows[8]['categoryPermissionStaff']  = 'Y';
$actionRows[8]['categoryPermissionStudent']= 'N';
$actionRows[8]['categoryPermissionParent'] = 'N';
$actionRows[8]['categoryPermissionOther']  = 'N';
