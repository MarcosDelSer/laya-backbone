'use client';

import { useState, useEffect, useCallback } from 'react';
import { useRouter } from 'next/navigation';
import Link from 'next/link';
import { ServiceAgreementViewer } from '@/components/ServiceAgreementViewer';
import { ServiceAgreementSignature } from '@/components/ServiceAgreementSignature';
import type {
  ServiceAgreement,
  SignServiceAgreementRequest,
} from '@/lib/types';
import { getServiceAgreementPdfUrl } from '@/lib/gibbon-client';

// Mock signature type that matches what the viewer component actually uses
// Full ServiceAgreementSignature interface has more required fields for API storage
interface MockSignature {
  id: string;
  signedAt: string;
  signerRole: 'parent' | 'provider' | 'witness';
  signerName: string;
  signatureType: 'typed' | 'drawn';
  ipAddress: string;
  verificationStatus: 'pending' | 'verified' | 'failed';
}

// Mock annex type that matches what the viewer component actually uses
interface MockAnnex {
  id: string;
  type: 'A' | 'B' | 'C' | 'D';
  status: 'pending' | 'signed' | 'declined' | 'not_applicable';
  // Type A fields
  authorizeFieldTrips?: boolean;
  transportationAuthorized?: boolean;
  walkingDistanceAuthorized?: boolean;
  fieldTripConditions?: string;
  // Type B fields
  hygieneItemsIncluded?: boolean;
  itemsList?: string[];
  monthlyFee?: number;
  parentProvides?: string[];
  // Type C fields
  supplementaryMealsIncluded?: boolean;
  mealsIncluded?: string[];
  dietaryRestrictions?: string;
  allergyInfo?: string;
  // Type D fields
  extendedHoursRequired?: boolean;
  requestedStartTime?: string;
  requestedEndTime?: string;
  additionalHoursPerDay?: number;
  hourlyRate?: number;
  monthlyEstimate?: number;
  reason?: string;
  // Signature fields
  signedAt?: string;
  signedBy?: string;
}

// Mock data structure - uses partial types for development
// Will be replaced with actual API response that matches full ServiceAgreement interface
interface MockServiceAgreement {
  id: string;
  agreementNumber: string;
  status: 'draft' | 'pending_signature' | 'active' | 'expired' | 'terminated' | 'cancelled';
  schoolYearId: string;
  childId: string;
  childName: string;
  childDateOfBirth: string;
  parentId: string;
  parentName: string;
  parentAddress: string;
  parentPhone: string;
  parentEmail: string;
  providerId: string;
  providerName: string;
  providerAddress: string;
  providerPhone: string;
  providerPermitNumber?: string;
  serviceDescription: string;
  programType: string;
  ageGroup: string;
  classroomId?: string;
  classroomName?: string;
  operatingHours: {
    openTime: string;
    closeTime: string;
    operatingDays: ('monday' | 'tuesday' | 'wednesday' | 'thursday' | 'friday' | 'saturday' | 'sunday')[];
    maxDailyHours: number;
  };
  attendancePattern: {
    scheduledDays: ('monday' | 'tuesday' | 'wednesday' | 'thursday' | 'friday' | 'saturday' | 'sunday')[];
    arrivalTime: string;
    departureTime: string;
    isFullTime: boolean;
    daysPerWeek: number;
  };
  paymentTerms: {
    contributionType: 'reduced' | 'full_rate' | 'mixed';
    dailyRate: number;
    monthlyAmount: number;
    paymentDueDay: number;
    paymentMethod: string;
    depositAmount?: number;
    depositRefundable?: boolean;
    lateFeePercentage?: number;
    lateFeeGraceDays?: number;
    nsfFee?: number;
  };
  latePickupFees: {
    gracePeriodMinutes: number;
    feePerInterval: number;
    intervalMinutes: number;
    maxDailyFee: number;
  };
  closureDays: string[];
  holidaySchedule?: string;
  vacationWeeks?: number;
  absencePolicy: string;
  absenceNotificationRequired: boolean;
  absenceNotificationMethod?: string;
  sickDayPolicy?: string;
  startDate: string;
  endDate: string;
  autoRenewal: boolean;
  renewalNoticeRequired?: boolean;
  renewalNoticeDays?: number;
  terminationConditions: {
    noticePeriodDays: number;
    immediateTerminationReasons: string[];
    refundPolicy: string;
  };
  specialConditions?: string;
  specialNeedsAccommodations?: string;
  medicalConditions?: string;
  allergies?: string;
  emergencyContacts?: string;
  consumerProtectionAcknowledgment: {
    acknowledged: boolean;
    acknowledgedAt?: string;
    coolingOffPeriodEndDate?: string;
    coolingOffDaysRemaining?: number;
  };
  signatures: MockSignature[];
  annexes: MockAnnex[];
  parentSignedAt?: string;
  providerSignedAt?: string;
  allSignaturesComplete: boolean;
  pdfUrl?: string;
  notes?: string;
  createdAt: string;
  updatedAt: string;
}

// Mock data for development - will be replaced with actual API call
const mockAgreement: MockServiceAgreement = {
  id: 'sa-1',
  agreementNumber: 'SA-2024-001',
  status: 'pending_signature',
  schoolYearId: '2024-2025',
  childId: 'child-1',
  childName: 'Emma Johnson',
  childDateOfBirth: '2020-03-15',
  parentId: 'parent-1',
  parentName: 'Sarah Johnson',
  parentAddress: '123 Main Street, Montreal, QC H3A 1B1',
  parentPhone: '514-555-1234',
  parentEmail: 'sarah.johnson@email.com',
  providerId: 'provider-1',
  providerName: 'Little Stars Daycare',
  providerAddress: '456 Care Avenue, Montreal, QC H3B 2C2',
  providerPhone: '514-555-5678',
  providerPermitNumber: 'QC-CPE-2024-001',
  serviceDescription:
    'Full-time childcare services including educational activities, meals, and outdoor play.',
  programType: 'Full-Day Program',
  ageGroup: 'Toddler (18-36 months)',
  classroomName: 'Sunshine Room',
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
    contributionType: 'reduced',
    dailyRate: 9.35,
    monthlyAmount: 187.0,
    paymentDueDay: 1,
    paymentMethod: 'Pre-Authorized Debit',
    depositAmount: 200.0,
    depositRefundable: true,
    lateFeePercentage: 2,
    lateFeeGraceDays: 10,
    nsfFee: 35.0,
  },
  latePickupFees: {
    gracePeriodMinutes: 10,
    feePerInterval: 5.0,
    intervalMinutes: 15,
    maxDailyFee: 50.0,
  },
  closureDays: [
    'New Year\'s Day',
    'Good Friday',
    'Easter Monday',
    'Victoria Day',
    'Saint-Jean-Baptiste Day',
    'Canada Day',
    'Labour Day',
    'Thanksgiving',
    'Christmas Day',
    'Boxing Day',
  ],
  holidaySchedule: 'Standard Quebec statutory holidays plus 2 weeks winter break',
  vacationWeeks: 2,
  absencePolicy:
    'Parents must notify the daycare of any absence by 9:00 AM. Fees are still applicable for planned absences.',
  absenceNotificationRequired: true,
  absenceNotificationMethod: 'Phone call or parent portal',
  sickDayPolicy:
    'Children with fever, vomiting, or contagious illness must stay home until symptom-free for 24 hours.',
  startDate: '2024-09-01',
  endDate: '2025-06-30',
  autoRenewal: true,
  renewalNoticeRequired: true,
  renewalNoticeDays: 30,
  terminationConditions: {
    noticePeriodDays: 14,
    immediateTerminationReasons: [
      'Non-payment after 30 days',
      'Repeated policy violations',
      'Child safety concerns',
      'Fraudulent information',
    ],
    refundPolicy: 'Prorated refund for unused days, minus administrative fee.',
  },
  specialConditions: '',
  specialNeedsAccommodations: '',
  medicalConditions: '',
  allergies: 'No known allergies',
  emergencyContacts: 'John Johnson (Father) - 514-555-4321',
  consumerProtectionAcknowledgment: {
    acknowledged: false,
    acknowledgedAt: undefined,
    coolingOffPeriodEndDate: undefined,
    coolingOffDaysRemaining: undefined,
  },
  signatures: [
    {
      id: 'sig-1',
      signedAt: '2024-08-15T10:00:00Z',
      signerRole: 'provider',
      signerName: 'Little Stars Daycare (Marie Tremblay)',
      signatureType: 'typed',
      ipAddress: '192.168.1.100',
      verificationStatus: 'verified',
    },
  ],
  annexes: [
    {
      id: 'annex-a-1',
      type: 'A',
      status: 'pending',
      authorizeFieldTrips: true,
      transportationAuthorized: false,
      walkingDistanceAuthorized: true,
      fieldTripConditions: '',
    },
    {
      id: 'annex-b-1',
      type: 'B',
      status: 'pending',
      hygieneItemsIncluded: true,
      itemsList: ['Diapers', 'Wipes', 'Sunscreen'],
      monthlyFee: 25.0,
      parentProvides: [],
    },
    {
      id: 'annex-c-1',
      type: 'C',
      status: 'pending',
      supplementaryMealsIncluded: true,
      mealsIncluded: ['Lunch', 'Morning snack', 'Afternoon snack'],
      dietaryRestrictions: '',
      allergyInfo: '',
      monthlyFee: 0,
    },
    {
      id: 'annex-d-1',
      type: 'D',
      status: 'not_applicable',
      extendedHoursRequired: false,
    },
  ],
  parentSignedAt: undefined,
  providerSignedAt: '2024-08-15T10:00:00Z',
  allSignaturesComplete: false,
  pdfUrl: undefined,
  notes: '',
  createdAt: '2024-08-10T09:00:00Z',
  updatedAt: '2024-08-15T10:00:00Z',
};

interface PageParams {
  params: Promise<{
    id: string;
  }>;
}

export default function ServiceAgreementDetailPage({ params }: PageParams) {
  const router = useRouter();
  const [resolvedParams, setResolvedParams] = useState<{ id: string } | null>(null);
  // Using MockServiceAgreement for mock data; will be ServiceAgreement from API
  const [agreement, setAgreement] = useState<MockServiceAgreement | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSignModalOpen, setIsSignModalOpen] = useState(false);

  // Resolve params (Next.js 15 async params)
  useEffect(() => {
    params.then((p) => setResolvedParams(p));
  }, [params]);

  // Fetch agreement data
  useEffect(() => {
    if (!resolvedParams?.id) return;

    const fetchAgreement = async () => {
      setIsLoading(true);
      setError(null);

      try {
        // TODO: Replace with actual API call when backend is ready
        // const data = await getServiceAgreement(resolvedParams.id);
        // setAgreement(data);

        // For now, use mock data
        await new Promise((resolve) => setTimeout(resolve, 500));

        // Simulate finding the agreement by ID
        if (
          resolvedParams.id === 'sa-1' ||
          resolvedParams.id === mockAgreement.id
        ) {
          setAgreement(mockAgreement);
        } else if (resolvedParams.id === 'sa-2') {
          // Create a signed version for testing
          setAgreement({
            ...mockAgreement,
            id: 'sa-2',
            agreementNumber: 'SA-2024-002',
            status: 'active',
            childName: 'Liam Johnson',
            parentSignedAt: '2024-08-18T14:32:00Z',
            allSignaturesComplete: true,
            consumerProtectionAcknowledgment: {
              acknowledged: true,
              acknowledgedAt: '2024-08-18T14:32:00Z',
              coolingOffPeriodEndDate: '2024-08-28',
              coolingOffDaysRemaining: 0,
            },
            signatures: [
              ...mockAgreement.signatures,
              {
                id: 'sig-2',
                signedAt: '2024-08-18T14:32:00Z',
                signerRole: 'parent',
                signerName: 'Sarah Johnson',
                signatureType: 'drawn',
                ipAddress: '192.168.1.50',
                verificationStatus: 'verified',
              },
            ],
            pdfUrl: getServiceAgreementPdfUrl('sa-2'),
          });
        } else {
          setError('Service agreement not found.');
        }
      } catch (err) {
        setError(
          err instanceof Error
            ? err.message
            : 'Failed to load service agreement. Please try again.'
        );
      } finally {
        setIsLoading(false);
      }
    };

    fetchAgreement();
  }, [resolvedParams?.id]);

  // Handle signing the agreement
  const handleSign = useCallback(() => {
    setIsSignModalOpen(true);
  }, []);

  // Handle closing sign modal
  const handleCloseSignModal = useCallback(() => {
    setIsSignModalOpen(false);
  }, []);

  // Handle signature submission
  const handleSignSubmit = useCallback(
    async (request: SignServiceAgreementRequest): Promise<void> => {
      try {
        // TODO: Replace with actual API call when backend is ready
        // const response = await signServiceAgreement(request);

        // Simulate signing process
        await new Promise((resolve) => setTimeout(resolve, 1500));

        // Update local state with signed agreement
        setAgreement((prev) => {
          if (!prev) return prev;

          const now = new Date().toISOString();
          const newSignature = {
            id: `sig-${Date.now()}`,
            signedAt: now,
            signerRole: 'parent' as const,
            signerName: prev.parentName,
            signatureType: request.signatureType,
            ipAddress: '192.168.1.1', // Would come from server
            verificationStatus: 'verified' as const,
          };

          // Update annex statuses
          const updatedAnnexes = prev.annexes.map((annex) => {
            const annexSig = request.annexSignatures?.find(
              (s) => s.annexId === annex.id
            );
            if (annexSig?.signed) {
              return {
                ...annex,
                status: 'signed' as const,
                signedAt: now,
                signedBy: prev.parentName,
              };
            }
            return annex;
          });

          return {
            ...prev,
            status: 'active' as const,
            parentSignedAt: now,
            allSignaturesComplete: true,
            signatures: [...prev.signatures, newSignature],
            annexes: updatedAnnexes,
            consumerProtectionAcknowledgment: {
              acknowledged: true,
              acknowledgedAt: now,
              coolingOffPeriodEndDate: new Date(
                Date.now() + 10 * 24 * 60 * 60 * 1000
              )
                .toISOString()
                .split('T')[0],
              coolingOffDaysRemaining: 10,
            },
            pdfUrl: getServiceAgreementPdfUrl(prev.id),
          };
        });

        setIsSignModalOpen(false);
      } catch (err) {
        throw err instanceof Error
          ? err
          : new Error('Failed to sign agreement. Please try again.');
      }
    },
    []
  );

  // Handle navigation back
  const handleBack = useCallback(() => {
    router.push('/service-agreements');
  }, [router]);

  // Loading state
  if (isLoading || !resolvedParams) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        {/* Header skeleton */}
        <div className="mb-6">
          <div className="flex items-center justify-between">
            <div>
              <div className="h-8 w-48 skeleton mb-2" />
              <div className="h-4 w-32 skeleton" />
            </div>
            <div className="h-6 w-28 skeleton rounded-full" />
          </div>
          <div className="mt-4 flex flex-wrap gap-4">
            <div className="h-5 w-32 skeleton" />
            <div className="h-5 w-48 skeleton" />
          </div>
        </div>

        {/* Content skeleton */}
        <div className="space-y-4">
          {[1, 2, 3, 4, 5].map((i) => (
            <div key={i} className="border border-gray-200 rounded-lg p-4">
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center space-x-3">
                  <div className="h-7 w-7 rounded-full skeleton" />
                  <div className="h-5 w-40 skeleton" />
                </div>
                <div className="h-5 w-5 skeleton" />
              </div>
            </div>
          ))}
        </div>

        {/* Footer skeleton */}
        <div className="mt-8 flex flex-wrap gap-3 border-t border-gray-200 pt-6">
          <div className="h-10 w-36 skeleton rounded-lg" />
          <div className="h-10 w-36 skeleton rounded-lg" />
        </div>
      </div>
    );
  }

  // Error state
  if (error) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="rounded-lg bg-red-50 border border-red-200 p-8 text-center">
          <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
            <svg
              className="h-8 w-8 text-red-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
          </div>
          <h3 className="mt-4 text-lg font-medium text-red-900">{error}</h3>
          <p className="mt-2 text-sm text-red-700">
            Please try again or contact support if the problem persists.
          </p>
          <div className="mt-6 flex items-center justify-center gap-3">
            <button
              type="button"
              onClick={() => window.location.reload()}
              className="btn btn-outline"
            >
              Try Again
            </button>
            <Link href="/service-agreements" className="btn btn-primary">
              Back to Agreements
            </Link>
          </div>
        </div>
      </div>
    );
  }

  // Not found state
  if (!agreement) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="rounded-lg border-2 border-dashed border-gray-200 p-12 text-center">
          <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
            <svg
              className="h-8 w-8 text-gray-400"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
              />
            </svg>
          </div>
          <h3 className="mt-4 text-lg font-medium text-gray-900">
            Agreement Not Found
          </h3>
          <p className="mt-2 text-sm text-gray-500">
            The service agreement you&apos;re looking for doesn&apos;t exist or
            you don&apos;t have permission to view it.
          </p>
          <Link href="/service-agreements" className="mt-6 btn btn-primary">
            Back to Agreements
          </Link>
        </div>
      </div>
    );
  }

  // Main content
  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Back navigation */}
      <div className="mb-6">
        <Link
          href="/service-agreements"
          className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700"
        >
          <svg
            className="mr-2 h-4 w-4"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M10 19l-7-7m0 0l7-7m-7 7h18"
            />
          </svg>
          Back to Service Agreements
        </Link>
      </div>

      {/* Agreement viewer */}
      {/* Cast mock data to ServiceAgreement - actual API will return proper type */}
      <ServiceAgreementViewer
        agreement={agreement as unknown as ServiceAgreement}
        onClose={handleBack}
        onSign={handleSign}
      />

      {/* Signature modal */}
      <ServiceAgreementSignature
        agreement={agreement as unknown as ServiceAgreement}
        isOpen={isSignModalOpen}
        onClose={handleCloseSignModal}
        onSubmit={handleSignSubmit}
      />
    </div>
  );
}
