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
$name        = 'Enhanced Finance';
$description = 'Enhanced finance module for LAYA with Quebec RL-24 tax receipt generation, PDF export, and email delivery capabilities.';
$entryURL    = 'releve24_pdf_batch.php';
$type        = 'Additional';
$category    = 'Finance';
$version     = '1.0.02';
$author      = 'LAYA Development Team';
$url         = 'https://laya.education';

// Module tables (created in Task 020)
$moduleTables = [];

// gibbonSetting entries
$gibbonSetting = [];

// Action rows for gibbonAction
$actionRows = [];

$actionRows[] = [
    'name'                      => 'Generate RL-24 PDF',
    'precedence'                => 0,
    'category'                  => 'Tax Receipts',
    'description'               => 'Generate a single Quebec RL-24 tax receipt PDF for download or printing.',
    'URLList'                   => 'releve24_pdf.php',
    'entryURL'                  => 'releve24_pdf.php',
    'entrySidebar'              => 'N',
    'menuShow'                  => 'N',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

$actionRows[] = [
    'name'                      => 'Batch Generate RL-24 PDFs',
    'precedence'                => 0,
    'category'                  => 'Tax Receipts',
    'description'               => 'Generate multiple Quebec RL-24 tax receipt PDFs as a ZIP archive for bulk distribution.',
    'URLList'                   => 'releve24_pdf_batch.php',
    'entryURL'                  => 'releve24_pdf_batch.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'N',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'N',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'N',
    'categoryPermissionOther'   => 'N',
];

// Hooks (optional - for extending existing pages)
$hooks = [];
