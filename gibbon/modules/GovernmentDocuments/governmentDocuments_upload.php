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

use Gibbon\Forms\Form;
use Gibbon\FileUploader;
use Gibbon\Services\Format;
use Gibbon\Module\GovernmentDocuments\Domain\GovernmentDocumentGateway;

if (isActionAccessible($guid, $connection2, '/modules/GovernmentDocuments/governmentDocuments_upload.php') == false) {
    // Access denied
    $page->addError(__('You do not have access to this action.'));
} else {
    // Proceed!
    $page->breadcrumbs
        ->add(__('Government Documents'), 'governmentDocuments.php')
        ->add(__('Upload Document'));

    // Get parameters from URL
    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';
    $gibbonGovernmentDocumentTypeID = $_GET['gibbonGovernmentDocumentTypeID'] ?? '';
    $gibbonFamilyID = $_GET['gibbonFamilyID'] ?? '';

    // Get settings
    $maxSizeMB = 10; // Maximum file size in MB
    $allowedTypes = 'pdf,jpg,jpeg,png';

    // Get document gateway
    $documentGateway = $container->get(GovernmentDocumentGateway::class);

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_upload.php';

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }

        $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
        $uploadedByID = $session->get('gibbonPersonID');

        // Get POST data
        $gibbonPersonID = $_POST['gibbonPersonID'] ?? '';
        $gibbonGovernmentDocumentTypeID = $_POST['gibbonGovernmentDocumentTypeID'] ?? '';
        $gibbonFamilyID = $_POST['gibbonFamilyID'] ?? '';
        $documentNumber = $_POST['documentNumber'] ?? '';
        $issueDate = !empty($_POST['issueDate']) ? Format::dateConvert($_POST['issueDate']) : null;
        $expiryDate = !empty($_POST['expiryDate']) ? Format::dateConvert($_POST['expiryDate']) : null;
        $notes = $_POST['notes'] ?? '';

        // Validate required fields
        if (empty($gibbonPersonID) || empty($gibbonGovernmentDocumentTypeID)) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        // Process file upload
        if (empty($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
            $URL .= '&return=error1';
            header("Location: {$URL}");
            exit;
        }

        // Check file error
        if ($_FILES['document']['error'] !== UPLOAD_ERR_OK) {
            $URL .= '&return=error3';
            header("Location: {$URL}");
            exit;
        }

        // Validate file type
        $fileUploader = $container->get(FileUploader::class);
        $allowedTypesArray = explode(',', str_replace(' ', '', $allowedTypes));
        $fileUploader->setFileSuffixes($allowedTypesArray);

        // Generate upload path: uploads/governmentDocuments/{familyID}/YYYY/MM
        $uploadPath = 'uploads/governmentDocuments';
        if (!empty($gibbonFamilyID)) {
            $uploadPath .= '/' . $gibbonFamilyID;
        }
        $uploadPath .= '/' . date('Y') . '/' . date('m');

        $absoluteUploadPath = $session->get('absolutePath') . '/' . $uploadPath;

        // Create directory if it doesn't exist
        if (!is_dir($absoluteUploadPath)) {
            mkdir($absoluteUploadPath, 0755, true);
        }

        // Upload the file
        $file = $_FILES['document'];
        $originalFileName = $file['name'];
        $filename = $fileUploader->upload($file, $uploadPath);

        if (empty($filename)) {
            $URL .= '&return=error3';
            header("Location: {$URL}");
            exit;
        }

        // Get file info
        $filePath = $uploadPath . '/' . $filename;
        $fileSize = $file['size'];
        $mimeType = $file['type'];

        // Validate file size
        $maxSizeBytes = $maxSizeMB * 1024 * 1024;
        if ($fileSize > $maxSizeBytes) {
            // Delete uploaded file
            @unlink($session->get('absolutePath') . '/' . $filePath);
            $URL .= '&return=error4';
            header("Location: {$URL}");
            exit;
        }

        // Insert document record
        $documentID = $documentGateway->insertDocument([
            'gibbonGovernmentDocumentTypeID' => $gibbonGovernmentDocumentTypeID,
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'documentNumber' => $documentNumber,
            'issueDate' => $issueDate,
            'expiryDate' => $expiryDate,
            'filePath' => $filePath,
            'originalFileName' => $originalFileName,
            'fileSize' => $fileSize,
            'mimeType' => $mimeType,
            'status' => 'pending',
            'notes' => $notes,
            'uploadedByID' => $uploadedByID,
        ]);

        if ($documentID === false) {
            // Delete uploaded file if database insert failed
            @unlink($session->get('absolutePath') . '/' . $filePath);
            $URL .= '&return=error2';
            header("Location: {$URL}");
            exit;
        }

        // Insert audit log entry
        $documentGateway->insertLog(
            $documentID,
            $uploadedByID,
            'upload',
            null,
            'pending',
            'Document uploaded: ' . $originalFileName,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );

        // Success - redirect back to documents page
        $returnURL = $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments.php';
        if (!empty($gibbonFamilyID)) {
            $returnURL .= '&gibbonFamilyID=' . $gibbonFamilyID;
        }
        $returnURL .= '&return=success0';
        header("Location: {$returnURL}");
        exit;
    }

    // Display return messages
    if (isset($_GET['return'])) {
        switch ($_GET['return']) {
            case 'success0':
                $page->addMessage(__('Document uploaded successfully. It will be reviewed by staff.'));
                break;
            case 'error0':
                $page->addError(__('Your request failed because you do not have access to this action.'));
                break;
            case 'error1':
                $page->addError(__('Your request failed because your inputs were invalid. Please select a person, document type, and file to upload.'));
                break;
            case 'error2':
                $page->addError(__('Your request failed due to a database error.'));
                break;
            case 'error3':
                $page->addError(__('Your request failed because the uploaded file was invalid or the wrong type. Please upload a PDF, JPEG, or PNG file.'));
                break;
            case 'error4':
                $page->addError(sprintf(__('Your request failed because the uploaded file was too large. Maximum size is %s MB.'), $maxSizeMB));
                break;
        }
    }

    // Validate that we have required parameters for pre-filling
    $personName = '';
    $documentTypeName = '';

    if (!empty($gibbonPersonID)) {
        $personResult = $connection2->prepare("SELECT preferredName, surname FROM gibbonPerson WHERE gibbonPersonID = :gibbonPersonID");
        $personResult->execute(['gibbonPersonID' => $gibbonPersonID]);
        $person = $personResult->fetch();
        if ($person) {
            $personName = Format::name('', $person['preferredName'], $person['surname'], 'Student');
        }
    }

    if (!empty($gibbonGovernmentDocumentTypeID)) {
        $docType = $documentGateway->getDocumentTypeByID($gibbonGovernmentDocumentTypeID);
        if ($docType) {
            $documentTypeName = $docType['nameDisplay'];
        }
    }

    // Create form
    $form = Form::create('uploadDocument', $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_upload.php');
    $form->setTitle(__('Upload Government Document'));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('csrf_token', $session->get('csrf_token'));
    $form->addHiddenValue('gibbonFamilyID', $gibbonFamilyID);

    // Person selector (if not pre-selected)
    if (!empty($gibbonPersonID) && !empty($personName)) {
        $form->addHiddenValue('gibbonPersonID', $gibbonPersonID);
        $row = $form->addRow();
            $row->addLabel('personDisplay', __('Person'));
            $row->addTextField('personDisplay')
                ->setValue($personName)
                ->readonly();
    } else {
        // Get family members for selection
        $personOptions = [];
        if (!empty($gibbonFamilyID)) {
            $membersQuery = $connection2->prepare("
                SELECT DISTINCT p.gibbonPersonID, p.preferredName, p.surname, 'Child' as memberType
                FROM gibbonPerson p
                INNER JOIN gibbonFamilyChild fc ON p.gibbonPersonID = fc.gibbonPersonID
                WHERE fc.gibbonFamilyID = :gibbonFamilyID AND p.status = 'Full'
                UNION
                SELECT DISTINCT p.gibbonPersonID, p.preferredName, p.surname, 'Parent' as memberType
                FROM gibbonPerson p
                INNER JOIN gibbonFamilyAdult fa ON p.gibbonPersonID = fa.gibbonPersonID
                WHERE fa.gibbonFamilyID = :gibbonFamilyID AND p.status = 'Full'
                ORDER BY memberType, surname, preferredName
            ");
            $membersQuery->execute(['gibbonFamilyID' => $gibbonFamilyID]);
            while ($member = $membersQuery->fetch()) {
                $personOptions[$member['gibbonPersonID']] = Format::name('', $member['preferredName'], $member['surname'], 'Student') . ' (' . __($member['memberType']) . ')';
            }
        }

        $row = $form->addRow();
            $row->addLabel('gibbonPersonID', __('Person'))
                ->description(__('Select the family member this document belongs to.'));
            $row->addSelect('gibbonPersonID')
                ->fromArray($personOptions)
                ->required()
                ->placeholder(__('Select a person...'));
    }

    // Document type selector (if not pre-selected)
    if (!empty($gibbonGovernmentDocumentTypeID) && !empty($documentTypeName)) {
        $form->addHiddenValue('gibbonGovernmentDocumentTypeID', $gibbonGovernmentDocumentTypeID);
        $row = $form->addRow();
            $row->addLabel('documentTypeDisplay', __('Document Type'));
            $row->addTextField('documentTypeDisplay')
                ->setValue($documentTypeName)
                ->readonly();
    } else {
        // Get active document types
        $documentTypes = $documentGateway->selectActiveDocumentTypes();
        $typeOptions = [];
        foreach ($documentTypes as $type) {
            $typeOptions[$type['gibbonGovernmentDocumentTypeID']] = $type['nameDisplay'] . ' (' . __($type['category']) . ')';
        }

        $row = $form->addRow();
            $row->addLabel('gibbonGovernmentDocumentTypeID', __('Document Type'))
                ->description(__('Select the type of government document.'));
            $row->addSelect('gibbonGovernmentDocumentTypeID')
                ->fromArray($typeOptions)
                ->required()
                ->placeholder(__('Select a document type...'));
    }

    // Document file input
    $row = $form->addRow();
        $row->addLabel('document', __('Document File'))
            ->description(sprintf(__('Allowed file types: %s. Maximum size: %s MB.'), $allowedTypes, $maxSizeMB));
        $row->addFileUpload('document')
            ->required()
            ->accepts('.' . str_replace(',', ',.', $allowedTypes));

    // Document number (optional)
    $row = $form->addRow();
        $row->addLabel('documentNumber', __('Document Number'))
            ->description(__('Optional: Enter the document number or ID shown on the document.'));
        $row->addTextField('documentNumber')
            ->maxLength(100);

    // Issue date (optional)
    $row = $form->addRow();
        $row->addLabel('issueDate', __('Issue Date'))
            ->description(__('Optional: The date the document was issued.'));
        $row->addDate('issueDate');

    // Expiry date (optional)
    $row = $form->addRow();
        $row->addLabel('expiryDate', __('Expiry Date'))
            ->description(__('Optional: The date the document expires, if applicable.'));
        $row->addDate('expiryDate');

    // Notes (optional)
    $row = $form->addRow();
        $row->addLabel('notes', __('Notes'))
            ->description(__('Optional: Any additional notes about this document.'));
        $row->addTextArea('notes')
            ->setRows(3)
            ->maxLength(1000);

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Upload'));

    echo $form->getOutput();
}
