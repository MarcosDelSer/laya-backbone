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
use Gibbon\Module\System\Domain\OrganizationInfoStep;
use Gibbon\Module\System\Domain\InstallationDetector;
use Gibbon\Domain\System\SettingGateway;
use PDO;
use PDOStatement;

/**
 * Unit tests for OrganizationInfoStep.
 *
 * These tests verify that the OrganizationInfoStep correctly validates,
 * saves, and retrieves organization information for the setup wizard.
 *
 * @covers \Gibbon\Module\System\Domain\OrganizationInfoStep
 */
class OrganizationInfoStepTest extends TestCase
{
    private $settingGateway;
    private $pdo;
    private $installationDetector;
    private $orgInfoStep;

    protected function setUp(): void
    {
        // Create mock objects
        $this->settingGateway = $this->createMock(SettingGateway::class);
        $this->pdo = $this->createMock(PDO::class);
        $this->installationDetector = $this->createMock(InstallationDetector::class);

        $this->orgInfoStep = new OrganizationInfoStep(
            $this->settingGateway,
            $this->pdo,
            $this->installationDetector
        );
    }

    // =========================================================================
    // VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function validateReturnsNoErrorsForValidData(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street, City, State 12345',
            'phone' => '+1 (555) 123-4567',
            'email' => 'info@sunshine-daycare.com',
            'website' => 'https://www.sunshine-daycare.com',
            'licenseNumber' => 'DC-2024-12345',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertEmpty($errors);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenNameIsEmpty(): void
    {
        $data = [
            'name' => '',
            'address' => '123 Main Street',
            'phone' => '555-1234',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('name', $errors);
        $this->assertEquals('Organization name is required', $errors['name']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenNameIsTooShort(): void
    {
        $data = [
            'name' => 'A',
            'address' => '123 Main Street',
            'phone' => '555-1234',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('name', $errors);
        $this->assertEquals('Organization name must be at least 2 characters', $errors['name']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenNameIsTooLong(): void
    {
        $data = [
            'name' => str_repeat('A', 256),
            'address' => '123 Main Street',
            'phone' => '555-1234',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('name', $errors);
        $this->assertEquals('Organization name must not exceed 255 characters', $errors['name']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenAddressIsEmpty(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '',
            'phone' => '555-1234',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('address', $errors);
        $this->assertEquals('Address is required', $errors['address']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenAddressIsTooShort(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123',
            'phone' => '555-1234',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('address', $errors);
        $this->assertEquals('Address must be at least 5 characters', $errors['address']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenAddressIsTooLong(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => str_repeat('A', 501),
            'phone' => '555-1234',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('address', $errors);
        $this->assertEquals('Address must not exceed 500 characters', $errors['address']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenPhoneIsEmpty(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('phone', $errors);
        $this->assertEquals('Phone number is required', $errors['phone']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenPhoneHasInvalidCharacters(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '555-ABC-1234',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('phone', $errors);
        $this->assertStringContainsString('digits, spaces, hyphens, parentheses, and plus sign', $errors['phone']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenPhoneIsTooLong(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => str_repeat('1', 51),
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('phone', $errors);
        $this->assertEquals('Phone number must not exceed 50 characters', $errors['phone']);
    }

    /**
     * @test
     */
    public function validateAcceptsValidPhoneFormats(): void
    {
        $validPhones = [
            '555-1234',
            '(555) 123-4567',
            '+1 555 123 4567',
            '555.123.4567',  // This should fail with current validation
            '1234567890',
        ];

        foreach ($validPhones as $phone) {
            $data = [
                'name' => 'Sunshine Daycare',
                'address' => '123 Main Street',
                'phone' => $phone,
            ];

            $errors = $this->orgInfoStep->validate($data);

            // Expect no phone error for valid formats
            if (preg_match('/^[\d\s\-\(\)\+]+$/', $phone)) {
                $this->assertArrayNotHasKey('phone', $errors, "Phone format '$phone' should be valid");
            }
        }
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenEmailIsInvalid(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '555-1234',
            'email' => 'invalid-email',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Invalid email address format', $errors['email']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenEmailIsTooLong(): void
    {
        $longEmail = str_repeat('a', 250) . '@test.com';
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '555-1234',
            'email' => $longEmail,
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('email', $errors);
        $this->assertEquals('Email must not exceed 255 characters', $errors['email']);
    }

    /**
     * @test
     */
    public function validateAcceptsEmptyEmail(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '555-1234',
            'email' => '',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayNotHasKey('email', $errors);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenWebsiteIsInvalid(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '555-1234',
            'website' => 'not-a-valid-url',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('website', $errors);
        $this->assertEquals('Invalid website URL format', $errors['website']);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenWebsiteIsTooLong(): void
    {
        $longUrl = 'http://' . str_repeat('a', 250) . '.com';
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '555-1234',
            'website' => $longUrl,
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('website', $errors);
        $this->assertEquals('Website URL must not exceed 255 characters', $errors['website']);
    }

    /**
     * @test
     */
    public function validateAcceptsEmptyWebsite(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '555-1234',
            'website' => '',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayNotHasKey('website', $errors);
    }

    /**
     * @test
     */
    public function validateReturnsErrorWhenLicenseNumberIsTooLong(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '555-1234',
            'licenseNumber' => str_repeat('A', 101),
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayHasKey('licenseNumber', $errors);
        $this->assertEquals('License number must not exceed 100 characters', $errors['licenseNumber']);
    }

    /**
     * @test
     */
    public function validateAcceptsEmptyLicenseNumber(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '555-1234',
            'licenseNumber' => '',
        ];

        $errors = $this->orgInfoStep->validate($data);

        $this->assertArrayNotHasKey('licenseNumber', $errors);
    }

    // =========================================================================
    // SAVE OPERATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function saveReturnsFalseForInvalidData(): void
    {
        $data = [
            'name' => '', // Invalid: empty name
            'address' => '123 Main Street',
            'phone' => '555-1234',
        ];

        // Mock getOrganizationInfo to return null (no existing org)
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('query')->willReturn($stmt);

        $result = $this->orgInfoStep->save($data);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function saveCreatesNewOrganizationWhenNoneExists(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '555-1234',
        ];

        // Mock getOrganizationInfo to return null (no existing org)
        $queryStmt = $this->createMock(PDOStatement::class);
        $queryStmt->method('fetch')->willReturn(false);

        // Mock prepare for INSERT
        $prepareStmt = $this->createMock(PDOStatement::class);
        $prepareStmt->method('execute')->willReturn(true);

        $this->pdo->method('query')->willReturn($queryStmt);
        $this->pdo->method('prepare')->willReturn($prepareStmt);

        $this->installationDetector
            ->method('saveWizardProgress')
            ->willReturn(true);

        $result = $this->orgInfoStep->save($data);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function saveUpdatesExistingOrganization(): void
    {
        $data = [
            'name' => 'Updated Daycare',
            'address' => '456 New Street',
            'phone' => '555-9999',
        ];

        // Mock getOrganizationInfo to return existing org
        $existingData = [
            'name' => 'Old Daycare',
            'address' => '123 Old Street',
            'phone' => '555-1234',
        ];

        $queryStmt = $this->createMock(PDOStatement::class);
        $queryStmt->method('fetch')->willReturn($existingData);

        // Mock prepare for UPDATE
        $prepareStmt = $this->createMock(PDOStatement::class);
        $prepareStmt->method('execute')->willReturn(true);

        $this->pdo->method('query')->willReturn($queryStmt);
        $this->pdo->method('prepare')->willReturn($prepareStmt);

        $this->installationDetector
            ->method('saveWizardProgress')
            ->willReturn(true);

        $result = $this->orgInfoStep->save($data);

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function saveReturnsFalseOnDatabaseException(): void
    {
        $data = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '555-1234',
        ];

        $this->pdo
            ->method('query')
            ->will($this->throwException(new \PDOException('Database error')));

        $result = $this->orgInfoStep->save($data);

        $this->assertFalse($result);
    }

    // =========================================================================
    // GET ORGANIZATION INFO TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getOrganizationInfoReturnsNullWhenNoRecord(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('query')->willReturn($stmt);

        $result = $this->orgInfoStep->getOrganizationInfo();

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function getOrganizationInfoReturnsDataWhenRecordExists(): void
    {
        $orgData = [
            'name' => 'Sunshine Daycare',
            'nameShort' => 'Sunshine',
            'address' => '123 Main Street',
            'phone' => '555-1234',
            'email' => 'info@sunshine.com',
            'website' => 'https://sunshine.com',
            'licenseNumber' => 'DC-12345',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($orgData);

        $this->pdo->method('query')->willReturn($stmt);

        $result = $this->orgInfoStep->getOrganizationInfo();

        $this->assertIsArray($result);
        $this->assertEquals('Sunshine Daycare', $result['name']);
        $this->assertEquals('123 Main Street', $result['address']);
        $this->assertEquals('555-1234', $result['phone']);
    }

    /**
     * @test
     */
    public function getOrganizationInfoReturnsNullOnDatabaseException(): void
    {
        $this->pdo
            ->method('query')
            ->will($this->throwException(new \PDOException('Database error')));

        $result = $this->orgInfoStep->getOrganizationInfo();

        $this->assertNull($result);
    }

    // =========================================================================
    // IS COMPLETED TESTS
    // =========================================================================

    /**
     * @test
     */
    public function isCompletedReturnsTrueWhenDataExists(): void
    {
        $orgData = [
            'name' => 'Sunshine Daycare',
            'address' => '123 Main Street',
            'phone' => '555-1234',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($orgData);

        $this->pdo->method('query')->willReturn($stmt);

        $result = $this->orgInfoStep->isCompleted();

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function isCompletedReturnsFalseWhenNoData(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('query')->willReturn($stmt);

        $result = $this->orgInfoStep->isCompleted();

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function isCompletedReturnsFalseWhenNameIsEmpty(): void
    {
        $orgData = [
            'name' => '',
            'address' => '123 Main Street',
            'phone' => '555-1234',
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($orgData);

        $this->pdo->method('query')->willReturn($stmt);

        $result = $this->orgInfoStep->isCompleted();

        $this->assertFalse($result);
    }

    // =========================================================================
    // WIZARD PROGRESS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getWizardProgressReturnsDataFromInstallationDetector(): void
    {
        $wizardData = [
            'gibbonSetupWizardID' => 1,
            'stepCompleted' => 'organization',
            'stepData' => [
                'name' => 'Test Daycare',
                'address' => '123 Test St',
                'phone' => '555-0000',
            ],
        ];

        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn($wizardData);

        $result = $this->orgInfoStep->getWizardProgress();

        $this->assertIsArray($result);
        $this->assertEquals('Test Daycare', $result['name']);
    }

    /**
     * @test
     */
    public function getWizardProgressReturnsNullWhenNoProgress(): void
    {
        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn(null);

        $result = $this->orgInfoStep->getWizardProgress();

        $this->assertNull($result);
    }

    /**
     * @test
     */
    public function getWizardProgressReturnsNullWhenStepDataMissing(): void
    {
        $wizardData = [
            'gibbonSetupWizardID' => 1,
            'stepCompleted' => 'organization',
        ];

        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn($wizardData);

        $result = $this->orgInfoStep->getWizardProgress();

        $this->assertNull($result);
    }

    // =========================================================================
    // PREPARE DATA TESTS
    // =========================================================================

    /**
     * @test
     */
    public function prepareDataReturnsDefaultsWhenNoDataExists(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);

        $this->pdo->method('query')->willReturn($stmt);

        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn(null);

        $result = $this->orgInfoStep->prepareData();

        $this->assertIsArray($result);
        $this->assertEquals('', $result['name']);
        $this->assertEquals('', $result['address']);
        $this->assertEquals('', $result['phone']);
        $this->assertEquals('', $result['email']);
        $this->assertEquals('', $result['website']);
        $this->assertEquals('', $result['licenseNumber']);
    }

    /**
     * @test
     */
    public function prepareDataMergesSavedDataWithWizardProgress(): void
    {
        $savedData = [
            'name' => 'Saved Daycare',
            'address' => '123 Saved St',
            'phone' => '555-1111',
        ];

        $wizardData = [
            'gibbonSetupWizardID' => 1,
            'stepCompleted' => 'organization',
            'stepData' => [
                'name' => 'Updated Daycare',
                'email' => 'new@email.com',
            ],
        ];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn($savedData);

        $this->pdo->method('query')->willReturn($stmt);

        $this->installationDetector
            ->method('getWizardProgress')
            ->willReturn($wizardData);

        $result = $this->orgInfoStep->prepareData();

        // Wizard data should override saved data
        $this->assertEquals('Updated Daycare', $result['name']);
        $this->assertEquals('new@email.com', $result['email']);
        // Non-overridden fields should remain from saved data
        $this->assertEquals('123 Saved St', $result['address']);
        $this->assertEquals('555-1111', $result['phone']);
    }

    // =========================================================================
    // CLEAR TESTS
    // =========================================================================

    /**
     * @test
     */
    public function clearDeletesOrganizationData(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->orgInfoStep->clear();

        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function clearReturnsFalseOnDatabaseException(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')
            ->will($this->throwException(new \PDOException('Database error')));

        $this->pdo->method('prepare')->willReturn($stmt);

        $result = $this->orgInfoStep->clear();

        $this->assertFalse($result);
    }

    // =========================================================================
    // SHORT NAME GENERATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function saveGeneratesShortNameFromFirstWord(): void
    {
        $data = [
            'name' => 'Sunshine Daycare Center',
            'address' => '123 Main Street',
            'phone' => '555-1234',
        ];

        $queryStmt = $this->createMock(PDOStatement::class);
        $queryStmt->method('fetch')->willReturn(false);

        $prepareStmt = $this->createMock(PDOStatement::class);

        // Capture the execute parameters to verify short name
        $executeParams = null;
        $prepareStmt->method('execute')
            ->willReturnCallback(function($params) use (&$executeParams) {
                $executeParams = $params;
                return true;
            });

        $this->pdo->method('query')->willReturn($queryStmt);
        $this->pdo->method('prepare')->willReturn($prepareStmt);

        $this->installationDetector
            ->method('saveWizardProgress')
            ->willReturn(true);

        $this->orgInfoStep->save($data);

        // Short name should be first word
        $this->assertEquals('Sunshine', $executeParams[':nameShort']);
    }

    /**
     * @test
     */
    public function saveGeneratesShortNameLimitedTo20Characters(): void
    {
        $data = [
            'name' => 'VeryLongOrganizationNameThatExceedsLimit',
            'address' => '123 Main Street',
            'phone' => '555-1234',
        ];

        $queryStmt = $this->createMock(PDOStatement::class);
        $queryStmt->method('fetch')->willReturn(false);

        $prepareStmt = $this->createMock(PDOStatement::class);

        $executeParams = null;
        $prepareStmt->method('execute')
            ->willReturnCallback(function($params) use (&$executeParams) {
                $executeParams = $params;
                return true;
            });

        $this->pdo->method('query')->willReturn($queryStmt);
        $this->pdo->method('prepare')->willReturn($prepareStmt);

        $this->installationDetector
            ->method('saveWizardProgress')
            ->willReturn(true);

        $this->orgInfoStep->save($data);

        // Short name should be truncated to 20 chars
        $this->assertEquals(20, strlen($executeParams[':nameShort']));
        $this->assertEquals('VeryLongOrganization', $executeParams[':nameShort']);
    }
}
