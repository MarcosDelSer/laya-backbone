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

namespace Gibbon\Module\EnhancedFinance;

use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Module\EnhancedFinance\Domain\Releve24Gateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;

/**
 * Quebec Relevé 24 (RL-24) Business Logic
 *
 * Handles the generation and calculation of Quebec RL-24 tax slips for childcare expenses.
 * RL-24 is required by Revenu Québec for childcare expense deductions.
 *
 * Critical Business Rule: RL-24 amounts must reflect PAID amounts at filing time,
 * NOT invoiced amounts. If additional payments are received after initial RL-24 filing,
 * an amended RL-24 (type A) must be issued.
 *
 * Box Definitions:
 * - Box A: Slip Type (R=original, A=amended, D=cancelled)
 * - Box B: Days of Care (actual paid days, not calendar or invoiced days)
 * - Box C: Total Amounts Paid (all payments received)
 * - Box D: Non-Qualifying Expenses (medical, transport, teaching, etc.)
 * - Box E: Qualifying Expenses (Box C - Box D)
 * - Box H: Provider SIN (XXX-XXX-XXX format)
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class Releve24
{
    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var InvoiceGateway
     */
    protected $invoiceGateway;

    /**
     * @var PaymentGateway
     */
    protected $paymentGateway;

    /**
     * @var Releve24Gateway
     */
    protected $releve24Gateway;

    /**
     * Non-qualifying expense types that must be excluded from Box E.
     * These are defined by Revenu Québec and cannot be claimed for childcare deductions.
     *
     * @var array
     */
    public const NON_QUALIFYING_EXPENSE_TYPES = [
        'medical',           // Medical or hospital care
        'hospital',          // Hospital care
        'transportation',    // Transportation services
        'transport',         // Transportation services (alternate)
        'teaching',          // Teaching services
        'education',         // Educational services
        'fieldtrip',         // Field trips
        'field_trip',        // Field trips (alternate)
        'registration',      // Registration fees
        'registration_fee',  // Registration fees (alternate)
        'late_fee',          // Late payment penalties
        'late_penalty',      // Late payment penalties (alternate)
        'penalty',           // Penalties
        'admin_fee',         // Administrative fees (non-care related)
        'supply_fee',        // Supply fees (non-care related)
        'meal_supplement',   // Additional meal supplements (above basic)
    ];

    /**
     * Slip types for RL-24
     */
    public const SLIP_TYPE_ORIGINAL = 'R';
    public const SLIP_TYPE_AMENDED = 'A';
    public const SLIP_TYPE_CANCELLED = 'D';

    /**
     * Status values
     */
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_GENERATED = 'Generated';
    public const STATUS_SENT = 'Sent';
    public const STATUS_FILED = 'Filed';
    public const STATUS_AMENDED = 'Amended';

    /**
     * Constructor.
     *
     * @param Connection $db
     * @param SettingGateway $settingGateway
     * @param InvoiceGateway $invoiceGateway
     * @param PaymentGateway $paymentGateway
     * @param Releve24Gateway $releve24Gateway
     */
    public function __construct(
        Connection $db,
        SettingGateway $settingGateway,
        InvoiceGateway $invoiceGateway,
        PaymentGateway $paymentGateway,
        Releve24Gateway $releve24Gateway
    ) {
        $this->db = $db;
        $this->settingGateway = $settingGateway;
        $this->invoiceGateway = $invoiceGateway;
        $this->paymentGateway = $paymentGateway;
        $this->releve24Gateway = $releve24Gateway;
    }

    /**
     * Generate RL-24 data for a child for a given tax year.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $gibbonFamilyID Family ID
     * @param int $taxYear Tax year (YYYY format)
     * @param int $createdByID Staff ID creating the RL-24
     * @return array RL-24 data array ready for storage or rendering
     * @throws \InvalidArgumentException If required data is missing
     */
    public function generateReleve24($gibbonPersonID, $gibbonFamilyID, $taxYear, $createdByID)
    {
        // Validate inputs
        if (empty($gibbonPersonID) || empty($gibbonFamilyID) || empty($taxYear)) {
            throw new \InvalidArgumentException('Person ID, Family ID, and Tax Year are required');
        }

        // Determine slip type (R for new, A for amendment)
        $slipType = $this->determineSlipType($gibbonPersonID, $taxYear);

        // Get provider information from settings
        $providerSIN = $this->getProviderSIN();
        $providerName = $this->settingGateway->getSettingByScope('Enhanced Finance', 'providerName') ?? '';

        // Get child and family information
        $childInfo = $this->getChildInfo($gibbonPersonID);
        $familyInfo = $this->getFamilyInfo($gibbonFamilyID);
        $recipientInfo = $this->getRecipientInfo($gibbonFamilyID);

        // Calculate Box B - Days of Care
        $daysOfCare = $this->calculateDaysOfCare($gibbonPersonID, $taxYear);

        // Calculate Box C - Total Amounts Paid
        $totalAmountsPaid = $this->calculateTotalAmountsPaid($gibbonPersonID, $taxYear);

        // Calculate Box D - Non-Qualifying Expenses
        $nonQualifyingExpenses = $this->calculateNonQualifyingExpenses($gibbonPersonID, $taxYear);

        // Calculate Box E - Qualifying Expenses (C - D)
        $qualifyingExpenses = $this->calculateQualifyingExpenses($totalAmountsPaid, $nonQualifyingExpenses);

        // Format child name for RL-24
        $childName = $this->formatName($childInfo['surname'] ?? '', $childInfo['preferredName'] ?? '');

        // Format recipient name for RL-24
        $recipientName = $this->formatName($recipientInfo['surname'] ?? '', $recipientInfo['preferredName'] ?? '');

        // Build RL-24 data array
        $releve24Data = [
            'gibbonPersonID' => $gibbonPersonID,
            'gibbonFamilyID' => $gibbonFamilyID,
            'taxYear' => $taxYear,
            'slipType' => $slipType,
            'daysOfCare' => $daysOfCare,
            'totalAmountsPaid' => $totalAmountsPaid,
            'nonQualifyingExpenses' => $nonQualifyingExpenses,
            'qualifyingExpenses' => $qualifyingExpenses,
            'providerSIN' => $providerSIN,
            'recipientSIN' => $this->formatSIN($recipientInfo['sin'] ?? ''),
            'recipientName' => $recipientName,
            'childName' => $childName,
            'generatedAt' => date('Y-m-d H:i:s'),
            'status' => self::STATUS_DRAFT,
            'createdByID' => $createdByID,
        ];

        return $releve24Data;
    }

    /**
     * Generate and save RL-24 to database.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $gibbonFamilyID Family ID
     * @param int $taxYear Tax year (YYYY format)
     * @param int $createdByID Staff ID creating the RL-24
     * @return int|false RL-24 ID on success, false on failure
     */
    public function generateAndSaveReleve24($gibbonPersonID, $gibbonFamilyID, $taxYear, $createdByID)
    {
        $releve24Data = $this->generateReleve24($gibbonPersonID, $gibbonFamilyID, $taxYear, $createdByID);

        // Change status to Generated since we're saving it
        $releve24Data['status'] = self::STATUS_GENERATED;

        // Generate slip number
        $releve24Data['slipNumber'] = $this->generateSlipNumber($taxYear);

        return $this->releve24Gateway->insertReleve24($releve24Data);
    }

    /**
     * Determine the slip type based on whether an original RL-24 exists.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $taxYear Tax year
     * @return string Slip type (R, A, or D)
     */
    public function determineSlipType($gibbonPersonID, $taxYear)
    {
        // Check if an original slip already exists and has been sent/filed
        if ($this->releve24Gateway->hasOriginalReleve24($gibbonPersonID, $taxYear)) {
            return self::SLIP_TYPE_AMENDED;
        }

        return self::SLIP_TYPE_ORIGINAL;
    }

    /**
     * Calculate Box B - Days of Care.
     *
     * IMPORTANT: This counts actual PAID days, not calendar days or invoiced days.
     * Only days that have been paid for are counted.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $taxYear Tax year
     * @return int Number of paid care days
     */
    public function calculateDaysOfCare($gibbonPersonID, $taxYear)
    {
        // Get payments for the child in the tax year
        $startDate = $taxYear . '-01-01';
        $endDate = $taxYear . '-12-31';

        $payments = $this->paymentGateway->selectPaymentsByChildAndDateRange(
            $gibbonPersonID,
            $startDate,
            $endDate
        );

        // Calculate days based on paid invoices
        // Assumption: Each invoice represents care for a specific period
        // We need to calculate the number of weekdays in the paid period
        $totalDays = 0;

        // Get contract information to determine days per week
        $contract = $this->getActiveContract($gibbonPersonID, $taxYear);
        $daysPerWeek = $contract['daysPerWeek'] ?? 5;

        if ($payments) {
            foreach ($payments->fetchAll() as $payment) {
                // Each payment contributes proportionally to days of care
                // This is a simplified calculation - actual implementation may vary
                $invoiceID = $payment['gibbonEnhancedFinanceInvoiceID'];
                $invoice = $this->invoiceGateway->selectInvoiceByID($invoiceID);

                if (!empty($invoice) && $invoice['totalAmount'] > 0) {
                    // Calculate paid proportion of invoice
                    $paidProportion = min(1, $payment['amount'] / $invoice['totalAmount']);

                    // Estimate care days for this invoice period
                    // Typically, invoices cover weekly or monthly periods
                    $invoiceDays = $this->estimateCareDaysForInvoice($invoice, $daysPerWeek);
                    $totalDays += (int) round($invoiceDays * $paidProportion);
                }
            }
        }

        return $totalDays;
    }

    /**
     * Estimate the number of care days for an invoice period.
     *
     * @param array $invoice Invoice data
     * @param int $daysPerWeek Days per week of care
     * @return int Estimated care days
     */
    protected function estimateCareDaysForInvoice($invoice, $daysPerWeek = 5)
    {
        // If invoice has specific date range, calculate from that
        $invoiceDate = $invoice['invoiceDate'] ?? null;
        $dueDate = $invoice['dueDate'] ?? null;

        if (empty($invoiceDate)) {
            // Default assumption: 1 week of care
            return $daysPerWeek;
        }

        // Calculate weeks between dates and multiply by days per week
        $start = new \DateTime($invoiceDate);

        // Assume invoice covers period from start of month
        // This is a simplification - actual implementation should use contract periods
        $end = new \DateTime($dueDate ?: $invoiceDate);
        $interval = $start->diff($end);
        $totalDays = $interval->days;

        // Approximate care days (excluding weekends if 5-day care)
        if ($daysPerWeek == 5) {
            $weeks = ceil($totalDays / 7);
            return (int) min($weeks * 5, $totalDays);
        }

        return min($totalDays, $daysPerWeek);
    }

    /**
     * Calculate Box C - Total Amounts Paid.
     *
     * CRITICAL: This must include ONLY actual payments received, NOT invoiced amounts.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $taxYear Tax year
     * @return float Total amount paid
     */
    public function calculateTotalAmountsPaid($gibbonPersonID, $taxYear)
    {
        $result = $this->paymentGateway->selectTotalPaidByChildAndTaxYear($gibbonPersonID, $taxYear);

        return (float) ($result['totalPaid'] ?? 0);
    }

    /**
     * Calculate Box D - Non-Qualifying Expenses.
     *
     * Non-qualifying expenses include:
     * - Medical or hospital care
     * - Transportation services
     * - Teaching services
     * - Field trips
     * - Registration fees
     * - Late payment penalties
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $taxYear Tax year
     * @return float Total non-qualifying expenses
     */
    public function calculateNonQualifyingExpenses($gibbonPersonID, $taxYear)
    {
        // This would typically query invoice line items categorized as non-qualifying
        // For this implementation, we'll query based on notes/categories in invoices

        $startDate = $taxYear . '-01-01';
        $endDate = $taxYear . '-12-31';

        $sql = "SELECT
                COALESCE(SUM(p.amount), 0) AS nonQualifyingTotal
            FROM gibbonEnhancedFinancePayment p
            INNER JOIN gibbonEnhancedFinanceInvoice i
                ON p.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
            WHERE i.gibbonPersonID = :gibbonPersonID
            AND p.paymentDate BETWEEN :startDate AND :endDate
            AND (
                LOWER(i.notes) LIKE '%medical%'
                OR LOWER(i.notes) LIKE '%hospital%'
                OR LOWER(i.notes) LIKE '%transport%'
                OR LOWER(i.notes) LIKE '%teaching%'
                OR LOWER(i.notes) LIKE '%field trip%'
                OR LOWER(i.notes) LIKE '%fieldtrip%'
                OR LOWER(i.notes) LIKE '%registration%'
                OR LOWER(i.notes) LIKE '%late fee%'
                OR LOWER(i.notes) LIKE '%penalty%'
            )";

        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];

        try {
            $result = $this->db->selectOne($sql, $data);
            return (float) ($result['nonQualifyingTotal'] ?? 0);
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Calculate Box E - Qualifying Expenses.
     *
     * This is simply Box C - Box D.
     *
     * @param float $totalAmountsPaid Box C value
     * @param float $nonQualifyingExpenses Box D value
     * @return float Qualifying expenses
     */
    public function calculateQualifyingExpenses($totalAmountsPaid, $nonQualifyingExpenses)
    {
        $qualifyingExpenses = $totalAmountsPaid - $nonQualifyingExpenses;

        // Cannot be negative
        return max(0, $qualifyingExpenses);
    }

    /**
     * Get provider SIN from settings, formatted as XXX-XXX-XXX.
     *
     * @return string Formatted provider SIN or empty string
     */
    public function getProviderSIN()
    {
        $sin = $this->settingGateway->getSettingByScope('Enhanced Finance', 'providerSIN');

        return $this->formatSIN($sin);
    }

    /**
     * Format a SIN number to XXX-XXX-XXX format.
     *
     * @param string $sin Raw SIN (9 digits, may include dashes)
     * @return string Formatted SIN or empty string if invalid
     */
    public function formatSIN($sin)
    {
        if (empty($sin)) {
            return '';
        }

        // Remove all non-numeric characters
        $digits = preg_replace('/[^0-9]/', '', $sin);

        // SIN must be exactly 9 digits
        if (strlen($digits) !== 9) {
            return '';
        }

        // Format as XXX-XXX-XXX
        return substr($digits, 0, 3) . '-' . substr($digits, 3, 3) . '-' . substr($digits, 6, 3);
    }

    /**
     * Validate a SIN number using the Luhn algorithm.
     *
     * @param string $sin SIN to validate (formatted or unformatted)
     * @return bool True if valid, false otherwise
     */
    public function validateSIN($sin)
    {
        // Remove all non-numeric characters
        $digits = preg_replace('/[^0-9]/', '', $sin);

        // SIN must be exactly 9 digits
        if (strlen($digits) !== 9) {
            return false;
        }

        // Luhn algorithm validation
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $digit = (int) $digits[$i];

            // Double every second digit (index 1, 3, 5, 7)
            if ($i % 2 == 1) {
                $digit *= 2;
                // If result is > 9, subtract 9 (equivalent to summing digits)
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        // Valid if sum is divisible by 10
        return ($sum % 10) === 0;
    }

    /**
     * Format name for RL-24 display (Surname, First Name).
     *
     * @param string $surname
     * @param string $firstName
     * @return string Formatted name
     */
    public function formatName($surname, $firstName)
    {
        $surname = trim($surname);
        $firstName = trim($firstName);

        if (empty($surname) && empty($firstName)) {
            return '';
        }

        if (empty($surname)) {
            return $firstName;
        }

        if (empty($firstName)) {
            return $surname;
        }

        return $surname . ', ' . $firstName;
    }

    /**
     * Generate a unique slip number for RL-24.
     *
     * Format: RL24-YYYY-NNNNNN (e.g., RL24-2025-000001)
     *
     * @param int $taxYear Tax year
     * @return string Unique slip number
     */
    public function generateSlipNumber($taxYear)
    {
        // Get the next sequence number for the tax year
        $sql = "SELECT MAX(CAST(SUBSTRING(slipNumber, 11) AS UNSIGNED)) AS maxNum
                FROM gibbonEnhancedFinanceReleve24
                WHERE taxYear = :taxYear";

        $data = ['taxYear' => $taxYear];

        try {
            $result = $this->db->selectOne($sql, $data);
            $nextNum = ($result['maxNum'] ?? 0) + 1;
        } catch (\Exception $e) {
            $nextNum = 1;
        }

        return sprintf('RL24-%d-%06d', $taxYear, $nextNum);
    }

    /**
     * Get child information by person ID.
     *
     * @param int $gibbonPersonID
     * @return array Child information
     */
    protected function getChildInfo($gibbonPersonID)
    {
        $sql = "SELECT
                gibbonPersonID,
                surname,
                preferredName,
                firstName,
                dob,
                gender
            FROM gibbonPerson
            WHERE gibbonPersonID = :gibbonPersonID";

        try {
            return $this->db->selectOne($sql, ['gibbonPersonID' => $gibbonPersonID]) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get family information by family ID.
     *
     * @param int $gibbonFamilyID
     * @return array Family information
     */
    protected function getFamilyInfo($gibbonFamilyID)
    {
        $sql = "SELECT
                gibbonFamilyID,
                name,
                nameAddress
            FROM gibbonFamily
            WHERE gibbonFamilyID = :gibbonFamilyID";

        try {
            return $this->db->selectOne($sql, ['gibbonFamilyID' => $gibbonFamilyID]) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get recipient (parent/guardian) information for RL-24.
     *
     * Returns the primary adult (parent 1) from the family.
     *
     * @param int $gibbonFamilyID
     * @return array Recipient information including SIN if available
     */
    protected function getRecipientInfo($gibbonFamilyID)
    {
        // Get primary parent from family
        $sql = "SELECT
                p.gibbonPersonID,
                p.surname,
                p.preferredName,
                p.firstName,
                p.address1,
                p.address1City,
                p.address1Province,
                p.address1PostalCode,
                fa.contactPriority
            FROM gibbonFamilyAdult fa
            INNER JOIN gibbonPerson p ON fa.gibbonPersonID = p.gibbonPersonID
            WHERE fa.gibbonFamilyID = :gibbonFamilyID
            AND fa.contactPriority = 1
            ORDER BY fa.contactPriority ASC
            LIMIT 1";

        try {
            $recipient = $this->db->selectOne($sql, ['gibbonFamilyID' => $gibbonFamilyID]) ?: [];

            // SIN would typically be stored in a secure custom field
            // For now, return empty - this should be populated from secure storage
            $recipient['sin'] = '';

            return $recipient;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get active contract for a child in a tax year.
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $taxYear Tax year
     * @return array Contract information or defaults
     */
    protected function getActiveContract($gibbonPersonID, $taxYear)
    {
        $startDate = $taxYear . '-01-01';
        $endDate = $taxYear . '-12-31';

        $sql = "SELECT *
            FROM gibbonEnhancedFinanceContract
            WHERE gibbonPersonID = :gibbonPersonID
            AND status = 'Active'
            AND startDate <= :endDate
            AND (endDate IS NULL OR endDate >= :startDate)
            ORDER BY startDate DESC
            LIMIT 1";

        $data = [
            'gibbonPersonID' => $gibbonPersonID,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];

        try {
            $contract = $this->db->selectOne($sql, $data);
            return $contract ?: ['daysPerWeek' => 5, 'weeklyRate' => 0];
        } catch (\Exception $e) {
            return ['daysPerWeek' => 5, 'weeklyRate' => 0];
        }
    }

    /**
     * Generate RL-24 slip data for rendering/printing.
     *
     * @param int $gibbonEnhancedFinanceReleve24ID RL-24 record ID
     * @return array Complete slip data for rendering
     */
    public function getSlipDataForRendering($gibbonEnhancedFinanceReleve24ID)
    {
        $slip = $this->releve24Gateway->selectReleve24ByID($gibbonEnhancedFinanceReleve24ID);

        if (empty($slip)) {
            return [];
        }

        // Get provider information
        $providerName = $this->settingGateway->getSettingByScope('Enhanced Finance', 'providerName') ?? '';
        $providerAddress = $this->settingGateway->getSettingByScope('Enhanced Finance', 'providerAddress') ?? '';
        $providerNEQ = $this->settingGateway->getSettingByScope('Enhanced Finance', 'providerNEQ') ?? '';

        return [
            // Header information
            'taxYear' => $slip['taxYear'],
            'slipNumber' => $slip['slipNumber'] ?? '',
            'generatedAt' => $slip['generatedAt'],

            // Box A - Slip Type
            'boxA' => $this->getSlipTypeLabel($slip['slipType']),
            'slipType' => $slip['slipType'],

            // Box B - Days of Care
            'boxB' => $slip['daysOfCare'],

            // Box C - Total Amounts Paid
            'boxC' => number_format((float) $slip['totalAmountsPaid'], 2, '.', ''),

            // Box D - Non-Qualifying Expenses
            'boxD' => number_format((float) $slip['nonQualifyingExpenses'], 2, '.', ''),

            // Box E - Qualifying Expenses
            'boxE' => number_format((float) $slip['qualifyingExpenses'], 2, '.', ''),

            // Box H - Provider SIN
            'boxH' => $slip['providerSIN'],

            // Provider Information
            'providerName' => $providerName,
            'providerAddress' => $providerAddress,
            'providerNEQ' => $providerNEQ,
            'providerSIN' => $slip['providerSIN'],

            // Recipient Information
            'recipientName' => $slip['recipientName'],
            'recipientSIN' => $slip['recipientSIN'],

            // Child Information
            'childName' => $slip['childName'],
            'childDOB' => $slip['childDOB'] ?? '',

            // Status
            'status' => $slip['status'],
            'sentAt' => $slip['sentAt'],

            // Raw data for further processing
            'raw' => $slip
        ];
    }

    /**
     * Get human-readable label for slip type.
     *
     * @param string $slipType Slip type code (R, A, D)
     * @return string Human-readable label
     */
    public function getSlipTypeLabel($slipType)
    {
        switch ($slipType) {
            case self::SLIP_TYPE_ORIGINAL:
                return 'Original';
            case self::SLIP_TYPE_AMENDED:
                return 'Amended';
            case self::SLIP_TYPE_CANCELLED:
                return 'Cancelled';
            default:
                return 'Unknown';
        }
    }

    /**
     * Check if an RL-24 can be amended.
     *
     * @param int $gibbonEnhancedFinanceReleve24ID RL-24 record ID
     * @return bool True if can be amended
     */
    public function canAmend($gibbonEnhancedFinanceReleve24ID)
    {
        $slip = $this->releve24Gateway->selectReleve24ByID($gibbonEnhancedFinanceReleve24ID);

        if (empty($slip)) {
            return false;
        }

        // Can only amend if original has been sent or filed
        return in_array($slip['status'], [self::STATUS_SENT, self::STATUS_FILED]);
    }

    /**
     * Check if an RL-24 can be cancelled.
     *
     * @param int $gibbonEnhancedFinanceReleve24ID RL-24 record ID
     * @return bool True if can be cancelled
     */
    public function canCancel($gibbonEnhancedFinanceReleve24ID)
    {
        $slip = $this->releve24Gateway->selectReleve24ByID($gibbonEnhancedFinanceReleve24ID);

        if (empty($slip)) {
            return false;
        }

        // Cannot cancel a cancelled slip
        if ($slip['slipType'] === self::SLIP_TYPE_CANCELLED) {
            return false;
        }

        // Can only cancel if not yet filed
        return !in_array($slip['status'], [self::STATUS_FILED]);
    }

    /**
     * Create an amended RL-24 slip.
     *
     * @param int $originalReleve24ID Original RL-24 ID to amend
     * @param int $createdByID Staff ID creating the amendment
     * @return int|false New RL-24 ID on success, false on failure
     */
    public function createAmendment($originalReleve24ID, $createdByID)
    {
        $original = $this->releve24Gateway->selectReleve24ByID($originalReleve24ID);

        if (empty($original) || !$this->canAmend($originalReleve24ID)) {
            return false;
        }

        // Mark original as amended
        $this->releve24Gateway->update($originalReleve24ID, ['status' => self::STATUS_AMENDED]);

        // Generate new RL-24 data with updated calculations
        return $this->generateAndSaveReleve24(
            $original['gibbonPersonID'],
            $original['gibbonFamilyID'],
            $original['taxYear'],
            $createdByID
        );
    }

    /**
     * Check if all required provider information is configured.
     *
     * @return array List of missing required fields
     */
    public function validateProviderConfiguration()
    {
        $missing = [];

        $providerSIN = $this->settingGateway->getSettingByScope('Enhanced Finance', 'providerSIN');
        if (empty($providerSIN)) {
            $missing[] = 'Provider SIN';
        } elseif (!$this->validateSIN($providerSIN)) {
            $missing[] = 'Provider SIN (invalid format)';
        }

        $providerName = $this->settingGateway->getSettingByScope('Enhanced Finance', 'providerName');
        if (empty($providerName)) {
            $missing[] = 'Provider Name';
        }

        $providerAddress = $this->settingGateway->getSettingByScope('Enhanced Finance', 'providerAddress');
        if (empty($providerAddress)) {
            $missing[] = 'Provider Address';
        }

        return $missing;
    }

    /**
     * Get children eligible for RL-24 generation for a tax year.
     *
     * Returns children who have paid invoices in the tax year.
     *
     * @param int $taxYear Tax year
     * @return array List of eligible children with summary data
     */
    public function getEligibleChildren($taxYear)
    {
        $startDate = $taxYear . '-01-01';
        $endDate = $taxYear . '-12-31';

        $sql = "SELECT
                p.gibbonPersonID,
                p.surname,
                p.preferredName,
                p.dob,
                f.gibbonFamilyID,
                f.name AS familyName,
                SUM(pay.amount) AS totalPaid,
                COUNT(DISTINCT i.gibbonEnhancedFinanceInvoiceID) AS invoiceCount,
                COUNT(DISTINCT pay.gibbonEnhancedFinancePaymentID) AS paymentCount,
                CASE WHEN r.gibbonEnhancedFinanceReleve24ID IS NOT NULL THEN 1 ELSE 0 END AS hasReleve24
            FROM gibbonEnhancedFinancePayment pay
            INNER JOIN gibbonEnhancedFinanceInvoice i ON pay.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
            INNER JOIN gibbonPerson p ON i.gibbonPersonID = p.gibbonPersonID
            INNER JOIN gibbonFamily f ON i.gibbonFamilyID = f.gibbonFamilyID
            LEFT JOIN gibbonEnhancedFinanceReleve24 r ON p.gibbonPersonID = r.gibbonPersonID AND r.taxYear = :taxYear
            WHERE pay.paymentDate BETWEEN :startDate AND :endDate
            GROUP BY p.gibbonPersonID, f.gibbonFamilyID
            HAVING totalPaid > 0
            ORDER BY p.surname ASC, p.preferredName ASC";

        $data = [
            'taxYear' => $taxYear,
            'startDate' => $startDate,
            'endDate' => $endDate
        ];

        try {
            $result = $this->db->select($sql, $data);
            return $result->fetchAll() ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get summary statistics for RL-24 generation.
     *
     * @param int $taxYear Tax year
     * @return array Summary statistics
     */
    public function getReleve24Summary($taxYear)
    {
        $eligible = $this->getEligibleChildren($taxYear);
        $slipSummary = $this->releve24Gateway->selectReleve24SummaryByYear($taxYear);

        return [
            'taxYear' => $taxYear,
            'eligibleChildren' => count($eligible),
            'totalAmountPaid' => array_sum(array_column($eligible, 'totalPaid')),
            'generatedSlips' => (int) ($slipSummary['totalSlips'] ?? 0),
            'draftCount' => (int) ($slipSummary['draftCount'] ?? 0),
            'generatedCount' => (int) ($slipSummary['generatedCount'] ?? 0),
            'sentCount' => (int) ($slipSummary['sentCount'] ?? 0),
            'filedCount' => (int) ($slipSummary['filedCount'] ?? 0),
            'totalQualifyingExpenses' => (float) ($slipSummary['totalQualifyingExpenses'] ?? 0),
            'totalDaysOfCare' => (int) ($slipSummary['totalDaysOfCare'] ?? 0),
        ];
    }
}
