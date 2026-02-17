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
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\RL24Submission\Domain\RL24EligibilityGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('FO-0601 Eligibility Forms'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/RL24Submission/rl24_eligibility.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get filter values from request
    $formYear = $_GET['formYear'] ?? date('Y');
    $approvalStatus = $_GET['approvalStatus'] ?? '';

    // Validate form year (must be 4-digit year)
    if (!preg_match('/^\d{4}$/', $formYear)) {
        $formYear = date('Y');
    }

    // Get eligibility gateway via DI container
    $eligibilityGateway = $container->get(RL24EligibilityGateway::class);

    // Get summary statistics for the school year
    $summary = $eligibilityGateway->getEligibilitySummaryBySchoolYear($gibbonSchoolYearID);
    $statusCounts = $eligibilityGateway->getStatusCounts($gibbonSchoolYearID);

    // Page header with year selector
    echo '<h2>' . __('FO-0601 Eligibility Forms') . '</h2>';

    // Filter form
    $form = Form::create('eligibilityFilter', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setFactory(DatabaseFormFactory::create($pdo));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/RL24Submission/rl24_eligibility.php');

    $row = $form->addRow();
        $row->addLabel('formYear', __('Tax Year'));
        $col = $row->addColumn()->addClass('inline');

        // Generate year options (current year and 5 years back)
        $currentYear = (int) date('Y');
        $yearOptions = [];
        for ($y = $currentYear; $y >= $currentYear - 5; $y--) {
            $yearOptions[$y] = $y;
        }
        $col->addSelect('formYear')
            ->fromArray($yearOptions)
            ->selected($formYear)
            ->setClass('standardWidth');

    $row = $form->addRow();
        $row->addLabel('approvalStatus', __('Status'));
        $col = $row->addColumn()->addClass('inline');
        $col->addSelect('approvalStatus')
            ->fromArray([
                '' => __('All'),
                'Pending' => __('Pending'),
                'Approved' => __('Approved'),
                'Rejected' => __('Rejected'),
                'Incomplete' => __('Incomplete'),
            ])
            ->selected($approvalStatus)
            ->setClass('standardWidth');

    $row = $form->addRow();
        $row->addFooter();
        $row->addSearchSubmit($session);

    echo $form->getOutput();

    // Display summary dashboard
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';

    // Total Forms Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Total Forms') . '</h3>';
    echo '<div class="text-center">';
    echo '<span class="text-4xl font-bold text-blue-600">' . ($summary['totalForms'] ?? 0) . '</span>';
    echo '</div>';
    echo '</div>';

    // Pending Review Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Pending Review') . '</h3>';
    echo '<div class="text-center">';
    $pendingCount = $statusCounts['Pending'] ?? 0;
    $pendingClass = $pendingCount > 0 ? 'text-orange-500' : 'text-gray-400';
    echo '<span class="text-4xl font-bold ' . $pendingClass . '">' . $pendingCount . '</span>';
    echo '</div>';
    if ($pendingCount > 0) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_eligibility.php&formYear=' . $formYear . '&approvalStatus=Pending" class="block mt-2 text-sm text-blue-600 hover:underline text-center">' . __('View Pending') . ' &rarr;</a>';
    }
    echo '</div>';

    // Approved Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Approved') . '</h3>';
    echo '<div class="text-center">';
    $approvedCount = $statusCounts['Approved'] ?? 0;
    $approvedClass = $approvedCount > 0 ? 'text-green-600' : 'text-gray-400';
    echo '<span class="text-4xl font-bold ' . $approvedClass . '">' . $approvedCount . '</span>';
    echo '</div>';
    if ($approvedCount > 0) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_eligibility.php&formYear=' . $formYear . '&approvalStatus=Approved" class="block mt-2 text-sm text-blue-600 hover:underline text-center">' . __('View Approved') . ' &rarr;</a>';
    }
    echo '</div>';

    // Documents Status Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Documents Complete') . '</h3>';
    echo '<div class="text-center">';
    $docsComplete = $summary['documentsCompleteCount'] ?? 0;
    $totalForms = $summary['totalForms'] ?? 0;
    $docsClass = $docsComplete === $totalForms && $totalForms > 0 ? 'text-green-600' : 'text-purple-600';
    echo '<span class="text-4xl font-bold ' . $docsClass . '">' . $docsComplete . '/' . $totalForms . '</span>';
    echo '</div>';
    echo '</div>';

    echo '</div>';

    // Add New Form Button
    echo '<div class="mb-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/RL24Submission/rl24_eligibility_add.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">';
    echo '<svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>';
    echo __('Add New Eligibility Form');
    echo '</a>';
    echo '</div>';

    // Build query criteria
    $criteria = $eligibilityGateway->newQueryCriteria()
        ->sortBy(['timestampCreated'], 'DESC')
        ->filterBy('formYear', $formYear)
        ->fromPOST();

    if (!empty($approvalStatus)) {
        $criteria->filterBy('approvalStatus', $approvalStatus);
    }

    // Query eligibility forms
    $eligibilityForms = $eligibilityGateway->queryEligibility($criteria, $gibbonSchoolYearID);

    // Create data table
    $table = DataTable::createPaginated('eligibilityForms', $criteria);

    $table->setTitle(__('Eligibility Forms for Tax Year') . ' ' . $formYear);

    // Add columns
    $table->addColumn('childName', __('Child'))
        ->format(function ($row) {
            $output = '<div class="flex items-center">';
            if (!empty($row['childImage'])) {
                $output .= '<img class="w-8 h-8 rounded-full mr-2" src="' . htmlspecialchars($row['childImage']) . '" alt="">';
            }
            $output .= '<div>';
            $output .= '<div class="font-medium">' . htmlspecialchars($row['childFirstName'] . ' ' . $row['childLastName']) . '</div>';
            if (!empty($row['childDateOfBirth'])) {
                $output .= '<div class="text-xs text-gray-500">' . __('DOB') . ': ' . Format::date($row['childDateOfBirth']) . '</div>';
            }
            $output .= '</div></div>';
            return $output;
        });

    $table->addColumn('parentName', __('Parent/Guardian'))
        ->format(function ($row) {
            return htmlspecialchars($row['parentFirstName'] . ' ' . $row['parentLastName']);
        });

    $table->addColumn('servicePeriod', __('Service Period'))
        ->format(function ($row) {
            if (empty($row['servicePeriodStart']) || empty($row['servicePeriodEnd'])) {
                return '<span class="text-gray-400">' . __('Not set') . '</span>';
            }
            return Format::date($row['servicePeriodStart']) . ' - ' . Format::date($row['servicePeriodEnd']);
        });

    $table->addColumn('approvalStatus', __('Status'))
        ->format(function ($row) {
            $statusColors = [
                'Pending' => 'bg-yellow-100 text-yellow-800',
                'Approved' => 'bg-green-100 text-green-800',
                'Rejected' => 'bg-red-100 text-red-800',
                'Incomplete' => 'bg-gray-100 text-gray-800',
            ];
            $color = $statusColors[$row['approvalStatus']] ?? 'bg-gray-100 text-gray-800';
            return '<span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ' . $color . '">' . __($row['approvalStatus']) . '</span>';
        });

    $table->addColumn('documentsComplete', __('Docs'))
        ->format(function ($row) {
            if ($row['documentsComplete'] === 'Y') {
                return '<span class="text-green-600" title="' . __('Documents Complete') . '">&#10004;</span>';
            }
            return '<span class="text-red-500" title="' . __('Documents Incomplete') . '">&#10008;</span>';
        });

    $table->addColumn('signatureConfirmed', __('Signed'))
        ->format(function ($row) {
            if ($row['signatureConfirmed'] === 'Y') {
                return '<span class="text-green-600" title="' . __('Signature Confirmed') . '">&#10004;</span>';
            }
            return '<span class="text-red-500" title="' . __('Signature Pending') . '">&#10008;</span>';
        });

    $table->addColumn('timestampCreated', __('Created'))
        ->format(function ($row) {
            return Format::dateTime($row['timestampCreated']);
        });

    // Action buttons
    $table->addActionColumn()
        ->addParam('gibbonRL24EligibilityID')
        ->format(function ($row, $actions) use ($session) {
            // View/Edit action
            $actions->addAction('edit', __('Edit'))
                ->setURL('/modules/RL24Submission/rl24_eligibility_edit.php');

            // Documents action
            $actions->addAction('view', __('Documents'))
                ->setIcon('upload')
                ->setURL('/modules/RL24Submission/rl24_eligibility_documents.php');

            // Delete action (only for Pending/Incomplete)
            if (in_array($row['approvalStatus'], ['Pending', 'Incomplete'])) {
                $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/RL24Submission/rl24_eligibility_delete.php');
            }
        });

    echo $table->render($eligibilityForms);

    // Show helpful information
    echo '<div class="mt-6 p-4 bg-blue-50 rounded-lg">';
    echo '<h4 class="font-semibold mb-2">' . __('About FO-0601 Eligibility Forms') . '</h4>';
    echo '<p class="text-sm text-gray-600 mb-2">' . __('The FO-0601 form is required by Revenu Quebec to establish eligibility for childcare expense tax credits. Parents must complete this form to confirm their eligibility status.') . '</p>';
    echo '<ul class="text-sm text-gray-600 list-disc list-inside">';
    echo '<li>' . __('All forms must be approved before generating RL-24 slips.') . '</li>';
    echo '<li>' . __('Required supporting documents must be uploaded and verified.') . '</li>';
    echo '<li>' . __('Parent signature confirmation is required for form completion.') . '</li>';
    echo '</ul>';
    echo '</div>';
}
