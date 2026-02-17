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
use Gibbon\Module\DevelopmentProfile\Domain\DevelopmentProfileGateway;
use Gibbon\Module\DevelopmentProfile\Domain\ObservationGateway;
use Gibbon\Module\DevelopmentProfile\Domain\SkillAssessmentGateway;
use Gibbon\Module\DevelopmentProfile\Domain\SnapshotGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Development Profile'), 'developmentProfile.php');
$page->breadcrumbs->add(__('View Profile'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/DevelopmentProfile/developmentProfile_view.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get profile ID from request
    $gibbonDevelopmentProfileID = $_GET['gibbonDevelopmentProfileID'] ?? null;

    if (empty($gibbonDevelopmentProfileID)) {
        $page->addError(__('No development profile specified.'));
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
        return;
    }

    // Get gateways via DI container
    $profileGateway = $container->get(DevelopmentProfileGateway::class);
    $observationGateway = $container->get(ObservationGateway::class);
    $skillGateway = $container->get(SkillAssessmentGateway::class);
    $snapshotGateway = $container->get(SnapshotGateway::class);

    // Get profile with details
    $profile = $profileGateway->getProfileWithDetails($gibbonDevelopmentProfileID);

    if (empty($profile)) {
        $page->addError(__('Development profile not found.'));
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
        return;
    }

    // Get filter parameters
    $domainFilter = $_GET['domain'] ?? '';

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

    // Skill status colors
    $statusColors = [
        'can' => 'green',
        'learning' => 'blue',
        'not_yet' => 'yellow',
        'na' => 'gray',
    ];

    $statusLabels = [
        'can' => __('Can Do'),
        'learning' => __('Learning'),
        'not_yet' => __('Not Yet'),
        'na' => __('N/A'),
    ];

    // Calculate age
    $childName = Format::name('', $profile['preferredName'], $profile['surname'], 'Student', true, true);
    $birthDate = $profile['birthDate'] ?? $profile['dob'];
    $ageMonths = null;
    $ageDisplay = '-';
    if (!empty($birthDate)) {
        $birth = new DateTime($birthDate);
        $now = new DateTime();
        $diff = $now->diff($birth);
        $ageMonths = ($diff->y * 12) + $diff->m;
        $ageDisplay = $diff->y . ' ' . __('years') . ', ' . $diff->m . ' ' . __('months');
    }

    // Get profile summary statistics
    $summary = $profileGateway->getProfileSummary($gibbonDevelopmentProfileID);

    // Get domain progress
    $domainProgress = $profileGateway->getDomainProgress($gibbonDevelopmentProfileID);

    // Get observation stats
    $observationStats = $observationGateway->getObservationStatsByDomain($gibbonDevelopmentProfileID);

    // Get skill progress
    $skillProgress = $skillGateway->getOverallProgress($gibbonDevelopmentProfileID);

    // Get recently mastered skills
    $recentlyMastered = $skillGateway->getRecentlyMasteredSkills($gibbonDevelopmentProfileID, 5);

    // Get currently learning skills
    $currentlyLearning = $skillGateway->getCurrentlyLearningSkills($gibbonDevelopmentProfileID);

    // Get latest snapshot
    $latestSnapshot = $snapshotGateway->getLatestSnapshot($gibbonDevelopmentProfileID);

    // Profile Header
    $image = !empty($profile['image_240']) ? $profile['image_240'] : 'themes/Default/img/anonymous_240.jpg';

    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<div class="flex items-start space-x-6">';

    // Profile image
    echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-24 h-24 rounded-full object-cover" alt="">';

    // Profile info
    echo '<div class="flex-1">';
    echo '<h2 class="text-2xl font-bold">' . htmlspecialchars($childName) . '</h2>';
    echo '<p class="text-gray-600">' . __('Portrait de Développement') . '</p>';
    echo '<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-3">';

    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Birth Date') . '</span>';
    echo '<p class="font-medium">' . (!empty($birthDate) ? Format::date($birthDate) : '-') . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Age') . '</span>';
    echo '<p class="font-medium">' . $ageDisplay . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Educator') . '</span>';
    echo '<p class="font-medium">' . (!empty($profile['educatorName']) ? Format::name('', $profile['educatorName'], $profile['educatorSurname'], 'Staff', false, true) : '-') . '</p>';
    echo '</div>';

    echo '<div>';
    echo '<span class="text-sm text-gray-500">' . __('Status') . '</span>';
    echo '<p class="font-medium">' . ($profile['isActive'] === 'Y' ? '<span class="text-green-600">' . __('Active') . '</span>' : '<span class="text-gray-400">' . __('Inactive') . '</span>') . '</p>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Quick actions
    echo '<div class="flex flex-col space-y-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_add.php&gibbonDevelopmentProfileID=' . $gibbonDevelopmentProfileID . '" class="bg-blue-500 text-white px-4 py-2 rounded text-sm hover:bg-blue-600 text-center">' . __('Add Observation') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_export.php&gibbonDevelopmentProfileID=' . $gibbonDevelopmentProfileID . '" class="bg-gray-500 text-white px-4 py-2 rounded text-sm hover:bg-gray-600 text-center">' . __('Export PDF') . '</a>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Summary Statistics
    echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">';

    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<span class="block text-3xl font-bold text-blue-600">' . ($summary['totalSkills'] ?? 0) . '</span>';
    echo '<span class="text-sm text-gray-500">' . __('Total Skills') . '</span>';
    echo '</div>';

    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<span class="block text-3xl font-bold text-green-600">' . ($summary['masteredSkills'] ?? 0) . '</span>';
    echo '<span class="text-sm text-gray-500">' . __('Mastered') . '</span>';
    echo '</div>';

    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<span class="block text-3xl font-bold text-blue-600">' . ($summary['learningSkills'] ?? 0) . '</span>';
    echo '<span class="text-sm text-gray-500">' . __('Learning') . '</span>';
    echo '</div>';

    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<span class="block text-3xl font-bold text-purple-600">' . ($summary['totalObservations'] ?? 0) . '</span>';
    echo '<span class="text-sm text-gray-500">' . __('Observations') . '</span>';
    echo '</div>';

    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<span class="block text-3xl font-bold text-green-600">' . ($summary['milestoneObservations'] ?? 0) . '</span>';
    echo '<span class="text-sm text-gray-500">' . __('Milestones') . '</span>';
    echo '</div>';

    echo '<div class="bg-white rounded-lg shadow p-4 text-center">';
    echo '<span class="block text-3xl font-bold text-red-600">' . ($summary['concernObservations'] ?? 0) . '</span>';
    echo '<span class="text-sm text-gray-500">' . __('Concerns') . '</span>';
    echo '</div>';

    echo '</div>';

    // Domain Progress Overview
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Development by Domain') . '</h3>';

    // Create domain progress map
    $domainProgressMap = [];
    foreach ($domainProgress as $dp) {
        $domainProgressMap[$dp['domain']] = $dp;
    }

    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">';

    foreach ($domains as $key => $domain) {
        $progress = $domainProgressMap[$key] ?? null;
        $total = $progress['totalSkills'] ?? 0;
        $mastered = $progress['masteredCount'] ?? 0;
        $learning = $progress['learningCount'] ?? 0;
        $notYet = $progress['notYetCount'] ?? 0;

        // Calculate percentage (excluding N/A)
        $assessed = $total - ($progress['naCount'] ?? 0);
        $masteryPercent = $assessed > 0 ? round(($mastered / $assessed) * 100) : 0;

        echo '<div class="bg-white rounded-lg shadow p-4 border-l-4 border-' . $domain['color'] . '-500">';
        echo '<div class="flex items-center justify-between mb-2">';
        echo '<h4 class="font-medium">' . $domain['icon'] . ' ' . $domain['name'] . '</h4>';
        echo '<span class="text-lg font-bold text-' . $domain['color'] . '-600">' . $masteryPercent . '%</span>';
        echo '</div>';

        // Progress bar
        if ($assessed > 0) {
            $masteredWidth = round(($mastered / $assessed) * 100);
            $learningWidth = round(($learning / $assessed) * 100);
            $notYetWidth = 100 - $masteredWidth - $learningWidth;

            echo '<div class="w-full bg-gray-200 rounded-full h-3 mb-2 flex overflow-hidden">';
            if ($masteredWidth > 0) {
                echo '<div class="bg-green-500 h-3" style="width: ' . $masteredWidth . '%"></div>';
            }
            if ($learningWidth > 0) {
                echo '<div class="bg-blue-400 h-3" style="width: ' . $learningWidth . '%"></div>';
            }
            if ($notYetWidth > 0) {
                echo '<div class="bg-yellow-300 h-3" style="width: ' . $notYetWidth . '%"></div>';
            }
            echo '</div>';
        } else {
            echo '<div class="w-full bg-gray-200 rounded-full h-3 mb-2"></div>';
        }

        // Stats row
        echo '<div class="flex justify-between text-xs text-gray-500">';
        echo '<span class="text-green-600">✓ ' . $mastered . ' ' . __('mastered') . '</span>';
        echo '<span class="text-blue-600">◐ ' . $learning . ' ' . __('learning') . '</span>';
        echo '<span class="text-yellow-600">○ ' . $notYet . ' ' . __('not yet') . '</span>';
        echo '</div>';

        // View link
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_view.php&gibbonDevelopmentProfileID=' . $gibbonDevelopmentProfileID . '&domain=' . $key . '" class="text-xs text-blue-600 hover:underline mt-2 inline-block">' . __('View details') . ' &rarr;</a>';
        echo '</div>';
    }

    echo '</div>';

    // Recent Achievements Section
    if (!empty($recentlyMastered)) {
        echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">';
        echo '<h4 class="font-semibold mb-3 text-green-700">🎉 ' . __('Recently Mastered Skills') . '</h4>';
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">';
        foreach ($recentlyMastered as $skill) {
            $domainInfo = $domains[$skill['domain']] ?? ['icon' => '', 'color' => 'gray'];
            echo '<div class="bg-white rounded p-2 flex items-center space-x-2">';
            echo '<span>' . $domainInfo['icon'] . '</span>';
            echo '<div class="flex-1">';
            echo '<p class="font-medium text-sm">' . htmlspecialchars($skill['skillName']) . '</p>';
            if (!empty($skill['skillNameFR'])) {
                echo '<p class="text-xs text-gray-500">' . htmlspecialchars($skill['skillNameFR']) . '</p>';
            }
            echo '</div>';
            echo '<span class="text-xs text-gray-400">' . Format::date($skill['assessedAt']) . '</span>';
            echo '</div>';
        }
        echo '</div>';
        echo '</div>';
    }

    // Currently Learning Skills
    if (!empty($currentlyLearning)) {
        echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">';
        echo '<h4 class="font-semibold mb-3 text-blue-700">📚 ' . __('Currently Learning') . '</h4>';
        echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">';
        foreach (array_slice($currentlyLearning, 0, 9) as $skill) {
            $domainInfo = $domains[$skill['domain']] ?? ['icon' => '', 'color' => 'gray'];
            echo '<div class="bg-white rounded p-2 flex items-center space-x-2">';
            echo '<span>' . $domainInfo['icon'] . '</span>';
            echo '<div class="flex-1">';
            echo '<p class="font-medium text-sm">' . htmlspecialchars($skill['skillName']) . '</p>';
            if (!empty($skill['evidence'])) {
                echo '<p class="text-xs text-gray-500 truncate">' . htmlspecialchars(substr($skill['evidence'], 0, 50)) . '</p>';
            }
            echo '</div>';
            echo '</div>';
        }
        if (count($currentlyLearning) > 9) {
            echo '<p class="text-sm text-blue-600 col-span-full mt-2">+ ' . (count($currentlyLearning) - 9) . ' ' . __('more skills') . '</p>';
        }
        echo '</div>';
        echo '</div>';
    }

    // Latest Snapshot
    if (!empty($latestSnapshot)) {
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Latest Monthly Snapshot') . '</h3>';
        echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';

        echo '<div class="flex justify-between items-start mb-3">';
        echo '<div>';
        echo '<h4 class="font-medium">' . Format::monthName(substr($latestSnapshot['snapshotMonth'], 5, 2)) . ' ' . substr($latestSnapshot['snapshotMonth'], 0, 4) . '</h4>';
        echo '<p class="text-sm text-gray-500">' . __('Age at snapshot') . ': ' . ($latestSnapshot['ageMonths'] ?? '-') . ' ' . __('months') . '</p>';
        echo '</div>';

        // Overall progress badge
        $progressColors = [
            'on_track' => 'green',
            'needs_support' => 'orange',
            'excelling' => 'blue',
        ];
        $progressLabels = [
            'on_track' => __('On Track'),
            'needs_support' => __('Needs Support'),
            'excelling' => __('Excelling'),
        ];
        $progressColor = $progressColors[$latestSnapshot['overallProgress']] ?? 'gray';
        $progressLabel = $progressLabels[$latestSnapshot['overallProgress']] ?? $latestSnapshot['overallProgress'];
        echo '<span class="bg-' . $progressColor . '-100 text-' . $progressColor . '-800 px-3 py-1 rounded text-sm font-medium">' . $progressLabel . '</span>';
        echo '</div>';

        // Strengths
        if (!empty($latestSnapshot['strengths'])) {
            $strengths = json_decode($latestSnapshot['strengths'], true);
            if (!empty($strengths)) {
                echo '<div class="mb-3">';
                echo '<h5 class="text-sm font-medium text-green-700 mb-1">' . __('Strengths') . ':</h5>';
                echo '<ul class="list-disc list-inside text-sm text-gray-600">';
                foreach ($strengths as $strength) {
                    echo '<li>' . htmlspecialchars($strength) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
        }

        // Growth Areas
        if (!empty($latestSnapshot['growthAreas'])) {
            $growthAreas = json_decode($latestSnapshot['growthAreas'], true);
            if (!empty($growthAreas)) {
                echo '<div class="mb-3">';
                echo '<h5 class="text-sm font-medium text-orange-700 mb-1">' . __('Growth Areas') . ':</h5>';
                echo '<ul class="list-disc list-inside text-sm text-gray-600">';
                foreach ($growthAreas as $area) {
                    echo '<li>' . htmlspecialchars($area) . '</li>';
                }
                echo '</ul>';
                echo '</div>';
            }
        }

        // Recommendations
        if (!empty($latestSnapshot['recommendations'])) {
            echo '<div>';
            echo '<h5 class="text-sm font-medium text-blue-700 mb-1">' . __('Recommendations') . ':</h5>';
            echo '<p class="text-sm text-gray-600">' . htmlspecialchars($latestSnapshot['recommendations']) . '</p>';
            echo '</div>';
        }

        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_snapshots.php&gibbonDevelopmentProfileID=' . $gibbonDevelopmentProfileID . '" class="text-sm text-blue-600 hover:underline mt-3 inline-block">' . __('View all snapshots') . ' &rarr;</a>';
        echo '</div>';
    }

    // Observations Table
    echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Observations') . '</h3>';

    // Domain filter form
    $domainOptions = ['' => __('All Domains')];
    foreach ($domains as $key => $domain) {
        $domainOptions[$key] = $domain['icon'] . ' ' . $domain['name'];
    }

    $filterForm = Form::create('observationFilter', $session->get('absoluteURL') . '/index.php');
    $filterForm->setMethod('get');
    $filterForm->addHiddenValue('q', '/modules/DevelopmentProfile/developmentProfile_view.php');
    $filterForm->addHiddenValue('gibbonDevelopmentProfileID', $gibbonDevelopmentProfileID);

    $row = $filterForm->addRow();
    $row->addLabel('domain', __('Filter by Domain'));
    $row->addSelect('domain')->fromArray($domainOptions)->selected($domainFilter);

    $row = $filterForm->addRow();
    $row->addSubmit(__('Filter'));

    echo $filterForm->getOutput();

    // Build observations query
    $criteria = $observationGateway->newQueryCriteria()
        ->sortBy(['observedAt'], 'DESC')
        ->fromPOST();

    if (!empty($domainFilter)) {
        $criteria->filterBy('domain', $domainFilter);
    }

    $observations = $observationGateway->queryObservations($criteria, $gibbonDevelopmentProfileID);

    // Build DataTable
    $table = DataTable::createPaginated('observations', $criteria);
    $table->setTitle(__('Observation Records'));

    $table->addColumn('observedAt', __('Date'))
        ->sortable()
        ->format(function ($row) {
            return Format::date($row['observedAt']);
        });

    $table->addColumn('domain', __('Domain'))
        ->sortable()
        ->format(function ($row) use ($domains) {
            $domain = $domains[$row['domain']] ?? null;
            if ($domain) {
                return '<span class="bg-' . $domain['color'] . '-100 text-' . $domain['color'] . '-800 text-xs px-2 py-1 rounded">' . $domain['icon'] . ' ' . $domain['name'] . '</span>';
            }
            return $row['domain'];
        });

    $table->addColumn('behaviorDescription', __('Behavior'))
        ->format(function ($row) {
            $desc = htmlspecialchars($row['behaviorDescription']);
            if (strlen($desc) > 100) {
                $desc = substr($desc, 0, 100) . '...';
            }
            return $desc;
        });

    $table->addColumn('flags', __('Flags'))
        ->notSortable()
        ->format(function ($row) {
            $flags = [];
            if ($row['isMilestone'] === 'Y') {
                $flags[] = '<span class="text-green-600">🎉 ' . __('Milestone') . '</span>';
            }
            if ($row['isConcern'] === 'Y') {
                $flags[] = '<span class="text-red-600">⚠️ ' . __('Concern') . '</span>';
            }
            return implode(' ', $flags) ?: '-';
        });

    $table->addColumn('observer', __('Observer'))
        ->format(function ($row) {
            $type = ucfirst($row['observerType']);
            $name = Format::name('', $row['observerName'], $row['observerSurname'], 'Staff', false, true);
            return $name . ' <span class="text-xs text-gray-500">(' . __($type) . ')</span>';
        });

    // Output table
    if ($observations->count() > 0) {
        echo $table->render($observations);
    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        echo __('No observations recorded yet.');
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-6">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
