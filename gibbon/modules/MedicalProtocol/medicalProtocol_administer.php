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
use Gibbon\Module\MedicalProtocol\Domain\ProtocolGateway;
use Gibbon\Module\MedicalProtocol\Domain\AuthorizationGateway;
use Gibbon\Module\MedicalProtocol\Domain\AdministrationGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Medical Protocol'), 'medicalProtocol.php');
$page->breadcrumbs->add(__('Administer Protocol'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/MedicalProtocol/medicalProtocol_administer.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get date from request or default to today
    $date = $_GET['date'] ?? date('Y-m-d');

    // Validate date format
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $date = date('Y-m-d');
    }

    // Get gateways via DI container
    $protocolGateway = $container->get(ProtocolGateway::class);
    $authorizationGateway = $container->get(AuthorizationGateway::class);
    $administrationGateway = $container->get(AdministrationGateway::class);

    // Temperature method options
    $temperatureMethodOptions = [
        'Oral'     => __('Oral'),
        'Axillary' => __('Axillary'),
        'Rectal'   => __('Rectal'),
        'Tympanic' => __('Tympanic'),
        'Temporal' => __('Temporal'),
    ];

    // Handle administration logging action
    $action = $_POST['action'] ?? '';
    $selectedChildID = $_POST['gibbonPersonID'] ?? null;
    $selectedProtocolID = $_POST['gibbonMedicalProtocolID'] ?? null;

    if ($action === 'logAdministration' && !empty($selectedChildID) && !empty($selectedProtocolID)) {
        $authorizationID = $_POST['gibbonMedicalProtocolAuthorizationID'] ?? null;
        $doseGiven = $_POST['doseGiven'] ?? '';
        $doseMg = $_POST['doseMg'] ?? null;
        $concentration = $_POST['concentration'] ?? null;
        $weightAtTimeKg = $_POST['weightAtTimeKg'] ?? null;
        $temperatureC = $_POST['temperatureC'] ?? null;
        $temperatureMethod = $_POST['temperatureMethod'] ?? null;
        $reason = $_POST['reason'] ?? '';
        $observations = $_POST['observations'] ?? '';
        $time = $_POST['time'] ?? date('H:i:s');
        $witnessedByID = $_POST['witnessedByID'] ?? null;
        $scheduleFollowUp = $_POST['scheduleFollowUp'] ?? 'N';

        // Validate required fields
        if (!empty($authorizationID) && !empty($doseGiven) && !empty($weightAtTimeKg)) {
            // Get protocol details to check requirements
            $protocol = $protocolGateway->getProtocolByID($selectedProtocolID);

            // Check if temperature is required
            if ($protocol && $protocol['requiresTemperature'] === 'Y' && empty($temperatureC)) {
                $page->addError(__('Temperature is required for this protocol.'));
            } else {
                // Check if weight is expired (Quebec FO-0647: 3-month revalidation)
                $isWeightExpired = $authorizationGateway->isWeightExpired($authorizationID);

                if ($isWeightExpired) {
                    $weightExpiryDate = $authorizationGateway->getWeightExpiryDate($authorizationID);
                    $expiryDateFormatted = $weightExpiryDate ? Format::date($weightExpiryDate) : __('unknown');
                    $page->addError(sprintf(__('Cannot administer medication. Child\'s weight data is expired (expiry date: %s). Weight must be updated and revalidated per Quebec protocol requirements (3-month maximum).'), $expiryDateFormatted));
                } else {
                    // Comprehensive dose validation (includes interval, daily limit, and dose safety)
                    $maxDailyDoses = $protocol['maxDailyDoses'] ?? 5;
                    $validation = $administrationGateway->validateAdministration(
                        $selectedChildID,
                        $selectedProtocolID,
                        $weightAtTimeKg,
                        !empty($doseMg) ? $doseMg : null,
                        $concentration,
                        null, // ageMonths - not currently tracked, optional parameter
                        $maxDailyDoses
                    );

                    // Check if administration can proceed
                    if (!$validation['canAdminister']) {
                        // Display all validation errors
                        foreach ($validation['errors'] as $error) {
                            $page->addError($error);
                        }
                    } else {
                        // Display any warnings (e.g., borderline doses)
                        if (!empty($validation['warnings'])) {
                            foreach ($validation['warnings'] as $warning) {
                                $page->addWarning($warning);
                            }
                        }

                        // Calculate follow-up time (60 minutes after administration)
                        $followUpTime = null;
                        if ($scheduleFollowUp === 'Y' && $protocol['requiresTemperature'] === 'Y') {
                            $adminDateTime = new DateTime($date . ' ' . $time);
                            $adminDateTime->modify('+60 minutes');
                            $followUpTime = $adminDateTime->format('H:i:s');
                        }

                        // Log the administration
                        $result = $administrationGateway->logAdministration([
                            'gibbonMedicalProtocolAuthorizationID' => $authorizationID,
                            'gibbonPersonID' => $selectedChildID,
                            'gibbonSchoolYearID' => $gibbonSchoolYearID,
                            'date' => $date,
                            'time' => $time,
                            'administeredByID' => $gibbonPersonID,
                            'witnessedByID' => !empty($witnessedByID) ? $witnessedByID : null,
                            'doseGiven' => $doseGiven,
                            'doseMg' => !empty($doseMg) ? $doseMg : null,
                            'concentration' => $concentration,
                            'weightAtTimeKg' => $weightAtTimeKg,
                            'temperatureC' => !empty($temperatureC) ? $temperatureC : null,
                            'temperatureMethod' => $temperatureMethod,
                            'reason' => $reason,
                            'observations' => $observations,
                            'followUpTime' => $followUpTime,
                        ]);

                        if ($result !== false) {
                            $page->addSuccess(__('Protocol administration has been logged successfully.'));
                            if ($followUpTime) {
                                $page->addMessage(sprintf(__('Follow-up check scheduled for %s.'), Format::time($followUpTime)));
                            }
                        } else {
                            $page->addError(__('Failed to log administration.'));
                        }
                    }
                }
            }
        } else {
            $page->addError(__('Please fill in all required fields.'));
        }
    }

    // Handle follow-up completion action
    if ($action === 'completeFollowUp') {
        $administrationID = $_POST['gibbonMedicalProtocolAdministrationID'] ?? null;
        $followUpNotes = $_POST['followUpNotes'] ?? '';

        if (!empty($administrationID)) {
            $result = $administrationGateway->markFollowUpCompleted($administrationID, $followUpNotes);
            if ($result) {
                $page->addSuccess(__('Follow-up has been marked as completed.'));
            } else {
                $page->addError(__('Failed to update follow-up status.'));
            }
        }
    }

    // Handle parent notification action
    if ($action === 'notifyParent') {
        $administrationID = $_POST['gibbonMedicalProtocolAdministrationID'] ?? null;
        if (!empty($administrationID)) {
            $result = $administrationGateway->markParentNotified($administrationID);
            if ($result) {
                $page->addSuccess(__('Parent has been marked as notified.'));
            } else {
                $page->addError(__('Failed to update notification status.'));
            }
        }
    }

    // Page header
    echo '<h2>' . __('Administer Protocol') . '</h2>';

    // Date navigation form
    $form = Form::create('dateFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/MedicalProtocol/medicalProtocol_administer.php');

    $row = $form->addRow();
    $row->addLabel('date', __('Date'));
    $row->addDate('date')->setValue(Format::date($date))->required();

    $row = $form->addRow();
    $row->addSubmit(__('Go'));

    echo $form->getOutput();

    // Display formatted date
    echo '<p class="text-lg mb-4">' . __('Administering protocols for') . ': <strong>' . Format::date($date) . '</strong></p>';

    // Get summary statistics
    $summary = $administrationGateway->getAdministrationSummaryByDate($gibbonSchoolYearID, $date);

    // Display summary
    echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Today\'s Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-center">';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($summary['totalAdministrations'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Total') . '</span>';
    echo '</div>';

    echo '<div class="bg-gray-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold">' . ($summary['childrenCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Children') . '</span>';
    echo '</div>';

    echo '<div class="bg-blue-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-blue-600">' . ($summary['acetaminophenCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Acetaminophen') . '</span>';
    echo '</div>';

    echo '<div class="bg-green-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-green-600">' . ($summary['insectRepellentCount'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Insect Repellent') . '</span>';
    echo '</div>';

    echo '<div class="bg-yellow-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-yellow-600">' . ($summary['followUpsPending'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Follow-ups Pending') . '</span>';
    echo '</div>';

    echo '<div class="bg-purple-50 rounded p-2">';
    echo '<span class="block text-2xl font-bold text-purple-600">' . ($summary['parentsNotified'] ?? 0) . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Parents Notified') . '</span>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Section: Pending Follow-ups (urgent alerts)
    $pendingFollowUps = $administrationGateway->selectAdministrationsPendingFollowUp($gibbonSchoolYearID, $date);

    if ($pendingFollowUps->rowCount() > 0) {
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Pending Follow-ups') . '</h3>';
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">';
        echo '<p class="text-sm text-yellow-700 mb-3">' . __('These children require temperature rechecks.') . '</p>';

        echo '<div class="space-y-2">';
        foreach ($pendingFollowUps as $followUp) {
            $childName = Format::name('', $followUp['preferredName'], $followUp['surname'], 'Student', false, true);
            $followUpTime = $followUp['followUpTime'] ?? '';
            $adminTime = $followUp['time'] ?? '';
            $temp = $followUp['temperatureC'] ?? 'N/A';
            $protocol = $followUp['protocolName'] ?? '';
            $formCode = $followUp['formCode'] ?? '';
            $adminID = $followUp['gibbonMedicalProtocolAdministrationID'];

            // Check if follow-up is overdue
            $currentTime = date('H:i:s');
            $isOverdue = !empty($followUpTime) && $followUpTime < $currentTime;
            $statusClass = $isOverdue ? 'bg-red-100 border-red-200' : 'bg-white';
            $statusText = $isOverdue ? __('OVERDUE') : __('Due') . ' ' . Format::time($followUpTime);
            $statusTextClass = $isOverdue ? 'text-red-600 font-bold' : 'text-yellow-600';

            echo '<div class="' . $statusClass . ' rounded p-3 flex items-center justify-between border">';
            echo '<div>';
            echo '<span class="font-medium">' . htmlspecialchars($childName) . '</span>';
            echo ' - <span class="text-sm">' . htmlspecialchars($protocol) . ' (' . htmlspecialchars($formCode) . ')</span>';
            echo '<p class="text-sm text-gray-600 mt-1">';
            echo __('Administered at') . ' ' . Format::time($adminTime);
            echo ' | ' . __('Temp') . ': ' . $temp . '°C';
            echo '</p>';
            echo '<p class="text-sm ' . $statusTextClass . '">' . $statusText . '</p>';
            echo '</div>';
            echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_administer.php&date=' . $date . '" class="ml-4 flex items-center gap-2">';
            echo '<input type="hidden" name="action" value="completeFollowUp">';
            echo '<input type="hidden" name="gibbonMedicalProtocolAdministrationID" value="' . $adminID . '">';
            echo '<input type="text" name="followUpNotes" placeholder="' . __('Notes (optional)') . '" class="w-32 px-2 py-1 border rounded text-sm">';
            echo '<button type="submit" class="bg-green-500 text-white text-xs px-3 py-1 rounded hover:bg-green-600">' . __('Complete') . '</button>';
            echo '</form>';
            echo '</div>';
        }
        echo '</div>';

        echo '</div>';
    }

    // Section: New Administration Form
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('New Administration') . '</h3>';

    // Get active protocols
    $protocols = $protocolGateway->selectActiveProtocols()->fetchAll();

    // Get authorized children for each protocol
    $authorizedChildren = [];
    foreach ($protocols as $protocol) {
        $children = $authorizationGateway->selectAuthorizedChildren($protocol['gibbonMedicalProtocolID'], $gibbonSchoolYearID)->fetchAll();
        foreach ($children as $child) {
            $key = $child['gibbonPersonID'] . '_' . $protocol['gibbonMedicalProtocolID'];
            $authorizedChildren[$key] = [
                'childID' => $child['gibbonPersonID'],
                'childName' => Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true),
                'protocolID' => $protocol['gibbonMedicalProtocolID'],
                'protocolName' => $protocol['name'],
                'formCode' => $protocol['formCode'],
                'authorizationID' => $child['gibbonMedicalProtocolAuthorizationID'],
                'weightKg' => $child['weightKg'],
                'weightExpiryDate' => $child['weightExpiryDate'],
                'image' => $child['image_240'] ?? '',
            ];
        }
    }

    if (!empty($authorizedChildren)) {
        echo '<div class="bg-white border rounded-lg p-4 mb-4">';
        echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol_administer.php&date=' . $date . '" id="administerForm">';
        echo '<input type="hidden" name="action" value="logAdministration">';

        // Step 1: Select Protocol
        echo '<div class="mb-4">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Protocol') . ' <span class="text-red-500">*</span></label>';
        echo '<select name="gibbonMedicalProtocolID" id="protocolSelect" class="w-full border rounded px-3 py-2" required onchange="updateChildList()">';
        echo '<option value="">' . __('Select Protocol') . '</option>';
        foreach ($protocols as $protocol) {
            $selected = (!empty($selectedProtocolID) && $selectedProtocolID == $protocol['gibbonMedicalProtocolID']) ? ' selected' : '';
            $typeLabel = $protocol['type'] === 'Medication' ? '💊' : '🧴';
            echo '<option value="' . $protocol['gibbonMedicalProtocolID'] . '"' . $selected . ' data-type="' . $protocol['type'] . '" data-requires-temp="' . $protocol['requiresTemperature'] . '">';
            echo $typeLabel . ' ' . htmlspecialchars($protocol['name']) . ' (' . htmlspecialchars($protocol['formCode']) . ')';
            echo '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Step 2: Select Child
        echo '<div class="mb-4">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Child') . ' <span class="text-red-500">*</span></label>';
        echo '<select name="gibbonPersonID" id="childSelect" class="w-full border rounded px-3 py-2" required onchange="updateChildDetails()">';
        echo '<option value="">' . __('Select a protocol first') . '</option>';
        echo '</select>';
        echo '<input type="hidden" name="gibbonMedicalProtocolAuthorizationID" id="authorizationID" value="">';
        echo '</div>';

        // Child details display
        echo '<div id="childDetails" class="hidden bg-gray-50 rounded p-3 mb-4">';
        echo '<div class="flex items-center gap-4">';
        echo '<img id="childImage" src="" class="w-16 h-16 rounded-full object-cover" alt="">';
        echo '<div>';
        echo '<div id="childNameDisplay" class="font-medium"></div>';
        echo '<div id="childWeightDisplay" class="text-sm text-gray-600"></div>';
        echo '<div id="weightWarning" class="hidden text-sm text-red-500 font-medium"></div>';
        echo '</div>';
        echo '</div>';
        echo '<div id="dosageRecommendation" class="mt-3 hidden">';
        echo '<p class="text-sm font-medium">' . __('Recommended Dosage') . ':</p>';
        echo '<div id="dosageOptions" class="mt-1"></div>';
        echo '</div>';
        echo '<div id="administrationWarning" class="mt-3 hidden bg-red-50 border border-red-200 rounded p-2">';
        echo '<p class="text-sm text-red-600"></p>';
        echo '</div>';
        echo '</div>';

        // Administration details
        echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';

        // Time
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Time') . ' <span class="text-red-500">*</span></label>';
        echo '<input type="time" name="time" value="' . date('H:i') . '" class="w-full border rounded px-3 py-2" required>';
        echo '</div>';

        // Weight at time of administration
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Weight (kg)') . ' <span class="text-red-500">*</span></label>';
        echo '<input type="number" name="weightAtTimeKg" id="weightAtTimeKg" step="0.1" min="2" max="50" class="w-full border rounded px-3 py-2" required>';
        echo '</div>';

        // Temperature (for medications)
        echo '<div id="temperatureSection">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Temperature (°C)') . ' <span class="text-red-500" id="tempRequired">*</span></label>';
        echo '<input type="number" name="temperatureC" id="temperatureC" step="0.1" min="35" max="42" placeholder="37.0" class="w-full border rounded px-3 py-2">';
        echo '</div>';

        // Temperature method
        echo '<div id="tempMethodSection">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Temperature Method') . '</label>';
        echo '<select name="temperatureMethod" id="temperatureMethod" class="w-full border rounded px-3 py-2">';
        echo '<option value="">' . __('Select Method') . '</option>';
        foreach ($temperatureMethodOptions as $value => $label) {
            echo '<option value="' . htmlspecialchars($value) . '">' . htmlspecialchars($label) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Concentration (for medications)
        echo '<div id="concentrationSection">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Concentration') . '</label>';
        echo '<select name="concentration" id="concentrationSelect" class="w-full border rounded px-3 py-2" onchange="updateDosage()">';
        echo '<option value="">' . __('Select Concentration') . '</option>';
        echo '<option value="80mg/mL">80mg/mL (' . __('Drops') . ')</option>';
        echo '<option value="80mg/5mL">80mg/5mL (' . __('Syrup') . ')</option>';
        echo '<option value="160mg/5mL">160mg/5mL (' . __('Concentrated') . ')</option>';
        echo '</select>';
        echo '</div>';

        // Dose given
        echo '<div>';
        echo '<label class="block text-sm font-medium mb-1">' . __('Dose Given') . ' <span class="text-red-500">*</span></label>';
        echo '<input type="text" name="doseGiven" id="doseGiven" placeholder="e.g., 5 mL" class="w-full border rounded px-3 py-2" required>';
        echo '</div>';

        // Dose in mg
        echo '<div id="doseMgSection">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Dose (mg)') . '</label>';
        echo '<input type="number" name="doseMg" id="doseMg" step="0.1" min="0" class="w-full border rounded px-3 py-2">';
        echo '</div>';

        echo '</div>';

        // Reason
        echo '<div class="mb-4">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Reason') . '</label>';
        echo '<textarea name="reason" rows="2" class="w-full border rounded px-3 py-2" placeholder="' . __('e.g., Fever, outdoor activity...') . '"></textarea>';
        echo '</div>';

        // Observations
        echo '<div class="mb-4">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Observations') . '</label>';
        echo '<textarea name="observations" rows="2" class="w-full border rounded px-3 py-2" placeholder="' . __('Post-administration observations...') . '"></textarea>';
        echo '</div>';

        // Schedule follow-up
        echo '<div class="mb-4" id="followUpSection">';
        echo '<label class="flex items-center gap-2">';
        echo '<input type="checkbox" name="scheduleFollowUp" value="Y" checked>';
        echo '<span class="text-sm font-medium">' . __('Schedule 60-minute follow-up check') . '</span>';
        echo '</label>';
        echo '</div>';

        // Witness (optional)
        echo '<div class="mb-4">';
        echo '<label class="block text-sm font-medium mb-1">' . __('Witnessed By') . ' (' . __('Optional') . ')</label>';
        echo '<select name="witnessedByID" class="w-full border rounded px-3 py-2">';
        echo '<option value="">' . __('No witness') . '</option>';
        // This would typically be populated with staff members
        echo '</select>';
        echo '</div>';

        // Submit button
        echo '<button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Log Administration') . '</button>';

        echo '</form>';
        echo '</div>';

        // JavaScript for dynamic form updates
        echo '<script>
        var authorizedChildren = ' . json_encode($authorizedChildren) . ';
        var protocols = ' . json_encode(array_combine(array_column($protocols, "gibbonMedicalProtocolID"), $protocols)) . ';

        function updateChildList() {
            var protocolID = document.getElementById("protocolSelect").value;
            var childSelect = document.getElementById("childSelect");
            var protocol = protocols[protocolID] || null;

            childSelect.innerHTML = "<option value=\"\">' . __('Select Child') . '</option>";

            if (protocolID) {
                Object.keys(authorizedChildren).forEach(function(key) {
                    var child = authorizedChildren[key];
                    if (child.protocolID == protocolID) {
                        var option = document.createElement("option");
                        option.value = child.childID;
                        option.setAttribute("data-auth-id", child.authorizationID);
                        option.setAttribute("data-weight", child.weightKg);
                        option.setAttribute("data-weight-expiry", child.weightExpiryDate);
                        option.setAttribute("data-image", child.image);
                        option.textContent = child.childName + " (" + child.weightKg + " kg)";
                        childSelect.appendChild(option);
                    }
                });
            }

            // Update form sections based on protocol type
            updateFormSections(protocol);
            document.getElementById("childDetails").classList.add("hidden");
        }

        function updateFormSections(protocol) {
            var isMedication = protocol && protocol.type === "Medication";
            var requiresTemp = protocol && protocol.requiresTemperature === "Y";

            document.getElementById("temperatureSection").style.display = requiresTemp ? "block" : "none";
            document.getElementById("tempMethodSection").style.display = requiresTemp ? "block" : "none";
            document.getElementById("concentrationSection").style.display = isMedication ? "block" : "none";
            document.getElementById("doseMgSection").style.display = isMedication ? "block" : "none";
            document.getElementById("followUpSection").style.display = requiresTemp ? "block" : "none";

            // Update required attribute for temperature
            var tempInput = document.getElementById("temperatureC");
            if (requiresTemp) {
                tempInput.setAttribute("required", "required");
                document.getElementById("tempRequired").style.display = "inline";
            } else {
                tempInput.removeAttribute("required");
                document.getElementById("tempRequired").style.display = "none";
            }
        }

        function updateChildDetails() {
            var childSelect = document.getElementById("childSelect");
            var selectedOption = childSelect.options[childSelect.selectedIndex];
            var childDetails = document.getElementById("childDetails");

            if (childSelect.value) {
                var authID = selectedOption.getAttribute("data-auth-id");
                var weight = selectedOption.getAttribute("data-weight");
                var weightExpiry = selectedOption.getAttribute("data-weight-expiry");
                var image = selectedOption.getAttribute("data-image") || "themes/Default/img/anonymous_240.jpg";

                document.getElementById("authorizationID").value = authID;
                document.getElementById("weightAtTimeKg").value = weight;
                document.getElementById("childNameDisplay").textContent = selectedOption.textContent.split(" (")[0];
                document.getElementById("childWeightDisplay").textContent = "' . __('Weight') . ': " + weight + " kg | ' . __('Weight Expiry') . ': " + weightExpiry;
                document.getElementById("childImage").src = "' . $session->get('absoluteURL') . '/" + image;

                // Check if weight is expired
                var today = new Date().toISOString().split("T")[0];
                var weightWarning = document.getElementById("weightWarning");
                if (weightExpiry < today) {
                    weightWarning.textContent = "' . __('Warning: Weight has expired. Please update weight before administration.') . '";
                    weightWarning.classList.remove("hidden");
                } else {
                    weightWarning.classList.add("hidden");
                }

                childDetails.classList.remove("hidden");
                updateDosage();
            } else {
                childDetails.classList.add("hidden");
            }
        }

        function updateDosage() {
            var protocolID = document.getElementById("protocolSelect").value;
            var weight = parseFloat(document.getElementById("weightAtTimeKg").value) || 0;
            var concentration = document.getElementById("concentrationSelect").value;
            var protocol = protocols[protocolID] || null;
            var dosageRecommendation = document.getElementById("dosageRecommendation");
            var dosageOptions = document.getElementById("dosageOptions");

            if (protocol && protocol.type === "Medication" && weight > 0) {
                // Calculate dose based on 10-15 mg/kg guideline
                var minDose = weight * 10;
                var maxDose = weight * 15;
                var recDose = weight * 12.5;

                dosageOptions.innerHTML = "<p class=\"text-sm text-gray-600\">' . __('Based on weight') . ' (" + weight + " kg):</p>";
                dosageOptions.innerHTML += "<p class=\"text-sm\"><span class=\"font-medium\">' . __('Recommended range') . ':</span> " + minDose.toFixed(1) + " - " + maxDose.toFixed(1) + " mg</p>";

                if (concentration) {
                    // Convert dose to mL based on concentration
                    var mlDose = 0;
                    switch(concentration) {
                        case "80mg/mL":
                            mlDose = recDose / 80;
                            break;
                        case "80mg/5mL":
                            mlDose = (recDose / 80) * 5;
                            break;
                        case "160mg/5mL":
                            mlDose = (recDose / 160) * 5;
                            break;
                    }
                    dosageOptions.innerHTML += "<p class=\"text-sm mt-1\"><span class=\"font-medium\">' . __('Suggested dose') . ' (" + concentration + "):</span> " + mlDose.toFixed(2) + " mL (~" + recDose.toFixed(1) + " mg)</p>";

                    // Pre-fill dose fields
                    document.getElementById("doseGiven").value = mlDose.toFixed(2) + " mL";
                    document.getElementById("doseMg").value = recDose.toFixed(1);
                }

                dosageRecommendation.classList.remove("hidden");
            } else {
                dosageRecommendation.classList.add("hidden");
            }
        }

        // Initialize form on page load
        document.addEventListener("DOMContentLoaded", function() {
            var protocolSelect = document.getElementById("protocolSelect");
            if (protocolSelect.value) {
                updateChildList();
            } else {
                updateFormSections(null);
            }
        });
        </script>';

    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500 mb-4">';
        echo __('No children with active authorizations found. Please ensure parent authorizations are completed before administering protocols.');
        echo '</div>';
    }

    // Section: Today's Administration Records
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Today\'s Administration Records') . '</h3>';

    // Build query criteria
    $criteria = $administrationGateway->newQueryCriteria()
        ->sortBy(['time'], 'DESC')
        ->fromPOST();

    // Get administration data for the date
    $administrations = $administrationGateway->queryAdministrationsByDate($criteria, $gibbonSchoolYearID, $date);

    // Build DataTable
    $table = DataTable::createPaginated('administrations', $criteria);
    $table->setTitle(__('Administration Records'));

    // Add columns
    $table->addColumn('image', __('Photo'))
        ->notSortable()
        ->format(function ($row) use ($session) {
            $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
        });

    $table->addColumn('name', __('Child'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
        });

    $table->addColumn('time', __('Time'))
        ->sortable()
        ->format(function ($row) {
            return Format::time($row['time']);
        });

    $table->addColumn('protocolName', __('Protocol'))
        ->sortable()
        ->format(function ($row) {
            $formCode = $row['formCode'] ?? '';
            return htmlspecialchars($row['protocolName']) . '<br><span class="text-xs text-gray-500">' . htmlspecialchars($formCode) . '</span>';
        });

    $table->addColumn('doseGiven', __('Dose'))
        ->format(function ($row) {
            $dose = htmlspecialchars($row['doseGiven']);
            if (!empty($row['doseMg'])) {
                $dose .= '<br><span class="text-xs text-gray-500">' . $row['doseMg'] . ' mg</span>';
            }
            return $dose;
        });

    $table->addColumn('temperatureC', __('Temp'))
        ->format(function ($row) {
            if (!empty($row['temperatureC'])) {
                return $row['temperatureC'] . '°C<br><span class="text-xs text-gray-500">' . ($row['temperatureMethod'] ?? '') . '</span>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    $table->addColumn('followUpTime', __('Follow-up'))
        ->notSortable()
        ->format(function ($row) {
            if (!empty($row['followUpTime'])) {
                if ($row['followUpCompleted'] === 'Y') {
                    return '<span class="text-green-600" title="' . __('Completed') . '">✓ ' . Format::time($row['followUpTime']) . '</span>';
                }
                // Check if overdue
                $currentTime = date('H:i:s');
                $isOverdue = $row['followUpTime'] < $currentTime;
                $class = $isOverdue ? 'text-red-600 font-bold' : 'text-yellow-600';
                return '<span class="' . $class . '">' . Format::time($row['followUpTime']) . '</span>';
            }
            return '<span class="text-gray-400">-</span>';
        });

    $table->addColumn('administeredByName', __('Staff'))
        ->notSortable()
        ->format(function ($row) {
            if (!empty($row['administeredByName'])) {
                return Format::name('', $row['administeredByName'], $row['administeredBySurname'], 'Staff', false, false);
            }
            return '<span class="text-gray-400">-</span>';
        });

    $table->addColumn('parentNotified', __('Notified'))
        ->notSortable()
        ->format(function ($row) {
            if ($row['parentNotified'] === 'Y') {
                return '<span class="text-green-600" title="' . __('Parent Notified') . '">✓</span>';
            }
            return '<span class="text-red-600" title="' . __('Not Notified') . '">✗</span>';
        });

    // Output table
    if ($administrations->count() > 0) {
        echo $table->render($administrations);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No administration records found for this date.');
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/MedicalProtocol/medicalProtocol.php&date=' . $date . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
