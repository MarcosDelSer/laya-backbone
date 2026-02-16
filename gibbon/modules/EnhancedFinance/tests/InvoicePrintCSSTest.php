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
 * Invoice Print CSS Tests
 *
 * Tests for the print-friendly CSS stylesheet used in invoice generation.
 * Verifies file existence, structure, and key CSS rules.
 *
 * @version v1.0.00
 * @since   v1.0.00
 */
class InvoicePrintCSSTest extends TestCase
{
    private $cssFilePath;
    private $cssContent;

    protected function setUp(): void
    {
        parent::setUp();

        // Path to the CSS file
        $this->cssFilePath = __DIR__ . '/../css/invoice_print.css';

        // Load CSS content if file exists
        if (file_exists($this->cssFilePath)) {
            $this->cssContent = file_get_contents($this->cssFilePath);
        }
    }

    /**
     * Test that the CSS file exists
     */
    public function testCSSFileExists()
    {
        $this->assertFileExists(
            $this->cssFilePath,
            'invoice_print.css file should exist in the css directory'
        );
    }

    /**
     * Test that the CSS file is not empty
     */
    public function testCSSFileNotEmpty()
    {
        $this->assertNotEmpty(
            $this->cssContent,
            'CSS file should not be empty'
        );

        $this->assertGreaterThan(
            1000,
            strlen($this->cssContent),
            'CSS file should contain substantial content (>1000 characters)'
        );
    }

    /**
     * Test that the CSS file contains @media print directive
     */
    public function testContainsMediaPrintDirective()
    {
        $this->assertStringContainsString(
            '@media print',
            $this->cssContent,
            'CSS should contain @media print directive'
        );
    }

    /**
     * Test that @page rule is defined for page setup
     */
    public function testContainsPageRule()
    {
        $this->assertStringContainsString(
            '@page',
            $this->cssContent,
            'CSS should contain @page rule for page setup'
        );
    }

    /**
     * Test that color adjustment properties are set
     */
    public function testContainsColorAdjustment()
    {
        $hasColorAdjust = (
            strpos($this->cssContent, 'print-color-adjust') !== false ||
            strpos($this->cssContent, '-webkit-print-color-adjust') !== false ||
            strpos($this->cssContent, 'color-adjust') !== false
        );

        $this->assertTrue(
            $hasColorAdjust,
            'CSS should contain color adjustment properties for print'
        );
    }

    /**
     * Test that page break controls are defined
     */
    public function testContainsPageBreakControls()
    {
        $hasPageBreak = (
            strpos($this->cssContent, 'page-break-before') !== false ||
            strpos($this->cssContent, 'page-break-after') !== false ||
            strpos($this->cssContent, 'page-break-inside') !== false ||
            strpos($this->cssContent, 'break-before') !== false ||
            strpos($this->cssContent, 'break-after') !== false ||
            strpos($this->cssContent, 'break-inside') !== false
        );

        $this->assertTrue(
            $hasPageBreak,
            'CSS should contain page break control properties'
        );
    }

    /**
     * Test that no-print class is defined
     */
    public function testContainsNoPrintClass()
    {
        $this->assertStringContainsString(
            '.no-print',
            $this->cssContent,
            'CSS should define .no-print class to hide elements'
        );

        $this->assertStringContainsString(
            'display: none',
            $this->cssContent,
            'CSS should hide no-print elements with display: none'
        );
    }

    /**
     * Test that invoice header styles are defined
     */
    public function testContainsInvoiceHeaderStyles()
    {
        $this->assertStringContainsString(
            '.invoice-header',
            $this->cssContent,
            'CSS should define .invoice-header styles'
        );
    }

    /**
     * Test that table styles are defined
     */
    public function testContainsTableStyles()
    {
        $this->assertStringContainsString(
            'table.items-table',
            $this->cssContent,
            'CSS should define table.items-table styles'
        );

        $this->assertStringContainsString(
            'table.items-table thead',
            $this->cssContent,
            'CSS should define table header styles'
        );
    }

    /**
     * Test that totals section styles are defined
     */
    public function testContainsTotalsStyles()
    {
        $this->assertStringContainsString(
            '.totals-section',
            $this->cssContent,
            'CSS should define .totals-section styles'
        );

        $this->assertStringContainsString(
            '.total-final-row',
            $this->cssContent,
            'CSS should define .total-final-row styles'
        );
    }

    /**
     * Test that customer box styles are defined
     */
    public function testContainsCustomerBoxStyles()
    {
        $this->assertStringContainsString(
            '.customer-box',
            $this->cssContent,
            'CSS should define .customer-box styles'
        );
    }

    /**
     * Test that payment section styles are defined
     */
    public function testContainsPaymentStyles()
    {
        $this->assertStringContainsString(
            '.payment-section',
            $this->cssContent,
            'CSS should define .payment-section styles'
        );
    }

    /**
     * Test that notes section styles are defined
     */
    public function testContainsNotesStyles()
    {
        $this->assertStringContainsString(
            '.notes-section',
            $this->cssContent,
            'CSS should define .notes-section styles'
        );
    }

    /**
     * Test that footer styles are defined
     */
    public function testContainsFooterStyles()
    {
        $this->assertStringContainsString(
            '.invoice-footer',
            $this->cssContent,
            'CSS should define .invoice-footer styles'
        );
    }

    /**
     * Test that branding color is used (#4A90A4)
     */
    public function testContainsBrandingColor()
    {
        $this->assertStringContainsString(
            '#4A90A4',
            $this->cssContent,
            'CSS should use branding color #4A90A4'
        );
    }

    /**
     * Test that background colors use !important for print
     */
    public function testBackgroundColorsUseImportant()
    {
        // Check for at least one background-color with !important
        $pattern = '/background-color:[^;]+!important/';
        $this->assertMatchesRegularExpression(
            $pattern,
            $this->cssContent,
            'CSS should use !important for critical background colors'
        );
    }

    /**
     * Test that CSS has proper documentation header
     */
    public function testContainsDocumentationHeader()
    {
        $this->assertStringContainsString(
            'Print-Friendly CSS',
            $this->cssContent,
            'CSS should have a documentation header'
        );

        $this->assertStringContainsString(
            'LAYA Kindergarten Platform',
            $this->cssContent,
            'CSS should reference LAYA platform'
        );
    }

    /**
     * Test that CSS is properly structured with sections
     */
    public function testCSSHasSectionComments()
    {
        $this->assertStringContainsString(
            '===',
            $this->cssContent,
            'CSS should have section divider comments'
        );
    }

    /**
     * Test that print-only class is defined
     */
    public function testContainsPrintOnlyClass()
    {
        $this->assertStringContainsString(
            '.print-only',
            $this->cssContent,
            'CSS should define .print-only class for print-visible elements'
        );
    }

    /**
     * Test that page size is specified
     */
    public function testContainsPageSize()
    {
        $hasSize = (
            strpos($this->cssContent, 'size:') !== false ||
            strpos($this->cssContent, 'letter') !== false ||
            strpos($this->cssContent, 'portrait') !== false
        );

        $this->assertTrue(
            $hasSize,
            'CSS should specify page size in @page rule'
        );
    }

    /**
     * Test that margins are specified
     */
    public function testContainsPageMargins()
    {
        $this->assertMatchesRegularExpression(
            '/margin:[^;]+;/',
            $this->cssContent,
            'CSS should specify page margins'
        );
    }

    /**
     * Test that font sizes use print-appropriate units (pt)
     */
    public function testUsesPrintFontUnits()
    {
        $this->assertStringContainsString(
            'pt',
            $this->cssContent,
            'CSS should use pt units for font sizes (print-appropriate)'
        );
    }

    /**
     * Test that image optimization rules exist
     */
    public function testContainsImageOptimization()
    {
        $this->assertStringContainsString(
            'img',
            $this->cssContent,
            'CSS should contain image styling rules'
        );
    }

    /**
     * Test that utility classes are defined
     */
    public function testContainsUtilityClasses()
    {
        $utilityClasses = ['.text-bold', '.text-right', '.text-center'];

        foreach ($utilityClasses as $class) {
            $this->assertStringContainsString(
                $class,
                $this->cssContent,
                "CSS should define utility class {$class}"
            );
        }
    }

    /**
     * Test that CSS is valid (basic syntax check)
     */
    public function testCSSBasicSyntaxValid()
    {
        // Count opening and closing braces
        $openBraces = substr_count($this->cssContent, '{');
        $closeBraces = substr_count($this->cssContent, '}');

        $this->assertEquals(
            $openBraces,
            $closeBraces,
            'CSS should have matching opening and closing braces'
        );
    }

    /**
     * Test that batch invoice styles are defined
     */
    public function testContainsBatchInvoiceStyles()
    {
        $hasBatchStyles = (
            strpos($this->cssContent, '.invoice-page') !== false ||
            strpos($this->cssContent, '.invoice-separator') !== false
        );

        $this->assertTrue(
            $hasBatchStyles,
            'CSS should contain styles for batch invoice generation'
        );
    }

    /**
     * Test CSS file permissions (should be readable)
     */
    public function testCSSFileIsReadable()
    {
        $this->assertTrue(
            is_readable($this->cssFilePath),
            'CSS file should be readable'
        );
    }

    /**
     * Test that CSS doesn't contain debugging code
     */
    public function testCSSNoDebuggingCode()
    {
        $debugPatterns = [
            'console.log',
            'alert(',
            'debugger',
            'TODO:',
            'FIXME:',
            'XXX:'
        ];

        foreach ($debugPatterns as $pattern) {
            $this->assertStringNotContainsString(
                $pattern,
                $this->cssContent,
                "CSS should not contain debugging code: {$pattern}"
            );
        }
    }

    /**
     * Test that critical elements have page-break-inside: avoid
     */
    public function testCriticalElementsAvoidPageBreak()
    {
        $this->assertStringContainsString(
            'page-break-inside: avoid',
            $this->cssContent,
            'CSS should prevent page breaks inside critical elements'
        );
    }

    /**
     * Test that shadow and transition effects are removed for print
     */
    public function testRemovesScreenEffectsForPrint()
    {
        $effects = [
            'box-shadow: none',
            'text-shadow: none',
            'transition: none',
            'animation: none'
        ];

        $hasEffectRemoval = false;
        foreach ($effects as $effect) {
            if (strpos($this->cssContent, $effect) !== false) {
                $hasEffectRemoval = true;
                break;
            }
        }

        $this->assertTrue(
            $hasEffectRemoval,
            'CSS should remove screen effects (shadows, transitions) for print'
        );
    }

    /**
     * Test that accessibility features are preserved
     */
    public function testPreservesAccessibilityFeatures()
    {
        $this->assertStringContainsString(
            'line-height',
            $this->cssContent,
            'CSS should specify line-height for readability'
        );
    }

    /**
     * Test file size is reasonable (not too large)
     */
    public function testFileSizeReasonable()
    {
        $fileSize = strlen($this->cssContent);

        $this->assertLessThan(
            100000,
            $fileSize,
            'CSS file should not be excessively large (< 100KB)'
        );
    }

    /**
     * Test that CSS contains usage documentation
     */
    public function testContainsUsageDocumentation()
    {
        $this->assertStringContainsString(
            'Usage:',
            $this->cssContent,
            'CSS should contain usage documentation'
        );
    }
}
