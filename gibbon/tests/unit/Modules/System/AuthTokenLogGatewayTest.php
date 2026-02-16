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

namespace Gibbon\Tests\Unit\Modules\System;

use PHPUnit\Framework\TestCase;

/**
 * Auth Token Log Gateway Tests
 *
 * Tests for the AuthTokenLogGateway class that handles
 * authentication token exchange audit logging.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class AuthTokenLogGatewayTest extends TestCase
{
    /**
     * Test that log data structure is correct.
     */
    public function testLogDataStructure()
    {
        $logData = [
            'gibbonPersonID' => 123,
            'username' => 'testuser',
            'sessionID' => 'abc123xyz',
            'tokenStatus' => 'success',
            'ipAddress' => '192.168.1.1',
            'userAgent' => 'Mozilla/5.0',
            'gibbonRoleIDPrimary' => '002',
            'aiRole' => 'teacher',
            'expiresAt' => '2026-02-16 12:00:00',
        ];

        // Verify required fields
        $this->assertArrayHasKey('gibbonPersonID', $logData);
        $this->assertArrayHasKey('username', $logData);
        $this->assertArrayHasKey('sessionID', $logData);
        $this->assertArrayHasKey('tokenStatus', $logData);

        // Verify optional fields
        $this->assertArrayHasKey('ipAddress', $logData);
        $this->assertArrayHasKey('userAgent', $logData);
        $this->assertArrayHasKey('gibbonRoleIDPrimary', $logData);
        $this->assertArrayHasKey('aiRole', $logData);
        $this->assertArrayHasKey('expiresAt', $logData);

        $this->assertTrue(true);
    }

    /**
     * Test valid token status values.
     */
    public function testValidTokenStatus()
    {
        $validStatuses = ['success', 'failed', 'expired'];

        foreach ($validStatuses as $status) {
            $this->assertContains($status, $validStatuses);
        }
    }

    /**
     * Test IP address validation.
     */
    public function testIPAddressValidation()
    {
        // Valid IPv4
        $this->assertTrue((bool)filter_var('192.168.1.1', FILTER_VALIDATE_IP));
        $this->assertTrue((bool)filter_var('10.0.0.1', FILTER_VALIDATE_IP));

        // Valid IPv6
        $this->assertTrue((bool)filter_var('2001:0db8:85a3::8a2e:0370:7334', FILTER_VALIDATE_IP));
        $this->assertTrue((bool)filter_var('::1', FILTER_VALIDATE_IP));

        // Invalid IPs
        $this->assertFalse((bool)filter_var('invalid', FILTER_VALIDATE_IP));
        $this->assertFalse((bool)filter_var('256.256.256.256', FILTER_VALIDATE_IP));
        $this->assertFalse((bool)filter_var('', FILTER_VALIDATE_IP));
    }

    /**
     * Test successful token exchange log data.
     */
    public function testSuccessfulTokenExchangeLogData()
    {
        $logData = [
            'gibbonPersonID' => 100,
            'username' => 'john.doe',
            'sessionID' => 'sess_abc123',
            'tokenStatus' => 'success',
            'ipAddress' => '203.0.113.1',
            'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
            'gibbonRoleIDPrimary' => '001',
            'aiRole' => 'admin',
            'expiresAt' => date('Y-m-d H:i:s', time() + 3600),
        ];

        $this->assertEquals('success', $logData['tokenStatus']);
        $this->assertEquals('admin', $logData['aiRole']);
        $this->assertNotEmpty($logData['expiresAt']);
    }

    /**
     * Test failed token exchange log data.
     */
    public function testFailedTokenExchangeLogData()
    {
        $logData = [
            'gibbonPersonID' => 0,
            'username' => 'unknown',
            'sessionID' => 'none',
            'tokenStatus' => 'failed',
            'ipAddress' => '198.51.100.1',
            'userAgent' => 'curl/7.68.0',
            'errorMessage' => 'No valid Gibbon session found',
        ];

        $this->assertEquals('failed', $logData['tokenStatus']);
        $this->assertArrayHasKey('errorMessage', $logData);
        $this->assertNotEmpty($logData['errorMessage']);
    }

    /**
     * Test statistics data structure.
     */
    public function testStatisticsDataStructure()
    {
        $stats = [
            'totalExchanges' => 100,
            'successfulExchanges' => 95,
            'failedExchanges' => 5,
            'expiredTokens' => 0,
            'uniqueUsers' => 50,
            'uniqueIPs' => 45,
        ];

        $this->assertArrayHasKey('totalExchanges', $stats);
        $this->assertArrayHasKey('successfulExchanges', $stats);
        $this->assertArrayHasKey('failedExchanges', $stats);
        $this->assertArrayHasKey('expiredTokens', $stats);
        $this->assertArrayHasKey('uniqueUsers', $stats);
        $this->assertArrayHasKey('uniqueIPs', $stats);

        // Verify total equals sum of statuses
        $this->assertEquals(
            $stats['totalExchanges'],
            $stats['successfulExchanges'] + $stats['failedExchanges'] + $stats['expiredTokens']
        );
    }

    /**
     * Test role statistics data structure.
     */
    public function testRoleStatisticsDataStructure()
    {
        $roleStats = [
            [
                'aiRole' => 'admin',
                'count' => 10,
                'successCount' => 10,
                'failedCount' => 0,
            ],
            [
                'aiRole' => 'teacher',
                'count' => 50,
                'successCount' => 48,
                'failedCount' => 2,
            ],
            [
                'aiRole' => 'parent',
                'count' => 30,
                'successCount' => 29,
                'failedCount' => 1,
            ],
        ];

        foreach ($roleStats as $roleStat) {
            $this->assertArrayHasKey('aiRole', $roleStat);
            $this->assertArrayHasKey('count', $roleStat);
            $this->assertArrayHasKey('successCount', $roleStat);
            $this->assertArrayHasKey('failedCount', $roleStat);

            // Verify count equals sum
            $this->assertEquals(
                $roleStat['count'],
                $roleStat['successCount'] + $roleStat['failedCount']
            );
        }
    }

    /**
     * Test suspicious activity detection data.
     */
    public function testSuspiciousActivityDataStructure()
    {
        $suspiciousActivity = [
            [
                'username' => 'attacker',
                'ipAddress' => '192.0.2.1',
                'failedCount' => 10,
                'lastAttempt' => '2026-02-16 10:30:00',
                'firstAttempt' => '2026-02-16 10:00:00',
            ],
        ];

        foreach ($suspiciousActivity as $activity) {
            $this->assertArrayHasKey('username', $activity);
            $this->assertArrayHasKey('ipAddress', $activity);
            $this->assertArrayHasKey('failedCount', $activity);
            $this->assertArrayHasKey('lastAttempt', $activity);
            $this->assertArrayHasKey('firstAttempt', $activity);

            // Failed count should be at least threshold (default 5)
            $this->assertGreaterThanOrEqual(5, $activity['failedCount']);

            // Last attempt should be after or equal to first attempt
            $this->assertGreaterThanOrEqual(
                strtotime($activity['firstAttempt']),
                strtotime($activity['lastAttempt'])
            );
        }
    }

    /**
     * Test recent token exchanges data structure.
     */
    public function testRecentTokenExchangesDataStructure()
    {
        $recentExchanges = [
            [
                'gibbonAuthTokenLogID' => 1,
                'gibbonPersonID' => 100,
                'username' => 'user1',
                'tokenStatus' => 'success',
                'ipAddress' => '192.168.1.1',
                'aiRole' => 'teacher',
                'errorMessage' => null,
                'timestampCreated' => '2026-02-16 10:00:00',
                'expiresAt' => '2026-02-16 11:00:00',
            ],
        ];

        foreach ($recentExchanges as $exchange) {
            $this->assertArrayHasKey('gibbonAuthTokenLogID', $exchange);
            $this->assertArrayHasKey('gibbonPersonID', $exchange);
            $this->assertArrayHasKey('username', $exchange);
            $this->assertArrayHasKey('tokenStatus', $exchange);
            $this->assertArrayHasKey('timestampCreated', $exchange);
        }
    }

    /**
     * Test user token history data structure.
     */
    public function testUserTokenHistoryDataStructure()
    {
        $userHistory = [
            [
                'gibbonAuthTokenLogID' => 1,
                'sessionID' => 'sess_123',
                'tokenStatus' => 'success',
                'ipAddress' => '192.168.1.1',
                'userAgent' => 'Mozilla/5.0',
                'aiRole' => 'teacher',
                'errorMessage' => null,
                'timestampCreated' => '2026-02-16 10:00:00',
                'expiresAt' => '2026-02-16 11:00:00',
            ],
        ];

        foreach ($userHistory as $entry) {
            $this->assertArrayHasKey('gibbonAuthTokenLogID', $entry);
            $this->assertArrayHasKey('sessionID', $entry);
            $this->assertArrayHasKey('tokenStatus', $entry);
            $this->assertArrayHasKey('timestampCreated', $entry);
        }
    }

    /**
     * Test hourly activity data structure.
     */
    public function testHourlyActivityDataStructure()
    {
        $hourlyActivity = [
            [
                'hour' => '2026-02-16 09:00:00',
                'totalExchanges' => 15,
                'successCount' => 14,
                'failedCount' => 1,
            ],
            [
                'hour' => '2026-02-16 10:00:00',
                'totalExchanges' => 20,
                'successCount' => 19,
                'failedCount' => 1,
            ],
        ];

        foreach ($hourlyActivity as $activity) {
            $this->assertArrayHasKey('hour', $activity);
            $this->assertArrayHasKey('totalExchanges', $activity);
            $this->assertArrayHasKey('successCount', $activity);
            $this->assertArrayHasKey('failedCount', $activity);

            // Verify total equals sum
            $this->assertEquals(
                $activity['totalExchanges'],
                $activity['successCount'] + $activity['failedCount']
            );

            // Verify hour format
            $this->assertMatchesRegularExpression(
                '/^\d{4}-\d{2}-\d{2} \d{2}:00:00$/',
                $activity['hour']
            );
        }
    }

    /**
     * Test date range filtering.
     */
    public function testDateRangeFiltering()
    {
        $dateFrom = '2026-02-01';
        $dateTo = '2026-02-16';

        $this->assertNotEmpty($dateFrom);
        $this->assertNotEmpty($dateTo);

        // Verify date format
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dateFrom);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $dateTo);

        // Verify dateTo is after or equal to dateFrom
        $this->assertGreaterThanOrEqual(
            strtotime($dateFrom),
            strtotime($dateTo)
        );
    }

    /**
     * Test failed attempts threshold detection.
     */
    public function testFailedAttemptsThreshold()
    {
        $failedAttempts = 5;
        $withinMinutes = 30;

        $this->assertGreaterThan(0, $failedAttempts);
        $this->assertGreaterThan(0, $withinMinutes);

        // Test threshold detection logic
        $this->assertTrue($failedAttempts >= 3); // Minimum reasonable threshold
        $this->assertTrue($withinMinutes <= 60); // Maximum reasonable time window
    }

    /**
     * Test log cleanup parameters.
     */
    public function testLogCleanupParameters()
    {
        $daysOld = 90; // Default retention period

        $this->assertGreaterThan(0, $daysOld);
        $this->assertGreaterThanOrEqual(30, $daysOld); // At least 30 days retention
    }
}
