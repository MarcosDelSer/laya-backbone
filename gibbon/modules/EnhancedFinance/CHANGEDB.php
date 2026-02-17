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

// v1.0.02 - Add RL-24 UUID-based table and email logging table
++$count;
$sql[$count][0] = '1.0.02';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `enhanced_finance_releve24` (
    `id` CHAR(36) NOT NULL PRIMARY KEY COMMENT 'UUID primary key',
    `gibbonPersonID` INT UNSIGNED NOT NULL COMMENT 'Child',
    `gibbonFamilyID` INT UNSIGNED NOT NULL COMMENT 'Family/recipient',
    `document_year` YEAR NOT NULL COMMENT 'Tax year for the document',
    `total_eligible` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total eligible childcare expenses',
    `total_paid` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total amounts paid',
    `status` ENUM('Draft','Generated','Sent','Filed','Amended') NOT NULL DEFAULT 'Draft',
    `generated_at` DATETIME NULL COMMENT 'When PDF was generated',
    `sent_at` DATETIME NULL COMMENT 'When email was sent to recipient',
    `created_by` INT UNSIGNED NOT NULL COMMENT 'Staff who created',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_person` (`gibbonPersonID`),
    INDEX `idx_family` (`gibbonFamilyID`),
    INDEX `idx_document_year` (`document_year`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='RL-24 documents with UUID primary key for PDF generation';end

CREATE TABLE IF NOT EXISTS `enhanced_finance_email_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `releve24_id` CHAR(36) NOT NULL COMMENT 'UUID of the RL-24 document',
    `recipient_email` VARCHAR(255) NOT NULL COMMENT 'Email address sent to',
    `success` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=success, 0=failure',
    `error_code` VARCHAR(50) NULL COMMENT 'Error code if failed',
    `error_message` TEXT NULL COMMENT 'Error details if failed',
    `ip_address` VARCHAR(45) NULL COMMENT 'IP address of sender',
    `user_agent` VARCHAR(255) NULL COMMENT 'User agent string',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_releve24` (`releve24_id`),
    INDEX `idx_recipient` (`recipient_email`),
    INDEX `idx_success` (`success`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Email send log for RL-24 compliance tracking';end
";
