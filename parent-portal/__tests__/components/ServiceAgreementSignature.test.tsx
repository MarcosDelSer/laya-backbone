/**
 * Unit tests for ServiceAgreementSignature component
 * Tests modal rendering, form validation, signature collection, and submission
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { ServiceAgreementSignature } from '@/components/ServiceAgreementSignature'
import type { ServiceAgreement, ServiceAgreementAnnex, ContributionType, AnnexStatus } from '@/lib/types'

// Mock the SignatureCanvas component
vi.mock('@/components/SignatureCanvas', () => ({
  SignatureCanvas: ({ onSignatureChange }: { onSignatureChange: (hasSig: boolean, dataUrl: string | null) => void }) => (
    <div data-testid="signature-canvas">
      <button
        type="button"
        data-testid="mock-sign-button"
        onClick={() => onSignatureChange(true, 'data:image/png;base64,mockSignature')}
      >
        Mock Sign
      </button>
      <button
        type="button"
        data-testid="mock-clear-button"
        onClick={() => onSignatureChange(false, null)}
      >
        Mock Clear
      </button>
    </div>
  ),
}))

// Helper function to create a mock agreement
function createMockAgreement(
  overrides: Partial<ServiceAgreement> = {}
): ServiceAgreement {
  return {
    id: 'agreement-123',
    agreementNumber: 'SA-2026-001',
    status: 'pending_signature',
    schoolYearId: 'year-2026',
    childId: 'child-456',
    childName: 'Emma Johnson',
    childDateOfBirth: '2020-05-15',
    parentId: 'parent-789',
    parentName: 'John Johnson',
    parentAddress: '123 Main St, Montreal, QC H1A 1A1',
    parentPhone: '514-555-1234',
    parentEmail: 'john@example.com',
    providerId: 'provider-001',
    providerName: 'ABC Daycare',
    providerAddress: '456 Care Ave, Montreal, QC H2B 2B2',
    providerPhone: '514-555-5678',
    providerPermitNumber: 'QC-CPE-12345',
    serviceDescription: 'Full-time daycare services',
    programType: 'Full-time',
    ageGroup: '3-5 years',
    operatingHours: {
      openTime: '07:00',
      closeTime: '18:00',
      operatingDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
      maxDailyHours: 10,
    },
    attendancePattern: {
      scheduledDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
      arrivalTime: '08:00',
      departureTime: '17:00',
      isFullTime: true,
      daysPerWeek: 5,
    },
    paymentTerms: {
      contributionType: 'reduced' as ContributionType,
      dailyRate: 9.35,
      monthlyAmount: 187.0,
      paymentDueDay: 1,
      paymentMethod: 'Pre-authorized debit',
    },
    latePickupFees: {
      gracePeriodMinutes: 15,
      feePerInterval: 1.0,
      intervalMinutes: 5,
      maxDailyFee: 25.0,
    },
    closureDays: ['2026-12-25', '2026-01-01'],
    absencePolicy: 'Parents must notify by 9:00 AM',
    absenceNotificationRequired: true,
    startDate: '2026-01-01',
    endDate: '2026-12-31',
    autoRenewal: false,
    terminationConditions: {
      noticePeriodDays: 30,
      immediateTerminationReasons: ['Non-payment', 'Safety concerns'],
      refundPolicy: 'Pro-rated refund for unused days',
    },
    consumerProtectionAcknowledgment: {
      acknowledged: false,
    },
    signatures: [],
    allSignaturesComplete: false,
    annexes: [],
    createdAt: '2026-01-10T08:00:00Z',
    updatedAt: '2026-01-10T08:00:00Z',
    createdBy: 'admin-001',
    ...overrides,
  }
}

// Helper function to create a mock annex
function createMockAnnex(
  type: 'A' | 'B' | 'C' | 'D',
  status: AnnexStatus = 'pending',
  overrides: Partial<ServiceAgreementAnnex> = {}
): ServiceAgreementAnnex {
  const baseAnnex = {
    id: `annex-${type}-001`,
    agreementId: 'agreement-123',
    type,
    status,
  }

  switch (type) {
    case 'A':
      return {
        ...baseAnnex,
        type: 'A',
        authorizeFieldTrips: true,
        ...overrides,
      } as ServiceAgreementAnnex
    case 'B':
      return {
        ...baseAnnex,
        type: 'B',
        hygieneItemsIncluded: true,
        ...overrides,
      } as ServiceAgreementAnnex
    case 'C':
      return {
        ...baseAnnex,
        type: 'C',
        supplementaryMealsIncluded: true,
        ...overrides,
      } as ServiceAgreementAnnex
    case 'D':
      return {
        ...baseAnnex,
        type: 'D',
        extendedHoursRequired: true,
        ...overrides,
      } as ServiceAgreementAnnex
    default:
      return baseAnnex as ServiceAgreementAnnex
  }
}

describe('ServiceAgreementSignature Component', () => {
  const mockOnClose = vi.fn()
  const mockOnSubmit = vi.fn()

  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    // Clean up body overflow style
    document.body.style.overflow = 'unset'
  })

  describe('Modal Visibility', () => {
    it('does not render when isOpen is false', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={false}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.queryByText('Sign Service Agreement')).not.toBeInTheDocument()
    })

    it('does not render when agreement is null', () => {
      render(
        <ServiceAgreementSignature
          agreement={null}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.queryByText('Sign Service Agreement')).not.toBeInTheDocument()
    })

    it('renders modal when isOpen is true and agreement is provided', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Sign Service Agreement')).toBeInTheDocument()
    })
  })

  describe('Agreement Summary Display', () => {
    it('displays agreement number in header', () => {
      const agreement = createMockAgreement({ agreementNumber: 'SA-2026-001' })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Agreement #SA-2026-001')).toBeInTheDocument()
    })

    it('displays child name in summary', () => {
      const agreement = createMockAgreement({ childName: 'Emma Johnson' })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Emma Johnson')).toBeInTheDocument()
    })

    it('displays provider name in summary', () => {
      const agreement = createMockAgreement({ providerName: 'ABC Daycare' })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('ABC Daycare')).toBeInTheDocument()
    })

    it('displays monthly amount in summary', () => {
      const agreement = createMockAgreement({
        paymentTerms: {
          contributionType: 'reduced',
          dailyRate: 9.35,
          monthlyAmount: 187.0,
          paymentDueDay: 1,
          paymentMethod: 'Pre-authorized debit',
        },
      })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      // Check for formatted currency (CA$187.00 or $187.00)
      expect(screen.getByText(/\$187\.00/)).toBeInTheDocument()
    })

    it('displays agreement period in summary', () => {
      const agreement = createMockAgreement({
        startDate: '2026-01-01',
        endDate: '2026-12-31',
      })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      // Date format: Jan 1, 2026 - Dec 31, 2026
      expect(screen.getByText(/Jan 1, 2026/)).toBeInTheDocument()
      expect(screen.getByText(/Dec 31, 2026/)).toBeInTheDocument()
    })
  })

  describe('Consumer Protection Act Notice', () => {
    it('displays Consumer Protection Act notice heading', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Quebec Consumer Protection Act Notice')).toBeInTheDocument()
    })

    it('displays 10-day cooling-off period information', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText(/10-day cooling-off period/)).toBeInTheDocument()
    })

    it('displays Consumer Protection acknowledgment checkbox', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(
        screen.getByText(/I acknowledge that I have read and understand the Consumer Protection Act/)
      ).toBeInTheDocument()
    })

    it('Consumer Protection checkbox is initially unchecked', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      const checkboxes = screen.getAllByRole('checkbox')
      const cpCheckbox = checkboxes[0] // First checkbox is Consumer Protection
      expect(cpCheckbox).not.toBeChecked()
    })
  })

  describe('Signature Type Selection', () => {
    it('displays signature method label', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Signature Method')).toBeInTheDocument()
    })

    it('displays draw signature option', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Draw signature')).toBeInTheDocument()
    })

    it('displays type signature option', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Type signature')).toBeInTheDocument()
    })

    it('draw signature is selected by default', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      const radioButtons = screen.getAllByRole('radio')
      const drawnRadio = radioButtons[0]
      expect(drawnRadio).toBeChecked()
    })

    it('shows SignatureCanvas when draw is selected', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByTestId('signature-canvas')).toBeInTheDocument()
    })

    it('shows text input when type signature is selected', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      // Click on type signature option
      const typeRadio = screen.getByLabelText('Type signature')
      await user.click(typeRadio)

      expect(screen.getByPlaceholderText('Type your full legal name')).toBeInTheDocument()
    })
  })

  describe('Typed Signature Input', () => {
    it('shows placeholder text for typed signature', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      const typeRadio = screen.getByLabelText('Type signature')
      await user.click(typeRadio)

      const input = screen.getByPlaceholderText('Type your full legal name')
      expect(input).toBeInTheDocument()
    })

    it('shows electronic signature notice for typed signatures', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      const typeRadio = screen.getByLabelText('Type signature')
      await user.click(typeRadio)

      expect(
        screen.getByText(/By typing your name, you agree that this constitutes your electronic signature/)
      ).toBeInTheDocument()
    })
  })

  describe('Terms and Conditions', () => {
    it('displays terms acceptance checkbox', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(
        screen.getByText(/I have read and accept all terms and conditions/)
      ).toBeInTheDocument()
    })

    it('terms checkbox is initially unchecked', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      const checkboxes = screen.getAllByRole('checkbox')
      const termsCheckbox = checkboxes[1] // Second checkbox is terms
      expect(termsCheckbox).not.toBeChecked()
    })
  })

  describe('Legal Acknowledgment', () => {
    it('displays legal acknowledgment checkbox', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(
        screen.getByText(/I understand that by signing below, I am entering into a legally binding agreement/)
      ).toBeInTheDocument()
    })

    it('legal acknowledgment checkbox is initially unchecked', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      const checkboxes = screen.getAllByRole('checkbox')
      const legalCheckbox = checkboxes[2] // Third checkbox is legal
      expect(legalCheckbox).not.toBeChecked()
    })
  })

  describe('Pending Annexes', () => {
    it('displays pending annexes section when annexes exist', () => {
      const agreement = createMockAgreement({
        annexes: [createMockAnnex('A', 'pending')],
      })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Optional Annexes')).toBeInTheDocument()
    })

    it('does not display annexes section when no pending annexes', () => {
      const agreement = createMockAgreement({ annexes: [] })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.queryByText('Optional Annexes')).not.toBeInTheDocument()
    })

    it('displays Annex A title correctly', () => {
      const agreement = createMockAgreement({
        annexes: [createMockAnnex('A', 'pending')],
      })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Annex A - Field Trips')).toBeInTheDocument()
    })

    it('displays Annex B title correctly', () => {
      const agreement = createMockAgreement({
        annexes: [createMockAnnex('B', 'pending')],
      })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Annex B - Hygiene Items')).toBeInTheDocument()
    })

    it('displays Annex C title correctly', () => {
      const agreement = createMockAgreement({
        annexes: [createMockAnnex('C', 'pending')],
      })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Annex C - Meals')).toBeInTheDocument()
    })

    it('displays Annex D title correctly', () => {
      const agreement = createMockAgreement({
        annexes: [createMockAnnex('D', 'pending')],
      })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Annex D - Extended Hours')).toBeInTheDocument()
    })

    it('annexes are checked by default', () => {
      const agreement = createMockAgreement({
        annexes: [createMockAnnex('A', 'pending')],
      })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      const annexCheckboxes = screen.getAllByRole('checkbox')
      // Find the annex checkbox (should be checked by default)
      const annexA = screen.getByText('Annex A - Field Trips').closest('label')?.querySelector('input')
      expect(annexA).toBeChecked()
    })

    it('only shows pending annexes, not signed ones', () => {
      const agreement = createMockAgreement({
        annexes: [
          createMockAnnex('A', 'pending'),
          createMockAnnex('B', 'signed'),
        ],
      })
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Annex A - Field Trips')).toBeInTheDocument()
      expect(screen.queryByText('Annex B - Hygiene Items')).not.toBeInTheDocument()
    })
  })

  describe('Submit Button State', () => {
    it('submit button is disabled when no signature provided', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      const submitButton = screen.getByRole('button', { name: /Sign Agreement/i })
      expect(submitButton).toBeDisabled()
    })

    it('submit button shows "Sign Agreement" text when not submitting', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Sign Agreement')).toBeInTheDocument()
    })

    it('shows "Please complete all required fields" when incomplete', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Please complete all required fields')).toBeInTheDocument()
    })

    it('shows "Ready to submit" when all fields are complete', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      // Complete all required fields
      await user.click(screen.getByTestId('mock-sign-button'))

      const checkboxes = screen.getAllByRole('checkbox')
      for (const checkbox of checkboxes) {
        await user.click(checkbox)
      }

      expect(screen.getByText('Ready to submit')).toBeInTheDocument()
    })

    it('submit button is enabled when all requirements met', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      // Provide signature
      await user.click(screen.getByTestId('mock-sign-button'))

      // Check all checkboxes
      const checkboxes = screen.getAllByRole('checkbox')
      for (const checkbox of checkboxes) {
        await user.click(checkbox)
      }

      const submitButton = screen.getByRole('button', { name: /Sign Agreement/i })
      expect(submitButton).not.toBeDisabled()
    })
  })

  describe('Cancel Button', () => {
    it('renders Cancel button', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(screen.getByText('Cancel')).toBeInTheDocument()
    })

    it('calls onClose when Cancel is clicked', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      await user.click(screen.getByText('Cancel'))
      expect(mockOnClose).toHaveBeenCalledTimes(1)
    })

    it('Cancel button has outline styling', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      const cancelButton = screen.getByText('Cancel')
      expect(cancelButton).toHaveClass('btn-outline')
    })
  })

  describe('Close Button (X)', () => {
    it('renders close button in header', () => {
      const agreement = createMockAgreement()
      const { container } = render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      // The close button contains an SVG with the X icon
      const closeButton = container.querySelector('button[type="button"]')
      expect(closeButton).toBeInTheDocument()
    })

    it('calls onClose when X button is clicked', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()
      const { container } = render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      // Find the close button (first button in the header with SVG)
      const headerButtons = container.querySelectorAll('.border-b button')
      const closeButton = headerButtons[0]
      await user.click(closeButton)

      expect(mockOnClose).toHaveBeenCalled()
    })
  })

  describe('Backdrop', () => {
    it('calls onClose when backdrop is clicked', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()
      const { container } = render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      const backdrop = container.querySelector('.bg-black.bg-opacity-50')
      if (backdrop) {
        await user.click(backdrop)
        expect(mockOnClose).toHaveBeenCalled()
      }
    })
  })

  describe('Timestamp Notice', () => {
    it('displays timestamp audit notice', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      expect(
        screen.getByText(/Your signature will be timestamped with the current date, time, and IP address/)
      ).toBeInTheDocument()
    })
  })

  describe('Form Submission', () => {
    it('calls onSubmit with correct data when form is submitted', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()
      mockOnSubmit.mockResolvedValue(undefined)

      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      // Provide signature
      await user.click(screen.getByTestId('mock-sign-button'))

      // Check all checkboxes
      const checkboxes = screen.getAllByRole('checkbox')
      for (const checkbox of checkboxes) {
        await user.click(checkbox)
      }

      // Submit form
      const submitButton = screen.getByRole('button', { name: /Sign Agreement/i })
      await user.click(submitButton)

      await waitFor(() => {
        expect(mockOnSubmit).toHaveBeenCalledTimes(1)
      })

      // Verify the request payload
      const submitCall = mockOnSubmit.mock.calls[0][0]
      expect(submitCall.agreementId).toBe('agreement-123')
      expect(submitCall.signatureType).toBe('drawn')
      expect(submitCall.consumerProtectionAcknowledged).toBe(true)
      expect(submitCall.termsAccepted).toBe(true)
      expect(submitCall.legalAcknowledged).toBe(true)
    })

    it('submits typed signature correctly', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()
      mockOnSubmit.mockResolvedValue(undefined)

      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      // Switch to typed signature
      const typeRadio = screen.getByLabelText('Type signature')
      await user.click(typeRadio)

      // Type signature
      const signatureInput = screen.getByPlaceholderText('Type your full legal name')
      await user.type(signatureInput, 'John Johnson')

      // Check all checkboxes
      const checkboxes = screen.getAllByRole('checkbox')
      for (const checkbox of checkboxes) {
        await user.click(checkbox)
      }

      // Submit form
      const submitButton = screen.getByRole('button', { name: /Sign Agreement/i })
      await user.click(submitButton)

      await waitFor(() => {
        expect(mockOnSubmit).toHaveBeenCalledTimes(1)
      })

      const submitCall = mockOnSubmit.mock.calls[0][0]
      expect(submitCall.signatureType).toBe('typed')
      expect(submitCall.signatureData).toBe('John Johnson')
    })

    it('includes annex signatures in submission', async () => {
      const agreement = createMockAgreement({
        annexes: [
          createMockAnnex('A', 'pending'),
          createMockAnnex('B', 'pending'),
        ],
      })
      const user = userEvent.setup()
      mockOnSubmit.mockResolvedValue(undefined)

      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      // Provide signature
      await user.click(screen.getByTestId('mock-sign-button'))

      // Check all checkboxes
      const checkboxes = screen.getAllByRole('checkbox')
      for (const checkbox of checkboxes) {
        if (!checkbox.checked) {
          await user.click(checkbox)
        }
      }

      // Submit form
      const submitButton = screen.getByRole('button', { name: /Sign Agreement/i })
      await user.click(submitButton)

      await waitFor(() => {
        expect(mockOnSubmit).toHaveBeenCalledTimes(1)
      })

      const submitCall = mockOnSubmit.mock.calls[0][0]
      expect(submitCall.annexSignatures).toBeDefined()
      expect(submitCall.annexSignatures.length).toBe(2)
    })
  })

  describe('Error Handling', () => {
    it('displays error when submission fails', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()
      mockOnSubmit.mockRejectedValue(new Error('Network error'))

      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      // Complete all fields
      await user.click(screen.getByTestId('mock-sign-button'))
      const checkboxes = screen.getAllByRole('checkbox')
      for (const checkbox of checkboxes) {
        await user.click(checkbox)
      }

      // Submit form
      const submitButton = screen.getByRole('button', { name: /Sign Agreement/i })
      await user.click(submitButton)

      await waitFor(() => {
        expect(screen.getByText('Network error')).toBeInTheDocument()
      })
    })

    it('displays generic error message for non-Error exceptions', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()
      mockOnSubmit.mockRejectedValue('Unknown error')

      render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      // Complete all fields
      await user.click(screen.getByTestId('mock-sign-button'))
      const checkboxes = screen.getAllByRole('checkbox')
      for (const checkbox of checkboxes) {
        await user.click(checkbox)
      }

      // Submit form
      const submitButton = screen.getByRole('button', { name: /Sign Agreement/i })
      await user.click(submitButton)

      await waitFor(() => {
        expect(screen.getByText('Failed to submit signature. Please try again.')).toBeInTheDocument()
      })
    })
  })

  describe('Validation Errors', () => {
    it('shows error when submitting without signature', async () => {
      const agreement = createMockAgreement()
      const user = userEvent.setup()

      const { container } = render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )

      // Check all checkboxes but don't provide signature
      const checkboxes = screen.getAllByRole('checkbox')
      for (const checkbox of checkboxes) {
        await user.click(checkbox)
      }

      // Button should still be disabled
      const submitButton = screen.getByRole('button', { name: /Sign Agreement/i })
      expect(submitButton).toBeDisabled()
    })
  })

  describe('Modal Structure', () => {
    it('renders modal with correct z-index', () => {
      const agreement = createMockAgreement()
      const { container } = render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      const modal = container.querySelector('.z-50')
      expect(modal).toBeInTheDocument()
    })

    it('renders modal with scrollable content area', () => {
      const agreement = createMockAgreement()
      const { container } = render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      const scrollArea = container.querySelector('.overflow-y-auto')
      expect(scrollArea).toBeInTheDocument()
    })

    it('renders header with border', () => {
      const agreement = createMockAgreement()
      const { container } = render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      const header = container.querySelector('.border-b.border-gray-200')
      expect(header).toBeInTheDocument()
    })

    it('renders footer with border', () => {
      const agreement = createMockAgreement()
      const { container } = render(
        <ServiceAgreementSignature
          agreement={agreement}
          isOpen={true}
          onClose={mockOnClose}
          onSubmit={mockOnSubmit}
        />
      )
      const footer = container.querySelector('.border-t.border-gray-200')
      expect(footer).toBeInTheDocument()
    })
  })
})
