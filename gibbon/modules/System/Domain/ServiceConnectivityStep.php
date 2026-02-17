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

namespace Gibbon\Module\System\Domain;

use Gibbon\Domain\System\SettingGateway;

/**
 * ServiceConnectivityStep
 *
 * Handles service connectivity verification for the setup wizard.
 * Checks connectivity to MySQL, PostgreSQL, Redis, and AI service
 * to ensure all required services are accessible before completing setup.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ServiceConnectivityStep
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
     * @var InstallationDetector
     */
    protected $installationDetector;

    /**
     * Service check results
     */
    const SERVICE_STATUS_OK = 'ok';
    const SERVICE_STATUS_WARNING = 'warning';
    const SERVICE_STATUS_ERROR = 'error';

    /**
     * Constructor.
     *
     * @param SettingGateway $settingGateway Settings gateway
     * @param \PDO $pdo Database connection
     * @param InstallationDetector $installationDetector Installation detector
     */
    public function __construct(
        SettingGateway $settingGateway,
        \PDO $pdo,
        InstallationDetector $installationDetector
    ) {
        $this->settingGateway = $settingGateway;
        $this->pdo = $pdo;
        $this->installationDetector = $installationDetector;
    }

    /**
     * Check connectivity to all required services.
     *
     * @param array $config Optional configuration (service hosts, ports, etc.)
     * @return array Service check results with status for each service
     */
    public function checkServices(array $config = [])
    {
        $results = [];

        // Check MySQL connectivity (already connected via PDO)
        $results['mysql'] = $this->checkMySQL();

        // Check PostgreSQL connectivity (optional)
        $results['postgresql'] = $this->checkPostgreSQL($config['postgresql'] ?? []);

        // Check Redis connectivity (optional)
        $results['redis'] = $this->checkRedis($config['redis'] ?? []);

        // Check AI service connectivity (optional)
        $results['ai_service'] = $this->checkAIService($config['ai_service'] ?? []);

        return $results;
    }

    /**
     * Check MySQL database connectivity.
     *
     * @return array Status with details
     */
    protected function checkMySQL()
    {
        try {
            // Test basic connectivity
            $stmt = $this->pdo->query("SELECT VERSION() as version");
            $version = $stmt->fetchColumn();

            // Check if we can create/read tables
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'gibbonSetting'");
            $tableExists = $stmt->fetchColumn();

            if (!$tableExists) {
                return [
                    'status' => self::SERVICE_STATUS_WARNING,
                    'message' => 'MySQL connected but core tables not found',
                    'version' => $version,
                ];
            }

            return [
                'status' => self::SERVICE_STATUS_OK,
                'message' => 'MySQL connection successful',
                'version' => $version,
            ];
        } catch (\PDOException $e) {
            return [
                'status' => self::SERVICE_STATUS_ERROR,
                'message' => 'MySQL connection failed: ' . $e->getMessage(),
                'version' => null,
            ];
        }
    }

    /**
     * Check PostgreSQL database connectivity.
     *
     * @param array $config PostgreSQL configuration (host, port, database, user, password)
     * @return array Status with details
     */
    protected function checkPostgreSQL(array $config)
    {
        // PostgreSQL is optional - if no config provided, mark as not configured
        if (empty($config['host'])) {
            return [
                'status' => self::SERVICE_STATUS_WARNING,
                'message' => 'PostgreSQL not configured (optional)',
                'version' => null,
            ];
        }

        try {
            $host = $config['host'];
            $port = $config['port'] ?? 5432;
            $database = $config['database'] ?? 'postgres';
            $user = $config['user'] ?? 'postgres';
            $password = $config['password'] ?? '';

            $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
            $pgsql = new \PDO($dsn, $user, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_TIMEOUT => 5,
            ]);

            $stmt = $pgsql->query("SELECT version()");
            $version = $stmt->fetchColumn();

            return [
                'status' => self::SERVICE_STATUS_OK,
                'message' => 'PostgreSQL connection successful',
                'version' => $version,
            ];
        } catch (\PDOException $e) {
            return [
                'status' => self::SERVICE_STATUS_ERROR,
                'message' => 'PostgreSQL connection failed: ' . $e->getMessage(),
                'version' => null,
            ];
        }
    }

    /**
     * Check Redis connectivity.
     *
     * @param array $config Redis configuration (host, port, password)
     * @return array Status with details
     */
    protected function checkRedis(array $config)
    {
        // Redis is optional - if no config provided, mark as not configured
        if (empty($config['host'])) {
            return [
                'status' => self::SERVICE_STATUS_WARNING,
                'message' => 'Redis not configured (optional)',
                'version' => null,
            ];
        }

        // Check if Redis extension is available
        if (!extension_loaded('redis')) {
            return [
                'status' => self::SERVICE_STATUS_WARNING,
                'message' => 'Redis PHP extension not installed (optional)',
                'version' => null,
            ];
        }

        try {
            $host = $config['host'];
            $port = $config['port'] ?? 6379;
            $password = $config['password'] ?? null;
            $timeout = $config['timeout'] ?? 2.5;

            $redis = new \Redis();
            $connected = $redis->connect($host, $port, $timeout);

            if (!$connected) {
                throw new \Exception('Failed to connect to Redis');
            }

            // Authenticate if password is provided
            if ($password) {
                $redis->auth($password);
            }

            // Test basic operation
            $redis->ping();
            $info = $redis->info('server');
            $version = $info['redis_version'] ?? 'unknown';

            $redis->close();

            return [
                'status' => self::SERVICE_STATUS_OK,
                'message' => 'Redis connection successful',
                'version' => $version,
            ];
        } catch (\Exception $e) {
            return [
                'status' => self::SERVICE_STATUS_ERROR,
                'message' => 'Redis connection failed: ' . $e->getMessage(),
                'version' => null,
            ];
        }
    }

    /**
     * Check AI service connectivity.
     *
     * @param array $config AI service configuration (url, api_key)
     * @return array Status with details
     */
    protected function checkAIService(array $config)
    {
        // AI service is optional - if no config provided, mark as not configured
        if (empty($config['url'])) {
            return [
                'status' => self::SERVICE_STATUS_WARNING,
                'message' => 'AI service not configured (optional)',
                'version' => null,
            ];
        }

        try {
            $url = rtrim($config['url'], '/');
            $healthEndpoint = $url . '/health';
            $apiKey = $config['api_key'] ?? null;
            $timeout = $config['timeout'] ?? 10;

            // Build context options
            $contextOptions = [
                'http' => [
                    'method' => 'GET',
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
            ];

            // Add API key header if provided
            if ($apiKey) {
                $contextOptions['http']['header'] = "Authorization: Bearer {$apiKey}\r\n";
            }

            $context = stream_context_create($contextOptions);

            // Try to connect to health endpoint
            $response = @file_get_contents($healthEndpoint, false, $context);

            if ($response === false) {
                // Try base URL if health endpoint fails
                $response = @file_get_contents($url, false, $context);
                if ($response === false) {
                    throw new \Exception('Unable to reach AI service');
                }
            }

            // Check HTTP response code
            $httpCode = 200;
            if (isset($http_response_header)) {
                foreach ($http_response_header as $header) {
                    if (preg_match('/HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                        $httpCode = (int)$matches[1];
                        break;
                    }
                }
            }

            if ($httpCode >= 200 && $httpCode < 300) {
                // Try to parse version from response if available
                $version = null;
                $data = @json_decode($response, true);
                if ($data && isset($data['version'])) {
                    $version = $data['version'];
                }

                return [
                    'status' => self::SERVICE_STATUS_OK,
                    'message' => 'AI service connection successful',
                    'version' => $version,
                ];
            } else {
                return [
                    'status' => self::SERVICE_STATUS_WARNING,
                    'message' => "AI service responded with HTTP {$httpCode}",
                    'version' => null,
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => self::SERVICE_STATUS_ERROR,
                'message' => 'AI service connection failed: ' . $e->getMessage(),
                'version' => null,
            ];
        }
    }

    /**
     * Validate service configuration.
     *
     * @param array $data Service configuration data
     * @return array Array of validation errors (empty if valid)
     */
    public function validate(array $data)
    {
        $errors = [];

        // PostgreSQL configuration validation (optional)
        if (isset($data['postgresql']['enabled']) && $data['postgresql']['enabled']) {
            if (empty($data['postgresql']['host'])) {
                $errors['postgresql_host'] = 'PostgreSQL host is required when enabled';
            }
        }

        // Redis configuration validation (optional)
        if (isset($data['redis']['enabled']) && $data['redis']['enabled']) {
            if (empty($data['redis']['host'])) {
                $errors['redis_host'] = 'Redis host is required when enabled';
            }
        }

        // AI service configuration validation (optional)
        if (isset($data['ai_service']['enabled']) && $data['ai_service']['enabled']) {
            if (empty($data['ai_service']['url'])) {
                $errors['ai_service_url'] = 'AI service URL is required when enabled';
            } elseif (!filter_var($data['ai_service']['url'], FILTER_VALIDATE_URL)) {
                $errors['ai_service_url'] = 'AI service URL must be a valid URL';
            }
        }

        return $errors;
    }

    /**
     * Save service connectivity check results.
     *
     * @param array $results Service check results
     * @return bool True if successful
     */
    public function save(array $results)
    {
        try {
            // Begin transaction
            $this->pdo->beginTransaction();

            try {
                // Save check results as JSON in settings
                $resultsJson = json_encode($results);
                $this->saveSetting('System', 'serviceConnectivityCheck', $resultsJson);

                // Save check timestamp
                $this->saveSetting('System', 'serviceConnectivityCheckTime', date('Y-m-d H:i:s'));

                // Save progress in wizard
                $this->installationDetector->saveWizardProgress('service_connectivity', [
                    'results' => $results,
                    'timestamp' => time(),
                ]);

                $this->pdo->commit();
                return true;
            } catch (\Exception $e) {
                $this->pdo->rollBack();
                return false;
            }
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Save a setting to the database.
     *
     * @param string $scope Setting scope
     * @param string $name Setting name
     * @param string $value Setting value
     * @return bool True if successful
     */
    protected function saveSetting($scope, $name, $value)
    {
        try {
            // Check if setting exists
            $stmt = $this->pdo->prepare("
                SELECT gibbonSettingID FROM gibbonSetting
                WHERE scope = :scope AND name = :name
            ");
            $stmt->execute([':scope' => $scope, ':name' => $name]);
            $exists = $stmt->fetchColumn();

            if ($exists) {
                // Update existing setting
                $stmt = $this->pdo->prepare("
                    UPDATE gibbonSetting
                    SET value = :value
                    WHERE scope = :scope AND name = :name
                ");
            } else {
                // Insert new setting
                $stmt = $this->pdo->prepare("
                    INSERT INTO gibbonSetting (scope, name, value, nameDisplay, description)
                    VALUES (:scope, :name, :value, :nameDisplay, :description)
                ");
                $stmt->bindValue(':nameDisplay', ucfirst($name));
                $stmt->bindValue(':description', 'Auto-generated by setup wizard');
            }

            $stmt->bindValue(':scope', $scope);
            $stmt->bindValue(':name', $name);
            $stmt->bindValue(':value', $value);
            $stmt->execute();

            return true;
        } catch (\PDOException $e) {
            throw $e;
        }
    }

    /**
     * Get saved service connectivity check results.
     *
     * @return array|null Service check results or null if not found
     */
    public function getCheckResults()
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT value FROM gibbonSetting
                WHERE scope = 'System' AND name = 'serviceConnectivityCheck'
            ");
            $stmt->execute();
            $json = $stmt->fetchColumn();

            if ($json) {
                return json_decode($json, true);
            }

            return null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Check if service connectivity verification has been completed.
     *
     * @return bool True if connectivity checks completed
     */
    public function isCompleted()
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) FROM gibbonSetting
                WHERE scope = 'System' AND name = 'serviceConnectivityCheck'
            ");
            $stmt->execute();
            $count = (int) $stmt->fetchColumn();
            return $count > 0;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Get service connectivity data from wizard progress (for resume capability).
     *
     * @return array|null Connectivity data from wizard progress
     */
    public function getWizardProgress()
    {
        $progress = $this->installationDetector->getWizardProgress();

        if ($progress && isset($progress['stepData']) && is_array($progress['stepData'])) {
            return $progress['stepData'];
        }

        return null;
    }

    /**
     * Prepare service connectivity data for display.
     * Merges saved data with wizard progress.
     *
     * @return array Service connectivity data
     */
    public function prepareData()
    {
        // Start with saved check results
        $data = $this->getCheckResults();

        // Override with wizard progress if available (for resume)
        $wizardData = $this->getWizardProgress();
        if ($wizardData && isset($wizardData['results'])) {
            $data = $wizardData['results'];
        }

        return $data ?: [];
    }

    /**
     * Get overall service status.
     *
     * @param array $results Service check results
     * @return string Overall status (ok, warning, error)
     */
    public function getOverallStatus(array $results)
    {
        $hasError = false;
        $hasWarning = false;

        foreach ($results as $service => $result) {
            if ($result['status'] === self::SERVICE_STATUS_ERROR) {
                // MySQL errors are critical
                if ($service === 'mysql') {
                    return self::SERVICE_STATUS_ERROR;
                }
                $hasError = true;
            } elseif ($result['status'] === self::SERVICE_STATUS_WARNING) {
                $hasWarning = true;
            }
        }

        if ($hasError) {
            return self::SERVICE_STATUS_ERROR;
        } elseif ($hasWarning) {
            return self::SERVICE_STATUS_WARNING;
        }

        return self::SERVICE_STATUS_OK;
    }

    /**
     * Clear service connectivity check data (for testing/reset).
     *
     * @return bool True if successful
     */
    public function clear()
    {
        try {
            $this->pdo->beginTransaction();

            try {
                // Delete service connectivity settings
                $stmt = $this->pdo->prepare("
                    DELETE FROM gibbonSetting
                    WHERE scope = 'System' AND name IN ('serviceConnectivityCheck', 'serviceConnectivityCheckTime')
                ");
                $stmt->execute();

                $this->pdo->commit();
                return true;
            } catch (\PDOException $e) {
                $this->pdo->rollBack();
                return false;
            }
        } catch (\PDOException $e) {
            return false;
        }
    }
}
