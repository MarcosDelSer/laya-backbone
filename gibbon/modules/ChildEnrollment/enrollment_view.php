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

use Gibbon\Services\Format;
use Gibbon\Module\ChildEnrollment\Domain\EnrollmentFormGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Enrollment Forms'), 'enrollment_list.php');
$page->breadcrumbs->add(__('View Enrollment Form'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/ChildEnrollment/enrollment_view.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get form ID from request
    $gibbonChildEnrollmentFormID = $_GET['gibbonChildEnrollmentFormID'] ?? null;

    if (empty($gibbonChildEnrollmentFormID)) {
        $page->addError(__('You have not specified one or more required parameters.'));
        return;
    }

    // Get gateway via DI container
    $enrollmentFormGateway = $container->get(EnrollmentFormGateway::class);

    // Get form with all related data
    $form = $enrollmentFormGateway->getFormWithRelations($gibbonChildEnrollmentFormID);

    if ($form === false) {
        $page->addError(__('The specified record cannot be found.'));
        return;
    }

    // Status badge styling
    $statusClasses = [
        'Draft' => 'bg-gray-100 text-gray-800 border-gray-300',
        'Submitted' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
        'Approved' => 'bg-green-100 text-green-800 border-green-300',
        'Rejected' => 'bg-red-100 text-red-800 border-red-300',
        'Expired' => 'bg-orange-100 text-orange-800 border-orange-300',
    ];
    $statusClass = $statusClasses[$form['status']] ?? 'bg-gray-100 text-gray-800 border-gray-300';

    // Page header with action buttons
    echo '<div class="flex justify-between items-start mb-6">';
    echo '<div>';
    echo '<h2 class="mb-2">' . __('Enrollment Form') . ': ' . htmlspecialchars($form['formNumber']) . '</h2>';
    echo '<span class="inline-block px-3 py-1 rounded-full text-sm font-medium border ' . $statusClass . '">' . __($form['status']) . '</span>';
    echo '<span class="ml-2 text-sm text-gray-500">' . __('Version') . ' ' . $form['version'] . '</span>';
    echo '</div>';
    echo '<div class="flex gap-2">';

    // Edit button (only for Draft or Rejected forms)
    if (in_array($form['status'], ['Draft', 'Rejected'])) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ChildEnrollment/enrollment_edit.php&gibbonChildEnrollmentFormID=' . $gibbonChildEnrollmentFormID . '" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Edit') . '</a>';
    }

    // PDF button
    echo '<a href="' . $session->get('absoluteURL') . '/modules/ChildEnrollment/enrollment_pdf.php?gibbonChildEnrollmentFormID=' . $gibbonChildEnrollmentFormID . '" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600" target="_blank">' . __('Download PDF') . '</a>';

    echo '</div>';
    echo '</div>';

    // Section: Child Information
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Child Information') . '</h3>';
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

    // Name
    echo '<div>';
    echo '<label class="block text-sm font-medium text-gray-500">' . __('Name') . '</label>';
    echo '<p class="text-lg">' . htmlspecialchars($form['childFirstName'] . ' ' . $form['childLastName']) . '</p>';
    echo '</div>';

    // Date of Birth
    echo '<div>';
    echo '<label class="block text-sm font-medium text-gray-500">' . __('Date of Birth') . '</label>';
    echo '<p class="text-lg">' . Format::date($form['childDateOfBirth']) . '</p>';
    echo '</div>';

    // Admission Date
    echo '<div>';
    echo '<label class="block text-sm font-medium text-gray-500">' . __('Admission Date') . '</label>';
    echo '<p class="text-lg">' . (!empty($form['admissionDate']) ? Format::date($form['admissionDate']) : '-') . '</p>';
    echo '</div>';

    // Address
    echo '<div class="md:col-span-2">';
    echo '<label class="block text-sm font-medium text-gray-500">' . __('Address') . '</label>';
    $address = [];
    if (!empty($form['childAddress'])) $address[] = $form['childAddress'];
    if (!empty($form['childCity'])) $address[] = $form['childCity'];
    if (!empty($form['childPostalCode'])) $address[] = $form['childPostalCode'];
    echo '<p class="text-lg">' . (!empty($address) ? htmlspecialchars(implode(', ', $address)) : '-') . '</p>';
    echo '</div>';

    // Languages
    echo '<div>';
    echo '<label class="block text-sm font-medium text-gray-500">' . __('Languages Spoken') . '</label>';
    echo '<p class="text-lg">' . (!empty($form['languagesSpoken']) ? htmlspecialchars($form['languagesSpoken']) : '-') . '</p>';
    echo '</div>';

    // Notes
    if (!empty($form['notes'])) {
        echo '<div class="md:col-span-3">';
        echo '<label class="block text-sm font-medium text-gray-500">' . __('Notes') . '</label>';
        echo '<p class="text-lg">' . nl2br(htmlspecialchars($form['notes'])) . '</p>';
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';

    // Section: Parent/Guardian Information
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Parent/Guardian Information') . '</h3>';

    if (!empty($form['parents'])) {
        echo '<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">';
        foreach ($form['parents'] as $parent) {
            $isPrimary = $parent['isPrimaryContact'] === 'Y';
            echo '<div class="border rounded-lg p-4 ' . ($isPrimary ? 'border-blue-300 bg-blue-50' : 'border-gray-200') . '">';
            echo '<div class="flex justify-between items-start mb-3">';
            echo '<h4 class="font-medium">' . __('Parent') . ' ' . $parent['parentNumber'] . '</h4>';
            if ($isPrimary) {
                echo '<span class="bg-blue-500 text-white text-xs px-2 py-1 rounded">' . __('Primary Contact') . '</span>';
            }
            echo '</div>';

            echo '<div class="grid grid-cols-2 gap-3 text-sm">';

            // Name & Relationship
            echo '<div class="col-span-2">';
            echo '<label class="text-gray-500">' . __('Name') . '</label>';
            echo '<p class="font-medium">' . htmlspecialchars($parent['name']) . ' (' . htmlspecialchars($parent['relationship']) . ')</p>';
            echo '</div>';

            // Contact info
            if (!empty($parent['cellPhone'])) {
                echo '<div>';
                echo '<label class="text-gray-500">' . __('Cell Phone') . '</label>';
                echo '<p>' . htmlspecialchars($parent['cellPhone']) . '</p>';
                echo '</div>';
            }
            if (!empty($parent['homePhone'])) {
                echo '<div>';
                echo '<label class="text-gray-500">' . __('Home Phone') . '</label>';
                echo '<p>' . htmlspecialchars($parent['homePhone']) . '</p>';
                echo '</div>';
            }
            if (!empty($parent['workPhone'])) {
                echo '<div>';
                echo '<label class="text-gray-500">' . __('Work Phone') . '</label>';
                echo '<p>' . htmlspecialchars($parent['workPhone']) . '</p>';
                echo '</div>';
            }
            if (!empty($parent['email'])) {
                echo '<div>';
                echo '<label class="text-gray-500">' . __('Email') . '</label>';
                echo '<p>' . htmlspecialchars($parent['email']) . '</p>';
                echo '</div>';
            }

            // Address
            $parentAddress = [];
            if (!empty($parent['address'])) $parentAddress[] = $parent['address'];
            if (!empty($parent['city'])) $parentAddress[] = $parent['city'];
            if (!empty($parent['postalCode'])) $parentAddress[] = $parent['postalCode'];
            if (!empty($parentAddress)) {
                echo '<div class="col-span-2">';
                echo '<label class="text-gray-500">' . __('Address') . '</label>';
                echo '<p>' . htmlspecialchars(implode(', ', $parentAddress)) . '</p>';
                echo '</div>';
            }

            // Work info
            if (!empty($parent['employer'])) {
                echo '<div class="col-span-2">';
                echo '<label class="text-gray-500">' . __('Employer') . '</label>';
                echo '<p>' . htmlspecialchars($parent['employer']);
                if (!empty($parent['workHours'])) {
                    echo ' (' . htmlspecialchars($parent['workHours']) . ')';
                }
                echo '</p>';
                if (!empty($parent['workAddress'])) {
                    echo '<p class="text-gray-500 text-xs">' . htmlspecialchars($parent['workAddress']) . '</p>';
                }
                echo '</div>';
            }

            echo '</div>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-gray-500">' . __('No parent information recorded.') . '</p>';
    }
    echo '</div>';

    // Section: Authorized Pickups
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Authorized Pickup Persons') . '</h3>';

    if (!empty($form['authorizedPickups'])) {
        echo '<div class="overflow-x-auto">';
        echo '<table class="min-w-full divide-y divide-gray-200">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">' . __('Priority') . '</th>';
        echo '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">' . __('Name') . '</th>';
        echo '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">' . __('Relationship') . '</th>';
        echo '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">' . __('Phone') . '</th>';
        echo '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">' . __('Notes') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="bg-white divide-y divide-gray-200">';
        foreach ($form['authorizedPickups'] as $pickup) {
            echo '<tr>';
            echo '<td class="px-4 py-3 whitespace-nowrap"><span class="bg-gray-200 px-2 py-1 rounded text-sm">#' . $pickup['priority'] . '</span></td>';
            echo '<td class="px-4 py-3 whitespace-nowrap font-medium">' . htmlspecialchars($pickup['name']) . '</td>';
            echo '<td class="px-4 py-3 whitespace-nowrap">' . htmlspecialchars($pickup['relationship']) . '</td>';
            echo '<td class="px-4 py-3 whitespace-nowrap">' . htmlspecialchars($pickup['phone']) . '</td>';
            echo '<td class="px-4 py-3">' . (!empty($pickup['notes']) ? htmlspecialchars($pickup['notes']) : '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500">' . __('No authorized pickup persons recorded.') . '</p>';
    }
    echo '</div>';

    // Section: Emergency Contacts
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Emergency Contacts') . '</h3>';

    if (!empty($form['emergencyContacts'])) {
        echo '<div class="overflow-x-auto">';
        echo '<table class="min-w-full divide-y divide-gray-200">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">' . __('Priority') . '</th>';
        echo '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">' . __('Name') . '</th>';
        echo '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">' . __('Relationship') . '</th>';
        echo '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">' . __('Phone') . '</th>';
        echo '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">' . __('Alternate Phone') . '</th>';
        echo '<th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">' . __('Notes') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="bg-white divide-y divide-gray-200">';
        foreach ($form['emergencyContacts'] as $contact) {
            echo '<tr>';
            echo '<td class="px-4 py-3 whitespace-nowrap"><span class="bg-red-100 text-red-800 px-2 py-1 rounded text-sm">#' . $contact['priority'] . '</span></td>';
            echo '<td class="px-4 py-3 whitespace-nowrap font-medium">' . htmlspecialchars($contact['name']) . '</td>';
            echo '<td class="px-4 py-3 whitespace-nowrap">' . htmlspecialchars($contact['relationship']) . '</td>';
            echo '<td class="px-4 py-3 whitespace-nowrap">' . htmlspecialchars($contact['phone']) . '</td>';
            echo '<td class="px-4 py-3 whitespace-nowrap">' . (!empty($contact['alternatePhone']) ? htmlspecialchars($contact['alternatePhone']) : '-') . '</td>';
            echo '<td class="px-4 py-3">' . (!empty($contact['notes']) ? htmlspecialchars($contact['notes']) : '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<p class="text-gray-500">' . __('No emergency contacts recorded.') . '</p>';
    }
    echo '</div>';

    // Section: Health Information
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Health Information') . '</h3>';

    if (!empty($form['health'])) {
        $health = $form['health'];
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

        // EpiPen alert
        if ($health['hasEpiPen'] === 'Y') {
            echo '<div class="md:col-span-3 bg-red-50 border border-red-200 rounded-lg p-4">';
            echo '<div class="flex items-center">';
            echo '<span class="text-red-600 font-bold text-lg mr-2">!</span>';
            echo '<span class="text-red-800 font-medium">' . __('Child has EpiPen') . '</span>';
            echo '</div>';
            if (!empty($health['epiPenInstructions'])) {
                echo '<p class="mt-2 text-red-700">' . nl2br(htmlspecialchars($health['epiPenInstructions'])) . '</p>';
            }
            echo '</div>';
        }

        // Allergies
        echo '<div class="md:col-span-2">';
        echo '<label class="block text-sm font-medium text-gray-500">' . __('Allergies') . '</label>';
        if (!empty($health['allergies'])) {
            $allergies = json_decode($health['allergies'], true);
            if (is_array($allergies)) {
                echo '<ul class="list-disc list-inside">';
                foreach ($allergies as $allergy) {
                    if (is_array($allergy)) {
                        echo '<li>' . htmlspecialchars($allergy['name'] ?? '') .
                             (!empty($allergy['severity']) ? ' (' . htmlspecialchars($allergy['severity']) . ')' : '') . '</li>';
                    } else {
                        echo '<li>' . htmlspecialchars($allergy) . '</li>';
                    }
                }
                echo '</ul>';
            } else {
                echo '<p>' . htmlspecialchars($health['allergies']) . '</p>';
            }
        } else {
            echo '<p class="text-gray-400">' . __('None recorded') . '</p>';
        }
        echo '</div>';

        // Medical Conditions
        echo '<div>';
        echo '<label class="block text-sm font-medium text-gray-500">' . __('Medical Conditions') . '</label>';
        echo '<p>' . (!empty($health['medicalConditions']) ? nl2br(htmlspecialchars($health['medicalConditions'])) : '<span class="text-gray-400">' . __('None recorded') . '</span>') . '</p>';
        echo '</div>';

        // Medications
        echo '<div class="md:col-span-2">';
        echo '<label class="block text-sm font-medium text-gray-500">' . __('Medications') . '</label>';
        if (!empty($health['medications'])) {
            $medications = json_decode($health['medications'], true);
            if (is_array($medications)) {
                echo '<ul class="list-disc list-inside">';
                foreach ($medications as $med) {
                    if (is_array($med)) {
                        echo '<li>' . htmlspecialchars($med['name'] ?? '');
                        if (!empty($med['dosage'])) echo ' - ' . htmlspecialchars($med['dosage']);
                        if (!empty($med['schedule'])) echo ' (' . htmlspecialchars($med['schedule']) . ')';
                        echo '</li>';
                    } else {
                        echo '<li>' . htmlspecialchars($med) . '</li>';
                    }
                }
                echo '</ul>';
            } else {
                echo '<p>' . htmlspecialchars($health['medications']) . '</p>';
            }
        } else {
            echo '<p class="text-gray-400">' . __('None recorded') . '</p>';
        }
        echo '</div>';

        // Special Needs
        if (!empty($health['specialNeeds'])) {
            echo '<div class="md:col-span-3">';
            echo '<label class="block text-sm font-medium text-gray-500">' . __('Special Needs') . '</label>';
            echo '<p>' . nl2br(htmlspecialchars($health['specialNeeds'])) . '</p>';
            echo '</div>';
        }

        // Doctor Info
        echo '<div class="md:col-span-3 border-t pt-4 mt-2">';
        echo '<h4 class="font-medium mb-2">' . __('Doctor Information') . '</h4>';
        echo '<div class="grid grid-cols-3 gap-4">';
        echo '<div>';
        echo '<label class="block text-sm text-gray-500">' . __('Doctor Name') . '</label>';
        echo '<p>' . (!empty($health['doctorName']) ? htmlspecialchars($health['doctorName']) : '-') . '</p>';
        echo '</div>';
        echo '<div>';
        echo '<label class="block text-sm text-gray-500">' . __('Doctor Phone') . '</label>';
        echo '<p>' . (!empty($health['doctorPhone']) ? htmlspecialchars($health['doctorPhone']) : '-') . '</p>';
        echo '</div>';
        echo '<div>';
        echo '<label class="block text-sm text-gray-500">' . __('Doctor Address') . '</label>';
        echo '<p>' . (!empty($health['doctorAddress']) ? htmlspecialchars($health['doctorAddress']) : '-') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Health Insurance
        echo '<div class="md:col-span-3 border-t pt-4 mt-2">';
        echo '<h4 class="font-medium mb-2">' . __('Health Insurance (RAMQ)') . '</h4>';
        echo '<div class="grid grid-cols-2 gap-4">';
        echo '<div>';
        echo '<label class="block text-sm text-gray-500">' . __('Insurance Number') . '</label>';
        echo '<p class="font-mono">' . (!empty($health['healthInsuranceNumber']) ? htmlspecialchars($health['healthInsuranceNumber']) : '-') . '</p>';
        echo '</div>';
        echo '<div>';
        echo '<label class="block text-sm text-gray-500">' . __('Expiry Date') . '</label>';
        echo '<p>' . (!empty($health['healthInsuranceExpiry']) ? Format::date($health['healthInsuranceExpiry']) : '-') . '</p>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>';
    } else {
        echo '<p class="text-gray-500">' . __('No health information recorded.') . '</p>';
    }
    echo '</div>';

    // Section: Nutrition Information
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Nutrition Information') . '</h3>';

    if (!empty($form['nutrition'])) {
        $nutrition = $form['nutrition'];
        echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';

        // Bottle Feeding alert
        if ($nutrition['isBottleFeeding'] === 'Y') {
            echo '<div class="md:col-span-2 bg-blue-50 border border-blue-200 rounded-lg p-4">';
            echo '<span class="text-blue-800 font-medium">' . __('Child is bottle feeding') . '</span>';
            if (!empty($nutrition['bottleFeedingInfo'])) {
                echo '<p class="mt-2 text-blue-700">' . nl2br(htmlspecialchars($nutrition['bottleFeedingInfo'])) . '</p>';
            }
            echo '</div>';
        }

        // Dietary Restrictions
        echo '<div>';
        echo '<label class="block text-sm font-medium text-gray-500">' . __('Dietary Restrictions') . '</label>';
        echo '<p>' . (!empty($nutrition['dietaryRestrictions']) ? nl2br(htmlspecialchars($nutrition['dietaryRestrictions'])) : '<span class="text-gray-400">' . __('None recorded') . '</span>') . '</p>';
        echo '</div>';

        // Food Allergies
        echo '<div>';
        echo '<label class="block text-sm font-medium text-gray-500">' . __('Food Allergies') . '</label>';
        echo '<p>' . (!empty($nutrition['foodAllergies']) ? nl2br(htmlspecialchars($nutrition['foodAllergies'])) : '<span class="text-gray-400">' . __('None recorded') . '</span>') . '</p>';
        echo '</div>';

        // Feeding Instructions
        if (!empty($nutrition['feedingInstructions'])) {
            echo '<div class="md:col-span-2">';
            echo '<label class="block text-sm font-medium text-gray-500">' . __('Special Feeding Instructions') . '</label>';
            echo '<p>' . nl2br(htmlspecialchars($nutrition['feedingInstructions'])) . '</p>';
            echo '</div>';
        }

        // Food Preferences
        echo '<div>';
        echo '<label class="block text-sm font-medium text-gray-500">' . __('Food Preferences') . '</label>';
        echo '<p>' . (!empty($nutrition['foodPreferences']) ? nl2br(htmlspecialchars($nutrition['foodPreferences'])) : '<span class="text-gray-400">' . __('Not specified') . '</span>') . '</p>';
        echo '</div>';

        // Food Dislikes
        echo '<div>';
        echo '<label class="block text-sm font-medium text-gray-500">' . __('Food Dislikes') . '</label>';
        echo '<p>' . (!empty($nutrition['foodDislikes']) ? nl2br(htmlspecialchars($nutrition['foodDislikes'])) : '<span class="text-gray-400">' . __('Not specified') . '</span>') . '</p>';
        echo '</div>';

        // Meal Plan Notes
        if (!empty($nutrition['mealPlanNotes'])) {
            echo '<div class="md:col-span-2">';
            echo '<label class="block text-sm font-medium text-gray-500">' . __('Meal Plan Notes') . '</label>';
            echo '<p>' . nl2br(htmlspecialchars($nutrition['mealPlanNotes'])) . '</p>';
            echo '</div>';
        }

        echo '</div>';
    } else {
        echo '<p class="text-gray-500">' . __('No nutrition information recorded.') . '</p>';
    }
    echo '</div>';

    // Section: Attendance Pattern
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Weekly Attendance Schedule') . '</h3>';

    if (!empty($form['attendance'])) {
        $attendance = $form['attendance'];

        // Schedule grid
        echo '<div class="overflow-x-auto">';
        echo '<table class="min-w-full">';
        echo '<thead>';
        echo '<tr class="bg-gray-50">';
        echo '<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase"></th>';
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $dayLabels = [__('Mon'), __('Tue'), __('Wed'), __('Thu'), __('Fri'), __('Sat'), __('Sun')];
        foreach ($dayLabels as $label) {
            echo '<th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">' . $label . '</th>';
        }
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        // AM row
        echo '<tr>';
        echo '<td class="px-4 py-2 font-medium text-sm">' . __('AM') . '</td>';
        foreach ($days as $day) {
            $field = $day . 'Am';
            $checked = isset($attendance[$field]) && $attendance[$field] === 'Y';
            echo '<td class="px-4 py-2 text-center">';
            if ($checked) {
                echo '<span class="inline-block w-6 h-6 bg-green-500 text-white rounded-full leading-6 text-sm font-bold">&#10003;</span>';
            } else {
                echo '<span class="inline-block w-6 h-6 bg-gray-200 rounded-full"></span>';
            }
            echo '</td>';
        }
        echo '</tr>';

        // PM row
        echo '<tr>';
        echo '<td class="px-4 py-2 font-medium text-sm">' . __('PM') . '</td>';
        foreach ($days as $day) {
            $field = $day . 'Pm';
            $checked = isset($attendance[$field]) && $attendance[$field] === 'Y';
            echo '<td class="px-4 py-2 text-center">';
            if ($checked) {
                echo '<span class="inline-block w-6 h-6 bg-green-500 text-white rounded-full leading-6 text-sm font-bold">&#10003;</span>';
            } else {
                echo '<span class="inline-block w-6 h-6 bg-gray-200 rounded-full"></span>';
            }
            echo '</td>';
        }
        echo '</tr>';

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        // Additional attendance info
        echo '<div class="grid grid-cols-3 gap-4 mt-4 pt-4 border-t">';
        echo '<div>';
        echo '<label class="block text-sm font-medium text-gray-500">' . __('Expected Arrival') . '</label>';
        echo '<p>' . (!empty($attendance['expectedArrivalTime']) ? Format::time($attendance['expectedArrivalTime']) : '-') . '</p>';
        echo '</div>';
        echo '<div>';
        echo '<label class="block text-sm font-medium text-gray-500">' . __('Expected Departure') . '</label>';
        echo '<p>' . (!empty($attendance['expectedDepartureTime']) ? Format::time($attendance['expectedDepartureTime']) : '-') . '</p>';
        echo '</div>';
        echo '<div>';
        echo '<label class="block text-sm font-medium text-gray-500">' . __('Expected Hours/Week') . '</label>';
        echo '<p>' . (!empty($attendance['expectedHoursPerWeek']) ? $attendance['expectedHoursPerWeek'] . ' ' . __('hours') : '-') . '</p>';
        echo '</div>';
        echo '</div>';

        if (!empty($attendance['notes'])) {
            echo '<div class="mt-4 pt-4 border-t">';
            echo '<label class="block text-sm font-medium text-gray-500">' . __('Notes') . '</label>';
            echo '<p>' . nl2br(htmlspecialchars($attendance['notes'])) . '</p>';
            echo '</div>';
        }
    } else {
        echo '<p class="text-gray-500">' . __('No attendance schedule recorded.') . '</p>';
    }
    echo '</div>';

    // Section: Signatures
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4 border-b pb-2">' . __('Signatures') . '</h3>';

    if (!empty($form['signatures'])) {
        echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-6">';

        $signatureTypes = ['Parent1' => __('Parent 1'), 'Parent2' => __('Parent 2'), 'Director' => __('Director')];
        $signatureMap = [];
        foreach ($form['signatures'] as $sig) {
            $signatureMap[$sig['signatureType']] = $sig;
        }

        foreach ($signatureTypes as $type => $label) {
            echo '<div class="border rounded-lg p-4 ' . (isset($signatureMap[$type]) ? 'border-green-300 bg-green-50' : 'border-gray-200 bg-gray-50') . '">';
            echo '<h4 class="font-medium mb-2">' . $label . '</h4>';

            if (isset($signatureMap[$type])) {
                $sig = $signatureMap[$type];
                // Display signature image
                echo '<div class="bg-white border rounded p-2 mb-2">';
                if (strpos($sig['signatureData'], 'data:image') === 0) {
                    echo '<img src="' . $sig['signatureData'] . '" alt="' . $label . ' Signature" class="max-h-20 mx-auto">';
                } else {
                    echo '<p class="text-xs text-gray-400 text-center">' . __('Signature on file') . '</p>';
                }
                echo '</div>';
                echo '<p class="text-sm"><strong>' . htmlspecialchars($sig['signerName']) . '</strong></p>';
                echo '<p class="text-xs text-gray-500">' . Format::dateTime($sig['signedAt']) . '</p>';
            } else {
                echo '<p class="text-gray-400 text-sm">' . __('Not signed') . '</p>';
            }
            echo '</div>';
        }

        echo '</div>';
    } else {
        echo '<p class="text-gray-500">' . __('No signatures recorded.') . '</p>';
    }
    echo '</div>';

    // Section: Form Metadata
    echo '<div class="bg-gray-50 rounded-lg border p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-4">' . __('Form Information') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">';

    echo '<div>';
    echo '<label class="block text-gray-500">' . __('Form Number') . '</label>';
    echo '<p class="font-mono">' . htmlspecialchars($form['formNumber']) . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-gray-500">' . __('School Year') . '</label>';
    echo '<p>' . htmlspecialchars($form['schoolYearName'] ?? '-') . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-gray-500">' . __('Created') . '</label>';
    echo '<p>' . Format::dateTime($form['timestampCreated']) . '</p>';
    if (!empty($form['createdByName'])) {
        echo '<p class="text-xs text-gray-500">' . __('by') . ' ' . Format::name('', $form['createdByName'], $form['createdBySurname'], 'Staff') . '</p>';
    }
    echo '</div>';

    echo '<div>';
    echo '<label class="block text-gray-500">' . __('Last Modified') . '</label>';
    echo '<p>' . Format::dateTime($form['timestampModified']) . '</p>';
    echo '</div>';

    // Status-specific info
    if ($form['status'] === 'Submitted' && !empty($form['submittedAt'])) {
        echo '<div>';
        echo '<label class="block text-gray-500">' . __('Submitted') . '</label>';
        echo '<p>' . Format::dateTime($form['submittedAt']) . '</p>';
        echo '</div>';
    }

    if ($form['status'] === 'Approved' && !empty($form['approvedAt'])) {
        echo '<div>';
        echo '<label class="block text-gray-500">' . __('Approved') . '</label>';
        echo '<p>' . Format::dateTime($form['approvedAt']) . '</p>';
        if (!empty($form['approvedByName'])) {
            echo '<p class="text-xs text-gray-500">' . __('by') . ' ' . Format::name('', $form['approvedByName'], $form['approvedBySurname'], 'Staff') . '</p>';
        }
        echo '</div>';
    }

    if ($form['status'] === 'Rejected' && !empty($form['rejectedAt'])) {
        echo '<div class="md:col-span-2">';
        echo '<label class="block text-gray-500">' . __('Rejected') . '</label>';
        echo '<p>' . Format::dateTime($form['rejectedAt']) . '</p>';
        if (!empty($form['rejectedByName'])) {
            echo '<p class="text-xs text-gray-500">' . __('by') . ' ' . Format::name('', $form['rejectedByName'], $form['rejectedBySurname'], 'Staff') . '</p>';
        }
        if (!empty($form['rejectionReason'])) {
            echo '<p class="mt-2 text-red-600">' . __('Reason') . ': ' . htmlspecialchars($form['rejectionReason']) . '</p>';
        }
        echo '</div>';
    }

    echo '</div>';
    echo '</div>';

    // Back link
    echo '<div class="mt-6">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ChildEnrollment/enrollment_list.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Enrollment Forms') . '</a>';
    echo '</div>';
}
