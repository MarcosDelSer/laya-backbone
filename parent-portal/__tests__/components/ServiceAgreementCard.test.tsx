/**
 * Unit tests for ServiceAgreementCard component
 * Tests rendering, status badges, action buttons, and user interactions
 */

import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { ServiceAgreementCard } from '@/components/ServiceAgreementCard'
import type { ServiceAgreementSummary, ServiceAgreementStatus } from '@/lib/types'

// Helper function to create a mock agreement
function createMockAgreement(
  overrides: Partial<ServiceAgreementSummary> = {}
): ServiceAgreementSummary {
  return {
    id: 'agreement-123',
    agreementNumber: 'SA-2026-001',
    status: 'active',
    childId: 'child-456',
    childName: 'Emma Johnson',
    parentName: 'John Johnson',
    startDate: '2026-01-01',
    endDate: '2026-12-31',
    allSignaturesComplete: true,
    parentSignedAt: '2026-01-15T10:30:00Z',
    providerSignedAt: '2026-01-14T09:00:00Z',
    createdAt: '2026-01-10T08:00:00Z',
    updatedAt: '2026-01-15T10:30:00Z',
    ...overrides,
  }
}

describe('ServiceAgreementCard Component', () => {
  describe('Basic Rendering', () => {
    it('renders child name correctly', () => {
      const agreement = createMockAgreement({ childName: 'Emma Johnson' })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.getByText('Emma Johnson')).toBeInTheDocument()
    })

    it('renders agreement number with hash prefix', () => {
      const agreement = createMockAgreement({ agreementNumber: 'SA-2026-001' })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.getByText('#SA-2026-001')).toBeInTheDocument()
    })

    it('renders "Service Agreement" heading', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.getByText('Service Agreement')).toBeInTheDocument()
    })

    it('renders date range correctly', () => {
      const agreement = createMockAgreement({
        startDate: '2026-01-01',
        endDate: '2026-12-31',
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      // The component formats dates as 'short' month
      expect(screen.getByText(/Jan 1, 2026/)).toBeInTheDocument()
      expect(screen.getByText(/Dec 31, 2026/)).toBeInTheDocument()
    })

    it('renders agreement icon', () => {
      const agreement = createMockAgreement()
      const { container } = render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      const iconWrapper = container.querySelector('.bg-purple-100')
      expect(iconWrapper).toBeInTheDocument()
      const svg = iconWrapper?.querySelector('svg')
      expect(svg).toBeInTheDocument()
    })
  })

  describe('Status Badge Rendering', () => {
    const statusTestCases: Array<{
      status: ServiceAgreementStatus
      label: string
      badgeClass: string
    }> = [
      { status: 'active', label: 'Active', badgeClass: 'badge-success' },
      { status: 'pending_signature', label: 'Pending Signature', badgeClass: 'badge-warning' },
      { status: 'draft', label: 'Draft', badgeClass: 'badge-info' },
      { status: 'expired', label: 'Expired', badgeClass: 'badge-error' },
      { status: 'terminated', label: 'Terminated', badgeClass: 'badge-error' },
      { status: 'cancelled', label: 'Cancelled', badgeClass: 'badge-neutral' },
    ]

    statusTestCases.forEach(({ status, label, badgeClass }) => {
      it(`renders ${status} status with correct label "${label}"`, () => {
        const agreement = createMockAgreement({ status })
        render(
          <ServiceAgreementCard
            agreement={agreement}
            onView={vi.fn()}
            onSign={vi.fn()}
          />
        )
        expect(screen.getByText(label)).toBeInTheDocument()
      })

      it(`applies ${badgeClass} class for ${status} status`, () => {
        const agreement = createMockAgreement({ status })
        const { container } = render(
          <ServiceAgreementCard
            agreement={agreement}
            onView={vi.fn()}
            onSign={vi.fn()}
          />
        )
        const badge = container.querySelector(`.${badgeClass}`)
        expect(badge).toBeInTheDocument()
      })

      it(`includes an icon in status badge for ${status}`, () => {
        const agreement = createMockAgreement({ status })
        const { container } = render(
          <ServiceAgreementCard
            agreement={agreement}
            onView={vi.fn()}
            onSign={vi.fn()}
          />
        )
        const badge = container.querySelector('.badge')
        const svg = badge?.querySelector('svg')
        expect(svg).toBeInTheDocument()
      })
    })
  })

  describe('Signature Status Display', () => {
    it('displays signed date when agreement is signed by parent', () => {
      const agreement = createMockAgreement({
        parentSignedAt: '2026-01-15T10:30:00Z',
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.getByText(/Signed on/)).toBeInTheDocument()
    })

    it('does not display signed info when agreement is not signed', () => {
      const agreement = createMockAgreement({
        parentSignedAt: undefined,
        status: 'draft',
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.queryByText(/Signed on/)).not.toBeInTheDocument()
    })

    it('displays checkmark icon with signed status', () => {
      const agreement = createMockAgreement({
        parentSignedAt: '2026-01-15T10:30:00Z',
      })
      const { container } = render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      const signedText = container.querySelector('.text-green-600')
      expect(signedText).toBeInTheDocument()
      const svg = signedText?.querySelector('svg')
      expect(svg).toBeInTheDocument()
    })
  })

  describe('Pending Signature Notice', () => {
    it('displays "Your signature is required" for pending_signature without parent signature', () => {
      const agreement = createMockAgreement({
        status: 'pending_signature',
        parentSignedAt: undefined,
        allSignaturesComplete: false,
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.getByText('Your signature is required')).toBeInTheDocument()
    })

    it('does not display signature required notice when already signed', () => {
      const agreement = createMockAgreement({
        status: 'pending_signature',
        parentSignedAt: '2026-01-15T10:30:00Z',
        allSignaturesComplete: false,
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.queryByText('Your signature is required')).not.toBeInTheDocument()
    })

    it('does not display signature required notice for active status', () => {
      const agreement = createMockAgreement({
        status: 'active',
        parentSignedAt: '2026-01-15T10:30:00Z',
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.queryByText('Your signature is required')).not.toBeInTheDocument()
    })

    it('displays warning icon with pending signature notice', () => {
      const agreement = createMockAgreement({
        status: 'pending_signature',
        parentSignedAt: undefined,
      })
      const { container } = render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      const warningText = container.querySelector('.text-amber-600')
      expect(warningText).toBeInTheDocument()
      const svg = warningText?.querySelector('svg')
      expect(svg).toBeInTheDocument()
    })
  })

  describe('View Agreement Button', () => {
    it('always renders View Agreement button', () => {
      const agreement = createMockAgreement()
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.getByText('View Agreement')).toBeInTheDocument()
    })

    it('calls onView with agreement id when View Agreement is clicked', () => {
      const onView = vi.fn()
      const agreement = createMockAgreement({ id: 'agreement-456' })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={onView}
          onSign={vi.fn()}
        />
      )
      fireEvent.click(screen.getByText('View Agreement'))
      expect(onView).toHaveBeenCalledTimes(1)
      expect(onView).toHaveBeenCalledWith('agreement-456')
    })

    it('View Agreement button has correct styling', () => {
      const agreement = createMockAgreement()
      const { container } = render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      const viewButton = screen.getByText('View Agreement').closest('button')
      expect(viewButton).toHaveClass('btn')
      expect(viewButton).toHaveClass('btn-outline')
    })
  })

  describe('Sign Agreement Button', () => {
    it('renders Sign Agreement button when signature is required', () => {
      const agreement = createMockAgreement({
        status: 'pending_signature',
        parentSignedAt: undefined,
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.getByText('Sign Agreement')).toBeInTheDocument()
    })

    it('does not render Sign Agreement button when already signed', () => {
      const agreement = createMockAgreement({
        status: 'pending_signature',
        parentSignedAt: '2026-01-15T10:30:00Z',
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.queryByText('Sign Agreement')).not.toBeInTheDocument()
    })

    it('does not render Sign Agreement button for active status', () => {
      const agreement = createMockAgreement({ status: 'active' })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.queryByText('Sign Agreement')).not.toBeInTheDocument()
    })

    it('does not render Sign Agreement button for draft status', () => {
      const agreement = createMockAgreement({
        status: 'draft',
        parentSignedAt: undefined,
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.queryByText('Sign Agreement')).not.toBeInTheDocument()
    })

    it('calls onSign with agreement id when Sign Agreement is clicked', () => {
      const onSign = vi.fn()
      const agreement = createMockAgreement({
        id: 'agreement-789',
        status: 'pending_signature',
        parentSignedAt: undefined,
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={onSign}
        />
      )
      fireEvent.click(screen.getByText('Sign Agreement'))
      expect(onSign).toHaveBeenCalledTimes(1)
      expect(onSign).toHaveBeenCalledWith('agreement-789')
    })

    it('Sign Agreement button has primary styling', () => {
      const agreement = createMockAgreement({
        status: 'pending_signature',
        parentSignedAt: undefined,
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      const signButton = screen.getByText('Sign Agreement').closest('button')
      expect(signButton).toHaveClass('btn')
      expect(signButton).toHaveClass('btn-primary')
    })
  })

  describe('View Signed Copy Button', () => {
    it('renders View Signed Copy button when all signatures are complete', () => {
      const agreement = createMockAgreement({
        allSignaturesComplete: true,
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.getByText('View Signed Copy')).toBeInTheDocument()
    })

    it('does not render View Signed Copy button when signatures are incomplete', () => {
      const agreement = createMockAgreement({
        allSignaturesComplete: false,
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(screen.queryByText('View Signed Copy')).not.toBeInTheDocument()
    })

    it('calls onView with agreement id when View Signed Copy is clicked', () => {
      const onView = vi.fn()
      const agreement = createMockAgreement({
        id: 'agreement-complete',
        allSignaturesComplete: true,
      })
      render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={onView}
          onSign={vi.fn()}
        />
      )
      fireEvent.click(screen.getByText('View Signed Copy'))
      expect(onView).toHaveBeenCalledWith('agreement-complete')
    })

    it('View Signed Copy button has green styling', () => {
      const agreement = createMockAgreement({
        allSignaturesComplete: true,
      })
      const { container } = render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      const signedCopyButton = screen.getByText('View Signed Copy').closest('button')
      expect(signedCopyButton).toHaveClass('text-green-600')
      expect(signedCopyButton).toHaveClass('border-green-300')
    })
  })

  describe('Card Structure', () => {
    it('renders card with correct structure', () => {
      const agreement = createMockAgreement()
      const { container } = render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      expect(container.querySelector('.card')).toBeInTheDocument()
      expect(container.querySelector('.card-body')).toBeInTheDocument()
    })

    it('renders action buttons in bordered section', () => {
      const agreement = createMockAgreement()
      const { container } = render(
        <ServiceAgreementCard
          agreement={agreement}
          onView={vi.fn()}
          onSign={vi.fn()}
        />
      )
      const actionsSection = container.querySelector('.border-t.border-gray-100')
      expect(actionsSection).toBeInTheDocument()
    })
  })
})
