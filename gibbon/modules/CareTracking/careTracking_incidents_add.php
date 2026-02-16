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
use Gibbon\Forms\CustomFieldHandler;
use Gibbon\Services\Format;
use Gibbon\Module\CareTracking\Domain\IncidentGateway;
use Gibbon\Module\CareTracking\Domain\AttendanceGateway;
use Gibbon\Module\CareTracking\Domain\IncidentNotificationService;
use Gibbon\FileUploader;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Care Tracking'), 'careTracking.php');
$page->breadcrumbs->add(__('Incidents'), 'careTracking_incidents.php');
$page->breadcrumbs->add(__('Add Incident'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/CareTracking/careTracking_incidents_add.php')) {
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
    $incidentGateway = $container->get(IncidentGateway::class);
    $attendanceGateway = $container->get(AttendanceGateway::class);

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
        $childID = $_POST['gibbonPersonID'] ?? null;
        $incidentDate = $_POST['date'] ?? $date;
        $incidentTime = $_POST['time'] ?? date('H:i:s');
        $incidentType = $_POST['type'] ?? '';
        $incidentCategory = $_POST['incidentCategory'] ?? '';
        $severity = $_POST['severity'] ?? 'Low';
        $bodyPart = $_POST['bodyPart'] ?? '';
        $description = $_POST['description'] ?? '';
        $actionTaken = $_POST['actionTaken'] ?? '';
        $medicalConsulted = $_POST['medicalConsulted'] ?? 'N';
        $followUpRequired = $_POST['followUpRequired'] ?? 'N';

        // Validate required fields
        $errors = [];
        if (empty($childID)) {
            $errors[] = __('Please select a child.');
        }
        if (empty($incidentType)) {
            $errors[] = __('Please select an incident type.');
        }
        if (empty($severity)) {
            $errors[] = __('Please select a severity level.');
        }
        if (empty($description)) {
            $errors[] = __('Please provide an incident description.');
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                $page->addError($error);
            }
        } else {
            // Handle file upload if present
            $photoPath = null;
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
                $fileName = 'incident_' . $childID . '_' . date('Y-m-d_His') . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);

                $uploaded = $fileUploader->upload($file, $uploadPath, $fileName);
                if (!empty($uploaded)) {
                    $photoPath = 'uploads/CareTracking/incidents/' . $uploaded;
                }
            }

            // Prepare incident data
            $incidentData = [
                'gibbonPersonID'     => $childID,
                'gibbonSchoolYearID' => $gibbonSchoolYearID,
                'date'               => $incidentDate,
                'time'               => $incidentTime,
                'type'               => $incidentType,
                'severity'           => $severity,
                'incidentCategory'   => !empty($incidentCategory) ? $incidentCategory : null,
                'bodyPart'           => !empty($bodyPart) ? $bodyPart : null,
                'description'        => $description,
                'actionTaken'        => !empty($actionTaken) ? $actionTaken : null,
                'medicalConsulted'   => $medicalConsulted,
                'followUpRequired'   => $followUpRequired,
                'photoPath'          => $photoPath,
                'recordedByID'       => $gibbonPersonID,
            ];

            // Log the incident
            $incidentID = $incidentGateway->logDetailedIncident($incidentData);

            if ($incidentID !== false) {
                // Check if auto-notification is enabled
                $autoNotify = $container->get('config')->getSettingByScope('Care Tracking', 'notifyOnIncident');

                if ($autoNotify === 'Y') {
                    try {
                        $notificationService = $container->get(IncidentNotificationService::class);
                        $notificationService->notifyParent($incidentID);
                    } catch (\Exception $e) {
                        // Log error but don't fail the incident creation
                        error_log('Failed to send incident notification: ' . $e->getMessage());
                    }
                }

                // Check if director escalation is needed
                $escalationSeverities = $container->get('config')->getSettingByScope('Care Tracking', 'incidentEscalationSeverities');
                $escalationSeveritiesArray = array_map('trim', explode(',', $escalationSeverities ?? 'High,Critical'));

                if (in_array($severity, $escalationSeveritiesArray)) {
                    try {
                        $notificationService = $container->get(IncidentNotificationService::class);
                        $notificationService->notifyDirector($incidentID);
                    } catch (\Exception $e) {
                        error_log('Failed to send director notification: ' . $e->getMessage());
                    }
                }

                $page->addSuccess(__('Incident has been logged successfully.'));

                // Redirect to incidents page
                $URL = $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents.php&date=' . $incidentDate;
                header("Location: {$URL}");
                exit;
            } else {
                $page->addError(__('Failed to log incident. Please try again.'));
            }
        }
    }

    // Get children for selection
    // First, get currently checked-in children
    $checkedInChildren = $attendanceGateway->selectChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);
    $checkedInList = [];
    foreach ($checkedInChildren as $child) {
        $checkedInList[$child['gibbonPersonID']] = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true) . ' (' . __('Checked In') . ')';
    }

    // Also get all enrolled children for historical incidents
    $enrolledChildren = $attendanceGateway->selectChildrenNotCheckedIn($gibbonSchoolYearID, $date);
    $notCheckedInList = [];
    foreach ($enrolledChildren as $child) {
        if (!isset($checkedInList[$child['gibbonPersonID']])) {
            $notCheckedInList[$child['gibbonPersonID']] = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
        }
    }

    // Combine lists with checked-in children first
    $childOptions = ['' => __('Please Select')] + $checkedInList + $notCheckedInList;

    // Page header
    echo '<h2>' . __('Add New Incident Report') . '</h2>';

    // Help text
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">';
    echo '<p class="text-sm text-blue-800">';
    echo '<strong>' . __('Instructions') . ':</strong> ' . __('Use this form to document any incident involving a child. For high or critical severity incidents, the director will be automatically notified. Parents will receive a notification based on system settings.');
    echo '</p>';
    echo '</div>';

    // Build form
    $form = Form::create('addIncident', $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents_add.php&date=' . $date);
    $form->setFactory(\Gibbon\Forms\DatabaseFormFactory::create($pdo));
    $form->addHiddenValue('address', $session->get('address'));

    // Section: Child & Basic Information
    $form->addRow()->addHeading(__('Child & Basic Information'));

    $row = $form->addRow();
    $row->addLabel('gibbonPersonID', __('Child'))->description(__('Select the child involved in the incident.'));
    $row->addSelect('gibbonPersonID')
        ->fromArray($childOptions)
        ->required()
        ->placeholder();

    $row = $form->addRow();
    $row->addLabel('date', __('Date'))->description(__('Date when the incident occurred.'));
    $row->addDate('date')
        ->setValue(Format::date($date))
        ->required();

    $row = $form->addRow();
    $row->addLabel('time', __('Time'))->description(__('Approximate time of the incident.'));
    $row->addTime('time')
        ->setValue(date('H:i'))
        ->required();

    // Section: Incident Classification
    $form->addRow()->addHeading(__('Incident Classification'));

    $row = $form->addRow();
    $row->addLabel('type', __('Incident Type'))->description(__('Primary classification of the incident.'));
    $row->addSelect('type')
        ->fromArray($incidentTypes)
        ->required()
        ->placeholder();

    $row = $form->addRow();
    $row->addLabel('incidentCategory', __('Incident Category'))->description(__('More specific categorization of the incident.'));
    $row->addSelect('incidentCategory')
        ->fromArray($incidentCategories)
        ->placeholder();

    $row = $form->addRow();
    $row->addLabel('severity', __('Severity Level'))->description(__('Assess the severity of the incident. High/Critical will notify the director.'));
    $row->addSelect('severity')
        ->fromArray($severityOptions)
        ->selected('Low')
        ->required();

    $row = $form->addRow();
    $row->addLabel('bodyPart', __('Body Part Affected'))->description(__('Select the body part affected, if applicable.'));
    $row->addSelect('bodyPart')
        ->fromArray($bodyPartOptions)
        ->placeholder();

    // Section: Incident Details
    $form->addRow()->addHeading(__('Incident Details'));

    $row = $form->addRow();
    $row->addLabel('description', __('Description'))->description(__('Provide a detailed description of what happened.'));
    $row->addTextArea('description')
        ->setRows(5)
        ->required()
        ->placeholder(__('Describe the incident in detail: what happened, where, how, any witnesses...'));

    $row = $form->addRow();
    $row->addLabel('actionTaken', __('First Aid / Action Taken'))->description(__('Describe any first aid or immediate action taken.'));
    $row->addTextArea('actionTaken')
        ->setRows(3)
        ->placeholder(__('Describe first aid administered, comfort measures, or other actions taken...'));

    // Section: Medical & Follow-up
    $form->addRow()->addHeading(__('Medical & Follow-up'));

    $row = $form->addRow();
    $row->addLabel('medicalConsulted', __('Medical Professional Consulted'))->description(__('Was a medical professional (nurse, doctor) consulted?'));
    $row->addSelect('medicalConsulted')
        ->fromArray($yesNoOptions)
        ->selected('N');

    $row = $form->addRow();
    $row->addLabel('followUpRequired', __('Follow-up Required'))->description(__('Does this incident require follow-up care or observation?'));
    $row->addSelect('followUpRequired')
        ->fromArray($yesNoOptions)
        ->selected('N');

    // Section: Documentation
    $form->addRow()->addHeading(__('Documentation'));

    $row = $form->addRow();
    $row->addLabel('photo', __('Photo Documentation'))->description(__('Upload a photo of the injury/incident if appropriate. Accepted formats: JPEG, PNG, GIF.'));
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
        warningDiv.innerHTML = "<strong>' . __('Note') . ':</strong> ' . __('High and Critical severity incidents will automatically notify the director.') . '";

        var severityRow = severitySelect.closest(".flex-wrap");
        if (severityRow) {
            severityRow.parentNode.insertBefore(warningDiv, severityRow.nextSibling);
        }

        severitySelect.addEventListener("change", function() {
            if (this.value === "High" || this.value === "Critical") {
                warningDiv.style.display = "block";
            } else {
                warningDiv.style.display = "none";
            }
        });
    });
    </script>';

    // Submit buttons
    $row = $form->addRow();
    $row->addFooter();
    $row->addSubmit(__('Submit Incident Report'));

    echo $form->getOutput();

    // Back link
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/CareTracking/careTracking_incidents.php&date=' . $date . '" class="text-blue-600 hover:underline">&larr; ' . __('Back to Incidents') . '</a>';
    echo '</div>';
}
