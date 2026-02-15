/**
 * LAYA Parent App - Invoices API
 *
 * API functions for fetching and managing invoices.
 * Provides invoice listing, details, PDF download, and summary statistics
 * for parents to track their childcare payments.
 */

import {api} from './client';
import {API_CONFIG} from './config';
import type {
  ApiResponse,
  Invoice,
  InvoiceStatus,
  InvoiceItem,
  PaginatedResponse,
} from '../types';

// ============================================================================
// Response Types
// ============================================================================

/**
 * Response type for invoices list endpoint
 */
export interface InvoicesListResponse {
  invoices: Invoice[];
  summary: InvoiceSummary;
}

/**
 * Summary statistics for invoices
 */
export interface InvoiceSummary {
  totalAmount: number;
  paidAmount: number;
  pendingAmount: number;
  overdueAmount: number;
  totalInvoices: number;
  paidCount: number;
  pendingCount: number;
  overdueCount: number;
}

/**
 * Filter options for fetching invoices
 */
export interface InvoicesFilter {
  status?: InvoiceStatus;
  startDate?: string;
  endDate?: string;
  limit?: number;
  offset?: number;
}

/**
 * Response type for PDF download
 */
export interface InvoicePdfResponse {
  pdfUrl: string;
  expiresAt: string;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Format currency amount for display (e.g., "$1,234.56")
 */
export function formatCurrency(amount: number, currency: string = 'USD'): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
  }).format(amount);
}

/**
 * Format date for display (e.g., "January 15, 2024")
 */
export function formatDateForDisplay(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Format date for short display (e.g., "Jan 15, 2024")
 */
export function formatDateShort(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

/**
 * Get the current date in YYYY-MM-DD format
 */
function getCurrentDate(): string {
  const now = new Date();
  return now.toISOString().split('T')[0];
}

/**
 * Check if an invoice is overdue
 */
export function isInvoiceOverdue(invoice: Invoice): boolean {
  if (invoice.status === 'paid') {
    return false;
  }
  const dueDate = new Date(invoice.dueDate);
  const today = new Date(getCurrentDate());
  return dueDate < today;
}

/**
 * Get days until due (negative if overdue)
 */
export function getDaysUntilDue(dueDate: string): number {
  const due = new Date(dueDate);
  const today = new Date(getCurrentDate());
  const diffTime = due.getTime() - today.getTime();
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

/**
 * Get due status display text
 */
export function getDueStatusDisplay(invoice: Invoice): string {
  if (invoice.status === 'paid') {
    return 'Paid';
  }

  const daysUntilDue = getDaysUntilDue(invoice.dueDate);

  if (daysUntilDue < 0) {
    const daysOverdue = Math.abs(daysUntilDue);
    return `${daysOverdue} day${daysOverdue > 1 ? 's' : ''} overdue`;
  }

  if (daysUntilDue === 0) {
    return 'Due today';
  }

  if (daysUntilDue === 1) {
    return 'Due tomorrow';
  }

  return `Due in ${daysUntilDue} days`;
}

/**
 * Get status badge color for UI display
 */
export function getStatusColor(status: InvoiceStatus): {
  background: string;
  text: string;
} {
  const colorMap: Record<InvoiceStatus, {background: string; text: string}> = {
    paid: {background: '#dcfce7', text: '#166534'},
    pending: {background: '#fef3c7', text: '#92400e'},
    overdue: {background: '#fee2e2', text: '#991b1b'},
  };
  return colorMap[status];
}

/**
 * Get status display text
 */
export function getStatusDisplay(status: InvoiceStatus): string {
  const displayMap: Record<InvoiceStatus, string> = {
    paid: 'Paid',
    pending: 'Pending',
    overdue: 'Overdue',
  };
  return displayMap[status];
}

/**
 * Calculate line item total
 */
export function calculateItemTotal(item: InvoiceItem): number {
  return item.quantity * item.unitPrice;
}

/**
 * Calculate invoice subtotal from items
 */
export function calculateSubtotal(items: InvoiceItem[]): number {
  return items.reduce((total, item) => total + item.total, 0);
}

// ============================================================================
// API Functions
// ============================================================================

/**
 * Fetch all invoices for the parent
 */
export async function fetchInvoices(
  options?: InvoicesFilter,
): Promise<ApiResponse<InvoicesListResponse>> {
  const params: Record<string, string> = {};

  if (options?.status) {
    params.status = options.status;
  }
  if (options?.startDate) {
    params.startDate = options.startDate;
  }
  if (options?.endDate) {
    params.endDate = options.endDate;
  }
  if (options?.limit !== undefined) {
    params.limit = String(options.limit);
  }
  if (options?.offset !== undefined) {
    params.offset = String(options.offset);
  }

  return api.get<InvoicesListResponse>(
    API_CONFIG.endpoints.invoices.list,
    params,
  );
}

/**
 * Fetch invoices with pagination
 */
export async function fetchInvoicesPaginated(
  options?: InvoicesFilter,
): Promise<ApiResponse<PaginatedResponse<Invoice>>> {
  const params: Record<string, string> = {};

  if (options?.status) {
    params.status = options.status;
  }
  if (options?.startDate) {
    params.startDate = options.startDate;
  }
  if (options?.endDate) {
    params.endDate = options.endDate;
  }
  if (options?.limit !== undefined) {
    params.limit = String(options.limit);
  }
  if (options?.offset !== undefined) {
    params.offset = String(options.offset);
  }

  return api.get<PaginatedResponse<Invoice>>(
    API_CONFIG.endpoints.invoices.list,
    params,
  );
}

/**
 * Fetch a single invoice by ID
 */
export async function fetchInvoiceById(
  invoiceId: string,
): Promise<ApiResponse<Invoice>> {
  return api.get<Invoice>(
    API_CONFIG.endpoints.invoices.details,
    {id: invoiceId},
  );
}

/**
 * Get PDF download URL for an invoice
 */
export async function getInvoicePdfUrl(
  invoiceId: string,
): Promise<ApiResponse<InvoicePdfResponse>> {
  return api.get<InvoicePdfResponse>(
    API_CONFIG.endpoints.invoices.downloadPdf,
    {id: invoiceId},
  );
}

/**
 * Fetch unpaid invoices (pending and overdue)
 */
export async function fetchUnpaidInvoices(): Promise<ApiResponse<InvoicesListResponse>> {
  // Fetch all invoices and filter client-side
  const result = await fetchInvoices();

  if (!result.success || !result.data) {
    return result;
  }

  const unpaidInvoices = result.data.invoices.filter(
    invoice => invoice.status !== 'paid',
  );

  return {
    success: true,
    data: {
      invoices: unpaidInvoices,
      summary: calculateSummary(unpaidInvoices),
    },
    error: null,
  };
}

/**
 * Fetch overdue invoices
 */
export async function fetchOverdueInvoices(): Promise<ApiResponse<InvoicesListResponse>> {
  return fetchInvoices({status: 'overdue'});
}

// ============================================================================
// Data Processing Functions
// ============================================================================

/**
 * Calculate summary statistics from invoices
 */
export function calculateSummary(invoices: Invoice[]): InvoiceSummary {
  const summary: InvoiceSummary = {
    totalAmount: 0,
    paidAmount: 0,
    pendingAmount: 0,
    overdueAmount: 0,
    totalInvoices: invoices.length,
    paidCount: 0,
    pendingCount: 0,
    overdueCount: 0,
  };

  for (const invoice of invoices) {
    summary.totalAmount += invoice.amount;

    switch (invoice.status) {
      case 'paid':
        summary.paidAmount += invoice.amount;
        summary.paidCount++;
        break;
      case 'pending':
        summary.pendingAmount += invoice.amount;
        summary.pendingCount++;
        break;
      case 'overdue':
        summary.overdueAmount += invoice.amount;
        summary.overdueCount++;
        break;
    }
  }

  return summary;
}

/**
 * Sort invoices by date (most recent first)
 */
export function sortInvoicesByDate(invoices: Invoice[]): Invoice[] {
  return [...invoices].sort((a, b) => {
    return new Date(b.date).getTime() - new Date(a.date).getTime();
  });
}

/**
 * Sort invoices by due date (soonest first)
 */
export function sortInvoicesByDueDate(invoices: Invoice[]): Invoice[] {
  return [...invoices].sort((a, b) => {
    return new Date(a.dueDate).getTime() - new Date(b.dueDate).getTime();
  });
}

/**
 * Sort invoices by amount (highest first)
 */
export function sortInvoicesByAmount(invoices: Invoice[]): Invoice[] {
  return [...invoices].sort((a, b) => b.amount - a.amount);
}

/**
 * Sort invoices by status priority (overdue first, then pending, then paid)
 */
export function sortInvoicesByStatusPriority(invoices: Invoice[]): Invoice[] {
  const priorityMap: Record<InvoiceStatus, number> = {
    overdue: 0,
    pending: 1,
    paid: 2,
  };

  return [...invoices].sort((a, b) => {
    const priorityDiff = priorityMap[a.status] - priorityMap[b.status];
    if (priorityDiff !== 0) {
      return priorityDiff;
    }
    // If same status, sort by due date (soonest first)
    return new Date(a.dueDate).getTime() - new Date(b.dueDate).getTime();
  });
}

/**
 * Filter invoices by status
 */
export function filterInvoicesByStatus(
  invoices: Invoice[],
  status: InvoiceStatus,
): Invoice[] {
  return invoices.filter(invoice => invoice.status === status);
}

/**
 * Filter invoices by date range
 */
export function filterInvoicesByDateRange(
  invoices: Invoice[],
  startDate: string,
  endDate: string,
): Invoice[] {
  const start = new Date(startDate);
  const end = new Date(endDate);

  return invoices.filter(invoice => {
    const invoiceDate = new Date(invoice.date);
    return invoiceDate >= start && invoiceDate <= end;
  });
}

/**
 * Group invoices by month (e.g., "January 2024")
 */
export function groupInvoicesByMonth(
  invoices: Invoice[],
): Map<string, Invoice[]> {
  const grouped = new Map<string, Invoice[]>();

  for (const invoice of invoices) {
    const date = new Date(invoice.date);
    const monthKey = date.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
    });

    const existing = grouped.get(monthKey) || [];
    existing.push(invoice);
    grouped.set(monthKey, existing);
  }

  return grouped;
}

/**
 * Group invoices by status
 */
export function groupInvoicesByStatus(
  invoices: Invoice[],
): Record<InvoiceStatus, Invoice[]> {
  const grouped: Record<InvoiceStatus, Invoice[]> = {
    paid: [],
    pending: [],
    overdue: [],
  };

  for (const invoice of invoices) {
    grouped[invoice.status].push(invoice);
  }

  return grouped;
}

/**
 * Get the most recent invoice
 */
export function getMostRecentInvoice(invoices: Invoice[]): Invoice | null {
  if (invoices.length === 0) {
    return null;
  }
  return sortInvoicesByDate(invoices)[0];
}

/**
 * Get the next due invoice (unpaid)
 */
export function getNextDueInvoice(invoices: Invoice[]): Invoice | null {
  const unpaidInvoices = invoices.filter(invoice => invoice.status !== 'paid');
  if (unpaidInvoices.length === 0) {
    return null;
  }
  return sortInvoicesByDueDate(unpaidInvoices)[0];
}

/**
 * Search invoices by invoice number
 */
export function searchInvoicesByNumber(
  invoices: Invoice[],
  query: string,
): Invoice[] {
  const lowerQuery = query.toLowerCase();
  return invoices.filter(invoice =>
    invoice.number.toLowerCase().includes(lowerQuery),
  );
}

/**
 * Format invoice number for display (add prefix if needed)
 */
export function formatInvoiceNumber(number: string): string {
  // If number already has a prefix, return as-is
  if (number.includes('-') || number.includes('#')) {
    return number;
  }
  return `#${number}`;
}

/**
 * Check if there are any urgent invoices (overdue or due soon)
 */
export function hasUrgentInvoices(invoices: Invoice[]): boolean {
  return invoices.some(invoice => {
    if (invoice.status === 'paid') {
      return false;
    }
    if (invoice.status === 'overdue') {
      return true;
    }
    // Consider "urgent" if due within 3 days
    const daysUntilDue = getDaysUntilDue(invoice.dueDate);
    return daysUntilDue <= 3;
  });
}

/**
 * Get count of urgent invoices
 */
export function getUrgentInvoiceCount(invoices: Invoice[]): number {
  return invoices.filter(invoice => {
    if (invoice.status === 'paid') {
      return false;
    }
    if (invoice.status === 'overdue') {
      return true;
    }
    const daysUntilDue = getDaysUntilDue(invoice.dueDate);
    return daysUntilDue <= 3;
  }).length;
}
