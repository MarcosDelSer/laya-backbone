<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuiber and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

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

// Module metadata
$name        = 'Photo Management';
$description = 'Manage photo uploads, child tagging, and galleries for LAYA kindergarten with 5-year soft-delete retention for Quebec regulatory compliance.';
$entryURL    = 'photos.php';
$type        = 'Additional';
$category    = 'Care';
$version     = '1.0.00';
$author      = 'LAYA Development Team';
$url         = 'https://laya.education';

// Module tables
$moduleTables = [
    'gibbonPhotoUpload',
    'gibbonPhotoTag',
    'gibbonPhotoRetention',
];

// Module actions (menu items)
$actionRows = [];

$actionRows[0] = [
    'name'                      => 'View Photos',
    'precedence'                => '0',
    'category'                  => 'Photos',
    'description'               => 'View and manage photo gallery',
    'URLList'                   => 'photos.php,photos_tag.php',
    'entryURL'                  => 'photos.php',
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

$actionRows[1] = [
    'name'                      => 'Upload Photos',
    'precedence'                => '0',
    'category'                  => 'Photos',
    'description'               => 'Upload new photos with optional captions',
    'URLList'                   => 'photos_upload.php',
    'entryURL'                  => 'photos_upload.php',
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

$actionRows[2] = [
    'name'                      => 'View Gallery',
    'precedence'                => '0',
    'category'                  => 'Photos',
    'description'               => 'View photo gallery filtered by child',
    'URLList'                   => 'photos_gallery.php',
    'entryURL'                  => 'photos_gallery.php',
    'entrySidebar'              => 'Y',
    'menuShow'                  => 'Y',
    'defaultPermissionAdmin'    => 'Y',
    'defaultPermissionTeacher'  => 'Y',
    'defaultPermissionStudent'  => 'N',
    'defaultPermissionParent'   => 'Y',
    'defaultPermissionSupport'  => 'Y',
    'categoryPermissionStaff'   => 'Y',
    'categoryPermissionStudent' => 'N',
    'categoryPermissionParent'  => 'Y',
    'categoryPermissionOther'   => 'N',
];

// Module settings
$gibbonSetting = [];

$gibbonSetting[0] = [
    'scope'       => 'Photo Management',
    'name'        => 'photoRetentionYears',
    'nameDisplay' => 'Photo Retention Years',
    'description' => 'Number of years to retain deleted photos before permanent deletion (Quebec compliance requires 5 years)',
    'value'       => '5',
];

$gibbonSetting[1] = [
    'scope'       => 'Photo Management',
    'name'        => 'photoMaxSizeMB',
    'nameDisplay' => 'Maximum Photo Size (MB)',
    'description' => 'Maximum file size for uploaded photos in megabytes',
    'value'       => '10',
];

$gibbonSetting[2] = [
    'scope'       => 'Photo Management',
    'name'        => 'photoAllowedTypes',
    'nameDisplay' => 'Allowed Photo Types',
    'description' => 'Comma-separated list of allowed file extensions',
    'value'       => 'jpg,jpeg,png,gif',
];

$gibbonSetting[3] = [
    'scope'       => 'Photo Management',
    'name'        => 'photoDefaultShareWithParent',
    'nameDisplay' => 'Default Share with Parents',
    'description' => 'Whether new photos are shared with parents by default',
    'value'       => 'Y',
];
