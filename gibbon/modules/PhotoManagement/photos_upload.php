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

use Gibbon\Forms\Form;
use Gibbon\FileUploader;
use Gibbon\Services\Format;
use Gibbon\Domain\User\FamilyGateway;
use Gibbon\Module\PhotoManagement\Domain\PhotoGateway;
use Gibbon\Module\PhotoManagement\Domain\PhotoTagGateway;
use Gibbon\Module\PhotoManagement\Service\PhotoAccessService;
use Gibbon\Module\AISync\AISyncService;
use Gibbon\Domain\System\SettingGateway;

// Module includes
require_once __DIR__ . '/moduleFunctions.php';

if (isActionAccessible($guid, $connection2, '/modules/PhotoManagement/photos_upload.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Photo Gallery'), 'photos.php')
        ->add(__('Upload Photo'));

    // Get gateways and settings
    $photoGateway = $container->get(PhotoGateway::class);
    $photoTagGateway = $container->get(PhotoTagGateway::class);
    $familyGateway = $container->get(FamilyGateway::class);
    $settingGateway = $container->get(SettingGateway::class);

    // Initialize PhotoAccessService
    $photoAccessService = new PhotoAccessService($photoGateway, $photoTagGateway, $familyGateway, $settingGateway);

    // Get settings
    $maxSizeMB = $settingGateway->getSettingByScope('Photo Management', 'photoMaxSizeMB') ?? 10;
    $allowedTypes = $settingGateway->getSettingByScope('Photo Management', 'photoAllowedTypes') ?? 'jpg,jpeg,png,gif';
    $defaultShareWithParent = $settingGateway->getSettingByScope('Photo Management', 'photoDefaultShareWithParent') ?? 'Y';

    // Get AI Sync service for webhook notifications
    try {
        $aiSyncService = new AISyncService($settingGateway, $pdo);
    } catch (Exception $e) {
        $aiSyncService = null;
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/PhotoManagement/photos_upload.php';

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }

        $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
        $gibbonPersonID = $session->get('gibbonPersonID');

        // Validate required fields
        $caption = $_POST['caption'] ?? '';
        $sharedWithParent = $_POST['sharedWithParent'] ?? 'Y';

        // Process file upload
        if (empty($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        // Check file error
        if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
            $URL .= '&return=error3';
            header("Location: {$URL}");
            exit;
        }

        // Get file info
        $file = $_FILES['photo'];
        $fileSize = $file['size'];
        $mimeType = $file['type'];

        // Validate file type using service
        if (!$photoAccessService->isValidPhotoType($mimeType, $allowedTypes)) {
            $URL .= '&return=error3';
            header("Location: {$URL}");
            exit;
        }

        // Validate file size using service
        if (!$photoAccessService->isValidFileSize($fileSize)) {
            $URL .= '&return=error4';
            header("Location: {$URL}");
            exit;
        }

        // Validate file type for FileUploader
        $fileUploader = $container->get(FileUploader::class);
        $allowedTypesArray = explode(',', str_replace(' ', '', $allowedTypes));
        $fileUploader->setFileSuffixes($allowedTypesArray);

        // Generate upload path YYYY/MM
        $uploadPath = 'uploads/' . date('Y') . '/' . date('m');
        $absoluteUploadPath = $session->get('absolutePath') . '/' . $uploadPath;

        // Create directory if it doesn't exist
        if (!is_dir($absoluteUploadPath)) {
            mkdir($absoluteUploadPath, 0755, true);
        }

        // Upload the file
        $filename = $fileUploader->upload($file, $uploadPath);

        if (empty($filename)) {
            $URL .= '&return=error3';
            header("Location: {$URL}");
            exit;
        }

        // Get file path
        $filePath = $uploadPath . '/' . $filename;

        // Insert photo record
        $photoID = $photoGateway->insertPhoto([
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'filename' => $filename,
            'filePath' => $filePath,
            'caption' => $caption,
            'mimeType' => $mimeType,
            'fileSize' => $fileSize,
            'uploadedByID' => $gibbonPersonID,
            'sharedWithParent' => $sharedWithParent,
        ]);

        if ($photoID === false) {
            // Delete uploaded file if database insert failed
            @unlink($session->get('absolutePath') . '/' . $filePath);
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Trigger webhook for AI sync
        if ($aiSyncService !== null) {
            try {
                $photoData = [
                    'gibbonPhotoUploadID' => $photoID,
                    'gibbonSchoolYearID' => $gibbonSchoolYearID,
                    'filename' => $filename,
                    'filePath' => $filePath,
                    'caption' => $caption,
                    'mimeType' => $mimeType,
                    'fileSize' => $fileSize,
                    'uploadedByID' => $gibbonPersonID,
                    'sharedWithParent' => $sharedWithParent,
                ];
                $aiSyncService->syncPhotoUpload($photoID, $photoData);
            } catch (Exception $e) {
                // Silently fail - don't break UX if webhook fails
            }
        }

        // Success - redirect to tagging page
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/PhotoManagement/photos_tag.php&gibbonPhotoUploadID=' . $photoID . '&return=success0';
        header("Location: {$URL}");
        exit;
    }

    // Display return messages
    if (isset($_GET['return'])) {
        $editLink = '';
        switch ($_GET['return']) {
            case 'success0':
                $page->addMessage(__('Photo uploaded successfully. You can now tag children in this photo.'));
                break;
            case 'error0':
                $page->addError(__('Your request failed because you do not have access to this action.'));
                break;
            case 'error1':
                $page->addError(__('Your request failed because your inputs were invalid. Please select a photo to upload.'));
                break;
            case 'error2':
                $page->addError(__('Your request failed due to a database error.'));
                break;
            case 'error3':
                $page->addError(__('Your request failed because the uploaded file was invalid or the wrong type. Please upload a JPEG, PNG, or GIF image.'));
                break;
            case 'error4':
                $page->addError(__('Your request failed because the uploaded file was too large. Maximum size is %s MB.', $maxSizeMB));
                break;
        }
    }

    // Create form
    $form = Form::create('uploadPhoto', $session->get('absoluteURL') . '/index.php?q=/modules/PhotoManagement/photos_upload.php');
    $form->setTitle(__('Upload Photo'));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('csrf_token', $session->get('csrf_token'));

    // Photo file input
    $row = $form->addRow();
        $row->addLabel('photo', __('Photo'))
            ->description(sprintf(__('Allowed file types: %s. Maximum size: %s MB.'), $allowedTypes, $maxSizeMB));
        $row->addFileUpload('photo')
            ->required()
            ->accepts('.' . str_replace(',', ',.', $allowedTypes));

    // Caption
    $row = $form->addRow();
        $row->addLabel('caption', __('Caption'))
            ->description(__('Optional description for the photo.'));
        $row->addTextArea('caption')
            ->setRows(3)
            ->maxLength(1000);

    // Share with parents
    $row = $form->addRow();
        $row->addLabel('sharedWithParent', __('Share with Parents'))
            ->description(__('Allow parents to view this photo in their gallery.'));
        $row->addYesNo('sharedWithParent')
            ->required()
            ->selected($defaultShareWithParent);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Upload'));

    echo $form->getOutput();

    // Tips section
    echo '<div class="message">';
    echo '<h4>' . __('Photo Upload Tips') . '</h4>';
    echo '<ul>';
    echo '<li>' . __('Photos should be well-lit and clear.') . '</li>';
    echo '<li>' . __('After uploading, you will be able to tag children in the photo.') . '</li>';
    echo '<li>' . __('Photos shared with parents will appear in their gallery view.') . '</li>';
    echo '<li>' . __('Photos are retained for 5 years in compliance with Quebec regulations.') . '</li>';
    echo '</ul>';
    echo '</div>';
}
