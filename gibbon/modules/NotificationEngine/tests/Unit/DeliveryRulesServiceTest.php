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

namespace Gibbon\Module\NotificationEngine\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gibbon\Module\NotificationEngine\Service\DeliveryRulesService;
use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;
use Gibbon\Domain\System\SettingGateway;

/**
 * Unit tests for DeliveryRulesService.
 *
 * Tests notification delivery rules including retry logic with exponential backoff,
 * delivery scheduling, queue health monitoring, and delivery eligibility.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class DeliveryRulesServiceTest extends TestCase
{
    /**
     * @var MockObject|NotificationGateway
     */
    protected $notificationGateway;

    /**
     * @var MockObject|SettingGateway
     */
    protected $settingGateway;

    /**
     * @var DeliveryRulesService
     */
    protected $service;

    /**
     * Sample notification data for testing.
     *
     * @var array
     */
    protected $sampleNotification;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mocks
        $this->notificationGateway = $this->createMock(NotificationGateway::class);
        $this->settingGateway = $this->createMock(SettingGateway::class);

        // Create service with mocked dependencies
        $this->service = new DeliveryRulesService(
            $this->notificationGateway,
            $this->settingGateway
        );

        // Sample notification data
        $this->sampleNotification = [
            'gibbonNotificationQueueID' => 1,
            'gibbonPersonID' => 100,
            'type' => 'childcare_invoice',
            'title' => 'New Invoice Available',
            'message' => 'Your invoice for January is ready',
            'status' => 'pending',
            'attempts' => 0,
            'lastAttemptAt' => null,
            'errorMessage' => null,
            'timestampCreated' => '2025-01-15 10:00:00',
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->notificationGateway = null;
        $this->settingGateway = null;
        $this->service = null;
    }

    // =========================================================================
    // RETRY LOGIC TESTS
    // =========================================================================

    /**
     * Test exponential backoff calculation for retry delays.
     */
    public function testCalculateRetryDelayWithExponentialBackoff(): void
    {
        // Base delay of 5 minutes
        $baseDelay = 5;

        // Attempt 1: 5 minutes (2^0 * 5)
        $this->assertEquals(5, $this->service->calculateRetryDelay(1, $baseDelay));

        // Attempt 2: 10 minutes (2^1 * 5)
        $this->assertEquals(10, $this->service->calculateRetryDelay(2, $baseDelay));

        // Attempt 3: 20 minutes (2^2 * 5)
        $this->assertEquals(20, $this->service->calculateRetryDelay(3, $baseDelay));

        // Attempt 4: 40 minutes (2^3 * 5)
        $this->assertEquals(40, $this->service->calculateRetryDelay(4, $baseDelay));
    }

    /**
     * Test retry delay calculation with zero attempts returns zero.
     */
    public function testCalculateRetryDelayWithZeroAttemptsReturnsZero(): void
    {
        $this->assertEquals(0, $this->service->calculateRetryDelay(0, 5));
    }

    /**
     * Test retry delay calculation with negative attempts returns zero.
     */
    public function testCalculateRetryDelayWithNegativeAttemptsReturnsZero(): void
    {
        $this->assertEquals(0, $this->service->calculateRetryDelay(-1, 5));
    }

    /**
     * Test retry delay uses setting value when no delay provided.
     */
    public function testCalculateRetryDelayUsesSettingValue(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->with('Notification Engine', 'retryDelayMinutes')
            ->willReturn('10');

        // Should use 10 from settings
        $this->assertEquals(10, $this->service->calculateRetryDelay(1));
    }

    /**
     * Test getNextRetryTime calculates correct timestamp.
     */
    public function testGetNextRetryTimeCalculatesCorrectTimestamp(): void
    {
        $notification = [
            'attempts' => 1,
            'lastAttemptAt' => '2025-01-15 10:00:00',
        ];

        // With 5 minute delay, next retry should be 10:05:00
        $nextRetry = $this->service->getNextRetryTime($notification, 5);
        $this->assertEquals('2025-01-15 10:05:00', $nextRetry);
    }

    /**
     * Test getNextRetryTime returns null for never-attempted notification.
     */
    public function testGetNextRetryTimeReturnsNullForNeverAttempted(): void
    {
        $notification = [
            'attempts' => 0,
            'lastAttemptAt' => null,
        ];

        $nextRetry = $this->service->getNextRetryTime($notification, 5);
        $this->assertNull($nextRetry);
    }

    /**
     * Test getNextRetryTime returns null when no last attempt timestamp.
     */
    public function testGetNextRetryTimeReturnsNullWhenNoLastAttempt(): void
    {
        $notification = [
            'attempts' => 2,
            'lastAttemptAt' => null,
        ];

        $nextRetry = $this->service->getNextRetryTime($notification, 5);
        $this->assertNull($nextRetry);
    }

    /**
     * Test isReadyForRetry returns true for never-attempted notification.
     */
    public function testIsReadyForRetryReturnsTrueForNeverAttempted(): void
    {
        $notification = [
            'attempts' => 0,
            'lastAttemptAt' => null,
        ];

        $this->assertTrue($this->service->isReadyForRetry($notification, 5));
    }

    /**
     * Test isReadyForRetry returns true when enough time has passed.
     */
    public function testIsReadyForRetryReturnsTrueWhenTimeHasPassed(): void
    {
        // Last attempt was 10 minutes ago
        $notification = [
            'attempts' => 1,
            'lastAttemptAt' => date('Y-m-d H:i:s', time() - 600),
        ];

        // With 5 minute delay, should be ready
        $this->assertTrue($this->service->isReadyForRetry($notification, 5));
    }

    /**
     * Test isReadyForRetry returns false when not enough time has passed.
     */
    public function testIsReadyForRetryReturnsFalseWhenNotEnoughTime(): void
    {
        // Last attempt was 2 minutes ago
        $notification = [
            'attempts' => 1,
            'lastAttemptAt' => date('Y-m-d H:i:s', time() - 120),
        ];

        // With 5 minute delay, should not be ready
        $this->assertFalse($this->service->isReadyForRetry($notification, 5));
    }

    /**
     * Test getRetryInfo returns comprehensive retry details.
     */
    public function testGetRetryInfoReturnsComprehensiveDetails(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['Notification Engine', 'maxRetryAttempts', '3'],
                ['Notification Engine', 'retryDelayMinutes', '5'],
            ]);

        $notification = [
            'gibbonNotificationQueueID' => 1,
            'attempts' => 2,
            'lastAttemptAt' => '2025-01-15 10:00:00',
            'status' => 'pending',
            'errorMessage' => 'Connection timeout',
        ];

        $this->notificationGateway
            ->method('getNotificationByID')
            ->with(1)
            ->willReturn($notification);

        $info = $this->service->getRetryInfo(1);

        $this->assertNotNull($info);
        $this->assertEquals(1, $info['gibbonNotificationQueueID']);
        $this->assertEquals(2, $info['currentAttempts']);
        $this->assertEquals(3, $info['maxAttempts']);
        $this->assertEquals(1, $info['retriesRemaining']);
        $this->assertTrue($info['hasMoreRetries']);
        $this->assertEquals('pending', $info['status']);
        $this->assertEquals('Connection timeout', $info['errorMessage']);
        $this->assertEquals(10, $info['currentDelayMinutes']); // 2nd attempt: 5 * 2^1 = 10
        $this->assertEquals(20, $info['nextDelayMinutes']); // 3rd attempt: 5 * 2^2 = 20
    }

    /**
     * Test getRetryInfo returns null for non-existent notification.
     */
    public function testGetRetryInfoReturnsNullForNonExistent(): void
    {
        $this->notificationGateway
            ->method('getNotificationByID')
            ->with(999)
            ->willReturn(false);

        $info = $this->service->getRetryInfo(999);
        $this->assertNull($info);
    }

    /**
     * Test hasExhaustedRetries returns false when retries remain.
     */
    public function testHasExhaustedRetriesReturnsFalseWhenRetriesRemain(): void
    {
        $notification = ['attempts' => 2];

        $this->settingGateway
            ->method('getSettingByScope')
            ->with('Notification Engine', 'maxRetryAttempts')
            ->willReturn('3');

        $this->assertFalse($this->service->hasExhaustedRetries($notification));
    }

    /**
     * Test hasExhaustedRetries returns true when max attempts reached.
     */
    public function testHasExhaustedRetriesReturnsTrueWhenMaxReached(): void
    {
        $notification = ['attempts' => 3];

        $this->settingGateway
            ->method('getSettingByScope')
            ->with('Notification Engine', 'maxRetryAttempts')
            ->willReturn('3');

        $this->assertTrue($this->service->hasExhaustedRetries($notification));
    }

    /**
     * Test hasExhaustedRetries returns true when attempts exceed max.
     */
    public function testHasExhaustedRetriesReturnsTrueWhenExceeded(): void
    {
        $notification = ['attempts' => 5];

        $this->settingGateway
            ->method('getSettingByScope')
            ->with('Notification Engine', 'maxRetryAttempts')
            ->willReturn('3');

        $this->assertTrue($this->service->hasExhaustedRetries($notification));
    }

    // =========================================================================
    // DELIVERY SCHEDULING TESTS
    // =========================================================================

    /**
     * Test getPendingNotificationsForDelivery uses settings.
     */
    public function testGetPendingNotificationsForDeliveryUsesSettings(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['Notification Engine', 'maxRetryAttempts', '3'],
                ['Notification Engine', 'retryDelayMinutes', '5'],
            ]);

        $this->notificationGateway
            ->expects($this->once())
            ->method('selectPendingNotifications')
            ->with(50, 3, 5)
            ->willReturn([]);

        $this->service->getPendingNotificationsForDelivery(50);
    }

    /**
     * Test getNotificationsPendingRetry delegates to gateway.
     */
    public function testGetNotificationsPendingRetryDelegatesToGateway(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->with('Notification Engine', 'retryDelayMinutes')
            ->willReturn('5');

        $expected = [
            ['gibbonNotificationQueueID' => 1, 'attempts' => 1],
            ['gibbonNotificationQueueID' => 2, 'attempts' => 2],
        ];

        $this->notificationGateway
            ->expects($this->once())
            ->method('selectNotificationsPendingRetry')
            ->with(5)
            ->willReturn($expected);

        $result = $this->service->getNotificationsPendingRetry();
        $this->assertEquals($expected, $result);
    }

    /**
     * Test shouldAttemptDelivery returns false for non-pending notification.
     */
    public function testShouldAttemptDeliveryReturnsFalseForNonPending(): void
    {
        $notification = [
            'status' => 'sent',
            'attempts' => 1,
        ];

        $this->assertFalse($this->service->shouldAttemptDelivery($notification));
    }

    /**
     * Test shouldAttemptDelivery returns false when retries exhausted.
     */
    public function testShouldAttemptDeliveryReturnsFalseWhenRetriesExhausted(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->with('Notification Engine', 'maxRetryAttempts')
            ->willReturn('3');

        $notification = [
            'status' => 'pending',
            'attempts' => 3,
        ];

        $this->assertFalse($this->service->shouldAttemptDelivery($notification));
    }

    /**
     * Test shouldAttemptDelivery returns false when not ready for retry.
     */
    public function testShouldAttemptDeliveryReturnsFalseWhenNotReady(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['Notification Engine', 'maxRetryAttempts', '3'],
                ['Notification Engine', 'retryDelayMinutes', '5'],
            ]);

        // Last attempt was 2 minutes ago (not enough time)
        $notification = [
            'status' => 'pending',
            'attempts' => 1,
            'lastAttemptAt' => date('Y-m-d H:i:s', time() - 120),
        ];

        $this->assertFalse($this->service->shouldAttemptDelivery($notification));
    }

    /**
     * Test shouldAttemptDelivery returns true when all conditions met.
     */
    public function testShouldAttemptDeliveryReturnsTrueWhenReady(): void
    {
        $this->settingGateway
            ->method('getSettingByScope')
            ->willReturnMap([
                ['Notification Engine', 'maxRetryAttempts', '3'],
                ['Notification Engine', 'retryDelayMinutes', '5'],
            ]);

        // Last attempt was 10 minutes ago (enough time)
        $notification = [
            'status' => 'pending',
            'attempts' => 1,
            'lastAttemptAt' => date('Y-m-d H:i:s', time() - 600),
        ];

        $this->assertTrue($this->service->shouldAttemptDelivery($notification));
    }

    // =========================================================================
    // QUEUE HEALTH MONITORING TESTS
    // =========================================================================

    /**
     * Test getRetryStatistics delegates to gateway.
     */
    public function testGetRetryStatisticsDelegatesToGateway(): void
    {
        $expected = [
            ['attempts' => 1, 'count' => 5],
            ['attempts' => 2, 'count' => 3],
            ['attempts' => 3, 'count' => 1],
        ];

        $this->notificationGateway
            ->expects($this->once())
            ->method('getRetryStatistics')
            ->willReturn($expected);

        $result = $this->service->getRetryStatistics();
        $this->assertEquals($expected, $result);
    }

    /**
     * Test getRetryHealthMetrics delegates to gateway.
     */
    public function testGetRetryHealthMetricsDelegatesToGateway(): void
    {
        $expected = [
            'total_retrying' => 10,
            'avg_attempts' => 1.5,
            'failure_rate' => 5.2,
            'retry_recovery_rate' => 15.3,
        ];

        $this->notificationGateway
            ->expects($this->once())
            ->method('getRetryHealthMetrics')
            ->willReturn($expected);

        $result = $this->service->getRetryHealthMetrics();
        $this->assertEquals($expected, $result);
    }

    /**
     * Test getQueueStatistics delegates to gateway.
     */
    public function testGetQueueStatisticsDelegatesToGateway(): void
    {
        $expected = [
            'pending' => 100,
            'sent' => 500,
            'failed' => 10,
        ];

        $this->notificationGateway
            ->expects($this->once())
            ->method('getQueueStatistics')
            ->willReturn($expected);

        $result = $this->service->getQueueStatistics();
        $this->assertEquals($expected, $result);
    }

    /**
     * Test assessQueueHealth returns healthy status for normal queue.
     */
    public function testAssessQueueHealthReturnsHealthyForNormalQueue(): void
    {
        $this->notificationGateway
            ->method('getQueueStatistics')
            ->willReturn([
                'pending' => 100,
                'sent' => 500,
                'failed' => 5,
            ]);

        $this->notificationGateway
            ->method('getRetryHealthMetrics')
            ->willReturn([
                'failure_rate' => 5.0,
                'retry_recovery_rate' => 10.0,
            ]);

        $health = $this->service->assessQueueHealth();

        $this->assertEquals('healthy', $health['status']);
        $this->assertEmpty($health['recommendations']);
    }

    /**
     * Test assessQueueHealth returns warning for growing queue.
     */
    public function testAssessQueueHealthReturnsWarningForGrowingQueue(): void
    {
        $this->notificationGateway
            ->method('getQueueStatistics')
            ->willReturn([
                'pending' => 600, // Over 500
                'sent' => 500,
                'failed' => 5,
            ]);

        $this->notificationGateway
            ->method('getRetryHealthMetrics')
            ->willReturn([
                'failure_rate' => 5.0,
                'retry_recovery_rate' => 10.0,
            ]);

        $health = $this->service->assessQueueHealth();

        $this->assertEquals('warning', $health['status']);
        $this->assertContains('Pending queue growing. Monitor processing rate.', $health['recommendations']);
    }

    /**
     * Test assessQueueHealth returns critical for large queue.
     */
    public function testAssessQueueHealthReturnsCriticalForLargeQueue(): void
    {
        $this->notificationGateway
            ->method('getQueueStatistics')
            ->willReturn([
                'pending' => 1500, // Over 1000
                'sent' => 500,
                'failed' => 5,
            ]);

        $this->notificationGateway
            ->method('getRetryHealthMetrics')
            ->willReturn([
                'failure_rate' => 5.0,
                'retry_recovery_rate' => 10.0,
            ]);

        $health = $this->service->assessQueueHealth();

        $this->assertEquals('critical', $health['status']);
        $this->assertContains('Large pending queue detected. Consider increasing processing capacity.', $health['recommendations']);
    }

    /**
     * Test assessQueueHealth detects high failure rate.
     */
    public function testAssessQueueHealthDetectsHighFailureRate(): void
    {
        $this->notificationGateway
            ->method('getQueueStatistics')
            ->willReturn([
                'pending' => 100,
                'sent' => 500,
                'failed' => 150,
            ]);

        $this->notificationGateway
            ->method('getRetryHealthMetrics')
            ->willReturn([
                'failure_rate' => 25.0, // Over 20%
                'retry_recovery_rate' => 10.0,
            ]);

        $health = $this->service->assessQueueHealth();

        $this->assertEquals('critical', $health['status']);
        $this->assertContains('High failure rate detected. Check email/push configuration.', $health['recommendations']);
    }

    /**
     * Test assessQueueHealth detects elevated failure rate.
     */
    public function testAssessQueueHealthDetectsElevatedFailureRate(): void
    {
        $this->notificationGateway
            ->method('getQueueStatistics')
            ->willReturn([
                'pending' => 100,
                'sent' => 500,
                'failed' => 50,
            ]);

        $this->notificationGateway
            ->method('getRetryHealthMetrics')
            ->willReturn([
                'failure_rate' => 15.0, // Between 10-20%
                'retry_recovery_rate' => 10.0,
            ]);

        $health = $this->service->assessQueueHealth();

        $this->assertEquals('warning', $health['status']);
        $this->assertContains('Elevated failure rate. Review error logs.', $health['recommendations']);
    }

    /**
     * Test assessQueueHealth detects high retry recovery rate.
     */
    public function testAssessQueueHealthDetectsHighRetryRecoveryRate(): void
    {
        $this->notificationGateway
            ->method('getQueueStatistics')
            ->willReturn([
                'pending' => 100,
                'sent' => 500,
                'failed' => 5,
            ]);

        $this->notificationGateway
            ->method('getRetryHealthMetrics')
            ->willReturn([
                'failure_rate' => 5.0,
                'retry_recovery_rate' => 35.0, // Over 30%
            ]);

        $health = $this->service->assessQueueHealth();

        $this->assertEquals('warning', $health['status']);
        $this->assertContains('Many notifications requiring retries. Check for intermittent issues.', $health['recommendations']);
    }

    // =========================================================================
    // DELIVERY ELIGIBILITY TESTS
    // =========================================================================

    /**
     * Test canDeliverToRecipient with both channels enabled.
     */
    public function testCanDeliverToRecipientWithBothChannelsEnabled(): void
    {
        $this->notificationGateway
            ->method('isEmailEnabled')
            ->willReturn(true);

        $this->notificationGateway
            ->method('isPushEnabled')
            ->willReturn(true);

        $result = $this->service->canDeliverToRecipient(100, 'childcare_invoice', 'both');

        $this->assertTrue($result['canDeliver']);
        $this->assertEmpty($result['reasons']);
        $this->assertEquals('both', $result['effectiveChannel']);
    }

    /**
     * Test canDeliverToRecipient with email disabled.
     */
    public function testCanDeliverToRecipientWithEmailDisabled(): void
    {
        $this->notificationGateway
            ->method('isEmailEnabled')
            ->willReturn(false);

        $this->notificationGateway
            ->method('isPushEnabled')
            ->willReturn(true);

        $result = $this->service->canDeliverToRecipient(100, 'childcare_invoice', 'email');

        $this->assertFalse($result['canDeliver']);
        $this->assertContains('Email notifications disabled for this type', $result['reasons']);
    }

    /**
     * Test canDeliverToRecipient with push disabled but email enabled.
     */
    public function testCanDeliverToRecipientWithPushDisabledEmailEnabled(): void
    {
        $this->notificationGateway
            ->method('isEmailEnabled')
            ->willReturn(true);

        $this->notificationGateway
            ->method('isPushEnabled')
            ->willReturn(false);

        $result = $this->service->canDeliverToRecipient(100, 'childcare_invoice', 'both');

        $this->assertTrue($result['canDeliver']);
        $this->assertEquals('email', $result['effectiveChannel']);
    }

    /**
     * Test canDeliverToRecipient with both channels disabled.
     */
    public function testCanDeliverToRecipientWithBothChannelsDisabled(): void
    {
        $this->notificationGateway
            ->method('isEmailEnabled')
            ->willReturn(false);

        $this->notificationGateway
            ->method('isPushEnabled')
            ->willReturn(false);

        $result = $this->service->canDeliverToRecipient(100, 'childcare_invoice', 'both');

        $this->assertFalse($result['canDeliver']);
        $this->assertEquals('none', $result['effectiveChannel']);
    }

    /**
     * Test determineEffectiveChannel with both enabled.
     */
    public function testDetermineEffectiveChannelWithBothEnabled(): void
    {
        $this->notificationGateway
            ->method('isEmailEnabled')
            ->willReturn(true);

        $this->notificationGateway
            ->method('isPushEnabled')
            ->willReturn(true);

        $channel = $this->service->determineEffectiveChannel(100, 'childcare_invoice', 'both');
        $this->assertEquals('both', $channel);
    }

    /**
     * Test determineEffectiveChannel with only email enabled.
     */
    public function testDetermineEffectiveChannelWithOnlyEmailEnabled(): void
    {
        $this->notificationGateway
            ->method('isEmailEnabled')
            ->willReturn(true);

        $this->notificationGateway
            ->method('isPushEnabled')
            ->willReturn(false);

        $channel = $this->service->determineEffectiveChannel(100, 'childcare_invoice', 'both');
        $this->assertEquals('email', $channel);
    }

    /**
     * Test determineEffectiveChannel with only push enabled.
     */
    public function testDetermineEffectiveChannelWithOnlyPushEnabled(): void
    {
        $this->notificationGateway
            ->method('isEmailEnabled')
            ->willReturn(false);

        $this->notificationGateway
            ->method('isPushEnabled')
            ->willReturn(true);

        $channel = $this->service->determineEffectiveChannel(100, 'childcare_invoice', 'both');
        $this->assertEquals('push', $channel);
    }

    /**
     * Test determineEffectiveChannel with none enabled.
     */
    public function testDetermineEffectiveChannelWithNoneEnabled(): void
    {
        $this->notificationGateway
            ->method('isEmailEnabled')
            ->willReturn(false);

        $this->notificationGateway
            ->method('isPushEnabled')
            ->willReturn(false);

        $channel = $this->service->determineEffectiveChannel(100, 'childcare_invoice', 'both');
        $this->assertEquals('none', $channel);
    }

    /**
     * Test determineEffectiveChannel respects requested channel.
     */
    public function testDetermineEffectiveChannelRespectsRequestedChannel(): void
    {
        $this->notificationGateway
            ->method('isEmailEnabled')
            ->willReturn(true);

        $this->notificationGateway
            ->method('isPushEnabled')
            ->willReturn(true);

        // Request only email
        $channel = $this->service->determineEffectiveChannel(100, 'childcare_invoice', 'email');
        $this->assertEquals('email', $channel);

        // Request only push
        $channel = $this->service->determineEffectiveChannel(100, 'childcare_invoice', 'push');
        $this->assertEquals('push', $channel);
    }
}
