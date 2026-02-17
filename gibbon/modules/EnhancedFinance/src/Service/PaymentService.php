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
use Gibbon\Module\EnhancedFinance\Domain\PaymentGateway;
use Gibbon\Module\EnhancedFinance\Domain\InvoiceGateway;

/**
 * PaymentService
 *
 * Business logic service for payment processing operations.
 * Handles payment validation, balance calculations, payment recording, and invoice updates.
 * Extracted from finance_payment_addProcess.php to improve testability and maintainability.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PaymentService
{
    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var PaymentGateway
     */
    protected $paymentGateway;

    /**
     * @var InvoiceGateway
     */
    protected $invoiceGateway;

    /**
     * Constructor.
     *
     * @param SettingGateway $settingGateway Settings gateway
     * @param PaymentGateway $paymentGateway Payment gateway
     * @param InvoiceGateway $invoiceGateway Invoice gateway
     */
    public function __construct(
        SettingGateway $settingGateway,
        PaymentGateway $paymentGateway,
        InvoiceGateway $invoiceGateway
    ) {
        $this->settingGateway = $settingGateway;
        $this->paymentGateway = $paymentGateway;
        $this->invoiceGateway = $invoiceGateway;
    }

    /**
     * Get available payment methods.
     *
     * @return array Array of payment method options
     */
    public function getPaymentMethods()
    {
        return [
            'Cash' => 'Cash',
            'Check' => 'Check',
            'Credit Card' => 'Credit Card',
            'Debit Card' => 'Debit Card',
            'Bank Transfer' => 'Bank Transfer',
            'Online' => 'Online',
            'Other' => 'Other',
        ];
    }

    /**
     * Validate payment amount.
     *
     * @param float $amount Payment amount
     * @return array Result with success and error message
     */
    public function validateAmount($amount)
    {
        $amount = (float)$amount;

        if ($amount <= 0) {
            return [
                'success' => false,
                'error' => 'Payment amount must be greater than zero.',
            ];
        }

        return ['success' => true];
    }

    /**
     * Calculate balance remaining on an invoice.
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
     * Check if payment amount exceeds invoice balance.
     *
     * @param float $paymentAmount Payment amount to check
     * @param float $balanceRemaining Current balance remaining
     * @return bool True if payment exceeds balance
     */
    public function exceedsBalance($paymentAmount, $balanceRemaining)
    {
        return (float)$paymentAmount > (float)$balanceRemaining;
    }

    /**
     * Validate payment against invoice balance.
     *
     * @param float $paymentAmount Payment amount
     * @param array $invoice Invoice data with totalAmount and paidAmount
     * @return array Result with success and error message
     */
    public function validatePaymentAgainstInvoice($paymentAmount, $invoice)
    {
        $paymentAmount = (float)$paymentAmount;
        $balanceRemaining = $this->calculateBalance(
            (float)$invoice['totalAmount'],
            (float)$invoice['paidAmount']
        );

        if ($this->exceedsBalance($paymentAmount, $balanceRemaining)) {
            return [
                'success' => false,
                'error' => 'Payment amount exceeds invoice balance.',
                'balanceRemaining' => $balanceRemaining,
            ];
        }

        return ['success' => true, 'balanceRemaining' => $balanceRemaining];
    }

    /**
     * Prepare payment data for insertion.
     *
     * @param array $data Raw payment data
     * @param int $recordedByID User ID recording the payment
     * @return array Prepared payment data
     */
    public function preparePaymentData($data, $recordedByID)
    {
        return [
            'gibbonEnhancedFinanceInvoiceID' => $data['gibbonEnhancedFinanceInvoiceID'] ?? null,
            'paymentDate' => $data['paymentDate'] ?? date('Y-m-d'),
            'amount' => (float)($data['amount'] ?? 0),
            'method' => $data['method'] ?? 'Cash',
            'reference' => $data['reference'] ?? '',
            'notes' => $data['notes'] ?? '',
            'recordedByID' => $recordedByID,
        ];
    }

    /**
     * Process a payment (insert payment and update invoice).
     *
     * @param array $paymentData Payment data
     * @return array Result with success, paymentID, and error message if applicable
     */
    public function processPayment($paymentData)
    {
        // Validate required fields
        if (empty($paymentData['gibbonEnhancedFinanceInvoiceID'])) {
            return [
                'success' => false,
                'error' => 'Invoice ID is required.',
            ];
        }

        // Validate amount
        $amountValidation = $this->validateAmount($paymentData['amount']);
        if (!$amountValidation['success']) {
            return $amountValidation;
        }

        // Get invoice
        $invoice = $this->invoiceGateway->selectInvoiceByID($paymentData['gibbonEnhancedFinanceInvoiceID']);
        if (empty($invoice)) {
            return [
                'success' => false,
                'error' => 'Invoice not found.',
            ];
        }

        // Validate payment against invoice balance
        $balanceValidation = $this->validatePaymentAgainstInvoice($paymentData['amount'], $invoice);
        if (!$balanceValidation['success']) {
            return $balanceValidation;
        }

        // Insert payment
        $paymentID = $this->paymentGateway->insertPayment($paymentData);
        if ($paymentID === false) {
            return [
                'success' => false,
                'error' => 'Failed to insert payment.',
            ];
        }

        // Update invoice paid amount and status
        $updateResult = $this->invoiceGateway->updatePaidAmount($paymentData['gibbonEnhancedFinanceInvoiceID']);
        if ($updateResult === false) {
            return [
                'success' => false,
                'error' => 'Payment recorded but failed to update invoice.',
                'paymentID' => $paymentID,
            ];
        }

        return [
            'success' => true,
            'paymentID' => $paymentID,
        ];
    }

    /**
     * Get total payments for an invoice.
     *
     * @param int $gibbonEnhancedFinanceInvoiceID Invoice ID
     * @return float Total paid amount
     */
    public function getTotalPaidForInvoice($gibbonEnhancedFinanceInvoiceID)
    {
        return $this->paymentGateway->getTotalPaidForInvoice($gibbonEnhancedFinanceInvoiceID);
    }

    /**
     * Get payment summary for a school year.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @return array Summary with totalAmount, paymentCount
     */
    public function getPaymentSummaryForYear($gibbonSchoolYearID)
    {
        $summary = $this->paymentGateway->selectTotalPaymentsByYear($gibbonSchoolYearID);

        return [
            'totalAmount' => (float)($summary['totalAmount'] ?? 0),
            'paymentCount' => (int)($summary['paymentCount'] ?? 0),
        ];
    }

    /**
     * Get payment summary by method for a school year.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @return array Array of payment method summaries
     */
    public function getPaymentSummaryByMethod($gibbonSchoolYearID)
    {
        $result = $this->paymentGateway->selectPaymentSummaryByMethod($gibbonSchoolYearID);
        $summary = [];

        while ($row = $result->fetch()) {
            $summary[] = [
                'method' => $row['method'],
                'paymentCount' => (int)$row['paymentCount'],
                'totalAmount' => (float)$row['totalAmount'],
            ];
        }

        return $summary;
    }

    /**
     * Get payment summary by month for a school year.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @return array Array of monthly payment summaries
     */
    public function getPaymentSummaryByMonth($gibbonSchoolYearID)
    {
        $result = $this->paymentGateway->selectPaymentSummaryByMonth($gibbonSchoolYearID);
        $summary = [];

        while ($row = $result->fetch()) {
            $summary[] = [
                'year' => (int)$row['paymentYear'],
                'month' => (int)$row['paymentMonth'],
                'paymentCount' => (int)$row['paymentCount'],
                'totalAmount' => (float)$row['totalAmount'],
            ];
        }

        return $summary;
    }

    /**
     * Get total payments for a child in a tax year (for RL-24).
     *
     * @param int $gibbonPersonID Child's person ID
     * @param int $taxYear Tax year (YYYY format)
     * @return array Summary with totalPaid, paymentCount, invoiceCount
     */
    public function getTotalPaidByChildAndTaxYear($gibbonPersonID, $taxYear)
    {
        $summary = $this->paymentGateway->selectTotalPaidByChildAndTaxYear($gibbonPersonID, $taxYear);

        return [
            'totalPaid' => (float)($summary['totalPaid'] ?? 0),
            'paymentCount' => (int)($summary['paymentCount'] ?? 0),
            'invoiceCount' => (int)($summary['invoiceCount'] ?? 0),
        ];
    }

    /**
     * Calculate overpayment amount.
     *
     * @param float $paymentAmount Payment amount
     * @param float $balanceRemaining Current balance remaining
     * @return float Overpayment amount (0 if no overpayment)
     */
    public function calculateOverpayment($paymentAmount, $balanceRemaining)
    {
        $overpayment = (float)$paymentAmount - (float)$balanceRemaining;
        return max(0, round($overpayment, 2));
    }

    /**
     * Determine if a payment method requires a reference.
     *
     * @param string $method Payment method
     * @return bool True if reference is required
     */
    public function requiresReference($method)
    {
        $methodsRequiringReference = ['Check', 'Bank Transfer', 'Online'];
        return in_array($method, $methodsRequiringReference);
    }

    /**
     * Format payment amount for display.
     *
     * @param float $amount Payment amount
     * @param string $currencySymbol Currency symbol (default: $)
     * @return string Formatted amount
     */
    public function formatAmount($amount, $currencySymbol = '$')
    {
        return $currencySymbol . number_format((float)$amount, 2);
    }

    /**
     * Get recent payments for dashboard display.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param int $limit Number of payments to return
     * @return array Array of recent payments
     */
    public function getRecentPayments($gibbonSchoolYearID, $limit = 10)
    {
        $result = $this->paymentGateway->selectRecentPayments($gibbonSchoolYearID, $limit);
        $payments = [];

        while ($row = $result->fetch()) {
            $payments[] = $row;
        }

        return $payments;
    }
}
