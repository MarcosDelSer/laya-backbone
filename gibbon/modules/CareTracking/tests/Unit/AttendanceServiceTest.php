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
use Gibbon\Module\CareTracking\Service\AttendanceService;
use Gibbon\Module\CareTracking\Domain\AttendanceGateway;
use Gibbon\Module\CareTracking\Validator\AttendanceValidator;
use Gibbon\Domain\QueryCriteria;

/**
 * Unit tests for AttendanceService.
 *
 * Tests check-in/check-out operations, validation, queries, and hour calculations.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AttendanceServiceTest extends TestCase
{
    /**
     * @var AttendanceGateway|MockObject
     */
    protected $attendanceGateway;

    /**
     * @var AttendanceValidator|MockObject
     */
    protected $validator;

    /**
     * @var AttendanceService
     */
    protected $attendanceService;

    /**
     * Sample attendance data for testing.
     *
     * @var array
     */
    protected $sampleAttendance;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock gateway and validator
        $this->attendanceGateway = $this->createMock(AttendanceGateway::class);
        $this->validator = $this->createMock(AttendanceValidator::class);

        // Create service with mocks
        $this->attendanceService = new AttendanceService($this->attendanceGateway, $this->validator);

        // Sample attendance data
        $this->sampleAttendance = [
            'gibbonCareAttendanceID' => 1,
            'gibbonPersonID' => 100,
            'gibbonSchoolYearID' => 2025,
            'date' => '2025-01-15',
            'checkInTime' => '08:00:00',
            'checkOutTime' => '17:00:00',
            'checkInByID' => 1,
            'checkOutByID' => 1,
            'lateArrival' => false,
            'earlyDeparture' => false,
            'pickupPersonName' => 'Jane Doe',
            'pickupVerified' => true,
            'notes' => 'Normal day',
            'timestampCreated' => '2025-01-15 08:00:00',
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->attendanceGateway = null;
        $this->validator = null;
        $this->attendanceService = null;
    }

    // =========================================================================
    // CHECK-IN TESTS
    // =========================================================================

    /**
     * Test successful check-in with valid data.
     */
    public function testSuccessfulCheckInWithValidData(): void
    {
        $gibbonPersonID = 100;
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';
        $time = '08:00:00';
        $checkInByID = 1;

        $this->validator->expects($this->once())
            ->method('validateCheckIn')
            ->willReturn(['valid' => true, 'errors' => []]);

        $this->attendanceGateway->expects($this->once())
            ->method('checkIn')
            ->with($gibbonPersonID, $gibbonSchoolYearID, $date, $time, $checkInByID, false, null)
            ->willReturn(1);

        $result = $this->attendanceService->checkIn($gibbonPersonID, $gibbonSchoolYearID, $date, $time, $checkInByID);

        $this->assertTrue($result['success'], 'Check-in should succeed');
        $this->assertEquals(1, $result['id'], 'Should return attendance ID');
        $this->assertEmpty($result['errors'], 'Should have no errors');
    }

    /**
     * Test check-in with late arrival flag.
     */
    public function testCheckInWithLateArrival(): void
    {
        $gibbonPersonID = 100;
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';
        $time = '10:00:00';
        $checkInByID = 1;
        $lateArrival = true;
        $notes = 'Missed bus';

        $this->validator->expects($this->once())
            ->method('validateCheckIn')
            ->willReturn(['valid' => true, 'errors' => []]);

        $this->attendanceGateway->expects($this->once())
            ->method('checkIn')
            ->with($gibbonPersonID, $gibbonSchoolYearID, $date, $time, $checkInByID, $lateArrival, $notes)
            ->willReturn(2);

        $result = $this->attendanceService->checkIn(
            $gibbonPersonID,
            $gibbonSchoolYearID,
            $date,
            $time,
            $checkInByID,
            $lateArrival,
            $notes
        );

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['id']);
    }

    /**
     * Test check-in fails with invalid data.
     */
    public function testCheckInFailsWithInvalidData(): void
    {
        $this->validator->expects($this->once())
            ->method('validateCheckIn')
            ->willReturn([
                'valid' => false,
                'errors' => ['Date is required', 'Time is required'],
            ]);

        $this->attendanceGateway->expects($this->never())
            ->method('checkIn');

        $result = $this->attendanceService->checkIn(100, 2025, '', '', 1);

        $this->assertFalse($result['success'], 'Check-in should fail with invalid data');
        $this->assertCount(2, $result['errors'], 'Should have validation errors');
    }

    /**
     * Test check-in fails when gateway returns false.
     */
    public function testCheckInFailsWhenGatewayFails(): void
    {
        $this->validator->expects($this->once())
            ->method('validateCheckIn')
            ->willReturn(['valid' => true, 'errors' => []]);

        $this->attendanceGateway->expects($this->once())
            ->method('checkIn')
            ->willReturn(false);

        $result = $this->attendanceService->checkIn(100, 2025, '2025-01-15', '08:00:00', 1);

        $this->assertFalse($result['success'], 'Check-in should fail when gateway fails');
        $this->assertContains('Failed to record check-in', $result['errors']);
    }

    // =========================================================================
    // CHECK-OUT TESTS
    // =========================================================================

    /**
     * Test successful check-out with valid data.
     */
    public function testSuccessfulCheckOutWithValidData(): void
    {
        $gibbonCareAttendanceID = 1;
        $time = '17:00:00';
        $checkOutByID = 1;

        $this->validator->expects($this->once())
            ->method('validateCheckOut')
            ->willReturn(['valid' => true, 'errors' => []]);

        $this->attendanceGateway->expects($this->once())
            ->method('checkOut')
            ->with($gibbonCareAttendanceID, $time, $checkOutByID, false, null, null, false, null)
            ->willReturn(true);

        $result = $this->attendanceService->checkOut($gibbonCareAttendanceID, $time, $checkOutByID);

        $this->assertTrue($result['success'], 'Check-out should succeed');
        $this->assertEmpty($result['errors'], 'Should have no errors');
    }

    /**
     * Test check-out with early departure and pickup person.
     */
    public function testCheckOutWithEarlyDepartureAndPickupPerson(): void
    {
        $gibbonCareAttendanceID = 1;
        $time = '15:00:00';
        $checkOutByID = 1;
        $earlyDeparture = true;
        $pickupPersonName = 'Jane Doe';
        $gibbonCareAuthorizedPickupID = 5;
        $pickupVerified = true;
        $notes = 'Doctor appointment';

        $this->validator->expects($this->once())
            ->method('validateCheckOut')
            ->willReturn(['valid' => true, 'errors' => []]);

        $this->attendanceGateway->expects($this->once())
            ->method('checkOut')
            ->with(
                $gibbonCareAttendanceID,
                $time,
                $checkOutByID,
                $earlyDeparture,
                $pickupPersonName,
                $gibbonCareAuthorizedPickupID,
                $pickupVerified,
                $notes
            )
            ->willReturn(true);

        $result = $this->attendanceService->checkOut(
            $gibbonCareAttendanceID,
            $time,
            $checkOutByID,
            $earlyDeparture,
            $pickupPersonName,
            $gibbonCareAuthorizedPickupID,
            $pickupVerified,
            $notes
        );

        $this->assertTrue($result['success']);
    }

    /**
     * Test check-out fails with invalid data.
     */
    public function testCheckOutFailsWithInvalidData(): void
    {
        $this->validator->expects($this->once())
            ->method('validateCheckOut')
            ->willReturn([
                'valid' => false,
                'errors' => ['Attendance ID is required', 'Time is required'],
            ]);

        $this->attendanceGateway->expects($this->never())
            ->method('checkOut');

        $result = $this->attendanceService->checkOut(0, '', 1);

        $this->assertFalse($result['success'], 'Check-out should fail with invalid data');
        $this->assertCount(2, $result['errors'], 'Should have validation errors');
    }

    /**
     * Test check-out fails when gateway returns false.
     */
    public function testCheckOutFailsWhenGatewayFails(): void
    {
        $this->validator->expects($this->once())
            ->method('validateCheckOut')
            ->willReturn(['valid' => true, 'errors' => []]);

        $this->attendanceGateway->expects($this->once())
            ->method('checkOut')
            ->willReturn(false);

        $result = $this->attendanceService->checkOut(1, '17:00:00', 1);

        $this->assertFalse($result['success'], 'Check-out should fail when gateway fails');
        $this->assertContains('Failed to record check-out', $result['errors']);
    }

    // =========================================================================
    // CHECK-IN STATUS TESTS
    // =========================================================================

    /**
     * Test isCheckedIn returns true when child is checked in.
     */
    public function testIsCheckedInReturnsTrueWhenChildIsCheckedIn(): void
    {
        $gibbonPersonID = 100;
        $date = '2025-01-15';
        $attendanceRecord = [
            'checkInTime' => '08:00:00',
            'checkOutTime' => null,
        ];

        $this->attendanceGateway->expects($this->once())
            ->method('getAttendanceByPersonAndDate')
            ->with($gibbonPersonID, $date)
            ->willReturn($attendanceRecord);

        $result = $this->attendanceService->isCheckedIn($gibbonPersonID, $date);

        $this->assertTrue($result, 'Child should be checked in');
    }

    /**
     * Test isCheckedIn returns false when child is not checked in.
     */
    public function testIsCheckedInReturnsFalseWhenChildIsNotCheckedIn(): void
    {
        $gibbonPersonID = 100;
        $date = '2025-01-15';

        $this->attendanceGateway->expects($this->once())
            ->method('getAttendanceByPersonAndDate')
            ->with($gibbonPersonID, $date)
            ->willReturn([]);

        $result = $this->attendanceService->isCheckedIn($gibbonPersonID, $date);

        $this->assertFalse($result, 'Child should not be checked in');
    }

    /**
     * Test isCheckedIn returns false when child is already checked out.
     */
    public function testIsCheckedInReturnsFalseWhenChildIsCheckedOut(): void
    {
        $gibbonPersonID = 100;
        $date = '2025-01-15';
        $attendanceRecord = [
            'checkInTime' => '08:00:00',
            'checkOutTime' => '17:00:00',
        ];

        $this->attendanceGateway->expects($this->once())
            ->method('getAttendanceByPersonAndDate')
            ->with($gibbonPersonID, $date)
            ->willReturn($attendanceRecord);

        $result = $this->attendanceService->isCheckedIn($gibbonPersonID, $date);

        $this->assertFalse($result, 'Child should not be checked in after check-out');
    }

    // =========================================================================
    // HOURS CALCULATION TESTS
    // =========================================================================

    /**
     * Test calculateHours returns correct hours for full day.
     */
    public function testCalculateHoursReturnsCorrectHoursForFullDay(): void
    {
        $attendance = [
            'checkInTime' => '2025-01-15 08:00:00',
            'checkOutTime' => '2025-01-15 17:00:00',
        ];

        $hours = $this->attendanceService->calculateHours($attendance);

        $this->assertEquals(9.0, $hours, 'Should calculate 9 hours');
    }

    /**
     * Test calculateHours returns correct hours for partial day.
     */
    public function testCalculateHoursReturnsCorrectHoursForPartialDay(): void
    {
        $attendance = [
            'checkInTime' => '2025-01-15 09:30:00',
            'checkOutTime' => '2025-01-15 14:45:00',
        ];

        $hours = $this->attendanceService->calculateHours($attendance);

        $this->assertEquals(5.25, $hours, 'Should calculate 5.25 hours (5 hours 15 minutes)');
    }

    /**
     * Test calculateHours returns null when check-out time is missing.
     */
    public function testCalculateHoursReturnsNullWhenCheckOutTimeMissing(): void
    {
        $attendance = [
            'checkInTime' => '2025-01-15 08:00:00',
            'checkOutTime' => null,
        ];

        $hours = $this->attendanceService->calculateHours($attendance);

        $this->assertNull($hours, 'Should return null when check-out time is missing');
    }

    /**
     * Test calculateHours returns null when check-in time is missing.
     */
    public function testCalculateHoursReturnsNullWhenCheckInTimeMissing(): void
    {
        $attendance = [
            'checkInTime' => null,
            'checkOutTime' => '2025-01-15 17:00:00',
        ];

        $hours = $this->attendanceService->calculateHours($attendance);

        $this->assertNull($hours, 'Should return null when check-in time is missing');
    }

    /**
     * Test calculateHours handles minutes and seconds correctly.
     */
    public function testCalculateHoursHandlesMinutesAndSeconds(): void
    {
        $attendance = [
            'checkInTime' => '2025-01-15 08:00:00',
            'checkOutTime' => '2025-01-15 09:30:30',
        ];

        $hours = $this->attendanceService->calculateHours($attendance);

        $expectedHours = 1 + (30 / 60) + (30 / 3600); // 1.508333...
        $this->assertEquals($expectedHours, $hours, 'Should calculate hours including minutes and seconds', 0.001);
    }

    // =========================================================================
    // QUERY TESTS
    // =========================================================================

    /**
     * Test that queryAttendance calls gateway correctly.
     */
    public function testQueryAttendanceCallsGateway(): void
    {
        $criteria = $this->createMock(QueryCriteria::class);
        $gibbonSchoolYearID = 2025;

        $this->attendanceGateway->expects($this->once())
            ->method('queryAttendance')
            ->with($criteria, $gibbonSchoolYearID);

        $this->attendanceService->queryAttendance($criteria, $gibbonSchoolYearID);
    }

    /**
     * Test that queryAttendanceByDate calls gateway correctly.
     */
    public function testQueryAttendanceByDateCallsGateway(): void
    {
        $criteria = $this->createMock(QueryCriteria::class);
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';

        $this->attendanceGateway->expects($this->once())
            ->method('queryAttendanceByDate')
            ->with($criteria, $gibbonSchoolYearID, $date);

        $this->attendanceService->queryAttendanceByDate($criteria, $gibbonSchoolYearID, $date);
    }

    /**
     * Test that queryAttendanceByPerson calls gateway correctly.
     */
    public function testQueryAttendanceByPersonCallsGateway(): void
    {
        $criteria = $this->createMock(QueryCriteria::class);
        $gibbonPersonID = 100;
        $gibbonSchoolYearID = 2025;

        $this->attendanceGateway->expects($this->once())
            ->method('queryAttendanceByPerson')
            ->with($criteria, $gibbonPersonID, $gibbonSchoolYearID);

        $this->attendanceService->queryAttendanceByPerson($criteria, $gibbonPersonID, $gibbonSchoolYearID);
    }

    /**
     * Test that getChildrenCurrentlyCheckedIn calls gateway correctly.
     */
    public function testGetChildrenCurrentlyCheckedInCallsGateway(): void
    {
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';

        $this->attendanceGateway->expects($this->once())
            ->method('selectChildrenCurrentlyCheckedIn')
            ->with($gibbonSchoolYearID, $date);

        $this->attendanceService->getChildrenCurrentlyCheckedIn($gibbonSchoolYearID, $date);
    }

    /**
     * Test that getChildrenNotCheckedIn calls gateway correctly.
     */
    public function testGetChildrenNotCheckedInCallsGateway(): void
    {
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';

        $this->attendanceGateway->expects($this->once())
            ->method('selectChildrenNotCheckedIn')
            ->with($gibbonSchoolYearID, $date);

        $this->attendanceService->getChildrenNotCheckedIn($gibbonSchoolYearID, $date);
    }

    // =========================================================================
    // STATISTICS TESTS
    // =========================================================================

    /**
     * Test that getAttendanceSummaryByDate returns summary statistics.
     */
    public function testGetAttendanceSummaryByDateReturnsStatistics(): void
    {
        $gibbonSchoolYearID = 2025;
        $date = '2025-01-15';
        $expectedSummary = [
            'totalChildren' => 50,
            'checkedIn' => 45,
            'checkedOut' => 30,
            'currentlyPresent' => 15,
        ];

        $this->attendanceGateway->expects($this->once())
            ->method('getAttendanceSummaryByDate')
            ->with($gibbonSchoolYearID, $date)
            ->willReturn($expectedSummary);

        $result = $this->attendanceService->getAttendanceSummaryByDate($gibbonSchoolYearID, $date);

        $this->assertEquals($expectedSummary, $result);
    }

    /**
     * Test that getAttendanceStatsByPersonAndDateRange returns statistics.
     */
    public function testGetAttendanceStatsByPersonAndDateRangeReturnsStatistics(): void
    {
        $gibbonPersonID = 100;
        $dateStart = '2025-01-01';
        $dateEnd = '2025-01-31';
        $expectedStats = [
            'totalDays' => 20,
            'averageHours' => 8.5,
            'lateArrivals' => 2,
            'earlyDepartures' => 1,
        ];

        $this->attendanceGateway->expects($this->once())
            ->method('getAttendanceStatsByPersonAndDateRange')
            ->with($gibbonPersonID, $dateStart, $dateEnd)
            ->willReturn($expectedStats);

        $result = $this->attendanceService->getAttendanceStatsByPersonAndDateRange($gibbonPersonID, $dateStart, $dateEnd);

        $this->assertEquals($expectedStats, $result);
    }
}
