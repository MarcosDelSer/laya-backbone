/**
 * LAYA Parent App - Invoice API
 *
 * API functions for fetching invoice data including invoice list,
 * details, and PDF downloads for the parent's billing history.
 * Follows patterns from ParentPortal invoice endpoints.
 */

import {api} from './client';
import {API_CONFIG, buildApiUrl} from './config';
import type {
  ApiResponse,
  Invoice,
  InvoiceStatus,
  PaginatedResponse,
} from '../types';
import {Linking, Platform} from 'react-native';

/**
 * Response type for invoice list endpoint
 */
interface InvoiceListResponse {
  invoices: Invoice[];
  summary: InvoiceSummary;
}

/**
 * Summary statistics for invoices
 */
export interface InvoiceSummary {
  totalPaid: number;
  totalPending: number;
  totalOverdue: number;
  paidCount: number;
  pendingCount: number;
  overdueCount: number;
}

/**
 * Parameters for fetching invoice data
 */
interface FetchInvoiceParams {
  status?: InvoiceStatus;
  page?: number;
  pageSize?: number;
}

/**
 * Fetch the list of invoices for the current parent
 */
export async function fetchInvoices(
  params?: FetchInvoiceParams,
): Promise<ApiResponse<InvoiceListResponse>> {
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

  return api.get<InvoiceListResponse>(
    API_CONFIG.endpoints.invoices.list,
    queryParams,
  );
}

/**
 * Fetch details for a specific invoice
 */
export async function fetchInvoiceDetails(
  invoiceId: string,
): Promise<ApiResponse<Invoice>> {
  const endpoint = API_CONFIG.endpoints.invoices.details.replace(':id', invoiceId);
  return api.get<Invoice>(endpoint);
}

/**
 * Download an invoice PDF
 * Opens the PDF URL in the device's default PDF viewer
 */
export async function downloadInvoicePdf(invoiceId: string): Promise<void> {
  const endpoint = API_CONFIG.endpoints.invoices.downloadPdf.replace(':id', invoiceId);
  const url = buildApiUrl(endpoint);

  try {
    const canOpen = await Linking.canOpenURL(url);
    if (canOpen) {
      await Linking.openURL(url);
    }
  } catch {
    // Error will be handled by the calling component
    throw new Error('Unable to open PDF');
  }
}

/**
 * Format a currency amount for display
 */
export function formatCurrency(amount: number, currency: string = 'USD'): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: currency,
  }).format(amount);
}

/**
 * Format a date string for display
 */
export function formatInvoiceDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

/**
 * Calculate days until due date (negative if overdue)
 */
export function getDaysUntilDue(dueDate: string): number {
  const due = new Date(dueDate);
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  due.setHours(0, 0, 0, 0);
  const diffTime = due.getTime() - today.getTime();
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

/**
 * Get display label for invoice status
 */
export function getStatusLabel(status: InvoiceStatus): string {
  const labels: Record<InvoiceStatus, string> = {
    draft: 'Draft',
    sent: 'Sent',
    paid: 'Paid',
    overdue: 'Overdue',
    cancelled: 'Cancelled',
  };
  return labels[status] || status;
}

/**
 * Calculate summary statistics from invoices
 */
export function calculateSummary(invoices: Invoice[]): InvoiceSummary {
  const paidInvoices = invoices.filter(inv => inv.status === 'paid');
  const pendingInvoices = invoices.filter(inv => inv.status === 'sent' || inv.status === 'draft');
  const overdueInvoices = invoices.filter(inv => inv.status === 'overdue');

  return {
    totalPaid: paidInvoices.reduce((sum, inv) => sum + inv.amount, 0),
    totalPending: pendingInvoices.reduce((sum, inv) => sum + inv.amount, 0),
    totalOverdue: overdueInvoices.reduce((sum, inv) => sum + inv.amount, 0),
    paidCount: paidInvoices.length,
    pendingCount: pendingInvoices.length,
    overdueCount: overdueInvoices.length,
  };
}

/**
 * Generate mock invoice data for development
 */
export function getMockInvoiceData(): InvoiceListResponse {
  const mockInvoices: Invoice[] = [
    {
      id: 'inv-001',
      invoiceNumber: 'INV-2026-001',
      issueDate: '2026-02-01',
      dueDate: '2026-02-15',
      status: 'sent',
      amount: 1250.00,
      currency: 'USD',
      pdfUrl: '/invoices/INV-2026-001.pdf',
      items: [
        {
          id: 'item-1',
          description: 'Monthly Tuition - February 2026',
          quantity: 1,
          unitPrice: 1100.00,
          total: 1100.00,
        },
        {
          id: 'item-2',
          description: 'Lunch Program',
          quantity: 1,
          unitPrice: 100.00,
          total: 100.00,
        },
        {
          id: 'item-3',
          description: 'Activity Fee',
          quantity: 1,
          unitPrice: 50.00,
          total: 50.00,
        },
      ],
    },
    {
      id: 'inv-002',
      invoiceNumber: 'INV-2026-002',
      issueDate: '2026-01-01',
      dueDate: '2026-01-15',
      status: 'paid',
      amount: 1250.00,
      currency: 'USD',
      pdfUrl: '/invoices/INV-2026-002.pdf',
      items: [
        {
          id: 'item-4',
          description: 'Monthly Tuition - January 2026',
          quantity: 1,
          unitPrice: 1100.00,
          total: 1100.00,
        },
        {
          id: 'item-5',
          description: 'Lunch Program',
          quantity: 1,
          unitPrice: 100.00,
          total: 100.00,
        },
        {
          id: 'item-6',
          description: 'Activity Fee',
          quantity: 1,
          unitPrice: 50.00,
          total: 50.00,
        },
      ],
    },
    {
      id: 'inv-003',
      invoiceNumber: 'INV-2025-012',
      issueDate: '2025-12-01',
      dueDate: '2025-12-15',
      status: 'paid',
      amount: 1350.00,
      currency: 'USD',
      pdfUrl: '/invoices/INV-2025-012.pdf',
      items: [
        {
          id: 'item-7',
          description: 'Monthly Tuition - December 2025',
          quantity: 1,
          unitPrice: 1100.00,
          total: 1100.00,
        },
        {
          id: 'item-8',
          description: 'Lunch Program',
          quantity: 1,
          unitPrice: 100.00,
          total: 100.00,
        },
        {
          id: 'item-9',
          description: 'Activity Fee',
          quantity: 1,
          unitPrice: 50.00,
          total: 50.00,
        },
        {
          id: 'item-10',
          description: 'Holiday Party Contribution',
          quantity: 1,
          unitPrice: 100.00,
          total: 100.00,
        },
      ],
    },
    {
      id: 'inv-004',
      invoiceNumber: 'INV-2025-011',
      issueDate: '2025-11-01',
      dueDate: '2025-11-15',
      status: 'paid',
      amount: 1250.00,
      currency: 'USD',
      pdfUrl: '/invoices/INV-2025-011.pdf',
      items: [
        {
          id: 'item-11',
          description: 'Monthly Tuition - November 2025',
          quantity: 1,
          unitPrice: 1100.00,
          total: 1100.00,
        },
        {
          id: 'item-12',
          description: 'Lunch Program',
          quantity: 1,
          unitPrice: 100.00,
          total: 100.00,
        },
        {
          id: 'item-13',
          description: 'Activity Fee',
          quantity: 1,
          unitPrice: 50.00,
          total: 50.00,
        },
      ],
    },
  ];

  return {
    invoices: mockInvoices,
    summary: calculateSummary(mockInvoices),
  };
}
