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
use Gibbon\Module\System\Domain\InstallationDetector;
use Gibbon\Domain\System\SettingGateway;
use PDO;
use PDOStatement;

/**
 * Unit tests for InstallationDetector.
 *
 * These tests verify that the InstallationDetector correctly identifies
 * fresh installations, manages wizard state, and tracks setup progress.
 *
 * @covers \Gibbon\Module\System\Domain\InstallationDetector
 */
class InstallationDetectorTest extends TestCase
{
    private $settingGateway;
    private $pdo;
    private $detector;

    protected function setUp(): void
    {
        // Create mock objects
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->pdo = $this->createMock(PDO::class);

        $this->detector = new InstallationDetector(
            $this->settingGateway,
            $this->pdo
        );
    }

    // =========================================================================
    // FRESH INSTALLATION DETECTION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function isFreshInstallationReturnsTrueWhenFreshInstallFlagIsY(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->with('System', 'freshInstallation')
            ->willReturn('Y');

        $this->assertTrue($this->detector->isFreshInstallation());
    }

    /**
     * @test
     */
    public function isFreshInstallationReturnsFalseWhenWizardCompleted(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['System', 'freshInstallation', 'N'],
                ['System', 'setupWizardCompleted', 'Y'],
            ]);

        $this->assertFalse($this->detector->isFreshInstallation());
    }

    /**
     * @test
     */
    public function isFreshInstallationReturnsFalseWhenOrganizationDataExists(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['System', 'freshInstallation', 'N'],
                ['System', 'setupWizardCompleted', 'N'],
            ]);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(1);

        $this->pdo
            ->method('query')
            ->with('SELECT COUNT(*) FROM gibbonSchool')
            ->willReturn($stmt);

        $this->assertFalse($this->detector->isFreshInstallation());
    }

    /**
     * @test
     */
    public function isFreshInstallationReturnsFalseWhenAdminUsersExist(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['System', 'freshInstallation', 'N'],
                ['System', 'setupWizardCompleted', 'N'],
            ]);

        // Mock organization data check (no data)
        $orgStmt = $this->createMock(PDOStatement::class);
        $orgStmt->method('fetchColumn')->willReturn(0);

        // Mock admin users check (users exist)
        $adminStmt = $this->createMock(PDOStatement::class);
        $adminStmt->method('fetchColumn')->willReturn(1);

        $this->pdo
            ->method('query')
            ->willReturnOnConsecutiveCalls($orgStmt, $adminStmt);

        $this->assertFalse($this->detector->isFreshInstallation());
    }

    /**
     * @test
     */
    public function isFreshInstallationReturnsTrueWhenNoDataAndNoAdminUsers(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['System', 'freshInstallation', 'N'],
                ['System', 'setupWizardCompleted', 'N'],
            ]);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);

        $this->pdo
            ->method('query')
            ->willReturn($stmt);

        $this->assertTrue($this->detector->isFreshInstallation());
    }

    // =========================================================================
    // WIZARD COMPLETION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function isWizardCompletedReturnsTrueWhenSettingIsY(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->with('System', 'setupWizardCompleted')
            ->willReturn('Y');

        $this->assertTrue($this->detector->isWizardCompleted());
    }

    /**
     * @test
     */
    public function isWizardCompletedReturnsFalseWhenSettingIsN(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->with('System', 'setupWizardCompleted')
            ->willReturn('N');

        $this->assertFalse($this->detector->isWizardCompleted());
    }

    /**
     * @test
     */
    public function isWizardEnabledReturnsTrueWhenSettingIsY(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->with('System', 'setupWizardEnabled')
            ->willReturn('Y');

        $this->assertTrue($this->detector->isWizardEnabled());
    }

    /**
     * @test
     */
    public function isWizardEnabledReturnsFalseWhenSettingIsN(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->with('System', 'setupWizardEnabled')
            ->willReturn('N');

        $this->assertFalse($this->detector->isWizardEnabled());
    }

    // =========================================================================
    // WIZARD STATE MANAGEMENT TESTS
    // =========================================================================

    /**
     * @test
     */
    public function markWizardCompletedUpdatesSettingsAndWizardRecord(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->detector->markWizardCompleted();

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function resetWizardUpdatesSettings(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->detector->resetWizard();

        $this->assertTrue($result);
    }

    // =========================================================================
    // WIZARD PROGRESS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getWizardProgressReturnsNullWhenNoRecord(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo
            ->method('query')
            ->willReturn($stmt);

        $result = $this->detector->getWizardProgress();

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function getWizardProgressReturnsDataWithDecodedStepData(): void
    {
        $wizardData = [
            'gibbonSetupWizardID' => 1,
            'stepCompleted' => 'organization',
            'stepData' => '{"name":"Test Daycare","address":"123 Main St"}',
            'wizardCompleted' => 'N',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($wizardData);

        $this->pdo
            ->method('query')
            ->willReturn($stmt);

        $result = $this->detector->getWizardProgress();

        $this->assertIsArray($result);
        $this->assertEquals(1, $result['gibbonSetupWizardID']);
        $this->assertEquals('organization', $result['stepCompleted']);
        $this->assertIsArray($result['stepData']);
        $this->assertEquals('Test Daycare', $result['stepData']['name']);
    }

    /**
     * @test
     */
    public function saveWizardProgressCreatesNewRecordWhenNoneExists(): void
    {
        // Mock getWizardProgress to return null
        $queryStmt = $this->createMock(PDOStatement::class);
        $queryStmt->method('fetch')->willReturn(false);

        // Mock prepare for INSERT
        $prepareStmt = $this->createMock(PDOStatement::class);
        $prepareStmt->method('execute')->willReturn(true);

        $this->pdo
            ->method('query')
            ->willReturn($queryStmt);

        $this->pdo
            ->method('prepare')
            ->willReturn($prepareStmt);

        $result = $this->detector->saveWizardProgress('organization', ['name' => 'Test']);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function saveWizardProgressUpdatesExistingRecord(): void
    {
        // Mock getWizardProgress to return existing record
        $existingData = [
            'gibbonSetupWizardID' => 1,
            'stepCompleted' => 'organization',
            'stepData' => '{}',
            'wizardCompleted' => 'N',
        ];

        $queryStmt = $this->createMock(PDOStatement::class);
        $queryStmt->method('fetch')->willReturn($existingData);

        // Mock prepare for UPDATE
        $prepareStmt = $this->createMock(PDOStatement::class);
        $prepareStmt->method('execute')->willReturn(true);

        $this->pdo
            ->method('query')
            ->willReturn($queryStmt);

        $this->pdo
            ->method('prepare')
            ->willReturn($prepareStmt);

        $result = $this->detector->saveWizardProgress('admin', ['email' => 'admin@test.com']);

        $this->assertTrue($result);
    }

    // =========================================================================
    // INSTALLATION STATUS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getInstallationStatusReturnsCompleteStatusInfo(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['System', 'freshInstallation', 'Y'],
                ['System', 'setupWizardCompleted', 'N'],
                ['System', 'setupWizardEnabled', 'Y'],
            ]);

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(0);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo
            ->method('query')
            ->willReturn($stmt);

        $status = $this->detector->getInstallationStatus();

        $this->assertIsArray($status);
        $this->assertArrayHasKey('isFresh', $status);
        $this->assertArrayHasKey('wizardCompleted', $status);
        $this->assertArrayHasKey('wizardEnabled', $status);
        $this->assertArrayHasKey('hasOrganization', $status);
        $this->assertArrayHasKey('hasAdminUsers', $status);
        $this->assertArrayHasKey('wizardProgress', $status);
    }

    // =========================================================================
    // SHOULD SHOW WIZARD TESTS
    // =========================================================================

    /**
     * @test
     */
    public function shouldShowWizardReturnsTrueForFreshInstallWithEnabledWizard(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['System', 'freshInstallation', 'Y'],
                ['System', 'setupWizardCompleted', 'N'],
                ['System', 'setupWizardEnabled', 'Y'],
            ]);

        $this->assertTrue($this->detector->shouldShowWizard());
    }

    /**
     * @test
     */
    public function shouldShowWizardReturnsFalseWhenWizardDisabled(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['System', 'freshInstallation', 'Y'],
                ['System', 'setupWizardCompleted', 'N'],
                ['System', 'setupWizardEnabled', 'N'],
            ]);

        $this->assertFalse($this->detector->shouldShowWizard());
    }

    /**
     * @test
     */
    public function shouldShowWizardReturnsFalseWhenWizardCompleted(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['System', 'freshInstallation', 'Y'],
                ['System', 'setupWizardCompleted', 'Y'],
                ['System', 'setupWizardEnabled', 'Y'],
            ]);

        $this->assertFalse($this->detector->shouldShowWizard());
    }

    /**
     * @test
     */
    public function shouldShowWizardReturnsFalseWhenNotFreshInstall(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['System', 'freshInstallation', 'N'],
                ['System', 'setupWizardCompleted', 'N'],
                ['System', 'setupWizardEnabled', 'Y'],
            ]);

        // Mock organization exists
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetchColumn')->willReturn(1);

        $this->pdo
            ->method('query')
            ->willReturn($stmt);

        $this->assertFalse($this->detector->shouldShowWizard());
    }

    // =========================================================================
    // ERROR HANDLING TESTS
    // =========================================================================

    /**
     * @test
     */
    public function hasOrganizationDataReturnsFalseOnDatabaseException(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['System', 'freshInstallation', 'N'],
                ['System', 'setupWizardCompleted', 'N'],
            ]);

        $this->pdo
            ->method('query')
            ->will($this->throwException(new \PDOException('Table does not exist')));

        // Should return true for fresh install when table doesn't exist
        $this->assertTrue($this->detector->isFreshInstallation());
    }

    /**
     * @test
     */
    public function getWizardProgressReturnsNullOnDatabaseException(): void
    {
        $this->pdo
            ->method('query')
            ->will($this->throwException(new \PDOException('Database error')));

        $result = $this->detector->getWizardProgress();

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function markWizardCompletedReturnsFalseOnDatabaseException(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->will($this->throwException(new \PDOException('Database error')));

        $this->pdo
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->detector->markWizardCompleted();

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function resetWizardReturnsFalseOnDatabaseException(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->will($this->throwException(new \PDOException('Database error')));

        $this->pdo
            ->method('prepare')
            ->willReturn($stmt);

        $result = $this->detector->resetWizard();

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function saveWizardProgressReturnsFalseOnDatabaseException(): void
    {
        $queryStmt = $this->createMock(PDOStatement::class);
        $queryStmt->method('fetch')->willReturn(false);

        $prepareStmt = $this->createMock(PDOStatement::class);
        $prepareStmt->method('execute')
            ->will($this->throwException(new \PDOException('Database error')));

        $this->pdo
            ->method('query')
            ->willReturn($queryStmt);

        $this->pdo
            ->method('prepare')
            ->willReturn($prepareStmt);

        $result = $this->detector->saveWizardProgress('organization', ['name' => 'Test']);

        $this->assertFalse($result);
    }
}
