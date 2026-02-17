'use client';

import { useState, useEffect } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { ProtocolAuthorizationForm } from '@/components/ProtocolAuthorizationForm';
import type {
  Child,
  MedicalProtocol,
  ProtocolSummary,
  ProtocolAuthorizationStatus,
  CreateProtocolAuthorizationRequest,
} from '@/lib/types';

// Mock data - will be replaced with API calls
const mockChildren: Child[] = [
  {
    id: 'child-1',
    firstName: 'Emma',
    lastName: 'Johnson',
    dateOfBirth: '2021-03-15',
    profilePhotoUrl: '/avatars/emma.jpg',
    classroomId: 'classroom-1',
    classroomName: 'Butterflies',
  },
  {
    id: 'child-2',
    firstName: 'Noah',
    lastName: 'Johnson',
    dateOfBirth: '2023-08-22',
    profilePhotoUrl: '/avatars/noah.jpg',
    classroomId: 'classroom-2',
    classroomName: 'Ladybugs',
  },
];

const mockProtocols: MedicalProtocol[] = [
  {
    id: 'protocol-1',
    name: 'Acetaminophen Administration',
    formCode: 'FO-0647',
    type: 'medication',
    description: 'Quebec-mandated protocol for acetaminophen administration for fever/pain relief',
    minimumAgeMonths: 0,
    requiresWeight: true,
    requiresTemperature: true,
    minimumIntervalHours: 4,
    maxDailyDoses: 5,
    isActive: true,
  },
  {
    id: 'protocol-2',
    name: 'Insect Repellent Application',
    formCode: 'FO-0646',
    type: 'topical',
    description: 'Quebec-mandated protocol for insect repellent application during outdoor activities',
    minimumAgeMonths: 6,
    requiresWeight: false,
    requiresTemperature: false,
    isActive: true,
  },
];

// Mock protocol summaries for a child
const mockProtocolSummaries: Record<string, ProtocolSummary[]> = {
  'child-1': [
    {
      protocolId: 'protocol-1',
      protocolName: 'Acetaminophen Administration',
      protocolFormCode: 'FO-0647',
      protocolType: 'medication',
      authorizationStatus: 'active',
      lastAuthorizedAt: '2024-01-15T10:00:00Z',
      weightKg: 15.2,
      weightRecordedAt: '2024-01-15T10:00:00Z',
      isWeightExpired: false,
      canAdminister: true,
    },
    {
      protocolId: 'protocol-2',
      protocolName: 'Insect Repellent Application',
      protocolFormCode: 'FO-0646',
      protocolType: 'topical',
      authorizationStatus: 'pending',
      canAdminister: false,
    },
  ],
  'child-2': [
    {
      protocolId: 'protocol-1',
      protocolName: 'Acetaminophen Administration',
      protocolFormCode: 'FO-0647',
      protocolType: 'medication',
      authorizationStatus: null,
      canAdminister: false,
    },
    {
      protocolId: 'protocol-2',
      protocolName: 'Insect Repellent Application',
      protocolFormCode: 'FO-0646',
      protocolType: 'topical',
      authorizationStatus: 'expired',
      lastAuthorizedAt: '2023-06-01T10:00:00Z',
      canAdminister: false,
    },
  ],
};

/**
 * Calculate age in months from date of birth.
 */
function calculateAgeInMonths(dateOfBirth: string): number {
  const birth = new Date(dateOfBirth);
  const now = new Date();
  const months =
    (now.getFullYear() - birth.getFullYear()) * 12 +
    (now.getMonth() - birth.getMonth());
  return months;
}

/**
 * Format date for display.
 */
function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

/**
 * Calculate weight expiry date (3 months from recorded date).
 */
function calculateWeightExpiryDate(weightRecordedAt: string): Date {
  const recordedDate = new Date(weightRecordedAt);
  const expiryDate = new Date(recordedDate);
  expiryDate.setMonth(expiryDate.getMonth() + 3);
  return expiryDate;
}

/**
 * Check if weight is expired (>3 months old).
 */
function isWeightStale(weightRecordedAt: string): boolean {
  const expiryDate = calculateWeightExpiryDate(weightRecordedAt);
  return new Date() > expiryDate;
}

/**
 * Get days until weight expires.
 */
function getDaysUntilWeightExpiry(weightRecordedAt: string): number {
  const expiryDate = calculateWeightExpiryDate(weightRecordedAt);
  const now = new Date();
  const diffTime = expiryDate.getTime() - now.getTime();
  const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
  return diffDays;
}

/**
 * Get protocol type icon.
 */
function getProtocolTypeIcon(type: 'medication' | 'topical'): React.ReactNode {
  switch (type) {
    case 'medication':
      return (
        <svg
          className="h-6 w-6 text-red-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"
          />
        </svg>
      );
    case 'topical':
      return (
        <svg
          className="h-6 w-6 text-green-600"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"
          />
        </svg>
      );
  }
}

/**
 * Get protocol type background color class.
 */
function getProtocolTypeBgColor(type: 'medication' | 'topical'): string {
  return type === 'medication' ? 'bg-red-100' : 'bg-green-100';
}

/**
 * Get authorization status badge.
 */
function getAuthorizationStatusBadge(
  status: ProtocolAuthorizationStatus | null
): React.ReactNode {
  switch (status) {
    case 'active':
      return (
        <span className="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
          Active
        </span>
      );
    case 'pending':
      return (
        <span className="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-800">
          Pending
        </span>
      );
    case 'expired':
      return (
        <span className="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800">
          Expired
        </span>
      );
    case 'revoked':
      return (
        <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
          Revoked
        </span>
      );
    default:
      return (
        <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
          Not Authorized
        </span>
      );
  }
}

export default function AuthorizePage() {
  const params = useParams();
  const router = useRouter();
  const childId = params.childId as string;

  const [child, setChild] = useState<Child | null>(null);
  const [protocols, setProtocols] = useState<MedicalProtocol[]>([]);
  const [protocolSummaries, setProtocolSummaries] = useState<ProtocolSummary[]>([]);
  const [selectedProtocol, setSelectedProtocol] = useState<MedicalProtocol | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [successMessage, setSuccessMessage] = useState<string | null>(null);

  // Load child data and protocols on mount
  useEffect(() => {
    const loadData = async () => {
      setIsLoading(true);
      setError(null);

      try {
        // In production, these would be API calls
        const foundChild = mockChildren.find((c) => c.id === childId);
        if (!foundChild) {
          setError('Child not found');
          setIsLoading(false);
          return;
        }

        setChild(foundChild);
        setProtocols(mockProtocols);
        setProtocolSummaries(mockProtocolSummaries[childId] || []);
      } catch {
        setError('Failed to load data. Please try again.');
      } finally {
        setIsLoading(false);
      }
    };

    if (childId) {
      loadData();
    }
  }, [childId]);

  // Get child's full name
  const childName = child ? `${child.firstName} ${child.lastName}` : '';
  const childAgeMonths = child ? calculateAgeInMonths(child.dateOfBirth) : 0;

  // Handle opening authorization modal
  const handleAuthorize = (protocol: MedicalProtocol) => {
    // Check age restriction for insect repellent
    if (
      protocol.minimumAgeMonths &&
      childAgeMonths < protocol.minimumAgeMonths
    ) {
      setError(
        `${child?.firstName} must be at least ${protocol.minimumAgeMonths} months old for this protocol. Current age: ${childAgeMonths} months.`
      );
      return;
    }

    setSelectedProtocol(protocol);
    setIsModalOpen(true);
    setSuccessMessage(null);
    setError(null);
  };

  // Handle closing modal
  const handleCloseModal = () => {
    setIsModalOpen(false);
    setSelectedProtocol(null);
  };

  // Handle authorization submission
  const handleSubmitAuthorization = (request: CreateProtocolAuthorizationRequest) => {
    // In production, this would call the API
    // Update local state to reflect the new authorization
    const now = new Date().toISOString();
    setProtocolSummaries((prev) =>
      prev.map((p) =>
        p.protocolId === request.protocolId
          ? {
              ...p,
              authorizationStatus: 'active' as ProtocolAuthorizationStatus,
              lastAuthorizedAt: now,
              weightKg: request.weightKg || p.weightKg,
              weightRecordedAt: request.weightKg ? now : p.weightRecordedAt,
              isWeightExpired: false,
              canAdminister: true,
            }
          : p
      )
    );

    handleCloseModal();
    setSuccessMessage(
      `Successfully authorized ${selectedProtocol?.name} for ${child?.firstName}.`
    );

    // Clear success message after a few seconds
    setTimeout(() => {
      setSuccessMessage(null);
    }, 5000);
  };

  // Get protocol summary for a given protocol
  const getProtocolSummary = (protocolId: string): ProtocolSummary | undefined => {
    return protocolSummaries.find((p) => p.protocolId === protocolId);
  };

  // Check if a protocol needs authorization
  const needsAuthorization = (protocolId: string): boolean => {
    const summary = getProtocolSummary(protocolId);
    return (
      !summary?.authorizationStatus ||
      summary.authorizationStatus === 'expired' ||
      summary.authorizationStatus === 'revoked'
    );
  };

  // Loading state
  if (isLoading) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="animate-pulse">
          <div className="h-8 bg-gray-200 rounded w-1/3 mb-4" />
          <div className="h-4 bg-gray-200 rounded w-1/2 mb-8" />
          <div className="space-y-4">
            <div className="h-32 bg-gray-200 rounded" />
            <div className="h-32 bg-gray-200 rounded" />
          </div>
        </div>
      </div>
    );
  }

  // Error state for child not found
  if (error && !child) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="rounded-lg bg-red-50 border border-red-200 p-6 text-center">
          <svg
            className="mx-auto h-12 w-12 text-red-400"
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
          <h3 className="mt-4 text-lg font-medium text-red-800">{error}</h3>
          <p className="mt-2 text-sm text-red-600">
            The child you are looking for could not be found.
          </p>
          <Link
            href="/medical-protocols"
            className="mt-4 inline-block btn btn-primary"
          >
            Go to Medical Protocols
          </Link>
        </div>
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Authorize Medical Protocols
            </h1>
            <p className="mt-1 text-sm text-gray-600">
              Sign authorizations for {childName}
            </p>
          </div>
          <Link href="/medical-protocols" className="btn btn-outline self-start">
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
            Back to Protocols
          </Link>
        </div>
      </div>

      {/* Child Info Card */}
      <div className="mb-6 card p-6">
        <div className="flex items-center space-x-4">
          <div className="flex h-16 w-16 items-center justify-center rounded-full bg-blue-100 overflow-hidden">
            {child?.profilePhotoUrl ? (
              <img
                src={child.profilePhotoUrl}
                alt={`${child.firstName}'s photo`}
                className="h-full w-full object-cover"
              />
            ) : (
              <svg
                className="h-8 w-8 text-blue-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                />
              </svg>
            )}
          </div>
          <div>
            <h2 className="text-lg font-semibold text-gray-900">{childName}</h2>
            <p className="text-sm text-gray-500">
              {child?.classroomName} | {childAgeMonths} months old
            </p>
          </div>
        </div>
      </div>

      {/* Success Message */}
      {successMessage && (
        <div className="mb-6 rounded-lg bg-green-50 border border-green-200 p-4">
          <div className="flex">
            <svg
              className="h-5 w-5 text-green-400 flex-shrink-0"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 13l4 4L19 7"
              />
            </svg>
            <div className="ml-3">
              <h3 className="text-sm font-medium text-green-800">
                Authorization Successful
              </h3>
              <p className="mt-1 text-sm text-green-700">{successMessage}</p>
            </div>
            <button
              type="button"
              onClick={() => setSuccessMessage(null)}
              className="ml-auto text-green-400 hover:text-green-600"
            >
              <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fillRule="evenodd"
                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                  clipRule="evenodd"
                />
              </svg>
            </button>
          </div>
        </div>
      )}

      {/* Error Message */}
      {error && child && (
        <div className="mb-6 rounded-lg bg-red-50 border border-red-200 p-4">
          <div className="flex">
            <svg
              className="h-5 w-5 text-red-400 flex-shrink-0"
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
            <div className="ml-3">
              <h3 className="text-sm font-medium text-red-800">Error</h3>
              <p className="mt-1 text-sm text-red-700">{error}</p>
            </div>
            <button
              type="button"
              onClick={() => setError(null)}
              className="ml-auto text-red-400 hover:text-red-600"
            >
              <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fillRule="evenodd"
                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                  clipRule="evenodd"
                />
              </svg>
            </button>
          </div>
        </div>
      )}

      {/* Weight Expiry Warning */}
      {protocolSummaries.some(
        (p) => p.weightRecordedAt && isWeightStale(p.weightRecordedAt)
      ) && (
        <div className="mb-6 rounded-lg bg-amber-50 border border-amber-200 p-4">
          <div className="flex">
            <svg
              className="h-5 w-5 text-amber-400 flex-shrink-0"
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
            <div className="ml-3">
              <h3 className="text-sm font-medium text-amber-800">
                Weight Update Required
              </h3>
              <p className="mt-1 text-sm text-amber-700">
                {child?.firstName}'s weight for medication protocols is more than 3 months old.
                Quebec regulations require weight to be updated every 3 months for accurate
                medication dosing. Please update the weight before authorizing medication protocols.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Quebec Protocol Info */}
      <div className="mb-6 rounded-lg bg-blue-50 border border-blue-200 p-4">
        <div className="flex">
          <svg
            className="h-5 w-5 text-blue-400 flex-shrink-0"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
          <div className="ml-3">
            <h3 className="text-sm font-medium text-blue-800">
              Quebec Medical Protocols
            </h3>
            <p className="mt-1 text-sm text-blue-700">
              Select a protocol below to review and sign the authorization form.
              Your e-signature will be securely stored in compliance with Quebec
              childcare regulations.
            </p>
          </div>
        </div>
      </div>

      {/* Protocol Selection */}
      <h2 className="section-title mb-4">Available Protocols</h2>
      <div className="space-y-4">
        {protocols.map((protocol) => {
          const summary = getProtocolSummary(protocol.id);
          const needsAuth = needsAuthorization(protocol.id);
          const isAgeRestricted =
            protocol.minimumAgeMonths !== undefined &&
            childAgeMonths < protocol.minimumAgeMonths;

          return (
            <div key={protocol.id} className="card p-6">
              <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div className="flex items-start space-x-4">
                  <div
                    className={`flex h-12 w-12 items-center justify-center rounded-full ${getProtocolTypeBgColor(
                      protocol.type
                    )}`}
                  >
                    {getProtocolTypeIcon(protocol.type)}
                  </div>
                  <div className="flex-1">
                    <div className="flex items-center space-x-2 flex-wrap">
                      <h3 className="text-lg font-semibold text-gray-900">
                        {protocol.name}
                      </h3>
                      <span className="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                        {protocol.formCode}
                      </span>
                      {getAuthorizationStatusBadge(summary?.authorizationStatus || null)}
                    </div>
                    <p className="mt-1 text-sm text-gray-600">
                      {protocol.description}
                    </p>

                    {/* Protocol details */}
                    <div className="mt-3 flex flex-wrap gap-3 text-xs text-gray-500">
                      {protocol.requiresWeight && (
                        <span className="flex items-center">
                          <svg
                            className="mr-1 h-3 w-3"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth={2}
                              d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"
                            />
                          </svg>
                          Weight-based dosing
                        </span>
                      )}
                      {protocol.minimumIntervalHours && (
                        <span className="flex items-center">
                          <svg
                            className="mr-1 h-3 w-3"
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                          >
                            <path
                              strokeLinecap="round"
                              strokeLinejoin="round"
                              strokeWidth={2}
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                            />
                          </svg>
                          Min {protocol.minimumIntervalHours}h between doses
                        </span>
                      )}
                      {protocol.minimumAgeMonths !== undefined &&
                        protocol.minimumAgeMonths > 0 && (
                          <span className="flex items-center">
                            <svg
                              className="mr-1 h-3 w-3"
                              fill="none"
                              stroke="currentColor"
                              viewBox="0 0 24 24"
                            >
                              <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                              />
                            </svg>
                            Min age: {protocol.minimumAgeMonths} months
                          </span>
                        )}
                    </div>

                    {/* Weight info for authorized protocols */}
                    {summary?.authorizationStatus === 'active' && summary.weightKg && (
                      <div className="mt-3 space-y-1">
                        <div className="text-sm text-gray-600">
                          Authorized weight:{' '}
                          <span className="font-medium text-gray-900">
                            {summary.weightKg} kg
                          </span>
                        </div>
                        {summary.weightRecordedAt && (
                          <div className="text-xs text-gray-500">
                            Weight recorded: {formatDate(summary.weightRecordedAt)}
                            {' • '}
                            Expires: {formatDate(calculateWeightExpiryDate(summary.weightRecordedAt).toISOString())}
                            {!isWeightStale(summary.weightRecordedAt) && (
                              <span className="ml-1">
                                ({getDaysUntilWeightExpiry(summary.weightRecordedAt)} days remaining)
                              </span>
                            )}
                          </div>
                        )}
                        {summary.weightRecordedAt && isWeightStale(summary.weightRecordedAt) && (
                          <div className="flex items-center text-sm text-amber-600 font-medium">
                            <svg
                              className="mr-1.5 h-4 w-4"
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
                            Weight expired - update required before next administration
                          </div>
                        )}
                      </div>
                    )}

                    {/* Age restriction warning */}
                    {isAgeRestricted && (
                      <div className="mt-3 flex items-center text-sm text-amber-600">
                        <svg
                          className="mr-1.5 h-4 w-4"
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
                        {child?.firstName} is under the minimum age requirement ({childAgeMonths}/{protocol.minimumAgeMonths} months)
                      </div>
                    )}
                  </div>
                </div>

                {/* Action button */}
                <div className="flex-shrink-0">
                  {needsAuth ? (
                    <button
                      type="button"
                      onClick={() => handleAuthorize(protocol)}
                      disabled={isAgeRestricted}
                      className={`btn ${
                        isAgeRestricted
                          ? 'btn-outline opacity-50 cursor-not-allowed'
                          : 'btn-primary'
                      }`}
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
                          d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                        />
                      </svg>
                      Sign Authorization
                    </button>
                  ) : summary?.isWeightExpired ? (
                    <button
                      type="button"
                      onClick={() => handleAuthorize(protocol)}
                      className="btn btn-outline text-amber-600 border-amber-300 hover:bg-amber-50"
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
                          d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
                        />
                      </svg>
                      Update Weight
                    </button>
                  ) : (
                    <span className="inline-flex items-center text-sm text-green-600 font-medium">
                      <svg
                        className="mr-1.5 h-5 w-5"
                        fill="none"
                        stroke="currentColor"
                        viewBox="0 0 24 24"
                      >
                        <path
                          strokeLinecap="round"
                          strokeLinejoin="round"
                          strokeWidth={2}
                          d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                        />
                      </svg>
                      Authorized
                    </span>
                  )}
                </div>
              </div>
            </div>
          );
        })}
      </div>

      {/* No protocols message */}
      {protocols.length === 0 && (
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
                d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
              />
            </svg>
          </div>
          <h3 className="mt-4 text-lg font-medium text-gray-900">
            No protocols available
          </h3>
          <p className="mt-2 text-sm text-gray-500">
            There are no medical protocols available for authorization at this time.
          </p>
        </div>
      )}

      {/* Authorization Modal */}
      <ProtocolAuthorizationForm
        protocol={selectedProtocol}
        childId={childId}
        childName={childName}
        isOpen={isModalOpen}
        onClose={handleCloseModal}
        onSubmit={handleSubmitAuthorization}
      />
    </div>
  );
}
