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
use Gibbon\Module\DevelopmentProfile\Domain\DevelopmentProfileGateway;
use Gibbon\Module\DevelopmentProfile\Domain\ObservationGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Development Profile'), 'developmentProfile.php');
$page->breadcrumbs->add(__('Add Observation'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/DevelopmentProfile/developmentProfile_add.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get profile ID from request if provided
    $gibbonDevelopmentProfileID = $_GET['gibbonDevelopmentProfileID'] ?? null;

    // Get gateways via DI container
    $profileGateway = $container->get(DevelopmentProfileGateway::class);
    $observationGateway = $container->get(ObservationGateway::class);

    // Quebec developmental domains
    $domains = [
        'affective' => __('Affective Development') . ' / Développement affectif',
        'social' => __('Social Development') . ' / Développement social',
        'language' => __('Language & Communication') . ' / Langage et communication',
        'cognitive' => __('Cognitive Development') . ' / Développement cognitif',
        'gross_motor' => __('Gross Motor') . ' / Motricité globale',
        'fine_motor' => __('Fine Motor') . ' / Motricité fine',
    ];

    // Observer types
    $observerTypes = [
        'educator' => __('Educator'),
        'parent' => __('Parent'),
        'specialist' => __('Specialist'),
    ];

    // Handle observation submission
    $action = $_POST['action'] ?? '';

    if ($action === 'addObservation') {
        $profileID = $_POST['gibbonDevelopmentProfileID'] ?? null;
        $domain = $_POST['domain'] ?? '';
        $observedAt = !empty($_POST['observedAt']) ? Format::dateConvert($_POST['observedAt']) : date('Y-m-d H:i:s');
        $observerType = $_POST['observerType'] ?? 'educator';
        $behaviorDescription = $_POST['behaviorDescription'] ?? '';
        $context = !empty($_POST['context']) ? $_POST['context'] : null;
        $isMilestone = isset($_POST['isMilestone']) && $_POST['isMilestone'] === 'Y';
        $isConcern = isset($_POST['isConcern']) && $_POST['isConcern'] === 'Y';

        if (!empty($profileID) && !empty($domain) && !empty($behaviorDescription)) {
            $result = $observationGateway->logObservation(
                $profileID,
                $domain,
                $observedAt,
                $gibbonPersonID,
                $observerType,
                $behaviorDescription,
                $context,
                $isMilestone,
                $isConcern,
                null // attachments
            );

            if ($result !== false) {
                $page->addSuccess(__('Observation has been recorded successfully.'));
                // Clear form data on success
                $gibbonDevelopmentProfileID = null;
            } else {
                $page->addError(__('Failed to record observation.'));
            }
        } else {
            $page->addError(__('Please fill in all required fields.'));
        }
    }

    // Page header
    echo '<h2>' . __('Add Observation') . '</h2>';
    echo '<p class="text-gray-600 mb-4">' . __('Record observable behavior for a child\'s developmental profile.') . '</p>';

    // Get active profiles for selection
    $criteria = $profileGateway->newQueryCriteria()->sortBy(['surname', 'preferredName']);
    $activeProfiles = $profileGateway->queryActiveProfiles($criteria, $gibbonSchoolYearID);

    if ($activeProfiles->count() === 0) {
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">';
        echo '<p class="text-yellow-600">' . __('No active development profiles found. Please create profiles before adding observations.') . '</p>';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_manage.php" class="text-blue-600 hover:underline">' . __('Manage Profiles') . '</a>';
        echo '</div>';
    } else {
        // Build child selection options
        $childOptions = [];
        foreach ($activeProfiles as $profile) {
            $childName = Format::name('', $profile['preferredName'], $profile['surname'], 'Student', false, true);
            $childOptions[$profile['gibbonDevelopmentProfileID']] = $childName;
        }

        // Create observation form
        $form = Form::create('addObservation', $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_add.php');
        $form->setDescription(__('Record an observation about a child\'s developmental behavior. Observations help build a comprehensive picture of each child\'s growth across the 6 Quebec developmental domains.'));
        $form->addHiddenValue('action', 'addObservation');

        // Child selection
        $row = $form->addRow();
        $row->addLabel('gibbonDevelopmentProfileID', __('Child'))->description(__('Select the child for this observation.'));
        $row->addSelect('gibbonDevelopmentProfileID')
            ->fromArray($childOptions)
            ->required()
            ->placeholder(__('Select Child...'))
            ->selected($gibbonDevelopmentProfileID);

        // Domain selection
        $row = $form->addRow();
        $row->addLabel('domain', __('Developmental Domain'))->description(__('Which Quebec developmental domain does this observation relate to?'));
        $row->addSelect('domain')
            ->fromArray($domains)
            ->required()
            ->placeholder(__('Select Domain...'));

        // Observed date/time
        $row = $form->addRow();
        $row->addLabel('observedAt', __('Observed Date'))->description(__('When was this behavior observed?'));
        $row->addDate('observedAt')
            ->setValue(Format::date(date('Y-m-d')))
            ->required();

        // Observer type
        $row = $form->addRow();
        $row->addLabel('observerType', __('Observer Type'))->description(__('Who is recording this observation?'));
        $row->addSelect('observerType')
            ->fromArray($observerTypes)
            ->required()
            ->selected('educator');

        // Behavior description
        $row = $form->addRow();
        $row->addLabel('behaviorDescription', __('Behavior Description'))->description(__('Describe the observable behavior in detail. Be specific about what you saw and heard.'));
        $row->addTextArea('behaviorDescription')
            ->required()
            ->setRows(5)
            ->placeholder(__('Describe what you observed...'));

        // Context
        $row = $form->addRow();
        $row->addLabel('context', __('Context'))->description(__('Describe the setting, activity, or situation when this was observed.'));
        $row->addTextArea('context')
            ->setRows(3)
            ->placeholder(__('Where and when did this occur? What was the child doing?'));

        // Milestone flag
        $row = $form->addRow();
        $row->addLabel('isMilestone', __('Milestone'))->description(__('Is this observation a significant developmental milestone?'));
        $row->addCheckbox('isMilestone')
            ->setValue('Y')
            ->description(__('Mark as milestone'));

        // Concern flag
        $row = $form->addRow();
        $row->addLabel('isConcern', __('Concern'))->description(__('Does this observation raise any developmental concerns?'));
        $row->addCheckbox('isConcern')
            ->setValue('Y')
            ->description(__('Flag as concern'));

        // Submit
        $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Record Observation'));

        echo $form->getOutput();

        // Information about domains
        echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">';
        echo '<h3 class="font-semibold mb-3">' . __('Quebec Developmental Domains Guide') . '</h3>';
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">';

        echo '<div>';
        echo '<h4 class="font-medium text-pink-700">❤️ ' . __('Affective Development') . '</h4>';
        echo '<p class="text-gray-600">' . __('Emotional expression, self-regulation, attachment, self-confidence') . '</p>';
        echo '</div>';

        echo '<div>';
        echo '<h4 class="font-medium text-blue-700">👥 ' . __('Social Development') . '</h4>';
        echo '<p class="text-gray-600">' . __('Peer interactions, turn-taking, empathy, group participation') . '</p>';
        echo '</div>';

        echo '<div>';
        echo '<h4 class="font-medium text-purple-700">💬 ' . __('Language & Communication') . '</h4>';
        echo '<p class="text-gray-600">' . __('Receptive/expressive language, speech clarity, emergent literacy') . '</p>';
        echo '</div>';

        echo '<div>';
        echo '<h4 class="font-medium text-yellow-700">🧠 ' . __('Cognitive Development') . '</h4>';
        echo '<p class="text-gray-600">' . __('Problem-solving, memory, attention, classification, number concepts') . '</p>';
        echo '</div>';

        echo '<div>';
        echo '<h4 class="font-medium text-green-700">🏃 ' . __('Gross Motor') . '</h4>';
        echo '<p class="text-gray-600">' . __('Balance, coordination, body awareness, outdoor skills') . '</p>';
        echo '</div>';

        echo '<div>';
        echo '<h4 class="font-medium text-orange-700">✋ ' . __('Fine Motor') . '</h4>';
        echo '<p class="text-gray-600">' . __('Hand-eye coordination, pencil grip, manipulation, self-care') . '</p>';
        echo '</div>';

        echo '</div>';
        echo '</div>';

        // Quick child grid for selection
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Quick Select Child') . '</h3>';
        echo '<p class="text-sm text-gray-600 mb-3">' . __('Click on a child to pre-select them in the form above.') . '</p>';

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2 mb-4">';
        foreach ($activeProfiles as $profile) {
            $childName = Format::name('', $profile['preferredName'], $profile['surname'], 'Student', false, true);
            $image = !empty($profile['image_240']) ? $profile['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            $selected = ($profile['gibbonDevelopmentProfileID'] == $gibbonDevelopmentProfileID) ? 'ring-2 ring-blue-500' : '';

            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_add.php&gibbonDevelopmentProfileID=' . $profile['gibbonDevelopmentProfileID'] . '" class="bg-white rounded p-2 flex items-center space-x-2 hover:bg-gray-50 border ' . $selected . '">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
            echo '<span class="text-sm truncate">' . htmlspecialchars($childName) . '</span>';
            echo '</a>';
        }
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
