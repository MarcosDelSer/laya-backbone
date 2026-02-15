/**
 * LAYA Parent App - Documents API
 *
 * API functions for fetching document data and submitting signatures.
 * Follows patterns from ParentPortal e-signature endpoints.
 */

import {api} from './client';
import {API_CONFIG, buildApiUrl} from './config';
import type {
  ApiResponse,
  SignatureRequest,
  SignatureStatus,
} from '../types';
import {Linking} from 'react-native';

/**
 * Response type for document list endpoint
 */
interface DocumentListResponse {
  documents: SignatureRequest[];
  summary: DocumentSummary;
}

/**
 * Summary statistics for documents
 */
export interface DocumentSummary {
  totalCount: number;
  pendingCount: number;
  signedCount: number;
  expiredCount: number;
}

/**
 * Parameters for fetching documents
 */
interface FetchDocumentParams {
  status?: SignatureStatus;
  page?: number;
  pageSize?: number;
}

/**
 * Signature submission payload
 */
interface SignaturePayload {
  signatureDataUrl: string;
  signedAt: string;
}

/**
 * Fetch the list of documents requiring signature
 */
export async function fetchDocuments(
  params?: FetchDocumentParams,
): Promise<ApiResponse<DocumentListResponse>> {
  const queryParams: Record<string, string> = {};

  if (params?.status) {
    queryParams.status = params.status;
  }
  if (params?.page !== undefined) {
    queryParams.page = params.page.toString();
  }
  if (params?.pageSize !== undefined) {
    queryParams.pageSize = params.pageSize.toString();
  }

  return api.get<DocumentListResponse>(
    API_CONFIG.endpoints.signatures.pending,
    queryParams,
  );
}

/**
 * Fetch details for a specific document
 */
export async function fetchDocumentDetails(
  documentId: string,
): Promise<ApiResponse<SignatureRequest>> {
  const endpoint = API_CONFIG.endpoints.signatures.document.replace(':id', documentId);
  return api.get<SignatureRequest>(endpoint);
}

/**
 * Submit a signature for a document
 */
export async function submitSignature(
  documentId: string,
  signatureDataUrl: string,
): Promise<ApiResponse<SignatureRequest>> {
  const endpoint = API_CONFIG.endpoints.signatures.sign.replace(':id', documentId);
  const payload: SignaturePayload = {
    signatureDataUrl,
    signedAt: new Date().toISOString(),
  };
  return api.post<SignatureRequest>(endpoint, payload);
}

/**
 * Open document PDF in external viewer
 */
export async function openDocumentPdf(documentUrl: string): Promise<void> {
  const url = buildApiUrl(documentUrl);

  try {
    const canOpen = await Linking.canOpenURL(url);
    if (canOpen) {
      await Linking.openURL(url);
    }
  } catch {
    throw new Error('Unable to open document');
  }
}

/**
 * Format a date string for display
 */
export function formatDocumentDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

/**
 * Format a date with time for display
 */
export function formatDocumentDateTime(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

/**
 * Calculate days until expiration (negative if expired)
 */
export function getDaysUntilExpiration(expiresAt: string | null): number | null {
  if (!expiresAt) {
    return null;
  }

  const expiry = new Date(expiresAt);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  expiry.setHours(0, 0, 0, 0);
  const diffTime = expiry.getTime() - today.getTime();
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

/**
 * Get display label for document status
 */
export function getStatusLabel(status: SignatureStatus): string {
  const labels: Record<SignatureStatus, string> = {
    pending: 'Pending Signature',
    signed: 'Signed',
    expired: 'Expired',
  };
  return labels[status] || status;
}

/**
 * Calculate summary statistics from documents
 */
export function calculateSummary(documents: SignatureRequest[]): DocumentSummary {
  const pendingDocs = documents.filter(doc => doc.status === 'pending');
  const signedDocs = documents.filter(doc => doc.status === 'signed');
  const expiredDocs = documents.filter(doc => doc.status === 'expired');

  return {
    totalCount: documents.length,
    pendingCount: pendingDocs.length,
    signedCount: signedDocs.length,
    expiredCount: expiredDocs.length,
  };
}

/**
 * Generate mock document data for development
 */
export function getMockDocumentData(): DocumentListResponse {
  const mockDocuments: SignatureRequest[] = [
    {
      id: 'doc-001',
      documentTitle: 'Enrollment Agreement 2026-2027',
      description: 'Annual enrollment agreement for the upcoming school year',
      requestedAt: '2026-02-01T10:00:00Z',
      expiresAt: '2026-02-28T23:59:59Z',
      status: 'pending',
      signedAt: null,
      documentUrl: '/documents/enrollment-agreement-2026.pdf',
    },
    {
      id: 'doc-002',
      documentTitle: 'Photo & Video Release Consent',
      description: 'Permission for school to use photos and videos of your child',
      requestedAt: '2026-02-10T09:30:00Z',
      expiresAt: '2026-03-15T23:59:59Z',
      status: 'pending',
      signedAt: null,
      documentUrl: '/documents/photo-release.pdf',
    },
    {
      id: 'doc-003',
      documentTitle: 'Emergency Medical Authorization',
      description: 'Authorization for emergency medical treatment',
      requestedAt: '2026-01-15T14:00:00Z',
      expiresAt: null,
      status: 'signed',
      signedAt: '2026-01-18T11:23:45Z',
      documentUrl: '/documents/medical-auth.pdf',
    },
    {
      id: 'doc-004',
      documentTitle: 'Parent Handbook Acknowledgment',
      description: 'Confirm you have read and understand the parent handbook',
      requestedAt: '2026-01-10T08:00:00Z',
      expiresAt: null,
      status: 'signed',
      signedAt: '2026-01-12T16:45:00Z',
      documentUrl: '/documents/handbook-ack.pdf',
    },
    {
      id: 'doc-005',
      documentTitle: 'Field Trip Permission - Zoo Visit',
      description: 'Permission for your child to attend the upcoming zoo field trip',
      requestedAt: '2026-02-12T11:30:00Z',
      expiresAt: '2026-02-20T23:59:59Z',
      status: 'pending',
      signedAt: null,
      documentUrl: '/documents/field-trip-zoo.pdf',
    },
    {
      id: 'doc-006',
      documentTitle: 'Allergy & Dietary Information Update',
      description: 'Update your child\'s allergy and dietary information for the new term',
      requestedAt: '2025-12-15T10:00:00Z',
      expiresAt: '2025-12-30T23:59:59Z',
      status: 'expired',
      signedAt: null,
      documentUrl: '/documents/allergy-update.pdf',
    },
  ];

  return {
    documents: mockDocuments,
    summary: calculateSummary(mockDocuments),
  };
}
