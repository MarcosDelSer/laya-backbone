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
use Gibbon\Module\GovernmentDocuments\Domain\GovernmentDocumentGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Government Documents'), 'governmentDocuments.php');
$page->breadcrumbs->add(__('Compliance Checklist'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/GovernmentDocuments/governmentDocuments_checklist.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get gateway via DI container
    $documentGateway = $container->get(GovernmentDocumentGateway::class);

    // Compliance filter options
    $complianceOptions = [
        ''            => __('All Families'),
        'compliant'   => __('Fully Compliant'),
        'partial'     => __('Partially Compliant'),
        'attention'   => __('Needs Attention'),
    ];

    // Get filter from request
    $complianceFilter = $_GET['compliance'] ?? '';
    $searchFilter = $_GET['search'] ?? '';

    // Page header
    echo '<h2>' . __('Family Document Compliance Checklist') . '</h2>';
    echo '<p class="text-gray-600 mb-4">' . __('Director view showing document compliance status for all families with enrolled students.') . '</p>';

    // Get overall statistics
    $statistics = $documentGateway->getDocumentStatistics($gibbonSchoolYearID);
    $complianceRate = $documentGateway->getComplianceRate($gibbonSchoolYearID);

    // Overall Summary Cards
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';

    // Compliance Rate Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Overall Compliance') . '</h3>';
    $complianceColor = $complianceRate >= 80 ? 'text-green-600' : ($complianceRate >= 60 ? 'text-yellow-600' : 'text-red-600');
    echo '<div class="text-3xl font-bold ' . $complianceColor . '">' . $complianceRate . '%</div>';
    echo '<p class="text-gray-500 text-sm">' . __('Documents verified across all families') . '</p>';
    echo '</div>';

    // Total Documents Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Total Documents') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Verified') . ':</span><span class="font-bold text-green-600">' . ($statistics['verifiedCount'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Pending') . ':</span><span class="font-bold text-yellow-600">' . ($statistics['pendingCount'] ?? 0) . '</span></div>';
    echo '</div>';
    echo '</div>';

    // Attention Required Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Attention Required') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Expired') . ':</span><span class="font-bold text-red-600">' . ($statistics['expiredCount'] ?? 0) . '</span></div>';
    echo '<div class="flex justify-between"><span>' . __('Rejected') . ':</span><span class="font-bold text-red-500">' . ($statistics['rejectedCount'] ?? 0) . '</span></div>';
    echo '</div>';
    echo '</div>';

    // Expiring Soon Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Expiring Soon') . '</h3>';
    echo '<div class="text-3xl font-bold text-orange-500">' . ($statistics['expiringSoonCount'] ?? 0) . '</div>';
    echo '<p class="text-gray-500 text-sm">' . __('Documents expiring within 30 days') . '</p>';
    echo '</div>';

    echo '</div>'; // End statistics grid

    // Filter form
    $form = Form::create('checklistFilter', $session->get('absoluteURL') . '/index.php');
    $form->setMethod('get');
    $form->addHiddenValue('q', '/modules/GovernmentDocuments/governmentDocuments_checklist.php');

    $row = $form->addRow();
        $row->addLabel('search', __('Search'));
        $row->addTextField('search')
            ->placeholder(__('Family name...'))
            ->setValue($searchFilter);

    $row = $form->addRow();
        $row->addLabel('compliance', __('Compliance Status'));
        $row->addSelect('compliance')
            ->fromArray($complianceOptions)
            ->selected($complianceFilter);

    $row = $form->addRow();
        $row->addSubmit(__('Filter'));

    echo $form->getOutput();

    // Get all families' compliance summary
    $familiesData = $documentGateway->selectChecklistSummaryAllFamilies($gibbonSchoolYearID);

    // Get required document types count for compliance calculation
    $documentTypes = $documentGateway->selectActiveDocumentTypes();
    $requiredTypesCount = 0;
    foreach ($documentTypes as $type) {
        if ($type['required'] === 'Y') {
            $requiredTypesCount++;
        }
    }

    // Process and filter families
    $processedFamilies = [];
    foreach ($familiesData as $family) {
        $verifiedCount = (int) $family['verifiedCount'];
        $pendingCount = (int) $family['pendingCount'];
        $rejectedCount = (int) $family['rejectedCount'];
        $expiredCount = (int) $family['expiredCount'];

        $totalDocuments = $verifiedCount + $pendingCount + $rejectedCount + $expiredCount;
        $attentionCount = $rejectedCount + $expiredCount;

        // Determine compliance status
        $complianceStatus = 'partial';
        if ($attentionCount > 0) {
            $complianceStatus = 'attention';
        } elseif ($verifiedCount > 0 && $pendingCount === 0 && $attentionCount === 0) {
            $complianceStatus = 'compliant';
        }

        // Calculate family compliance percentage
        $familyComplianceRate = 0;
        if ($totalDocuments > 0) {
            $familyComplianceRate = round(($verifiedCount / $totalDocuments) * 100, 1);
        }

        // Apply filters
        if (!empty($searchFilter) && stripos($family['familyName'], $searchFilter) === false) {
            continue;
        }

        if (!empty($complianceFilter) && $complianceStatus !== $complianceFilter) {
            continue;
        }

        $processedFamilies[] = [
            'gibbonFamilyID' => $family['gibbonFamilyID'],
            'familyName' => $family['familyName'],
            'verifiedCount' => $verifiedCount,
            'pendingCount' => $pendingCount,
            'rejectedCount' => $rejectedCount,
            'expiredCount' => $expiredCount,
            'totalDocuments' => $totalDocuments,
            'attentionCount' => $attentionCount,
            'complianceStatus' => $complianceStatus,
            'complianceRate' => $familyComplianceRate,
        ];
    }

    // Count families by status for summary
    $familyStatusCounts = [
        'compliant' => 0,
        'partial' => 0,
        'attention' => 0,
    ];
    foreach ($processedFamilies as $family) {
        $familyStatusCounts[$family['complianceStatus']]++;
    }

    // Family Status Summary
    echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Family Compliance Overview') . '</h3>';
    echo '<div class="grid grid-cols-3 gap-4 text-center">';

    echo '<div class="bg-green-100 rounded p-3">';
    echo '<div class="text-2xl font-bold text-green-600">' . $familyStatusCounts['compliant'] . '</div>';
    echo '<div class="text-sm text-gray-600">' . __('Fully Compliant') . '</div>';
    echo '</div>';

    echo '<div class="bg-yellow-100 rounded p-3">';
    echo '<div class="text-2xl font-bold text-yellow-600">' . $familyStatusCounts['partial'] . '</div>';
    echo '<div class="text-sm text-gray-600">' . __('Partially Compliant') . '</div>';
    echo '</div>';

    echo '<div class="bg-red-100 rounded p-3">';
    echo '<div class="text-2xl font-bold text-red-600">' . $familyStatusCounts['attention'] . '</div>';
    echo '<div class="text-sm text-gray-600">' . __('Needs Attention') . '</div>';
    echo '</div>';

    echo '</div>';
    echo '</div>';

    // Build and render the families table
    if (count($processedFamilies) > 0) {
        echo '<h3 class="text-lg font-semibold mb-3">' . __('All Families') . '</h3>';

        echo '<table class="w-full bg-white rounded-lg shadow overflow-hidden">';
        echo '<thead class="bg-gray-50">';
        echo '<tr>';
        echo '<th class="px-4 py-3 text-left text-sm font-medium text-gray-700">' . __('Family Name') . '</th>';
        echo '<th class="px-4 py-3 text-center text-sm font-medium text-gray-700">' . __('Status') . '</th>';
        echo '<th class="px-4 py-3 text-center text-sm font-medium text-gray-700">' . __('Compliance') . '</th>';
        echo '<th class="px-4 py-3 text-center text-sm font-medium text-gray-700">' . __('Verified') . '</th>';
        echo '<th class="px-4 py-3 text-center text-sm font-medium text-gray-700">' . __('Pending') . '</th>';
        echo '<th class="px-4 py-3 text-center text-sm font-medium text-gray-700">' . __('Attention') . '</th>';
        echo '<th class="px-4 py-3 text-center text-sm font-medium text-gray-700">' . __('Actions') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="divide-y divide-gray-200">';

        foreach ($processedFamilies as $family) {
            // Determine status badge colors
            $statusBadge = '';
            switch ($family['complianceStatus']) {
                case 'compliant':
                    $statusBadge = '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">' . __('Compliant') . '</span>';
                    break;
                case 'partial':
                    $statusBadge = '<span class="bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">' . __('Partial') . '</span>';
                    break;
                case 'attention':
                    $statusBadge = '<span class="bg-red-100 text-red-800 text-xs px-2 py-1 rounded">' . __('Needs Attention') . '</span>';
                    break;
            }

            // Compliance progress bar color
            $progressColor = $family['complianceRate'] >= 80 ? 'bg-green-500' : ($family['complianceRate'] >= 50 ? 'bg-yellow-500' : 'bg-red-500');

            echo '<tr class="hover:bg-gray-50">';

            // Family Name
            echo '<td class="px-4 py-3">';
            echo '<div class="font-medium">' . htmlspecialchars($family['familyName']) . '</div>';
            echo '</td>';

            // Status Badge
            echo '<td class="px-4 py-3 text-center">' . $statusBadge . '</td>';

            // Compliance Rate with progress bar
            echo '<td class="px-4 py-3">';
            echo '<div class="flex items-center">';
            echo '<div class="flex-1 h-2 bg-gray-200 rounded mr-2">';
            echo '<div class="h-2 ' . $progressColor . ' rounded" style="width: ' . $family['complianceRate'] . '%"></div>';
            echo '</div>';
            echo '<span class="text-sm font-medium">' . $family['complianceRate'] . '%</span>';
            echo '</div>';
            echo '</td>';

            // Verified Count
            echo '<td class="px-4 py-3 text-center">';
            if ($family['verifiedCount'] > 0) {
                echo '<span class="text-green-600 font-medium">' . $family['verifiedCount'] . '</span>';
            } else {
                echo '<span class="text-gray-400">0</span>';
            }
            echo '</td>';

            // Pending Count
            echo '<td class="px-4 py-3 text-center">';
            if ($family['pendingCount'] > 0) {
                echo '<span class="text-yellow-600 font-medium">' . $family['pendingCount'] . '</span>';
            } else {
                echo '<span class="text-gray-400">0</span>';
            }
            echo '</td>';

            // Attention Count (rejected + expired)
            echo '<td class="px-4 py-3 text-center">';
            if ($family['attentionCount'] > 0) {
                echo '<span class="text-red-600 font-bold">' . $family['attentionCount'] . '</span>';
            } else {
                echo '<span class="text-gray-400">0</span>';
            }
            echo '</td>';

            // Actions
            echo '<td class="px-4 py-3 text-center">';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments.php&gibbonFamilyID=' . $family['gibbonFamilyID'] . '" class="inline-flex items-center text-blue-600 hover:underline text-sm">';
            echo __('View Details') . ' &rarr;';
            echo '</a>';
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

    } else {
        echo '<div class="bg-gray-50 rounded-lg p-4 text-center text-gray-500">';
        if (!empty($searchFilter) || !empty($complianceFilter)) {
            echo __('No families found matching the selected filters.');
        } else {
            echo __('No families with enrolled students found for this school year.');
        }
        echo '</div>';
    }

    // Export/Print Actions
    echo '<div class="mt-6 flex flex-wrap gap-2">';
    echo '<h3 class="w-full text-lg font-semibold mb-2">' . __('Actions') . '</h3>';

    // Print button
    echo '<button onclick="window.print()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">';
    echo __('Print Checklist');
    echo '</button>';

    // Link to families needing attention
    if ($familyStatusCounts['attention'] > 0) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_checklist.php&compliance=attention" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">';
        echo __('View Families Needing Attention') . ' (' . $familyStatusCounts['attention'] . ')';
        echo '</a>';
    }

    // Link to verify pending documents
    if (($statistics['pendingCount'] ?? 0) > 0) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_verify.php" class="bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600">';
        echo __('Review Pending Documents') . ' (' . ($statistics['pendingCount'] ?? 0) . ')';
        echo '</a>';
    }

    echo '</div>';

    // Expiring Documents Section
    $expiringDocuments = $documentGateway->selectExpiringDocuments($gibbonSchoolYearID, 30);
    if (!empty($expiringDocuments)) {
        echo '<h3 class="text-lg font-semibold mt-6 mb-3">' . __('Documents Expiring Soon (30 Days)') . '</h3>';

        echo '<div class="bg-orange-50 border border-orange-200 rounded-lg overflow-hidden">';
        echo '<table class="w-full">';
        echo '<thead class="bg-orange-100">';
        echo '<tr>';
        echo '<th class="px-4 py-2 text-left text-sm font-medium text-gray-700">' . __('Person') . '</th>';
        echo '<th class="px-4 py-2 text-left text-sm font-medium text-gray-700">' . __('Document Type') . '</th>';
        echo '<th class="px-4 py-2 text-center text-sm font-medium text-gray-700">' . __('Expiry Date') . '</th>';
        echo '<th class="px-4 py-2 text-center text-sm font-medium text-gray-700">' . __('Days Left') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="divide-y divide-orange-200">';

        foreach ($expiringDocuments as $doc) {
            $daysLeft = (int) $doc['daysUntilExpiry'];
            $urgencyClass = $daysLeft <= 7 ? 'bg-red-100' : ($daysLeft <= 14 ? 'bg-orange-100' : '');

            echo '<tr class="' . $urgencyClass . '">';
            echo '<td class="px-4 py-2">' . Format::name('', $doc['preferredName'], $doc['surname'], 'Student') . '</td>';
            echo '<td class="px-4 py-2">' . htmlspecialchars($doc['documentTypeDisplay']) . '</td>';
            echo '<td class="px-4 py-2 text-center">' . Format::date($doc['expiryDate']) . '</td>';
            echo '<td class="px-4 py-2 text-center">';
            if ($daysLeft <= 7) {
                echo '<span class="text-red-600 font-bold">' . $daysLeft . ' ' . __('days') . '</span>';
            } else {
                echo '<span class="text-orange-600">' . $daysLeft . ' ' . __('days') . '</span>';
            }
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }

    // Link back to dashboard
    echo '<div class="mt-6">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Dashboard') . '</a>';
    echo '</div>';
}
