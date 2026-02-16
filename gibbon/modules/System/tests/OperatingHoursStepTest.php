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
use Gibbon\Module\System\Domain\OperatingHoursStep;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\System\Domain\InstallationDetector;

/**
 * OperatingHoursStepTest
 *
 * Unit tests for the OperatingHoursStep class.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class OperatingHoursStepTest extends TestCase
{
    protected $pdo;
    protected $settingGateway;
    protected $installationDetector;
    protected $operatingHoursStep;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create necessary tables
        $this->createTables();

        // Create mock SettingGateway
        $this->settingGateway = $this->createMock(SettingGateway::class);

        // Create mock InstallationDetector
        $this->installationDetector = $this->createMock(InstallationDetector::class);

        // Create OperatingHoursStep instance
        $this->operatingHoursStep = new OperatingHoursStep(
            $this->settingGateway,
            $this->pdo,
            $this->installationDetector
        );
    }

    protected function createTables()
    {
        // Create gibbonSetting table
        $this->pdo->exec("
            CREATE TABLE gibbonSetting (
                gibbonSettingID INTEGER PRIMARY KEY AUTOINCREMENT,
                scope VARCHAR(50) NOT NULL,
                name VARCHAR(50) NOT NULL,
                nameDisplay VARCHAR(60) NOT NULL,
                description TEXT NOT NULL,
                value TEXT
            )
        ");

        // Create gibbonSchoolClosureDate table
        $this->pdo->exec("
            CREATE TABLE gibbonSchoolClosureDate (
                gibbonSchoolClosureDateID INTEGER PRIMARY KEY AUTOINCREMENT,
                date DATE NOT NULL,
                reason VARCHAR(255) NOT NULL
            )
        ");
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
    }

    /**
     * Test validation with valid schedule data
     */
    public function testValidateWithValidSchedule()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'tuesday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'wednesday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'thursday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'friday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'saturday' => ['isOpen' => false, 'openTime' => '', 'closeTime' => ''],
                'sunday' => ['isOpen' => false, 'openTime' => '', 'closeTime' => ''],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertEmpty($errors, 'Valid schedule should have no validation errors');
    }

    /**
     * Test validation with missing schedule
     */
    public function testValidateWithMissingSchedule()
    {
        $data = [];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertArrayHasKey('schedule', $errors);
        $this->assertEquals('Schedule data is required', $errors['schedule']);
    }

    /**
     * Test validation with no open days
     */
    public function testValidateWithNoOpenDays()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => false],
                'tuesday' => ['isOpen' => false],
                'wednesday' => ['isOpen' => false],
                'thursday' => ['isOpen' => false],
                'friday' => ['isOpen' => false],
                'saturday' => ['isOpen' => false],
                'sunday' => ['isOpen' => false],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertArrayHasKey('schedule', $errors);
        $this->assertEquals('At least one day must be marked as open', $errors['schedule']);
    }

    /**
     * Test validation with missing open time
     */
    public function testValidateWithMissingOpenTime()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '', 'closeTime' => '18:00'],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertArrayHasKey('schedule.monday.openTime', $errors);
        $this->assertEquals('Monday open time is required', $errors['schedule.monday.openTime']);
    }

    /**
     * Test validation with missing close time
     */
    public function testValidateWithMissingCloseTime()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => ''],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertArrayHasKey('schedule.monday.closeTime', $errors);
        $this->assertEquals('Monday close time is required', $errors['schedule.monday.closeTime']);
    }

    /**
     * Test validation with invalid time format
     */
    public function testValidateWithInvalidTimeFormat()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '8:00', 'closeTime' => '18:00'],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertArrayHasKey('schedule.monday.openTime', $errors);
        $this->assertEquals('Monday open time must be in HH:MM format', $errors['schedule.monday.openTime']);
    }

    /**
     * Test validation with various invalid time formats
     */
    public function testValidateWithVariousInvalidTimeFormats()
    {
        $invalidTimes = [
            '25:00',    // Invalid hour
            '12:60',    // Invalid minute
            'abc',      // Not a time
            '12:5',     // Missing leading zero
            '1:30',     // Missing leading zero
        ];

        foreach ($invalidTimes as $invalidTime) {
            $data = [
                'schedule' => [
                    'monday' => ['isOpen' => true, 'openTime' => $invalidTime, 'closeTime' => '18:00'],
                ],
            ];

            $errors = $this->operatingHoursStep->validate($data);

            $this->assertArrayHasKey('schedule.monday.openTime', $errors,
                "Time {$invalidTime} should be invalid");
        }
    }

    /**
     * Test validation with valid time formats
     */
    public function testValidateWithValidTimeFormats()
    {
        $validTimes = [
            '00:00',
            '08:00',
            '12:30',
            '18:45',
            '23:59',
        ];

        foreach ($validTimes as $validTime) {
            $data = [
                'schedule' => [
                    'monday' => ['isOpen' => true, 'openTime' => $validTime, 'closeTime' => '23:59'],
                ],
            ];

            $errors = $this->operatingHoursStep->validate($data);

            $this->assertArrayNotHasKey('schedule.monday.openTime', $errors,
                "Time {$validTime} should be valid");
        }
    }

    /**
     * Test validation with open time after close time
     */
    public function testValidateWithOpenTimeAfterCloseTime()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '18:00', 'closeTime' => '08:00'],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertArrayHasKey('schedule.monday.time', $errors);
        $this->assertEquals('Monday open time must be before close time', $errors['schedule.monday.time']);
    }

    /**
     * Test validation with same open and close time
     */
    public function testValidateWithSameOpenAndCloseTime()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '12:00', 'closeTime' => '12:00'],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertArrayHasKey('schedule.monday.time', $errors);
        $this->assertEquals('Monday open time must be before close time', $errors['schedule.monday.time']);
    }

    /**
     * Test validation with valid closure days
     */
    public function testValidateWithValidClosureDays()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
            ],
            'closureDays' => [
                ['date' => '2026-12-25', 'reason' => 'Christmas Day'],
                ['date' => '2026-01-01', 'reason' => 'New Year Day'],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertArrayNotHasKey('closureDays', $errors);
        $this->assertArrayNotHasKey('closureDays.0.date', $errors);
        $this->assertArrayNotHasKey('closureDays.0.reason', $errors);
    }

    /**
     * Test validation with invalid closure day date
     */
    public function testValidateWithInvalidClosureDayDate()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
            ],
            'closureDays' => [
                ['date' => '2026-13-01', 'reason' => 'Invalid month'],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertArrayHasKey('closureDays.0.date', $errors);
        $this->assertEquals('Closure date must be in YYYY-MM-DD format', $errors['closureDays.0.date']);
    }

    /**
     * Test validation with missing closure day date
     */
    public function testValidateWithMissingClosureDayDate()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
            ],
            'closureDays' => [
                ['date' => '', 'reason' => 'Holiday'],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertArrayHasKey('closureDays.0.date', $errors);
        $this->assertEquals('Closure date is required', $errors['closureDays.0.date']);
    }

    /**
     * Test validation with missing closure day reason
     */
    public function testValidateWithMissingClosureDayReason()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
            ],
            'closureDays' => [
                ['date' => '2026-12-25', 'reason' => ''],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertArrayHasKey('closureDays.0.reason', $errors);
        $this->assertEquals('Closure reason is required', $errors['closureDays.0.reason']);
    }

    /**
     * Test validation with too long closure day reason
     */
    public function testValidateWithTooLongClosureDayReason()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
            ],
            'closureDays' => [
                ['date' => '2026-12-25', 'reason' => str_repeat('a', 256)],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertArrayHasKey('closureDays.0.reason', $errors);
        $this->assertEquals('Closure reason must not exceed 255 characters', $errors['closureDays.0.reason']);
    }

    /**
     * Test save with valid data
     */
    public function testSaveWithValidData()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'tuesday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'wednesday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'thursday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'friday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'saturday' => ['isOpen' => false],
                'sunday' => ['isOpen' => false],
            ],
            'closureDays' => [
                ['date' => '2026-12-25', 'reason' => 'Christmas Day'],
            ],
            'timezone' => 'America/New_York',
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress')
            ->with('operating_hours', $data);

        $result = $this->operatingHoursStep->save($data);

        $this->assertTrue($result, 'Save should succeed with valid data');

        // Verify schedule was saved to settings
        $stmt = $this->pdo->query("
            SELECT value FROM gibbonSetting
            WHERE scope = 'Daycare' AND name = 'operatingHoursSchedule'
        ");
        $savedSchedule = $stmt->fetchColumn();
        $this->assertNotEmpty($savedSchedule);
        $this->assertEquals($data['schedule'], json_decode($savedSchedule, true));

        // Verify timezone was saved
        $stmt = $this->pdo->query("
            SELECT value FROM gibbonSetting
            WHERE scope = 'System' AND name = 'timezone'
        ");
        $savedTimezone = $stmt->fetchColumn();
        $this->assertEquals('America/New_York', $savedTimezone);

        // Verify closure days were saved
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonSchoolClosureDate");
        $count = $stmt->fetchColumn();
        $this->assertEquals(1, $count);

        $stmt = $this->pdo->query("SELECT date, reason FROM gibbonSchoolClosureDate");
        $closureDay = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('2026-12-25', $closureDay['date']);
        $this->assertEquals('Christmas Day', $closureDay['reason']);
    }

    /**
     * Test save with invalid data
     */
    public function testSaveWithInvalidData()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '18:00', 'closeTime' => '08:00'], // Invalid
            ],
        ];

        $this->installationDetector->expects($this->never())
            ->method('saveWizardProgress');

        $result = $this->operatingHoursStep->save($data);

        $this->assertFalse($result, 'Save should fail with invalid data');
    }

    /**
     * Test save updates existing settings
     */
    public function testSaveUpdatesExistingSettings()
    {
        // Insert initial setting
        $this->pdo->exec("
            INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
            VALUES ('Daycare', 'operatingHoursSchedule', 'Operating Hours Schedule', 'Schedule', '{}')
        ");

        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '09:00', 'closeTime' => '17:00'],
            ],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $result = $this->operatingHoursStep->save($data);

        $this->assertTrue($result);

        // Verify setting was updated
        $stmt = $this->pdo->query("
            SELECT value FROM gibbonSetting
            WHERE scope = 'Daycare' AND name = 'operatingHoursSchedule'
        ");
        $savedSchedule = json_decode($stmt->fetchColumn(), true);
        $this->assertEquals('09:00', $savedSchedule['monday']['openTime']);
    }

    /**
     * Test save with multiple closure days
     */
    public function testSaveWithMultipleClosureDays()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
            ],
            'closureDays' => [
                ['date' => '2026-12-25', 'reason' => 'Christmas Day'],
                ['date' => '2026-01-01', 'reason' => 'New Year Day'],
                ['date' => '2026-07-04', 'reason' => 'Independence Day'],
            ],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $result = $this->operatingHoursStep->save($data);

        $this->assertTrue($result);

        // Verify all closure days were saved
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonSchoolClosureDate");
        $count = $stmt->fetchColumn();
        $this->assertEquals(3, $count);
    }

    /**
     * Test save clears existing closure days before adding new ones
     */
    public function testSaveClearsExistingClosureDays()
    {
        // Add initial closure days
        $this->pdo->exec("
            INSERT INTO gibbonSchoolClosureDate (date, reason)
            VALUES ('2025-12-25', 'Old Christmas')
        ");

        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
            ],
            'closureDays' => [
                ['date' => '2026-12-25', 'reason' => 'New Christmas'],
            ],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $result = $this->operatingHoursStep->save($data);

        $this->assertTrue($result);

        // Verify only new closure day exists
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonSchoolClosureDate");
        $count = $stmt->fetchColumn();
        $this->assertEquals(1, $count);

        $stmt = $this->pdo->query("SELECT reason FROM gibbonSchoolClosureDate");
        $reason = $stmt->fetchColumn();
        $this->assertEquals('New Christmas', $reason);
    }

    /**
     * Test isCompleted returns false when no data
     */
    public function testIsCompletedReturnsFalseWhenNoData()
    {
        $result = $this->operatingHoursStep->isCompleted();

        $this->assertFalse($result);
    }

    /**
     * Test isCompleted returns true when data exists
     */
    public function testIsCompletedReturnsTrueWhenDataExists()
    {
        // Insert setting
        $this->pdo->exec("
            INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
            VALUES ('Daycare', 'operatingHoursSchedule', 'Schedule', 'Desc', '{}')
        ");

        $result = $this->operatingHoursStep->isCompleted();

        $this->assertTrue($result);
    }

    /**
     * Test getOperatingHours returns saved data
     */
    public function testGetOperatingHoursReturnsSavedData()
    {
        $schedule = [
            'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
        ];

        // Insert schedule
        $this->pdo->exec("
            INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
            VALUES ('Daycare', 'operatingHoursSchedule', 'Schedule', 'Desc', '" . json_encode($schedule) . "')
        ");

        // Insert timezone
        $this->pdo->exec("
            INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
            VALUES ('System', 'timezone', 'Timezone', 'Desc', 'America/New_York')
        ");

        // Insert closure day
        $this->pdo->exec("
            INSERT INTO gibbonSchoolClosureDate (date, reason)
            VALUES ('2026-12-25', 'Christmas Day')
        ");

        $result = $this->operatingHoursStep->getOperatingHours();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('schedule', $result);
        $this->assertArrayHasKey('closureDays', $result);
        $this->assertArrayHasKey('timezone', $result);
        $this->assertEquals($schedule, $result['schedule']);
        $this->assertEquals('America/New_York', $result['timezone']);
        $this->assertCount(1, $result['closureDays']);
        $this->assertEquals('2026-12-25', $result['closureDays'][0]['date']);
    }

    /**
     * Test getOperatingHours returns default when no data
     */
    public function testGetOperatingHoursReturnsDefaultWhenNoData()
    {
        $result = $this->operatingHoursStep->getOperatingHours();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('schedule', $result);
        $this->assertArrayHasKey('monday', $result['schedule']);
        $this->assertFalse($result['schedule']['monday']['isOpen']);
        $this->assertEquals('08:00', $result['schedule']['monday']['openTime']);
        $this->assertEquals('18:00', $result['schedule']['monday']['closeTime']);
    }

    /**
     * Test prepareData merges saved and wizard progress data
     */
    public function testPrepareDataMergesSavedAndWizardProgress()
    {
        $savedSchedule = [
            'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
        ];

        // Insert saved schedule
        $this->pdo->exec("
            INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
            VALUES ('Daycare', 'operatingHoursSchedule', 'Schedule', 'Desc', '" . json_encode($savedSchedule) . "')
        ");

        // Mock wizard progress with different schedule
        $wizardProgress = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '09:00', 'closeTime' => '17:00'],
            ],
        ];

        $this->installationDetector->method('getWizardProgress')
            ->willReturn(['stepData' => $wizardProgress]);

        $result = $this->operatingHoursStep->prepareData();

        // Wizard progress should override saved data
        $this->assertEquals('09:00', $result['schedule']['monday']['openTime']);
        $this->assertEquals('17:00', $result['schedule']['monday']['closeTime']);
    }

    /**
     * Test clear removes all operating hours data
     */
    public function testClearRemovesAllOperatingHoursData()
    {
        // Insert schedule
        $this->pdo->exec("
            INSERT INTO gibbonSetting (scope, name, nameDisplay, description, value)
            VALUES ('Daycare', 'operatingHoursSchedule', 'Schedule', 'Desc', '{}')
        ");

        // Insert closure day
        $this->pdo->exec("
            INSERT INTO gibbonSchoolClosureDate (date, reason)
            VALUES ('2026-12-25', 'Christmas Day')
        ");

        $result = $this->operatingHoursStep->clear();

        $this->assertTrue($result);

        // Verify schedule was deleted
        $stmt = $this->pdo->query("
            SELECT COUNT(*) FROM gibbonSetting
            WHERE scope = 'Daycare' AND name = 'operatingHoursSchedule'
        ");
        $count = $stmt->fetchColumn();
        $this->assertEquals(0, $count);

        // Verify closure days were deleted
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonSchoolClosureDate");
        $count = $stmt->fetchColumn();
        $this->assertEquals(0, $count);
    }

    /**
     * Test validation with all days of the week
     */
    public function testValidateWithAllDaysOfWeek()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'tuesday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'wednesday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'thursday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'friday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
                'saturday' => ['isOpen' => true, 'openTime' => '09:00', 'closeTime' => '15:00'],
                'sunday' => ['isOpen' => true, 'openTime' => '10:00', 'closeTime' => '14:00'],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertEmpty($errors);
    }

    /**
     * Test validation with partial week (weekend only)
     */
    public function testValidateWithPartialWeek()
    {
        $data = [
            'schedule' => [
                'saturday' => ['isOpen' => true, 'openTime' => '09:00', 'closeTime' => '15:00'],
                'sunday' => ['isOpen' => true, 'openTime' => '10:00', 'closeTime' => '14:00'],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertEmpty($errors);
    }

    /**
     * Test validation with edge case times (midnight)
     */
    public function testValidateWithMidnightTimes()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '00:00', 'closeTime' => '23:59'],
            ],
        ];

        $errors = $this->operatingHoursStep->validate($data);

        $this->assertEmpty($errors);
    }

    /**
     * Test save without closure days
     */
    public function testSaveWithoutClosureDays()
    {
        $data = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
            ],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $result = $this->operatingHoursStep->save($data);

        $this->assertTrue($result);

        // Verify no closure days were saved
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonSchoolClosureDate");
        $count = $stmt->fetchColumn();
        $this->assertEquals(0, $count);
    }

    /**
     * Test getWizardProgress returns null when no progress
     */
    public function testGetWizardProgressReturnsNullWhenNoProgress()
    {
        $this->installationDetector->method('getWizardProgress')
            ->willReturn(null);

        $result = $this->operatingHoursStep->getWizardProgress();

        $this->assertNull($result);
    }

    /**
     * Test getWizardProgress returns stepData when available
     */
    public function testGetWizardProgressReturnsStepDataWhenAvailable()
    {
        $stepData = [
            'schedule' => [
                'monday' => ['isOpen' => true, 'openTime' => '08:00', 'closeTime' => '18:00'],
            ],
        ];

        $this->installationDetector->method('getWizardProgress')
            ->willReturn(['stepData' => $stepData]);

        $result = $this->operatingHoursStep->getWizardProgress();

        $this->assertEquals($stepData, $result);
    }
}
