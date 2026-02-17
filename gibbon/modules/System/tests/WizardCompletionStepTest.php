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

namespace Gibbon\Module\System\Tests;

use PHPUnit\Framework\TestCase;
use Gibbon\Module\System\Domain\WizardCompletionStep;
use Gibbon\Module\System\Domain\InstallationDetector;
use Gibbon\Domain\System\SettingGateway;

/**
 * Test WizardCompletionStep class
 */
class WizardCompletionStepTest extends TestCase
{
    protected $step;
    protected $settingGateway;
    protected $pdo;
    protected $installationDetector;

    protected function setUp(): void
    {
        // Create mock SettingGateway
        $this->settingGateway = $this->createMock(SettingGateway::class);

        // Create mock PDO
        $this->pdo = $this->createMock(\PDO::class);

        // Create mock InstallationDetector
        $this->installationDetector = $this->createMock(InstallationDetector::class);

        // Create WizardCompletionStep instance
        $this->step = new WizardCompletionStep(
            $this->settingGateway,
            $this->pdo,
            $this->installationDetector
        );
    }

    /**
     * Test validate() with wizard already completed
     */
    public function testValidateWhenWizardAlreadyCompleted()
    {
        // Mock wizard already completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(true);

        $errors = $this->step->validate([]);

        $this->assertArrayHasKey('wizard_completed', $errors);
        $this->assertStringContainsString('already been completed', $errors['wizard_completed']);
    }

    /**
     * Test validate() with no wizard progress
     */
    public function testValidateWithNoWizardProgress()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock no wizard progress
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn(null);

        $errors = $this->step->validate([]);

        $this->assertArrayHasKey('no_progress', $errors);
        $this->assertStringContainsString('No wizard progress found', $errors['no_progress']);
    }

    /**
     * Test validate() with incomplete steps
     */
    public function testValidateWithIncompleteSteps()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock wizard progress with only some steps completed
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => [
                    'organization_info' => ['name' => 'Test Daycare'],
                    'admin_account' => ['email' => 'admin@test.com'],
                    // Missing: operating_hours, groups_rooms, finance_settings, service_connectivity
                ],
            ]);

        $errors = $this->step->validate([]);

        $this->assertArrayHasKey('incomplete_steps', $errors);
        $this->assertStringContainsString('Operating Hours', $errors['incomplete_steps']);
        $this->assertStringContainsString('Groups Rooms', $errors['incomplete_steps']);
        $this->assertStringContainsString('Finance Settings', $errors['incomplete_steps']);
        $this->assertStringContainsString('Service Connectivity', $errors['incomplete_steps']);
    }

    /**
     * Test validate() with all steps completed
     */
    public function testValidateWithAllStepsCompleted()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock wizard progress with all required steps completed
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => [
                    'organization_info' => ['name' => 'Test Daycare'],
                    'admin_account' => ['email' => 'admin@test.com'],
                    'operating_hours' => ['monday_open' => '08:00'],
                    'groups_rooms' => ['groups' => []],
                    'finance_settings' => ['currency' => 'USD'],
                    'service_connectivity' => ['mysql' => 'ok'],
                ],
            ]);

        $errors = $this->step->validate([]);

        $this->assertEmpty($errors);
    }

    /**
     * Test validate() with empty step data
     */
    public function testValidateWithEmptyStepData()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock wizard progress with empty stepData
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => [],
            ]);

        $errors = $this->step->validate([]);

        $this->assertArrayHasKey('incomplete_steps', $errors);
    }

    /**
     * Test save() success
     */
    public function testSaveSuccess()
    {
        // Mock PDO transaction methods
        $this->pdo
            ->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $this->pdo
            ->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        // Mock InstallationDetector::markWizardCompleted()
        $this->installationDetector
            ->expects($this->once())
            ->method('markWizardCompleted')
            ->willReturn(true);

        // Mock InstallationDetector::saveWizardProgress()
        $this->installationDetector
            ->expects($this->once())
            ->method('saveWizardProgress')
            ->with('completed', $this->anything())
            ->willReturn(true);

        // Mock PDO prepare/execute for settings
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(false); // Setting doesn't exist yet

        $this->pdo
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->step->save([]);

        $this->assertTrue($result);
    }

    /**
     * Test save() with completion data
     */
    public function testSaveWithCompletionData()
    {
        // Mock PDO transaction methods
        $this->pdo
            ->method('beginTransaction')
            ->willReturn(true);

        $this->pdo
            ->method('commit')
            ->willReturn(true);

        // Mock InstallationDetector methods
        $this->installationDetector
            ->method('markWizardCompleted')
            ->willReturn(true);

        $this->installationDetector
            ->method('saveWizardProgress')
            ->willReturn(true);

        // Mock PDO prepare/execute for settings
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(false);

        $this->pdo
            ->method('prepare')
            ->willReturn($stmt);

        $completionData = [
            'notes' => 'Initial setup completed',
            'version' => '1.0.0',
        ];

        $result = $this->step->save($completionData);

        $this->assertTrue($result);
    }

    /**
     * Test save() failure when markWizardCompleted fails
     */
    public function testSaveFailureWhenMarkCompletedFails()
    {
        // Mock PDO transaction methods
        $this->pdo
            ->method('beginTransaction')
            ->willReturn(true);

        $this->pdo
            ->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);

        // Mock InstallationDetector::markWizardCompleted() to fail
        $this->installationDetector
            ->method('markWizardCompleted')
            ->willReturn(false);

        $result = $this->step->save([]);

        $this->assertFalse($result);
    }

    /**
     * Test save() failure on database error
     */
    public function testSaveFailureOnDatabaseError()
    {
        // Mock PDO to throw exception
        $this->pdo
            ->method('beginTransaction')
            ->willThrowException(new \PDOException('Database error'));

        $result = $this->step->save([]);

        $this->assertFalse($result);
    }

    /**
     * Test isCompleted() returns true when wizard is completed
     */
    public function testIsCompletedReturnsTrue()
    {
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(true);

        $result = $this->step->isCompleted();

        $this->assertTrue($result);
    }

    /**
     * Test isCompleted() returns false when wizard is not completed
     */
    public function testIsCompletedReturnsFalse()
    {
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        $result = $this->step->isCompleted();

        $this->assertFalse($result);
    }

    /**
     * Test prepareData() with wizard not completed
     */
    public function testPrepareDataWhenNotCompleted()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock wizard progress with some steps completed
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => [
                    'organization_info' => ['name' => 'Test'],
                    'admin_account' => ['email' => 'admin@test.com'],
                ],
            ]);

        // Mock PDO prepare/execute
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn(false);

        $this->pdo
            ->method('prepare')
            ->willReturn($stmt);

        $data = $this->step->prepareData();

        $this->assertFalse($data['is_completed']);
        $this->assertArrayHasKey('completed_steps', $data);
        $this->assertArrayHasKey('pending_steps', $data);
        $this->assertContains('organization_info', $data['completed_steps']);
        $this->assertContains('admin_account', $data['completed_steps']);
        $this->assertNotEmpty($data['pending_steps']);
    }

    /**
     * Test prepareData() with wizard completed
     */
    public function testPrepareDataWhenCompleted()
    {
        // Mock wizard completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(true);

        // Mock wizard progress with all steps completed
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => [
                    'organization_info' => ['name' => 'Test'],
                    'admin_account' => ['email' => 'admin@test.com'],
                    'operating_hours' => ['monday_open' => '08:00'],
                    'groups_rooms' => ['groups' => []],
                    'finance_settings' => ['currency' => 'USD'],
                    'service_connectivity' => ['mysql' => 'ok'],
                ],
            ]);

        // Mock PDO prepare/execute to return completion date
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturnOnConsecutiveCalls(
            '2026-02-16 10:30:00', // setupWizardCompletedDate
            null // setupWizardCompletionData
        );

        $this->pdo
            ->method('prepare')
            ->willReturn($stmt);

        $data = $this->step->prepareData();

        $this->assertTrue($data['is_completed']);
        $this->assertEquals('2026-02-16 10:30:00', $data['completed_at']);
        $this->assertCount(6, $data['completed_steps']);
        $this->assertEmpty($data['pending_steps']);
    }

    /**
     * Test prepareData() with completion data
     */
    public function testPrepareDataWithCompletionData()
    {
        // Mock wizard completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(true);

        // Mock wizard progress
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => [
                    'organization_info' => ['name' => 'Test'],
                    'admin_account' => ['email' => 'admin@test.com'],
                    'operating_hours' => ['monday_open' => '08:00'],
                    'groups_rooms' => ['groups' => []],
                    'finance_settings' => ['currency' => 'USD'],
                    'service_connectivity' => ['mysql' => 'ok'],
                ],
            ]);

        // Mock PDO prepare/execute to return completion data
        $completionDataJson = json_encode([
            'completed_at' => 1234567890,
            'data' => ['notes' => 'Setup completed successfully'],
        ]);

        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturnOnConsecutiveCalls(
            '2026-02-16 10:30:00', // setupWizardCompletedDate
            $completionDataJson    // setupWizardCompletionData
        );

        $this->pdo
            ->method('prepare')
            ->willReturn($stmt);

        $data = $this->step->prepareData();

        $this->assertArrayHasKey('completion_data', $data);
        $this->assertEquals(1234567890, $data['completion_data']['completed_at']);
    }

    /**
     * Test getRequiredSteps() returns correct steps
     */
    public function testGetRequiredSteps()
    {
        $steps = $this->step->getRequiredSteps();

        $this->assertIsArray($steps);
        $this->assertContains('organization_info', $steps);
        $this->assertContains('admin_account', $steps);
        $this->assertContains('operating_hours', $steps);
        $this->assertContains('groups_rooms', $steps);
        $this->assertContains('finance_settings', $steps);
        $this->assertContains('service_connectivity', $steps);
    }

    /**
     * Test canComplete() returns true when all steps done
     */
    public function testCanCompleteReturnsTrueWhenAllStepsDone()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock wizard progress with all steps completed
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => [
                    'organization_info' => ['name' => 'Test'],
                    'admin_account' => ['email' => 'admin@test.com'],
                    'operating_hours' => ['monday_open' => '08:00'],
                    'groups_rooms' => ['groups' => []],
                    'finance_settings' => ['currency' => 'USD'],
                    'service_connectivity' => ['mysql' => 'ok'],
                ],
            ]);

        $result = $this->step->canComplete();

        $this->assertTrue($result);
    }

    /**
     * Test canComplete() returns false when wizard already completed
     */
    public function testCanCompleteReturnsFalseWhenAlreadyCompleted()
    {
        // Mock wizard already completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(true);

        $result = $this->step->canComplete();

        $this->assertFalse($result);
    }

    /**
     * Test canComplete() returns false when steps incomplete
     */
    public function testCanCompleteReturnsFalseWhenStepsIncomplete()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock wizard progress with only some steps
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => [
                    'organization_info' => ['name' => 'Test'],
                    'admin_account' => ['email' => 'admin@test.com'],
                ],
            ]);

        $result = $this->step->canComplete();

        $this->assertFalse($result);
    }

    /**
     * Test clear() success
     */
    public function testClearSuccess()
    {
        // Mock PDO transaction methods
        $this->pdo
            ->expects($this->once())
            ->method('beginTransaction')
            ->willReturn(true);

        $this->pdo
            ->expects($this->once())
            ->method('commit')
            ->willReturn(true);

        // Mock InstallationDetector::resetWizard()
        $this->installationDetector
            ->expects($this->once())
            ->method('resetWizard')
            ->willReturn(true);

        // Mock PDO prepare/execute for deleting settings
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->step->clear();

        $this->assertTrue($result);
    }

    /**
     * Test clear() failure when resetWizard fails
     */
    public function testClearFailureWhenResetWizardFails()
    {
        // Mock PDO transaction methods
        $this->pdo
            ->method('beginTransaction')
            ->willReturn(true);

        $this->pdo
            ->expects($this->once())
            ->method('rollBack')
            ->willReturn(true);

        // Mock InstallationDetector::resetWizard() to fail
        $this->installationDetector
            ->method('resetWizard')
            ->willReturn(false);

        $result = $this->step->clear();

        $this->assertFalse($result);
    }

    /**
     * Test clear() failure on database error
     */
    public function testClearFailureOnDatabaseError()
    {
        // Mock PDO to throw exception
        $this->pdo
            ->method('beginTransaction')
            ->willThrowException(new \PDOException('Database error'));

        $result = $this->step->clear();

        $this->assertFalse($result);
    }

    /**
     * Test validate() handles missing stepData array
     */
    public function testValidateHandlesMissingStepDataArray()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock wizard progress with stepData not an array
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => 'invalid',
            ]);

        $errors = $this->step->validate([]);

        $this->assertArrayHasKey('incomplete_steps', $errors);
    }

    /**
     * Test validate() handles stepData with null values
     */
    public function testValidateHandlesStepDataWithNullValues()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock wizard progress with null step values
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => [
                    'organization_info' => null,
                    'admin_account' => null,
                ],
            ]);

        $errors = $this->step->validate([]);

        $this->assertArrayHasKey('incomplete_steps', $errors);
    }

    /**
     * Test validate() handles stepData with empty arrays
     */
    public function testValidateHandlesStepDataWithEmptyArrays()
    {
        // Mock wizard not completed
        $this->installationDetector
            ->method('isWizardCompleted')
            ->willReturn(false);

        // Mock wizard progress with empty array step values (should be treated as incomplete)
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn([
                'stepData' => [
                    'organization_info' => [],
                    'admin_account' => [],
                ],
            ]);

        $errors = $this->step->validate([]);

        $this->assertArrayHasKey('incomplete_steps', $errors);
    }
}
