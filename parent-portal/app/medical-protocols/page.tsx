'use client';

import { useState, useEffect } from 'react';
import Link from 'next/link';
import { MedicalProtocolCard } from '@/components/MedicalProtocolCard';
import { ProtocolAuthorizationForm } from '@/components/ProtocolAuthorizationForm';
import type {
  Child,
  ChildProtocolOverview,
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
      lastAdministeredAt: '2024-02-10T14:30:00Z',
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

type FilterType = 'all' | 'authorized' | 'pending' | 'action-required';

export default function MedicalProtocolsPage() {
  const [children] = useState<Child[]>(mockChildren);
  const [selectedChildId, setSelectedChildId] = useState<string>(mockChildren[0]?.id || '');
  const [protocolSummaries, setProtocolSummaries] = useState<ProtocolSummary[]>([]);
  const [filter, setFilter] = useState<FilterType>('all');
  const [signingProtocol, setSigningProtocol] = useState<MedicalProtocol | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);

  // Get selected child info
  const selectedChild = children.find((c) => c.id === selectedChildId);
  const selectedChildName = selectedChild
    ? `${selectedChild.firstName} ${selectedChild.lastName}`
    : '';

  // Load protocol summaries when child changes
  useEffect(() => {
    if (selectedChildId) {
      // In production, this would be an API call
      setProtocolSummaries(mockProtocolSummaries[selectedChildId] || []);
    }
  }, [selectedChildId]);

  // Filter protocols based on selected filter
  const filteredProtocols = protocolSummaries.filter((protocol) => {
    switch (filter) {
      case 'authorized':
        return protocol.authorizationStatus === 'active';
      case 'pending':
        return protocol.authorizationStatus === 'pending';
      case 'action-required':
        return (
          !protocol.authorizationStatus ||
          protocol.authorizationStatus === 'expired' ||
          protocol.authorizationStatus === 'pending' ||
          protocol.isWeightExpired === true
        );
      default:
        return true;
    }
  });

  // Calculate counts
  const authorizedCount = protocolSummaries.filter(
    (p) => p.authorizationStatus === 'active'
  ).length;
  const pendingCount = protocolSummaries.filter(
    (p) => p.authorizationStatus === 'pending'
  ).length;
  const actionRequiredCount = protocolSummaries.filter(
    (p) =>
      !p.authorizationStatus ||
      p.authorizationStatus === 'expired' ||
      p.authorizationStatus === 'pending' ||
      p.isWeightExpired === true
  ).length;

  // Handle opening authorization modal
  const handleAuthorize = (protocolId: string) => {
    const protocol = mockProtocols.find((p) => p.id === protocolId);
    if (protocol) {
      setSigningProtocol(protocol);
      setIsModalOpen(true);
    }
  };

  // Handle closing modal
  const handleCloseModal = () => {
    setIsModalOpen(false);
    setSigningProtocol(null);
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
  };

  // Handle weight update
  const handleUpdateWeight = (protocolId: string) => {
    // In production, this would open a weight update modal
    // For now, we'll just authorize the protocol
    handleAuthorize(protocolId);
  };

  // Handle view details
  const handleViewDetails = (protocolId: string) => {
    // In production, this would navigate to protocol details page
    // For now, just log the action
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Medical Protocols</h1>
            <p className="mt-1 text-sm text-gray-600">
              Manage medical protocol authorizations for your children
            </p>
          </div>
          <Link href="/" className="btn btn-outline self-start">
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
            Back to Dashboard
          </Link>
        </div>
      </div>

      {/* Child Selector */}
      <div className="mb-6">
        <label className="block text-sm font-medium text-gray-700 mb-2">
          Select Child
        </label>
        <div className="flex flex-wrap gap-2">
          {children.map((child) => (
            <button
              key={child.id}
              type="button"
              onClick={() => setSelectedChildId(child.id)}
              className={`flex items-center space-x-2 px-4 py-2 rounded-lg border transition-colors ${
                selectedChildId === child.id
                  ? 'bg-primary-50 border-primary-300 text-primary-700'
                  : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'
              }`}
            >
              <div className="flex h-8 w-8 items-center justify-center rounded-full bg-gray-100 overflow-hidden">
                {child.profilePhotoUrl ? (
                  <img
                    src={child.profilePhotoUrl}
                    alt={`${child.firstName}'s photo`}
                    className="h-full w-full object-cover"
                  />
                ) : (
                  <svg
                    className="h-4 w-4 text-gray-400"
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
              <span className="font-medium">{child.firstName}</span>
            </button>
          ))}
        </div>
      </div>

      {/* Summary Cards */}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {/* Authorized Protocols */}
        <div className="card p-4">
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-green-100">
              <svg
                className="h-5 w-5 text-green-600"
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
            <div>
              <p className="text-sm text-gray-500">Authorized</p>
              <p className="text-xl font-bold text-green-600">{authorizedCount}</p>
            </div>
          </div>
        </div>

        {/* Pending Authorizations */}
        <div className="card p-4">
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-yellow-100">
              <svg
                className="h-5 w-5 text-yellow-600"
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
            </div>
            <div>
              <p className="text-sm text-gray-500">Pending</p>
              <p className="text-xl font-bold text-yellow-600">{pendingCount}</p>
            </div>
          </div>
        </div>

        {/* Action Required */}
        <div className="card p-4">
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-100">
              <svg
                className="h-5 w-5 text-red-600"
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
            <div>
              <p className="text-sm text-gray-500">Action Required</p>
              <p className="text-xl font-bold text-red-600">{actionRequiredCount}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Filter tabs */}
      <div className="mb-6 flex items-center justify-between">
        <h2 className="section-title">Protocol Authorizations</h2>
        <div className="flex items-center space-x-2">
          <button
            type="button"
            onClick={() => setFilter('all')}
            className={`btn text-sm ${
              filter === 'all' ? 'btn-primary' : 'btn-outline'
            }`}
          >
            All
          </button>
          <button
            type="button"
            onClick={() => setFilter('authorized')}
            className={`btn text-sm ${
              filter === 'authorized' ? 'btn-primary' : 'btn-outline'
            }`}
          >
            Authorized
          </button>
          <button
            type="button"
            onClick={() => setFilter('action-required')}
            className={`btn text-sm ${
              filter === 'action-required' ? 'btn-primary' : 'btn-outline'
            }`}
          >
            Action Required
            {actionRequiredCount > 0 && (
              <span className="ml-2 inline-flex items-center justify-center h-5 w-5 rounded-full bg-red-100 text-red-800 text-xs font-medium">
                {actionRequiredCount}
              </span>
            )}
          </button>
        </div>
      </div>

      {/* Action required notice */}
      {actionRequiredCount > 0 && filter !== 'authorized' && (
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
              <h3 className="text-sm font-medium text-red-800">
                Action Required
              </h3>
              <p className="mt-1 text-sm text-red-700">
                {selectedChild?.firstName} has {actionRequiredCount} protocol
                {actionRequiredCount !== 1 ? 's' : ''} requiring your attention.
                Please sign authorizations or update weight information to ensure
                your child can receive care when needed.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Protocol info box */}
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
              These protocols follow Quebec Ministry of Families guidelines.
              <strong> FO-0647</strong> covers acetaminophen administration for fever/pain,
              and <strong> FO-0646</strong> covers insect repellent application.
              Weight must be updated every 3 months for medication dosing accuracy.
            </p>
          </div>
        </div>
      </div>

      {/* Protocol list */}
      {filteredProtocols.length > 0 ? (
        <div className="space-y-4">
          {filteredProtocols.map((protocol) => (
            <MedicalProtocolCard
              key={protocol.protocolId}
              protocol={protocol}
              childName={selectedChildName}
              onAuthorize={handleAuthorize}
              onUpdateWeight={handleUpdateWeight}
              onViewDetails={handleViewDetails}
            />
          ))}
        </div>
      ) : (
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
            No protocols found
          </h3>
          <p className="mt-2 text-sm text-gray-500">
            {filter === 'authorized'
              ? `${selectedChild?.firstName || 'This child'} doesn't have any authorized protocols yet.`
              : filter === 'action-required'
                ? 'All protocols are up to date. Great job!'
                : 'No medical protocols are available at this time.'}
          </p>
          {filter !== 'all' && (
            <button
              type="button"
              onClick={() => setFilter('all')}
              className="mt-4 btn btn-outline"
            >
              View All Protocols
            </button>
          )}
        </div>
      )}

      {/* Authorization Modal */}
      <ProtocolAuthorizationForm
        protocol={signingProtocol}
        childId={selectedChildId}
        childName={selectedChildName}
        isOpen={isModalOpen}
        onClose={handleCloseModal}
        onSubmit={handleSubmitAuthorization}
      />
    </div>
  );
}
