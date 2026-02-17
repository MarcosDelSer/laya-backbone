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
use Gibbon\Domain\QueryCriteria;
use Gibbon\Module\ServiceAgreement\Domain\ServiceAgreementGateway;
use Gibbon\Module\ServiceAgreement\Domain\AnnexGateway;
use Gibbon\Module\ServiceAgreement\Domain\SignatureGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Service Agreements'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/ServiceAgreement/serviceAgreement.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get filter status from request
    $statusFilter = $_GET['status'] ?? '';

    // Get gateways via DI container
    $serviceAgreementGateway = $container->get(ServiceAgreementGateway::class);

    // Get summary statistics for the school year
    $statusCounts = $serviceAgreementGateway->countAgreementsByStatus($gibbonSchoolYearID);
    $expiringAgreements = $serviceAgreementGateway->selectExpiringAgreements($gibbonSchoolYearID, 30)->fetchAll();
    $childrenWithoutAgreement = $serviceAgreementGateway->selectChildrenWithoutAgreement($gibbonSchoolYearID)->fetchAll();

    // Page header
    echo '<h2>' . __('Service Agreement Dashboard') . '</h2>';

    // Show information about Quebec FO-0659 compliance
    echo '<p class="text-gray-600 mb-4">';
    echo __('Manage Quebec FO-0659 Service Agreements (Entente de Services) between childcare providers and parents. All agreements comply with Quebec Consumer Protection Act requirements.');
    echo '</p>';

    // Dashboard grid layout - Status Summary Cards
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';

    // Active Agreements Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Active') . '</h3>';
    echo '<div class="text-3xl font-bold text-green-600">' . ($statusCounts['activeCount'] ?? 0) . '</div>';
    echo '<p class="text-gray-500 text-sm">' . __('Fully signed agreements') . '</p>';
    if (($statusCounts['activeCount'] ?? 0) > 0) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement.php&status=Active" class="block mt-3 text-blue-600 hover:underline text-sm">' . __('View Active') . ' &rarr;</a>';
    }
    echo '</div>';

    // Pending Signature Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Pending Signature') . '</h3>';
    echo '<div class="text-3xl font-bold text-orange-500">' . ($statusCounts['pendingCount'] ?? 0) . '</div>';
    echo '<p class="text-gray-500 text-sm">' . __('Awaiting parent signature') . '</p>';
    if (($statusCounts['pendingCount'] ?? 0) > 0) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement.php&status=Pending+Signature" class="block mt-3 text-blue-600 hover:underline text-sm">' . __('View Pending') . ' &rarr;</a>';
    }
    echo '</div>';

    // Draft Agreements Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Draft') . '</h3>';
    echo '<div class="text-3xl font-bold text-gray-500">' . ($statusCounts['draftCount'] ?? 0) . '</div>';
    echo '<p class="text-gray-500 text-sm">' . __('Work in progress') . '</p>';
    if (($statusCounts['draftCount'] ?? 0) > 0) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement.php&status=Draft" class="block mt-3 text-blue-600 hover:underline text-sm">' . __('View Drafts') . ' &rarr;</a>';
    }
    echo '</div>';

    // Expired/Terminated Card
    $expiredTerminatedCount = ($statusCounts['expiredCount'] ?? 0) + ($statusCounts['terminatedCount'] ?? 0) + ($statusCounts['cancelledCount'] ?? 0);
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Expired/Terminated') . '</h3>';
    echo '<div class="text-3xl font-bold text-red-500">' . $expiredTerminatedCount . '</div>';
    echo '<p class="text-gray-500 text-sm">' . __('No longer active') . '</p>';
    if ($expiredTerminatedCount > 0) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement.php&status=Expired" class="block mt-3 text-blue-600 hover:underline text-sm">' . __('View Expired') . ' &rarr;</a>';
    }
    echo '</div>';

    echo '</div>'; // End status cards grid

    // Alert Sections
    echo '<div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-6">';

    // Expiring Soon Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Expiring Soon') . ' <span class="text-sm font-normal text-gray-500">' . __('(Next 30 days)') . '</span></h3>';
    if (!empty($expiringAgreements)) {
        echo '<div class="space-y-2 max-h-48 overflow-y-auto">';
        foreach ($expiringAgreements as $agreement) {
            $daysRemaining = $agreement['daysRemaining'] ?? 0;
            $urgencyClass = $daysRemaining <= 7 ? 'text-red-600' : ($daysRemaining <= 14 ? 'text-orange-500' : 'text-yellow-600');
            $childName = Format::name('', $agreement['childPreferredName'], $agreement['childSurname'], 'Student');
            echo '<div class="flex justify-between items-center py-1 border-b border-gray-100">';
            echo '<span>' . htmlspecialchars($childName) . '</span>';
            echo '<span class="' . $urgencyClass . ' font-medium">' . sprintf(__('%d days'), $daysRemaining) . '</span>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p class="text-green-600">' . __('No agreements expiring in the next 30 days.') . '</p>';
    }
    echo '</div>';

    // Children Without Agreement Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Children Without Agreement') . '</h3>';
    $childrenWithoutCount = count($childrenWithoutAgreement);
    if ($childrenWithoutCount > 0) {
        echo '<p class="text-orange-500 mb-2">' . sprintf(__('%d children need service agreements'), $childrenWithoutCount) . '</p>';
        echo '<div class="space-y-2 max-h-48 overflow-y-auto">';
        $displayCount = min(5, $childrenWithoutCount);
        for ($i = 0; $i < $displayCount; $i++) {
            $child = $childrenWithoutAgreement[$i];
            $childName = Format::name('', $child['preferredName'], $child['surname'], 'Student');
            $formGroup = $child['formGroupName'] ?? '';
            echo '<div class="flex justify-between items-center py-1 border-b border-gray-100">';
            echo '<span>' . htmlspecialchars($childName) . '</span>';
            echo '<span class="text-gray-500 text-sm">' . htmlspecialchars($formGroup) . '</span>';
            echo '</div>';
        }
        if ($childrenWithoutCount > 5) {
            echo '<p class="text-gray-500 text-sm mt-2">' . sprintf(__('And %d more...'), $childrenWithoutCount - 5) . '</p>';
        }
        echo '</div>';
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_add.php" class="block mt-3 text-blue-600 hover:underline text-sm">' . __('Create New Agreement') . ' &rarr;</a>';
    } else {
        echo '<p class="text-green-600">' . __('All enrolled children have service agreements.') . '</p>';
    }
    echo '</div>';

    echo '</div>'; // End alert cards grid

    // Quick Action Buttons
    echo '<div class="mb-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Actions') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_add.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Create New Agreement') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement.php&status=Pending+Signature" class="bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">' . __('Review Pending') . '</a>';
    echo '</div>';
    echo '</div>';

    // Service Agreements Table
    echo '<h3 class="text-lg font-semibold mb-3">' . __('All Service Agreements') . '</h3>';

    // Status filter form
    echo '<div class="mb-4">';
    echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php" class="inline">';
    echo '<input type="hidden" name="q" value="/modules/ServiceAgreement/serviceAgreement.php">';
    echo '<label class="mr-2">' . __('Filter by Status') . ':</label>';
    echo '<select name="status" class="standardWidth" onchange="this.form.submit()">';
    echo '<option value="">' . __('All Statuses') . '</option>';
    $statuses = ['Draft', 'Pending Signature', 'Active', 'Expired', 'Terminated', 'Cancelled'];
    foreach ($statuses as $status) {
        $selected = ($statusFilter === $status) ? ' selected' : '';
        echo '<option value="' . htmlspecialchars($status) . '"' . $selected . '>' . __($status) . '</option>';
    }
    echo '</select>';
    echo '<button type="submit" class="ml-2">' . __('Go') . '</button>';
    if (!empty($statusFilter)) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement.php" class="ml-4">' . __('Clear Filter') . '</a>';
    }
    echo '</form>';
    echo '</div>';

    // Query service agreements with criteria
    $criteria = $serviceAgreementGateway->newQueryCriteria(true)
        ->sortBy(['timestampCreated'], 'DESC')
        ->pageSize(25)
        ->fromPOST();

    // Apply status filter if set
    if (!empty($statusFilter)) {
        $criteria->filterBy('status', $statusFilter);
    }

    $agreements = $serviceAgreementGateway->queryServiceAgreements($criteria, $gibbonSchoolYearID);

    // Create data table
    $table = DataTable::createPaginated('serviceAgreements', $criteria);

    $table->addHeaderAction('add', __('Add'))
        ->setURL('/modules/ServiceAgreement/serviceAgreement_add.php')
        ->displayLabel();

    // Agreement Number column
    $table->addColumn('agreementNumber', __('Agreement #'))
        ->sortable(['agreementNumber'])
        ->format(function ($row) use ($session) {
            $url = $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_view.php&gibbonServiceAgreementID=' . $row['gibbonServiceAgreementID'];
            return '<a href="' . $url . '" class="text-blue-600 hover:underline">' . htmlspecialchars($row['agreementNumber']) . '</a>';
        });

    // Child Name column
    $table->addColumn('childName', __('Child'))
        ->sortable(['childSurname', 'childPreferredName'])
        ->format(function ($row) {
            return Format::name('', $row['childPreferredName'], $row['childSurname'], 'Student');
        });

    // Parent Name column
    $table->addColumn('parentName', __('Parent'))
        ->sortable(['parentSurname', 'parentPreferredName'])
        ->format(function ($row) {
            return Format::name('', $row['parentPreferredName'], $row['parentSurname'], 'Parent');
        });

    // Status column with color coding
    $table->addColumn('status', __('Status'))
        ->sortable(['status'])
        ->format(function ($row) {
            $status = $row['status'];
            $classes = [
                'Draft' => 'bg-gray-200 text-gray-800',
                'Pending Signature' => 'bg-orange-200 text-orange-800',
                'Active' => 'bg-green-200 text-green-800',
                'Expired' => 'bg-red-200 text-red-800',
                'Terminated' => 'bg-red-200 text-red-800',
                'Cancelled' => 'bg-gray-300 text-gray-600',
            ];
            $class = $classes[$status] ?? 'bg-gray-200 text-gray-800';
            return '<span class="px-2 py-1 rounded text-sm ' . $class . '">' . __($status) . '</span>';
        });

    // Contribution Type column
    $table->addColumn('contributionType', __('Contribution'))
        ->sortable(['contributionType'])
        ->format(function ($row) {
            $type = $row['contributionType'];
            if ($type === 'Reduced') {
                return __('Reduced') . ' ($' . number_format($row['dailyReducedContribution'], 2) . '/day)';
            }
            return __($type);
        });

    // Effective Date column
    $table->addColumn('effectiveDate', __('Effective Date'))
        ->sortable(['effectiveDate'])
        ->format(function ($row) {
            return !empty($row['effectiveDate']) ? Format::date($row['effectiveDate']) : '-';
        });

    // Signatures column
    $table->addColumn('allSignaturesComplete', __('Signed'))
        ->format(function ($row) {
            if ($row['allSignaturesComplete'] === 'Y') {
                return '<span class="text-green-600">&#10003; ' . __('Complete') . '</span>';
            }
            return '<span class="text-orange-500">' . __('Pending') . '</span>';
        });

    // Actions column
    $table->addActionColumn()
        ->addParam('gibbonServiceAgreementID')
        ->format(function ($row, $actions) {
            $actions->addAction('view', __('View'))
                ->setURL('/modules/ServiceAgreement/serviceAgreement_view.php');

            if ($row['status'] === 'Draft') {
                $actions->addAction('edit', __('Edit'))
                    ->setURL('/modules/ServiceAgreement/serviceAgreement_edit.php');
            }

            if ($row['allSignaturesComplete'] === 'Y') {
                $actions->addAction('pdf', __('Download PDF'))
                    ->setIcon('print')
                    ->setURL('/modules/ServiceAgreement/serviceAgreement_pdf.php')
                    ->directLink();
            }
        });

    echo $table->render($agreements);
}
