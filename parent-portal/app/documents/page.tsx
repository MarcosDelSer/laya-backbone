'use client';

import { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { DocumentCard } from '@/components/DocumentCard';
import { DocumentSignature } from '@/components/DocumentSignature';
import {
  getDocuments,
  getSignatureDashboard,
  createSignature,
  type Document as ApiDocument,
  type DocumentStatus,
  type SignatureDashboardResponse,
} from '@/lib/ai-client';

// Type definitions - Internal document representation for UI
interface Document {
  id: string;
  title: string;
  type: string;
  uploadDate: string;
  status: 'pending' | 'signed';
  signedAt?: string;
  signatureUrl?: string;
  pdfUrl: string;
}

/**
 * Convert API document to UI document format.
 */
function apiDocumentToUIDocument(apiDoc: ApiDocument): Document {
  return {
    id: apiDoc.id,
    title: apiDoc.title,
    type: apiDoc.type,
    uploadDate: apiDoc.created_at,
    status: apiDoc.status === 'signed' ? 'signed' : 'pending',
    pdfUrl: apiDoc.content_url,
  };
}

// Mock data - will be replaced with API calls
const mockDocuments: Document[] = [
  {
    id: 'doc-1',
    title: 'Enrollment Agreement 2024-2025',
    type: 'Enrollment',
    uploadDate: '2024-01-15T10:00:00Z',
    status: 'signed',
    signedAt: '2024-01-18T14:32:00Z',
    signatureUrl: '/signatures/doc-1-sig.png',
    pdfUrl: '/documents/enrollment-agreement.pdf',
  },
  {
    id: 'doc-2',
    title: 'Photo & Video Release Consent',
    type: 'Consent Form',
    uploadDate: '2024-01-15T10:00:00Z',
    status: 'pending',
    pdfUrl: '/documents/photo-release.pdf',
  },
  {
    id: 'doc-3',
    title: 'Emergency Medical Authorization',
    type: 'Medical',
    uploadDate: '2024-01-20T09:00:00Z',
    status: 'pending',
    pdfUrl: '/documents/medical-auth.pdf',
  },
  {
    id: 'doc-4',
    title: 'Parent Handbook Acknowledgment',
    type: 'Policy',
    uploadDate: '2024-01-10T08:00:00Z',
    status: 'signed',
    signedAt: '2024-01-12T16:45:00Z',
    signatureUrl: '/signatures/doc-4-sig.png',
    pdfUrl: '/documents/handbook-ack.pdf',
  },
  {
    id: 'doc-5',
    title: 'Field Trip Permission - Zoo Visit',
    type: 'Consent Form',
    uploadDate: '2024-02-01T11:30:00Z',
    status: 'pending',
    pdfUrl: '/documents/field-trip-zoo.pdf',
  },
  {
    id: 'doc-6',
    title: 'Allergy & Dietary Information',
    type: 'Health',
    uploadDate: '2024-01-15T10:00:00Z',
    status: 'signed',
    signedAt: '2024-01-16T09:15:00Z',
    signatureUrl: '/signatures/doc-6-sig.png',
    pdfUrl: '/documents/allergy-info.pdf',
  },
];

type FilterType = 'all' | 'pending' | 'signed';

export default function DocumentsPage() {
  const [documents, setDocuments] = useState<Document[]>(mockDocuments);
  const [filter, setFilter] = useState<FilterType>('all');
  const [signingDocument, setSigningDocument] = useState<Document | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [useRealAPI, setUseRealAPI] = useState(false);
  const [dashboardData, setDashboardData] = useState<SignatureDashboardResponse | null>(null);

  // Fetch dashboard data from API
  const fetchDashboard = useCallback(async () => {
    if (!useRealAPI) {
      return;
    }

    try {
      const dashboard = await getSignatureDashboard({ limit_recent: 5 });
      setDashboardData(dashboard);
    } catch (err) {
      // Continue with local calculations if dashboard fails
    }
  }, [useRealAPI]);

  // Fetch documents from API
  const fetchDocuments = useCallback(async () => {
    if (!useRealAPI) {
      setIsLoading(false);
      return;
    }

    try {
      setIsLoading(true);
      setError(null);

      // Fetch both documents and dashboard in parallel
      const [documentsResponse, dashboardResponse] = await Promise.all([
        getDocuments({ limit: 100 }),
        getSignatureDashboard({ limit_recent: 5 }).catch(() => null),
      ]);

      const uiDocuments = documentsResponse.items.map(apiDocumentToUIDocument);
      setDocuments(uiDocuments);
      if (dashboardResponse) {
        setDashboardData(dashboardResponse);
      }
    } catch (err) {
      setError('Failed to load documents. Please try again later.');
      // Fall back to mock data on error
      setDocuments(mockDocuments);
    } finally {
      setIsLoading(false);
    }
  }, [useRealAPI]);

  // Load documents on mount
  useEffect(() => {
    fetchDocuments();
  }, [fetchDocuments]);

  // Filter documents based on selected filter
  const filteredDocuments = documents.filter((doc) => {
    if (filter === 'all') return true;
    return doc.status === filter;
  });

  // Calculate counts - use dashboard data if available, otherwise calculate locally
  const pendingCount = dashboardData?.summary.pending_signatures ?? documents.filter((d) => d.status === 'pending').length;
  const signedCount = dashboardData?.summary.signed_documents ?? documents.filter((d) => d.status === 'signed').length;
  const totalCount = dashboardData?.summary.total_documents ?? documents.length;
  const completionRate = dashboardData?.summary.completion_rate;
  const signaturesThisMonth = dashboardData?.summary.signatures_this_month;
  const alerts = dashboardData?.alerts ?? [];

  // Handle opening sign modal
  const handleOpenSignModal = (documentId: string) => {
    const doc = documents.find((d) => d.id === documentId);
    if (doc) {
      setSigningDocument(doc);
      setIsModalOpen(true);
    }
  };

  // Handle closing sign modal
  const handleCloseModal = () => {
    setIsModalOpen(false);
    setSigningDocument(null);
  };

  // Handle signature submission
  const handleSignDocument = async (documentId: string, signatureDataUrl: string) => {
    if (useRealAPI) {
      try {
        // Get user info (in a real app, this would come from auth context)
        const userId = 'current-user-id'; // TODO: Get from auth context

        // Get client IP and device info
        const ipAddress = 'client-ip'; // TODO: Get actual IP from request or browser
        const deviceInfo = navigator.userAgent;

        // Submit signature to API
        await createSignature(documentId, {
          document_id: documentId,
          signer_id: userId,
          signature_image_url: signatureDataUrl,
          ip_address: ipAddress,
          device_info: deviceInfo,
        });

        // Refresh documents list from API
        await fetchDocuments();
        handleCloseModal();
      } catch (err) {
        setError('Failed to submit signature. Please try again.');
      }
    } else {
      // Mock implementation - update local state
      setDocuments((prev) =>
        prev.map((doc) =>
          doc.id === documentId
            ? {
                ...doc,
                status: 'signed' as const,
                signedAt: new Date().toISOString(),
                signatureUrl: signatureDataUrl,
              }
            : doc
        )
      );
      handleCloseModal();
    }
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Documents</h1>
            <p className="mt-1 text-sm text-gray-600">
              Review and sign required documents for your child
            </p>
          </div>
          <div className="flex items-center gap-3">
            {/* API Toggle for development */}
            <label className="flex items-center gap-2 text-xs text-gray-600">
              <input
                type="checkbox"
                checked={useRealAPI}
                onChange={(e) => setUseRealAPI(e.target.checked)}
                className="rounded"
              />
              Use Real API
            </label>
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
      </div>

      {/* Error Message */}
      {error && (
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
                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
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

      {/* Loading State */}
      {isLoading && (
        <div className="flex items-center justify-center py-12">
          <div className="text-center">
            <svg
              className="mx-auto h-12 w-12 text-gray-400 animate-spin"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                className="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
              />
              <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              />
            </svg>
            <p className="mt-4 text-sm text-gray-600">Loading documents...</p>
          </div>
        </div>
      )}

      {!isLoading && (
        <>

      {/* Summary Cards */}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {/* Total Documents */}
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
              <p className="text-sm text-gray-500">Total Documents</p>
              <p className="text-xl font-bold text-gray-900">
                {totalCount}
              </p>
              {signaturesThisMonth !== undefined && (
                <p className="text-xs text-gray-400 mt-1">
                  {signaturesThisMonth} this month
                </p>
              )}
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
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
            <div>
              <p className="text-sm text-gray-500">Pending Signatures</p>
              <p className="text-xl font-bold text-yellow-600">{pendingCount}</p>
            </div>
          </div>
        </div>

        {/* Completed */}
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
              <p className="text-sm text-gray-500">Signed Documents</p>
              <p className="text-xl font-bold text-green-600">{signedCount}</p>
            </div>
          </div>
        </div>

        {/* Completion Rate - only show if dashboard data available */}
        {completionRate !== undefined && (
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
                    d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
                  />
                </svg>
              </div>
              <div>
                <p className="text-sm text-gray-500">Completion Rate</p>
                <p className="text-xl font-bold text-purple-600">
                  {completionRate.toFixed(1)}%
                </p>
              </div>
            </div>
          </div>
        )}
      </div>

      {/* Dashboard Alerts - only show if alerts available */}
      {alerts.length > 0 && (
        <div className="mb-6 space-y-2">
          {alerts.map((alert, index) => (
            <div key={index} className="rounded-lg bg-blue-50 border border-blue-200 p-3">
              <div className="flex items-start">
                <svg
                  className="h-5 w-5 text-blue-400 flex-shrink-0 mt-0.5"
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
                <p className="ml-3 text-sm text-blue-800">{alert}</p>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Filter tabs */}
      <div className="mb-6 flex items-center justify-between">
        <h2 className="section-title">Document Library</h2>
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
            onClick={() => setFilter('signed')}
            className={`btn text-sm ${
              filter === 'signed' ? 'btn-primary' : 'btn-outline'
            }`}
          >
            Signed
          </button>
        </div>
      </div>

      {/* Pending action notice */}
      {pendingCount > 0 && filter !== 'signed' && (
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
              <h3 className="text-sm font-medium text-yellow-800">
                Action Required
              </h3>
              <p className="mt-1 text-sm text-yellow-700">
                You have {pendingCount} document{pendingCount !== 1 ? 's' : ''}{' '}
                requiring your signature. Please review and sign them at your
                earliest convenience.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Document list */}
      {filteredDocuments.length > 0 ? (
        <div className="space-y-4">
          {filteredDocuments.map((doc) => (
            <DocumentCard
              key={doc.id}
              document={doc}
              onSign={handleOpenSignModal}
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
            {filter === 'pending'
              ? "Great job! You've signed all required documents."
              : filter === 'signed'
                ? "You haven't signed any documents yet."
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

      {/* Signature Modal */}
      <DocumentSignature
        documentToSign={signingDocument}
        isOpen={isModalOpen}
        onClose={handleCloseModal}
        onSubmit={handleSignDocument}
      />
      </>
      )}
    </div>
  );
}
