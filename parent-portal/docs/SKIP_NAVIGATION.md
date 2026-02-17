# Skip Navigation Implementation

## Overview

The Skip Navigation feature is a critical accessibility enhancement that allows keyboard and screen reader users to bypass repetitive navigation links and jump directly to the main content of the page. This implementation is fully compliant with WCAG 2.1 AA standards.

## WCAG Compliance

**WCAG 2.1 Level AA Success Criteria:**
- **2.4.1 Bypass Blocks (Level A)**: Provides a mechanism to bypass blocks of content that are repeated on multiple pages
- **2.1.1 Keyboard (Level A)**: All functionality is available through keyboard interface
- **1.4.3 Contrast (Minimum) (Level AA)**: Skip link has sufficient color contrast when visible (>4.5:1)

## Implementation Details

### Component Location
- **File**: `parent-portal/components/SkipNavigation.tsx`
- **Integration**: `parent-portal/app/layout.tsx`

### How It Works

1. **Positioning**: The skip link is the first focusable element on every page
2. **Visibility**: Visually hidden by default, becomes visible when focused via keyboard
3. **Navigation**: Links to `#main-content` anchor on the page
4. **Focus Management**: Main content has `tabIndex={-1}` to receive programmatic focus

### Code Structure

```tsx
// Component Implementation
export function SkipNavigation() {
  return (
    <a
      href="#main-content"
      className="skip-to-main sr-only-focusable fixed left-4 top-4 z-[9999] rounded-md bg-primary-900 px-4 py-2 font-semibold text-white shadow-lg transition-transform focus:translate-y-0"
    >
      Skip to main content
    </a>
  );
}
```

```tsx
// Layout Integration
export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en">
      <body>
        <SkipNavigation />
        <Navigation />
        <main id="main-content" tabIndex={-1}>
          {children}
        </main>
      </body>
    </html>
  );
}
```

## CSS Styling

The skip navigation uses two key CSS utilities:

### 1. Screen Reader Only (Focusable)

```css
.sr-only-focusable {
  /* Visually hidden by default */
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0, 0, 0, 0);
  white-space: nowrap;
  border-width: 0;
}

.sr-only-focusable:focus {
  /* Visible when focused */
  position: static;
  width: auto;
  height: auto;
  overflow: visible;
  clip: auto;
  white-space: normal;
}
```

### 2. Skip to Main Styles

```css
.skip-to-main {
  position: fixed;
  left: -9999px;
  z-index: 9999;
  padding: 0.75rem 1.5rem;
  font-weight: 600;
  transition: left 0.2s;
}

.skip-to-main:focus {
  left: 1rem;
  top: 1rem;
}
```

## Usage Examples

### Basic Usage

The skip navigation is automatically included in the root layout and requires no additional setup for individual pages:

```tsx
// In any page component
export default function MyPage() {
  return (
    <div>
      <h1>Page Title</h1>
      <p>Page content...</p>
    </div>
  );
}
```

The skip link will automatically appear before all navigation on every page.

### Custom Main Content IDs

If you need multiple skip links (for complex layouts), you can create custom variations:

```tsx
export function SkipToSearch() {
  return (
    <a
      href="#search-content"
      className="sr-only-focusable fixed left-4 top-16 z-[9999] rounded-md bg-primary-900 px-4 py-2 font-semibold text-white"
    >
      Skip to search
    </a>
  );
}
```

## Keyboard Navigation

### User Experience

1. **Tab Key**: User presses Tab when page loads
2. **First Focus**: Skip link becomes visible at top-left of viewport
3. **Activation**: User presses Enter to activate link
4. **Jump**: Page scrolls to main content, focus moves to main element
5. **Continue**: User can continue tabbing through main content

### Key Bindings

| Key | Action |
|-----|--------|
| Tab | Focus skip link (first tab on page load) |
| Enter/Space | Activate skip link, jump to main content |
| Shift+Tab | Move focus backwards (from skip link to browser chrome) |

## Screen Reader Compatibility

### NVDA (Windows)
- Announces: "Skip to main content, link"
- Activation: Enter key
- Behavior: Moves virtual cursor to main content

### JAWS (Windows)
- Announces: "Skip to main content, link"
- Activation: Enter key
- Behavior: Moves virtual cursor to main content

### VoiceOver (macOS/iOS)
- Announces: "Skip to main content, link"
- Activation: Control+Option+Space
- Behavior: Moves focus to main content landmark

### TalkBack (Android)
- Announces: "Skip to main content, link"
- Activation: Double tap
- Behavior: Navigates to main content

## Testing

### Manual Testing

1. **Keyboard Test**:
   ```
   - Load any page
   - Press Tab key
   - Verify skip link appears
   - Press Enter
   - Verify focus moves to main content
   ```

2. **Screen Reader Test**:
   ```
   - Enable screen reader
   - Load any page
   - Tab to first element
   - Verify "Skip to main content" is announced
   - Activate link
   - Verify focus moves to main content
   ```

3. **Visual Test**:
   ```
   - Load page
   - Press Tab to focus skip link
   - Verify link is visible with good contrast
   - Verify link has clear focus indicator
   ```

### Automated Testing

Run the comprehensive test suite:

```bash
npm test -- skip-navigation.test.tsx
```

**Test Coverage:**
- Component rendering
- Keyboard navigation
- WCAG 2.1 AA compliance
- Focus management
- Integration with layout
- Edge cases

## Browser Support

| Browser | Version | Support |
|---------|---------|---------|
| Chrome | 90+ | ✅ Full |
| Firefox | 88+ | ✅ Full |
| Safari | 14+ | ✅ Full |
| Edge | 90+ | ✅ Full |
| Opera | 76+ | ✅ Full |

## Accessibility Benefits

### For Keyboard Users
- **Time Savings**: Skip repetitive navigation on every page
- **Efficiency**: Direct access to main content
- **Reduced Fatigue**: Less keystrokes required

### For Screen Reader Users
- **Better Experience**: No need to listen to all navigation links
- **Quick Access**: Jump to content immediately
- **Standard Pattern**: Familiar to assistive technology users

### For Motor Impairment Users
- **Reduced Clicks**: Fewer interactions needed
- **Faster Navigation**: Direct path to content
- **Less Frustration**: Easier to use website

## Common Issues and Solutions

### Issue 1: Skip Link Not Visible on Focus

**Problem**: Skip link doesn't appear when tabbed to

**Solution**:
- Verify `sr-only-focusable` class is applied
- Check that no parent element has `overflow: hidden`
- Ensure z-index is high enough (9999)

### Issue 2: Main Content Not Receiving Focus

**Problem**: Clicking skip link doesn't move focus

**Solution**:
- Verify main element has `id="main-content"`
- Ensure main element has `tabIndex={-1}`
- Check that no JavaScript is preventing default anchor behavior

### Issue 3: Skip Link Behind Other Elements

**Problem**: Skip link is obscured by navigation

**Solution**:
- Increase z-index value
- Use `position: fixed` instead of absolute
- Ensure no sticky elements have higher z-index

## Best Practices

### DO ✅
- Place skip link as first focusable element
- Use descriptive link text ("Skip to main content")
- Ensure high contrast when visible (WCAG AA)
- Test with keyboard and screen readers
- Include in all pages via layout component

### DON'T ❌
- Hide skip link from screen readers
- Use ambiguous text ("Skip")
- Place skip link after navigation
- Forget to mark main content with ID
- Disable focus indicators

## Related Documentation

- [WCAG 2.1 AA Compliance Guide](./ACCESSIBILITY_AUDIT.md)
- [Keyboard Navigation](../KEYBOARD_NAVIGATION.md)
- [Focus Management](../FOCUS_MANAGEMENT_IMPLEMENTATION.md)
- [Screen Reader Compatibility](../SCREEN_READER_COMPATIBILITY.md)

## Resources

### WCAG Guidelines
- [WCAG 2.4.1 Bypass Blocks](https://www.w3.org/WAI/WCAG21/Understanding/bypass-blocks.html)
- [WCAG 2.1.1 Keyboard](https://www.w3.org/WAI/WCAG21/Understanding/keyboard.html)

### Code Examples
- [WebAIM: Skip Navigation Links](https://webaim.org/techniques/skipnav/)
- [A11y Project: Skip Navigation](https://www.a11yproject.com/posts/skip-nav-links/)

### Testing Tools
- [WAVE Browser Extension](https://wave.webaim.org/extension/)
- [axe DevTools](https://www.deque.com/axe/devtools/)
- [Lighthouse Accessibility Audit](https://developers.google.com/web/tools/lighthouse)

## Maintenance

### Regular Checks
- Verify skip link appears on all new pages
- Test with latest browser versions
- Validate with accessibility testing tools
- Review user feedback

### Updates
- Monitor WCAG guideline changes
- Update documentation as needed
- Keep test suite current
- Review and improve based on usage patterns

## Support

For issues or questions about skip navigation implementation:
1. Check this documentation first
2. Review test cases in `__tests__/components/skip-navigation.test.tsx`
3. Consult WCAG 2.1 guidelines
4. Contact accessibility team

---

**Document Version**: 1.0.0
**Last Updated**: 2026-02-17
**Maintained By**: LAYA Development Team
