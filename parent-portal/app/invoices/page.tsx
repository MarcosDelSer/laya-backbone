import Link from 'next/link';
import { InvoiceCard } from '@/components/InvoiceCard';
import { PaymentStatusBadge } from '@/components/PaymentStatusBadge';

// Type definition for invoice data
interface Invoice {
  id: string;
  number: string;
  date: string;
  dueDate: string;
  amount: number;
  status: 'paid' | 'pending' | 'overdue';
  pdfUrl: string;
  items: {
    description: string;
    quantity: number;
    unitPrice: number;
    total: number;
  }[];
}

// Mock data for invoices - will be replaced with API calls
const mockInvoices: Invoice[] = [
  {
    id: 'inv-001',
    number: 'INV-2026-001',
    date: '2026-02-01',
    dueDate: '2026-02-15',
    amount: 1250.00,
    status: 'pending',
    pdfUrl: '/invoices/INV-2026-001.pdf',
    items: [
      {
        description: 'Monthly Tuition - February 2026',
        quantity: 1,
        unitPrice: 1100.00,
        total: 1100.00,
      },
      {
        description: 'Lunch Program',
        quantity: 1,
        unitPrice: 100.00,
        total: 100.00,
      },
      {
        description: 'Activity Fee',
        quantity: 1,
        unitPrice: 50.00,
        total: 50.00,
      },
    ],
  },
  {
    id: 'inv-002',
    number: 'INV-2026-002',
    date: '2026-01-01',
    dueDate: '2026-01-15',
    amount: 1250.00,
    status: 'paid',
    pdfUrl: '/invoices/INV-2026-002.pdf',
    items: [
      {
        description: 'Monthly Tuition - January 2026',
        quantity: 1,
        unitPrice: 1100.00,
        total: 1100.00,
      },
      {
        description: 'Lunch Program',
        quantity: 1,
        unitPrice: 100.00,
        total: 100.00,
      },
      {
        description: 'Activity Fee',
        quantity: 1,
        unitPrice: 50.00,
        total: 50.00,
      },
    ],
  },
  {
    id: 'inv-003',
    number: 'INV-2025-012',
    date: '2025-12-01',
    dueDate: '2025-12-15',
    amount: 1350.00,
    status: 'paid',
    pdfUrl: '/invoices/INV-2025-012.pdf',
    items: [
      {
        description: 'Monthly Tuition - December 2025',
        quantity: 1,
        unitPrice: 1100.00,
        total: 1100.00,
      },
      {
        description: 'Lunch Program',
        quantity: 1,
        unitPrice: 100.00,
        total: 100.00,
      },
      {
        description: 'Activity Fee',
        quantity: 1,
        unitPrice: 50.00,
        total: 50.00,
      },
      {
        description: 'Holiday Party Contribution',
        quantity: 1,
        unitPrice: 100.00,
        total: 100.00,
      },
    ],
  },
  {
    id: 'inv-004',
    number: 'INV-2025-011',
    date: '2025-11-01',
    dueDate: '2025-11-15',
    amount: 1250.00,
    status: 'paid',
    pdfUrl: '/invoices/INV-2025-011.pdf',
    items: [
      {
        description: 'Monthly Tuition - November 2025',
        quantity: 1,
        unitPrice: 1100.00,
        total: 1100.00,
      },
      {
        description: 'Lunch Program',
        quantity: 1,
        unitPrice: 100.00,
        total: 100.00,
      },
      {
        description: 'Activity Fee',
        quantity: 1,
        unitPrice: 50.00,
        total: 50.00,
      },
    ],
  },
];

function formatCurrency(amount: number): string {
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
  }).format(amount);
}

export default function InvoicesPage() {
  // Calculate summary statistics
  const totalPaid = mockInvoices
    .filter((inv) => inv.status === 'paid')
    .reduce((sum, inv) => sum + inv.amount, 0);

  const totalPending = mockInvoices
    .filter((inv) => inv.status === 'pending')
    .reduce((sum, inv) => sum + inv.amount, 0);

  const totalOverdue = mockInvoices
    .filter((inv) => inv.status === 'overdue')
    .reduce((sum, inv) => sum + inv.amount, 0);

  const pendingCount = mockInvoices.filter((inv) => inv.status === 'pending').length;
  const overdueCount = mockInvoices.filter((inv) => inv.status === 'overdue').length;
  const paidCount = mockInvoices.filter((inv) => inv.status === 'paid').length;

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">Invoices</h1>
            <p className="mt-1 text-gray-600">
              View and manage your billing history
            </p>
          </div>
          <Link href="/" className="btn btn-outline">
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
            Back
          </Link>
        </div>
      </div>

      {/* Summary Cards */}
      <div className="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {/* Total Pending */}
        <div className="card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500">Pending</p>
              <p className="text-2xl font-bold text-yellow-600">
                {formatCurrency(totalPending)}
              </p>
              <p className="text-xs text-gray-500">
                {pendingCount} invoice{pendingCount !== 1 ? 's' : ''}
              </p>
            </div>
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
          </div>
        </div>

        {/* Total Overdue */}
        <div className="card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500">Overdue</p>
              <p className="text-2xl font-bold text-red-600">
                {formatCurrency(totalOverdue)}
              </p>
              <p className="text-xs text-gray-500">
                {overdueCount} invoice{overdueCount !== 1 ? 's' : ''}
              </p>
            </div>
            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-red-100">
              <svg
                className="h-5 w-5 text-red-600"
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
            </div>
          </div>
        </div>

        {/* Total Paid */}
        <div className="card p-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm text-gray-500">Paid (This Year)</p>
              <p className="text-2xl font-bold text-green-600">
                {formatCurrency(totalPaid)}
              </p>
              <p className="text-xs text-gray-500">
                {paidCount} invoice{paidCount !== 1 ? 's' : ''}
              </p>
            </div>
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
                  d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            </div>
          </div>
        </div>
      </div>

      {/* Payment History Section */}
      <div className="mb-6">
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <h2 className="text-lg font-semibold text-gray-900">Payment History</h2>
          <div className="flex items-center space-x-2">
            <button type="button" className="btn btn-outline btn-sm" disabled>
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
                  d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"
                />
              </svg>
              Filter
            </button>
            <button type="button" className="btn btn-outline btn-sm" disabled>
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
                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                />
              </svg>
              Export
            </button>
          </div>
        </div>
      </div>

      {/* Status Filter Pills */}
      <div className="mb-6 flex flex-wrap gap-2">
        <button
          type="button"
          className="badge badge-neutral cursor-pointer hover:bg-gray-200"
        >
          All ({mockInvoices.length})
        </button>
        {pendingCount > 0 && (
          <button
            type="button"
            className="inline-flex items-center cursor-pointer hover:opacity-80"
          >
            <PaymentStatusBadge status="pending" size="sm" />
            <span className="ml-1 text-xs text-gray-500">({pendingCount})</span>
          </button>
        )}
        {overdueCount > 0 && (
          <button
            type="button"
            className="inline-flex items-center cursor-pointer hover:opacity-80"
          >
            <PaymentStatusBadge status="overdue" size="sm" />
            <span className="ml-1 text-xs text-gray-500">({overdueCount})</span>
          </button>
        )}
        {paidCount > 0 && (
          <button
            type="button"
            className="inline-flex items-center cursor-pointer hover:opacity-80"
          >
            <PaymentStatusBadge status="paid" size="sm" />
            <span className="ml-1 text-xs text-gray-500">({paidCount})</span>
          </button>
        )}
      </div>

      {/* Invoice List */}
      {mockInvoices.length > 0 ? (
        <div className="space-y-6">
          {mockInvoices.map((invoice) => (
            <InvoiceCard key={invoice.id} invoice={invoice} />
          ))}
        </div>
      ) : (
        <div className="card p-12 text-center">
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
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
          <h3 className="text-lg font-medium text-gray-900">No invoices yet</h3>
          <p className="mt-2 text-gray-500">
            Your invoices will appear here once they are generated.
          </p>
        </div>
      )}

      {/* Load More - placeholder for pagination */}
      {mockInvoices.length > 0 && (
        <div className="mt-8 text-center">
          <button type="button" className="btn btn-outline" disabled>
            Load More Invoices
          </button>
        </div>
      )}
    </div>
  );
}
