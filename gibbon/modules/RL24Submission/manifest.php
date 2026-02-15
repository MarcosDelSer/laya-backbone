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
$name        = 'RL-24 Submission';
$description = 'Revenu Quebec RL-24 XML generation with government schema validation, batch processing, FO-0601 eligibility form digitization, and automatic summary calculations.';
$entryURL    = 'rl24_transmissions.php';
$type        = 'Additional';
$category    = 'Finance';
$version     = '1.0.00';
$author      = 'LAYA';
$url         = 'https://laya.ca';

// Module tables (defined in CHANGEDB.php)
$moduleTables = [
    'gibbonRL24Transmission',
    'gibbonRL24Slip',
    'gibbonRL24Eligibility',
    'gibbonRL24EligibilityDocument',
];

// gibbonSetting entries
$gibbonSetting = [];

$gibbonSetting[] = [
    'scope'       => 'RL-24 Submission',
    'name'        => 'preparerNumber',
    'nameDisplay' => 'Preparer Number',
    'description' => 'Revenu Quebec preparer identification number',
    'value'       => '',
];

$gibbonSetting[] = [
    'scope'       => 'RL-24 Submission',
    'name'        => 'providerName',
    'nameDisplay' => 'Provider Name',
    'description' => 'Childcare provider official name for RL-24 forms',
    'value'       => '',
];

$gibbonSetting[] = [
    'scope'       => 'RL-24 Submission',
    'name'        => 'providerNEQ',
    'nameDisplay' => 'Provider NEQ',
    'description' => 'Quebec Enterprise Number (NEQ) for the provider',
    'value'       => '',
];

$gibbonSetting[] = [
    'scope'       => 'RL-24 Submission',
    'name'        => 'providerAddress',
    'nameDisplay' => 'Provider Address',
    'description' => 'Official address for RL-24 forms',
    'value'       => '',
];

// Action rows for gibbonAction
$actionRows = [];

$actionRows[] = [
    'name'                      => 'RL-24 Transmissions',
    'precedence'                => 0,
    'category'                  => 'Tax Forms',
    'description'               => 'View and manage RL-24 batch transmissions for Revenu Quebec.',
    'URLList'                   => 'rl24_transmissions.php,rl24_transmissions_generate.php,rl24_transmissions_generateProcess.php,rl24_transmissions_view.php,rl24_transmissions_download.php',
    'entryURL'                  => 'rl24_transmissions.php',
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

$actionRows[] = [
    'name'                      => 'FO-0601 Eligibility Forms',
    'precedence'                => 0,
    'category'                  => 'Tax Forms',
    'description'               => 'Manage FO-0601 eligibility forms for childcare tax credits.',
    'URLList'                   => 'rl24_eligibility.php,rl24_eligibility_add.php,rl24_eligibility_addProcess.php,rl24_eligibility_edit.php,rl24_eligibility_documents.php',
    'entryURL'                  => 'rl24_eligibility.php',
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

$actionRows[] = [
    'name'                      => 'RL-24 Slips',
    'precedence'                => 0,
    'category'                  => 'Tax Forms',
    'description'               => 'View individual RL-24 slips with search and filter options.',
    'URLList'                   => 'rl24_slips.php',
    'entryURL'                  => 'rl24_slips.php',
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

$actionRows[] = [
    'name'                      => 'RL-24 Settings',
    'precedence'                => 0,
    'category'                  => 'Tax Forms',
    'description'               => 'Configure RL-24 module settings including preparer and provider information.',
    'URLList'                   => 'rl24_settings.php',
    'entryURL'                  => 'rl24_settings.php',
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
