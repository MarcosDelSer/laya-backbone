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

namespace Gibbon\Module\StaffManagement\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Unit tests for Staff Management Domain Gateways.
 *
 * These tests verify that all Staff Management gateway classes are properly configured
 * with correct table names, primary keys, and searchable columns. Also validates
 * method signatures and expected method availability.
 *
 * @covers \Gibbon\Module\StaffManagement\Domain\StaffProfileGateway
 * @covers \Gibbon\Module\StaffManagement\Domain\CertificationGateway
 */
class GatewayTest extends TestCase
{
    /**
     * Gateway class configurations to test.
     *
     * @var array
     */
    private static $gatewayConfigs = [
        'Gibbon\\Module\\StaffManagement\\Domain\\StaffProfileGateway' => [
            'tableName' => 'gibbonStaffProfile',
            'primaryKey' => 'gibbonStaffProfileID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonStaffProfile.employeeNumber', 'gibbonStaffProfile.position', 'gibbonStaffProfile.department'],
        ],
        'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway' => [
            'tableName' => 'gibbonStaffCertification',
            'primaryKey' => 'gibbonStaffCertificationID',
            'searchableColumns' => ['gibbonPerson.preferredName', 'gibbonPerson.surname', 'gibbonStaffCertification.certificationName', 'gibbonStaffCertification.issuingOrganization', 'gibbonStaffCertification.certificateNumber'],
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
    // STAFF PROFILE GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function staffProfileGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\StaffProfileGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryStaffProfiles',
            'queryActiveStaff',
            'getStaffProfileByID',
            'getStaffProfileByPersonID',
            'selectActiveStaffList',
            'selectStaffByQualificationLevel',
            'selectStaffWithProbationEnding',
            'getStaffSummaryStatistics',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('StaffProfileGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function staffProfileGatewayQueryStaffProfilesAcceptsCriteria(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\StaffProfileGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('queryStaffProfiles'));

        $method = $reflection->getMethod('queryStaffProfiles');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params), 'queryStaffProfiles should have at least 1 parameter');
        $this->assertEquals('criteria', $params[0]->getName());
    }

    /**
     * @test
     */
    public function staffProfileGatewayHasGetByIDMethod(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\StaffProfileGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('getStaffProfileByID'));

        $method = $reflection->getMethod('getStaffProfileByID');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getStaffProfileByID should have 1 parameter');
        $this->assertEquals('gibbonStaffProfileID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function staffProfileGatewayHasGetByPersonIDMethod(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\StaffProfileGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('getStaffProfileByPersonID'));

        $method = $reflection->getMethod('getStaffProfileByPersonID');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getStaffProfileByPersonID should have 1 parameter');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function staffProfileGatewayHasQualificationLevelMethod(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\StaffProfileGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('selectStaffByQualificationLevel'));

        $method = $reflection->getMethod('selectStaffByQualificationLevel');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'selectStaffByQualificationLevel should have 1 parameter');
        $this->assertEquals('qualificationLevel', $params[0]->getName());
    }

    /**
     * @test
     */
    public function staffProfileGatewayHasProbationEndingMethod(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\StaffProfileGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('selectStaffWithProbationEnding'));

        $method = $reflection->getMethod('selectStaffWithProbationEnding');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'selectStaffWithProbationEnding should have 1 parameter');
        $this->assertEquals('endDate', $params[0]->getName());
    }

    /**
     * @test
     */
    public function staffProfileGatewayHasHasStaffProfileMethod(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\StaffProfileGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('hasStaffProfile'),
            'StaffProfileGateway should have hasStaffProfile method'
        );

        $method = $reflection->getMethod('hasStaffProfile');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'hasStaffProfile should have 1 parameter');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function staffProfileGatewayHasReportingMethods(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\StaffProfileGateway';
        $reflection = new ReflectionClass($className);

        $reportingMethods = [
            'selectStaffCountByPosition',
            'selectStaffCountByDepartment',
            'selectStaffHiredInDateRange',
            'selectStaffTerminatedInDateRange',
            'selectUniquePositions',
            'selectUniqueDepartments',
        ];

        foreach ($reportingMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('StaffProfileGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function staffProfileGatewayHasDateRangeQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\StaffProfileGateway';
        $reflection = new ReflectionClass($className);

        // Test selectStaffHiredInDateRange
        $this->assertTrue($reflection->hasMethod('selectStaffHiredInDateRange'));
        $method = $reflection->getMethod('selectStaffHiredInDateRange');
        $params = $method->getParameters();
        $this->assertCount(2, $params, 'selectStaffHiredInDateRange should have 2 parameters');
        $this->assertEquals('dateStart', $params[0]->getName());
        $this->assertEquals('dateEnd', $params[1]->getName());

        // Test selectStaffTerminatedInDateRange
        $this->assertTrue($reflection->hasMethod('selectStaffTerminatedInDateRange'));
        $method = $reflection->getMethod('selectStaffTerminatedInDateRange');
        $params = $method->getParameters();
        $this->assertCount(2, $params, 'selectStaffTerminatedInDateRange should have 2 parameters');
        $this->assertEquals('dateStart', $params[0]->getName());
        $this->assertEquals('dateEnd', $params[1]->getName());
    }

    // =========================================================================
    // CERTIFICATION GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function certificationGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryCertifications',
            'queryCertificationsByPerson',
            'queryCertificationsExpiringSoon',
            'getCertificationByID',
            'selectExpiredCertifications',
            'selectCertificationsNeedingRenewalReminder',
            'getCertificationSummaryStatistics',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('CertificationGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function certificationGatewayQueryCertificationsAcceptsCriteria(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('queryCertifications'));

        $method = $reflection->getMethod('queryCertifications');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params), 'queryCertifications should have at least 1 parameter');
        $this->assertEquals('criteria', $params[0]->getName());
    }

    /**
     * @test
     */
    public function certificationGatewayQueryByPersonAcceptsCriteriaAndPersonID(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('queryCertificationsByPerson'));

        $method = $reflection->getMethod('queryCertificationsByPerson');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'queryCertificationsByPerson should have 2 parameters');
        $this->assertEquals('criteria', $params[0]->getName());
        $this->assertEquals('gibbonPersonID', $params[1]->getName());
    }

    /**
     * @test
     */
    public function certificationGatewayQueryExpiringSoonAcceptsDateRange(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('queryCertificationsExpiringSoon'));

        $method = $reflection->getMethod('queryCertificationsExpiringSoon');
        $params = $method->getParameters();

        $this->assertCount(3, $params, 'queryCertificationsExpiringSoon should have 3 parameters');
        $this->assertEquals('criteria', $params[0]->getName());
        $this->assertEquals('dateStart', $params[1]->getName());
        $this->assertEquals('dateEnd', $params[2]->getName());
    }

    /**
     * @test
     */
    public function certificationGatewayHasGetByIDMethod(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('getCertificationByID'));

        $method = $reflection->getMethod('getCertificationByID');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getCertificationByID should have 1 parameter');
        $this->assertEquals('gibbonStaffCertificationID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function certificationGatewayHasRenewalReminderMethod(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('selectCertificationsNeedingRenewalReminder'));

        $method = $reflection->getMethod('selectCertificationsNeedingRenewalReminder');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'selectCertificationsNeedingRenewalReminder should have 1 parameter');
        $this->assertEquals('warningDays', $params[0]->getName());
    }

    /**
     * @test
     */
    public function certificationGatewayHasMarkReminderSentMethod(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('markReminderSent'),
            'CertificationGateway should have markReminderSent method'
        );

        $method = $reflection->getMethod('markReminderSent');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'markReminderSent should have 1 parameter');
        $this->assertEquals('gibbonStaffCertificationID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function certificationGatewayHasUpdateExpiredMethod(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('updateExpiredCertifications'),
            'CertificationGateway should have updateExpiredCertifications method'
        );

        $method = $reflection->getMethod('updateExpiredCertifications');
        $params = $method->getParameters();

        $this->assertCount(0, $params, 'updateExpiredCertifications should have no parameters');
    }

    /**
     * @test
     */
    public function certificationGatewayHasComplianceCheckMethods(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway';
        $reflection = new ReflectionClass($className);

        $complianceMethods = [
            'selectStaffMissingRequiredCertifications',
            'hasValidCertification',
            'selectValidCertificationsByPerson',
        ];

        foreach ($complianceMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('CertificationGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function certificationGatewayHasValidCertificationMethod(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('hasValidCertification'));

        $method = $reflection->getMethod('hasValidCertification');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'hasValidCertification should have 2 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('certificationType', $params[1]->getName());
    }

    /**
     * @test
     */
    public function certificationGatewayHasValidCertificationsByPersonMethod(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('selectValidCertificationsByPerson'));

        $method = $reflection->getMethod('selectValidCertificationsByPerson');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'selectValidCertificationsByPerson should have 1 parameter');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function certificationGatewayHasReportingMethods(): void
    {
        $className = 'Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway';
        $reflection = new ReflectionClass($className);

        $reportingMethods = [
            'selectCertificationCountByType',
            'selectUniqueIssuingOrganizations',
        ];

        foreach ($reportingMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('CertificationGateway should have method %s', $method)
            );
        }
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
            'StaffProfileGateway' => ['Gibbon\\Module\\StaffManagement\\Domain\\StaffProfileGateway'],
            'CertificationGateway' => ['Gibbon\\Module\\StaffManagement\\Domain\\CertificationGateway'],
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

        // Staff Management tables should have 'Staff' in the name
        $this->assertStringContainsString(
            'Staff',
            $tableName,
            sprintf('Table name %s should contain "Staff" for Staff Management module', $tableName)
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
            ['gibbonStaffProfile'],
            ['gibbonStaffCertification'],
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
            ['gibbonStaffProfileID', 'gibbonStaffProfile'],
            ['gibbonStaffCertificationID', 'gibbonStaffCertification'],
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
            'StaffProfileGateway' => 'queryStaffProfiles',
            'CertificationGateway' => 'queryCertifications',
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
