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
 * Unit tests for IncidentNotificationService.
 *
 * These tests verify that the IncidentNotificationService class has the correct
 * structure, method signatures, and constants for incident notification handling.
 *
 * @covers \Gibbon\Module\CareTracking\Domain\IncidentNotificationService
 */
class IncidentNotificationServiceTest extends TestCase
{
    /**
     * The fully qualified class name for IncidentNotificationService.
     *
     * @var string
     */
    private const CLASS_NAME = 'Gibbon\\Module\\CareTracking\\Domain\\IncidentNotificationService';

    /**
     * Get reflection class instance for IncidentNotificationService.
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
            'IncidentNotificationService class should exist'
        );
    }

    /**
     * @test
     */
    public function constructorHasCorrectParameters(): void
    {
        $reflection = $this->getReflection();
        $constructor = $reflection->getConstructor();

        $this->assertNotNull($constructor, 'IncidentNotificationService should have a constructor');

        $params = $constructor->getParameters();
        $this->assertCount(3, $params, 'Constructor should have 3 parameters');

        $this->assertEquals('db', $params[0]->getName());
        $this->assertEquals('notificationGateway', $params[1]->getName());
        $this->assertEquals('incidentGateway', $params[2]->getName());
    }

    /**
     * @test
     */
    public function classHasRequiredProperties(): void
    {
        $reflection = $this->getReflection();

        $requiredProperties = [
            'db',
            'notificationGateway',
            'incidentGateway',
        ];

        foreach ($requiredProperties as $property) {
            $this->assertTrue(
                $reflection->hasProperty($property),
                sprintf('IncidentNotificationService should have property "%s"', $property)
            );
        }
    }

    // =========================================================================
    // NOTIFICATION TYPE CONSTANTS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function hasNotificationTypeConstants(): void
    {
        $reflection = $this->getReflection();

        $requiredConstants = [
            'TYPE_INCIDENT_PARENT',
            'TYPE_INCIDENT_DIRECTOR',
            'TYPE_INCIDENT_ESCALATION',
            'TYPE_PATTERN_ALERT',
        ];

        foreach ($requiredConstants as $constant) {
            $this->assertTrue(
                $reflection->hasConstant($constant),
                sprintf('IncidentNotificationService should have constant "%s"', $constant)
            );
        }
    }

    /**
     * @test
     */
    public function typeIncidentParentConstantHasCorrectValue(): void
    {
        $this->assertEquals(
            'incident_parent_notification',
            $this->getConstantValue('TYPE_INCIDENT_PARENT'),
            'TYPE_INCIDENT_PARENT should be "incident_parent_notification"'
        );
    }

    /**
     * @test
     */
    public function typeIncidentDirectorConstantHasCorrectValue(): void
    {
        $this->assertEquals(
            'incident_director_escalation',
            $this->getConstantValue('TYPE_INCIDENT_DIRECTOR'),
            'TYPE_INCIDENT_DIRECTOR should be "incident_director_escalation"'
        );
    }

    /**
     * @test
     */
    public function typeIncidentEscalationConstantHasCorrectValue(): void
    {
        $this->assertEquals(
            'incident_escalation',
            $this->getConstantValue('TYPE_INCIDENT_ESCALATION'),
            'TYPE_INCIDENT_ESCALATION should be "incident_escalation"'
        );
    }

    /**
     * @test
     */
    public function typePatternAlertConstantHasCorrectValue(): void
    {
        $this->assertEquals(
            'incident_pattern_alert',
            $this->getConstantValue('TYPE_PATTERN_ALERT'),
            'TYPE_PATTERN_ALERT should be "incident_pattern_alert"'
        );
    }

    /**
     * @test
     */
    public function hasImmediateEscalationSeveritiesConstant(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasConstant('IMMEDIATE_ESCALATION_SEVERITIES'),
            'IncidentNotificationService should have IMMEDIATE_ESCALATION_SEVERITIES constant'
        );

        $severities = $this->getConstantValue('IMMEDIATE_ESCALATION_SEVERITIES');
        $this->assertIsArray($severities, 'IMMEDIATE_ESCALATION_SEVERITIES should be an array');
        $this->assertContains('Critical', $severities, 'IMMEDIATE_ESCALATION_SEVERITIES should contain "Critical"');
        $this->assertContains('High', $severities, 'IMMEDIATE_ESCALATION_SEVERITIES should contain "High"');
    }

    // =========================================================================
    // PUBLIC METHOD TESTS - NOTIFICATION METHODS
    // =========================================================================

    /**
     * @test
     */
    public function hasNotifyParentMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('notifyParent'),
            'IncidentNotificationService should have notifyParent method'
        );

        $method = $reflection->getMethod('notifyParent');
        $this->assertTrue($method->isPublic(), 'notifyParent should be public');

        $params = $method->getParameters();
        $this->assertGreaterThanOrEqual(1, count($params), 'notifyParent should have at least 1 parameter');
        $this->assertEquals('gibbonCareIncidentID', $params[0]->getName());

        if (count($params) >= 2) {
            $this->assertEquals('channel', $params[1]->getName());
            $this->assertTrue($params[1]->isOptional(), 'channel should be optional');
        }
    }

    /**
     * @test
     */
    public function hasNotifyDirectorMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('notifyDirector'),
            'IncidentNotificationService should have notifyDirector method'
        );

        $method = $reflection->getMethod('notifyDirector');
        $this->assertTrue($method->isPublic(), 'notifyDirector should be public');

        $params = $method->getParameters();
        $this->assertGreaterThanOrEqual(1, count($params), 'notifyDirector should have at least 1 parameter');
        $this->assertEquals('gibbonCareIncidentID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function hasQueueEscalationMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('queueEscalation'),
            'IncidentNotificationService should have queueEscalation method'
        );

        $method = $reflection->getMethod('queueEscalation');
        $this->assertTrue($method->isPublic(), 'queueEscalation should be public');

        $params = $method->getParameters();
        $this->assertGreaterThanOrEqual(1, count($params), 'queueEscalation should have at least 1 parameter');
        $this->assertEquals('gibbonCareIncidentID', $params[0]->getName());
    }

    /**
     * @test
     */
    public function hasProcessPendingEscalationsMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('processPendingEscalations'),
            'IncidentNotificationService should have processPendingEscalations method'
        );

        $method = $reflection->getMethod('processPendingEscalations');
        $this->assertTrue($method->isPublic(), 'processPendingEscalations should be public');

        $params = $method->getParameters();
        $this->assertCount(0, $params, 'processPendingEscalations should have no parameters');
    }

    /**
     * @test
     */
    public function hasNotifyPatternDetectedMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('notifyPatternDetected'),
            'IncidentNotificationService should have notifyPatternDetected method'
        );

        $method = $reflection->getMethod('notifyPatternDetected');
        $this->assertTrue($method->isPublic(), 'notifyPatternDetected should be public');

        $params = $method->getParameters();
        $this->assertCount(3, $params, 'notifyPatternDetected should have 3 parameters');
        $this->assertEquals('gibbonPersonID', $params[0]->getName());
        $this->assertEquals('patternType', $params[1]->getName());
        $this->assertEquals('patternDetails', $params[2]->getName());
    }

    /**
     * @test
     */
    public function hasRequiresImmediateEscalationMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('requiresImmediateEscalation'),
            'IncidentNotificationService should have requiresImmediateEscalation method'
        );

        $method = $reflection->getMethod('requiresImmediateEscalation');
        $this->assertTrue($method->isPublic(), 'requiresImmediateEscalation should be public');

        $params = $method->getParameters();
        $this->assertCount(1, $params, 'requiresImmediateEscalation should have 1 parameter');
        $this->assertEquals('incident', $params[0]->getName());
    }

    // =========================================================================
    // PROTECTED METHOD TESTS - HELPER METHODS
    // =========================================================================

    /**
     * @test
     */
    public function hasGetChildDetailsMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('getChildDetails'),
            'IncidentNotificationService should have getChildDetails method'
        );

        $method = $reflection->getMethod('getChildDetails');
        $this->assertTrue($method->isProtected(), 'getChildDetails should be protected');
    }

    /**
     * @test
     */
    public function hasGetParentIDsForChildMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('getParentIDsForChild'),
            'IncidentNotificationService should have getParentIDsForChild method'
        );

        $method = $reflection->getMethod('getParentIDsForChild');
        $this->assertTrue($method->isProtected(), 'getParentIDsForChild should be protected');
    }

    /**
     * @test
     */
    public function hasGetDirectorIDsMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('getDirectorIDs'),
            'IncidentNotificationService should have getDirectorIDs method'
        );

        $method = $reflection->getMethod('getDirectorIDs');
        $this->assertTrue($method->isProtected(), 'getDirectorIDs should be protected');
    }

    /**
     * @test
     */
    public function hasBuildParentNotificationTitleMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('buildParentNotificationTitle'),
            'IncidentNotificationService should have buildParentNotificationTitle method'
        );

        $method = $reflection->getMethod('buildParentNotificationTitle');
        $this->assertTrue($method->isProtected(), 'buildParentNotificationTitle should be protected');

        $params = $method->getParameters();
        $this->assertCount(2, $params, 'buildParentNotificationTitle should have 2 parameters');
        $this->assertEquals('incident', $params[0]->getName());
        $this->assertEquals('child', $params[1]->getName());
    }

    /**
     * @test
     */
    public function hasBuildParentNotificationBodyMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('buildParentNotificationBody'),
            'IncidentNotificationService should have buildParentNotificationBody method'
        );

        $method = $reflection->getMethod('buildParentNotificationBody');
        $this->assertTrue($method->isProtected(), 'buildParentNotificationBody should be protected');

        $params = $method->getParameters();
        $this->assertCount(2, $params, 'buildParentNotificationBody should have 2 parameters');
    }

    /**
     * @test
     */
    public function hasBuildDirectorNotificationTitleMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('buildDirectorNotificationTitle'),
            'IncidentNotificationService should have buildDirectorNotificationTitle method'
        );

        $method = $reflection->getMethod('buildDirectorNotificationTitle');
        $this->assertTrue($method->isProtected(), 'buildDirectorNotificationTitle should be protected');

        $params = $method->getParameters();
        $this->assertCount(3, $params, 'buildDirectorNotificationTitle should have 3 parameters');
    }

    /**
     * @test
     */
    public function hasBuildDirectorNotificationBodyMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('buildDirectorNotificationBody'),
            'IncidentNotificationService should have buildDirectorNotificationBody method'
        );

        $method = $reflection->getMethod('buildDirectorNotificationBody');
        $this->assertTrue($method->isProtected(), 'buildDirectorNotificationBody should be protected');

        $params = $method->getParameters();
        $this->assertCount(3, $params, 'buildDirectorNotificationBody should have 3 parameters');
    }

    // =========================================================================
    // PROTECTED METHOD TESTS - ESCALATION HELPERS
    // =========================================================================

    /**
     * @test
     */
    public function hasMarkIncidentParentNotifiedMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('markIncidentParentNotified'),
            'IncidentNotificationService should have markIncidentParentNotified method'
        );

        $method = $reflection->getMethod('markIncidentParentNotified');
        $this->assertTrue($method->isProtected(), 'markIncidentParentNotified should be protected');
    }

    /**
     * @test
     */
    public function hasLogEscalationMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('logEscalation'),
            'IncidentNotificationService should have logEscalation method'
        );

        $method = $reflection->getMethod('logEscalation');
        $this->assertTrue($method->isProtected(), 'logEscalation should be protected');
    }

    /**
     * @test
     */
    public function hasGetExistingEscalationMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('getExistingEscalation'),
            'IncidentNotificationService should have getExistingEscalation method'
        );

        $method = $reflection->getMethod('getExistingEscalation');
        $this->assertTrue($method->isProtected(), 'getExistingEscalation should be protected');
    }

    /**
     * @test
     */
    public function hasInsertEscalationMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('insertEscalation'),
            'IncidentNotificationService should have insertEscalation method'
        );

        $method = $reflection->getMethod('insertEscalation');
        $this->assertTrue($method->isProtected(), 'insertEscalation should be protected');
    }

    /**
     * @test
     */
    public function hasGetPendingEscalationsMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('getPendingEscalations'),
            'IncidentNotificationService should have getPendingEscalations method'
        );

        $method = $reflection->getMethod('getPendingEscalations');
        $this->assertTrue($method->isProtected(), 'getPendingEscalations should be protected');
    }

    /**
     * @test
     */
    public function hasMarkEscalationSentMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('markEscalationSent'),
            'IncidentNotificationService should have markEscalationSent method'
        );

        $method = $reflection->getMethod('markEscalationSent');
        $this->assertTrue($method->isProtected(), 'markEscalationSent should be protected');
    }

    /**
     * @test
     */
    public function hasMarkEscalationFailedMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('markEscalationFailed'),
            'IncidentNotificationService should have markEscalationFailed method'
        );

        $method = $reflection->getMethod('markEscalationFailed');
        $this->assertTrue($method->isProtected(), 'markEscalationFailed should be protected');
    }

    /**
     * @test
     */
    public function hasMarkEscalationCancelledMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('markEscalationCancelled'),
            'IncidentNotificationService should have markEscalationCancelled method'
        );

        $method = $reflection->getMethod('markEscalationCancelled');
        $this->assertTrue($method->isProtected(), 'markEscalationCancelled should be protected');
    }

    /**
     * @test
     */
    public function hasGetEscalationReasonMethod(): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod('getEscalationReason'),
            'IncidentNotificationService should have getEscalationReason method'
        );

        $method = $reflection->getMethod('getEscalationReason');
        $this->assertTrue($method->isProtected(), 'getEscalationReason should be protected');
    }

    // =========================================================================
    // DATA PROVIDER TESTS
    // =========================================================================

    /**
     * Data provider for public notification methods.
     *
     * @return array
     */
    public static function publicMethodsProvider(): array
    {
        return [
            'notifyParent' => ['notifyParent'],
            'notifyDirector' => ['notifyDirector'],
            'queueEscalation' => ['queueEscalation'],
            'processPendingEscalations' => ['processPendingEscalations'],
            'notifyPatternDetected' => ['notifyPatternDetected'],
            'requiresImmediateEscalation' => ['requiresImmediateEscalation'],
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
            sprintf('IncidentNotificationService should have method %s', $methodName)
        );

        $method = $reflection->getMethod($methodName);
        $this->assertTrue(
            $method->isPublic(),
            sprintf('Method %s should be public', $methodName)
        );
    }

    /**
     * Data provider for protected helper methods.
     *
     * @return array
     */
    public static function protectedMethodsProvider(): array
    {
        return [
            'getChildDetails' => ['getChildDetails'],
            'getParentIDsForChild' => ['getParentIDsForChild'],
            'getDirectorIDs' => ['getDirectorIDs'],
            'buildParentNotificationTitle' => ['buildParentNotificationTitle'],
            'buildParentNotificationBody' => ['buildParentNotificationBody'],
            'buildDirectorNotificationTitle' => ['buildDirectorNotificationTitle'],
            'buildDirectorNotificationBody' => ['buildDirectorNotificationBody'],
            'markIncidentParentNotified' => ['markIncidentParentNotified'],
            'logEscalation' => ['logEscalation'],
            'getExistingEscalation' => ['getExistingEscalation'],
            'insertEscalation' => ['insertEscalation'],
            'getPendingEscalations' => ['getPendingEscalations'],
            'markEscalationSent' => ['markEscalationSent'],
            'markEscalationFailed' => ['markEscalationFailed'],
            'markEscalationCancelled' => ['markEscalationCancelled'],
            'getEscalationReason' => ['getEscalationReason'],
        ];
    }

    /**
     * @test
     * @dataProvider protectedMethodsProvider
     */
    public function protectedMethodExists(string $methodName): void
    {
        $reflection = $this->getReflection();

        $this->assertTrue(
            $reflection->hasMethod($methodName),
            sprintf('IncidentNotificationService should have method %s', $methodName)
        );

        $method = $reflection->getMethod($methodName);
        $this->assertTrue(
            $method->isProtected(),
            sprintf('Method %s should be protected', $methodName)
        );
    }

    // =========================================================================
    // NOTIFICATION TYPE CONSTANT VALUE TESTS
    // =========================================================================

    /**
     * Data provider for notification type constants.
     *
     * @return array
     */
    public static function notificationTypeConstantsProvider(): array
    {
        return [
            'TYPE_INCIDENT_PARENT' => ['TYPE_INCIDENT_PARENT', 'incident_parent_notification'],
            'TYPE_INCIDENT_DIRECTOR' => ['TYPE_INCIDENT_DIRECTOR', 'incident_director_escalation'],
            'TYPE_INCIDENT_ESCALATION' => ['TYPE_INCIDENT_ESCALATION', 'incident_escalation'],
            'TYPE_PATTERN_ALERT' => ['TYPE_PATTERN_ALERT', 'incident_pattern_alert'],
        ];
    }

    /**
     * @test
     * @dataProvider notificationTypeConstantsProvider
     */
    public function notificationTypeConstantHasCorrectValue(string $constantName, string $expectedValue): void
    {
        $this->assertEquals(
            $expectedValue,
            $this->getConstantValue($constantName),
            sprintf('%s should be "%s"', $constantName, $expectedValue)
        );
    }

    // =========================================================================
    // ESCALATION METHOD PARAMETER TESTS
    // =========================================================================

    /**
     * @test
     */
    public function queueEscalationHasCorrectParameters(): void
    {
        $reflection = $this->getReflection();
        $method = $reflection->getMethod('queueEscalation');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params));
        $this->assertEquals('gibbonCareIncidentID', $params[0]->getName());

        if (count($params) >= 2) {
            $this->assertEquals('escalationType', $params[1]->getName());
            $this->assertTrue($params[1]->isOptional(), 'escalationType should be optional');
        }

        if (count($params) >= 3) {
            $this->assertEquals('delayMinutes', $params[2]->getName());
            $this->assertTrue($params[2]->isOptional(), 'delayMinutes should be optional');
        }

        if (count($params) >= 4) {
            $this->assertEquals('additionalData', $params[3]->getName());
            $this->assertTrue($params[3]->isOptional(), 'additionalData should be optional');
        }
    }

    /**
     * @test
     */
    public function notifyDirectorHasCorrectParameters(): void
    {
        $reflection = $this->getReflection();
        $method = $reflection->getMethod('notifyDirector');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params));
        $this->assertEquals('gibbonCareIncidentID', $params[0]->getName());

        if (count($params) >= 2) {
            $this->assertEquals('reason', $params[1]->getName());
            $this->assertTrue($params[1]->isOptional(), 'reason should be optional');
        }

        if (count($params) >= 3) {
            $this->assertEquals('directorID', $params[2]->getName());
            $this->assertTrue($params[2]->isOptional(), 'directorID should be optional');
        }

        if (count($params) >= 4) {
            $this->assertEquals('channel', $params[3]->getName());
            $this->assertTrue($params[3]->isOptional(), 'channel should be optional');
        }
    }
}
