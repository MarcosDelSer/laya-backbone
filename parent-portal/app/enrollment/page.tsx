'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { EnrollmentFormCard } from '@/components/enrollment/EnrollmentFormCard';
import type { EnrollmentFormSummary, EnrollmentFormStatus } from '@/lib/types';

// ============================================================================
// Mock Data - will be replaced with API calls
// ============================================================================

const mockEnrollmentForms: EnrollmentFormSummary[] = [
  {
    id: 'enroll-1',
    formNumber: 'ENR-2024-001',
    status: 'Approved',
    version: 1,
    admissionDate: '2024-09-01',
    childFirstName: 'Sophie',
    childLastName: 'Martin',
    childDateOfBirth: '2021-03-15',
    createdAt: '2024-01-10T09:00:00Z',
    updatedAt: '2024-01-15T14:30:00Z',
    createdByName: 'Marie Martin',
  },
  {
    id: 'enroll-2',
    formNumber: 'ENR-2024-002',
    status: 'Draft',
    version: 1,
    admissionDate: '2024-09-01',
    childFirstName: 'Lucas',
    childLastName: 'Bernard',
    childDateOfBirth: '2022-06-20',
    createdAt: '2024-02-01T10:30:00Z',
    updatedAt: '2024-02-01T10:30:00Z',
    createdByName: 'Jean Bernard',
  },
  {
    id: 'enroll-3',
    formNumber: 'ENR-2024-003',
    status: 'Submitted',
    version: 1,
    admissionDate: '2024-09-01',
    childFirstName: 'Emma',
    childLastName: 'Tremblay',
    childDateOfBirth: '2020-11-08',
    createdAt: '2024-01-20T11:00:00Z',
    updatedAt: '2024-01-22T16:45:00Z',
    createdByName: 'Claire Tremblay',
  },
  {
    id: 'enroll-4',
    formNumber: 'ENR-2024-004',
    status: 'Rejected',
    version: 2,
    admissionDate: '2024-09-01',
    childFirstName: 'Noah',
    childLastName: 'Gagnon',
    childDateOfBirth: '2021-08-12',
    createdAt: '2024-01-25T08:00:00Z',
    updatedAt: '2024-01-28T10:00:00Z',
    createdByName: 'Pierre Gagnon',
  },
  {
    id: 'enroll-5',
    formNumber: 'ENR-2023-012',
    status: 'Expired',
    version: 1,
    childFirstName: 'Olivia',
    childLastName: 'Roy',
    childDateOfBirth: '2019-04-25',
    createdAt: '2023-06-15T14:00:00Z',
    updatedAt: '2023-06-15T14:00:00Z',
    createdByName: 'Anne Roy',
  },
];

// ============================================================================
// Filter Type
// ============================================================================

type FilterType = 'all' | EnrollmentFormStatus;

// ============================================================================
// Page Component
// ============================================================================

export default function EnrollmentPage() {
  const router = useRouter();
  const [forms, setForms] = useState<EnrollmentFormSummary[]>(mockEnrollmentForms);
  const [filter, setFilter] = useState<FilterType>('all');

  // Filter forms based on selected filter
  const filteredForms = forms.filter((form) => {
    if (filter === 'all') return true;
    return form.status === filter;
  });

  // Calculate counts
  const totalCount = forms.length;
  const draftCount = forms.filter((f) => f.status === 'Draft').length;
  const submittedCount = forms.filter((f) => f.status === 'Submitted').length;
  const approvedCount = forms.filter((f) => f.status === 'Approved').length;
  const rejectedCount = forms.filter((f) => f.status === 'Rejected').length;

  // Handlers
  const handleView = (formId: string) => {
    router.push(`/enrollment/${formId}`);
  };

  const handleEdit = (formId: string) => {
    router.push(`/enrollment/${formId}/edit`);
  };

  const handleContinue = (formId: string) => {
    router.push(`/enrollment/${formId}/edit`);
  };

  const handleNewForm = () => {
    router.push('/enrollment/new');
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Enrollment Forms</h1>
            <p className="mt-1 text-sm text-gray-600">
              Manage enrollment forms for your children
            </p>
          </div>
          <div className="flex items-center gap-3 self-start">
            <Link href="/" className="btn btn-outline">
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
            <button
              type="button"
              onClick={handleNewForm}
              className="btn btn-primary"
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
                  d="M12 4v16m8-8H4"
                />
              </svg>
              New Enrollment
            </button>
          </div>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-4">
        {/* Total Forms */}
        <div className="card p-4">
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100">
              <svg
                className="h-5 w-5 text-blue-600"
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
              <p className="text-sm text-gray-500">Total Forms</p>
              <p className="text-xl font-bold text-gray-900">{totalCount}</p>
            </div>
          </div>
        </div>

        {/* Draft Forms */}
        <div className="card p-4">
          <div className="flex items-center space-x-3">
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100">
              <svg
                className="h-5 w-5 text-gray-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
                />
              </svg>
            </div>
            <div>
              <p className="text-sm text-gray-500">Drafts</p>
              <p className="text-xl font-bold text-gray-600">{draftCount}</p>
            </div>
          </div>
        </div>

        {/* Submitted Forms */}
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
              <p className="text-sm text-gray-500">Pending Review</p>
              <p className="text-xl font-bold text-yellow-600">{submittedCount}</p>
            </div>
          </div>
        </div>

        {/* Approved Forms */}
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
              <p className="text-sm text-gray-500">Approved</p>
              <p className="text-xl font-bold text-green-600">{approvedCount}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Filter tabs */}
      <div className="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <h2 className="section-title">Enrollment Forms</h2>
        <div className="flex flex-wrap items-center gap-2">
          <button
            type="button"
            onClick={() => setFilter('all')}
            className={`btn text-sm ${filter === 'all' ? 'btn-primary' : 'btn-outline'}`}
          >
            All
          </button>
          <button
            type="button"
            onClick={() => setFilter('Draft')}
            className={`btn text-sm ${filter === 'Draft' ? 'btn-primary' : 'btn-outline'}`}
          >
            Drafts
            {draftCount > 0 && (
              <span className="ml-2 inline-flex items-center justify-center h-5 w-5 rounded-full bg-gray-100 text-gray-800 text-xs font-medium">
                {draftCount}
              </span>
            )}
          </button>
          <button
            type="button"
            onClick={() => setFilter('Submitted')}
            className={`btn text-sm ${filter === 'Submitted' ? 'btn-primary' : 'btn-outline'}`}
          >
            Submitted
            {submittedCount > 0 && (
              <span className="ml-2 inline-flex items-center justify-center h-5 w-5 rounded-full bg-yellow-100 text-yellow-800 text-xs font-medium">
                {submittedCount}
              </span>
            )}
          </button>
          <button
            type="button"
            onClick={() => setFilter('Approved')}
            className={`btn text-sm ${filter === 'Approved' ? 'btn-primary' : 'btn-outline'}`}
          >
            Approved
          </button>
          <button
            type="button"
            onClick={() => setFilter('Rejected')}
            className={`btn text-sm ${filter === 'Rejected' ? 'btn-primary' : 'btn-outline'}`}
          >
            Rejected
            {rejectedCount > 0 && (
              <span className="ml-2 inline-flex items-center justify-center h-5 w-5 rounded-full bg-red-100 text-red-800 text-xs font-medium">
                {rejectedCount}
              </span>
            )}
          </button>
        </div>
      </div>

      {/* Action notices */}
      {draftCount > 0 && filter !== 'Approved' && filter !== 'Submitted' && (
        <div className="mb-6 rounded-lg bg-gray-50 border border-gray-200 p-4">
          <div className="flex">
            <svg
              className="h-5 w-5 text-gray-400 flex-shrink-0"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
              />
            </svg>
            <div className="ml-3">
              <h3 className="text-sm font-medium text-gray-800">
                Incomplete Forms
              </h3>
              <p className="mt-1 text-sm text-gray-600">
                You have {draftCount} draft form{draftCount !== 1 ? 's' : ''}{' '}
                that need{draftCount === 1 ? 's' : ''} to be completed and submitted.
              </p>
            </div>
          </div>
        </div>
      )}

      {rejectedCount > 0 && (filter === 'all' || filter === 'Rejected') && (
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
                You have {rejectedCount} form{rejectedCount !== 1 ? 's' : ''}{' '}
                that {rejectedCount === 1 ? 'was' : 'were'} rejected and require{rejectedCount === 1 ? 's' : ''} your attention.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Form list */}
      {filteredForms.length > 0 ? (
        <div className="space-y-4">
          {filteredForms.map((form) => (
            <EnrollmentFormCard
              key={form.id}
              form={form}
              onView={handleView}
              onEdit={handleEdit}
              onContinue={handleContinue}
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
          <h3 className="mt-4 text-lg font-medium text-gray-900">
            {filter === 'all' ? 'No enrollment forms yet' : `No ${filter.toLowerCase()} forms`}
          </h3>
          <p className="mt-2 text-sm text-gray-500">
            {filter === 'Draft'
              ? 'You have no draft forms in progress.'
              : filter === 'Submitted'
                ? 'No forms are currently awaiting review.'
                : filter === 'Approved'
                  ? 'No forms have been approved yet.'
                  : filter === 'Rejected'
                    ? 'No forms have been rejected.'
                    : filter === 'Expired'
                      ? 'No forms have expired.'
                      : 'Get started by creating a new enrollment form for your child.'}
          </p>
          {filter === 'all' ? (
            <button
              type="button"
              onClick={handleNewForm}
              className="mt-4 btn btn-primary"
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
                  d="M12 4v16m8-8H4"
                />
              </svg>
              New Enrollment Form
            </button>
          ) : (
            <button
              type="button"
              onClick={() => setFilter('all')}
              className="mt-4 btn btn-outline"
            >
              View All Forms
            </button>
          )}
        </div>
      )}
    </div>
  );
}
