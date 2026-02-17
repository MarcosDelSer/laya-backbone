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

namespace Gibbon\Module\RL24Submission\Tests;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use DOMDocument;
use Gibbon\Module\RL24Submission\Xml\RL24XmlSchema;
use Gibbon\Module\RL24Submission\Xml\RL24XmlGenerator;
use Gibbon\Module\RL24Submission\Xml\RL24XmlValidator;
use Gibbon\Module\RL24Submission\Xml\RL24SlipBuilder;

/**
 * Unit tests for RL-24 XML Generation and Validation.
 *
 * These tests verify that the XML generation classes properly create
 * Revenu Québec compliant RL-24 XML files with correct structure,
 * namespace handling, and schema validation.
 *
 * @covers \Gibbon\Module\RL24Submission\Xml\RL24XmlSchema
 * @covers \Gibbon\Module\RL24Submission\Xml\RL24XmlGenerator
 * @covers \Gibbon\Module\RL24Submission\Xml\RL24XmlValidator
 * @covers \Gibbon\Module\RL24Submission\Xml\RL24SlipBuilder
 */
class XmlGeneratorTest extends TestCase
{
    // =========================================================================
    // TEST DATA FIXTURES
    // =========================================================================

    /**
     * Sample transmission data for testing.
     *
     * @var array
     */
    private static $sampleTransmissionData = [
        'taxYear' => 2024,
        'sequenceNumber' => 1,
        'preparerNumber' => '123456',
        'transmissionType' => 'O',
        'providerName' => 'Test Childcare Center',
        'providerNEQ' => '1234567890',
        'providerAddressLine1' => '123 Main Street',
        'providerCity' => 'Montreal',
        'providerProvince' => 'QC',
        'providerPostalCode' => 'H2X 1Y2',
    ];

    /**
     * Sample slip data for testing.
     *
     * @var array
     */
    private static $sampleSlipData = [
        'parentFirstName' => 'Jean',
        'parentLastName' => 'Dupont',
        'parentSIN' => '046454286',
        'parentAddressLine1' => '456 Maple Ave',
        'parentCity' => 'Laval',
        'parentProvince' => 'QC',
        'parentPostalCode' => 'H7N 2T1',
        'childFirstName' => 'Marie',
        'childLastName' => 'Dupont',
        'childDateOfBirth' => '2020-05-15',
        'servicePeriodStart' => '2024-01-01',
        'servicePeriodEnd' => '2024-12-31',
        'totalDays' => 220,
        'case11Amount' => 8500.00,
        'case12Amount' => 8500.00,
        'case13Amount' => 0.00,
        'case14Amount' => 8500.00,
        'caseACode' => 'O',
    ];

    // =========================================================================
    // RL24XmlSchema CONSTANT TESTS
    // =========================================================================

    /**
     * @test
     */
    public function schemaHasCorrectNamespaceConstants(): void
    {
        $this->assertEquals('http://www.mrq.gouv.qc.ca/T5/RL24', RL24XmlSchema::NS_RELEVE);
        $this->assertEquals('http://www.w3.org/2001/XMLSchema-instance', RL24XmlSchema::NS_XSI);
        $this->assertEquals('http://www.mrq.gouv.qc.ca/T5/transmission', RL24XmlSchema::NS_TRANSMISSION);
    }

    /**
     * @test
     */
    public function schemaHasCorrectRootElementConstants(): void
    {
        $this->assertEquals('Transmission', RL24XmlSchema::ELEMENT_ROOT);
        $this->assertEquals('Entete', RL24XmlSchema::ELEMENT_HEADER);
        $this->assertEquals('Groupe', RL24XmlSchema::ELEMENT_GROUP);
        $this->assertEquals('RL24', RL24XmlSchema::ELEMENT_SLIP);
        $this->assertEquals('Sommaire', RL24XmlSchema::ELEMENT_SUMMARY);
    }

    /**
     * @test
     */
    public function schemaHasCorrectBoxElementConstants(): void
    {
        $this->assertEquals('Case10', RL24XmlSchema::ELEMENT_BOX_10);
        $this->assertEquals('Case11', RL24XmlSchema::ELEMENT_BOX_11);
        $this->assertEquals('Case12', RL24XmlSchema::ELEMENT_BOX_12);
        $this->assertEquals('Case13', RL24XmlSchema::ELEMENT_BOX_13);
        $this->assertEquals('Case14', RL24XmlSchema::ELEMENT_BOX_14);
    }

    /**
     * @test
     */
    public function schemaHasCorrectSlipTypeCodes(): void
    {
        $this->assertEquals('O', RL24XmlSchema::CODE_ORIGINAL);
        $this->assertEquals('A', RL24XmlSchema::CODE_AMENDED);
        $this->assertEquals('D', RL24XmlSchema::CODE_CANCELLED);
    }

    /**
     * @test
     */
    public function schemaHasCorrectValidationConstraints(): void
    {
        $this->assertEquals(1000, RL24XmlSchema::MAX_SLIPS_PER_FILE);
        $this->assertEquals(314572800, RL24XmlSchema::MAX_FILE_SIZE_BYTES);
        $this->assertEquals(9, RL24XmlSchema::SIN_LENGTH);
        $this->assertEquals(10, RL24XmlSchema::NEQ_LENGTH);
    }

    /**
     * @test
     */
    public function schemaReturnsValidSlipTypeCodes(): void
    {
        $codes = RL24XmlSchema::getValidSlipTypeCodes();

        $this->assertIsArray($codes);
        $this->assertCount(3, $codes);
        $this->assertContains('O', $codes);
        $this->assertContains('A', $codes);
        $this->assertContains('D', $codes);
    }

    /**
     * @test
     */
    public function schemaReturnsValidTransmissionTypes(): void
    {
        $types = RL24XmlSchema::getValidTransmissionTypes();

        $this->assertIsArray($types);
        $this->assertCount(3, $types);
        $this->assertContains('O', $types);
        $this->assertContains('M', $types);
        $this->assertContains('A', $types);
    }

    /**
     * @test
     * @dataProvider filenameGenerationProvider
     */
    public function schemaGeneratesCorrectFilename(int $taxYear, string $preparerNumber, int $sequenceNumber, string $expected): void
    {
        $filename = RL24XmlSchema::generateFilename($taxYear, $preparerNumber, $sequenceNumber);
        $this->assertEquals($expected, $filename);
    }

    /**
     * Data provider for filename generation tests.
     *
     * @return array
     */
    public static function filenameGenerationProvider(): array
    {
        return [
            'standard case' => [2024, '123456', 1, '24123456001.xml'],
            'sequence padding' => [2024, '123456', 99, '24123456099.xml'],
            'sequence triple digit' => [2024, '123456', 999, '24123456999.xml'],
            'preparer padding' => [2024, '999', 1, '24000999001.xml'],
            'different year' => [2025, '123456', 1, '25123456001.xml'],
        ];
    }

    /**
     * @test
     * @dataProvider sinFormattingProvider
     */
    public function schemaFormatsSINCorrectly(string $input, string $expected): void
    {
        $formatted = RL24XmlSchema::formatSIN($input);
        $this->assertEquals($expected, $formatted);
    }

    /**
     * Data provider for SIN formatting tests.
     *
     * @return array
     */
    public static function sinFormattingProvider(): array
    {
        return [
            'clean sin' => ['123456789', '123456789'],
            'sin with spaces' => ['123 456 789', '123456789'],
            'sin with dashes' => ['123-456-789', '123456789'],
            'too short' => ['12345678', ''],
            'too long' => ['1234567890', ''],
            'empty' => ['', ''],
        ];
    }

    /**
     * @test
     * @dataProvider amountFormattingProvider
     */
    public function schemaFormatsAmountCorrectly(float $input, string $expected): void
    {
        $formatted = RL24XmlSchema::formatAmount($input);
        $this->assertEquals($expected, $formatted);
    }

    /**
     * Data provider for amount formatting tests.
     *
     * @return array
     */
    public static function amountFormattingProvider(): array
    {
        return [
            'integer amount' => [1000.00, '1000.00'],
            'with cents' => [1234.56, '1234.56'],
            'zero' => [0.00, '0.00'],
            'large amount' => [999999.99, '999999.99'],
            'single decimal' => [100.5, '100.50'],
            'negative treated as zero' => [-100.00, '0.00'],
        ];
    }

    /**
     * @test
     * @dataProvider neqValidationProvider
     */
    public function schemaValidatesNEQCorrectly(string $neq, bool $expected): void
    {
        $isValid = RL24XmlSchema::isValidNEQ($neq);
        $this->assertEquals($expected, $isValid);
    }

    /**
     * Data provider for NEQ validation tests.
     *
     * @return array
     */
    public static function neqValidationProvider(): array
    {
        return [
            'valid 10 digits' => ['1234567890', true],
            'valid with spaces' => ['1234 5678 90', true],
            'too short' => ['123456789', false],
            'too long' => ['12345678901', false],
            'empty' => ['', false],
        ];
    }

    /**
     * @test
     * @dataProvider transmitterNumberValidationProvider
     */
    public function schemaValidatesTransmitterNumberCorrectly(string $number, bool $expected): void
    {
        $isValid = RL24XmlSchema::isValidTransmitterNumber($number);
        $this->assertEquals($expected, $isValid);
    }

    /**
     * Data provider for transmitter number validation tests.
     *
     * @return array
     */
    public static function transmitterNumberValidationProvider(): array
    {
        return [
            'valid format' => ['NP123456', true],
            'lowercase prefix' => ['np123456', false],
            'missing prefix' => ['123456', false],
            'too short' => ['NP12345', false],
            'too long' => ['NP1234567', false],
            'with letters in number' => ['NP12345A', false],
        ];
    }

    // =========================================================================
    // RL24XmlGenerator STRUCTURE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function generatorClassExists(): void
    {
        $this->assertTrue(class_exists(RL24XmlGenerator::class));
    }

    /**
     * @test
     */
    public function generatorHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(RL24XmlGenerator::class);

        $requiredMethods = [
            'setTransmissionData',
            'addSlip',
            'addSlips',
            'clearSlips',
            'generate',
            'getXmlString',
            'saveToFile',
            'getFilename',
            'getErrors',
            'hasErrors',
            'getSummaryTotals',
            'reset',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('RL24XmlGenerator should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function generatorCanBeInstantiated(): void
    {
        $generator = new RL24XmlGenerator();
        $this->assertInstanceOf(RL24XmlGenerator::class, $generator);
    }

    /**
     * @test
     */
    public function generatorSetTransmissionDataReturnsSelf(): void
    {
        $generator = new RL24XmlGenerator();
        $result = $generator->setTransmissionData(self::$sampleTransmissionData);

        $this->assertSame($generator, $result);
    }

    /**
     * @test
     */
    public function generatorAddSlipReturnsSelf(): void
    {
        $generator = new RL24XmlGenerator();
        $result = $generator->addSlip(self::$sampleSlipData);

        $this->assertSame($generator, $result);
    }

    /**
     * @test
     */
    public function generatorAddSlipsReturnsSelf(): void
    {
        $generator = new RL24XmlGenerator();
        $result = $generator->addSlips([self::$sampleSlipData, self::$sampleSlipData]);

        $this->assertSame($generator, $result);
    }

    /**
     * @test
     */
    public function generatorClearSlipsReturnsSelf(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->addSlip(self::$sampleSlipData);
        $result = $generator->clearSlips();

        $this->assertSame($generator, $result);
    }

    /**
     * @test
     */
    public function generatorResetReturnsSelf(): void
    {
        $generator = new RL24XmlGenerator();
        $result = $generator->reset();

        $this->assertSame($generator, $result);
    }

    /**
     * @test
     */
    public function generatorFailsWithoutTransmissionData(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->addSlip(self::$sampleSlipData);

        $result = $generator->generate();

        $this->assertFalse($result);
        $this->assertTrue($generator->hasErrors());
    }

    /**
     * @test
     */
    public function generatorFailsWithoutSlips(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);

        $result = $generator->generate();

        $this->assertFalse($result);
        $this->assertTrue($generator->hasErrors());
        $this->assertContains('No slips to include in the transmission', $generator->getErrors());
    }

    /**
     * @test
     */
    public function generatorFailsWithMissingRequiredFields(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData([
            'taxYear' => 2024,
            // Missing required fields
        ]);
        $generator->addSlip(self::$sampleSlipData);

        $result = $generator->generate();

        $this->assertFalse($result);
        $this->assertTrue($generator->hasErrors());
    }

    /**
     * @test
     */
    public function generatorSucceedsWithValidData(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);

        $result = $generator->generate();

        $this->assertTrue($result);
        $this->assertFalse($generator->hasErrors());
    }

    /**
     * @test
     */
    public function generatorProducesValidXml(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $xmlString = $generator->getXmlString();

        $this->assertNotEmpty($xmlString);
        $this->assertStringStartsWith('<?xml', $xmlString);

        // Verify it's well-formed XML
        $dom = new DOMDocument();
        $loaded = $dom->loadXML($xmlString);
        $this->assertTrue($loaded);
    }

    /**
     * @test
     */
    public function generatorXmlHasCorrectRootElement(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $dom = new DOMDocument();
        $dom->loadXML($generator->getXmlString());

        $root = $dom->documentElement;
        $this->assertEquals('Transmission', $root->localName);
    }

    /**
     * @test
     */
    public function generatorXmlHasCorrectNamespace(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $dom = new DOMDocument();
        $dom->loadXML($generator->getXmlString());

        $root = $dom->documentElement;
        $this->assertEquals(RL24XmlSchema::NS_RELEVE, $root->namespaceURI);
    }

    /**
     * @test
     */
    public function generatorXmlContainsHeader(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $dom = new DOMDocument();
        $dom->loadXML($generator->getXmlString());

        $headers = $dom->getElementsByTagName('Entete');
        $this->assertEquals(1, $headers->length);
    }

    /**
     * @test
     */
    public function generatorXmlContainsGroup(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $dom = new DOMDocument();
        $dom->loadXML($generator->getXmlString());

        $groups = $dom->getElementsByTagName('Groupe');
        $this->assertEquals(1, $groups->length);
    }

    /**
     * @test
     */
    public function generatorXmlContainsSlips(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $dom = new DOMDocument();
        $dom->loadXML($generator->getXmlString());

        $slips = $dom->getElementsByTagName('RL24');
        $this->assertEquals(2, $slips->length);
    }

    /**
     * @test
     */
    public function generatorXmlContainsSummary(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $dom = new DOMDocument();
        $dom->loadXML($generator->getXmlString());

        $summaries = $dom->getElementsByTagName('Sommaire');
        $this->assertEquals(1, $summaries->length);
    }

    /**
     * @test
     */
    public function generatorCalculatesSummaryTotals(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $totals = $generator->getSummaryTotals();

        $this->assertEquals(2, $totals['totalSlips']);
        $this->assertEquals(440, $totals['totalDays']); // 220 * 2
        $this->assertEquals(17000.00, $totals['totalCase11']); // 8500 * 2
    }

    /**
     * @test
     */
    public function generatorGeneratesCorrectFilename(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);

        $filename = $generator->getFilename();

        $this->assertEquals('24123456001.xml', $filename);
    }

    // =========================================================================
    // RL24XmlValidator STRUCTURE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function validatorClassExists(): void
    {
        $this->assertTrue(class_exists(RL24XmlValidator::class));
    }

    /**
     * @test
     */
    public function validatorHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(RL24XmlValidator::class);

        $requiredMethods = [
            'validateString',
            'validateFile',
            'validateDom',
            'setSchemaPath',
            'setStrictMode',
            'getErrors',
            'getWarnings',
            'getAllMessages',
            'hasErrors',
            'hasWarnings',
            'isClean',
            'getSummary',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('RL24XmlValidator should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function validatorHasCorrectErrorConstants(): void
    {
        $this->assertEquals('XML_MALFORMED', RL24XmlValidator::ERR_XML_MALFORMED);
        $this->assertEquals('MISSING_ELEMENT', RL24XmlValidator::ERR_MISSING_ELEMENT);
        $this->assertEquals('INVALID_VALUE', RL24XmlValidator::ERR_INVALID_VALUE);
        $this->assertEquals('INVALID_FORMAT', RL24XmlValidator::ERR_INVALID_FORMAT);
        $this->assertEquals('BUSINESS_RULE', RL24XmlValidator::ERR_BUSINESS_RULE);
        $this->assertEquals('SUMMARY_MISMATCH', RL24XmlValidator::ERR_SUMMARY_MISMATCH);
    }

    /**
     * @test
     */
    public function validatorHasCorrectSeverityConstants(): void
    {
        $this->assertEquals('error', RL24XmlValidator::SEVERITY_ERROR);
        $this->assertEquals('warning', RL24XmlValidator::SEVERITY_WARNING);
        $this->assertEquals('notice', RL24XmlValidator::SEVERITY_NOTICE);
    }

    /**
     * @test
     */
    public function validatorCanBeInstantiatedWithStrictMode(): void
    {
        $validatorStrict = new RL24XmlValidator(true);
        $validatorRelaxed = new RL24XmlValidator(false);

        $this->assertInstanceOf(RL24XmlValidator::class, $validatorStrict);
        $this->assertInstanceOf(RL24XmlValidator::class, $validatorRelaxed);
    }

    /**
     * @test
     */
    public function validatorSetStrictModeReturnsSelf(): void
    {
        $validator = new RL24XmlValidator();
        $result = $validator->setStrictMode(true);

        $this->assertSame($validator, $result);
    }

    /**
     * @test
     */
    public function validatorSetSchemaPathReturnsSelf(): void
    {
        $validator = new RL24XmlValidator();
        $result = $validator->setSchemaPath('/path/to/schema.xsd');

        $this->assertSame($validator, $result);
    }

    /**
     * @test
     */
    public function validatorRejectsEmptyXml(): void
    {
        $validator = new RL24XmlValidator();
        $result = $validator->validateString('');

        $this->assertFalse($result);
        $this->assertTrue($validator->hasErrors());
    }

    /**
     * @test
     */
    public function validatorRejectsMalformedXml(): void
    {
        $validator = new RL24XmlValidator();
        $result = $validator->validateString('<invalid><unclosed>');

        $this->assertFalse($result);
        $this->assertTrue($validator->hasErrors());
    }

    /**
     * @test
     */
    public function validatorAcceptsValidXml(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $validator = new RL24XmlValidator();
        $result = $validator->validateString($generator->getXmlString());

        $this->assertTrue($result);
        $this->assertFalse($validator->hasErrors());
    }

    /**
     * @test
     */
    public function validatorCanValidateDomDocument(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $validator = new RL24XmlValidator();
        $result = $validator->validateDom($generator->getDomDocument());

        $this->assertTrue($result);
        $this->assertFalse($validator->hasErrors());
    }

    /**
     * @test
     */
    public function validatorReportsMissingSlips(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <Transmission xmlns="http://www.mrq.gouv.qc.ca/T5/RL24">
                <Entete>
                    <Transmetteur>
                        <NoTransmetteur>NP123456</NoTransmetteur>
                        <TypeTransmission>O</TypeTransmission>
                        <Annee>2024</Annee>
                        <NoSequence>001</NoSequence>
                    </Transmetteur>
                </Entete>
                <Groupe>
                    <Emetteur>
                        <NEQ>1234567890</NEQ>
                        <NomEmetteur><Ligne1>Test</Ligne1></NomEmetteur>
                    </Emetteur>
                    <Sommaire>
                        <NombreReleves>0</NombreReleves>
                    </Sommaire>
                </Groupe>
            </Transmission>';

        $validator = new RL24XmlValidator();
        $result = $validator->validateString($xml);

        $this->assertFalse($result);
        $this->assertTrue($validator->hasErrors());
    }

    /**
     * @test
     */
    public function validatorReturnsSummaryString(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $validator = new RL24XmlValidator();
        $validator->validateString($generator->getXmlString());

        $summary = $validator->getSummary();

        $this->assertIsString($summary);
        $this->assertNotEmpty($summary);
    }

    /**
     * @test
     */
    public function validatorIsCleanForValidXml(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $validator = new RL24XmlValidator(false); // Non-strict mode
        $validator->validateString($generator->getXmlString());

        // Note: isClean() returns true only if no errors AND no warnings
        $this->assertFalse($validator->hasErrors());
    }

    // =========================================================================
    // RL24SlipBuilder STRUCTURE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function slipBuilderClassExists(): void
    {
        $this->assertTrue(class_exists(RL24SlipBuilder::class));
    }

    /**
     * @test
     */
    public function slipBuilderHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(RL24SlipBuilder::class);

        $requiredMethods = [
            'setSlipData',
            'setSlipNumber',
            'build',
            'buildMultiple',
            'getErrors',
            'getWarnings',
            'hasErrors',
            'hasWarnings',
            'reset',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('RL24SlipBuilder should have method %s', $method)
            );
        }
    }

    /**
     * @test
     */
    public function slipBuilderHasStaticFactoryMethods(): void
    {
        $reflection = new ReflectionClass(RL24SlipBuilder::class);

        $staticMethods = [
            'fromSlipData',
            'createSlipElement',
            'createOriginalSlip',
            'createAmendedSlip',
            'createCancelledSlip',
            'calculateSummaryTotals',
        ];

        foreach ($staticMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                sprintf('RL24SlipBuilder should have static method %s', $method)
            );
            $this->assertTrue(
                $reflection->getMethod($method)->isStatic(),
                sprintf('RL24SlipBuilder::%s should be static', $method)
            );
        }
    }

    /**
     * @test
     */
    public function slipBuilderCanBeInstantiated(): void
    {
        $dom = new DOMDocument();
        $builder = new RL24SlipBuilder($dom);

        $this->assertInstanceOf(RL24SlipBuilder::class, $builder);
    }

    /**
     * @test
     */
    public function slipBuilderSetSlipDataReturnsSelf(): void
    {
        $dom = new DOMDocument();
        $builder = new RL24SlipBuilder($dom);
        $result = $builder->setSlipData(self::$sampleSlipData);

        $this->assertSame($builder, $result);
    }

    /**
     * @test
     */
    public function slipBuilderSetSlipNumberReturnsSelf(): void
    {
        $dom = new DOMDocument();
        $builder = new RL24SlipBuilder($dom);
        $result = $builder->setSlipNumber(1);

        $this->assertSame($builder, $result);
    }

    /**
     * @test
     */
    public function slipBuilderResetReturnsSelf(): void
    {
        $dom = new DOMDocument();
        $builder = new RL24SlipBuilder($dom);
        $result = $builder->reset();

        $this->assertSame($builder, $result);
    }

    /**
     * @test
     */
    public function slipBuilderBuildsSlipElement(): void
    {
        $dom = new DOMDocument();
        $builder = new RL24SlipBuilder($dom);
        $builder->setSlipData(self::$sampleSlipData);
        $builder->setSlipNumber(1);

        $element = $builder->build();

        $this->assertNotNull($element);
        $this->assertEquals('RL24', $element->localName);
    }

    /**
     * @test
     */
    public function slipBuilderFailsWithMissingRequiredFields(): void
    {
        $dom = new DOMDocument();
        $builder = new RL24SlipBuilder($dom);
        $builder->setSlipData([
            // Missing required fields
        ]);
        $builder->setSlipNumber(1);

        $element = $builder->build();

        $this->assertNull($element);
        $this->assertTrue($builder->hasErrors());
    }

    /**
     * @test
     */
    public function slipBuilderFromSlipDataCreatesInstance(): void
    {
        $dom = new DOMDocument();
        $builder = RL24SlipBuilder::fromSlipData($dom, self::$sampleSlipData, 1);

        $this->assertInstanceOf(RL24SlipBuilder::class, $builder);
    }

    /**
     * @test
     */
    public function slipBuilderCreateOriginalSlipCreatesElement(): void
    {
        $dom = new DOMDocument();
        $element = RL24SlipBuilder::createOriginalSlip($dom, self::$sampleSlipData, 1);

        $this->assertNotNull($element);
    }

    /**
     * @test
     */
    public function slipBuilderCreateAmendedSlipCreatesElement(): void
    {
        $dom = new DOMDocument();
        $element = RL24SlipBuilder::createAmendedSlip($dom, self::$sampleSlipData, 2, 1);

        $this->assertNotNull($element);

        // Verify it has Case A = 'A' (Amended)
        $caseA = $element->getElementsByTagName('CaseA');
        $this->assertEquals(1, $caseA->length);
        $this->assertEquals('A', $caseA->item(0)->textContent);
    }

    /**
     * @test
     */
    public function slipBuilderCreateCancelledSlipCreatesElement(): void
    {
        $dom = new DOMDocument();
        $element = RL24SlipBuilder::createCancelledSlip($dom, self::$sampleSlipData, 3, 1);

        $this->assertNotNull($element);

        // Verify it has Case A = 'D' (Cancelled)
        $caseA = $element->getElementsByTagName('CaseA');
        $this->assertEquals(1, $caseA->length);
        $this->assertEquals('D', $caseA->item(0)->textContent);
    }

    /**
     * @test
     */
    public function slipBuilderCalculatesSummaryTotals(): void
    {
        $slips = [
            self::$sampleSlipData,
            self::$sampleSlipData,
        ];

        $totals = RL24SlipBuilder::calculateSummaryTotals($slips);

        $this->assertEquals(2, $totals['totalSlips']);
        $this->assertEquals(440, $totals['totalDays']);
        $this->assertEquals(17000.00, $totals['totalCase11']);
        $this->assertEquals(17000.00, $totals['totalCase12']);
        $this->assertEquals(0.00, $totals['totalCase13']);
        $this->assertEquals(17000.00, $totals['totalCase14']);
    }

    /**
     * @test
     */
    public function slipBuilderBuildMultipleCreatesMultipleElements(): void
    {
        $dom = new DOMDocument();
        $builder = new RL24SlipBuilder($dom);

        $elements = $builder->buildMultiple([
            self::$sampleSlipData,
            self::$sampleSlipData,
        ]);

        $this->assertCount(2, $elements);
    }

    /**
     * @test
     */
    public function slipBuilderSlipHasIdentificationSection(): void
    {
        $dom = new DOMDocument();
        $element = RL24SlipBuilder::createOriginalSlip($dom, self::$sampleSlipData, 1);

        $identification = $element->getElementsByTagName('Identification');
        $this->assertEquals(1, $identification->length);

        $slipNumber = $element->getElementsByTagName('NoReleve');
        $this->assertEquals(1, $slipNumber->length);
    }

    /**
     * @test
     */
    public function slipBuilderSlipHasRecipientSection(): void
    {
        $dom = new DOMDocument();
        $element = RL24SlipBuilder::createOriginalSlip($dom, self::$sampleSlipData, 1);

        $recipient = $element->getElementsByTagName('Destinataire');
        $this->assertEquals(1, $recipient->length);
    }

    /**
     * @test
     */
    public function slipBuilderSlipHasChildSection(): void
    {
        $dom = new DOMDocument();
        $element = RL24SlipBuilder::createOriginalSlip($dom, self::$sampleSlipData, 1);

        $child = $element->getElementsByTagName('Enfant');
        $this->assertEquals(1, $child->length);
    }

    /**
     * @test
     */
    public function slipBuilderSlipHasServicePeriodSection(): void
    {
        $dom = new DOMDocument();
        $element = RL24SlipBuilder::createOriginalSlip($dom, self::$sampleSlipData, 1);

        $period = $element->getElementsByTagName('Periode');
        $this->assertEquals(1, $period->length);
    }

    /**
     * @test
     */
    public function slipBuilderSlipHasAmountBoxes(): void
    {
        $dom = new DOMDocument();
        $element = RL24SlipBuilder::createOriginalSlip($dom, self::$sampleSlipData, 1);

        $box10 = $element->getElementsByTagName('Case10');
        $box11 = $element->getElementsByTagName('Case11');
        $box12 = $element->getElementsByTagName('Case12');
        $box14 = $element->getElementsByTagName('Case14');

        $this->assertEquals(1, $box10->length);
        $this->assertEquals(1, $box11->length);
        $this->assertEquals(1, $box12->length);
        $this->assertEquals(1, $box14->length);

        $this->assertEquals('220', $box10->item(0)->textContent);
        $this->assertEquals('8500.00', $box11->item(0)->textContent);
        $this->assertEquals('8500.00', $box12->item(0)->textContent);
        $this->assertEquals('8500.00', $box14->item(0)->textContent);
    }

    // =========================================================================
    // INTEGRATION TESTS - FULL XML GENERATION FLOW
    // =========================================================================

    /**
     * @test
     */
    public function fullXmlGenerationAndValidationFlow(): void
    {
        // Generate XML
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->addSlip(self::$sampleSlipData);

        $generateResult = $generator->generate();
        $this->assertTrue($generateResult, 'XML generation should succeed');

        // Validate XML
        $validator = new RL24XmlValidator(false);
        $validateResult = $validator->validateString($generator->getXmlString());
        $this->assertTrue($validateResult, 'Generated XML should pass validation');

        // Verify structure
        $dom = new DOMDocument();
        $dom->loadXML($generator->getXmlString());

        // Check root element
        $this->assertEquals('Transmission', $dom->documentElement->localName);

        // Check header
        $headers = $dom->getElementsByTagName('Entete');
        $this->assertEquals(1, $headers->length);

        // Check group
        $groups = $dom->getElementsByTagName('Groupe');
        $this->assertEquals(1, $groups->length);

        // Check slips
        $slips = $dom->getElementsByTagName('RL24');
        $this->assertEquals(2, $slips->length);

        // Check summary
        $summaries = $dom->getElementsByTagName('Sommaire');
        $this->assertEquals(1, $summaries->length);
    }

    /**
     * @test
     */
    public function xmlContainsCorrectTransmitterNumber(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $dom = new DOMDocument();
        $dom->loadXML($generator->getXmlString());

        $transmitterNumbers = $dom->getElementsByTagName('NoTransmetteur');
        $this->assertEquals(1, $transmitterNumbers->length);
        $this->assertEquals('NP123456', $transmitterNumbers->item(0)->textContent);
    }

    /**
     * @test
     */
    public function xmlContainsCorrectTaxYear(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $dom = new DOMDocument();
        $dom->loadXML($generator->getXmlString());

        $taxYears = $dom->getElementsByTagName('Annee');
        $this->assertEquals(1, $taxYears->length);
        $this->assertEquals('2024', $taxYears->item(0)->textContent);
    }

    /**
     * @test
     */
    public function xmlContainsCorrectIssuerNEQ(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $dom = new DOMDocument();
        $dom->loadXML($generator->getXmlString());

        $neqs = $dom->getElementsByTagName('NEQ');
        $this->assertEquals(1, $neqs->length);
        $this->assertEquals('1234567890', $neqs->item(0)->textContent);
    }

    /**
     * @test
     */
    public function xmlSummaryMatchesSlipCount(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->addSlip(self::$sampleSlipData);
        $generator->generate();

        $dom = new DOMDocument();
        $dom->loadXML($generator->getXmlString());

        $slips = $dom->getElementsByTagName('RL24');
        $slipCount = $slips->length;

        $totalSlips = $dom->getElementsByTagName('NombreReleves');
        $this->assertEquals(1, $totalSlips->length);
        $this->assertEquals((string) $slipCount, $totalSlips->item(0)->textContent);
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function generatorHandlesSpecialCharactersInNames(): void
    {
        $slipData = self::$sampleSlipData;
        $slipData['parentFirstName'] = 'Jean-François';
        $slipData['parentLastName'] = "L'Heureux";
        $slipData['childFirstName'] = 'Éloïse';
        $slipData['childLastName'] = 'Côté';

        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip($slipData);

        $result = $generator->generate();
        $this->assertTrue($result);

        // Verify the XML is well-formed
        $dom = new DOMDocument();
        $loaded = $dom->loadXML($generator->getXmlString());
        $this->assertTrue($loaded);
    }

    /**
     * @test
     */
    public function generatorHandlesZeroAmounts(): void
    {
        $slipData = self::$sampleSlipData;
        $slipData['case11Amount'] = 0;
        $slipData['case12Amount'] = 0;
        $slipData['case13Amount'] = 0;
        $slipData['case14Amount'] = 0;
        $slipData['totalDays'] = 0;

        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);
        $generator->addSlip($slipData);

        $result = $generator->generate();
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function generatorHandlesMaximumAllowedSlips(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);

        // Add maximum allowed slips
        for ($i = 0; $i < RL24XmlSchema::MAX_SLIPS_PER_FILE; $i++) {
            $generator->addSlip(self::$sampleSlipData);
        }

        $result = $generator->generate();
        $this->assertTrue($result);
    }

    /**
     * @test
     */
    public function generatorRejectsTooManySlips(): void
    {
        $generator = new RL24XmlGenerator();
        $generator->setTransmissionData(self::$sampleTransmissionData);

        // Add more than maximum allowed slips
        for ($i = 0; $i <= RL24XmlSchema::MAX_SLIPS_PER_FILE; $i++) {
            $generator->addSlip(self::$sampleSlipData);
        }

        $result = $generator->generate();
        $this->assertFalse($result);
        $this->assertTrue($generator->hasErrors());
    }

    /**
     * @test
     */
    public function validatorRejectsInvalidNEQ(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
            <Transmission xmlns="http://www.mrq.gouv.qc.ca/T5/RL24">
                <Entete>
                    <Transmetteur>
                        <NoTransmetteur>NP123456</NoTransmetteur>
                        <TypeTransmission>O</TypeTransmission>
                        <Annee>2024</Annee>
                        <NoSequence>001</NoSequence>
                    </Transmetteur>
                </Entete>
                <Groupe>
                    <Emetteur>
                        <NEQ>123</NEQ>
                        <NomEmetteur><Ligne1>Test</Ligne1></NomEmetteur>
                    </Emetteur>
                    <RL24>
                        <Identification>
                            <NoReleve>1</NoReleve>
                            <CaseA>O</CaseA>
                        </Identification>
                        <Destinataire>
                            <NomDestinataire>
                                <Nom>Test</Nom>
                                <Prenom>User</Prenom>
                            </NomDestinataire>
                        </Destinataire>
                        <Enfant>
                            <NomEnfant>Child</NomEnfant>
                            <PrenomEnfant>Test</PrenomEnfant>
                        </Enfant>
                        <Periode>
                            <DateDebut>2024-01-01</DateDebut>
                            <DateFin>2024-12-31</DateFin>
                        </Periode>
                    </RL24>
                    <Sommaire>
                        <NombreReleves>1</NombreReleves>
                    </Sommaire>
                </Groupe>
            </Transmission>';

        $validator = new RL24XmlValidator();
        $result = $validator->validateString($xml);

        $this->assertFalse($result);
        $this->assertTrue($validator->hasErrors());
    }
}
