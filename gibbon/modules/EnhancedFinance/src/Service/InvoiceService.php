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

namespace Gibbon\Module\EnhancedFinance\Service;

use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;
use Gibbon\Module\EnhancedFinance\Validator\InvoiceValidator;

/**
 * InvoiceService
 *
 * Business logic service for invoice operations.
 * Handles tax calculations, invoice number generation, and invoice totals.
 * Extracted from finance_invoice_add.php to improve testability and maintainability.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InvoiceService
{
    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var InvoiceGateway
     */
    protected $invoiceGateway;

    /**
     * @var InvoiceValidator
     */
    protected $validator;

    /**
     * @var array Tax rate cache
     */
    protected $taxRates = null;

    /**
     * Constructor.
     *
     * @param SettingGateway $settingGateway Settings gateway
     * @param InvoiceGateway $invoiceGateway Invoice gateway
     * @param InvoiceValidator $validator Invoice validator
     */
    public function __construct(
        SettingGateway $settingGateway,
        InvoiceGateway $invoiceGateway,
        InvoiceValidator $validator
    ) {
        $this->settingGateway = $settingGateway;
        $this->invoiceGateway = $invoiceGateway;
        $this->validator = $validator;
    }

    /**
     * Get tax rates from settings.
     *
     * @return array Tax rates with GST, QST, and combined
     */
    public function getTaxRates()
    {
        if ($this->taxRates !== null) {
            return $this->taxRates;
        }

        $gstRate = $this->settingGateway->getSettingByScope('Enhanced Finance', 'gstRate') ?: '0.05';
        $qstRate = $this->settingGateway->getSettingByScope('Enhanced Finance', 'qstRate') ?: '0.09975';

        $this->taxRates = [
            'gst' => (float)$gstRate,
            'qst' => (float)$qstRate,
            'combined' => (float)$gstRate + (float)$qstRate,
        ];

        return $this->taxRates;
    }

    /**
     * Get GST rate.
     *
     * @return float GST rate as decimal (e.g., 0.05 for 5%)
     */
    public function getGSTRate()
    {
        $rates = $this->getTaxRates();
        return $rates['gst'];
    }

    /**
     * Get QST rate.
     *
     * @return float QST rate as decimal (e.g., 0.09975 for 9.975%)
     */
    public function getQSTRate()
    {
        $rates = $this->getTaxRates();
        return $rates['qst'];
    }

    /**
     * Get combined tax rate (GST + QST).
     *
     * @return float Combined tax rate as decimal
     */
    public function getCombinedTaxRate()
    {
        $rates = $this->getTaxRates();
        return $rates['combined'];
    }

    /**
     * Calculate tax amount for a given subtotal.
     *
     * @param float $subtotal Subtotal amount before tax
     * @param bool $roundTo2Decimals Round result to 2 decimal places (default: true)
     * @return float Tax amount
     */
    public function calculateTax($subtotal, $roundTo2Decimals = true)
    {
        $taxRate = $this->getCombinedTaxRate();
        $tax = (float)$subtotal * $taxRate;

        return $roundTo2Decimals ? round($tax, 2) : $tax;
    }

    /**
     * Calculate GST amount for a given subtotal.
     *
     * @param float $subtotal Subtotal amount before tax
     * @param bool $roundTo2Decimals Round result to 2 decimal places (default: true)
     * @return float GST amount
     */
    public function calculateGST($subtotal, $roundTo2Decimals = true)
    {
        $gstRate = $this->getGSTRate();
        $gst = (float)$subtotal * $gstRate;

        return $roundTo2Decimals ? round($gst, 2) : $gst;
    }

    /**
     * Calculate QST amount for a given subtotal.
     *
     * @param float $subtotal Subtotal amount before tax
     * @param bool $roundTo2Decimals Round result to 2 decimal places (default: true)
     * @return float QST amount
     */
    public function calculateQST($subtotal, $roundTo2Decimals = true)
    {
        $qstRate = $this->getQSTRate();
        $qst = (float)$subtotal * $qstRate;

        return $roundTo2Decimals ? round($qst, 2) : $qst;
    }

    /**
     * Calculate invoice total (subtotal + tax).
     *
     * @param float $subtotal Subtotal amount before tax
     * @param bool $roundTo2Decimals Round result to 2 decimal places (default: true)
     * @return float Total amount including tax
     */
    public function calculateTotal($subtotal, $roundTo2Decimals = true)
    {
        $tax = $this->calculateTax($subtotal, false);
        $total = (float)$subtotal + $tax;

        return $roundTo2Decimals ? round($total, 2) : $total;
    }

    /**
     * Calculate detailed invoice amounts.
     *
     * @param float $subtotal Subtotal amount before tax
     * @return array Array with subtotal, gst, qst, taxAmount, and totalAmount
     */
    public function calculateInvoiceAmounts($subtotal)
    {
        $subtotal = (float)$subtotal;
        $gst = $this->calculateGST($subtotal);
        $qst = $this->calculateQST($subtotal);
        $taxAmount = round($gst + $qst, 2);
        $totalAmount = round($subtotal + $taxAmount, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'gst' => $gst,
            'qst' => $qst,
            'taxAmount' => $taxAmount,
            'totalAmount' => $totalAmount,
        ];
    }

    /**
     * Generate next invoice number for a school year.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @return string Generated invoice number
     */
    public function generateInvoiceNumber($gibbonSchoolYearID)
    {
        $prefix = $this->settingGateway->getSettingByScope('Enhanced Finance', 'invoicePrefix') ?: 'INV-';
        return $this->invoiceGateway->generateInvoiceNumber($prefix, $gibbonSchoolYearID);
    }

    /**
     * Calculate due date based on invoice date and payment terms.
     *
     * @param string $invoiceDate Invoice date (Y-m-d format)
     * @param int|null $paymentTermsDays Number of days until due (null = use default from settings)
     * @return string Due date (Y-m-d format)
     */
    public function calculateDueDate($invoiceDate, $paymentTermsDays = null)
    {
        if ($paymentTermsDays === null) {
            $paymentTermsDays = $this->settingGateway->getSettingByScope('Enhanced Finance', 'defaultPaymentTermsDays') ?: '30';
        }

        $paymentTermsDays = (int)$paymentTermsDays;

        if (!$this->validator->isValidDate($invoiceDate)) {
            // Fallback to today if invalid date
            $invoiceDate = date('Y-m-d');
        }

        return date('Y-m-d', strtotime($invoiceDate . ' +' . $paymentTermsDays . ' days'));
    }

    /**
     * Get default payment terms in days.
     *
     * @return int Default payment terms days
     */
    public function getDefaultPaymentTerms()
    {
        $defaultPaymentTermsDays = $this->settingGateway->getSettingByScope('Enhanced Finance', 'defaultPaymentTermsDays') ?: '30';
        return (int)$defaultPaymentTermsDays;
    }

    /**
     * Calculate invoice balance remaining.
     *
     * @param float $totalAmount Total invoice amount
     * @param float $paidAmount Amount already paid
     * @return float Balance remaining
     */
    public function calculateBalance($totalAmount, $paidAmount)
    {
        return round((float)$totalAmount - (float)$paidAmount, 2);
    }

    /**
     * Determine invoice status based on amounts.
     *
     * @param float $totalAmount Total invoice amount
     * @param float $paidAmount Amount already paid
     * @param string|null $currentStatus Current status (to preserve Cancelled/Refunded)
     * @return string Invoice status
     */
    public function determineStatus($totalAmount, $paidAmount, $currentStatus = null)
    {
        // Preserve terminal statuses
        if (in_array($currentStatus, ['Cancelled', 'Refunded'])) {
            return $currentStatus;
        }

        $totalAmount = (float)$totalAmount;
        $paidAmount = (float)$paidAmount;

        if ($paidAmount >= $totalAmount && $totalAmount > 0) {
            return 'Paid';
        } elseif ($paidAmount > 0) {
            return 'Partial';
        } elseif ($currentStatus === 'Issued') {
            return 'Issued';
        } else {
            return 'Pending';
        }
    }

    /**
     * Check if an invoice is overdue.
     *
     * @param string $dueDate Due date (Y-m-d format)
     * @param string $status Invoice status
     * @param string|null $compareDate Date to compare against (null = today)
     * @return bool True if overdue
     */
    public function isOverdue($dueDate, $status, $compareDate = null)
    {
        if (!in_array($status, ['Issued', 'Partial'])) {
            return false;
        }

        $compareDate = $compareDate ?: date('Y-m-d');

        if (!$this->validator->isValidDate($dueDate) || !$this->validator->isValidDate($compareDate)) {
            return false;
        }

        return strtotime($dueDate) < strtotime($compareDate);
    }

    /**
     * Get invoice age in days.
     *
     * @param string $invoiceDate Invoice date (Y-m-d format)
     * @param string|null $compareDate Date to compare against (null = today)
     * @return int Age in days
     */
    public function getInvoiceAge($invoiceDate, $compareDate = null)
    {
        $compareDate = $compareDate ?: date('Y-m-d');

        if (!$this->validator->isValidDate($invoiceDate) || !$this->validator->isValidDate($compareDate)) {
            return 0;
        }

        $start = new \DateTime($invoiceDate);
        $end = new \DateTime($compareDate);
        $diff = $start->diff($end);

        return $diff->days;
    }

    /**
     * Get days overdue for an invoice.
     *
     * @param string $dueDate Due date (Y-m-d format)
     * @param string|null $compareDate Date to compare against (null = today)
     * @return int Days overdue (0 if not overdue)
     */
    public function getDaysOverdue($dueDate, $compareDate = null)
    {
        $compareDate = $compareDate ?: date('Y-m-d');

        if (!$this->validator->isValidDate($dueDate) || !$this->validator->isValidDate($compareDate)) {
            return 0;
        }

        $start = new \DateTime($dueDate);
        $end = new \DateTime($compareDate);

        if ($start >= $end) {
            return 0;
        }

        $diff = $start->diff($end);
        return $diff->days;
    }

    /**
     * Validate invoice data.
     *
     * @param array $invoiceData Invoice data to validate
     * @return array Validation result with success and errors
     */
    public function validateInvoice($invoiceData)
    {
        return $this->validator->validate($invoiceData);
    }

    /**
     * Prepare invoice data for creation.
     *
     * @param array $data Raw invoice data
     * @return array Prepared invoice data with calculated fields
     */
    public function prepareInvoiceData($data)
    {
        $subtotal = (float)($data['subtotal'] ?? 0);

        // Calculate amounts
        $amounts = $this->calculateInvoiceAmounts($subtotal);

        // Calculate due date if not provided
        $invoiceDate = $data['invoiceDate'] ?? date('Y-m-d');
        $dueDate = $data['dueDate'] ?? $this->calculateDueDate($invoiceDate);

        // Generate invoice number if not provided
        $invoiceNumber = $data['invoiceNumber'] ?? null;
        if (empty($invoiceNumber) && !empty($data['gibbonSchoolYearID'])) {
            $invoiceNumber = $this->generateInvoiceNumber($data['gibbonSchoolYearID']);
        }

        return array_merge($data, [
            'invoiceNumber' => $invoiceNumber,
            'invoiceDate' => $invoiceDate,
            'dueDate' => $dueDate,
            'subtotal' => $amounts['subtotal'],
            'taxAmount' => $amounts['taxAmount'],
            'totalAmount' => $amounts['totalAmount'],
            'paidAmount' => (float)($data['paidAmount'] ?? 0),
            'status' => $data['status'] ?? 'Pending',
        ]);
    }
}
