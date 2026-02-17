'use client';

import { useTranslations } from 'next-intl';
import { PaymentStatusBadge } from './PaymentStatusBadge';
import { useFormatting } from '@/lib/hooks/useFormatting';

interface InvoiceItem {
  description: string;
  quantity: number;
  unitPrice: number;
  total: number;
}

export interface InvoiceCardProps {
  invoice: {
    id: string;
    number: string;
    date: string;
    dueDate: string;
    amount: number;
    status: 'paid' | 'pending' | 'overdue';
    pdfUrl: string;
    items: InvoiceItem[];
  };
}

export function InvoiceCard({ invoice }: InvoiceCardProps) {
  const t = useTranslations();
  const { formatCurrency, formatDate, getDaysUntilDue } = useFormatting();

  const daysUntilDue = getDaysUntilDue(invoice.dueDate);
  const isPastDue = daysUntilDue < 0 && invoice.status !== 'paid';

  const handleDownload = () => {
    // In production, this would trigger a PDF download
    // For now, we just indicate the action
    if (invoice.pdfUrl) {
      window.open(invoice.pdfUrl, '_blank');
    }
  };

  /**
   * Get the due date status message using translations with plural support.
   */
  const getDueDateStatus = () => {
    if (isPastDue) {
      return t('common.time.daysOverdue', { count: Math.abs(daysUntilDue) });
    } else if (daysUntilDue === 0) {
      return t('common.time.dueToday');
    } else {
      return t('common.time.daysRemaining', { count: daysUntilDue });
    }
  };

  return (
    <article className="card" aria-labelledby={`invoice-${invoice.id}-title`}>
      {/* Invoice Header */}
      <div className="card-header">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100" aria-hidden="true">
              <svg
                className="h-6 w-6 text-blue-600"
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
              <h3 id={`invoice-${invoice.id}-title`} className="text-lg font-semibold text-gray-900">
                Invoice #{invoice.number}
              </h3>
              <p className="text-sm text-gray-600">
                {t('invoices.issued', { date: formatDate(invoice.date) })}
              </p>
            </div>
          </div>
          <div className="flex items-center space-x-3">
            <PaymentStatusBadge status={invoice.status} />
          </div>
        </div>
      </div>

      <div className="card-body">
        {/* Amount and Due Date */}
        <div className="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
          <div>
            <p className="text-sm text-gray-500">{t('invoices.totalAmount')}</p>
            <p className="text-2xl font-bold text-gray-900">
              {formatCurrency(invoice.amount)}
            </p>
          </div>
          <div className="text-left sm:text-right">
            <p className="text-sm text-gray-500">{t('invoices.dueDate')}</p>
            <p className={`font-medium ${isPastDue ? 'text-red-600' : 'text-gray-900'}`}>
              {formatDate(invoice.dueDate)}
            </p>
            {invoice.status !== 'paid' && (
              <p className={`text-xs ${isPastDue ? 'text-red-500' : 'text-gray-500'}`}>
                {getDueDateStatus()}
              </p>
            )}
          </div>
        </div>

        {/* Invoice Items */}
        {invoice.items.length > 0 && (
          <div className="mb-6">
            <h4 className="font-medium text-gray-900 mb-3">{t('invoices.invoiceDetails')}</h4>
            <div className="overflow-x-auto">
              <table className="min-w-full divide-y divide-gray-200" aria-label="Invoice items">
                <thead className="bg-gray-50">
                  <tr>
                    <th scope="col" className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Description
                    </th>
                    <th scope="col" className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Qty
                    </th>
                    <th scope="col" className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Unit Price
                    </th>
                    <th scope="col" className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                      Total
                    </th>
                  </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                  {invoice.items.map((item, index) => (
                    <tr key={index}>
                      <td className="px-3 py-2 text-sm text-gray-900">
                        {item.description}
                      </td>
                      <td className="px-3 py-2 text-sm text-gray-600 text-right">
                        {item.quantity}
                      </td>
                      <td className="px-3 py-2 text-sm text-gray-600 text-right">
                        {formatCurrency(item.unitPrice)}
                      </td>
                      <td className="px-3 py-2 text-sm font-medium text-gray-900 text-right">
                        {formatCurrency(item.total)}
                      </td>
                    </tr>
                  ))}
                </tbody>
                <tfoot className="bg-gray-50">
                  <tr>
                    <th scope="row" colSpan={3} className="px-3 py-2 text-sm font-medium text-gray-900 text-right">
                      Total
                    </th>
                    <td className="px-3 py-2 text-sm font-bold text-gray-900 text-right">
                      {formatCurrency(invoice.amount)}
                    </td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        )}

        {/* Actions */}
        <div className="flex flex-col sm:flex-row gap-3">
          <button
            type="button"
            onClick={handleDownload}
            aria-label={`Download PDF for invoice ${invoice.number}`}
            className="btn btn-outline flex-1 sm:flex-none"
          >
            <svg
              className="mr-2 h-4 w-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
              aria-hidden="true"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
              />
            </svg>
            {t('invoices.actions.downloadPdf')}
          </button>
          {invoice.status !== 'paid' && (
            <button
              type="button"
              className="btn btn-primary flex-1 sm:flex-none"
              disabled
              aria-label={`Pay invoice ${invoice.number} (Coming soon)`}
            >
              <svg
                className="mr-2 h-4 w-4"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"
                />
              </svg>
              {t('invoices.actions.payNow')}
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
