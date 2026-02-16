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

use PHPUnit\Framework\TestCase;
use Gibbon\Module\System\Domain\ServiceConnectivityStep;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\System\Domain\InstallationDetector;

/**
 * ServiceConnectivityStepTest
 *
 * Unit tests for the ServiceConnectivityStep class.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ServiceConnectivityStepTest extends TestCase
{
    protected $pdo;
    protected $settingGateway;
    protected $installationDetector;
    protected $serviceConnectivityStep;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create gibbonSetting table
        $this->pdo->exec("
            CREATE TABLE gibbonSetting (
                gibbonSettingID INTEGER PRIMARY KEY AUTOINCREMENT,
                scope VARCHAR(50),
                name VARCHAR(50),
                value TEXT,
                nameDisplay VARCHAR(100),
                description TEXT
            )
        ");

        // Create mock SettingGateway
        $this->settingGateway = $this->createMock(SettingGateway::class);

        // Create mock InstallationDetector
        $this->installationDetector = $this->createMock(InstallationDetector::class);

        // Create ServiceConnectivityStep instance
        $this->serviceConnectivityStep = new ServiceConnectivityStep(
            $this->settingGateway,
            $this->pdo,
            $this->installationDetector
        );
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
    }

    /**
     * Test MySQL connectivity check (always succeeds with PDO)
     */
    public function testCheckServicesWithMySQLSuccess()
    {
        $results = $this->serviceConnectivityStep->checkServices();

        $this->assertArrayHasKey('mysql', $results);
        $this->assertArrayHasKey('status', $results['mysql']);
        $this->assertArrayHasKey('message', $results['mysql']);
        $this->assertContains($results['mysql']['status'], ['ok', 'warning']);
    }

    /**
     * Test PostgreSQL check with no configuration
     */
    public function testCheckPostgreSQLWithNoConfig()
    {
        $results = $this->serviceConnectivityStep->checkServices([
            'postgresql' => []
        ]);

        $this->assertArrayHasKey('postgresql', $results);
        $this->assertEquals('warning', $results['postgresql']['status']);
        $this->assertStringContainsString('not configured', $results['postgresql']['message']);
    }

    /**
     * Test Redis check with no configuration
     */
    public function testCheckRedisWithNoConfig()
    {
        $results = $this->serviceConnectivityStep->checkServices([
            'redis' => []
        ]);

        $this->assertArrayHasKey('redis', $results);
        $this->assertEquals('warning', $results['redis']['status']);
        $this->assertStringContainsString('not configured', $results['redis']['message']);
    }

    /**
     * Test AI service check with no configuration
     */
    public function testCheckAIServiceWithNoConfig()
    {
        $results = $this->serviceConnectivityStep->checkServices([
            'ai_service' => []
        ]);

        $this->assertArrayHasKey('ai_service', $results);
        $this->assertEquals('warning', $results['ai_service']['status']);
        $this->assertStringContainsString('not configured', $results['ai_service']['message']);
    }

    /**
     * Test validation with valid data
     */
    public function testValidateWithValidData()
    {
        $data = [
            'postgresql' => [
                'enabled' => true,
                'host' => 'localhost',
                'port' => 5432,
            ],
            'redis' => [
                'enabled' => true,
                'host' => 'localhost',
            ],
            'ai_service' => [
                'enabled' => true,
                'url' => 'http://localhost:8000',
            ],
        ];

        $errors = $this->serviceConnectivityStep->validate($data);

        $this->assertEmpty($errors, 'Valid service configuration should have no validation errors');
    }

    /**
     * Test validation with missing PostgreSQL host
     */
    public function testValidateWithMissingPostgreSQLHost()
    {
        $data = [
            'postgresql' => [
                'enabled' => true,
            ],
        ];

        $errors = $this->serviceConnectivityStep->validate($data);

        $this->assertArrayHasKey('postgresql_host', $errors);
        $this->assertStringContainsString('required', $errors['postgresql_host']);
    }

    /**
     * Test validation with missing Redis host
     */
    public function testValidateWithMissingRedisHost()
    {
        $data = [
            'redis' => [
                'enabled' => true,
            ],
        ];

        $errors = $this->serviceConnectivityStep->validate($data);

        $this->assertArrayHasKey('redis_host', $errors);
        $this->assertStringContainsString('required', $errors['redis_host']);
    }

    /**
     * Test validation with missing AI service URL
     */
    public function testValidateWithMissingAIServiceURL()
    {
        $data = [
            'ai_service' => [
                'enabled' => true,
            ],
        ];

        $errors = $this->serviceConnectivityStep->validate($data);

        $this->assertArrayHasKey('ai_service_url', $errors);
        $this->assertStringContainsString('required', $errors['ai_service_url']);
    }

    /**
     * Test validation with invalid AI service URL
     */
    public function testValidateWithInvalidAIServiceURL()
    {
        $data = [
            'ai_service' => [
                'enabled' => true,
                'url' => 'not-a-valid-url',
            ],
        ];

        $errors = $this->serviceConnectivityStep->validate($data);

        $this->assertArrayHasKey('ai_service_url', $errors);
        $this->assertStringContainsString('valid URL', $errors['ai_service_url']);
    }

    /**
     * Test validation with disabled services (no errors expected)
     */
    public function testValidateWithDisabledServices()
    {
        $data = [
            'postgresql' => [
                'enabled' => false,
            ],
            'redis' => [
                'enabled' => false,
            ],
            'ai_service' => [
                'enabled' => false,
            ],
        ];

        $errors = $this->serviceConnectivityStep->validate($data);

        $this->assertEmpty($errors, 'Disabled services should not require configuration');
    }

    /**
     * Test saving service check results
     */
    public function testSaveCheckResults()
    {
        $results = [
            'mysql' => [
                'status' => 'ok',
                'message' => 'MySQL connection successful',
                'version' => '8.0.25',
            ],
            'postgresql' => [
                'status' => 'warning',
                'message' => 'PostgreSQL not configured',
                'version' => null,
            ],
            'redis' => [
                'status' => 'warning',
                'message' => 'Redis not configured',
                'version' => null,
            ],
            'ai_service' => [
                'status' => 'ok',
                'message' => 'AI service connection successful',
                'version' => '1.0.0',
            ],
        ];

        // Mock installation detector
        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress')
            ->with('service_connectivity', $this->anything());

        $success = $this->serviceConnectivityStep->save($results);

        $this->assertTrue($success, 'Saving service check results should succeed');

        // Verify settings were saved
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM gibbonSetting
            WHERE scope = 'System' AND name IN ('serviceConnectivityCheck', 'serviceConnectivityCheckTime')
        ");
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();

        $this->assertEquals(2, $count, 'Should save 2 settings (results and timestamp)');
    }

    /**
     * Test retrieving saved check results
     */
    public function testGetCheckResults()
    {
        $results = [
            'mysql' => [
                'status' => 'ok',
                'message' => 'MySQL connection successful',
                'version' => '8.0.25',
            ],
        ];

        // Save results
        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');
        $this->serviceConnectivityStep->save($results);

        // Retrieve results
        $retrieved = $this->serviceConnectivityStep->getCheckResults();

        $this->assertNotNull($retrieved);
        $this->assertArrayHasKey('mysql', $retrieved);
        $this->assertEquals('ok', $retrieved['mysql']['status']);
    }

    /**
     * Test isCompleted returns false when no checks have been performed
     */
    public function testIsCompletedReturnsFalseWhenNoChecks()
    {
        $completed = $this->serviceConnectivityStep->isCompleted();

        $this->assertFalse($completed, 'Should not be completed when no checks have been performed');
    }

    /**
     * Test isCompleted returns true after checks are saved
     */
    public function testIsCompletedReturnsTrueAfterSave()
    {
        $results = [
            'mysql' => ['status' => 'ok', 'message' => 'OK', 'version' => '8.0'],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $this->serviceConnectivityStep->save($results);
        $completed = $this->serviceConnectivityStep->isCompleted();

        $this->assertTrue($completed, 'Should be completed after saving check results');
    }

    /**
     * Test prepareData returns empty array when no data exists
     */
    public function testPrepareDataReturnsEmptyWhenNoData()
    {
        $this->installationDetector->expects($this->once())
            ->method('getWizardProgress')
            ->willReturn(null);

        $data = $this->serviceConnectivityStep->prepareData();

        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    /**
     * Test prepareData returns saved results
     */
    public function testPrepareDataReturnsSavedResults()
    {
        $results = [
            'mysql' => ['status' => 'ok', 'message' => 'OK', 'version' => '8.0'],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $this->serviceConnectivityStep->save($results);

        $this->installationDetector->expects($this->once())
            ->method('getWizardProgress')
            ->willReturn(null);

        $data = $this->serviceConnectivityStep->prepareData();

        $this->assertArrayHasKey('mysql', $data);
    }

    /**
     * Test prepareData prefers wizard progress over saved data (for resume)
     */
    public function testPrepareDataPrefersWizardProgress()
    {
        $savedResults = [
            'mysql' => ['status' => 'ok', 'message' => 'Old', 'version' => '8.0'],
        ];

        $wizardResults = [
            'mysql' => ['status' => 'ok', 'message' => 'New', 'version' => '8.1'],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $this->serviceConnectivityStep->save($savedResults);

        $this->installationDetector->expects($this->once())
            ->method('getWizardProgress')
            ->willReturn(['stepData' => ['results' => $wizardResults]]);

        $data = $this->serviceConnectivityStep->prepareData();

        $this->assertEquals('New', $data['mysql']['message']);
    }

    /**
     * Test overall status with all OK
     */
    public function testGetOverallStatusAllOK()
    {
        $results = [
            'mysql' => ['status' => 'ok', 'message' => 'OK', 'version' => '8.0'],
            'postgresql' => ['status' => 'ok', 'message' => 'OK', 'version' => '13.0'],
        ];

        $status = $this->serviceConnectivityStep->getOverallStatus($results);

        $this->assertEquals('ok', $status);
    }

    /**
     * Test overall status with warnings
     */
    public function testGetOverallStatusWithWarnings()
    {
        $results = [
            'mysql' => ['status' => 'ok', 'message' => 'OK', 'version' => '8.0'],
            'postgresql' => ['status' => 'warning', 'message' => 'Not configured', 'version' => null],
        ];

        $status = $this->serviceConnectivityStep->getOverallStatus($results);

        $this->assertEquals('warning', $status);
    }

    /**
     * Test overall status with MySQL error (critical)
     */
    public function testGetOverallStatusWithMySQLError()
    {
        $results = [
            'mysql' => ['status' => 'error', 'message' => 'Connection failed', 'version' => null],
            'postgresql' => ['status' => 'ok', 'message' => 'OK', 'version' => '13.0'],
        ];

        $status = $this->serviceConnectivityStep->getOverallStatus($results);

        $this->assertEquals('error', $status);
    }

    /**
     * Test overall status with non-MySQL error
     */
    public function testGetOverallStatusWithNonMySQLError()
    {
        $results = [
            'mysql' => ['status' => 'ok', 'message' => 'OK', 'version' => '8.0'],
            'postgresql' => ['status' => 'error', 'message' => 'Connection failed', 'version' => null],
        ];

        $status = $this->serviceConnectivityStep->getOverallStatus($results);

        $this->assertEquals('error', $status);
    }

    /**
     * Test clear functionality
     */
    public function testClear()
    {
        $results = [
            'mysql' => ['status' => 'ok', 'message' => 'OK', 'version' => '8.0'],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $this->serviceConnectivityStep->save($results);

        $this->assertTrue($this->serviceConnectivityStep->isCompleted());

        $success = $this->serviceConnectivityStep->clear();

        $this->assertTrue($success);
        $this->assertFalse($this->serviceConnectivityStep->isCompleted());
    }

    /**
     * Test that check results are properly JSON encoded and decoded
     */
    public function testCheckResultsJSONEncodingDecoding()
    {
        $results = [
            'mysql' => [
                'status' => 'ok',
                'message' => 'MySQL connection successful',
                'version' => '8.0.25',
            ],
            'ai_service' => [
                'status' => 'ok',
                'message' => 'AI service connection successful',
                'version' => '1.0.0',
            ],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $this->serviceConnectivityStep->save($results);
        $retrieved = $this->serviceConnectivityStep->getCheckResults();

        $this->assertEquals($results, $retrieved, 'Retrieved results should match saved results');
    }

    /**
     * Test getWizardProgress returns null when no progress saved
     */
    public function testGetWizardProgressReturnsNullWhenNoProgress()
    {
        $this->installationDetector->expects($this->once())
            ->method('getWizardProgress')
            ->willReturn(null);

        $progress = $this->serviceConnectivityStep->getWizardProgress();

        $this->assertNull($progress);
    }

    /**
     * Test getWizardProgress returns stepData when available
     */
    public function testGetWizardProgressReturnsStepData()
    {
        $stepData = ['results' => ['mysql' => ['status' => 'ok']]];

        $this->installationDetector->expects($this->once())
            ->method('getWizardProgress')
            ->willReturn(['stepData' => $stepData]);

        $progress = $this->serviceConnectivityStep->getWizardProgress();

        $this->assertEquals($stepData, $progress);
    }

    /**
     * Test save handles transaction rollback on error
     */
    public function testSaveHandlesTransactionRollback()
    {
        // Create a PDO mock that throws an exception during commit
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())
            ->method('beginTransaction')
            ->willThrowException(new PDOException('Database error'));

        $step = new ServiceConnectivityStep(
            $this->settingGateway,
            $pdoMock,
            $this->installationDetector
        );

        $results = [
            'mysql' => ['status' => 'ok', 'message' => 'OK', 'version' => '8.0'],
        ];

        $success = $step->save($results);

        $this->assertFalse($success, 'Save should return false on database error');
    }

    /**
     * Test getCheckResults returns null when database query fails
     */
    public function testGetCheckResultsReturnsNullOnDatabaseError()
    {
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Database error'));

        $step = new ServiceConnectivityStep(
            $this->settingGateway,
            $pdoMock,
            $this->installationDetector
        );

        $result = $step->getCheckResults();

        $this->assertNull($result);
    }

    /**
     * Test isCompleted returns false on database error
     */
    public function testIsCompletedReturnsFalseOnDatabaseError()
    {
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())
            ->method('prepare')
            ->willThrowException(new PDOException('Database error'));

        $step = new ServiceConnectivityStep(
            $this->settingGateway,
            $pdoMock,
            $this->installationDetector
        );

        $result = $step->isCompleted();

        $this->assertFalse($result);
    }

    /**
     * Test clear handles database error gracefully
     */
    public function testClearHandlesDatabaseError()
    {
        $pdoMock = $this->createMock(PDO::class);
        $pdoMock->expects($this->once())
            ->method('beginTransaction')
            ->willThrowException(new PDOException('Database error'));

        $step = new ServiceConnectivityStep(
            $this->settingGateway,
            $pdoMock,
            $this->installationDetector
        );

        $result = $step->clear();

        $this->assertFalse($result);
    }

    /**
     * Test that timestamp is saved along with results
     */
    public function testSaveSavesTimestamp()
    {
        $results = [
            'mysql' => ['status' => 'ok', 'message' => 'OK', 'version' => '8.0'],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $this->serviceConnectivityStep->save($results);

        // Check that timestamp was saved
        $stmt = $this->pdo->prepare("
            SELECT value FROM gibbonSetting
            WHERE scope = 'System' AND name = 'serviceConnectivityCheckTime'
        ");
        $stmt->execute();
        $timestamp = $stmt->fetchColumn();

        $this->assertNotEmpty($timestamp);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $timestamp);
    }
}
