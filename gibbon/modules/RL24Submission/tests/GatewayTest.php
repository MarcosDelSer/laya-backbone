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

namespace Gibbon\Module\RL24Submission\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Unit tests for RL-24 Submission Domain Gateways.
 *
 * These tests verify that all RL-24 Submission gateway classes are properly configured
 * with correct table names, primary keys, and searchable columns. Also validates
 * method signatures and expected method availability.
 *
 * @covers \Gibbon\Module\RL24Submission\Domain\RL24TransmissionGateway
 * @covers \Gibbon\Module\RL24Submission\Domain\RL24SlipGateway
 * @covers \Gibbon\Module\RL24Submission\Domain\RL24EligibilityGateway
 */
class GatewayTest extends TestCase
{
    /**
     * Gateway class configurations to test.
     *
     * @var array
     */
    private static $gatewayConfigs = [
        'Gibbon\\Module\\RL24Submission\\Domain\\RL24TransmissionGateway' => [
            'tableName' => 'gibbonRL24Transmission',
            'primaryKey' => 'gibbonRL24TransmissionID',
            'searchableColumns' => ['gibbonRL24Transmission.fileName', 'gibbonRL24Transmission.providerName', 'gibbonRL24Transmission.confirmationNumber', 'gibbonRL24Transmission.notes'],
        ],
        'Gibbon\\Module\\RL24Submission\\Domain\\RL24SlipGateway' => [
            'tableName' => 'gibbonRL24Slip',
            'primaryKey' => 'gibbonRL24SlipID',
            'searchableColumns' => ['gibbonRL24Slip.parentFirstName', 'gibbonRL24Slip.parentLastName', 'gibbonRL24Slip.childFirstName', 'gibbonRL24Slip.childLastName', 'gibbonRL24Slip.notes'],
        ],
        'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway' => [
            'tableName' => 'gibbonRL24Eligibility',
            'primaryKey' => 'gibbonRL24EligibilityID',
            'searchableColumns' => ['gibbonRL24Eligibility.parentFirstName', 'gibbonRL24Eligibility.parentLastName', 'gibbonRL24Eligibility.childFirstName', 'gibbonRL24Eligibility.childLastName', 'gibbonRL24Eligibility.notes'],
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
    // RL24 TRANSMISSION GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function transmissionGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24TransmissionGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryTransmissions',
            'queryTransmissionsByTaxYear',
            'selectTransmissionsByStatus',
            'selectTransmissionsByTaxYear',
            'getTransmissionByID',
            'getTransmissionByYearAndSequence',
            'getNextSequenceNumber',
            'getTransmissionSummaryBySchoolYear',
            'selectDistinctTaxYears',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('RL24TransmissionGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function transmissionGatewayHasCreateTransmissionMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24TransmissionGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('createTransmission'));

        $method = $reflection->getMethod('createTransmission');
        $params = $method->getParameters();

        $this->assertCount(4, $params, 'createTransmission should have 4 parameters');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
        $this->assertEquals('taxYear', $params[1]->getName());
        $this->assertEquals('generatedByID', $params[2]->getName());
        $this->assertEquals('providerInfo', $params[3]->getName());
    }

    /**
     * @test
     */
    public function transmissionGatewayHasUpdateStatusMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24TransmissionGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('updateTransmissionStatus'));

        $method = $reflection->getMethod('updateTransmissionStatus');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'updateTransmissionStatus should have at least 2 parameters');
        $this->assertEquals('gibbonRL24TransmissionID', $params[0]->getName());
        $this->assertEquals('status', $params[1]->getName());
    }

    /**
     * @test
     */
    public function transmissionGatewayHasSummaryTotalsMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24TransmissionGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('updateSummaryTotals'));

        $method = $reflection->getMethod('updateSummaryTotals');
        $params = $method->getParameters();

        $this->assertCount(5, $params, 'updateSummaryTotals should have 5 parameters');
        $this->assertEquals('gibbonRL24TransmissionID', $params[0]->getName());
        $this->assertEquals('totalSlips', $params[1]->getName());
        $this->assertEquals('totalAmountCase11', $params[2]->getName());
        $this->assertEquals('totalAmountCase12', $params[3]->getName());
        $this->assertEquals('totalDays', $params[4]->getName());
    }

    /**
     * @test
     */
    public function transmissionGatewayHasXmlFileMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24TransmissionGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('updateXmlFile'));

        $method = $reflection->getMethod('updateXmlFile');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(3, count($params), 'updateXmlFile should have at least 3 parameters');
        $this->assertEquals('gibbonRL24TransmissionID', $params[0]->getName());
        $this->assertEquals('xmlFilePath', $params[1]->getName());
        $this->assertEquals('xmlValidated', $params[2]->getName());
    }

    /**
     * @test
     */
    public function transmissionGatewayHasSubmissionMethods(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24TransmissionGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('recordSubmission'),
            'RL24TransmissionGateway should have recordSubmission method'
        );

        $this->assertTrue(
            $reflection->hasMethod('recordAcceptance'),
            'RL24TransmissionGateway should have recordAcceptance method'
        );

        $this->assertTrue(
            $reflection->hasMethod('recordRejection'),
            'RL24TransmissionGateway should have recordRejection method'
        );

        $this->assertTrue(
            $reflection->hasMethod('cancelTransmission'),
            'RL24TransmissionGateway should have cancelTransmission method'
        );
    }

    // =========================================================================
    // RL24 SLIP GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function slipGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24SlipGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'querySlips',
            'querySlipsByTaxYear',
            'querySlipsByChild',
            'selectSlipsByTransmission',
            'selectSlipsByTransmissionAndStatus',
            'getSlipByID',
            'getNextSlipNumber',
            'getSlipSummaryByTransmission',
            'getSlipSummaryByTaxYear',
            'getIncludedSlipTotals',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('RL24SlipGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function slipGatewayHasCreateSlipMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24SlipGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('createSlip'));

        $method = $reflection->getMethod('createSlip');
        $params = $method->getParameters();

        $this->assertCount(4, $params, 'createSlip should have 4 parameters');
        $this->assertEquals('gibbonRL24TransmissionID', $params[0]->getName());
        $this->assertEquals('gibbonPersonIDChild', $params[1]->getName());
        $this->assertEquals('gibbonPersonIDParent', $params[2]->getName());
        $this->assertEquals('slipData', $params[3]->getName());
    }

    /**
     * @test
     */
    public function slipGatewayHasUpdateSlipStatusMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24SlipGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('updateSlipStatus'));

        $method = $reflection->getMethod('updateSlipStatus');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'updateSlipStatus should have at least 2 parameters');
        $this->assertEquals('gibbonRL24SlipID', $params[0]->getName());
        $this->assertEquals('status', $params[1]->getName());
    }

    /**
     * @test
     */
    public function slipGatewayHasUpdateAmountsMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24SlipGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('updateSlipAmounts'));

        $method = $reflection->getMethod('updateSlipAmounts');
        $params = $method->getParameters();

        $this->assertCount(6, $params, 'updateSlipAmounts should have 6 parameters');
        $this->assertEquals('gibbonRL24SlipID', $params[0]->getName());
        $this->assertEquals('totalDays', $params[1]->getName());
        $this->assertEquals('case11Amount', $params[2]->getName());
        $this->assertEquals('case12Amount', $params[3]->getName());
        $this->assertEquals('case13Amount', $params[4]->getName());
        $this->assertEquals('case14Amount', $params[5]->getName());
    }

    /**
     * @test
     */
    public function slipGatewayHasAmendmentMethods(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24SlipGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('createAmendedSlip'),
            'RL24SlipGateway should have createAmendedSlip method'
        );

        $this->assertTrue(
            $reflection->hasMethod('createCancelledSlip'),
            'RL24SlipGateway should have createCancelledSlip method'
        );

        $this->assertTrue(
            $reflection->hasMethod('selectAmendmentHistory'),
            'RL24SlipGateway should have selectAmendmentHistory method'
        );
    }

    /**
     * @test
     */
    public function slipGatewayHasExistenceCheckMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24SlipGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('slipExistsForChildAndYear'),
            'RL24SlipGateway should have slipExistsForChildAndYear method'
        );

        $method = $reflection->getMethod('slipExistsForChildAndYear');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'slipExistsForChildAndYear should have at least 2 parameters');
    }

    /**
     * @test
     */
    public function slipGatewayHasIncludeDraftSlipsMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24SlipGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('includeDraftSlips'),
            'RL24SlipGateway should have includeDraftSlips method'
        );

        $this->assertTrue(
            $reflection->hasMethod('deleteDraftSlip'),
            'RL24SlipGateway should have deleteDraftSlip method'
        );
    }

    // =========================================================================
    // RL24 ELIGIBILITY GATEWAY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function eligibilityGatewayHasRequiredQueryMethods(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $requiredMethods = [
            'queryEligibility',
            'queryEligibilityByFormYear',
            'queryEligibilityByChild',
            'selectEligibilityByStatus',
            'selectApprovedEligibilityByFormYear',
            'getEligibilityByID',
            'getEligibilityByChildAndYear',
            'getEligibilitySummaryBySchoolYear',
            'getEligibilitySummaryByFormYear',
            'getStatusCounts',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('RL24EligibilityGateway should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function eligibilityGatewayHasCreateEligibilityMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('createEligibility'));

        $method = $reflection->getMethod('createEligibility');
        $params = $method->getParameters();

        $this->assertCount(5, $params, 'createEligibility should have 5 parameters');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
        $this->assertEquals('gibbonPersonIDChild', $params[1]->getName());
        $this->assertEquals('gibbonPersonIDParent', $params[2]->getName());
        $this->assertEquals('createdByID', $params[3]->getName());
        $this->assertEquals('formData', $params[4]->getName());
    }

    /**
     * @test
     */
    public function eligibilityGatewayHasUpdateApprovalStatusMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('updateApprovalStatus'));

        $method = $reflection->getMethod('updateApprovalStatus');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'updateApprovalStatus should have at least 2 parameters');
        $this->assertEquals('gibbonRL24EligibilityID', $params[0]->getName());
        $this->assertEquals('approvalStatus', $params[1]->getName());
    }

    /**
     * @test
     */
    public function eligibilityGatewayHasDocumentsAndSignatureMethods(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('updateDocumentsComplete'),
            'RL24EligibilityGateway should have updateDocumentsComplete method'
        );

        $this->assertTrue(
            $reflection->hasMethod('updateSignatureStatus'),
            'RL24EligibilityGateway should have updateSignatureStatus method'
        );
    }

    /**
     * @test
     */
    public function eligibilityGatewayHasInfoUpdateMethods(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('updateParentInfo'),
            'RL24EligibilityGateway should have updateParentInfo method'
        );

        $this->assertTrue(
            $reflection->hasMethod('updateChildInfo'),
            'RL24EligibilityGateway should have updateChildInfo method'
        );

        $this->assertTrue(
            $reflection->hasMethod('updateServicePeriod'),
            'RL24EligibilityGateway should have updateServicePeriod method'
        );
    }

    /**
     * @test
     */
    public function eligibilityGatewayHasExistenceCheckMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('eligibilityExistsForChildAndYear'),
            'RL24EligibilityGateway should have eligibilityExistsForChildAndYear method'
        );

        $method = $reflection->getMethod('eligibilityExistsForChildAndYear');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'eligibilityExistsForChildAndYear should have at least 2 parameters');
    }

    /**
     * @test
     */
    public function eligibilityGatewayHasChildSelectionMethods(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('selectChildrenNeedingEligibility'),
            'RL24EligibilityGateway should have selectChildrenNeedingEligibility method'
        );

        $this->assertTrue(
            $reflection->hasMethod('selectChildrenWithApprovedEligibility'),
            'RL24EligibilityGateway should have selectChildrenWithApprovedEligibility method'
        );
    }

    /**
     * @test
     */
    public function eligibilityGatewayHasDeleteMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('deleteEligibility'),
            'RL24EligibilityGateway should have deleteEligibility method'
        );
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
            'RL24TransmissionGateway' => ['Gibbon\\Module\\RL24Submission\\Domain\\RL24TransmissionGateway'],
            'RL24SlipGateway' => ['Gibbon\\Module\\RL24Submission\\Domain\\RL24SlipGateway'],
            'RL24EligibilityGateway' => ['Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway'],
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

        // RL-24 tables should have 'RL24' in the name
        $this->assertStringContainsString(
            'RL24',
            $tableName,
            sprintf('Table name %s should contain "RL24" for RL-24 Submission module', $tableName)
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
            ['gibbonRL24Transmission'],
            ['gibbonRL24Slip'],
            ['gibbonRL24Eligibility'],
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
            ['gibbonRL24TransmissionID', 'gibbonRL24Transmission'],
            ['gibbonRL24SlipID', 'gibbonRL24Slip'],
            ['gibbonRL24EligibilityID', 'gibbonRL24Eligibility'],
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
            'RL24TransmissionGateway' => 'queryTransmissions',
            'RL24SlipGateway' => 'querySlips',
            'RL24EligibilityGateway' => 'queryEligibility',
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
    // SEARCHABLE COLUMNS VALIDATION
    // =========================================================================

    /**
     * @test
     * @dataProvider searchableColumnsProvider
     */
    public function searchableColumnsHaveCorrectFormat(string $column): void
    {
        // Searchable columns should be in format: table.column
        $this->assertStringContainsString(
            '.',
            $column,
            sprintf('Searchable column "%s" should be in format table.column', $column)
        );

        // Should start with gibbon prefix
        $this->assertStringStartsWith(
            'gibbon',
            $column,
            sprintf('Searchable column "%s" should reference a table starting with "gibbon"', $column)
        );
    }

    /**
     * Data provider for searchable columns.
     *
     * @return array
     */
    public static function searchableColumnsProvider(): array
    {
        $columns = [];
        foreach (self::$gatewayConfigs as $config) {
            foreach ($config['searchableColumns'] as $column) {
                $columns[$column] = [$column];
            }
        }
        return $columns;
    }
}
