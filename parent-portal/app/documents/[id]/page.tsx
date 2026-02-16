'use client';

import { useState, useEffect, useCallback } from 'react';
import { useParams, useRouter } from 'next/navigation';
import Link from 'next/link';
import { DocumentSignature } from '@/components/DocumentSignature';
import {
  getDocument,
  getDocumentSignatures,
  createSignature,
  type Document as ApiDocument,
  type Signature,
  type DocumentStatus,
} from '@/lib/ai-client';

// UI document type that matches DocumentSignature component expectations
interface UIDocument {
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
 * Document preview page - displays full document details and allows signing.
 *
 * Features:
 * - Document metadata display (title, type, status, dates)
 * - PDF preview/download
 * - Signature information if signed
 * - Signature canvas for unsigned documents
 * - Audit trail information
 * - Real-time status updates
 */
export default function DocumentPreviewPage() {
  const params = useParams();
  const router = useRouter();
  const documentId = params.id as string;

  const [document, setDocument] = useState<ApiDocument | null>(null);
  const [signatures, setSignatures] = useState<Signature[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSignModalOpen, setIsSignModalOpen] = useState(false);
  const [useRealAPI, setUseRealAPI] = useState(false);

  // Mock document data for development
  const mockDocument: ApiDocument = {
    id: documentId,
    title: 'Enrollment Agreement 2024-2025',
    type: 'Enrollment',
    content_url: '/documents/enrollment-agreement.pdf',
    status: 'pending',
    created_by: 'staff-001',
    created_at: '2024-01-15T10:00:00Z',
    updated_at: '2024-01-15T10:00:00Z',
  };

  const mockSignatures: Signature[] = [];

  // Fetch document and signatures from API
  const fetchDocumentData = useCallback(async () => {
    if (!useRealAPI) {
      // Use mock data
      setDocument(mockDocument);
      setSignatures(mockSignatures);
      setIsLoading(false);
      return;
    }

    try {
      setIsLoading(true);
      setError(null);

      // Fetch document and signatures in parallel
      const [docData, sigData] = await Promise.all([
        getDocument(documentId),
        getDocumentSignatures(documentId),
      ]);

      setDocument(docData);
      setSignatures(sigData);
    } catch (err) {
      console.error('Failed to fetch document:', err);
      setError('Failed to load document. Please try again later.');
      // Fall back to mock data on error
      setDocument(mockDocument);
      setSignatures(mockSignatures);
    } finally {
      setIsLoading(false);
    }
  }, [documentId, useRealAPI]);

  // Load document on mount
  useEffect(() => {
    fetchDocumentData();
  }, [fetchDocumentData]);

  // Handle opening sign modal
  const handleOpenSignModal = () => {
    setIsSignModalOpen(true);
  };

  // Handle closing sign modal
  const handleCloseSignModal = () => {
    setIsSignModalOpen(false);
  };

  // Handle signature submission
  const handleSignDocument = async (docId: string, signatureDataUrl: string) => {
    if (useRealAPI) {
      try {
        // Get user info (in a real app, this would come from auth context)
        const userId = 'current-user-id'; // TODO: Get from auth context

        // Get client IP and device info
        const ipAddress = 'client-ip'; // TODO: Get actual IP from request or browser
        const deviceInfo = navigator.userAgent;

        // Submit signature to API
        await createSignature(docId, {
          document_id: docId,
          signer_id: userId,
          signature_image_url: signatureDataUrl,
          ip_address: ipAddress,
          device_info: deviceInfo,
        });

        // Refresh document data from API
        await fetchDocumentData();
        handleCloseSignModal();
      } catch (err) {
        console.error('Failed to submit signature:', err);
        setError('Failed to submit signature. Please try again.');
      }
    } else {
      // Mock implementation - update local state
      if (document) {
        const updatedDoc: ApiDocument = {
          ...document,
          status: 'signed',
          updated_at: new Date().toISOString(),
        };
        setDocument(updatedDoc);

        const newSignature: Signature = {
          id: `sig-${Date.now()}`,
          document_id: docId,
          signer_id: 'current-user-id',
          signature_image_url: signatureDataUrl,
          ip_address: 'mock-ip',
          device_info: navigator.userAgent,
          timestamp: new Date().toISOString(),
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
        };
        setSignatures([newSignature]);
      }
      handleCloseSignModal();
    }
  };

  // Convert API document to UI format for DocumentSignature component
  const getUIDocument = (): UIDocument | null => {
    if (!document) return null;

    return {
      id: document.id,
      title: document.title,
      type: document.type,
      uploadDate: document.created_at,
      status: document.status === 'signed' ? 'signed' : 'pending',
      signedAt: signatures[0]?.timestamp,
      signatureUrl: signatures[0]?.signature_image_url,
      pdfUrl: document.content_url,
    };
  };

  // Format date for display
  const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  // Get status badge color
  const getStatusColor = (status: DocumentStatus): string => {
    switch (status) {
      case 'signed':
        return 'bg-green-100 text-green-800';
      case 'pending':
        return 'bg-yellow-100 text-yellow-800';
      case 'expired':
        return 'bg-red-100 text-red-800';
      case 'draft':
        return 'bg-gray-100 text-gray-800';
      default:
        return 'bg-gray-100 text-gray-800';
    }
  };

  // Loading state
  if (isLoading) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
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
            <p className="mt-4 text-sm text-gray-600">Loading document...</p>
          </div>
        </div>
      </div>
    );
  }

  // Error state - document not found
  if (!document) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="text-center py-12">
          <svg
            className="mx-auto h-16 w-16 text-gray-400"
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
          <h3 className="mt-4 text-lg font-medium text-gray-900">
            Document not found
          </h3>
          <p className="mt-2 text-sm text-gray-500">
            The document you're looking for doesn't exist or has been removed.
          </p>
          <div className="mt-6">
            <Link href="/documents" className="btn btn-primary">
              Back to Documents
            </Link>
          </div>
        </div>
      </div>
    );
  }

  const uiDocument = getUIDocument();

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <Link
              href="/documents"
              className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 mb-2"
            >
              <svg
                className="mr-1 h-4 w-4"
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
              Back to Documents
            </Link>
            <h1 className="text-2xl font-bold text-gray-900">
              {document.title}
            </h1>
            <p className="mt-1 text-sm text-gray-600">{document.type}</p>
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

      {/* Status Banner */}
      <div className="mb-6 card">
        <div className="p-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <div
                className={`flex h-12 w-12 items-center justify-center rounded-full ${
                  document.status === 'signed'
                    ? 'bg-green-100'
                    : 'bg-yellow-100'
                }`}
              >
                <svg
                  className={`h-6 w-6 ${
                    document.status === 'signed'
                      ? 'text-green-600'
                      : 'text-yellow-600'
                  }`}
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  {document.status === 'signed' ? (
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M5 13l4 4L19 7"
                    />
                  ) : (
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                    />
                  )}
                </svg>
              </div>
              <div>
                <div className="flex items-center space-x-2">
                  <h2 className="text-lg font-semibold text-gray-900">
                    {document.status === 'signed'
                      ? 'Document Signed'
                      : 'Signature Required'}
                  </h2>
                  <span
                    className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(
                      document.status
                    )}`}
                  >
                    {document.status.charAt(0).toUpperCase() +
                      document.status.slice(1)}
                  </span>
                </div>
                <p className="mt-1 text-sm text-gray-600">
                  {document.status === 'signed'
                    ? 'This document has been signed and is legally binding.'
                    : 'Please review and sign this document at your earliest convenience.'}
                </p>
              </div>
            </div>
            {document.status !== 'signed' && (
              <button
                type="button"
                onClick={handleOpenSignModal}
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
                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                  />
                </svg>
                Sign Document
              </button>
            )}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Main Content */}
        <div className="lg:col-span-2 space-y-6">
          {/* Document Preview */}
          <div className="card">
            <div className="border-b border-gray-200 px-6 py-4">
              <h3 className="text-lg font-semibold text-gray-900">
                Document Preview
              </h3>
            </div>
            <div className="p-6">
              <div className="bg-gray-50 rounded-lg border-2 border-dashed border-gray-300 p-8 text-center">
                <svg
                  className="mx-auto h-16 w-16 text-gray-400"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"
                  />
                </svg>
                <h4 className="mt-4 text-sm font-medium text-gray-900">
                  PDF Preview
                </h4>
                <p className="mt-2 text-sm text-gray-500">
                  {document.title}
                </p>
                <div className="mt-6 flex justify-center gap-3">
                  <a
                    href={document.content_url}
                    target="_blank"
                    rel="noopener noreferrer"
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
                        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                      />
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                      />
                    </svg>
                    Open PDF
                  </a>
                  <a
                    href={document.content_url}
                    download
                    className="btn btn-outline"
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
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                      />
                    </svg>
                    Download
                  </a>
                </div>
              </div>
            </div>
          </div>

          {/* Signature Information */}
          {signatures.length > 0 && (
            <div className="card">
              <div className="border-b border-gray-200 px-6 py-4">
                <h3 className="text-lg font-semibold text-gray-900">
                  Signature Information
                </h3>
              </div>
              <div className="p-6">
                {signatures.map((signature) => (
                  <div key={signature.id} className="space-y-4">
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <p className="text-sm font-medium text-gray-900">
                          Digital Signature
                        </p>
                        <p className="mt-1 text-xs text-gray-500">
                          Signed on {formatDate(signature.timestamp)}
                        </p>
                      </div>
                      {signature.signature_image_url && (
                        <div className="ml-4 p-2 border border-gray-200 rounded bg-white">
                          <img
                            src={signature.signature_image_url}
                            alt="Signature"
                            className="h-16 w-auto"
                          />
                        </div>
                      )}
                    </div>

                    {/* Audit Trail */}
                    <div className="mt-4 rounded-lg bg-gray-50 p-4">
                      <h4 className="text-xs font-medium text-gray-700 mb-3">
                        Audit Trail
                      </h4>
                      <dl className="grid grid-cols-1 gap-3 text-xs">
                        <div>
                          <dt className="text-gray-500">Timestamp</dt>
                          <dd className="mt-1 text-gray-900">
                            {formatDate(signature.timestamp)}
                          </dd>
                        </div>
                        {signature.ip_address && (
                          <div>
                            <dt className="text-gray-500">IP Address</dt>
                            <dd className="mt-1 text-gray-900 font-mono">
                              {signature.ip_address}
                            </dd>
                          </div>
                        )}
                        {signature.device_info && (
                          <div>
                            <dt className="text-gray-500">Device</dt>
                            <dd className="mt-1 text-gray-900">
                              {signature.device_info}
                            </dd>
                          </div>
                        )}
                        <div>
                          <dt className="text-gray-500">Signature ID</dt>
                          <dd className="mt-1 text-gray-900 font-mono text-xs">
                            {signature.id}
                          </dd>
                        </div>
                      </dl>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>

        {/* Sidebar */}
        <div className="lg:col-span-1 space-y-6">
          {/* Document Details */}
          <div className="card">
            <div className="border-b border-gray-200 px-6 py-4">
              <h3 className="text-lg font-semibold text-gray-900">Details</h3>
            </div>
            <div className="p-6">
              <dl className="space-y-4 text-sm">
                <div>
                  <dt className="text-gray-500">Document Type</dt>
                  <dd className="mt-1 text-gray-900">{document.type}</dd>
                </div>
                <div>
                  <dt className="text-gray-500">Status</dt>
                  <dd className="mt-1">
                    <span
                      className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${getStatusColor(
                        document.status
                      )}`}
                    >
                      {document.status.charAt(0).toUpperCase() +
                        document.status.slice(1)}
                    </span>
                  </dd>
                </div>
                <div>
                  <dt className="text-gray-500">Created</dt>
                  <dd className="mt-1 text-gray-900">
                    {formatDate(document.created_at)}
                  </dd>
                </div>
                <div>
                  <dt className="text-gray-500">Last Updated</dt>
                  <dd className="mt-1 text-gray-900">
                    {formatDate(document.updated_at)}
                  </dd>
                </div>
                {signatures.length > 0 && (
                  <div>
                    <dt className="text-gray-500">Signed</dt>
                    <dd className="mt-1 text-gray-900">
                      {formatDate(signatures[0].timestamp)}
                    </dd>
                  </div>
                )}
              </dl>
            </div>
          </div>

          {/* Actions */}
          <div className="card">
            <div className="border-b border-gray-200 px-6 py-4">
              <h3 className="text-lg font-semibold text-gray-900">Actions</h3>
            </div>
            <div className="p-6 space-y-3">
              <a
                href={document.content_url}
                target="_blank"
                rel="noopener noreferrer"
                className="btn btn-outline w-full"
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
                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                  />
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                  />
                </svg>
                View PDF
              </a>
              <a
                href={document.content_url}
                download
                className="btn btn-outline w-full"
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
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                  />
                </svg>
                Download PDF
              </a>
              {document.status !== 'signed' && (
                <button
                  type="button"
                  onClick={handleOpenSignModal}
                  className="btn btn-primary w-full"
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
                  Sign Document
                </button>
              )}
            </div>
          </div>

          {/* Help */}
          <div className="card bg-blue-50 border-blue-200">
            <div className="p-6">
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
                    Need Help?
                  </h3>
                  <p className="mt-1 text-sm text-blue-700">
                    If you have questions about this document, please contact
                    your daycare administrator.
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      {/* Signature Modal */}
      {uiDocument && (
        <DocumentSignature
          documentToSign={uiDocument}
          isOpen={isSignModalOpen}
          onClose={handleCloseSignModal}
          onSubmit={handleSignDocument}
        />
      )}
    </div>
  );
}
