<?php

namespace Gibbon\Module\AISync\Tests;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Mockery;

/**
 * Base Test Case for AISync Module Tests
 *
 * Provides common functionality and utilities for all test cases.
 */
abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Clean up after each test
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        // Close all Mockery mocks
        if (class_exists('Mockery')) {
            Mockery::close();
        }
    }

    /**
     * Create a mock PDO instance for database testing
     *
     * @return \Mockery\MockInterface|\PDO
     */
    protected function createMockPDO()
    {
        return Mockery::mock('PDO');
    }

    /**
     * Create a mock PDOStatement instance
     *
     * @return \Mockery\MockInterface|\PDOStatement
     */
    protected function createMockStatement()
    {
        return Mockery::mock('PDOStatement');
    }

    /**
     * Create a mock Guzzle HTTP client
     *
     * @return \Mockery\MockInterface|\GuzzleHttp\Client
     */
    protected function createMockGuzzleClient()
    {
        if (!class_exists('Mockery')) {
            $this->markTestSkipped('Mockery is required for this test');
        }

        return Mockery::mock('GuzzleHttp\Client');
    }

    /**
     * Create a mock Guzzle HTTP response
     *
     * @param int $statusCode
     * @param string $body
     * @param array $headers
     * @return \Mockery\MockInterface|\Psr\Http\Message\ResponseInterface
     */
    protected function createMockResponse($statusCode = 200, $body = '', $headers = [])
    {
        $response = Mockery::mock('Psr\Http\Message\ResponseInterface');
        $response->shouldReceive('getStatusCode')->andReturn($statusCode);
        $response->shouldReceive('getBody')->andReturn($body);
        $response->shouldReceive('getHeaders')->andReturn($headers);

        return $response;
    }

    /**
     * Create a mock Guzzle promise
     *
     * @return \Mockery\MockInterface|\GuzzleHttp\Promise\PromiseInterface
     */
    protected function createMockPromise()
    {
        return Mockery::mock('GuzzleHttp\Promise\PromiseInterface');
    }

    /**
     * Assert that a string is valid JSON
     *
     * @param string $string
     * @param string $message
     */
    protected function assertValidJson($string, $message = '')
    {
        json_decode($string);
        $this->assertEquals(
            JSON_ERROR_NONE,
            json_last_error(),
            $message ?: 'Failed asserting that string is valid JSON'
        );
    }

    /**
     * Assert that a JWT token is valid
     *
     * @param string $token
     * @param string $message
     */
    protected function assertValidJWT($token, $message = '')
    {
        $parts = explode('.', $token);
        $this->assertCount(
            3,
            $parts,
            $message ?: 'Failed asserting that string is a valid JWT (must have 3 parts)'
        );

        // Verify each part is base64url encoded
        foreach ($parts as $part) {
            $this->assertMatchesRegularExpression(
                '/^[A-Za-z0-9_-]+$/',
                $part,
                'JWT part must be base64url encoded'
            );
        }
    }

    /**
     * Get a test payload for webhook testing
     *
     * @param string $eventType
     * @param string $entityType
     * @param int $entityID
     * @return array
     */
    protected function getTestPayload($eventType = 'test_event', $entityType = 'test_entity', $entityID = 123)
    {
        return [
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityID,
            'timestamp' => date('c'),
            'data' => [
                'test' => true,
                'foo' => 'bar',
            ],
        ];
    }

    /**
     * Assert that an array has the expected structure
     *
     * @param array $expected
     * @param array $actual
     * @param string $message
     */
    protected function assertArrayStructure(array $expected, array $actual, $message = '')
    {
        foreach ($expected as $key) {
            $this->assertArrayHasKey(
                $key,
                $actual,
                $message ?: "Failed asserting that array has key '{$key}'"
            );
        }
    }
}
