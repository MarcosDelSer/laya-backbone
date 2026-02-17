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
  DietaryProfile,
  Document,
  DosingCalculationRequest,
  DosingCalculationResponse,
  Invoice,
  MenuItem,
  Message,
  MessageThread,
  NutritionalReport,
  PaginatedResponse,
  PaginationParams,
  ProtocolAdministration,
  ProtocolAuthorization,
  ProtocolAuthorizationStatus,
  SendMessageRequest,
  CreateThreadRequest,
  SignDocumentRequest,
  EnrollmentForm,
  EnrollmentFormSummary,
  EnrollmentFormStatus,
  CreateEnrollmentFormRequest,
  UpdateEnrollmentFormRequest,
  SignEnrollmentFormRequest,
  SubmitEnrollmentFormRequest,
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

  // Enrollment Forms
  ENROLLMENT_FORMS: '/api/v1/enrollment-forms',
  ENROLLMENT_FORM: (id: string) => `/api/v1/enrollment-forms/${id}`,
  ENROLLMENT_FORM_SIGN: (id: string) => `/api/v1/enrollment-forms/${id}/sign`,
  ENROLLMENT_FORM_SUBMIT: (id: string) => `/api/v1/enrollment-forms/${id}/submit`,
  ENROLLMENT_FORM_PDF: (id: string) => `/api/v1/enrollment-forms/${id}/pdf`,
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
// Enrollment Forms API
// ============================================================================

/**
 * Parameters for fetching enrollment forms.
 */
export interface EnrollmentFormParams extends PaginationParams {
  status?: EnrollmentFormStatus;
  familyId?: string;
  personId?: string;
}

/**
 * Fetch enrollment forms with optional filters.
 */
export async function getEnrollmentForms(
  params?: EnrollmentFormParams
): Promise<PaginatedResponse<EnrollmentFormSummary>> {
  return gibbonClient.get<PaginatedResponse<EnrollmentFormSummary>>(ENDPOINTS.ENROLLMENT_FORMS, {
    params: {
      skip: params?.skip,
      limit: params?.limit,
      status: params?.status,
      family_id: params?.familyId,
      person_id: params?.personId,
    },
  });
}

/**
 * Fetch a specific enrollment form by ID with all related data.
 */
export async function getEnrollmentForm(formId: string): Promise<EnrollmentForm> {
  return gibbonClient.get<EnrollmentForm>(ENDPOINTS.ENROLLMENT_FORM(formId));
}

/**
 * Create a new enrollment form.
 */
export async function createEnrollmentForm(
  request: CreateEnrollmentFormRequest
): Promise<EnrollmentForm> {
  return gibbonClient.post<EnrollmentForm>(ENDPOINTS.ENROLLMENT_FORMS, request);
}

/**
 * Update an existing enrollment form.
 */
export async function updateEnrollmentForm(
  formId: string,
  request: UpdateEnrollmentFormRequest
): Promise<EnrollmentForm> {
  return gibbonClient.put<EnrollmentForm>(ENDPOINTS.ENROLLMENT_FORM(formId), request);
}

/**
 * Delete an enrollment form (only draft forms can be deleted).
 */
export async function deleteEnrollmentForm(formId: string): Promise<void> {
  return gibbonClient.delete<void>(ENDPOINTS.ENROLLMENT_FORM(formId));
}

/**
 * Sign an enrollment form with e-signature.
 */
export async function signEnrollmentForm(
  request: SignEnrollmentFormRequest
): Promise<EnrollmentForm> {
  return gibbonClient.post<EnrollmentForm>(ENDPOINTS.ENROLLMENT_FORM_SIGN(request.formId), {
    signature_type: request.signatureType,
    signature_data: request.signatureData,
    signer_name: request.signerName,
  });
}

/**
 * Submit an enrollment form for approval.
 */
export async function submitEnrollmentForm(
  request: SubmitEnrollmentFormRequest
): Promise<EnrollmentForm> {
  return gibbonClient.post<EnrollmentForm>(ENDPOINTS.ENROLLMENT_FORM_SUBMIT(request.formId));
}

/**
 * Get the PDF download URL for an enrollment form.
 */
export function getEnrollmentFormPdfUrl(formId: string): string {
  return `${process.env.NEXT_PUBLIC_GIBBON_URL || 'http://localhost:8080/gibbon'}${ENDPOINTS.ENROLLMENT_FORM_PDF(formId)}`;
}

/**
 * Get enrollment forms for a specific family.
 */
export async function getFamilyEnrollmentForms(
  familyId: string,
  params?: Omit<EnrollmentFormParams, 'familyId'>
): Promise<PaginatedResponse<EnrollmentFormSummary>> {
  return getEnrollmentForms({
    ...params,
    familyId,
  });
}

/**
 * Get draft enrollment forms count.
 */
export async function getDraftEnrollmentFormsCount(): Promise<number> {
  const response = await getEnrollmentForms({ status: 'Draft', limit: 1 });
  return response.total;
}

/**
 * Get pending submission enrollment forms count.
 */
export async function getSubmittedEnrollmentFormsCount(): Promise<number> {
  const response = await getEnrollmentForms({ status: 'Submitted', limit: 1 });
  return response.total;
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
