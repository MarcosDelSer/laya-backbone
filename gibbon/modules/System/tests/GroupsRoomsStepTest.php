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
use Gibbon\Module\System\Domain\GroupsRoomsStep;
use Gibbon\Domain\System\SettingGateway;
use Gibbon\Module\System\Domain\InstallationDetector;

/**
 * GroupsRoomsStepTest
 *
 * Unit tests for the GroupsRoomsStep class.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class GroupsRoomsStepTest extends TestCase
{
    protected $pdo;
    protected $settingGateway;
    protected $installationDetector;
    protected $groupsRoomsStep;

    protected function setUp(): void
    {
        // Create in-memory SQLite database for testing
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Create mock SettingGateway
        $this->settingGateway = $this->createMock(SettingGateway::class);

        // Create mock InstallationDetector
        $this->installationDetector = $this->createMock(InstallationDetector::class);

        // Create GroupsRoomsStep instance
        $this->groupsRoomsStep = new GroupsRoomsStep(
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
     * Test validation with valid groups data
     */
    public function testValidateWithValidGroups()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'description' => '0-12 months',
                    'minAge' => 0,
                    'maxAge' => 1,
                    'capacity' => 8,
                ],
                [
                    'name' => 'Toddlers',
                    'description' => '1-3 years',
                    'minAge' => 1,
                    'maxAge' => 3,
                    'capacity' => 12,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertEmpty($errors, 'Valid groups should have no validation errors');
    }

    /**
     * Test validation with missing groups
     */
    public function testValidateWithMissingGroups()
    {
        $data = [];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups', $errors);
        $this->assertEquals('At least one group/room is required', $errors['groups']);
    }

    /**
     * Test validation with empty groups array
     */
    public function testValidateWithEmptyGroupsArray()
    {
        $data = ['groups' => []];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups', $errors);
        $this->assertEquals('At least one group/room is required', $errors['groups']);
    }

    /**
     * Test validation with missing group name
     */
    public function testValidateWithMissingGroupName()
    {
        $data = [
            'groups' => [
                [
                    'name' => '',
                    'capacity' => 10,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.name', $errors);
        $this->assertEquals('Group name is required', $errors['groups.0.name']);
    }

    /**
     * Test validation with too short group name
     */
    public function testValidateWithTooShortGroupName()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'A',
                    'capacity' => 10,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.name', $errors);
        $this->assertEquals('Group name must be at least 2 characters', $errors['groups.0.name']);
    }

    /**
     * Test validation with too long group name
     */
    public function testValidateWithTooLongGroupName()
    {
        $data = [
            'groups' => [
                [
                    'name' => str_repeat('a', 101),
                    'capacity' => 10,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.name', $errors);
        $this->assertEquals('Group name must not exceed 100 characters', $errors['groups.0.name']);
    }

    /**
     * Test validation with duplicate group names
     */
    public function testValidateWithDuplicateGroupNames()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 10,
                ],
                [
                    'name' => 'Infants',
                    'capacity' => 12,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.1.name', $errors);
        $this->assertEquals('Group name must be unique', $errors['groups.1.name']);
    }

    /**
     * Test validation with duplicate group names (case insensitive)
     */
    public function testValidateWithDuplicateGroupNamesCaseInsensitive()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 10,
                ],
                [
                    'name' => 'INFANTS',
                    'capacity' => 12,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.1.name', $errors);
        $this->assertEquals('Group name must be unique', $errors['groups.1.name']);
    }

    /**
     * Test validation with missing capacity
     */
    public function testValidateWithMissingCapacity()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => '',
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.capacity', $errors);
        $this->assertEquals('Capacity is required', $errors['groups.0.capacity']);
    }

    /**
     * Test validation with non-numeric capacity
     */
    public function testValidateWithNonNumericCapacity()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 'abc',
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.capacity', $errors);
        $this->assertEquals('Capacity must be a number', $errors['groups.0.capacity']);
    }

    /**
     * Test validation with capacity less than 1
     */
    public function testValidateWithCapacityLessThanOne()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 0,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.capacity', $errors);
        $this->assertEquals('Capacity must be at least 1', $errors['groups.0.capacity']);
    }

    /**
     * Test validation with capacity exceeding maximum
     */
    public function testValidateWithCapacityExceedingMaximum()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 1000,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.capacity', $errors);
        $this->assertEquals('Capacity must not exceed 999', $errors['groups.0.capacity']);
    }

    /**
     * Test validation with non-numeric minAge
     */
    public function testValidateWithNonNumericMinAge()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 10,
                    'minAge' => 'abc',
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.minAge', $errors);
        $this->assertEquals('Minimum age must be a number', $errors['groups.0.minAge']);
    }

    /**
     * Test validation with negative minAge
     */
    public function testValidateWithNegativeMinAge()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 10,
                    'minAge' => -1,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.minAge', $errors);
        $this->assertEquals('Minimum age must be 0 or greater', $errors['groups.0.minAge']);
    }

    /**
     * Test validation with minAge exceeding maximum
     */
    public function testValidateWithMinAgeExceedingMaximum()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 10,
                    'minAge' => 19,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.minAge', $errors);
        $this->assertEquals('Minimum age must not exceed 18 years', $errors['groups.0.minAge']);
    }

    /**
     * Test validation with non-numeric maxAge
     */
    public function testValidateWithNonNumericMaxAge()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 10,
                    'maxAge' => 'xyz',
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.maxAge', $errors);
        $this->assertEquals('Maximum age must be a number', $errors['groups.0.maxAge']);
    }

    /**
     * Test validation with negative maxAge
     */
    public function testValidateWithNegativeMaxAge()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 10,
                    'maxAge' => -1,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.maxAge', $errors);
        $this->assertEquals('Maximum age must be 0 or greater', $errors['groups.0.maxAge']);
    }

    /**
     * Test validation with maxAge exceeding maximum
     */
    public function testValidateWithMaxAgeExceedingMaximum()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 10,
                    'maxAge' => 20,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.maxAge', $errors);
        $this->assertEquals('Maximum age must not exceed 18 years', $errors['groups.0.maxAge']);
    }

    /**
     * Test validation with minAge greater than maxAge
     */
    public function testValidateWithMinAgeGreaterThanMaxAge()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 10,
                    'minAge' => 5,
                    'maxAge' => 3,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.ageRange', $errors);
        $this->assertEquals('Minimum age must be less than or equal to maximum age', $errors['groups.0.ageRange']);
    }

    /**
     * Test validation with equal minAge and maxAge (valid)
     */
    public function testValidateWithEqualMinAndMaxAge()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 10,
                    'minAge' => 2,
                    'maxAge' => 2,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayNotHasKey('groups.0.ageRange', $errors);
    }

    /**
     * Test validation with too long description
     */
    public function testValidateWithTooLongDescription()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 10,
                    'description' => str_repeat('a', 501),
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.description', $errors);
        $this->assertEquals('Description must not exceed 500 characters', $errors['groups.0.description']);
    }

    /**
     * Test validation with valid description
     */
    public function testValidateWithValidDescription()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'capacity' => 10,
                    'description' => 'This is a valid description',
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayNotHasKey('groups.0.description', $errors);
    }

    /**
     * Test validation with optional fields omitted
     */
    public function testValidateWithOptionalFieldsOmitted()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Mixed Ages',
                    'capacity' => 15,
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertEmpty($errors);
    }

    /**
     * Test save with valid data
     */
    public function testSaveWithValidData()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'description' => '0-12 months',
                    'minAge' => 0,
                    'maxAge' => 1,
                    'capacity' => 8,
                ],
                [
                    'name' => 'Toddlers',
                    'description' => '1-3 years',
                    'minAge' => 1,
                    'maxAge' => 3,
                    'capacity' => 12,
                ],
            ],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress')
            ->with('groups_rooms', $data);

        $result = $this->groupsRoomsStep->save($data);

        $this->assertTrue($result, 'Save should succeed with valid data');

        // Verify groups were saved
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonDaycareGroup");
        $count = $stmt->fetchColumn();
        $this->assertEquals(2, $count);

        // Verify first group details
        $stmt = $this->pdo->query("
            SELECT name, description, minAge, maxAge, capacity
            FROM gibbonDaycareGroup
            WHERE name = 'Infants'
        ");
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Infants', $group['name']);
        $this->assertEquals('0-12 months', $group['description']);
        $this->assertEquals(0, $group['minAge']);
        $this->assertEquals(1, $group['maxAge']);
        $this->assertEquals(8, $group['capacity']);
    }

    /**
     * Test save with invalid data
     */
    public function testSaveWithInvalidData()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'A',  // Too short
                    'capacity' => 10,
                ],
            ],
        ];

        $this->installationDetector->expects($this->never())
            ->method('saveWizardProgress');

        $result = $this->groupsRoomsStep->save($data);

        $this->assertFalse($result, 'Save should fail with invalid data');
    }

    /**
     * Test save clears existing groups before adding new ones
     */
    public function testSaveClearsExistingGroups()
    {
        // Create table and add initial group
        $this->groupsRoomsStep->save([
            'groups' => [
                ['name' => 'Old Group', 'capacity' => 5],
            ],
        ]);

        // Save new groups
        $data = [
            'groups' => [
                ['name' => 'New Group', 'capacity' => 10],
            ],
        ];

        $this->installationDetector->expects($this->atLeastOnce())
            ->method('saveWizardProgress');

        $result = $this->groupsRoomsStep->save($data);

        $this->assertTrue($result);

        // Verify only new group exists
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonDaycareGroup");
        $count = $stmt->fetchColumn();
        $this->assertEquals(1, $count);

        $stmt = $this->pdo->query("SELECT name FROM gibbonDaycareGroup");
        $name = $stmt->fetchColumn();
        $this->assertEquals('New Group', $name);
    }

    /**
     * Test save with group without age ranges
     */
    public function testSaveWithGroupWithoutAgeRanges()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Mixed Ages',
                    'capacity' => 20,
                ],
            ],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $result = $this->groupsRoomsStep->save($data);

        $this->assertTrue($result);

        $stmt = $this->pdo->query("
            SELECT name, minAge, maxAge, capacity
            FROM gibbonDaycareGroup
        ");
        $group = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('Mixed Ages', $group['name']);
        $this->assertNull($group['minAge']);
        $this->assertNull($group['maxAge']);
        $this->assertEquals(20, $group['capacity']);
    }

    /**
     * Test isCompleted returns false when no groups
     */
    public function testIsCompletedReturnsFalseWhenNoGroups()
    {
        $result = $this->groupsRoomsStep->isCompleted();

        $this->assertFalse($result);
    }

    /**
     * Test isCompleted returns true when groups exist
     */
    public function testIsCompletedReturnsTrueWhenGroupsExist()
    {
        $data = [
            'groups' => [
                ['name' => 'Test Group', 'capacity' => 10],
            ],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $this->groupsRoomsStep->save($data);

        $result = $this->groupsRoomsStep->isCompleted();

        $this->assertTrue($result);
    }

    /**
     * Test getGroupsRooms returns saved data
     */
    public function testGetGroupsRoomsReturnsSavedData()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Infants',
                    'description' => 'Young babies',
                    'minAge' => 0,
                    'maxAge' => 1,
                    'capacity' => 8,
                ],
            ],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $this->groupsRoomsStep->save($data);

        $result = $this->groupsRoomsStep->getGroupsRooms();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('groups', $result);
        $this->assertCount(1, $result['groups']);
        $this->assertEquals('Infants', $result['groups'][0]['name']);
        $this->assertEquals('Young babies', $result['groups'][0]['description']);
        $this->assertEquals(0, $result['groups'][0]['minAge']);
        $this->assertEquals(1, $result['groups'][0]['maxAge']);
        $this->assertEquals(8, $result['groups'][0]['capacity']);
    }

    /**
     * Test getGroupsRooms returns empty array when no data
     */
    public function testGetGroupsRoomsReturnsEmptyArrayWhenNoData()
    {
        $result = $this->groupsRoomsStep->getGroupsRooms();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('groups', $result);
        $this->assertEmpty($result['groups']);
    }

    /**
     * Test prepareData merges saved and wizard progress data
     */
    public function testPrepareDataMergesSavedAndWizardProgress()
    {
        // Save initial data
        $this->groupsRoomsStep->save([
            'groups' => [
                ['name' => 'Saved Group', 'capacity' => 10],
            ],
        ]);

        // Mock wizard progress with different data
        $wizardProgress = [
            'groups' => [
                ['name' => 'Progress Group', 'capacity' => 15],
            ],
        ];

        $this->installationDetector->method('getWizardProgress')
            ->willReturn(['stepData' => $wizardProgress]);

        $result = $this->groupsRoomsStep->prepareData();

        // Wizard progress should override saved data
        $this->assertCount(1, $result['groups']);
        $this->assertEquals('Progress Group', $result['groups'][0]['name']);
        $this->assertEquals(15, $result['groups'][0]['capacity']);
    }

    /**
     * Test clear removes all groups
     */
    public function testClearRemovesAllGroups()
    {
        // Save groups
        $this->groupsRoomsStep->save([
            'groups' => [
                ['name' => 'Test Group', 'capacity' => 10],
            ],
        ]);

        $result = $this->groupsRoomsStep->clear();

        $this->assertTrue($result);

        // Verify groups were deleted
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonDaycareGroup");
        $count = $stmt->fetchColumn();
        $this->assertEquals(0, $count);
    }

    /**
     * Test getDefaultGroups returns sample groups
     */
    public function testGetDefaultGroupsReturnsSampleGroups()
    {
        $result = $this->groupsRoomsStep->getDefaultGroups();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('groups', $result);
        $this->assertCount(3, $result['groups']);
        $this->assertEquals('Infants', $result['groups'][0]['name']);
        $this->assertEquals('Toddlers', $result['groups'][1]['name']);
        $this->assertEquals('Preschool', $result['groups'][2]['name']);
    }

    /**
     * Test getWizardProgress returns null when no progress
     */
    public function testGetWizardProgressReturnsNullWhenNoProgress()
    {
        $this->installationDetector->method('getWizardProgress')
            ->willReturn(null);

        $result = $this->groupsRoomsStep->getWizardProgress();

        $this->assertNull($result);
    }

    /**
     * Test getWizardProgress returns stepData when available
     */
    public function testGetWizardProgressReturnsStepDataWhenAvailable()
    {
        $stepData = [
            'groups' => [
                ['name' => 'Test Group', 'capacity' => 10],
            ],
        ];

        $this->installationDetector->method('getWizardProgress')
            ->willReturn(['stepData' => $stepData]);

        $result = $this->groupsRoomsStep->getWizardProgress();

        $this->assertEquals($stepData, $result);
    }

    /**
     * Test save with multiple groups
     */
    public function testSaveWithMultipleGroups()
    {
        $data = [
            'groups' => [
                ['name' => 'Group 1', 'capacity' => 10],
                ['name' => 'Group 2', 'capacity' => 15],
                ['name' => 'Group 3', 'capacity' => 20],
            ],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $result = $this->groupsRoomsStep->save($data);

        $this->assertTrue($result);

        $stmt = $this->pdo->query("SELECT COUNT(*) FROM gibbonDaycareGroup");
        $count = $stmt->fetchColumn();
        $this->assertEquals(3, $count);
    }

    /**
     * Test validation with multiple errors on same group
     */
    public function testValidateWithMultipleErrorsOnSameGroup()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'A',  // Too short
                    'capacity' => 0,  // Too small
                    'minAge' => -1,  // Negative
                    'maxAge' => 20,  // Too large
                    'description' => str_repeat('a', 501),  // Too long
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertArrayHasKey('groups.0.name', $errors);
        $this->assertArrayHasKey('groups.0.capacity', $errors);
        $this->assertArrayHasKey('groups.0.minAge', $errors);
        $this->assertArrayHasKey('groups.0.maxAge', $errors);
        $this->assertArrayHasKey('groups.0.description', $errors);
    }

    /**
     * Test validation with valid edge case values
     */
    public function testValidateWithValidEdgeCaseValues()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'AB',  // Minimum length
                    'capacity' => 1,  // Minimum capacity
                    'minAge' => 0,  // Minimum age
                    'maxAge' => 18,  // Maximum age
                ],
            ],
        ];

        $errors = $this->groupsRoomsStep->validate($data);

        $this->assertEmpty($errors);
    }

    /**
     * Test save with isActive field
     */
    public function testSaveWithIsActiveField()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Active Group',
                    'capacity' => 10,
                    'isActive' => 'Y',
                ],
                [
                    'name' => 'Inactive Group',
                    'capacity' => 10,
                    'isActive' => 'N',
                ],
            ],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $result = $this->groupsRoomsStep->save($data);

        $this->assertTrue($result);

        $stmt = $this->pdo->query("
            SELECT name, isActive
            FROM gibbonDaycareGroup
            ORDER BY name ASC
        ");
        $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->assertEquals('Y', $groups[0]['isActive']);
        $this->assertEquals('N', $groups[1]['isActive']);
    }

    /**
     * Test save with default isActive value
     */
    public function testSaveWithDefaultIsActiveValue()
    {
        $data = [
            'groups' => [
                [
                    'name' => 'Test Group',
                    'capacity' => 10,
                ],
            ],
        ];

        $this->installationDetector->expects($this->once())
            ->method('saveWizardProgress');

        $result = $this->groupsRoomsStep->save($data);

        $this->assertTrue($result);

        $stmt = $this->pdo->query("SELECT isActive FROM gibbonDaycareGroup");
        $isActive = $stmt->fetchColumn();

        $this->assertEquals('Y', $isActive);
    }
}
