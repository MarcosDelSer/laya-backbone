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

// Enhanced Finance Module - Database Change Log
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = array();
$count = 0;

// v1.0.00 - Initial release
++$count;
$sql[$count][0] = '1.0.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinanceInvoice` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinancePayment` (
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
    CONSTRAINT `fk_enhanced_payment_invoice` FOREIGN KEY (`gibbonEnhancedFinanceInvoiceID`)
        REFERENCES `gibbonEnhancedFinanceInvoice`(`gibbonEnhancedFinanceInvoiceID`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinanceReleve24` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinanceContract` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'providerSIN', 'Provider SIN', 'Social Insurance Number of the childcare provider for RL-24 Box H (format: XXX-XXX-XXX)', '') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'providerName', 'Provider Name', 'Name of the childcare provider/organization for RL-24 documents', '') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'providerAddress', 'Provider Address', 'Full address of the childcare provider for RL-24 documents', '') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'providerNEQ', 'Provider NEQ', 'Quebec Enterprise Number (NEQ) of the childcare provider', '') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'invoicePrefix', 'Invoice Number Prefix', 'Prefix for generated invoice numbers (e.g., INV-)', 'INV-') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'gstRate', 'GST Rate', 'Goods and Services Tax rate as decimal (e.g., 0.05 for 5%)', '0.05') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'qstRate', 'QST Rate', 'Quebec Sales Tax rate as decimal (e.g., 0.09975 for 9.975%)', '0.09975') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'defaultPaymentTermsDays', 'Default Payment Terms (Days)', 'Default number of days for payment due date from invoice date', '30') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'rl24FilingDeadline', 'RL-24 Filing Deadline', 'Default filing deadline for RL-24 slips (last day of February)', '02-28') ON DUPLICATE KEY UPDATE scope=scope;end
";

// v1.0.01 - Bug fixes and minor improvements (placeholder for future updates)
++$count;
$sql[$count][0] = '1.0.01';
$sql[$count][1] = "";

// v1.0.02 - Expense tracking and accounting export
++$count;
$sql[$count][0] = '1.0.02';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinanceExpenseCategory` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinanceExpense` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonEnhancedFinanceExportLog` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonEnhancedFinanceExpenseCategory` (`name`, `description`, `accountCode`, `isActive`, `sortOrder`, `createdByID`) VALUES
    ('Payroll', 'Staff salaries and wages', '5100', 1, 1, 1),
    ('Supplies', 'Educational and office supplies', '5200', 1, 2, 1),
    ('Utilities', 'Electricity, water, gas, internet', '5300', 1, 3, 1),
    ('Rent', 'Facility rental costs', '5400', 1, 4, 1),
    ('Insurance', 'Liability and property insurance', '5500', 1, 5, 1),
    ('Food', 'Meals and snacks for children', '5600', 1, 6, 1),
    ('Equipment', 'Furniture, toys, and equipment', '5700', 1, 7, 1),
    ('Professional Development', 'Staff training and certifications', '5800', 1, 8, 1),
    ('Maintenance', 'Building and equipment repairs', '5900', 1, 9, 1),
    ('Other', 'Miscellaneous expenses', '5999', 1, 99, 1)
ON DUPLICATE KEY UPDATE name=name;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'expenseApprovalRequired', 'Expense Approval Required', 'Require approval for expenses before they can be marked as paid (Y/N)', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'expenseReceiptRequired', 'Expense Receipt Required', 'Require receipt upload for all expenses (Y/N)', 'N') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'sage50AccountsReceivable', 'Sage 50 Accounts Receivable Account', 'Account code for Accounts Receivable in Sage 50 exports', '1200') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'sage50RevenueAccount', 'Sage 50 Revenue Account', 'Account code for Revenue in Sage 50 exports', '4100') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'quickbooksIncomeAccount', 'QuickBooks Income Account', 'Account name for Income in QuickBooks exports', 'Childcare Revenue') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'quickbooksARAccount', 'QuickBooks A/R Account', 'Account name for Accounts Receivable in QuickBooks exports', 'Accounts Receivable') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Enhanced Finance', 'exportRetentionDays', 'Export File Retention (Days)', 'Number of days to retain exported files before automatic cleanup', '90') ON DUPLICATE KEY UPDATE scope=scope;end
";
