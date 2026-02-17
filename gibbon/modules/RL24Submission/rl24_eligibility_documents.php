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
use Gibbon\Tables\DataTable;
use Gibbon\Module\RL24Submission\Domain\RL24EligibilityGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('FO-0601 Eligibility Forms'), 'rl24_eligibility.php');
$page->breadcrumbs->add(__('Manage Documents'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/RL24Submission/rl24_eligibility.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get eligibility ID from URL
    $gibbonRL24EligibilityID = $_GET['gibbonRL24EligibilityID'] ?? '';

    if (empty($gibbonRL24EligibilityID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get eligibility gateway via DI container
    $eligibilityGateway = $container->get(RL24EligibilityGateway::class);

    // Get existing eligibility form data
    $eligibility = $eligibilityGateway->getEligibilityByID($gibbonRL24EligibilityID);

    if (empty($eligibility)) {
        $page->addError(__('The specified record does not exist.'));
        return;
    }

    // Get settings
    $settingGateway = $container->get(\Gibbon\Domain\System\SettingGateway::class);
    $maxSizeMB = 10; // 10MB max file size
    $allowedTypes = 'pdf,jpg,jpeg,png,gif';

    // Document types required for FO-0601 eligibility
    $documentTypes = [
        'ProofOfCitizenship' => __('Proof of Citizenship'),
        'ProofOfResidency' => __('Proof of Residency'),
        'BirthCertificate' => __('Child Birth Certificate'),
        'SINDocument' => __('SIN Documentation'),
        'ProofOfGuardianship' => __('Proof of Guardianship'),
        'Other' => __('Other Supporting Document'),
    ];

    // Get current user
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Handle document upload (POST)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_eligibility_documents.php&gibbonRL24EligibilityID=' . $gibbonRL24EligibilityID;

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $URL .= '&return=error0';
            header("Location: {$URL}");
            exit;
        }

        $action = $_POST['action'];

        if ($action === 'upload') {
            // Handle document upload
            $documentType = $_POST['documentType'] ?? '';
            $documentName = $_POST['documentName'] ?? '';

            // Validate required fields
            if (empty($documentType) || empty($documentName)) {
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

            // Generate upload path for RL-24 documents
            $uploadPath = 'uploads/rl24/' . date('Y') . '/' . date('m');
            $absoluteUploadPath = $session->get('absolutePath') . '/' . $uploadPath;

            // Create directory if it doesn't exist
            if (!is_dir($absoluteUploadPath)) {
                mkdir($absoluteUploadPath, 0755, true);
            }

            // Upload the file
            $file = $_FILES['document'];
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
            $data = [
                'gibbonRL24EligibilityID' => $gibbonRL24EligibilityID,
                'documentType' => $documentType,
                'documentName' => $documentName,
                'filePath' => $filePath,
                'fileType' => $mimeType,
                'fileSize' => $fileSize,
                'uploadedByID' => $gibbonPersonID,
                'verificationStatus' => 'Pending',
            ];

            $sql = "INSERT INTO gibbonRL24EligibilityDocument
                    (gibbonRL24EligibilityID, documentType, documentName, filePath, fileType, fileSize, uploadedByID, verificationStatus)
                    VALUES (:gibbonRL24EligibilityID, :documentType, :documentName, :filePath, :fileType, :fileSize, :uploadedByID, :verificationStatus)";

            try {
                $pdo->prepare($sql)->execute($data);
                $URL .= '&return=success0';
            } catch (\PDOException $e) {
                // Delete uploaded file if database insert failed
                @unlink($session->get('absolutePath') . '/' . $filePath);
                $URL .= '&return=error2';
            }

            header("Location: {$URL}");
            exit;

        } elseif ($action === 'verify') {
            // Handle document verification
            $documentID = $_POST['gibbonRL24EligibilityDocumentID'] ?? '';
            $verificationStatus = $_POST['verificationStatus'] ?? '';
            $verificationNotes = $_POST['verificationNotes'] ?? '';

            if (empty($documentID) || empty($verificationStatus)) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit;
            }

            $data = [
                'verificationStatus' => $verificationStatus,
                'verificationNotes' => $verificationNotes,
                'verifiedByID' => $gibbonPersonID,
                'verifiedDate' => date('Y-m-d'),
                'gibbonRL24EligibilityDocumentID' => $documentID,
            ];

            $sql = "UPDATE gibbonRL24EligibilityDocument
                    SET verificationStatus=:verificationStatus, verificationNotes=:verificationNotes,
                        verifiedByID=:verifiedByID, verifiedDate=:verifiedDate
                    WHERE gibbonRL24EligibilityDocumentID=:gibbonRL24EligibilityDocumentID";

            try {
                $pdo->prepare($sql)->execute($data);

                // Check if all documents are verified and update eligibility
                $checkSql = "SELECT COUNT(*) as total,
                             SUM(CASE WHEN verificationStatus='Verified' THEN 1 ELSE 0 END) as verified
                             FROM gibbonRL24EligibilityDocument
                             WHERE gibbonRL24EligibilityID=:gibbonRL24EligibilityID";
                $checkResult = $pdo->prepare($checkSql);
                $checkResult->execute(['gibbonRL24EligibilityID' => $gibbonRL24EligibilityID]);
                $counts = $checkResult->fetch();

                if ($counts && $counts['total'] > 0 && $counts['total'] == $counts['verified']) {
                    $eligibilityGateway->updateDocumentsComplete($gibbonRL24EligibilityID, true);
                } else {
                    $eligibilityGateway->updateDocumentsComplete($gibbonRL24EligibilityID, false);
                }

                $URL .= '&return=success1';
            } catch (\PDOException $e) {
                $URL .= '&return=error2';
            }

            header("Location: {$URL}");
            exit;

        } elseif ($action === 'delete') {
            // Handle document deletion
            $documentID = $_POST['gibbonRL24EligibilityDocumentID'] ?? '';

            if (empty($documentID)) {
                $URL .= '&return=error1';
                header("Location: {$URL}");
                exit;
            }

            // Get document info to delete file
            $docSql = "SELECT filePath FROM gibbonRL24EligibilityDocument WHERE gibbonRL24EligibilityDocumentID=:documentID";
            $docStmt = $pdo->prepare($docSql);
            $docStmt->execute(['documentID' => $documentID]);
            $document = $docStmt->fetch();

            if ($document) {
                // Delete file
                $fullPath = $session->get('absolutePath') . '/' . $document['filePath'];
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }

                // Delete record
                $deleteSql = "DELETE FROM gibbonRL24EligibilityDocument WHERE gibbonRL24EligibilityDocumentID=:documentID";
                try {
                    $pdo->prepare($deleteSql)->execute(['documentID' => $documentID]);

                    // Update documents complete status
                    $eligibilityGateway->updateDocumentsComplete($gibbonRL24EligibilityID, false);

                    $URL .= '&return=success2';
                } catch (\PDOException $e) {
                    $URL .= '&return=error2';
                }
            } else {
                $URL .= '&return=error5';
            }

            header("Location: {$URL}");
            exit;
        }
    }

    // Display return messages
    if (isset($_GET['return'])) {
        switch ($_GET['return']) {
            case 'success0':
                $page->addMessage(__('Document uploaded successfully.'));
                break;
            case 'success1':
                $page->addMessage(__('Document verification status updated.'));
                break;
            case 'success2':
                $page->addMessage(__('Document deleted successfully.'));
                break;
            case 'error0':
                $page->addError(__('Your request failed because you do not have access to this action.'));
                break;
            case 'error1':
                $page->addError(__('Your request failed because your inputs were invalid.'));
                break;
            case 'error2':
                $page->addError(__('Your request failed due to a database error.'));
                break;
            case 'error3':
                $page->addError(__('Your request failed because the uploaded file was invalid or the wrong type. Allowed types: %s', $allowedTypes));
                break;
            case 'error4':
                $page->addError(__('Your request failed because the uploaded file was too large. Maximum size is %s MB.', $maxSizeMB));
                break;
            case 'error5':
                $page->addError(__('The specified document does not exist.'));
                break;
        }
    }

    // Display eligibility form summary
    $childName = $eligibility['childFirstName'] . ' ' . $eligibility['childLastName'];
    $parentName = $eligibility['parentFirstName'] . ' ' . $eligibility['parentLastName'];

    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Eligibility Form Details') . '</h3>';
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';

    echo '<div>';
    echo '<p><strong>' . __('Child') . ':</strong> ' . htmlspecialchars($childName) . '</p>';
    echo '<p><strong>' . __('Parent/Guardian') . ':</strong> ' . htmlspecialchars($parentName) . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<p><strong>' . __('Tax Year') . ':</strong> ' . htmlspecialchars($eligibility['formYear']) . '</p>';
    $statusColors = [
        'Pending' => 'bg-yellow-100 text-yellow-800',
        'Approved' => 'bg-green-100 text-green-800',
        'Rejected' => 'bg-red-100 text-red-800',
        'Incomplete' => 'bg-gray-100 text-gray-800',
    ];
    $statusColor = $statusColors[$eligibility['approvalStatus']] ?? 'bg-gray-100 text-gray-800';
    echo '<p><strong>' . __('Status') . ':</strong> <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ' . $statusColor . '">' . __($eligibility['approvalStatus']) . '</span></p>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Get existing documents
    $docSql = "SELECT d.*,
               uploader.preferredName as uploaderName, uploader.surname as uploaderSurname,
               verifier.preferredName as verifierName, verifier.surname as verifierSurname
               FROM gibbonRL24EligibilityDocument d
               LEFT JOIN gibbonPerson as uploader ON d.uploadedByID=uploader.gibbonPersonID
               LEFT JOIN gibbonPerson as verifier ON d.verifiedByID=verifier.gibbonPersonID
               WHERE d.gibbonRL24EligibilityID=:gibbonRL24EligibilityID
               ORDER BY d.timestampCreated DESC";
    $docStmt = $pdo->prepare($docSql);
    $docStmt->execute(['gibbonRL24EligibilityID' => $gibbonRL24EligibilityID]);
    $documents = $docStmt->fetchAll();

    // Document Checklist
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Document Checklist') . '</h3>';
    echo '<p class="text-sm text-gray-600 mb-4">' . __('The following documents may be required to verify eligibility for childcare tax credits.') . '</p>';

    // Check which document types have been uploaded
    $uploadedTypes = [];
    $verifiedTypes = [];
    foreach ($documents as $doc) {
        $uploadedTypes[$doc['documentType']] = true;
        if ($doc['verificationStatus'] === 'Verified') {
            $verifiedTypes[$doc['documentType']] = true;
        }
    }

    echo '<ul class="space-y-2">';
    foreach ($documentTypes as $type => $label) {
        $icon = '';
        $iconClass = '';
        $statusText = '';

        if (isset($verifiedTypes[$type])) {
            $icon = '&#10004;';
            $iconClass = 'text-green-600';
            $statusText = '<span class="text-xs text-green-600 ml-2">' . __('Verified') . '</span>';
        } elseif (isset($uploadedTypes[$type])) {
            $icon = '&#8987;';
            $iconClass = 'text-yellow-600';
            $statusText = '<span class="text-xs text-yellow-600 ml-2">' . __('Pending Review') . '</span>';
        } else {
            $icon = '&#9711;';
            $iconClass = 'text-gray-400';
            $statusText = '<span class="text-xs text-gray-400 ml-2">' . __('Not Uploaded') . '</span>';
        }

        echo '<li class="flex items-center">';
        echo '<span class="' . $iconClass . ' mr-2">' . $icon . '</span>';
        echo '<span>' . $label . '</span>';
        echo $statusText;
        echo '</li>';
    }
    echo '</ul>';
    echo '</div>';

    // Existing Documents Table
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Uploaded Documents') . '</h3>';

    if (empty($documents)) {
        echo '<div class="p-4 bg-gray-50 rounded-lg text-gray-600 mb-6">';
        echo __('No documents have been uploaded yet.');
        echo '</div>';
    } else {
        echo '<div class="overflow-x-auto mb-6">';
        echo '<table class="min-w-full bg-white border rounded-lg">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="px-4 py-2 text-left text-sm font-medium text-gray-700">' . __('Document Type') . '</th>';
        echo '<th class="px-4 py-2 text-left text-sm font-medium text-gray-700">' . __('Name') . '</th>';
        echo '<th class="px-4 py-2 text-left text-sm font-medium text-gray-700">' . __('Status') . '</th>';
        echo '<th class="px-4 py-2 text-left text-sm font-medium text-gray-700">' . __('Uploaded') . '</th>';
        echo '<th class="px-4 py-2 text-left text-sm font-medium text-gray-700">' . __('Actions') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="divide-y">';

        foreach ($documents as $doc) {
            $docStatusColors = [
                'Pending' => 'bg-yellow-100 text-yellow-800',
                'Verified' => 'bg-green-100 text-green-800',
                'Rejected' => 'bg-red-100 text-red-800',
            ];
            $docStatusColor = $docStatusColors[$doc['verificationStatus']] ?? 'bg-gray-100 text-gray-800';

            echo '<tr>';
            echo '<td class="px-4 py-2 text-sm">' . htmlspecialchars($documentTypes[$doc['documentType']] ?? $doc['documentType']) . '</td>';
            echo '<td class="px-4 py-2 text-sm">' . htmlspecialchars($doc['documentName']) . '</td>';
            echo '<td class="px-4 py-2 text-sm"><span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ' . $docStatusColor . '">' . __($doc['verificationStatus']) . '</span></td>';
            echo '<td class="px-4 py-2 text-sm">';
            echo Format::dateTime($doc['timestampCreated']);
            if (!empty($doc['uploaderName'])) {
                echo '<br><span class="text-xs text-gray-500">' . __('by') . ' ' . htmlspecialchars($doc['uploaderName'] . ' ' . $doc['uploaderSurname']) . '</span>';
            }
            echo '</td>';
            echo '<td class="px-4 py-2 text-sm">';

            // View/Download link
            echo '<a href="' . $session->get('absoluteURL') . '/' . htmlspecialchars($doc['filePath']) . '" target="_blank" class="text-blue-600 hover:underline mr-2">' . __('View') . '</a>';

            // Verify button (only for pending documents)
            if ($doc['verificationStatus'] === 'Pending') {
                echo '<button type="button" onclick="openVerifyModal(' . $doc['gibbonRL24EligibilityDocumentID'] . ')" class="text-green-600 hover:underline mr-2">' . __('Verify') . '</button>';
            }

            // Delete button
            echo '<button type="button" onclick="confirmDelete(' . $doc['gibbonRL24EligibilityDocumentID'] . ')" class="text-red-600 hover:underline">' . __('Delete') . '</button>';

            echo '</td>';
            echo '</tr>';

            // Show verification notes if present
            if (!empty($doc['verificationNotes'])) {
                echo '<tr class="bg-gray-50">';
                echo '<td colspan="5" class="px-4 py-2 text-sm text-gray-600">';
                echo '<strong>' . __('Verification Notes') . ':</strong> ' . htmlspecialchars($doc['verificationNotes']);
                if (!empty($doc['verifierName']) && !empty($doc['verifiedDate'])) {
                    echo ' <span class="text-xs">(' . __('by') . ' ' . htmlspecialchars($doc['verifierName'] . ' ' . $doc['verifierSurname']) . ' ' . __('on') . ' ' . Format::date($doc['verifiedDate']) . ')</span>';
                }
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    // Upload Document Form
    $form = Form::create('uploadDocument', $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_eligibility_documents.php&gibbonRL24EligibilityID=' . $gibbonRL24EligibilityID);
    $form->setTitle(__('Upload New Document'));
    $form->addHiddenValue('address', $session->get('address'));
    $form->addHiddenValue('csrf_token', $session->get('csrf_token'));
    $form->addHiddenValue('action', 'upload');

    $row = $form->addRow();
        $row->addLabel('documentType', __('Document Type'));
        $row->addSelect('documentType')
            ->fromArray($documentTypes)
            ->placeholder(__('Please select...'))
            ->required();

    $row = $form->addRow();
        $row->addLabel('documentName', __('Document Name'))
            ->description(__('A descriptive name for this document.'));
        $row->addTextField('documentName')
            ->maxLength(100)
            ->required();

    $row = $form->addRow();
        $row->addLabel('document', __('Document File'))
            ->description(sprintf(__('Allowed file types: %s. Maximum size: %s MB.'), $allowedTypes, $maxSizeMB));
        $row->addFileUpload('document')
            ->required()
            ->accepts('.' . str_replace(',', ',.', $allowedTypes));

    $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Upload Document'));

    echo $form->getOutput();

    // Verification Modal (hidden by default)
    echo '<div id="verifyModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">';
    echo '<div class="bg-white rounded-lg shadow-lg p-6 max-w-md w-full mx-4">';
    echo '<h3 class="text-lg font-semibold mb-4">' . __('Verify Document') . '</h3>';
    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_eligibility_documents.php&gibbonRL24EligibilityID=' . $gibbonRL24EligibilityID . '">';
    echo '<input type="hidden" name="csrf_token" value="' . $session->get('csrf_token') . '">';
    echo '<input type="hidden" name="action" value="verify">';
    echo '<input type="hidden" name="gibbonRL24EligibilityDocumentID" id="verifyDocumentID" value="">';

    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium text-gray-700 mb-1">' . __('Verification Status') . '</label>';
    echo '<select name="verificationStatus" class="w-full border rounded px-3 py-2" required>';
    echo '<option value="">' . __('Please select...') . '</option>';
    echo '<option value="Verified">' . __('Verified') . '</option>';
    echo '<option value="Rejected">' . __('Rejected') . '</option>';
    echo '</select>';
    echo '</div>';

    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium text-gray-700 mb-1">' . __('Notes') . '</label>';
    echo '<textarea name="verificationNotes" class="w-full border rounded px-3 py-2" rows="3" placeholder="' . __('Optional notes about the verification...') . '"></textarea>';
    echo '</div>';

    echo '<div class="flex justify-end space-x-2">';
    echo '<button type="button" onclick="closeVerifyModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">' . __('Cancel') . '</button>';
    echo '<button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">' . __('Save') . '</button>';
    echo '</div>';

    echo '</form>';
    echo '</div>';
    echo '</div>';

    // Delete Confirmation Modal
    echo '<div id="deleteModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">';
    echo '<div class="bg-white rounded-lg shadow-lg p-6 max-w-md w-full mx-4">';
    echo '<h3 class="text-lg font-semibold mb-4">' . __('Delete Document') . '</h3>';
    echo '<p class="text-gray-600 mb-4">' . __('Are you sure you want to delete this document? This action cannot be undone.') . '</p>';
    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_eligibility_documents.php&gibbonRL24EligibilityID=' . $gibbonRL24EligibilityID . '">';
    echo '<input type="hidden" name="csrf_token" value="' . $session->get('csrf_token') . '">';
    echo '<input type="hidden" name="action" value="delete">';
    echo '<input type="hidden" name="gibbonRL24EligibilityDocumentID" id="deleteDocumentID" value="">';

    echo '<div class="flex justify-end space-x-2">';
    echo '<button type="button" onclick="closeDeleteModal()" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">' . __('Cancel') . '</button>';
    echo '<button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">' . __('Delete') . '</button>';
    echo '</div>';

    echo '</form>';
    echo '</div>';
    echo '</div>';

    // JavaScript for modals
    echo '<script>
    function openVerifyModal(documentID) {
        document.getElementById("verifyDocumentID").value = documentID;
        document.getElementById("verifyModal").classList.remove("hidden");
        document.getElementById("verifyModal").classList.add("flex");
    }

    function closeVerifyModal() {
        document.getElementById("verifyModal").classList.add("hidden");
        document.getElementById("verifyModal").classList.remove("flex");
    }

    function confirmDelete(documentID) {
        document.getElementById("deleteDocumentID").value = documentID;
        document.getElementById("deleteModal").classList.remove("hidden");
        document.getElementById("deleteModal").classList.add("flex");
    }

    function closeDeleteModal() {
        document.getElementById("deleteModal").classList.add("hidden");
        document.getElementById("deleteModal").classList.remove("flex");
    }

    // Close modals on background click
    document.getElementById("verifyModal").addEventListener("click", function(e) {
        if (e.target === this) closeVerifyModal();
    });
    document.getElementById("deleteModal").addEventListener("click", function(e) {
        if (e.target === this) closeDeleteModal();
    });
    </script>';

    // Information box
    echo '<div class="mt-6 p-4 bg-blue-50 rounded-lg">';
    echo '<h4 class="font-semibold mb-2">' . __('Document Requirements') . '</h4>';
    echo '<ul class="text-sm text-gray-600 list-disc list-inside">';
    echo '<li>' . __('Proof of Citizenship: Passport, citizenship card, or birth certificate.') . '</li>';
    echo '<li>' . __('Proof of Residency: Utility bill, lease agreement, or government correspondence with Quebec address.') . '</li>';
    echo '<li>' . __('Child Birth Certificate: Official birth certificate showing parent relationship.') . '</li>';
    echo '<li>' . __('SIN Documentation: Social Insurance Number card or letter from Service Canada.') . '</li>';
    echo '<li>' . __('Proof of Guardianship: Court order or legal documentation if applicable.') . '</li>';
    echo '</ul>';
    echo '<p class="text-sm text-gray-600 mt-2"><strong>' . __('Note') . ':</strong> ' . __('All documents must be verified before the eligibility form can be approved.') . '</p>';
    echo '</div>';
}
