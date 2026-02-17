'use client';

import { useState } from 'react';
import Link from 'next/link';
import { ServiceAgreementCard } from '@/components/ServiceAgreementCard';
import type { ServiceAgreementSummary } from '@/lib/types';

// Mock data - will be replaced with API calls
const mockServiceAgreements: ServiceAgreementSummary[] = [
  {
    id: 'sa-1',
    agreementNumber: 'SA-2024-001',
    status: 'pending_signature',
    childId: 'child-1',
    childName: 'Emma Johnson',
    parentName: 'Sarah Johnson',
    startDate: '2024-09-01',
    endDate: '2025-06-30',
    allSignaturesComplete: false,
    parentSignedAt: undefined,
    providerSignedAt: '2024-08-15T10:00:00Z',
    createdAt: '2024-08-10T09:00:00Z',
    updatedAt: '2024-08-15T10:00:00Z',
  },
  {
    id: 'sa-2',
    agreementNumber: 'SA-2024-002',
    status: 'active',
    childId: 'child-2',
    childName: 'Liam Johnson',
    parentName: 'Sarah Johnson',
    startDate: '2024-09-01',
    endDate: '2025-06-30',
    allSignaturesComplete: true,
    parentSignedAt: '2024-08-18T14:32:00Z',
    providerSignedAt: '2024-08-15T10:00:00Z',
    createdAt: '2024-08-10T09:30:00Z',
    updatedAt: '2024-08-18T14:32:00Z',
  },
  {
    id: 'sa-3',
    agreementNumber: 'SA-2023-015',
    status: 'expired',
    childId: 'child-1',
    childName: 'Emma Johnson',
    parentName: 'Sarah Johnson',
    startDate: '2023-09-01',
    endDate: '2024-06-30',
    allSignaturesComplete: true,
    parentSignedAt: '2023-08-20T11:15:00Z',
    providerSignedAt: '2023-08-18T09:00:00Z',
    createdAt: '2023-08-15T08:00:00Z',
    updatedAt: '2024-06-30T23:59:59Z',
  },
  {
    id: 'sa-4',
    agreementNumber: 'SA-2024-003',
    status: 'draft',
    childId: 'child-3',
    childName: 'Olivia Johnson',
    parentName: 'Sarah Johnson',
    startDate: '2024-10-01',
    endDate: '2025-06-30',
    allSignaturesComplete: false,
    parentSignedAt: undefined,
    providerSignedAt: undefined,
    createdAt: '2024-09-01T10:00:00Z',
    updatedAt: '2024-09-01T10:00:00Z',
  },
];

type FilterType = 'all' | 'pending_signature' | 'active' | 'other';

export default function ServiceAgreementsPage() {
  const [agreements] = useState<ServiceAgreementSummary[]>(mockServiceAgreements);
  const [filter, setFilter] = useState<FilterType>('all');

  // Filter agreements based on selected filter
  const filteredAgreements = agreements.filter((agreement) => {
    if (filter === 'all') return true;
    if (filter === 'pending_signature') return agreement.status === 'pending_signature';
    if (filter === 'active') return agreement.status === 'active';
    if (filter === 'other') {
      return ['draft', 'expired', 'terminated', 'cancelled'].includes(agreement.status);
    }
    return true;
  });

  // Calculate counts
  const pendingCount = agreements.filter((a) => a.status === 'pending_signature').length;
  const activeCount = agreements.filter((a) => a.status === 'active').length;

  // Handle view agreement
  const handleViewAgreement = (agreementId: string) => {
    // TODO: Navigate to agreement detail page or open modal
    window.location.href = `/service-agreements/${agreementId}`;
  };

  // Handle sign agreement
  const handleSignAgreement = (agreementId: string) => {
    // TODO: Navigate to signing page or open signing modal
    window.location.href = `/service-agreements/${agreementId}/sign`;
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Service Agreements</h1>
            <p className="mt-1 text-sm text-gray-600">
              Review and sign childcare service agreements for your children
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

      {/* Summary Cards */}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {/* Total Agreements */}
        <div className="card p-4">
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-purple-100">
              <svg
                className="h-5 w-5 text-purple-600"
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
            <div>
              <p className="text-sm text-gray-500">Total Agreements</p>
              <p className="text-xl font-bold text-gray-900">{agreements.length}</p>
            </div>
          </div>
        </div>

        {/* Pending Signatures */}
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
                  d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                />
              </svg>
            </div>
            <div>
              <p className="text-sm text-gray-500">Pending Signatures</p>
              <p className="text-xl font-bold text-yellow-600">{pendingCount}</p>
            </div>
          </div>
        </div>

        {/* Active Agreements */}
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
                  d="M5 13l4 4L19 7"
                />
              </svg>
            </div>
            <div>
              <p className="text-sm text-gray-500">Active Agreements</p>
              <p className="text-xl font-bold text-green-600">{activeCount}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Filter tabs */}
      <div className="mb-6 flex items-center justify-between">
        <h2 className="section-title">Agreements</h2>
        <div className="flex items-center space-x-2">
          <button
            type="button"
            onClick={() => setFilter('all')}
            className={`btn text-sm ${filter === 'all' ? 'btn-primary' : 'btn-outline'}`}
          >
            All
          </button>
          <button
            type="button"
            onClick={() => setFilter('pending_signature')}
            className={`btn text-sm ${filter === 'pending_signature' ? 'btn-primary' : 'btn-outline'}`}
          >
            Pending
            {pendingCount > 0 && (
              <span className="ml-2 inline-flex items-center justify-center h-5 w-5 rounded-full bg-yellow-100 text-yellow-800 text-xs font-medium">
                {pendingCount}
              </span>
            )}
          </button>
          <button
            type="button"
            onClick={() => setFilter('active')}
            className={`btn text-sm ${filter === 'active' ? 'btn-primary' : 'btn-outline'}`}
          >
            Active
          </button>
          <button
            type="button"
            onClick={() => setFilter('other')}
            className={`btn text-sm ${filter === 'other' ? 'btn-primary' : 'btn-outline'}`}
          >
            Other
          </button>
        </div>
      </div>

      {/* Pending action notice */}
      {pendingCount > 0 && filter !== 'active' && filter !== 'other' && (
        <div className="mb-6 rounded-lg bg-yellow-50 border border-yellow-200 p-4">
          <div className="flex">
            <svg
              className="h-5 w-5 text-yellow-400 flex-shrink-0"
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
              <h3 className="text-sm font-medium text-yellow-800">Action Required</h3>
              <p className="mt-1 text-sm text-yellow-700">
                You have {pendingCount} service agreement{pendingCount !== 1 ? 's' : ''}{' '}
                requiring your signature. Please review and sign them to activate childcare
                services.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Agreement list */}
      {filteredAgreements.length > 0 ? (
        <div className="space-y-4">
          {filteredAgreements.map((agreement) => (
            <ServiceAgreementCard
              key={agreement.id}
              agreement={agreement}
              onView={handleViewAgreement}
              onSign={handleSignAgreement}
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
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
              />
            </svg>
          </div>
          <h3 className="mt-4 text-lg font-medium text-gray-900">No agreements found</h3>
          <p className="mt-2 text-sm text-gray-500">
            {filter === 'pending_signature'
              ? "Great job! You've signed all pending agreements."
              : filter === 'active'
                ? 'No active agreements at this time.'
                : filter === 'other'
                  ? 'No draft, expired, or cancelled agreements.'
                  : 'No service agreements are available at this time.'}
          </p>
          {filter !== 'all' && (
            <button
              type="button"
              onClick={() => setFilter('all')}
              className="mt-4 btn btn-outline"
            >
              View All Agreements
            </button>
          )}
        </div>
      )}

      {/* Quebec Consumer Protection Act Notice */}
      <div className="mt-8 rounded-lg bg-blue-50 border border-blue-200 p-4">
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
              Quebec Consumer Protection Act
            </h3>
            <p className="mt-1 text-sm text-blue-700">
              Under the Consumer Protection Act, you have 10 days from the date of signing to
              cancel a service agreement without penalty. All agreements comply with Quebec
              FO-0659 requirements.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
