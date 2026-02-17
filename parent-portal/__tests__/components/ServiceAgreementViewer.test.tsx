/**
 * Unit tests for ServiceAgreementViewer component
 * Tests rendering of all 13 articles, status badges, signatures, annexes, and action buttons
 */

import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { ServiceAgreementViewer } from '@/components/ServiceAgreementViewer'
import type {
  ServiceAgreement,
  ServiceAgreementStatus,
  ServiceAgreementSignature,
  ServiceAgreementAnnex,
} from '@/lib/types'

// Helper function to create a mock agreement
function createMockAgreement(
  overrides: Partial<ServiceAgreement> = {}
): ServiceAgreement {
  return {
    id: 'agreement-123',
    agreementNumber: 'SA-2026-001',
    status: 'active',
    schoolYearId: 'sy-2026',

    // Article 1: Identification of Parties
    childId: 'child-456',
    childName: 'Emma Johnson',
    childDateOfBirth: '2022-05-15',
    parentId: 'parent-789',
    parentName: 'John Johnson',
    parentAddress: '123 Main Street, Montreal, QC H1A 1A1',
    parentPhone: '514-555-0123',
    parentEmail: 'john.johnson@example.com',
    providerId: 'provider-001',
    providerName: 'Little Stars Daycare',
    providerAddress: '456 Care Avenue, Montreal, QC H2B 2B2',
    providerPhone: '514-555-0456',
    providerPermitNumber: 'QC-2024-12345',

    // Article 2: Description of Services
    serviceDescription: 'Full-time daycare services including meals and educational activities',
    programType: 'Centre de la petite enfance (CPE)',
    ageGroup: 'Toddler (18-36 months)',
    classroomId: 'classroom-01',
    classroomName: 'Sunshine Room',

    // Article 3: Operating Hours
    operatingHours: {
      openTime: '07:00',
      closeTime: '18:00',
      operatingDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
      maxDailyHours: 10,
    },

    // Article 4: Attendance Pattern
    attendancePattern: {
      scheduledDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
      arrivalTime: '08:00',
      departureTime: '17:00',
      isFullTime: true,
      daysPerWeek: 5,
    },

    // Article 5: Payment Terms
    paymentTerms: {
      contributionType: 'reduced',
      dailyRate: 9.35,
      monthlyAmount: 205.70,
      paymentDueDay: 1,
      paymentMethod: 'Pre-authorized debit',
      lateFeePercentage: 5,
      lateFeeGraceDays: 7,
      nsfFee: 35,
      depositAmount: 200,
      depositRefundable: true,
    },

    // Article 6: Late Pickup Fees
    latePickupFees: {
      gracePeriodMinutes: 5,
      feePerInterval: 10,
      intervalMinutes: 15,
      maxDailyFee: 50,
    },

    // Article 7: Closure Days
    closureDays: ['Christmas Day', 'New Year\'s Day', 'Saint-Jean-Baptiste Day'],
    holidaySchedule: 'Quebec statutory holidays',
    vacationWeeks: 2,

    // Article 8: Absence Policy
    absencePolicy: 'Full payment required for absences without 24-hour notice',
    absenceNotificationRequired: true,
    absenceNotificationMethod: 'Phone or email',
    sickDayPolicy: 'Child must be fever-free for 24 hours before returning',

    // Article 9: Agreement Duration
    startDate: '2026-01-01',
    endDate: '2026-12-31',
    autoRenewal: true,
    renewalNoticeRequired: true,
    renewalNoticeDays: 30,

    // Article 10: Termination Conditions
    terminationConditions: {
      noticePeriodDays: 30,
      immediateTerminationReasons: [
        'Non-payment of fees',
        'Endangering other children',
        'Fraudulent documentation',
      ],
      refundPolicy: 'Pro-rated refund of unused fees less deposit',
    },

    // Article 11: Special Conditions
    specialConditions: 'Child requires afternoon nap',
    specialNeedsAccommodations: undefined,
    medicalConditions: undefined,
    allergies: 'Peanut allergy',
    emergencyContacts: 'Jane Johnson (514-555-0124)',

    // Article 12: Consumer Protection Act Notice
    consumerProtectionAcknowledgment: {
      acknowledged: true,
      acknowledgedAt: '2026-01-15T10:30:00Z',
      acknowledgedBy: 'parent-789',
      coolingOffPeriodEndDate: '2026-01-25',
      coolingOffDaysRemaining: 0,
    },

    // Article 13: Signatures
    signatures: [],
    parentSignedAt: '2026-01-15T10:30:00Z',
    providerSignedAt: '2026-01-14T09:00:00Z',
    allSignaturesComplete: true,

    // Annexes
    annexes: [],

    // Metadata
    createdAt: '2026-01-10T08:00:00Z',
    updatedAt: '2026-01-15T10:30:00Z',
    createdBy: 'admin-001',
    pdfUrl: 'https://example.com/agreements/SA-2026-001.pdf',
    notes: 'Standard agreement',

    ...overrides,
  }
}

// Helper function to create a mock signature
function createMockSignature(
  overrides: Partial<ServiceAgreementSignature> = {}
): ServiceAgreementSignature {
  return {
    id: 'sig-001',
    agreementId: 'agreement-123',
    signerRole: 'parent',
    signerName: 'John Johnson',
    signerPersonId: 'parent-789',
    signatureData: 'data:image/png;base64,...',
    signatureType: 'drawn',
    signedAt: '2026-01-15T10:30:00Z',
    ipAddress: '192.168.1.1',
    userAgent: 'Mozilla/5.0',
    verificationHash: 'abc123',
    verificationStatus: 'verified',
    consumerProtectionAcknowledged: true,
    termsAccepted: true,
    legalAcknowledged: true,
    ...overrides,
  }
}

describe('ServiceAgreementViewer Component', () => {
  describe('Header Rendering', () => {
    it('renders "Service Agreement" heading', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Service Agreement')).toBeInTheDocument()
    })

    it('renders agreement number with # prefix', () => {
      const agreement = createMockAgreement({ agreementNumber: 'SA-2026-001' })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Agreement #SA-2026-001')).toBeInTheDocument()
    })

    it('renders child name in quick info bar', () => {
      const agreement = createMockAgreement({ childName: 'Emma Johnson' })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Emma Johnson')).toBeInTheDocument()
    })

    it('renders date range in quick info bar', () => {
      const agreement = createMockAgreement({
        startDate: '2026-01-01',
        endDate: '2026-12-31',
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      // Dates are formatted using toLocaleDateString
      expect(screen.getByText(/Jan 1, 2026/)).toBeInTheDocument()
      expect(screen.getByText(/Dec 31, 2026/)).toBeInTheDocument()
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
        render(<ServiceAgreementViewer agreement={agreement} />)
        expect(screen.getByText(label)).toBeInTheDocument()
      })

      it(`applies ${badgeClass} class for ${status} status`, () => {
        const agreement = createMockAgreement({ status })
        const { container } = render(<ServiceAgreementViewer agreement={agreement} />)
        const badge = container.querySelector(`.${badgeClass}`)
        expect(badge).toBeInTheDocument()
      })
    })
  })

  describe('Signature Required Alert', () => {
    it('displays signature required alert when status is pending_signature and no parent signature', () => {
      const agreement = createMockAgreement({
        status: 'pending_signature',
        signatures: [],
        parentSignedAt: undefined,
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Signature Required')).toBeInTheDocument()
      expect(screen.getByText(/Please review this agreement carefully/)).toBeInTheDocument()
    })

    it('does not display signature required alert when parent has signed', () => {
      const parentSignature = createMockSignature({ signerRole: 'parent' })
      const agreement = createMockAgreement({
        status: 'pending_signature',
        signatures: [parentSignature],
        parentSignedAt: '2026-01-15T10:30:00Z',
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.queryByText('Signature Required')).not.toBeInTheDocument()
    })

    it('does not display signature required alert for active status', () => {
      const agreement = createMockAgreement({ status: 'active' })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.queryByText('Signature Required')).not.toBeInTheDocument()
    })

    it('displays amber background for signature required alert', () => {
      const agreement = createMockAgreement({
        status: 'pending_signature',
        signatures: [],
        parentSignedAt: undefined,
      })
      const { container } = render(<ServiceAgreementViewer agreement={agreement} />)
      const alert = container.querySelector('.bg-amber-50')
      expect(alert).toBeInTheDocument()
    })
  })

  describe('Article Sections', () => {
    it('renders Article 1 - Identification of Parties (expanded by default)', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Identification of Parties')).toBeInTheDocument()
      // Should be expanded, so child info should be visible
      expect(screen.getByText('Child Information')).toBeInTheDocument()
    })

    it('renders Article 2 - Description of Services', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Description of Services')).toBeInTheDocument()
    })

    it('renders Article 3 - Operating Hours', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Operating Hours')).toBeInTheDocument()
    })

    it('renders Article 4 - Attendance Pattern', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Attendance Pattern')).toBeInTheDocument()
    })

    it('renders Article 5 - Payment Terms', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Payment Terms')).toBeInTheDocument()
    })

    it('renders Article 6 - Late Pickup Fees', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Late Pickup Fees')).toBeInTheDocument()
    })

    it('renders Article 7 - Closure Days', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Closure Days')).toBeInTheDocument()
    })

    it('renders Article 8 - Absence Policy', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Absence Policy')).toBeInTheDocument()
    })

    it('renders Article 9 - Agreement Duration', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Agreement Duration')).toBeInTheDocument()
    })

    it('renders Article 10 - Termination Conditions', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Termination Conditions')).toBeInTheDocument()
    })

    it('renders Article 11 - Special Conditions', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Special Conditions')).toBeInTheDocument()
    })

    it('renders Article 12 - Consumer Protection Act Notice', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Consumer Protection Act Notice')).toBeInTheDocument()
    })

    it('renders Article 13 - Signatures (expanded by default)', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Signatures')).toBeInTheDocument()
      // Should be expanded, so parent signature status should be visible
      expect(screen.getByText('Parent Signature:')).toBeInTheDocument()
    })

    it('renders article numbers 1-13', () => {
      const agreement = createMockAgreement()
      const { container } = render(<ServiceAgreementViewer agreement={agreement} />)
      // Article numbers are in circular badges
      const articleBadges = container.querySelectorAll('.rounded-full.bg-purple-100')
      expect(articleBadges.length).toBeGreaterThanOrEqual(13)
    })
  })

  describe('Article Section Expand/Collapse', () => {
    it('expands collapsed article when clicked', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)

      // Article 5 (Payment Terms) is collapsed by default
      const paymentTermsButton = screen.getByText('Payment Terms').closest('button')
      expect(paymentTermsButton).toBeInTheDocument()

      // Click to expand
      fireEvent.click(paymentTermsButton!)

      // Content should now be visible
      expect(screen.getByText('Daily Rate')).toBeInTheDocument()
    })

    it('collapses expanded article when clicked', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)

      // Article 1 (Identification of Parties) is expanded by default
      // Verify it's expanded
      expect(screen.getByText('Child Information')).toBeInTheDocument()

      // Click to collapse
      const identificationButton = screen.getByText('Identification of Parties').closest('button')
      fireEvent.click(identificationButton!)

      // Content should now be hidden
      expect(screen.queryByText('Child Information')).not.toBeInTheDocument()
    })
  })

  describe('Article 1 - Identification of Parties Content', () => {
    it('displays child name', () => {
      const agreement = createMockAgreement({ childName: 'Emma Johnson' })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getAllByText('Emma Johnson').length).toBeGreaterThan(0)
    })

    it('displays parent name', () => {
      const agreement = createMockAgreement({ parentName: 'John Johnson' })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('John Johnson')).toBeInTheDocument()
    })

    it('displays parent contact information', () => {
      const agreement = createMockAgreement({
        parentPhone: '514-555-0123',
        parentEmail: 'john.johnson@example.com',
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('514-555-0123')).toBeInTheDocument()
      expect(screen.getByText('john.johnson@example.com')).toBeInTheDocument()
    })

    it('displays provider name', () => {
      const agreement = createMockAgreement({ providerName: 'Little Stars Daycare' })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Little Stars Daycare')).toBeInTheDocument()
    })

    it('displays provider permit number when available', () => {
      const agreement = createMockAgreement({ providerPermitNumber: 'QC-2024-12345' })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('QC-2024-12345')).toBeInTheDocument()
    })
  })

  describe('Article 5 - Payment Terms Content', () => {
    it('displays contribution type correctly formatted', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)

      // Expand payment terms section
      const paymentTermsButton = screen.getByText('Payment Terms').closest('button')
      fireEvent.click(paymentTermsButton!)

      expect(screen.getByText(/Quebec Reduced Contribution/)).toBeInTheDocument()
    })

    it('displays formatted currency for daily rate', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)

      // Expand payment terms section
      const paymentTermsButton = screen.getByText('Payment Terms').closest('button')
      fireEvent.click(paymentTermsButton!)

      // $9.35 formatted as CAD currency
      expect(screen.getByText(/\$9\.35/)).toBeInTheDocument()
    })

    it('displays deposit information when available', () => {
      const agreement = createMockAgreement({
        paymentTerms: {
          ...createMockAgreement().paymentTerms,
          depositAmount: 200,
          depositRefundable: true,
        },
      })
      render(<ServiceAgreementViewer agreement={agreement} />)

      // Expand payment terms section
      const paymentTermsButton = screen.getByText('Payment Terms').closest('button')
      fireEvent.click(paymentTermsButton!)

      expect(screen.getByText(/\$200\.00/)).toBeInTheDocument()
      expect(screen.getByText(/Refundable/)).toBeInTheDocument()
    })
  })

  describe('Signature Section Content', () => {
    it('displays "No signatures collected yet" when no signatures exist', () => {
      const agreement = createMockAgreement({
        signatures: [],
        parentSignedAt: undefined,
        providerSignedAt: undefined,
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('No signatures collected yet.')).toBeInTheDocument()
    })

    it('displays signature details when signatures exist', () => {
      const parentSignature = createMockSignature({
        signerRole: 'parent',
        signerName: 'John Johnson',
        signatureType: 'drawn',
        verificationStatus: 'verified',
      })
      const agreement = createMockAgreement({
        signatures: [parentSignature],
      })
      render(<ServiceAgreementViewer agreement={agreement} />)

      expect(screen.getByText('John Johnson')).toBeInTheDocument()
      expect(screen.getByText('parent')).toBeInTheDocument()
      expect(screen.getByText('Verified')).toBeInTheDocument()
    })

    it('displays signature type as Hand-drawn for drawn signatures', () => {
      const parentSignature = createMockSignature({
        signatureType: 'drawn',
      })
      const agreement = createMockAgreement({
        signatures: [parentSignature],
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText(/Hand-drawn/)).toBeInTheDocument()
    })

    it('displays signature type as Typed for typed signatures', () => {
      const parentSignature = createMockSignature({
        signatureType: 'typed',
      })
      const agreement = createMockAgreement({
        signatures: [parentSignature],
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText(/Typed/)).toBeInTheDocument()
    })

    it('displays parent signature status as "Signed" when signed', () => {
      const agreement = createMockAgreement({
        parentSignedAt: '2026-01-15T10:30:00Z',
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      const signedElements = screen.getAllByText('Signed')
      expect(signedElements.length).toBeGreaterThan(0)
    })

    it('displays parent signature status as "Pending" when not signed', () => {
      const agreement = createMockAgreement({
        parentSignedAt: undefined,
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      const pendingElements = screen.getAllByText('Pending')
      expect(pendingElements.length).toBeGreaterThan(0)
    })

    it('displays provider signature status as "Signed" when signed', () => {
      const agreement = createMockAgreement({
        providerSignedAt: '2026-01-14T09:00:00Z',
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      const signedElements = screen.getAllByText('Signed')
      expect(signedElements.length).toBeGreaterThan(0)
    })

    it('displays IP address when available on signature', () => {
      const parentSignature = createMockSignature({
        ipAddress: '192.168.1.1',
      })
      const agreement = createMockAgreement({
        signatures: [parentSignature],
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText(/192\.168\.1\.1/)).toBeInTheDocument()
    })
  })

  describe('Consumer Protection Notice Section', () => {
    it('displays Quebec Consumer Protection Act title', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)

      // Expand Consumer Protection section
      const cpButton = screen.getByText('Consumer Protection Act Notice').closest('button')
      fireEvent.click(cpButton!)

      expect(screen.getByText('Quebec Consumer Protection Act')).toBeInTheDocument()
    })

    it('displays 10-day cooling-off period information', () => {
      const agreement = createMockAgreement()
      render(<ServiceAgreementViewer agreement={agreement} />)

      // Expand Consumer Protection section
      const cpButton = screen.getByText('Consumer Protection Act Notice').closest('button')
      fireEvent.click(cpButton!)

      expect(screen.getByText(/10-day cooling-off period/)).toBeInTheDocument()
    })

    it('displays acknowledgment status as "Acknowledged" when acknowledged', () => {
      const agreement = createMockAgreement({
        consumerProtectionAcknowledgment: {
          acknowledged: true,
          acknowledgedAt: '2026-01-15T10:30:00Z',
        },
      })
      render(<ServiceAgreementViewer agreement={agreement} />)

      // Expand Consumer Protection section
      const cpButton = screen.getByText('Consumer Protection Act Notice').closest('button')
      fireEvent.click(cpButton!)

      expect(screen.getByText('Acknowledged')).toBeInTheDocument()
    })

    it('displays acknowledgment status as "Pending acknowledgment" when not acknowledged', () => {
      const agreement = createMockAgreement({
        consumerProtectionAcknowledgment: {
          acknowledged: false,
        },
      })
      render(<ServiceAgreementViewer agreement={agreement} />)

      // Expand Consumer Protection section
      const cpButton = screen.getByText('Consumer Protection Act Notice').closest('button')
      fireEvent.click(cpButton!)

      expect(screen.getByText('Pending acknowledgment')).toBeInTheDocument()
    })
  })

  describe('Annexes Section', () => {
    it('does not render annexes section when no annexes exist', () => {
      const agreement = createMockAgreement({ annexes: [] })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.queryByText('Optional Annexes')).not.toBeInTheDocument()
    })

    it('renders annexes section when annexes exist', () => {
      const annexA: ServiceAgreementAnnex = {
        id: 'annex-a-001',
        agreementId: 'agreement-123',
        type: 'A',
        status: 'signed',
        authorizeFieldTrips: true,
        fieldTripConditions: 'Must be notified 24 hours in advance',
        signedAt: '2026-01-15T10:30:00Z',
        signedBy: 'John Johnson',
      }
      const agreement = createMockAgreement({ annexes: [annexA] })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Optional Annexes')).toBeInTheDocument()
    })

    it('renders Annex A - Field Trips Authorization with correct title', () => {
      const annexA: ServiceAgreementAnnex = {
        id: 'annex-a-001',
        agreementId: 'agreement-123',
        type: 'A',
        status: 'signed',
        authorizeFieldTrips: true,
      }
      const agreement = createMockAgreement({ annexes: [annexA] })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Annex A - Field Trips Authorization')).toBeInTheDocument()
    })

    it('renders Annex B - Hygiene Items with correct title', () => {
      const annexB: ServiceAgreementAnnex = {
        id: 'annex-b-001',
        agreementId: 'agreement-123',
        type: 'B',
        status: 'pending',
        hygieneItemsIncluded: true,
        itemsList: ['Diapers', 'Wipes'],
        monthlyFee: 25,
      }
      const agreement = createMockAgreement({ annexes: [annexB] })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Annex B - Hygiene Items')).toBeInTheDocument()
    })

    it('renders Annex C - Supplementary Meals with correct title', () => {
      const annexC: ServiceAgreementAnnex = {
        id: 'annex-c-001',
        agreementId: 'agreement-123',
        type: 'C',
        status: 'signed',
        supplementaryMealsIncluded: true,
        mealsIncluded: ['Breakfast', 'Lunch', 'Afternoon snack'],
      }
      const agreement = createMockAgreement({ annexes: [annexC] })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Annex C - Supplementary Meals')).toBeInTheDocument()
    })

    it('renders Annex D - Extended Hours with correct title', () => {
      const annexD: ServiceAgreementAnnex = {
        id: 'annex-d-001',
        agreementId: 'agreement-123',
        type: 'D',
        status: 'pending',
        extendedHoursRequired: true,
        requestedStartTime: '06:30',
        requestedEndTime: '18:30',
        additionalHoursPerDay: 1,
        hourlyRate: 15,
        monthlyEstimate: 300,
      }
      const agreement = createMockAgreement({ annexes: [annexD] })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText('Annex D - Extended Hours')).toBeInTheDocument()
    })

    it('displays annex status badge for signed annex', () => {
      const annexA: ServiceAgreementAnnex = {
        id: 'annex-a-001',
        agreementId: 'agreement-123',
        type: 'A',
        status: 'signed',
        authorizeFieldTrips: true,
      }
      const agreement = createMockAgreement({ annexes: [annexA] })
      const { container } = render(<ServiceAgreementViewer agreement={agreement} />)
      // Find badge-success within annexes section
      const annexSection = screen.getByText('Optional Annexes').closest('div')
      const signedBadge = annexSection?.querySelector('.badge-success')
      expect(signedBadge).toBeInTheDocument()
    })

    it('displays annex status badge for pending annex', () => {
      const annexA: ServiceAgreementAnnex = {
        id: 'annex-a-001',
        agreementId: 'agreement-123',
        type: 'A',
        status: 'pending',
        authorizeFieldTrips: false,
      }
      const agreement = createMockAgreement({ annexes: [annexA] })
      const { container } = render(<ServiceAgreementViewer agreement={agreement} />)
      const annexSection = screen.getByText('Optional Annexes').closest('div')
      const pendingBadge = annexSection?.querySelector('.badge-warning')
      expect(pendingBadge).toBeInTheDocument()
    })

    it('displays annex status badge for declined annex', () => {
      const annexA: ServiceAgreementAnnex = {
        id: 'annex-a-001',
        agreementId: 'agreement-123',
        type: 'A',
        status: 'declined',
        authorizeFieldTrips: false,
      }
      const agreement = createMockAgreement({ annexes: [annexA] })
      const { container } = render(<ServiceAgreementViewer agreement={agreement} />)
      const annexSection = screen.getByText('Optional Annexes').closest('div')
      const declinedBadge = annexSection?.querySelector('.badge-error')
      expect(declinedBadge).toBeInTheDocument()
    })
  })

  describe('Action Buttons', () => {
    describe('Download PDF Button', () => {
      it('renders Download PDF link when pdfUrl is provided', () => {
        const agreement = createMockAgreement({
          pdfUrl: 'https://example.com/agreements/SA-2026-001.pdf',
        })
        render(<ServiceAgreementViewer agreement={agreement} />)
        expect(screen.getByText('Download PDF')).toBeInTheDocument()
      })

      it('does not render Download PDF link when pdfUrl is not provided', () => {
        const agreement = createMockAgreement({ pdfUrl: undefined })
        render(<ServiceAgreementViewer agreement={agreement} />)
        expect(screen.queryByText('Download PDF')).not.toBeInTheDocument()
      })

      it('Download PDF link has correct href', () => {
        const agreement = createMockAgreement({
          pdfUrl: 'https://example.com/agreements/SA-2026-001.pdf',
        })
        render(<ServiceAgreementViewer agreement={agreement} />)
        const link = screen.getByText('Download PDF').closest('a')
        expect(link).toHaveAttribute('href', 'https://example.com/agreements/SA-2026-001.pdf')
      })

      it('Download PDF link opens in new tab', () => {
        const agreement = createMockAgreement({
          pdfUrl: 'https://example.com/agreements/SA-2026-001.pdf',
        })
        render(<ServiceAgreementViewer agreement={agreement} />)
        const link = screen.getByText('Download PDF').closest('a')
        expect(link).toHaveAttribute('target', '_blank')
        expect(link).toHaveAttribute('rel', 'noopener noreferrer')
      })
    })

    describe('Sign Agreement Button', () => {
      it('renders Sign Agreement button when signature is required and onSign is provided', () => {
        const agreement = createMockAgreement({
          status: 'pending_signature',
          signatures: [],
          parentSignedAt: undefined,
        })
        const onSign = vi.fn()
        render(<ServiceAgreementViewer agreement={agreement} onSign={onSign} />)
        expect(screen.getByText('Sign Agreement')).toBeInTheDocument()
      })

      it('does not render Sign Agreement button when signature is not required', () => {
        const agreement = createMockAgreement({
          status: 'active',
          parentSignedAt: '2026-01-15T10:30:00Z',
        })
        const onSign = vi.fn()
        render(<ServiceAgreementViewer agreement={agreement} onSign={onSign} />)
        expect(screen.queryByText('Sign Agreement')).not.toBeInTheDocument()
      })

      it('does not render Sign Agreement button when parent has already signed', () => {
        const parentSignature = createMockSignature({ signerRole: 'parent' })
        const agreement = createMockAgreement({
          status: 'pending_signature',
          signatures: [parentSignature],
          parentSignedAt: '2026-01-15T10:30:00Z',
        })
        const onSign = vi.fn()
        render(<ServiceAgreementViewer agreement={agreement} onSign={onSign} />)
        expect(screen.queryByText('Sign Agreement')).not.toBeInTheDocument()
      })

      it('does not render Sign Agreement button when onSign is not provided', () => {
        const agreement = createMockAgreement({
          status: 'pending_signature',
          signatures: [],
          parentSignedAt: undefined,
        })
        render(<ServiceAgreementViewer agreement={agreement} />)
        expect(screen.queryByText('Sign Agreement')).not.toBeInTheDocument()
      })

      it('calls onSign when Sign Agreement button is clicked', () => {
        const agreement = createMockAgreement({
          status: 'pending_signature',
          signatures: [],
          parentSignedAt: undefined,
        })
        const onSign = vi.fn()
        render(<ServiceAgreementViewer agreement={agreement} onSign={onSign} />)
        fireEvent.click(screen.getByText('Sign Agreement'))
        expect(onSign).toHaveBeenCalledTimes(1)
      })

      it('Sign Agreement button has primary styling', () => {
        const agreement = createMockAgreement({
          status: 'pending_signature',
          signatures: [],
          parentSignedAt: undefined,
        })
        const onSign = vi.fn()
        render(<ServiceAgreementViewer agreement={agreement} onSign={onSign} />)
        const button = screen.getByText('Sign Agreement').closest('button')
        expect(button).toHaveClass('btn')
        expect(button).toHaveClass('btn-primary')
      })
    })

    describe('Close Button', () => {
      it('renders Close button when onClose is provided', () => {
        const agreement = createMockAgreement()
        const onClose = vi.fn()
        render(<ServiceAgreementViewer agreement={agreement} onClose={onClose} />)
        expect(screen.getByText('Close')).toBeInTheDocument()
      })

      it('does not render Close button when onClose is not provided', () => {
        const agreement = createMockAgreement()
        render(<ServiceAgreementViewer agreement={agreement} />)
        expect(screen.queryByText('Close')).not.toBeInTheDocument()
      })

      it('calls onClose when Close button is clicked', () => {
        const agreement = createMockAgreement()
        const onClose = vi.fn()
        render(<ServiceAgreementViewer agreement={agreement} onClose={onClose} />)
        fireEvent.click(screen.getByText('Close'))
        expect(onClose).toHaveBeenCalledTimes(1)
      })

      it('Close button has outline styling', () => {
        const agreement = createMockAgreement()
        const onClose = vi.fn()
        render(<ServiceAgreementViewer agreement={agreement} onClose={onClose} />)
        const button = screen.getByText('Close').closest('button')
        expect(button).toHaveClass('btn')
        expect(button).toHaveClass('btn-outline')
      })

      it('renders close X button in header when onClose is provided', () => {
        const agreement = createMockAgreement()
        const onClose = vi.fn()
        const { container } = render(
          <ServiceAgreementViewer agreement={agreement} onClose={onClose} />
        )
        // Find the X close button in header (rounded-full p-2)
        const headerCloseButton = container.querySelector('.rounded-full.p-2')
        expect(headerCloseButton).toBeInTheDocument()
      })

      it('calls onClose when header X button is clicked', () => {
        const agreement = createMockAgreement()
        const onClose = vi.fn()
        const { container } = render(
          <ServiceAgreementViewer agreement={agreement} onClose={onClose} />
        )
        const headerCloseButton = container.querySelector('.rounded-full.p-2')
        fireEvent.click(headerCloseButton!)
        expect(onClose).toHaveBeenCalledTimes(1)
      })
    })
  })

  describe('Metadata Footer', () => {
    it('displays created date', () => {
      const agreement = createMockAgreement({
        createdAt: '2026-01-10T08:00:00Z',
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText(/Created:/)).toBeInTheDocument()
    })

    it('displays last updated date', () => {
      const agreement = createMockAgreement({
        updatedAt: '2026-01-15T10:30:00Z',
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText(/Last updated:/)).toBeInTheDocument()
    })

    it('displays notes when provided', () => {
      const agreement = createMockAgreement({
        notes: 'Standard agreement with special nap requirements',
      })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.getByText(/Notes:/)).toBeInTheDocument()
      expect(
        screen.getByText(/Standard agreement with special nap requirements/)
      ).toBeInTheDocument()
    })

    it('does not display notes section when notes are not provided', () => {
      const agreement = createMockAgreement({ notes: undefined })
      render(<ServiceAgreementViewer agreement={agreement} />)
      expect(screen.queryByText(/Notes:/)).not.toBeInTheDocument()
    })
  })

  describe('Helper Functions', () => {
    describe('Time Formatting', () => {
      it('formats operating hours correctly (AM/PM)', () => {
        const agreement = createMockAgreement({
          operatingHours: {
            openTime: '07:00',
            closeTime: '18:00',
            operatingDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            maxDailyHours: 10,
          },
        })
        render(<ServiceAgreementViewer agreement={agreement} />)

        // Expand Operating Hours section
        const operatingHoursButton = screen.getByText('Operating Hours').closest('button')
        fireEvent.click(operatingHoursButton!)

        expect(screen.getByText(/7:00 AM/)).toBeInTheDocument()
        expect(screen.getByText(/6:00 PM/)).toBeInTheDocument()
      })
    })

    describe('Days Formatting', () => {
      it('formats weekdays as "Monday - Friday"', () => {
        const agreement = createMockAgreement({
          operatingHours: {
            openTime: '07:00',
            closeTime: '18:00',
            operatingDays: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
            maxDailyHours: 10,
          },
        })
        render(<ServiceAgreementViewer agreement={agreement} />)

        // Expand Operating Hours section
        const operatingHoursButton = screen.getByText('Operating Hours').closest('button')
        fireEvent.click(operatingHoursButton!)

        expect(screen.getByText('Monday - Friday')).toBeInTheDocument()
      })

      it('formats all days as "Every day"', () => {
        const agreement = createMockAgreement({
          operatingHours: {
            openTime: '07:00',
            closeTime: '18:00',
            operatingDays: [
              'monday',
              'tuesday',
              'wednesday',
              'thursday',
              'friday',
              'saturday',
              'sunday',
            ],
            maxDailyHours: 10,
          },
        })
        render(<ServiceAgreementViewer agreement={agreement} />)

        // Expand Operating Hours section
        const operatingHoursButton = screen.getByText('Operating Hours').closest('button')
        fireEvent.click(operatingHoursButton!)

        expect(screen.getByText('Every day')).toBeInTheDocument()
      })
    })
  })

  describe('Special Conditions Display', () => {
    it('displays "No special conditions specified" when no special conditions', () => {
      const agreement = createMockAgreement({
        specialConditions: undefined,
        specialNeedsAccommodations: undefined,
        medicalConditions: undefined,
        allergies: undefined,
        emergencyContacts: undefined,
      })
      render(<ServiceAgreementViewer agreement={agreement} />)

      // Expand Special Conditions section
      const specialConditionsButton = screen.getByText('Special Conditions').closest('button')
      fireEvent.click(specialConditionsButton!)

      expect(screen.getByText('No special conditions specified.')).toBeInTheDocument()
    })

    it('displays allergies when provided', () => {
      const agreement = createMockAgreement({
        allergies: 'Peanut allergy',
      })
      render(<ServiceAgreementViewer agreement={agreement} />)

      // Expand Special Conditions section
      const specialConditionsButton = screen.getByText('Special Conditions').closest('button')
      fireEvent.click(specialConditionsButton!)

      expect(screen.getByText('Peanut allergy')).toBeInTheDocument()
    })
  })

  describe('Component Structure', () => {
    it('renders with max-w-4xl container', () => {
      const agreement = createMockAgreement()
      const { container } = render(<ServiceAgreementViewer agreement={agreement} />)
      expect(container.querySelector('.max-w-4xl')).toBeInTheDocument()
    })

    it('renders article sections with proper spacing', () => {
      const agreement = createMockAgreement()
      const { container } = render(<ServiceAgreementViewer agreement={agreement} />)
      expect(container.querySelector('.space-y-4')).toBeInTheDocument()
    })

    it('renders action buttons section with border-t', () => {
      const agreement = createMockAgreement()
      const onClose = vi.fn()
      const { container } = render(
        <ServiceAgreementViewer agreement={agreement} onClose={onClose} />
      )
      const actionSection = container.querySelector('.border-t.border-gray-200.pt-6')
      expect(actionSection).toBeInTheDocument()
    })
  })
})
