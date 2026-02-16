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

use Gibbon\Contracts\Comms\Mailer as MailerInterface;
use Gibbon\Contracts\Services\Session;
use Gibbon\Domain\System\SettingGateway;

/**
 * InvoiceEmailDelivery
 *
 * Email delivery service for invoice PDFs.
 * Supports single and batch invoice delivery with PDF attachments.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InvoiceEmailDelivery
{
    /**
     * @var MailerInterface
     */
    protected $mailer;

    /**
     * @var Session
     */
    protected $session;

    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var InvoicePDFGenerator
     */
    protected $pdfGenerator;

    /**
     * @var array Last error details
     */
    protected $lastError = [];

    /**
     * Constructor.
     *
     * @param MailerInterface $mailer Gibbon Mailer instance
     * @param Session $session Gibbon Session
     * @param SettingGateway $settingGateway Settings gateway
     * @param InvoicePDFGenerator $pdfGenerator PDF generator service
     */
    public function __construct(
        MailerInterface $mailer,
        Session $session,
        SettingGateway $settingGateway,
        InvoicePDFGenerator $pdfGenerator
    ) {
        $this->mailer = $mailer;
        $this->session = $session;
        $this->settingGateway = $settingGateway;
        $this->pdfGenerator = $pdfGenerator;
    }

    /**
     * Check if email delivery is enabled globally.
     *
     * @return bool
     */
    public function isEmailEnabled()
    {
        $enabled = $this->settingGateway->getSettingByScope('EnhancedFinance', 'invoiceEmailEnabled');
        return $enabled === 'Y' || $enabled === null; // Default to enabled
    }

    /**
     * Validate recipient email address.
     *
     * @param string $email Email address to validate
     * @return bool
     */
    public function isValidEmail($email)
    {
        if (empty($email)) {
            $this->lastError = [
                'code' => 'EMPTY_EMAIL',
                'message' => 'Email address is empty',
            ];
            return false;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = [
                'code' => 'INVALID_EMAIL',
                'message' => 'Email address is not valid',
            ];
            return false;
        }

        return true;
    }

    /**
     * Send an invoice PDF via email.
     *
     * @param array $invoiceData Invoice data for PDF generation
     * @param string $recipientEmail Recipient email address
     * @param string $recipientName Recipient name (optional)
     * @param array $options Optional settings (subject, body, cc, bcc)
     * @return array Result with success/failure details
     */
    public function send(array $invoiceData, $recipientEmail, $recipientName = '', array $options = [])
    {
        // Check if email is enabled
        if (!$this->isEmailEnabled()) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'EMAIL_DISABLED',
                    'message' => 'Invoice email delivery is disabled',
                ],
            ];
        }

        // Validate recipient email
        if (!$this->isValidEmail($recipientEmail)) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        try {
            // Generate PDF as string
            $pdfContent = $this->pdfGenerator->generate($invoiceData, 'S');

            if (!$pdfContent) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'PDF_GENERATION_FAILED',
                        'message' => 'Failed to generate PDF content',
                    ],
                ];
            }

            // Prepare email
            $this->mailer->clearAll();

            // Set sender
            $schoolName = $this->session->get('organisationName') ?: 'LAYA Kindergarten';
            $systemEmail = $this->session->get('organisationEmail');

            if ($systemEmail) {
                $this->mailer->SetFrom($systemEmail, $schoolName);
            }

            // Set recipient
            $this->mailer->addAddress($recipientEmail, $recipientName);

            // Add CC recipients if provided
            if (!empty($options['cc'])) {
                if (is_array($options['cc'])) {
                    foreach ($options['cc'] as $cc) {
                        $this->mailer->addCC($cc);
                    }
                } else {
                    $this->mailer->addCC($options['cc']);
                }
            }

            // Add BCC recipients if provided
            if (!empty($options['bcc'])) {
                if (is_array($options['bcc'])) {
                    foreach ($options['bcc'] as $bcc) {
                        $this->mailer->addBCC($bcc);
                    }
                } else {
                    $this->mailer->addBCC($options['bcc']);
                }
            }

            // Set subject
            $invoiceNumber = $invoiceData['invoiceNumber'] ?? 'Invoice';
            $subject = $options['subject'] ?? "Invoice {$invoiceNumber} - {$schoolName}";
            $this->mailer->setSubject($subject);

            // Set body
            $body = $options['body'] ?? $this->getDefaultEmailBody($invoiceData);
            $this->mailer->setBody($this->formatEmailBody($body, $invoiceData));

            // Attach PDF
            $filename = "invoice_{$invoiceNumber}.pdf";
            $this->mailer->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');

            // Send email
            $sent = $this->mailer->Send();

            if ($sent) {
                return [
                    'success' => true,
                    'message' => 'Invoice email sent successfully',
                    'recipient' => $recipientEmail,
                    'invoiceNumber' => $invoiceNumber,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'SEND_FAILED',
                        'message' => $this->mailer->ErrorInfo ?? 'Unknown mailer error',
                    ],
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'EXCEPTION',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Send multiple invoices as individual emails.
     *
     * @param array $invoices Array of invoice data with recipient info
     *                        Format: [['invoiceData' => [...], 'recipientEmail' => '...', 'recipientName' => '...'], ...]
     * @param array $options Optional settings applied to all emails
     * @return array Results for each invoice
     */
    public function sendBatch(array $invoices, array $options = [])
    {
        $results = [];

        foreach ($invoices as $index => $invoice) {
            if (!isset($invoice['invoiceData']) || !isset($invoice['recipientEmail'])) {
                $results[] = [
                    'success' => false,
                    'index' => $index,
                    'error' => [
                        'code' => 'MISSING_DATA',
                        'message' => 'Invoice data or recipient email missing',
                    ],
                ];
                continue;
            }

            $recipientName = $invoice['recipientName'] ?? '';
            $invoiceOptions = array_merge($options, $invoice['options'] ?? []);

            $result = $this->send(
                $invoice['invoiceData'],
                $invoice['recipientEmail'],
                $recipientName,
                $invoiceOptions
            );

            $result['index'] = $index;
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Send a combined batch of invoices as a single PDF to one recipient.
     *
     * @param array $invoicesData Array of invoice data for batch PDF
     * @param string $recipientEmail Recipient email address
     * @param string $recipientName Recipient name (optional)
     * @param array $options Optional settings (subject, body, cc, bcc)
     * @return array Result with success/failure details
     */
    public function sendBatchPDF(array $invoicesData, $recipientEmail, $recipientName = '', array $options = [])
    {
        // Check if email is enabled
        if (!$this->isEmailEnabled()) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'EMAIL_DISABLED',
                    'message' => 'Invoice email delivery is disabled',
                ],
            ];
        }

        // Validate recipient email
        if (!$this->isValidEmail($recipientEmail)) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        if (empty($invoicesData)) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'NO_INVOICES',
                    'message' => 'No invoices provided for batch email',
                ],
            ];
        }

        try {
            // Generate batch PDF as string
            $pdfContent = $this->pdfGenerator->generateBatch($invoicesData, 'S');

            if (!$pdfContent) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'PDF_GENERATION_FAILED',
                        'message' => 'Failed to generate batch PDF content',
                    ],
                ];
            }

            // Prepare email
            $this->mailer->clearAll();

            // Set sender
            $schoolName = $this->session->get('organisationName') ?: 'LAYA Kindergarten';
            $systemEmail = $this->session->get('organisationEmail');

            if ($systemEmail) {
                $this->mailer->SetFrom($systemEmail, $schoolName);
            }

            // Set recipient
            $this->mailer->addAddress($recipientEmail, $recipientName);

            // Add CC recipients if provided
            if (!empty($options['cc'])) {
                if (is_array($options['cc'])) {
                    foreach ($options['cc'] as $cc) {
                        $this->mailer->addCC($cc);
                    }
                } else {
                    $this->mailer->addCC($options['cc']);
                }
            }

            // Add BCC recipients if provided
            if (!empty($options['bcc'])) {
                if (is_array($options['bcc'])) {
                    foreach ($options['bcc'] as $bcc) {
                        $this->mailer->addBCC($bcc);
                    }
                } else {
                    $this->mailer->addBCC($options['bcc']);
                }
            }

            // Set subject
            $invoiceCount = count($invoicesData);
            $subject = $options['subject'] ?? "Batch Invoices ({$invoiceCount} invoices) - {$schoolName}";
            $this->mailer->setSubject($subject);

            // Set body
            $body = $options['body'] ?? $this->getDefaultBatchEmailBody($invoicesData);
            $this->mailer->setBody($this->formatBatchEmailBody($body, $invoicesData));

            // Attach batch PDF
            $filename = $options['filename'] ?? 'invoices_batch.pdf';
            $this->mailer->addStringAttachment($pdfContent, $filename, 'base64', 'application/pdf');

            // Send email
            $sent = $this->mailer->Send();

            if ($sent) {
                return [
                    'success' => true,
                    'message' => 'Batch invoice email sent successfully',
                    'recipient' => $recipientEmail,
                    'invoiceCount' => $invoiceCount,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'SEND_FAILED',
                        'message' => $this->mailer->ErrorInfo ?? 'Unknown mailer error',
                    ],
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'EXCEPTION',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Get default email body for single invoice.
     *
     * @param array $invoiceData Invoice data
     * @return string Email body text
     */
    protected function getDefaultEmailBody(array $invoiceData)
    {
        $customerName = $invoiceData['customerName'] ?? 'Valued Customer';
        $invoiceNumber = $invoiceData['invoiceNumber'] ?? 'N/A';
        $schoolName = $this->session->get('organisationName') ?: 'LAYA Kindergarten';

        return "Dear {$customerName},\n\n"
            . "Please find attached your invoice #{$invoiceNumber} from {$schoolName}.\n\n"
            . "If you have any questions about this invoice, please don't hesitate to contact us.\n\n"
            . "Thank you for your business.";
    }

    /**
     * Get default email body for batch invoices.
     *
     * @param array $invoicesData Array of invoice data
     * @return string Email body text
     */
    protected function getDefaultBatchEmailBody(array $invoicesData)
    {
        $invoiceCount = count($invoicesData);
        $schoolName = $this->session->get('organisationName') ?: 'LAYA Kindergarten';

        // Extract invoice numbers
        $invoiceNumbers = array_map(function ($invoice) {
            return $invoice['invoiceNumber'] ?? 'N/A';
        }, $invoicesData);

        $invoiceList = implode(', ', $invoiceNumbers);

        return "Dear Valued Customer,\n\n"
            . "Please find attached {$invoiceCount} invoice(s) from {$schoolName}.\n\n"
            . "Invoice numbers: {$invoiceList}\n\n"
            . "If you have any questions about these invoices, please don't hesitate to contact us.\n\n"
            . "Thank you for your business.";
    }

    /**
     * Format email body with HTML template.
     *
     * @param string $body Plain text or HTML body
     * @param array $invoiceData Invoice data
     * @return string Formatted HTML email body
     */
    protected function formatEmailBody($body, array $invoiceData)
    {
        $schoolName = $this->session->get('organisationName') ?: 'LAYA Kindergarten';
        $schoolWebsite = $this->session->get('organisationWebsite') ?: '';
        $invoiceNumber = $invoiceData['invoiceNumber'] ?? 'N/A';

        // Convert newlines to HTML breaks if body is plain text
        if (strpos($body, '<p>') === false && strpos($body, '<br') === false) {
            $body = nl2br(htmlspecialchars($body));
        }

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice ' . htmlspecialchars($invoiceNumber) . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .header { background: #4A90A4; color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .content { padding: 40px 30px; }
        .content p { margin: 0 0 15px 0; }
        .invoice-info { background: #f8f9fa; border-left: 4px solid #4A90A4; padding: 15px 20px; margin: 20px 0; }
        .invoice-info strong { color: #4A90A4; }
        .footer { background: #f8f9fa; padding: 30px 20px; text-align: center; font-size: 13px; color: #666; border-top: 1px solid #e0e0e0; }
        .footer a { color: #4A90A4; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . htmlspecialchars($schoolName) . '</h1>
        </div>
        <div class="content">
            ' . $body . '
            <div class="invoice-info">
                <strong>Invoice Number:</strong> ' . htmlspecialchars($invoiceNumber) . '<br>
                <strong>Attachment:</strong> invoice_' . htmlspecialchars($invoiceNumber) . '.pdf
            </div>
        </div>
        <div class="footer">
            <p><strong>' . htmlspecialchars($schoolName) . '</strong></p>';

        if ($schoolWebsite) {
            $html .= '<p><a href="' . htmlspecialchars($schoolWebsite) . '">' . htmlspecialchars($schoolWebsite) . '</a></p>';
        }

        $html .= '
            <p style="margin-top: 20px; color: #999; font-size: 12px;">
                This is an automated message. Please do not reply directly to this email.
            </p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Format batch email body with HTML template.
     *
     * @param string $body Plain text or HTML body
     * @param array $invoicesData Array of invoice data
     * @return string Formatted HTML email body
     */
    protected function formatBatchEmailBody($body, array $invoicesData)
    {
        $schoolName = $this->session->get('organisationName') ?: 'LAYA Kindergarten';
        $schoolWebsite = $this->session->get('organisationWebsite') ?: '';
        $invoiceCount = count($invoicesData);

        // Convert newlines to HTML breaks if body is plain text
        if (strpos($body, '<p>') === false && strpos($body, '<br') === false) {
            $body = nl2br(htmlspecialchars($body));
        }

        // Build invoice list
        $invoiceList = '';
        foreach ($invoicesData as $invoice) {
            $invoiceNumber = $invoice['invoiceNumber'] ?? 'N/A';
            $customerName = $invoice['customerName'] ?? 'N/A';
            $invoiceList .= '<li>' . htmlspecialchars($invoiceNumber) . ' - ' . htmlspecialchars($customerName) . '</li>';
        }

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Invoices</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f5f5f5; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
        .header { background: #4A90A4; color: white; padding: 30px 20px; text-align: center; }
        .header h1 { margin: 0; font-size: 28px; font-weight: 600; }
        .content { padding: 40px 30px; }
        .content p { margin: 0 0 15px 0; }
        .invoice-info { background: #f8f9fa; border-left: 4px solid #4A90A4; padding: 15px 20px; margin: 20px 0; }
        .invoice-info strong { color: #4A90A4; }
        .invoice-list { margin: 15px 0; padding-left: 20px; }
        .invoice-list li { margin: 5px 0; }
        .footer { background: #f8f9fa; padding: 30px 20px; text-align: center; font-size: 13px; color: #666; border-top: 1px solid #e0e0e0; }
        .footer a { color: #4A90A4; text-decoration: none; }
        .footer a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>' . htmlspecialchars($schoolName) . '</h1>
        </div>
        <div class="content">
            ' . $body . '
            <div class="invoice-info">
                <strong>Number of Invoices:</strong> ' . $invoiceCount . '<br>
                <strong>Invoices Included:</strong>
                <ul class="invoice-list">
                    ' . $invoiceList . '
                </ul>
            </div>
        </div>
        <div class="footer">
            <p><strong>' . htmlspecialchars($schoolName) . '</strong></p>';

        if ($schoolWebsite) {
            $html .= '<p><a href="' . htmlspecialchars($schoolWebsite) . '">' . htmlspecialchars($schoolWebsite) . '</a></p>';
        }

        $html .= '
            <p style="margin-top: 20px; color: #999; font-size: 12px;">
                This is an automated message. Please do not reply directly to this email.
            </p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Get the last error details.
     *
     * @return array Error details with code and message
     */
    public function getLastError()
    {
        return $this->lastError;
    }
}
