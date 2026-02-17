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

namespace Gibbon\Module\CareTracking\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gibbon\Module\CareTracking\Service\MealService;
use Gibbon\Module\CareTracking\Domain\MealGateway;
use Gibbon\Domain\QueryCriteria;

/**
 * Unit tests for MealService.
 *
 * Tests meal logging, validation, queries, and statistics retrieval.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class MealServiceTest extends TestCase
{
    /**
     * @var MealGateway|MockObject
     */
    protected $mealGateway;

    /**
     * @var MealService
     */
    protected $mealService;

    /**
     * Sample meal data for testing.
     *
     * @var array
     */
    protected $sampleMeal;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock gateway
        $this->mealGateway = $this->createMock(MealGateway::class);

        // Create service with mock gateway
        $this->mealService = new MealService($this->mealGateway);

        // Sample meal data
        $this->sampleMeal = [
            'gibbonCareTrackingMealID' => 1,
            'gibbonPersonID' => 100,
            'gibbonSchoolYearID' => 2025,
            'date' => '2025-01-15',
            'mealType' => 'Lunch',
            'quantity' => 'Most',
            'allergyAlert' => false,
            'notes' => 'Ate well',
            'recordedByID' => 1,
            'timestampCreated' => '2025-01-15 12:30:00',
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->mealGateway = null;
        $this->mealService = null;
    }

    // =========================================================================
    // MEAL VALIDATION TESTS
    // =========================================================================

    /**
     * Test that valid meal data passes validation.
     */
    public function testValidMealDataPassesValidation(): void
    {
        $validData = [
            'gibbonPersonID' => 100,
            'date' => '2025-01-15',
            'mealType' => 'Lunch',
            'quantity' => 'Most',
            'recordedByID' => 1,
        ];

        $result = $this->mealService->validateMealData($validData);

        $this->assertTrue($result['valid'], 'Valid meal data should pass validation');
        $this->assertEmpty($result['errors'], 'Valid data should have no errors');
    }

    /**
     * Test that missing child ID fails validation.
     */
    public function testMissingChildIDFailsValidation(): void
    {
        $invalidData = [
            'date' => '2025-01-15',
            'mealType' => 'Lunch',
            'quantity' => 'Most',
            'recordedByID' => 1,
        ];

        $result = $this->mealService->validateMealData($invalidData);

        $this->assertFalse($result['valid'], 'Missing child ID should fail validation');
        $this->assertContains('Child ID is required', $result['errors']);
    }

    /**
     * Test that missing date fails validation.
     */
    public function testMissingDateFailsValidation(): void
    {
        $invalidData = [
            'gibbonPersonID' => 100,
            'mealType' => 'Lunch',
            'quantity' => 'Most',
            'recordedByID' => 1,
        ];

        $result = $this->mealService->validateMealData($invalidData);

        $this->assertFalse($result['valid'], 'Missing date should fail validation');
        $this->assertContains('Date is required', $result['errors']);
    }

    /**
     * Test that invalid date format fails validation.
     */
    public function testInvalidDateFormatFailsValidation(): void
    {
        $invalidData = [
            'gibbonPersonID' => 100,
            'date' => '15-01-2025',
            'mealType' => 'Lunch',
            'quantity' => 'Most',
            'recordedByID' => 1,
        ];

        $result = $this->mealService->validateMealData($invalidData);

        $this->assertFalse($result['valid'], 'Invalid date format should fail validation');
        $this->assertContains('Date must be in Y-m-d format', $result['errors']);
    }

    /**
     * Test that invalid meal type fails validation.
     */
    public function testInvalidMealTypeFailsValidation(): void
    {
        $invalidData = [
            'gibbonPersonID' => 100,
            'date' => '2025-01-15',
            'mealType' => 'InvalidMeal',
            'quantity' => 'Most',
            'recordedByID' => 1,
        ];

        $result = $this->mealService->validateMealData($invalidData);

        $this->assertFalse($result['valid'], 'Invalid meal type should fail validation');
        $this->assertContains('Valid meal type is required', $result['errors']);
    }

    /**
     * Test that all valid meal types pass validation.
     */
    public function testAllValidMealTypesPassValidation(): void
    {
        $validMealTypes = ['Breakfast', 'Morning Snack', 'Lunch', 'Afternoon Snack', 'Dinner'];

        foreach ($validMealTypes as $mealType) {
            $data = [
                'gibbonPersonID' => 100,
                'date' => '2025-01-15',
                'mealType' => $mealType,
                'quantity' => 'All',
                'recordedByID' => 1,
            ];

            $result = $this->mealService->validateMealData($data);

            $this->assertTrue($result['valid'], "Meal type '{$mealType}' should be valid");
        }
    }

    /**
     * Test that invalid quantity fails validation.
     */
    public function testInvalidQuantityFailsValidation(): void
    {
        $invalidData = [
            'gibbonPersonID' => 100,
            'date' => '2025-01-15',
            'mealType' => 'Lunch',
            'quantity' => 'InvalidQuantity',
            'recordedByID' => 1,
        ];

        $result = $this->mealService->validateMealData($invalidData);

        $this->assertFalse($result['valid'], 'Invalid quantity should fail validation');
        $this->assertContains('Valid quantity is required', $result['errors']);
    }

    /**
     * Test that all valid quantities pass validation.
     */
    public function testAllValidQuantitiesPassValidation(): void
    {
        $validQuantities = ['All', 'Most', 'Some', 'Little', 'None'];

        foreach ($validQuantities as $quantity) {
            $data = [
                'gibbonPersonID' => 100,
                'date' => '2025-01-15',
                'mealType' => 'Lunch',
                'quantity' => $quantity,
                'recordedByID' => 1,
            ];

            $result = $this->mealService->validateMealData($data);

            $this->assertTrue($result['valid'], "Quantity '{$quantity}' should be valid");
        }
    }

    /**
     * Test that missing recordedByID fails validation.
     */
    public function testMissingRecordedByIDFailsValidation(): void
    {
        $invalidData = [
            'gibbonPersonID' => 100,
            'date' => '2025-01-15',
            'mealType' => 'Lunch',
            'quantity' => 'Most',
        ];

        $result = $this->mealService->validateMealData($invalidData);

        $this->assertFalse($result['valid'], 'Missing recordedByID should fail validation');
        $this->assertContains('Recorded by ID is required', $result['errors']);
    }

    // =========================================================================
    // MEAL LOGGING TESTS
    // =========================================================================

    /**
     * Test that logMeal calls gateway with correct parameters.
     */
    public function testLogMealCallsGatewayWithCorrectParameters(): void
    {
        $gibbonPersonID = 100;
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';
        $mealType = 'Lunch';
        $quantity = 'Most';
        $recordedByID = 1;
        $allergyAlert = false;
        $notes = 'Ate well';

        $this->mealGateway->expects($this->once())
            ->method('logMeal')
            ->with(
                $gibbonPersonID,
                $gibbonSchoolYearID,
                $date,
                $mealType,
                $quantity,
                $recordedByID,
                $allergyAlert,
                $notes
            )
            ->willReturn(1);

        $result = $this->mealService->logMeal(
            $gibbonPersonID,
            $gibbonSchoolYearID,
            $date,
            $mealType,
            $quantity,
            $recordedByID,
            $allergyAlert,
            $notes
        );

        $this->assertEquals(1, $result, 'logMeal should return the meal ID');
    }

    /**
     * Test that logMeal with allergy alert is recorded correctly.
     */
    public function testLogMealWithAllergyAlert(): void
    {
        $this->mealGateway->expects($this->once())
            ->method('logMeal')
            ->with(
                100,
                2025,
                '2025-01-15',
                'Lunch',
                'None',
                1,
                true,
                'Allergic reaction detected'
            )
            ->willReturn(2);

        $result = $this->mealService->logMeal(
            100,
            2025,
            '2025-01-15',
            'Lunch',
            'None',
            1,
            true,
            'Allergic reaction detected'
        );

        $this->assertEquals(2, $result);
    }

    // =========================================================================
    // MEAL QUERY TESTS
    // =========================================================================

    /**
     * Test that queryMeals calls gateway correctly.
     */
    public function testQueryMealsCallsGateway(): void
    {
        $criteria = $this->createMock(QueryCriteria::class);
        $gibbonSchoolYearID = 2025;

        $this->mealGateway->expects($this->once())
            ->method('queryMeals')
            ->with($criteria, $gibbonSchoolYearID);

        $this->mealService->queryMeals($criteria, $gibbonSchoolYearID);
    }

    /**
     * Test that queryMealsByDate calls gateway correctly.
     */
    public function testQueryMealsByDateCallsGateway(): void
    {
        $criteria = $this->createMock(QueryCriteria::class);
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';

        $this->mealGateway->expects($this->once())
            ->method('queryMealsByDate')
            ->with($criteria, $gibbonSchoolYearID, $date);

        $this->mealService->queryMealsByDate($criteria, $gibbonSchoolYearID, $date);
    }

    /**
     * Test that queryMealsByPerson calls gateway correctly.
     */
    public function testQueryMealsByPersonCallsGateway(): void
    {
        $criteria = $this->createMock(QueryCriteria::class);
        $gibbonPersonID = 100;
        $gibbonSchoolYearID = 2025;

        $this->mealGateway->expects($this->once())
            ->method('queryMealsByPerson')
            ->with($criteria, $gibbonPersonID, $gibbonSchoolYearID);

        $this->mealService->queryMealsByPerson($criteria, $gibbonPersonID, $gibbonSchoolYearID);
    }

    /**
     * Test that getMealsByPersonAndDate calls gateway correctly.
     */
    public function testGetMealsByPersonAndDateCallsGateway(): void
    {
        $gibbonPersonID = 100;
        $date = '2025-01-15';

        $this->mealGateway->expects($this->once())
            ->method('selectMealsByPersonAndDate')
            ->with($gibbonPersonID, $date);

        $this->mealService->getMealsByPersonAndDate($gibbonPersonID, $date);
    }

    /**
     * Test that getMealByPersonDateAndType calls gateway correctly.
     */
    public function testGetMealByPersonDateAndTypeCallsGateway(): void
    {
        $gibbonPersonID = 100;
        $date = '2025-01-15';
        $mealType = 'Lunch';

        $this->mealGateway->expects($this->once())
            ->method('getMealByPersonDateAndType')
            ->with($gibbonPersonID, $date, $mealType)
            ->willReturn($this->sampleMeal);

        $result = $this->mealService->getMealByPersonDateAndType($gibbonPersonID, $date, $mealType);

        $this->assertEquals($this->sampleMeal, $result);
    }

    /**
     * Test that getChildrenWithoutMeal calls gateway correctly.
     */
    public function testGetChildrenWithoutMealCallsGateway(): void
    {
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';
        $mealType = 'Lunch';

        $this->mealGateway->expects($this->once())
            ->method('selectChildrenWithoutMeal')
            ->with($gibbonSchoolYearID, $date, $mealType);

        $this->mealService->getChildrenWithoutMeal($gibbonSchoolYearID, $date, $mealType);
    }

    // =========================================================================
    // STATISTICS TESTS
    // =========================================================================

    /**
     * Test that getMealSummaryByDate returns summary statistics.
     */
    public function testGetMealSummaryByDateReturnsStatistics(): void
    {
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';
        $expectedSummary = [
            'totalMeals' => 50,
            'breakfast' => 10,
            'lunch' => 20,
            'snacks' => 20,
        ];

        $this->mealGateway->expects($this->once())
            ->method('getMealSummaryByDate')
            ->with($gibbonSchoolYearID, $date)
            ->willReturn($expectedSummary);

        $result = $this->mealService->getMealSummaryByDate($gibbonSchoolYearID, $date);

        $this->assertEquals($expectedSummary, $result);
    }

    /**
     * Test that getMealStatsByPersonAndDateRange returns statistics.
     */
    public function testGetMealStatsByPersonAndDateRangeReturnsStatistics(): void
    {
        $gibbonPersonID = 100;
        $dateStart = '2025-01-01';
        $dateEnd = '2025-01-31';
        $expectedStats = [
            'totalMeals' => 60,
            'averageQuantity' => 'Most',
            'allergyAlerts' => 2,
        ];

        $this->mealGateway->expects($this->once())
            ->method('getMealStatsByPersonAndDateRange')
            ->with($gibbonPersonID, $dateStart, $dateEnd)
            ->willReturn($expectedStats);

        $result = $this->mealService->getMealStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd);

        $this->assertEquals($expectedStats, $result);
    }
}
