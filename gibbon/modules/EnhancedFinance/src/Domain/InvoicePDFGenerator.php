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

namespace Gibbon\Module\EnhancedFinance\Domain;

use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\System\SettingGateway;
use Mpdf\Mpdf;

/**
 * InvoicePDFGenerator
 *
 * Professional invoice PDF generation service using mPDF.
 * Supports daycare branding, itemized services, GST/QST tax calculation,
 * payment terms, and customizable templates.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InvoicePDFGenerator
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var array Last error details
     */
    protected $lastError = [];

    /**
     * GST tax rate (5%)
     */
    const GST_RATE = 0.05;

    /**
     * QST tax rate (9.975%)
     */
    const QST_RATE = 0.09975;

    /**
     * Constructor.
     *
     * @param Session $session Gibbon Session
     * @param SettingGateway $settingGateway Settings gateway
     */
    public function __construct(
        Session $session,
        SettingGateway $settingGateway
    ) {
        $this->session = $session;
        $this->settingGateway = $settingGateway;
    }

    /**
     * Generate an invoice PDF for a single invoice.
     *
     * @param array $invoiceData Invoice data including header, items, and totals
     * @param string $outputMode Output mode: 'D' (download), 'F' (file), 'S' (string), 'I' (inline)
     * @param string|null $filename Output filename (for D or F modes)
     * @return mixed PDF content (string for S mode) or boolean (F mode) or void (D/I modes)
     * @throws \Exception on PDF generation errors
     */
    public function generate(array $invoiceData, $outputMode = 'D', $filename = null)
    {
        try {
            // Validate invoice data
            $this->validateInvoiceData($invoiceData);

            // Generate PDF
            $mpdf = $this->createMpdfInstance();
            $html = $this->renderInvoiceTemplate($invoiceData);

            $mpdf->WriteHTML($html);

            // Set default filename if not provided
            if ($filename === null) {
                $invoiceNumber = $invoiceData['invoiceNumber'] ?? 'invoice';
                $filename = "invoice_{$invoiceNumber}.pdf";
            }

            return $mpdf->Output($filename, $outputMode);
        } catch (\Exception $e) {
            $this->lastError = [
                'code' => 'GENERATION_FAILED',
                'message' => $e->getMessage(),
            ];
            throw $e;
        }
    }

    /**
     * Generate multiple invoices as a single batch PDF.
     *
     * @param array $invoicesData Array of invoice data
     * @param string $outputMode Output mode: 'D' (download), 'F' (file), 'S' (string)
     * @param string|null $filename Output filename
     * @return mixed PDF content or boolean
     * @throws \Exception on PDF generation errors
     */
    public function generateBatch(array $invoicesData, $outputMode = 'D', $filename = null)
    {
        try {
            if (empty($invoicesData)) {
                throw new \Exception('No invoices provided for batch generation');
            }

            $mpdf = $this->createMpdfInstance();

            foreach ($invoicesData as $index => $invoiceData) {
                // Validate each invoice
                $this->validateInvoiceData($invoiceData);

                // Render invoice template
                $html = $this->renderInvoiceTemplate($invoiceData);

                if ($index > 0) {
                    $mpdf->AddPage();
                }

                $mpdf->WriteHTML($html);
            }

            // Set default filename if not provided
            if ($filename === null) {
                $date = date('Y-m-d');
                $filename = "invoices_batch_{$date}.pdf";
            }

            return $mpdf->Output($filename, $outputMode);
        } catch (\Exception $e) {
            $this->lastError = [
                'code' => 'BATCH_GENERATION_FAILED',
                'message' => $e->getMessage(),
            ];
            throw $e;
        }
    }

    /**
     * Create an mPDF instance with default configuration.
     *
     * @return Mpdf
     * @throws \Mpdf\MpdfException
     */
    protected function createMpdfInstance()
    {
        $config = [
            'mode' => 'utf-8',
            'format' => 'Letter',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_header' => 10,
            'margin_footer' => 10,
        ];

        return new Mpdf($config);
    }

    /**
     * Render the invoice HTML template.
     *
     * @param array $invoiceData Invoice data
     * @return string HTML content
     */
    protected function renderInvoiceTemplate(array $invoiceData)
    {
        // Get organization branding
        $organizationName = $this->session->get('organisationName') ?: 'LAYA Kindergarten';
        $organizationAddress = $this->getOrganizationAddress();
        $organizationLogo = $this->getOrganizationLogo();

        // Calculate totals
        $subtotal = $this->calculateSubtotal($invoiceData['items']);
        $gstAmount = $subtotal * self::GST_RATE;
        $qstAmount = $subtotal * self::QST_RATE;
        $total = $subtotal + $gstAmount + $qstAmount;

        // Build HTML
        $html = $this->getInvoiceHeader();
        $html .= $this->buildInvoiceHTML([
            'organizationName' => $organizationName,
            'organizationAddress' => $organizationAddress,
            'organizationLogo' => $organizationLogo,
            'invoiceData' => $invoiceData,
            'subtotal' => $subtotal,
            'gstAmount' => $gstAmount,
            'qstAmount' => $qstAmount,
            'total' => $total,
        ]);

        return $html;
    }

    /**
     * Get the invoice HTML header with CSS styles.
     *
     * @return string HTML header
     */
    protected function getInvoiceHeader()
    {
        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            color: #333;
        }
        .invoice-header {
            margin-bottom: 30px;
            border-bottom: 2px solid #4A90A4;
            padding-bottom: 20px;
        }
        .logo {
            max-width: 200px;
            max-height: 80px;
        }
        .organization-info {
            text-align: right;
            margin-top: 10px;
        }
        .invoice-title {
            font-size: 24pt;
            font-weight: bold;
            color: #4A90A4;
            margin: 20px 0;
        }
        .invoice-info-section {
            margin-bottom: 20px;
        }
        .invoice-info-label {
            font-weight: bold;
            display: inline-block;
            width: 120px;
        }
        .customer-info {
            background-color: #f5f5f5;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        table.items th {
            background-color: #4A90A4;
            color: white;
            padding: 10px;
            text-align: left;
            font-weight: bold;
        }
        table.items td {
            border-bottom: 1px solid #ddd;
            padding: 8px;
        }
        table.items tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        table.items .item-description {
            width: 50%;
        }
        table.items .item-quantity {
            width: 15%;
            text-align: center;
        }
        table.items .item-unit-price {
            width: 17.5%;
            text-align: right;
        }
        table.items .item-total {
            width: 17.5%;
            text-align: right;
        }
        .totals-section {
            float: right;
            width: 300px;
            margin-top: 20px;
        }
        .totals-row {
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
        }
        .totals-label {
            display: inline-block;
            width: 60%;
            font-weight: bold;
        }
        .totals-amount {
            display: inline-block;
            width: 38%;
            text-align: right;
        }
        .total-final {
            font-size: 14pt;
            font-weight: bold;
            background-color: #4A90A4;
            color: white;
            padding: 10px;
            margin-top: 10px;
        }
        .payment-info {
            clear: both;
            margin-top: 60px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        .payment-section-title {
            font-weight: bold;
            font-size: 12pt;
            margin-bottom: 10px;
            color: #4A90A4;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 8pt;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 10px;
        }
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body>';
    }

    /**
     * Build the main invoice HTML content.
     *
     * @param array $data Template data
     * @return string HTML content
     */
    protected function buildInvoiceHTML(array $data)
    {
        $html = '<div class="invoice-header">';

        // Logo and organization info
        if ($data['organizationLogo']) {
            $html .= '<img src="' . htmlspecialchars($data['organizationLogo']) . '" class="logo" alt="Logo">';
        }
        $html .= '<div class="organization-info">';
        $html .= '<strong>' . htmlspecialchars($data['organizationName']) . '</strong><br>';
        $html .= nl2br(htmlspecialchars($data['organizationAddress']));
        $html .= '</div>';
        $html .= '</div>';

        // Invoice title and details
        $html .= '<div class="invoice-title">INVOICE</div>';

        $html .= '<div class="invoice-info-section">';
        $html .= '<span class="invoice-info-label">Invoice Number:</span> ' . htmlspecialchars($data['invoiceData']['invoiceNumber'] ?? 'N/A') . '<br>';
        $html .= '<span class="invoice-info-label">Invoice Date:</span> ' . htmlspecialchars($data['invoiceData']['invoiceDate'] ?? date('Y-m-d')) . '<br>';
        $html .= '<span class="invoice-info-label">Due Date:</span> ' . htmlspecialchars($data['invoiceData']['dueDate'] ?? 'Upon Receipt') . '<br>';
        if (!empty($data['invoiceData']['period'])) {
            $html .= '<span class="invoice-info-label">Billing Period:</span> ' . htmlspecialchars($data['invoiceData']['period']) . '<br>';
        }
        $html .= '</div>';

        // Customer information
        $html .= '<div class="customer-info">';
        $html .= '<strong>Bill To:</strong><br>';
        $html .= htmlspecialchars($data['invoiceData']['customerName'] ?? '') . '<br>';
        if (!empty($data['invoiceData']['customerAddress'])) {
            $html .= nl2br(htmlspecialchars($data['invoiceData']['customerAddress'])) . '<br>';
        }
        if (!empty($data['invoiceData']['customerEmail'])) {
            $html .= 'Email: ' . htmlspecialchars($data['invoiceData']['customerEmail']) . '<br>';
        }
        if (!empty($data['invoiceData']['customerPhone'])) {
            $html .= 'Phone: ' . htmlspecialchars($data['invoiceData']['customerPhone']) . '<br>';
        }
        $html .= '</div>';

        // Itemized services table
        $html .= '<table class="items">';
        $html .= '<thead><tr>';
        $html .= '<th class="item-description">Description</th>';
        $html .= '<th class="item-quantity">Quantity</th>';
        $html .= '<th class="item-unit-price">Unit Price</th>';
        $html .= '<th class="item-total">Total</th>';
        $html .= '</tr></thead>';
        $html .= '<tbody>';

        foreach ($data['invoiceData']['items'] as $item) {
            $itemTotal = $item['quantity'] * $item['unitPrice'];
            $html .= '<tr>';
            $html .= '<td class="item-description">' . htmlspecialchars($item['description']) . '</td>';
            $html .= '<td class="item-quantity">' . htmlspecialchars($item['quantity']) . '</td>';
            $html .= '<td class="item-unit-price">$' . number_format($item['unitPrice'], 2) . '</td>';
            $html .= '<td class="item-total">$' . number_format($itemTotal, 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody>';
        $html .= '</table>';

        // Totals section
        $html .= '<div class="totals-section">';
        $html .= '<div class="totals-row">';
        $html .= '<span class="totals-label">Subtotal:</span>';
        $html .= '<span class="totals-amount">$' . number_format($data['subtotal'], 2) . '</span>';
        $html .= '</div>';

        $html .= '<div class="totals-row">';
        $html .= '<span class="totals-label">GST (5%):</span>';
        $html .= '<span class="totals-amount">$' . number_format($data['gstAmount'], 2) . '</span>';
        $html .= '</div>';

        $html .= '<div class="totals-row">';
        $html .= '<span class="totals-label">QST (9.975%):</span>';
        $html .= '<span class="totals-amount">$' . number_format($data['qstAmount'], 2) . '</span>';
        $html .= '</div>';

        $html .= '<div class="total-final">';
        $html .= '<span class="totals-label">TOTAL DUE:</span>';
        $html .= '<span class="totals-amount">$' . number_format($data['total'], 2) . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        // Payment information
        $html .= '<div class="payment-info">';
        $html .= '<div class="payment-section-title">Payment Terms & Methods</div>';
        $html .= '<p>' . htmlspecialchars($data['invoiceData']['paymentTerms'] ?? 'Payment is due upon receipt.') . '</p>';

        if (!empty($data['invoiceData']['paymentMethods'])) {
            $html .= '<p><strong>Accepted Payment Methods:</strong><br>';
            $html .= nl2br(htmlspecialchars($data['invoiceData']['paymentMethods']));
            $html .= '</p>';
        }

        if (!empty($data['invoiceData']['notes'])) {
            $html .= '<div class="payment-section-title">Notes</div>';
            $html .= '<p>' . nl2br(htmlspecialchars($data['invoiceData']['notes'])) . '</p>';
        }
        $html .= '</div>';

        // Footer
        $html .= '<div class="footer">';
        $html .= 'Thank you for your business!<br>';
        $html .= 'This invoice was generated on ' . date('Y-m-d H:i:s') . '';
        $html .= '</div>';

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Calculate subtotal from invoice items.
     *
     * @param array $items Invoice items
     * @return float Subtotal amount
     */
    protected function calculateSubtotal(array $items)
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $quantity = floatval($item['quantity'] ?? 0);
            $unitPrice = floatval($item['unitPrice'] ?? 0);
            $subtotal += $quantity * $unitPrice;
        }

        return $subtotal;
    }

    /**
     * Validate invoice data structure.
     *
     * @param array $invoiceData Invoice data to validate
     * @throws \Exception if validation fails
     */
    protected function validateInvoiceData(array $invoiceData)
    {
        // Required fields
        $requiredFields = ['invoiceNumber', 'customerName', 'items'];

        foreach ($requiredFields as $field) {
            if (empty($invoiceData[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Validate items
        if (!is_array($invoiceData['items']) || empty($invoiceData['items'])) {
            throw new \Exception('Invoice must contain at least one item');
        }

        // Validate each item
        foreach ($invoiceData['items'] as $index => $item) {
            if (empty($item['description'])) {
                throw new \Exception("Item {$index}: Missing description");
            }
            if (!isset($item['quantity']) || $item['quantity'] <= 0) {
                throw new \Exception("Item {$index}: Invalid quantity");
            }
            if (!isset($item['unitPrice']) || $item['unitPrice'] < 0) {
                throw new \Exception("Item {$index}: Invalid unit price");
            }
        }
    }

    /**
     * Get organization address from settings.
     *
     * @return string Organization address
     */
    protected function getOrganizationAddress()
    {
        $address1 = $this->session->get('organisationAddress') ?: '';
        $address2 = $this->session->get('organisationAddressLocality') ?: '';
        $address3 = $this->session->get('organisationAddressRegion') ?: '';
        $postalCode = $this->session->get('organisationPostalCode') ?: '';
        $country = $this->session->get('organisationCountry') ?: '';

        $addressParts = array_filter([$address1, $address2, $address3, $postalCode, $country]);
        return implode("\n", $addressParts);
    }

    /**
     * Get organization logo path.
     *
     * @return string|null Logo file path or null if not set
     */
    protected function getOrganizationLogo()
    {
        $logo = $this->session->get('organisationLogo');

        if ($logo && file_exists($logo)) {
            return $logo;
        }

        return null;
    }

    /**
     * Get the last error details.
     *
     * @return array Error details
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Save invoice PDF to file.
     *
     * @param array $invoiceData Invoice data
     * @param string $filepath Output file path
     * @return bool Success status
     */
    public function saveToFile(array $invoiceData, $filepath)
    {
        try {
            $this->generate($invoiceData, 'F', $filepath);
            return true;
        } catch (\Exception $e) {
            $this->lastError = [
                'code' => 'SAVE_FAILED',
                'message' => $e->getMessage(),
            ];
            return false;
        }
    }

    /**
     * Generate invoice PDF as string for email attachment.
     *
     * @param array $invoiceData Invoice data
     * @return string|false PDF content or false on failure
     */
    public function generateAsString(array $invoiceData)
    {
        try {
            return $this->generate($invoiceData, 'S');
        } catch (\Exception $e) {
            $this->lastError = [
                'code' => 'STRING_GENERATION_FAILED',
                'message' => $e->getMessage(),
            ];
            return false;
        }
    }
}
