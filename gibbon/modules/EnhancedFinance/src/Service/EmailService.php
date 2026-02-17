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

use Gibbon\Contracts\Comms\Mailer as MailerInterface;
use Gibbon\Contracts\Services\Session;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Module\EnhancedFinance\Domain\Releve24PDFGenerator;
use InvalidArgumentException;
use RuntimeException;

/**
 * EmailService
 *
 * Email delivery service for sending RL-24 PDFs to parents.
 * Supports single and batch email sending with PDF attachments.
 * Uses Gibbon's Mailer interface and logs all sends for compliance.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class EmailService
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
     * @var Connection
     */
    protected $connection;

    /**
     * @var Releve24PDFGenerator
     */
    protected $pdfGenerator;

    /**
     * @var array Last error details
     */
    protected $lastError = [];

    /**
     * @var array Email send log for compliance
     */
    protected $sendLog = [];

    /**
     * Constructor.
     *
     * @param MailerInterface $mailer Gibbon Mailer instance
     * @param Session $session Gibbon Session
     * @param Connection $connection Database connection
     * @param Releve24PDFGenerator $pdfGenerator PDF generator instance
     */
    public function __construct(
        MailerInterface $mailer,
        Session $session,
        Connection $connection,
        Releve24PDFGenerator $pdfGenerator
    ) {
        $this->mailer = $mailer;
        $this->session = $session;
        $this->connection = $connection;
        $this->pdfGenerator = $pdfGenerator;
    }

    /**
     * Send RL-24 PDF to a parent via email.
     *
     * @param string $releve24Id UUID of the RL-24 document
     * @param string|null $recipientEmail Optional override email address
     * @return array Result with success/failure details
     */
    public function sendRL24Email(string $releve24Id, ?string $recipientEmail = null): array
    {
        // Validate UUID format
        if (!$this->isValidUuid($releve24Id)) {
            $this->lastError = [
                'code' => 'INVALID_UUID',
                'message' => 'Invalid RL-24 document ID format',
            ];
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        // Get RL-24 document data
        $releve24 = $this->getReleve24Data($releve24Id);
        if (!$releve24) {
            $this->lastError = [
                'code' => 'DOCUMENT_NOT_FOUND',
                'message' => 'RL-24 document not found',
            ];
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        // Get recipient data
        $recipient = $this->getRecipientData($releve24['gibbonFamilyID'] ?? null);

        // Use provided email or fallback to family's primary email
        $email = $recipientEmail ?: ($recipient['email'] ?? null);

        // Validate email address
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->lastError = [
                'code' => 'INVALID_EMAIL',
                'message' => 'No valid email address found for recipient',
            ];
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        try {
            // Generate PDF
            $pdfContent = $this->pdfGenerator->generatePDF($releve24Id);
            $pdfFilename = $this->generatePdfFilename($releve24);

            // Get child data for email personalization
            $childData = $this->getChildData($releve24['gibbonPersonID'] ?? null);

            // Send email with attachment
            $result = $this->sendEmailWithAttachment(
                $email,
                $recipient['name'] ?? '',
                $releve24,
                $childData,
                $pdfContent,
                $pdfFilename
            );

            // Log the send for compliance
            $this->logEmailSend($releve24Id, $email, $result['success'], $result['error'] ?? null);

            return $result;

        } catch (\Exception $e) {
            $error = [
                'code' => 'EXCEPTION',
                'message' => $e->getMessage(),
            ];
            $this->lastError = $error;

            // Log the failed send
            $this->logEmailSend($releve24Id, $email, false, $error);

            return [
                'success' => false,
                'error' => $error,
            ];
        }
    }

    /**
     * Send multiple RL-24 PDFs to parents via email.
     *
     * @param array $releve24Ids Array of RL-24 document UUIDs
     * @return array Results for each document
     */
    public function sendBatchRL24Emails(array $releve24Ids): array
    {
        if (empty($releve24Ids)) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'EMPTY_BATCH',
                    'message' => 'No RL-24 document IDs provided',
                ],
                'results' => [],
            ];
        }

        $results = [];
        $successCount = 0;
        $failureCount = 0;
        $skippedCount = 0;

        foreach ($releve24Ids as $releve24Id) {
            $result = $this->sendRL24Email($releve24Id);
            $results[$releve24Id] = $result;

            if ($result['success']) {
                $successCount++;
            } elseif (isset($result['skipped']) && $result['skipped']) {
                $skippedCount++;
            } else {
                $failureCount++;
            }
        }

        return [
            'success' => $successCount > 0,
            'summary' => [
                'total' => count($releve24Ids),
                'success' => $successCount,
                'failed' => $failureCount,
                'skipped' => $skippedCount,
            ],
            'results' => $results,
        ];
    }

    /**
     * Check if a recipient can receive RL-24 emails.
     *
     * @param string $releve24Id UUID of the RL-24 document
     * @return array Validation result with email if valid
     */
    public function canSendToRecipient(string $releve24Id): array
    {
        // Validate UUID format
        if (!$this->isValidUuid($releve24Id)) {
            return [
                'canSend' => false,
                'error' => 'Invalid RL-24 document ID format',
            ];
        }

        // Get RL-24 document data
        $releve24 = $this->getReleve24Data($releve24Id);
        if (!$releve24) {
            return [
                'canSend' => false,
                'error' => 'RL-24 document not found',
            ];
        }

        // Get recipient data
        $recipient = $this->getRecipientData($releve24['gibbonFamilyID'] ?? null);
        $email = $recipient['email'] ?? null;

        if (empty($email)) {
            return [
                'canSend' => false,
                'error' => 'No email address found for parent',
            ];
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'canSend' => false,
                'error' => 'Invalid email address format',
            ];
        }

        return [
            'canSend' => true,
            'email' => $email,
            'recipientName' => $recipient['name'] ?? '',
        ];
    }

    /**
     * Send an email with PDF attachment.
     *
     * @param string $email Recipient email address
     * @param string $recipientName Recipient name
     * @param array $releve24 RL-24 document data
     * @param array $childData Child data
     * @param string $pdfContent Binary PDF content
     * @param string $pdfFilename PDF filename
     * @return array Send result
     */
    protected function sendEmailWithAttachment(
        string $email,
        string $recipientName,
        array $releve24,
        array $childData,
        string $pdfContent,
        string $pdfFilename
    ): array {
        try {
            // Get organization details
            $schoolName = $this->session->get('organisationName') ?: 'LAYA Kindergarten';
            $systemEmail = $this->session->get('organisationEmail');

            // Prepare the email
            $this->mailer->clearAll();

            // Set sender
            if ($systemEmail) {
                $this->mailer->SetFrom($systemEmail, $schoolName);
            }

            // Set recipient
            $this->mailer->addAddress($email, $recipientName);

            // Set subject
            $documentYear = $releve24['document_year'] ?? date('Y');
            $childName = $childData['name'] ?? 'votre enfant';
            $subject = "Relevé 24 - {$documentYear} - {$childName}";
            $this->mailer->setSubject($subject);

            // Set body with professional email template
            $body = $this->formatEmailBody($releve24, $childData, $recipientName);
            $this->mailer->setBody($body);

            // Add PDF attachment
            $this->mailer->AddStringAttachment($pdfContent, $pdfFilename, 'base64', 'application/pdf');

            // Send the email
            $sent = $this->mailer->Send();

            if ($sent) {
                return [
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'recipient' => $email,
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
     * Format the email body for RL-24 delivery.
     *
     * @param array $releve24 RL-24 document data
     * @param array $childData Child data
     * @param string $recipientName Recipient name
     * @return string Formatted HTML email body
     */
    protected function formatEmailBody(array $releve24, array $childData, string $recipientName): string
    {
        $schoolName = $this->session->get('organisationName') ?: 'LAYA Kindergarten';
        $schoolWebsite = $this->session->get('organisationWebsite') ?: '';
        $schoolPhone = $this->session->get('organisationPhone') ?: '';
        $documentYear = $releve24['document_year'] ?? date('Y');
        $childName = $childData['name'] ?? 'votre enfant';
        $totalEligible = number_format((float)($releve24['total_eligible'] ?? 0), 2);

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relevé 24 - ' . htmlspecialchars($documentYear) . '</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4A90A4; color: white; padding: 20px; text-align: center; }
        .content { background: #ffffff; padding: 30px; }
        .info-box { background: #f5f5f5; padding: 15px; margin: 20px 0; border-left: 4px solid #4A90A4; }
        .amount { font-size: 24px; font-weight: bold; color: #2E7D32; }
        .footer { background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .footer a { color: #4A90A4; }
        .important { background: #FFF3E0; padding: 15px; margin: 20px 0; border-left: 4px solid #FF9800; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; font-size: 24px;">' . htmlspecialchars($schoolName) . '</h1>
            <p style="margin: 10px 0 0 0; font-size: 14px;">Relevé 24 - Frais de garde d\'enfants</p>
        </div>
        <div class="content">
            <p>Bonjour' . ($recipientName ? ' ' . htmlspecialchars($recipientName) : '') . ',</p>

            <p>Veuillez trouver ci-joint votre <strong>Relevé 24</strong> pour l\'année d\'imposition <strong>' . htmlspecialchars($documentYear) . '</strong>,
            concernant les frais de garde de <strong>' . htmlspecialchars($childName) . '</strong>.</p>

            <div class="info-box">
                <p style="margin: 0 0 10px 0;"><strong>Résumé du document:</strong></p>
                <p style="margin: 0;">Année d\'imposition: <strong>' . htmlspecialchars($documentYear) . '</strong></p>
                <p style="margin: 0;">Enfant: <strong>' . htmlspecialchars($childName) . '</strong></p>
                <p style="margin: 0;">Montant admissible (Case 46): <span class="amount">$' . $totalEligible . '</span></p>
            </div>

            <div class="important">
                <p style="margin: 0;"><strong>Important:</strong> Ce document est requis pour votre déclaration de revenus auprès de
                Revenu Québec. Conservez-le précieusement avec vos documents fiscaux.</p>
            </div>

            <p>Si vous avez des questions concernant ce relevé, n\'hésitez pas à nous contacter.</p>

            <p>Cordialement,<br>' . htmlspecialchars($schoolName) . '</p>
        </div>
        <div class="footer">
            <p><strong>' . htmlspecialchars($schoolName) . '</strong></p>';

        if ($schoolPhone) {
            $html .= '<p>Téléphone: ' . htmlspecialchars($schoolPhone) . '</p>';
        }

        if ($schoolWebsite) {
            $html .= '<p><a href="' . htmlspecialchars($schoolWebsite) . '">' . htmlspecialchars($schoolWebsite) . '</a></p>';
        }

        $html .= '
            <p style="margin-top: 15px; font-size: 10px; color: #999;">
                Ce courriel a été généré automatiquement. Veuillez ne pas y répondre directement.
            </p>
        </div>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Get RL-24 document data from database.
     *
     * @param string $releve24Id
     * @return array|null
     */
    protected function getReleve24Data(string $releve24Id): ?array
    {
        $sql = "SELECT * FROM enhanced_finance_releve24 WHERE id = :id";
        $data = ['id' => $releve24Id];

        $result = $this->connection->selectOne($sql, $data);
        return $result ?: null;
    }

    /**
     * Get recipient (parent) data from database.
     *
     * @param int|null $familyId
     * @return array
     */
    protected function getRecipientData(?int $familyId): array
    {
        if ($familyId === null) {
            return ['name' => '', 'email' => ''];
        }

        $sql = "SELECT
                    gp.preferredName,
                    gp.surname,
                    gp.email
                FROM gibbonFamily gf
                LEFT JOIN gibbonFamilyAdult gfa ON gf.gibbonFamilyID = gfa.gibbonFamilyID AND gfa.contactPriority = 1
                LEFT JOIN gibbonPerson gp ON gfa.gibbonPersonID = gp.gibbonPersonID
                WHERE gf.gibbonFamilyID = :familyId";

        $data = ['familyId' => $familyId];
        $result = $this->connection->selectOne($sql, $data);

        if (!$result) {
            return ['name' => '', 'email' => ''];
        }

        return [
            'name' => trim(($result['preferredName'] ?? '') . ' ' . ($result['surname'] ?? '')),
            'email' => $result['email'] ?? '',
        ];
    }

    /**
     * Get child data from database.
     *
     * @param int|null $personId
     * @return array
     */
    protected function getChildData(?int $personId): array
    {
        if ($personId === null) {
            return ['name' => 'N/A'];
        }

        $sql = "SELECT preferredName, surname FROM gibbonPerson WHERE gibbonPersonID = :personId";
        $data = ['personId' => $personId];
        $result = $this->connection->selectOne($sql, $data);

        if (!$result) {
            return ['name' => 'N/A'];
        }

        return [
            'name' => trim(($result['preferredName'] ?? '') . ' ' . ($result['surname'] ?? '')) ?: 'N/A',
        ];
    }

    /**
     * Generate PDF filename for a given RL-24 document.
     *
     * @param array $releve24Data
     * @return string
     */
    protected function generatePdfFilename(array $releve24Data): string
    {
        $year = $releve24Data['document_year'] ?? date('Y');
        $familyId = $releve24Data['gibbonFamilyID'] ?? 'unknown';
        $childId = $releve24Data['gibbonPersonID'] ?? '';

        $suffix = $childId ? "_{$childId}" : '';
        return "RL24_{$year}_{$familyId}{$suffix}.pdf";
    }

    /**
     * Validate UUID format.
     *
     * @param string $uuid
     * @return bool
     */
    protected function isValidUuid(string $uuid): bool
    {
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
        return (bool) preg_match($pattern, $uuid);
    }

    /**
     * Log email send for compliance.
     *
     * @param string $releve24Id Document ID
     * @param string $email Recipient email
     * @param bool $success Send success status
     * @param array|null $error Error details if failed
     * @return void
     */
    protected function logEmailSend(string $releve24Id, string $email, bool $success, ?array $error = null): void
    {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'releve24Id' => $releve24Id,
            'email' => $email,
            'success' => $success,
            'error' => $error,
            'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? 'CLI',
        ];

        $this->sendLog[] = $logEntry;

        // Also log to database for persistent compliance record
        $this->logToDatabase($logEntry);
    }

    /**
     * Log email send to database for compliance.
     *
     * @param array $logEntry Log entry data
     * @return void
     */
    protected function logToDatabase(array $logEntry): void
    {
        try {
            // Check if log table exists, if not skip database logging
            $sql = "INSERT INTO enhanced_finance_email_log
                    (releve24_id, recipient_email, success, error_code, error_message, ip_address, user_agent, created_at)
                    VALUES (:releve24Id, :email, :success, :errorCode, :errorMessage, :ipAddress, :userAgent, :timestamp)";

            $data = [
                'releve24Id' => $logEntry['releve24Id'],
                'email' => $logEntry['email'],
                'success' => $logEntry['success'] ? 1 : 0,
                'errorCode' => $logEntry['error']['code'] ?? null,
                'errorMessage' => $logEntry['error']['message'] ?? null,
                'ipAddress' => $logEntry['ipAddress'],
                'userAgent' => substr($logEntry['userAgent'], 0, 255),
                'timestamp' => $logEntry['timestamp'],
            ];

            $this->connection->insert($sql, $data);
        } catch (\Exception $e) {
            // Silently fail database logging - don't break email send for logging issues
            // The in-memory sendLog still captures the data
        }
    }

    /**
     * Get the send log for the current session.
     *
     * @return array
     */
    public function getSendLog(): array
    {
        return $this->sendLog;
    }

    /**
     * Get the last error details.
     *
     * @return array
     */
    public function getLastError(): array
    {
        return $this->lastError;
    }

    /**
     * Clear the send log.
     *
     * @return void
     */
    public function clearSendLog(): void
    {
        $this->sendLog = [];
    }
}
