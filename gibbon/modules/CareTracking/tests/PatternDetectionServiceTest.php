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
use ReflectionMethod;
use ReflectionProperty;

/**
 * Unit tests for PatternDetectionService.
 *
 * These tests verify that the PatternDetectionService class has the correct
 * structure, method signatures, and constants for incident pattern detection.
 *
 * @covers \Gibbon\Module\CareTracking\Domain\PatternDetectionService
 */
class PatternDetectionServiceTest extends TestCase
{
    /**
     * The fully qualified class name for PatternDetectionService.
     *
     * @var string
     */
    private const CLASS_NAME = 'Gibbon\\Module\\CareTracking\\Domain\\PatternDetectionService';

    /**
     * Get reflection class instance for PatternDetectionService.
     *
     * @return ReflectionClass
     */
    private function getReflection(): ReflectionClass
    {
        return new ReflectionClass(self::CLASS_NAME);
    }

    /**
     * Helper method to get the value of a constant.
     *
     * @param string $constantName
     * @return mixed
     */
    private function getConstantValue(string $constantName)
    {
        $reflection = $this->getReflection();
        return $reflection->getConstant($constantName);
    }

    /**
     * Helper method to get a property value from the class.
     *
     * @param string $propertyName
     * @return ReflectionProperty
     */
    private function getProperty(string $propertyName): ReflectionProperty
    {
        $reflection = $this->getReflection();
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property;
    }

    // =========================================================================
    // CLASS STRUCTURE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function classExists(): void
    {
        $this->assertTrue(
            class_exists(self::CLASS_NAME),
            'PatternDetectionService class should exist'
        );
    }

    /**
     * @test
     */
    public function constructorHasCorrectParameters(): void
    {
        $reflection = $this->getReflection();
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor, 'PatternDetectionService should have a constructor');

        $params = $constructor->getParameters();
        $this->assertCount(2, $params, 'Constructor should have 2 parameters');

        $this->assertEquals('db', $params[0]->getName());
        $this->assertEquals('incidentGateway', $params[1]->getName());
    }

    /**
     * @test
     */
    public function classHasRequiredProperties(): void
    {
        $reflection = $this->getReflection();

        $requiredProperties = [
            'db',
            'incidentGateway',
            'defaultThreshold',
            'defaultPeriodDays',
        ];

        foreach ($requiredProperties as $property) {
            $this->assertTrue(
                $reflection->hasProperty($property),
                sprintf('PatternDetectionService should have property "%s"', $property)
            );
        }
    }

    // =========================================================================
    // PATTERN TYPE CONSTANTS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function hasPatternTypeConstants(): void
    {
        $reflection = $this->getReflection();

        $requiredConstants = [
            'PATTERN_FREQUENCY',
            'PATTERN_SEVERITY',
            'PATTERN_CATEGORY',
            'PATTERN_LOCATION',
            'PATTERN_TIME',
            'PATTERN_BEHAVIORAL',
        ];

        foreach ($requiredConstants as $constant) {
            $this->assertTrue(
                $reflection->hasConstant($constant),
                sprintf('PatternDetectionService should have constant "%s"', $constant)
            );
        }
    }

    /**
     * @test
     */
    public function patternFrequencyConstantHasCorrectValue(): void
    {
        $this->assertEquals(
            'Frequency',
            $this->getConstantValue('PATTERN_FREQUENCY'),
            'PATTERN_FREQUENCY should be "Frequency"'
        );
    }

    /**
     * @test
     */
    public function patternSeverityConstantHasCorrectValue(): void
    {
        $this->assertEquals(
            'Severity',
            $this->getConstantValue('PATTERN_SEVERITY'),
            'PATTERN_SEVERITY should be "Severity"'
        );
    }

    /**
     * @test
     */
    public function patternCategoryConstantHasCorrectValue(): void
    {
        $this->assertEquals(
            'Category',
            $this->getConstantValue('PATTERN_CATEGORY'),
            'PATTERN_CATEGORY should be "Category"'
        );
    }

    /**
     * @test
     */
    public function patternBehavioralConstantHasCorrectValue(): void
    {
        $this->assertEquals(
            'Behavioral',
            $this->getConstantValue('PATTERN_BEHAVIORAL'),
            'PATTERN_BEHAVIORAL should be "Behavioral"'
        );
    }

    // =========================================================================
    // PUBLIC METHOD TESTS
    // =========================================================================

    /**
     * @test
     */
    public function hasRunPatternDetectionMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('runPatternDetection'),
            'PatternDetectionService should have runPatternDetection method'
        );

        $method = $reflection->getMethod('runPatternDetection');
        $this->assertTrue($method->isPublic(), 'runPatternDetection should be public');

        $params = $method->getParameters();
        $this->assertCount(3, $params, 'runPatternDetection should have 3 parameters');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
        $this->assertEquals('threshold', $params[1]->getName());
        $this->assertEquals('periodDays', $params[2]->getName());

        // Check optional parameters
        $this->assertTrue($params[1]->isOptional(), 'threshold should be optional');
        $this->assertTrue($params[2]->isOptional(), 'periodDays should be optional');
    }

    /**
     * @test
     */
    public function hasIdentifyAtRiskChildrenMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('identifyAtRiskChildren'),
            'PatternDetectionService should have identifyAtRiskChildren method'
        );

        $method = $reflection->getMethod('identifyAtRiskChildren');
        $this->assertTrue($method->isPublic(), 'identifyAtRiskChildren should be public');

        $params = $method->getParameters();
        $this->assertGreaterThanOrEqual(1, count($params), 'identifyAtRiskChildren should have at least 1 parameter');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function hasCreatePatternAlertMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('createPatternAlert'),
            'PatternDetectionService should have createPatternAlert method'
        );

        $method = $reflection->getMethod('createPatternAlert');
        $this->assertTrue($method->isPublic(), 'createPatternAlert should be public');

        $params = $method->getParameters();
        $this->assertGreaterThanOrEqual(6, count($params), 'createPatternAlert should have at least 6 parameters');

        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
        $this->assertEquals('patternType', $params[2]->getName());
        $this->assertEquals('incidentCount', $params[3]->getName());
        $this->assertEquals('periodDays', $params[4]->getName());
        $this->assertEquals('incidentIDs', $params[5]->getName());
    }

    /**
     * @test
     */
    public function hasGetPendingPatternAlertsMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('getPendingPatternAlerts'),
            'PatternDetectionService should have getPendingPatternAlerts method'
        );

        $method = $reflection->getMethod('getPendingPatternAlerts');
        $this->assertTrue($method->isPublic(), 'getPendingPatternAlerts should be public');

        $params = $method->getParameters();
        $this->assertCount(1, $params, 'getPendingPatternAlerts should have 1 parameter');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function hasGetPatternAlertsByPersonMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('getPatternAlertsByPerson'),
            'PatternDetectionService should have getPatternAlertsByPerson method'
        );

        $method = $reflection->getMethod('getPatternAlertsByPerson');
        $this->assertTrue($method->isPublic(), 'getPatternAlertsByPerson should be public');

        $params = $method->getParameters();
        $this->assertCount(2, $params, 'getPatternAlertsByPerson should have 2 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('gibbonSchoolYearID', $params[1]->getName());
    }

    /**
     * @test
     */
    public function hasMarkPatternReviewedMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('markPatternReviewed'),
            'PatternDetectionService should have markPatternReviewed method'
        );

        $method = $reflection->getMethod('markPatternReviewed');
        $this->assertTrue($method->isPublic(), 'markPatternReviewed should be public');

        $params = $method->getParameters();
        $this->assertGreaterThanOrEqual(3, count($params), 'markPatternReviewed should have at least 3 parameters');

        $this->assertEquals('gibbonCareIncidentPatternID', $params[0]->getName());
        $this->assertEquals('status', $params[1]->getName());
        $this->assertEquals('reviewedByID', $params[2]->getName());
    }

    /**
     * @test
     */
    public function hasGetPatternStatisticsMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('getPatternStatistics'),
            'PatternDetectionService should have getPatternStatistics method'
        );

        $method = $reflection->getMethod('getPatternStatistics');
        $this->assertTrue($method->isPublic(), 'getPatternStatistics should be public');

        $params = $method->getParameters();
        $this->assertCount(1, $params, 'getPatternStatistics should have 1 parameter');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
    }

    // =========================================================================
    // PROTECTED METHOD TESTS
    // =========================================================================

    /**
     * @test
     */
    public function hasDetectFrequencyPatternsMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('detectFrequencyPatterns'),
            'PatternDetectionService should have detectFrequencyPatterns method'
        );

        $method = $reflection->getMethod('detectFrequencyPatterns');
        $this->assertTrue($method->isProtected(), 'detectFrequencyPatterns should be protected');
    }

    /**
     * @test
     */
    public function hasDetectSeverityPatternsMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('detectSeverityPatterns'),
            'PatternDetectionService should have detectSeverityPatterns method'
        );

        $method = $reflection->getMethod('detectSeverityPatterns');
        $this->assertTrue($method->isProtected(), 'detectSeverityPatterns should be protected');
    }

    /**
     * @test
     */
    public function hasDetectCategoryPatternsMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('detectCategoryPatterns'),
            'PatternDetectionService should have detectCategoryPatterns method'
        );

        $method = $reflection->getMethod('detectCategoryPatterns');
        $this->assertTrue($method->isProtected(), 'detectCategoryPatterns should be protected');
    }

    /**
     * @test
     */
    public function hasDetectBehavioralPatternsMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('detectBehavioralPatterns'),
            'PatternDetectionService should have detectBehavioralPatterns method'
        );

        $method = $reflection->getMethod('detectBehavioralPatterns');
        $this->assertTrue($method->isProtected(), 'detectBehavioralPatterns should be protected');
    }

    /**
     * @test
     */
    public function hasCalculateRiskLevelMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('calculateRiskLevel'),
            'PatternDetectionService should have calculateRiskLevel method'
        );

        $method = $reflection->getMethod('calculateRiskLevel');
        $this->assertTrue($method->isProtected(), 'calculateRiskLevel should be protected');
    }

    /**
     * @test
     */
    public function hasGetSettingValueMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('getSettingValue'),
            'PatternDetectionService should have getSettingValue method'
        );

        $method = $reflection->getMethod('getSettingValue');
        $this->assertTrue($method->isProtected(), 'getSettingValue should be protected');
    }

    // =========================================================================
    // DATA PROVIDER TESTS
    // =========================================================================

    /**
     * Data provider for pattern detection method tests.
     *
     * @return array
     */
    public static function patternDetectionMethodsProvider(): array
    {
        return [
            'detectFrequencyPatterns' => ['detectFrequencyPatterns', 4],
            'detectSeverityPatterns' => ['detectSeverityPatterns', 3],
            'detectCategoryPatterns' => ['detectCategoryPatterns', 4],
            'detectBehavioralPatterns' => ['detectBehavioralPatterns', 3],
        ];
    }

    /**
     * @test
     * @dataProvider patternDetectionMethodsProvider
     */
    public function patternDetectionMethodHasCorrectParameterCount(string $methodName, int $expectedParams): void
    {
        $reflection = $this->getReflection();
        $method = $reflection->getMethod($methodName);
        $params = $method->getParameters();

        $this->assertCount(
            $expectedParams,
            $params,
            sprintf('%s should have %d parameters', $methodName, $expectedParams)
        );
    }

    /**
     * Data provider for public service methods.
     *
     * @return array
     */
    public static function publicMethodsProvider(): array
    {
        return [
            'runPatternDetection' => ['runPatternDetection'],
            'identifyAtRiskChildren' => ['identifyAtRiskChildren'],
            'createPatternAlert' => ['createPatternAlert'],
            'getPendingPatternAlerts' => ['getPendingPatternAlerts'],
            'getPatternAlertsByPerson' => ['getPatternAlertsByPerson'],
            'markPatternReviewed' => ['markPatternReviewed'],
            'getPatternStatistics' => ['getPatternStatistics'],
        ];
    }

    /**
     * @test
     * @dataProvider publicMethodsProvider
     */
    public function publicMethodExists(string $methodName): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod($methodName),
            sprintf('PatternDetectionService should have method %s', $methodName)
        );

        $method = $reflection->getMethod($methodName);
        $this->assertTrue(
            $method->isPublic(),
            sprintf('Method %s should be public', $methodName)
        );
    }

    // =========================================================================
    // DEFAULT VALUE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function defaultThresholdPropertyExists(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasProperty('defaultThreshold'),
            'PatternDetectionService should have defaultThreshold property'
        );

        $property = $reflection->getProperty('defaultThreshold');
        $this->assertTrue($property->isProtected(), 'defaultThreshold should be protected');
    }

    /**
     * @test
     */
    public function defaultPeriodDaysPropertyExists(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasProperty('defaultPeriodDays'),
            'PatternDetectionService should have defaultPeriodDays property'
        );

        $property = $reflection->getProperty('defaultPeriodDays');
        $this->assertTrue($property->isProtected(), 'defaultPeriodDays should be protected');
    }

    // =========================================================================
    // HELPER METHOD TESTS
    // =========================================================================

    /**
     * @test
     */
    public function hasGetExistingPendingPatternMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('getExistingPendingPattern'),
            'PatternDetectionService should have getExistingPendingPattern method'
        );

        $method = $reflection->getMethod('getExistingPendingPattern');
        $this->assertTrue($method->isProtected(), 'getExistingPendingPattern should be protected');

        $params = $method->getParameters();
        $this->assertCount(3, $params, 'getExistingPendingPattern should have 3 parameters');
    }

    /**
     * @test
     */
    public function hasUpdatePatternAlertMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('updatePatternAlert'),
            'PatternDetectionService should have updatePatternAlert method'
        );

        $method = $reflection->getMethod('updatePatternAlert');
        $this->assertTrue($method->isProtected(), 'updatePatternAlert should be protected');

        $params = $method->getParameters();
        $this->assertGreaterThanOrEqual(3, count($params), 'updatePatternAlert should have at least 3 parameters');
    }
}
