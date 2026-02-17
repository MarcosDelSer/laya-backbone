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

namespace Gibbon\Module\MedicalTracking\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Unit tests for Medical Tracking Domain Gateways.
 *
 * These tests verify that all Medical Tracking gateway classes are properly configured
 * with correct table names, primary keys, and searchable columns. Also validates
 * method signatures and expected method availability.
 *
 * @covers \Gibbon\Module\MedicalTracking\Domain\AllergyGateway
 * @covers \Gibbon\Module\MedicalTracking\Domain\MedicationGateway
 * @covers \Gibbon\Module\MedicalTracking\Domain\AccommodationPlanGateway
 * @covers \Gibbon\Module\MedicalTracking\Domain\MedicalAlertGateway
 */
class ModelTest extends TestCase
{
    /**
     * Gateway class configurations to test.
     *
     * @var array
     */
    private static $gatewayConfigs = [
        'Gibbon\\Module\\MedicalTracking\\Domain\\AllergyGateway' => [
            'tableName' => 'gibbonMedicalAllergy',
            'primaryKey' => 'gibbonMedicalAllergyID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonMedicalAllergy.allergenName', 'gibbonMedicalAllergy.notes'],
        ],
        'Gibbon\\Module\\MedicalTracking\\Domain\\MedicationGateway' => [
            'tableName' => 'gibbonMedicalMedication',
            'primaryKey' => 'gibbonMedicalMedicationID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonMedicalMedication.medicationName', 'gibbonMedicalMedication.notes'],
        ],
        'Gibbon\\Module\\MedicalTracking\\Domain\\AccommodationPlanGateway' => [
            'tableName' => 'gibbonMedicalAccommodationPlan',
            'primaryKey' => 'gibbonMedicalAccommodationPlanID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonMedicalAccommodationPlan.planName', 'gibbonMedicalAccommodationPlan.notes'],
        ],
        'Gibbon\\Module\\MedicalTracking\\Domain\\MedicalAlertGateway' => [
            'tableName' => 'gibbonMedicalAlert',
            'primaryKey' => 'gibbonMedicalAlertID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonMedicalAlert.title', 'gibbonMedicalAlert.description'],
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
    // ALLERGY GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function allergyGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\AllergyGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryAllergies',
            'queryActiveAllergies',
            'queryAllergiesByPerson',
            'selectAllergiesByPerson',
            'selectFoodAllergiesByPerson',
            'getAllergySummary',
            'selectChildrenWithSevereAllergies',
            'selectChildrenWithEpiPen',
            'selectUnverifiedAllergies',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('AllergyGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function allergyGatewayHasAddAllergyMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\AllergyGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('addAllergy'));

        $method = $reflection->getMethod('addAllergy');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(5, count($params), 'addAllergy should have at least 5 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('allergenName', $params[1]->getName());
        $this->assertEquals('allergenType', $params[2]->getName());
        $this->assertEquals('severity', $params[3]->getName());
        $this->assertEquals('createdByID', $params[4]->getName());
    }

    /**
     * @test
     */
    public function allergyGatewayHasVerifyAllergyMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\AllergyGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('verifyAllergy'));

        $method = $reflection->getMethod('verifyAllergy');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'verifyAllergy should have 2 parameters');
        $this->assertEquals('gibbonMedicalAllergyID', $params[0]->getName());
        $this->assertEquals('verifiedByID', $params[1]->getName());
    }

    /**
     * @test
     */
    public function allergyGatewayHasCheckAllergenMatchMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\AllergyGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('checkAllergenMatch'),
            'AllergyGateway should have checkAllergenMatch method for meal integration'
        );

        $method = $reflection->getMethod('checkAllergenMatch');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'checkAllergenMatch should have 2 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('allergenName', $params[1]->getName());
    }

    /**
     * @test
     */
    public function allergyGatewayHasDeactivateMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\AllergyGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('deactivateAllergy'),
            'AllergyGateway should have deactivateAllergy method for soft delete'
        );
    }

    // =========================================================================
    // MEDICATION GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function medicationGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicationGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryMedications',
            'queryActiveMedications',
            'queryMedicationsByPerson',
            'queryExpiringMedications',
            'selectMedicationsByPerson',
            'selectStaffAdministeredMedicationsByPerson',
            'getMedicationSummary',
            'selectExpiredMedications',
            'selectMedicationsExpiringSoon',
            'selectChildrenWithStaffMedications',
            'selectUnverifiedMedications',
            'selectMedicationsAwaitingConsent',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('MedicationGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function medicationGatewayHasAddMedicationMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('addMedication'));

        $method = $reflection->getMethod('addMedication');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(6, count($params), 'addMedication should have at least 6 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('medicationName', $params[1]->getName());
        $this->assertEquals('medicationType', $params[2]->getName());
        $this->assertEquals('dosage', $params[3]->getName());
        $this->assertEquals('frequency', $params[4]->getName());
        $this->assertEquals('createdByID', $params[5]->getName());
    }

    /**
     * @test
     */
    public function medicationGatewayHasVerifyMedicationMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('verifyMedication'));

        $method = $reflection->getMethod('verifyMedication');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'verifyMedication should have 2 parameters');
        $this->assertEquals('gibbonMedicalMedicationID', $params[0]->getName());
        $this->assertEquals('verifiedByID', $params[1]->getName());
    }

    /**
     * @test
     */
    public function medicationGatewayHasConsentAndExpirationMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('recordParentConsent'),
            'MedicationGateway should have recordParentConsent method'
        );

        $this->assertTrue(
            $reflection->hasMethod('updateExpirationDate'),
            'MedicationGateway should have updateExpirationDate method'
        );

        $this->assertTrue(
            $reflection->hasMethod('getExpirationMonitoringSummary'),
            'MedicationGateway should have getExpirationMonitoringSummary method'
        );
    }

    /**
     * @test
     */
    public function medicationGatewayHasDeactivateMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('deactivateMedication'),
            'MedicationGateway should have deactivateMedication method for soft delete'
        );
    }

    // =========================================================================
    // ACCOMMODATION PLAN GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function accommodationPlanGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\AccommodationPlanGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryAccommodationPlans',
            'queryActiveAccommodationPlans',
            'queryAccommodationPlansByPerson',
            'queryDietarySubstitutions',
            'queryEmergencyPlans',
            'queryStaffTrainingRecords',
            'selectAccommodationPlansByPerson',
            'getAccommodationPlanWithDetails',
            'selectDietarySubstitutionsByPerson',
            'selectEmergencyPlansByPerson',
            'selectTrainingRecordsByStaff',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('AccommodationPlanGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function accommodationPlanGatewayHasAddAccommodationPlanMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\AccommodationPlanGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('addAccommodationPlan'));

        $method = $reflection->getMethod('addAccommodationPlan');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(4, count($params), 'addAccommodationPlan should have at least 4 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('planName', $params[1]->getName());
        $this->assertEquals('planType', $params[2]->getName());
        $this->assertEquals('createdByID', $params[3]->getName());
    }

    /**
     * @test
     */
    public function accommodationPlanGatewayHasApproveMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\AccommodationPlanGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('approveAccommodationPlan'),
            'AccommodationPlanGateway should have approveAccommodationPlan method'
        );

        $method = $reflection->getMethod('approveAccommodationPlan');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'approveAccommodationPlan should have 2 parameters');
    }

    /**
     * @test
     */
    public function accommodationPlanGatewayHasDietarySubstitutionMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\AccommodationPlanGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('addDietarySubstitution'),
            'AccommodationPlanGateway should have addDietarySubstitution method'
        );

        $this->assertTrue(
            $reflection->hasMethod('updateDietarySubstitution'),
            'AccommodationPlanGateway should have updateDietarySubstitution method'
        );

        $this->assertTrue(
            $reflection->hasMethod('selectDietarySubstitutionsByPlan'),
            'AccommodationPlanGateway should have selectDietarySubstitutionsByPlan method'
        );
    }

    /**
     * @test
     */
    public function accommodationPlanGatewayHasEmergencyPlanMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\AccommodationPlanGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('addEmergencyPlan'),
            'AccommodationPlanGateway should have addEmergencyPlan method'
        );

        $this->assertTrue(
            $reflection->hasMethod('updateEmergencyPlan'),
            'AccommodationPlanGateway should have updateEmergencyPlan method'
        );

        $this->assertTrue(
            $reflection->hasMethod('getEmergencyPlanWithDetails'),
            'AccommodationPlanGateway should have getEmergencyPlanWithDetails method'
        );
    }

    /**
     * @test
     */
    public function accommodationPlanGatewayHasStaffTrainingMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\AccommodationPlanGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('addTrainingRecord'),
            'AccommodationPlanGateway should have addTrainingRecord method'
        );

        $this->assertTrue(
            $reflection->hasMethod('verifyTrainingRecord'),
            'AccommodationPlanGateway should have verifyTrainingRecord method'
        );

        $this->assertTrue(
            $reflection->hasMethod('selectExpiringTrainingRecords'),
            'AccommodationPlanGateway should have selectExpiringTrainingRecords method'
        );

        $this->assertTrue(
            $reflection->hasMethod('selectExpiredTrainingRecords'),
            'AccommodationPlanGateway should have selectExpiredTrainingRecords method'
        );

        $this->assertTrue(
            $reflection->hasMethod('selectStaffByTrainingType'),
            'AccommodationPlanGateway should have selectStaffByTrainingType method'
        );
    }

    // =========================================================================
    // MEDICAL ALERT GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function medicalAlertGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicalAlertGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryAlerts',
            'queryActiveAlerts',
            'queryAlertsByPerson',
            'selectAlertsByPerson',
            'selectDashboardAlertsByPerson',
            'selectCheckInAlerts',
            'selectCriticalAlerts',
            'selectAlertsByType',
            'selectChildrenWithAlerts',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('MedicalAlertGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function medicalAlertGatewayHasCreateAlertMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicalAlertGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('createAlert'));

        $method = $reflection->getMethod('createAlert');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(6, count($params), 'createAlert should have at least 6 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('alertType', $params[1]->getName());
        $this->assertEquals('alertLevel', $params[2]->getName());
        $this->assertEquals('title', $params[3]->getName());
        $this->assertEquals('description', $params[4]->getName());
        $this->assertEquals('createdByID', $params[5]->getName());
    }

    /**
     * @test
     */
    public function medicalAlertGatewayHasAllergenDetectionMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicalAlertGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('detectAllergenExposure'),
            'MedicalAlertGateway should have detectAllergenExposure method for meal integration'
        );

        $this->assertTrue(
            $reflection->hasMethod('createAllergenExposureAlert'),
            'MedicalAlertGateway should have createAllergenExposureAlert method'
        );

        $this->assertTrue(
            $reflection->hasMethod('triggerMealAllergenAlert'),
            'MedicalAlertGateway should have triggerMealAllergenAlert method'
        );
    }

    /**
     * @test
     */
    public function medicalAlertGatewayHasNotificationIntegrationMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicalAlertGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('queueAlertNotifications'),
            'MedicalAlertGateway should have queueAlertNotifications method'
        );

        $this->assertTrue(
            $reflection->hasMethod('getStaffToNotify'),
            'MedicalAlertGateway should have getStaffToNotify method'
        );
    }

    /**
     * @test
     */
    public function medicalAlertGatewayHasDeactivateMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicalAlertGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('deactivateAlert'),
            'MedicalAlertGateway should have deactivateAlert method'
        );

        $this->assertTrue(
            $reflection->hasMethod('deactivateAlertsByAllergyID'),
            'MedicalAlertGateway should have deactivateAlertsByAllergyID method'
        );

        $this->assertTrue(
            $reflection->hasMethod('deactivateAlertsByMedicationID'),
            'MedicalAlertGateway should have deactivateAlertsByMedicationID method'
        );
    }

    /**
     * @test
     */
    public function medicalAlertGatewayHasCreateAllergyAlertMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicalAlertGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('createAllergyAlert'),
            'MedicalAlertGateway should have createAllergyAlert method'
        );

        $method = $reflection->getMethod('createAllergyAlert');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'createAllergyAlert should have 2 parameters');
        $this->assertEquals('allergy', $params[0]->getName());
        $this->assertEquals('createdByID', $params[1]->getName());
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
            'Allergy Stats by Person' => [
                'Gibbon\\Module\\MedicalTracking\\Domain\\AllergyGateway',
                'getAllergyStatsByPerson',
            ],
            'Allergy Summary' => [
                'Gibbon\\Module\\MedicalTracking\\Domain\\AllergyGateway',
                'getAllergySummary',
            ],
            'Medication Stats by Person' => [
                'Gibbon\\Module\\MedicalTracking\\Domain\\MedicationGateway',
                'getMedicationStatsByPerson',
            ],
            'Medication Summary' => [
                'Gibbon\\Module\\MedicalTracking\\Domain\\MedicationGateway',
                'getMedicationSummary',
            ],
            'Accommodation Plan Summary' => [
                'Gibbon\\Module\\MedicalTracking\\Domain\\AccommodationPlanGateway',
                'getAccommodationPlanSummary',
            ],
            'Training Summary by Type' => [
                'Gibbon\\Module\\MedicalTracking\\Domain\\AccommodationPlanGateway',
                'getTrainingSummaryByType',
            ],
            'Alert Statistics' => [
                'Gibbon\\Module\\MedicalTracking\\Domain\\MedicalAlertGateway',
                'getAlertStatistics',
            ],
            'Alert Stats by Person' => [
                'Gibbon\\Module\\MedicalTracking\\Domain\\MedicalAlertGateway',
                'getAlertStatsByPerson',
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
            'AllergyGateway' => ['Gibbon\\Module\\MedicalTracking\\Domain\\AllergyGateway'],
            'MedicationGateway' => ['Gibbon\\Module\\MedicalTracking\\Domain\\MedicationGateway'],
            'AccommodationPlanGateway' => ['Gibbon\\Module\\MedicalTracking\\Domain\\AccommodationPlanGateway'],
            'MedicalAlertGateway' => ['Gibbon\\Module\\MedicalTracking\\Domain\\MedicalAlertGateway'],
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

        // Medical Tracking tables should have 'Medical' in the name
        $this->assertStringContainsString(
            'Medical',
            $tableName,
            sprintf('Table name %s should contain "Medical" for Medical Tracking module', $tableName)
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
            ['gibbonMedicalAllergy'],
            ['gibbonMedicalMedication'],
            ['gibbonMedicalAccommodationPlan'],
            ['gibbonMedicalAlert'],
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
            ['gibbonMedicalAllergyID', 'gibbonMedicalAllergy'],
            ['gibbonMedicalMedicationID', 'gibbonMedicalMedication'],
            ['gibbonMedicalAccommodationPlanID', 'gibbonMedicalAccommodationPlan'],
            ['gibbonMedicalAlertID', 'gibbonMedicalAlert'],
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
            'AllergyGateway' => 'queryAllergies',
            'MedicationGateway' => 'queryMedications',
            'AccommodationPlanGateway' => 'queryAccommodationPlans',
            'MedicalAlertGateway' => 'queryAlerts',
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

    // =========================================================================
    // RELATED RECORD LOOKUP TESTS
    // =========================================================================

    /**
     * @test
     */
    public function medicalAlertGatewayHasRelatedRecordLookupMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicalAlertGateway';
        $reflection = new ReflectionClass($className);

        $lookupMethods = [
            'getAlertByAllergyID',
            'getAlertByMedicationID',
            'getAlertByPlanID',
            'getAlertWithDetails',
        ];

        foreach ($lookupMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('MedicalAlertGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function allergyGatewayHasRelatedRecordLookupMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\AllergyGateway';
        $reflection = new ReflectionClass($className);

        $lookupMethods = [
            'getAllergyByPersonAndAllergen',
            'getAllergyWithDetails',
            'selectCommonAllergens',
        ];

        foreach ($lookupMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('AllergyGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function medicationGatewayHasRelatedRecordLookupMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalTracking\\Domain\\MedicationGateway';
        $reflection = new ReflectionClass($className);

        $lookupMethods = [
            'getMedicationByPersonAndName',
            'getMedicationWithDetails',
        ];

        foreach ($lookupMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('MedicationGateway should have method %s', $method)
            );
        }
    }
}
