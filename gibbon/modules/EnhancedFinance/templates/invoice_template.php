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

/**
 * Professional Invoice PDF Template
 *
 * This template generates a professional invoice PDF with daycare branding,
 * itemized services, GST/QST tax calculations, and payment terms.
 *
 * Expected Template Variables:
 * - $organizationName (string): Daycare/organization name
 * - $organizationAddress (string): Organization address with line breaks
 * - $organizationLogo (string|null): Path to organization logo
 * - $organizationPhone (string): Organization phone number
 * - $organizationEmail (string): Organization email
 * - $invoiceData (array): Invoice details including:
 *   - invoiceNumber (string): Invoice number
 *   - invoiceDate (string): Invoice date (Y-m-d format)
 *   - dueDate (string): Payment due date
 *   - period (string): Billing period (optional)
 *   - customerName (string): Customer/family name
 *   - customerAddress (string): Customer address (optional)
 *   - customerEmail (string): Customer email (optional)
 *   - customerPhone (string): Customer phone (optional)
 *   - items (array): Line items with description, quantity, unitPrice
 *   - paymentTerms (string): Payment terms text
 *   - paymentMethods (string): Accepted payment methods
 *   - notes (string): Additional notes (optional)
 * - $subtotal (float): Subtotal before taxes
 * - $gstAmount (float): GST tax amount (5%)
 * - $qstAmount (float): QST tax amount (9.975%)
 * - $total (float): Total amount due
 * - $gstRate (float): GST rate (default 0.05)
 * - $qstRate (float): QST rate (default 0.09975)
 *
 * @version v1.0.00
 * @since   v1.0.00
 */

// Prevent direct access
if (!isset($organizationName) || !isset($invoiceData)) {
    die('This template requires template variables to be set.');
}

// Default values
$organizationPhone = $organizationPhone ?? '';
$organizationEmail = $organizationEmail ?? '';
$gstRate = $gstRate ?? 0.05;
$qstRate = $qstRate ?? 0.09975;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoiceData['invoiceNumber'] ?? 'N/A'); ?></title>
    <!-- Print-friendly CSS for browser printing -->
    <link rel="stylesheet" href="<?php echo isset($modulePath) ? $modulePath : '../modules/EnhancedFinance'; ?>/css/invoice_print.css" media="print">
    <style>
        /* Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #333;
            line-height: 1.6;
            background: #fff;
        }

        /* Page Layout */
        .page-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Header Section with Branding */
        .invoice-header {
            display: table;
            width: 100%;
            margin-bottom: 30px;
            border-bottom: 3px solid #4A90A4;
            padding-bottom: 20px;
        }

        .header-left {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }

        .header-right {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            text-align: right;
        }

        .logo {
            max-width: 180px;
            max-height: 100px;
            margin-bottom: 10px;
        }

        .organization-name {
            font-size: 18pt;
            font-weight: bold;
            color: #4A90A4;
            margin-bottom: 5px;
        }

        .organization-info {
            font-size: 9pt;
            color: #666;
            line-height: 1.5;
        }

        /* Invoice Title */
        .invoice-title-section {
            margin: 30px 0 20px 0;
        }

        .invoice-title {
            font-size: 28pt;
            font-weight: bold;
            color: #4A90A4;
            letter-spacing: 1px;
            text-transform: uppercase;
        }

        /* Invoice Details Grid */
        .invoice-details {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }

        .details-left {
            display: table-cell;
            width: 60%;
            vertical-align: top;
            padding-right: 20px;
        }

        .details-right {
            display: table-cell;
            width: 40%;
            vertical-align: top;
        }

        .info-group {
            margin-bottom: 20px;
        }

        .info-label {
            font-weight: bold;
            font-size: 9pt;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 11pt;
            color: #333;
        }

        .info-row {
            margin-bottom: 8px;
        }

        .info-row-label {
            display: inline-block;
            width: 120px;
            font-weight: bold;
            color: #666;
        }

        /* Customer Information Box */
        .customer-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-left: 4px solid #4A90A4;
            padding: 15px;
            margin-bottom: 30px;
            border-radius: 4px;
        }

        .customer-title {
            font-weight: bold;
            font-size: 11pt;
            color: #4A90A4;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .customer-details {
            font-size: 10pt;
            line-height: 1.8;
        }

        /* Itemized Services Table */
        .items-section {
            margin: 30px 0;
        }

        .section-title {
            font-size: 12pt;
            font-weight: bold;
            color: #4A90A4;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table.items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        table.items-table thead {
            background-color: #4A90A4;
            color: white;
        }

        table.items-table th {
            padding: 12px 10px;
            text-align: left;
            font-weight: bold;
            font-size: 10pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table.items-table th.align-center {
            text-align: center;
        }

        table.items-table th.align-right {
            text-align: right;
        }

        table.items-table tbody tr {
            border-bottom: 1px solid #e9ecef;
        }

        table.items-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        table.items-table tbody tr:hover {
            background-color: #e9ecef;
        }

        table.items-table td {
            padding: 10px;
            font-size: 10pt;
            vertical-align: top;
        }

        table.items-table td.item-description {
            width: 50%;
        }

        table.items-table td.item-quantity {
            width: 15%;
            text-align: center;
        }

        table.items-table td.item-unit-price {
            width: 17.5%;
            text-align: right;
        }

        table.items-table td.item-total {
            width: 17.5%;
            text-align: right;
            font-weight: bold;
        }

        .item-description-text {
            font-weight: 500;
            color: #333;
        }

        /* Totals Section */
        .totals-section {
            float: right;
            width: 350px;
            margin-top: 20px;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
        }

        .totals-table tr {
            border-bottom: 1px solid #e9ecef;
        }

        .totals-table td {
            padding: 10px;
            font-size: 10pt;
        }

        .totals-label {
            font-weight: bold;
            color: #666;
            text-align: left;
        }

        .totals-amount {
            text-align: right;
            font-weight: 500;
        }

        .tax-details {
            font-size: 8pt;
            color: #999;
        }

        .total-final-row {
            background-color: #4A90A4;
            color: white;
            font-size: 14pt;
            font-weight: bold;
        }

        .total-final-row td {
            padding: 15px 10px;
        }

        .spacer-row {
            height: 10px;
        }

        /* Payment Information */
        .payment-section {
            clear: both;
            margin-top: 60px;
            padding-top: 30px;
            border-top: 2px solid #e9ecef;
        }

        .payment-title {
            font-size: 12pt;
            font-weight: bold;
            color: #4A90A4;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        .payment-content {
            font-size: 10pt;
            line-height: 1.8;
            color: #555;
        }

        .payment-methods {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-top: 15px;
        }

        .payment-methods-title {
            font-weight: bold;
            color: #4A90A4;
            margin-bottom: 8px;
        }

        /* Notes Section */
        .notes-section {
            margin-top: 20px;
            padding: 15px;
            background-color: #fff9e6;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
        }

        .notes-title {
            font-weight: bold;
            color: #856404;
            margin-bottom: 8px;
        }

        .notes-content {
            font-size: 9pt;
            color: #856404;
            white-space: pre-wrap;
        }

        /* Footer */
        .invoice-footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
            text-align: center;
            font-size: 8pt;
            color: #999;
        }

        .footer-thank-you {
            font-size: 11pt;
            font-weight: bold;
            color: #4A90A4;
            margin-bottom: 10px;
        }

        /* Print Styles */
        @media print {
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
                color-adjust: exact;
            }

            .page-container {
                max-width: 100%;
                padding: 0;
            }

            .page-break {
                page-break-before: always;
            }

            .no-print {
                display: none;
            }

            table.items-table thead {
                background-color: #4A90A4 !important;
                color: white !important;
            }

            .total-final-row {
                background-color: #4A90A4 !important;
                color: white !important;
            }
        }

        /* Utility Classes */
        .text-bold {
            font-weight: bold;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .mb-10 {
            margin-bottom: 10px;
        }

        .mb-20 {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Header Section with Branding -->
        <div class="invoice-header">
            <div class="header-left">
                <?php if ($organizationLogo && file_exists($organizationLogo)): ?>
                    <img src="<?php echo htmlspecialchars($organizationLogo); ?>" alt="<?php echo htmlspecialchars($organizationName); ?> Logo" class="logo">
                <?php endif; ?>
                <div class="organization-name"><?php echo htmlspecialchars($organizationName); ?></div>
            </div>
            <div class="header-right">
                <div class="organization-info">
                    <?php echo nl2br(htmlspecialchars($organizationAddress)); ?>
                    <?php if ($organizationPhone): ?>
                        <br>Tel: <?php echo htmlspecialchars($organizationPhone); ?>
                    <?php endif; ?>
                    <?php if ($organizationEmail): ?>
                        <br>Email: <?php echo htmlspecialchars($organizationEmail); ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Invoice Title -->
        <div class="invoice-title-section">
            <div class="invoice-title">Invoice</div>
        </div>

        <!-- Invoice Details -->
        <div class="invoice-details">
            <div class="details-left">
                <!-- Bill To (Customer Information) -->
                <div class="customer-box">
                    <div class="customer-title">Bill To</div>
                    <div class="customer-details">
                        <strong><?php echo htmlspecialchars($invoiceData['customerName'] ?? 'N/A'); ?></strong>
                        <?php if (!empty($invoiceData['customerAddress'])): ?>
                            <br><?php echo nl2br(htmlspecialchars($invoiceData['customerAddress'])); ?>
                        <?php endif; ?>
                        <?php if (!empty($invoiceData['customerEmail'])): ?>
                            <br>Email: <?php echo htmlspecialchars($invoiceData['customerEmail']); ?>
                        <?php endif; ?>
                        <?php if (!empty($invoiceData['customerPhone'])): ?>
                            <br>Phone: <?php echo htmlspecialchars($invoiceData['customerPhone']); ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="details-right">
                <!-- Invoice Information -->
                <div class="info-group">
                    <div class="info-row">
                        <span class="info-row-label">Invoice #:</span>
                        <strong><?php echo htmlspecialchars($invoiceData['invoiceNumber'] ?? 'N/A'); ?></strong>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Invoice Date:</span>
                        <?php echo htmlspecialchars($invoiceData['invoiceDate'] ?? date('Y-m-d')); ?>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Due Date:</span>
                        <strong><?php echo htmlspecialchars($invoiceData['dueDate'] ?? 'Upon Receipt'); ?></strong>
                    </div>
                    <?php if (!empty($invoiceData['period'])): ?>
                        <div class="info-row">
                            <span class="info-row-label">Billing Period:</span>
                            <?php echo htmlspecialchars($invoiceData['period']); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Itemized Services -->
        <div class="items-section">
            <div class="section-title">Services &amp; Items</div>
            <table class="items-table">
                <thead>
                    <tr>
                        <th class="item-description">Description</th>
                        <th class="align-center">Quantity</th>
                        <th class="align-right">Unit Price</th>
                        <th class="align-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($invoiceData['items']) && is_array($invoiceData['items'])): ?>
                        <?php foreach ($invoiceData['items'] as $item): ?>
                            <?php
                            $itemQuantity = floatval($item['quantity'] ?? 0);
                            $itemUnitPrice = floatval($item['unitPrice'] ?? 0);
                            $itemTotal = $itemQuantity * $itemUnitPrice;
                            ?>
                            <tr>
                                <td class="item-description">
                                    <span class="item-description-text"><?php echo htmlspecialchars($item['description'] ?? 'N/A'); ?></span>
                                </td>
                                <td class="item-quantity"><?php echo number_format($itemQuantity, 0); ?></td>
                                <td class="item-unit-price">$<?php echo number_format($itemUnitPrice, 2); ?></td>
                                <td class="item-total">$<?php echo number_format($itemTotal, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center" style="padding: 20px; color: #999;">
                                No items found
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Totals Section with GST/QST -->
        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td class="totals-label">Subtotal:</td>
                    <td class="totals-amount">$<?php echo number_format($subtotal, 2); ?></td>
                </tr>
                <tr>
                    <td class="totals-label">
                        GST:
                        <span class="tax-details">(<?php echo number_format($gstRate * 100, 2); ?>%)</span>
                    </td>
                    <td class="totals-amount">$<?php echo number_format($gstAmount, 2); ?></td>
                </tr>
                <tr>
                    <td class="totals-label">
                        QST:
                        <span class="tax-details">(<?php echo number_format($qstRate * 100, 3); ?>%)</span>
                    </td>
                    <td class="totals-amount">$<?php echo number_format($qstAmount, 2); ?></td>
                </tr>
                <tr class="spacer-row">
                    <td colspan="2"></td>
                </tr>
                <tr class="total-final-row">
                    <td class="totals-label">TOTAL DUE:</td>
                    <td class="totals-amount">$<?php echo number_format($total, 2); ?></td>
                </tr>
            </table>
        </div>

        <!-- Payment Information -->
        <div class="payment-section">
            <div class="payment-title">Payment Terms &amp; Methods</div>
            <div class="payment-content">
                <p><?php echo htmlspecialchars($invoiceData['paymentTerms'] ?? 'Payment is due upon receipt. Please remit payment by the due date to avoid late fees.'); ?></p>
            </div>

            <?php if (!empty($invoiceData['paymentMethods'])): ?>
                <div class="payment-methods">
                    <div class="payment-methods-title">Accepted Payment Methods:</div>
                    <div><?php echo nl2br(htmlspecialchars($invoiceData['paymentMethods'])); ?></div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Additional Notes -->
        <?php if (!empty($invoiceData['notes'])): ?>
            <div class="notes-section">
                <div class="notes-title">Important Notes:</div>
                <div class="notes-content"><?php echo nl2br(htmlspecialchars($invoiceData['notes'])); ?></div>
            </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="invoice-footer">
            <div class="footer-thank-you">Thank you for your business!</div>
            <div>This invoice was generated on <?php echo date('F j, Y \a\t g:i A'); ?></div>
            <?php if (!empty($invoiceData['invoiceNumber'])): ?>
                <div style="margin-top: 5px;">Invoice Reference: <?php echo htmlspecialchars($invoiceData['invoiceNumber']); ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
