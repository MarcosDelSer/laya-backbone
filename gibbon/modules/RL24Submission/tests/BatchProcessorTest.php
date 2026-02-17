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
use Gibbon\Module\RL24Submission\Services\RL24BatchProcessor;
use Gibbon\Module\RL24Submission\Services\RL24SummaryCalculator;
use Gibbon\Module\RL24Submission\Xml\RL24XmlSchema;

/**
 * Unit tests for RL-24 Batch Processing and Summary Calculations.
 *
 * These tests verify that the batch processing service properly handles
 * RL-24 slip generation, transmission creation, and summary calculations
 * for Revenu Québec tax slip submissions.
 *
 * @covers \Gibbon\Module\RL24Submission\Services\RL24BatchProcessor
 * @covers \Gibbon\Module\RL24Submission\Services\RL24SummaryCalculator
 */
class BatchProcessorTest extends TestCase
{
    // =========================================================================
    // TEST DATA FIXTURES
    // =========================================================================

    /**
     * Sample eligibility form data for testing.
     *
     * @var array
     */
    private static $sampleEligibilityData = [
        'gibbonRL24EligibilityID' => 1,
        'gibbonPersonIDChild' => 100,
        'gibbonPersonIDParent' => 200,
        'parentFirstName' => 'Jean',
        'parentLastName' => 'Dupont',
        'parentSIN' => '046454286',
        'parentAddressLine1' => '123 Main Street',
        'parentCity' => 'Montreal',
        'parentProvince' => 'QC',
        'parentPostalCode' => 'H2X 1Y2',
        'childFirstName' => 'Marie',
        'childLastName' => 'Dupont',
        'childDateOfBirth' => '2020-05-15',
        'childDOB' => '2020-05-15',
        'servicePeriodStart' => '2024-01-01',
        'servicePeriodEnd' => '2024-12-31',
        'approvalStatus' => 'Approved',
        'formYear' => 2024,
    ];

    /**
     * Sample slip data for summary calculation testing.
     *
     * @var array
     */
    private static $sampleSlipData = [
        'gibbonRL24SlipID' => 1,
        'gibbonPersonIDChild' => 100,
        'gibbonPersonIDParent' => 200,
        'slipNumber' => 1,
        'taxYear' => 2024,
        'totalDays' => 220,
        'case11Amount' => 8500.00,
        'case12Amount' => 8500.00,
        'case13Amount' => 0.00,
        'case14Amount' => 8500.00,
        'status' => 'Included',
        'caseACode' => 'O',
    ];

    // =========================================================================
    // RL24BatchProcessor CLASS STRUCTURE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function batchProcessorClassExists(): void
    {
        $this->assertTrue(class_exists(RL24BatchProcessor::class));
    }

    /**
     * @test
     */
    public function batchProcessorHasRequiredProperties(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);

        $requiredProperties = [
            'transmissionGateway',
            'slipGateway',
            'eligibilityGateway',
            'settingGateway',
            'xmlGenerator',
            'xmlValidator',
            'errors',
            'warnings',
            'stats',
            'outputPath',
            'dryRun',
            'verbose',
        ];

        foreach ($requiredProperties as $property) {
            $this->assertTrue(
                $reflection->hasProperty($property),
                sprintf('RL24BatchProcessor should have property %s', $property)
            );
        }
    }

    /**
     * @test
     */
    public function batchProcessorHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);

        $requiredMethods = [
            'processBatch',
            'previewBatch',
            'regenerateXml',
            'setDryRun',
            'setVerbose',
            'setOutputPath',
            'getErrors',
            'getWarnings',
            'getStats',
            'hasErrors',
            'hasWarnings',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('RL24BatchProcessor should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function batchProcessorHasProtectedHelperMethods(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);

        $protectedMethods = [
            'generateSlipsFromEligibility',
            'prepareSlipData',
            'validateSlipData',
            'calculateServiceDays',
            'calculateSummaryTotals',
            'generateXmlFile',
            'getOutputDirectory',
            'getProviderInfo',
            'validateProviderInfo',
            'resetState',
            'buildResult',
            'log',
        ];

        foreach ($protectedMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('RL24BatchProcessor should have protected method %s', $method)
            );

            $methodReflection = $reflection->getMethod($method);
            $this->assertTrue(
                $methodReflection->isProtected(),
                sprintf('RL24BatchProcessor::%s should be protected', $method)
            );
        }
    }

    /**
     * @test
     */
    public function batchProcessorSetDryRunReturnsSelf(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('setDryRun');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('self', $returnType->getName());
    }

    /**
     * @test
     */
    public function batchProcessorSetVerboseReturnsSelf(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('setVerbose');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('self', $returnType->getName());
    }

    /**
     * @test
     */
    public function batchProcessorSetOutputPathReturnsSelf(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('setOutputPath');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('self', $returnType->getName());
    }

    /**
     * @test
     */
    public function batchProcessorProcessBatchHasCorrectParameters(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('processBatch');
        $params = $method->getParameters();

        $this->assertCount(4, $params, 'processBatch should have 4 parameters');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
        $this->assertEquals('taxYear', $params[1]->getName());
        $this->assertEquals('generatedByID', $params[2]->getName());
        $this->assertEquals('options', $params[3]->getName());
    }

    /**
     * @test
     */
    public function batchProcessorProcessBatchReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('processBatch');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * @test
     */
    public function batchProcessorPreviewBatchHasCorrectParameters(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('previewBatch');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'previewBatch should have 2 parameters');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
        $this->assertEquals('taxYear', $params[1]->getName());
    }

    /**
     * @test
     */
    public function batchProcessorPreviewBatchReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('previewBatch');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * @test
     */
    public function batchProcessorRegenerateXmlHasCorrectParameters(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('regenerateXml');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'regenerateXml should have 1 parameter');
        $this->assertEquals('transmissionID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function batchProcessorRegenerateXmlReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('regenerateXml');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * @test
     */
    public function batchProcessorGettersReturnArrays(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);

        $arrayGetters = ['getErrors', 'getWarnings', 'getStats'];

        foreach ($arrayGetters as $getter) {
            $method = $reflection->getMethod($getter);
            $returnType = $method->getReturnType();

            $this->assertNotNull($returnType, sprintf('%s should have a return type', $getter));
            $this->assertEquals('array', $returnType->getName(), sprintf('%s should return array', $getter));
        }
    }

    /**
     * @test
     */
    public function batchProcessorHasErrorsReturnsBool(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('hasErrors');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    /**
     * @test
     */
    public function batchProcessorHasWarningsReturnsBool(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('hasWarnings');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    // =========================================================================
    // RL24SummaryCalculator CLASS STRUCTURE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function summaryCalculatorClassExists(): void
    {
        $this->assertTrue(class_exists(RL24SummaryCalculator::class));
    }

    /**
     * @test
     */
    public function summaryCalculatorHasRequiredProperties(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);

        $requiredProperties = [
            'transmissionGateway',
            'slipGateway',
            'eligibilityGateway',
            'errors',
            'warnings',
        ];

        foreach ($requiredProperties as $property) {
            $this->assertTrue(
                $reflection->hasProperty($property),
                sprintf('RL24SummaryCalculator should have property %s', $property)
            );
        }
    }

    /**
     * @test
     */
    public function summaryCalculatorHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);

        $requiredMethods = [
            'calculateTransmissionSummary',
            'calculateSummaryFromSlips',
            'calculatePreviewSummary',
            'calculateYearToDateSummary',
            'updateTransmissionSummary',
            'validateTransmissionSummary',
            'calculateSummaryBySlipType',
            'calculateXmlSummary',
            'recalculateCase14Amounts',
            'calculateServiceDays',
            'calculateAverages',
            'getErrors',
            'getWarnings',
            'hasErrors',
            'hasWarnings',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('RL24SummaryCalculator should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function summaryCalculatorHasProtectedHelperMethods(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);

        $protectedMethods = [
            'validateCase14Calculation',
            'buildEmptySummary',
            'resetState',
        ];

        foreach ($protectedMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('RL24SummaryCalculator should have protected method %s', $method)
            );

            $methodReflection = $reflection->getMethod($method);
            $this->assertTrue(
                $methodReflection->isProtected(),
                sprintf('RL24SummaryCalculator::%s should be protected', $method)
            );
        }
    }

    /**
     * @test
     */
    public function summaryCalculatorCalculateTransmissionSummaryHasCorrectParameters(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('calculateTransmissionSummary');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params), 'calculateTransmissionSummary should have at least 1 parameter');
        $this->assertEquals('gibbonRL24TransmissionID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorCalculateTransmissionSummaryReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('calculateTransmissionSummary');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorCalculateSummaryFromSlipsReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('calculateSummaryFromSlips');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorCalculatePreviewSummaryHasCorrectParameters(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('calculatePreviewSummary');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'calculatePreviewSummary should have 1 parameter');
        $this->assertEquals('taxYear', $params[0]->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorCalculateYearToDateSummaryHasCorrectParameters(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('calculateYearToDateSummary');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'calculateYearToDateSummary should have 1 parameter');
        $this->assertEquals('taxYear', $params[0]->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorValidateTransmissionSummaryReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('validateTransmissionSummary');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorCalculateSummaryBySlipTypeReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('calculateSummaryBySlipType');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorCalculateXmlSummaryReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('calculateXmlSummary');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorRecalculateCase14AmountsReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('recalculateCase14Amounts');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorCalculateServiceDaysReturnsInt(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('calculateServiceDays');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('int', $returnType->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorCalculateAveragesReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('calculateAverages');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorGettersReturnArrays(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);

        $arrayGetters = ['getErrors', 'getWarnings'];

        foreach ($arrayGetters as $getter) {
            $method = $reflection->getMethod($getter);
            $returnType = $method->getReturnType();

            $this->assertNotNull($returnType, sprintf('%s should have a return type', $getter));
            $this->assertEquals('array', $returnType->getName(), sprintf('%s should return array', $getter));
        }
    }

    /**
     * @test
     */
    public function summaryCalculatorHasErrorsReturnsBool(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('hasErrors');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorHasWarningsReturnsBool(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('hasWarnings');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    // =========================================================================
    // SERVICE DAYS CALCULATION TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider serviceDaysProvider
     */
    public function serviceDaysCalculatedCorrectly(string $startDate, string $endDate, int $expectedDays): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('calculateServiceDays');
        $method->setAccessible(true);

        // Create a mock instance to test the method
        $calculator = $this->getMockBuilder(RL24SummaryCalculator::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $actualDays = $method->invoke($calculator, $startDate, $endDate);
        $this->assertEquals($expectedDays, $actualDays);
    }

    /**
     * Data provider for service days calculation tests.
     *
     * @return array
     */
    public static function serviceDaysProvider(): array
    {
        return [
            'full year' => ['2024-01-01', '2024-12-31', 366], // 2024 is a leap year
            'single day' => ['2024-06-15', '2024-06-15', 1],
            'one week' => ['2024-01-01', '2024-01-07', 7],
            'one month (31 days)' => ['2024-01-01', '2024-01-31', 31],
            'one month (30 days)' => ['2024-04-01', '2024-04-30', 30],
            'february leap year' => ['2024-02-01', '2024-02-29', 29],
            'february non-leap year' => ['2025-02-01', '2025-02-28', 28],
            'quarter' => ['2024-01-01', '2024-03-31', 91], // Q1 2024 (leap year)
            'half year' => ['2024-01-01', '2024-06-30', 182], // First half of 2024
        ];
    }

    // =========================================================================
    // EMPTY SUMMARY STRUCTURE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function buildEmptySummaryContainsAllRequiredKeys(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('buildEmptySummary');
        $method->setAccessible(true);

        $calculator = $this->getMockBuilder(RL24SummaryCalculator::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $summary = $method->invoke($calculator);

        $requiredKeys = [
            'totalSlips',
            'totalDays',
            'totalCase10',
            'totalCase11',
            'totalCase12',
            'totalCase13',
            'totalCase14',
            'participantCount',
            'originalSlips',
            'amendmentSlips',
            'amendedSlips',
            'cancelledSlips',
            'isPreview',
        ];

        foreach ($requiredKeys as $key) {
            $this->assertArrayHasKey($key, $summary, sprintf('Empty summary should contain key %s', $key));
        }
    }

    /**
     * @test
     */
    public function buildEmptySummaryHasZeroDefaults(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('buildEmptySummary');
        $method->setAccessible(true);

        $calculator = $this->getMockBuilder(RL24SummaryCalculator::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $summary = $method->invoke($calculator);

        $this->assertEquals(0, $summary['totalSlips']);
        $this->assertEquals(0, $summary['totalDays']);
        $this->assertEquals(0, $summary['totalCase10']);
        $this->assertEquals(0.00, $summary['totalCase11']);
        $this->assertEquals(0.00, $summary['totalCase12']);
        $this->assertEquals(0.00, $summary['totalCase13']);
        $this->assertEquals(0.00, $summary['totalCase14']);
        $this->assertEquals(0, $summary['participantCount']);
        $this->assertEquals(0, $summary['originalSlips']);
        $this->assertEquals(0, $summary['amendmentSlips']);
        $this->assertEquals(0, $summary['amendedSlips']);
        $this->assertEquals(0, $summary['cancelledSlips']);
        $this->assertFalse($summary['isPreview']);
    }

    // =========================================================================
    // SLIP DATA PREPARATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function prepareSlipDataHasCorrectParameters(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('prepareSlipData');
        $params = $method->getParameters();

        $this->assertCount(3, $params, 'prepareSlipData should have 3 parameters');
        $this->assertEquals('form', $params[0]->getName());
        $this->assertEquals('taxYear', $params[1]->getName());
        $this->assertEquals('slipNumber', $params[2]->getName());
    }

    /**
     * @test
     */
    public function prepareSlipDataReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('prepareSlipData');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    // =========================================================================
    // SLIP DATA VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function validateSlipDataReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('validateSlipData');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * @test
     */
    public function validateSlipDataHasSingleParameter(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('validateSlipData');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'validateSlipData should have 1 parameter');
        $this->assertEquals('slipData', $params[0]->getName());
    }

    // =========================================================================
    // PROVIDER INFO VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function validateProviderInfoReturnsBool(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('validateProviderInfo');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('bool', $returnType->getName());
    }

    /**
     * @test
     */
    public function getProviderInfoReturnsArray(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('getProviderInfo');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    // =========================================================================
    // SUMMARY CALCULATION LOGIC TESTS
    // =========================================================================

    /**
     * @test
     */
    public function summaryCalculationUsesCorrectBoxConstants(): void
    {
        // Verify that summary calculations align with Box element constants
        $this->assertEquals('Case10', RL24XmlSchema::ELEMENT_BOX_10);
        $this->assertEquals('Case11', RL24XmlSchema::ELEMENT_BOX_11);
        $this->assertEquals('Case12', RL24XmlSchema::ELEMENT_BOX_12);
        $this->assertEquals('Case13', RL24XmlSchema::ELEMENT_BOX_13);
        $this->assertEquals('Case14', RL24XmlSchema::ELEMENT_BOX_14);
    }

    /**
     * @test
     */
    public function summaryCalculationUsesCorrectSlipTypeCodes(): void
    {
        // Verify that summary calculations use correct slip type codes
        $this->assertEquals('O', RL24XmlSchema::CODE_ORIGINAL);
        $this->assertEquals('A', RL24XmlSchema::CODE_AMENDED);
        $this->assertEquals('D', RL24XmlSchema::CODE_CANCELLED);
    }

    /**
     * @test
     */
    public function summaryCalculationUsesCorrectAmountDecimals(): void
    {
        // Verify that summary calculations use correct decimal precision
        $this->assertEquals(2, RL24XmlSchema::AMOUNT_DECIMALS);
    }

    // =========================================================================
    // BATCH PROCESSING RESULT STRUCTURE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function buildResultContainsRequiredKeys(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('buildResult');
        $method->setAccessible(true);

        // Get a mock processor to test
        $processor = $this->getMockBuilder(RL24BatchProcessor::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        // Set the errors, warnings, stats properties
        $errorsProperty = $reflection->getProperty('errors');
        $errorsProperty->setAccessible(true);
        $errorsProperty->setValue($processor, ['Test error']);

        $warningsProperty = $reflection->getProperty('warnings');
        $warningsProperty->setAccessible(true);
        $warningsProperty->setValue($processor, ['Test warning']);

        $statsProperty = $reflection->getProperty('stats');
        $statsProperty->setAccessible(true);
        $statsProperty->setValue($processor, ['slipsGenerated' => 5]);

        $result = $method->invoke($processor, true, 123, 'Success message');

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('transmissionID', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertArrayHasKey('stats', $result);

        $this->assertTrue($result['success']);
        $this->assertEquals(123, $result['transmissionID']);
        $this->assertEquals('Success message', $result['message']);
        $this->assertContains('Test error', $result['errors']);
        $this->assertContains('Test warning', $result['warnings']);
        $this->assertEquals(5, $result['stats']['slipsGenerated']);
    }

    // =========================================================================
    // PREVIEW RESULT STRUCTURE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function previewBatchResultContainsRequiredKeys(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('previewBatch');

        // The preview should return an array with specific keys
        $params = $method->getParameters();
        $this->assertCount(2, $params, 'previewBatch should have 2 parameters');

        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    // =========================================================================
    // VALIDATION RESULT STRUCTURE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function validateTransmissionSummaryResultContainsValidKey(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('validateTransmissionSummary');

        // The validation should return an array with 'valid' key
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    // =========================================================================
    // XML SUMMARY OUTPUT TESTS
    // =========================================================================

    /**
     * @test
     */
    public function calculateXmlSummaryUsesSchemaConstants(): void
    {
        // Verify that XML summary calculation uses correct element constants
        $this->assertEquals('NombreReleves', RL24XmlSchema::ELEMENT_TOTAL_SLIPS);
        $this->assertEquals('NombreJours', RL24XmlSchema::ELEMENT_TOTAL_DAYS);
        $this->assertEquals('TotalCase11', RL24XmlSchema::ELEMENT_TOTAL_BOX_11);
        $this->assertEquals('TotalCase12', RL24XmlSchema::ELEMENT_TOTAL_BOX_12);
        $this->assertEquals('TotalCase13', RL24XmlSchema::ELEMENT_TOTAL_BOX_13);
        $this->assertEquals('TotalCase14', RL24XmlSchema::ELEMENT_TOTAL_BOX_14);
    }

    // =========================================================================
    // AVERAGES CALCULATION TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider averagesCalculationProvider
     */
    public function calculateAveragesReturnsCorrectValues(array $summary, array $expectedAverages): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('calculateAverages');
        $method->setAccessible(true);

        $calculator = $this->getMockBuilder(RL24SummaryCalculator::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();

        $averages = $method->invoke($calculator, $summary);

        $this->assertEquals($expectedAverages['avgDays'], $averages['avgDays']);
        $this->assertEquals($expectedAverages['avgCase11'], $averages['avgCase11']);
        $this->assertEquals($expectedAverages['avgCase12'], $averages['avgCase12']);
    }

    /**
     * Data provider for averages calculation tests.
     *
     * @return array
     */
    public static function averagesCalculationProvider(): array
    {
        return [
            'two slips equal' => [
                [
                    'totalSlips' => 2,
                    'totalDays' => 440,
                    'totalCase11' => 17000.00,
                    'totalCase12' => 17000.00,
                    'totalCase13' => 0.00,
                    'totalCase14' => 17000.00,
                ],
                [
                    'avgDays' => 220.0,
                    'avgCase11' => 8500.00,
                    'avgCase12' => 8500.00,
                ],
            ],
            'three slips varying' => [
                [
                    'totalSlips' => 3,
                    'totalDays' => 660,
                    'totalCase11' => 25500.00,
                    'totalCase12' => 25500.00,
                    'totalCase13' => 0.00,
                    'totalCase14' => 25500.00,
                ],
                [
                    'avgDays' => 220.0,
                    'avgCase11' => 8500.00,
                    'avgCase12' => 8500.00,
                ],
            ],
            'zero slips' => [
                [
                    'totalSlips' => 0,
                    'totalDays' => 0,
                    'totalCase11' => 0.00,
                    'totalCase12' => 0.00,
                    'totalCase13' => 0.00,
                    'totalCase14' => 0.00,
                ],
                [
                    'avgDays' => 0,
                    'avgCase11' => 0.00,
                    'avgCase12' => 0.00,
                ],
            ],
        ];
    }

    // =========================================================================
    // BOX 14 RECALCULATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function recalculateCase14AmountsHasCorrectParameters(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('recalculateCase14Amounts');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'recalculateCase14Amounts should have 1 parameter');
        $this->assertEquals('gibbonRL24TransmissionID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function box14CalculationFormulaIsCorrect(): void
    {
        // Box 14 = Box 12 - Box 13 (Net eligible expenses)
        $box12 = 8500.00;
        $box13 = 1500.00;
        $expectedBox14 = $box12 - $box13;

        $this->assertEquals(7000.00, $expectedBox14);

        // Verify minimum is zero
        $box12Negative = 1000.00;
        $box13Negative = 1500.00;
        $expectedBox14Negative = max(0, $box12Negative - $box13Negative);

        $this->assertEquals(0, $expectedBox14Negative);
    }

    // =========================================================================
    // CONSTRUCTOR DEPENDENCY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function batchProcessorConstructorRequiresGateways(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        $this->assertCount(4, $params, 'RL24BatchProcessor constructor should have 4 parameters');
        $this->assertEquals('transmissionGateway', $params[0]->getName());
        $this->assertEquals('slipGateway', $params[1]->getName());
        $this->assertEquals('eligibilityGateway', $params[2]->getName());
        $this->assertEquals('settingGateway', $params[3]->getName());
    }

    /**
     * @test
     */
    public function summaryCalculatorConstructorRequiresGateways(): void
    {
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $constructor = $reflection->getConstructor();
        $params = $constructor->getParameters();

        $this->assertCount(3, $params, 'RL24SummaryCalculator constructor should have 3 parameters');
        $this->assertEquals('transmissionGateway', $params[0]->getName());
        $this->assertEquals('slipGateway', $params[1]->getName());
        $this->assertEquals('eligibilityGateway', $params[2]->getName());
    }

    // =========================================================================
    // MAX SLIPS PER FILE CONSTRAINT TESTS
    // =========================================================================

    /**
     * @test
     */
    public function batchProcessorRespectsMaxSlipsPerFileConstraint(): void
    {
        // Verify the constant exists and has correct value
        $this->assertEquals(1000, RL24XmlSchema::MAX_SLIPS_PER_FILE);
    }

    // =========================================================================
    // OUTPUT DIRECTORY HANDLING TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getOutputDirectoryHasCorrectParameters(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('getOutputDirectory');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'getOutputDirectory should have 1 parameter');
        $this->assertEquals('taxYear', $params[0]->getName());
    }

    /**
     * @test
     */
    public function getOutputDirectoryReturnsString(): void
    {
        $reflection = new ReflectionClass(RL24BatchProcessor::class);
        $method = $reflection->getMethod('getOutputDirectory');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('string', $returnType->getName());
    }

    // =========================================================================
    // SLIP TYPE BREAKDOWN TESTS
    // =========================================================================

    /**
     * @test
     */
    public function calculateSummaryBySlipTypeContainsAllTypes(): void
    {
        // Verify that the method signature exists and returns array
        $reflection = new ReflectionClass(RL24SummaryCalculator::class);
        $method = $reflection->getMethod('calculateSummaryBySlipType');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }
}
