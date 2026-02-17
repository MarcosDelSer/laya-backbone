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
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Excel Exporter Base Class
 *
 * Base class for generating Excel exports using PhpSpreadsheet.
 * Provides common functionality for creating, styling, and saving Excel files.
 *
 * Excel Export Features:
 * - Support for multiple worksheets
 * - Configurable column widths (auto-size or fixed)
 * - Header row styling with background color
 * - Currency and date formatting
 * - Border styles
 * - Summary rows with formulas
 * - Export logging for audit trail
 *
 * Usage:
 * - Extend this class and implement specific export logic
 * - Use createSpreadsheet() to initialize a new workbook
 * - Use helper methods to add data, style cells, and format content
 * - Use saveSpreadsheet() to write the file
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
abstract class ExcelExporter
{
    /**
     * @var Connection
     */
    protected $db;

    /**
     * @var SettingGateway
     */
    protected $settingGateway;

    /**
     * @var ExportGateway
     */
    protected $exportGateway;

    /**
     * @var Spreadsheet|null
     */
    protected $spreadsheet;

    /**
     * Date format options
     */
    public const DATE_FORMAT_YMD = 'Y-m-d';
    public const DATE_FORMAT_MDY = 'm/d/Y';
    public const DATE_FORMAT_DMY = 'd/m/Y';

    /**
     * Excel number formats
     */
    public const NUMBER_FORMAT_CURRENCY = '$#,##0.00';
    public const NUMBER_FORMAT_CURRENCY_CAD = '[$$-1009]#,##0.00';
    public const NUMBER_FORMAT_NUMBER = '#,##0.00';
    public const NUMBER_FORMAT_INTEGER = '#,##0';
    public const NUMBER_FORMAT_DATE = 'YYYY-MM-DD';
    public const NUMBER_FORMAT_DATE_MDY = 'MM/DD/YYYY';
    public const NUMBER_FORMAT_DATE_DMY = 'DD/MM/YYYY';
    public const NUMBER_FORMAT_PERCENT = '0.00%';

    /**
     * Color constants for consistent styling
     */
    public const COLOR_HEADER_BG = 'E2E8F0';      // Light slate gray
    public const COLOR_HEADER_TEXT = '1E293B';    // Dark slate
    public const COLOR_TOTAL_BG = 'F8FAFC';       // Very light gray
    public const COLOR_BORDER = 'CBD5E1';         // Medium slate
    public const COLOR_SUCCESS = '22C55E';        // Green
    public const COLOR_WARNING = 'F59E0B';        // Orange
    public const COLOR_DANGER = 'EF4444';         // Red

    /**
     * Default configuration
     */
    protected $config = [
        'dateFormat' => self::DATE_FORMAT_YMD,
        'currencyFormat' => self::NUMBER_FORMAT_CURRENCY_CAD,
        'autoSizeColumns' => true,
        'freezeHeaderRow' => true,
        'includeFilters' => true,
        'defaultFontName' => 'Calibri',
        'defaultFontSize' => 11,
        'headerFontSize' => 11,
        'headerBold' => true,
    ];

    /**
     * Constructor.
     *
     * @param Connection $db
     * @param SettingGateway $settingGateway
     * @param ExportGateway $exportGateway
     */
    public function __construct(
        Connection $db,
        SettingGateway $settingGateway,
        ExportGateway $exportGateway
    ) {
        $this->db = $db;
        $this->settingGateway = $settingGateway;
        $this->exportGateway = $exportGateway;
    }

    /**
     * Configure export settings.
     *
     * @param array $config Configuration options
     * @return self
     */
    public function configure(array $config): self
    {
        $this->config = array_merge($this->config, $config);
        return $this;
    }

    /**
     * Create a new spreadsheet instance.
     *
     * @param string|null $title Spreadsheet title
     * @param string|null $creator Creator name
     * @return Spreadsheet
     */
    protected function createSpreadsheet($title = null, $creator = null): Spreadsheet
    {
        $this->spreadsheet = new Spreadsheet();

        // Set document properties
        $properties = $this->spreadsheet->getProperties();
        $properties->setCreator($creator ?? 'Gibbon Enhanced Finance');
        $properties->setLastModifiedBy($creator ?? 'Gibbon Enhanced Finance');
        $properties->setTitle($title ?? 'Financial Export');
        $properties->setSubject('Financial Data Export');
        $properties->setDescription('Generated by Gibbon Enhanced Finance Module');
        $properties->setCreated(time());

        // Set default font
        $this->spreadsheet->getDefaultStyle()->getFont()
            ->setName($this->config['defaultFontName'])
            ->setSize($this->config['defaultFontSize']);

        return $this->spreadsheet;
    }

    /**
     * Get the active worksheet.
     *
     * @return Worksheet
     */
    protected function getActiveSheet(): Worksheet
    {
        if ($this->spreadsheet === null) {
            $this->createSpreadsheet();
        }
        return $this->spreadsheet->getActiveSheet();
    }

    /**
     * Create a new worksheet and make it active.
     *
     * @param string $title Worksheet title
     * @return Worksheet
     */
    protected function createSheet($title): Worksheet
    {
        if ($this->spreadsheet === null) {
            $this->createSpreadsheet();
        }

        $sheet = $this->spreadsheet->createSheet();
        $sheet->setTitle($this->sanitizeSheetTitle($title));
        $this->spreadsheet->setActiveSheetIndex($this->spreadsheet->getSheetCount() - 1);

        return $sheet;
    }

    /**
     * Sanitize worksheet title (max 31 chars, no special chars).
     *
     * @param string $title
     * @return string
     */
    protected function sanitizeSheetTitle($title): string
    {
        // Remove invalid characters for Excel sheet names
        $title = preg_replace('/[\\\\\/\*\?\[\]\:]/', '', $title);
        // Truncate to 31 characters (Excel limit)
        return mb_substr($title, 0, 31);
    }

    /**
     * Write header row with styling.
     *
     * @param Worksheet $sheet
     * @param array $headers Array of header labels
     * @param int $row Row number (1-based)
     * @param int $startCol Starting column number (1-based)
     * @return int The row number after headers
     */
    protected function writeHeaders(Worksheet $sheet, array $headers, $row = 1, $startCol = 1): int
    {
        $col = $startCol;
        foreach ($headers as $header) {
            $sheet->setCellValue([$col, $row], $header);
            $col++;
        }

        // Apply header styling
        $endCol = $startCol + count($headers) - 1;
        $headerRange = Coordinate::stringFromColumnIndex($startCol) . $row . ':' .
                       Coordinate::stringFromColumnIndex($endCol) . $row;

        $sheet->getStyle($headerRange)->applyFromArray([
            'font' => [
                'bold' => $this->config['headerBold'],
                'size' => $this->config['headerFontSize'],
                'color' => ['rgb' => self::COLOR_HEADER_TEXT],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLOR_HEADER_BG],
            ],
            'borders' => [
                'bottom' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => self::COLOR_BORDER],
                ],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_LEFT,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Freeze header row if configured
        if ($this->config['freezeHeaderRow']) {
            $sheet->freezePane('A' . ($row + 1));
        }

        // Add auto-filter if configured
        if ($this->config['includeFilters']) {
            $sheet->setAutoFilter($headerRange);
        }

        return $row + 1;
    }

    /**
     * Write a data row.
     *
     * @param Worksheet $sheet
     * @param array $data Array of cell values
     * @param int $row Row number (1-based)
     * @param int $startCol Starting column number (1-based)
     * @return int The row number after writing
     */
    protected function writeRow(Worksheet $sheet, array $data, $row, $startCol = 1): int
    {
        $col = $startCol;
        foreach ($data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }

        return $row + 1;
    }

    /**
     * Write multiple data rows from an array.
     *
     * @param Worksheet $sheet
     * @param array $rows Array of row data arrays
     * @param int $startRow Starting row number (1-based)
     * @param int $startCol Starting column number (1-based)
     * @return int The row number after writing all rows
     */
    protected function writeRows(Worksheet $sheet, array $rows, $startRow, $startCol = 1): int
    {
        $row = $startRow;
        foreach ($rows as $rowData) {
            $row = $this->writeRow($sheet, $rowData, $row, $startCol);
        }
        return $row;
    }

    /**
     * Write a summary/total row with styling.
     *
     * @param Worksheet $sheet
     * @param array $data Array of cell values
     * @param int $row Row number (1-based)
     * @param int $startCol Starting column number (1-based)
     * @param int $totalCols Total number of columns
     * @return int The row number after writing
     */
    protected function writeTotalRow(Worksheet $sheet, array $data, $row, $startCol = 1, $totalCols = null): int
    {
        $col = $startCol;
        foreach ($data as $value) {
            $sheet->setCellValue([$col, $row], $value);
            $col++;
        }

        // Apply total row styling
        $totalCols = $totalCols ?? count($data);
        $endCol = $startCol + $totalCols - 1;
        $totalRange = Coordinate::stringFromColumnIndex($startCol) . $row . ':' .
                      Coordinate::stringFromColumnIndex($endCol) . $row;

        $sheet->getStyle($totalRange)->applyFromArray([
            'font' => [
                'bold' => true,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => self::COLOR_TOTAL_BG],
            ],
            'borders' => [
                'top' => [
                    'borderStyle' => Border::BORDER_MEDIUM,
                    'color' => ['rgb' => self::COLOR_BORDER],
                ],
            ],
        ]);

        return $row + 1;
    }

    /**
     * Set column format for currency values.
     *
     * @param Worksheet $sheet
     * @param string|int $column Column letter or index
     * @param int|null $startRow Starting row (null for entire column)
     * @param int|null $endRow Ending row (null for entire column)
     */
    protected function formatColumnAsCurrency(Worksheet $sheet, $column, $startRow = null, $endRow = null): void
    {
        $columnLetter = is_int($column) ? Coordinate::stringFromColumnIndex($column) : $column;

        if ($startRow !== null && $endRow !== null) {
            $range = $columnLetter . $startRow . ':' . $columnLetter . $endRow;
        } else {
            $range = $columnLetter . ':' . $columnLetter;
        }

        $sheet->getStyle($range)->getNumberFormat()
            ->setFormatCode($this->config['currencyFormat']);
    }

    /**
     * Set column format for date values.
     *
     * @param Worksheet $sheet
     * @param string|int $column Column letter or index
     * @param int|null $startRow Starting row (null for entire column)
     * @param int|null $endRow Ending row (null for entire column)
     */
    protected function formatColumnAsDate(Worksheet $sheet, $column, $startRow = null, $endRow = null): void
    {
        $columnLetter = is_int($column) ? Coordinate::stringFromColumnIndex($column) : $column;

        if ($startRow !== null && $endRow !== null) {
            $range = $columnLetter . $startRow . ':' . $columnLetter . $endRow;
        } else {
            $range = $columnLetter . ':' . $columnLetter;
        }

        $formatCode = match ($this->config['dateFormat']) {
            self::DATE_FORMAT_MDY => self::NUMBER_FORMAT_DATE_MDY,
            self::DATE_FORMAT_DMY => self::NUMBER_FORMAT_DATE_DMY,
            default => self::NUMBER_FORMAT_DATE,
        };

        $sheet->getStyle($range)->getNumberFormat()
            ->setFormatCode($formatCode);
    }

    /**
     * Set column format for numbers.
     *
     * @param Worksheet $sheet
     * @param string|int $column Column letter or index
     * @param string $format Number format code
     * @param int|null $startRow Starting row (null for entire column)
     * @param int|null $endRow Ending row (null for entire column)
     */
    protected function formatColumnAsNumber(Worksheet $sheet, $column, $format = self::NUMBER_FORMAT_NUMBER, $startRow = null, $endRow = null): void
    {
        $columnLetter = is_int($column) ? Coordinate::stringFromColumnIndex($column) : $column;

        if ($startRow !== null && $endRow !== null) {
            $range = $columnLetter . $startRow . ':' . $columnLetter . $endRow;
        } else {
            $range = $columnLetter . ':' . $columnLetter;
        }

        $sheet->getStyle($range)->getNumberFormat()
            ->setFormatCode($format);
    }

    /**
     * Set column width.
     *
     * @param Worksheet $sheet
     * @param string|int $column Column letter or index
     * @param float|string $width Width value or 'auto' for auto-size
     */
    protected function setColumnWidth(Worksheet $sheet, $column, $width): void
    {
        $columnLetter = is_int($column) ? Coordinate::stringFromColumnIndex($column) : $column;
        $columnDimension = $sheet->getColumnDimension($columnLetter);

        if ($width === 'auto') {
            $columnDimension->setAutoSize(true);
        } else {
            $columnDimension->setWidth((float) $width);
        }
    }

    /**
     * Auto-size all columns in a worksheet.
     *
     * @param Worksheet $sheet
     * @param int $startCol Starting column (1-based)
     * @param int|null $endCol Ending column (null for highest column)
     */
    protected function autoSizeColumns(Worksheet $sheet, $startCol = 1, $endCol = null): void
    {
        $endCol = $endCol ?? Coordinate::columnIndexFromString($sheet->getHighestColumn());

        for ($col = $startCol; $col <= $endCol; $col++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setAutoSize(true);
        }
    }

    /**
     * Add borders to a range.
     *
     * @param Worksheet $sheet
     * @param string $range Cell range (e.g., 'A1:G10')
     * @param string $borderStyle Border style constant
     */
    protected function addBorders(Worksheet $sheet, $range, $borderStyle = Border::BORDER_THIN): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => $borderStyle,
                    'color' => ['rgb' => self::COLOR_BORDER],
                ],
            ],
        ]);
    }

    /**
     * Apply conditional formatting for currency values (positive/negative).
     *
     * @param Worksheet $sheet
     * @param string $range Cell range
     */
    protected function applyConditionalCurrencyFormatting(Worksheet $sheet, $range): void
    {
        // Positive values - green
        $conditionPositive = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $conditionPositive->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS)
            ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_GREATERTHAN)
            ->addCondition('0')
            ->getStyle()->getFont()->getColor()->setRGB(self::COLOR_SUCCESS);

        // Negative values - red
        $conditionNegative = new \PhpOffice\PhpSpreadsheet\Style\Conditional();
        $conditionNegative->setConditionType(\PhpOffice\PhpSpreadsheet\Style\Conditional::CONDITION_CELLIS)
            ->setOperatorType(\PhpOffice\PhpSpreadsheet\Style\Conditional::OPERATOR_LESSTHAN)
            ->addCondition('0')
            ->getStyle()->getFont()->getColor()->setRGB(self::COLOR_DANGER);

        $conditionalStyles = $sheet->getStyle($range)->getConditionalStyles();
        $conditionalStyles[] = $conditionPositive;
        $conditionalStyles[] = $conditionNegative;
        $sheet->getStyle($range)->setConditionalStyles($conditionalStyles);
    }

    /**
     * Save spreadsheet to file.
     *
     * @param string $fileName File name (without path)
     * @return string Full file path
     * @throws \Exception If save fails
     */
    protected function saveSpreadsheet($fileName): string
    {
        if ($this->spreadsheet === null) {
            throw new \Exception('No spreadsheet to save. Call createSpreadsheet() first.');
        }

        // Ensure .xlsx extension
        if (!str_ends_with(strtolower($fileName), '.xlsx')) {
            $fileName .= '.xlsx';
        }

        // Get absolute path from Gibbon configuration
        $absolutePath = $this->getExportDirectory();

        // Create export directory if it doesn't exist
        if (!is_dir($absolutePath)) {
            if (!mkdir($absolutePath, 0755, true)) {
                throw new \Exception('Failed to create export directory: ' . $absolutePath);
            }
        }

        $filePath = $absolutePath . '/' . $fileName;

        // Write Excel file
        $writer = new Xlsx($this->spreadsheet);
        $writer->save($filePath);

        // Clear memory
        $this->spreadsheet->disconnectWorksheets();
        unset($this->spreadsheet);
        $this->spreadsheet = null;

        return $filePath;
    }

    /**
     * Get the export directory path.
     *
     * @return string
     */
    protected function getExportDirectory(): string
    {
        // Use Gibbon's uploads directory structure
        global $session;

        $absolutePath = $session->get('absolutePath') ?? '';
        $uploadsPath = $absolutePath . '/uploads';

        // Create module-specific export directory
        $exportPath = $uploadsPath . '/EnhancedFinance/exports/' . date('Y/m');

        return $exportPath;
    }

    /**
     * Get file size of spreadsheet (approximate, for logging).
     *
     * @param string $filePath
     * @return int
     */
    protected function getFileSize($filePath): int
    {
        return file_exists($filePath) ? filesize($filePath) : 0;
    }

    /**
     * Calculate checksum of file for audit purposes.
     *
     * @param string $filePath
     * @return string
     */
    protected function calculateChecksum($filePath): string
    {
        return file_exists($filePath) ? hash_file('sha256', $filePath) : '';
    }

    /**
     * Generate export file name.
     *
     * @param string $type Export type identifier
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return string
     */
    protected function generateFileName($type, $gibbonSchoolYearID, $dateFrom = null, $dateTo = null): string
    {
        $parts = [
            $type,
            'SY' . $gibbonSchoolYearID,
        ];

        if ($dateFrom) {
            $parts[] = 'from' . str_replace('-', '', $dateFrom);
        }

        if ($dateTo) {
            $parts[] = 'to' . str_replace('-', '', $dateTo);
        }

        $parts[] = date('Ymd_His');

        return implode('_', $parts) . '.xlsx';
    }

    /**
     * Create export log entry.
     *
     * @param string $exportType
     * @param int $gibbonSchoolYearID
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param int $exportedByID
     * @param string $subType
     * @return int Export log ID
     */
    protected function createExportLog($exportType, $gibbonSchoolYearID, $dateFrom, $dateTo, $exportedByID, $subType): int
    {
        $fileName = $this->generateFileName($exportType . '_' . $subType, $gibbonSchoolYearID, $dateFrom, $dateTo);

        return $this->exportGateway->insertExport([
            'exportType' => $exportType,
            'exportFormat' => 'XLSX',
            'gibbonSchoolYearID' => $gibbonSchoolYearID,
            'dateRangeStart' => $dateFrom,
            'dateRangeEnd' => $dateTo,
            'fileName' => $fileName,
            'filePath' => '',
            'exportedByID' => $exportedByID,
            'status' => 'Pending',
        ]);
    }

    /**
     * Format date for display.
     *
     * @param string $date Date in Y-m-d format
     * @return string
     */
    protected function formatDate($date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            $dateObj = new \DateTime($date);
            return $dateObj->format($this->config['dateFormat']);
        } catch (\Exception $e) {
            return $date;
        }
    }

    /**
     * Format amount for display.
     *
     * @param mixed $amount
     * @return float
     */
    protected function formatAmount($amount): float
    {
        if ($amount === null || $amount === '') {
            return 0.00;
        }

        return round((float) $amount, 2);
    }

    /**
     * Format name (Surname, FirstName).
     *
     * @param string $surname
     * @param string $firstName
     * @return string
     */
    protected function formatName($surname, $firstName): string
    {
        $surname = trim($surname);
        $firstName = trim($firstName);

        if (empty($surname) && empty($firstName)) {
            return '';
        }

        if (empty($surname)) {
            return $firstName;
        }

        if (empty($firstName)) {
            return $surname;
        }

        return $surname . ', ' . $firstName;
    }

    /**
     * Sanitize field for export.
     *
     * @param string|null $field
     * @return string
     */
    protected function sanitizeField($field): string
    {
        if ($field === null) {
            return '';
        }

        // Remove control characters except tab, newline, carriage return
        $field = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $field);

        // Trim whitespace
        return trim($field);
    }

    /**
     * Calculate total amount from records.
     *
     * @param array $records
     * @param string $field
     * @return float
     */
    protected function calculateTotalAmount(array $records, $field): float
    {
        $total = 0.0;
        foreach ($records as $record) {
            $total += (float) ($record[$field] ?? 0);
        }
        return $total;
    }

    /**
     * Format customer ID from family ID.
     *
     * @param int $gibbonFamilyID
     * @return string
     */
    protected function formatCustomerID($gibbonFamilyID): string
    {
        return 'FAM-' . str_pad((string) $gibbonFamilyID, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Write a report header section.
     *
     * @param Worksheet $sheet
     * @param string $title Report title
     * @param array $metadata Additional metadata (key-value pairs)
     * @param int $startRow Starting row
     * @return int The row number after the header section
     */
    protected function writeReportHeader(Worksheet $sheet, $title, array $metadata = [], $startRow = 1): int
    {
        $row = $startRow;

        // Title
        $sheet->setCellValue([1, $row], $title);
        $sheet->getStyle('A' . $row)->getFont()->setBold(true)->setSize(14);
        $row++;

        // Metadata
        foreach ($metadata as $label => $value) {
            $sheet->setCellValue([1, $row], $label . ':');
            $sheet->setCellValue([2, $row], $value);
            $row++;
        }

        // Empty row before data
        $row++;

        return $row;
    }

    /**
     * Add a SUM formula to a cell.
     *
     * @param Worksheet $sheet
     * @param string|int $col Column letter or index
     * @param int $row Row number
     * @param int $startRow Start row for sum
     * @param int $endRow End row for sum
     */
    protected function addSumFormula(Worksheet $sheet, $col, $row, $startRow, $endRow): void
    {
        $columnLetter = is_int($col) ? Coordinate::stringFromColumnIndex($col) : $col;
        $formula = "=SUM({$columnLetter}{$startRow}:{$columnLetter}{$endRow})";
        $sheet->setCellValue([$columnLetter, $row], $formula);
    }

    /**
     * Add a COUNT formula to a cell.
     *
     * @param Worksheet $sheet
     * @param string|int $col Column letter or index
     * @param int $row Row number
     * @param int $startRow Start row for count
     * @param int $endRow End row for count
     */
    protected function addCountFormula(Worksheet $sheet, $col, $row, $startRow, $endRow): void
    {
        $columnLetter = is_int($col) ? Coordinate::stringFromColumnIndex($col) : $col;
        $formula = "=COUNTA({$columnLetter}{$startRow}:{$columnLetter}{$endRow})";
        $sheet->setCellValue([$columnLetter, $row], $formula);
    }
}
