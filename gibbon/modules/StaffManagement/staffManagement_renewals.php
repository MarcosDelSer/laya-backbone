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
use Gibbon\Module\StaffManagement\Domain\CertificationGateway;
use Gibbon\Module\StaffManagement\Domain\StaffProfileGateway;
use Gibbon\Module\StaffManagement\Domain\AuditLogGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Staff Management'), 'staffManagement.php');
$page->breadcrumbs->add(__('Certifications'), 'staffManagement_certifications.php');
$page->breadcrumbs->add(__('Renewal Reminders'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/StaffManagement/staffManagement_renewals.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get current person ID from session
    $gibbonPersonID = $session->get('gibbonPersonID');

    // Get gateways via DI container
    $certificationGateway = $container->get(CertificationGateway::class);
    $staffProfileGateway = $container->get(StaffProfileGateway::class);
    $auditLogGateway = $container->get(AuditLogGateway::class);

    // Certification type options for required certifications
    $requiredCertificationTypes = ['First Aid', 'CPR', 'ECE Certificate', 'ECE Diploma', 'Police Check'];

    // Handle form actions
    $action = $_POST['action'] ?? '';

    if ($action === 'sendReminder') {
        $certificationID = $_POST['gibbonStaffCertificationID'] ?? null;

        if (!empty($certificationID)) {
            // Mark reminder as sent
            $result = $certificationGateway->markReminderSent($certificationID);

            if ($result !== false) {
                $page->addSuccess(__('Reminder has been marked as sent.'));

                // Log the action
                $auditLogGateway->logChange(
                    'gibbonStaffCertification',
                    $certificationID,
                    'reminderSent',
                    'N',
                    'Y',
                    $gibbonPersonID,
                    $session->get('session')
                );
            } else {
                $page->addError(__('Failed to update reminder status.'));
            }
        }
    } elseif ($action === 'sendBulkReminders') {
        $timeframe = $_POST['timeframe'] ?? '30';
        $dateStart = date('Y-m-d');
        $dateEnd = date('Y-m-d', strtotime('+' . intval($timeframe) . ' days'));

        // Get certifications needing reminders
        $remindersNeeded = $certificationGateway->selectCertificationsNeedingRenewalReminder(intval($timeframe));
        $sentCount = 0;

        foreach ($remindersNeeded as $cert) {
            $certificationGateway->markReminderSent($cert['gibbonStaffCertificationID']);
            $sentCount++;

            // Log the action
            $auditLogGateway->logChange(
                'gibbonStaffCertification',
                $cert['gibbonStaffCertificationID'],
                'reminderSent',
                'N',
                'Y',
                $gibbonPersonID,
                $session->get('session')
            );
        }

        if ($sentCount > 0) {
            $page->addSuccess(sprintf(__('Successfully marked %d reminder(s) as sent.'), $sentCount));
        } else {
            $page->addWarning(__('No certifications found needing reminders in the selected timeframe.'));
        }
    } elseif ($action === 'updateExpired') {
        // Update all expired certifications to expired status
        $updated = $certificationGateway->updateExpiredCertifications();

        if ($updated !== false) {
            $page->addSuccess(__('Expired certifications have been updated.'));
        } else {
            $page->addError(__('Failed to update expired certifications.'));
        }
    }

    // Get filter parameter
    $timeframe = $_GET['timeframe'] ?? '30';
    $validTimeframes = ['7', '14', '30', '60', '90', 'expired'];

    if (!in_array($timeframe, $validTimeframes)) {
        $timeframe = '30';
    }

    // Page header
    echo '<h2>' . __('Certification Renewal Dashboard') . '</h2>';

    // Summary statistics
    $summary = $certificationGateway->getCertificationSummaryStatistics();

    // Calculate additional statistics for each timeframe
    $today = date('Y-m-d');
    $in7Days = date('Y-m-d', strtotime('+7 days'));
    $in14Days = date('Y-m-d', strtotime('+14 days'));
    $in30Days = date('Y-m-d', strtotime('+30 days'));
    $in60Days = date('Y-m-d', strtotime('+60 days'));
    $in90Days = date('Y-m-d', strtotime('+90 days'));

    // Get counts for each timeframe
    $criteria7 = $certificationGateway->newQueryCriteria();
    $expiring7 = $certificationGateway->queryCertificationsExpiringSoon($criteria7, $today, $in7Days);
    $count7 = $expiring7->count();

    $criteria14 = $certificationGateway->newQueryCriteria();
    $expiring14 = $certificationGateway->queryCertificationsExpiringSoon($criteria14, $today, $in14Days);
    $count14 = $expiring14->count();

    $criteria30 = $certificationGateway->newQueryCriteria();
    $expiring30 = $certificationGateway->queryCertificationsExpiringSoon($criteria30, $today, $in30Days);
    $count30 = $expiring30->count();

    $criteria60 = $certificationGateway->newQueryCriteria();
    $expiring60 = $certificationGateway->queryCertificationsExpiringSoon($criteria60, $today, $in60Days);
    $count60 = $expiring60->count();

    $criteria90 = $certificationGateway->newQueryCriteria();
    $expiring90 = $certificationGateway->queryCertificationsExpiringSoon($criteria90, $today, $in90Days);
    $count90 = $expiring90->count();

    // Get expired count
    $expiredCerts = $certificationGateway->selectExpiredCertifications();
    $countExpired = $expiredCerts->rowCount();

    // Renewal Summary Cards
    echo '<div class="bg-white rounded-lg shadow p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Renewal Summary') . '</h3>';
    echo '<div class="grid grid-cols-2 md:grid-cols-6 gap-4 text-center">';

    // Expired
    $expiredClass = $timeframe === 'expired' ? 'ring-2 ring-red-500' : '';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_renewals.php&timeframe=expired" class="bg-red-50 rounded p-3 hover:bg-red-100 transition ' . $expiredClass . '">';
    echo '<span class="block text-2xl font-bold text-red-600">' . $countExpired . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Expired') . '</span>';
    echo '</a>';

    // 7 days
    $class7 = $timeframe === '7' ? 'ring-2 ring-red-500' : '';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_renewals.php&timeframe=7" class="bg-red-50 rounded p-3 hover:bg-red-100 transition ' . $class7 . '">';
    echo '<span class="block text-2xl font-bold text-red-600">' . $count7 . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Within 7 Days') . '</span>';
    echo '</a>';

    // 14 days
    $class14 = $timeframe === '14' ? 'ring-2 ring-orange-500' : '';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_renewals.php&timeframe=14" class="bg-orange-50 rounded p-3 hover:bg-orange-100 transition ' . $class14 . '">';
    echo '<span class="block text-2xl font-bold text-orange-600">' . $count14 . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Within 14 Days') . '</span>';
    echo '</a>';

    // 30 days
    $class30 = $timeframe === '30' ? 'ring-2 ring-yellow-500' : '';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_renewals.php&timeframe=30" class="bg-yellow-50 rounded p-3 hover:bg-yellow-100 transition ' . $class30 . '">';
    echo '<span class="block text-2xl font-bold text-yellow-600">' . $count30 . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Within 30 Days') . '</span>';
    echo '</a>';

    // 60 days
    $class60 = $timeframe === '60' ? 'ring-2 ring-blue-500' : '';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_renewals.php&timeframe=60" class="bg-blue-50 rounded p-3 hover:bg-blue-100 transition ' . $class60 . '">';
    echo '<span class="block text-2xl font-bold text-blue-600">' . $count60 . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Within 60 Days') . '</span>';
    echo '</a>';

    // 90 days
    $class90 = $timeframe === '90' ? 'ring-2 ring-green-500' : '';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_renewals.php&timeframe=90" class="bg-green-50 rounded p-3 hover:bg-green-100 transition ' . $class90 . '">';
    echo '<span class="block text-2xl font-bold text-green-600">' . $count90 . '</span>';
    echo '<span class="text-xs text-gray-500">' . __('Within 90 Days') . '</span>';
    echo '</a>';

    echo '</div>';
    echo '</div>';

    // Quick Actions
    echo '<div class="bg-gray-50 rounded-lg p-4 mb-6">';
    echo '<h3 class="text-lg font-semibold mb-3">' . __('Quick Actions') . '</h3>';
    echo '<div class="flex flex-wrap gap-3">';

    // Send bulk reminders form
    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_renewals.php&timeframe=' . $timeframe . '" class="inline-flex items-center gap-2">';
    echo '<input type="hidden" name="action" value="sendBulkReminders">';
    echo '<select name="timeframe" class="border rounded px-2 py-1 text-sm">';
    echo '<option value="7">' . __('7 Days') . '</option>';
    echo '<option value="14">' . __('14 Days') . '</option>';
    echo '<option value="30" selected>' . __('30 Days') . '</option>';
    echo '<option value="60">' . __('60 Days') . '</option>';
    echo '<option value="90">' . __('90 Days') . '</option>';
    echo '</select>';
    echo '<button type="submit" class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600" onclick="return confirm(\'' . __('Mark all reminders as sent for the selected timeframe?') . '\')">';
    echo __('Send Bulk Reminders');
    echo '</button>';
    echo '</form>';

    // Update expired certifications
    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_renewals.php&timeframe=' . $timeframe . '" class="inline">';
    echo '<input type="hidden" name="action" value="updateExpired">';
    echo '<button type="submit" class="bg-orange-500 text-white px-3 py-1 rounded text-sm hover:bg-orange-600" onclick="return confirm(\'' . __('Update all overdue certifications to Expired status?') . '\')">';
    echo __('Update Expired Status');
    echo '</button>';
    echo '</form>';

    // Link to main certifications page
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php" class="bg-gray-500 text-white px-3 py-1 rounded text-sm hover:bg-gray-600">';
    echo __('Manage All Certifications');
    echo '</a>';

    echo '</div>';
    echo '</div>';

    // Display certifications based on selected timeframe
    if ($timeframe === 'expired') {
        // Show expired certifications
        echo '<h3 class="text-lg font-semibold mb-3 text-red-600">' . __('Expired Certifications') . '</h3>';

        if ($countExpired > 0) {
            echo '<div class="bg-white rounded-lg shadow overflow-hidden">';
            echo '<table class="min-w-full">';
            echo '<thead class="bg-red-50">';
            echo '<tr>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Staff Member') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Certification') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Type') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Expired On') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Days Expired') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Required') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Actions') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody class="divide-y divide-gray-200">';

            // Re-fetch to iterate
            $expiredCerts = $certificationGateway->selectExpiredCertifications();
            foreach ($expiredCerts as $cert) {
                $staffName = Format::name('', $cert['preferredName'], $cert['surname'], 'Staff', false, true);
                $daysExpired = intval($cert['daysExpired']);

                $rowClass = $cert['isRequired'] === 'Y' ? 'bg-red-50' : '';

                echo '<tr class="' . $rowClass . '">';
                echo '<td class="px-4 py-3">';
                echo '<div class="font-semibold">' . htmlspecialchars($staffName) . '</div>';
                if (!empty($cert['email'])) {
                    echo '<div class="text-xs text-gray-500">' . htmlspecialchars($cert['email']) . '</div>';
                }
                echo '</td>';
                echo '<td class="px-4 py-3 font-medium">' . htmlspecialchars($cert['certificationName']) . '</td>';
                echo '<td class="px-4 py-3"><span class="bg-gray-100 text-gray-800 text-xs px-2 py-1 rounded">' . __($cert['certificationType']) . '</span></td>';
                echo '<td class="px-4 py-3 text-red-600">' . Format::date($cert['expiryDate']) . '</td>';
                echo '<td class="px-4 py-3"><span class="text-red-600 font-bold">' . $daysExpired . ' ' . __('days') . '</span></td>';
                echo '<td class="px-4 py-3">';
                if ($cert['isRequired'] === 'Y') {
                    echo '<span class="bg-red-200 text-red-800 text-xs px-2 py-1 rounded font-semibold">' . __('Required') . '</span>';
                } else {
                    echo '<span class="text-gray-400">' . __('No') . '</span>';
                }
                echo '</td>';
                echo '<td class="px-4 py-3">';
                echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php&gibbonPersonID=' . $cert['gibbonPersonID'] . '" class="text-blue-600 hover:underline text-sm">' . __('View') . '</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center text-green-600">';
            echo __('No expired certifications found.');
            echo '</div>';
        }
    } else {
        // Show expiring soon certifications
        $days = intval($timeframe);
        $dateEnd = date('Y-m-d', strtotime('+' . $days . ' days'));

        echo '<h3 class="text-lg font-semibold mb-3">' . sprintf(__('Certifications Expiring Within %d Days'), $days) . '</h3>';

        // Get certifications expiring in this timeframe
        $criteria = $certificationGateway->newQueryCriteria()
            ->sortBy(['daysUntilExpiry'], 'ASC');
        $expiringCerts = $certificationGateway->queryCertificationsExpiringSoon($criteria, $today, $dateEnd);

        if ($expiringCerts->count() > 0) {
            echo '<div class="bg-white rounded-lg shadow overflow-hidden">';
            echo '<table class="min-w-full">';
            echo '<thead class="bg-yellow-50">';
            echo '<tr>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Staff Member') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Certification') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Type') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Expires On') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Days Left') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Required') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Reminder') . '</th>';
            echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Actions') . '</th>';
            echo '</tr>';
            echo '</thead>';
            echo '<tbody class="divide-y divide-gray-200">';

            foreach ($expiringCerts as $cert) {
                $staffName = Format::name('', $cert['preferredName'], $cert['surname'], 'Staff', false, true);
                $daysUntilExpiry = intval($cert['daysUntilExpiry']);

                // Determine row styling based on urgency
                if ($daysUntilExpiry <= 7) {
                    $rowClass = 'bg-red-50';
                    $daysClass = 'text-red-600 font-bold';
                } elseif ($daysUntilExpiry <= 14) {
                    $rowClass = 'bg-orange-50';
                    $daysClass = 'text-orange-600 font-semibold';
                } elseif ($daysUntilExpiry <= 30) {
                    $rowClass = 'bg-yellow-50';
                    $daysClass = 'text-yellow-600';
                } else {
                    $rowClass = '';
                    $daysClass = 'text-gray-600';
                }

                // Add highlight for required certifications
                if ($cert['isRequired'] === 'Y') {
                    $rowClass .= ' border-l-4 border-red-500';
                }

                echo '<tr class="' . $rowClass . '">';
                echo '<td class="px-4 py-3">';
                $image = !empty($cert['image_240']) ? $cert['image_240'] : 'themes/Default/img/anonymous_240.jpg';
                echo '<div class="flex items-center space-x-3">';
                echo '<img src="' . $session->get('absoluteURL') . '/' . $image . '" class="w-10 h-10 rounded-full object-cover" alt="">';
                echo '<div>';
                echo '<div class="font-semibold">' . htmlspecialchars($staffName) . '</div>';
                if (!empty($cert['email'])) {
                    echo '<div class="text-xs text-gray-500">' . htmlspecialchars($cert['email']) . '</div>';
                }
                echo '</div>';
                echo '</div>';
                echo '</td>';
                echo '<td class="px-4 py-3">';
                echo '<span class="font-medium">' . htmlspecialchars($cert['certificationName']) . '</span>';
                if (!empty($cert['issuingOrganization'])) {
                    echo '<br><span class="text-xs text-gray-500">' . htmlspecialchars($cert['issuingOrganization']) . '</span>';
                }
                echo '</td>';

                // Type with color coding
                $typeColors = [
                    'First Aid'       => 'red',
                    'CPR'             => 'pink',
                    'ECE Certificate' => 'green',
                    'ECE Diploma'     => 'teal',
                    'Police Check'    => 'blue',
                    'Driver License'  => 'indigo',
                    'Food Handler'    => 'orange',
                    'WHMIS'           => 'yellow',
                    'Other'           => 'gray',
                ];
                $typeColor = $typeColors[$cert['certificationType']] ?? 'gray';
                echo '<td class="px-4 py-3"><span class="bg-' . $typeColor . '-100 text-' . $typeColor . '-800 text-xs px-2 py-1 rounded">' . __($cert['certificationType']) . '</span></td>';

                echo '<td class="px-4 py-3">' . Format::date($cert['expiryDate']) . '</td>';
                echo '<td class="px-4 py-3"><span class="' . $daysClass . '">' . $daysUntilExpiry . ' ' . __('days') . '</span></td>';
                echo '<td class="px-4 py-3">';
                if ($cert['isRequired'] === 'Y') {
                    echo '<span class="bg-red-200 text-red-800 text-xs px-2 py-1 rounded font-semibold">' . __('Required') . '</span>';
                } else {
                    echo '<span class="text-gray-400">' . __('No') . '</span>';
                }
                echo '</td>';

                // Reminder status and action
                echo '<td class="px-4 py-3">';
                if ($cert['reminderSent'] === 'Y') {
                    echo '<span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded">' . __('Sent') . '</span>';
                    if (!empty($cert['reminderSentDate'])) {
                        echo '<br><span class="text-xs text-gray-500">' . Format::date($cert['reminderSentDate']) . '</span>';
                    }
                } else {
                    echo '<form method="post" action="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_renewals.php&timeframe=' . $timeframe . '" class="inline">';
                    echo '<input type="hidden" name="action" value="sendReminder">';
                    echo '<input type="hidden" name="gibbonStaffCertificationID" value="' . $cert['gibbonStaffCertificationID'] . '">';
                    echo '<button type="submit" class="bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded hover:bg-blue-200">' . __('Mark Sent') . '</button>';
                    echo '</form>';
                }
                echo '</td>';

                // Actions
                echo '<td class="px-4 py-3">';
                echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php&gibbonPersonID=' . $cert['gibbonPersonID'] . '" class="text-blue-600 hover:underline text-sm">' . __('View Profile') . '</a>';
                echo '</td>';
                echo '</tr>';
            }

            echo '</tbody>';
            echo '</table>';
            echo '</div>';
        } else {
            echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center text-green-600">';
            echo sprintf(__('No certifications expiring within %d days.'), $days);
            echo '</div>';
        }
    }

    // Staff Missing Required Certifications Section
    echo '<h3 class="text-lg font-semibold mt-8 mb-3 text-purple-600">' . __('Staff Missing Required Certifications') . '</h3>';

    $missingRequired = $certificationGateway->selectStaffMissingRequiredCertifications($requiredCertificationTypes);

    if ($missingRequired->rowCount() > 0) {
        echo '<div class="bg-white rounded-lg shadow overflow-hidden">';
        echo '<table class="min-w-full">';
        echo '<thead class="bg-purple-50">';
        echo '<tr>';
        echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Staff Member') . '</th>';
        echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Position') . '</th>';
        echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Missing Certifications') . '</th>';
        echo '<th class="px-4 py-2 text-left text-xs font-semibold text-gray-600">' . __('Actions') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="divide-y divide-gray-200">';

        foreach ($missingRequired as $staff) {
            $staffName = Format::name('', $staff['preferredName'], $staff['surname'], 'Staff', false, true);
            $missingTypes = explode(',', $staff['missingTypes']);

            echo '<tr class="bg-purple-50">';
            echo '<td class="px-4 py-3">';
            echo '<div class="font-semibold">' . htmlspecialchars($staffName) . '</div>';
            if (!empty($staff['email'])) {
                echo '<div class="text-xs text-gray-500">' . htmlspecialchars($staff['email']) . '</div>';
            }
            echo '</td>';
            echo '<td class="px-4 py-3">' . htmlspecialchars($staff['position'] ?? '-') . '</td>';
            echo '<td class="px-4 py-3">';
            echo '<div class="flex flex-wrap gap-1">';
            foreach ($missingTypes as $type) {
                $typeColors = [
                    'First Aid'       => 'red',
                    'CPR'             => 'pink',
                    'ECE Certificate' => 'green',
                    'ECE Diploma'     => 'teal',
                    'Police Check'    => 'blue',
                ];
                $color = $typeColors[trim($type)] ?? 'gray';
                echo '<span class="bg-' . $color . '-100 text-' . $color . '-800 text-xs px-2 py-1 rounded">' . __(trim($type)) . '</span>';
            }
            echo '</div>';
            echo '</td>';
            echo '<td class="px-4 py-3">';
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php&gibbonPersonID=' . $staff['gibbonPersonID'] . '" class="text-blue-600 hover:underline text-sm">' . __('Add Certification') . '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<div class="bg-green-50 border border-green-200 rounded-lg p-4 text-center text-green-600">';
        echo __('All active staff have the required certifications.');
        echo '</div>';
    }

    // Certification Compliance Summary by Type
    echo '<h3 class="text-lg font-semibold mt-8 mb-3">' . __('Required Certification Coverage') . '</h3>';

    $typeBreakdown = $certificationGateway->selectCertificationCountByType();
    $staffCount = $staffProfileGateway->getStaffSummaryStatistics();
    $totalActiveStaff = $staffCount['totalActive'] ?? 0;

    if ($typeBreakdown->rowCount() > 0 && $totalActiveStaff > 0) {
        echo '<div class="bg-white rounded-lg shadow p-4">';
        echo '<p class="text-sm text-gray-500 mb-4">' . sprintf(__('Coverage based on %d active staff members'), $totalActiveStaff) . '</p>';
        echo '<div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">';

        foreach ($requiredCertificationTypes as $requiredType) {
            $typeData = null;
            // Reset iterator
            foreach ($typeBreakdown as $type) {
                if ($type['certificationType'] === $requiredType) {
                    $typeData = $type;
                    break;
                }
            }

            $validCount = $typeData ? intval($typeData['validCount']) : 0;
            $coverage = $totalActiveStaff > 0 ? round(($validCount / $totalActiveStaff) * 100) : 0;

            $typeColors = [
                'First Aid'       => 'red',
                'CPR'             => 'pink',
                'ECE Certificate' => 'green',
                'ECE Diploma'     => 'teal',
                'Police Check'    => 'blue',
            ];
            $color = $typeColors[$requiredType] ?? 'gray';

            // Determine status color based on coverage
            if ($coverage >= 100) {
                $statusColor = 'green';
                $statusText = __('Complete');
            } elseif ($coverage >= 75) {
                $statusColor = 'yellow';
                $statusText = __('Good');
            } elseif ($coverage >= 50) {
                $statusColor = 'orange';
                $statusText = __('Fair');
            } else {
                $statusColor = 'red';
                $statusText = __('Low');
            }

            echo '<div class="bg-' . $color . '-50 rounded-lg p-4 text-center">';
            echo '<span class="block text-sm font-semibold text-' . $color . '-800 mb-2">' . __($requiredType) . '</span>';

            // Progress bar
            echo '<div class="w-full bg-gray-200 rounded-full h-2 mb-2">';
            echo '<div class="bg-' . $statusColor . '-500 h-2 rounded-full" style="width: ' . min($coverage, 100) . '%"></div>';
            echo '</div>';

            echo '<span class="block text-lg font-bold text-' . $statusColor . '-600">' . $coverage . '%</span>';
            echo '<span class="text-xs text-gray-500">' . $validCount . ' / ' . $totalActiveStaff . ' ' . __('staff') . '</span>';
            echo '<br><span class="text-xs text-' . $statusColor . '-600 font-semibold">' . $statusText . '</span>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // Navigation links
    echo '<div class="mt-6 flex gap-4">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement_certifications.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Certifications') . '</a>';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/StaffManagement/staffManagement.php" class="text-blue-600 hover:underline">&larr; ' . __('Back to Staff Management') . '</a>';
    echo '</div>';
}
