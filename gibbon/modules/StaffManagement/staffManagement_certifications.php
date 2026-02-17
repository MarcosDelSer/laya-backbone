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
use Gibbon\Module\StaffManagement\Domain\CertificationGateway;
use Gibbon\Module\StaffManagement\Domain\StaffProfileGateway;
use Gibbon\Module\StaffManagement\Domain\AuditLogGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Staff Management'), 'staffManagement.php');
$page->breadcrumbs->add(__('Certifications'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_certifications.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways via DI container
    $certificationGateway = $container->get(CertificationGateway::class);
    $staffProfileGateway = $container->get(StaffProfileGateway::class);
    $auditLogGateway = $container->get(AuditLogGateway::class);

    // Certification type options
    $certificationTypes = [
        'First Aid'        => __('First Aid'),
        'CPR'              => __('CPR'),
        'ECE Certificate'  => __('ECE Certificate'),
        'ECE Diploma'      => __('ECE Diploma'),
        'Police Check'     => __('Police Check'),
        'Driver License'   => __('Driver License'),
        'Food Handler'     => __('Food Handler'),
        'WHMIS'           => __('WHMIS'),
        'Other'           => __('Other'),
    ];

    // Status options
    $statusOptions = [
        'Valid'   => __('Valid'),
        'Pending' => __('Pending'),
        'Expired' => __('Expired'),
        'Revoked' => __('Revoked'),
    ];

    // Handle form actions
    $action = $_POST['action'] ?? '';
    $certificationID = $_POST['gibbonStaffCertificationID'] ?? null;

    if ($action === 'add') {
        $personID = $_POST['gibbonPersonID'] ?? null;
        $certificationType = $_POST['certificationType'] ?? '';
        $certificationName = $_POST['certificationName'] ?? '';
        $issuingOrganization = $_POST['issuingOrganization'] ?? null;
        $certificateNumber = $_POST['certificateNumber'] ?? null;
        $issueDate = !empty($_POST['issueDate']) ? Format::dateConvert($_POST['issueDate']) : null;
        $expiryDate = !empty($_POST['expiryDate']) ? Format::dateConvert($_POST['expiryDate']) : null;
        $isRequired = $_POST['isRequired'] ?? 'N';
        $notes = $_POST['notes'] ?? null;

        if (!empty($personID) && !empty($certificationType) && !empty($certificationName)) {
            $data = [
                'gibbonPersonID' => $personID,
                'certificationType' => $certificationType,
                'certificationName' => $certificationName,
                'issuingOrganization' => $issuingOrganization,
                'certificateNumber' => $certificateNumber,
                'issueDate' => $issueDate,
                'expiryDate' => $expiryDate,
                'isRequired' => $isRequired,
                'status' => 'Valid',
                'recordedByID' => $gibbonPersonID,
                'reminderSent' => 'N',
            ];

            if (!empty($notes)) {
                $data['notes'] = $notes;
            }

            $newID = $certificationGateway->insert($data);

            if ($newID !== false) {
                $page->addSuccess(__('Certification has been added successfully.'));

                // Log the action
                $auditLogGateway->logInsert(
                    'gibbonStaffCertification',
                    $newID,
                    $gibbonPersonID,
                    $session->get('session')
                );
            } else {
                $page->addError(__('Failed to add certification.'));
            }
        } else {
            $page->addError(__('Please fill in all required fields.'));
        }
    } elseif ($action === 'edit' && !empty($certificationID)) {
        $certificationType = $_POST['certificationType'] ?? '';
        $certificationName = $_POST['certificationName'] ?? '';
        $issuingOrganization = $_POST['issuingOrganization'] ?? null;
        $certificateNumber = $_POST['certificateNumber'] ?? null;
        $issueDate = !empty($_POST['issueDate']) ? Format::dateConvert($_POST['issueDate']) : null;
        $expiryDate = !empty($_POST['expiryDate']) ? Format::dateConvert($_POST['expiryDate']) : null;
        $isRequired = $_POST['isRequired'] ?? 'N';
        $status = $_POST['status'] ?? 'Valid';
        $notes = $_POST['notes'] ?? null;

        if (!empty($certificationType) && !empty($certificationName)) {
            // Get old values for audit
            $oldCertification = $certificationGateway->getCertificationByID($certificationID);

            $data = [
                'certificationType' => $certificationType,
                'certificationName' => $certificationName,
                'issuingOrganization' => $issuingOrganization,
                'certificateNumber' => $certificateNumber,
                'issueDate' => $issueDate,
                'expiryDate' => $expiryDate,
                'isRequired' => $isRequired,
                'status' => $status,
                'notes' => $notes,
            ];

            $result = $certificationGateway->update($certificationID, $data);

            if ($result !== false) {
                $page->addSuccess(__('Certification has been updated successfully.'));

                // Log the action
                $auditLogGateway->logUpdate(
                    'gibbonStaffCertification',
                    $certificationID,
                    json_encode($oldCertification),
                    json_encode($data),
                    $gibbonPersonID,
                    $session->get('session')
                );
            } else {
                $page->addError(__('Failed to update certification.'));
            }
        } else {
            $page->addError(__('Please fill in all required fields.'));
        }
    } elseif ($action === 'delete' && !empty($certificationID)) {
        // Get old values for audit
        $oldCertification = $certificationGateway->getCertificationByID($certificationID);

        $result = $certificationGateway->delete($certificationID);

        if ($result !== false) {
            $page->addSuccess(__('Certification has been deleted successfully.'));

            // Log the action
            $auditLogGateway->logDelete(
                'gibbonStaffCertification',
                $certificationID,
                json_encode($oldCertification),
                $gibbonPersonID,
                $session->get('session')
            );
        } else {
            $page->addError(__('Failed to delete certification.'));
        }
    }

    // Get filter parameters
    $filter = $_GET['filter'] ?? '';
    $filterStatus = $_GET['status'] ?? '';
    $filterType = $_GET['certificationType'] ?? '';
    $filterPerson = $_GET['gibbonPersonID'] ?? '';

    // Page header
    echo '<h2>' . __('Certification Management') . '</h2>';

    // Get summary statistics
    $summary = $certificationGateway->getCertificationSummaryStatistics();

    // Display summary cards
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Certification Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-center">';

    echo '<div class="bg-green-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-green-600">' . ($summary['totalValid'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Valid') . '</span>';
    echo '</div>';

    echo '<div class="bg-blue-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-blue-600">' . ($summary['totalPending'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Pending') . '</span>';
    echo '</div>';

    echo '<div class="bg-orange-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-orange-600">' . ($summary['expiringSoon'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Expiring Soon') . '</span>';
    echo '</div>';

    echo '<div class="bg-red-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-red-600">' . ($summary['totalExpired'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Expired') . '</span>';
    echo '</div>';

    echo '<div class="bg-purple-50 rounded p-3">';
    echo '<span class="block text-2xl font-bold text-purple-600">' . ($summary['totalRequiredValid'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Required Valid') . '</span>';
    echo '</div>';

    echo '<div class="bg-gray-50 rounded p-3">';
    $requiredExpired = $summary['totalRequiredExpired'] ?? 0;
    $expiredClass = $requiredExpired > 0 ? 'text-red-600 font-bold' : 'text-gray-600';
    echo '<span class="block text-2xl font-bold ' . $expiredClass . '">' . $requiredExpired . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Required Expired') . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Expiring Soon Alert Section
    $dateStart = date('Y-m-d');
    $dateEnd = date('Y-m-d', strtotime('+30 days'));
    $expiringSoonCriteria = $certificationGateway->newQueryCriteria();
    $expiringSoonData = $certificationGateway->queryCertificationsExpiringSoon($expiringSoonCriteria, $dateStart, $dateEnd);

    if ($expiringSoonData->count() > 0) {
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">';
        echo '<h3 class="text-lg font-semibold text-yellow-800 mb-3">' . __('Certifications Expiring Within 30 Days') . '</h3>';
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">';

        foreach ($expiringSoonData as $cert) {
            $staffName = Format::name('', $cert['preferredName'], $cert['surname'], 'Staff', false, true);
            $daysUntilExpiry = intval($cert['daysUntilExpiry']);
            $image = !empty($cert['image_240']) ? $cert['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            $urgencyClass = $daysUntilExpiry <= 7 ? 'bg-red-100 border-red-300' : ($daysUntilExpiry <= 14 ? 'bg-orange-100 border-orange-300' : 'bg-yellow-100 border-yellow-300');
            $textClass = $daysUntilExpiry <= 7 ? 'text-red-600' : ($daysUntilExpiry <= 14 ? 'text-orange-600' : 'text-yellow-600');

            echo '<div class="' . $urgencyClass . ' border rounded-lg p-3 flex items-center space-x-3">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-12 h-12 rounded-full object-cover" alt="">';
            echo '<div class="flex-1">';
            echo '<p class="font-semibold text-sm">' . htmlspecialchars($staffName) . '</p>';
            echo '<p class="text-xs text-gray-600">' . htmlspecialchars($cert['certificationName']) . '</p>';
            echo '<p class="text-xs ' . $textClass . ' font-semibold">';
            echo sprintf(__('Expires in %d day(s)'), $daysUntilExpiry);
            echo ' (' . Format::date($cert['expiryDate']) . ')';
            echo '</p>';
            echo '</div>';
            if ($cert['isRequired'] === 'Y') {
                echo '<span class="bg-red-200 text-red-800 text-xs px-2 py-1 rounded">' . __('Required') . '</span>';
            }
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Filter form
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';

    $form = Form::create('certificationFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/StaffManagement/staffManagement_certifications.php');

    $row = $form->addRow();
    $row->addLabel('status', __('Status'));
    $row->addSelect('status')->fromArray(['' => __('All')] + $statusOptions)->selected($filterStatus);

    $row = $form->addRow();
    $row->addLabel('certificationType', __('Certification Type'));
    $row->addSelect('certificationType')->fromArray(['' => __('All')] + $certificationTypes)->selected($filterType);

    // Get staff list for filter
    $staffCriteria = $staffProfileGateway->newQueryCriteria();
    $staffList = $staffProfileGateway->queryStaffProfiles($staffCriteria);
    $staffOptions = [];
    foreach ($staffList as $staff) {
        $staffOptions[$staff['gibbonPersonID']] = Format::name('', $staff['preferredName'], $staff['surname'], 'Staff', true, true);
    }

    $row = $form->addRow();
    $row->addLabel('gibbonPersonID', __('Staff Member'));
    $row->addSelect('gibbonPersonID')->fromArray(['' => __('All Staff')] + $staffOptions)->selected($filterPerson);

    // Quick filter options
    $row = $form->addRow();
    $col = $row->addColumn()->addClass('flex gap-2 flex-wrap');
    $col->addContent('<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php&filter=expired" class="text-red-600 hover:underline text-sm">' . __('Show Expired') . '</a>');
    $col->addContent('<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php&filter=expiring" class="text-orange-600 hover:underline text-sm">' . __('Show Expiring Soon') . '</a>');
    $col->addContent('<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php&filter=required" class="text-purple-600 hover:underline text-sm">' . __('Show Required Only') . '</a>');
    $col->addContent('<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php" class="text-blue-600 hover:underline text-sm">' . __('Clear Filters') . '</a>');

    $row = $form->addRow();
    $row->addSubmit(__('Filter'));

    echo $form->getOutput();
    echo '</div>';

    // Add New Certification Section
    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold text-green-800 mb-3">' . __('Add New Certification') . '</h3>';

    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php">';
    echo '<input type="hidden" name="action" value="add">';

    echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">';

    // Staff member
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Staff Member') . ' <span class="text-red-500">*</span></label>';
    echo '<select name="gibbonPersonID" class="w-full border rounded px-3 py-2" required>';
    echo '<option value="">' . __('Select Staff') . '</option>';
    foreach ($staffOptions as $id => $name) {
        echo '<option value="' . $id . '">' . htmlspecialchars($name) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Certification type
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Certification Type') . ' <span class="text-red-500">*</span></label>';
    echo '<select name="certificationType" class="w-full border rounded px-3 py-2" required>';
    echo '<option value="">' . __('Select Type') . '</option>';
    foreach ($certificationTypes as $value => $label) {
        echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // Certification name
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Certification Name') . ' <span class="text-red-500">*</span></label>';
    echo '<input type="text" name="certificationName" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Standard First Aid') . '" required>';
    echo '</div>';

    // Issuing organization
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Issuing Organization') . '</label>';
    echo '<input type="text" name="issuingOrganization" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Red Cross') . '">';
    echo '</div>';

    // Certificate number
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Certificate Number') . '</label>';
    echo '<input type="text" name="certificateNumber" class="w-full border rounded px-3 py-2">';
    echo '</div>';

    // Issue date
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Issue Date') . '</label>';
    echo '<input type="date" name="issueDate" class="w-full border rounded px-3 py-2">';
    echo '</div>';

    // Expiry date
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Expiry Date') . '</label>';
    echo '<input type="date" name="expiryDate" class="w-full border rounded px-3 py-2">';
    echo '</div>';

    // Is required
    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Required Certification') . '</label>';
    echo '<select name="isRequired" class="w-full border rounded px-3 py-2">';
    echo '<option value="N">' . __('No') . '</option>';
    echo '<option value="Y">' . __('Yes') . '</option>';
    echo '</select>';
    echo '</div>';

    // Notes
    echo '<div class="md:col-span-3">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
    echo '<textarea name="notes" rows="2" class="w-full border rounded px-3 py-2" placeholder="' . __('Optional notes...') . '"></textarea>';
    echo '</div>';

    echo '</div>';

    echo '<button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Add Certification') . '</button>';

    echo '</form>';
    echo '</div>';

    // Build query criteria
    $criteria = $certificationGateway->newQueryCriteria()
        ->sortBy(['expiryDate'], 'ASC')
        ->fromPOST();

    // Apply filters
    if (!empty($filterStatus)) {
        $criteria->filterBy('status', $filterStatus);
    }
    if (!empty($filterType)) {
        $criteria->filterBy('certificationType', $filterType);
    }
    if (!empty($filterPerson)) {
        $criteria->filterBy('person', $filterPerson);
    }

    // Apply quick filters
    if ($filter === 'expired') {
        $criteria->filterBy('expired', 'Y');
    } elseif ($filter === 'expiring') {
        $criteria->filterBy('expiringBefore', date('Y-m-d', strtotime('+30 days')));
        $criteria->filterBy('expiringAfter', date('Y-m-d'));
    } elseif ($filter === 'required') {
        $criteria->filterBy('isRequired', 'Y');
    }

    // Get certification data
    $certifications = $certificationGateway->queryCertifications($criteria);

    // Build DataTable
    $table = DataTable::createPaginated('certifications', $criteria);
    $table->setTitle(__('All Certifications'));

    // Add columns
    $table->addColumn('image', __('Photo'))
        ->notSortable()
        ->format(function ($row) use ($session) {
            $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
        });

    $table->addColumn('name', __('Staff Member'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Staff', true, true);
        });

    $table->addColumn('certificationName', __('Certification'))
        ->sortable()
        ->format(function ($row) {
            $output = '<span class="font-semibold">' . htmlspecialchars($row['certificationName']) . '</span>';
            if ($row['isRequired'] === 'Y') {
                $output .= ' <span class="bg-red-100 text-red-800 text-xs px-2 py-0.5 rounded">' . __('Required') . '</span>';
            }
            return $output;
        });

    $table->addColumn('certificationType', __('Type'))
        ->sortable()
        ->format(function ($row) {
            $colors = [
                'First Aid'       => 'red',
                'CPR'             => 'pink',
                'ECE Certificate' => 'green',
                'ECE Diploma'     => 'teal',
                'Police Check'    => 'blue',
                'Driver License'  => 'indigo',
                'Food Handler'    => 'orange',
                'WHMIS'           => 'yellow',
                'Other'           => 'gray',
            ];
            $color = $colors[$row['certificationType']] ?? 'gray';
            return '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __($row['certificationType']) . '</span>';
        });

    $table->addColumn('issuingOrganization', __('Issuing Organization'))
        ->sortable()
        ->format(function ($row) {
            return !empty($row['issuingOrganization']) ? htmlspecialchars($row['issuingOrganization']) : '-';
        });

    $table->addColumn('issueDate', __('Issue Date'))
        ->sortable()
        ->format(function ($row) {
            return !empty($row['issueDate']) ? Format::date($row['issueDate']) : '-';
        });

    $table->addColumn('expiryDate', __('Expiry Date'))
        ->sortable()
        ->format(function ($row) {
            if (empty($row['expiryDate'])) {
                return '<span class="text-gray-500">' . __('No Expiry') . '</span>';
            }

            $expiryDate = $row['expiryDate'];
            $today = date('Y-m-d');
            $daysUntilExpiry = (strtotime($expiryDate) - strtotime($today)) / 86400;

            $formattedDate = Format::date($expiryDate);

            if ($daysUntilExpiry < 0) {
                // Expired
                $daysExpired = abs(intval($daysUntilExpiry));
                return '<span class="text-red-600 font-semibold">' . $formattedDate . '</span><br><span class="text-xs text-red-500">' . sprintf(__('Expired %d day(s) ago'), $daysExpired) . '</span>';
            } elseif ($daysUntilExpiry <= 7) {
                // Critical - within 7 days
                return '<span class="text-red-600 font-semibold">' . $formattedDate . '</span><br><span class="text-xs text-red-500">' . sprintf(__('%d day(s) left'), intval($daysUntilExpiry)) . '</span>';
            } elseif ($daysUntilExpiry <= 30) {
                // Warning - within 30 days
                return '<span class="text-orange-600 font-semibold">' . $formattedDate . '</span><br><span class="text-xs text-orange-500">' . sprintf(__('%d day(s) left'), intval($daysUntilExpiry)) . '</span>';
            } else {
                // Valid
                return '<span class="text-green-600">' . $formattedDate . '</span>';
            }
        });

    $table->addColumn('status', __('Status'))
        ->sortable()
        ->format(function ($row) {
            $statusClasses = [
                'Valid'   => 'bg-green-100 text-green-800',
                'Pending' => 'bg-blue-100 text-blue-800',
                'Expired' => 'bg-red-100 text-red-800',
                'Revoked' => 'bg-gray-100 text-gray-800',
            ];
            $class = $statusClasses[$row['status']] ?? 'bg-gray-100 text-gray-800';
            return '<span class="' . $class . ' text-xs px-2 py-1 rounded">' . __($row['status']) . '</span>';
        });

    // Add action column
    $table->addActionColumn()
        ->addParam('gibbonStaffCertificationID')
        ->format(function ($row, $actions) use ($session) {
            $actions->addAction('view', __('View'))
                ->setIcon('page_white')
                ->setURL('/modules/StaffManagement/staffManagement_certifications_view.php');

            $actions->addAction('edit', __('Edit'))
                ->setIcon('config')
                ->setURL('/modules/StaffManagement/staffManagement_certifications_edit.php');

            $actions->addAction('delete', __('Delete'))
                ->setIcon('garbage')
                ->setURL('/modules/StaffManagement/staffManagement_certifications_delete.php')
                ->modalWindow();
        });

    // Output table
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Certification Records') . '</h3>';

    if ($certifications->count() > 0) {
        echo $table->render($certifications);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No certification records found.');
        echo '</div>';
    }

    // Certification Type Breakdown
    $typeBreakdown = $certificationGateway->selectCertificationCountByType();

    if ($typeBreakdown->rowCount() > 0) {
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Certification Type Breakdown') . '</h3>';
        echo '<div class="bg-white rounded-lg shadow p-4">';
        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">';

        foreach ($typeBreakdown as $type) {
            $colors = [
                'First Aid'       => 'red',
                'CPR'             => 'pink',
                'ECE Certificate' => 'green',
                'ECE Diploma'     => 'teal',
                'Police Check'    => 'blue',
                'Driver License'  => 'indigo',
                'Food Handler'    => 'orange',
                'WHMIS'           => 'yellow',
                'Other'           => 'gray',
            ];
            $color = $colors[$type['certificationType']] ?? 'gray';

            echo '<div class="bg-' . $color . '-50 rounded-lg p-3 text-center">';
            echo '<span class="block text-sm font-semibold text-' . $color . '-800">' . __($type['certificationType']) . '</span>';
            echo '<div class="mt-2 text-xs space-y-1">';
            echo '<div class="flex justify-between"><span>' . __('Valid') . ':</span><span class="text-green-600 font-semibold">' . $type['validCount'] . '</span></div>';
            echo '<div class="flex justify-between"><span>' . __('Pending') . ':</span><span class="text-blue-600">' . $type['pendingCount'] . '</span></div>';
            echo '<div class="flex justify-between"><span>' . __('Expired') . ':</span><span class="text-red-600">' . $type['expiredCount'] . '</span></div>';
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-6">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Staff Management') . '</a>';
    echo '</div>';
}
