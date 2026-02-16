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
use Gibbon\Module\GovernmentDocuments\Domain\GovernmentDocumentGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Government Documents'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/GovernmentDocuments/governmentDocuments.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get school year ID from session
    $gibbonSchoolYearID = $session->get('gibbonSchoolYearID');

    // Get gateway via DI container
    $documentGateway = $container->get(GovernmentDocumentGateway::class);

    // Get family ID from request (for staff viewing a specific family) or from session (for parents)
    $gibbonFamilyID = $_GET['gibbonFamilyID'] ?? null;

    // If no family ID provided and user is a parent, get their family
    if (empty($gibbonFamilyID)) {
        $gibbonPersonID = $session->get('gibbonPersonID');
        // Check if user is a parent
        $familyResult = $connection2->prepare("SELECT gibbonFamilyID FROM gibbonFamilyAdult WHERE gibbonPersonID = :gibbonPersonID ORDER BY contactPriority ASC LIMIT 1");
        $familyResult->execute(['gibbonPersonID' => $gibbonPersonID]);
        $familyData = $familyResult->fetch();
        if ($familyData) {
            $gibbonFamilyID = $familyData['gibbonFamilyID'];
        }
    }

    // Get document statistics for the school year
    $statistics = $documentGateway->getDocumentStatistics($gibbonSchoolYearID);
    $complianceRate = $documentGateway->getComplianceRate($gibbonSchoolYearID);

    // Page header
    echo '<h2>' . __('Government Documents Dashboard') . '</h2>';

    // Overall Statistics Cards (for staff/admin view)
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">';

    // Compliance Rate Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Compliance Rate') . '</h3>';
    $complianceColor = $complianceRate >= 80 ? 'text-green-600' : ($complianceRate >= 60 ? 'text-yellow-600' : 'text-red-600');
    echo '<div class="text-3xl font-bold ' . $complianceColor . '">' . $complianceRate . '%</div>';
    echo '<p class="text-gray-500 text-sm">' . __('Required documents verified') . '</p>';
    echo '</div>';

    // Verified Documents Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Verified') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Documents') . ':</span><span class="font-bold text-green-600">' . ($statistics['verifiedCount'] ?? 0) . '</span></div>';
    echo '</div>';
    echo '</div>';

    // Pending Documents Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Pending Review') . '</h3>';
    echo '<div class="space-y-2">';
    echo '<div class="flex justify-between"><span>' . __('Documents') . ':</span><span class="font-bold text-yellow-600">' . ($statistics['pendingCount'] ?? 0) . '</span></div>';
    echo '</div>';
    if (($statistics['pendingCount'] ?? 0) > 0) {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_verify.php" class="block mt-3 text-blue-600 hover:underline">' . __('Review Pending') . ' &rarr;</a>';
    }
    echo '</div>';

    // Expiring/Expired Card
    echo '<div class="bg-white rounded-lg shadow p-4">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">' . __('Attention Required') . '</h3>';
    echo '<div class="space-y-2">';
    $expiredCount = $statistics['expiredCount'] ?? 0;
    $expiringSoonCount = $statistics['expiringSoonCount'] ?? 0;
    $rejectedCount = $statistics['rejectedCount'] ?? 0;
    if ($expiredCount > 0) {
        echo '<div class="flex justify-between"><span>' . __('Expired') . ':</span><span class="font-bold text-red-600">' . $expiredCount . '</span></div>';
    }
    if ($expiringSoonCount > 0) {
        echo '<div class="flex justify-between"><span>' . __('Expiring Soon') . ':</span><span class="font-bold text-orange-500">' . $expiringSoonCount . '</span></div>';
    }
    if ($rejectedCount > 0) {
        echo '<div class="flex justify-between"><span>' . __('Rejected') . ':</span><span class="font-bold text-red-500">' . $rejectedCount . '</span></div>';
    }
    if ($expiredCount == 0 && $expiringSoonCount == 0 && $rejectedCount == 0) {
        echo '<p class="text-green-600">' . __('All documents up to date.') . '</p>';
    }
    echo '</div>';
    echo '</div>';

    echo '</div>'; // End statistics grid

    // Family Documents Section
    if (!empty($gibbonFamilyID)) {
        // Get family name
        $familyNameResult = $connection2->prepare("SELECT name FROM gibbonFamily WHERE gibbonFamilyID = :gibbonFamilyID");
        $familyNameResult->execute(['gibbonFamilyID' => $gibbonFamilyID]);
        $familyName = $familyNameResult->fetchColumn() ?: __('Selected Family');

        echo '<h2 class="mt-6">' . __('Documents for') . ': ' . htmlspecialchars($familyName) . '</h2>';

        // Get comprehensive checklist for the family
        $checklist = $documentGateway->getChecklistByFamily($gibbonFamilyID, $gibbonSchoolYearID);

        // Family Summary Card
        $summary = $checklist['summary'];
        echo '<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">';
        echo '<h3 class="text-lg font-semibold mb-3">' . __('Family Document Summary') . '</h3>';
        echo '<div class="grid grid-cols-2 md:grid-cols-5 gap-4">';
        echo '<div class="text-center"><div class="text-2xl font-bold">' . $summary['total'] . '</div><div class="text-gray-500 text-sm">' . __('Total Required') . '</div></div>';
        echo '<div class="text-center"><div class="text-2xl font-bold text-green-600">' . $summary['verified'] . '</div><div class="text-gray-500 text-sm">' . __('Verified') . '</div></div>';
        echo '<div class="text-center"><div class="text-2xl font-bold text-yellow-600">' . $summary['pending'] . '</div><div class="text-gray-500 text-sm">' . __('Pending') . '</div></div>';
        echo '<div class="text-center"><div class="text-2xl font-bold text-gray-600">' . $summary['missing'] . '</div><div class="text-gray-500 text-sm">' . __('Missing') . '</div></div>';
        echo '<div class="text-center"><div class="text-2xl font-bold text-red-600">' . ($summary['expired'] + $summary['rejected']) . '</div><div class="text-gray-500 text-sm">' . __('Action Needed') . '</div></div>';
        echo '</div>';
        echo '</div>';

        // Group document types by category
        $typesByCategory = [];
        foreach ($checklist['documentTypes'] as $type) {
            $category = $type['category'] ?? 'Other';
            if (!isset($typesByCategory[$category])) {
                $typesByCategory[$category] = [];
            }
            $typesByCategory[$category][] = $type;
        }

        // Display documents by family member
        echo '<div class="space-y-6">';
        foreach ($checklist['members'] as $member) {
            $memberName = Format::name('', $member['preferredName'], $member['surname'], 'Student');
            $memberType = $member['memberType'];

            echo '<div class="bg-white rounded-lg shadow p-4">';
            echo '<h3 class="text-lg font-semibold border-b pb-2 mb-3">';
            echo '<span class="inline-block bg-' . ($memberType === 'Child' ? 'blue' : 'purple') . '-100 text-' . ($memberType === 'Child' ? 'blue' : 'purple') . '-800 px-2 py-1 rounded text-sm mr-2">' . __($memberType) . '</span>';
            echo htmlspecialchars($memberName);
            echo '</h3>';

            echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

            // Get applicable document types for this member
            $applicableTypes = [];
            foreach ($checklist['documentTypes'] as $type) {
                if ($type['category'] === $memberType) {
                    $applicableTypes[] = $type;
                }
            }

            foreach ($applicableTypes as $type) {
                $documentKey = $member['gibbonPersonID'] . '_' . $type['gibbonGovernmentDocumentTypeID'];
                $document = $checklist['documentIndex'][$documentKey] ?? null;

                // Determine status
                $status = 'missing';
                $statusLabel = __('Missing');
                $statusColor = 'gray';
                $statusIcon = '&#x25CB;'; // Empty circle

                if ($document) {
                    $status = $document['status'];
                    // Check for expiration
                    if ($status === 'verified' && !empty($document['expiryDate']) && $document['expiryDate'] < date('Y-m-d')) {
                        $status = 'expired';
                    }

                    switch ($status) {
                        case 'verified':
                            $statusLabel = __('Verified');
                            $statusColor = 'green';
                            $statusIcon = '&#x2713;'; // Checkmark
                            break;
                        case 'pending':
                            $statusLabel = __('Pending');
                            $statusColor = 'yellow';
                            $statusIcon = '&#x25CF;'; // Filled circle
                            break;
                        case 'rejected':
                            $statusLabel = __('Rejected');
                            $statusColor = 'red';
                            $statusIcon = '&#x2717;'; // X mark
                            break;
                        case 'expired':
                            $statusLabel = __('Expired');
                            $statusColor = 'red';
                            $statusIcon = '&#x26A0;'; // Warning
                            break;
                    }
                }

                echo '<div class="border rounded p-3 ' . ($type['required'] === 'Y' ? 'border-l-4 border-l-blue-500' : '') . '">';
                echo '<div class="flex justify-between items-start mb-2">';
                echo '<div>';
                echo '<div class="font-medium">' . htmlspecialchars($type['nameDisplay']) . '</div>';
                if ($type['required'] === 'Y') {
                    echo '<span class="text-xs text-red-500">' . __('Required') . '</span>';
                }
                echo '</div>';
                echo '<span class="inline-flex items-center px-2 py-1 rounded text-xs font-medium bg-' . $statusColor . '-100 text-' . $statusColor . '-800">';
                echo $statusIcon . ' ' . $statusLabel;
                echo '</span>';
                echo '</div>';

                // Document details if exists
                if ($document) {
                    echo '<div class="text-sm text-gray-600">';
                    if (!empty($document['issueDate'])) {
                        echo '<div>' . __('Issued') . ': ' . Format::date($document['issueDate']) . '</div>';
                    }
                    if (!empty($document['expiryDate'])) {
                        $isExpiringSoon = $document['expiryDate'] <= date('Y-m-d', strtotime('+30 days')) && $document['expiryDate'] >= date('Y-m-d');
                        echo '<div class="' . ($isExpiringSoon ? 'text-orange-500 font-medium' : '') . '">' . __('Expires') . ': ' . Format::date($document['expiryDate']) . '</div>';
                    }
                    if ($status === 'rejected' && !empty($document['rejectionReason'])) {
                        echo '<div class="text-red-600 mt-1">' . __('Reason') . ': ' . htmlspecialchars($document['rejectionReason']) . '</div>';
                    }
                    echo '</div>';

                    // View document link
                    if (!empty($document['filePath'])) {
                        echo '<a href="' . $session->get('absoluteURL') . '/' . htmlspecialchars($document['filePath']) . '" target="_blank" class="inline-block mt-2 text-blue-600 hover:underline text-sm">' . __('View Document') . '</a>';
                    }
                }

                // Upload button for missing, rejected, or expired documents
                if ($status === 'missing' || $status === 'rejected' || $status === 'expired') {
                    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_upload.php';
                    echo '&gibbonPersonID=' . $member['gibbonPersonID'];
                    echo '&gibbonGovernmentDocumentTypeID=' . $type['gibbonGovernmentDocumentTypeID'];
                    echo '&gibbonFamilyID=' . $gibbonFamilyID;
                    echo '" class="inline-block mt-2 bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600">' . __('Upload') . '</a>';
                }

                echo '</div>';
            }

            echo '</div>'; // End grid
            echo '</div>'; // End member card
        }
        echo '</div>'; // End members space

    } else {
        // No family selected - show family selector (for staff)
        echo '<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">';
        echo '<h3 class="text-lg font-semibold mb-2">' . __('Select a Family') . '</h3>';
        echo '<p class="text-gray-600 mb-4">' . __('Please select a family to view their government documents.') . '</p>';

        // Family selector form
        echo '<form method="get" action="' . $session->get('absoluteURL') . '/index.php">';
        echo '<input type="hidden" name="q" value="/modules/GovernmentDocuments/governmentDocuments.php">';
        echo '<div class="flex gap-2">';
        echo '<select name="gibbonFamilyID" class="standardWidth">';
        echo '<option value="">' . __('Select a family...') . '</option>';

        // Get families with enrolled students
        $familyQuery = $connection2->prepare("
            SELECT DISTINCT f.gibbonFamilyID, f.name
            FROM gibbonFamily f
            INNER JOIN gibbonFamilyChild fc ON f.gibbonFamilyID = fc.gibbonFamilyID
            INNER JOIN gibbonStudentEnrolment se ON fc.gibbonPersonID = se.gibbonPersonID
            WHERE se.gibbonSchoolYearID = :gibbonSchoolYearID
            ORDER BY f.name
        ");
        $familyQuery->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
        while ($family = $familyQuery->fetch()) {
            echo '<option value="' . $family['gibbonFamilyID'] . '">' . htmlspecialchars($family['name']) . '</option>';
        }

        echo '</select>';
        echo '<button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('View') . '</button>';
        echo '</div>';
        echo '</form>';
        echo '</div>';
    }

    // Quick Action Buttons
    echo '<div class="mt-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Actions') . '</h3>';
    echo '<div class="flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_verify.php" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Review Pending Documents') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_checklist.php" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Compliance Checklist') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_types.php" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">' . __('Manage Document Types') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/GovernmentDocuments/governmentDocuments_reports.php" class="bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600">' . __('View Reports') . '</a>';
    echo '</div>';
    echo '</div>';
}
