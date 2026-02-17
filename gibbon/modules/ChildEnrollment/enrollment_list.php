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
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\ChildEnrollment\Domain\EnrollmentFormGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Enrollment Forms'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/ChildEnrollment/enrollment_list.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get gateway via DI container
    $enrollmentFormGateway = $container->get(EnrollmentFormGateway::class);

    // Get form statistics for the school year
    $stats = $enrollmentFormGateway->getFormStatsBySchoolYear($gibbonSchoolYearID);

    // Display statistics
    echo '<h2>' . __('Enrollment Form Summary') . '</h2>';
    echo '<div class="grid grid-cols-2 md:grid-cols-6 gap-4 mb-6">';

    // Draft
    echo '<div class="bg-gray-50 border border-gray-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-gray-600">' . ($stats['Draft'] ?? 0) . '</div>';
    echo '<div class="text-sm text-gray-700">' . __('Draft') . '</div>';
    echo '</div>';

    // Submitted
    echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-yellow-600">' . ($stats['Submitted'] ?? 0) . '</div>';
    echo '<div class="text-sm text-yellow-700">' . __('Submitted') . '</div>';
    echo '</div>';

    // Approved
    echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-green-600">' . ($stats['Approved'] ?? 0) . '</div>';
    echo '<div class="text-sm text-green-700">' . __('Approved') . '</div>';
    echo '</div>';

    // Rejected
    echo '<div class="bg-red-50 border border-red-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-red-600">' . ($stats['Rejected'] ?? 0) . '</div>';
    echo '<div class="text-sm text-red-700">' . __('Rejected') . '</div>';
    echo '</div>';

    // Expired
    echo '<div class="bg-orange-50 border border-orange-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-orange-600">' . ($stats['Expired'] ?? 0) . '</div>';
    echo '<div class="text-sm text-orange-700">' . __('Expired') . '</div>';
    echo '</div>';

    // Total
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-center">';
    echo '<div class="text-3xl font-bold text-blue-600">' . ($stats['Total'] ?? 0) . '</div>';
    echo '<div class="text-sm text-blue-700">' . __('Total') . '</div>';
    echo '</div>';

    echo '</div>';

    // Filter form
    $form = Form::create('filter', $session->get('absoluteURL') . '/index.php', 'get');
    $form->setTitle(__('Filter'));
    $form->setClass('noIntBorder fullWidth');

    $form->addHiddenValue('q', '/modules/ChildEnrollment/enrollment_list.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search'));
        $row->addTextField('search')
            ->setValue($_GET['search'] ?? '')
            ->placeholder(__('Child name or form number...'))
            ->maxLength(50);

    $row = $form->addRow();
        $row->addLabel('status', __('Status'));
        $row->addSelect('status')
            ->fromArray([
                '' => __('All'),
                'Draft' => __('Draft'),
                'Submitted' => __('Submitted'),
                'Approved' => __('Approved'),
                'Rejected' => __('Rejected'),
                'Expired' => __('Expired'),
            ])
            ->selected($_GET['status'] ?? '');

    $row = $form->addRow();
        $row->addSearchSubmit($session, __('Clear Filters'));

    echo $form->getOutput();

    // Build query criteria
    $criteria = $enrollmentFormGateway->newQueryCriteria(true)
        ->sortBy(['timestampCreated'], 'DESC')
        ->filterBy('status', $_GET['status'] ?? '')
        ->fromPOST();

    // Apply search filter if provided
    $search = $_GET['search'] ?? '';
    if (!empty($search)) {
        $criteria->filterBy('search', $search);
    }

    // Get enrollment forms
    $forms = $enrollmentFormGateway->queryForms($criteria, $gibbonSchoolYearID);

    // Create data table
    $table = DataTable::createPaginated('enrollmentForms', $criteria);
    $table->setTitle(__('Enrollment Forms'));

    // Add header actions
    $table->addHeaderAction('add', __('New Enrollment Form'))
        ->setURL('/modules/ChildEnrollment/enrollment_add.php')
        ->displayLabel();

    // Form Number column
    $table->addColumn('formNumber', __('Form #'))
        ->width('10%')
        ->format(function ($row) {
            return '<span class="font-mono text-sm">' . htmlspecialchars($row['formNumber']) . '</span>';
        });

    // Child column
    $table->addColumn('child', __('Child'))
        ->width('20%')
        ->format(function ($row) {
            $childName = trim($row['childFirstName'] . ' ' . $row['childLastName']);
            $dob = !empty($row['childDateOfBirth']) ? Format::date($row['childDateOfBirth']) : '';

            $output = '<strong>' . htmlspecialchars($childName) . '</strong>';
            if (!empty($dob)) {
                $output .= '<br><span class="text-xs text-gray-500">' . __('DOB') . ': ' . $dob . '</span>';
            }
            return $output;
        });

    // Status column
    $table->addColumn('status', __('Status'))
        ->width('10%')
        ->format(function ($row) {
            $statusClasses = [
                'Draft' => 'tag dull',
                'Submitted' => 'tag warning',
                'Approved' => 'tag success',
                'Rejected' => 'tag error',
                'Expired' => 'tag dull',
            ];
            $class = $statusClasses[$row['status']] ?? 'tag dull';
            return '<span class="' . $class . '">' . __($row['status']) . '</span>';
        });

    // Version column
    $table->addColumn('version', __('Version'))
        ->width('8%')
        ->format(function ($row) {
            return '<span class="tag dull">v' . $row['version'] . '</span>';
        });

    // Admission Date column
    $table->addColumn('admissionDate', __('Admission Date'))
        ->width('12%')
        ->format(function ($row) {
            return !empty($row['admissionDate']) ? Format::date($row['admissionDate']) : '-';
        });

    // Created By column
    $table->addColumn('createdBy', __('Created By'))
        ->width('15%')
        ->format(function ($row) {
            if (!empty($row['createdByName'])) {
                return Format::name('', $row['createdByName'], $row['createdBySurname'], 'Staff');
            }
            return '-';
        });

    // Created column
    $table->addColumn('timestampCreated', __('Created'))
        ->width('12%')
        ->format(function ($row) {
            return Format::dateTime($row['timestampCreated']);
        });

    // Last Modified column
    $table->addColumn('timestampModified', __('Modified'))
        ->width('12%')
        ->format(function ($row) {
            if (!empty($row['timestampModified']) && $row['timestampModified'] !== $row['timestampCreated']) {
                return Format::dateTime($row['timestampModified']);
            }
            return '-';
        });

    // Actions column
    $table->addActionColumn()
        ->addParam('gibbonChildEnrollmentFormID')
        ->format(function ($row, $actions) {
            // View action
            $actions->addAction('view', __('View'))
                ->setURL('/modules/ChildEnrollment/enrollment_view.php')
                ->setIcon('search');

            // Edit action (only for Draft or Rejected forms)
            if (in_array($row['status'], ['Draft', 'Rejected'])) {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/ChildEnrollment/enrollment_edit.php')
                    ->setIcon('config');
            }

            // PDF action (for all forms)
            $actions->addAction('pdf', __('Download PDF'))
                ->setURL('/modules/ChildEnrollment/enrollment_pdf.php')
                ->setIcon('download')
                ->directLink();

            // Approve/Reject actions (only for Submitted forms)
            if ($row['status'] === 'Submitted') {
                $actions->addAction('approve', __('Approve'))
                    ->setURL('/modules/ChildEnrollment/enrollment_approveProcess.php')
                    ->setIcon('iconTick')
                    ->directLink()
                    ->addConfirmation(__('Are you sure you want to approve this enrollment form?'));

                $actions->addAction('reject', __('Reject'))
                    ->setURL('/modules/ChildEnrollment/enrollment_rejectProcess.php')
                    ->setIcon('iconCross')
                    ->directLink()
                    ->addConfirmation(__('Are you sure you want to reject this enrollment form?'));
            }

            // Delete action (only for Draft forms)
            if ($row['status'] === 'Draft') {
                $actions->addAction('delete', __('Delete'))
                    ->setURL('/modules/ChildEnrollment/enrollment_deleteProcess.php')
                    ->setIcon('garbage')
                    ->directLink()
                    ->addConfirmation(__('Are you sure you want to delete this enrollment form? This action cannot be undone.'));
            }
        });

    echo $table->render($forms);

    // Help message
    echo '<div class="message">';
    echo '<h4>' . __('About Enrollment Forms') . '</h4>';
    echo '<p>' . __('Enrollment forms (Fiche d\'Inscription) capture all required information for child enrollment in accordance with Quebec regulations. Each form includes:') . '</p>';
    echo '<ul class="list-disc ml-6 mt-2">';
    echo '<li>' . __('Child identification and contact information') . '</li>';
    echo '<li>' . __('Parent/guardian information') . '</li>';
    echo '<li>' . __('Authorized pickup persons') . '</li>';
    echo '<li>' . __('Emergency contacts') . '</li>';
    echo '<li>' . __('Health and medical information') . '</li>';
    echo '<li>' . __('Nutrition and dietary requirements') . '</li>';
    echo '<li>' . __('Weekly attendance schedule') . '</li>';
    echo '<li>' . __('E-signatures from parents and director') . '</li>';
    echo '</ul>';
    echo '</div>';
}
