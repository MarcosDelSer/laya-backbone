/**
 * Integration tests for service agreement API endpoints.
 * Tests the gibbon-client.ts methods for service agreements with mocked fetch responses.
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import {
  getServiceAgreements,
  getServiceAgreement,
  signServiceAgreement,
  getServiceAgreementPdfUrl,
  getPendingServiceAgreementsCount,
  getServiceAgreementsRequiringSignature,
  getActiveServiceAgreementsForChild,
} from '@/lib/gibbon-client'
import type {
  ServiceAgreement,
  ServiceAgreementSummary,
  SignServiceAgreementRequest,
  SignServiceAgreementResponse,
  PaginatedResponse,
} from '@/lib/types'

// ============================================================================
// Mock Data
// ============================================================================

/**
 * Creates a mock service agreement summary for testing.
 */
function createMockServiceAgreementSummary(
  overrides: Partial<ServiceAgreementSummary> = {}
): ServiceAgreementSummary {
  return {
    id: 'sa-001',
    agreementNumber: 'SA-2024-001',
    status: 'pending_signature',
    childId: 'child-001',
    childName: 'John Doe',
    parentName: 'Jane Doe',
    startDate: '2024-01-01',
    endDate: '2024-12-31',
    allSignaturesComplete: false,
    parentSignedAt: undefined,
    providerSignedAt: undefined,
    createdAt: '2024-01-01T00:00:00Z',
    updatedAt: '2024-01-01T00:00:00Z',
    ...overrides,
  }
}

/**
 * Creates a mock full service agreement for testing.
 */
function createMockServiceAgreement(
  overrides: Partial<ServiceAgreement> = {}
): ServiceAgreement {
  return {
    id: 'sa-001',
    agreementNumber: 'SA-2024-001',
    status: 'pending_signature',
    schoolYearId: 'sy-2024',

    // Article 1: Identification of Parties
    childId: 'child-001',
    childName: 'John Doe',
    childDateOfBirth: '2020-06-15',
    parentId: 'parent-001',
    parentName: 'Jane Doe',
    parentAddress: '123 Main St, Montreal, QC H1A 1A1',
    parentPhone: '514-555-0123',
    parentEmail: 'jane.doe@example.com',
    providerId: 'provider-001',
    providerName: 'Happy Kids Daycare',
    providerAddress: '456 Care Ave, Montreal, QC H2B 2B2',
    providerPhone: '514-555-0456',
    providerPermitNumber: 'QC-CPE-12345',

    // Article 2: Description of Services
    serviceDescription: 'Full-time childcare services',
    programType: 'CPE',
    ageGroup: '3-5 years',
    classroomId: 'room-001',
    classroomName: 'Sunflower Room',

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
      monthlyAmount: 196.35,
      paymentDueDay: 1,
      paymentMethod: 'Pre-authorized debit',
      lateFeePercentage: 2,
      lateFeeGraceDays: 5,
      nsfFee: 25,
      depositAmount: 196.35,
      depositRefundable: true,
    },

    // Article 6: Late Pickup Fees
    latePickupFees: {
      gracePeriodMinutes: 5,
      feePerInterval: 1,
      intervalMinutes: 5,
      maxDailyFee: 25,
    },

    // Article 7: Closure Days
    closureDays: ['2024-12-25', '2024-12-26', '2025-01-01'],
    holidaySchedule: 'Quebec statutory holidays',
    vacationWeeks: 2,

    // Article 8: Absence Policy
    absencePolicy: 'Parents must notify by 9:00 AM',
    absenceNotificationRequired: true,
    absenceNotificationMethod: 'Phone or app',
    sickDayPolicy: 'Child must be symptom-free for 24 hours before returning',

    // Article 9: Agreement Duration
    startDate: '2024-01-01',
    endDate: '2024-12-31',
    autoRenewal: true,
    renewalNoticeRequired: true,
    renewalNoticeDays: 30,

    // Article 10: Termination Conditions
    terminationConditions: {
      noticePeriodDays: 30,
      immediateTerminationReasons: [
        'Non-payment',
        'Safety concerns',
        'Repeated policy violations',
      ],
      refundPolicy: 'Pro-rated refund for unused days',
    },

    // Article 11: Special Conditions
    specialConditions: 'None',
    specialNeedsAccommodations: undefined,
    medicalConditions: undefined,
    allergies: 'Peanuts',
    emergencyContacts: 'John Doe (Father): 514-555-0789',

    // Article 12: Consumer Protection Act Notice
    consumerProtectionAcknowledgment: {
      acknowledged: false,
      acknowledgedAt: undefined,
      acknowledgedBy: undefined,
      coolingOffPeriodEndDate: undefined,
      coolingOffDaysRemaining: undefined,
    },

    // Article 13: Signatures
    signatures: [],
    parentSignedAt: undefined,
    providerSignedAt: undefined,
    allSignaturesComplete: false,

    // Annexes
    annexes: [
      {
        id: 'annex-a-001',
        agreementId: 'sa-001',
        type: 'A',
        status: 'pending',
        authorizeFieldTrips: true,
        fieldTripConditions: 'Within 10km radius',
        transportationAuthorized: true,
        walkingDistanceAuthorized: true,
      },
      {
        id: 'annex-b-001',
        agreementId: 'sa-001',
        type: 'B',
        status: 'pending',
        hygieneItemsIncluded: true,
        itemsList: ['Diapers', 'Wipes'],
        monthlyFee: 25,
      },
    ],

    // Metadata
    createdAt: '2024-01-01T00:00:00Z',
    updatedAt: '2024-01-01T00:00:00Z',
    createdBy: 'admin-001',
    pdfUrl: undefined,
    notes: undefined,
    ...overrides,
  }
}

/**
 * Creates a mock sign service agreement response.
 */
function createMockSignResponse(
  overrides: Partial<SignServiceAgreementResponse> = {}
): SignServiceAgreementResponse {
  return {
    success: true,
    signatureId: 'sig-001',
    agreementStatus: 'active',
    allSignaturesComplete: true,
    pdfUrl: '/api/v1/service-agreements/sa-001/pdf',
    message: 'Agreement signed successfully',
    ...overrides,
  }
}

// ============================================================================
// Test Setup
// ============================================================================

describe('Service Agreement API Integration Tests', () => {
  const originalFetch = global.fetch

  beforeEach(() => {
    vi.resetAllMocks()
  })

  afterEach(() => {
    global.fetch = originalFetch
  })

  // ==========================================================================
  // getServiceAgreements Tests
  // ==========================================================================

  describe('getServiceAgreements', () => {
    it('should fetch service agreements list', async () => {
      const mockAgreements = [
        createMockServiceAgreementSummary({ id: 'sa-001' }),
        createMockServiceAgreementSummary({ id: 'sa-002', status: 'active' }),
      ]

      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: mockAgreements,
        total: 2,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await getServiceAgreements()

      expect(result.items).toHaveLength(2)
      expect(result.total).toBe(2)
      expect(result.items[0].id).toBe('sa-001')
      expect(result.items[1].status).toBe('active')
    })

    it('should pass status filter to API', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [createMockServiceAgreementSummary({ status: 'pending_signature' })],
        total: 1,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      await getServiceAgreements({ status: 'pending_signature' })

      expect(global.fetch).toHaveBeenCalledTimes(1)
      const callUrl = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0][0]
      expect(callUrl).toContain('status=pending_signature')
    })

    it('should pass childId filter to API', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [createMockServiceAgreementSummary({ childId: 'child-123' })],
        total: 1,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      await getServiceAgreements({ childId: 'child-123' })

      expect(global.fetch).toHaveBeenCalledTimes(1)
      const callUrl = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0][0]
      expect(callUrl).toContain('child_id=child-123')
    })

    it('should pass pagination parameters to API', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [],
        total: 100,
        skip: 20,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      await getServiceAgreements({ skip: 20, limit: 10 })

      const callUrl = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0][0]
      expect(callUrl).toContain('skip=20')
      expect(callUrl).toContain('limit=10')
    })

    it('should handle empty results', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [],
        total: 0,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await getServiceAgreements()

      expect(result.items).toHaveLength(0)
      expect(result.total).toBe(0)
    })

    it('should throw error on API failure', async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 500,
        statusText: 'Internal Server Error',
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({ detail: 'Server error' }),
      })

      await expect(getServiceAgreements()).rejects.toThrow()
    })
  })

  // ==========================================================================
  // getServiceAgreement Tests
  // ==========================================================================

  describe('getServiceAgreement', () => {
    it('should fetch a specific service agreement by ID', async () => {
      const mockAgreement = createMockServiceAgreement({ id: 'sa-123' })

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockAgreement,
      })

      const result = await getServiceAgreement('sa-123')

      expect(result.id).toBe('sa-123')
      expect(result.agreementNumber).toBe('SA-2024-001')
      expect(result.childName).toBe('John Doe')
    })

    it('should include all 13 articles in response', async () => {
      const mockAgreement = createMockServiceAgreement()

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockAgreement,
      })

      const result = await getServiceAgreement('sa-001')

      // Article 1: Identification of Parties
      expect(result.childId).toBeDefined()
      expect(result.parentId).toBeDefined()
      expect(result.providerId).toBeDefined()

      // Article 2: Description of Services
      expect(result.serviceDescription).toBeDefined()
      expect(result.programType).toBeDefined()

      // Article 3: Operating Hours
      expect(result.operatingHours).toBeDefined()
      expect(result.operatingHours.openTime).toBe('07:00')

      // Article 4: Attendance Pattern
      expect(result.attendancePattern).toBeDefined()
      expect(result.attendancePattern.isFullTime).toBe(true)

      // Article 5: Payment Terms
      expect(result.paymentTerms).toBeDefined()
      expect(result.paymentTerms.dailyRate).toBe(9.35)

      // Article 6: Late Pickup Fees
      expect(result.latePickupFees).toBeDefined()
      expect(result.latePickupFees.gracePeriodMinutes).toBe(5)

      // Article 7: Closure Days
      expect(result.closureDays).toBeDefined()
      expect(result.closureDays).toHaveLength(3)

      // Article 8: Absence Policy
      expect(result.absencePolicy).toBeDefined()
      expect(result.absenceNotificationRequired).toBe(true)

      // Article 9: Agreement Duration
      expect(result.startDate).toBeDefined()
      expect(result.endDate).toBeDefined()

      // Article 10: Termination Conditions
      expect(result.terminationConditions).toBeDefined()
      expect(result.terminationConditions.noticePeriodDays).toBe(30)

      // Article 11: Special Conditions
      expect(result.allergies).toBe('Peanuts')

      // Article 12: Consumer Protection Act Notice
      expect(result.consumerProtectionAcknowledgment).toBeDefined()

      // Article 13: Signatures
      expect(result.signatures).toBeDefined()
      expect(result.allSignaturesComplete).toBeDefined()
    })

    it('should include annexes in response', async () => {
      const mockAgreement = createMockServiceAgreement()

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockAgreement,
      })

      const result = await getServiceAgreement('sa-001')

      expect(result.annexes).toHaveLength(2)
      expect(result.annexes[0].type).toBe('A')
      expect(result.annexes[1].type).toBe('B')
    })

    it('should call correct endpoint', async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => createMockServiceAgreement(),
      })

      await getServiceAgreement('sa-xyz')

      const callUrl = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0][0]
      expect(callUrl).toContain('/api/v1/service-agreements/sa-xyz')
    })

    it('should throw error when agreement not found', async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 404,
        statusText: 'Not Found',
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({ detail: 'Service agreement not found' }),
      })

      await expect(getServiceAgreement('nonexistent')).rejects.toThrow()
    })
  })

  // ==========================================================================
  // signServiceAgreement Tests
  // ==========================================================================

  describe('signServiceAgreement', () => {
    it('should sign a service agreement with typed signature', async () => {
      const mockResponse = createMockSignResponse()

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const request: SignServiceAgreementRequest = {
        agreementId: 'sa-001',
        signatureData: 'Jane Doe',
        signatureType: 'typed',
        consumerProtectionAcknowledged: true,
        termsAccepted: true,
        legalAcknowledged: true,
      }

      const result = await signServiceAgreement(request)

      expect(result.success).toBe(true)
      expect(result.signatureId).toBe('sig-001')
      expect(result.agreementStatus).toBe('active')
      expect(result.allSignaturesComplete).toBe(true)
    })

    it('should sign a service agreement with drawn signature', async () => {
      const mockResponse = createMockSignResponse()

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const request: SignServiceAgreementRequest = {
        agreementId: 'sa-001',
        signatureData: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
        signatureType: 'drawn',
        consumerProtectionAcknowledged: true,
        termsAccepted: true,
        legalAcknowledged: true,
      }

      const result = await signServiceAgreement(request)

      expect(result.success).toBe(true)
    })

    it('should include annex signatures in request', async () => {
      const mockResponse = createMockSignResponse()

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const request: SignServiceAgreementRequest = {
        agreementId: 'sa-001',
        signatureData: 'Jane Doe',
        signatureType: 'typed',
        consumerProtectionAcknowledged: true,
        termsAccepted: true,
        legalAcknowledged: true,
        annexSignatures: [
          { annexId: 'annex-a-001', signed: true },
          { annexId: 'annex-b-001', signed: false },
        ],
      }

      await signServiceAgreement(request)

      const callBody = JSON.parse((global.fetch as ReturnType<typeof vi.fn>).mock.calls[0][1].body)
      expect(callBody.annex_signatures).toBeDefined()
      expect(callBody.annex_signatures).toHaveLength(2)
      expect(callBody.annex_signatures[0].annex_id).toBe('annex-a-001')
      expect(callBody.annex_signatures[0].signed).toBe(true)
    })

    it('should send correct request body', async () => {
      const mockResponse = createMockSignResponse()

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const request: SignServiceAgreementRequest = {
        agreementId: 'sa-001',
        signatureData: 'Jane Doe',
        signatureType: 'typed',
        consumerProtectionAcknowledged: true,
        termsAccepted: true,
        legalAcknowledged: true,
      }

      await signServiceAgreement(request)

      const callBody = JSON.parse((global.fetch as ReturnType<typeof vi.fn>).mock.calls[0][1].body)
      expect(callBody.signature_data).toBe('Jane Doe')
      expect(callBody.signature_type).toBe('typed')
      expect(callBody.consumer_protection_acknowledged).toBe(true)
      expect(callBody.terms_accepted).toBe(true)
      expect(callBody.legal_acknowledged).toBe(true)
    })

    it('should call correct endpoint', async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => createMockSignResponse(),
      })

      await signServiceAgreement({
        agreementId: 'sa-xyz',
        signatureData: 'Test',
        signatureType: 'typed',
        consumerProtectionAcknowledged: true,
        termsAccepted: true,
        legalAcknowledged: true,
      })

      const callUrl = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0][0]
      expect(callUrl).toContain('/api/v1/service-agreements/sa-xyz/sign')
    })

    it('should return PDF URL after all signatures complete', async () => {
      const mockResponse = createMockSignResponse({
        allSignaturesComplete: true,
        pdfUrl: '/api/v1/service-agreements/sa-001/pdf',
      })

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await signServiceAgreement({
        agreementId: 'sa-001',
        signatureData: 'Jane Doe',
        signatureType: 'typed',
        consumerProtectionAcknowledged: true,
        termsAccepted: true,
        legalAcknowledged: true,
      })

      expect(result.allSignaturesComplete).toBe(true)
      expect(result.pdfUrl).toBe('/api/v1/service-agreements/sa-001/pdf')
    })

    it('should handle validation errors', async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 422,
        statusText: 'Unprocessable Entity',
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({ detail: 'Consumer protection acknowledgment is required' }),
      })

      await expect(
        signServiceAgreement({
          agreementId: 'sa-001',
          signatureData: 'Jane Doe',
          signatureType: 'typed',
          consumerProtectionAcknowledged: false,
          termsAccepted: true,
          legalAcknowledged: true,
        })
      ).rejects.toThrow()
    })
  })

  // ==========================================================================
  // getServiceAgreementPdfUrl Tests
  // ==========================================================================

  describe('getServiceAgreementPdfUrl', () => {
    it('should return correct PDF URL', () => {
      const url = getServiceAgreementPdfUrl('sa-001')

      expect(url).toContain('/api/v1/service-agreements/sa-001/pdf')
    })

    it('should include base URL', () => {
      const url = getServiceAgreementPdfUrl('sa-001')

      // Should contain the base gibbon URL
      expect(url).toMatch(/^http:\/\/.*\/api\/v1\/service-agreements\/sa-001\/pdf$/)
    })

    it('should handle different agreement IDs', () => {
      const url1 = getServiceAgreementPdfUrl('agreement-123')
      const url2 = getServiceAgreementPdfUrl('agreement-456')

      expect(url1).toContain('agreement-123')
      expect(url2).toContain('agreement-456')
      expect(url1).not.toBe(url2)
    })
  })

  // ==========================================================================
  // getPendingServiceAgreementsCount Tests
  // ==========================================================================

  describe('getPendingServiceAgreementsCount', () => {
    it('should return count of pending agreements', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [createMockServiceAgreementSummary({ status: 'pending_signature' })],
        total: 5,
        skip: 0,
        limit: 1,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const count = await getPendingServiceAgreementsCount()

      expect(count).toBe(5)
    })

    it('should filter by pending_signature status', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [],
        total: 0,
        skip: 0,
        limit: 1,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      await getPendingServiceAgreementsCount()

      const callUrl = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0][0]
      expect(callUrl).toContain('status=pending_signature')
    })

    it('should return 0 when no pending agreements', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [],
        total: 0,
        skip: 0,
        limit: 1,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const count = await getPendingServiceAgreementsCount()

      expect(count).toBe(0)
    })
  })

  // ==========================================================================
  // getServiceAgreementsRequiringSignature Tests
  // ==========================================================================

  describe('getServiceAgreementsRequiringSignature', () => {
    it('should return agreements without parent signature', async () => {
      const mockAgreements = [
        createMockServiceAgreementSummary({
          id: 'sa-001',
          status: 'pending_signature',
          parentSignedAt: undefined,
        }),
        createMockServiceAgreementSummary({
          id: 'sa-002',
          status: 'pending_signature',
          parentSignedAt: '2024-01-15T10:00:00Z', // Already signed
        }),
        createMockServiceAgreementSummary({
          id: 'sa-003',
          status: 'pending_signature',
          parentSignedAt: undefined,
        }),
      ]

      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: mockAgreements,
        total: 3,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await getServiceAgreementsRequiringSignature()

      // Should filter out sa-002 which already has parent signature
      expect(result).toHaveLength(2)
      expect(result.find((a) => a.id === 'sa-002')).toBeUndefined()
      expect(result.find((a) => a.id === 'sa-001')).toBeDefined()
      expect(result.find((a) => a.id === 'sa-003')).toBeDefined()
    })

    it('should return empty array when all agreements are signed', async () => {
      const mockAgreements = [
        createMockServiceAgreementSummary({
          status: 'pending_signature',
          parentSignedAt: '2024-01-15T10:00:00Z',
        }),
      ]

      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: mockAgreements,
        total: 1,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await getServiceAgreementsRequiringSignature()

      expect(result).toHaveLength(0)
    })
  })

  // ==========================================================================
  // getActiveServiceAgreementsForChild Tests
  // ==========================================================================

  describe('getActiveServiceAgreementsForChild', () => {
    it('should fetch active agreements for a specific child', async () => {
      const mockAgreements = [
        createMockServiceAgreementSummary({
          id: 'sa-001',
          childId: 'child-123',
          status: 'active',
        }),
      ]

      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: mockAgreements,
        total: 1,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await getActiveServiceAgreementsForChild('child-123')

      expect(result).toHaveLength(1)
      expect(result[0].childId).toBe('child-123')
      expect(result[0].status).toBe('active')
    })

    it('should pass childId and status filters', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [],
        total: 0,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      await getActiveServiceAgreementsForChild('child-abc')

      const callUrl = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0][0]
      expect(callUrl).toContain('child_id=child-abc')
      expect(callUrl).toContain('status=active')
    })

    it('should return empty array when no active agreements exist', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [],
        total: 0,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await getActiveServiceAgreementsForChild('child-no-agreements')

      expect(result).toHaveLength(0)
    })
  })

  // ==========================================================================
  // Error Handling Tests
  // ==========================================================================

  describe('Error Handling', () => {
    it('should handle network errors', async () => {
      global.fetch = vi.fn().mockRejectedValue(new Error('Network error'))

      await expect(getServiceAgreements()).rejects.toThrow()
    })

    it('should handle 401 Unauthorized errors', async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 401,
        statusText: 'Unauthorized',
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({ detail: 'Unauthorized' }),
      })

      await expect(getServiceAgreement('sa-001')).rejects.toThrow()
    })

    it('should handle 403 Forbidden errors', async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 403,
        statusText: 'Forbidden',
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({ detail: 'Access denied' }),
      })

      await expect(getServiceAgreement('sa-001')).rejects.toThrow()
    })

    it('should handle 500 Server errors', async () => {
      global.fetch = vi.fn().mockResolvedValue({
        ok: false,
        status: 500,
        statusText: 'Internal Server Error',
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => ({ detail: 'Internal server error' }),
      })

      await expect(getServiceAgreements()).rejects.toThrow()
    })
  })

  // ==========================================================================
  // Service Agreement Status Tests
  // ==========================================================================

  describe('Service Agreement Statuses', () => {
    it('should handle draft status', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [createMockServiceAgreementSummary({ status: 'draft' })],
        total: 1,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await getServiceAgreements({ status: 'draft' })

      expect(result.items[0].status).toBe('draft')
    })

    it('should handle pending_signature status', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [createMockServiceAgreementSummary({ status: 'pending_signature' })],
        total: 1,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await getServiceAgreements({ status: 'pending_signature' })

      expect(result.items[0].status).toBe('pending_signature')
    })

    it('should handle active status', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [createMockServiceAgreementSummary({ status: 'active' })],
        total: 1,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await getServiceAgreements({ status: 'active' })

      expect(result.items[0].status).toBe('active')
    })

    it('should handle expired status', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [createMockServiceAgreementSummary({ status: 'expired' })],
        total: 1,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await getServiceAgreements({ status: 'expired' })

      expect(result.items[0].status).toBe('expired')
    })

    it('should handle terminated status', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [createMockServiceAgreementSummary({ status: 'terminated' })],
        total: 1,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await getServiceAgreements({ status: 'terminated' })

      expect(result.items[0].status).toBe('terminated')
    })

    it('should handle cancelled status', async () => {
      const mockResponse: PaginatedResponse<ServiceAgreementSummary> = {
        items: [createMockServiceAgreementSummary({ status: 'cancelled' })],
        total: 1,
        skip: 0,
        limit: 10,
      }

      global.fetch = vi.fn().mockResolvedValue({
        ok: true,
        status: 200,
        headers: new Headers({ 'content-type': 'application/json' }),
        json: async () => mockResponse,
      })

      const result = await getServiceAgreements({ status: 'cancelled' })

      expect(result.items[0].status).toBe('cancelled')
    })
  })
})
