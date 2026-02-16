/**
 * Type-safe Gibbon CMS API client for LAYA Parent Portal.
 *
 * Provides methods for interacting with the Gibbon CMS backend
 * for daily reports, invoices, messages, documents, and child data.
 */

import { gibbonClient, ApiError } from './api';
import type {
  Child,
  DailyReport,
  Document,
  GovernmentDocument,
  GovernmentDocumentChecklist,
  GovernmentDocumentStats,
  GovernmentDocumentTypeDefinition,
  GovernmentDocumentUploadRequest,
  Invoice,
  Message,
  MessageThread,
  PaginatedResponse,
  PaginationParams,
  SendMessageRequest,
  CreateThreadRequest,
  SignDocumentRequest,
} from './types';

// ============================================================================
// API Endpoints
// ============================================================================

const ENDPOINTS = {
  // Children
  CHILDREN: '/api/v1/children',
  CHILD: (id: string) => `/api/v1/children/${id}`,

  // Daily Reports
  DAILY_REPORTS: '/api/v1/daily-reports',
  DAILY_REPORT: (id: string) => `/api/v1/daily-reports/${id}`,

  // Invoices
  INVOICES: '/api/v1/invoices',
  INVOICE: (id: string) => `/api/v1/invoices/${id}`,
  INVOICE_PDF: (id: string) => `/api/v1/invoices/${id}/pdf`,

  // Messages
  MESSAGE_THREADS: '/api/v1/messages/threads',
  MESSAGE_THREAD: (id: string) => `/api/v1/messages/threads/${id}`,
  THREAD_MESSAGES: (threadId: string) => `/api/v1/messages/threads/${threadId}/messages`,
  SEND_MESSAGE: '/api/v1/messages/send',
  MARK_READ: (messageId: string) => `/api/v1/messages/${messageId}/read`,

  // Documents
  DOCUMENTS: '/api/v1/documents',
  DOCUMENT: (id: string) => `/api/v1/documents/${id}`,
  SIGN_DOCUMENT: (id: string) => `/api/v1/documents/${id}/sign`,
  DOCUMENT_PDF: (id: string) => `/api/v1/documents/${id}/pdf`,

  // Government Documents
  GOVERNMENT_DOCUMENTS: '/api/v1/government-documents',
  GOVERNMENT_DOCUMENT: (id: string) => `/api/v1/government-documents/${id}`,
  GOVERNMENT_DOCUMENT_TYPES: '/api/v1/government-documents/types',
  GOVERNMENT_DOCUMENT_CHECKLIST: '/api/v1/government-documents/checklist',
  GOVERNMENT_DOCUMENT_STATS: '/api/v1/government-documents/stats',
  GOVERNMENT_DOCUMENT_UPLOAD: '/api/v1/government-documents/upload',
  GOVERNMENT_DOCUMENT_FILE: (id: string) => `/api/v1/government-documents/${id}/file`,
} as const;

// ============================================================================
// Child API
// ============================================================================

/**
 * Fetch all children for the current parent.
 */
export async function getChildren(): Promise<Child[]> {
  return gibbonClient.get<Child[]>(ENDPOINTS.CHILDREN);
}

/**
 * Fetch a specific child by ID.
 */
export async function getChild(childId: string): Promise<Child> {
  return gibbonClient.get<Child>(ENDPOINTS.CHILD(childId));
}

// ============================================================================
// Daily Reports API
// ============================================================================

/**
 * Parameters for fetching daily reports.
 */
export interface DailyReportsParams extends PaginationParams {
  childId?: string;
  startDate?: string;
  endDate?: string;
}

/**
 * Fetch daily reports with optional filters.
 */
export async function getDailyReports(
  params?: DailyReportsParams
): Promise<PaginatedResponse<DailyReport>> {
  return gibbonClient.get<PaginatedResponse<DailyReport>>(ENDPOINTS.DAILY_REPORTS, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      child_id: params?.childId,
      start_date: params?.startDate,
      end_date: params?.endDate,
    },
  });
}

/**
 * Fetch a specific daily report by ID.
 */
export async function getDailyReport(reportId: string): Promise<DailyReport> {
  return gibbonClient.get<DailyReport>(ENDPOINTS.DAILY_REPORT(reportId));
}

/**
 * Fetch the most recent daily report for a child.
 */
export async function getLatestDailyReport(childId: string): Promise<DailyReport | null> {
  const response = await getDailyReports({
    childId,
    limit: 1,
  });

  return response.items[0] || null;
}

// ============================================================================
// Invoice API
// ============================================================================

/**
 * Parameters for fetching invoices.
 */
export interface InvoiceParams extends PaginationParams {
  status?: 'paid' | 'pending' | 'overdue';
  startDate?: string;
  endDate?: string;
}

/**
 * Fetch invoices with optional filters.
 */
export async function getInvoices(
  params?: InvoiceParams
): Promise<PaginatedResponse<Invoice>> {
  return gibbonClient.get<PaginatedResponse<Invoice>>(ENDPOINTS.INVOICES, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      status: params?.status,
      start_date: params?.startDate,
      end_date: params?.endDate,
    },
  });
}

/**
 * Fetch a specific invoice by ID.
 */
export async function getInvoice(invoiceId: string): Promise<Invoice> {
  return gibbonClient.get<Invoice>(ENDPOINTS.INVOICE(invoiceId));
}

/**
 * Get the PDF download URL for an invoice.
 */
export function getInvoicePdfUrl(invoiceId: string): string {
  return `${process.env.NEXT_PUBLIC_GIBBON_URL || 'http://localhost:8080/gibbon'}${ENDPOINTS.INVOICE_PDF(invoiceId)}`;
}

/**
 * Fetch invoice summary statistics.
 */
export interface InvoiceSummary {
  totalPending: number;
  totalOverdue: number;
  totalPaid: number;
  pendingCount: number;
  overdueCount: number;
  paidCount: number;
}

export async function getInvoiceSummary(): Promise<InvoiceSummary> {
  const [pending, overdue, paid] = await Promise.all([
    getInvoices({ status: 'pending' }),
    getInvoices({ status: 'overdue' }),
    getInvoices({ status: 'paid', limit: 100 }),
  ]);

  return {
    totalPending: pending.items.reduce((sum, inv) => sum + inv.amount, 0),
    totalOverdue: overdue.items.reduce((sum, inv) => sum + inv.amount, 0),
    totalPaid: paid.items.reduce((sum, inv) => sum + inv.amount, 0),
    pendingCount: pending.total,
    overdueCount: overdue.total,
    paidCount: paid.total,
  };
}

// ============================================================================
// Messages API
// ============================================================================

/**
 * Parameters for fetching message threads.
 */
export interface ThreadParams extends PaginationParams {
  unreadOnly?: boolean;
}

/**
 * Fetch message threads with optional filters.
 */
export async function getMessageThreads(
  params?: ThreadParams
): Promise<PaginatedResponse<MessageThread>> {
  return gibbonClient.get<PaginatedResponse<MessageThread>>(ENDPOINTS.MESSAGE_THREADS, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      unread_only: params?.unreadOnly,
    },
  });
}

/**
 * Fetch a specific message thread by ID.
 */
export async function getMessageThread(threadId: string): Promise<MessageThread> {
  return gibbonClient.get<MessageThread>(ENDPOINTS.MESSAGE_THREAD(threadId));
}

/**
 * Fetch messages in a thread.
 */
export async function getThreadMessages(
  threadId: string,
  params?: PaginationParams
): Promise<PaginatedResponse<Message>> {
  return gibbonClient.get<PaginatedResponse<Message>>(ENDPOINTS.THREAD_MESSAGES(threadId), {
    params: {
      skip: params?.skip,
      limit: params?.limit,
    },
  });
}

/**
 * Send a message in an existing thread.
 */
export async function sendMessage(request: SendMessageRequest): Promise<Message> {
  return gibbonClient.post<Message>(ENDPOINTS.SEND_MESSAGE, request);
}

/**
 * Create a new message thread.
 */
export async function createThread(request: CreateThreadRequest): Promise<MessageThread> {
  return gibbonClient.post<MessageThread>(ENDPOINTS.MESSAGE_THREADS, request);
}

/**
 * Mark a message as read.
 */
export async function markMessageAsRead(messageId: string): Promise<void> {
  return gibbonClient.post<void>(ENDPOINTS.MARK_READ(messageId));
}

/**
 * Get total unread message count.
 */
export async function getUnreadCount(): Promise<number> {
  const response = await getMessageThreads({ unreadOnly: true });
  return response.items.reduce((sum, thread) => sum + thread.unreadCount, 0);
}

// ============================================================================
// Documents API
// ============================================================================

/**
 * Parameters for fetching documents.
 */
export interface DocumentParams extends PaginationParams {
  status?: 'pending' | 'signed';
  type?: string;
}

/**
 * Fetch documents with optional filters.
 */
export async function getDocuments(
  params?: DocumentParams
): Promise<PaginatedResponse<Document>> {
  return gibbonClient.get<PaginatedResponse<Document>>(ENDPOINTS.DOCUMENTS, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      status: params?.status,
      type: params?.type,
    },
  });
}

/**
 * Fetch a specific document by ID.
 */
export async function getDocument(documentId: string): Promise<Document> {
  return gibbonClient.get<Document>(ENDPOINTS.DOCUMENT(documentId));
}

/**
 * Sign a document electronically.
 */
export async function signDocument(request: SignDocumentRequest): Promise<Document> {
  return gibbonClient.post<Document>(ENDPOINTS.SIGN_DOCUMENT(request.documentId), {
    signature_data: request.signatureData,
  });
}

/**
 * Get the PDF download URL for a document.
 */
export function getDocumentPdfUrl(documentId: string): string {
  return `${process.env.NEXT_PUBLIC_GIBBON_URL || 'http://localhost:8080/gibbon'}${ENDPOINTS.DOCUMENT_PDF(documentId)}`;
}

/**
 * Get pending documents count.
 */
export async function getPendingDocumentsCount(): Promise<number> {
  const response = await getDocuments({ status: 'pending', limit: 1 });
  return response.total;
}

// ============================================================================
// Government Documents API
// ============================================================================

/**
 * Parameters for fetching government documents.
 */
export interface GovernmentDocumentParams extends PaginationParams {
  personId?: string;
  status?: 'missing' | 'pending_verification' | 'verified' | 'rejected' | 'expired';
  category?: 'child_identity' | 'parent_identity' | 'health' | 'immigration';
}

/**
 * Fetch government documents with optional filters.
 */
export async function getGovernmentDocuments(
  params?: GovernmentDocumentParams
): Promise<PaginatedResponse<GovernmentDocument>> {
  return gibbonClient.get<PaginatedResponse<GovernmentDocument>>(ENDPOINTS.GOVERNMENT_DOCUMENTS, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      person_id: params?.personId,
      status: params?.status,
      category: params?.category,
    },
  });
}

/**
 * Fetch a specific government document by ID.
 */
export async function getGovernmentDocument(documentId: string): Promise<GovernmentDocument> {
  return gibbonClient.get<GovernmentDocument>(ENDPOINTS.GOVERNMENT_DOCUMENT(documentId));
}

/**
 * Fetch all available government document types.
 */
export async function getGovernmentDocumentTypes(): Promise<GovernmentDocumentTypeDefinition[]> {
  return gibbonClient.get<GovernmentDocumentTypeDefinition[]>(ENDPOINTS.GOVERNMENT_DOCUMENT_TYPES);
}

/**
 * Fetch the family document checklist.
 */
export async function getGovernmentDocumentChecklist(): Promise<GovernmentDocumentChecklist> {
  return gibbonClient.get<GovernmentDocumentChecklist>(ENDPOINTS.GOVERNMENT_DOCUMENT_CHECKLIST);
}

/**
 * Fetch government document statistics.
 */
export async function getGovernmentDocumentStats(): Promise<GovernmentDocumentStats> {
  return gibbonClient.get<GovernmentDocumentStats>(ENDPOINTS.GOVERNMENT_DOCUMENT_STATS);
}

/**
 * Upload a government document.
 */
export async function uploadGovernmentDocument(
  request: GovernmentDocumentUploadRequest
): Promise<GovernmentDocument> {
  const formData = new FormData();
  formData.append('person_id', request.personId);
  formData.append('document_type_id', request.documentTypeId);
  formData.append('file', request.file);

  if (request.documentNumber) {
    formData.append('document_number', request.documentNumber);
  }
  if (request.issueDate) {
    formData.append('issue_date', request.issueDate);
  }
  if (request.expirationDate) {
    formData.append('expiration_date', request.expirationDate);
  }
  if (request.notes) {
    formData.append('notes', request.notes);
  }

  return gibbonClient.post<GovernmentDocument>(ENDPOINTS.GOVERNMENT_DOCUMENT_UPLOAD, formData);
}

/**
 * Get the file download URL for a government document.
 */
export function getGovernmentDocumentFileUrl(documentId: string): string {
  return `${process.env.NEXT_PUBLIC_GIBBON_URL || 'http://localhost:8080/gibbon'}${ENDPOINTS.GOVERNMENT_DOCUMENT_FILE(documentId)}`;
}

/**
 * Delete a government document.
 */
export async function deleteGovernmentDocument(documentId: string): Promise<void> {
  return gibbonClient.delete<void>(ENDPOINTS.GOVERNMENT_DOCUMENT(documentId));
}

/**
 * Get documents requiring attention (missing, expired, or expiring soon).
 */
export async function getGovernmentDocumentsRequiringAttention(): Promise<GovernmentDocument[]> {
  const [missing, expired, pendingVerification] = await Promise.all([
    getGovernmentDocuments({ status: 'missing' }),
    getGovernmentDocuments({ status: 'expired' }),
    getGovernmentDocuments({ status: 'pending_verification' }),
  ]);

  return [...missing.items, ...expired.items, ...pendingVerification.items];
}

// ============================================================================
// Error Handling Helpers
// ============================================================================

/**
 * Check if an error is an API error.
 */
export function isApiError(error: unknown): error is ApiError {
  return error instanceof ApiError;
}

/**
 * Get user-friendly error message.
 */
export function getErrorMessage(error: unknown): string {
  if (isApiError(error)) {
    return error.userMessage;
  }
  if (error instanceof Error) {
    return error.message;
  }
  return 'An unexpected error occurred.';
}
