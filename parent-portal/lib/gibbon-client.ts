/**
 * Type-safe Gibbon CMS API client for LAYA Parent Portal.
 *
 * Provides methods for interacting with the Gibbon CMS backend
 * for daily reports, invoices, messages, documents, and child data.
 */

import { gibbonClient, ApiError } from './api';
import type {
  Child,
  ChildProtocolOverview,
  CreateProtocolAuthorizationRequest,
  DailyReport,
  Document,
  DosingCalculationRequest,
  DosingCalculationResponse,
  Invoice,
  MedicalProtocol,
  Message,
  MessageThread,
  PaginatedResponse,
  PaginationParams,
  ProtocolAdministration,
  ProtocolAuthorization,
  ProtocolAuthorizationStatus,
  SendMessageRequest,
  CreateThreadRequest,
  SignDocumentRequest,
  UpdateWeightRequest,
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

  // Medical Protocols
  MEDICAL_PROTOCOLS: '/api/v1/medical-protocols',
  MEDICAL_PROTOCOL: (id: string) => `/api/v1/medical-protocols/${id}`,
  PROTOCOL_AUTHORIZATIONS: '/api/v1/medical-protocols/authorizations',
  PROTOCOL_AUTHORIZATION: (id: string) => `/api/v1/medical-protocols/authorizations/${id}`,
  CHILD_AUTHORIZATIONS: (childId: string) => `/api/v1/medical-protocols/children/${childId}/authorizations`,
  CHILD_PROTOCOL_OVERVIEW: (childId: string) => `/api/v1/medical-protocols/children/${childId}/overview`,
  PROTOCOL_ADMINISTRATIONS: '/api/v1/medical-protocols/administrations',
  CHILD_ADMINISTRATIONS: (childId: string) => `/api/v1/medical-protocols/children/${childId}/administrations`,
  CALCULATE_DOSING: '/api/v1/medical-protocols/calculate-dosing',
  UPDATE_WEIGHT: '/api/v1/medical-protocols/update-weight',
  REVOKE_AUTHORIZATION: (id: string) => `/api/v1/medical-protocols/authorizations/${id}/revoke`,
  ACKNOWLEDGE_ADMINISTRATION: (id: string) => `/api/v1/medical-protocols/administrations/${id}/acknowledge`,
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
// Medical Protocols API
// ============================================================================

/**
 * Fetch all available medical protocols.
 */
export async function getMedicalProtocols(): Promise<MedicalProtocol[]> {
  return gibbonClient.get<MedicalProtocol[]>(ENDPOINTS.MEDICAL_PROTOCOLS);
}

/**
 * Fetch a specific medical protocol by ID.
 */
export async function getMedicalProtocol(protocolId: string): Promise<MedicalProtocol> {
  return gibbonClient.get<MedicalProtocol>(ENDPOINTS.MEDICAL_PROTOCOL(protocolId));
}

/**
 * Parameters for fetching protocol authorizations.
 */
export interface ProtocolAuthorizationParams extends PaginationParams {
  childId?: string;
  protocolId?: string;
  status?: ProtocolAuthorizationStatus;
}

/**
 * Fetch protocol authorizations with optional filters.
 */
export async function getProtocolAuthorizations(
  params?: ProtocolAuthorizationParams
): Promise<PaginatedResponse<ProtocolAuthorization>> {
  return gibbonClient.get<PaginatedResponse<ProtocolAuthorization>>(ENDPOINTS.PROTOCOL_AUTHORIZATIONS, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      child_id: params?.childId,
      protocol_id: params?.protocolId,
      status: params?.status,
    },
  });
}

/**
 * Fetch a specific protocol authorization by ID.
 */
export async function getProtocolAuthorization(authorizationId: string): Promise<ProtocolAuthorization> {
  return gibbonClient.get<ProtocolAuthorization>(ENDPOINTS.PROTOCOL_AUTHORIZATION(authorizationId));
}

/**
 * Fetch all protocol authorizations for a specific child.
 */
export async function getChildAuthorizations(childId: string): Promise<ProtocolAuthorization[]> {
  return gibbonClient.get<ProtocolAuthorization[]>(ENDPOINTS.CHILD_AUTHORIZATIONS(childId));
}

/**
 * Fetch the protocol overview for a specific child.
 */
export async function getChildProtocolOverview(childId: string): Promise<ChildProtocolOverview> {
  return gibbonClient.get<ChildProtocolOverview>(ENDPOINTS.CHILD_PROTOCOL_OVERVIEW(childId));
}

/**
 * Create a new protocol authorization.
 */
export async function createProtocolAuthorization(
  request: CreateProtocolAuthorizationRequest
): Promise<ProtocolAuthorization> {
  return gibbonClient.post<ProtocolAuthorization>(ENDPOINTS.PROTOCOL_AUTHORIZATIONS, {
    child_id: request.childId,
    protocol_id: request.protocolId,
    weight_kg: request.weightKg,
    signature_data: request.signatureData,
    agreement_text: request.agreementText,
  });
}

/**
 * Update a child's weight for a protocol authorization.
 */
export async function updateChildWeight(request: UpdateWeightRequest): Promise<ProtocolAuthorization> {
  return gibbonClient.post<ProtocolAuthorization>(ENDPOINTS.UPDATE_WEIGHT, {
    child_id: request.childId,
    protocol_id: request.protocolId,
    weight_kg: request.weightKg,
  });
}

/**
 * Revoke request payload.
 */
export interface RevokeAuthorizationRequest {
  authorizationId: string;
  reason?: string;
}

/**
 * Revoke a protocol authorization.
 */
export async function revokeProtocolAuthorization(
  request: RevokeAuthorizationRequest
): Promise<ProtocolAuthorization> {
  return gibbonClient.post<ProtocolAuthorization>(ENDPOINTS.REVOKE_AUTHORIZATION(request.authorizationId), {
    reason: request.reason,
  });
}

/**
 * Calculate dosing information for a given weight.
 */
export async function calculateDosing(
  request: DosingCalculationRequest
): Promise<DosingCalculationResponse> {
  return gibbonClient.post<DosingCalculationResponse>(ENDPOINTS.CALCULATE_DOSING, {
    protocol_id: request.protocolId,
    weight_kg: request.weightKg,
    concentration: request.concentration,
  });
}

/**
 * Parameters for fetching protocol administrations.
 */
export interface ProtocolAdministrationParams extends PaginationParams {
  childId?: string;
  protocolId?: string;
  startDate?: string;
  endDate?: string;
}

/**
 * Fetch protocol administrations with optional filters.
 */
export async function getProtocolAdministrations(
  params?: ProtocolAdministrationParams
): Promise<PaginatedResponse<ProtocolAdministration>> {
  return gibbonClient.get<PaginatedResponse<ProtocolAdministration>>(ENDPOINTS.PROTOCOL_ADMINISTRATIONS, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      child_id: params?.childId,
      protocol_id: params?.protocolId,
      start_date: params?.startDate,
      end_date: params?.endDate,
    },
  });
}

/**
 * Fetch all protocol administrations for a specific child.
 */
export async function getChildAdministrations(
  childId: string,
  params?: PaginationParams
): Promise<PaginatedResponse<ProtocolAdministration>> {
  return gibbonClient.get<PaginatedResponse<ProtocolAdministration>>(ENDPOINTS.CHILD_ADMINISTRATIONS(childId), {
    params: {
      skip: params?.skip,
      limit: params?.limit,
    },
  });
}

/**
 * Acknowledge a protocol administration as a parent.
 */
export async function acknowledgeAdministration(administrationId: string): Promise<ProtocolAdministration> {
  return gibbonClient.post<ProtocolAdministration>(ENDPOINTS.ACKNOWLEDGE_ADMINISTRATION(administrationId));
}

/**
 * Get the count of active protocol authorizations for the current parent.
 */
export async function getActiveAuthorizationsCount(): Promise<number> {
  const response = await getProtocolAuthorizations({ status: 'active', limit: 1 });
  return response.total;
}

/**
 * Get the count of pending protocol authorizations requiring action.
 */
export async function getPendingAuthorizationsCount(): Promise<number> {
  const response = await getProtocolAuthorizations({ status: 'pending', limit: 1 });
  return response.total;
}

/**
 * Get the count of unacknowledged administrations for a child.
 */
export async function getUnacknowledgedAdministrationsCount(childId: string): Promise<number> {
  const response = await getChildAdministrations(childId, { limit: 100 });
  return response.items.filter((admin) => admin.parentNotified && !admin.parentAcknowledged).length;
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
