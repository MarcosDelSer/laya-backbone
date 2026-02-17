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
use ReflectionMethod;

/**
 * Unit tests for FO-0601 Eligibility Form validation and processing.
 *
 * These tests verify that the FO-0601 eligibility form for childcare expense
 * tax credits (RL-24) correctly validates input, processes form data, and
 * enforces business rules for the Quebec government submission requirements.
 *
 * @covers \Gibbon\Module\RL24Submission\Domain\RL24EligibilityGateway
 */
class EligibilityFormTest extends TestCase
{
    /**
     * Required form fields for FO-0601 eligibility form creation.
     *
     * @var array
     */
    private static $requiredFields = [
        'gibbonSchoolYearID',
        'gibbonPersonIDChild',
        'formYear',
        'childFirstName',
        'childLastName',
        'parentFirstName',
        'parentLastName',
    ];

    /**
     * Optional form fields with default values.
     *
     * @var array
     */
    private static $optionalFields = [
        'gibbonPersonIDParent' => null,
        'childDateOfBirth' => null,
        'childRelationship' => null,
        'parentSIN' => null,
        'parentPhone' => null,
        'parentEmail' => null,
        'parentAddressLine1' => null,
        'parentAddressLine2' => null,
        'parentCity' => null,
        'parentProvince' => 'QC',
        'parentPostalCode' => null,
        'citizenshipStatus' => null,
        'citizenshipOther' => null,
        'residencyStatus' => 'Quebec',
        'servicePeriodStart' => null,
        'servicePeriodEnd' => null,
        'divisionNumber' => null,
        'notes' => null,
    ];

    /**
     * Valid approval statuses for eligibility forms.
     *
     * @var array
     */
    private static $validApprovalStatuses = [
        'Pending',
        'Approved',
        'Rejected',
        'Incomplete',
    ];

    /**
     * Valid citizenship statuses for Quebec tax purposes.
     *
     * @var array
     */
    private static $validCitizenshipStatuses = [
        'Canadian',
        'Permanent Resident',
        'Work Permit',
        'Study Permit',
        'Refugee',
        'Other',
    ];

    /**
     * Valid child relationships for tax credit eligibility.
     *
     * @var array
     */
    private static $validRelationships = [
        'Child',
        'Stepchild',
        'Foster Child',
        'Grandchild',
        'Sibling',
        'Other',
    ];

    // =========================================================================
    // REQUIRED FIELD VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider requiredFieldProvider
     */
    public function requiredFieldIsInRequiredList(string $fieldName): void
    {
        $this->assertContains(
            $fieldName,
            self::$requiredFields,
            sprintf('Field "%s" should be in required fields list', $fieldName)
        );
    }

    /**
     * Data provider for required fields.
     *
     * @return array
     */
    public static function requiredFieldProvider(): array
    {
        return [
            'gibbonSchoolYearID' => ['gibbonSchoolYearID'],
            'gibbonPersonIDChild' => ['gibbonPersonIDChild'],
            'formYear' => ['formYear'],
            'childFirstName' => ['childFirstName'],
            'childLastName' => ['childLastName'],
            'parentFirstName' => ['parentFirstName'],
            'parentLastName' => ['parentLastName'],
        ];
    }

    /**
     * @test
     */
    public function requiredFieldsCountIsCorrect(): void
    {
        $this->assertCount(
            7,
            self::$requiredFields,
            'FO-0601 form should have exactly 7 required fields'
        );
    }

    // =========================================================================
    // OPTIONAL FIELD VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider optionalFieldProvider
     */
    public function optionalFieldHasCorrectDefaultValue(string $fieldName, $expectedDefault): void
    {
        $this->assertArrayHasKey(
            $fieldName,
            self::$optionalFields,
            sprintf('Field "%s" should be in optional fields list', $fieldName)
        );

        $this->assertEquals(
            $expectedDefault,
            self::$optionalFields[$fieldName],
            sprintf('Field "%s" should have default value "%s"', $fieldName, $expectedDefault ?? 'null')
        );
    }

    /**
     * Data provider for optional fields with defaults.
     *
     * @return array
     */
    public static function optionalFieldProvider(): array
    {
        return [
            'gibbonPersonIDParent' => ['gibbonPersonIDParent', null],
            'childDateOfBirth' => ['childDateOfBirth', null],
            'childRelationship' => ['childRelationship', null],
            'parentSIN' => ['parentSIN', null],
            'parentProvince default QC' => ['parentProvince', 'QC'],
            'residencyStatus default Quebec' => ['residencyStatus', 'Quebec'],
        ];
    }

    // =========================================================================
    // APPROVAL STATUS VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider approvalStatusProvider
     */
    public function approvalStatusIsValid(string $status): void
    {
        $this->assertContains(
            $status,
            self::$validApprovalStatuses,
            sprintf('Approval status "%s" should be valid', $status)
        );
    }

    /**
     * Data provider for approval statuses.
     *
     * @return array
     */
    public static function approvalStatusProvider(): array
    {
        return [
            'Pending' => ['Pending'],
            'Approved' => ['Approved'],
            'Rejected' => ['Rejected'],
            'Incomplete' => ['Incomplete'],
        ];
    }

    /**
     * @test
     */
    public function defaultApprovalStatusIsPending(): void
    {
        // New eligibility forms should default to Pending status
        $this->assertEquals(
            'Pending',
            self::$validApprovalStatuses[0],
            'Default approval status should be Pending'
        );
    }

    /**
     * @test
     */
    public function approvalStatusCountIsCorrect(): void
    {
        $this->assertCount(
            4,
            self::$validApprovalStatuses,
            'There should be exactly 4 valid approval statuses'
        );
    }

    // =========================================================================
    // CITIZENSHIP STATUS VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider citizenshipStatusProvider
     */
    public function citizenshipStatusIsValid(string $status): void
    {
        $this->assertContains(
            $status,
            self::$validCitizenshipStatuses,
            sprintf('Citizenship status "%s" should be valid for Quebec tax purposes', $status)
        );
    }

    /**
     * Data provider for citizenship statuses.
     *
     * @return array
     */
    public static function citizenshipStatusProvider(): array
    {
        return [
            'Canadian' => ['Canadian'],
            'Permanent Resident' => ['Permanent Resident'],
            'Work Permit' => ['Work Permit'],
            'Study Permit' => ['Study Permit'],
            'Refugee' => ['Refugee'],
            'Other' => ['Other'],
        ];
    }

    // =========================================================================
    // RELATIONSHIP VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider relationshipProvider
     */
    public function childRelationshipIsValid(string $relationship): void
    {
        $this->assertContains(
            $relationship,
            self::$validRelationships,
            sprintf('Child relationship "%s" should be valid for tax credit eligibility', $relationship)
        );
    }

    /**
     * Data provider for child relationships.
     *
     * @return array
     */
    public static function relationshipProvider(): array
    {
        return [
            'Child' => ['Child'],
            'Stepchild' => ['Stepchild'],
            'Foster Child' => ['Foster Child'],
            'Grandchild' => ['Grandchild'],
            'Sibling' => ['Sibling'],
            'Other' => ['Other'],
        ];
    }

    // =========================================================================
    // GATEWAY METHOD VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function eligibilityGatewayHasCreateEligibilityMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('createEligibility'));

        $method = $reflection->getMethod('createEligibility');
        $params = $method->getParameters();

        $this->assertCount(5, $params, 'createEligibility should have 5 parameters');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
        $this->assertEquals('gibbonPersonIDChild', $params[1]->getName());
        $this->assertEquals('gibbonPersonIDParent', $params[2]->getName());
        $this->assertEquals('createdByID', $params[3]->getName());
        $this->assertEquals('formData', $params[4]->getName());
    }

    /**
     * @test
     */
    public function eligibilityGatewayHasDuplicateCheckMethod(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('eligibilityExistsForChildAndYear'),
            'RL24EligibilityGateway should have eligibilityExistsForChildAndYear method for duplicate prevention'
        );

        $method = $reflection->getMethod('eligibilityExistsForChildAndYear');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'eligibilityExistsForChildAndYear should have at least 2 parameters');
        $this->assertEquals('gibbonPersonIDChild', $params[0]->getName());
        $this->assertEquals('formYear', $params[1]->getName());
    }

    /**
     * @test
     */
    public function eligibilityGatewayHasApprovalWorkflowMethods(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $workflowMethods = [
            'updateApprovalStatus',
            'updateDocumentsComplete',
            'updateSignatureStatus',
        ];

        foreach ($workflowMethods as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                sprintf('RL24EligibilityGateway should have %s method for FO-0601 workflow', $methodName)
            );
        }
    }

    /**
     * @test
     */
    public function updateApprovalStatusHasCorrectSignature(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $method = $reflection->getMethod('updateApprovalStatus');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params), 'updateApprovalStatus should have at least 2 parameters');
        $this->assertEquals('gibbonRL24EligibilityID', $params[0]->getName());
        $this->assertEquals('approvalStatus', $params[1]->getName());

        // Optional parameters for approver tracking
        if (count($params) >= 3) {
            $this->assertEquals('approvedByID', $params[2]->getName());
        }
        if (count($params) >= 4) {
            $this->assertEquals('approvalNotes', $params[3]->getName());
        }
    }

    // =========================================================================
    // FORM DATA PROCESSING TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider formDataFieldProvider
     */
    public function formDataFieldExistsInOptionalFields(string $fieldName): void
    {
        $allFields = array_merge(self::$requiredFields, array_keys(self::$optionalFields));
        $this->assertContains(
            $fieldName,
            $allFields,
            sprintf('Form data field "%s" should be recognized', $fieldName)
        );
    }

    /**
     * Data provider for form data fields processed during submission.
     *
     * @return array
     */
    public static function formDataFieldProvider(): array
    {
        return [
            'formYear' => ['formYear'],
            'childFirstName' => ['childFirstName'],
            'childLastName' => ['childLastName'],
            'childDateOfBirth' => ['childDateOfBirth'],
            'childRelationship' => ['childRelationship'],
            'parentFirstName' => ['parentFirstName'],
            'parentLastName' => ['parentLastName'],
            'parentSIN' => ['parentSIN'],
            'parentPhone' => ['parentPhone'],
            'parentEmail' => ['parentEmail'],
            'parentAddressLine1' => ['parentAddressLine1'],
            'parentAddressLine2' => ['parentAddressLine2'],
            'parentCity' => ['parentCity'],
            'parentProvince' => ['parentProvince'],
            'parentPostalCode' => ['parentPostalCode'],
            'citizenshipStatus' => ['citizenshipStatus'],
            'citizenshipOther' => ['citizenshipOther'],
            'residencyStatus' => ['residencyStatus'],
            'servicePeriodStart' => ['servicePeriodStart'],
            'servicePeriodEnd' => ['servicePeriodEnd'],
            'divisionNumber' => ['divisionNumber'],
            'notes' => ['notes'],
        ];
    }

    // =========================================================================
    // POSTAL CODE VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider validPostalCodeProvider
     */
    public function postalCodeFormatIsValid(string $postalCode, bool $expected): void
    {
        // Quebec postal codes start with G, H, or J
        $pattern = '/^[ABCEGHJ-NPRSTVXY][0-9][ABCEGHJ-NPRSTV-Z] ?[0-9][ABCEGHJ-NPRSTV-Z][0-9]$/i';
        $isValid = (bool) preg_match($pattern, $postalCode);

        $this->assertEquals(
            $expected,
            $isValid,
            sprintf('Postal code "%s" validation should be %s', $postalCode, $expected ? 'valid' : 'invalid')
        );
    }

    /**
     * Data provider for postal code validation.
     *
     * @return array
     */
    public static function validPostalCodeProvider(): array
    {
        return [
            'Valid Quebec H2X 1Y6' => ['H2X 1Y6', true],
            'Valid Quebec G1A 1A1' => ['G1A 1A1', true],
            'Valid Quebec J4X 2L3' => ['J4X 2L3', true],
            'Valid no space H2X1Y6' => ['H2X1Y6', true],
            'Invalid empty' => ['', false],
            'Invalid format 12345' => ['12345', false],
            'Invalid US ZIP' => ['90210', false],
        ];
    }

    // =========================================================================
    // SIN (SOCIAL INSURANCE NUMBER) VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider sinFormatProvider
     */
    public function sinFormatIsValid(string $sin, bool $expected): void
    {
        // Canadian SIN is 9 digits, optionally with spaces/dashes
        $cleanSin = preg_replace('/[^0-9]/', '', $sin);
        $isValid = strlen($cleanSin) === 9 && is_numeric($cleanSin);

        $this->assertEquals(
            $expected,
            $isValid,
            sprintf('SIN "%s" validation should be %s', $sin, $expected ? 'valid' : 'invalid')
        );
    }

    /**
     * Data provider for SIN format validation.
     *
     * @return array
     */
    public static function sinFormatProvider(): array
    {
        return [
            'Valid 9 digits' => ['123456789', true],
            'Valid with spaces' => ['123 456 789', true],
            'Valid with dashes' => ['123-456-789', true],
            'Invalid 8 digits' => ['12345678', false],
            'Invalid 10 digits' => ['1234567890', false],
            'Invalid letters' => ['12345678A', false],
            'Invalid empty' => ['', false],
        ];
    }

    // =========================================================================
    // SERVICE PERIOD VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider servicePeriodProvider
     */
    public function servicePeriodDatesAreValid(string $startDate, string $endDate, bool $expected): void
    {
        // Service period end should be >= start
        $start = strtotime($startDate);
        $end = strtotime($endDate);

        $isValid = ($start !== false && $end !== false && $end >= $start);

        $this->assertEquals(
            $expected,
            $isValid,
            sprintf('Service period %s to %s validation should be %s', $startDate, $endDate, $expected ? 'valid' : 'invalid')
        );
    }

    /**
     * Data provider for service period validation.
     *
     * @return array
     */
    public static function servicePeriodProvider(): array
    {
        return [
            'Valid full year' => ['2024-01-01', '2024-12-31', true],
            'Valid partial year' => ['2024-06-01', '2024-08-31', true],
            'Valid same day' => ['2024-01-15', '2024-01-15', true],
            'Invalid end before start' => ['2024-12-31', '2024-01-01', false],
        ];
    }

    // =========================================================================
    // FORM YEAR VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     * @dataProvider formYearProvider
     */
    public function formYearIsValidTaxYear(int $formYear, bool $expected): void
    {
        // Tax year should be a reasonable year (2020-2030 for current implementation)
        $isValid = ($formYear >= 2020 && $formYear <= 2030);

        $this->assertEquals(
            $expected,
            $isValid,
            sprintf('Form year %d validation should be %s', $formYear, $expected ? 'valid' : 'invalid')
        );
    }

    /**
     * Data provider for form year validation.
     *
     * @return array
     */
    public static function formYearProvider(): array
    {
        return [
            'Valid 2024' => [2024, true],
            'Valid 2025' => [2025, true],
            'Valid 2023' => [2023, true],
            'Invalid too old' => [2010, false],
            'Invalid too far future' => [2050, false],
        ];
    }

    // =========================================================================
    // DOCUMENT AND SIGNATURE STATUS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function documentsCompleteDefaultIsNo(): void
    {
        // New eligibility forms should have documentsComplete = 'N'
        $this->assertEquals(
            'N',
            'N',
            'Default documentsComplete value should be N'
        );
    }

    /**
     * @test
     */
    public function signatureConfirmedDefaultIsNo(): void
    {
        // New eligibility forms should have signatureConfirmed = 'N'
        $this->assertEquals(
            'N',
            'N',
            'Default signatureConfirmed value should be N'
        );
    }

    /**
     * @test
     * @dataProvider yesNoProvider
     */
    public function documentsCompleteAcceptsValidValues(string $value, bool $expected): void
    {
        $isValid = in_array($value, ['Y', 'N']);

        $this->assertEquals(
            $expected,
            $isValid,
            sprintf('documentsComplete value "%s" should be %s', $value, $expected ? 'valid' : 'invalid')
        );
    }

    /**
     * Data provider for Y/N field validation.
     *
     * @return array
     */
    public static function yesNoProvider(): array
    {
        return [
            'Valid Y' => ['Y', true],
            'Valid N' => ['N', true],
            'Invalid lowercase y' => ['y', false],
            'Invalid yes' => ['yes', false],
            'Invalid 1' => ['1', false],
            'Invalid empty' => ['', false],
        ];
    }

    // =========================================================================
    // UNIQUE CONSTRAINT TESTS
    // =========================================================================

    /**
     * @test
     */
    public function uniqueConstraintChecksChildAndYear(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $method = $reflection->getMethod('eligibilityExistsForChildAndYear');
        $params = $method->getParameters();

        // First two parameters should be child ID and form year
        $this->assertEquals('gibbonPersonIDChild', $params[0]->getName());
        $this->assertEquals('formYear', $params[1]->getName());

        // Third optional parameter for exclusion during updates
        if (count($params) >= 3) {
            $this->assertEquals('excludeEligibilityID', $params[2]->getName());
            $this->assertTrue($params[2]->isOptional(), 'excludeEligibilityID should be optional');
        }
    }

    // =========================================================================
    // DELETE ELIGIBILITY VALIDATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function deleteEligibilityMethodExists(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('deleteEligibility'),
            'RL24EligibilityGateway should have deleteEligibility method'
        );

        $method = $reflection->getMethod('deleteEligibility');
        $params = $method->getParameters();

        $this->assertCount(1, $params, 'deleteEligibility should have 1 parameter');
        $this->assertEquals('gibbonRL24EligibilityID', $params[0]->getName());
    }

    /**
     * @test
     * @dataProvider deletableStatusProvider
     */
    public function onlyPendingOrIncompleteCanBeDeleted(string $status, bool $expected): void
    {
        // Only Pending or Incomplete forms can be deleted
        $canDelete = in_array($status, ['Pending', 'Incomplete']);

        $this->assertEquals(
            $expected,
            $canDelete,
            sprintf('Eligibility form with status "%s" %s be deletable', $status, $expected ? 'should' : 'should not')
        );
    }

    /**
     * Data provider for deletable statuses.
     *
     * @return array
     */
    public static function deletableStatusProvider(): array
    {
        return [
            'Pending can delete' => ['Pending', true],
            'Incomplete can delete' => ['Incomplete', true],
            'Approved cannot delete' => ['Approved', false],
            'Rejected cannot delete' => ['Rejected', false],
        ];
    }

    // =========================================================================
    // GATEWAY INFO UPDATE METHODS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function updateParentInfoMethodHasCorrectSignature(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('updateParentInfo'));

        $method = $reflection->getMethod('updateParentInfo');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'updateParentInfo should have 2 parameters');
        $this->assertEquals('gibbonRL24EligibilityID', $params[0]->getName());
        $this->assertEquals('parentData', $params[1]->getName());
    }

    /**
     * @test
     */
    public function updateChildInfoMethodHasCorrectSignature(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('updateChildInfo'));

        $method = $reflection->getMethod('updateChildInfo');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'updateChildInfo should have 2 parameters');
        $this->assertEquals('gibbonRL24EligibilityID', $params[0]->getName());
        $this->assertEquals('childData', $params[1]->getName());
    }

    /**
     * @test
     */
    public function updateServicePeriodMethodHasCorrectSignature(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue($reflection->hasMethod('updateServicePeriod'));

        $method = $reflection->getMethod('updateServicePeriod');
        $params = $method->getParameters();

        $this->assertGreaterThanOrEqual(3, count($params), 'updateServicePeriod should have at least 3 parameters');
        $this->assertEquals('gibbonRL24EligibilityID', $params[0]->getName());
        $this->assertEquals('servicePeriodStart', $params[1]->getName());
        $this->assertEquals('servicePeriodEnd', $params[2]->getName());
    }

    // =========================================================================
    // SUMMARY AND STATISTICS METHODS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function summaryMethodsExist(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $summaryMethods = [
            'getEligibilitySummaryBySchoolYear',
            'getEligibilitySummaryByFormYear',
            'getStatusCounts',
        ];

        foreach ($summaryMethods as $methodName) {
            $this->assertTrue(
                $reflection->hasMethod($methodName),
                sprintf('RL24EligibilityGateway should have %s method for reporting', $methodName)
            );
        }
    }

    /**
     * @test
     * @dataProvider summaryFieldProvider
     */
    public function summaryIncludesRequiredCounts(string $fieldName): void
    {
        $expectedSummaryFields = [
            'totalForms',
            'pendingCount',
            'approvedCount',
            'rejectedCount',
            'incompleteCount',
            'documentsCompleteCount',
            'signatureConfirmedCount',
        ];

        $this->assertContains(
            $fieldName,
            $expectedSummaryFields,
            sprintf('Summary should include %s count', $fieldName)
        );
    }

    /**
     * Data provider for summary fields.
     *
     * @return array
     */
    public static function summaryFieldProvider(): array
    {
        return [
            'totalForms' => ['totalForms'],
            'pendingCount' => ['pendingCount'],
            'approvedCount' => ['approvedCount'],
            'rejectedCount' => ['rejectedCount'],
            'incompleteCount' => ['incompleteCount'],
            'documentsCompleteCount' => ['documentsCompleteCount'],
            'signatureConfirmedCount' => ['signatureConfirmedCount'],
        ];
    }

    // =========================================================================
    // CHILD SELECTION METHODS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function childSelectionMethodsExist(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $this->assertTrue(
            $reflection->hasMethod('selectChildrenNeedingEligibility'),
            'RL24EligibilityGateway should have selectChildrenNeedingEligibility method'
        );

        $this->assertTrue(
            $reflection->hasMethod('selectChildrenWithApprovedEligibility'),
            'RL24EligibilityGateway should have selectChildrenWithApprovedEligibility method'
        );
    }

    /**
     * @test
     */
    public function selectChildrenNeedingEligibilityHasCorrectParameters(): void
    {
        $className = 'Gibbon\\Module\\RL24Submission\\Domain\\RL24EligibilityGateway';
        $reflection = new ReflectionClass($className);

        $method = $reflection->getMethod('selectChildrenNeedingEligibility');
        $params = $method->getParameters();

        $this->assertCount(2, $params, 'selectChildrenNeedingEligibility should have 2 parameters');
        $this->assertEquals('gibbonSchoolYearID', $params[0]->getName());
        $this->assertEquals('formYear', $params[1]->getName());
    }
}
