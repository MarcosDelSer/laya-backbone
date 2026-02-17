# Screen Reader Compatibility Guide

This document describes the screen reader accessibility features implemented in the LAYA Parent Portal to achieve WCAG 2.1 AA compliance.

## Overview

The Parent Portal implements comprehensive screen reader support through:

1. **Alt text on all images** - Descriptive alternative text for all meaningful images
2. **ARIA live regions** - Dynamic content updates announced to screen readers
3. **Screen reader announcements** - Programmatic announcements for state changes
4. **Proper semantic markup** - Using appropriate HTML elements and ARIA attributes
5. **Hidden decorative elements** - Decorative icons properly hidden from screen readers

## Implementation Components

### 1. ScreenReaderAnnouncer Component

**Location:** `components/ScreenReaderAnnouncer.tsx`

A global component that provides aria-live regions for screen reader announcements. This component is included once in the app layout.

**Features:**
- Two live regions: `polite` (non-interrupting) and `assertive` (interrupting)
- Automatically cleans up announcements after they've been read
- Uses `sr-only` class to hide visually while keeping accessible

**Usage:**
```tsx
// Already included in app/layout.tsx
<ScreenReaderAnnouncer />
```

### 2. useAnnounce Hook

**Location:** `hooks/useAnnounce.ts`

A React hook for making programmatic screen reader announcements.

**API:**
```tsx
const announce = useAnnounce();

// Polite announcement (waits for screen reader to finish)
announce('Message sent successfully', 'polite');

// Assertive announcement (interrupts screen reader - use sparingly)
announce('Error: Form submission failed', 'assertive');
```

**Best Practices:**
- Use `polite` for success messages, status updates, and non-critical information
- Use `assertive` only for errors, warnings, and critical information
- Keep messages concise and descriptive
- Avoid announcing visual changes that don't affect content

**Example Usage:**
```tsx
'use client';

import { useState } from 'react';
import { useAnnounce } from '@/hooks';

export function MyComponent() {
  const [loading, setLoading] = useState(false);
  const announce = useAnnounce();

  const handleSubmit = async () => {
    setLoading(true);
    announce('Submitting form, please wait', 'polite');

    try {
      await submitForm();
      announce('Form submitted successfully', 'polite');
    } catch (error) {
      announce('Error submitting form: ' + error.message, 'assertive');
    } finally {
      setLoading(false);
    }
  };

  return (
    <form onSubmit={handleSubmit}>
      {/* form content */}
    </form>
  );
}
```

### 3. LoadingSpinner Component

**Location:** `components/LoadingSpinner.tsx`

An accessible loading indicator with proper screen reader announcements.

**Features:**
- `role="status"` for screen reader announcement
- `aria-live="polite"` for dynamic updates
- Customizable size and label
- Hidden spinner animation (decorative)

**Usage:**
```tsx
import { LoadingSpinner } from '@/components/LoadingSpinner';

<LoadingSpinner size="md" label="Loading data" />
```

## Image Alt Text Guidelines

All images in the application follow these guidelines:

### Informative Images
Images that convey information or meaning MUST have descriptive alt text:

```tsx
// Photo Gallery - descriptive alt text
<Image
  src={photo.url}
  alt={photo.caption || `Photo ${index + 1}`}
  // ...
/>

// Dashboard photo placeholders
<div role="img" aria-label="Art project - Today">
  {/* placeholder content */}
</div>
```

### Decorative Images
Purely decorative images MUST be hidden from screen readers:

```tsx
// Decorative icon
<svg aria-hidden="true">
  {/* icon paths */}
</svg>

// Decorative background div
<div className="bg-primary-100" aria-hidden="true">
  {/* decorative content */}
</div>
```

### Alt Text Best Practices

1. **Be concise** - Describe the image briefly (under 150 characters when possible)
2. **Be descriptive** - Include relevant details that convey the image's purpose
3. **Avoid redundancy** - Don't start with "Image of..." or "Picture of..."
4. **Context matters** - Consider the surrounding content when writing alt text
5. **Empty alt for decorative** - Use `aria-hidden="true"` for decorative elements

**Examples:**

✅ Good:
```tsx
<Image alt="Child painting at art table" />
<Image alt="Emma's breakfast - pancakes and fruit" />
<svg aria-hidden="true"> {/* decorative icon */} </svg>
```

❌ Bad:
```tsx
<Image alt="Image of a child" />
<Image alt="" /> {/* informative image with empty alt */}
<svg> {/* decorative icon without aria-hidden */} </svg>
```

## ARIA Live Regions

### Loading States

All loading states use `role="status"` and `aria-live="polite"`:

```tsx
// app/loading.tsx
<div
  role="status"
  aria-live="polite"
  aria-label="Loading content, please wait"
>
  <span className="sr-only">Loading content, please wait...</span>
  {/* skeleton UI */}
</div>
```

### Error States

Error states use `role="alert"` and `aria-live="assertive"`:

```tsx
// app/error.tsx
<div
  role="alert"
  aria-live="assertive"
>
  <h2>Something went wrong!</h2>
  <p>Error message here</p>
</div>
```

### Dynamic Content Updates

Dynamic content that changes uses appropriate aria-live values:

```tsx
// Character count in MessageComposer
<span
  role="status"
  aria-live="polite"
  className="text-xs text-gray-400"
>
  {message.length}/500
</span>

// Status badges
<span
  role="status"
  aria-label="Child status: Checked In"
  className="badge badge-success"
>
  Checked In
</span>
```

## Semantic Time Elements

All temporal data uses semantic `<time>` elements:

```tsx
// Activities
<time dateTime={activity.time}>{activity.time}</time>

// Naps
<time dateTime={nap.startTime}>{nap.startTime}</time> -
<time dateTime={nap.endTime}>{nap.endTime}</time>

// Meals
<time dateTime={meal.time}>{meal.time}</time>
```

## Testing Screen Reader Compatibility

### Automated Testing Tools

1. **axe DevTools** (Browser Extension)
   - Install: Chrome/Firefox extension
   - Run: DevTools → axe → Scan
   - Check: All WCAG 2.1 AA issues resolved

2. **WAVE** (Browser Extension)
   - Install: Chrome/Firefox extension
   - Check: Images, ARIA, structure

3. **Lighthouse** (Chrome DevTools)
   - Open: DevTools → Lighthouse
   - Run: Accessibility audit
   - Target: Score ≥ 90

### Manual Screen Reader Testing

#### macOS - VoiceOver

1. **Enable VoiceOver:** `Cmd + F5`
2. **Basic Navigation:**
   - Next element: `VO + Right Arrow` (Ctrl+Option+Right Arrow)
   - Previous element: `VO + Left Arrow`
   - Interact with element: `VO + Space`
3. **Test Scenarios:**
   - Navigate through page using Tab key
   - Listen to all headings: `VO + Cmd + H`
   - Listen to all images: Verify alt text is read
   - Trigger form submission: Verify success/error announcements
   - Navigate to loading page: Verify "Loading" is announced

#### Windows - NVDA (Free)

1. **Download:** https://www.nvaccess.org/download/
2. **Start NVDA:** `Ctrl + Alt + N`
3. **Basic Navigation:**
   - Next element: `Down Arrow`
   - Previous element: `Up Arrow`
   - Interact: `Enter` or `Space`
4. **Test Scenarios:** Same as VoiceOver

#### Windows - JAWS (Commercial)

1. **Download:** https://www.freedomscientific.com/products/software/jaws/
2. **Start JAWS:** Automatically starts
3. **Basic Navigation:**
   - Next element: `Down Arrow`
   - Next heading: `H`
   - Next button: `B`
4. **Test Scenarios:** Same as VoiceOver

### Testing Checklist

- [ ] All images have appropriate alt text or are hidden with `aria-hidden="true"`
- [ ] Loading states announce "Loading" to screen readers
- [ ] Error states announce errors with `role="alert"`
- [ ] Form submissions announce success/failure
- [ ] Dynamic content updates are announced (character count, status changes)
- [ ] All decorative icons are hidden from screen readers
- [ ] Time elements use semantic `<time>` tags
- [ ] Navigation is logical and follows visual order
- [ ] All interactive elements have accessible names
- [ ] Live regions work correctly (test by triggering state changes)

## WCAG 2.1 AA Criteria Addressed

### 1.1.1 Non-text Content (Level A)
✅ All images have text alternatives via alt attributes or aria-label
✅ Decorative images hidden with aria-hidden="true"

### 1.3.1 Info and Relationships (Level A)
✅ Semantic HTML elements used (nav, main, article, section)
✅ ARIA roles supplement semantic structure (role="list", role="listitem")

### 2.4.4 Link Purpose (Level A)
✅ All links have descriptive aria-label when text is unclear

### 4.1.2 Name, Role, Value (Level A)
✅ All interactive elements have accessible names
✅ All dynamic content has appropriate ARIA attributes

### 4.1.3 Status Messages (Level AA)
✅ Status messages use role="status" with aria-live="polite"
✅ Error messages use role="alert" with aria-live="assertive"

## Common Patterns

### Pattern: Loading State
```tsx
<div
  role="status"
  aria-live="polite"
  aria-label="Loading content"
>
  <LoadingSpinner label="Loading data" />
</div>
```

### Pattern: Error Message
```tsx
<div
  role="alert"
  aria-live="assertive"
>
  <p>Error: {errorMessage}</p>
</div>
```

### Pattern: Success Announcement
```tsx
const announce = useAnnounce();

const handleSuccess = () => {
  announce('Operation completed successfully', 'polite');
};
```

### Pattern: Informative Image
```tsx
<Image
  src="/photo.jpg"
  alt="Descriptive text that conveys the image's meaning"
  // ...
/>
```

### Pattern: Decorative Element
```tsx
<div aria-hidden="true">
  <svg> {/* decorative icon */} </svg>
</div>
```

## Troubleshooting

### Issue: Screen reader not announcing live regions
**Solution:**
- Ensure ScreenReaderAnnouncer component is in the layout
- Check that aria-live and role attributes are present
- Verify content is actually changing (not just CSS)

### Issue: Images not being read
**Solution:**
- Check that alt attribute is present and not empty
- Verify decorative images have aria-hidden="true"
- Test with actual screen reader, not just automated tools

### Issue: Redundant announcements
**Solution:**
- Remove aria-label from elements with sufficient text content
- Hide decorative icons with aria-hidden="true"
- Use role="presentation" for layout tables

## Future Improvements

1. **Reduced Motion Support** - Respect prefers-reduced-motion for animations
2. **Custom Focus Indicators** - Enhanced focus visibility for keyboard users
3. **Screen Reader-Only Content** - Additional context for complex interactions
4. **Announcement Queue** - Prevent announcement overlap
5. **Language Support** - Multi-language announcements

## Resources

- [WCAG 2.1 Quick Reference](https://www.w3.org/WAI/WCAG21/quickref/)
- [ARIA Authoring Practices Guide](https://www.w3.org/WAI/ARIA/apg/)
- [WebAIM Screen Reader Testing](https://webaim.org/articles/screenreader_testing/)
- [MDN ARIA Live Regions](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/ARIA_Live_Regions)

## Support

For questions or issues with screen reader compatibility:
1. Review this documentation
2. Test with actual screen readers
3. Consult WCAG 2.1 guidelines
4. File an issue with reproduction steps
