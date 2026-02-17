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
use Gibbon\Module\ServiceAgreement\Domain\ServiceAgreementGateway;
use Gibbon\Module\ServiceAgreement\Domain\AnnexGateway;
use Gibbon\Module\ServiceAgreement\Domain\SignatureGateway;

// Module setup - breadcrumbs
$page->breadcrumbs->add(__('Service Agreements'), 'serviceAgreement.php');
$page->breadcrumbs->add(__('View Agreement'));

// Access check
if (!isActionAccessible($guid, $connection2, '/modules/ServiceAgreement/serviceAgreement_view.php')) {
    $page->addError(__('You do not have access to this action.'));
} else {
    // Get agreement ID from request
    $gibbonServiceAgreementID = $_GET['gibbonServiceAgreementID'] ?? '';

    if (empty($gibbonServiceAgreementID)) {
        $page->addError(__('No service agreement specified.'));
        return;
    }

    // Get gateways via DI container
    $serviceAgreementGateway = $container->get(ServiceAgreementGateway::class);
    $annexGateway = $container->get(AnnexGateway::class);
    $signatureGateway = $container->get(SignatureGateway::class);

    // Get agreement with details
    $agreement = $serviceAgreementGateway->getAgreementWithDetails($gibbonServiceAgreementID);

    if (!$agreement) {
        $page->addError(__('The specified service agreement could not be found.'));
        return;
    }

    // Get annexes and signatures
    $annexes = $annexGateway->selectAnnexesByAgreement($gibbonServiceAgreementID)->fetchAll();
    $signatures = $signatureGateway->selectSignaturesByAgreement($gibbonServiceAgreementID)->fetchAll();
    $annexSummary = $annexGateway->getAnnexSummary($gibbonServiceAgreementID);

    // Get annex type names
    $annexTypeNames = AnnexGateway::getAnnexTypeNames();

    // Page header with agreement number and status
    echo '<div class="flex justify-between items-start mb-6">';
    echo '<div>';
    echo '<h2 class="mb-2">' . __('Service Agreement') . ': ' . htmlspecialchars($agreement['agreementNumber']) . '</h2>';
    echo '<p class="text-gray-600">' . __('Quebec FO-0659 Service Agreement (Entente de Services)') . '</p>';
    echo '</div>';
    echo '<div class="text-right">';

    // Status badge
    $statusClasses = [
        'Draft' => 'bg-gray-200 text-gray-800',
        'Pending Signature' => 'bg-orange-200 text-orange-800',
        'Active' => 'bg-green-200 text-green-800',
        'Expired' => 'bg-red-200 text-red-800',
        'Terminated' => 'bg-red-200 text-red-800',
        'Cancelled' => 'bg-gray-300 text-gray-600',
    ];
    $statusClass = $statusClasses[$agreement['status']] ?? 'bg-gray-200 text-gray-800';
    echo '<span class="px-3 py-1 rounded-full text-sm font-medium ' . $statusClass . '">' . __($agreement['status']) . '</span>';

    // Signatures indicator
    if ($agreement['allSignaturesComplete'] === 'Y') {
        echo '<div class="mt-2 text-green-600 text-sm"><span class="mr-1">&#10003;</span>' . __('All Signatures Complete') . '</div>';
    } else {
        echo '<div class="mt-2 text-orange-500 text-sm">' . __('Signatures Pending') . '</div>';
    }
    echo '</div>';
    echo '</div>';

    // Action buttons
    echo '<div class="mb-6 flex flex-wrap gap-2">';
    echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement.php" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">&larr; ' . __('Back to List') . '</a>';

    if ($agreement['status'] === 'Draft') {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_edit.php&gibbonServiceAgreementID=' . $gibbonServiceAgreementID . '" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">' . __('Edit Agreement') . '</a>';
    }

    if ($agreement['status'] === 'Pending Signature') {
        echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_sign.php&gibbonServiceAgreementID=' . $gibbonServiceAgreementID . '" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Sign Agreement') . '</a>';
    }

    if ($agreement['allSignaturesComplete'] === 'Y') {
        echo '<a href="' . $session->get('absoluteURL') . '/modules/ServiceAgreement/serviceAgreement_pdf.php?gibbonServiceAgreementID=' . $gibbonServiceAgreementID . '" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600" target="_blank">' . __('Download PDF') . '</a>';
    }
    echo '</div>';

    // Agreement Overview Card
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Agreement Overview') . '</h3>';
    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">';

    // School Year
    echo '<div>';
    echo '<span class="text-gray-500 text-sm">' . __('School Year') . '</span>';
    echo '<div class="font-medium">' . htmlspecialchars($agreement['schoolYearName'] ?? '-') . '</div>';
    echo '</div>';

    // Effective Date
    echo '<div>';
    echo '<span class="text-gray-500 text-sm">' . __('Effective Date') . '</span>';
    echo '<div class="font-medium">' . (!empty($agreement['effectiveDate']) ? Format::date($agreement['effectiveDate']) : '-') . '</div>';
    echo '</div>';

    // Expiration Date
    echo '<div>';
    echo '<span class="text-gray-500 text-sm">' . __('Expiration Date') . '</span>';
    echo '<div class="font-medium">' . (!empty($agreement['expirationDate']) ? Format::date($agreement['expirationDate']) : __('No expiration')) . '</div>';
    echo '</div>';

    // Contribution Type
    echo '<div>';
    echo '<span class="text-gray-500 text-sm">' . __('Contribution Type') . '</span>';
    echo '<div class="font-medium">' . __($agreement['contributionType'] ?? '-');
    if ($agreement['contributionType'] === 'Reduced' && !empty($agreement['dailyReducedContribution'])) {
        echo ' <span class="text-gray-500">($' . number_format($agreement['dailyReducedContribution'], 2) . '/day)</span>';
    }
    echo '</div>';
    echo '</div>';

    echo '</div>'; // End grid
    echo '</div>'; // End overview card

    // ========================================
    // ARTICLE 1: Identification of Parties
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Article 1: Identification of Parties') . '</h3>';

    echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-6">';

    // Provider Information
    echo '<div class="bg-gray-50 rounded p-4">';
    echo '<h4 class="font-medium text-gray-700 mb-3">' . __('Provider') . '</h4>';
    echo '<div class="space-y-2">';
    echo '<div><span class="text-gray-500 text-sm">' . __('Name') . ':</span><div>' . htmlspecialchars($agreement['providerName'] ?? '-') . '</div></div>';
    if (!empty($agreement['providerPermitNumber'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Permit #') . ':</span><div>' . htmlspecialchars($agreement['providerPermitNumber']) . '</div></div>';
    }
    if (!empty($agreement['providerAddress'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Address') . ':</span><div>' . nl2br(htmlspecialchars($agreement['providerAddress'])) . '</div></div>';
    }
    if (!empty($agreement['providerPhone'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Phone') . ':</span><div>' . htmlspecialchars($agreement['providerPhone']) . '</div></div>';
    }
    if (!empty($agreement['providerEmail'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Email') . ':</span><div>' . htmlspecialchars($agreement['providerEmail']) . '</div></div>';
    }
    echo '</div>';
    echo '</div>';

    // Child Information
    echo '<div class="bg-blue-50 rounded p-4">';
    echo '<h4 class="font-medium text-gray-700 mb-3">' . __('Child') . '</h4>';
    echo '<div class="space-y-2">';
    $childDisplayName = Format::name('', $agreement['childPreferredName'], $agreement['childSurname'], 'Student');
    echo '<div><span class="text-gray-500 text-sm">' . __('Name') . ':</span><div class="font-medium">' . htmlspecialchars($agreement['childName'] ?? $childDisplayName) . '</div></div>';
    if (!empty($agreement['childDateOfBirth'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Date of Birth') . ':</span><div>' . Format::date($agreement['childDateOfBirth']) . '</div></div>';
    }
    echo '</div>';
    echo '</div>';

    // Parent Information
    echo '<div class="bg-green-50 rounded p-4">';
    echo '<h4 class="font-medium text-gray-700 mb-3">' . __('Parent/Guardian') . '</h4>';
    echo '<div class="space-y-2">';
    $parentDisplayName = Format::name('', $agreement['parentPreferredName'], $agreement['parentSurname'], 'Parent');
    echo '<div><span class="text-gray-500 text-sm">' . __('Name') . ':</span><div class="font-medium">' . htmlspecialchars($agreement['parentName'] ?? $parentDisplayName) . '</div></div>';
    if (!empty($agreement['parentAddress'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Address') . ':</span><div>' . nl2br(htmlspecialchars($agreement['parentAddress'])) . '</div></div>';
    }
    if (!empty($agreement['parentPhone'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Phone') . ':</span><div>' . htmlspecialchars($agreement['parentPhone']) . '</div></div>';
    }
    if (!empty($agreement['parentEmail'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Email') . ':</span><div>' . htmlspecialchars($agreement['parentEmail']) . '</div></div>';
    }
    echo '</div>';
    echo '</div>';

    echo '</div>'; // End grid
    echo '</div>'; // End Article 1 card

    // ========================================
    // ARTICLE 2 & 3: Services & Operating Hours
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Articles 2-3: Services & Operating Hours') . '</h3>';

    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-6">';

    // Article 2: Services
    echo '<div>';
    echo '<h4 class="font-medium text-gray-700 mb-3">' . __('Services Provided') . '</h4>';
    echo '<div class="space-y-2">';

    if (!empty($agreement['maxHoursPerDay'])) {
        echo '<div class="flex justify-between"><span class="text-gray-500">' . __('Max Hours/Day') . ':</span><span>' . $agreement['maxHoursPerDay'] . ' ' . __('hours') . '</span></div>';
    }

    // Meals included
    $meals = [];
    if ($agreement['includesBreakfast'] === 'Y') $meals[] = __('Breakfast');
    if ($agreement['includesLunch'] === 'Y') $meals[] = __('Lunch');
    if ($agreement['includesSnacks'] === 'Y') $meals[] = __('Snacks');
    if ($agreement['includesDinner'] === 'Y') $meals[] = __('Dinner');
    if (!empty($meals)) {
        echo '<div><span class="text-gray-500">' . __('Meals Included') . ':</span><div>' . implode(', ', $meals) . '</div></div>';
    }

    if (!empty($agreement['serviceDescription'])) {
        echo '<div><span class="text-gray-500">' . __('Additional Services') . ':</span><div class="mt-1">' . nl2br(htmlspecialchars($agreement['serviceDescription'])) . '</div></div>';
    }
    echo '</div>';
    echo '</div>';

    // Article 3: Operating Hours
    echo '<div>';
    echo '<h4 class="font-medium text-gray-700 mb-3">' . __('Operating Hours') . '</h4>';
    echo '<div class="space-y-2">';

    if (!empty($agreement['operatingHoursStart']) && !empty($agreement['operatingHoursEnd'])) {
        echo '<div class="flex justify-between"><span class="text-gray-500">' . __('Hours') . ':</span><span>' . $agreement['operatingHoursStart'] . ' - ' . $agreement['operatingHoursEnd'] . '</span></div>';
    }

    if (!empty($agreement['operatingDays'])) {
        $daysMap = [
            'Mon' => __('Monday'),
            'Tue' => __('Tuesday'),
            'Wed' => __('Wednesday'),
            'Thu' => __('Thursday'),
            'Fri' => __('Friday'),
            'Sat' => __('Saturday'),
            'Sun' => __('Sunday'),
        ];
        $operatingDays = is_array($agreement['operatingDays']) ? $agreement['operatingDays'] : explode(',', $agreement['operatingDays']);
        $dayNames = array_map(function($day) use ($daysMap) {
            return $daysMap[trim($day)] ?? trim($day);
        }, $operatingDays);
        echo '<div><span class="text-gray-500">' . __('Operating Days') . ':</span><div>' . implode(', ', $dayNames) . '</div></div>';
    }
    echo '</div>';
    echo '</div>';

    echo '</div>'; // End grid
    echo '</div>'; // End Article 2-3 card

    // ========================================
    // ARTICLE 4: Attendance Pattern
    // ========================================
    if (!empty($agreement['attendancePattern']) || !empty($agreement['hoursPerWeek'])) {
        echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
        echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Article 4: Attendance Pattern') . '</h3>';

        if (!empty($agreement['hoursPerWeek'])) {
            echo '<div class="mb-3"><span class="text-gray-500">' . __('Hours Per Week') . ':</span> <span class="font-medium">' . $agreement['hoursPerWeek'] . ' ' . __('hours') . '</span></div>';
        }

        if (!empty($agreement['attendancePattern'])) {
            echo '<div><span class="text-gray-500">' . __('Schedule') . ':</span>';
            echo '<div class="mt-2 p-3 bg-gray-50 rounded">' . nl2br(htmlspecialchars($agreement['attendancePattern'])) . '</div>';
            echo '</div>';
        }

        echo '</div>';
    }

    // ========================================
    // ARTICLE 5: Payment Terms
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Article 5: Payment Terms') . '</h3>';

    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

    echo '<div><span class="text-gray-500 text-sm">' . __('Contribution Type') . '</span><div class="font-medium">' . __($agreement['contributionType'] ?? '-') . '</div></div>';

    if (!empty($agreement['dailyReducedContribution'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Daily Reduced Contribution') . '</span><div class="font-medium">$' . number_format($agreement['dailyReducedContribution'], 2) . '</div></div>';
    }

    if (!empty($agreement['additionalDailyRate'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Additional Daily Rate') . '</span><div class="font-medium">$' . number_format($agreement['additionalDailyRate'], 2) . '</div></div>';
    }

    if (!empty($agreement['paymentFrequency'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Payment Frequency') . '</span><div class="font-medium">' . __($agreement['paymentFrequency']) . '</div></div>';
    }

    if (!empty($agreement['paymentMethod'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Payment Method') . '</span><div class="font-medium">' . __($agreement['paymentMethod']) . '</div></div>';
    }

    if (!empty($agreement['paymentDueDay'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Payment Due Day') . '</span><div class="font-medium">' . sprintf(__('Day %d of month'), $agreement['paymentDueDay']) . '</div></div>';
    }

    echo '</div>';
    echo '</div>';

    // ========================================
    // ARTICLE 6: Late Pickup Fees
    // ========================================
    if (!empty($agreement['latePickupFeePerMinute']) || !empty($agreement['latePickupGracePeriod'])) {
        echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
        echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Article 6: Late Pickup Fees') . '</h3>';

        echo '<div class="grid grid-cols-1 md:grid-cols-3 gap-4">';

        if (!empty($agreement['latePickupFeePerMinute'])) {
            echo '<div><span class="text-gray-500 text-sm">' . __('Fee Per Minute') . '</span><div class="font-medium">$' . number_format($agreement['latePickupFeePerMinute'], 2) . '</div></div>';
        }

        if (!empty($agreement['latePickupGracePeriod'])) {
            echo '<div><span class="text-gray-500 text-sm">' . __('Grace Period') . '</span><div class="font-medium">' . $agreement['latePickupGracePeriod'] . ' ' . __('minutes') . '</div></div>';
        }

        if (!empty($agreement['latePickupMaxFee'])) {
            echo '<div><span class="text-gray-500 text-sm">' . __('Maximum Fee') . '</span><div class="font-medium">$' . number_format($agreement['latePickupMaxFee'], 2) . '</div></div>';
        }

        echo '</div>';
        echo '</div>';
    }

    // ========================================
    // ARTICLE 7: Closure Days
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Article 7: Closure Days') . '</h3>';

    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">';

    echo '<div><span class="text-gray-500 text-sm">' . __('Statutory Holidays Closed') . '</span><div class="font-medium">' . ($agreement['statutoryHolidaysClosed'] === 'Y' ? __('Yes') : __('No')) . '</div></div>';

    if (!empty($agreement['summerClosureWeeks'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Summer Closure') . '</span><div class="font-medium">' . $agreement['summerClosureWeeks'] . ' ' . __('weeks') . '</div></div>';
    }

    if (!empty($agreement['winterClosureWeeks'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Winter Closure') . '</span><div class="font-medium">' . $agreement['winterClosureWeeks'] . ' ' . __('weeks') . '</div></div>';
    }

    echo '</div>';

    if (!empty($agreement['closureDatesText'])) {
        echo '<div class="mt-4"><span class="text-gray-500">' . __('Specific Closure Dates') . ':</span>';
        echo '<div class="mt-1 p-3 bg-gray-50 rounded">' . nl2br(htmlspecialchars($agreement['closureDatesText'])) . '</div>';
        echo '</div>';
    }

    echo '</div>';

    // ========================================
    // ARTICLE 8: Absence Policy
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Article 8: Absence Policy') . '</h3>';

    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';

    if (!empty($agreement['maxAbsenceDaysPerYear'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Max Absence Days/Year') . '</span><div class="font-medium">' . $agreement['maxAbsenceDaysPerYear'] . ' ' . __('days') . '</div></div>';
    }

    if (!empty($agreement['absenceNoticeRequired'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Notice Required') . '</span><div class="font-medium">' . $agreement['absenceNoticeRequired'] . ' ' . __('hours') . '</div></div>';
    }

    if (!empty($agreement['absenceChargePolicy'])) {
        $chargePolicies = [
            'ChargeAll' => __('Charge for all absences'),
            'ChargePartial' => __('Charge partial for absences'),
            'NoCharge' => __('No charge for absences'),
        ];
        echo '<div><span class="text-gray-500 text-sm">' . __('Charge Policy') . '</span><div class="font-medium">' . ($chargePolicies[$agreement['absenceChargePolicy']] ?? $agreement['absenceChargePolicy']) . '</div></div>';
    }

    echo '</div>';

    if (!empty($agreement['medicalAbsencePolicy'])) {
        echo '<div class="mt-4"><span class="text-gray-500">' . __('Medical Absence Policy') . ':</span>';
        echo '<div class="mt-1 p-3 bg-gray-50 rounded">' . nl2br(htmlspecialchars($agreement['medicalAbsencePolicy'])) . '</div>';
        echo '</div>';
    }

    echo '</div>';

    // ========================================
    // ARTICLE 9: Agreement Duration
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Article 9: Agreement Duration') . '</h3>';

    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">';

    echo '<div><span class="text-gray-500 text-sm">' . __('Effective Date') . '</span><div class="font-medium">' . (!empty($agreement['effectiveDate']) ? Format::date($agreement['effectiveDate']) : '-') . '</div></div>';

    echo '<div><span class="text-gray-500 text-sm">' . __('Expiration Date') . '</span><div class="font-medium">' . (!empty($agreement['expirationDate']) ? Format::date($agreement['expirationDate']) : __('No expiration')) . '</div></div>';

    if (!empty($agreement['renewalType'])) {
        $renewalTypes = [
            'AutoRenew' => __('Automatic Annual Renewal'),
            'RequiresRenewal' => __('Requires Explicit Renewal'),
            'FixedTerm' => __('Fixed Term'),
        ];
        echo '<div><span class="text-gray-500 text-sm">' . __('Renewal Type') . '</span><div class="font-medium">' . ($renewalTypes[$agreement['renewalType']] ?? $agreement['renewalType']) . '</div></div>';
    }

    if (!empty($agreement['renewalNoticeRequired'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Renewal Notice') . '</span><div class="font-medium">' . $agreement['renewalNoticeRequired'] . ' ' . __('days') . '</div></div>';
    }

    echo '</div>';
    echo '</div>';

    // ========================================
    // ARTICLE 10: Termination Conditions
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Article 10: Termination Conditions') . '</h3>';

    echo '<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">';

    if (!empty($agreement['parentTerminationNotice'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Parent Termination Notice') . '</span><div class="font-medium">' . $agreement['parentTerminationNotice'] . ' ' . __('days') . '</div></div>';
    }

    if (!empty($agreement['providerTerminationNotice'])) {
        echo '<div><span class="text-gray-500 text-sm">' . __('Provider Termination Notice') . '</span><div class="font-medium">' . $agreement['providerTerminationNotice'] . ' ' . __('days') . '</div></div>';
    }

    echo '</div>';

    if (!empty($agreement['immediateTerminationConditions'])) {
        echo '<div class="mb-4"><span class="text-gray-500">' . __('Immediate Termination Conditions') . ':</span>';
        echo '<div class="mt-1 p-3 bg-red-50 rounded">' . nl2br(htmlspecialchars($agreement['immediateTerminationConditions'])) . '</div>';
        echo '</div>';
    }

    if (!empty($agreement['terminationRefundPolicy'])) {
        echo '<div><span class="text-gray-500">' . __('Termination Refund Policy') . ':</span>';
        echo '<div class="mt-1 p-3 bg-gray-50 rounded">' . nl2br(htmlspecialchars($agreement['terminationRefundPolicy'])) . '</div>';
        echo '</div>';
    }

    echo '</div>';

    // ========================================
    // ARTICLE 11: Special Conditions
    // ========================================
    if (!empty($agreement['specialConditions'])) {
        echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
        echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Article 11: Special Conditions') . '</h3>';
        echo '<div class="p-3 bg-yellow-50 rounded">' . nl2br(htmlspecialchars($agreement['specialConditions'])) . '</div>';
        echo '</div>';
    }

    // ========================================
    // ARTICLE 12: Consumer Protection Act
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Article 12: Quebec Consumer Protection Act') . '</h3>';

    echo '<div class="bg-yellow-50 border border-yellow-200 rounded p-4">';
    echo '<p class="text-sm text-yellow-800 mb-3">' . __('This service agreement is subject to the Quebec Consumer Protection Act (Loi sur la protection du consommateur).') . '</p>';
    echo '<ul class="text-sm text-yellow-700 list-disc list-inside space-y-1">';
    echo '<li>' . __('The parent has 10 days to cancel the contract without penalty after receiving the signed copy') . '</li>';
    echo '<li>' . __('All fees and charges must be clearly stated in the contract') . '</li>';
    echo '<li>' . __('The provider must give written notice before any fee increases') . '</li>';
    echo '</ul>';

    if ($agreement['consumerProtectionAcknowledged'] === 'Y') {
        echo '<div class="mt-3 text-green-600"><span class="mr-1">&#10003;</span>' . __('Consumer Protection Act acknowledged');
        if (!empty($agreement['consumerProtectionAcknowledgedDate'])) {
            echo ' ' . __('on') . ' ' . Format::dateTime($agreement['consumerProtectionAcknowledgedDate']);
        }
        echo '</div>';
    } else {
        echo '<div class="mt-3 text-orange-600">' . __('Consumer Protection Act acknowledgment pending') . '</div>';
    }

    echo '</div>';
    echo '</div>';

    // ========================================
    // ANNEXES A-D
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Annexes A-D') . '</h3>';

    // Annex summary
    echo '<div class="grid grid-cols-4 gap-2 mb-4 text-center">';
    echo '<div class="bg-green-100 rounded p-2"><div class="font-bold">' . ($annexSummary['signedCount'] ?? 0) . '</div><div class="text-xs text-gray-600">' . __('Signed') . '</div></div>';
    echo '<div class="bg-orange-100 rounded p-2"><div class="font-bold">' . ($annexSummary['pendingCount'] ?? 0) . '</div><div class="text-xs text-gray-600">' . __('Pending') . '</div></div>';
    echo '<div class="bg-red-100 rounded p-2"><div class="font-bold">' . ($annexSummary['declinedCount'] ?? 0) . '</div><div class="text-xs text-gray-600">' . __('Declined') . '</div></div>';
    echo '<div class="bg-gray-100 rounded p-2"><div class="font-bold">' . ($annexSummary['notApplicableCount'] ?? 0) . '</div><div class="text-xs text-gray-600">' . __('N/A') . '</div></div>';
    echo '</div>';

    // Individual annexes
    foreach ($annexes as $annex) {
        $annexType = $annex['annexType'];
        $annexName = $annexTypeNames[$annexType] ?? __('Annex') . ' ' . $annexType;

        // Status badge
        $annexStatusClasses = [
            'Signed' => 'bg-green-200 text-green-800',
            'Pending' => 'bg-orange-200 text-orange-800',
            'Declined' => 'bg-red-200 text-red-800',
            'NotApplicable' => 'bg-gray-200 text-gray-600',
        ];
        $annexStatusClass = $annexStatusClasses[$annex['status']] ?? 'bg-gray-200 text-gray-800';

        echo '<div class="border rounded p-4 mb-3">';
        echo '<div class="flex justify-between items-start mb-2">';
        echo '<h4 class="font-medium">' . __('Annex') . ' ' . $annexType . ': ' . __($annexName) . '</h4>';
        echo '<span class="px-2 py-1 rounded text-xs ' . $annexStatusClass . '">' . __($annex['status']) . '</span>';
        echo '</div>';

        // Annex-specific content
        switch ($annexType) {
            case 'A': // Field Trips
                echo '<div class="text-sm">';
                echo '<div><span class="text-gray-500">' . __('Field Trips Authorized') . ':</span> ';
                echo '<span class="font-medium">' . ($annex['fieldTripsAuthorized'] === 'Y' ? __('Yes') : __('No')) . '</span></div>';
                if (!empty($annex['fieldTripsConditions'])) {
                    echo '<div class="mt-1"><span class="text-gray-500">' . __('Conditions') . ':</span> ' . htmlspecialchars($annex['fieldTripsConditions']) . '</div>';
                }
                echo '</div>';
                break;

            case 'B': // Hygiene Items
                echo '<div class="text-sm">';
                echo '<div><span class="text-gray-500">' . __('Hygiene Items Included') . ':</span> ';
                echo '<span class="font-medium">' . ($annex['hygieneItemsIncluded'] === 'Y' ? __('Yes') : __('No')) . '</span></div>';
                if (!empty($annex['hygieneItemsDescription'])) {
                    echo '<div class="mt-1"><span class="text-gray-500">' . __('Description') . ':</span> ' . htmlspecialchars($annex['hygieneItemsDescription']) . '</div>';
                }
                if (!empty($annex['hygieneItemsMonthlyFee'])) {
                    echo '<div class="mt-1"><span class="text-gray-500">' . __('Monthly Fee') . ':</span> $' . number_format($annex['hygieneItemsMonthlyFee'], 2) . '</div>';
                }
                echo '</div>';
                break;

            case 'C': // Supplementary Meals
                echo '<div class="text-sm">';
                echo '<div><span class="text-gray-500">' . __('Supplementary Meals Included') . ':</span> ';
                echo '<span class="font-medium">' . ($annex['supplementaryMealsIncluded'] === 'Y' ? __('Yes') : __('No')) . '</span></div>';
                if (!empty($annex['supplementaryMealsDays'])) {
                    echo '<div class="mt-1"><span class="text-gray-500">' . __('Days') . ':</span> ' . htmlspecialchars($annex['supplementaryMealsDays']) . '</div>';
                }
                if (!empty($annex['supplementaryMealsDescription'])) {
                    echo '<div class="mt-1"><span class="text-gray-500">' . __('Description') . ':</span> ' . htmlspecialchars($annex['supplementaryMealsDescription']) . '</div>';
                }
                if (!empty($annex['supplementaryMealsFee'])) {
                    echo '<div class="mt-1"><span class="text-gray-500">' . __('Fee') . ':</span> $' . number_format($annex['supplementaryMealsFee'], 2) . '</div>';
                }
                echo '</div>';
                break;

            case 'D': // Extended Hours
                echo '<div class="text-sm">';
                echo '<div><span class="text-gray-500">' . __('Extended Hours Included') . ':</span> ';
                echo '<span class="font-medium">' . ($annex['extendedHoursIncluded'] === 'Y' ? __('Yes') : __('No')) . '</span></div>';
                if (!empty($annex['extendedHoursStart']) && !empty($annex['extendedHoursEnd'])) {
                    echo '<div class="mt-1"><span class="text-gray-500">' . __('Extended Hours') . ':</span> ' . $annex['extendedHoursStart'] . ' - ' . $annex['extendedHoursEnd'] . '</div>';
                }
                if (!empty($annex['extendedHoursHourlyRate'])) {
                    echo '<div class="mt-1"><span class="text-gray-500">' . __('Hourly Rate') . ':</span> $' . number_format($annex['extendedHoursHourlyRate'], 2) . '</div>';
                }
                if (!empty($annex['extendedHoursMaxDaily'])) {
                    echo '<div class="mt-1"><span class="text-gray-500">' . __('Max Daily Fee') . ':</span> $' . number_format($annex['extendedHoursMaxDaily'], 2) . '</div>';
                }
                echo '</div>';
                break;
        }

        // Signed info
        if ($annex['status'] === 'Signed' && !empty($annex['signedDate'])) {
            $signerName = Format::name('', $annex['signedByName'] ?? '', $annex['signedBySurname'] ?? '', 'Staff');
            echo '<div class="mt-2 text-xs text-gray-500">';
            echo __('Signed') . ' ' . Format::dateTime($annex['signedDate']);
            if (!empty($signerName)) {
                echo ' ' . __('by') . ' ' . $signerName;
            }
            echo '</div>';
        }

        echo '</div>'; // End annex card
    }

    echo '</div>'; // End Annexes section

    // ========================================
    // ARTICLE 13: Signatures
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6 mb-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Article 13: Signatures') . '</h3>';

    if (empty($signatures)) {
        echo '<p class="text-gray-500">' . __('No signatures have been collected yet.') . '</p>';
        if ($agreement['status'] === 'Pending Signature') {
            echo '<a href="' . $session->get('absoluteURL') . '/index.php?q=/modules/ServiceAgreement/serviceAgreement_sign.php&gibbonServiceAgreementID=' . $gibbonServiceAgreementID . '" class="inline-block mt-3 bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">' . __('Collect Signatures') . '</a>';
        }
    } else {
        // Signatures table
        echo '<table class="w-full border-collapse">';
        echo '<thead>';
        echo '<tr class="bg-gray-100">';
        echo '<th class="border px-4 py-2 text-left">' . __('Signer Type') . '</th>';
        echo '<th class="border px-4 py-2 text-left">' . __('Name') . '</th>';
        echo '<th class="border px-4 py-2 text-left">' . __('Date/Time') . '</th>';
        echo '<th class="border px-4 py-2 text-left">' . __('IP Address') . '</th>';
        echo '<th class="border px-4 py-2 text-left">' . __('Verified') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($signatures as $signature) {
            echo '<tr>';
            echo '<td class="border px-4 py-2">' . __($signature['signerType']) . '</td>';
            echo '<td class="border px-4 py-2">' . htmlspecialchars($signature['signerName']);
            if (!empty($signature['signerEmail'])) {
                echo '<div class="text-xs text-gray-500">' . htmlspecialchars($signature['signerEmail']) . '</div>';
            }
            echo '</td>';
            echo '<td class="border px-4 py-2">' . (!empty($signature['signedDate']) ? Format::dateTime($signature['signedDate']) : '-') . '</td>';
            echo '<td class="border px-4 py-2 font-mono text-sm">' . htmlspecialchars($signature['ipAddress'] ?? '-') . '</td>';
            echo '<td class="border px-4 py-2">';
            if ($signature['verified'] === 'Y') {
                echo '<span class="text-green-600">&#10003; ' . __('Verified') . '</span>';
                if (!empty($signature['verifiedDate'])) {
                    echo '<div class="text-xs text-gray-500">' . Format::dateTime($signature['verifiedDate']) . '</div>';
                }
            } else {
                echo '<span class="text-orange-500">' . __('Pending') . '</span>';
            }
            echo '</td>';
            echo '</tr>';

            // Consumer Protection acknowledgment
            if ($signature['consumerProtectionAcknowledged'] === 'Y') {
                echo '<tr class="bg-yellow-50">';
                echo '<td colspan="5" class="border px-4 py-2 text-sm text-yellow-700">';
                echo '<span class="mr-1">&#10003;</span>' . __('Consumer Protection Act acknowledged at time of signature');
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody>';
        echo '</table>';

        // Check for missing signatures
        $hasParent = false;
        $hasProvider = false;
        foreach ($signatures as $sig) {
            if ($sig['signerType'] === 'Parent') $hasParent = true;
            if ($sig['signerType'] === 'Provider') $hasProvider = true;
        }

        if (!$hasParent || !$hasProvider) {
            echo '<div class="mt-4 p-3 bg-orange-50 rounded">';
            echo '<h4 class="font-medium text-orange-800 mb-2">' . __('Missing Signatures') . '</h4>';
            echo '<ul class="list-disc list-inside text-orange-700 text-sm">';
            if (!$hasParent) echo '<li>' . __('Parent signature required') . '</li>';
            if (!$hasProvider) echo '<li>' . __('Provider signature required') . '</li>';
            echo '</ul>';
            echo '</div>';
        }
    }

    echo '</div>'; // End signatures section

    // ========================================
    // Audit Information
    // ========================================
    echo '<div class="bg-white rounded-lg shadow p-6">';
    echo '<h3 class="text-lg font-semibold border-b pb-2 mb-4">' . __('Audit Information') . '</h3>';

    echo '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 text-sm">';

    echo '<div><span class="text-gray-500">' . __('Created') . '</span><div>' . (!empty($agreement['timestampCreated']) ? Format::dateTime($agreement['timestampCreated']) : '-') . '</div></div>';

    if (!empty($agreement['createdByName'])) {
        $createdByName = Format::name('', $agreement['createdByName'], $agreement['createdBySurname'], 'Staff');
        echo '<div><span class="text-gray-500">' . __('Created By') . '</span><div>' . $createdByName . '</div></div>';
    }

    if (!empty($agreement['agreementCompletedDate'])) {
        echo '<div><span class="text-gray-500">' . __('Completed') . '</span><div>' . Format::dateTime($agreement['agreementCompletedDate']) . '</div></div>';
    }

    if (!empty($agreement['timestampModified'])) {
        echo '<div><span class="text-gray-500">' . __('Last Modified') . '</span><div>' . Format::dateTime($agreement['timestampModified']) . '</div></div>';
    }

    echo '</div>';

    // Language preference
    if (!empty($agreement['languagePreference'])) {
        $languages = ['fr' => __('French'), 'en' => __('English')];
        echo '<div class="mt-4"><span class="text-gray-500">' . __('Agreement Language') . ':</span> ' . ($languages[$agreement['languagePreference']] ?? $agreement['languagePreference']) . '</div>';
    }

    echo '</div>';
}
