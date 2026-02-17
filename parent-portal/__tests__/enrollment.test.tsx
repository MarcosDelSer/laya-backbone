/**
 * Unit tests for enrollment form components
 * Tests EnrollmentFormCard and ChildInfoSection components
 */

import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { EnrollmentFormCard } from '@/components/enrollment/EnrollmentFormCard'
import { ChildInfoSection } from '@/components/enrollment/ChildInfoSection'
import type { EnrollmentFormSummary, EnrollmentFormStatus } from '@/lib/types'

// ============================================================================
// Test Fixtures
// ============================================================================

const createMockForm = (
  overrides: Partial<EnrollmentFormSummary> = {}
): EnrollmentFormSummary => ({
  id: 'form-1',
  formNumber: 'ENR-2024-0001',
  status: 'Draft',
  version: 1,
  childFirstName: 'Emma',
  childLastName: 'Smith',
  childDateOfBirth: '2022-03-15',
  admissionDate: '2024-09-01',
  createdAt: '2024-01-15T10:30:00Z',
  updatedAt: '2024-01-16T14:20:00Z',
  createdByName: 'John Smith',
  ...overrides,
})

const createMockChildInfoData = () => ({
  childFirstName: '',
  childLastName: '',
  childDateOfBirth: '',
  childAddress: '',
  childCity: '',
  childPostalCode: '',
  languagesSpoken: '',
  admissionDate: '',
  notes: '',
})

// ============================================================================
// EnrollmentFormCard Tests
// ============================================================================

describe('EnrollmentFormCard Component', () => {
  const mockOnView = vi.fn()
  const mockOnEdit = vi.fn()
  const mockOnContinue = vi.fn()

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('Basic Rendering', () => {
    it('renders child name correctly', () => {
      const form = createMockForm()
      render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      expect(screen.getByText('Emma Smith')).toBeInTheDocument()
    })

    it('renders form number correctly', () => {
      const form = createMockForm()
      render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      expect(screen.getByText(/Form #ENR-2024-0001/)).toBeInTheDocument()
    })

    it('renders date of birth', () => {
      const form = createMockForm()
      render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      expect(screen.getByText(/DOB:/)).toBeInTheDocument()
    })

    it('displays version number when version > 1', () => {
      const form = createMockForm({ version: 3 })
      render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      expect(screen.getByText('(v3)')).toBeInTheDocument()
    })

    it('does not display version number for version 1', () => {
      const form = createMockForm({ version: 1 })
      render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      expect(screen.queryByText('(v1)')).not.toBeInTheDocument()
    })

    it('displays admission date when provided', () => {
      const form = createMockForm({ admissionDate: '2024-09-01' })
      render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      expect(screen.getByText(/Admission:/)).toBeInTheDocument()
    })

    it('displays created by information', () => {
      const form = createMockForm({ createdByName: 'John Smith' })
      render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      expect(screen.getByText(/by John Smith/)).toBeInTheDocument()
    })
  })

  describe('Status Badge Rendering', () => {
    const statuses: EnrollmentFormStatus[] = ['Draft', 'Submitted', 'Approved', 'Rejected', 'Expired']

    statuses.forEach((status) => {
      it(`renders ${status} status badge correctly`, () => {
        const form = createMockForm({ status })
        render(<EnrollmentFormCard form={form} onView={mockOnView} />)
        expect(screen.getByText(status)).toBeInTheDocument()
      })
    })

    it('applies badge-default class for Draft status', () => {
      const form = createMockForm({ status: 'Draft' })
      const { container } = render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      const badge = container.querySelector('.badge')
      expect(badge).toHaveClass('badge-default')
    })

    it('applies badge-info class for Submitted status', () => {
      const form = createMockForm({ status: 'Submitted' })
      const { container } = render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      const badge = container.querySelector('.badge')
      expect(badge).toHaveClass('badge-info')
    })

    it('applies badge-success class for Approved status', () => {
      const form = createMockForm({ status: 'Approved' })
      const { container } = render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      const badge = container.querySelector('.badge')
      expect(badge).toHaveClass('badge-success')
    })

    it('applies badge-error class for Rejected status', () => {
      const form = createMockForm({ status: 'Rejected' })
      const { container } = render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      const badge = container.querySelector('.badge')
      expect(badge).toHaveClass('badge-error')
    })

    it('applies badge-warning class for Expired status', () => {
      const form = createMockForm({ status: 'Expired' })
      const { container } = render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      const badge = container.querySelector('.badge')
      expect(badge).toHaveClass('badge-warning')
    })
  })

  describe('Action Buttons', () => {
    it('always shows View Details button', () => {
      const form = createMockForm()
      render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      expect(screen.getByText('View Details')).toBeInTheDocument()
    })

    it('calls onView when View Details is clicked', () => {
      const form = createMockForm()
      render(<EnrollmentFormCard form={form} onView={mockOnView} />)
      fireEvent.click(screen.getByText('View Details'))
      expect(mockOnView).toHaveBeenCalledWith('form-1')
    })

    it('shows Continue button for Draft forms when onContinue is provided', () => {
      const form = createMockForm({ status: 'Draft' })
      render(
        <EnrollmentFormCard
          form={form}
          onView={mockOnView}
          onContinue={mockOnContinue}
        />
      )
      expect(screen.getByText('Continue')).toBeInTheDocument()
    })

    it('calls onContinue when Continue is clicked', () => {
      const form = createMockForm({ status: 'Draft' })
      render(
        <EnrollmentFormCard
          form={form}
          onView={mockOnView}
          onContinue={mockOnContinue}
        />
      )
      fireEvent.click(screen.getByText('Continue'))
      expect(mockOnContinue).toHaveBeenCalledWith('form-1')
    })

    it('does not show Continue button for non-Draft forms', () => {
      const form = createMockForm({ status: 'Submitted' })
      render(
        <EnrollmentFormCard
          form={form}
          onView={mockOnView}
          onContinue={mockOnContinue}
        />
      )
      expect(screen.queryByText('Continue')).not.toBeInTheDocument()
    })

    it('shows Edit button for Rejected forms when onEdit is provided', () => {
      const form = createMockForm({ status: 'Rejected' })
      render(
        <EnrollmentFormCard
          form={form}
          onView={mockOnView}
          onEdit={mockOnEdit}
        />
      )
      expect(screen.getByText('Edit')).toBeInTheDocument()
    })

    it('calls onEdit when Edit is clicked', () => {
      const form = createMockForm({ status: 'Rejected' })
      render(
        <EnrollmentFormCard
          form={form}
          onView={mockOnView}
          onEdit={mockOnEdit}
        />
      )
      fireEvent.click(screen.getByText('Edit'))
      expect(mockOnEdit).toHaveBeenCalledWith('form-1')
    })

    it('does not show Edit button for Approved forms', () => {
      const form = createMockForm({ status: 'Approved' })
      render(
        <EnrollmentFormCard
          form={form}
          onView={mockOnView}
          onEdit={mockOnEdit}
        />
      )
      expect(screen.queryByText('Edit')).not.toBeInTheDocument()
    })

    it('does not show Edit button for Submitted forms', () => {
      const form = createMockForm({ status: 'Submitted' })
      render(
        <EnrollmentFormCard
          form={form}
          onView={mockOnView}
          onEdit={mockOnEdit}
        />
      )
      expect(screen.queryByText('Edit')).not.toBeInTheDocument()
    })

    it('does not show Edit button for Expired forms', () => {
      const form = createMockForm({ status: 'Expired' })
      render(
        <EnrollmentFormCard
          form={form}
          onView={mockOnView}
          onEdit={mockOnEdit}
        />
      )
      expect(screen.queryByText('Edit')).not.toBeInTheDocument()
    })

    it('shows Continue instead of Edit for Draft forms', () => {
      const form = createMockForm({ status: 'Draft' })
      render(
        <EnrollmentFormCard
          form={form}
          onView={mockOnView}
          onEdit={mockOnEdit}
          onContinue={mockOnContinue}
        />
      )
      expect(screen.getByText('Continue')).toBeInTheDocument()
      expect(screen.queryByText('Edit')).not.toBeInTheDocument()
    })
  })

  describe('Icon Rendering', () => {
    it('renders an SVG icon for each status', () => {
      const statuses: EnrollmentFormStatus[] = ['Draft', 'Submitted', 'Approved', 'Rejected', 'Expired']

      statuses.forEach((status) => {
        const form = createMockForm({ status })
        const { container } = render(<EnrollmentFormCard form={form} onView={mockOnView} />)
        const svgs = container.querySelectorAll('svg')
        expect(svgs.length).toBeGreaterThan(0)
      })
    })
  })
})

// ============================================================================
// ChildInfoSection Tests
// ============================================================================

describe('ChildInfoSection Component', () => {
  const mockOnChange = vi.fn()

  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('Basic Rendering', () => {
    it('renders section header', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )
      expect(screen.getByText('Child Identification')).toBeInTheDocument()
    })

    it('renders required field indicators', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )
      expect(screen.getByText('First Name')).toBeInTheDocument()
      expect(screen.getByText('Last Name')).toBeInTheDocument()
      expect(screen.getByText('Date of Birth')).toBeInTheDocument()
    })

    it('renders all input fields', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )
      expect(screen.getByPlaceholderText("Enter child's first name")).toBeInTheDocument()
      expect(screen.getByPlaceholderText("Enter child's last name")).toBeInTheDocument()
    })

    it('renders address section', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )
      expect(screen.getByText('Address Information')).toBeInTheDocument()
      expect(screen.getByText('Street Address')).toBeInTheDocument()
      expect(screen.getByText('City')).toBeInTheDocument()
      expect(screen.getByText('Postal Code')).toBeInTheDocument()
    })

    it('renders language section', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )
      expect(screen.getByText('Language Information')).toBeInTheDocument()
      expect(screen.getByText('Languages Spoken at Home')).toBeInTheDocument()
    })

    it('renders notes section', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )
      expect(screen.getByText('Additional Notes')).toBeInTheDocument()
      expect(screen.getByText('Notes or Comments')).toBeInTheDocument()
    })

    it('renders info notice', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )
      expect(screen.getByText(/This information will be used to identify your child/)).toBeInTheDocument()
    })
  })

  describe('Data Binding', () => {
    it('displays existing data in form fields', () => {
      const data = {
        childFirstName: 'Emma',
        childLastName: 'Smith',
        childDateOfBirth: '2022-03-15',
        childAddress: '123 Main St',
        childCity: 'Montreal',
        childPostalCode: 'H1A 2B3',
        languagesSpoken: 'French, English',
        admissionDate: '2024-09-01',
        notes: 'Some notes',
      }

      render(<ChildInfoSection data={data} onChange={mockOnChange} />)

      expect(screen.getByDisplayValue('Emma')).toBeInTheDocument()
      expect(screen.getByDisplayValue('Smith')).toBeInTheDocument()
      expect(screen.getByDisplayValue('2022-03-15')).toBeInTheDocument()
      expect(screen.getByDisplayValue('123 Main St')).toBeInTheDocument()
      expect(screen.getByDisplayValue('Montreal')).toBeInTheDocument()
      expect(screen.getByDisplayValue('H1A 2B3')).toBeInTheDocument()
      expect(screen.getByDisplayValue('French, English')).toBeInTheDocument()
      expect(screen.getByDisplayValue('2024-09-01')).toBeInTheDocument()
      expect(screen.getByDisplayValue('Some notes')).toBeInTheDocument()
    })
  })

  describe('Input Changes', () => {
    it('calls onChange when first name is changed', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )

      const input = screen.getByPlaceholderText("Enter child's first name")
      fireEvent.change(input, { target: { value: 'Emma' } })

      expect(mockOnChange).toHaveBeenCalledWith({ childFirstName: 'Emma' })
    })

    it('calls onChange when last name is changed', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )

      const input = screen.getByPlaceholderText("Enter child's last name")
      fireEvent.change(input, { target: { value: 'Smith' } })

      expect(mockOnChange).toHaveBeenCalledWith({ childLastName: 'Smith' })
    })

    it('calls onChange when address is changed', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )

      const input = screen.getByPlaceholderText('123 Main Street, Apt 4B')
      fireEvent.change(input, { target: { value: '456 Oak Ave' } })

      expect(mockOnChange).toHaveBeenCalledWith({ childAddress: '456 Oak Ave' })
    })

    it('calls onChange when city is changed', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )

      const input = screen.getByPlaceholderText('Montreal')
      fireEvent.change(input, { target: { value: 'Quebec City' } })

      expect(mockOnChange).toHaveBeenCalledWith({ childCity: 'Quebec City' })
    })

    it('calls onChange when languages is changed', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )

      const input = screen.getByPlaceholderText('French, English')
      fireEvent.change(input, { target: { value: 'French, Spanish' } })

      expect(mockOnChange).toHaveBeenCalledWith({ languagesSpoken: 'French, Spanish' })
    })
  })

  describe('Validation Errors', () => {
    it('displays validation errors when provided', () => {
      const errors = ['First name is required', 'Date of birth is required']

      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
          errors={errors}
        />
      )

      expect(screen.getByText('First name is required')).toBeInTheDocument()
      expect(screen.getByText('Date of birth is required')).toBeInTheDocument()
    })

    it('displays error section header when errors exist', () => {
      const errors = ['First name is required']

      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
          errors={errors}
        />
      )

      expect(screen.getByText('Please correct the following:')).toBeInTheDocument()
    })

    it('does not display error section when no errors', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
          errors={[]}
        />
      )

      expect(screen.queryByText('Please correct the following:')).not.toBeInTheDocument()
    })
  })

  describe('Disabled State', () => {
    it('disables all inputs when disabled prop is true', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
          disabled={true}
        />
      )

      const firstNameInput = screen.getByPlaceholderText("Enter child's first name")
      const lastNameInput = screen.getByPlaceholderText("Enter child's last name")

      expect(firstNameInput).toBeDisabled()
      expect(lastNameInput).toBeDisabled()
    })

    it('enables all inputs when disabled prop is false', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
          disabled={false}
        />
      )

      const firstNameInput = screen.getByPlaceholderText("Enter child's first name")
      const lastNameInput = screen.getByPlaceholderText("Enter child's last name")

      expect(firstNameInput).not.toBeDisabled()
      expect(lastNameInput).not.toBeDisabled()
    })
  })

  describe('Postal Code Format', () => {
    it('shows postal code format hint', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )

      expect(screen.getByText(/Canadian postal code format/)).toBeInTheDocument()
    })

    it('has maxLength attribute on postal code input', () => {
      render(
        <ChildInfoSection
          data={createMockChildInfoData()}
          onChange={mockOnChange}
        />
      )

      const postalCodeInput = screen.getByPlaceholderText('H1A 2B3')
      expect(postalCodeInput).toHaveAttribute('maxLength', '7')
    })
  })
})

// ============================================================================
// Age Calculation Utility Tests (via component)
// ============================================================================

describe('Age Calculation', () => {
  const mockOnView = vi.fn()

  it('displays calculated age for a child', () => {
    // Create a form with a child born 2 years ago
    const twoYearsAgo = new Date()
    twoYearsAgo.setFullYear(twoYearsAgo.getFullYear() - 2)

    const form = createMockForm({
      childDateOfBirth: twoYearsAgo.toISOString().split('T')[0],
    })

    render(<EnrollmentFormCard form={form} onView={mockOnView} />)

    // Should show something like "2 years old" or "2y 0m old"
    expect(screen.getByText(/old/)).toBeInTheDocument()
  })
})
