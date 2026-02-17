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
  Invoice,
  Message,
  MessageThread,
  PaginatedResponse,
  PaginationParams,
  SendMessageRequest,
  CreateThreadRequest,
  SignDocumentRequest,
  ServiceAgreement,
  ServiceAgreementSummary,
  ServiceAgreementStatus,
  SignServiceAgreementRequest,
  SignServiceAgreementResponse,
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

  // Service Agreements
  SERVICE_AGREEMENTS: '/api/v1/service-agreements',
  SERVICE_AGREEMENT: (id: string) => `/api/v1/service-agreements/${id}`,
  SIGN_SERVICE_AGREEMENT: (id: string) => `/api/v1/service-agreements/${id}/sign`,
  SERVICE_AGREEMENT_PDF: (id: string) => `/api/v1/service-agreements/${id}/pdf`,
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
// Service Agreements API
// ============================================================================

/**
 * Parameters for fetching service agreements.
 */
export interface ServiceAgreementParams extends PaginationParams {
  status?: ServiceAgreementStatus;
  childId?: string;
  schoolYearId?: string;
}

/**
 * Fetch service agreements with optional filters.
 */
export async function getServiceAgreements(
  params?: ServiceAgreementParams
): Promise<PaginatedResponse<ServiceAgreementSummary>> {
  return gibbonClient.get<PaginatedResponse<ServiceAgreementSummary>>(ENDPOINTS.SERVICE_AGREEMENTS, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      status: params?.status,
      child_id: params?.childId,
      school_year_id: params?.schoolYearId,
    },
  });
}

/**
 * Fetch a specific service agreement by ID.
 */
export async function getServiceAgreement(agreementId: string): Promise<ServiceAgreement> {
  return gibbonClient.get<ServiceAgreement>(ENDPOINTS.SERVICE_AGREEMENT(agreementId));
}

/**
 * Sign a service agreement electronically.
 */
export async function signServiceAgreement(
  request: SignServiceAgreementRequest
): Promise<SignServiceAgreementResponse> {
  return gibbonClient.post<SignServiceAgreementResponse>(
    ENDPOINTS.SIGN_SERVICE_AGREEMENT(request.agreementId),
    {
      signature_data: request.signatureData,
      signature_type: request.signatureType,
      consumer_protection_acknowledged: request.consumerProtectionAcknowledged,
      terms_accepted: request.termsAccepted,
      legal_acknowledged: request.legalAcknowledged,
      annex_signatures: request.annexSignatures?.map((a) => ({
        annex_id: a.annexId,
        signed: a.signed,
      })),
    }
  );
}

/**
 * Get the PDF download URL for a service agreement.
 */
export function getServiceAgreementPdfUrl(agreementId: string): string {
  return `${process.env.NEXT_PUBLIC_GIBBON_URL || 'http://localhost:8080/gibbon'}${ENDPOINTS.SERVICE_AGREEMENT_PDF(agreementId)}`;
}

/**
 * Get pending service agreements count.
 */
export async function getPendingServiceAgreementsCount(): Promise<number> {
  const response = await getServiceAgreements({ status: 'pending_signature', limit: 1 });
  return response.total;
}

/**
 * Fetch service agreements that require parent signature.
 */
export async function getServiceAgreementsRequiringSignature(): Promise<ServiceAgreementSummary[]> {
  const response = await getServiceAgreements({ status: 'pending_signature' });
  return response.items.filter((agreement) => !agreement.parentSignedAt);
}

/**
 * Fetch active service agreements for a child.
 */
export async function getActiveServiceAgreementsForChild(
  childId: string
): Promise<ServiceAgreementSummary[]> {
  const response = await getServiceAgreements({ childId, status: 'active' });
  return response.items;
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
