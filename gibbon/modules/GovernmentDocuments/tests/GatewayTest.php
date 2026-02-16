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

namespace Gibbon\Module\GovernmentDocuments\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Unit tests for Government Documents Domain Gateway.
 *
 * These tests verify that the GovernmentDocumentGateway class is properly configured
 * with correct table names, primary keys, and searchable columns. Also validates
 * method signatures and expected method availability.
 *
 * @covers \Gibbon\Module\GovernmentDocuments\Domain\GovernmentDocumentGateway
 */
class GatewayTest extends TestCase
{
    /**
     * Gateway class name for testing.
     *
     * @var string
     */
    private static $gatewayClass = 'Gibbon\\Module\\GovernmentDocuments\\Domain\\GovernmentDocumentGateway';

    /**
     * Gateway configuration for validation.
     *
     * @var array
     */
    private static $gatewayConfig = [
        'tableName' => 'gibbonGovernmentDocument',
        'primaryKey' => 'gibbonGovernmentDocumentID',
        'searchableColumns' => [
            'gibbonGovernmentDocument.documentNumber',
            'gibbonGovernmentDocument.notes',
            'gibbonPerson.preferredName',
            'gibbonPerson.surname',
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

    // =========================================================================
    // GATEWAY CONFIGURATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayHasCorrectTableName(): void
    {
        $tableName = $this->getStaticProperty(self::$gatewayClass, 'tableName');
        $this->assertEquals(
            self::$gatewayConfig['tableName'],
            $tableName,
            sprintf('GovernmentDocumentGateway should have table name "%s"', self::$gatewayConfig['tableName'])
        );
    }

    /**
     * @test
     */
    public function gatewayHasCorrectPrimaryKey(): void
    {
        $primaryKey = $this->getStaticProperty(self::$gatewayClass, 'primaryKey');
        $this->assertEquals(
            self::$gatewayConfig['primaryKey'],
            $primaryKey,
            sprintf('GovernmentDocumentGateway should have primary key "%s"', self::$gatewayConfig['primaryKey'])
        );
    }

    /**
     * @test
     */
    public function gatewayHasCorrectSearchableColumns(): void
    {
        $searchableColumns = $this->getStaticProperty(self::$gatewayClass, 'searchableColumns');
        $this->assertEquals(
            self::$gatewayConfig['searchableColumns'],
            $searchableColumns,
            'GovernmentDocumentGateway should have correct searchable columns'
        );
    }

    // =========================================================================
    // GATEWAY CLASS INHERITANCE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayExtendsQueryableGateway(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);
        $parentClass = $reflection->getParentClass();

        $this->assertNotFalse($parentClass, 'GovernmentDocumentGateway should extend a parent class');
        $this->assertEquals(
            'Gibbon\\Domain\\QueryableGateway',
            $parentClass->getName(),
            'GovernmentDocumentGateway should extend QueryableGateway'
        );
    }

    /**
     * @test
     */
    public function gatewayUsesTableAwareTrait(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);
        $traits = $reflection->getTraitNames();

        $this->assertContains(
            'Gibbon\\Domain\\Traits\\TableAware',
            $traits,
            'GovernmentDocumentGateway should use TableAware trait'
        );
    }

    // =========================================================================
    // TABLE NAME AND PRIMARY KEY FORMAT VALIDATION
    // =========================================================================

    /**
     * @test
     */
    public function tableNameFollowsGibbonConvention(): void
    {
        $tableName = self::$gatewayConfig['tableName'];

        // Gibbon tables should start with 'gibbon' prefix
        $this->assertStringStartsWith(
            'gibbon',
            $tableName,
            sprintf('Table name %s should start with "gibbon" prefix', $tableName)
        );

        // Government Documents tables should have 'GovernmentDocument' in the name
        $this->assertStringContainsString(
            'GovernmentDocument',
            $tableName,
            sprintf('Table name %s should contain "GovernmentDocument" for Government Documents module', $tableName)
        );
    }

    /**
     * @test
     */
    public function primaryKeyFollowsGibbonConvention(): void
    {
        $primaryKey = self::$gatewayConfig['primaryKey'];
        $tableName = self::$gatewayConfig['tableName'];

        // Primary key should be tableName + 'ID'
        $expectedPrimaryKey = $tableName . 'ID';
        $this->assertEquals(
            $expectedPrimaryKey,
            $primaryKey,
            sprintf('Primary key should be %s', $expectedPrimaryKey)
        );
    }

    // =========================================================================
    // DOCUMENT QUERY METHODS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayHasRequiredQueryMethods(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $requiredMethods = [
            'queryDocuments',
            'queryDocumentsByFamily',
            'queryExpiringDocuments',
            'queryMissingDocuments',
            'selectExpiringDocuments',
            'selectMissingDocumentsByPerson',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('GovernmentDocumentGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function queryDocumentsMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('queryDocuments'));

        $method = $reflection->getMethod('queryDocuments');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'queryDocuments should have 2 parameters');
        $this->assertEquals('criteria', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
    }

    /**
     * @test
     */
    public function queryDocumentsByFamilyMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('queryDocumentsByFamily'));

        $method = $reflection->getMethod('queryDocumentsByFamily');
        $params = $method->getParameters();

        $this->assertCount(3, $params, 'queryDocumentsByFamily should have 3 parameters');
        $this->assertEquals('criteria', $params[0]->getName());
        $this->assertEquals('gibbonFamilyID', $params[1]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[2]->getName());
    }

    /**
     * @test
     */
    public function queryExpiringDocumentsMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('queryExpiringDocuments'));

        $method = $reflection->getMethod('queryExpiringDocuments');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'queryExpiringDocuments should have at least 2 parameters');
        $this->assertEquals('criteria', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
        $this->assertEquals('daysUntilExpiry', $params[2]->getName());
    }

    /**
     * @test
     */
    public function queryMissingDocumentsMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('queryMissingDocuments'));

        $method = $reflection->getMethod('queryMissingDocuments');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'queryMissingDocuments should have at least 2 parameters');
        $this->assertEquals('criteria', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
    }

    // =========================================================================
    // CHECKLIST METHODS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayHasChecklistMethods(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $checklistMethods = [
            'getChecklistByFamily',
            'selectChecklistSummaryAllFamilies',
        ];

        foreach ($checklistMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('GovernmentDocumentGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function getChecklistByFamilyMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('getChecklistByFamily'));

        $method = $reflection->getMethod('getChecklistByFamily');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'getChecklistByFamily should have 2 parameters');
        $this->assertEquals('gibbonFamilyID', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
    }

    /**
     * @test
     */
    public function selectChecklistSummaryAllFamiliesMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('selectChecklistSummaryAllFamilies'));

        $method = $reflection->getMethod('selectChecklistSummaryAllFamilies');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'selectChecklistSummaryAllFamilies should have 1 parameter');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
    }

    // =========================================================================
    // DOCUMENT CRUD METHODS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayHasCRUDMethods(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $crudMethods = [
            'getDocumentByID',
            'getDocumentByPersonAndType',
            'insertDocument',
            'updateDocument',
        ];

        foreach ($crudMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('GovernmentDocumentGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function getDocumentByIDMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('getDocumentByID'));

        $method = $reflection->getMethod('getDocumentByID');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getDocumentByID should have 1 parameter');
        $this->assertEquals('gibbonGovernmentDocumentID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function getDocumentByPersonAndTypeMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('getDocumentByPersonAndType'));

        $method = $reflection->getMethod('getDocumentByPersonAndType');
        $params = $method->getParameters();

        $this->assertCount(3, $params, 'getDocumentByPersonAndType should have 3 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('gibbonGovernmentDocumentTypeID', $params[1]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[2]->getName());
    }

    /**
     * @test
     */
    public function insertDocumentMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('insertDocument'));

        $method = $reflection->getMethod('insertDocument');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'insertDocument should have 1 parameter');
        $this->assertEquals('data', $params[0]->getName());
    }

    /**
     * @test
     */
    public function updateDocumentMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('updateDocument'));

        $method = $reflection->getMethod('updateDocument');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'updateDocument should have 2 parameters');
        $this->assertEquals('gibbonGovernmentDocumentID', $params[0]->getName());
        $this->assertEquals('data', $params[1]->getName());
    }

    // =========================================================================
    // VERIFICATION METHODS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayHasVerificationMethods(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $verificationMethods = [
            'updateVerificationStatus',
            'markAsExpired',
            'selectExpiredDocumentsToUpdate',
        ];

        foreach ($verificationMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('GovernmentDocumentGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function updateVerificationStatusMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('updateVerificationStatus'));

        $method = $reflection->getMethod('updateVerificationStatus');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(3, count($params), 'updateVerificationStatus should have at least 3 parameters');
        $this->assertEquals('gibbonGovernmentDocumentID', $params[0]->getName());
        $this->assertEquals('status', $params[1]->getName());
        $this->assertEquals('verifiedByID', $params[2]->getName());
    }

    /**
     * @test
     */
    public function markAsExpiredMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('markAsExpired'));

        $method = $reflection->getMethod('markAsExpired');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'markAsExpired should have 1 parameter');
        $this->assertEquals('gibbonGovernmentDocumentID', $params[0]->getName());
    }

    // =========================================================================
    // AUDIT LOG METHODS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayHasAuditLogMethods(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $logMethods = [
            'insertLog',
            'selectLogByDocument',
        ];

        foreach ($logMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('GovernmentDocumentGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function insertLogMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('insertLog'));

        $method = $reflection->getMethod('insertLog');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(3, count($params), 'insertLog should have at least 3 parameters');
        $this->assertEquals('gibbonGovernmentDocumentID', $params[0]->getName());
        $this->assertEquals('gibbonPersonID', $params[1]->getName());
        $this->assertEquals('action', $params[2]->getName());
    }

    /**
     * @test
     */
    public function selectLogByDocumentMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('selectLogByDocument'));

        $method = $reflection->getMethod('selectLogByDocument');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'selectLogByDocument should have 1 parameter');
        $this->assertEquals('gibbonGovernmentDocumentID', $params[0]->getName());
    }

    // =========================================================================
    // DOCUMENT TYPE METHODS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayHasDocumentTypeMethods(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $typeMethods = [
            'selectActiveDocumentTypes',
            'getDocumentTypeByID',
            'getDocumentTypeByName',
        ];

        foreach ($typeMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('GovernmentDocumentGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function getDocumentTypeByIDMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('getDocumentTypeByID'));

        $method = $reflection->getMethod('getDocumentTypeByID');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getDocumentTypeByID should have 1 parameter');
        $this->assertEquals('gibbonGovernmentDocumentTypeID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function getDocumentTypeByNameMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('getDocumentTypeByName'));

        $method = $reflection->getMethod('getDocumentTypeByName');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getDocumentTypeByName should have 1 parameter');
        $this->assertEquals('name', $params[0]->getName());
    }

    // =========================================================================
    // STATISTICS METHODS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayHasStatisticsMethods(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $statsMethods = [
            'getDocumentStatistics',
            'getComplianceRate',
        ];

        foreach ($statsMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('GovernmentDocumentGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function getDocumentStatisticsMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('getDocumentStatistics'));

        $method = $reflection->getMethod('getDocumentStatistics');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getDocumentStatistics should have 1 parameter');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function getComplianceRateMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('getComplianceRate'));

        $method = $reflection->getMethod('getComplianceRate');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getComplianceRate should have 1 parameter');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
    }

    // =========================================================================
    // SERVICE AGREEMENT INTEGRATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayHasCriticalDocumentMethods(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $criticalMethods = [
            'hasCriticalDocuments',
            'getCriticalDocumentTypes',
        ];

        foreach ($criticalMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('GovernmentDocumentGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function hasCriticalDocumentsMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('hasCriticalDocuments'));

        $method = $reflection->getMethod('hasCriticalDocuments');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'hasCriticalDocuments should have 2 parameters');
        $this->assertEquals('gibbonFamilyID', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
    }

    /**
     * @test
     */
    public function getCriticalDocumentTypesMethodReturnsExpectedStructure(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $this->assertTrue($reflection->hasMethod('getCriticalDocumentTypes'));

        $method = $reflection->getMethod('getCriticalDocumentTypes');
        $params = $method->getParameters();

        // getCriticalDocumentTypes takes no parameters
        $this->assertCount(0, $params, 'getCriticalDocumentTypes should have 0 parameters');
    }

    // =========================================================================
    // FIRST PARAMETER TYPE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayQueryMethodAcceptsQueryCriteria(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $queryMethods = [
            'queryDocuments',
            'queryDocumentsByFamily',
            'queryExpiringDocuments',
            'queryMissingDocuments',
        ];

        foreach ($queryMethods as $methodName) {
            $this->assertTrue($reflection->hasMethod($methodName));

            $method = $reflection->getMethod($methodName);
            $params = $method->getParameters();

            $this->assertGreaterThan(0, count($params), sprintf('%s should have parameters', $methodName));

            // First parameter should be QueryCriteria
            $firstParam = $params[0];
            $this->assertEquals(
                'criteria',
                $firstParam->getName(),
                sprintf('First parameter of %s should be named "criteria"', $methodName)
            );
        }
    }

    // =========================================================================
    // PRIVATE METHOD EXISTENCE TEST
    // =========================================================================

    /**
     * @test
     */
    public function gatewayHasPrivateHelperMethods(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        // hasVerifiedDocument should be a private helper method
        $this->assertTrue(
            $reflection->hasMethod('hasVerifiedDocument'),
            'GovernmentDocumentGateway should have private hasVerifiedDocument method'
        );

        $method = $reflection->getMethod('hasVerifiedDocument');
        $this->assertTrue(
            $method->isPrivate(),
            'hasVerifiedDocument should be a private method'
        );
    }

    /**
     * @test
     */
    public function hasVerifiedDocumentMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::$gatewayClass);

        $method = $reflection->getMethod('hasVerifiedDocument');
        $method->setAccessible(true);
        $params = $method->getParameters();

        $this->assertCount(3, $params, 'hasVerifiedDocument should have 3 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('documentTypeNames', $params[1]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[2]->getName());
    }
}
