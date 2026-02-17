# Responsive Text Scaling (rem/em)

## WCAG 2.1 AA Compliance

This document describes the implementation of responsive text scaling to meet **WCAG 2.1 Level AA Success Criterion 1.4.4: Resize Text**.

### Success Criterion 1.4.4: Resize Text

> Except for captions and images of text, text can be resized without assistive technology up to 200 percent without loss of content or functionality.

**Conformance Level**: AA

**What This Means**: Users must be able to increase text size up to 200% using browser zoom or font size settings without losing content or functionality.

---

## Implementation Overview

The LAYA Parent Portal implements responsive text scaling through:

1. **Root font-size set to 100%** - Respects user browser settings
2. **Relative units (rem/em)** - All text sizing uses relative units, not pixels
3. **Tailwind CSS default configuration** - Uses rem for all sizing by default
4. **Flexible layouts** - Containers adapt to scaled text without overflow

---

## Technical Implementation

### 1. Root Font Size Configuration

In `app/globals.css`:

```css
html {
  font-size: 100%; /* 16px default, respects user browser settings */
  scroll-behavior: smooth;
}
```

**Why 100%?**
- Respects user's browser font size preferences
- Provides a consistent baseline (typically 16px)
- Allows proportional scaling when users adjust settings

### 2. Tailwind CSS rem-based Sizing

Tailwind CSS uses **rem units** for all font sizes by default:

| Tailwind Class | Computed Size (at 100%) | Rem Value |
|----------------|-------------------------|-----------|
| `text-xs`      | 12px                   | 0.75rem   |
| `text-sm`      | 14px                   | 0.875rem  |
| `text-base`    | 16px                   | 1rem      |
| `text-lg`      | 18px                   | 1.125rem  |
| `text-xl`      | 20px                   | 1.25rem   |
| `text-2xl`     | 24px                   | 1.5rem    |
| `text-3xl`     | 30px                   | 1.875rem  |
| `text-4xl`     | 36px                   | 2.25rem   |

**At 200% zoom**, all these values double proportionally:
- `text-base` becomes 32px (2rem)
- `text-lg` becomes 36px (2.25rem)
- etc.

### 3. When to Use rem vs em

#### Use **rem** (root em) for:
- Font sizes
- Spacing that should scale with global text size
- Component padding/margin when consistency is needed

```tsx
// Good: Uses Tailwind's rem-based classes
<p className="text-base mb-4">Content</p>
```

#### Use **em** (relative to parent) for:
- Spacing within a component that should scale with that component's font size
- Button padding relative to button text size
- Icon sizing relative to surrounding text

```tsx
// Good: Padding scales with button text size
<button className="text-sm px-[1em] py-[0.5em]">
  Click me
</button>
```

#### **Avoid px** for text and related spacing:
```tsx
// Bad: Won't scale with user preferences
<p style={{ fontSize: '16px', marginBottom: '16px' }}>Content</p>

// Good: Scales proportionally
<p className="text-base mb-4">Content</p>
```

---

## Testing Text Scaling

### Browser-Based Testing

#### 1. Browser Zoom (Ctrl/Cmd + Plus)
Most browsers zoom both text and layout together:
- Press `Ctrl +` (Windows/Linux) or `Cmd +` (Mac) to zoom in
- Test up to 200% zoom (2x)
- Verify no content is cut off or hidden
- Check that all functionality remains accessible

#### 2. Text-Only Zoom (Firefox)
Firefox offers text-only zoom:
1. Open Firefox Settings
2. Set "Zoom text only" option
3. Use `Ctrl +` to increase text size to 200%
4. Verify layout accommodates larger text

#### 3. Browser Font Size Settings

**Chrome/Edge:**
1. Settings → Appearance → Font size
2. Set to "Very Large" (20px = 125%)
3. Navigate the portal and verify readability

**Firefox:**
1. Settings → Language and Appearance
2. Adjust "Default font" size to 20px or 24px
3. Test all pages

**Safari:**
1. Safari → Settings → Advanced
2. Never use font sizes smaller than: 16
3. Test scaling behavior

### Manual Testing Checklist

- [ ] All text visible at 100% zoom
- [ ] All text visible and readable at 200% zoom
- [ ] No horizontal scrolling at 200% zoom (on desktop)
- [ ] No text cut off or overlapping at 200% zoom
- [ ] All interactive elements remain clickable at 200% zoom
- [ ] Form inputs expand appropriately with text
- [ ] Modal dialogs remain usable at 200% zoom
- [ ] Navigation menus work at 200% zoom
- [ ] No loss of functionality at any zoom level

---

## Common Patterns and Examples

### Component Text Sizing

```tsx
// Section Headings
<h1 className="text-3xl font-bold mb-6">Page Title</h1>
<h2 className="text-2xl font-semibold mb-4">Section Heading</h2>
<h3 className="text-xl font-medium mb-3">Subsection</h3>

// Body Text
<p className="text-base leading-relaxed mb-4">
  Regular paragraph content with comfortable line height.
</p>

// Small Text (captions, metadata)
<span className="text-sm text-gray-600">
  Last updated: Jan 15, 2026
</span>

// Extra Small (labels, badges)
<span className="text-xs uppercase tracking-wide">
  Status: Active
</span>
```

### Responsive Typography

```tsx
// Scale text size on larger screens
<h1 className="text-2xl md:text-3xl lg:text-4xl font-bold">
  Responsive Heading
</h1>

// Adjust line height for readability
<p className="text-base leading-normal md:leading-relaxed lg:leading-loose">
  Content with responsive line height.
</p>
```

### Button Sizing with em

```tsx
// Button padding scales with its text size
<button className="text-sm px-[1.5em] py-[0.75em] rounded-md">
  Small Button
</button>

<button className="text-base px-[1.5em] py-[0.75em] rounded-md">
  Regular Button
</button>

<button className="text-lg px-[1.5em] py-[0.75em] rounded-md">
  Large Button
</button>
```

---

## Avoiding Common Pitfalls

### ❌ Don't Use Pixel Units for Text

```tsx
// Bad: Won't scale with user preferences
<div style={{ fontSize: '14px' }}>Text</div>
<p className="text-[14px]">Text</p> // Arbitrary Tailwind value
```

### ✅ Use rem-based Tailwind Classes

```tsx
// Good: Uses Tailwind's built-in rem values
<div className="text-sm">Text</div>
<p className="text-base">Text</p>
```

### ❌ Don't Set Fixed Heights on Text Containers

```tsx
// Bad: Text may overflow at 200% zoom
<div style={{ height: '40px' }}>
  <p>Text content</p>
</div>
```

### ✅ Use min-height or auto height

```tsx
// Good: Container expands with content
<div className="min-h-[2.5rem]">
  <p>Text content</p>
</div>
```

### ❌ Don't Disable User Zoom on Mobile

```tsx
// Bad: Prevents accessibility
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
```

### ✅ Allow User Zoom

```tsx
// Good: Allows pinch-to-zoom
<meta name="viewport" content="width=device-width, initial-scale=1" />
```

---

## Verification and Maintenance

### Automated Checks

Our test suite includes automated checks for:
- No hardcoded pixel font sizes in components
- Proper use of Tailwind classes
- Container flexibility at various zoom levels

Run tests with:
```bash
npm test -- responsive-text-scaling.test.tsx
```

### Code Review Checklist

When reviewing code, verify:
- [ ] No `fontSize: 'Npx'` in inline styles
- [ ] No `text-[Npx]` arbitrary Tailwind values for font size
- [ ] Text containers use flexible heights (not fixed px heights)
- [ ] Breakpoint adjustments use Tailwind's responsive classes

### Design Handoff

When implementing designs:
1. Convert all px font sizes to nearest Tailwind rem class
2. Test designs at 100%, 150%, and 200% zoom
3. Adjust spacing if needed for larger text
4. Document any intentional fixed-size elements (logos, icons)

---

## Browser Compatibility

### Desktop Browsers

| Browser | Text Zoom | Page Zoom | Status |
|---------|-----------|-----------|--------|
| Chrome 90+ | Via Settings | Ctrl/Cmd + | ✅ Supported |
| Firefox 88+ | Text Only Option | Ctrl/Cmd + | ✅ Supported |
| Safari 14+ | Via Settings | Cmd + | ✅ Supported |
| Edge 90+ | Via Settings | Ctrl + | ✅ Supported |

### Mobile Browsers

| Browser | Pinch Zoom | Accessibility Zoom | Status |
|---------|------------|-------------------|--------|
| Safari iOS | ✅ Yes | Via Settings | ✅ Supported |
| Chrome Android | ✅ Yes | Via Settings | ✅ Supported |
| Firefox Android | ✅ Yes | Via Settings | ✅ Supported |

### Assistive Technology

| Technology | Scaling Method | Status |
|------------|---------------|--------|
| Screen Magnifiers | System-level zoom | ✅ Compatible |
| Browser Extensions | Custom zoom levels | ✅ Compatible |
| OS Accessibility | Display scaling | ✅ Compatible |

---

## Real-World User Scenarios

### Scenario 1: User with Low Vision
**Need**: Increase text size to read comfortably
**Solution**: User sets browser font size to 20px (125% of default)
**Result**: All text scales proportionally, layout remains functional

### Scenario 2: Older Adult
**Need**: Larger text for easier reading
**Solution**: User zooms browser to 150% or 200%
**Result**: Text and UI scale together, no horizontal scrolling on desktop

### Scenario 3: Mobile User in Bright Sunlight
**Need**: Temporarily increase text size to read
**Solution**: Pinch-to-zoom on mobile device
**Result**: Content zooms smoothly, viewport allows zooming

### Scenario 4: User with Cognitive Disability
**Need**: Consistent, predictable text sizing
**Solution**: System-wide font preferences applied
**Result**: Portal respects user's preferred font size

---

## References

### WCAG 2.1 Guidelines
- [Success Criterion 1.4.4: Resize Text (Level AA)](https://www.w3.org/WAI/WCAG21/Understanding/resize-text.html)
- [Understanding Resize Text](https://www.w3.org/WAI/WCAG21/Understanding/resize-text)

### CSS Units
- [CSS rem unit (MDN)](https://developer.mozilla.org/en-US/docs/Learn/CSS/Building_blocks/Values_and_units#relative_length_units)
- [Using em vs rem (CSS Tricks)](https://css-tricks.com/rem-global-em-local/)

### Tailwind CSS
- [Font Size Documentation](https://tailwindcss.com/docs/font-size)
- [Responsive Design](https://tailwindcss.com/docs/responsive-design)

---

## Support and Questions

For questions about responsive text scaling:
1. Review this documentation
2. Check existing component implementations
3. Run automated tests to verify compliance
4. Consult WCAG 2.1 guidelines for edge cases

**Last Updated**: February 17, 2026
**WCAG Version**: 2.1 Level AA
**Maintained By**: LAYA Development Team
