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
use Gibbon\Module\MedicalTracking\Domain\MedicationGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Medical Tracking'), 'medicalTracking.php');
$page->breadcrumbs->add(__('Manage Medications'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/MedicalTracking/medicalTracking_medications.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID and current user from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateway via DI container
    $medicationGateway = $container->get(MedicationGateway::class);

    // Medication types and administration options
    $medicationTypes = [
        'Prescription'     => __('Prescription'),
        'Over-the-Counter' => __('Over-the-Counter'),
        'Supplement'       => __('Supplement'),
        'Other'            => __('Other'),
    ];

    $administeredByOptions = [
        'Staff'  => __('Staff'),
        'Nurse'  => __('Nurse'),
        'Self'   => __('Self'),
    ];

    $frequencyOptions = [
        'As Needed'     => __('As Needed'),
        'Once Daily'    => __('Once Daily'),
        'Twice Daily'   => __('Twice Daily'),
        'Three Times'   => __('Three Times Daily'),
        'Four Times'    => __('Four Times Daily'),
        'Every 4 Hours' => __('Every 4 Hours'),
        'Every 6 Hours' => __('Every 6 Hours'),
        'Every 8 Hours' => __('Every 8 Hours'),
        'Weekly'        => __('Weekly'),
        'Other'         => __('Other'),
    ];

    $routeOptions = [
        'Oral'        => __('Oral'),
        'Topical'     => __('Topical'),
        'Inhaled'     => __('Inhaled'),
        'Injection'   => __('Injection'),
        'Eye Drops'   => __('Eye Drops'),
        'Ear Drops'   => __('Ear Drops'),
        'Nasal'       => __('Nasal'),
        'Rectal'      => __('Rectal'),
        'Other'       => __('Other'),
    ];

    // Get filter values from request
    $filterMedicationType = $_GET['medicationType'] ?? '';
    $filterAdministeredBy = $_GET['administeredBy'] ?? '';
    $filterParentConsent = $_GET['parentConsent'] ?? '';
    $filterVerified = $_GET['verified'] ?? '';
    $filterActive = $_GET['active'] ?? 'Y';
    $filterExpiration = $_GET['expiration'] ?? '';
    $filterChild = $_GET['gibbonPersonID'] ?? '';

    // Handle actions
    $action = $_POST['action'] ?? '';

    // Handle add medication action
    if ($action === 'addMedication') {
        $childID = $_POST['gibbonPersonID'] ?? null;
        $medicationName = trim($_POST['medicationName'] ?? '');
        $medicationType = $_POST['medicationType'] ?? 'Prescription';
        $dosage = trim($_POST['dosage'] ?? '');
        $frequency = $_POST['frequency'] ?? 'As Needed';
        $route = $_POST['route'] ?? 'Oral';
        $administeredBy = $_POST['administeredBy'] ?? 'Staff';
        $prescribedBy = trim($_POST['prescribedBy'] ?? '');
        $prescriptionDate = !empty($_POST['prescriptionDate']) ? Format::dateConvert($_POST['prescriptionDate']) : null;
        $expirationDate = !empty($_POST['expirationDate']) ? Format::dateConvert($_POST['expirationDate']) : null;
        $purpose = trim($_POST['purpose'] ?? '');
        $sideEffects = trim($_POST['sideEffects'] ?? '');
        $storageLocation = trim($_POST['storageLocation'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($childID) && !empty($medicationName) && !empty($dosage)) {
            $additionalData = [
                'route' => $route,
                'prescribedBy' => $prescribedBy ?: null,
                'prescriptionDate' => $prescriptionDate,
                'expirationDate' => $expirationDate,
                'purpose' => $purpose ?: null,
                'sideEffects' => $sideEffects ?: null,
                'storageLocation' => $storageLocation ?: null,
                'administeredBy' => $administeredBy,
                'notes' => $notes ?: null,
            ];

            $result = $medicationGateway->addMedication(
                $childID,
                $medicationName,
                $medicationType,
                $dosage,
                $frequency,
                $gibbonPersonID,
                $additionalData
            );

            if ($result !== false) {
                $page->addSuccess(__('Medication record has been added successfully.'));
            } else {
                $page->addError(__('This medication already exists for this child or could not be added.'));
            }
        } else {
            $page->addError(__('Please select a child and enter medication name and dosage.'));
        }
    }

    // Handle verify medication action
    if ($action === 'verifyMedication') {
        $medicationID = $_POST['gibbonMedicalMedicationID'] ?? null;

        if (!empty($medicationID)) {
            $result = $medicationGateway->verifyMedication($medicationID, $gibbonPersonID);

            if ($result) {
                $page->addSuccess(__('Medication record has been verified.'));
            } else {
                $page->addError(__('Failed to verify medication record.'));
            }
        }
    }

    // Handle record parent consent action
    if ($action === 'recordConsent') {
        $medicationID = $_POST['gibbonMedicalMedicationID'] ?? null;

        if (!empty($medicationID)) {
            $result = $medicationGateway->recordParentConsent($medicationID);

            if ($result) {
                $page->addSuccess(__('Parent consent has been recorded.'));
            } else {
                $page->addError(__('Failed to record parent consent.'));
            }
        }
    }

    // Handle deactivate medication action
    if ($action === 'deactivateMedication') {
        $medicationID = $_POST['gibbonMedicalMedicationID'] ?? null;

        if (!empty($medicationID)) {
            $result = $medicationGateway->deactivateMedication($medicationID);

            if ($result) {
                $page->addSuccess(__('Medication record has been deactivated.'));
            } else {
                $page->addError(__('Failed to deactivate medication record.'));
            }
        }
    }

    // Handle update expiration date action
    if ($action === 'updateExpiration') {
        $medicationID = $_POST['gibbonMedicalMedicationID'] ?? null;
        $newExpirationDate = !empty($_POST['expirationDate']) ? Format::dateConvert($_POST['expirationDate']) : null;

        if (!empty($medicationID) && !empty($newExpirationDate)) {
            $result = $medicationGateway->updateExpirationDate($medicationID, $newExpirationDate);

            if ($result) {
                $page->addSuccess(__('Expiration date has been updated.'));
            } else {
                $page->addError(__('Failed to update expiration date.'));
            }
        }
    }

    // Page header
    echo '<h2>' . __('Medication Management') . '</h2>';
    echo '<p class="text-gray-600 mb-4">' . __('View and manage medication records for children. Add new medications, verify records, track expiration dates, and manage parent consent.') . '</p>';

    // Get summary statistics
    $summary = $medicationGateway->getMedicationSummary();
    $expirationSummary = $medicationGateway->getExpirationMonitoringSummary(30);

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Medication Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">';

    $totalMedications = 0;
    $totalPrescription = 0;
    $totalStaffAdministered = 0;
    $totalUnverified = 0;
    $totalAwaitingConsent = 0;

    if (!empty($summary) && is_array($summary)) {
        foreach ($summary as $row) {
            $totalMedications += $row['totalCount'] ?? 0;
            if (($row['medicationType'] ?? '') === 'Prescription') {
                $totalPrescription += $row['totalCount'] ?? 0;
            }
            if (in_array($row['administeredBy'] ?? '', ['Staff', 'Nurse'])) {
                $totalStaffAdministered += $row['totalCount'] ?? 0;
            }
            $totalUnverified += $row['unverifiedCount'] ?? 0;
            $awaitingConsent = ($row['totalCount'] ?? 0) - ($row['consentedCount'] ?? 0);
            if ($awaitingConsent > 0) {
                $totalAwaitingConsent += $awaitingConsent;
            }
        }
    }

    echo '<div class="bg-gray-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-gray-600">' . __('Total Active') . '</span>';
    echo '<span class="block text-3xl font-bold text-gray-800">' . $totalMedications . '</span>';
    echo '</div>';

    echo '<div class="bg-blue-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-blue-600">' . __('Staff Administered') . '</span>';
    echo '<span class="block text-3xl font-bold text-blue-700">' . $totalStaffAdministered . '</span>';
    echo '</div>';

    echo '<div class="bg-red-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-red-600">' . __('Expired') . '</span>';
    echo '<span class="block text-3xl font-bold text-red-700">' . ($expirationSummary['expiredCount'] ?? 0) . '</span>';
    echo '</div>';

    echo '<div class="bg-orange-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-orange-600">' . __('Expiring Soon') . '</span>';
    echo '<span class="block text-3xl font-bold text-orange-700">' . ($expirationSummary['expiringSoonCount'] ?? 0) . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-yellow-600">' . __('Unverified') . '</span>';
    echo '<span class="block text-3xl font-bold text-yellow-700">' . $totalUnverified . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Alert banner for expired medications
    $expiredCount = $expirationSummary['expiredCount'] ?? 0;
    $expiringSoonCount = $expirationSummary['expiringSoonCount'] ?? 0;

    if ($expiredCount > 0 || $expiringSoonCount > 0) {
        echo '<div class="bg-red-50 border-l-4 border-red-500 p-4 mb-4">';
        echo '<div class="flex">';
        echo '<div class="flex-shrink-0"><span class="text-red-500 text-2xl">&#9888;</span></div>';
        echo '<div class="ml-3">';
        echo '<h3 class="text-red-800 font-semibold">' . __('Medication Alert') . '</h3>';
        echo '<ul class="list-disc list-inside text-red-700 mt-2">';
        if ($expiredCount > 0) {
            echo '<li>' . sprintf(__('%d medication(s) have expired and need replacement'), $expiredCount) . '</li>';
        }
        if ($expiringSoonCount > 0) {
            echo '<li>' . sprintf(__('%d medication(s) will expire within 30 days'), $expiringSoonCount) . '</li>';
        }
        echo '</ul>';
        echo '</div></div></div>';
    }

    // Filter form
    $filterForm = Form::create('medicationFilter', $session->get('absoluteURL') . '/index.php');
    $filterForm->setMethod('get');
    $filterForm->setClass('noIntBorder fullWidth');
    $filterForm->addHiddenValue('q', '/modules/MedicalTracking/medicalTracking_medications.php');

    $row = $filterForm->addRow();
    $row->addLabel('medicationType', __('Medication Type'));
    $row->addSelect('medicationType')
        ->fromArray(['' => __('All Types')] + $medicationTypes)
        ->selected($filterMedicationType);

    $row = $filterForm->addRow();
    $row->addLabel('administeredBy', __('Administered By'));
    $row->addSelect('administeredBy')
        ->fromArray(['' => __('All')] + $administeredByOptions)
        ->selected($filterAdministeredBy);

    $row = $filterForm->addRow();
    $row->addLabel('parentConsent', __('Parent Consent'));
    $row->addSelect('parentConsent')
        ->fromArray(['' => __('All'), 'Y' => __('Consented'), 'N' => __('Awaiting Consent')])
        ->selected($filterParentConsent);

    $row = $filterForm->addRow();
    $row->addLabel('verified', __('Verified Status'));
    $row->addSelect('verified')
        ->fromArray(['' => __('All'), 'Y' => __('Verified'), 'N' => __('Unverified')])
        ->selected($filterVerified);

    $row = $filterForm->addRow();
    $row->addLabel('active', __('Status'));
    $row->addSelect('active')
        ->fromArray(['Y' => __('Active'), 'N' => __('Inactive'), '' => __('All')])
        ->selected($filterActive);

    $row = $filterForm->addRow();
    $row->addSearchSubmit($session, __('Clear Filters'), ['medicationType', 'administeredBy', 'parentConsent', 'verified', 'active']);

    echo $filterForm->getOutput();

    // Build query criteria
    $criteria = $medicationGateway->newQueryCriteria()
        ->sortBy(['medicationName', 'surname', 'preferredName'])
        ->fromPOST();

    // Add filters to criteria
    if (!empty($filterMedicationType)) {
        $criteria->filterBy('medicationType', $filterMedicationType);
    }
    if (!empty($filterAdministeredBy)) {
        $criteria->filterBy('administeredBy', $filterAdministeredBy);
    }
    if (!empty($filterParentConsent)) {
        $criteria->filterBy('parentConsent', $filterParentConsent);
    }
    if (!empty($filterVerified)) {
        $criteria->filterBy('verified', $filterVerified);
    }
    if ($filterActive !== '') {
        $criteria->filterBy('active', $filterActive);
    }
    if (!empty($filterChild)) {
        $criteria->filterBy('child', $filterChild);
    }

    // Get medication data
    $medications = $medicationGateway->queryMedications($criteria);

    // Build DataTable
    $table = DataTable::createPaginated('medications', $criteria);
    $table->setTitle(__('Medication Records'));

    // Add columns
    $table->addColumn('image', __('Photo'))
        ->notSortable()
        ->format(function ($row) use ($session) {
            $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
        });

    $table->addColumn('name', __('Child Name'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
        });

    $table->addColumn('medicationName', __('Medication'))
        ->sortable()
        ->format(function ($row) {
            $typeIcon = '';
            switch ($row['medicationType']) {
                case 'Prescription':
                    $typeIcon = '💊';
                    break;
                case 'Over-the-Counter':
                    $typeIcon = '🏪';
                    break;
                case 'Supplement':
                    $typeIcon = '🌿';
                    break;
                default:
                    $typeIcon = '💉';
            }
            return '<span title="' . __($row['medicationType']) . '">' . $typeIcon . '</span> ' . htmlspecialchars($row['medicationName']);
        });

    $table->addColumn('dosage', __('Dosage'))
        ->sortable()
        ->format(function ($row) {
            $dosage = htmlspecialchars($row['dosage']);
            $frequency = !empty($row['frequency']) ? '<span class="text-xs text-gray-500 block">' . __($row['frequency']) . '</span>' : '';
            return $dosage . $frequency;
        });

    $table->addColumn('route', __('Route'))
        ->notSortable()
        ->format(function ($row) {
            return __($row['route'] ?? 'Oral');
        });

    $table->addColumn('administeredBy', __('Given By'))
        ->sortable()
        ->format(function ($row) {
            $colors = [
                'Staff' => 'bg-blue-100 text-blue-800',
                'Nurse' => 'bg-purple-100 text-purple-800',
                'Self'  => 'bg-gray-100 text-gray-800',
            ];
            $color = $colors[$row['administeredBy']] ?? 'bg-gray-100 text-gray-800';
            return '<span class="' . $color . ' text-xs px-2 py-1 rounded">' . __($row['administeredBy']) . '</span>';
        });

    $table->addColumn('expirationDate', __('Expires'))
        ->sortable()
        ->format(function ($row) {
            if (empty($row['expirationDate'])) {
                return '<span class="text-gray-400">-</span>';
            }
            $expirationDate = $row['expirationDate'];
            $today = date('Y-m-d');
            $daysUntil = (strtotime($expirationDate) - strtotime($today)) / 86400;

            if ($daysUntil < 0) {
                return '<span class="text-red-600 font-bold" title="' . __('Expired') . '">⚠️ ' . Format::date($expirationDate) . '</span>';
            } elseif ($daysUntil <= 30) {
                return '<span class="text-orange-600" title="' . sprintf(__('Expires in %d days'), round($daysUntil)) . '">' . Format::date($expirationDate) . '</span>';
            }
            return Format::date($expirationDate);
        });

    $table->addColumn('parentConsent', __('Consent'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['parentConsent'] === 'Y') {
                $consentDate = !empty($row['parentConsentDate']) ? Format::date($row['parentConsentDate']) : '';
                return '<span class="text-green-600" title="' . $consentDate . '">✓ ' . __('Yes') . '</span>';
            }
            return '<span class="text-yellow-600">⏳ ' . __('Pending') . '</span>';
        });

    $table->addColumn('verified', __('Verified'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['verified'] === 'Y') {
                $verifiedBy = !empty($row['verifiedByName'])
                    ? Format::name('', $row['verifiedByName'], $row['verifiedBySurname'], 'Staff', false, true)
                    : __('Staff');
                $verifiedDate = !empty($row['verifiedDate']) ? Format::date($row['verifiedDate']) : '';
                return '<span class="text-green-600" title="' . $verifiedBy . ' - ' . $verifiedDate . '">✓ ' . __('Verified') . '</span>';
            }
            return '<span class="text-yellow-600">⏳ ' . __('Pending') . '</span>';
        });

    $table->addColumn('active', __('Status'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['active'] === 'Y') {
                return '<span class="text-green-600">' . __('Active') . '</span>';
            }
            return '<span class="text-gray-500">' . __('Inactive') . '</span>';
        });

    // Add action column
    $table->addActionColumn()
        ->format(function ($row, $actions) use ($session, $gibbonPersonID) {
            // Verify action (only for unverified, active medications)
            if ($row['verified'] === 'N' && $row['active'] === 'Y') {
                $actions->addAction('verify', __('Verify'))
                    ->setIcon('iconTick')
                    ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php')
                    ->addParam('verify', $row['gibbonMedicalMedicationID'])
                    ->directLink();
            }

            // Record consent action (only for those awaiting consent)
            if ($row['parentConsent'] === 'N' && $row['active'] === 'Y') {
                $actions->addAction('consent', __('Record Consent'))
                    ->setIcon('iconPerson')
                    ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php')
                    ->addParam('consent', $row['gibbonMedicalMedicationID'])
                    ->directLink();
            }

            // Deactivate action (only for active medications)
            if ($row['active'] === 'Y') {
                $actions->addAction('deactivate', __('Deactivate'))
                    ->setIcon('iconCross')
                    ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php')
                    ->addParam('deactivate', $row['gibbonMedicalMedicationID'])
                    ->directLink();
            }

            return $actions;
        });

    // Output table
    if ($medications->count() > 0) {
        echo $table->render($medications);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500 mb-4">';
        echo __('No medication records found matching the selected criteria.');
        echo '</div>';
    }

    // Handle quick actions via GET parameters
    $verifyID = $_GET['verify'] ?? null;
    $consentID = $_GET['consent'] ?? null;
    $deactivateID = $_GET['deactivate'] ?? null;

    if (!empty($verifyID)) {
        $result = $medicationGateway->verifyMedication($verifyID, $gibbonPersonID);
        if ($result) {
            echo '<script>window.location.href = "' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php";</script>';
        }
    }

    if (!empty($consentID)) {
        $result = $medicationGateway->recordParentConsent($consentID);
        if ($result) {
            echo '<script>window.location.href = "' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php";</script>';
        }
    }

    if (!empty($deactivateID)) {
        $result = $medicationGateway->deactivateMedication($deactivateID);
        if ($result) {
            echo '<script>window.location.href = "' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php";</script>';
        }
    }

    // Section: Expiring Medications (if any)
    $expiringSoonMedications = $medicationGateway->selectMedicationsExpiringSoon(30);
    $expiringSoonCount = $expiringSoonMedications->rowCount();

    if ($expiringSoonCount > 0) {
        echo '<div class="mt-6">';
        echo '<h3 class="text-lg font-semibold mb-3">' . __('Medications Expiring Soon') . '</h3>';
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">';
        echo '<div class="space-y-2">';
        while ($med = $expiringSoonMedications->fetch()) {
            $childName = Format::name('', $med['preferredName'], $med['surname'], 'Student', false, true);
            $daysLeft = $med['daysUntilExpiry'];
            $urgencyClass = $daysLeft <= 7 ? 'text-red-600' : ($daysLeft <= 14 ? 'text-orange-600' : 'text-yellow-600');
            echo '<div class="flex justify-between items-center bg-white p-2 rounded">';
            echo '<div>';
            echo '<span class="font-medium">' . htmlspecialchars($med['medicationName']) . '</span>';
            echo '<span class="text-sm text-gray-500"> - ' . htmlspecialchars($childName) . '</span>';
            if (!empty($med['storageLocation'])) {
                echo '<span class="text-xs text-gray-400 block">' . __('Location') . ': ' . htmlspecialchars($med['storageLocation']) . '</span>';
            }
            echo '</div>';
            echo '<span class="' . $urgencyClass . ' font-semibold">' . sprintf(__('%d days'), $daysLeft) . '</span>';
            echo '</div>';
        }
        echo '</div></div></div>';
    }

    // Section: Expired Medications (if any)
    $expiredMedications = $medicationGateway->selectExpiredMedications();
    $expiredCount = $expiredMedications->rowCount();

    if ($expiredCount > 0) {
        echo '<div class="mt-6">';
        echo '<h3 class="text-lg font-semibold mb-3">' . __('Expired Medications - Action Required') . '</h3>';
        echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4">';
        echo '<div class="space-y-2">';
        while ($med = $expiredMedications->fetch()) {
            $childName = Format::name('', $med['preferredName'], $med['surname'], 'Student', false, true);
            $daysExpired = $med['daysExpired'];
            echo '<div class="flex justify-between items-center bg-white p-2 rounded border-l-4 border-red-500">';
            echo '<div>';
            echo '<span class="font-medium text-red-700">' . htmlspecialchars($med['medicationName']) . '</span>';
            echo '<span class="text-sm text-gray-500"> - ' . htmlspecialchars($childName) . '</span>';
            if (!empty($med['storageLocation'])) {
                echo '<span class="text-xs text-gray-400 block">' . __('Location') . ': ' . htmlspecialchars($med['storageLocation']) . '</span>';
            }
            echo '</div>';
            echo '<span class="text-red-600 font-semibold">' . sprintf(__('Expired %d days ago'), $daysExpired) . '</span>';
            echo '</div>';
        }
        echo '</div></div></div>';
    }

    // Section: Add New Medication
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Add New Medication Record') . '</h3>';

    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">';
    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php">';
    echo '<input type="hidden" name="action" value="addMedication">';

    // Child and medication name
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">';

    // Get children (active students)
    $sql = "SELECT gibbonPersonID, preferredName, surname
            FROM gibbonPerson
            WHERE status='Full'
            ORDER BY surname, preferredName";
    $result = $connection2->query($sql);
    $children = [];
    while ($row = $result->fetch()) {
        $children[$row['gibbonPersonID']] = Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
    }

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Child') . ' <span class="text-red-500">*</span></label>';
    echo '<select name="gibbonPersonID" class="w-full border rounded px-3 py-2" required>';
    echo '<option value="">' . __('Select a child...') . '</option>';
    foreach ($children as $id => $name) {
        echo '<option value="' . $id . '">' . htmlspecialchars($name) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Medication Name') . ' <span class="text-red-500">*</span></label>';
    echo '<input type="text" name="medicationName" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Amoxicillin, Tylenol') . '" required>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Medication Type') . '</label>';
    echo '<select name="medicationType" class="w-full border rounded px-3 py-2">';
    foreach ($medicationTypes as $value => $label) {
        $selected = $value === 'Prescription' ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '</div>';

    // Dosage, frequency, route
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Dosage') . ' <span class="text-red-500">*</span></label>';
    echo '<input type="text" name="dosage" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., 5ml, 1 tablet') . '" required>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Frequency') . '</label>';
    echo '<select name="frequency" class="w-full border rounded px-3 py-2">';
    foreach ($frequencyOptions as $value => $label) {
        $selected = $value === 'As Needed' ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Route') . '</label>';
    echo '<select name="route" class="w-full border rounded px-3 py-2">';
    foreach ($routeOptions as $value => $label) {
        $selected = $value === 'Oral' ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Administered By') . '</label>';
    echo '<select name="administeredBy" class="w-full border rounded px-3 py-2">';
    foreach ($administeredByOptions as $value => $label) {
        $selected = $value === 'Staff' ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '</div>';

    // Prescription details
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Prescribed By') . '</label>';
    echo '<input type="text" name="prescribedBy" class="w-full border rounded px-3 py-2" placeholder="' . __('Doctor name') . '">';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Prescription Date') . '</label>';
    echo '<input type="date" name="prescriptionDate" class="w-full border rounded px-3 py-2">';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Expiration Date') . '</label>';
    echo '<input type="date" name="expirationDate" class="w-full border rounded px-3 py-2">';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Storage Location') . '</label>';
    echo '<input type="text" name="storageLocation" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Nurse\'s office, refrigerator') . '">';
    echo '</div>';

    echo '</div>';

    // Purpose and side effects
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Purpose/Condition') . '</label>';
    echo '<textarea name="purpose" class="w-full border rounded px-3 py-2" rows="2" placeholder="' . __('What is this medication for?') . '"></textarea>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Side Effects to Watch') . '</label>';
    echo '<textarea name="sideEffects" class="w-full border rounded px-3 py-2" rows="2" placeholder="' . __('Known side effects to monitor...') . '"></textarea>';
    echo '</div>';

    echo '</div>';

    // Notes
    echo '<div class="mb-4">';
    echo '<label class="block text-sm font-medium mb-1">' . __('Additional Notes') . '</label>';
    echo '<input type="text" name="notes" class="w-full border rounded px-3 py-2" placeholder="' . __('Any additional information...') . '">';
    echo '</div>';

    // Submit button
    echo '<div class="mt-4">';
    echo '<button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Add Medication Record') . '</button>';
    echo '</div>';

    echo '</form>';
    echo '</div>';

    // Quick links section
    echo '<div class="mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Links') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php&administeredBy=Staff" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Staff Administered') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php&administeredBy=Nurse" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('Nurse Administered') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php&parentConsent=N" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">' . __('Awaiting Consent') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php&verified=N" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">' . __('Unverified Records') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_medications.php&medicationType=Prescription" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Prescriptions Only') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
    echo '</div>';
}
