'use client';

import { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { GovernmentDocumentCard } from '@/components/GovernmentDocumentCard';
import { GovernmentDocumentUpload } from '@/components/GovernmentDocumentUpload';
import type {
  GovernmentDocument,
  GovernmentDocumentChecklist,
  GovernmentDocumentStats,
  GovernmentDocumentTypeDefinition,
  GovernmentDocumentUploadRequest,
  GovernmentDocumentChecklistItem,
  GovernmentDocumentStatus,
} from '@/lib/types';

// Mock data - will be replaced with API calls
const mockStats: GovernmentDocumentStats = {
  total: 12,
  verified: 7,
  pendingVerification: 2,
  missing: 2,
  expired: 1,
  expiringWithin30Days: 1,
};

const mockDocumentTypes: GovernmentDocumentTypeDefinition[] = [
  {
    id: 'type-1',
    name: 'Birth Certificate',
    description: "Official birth certificate for the child",
    category: 'child_identity',
    isRequired: true,
    appliesToChild: true,
    appliesToParent: false,
    hasExpiration: false,
  },
  {
    id: 'type-2',
    name: 'Health Insurance Card',
    description: "Quebec health insurance card (RAMQ)",
    category: 'health',
    isRequired: true,
    appliesToChild: true,
    appliesToParent: false,
    hasExpiration: true,
  },
  {
    id: 'type-3',
    name: 'Immunization Record',
    description: "Up-to-date immunization record",
    category: 'health',
    isRequired: true,
    appliesToChild: true,
    appliesToParent: false,
    hasExpiration: false,
  },
  {
    id: 'type-4',
    name: 'Parent ID',
    description: "Government-issued photo identification",
    category: 'parent_identity',
    isRequired: true,
    appliesToChild: false,
    appliesToParent: true,
    hasExpiration: true,
  },
  {
    id: 'type-5',
    name: 'Citizenship Proof',
    description: "Proof of Canadian citizenship or permanent residency",
    category: 'immigration',
    isRequired: false,
    appliesToChild: true,
    appliesToParent: false,
    hasExpiration: false,
  },
];

const mockChecklist: GovernmentDocumentChecklist = {
  familyId: 'family-1',
  children: [
    {
      personId: 'child-1',
      personName: 'Emma Thompson',
      items: [
        {
          documentType: mockDocumentTypes[0],
          personId: 'child-1',
          personName: 'Emma Thompson',
          status: 'verified',
          document: {
            id: 'doc-1',
            familyId: 'family-1',
            personId: 'child-1',
            personName: 'Emma Thompson',
            documentTypeId: 'type-1',
            documentTypeName: 'Birth Certificate',
            category: 'child_identity',
            status: 'verified',
            documentNumber: 'BC-123456',
            fileUrl: '/documents/birth-cert.pdf',
            fileName: 'birth-certificate.pdf',
            uploadedAt: '2024-01-15T10:00:00Z',
            verifiedAt: '2024-01-16T14:30:00Z',
            verifiedBy: 'Staff Member',
            createdAt: '2024-01-15T10:00:00Z',
            updatedAt: '2024-01-16T14:30:00Z',
          },
        },
        {
          documentType: mockDocumentTypes[1],
          personId: 'child-1',
          personName: 'Emma Thompson',
          status: 'verified',
          document: {
            id: 'doc-2',
            familyId: 'family-1',
            personId: 'child-1',
            personName: 'Emma Thompson',
            documentTypeId: 'type-2',
            documentTypeName: 'Health Insurance Card',
            category: 'health',
            status: 'verified',
            expirationDate: '2025-06-15',
            fileUrl: '/documents/health-card.pdf',
            fileName: 'health-card.pdf',
            uploadedAt: '2024-01-15T10:00:00Z',
            verifiedAt: '2024-01-17T09:15:00Z',
            createdAt: '2024-01-15T10:00:00Z',
            updatedAt: '2024-01-17T09:15:00Z',
          },
          daysUntilExpiration: 120,
        },
        {
          documentType: mockDocumentTypes[2],
          personId: 'child-1',
          personName: 'Emma Thompson',
          status: 'pending_verification',
          document: {
            id: 'doc-3',
            familyId: 'family-1',
            personId: 'child-1',
            personName: 'Emma Thompson',
            documentTypeId: 'type-3',
            documentTypeName: 'Immunization Record',
            category: 'health',
            status: 'pending_verification',
            fileUrl: '/documents/immunization.pdf',
            fileName: 'immunization.pdf',
            uploadedAt: '2024-02-01T11:00:00Z',
            createdAt: '2024-02-01T11:00:00Z',
            updatedAt: '2024-02-01T11:00:00Z',
          },
        },
        {
          documentType: mockDocumentTypes[4],
          personId: 'child-1',
          personName: 'Emma Thompson',
          status: 'missing',
        },
      ],
    },
    {
      personId: 'child-2',
      personName: 'Oliver Thompson',
      items: [
        {
          documentType: mockDocumentTypes[0],
          personId: 'child-2',
          personName: 'Oliver Thompson',
          status: 'verified',
          document: {
            id: 'doc-4',
            familyId: 'family-1',
            personId: 'child-2',
            personName: 'Oliver Thompson',
            documentTypeId: 'type-1',
            documentTypeName: 'Birth Certificate',
            category: 'child_identity',
            status: 'verified',
            documentNumber: 'BC-789012',
            fileUrl: '/documents/birth-cert-oliver.pdf',
            fileName: 'birth-certificate-oliver.pdf',
            uploadedAt: '2024-01-15T10:00:00Z',
            verifiedAt: '2024-01-16T14:30:00Z',
            createdAt: '2024-01-15T10:00:00Z',
            updatedAt: '2024-01-16T14:30:00Z',
          },
        },
        {
          documentType: mockDocumentTypes[1],
          personId: 'child-2',
          personName: 'Oliver Thompson',
          status: 'expired',
          document: {
            id: 'doc-5',
            familyId: 'family-1',
            personId: 'child-2',
            personName: 'Oliver Thompson',
            documentTypeId: 'type-2',
            documentTypeName: 'Health Insurance Card',
            category: 'health',
            status: 'expired',
            expirationDate: '2024-01-01',
            fileUrl: '/documents/health-card-oliver.pdf',
            fileName: 'health-card-oliver.pdf',
            uploadedAt: '2023-01-15T10:00:00Z',
            createdAt: '2023-01-15T10:00:00Z',
            updatedAt: '2024-01-02T00:00:00Z',
          },
          daysUntilExpiration: -46,
        },
        {
          documentType: mockDocumentTypes[2],
          personId: 'child-2',
          personName: 'Oliver Thompson',
          status: 'missing',
        },
      ],
    },
  ],
  parents: [
    {
      personId: 'parent-1',
      personName: 'John Thompson',
      items: [
        {
          documentType: mockDocumentTypes[3],
          personId: 'parent-1',
          personName: 'John Thompson',
          status: 'verified',
          document: {
            id: 'doc-6',
            familyId: 'family-1',
            personId: 'parent-1',
            personName: 'John Thompson',
            documentTypeId: 'type-4',
            documentTypeName: 'Parent ID',
            category: 'parent_identity',
            status: 'verified',
            expirationDate: '2028-03-15',
            fileUrl: '/documents/parent-id.pdf',
            fileName: 'parent-id.pdf',
            uploadedAt: '2024-01-15T10:00:00Z',
            verifiedAt: '2024-01-16T11:00:00Z',
            createdAt: '2024-01-15T10:00:00Z',
            updatedAt: '2024-01-16T11:00:00Z',
          },
          daysUntilExpiration: 1520,
        },
      ],
    },
    {
      personId: 'parent-2',
      personName: 'Sarah Thompson',
      items: [
        {
          documentType: mockDocumentTypes[3],
          personId: 'parent-2',
          personName: 'Sarah Thompson',
          status: 'pending_verification',
          document: {
            id: 'doc-7',
            familyId: 'family-1',
            personId: 'parent-2',
            personName: 'Sarah Thompson',
            documentTypeId: 'type-4',
            documentTypeName: 'Parent ID',
            category: 'parent_identity',
            status: 'pending_verification',
            expirationDate: '2027-08-20',
            fileUrl: '/documents/parent-id-sarah.pdf',
            fileName: 'parent-id-sarah.pdf',
            uploadedAt: '2024-02-10T09:00:00Z',
            createdAt: '2024-02-10T09:00:00Z',
            updatedAt: '2024-02-10T09:00:00Z',
          },
          daysUntilExpiration: 1280,
        },
      ],
    },
  ],
  complianceRate: 70,
  criticalDocumentsMissing: false,
  missingCriticalDocuments: [],
};

type FilterType = 'all' | 'verified' | 'pending' | 'attention';

export default function GovernmentDocumentsPage() {
  const [stats, setStats] = useState<GovernmentDocumentStats>(mockStats);
  const [checklist, setChecklist] = useState<GovernmentDocumentChecklist>(mockChecklist);
  const [documentTypes, setDocumentTypes] = useState<GovernmentDocumentTypeDefinition[]>(mockDocumentTypes);
  const [filter, setFilter] = useState<FilterType>('all');
  const [isUploadModalOpen, setIsUploadModalOpen] = useState(false);
  const [uploadTarget, setUploadTarget] = useState<{
    documentType: GovernmentDocumentTypeDefinition | null;
    personId: string;
    personName: string;
  }>({
    documentType: null,
    personId: '',
    personName: '',
  });
  const [isLoading, setIsLoading] = useState(false);

  // Filter checklist items based on selected filter
  const filterItems = useCallback((items: GovernmentDocumentChecklistItem[]): GovernmentDocumentChecklistItem[] => {
    switch (filter) {
      case 'verified':
        return items.filter((item) => item.status === 'verified');
      case 'pending':
        return items.filter((item) => item.status === 'pending_verification');
      case 'attention':
        return items.filter(
          (item) =>
            item.status === 'missing' ||
            item.status === 'expired' ||
            item.status === 'rejected'
        );
      default:
        return items;
    }
  }, [filter]);

  // Get all documents flattened for display
  const getAllDocuments = useCallback((): GovernmentDocument[] => {
    const docs: GovernmentDocument[] = [];

    checklist.children.forEach((child) => {
      child.items.forEach((item) => {
        if (item.document) {
          docs.push(item.document);
        } else {
          // Create a "missing" document placeholder
          docs.push({
            id: `missing-${item.documentType.id}-${item.personId}`,
            familyId: checklist.familyId,
            personId: item.personId,
            personName: item.personName,
            documentTypeId: item.documentType.id,
            documentTypeName: item.documentType.name,
            category: item.documentType.category,
            status: 'missing',
            createdAt: new Date().toISOString(),
            updatedAt: new Date().toISOString(),
          });
        }
      });
    });

    checklist.parents.forEach((parent) => {
      parent.items.forEach((item) => {
        if (item.document) {
          docs.push(item.document);
        } else {
          docs.push({
            id: `missing-${item.documentType.id}-${item.personId}`,
            familyId: checklist.familyId,
            personId: item.personId,
            personName: item.personName,
            documentTypeId: item.documentType.id,
            documentTypeName: item.documentType.name,
            category: item.documentType.category,
            status: 'missing',
            createdAt: new Date().toISOString(),
            updatedAt: new Date().toISOString(),
          });
        }
      });
    });

    return docs;
  }, [checklist]);

  // Filter documents based on selected filter
  const filteredDocuments = getAllDocuments().filter((doc) => {
    switch (filter) {
      case 'verified':
        return doc.status === 'verified';
      case 'pending':
        return doc.status === 'pending_verification';
      case 'attention':
        return doc.status === 'missing' || doc.status === 'expired' || doc.status === 'rejected';
      default:
        return true;
    }
  });

  // Calculate counts
  const attentionCount = stats.missing + stats.expired;
  const pendingCount = stats.pendingVerification;
  const verifiedCount = stats.verified;

  // Handle opening upload modal
  const handleOpenUpload = (documentTypeId: string, personId: string) => {
    const docType = documentTypes.find((dt) => dt.id === documentTypeId);

    // Find person name
    let personName = '';
    for (const child of checklist.children) {
      if (child.personId === personId) {
        personName = child.personName;
        break;
      }
    }
    if (!personName) {
      for (const parent of checklist.parents) {
        if (parent.personId === personId) {
          personName = parent.personName;
          break;
        }
      }
    }

    if (docType) {
      setUploadTarget({
        documentType: docType,
        personId,
        personName,
      });
      setIsUploadModalOpen(true);
    }
  };

  // Handle closing upload modal
  const handleCloseUpload = () => {
    setIsUploadModalOpen(false);
    setUploadTarget({
      documentType: null,
      personId: '',
      personName: '',
    });
  };

  // Handle document upload submission
  const handleUploadSubmit = async (request: GovernmentDocumentUploadRequest): Promise<void> => {
    // TODO: Replace with actual API call
    // await uploadGovernmentDocument(request);

    // Simulate upload delay
    await new Promise((resolve) => setTimeout(resolve, 1500));

    // Update local state to reflect the upload
    // In production, we would refetch the checklist from the API
    handleCloseUpload();
  };

  // Handle view document
  const handleViewDocument = (documentId: string) => {
    // TODO: Open document viewer or download
  };

  // Handle delete document
  const handleDeleteDocument = async (documentId: string) => {
    // TODO: Implement delete with confirmation
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Government Documents</h1>
            <p className="mt-1 text-sm text-gray-600">
              Required documents for childcare enrollment per Quebec regulations
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

      {/* Compliance Progress */}
      <div className="mb-8 card p-6">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold text-gray-900">Document Compliance</h2>
          <span className="text-2xl font-bold text-primary-600">{checklist.complianceRate}%</span>
        </div>
        <div className="h-3 w-full overflow-hidden rounded-full bg-gray-200">
          <div
            className={`h-full rounded-full transition-all duration-500 ${
              checklist.complianceRate >= 80
                ? 'bg-green-500'
                : checklist.complianceRate >= 50
                  ? 'bg-yellow-500'
                  : 'bg-red-500'
            }`}
            style={{ width: `${checklist.complianceRate}%` }}
          />
        </div>
        <p className="mt-2 text-sm text-gray-500">
          {stats.verified} of {stats.total} required documents verified
        </p>
      </div>

      {/* Summary Cards */}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {/* Needs Attention */}
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
              <p className="text-sm text-gray-500">Needs Attention</p>
              <p className="text-xl font-bold text-red-600">{attentionCount}</p>
            </div>
          </div>
        </div>

        {/* Pending Verification */}
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
              <p className="text-sm text-gray-500">Pending Verification</p>
              <p className="text-xl font-bold text-yellow-600">{pendingCount}</p>
            </div>
          </div>
        </div>

        {/* Verified */}
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
              <p className="text-sm text-gray-500">Verified</p>
              <p className="text-xl font-bold text-green-600">{verifiedCount}</p>
            </div>
          </div>
        </div>
      </div>

      {/* Expiring Soon Warning */}
      {stats.expiringWithin30Days > 0 && (
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
                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
              />
            </svg>
            <div className="ml-3">
              <h3 className="text-sm font-medium text-amber-800">
                Documents Expiring Soon
              </h3>
              <p className="mt-1 text-sm text-amber-700">
                You have {stats.expiringWithin30Days} document{stats.expiringWithin30Days !== 1 ? 's' : ''}{' '}
                expiring within the next 30 days. Please upload updated copies to avoid
                compliance issues.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Critical Documents Missing Warning */}
      {checklist.criticalDocumentsMissing && (
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
                Critical Documents Missing
              </h3>
              <p className="mt-1 text-sm text-red-700">
                Your enrollment may be delayed until these critical documents are provided:{' '}
                {checklist.missingCriticalDocuments.join(', ')}.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Action Required Notice */}
      {attentionCount > 0 && filter !== 'verified' && (
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
                You have {attentionCount} document{attentionCount !== 1 ? 's' : ''}{' '}
                that require your attention. Please upload the missing or expired documents
                to maintain compliance.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Filter tabs */}
      <div className="mb-6 flex items-center justify-between">
        <h2 className="section-title">Document Checklist</h2>
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
            onClick={() => setFilter('attention')}
            className={`btn text-sm ${
              filter === 'attention' ? 'btn-primary' : 'btn-outline'
            }`}
          >
            Attention
            {attentionCount > 0 && (
              <span className="ml-2 inline-flex items-center justify-center h-5 w-5 rounded-full bg-red-100 text-red-800 text-xs font-medium">
                {attentionCount}
              </span>
            )}
          </button>
          <button
            type="button"
            onClick={() => setFilter('pending')}
            className={`btn text-sm ${
              filter === 'pending' ? 'btn-primary' : 'btn-outline'
            }`}
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
            onClick={() => setFilter('verified')}
            className={`btn text-sm ${
              filter === 'verified' ? 'btn-primary' : 'btn-outline'
            }`}
          >
            Verified
          </button>
        </div>
      </div>

      {/* Document list */}
      {filteredDocuments.length > 0 ? (
        <div className="space-y-4">
          {filteredDocuments.map((doc) => (
            <GovernmentDocumentCard
              key={doc.id}
              document={doc}
              onUpload={handleOpenUpload}
              onView={handleViewDocument}
              onDelete={handleDeleteDocument}
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
            No documents found
          </h3>
          <p className="mt-2 text-sm text-gray-500">
            {filter === 'attention'
              ? 'Great! All documents are up to date.'
              : filter === 'pending'
                ? 'No documents are pending verification.'
                : filter === 'verified'
                  ? "No documents have been verified yet."
                  : 'No documents are available at this time.'}
          </p>
          {filter !== 'all' && (
            <button
              type="button"
              onClick={() => setFilter('all')}
              className="mt-4 btn btn-outline"
            >
              View All Documents
            </button>
          )}
        </div>
      )}

      {/* Help text */}
      <div className="mt-8 rounded-lg bg-gray-50 p-4">
        <h3 className="text-sm font-medium text-gray-900">
          Need help with document submission?
        </h3>
        <p className="mt-2 text-sm text-gray-600">
          Accepted formats: PDF, JPG, PNG (max 10MB). Ensure documents are clear, legible,
          and show all required information. Our staff will review and verify your
          submissions within 2-3 business days.
        </p>
        <p className="mt-2 text-sm text-gray-600">
          For questions about required documents, please contact the childcare center
          administration.
        </p>
      </div>

      {/* Upload Modal */}
      <GovernmentDocumentUpload
        isOpen={isUploadModalOpen}
        onClose={handleCloseUpload}
        onSubmit={handleUploadSubmit}
        documentType={uploadTarget.documentType}
        personId={uploadTarget.personId}
        personName={uploadTarget.personName}
      />
    </div>
  );
}
