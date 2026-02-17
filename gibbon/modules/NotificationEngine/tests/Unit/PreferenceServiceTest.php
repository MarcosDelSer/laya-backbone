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
use Gibbon\Module\NotificationEngine\Service\PreferenceService;
use Gibbon\Module\NotificationEngine\Domain\NotificationGateway;

/**
 * Unit tests for PreferenceService.
 *
 * Tests user notification preference management including preference retrieval,
 * updates, bulk operations, and validation.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class PreferenceServiceTest extends TestCase
{
    /**
     * @var MockObject|NotificationGateway
     */
    protected $notificationGateway;

    /**
     * @var PreferenceService
     */
    protected $service;

    /**
     * Sample preference data for testing.
     *
     * @var array
     */
    protected $samplePreference;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock
        $this->notificationGateway = $this->createMock(NotificationGateway::class);

        // Create service with mocked dependency
        $this->service = new PreferenceService($this->notificationGateway);

        // Sample preference data
        $this->samplePreference = [
            'gibbonNotificationPreferenceID' => 1,
            'gibbonPersonID' => 100,
            'type' => 'childcare_invoice',
            'emailEnabled' => 'Y',
            'pushEnabled' => 'Y',
            'timestampCreated' => '2025-01-15 10:00:00',
            'timestampModified' => '2025-01-15 10:00:00',
        ];
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->notificationGateway = null;
        $this->service = null;
    }

    // =========================================================================
    // PREFERENCE RETRIEVAL TESTS
    // =========================================================================

    /**
     * Test getUserPreferences delegates to gateway.
     */
    public function testGetUserPreferencesDelegatesToGateway(): void
    {
        $expected = [
            $this->samplePreference,
            [
                'gibbonNotificationPreferenceID' => 2,
                'gibbonPersonID' => 100,
                'type' => 'attendance_alert',
                'emailEnabled' => 'Y',
                'pushEnabled' => 'N',
            ],
        ];

        $this->notificationGateway
            ->expects($this->once())
            ->method('selectPreferencesByPerson')
            ->with(100)
            ->willReturn($expected);

        $result = $this->service->getUserPreferences(100);
        $this->assertEquals($expected, $result);
    }

    /**
     * Test getPreference delegates to gateway.
     */
    public function testGetPreferenceDelegatesToGateway(): void
    {
        $this->notificationGateway
            ->expects($this->once())
            ->method('getPreference')
            ->with(100, 'childcare_invoice')
            ->willReturn($this->samplePreference);

        $result = $this->service->getPreference(100, 'childcare_invoice');
        $this->assertEquals($this->samplePreference, $result);
    }

    /**
     * Test isEmailEnabled delegates to gateway.
     */
    public function testIsEmailEnabledDelegatesToGateway(): void
    {
        $this->notificationGateway
            ->expects($this->once())
            ->method('isEmailEnabled')
            ->with(100, 'childcare_invoice')
            ->willReturn(true);

        $result = $this->service->isEmailEnabled(100, 'childcare_invoice');
        $this->assertTrue($result);
    }

    /**
     * Test isPushEnabled delegates to gateway.
     */
    public function testIsPushEnabledDelegatesToGateway(): void
    {
        $this->notificationGateway
            ->expects($this->once())
            ->method('isPushEnabled')
            ->with(100, 'childcare_invoice')
            ->willReturn(true);

        $result = $this->service->isPushEnabled(100, 'childcare_invoice');
        $this->assertTrue($result);
    }

    /**
     * Test getEnabledChannels with both channels enabled.
     */
    public function testGetEnabledChannelsWithBothEnabled(): void
    {
        $this->notificationGateway
            ->method('isEmailEnabled')
            ->willReturn(true);

        $this->notificationGateway
            ->method('isPushEnabled')
            ->willReturn(true);

        $result = $this->service->getEnabledChannels(100, 'childcare_invoice');

        $this->assertEquals(['email', 'push'], $result['channels']);
        $this->assertTrue($result['emailEnabled']);
        $this->assertTrue($result['pushEnabled']);
        $this->assertTrue($result['hasAnyChannel']);
    }

    /**
     * Test getEnabledChannels with only email enabled.
     */
    public function testGetEnabledChannelsWithOnlyEmailEnabled(): void
    {
        $this->notificationGateway
            ->method('isEmailEnabled')
            ->willReturn(true);

        $this->notificationGateway
            ->method('isPushEnabled')
            ->willReturn(false);

        $result = $this->service->getEnabledChannels(100, 'childcare_invoice');

        $this->assertEquals(['email'], $result['channels']);
        $this->assertTrue($result['emailEnabled']);
        $this->assertFalse($result['pushEnabled']);
        $this->assertTrue($result['hasAnyChannel']);
    }

    /**
     * Test getEnabledChannels with no channels enabled.
     */
    public function testGetEnabledChannelsWithNoneEnabled(): void
    {
        $this->notificationGateway
            ->method('isEmailEnabled')
            ->willReturn(false);

        $this->notificationGateway
            ->method('isPushEnabled')
            ->willReturn(false);

        $result = $this->service->getEnabledChannels(100, 'childcare_invoice');

        $this->assertEmpty($result['channels']);
        $this->assertFalse($result['emailEnabled']);
        $this->assertFalse($result['pushEnabled']);
        $this->assertFalse($result['hasAnyChannel']);
    }

    // =========================================================================
    // PREFERENCE MANAGEMENT TESTS
    // =========================================================================

    /**
     * Test setPreference converts boolean to Y/N format.
     */
    public function testSetPreferenceConvertsBooleanToYN(): void
    {
        $this->notificationGateway
            ->expects($this->once())
            ->method('setPreference')
            ->with(100, 'childcare_invoice', 'Y', 'N')
            ->willReturn(true);

        $result = $this->service->setPreference(100, 'childcare_invoice', true, false);
        $this->assertTrue($result);
    }

    /**
     * Test setPreference with both channels enabled.
     */
    public function testSetPreferenceWithBothEnabled(): void
    {
        $this->notificationGateway
            ->expects($this->once())
            ->method('setPreference')
            ->with(100, 'childcare_invoice', 'Y', 'Y')
            ->willReturn(true);

        $result = $this->service->setPreference(100, 'childcare_invoice', true, true);
        $this->assertTrue($result);
    }

    /**
     * Test enableEmail preserves existing push preference.
     */
    public function testEnableEmailPreservesExistingPushPreference(): void
    {
        $this->notificationGateway
            ->method('getPreference')
            ->willReturn([
                'emailEnabled' => 'N',
                'pushEnabled' => 'N',
            ]);

        $this->notificationGateway
            ->expects($this->once())
            ->method('setPreference')
            ->with(100, 'childcare_invoice', 'Y', 'N')
            ->willReturn(true);

        $result = $this->service->enableEmail(100, 'childcare_invoice');
        $this->assertTrue($result);
    }

    /**
     * Test disableEmail preserves existing push preference.
     */
    public function testDisableEmailPreservesExistingPushPreference(): void
    {
        $this->notificationGateway
            ->method('getPreference')
            ->willReturn([
                'emailEnabled' => 'Y',
                'pushEnabled' => 'Y',
            ]);

        $this->notificationGateway
            ->expects($this->once())
            ->method('setPreference')
            ->with(100, 'childcare_invoice', 'N', 'Y')
            ->willReturn(true);

        $result = $this->service->disableEmail(100, 'childcare_invoice');
        $this->assertTrue($result);
    }

    /**
     * Test enablePush preserves existing email preference.
     */
    public function testEnablePushPreservesExistingEmailPreference(): void
    {
        $this->notificationGateway
            ->method('getPreference')
            ->willReturn([
                'emailEnabled' => 'Y',
                'pushEnabled' => 'N',
            ]);

        $this->notificationGateway
            ->expects($this->once())
            ->method('setPreference')
            ->with(100, 'childcare_invoice', 'Y', 'Y')
            ->willReturn(true);

        $result = $this->service->enablePush(100, 'childcare_invoice');
        $this->assertTrue($result);
    }

    /**
     * Test disablePush preserves existing email preference.
     */
    public function testDisablePushPreservesExistingEmailPreference(): void
    {
        $this->notificationGateway
            ->method('getPreference')
            ->willReturn([
                'emailEnabled' => 'N',
                'pushEnabled' => 'Y',
            ]);

        $this->notificationGateway
            ->expects($this->once())
            ->method('setPreference')
            ->with(100, 'childcare_invoice', 'N', 'N')
            ->willReturn(true);

        $result = $this->service->disablePush(100, 'childcare_invoice');
        $this->assertTrue($result);
    }

    /**
     * Test deletePreference delegates to gateway.
     */
    public function testDeletePreferenceDelegatesToGateway(): void
    {
        $this->notificationGateway
            ->expects($this->once())
            ->method('deletePreference')
            ->with(1)
            ->willReturn(true);

        $result = $this->service->deletePreference(1);
        $this->assertTrue($result);
    }

    /**
     * Test resetAllPreferences deletes all user preferences.
     */
    public function testResetAllPreferencesDeletesAll(): void
    {
        $preferences = [
            ['gibbonNotificationPreferenceID' => 1],
            ['gibbonNotificationPreferenceID' => 2],
            ['gibbonNotificationPreferenceID' => 3],
        ];

        $this->notificationGateway
            ->method('selectPreferencesByPerson')
            ->willReturn($preferences);

        $this->notificationGateway
            ->expects($this->exactly(3))
            ->method('deletePreference')
            ->willReturn(true);

        $count = $this->service->resetAllPreferences(100);
        $this->assertEquals(3, $count);
    }

    /**
     * Test resetAllPreferences returns zero for user with no preferences.
     */
    public function testResetAllPreferencesReturnsZeroForNone(): void
    {
        $this->notificationGateway
            ->method('selectPreferencesByPerson')
            ->willReturn([]);

        $count = $this->service->resetAllPreferences(100);
        $this->assertEquals(0, $count);
    }

    // =========================================================================
    // BULK OPERATIONS TESTS
    // =========================================================================

    /**
     * Test setBulkPreferences updates multiple preferences.
     */
    public function testSetBulkPreferencesUpdatesMultiple(): void
    {
        $preferences = [
            'childcare_invoice' => ['emailEnabled' => true, 'pushEnabled' => false],
            'attendance_alert' => ['emailEnabled' => false, 'pushEnabled' => true],
            'incident_report' => ['emailEnabled' => true, 'pushEnabled' => true],
        ];

        $this->notificationGateway
            ->expects($this->exactly(3))
            ->method('setPreference')
            ->willReturn(true);

        $results = $this->service->setBulkPreferences(100, $preferences);

        $this->assertCount(3, $results);
        $this->assertTrue($results['childcare_invoice']['success']);
        $this->assertTrue($results['childcare_invoice']['emailEnabled']);
        $this->assertFalse($results['childcare_invoice']['pushEnabled']);
    }

    /**
     * Test setBulkPreferences handles partial failures.
     */
    public function testSetBulkPreferencesHandlesPartialFailures(): void
    {
        $preferences = [
            'childcare_invoice' => ['emailEnabled' => true, 'pushEnabled' => true],
            'attendance_alert' => ['emailEnabled' => true, 'pushEnabled' => true],
        ];

        $this->notificationGateway
            ->method('setPreference')
            ->willReturnOnConsecutiveCalls(true, false);

        $results = $this->service->setBulkPreferences(100, $preferences);

        $this->assertTrue($results['childcare_invoice']['success']);
        $this->assertFalse($results['attendance_alert']['success']);
    }

    /**
     * Test disableAllNotifications with specific types.
     */
    public function testDisableAllNotificationsWithSpecificTypes(): void
    {
        $types = ['childcare_invoice', 'attendance_alert'];

        $this->notificationGateway
            ->expects($this->exactly(2))
            ->method('setPreference')
            ->withConsecutive(
                [100, 'childcare_invoice', 'N', 'N'],
                [100, 'attendance_alert', 'N', 'N']
            )
            ->willReturn(true);

        $count = $this->service->disableAllNotifications(100, $types);
        $this->assertEquals(2, $count);
    }

    /**
     * Test disableAllNotifications fetches all types when none specified.
     */
    public function testDisableAllNotificationsFetchesAllTypes(): void
    {
        $templates = [
            ['type' => 'childcare_invoice'],
            ['type' => 'attendance_alert'],
            ['type' => 'incident_report'],
        ];

        $this->notificationGateway
            ->method('selectActiveTemplates')
            ->willReturn($templates);

        $this->notificationGateway
            ->expects($this->exactly(3))
            ->method('setPreference')
            ->willReturn(true);

        $count = $this->service->disableAllNotifications(100);
        $this->assertEquals(3, $count);
    }

    /**
     * Test enableAllNotifications with specific types.
     */
    public function testEnableAllNotificationsWithSpecificTypes(): void
    {
        $types = ['childcare_invoice', 'attendance_alert'];

        $this->notificationGateway
            ->expects($this->exactly(2))
            ->method('setPreference')
            ->withConsecutive(
                [100, 'childcare_invoice', 'Y', 'Y'],
                [100, 'attendance_alert', 'Y', 'Y']
            )
            ->willReturn(true);

        $count = $this->service->enableAllNotifications(100, $types);
        $this->assertEquals(2, $count);
    }

    /**
     * Test enableAllNotifications fetches all types when none specified.
     */
    public function testEnableAllNotificationsFetchesAllTypes(): void
    {
        $templates = [
            ['type' => 'childcare_invoice'],
            ['type' => 'attendance_alert'],
        ];

        $this->notificationGateway
            ->method('selectActiveTemplates')
            ->willReturn($templates);

        $this->notificationGateway
            ->expects($this->exactly(2))
            ->method('setPreference')
            ->willReturn(true);

        $count = $this->service->enableAllNotifications(100);
        $this->assertEquals(2, $count);
    }

    // =========================================================================
    // VALIDATION TESTS
    // =========================================================================

    /**
     * Test validatePreference accepts valid settings.
     */
    public function testValidatePreferenceAcceptsValidSettings(): void
    {
        $this->notificationGateway
            ->method('getTemplateByType')
            ->willReturn(['type' => 'childcare_invoice']);

        $result = $this->service->validatePreference('childcare_invoice', true, true);

        $this->assertTrue($result['isValid']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * Test validatePreference rejects invalid type.
     */
    public function testValidatePreferenceRejectsInvalidType(): void
    {
        $this->notificationGateway
            ->method('getTemplateByType')
            ->willReturn(false);

        $result = $this->service->validatePreference('invalid_type', true, true);

        $this->assertFalse($result['isValid']);
        $this->assertContains('Invalid notification type: invalid_type', $result['errors']);
    }

    /**
     * Test validatePreference requires at least one channel.
     */
    public function testValidatePreferenceRequiresAtLeastOneChannel(): void
    {
        $this->notificationGateway
            ->method('getTemplateByType')
            ->willReturn(['type' => 'childcare_invoice']);

        $result = $this->service->validatePreference('childcare_invoice', false, false);

        $this->assertFalse($result['isValid']);
        $this->assertContains('At least one notification channel must be enabled', $result['errors']);
    }

    /**
     * Test validatePreference accepts email-only preference.
     */
    public function testValidatePreferenceAcceptsEmailOnly(): void
    {
        $this->notificationGateway
            ->method('getTemplateByType')
            ->willReturn(['type' => 'childcare_invoice']);

        $result = $this->service->validatePreference('childcare_invoice', true, false);

        $this->assertTrue($result['isValid']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * Test validatePreference accepts push-only preference.
     */
    public function testValidatePreferenceAcceptsPushOnly(): void
    {
        $this->notificationGateway
            ->method('getTemplateByType')
            ->willReturn(['type' => 'childcare_invoice']);

        $result = $this->service->validatePreference('childcare_invoice', false, true);

        $this->assertTrue($result['isValid']);
        $this->assertEmpty($result['errors']);
    }

    /**
     * Test getPreferenceSummary calculates correct counts.
     */
    public function testGetPreferenceSummaryCalculatesCorrectCounts(): void
    {
        $preferences = [
            ['emailEnabled' => 'Y', 'pushEnabled' => 'Y'],
            ['emailEnabled' => 'Y', 'pushEnabled' => 'N'],
            ['emailEnabled' => 'N', 'pushEnabled' => 'Y'],
            ['emailEnabled' => 'N', 'pushEnabled' => 'N'],
        ];

        $this->notificationGateway
            ->method('selectPreferencesByPerson')
            ->willReturn($preferences);

        $summary = $this->service->getPreferenceSummary(100);

        $this->assertEquals(4, $summary['total']);
        $this->assertEquals(2, $summary['emailEnabled']);
        $this->assertEquals(2, $summary['emailDisabled']);
        $this->assertEquals(2, $summary['pushEnabled']);
        $this->assertEquals(2, $summary['pushDisabled']);
        $this->assertEquals(1, $summary['bothEnabled']);
        $this->assertEquals(1, $summary['bothDisabled']);
    }

    /**
     * Test getPreferenceSummary handles empty preferences.
     */
    public function testGetPreferenceSummaryHandlesEmptyPreferences(): void
    {
        $this->notificationGateway
            ->method('selectPreferencesByPerson')
            ->willReturn([]);

        $summary = $this->service->getPreferenceSummary(100);

        $this->assertEquals(0, $summary['total']);
        $this->assertEquals(0, $summary['emailEnabled']);
        $this->assertEquals(0, $summary['emailDisabled']);
        $this->assertEquals(0, $summary['pushEnabled']);
        $this->assertEquals(0, $summary['pushDisabled']);
        $this->assertEquals(0, $summary['bothEnabled']);
        $this->assertEquals(0, $summary['bothDisabled']);
    }
}
