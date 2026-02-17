/**
 * Form Labels and Error Messages - Accessibility Tests
 *
 * Tests WCAG 2.1 AA compliance for form labels and error messages:
 * - Success Criterion 1.3.1: Info and Relationships
 * - Success Criterion 3.3.1: Error Identification
 * - Success Criterion 3.3.2: Labels or Instructions
 * - Success Criterion 4.1.2: Name, Role, Value
 */

import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import '@testing-library/jest-dom';

// Mock components for testing patterns
interface FormFieldProps {
  label: string;
  id: string;
  type?: string;
  error?: string;
  required?: boolean;
  helpText?: string;
  value?: string;
  onChange?: (e: React.ChangeEvent<HTMLInputElement>) => void;
}

function FormField({
  label,
  id,
  type = 'text',
  error,
  required,
  helpText,
  value,
  onChange,
}: FormFieldProps) {
  const errorId = `${id}-error`;
  const helpId = `${id}-help`;
  const describedBy = [error ? errorId : null, helpText ? helpId : null]
    .filter(Boolean)
    .join(' ');

  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-gray-700">
        {label}
        {required && (
          <span className="text-red-600 ml-1" aria-label="required">
            *
          </span>
        )}
      </label>

      <input
        type={type}
        id={id}
        name={id}
        value={value}
        onChange={onChange}
        required={required}
        aria-required={required}
        aria-invalid={!!error}
        aria-describedby={describedBy || undefined}
        className={`mt-1 block w-full rounded-md ${
          error
            ? 'border-red-500 focus:ring-red-500'
            : 'border-gray-300 focus:ring-primary'
        }`}
      />

      {error && (
        <p id={errorId} className="mt-1 text-sm text-red-600" role="alert">
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

interface FormErrorSummaryProps {
  errors: Array<{ field: string; message: string }>;
}

function FormErrorSummary({ errors }: FormErrorSummaryProps) {
  if (errors.length === 0) return null;

  return (
    <div
      role="alert"
      aria-labelledby="error-summary-title"
      className="mb-6 rounded-md bg-red-50 border border-red-400 p-4"
    >
      <div className="flex">
        <div className="ml-3">
          <h3 id="error-summary-title" className="text-sm font-medium text-red-800">
            There {errors.length === 1 ? 'is' : 'are'} {errors.length} error
            {errors.length !== 1 ? 's' : ''} with your submission
          </h3>
          <div className="mt-2 text-sm text-red-700">
            <ul className="list-disc list-inside space-y-1">
              {errors.map((error, index) => (
                <li key={index}>
                  <a href={`#${error.field}`} className="font-medium underline">
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

describe('Form Labels - WCAG 3.3.2 (Labels or Instructions)', () => {
  describe('Basic Label Association', () => {
    it('should associate label with input using htmlFor and id', () => {
      render(<FormField label="Email Address" id="email" type="email" />);

      const input = screen.getByLabelText('Email Address');
      expect(input).toBeInTheDocument();
      expect(input).toHaveAttribute('type', 'email');
      expect(input).toHaveAttribute('id', 'email');
    });

    it('should support labels with special characters', () => {
      render(
        <FormField label="Parent's Full Name" id="parent-name" type="text" />
      );

      const input = screen.getByLabelText("Parent's Full Name");
      expect(input).toBeInTheDocument();
    });

    it('should work with multiple form fields', () => {
      const { container } = render(
        <form>
          <FormField label="First Name" id="first-name" />
          <FormField label="Last Name" id="last-name" />
          <FormField label="Email" id="email" type="email" />
        </form>
      );

      expect(screen.getByLabelText('First Name')).toBeInTheDocument();
      expect(screen.getByLabelText('Last Name')).toBeInTheDocument();
      expect(screen.getByLabelText('Email')).toBeInTheDocument();

      // Each input should have unique id
      const inputs = container.querySelectorAll('input');
      const ids = Array.from(inputs).map((input) => input.id);
      const uniqueIds = new Set(ids);
      expect(uniqueIds.size).toBe(ids.length);
    });
  });

  describe('Required Field Indicators', () => {
    it('should mark required fields with aria-required', () => {
      render(<FormField label="Email" id="email" required />);

      const input = screen.getByLabelText('Email');
      expect(input).toHaveAttribute('aria-required', 'true');
      expect(input).toHaveAttribute('required');
    });

    it('should include visual required indicator in label', () => {
      render(<FormField label="Email" id="email" required />);

      const requiredIndicator = screen.getByLabelText('required');
      expect(requiredIndicator).toBeInTheDocument();
      expect(requiredIndicator).toHaveTextContent('*');
    });

    it('should not mark optional fields as required', () => {
      render(<FormField label="Middle Name" id="middle-name" />);

      const input = screen.getByLabelText('Middle Name');
      expect(input).not.toHaveAttribute('aria-required');
      expect(input).not.toHaveAttribute('required');
    });
  });

  describe('Help Text', () => {
    it('should associate help text with input using aria-describedby', () => {
      render(
        <FormField
          label="Password"
          id="password"
          type="password"
          helpText="Must be at least 8 characters"
        />
      );

      const input = screen.getByLabelText('Password');
      const helpText = screen.getByText('Must be at least 8 characters');

      expect(helpText).toHaveAttribute('id', 'password-help');
      expect(input).toHaveAttribute('aria-describedby', 'password-help');
    });

    it('should not show help text when error is present', () => {
      const { rerender } = render(
        <FormField
          label="Email"
          id="email"
          helpText="We'll never share your email"
        />
      );

      expect(screen.getByText("We'll never share your email")).toBeInTheDocument();

      rerender(
        <FormField
          label="Email"
          id="email"
          helpText="We'll never share your email"
          error="Email is required"
        />
      );

      expect(screen.queryByText("We'll never share your email")).not.toBeInTheDocument();
      expect(screen.getByText('Email is required')).toBeInTheDocument();
    });
  });
});

describe('Error Messages - WCAG 3.3.1 (Error Identification)', () => {
  describe('Error Association', () => {
    it('should associate error message with input using aria-describedby', () => {
      render(
        <FormField
          label="Email"
          id="email"
          error="Email address is required"
        />
      );

      const input = screen.getByLabelText('Email');
      const errorMessage = screen.getByText('Email address is required');

      expect(errorMessage).toHaveAttribute('id', 'email-error');
      expect(input).toHaveAttribute('aria-describedby', 'email-error');
    });

    it('should mark invalid inputs with aria-invalid', () => {
      const { rerender } = render(<FormField label="Email" id="email" />);

      let input = screen.getByLabelText('Email');
      expect(input).toHaveAttribute('aria-invalid', 'false');

      rerender(
        <FormField label="Email" id="email" error="Invalid email format" />
      );

      input = screen.getByLabelText('Email');
      expect(input).toHaveAttribute('aria-invalid', 'true');
    });

    it('should use role="alert" for error messages', () => {
      render(
        <FormField
          label="Email"
          id="email"
          error="Email address is required"
        />
      );

      const errorMessage = screen.getByRole('alert');
      expect(errorMessage).toHaveTextContent('Email address is required');
    });
  });

  describe('Error Message Content', () => {
    it('should display specific, actionable error messages', () => {
      render(
        <FormField
          label="Password"
          id="password"
          error="Password must contain at least 8 characters"
        />
      );

      expect(
        screen.getByText('Password must contain at least 8 characters')
      ).toBeInTheDocument();
    });

    it('should update error message dynamically', () => {
      const { rerender } = render(
        <FormField label="Email" id="email" error="Email is required" />
      );

      expect(screen.getByText('Email is required')).toBeInTheDocument();

      rerender(
        <FormField
          label="Email"
          id="email"
          error="Please enter a valid email address"
        />
      );

      expect(screen.queryByText('Email is required')).not.toBeInTheDocument();
      expect(
        screen.getByText('Please enter a valid email address')
      ).toBeInTheDocument();
    });

    it('should clear error message when resolved', () => {
      const { rerender } = render(
        <FormField label="Email" id="email" error="Email is required" />
      );

      expect(screen.getByRole('alert')).toBeInTheDocument();

      rerender(<FormField label="Email" id="email" />);

      expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });
  });

  describe('Visual Error Indicators', () => {
    it('should apply error styling to invalid inputs', () => {
      render(
        <FormField label="Email" id="email" error="Email is required" />
      );

      const input = screen.getByLabelText('Email');
      expect(input.className).toContain('border-red-500');
    });

    it('should apply normal styling to valid inputs', () => {
      render(<FormField label="Email" id="email" />);

      const input = screen.getByLabelText('Email');
      expect(input.className).toContain('border-gray-300');
      expect(input.className).not.toContain('border-red-500');
    });
  });

  describe('Combined Help Text and Errors', () => {
    it('should describe input with both help text and error', () => {
      render(
        <FormField
          label="Password"
          id="password"
          helpText="Must be at least 8 characters"
          error="Password is too short"
        />
      );

      const input = screen.getByLabelText('Password');
      expect(input).toHaveAttribute('aria-describedby', 'password-error');
    });
  });
});

describe('Form Error Summary - WCAG 3.3.1', () => {
  describe('Error Summary Display', () => {
    it('should not display when there are no errors', () => {
      render(<FormErrorSummary errors={[]} />);

      expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    });

    it('should display summary with single error', () => {
      const errors = [{ field: 'email', message: 'Email is required' }];
      render(<FormErrorSummary errors={errors} />);

      expect(screen.getByRole('alert')).toBeInTheDocument();
      expect(screen.getByText(/There is 1 error/i)).toBeInTheDocument();
    });

    it('should display summary with multiple errors', () => {
      const errors = [
        { field: 'email', message: 'Email is required' },
        { field: 'password', message: 'Password is required' },
      ];
      render(<FormErrorSummary errors={errors} />);

      expect(screen.getByText(/There are 2 errors/i)).toBeInTheDocument();
    });

    it('should list all error messages', () => {
      const errors = [
        { field: 'email', message: 'Email is required' },
        { field: 'password', message: 'Password must be at least 8 characters' },
        { field: 'name', message: 'Full name is required' },
      ];
      render(<FormErrorSummary errors={errors} />);

      expect(screen.getByText('Email is required')).toBeInTheDocument();
      expect(
        screen.getByText('Password must be at least 8 characters')
      ).toBeInTheDocument();
      expect(screen.getByText('Full name is required')).toBeInTheDocument();
    });
  });

  describe('Error Summary Accessibility', () => {
    it('should use role="alert" for live announcement', () => {
      const errors = [{ field: 'email', message: 'Email is required' }];
      render(<FormErrorSummary errors={errors} />);

      const summary = screen.getByRole('alert');
      expect(summary).toBeInTheDocument();
    });

    it('should have accessible title with aria-labelledby', () => {
      const errors = [{ field: 'email', message: 'Email is required' }];
      render(<FormErrorSummary errors={errors} />);

      const summary = screen.getByRole('alert');
      expect(summary).toHaveAttribute('aria-labelledby', 'error-summary-title');

      const title = screen.getByText(/There is 1 error/i);
      expect(title).toHaveAttribute('id', 'error-summary-title');
    });

    it('should provide links to error fields', () => {
      const errors = [
        { field: 'email', message: 'Email is required' },
        { field: 'password', message: 'Password is required' },
      ];
      render(<FormErrorSummary errors={errors} />);

      const emailLink = screen.getByRole('link', { name: /Email is required/i });
      const passwordLink = screen.getByRole('link', {
        name: /Password is required/i,
      });

      expect(emailLink).toHaveAttribute('href', '#email');
      expect(passwordLink).toHaveAttribute('href', '#password');
    });
  });
});

describe('Keyboard Interaction - WCAG 2.1.1', () => {
  it('should allow keyboard navigation between form fields', async () => {
    const user = userEvent.setup();

    render(
      <form>
        <FormField label="First Name" id="first-name" />
        <FormField label="Last Name" id="last-name" />
        <FormField label="Email" id="email" type="email" />
      </form>
    );

    const firstName = screen.getByLabelText('First Name');
    const lastName = screen.getByLabelText('Last Name');
    const email = screen.getByLabelText('Email');

    firstName.focus();
    expect(firstName).toHaveFocus();

    await user.tab();
    expect(lastName).toHaveFocus();

    await user.tab();
    expect(email).toHaveFocus();
  });

  it('should support keyboard input in form fields', async () => {
    const user = userEvent.setup();
    const onChange = vi.fn();

    render(<FormField label="Name" id="name" onChange={onChange} />);

    const input = screen.getByLabelText('Name');
    await user.click(input);
    await user.type(input, 'John Doe');

    expect(onChange).toHaveBeenCalled();
  });
});

describe('Screen Reader Support - WCAG 4.1.2', () => {
  it('should provide complete information to screen readers', () => {
    render(
      <FormField
        label="Email Address"
        id="email"
        type="email"
        required
        helpText="We'll use this to send you updates"
        error="Please enter a valid email address"
      />
    );

    const input = screen.getByLabelText('Email Address');

    // Should have all necessary ARIA attributes
    expect(input).toHaveAttribute('type', 'email');
    expect(input).toHaveAttribute('id', 'email');
    expect(input).toHaveAttribute('aria-required', 'true');
    expect(input).toHaveAttribute('aria-invalid', 'true');
    expect(input).toHaveAttribute('aria-describedby', 'email-error');

    // Error message should be announced
    expect(screen.getByRole('alert')).toHaveTextContent(
      'Please enter a valid email address'
    );
  });

  it('should announce required fields to screen readers', () => {
    render(<FormField label="Password" id="password" type="password" required />);

    const input = screen.getByLabelText('Password');
    const requiredIndicator = screen.getByLabelText('required');

    expect(input).toHaveAttribute('aria-required', 'true');
    expect(requiredIndicator).toBeInTheDocument();
  });
});

describe('Real-World Form Validation Scenarios', () => {
  it('should handle email validation errors', () => {
    const validateEmail = (email: string) => {
      if (!email) return 'Email address is required';
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        return 'Please enter a valid email address (e.g., name@example.com)';
      }
      return '';
    };

    const { rerender } = render(<FormField label="Email" id="email" />);

    // Empty email
    rerender(<FormField label="Email" id="email" error={validateEmail('')} />);
    expect(screen.getByText('Email address is required')).toBeInTheDocument();

    // Invalid format
    rerender(
      <FormField label="Email" id="email" error={validateEmail('invalid')} />
    );
    expect(
      screen.getByText(/Please enter a valid email address/i)
    ).toBeInTheDocument();

    // Valid email
    rerender(
      <FormField
        label="Email"
        id="email"
        error={validateEmail('user@example.com')}
      />
    );
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
  });

  it('should handle password strength validation', () => {
    const validatePassword = (password: string) => {
      if (!password) return 'Password is required';
      if (password.length < 8) {
        return 'Password must contain at least 8 characters';
      }
      if (!/(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/.test(password)) {
        return 'Password must contain uppercase, lowercase, and numbers';
      }
      return '';
    };

    const { rerender } = render(
      <FormField label="Password" id="password" type="password" />
    );

    // Too short
    rerender(
      <FormField
        label="Password"
        id="password"
        type="password"
        error={validatePassword('short')}
      />
    );
    expect(
      screen.getByText('Password must contain at least 8 characters')
    ).toBeInTheDocument();

    // Missing complexity
    rerender(
      <FormField
        label="Password"
        id="password"
        type="password"
        error={validatePassword('password')}
      />
    );
    expect(
      screen.getByText('Password must contain uppercase, lowercase, and numbers')
    ).toBeInTheDocument();
  });
});
