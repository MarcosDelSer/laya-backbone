<?php
/*
Gibbon: the flexible, open school platform
Founded by Ross Parker at ICHK Secondary. Built by Ross Parker, Sandra Kuipers and the Gibbon community (https://gibbonedu.org/about/)
Copyright (c) 2010, Gibbon Foundation
Gibbon(tm), Gibbon Education Ltd. (Hong Kong)

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

namespace Gibbon\Module\CareTracking\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Unit tests for Care Tracking Domain Gateways.
 *
 * These tests verify that all Care Tracking gateway classes are properly configured
 * with correct table names, primary keys, and searchable columns. Also validates
 * method signatures and expected method availability.
 *
 * @covers \Gibbon\Module\CareTracking\Domain\AttendanceGateway
 * @covers \Gibbon\Module\CareTracking\Domain\MealGateway
 * @covers \Gibbon\Module\CareTracking\Domain\NapGateway
 * @covers \Gibbon\Module\CareTracking\Domain\DiaperGateway
 * @covers \Gibbon\Module\CareTracking\Domain\IncidentGateway
 * @covers \Gibbon\Module\CareTracking\Domain\ActivityGateway
 */
class ModelTest extends TestCase
{
    /**
     * Gateway class configurations to test.
     *
     * @var array
     */
    private static $gatewayConfigs = [
        'Gibbon\\Module\\CareTracking\\Domain\\AttendanceGateway' => [
            'tableName' => 'gibbonCareAttendance',
            'primaryKey' => 'gibbonCareAttendanceID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareAttendance.notes'],
        ],
        'Gibbon\\Module\\CareTracking\\Domain\\MealGateway' => [
            'tableName' => 'gibbonCareMeal',
            'primaryKey' => 'gibbonCareMealID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareMeal.notes'],
        ],
        'Gibbon\\Module\\CareTracking\\Domain\\NapGateway' => [
            'tableName' => 'gibbonCareNap',
            'primaryKey' => 'gibbonCareNapID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareNap.notes'],
        ],
        'Gibbon\\Module\\CareTracking\\Domain\\DiaperGateway' => [
            'tableName' => 'gibbonCareDiaper',
            'primaryKey' => 'gibbonCareDiaperID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareDiaper.notes'],
        ],
        'Gibbon\\Module\\CareTracking\\Domain\\IncidentGateway' => [
            'tableName' => 'gibbonCareIncident',
            'primaryKey' => 'gibbonCareIncidentID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareIncident.description', 'gibbonCareIncident.actionTaken'],
        ],
        'Gibbon\\Module\\CareTracking\\Domain\\ActivityGateway' => [
            'tableName' => 'gibbonCareActivity',
            'primaryKey' => 'gibbonCareActivityID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonCareActivity.activityName', 'gibbonCareActivity.notes'],
        ],
    ];

    /**
     * Helper method to get the value of a private static property using reflection.
     *
     * @param string $className
     * @param string $propertyName
     * @return mixed
     */
    private function getStaticProperty(string $className, string $propertyName)
    {
        $reflection = new ReflectionClass($className);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue();
    }

    /**
     * Data provider for gateway configuration tests.
     *
     * @return array
     */
    public static function gatewayConfigProvider(): array
    {
        $data = [];
        foreach (self::$gatewayConfigs as $className => $config) {
            $shortName = substr($className, strrpos($className, '\\') + 1);
            $data[$shortName] = [$className, $config];
        }
        return $data;
    }

    // =========================================================================
    // GATEWAY TABLE NAME TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider gatewayConfigProvider
     */
    public function gatewayHasCorrectTableName(string $className, array $config): void
    {
        $tableName = $this->getStaticProperty($className, 'tableName');
        $this->assertEquals(
            $config['tableName'],
            $tableName,
            sprintf('%s should have table name "%s"', $className, $config['tableName'])
        );
    }

    /**
     * @test
     * @dataProvider gatewayConfigProvider
     */
    public function gatewayHasCorrectPrimaryKey(string $className, array $config): void
    {
        $primaryKey = $this->getStaticProperty($className, 'primaryKey');
        $this->assertEquals(
            $config['primaryKey'],
            $primaryKey,
            sprintf('%s should have primary key "%s"', $className, $config['primaryKey'])
        );
    }

    /**
     * @test
     * @dataProvider gatewayConfigProvider
     */
    public function gatewayHasCorrectSearchableColumns(string $className, array $config): void
    {
        $searchableColumns = $this->getStaticProperty($className, 'searchableColumns');
        $this->assertEquals(
            $config['searchableColumns'],
            $searchableColumns,
            sprintf('%s should have correct searchable columns', $className)
        );
    }

    // =========================================================================
    // ATTENDANCE GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function attendanceGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\AttendanceGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryAttendance',
            'queryAttendanceByDate',
            'queryAttendanceByPerson',
            'getAttendanceByPersonAndDate',
            'selectChildrenCurrentlyCheckedIn',
            'selectChildrenNotCheckedIn',
            'getAttendanceSummaryByDate',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('AttendanceGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function attendanceGatewayHasCheckInMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\AttendanceGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('checkIn'));

        $method = $reflection->getMethod('checkIn');
        $params = $method->getParameters();

        $this->assertCount(7, $params, 'checkIn should have 7 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
        $this->assertEquals('date', $params[2]->getName());
        $this->assertEquals('time', $params[3]->getName());
        $this->assertEquals('checkInByID', $params[4]->getName());
    }

    /**
     * @test
     */
    public function attendanceGatewayHasCheckOutMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\AttendanceGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('checkOut'));

        $method = $reflection->getMethod('checkOut');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(3, count($params), 'checkOut should have at least 3 parameters');
        $this->assertEquals('gibbonCareAttendanceID', $params[0]->getName());
        $this->assertEquals('time', $params[1]->getName());
        $this->assertEquals('checkOutByID', $params[2]->getName());
    }

    // =========================================================================
    // MEAL GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function mealGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\MealGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryMeals',
            'queryMealsByDate',
            'queryMealsByPerson',
            'selectMealsByPersonAndDate',
            'getMealByPersonDateAndType',
            'getMealSummaryByDate',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('MealGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function mealGatewayHasLogMealMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\MealGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('logMeal'));

        $method = $reflection->getMethod('logMeal');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(6, count($params), 'logMeal should have at least 6 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
        $this->assertEquals('date', $params[2]->getName());
        $this->assertEquals('mealType', $params[3]->getName());
        $this->assertEquals('quantity', $params[4]->getName());
        $this->assertEquals('recordedByID', $params[5]->getName());
    }

    /**
     * @test
     */
    public function mealGatewayHasChildrenWithoutMealMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\MealGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('selectChildrenWithoutMeal'),
            'MealGateway should have selectChildrenWithoutMeal method'
        );

        $method = $reflection->getMethod('selectChildrenWithoutMeal');
        $params = $method->getParameters();

        $this->assertCount(3, $params, 'selectChildrenWithoutMeal should have 3 parameters');
    }

    // =========================================================================
    // NAP GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function napGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\NapGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryNaps',
            'queryNapsByDate',
            'queryNapsByPerson',
            'selectNapsByPersonAndDate',
            'getActiveNap',
            'getNapSummaryByDate',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('NapGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function napGatewayHasStartNapMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\NapGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('startNap'));

        $method = $reflection->getMethod('startNap');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(5, count($params), 'startNap should have at least 5 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
        $this->assertEquals('date', $params[2]->getName());
        $this->assertEquals('startTime', $params[3]->getName());
        $this->assertEquals('recordedByID', $params[4]->getName());
    }

    /**
     * @test
     */
    public function napGatewayHasEndNapMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\NapGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('endNap'));

        $method = $reflection->getMethod('endNap');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'endNap should have at least 2 parameters');
        $this->assertEquals('gibbonCareNapID', $params[0]->getName());
        $this->assertEquals('endTime', $params[1]->getName());
    }

    /**
     * @test
     */
    public function napGatewayHasCurrentlyNappingMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\NapGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('selectChildrenCurrentlyNapping'),
            'NapGateway should have selectChildrenCurrentlyNapping method'
        );
    }

    // =========================================================================
    // DIAPER GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function diaperGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\DiaperGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryDiapers',
            'queryDiapersByDate',
            'queryDiapersByPerson',
            'selectDiapersByPersonAndDate',
            'getLastDiaperChange',
            'getDiaperSummaryByDate',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('DiaperGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function diaperGatewayHasLogDiaperChangeMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\DiaperGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('logDiaperChange'));

        $method = $reflection->getMethod('logDiaperChange');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(6, count($params), 'logDiaperChange should have at least 6 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
        $this->assertEquals('date', $params[2]->getName());
        $this->assertEquals('time', $params[3]->getName());
        $this->assertEquals('type', $params[4]->getName());
        $this->assertEquals('recordedByID', $params[5]->getName());
    }

    /**
     * @test
     */
    public function diaperGatewayHasChildrenNeedingChangeMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\DiaperGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('selectChildrenNeedingChange'),
            'DiaperGateway should have selectChildrenNeedingChange method'
        );

        $method = $reflection->getMethod('selectChildrenNeedingChange');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'selectChildrenNeedingChange should have at least 2 parameters');
    }

    /**
     * @test
     */
    public function diaperGatewayHasCountMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\DiaperGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('countDiaperChangesByPersonAndDate'),
            'DiaperGateway should have countDiaperChangesByPersonAndDate method'
        );
    }

    // =========================================================================
    // INCIDENT GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function incidentGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\IncidentGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryIncidents',
            'queryIncidentsByDate',
            'queryIncidentsByPerson',
            'selectIncidentsByPersonAndDate',
            'getIncidentSummaryByDate',
            'selectIncidentsPendingNotification',
            'selectIncidentsPendingAcknowledgment',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('IncidentGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function incidentGatewayHasLogIncidentMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\IncidentGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('logIncident'));

        $method = $reflection->getMethod('logIncident');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(8, count($params), 'logIncident should have at least 8 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
        $this->assertEquals('date', $params[2]->getName());
        $this->assertEquals('time', $params[3]->getName());
        $this->assertEquals('type', $params[4]->getName());
        $this->assertEquals('severity', $params[5]->getName());
        $this->assertEquals('description', $params[6]->getName());
        $this->assertEquals('recordedByID', $params[7]->getName());
    }

    /**
     * @test
     */
    public function incidentGatewayHasParentNotificationMethods(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\IncidentGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('markParentNotified'),
            'IncidentGateway should have markParentNotified method'
        );

        $this->assertTrue(
            $reflection->hasMethod('markParentAcknowledged'),
            'IncidentGateway should have markParentAcknowledged method'
        );
    }

    /**
     * @test
     */
    public function incidentGatewayHasSevereIncidentsMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\IncidentGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('selectSevereIncidents'),
            'IncidentGateway should have selectSevereIncidents method'
        );

        $method = $reflection->getMethod('selectSevereIncidents');
        $params = $method->getParameters();

        $this->assertCount(3, $params, 'selectSevereIncidents should have 3 parameters');
    }

    // =========================================================================
    // ACTIVITY GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function activityGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\ActivityGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryActivities',
            'queryActivitiesByDate',
            'queryActivitiesByPerson',
            'selectActivitiesByPersonAndDate',
            'getActivitySummaryByDate',
            'selectChildrenWithoutActivities',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('ActivityGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function activityGatewayHasLogActivityMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\ActivityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('logActivity'));

        $method = $reflection->getMethod('logActivity');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(6, count($params), 'logActivity should have at least 6 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
        $this->assertEquals('date', $params[2]->getName());
        $this->assertEquals('activityName', $params[3]->getName());
        $this->assertEquals('activityType', $params[4]->getName());
        $this->assertEquals('recordedByID', $params[5]->getName());
    }

    /**
     * @test
     */
    public function activityGatewayHasUpdateParticipationMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\ActivityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('updateParticipation'),
            'ActivityGateway should have updateParticipation method'
        );

        $method = $reflection->getMethod('updateParticipation');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'updateParticipation should have at least 2 parameters');
    }

    /**
     * @test
     */
    public function activityGatewayHasAISuggestedMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\ActivityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('selectAISuggestedActivities'),
            'ActivityGateway should have selectAISuggestedActivities method'
        );
    }

    /**
     * @test
     */
    public function activityGatewayHasPopularActivitiesMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\ActivityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('getPopularActivities'),
            'ActivityGateway should have getPopularActivities method'
        );

        $method = $reflection->getMethod('getPopularActivities');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(3, count($params), 'getPopularActivities should have at least 3 parameters');
    }

    /**
     * @test
     */
    public function activityGatewayHasActivityTypeDistributionMethod(): void
    {
        $className = 'Gibbon\\Module\\CareTracking\\Domain\\ActivityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('getActivityTypeDistribution'),
            'ActivityGateway should have getActivityTypeDistribution method'
        );
    }

    // =========================================================================
    // STATISTICS METHODS TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider statisticsMethodProvider
     */
    public function gatewayHasStatisticsMethod(string $className, string $methodName): void
    {
        $reflection = new ReflectionClass($className);
        $this->assertTrue(
            $reflection->hasMethod($methodName),
            sprintf('%s should have method %s', $className, $methodName)
        );
    }

    /**
     * Data provider for statistics methods.
     *
     * @return array
     */
    public static function statisticsMethodProvider(): array
    {
        return [
            'Attendance Stats by Date Range' => [
                'Gibbon\\Module\\CareTracking\\Domain\\AttendanceGateway',
                'getAttendanceStatsByPersonAndDateRange',
            ],
            'Meal Stats by Date Range' => [
                'Gibbon\\Module\\CareTracking\\Domain\\MealGateway',
                'getMealStatsByPersonAndDateRange',
            ],
            'Nap Stats by Date Range' => [
                'Gibbon\\Module\\CareTracking\\Domain\\NapGateway',
                'getNapStatsByPersonAndDateRange',
            ],
            'Diaper Stats by Date Range' => [
                'Gibbon\\Module\\CareTracking\\Domain\\DiaperGateway',
                'getDiaperStatsByPersonAndDateRange',
            ],
            'Incident Stats by Date Range' => [
                'Gibbon\\Module\\CareTracking\\Domain\\IncidentGateway',
                'getIncidentStatsByPersonAndDateRange',
            ],
            'Activity Stats by Date Range' => [
                'Gibbon\\Module\\CareTracking\\Domain\\ActivityGateway',
                'getActivityStatsByPersonAndDateRange',
            ],
        ];
    }

    // =========================================================================
    // GATEWAY CLASS INHERITANCE TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider gatewayClassProvider
     */
    public function gatewayExtendsQueryableGateway(string $className): void
    {
        $reflection = new ReflectionClass($className);
        $parentClass = $reflection->getParentClass();

        $this->assertNotFalse($parentClass, sprintf('%s should extend a parent class', $className));
        $this->assertEquals(
            'Gibbon\\Domain\\QueryableGateway',
            $parentClass->getName(),
            sprintf('%s should extend QueryableGateway', $className)
        );
    }

    /**
     * @test
     * @dataProvider gatewayClassProvider
     */
    public function gatewayUsesTableAwareTrait(string $className): void
    {
        $reflection = new ReflectionClass($className);
        $traits = $reflection->getTraitNames();

        $this->assertContains(
            'Gibbon\\Domain\\Traits\\TableAware',
            $traits,
            sprintf('%s should use TableAware trait', $className)
        );
    }

    /**
     * Data provider for gateway classes.
     *
     * @return array
     */
    public static function gatewayClassProvider(): array
    {
        return [
            'AttendanceGateway' => ['Gibbon\\Module\\CareTracking\\Domain\\AttendanceGateway'],
            'MealGateway' => ['Gibbon\\Module\\CareTracking\\Domain\\MealGateway'],
            'NapGateway' => ['Gibbon\\Module\\CareTracking\\Domain\\NapGateway'],
            'DiaperGateway' => ['Gibbon\\Module\\CareTracking\\Domain\\DiaperGateway'],
            'IncidentGateway' => ['Gibbon\\Module\\CareTracking\\Domain\\IncidentGateway'],
            'ActivityGateway' => ['Gibbon\\Module\\CareTracking\\Domain\\ActivityGateway'],
        ];
    }

    // =========================================================================
    // TABLE NAME FORMAT VALIDATION
    // =========================================================================

    /**
     * @test
     * @dataProvider tableNameProvider
     */
    public function tableNameFollowsGibbonConvention(string $tableName): void
    {
        // Gibbon tables should start with 'gibbon' prefix
        $this->assertStringStartsWith(
            'gibbon',
            $tableName,
            sprintf('Table name %s should start with "gibbon" prefix', $tableName)
        );

        // Care Tracking tables should have 'Care' in the name
        $this->assertStringContainsString(
            'Care',
            $tableName,
            sprintf('Table name %s should contain "Care" for Care Tracking module', $tableName)
        );
    }

    /**
     * Data provider for table names.
     *
     * @return array
     */
    public static function tableNameProvider(): array
    {
        return [
            ['gibbonCareAttendance'],
            ['gibbonCareMeal'],
            ['gibbonCareNap'],
            ['gibbonCareDiaper'],
            ['gibbonCareIncident'],
            ['gibbonCareActivity'],
        ];
    }

    // =========================================================================
    // PRIMARY KEY FORMAT VALIDATION
    // =========================================================================

    /**
     * @test
     * @dataProvider primaryKeyProvider
     */
    public function primaryKeyFollowsGibbonConvention(string $primaryKey, string $tableName): void
    {
        // Primary key should be tableName + 'ID'
        $expectedPrimaryKey = $tableName . 'ID';
        $this->assertEquals(
            $expectedPrimaryKey,
            $primaryKey,
            sprintf('Primary key should be %s', $expectedPrimaryKey)
        );
    }

    /**
     * Data provider for primary keys.
     *
     * @return array
     */
    public static function primaryKeyProvider(): array
    {
        return [
            ['gibbonCareAttendanceID', 'gibbonCareAttendance'],
            ['gibbonCareMealID', 'gibbonCareMeal'],
            ['gibbonCareNapID', 'gibbonCareNap'],
            ['gibbonCareDiaperID', 'gibbonCareDiaper'],
            ['gibbonCareIncidentID', 'gibbonCareIncident'],
            ['gibbonCareActivityID', 'gibbonCareActivity'],
        ];
    }

    // =========================================================================
    // FILTER RULE CONSISTENCY TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider gatewayClassProvider
     */
    public function gatewayQueryMethodAcceptsQueryCriteria(string $className): void
    {
        $reflection = new ReflectionClass($className);
        $shortName = substr($className, strrpos($className, '\\') + 1);

        // Determine the main query method name based on gateway type
        $methodName = match ($shortName) {
            'AttendanceGateway' => 'queryAttendance',
            'MealGateway' => 'queryMeals',
            'NapGateway' => 'queryNaps',
            'DiaperGateway' => 'queryDiapers',
            'IncidentGateway' => 'queryIncidents',
            'ActivityGateway' => 'queryActivities',
            default => null,
        };

        $this->assertNotNull($methodName, sprintf('Could not determine query method for %s', $className));
        $this->assertTrue($reflection->hasMethod($methodName));

        $method = $reflection->getMethod($methodName);
        $params = $method->getParameters();

        $this->assertGreaterThan(0, count($params), sprintf('%s::%s should have parameters', $className, $methodName));

        // First parameter should be QueryCriteria
        $firstParam = $params[0];
        $this->assertEquals(
            'criteria',
            $firstParam->getName(),
            sprintf('First parameter of %s::%s should be named "criteria"', $className, $methodName)
        );
    }
}
