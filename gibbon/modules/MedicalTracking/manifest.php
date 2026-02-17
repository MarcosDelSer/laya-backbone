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

// This file describes the module, including database tables

// Basic variables
$name        = 'Medical Tracking';
$description = 'Comprehensive allergy and medical tracking with severity levels, accommodation plans, medication management, and real-time alerts.';
$entryURL    = 'medicalTracking.php';
$type        = 'Additional';
$category    = 'Childcare';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables & gibbonSetting entries
$moduleTables = [];

// gibbonSetting entries
$gibbonSetting = [];

$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Medical Tracking', 'alertOnAllergenExposure', 'Alert on Allergen Exposure', 'Automatically send alerts when a child with allergies is exposed to an allergen', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Medical Tracking', 'requireMedicationAcknowledgment', 'Require Medication Acknowledgment', 'Require parent acknowledgment for medication administration', 'Y');";
$gibbonSetting[] = "INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value) VALUES ('Medical Tracking', 'allergyAlertDisplayDuration', 'Allergy Alert Display Duration', 'Duration in seconds to display allergy alerts', '30');";

// Action rows for gibbonAction
$actionRows = [];

$actionRows[] = [
    'name'                      => 'Medical Tracking Dashboard',
    'precedence'                => 0,
    'category'                  => 'Medical',
    'description'               => 'View medical tracking dashboard for all children.',
    'URLList'                   => 'medicalTracking.php',
    'entryURL'                  => 'medicalTracking.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Allergy Management',
    'precedence'                => 0,
    'category'                  => 'Medical',
    'description'               => 'Manage allergies and allergen information for children.',
    'URLList'                   => 'medicalTracking_allergies.php',
    'entryURL'                  => 'medicalTracking_allergies.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Medication Management',
    'precedence'                => 0,
    'category'                  => 'Medical',
    'description'               => 'Manage medications and administration schedules for children.',
    'URLList'                   => 'medicalTracking_medications.php',
    'entryURL'                  => 'medicalTracking_medications.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Accommodation Plans',
    'precedence'                => 0,
    'category'                  => 'Medical',
    'description'               => 'View and manage accommodation plans for children with medical needs.',
    'URLList'                   => 'medicalTracking_accommodations.php',
    'entryURL'                  => 'medicalTracking_accommodations.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Medical Alerts',
    'precedence'                => 0,
    'category'                  => 'Medical',
    'description'               => 'View and manage medical alerts and notifications.',
    'URLList'                   => 'medicalTracking_alerts.php',
    'entryURL'                  => 'medicalTracking_alerts.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Medical Reports',
    'precedence'                => 0,
    'category'                  => 'Medical',
    'description'               => 'Generate medical tracking reports and summaries.',
    'URLList'                   => 'medicalTracking_reports.php',
    'entryURL'                  => 'medicalTracking_reports.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'N',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

// Hooks (optional - for extending existing pages)
$hooks = [];
