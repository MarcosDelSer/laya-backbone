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

use Gibbon\Module\PhotoManagement\Domain\PhotoGateway;
use Gibbon\Module\AISync\AISyncService;
use Gibbon\Domain\System\SettingGateway;

// Include core (this file is called directly, not through module framework)
include '../../gibbon.php';

$URL = $session->get('absoluteURL') . '/index.php?q=/modules/PhotoManagement/photos.php';

if (isActionAccessible($guid, $connection2, '/modules/PhotoManagement/photos.php') == false) {
    $URL .= '&return=error0';
    header("Location: {$URL}");
    exit;
}

// Get photo ID
$gibbonPhotoUploadID = $_GET['gibbonPhotoUploadID'] ?? '';

if (empty($gibbonPhotoUploadID)) {
    $URL .= '&return=error1';
    header("Location: {$URL}");
    exit;
}

// Get gateway
$photoGateway = $container->get(PhotoGateway::class);

// Get photo to verify it exists
$photo = $photoGateway->getPhotoByID($gibbonPhotoUploadID);

if (empty($photo)) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

// Soft delete the photo (Quebec compliance - 5 year retention)
$gibbonPersonID = $session->get('gibbonPersonID');
$deleted = $photoGateway->softDelete($gibbonPhotoUploadID, $gibbonPersonID);

if ($deleted === false) {
    $URL .= '&return=error2';
    header("Location: {$URL}");
    exit;
}

// Trigger webhook for AI sync
try {
    $settingGateway = $container->get(SettingGateway::class);
    $aiSyncService = new AISyncService($settingGateway, $pdo);

    $photoData = [
        'gibbonPhotoUploadID' => $gibbonPhotoUploadID,
        'filename' => $photo['filename'] ?? '',
        'filePath' => $photo['filePath'] ?? '',
        'deletedByID' => $gibbonPersonID,
        'softDelete' => true,
    ];
    $aiSyncService->syncPhotoDelete($gibbonPhotoUploadID, $photoData);
} catch (Exception $e) {
    // Silently fail - don't break UX if webhook fails
}

// Success
$URL .= '&return=success0';
header("Location: {$URL}");
exit;
