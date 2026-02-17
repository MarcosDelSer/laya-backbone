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

namespace Gibbon\Module\MedicalProtocol\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Unit tests for Medical Protocol Domain Gateways.
 *
 * These tests verify that all Medical Protocol gateway classes are properly configured
 * with correct table names, primary keys, and searchable columns. Also validates
 * method signatures and expected method availability.
 *
 * @covers \Gibbon\Module\MedicalProtocol\Domain\ProtocolGateway
 * @covers \Gibbon\Module\MedicalProtocol\Domain\AuthorizationGateway
 * @covers \Gibbon\Module\MedicalProtocol\Domain\AdministrationGateway
 */
class GatewayTest extends TestCase
{
    /**
     * Gateway class configurations to test.
     *
     * @var array
     */
    private static $gatewayConfigs = [
        'Gibbon\\Module\\MedicalProtocol\\Domain\\ProtocolGateway' => [
            'tableName' => 'gibbonMedicalProtocol',
            'primaryKey' => 'gibbonMedicalProtocolID',
            'searchableColumns' => ['gibbonMedicalProtocol.name', 'gibbonMedicalProtocol.formCode', 'gibbonMedicalProtocol.description'],
        ],
        'Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway' => [
            'tableName' => 'gibbonMedicalProtocolAuthorization',
            'primaryKey' => 'gibbonMedicalProtocolAuthorizationID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonMedicalProtocol.name', 'gibbonMedicalProtocol.formCode'],
        ],
        'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway' => [
            'tableName' => 'gibbonMedicalProtocolAdministration',
            'primaryKey' => 'gibbonMedicalProtocolAdministrationID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonMedicalProtocolAdministration.reason', 'gibbonMedicalProtocolAdministration.observations'],
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
    // PROTOCOL GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function protocolGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\ProtocolGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryProtocols',
            'selectActiveProtocols',
            'getProtocolByID',
            'getProtocolByFormCode',
            'getDosageForWeight',
            'selectDosingByProtocol',
            'getConcentrationsByProtocol',
            'getWeightRangeByProtocol',
            'isAgeAllowed',
            'isWeightInRange',
            'getProtocolSummary',
            'calculateDoseByWeight',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('ProtocolGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function protocolGatewayQueryProtocolsMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\ProtocolGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('queryProtocols'));

        $method = $reflection->getMethod('queryProtocols');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'queryProtocols should have 1 parameter');
        $this->assertEquals('criteria', $params[0]->getName());
    }

    /**
     * @test
     */
    public function protocolGatewayGetDosageForWeightMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\ProtocolGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('getDosageForWeight'));

        $method = $reflection->getMethod('getDosageForWeight');
        $params = $method->getParameters();

        $this->assertCount(3, $params, 'getDosageForWeight should have 3 parameters');
        $this->assertEquals('gibbonMedicalProtocolID', $params[0]->getName());
        $this->assertEquals('weightKg', $params[1]->getName());
        $this->assertEquals('concentration', $params[2]->getName());
    }

    /**
     * @test
     */
    public function protocolGatewayIsAgeAllowedMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\ProtocolGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('isAgeAllowed'));

        $method = $reflection->getMethod('isAgeAllowed');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'isAgeAllowed should have 2 parameters');
        $this->assertEquals('gibbonMedicalProtocolID', $params[0]->getName());
        $this->assertEquals('ageMonths', $params[1]->getName());
    }

    /**
     * @test
     */
    public function protocolGatewayIsWeightInRangeMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\ProtocolGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('isWeightInRange'));

        $method = $reflection->getMethod('isWeightInRange');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'isWeightInRange should have 2 parameters');
        $this->assertEquals('gibbonMedicalProtocolID', $params[0]->getName());
        $this->assertEquals('weightKg', $params[1]->getName());
    }

    /**
     * @test
     */
    public function protocolGatewayCalculateDoseByWeightMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\ProtocolGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('calculateDoseByWeight'));

        $method = $reflection->getMethod('calculateDoseByWeight');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params), 'calculateDoseByWeight should have at least 1 parameter');
        $this->assertEquals('weightKg', $params[0]->getName());
    }

    // =========================================================================
    // AUTHORIZATION GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function authorizationGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryAuthorizations',
            'queryAuthorizationsByPerson',
            'getAuthorizationByChildAndProtocol',
            'getActiveAuthorization',
            'createAuthorization',
            'revokeAuthorization',
            'isAuthorized',
            'isWeightExpired',
            'isWeightExpiredByChildAndProtocol',
            'selectExpiringAuthorizations',
            'selectExpiredWeightAuthorizations',
            'updateWeight',
            'getAuthorizationSummary',
            'getAuthorizationSummaryByProtocol',
            'selectActiveAuthorizations',
            'selectAuthorizedChildren',
            'expireAuthorization',
            'updateExpiryDate',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('AuthorizationGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function authorizationGatewayQueryAuthorizationsMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('queryAuthorizations'));

        $method = $reflection->getMethod('queryAuthorizations');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'queryAuthorizations should have 2 parameters');
        $this->assertEquals('criteria', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
    }

    /**
     * @test
     */
    public function authorizationGatewayCreateAuthorizationMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('createAuthorization'));

        $method = $reflection->getMethod('createAuthorization');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(7, count($params), 'createAuthorization should have at least 7 parameters');
        $this->assertEquals('gibbonMedicalProtocolID', $params[0]->getName());
        $this->assertEquals('gibbonPersonID', $params[1]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[2]->getName());
        $this->assertEquals('authorizedByID', $params[3]->getName());
        $this->assertEquals('weightKg', $params[4]->getName());
        $this->assertEquals('signatureData', $params[5]->getName());
        $this->assertEquals('agreementText', $params[6]->getName());
    }

    /**
     * @test
     */
    public function authorizationGatewayRevokeAuthorizationMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('revokeAuthorization'));

        $method = $reflection->getMethod('revokeAuthorization');
        $params = $method->getParameters();

        $this->assertCount(3, $params, 'revokeAuthorization should have 3 parameters');
        $this->assertEquals('gibbonMedicalProtocolAuthorizationID', $params[0]->getName());
        $this->assertEquals('revokedByID', $params[1]->getName());
        $this->assertEquals('reason', $params[2]->getName());
    }

    /**
     * @test
     */
    public function authorizationGatewayIsAuthorizedMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('isAuthorized'));

        $method = $reflection->getMethod('isAuthorized');
        $params = $method->getParameters();

        $this->assertCount(3, $params, 'isAuthorized should have 3 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('gibbonMedicalProtocolID', $params[1]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[2]->getName());
    }

    /**
     * @test
     */
    public function authorizationGatewayIsWeightExpiredMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('isWeightExpired'));

        $method = $reflection->getMethod('isWeightExpired');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'isWeightExpired should have 1 parameter');
        $this->assertEquals('gibbonMedicalProtocolAuthorizationID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function authorizationGatewayUpdateWeightMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('updateWeight'));

        $method = $reflection->getMethod('updateWeight');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'updateWeight should have 2 parameters');
        $this->assertEquals('gibbonMedicalProtocolAuthorizationID', $params[0]->getName());
        $this->assertEquals('weightKg', $params[1]->getName());
    }

    /**
     * @test
     */
    public function authorizationGatewaySelectExpiringAuthorizationsMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('selectExpiringAuthorizations'));

        $method = $reflection->getMethod('selectExpiringAuthorizations');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params), 'selectExpiringAuthorizations should have at least 1 parameter');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function authorizationGatewaySelectAuthorizedChildrenMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('selectAuthorizedChildren'));

        $method = $reflection->getMethod('selectAuthorizedChildren');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'selectAuthorizedChildren should have 2 parameters');
        $this->assertEquals('gibbonMedicalProtocolID', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
    }

    // =========================================================================
    // ADMINISTRATION GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function administrationGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryAdministrations',
            'queryAdministrationsByDate',
            'queryAdministrationsByPerson',
            'selectAdministrationsByPersonAndDate',
            'getLastAdministration',
            'canAdminister',
            'logAdministration',
            'getAdministrationHistory',
            'getAdministrationSummaryByDate',
            'generateComplianceReport',
            'markFollowUpCompleted',
            'markParentNotified',
            'markParentAcknowledged',
            'selectAdministrationsPendingFollowUp',
            'selectAdministrationsPendingNotification',
            'getAdministrationCountLast24Hours',
            'isDailyLimitReached',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('AdministrationGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function administrationGatewayQueryAdministrationsMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('queryAdministrations'));

        $method = $reflection->getMethod('queryAdministrations');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'queryAdministrations should have 2 parameters');
        $this->assertEquals('criteria', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
    }

    /**
     * @test
     */
    public function administrationGatewayCanAdministerMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('canAdminister'));

        $method = $reflection->getMethod('canAdminister');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'canAdminister should have at least 2 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('gibbonMedicalProtocolID', $params[1]->getName());
    }

    /**
     * @test
     */
    public function administrationGatewayLogAdministrationMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('logAdministration'));

        $method = $reflection->getMethod('logAdministration');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'logAdministration should have 1 parameter');
        $this->assertEquals('data', $params[0]->getName());
    }

    /**
     * @test
     */
    public function administrationGatewayIsDailyLimitReachedMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('isDailyLimitReached'));

        $method = $reflection->getMethod('isDailyLimitReached');
        $params = $method->getParameters();

        $this->assertCount(3, $params, 'isDailyLimitReached should have 3 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('gibbonMedicalProtocolID', $params[1]->getName());
        $this->assertEquals('maxDailyDoses', $params[2]->getName());
    }

    /**
     * @test
     */
    public function administrationGatewayGenerateComplianceReportMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('generateComplianceReport'));

        $method = $reflection->getMethod('generateComplianceReport');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(3, count($params), 'generateComplianceReport should have at least 3 parameters');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
        $this->assertEquals('dateStart', $params[1]->getName());
        $this->assertEquals('dateEnd', $params[2]->getName());
    }

    /**
     * @test
     */
    public function administrationGatewayMarkFollowUpCompletedMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('markFollowUpCompleted'));

        $method = $reflection->getMethod('markFollowUpCompleted');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params), 'markFollowUpCompleted should have at least 1 parameter');
        $this->assertEquals('gibbonMedicalProtocolAdministrationID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function administrationGatewayMarkParentNotifiedMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('markParentNotified'));

        $method = $reflection->getMethod('markParentNotified');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'markParentNotified should have 1 parameter');
        $this->assertEquals('gibbonMedicalProtocolAdministrationID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function administrationGatewayMarkParentAcknowledgedMethod(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('markParentAcknowledged'));

        $method = $reflection->getMethod('markParentAcknowledged');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'markParentAcknowledged should have 1 parameter');
        $this->assertEquals('gibbonMedicalProtocolAdministrationID', $params[0]->getName());
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
            'ProtocolGateway' => ['Gibbon\\Module\\MedicalProtocol\\Domain\\ProtocolGateway'],
            'AuthorizationGateway' => ['Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway'],
            'AdministrationGateway' => ['Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway'],
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

        // Medical Protocol tables should have 'MedicalProtocol' in the name
        $this->assertStringContainsString(
            'MedicalProtocol',
            $tableName,
            sprintf('Table name %s should contain "MedicalProtocol" for Medical Protocol module', $tableName)
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
            ['gibbonMedicalProtocol'],
            ['gibbonMedicalProtocolAuthorization'],
            ['gibbonMedicalProtocolAdministration'],
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
            ['gibbonMedicalProtocolID', 'gibbonMedicalProtocol'],
            ['gibbonMedicalProtocolAuthorizationID', 'gibbonMedicalProtocolAuthorization'],
            ['gibbonMedicalProtocolAdministrationID', 'gibbonMedicalProtocolAdministration'],
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
            'ProtocolGateway' => 'queryProtocols',
            'AuthorizationGateway' => 'queryAuthorizations',
            'AdministrationGateway' => 'queryAdministrations',
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
    // QUEBEC MEDICAL PROTOCOL SPECIFIC TESTS
    // =========================================================================

    /**
     * @test
     */
    public function protocolGatewaySupportsWeightBasedDosing(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\ProtocolGateway';
        $reflection = new ReflectionClass($className);

        // Weight-based dosing methods for acetaminophen (FO-0647)
        $dosingMethods = [
            'getDosageForWeight',
            'selectDosingByProtocol',
            'getConcentrationsByProtocol',
            'getWeightRangeByProtocol',
            'isWeightInRange',
            'calculateDoseByWeight',
        ];

        foreach ($dosingMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('ProtocolGateway should have weight-based dosing method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function authorizationGatewaySupportsESignature(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway';
        $reflection = new ReflectionClass($className);

        // createAuthorization should accept signature data
        $method = $reflection->getMethod('createAuthorization');
        $params = $method->getParameters();
        $paramNames = array_map(fn($p) => $p->getName(), $params);

        $this->assertContains('signatureData', $paramNames, 'createAuthorization should accept signatureData parameter');
        $this->assertContains('agreementText', $paramNames, 'createAuthorization should accept agreementText parameter');
    }

    /**
     * @test
     */
    public function authorizationGatewaySupports3MonthWeightExpiry(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AuthorizationGateway';
        $reflection = new ReflectionClass($className);

        // Weight expiry related methods
        $weightExpiryMethods = [
            'isWeightExpired',
            'isWeightExpiredByChildAndProtocol',
            'selectExpiringAuthorizations',
            'selectExpiredWeightAuthorizations',
            'updateWeight',
        ];

        foreach ($weightExpiryMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('AuthorizationGateway should have weight expiry method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function administrationGatewaySupports4HourInterval(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        // Interval checking methods for acetaminophen (4-hour minimum)
        $this->assertTrue(
            $reflection->hasMethod('canAdminister'),
            'AdministrationGateway should have canAdminister method for interval checking'
        );

        $this->assertTrue(
            $reflection->hasMethod('getLastAdministration'),
            'AdministrationGateway should have getLastAdministration method for interval checking'
        );
    }

    /**
     * @test
     */
    public function administrationGatewaySupports5DoseLimit(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        // Daily dose limit methods (5-dose limit for acetaminophen)
        $this->assertTrue(
            $reflection->hasMethod('isDailyLimitReached'),
            'AdministrationGateway should have isDailyLimitReached method'
        );

        $this->assertTrue(
            $reflection->hasMethod('getAdministrationCountLast24Hours'),
            'AdministrationGateway should have getAdministrationCountLast24Hours method'
        );
    }

    /**
     * @test
     */
    public function administrationGatewaySupportsComplianceReporting(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        // Quebec compliance reporting
        $this->assertTrue(
            $reflection->hasMethod('generateComplianceReport'),
            'AdministrationGateway should have generateComplianceReport method for Quebec compliance'
        );

        $method = $reflection->getMethod('generateComplianceReport');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(3, count($params), 'generateComplianceReport should accept date range parameters');
    }

    /**
     * @test
     */
    public function administrationGatewaySupportsFollowUp(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        // Follow-up methods (required 60 minutes after acetaminophen)
        $followUpMethods = [
            'markFollowUpCompleted',
            'selectAdministrationsPendingFollowUp',
        ];

        foreach ($followUpMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('AdministrationGateway should have follow-up method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function administrationGatewaySupportsParentNotification(): void
    {
        $className = 'Gibbon\\Module\\MedicalProtocol\\Domain\\AdministrationGateway';
        $reflection = new ReflectionClass($className);

        // Parent notification methods
        $notificationMethods = [
            'markParentNotified',
            'markParentAcknowledged',
            'selectAdministrationsPendingNotification',
        ];

        foreach ($notificationMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('AdministrationGateway should have parent notification method %s', $method)
            );
        }
    }
}
