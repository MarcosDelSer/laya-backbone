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
use Gibbon\Module\CareTracking\Service\IncidentService;
use Gibbon\Module\CareTracking\Domain\IncidentGateway;
use Gibbon\Domain\QueryCriteria;

/**
 * Unit tests for IncidentService.
 *
 * Tests incident logging, validation, queries, severity checking, and parent notifications.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class IncidentServiceTest extends TestCase
{
    /**
     * @var IncidentGateway|MockObject
     */
    protected $incidentGateway;

    /**
     * @var IncidentService
     */
    protected $incidentService;

    /**
     * Sample incident data for testing.
     *
     * @var array
     */
    protected $sampleIncident;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock gateway
        $this->incidentGateway = $this->createMock(IncidentGateway::class);

        // Create service with mock gateway
        $this->incidentService = new IncidentService($this->incidentGateway);

        // Sample incident data
        $this->sampleIncident = [
            'gibbonCareIncidentID' => 1,
            'gibbonPersonID' => 100,
            'gibbonSchoolYearID' => 2025,
            'date' => '2025-01-15',
            'time' => '14:30:00',
            'type' => 'Minor Injury',
            'severity' => 'Low',
            'description' => 'Scraped knee on playground',
            'actionTaken' => 'Applied bandage',
            'recordedByID' => 1,
            'parentNotified' => false,
            'parentAcknowledged' => false,
            'timestampCreated' => '2025-01-15 14:35:00',
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->incidentGateway = null;
        $this->incidentService = null;
    }

    // =========================================================================
    // INCIDENT VALIDATION TESTS
    // =========================================================================

    /**
     * Test that valid incident data passes validation.
     */
    public function testValidIncidentDataPassesValidation(): void
    {
        $validData = [
            'gibbonPersonID' => 100,
            'date' => '2025-01-15',
            'time' => '14:30:00',
            'type' => 'Minor Injury',
            'severity' => 'Low',
            'description' => 'Scraped knee on playground',
            'recordedByID' => 1,
        ];

        $result = $this->incidentService->validateIncidentData($validData);

        $this->assertTrue($result['valid'], 'Valid incident data should pass validation');
        $this->assertEmpty($result['errors'], 'Valid data should have no errors');
    }

    /**
     * Test that missing child ID fails validation.
     */
    public function testMissingChildIDFailsValidation(): void
    {
        $invalidData = [
            'date' => '2025-01-15',
            'time' => '14:30:00',
            'type' => 'Minor Injury',
            'severity' => 'Low',
            'description' => 'Test',
            'recordedByID' => 1,
        ];

        $result = $this->incidentService->validateIncidentData($invalidData);

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
            'time' => '14:30:00',
            'type' => 'Minor Injury',
            'severity' => 'Low',
            'description' => 'Test',
            'recordedByID' => 1,
        ];

        $result = $this->incidentService->validateIncidentData($invalidData);

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
            'time' => '14:30:00',
            'type' => 'Minor Injury',
            'severity' => 'Low',
            'description' => 'Test',
            'recordedByID' => 1,
        ];

        $result = $this->incidentService->validateIncidentData($invalidData);

        $this->assertFalse($result['valid'], 'Invalid date format should fail validation');
        $this->assertContains('Date must be in Y-m-d format', $result['errors']);
    }

    /**
     * Test that missing time fails validation.
     */
    public function testMissingTimeFailsValidation(): void
    {
        $invalidData = [
            'gibbonPersonID' => 100,
            'date' => '2025-01-15',
            'type' => 'Minor Injury',
            'severity' => 'Low',
            'description' => 'Test',
            'recordedByID' => 1,
        ];

        $result = $this->incidentService->validateIncidentData($invalidData);

        $this->assertFalse($result['valid'], 'Missing time should fail validation');
        $this->assertContains('Time is required', $result['errors']);
    }

    /**
     * Test that invalid time format fails validation.
     */
    public function testInvalidTimeFormatFailsValidation(): void
    {
        $invalidData = [
            'gibbonPersonID' => 100,
            'date' => '2025-01-15',
            'time' => '2:30 PM',
            'type' => 'Minor Injury',
            'severity' => 'Low',
            'description' => 'Test',
            'recordedByID' => 1,
        ];

        $result = $this->incidentService->validateIncidentData($invalidData);

        $this->assertFalse($result['valid'], 'Invalid time format should fail validation');
        $this->assertContains('Time must be in H:i or H:i:s format', $result['errors']);
    }

    /**
     * Test that both H:i and H:i:s time formats are valid.
     */
    public function testBothTimeFormatsAreValid(): void
    {
        $timeFormats = ['14:30', '14:30:00'];

        foreach ($timeFormats as $time) {
            $data = [
                'gibbonPersonID' => 100,
                'date' => '2025-01-15',
                'time' => $time,
                'type' => 'Minor Injury',
                'severity' => 'Low',
                'description' => 'Test',
                'recordedByID' => 1,
            ];

            $result = $this->incidentService->validateIncidentData($data);

            $this->assertTrue($result['valid'], "Time format '{$time}' should be valid");
        }
    }

    /**
     * Test that invalid incident type fails validation.
     */
    public function testInvalidIncidentTypeFailsValidation(): void
    {
        $invalidData = [
            'gibbonPersonID' => 100,
            'date' => '2025-01-15',
            'time' => '14:30:00',
            'type' => 'InvalidType',
            'severity' => 'Low',
            'description' => 'Test',
            'recordedByID' => 1,
        ];

        $result = $this->incidentService->validateIncidentData($invalidData);

        $this->assertFalse($result['valid'], 'Invalid incident type should fail validation');
        $this->assertContains('Valid incident type is required', $result['errors']);
    }

    /**
     * Test that all valid incident types pass validation.
     */
    public function testAllValidIncidentTypesPassValidation(): void
    {
        $validTypes = ['Minor Injury', 'Major Injury', 'Illness', 'Behavioral', 'Other'];

        foreach ($validTypes as $type) {
            $data = [
                'gibbonPersonID' => 100,
                'date' => '2025-01-15',
                'time' => '14:30:00',
                'type' => $type,
                'severity' => 'Low',
                'description' => 'Test',
                'recordedByID' => 1,
            ];

            $result = $this->incidentService->validateIncidentData($data);

            $this->assertTrue($result['valid'], "Incident type '{$type}' should be valid");
        }
    }

    /**
     * Test that invalid severity fails validation.
     */
    public function testInvalidSeverityFailsValidation(): void
    {
        $invalidData = [
            'gibbonPersonID' => 100,
            'date' => '2025-01-15',
            'time' => '14:30:00',
            'type' => 'Minor Injury',
            'severity' => 'InvalidSeverity',
            'description' => 'Test',
            'recordedByID' => 1,
        ];

        $result = $this->incidentService->validateIncidentData($invalidData);

        $this->assertFalse($result['valid'], 'Invalid severity should fail validation');
        $this->assertContains('Valid severity level is required', $result['errors']);
    }

    /**
     * Test that all valid severity levels pass validation.
     */
    public function testAllValidSeverityLevelsPassValidation(): void
    {
        $validSeverities = ['Low', 'Medium', 'High', 'Critical'];

        foreach ($validSeverities as $severity) {
            $data = [
                'gibbonPersonID' => 100,
                'date' => '2025-01-15',
                'time' => '14:30:00',
                'type' => 'Minor Injury',
                'severity' => $severity,
                'description' => 'Test',
                'recordedByID' => 1,
            ];

            $result = $this->incidentService->validateIncidentData($data);

            $this->assertTrue($result['valid'], "Severity '{$severity}' should be valid");
        }
    }

    /**
     * Test that missing description fails validation.
     */
    public function testMissingDescriptionFailsValidation(): void
    {
        $invalidData = [
            'gibbonPersonID' => 100,
            'date' => '2025-01-15',
            'time' => '14:30:00',
            'type' => 'Minor Injury',
            'severity' => 'Low',
            'recordedByID' => 1,
        ];

        $result = $this->incidentService->validateIncidentData($invalidData);

        $this->assertFalse($result['valid'], 'Missing description should fail validation');
        $this->assertContains('Description is required', $result['errors']);
    }

    /**
     * Test that missing recordedByID fails validation.
     */
    public function testMissingRecordedByIDFailsValidation(): void
    {
        $invalidData = [
            'gibbonPersonID' => 100,
            'date' => '2025-01-15',
            'time' => '14:30:00',
            'type' => 'Minor Injury',
            'severity' => 'Low',
            'description' => 'Test',
        ];

        $result = $this->incidentService->validateIncidentData($invalidData);

        $this->assertFalse($result['valid'], 'Missing recordedByID should fail validation');
        $this->assertContains('Recorded by ID is required', $result['errors']);
    }

    // =========================================================================
    // INCIDENT LOGGING TESTS
    // =========================================================================

    /**
     * Test that logIncident calls gateway with correct parameters.
     */
    public function testLogIncidentCallsGatewayWithCorrectParameters(): void
    {
        $gibbonPersonID = 100;
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';
        $time = '14:30:00';
        $type = 'Minor Injury';
        $severity = 'Low';
        $description = 'Scraped knee on playground';
        $recordedByID = 1;
        $actionTaken = 'Applied bandage';

        $this->incidentGateway->expects($this->once())
            ->method('logIncident')
            ->with(
                $gibbonPersonID,
                $gibbonSchoolYearID,
                $date,
                $time,
                $type,
                $severity,
                $description,
                $recordedByID,
                $actionTaken
            )
            ->willReturn(1);

        $result = $this->incidentService->logIncident(
            $gibbonPersonID,
            $gibbonSchoolYearID,
            $date,
            $time,
            $type,
            $severity,
            $description,
            $recordedByID,
            $actionTaken
        );

        $this->assertEquals(1, $result, 'logIncident should return the incident ID');
    }

    /**
     * Test that logIncident without action taken works correctly.
     */
    public function testLogIncidentWithoutActionTaken(): void
    {
        $this->incidentGateway->expects($this->once())
            ->method('logIncident')
            ->with(100, 2025, '2025-01-15', '14:30:00', 'Illness', 'Medium', 'Fever', 1, null)
            ->willReturn(2);

        $result = $this->incidentService->logIncident(
            100,
            2025,
            '2025-01-15',
            '14:30:00',
            'Illness',
            'Medium',
            'Fever',
            1
        );

        $this->assertEquals(2, $result);
    }

    // =========================================================================
    // PARENT NOTIFICATION TESTS
    // =========================================================================

    /**
     * Test that markParentNotified calls gateway correctly.
     */
    public function testMarkParentNotifiedCallsGateway(): void
    {
        $gibbonCareIncidentID = 1;

        $this->incidentGateway->expects($this->once())
            ->method('markParentNotified')
            ->with($gibbonCareIncidentID)
            ->willReturn(true);

        $result = $this->incidentService->markParentNotified($gibbonCareIncidentID);

        $this->assertTrue($result);
    }

    /**
     * Test that markParentAcknowledged calls gateway correctly.
     */
    public function testMarkParentAcknowledgedCallsGateway(): void
    {
        $gibbonCareIncidentID = 1;

        $this->incidentGateway->expects($this->once())
            ->method('markParentAcknowledged')
            ->with($gibbonCareIncidentID)
            ->willReturn(true);

        $result = $this->incidentService->markParentAcknowledged($gibbonCareIncidentID);

        $this->assertTrue($result);
    }

    /**
     * Test that getIncidentsPendingNotification calls gateway correctly.
     */
    public function testGetIncidentsPendingNotificationCallsGateway(): void
    {
        $gibbonSchoolYearID = 2025;

        $this->incidentGateway->expects($this->once())
            ->method('selectIncidentsPendingNotification')
            ->with($gibbonSchoolYearID);

        $this->incidentService->getIncidentsPendingNotification($gibbonSchoolYearID);
    }

    /**
     * Test that getIncidentsPendingAcknowledgment calls gateway correctly.
     */
    public function testGetIncidentsPendingAcknowledgmentCallsGateway(): void
    {
        $gibbonSchoolYearID = 2025;

        $this->incidentGateway->expects($this->once())
            ->method('selectIncidentsPendingAcknowledgment')
            ->with($gibbonSchoolYearID);

        $this->incidentService->getIncidentsPendingAcknowledgment($gibbonSchoolYearID);
    }

    // =========================================================================
    // SEVERITY TESTS
    // =========================================================================

    /**
     * Test that requiresParentNotification returns true for High severity.
     */
    public function testRequiresParentNotificationReturnsTrueForHighSeverity(): void
    {
        $result = $this->incidentService->requiresParentNotification('High');

        $this->assertTrue($result, 'High severity should require parent notification');
    }

    /**
     * Test that requiresParentNotification returns true for Critical severity.
     */
    public function testRequiresParentNotificationReturnsTrueForCriticalSeverity(): void
    {
        $result = $this->incidentService->requiresParentNotification('Critical');

        $this->assertTrue($result, 'Critical severity should require parent notification');
    }

    /**
     * Test that requiresParentNotification returns false for Low severity.
     */
    public function testRequiresParentNotificationReturnsFalseForLowSeverity(): void
    {
        $result = $this->incidentService->requiresParentNotification('Low');

        $this->assertFalse($result, 'Low severity should not require parent notification');
    }

    /**
     * Test that requiresParentNotification returns false for Medium severity.
     */
    public function testRequiresParentNotificationReturnsFalseForMediumSeverity(): void
    {
        $result = $this->incidentService->requiresParentNotification('Medium');

        $this->assertFalse($result, 'Medium severity should not require parent notification');
    }

    /**
     * Test that isSevere returns true for High severity.
     */
    public function testIsSevereReturnsTrueForHighSeverity(): void
    {
        $result = $this->incidentService->isSevere('High');

        $this->assertTrue($result, 'High severity should be considered severe');
    }

    /**
     * Test that isSevere returns true for Critical severity.
     */
    public function testIsSevereReturnsTrueForCriticalSeverity(): void
    {
        $result = $this->incidentService->isSevere('Critical');

        $this->assertTrue($result, 'Critical severity should be considered severe');
    }

    /**
     * Test that isSevere returns false for Low severity.
     */
    public function testIsSevereReturnsFalseForLowSeverity(): void
    {
        $result = $this->incidentService->isSevere('Low');

        $this->assertFalse($result, 'Low severity should not be considered severe');
    }

    /**
     * Test that isSevere returns false for Medium severity.
     */
    public function testIsSevereReturnsFalseForMediumSeverity(): void
    {
        $result = $this->incidentService->isSevere('Medium');

        $this->assertFalse($result, 'Medium severity should not be considered severe');
    }

    /**
     * Test that getSevereIncidents calls gateway correctly.
     */
    public function testGetSevereIncidentsCallsGateway(): void
    {
        $gibbonSchoolYearID = 2025;
        $dateStart = '2025-01-01';
        $dateEnd = '2025-01-31';

        $this->incidentGateway->expects($this->once())
            ->method('selectSevereIncidents')
            ->with($gibbonSchoolYearID, $dateStart, $dateEnd);

        $this->incidentService->getSevereIncidents($gibbonSchoolYearID, $dateStart, $dateEnd);
    }

    // =========================================================================
    // QUERY TESTS
    // =========================================================================

    /**
     * Test that queryIncidents calls gateway correctly.
     */
    public function testQueryIncidentsCallsGateway(): void
    {
        $criteria = $this->createMock(QueryCriteria::class);
        $gibbonSchoolYearID = 2025;

        $this->incidentGateway->expects($this->once())
            ->method('queryIncidents')
            ->with($criteria, $gibbonSchoolYearID);

        $this->incidentService->queryIncidents($criteria, $gibbonSchoolYearID);
    }

    /**
     * Test that queryIncidentsByDate calls gateway correctly.
     */
    public function testQueryIncidentsByDateCallsGateway(): void
    {
        $criteria = $this->createMock(QueryCriteria::class);
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';

        $this->incidentGateway->expects($this->once())
            ->method('queryIncidentsByDate')
            ->with($criteria, $gibbonSchoolYearID, $date);

        $this->incidentService->queryIncidentsByDate($criteria, $gibbonSchoolYearID, $date);
    }

    /**
     * Test that queryIncidentsByPerson calls gateway correctly.
     */
    public function testQueryIncidentsByPersonCallsGateway(): void
    {
        $criteria = $this->createMock(QueryCriteria::class);
        $gibbonPersonID = 100;
        $gibbonSchoolYearID = 2025;

        $this->incidentGateway->expects($this->once())
            ->method('queryIncidentsByPerson')
            ->with($criteria, $gibbonPersonID, $gibbonSchoolYearID);

        $this->incidentService->queryIncidentsByPerson($criteria, $gibbonPersonID, $gibbonSchoolYearID);
    }

    /**
     * Test that getIncidentsByPersonAndDate calls gateway correctly.
     */
    public function testGetIncidentsByPersonAndDateCallsGateway(): void
    {
        $gibbonPersonID = 100;
        $date = '2025-01-15';

        $this->incidentGateway->expects($this->once())
            ->method('selectIncidentsByPersonAndDate')
            ->with($gibbonPersonID, $date);

        $this->incidentService->getIncidentsByPersonAndDate($gibbonPersonID, $date);
    }

    // =========================================================================
    // STATISTICS TESTS
    // =========================================================================

    /**
     * Test that getIncidentSummaryByDate returns summary statistics.
     */
    public function testGetIncidentSummaryByDateReturnsStatistics(): void
    {
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';
        $expectedSummary = [
            'totalIncidents' => 10,
            'byType' => [
                'Minor Injury' => 5,
                'Illness' => 3,
                'Behavioral' => 2,
            ],
            'bySeverity' => [
                'Low' => 6,
                'Medium' => 3,
                'High' => 1,
            ],
        ];

        $this->incidentGateway->expects($this->once())
            ->method('getIncidentSummaryByDate')
            ->with($gibbonSchoolYearID, $date)
            ->willReturn($expectedSummary);

        $result = $this->incidentService->getIncidentSummaryByDate($gibbonSchoolYearID, $date);

        $this->assertEquals($expectedSummary, $result);
    }

    /**
     * Test that getIncidentStatsByPersonAndDateRange returns statistics.
     */
    public function testGetIncidentStatsByPersonAndDateRangeReturnsStatistics(): void
    {
        $gibbonPersonID = 100;
        $dateStart = '2025-01-01';
        $dateEnd = '2025-01-31';
        $expectedStats = [
            'totalIncidents' => 5,
            'byType' => [
                'Minor Injury' => 3,
                'Illness' => 2,
            ],
            'mostCommonType' => 'Minor Injury',
        ];

        $this->incidentGateway->expects($this->once())
            ->method('getIncidentStatsByPersonAndDateRange')
            ->with($gibbonPersonID, $dateStart, $dateEnd)
            ->willReturn($expectedStats);

        $result = $this->incidentService->getIncidentStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd);

        $this->assertEquals($expectedStats, $result);
    }
}
