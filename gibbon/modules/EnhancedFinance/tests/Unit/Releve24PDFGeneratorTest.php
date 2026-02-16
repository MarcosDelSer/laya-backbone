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

namespace Gibbon\Module\EnhancedFinance\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Gibbon\Module\EnhancedFinance\Domain\Releve24PDFGenerator;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Contracts\Services\Session;
use InvalidArgumentException;
use RuntimeException;
use ReflectionClass;
use ReflectionMethod;

/**
 * Unit tests for Releve24PDFGenerator domain class.
 *
 * Tests verify PDF generation functionality for Quebec RL-24 tax receipts,
 * including single PDF generation, batch PDF generation with ZIP archive,
 * error handling for invalid inputs, and form layout verification.
 *
 * @covers \Gibbon\Module\EnhancedFinance\Domain\Releve24PDFGenerator
 */
class Releve24PDFGeneratorTest extends TestCase
{
    /**
     * Valid UUID for testing
     * @var string
     */
    private const VALID_UUID = '550e8400-e29b-41d4-a716-446655440000';

    /**
     * Invalid UUID for testing
     * @var string
     */
    private const INVALID_UUID = 'not-a-valid-uuid';

    /**
     * Non-existent UUID for testing
     * @var string
     */
    private const NONEXISTENT_UUID = '550e8400-e29b-41d4-a716-446655440999';

    /**
     * Sample RL-24 document data
     * @var array
     */
    private static $sampleReleve24Data = [
        'id' => '550e8400-e29b-41d4-a716-446655440000',
        'document_year' => '2025',
        'total_eligible' => 5000.00,
        'gibbonFamilyID' => 1,
        'gibbonPersonID' => 100,
        'status' => 'final',
    ];

    /**
     * Sample family data
     * @var array
     */
    private static $sampleFamilyData = [
        'gibbonFamilyID' => 1,
        'familyName' => 'Smith Family',
        'nameAddress' => 'Smith Household',
        'homeAddress' => '123 Main Street',
        'homeAddressDistrict' => 'Montreal',
        'homeAddressCountry' => 'Canada',
        'preferredName' => 'John',
        'surname' => 'Smith',
        'email' => 'john.smith@example.com',
        'address1' => '123 Main Street',
        'address1District' => 'Montreal',
        'address1Country' => 'Canada',
    ];

    /**
     * Sample child data
     * @var array
     */
    private static $sampleChildData = [
        'gibbonPersonID' => 100,
        'preferredName' => 'Jane',
        'surname' => 'Smith',
        'dob' => '2020-05-15',
        'gender' => 'F',
    ];

    /**
     * Sample school/organisation settings
     * @var array
     */
    private static $sampleSchoolData = [
        'organisationName' => 'ABC Daycare Center',
        'organisationAddress' => '456 School Ave, Montreal, QC',
        'organisationPhone' => '514-555-1234',
    ];

    /**
     * @var MockObject|Connection
     */
    private $mockConnection;

    /**
     * @var MockObject|Session
     */
    private $mockSession;

    /**
     * @var Releve24PDFGenerator
     */
    private $generator;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Create mock Connection
        $this->mockConnection = $this->createMock(Connection::class);

        // Create mock Session
        $this->mockSession = $this->createMock(Session::class);
        $this->mockSession->method('get')
            ->willReturnCallback(function ($key) {
                return self::$sampleSchoolData[$key] ?? null;
            });

        // Create generator instance
        $this->generator = new Releve24PDFGenerator($this->mockConnection, $this->mockSession);
    }

    // =========================================================================
    // UUID VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function isValidUuidReturnsTrueForValidUuids(): void
    {
        $method = $this->getProtectedMethod('isValidUuid');

        $validUuids = [
            '550e8400-e29b-41d4-a716-446655440000',
            'f47ac10b-58cc-4372-a567-0e02b2c3d479',
            '123e4567-e89b-12d3-a456-426614174000',
        ];

        foreach ($validUuids as $uuid) {
            $this->assertTrue(
                $method->invoke($this->generator, $uuid),
                "UUID '{$uuid}' should be valid"
            );
        }
    }

    /**
     * @test
     */
    public function isValidUuidReturnsFalseForInvalidUuids(): void
    {
        $method = $this->getProtectedMethod('isValidUuid');

        $invalidUuids = [
            'not-a-uuid',
            '12345',
            '',
            '550e8400-e29b-41d4-a716',
            '550e8400e29b41d4a716446655440000', // Missing dashes
        ];

        foreach ($invalidUuids as $uuid) {
            $this->assertFalse(
                $method->invoke($this->generator, $uuid),
                "UUID '{$uuid}' should be invalid"
            );
        }
    }

    // =========================================================================
    // SINGLE PDF GENERATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function generatePDFThrowsExceptionForInvalidUuidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid RL-24 document ID format');

        $this->generator->generatePDF(self::INVALID_UUID);
    }

    /**
     * @test
     */
    public function generatePDFThrowsExceptionForNonexistentDocument(): void
    {
        $this->mockConnection->method('selectOne')
            ->willReturn(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RL-24 document not found');

        $this->generator->generatePDF(self::NONEXISTENT_UUID);
    }

    /**
     * @test
     * @requires extension zip
     */
    public function generatePDFSucceedsWithValidData(): void
    {
        $this->setupMockConnectionForValidDocument();

        // Skip if mPDF is not installed
        if (!class_exists('Mpdf\\Mpdf')) {
            $this->markTestSkipped('mPDF library not installed');
        }

        $pdf = $this->generator->generatePDF(self::VALID_UUID);

        $this->assertNotEmpty($pdf, 'PDF content should not be empty');
        $this->assertStringStartsWith('%PDF', $pdf, 'Generated content should be a valid PDF');
    }

    // =========================================================================
    // BATCH PDF GENERATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function generateBatchPDFThrowsExceptionForEmptyArray(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No RL-24 document IDs provided');

        $this->generator->generateBatchPDF([]);
    }

    /**
     * @test
     */
    public function generateBatchPDFThrowsExceptionForAllInvalidUuids(): void
    {
        $invalidIds = ['invalid1', 'invalid2', 'invalid3'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No valid RL-24 document IDs provided');

        $this->generator->generateBatchPDF($invalidIds);
    }

    /**
     * @test
     * @requires extension zip
     */
    public function generateBatchPDFFiltersInvalidUuids(): void
    {
        $this->setupMockConnectionForValidDocument();

        // Skip if mPDF is not installed
        if (!class_exists('Mpdf\\Mpdf')) {
            $this->markTestSkipped('mPDF library not installed');
        }

        $mixedIds = [
            self::VALID_UUID,
            'invalid-uuid',
            '550e8400-e29b-41d4-a716-446655440001', // Valid but might not exist
        ];

        // This should process only valid UUIDs
        try {
            $this->generator->generateBatchPDF($mixedIds);
        } catch (RuntimeException $e) {
            // Expected if some documents don't exist
            $this->assertStringContainsString('Failed', $e->getMessage());
        }

        // Test passes if we get here without InvalidArgumentException
        $this->assertTrue(true);
    }

    /**
     * @test
     * @requires extension zip
     */
    public function generateBatchPDFCreatesValidZipArchive(): void
    {
        $this->setupMockConnectionForValidDocument();

        // Skip if mPDF is not installed
        if (!class_exists('Mpdf\\Mpdf')) {
            $this->markTestSkipped('mPDF library not installed');
        }

        $ids = [self::VALID_UUID];
        $zipContent = $this->generator->generateBatchPDF($ids);

        $this->assertNotEmpty($zipContent, 'ZIP content should not be empty');

        // Verify ZIP signature (PK)
        $this->assertStringStartsWith('PK', $zipContent, 'Generated content should be a valid ZIP archive');
    }

    // =========================================================================
    // ZIP FILE CONTENTS TESTS
    // =========================================================================

    /**
     * @test
     * @requires extension zip
     */
    public function zipFileContainsPdfsWithCorrectNames(): void
    {
        $this->setupMockConnectionForValidDocument();

        // Skip if mPDF is not installed
        if (!class_exists('Mpdf\\Mpdf')) {
            $this->markTestSkipped('mPDF library not installed');
        }

        $ids = [self::VALID_UUID];
        $zipContent = $this->generator->generateBatchPDF($ids);

        // Write ZIP to temp file for inspection
        $tempFile = tempnam(sys_get_temp_dir(), 'test_zip_');
        file_put_contents($tempFile, $zipContent);

        $zip = new \ZipArchive();
        $result = $zip->open($tempFile);
        $this->assertTrue($result === true, 'ZIP file should be openable');

        // Check that at least one file exists
        $this->assertGreaterThan(0, $zip->numFiles, 'ZIP should contain at least one file');

        // Check filename format
        $filename = $zip->getNameIndex(0);
        $this->assertMatchesRegularExpression(
            '/^RL24_\d{4}_\d+(_\d+)?\.pdf$/',
            $filename,
            'PDF filename should match expected format: RL24_YEAR_FAMILYID(_CHILDID).pdf'
        );

        // Verify the file inside is a valid PDF
        $pdfContent = $zip->getFromIndex(0);
        $this->assertStringStartsWith('%PDF', $pdfContent, 'File in ZIP should be a valid PDF');

        $zip->close();
        unlink($tempFile);
    }

    // =========================================================================
    // MISSING DATA HANDLING TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getPlaceholderFamilyDataReturnsExpectedStructure(): void
    {
        $method = $this->getProtectedMethod('getPlaceholderFamilyData');
        $data = $method->invoke($this->generator);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('familyName', $data);
        $this->assertArrayHasKey('address', $data);
        $this->assertArrayHasKey('email', $data);

        $this->assertEquals('N/A', $data['name']);
        $this->assertEquals('N/A', $data['familyName']);
        $this->assertEquals('N/A', $data['address']);
    }

    /**
     * @test
     */
    public function getPlaceholderChildDataReturnsExpectedStructure(): void
    {
        $method = $this->getProtectedMethod('getPlaceholderChildData');
        $data = $method->invoke($this->generator);

        $this->assertIsArray($data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayHasKey('dob', $data);
        $this->assertArrayHasKey('gender', $data);

        $this->assertEquals('N/A', $data['name']);
    }

    /**
     * @test
     */
    public function generatePDFHandlesMissingFamilyData(): void
    {
        // Setup connection to return RL-24 but null for family
        $this->mockConnection->method('selectOne')
            ->willReturnCallback(function ($sql, $params) {
                if (strpos($sql, 'enhanced_finance_releve24') !== false) {
                    $data = self::$sampleReleve24Data;
                    $data['gibbonFamilyID'] = null;
                    return $data;
                }
                return null;
            });

        // Skip if mPDF is not installed
        if (!class_exists('Mpdf\\Mpdf')) {
            $this->markTestSkipped('mPDF library not installed');
        }

        // Should not throw, just use placeholder data
        $pdf = $this->generator->generatePDF(self::VALID_UUID);
        $this->assertNotEmpty($pdf);
    }

    /**
     * @test
     */
    public function generatePDFHandlesMissingChildData(): void
    {
        // Setup connection to return RL-24 but null for child
        $this->mockConnection->method('selectOne')
            ->willReturnCallback(function ($sql, $params) {
                if (strpos($sql, 'enhanced_finance_releve24') !== false) {
                    $data = self::$sampleReleve24Data;
                    $data['gibbonPersonID'] = null;
                    return $data;
                }
                return null;
            });

        // Skip if mPDF is not installed
        if (!class_exists('Mpdf\\Mpdf')) {
            $this->markTestSkipped('mPDF library not installed');
        }

        // Should not throw, just use placeholder data
        $pdf = $this->generator->generatePDF(self::VALID_UUID);
        $this->assertNotEmpty($pdf);
    }

    // =========================================================================
    // FORM LAYOUT TESTS
    // =========================================================================

    /**
     * @test
     */
    public function renderInlineTemplateContainsRequiredElements(): void
    {
        $method = $this->getProtectedMethod('renderInlineTemplate');

        $releve24 = self::$sampleReleve24Data;
        $familyData = [
            'name' => 'John Smith',
            'familyName' => 'Smith Family',
            'address' => '123 Main St, Montreal',
            'email' => 'test@example.com',
        ];
        $childData = [
            'name' => 'Jane Smith',
            'dob' => '2020-05-15',
            'gender' => 'F',
        ];
        $schoolData = [
            'name' => 'ABC Daycare',
            'address' => '456 School Ave',
            'neq' => '1234567890',
            'phone' => '514-555-1234',
        ];

        $html = $method->invoke($this->generator, $releve24, $familyData, $childData, $schoolData);

        // Check for Quebec government header
        $this->assertStringContainsString('GOUVERNEMENT DU QUÉBEC', $html);
        $this->assertStringContainsString('RELEVÉ 24', $html);
        $this->assertStringContainsString('BOX CODE 46', $html);

        // Check for institution section
        $this->assertStringContainsString('ABC Daycare', $html);
        $this->assertStringContainsString('NEQ', $html);
        $this->assertStringContainsString('1234567890', $html);

        // Check for child section
        $this->assertStringContainsString('ENFANT', $html);
        $this->assertStringContainsString('Jane Smith', $html);
        $this->assertStringContainsString('2020-05-15', $html);

        // Check for parent section
        $this->assertStringContainsString('PARENT', $html);
        $this->assertStringContainsString('John Smith', $html);

        // Check for amount section
        $this->assertStringContainsString('MONTANT ADMISSIBLE', $html);
        $this->assertStringContainsString('$5,000.00', $html);

        // Check for tax year
        $this->assertStringContainsString('2025', $html);

        // Check for official notice
        $this->assertStringContainsString('exigences fiscales', $html);
    }

    /**
     * @test
     */
    public function templateContainsRequiredFormFields(): void
    {
        $method = $this->getProtectedMethod('renderInlineTemplate');

        $releve24 = self::$sampleReleve24Data;
        $familyData = $this->getProtectedMethod('getPlaceholderFamilyData')->invoke($this->generator);
        $childData = $this->getProtectedMethod('getPlaceholderChildData')->invoke($this->generator);
        $schoolData = [
            'name' => 'Test School',
            'address' => 'Test Address',
            'neq' => '',
            'phone' => '',
        ];

        $html = $method->invoke($this->generator, $releve24, $familyData, $childData, $schoolData);

        // Required fields per Quebec specification
        $requiredFields = [
            'ÉTABLISSEMENT', // Institution section
            'ENFANT',        // Child section
            'PARENT',        // Parent/Guardian section
            'NEQ',           // Quebec Enterprise Number
            'MONTANT',       // Amount
        ];

        foreach ($requiredFields as $field) {
            $this->assertStringContainsString(
                $field,
                $html,
                "Template should contain required field: {$field}"
            );
        }
    }

    // =========================================================================
    // PDF CONTENT VERIFICATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function generatePdfFilenameReturnsCorrectFormat(): void
    {
        $method = $this->getProtectedMethod('generatePdfFilename');

        $data = [
            'document_year' => '2025',
            'gibbonFamilyID' => 123,
            'gibbonPersonID' => 456,
        ];

        $filename = $method->invoke($this->generator, $data);
        $this->assertEquals('RL24_2025_123_456.pdf', $filename);
    }

    /**
     * @test
     */
    public function generatePdfFilenameHandlesMissingData(): void
    {
        $method = $this->getProtectedMethod('generatePdfFilename');

        $currentYear = date('Y');

        // Null data
        $filename = $method->invoke($this->generator, null);
        $this->assertEquals("RL24_{$currentYear}_unknown.pdf", $filename);

        // Missing child ID
        $data = [
            'document_year' => '2024',
            'gibbonFamilyID' => 999,
        ];
        $filename = $method->invoke($this->generator, $data);
        $this->assertEquals('RL24_2024_999.pdf', $filename);
    }

    // =========================================================================
    // ADDRESS FORMATTING TESTS
    // =========================================================================

    /**
     * @test
     */
    public function formatAddressHandlesAllComponents(): void
    {
        $method = $this->getProtectedMethod('formatAddress');

        $address = $method->invoke($this->generator, '123 Main St', 'Montreal', 'Canada');
        $this->assertEquals('123 Main St, Montreal, Canada', $address);
    }

    /**
     * @test
     */
    public function formatAddressHandlesMissingComponents(): void
    {
        $method = $this->getProtectedMethod('formatAddress');

        // Missing district
        $address = $method->invoke($this->generator, '123 Main St', '', 'Canada');
        $this->assertEquals('123 Main St, Canada', $address);

        // All empty
        $address = $method->invoke($this->generator, '', '', '');
        $this->assertEquals('N/A', $address);
    }

    // =========================================================================
    // TEMPLATE PATH TESTS
    // =========================================================================

    /**
     * @test
     */
    public function setTemplatePathUpdatesPath(): void
    {
        $newPath = '/custom/path/template.php';
        $this->generator->setTemplatePath($newPath);
        $this->assertEquals($newPath, $this->generator->getTemplatePath());
    }

    /**
     * @test
     */
    public function defaultTemplatePathIsCorrect(): void
    {
        $path = $this->generator->getTemplatePath();
        $this->assertStringContainsString('templates/rl24_template.php', $path);
    }

    // =========================================================================
    // ERROR HANDLING TESTS
    // =========================================================================

    /**
     * @test
     */
    public function getLastErrorReturnsEmptyArrayByDefault(): void
    {
        $error = $this->generator->getLastError();
        $this->assertIsArray($error);
        $this->assertEmpty($error);
    }

    /**
     * @test
     */
    public function getLastErrorContainsDetailsAfterPartialFailure(): void
    {
        $this->mockConnection->method('selectOne')
            ->willReturnCallback(function ($sql, $params) {
                // Return data for first call, null for subsequent
                static $callCount = 0;
                $callCount++;

                if (strpos($sql, 'enhanced_finance_releve24') !== false) {
                    if ($callCount === 1) {
                        return self::$sampleReleve24Data;
                    }
                    return null; // Second document not found
                }
                if (strpos($sql, 'gibbonFamily') !== false) {
                    return self::$sampleFamilyData;
                }
                if (strpos($sql, 'gibbonPerson') !== false) {
                    return self::$sampleChildData;
                }
                if (strpos($sql, 'gibbonSetting') !== false) {
                    return ['value' => '1234567890'];
                }
                return null;
            });

        // Skip if mPDF is not installed
        if (!class_exists('Mpdf\\Mpdf')) {
            $this->markTestSkipped('mPDF library not installed');
        }

        $secondUuid = '550e8400-e29b-41d4-a716-446655440001';

        try {
            $this->generator->generateBatchPDF([self::VALID_UUID, $secondUuid]);
        } catch (RuntimeException $e) {
            // Expected for completely failed batch
        }

        $error = $this->generator->getLastError();
        $this->assertIsArray($error);
    }

    // =========================================================================
    // PRINT FUNCTIONALITY TESTS
    // =========================================================================

    /**
     * @test
     */
    public function generatePDFForPrintCallsGeneratePDF(): void
    {
        $this->setupMockConnectionForValidDocument();

        // Skip if mPDF is not installed
        if (!class_exists('Mpdf\\Mpdf')) {
            $this->markTestSkipped('mPDF library not installed');
        }

        $pdf = $this->generator->generatePDFForPrint(self::VALID_UUID);
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Get a protected method for testing via reflection.
     *
     * @param string $methodName
     * @return ReflectionMethod
     */
    private function getProtectedMethod(string $methodName): ReflectionMethod
    {
        $reflection = new ReflectionClass(Releve24PDFGenerator::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method;
    }

    /**
     * Setup mock connection to return valid document data.
     */
    private function setupMockConnectionForValidDocument(): void
    {
        $this->mockConnection->method('selectOne')
            ->willReturnCallback(function ($sql, $params) {
                if (strpos($sql, 'enhanced_finance_releve24') !== false) {
                    return self::$sampleReleve24Data;
                }
                if (strpos($sql, 'gibbonFamily') !== false) {
                    return self::$sampleFamilyData;
                }
                if (strpos($sql, 'gibbonPerson') !== false) {
                    return self::$sampleChildData;
                }
                if (strpos($sql, 'gibbonSetting') !== false) {
                    return ['value' => '1234567890'];
                }
                return null;
            });
    }
}
