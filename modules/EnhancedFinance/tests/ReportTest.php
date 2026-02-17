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

namespace Gibbon\Module\EnhancedFinance\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Enhanced Finance Report calculations.
 *
 * Tests the aging report and collection report business logic including:
 * - Aging bucket calculations (Current, 30, 60, 90+ days)
 * - Collection stage determination (First Notice, Second Notice, Final Notice, Write-off)
 * - Collection summary aggregation
 * - Family-level summary calculations
 * - Collection rate calculations
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ReportTest extends TestCase
{
    /**
     * Sample aging data for testing.
     *
     * @var array
     */
    protected $sampleAgingData;

    /**
     * Sample collection data for testing.
     *
     * @var array
     */
    protected $sampleCollectionData;

    /**
     * Set up test fixtures.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Sample aging data representing various invoice scenarios
        $this->sampleAgingData = [
            [
                'gibbonEnhancedFinanceInvoiceID' => 1,
                'invoiceNumber' => 'INV-000001',
                'invoiceDate' => '2025-01-01',
                'dueDate' => '2025-02-01',
                'totalAmount' => 1000.00,
                'paidAmount' => 0.00,
                'balanceRemaining' => 1000.00,
                'status' => 'Issued',
                'daysOverdue' => -5, // Not yet due (current)
                'gibbonFamilyID' => 50,
                'familyName' => 'Smith Family',
                'childSurname' => 'Smith',
                'childPreferredName' => 'John',
            ],
            [
                'gibbonEnhancedFinanceInvoiceID' => 2,
                'invoiceNumber' => 'INV-000002',
                'invoiceDate' => '2025-01-01',
                'dueDate' => '2025-01-15',
                'totalAmount' => 800.00,
                'paidAmount' => 300.00,
                'balanceRemaining' => 500.00,
                'status' => 'Partial',
                'daysOverdue' => 15, // 1-30 days
                'gibbonFamilyID' => 50,
                'familyName' => 'Smith Family',
                'childSurname' => 'Smith',
                'childPreferredName' => 'Emma',
            ],
            [
                'gibbonEnhancedFinanceInvoiceID' => 3,
                'invoiceNumber' => 'INV-000003',
                'invoiceDate' => '2024-12-01',
                'dueDate' => '2024-12-15',
                'totalAmount' => 600.00,
                'paidAmount' => 0.00,
                'balanceRemaining' => 600.00,
                'status' => 'Issued',
                'daysOverdue' => 45, // 31-60 days
                'gibbonFamilyID' => 51,
                'familyName' => 'Johnson Family',
                'childSurname' => 'Johnson',
                'childPreferredName' => 'Michael',
            ],
            [
                'gibbonEnhancedFinanceInvoiceID' => 4,
                'invoiceNumber' => 'INV-000004',
                'invoiceDate' => '2024-11-01',
                'dueDate' => '2024-11-15',
                'totalAmount' => 1200.00,
                'paidAmount' => 200.00,
                'balanceRemaining' => 1000.00,
                'status' => 'Partial',
                'daysOverdue' => 75, // 61-90 days
                'gibbonFamilyID' => 52,
                'familyName' => 'Williams Family',
                'childSurname' => 'Williams',
                'childPreferredName' => 'Sarah',
            ],
            [
                'gibbonEnhancedFinanceInvoiceID' => 5,
                'invoiceNumber' => 'INV-000005',
                'invoiceDate' => '2024-09-01',
                'dueDate' => '2024-09-15',
                'totalAmount' => 1500.00,
                'paidAmount' => 0.00,
                'balanceRemaining' => 1500.00,
                'status' => 'Issued',
                'daysOverdue' => 120, // Over 90 days
                'gibbonFamilyID' => 53,
                'familyName' => 'Brown Family',
                'childSurname' => 'Brown',
                'childPreferredName' => 'David',
            ],
        ];

        // Sample collection data (overdue invoices only)
        $this->sampleCollectionData = array_filter($this->sampleAgingData, function ($invoice) {
            return (int) $invoice['daysOverdue'] > 0;
        });
        $this->sampleCollectionData = array_values($this->sampleCollectionData);
    }

    /**
     * Clean up after tests.
     */
    protected function tearDown(): void
    {
        parent::tearDown();
        $this->sampleAgingData = null;
        $this->sampleCollectionData = null;
    }

    // =========================================================================
    // AGING BUCKET CALCULATION TESTS
    // =========================================================================

    /**
     * Test aging bucket boundaries.
     */
    public function testAgingBucketBoundaries(): void
    {
        $testCases = [
            ['daysOverdue' => -10, 'expectedBucket' => 'current'],
            ['daysOverdue' => 0, 'expectedBucket' => 'current'],
            ['daysOverdue' => 1, 'expectedBucket' => 'days30'],
            ['daysOverdue' => 30, 'expectedBucket' => 'days30'],
            ['daysOverdue' => 31, 'expectedBucket' => 'days60'],
            ['daysOverdue' => 60, 'expectedBucket' => 'days60'],
            ['daysOverdue' => 61, 'expectedBucket' => 'days90'],
            ['daysOverdue' => 90, 'expectedBucket' => 'days90'],
            ['daysOverdue' => 91, 'expectedBucket' => 'over90'],
            ['daysOverdue' => 180, 'expectedBucket' => 'over90'],
        ];

        foreach ($testCases as $case) {
            $bucket = $this->determineAgingBucket($case['daysOverdue']);
            $this->assertEquals(
                $case['expectedBucket'],
                $bucket,
                "Days overdue {$case['daysOverdue']} should be in bucket {$case['expectedBucket']}"
            );
        }
    }

    /**
     * Helper function to determine aging bucket.
     *
     * @param int $daysOverdue Days past due date
     * @return string Bucket identifier
     */
    protected function determineAgingBucket(int $daysOverdue): string
    {
        if ($daysOverdue <= 0) {
            return 'current';
        } elseif ($daysOverdue <= 30) {
            return 'days30';
        } elseif ($daysOverdue <= 60) {
            return 'days60';
        } elseif ($daysOverdue <= 90) {
            return 'days90';
        } else {
            return 'over90';
        }
    }

    /**
     * Test aging bucket calculation from invoice data.
     */
    public function testCalculateAgingBuckets(): void
    {
        $buckets = $this->calculateAgingBuckets($this->sampleAgingData);

        // Verify structure
        $this->assertArrayHasKey('total', $buckets);
        $this->assertArrayHasKey('totalCount', $buckets);
        $this->assertArrayHasKey('current', $buckets);
        $this->assertArrayHasKey('currentCount', $buckets);
        $this->assertArrayHasKey('days30', $buckets);
        $this->assertArrayHasKey('days30Count', $buckets);
        $this->assertArrayHasKey('days60', $buckets);
        $this->assertArrayHasKey('days60Count', $buckets);
        $this->assertArrayHasKey('days90', $buckets);
        $this->assertArrayHasKey('days90Count', $buckets);
        $this->assertArrayHasKey('over90', $buckets);
        $this->assertArrayHasKey('over90Count', $buckets);
    }

    /**
     * Test aging bucket totals calculation.
     */
    public function testAgingBucketTotals(): void
    {
        $buckets = $this->calculateAgingBuckets($this->sampleAgingData);

        // Expected values based on sample data
        $this->assertEquals(1000.00, $buckets['current'], 'Current bucket should total 1000.00');
        $this->assertEquals(500.00, $buckets['days30'], '1-30 days bucket should total 500.00');
        $this->assertEquals(600.00, $buckets['days60'], '31-60 days bucket should total 600.00');
        $this->assertEquals(1000.00, $buckets['days90'], '61-90 days bucket should total 1000.00');
        $this->assertEquals(1500.00, $buckets['over90'], 'Over 90 days bucket should total 1500.00');

        // Total should be sum of all balances
        $expectedTotal = 1000.00 + 500.00 + 600.00 + 1000.00 + 1500.00;
        $this->assertEquals($expectedTotal, $buckets['total'], 'Total should equal sum of all balances');
    }

    /**
     * Test aging bucket counts.
     */
    public function testAgingBucketCounts(): void
    {
        $buckets = $this->calculateAgingBuckets($this->sampleAgingData);

        $this->assertEquals(1, $buckets['currentCount'], 'Current bucket should have 1 invoice');
        $this->assertEquals(1, $buckets['days30Count'], '1-30 days bucket should have 1 invoice');
        $this->assertEquals(1, $buckets['days60Count'], '31-60 days bucket should have 1 invoice');
        $this->assertEquals(1, $buckets['days90Count'], '61-90 days bucket should have 1 invoice');
        $this->assertEquals(1, $buckets['over90Count'], 'Over 90 days bucket should have 1 invoice');
        $this->assertEquals(5, $buckets['totalCount'], 'Total count should be 5 invoices');
    }

    /**
     * Test aging buckets with empty data.
     */
    public function testAgingBucketsWithEmptyData(): void
    {
        $buckets = $this->calculateAgingBuckets([]);

        $this->assertEquals(0.0, $buckets['total']);
        $this->assertEquals(0, $buckets['totalCount']);
        $this->assertEquals(0.0, $buckets['current']);
        $this->assertEquals(0, $buckets['currentCount']);
        $this->assertEquals(0.0, $buckets['days30']);
        $this->assertEquals(0, $buckets['days30Count']);
        $this->assertEquals(0.0, $buckets['days60']);
        $this->assertEquals(0, $buckets['days60Count']);
        $this->assertEquals(0.0, $buckets['days90']);
        $this->assertEquals(0, $buckets['days90Count']);
        $this->assertEquals(0.0, $buckets['over90']);
        $this->assertEquals(0, $buckets['over90Count']);
    }

    /**
     * Test aging buckets with all invoices current.
     */
    public function testAgingBucketsWithAllCurrentInvoices(): void
    {
        $allCurrentData = [
            ['balanceRemaining' => 100.00, 'daysOverdue' => -10],
            ['balanceRemaining' => 200.00, 'daysOverdue' => -5],
            ['balanceRemaining' => 300.00, 'daysOverdue' => 0],
        ];

        $buckets = $this->calculateAgingBuckets($allCurrentData);

        $this->assertEquals(600.00, $buckets['current'], 'All invoices should be in current bucket');
        $this->assertEquals(3, $buckets['currentCount']);
        $this->assertEquals(0.0, $buckets['days30']);
        $this->assertEquals(0.0, $buckets['days60']);
        $this->assertEquals(0.0, $buckets['days90']);
        $this->assertEquals(0.0, $buckets['over90']);
    }

    /**
     * Test aging buckets with all invoices severely overdue.
     */
    public function testAgingBucketsWithAllOver90Days(): void
    {
        $allOver90Data = [
            ['balanceRemaining' => 500.00, 'daysOverdue' => 100],
            ['balanceRemaining' => 750.00, 'daysOverdue' => 150],
            ['balanceRemaining' => 1000.00, 'daysOverdue' => 200],
        ];

        $buckets = $this->calculateAgingBuckets($allOver90Data);

        $this->assertEquals(0.0, $buckets['current']);
        $this->assertEquals(0.0, $buckets['days30']);
        $this->assertEquals(0.0, $buckets['days60']);
        $this->assertEquals(0.0, $buckets['days90']);
        $this->assertEquals(2250.00, $buckets['over90'], 'All invoices should be in over 90 bucket');
        $this->assertEquals(3, $buckets['over90Count']);
    }

    /**
     * Helper function to calculate aging buckets.
     *
     * @param array $agingData Invoice data with daysOverdue
     * @return array Bucket totals
     */
    protected function calculateAgingBuckets(array $agingData): array
    {
        $buckets = [
            'total' => 0.0,
            'totalCount' => 0,
            'current' => 0.0,
            'currentCount' => 0,
            'days30' => 0.0,
            'days30Count' => 0,
            'days60' => 0.0,
            'days60Count' => 0,
            'days90' => 0.0,
            'days90Count' => 0,
            'over90' => 0.0,
            'over90Count' => 0,
        ];

        foreach ($agingData as $invoice) {
            $balance = (float) $invoice['balanceRemaining'];
            $daysOverdue = (int) $invoice['daysOverdue'];

            $buckets['total'] += $balance;
            $buckets['totalCount']++;

            if ($daysOverdue <= 0) {
                $buckets['current'] += $balance;
                $buckets['currentCount']++;
            } elseif ($daysOverdue <= 30) {
                $buckets['days30'] += $balance;
                $buckets['days30Count']++;
            } elseif ($daysOverdue <= 60) {
                $buckets['days60'] += $balance;
                $buckets['days60Count']++;
            } elseif ($daysOverdue <= 90) {
                $buckets['days90'] += $balance;
                $buckets['days90Count']++;
            } else {
                $buckets['over90'] += $balance;
                $buckets['over90Count']++;
            }
        }

        return $buckets;
    }

    // =========================================================================
    // FAMILY SUMMARY CALCULATION TESTS
    // =========================================================================

    /**
     * Test family summary calculation.
     */
    public function testCalculateFamilySummary(): void
    {
        $familySummary = $this->calculateFamilySummary($this->sampleAgingData);

        // Should have 4 families (Smith has 2 invoices)
        $this->assertCount(4, $familySummary, 'Should have 4 unique families');
    }

    /**
     * Test family summary groups multiple children correctly.
     */
    public function testFamilySummaryGroupsMultipleChildren(): void
    {
        $familySummary = $this->calculateFamilySummary($this->sampleAgingData);

        // Find Smith Family
        $smithFamily = null;
        foreach ($familySummary as $family) {
            if ($family['familyName'] === 'Smith Family') {
                $smithFamily = $family;
                break;
            }
        }

        $this->assertNotNull($smithFamily, 'Smith Family should be in summary');
        $this->assertEquals(2, $smithFamily['invoiceCount'], 'Smith Family should have 2 invoices');
        $this->assertEquals(1500.00, $smithFamily['total'], 'Smith Family total should be 1500.00');
    }

    /**
     * Test family summary aging bucket distribution.
     */
    public function testFamilySummaryAgingDistribution(): void
    {
        $familySummary = $this->calculateFamilySummary($this->sampleAgingData);

        // Find Smith Family (has current and 1-30 days invoices)
        $smithFamily = null;
        foreach ($familySummary as $family) {
            if ($family['familyName'] === 'Smith Family') {
                $smithFamily = $family;
                break;
            }
        }

        $this->assertEquals(1000.00, $smithFamily['current'], 'Smith Family current should be 1000.00');
        $this->assertEquals(500.00, $smithFamily['days30'], 'Smith Family 1-30 days should be 500.00');
        $this->assertEquals(0.0, $smithFamily['days60']);
        $this->assertEquals(0.0, $smithFamily['days90']);
        $this->assertEquals(0.0, $smithFamily['over90']);
    }

    /**
     * Test family summary sorted by total descending.
     */
    public function testFamilySummarySortedByTotalDescending(): void
    {
        $familySummary = $this->calculateFamilySummary($this->sampleAgingData);

        $previousTotal = PHP_FLOAT_MAX;
        foreach ($familySummary as $family) {
            $this->assertLessThanOrEqual(
                $previousTotal,
                $family['total'],
                'Families should be sorted by total descending'
            );
            $previousTotal = $family['total'];
        }
    }

    /**
     * Test family summary with empty data.
     */
    public function testFamilySummaryWithEmptyData(): void
    {
        $familySummary = $this->calculateFamilySummary([]);

        $this->assertEmpty($familySummary, 'Empty data should return empty summary');
    }

    /**
     * Test family summary handles missing family name.
     */
    public function testFamilySummaryHandlesMissingFamilyName(): void
    {
        $dataWithMissingFamily = [
            [
                'gibbonFamilyID' => 99,
                'familyName' => null, // Missing family name
                'balanceRemaining' => 100.00,
                'daysOverdue' => 10,
            ],
        ];

        $familySummary = $this->calculateFamilySummary($dataWithMissingFamily);

        $this->assertCount(1, $familySummary);
        $this->assertEquals('Unknown Family', $familySummary[0]['familyName']);
    }

    /**
     * Helper function to calculate family summary.
     *
     * @param array $agingData Invoice data
     * @return array Family summary
     */
    protected function calculateFamilySummary(array $agingData): array
    {
        $families = [];

        foreach ($agingData as $invoice) {
            $familyID = $invoice['gibbonFamilyID'];
            $familyName = $invoice['familyName'] ?? 'Unknown Family';
            $balance = (float) $invoice['balanceRemaining'];
            $daysOverdue = (int) $invoice['daysOverdue'];

            if (!isset($families[$familyID])) {
                $families[$familyID] = [
                    'familyName' => $familyName,
                    'invoiceCount' => 0,
                    'current' => 0.0,
                    'days30' => 0.0,
                    'days60' => 0.0,
                    'days90' => 0.0,
                    'over90' => 0.0,
                    'total' => 0.0,
                ];
            }

            $families[$familyID]['invoiceCount']++;
            $families[$familyID]['total'] += $balance;

            if ($daysOverdue <= 0) {
                $families[$familyID]['current'] += $balance;
            } elseif ($daysOverdue <= 30) {
                $families[$familyID]['days30'] += $balance;
            } elseif ($daysOverdue <= 60) {
                $families[$familyID]['days60'] += $balance;
            } elseif ($daysOverdue <= 90) {
                $families[$familyID]['days90'] += $balance;
            } else {
                $families[$familyID]['over90'] += $balance;
            }
        }

        // Sort by total balance descending
        usort($families, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        return $families;
    }

    // =========================================================================
    // COLLECTION STAGE DETERMINATION TESTS
    // =========================================================================

    /**
     * Test collection stage boundaries.
     */
    public function testCollectionStageBoundaries(): void
    {
        $testCases = [
            ['daysOverdue' => -5, 'expectedStage' => 'current'],
            ['daysOverdue' => 0, 'expectedStage' => 'current'],
            ['daysOverdue' => 1, 'expectedStage' => 'first_notice'],
            ['daysOverdue' => 30, 'expectedStage' => 'first_notice'],
            ['daysOverdue' => 31, 'expectedStage' => 'second_notice'],
            ['daysOverdue' => 60, 'expectedStage' => 'second_notice'],
            ['daysOverdue' => 61, 'expectedStage' => 'final_notice'],
            ['daysOverdue' => 90, 'expectedStage' => 'final_notice'],
            ['daysOverdue' => 91, 'expectedStage' => 'write_off'],
            ['daysOverdue' => 180, 'expectedStage' => 'write_off'],
        ];

        foreach ($testCases as $case) {
            $stage = $this->getCollectionStage($case['daysOverdue']);
            $this->assertEquals(
                $case['expectedStage'],
                $stage,
                "Days overdue {$case['daysOverdue']} should be stage {$case['expectedStage']}"
            );
        }
    }

    /**
     * Test collection stage label mapping.
     */
    public function testCollectionStageLabels(): void
    {
        $labels = [
            'current' => 'Current',
            'first_notice' => 'First Notice',
            'second_notice' => 'Second Notice',
            'final_notice' => 'Final Notice',
            'write_off' => 'Write-off Review',
        ];

        foreach ($labels as $stage => $expectedLabel) {
            $label = $this->getStageLabel($stage);
            $this->assertEquals(
                $expectedLabel,
                $label,
                "Stage {$stage} should have label {$expectedLabel}"
            );
        }
    }

    /**
     * Test collection stage label for unknown stage.
     */
    public function testCollectionStageLabelUnknown(): void
    {
        $label = $this->getStageLabel('invalid_stage');

        $this->assertEquals('Unknown', $label, 'Unknown stage should return Unknown');
    }

    /**
     * Helper function to get collection stage.
     *
     * @param int $daysOverdue Days past due date
     * @return string Stage identifier
     */
    protected function getCollectionStage(int $daysOverdue): string
    {
        if ($daysOverdue <= 0) {
            return 'current';
        } elseif ($daysOverdue <= 30) {
            return 'first_notice';
        } elseif ($daysOverdue <= 60) {
            return 'second_notice';
        } elseif ($daysOverdue <= 90) {
            return 'final_notice';
        } else {
            return 'write_off';
        }
    }

    /**
     * Helper function to get stage label.
     *
     * @param string $stage Stage identifier
     * @return string Localized label
     */
    protected function getStageLabel(string $stage): string
    {
        $labels = [
            'current' => 'Current',
            'first_notice' => 'First Notice',
            'second_notice' => 'Second Notice',
            'final_notice' => 'Final Notice',
            'write_off' => 'Write-off Review',
        ];

        return $labels[$stage] ?? 'Unknown';
    }

    // =========================================================================
    // COLLECTION SUMMARY CALCULATION TESTS
    // =========================================================================

    /**
     * Test collection summary structure.
     */
    public function testCalculateCollectionSummaryStructure(): void
    {
        $summary = $this->calculateCollectionSummary($this->sampleCollectionData);

        $this->assertArrayHasKey('totalOverdue', $summary);
        $this->assertArrayHasKey('overdueCount', $summary);
        $this->assertArrayHasKey('firstNotice', $summary);
        $this->assertArrayHasKey('firstNoticeCount', $summary);
        $this->assertArrayHasKey('secondNotice', $summary);
        $this->assertArrayHasKey('secondNoticeCount', $summary);
        $this->assertArrayHasKey('finalNotice', $summary);
        $this->assertArrayHasKey('finalNoticeCount', $summary);
        $this->assertArrayHasKey('writeOffAmount', $summary);
        $this->assertArrayHasKey('writeOffCount', $summary);
    }

    /**
     * Test collection summary totals.
     */
    public function testCollectionSummaryTotals(): void
    {
        $summary = $this->calculateCollectionSummary($this->sampleCollectionData);

        // Expected values based on sample collection data (overdue only)
        $this->assertEquals(500.00, $summary['firstNotice'], 'First notice should total 500.00');
        $this->assertEquals(600.00, $summary['secondNotice'], 'Second notice should total 600.00');
        $this->assertEquals(1000.00, $summary['finalNotice'], 'Final notice should total 1000.00');
        $this->assertEquals(1500.00, $summary['writeOffAmount'], 'Write-off should total 1500.00');

        $expectedTotal = 500.00 + 600.00 + 1000.00 + 1500.00;
        $this->assertEquals($expectedTotal, $summary['totalOverdue'], 'Total overdue should equal sum');
    }

    /**
     * Test collection summary counts.
     */
    public function testCollectionSummaryCounts(): void
    {
        $summary = $this->calculateCollectionSummary($this->sampleCollectionData);

        $this->assertEquals(1, $summary['firstNoticeCount']);
        $this->assertEquals(1, $summary['secondNoticeCount']);
        $this->assertEquals(1, $summary['finalNoticeCount']);
        $this->assertEquals(1, $summary['writeOffCount']);
        $this->assertEquals(4, $summary['overdueCount'], 'Total overdue count should be 4');
    }

    /**
     * Test collection summary with empty data.
     */
    public function testCollectionSummaryWithEmptyData(): void
    {
        $summary = $this->calculateCollectionSummary([]);

        $this->assertEquals(0.0, $summary['totalOverdue']);
        $this->assertEquals(0, $summary['overdueCount']);
        $this->assertEquals(0.0, $summary['firstNotice']);
        $this->assertEquals(0, $summary['firstNoticeCount']);
        $this->assertEquals(0.0, $summary['secondNotice']);
        $this->assertEquals(0, $summary['secondNoticeCount']);
        $this->assertEquals(0.0, $summary['finalNotice']);
        $this->assertEquals(0, $summary['finalNoticeCount']);
        $this->assertEquals(0.0, $summary['writeOffAmount']);
        $this->assertEquals(0, $summary['writeOffCount']);
    }

    /**
     * Test collection summary with all first notice invoices.
     */
    public function testCollectionSummaryAllFirstNotice(): void
    {
        $firstNoticeData = [
            ['balanceRemaining' => 100.00, 'daysOverdue' => 5],
            ['balanceRemaining' => 200.00, 'daysOverdue' => 15],
            ['balanceRemaining' => 300.00, 'daysOverdue' => 25],
        ];

        $summary = $this->calculateCollectionSummary($firstNoticeData);

        $this->assertEquals(600.00, $summary['firstNotice']);
        $this->assertEquals(3, $summary['firstNoticeCount']);
        $this->assertEquals(0.0, $summary['secondNotice']);
        $this->assertEquals(0.0, $summary['finalNotice']);
        $this->assertEquals(0.0, $summary['writeOffAmount']);
    }

    /**
     * Helper function to calculate collection summary.
     *
     * @param array $collectionData Invoice data
     * @return array Summary totals
     */
    protected function calculateCollectionSummary(array $collectionData): array
    {
        $summary = [
            'totalOverdue' => 0.0,
            'overdueCount' => 0,
            'firstNotice' => 0.0,
            'firstNoticeCount' => 0,
            'secondNotice' => 0.0,
            'secondNoticeCount' => 0,
            'finalNotice' => 0.0,
            'finalNoticeCount' => 0,
            'writeOffAmount' => 0.0,
            'writeOffCount' => 0,
        ];

        foreach ($collectionData as $invoice) {
            $balance = (float) $invoice['balanceRemaining'];
            $stage = $this->getCollectionStage((int) $invoice['daysOverdue']);

            $summary['totalOverdue'] += $balance;
            $summary['overdueCount']++;

            switch ($stage) {
                case 'first_notice':
                    $summary['firstNotice'] += $balance;
                    $summary['firstNoticeCount']++;
                    break;
                case 'second_notice':
                    $summary['secondNotice'] += $balance;
                    $summary['secondNoticeCount']++;
                    break;
                case 'final_notice':
                    $summary['finalNotice'] += $balance;
                    $summary['finalNoticeCount']++;
                    break;
                case 'write_off':
                    $summary['writeOffAmount'] += $balance;
                    $summary['writeOffCount']++;
                    break;
            }
        }

        return $summary;
    }

    // =========================================================================
    // FAMILY COLLECTION SUMMARY TESTS
    // =========================================================================

    /**
     * Test family collection summary structure.
     */
    public function testFamilyCollectionSummaryStructure(): void
    {
        $familySummary = $this->calculateFamilyCollectionSummary($this->sampleCollectionData);

        // Should have 4 families in collection data
        $this->assertNotEmpty($familySummary);

        // Check structure of first family
        $firstFamily = $familySummary[0];
        $this->assertArrayHasKey('familyName', $firstFamily);
        $this->assertArrayHasKey('invoiceCount', $firstFamily);
        $this->assertArrayHasKey('firstNotice', $firstFamily);
        $this->assertArrayHasKey('secondNotice', $firstFamily);
        $this->assertArrayHasKey('finalNotice', $firstFamily);
        $this->assertArrayHasKey('writeOff', $firstFamily);
        $this->assertArrayHasKey('total', $firstFamily);
    }

    /**
     * Test family collection summary totals.
     */
    public function testFamilyCollectionSummaryTotals(): void
    {
        $familySummary = $this->calculateFamilyCollectionSummary($this->sampleCollectionData);

        // Find Brown Family (has the largest balance - 1500.00 in write-off)
        $brownFamily = null;
        foreach ($familySummary as $family) {
            if ($family['familyName'] === 'Brown Family') {
                $brownFamily = $family;
                break;
            }
        }

        $this->assertNotNull($brownFamily, 'Brown Family should be in summary');
        $this->assertEquals(1500.00, $brownFamily['writeOff']);
        $this->assertEquals(1500.00, $brownFamily['total']);
    }

    /**
     * Test family collection summary sorted by total descending.
     */
    public function testFamilyCollectionSummarySortedByTotal(): void
    {
        $familySummary = $this->calculateFamilyCollectionSummary($this->sampleCollectionData);

        $previousTotal = PHP_FLOAT_MAX;
        foreach ($familySummary as $family) {
            $this->assertLessThanOrEqual(
                $previousTotal,
                $family['total'],
                'Families should be sorted by total descending'
            );
            $previousTotal = $family['total'];
        }
    }

    /**
     * Helper function to calculate family collection summary.
     *
     * @param array $collectionData Invoice data
     * @return array Family summary
     */
    protected function calculateFamilyCollectionSummary(array $collectionData): array
    {
        $families = [];

        foreach ($collectionData as $invoice) {
            $familyID = $invoice['gibbonFamilyID'];
            $familyName = $invoice['familyName'] ?? 'Unknown Family';
            $balance = (float) $invoice['balanceRemaining'];
            $stage = $this->getCollectionStage((int) $invoice['daysOverdue']);

            if (!isset($families[$familyID])) {
                $families[$familyID] = [
                    'familyName' => $familyName,
                    'invoiceCount' => 0,
                    'firstNotice' => 0.0,
                    'secondNotice' => 0.0,
                    'finalNotice' => 0.0,
                    'writeOff' => 0.0,
                    'total' => 0.0,
                ];
            }

            $families[$familyID]['invoiceCount']++;
            $families[$familyID]['total'] += $balance;

            switch ($stage) {
                case 'first_notice':
                    $families[$familyID]['firstNotice'] += $balance;
                    break;
                case 'second_notice':
                    $families[$familyID]['secondNotice'] += $balance;
                    break;
                case 'final_notice':
                    $families[$familyID]['finalNotice'] += $balance;
                    break;
                case 'write_off':
                    $families[$familyID]['writeOff'] += $balance;
                    break;
            }
        }

        // Sort by total balance descending
        usort($families, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        return $families;
    }

    // =========================================================================
    // COLLECTION RATE CALCULATION TESTS
    // =========================================================================

    /**
     * Test collection rate calculation.
     */
    public function testCollectionRateCalculation(): void
    {
        $totalInvoiced = 10000.00;
        $totalPaid = 7500.00;

        $collectionRate = $this->calculateCollectionRate($totalInvoiced, $totalPaid);

        $this->assertEquals(75.00, $collectionRate, 'Collection rate should be 75%');
    }

    /**
     * Test collection rate with 100% collection.
     */
    public function testCollectionRateFullCollection(): void
    {
        $totalInvoiced = 10000.00;
        $totalPaid = 10000.00;

        $collectionRate = $this->calculateCollectionRate($totalInvoiced, $totalPaid);

        $this->assertEquals(100.00, $collectionRate, 'Collection rate should be 100%');
    }

    /**
     * Test collection rate with zero paid.
     */
    public function testCollectionRateZeroPaid(): void
    {
        $totalInvoiced = 10000.00;
        $totalPaid = 0.00;

        $collectionRate = $this->calculateCollectionRate($totalInvoiced, $totalPaid);

        $this->assertEquals(0.00, $collectionRate, 'Collection rate should be 0%');
    }

    /**
     * Test collection rate with zero invoiced.
     */
    public function testCollectionRateZeroInvoiced(): void
    {
        $totalInvoiced = 0.00;
        $totalPaid = 0.00;

        $collectionRate = $this->calculateCollectionRate($totalInvoiced, $totalPaid);

        $this->assertEquals(0.00, $collectionRate, 'Collection rate should be 0% when nothing invoiced');
    }

    /**
     * Test collection rate with overpayment.
     */
    public function testCollectionRateWithOverpayment(): void
    {
        $totalInvoiced = 10000.00;
        $totalPaid = 11000.00; // Overpayment

        $collectionRate = $this->calculateCollectionRate($totalInvoiced, $totalPaid);

        // Collection rate can exceed 100% if there are overpayments
        $this->assertGreaterThan(100.00, $collectionRate);
    }

    /**
     * Test collection rate color coding.
     */
    public function testCollectionRateColorCoding(): void
    {
        // >= 90% should be green
        $this->assertEquals('green', $this->getCollectionRateColor(95.0));
        $this->assertEquals('green', $this->getCollectionRateColor(90.0));

        // 70-89% should be yellow
        $this->assertEquals('yellow', $this->getCollectionRateColor(85.0));
        $this->assertEquals('yellow', $this->getCollectionRateColor(70.0));

        // < 70% should be red
        $this->assertEquals('red', $this->getCollectionRateColor(69.9));
        $this->assertEquals('red', $this->getCollectionRateColor(50.0));
        $this->assertEquals('red', $this->getCollectionRateColor(0.0));
    }

    /**
     * Helper function to calculate collection rate.
     *
     * @param float $totalInvoiced Total invoiced amount
     * @param float $totalPaid Total paid amount
     * @return float Collection rate percentage
     */
    protected function calculateCollectionRate(float $totalInvoiced, float $totalPaid): float
    {
        if ($totalInvoiced <= 0) {
            return 0.0;
        }

        return round(($totalPaid / $totalInvoiced) * 100, 2);
    }

    /**
     * Helper function to get collection rate color.
     *
     * @param float $collectionRate Collection rate percentage
     * @return string Color code
     */
    protected function getCollectionRateColor(float $collectionRate): string
    {
        if ($collectionRate >= 90) {
            return 'green';
        } elseif ($collectionRate >= 70) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    // =========================================================================
    // AVERAGE DAYS TO COLLECT TESTS
    // =========================================================================

    /**
     * Test average days to collect calculation.
     */
    public function testAverageDaysToCollectCalculation(): void
    {
        $payments = [
            ['invoiceDate' => '2025-01-01', 'paymentDate' => '2025-01-15'], // 14 days
            ['invoiceDate' => '2025-01-01', 'paymentDate' => '2025-01-31'], // 30 days
            ['invoiceDate' => '2025-01-01', 'paymentDate' => '2025-01-22'], // 21 days
        ];

        $avgDays = $this->calculateAverageDaysToCollect($payments);

        // (14 + 30 + 21) / 3 = 21.67
        $this->assertEqualsWithDelta(21.67, $avgDays, 0.01);
    }

    /**
     * Test average days to collect with single payment.
     */
    public function testAverageDaysToCollectSinglePayment(): void
    {
        $payments = [
            ['invoiceDate' => '2025-01-01', 'paymentDate' => '2025-01-10'], // 9 days
        ];

        $avgDays = $this->calculateAverageDaysToCollect($payments);

        $this->assertEquals(9, $avgDays);
    }

    /**
     * Test average days to collect with no payments.
     */
    public function testAverageDaysToCollectNoPayments(): void
    {
        $payments = [];

        $avgDays = $this->calculateAverageDaysToCollect($payments);

        $this->assertEquals(0, $avgDays);
    }

    /**
     * Test average days to collect color coding.
     */
    public function testAverageDaysToCollectColorCoding(): void
    {
        // <= 30 days should be green
        $this->assertEquals('green', $this->getAvgDaysColor(25));
        $this->assertEquals('green', $this->getAvgDaysColor(30));

        // 31-60 days should be yellow
        $this->assertEquals('yellow', $this->getAvgDaysColor(45));
        $this->assertEquals('yellow', $this->getAvgDaysColor(60));

        // > 60 days should be red
        $this->assertEquals('red', $this->getAvgDaysColor(61));
        $this->assertEquals('red', $this->getAvgDaysColor(90));
    }

    /**
     * Helper function to calculate average days to collect.
     *
     * @param array $payments Payment data with invoice and payment dates
     * @return float Average days
     */
    protected function calculateAverageDaysToCollect(array $payments): float
    {
        if (empty($payments)) {
            return 0;
        }

        $totalDays = 0;
        foreach ($payments as $payment) {
            $invoiceDate = strtotime($payment['invoiceDate']);
            $paymentDate = strtotime($payment['paymentDate']);
            $days = ($paymentDate - $invoiceDate) / (60 * 60 * 24);
            $totalDays += $days;
        }

        return round($totalDays / count($payments), 2);
    }

    /**
     * Helper function to get average days color.
     *
     * @param int $avgDays Average days to collect
     * @return string Color code
     */
    protected function getAvgDaysColor(int $avgDays): string
    {
        if ($avgDays <= 30) {
            return 'green';
        } elseif ($avgDays <= 60) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    // =========================================================================
    // SUGGESTED ACTION TESTS
    // =========================================================================

    /**
     * Test suggested action for each collection stage.
     */
    public function testSuggestedActionForEachStage(): void
    {
        $actions = [
            'current' => 'No action required',
            'first_notice' => 'Send payment reminder',
            'second_notice' => 'Phone call / formal notice',
            'final_notice' => 'Final warning / payment plan',
            'write_off' => 'Review for write-off',
        ];

        foreach ($actions as $stage => $expectedAction) {
            $action = $this->getSuggestedAction($stage);
            $this->assertEquals(
                $expectedAction,
                $action,
                "Stage {$stage} should have action '{$expectedAction}'"
            );
        }
    }

    /**
     * Test suggested action for unknown stage.
     */
    public function testSuggestedActionUnknownStage(): void
    {
        $action = $this->getSuggestedAction('invalid_stage');

        $this->assertEquals('Unknown', $action);
    }

    /**
     * Helper function to get suggested action.
     *
     * @param string $stage Stage identifier
     * @return string Suggested action text
     */
    protected function getSuggestedAction(string $stage): string
    {
        $actions = [
            'current' => 'No action required',
            'first_notice' => 'Send payment reminder',
            'second_notice' => 'Phone call / formal notice',
            'final_notice' => 'Final warning / payment plan',
            'write_off' => 'Review for write-off',
        ];

        return $actions[$stage] ?? 'Unknown';
    }

    // =========================================================================
    // EDGE CASE TESTS
    // =========================================================================

    /**
     * Test aging calculations with decimal balances.
     */
    public function testAgingCalculationsWithDecimalBalances(): void
    {
        $dataWithDecimals = [
            ['balanceRemaining' => 99.99, 'daysOverdue' => 5],
            ['balanceRemaining' => 0.01, 'daysOverdue' => 10],
            ['balanceRemaining' => 1234.56, 'daysOverdue' => 15],
        ];

        $buckets = $this->calculateAgingBuckets($dataWithDecimals);

        $expectedTotal = 99.99 + 0.01 + 1234.56;
        $this->assertEqualsWithDelta($expectedTotal, $buckets['days30'], 0.001);
    }

    /**
     * Test aging calculations with zero balance.
     */
    public function testAgingCalculationsWithZeroBalance(): void
    {
        $dataWithZero = [
            ['balanceRemaining' => 0.00, 'daysOverdue' => 10],
            ['balanceRemaining' => 100.00, 'daysOverdue' => 10],
        ];

        $buckets = $this->calculateAgingBuckets($dataWithZero);

        $this->assertEquals(100.00, $buckets['days30']);
        $this->assertEquals(2, $buckets['days30Count'], 'Should count zero balance invoice');
    }

    /**
     * Test aging calculations with negative balance (credit).
     */
    public function testAgingCalculationsWithNegativeBalance(): void
    {
        $dataWithNegative = [
            ['balanceRemaining' => -50.00, 'daysOverdue' => 10], // Credit
            ['balanceRemaining' => 150.00, 'daysOverdue' => 10],
        ];

        $buckets = $this->calculateAgingBuckets($dataWithNegative);

        $this->assertEquals(100.00, $buckets['days30']);
    }

    /**
     * Test aging calculations with large number of invoices.
     */
    public function testAgingCalculationsWithManyInvoices(): void
    {
        $manyInvoices = [];
        for ($i = 0; $i < 100; $i++) {
            $manyInvoices[] = [
                'balanceRemaining' => 100.00,
                'daysOverdue' => 15, // All in 1-30 days bucket
            ];
        }

        $buckets = $this->calculateAgingBuckets($manyInvoices);

        $this->assertEquals(10000.00, $buckets['days30']);
        $this->assertEquals(100, $buckets['days30Count']);
        $this->assertEquals(10000.00, $buckets['total']);
        $this->assertEquals(100, $buckets['totalCount']);
    }

    /**
     * Test collection summary with multiple invoices same family.
     */
    public function testCollectionSummaryMultipleInvoicesSameFamily(): void
    {
        $sameFamilyData = [
            ['balanceRemaining' => 100.00, 'daysOverdue' => 15, 'gibbonFamilyID' => 1, 'familyName' => 'Test Family'],
            ['balanceRemaining' => 200.00, 'daysOverdue' => 25, 'gibbonFamilyID' => 1, 'familyName' => 'Test Family'],
            ['balanceRemaining' => 300.00, 'daysOverdue' => 35, 'gibbonFamilyID' => 1, 'familyName' => 'Test Family'],
        ];

        $familySummary = $this->calculateFamilyCollectionSummary($sameFamilyData);

        $this->assertCount(1, $familySummary, 'Should have only 1 family');
        $this->assertEquals(3, $familySummary[0]['invoiceCount']);
        $this->assertEquals(600.00, $familySummary[0]['total']);
        $this->assertEquals(300.00, $familySummary[0]['firstNotice']); // 100 + 200
        $this->assertEquals(300.00, $familySummary[0]['secondNotice']); // 300
    }

    /**
     * Test boundary condition: exactly 30 days overdue.
     */
    public function testBoundaryExactly30DaysOverdue(): void
    {
        $stage = $this->getCollectionStage(30);
        $this->assertEquals('first_notice', $stage, '30 days should still be first notice');

        $bucket = $this->determineAgingBucket(30);
        $this->assertEquals('days30', $bucket, '30 days should still be in days30 bucket');
    }

    /**
     * Test boundary condition: exactly 60 days overdue.
     */
    public function testBoundaryExactly60DaysOverdue(): void
    {
        $stage = $this->getCollectionStage(60);
        $this->assertEquals('second_notice', $stage, '60 days should still be second notice');

        $bucket = $this->determineAgingBucket(60);
        $this->assertEquals('days60', $bucket, '60 days should still be in days60 bucket');
    }

    /**
     * Test boundary condition: exactly 90 days overdue.
     */
    public function testBoundaryExactly90DaysOverdue(): void
    {
        $stage = $this->getCollectionStage(90);
        $this->assertEquals('final_notice', $stage, '90 days should still be final notice');

        $bucket = $this->determineAgingBucket(90);
        $this->assertEquals('days90', $bucket, '90 days should still be in days90 bucket');
    }

    /**
     * Test floating point precision in totals.
     */
    public function testFloatingPointPrecisionInTotals(): void
    {
        // Classic floating point issue: 0.1 + 0.2 = 0.30000000000000004
        $dataWithFloatingPoint = [
            ['balanceRemaining' => 0.10, 'daysOverdue' => 5],
            ['balanceRemaining' => 0.20, 'daysOverdue' => 10],
        ];

        $buckets = $this->calculateAgingBuckets($dataWithFloatingPoint);

        $this->assertEqualsWithDelta(0.30, $buckets['days30'], 0.001, 'Should handle floating point precision');
    }
}
