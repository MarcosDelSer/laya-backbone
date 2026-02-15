'use client';

import { useState } from 'react';
import Link from 'next/link';
import { DocumentCard } from '@/components/DocumentCard';
import { DocumentSignature } from '@/components/DocumentSignature';

// Type definitions
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

  // Filter documents based on selected filter
  const filteredDocuments = documents.filter((doc) => {
    if (filter === 'all') return true;
    return doc.status === filter;
  });

  // Calculate counts
  const pendingCount = documents.filter((d) => d.status === 'pending').length;
  const signedCount = documents.filter((d) => d.status === 'signed').length;

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
  const handleSignDocument = (documentId: string, signatureDataUrl: string) => {
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
                {documents.length}
              </p>
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
      </div>

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
    </div>
  );
}
