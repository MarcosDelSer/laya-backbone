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
use Gibbon\Services\Format;
use Gibbon\Module\CareTracking\Domain\IncidentGateway;
use Gibbon\FileUploader;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Incidents'), 'careTracking_incidents.php');
$page->breadcrumbs->add(__('Edit Incident'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/careTracking_incidents_edit.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get incident ID from request
    $gibbonCareIncidentID = $_GET['gibbonCareIncidentID'] ?? null;

    if (empty($gibbonCareIncidentID)) {
        $page->addError(__('No incident specified.'));
    } else {
        // Get gateways via DI container
        $incidentGateway = $container->get(IncidentGateway::class);

        // Get the incident details
        $incident = $incidentGateway->getByID($gibbonCareIncidentID);

        if (empty($incident)) {
            $page->addError(__('The specified incident cannot be found.'));
        } else {
            $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
            $gibbonPersonID = $session->get('gibbonPersonID');

            // Get child details
            $childData = ['gibbonPersonID' => $incident['gibbonPersonID']];
            $childSql = "SELECT gibbonPersonID, preferredName, surname, image_240
                         FROM gibbonPerson
                         WHERE gibbonPersonID = :gibbonPersonID";
            $child = $pdo->selectOne($childSql, $childData);
            $childName = Format::name('', $child['preferredName'] ?? '', $child['surname'] ?? '', 'Student', false, true);

            // Incident type options
            $incidentTypes = [
                ''             => __('Please Select'),
                'Minor Injury' => __('Minor Injury'),
                'Major Injury' => __('Major Injury'),
                'Illness'      => __('Illness'),
                'Behavioral'   => __('Behavioral'),
                'Other'        => __('Other'),
            ];

            // Incident category options
            $incidentCategories = [
                ''                  => __('Please Select'),
                'Fall'              => __('Fall'),
                'Collision'         => __('Collision'),
                'Bite'              => __('Bite'),
                'Scratch'           => __('Scratch'),
                'Pinch'             => __('Pinch'),
                'Equipment'         => __('Equipment Related'),
                'Outdoor'           => __('Outdoor Incident'),
                'Illness'           => __('Illness'),
                'Allergic Reaction' => __('Allergic Reaction'),
                'Behavioral'        => __('Behavioral Incident'),
                'Other'             => __('Other'),
            ];

            // Body part options
            $bodyPartOptions = [
                ''          => __('Please Select'),
                'Head'      => __('Head'),
                'Face'      => __('Face'),
                'Eye'       => __('Eye'),
                'Ear'       => __('Ear'),
                'Nose'      => __('Nose'),
                'Mouth'     => __('Mouth'),
                'Neck'      => __('Neck'),
                'Chest'     => __('Chest'),
                'Back'      => __('Back'),
                'Stomach'   => __('Stomach'),
                'Left Arm'  => __('Left Arm'),
                'Right Arm' => __('Right Arm'),
                'Left Hand' => __('Left Hand'),
                'Right Hand'=> __('Right Hand'),
                'Left Leg'  => __('Left Leg'),
                'Right Leg' => __('Right Leg'),
                'Left Foot' => __('Left Foot'),
                'Right Foot'=> __('Right Foot'),
                'Multiple'  => __('Multiple Areas'),
                'Other'     => __('Other'),
            ];

            // Severity options
            $severityOptions = [
                'Low'      => __('Low - Minor, no lasting effects expected'),
                'Medium'   => __('Medium - Requires attention but not urgent'),
                'High'     => __('High - Significant, needs prompt parent notification'),
                'Critical' => __('Critical - Serious, requires immediate action'),
            ];

            // Yes/No options
            $yesNoOptions = [
                'N' => __('No'),
                'Y' => __('Yes'),
            ];

            // Handle form submission
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $incidentDate = $_POST['date'] ?? $incident['date'];
                $incidentTime = $_POST['time'] ?? $incident['time'];
                $incidentType = $_POST['type'] ?? '';
                $incidentCategory = $_POST['incidentCategory'] ?? '';
                $severity = $_POST['severity'] ?? 'Low';
                $bodyPart = $_POST['bodyPart'] ?? '';
                $description = $_POST['description'] ?? '';
                $actionTaken = $_POST['actionTaken'] ?? '';
                $medicalConsulted = $_POST['medicalConsulted'] ?? 'N';
                $followUpRequired = $_POST['followUpRequired'] ?? 'N';
                $editReason = $_POST['editReason'] ?? '';

                // Validate required fields
                $errors = [];
                if (empty($incidentType)) {
                    $errors[] = __('Please select an incident type.');
                }
                if (empty($severity)) {
                    $errors[] = __('Please select a severity level.');
                }
                if (empty($description)) {
                    $errors[] = __('Please provide an incident description.');
                }
                if (empty($editReason)) {
                    $errors[] = __('Please provide a reason for editing this incident.');
                }

                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $page->addError($error);
                    }
                } else {
                    // Handle file upload if present
                    $photoPath = $incident['photoPath']; // Keep existing photo by default
                    if (!empty($_FILES['photo']['name'])) {
                        $fileUploader = $container->get(FileUploader::class);
                        $fileUploader->setFileSuffixType(FileUploader::FILE_SUFFIX_INCREMENTAL);

                        // Allow only image files
                        $fileUploader->setFileTypes(['image/jpeg', 'image/png', 'image/gif']);

                        $uploadPath = $session->get('absolutePath') . '/uploads/CareTracking/incidents';

                        // Create directory if it doesn't exist
                        if (!is_dir($uploadPath)) {
                            mkdir($uploadPath, 0755, true);
                        }

                        $file = $_FILES['photo'];
                        $fileName = 'incident_' . $incident['gibbonPersonID'] . '_' . date('Y-m-d_His') . '_edit.' . pathinfo($file['name'], PATHINFO_EXTENSION);

                        $uploaded = $fileUploader->upload($file, $uploadPath, $fileName);
                        if (!empty($uploaded)) {
                            $photoPath = 'uploads/CareTracking/incidents/' . $uploaded;
                        }
                    }

                    // Check for photo removal
                    if (isset($_POST['removePhoto']) && $_POST['removePhoto'] === 'Y') {
                        $photoPath = null;
                    }

                    // Build audit trail entry
                    $auditEntry = [
                        'editedBy' => $gibbonPersonID,
                        'editedAt' => date('Y-m-d H:i:s'),
                        'reason' => $editReason,
                        'changes' => [],
                    ];

                    // Track changes for audit trail
                    $fieldsToTrack = [
                        'date' => $incidentDate,
                        'time' => $incidentTime,
                        'type' => $incidentType,
                        'incidentCategory' => $incidentCategory,
                        'severity' => $severity,
                        'bodyPart' => $bodyPart,
                        'description' => $description,
                        'actionTaken' => $actionTaken,
                        'medicalConsulted' => $medicalConsulted,
                        'followUpRequired' => $followUpRequired,
                        'photoPath' => $photoPath,
                    ];

                    foreach ($fieldsToTrack as $field => $newValue) {
                        $oldValue = $incident[$field] ?? '';
                        if ($oldValue !== $newValue) {
                            $auditEntry['changes'][$field] = [
                                'old' => $oldValue,
                                'new' => $newValue,
                            ];
                        }
                    }

                    // Parse existing audit log or create new one
                    $existingAuditLog = [];
                    if (!empty($incident['auditLog'])) {
                        $existingAuditLog = json_decode($incident['auditLog'], true) ?: [];
                    }
                    $existingAuditLog[] = $auditEntry;

                    // Prepare update data
                    $updateData = [
                        'date' => $incidentDate,
                        'time' => $incidentTime,
                        'type' => $incidentType,
                        'incidentCategory' => !empty($incidentCategory) ? $incidentCategory : null,
                        'severity' => $severity,
                        'bodyPart' => !empty($bodyPart) ? $bodyPart : null,
                        'description' => $description,
                        'actionTaken' => !empty($actionTaken) ? $actionTaken : null,
                        'medicalConsulted' => $medicalConsulted,
                        'followUpRequired' => $followUpRequired,
                        'photoPath' => $photoPath,
                        'auditLog' => json_encode($existingAuditLog),
                        'timestampModified' => date('Y-m-d H:i:s'),
                        'modifiedByID' => $gibbonPersonID,
                    ];

                    // Update the incident
                    $result = $incidentGateway->update($gibbonCareIncidentID, $updateData);

                    if ($result !== false) {
                        $page->addSuccess(__('Incident has been updated successfully.'));

                        // Redirect to view page
                        $URL = $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents_view.php&gibbonCareIncidentID=' . $gibbonCareIncidentID;
                        header("Location: {$URL}");
                        exit;
                    } else {
                        $page->addError(__('Failed to update incident. Please try again.'));
                    }
                }
            }

            // Page header
            echo '<h2>' . __('Edit Incident Report') . ': ' . htmlspecialchars($childName) . '</h2>';

            // Warning about editing
            echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">';
            echo '<p class="text-sm text-yellow-800">';
            echo '<strong>' . __('Important') . ':</strong> ' . __('All changes to incident reports are logged for audit purposes. Please provide a reason for your modifications. Original incident details are preserved in the audit trail.');
            echo '</p>';
            echo '</div>';

            // Show child info card
            echo '<div class="bg-white rounded-lg shadow p-4 mb-4">';
            echo '<div class="flex items-center">';
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-12 h-12 rounded-full object-cover mr-4" alt="">';
            echo '<div>';
            echo '<p class="font-semibold">' . htmlspecialchars($childName) . '</p>';
            echo '<p class="text-sm text-gray-600">' . __('Original Date') . ': ' . Format::date($incident['date']) . ' ' . Format::time($incident['time']) . '</p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            // Build form
            $form = Form::create('editIncident', $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents_edit.php&gibbonCareIncidentID=' . $gibbonCareIncidentID);
            $form->setFactory(\Gibbon\Forms\DatabaseFormFactory::create($pdo));
            $form->addHiddenValue('address', $session->get('address'));

            // Section: Edit Reason (required for audit trail)
            $form->addRow()->addHeading(__('Edit Reason'));

            $row = $form->addRow();
            $row->addLabel('editReason', __('Reason for Edit'))->description(__('Please explain why this incident report is being modified.'));
            $row->addTextArea('editReason')
                ->setRows(2)
                ->required()
                ->placeholder(__('e.g., Correcting typo, adding missing details, updating with new information...'));

            // Section: Date & Time
            $form->addRow()->addHeading(__('Date & Time'));

            $row = $form->addRow();
            $row->addLabel('date', __('Date'))->description(__('Date when the incident occurred.'));
            $row->addDate('date')
                ->setValue(Format::date($incident['date']))
                ->required();

            $row = $form->addRow();
            $row->addLabel('time', __('Time'))->description(__('Approximate time of the incident.'));
            $row->addTime('time')
                ->setValue(substr($incident['time'], 0, 5))
                ->required();

            // Section: Incident Classification
            $form->addRow()->addHeading(__('Incident Classification'));

            $row = $form->addRow();
            $row->addLabel('type', __('Incident Type'))->description(__('Primary classification of the incident.'));
            $row->addSelect('type')
                ->fromArray($incidentTypes)
                ->selected($incident['type'])
                ->required()
                ->placeholder();

            $row = $form->addRow();
            $row->addLabel('incidentCategory', __('Incident Category'))->description(__('More specific categorization of the incident.'));
            $row->addSelect('incidentCategory')
                ->fromArray($incidentCategories)
                ->selected($incident['incidentCategory'] ?? '')
                ->placeholder();

            $row = $form->addRow();
            $row->addLabel('severity', __('Severity Level'))->description(__('Assess the severity of the incident. High/Critical will notify the director.'));
            $row->addSelect('severity')
                ->fromArray($severityOptions)
                ->selected($incident['severity'])
                ->required();

            $row = $form->addRow();
            $row->addLabel('bodyPart', __('Body Part Affected'))->description(__('Select the body part affected, if applicable.'));
            $row->addSelect('bodyPart')
                ->fromArray($bodyPartOptions)
                ->selected($incident['bodyPart'] ?? '')
                ->placeholder();

            // Section: Incident Details
            $form->addRow()->addHeading(__('Incident Details'));

            $row = $form->addRow();
            $row->addLabel('description', __('Description'))->description(__('Provide a detailed description of what happened.'));
            $row->addTextArea('description')
                ->setRows(5)
                ->required()
                ->setValue($incident['description'])
                ->placeholder(__('Describe the incident in detail: what happened, where, how, any witnesses...'));

            $row = $form->addRow();
            $row->addLabel('actionTaken', __('First Aid / Action Taken'))->description(__('Describe any first aid or immediate action taken.'));
            $row->addTextArea('actionTaken')
                ->setRows(3)
                ->setValue($incident['actionTaken'] ?? '')
                ->placeholder(__('Describe first aid administered, comfort measures, or other actions taken...'));

            // Section: Medical & Follow-up
            $form->addRow()->addHeading(__('Medical & Follow-up'));

            $row = $form->addRow();
            $row->addLabel('medicalConsulted', __('Medical Professional Consulted'))->description(__('Was a medical professional (nurse, doctor) consulted?'));
            $row->addSelect('medicalConsulted')
                ->fromArray($yesNoOptions)
                ->selected($incident['medicalConsulted'] ?? 'N');

            $row = $form->addRow();
            $row->addLabel('followUpRequired', __('Follow-up Required'))->description(__('Does this incident require follow-up care or observation?'));
            $row->addSelect('followUpRequired')
                ->fromArray($yesNoOptions)
                ->selected($incident['followUpRequired'] ?? 'N');

            // Section: Documentation
            $form->addRow()->addHeading(__('Documentation'));

            // Show existing photo if present
            if (!empty($incident['photoPath'])) {
                echo '<div class="mb-4 p-4 bg-gray-50 rounded-lg">';
                echo '<p class="font-medium mb-2">' . __('Current Photo') . ':</p>';
                echo '<img src="' . $session->get('absoluteURL') . '/' . htmlspecialchars($incident['photoPath']) . '" class="max-w-xs h-auto rounded shadow mb-2" alt="' . __('Incident Photo') . '">';
                echo '<div class="mt-2">';
                echo '<label class="inline-flex items-center">';
                echo '<input type="checkbox" name="removePhoto" value="Y" class="form-checkbox h-4 w-4 text-red-600">';
                echo '<span class="ml-2 text-sm text-red-600">' . __('Remove this photo') . '</span>';
                echo '</label>';
                echo '</div>';
                echo '</div>';
            }

            $row = $form->addRow();
            $row->addLabel('photo', __('Upload New Photo'))->description(__('Upload a new photo to replace the existing one (optional). Accepted formats: JPEG, PNG, GIF.'));
            $row->addFileUpload('photo')
                ->accepts('.jpg,.jpeg,.png,.gif');

            // Severity warning message
            echo '<script>
            document.addEventListener("DOMContentLoaded", function() {
                var severitySelect = document.querySelector("select[name=severity]");
                var warningDiv = document.createElement("div");
                warningDiv.id = "severity-warning";
                warningDiv.className = "bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mt-2";
                warningDiv.style.display = "none";
                warningDiv.innerHTML = "<strong>' . __('Note') . ':</strong> ' . __('Changing severity to High or Critical may trigger additional director notifications.') . '";

                var severityRow = severitySelect.closest(".flex-wrap");
                if (severityRow) {
                    severityRow.parentNode.insertBefore(warningDiv, severityRow.nextSibling);
                }

                function checkSeverity() {
                    var originalSeverity = "' . htmlspecialchars($incident['severity']) . '";
                    var newSeverity = severitySelect.value;
                    if ((newSeverity === "High" || newSeverity === "Critical") && originalSeverity !== newSeverity) {
                        warningDiv.style.display = "block";
                    } else {
                        warningDiv.style.display = "none";
                    }
                }

                severitySelect.addEventListener("change", checkSeverity);
                checkSeverity(); // Check on initial load
            });
            </script>';

            // Submit buttons
            $row = $form->addRow();
            $row->addFooter();
            $row->addSubmit(__('Save Changes'));

            echo $form->getOutput();

            // Display audit history if exists
            if (!empty($incident['auditLog'])) {
                $auditLog = json_decode($incident['auditLog'], true);
                if (!empty($auditLog) && is_array($auditLog)) {
                    echo '<div class="bg-white rounded-lg shadow p-4 mt-6">';
                    echo '<h3 class="text-lg font-semibold mb-3 border-b pb-2">' . __('Edit History') . '</h3>';

                    echo '<div class="space-y-4">';
                    foreach (array_reverse($auditLog) as $entry) {
                        // Get editor details
                        $editorName = __('Unknown');
                        if (!empty($entry['editedBy'])) {
                            $editorData = ['gibbonPersonID' => $entry['editedBy']];
                            $editorSql = "SELECT preferredName, surname FROM gibbonPerson WHERE gibbonPersonID = :gibbonPersonID";
                            $editor = $pdo->selectOne($editorSql, $editorData);
                            if ($editor) {
                                $editorName = Format::name('', $editor['preferredName'], $editor['surname'], 'Staff', false, true);
                            }
                        }

                        echo '<div class="bg-gray-50 rounded p-3 border-l-4 border-blue-400">';
                        echo '<div class="flex justify-between items-start mb-2">';
                        echo '<div>';
                        echo '<p class="font-medium">' . htmlspecialchars($editorName) . '</p>';
                        echo '<p class="text-xs text-gray-500">' . Format::dateTime($entry['editedAt']) . '</p>';
                        echo '</div>';
                        echo '</div>';

                        echo '<p class="text-sm text-gray-700 mb-2"><strong>' . __('Reason') . ':</strong> ' . htmlspecialchars($entry['reason']) . '</p>';

                        if (!empty($entry['changes'])) {
                            echo '<div class="text-sm">';
                            echo '<p class="font-medium text-gray-600">' . __('Changes') . ':</p>';
                            echo '<ul class="list-disc list-inside ml-2">';
                            foreach ($entry['changes'] as $field => $change) {
                                $fieldLabel = ucfirst(str_replace(['_', 'ID'], [' ', ''], $field));
                                $oldVal = $change['old'] ?: '(' . __('empty') . ')';
                                $newVal = $change['new'] ?: '(' . __('empty') . ')';

                                // Truncate long values
                                if (strlen($oldVal) > 50) {
                                    $oldVal = substr($oldVal, 0, 50) . '...';
                                }
                                if (strlen($newVal) > 50) {
                                    $newVal = substr($newVal, 0, 50) . '...';
                                }

                                echo '<li><span class="text-gray-600">' . htmlspecialchars($fieldLabel) . ':</span> ';
                                echo '<span class="text-red-600 line-through">' . htmlspecialchars($oldVal) . '</span>';
                                echo ' &rarr; <span class="text-green-600">' . htmlspecialchars($newVal) . '</span></li>';
                            }
                            echo '</ul>';
                            echo '</div>';
                        }

                        echo '</div>';
                    }
                    echo '</div>';

                    echo '</div>';
                }
            }

            // Show original creation info
            echo '<div class="bg-gray-50 rounded-lg p-4 mt-4">';
            echo '<h4 class="font-medium mb-2">' . __('Original Record Information') . '</h4>';
            echo '<p class="text-sm text-gray-600">';
            echo __('Created') . ': ' . Format::dateTime($incident['timestampCreated']);

            if (!empty($incident['recordedByID'])) {
                $recordedByData = ['gibbonPersonID' => $incident['recordedByID']];
                $recordedBySql = "SELECT preferredName, surname FROM gibbonPerson WHERE gibbonPersonID = :gibbonPersonID";
                $recordedBy = $pdo->selectOne($recordedBySql, $recordedByData);
                if ($recordedBy) {
                    echo ' ' . __('by') . ' ' . Format::name('', $recordedBy['preferredName'], $recordedBy['surname'], 'Staff', false, true);
                }
            }
            echo '</p>';

            if (!empty($incident['timestampModified'])) {
                echo '<p class="text-sm text-gray-600">';
                echo __('Last Modified') . ': ' . Format::dateTime($incident['timestampModified']);
                if (!empty($incident['modifiedByID'])) {
                    $modifiedByData = ['gibbonPersonID' => $incident['modifiedByID']];
                    $modifiedBySql = "SELECT preferredName, surname FROM gibbonPerson WHERE gibbonPersonID = :gibbonPersonID";
                    $modifiedBy = $pdo->selectOne($modifiedBySql, $modifiedByData);
                    if ($modifiedBy) {
                        echo ' ' . __('by') . ' ' . Format::name('', $modifiedBy['preferredName'], $modifiedBy['surname'], 'Staff', false, true);
                    }
                }
                echo '</p>';
            }
            echo '</div>';

            // Navigation links
            echo '<div class="mt-4 flex gap-4">';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents_view.php&gibbonCareIncidentID=' . $gibbonCareIncidentID . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to View Incident') . '</a>';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents.php&date=' . $incident['date'] . '" class="text-blue-600 hover:underline">' . __('Back to Incidents List') . '</a>';
            echo '</div>';
        }
    }
}
