# Keyboard Navigation Documentation

## Overview

This document outlines the keyboard navigation features implemented in the LAYA Parent Portal to ensure WCAG 2.1 AA compliance.

## Global Keyboard Shortcuts

### Skip Navigation
- **Key:** Tab (on page load)
- **Action:** Focus the "Skip to main content" link
- **Purpose:** Allows keyboard users to bypass navigation and jump directly to main content

### General Navigation
- **Tab:** Move focus forward through interactive elements
- **Shift + Tab:** Move focus backward through interactive elements
- **Enter/Space:** Activate focused button or link

## Component-Specific Keyboard Navigation

### 1. Child Selector Dropdown

**Keyboard Controls:**
- **Enter/Space:** Open/close the dropdown
- **Arrow Down:** Move focus to next child in the list
- **Arrow Up:** Move focus to previous child in the list
- **Home:** Jump to first child in the list
- **End:** Jump to last child in the list
- **Escape:** Close the dropdown
- **Enter/Space (on option):** Select the focused child and close dropdown
- **Click outside:** Close the dropdown

**Implementation:**
- Uses `useArrowNavigation` hook for list navigation
- Uses `useEscapeKey` hook for Escape key handling
- Uses `useClickOutside` hook for click-outside-to-close
- Proper `tabIndex` management for roving tab index
- ARIA attributes: `role="listbox"`, `role="option"`, `aria-selected`

### 2. Photo Gallery Lightbox

**Keyboard Controls:**
- **Enter/Space (on thumbnail):** Open photo in lightbox
- **Escape:** Close the lightbox
- **Arrow Right:** View next photo
- **Arrow Left:** View previous photo
- **Tab/Shift+Tab:** Navigate between close button and navigation dots

**Implementation:**
- Uses `useFocusTrap` hook to trap focus within modal
- Uses `useEscapeKey` hook for Escape key handling
- Arrow key navigation between photos
- Focus returns to triggering thumbnail when modal closes
- ARIA attributes: `role="dialog"`, `aria-modal="true"`

### 3. Document Signature Modal

**Keyboard Controls:**
- **Escape:** Close the modal (when not submitting)
- **Tab/Shift+Tab:** Navigate between form fields, signature canvas, checkbox, and buttons
- **Enter:** Submit form (when all required fields are completed)

**Implementation:**
- Uses `useFocusTrap` hook to trap focus within modal
- Escape key handler respects `isSubmitting` state
- Focus returns to triggering element when modal closes
- Body scroll prevented when modal is open
- ARIA attributes: `role="dialog"`, `aria-modal="true"`, `aria-labelledby`

### 4. Message Composer

**Keyboard Controls:**
- **Enter:** Send message
- **Shift + Enter:** Insert new line
- **Tab:** Move to attach button or send button

**Implementation:**
- Clear keyboard hints provided (`aria-describedby`)
- Proper form submission handling
- Auto-resize textarea

### 5. Navigation Bar

**Keyboard Controls:**
- **Tab/Shift+Tab:** Navigate between navigation links
- **Enter/Space:** Activate link and navigate to page

**Implementation:**
- Semantic `<nav>` elements with `aria-label`
- Current page indicated with `aria-current="page"`
- Focus indicators on all interactive elements

## Custom Hooks

### useFocusTrap

Traps keyboard focus within a container (e.g., modals, dialogs).

**Features:**
- Cycles Tab key between first and last focusable elements
- Stores and restores previously focused element
- Automatically focuses first element when activated

**Usage:**
```tsx
const modalRef = useFocusTrap<HTMLDivElement>(isOpen);
<div ref={modalRef}>...</div>
```

### useEscapeKey

Handles Escape key press events.

**Features:**
- Executes callback when Escape is pressed
- Can be conditionally activated/deactivated
- Properly cleaned up on unmount

**Usage:**
```tsx
useEscapeKey(handleClose, isActive);
```

### useArrowNavigation

Implements arrow key navigation for lists and menus.

**Features:**
- Arrow Up/Down navigation
- Home/End key support
- Enter/Space for selection
- Optional looping from last to first item
- Roving tabindex pattern

**Usage:**
```tsx
const { focusedIndex } = useArrowNavigation({
  itemCount: items.length,
  isActive: isOpen,
  onSelect: handleSelect,
  loop: true,
});
```

### useClickOutside

Detects clicks outside a referenced element.

**Features:**
- Closes dropdowns/menus when clicking outside
- Can be conditionally activated
- Uses capture phase for reliable detection

**Usage:**
```tsx
const containerRef = useRef<HTMLDivElement>(null);
useClickOutside(containerRef, handleClose, isActive);
<div ref={containerRef}>...</div>
```

## Focus Indicators

### Visual Design
- **Color:** Primary-500 (#3b82f6)
- **Width:** 2px
- **Offset:** 2px
- **Style:** Solid outline with rounded corners

### Implementation
- Uses `:focus-visible` for keyboard-only focus indicators
- Fallback for browsers without `:focus-visible` support
- High contrast mode support with 3px outline
- Consistent across all interactive elements

## Accessibility Features

### Screen Reader Support
- `.sr-only` utility class for visually hidden content
- `.sr-only-focusable` for keyboard-accessible hidden content
- Proper ARIA labels on all interactive elements
- Semantic HTML structure

### Motion Preferences
- Respects `prefers-reduced-motion` media query
- Reduces animation/transition duration to near-instant
- Disables smooth scrolling when motion is reduced

### High Contrast Mode
- Enhanced focus indicators (3px) in high contrast mode
- Uses `currentColor` for better visibility

## Testing Keyboard Navigation

### Manual Testing Checklist

1. **Tab Order**
   - [ ] Tab through entire page in logical order
   - [ ] No keyboard traps (can tab out of all components)
   - [ ] Skip navigation link appears first
   - [ ] Focus indicators clearly visible

2. **Child Selector**
   - [ ] Can open with Enter/Space
   - [ ] Arrow keys navigate options
   - [ ] Escape closes dropdown
   - [ ] Enter selects option
   - [ ] Click outside closes dropdown

3. **Photo Gallery**
   - [ ] Can open lightbox with Enter
   - [ ] Escape closes lightbox
   - [ ] Arrow keys navigate photos
   - [ ] Focus trapped in modal
   - [ ] Focus returns to thumbnail on close

4. **Document Signature**
   - [ ] Escape closes modal
   - [ ] Tab cycles through form fields
   - [ ] Focus trapped in modal
   - [ ] Cannot submit without signature and agreement
   - [ ] Focus returns to trigger on close

5. **Messages**
   - [ ] Enter sends message
   - [ ] Shift+Enter creates new line
   - [ ] Tab navigates to buttons

### Browser Testing
- Chrome/Edge (Chromium)
- Firefox
- Safari

### Screen Reader Testing
- NVDA (Windows)
- JAWS (Windows)
- VoiceOver (macOS/iOS)

## Common Patterns

### Modal Focus Management
```tsx
const modalRef = useFocusTrap<HTMLDivElement>(isOpen);
useEscapeKey(handleClose, isOpen);

// Prevent body scroll
useEffect(() => {
  if (isOpen) {
    document.body.style.overflow = 'hidden';
  }
  return () => {
    document.body.style.overflow = 'unset';
  };
}, [isOpen]);

<div ref={modalRef} role="dialog" aria-modal="true">
  {/* Modal content */}
</div>
```

### Dropdown/Listbox Pattern
```tsx
const containerRef = useRef<HTMLDivElement>(null);
const { focusedIndex } = useArrowNavigation({
  itemCount: items.length,
  isActive: isOpen,
  onSelect: handleSelect,
});

useEscapeKey(handleClose, isOpen);
useClickOutside(containerRef, handleClose, isOpen);

<div ref={containerRef}>
  <button
    aria-expanded={isOpen}
    aria-haspopup="listbox"
  >
    Toggle
  </button>
  {isOpen && (
    <div role="listbox">
      {items.map((item, index) => (
        <button
          role="option"
          tabIndex={focusedIndex === index ? 0 : -1}
          aria-selected={selected === item}
        >
          {item}
        </button>
      ))}
    </div>
  )}
</div>
```

## Resources

- [WCAG 2.1 Guidelines](https://www.w3.org/WAI/WCAG21/quickref/)
- [ARIA Authoring Practices Guide](https://www.w3.org/WAI/ARIA/apg/)
- [WebAIM Keyboard Accessibility](https://webaim.org/articles/keyboard/)
- [Inclusive Components](https://inclusive-components.design/)

## Future Enhancements

- [ ] Keyboard shortcuts documentation modal (? key)
- [ ] Customizable keyboard shortcuts
- [ ] Visual focus indicator customization
- [ ] Tab order debugging mode
- [ ] Automated keyboard navigation testing
