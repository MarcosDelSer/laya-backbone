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

// Medical Tracking Module - Database Change Log
// USE ;end TO SEPARATE SQL STATEMENTS. DON'T USE ;end IN ANY OTHER PLACES!

$sql = array();
$count = 0;

// v1.0.00 - Initial release
++$count;
$sql[$count][0] = '1.0.00';
$sql[$count][1] = "
CREATE TABLE IF NOT EXISTS `gibbonMedicalAllergy` (
    `gibbonMedicalAllergyID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `allergenName` VARCHAR(100) NOT NULL,
    `allergenType` ENUM('Food','Medication','Environmental','Insect','Other') NOT NULL DEFAULT 'Food',
    `severity` ENUM('Mild','Moderate','Severe','Life-Threatening') NOT NULL DEFAULT 'Moderate',
    `reaction` TEXT NULL COMMENT 'Description of allergic reaction',
    `treatment` TEXT NULL COMMENT 'Recommended treatment/response',
    `epiPenRequired` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `epiPenLocation` VARCHAR(255) NULL COMMENT 'Where EpiPen is stored',
    `diagnosedDate` DATE NULL,
    `diagnosedBy` VARCHAR(100) NULL COMMENT 'Doctor/specialist name',
    `verified` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `verifiedByID` INT UNSIGNED NULL COMMENT 'Staff who verified',
    `verifiedDate` DATE NULL,
    `notes` TEXT NULL,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `createdByID` INT UNSIGNED NOT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `allergenType` (`allergenType`),
    KEY `severity` (`severity`),
    KEY `active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonMedicalMedication` (
    `gibbonMedicalMedicationID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `medicationName` VARCHAR(100) NOT NULL,
    `medicationType` ENUM('Prescription','Over-the-Counter','Supplement','Other') NOT NULL DEFAULT 'Prescription',
    `dosage` VARCHAR(100) NOT NULL,
    `frequency` VARCHAR(100) NOT NULL COMMENT 'e.g., twice daily, as needed',
    `route` ENUM('Oral','Topical','Injection','Inhaled','Other') NOT NULL DEFAULT 'Oral',
    `prescribedBy` VARCHAR(100) NULL COMMENT 'Doctor name',
    `prescriptionDate` DATE NULL,
    `expirationDate` DATE NULL,
    `purpose` TEXT NULL COMMENT 'Reason for medication',
    `sideEffects` TEXT NULL COMMENT 'Known side effects to watch for',
    `storageLocation` VARCHAR(255) NULL COMMENT 'Where medication is stored at school',
    `administeredBy` ENUM('Self','Staff','Nurse') NOT NULL DEFAULT 'Staff',
    `parentConsent` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `parentConsentDate` DATE NULL,
    `verified` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `verifiedByID` INT UNSIGNED NULL,
    `verifiedDate` DATE NULL,
    `notes` TEXT NULL,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `createdByID` INT UNSIGNED NOT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `medicationType` (`medicationType`),
    KEY `active` (`active`),
    KEY `expirationDate` (`expirationDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonMedicalAccommodationPlan` (
    `gibbonMedicalAccommodationPlanID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `gibbonSchoolYearID` INT UNSIGNED NOT NULL,
    `planType` ENUM('504 Plan','IEP','Health Plan','Emergency Action Plan','Other') NOT NULL DEFAULT 'Health Plan',
    `planName` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `accommodations` TEXT NOT NULL COMMENT 'List of required accommodations',
    `emergencyProcedures` TEXT NULL COMMENT 'Emergency response procedures',
    `triggersSigns` TEXT NULL COMMENT 'Warning signs to watch for',
    `staffNotifications` TEXT NULL COMMENT 'Staff who need to be notified',
    `documentPath` VARCHAR(255) NULL COMMENT 'Path to uploaded plan document',
    `effectiveDate` DATE NOT NULL,
    `expirationDate` DATE NULL,
    `reviewDate` DATE NULL COMMENT 'Next review date',
    `approvedByID` INT UNSIGNED NULL,
    `approvedDate` DATE NULL,
    `status` ENUM('Draft','Pending Approval','Active','Expired','Archived') NOT NULL DEFAULT 'Draft',
    `notes` TEXT NULL,
    `createdByID` INT UNSIGNED NOT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `gibbonSchoolYearID` (`gibbonSchoolYearID`),
    KEY `planType` (`planType`),
    KEY `status` (`status`),
    KEY `effectiveDate` (`effectiveDate`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonMedicalAlert` (
    `gibbonMedicalAlertID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `gibbonPersonID` INT UNSIGNED NOT NULL,
    `alertType` ENUM('Allergy','Medication','Condition','Dietary','Emergency Contact','Other') NOT NULL,
    `alertLevel` ENUM('Info','Warning','Critical') NOT NULL DEFAULT 'Warning',
    `title` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `actionRequired` TEXT NULL COMMENT 'What staff should do',
    `displayOnDashboard` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `displayOnAttendance` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `displayOnReports` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `notifyOnCheckIn` ENUM('Y','N') NOT NULL DEFAULT 'N',
    `relatedAllergyID` INT UNSIGNED NULL COMMENT 'Link to gibbonMedicalAllergy',
    `relatedMedicationID` INT UNSIGNED NULL COMMENT 'Link to gibbonMedicalMedication',
    `relatedPlanID` INT UNSIGNED NULL COMMENT 'Link to gibbonMedicalAccommodationPlan',
    `effectiveDate` DATE NULL,
    `expirationDate` DATE NULL,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `createdByID` INT UNSIGNED NOT NULL,
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY `gibbonPersonID` (`gibbonPersonID`),
    KEY `alertType` (`alertType`),
    KEY `alertLevel` (`alertLevel`),
    KEY `active` (`active`),
    KEY `displayOnDashboard` (`displayOnDashboard`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

CREATE TABLE IF NOT EXISTS `gibbonMedicalAllergenMenu` (
    `gibbonMedicalAllergenMenuID` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `allergenName` VARCHAR(100) NOT NULL,
    `allergenCategory` ENUM('Food','Medication','Environmental','Insect','Other') NOT NULL DEFAULT 'Food',
    `commonSymptoms` TEXT NULL COMMENT 'Common allergic reactions',
    `avoidanceGuidelines` TEXT NULL COMMENT 'How to avoid exposure',
    `emergencyResponse` TEXT NULL COMMENT 'Standard emergency response',
    `aliases` TEXT NULL COMMENT 'Alternative names/ingredients to watch for',
    `displayOrder` INT UNSIGNED NOT NULL DEFAULT 0,
    `active` ENUM('Y','N') NOT NULL DEFAULT 'Y',
    `timestampCreated` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `timestampModified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `allergenName` (`allergenName`),
    KEY `allergenCategory` (`allergenCategory`),
    KEY `active` (`active`),
    KEY `displayOrder` (`displayOrder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;end

INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Tracking', 'alertDisplayMode', 'Alert Display Mode', 'How medical alerts are displayed on dashboards', 'Icon') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Tracking', 'requireVerification', 'Require Verification', 'Require staff verification for allergies and medications', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Tracking', 'notifyOnNewAllergy', 'Notify on New Allergy', 'Send notifications when new allergies are added', 'Y') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Tracking', 'medicationExpiryWarningDays', 'Medication Expiry Warning Days', 'Days before medication expiration to show warning', '30') ON DUPLICATE KEY UPDATE scope=scope;end
INSERT INTO `gibbonSetting` (`scope`, `name`, `nameDisplay`, `description`, `value`) VALUES ('Medical Tracking', 'planReviewWarningDays', 'Plan Review Warning Days', 'Days before accommodation plan review date to show warning', '14') ON DUPLICATE KEY UPDATE scope=scope;end

INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Peanuts', 'Food', 'Hives, swelling, difficulty breathing, anaphylaxis', 'Avoid all peanut products, check food labels carefully', 'Administer EpiPen if prescribed, call emergency services', 'Groundnuts, arachis oil, monkey nuts', 1, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Tree Nuts', 'Food', 'Hives, swelling, difficulty breathing, anaphylaxis', 'Avoid all tree nut products including almonds, cashews, walnuts', 'Administer EpiPen if prescribed, call emergency services', 'Almonds, cashews, walnuts, pecans, pistachios, macadamia', 2, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Milk', 'Food', 'Hives, stomach pain, vomiting, wheezing', 'Avoid dairy products, check labels for milk derivatives', 'Administer antihistamine, seek medical attention if severe', 'Dairy, lactose, casein, whey, lactalbumin', 3, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Eggs', 'Food', 'Skin reactions, respiratory issues, stomach pain', 'Avoid eggs and egg-containing products', 'Administer antihistamine, seek medical attention if severe', 'Albumin, globulin, lysozyme, mayonnaise', 4, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Wheat', 'Food', 'Hives, digestive issues, respiratory problems', 'Avoid wheat and wheat-containing products', 'Administer antihistamine, seek medical attention if severe', 'Gluten, flour, semolina, spelt, durum', 5, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Soy', 'Food', 'Hives, itching, digestive issues', 'Avoid soy products, check processed food labels', 'Administer antihistamine if needed', 'Soya, edamame, tofu, tempeh, miso', 6, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Fish', 'Food', 'Hives, swelling, difficulty breathing', 'Avoid all fish products, be cautious of cross-contamination', 'Administer EpiPen if prescribed, call emergency services', 'Cod, salmon, tuna, anchovies, fish sauce', 7, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Shellfish', 'Food', 'Hives, swelling, difficulty breathing, anaphylaxis', 'Avoid all shellfish including shrimp, crab, lobster', 'Administer EpiPen if prescribed, call emergency services', 'Shrimp, crab, lobster, crayfish, prawns', 8, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Sesame', 'Food', 'Hives, swelling, difficulty breathing', 'Avoid sesame seeds and sesame oil', 'Administer EpiPen if prescribed, call emergency services', 'Tahini, sesame oil, halvah, hummus', 9, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Penicillin', 'Medication', 'Skin rash, hives, itching, swelling, anaphylaxis', 'Avoid penicillin and related antibiotics', 'Stop medication, seek immediate medical attention', 'Amoxicillin, ampicillin, penicillin V', 10, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Bee Stings', 'Insect', 'Swelling, hives, difficulty breathing, anaphylaxis', 'Avoid areas with bees, wear protective clothing outdoors', 'Administer EpiPen if prescribed, call emergency services', 'Bee venom, wasp stings, hornet stings', 11, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Latex', 'Environmental', 'Skin irritation, hives, respiratory issues', 'Use non-latex gloves and products', 'Remove latex contact, administer antihistamine', 'Natural rubber, rubber gloves', 12, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Dust Mites', 'Environmental', 'Sneezing, runny nose, itchy eyes, asthma symptoms', 'Keep environment clean, use hypoallergenic bedding', 'Administer antihistamine, use prescribed inhaler if needed', 'House dust, dust allergies', 13, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
INSERT INTO `gibbonMedicalAllergenMenu` (`allergenName`, `allergenCategory`, `commonSymptoms`, `avoidanceGuidelines`, `emergencyResponse`, `aliases`, `displayOrder`, `active`) VALUES ('Pollen', 'Environmental', 'Sneezing, runny nose, itchy eyes, congestion', 'Limit outdoor activities during high pollen days', 'Administer antihistamine as needed', 'Hay fever, seasonal allergies, grass pollen, tree pollen', 14, 'Y') ON DUPLICATE KEY UPDATE allergenName=allergenName;end
";

// v1.0.01 - Bug fixes and minor improvements
++$count;
$sql[$count][0] = '1.0.01';
$sql[$count][1] = "";
