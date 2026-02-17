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
use Gibbon\Module\System\Domain\SampleDataImportStep;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\System\Domain\InstallationDetector;

/**
 * SampleDataImportStepTest
 *
 * Unit tests for the SampleDataImportStep class.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class SampleDataImportStepTest extends TestCase
{
    protected $pdo;
    protected $settingGateway;
    protected $installationDetector;
    protected $sampleDataImportStep;

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

        // Create SampleDataImportStep instance
        $this->sampleDataImportStep = new SampleDataImportStep(
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
     * Create necessary database tables for testing
     */
    protected function createTables()
    {
        // Create gibbonSetting table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS gibbonSetting (
                gibbonSettingID INTEGER PRIMARY KEY AUTOINCREMENT,
                scope TEXT NOT NULL,
                name TEXT NOT NULL,
                nameDisplay TEXT NOT NULL,
                description TEXT,
                value TEXT
            )
        ");

        // Create gibbonPerson table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS gibbonPerson (
                gibbonPersonID INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT,
                surname TEXT NOT NULL,
                firstName TEXT NOT NULL,
                preferredName TEXT,
                officialName TEXT,
                gender TEXT,
                dob TEXT,
                username TEXT NOT NULL,
                password TEXT,
                email TEXT,
                jobTitle TEXT,
                status TEXT NOT NULL,
                canLogin TEXT NOT NULL
            )
        ");

        // Create gibbonSchoolYear table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS gibbonSchoolYear (
                gibbonSchoolYearID INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                status TEXT NOT NULL,
                sequenceNumber INTEGER
            )
        ");

        // Create gibbonStudentEnrolment table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS gibbonStudentEnrolment (
                gibbonStudentEnrolmentID INTEGER PRIMARY KEY AUTOINCREMENT,
                gibbonPersonID INTEGER NOT NULL,
                gibbonSchoolYearID INTEGER NOT NULL,
                gibbonYearGroupID INTEGER NOT NULL
            )
        ");

        // Create gibbonDaycareGroup table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS gibbonDaycareGroup (
                gibbonDaycareGroupID INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                description TEXT,
                minAge INTEGER,
                maxAge INTEGER,
                capacity INTEGER NOT NULL,
                isActive TEXT NOT NULL DEFAULT 'Y'
            )
        ");

        // Insert a current school year
        $this->pdo->exec("
            INSERT INTO gibbonSchoolYear (name, status, sequenceNumber)
            VALUES ('2025-2026', 'Current', 1)
        ");

        // Insert sample groups
        $this->pdo->exec("
            INSERT INTO gibbonDaycareGroup (name, description, minAge, maxAge, capacity, isActive)
            VALUES
                ('Infants', '0-12 months', 0, 1, 8, 'Y'),
                ('Toddlers', '1-3 years', 1, 3, 12, 'Y')
        ");
    }

    /**
     * Test validation when import is not requested (should pass)
     */
    public function testValidateWhenImportNotRequested()
    {
        $data = [
            'importSampleData' => false,
        ];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertEmpty($errors, 'Validation should pass when import is not requested');
    }

    /**
     * Test validation with valid data
     */
    public function testValidateWithValidData()
    {
        $data = [
            'importSampleData' => true,
            'categories' => ['students', 'parents', 'staff'],
            'studentCount' => 10,
            'parentCount' => 10,
            'staffCount' => 5,
        ];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertEmpty($errors, 'Valid data should have no validation errors');
    }

    /**
     * Test validation with empty data (should pass - import is optional)
     */
    public function testValidateWithEmptyData()
    {
        $data = [];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertEmpty($errors, 'Empty data should pass validation (import is optional)');
    }

    /**
     * Test validation with invalid category
     */
    public function testValidateWithInvalidCategory()
    {
        $data = [
            'importSampleData' => true,
            'categories' => ['students', 'invalid_category'],
        ];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertArrayHasKey('categories', $errors);
        $this->assertStringContainsString('Invalid category', $errors['categories']);
    }

    /**
     * Test validation with non-numeric student count
     */
    public function testValidateWithNonNumericStudentCount()
    {
        $data = [
            'importSampleData' => true,
            'studentCount' => 'abc',
        ];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertArrayHasKey('studentCount', $errors);
        $this->assertEquals('Student count must be a number', $errors['studentCount']);
    }

    /**
     * Test validation with negative student count
     */
    public function testValidateWithNegativeStudentCount()
    {
        $data = [
            'importSampleData' => true,
            'studentCount' => -5,
        ];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertArrayHasKey('studentCount', $errors);
        $this->assertEquals('Student count must be 0 or greater', $errors['studentCount']);
    }

    /**
     * Test validation with student count exceeding maximum
     */
    public function testValidateWithExcessiveStudentCount()
    {
        $data = [
            'importSampleData' => true,
            'studentCount' => 1001,
        ];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertArrayHasKey('studentCount', $errors);
        $this->assertEquals('Student count must not exceed 1000', $errors['studentCount']);
    }

    /**
     * Test validation with non-numeric parent count
     */
    public function testValidateWithNonNumericParentCount()
    {
        $data = [
            'importSampleData' => true,
            'parentCount' => 'xyz',
        ];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertArrayHasKey('parentCount', $errors);
        $this->assertEquals('Parent count must be a number', $errors['parentCount']);
    }

    /**
     * Test validation with negative parent count
     */
    public function testValidateWithNegativeParentCount()
    {
        $data = [
            'importSampleData' => true,
            'parentCount' => -3,
        ];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertArrayHasKey('parentCount', $errors);
        $this->assertEquals('Parent count must be 0 or greater', $errors['parentCount']);
    }

    /**
     * Test validation with parent count exceeding maximum
     */
    public function testValidateWithExcessiveParentCount()
    {
        $data = [
            'importSampleData' => true,
            'parentCount' => 1001,
        ];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertArrayHasKey('parentCount', $errors);
        $this->assertEquals('Parent count must not exceed 1000', $errors['parentCount']);
    }

    /**
     * Test validation with non-numeric staff count
     */
    public function testValidateWithNonNumericStaffCount()
    {
        $data = [
            'importSampleData' => true,
            'staffCount' => 'invalid',
        ];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertArrayHasKey('staffCount', $errors);
        $this->assertEquals('Staff count must be a number', $errors['staffCount']);
    }

    /**
     * Test validation with negative staff count
     */
    public function testValidateWithNegativeStaffCount()
    {
        $data = [
            'importSampleData' => true,
            'staffCount' => -2,
        ];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertArrayHasKey('staffCount', $errors);
        $this->assertEquals('Staff count must be 0 or greater', $errors['staffCount']);
    }

    /**
     * Test validation with staff count exceeding maximum
     */
    public function testValidateWithExcessiveStaffCount()
    {
        $data = [
            'importSampleData' => true,
            'staffCount' => 101,
        ];

        $errors = $this->sampleDataImportStep->validate($data);

        $this->assertArrayHasKey('staffCount', $errors);
        $this->assertEquals('Staff count must not exceed 100', $errors['staffCount']);
    }

    /**
     * Test save when import is declined
     */
    public function testSaveWhenImportDeclined()
    {
        $data = [
            'importSampleData' => false,
        ];

        $this->installationDetector
            ->expects($this->once())
            ->method('saveWizardProgress')
            ->with('sample_data_import', $data);

        $result = $this->sampleDataImportStep->save($data);

        $this->assertTrue($result, 'Save should succeed when import is declined');

        // Verify setting was saved
        $stmt = $this->pdo->prepare("
            SELECT value FROM gibbonSetting
            WHERE scope = 'System' AND name = 'sampleDataImported'
        ");
        $stmt->execute();
        $value = $stmt->fetchColumn();

        $this->assertEquals('N', $value, 'Setting should be N when import is declined');
    }

    /**
     * Test save with valid data and import requested
     */
    public function testSaveWithImportRequested()
    {
        $data = [
            'importSampleData' => true,
            'categories' => ['students'],
            'studentCount' => 5,
        ];

        $this->installationDetector
            ->expects($this->once())
            ->method('saveWizardProgress')
            ->with('sample_data_import', $data);

        $result = $this->sampleDataImportStep->save($data);

        $this->assertTrue($result, 'Save should succeed with valid data');

        // Verify settings were saved
        $stmt = $this->pdo->prepare("
            SELECT value FROM gibbonSetting
            WHERE scope = 'System' AND name = 'sampleDataImported'
        ");
        $stmt->execute();
        $value = $stmt->fetchColumn();

        $this->assertEquals('Y', $value, 'Setting should be Y when import is requested');

        // Verify students were created
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonPerson");
        $count = $stmt->fetchColumn();

        $this->assertGreaterThan(0, $count, 'Students should have been imported');
    }

    /**
     * Test save with invalid data
     */
    public function testSaveWithInvalidData()
    {
        $data = [
            'importSampleData' => true,
            'studentCount' => -1, // Invalid
        ];

        $result = $this->sampleDataImportStep->save($data);

        $this->assertFalse($result, 'Save should fail with invalid data');
    }

    /**
     * Test getSampleDataImport returns saved data
     */
    public function testGetSampleDataImport()
    {
        // Save some data first
        $data = [
            'importSampleData' => true,
            'categories' => ['students', 'parents'],
        ];

        $this->installationDetector
            ->method('saveWizardProgress');

        $this->sampleDataImportStep->save($data);

        // Retrieve it
        $retrieved = $this->sampleDataImportStep->getSampleDataImport();

        $this->assertNotNull($retrieved);
        $this->assertTrue($retrieved['importSampleData']);
        $this->assertEquals(['students', 'parents'], $retrieved['categories']);
    }

    /**
     * Test getSampleDataImport returns null when no data exists
     */
    public function testGetSampleDataImportReturnsNullWhenNoData()
    {
        $result = $this->sampleDataImportStep->getSampleDataImport();

        $this->assertNull($result, 'Should return null when no data exists');
    }

    /**
     * Test isCompleted returns true when setting exists
     */
    public function testIsCompletedReturnsTrueWhenSettingExists()
    {
        // Save data
        $data = ['importSampleData' => false];

        $this->installationDetector
            ->method('saveWizardProgress');

        $this->sampleDataImportStep->save($data);

        // Check completion
        $result = $this->sampleDataImportStep->isCompleted();

        $this->assertTrue($result, 'Should be completed when setting exists');
    }

    /**
     * Test isCompleted returns false when no setting exists
     */
    public function testIsCompletedReturnsFalseWhenNoSettingExists()
    {
        $result = $this->sampleDataImportStep->isCompleted();

        $this->assertFalse($result, 'Should not be completed when no setting exists');
    }

    /**
     * Test prepareData returns default settings when no data exists
     */
    public function testPrepareDataReturnsDefaultsWhenNoData()
    {
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn(null);

        $data = $this->sampleDataImportStep->prepareData();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('importSampleData', $data);
        $this->assertFalse($data['importSampleData']);
        $this->assertEquals([], $data['categories']);
        $this->assertEquals(10, $data['studentCount']);
        $this->assertEquals(10, $data['parentCount']);
        $this->assertEquals(5, $data['staffCount']);
    }

    /**
     * Test prepareData merges wizard progress
     */
    public function testPrepareDataMergesWizardProgress()
    {
        $wizardData = [
            'importSampleData' => true,
            'categories' => ['students'],
            'studentCount' => 20,
        ];

        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn(['stepData' => $wizardData]);

        $data = $this->sampleDataImportStep->prepareData();

        $this->assertTrue($data['importSampleData']);
        $this->assertEquals(['students'], $data['categories']);
        $this->assertEquals(20, $data['studentCount']);
    }

    /**
     * Test getDefaultSettings returns correct defaults
     */
    public function testGetDefaultSettings()
    {
        $defaults = $this->sampleDataImportStep->getDefaultSettings();

        $this->assertIsArray($defaults);
        $this->assertFalse($defaults['importSampleData']);
        $this->assertEquals([], $defaults['categories']);
        $this->assertEquals(10, $defaults['studentCount']);
        $this->assertEquals(10, $defaults['parentCount']);
        $this->assertEquals(5, $defaults['staffCount']);
    }

    /**
     * Test clear removes settings
     */
    public function testClear()
    {
        // Save some data first
        $data = ['importSampleData' => true];

        $this->installationDetector
            ->method('saveWizardProgress');

        $this->sampleDataImportStep->save($data);

        // Verify it exists
        $this->assertTrue($this->sampleDataImportStep->isCompleted());

        // Clear
        $result = $this->sampleDataImportStep->clear();

        $this->assertTrue($result, 'Clear should succeed');
        $this->assertFalse($this->sampleDataImportStep->isCompleted(), 'Settings should be cleared');
    }

    /**
     * Test getAvailableCategories returns all categories
     */
    public function testGetAvailableCategories()
    {
        $categories = $this->sampleDataImportStep->getAvailableCategories();

        $this->assertIsArray($categories);
        $this->assertContains('students', $categories);
        $this->assertContains('parents', $categories);
        $this->assertContains('staff', $categories);
        $this->assertContains('attendance', $categories);
        $this->assertContains('invoices', $categories);
        $this->assertContains('meals', $categories);
        $this->assertContains('activities', $categories);
    }

    /**
     * Test importStudents creates correct number of students
     */
    public function testImportStudentsCreatesCorrectNumber()
    {
        $data = [
            'importSampleData' => true,
            'categories' => ['students'],
            'studentCount' => 3,
        ];

        $this->installationDetector
            ->method('saveWizardProgress');

        $result = $this->sampleDataImportStep->save($data);

        $this->assertTrue($result);

        // Check student count
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonPerson");
        $count = $stmt->fetchColumn();

        $this->assertEquals(3, $count, 'Should create exactly 3 students');
    }

    /**
     * Test importParents creates parents
     */
    public function testImportParentsCreatesParents()
    {
        $data = [
            'importSampleData' => true,
            'categories' => ['parents'],
            'parentCount' => 2,
        ];

        $this->installationDetector
            ->method('saveWizardProgress');

        $result = $this->sampleDataImportStep->save($data);

        $this->assertTrue($result);

        // Check parent count
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonPerson WHERE canLogin = 'Y'");
        $count = $stmt->fetchColumn();

        $this->assertEquals(2, $count, 'Should create exactly 2 parents');
    }

    /**
     * Test importStaff creates staff members
     */
    public function testImportStaffCreatesStaff()
    {
        $data = [
            'importSampleData' => true,
            'categories' => ['staff'],
            'staffCount' => 4,
        ];

        $this->installationDetector
            ->method('saveWizardProgress');

        $result = $this->sampleDataImportStep->save($data);

        $this->assertTrue($result);

        // Check staff count
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonPerson WHERE jobTitle IS NOT NULL");
        $count = $stmt->fetchColumn();

        $this->assertEquals(4, $count, 'Should create exactly 4 staff members');
    }

    /**
     * Test importing multiple categories
     */
    public function testImportMultipleCategories()
    {
        $data = [
            'importSampleData' => true,
            'categories' => ['students', 'parents', 'staff'],
            'studentCount' => 2,
            'parentCount' => 2,
            'staffCount' => 1,
        ];

        $this->installationDetector
            ->method('saveWizardProgress');

        $result = $this->sampleDataImportStep->save($data);

        $this->assertTrue($result);

        // Check total person count
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonPerson");
        $count = $stmt->fetchColumn();

        $this->assertEquals(5, $count, 'Should create 2 students + 2 parents + 1 staff = 5 total');
    }

    /**
     * Test student count is clamped to maximum
     */
    public function testStudentCountIsClampedToMaximum()
    {
        $data = [
            'importSampleData' => true,
            'categories' => ['students'],
            'studentCount' => 2000, // Exceeds maximum, but should be clamped internally
        ];

        $this->installationDetector
            ->method('saveWizardProgress');

        // This should fail validation before import
        $result = $this->sampleDataImportStep->save($data);

        $this->assertFalse($result, 'Save should fail with excessive student count');
    }

    /**
     * Test getWizardProgress returns wizard data
     */
    public function testGetWizardProgress()
    {
        $wizardData = [
            'importSampleData' => true,
            'categories' => ['students'],
        ];

        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn(['stepData' => $wizardData]);

        $result = $this->sampleDataImportStep->getWizardProgress();

        $this->assertEquals($wizardData, $result);
    }

    /**
     * Test getWizardProgress returns null when no wizard data
     */
    public function testGetWizardProgressReturnsNullWhenNoData()
    {
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn(null);

        $result = $this->sampleDataImportStep->getWizardProgress();

        $this->assertNull($result);
    }
}
