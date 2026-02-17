# Invoice Print-Friendly CSS Documentation

## Overview

The `invoice_print.css` file provides comprehensive print-optimized styles for invoice documents in the LAYA EnhancedFinance module. This stylesheet ensures professional appearance when invoices are printed from web browsers.

## Location

```
gibbon/modules/EnhancedFinance/css/invoice_print.css
```

## Features

### 1. **Page Setup**
- Configured for US Letter size (8.5" × 11") in portrait orientation
- Optimized margins: 0.75" (top/bottom), 0.5" (left/right)
- Proper page break controls to prevent content splitting

### 2. **Color Preservation**
- Uses `print-color-adjust: exact` to ensure brand colors print correctly
- Preserves branding color (#4A90A4) in headers and totals
- Maintains background colors for table headers and highlights

### 3. **Page Break Management**
- Prevents page breaks inside critical elements:
  - Invoice headers
  - Customer information boxes
  - Table rows
  - Payment information
  - Notes sections
- Allows page breaks between major sections when needed

### 4. **Element Visibility Control**
- Hides non-essential elements: navigation, buttons, toolbars
- Shows print-only elements with `.print-only` class
- Removes screen-specific effects (shadows, transitions, animations)

### 5. **Typography Optimization**
- Uses point (pt) units for print-appropriate sizing
- Optimized line heights for readability
- Professional font stack: Helvetica Neue, Helvetica, Arial, sans-serif

### 6. **Table Styling**
- Clean, professional table layout
- Preserved header background colors
- Alternate row coloring for readability
- Page-break-safe table rows

### 7. **Layout Optimization**
- Removes shadows and transitions
- Optimizes spacing for print
- Ensures proper alignment
- Responsive to print media

## Usage

### Method 1: Link in HTML Head (Recommended)

Include the stylesheet in your invoice HTML pages:

```html
<link rel="stylesheet" href="../modules/EnhancedFinance/css/invoice_print.css" media="print">
```

The `media="print"` attribute ensures the styles only apply when printing.

### Method 2: Dynamic Path

For pages where the module path is dynamic:

```php
<link rel="stylesheet" href="<?php echo $modulePath; ?>/css/invoice_print.css" media="print">
```

### Method 3: Import in Existing Stylesheets

Import at the top of another CSS file:

```css
@import url('../css/invoice_print.css') print;
```

## CSS Classes Reference

### Element Visibility

| Class | Purpose | Print Behavior |
|-------|---------|----------------|
| `.no-print` | Hide element when printing | `display: none` |
| `.screen-only` | Show only on screen | `display: none` |
| `.print-only` | Show only when printing | `display: block` |

### Page Break Control

| Class | Purpose | Print Behavior |
|-------|---------|----------------|
| `.page-break` | Force page break before | `page-break-before: always` |
| `.page-break-before` | Force page break before | `page-break-before: always` |
| `.page-break-after` | Force page break after | `page-break-after: always` |
| `.no-break` | Prevent page breaks inside | `page-break-inside: avoid` |

### Invoice Structure

| Class | Purpose |
|-------|---------|
| `.invoice-header` | Header with logo and organization info |
| `.invoice-title` | "INVOICE" title section |
| `.invoice-details` | Invoice number, date, due date |
| `.customer-box` | Bill-to customer information |
| `.items-section` | Itemized services table |
| `.totals-section` | Subtotal, taxes, and total |
| `.payment-section` | Payment terms and methods |
| `.notes-section` | Additional notes and instructions |
| `.invoice-footer` | Footer with thank you message |

### Batch Invoices

| Class | Purpose |
|-------|---------|
| `.invoice-page` | Container for single invoice in batch |
| `.invoice-separator` | Separator between invoices |

## Example HTML Structure

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice INV-2024-01-001</title>
    <link rel="stylesheet" href="css/invoice_print.css" media="print">
    <style>
        /* Screen-specific styles here */
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Print button (hidden when printing) -->
        <button class="no-print" onclick="window.print()">Print Invoice</button>

        <!-- Invoice header with branding -->
        <div class="invoice-header">
            <div class="header-left">
                <img src="logo.png" alt="Company Logo" class="logo">
                <div class="organization-name">My Daycare</div>
            </div>
            <div class="header-right">
                <div class="organization-info">
                    123 Main Street<br>
                    City, State 12345<br>
                    Tel: (555) 123-4567
                </div>
            </div>
        </div>

        <!-- Invoice content -->
        <!-- ... -->
    </div>
</body>
</html>
```

## Browser Compatibility

The print CSS has been tested and works with:

- ✅ **Chrome/Edge** (Chromium-based): Full support
- ✅ **Firefox**: Full support
- ✅ **Safari**: Full support (use `-webkit-print-color-adjust`)
- ✅ **PDF Generation**: Compatible with mPDF and similar libraries

## Browser Print Settings

For best results, users should configure their browser print settings:

### Recommended Settings

1. **Paper Size**: Letter (8.5" × 11")
2. **Orientation**: Portrait
3. **Margins**: Default or Custom (0.5" all sides)
4. **Background Graphics**: **Enable** (important for colors)
5. **Headers/Footers**: Disable browser headers/footers

### Chrome Print Dialog

```
More settings →
☑ Background graphics
```

### Firefox Print Dialog

```
Page Setup →
☑ Print Background Colors
☑ Print Background Images
```

### Safari Print Dialog

```
☑ Print backgrounds
```

## Customization

### Changing Brand Color

To change the brand color from #4A90A4 to your own:

1. Open `invoice_print.css`
2. Find and replace all instances of `#4A90A4`
3. Replace with your brand color (e.g., `#3366CC`)

### Adjusting Page Margins

Modify the `@page` rule:

```css
@page {
    size: letter portrait;
    margin: 1in 0.5in; /* Top/Bottom: 1", Left/Right: 0.5" */
}
```

### Changing Font Sizes

Font sizes use point (pt) units. Common adjustments:

```css
body {
    font-size: 10pt; /* Base font size */
}

.invoice-title {
    font-size: 24pt; /* Main title */
}

.section-title {
    font-size: 12pt; /* Section headers */
}
```

## Troubleshooting

### Issue: Background colors not printing

**Solution**: Ensure users enable "Background graphics" in browser print settings.

**Alternative**: Use borders instead of backgrounds:

```css
.total-final-row {
    border: 2pt solid #4A90A4;
    border-left: 8pt solid #4A90A4;
}
```

### Issue: Content cut off at page breaks

**Solution**: Add `page-break-inside: avoid` to the affected element:

```css
.my-element {
    page-break-inside: avoid;
}
```

### Issue: Images not printing

**Solution**: Ensure image paths are absolute or relative to the HTML file, not the CSS file.

### Issue: Fonts look different when printed

**Solution**: This is normal. Print fonts may vary slightly. Use web-safe font stack:

```css
font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
```

## Testing Print Styles

### Method 1: Browser Print Preview

1. Open invoice in browser
2. Press `Ctrl+P` (Windows) or `Cmd+P` (Mac)
3. Review in print preview
4. Adjust CSS as needed

### Method 2: Print to PDF

1. Open invoice in browser
2. Print to PDF (save as PDF)
3. Review PDF output
4. Verify colors, layout, and pagination

### Method 3: Automated Testing

Run the PHPUnit test suite:

```bash
cd gibbon/modules/EnhancedFinance
phpunit tests/InvoicePrintCSSTest.php
```

This verifies:
- CSS file exists and is valid
- All required rules are present
- No syntax errors
- Documentation is complete

## Best Practices

### DO:
✅ Use point (pt) units for print styles
✅ Test on multiple browsers
✅ Enable background graphics for color printing
✅ Use `page-break-inside: avoid` for critical elements
✅ Provide print button with `.no-print` class
✅ Keep CSS organized with section comments
✅ Document any customizations

### DON'T:
❌ Use pixel (px) units for print fonts
❌ Rely on hover effects (not available in print)
❌ Use viewport units (vh, vw) for print
❌ Forget to test print preview
❌ Include unnecessary elements in print
❌ Use small font sizes (< 8pt difficult to read)
❌ Depend on JavaScript for print layout

## Related Files

- **Template**: `gibbon/modules/EnhancedFinance/templates/invoice_template.php`
- **Generator**: `gibbon/modules/EnhancedFinance/src/Domain/InvoicePDFGenerator.php`
- **Tests**: `gibbon/modules/EnhancedFinance/tests/InvoicePrintCSSTest.php`
- **Endpoints**:
  - Single: `gibbon/modules/EnhancedFinance/invoice_pdf.php`
  - Batch: `gibbon/modules/EnhancedFinance/invoice_pdf_batch.php`

## Support

For issues or questions about the print CSS:

1. Check browser print settings (background graphics enabled)
2. Review this documentation
3. Run test suite: `phpunit tests/InvoicePrintCSSTest.php`
4. Check browser console for CSS errors
5. Contact LAYA support team

## Version History

- **v1.0.00** (2024-02-16): Initial release
  - Print-optimized page layout
  - Color preservation
  - Page break management
  - Element visibility controls
  - Comprehensive documentation

## License

This file is part of the LAYA Kindergarten Platform and is licensed under the GNU General Public License v3.0.
