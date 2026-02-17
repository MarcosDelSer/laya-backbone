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
use Gibbon\Module\MedicalTracking\Domain\AllergyGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Medical Tracking'), 'medicalTracking.php');
$page->breadcrumbs->add(__('Manage Allergies'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/MedicalTracking/medicalTracking_allergies.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID and current user from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateway via DI container
    $allergyGateway = $container->get(AllergyGateway::class);

    // Allergen types and severity levels
    $allergenTypes = [
        'Food'          => __('Food'),
        'Medication'    => __('Medication'),
        'Environmental' => __('Environmental'),
        'Insect'        => __('Insect'),
        'Other'         => __('Other'),
    ];

    $severityLevels = [
        'Mild'             => __('Mild'),
        'Moderate'         => __('Moderate'),
        'Severe'           => __('Severe'),
        'Life-Threatening' => __('Life-Threatening'),
    ];

    // Get filter values from request
    $filterAllergenType = $_GET['allergenType'] ?? '';
    $filterSeverity = $_GET['severity'] ?? '';
    $filterEpiPen = $_GET['epiPen'] ?? '';
    $filterVerified = $_GET['verified'] ?? '';
    $filterActive = $_GET['active'] ?? 'Y';
    $filterChild = $_GET['gibbonPersonID'] ?? '';

    // Handle actions
    $action = $_POST['action'] ?? '';

    // Handle add allergy action
    if ($action === 'addAllergy') {
        $childID = $_POST['gibbonPersonID'] ?? null;
        $allergenName = trim($_POST['allergenName'] ?? '');
        $allergenType = $_POST['allergenType'] ?? 'Food';
        $severity = $_POST['severity'] ?? 'Moderate';
        $reaction = trim($_POST['reaction'] ?? '');
        $treatment = trim($_POST['treatment'] ?? '');
        $epiPenRequired = $_POST['epiPenRequired'] ?? 'N';
        $epiPenLocation = trim($_POST['epiPenLocation'] ?? '');
        $diagnosedDate = !empty($_POST['diagnosedDate']) ? Format::dateConvert($_POST['diagnosedDate']) : null;
        $diagnosedBy = trim($_POST['diagnosedBy'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($childID) && !empty($allergenName)) {
            $additionalData = [
                'reaction' => $reaction ?: null,
                'treatment' => $treatment ?: null,
                'epiPenRequired' => $epiPenRequired,
                'epiPenLocation' => $epiPenRequired === 'Y' ? $epiPenLocation : null,
                'diagnosedDate' => $diagnosedDate,
                'diagnosedBy' => $diagnosedBy ?: null,
                'notes' => $notes ?: null,
            ];

            $result = $allergyGateway->addAllergy(
                $childID,
                $allergenName,
                $allergenType,
                $severity,
                $gibbonPersonID,
                $additionalData
            );

            if ($result !== false) {
                $page->addSuccess(__('Allergy record has been added successfully.'));
            } else {
                $page->addError(__('This allergy already exists for this child or could not be added.'));
            }
        } else {
            $page->addError(__('Please select a child and enter an allergen name.'));
        }
    }

    // Handle verify allergy action
    if ($action === 'verifyAllergy') {
        $allergyID = $_POST['gibbonMedicalAllergyID'] ?? null;

        if (!empty($allergyID)) {
            $result = $allergyGateway->verifyAllergy($allergyID, $gibbonPersonID);

            if ($result) {
                $page->addSuccess(__('Allergy record has been verified.'));
            } else {
                $page->addError(__('Failed to verify allergy record.'));
            }
        }
    }

    // Handle deactivate allergy action
    if ($action === 'deactivateAllergy') {
        $allergyID = $_POST['gibbonMedicalAllergyID'] ?? null;

        if (!empty($allergyID)) {
            $result = $allergyGateway->deactivateAllergy($allergyID);

            if ($result) {
                $page->addSuccess(__('Allergy record has been deactivated.'));
            } else {
                $page->addError(__('Failed to deactivate allergy record.'));
            }
        }
    }

    // Page header
    echo '<h2>' . __('Allergy Management') . '</h2>';
    echo '<p class="text-gray-600 mb-4">' . __('View and manage allergy records for children. Add new allergies, verify records, and track severity levels.') . '</p>';

    // Get summary statistics
    $summary = $allergyGateway->getAllergySummary();

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Allergy Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">';

    $totalAllergies = 0;
    $totalSevere = 0;
    $totalEpiPen = 0;
    $totalUnverified = 0;

    if (!empty($summary) && is_array($summary)) {
        foreach ($summary as $row) {
            $totalAllergies += $row['totalCount'] ?? 0;
            if (in_array($row['severity'] ?? '', ['Severe', 'Life-Threatening'])) {
                $totalSevere += $row['totalCount'] ?? 0;
            }
            $totalEpiPen += $row['epiPenCount'] ?? 0;
            $totalUnverified += $row['unverifiedCount'] ?? 0;
        }
    }

    echo '<div class="bg-gray-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-gray-600">' . __('Total Allergies') . '</span>';
    echo '<span class="block text-3xl font-bold text-gray-800">' . $totalAllergies . '</span>';
    echo '</div>';

    echo '<div class="bg-red-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-red-600">' . __('Severe/Critical') . '</span>';
    echo '<span class="block text-3xl font-bold text-red-700">' . $totalSevere . '</span>';
    echo '</div>';

    echo '<div class="bg-orange-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-orange-600">' . __('EpiPen Required') . '</span>';
    echo '<span class="block text-3xl font-bold text-orange-700">' . $totalEpiPen . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-3">';
    echo '<span class="block text-sm font-medium text-yellow-600">' . __('Unverified') . '</span>';
    echo '<span class="block text-3xl font-bold text-yellow-700">' . $totalUnverified . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Filter form
    $filterForm = Form::create('allergyFilter', $session->get('absoluteURL') . '/index.php');
    $filterForm->setMethod('get');
    $filterForm->setClass('noIntBorder fullWidth');
    $filterForm->addHiddenValue('q', '/modules/MedicalTracking/medicalTracking_allergies.php');

    $row = $filterForm->addRow();
    $row->addLabel('allergenType', __('Allergen Type'));
    $row->addSelect('allergenType')
        ->fromArray(['' => __('All Types')] + $allergenTypes)
        ->selected($filterAllergenType);

    $row = $filterForm->addRow();
    $row->addLabel('severity', __('Severity'));
    $row->addSelect('severity')
        ->fromArray(['' => __('All Severities')] + $severityLevels)
        ->selected($filterSeverity);

    $row = $filterForm->addRow();
    $row->addLabel('epiPen', __('EpiPen Required'));
    $row->addSelect('epiPen')
        ->fromArray(['' => __('All'), 'Y' => __('Yes'), 'N' => __('No')])
        ->selected($filterEpiPen);

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
    $row->addSearchSubmit($session, __('Clear Filters'), ['allergenType', 'severity', 'epiPen', 'verified', 'active']);

    echo $filterForm->getOutput();

    // Build query criteria
    $criteria = $allergyGateway->newQueryCriteria()
        ->sortBy(['severity', 'surname', 'preferredName'])
        ->fromPOST();

    // Add filters to criteria
    if (!empty($filterAllergenType)) {
        $criteria->filterBy('allergenType', $filterAllergenType);
    }
    if (!empty($filterSeverity)) {
        $criteria->filterBy('severity', $filterSeverity);
    }
    if (!empty($filterEpiPen)) {
        $criteria->filterBy('epiPenRequired', $filterEpiPen);
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

    // Get allergy data
    $allergies = $allergyGateway->queryAllergies($criteria);

    // Build DataTable
    $table = DataTable::createPaginated('allergies', $criteria);
    $table->setTitle(__('Allergy Records'));

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

    $table->addColumn('allergenName', __('Allergen'))
        ->sortable()
        ->format(function ($row) {
            $typeIcon = '';
            switch ($row['allergenType']) {
                case 'Food':
                    $typeIcon = '🍽️';
                    break;
                case 'Medication':
                    $typeIcon = '💊';
                    break;
                case 'Environmental':
                    $typeIcon = '🌿';
                    break;
                case 'Insect':
                    $typeIcon = '🐝';
                    break;
                default:
                    $typeIcon = '⚠️';
            }
            return '<span title="' . __($row['allergenType']) . '">' . $typeIcon . '</span> ' . htmlspecialchars($row['allergenName']);
        });

    $table->addColumn('allergenType', __('Type'))
        ->sortable()
        ->format(function ($row) {
            return __($row['allergenType']);
        });

    $table->addColumn('severity', __('Severity'))
        ->sortable()
        ->format(function ($row) {
            $colors = [
                'Mild'             => 'bg-green-100 text-green-800',
                'Moderate'         => 'bg-yellow-100 text-yellow-800',
                'Severe'           => 'bg-orange-100 text-orange-800',
                'Life-Threatening' => 'bg-red-100 text-red-800',
            ];
            $color = $colors[$row['severity']] ?? 'bg-gray-100 text-gray-800';
            return '<span class="' . $color . ' text-xs px-2 py-1 rounded font-semibold">' . __($row['severity']) . '</span>';
        });

    $table->addColumn('epiPenRequired', __('EpiPen'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['epiPenRequired'] === 'Y') {
                $location = !empty($row['epiPenLocation']) ? htmlspecialchars($row['epiPenLocation']) : __('Location not specified');
                return '<span class="text-red-600 font-bold" title="' . $location . '">💉 ' . __('Required') . '</span>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    $table->addColumn('reaction', __('Reaction'))
        ->notSortable()
        ->format(function ($row) {
            if (empty($row['reaction'])) {
                return '<span class="text-gray-400">-</span>';
            }
            $text = htmlspecialchars($row['reaction']);
            if (strlen($text) > 40) {
                return '<span title="' . $text . '">' . substr($text, 0, 40) . '...</span>';
            }
            return $text;
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
            // Verify action (only for unverified, active allergies)
            if ($row['verified'] === 'N' && $row['active'] === 'Y') {
                $actions->addAction('verify', __('Verify'))
                    ->setIcon('iconTick')
                    ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php')
                    ->addParam('verify', $row['gibbonMedicalAllergyID'])
                    ->directLink();
            }

            // Deactivate action (only for active allergies)
            if ($row['active'] === 'Y') {
                $actions->addAction('deactivate', __('Deactivate'))
                    ->setIcon('iconCross')
                    ->setURL($session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php')
                    ->addParam('deactivate', $row['gibbonMedicalAllergyID'])
                    ->directLink();
            }

            return $actions;
        });

    // Output table
    if ($allergies->count() > 0) {
        echo $table->render($allergies);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500 mb-4">';
        echo __('No allergy records found matching the selected criteria.');
        echo '</div>';
    }

    // Handle quick actions via GET parameters
    $verifyID = $_GET['verify'] ?? null;
    $deactivateID = $_GET['deactivate'] ?? null;

    if (!empty($verifyID)) {
        $result = $allergyGateway->verifyAllergy($verifyID, $gibbonPersonID);
        if ($result) {
            echo '<script>window.location.href = "' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php";</script>';
        }
    }

    if (!empty($deactivateID)) {
        $result = $allergyGateway->deactivateAllergy($deactivateID);
        if ($result) {
            echo '<script>window.location.href = "' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php";</script>';
        }
    }

    // Section: Add New Allergy
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Add New Allergy Record') . '</h3>';

    // Get common allergens for suggestions
    $commonAllergens = $allergyGateway->selectCommonAllergens();
    $allergenSuggestions = [];
    while ($allergen = $commonAllergens->fetch()) {
        $allergenSuggestions[$allergen['allergenName']] = $allergen['allergenName'] . ' (' . __($allergen['allergenCategory']) . ')';
    }

    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">';
    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php">';
    echo '<input type="hidden" name="action" value="addAllergy">';

    // Child selection
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
    echo '<label class="block text-sm font-medium mb-1">' . __('Allergen Name') . ' <span class="text-red-500">*</span></label>';
    echo '<input type="text" name="allergenName" list="commonAllergens" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Peanuts, Penicillin') . '" required>';
    echo '<datalist id="commonAllergens">';
    foreach ($allergenSuggestions as $name => $display) {
        echo '<option value="' . htmlspecialchars($name) . '">' . htmlspecialchars($display) . '</option>';
    }
    echo '</datalist>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Allergen Type') . '</label>';
    echo '<select name="allergenType" class="w-full border rounded px-3 py-2">';
    foreach ($allergenTypes as $value => $label) {
        $selected = $value === 'Food' ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '</div>';

    // Severity and EpiPen
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-4">';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Severity') . '</label>';
    echo '<select name="severity" class="w-full border rounded px-3 py-2">';
    foreach ($severityLevels as $value => $label) {
        $selected = $value === 'Moderate' ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('EpiPen Required') . '</label>';
    echo '<select name="epiPenRequired" id="epiPenRequired" class="w-full border rounded px-3 py-2" onchange="toggleEpiPenLocation()">';
    echo '<option value="N">' . __('No') . '</option>';
    echo '<option value="Y">' . __('Yes') . '</option>';
    echo '</select>';
    echo '</div>';

    echo '<div id="epiPenLocationDiv" style="display:none;">';
    echo '<label class="block text-sm font-medium mb-1">' . __('EpiPen Location') . '</label>';
    echo '<input type="text" name="epiPenLocation" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Nurse\'s office, backpack') . '">';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Diagnosed Date') . '</label>';
    echo '<input type="date" name="diagnosedDate" class="w-full border rounded px-3 py-2">';
    echo '</div>';

    echo '</div>';

    // Reaction and treatment
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Reaction Description') . '</label>';
    echo '<textarea name="reaction" class="w-full border rounded px-3 py-2" rows="2" placeholder="' . __('Describe the allergic reaction symptoms...') . '"></textarea>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Treatment/Response') . '</label>';
    echo '<textarea name="treatment" class="w-full border rounded px-3 py-2" rows="2" placeholder="' . __('Describe the recommended treatment...') . '"></textarea>';
    echo '</div>';

    echo '</div>';

    // Additional info
    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Diagnosed By') . '</label>';
    echo '<input type="text" name="diagnosedBy" class="w-full border rounded px-3 py-2" placeholder="' . __('Doctor/specialist name') . '">';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-sm font-medium mb-1">' . __('Notes') . '</label>';
    echo '<input type="text" name="notes" class="w-full border rounded px-3 py-2" placeholder="' . __('Additional notes...') . '">';
    echo '</div>';

    echo '</div>';

    // Submit button
    echo '<div class="mt-4">';
    echo '<button type="submit" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Add Allergy Record') . '</button>';
    echo '</div>';

    echo '</form>';
    echo '</div>';

    // JavaScript for EpiPen location toggle
    echo '<script>
    function toggleEpiPenLocation() {
        var epiPenRequired = document.getElementById("epiPenRequired").value;
        var locationDiv = document.getElementById("epiPenLocationDiv");
        if (epiPenRequired === "Y") {
            locationDiv.style.display = "block";
        } else {
            locationDiv.style.display = "none";
        }
    }
    </script>';

    // Quick links section
    echo '<div class="mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Links') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php&severity=Severe" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">' . __('View Severe Allergies') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php&severity=Life-Threatening" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">' . __('View Life-Threatening') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php&epiPen=Y" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('EpiPen Required') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking_allergies.php&verified=N" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">' . __('Unverified Records') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalTracking/medicalTracking.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
    echo '</div>';
}
