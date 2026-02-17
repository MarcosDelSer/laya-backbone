# Form Labels and Error Messages - Accessibility Guide

## Overview

This document outlines the accessibility requirements and best practices for form labels and error messages in the LAYA Parent Portal. Proper form labeling and error handling are essential for WCAG 2.1 AA compliance and ensuring all users, including those using assistive technologies, can successfully complete forms.

## WCAG 2.1 AA Compliance

**Relevant Success Criteria:**

### Level A
- **1.3.1 Info and Relationships**: Information, structure, and relationships conveyed through presentation can be programmatically determined
- **3.3.1 Error Identification**: If an input error is automatically detected, the item in error is identified and the error is described to the user in text
- **3.3.2 Labels or Instructions**: Labels or instructions are provided when content requires user input
- **4.1.2 Name, Role, Value**: For all user interface components, the name and role can be programmatically determined

### Level AA
- **3.3.3 Error Suggestion**: If an input error is automatically detected and suggestions for correction are known, then the suggestions are provided to the user, unless it would jeopardize security or purpose
- **3.3.4 Error Prevention (Legal, Financial, Data)**: For pages that cause legal commitments or financial transactions, submissions are reversible, checked, or confirmed

## Form Label Requirements

### 1. Every Input Must Have a Label

**DO:**
```tsx
// Explicit label association using htmlFor
<label htmlFor="email" className="block text-sm font-medium text-gray-700">
  Email Address
</label>
<input
  type="email"
  id="email"
  name="email"
  className="mt-1 block w-full rounded-md border-gray-300"
/>

// Wrapped label (implicit association)
<label className="block text-sm font-medium text-gray-700">
  Email Address
  <input
    type="email"
    name="email"
    className="mt-1 block w-full rounded-md border-gray-300"
  />
</label>

// aria-label for inputs without visible label
<input
  type="search"
  name="search"
  aria-label="Search messages"
  placeholder="Search..."
/>
```

**DON'T:**
```tsx
// Missing label - inaccessible!
<input type="email" placeholder="Email" />

// Placeholder as label - fails WCAG!
<input type="text" placeholder="Full Name" />
```

### 2. Label Best Practices

1. **Use Clear, Descriptive Text**
   - Labels should clearly describe what information is expected
   - Avoid ambiguous terms like "Input" or "Field"

2. **Position Labels Consistently**
   - Place labels above inputs (recommended)
   - Or to the left for horizontal forms
   - Ensure 24px minimum click target size

3. **Required Field Indicators**
   ```tsx
   <label htmlFor="name" className="block text-sm font-medium text-gray-700">
     Full Name
     <span className="text-red-600" aria-label="required">*</span>
   </label>
   <input
     id="name"
     required
     aria-required="true"
   />
   ```

4. **Group Related Fields**
   ```tsx
   <fieldset>
     <legend className="text-base font-medium text-gray-900">
       Contact Information
     </legend>
     {/* Related inputs */}
   </fieldset>
   ```

### 3. Special Input Types

#### Checkboxes and Radio Buttons
```tsx
// Checkbox
<label className="flex items-center space-x-2">
  <input
    type="checkbox"
    id="agree"
    name="agree"
    aria-describedby="agree-description"
  />
  <span className="text-sm text-gray-700">
    I agree to the terms and conditions
  </span>
</label>
<p id="agree-description" className="mt-1 text-xs text-gray-500">
  By checking this box, you agree to be legally bound
</p>

// Radio group
<fieldset>
  <legend className="text-base font-medium">Payment Method</legend>
  <div className="mt-2 space-y-2">
    <label className="flex items-center">
      <input type="radio" name="payment" value="card" />
      <span className="ml-2">Credit Card</span>
    </label>
    <label className="flex items-center">
      <input type="radio" name="payment" value="bank" />
      <span className="ml-2">Bank Transfer</span>
    </label>
  </div>
</fieldset>
```

#### Textareas
```tsx
<label htmlFor="message" className="block text-sm font-medium text-gray-700">
  Message
</label>
<textarea
  id="message"
  name="message"
  rows={4}
  aria-describedby="message-hint"
  className="mt-1 block w-full rounded-md border-gray-300"
/>
<p id="message-hint" className="mt-1 text-sm text-gray-500">
  Maximum 500 characters
</p>
```

#### Select Dropdowns
```tsx
<label htmlFor="child" className="block text-sm font-medium text-gray-700">
  Select Child
</label>
<select
  id="child"
  name="child"
  aria-describedby="child-hint"
  className="mt-1 block w-full rounded-md border-gray-300"
>
  <option value="">-- Please Select --</option>
  <option value="1">John Doe</option>
  <option value="2">Jane Doe</option>
</select>
```

## Error Message Requirements

### 1. Error Identification

Errors must be:
- **Programmatically associated** with the input field
- **Visually distinct** from other text
- **Announced to screen readers** when they occur
- **Descriptive and actionable**

### 2. Error Message Pattern

```tsx
interface FormFieldProps {
  label: string;
  id: string;
  error?: string;
  required?: boolean;
  helpText?: string;
}

function FormField({ label, id, error, required, helpText }: FormFieldProps) {
  const errorId = `${id}-error`;
  const helpId = `${id}-help`;
  const describedBy = [
    error ? errorId : null,
    helpText ? helpId : null
  ].filter(Boolean).join(' ');

  return (
    <div>
      <label
        htmlFor={id}
        className="block text-sm font-medium text-gray-700"
      >
        {label}
        {required && (
          <span className="text-red-600 ml-1" aria-label="required">*</span>
        )}
      </label>

      <input
        type="text"
        id={id}
        name={id}
        required={required}
        aria-required={required}
        aria-invalid={!!error}
        aria-describedby={describedBy || undefined}
        className={`mt-1 block w-full rounded-md ${
          error
            ? 'border-red-500 focus:ring-red-500 focus:border-red-500'
            : 'border-gray-300 focus:ring-primary focus:border-primary'
        }`}
      />

      {error && (
        <p
          id={errorId}
          className="mt-1 text-sm text-red-600"
          role="alert"
          aria-live="polite"
        >
          {error}
        </p>
      )}

      {helpText && !error && (
        <p id={helpId} className="mt-1 text-sm text-gray-500">
          {helpText}
        </p>
      )}
    </div>
  );
}
```

### 3. Form-Level Error Summary

For forms with multiple errors:

```tsx
function FormErrorSummary({ errors }: { errors: Array<{ field: string; message: string }> }) {
  const summaryRef = useRef<HTMLDivElement>(null);

  // Focus error summary when errors appear
  useEffect(() => {
    if (errors.length > 0 && summaryRef.current) {
      summaryRef.current.focus();
    }
  }, [errors]);

  if (errors.length === 0) return null;

  return (
    <div
      ref={summaryRef}
      tabIndex={-1}
      role="alert"
      aria-labelledby="error-summary-title"
      className="mb-6 rounded-md bg-red-50 border border-red-400 p-4"
    >
      <div className="flex">
        <div className="flex-shrink-0">
          <svg className="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" />
          </svg>
        </div>
        <div className="ml-3">
          <h3 id="error-summary-title" className="text-sm font-medium text-red-800">
            There {errors.length === 1 ? 'is' : 'are'} {errors.length} error{errors.length !== 1 ? 's' : ''} with your submission
          </h3>
          <div className="mt-2 text-sm text-red-700">
            <ul className="list-disc list-inside space-y-1">
              {errors.map((error, index) => (
                <li key={index}>
                  <a href={`#${error.field}`} className="font-medium underline hover:text-red-900">
                    {error.message}
                  </a>
                </li>
              ))}
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
}
```

### 4. Error Message Guidelines

**DO:**
- Be specific about what's wrong: "Email address is required" ✓
- Suggest how to fix: "Password must contain at least 8 characters" ✓
- Use plain language, avoid jargon ✓
- Show errors after validation (on blur or submit) ✓

**DON'T:**
- Use vague messages: "Invalid input" ✗
- Blame the user: "You entered the wrong format" ✗
- Use only color to indicate errors ✗
- Show errors while user is still typing ✗

## Inline Validation

```tsx
const [email, setEmail] = useState('');
const [emailError, setEmailError] = useState('');
const [touched, setTouched] = useState(false);

const validateEmail = (value: string) => {
  if (!value) return 'Email address is required';
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
    return 'Please enter a valid email address (e.g., name@example.com)';
  }
  return '';
};

const handleBlur = () => {
  setTouched(true);
  setEmailError(validateEmail(email));
};

const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
  setEmail(e.target.value);
  // Only show errors after field has been touched
  if (touched) {
    setEmailError(validateEmail(e.target.value));
  }
};
```

## Screen Reader Announcements

### Live Regions for Dynamic Errors

```tsx
// Announce success or errors
<div role="status" aria-live="polite" aria-atomic="true" className="sr-only">
  {message}
</div>

// For urgent errors
<div role="alert" aria-live="assertive" className="sr-only">
  {criticalError}
</div>
```

## Form Submission States

```tsx
<button
  type="submit"
  disabled={isSubmitting}
  aria-busy={isSubmitting}
  aria-label={isSubmitting ? 'Submitting form' : 'Submit form'}
  className="btn btn-primary"
>
  {isSubmitting ? (
    <>
      <span className="sr-only">Submitting...</span>
      <LoadingSpinner aria-hidden="true" />
    </>
  ) : (
    'Submit'
  )}
</button>
```

## Testing Checklist

- [ ] All inputs have associated labels (visible or aria-label)
- [ ] Labels use `htmlFor` matching input `id`
- [ ] Required fields marked with `aria-required="true"`
- [ ] Error messages associated via `aria-describedby`
- [ ] Invalid fields have `aria-invalid="true"`
- [ ] Error messages use `role="alert"` for dynamic errors
- [ ] Form has error summary that receives focus on validation failure
- [ ] Tab order is logical and complete
- [ ] All functionality available via keyboard
- [ ] Color is not the only indicator of errors (icons, text)
- [ ] Error messages are descriptive and actionable
- [ ] Help text provided where needed
- [ ] Screen reader testing completed (NVDA, JAWS, VoiceOver)

## Existing Component Audit

### ✅ Compliant Components

1. **MessageComposer** (`components/MessageComposer.tsx`)
   - ✓ Textarea has `aria-label="Message text"`
   - ✓ Help text associated with `aria-describedby`
   - ✓ Character count has `role="status"` and `aria-live="polite"`
   - ✓ All buttons have descriptive `aria-label` attributes

2. **ColorContrastChecker** (`components/ColorContrastChecker.tsx`)
   - ✓ All inputs have `<label>` with `htmlFor` association
   - ✓ Color picker and text input both properly labeled
   - ✓ Clear, descriptive labels

### ⚠️ Needs Review

3. **DocumentSignature** (`components/DocumentSignature.tsx`)
   - ⚠️ Checkbox has `aria-label` but also visible text - should use `htmlFor` association
   - ⚠️ SignatureCanvas needs label association (has separate label id but not connected)
   - ✓ Form validation prevents submission when incomplete
   - ✓ Modal has proper `aria-labelledby` for title

### Recommended Improvements

#### DocumentSignature.tsx

**Current:**
```tsx
<input
  type="checkbox"
  checked={agreedToTerms}
  onChange={(e) => setAgreedToTerms(e.target.checked)}
  disabled={isSubmitting}
  aria-label="I acknowledge that I have read and understand this document"
  className="..."
/>
<span className="text-sm text-gray-600">
  I acknowledge that I have read and understand this document...
</span>
```

**Recommended:**
```tsx
<label className="flex items-start space-x-3">
  <input
    type="checkbox"
    id="agree-terms"
    checked={agreedToTerms}
    onChange={(e) => setAgreedToTerms(e.target.checked)}
    disabled={isSubmitting}
    aria-required="true"
    className="..."
  />
  <span className="text-sm text-gray-600">
    I acknowledge that I have read and understand this document.
    By signing below, I agree to be legally bound by its terms and conditions.
  </span>
</label>
```

**SignatureCanvas Label:**
```tsx
<div className="mb-6">
  <label
    htmlFor="signature-canvas"
    id="signature-label"
    className="block text-sm font-medium text-gray-700 mb-3"
  >
    Your Signature
  </label>
  <SignatureCanvas
    id="signature-canvas"
    aria-labelledby="signature-label"
    aria-required="true"
    onSignatureChange={handleSignatureChange}
    width={400}
    height={150}
  />
</div>
```

## Resources

- [WCAG 2.1 Understanding Success Criterion 3.3.1](https://www.w3.org/WAI/WCAG21/Understanding/error-identification.html)
- [WCAG 2.1 Understanding Success Criterion 3.3.2](https://www.w3.org/WAI/WCAG21/Understanding/labels-or-instructions.html)
- [W3C: Labeling Controls](https://www.w3.org/WAI/tutorials/forms/labels/)
- [W3C: Form Validation](https://www.w3.org/WAI/tutorials/forms/validation/)
- [MDN: ARIA: alert role](https://developer.mozilla.org/en-US/docs/Web/Accessibility/ARIA/Roles/alert_role)

## Browser and Screen Reader Compatibility

| Browser | Screen Reader | Support |
|---------|--------------|---------|
| Chrome | NVDA (Windows) | ✅ Full |
| Firefox | NVDA (Windows) | ✅ Full |
| Edge | JAWS (Windows) | ✅ Full |
| Safari | VoiceOver (macOS) | ✅ Full |
| Safari | VoiceOver (iOS) | ✅ Full |
| Chrome | TalkBack (Android) | ✅ Full |

## Implementation Status

- ✅ Documentation created
- ✅ Audit completed
- ✅ Test suite created
- ✅ Pattern examples provided
- ⚠️ Minor improvements recommended for existing components

**Last Updated:** February 17, 2026
