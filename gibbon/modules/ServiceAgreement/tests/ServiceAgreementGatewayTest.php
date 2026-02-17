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

namespace Gibbon\Module\ServiceAgreement\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Unit tests for Service Agreement Domain Gateway.
 *
 * These tests verify that the ServiceAgreementGateway class is properly configured
 * with correct table name, primary key, and searchable columns. Also validates
 * method signatures and expected method availability for Quebec FO-0659 service
 * agreement management.
 *
 * @covers \Gibbon\Module\ServiceAgreement\Domain\ServiceAgreementGateway
 */
class ServiceAgreementGatewayTest extends TestCase
{
    /**
     * Gateway class name constant.
     *
     * @var string
     */
    private const GATEWAY_CLASS = 'Gibbon\\Module\\ServiceAgreement\\Domain\\ServiceAgreementGateway';

    /**
     * Gateway configuration for testing.
     *
     * @var array
     */
    private static $gatewayConfig = [
        'tableName' => 'gibbonServiceAgreement',
        'primaryKey' => 'gibbonServiceAgreementID',
        'searchableColumns' => [
            'gibbonServiceAgreement.agreementNumber',
            'gibbonServiceAgreement.childName',
            'gibbonServiceAgreement.parentName',
            'gibbonServiceAgreement.providerName',
            'child.preferredName',
            'child.surname',
            'parent.preferredName',
            'parent.surname',
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
        $tableName = $this->getStaticProperty(self::GATEWAY_CLASS, 'tableName');
        $this->assertEquals(
            self::$gatewayConfig['tableName'],
            $tableName,
            sprintf('ServiceAgreementGateway should have table name "%s"', self::$gatewayConfig['tableName'])
        );
    }

    /**
     * @test
     */
    public function gatewayHasCorrectPrimaryKey(): void
    {
        $primaryKey = $this->getStaticProperty(self::GATEWAY_CLASS, 'primaryKey');
        $this->assertEquals(
            self::$gatewayConfig['primaryKey'],
            $primaryKey,
            sprintf('ServiceAgreementGateway should have primary key "%s"', self::$gatewayConfig['primaryKey'])
        );
    }

    /**
     * @test
     */
    public function gatewayHasCorrectSearchableColumns(): void
    {
        $searchableColumns = $this->getStaticProperty(self::GATEWAY_CLASS, 'searchableColumns');
        $this->assertEquals(
            self::$gatewayConfig['searchableColumns'],
            $searchableColumns,
            'ServiceAgreementGateway should have correct searchable columns'
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
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);
        $parentClass = $reflection->getParentClass();

        $this->assertNotFalse($parentClass, 'ServiceAgreementGateway should extend a parent class');
        $this->assertEquals(
            'Gibbon\\Domain\\QueryableGateway',
            $parentClass->getName(),
            'ServiceAgreementGateway should extend QueryableGateway'
        );
    }

    /**
     * @test
     */
    public function gatewayUsesTableAwareTrait(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);
        $traits = $reflection->getTraitNames();

        $this->assertContains(
            'Gibbon\\Domain\\Traits\\TableAware',
            $traits,
            'ServiceAgreementGateway should use TableAware trait'
        );
    }

    // =========================================================================
    // QUERY METHOD TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayHasRequiredQueryMethods(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $requiredMethods = [
            'queryServiceAgreements',
            'queryServiceAgreementsByChild',
            'queryServiceAgreementsByParent',
            'queryPendingAgreements',
            'getAgreementWithDetails',
            'getActiveAgreementByChild',
            'getAgreementByNumber',
            'getAgreementSummaryBySchoolYear',
            'selectExpiringAgreements',
            'selectChildrenWithoutAgreement',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('ServiceAgreementGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function queryServiceAgreementsMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('queryServiceAgreements'));

        $method = $reflection->getMethod('queryServiceAgreements');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'queryServiceAgreements should have 2 parameters');
        $this->assertEquals('criteria', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
    }

    /**
     * @test
     */
    public function queryServiceAgreementsByChildMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('queryServiceAgreementsByChild'));

        $method = $reflection->getMethod('queryServiceAgreementsByChild');
        $params = $method->getParameters();

        $this->assertCount(3, $params, 'queryServiceAgreementsByChild should have 3 parameters');
        $this->assertEquals('criteria', $params[0]->getName());
        $this->assertEquals('gibbonPersonIDChild', $params[1]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[2]->getName());
    }

    /**
     * @test
     */
    public function queryServiceAgreementsByParentMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('queryServiceAgreementsByParent'));

        $method = $reflection->getMethod('queryServiceAgreementsByParent');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'queryServiceAgreementsByParent should have at least 2 parameters');
        $this->assertEquals('criteria', $params[0]->getName());
        $this->assertEquals('gibbonPersonIDParent', $params[1]->getName());
    }

    /**
     * @test
     */
    public function queryPendingAgreementsMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('queryPendingAgreements'));

        $method = $reflection->getMethod('queryPendingAgreements');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'queryPendingAgreements should have 2 parameters');
        $this->assertEquals('criteria', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
    }

    // =========================================================================
    // DETAIL RETRIEVAL METHOD TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getAgreementWithDetailsMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('getAgreementWithDetails'));

        $method = $reflection->getMethod('getAgreementWithDetails');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getAgreementWithDetails should have 1 parameter');
        $this->assertEquals('gibbonServiceAgreementID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function getActiveAgreementByChildMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('getActiveAgreementByChild'));

        $method = $reflection->getMethod('getActiveAgreementByChild');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'getActiveAgreementByChild should have 2 parameters');
        $this->assertEquals('gibbonPersonIDChild', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
    }

    /**
     * @test
     */
    public function getAgreementByNumberMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('getAgreementByNumber'));

        $method = $reflection->getMethod('getAgreementByNumber');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getAgreementByNumber should have 1 parameter');
        $this->assertEquals('agreementNumber', $params[0]->getName());
    }

    // =========================================================================
    // STATISTICS AND SUMMARY METHOD TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getAgreementSummaryBySchoolYearMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('getAgreementSummaryBySchoolYear'));

        $method = $reflection->getMethod('getAgreementSummaryBySchoolYear');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getAgreementSummaryBySchoolYear should have 1 parameter');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function countAgreementsByStatusMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('countAgreementsByStatus'));

        $method = $reflection->getMethod('countAgreementsByStatus');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'countAgreementsByStatus should have 1 parameter');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function getPaymentStatsByAgreementMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('getPaymentStatsByAgreement'));

        $method = $reflection->getMethod('getPaymentStatsByAgreement');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getPaymentStatsByAgreement should have 1 parameter');
        $this->assertEquals('gibbonServiceAgreementID', $params[0]->getName());
    }

    // =========================================================================
    // SELECT METHOD TESTS
    // =========================================================================

    /**
     * @test
     */
    public function selectExpiringAgreementsMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('selectExpiringAgreements'));

        $method = $reflection->getMethod('selectExpiringAgreements');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params), 'selectExpiringAgreements should have at least 1 parameter');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function selectChildrenWithoutAgreementMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('selectChildrenWithoutAgreement'));

        $method = $reflection->getMethod('selectChildrenWithoutAgreement');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'selectChildrenWithoutAgreement should have 1 parameter');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
    }

    // =========================================================================
    // STATUS UPDATE METHOD TESTS
    // =========================================================================

    /**
     * @test
     */
    public function gatewayHasStatusUpdateMethods(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $statusMethods = [
            'updateStatus',
            'markSignaturesComplete',
            'markConsumerProtectionAcknowledged',
        ];

        foreach ($statusMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('ServiceAgreementGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function updateStatusMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('updateStatus'));

        $method = $reflection->getMethod('updateStatus');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'updateStatus should have 2 parameters');
        $this->assertEquals('gibbonServiceAgreementID', $params[0]->getName());
        $this->assertEquals('status', $params[1]->getName());
    }

    /**
     * @test
     */
    public function markSignaturesCompleteMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('markSignaturesComplete'));

        $method = $reflection->getMethod('markSignaturesComplete');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'markSignaturesComplete should have 1 parameter');
        $this->assertEquals('gibbonServiceAgreementID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function markConsumerProtectionAcknowledgedMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('markConsumerProtectionAcknowledged'));

        $method = $reflection->getMethod('markConsumerProtectionAcknowledged');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'markConsumerProtectionAcknowledged should have 1 parameter');
        $this->assertEquals('gibbonServiceAgreementID', $params[0]->getName());
    }

    // =========================================================================
    // UTILITY METHOD TESTS
    // =========================================================================

    /**
     * @test
     */
    public function generateAgreementNumberMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('generateAgreementNumber'));

        $method = $reflection->getMethod('generateAgreementNumber');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'generateAgreementNumber should have 1 parameter');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function hasExistingAgreementMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('hasExistingAgreement'));

        $method = $reflection->getMethod('hasExistingAgreement');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'hasExistingAgreement should have at least 2 parameters');
        $this->assertEquals('gibbonPersonIDChild', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
    }

    // =========================================================================
    // TABLE NAME AND PRIMARY KEY FORMAT VALIDATION
    // =========================================================================

    /**
     * @test
     */
    public function tableNameFollowsGibbonConvention(): void
    {
        $tableName = $this->getStaticProperty(self::GATEWAY_CLASS, 'tableName');

        // Gibbon tables should start with 'gibbon' prefix
        $this->assertStringStartsWith(
            'gibbon',
            $tableName,
            sprintf('Table name %s should start with "gibbon" prefix', $tableName)
        );

        // Service Agreement tables should have 'ServiceAgreement' in the name
        $this->assertStringContainsString(
            'ServiceAgreement',
            $tableName,
            sprintf('Table name %s should contain "ServiceAgreement" for ServiceAgreement module', $tableName)
        );
    }

    /**
     * @test
     */
    public function primaryKeyFollowsGibbonConvention(): void
    {
        $primaryKey = $this->getStaticProperty(self::GATEWAY_CLASS, 'primaryKey');
        $tableName = $this->getStaticProperty(self::GATEWAY_CLASS, 'tableName');

        // Primary key should be tableName + 'ID'
        $expectedPrimaryKey = $tableName . 'ID';
        $this->assertEquals(
            $expectedPrimaryKey,
            $primaryKey,
            sprintf('Primary key should be %s', $expectedPrimaryKey)
        );
    }

    // =========================================================================
    // FILTER RULES TESTS
    // =========================================================================

    /**
     * @test
     */
    public function queryMethodAcceptsQueryCriteria(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $this->assertTrue($reflection->hasMethod('queryServiceAgreements'));

        $method = $reflection->getMethod('queryServiceAgreements');
        $params = $method->getParameters();

        $this->assertGreaterThan(0, count($params), 'queryServiceAgreements should have parameters');

        // First parameter should be QueryCriteria
        $firstParam = $params[0];
        $this->assertEquals(
            'criteria',
            $firstParam->getName(),
            'First parameter of queryServiceAgreements should be named "criteria"'
        );
    }

    // =========================================================================
    // SEARCHABLE COLUMNS VALIDATION
    // =========================================================================

    /**
     * @test
     */
    public function searchableColumnsIncludeAgreementNumber(): void
    {
        $searchableColumns = $this->getStaticProperty(self::GATEWAY_CLASS, 'searchableColumns');

        $this->assertContains(
            'gibbonServiceAgreement.agreementNumber',
            $searchableColumns,
            'Searchable columns should include agreement number'
        );
    }

    /**
     * @test
     */
    public function searchableColumnsIncludePersonNames(): void
    {
        $searchableColumns = $this->getStaticProperty(self::GATEWAY_CLASS, 'searchableColumns');

        $this->assertContains(
            'child.preferredName',
            $searchableColumns,
            'Searchable columns should include child preferred name'
        );

        $this->assertContains(
            'child.surname',
            $searchableColumns,
            'Searchable columns should include child surname'
        );

        $this->assertContains(
            'parent.preferredName',
            $searchableColumns,
            'Searchable columns should include parent preferred name'
        );

        $this->assertContains(
            'parent.surname',
            $searchableColumns,
            'Searchable columns should include parent surname'
        );
    }

    /**
     * @test
     */
    public function searchableColumnsIncludeProviderName(): void
    {
        $searchableColumns = $this->getStaticProperty(self::GATEWAY_CLASS, 'searchableColumns');

        $this->assertContains(
            'gibbonServiceAgreement.providerName',
            $searchableColumns,
            'Searchable columns should include provider name'
        );
    }

    // =========================================================================
    // METHOD VISIBILITY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function publicMethodsAreAccessible(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $publicMethods = [
            'queryServiceAgreements',
            'queryServiceAgreementsByChild',
            'queryServiceAgreementsByParent',
            'queryPendingAgreements',
            'getAgreementWithDetails',
            'getActiveAgreementByChild',
            'getAgreementByNumber',
            'getAgreementSummaryBySchoolYear',
            'selectExpiringAgreements',
            'selectChildrenWithoutAgreement',
            'updateStatus',
            'markSignaturesComplete',
            'markConsumerProtectionAcknowledged',
            'generateAgreementNumber',
            'getPaymentStatsByAgreement',
            'countAgreementsByStatus',
            'hasExistingAgreement',
        ];

        foreach ($publicMethods as $methodName) {
            $method = $reflection->getMethod($methodName);
            $this->assertTrue(
                $method->isPublic(),
                sprintf('Method %s should be public', $methodName)
            );
        }
    }

    // =========================================================================
    // OPTIONAL PARAMETER TESTS
    // =========================================================================

    /**
     * @test
     */
    public function selectExpiringAgreementsHasOptionalDaysParameter(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $method = $reflection->getMethod('selectExpiringAgreements');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'selectExpiringAgreements should have 2 parameters');

        // Second parameter should be optional with default value
        $daysParam = $params[1];
        $this->assertEquals('daysUntilExpiration', $daysParam->getName());
        $this->assertTrue(
            $daysParam->isDefaultValueAvailable(),
            'daysUntilExpiration parameter should have a default value'
        );
        $this->assertEquals(
            30,
            $daysParam->getDefaultValue(),
            'daysUntilExpiration should default to 30'
        );
    }

    /**
     * @test
     */
    public function hasExistingAgreementHasOptionalExcludeParameter(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $method = $reflection->getMethod('hasExistingAgreement');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(3, count($params), 'hasExistingAgreement should have 3 parameters');

        // Third parameter should be optional
        $excludeParam = $params[2];
        $this->assertEquals('excludeAgreementID', $excludeParam->getName());
        $this->assertTrue(
            $excludeParam->isDefaultValueAvailable(),
            'excludeAgreementID parameter should have a default value'
        );
        $this->assertNull(
            $excludeParam->getDefaultValue(),
            'excludeAgreementID should default to null'
        );
    }

    /**
     * @test
     */
    public function queryServiceAgreementsByParentHasOptionalSchoolYearParameter(): void
    {
        $reflection = new ReflectionClass(self::GATEWAY_CLASS);

        $method = $reflection->getMethod('queryServiceAgreementsByParent');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(3, count($params), 'queryServiceAgreementsByParent should have 3 parameters');

        // Third parameter should be optional
        $yearParam = $params[2];
        $this->assertEquals('gibbonSchoolYearID', $yearParam->getName());
        $this->assertTrue(
            $yearParam->isDefaultValueAvailable(),
            'gibbonSchoolYearID parameter should have a default value'
        );
        $this->assertNull(
            $yearParam->getDefaultValue(),
            'gibbonSchoolYearID should default to null'
        );
    }
}
