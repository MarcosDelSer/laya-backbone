# Focus Management (Modal Trapping)

## Overview

This document describes the focus management implementation for modal dialogs and overlays in the parent portal application. Focus trapping ensures that keyboard navigation stays within modal components, meeting WCAG 2.1 AA accessibility requirements.

## Key Features

### 1. Focus Trapping
- Traps keyboard focus within modal dialogs
- Prevents Tab navigation from escaping the modal
- Cycles focus between first and last focusable elements

### 2. Focus Restoration
- Stores the previously focused element when modal opens
- Restores focus to that element when modal closes
- Ensures natural keyboard navigation flow

### 3. Keyboard Support
- **Tab**: Navigate to next focusable element
- **Shift+Tab**: Navigate to previous focusable element
- **Escape**: Close modal (when not disabled)
- **Arrow keys**: Navigate between items (in galleries)

### 4. Body Scroll Lock
- Prevents body scrolling when modal is open
- Automatically restores scroll when modal closes

## Implementation

### The `useFocusTrap` Hook

Located in: `parent-portal/hooks/useFocusTrap.ts`

```typescript
import { useFocusTrap } from '@/hooks';

function MyModal({ isOpen, onClose }) {
  const modalRef = useFocusTrap<HTMLDivElement>(isOpen);

  if (!isOpen) return null;

  return (
    <div ref={modalRef} role="dialog" aria-modal="true">
      {/* Modal content */}
    </div>
  );
}
```

#### Parameters
- `isActive` (boolean): Whether focus trap should be active

#### Returns
- `containerRef`: React ref to attach to the modal container

#### Behavior
1. **When activated**:
   - Stores currently focused element
   - Focuses first focusable element in container
   - Adds Tab key listener to trap focus

2. **Tab key handling**:
   - On Tab: If on last element, wrap to first
   - On Shift+Tab: If on first element, wrap to last

3. **When deactivated**:
   - Removes event listeners
   - Restores focus to previously focused element

### Supporting Hooks

#### `useEscapeKey`
Handles Escape key press to close modals.

```typescript
import { useEscapeKey } from '@/hooks';

function MyModal({ isOpen, onClose }) {
  useEscapeKey(onClose, isOpen);
  // ...
}
```

#### `useArrowNavigation`
Provides arrow key navigation for lists within modals.

```typescript
import { useArrowNavigation } from '@/hooks';

function MyDropdown({ isOpen, items }) {
  const { focusedIndex } = useArrowNavigation({
    itemCount: items.length,
    isActive: isOpen,
    onSelect: (index) => handleSelect(items[index]),
    loop: true,
  });
  // ...
}
```

## Examples

### 1. Document Signature Modal

Located in: `parent-portal/components/DocumentSignature.tsx`

Features:
- Focus trapped within signature form
- Escape key closes modal (unless submitting)
- Body scroll locked
- Focus restored to trigger button on close

```typescript
export function DocumentSignature({ isOpen, onClose, documentToSign, onSubmit }) {
  const modalRef = useFocusTrap<HTMLDivElement>(isOpen && !isSubmitting);

  useEffect(() => {
    const handleEscape = (e: KeyboardEvent) => {
      if (e.key === 'Escape' && !isSubmitting) {
        onClose();
      }
    };

    if (isOpen) {
      window.addEventListener('keydown', handleEscape);
      document.body.style.overflow = 'hidden';
    }

    return () => {
      window.removeEventListener('keydown', handleEscape);
      document.body.style.overflow = 'unset';
    };
  }, [isOpen, isSubmitting, onClose]);

  return (
    <div role="dialog" aria-modal="true" aria-labelledby="modal-title">
      <div ref={modalRef}>
        {/* Modal content */}
      </div>
    </div>
  );
}
```

### 2. Photo Gallery Lightbox

Located in: `parent-portal/components/PhotoGallery.tsx`

Features:
- Focus trapped in photo viewer
- Arrow key navigation between photos
- Escape key closes viewer
- Focus restored to selected photo on close

```typescript
export function PhotoGallery({ photos }) {
  const [selectedPhoto, setSelectedPhoto] = useState<Photo | null>(null);
  const modalRef = useFocusTrap<HTMLDivElement>(selectedPhoto !== null);

  const closeModal = useCallback(() => {
    setSelectedPhoto(null);
  }, []);

  useEscapeKey(closeModal, selectedPhoto !== null);

  // Arrow key navigation
  const handleKeyDown = useCallback((e: KeyboardEvent) => {
    if (!selectedPhoto) return;

    if (e.key === 'ArrowRight') {
      navigatePhoto('next');
    } else if (e.key === 'ArrowLeft') {
      navigatePhoto('prev');
    }
  }, [selectedPhoto, navigatePhoto]);

  return (
    <>
      {/* Gallery grid */}
      {selectedPhoto && (
        <div ref={modalRef} role="dialog" aria-modal="true" aria-label="Photo viewer">
          {/* Photo viewer content */}
        </div>
      )}
    </>
  );
}
```

## Best Practices

### 1. ARIA Attributes
Always include proper ARIA attributes on modals:

```typescript
<div
  role="dialog"
  aria-modal="true"
  aria-labelledby="modal-title"
  aria-describedby="modal-description"
>
  <h2 id="modal-title">Modal Title</h2>
  <p id="modal-description">Modal description</p>
</div>
```

### 2. Focusable Elements
The focus trap looks for these elements:
- `a[href]`
- `button:not([disabled])`
- `textarea:not([disabled])`
- `input:not([disabled])`
- `select:not([disabled])`
- `[tabindex]:not([tabindex="-1"])`

### 3. Initial Focus
The first focusable element receives focus automatically. To control this:

```typescript
// Add tabindex to control focus order
<button tabIndex={1}>First focused</button>
<button tabIndex={2}>Second focused</button>
```

### 4. Escape Key Handling
Consider the state of your modal when handling Escape:

```typescript
useEffect(() => {
  const handleEscape = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && !isSubmitting && !hasUnsavedChanges) {
      onClose();
    }
  };
  // ...
}, [isOpen, isSubmitting, hasUnsavedChanges]);
```

### 5. Body Scroll Lock
Always lock body scroll to prevent background scrolling:

```typescript
useEffect(() => {
  if (isOpen) {
    document.body.style.overflow = 'hidden';
  }

  return () => {
    document.body.style.overflow = 'unset';
  };
}, [isOpen]);
```

## Testing

### Unit Tests
Located in: `parent-portal/__tests__/hooks/keyboard-navigation.test.tsx`

Tests for:
- Focus trap activation/deactivation
- Tab key cycling
- Escape key handling
- Focus restoration

### Integration Tests
Located in: `parent-portal/__tests__/components/focus-management.test.tsx`

Tests for:
- Modal focus trapping
- Keyboard navigation
- ARIA attributes
- Body scroll lock
- Focus restoration

### Manual Testing Checklist

1. **Focus Trap**:
   - [ ] Open modal
   - [ ] Tab through all elements
   - [ ] Verify focus cycles back to first element
   - [ ] Shift+Tab cycles backward

2. **Escape Key**:
   - [ ] Press Escape to close modal
   - [ ] Verify modal closes
   - [ ] Verify focus restored to trigger

3. **Screen Reader**:
   - [ ] Modal announced as dialog
   - [ ] Title is read
   - [ ] Focus moves to modal

4. **Body Scroll**:
   - [ ] Open modal
   - [ ] Try to scroll page (should be locked)
   - [ ] Close modal
   - [ ] Verify scroll restored

## Accessibility Compliance

This implementation meets WCAG 2.1 AA requirements:

### Success Criteria Met

- **2.1.1 Keyboard (Level A)**: All functionality available via keyboard
- **2.1.2 No Keyboard Trap (Level A)**: Focus can be moved away using standard methods (Escape)
- **2.4.3 Focus Order (Level A)**: Focus order is logical and meaningful
- **2.4.7 Focus Visible (Level AA)**: Focus indicators visible on all elements
- **4.1.2 Name, Role, Value (Level A)**: Proper ARIA attributes on all interactive elements

### Testing with Assistive Technology

Tested with:
- NVDA (Windows)
- JAWS (Windows)
- VoiceOver (macOS/iOS)
- TalkBack (Android)

## Common Issues & Solutions

### Issue: Focus escapes modal
**Solution**: Ensure the ref is attached to the container element that wraps all focusable content.

### Issue: Focus not restored after close
**Solution**: Verify the modal is properly unmounted and the cleanup function runs.

### Issue: Tab doesn't cycle
**Solution**: Check that focusable elements are not disabled and have proper tabindex values.

### Issue: Escape doesn't close
**Solution**: Verify the escape key handler is active and not blocked by other event listeners.

## References

- [ARIA Authoring Practices Guide - Dialog](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/)
- [WCAG 2.1 - Keyboard Accessible](https://www.w3.org/WAI/WCAG21/Understanding/keyboard)
- [MDN - Managing Focus](https://developer.mozilla.org/en-US/docs/Web/Accessibility/Keyboard-navigable_JavaScript_widgets)
