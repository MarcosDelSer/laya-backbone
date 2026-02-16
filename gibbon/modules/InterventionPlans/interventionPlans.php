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
use Gibbon\Forms\Form;
use Gibbon\Forms\DatabaseFormFactory;
use Gibbon\Module\InterventionPlans\Domain\InterventionPlanGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Intervention Plans'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/InterventionPlans/interventionPlans.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get gateway via DI container
    $interventionPlanGateway = $container->get(InterventionPlanGateway::class);

    // Get filter values from request
    $status = $_GET['status'] ?? '';
    $gibbonPersonID = $_GET['gibbonPersonID'] ?? '';
    $parentSigned = $_GET['parentSigned'] ?? '';
    $needsReview = $_GET['needsReview'] ?? '';

    // Get summary statistics
    $summary = $interventionPlanGateway->getPlanSummaryBySchoolYear($gibbonSchoolYearID);

    // Page header
    echo '<h2>' . __('Intervention Plans Overview') . '</h2>';

    // Dashboard grid layout - Summary Cards
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';

    // Total Plans Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Total Plans') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Total') . ':</span><span class="font-bold text-2xl">' . ($summary['totalPlans'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Children with Plans') . ':</span><span>' . ($summary['childrenWithPlans'] ?? 0) . '</span></div>';
    echo '</div>';
    echo '</div>';

    // Status Breakdown Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('By Status') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Active') . ':</span><span class="font-bold text-green-600">' . ($summary['activePlans'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Draft') . ':</span><span class="text-gray-500">' . ($summary['draftPlans'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Under Review') . ':</span><span class="text-orange-500">' . ($summary['underReviewPlans'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Completed') . ':</span><span class="text-blue-600">' . ($summary['completedPlans'] ?? 0) . '</span></div>';
    echo '</div>';
    echo '</div>';

    // Parent Signature Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Parent Signatures') . '</h3>';
    echo '<div class="space-y-2">';
    $signedPlans = $summary['signedPlans'] ?? 0;
    $totalPlans = $summary['totalPlans'] ?? 0;
    $unsignedPlans = $totalPlans - $signedPlans;
    echo '<div class="flex justify-between"><span>' . __('Signed') . ':</span><span class="font-bold text-green-600">' . $signedPlans . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Pending Signature') . ':</span><span class="text-orange-500">' . $unsignedPlans . '</span></div>';
    if ($totalPlans > 0) {
        $signedPercent = round(($signedPlans / $totalPlans) * 100);
        echo '<div class="flex justify-between"><span>' . __('Signed Rate') . ':</span><span>' . $signedPercent . '%</span></div>';
    }
    echo '</div>';
    echo '</div>';

    // Reviews Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Reviews') . '</h3>';
    echo '<div class="space-y-2">';
    $overduePlans = $summary['overduePlans'] ?? 0;
    if ($overduePlans > 0) {
        echo '<div class="flex justify-between"><span>' . __('Overdue Reviews') . ':</span><span class="font-bold text-red-600">' . $overduePlans . '</span></div>';
    } else {
        echo '<div class="flex justify-between"><span>' . __('Overdue Reviews') . ':</span><span class="text-green-600">0</span></div>';
    }
    echo '<div class="flex justify-between"><span>' . __('Archived') . ':</span><span class="text-gray-500">' . ($summary['archivedPlans'] ?? 0) . '</span></div>';
    echo '</div>';
    if ($overduePlans > 0) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans.php&needsReview=Y" class="block mt-3 text-red-600 hover:underline">' . __('View Overdue Plans') . ' &rarr;</a>';
    }
    echo '</div>';

    echo '</div>'; // End grid

    // Filter Form
    $form = Form::create('filters', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filter Plans'));
    $form->setClass('noIntBorder fullWidth');
    $form->setFactory(DatabaseFormFactory::create($pdo));

    $form->addHiddenValue('q', '/modules/InterventionPlans/interventionPlans.php');

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray([
                '' => __('All'),
                'Draft' => __('Draft'),
                'Active' => __('Active'),
                'Under Review' => __('Under Review'),
                'Completed' => __('Completed'),
                'Archived' => __('Archived'),
            ])
            ->selected($status);

    $row = $form->addRow();
        $row->addLabel('gibbonPersonID', __('Child'));
        $row->addSelectStudent('gibbonPersonID', $gibbonSchoolYearID, ['allStudents' => false, 'byName' => true, 'byRoll' => false])
            ->placeholder()
            ->selected($gibbonPersonID);

    $row = $form->addRow();
        $row->addLabel('parentSigned', __('Parent Signed'));
        $row->addSelect('parentSigned')
            ->fromArray([
                '' => __('All'),
                'Y' => __('Yes'),
                'N' => __('No'),
            ])
            ->selected($parentSigned);

    $row = $form->addRow();
        $row->addLabel('needsReview', __('Needs Review'));
        $row->addSelect('needsReview')
            ->fromArray([
                '' => __('All'),
                'Y' => __('Overdue for Review'),
            ])
            ->selected($needsReview);

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'), ['q' => '/modules/InterventionPlans/interventionPlans.php']);

    echo $form->getOutput();

    // Build query criteria
    $criteria = $interventionPlanGateway->newQueryCriteria(true)
        ->sortBy(['gibbonInterventionPlan.timestampModified'], 'DESC')
        ->filterBy('status', $status)
        ->filterBy('child', $gibbonPersonID)
        ->filterBy('parentSigned', $parentSigned)
        ->filterBy('needsReview', $needsReview)
        ->fromPOST();

    // Query intervention plans
    $plans = $interventionPlanGateway->queryInterventionPlans($criteria, $gibbonSchoolYearID);

    // Create data table
    $table = DataTable::createPaginated('interventionPlans', $criteria);

    $table->setTitle(__('Intervention Plans'));

    // Add action button for creating new plan
    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/InterventionPlans/interventionPlans_add.php')
        ->displayLabel();

    // Child column with photo
    $table->addColumn('child', __('Child'))
        ->sortable(['gibbonPerson.surname', 'gibbonPerson.preferredName'])
        ->format(function ($row) {
            $output = '';
            if (!empty($row['image_240'])) {
                $output .= '<img src="' . htmlspecialchars($row['image_240']) . '" class="w-8 h-8 rounded-full inline-block mr-2" alt="">';
            }
            $output .= Format::name('', $row['preferredName'], $row['surname'], 'Student', true);
            return $output;
        });

    // Title column
    $table->addColumn('title', __('Plan Title'))
        ->sortable()
        ->format(function ($row) use ($session) {
            return '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans_view.php&gibbonInterventionPlanID=' . $row['gibbonInterventionPlanID'] . '" class="text-blue-600 hover:underline">' . htmlspecialchars($row['title']) . '</a>';
        });

    // Status column with color coding
    $table->addColumn('status', __('Status'))
        ->sortable()
        ->format(function ($row) {
            $statusColors = [
                'Draft' => 'bg-gray-100 text-gray-800',
                'Active' => 'bg-green-100 text-green-800',
                'Under Review' => 'bg-orange-100 text-orange-800',
                'Completed' => 'bg-blue-100 text-blue-800',
                'Archived' => 'bg-gray-200 text-gray-600',
            ];
            $colorClass = $statusColors[$row['status']] ?? 'bg-gray-100 text-gray-800';
            return '<span class="px-2 py-1 rounded text-xs font-medium ' . $colorClass . '">' . __($row['status']) . '</span>';
        });

    // Version column
    $table->addColumn('version', __('Version'))
        ->sortable()
        ->format(function ($row) {
            return 'v' . ($row['version'] ?? 1);
        });

    // Next Review column
    $table->addColumn('nextReviewDate', __('Next Review'))
        ->sortable()
        ->format(function ($row) {
            if (empty($row['nextReviewDate'])) {
                return '<span class="text-gray-400">' . __('Not Set') . '</span>';
            }
            $reviewDate = new DateTime($row['nextReviewDate']);
            $today = new DateTime();
            $formatted = Format::date($row['nextReviewDate']);

            if ($reviewDate < $today) {
                return '<span class="text-red-600 font-bold">' . $formatted . '</span> <span class="text-xs text-red-500">(' . __('Overdue') . ')</span>';
            } elseif ($reviewDate <= $today->modify('+7 days')) {
                return '<span class="text-orange-600">' . $formatted . '</span>';
            }
            return $formatted;
        });

    // Parent Signed column
    $table->addColumn('parentSigned', __('Parent Signed'))
        ->sortable()
        ->format(function ($row) {
            if ($row['parentSigned'] == 'Y') {
                $signedDate = !empty($row['parentSignatureDate']) ? Format::date($row['parentSignatureDate']) : '';
                return '<span class="text-green-600">' . __('Yes') . '</span>' . ($signedDate ? '<br><span class="text-xs text-gray-500">' . $signedDate . '</span>' : '');
            }
            return '<span class="text-orange-500">' . __('Pending') . '</span>';
        });

    // Created By column
    $table->addColumn('createdBy', __('Created By'))
        ->format(function ($row) {
            if (!empty($row['createdByName']) && !empty($row['createdBySurname'])) {
                return Format::name('', $row['createdByName'], $row['createdBySurname'], 'Staff', false);
            }
            return '<span class="text-gray-400">' . __('Unknown') . '</span>';
        });

    // Last Modified column
    $table->addColumn('timestampModified', __('Last Modified'))
        ->sortable()
        ->format(function ($row) {
            return Format::dateTime($row['timestampModified']);
        });

    // Action buttons
    $table->addActionColumn()
        ->addParam('gibbonInterventionPlanID')
        ->format(function ($row, $actions) {
            $actions->addAction('view', __('View'))
                ->setURL('/modules/InterventionPlans/interventionPlans_view.php');

            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/InterventionPlans/interventionPlans_edit.php');

            $actions->addAction('progress', __('Add Progress'))
                ->setIcon('plus')
                ->setURL('/modules/InterventionPlans/interventionPlans_progress.php');
        });

    echo $table->render($plans);

    // Quick Action Buttons
    echo '<div class="mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Actions') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans_add.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Create New Plan') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans.php&needsReview=Y" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">' . __('View Plans Needing Review') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans.php&parentSigned=N" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">' . __('Pending Signatures') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/InterventionPlans/interventionPlans.php&status=Active" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Active Plans Only') . '</a>';
    echo '</div>';
    echo '</div>';
}
