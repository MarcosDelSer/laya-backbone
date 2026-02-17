<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuibers and the Gibbon community (https://gibbonedu.org/about/)
Copyright © 2010, Gibbon Foundation
Gibbon™, Gibbon Education Ltd. (Gibbon Core) and Gibbon LAYA are trademarks of Gibbon Education Ltd.

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

/**
 * Service Agreement Module Functions
 *
 * Helper functions for the Service Agreement module including validation,
 * date calculations, and Quebec Consumer Protection Act compliance.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// =============================================================================
// AGREEMENT VALIDATION FUNCTIONS
// =============================================================================

/**
 * Validate service agreement data before saving.
 *
 * @param array $data Agreement data array
 * @return array ['valid' => bool, 'errors' => array]
 */
function validateServiceAgreementData($data)
{
    $errors = [];

    // Required fields
    $requiredFields = [
        'gibbonPersonIDChild' => __('Child'),
        'gibbonPersonIDParent' => __('Parent/Guardian'),
        'gibbonSchoolYearID' => __('School Year'),
        'effectiveDate' => __('Effective Date'),
        'providerName' => __('Provider Name'),
    ];

    foreach ($requiredFields as $field => $label) {
        if (empty($data[$field])) {
            $errors[] = sprintf(__('%s is required.'), $label);
        }
    }

    // Validate effective date is not in the past (unless editing existing)
    if (!empty($data['effectiveDate'])) {
        $effectiveDate = strtotime($data['effectiveDate']);
        if ($effectiveDate < strtotime('today') && empty($data['gibbonServiceAgreementID'])) {
            $errors[] = __('Effective date cannot be in the past for new agreements.');
        }
    }

    // Validate expiration date is after effective date
    if (!empty($data['effectiveDate']) && !empty($data['expirationDate'])) {
        if (strtotime($data['expirationDate']) <= strtotime($data['effectiveDate'])) {
            $errors[] = __('Expiration date must be after the effective date.');
        }
    }

    // Validate daily contribution amount if contribution type is Reduced
    if (!empty($data['contributionType']) && $data['contributionType'] === 'Reduced') {
        if (empty($data['dailyReducedContribution']) || $data['dailyReducedContribution'] <= 0) {
            $errors[] = __('Daily reduced contribution amount is required for reduced contribution type.');
        }
    }

    // Validate operating hours
    if (!empty($data['operatingHoursStart']) && !empty($data['operatingHoursEnd'])) {
        if (strtotime($data['operatingHoursStart']) >= strtotime($data['operatingHoursEnd'])) {
            $errors[] = __('Operating hours end time must be after start time.');
        }
    }

    // Validate late pickup fee
    if (!empty($data['latePickupFeePerMinute']) && $data['latePickupFeePerMinute'] < 0) {
        $errors[] = __('Late pickup fee cannot be negative.');
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
    ];
}

/**
 * Validate signature data before processing.
 *
 * @param array $data Signature data array
 * @return array ['valid' => bool, 'errors' => array]
 */
function validateSignatureData($data)
{
    $errors = [];

    // Required fields
    if (empty($data['signerType'])) {
        $errors[] = __('Signer type is required.');
    }

    if (empty($data['signerName'])) {
        $errors[] = __('Signer name is required.');
    }

    // Validate signer type
    $validSignerTypes = ['Parent', 'Provider', 'Witness'];
    if (!empty($data['signerType']) && !in_array($data['signerType'], $validSignerTypes)) {
        $errors[] = __('Invalid signer type.');
    }

    // Validate signature method and data
    if (!empty($data['signatureType'])) {
        if ($data['signatureType'] === 'Typed' && empty($data['typedSignature'])) {
            $errors[] = __('Typed signature is required.');
        }
        if ($data['signatureType'] === 'Drawn' && empty($data['signatureData'])) {
            $errors[] = __('Drawn signature is required.');
        }
    }

    // Validate required acknowledgments
    if (empty($data['consumerProtectionAcknowledged'])) {
        $errors[] = __('Consumer Protection Act acknowledgment is required.');
    }

    if (empty($data['termsAccepted'])) {
        $errors[] = __('Terms acceptance is required.');
    }

    if (empty($data['legalAcknowledgment'])) {
        $errors[] = __('Legal acknowledgment is required.');
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors,
    ];
}

/**
 * Validate agreement status transition.
 *
 * @param string $currentStatus Current agreement status
 * @param string $newStatus Proposed new status
 * @return bool True if transition is valid
 */
function isValidStatusTransition($currentStatus, $newStatus)
{
    $validTransitions = [
        'Draft' => ['Pending Signature', 'Cancelled'],
        'Pending Signature' => ['Active', 'Draft', 'Cancelled'],
        'Active' => ['Expired', 'Terminated', 'Cancelled'],
        'Expired' => ['Active'], // Can be renewed
        'Terminated' => [],
        'Cancelled' => [],
    ];

    if (!isset($validTransitions[$currentStatus])) {
        return false;
    }

    return in_array($newStatus, $validTransitions[$currentStatus]);
}

/**
 * Check if agreement has all required signatures.
 *
 * @param array $signatures Array of signature records
 * @return bool True if all required signatures are present
 */
function hasAllRequiredSignatures($signatures)
{
    $hasParent = false;
    $hasProvider = false;

    foreach ($signatures as $signature) {
        if ($signature['signerType'] === 'Parent') {
            $hasParent = true;
        }
        if ($signature['signerType'] === 'Provider') {
            $hasProvider = true;
        }
    }

    return $hasParent && $hasProvider;
}

// =============================================================================
// DATE CALCULATION FUNCTIONS
// =============================================================================

/**
 * Calculate the cooling-off period end date (10 days per Quebec Consumer Protection Act).
 *
 * @param string $signedDate The date the agreement was signed
 * @return string End date of cooling-off period (Y-m-d format)
 */
function getCoolingOffPeriodEndDate($signedDate)
{
    $signedTime = strtotime($signedDate);
    $coolingOffDays = 10;
    $endTime = strtotime("+{$coolingOffDays} days", $signedTime);
    return date('Y-m-d', $endTime);
}

/**
 * Check if the current date is within the cooling-off period.
 *
 * @param string $signedDate The date the agreement was signed
 * @return bool True if within cooling-off period
 */
function isWithinCoolingOffPeriod($signedDate)
{
    $endDate = getCoolingOffPeriodEndDate($signedDate);
    return strtotime($endDate) >= strtotime('today');
}

/**
 * Calculate days remaining in cooling-off period.
 *
 * @param string $signedDate The date the agreement was signed
 * @return int Days remaining (negative if expired)
 */
function getCoolingOffDaysRemaining($signedDate)
{
    $endDate = getCoolingOffPeriodEndDate($signedDate);
    $endTime = strtotime($endDate);
    $now = strtotime('today');

    return (int) floor(($endTime - $now) / (60 * 60 * 24));
}

/**
 * Calculate the termination notice date.
 *
 * @param int $noticeDays Number of days notice required
 * @param string|null $fromDate Starting date (defaults to today)
 * @return string Earliest termination date (Y-m-d format)
 */
function getTerminationNoticeDate($noticeDays, $fromDate = null)
{
    $fromTime = $fromDate ? strtotime($fromDate) : time();
    $terminationTime = strtotime("+{$noticeDays} days", $fromTime);
    return date('Y-m-d', $terminationTime);
}

/**
 * Calculate agreement expiration date based on effective date and duration.
 *
 * @param string $effectiveDate Agreement effective date
 * @param int $durationMonths Duration in months (default 12)
 * @return string Expiration date (Y-m-d format)
 */
function calculateExpirationDate($effectiveDate, $durationMonths = 12)
{
    $effectiveTime = strtotime($effectiveDate);
    $expirationTime = strtotime("+{$durationMonths} months", $effectiveTime);
    return date('Y-m-d', $expirationTime);
}

/**
 * Calculate days until agreement expiration.
 *
 * @param string $expirationDate Agreement expiration date
 * @return int Days until expiration (negative if expired)
 */
function getDaysUntilExpiration($expirationDate)
{
    $expirationTime = strtotime($expirationDate);
    $now = strtotime('today');

    return (int) floor(($expirationTime - $now) / (60 * 60 * 24));
}

/**
 * Check if an agreement is expiring soon.
 *
 * @param string $expirationDate Agreement expiration date
 * @param int $warningDays Days threshold for "expiring soon" (default 30)
 * @return bool True if expiring within threshold
 */
function isAgreementExpiringSoon($expirationDate, $warningDays = 30)
{
    $daysRemaining = getDaysUntilExpiration($expirationDate);
    return $daysRemaining >= 0 && $daysRemaining <= $warningDays;
}

/**
 * Check if an agreement has expired.
 *
 * @param string $expirationDate Agreement expiration date
 * @return bool True if expired
 */
function isAgreementExpired($expirationDate)
{
    return strtotime($expirationDate) < strtotime('today');
}

/**
 * Calculate renewal notice date (when parent should be notified about renewal).
 *
 * @param string $expirationDate Agreement expiration date
 * @param int $noticeRequired Days of notice required (default 30)
 * @return string Date to send renewal notice (Y-m-d format)
 */
function getRenewalNoticeDate($expirationDate, $noticeRequired = 30)
{
    $expirationTime = strtotime($expirationDate);
    $noticeTime = strtotime("-{$noticeRequired} days", $expirationTime);
    return date('Y-m-d', $noticeTime);
}

/**
 * Format date range for display.
 *
 * @param string $startDate Start date
 * @param string|null $endDate End date (null for ongoing)
 * @return string Formatted date range
 */
function formatAgreementDateRange($startDate, $endDate = null)
{
    if (empty($endDate)) {
        return date('M j, Y', strtotime($startDate)) . ' - ' . __('Ongoing');
    }

    return date('M j, Y', strtotime($startDate)) . ' - ' . date('M j, Y', strtotime($endDate));
}

// =============================================================================
// CONSUMER PROTECTION ACT NOTICE FUNCTIONS
// =============================================================================

/**
 * Get the Quebec Consumer Protection Act notice text in French.
 *
 * @return string HTML formatted notice in French
 */
function getConsumerProtectionNoticeFr()
{
    $notice = '<div class="consumer-protection-notice">';
    $notice .= '<h4>' . __('Avis important (Loi sur la protection du consommateur du Québec)') . '</h4>';
    $notice .= '<ul>';
    $notice .= '<li>' . __('Vous pouvez résilier le présent contrat sans pénalité dans les 10 jours suivant la réception de la copie signée.') . '</li>';
    $notice .= '<li>' . __('Tous les frais et charges sont clairement indiqués dans cette entente.') . '</li>';
    $notice .= '<li>' . __('Le prestataire doit vous donner un avis écrit avant toute augmentation de frais.') . '</li>';
    $notice .= '<li>' . __('Cette entente est assujettie à la Loi sur la protection du consommateur du Québec.') . '</li>';
    $notice .= '</ul>';
    $notice .= '</div>';

    return $notice;
}

/**
 * Get the Quebec Consumer Protection Act notice text in English.
 *
 * @return string HTML formatted notice in English
 */
function getConsumerProtectionNoticeEn()
{
    $notice = '<div class="consumer-protection-notice">';
    $notice .= '<h4>' . __('Important Notice (Quebec Consumer Protection Act)') . '</h4>';
    $notice .= '<ul>';
    $notice .= '<li>' . __('You have 10 days to cancel this contract without penalty after receiving the signed copy.') . '</li>';
    $notice .= '<li>' . __('All fees and charges are clearly stated in this agreement.') . '</li>';
    $notice .= '<li>' . __('The provider must give written notice before any fee increases.') . '</li>';
    $notice .= '<li>' . __('This agreement is subject to the Quebec Consumer Protection Act.') . '</li>';
    $notice .= '</ul>';
    $notice .= '</div>';

    return $notice;
}

/**
 * Get the Consumer Protection Act notice in the specified language.
 *
 * @param string $language Language code ('fr' or 'en')
 * @return string HTML formatted notice
 */
function getConsumerProtectionNotice($language = 'en')
{
    return $language === 'fr' ? getConsumerProtectionNoticeFr() : getConsumerProtectionNoticeEn();
}

/**
 * Get bilingual Consumer Protection Act notice.
 *
 * @return string HTML formatted bilingual notice
 */
function getConsumerProtectionNoticeBilingual()
{
    return getConsumerProtectionNoticeFr() . getConsumerProtectionNoticeEn();
}

/**
 * Get consumer protection cancellation deadline message.
 *
 * @param string $signedDate Agreement signed date
 * @param string $language Language code ('fr' or 'en')
 * @return string Formatted message with deadline
 */
function getConsumerProtectionDeadlineMessage($signedDate, $language = 'en')
{
    $endDate = getCoolingOffPeriodEndDate($signedDate);
    $daysRemaining = getCoolingOffDaysRemaining($signedDate);
    $formattedEndDate = date('F j, Y', strtotime($endDate));

    if ($daysRemaining < 0) {
        if ($language === 'fr') {
            return __('La période de résiliation de 10 jours est expirée.');
        }
        return __('The 10-day cancellation period has expired.');
    }

    if ($language === 'fr') {
        return sprintf(
            __('Vous avez jusqu\'au %s (%d jours restants) pour résilier ce contrat sans pénalité.'),
            $formattedEndDate,
            $daysRemaining
        );
    }

    return sprintf(
        __('You have until %s (%d days remaining) to cancel this contract without penalty.'),
        $formattedEndDate,
        $daysRemaining
    );
}

/**
 * Get Consumer Protection Act key points as array.
 *
 * @param string $language Language code ('fr' or 'en')
 * @return array Key points
 */
function getConsumerProtectionKeyPoints($language = 'en')
{
    if ($language === 'fr') {
        return [
            __('Résiliation de 10 jours sans pénalité'),
            __('Tous les frais doivent être clairement indiqués'),
            __('Avis écrit requis pour augmentation de frais'),
            __('Protection par la loi québécoise'),
        ];
    }

    return [
        __('10-day cancellation without penalty'),
        __('All fees must be clearly stated'),
        __('Written notice required for fee increases'),
        __('Protected by Quebec law'),
    ];
}

// =============================================================================
// FORMATTING AND DISPLAY FUNCTIONS
// =============================================================================

/**
 * Get agreement status badge HTML.
 *
 * @param string $status Agreement status
 * @return string HTML span element with styled badge
 */
function getAgreementStatusBadge($status)
{
    $statusClasses = [
        'Draft' => 'bg-gray-200 text-gray-800',
        'Pending Signature' => 'bg-orange-200 text-orange-800',
        'Active' => 'bg-green-200 text-green-800',
        'Expired' => 'bg-red-200 text-red-800',
        'Terminated' => 'bg-red-200 text-red-800',
        'Cancelled' => 'bg-gray-300 text-gray-600',
    ];

    $class = $statusClasses[$status] ?? 'bg-gray-200 text-gray-800';
    return '<span class="px-2 py-1 rounded text-xs font-medium ' . $class . '">' . __($status) . '</span>';
}

/**
 * Get annex status badge HTML.
 *
 * @param string $status Annex status
 * @return string HTML span element with styled badge
 */
function getAnnexStatusBadge($status)
{
    $statusClasses = [
        'Signed' => 'bg-green-200 text-green-800',
        'Pending' => 'bg-orange-200 text-orange-800',
        'Declined' => 'bg-red-200 text-red-800',
        'NotApplicable' => 'bg-gray-200 text-gray-600',
    ];

    $class = $statusClasses[$status] ?? 'bg-gray-200 text-gray-800';
    return '<span class="px-2 py-1 rounded text-xs font-medium ' . $class . '">' . __($status) . '</span>';
}

/**
 * Get signature verification badge HTML.
 *
 * @param string $verified 'Y' or 'N'
 * @return string HTML span element with styled badge
 */
function getSignatureVerificationBadge($verified)
{
    if ($verified === 'Y') {
        return '<span class="text-green-600">&#10003; ' . __('Verified') . '</span>';
    }
    return '<span class="text-orange-500">' . __('Pending') . '</span>';
}

/**
 * Format currency amount for display.
 *
 * @param float $amount Amount
 * @param string $currency Currency code (default CAD)
 * @return string Formatted currency string
 */
function formatAgreementCurrency($amount, $currency = 'CAD')
{
    return '$' . number_format($amount, 2);
}

/**
 * Format contribution type for display.
 *
 * @param string $type Contribution type
 * @param float|null $dailyAmount Daily amount for reduced contribution
 * @return string Formatted contribution info
 */
function formatContributionType($type, $dailyAmount = null)
{
    $formatted = __($type);

    if ($type === 'Reduced' && $dailyAmount !== null) {
        $formatted .= ' (' . formatAgreementCurrency($dailyAmount) . '/' . __('day') . ')';
    }

    return $formatted;
}

/**
 * Get operating days display format.
 *
 * @param string|array $operatingDays Comma-separated string or array of day codes
 * @return string Formatted days string
 */
function formatOperatingDays($operatingDays)
{
    $daysMap = [
        'Mon' => __('Monday'),
        'Tue' => __('Tuesday'),
        'Wed' => __('Wednesday'),
        'Thu' => __('Thursday'),
        'Fri' => __('Friday'),
        'Sat' => __('Saturday'),
        'Sun' => __('Sunday'),
    ];

    if (is_string($operatingDays)) {
        $operatingDays = array_map('trim', explode(',', $operatingDays));
    }

    $dayNames = [];
    foreach ($operatingDays as $day) {
        $dayNames[] = $daysMap[$day] ?? $day;
    }

    return implode(', ', $dayNames);
}

/**
 * Format operating hours for display.
 *
 * @param string $startTime Start time
 * @param string $endTime End time
 * @return string Formatted hours string
 */
function formatOperatingHours($startTime, $endTime)
{
    return $startTime . ' - ' . $endTime;
}

// =============================================================================
// CALCULATION FUNCTIONS
// =============================================================================

/**
 * Calculate total monthly fees from agreement data.
 *
 * @param array $agreement Agreement data
 * @param array $annexes Array of annex data
 * @return float Total monthly fees
 */
function calculateTotalMonthlyFees($agreement, $annexes = [])
{
    $total = 0.0;

    // Base contribution (daily rate * average days per month)
    if (!empty($agreement['dailyReducedContribution'])) {
        // Assuming 20 working days per month average
        $total += $agreement['dailyReducedContribution'] * 20;
    }

    // Add annex fees
    foreach ($annexes as $annex) {
        if ($annex['status'] !== 'Signed') {
            continue;
        }

        switch ($annex['annexType']) {
            case 'B': // Hygiene items
                if (!empty($annex['hygieneItemsMonthlyFee'])) {
                    $total += $annex['hygieneItemsMonthlyFee'];
                }
                break;
            case 'C': // Supplementary meals
                if (!empty($annex['supplementaryMealsFee'])) {
                    // Estimate monthly based on frequency
                    $total += $annex['supplementaryMealsFee'] * 4; // Assuming weekly
                }
                break;
        }
    }

    return $total;
}

/**
 * Calculate late pickup fee.
 *
 * @param int $minutesLate Number of minutes late
 * @param float $feePerMinute Fee per minute
 * @param int $gracePeriod Grace period in minutes (no charge)
 * @param float|null $maxFee Maximum fee cap
 * @return float Calculated fee
 */
function calculateLatePickupFee($minutesLate, $feePerMinute, $gracePeriod = 0, $maxFee = null)
{
    // Apply grace period
    $chargeableMinutes = max(0, $minutesLate - $gracePeriod);

    if ($chargeableMinutes <= 0) {
        return 0.0;
    }

    $fee = $chargeableMinutes * $feePerMinute;

    // Apply max fee cap if set
    if ($maxFee !== null && $fee > $maxFee) {
        $fee = $maxFee;
    }

    return $fee;
}

/**
 * Calculate extended hours fee.
 *
 * @param float $hours Number of extended hours
 * @param float $hourlyRate Hourly rate
 * @param float|null $maxDaily Maximum daily fee cap
 * @return float Calculated fee
 */
function calculateExtendedHoursFee($hours, $hourlyRate, $maxDaily = null)
{
    $fee = $hours * $hourlyRate;

    // Apply max daily cap if set
    if ($maxDaily !== null && $fee > $maxDaily) {
        $fee = $maxDaily;
    }

    return $fee;
}

/**
 * Get the current Quebec reduced contribution rate.
 *
 * @return float Current daily reduced contribution rate
 */
function getQuebecReducedContributionRate()
{
    // As of 2024, the reduced contribution is $9.35/day
    // This should ideally be loaded from settings
    return 9.35;
}

/**
 * Calculate estimated annual cost.
 *
 * @param float $dailyRate Daily contribution rate
 * @param int $daysPerWeek Days per week attending
 * @param int $weeksPerYear Weeks per year (accounting for closures)
 * @return float Estimated annual cost
 */
function calculateAnnualCost($dailyRate, $daysPerWeek, $weeksPerYear = 48)
{
    return $dailyRate * $daysPerWeek * $weeksPerYear;
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

/**
 * Generate a hash for signature verification.
 *
 * @param array $signatureData Signature data including name, date, IP
 * @return string SHA-256 hash
 */
function generateSignatureHash($signatureData)
{
    $hashInput = implode('|', [
        $signatureData['gibbonServiceAgreementID'] ?? '',
        $signatureData['signerType'] ?? '',
        $signatureData['signerName'] ?? '',
        $signatureData['signedDate'] ?? date('Y-m-d H:i:s'),
        $signatureData['ipAddress'] ?? '',
    ]);

    return hash('sha256', $hashInput);
}

/**
 * Verify a signature hash.
 *
 * @param string $hash Hash to verify
 * @param array $signatureData Original signature data
 * @return bool True if hash matches
 */
function verifySignatureHash($hash, $signatureData)
{
    $expectedHash = generateSignatureHash($signatureData);
    return hash_equals($expectedHash, $hash);
}

/**
 * Sanitize agreement data for display.
 *
 * @param array $agreement Agreement data
 * @return array Sanitized agreement data
 */
function sanitizeAgreementForDisplay($agreement)
{
    $fieldsToSanitize = [
        'providerName', 'childName', 'parentName',
        'providerAddress', 'parentAddress',
        'serviceDescription', 'attendancePattern',
        'specialConditions', 'closureDatesText',
        'medicalAbsencePolicy', 'immediateTerminationConditions',
        'terminationRefundPolicy',
    ];

    foreach ($fieldsToSanitize as $field) {
        if (isset($agreement[$field])) {
            $agreement[$field] = htmlspecialchars($agreement[$field], ENT_QUOTES, 'UTF-8');
        }
    }

    return $agreement;
}

/**
 * Check if user can edit agreement.
 *
 * @param array $agreement Agreement data
 * @param string $userRole User's role
 * @return bool True if user can edit
 */
function canEditAgreement($agreement, $userRole)
{
    // Only Draft agreements can be edited
    if ($agreement['status'] !== 'Draft') {
        return false;
    }

    // Admin and staff can always edit drafts
    if (in_array($userRole, ['Administrator', 'Staff'])) {
        return true;
    }

    return false;
}

/**
 * Check if user can sign agreement.
 *
 * @param array $agreement Agreement data
 * @param string $userRole User's role
 * @param int $userPersonID User's person ID
 * @return bool True if user can sign
 */
function canSignAgreement($agreement, $userRole, $userPersonID)
{
    // Only Pending Signature agreements can be signed
    if (!in_array($agreement['status'], ['Pending Signature', 'Active'])) {
        return false;
    }

    // Check if already fully signed
    if ($agreement['allSignaturesComplete'] === 'Y') {
        return false;
    }

    // Parents can sign their own child's agreement
    if ($userRole === 'Parent' && $agreement['gibbonPersonIDParent'] == $userPersonID) {
        return true;
    }

    // Staff/Admin can sign as provider
    if (in_array($userRole, ['Administrator', 'Staff'])) {
        return true;
    }

    return false;
}

/**
 * Get agreement summary for notifications.
 *
 * @param array $agreement Agreement data
 * @return array Summary data for notification templates
 */
function getAgreementSummaryForNotification($agreement)
{
    return [
        'agreementNumber' => $agreement['agreementNumber'] ?? '',
        'childName' => $agreement['childName'] ?? '',
        'parentName' => $agreement['parentName'] ?? '',
        'providerName' => $agreement['providerName'] ?? '',
        'effectiveDate' => !empty($agreement['effectiveDate']) ? date('M j, Y', strtotime($agreement['effectiveDate'])) : '',
        'expirationDate' => !empty($agreement['expirationDate']) ? date('M j, Y', strtotime($agreement['expirationDate'])) : __('No expiration'),
        'status' => $agreement['status'] ?? '',
        'dailyContribution' => !empty($agreement['dailyReducedContribution']) ? formatAgreementCurrency($agreement['dailyReducedContribution']) : '',
    ];
}
