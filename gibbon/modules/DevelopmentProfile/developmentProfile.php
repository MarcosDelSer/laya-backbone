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
use Gibbon\Tables\DataTable;
use Gibbon\Module\DevelopmentProfile\Domain\DevelopmentProfileGateway;
use Gibbon\Module\DevelopmentProfile\Domain\ObservationGateway;
use Gibbon\Module\DevelopmentProfile\Domain\SkillAssessmentGateway;
use Gibbon\Module\DevelopmentProfile\Domain\SnapshotGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Development Profile'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/DevelopmentProfile/developmentProfile.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways via DI container
    $profileGateway = $container->get(DevelopmentProfileGateway::class);
    $observationGateway = $container->get(ObservationGateway::class);
    $skillGateway = $container->get(SkillAssessmentGateway::class);
    $snapshotGateway = $container->get(SnapshotGateway::class);

    // Get current month for snapshots
    $currentMonth = date('Y-m');

    // Get summary statistics
    $criteria = $profileGateway->newQueryCriteria()->fromPOST();
    $activeProfiles = $profileGateway->queryActiveProfiles($criteria, $gibbonSchoolYearID);
    $totalActiveProfiles = $activeProfiles->count();

    // Get profiles needing snapshots this month
    $profilesNeedingSnapshots = $snapshotGateway->getProfilesNeedingSnapshots($gibbonSchoolYearID, $currentMonth);

    // Get recent concerns
    $recentConcerns = $observationGateway->getRecentConcerns($gibbonSchoolYearID, 5);

    // Get recent milestones
    $recentMilestones = $observationGateway->getRecentMilestones($gibbonSchoolYearID, 5);

    // Get profiles needing support
    $profilesNeedingSupport = $snapshotGateway->getSnapshotsNeedingSupport($gibbonSchoolYearID);

    // Quebec developmental domains
    $domains = [
        'affective' => [
            'name' => __('Affective Development'),
            'nameFR' => 'Développement affectif',
            'color' => 'pink',
            'icon' => '❤️',
        ],
        'social' => [
            'name' => __('Social Development'),
            'nameFR' => 'Développement social',
            'color' => 'blue',
            'icon' => '👥',
        ],
        'language' => [
            'name' => __('Language & Communication'),
            'nameFR' => 'Langage et communication',
            'color' => 'purple',
            'icon' => '💬',
        ],
        'cognitive' => [
            'name' => __('Cognitive Development'),
            'nameFR' => 'Développement cognitif',
            'color' => 'yellow',
            'icon' => '🧠',
        ],
        'gross_motor' => [
            'name' => __('Gross Motor'),
            'nameFR' => 'Motricité globale',
            'color' => 'green',
            'icon' => '🏃',
        ],
        'fine_motor' => [
            'name' => __('Fine Motor'),
            'nameFR' => 'Motricité fine',
            'color' => 'orange',
            'icon' => '✋',
        ],
    ];

    // Page header
    echo '<h2>' . __('Development Profile Dashboard') . '</h2>';
    echo '<p class="text-gray-600 mb-4">' . __('Portrait de Développement - Quebec-aligned 6-domain developmental tracking') . '</p>';

    // Summary Statistics Cards
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';

    // Active Profiles Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Active Profiles') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Total Active') . ':</span><span class="font-bold text-green-600">' . $totalActiveProfiles . '</span></div>';
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_manage.php" class="block mt-3 text-blue-600 hover:underline">' . __('Manage Profiles') . ' &rarr;</a>';
    echo '</div>';

    // Monthly Snapshots Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Monthly Snapshots') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Month') . ':</span><span class="font-bold">' . date('F Y') . '</span></div>';
    $needingSnapshots = count($profilesNeedingSnapshots);
    if ($needingSnapshots > 0) {
        echo '<div class="flex justify-between"><span>' . __('Pending') . ':</span><span class="text-orange-500">' . $needingSnapshots . ' ' . __('profiles') . '</span></div>';
    } else {
        echo '<p class="text-green-600">' . __('All snapshots complete!') . '</p>';
    }
    echo '</div>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_snapshots.php" class="block mt-3 text-blue-600 hover:underline">' . __('View Snapshots') . ' &rarr;</a>';
    echo '</div>';

    // Concerns Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Recent Concerns') . '</h3>';
    echo '<div class="space-y-2">';
    $concernCount = count($recentConcerns);
    if ($concernCount > 0) {
        echo '<div class="flex justify-between"><span>' . __('Recent') . ':</span><span class="text-red-500 font-bold">' . $concernCount . '</span></div>';
        foreach (array_slice($recentConcerns, 0, 2) as $concern) {
            $childName = Format::name('', $concern['preferredName'], $concern['surname'], 'Student', false, true);
            echo '<p class="text-sm text-gray-600 truncate">' . htmlspecialchars($childName) . '</p>';
        }
    } else {
        echo '<p class="text-green-600">' . __('No recent concerns.') . '</p>';
    }
    echo '</div>';
    echo '</div>';

    // Milestones Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Recent Milestones') . '</h3>';
    echo '<div class="space-y-2">';
    $milestoneCount = count($recentMilestones);
    if ($milestoneCount > 0) {
        echo '<div class="flex justify-between"><span>' . __('Achieved') . ':</span><span class="text-green-600 font-bold">' . $milestoneCount . '</span></div>';
        foreach (array_slice($recentMilestones, 0, 2) as $milestone) {
            $childName = Format::name('', $milestone['preferredName'], $milestone['surname'], 'Student', false, true);
            echo '<p class="text-sm text-gray-600 truncate">🎉 ' . htmlspecialchars($childName) . '</p>';
        }
    } else {
        echo '<p class="text-gray-500">' . __('No recent milestones.') . '</p>';
    }
    echo '</div>';
    echo '</div>';

    echo '</div>'; // End summary cards grid

    // Quebec 6 Domains Overview
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Quebec Developmental Domains') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">';

    foreach ($domains as $key => $domain) {
        echo '<div class="bg-' . $domain['color'] . '-50 rounded-lg p-4 text-center border border-' . $domain['color'] . '-200">';
        echo '<span class="text-3xl mb-2 block">' . $domain['icon'] . '</span>';
        echo '<h4 class="font-medium text-sm">' . $domain['name'] . '</h4>';
        echo '<p class="text-xs text-gray-500 mt-1">' . $domain['nameFR'] . '</p>';
        echo '</div>';
    }

    echo '</div>';

    // Profiles Needing Support
    if (!empty($profilesNeedingSupport)) {
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Children Needing Support') . '</h3>';
        echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">';
        echo '<p class="text-sm text-red-600 mb-3">' . __('These children have been flagged as needing additional support in their most recent snapshot.') . '</p>';

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2">';
        foreach ($profilesNeedingSupport as $child) {
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student', false, true);
            $image = !empty($child['image_240']) ? $child['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_view.php&gibbonDevelopmentProfileID=' . $child['gibbonDevelopmentProfileID'] . '" class="bg-white rounded p-2 flex items-center space-x-2 hover:bg-gray-50">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-8 h-8 rounded-full object-cover" alt="">';
            echo '<span class="text-sm truncate">' . htmlspecialchars($childName) . '</span>';
            echo '</a>';
        }
        echo '</div>';

        echo '</div>';
    }

    // Active Profiles Table
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Active Development Profiles') . '</h3>';

    // Build DataTable
    $table = DataTable::createPaginated('profiles', $criteria);
    $table->setTitle(__('Children with Development Profiles'));

    // Add columns
    $table->addColumn('image', __('Photo'))
        ->notSortable()
        ->format(function ($row) use ($session) {
            $image = !empty($row['image_240']) ? $row['image_240'] : 'themes/Default/img/anonymous_240.jpg';
            return '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
        });

    $table->addColumn('name', __('Name'))
        ->sortable(['surname', 'preferredName'])
        ->format(function ($row) {
            return Format::name('', $row['preferredName'], $row['surname'], 'Student', true, true);
        });

    $table->addColumn('birthDate', __('Birth Date'))
        ->sortable()
        ->format(function ($row) {
            if (!empty($row['birthDate'])) {
                return Format::date($row['birthDate']);
            } elseif (!empty($row['dob'])) {
                return Format::date($row['dob']);
            }
            return '-';
        });

    $table->addColumn('educator', __('Educator'))
        ->format(function ($row) {
            if (!empty($row['educatorName'])) {
                return Format::name('', $row['educatorName'], $row['educatorSurname'], 'Staff', false, true);
            }
            return '-';
        });

    $table->addColumn('timestampCreated', __('Created'))
        ->sortable()
        ->format(function ($row) {
            return Format::date($row['timestampCreated']);
        });

    // Add actions
    $table->addActionColumn()
        ->addParam('gibbonDevelopmentProfileID')
        ->format(function ($row, $actions) {
            $actions->addAction('view', __('View Profile'))
                ->setURL('/modules/DevelopmentProfile/developmentProfile_view.php');

            $actions->addAction('add', __('Add Observation'))
                ->setIcon('add')
                ->setURL('/modules/DevelopmentProfile/developmentProfile_add.php');

            $actions->addAction('export', __('Export PDF'))
                ->setIcon('print')
                ->setURL('/modules/DevelopmentProfile/developmentProfile_export.php');
        });

    // Output table
    if ($activeProfiles->count() > 0) {
        echo $table->render($activeProfiles);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No active development profiles found. Create profiles for children to start tracking their development.');
        echo '</div>';
    }

    // Quick Actions
    echo '<div class="mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Actions') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_add.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Add Observation') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_skill.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Assess Skills') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_snapshots.php" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('Monthly Snapshots') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_trajectory.php" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">' . __('Growth Trajectory') . '</a>';
    echo '</div>';
    echo '</div>';

    // Recent Observations Section
    if (!empty($recentMilestones) || !empty($recentConcerns)) {
        echo '<div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">';

        // Recent Milestones
        if (!empty($recentMilestones)) {
            echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4">';
            echo '<h4 class="font-semibold mb-3 text-green-700">' . __('Recent Milestones') . ' 🎉</h4>';
            echo '<div class="space-y-2">';
            foreach ($recentMilestones as $milestone) {
                $childName = Format::name('', $milestone['preferredName'], $milestone['surname'], 'Student', false, true);
                echo '<div class="bg-white rounded p-2">';
                echo '<p class="font-medium text-sm">' . htmlspecialchars($childName) . '</p>';
                echo '<p class="text-xs text-gray-600">' . htmlspecialchars(substr($milestone['behaviorDescription'], 0, 80)) . '...</p>';
                echo '<p class="text-xs text-gray-400">' . Format::date($milestone['observedAt']) . ' - ' . __($milestone['domain']) . '</p>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }

        // Recent Concerns
        if (!empty($recentConcerns)) {
            echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4">';
            echo '<h4 class="font-semibold mb-3 text-red-700">' . __('Recent Concerns') . ' ⚠️</h4>';
            echo '<div class="space-y-2">';
            foreach ($recentConcerns as $concern) {
                $childName = Format::name('', $concern['preferredName'], $concern['surname'], 'Student', false, true);
                echo '<div class="bg-white rounded p-2">';
                echo '<p class="font-medium text-sm">' . htmlspecialchars($childName) . '</p>';
                echo '<p class="text-xs text-gray-600">' . htmlspecialchars(substr($concern['behaviorDescription'], 0, 80)) . '...</p>';
                echo '<p class="text-xs text-gray-400">' . Format::date($concern['observedAt']) . ' - ' . __($concern['domain']) . '</p>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }

        echo '</div>';
    }
}
