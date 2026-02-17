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

namespace Gibbon\Module\EnhancedFinance\Export;

use Gibbon\Module\EnhancedFinance\Domain\ExportGateway;
use Gibbon\Contracts\Database\Connection;
use Gibbon\Domain\System\SettingGateway;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Report Excel Exporter
 *
 * Generates Excel exports for financial reports (Aging, Collection, Revenue).
 * Extends ExcelExporter base class for common spreadsheet functionality.
 *
 * Features:
 * - Aging Report: Outstanding invoices by aging bucket (30/60/90+ days)
 * - Collection Report: Collection tracking with stages and suggested actions
 * - Revenue Report: Monthly revenue breakdown with YTD comparison
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class ReportExcelExporter extends ExcelExporter
{
    /**
     * Color constants for aging buckets
     */
    public const COLOR_CURRENT = '22C55E';     // Green
    public const COLOR_DAYS_30 = 'EAB308';     // Yellow
    public const COLOR_DAYS_60 = 'F97316';     // Orange
    public const COLOR_DAYS_90 = 'F87171';     // Light Red
    public const COLOR_OVER_90 = 'EF4444';     // Red

    /**
     * Export Aging Report to Excel.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param string $schoolYearName School year name
     * @param string $currency Currency code
     * @param int $exportedByID User ID for export logging
     * @return array Export result with file path
     */
    public function exportAgingReport($gibbonSchoolYearID, $schoolYearName, $currency, $exportedByID)
    {
        // Fetch data
        $agingData = $this->fetchAgingData($gibbonSchoolYearID);
        $agingBuckets = $this->calculateAgingBuckets($agingData);

        // Create spreadsheet
        $this->createSpreadsheet('Accounts Receivable Aging Report - ' . $schoolYearName);
        $sheet = $this->getActiveSheet();
        $sheet->setTitle($this->sanitizeSheetTitle('Aging Report'));

        // Write report header
        $row = $this->writeReportHeader($sheet, __('Accounts Receivable Aging Report'), [
            __('School Year') => $schoolYearName,
            __('Generated') => date('Y-m-d H:i:s'),
            __('Currency') => $currency,
        ]);

        // Summary section
        $row++;
        $sheet->setCellValue('A' . $row, __('SUMMARY'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;

        // Summary headers
        $summaryHeaders = [__('Aging Bucket'), __('Amount'), __('Invoice Count')];
        $row = $this->writeHeaders($sheet, $summaryHeaders, $row);
        $this->getActiveSheet()->setAutoFilter(null); // Disable auto-filter for summary

        // Summary data
        $summaryStartRow = $row;
        $summaryData = [
            [__('Current (Not Yet Due)'), $agingBuckets['current'], $agingBuckets['currentCount']],
            [__('1-30 Days Overdue'), $agingBuckets['days30'], $agingBuckets['days30Count']],
            [__('31-60 Days Overdue'), $agingBuckets['days60'], $agingBuckets['days60Count']],
            [__('61-90 Days Overdue'), $agingBuckets['days90'], $agingBuckets['days90Count']],
            [__('Over 90 Days Overdue'), $agingBuckets['over90'], $agingBuckets['over90Count']],
        ];

        $colors = [self::COLOR_CURRENT, self::COLOR_DAYS_30, self::COLOR_DAYS_60, self::COLOR_DAYS_90, self::COLOR_OVER_90];
        foreach ($summaryData as $index => $summaryRow) {
            $row = $this->writeRow($sheet, $summaryRow, $row);
            // Apply color indicator
            $sheet->getStyle('A' . ($row - 1))->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $colors[$index]],
                ],
            ]);
        }

        // Total row
        $row = $this->writeTotalRow($sheet, [
            __('TOTAL OUTSTANDING'),
            $agingBuckets['total'],
            $agingBuckets['totalCount']
        ], $row);

        // Format currency column
        $this->formatColumnAsCurrency($sheet, 'B', $summaryStartRow, $row - 1);

        // Detail section
        $row += 2;
        $sheet->setCellValue('A' . $row, __('DETAIL'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;

        // Detail headers
        $detailHeaders = [
            __('Invoice #'),
            __('Family'),
            __('Child'),
            __('Invoice Date'),
            __('Due Date'),
            __('Days Overdue'),
            __('Aging Bucket'),
            __('Total Amount'),
            __('Paid Amount'),
            __('Balance'),
            __('Status')
        ];
        $row = $this->writeHeaders($sheet, $detailHeaders, $row);
        $dataStartRow = $row;

        // Write detail rows
        foreach ($agingData as $invoice) {
            $daysOverdue = (int) ($invoice['daysOverdue'] ?? 0);
            $bucket = $this->getAgingBucketLabel($daysOverdue);

            $rowData = [
                $invoice['invoiceNumber'],
                $invoice['familyName'] ?? '',
                trim(($invoice['childPreferredName'] ?? '') . ' ' . ($invoice['childSurname'] ?? '')),
                $this->formatDate($invoice['invoiceDate']),
                $this->formatDate($invoice['dueDate']),
                max(0, $daysOverdue),
                $bucket,
                $this->formatAmount($invoice['totalAmount']),
                $this->formatAmount($invoice['paidAmount']),
                $this->formatAmount($invoice['balanceRemaining']),
                $invoice['status']
            ];

            $row = $this->writeRow($sheet, $rowData, $row);

            // Apply color to aging bucket cell
            $bucketColor = $this->getAgingBucketColor($daysOverdue);
            $sheet->getStyle('G' . ($row - 1))->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $bucketColor],
                ],
            ]);
        }

        // Format currency columns
        $this->formatColumnAsCurrency($sheet, 'H', $dataStartRow, $row - 1);
        $this->formatColumnAsCurrency($sheet, 'I', $dataStartRow, $row - 1);
        $this->formatColumnAsCurrency($sheet, 'J', $dataStartRow, $row - 1);

        // Auto-size columns
        $this->autoSizeColumns($sheet);

        // Generate file name and save
        $fileName = $this->generateFileName('aging_report', $gibbonSchoolYearID);
        $filePath = $this->saveSpreadsheet($fileName);

        // Log export
        $this->logExport('REPORT', 'Aging Report', $gibbonSchoolYearID, $filePath, $exportedByID, count($agingData));

        return [
            'filePath' => $filePath,
            'fileName' => $fileName,
            'recordCount' => count($agingData),
        ];
    }

    /**
     * Export Collection Report to Excel.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param string $schoolYearName School year name
     * @param string $currency Currency code
     * @param int $exportedByID User ID for export logging
     * @param string $filterStage Optional collection stage filter
     * @return array Export result with file path
     */
    public function exportCollectionReport($gibbonSchoolYearID, $schoolYearName, $currency, $exportedByID, $filterStage = '')
    {
        // Fetch data
        $collectionData = $this->fetchCollectionData($gibbonSchoolYearID);
        $collectionSummary = $this->calculateCollectionSummary($collectionData);
        $paymentStats = $this->calculatePaymentStats($gibbonSchoolYearID);

        // Apply filter if specified
        if (!empty($filterStage)) {
            $collectionData = array_filter($collectionData, function ($invoice) use ($filterStage) {
                return $this->getCollectionStage($invoice['daysOverdue']) === $filterStage;
            });
            $collectionData = array_values($collectionData);
        }

        // Create spreadsheet
        $title = 'Collection Report - ' . $schoolYearName;
        if (!empty($filterStage)) {
            $title .= ' (' . $this->getStageLabel($filterStage) . ')';
        }
        $this->createSpreadsheet($title);
        $sheet = $this->getActiveSheet();
        $sheet->setTitle($this->sanitizeSheetTitle('Collection Report'));

        // Write report header
        $row = $this->writeReportHeader($sheet, __('Collection Report'), [
            __('School Year') => $schoolYearName,
            __('Generated') => date('Y-m-d H:i:s'),
            __('Currency') => $currency,
        ]);

        // KPI Section
        $row++;
        $sheet->setCellValue('A' . $row, __('KEY PERFORMANCE INDICATORS'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;

        $kpiData = [
            [__('Collection Rate'), number_format($paymentStats['collectionRate'], 1) . '%'],
            [__('Average Days to Collect'), number_format($paymentStats['avgDaysToCollect'], 0)],
        ];
        foreach ($kpiData as $kpi) {
            $row = $this->writeRow($sheet, $kpi, $row);
        }

        // Summary section
        $row += 2;
        $sheet->setCellValue('A' . $row, __('COLLECTION STAGE SUMMARY'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;

        // Summary headers
        $summaryHeaders = [__('Stage'), __('Amount'), __('Invoice Count')];
        $row = $this->writeHeaders($sheet, $summaryHeaders, $row);
        $this->getActiveSheet()->setAutoFilter(null);

        // Summary data
        $summaryStartRow = $row;
        $summaryData = [
            [__('First Notice (1-30 days)'), $collectionSummary['firstNotice'], $collectionSummary['firstNoticeCount']],
            [__('Second Notice (31-60 days)'), $collectionSummary['secondNotice'], $collectionSummary['secondNoticeCount']],
            [__('Final Notice (61-90 days)'), $collectionSummary['finalNotice'], $collectionSummary['finalNoticeCount']],
            [__('Write-off Review (90+ days)'), $collectionSummary['writeOffAmount'], $collectionSummary['writeOffCount']],
        ];

        $colors = [self::COLOR_DAYS_30, self::COLOR_DAYS_60, self::COLOR_DAYS_90, self::COLOR_OVER_90];
        foreach ($summaryData as $index => $summaryRow) {
            $row = $this->writeRow($sheet, $summaryRow, $row);
            $sheet->getStyle('A' . ($row - 1))->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $colors[$index]],
                ],
            ]);
        }

        // Total row
        $row = $this->writeTotalRow($sheet, [
            __('TOTAL IN COLLECTION'),
            $collectionSummary['totalOverdue'],
            $collectionSummary['overdueCount']
        ], $row);

        $this->formatColumnAsCurrency($sheet, 'B', $summaryStartRow, $row - 1);

        // Detail section
        $row += 2;
        $sheet->setCellValue('A' . $row, __('DETAIL'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;

        // Detail headers
        $detailHeaders = [
            __('Invoice #'),
            __('Family'),
            __('Child'),
            __('Invoice Date'),
            __('Due Date'),
            __('Days Overdue'),
            __('Collection Stage'),
            __('Suggested Action'),
            __('Total Amount'),
            __('Paid Amount'),
            __('Balance'),
            __('Status')
        ];
        $row = $this->writeHeaders($sheet, $detailHeaders, $row);
        $dataStartRow = $row;

        // Write detail rows
        foreach ($collectionData as $invoice) {
            $daysOverdue = (int) ($invoice['daysOverdue'] ?? 0);
            $stage = $this->getCollectionStage($daysOverdue);
            $stageLabel = $this->getStageLabel($stage);
            $action = $this->getSuggestedActionText($stage);

            $rowData = [
                $invoice['invoiceNumber'],
                $invoice['familyName'] ?? '',
                trim(($invoice['childPreferredName'] ?? '') . ' ' . ($invoice['childSurname'] ?? '')),
                $this->formatDate($invoice['invoiceDate']),
                $this->formatDate($invoice['dueDate']),
                max(0, $daysOverdue),
                $stageLabel,
                $action,
                $this->formatAmount($invoice['totalAmount']),
                $this->formatAmount($invoice['paidAmount']),
                $this->formatAmount($invoice['balanceRemaining']),
                $invoice['status']
            ];

            $row = $this->writeRow($sheet, $rowData, $row);

            // Apply color to stage cell
            $stageColor = $this->getCollectionStageColor($stage);
            $sheet->getStyle('G' . ($row - 1))->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $stageColor],
                ],
            ]);
        }

        // Format currency columns
        $this->formatColumnAsCurrency($sheet, 'I', $dataStartRow, $row - 1);
        $this->formatColumnAsCurrency($sheet, 'J', $dataStartRow, $row - 1);
        $this->formatColumnAsCurrency($sheet, 'K', $dataStartRow, $row - 1);

        // Auto-size columns
        $this->autoSizeColumns($sheet);

        // Generate file name and save
        $fileName = $this->generateFileName('collection_report', $gibbonSchoolYearID);
        $filePath = $this->saveSpreadsheet($fileName);

        // Log export
        $this->logExport('REPORT', 'Collection Report', $gibbonSchoolYearID, $filePath, $exportedByID, count($collectionData));

        return [
            'filePath' => $filePath,
            'fileName' => $fileName,
            'recordCount' => count($collectionData),
        ];
    }

    /**
     * Export Revenue Report to Excel.
     *
     * @param int $gibbonSchoolYearID School year ID
     * @param string $schoolYearName School year name
     * @param string $currency Currency code
     * @param int $exportedByID User ID for export logging
     * @return array Export result with file path
     */
    public function exportRevenueReport($gibbonSchoolYearID, $schoolYearName, $currency, $exportedByID)
    {
        // Fetch data
        $ytdSummary = $this->fetchYTDSummary($gibbonSchoolYearID);
        $monthlyRevenue = $this->fetchMonthlyRevenue($gibbonSchoolYearID);
        $paymentMethodBreakdown = $this->fetchPaymentMethodBreakdown($gibbonSchoolYearID);

        // Get previous year data
        $previousSchoolYear = $this->getPreviousSchoolYear($gibbonSchoolYearID);
        $previousYtdSummary = $previousSchoolYear ? $this->fetchYTDSummary($previousSchoolYear['gibbonSchoolYearID']) : null;
        $previousSchoolYearName = $previousSchoolYear['name'] ?? __('Previous Year');

        // Calculate comparison
        $comparison = $this->calculateComparison($ytdSummary, $previousYtdSummary);

        // Create spreadsheet
        $this->createSpreadsheet('Revenue Report - ' . $schoolYearName);
        $sheet = $this->getActiveSheet();
        $sheet->setTitle($this->sanitizeSheetTitle('Revenue Report'));

        // Write report header
        $row = $this->writeReportHeader($sheet, __('Revenue Report'), [
            __('School Year') => $schoolYearName,
            __('Generated') => date('Y-m-d H:i:s'),
            __('Currency') => $currency,
        ]);

        // YTD Summary Section
        $row++;
        $sheet->setCellValue('A' . $row, __('YTD SUMMARY'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;

        $collectionRate = $ytdSummary['totalInvoiced'] > 0
            ? round(($ytdSummary['totalCollected'] / $ytdSummary['totalInvoiced']) * 100, 1)
            : 0;

        $ytdData = [
            [__('Total Invoiced'), $ytdSummary['totalInvoiced']],
            [__('Total Collected'), $ytdSummary['totalCollected']],
            [__('Outstanding'), $ytdSummary['totalInvoiced'] - $ytdSummary['totalCollected']],
            [__('Collection Rate'), $collectionRate . '%'],
            [__('Invoice Count'), $ytdSummary['invoiceCount']],
            [__('Payment Count'), $ytdSummary['paymentCount']],
        ];

        foreach ($ytdData as $ytdRow) {
            $row = $this->writeRow($sheet, $ytdRow, $row);
        }

        // Format currency cells
        $this->formatColumnAsCurrency($sheet, 'B', $row - 6, $row - 3);

        // Year-over-Year Comparison
        if ($comparison['invoicedChange'] !== null) {
            $row += 2;
            $sheet->setCellValue('A' . $row, __('YEAR-OVER-YEAR COMPARISON'));
            $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
            $row++;

            $comparisonData = [
                [__('Metric'), $previousSchoolYearName, $schoolYearName, __('Change %')],
                [__('Invoiced'), $previousYtdSummary['totalInvoiced'] ?? 0, $ytdSummary['totalInvoiced'], number_format($comparison['invoicedChange'], 1) . '%'],
            ];

            if ($comparison['collectedChange'] !== null) {
                $comparisonData[] = [__('Collected'), $previousYtdSummary['totalCollected'] ?? 0, $ytdSummary['totalCollected'], number_format($comparison['collectedChange'], 1) . '%'];
            }

            $row = $this->writeHeaders($sheet, $comparisonData[0], $row);
            $this->getActiveSheet()->setAutoFilter(null);
            foreach (array_slice($comparisonData, 1) as $compRow) {
                $row = $this->writeRow($sheet, $compRow, $row);
            }
        }

        // Monthly Breakdown
        $row += 2;
        $sheet->setCellValue('A' . $row, __('MONTHLY BREAKDOWN'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;

        $monthlyHeaders = [__('Month'), __('Invoiced'), __('Collected'), __('Collection Rate'), __('Invoice Count'), __('Payment Count')];
        $row = $this->writeHeaders($sheet, $monthlyHeaders, $row);
        $monthlyStartRow = $row;

        foreach ($monthlyRevenue as $month) {
            $monthRate = $month['invoiced'] > 0 ? round(($month['collected'] / $month['invoiced']) * 100, 1) : 0;
            $rowData = [
                $month['monthName'],
                $month['invoiced'],
                $month['collected'],
                $monthRate . '%',
                $month['invoiceCount'],
                $month['paymentCount']
            ];
            $row = $this->writeRow($sheet, $rowData, $row);
        }

        // YTD Total row
        $row = $this->writeTotalRow($sheet, [
            __('YTD Total'),
            $ytdSummary['totalInvoiced'],
            $ytdSummary['totalCollected'],
            $collectionRate . '%',
            $ytdSummary['invoiceCount'],
            $ytdSummary['paymentCount']
        ], $row, 1, 6);

        // Format currency columns
        $this->formatColumnAsCurrency($sheet, 'B', $monthlyStartRow, $row - 1);
        $this->formatColumnAsCurrency($sheet, 'C', $monthlyStartRow, $row - 1);

        // Payment Method Breakdown
        $row += 2;
        $sheet->setCellValue('A' . $row, __('PAYMENT METHOD BREAKDOWN'));
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(12);
        $row++;

        $methodHeaders = [__('Method'), __('Total Amount'), __('Payment Count')];
        $row = $this->writeHeaders($sheet, $methodHeaders, $row);
        $this->getActiveSheet()->setAutoFilter(null);
        $methodStartRow = $row;

        foreach ($paymentMethodBreakdown as $method) {
            $rowData = [
                $method['method'],
                (float) $method['totalAmount'],
                $method['paymentCount']
            ];
            $row = $this->writeRow($sheet, $rowData, $row);
        }

        $this->formatColumnAsCurrency($sheet, 'B', $methodStartRow, $row - 1);

        // Auto-size columns
        $this->autoSizeColumns($sheet);

        // Generate file name and save
        $fileName = $this->generateFileName('revenue_report', $gibbonSchoolYearID);
        $filePath = $this->saveSpreadsheet($fileName);

        // Log export
        $this->logExport('REPORT', 'Revenue Report', $gibbonSchoolYearID, $filePath, $exportedByID, count($monthlyRevenue));

        return [
            'filePath' => $filePath,
            'fileName' => $fileName,
            'recordCount' => count($monthlyRevenue),
        ];
    }

    /**
     * Fetch aging data from database.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    protected function fetchAgingData($gibbonSchoolYearID)
    {
        $today = date('Y-m-d');
        $sql = "SELECT
                i.gibbonEnhancedFinanceInvoiceID,
                i.invoiceNumber,
                i.invoiceDate,
                i.dueDate,
                i.totalAmount,
                i.paidAmount,
                (i.totalAmount - i.paidAmount) AS balanceRemaining,
                i.status,
                DATEDIFF(:today, i.dueDate) AS daysOverdue,
                p.surname AS childSurname,
                p.preferredName AS childPreferredName,
                f.gibbonFamilyID,
                f.name AS familyName
            FROM gibbonEnhancedFinanceInvoice i
            LEFT JOIN gibbonPerson p ON i.gibbonPersonID = p.gibbonPersonID
            LEFT JOIN gibbonFamily f ON i.gibbonFamilyID = f.gibbonFamilyID
            WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID
            AND i.status IN ('Issued', 'Partial')
            ORDER BY DATEDIFF(:today2, i.dueDate) DESC, i.dueDate ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'gibbonSchoolYearID' => $gibbonSchoolYearID,
                'today' => $today,
                'today2' => $today
            ]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Fetch collection data from database.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    protected function fetchCollectionData($gibbonSchoolYearID)
    {
        $today = date('Y-m-d');
        $sql = "SELECT
                i.gibbonEnhancedFinanceInvoiceID,
                i.invoiceNumber,
                i.invoiceDate,
                i.dueDate,
                i.totalAmount,
                i.paidAmount,
                (i.totalAmount - i.paidAmount) AS balanceRemaining,
                i.status,
                DATEDIFF(:today, i.dueDate) AS daysOverdue,
                p.surname AS childSurname,
                p.preferredName AS childPreferredName,
                f.gibbonFamilyID,
                f.name AS familyName
            FROM gibbonEnhancedFinanceInvoice i
            LEFT JOIN gibbonPerson p ON i.gibbonPersonID = p.gibbonPersonID
            LEFT JOIN gibbonFamily f ON i.gibbonFamilyID = f.gibbonFamilyID
            WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID
            AND i.status IN ('Issued', 'Partial')
            AND i.dueDate < :today2
            ORDER BY DATEDIFF(:today3, i.dueDate) DESC, f.name ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'gibbonSchoolYearID' => $gibbonSchoolYearID,
                'today' => $today,
                'today2' => $today,
                'today3' => $today
            ]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Calculate aging bucket totals.
     *
     * @param array $agingData
     * @return array
     */
    protected function calculateAgingBuckets($agingData)
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
            } else if ($daysOverdue <= 30) {
                $buckets['days30'] += $balance;
                $buckets['days30Count']++;
            } else if ($daysOverdue <= 60) {
                $buckets['days60'] += $balance;
                $buckets['days60Count']++;
            } else if ($daysOverdue <= 90) {
                $buckets['days90'] += $balance;
                $buckets['days90Count']++;
            } else {
                $buckets['over90'] += $balance;
                $buckets['over90Count']++;
            }
        }

        return $buckets;
    }

    /**
     * Calculate collection summary totals.
     *
     * @param array $collectionData
     * @return array
     */
    protected function calculateCollectionSummary($collectionData)
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
            $stage = $this->getCollectionStage($invoice['daysOverdue']);

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

    /**
     * Calculate payment statistics.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    protected function calculatePaymentStats($gibbonSchoolYearID)
    {
        // Get total invoiced vs paid
        $sql = "SELECT
                SUM(totalAmount) AS totalInvoiced,
                SUM(paidAmount) AS totalPaid
            FROM gibbonEnhancedFinanceInvoice
            WHERE gibbonSchoolYearID = :gibbonSchoolYearID
            AND status NOT IN ('Cancelled', 'Refunded')";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            $totalInvoiced = (float) ($result['totalInvoiced'] ?? 0);
            $totalPaid = (float) ($result['totalPaid'] ?? 0);

            $collectionRate = $totalInvoiced > 0 ? ($totalPaid / $totalInvoiced) * 100 : 0;
        } catch (\PDOException $e) {
            $collectionRate = 0;
        }

        // Get average days to collect
        $sql2 = "SELECT
                AVG(DATEDIFF(p.paymentDate, i.invoiceDate)) AS avgDays
            FROM gibbonEnhancedFinancePayment p
            INNER JOIN gibbonEnhancedFinanceInvoice i ON p.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
            WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID";

        try {
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
            $result2 = $stmt2->fetch(\PDO::FETCH_ASSOC);

            $avgDaysToCollect = (float) ($result2['avgDays'] ?? 0);
        } catch (\PDOException $e) {
            $avgDaysToCollect = 0;
        }

        return [
            'collectionRate' => $collectionRate,
            'avgDaysToCollect' => $avgDaysToCollect,
        ];
    }

    /**
     * Fetch YTD summary.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    protected function fetchYTDSummary($gibbonSchoolYearID)
    {
        $sql = "SELECT
                COALESCE(SUM(totalAmount), 0) AS totalInvoiced,
                COALESCE(SUM(paidAmount), 0) AS totalCollected,
                COUNT(*) AS invoiceCount,
                SUM(CASE WHEN status IN ('Issued', 'Partial') THEN 1 ELSE 0 END) AS outstandingCount
            FROM gibbonEnhancedFinanceInvoice
            WHERE gibbonSchoolYearID = :gibbonSchoolYearID
            AND status NOT IN ('Cancelled', 'Refunded')";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
            $invoiceData = $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $invoiceData = [
                'totalInvoiced' => 0,
                'totalCollected' => 0,
                'invoiceCount' => 0,
                'outstandingCount' => 0,
            ];
        }

        $sql2 = "SELECT COUNT(*) AS paymentCount
            FROM gibbonEnhancedFinancePayment p
            INNER JOIN gibbonEnhancedFinanceInvoice i ON p.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
            WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID";

        try {
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
            $paymentData = $stmt2->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $paymentData = ['paymentCount' => 0];
        }

        return [
            'totalInvoiced' => (float) ($invoiceData['totalInvoiced'] ?? 0),
            'totalCollected' => (float) ($invoiceData['totalCollected'] ?? 0),
            'invoiceCount' => (int) ($invoiceData['invoiceCount'] ?? 0),
            'outstandingCount' => (int) ($invoiceData['outstandingCount'] ?? 0),
            'paymentCount' => (int) ($paymentData['paymentCount'] ?? 0),
        ];
    }

    /**
     * Fetch monthly revenue.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    protected function fetchMonthlyRevenue($gibbonSchoolYearID)
    {
        $sql = "SELECT
                MONTH(p.paymentDate) AS month,
                YEAR(p.paymentDate) AS year,
                COALESCE(SUM(p.amount), 0) AS collected,
                COUNT(p.gibbonEnhancedFinancePaymentID) AS paymentCount
            FROM gibbonEnhancedFinancePayment p
            INNER JOIN gibbonEnhancedFinanceInvoice i ON p.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
            WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID
            GROUP BY YEAR(p.paymentDate), MONTH(p.paymentDate)
            ORDER BY YEAR(p.paymentDate), MONTH(p.paymentDate)";

        $sql2 = "SELECT
                MONTH(invoiceDate) AS month,
                YEAR(invoiceDate) AS year,
                COALESCE(SUM(totalAmount), 0) AS invoiced,
                COUNT(*) AS invoiceCount
            FROM gibbonEnhancedFinanceInvoice
            WHERE gibbonSchoolYearID = :gibbonSchoolYearID
            AND status NOT IN ('Cancelled', 'Refunded')
            GROUP BY YEAR(invoiceDate), MONTH(invoiceDate)
            ORDER BY YEAR(invoiceDate), MONTH(invoiceDate)";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
            $collectedData = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
            $invoicedData = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

            $monthlyData = [];
            $collectedByMonth = [];

            foreach ($collectedData as $row) {
                $key = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
                $collectedByMonth[$key] = $row;
            }

            foreach ($invoicedData as $row) {
                $key = $row['year'] . '-' . str_pad($row['month'], 2, '0', STR_PAD_LEFT);
                $monthlyData[$key] = [
                    'month' => (int) $row['month'],
                    'year' => (int) $row['year'],
                    'monthName' => date('F Y', mktime(0, 0, 0, $row['month'], 1, $row['year'])),
                    'invoiced' => (float) $row['invoiced'],
                    'invoiceCount' => (int) $row['invoiceCount'],
                    'collected' => isset($collectedByMonth[$key]) ? (float) $collectedByMonth[$key]['collected'] : 0,
                    'paymentCount' => isset($collectedByMonth[$key]) ? (int) $collectedByMonth[$key]['paymentCount'] : 0,
                ];
            }

            foreach ($collectedByMonth as $key => $row) {
                if (!isset($monthlyData[$key])) {
                    $monthlyData[$key] = [
                        'month' => (int) $row['month'],
                        'year' => (int) $row['year'],
                        'monthName' => date('F Y', mktime(0, 0, 0, $row['month'], 1, $row['year'])),
                        'invoiced' => 0,
                        'invoiceCount' => 0,
                        'collected' => (float) $row['collected'],
                        'paymentCount' => (int) $row['paymentCount'],
                    ];
                }
            }

            ksort($monthlyData);
            return array_values($monthlyData);

        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Fetch payment method breakdown.
     *
     * @param int $gibbonSchoolYearID
     * @return array
     */
    protected function fetchPaymentMethodBreakdown($gibbonSchoolYearID)
    {
        $sql = "SELECT
                p.method,
                COALESCE(SUM(p.amount), 0) AS totalAmount,
                COUNT(p.gibbonEnhancedFinancePaymentID) AS paymentCount
            FROM gibbonEnhancedFinancePayment p
            INNER JOIN gibbonEnhancedFinanceInvoice i ON p.gibbonEnhancedFinanceInvoiceID = i.gibbonEnhancedFinanceInvoiceID
            WHERE i.gibbonSchoolYearID = :gibbonSchoolYearID
            GROUP BY p.method
            ORDER BY SUM(p.amount) DESC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['gibbonSchoolYearID' => $gibbonSchoolYearID]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Get previous school year.
     *
     * @param int $currentSchoolYearID
     * @return array|null
     */
    protected function getPreviousSchoolYear($currentSchoolYearID)
    {
        $sql = "SELECT y2.gibbonSchoolYearID, y2.name
                FROM gibbonSchoolYear y1
                INNER JOIN gibbonSchoolYear y2 ON y2.sequenceNumber = y1.sequenceNumber - 1
                WHERE y1.gibbonSchoolYearID = :gibbonSchoolYearID";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['gibbonSchoolYearID' => $currentSchoolYearID]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    /**
     * Calculate year-over-year comparison.
     *
     * @param array $currentYtd
     * @param array|null $previousYtd
     * @return array
     */
    protected function calculateComparison($currentYtd, $previousYtd)
    {
        if (!$previousYtd || $previousYtd['totalInvoiced'] == 0) {
            return [
                'invoicedChange' => null,
                'collectedChange' => null,
            ];
        }

        $invoicedChange = (($currentYtd['totalInvoiced'] - $previousYtd['totalInvoiced']) / $previousYtd['totalInvoiced']) * 100;
        $collectedChange = $previousYtd['totalCollected'] > 0
            ? (($currentYtd['totalCollected'] - $previousYtd['totalCollected']) / $previousYtd['totalCollected']) * 100
            : null;

        return [
            'invoicedChange' => $invoicedChange,
            'collectedChange' => $collectedChange,
        ];
    }

    /**
     * Get aging bucket label.
     *
     * @param int $daysOverdue
     * @return string
     */
    protected function getAgingBucketLabel($daysOverdue)
    {
        if ($daysOverdue <= 0) {
            return __('Current');
        } else if ($daysOverdue <= 30) {
            return __('1-30 Days');
        } else if ($daysOverdue <= 60) {
            return __('31-60 Days');
        } else if ($daysOverdue <= 90) {
            return __('61-90 Days');
        } else {
            return __('90+ Days');
        }
    }

    /**
     * Get aging bucket color.
     *
     * @param int $daysOverdue
     * @return string
     */
    protected function getAgingBucketColor($daysOverdue)
    {
        if ($daysOverdue <= 0) {
            return self::COLOR_CURRENT;
        } else if ($daysOverdue <= 30) {
            return self::COLOR_DAYS_30;
        } else if ($daysOverdue <= 60) {
            return self::COLOR_DAYS_60;
        } else if ($daysOverdue <= 90) {
            return self::COLOR_DAYS_90;
        } else {
            return self::COLOR_OVER_90;
        }
    }

    /**
     * Get collection stage.
     *
     * @param int $daysOverdue
     * @return string
     */
    protected function getCollectionStage($daysOverdue)
    {
        $daysOverdue = (int) $daysOverdue;

        if ($daysOverdue <= 0) {
            return 'current';
        } else if ($daysOverdue <= 30) {
            return 'first_notice';
        } else if ($daysOverdue <= 60) {
            return 'second_notice';
        } else if ($daysOverdue <= 90) {
            return 'final_notice';
        } else {
            return 'write_off';
        }
    }

    /**
     * Get stage label.
     *
     * @param string $stage
     * @return string
     */
    protected function getStageLabel($stage)
    {
        $labels = [
            'current' => __('Current'),
            'first_notice' => __('First Notice'),
            'second_notice' => __('Second Notice'),
            'final_notice' => __('Final Notice'),
            'write_off' => __('Write-off Review'),
        ];

        return $labels[$stage] ?? __('Unknown');
    }

    /**
     * Get suggested action text.
     *
     * @param string $stage
     * @return string
     */
    protected function getSuggestedActionText($stage)
    {
        $actions = [
            'current' => __('No action required'),
            'first_notice' => __('Send payment reminder'),
            'second_notice' => __('Phone call / formal notice'),
            'final_notice' => __('Final warning / payment plan'),
            'write_off' => __('Review for write-off'),
        ];

        return $actions[$stage] ?? __('Unknown');
    }

    /**
     * Get collection stage color.
     *
     * @param string $stage
     * @return string
     */
    protected function getCollectionStageColor($stage)
    {
        $colors = [
            'current' => self::COLOR_CURRENT,
            'first_notice' => self::COLOR_DAYS_30,
            'second_notice' => self::COLOR_DAYS_60,
            'final_notice' => self::COLOR_DAYS_90,
            'write_off' => self::COLOR_OVER_90,
        ];

        return $colors[$stage] ?? 'FFFFFF';
    }

    /**
     * Log export to database.
     *
     * @param string $exportType
     * @param string $description
     * @param int $gibbonSchoolYearID
     * @param string $filePath
     * @param int $exportedByID
     * @param int $recordCount
     */
    protected function logExport($exportType, $description, $gibbonSchoolYearID, $filePath, $exportedByID, $recordCount)
    {
        try {
            $checksum = $this->calculateChecksum($filePath);
            $fileSize = $this->getFileSize($filePath);
            $fileName = basename($filePath);

            $this->exportGateway->insertExport([
                'exportType' => $exportType,
                'exportFormat' => 'XLSX',
                'gibbonSchoolYearID' => $gibbonSchoolYearID,
                'dateRangeStart' => null,
                'dateRangeEnd' => null,
                'fileName' => $fileName,
                'filePath' => $filePath,
                'fileSize' => $fileSize,
                'checksum' => $checksum,
                'recordCount' => $recordCount,
                'exportedByID' => $exportedByID,
                'status' => 'Complete',
                'notes' => $description,
            ]);
        } catch (\Exception $e) {
            // Silently fail logging - don't prevent export
        }
    }
}
