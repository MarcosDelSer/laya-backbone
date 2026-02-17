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
use Gibbon\Module\MedicalTracking\Domain\AccommodationPlanGateway;
use Gibbon\Module\MedicalTracking\Domain\AllergyGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Medical Tracking'), 'medicalTracking.php');
$page->breadcrumbs->add(__('Accommodation Plans'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/MedicalTracking/medicalTracking_accommodations.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID and current user from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways via DI container
    $accommodationPlanGateway = $container->get(AccommodationPlanGateway::class);
    $allergyGateway = $container->get(AllergyGateway::class);

    // Plan types and severity levels
    $planTypes = [
        'Dietary'     => __('Dietary'),
        'Medical'     => __('Medical'),
        'Behavioral'  => __('Behavioral'),
        'Physical'    => __('Physical'),
        'Other'       => __('Other'),
    ];

    $mealTypes = [
        'All'              => __('All Meals'),
        'Breakfast'        => __('Breakfast'),
        'Morning Snack'    => __('Morning Snack'),
        'Lunch'            => __('Lunch'),
        'Afternoon Snack'  => __('Afternoon Snack'),
        'Dinner'           => __('Dinner'),
    ];

    $severityLevels = [
        'Low'      => __('Low'),
        'Medium'   => __('Medium'),
        'High'     => __('High'),
        'Critical' => __('Critical'),
    ];

    $trainingTypes = [
        'Allergy Response'   => __('Allergy Response'),
        'EpiPen Administration' => __('EpiPen Administration'),
        'First Aid'          => __('First Aid'),
        'CPR'                => __('CPR'),
        'Medication Admin'   => __('Medication Administration'),
        'Special Needs'      => __('Special Needs Care'),
        'Other'              => __('Other'),
    ];

    // Get filter values from request
    $filterPlanType = $_GET['planType'] ?? '';
    $filterApproved = $_GET['approved'] ?? '';
    $filterActive = $_GET['active'] ?? 'Y';
    $filterChild = $_GET['gibbonPersonID'] ?? '';
    $activeTab = $_GET['tab'] ?? 'plans';

    // Handle actions
    $action = $_POST['action'] ?? '';

    // Handle add accommodation plan action
    if ($action === 'addPlan') {
        $childID = $_POST['gibbonPersonID'] ?? null;
        $planName = trim($_POST['planName'] ?? '');
        $planType = $_POST['planType'] ?? 'Dietary';
        $effectiveDate = !empty($_POST['effectiveDate']) ? Format::dateConvert($_POST['effectiveDate']) : date('Y-m-d');
        $expiryDate = !empty($_POST['expiryDate']) ? Format::dateConvert($_POST['expiryDate']) : null;
        $allergyID = !empty($_POST['gibbonMedicalAllergyID']) ? $_POST['gibbonMedicalAllergyID'] : null;
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($childID) && !empty($planName)) {
            $additionalData = [
                'effectiveDate' => $effectiveDate,
                'expiryDate' => $expiryDate,
                'gibbonMedicalAllergyID' => $allergyID,
                'notes' => $notes ?: null,
            ];

            $result = $accommodationPlanGateway->addAccommodationPlan(
                $childID,
                $planName,
                $planType,
                $gibbonPersonID,
                $additionalData
            );

            if ($result !== false) {
                $page->addSuccess(__('Accommodation plan has been added successfully.'));
            } else {
                $page->addError(__('Failed to add accommodation plan.'));
            }
        } else {
            $page->addError(__('Please select a child and enter a plan name.'));
        }
    }

    // Handle add dietary substitution action
    if ($action === 'addSubstitution') {
        $planID = $_POST['gibbonMedicalAccommodationPlanID'] ?? null;
        $originalItem = trim($_POST['originalItem'] ?? '');
        $substituteItem = trim($_POST['substituteItem'] ?? '');
        $mealType = $_POST['mealType'] ?? 'All';
        $notes = trim($_POST['substitutionNotes'] ?? '');

        if (!empty($planID) && !empty($originalItem) && !empty($substituteItem)) {
            $result = $accommodationPlanGateway->addDietarySubstitution(
                $planID,
                $originalItem,
                $substituteItem,
                $mealType,
                $notes ?: null
            );

            if ($result !== false) {
                $page->addSuccess(__('Dietary substitution has been added successfully.'));
            } else {
                $page->addError(__('Failed to add dietary substitution.'));
            }
        } else {
            $page->addError(__('Please select an accommodation plan and enter both original and substitute items.'));
        }
    }

    // Handle add emergency plan action
    if ($action === 'addEmergencyPlan') {
        $planID = $_POST['gibbonMedicalAccommodationPlanID'] ?? null;
        $triggerCondition = trim($_POST['triggerCondition'] ?? '');
        $severityLevel = $_POST['severityLevel'] ?? 'Medium';
        $immediateActions = trim($_POST['immediateActions'] ?? '');
        $medicationRequired = trim($_POST['medicationRequired'] ?? '');
        $medicationLocation = trim($_POST['medicationLocation'] ?? '');
        $callEmergencyServices = $_POST['callEmergencyServices'] ?? 'N';
        $parentNotification = $_POST['parentNotification'] ?? 'Y';
        $additionalInstructions = trim($_POST['additionalInstructions'] ?? '');

        if (!empty($planID) && !empty($triggerCondition) && !empty($immediateActions)) {
            $additionalData = [
                'medicationRequired' => $medicationRequired ?: null,
                'medicationLocation' => $medicationLocation ?: null,
                'callEmergencyServices' => $callEmergencyServices,
                'parentNotification' => $parentNotification,
                'additionalInstructions' => $additionalInstructions ?: null,
            ];

            $result = $accommodationPlanGateway->addEmergencyPlan(
                $planID,
                $triggerCondition,
                $severityLevel,
                $immediateActions,
                $additionalData
            );

            if ($result !== false) {
                $page->addSuccess(__('Emergency plan has been added successfully.'));
            } else {
                $page->addError(__('Failed to add emergency plan.'));
            }
        } else {
            $page->addError(__('Please select an accommodation plan, enter a trigger condition, and describe immediate actions.'));
        }
    }

    // Handle add staff training action
    if ($action === 'addTraining') {
        $staffID = $_POST['staffPersonID'] ?? null;
        $trainingType = $_POST['trainingType'] ?? 'Allergy Response';
        $trainingName = trim($_POST['trainingName'] ?? '');
        $completedDate = !empty($_POST['completedDate']) ? Format::dateConvert($_POST['completedDate']) : date('Y-m-d');
        $expiryDate = !empty($_POST['trainingExpiryDate']) ? Format::dateConvert($_POST['trainingExpiryDate']) : null;
        $certificationNumber = trim($_POST['certificationNumber'] ?? '');
        $provider = trim($_POST['provider'] ?? '');
        $trainingNotes = trim($_POST['trainingNotes'] ?? '');

        if (!empty($staffID) && !empty($trainingName)) {
            $additionalData = [
                'expiryDate' => $expiryDate,
                'certificationNumber' => $certificationNumber ?: null,
                'provider' => $provider ?: null,
                'notes' => $trainingNotes ?: null,
            ];

            $result = $accommodationPlanGateway->addTrainingRecord(
                $staffID,
                $trainingType,
                $trainingName,
                $completedDate,
                $additionalData
            );

            if ($result !== false) {
                $page->addSuccess(__('Staff training record has been added successfully.'));
            } else {
                $page->addError(__('Failed to add staff training record.'));
            }
        } else {
            $page->addError(__('Please select a staff member and enter a training name.'));
        }
    }

    // Handle approve plan action
    if ($action === 'approvePlan') {
        $planID = $_POST['gibbonMedicalAccommodationPlanID'] ?? null;

        if (!empty($planID)) {
            $result = $accommodationPlanGateway->approveAccommodationPlan($planID, $gibbonPersonID);

            if ($result) {
                $page->addSuccess(__('Accommodation plan has been approved.'));
            } else {
                $page->addError(__('Failed to approve accommodation plan.'));
            }
        }
    }

    // Handle deactivate plan action
    if ($action === 'deactivatePlan') {
        $planID = $_POST['gibbonMedicalAccommodationPlanID'] ?? null;

        if (!empty($planID)) {
            $result = $accommodationPlanGateway->deactivateAccommodationPlan($planID);

            if ($result) {
                $page->addSuccess(__('Accommodation plan has been deactivated.'));
            } else {
                $page->addError(__('Failed to deactivate accommodation plan.'));
            }
        }
    }

    // Handle verify training action
    if ($action === 'verifyTraining') {
        $trainingID = $_POST['gibbonMedicalStaffTrainingID'] ?? null;

        if (!empty($trainingID)) {
            $result = $accommodationPlanGateway->verifyTrainingRecord($trainingID, $gibbonPersonID);

            if ($result) {
                $page->addSuccess(__('Training record has been verified.'));
            } else {
                $page->addError(__('Failed to verify training record.'));
            }
        }
    }

    // Page header
    echo '<h2>' . __('Accommodation Plans') . '</h2>';
    echo '<p class="text-gray-600 mb-4">' . __('Manage accommodation plans including dietary substitutions, emergency response plans, and staff training records for children with special medical needs.') . '</p>';

    // Get summary statistics
    $summary = $accommodationPlanGateway->getAccommodationPlanSummary();
    $trainingSummary = $accommodationPlanGateway->getTrainingSummaryByType();

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Accommodation Plan Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-center">';

    $totalPlans = 0;
    $totalApproved = 0;
    $totalPending = 0;
    $totalDietary = 0;
    $totalChildren = 0;

    if (!empty($summary) && is_array($summary)) {
        foreach ($summary as $row) {
            $totalPlans += $row['totalPlans'] ?? 0;
            $totalApproved += $row['approvedCount'] ?? 0;
            $totalPending += $row['pendingCount'] ?? 0;
            if (($row['planType'] ?? '') === 'Dietary') {
                $totalDietary += $row['totalPlans'] ?? 0;
            }
            $totalChildren = max($totalChildren, $row['childrenCount'] ?? 0);
        }
    }

    echo '<div class="bg-gray-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-gray-600">' . __('Total Plans') . '</span>';
    echo '<span class="block text-3xl font-bold text-gray-800">' . $totalPlans . '</span>';
    echo '</div>';

    echo '<div class="bg-green-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-green-600">' . __('Approved') . '</span>';
    echo '<span class="block text-3xl font-bold text-green-700">' . $totalApproved . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-yellow-600">' . __('Pending Approval') . '</span>';
    echo '<span class="block text-3xl font-bold text-yellow-700">' . $totalPending . '</span>';
    echo '</div>';

    echo '<div class="bg-blue-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-blue-600">' . __('Dietary Plans') . '</span>';
    echo '<span class="block text-3xl font-bold text-blue-700">' . $totalDietary . '</span>';
    echo '</div>';

    echo '<div class="bg-purple-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-purple-600">' . __('Children') . '</span>';
    echo '<span class="block text-3xl font-bold text-purple-700">' . $totalChildren . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Tab navigation
    echo '<div class="mb-4">';
    echo '<div class="flex border-b">';
    $tabs = [
        'plans' => __('Accommodation Plans'),
        'substitutions' => __('Dietary Substitutions'),
        'emergency' => __('Emergency Plans'),
        'training' => __('Staff Training'),
    ];
    foreach ($tabs as $tabKey => $tabLabel) {
        $activeClass = $activeTab === $tabKey ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=' . $tabKey . '" ';
        echo 'class="py-3 px-6 border-b-2 font-medium text-sm ' . $activeClass . '">';
        echo $tabLabel;
        echo '</a>';
    }
    echo '</div>';
    echo '</div>';

    // Handle quick actions via GET parameters
    $approveID = $_GET['approve'] ?? null;
    $deactivateID = $_GET['deactivate'] ?? null;
    $verifyTrainingID = $_GET['verifyTraining'] ?? null;

    if (!empty($approveID)) {
        $result = $accommodationPlanGateway->approveAccommodationPlan($approveID, $gibbonPersonID);
        if ($result) {
            echo '<script>window.location.href = "' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=' . $activeTab . '";</script>';
        }
    }

    if (!empty($deactivateID)) {
        $result = $accommodationPlanGateway->deactivateAccommodationPlan($deactivateID);
        if ($result) {
            echo '<script>window.location.href = "' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=' . $activeTab . '";</script>';
        }
    }

    if (!empty($verifyTrainingID)) {
        $result = $accommodationPlanGateway->verifyTrainingRecord($verifyTrainingID, $gibbonPersonID);
        if ($result) {
            echo '<script>window.location.href = "' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=training";</script>';
        }
    }

    // ==================== ACCOMMODATION PLANS TAB ====================
    if ($activeTab === 'plans') {
        // Filter form
        $filterForm = Form::create('planFilter', $session->get('absoluteURL') . '/index.php');
        $filterForm->setMethod('get');
        $filterForm->setClass('noIntBorder fullWidth');
        $filterForm->addHiddenValue('q', '/modules/MedicalTracking/medicalTracking_accommodations.php');
        $filterForm->addHiddenValue('tab', 'plans');

        $row = $filterForm->addRow();
        $row->addLabel('planType', __('Plan Type'));
        $row->addSelect('planType')
            ->fromArray(['' => __('All Types')] + $planTypes)
            ->selected($filterPlanType);

        $row = $filterForm->addRow();
        $row->addLabel('approved', __('Approval Status'));
        $row->addSelect('approved')
            ->fromArray(['' => __('All'), 'Y' => __('Approved'), 'N' => __('Pending Approval')])
            ->selected($filterApproved);

        $row = $filterForm->addRow();
        $row->addLabel('active', __('Status'));
        $row->addSelect('active')
            ->fromArray(['Y' => __('Active'), 'N' => __('Inactive'), '' => __('All')])
            ->selected($filterActive);

        $row = $filterForm->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'), ['planType', 'approved', 'active']);

        echo $filterForm->getOutput();

        // Build query criteria
        $criteria = $accommodationPlanGateway->newQueryCriteria()
            ->sortBy(['surname', 'preferredName', 'planType'])
            ->fromPOST();

        // Add filters to criteria
        if (!empty($filterPlanType)) {
            $criteria->filterBy('planType', $filterPlanType);
        }
        if (!empty($filterApproved)) {
            $criteria->filterBy('approved', $filterApproved);
        }
        if ($filterActive !== '') {
            $criteria->filterBy('active', $filterActive);
        }
        if (!empty($filterChild)) {
            $criteria->filterBy('child', $filterChild);
        }

        // Get accommodation plan data
        $plans = $accommodationPlanGateway->queryAccommodationPlans($criteria);

        // Build DataTable
        $table = DataTable::createPaginated('accommodationPlans', $criteria);
        $table->setTitle(__('Accommodation Plans'));

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

        $table->addColumn('planName', __('Plan Name'))
            ->sortable()
            ->format(function ($row) {
                return htmlspecialchars($row['planName']);
            });

        $table->addColumn('planType', __('Type'))
            ->sortable()
            ->format(function ($row) {
                $typeIcons = [
                    'Dietary'    => '<span title="' . __('Dietary') . '">&#127860;</span>',
                    'Medical'    => '<span title="' . __('Medical') . '">&#128138;</span>',
                    'Behavioral' => '<span title="' . __('Behavioral') . '">&#128101;</span>',
                    'Physical'   => '<span title="' . __('Physical') . '">&#128170;</span>',
                    'Other'      => '<span title="' . __('Other') . '">&#128203;</span>',
                ];
                $icon = $typeIcons[$row['planType']] ?? '';
                return $icon . ' ' . __($row['planType']);
            });

        $table->addColumn('effectiveDate', __('Effective Date'))
            ->sortable()
            ->format(function ($row) {
                return !empty($row['effectiveDate']) ? Format::date($row['effectiveDate']) : '-';
            });

        $table->addColumn('expiryDate', __('Expiry Date'))
            ->sortable()
            ->format(function ($row) {
                if (empty($row['expiryDate'])) {
                    return '<span class="text-gray-400">' . __('No Expiry') . '</span>';
                }
                $expiryDate = $row['expiryDate'];
                $today = date('Y-m-d');
                if ($expiryDate < $today) {
                    return '<span class="text-red-600 font-bold">' . Format::date($expiryDate) . ' (' . __('Expired') . ')</span>';
                }
                return Format::date($expiryDate);
            });

        $table->addColumn('approved', __('Approved'))
            ->notSortable()
            ->format(function ($row) {
                if ($row['approved'] === 'Y') {
                    $approvedBy = !empty($row['approvedByName'])
                        ? Format::name('', $row['approvedByName'], $row['approvedBySurname'], 'Staff', false, true)
                        : __('Staff');
                    $approvedDate = !empty($row['approvedDate']) ? Format::date($row['approvedDate']) : '';
                    return '<span class="text-green-600" title="' . $approvedBy . ' - ' . $approvedDate . '">&#10003; ' . __('Approved') . '</span>';
                }
                return '<span class="text-yellow-600">&#9203; ' . __('Pending') . '</span>';
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
            ->format(function ($row, $actions) use ($session, $gibbonPersonID, $activeTab) {
                // Approve action (only for pending, active plans)
                if ($row['approved'] === 'N' && $row['active'] === 'Y') {
                    $actions->addAction('approve', __('Approve'))
                        ->setIcon('iconTick')
                        ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=' . $activeTab)
                        ->addParam('approve', $row['gibbonMedicalAccommodationPlanID'])
                        ->directLink();
                }

                // Deactivate action (only for active plans)
                if ($row['active'] === 'Y') {
                    $actions->addAction('deactivate', __('Deactivate'))
                        ->setIcon('iconCross')
                        ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=' . $activeTab)
                        ->addParam('deactivate', $row['gibbonMedicalAccommodationPlanID'])
                        ->directLink();
                }

                return $actions;
            });

        // Output table
        if ($plans->count() > 0) {
            echo $table->render($plans);
        } else {
            echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500 mb-4">';
            echo __('No accommodation plans found matching the selected criteria.');
            echo '</div>';
        }

        // Section: Add New Accommodation Plan
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Add New Accommodation Plan') . '</h3>';

        echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=plans">';
        echo '<input type="hidden" name="action" value="addPlan">';

        // Child selection and plan details
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
        echo '<select name="gibbonPersonID" id="planChildSelect" class="w-full border rounded px-3 py-2" required onchange="updateAllergyOptions()">';
        echo '<option value="">' . __('Select a child...') . '</option>';
        foreach ($children as $id => $name) {
            echo '<option value="' . $id . '">' . htmlspecialchars($name) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Plan Name') . ' <span class="text-red-500">*</span></label>';
        echo '<input type="text" name="planName" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Peanut Allergy Dietary Plan') . '" required>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Plan Type') . '</label>';
        echo '<select name="planType" class="w-full border rounded px-3 py-2">';
        foreach ($planTypes as $value => $label) {
            $selected = $value === 'Dietary' ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '</div>';

        // Dates and related allergy
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Effective Date') . '</label>';
        echo '<input type="date" name="effectiveDate" class="w-full border rounded px-3 py-2" value="' . date('Y-m-d') . '">';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Expiry Date') . '</label>';
        echo '<input type="date" name="expiryDate" class="w-full border rounded px-3 py-2" placeholder="' . __('Optional') . '">';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Related Allergy') . '</label>';
        echo '<select name="gibbonMedicalAllergyID" id="allergySelect" class="w-full border rounded px-3 py-2">';
        echo '<option value="">' . __('None / Select child first') . '</option>';
        echo '</select>';
        echo '<span class="text-xs text-gray-500">' . __('Optional - link to existing allergy record') . '</span>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<input type="text" name="notes" class="w-full border rounded px-3 py-2" placeholder="' . __('Additional information...') . '">';
        echo '</div>';

        echo '</div>';

        // Submit button
        echo '<div class="mt-4">';
        echo '<button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Add Accommodation Plan') . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    // ==================== DIETARY SUBSTITUTIONS TAB ====================
    if ($activeTab === 'substitutions') {
        // Build query criteria
        $criteria = $accommodationPlanGateway->newQueryCriteria()
            ->sortBy(['surname', 'preferredName', 'mealType'])
            ->fromPOST();

        // Get dietary substitution data
        $substitutions = $accommodationPlanGateway->queryDietarySubstitutions($criteria);

        // Build DataTable
        $table = DataTable::createPaginated('dietarySubstitutions', $criteria);
        $table->setTitle(__('Dietary Substitutions'));

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

        $table->addColumn('planName', __('Plan'))
            ->sortable()
            ->format(function ($row) {
                return htmlspecialchars($row['planName']);
            });

        $table->addColumn('originalItem', __('Original Item'))
            ->sortable()
            ->format(function ($row) {
                return '<span class="text-red-600 line-through">' . htmlspecialchars($row['originalItem']) . '</span>';
            });

        $table->addColumn('substituteItem', __('Substitute'))
            ->sortable()
            ->format(function ($row) {
                return '<span class="text-green-600 font-semibold">' . htmlspecialchars($row['substituteItem']) . '</span>';
            });

        $table->addColumn('mealType', __('Meal'))
            ->sortable()
            ->format(function ($row) {
                return __($row['mealType']);
            });

        $table->addColumn('notes', __('Notes'))
            ->notSortable()
            ->format(function ($row) {
                if (empty($row['notes'])) {
                    return '<span class="text-gray-400">-</span>';
                }
                $text = htmlspecialchars($row['notes']);
                if (strlen($text) > 40) {
                    return '<span title="' . $text . '">' . substr($text, 0, 40) . '...</span>';
                }
                return $text;
            });

        $table->addColumn('active', __('Status'))
            ->notSortable()
            ->format(function ($row) {
                if ($row['active'] === 'Y') {
                    return '<span class="text-green-600">' . __('Active') . '</span>';
                }
                return '<span class="text-gray-500">' . __('Inactive') . '</span>';
            });

        // Output table
        if ($substitutions->count() > 0) {
            echo $table->render($substitutions);
        } else {
            echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500 mb-4">';
            echo __('No dietary substitutions found. Add substitutions to accommodation plans below.');
            echo '</div>';
        }

        // Section: Add New Dietary Substitution
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Add Dietary Substitution') . '</h3>';

        // Get active accommodation plans for dropdown
        $plansCriteria = $accommodationPlanGateway->newQueryCriteria();
        $activePlans = $accommodationPlanGateway->queryActiveAccommodationPlans($plansCriteria);

        echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=substitutions">';
        echo '<input type="hidden" name="action" value="addSubstitution">';

        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-4">';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Accommodation Plan') . ' <span class="text-red-500">*</span></label>';
        echo '<select name="gibbonMedicalAccommodationPlanID" class="w-full border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select a plan...') . '</option>';
        foreach ($activePlans as $plan) {
            $childName = Format::name('', $plan['preferredName'], $plan['surname'], 'Student', false, true);
            echo '<option value="' . $plan['gibbonMedicalAccommodationPlanID'] . '">' . htmlspecialchars($childName . ' - ' . $plan['planName']) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Original Item') . ' <span class="text-red-500">*</span></label>';
        echo '<input type="text" name="originalItem" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Peanut butter') . '" required>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Substitute Item') . ' <span class="text-red-500">*</span></label>';
        echo '<input type="text" name="substituteItem" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Sunflower seed butter') . '" required>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Meal Type') . '</label>';
        echo '<select name="mealType" class="w-full border rounded px-3 py-2">';
        foreach ($mealTypes as $value => $label) {
            $selected = $value === 'All' ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<input type="text" name="substitutionNotes" class="w-full border rounded px-3 py-2" placeholder="' . __('Optional notes...') . '">';
        echo '</div>';

        echo '</div>';

        echo '<div class="mt-4">';
        echo '<button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Add Substitution') . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    // ==================== EMERGENCY PLANS TAB ====================
    if ($activeTab === 'emergency') {
        // Build query criteria
        $criteria = $accommodationPlanGateway->newQueryCriteria()
            ->sortBy(['severityLevel', 'surname', 'preferredName'])
            ->fromPOST();

        // Get emergency plan data
        $emergencyPlans = $accommodationPlanGateway->queryEmergencyPlans($criteria);

        // Build DataTable
        $table = DataTable::createPaginated('emergencyPlans', $criteria);
        $table->setTitle(__('Emergency Response Plans'));

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

        $table->addColumn('planName', __('Plan'))
            ->sortable()
            ->format(function ($row) {
                return htmlspecialchars($row['planName']);
            });

        $table->addColumn('triggerCondition', __('Trigger'))
            ->sortable()
            ->format(function ($row) {
                $text = htmlspecialchars($row['triggerCondition']);
                if (strlen($text) > 30) {
                    return '<span title="' . $text . '">' . substr($text, 0, 30) . '...</span>';
                }
                return $text;
            });

        $table->addColumn('severityLevel', __('Severity'))
            ->sortable()
            ->format(function ($row) {
                $colors = [
                    'Low'      => 'bg-green-100 text-green-800',
                    'Medium'   => 'bg-yellow-100 text-yellow-800',
                    'High'     => 'bg-orange-100 text-orange-800',
                    'Critical' => 'bg-red-100 text-red-800',
                ];
                $color = $colors[$row['severityLevel']] ?? 'bg-gray-100 text-gray-800';
                return '<span class="' . $color . ' text-xs px-2 py-1 rounded font-semibold">' . __($row['severityLevel']) . '</span>';
            });

        $table->addColumn('immediateActions', __('Immediate Actions'))
            ->notSortable()
            ->format(function ($row) {
                $text = htmlspecialchars($row['immediateActions']);
                if (strlen($text) > 50) {
                    return '<span title="' . $text . '">' . substr($text, 0, 50) . '...</span>';
                }
                return $text;
            });

        $table->addColumn('callEmergencyServices', __('Call 911'))
            ->notSortable()
            ->format(function ($row) {
                if ($row['callEmergencyServices'] === 'Y') {
                    return '<span class="text-red-600 font-bold">&#128222; ' . __('Yes') . '</span>';
                }
                return '<span class="text-gray-400">' . __('No') . '</span>';
            });

        $table->addColumn('medicationRequired', __('Medication'))
            ->notSortable()
            ->format(function ($row) {
                if (!empty($row['medicationRequired'])) {
                    $location = !empty($row['medicationLocation']) ? ' (' . htmlspecialchars($row['medicationLocation']) . ')' : '';
                    return '<span class="text-blue-600">&#128138; ' . htmlspecialchars($row['medicationRequired']) . $location . '</span>';
                }
                return '<span class="text-gray-400">-</span>';
            });

        $table->addColumn('active', __('Status'))
            ->notSortable()
            ->format(function ($row) {
                if ($row['active'] === 'Y') {
                    return '<span class="text-green-600">' . __('Active') . '</span>';
                }
                return '<span class="text-gray-500">' . __('Inactive') . '</span>';
            });

        // Output table
        if ($emergencyPlans->count() > 0) {
            echo $table->render($emergencyPlans);
        } else {
            echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500 mb-4">';
            echo __('No emergency plans found. Add emergency response plans to accommodation plans below.');
            echo '</div>';
        }

        // Section: Add New Emergency Plan
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Add Emergency Response Plan') . '</h3>';

        // Get active accommodation plans for dropdown
        $plansCriteria = $accommodationPlanGateway->newQueryCriteria();
        $activePlans = $accommodationPlanGateway->queryActiveAccommodationPlans($plansCriteria);

        echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=emergency">';
        echo '<input type="hidden" name="action" value="addEmergencyPlan">';

        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Accommodation Plan') . ' <span class="text-red-500">*</span></label>';
        echo '<select name="gibbonMedicalAccommodationPlanID" class="w-full border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select a plan...') . '</option>';
        foreach ($activePlans as $plan) {
            $childName = Format::name('', $plan['preferredName'], $plan['surname'], 'Student', false, true);
            echo '<option value="' . $plan['gibbonMedicalAccommodationPlanID'] . '">' . htmlspecialchars($childName . ' - ' . $plan['planName']) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Trigger Condition') . ' <span class="text-red-500">*</span></label>';
        echo '<input type="text" name="triggerCondition" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Exposure to peanuts, difficulty breathing') . '" required>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Severity Level') . '</label>';
        echo '<select name="severityLevel" class="w-full border rounded px-3 py-2">';
        foreach ($severityLevels as $value => $label) {
            $selected = $value === 'Medium' ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '</div>';

        echo '<div class="mb-4">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Immediate Actions') . ' <span class="text-red-500">*</span></label>';
        echo '<textarea name="immediateActions" class="w-full border rounded px-3 py-2" rows="3" placeholder="' . __('Step-by-step instructions for staff to follow immediately...') . '" required></textarea>';
        echo '</div>';

        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Medication Required') . '</label>';
        echo '<input type="text" name="medicationRequired" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., EpiPen, Benadryl') . '">';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Medication Location') . '</label>';
        echo '<input type="text" name="medicationLocation" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Nurse\'s office, backpack') . '">';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Call Emergency Services (911)') . '</label>';
        echo '<select name="callEmergencyServices" class="w-full border rounded px-3 py-2">';
        echo '<option value="N">' . __('No - Not immediately required') . '</option>';
        echo '<option value="Y">' . __('Yes - Call immediately') . '</option>';
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notify Parent') . '</label>';
        echo '<select name="parentNotification" class="w-full border rounded px-3 py-2">';
        echo '<option value="Y" selected>' . __('Yes - Notify immediately') . '</option>';
        echo '<option value="N">' . __('No - Not required') . '</option>';
        echo '</select>';
        echo '</div>';

        echo '</div>';

        echo '<div class="mb-4">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Additional Instructions') . '</label>';
        echo '<textarea name="additionalInstructions" class="w-full border rounded px-3 py-2" rows="2" placeholder="' . __('Any additional information or follow-up procedures...') . '"></textarea>';
        echo '</div>';

        echo '<div class="mt-4">';
        echo '<button type="submit" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">' . __('Add Emergency Plan') . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    // ==================== STAFF TRAINING TAB ====================
    if ($activeTab === 'training') {
        // Display training summary
        if (!empty($trainingSummary) && is_array($trainingSummary)) {
            echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
            echo '<h3 class="text-lg font-semibold mb-3">' . __('Training Summary by Type') . '</h3>';
            echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4">';
            foreach ($trainingSummary as $training) {
                $hasExpired = ($training['expiredCount'] ?? 0) > 0;
                $hasExpiring = ($training['expiringCount'] ?? 0) > 0;
                $bgColor = $hasExpired ? 'bg-red-50' : ($hasExpiring ? 'bg-yellow-50' : 'bg-gray-50');
                echo '<div class="' . $bgColor . ' rounded p-3 text-center">';
                echo '<span class="block text-sm font-medium text-gray-600">' . __($training['trainingType']) . '</span>';
                echo '<span class="block text-2xl font-bold text-gray-800">' . ($training['totalStaff'] ?? 0) . '</span>';
                echo '<span class="text-xs text-gray-500">' . __('trained staff') . '</span>';
                if ($hasExpired) {
                    echo '<span class="block text-xs text-red-600">' . ($training['expiredCount'] ?? 0) . ' ' . __('expired') . '</span>';
                }
                if ($hasExpiring) {
                    echo '<span class="block text-xs text-orange-600">' . ($training['expiringCount'] ?? 0) . ' ' . __('expiring soon') . '</span>';
                }
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }

        // Build query criteria for training records
        $criteria = $accommodationPlanGateway->newQueryCriteria()
            ->sortBy(['expiryDate', 'surname', 'preferredName'])
            ->fromPOST();

        // Get training records
        $trainingRecords = $accommodationPlanGateway->queryStaffTrainingRecords($criteria);

        // Build DataTable
        $table = DataTable::createPaginated('staffTraining', $criteria);
        $table->setTitle(__('Staff Training Records'));

        // Add columns
        $table->addColumn('image', __('Photo'))
            ->notSortable()
            ->format(function ($row) use ($session) {
                $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
                return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
            });

        $table->addColumn('name', __('Staff Name'))
            ->sortable(['surname', 'preferredName'])
            ->format(function ($row) {
                return Format::name('', $row['preferredName'], $row['surname'], 'Staff', true, true);
            });

        $table->addColumn('trainingType', __('Type'))
            ->sortable()
            ->format(function ($row) {
                return __($row['trainingType']);
            });

        $table->addColumn('trainingName', __('Training'))
            ->sortable()
            ->format(function ($row) {
                return htmlspecialchars($row['trainingName']);
            });

        $table->addColumn('completedDate', __('Completed'))
            ->sortable()
            ->format(function ($row) {
                return Format::date($row['completedDate']);
            });

        $table->addColumn('expiryDate', __('Expires'))
            ->sortable()
            ->format(function ($row) {
                if (empty($row['expiryDate'])) {
                    return '<span class="text-gray-400">' . __('No Expiry') . '</span>';
                }
                $expiryDate = $row['expiryDate'];
                $today = date('Y-m-d');
                $daysUntil = (strtotime($expiryDate) - strtotime($today)) / 86400;

                if ($daysUntil < 0) {
                    return '<span class="text-red-600 font-bold">&#9888; ' . Format::date($expiryDate) . '</span>';
                } elseif ($daysUntil <= 30) {
                    return '<span class="text-orange-600">' . Format::date($expiryDate) . '</span>';
                }
                return Format::date($expiryDate);
            });

        $table->addColumn('provider', __('Provider'))
            ->notSortable()
            ->format(function ($row) {
                return !empty($row['provider']) ? htmlspecialchars($row['provider']) : '-';
            });

        $table->addColumn('certificationNumber', __('Cert #'))
            ->notSortable()
            ->format(function ($row) {
                return !empty($row['certificationNumber']) ? htmlspecialchars($row['certificationNumber']) : '-';
            });

        $table->addColumn('verified', __('Verified'))
            ->notSortable()
            ->format(function ($row) {
                if ($row['verified'] === 'Y') {
                    $verifiedBy = !empty($row['verifiedByName'])
                        ? Format::name('', $row['verifiedByName'], $row['verifiedBySurname'], 'Staff', false, true)
                        : __('Staff');
                    $verifiedDate = !empty($row['verifiedDate']) ? Format::date($row['verifiedDate']) : '';
                    return '<span class="text-green-600" title="' . $verifiedBy . ' - ' . $verifiedDate . '">&#10003; ' . __('Verified') . '</span>';
                }
                return '<span class="text-yellow-600">&#9203; ' . __('Pending') . '</span>';
            });

        // Add action column
        $table->addActionColumn()
            ->format(function ($row, $actions) use ($session, $gibbonPersonID) {
                // Verify action (only for unverified training)
                if ($row['verified'] === 'N') {
                    $actions->addAction('verify', __('Verify'))
                        ->setIcon('iconTick')
                        ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=training')
                        ->addParam('verifyTraining', $row['gibbonMedicalStaffTrainingID'])
                        ->directLink();
                }

                return $actions;
            });

        // Output table
        if ($trainingRecords->count() > 0) {
            echo $table->render($trainingRecords);
        } else {
            echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500 mb-4">';
            echo __('No staff training records found. Add training records below.');
            echo '</div>';
        }

        // Section: Expiring Training Records
        $expiringTraining = $accommodationPlanGateway->selectExpiringTrainingRecords(30);
        if ($expiringTraining->rowCount() > 0) {
            echo '<div class="mt-6">';
            echo '<h3 class="text-lg font-semibold mb-3">' . __('Training Expiring Soon (within 30 days)') . '</h3>';
            echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">';
            echo '<div class="space-y-2">';
            while ($training = $expiringTraining->fetch()) {
                $staffName = Format::name('', $training['preferredName'], $training['surname'], 'Staff', false, true);
                $daysLeft = $training['daysRemaining'];
                $urgencyClass = $daysLeft <= 7 ? 'text-red-600' : ($daysLeft <= 14 ? 'text-orange-600' : 'text-yellow-600');
                echo '<div class="flex justify-between items-center bg-white p-2 rounded">';
                echo '<div>';
                echo '<span class="font-medium">' . htmlspecialchars($training['trainingName']) . '</span>';
                echo '<span class="text-sm text-gray-500"> - ' . htmlspecialchars($staffName) . '</span>';
                echo '<span class="text-xs text-gray-400 block">' . __($training['trainingType']) . '</span>';
                echo '</div>';
                echo '<span class="' . $urgencyClass . ' font-semibold">' . sprintf(__('%d days'), $daysLeft) . '</span>';
                echo '</div>';
            }
            echo '</div></div></div>';
        }

        // Section: Expired Training Records
        $expiredTraining = $accommodationPlanGateway->selectExpiredTrainingRecords();
        if ($expiredTraining->rowCount() > 0) {
            echo '<div class="mt-6">';
            echo '<h3 class="text-lg font-semibold mb-3">' . __('Expired Training - Action Required') . '</h3>';
            echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4">';
            echo '<div class="space-y-2">';
            while ($training = $expiredTraining->fetch()) {
                $staffName = Format::name('', $training['preferredName'], $training['surname'], 'Staff', false, true);
                $daysExpired = $training['daysExpired'];
                echo '<div class="flex justify-between items-center bg-white p-2 rounded border-l-4 border-red-500">';
                echo '<div>';
                echo '<span class="font-medium text-red-700">' . htmlspecialchars($training['trainingName']) . '</span>';
                echo '<span class="text-sm text-gray-500"> - ' . htmlspecialchars($staffName) . '</span>';
                echo '<span class="text-xs text-gray-400 block">' . __($training['trainingType']) . '</span>';
                echo '</div>';
                echo '<span class="text-red-600 font-semibold">' . sprintf(__('Expired %d days ago'), $daysExpired) . '</span>';
                echo '</div>';
            }
            echo '</div></div></div>';
        }

        // Section: Add New Staff Training
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Add Staff Training Record') . '</h3>';

        echo '<div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=training">';
        echo '<input type="hidden" name="action" value="addTraining">';

        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">';

        // Get staff members
        $sql = "SELECT gibbonPersonID, preferredName, surname
                FROM gibbonPerson
                WHERE status='Full'
                AND gibbonRoleIDPrimary IN (SELECT gibbonRoleID FROM gibbonRole WHERE category='Staff')
                ORDER BY surname, preferredName";
        $result = $connection2->query($sql);
        $staffMembers = [];
        while ($row = $result->fetch()) {
            $staffMembers[$row['gibbonPersonID']] = Format::name('', $row['preferredName'], $row['surname'], 'Staff', true, true);
        }

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Staff Member') . ' <span class="text-red-500">*</span></label>';
        echo '<select name="staffPersonID" class="w-full border rounded px-3 py-2" required>';
        echo '<option value="">' . __('Select staff member...') . '</option>';
        foreach ($staffMembers as $id => $name) {
            echo '<option value="' . $id . '">' . htmlspecialchars($name) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Training Type') . '</label>';
        echo '<select name="trainingType" class="w-full border rounded px-3 py-2">';
        foreach ($trainingTypes as $value => $label) {
            $selected = $value === 'Allergy Response' ? ' selected' : '';
            echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Training Name') . ' <span class="text-red-500">*</span></label>';
        echo '<input type="text" name="trainingName" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., EpiPen Administration Training') . '" required>';
        echo '</div>';

        echo '</div>';

        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Completed Date') . '</label>';
        echo '<input type="date" name="completedDate" class="w-full border rounded px-3 py-2" value="' . date('Y-m-d') . '">';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Expiry Date') . '</label>';
        echo '<input type="date" name="trainingExpiryDate" class="w-full border rounded px-3 py-2">';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Certification Number') . '</label>';
        echo '<input type="text" name="certificationNumber" class="w-full border rounded px-3 py-2" placeholder="' . __('Optional') . '">';
        echo '</div>';

        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Training Provider') . '</label>';
        echo '<input type="text" name="provider" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Red Cross, Hospital') . '">';
        echo '</div>';

        echo '</div>';

        echo '<div class="mb-4">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
        echo '<input type="text" name="trainingNotes" class="w-full border rounded px-3 py-2" placeholder="' . __('Additional information...') . '">';
        echo '</div>';

        echo '<div class="mt-4">';
        echo '<button type="submit" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('Add Training Record') . '</button>';
        echo '</div>';

        echo '</form>';
        echo '</div>';
    }

    // Quick links section
    echo '<div class="mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Links') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=plans&approved=N" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">' . __('Pending Approval') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=plans&planType=Dietary" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Dietary Plans') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=emergency" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">' . __('Emergency Plans') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_accommodations.php&tab=training" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('Staff Training') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
    echo '</div>';

    // JavaScript for dynamic allergy options
    echo '<script>
    function updateAllergyOptions() {
        var childID = document.getElementById("planChildSelect").value;
        var allergySelect = document.getElementById("allergySelect");

        // Clear current options
        allergySelect.innerHTML = "<option value=\"\">' . __('Loading...') . '</option>";

        if (!childID) {
            allergySelect.innerHTML = "<option value=\"\">' . __('None / Select child first') . '</option>";
            return;
        }

        // Fetch allergies for the selected child via AJAX
        var xhr = new XMLHttpRequest();
        xhr.open("GET", "' . $session->get('absoluteURL') . '/modules/MedicalTracking/medicalTracking_accommodations_ajax.php?gibbonPersonID=" + childID, true);
        xhr.onreadystatechange = function() {
            if (xhr.readyState === 4 && xhr.status === 200) {
                try {
                    var allergies = JSON.parse(xhr.responseText);
                    allergySelect.innerHTML = "<option value=\"\">' . __('None') . '</option>";
                    for (var i = 0; i < allergies.length; i++) {
                        var option = document.createElement("option");
                        option.value = allergies[i].id;
                        option.text = allergies[i].name + " (" + allergies[i].severity + ")";
                        allergySelect.add(option);
                    }
                } catch (e) {
                    allergySelect.innerHTML = "<option value=\"\">' . __('None') . '</option>";
                }
            }
        };
        xhr.send();
    }
    </script>';
}
