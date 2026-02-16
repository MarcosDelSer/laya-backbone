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

namespace Gibbon\Module\AISync;

use Gibbon\Domain\System\SettingGateway;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Utils;

/**
 * AISyncService
 *
 * Webhook service for synchronizing data between Gibbon and the AI service.
 * Uses GuzzleHttp\Client with postAsync() for non-blocking webhook calls.
 * Supports retry logic with exponential backoff for failed syncs.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AISyncService
{
    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     * @var Client|null Guzzle HTTP client instance
     */
    protected $client = null;

    /**
     * @var bool Whether the service is initialized
     */
    protected $initialized = false;

    /**
     * @var array Last error details
     */
    protected $lastError = [];

    /**
     * @var array Pending promises for async operations
     */
    protected $pendingPromises = [];

    /**
     * Constructor.
     *
     * @param SettingGateway $settingGateway Settings gateway
     * @param \PDO $pdo Database connection
     */
    public function __construct(SettingGateway $settingGateway, \PDO $pdo)
    {
        $this->settingGateway = $settingGateway;
        $this->pdo = $pdo;
    }

    /**
     * Initialize the Guzzle HTTP client.
     *
     * @return bool True if initialized successfully
     */
    protected function initializeClient()
    {
        if ($this->initialized) {
            return true;
        }

        // Check if sync is enabled
        if (!$this->isSyncEnabled()) {
            $this->lastError = [
                'code' => 'SYNC_DISABLED',
                'message' => 'AI Sync is disabled in settings',
            ];
            return false;
        }

        // Get AI service URL from settings
        $baseUri = $this->getAIServiceURL();

        if (empty($baseUri)) {
            $this->lastError = [
                'code' => 'URL_MISSING',
                'message' => 'AI Service URL is not configured',
            ];
            return false;
        }

        // Get timeout setting
        $timeout = $this->getWebhookTimeout();

        try {
            // Check if Guzzle is available
            if (!class_exists('GuzzleHttp\Client')) {
                $this->lastError = [
                    'code' => 'GUZZLE_NOT_INSTALLED',
                    'message' => 'guzzlehttp/guzzle package is not installed. Run: composer require guzzlehttp/guzzle:^7.0',
                ];
                return false;
            }

            // Initialize Guzzle HTTP client
            $this->client = new Client([
                'base_uri' => $baseUri,
                'timeout'  => $timeout,
                'connect_timeout' => 10.0,
                'http_errors' => false,
            ]);

            $this->initialized = true;
            return true;
        } catch (\Exception $e) {
            $this->lastError = [
                'code' => 'INIT_FAILED',
                'message' => 'Guzzle client initialization failed: ' . $e->getMessage(),
            ];
            return false;
        }
    }

    /**
     * Check if AI sync is enabled globally.
     *
     * @return bool
     */
    public function isSyncEnabled()
    {
        $enabled = $this->settingGateway->getSettingByScope('AI Sync', 'syncEnabled');
        return $enabled === 'Y';
    }

    /**
     * Get the AI service base URL.
     *
     * @return string
     */
    public function getAIServiceURL()
    {
        // First check environment variable
        $url = getenv('AI_SERVICE_URL');
        if (!empty($url)) {
            return rtrim($url, '/');
        }

        // Fall back to database setting
        $url = $this->settingGateway->getSettingByScope('AI Sync', 'aiServiceURL');
        return rtrim($url ?: '', '/');
    }

    /**
     * Get the webhook timeout in seconds.
     *
     * @return float
     */
    public function getWebhookTimeout()
    {
        $timeout = $this->settingGateway->getSettingByScope('AI Sync', 'webhookTimeout');
        return (float) ($timeout ?: 30);
    }

    /**
     * Get the maximum retry attempts.
     *
     * @return int
     */
    public function getMaxRetryAttempts()
    {
        $retries = $this->settingGateway->getSettingByScope('AI Sync', 'maxRetryAttempts');
        return (int) ($retries ?: 3);
    }

    /**
     * Get the base retry delay in seconds.
     *
     * @return int
     */
    public function getRetryDelaySeconds()
    {
        $delay = $this->settingGateway->getSettingByScope('AI Sync', 'retryDelaySeconds');
        return (int) ($delay ?: 30);
    }

    /**
     * Generate a JWT token for authenticating with the AI service.
     *
     * @return string|null JWT token or null if unable to generate
     */
    protected function generateJWTToken()
    {
        $secretKey = getenv('JWT_SECRET_KEY');
        if (empty($secretKey)) {
            $this->lastError = [
                'code' => 'JWT_SECRET_MISSING',
                'message' => 'JWT_SECRET_KEY environment variable is not set',
            ];
            return null;
        }

        $algorithm = getenv('JWT_ALGORITHM') ?: 'HS256';

        // Build JWT header
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => $algorithm,
        ]);

        // Build JWT payload with expiration
        $payload = json_encode([
            'iss' => 'gibbon',
            'sub' => 'ai-sync',
            'iat' => time(),
            'exp' => time() + 3600, // 1 hour expiration
            'scope' => 'webhook',
        ]);

        // Encode header and payload
        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);

        // Create signature
        $signature = hash_hmac('sha256', "$base64UrlHeader.$base64UrlPayload", $secretKey, true);
        $base64UrlSignature = $this->base64UrlEncode($signature);

        return "$base64UrlHeader.$base64UrlPayload.$base64UrlSignature";
    }

    /**
     * Base64 URL encode a string.
     *
     * @param string $data
     * @return string
     */
    protected function base64UrlEncode($data)
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Create a sync log entry in the database.
     *
     * @param string $eventType Event type (e.g., 'care_activity_created')
     * @param string $entityType Entity type (e.g., 'activity')
     * @param int $entityID Entity ID
     * @param array $payload Data payload
     * @return int|null Log entry ID or null on failure
     */
    protected function createSyncLog($eventType, $entityType, $entityID, array $payload)
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO gibbonAISyncLog
                (eventType, entityType, entityID, payload, status, timestampCreated)
                VALUES (:eventType, :entityType, :entityID, :payload, 'pending', NOW())
            ");

            $stmt->execute([
                ':eventType' => $eventType,
                ':entityType' => $entityType,
                ':entityID' => $entityID,
                ':payload' => json_encode($payload),
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (\PDOException $e) {
            $this->lastError = [
                'code' => 'DB_ERROR',
                'message' => 'Failed to create sync log: ' . $e->getMessage(),
            ];
            return null;
        }
    }

    /**
     * Update a sync log entry with the result.
     *
     * @param int $logID Log entry ID
     * @param string $status Status: 'success' or 'failed'
     * @param string|null $response Response from AI service
     * @param string|null $errorMessage Error message if failed
     * @return bool
     */
    protected function updateSyncLog($logID, $status, $response = null, $errorMessage = null)
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE gibbonAISyncLog
                SET status = :status,
                    response = :response,
                    errorMessage = :errorMessage,
                    timestampProcessed = NOW(),
                    retryCount = retryCount + 1
                WHERE gibbonAISyncLogID = :logID
            ");

            return $stmt->execute([
                ':status' => $status,
                ':response' => $response,
                ':errorMessage' => $errorMessage,
                ':logID' => $logID,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Send a webhook to the AI service asynchronously.
     * Uses Guzzle's postAsync() for non-blocking HTTP requests.
     *
     * @param string $eventType Event type (e.g., 'care_activity_created')
     * @param string $entityType Entity type (e.g., 'activity')
     * @param int $entityID Entity ID
     * @param array $payload Data payload
     * @return array Result with log ID and promise
     */
    public function sendWebhookAsync($eventType, $entityType, $entityID, array $payload)
    {
        if (!$this->initializeClient()) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        // Generate JWT token
        $jwtToken = $this->generateJWTToken();
        if (!$jwtToken) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        // Create sync log entry
        $logID = $this->createSyncLog($eventType, $entityType, $entityID, $payload);
        if (!$logID) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        // Build webhook payload
        $webhookPayload = [
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityID,
            'payload' => $payload,
            'timestamp' => date('c'),
        ];

        // Send async POST request using Guzzle postAsync
        $promise = $this->client->postAsync('/api/v1/webhook', [
            'json' => $webhookPayload,
            'headers' => [
                'Authorization' => 'Bearer ' . $jwtToken,
                'Content-Type' => 'application/json',
                'X-Webhook-Event' => $eventType,
            ],
        ]);

        // Handle success callback
        $pdo = $this->pdo;
        $promise->then(
            function ($response) use ($logID, $pdo) {
                $statusCode = $response->getStatusCode();
                $body = (string) $response->getBody();

                if ($statusCode >= 200 && $statusCode < 300) {
                    // Update sync log as success
                    $stmt = $pdo->prepare("
                        UPDATE gibbonAISyncLog
                        SET status = 'success',
                            response = :response,
                            timestampProcessed = NOW()
                        WHERE gibbonAISyncLogID = :logID
                    ");
                    $stmt->execute([
                        ':response' => $body,
                        ':logID' => $logID,
                    ]);
                } else {
                    // Update sync log as failed
                    $stmt = $pdo->prepare("
                        UPDATE gibbonAISyncLog
                        SET status = 'failed',
                            response = :response,
                            errorMessage = :errorMessage,
                            retryCount = retryCount + 1,
                            timestampProcessed = NOW()
                        WHERE gibbonAISyncLogID = :logID
                    ");
                    $stmt->execute([
                        ':response' => $body,
                        ':errorMessage' => "HTTP {$statusCode} error",
                        ':logID' => $logID,
                    ]);
                }
            },
            function ($exception) use ($logID, $pdo) {
                // Update sync log as failed
                $errorMessage = $exception instanceof RequestException
                    ? $exception->getMessage()
                    : 'Unknown error occurred';

                $stmt = $pdo->prepare("
                    UPDATE gibbonAISyncLog
                    SET status = 'failed',
                        errorMessage = :errorMessage,
                        retryCount = retryCount + 1,
                        timestampProcessed = NOW()
                    WHERE gibbonAISyncLogID = :logID
                ");
                $stmt->execute([
                    ':errorMessage' => $errorMessage,
                    ':logID' => $logID,
                ]);
            }
        );

        // Store promise for later resolution if needed
        $this->pendingPromises[$logID] = $promise;

        return [
            'success' => true,
            'logID' => $logID,
            'async' => true,
            'message' => 'Webhook queued for async delivery',
        ];
    }

    /**
     * Send a webhook synchronously (blocking).
     * Use this when you need immediate confirmation of delivery.
     *
     * @param string $eventType Event type
     * @param string $entityType Entity type
     * @param int $entityID Entity ID
     * @param array $payload Data payload
     * @return array Result with success status
     */
    public function sendWebhookSync($eventType, $entityType, $entityID, array $payload)
    {
        if (!$this->initializeClient()) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        // Generate JWT token
        $jwtToken = $this->generateJWTToken();
        if (!$jwtToken) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        // Create sync log entry
        $logID = $this->createSyncLog($eventType, $entityType, $entityID, $payload);
        if (!$logID) {
            return [
                'success' => false,
                'error' => $this->lastError,
            ];
        }

        // Build webhook payload
        $webhookPayload = [
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityID,
            'payload' => $payload,
            'timestamp' => date('c'),
        ];

        try {
            $response = $this->client->post('/api/v1/webhook', [
                'json' => $webhookPayload,
                'headers' => [
                    'Authorization' => 'Bearer ' . $jwtToken,
                    'Content-Type' => 'application/json',
                    'X-Webhook-Event' => $eventType,
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = (string) $response->getBody();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->updateSyncLog($logID, 'success', $body);
                return [
                    'success' => true,
                    'logID' => $logID,
                    'statusCode' => $statusCode,
                    'response' => $body,
                ];
            } else {
                $this->updateSyncLog($logID, 'failed', $body, "HTTP {$statusCode} error");
                return [
                    'success' => false,
                    'logID' => $logID,
                    'statusCode' => $statusCode,
                    'error' => [
                        'code' => 'HTTP_ERROR',
                        'message' => "HTTP {$statusCode} error",
                    ],
                ];
            }
        } catch (ConnectException $e) {
            $this->updateSyncLog($logID, 'failed', null, 'Connection failed: ' . $e->getMessage());
            return [
                'success' => false,
                'logID' => $logID,
                'error' => [
                    'code' => 'CONNECTION_ERROR',
                    'message' => 'Failed to connect to AI service: ' . $e->getMessage(),
                ],
            ];
        } catch (RequestException $e) {
            $this->updateSyncLog($logID, 'failed', null, 'Request failed: ' . $e->getMessage());
            return [
                'success' => false,
                'logID' => $logID,
                'error' => [
                    'code' => 'REQUEST_ERROR',
                    'message' => $e->getMessage(),
                ],
            ];
        }
    }

    /**
     * Wait for all pending async webhooks to complete.
     *
     * @return array Results for all pending promises
     */
    public function waitForPending()
    {
        if (empty($this->pendingPromises)) {
            return [];
        }

        try {
            $results = Utils::settle($this->pendingPromises)->wait();
            $this->pendingPromises = [];
            return $results;
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Retry a failed sync by log ID.
     *
     * @param int $logID Sync log ID to retry
     * @return array Result of retry attempt
     */
    public function retryFailedSync($logID)
    {
        // Get the failed sync entry
        $stmt = $this->pdo->prepare("
            SELECT * FROM gibbonAISyncLog
            WHERE gibbonAISyncLogID = :logID AND status = 'failed'
        ");
        $stmt->execute([':logID' => $logID]);
        $log = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$log) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Failed sync log entry not found',
                ],
            ];
        }

        // Check retry count
        $maxRetries = $this->getMaxRetryAttempts();
        if ($log['retryCount'] >= $maxRetries) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'MAX_RETRIES_EXCEEDED',
                    'message' => "Maximum retry attempts ({$maxRetries}) exceeded",
                ],
            ];
        }

        // Parse the original payload
        $payload = json_decode($log['payload'], true);
        if (!$payload) {
            $payload = [];
        }

        // Reset status to pending for retry
        $stmt = $this->pdo->prepare("
            UPDATE gibbonAISyncLog
            SET status = 'pending'
            WHERE gibbonAISyncLogID = :logID
        ");
        $stmt->execute([':logID' => $logID]);

        // Resend the webhook
        return $this->sendWebhookSync(
            $log['eventType'],
            $log['entityType'],
            $log['entityID'],
            $payload
        );
    }

    /**
     * Sync a care activity event to the AI service.
     *
     * @param int $activityID Activity ID
     * @param array $activityData Activity data
     * @return array Result
     */
    public function syncCareActivity($activityID, array $activityData)
    {
        return $this->sendWebhookAsync(
            'care_activity_created',
            'activity',
            $activityID,
            $activityData
        );
    }

    /**
     * Sync a meal event to the AI service.
     *
     * @param int $mealID Meal log ID
     * @param array $mealData Meal data
     * @return array Result
     */
    public function syncMealEvent($mealID, array $mealData)
    {
        return $this->sendWebhookAsync(
            'meal_logged',
            'meal',
            $mealID,
            $mealData
        );
    }

    /**
     * Sync a nap event to the AI service.
     *
     * @param int $napID Nap log ID
     * @param array $napData Nap data
     * @return array Result
     */
    public function syncNapEvent($napID, array $napData)
    {
        return $this->sendWebhookAsync(
            'nap_logged',
            'nap',
            $napID,
            $napData
        );
    }

    /**
     * Sync a photo upload event to the AI service.
     *
     * @param int $photoID Photo ID
     * @param array $photoData Photo metadata
     * @return array Result
     */
    public function syncPhotoUpload($photoID, array $photoData)
    {
        return $this->sendWebhookAsync(
            'photo_uploaded',
            'photo',
            $photoID,
            $photoData
        );
    }

    /**
     * Sync a photo tag event to the AI service.
     *
     * @param int $photoID Photo ID
     * @param array $tagData Photo tag data including tagged children
     * @return array Result
     */
    public function syncPhotoTag($photoID, array $tagData)
    {
        return $this->sendWebhookAsync(
            'photo_tagged',
            'photo',
            $photoID,
            $tagData
        );
    }

    /**
     * Sync a photo delete event to the AI service.
     *
     * @param int $photoID Photo ID
     * @param array $photoData Photo metadata
     * @return array Result
     */
    public function syncPhotoDelete($photoID, array $photoData)
    {
        return $this->sendWebhookAsync(
            'photo_deleted',
            'photo',
            $photoID,
            $photoData
        );
    }

    /**
     * Sync a check-in event to the AI service.
     *
     * @param int $attendanceID Attendance record ID
     * @param array $attendanceData Attendance data
     * @return array Result
     */
    public function syncCheckIn($attendanceID, array $attendanceData)
    {
        return $this->sendWebhookAsync(
            'child_checked_in',
            'attendance',
            $attendanceID,
            $attendanceData
        );
    }

    /**
     * Sync a check-out event to the AI service.
     *
     * @param int $attendanceID Attendance record ID
     * @param array $attendanceData Attendance data
     * @return array Result
     */
    public function syncCheckOut($attendanceID, array $attendanceData)
    {
        return $this->sendWebhookAsync(
            'child_checked_out',
            'attendance',
            $attendanceID,
            $attendanceData
        );
    }

    /**
     * Get the last error details.
     *
     * @return array
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Get sync status information.
     *
     * @return array Status information
     */
    public function getStatus()
    {
        return [
            'enabled' => $this->isSyncEnabled(),
            'initialized' => $this->initialized,
            'guzzleInstalled' => class_exists('GuzzleHttp\Client'),
            'aiServiceUrl' => $this->getAIServiceURL(),
            'timeout' => $this->getWebhookTimeout(),
            'maxRetries' => $this->getMaxRetryAttempts(),
            'pendingPromises' => count($this->pendingPromises),
        ];
    }

    /**
     * Get statistics about sync operations.
     *
     * @param string|null $since Date string to filter from
     * @return array Statistics
     */
    public function getStatistics($since = null)
    {
        try {
            $whereClause = '';
            $params = [];

            if ($since) {
                $whereClause = 'WHERE timestampCreated >= :since';
                $params[':since'] = $since;
            }

            $stmt = $this->pdo->prepare("
                SELECT
                    status,
                    COUNT(*) as count
                FROM gibbonAISyncLog
                {$whereClause}
                GROUP BY status
            ");
            $stmt->execute($params);
            $statusCounts = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            $stmt = $this->pdo->prepare("
                SELECT
                    eventType,
                    COUNT(*) as count
                FROM gibbonAISyncLog
                {$whereClause}
                GROUP BY eventType
            ");
            $stmt->execute($params);
            $eventCounts = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            return [
                'byStatus' => $statusCounts,
                'byEventType' => $eventCounts,
                'total' => array_sum($statusCounts),
            ];
        } catch (\PDOException $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }
}
