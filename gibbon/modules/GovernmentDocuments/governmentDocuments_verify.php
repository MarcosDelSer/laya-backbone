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
use Gibbon\Tables\DataTable;
use Gibbon\Services\Format;
use Gibbon\Module\GovernmentDocuments\Domain\GovernmentDocumentGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Government Documents'), 'governmentDocuments.php');
$page->breadcrumbs->add(__('Document Verification'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/GovernmentDocuments/governmentDocuments_verify.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateway via DI container
    $documentGateway = $container->get(GovernmentDocumentGateway::class);

    // Status filter options
    $statusOptions = [
        ''         => __('All'),
        'pending'  => __('Pending'),
        'verified' => __('Verified'),
        'rejected' => __('Rejected'),
        'expired'  => __('Expired'),
    ];

    // Category filter options
    $categoryOptions = [
        ''         => __('All Categories'),
        'Child'    => __('Child'),
        'Parent'   => __('Parent'),
        'Staff'    => __('Staff'),
    ];

    // Get filters from request
    $statusFilter = $_GET['status'] ?? 'pending';
    $categoryFilter = $_GET['category'] ?? '';

    // Handle verification action
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $documentID = $_POST['gibbonGovernmentDocumentID'] ?? null;

        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $session->get('csrf_token')) {
            $page->addError(__('Your request failed because you do not have access to this action.'));
        } elseif (!empty($documentID)) {
            // Get current document for audit log
            $currentDocument = $documentGateway->getDocumentByID($documentID);
            $previousStatus = $currentDocument['status'] ?? null;

            if ($action === 'verify') {
                $result = $documentGateway->updateVerificationStatus(
                    $documentID,
                    'verified',
                    $gibbonPersonID,
                    null
                );

                if ($result) {
                    // Insert audit log
                    $documentGateway->insertLog(
                        $documentID,
                        $gibbonPersonID,
                        'verify',
                        $previousStatus,
                        'verified',
                        'Document verified by staff',
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null
                    );
                    $page->addSuccess(__('Document has been verified successfully.'));
                } else {
                    $page->addError(__('Failed to verify document.'));
                }
            } elseif ($action === 'reject') {
                $rejectionReason = $_POST['rejectionReason'] ?? '';

                if (empty($rejectionReason)) {
                    $page->addError(__('Please provide a reason for rejection.'));
                } else {
                    $result = $documentGateway->updateVerificationStatus(
                        $documentID,
                        'rejected',
                        $gibbonPersonID,
                        $rejectionReason
                    );

                    if ($result) {
                        // Insert audit log
                        $documentGateway->insertLog(
                            $documentID,
                            $gibbonPersonID,
                            'reject',
                            $previousStatus,
                            'rejected',
                            'Document rejected: ' . $rejectionReason,
                            $_SERVER['REMOTE_ADDR'] ?? null,
                            $_SERVER['HTTP_USER_AGENT'] ?? null
                        );
                        $page->addSuccess(__('Document has been rejected.'));
                    } else {
                        $page->addError(__('Failed to reject document.'));
                    }
                }
            } elseif ($action === 'revertToPending') {
                $result = $documentGateway->updateVerificationStatus(
                    $documentID,
                    'pending',
                    $gibbonPersonID,
                    null
                );

                if ($result) {
                    // Insert audit log
                    $documentGateway->insertLog(
                        $documentID,
                        $gibbonPersonID,
                        'revert',
                        $previousStatus,
                        'pending',
                        'Document reverted to pending status',
                        $_SERVER['REMOTE_ADDR'] ?? null,
                        $_SERVER['HTTP_USER_AGENT'] ?? null
                    );
                    $page->addSuccess(__('Document status has been reverted to pending.'));
                } else {
                    $page->addError(__('Failed to update document status.'));
                }
            }
        }
    }

    // Page header
    echo '<h2>' . __('Document Verification') . '</h2>';

    // Filter form
    $form = Form::create('verifyFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/GovernmentDocuments/governmentDocuments_verify.php');

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray($statusOptions)
            ->selected($statusFilter);

    $row = $form->addRow();
        $row->addLabel('category', __('Category'));
        $row->addSelect('category')
            ->fromArray($categoryOptions)
            ->selected($categoryFilter);

    $row = $form->addRow();
        $row->addSubmit(__('Filter'));

    echo $form->getOutput();

    // Get statistics
    $statistics = $documentGateway->getDocumentStatistics($gibbonSchoolYearID);

    // Summary statistics display
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Verification Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($statistics['totalDocuments'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total Documents') . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-yellow-600">' . ($statistics['pendingCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Pending Review') . '</span>';
    echo '</div>';

    echo '<div class="bg-green-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-green-600">' . ($statistics['verifiedCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Verified') . '</span>';
    echo '</div>';

    echo '<div class="bg-red-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-red-600">' . ($statistics['rejectedCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Rejected') . '</span>';
    echo '</div>';

    echo '<div class="bg-orange-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-orange-600">' . ($statistics['expiredCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Expired') . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Build query criteria
    $criteria = $documentGateway->newQueryCriteria()
        ->sortBy(['gibbonGovernmentDocument.timestampCreated'], 'DESC')
        ->filterBy('status', $statusFilter)
        ->filterBy('category', $categoryFilter)
        ->fromPOST();

    // Get documents for the current school year
    $documents = $documentGateway->queryDocuments($criteria, $gibbonSchoolYearID);

    // Build DataTable
    $table = DataTable::createPaginated('pendingDocuments', $criteria);
    $table->setTitle(__('Documents'));

    // Add columns
    $table->addColumn('image', __('Photo'))
        ->notSortable()
        ->format(function ($row) use ($session) {
            $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
        });

    $table->addColumn('name', __('Person'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
        });

    $table->addColumn('documentTypeDisplay', __('Document Type'))
        ->sortable(['documentTypeName'])
        ->format(function ($row) {
            return '<div>' . htmlspecialchars($row['documentTypeDisplay']) . '</div>' .
                   '<span class="text-xs text-gray-500">' . __($row['documentCategory']) . '</span>';
        });

    $table->addColumn('documentNumber', __('Document #'))
        ->format(function ($row) {
            return !empty($row['documentNumber']) ? htmlspecialchars($row['documentNumber']) : '-';
        });

    $table->addColumn('expiryDate', __('Expiry Date'))
        ->format(function ($row) {
            if (empty($row['expiryDate'])) {
                return '-';
            }
            $isExpired = $row['expiryDate'] < date('Y-m-d');
            $isExpiringSoon = !$isExpired && $row['expiryDate'] <= date('Y-m-d', strtotime('+30 days'));
            $colorClass = $isExpired ? 'text-red-600' : ($isExpiringSoon ? 'text-orange-500' : '');
            return '<span class="' . $colorClass . '">' . Format::date($row['expiryDate']) . '</span>';
        });

    $table->addColumn('status', __('Status'))
        ->sortable()
        ->format(function ($row) {
            $colors = [
                'pending'  => 'yellow',
                'verified' => 'green',
                'rejected' => 'red',
                'expired'  => 'orange',
            ];
            $color = $colors[$row['status']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __(ucfirst($row['status'])) . '</span>';
        });

    $table->addColumn('timestampCreated', __('Uploaded'))
        ->sortable()
        ->format(function ($row) {
            return Format::dateTime($row['timestampCreated']);
        });

    $table->addColumn('uploader', __('Uploaded By'))
        ->notSortable()
        ->format(function ($row) {
            if (!empty($row['uploaderPreferredName'])) {
                return Format::name('', $row['uploaderPreferredName'], $row['uploaderSurname'], 'Staff', false, true);
            }
            return '-';
        });

    // Add action column with verification actions
    $table->addActionColumn()
        ->addParam('gibbonGovernmentDocumentID')
        ->format(function ($row, $actions) use ($session) {
            // View document button
            if (!empty($row['filePath'])) {
                $actions->addAction('view', __('View Document'))
                    ->setIcon('view')
                    ->setURL($session->get('absoluteURL') . '/' . $row['filePath'])
                    ->directLink();
            }

            // Show different actions based on current status
            if ($row['status'] === 'pending') {
                $actions->addAction('verify', __('Verify'))
                    ->setIcon('iconTick')
                    ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_verify_action.php')
                    ->addParam('action', 'verify')
                    ->modalWindow(400, 250);

                $actions->addAction('reject', __('Reject'))
                    ->setIcon('iconCross')
                    ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_verify_action.php')
                    ->addParam('action', 'reject')
                    ->modalWindow(400, 350);
            } elseif ($row['status'] === 'verified' || $row['status'] === 'rejected') {
                $actions->addAction('revert', __('Revert to Pending'))
                    ->setIcon('refresh')
                    ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_verify_action.php')
                    ->addParam('action', 'revertToPending')
                    ->modalWindow(400, 250);
            }
        });

    // Output table or empty message
    if ($documents->count() > 0) {
        echo $table->render($documents);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        if ($statusFilter === 'pending') {
            echo __('No pending documents to review. All documents have been processed.');
        } else {
            echo __('No documents found matching the selected filters.');
        }
        echo '</div>';
    }

    // Quick verification section for pending documents (inline forms)
    if ($statusFilter === 'pending' && $documents->count() > 0) {
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Quick Verification') . '</h3>';
        echo '<p class="text-gray-600 mb-4">' . __('Use the forms below to quickly verify or reject pending documents.') . '</p>';

        echo '<div class="space-y-4">';
        foreach ($documents as $doc) {
            if ($doc['status'] !== 'pending') {
                continue;
            }

            $personName = Format::name('', $doc['preferredName'], $doc['surname'], 'Student');
            $documentType = $doc['documentTypeDisplay'];

            echo '<div class="bg-white rounded-lg shadow p-4 border-l-4 border-yellow-400">';
            echo '<div class="flex flex-wrap items-start justify-between gap-4">';

            // Document info
            echo '<div class="flex-1 min-w-0">';
            echo '<div class="font-medium">' . htmlspecialchars($personName) . ' - ' . htmlspecialchars($documentType) . '</div>';
            echo '<div class="text-sm text-gray-500">';
            if (!empty($doc['documentNumber'])) {
                echo __('Document #') . ': ' . htmlspecialchars($doc['documentNumber']) . ' | ';
            }
            echo __('Uploaded') . ': ' . Format::dateTime($doc['timestampCreated']);
            echo '</div>';
            if (!empty($doc['filePath'])) {
                echo '<a href="' . $session->get('absoluteURL') . '/' . htmlspecialchars($doc['filePath']) . '" target="_blank" class="text-blue-600 hover:underline text-sm">' . __('View Document') . '</a>';
            }
            echo '</div>';

            // Verification actions
            echo '<div class="flex items-center gap-2">';

            // Verify button
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_verify.php&status=' . $statusFilter . '" class="inline">';
            echo '<input type="hidden" name="csrf_token" value="' . $session->get('csrf_token') . '">';
            echo '<input type="hidden" name="action" value="verify">';
            echo '<input type="hidden" name="gibbonGovernmentDocumentID" value="' . $doc['gibbonGovernmentDocumentID'] . '">';
            echo '<button type="submit" class="bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600 text-sm">' . __('Verify') . '</button>';
            echo '</form>';

            // Reject form (inline with reason)
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_verify.php&status=' . $statusFilter . '" class="inline flex items-center gap-2">';
            echo '<input type="hidden" name="csrf_token" value="' . $session->get('csrf_token') . '">';
            echo '<input type="hidden" name="action" value="reject">';
            echo '<input type="hidden" name="gibbonGovernmentDocumentID" value="' . $doc['gibbonGovernmentDocumentID'] . '">';
            echo '<input type="text" name="rejectionReason" placeholder="' . __('Rejection reason...') . '" class="border rounded px-2 py-1 text-sm w-48" required>';
            echo '<button type="submit" class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 text-sm">' . __('Reject') . '</button>';
            echo '</form>';

            echo '</div>';
            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-6">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
