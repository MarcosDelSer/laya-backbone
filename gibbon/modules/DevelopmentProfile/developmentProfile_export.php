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
use Gibbon\Module\DevelopmentProfile\Domain\SkillAssessmentGateway;
use Gibbon\Module\DevelopmentProfile\Domain\SnapshotGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Development Profile'), 'developmentProfile.php');
$page->breadcrumbs->add(__('Export Profile'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/DevelopmentProfile/developmentProfile_export.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get profile ID from request
    $gibbonDevelopmentProfileID = $_GET['gibbonDevelopmentProfileID'] ?? null;

    // Get gateways via DI container
    $profileGateway = $container->get(DevelopmentProfileGateway::class);
    $observationGateway = $container->get(ObservationGateway::class);
    $skillGateway = $container->get(SkillAssessmentGateway::class);
    $snapshotGateway = $container->get(SnapshotGateway::class);

    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Quebec developmental domains
    $domains = [
        'affective' => [
            'name' => __('Affective Development'),
            'nameFR' => 'Développement affectif',
            'icon' => '❤️',
        ],
        'social' => [
            'name' => __('Social Development'),
            'nameFR' => 'Développement social',
            'icon' => '👥',
        ],
        'language' => [
            'name' => __('Language & Communication'),
            'nameFR' => 'Langage et communication',
            'icon' => '💬',
        ],
        'cognitive' => [
            'name' => __('Cognitive Development'),
            'nameFR' => 'Développement cognitif',
            'icon' => '🧠',
        ],
        'gross_motor' => [
            'name' => __('Gross Motor'),
            'nameFR' => 'Motricité globale',
            'icon' => '🏃',
        ],
        'fine_motor' => [
            'name' => __('Fine Motor'),
            'nameFR' => 'Motricité fine',
            'icon' => '✋',
        ],
    ];

    // Handle export request
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    if ($action === 'export' && !empty($gibbonDevelopmentProfileID)) {
        // Get profile data
        $profile = $profileGateway->getProfileWithDetails($gibbonDevelopmentProfileID);

        if (empty($profile)) {
            $page->addError(__('Development profile not found.'));
        } else {
            // Export options from form
            $includeSkills = isset($_POST['includeSkills']) && $_POST['includeSkills'] === 'Y';
            $includeObservations = isset($_POST['includeObservations']) && $_POST['includeObservations'] === 'Y';
            $includeSnapshots = isset($_POST['includeSnapshots']) && $_POST['includeSnapshots'] === 'Y';
            $dateFrom = !empty($_POST['dateFrom']) ? Format::dateConvert($_POST['dateFrom']) : null;
            $dateTo = !empty($_POST['dateTo']) ? Format::dateConvert($_POST['dateTo']) : null;

            // Child info
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

            // Get summary
            $summary = $profileGateway->getProfileSummary($gibbonDevelopmentProfileID);
            $domainProgress = $profileGateway->getDomainProgress($gibbonDevelopmentProfileID);

            // Start building HTML for PDF
            $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($childName) . ' - ' . __('Development Profile') . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.5;
            color: #333;
            margin: 20px;
        }
        h1 { font-size: 24px; color: #1a365d; margin-bottom: 10px; }
        h2 { font-size: 18px; color: #2d3748; margin-top: 20px; margin-bottom: 10px; border-bottom: 2px solid #e2e8f0; padding-bottom: 5px; }
        h3 { font-size: 14px; color: #4a5568; margin-top: 15px; margin-bottom: 8px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #1a365d; padding-bottom: 20px; }
        .subtitle { color: #718096; font-size: 14px; }
        .info-grid { display: table; width: 100%; margin-bottom: 20px; }
        .info-row { display: table-row; }
        .info-cell { display: table-cell; padding: 5px 10px; width: 25%; }
        .info-label { font-weight: bold; color: #4a5568; font-size: 11px; }
        .info-value { color: #1a202c; }
        .summary-box { background: #f7fafc; border: 1px solid #e2e8f0; border-radius: 5px; padding: 15px; margin-bottom: 20px; }
        .domain-section { margin-bottom: 20px; padding: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 5px; }
        .domain-header { font-weight: bold; margin-bottom: 10px; }
        .skill-list { margin-left: 20px; }
        .skill-item { margin: 5px 0; }
        .status-can { color: #38a169; }
        .status-learning { color: #3182ce; }
        .status-not_yet { color: #d69e2e; }
        .status-na { color: #a0aec0; }
        .observation { border-left: 3px solid #e2e8f0; padding-left: 15px; margin: 10px 0; }
        .observation-date { font-size: 11px; color: #718096; }
        .milestone { border-left-color: #38a169; }
        .concern { border-left-color: #e53e3e; }
        .snapshot { background: #fff; border: 1px solid #e2e8f0; border-radius: 5px; padding: 15px; margin-bottom: 15px; }
        .progress-bar { background: #e2e8f0; height: 10px; border-radius: 5px; overflow: hidden; margin: 5px 0; }
        .progress-fill { height: 100%; }
        .progress-can { background: #38a169; }
        .progress-learning { background: #3182ce; }
        .progress-not_yet { background: #d69e2e; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
        th { background: #f7fafc; font-weight: bold; }
        .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #e2e8f0; font-size: 10px; color: #718096; text-align: center; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>' . __('Portrait de Développement') . '</h1>
        <p class="subtitle">' . __('Development Profile - Quebec Early Childhood Assessment') . '</p>
    </div>

    <div class="info-grid">
        <div class="info-row">
            <div class="info-cell">
                <span class="info-label">' . __('Child Name') . '</span><br>
                <span class="info-value">' . htmlspecialchars($childName) . '</span>
            </div>
            <div class="info-cell">
                <span class="info-label">' . __('Birth Date') . '</span><br>
                <span class="info-value">' . (!empty($birthDate) ? Format::date($birthDate) : '-') . '</span>
            </div>
            <div class="info-cell">
                <span class="info-label">' . __('Age') . '</span><br>
                <span class="info-value">' . $ageDisplay . '</span>
            </div>
            <div class="info-cell">
                <span class="info-label">' . __('Report Date') . '</span><br>
                <span class="info-value">' . Format::date(date('Y-m-d')) . '</span>
            </div>
        </div>
        <div class="info-row">
            <div class="info-cell">
                <span class="info-label">' . __('Educator') . '</span><br>
                <span class="info-value">' . (!empty($profile['educatorName']) ? Format::name('', $profile['educatorName'], $profile['educatorSurname'], 'Staff', false, true) : '-') . '</span>
            </div>
            <div class="info-cell">
                <span class="info-label">' . __('Profile Status') . '</span><br>
                <span class="info-value">' . ($profile['isActive'] === 'Y' ? __('Active') : __('Inactive')) . '</span>
            </div>
            <div class="info-cell" colspan="2">
                <span class="info-label">' . __('Profile Created') . '</span><br>
                <span class="info-value">' . Format::date($profile['timestampCreated']) . '</span>
            </div>
        </div>
    </div>

    <div class="summary-box">
        <h3>' . __('Overall Summary') . '</h3>
        <table>
            <tr>
                <th>' . __('Total Skills') . '</th>
                <th>' . __('Mastered') . '</th>
                <th>' . __('Learning') . '</th>
                <th>' . __('Not Yet') . '</th>
                <th>' . __('Observations') . '</th>
                <th>' . __('Milestones') . '</th>
            </tr>
            <tr>
                <td>' . ($summary['totalSkills'] ?? 0) . '</td>
                <td class="status-can">' . ($summary['masteredSkills'] ?? 0) . '</td>
                <td class="status-learning">' . ($summary['learningSkills'] ?? 0) . '</td>
                <td class="status-not_yet">' . ($summary['notYetSkills'] ?? 0) . '</td>
                <td>' . ($summary['totalObservations'] ?? 0) . '</td>
                <td>' . ($summary['milestoneObservations'] ?? 0) . '</td>
            </tr>
        </table>
    </div>

    <h2>' . __('Development by Domain') . '</h2>';

            // Domain progress map
            $domainProgressMap = [];
            foreach ($domainProgress as $dp) {
                $domainProgressMap[$dp['domain']] = $dp;
            }

            foreach ($domains as $key => $domain) {
                $progress = $domainProgressMap[$key] ?? null;
                $total = $progress['totalSkills'] ?? 0;
                $mastered = $progress['masteredCount'] ?? 0;
                $learning = $progress['learningCount'] ?? 0;
                $notYet = $progress['notYetCount'] ?? 0;
                $assessed = $total - ($progress['naCount'] ?? 0);
                $masteryPercent = $assessed > 0 ? round(($mastered / $assessed) * 100) : 0;

                $html .= '
    <div class="domain-section">
        <div class="domain-header">' . $domain['icon'] . ' ' . $domain['name'] . ' / ' . $domain['nameFR'] . '</div>
        <p>' . __('Mastery') . ': ' . $masteryPercent . '%</p>
        <div class="progress-bar">';

                if ($assessed > 0) {
                    $masteredWidth = round(($mastered / $assessed) * 100);
                    $learningWidth = round(($learning / $assessed) * 100);
                    if ($masteredWidth > 0) {
                        $html .= '<span class="progress-fill progress-can" style="display: inline-block; width: ' . $masteredWidth . '%;"></span>';
                    }
                    if ($learningWidth > 0) {
                        $html .= '<span class="progress-fill progress-learning" style="display: inline-block; width: ' . $learningWidth . '%;"></span>';
                    }
                }

                $html .= '
        </div>
        <p style="font-size: 11px; color: #718096;">
            <span class="status-can">✓ ' . $mastered . ' ' . __('mastered') . '</span> |
            <span class="status-learning">◐ ' . $learning . ' ' . __('learning') . '</span> |
            <span class="status-not_yet">○ ' . $notYet . ' ' . __('not yet') . '</span>
        </p>';

                // Include skills for this domain if requested
                if ($includeSkills) {
                    $criteria = $skillGateway->newQueryCriteria()->sortBy(['skillName']);
                    $skills = $skillGateway->queryAssessmentsByDomain($criteria, $gibbonDevelopmentProfileID, $key);

                    if ($skills->count() > 0) {
                        $html .= '<div class="skill-list">';
                        foreach ($skills as $skill) {
                            $statusClass = 'status-' . $skill['status'];
                            $statusSymbol = ['can' => '✓', 'learning' => '◐', 'not_yet' => '○', 'na' => '−'][$skill['status']] ?? '−';
                            $html .= '<div class="skill-item">
                                <span class="' . $statusClass . '">' . $statusSymbol . '</span>
                                ' . htmlspecialchars($skill['skillName']);
                            if (!empty($skill['skillNameFR'])) {
                                $html .= ' <span style="color: #718096;">(' . htmlspecialchars($skill['skillNameFR']) . ')</span>';
                            }
                            $html .= '</div>';
                        }
                        $html .= '</div>';
                    }
                }

                $html .= '</div>';
            }

            // Include observations if requested
            if ($includeObservations) {
                $html .= '<div class="page-break"></div><h2>' . __('Observations') . '</h2>';

                $criteria = $observationGateway->newQueryCriteria()->sortBy(['observedAt'], 'DESC');
                if (!empty($dateFrom)) {
                    $criteria->filterBy('dateFrom', $dateFrom);
                }
                if (!empty($dateTo)) {
                    $criteria->filterBy('dateTo', $dateTo);
                }
                $observations = $observationGateway->queryObservations($criteria, $gibbonDevelopmentProfileID);

                if ($observations->count() > 0) {
                    foreach ($observations as $obs) {
                        $obsClass = 'observation';
                        if ($obs['isMilestone'] === 'Y') {
                            $obsClass .= ' milestone';
                        }
                        if ($obs['isConcern'] === 'Y') {
                            $obsClass .= ' concern';
                        }

                        $domainInfo = $domains[$obs['domain']] ?? ['name' => $obs['domain'], 'icon' => ''];

                        $html .= '
    <div class="' . $obsClass . '">
        <div class="observation-date">' . Format::date($obs['observedAt']) . ' - ' . $domainInfo['icon'] . ' ' . $domainInfo['name'] . '</div>
        <p>' . nl2br(htmlspecialchars($obs['behaviorDescription'])) . '</p>';

                        if (!empty($obs['context'])) {
                            $html .= '<p style="font-size: 11px; color: #718096;"><strong>' . __('Context') . ':</strong> ' . htmlspecialchars($obs['context']) . '</p>';
                        }

                        $flags = [];
                        if ($obs['isMilestone'] === 'Y') {
                            $flags[] = '🎉 ' . __('Milestone');
                        }
                        if ($obs['isConcern'] === 'Y') {
                            $flags[] = '⚠️ ' . __('Concern');
                        }
                        if (!empty($flags)) {
                            $html .= '<p style="font-size: 11px;"><strong>' . implode(' | ', $flags) . '</strong></p>';
                        }

                        $observerName = Format::name('', $obs['observerName'], $obs['observerSurname'], 'Staff', false, true);
                        $html .= '<p style="font-size: 10px; color: #a0aec0;">' . __('Observer') . ': ' . htmlspecialchars($observerName) . ' (' . __($obs['observerType']) . ')</p>
    </div>';
                    }
                } else {
                    $html .= '<p style="color: #718096;">' . __('No observations in the selected date range.') . '</p>';
                }
            }

            // Include snapshots if requested
            if ($includeSnapshots) {
                $html .= '<div class="page-break"></div><h2>' . __('Monthly Snapshots') . '</h2>';

                $criteria = $snapshotGateway->newQueryCriteria()->sortBy(['snapshotMonth'], 'DESC');
                $snapshots = $snapshotGateway->querySnapshots($criteria, $gibbonDevelopmentProfileID);

                if ($snapshots->count() > 0) {
                    foreach ($snapshots as $snap) {
                        $progressLabels = [
                            'on_track' => __('On Track'),
                            'needs_support' => __('Needs Support'),
                            'excelling' => __('Excelling'),
                        ];

                        $html .= '
    <div class="snapshot">
        <h3>' . Format::monthName(substr($snap['snapshotMonth'], 5, 2)) . ' ' . substr($snap['snapshotMonth'], 0, 4) . '</h3>
        <p><strong>' . __('Age') . ':</strong> ' . ($snap['ageMonths'] ?? '-') . ' ' . __('months') . '</p>
        <p><strong>' . __('Overall Progress') . ':</strong> ' . ($progressLabels[$snap['overallProgress']] ?? $snap['overallProgress']) . '</p>';

                        if (!empty($snap['strengths'])) {
                            $strengths = json_decode($snap['strengths'], true);
                            if (!empty($strengths)) {
                                $html .= '<p><strong>' . __('Strengths') . ':</strong></p><ul>';
                                foreach ($strengths as $s) {
                                    $html .= '<li>' . htmlspecialchars($s) . '</li>';
                                }
                                $html .= '</ul>';
                            }
                        }

                        if (!empty($snap['growthAreas'])) {
                            $areas = json_decode($snap['growthAreas'], true);
                            if (!empty($areas)) {
                                $html .= '<p><strong>' . __('Growth Areas') . ':</strong></p><ul>';
                                foreach ($areas as $a) {
                                    $html .= '<li>' . htmlspecialchars($a) . '</li>';
                                }
                                $html .= '</ul>';
                            }
                        }

                        if (!empty($snap['recommendations'])) {
                            $html .= '<p><strong>' . __('Recommendations') . ':</strong> ' . htmlspecialchars($snap['recommendations']) . '</p>';
                        }

                        $html .= '</div>';
                    }
                } else {
                    $html .= '<p style="color: #718096;">' . __('No monthly snapshots available.') . '</p>';
                }
            }

            // Footer
            $html .= '
    <div class="footer">
        <p>' . __('Portrait de Développement - Quebec Early Childhood Development Profile') . '</p>
        <p>' . __('Generated') . ': ' . Format::dateTime(date('Y-m-d H:i:s')) . '</p>
        <p>' . $session->get('organisationName') . '</p>
    </div>
</body>
</html>';

            // Output as downloadable HTML (can be printed to PDF by browser)
            $filename = 'DevelopmentProfile_' . preg_replace('/[^a-zA-Z0-9]/', '_', $childName) . '_' . date('Y-m-d') . '.html';

            header('Content-Type: text/html; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $html;
            exit;
        }
    }

    // Show export form if no action
    $profile = null;
    if (!empty($gibbonDevelopmentProfileID)) {
        $profile = $profileGateway->getProfileWithDetails($gibbonDevelopmentProfileID);
    }

    // Page header
    echo '<h2>' . __('Export Development Profile') . '</h2>';
    echo '<p class="text-gray-600 mb-4">' . __('Generate a printable PDF portfolio of a child\'s developmental profile.') . '</p>';

    // Get active profiles for selection if none specified
    $criteria = $profileGateway->newQueryCriteria()->sortBy(['surname', 'preferredName']);
    $activeProfiles = $profileGateway->queryActiveProfiles($criteria, $gibbonSchoolYearID);

    if ($activeProfiles->count() === 0) {
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">';
        echo '<p class="text-yellow-600">' . __('No active development profiles found.') . '</p>';
        echo '</div>';
    } else {
        // Build child selection options
        $childOptions = [];
        foreach ($activeProfiles as $p) {
            $childName = Format::name('', $p['preferredName'], $p['surname'], 'Student', false, true);
            $childOptions[$p['gibbonDevelopmentProfileID']] = $childName;
        }

        // Create export form
        $form = Form::create('exportProfile', $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_export.php');
        $form->setDescription(__('Select a child and choose export options to generate a PDF-ready development profile report.'));
        $form->addHiddenValue('action', 'export');

        // Child selection
        $row = $form->addRow();
        $row->addLabel('gibbonDevelopmentProfileID', __('Child'))->description(__('Select the child whose profile to export.'));
        $row->addSelect('gibbonDevelopmentProfileID')
            ->fromArray($childOptions)
            ->required()
            ->placeholder(__('Select Child...'))
            ->selected($gibbonDevelopmentProfileID);

        // Export options
        $form->addRow()->addHeading(__('Export Options'));

        $row = $form->addRow();
        $row->addLabel('includeSkills', __('Include Skills'))->description(__('Include detailed skill assessments for each domain.'));
        $row->addCheckbox('includeSkills')
            ->setValue('Y')
            ->checked(true)
            ->description(__('Include skill details'));

        $row = $form->addRow();
        $row->addLabel('includeObservations', __('Include Observations'))->description(__('Include all behavioral observations.'));
        $row->addCheckbox('includeObservations')
            ->setValue('Y')
            ->checked(true)
            ->description(__('Include observations'));

        $row = $form->addRow();
        $row->addLabel('includeSnapshots', __('Include Snapshots'))->description(__('Include monthly developmental snapshots.'));
        $row->addCheckbox('includeSnapshots')
            ->setValue('Y')
            ->checked(true)
            ->description(__('Include snapshots'));

        // Date range filter
        $form->addRow()->addHeading(__('Date Range (Optional)'));

        $row = $form->addRow();
        $row->addLabel('dateFrom', __('From Date'))->description(__('Filter observations from this date.'));
        $row->addDate('dateFrom');

        $row = $form->addRow();
        $row->addLabel('dateTo', __('To Date'))->description(__('Filter observations up to this date.'));
        $row->addDate('dateTo');

        // Submit
        $row = $form->addRow();
        $row->addFooter();
        $row->addSubmit(__('Generate Export'));

        echo $form->getOutput();

        // Preview selected profile
        if ($profile) {
            $childName = Format::name('', $profile['preferredName'], $profile['surname'], 'Student', true, true);
            $image = !empty($profile['image_240']) ? $profile['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mt-6">';
            echo '<h4 class="font-semibold mb-3">' . __('Selected Profile Preview') . '</h4>';
            echo '<div class="flex items-center space-x-4">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-16 h-16 rounded-full object-cover" alt="">';
            echo '<div>';
            echo '<p class="font-medium">' . htmlspecialchars($childName) . '</p>';
            $birthDate = $profile['birthDate'] ?? $profile['dob'];
            if (!empty($birthDate)) {
                $birth = new DateTime($birthDate);
                $now = new DateTime();
                $diff = $now->diff($birth);
                echo '<p class="text-sm text-gray-600">' . __('Age') . ': ' . $diff->y . ' ' . __('years') . ', ' . $diff->m . ' ' . __('months') . '</p>';
            }
            if (!empty($profile['educatorName'])) {
                echo '<p class="text-sm text-gray-600">' . __('Educator') . ': ' . Format::name('', $profile['educatorName'], $profile['educatorSurname'], 'Staff', false, true) . '</p>';
            }
            echo '</div>';
            echo '</div>';
            echo '</div>';
        }

        // Quick child selection
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Quick Select Child') . '</h3>';

        echo '<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2 mb-4">';
        foreach ($activeProfiles as $p) {
            $childName = Format::name('', $p['preferredName'], $p['surname'], 'Student', false, true);
            $image = !empty($p['image_240']) ? $p['image_240'] : 'themes/Default/img/anonymous_240.jpg';

            $selected = ($p['gibbonDevelopmentProfileID'] == $gibbonDevelopmentProfileID) ? 'ring-2 ring-blue-500' : '';

            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile_export.php&gibbonDevelopmentProfileID=' . $p['gibbonDevelopmentProfileID'] . '" class="bg-white rounded p-2 flex items-center space-x-2 hover:bg-gray-50 border ' . $selected . '">';
            echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
            echo '<span class="text-sm truncate">' . htmlspecialchars($childName) . '</span>';
            echo '</a>';
        }
        echo '</div>';
    }

    // Instructions
    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 mt-6">';
    echo '<h4 class="font-semibold mb-2">' . __('Export Instructions') . '</h4>';
    echo '<ol class="list-decimal list-inside text-sm text-gray-600 space-y-1">';
    echo '<li>' . __('Select a child from the dropdown or quick select grid above.') . '</li>';
    echo '<li>' . __('Choose which sections to include in the export.') . '</li>';
    echo '<li>' . __('Optionally filter observations by date range.') . '</li>';
    echo '<li>' . __('Click "Generate Export" to download the HTML file.') . '</li>';
    echo '<li>' . __('Open the downloaded file in a browser and use Print > Save as PDF.') . '</li>';
    echo '</ol>';
    echo '</div>';

    // Link back to dashboard
    echo '<div class="mt-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/DevelopmentProfile/developmentProfile.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
