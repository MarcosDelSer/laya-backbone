'use client';

import { useState, useCallback } from 'react';
import type { EnrollmentForm, EnrollmentFormSummary, EnrollmentFormStatus } from '@/lib/types';

// ============================================================================
// Types
// ============================================================================

/**
 * PDF preview data containing URL and metadata.
 */
export interface PdfPreviewData {
  url: string;
  filename?: string;
  generatedAt?: string;
  expiresAt?: string;
}

/**
 * Props for the EnrollmentPdfPreview component.
 */
export interface EnrollmentPdfPreviewProps {
  /** The enrollment form data (full or summary) */
  form: EnrollmentForm | EnrollmentFormSummary;
  /** Direct PDF URL if already available */
  pdfUrl?: string;
  /** Callback to generate/fetch PDF URL on demand */
  onGeneratePdf?: (formId: string) => Promise<PdfPreviewData>;
  /** Callback when PDF is downloaded */
  onDownload?: (formId: string, filename: string) => void;
  /** Whether the component is in a disabled state */
  disabled?: boolean;
  /** Display variant */
  variant?: 'card' | 'inline' | 'compact';
  /** Whether to show the form info section */
  showFormInfo?: boolean;
}

// ============================================================================
// Helper Functions
// ============================================================================

function formatDate(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatDateTime(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

function getDefaultFilename(form: EnrollmentForm | EnrollmentFormSummary): string {
  const childName = `${form.childFirstName}_${form.childLastName}`.replace(/\s+/g, '_');
  const formNumber = form.formNumber.replace(/[^a-zA-Z0-9]/g, '-');
  return `enrollment_${childName}_${formNumber}.pdf`;
}

function getStatusColor(status: EnrollmentFormStatus): string {
  switch (status) {
    case 'Draft':
      return 'text-gray-600';
    case 'Submitted':
      return 'text-blue-600';
    case 'Approved':
      return 'text-green-600';
    case 'Rejected':
      return 'text-red-600';
    case 'Expired':
      return 'text-amber-600';
    default:
      return 'text-gray-600';
  }
}

// ============================================================================
// Icons
// ============================================================================

function PdfIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className || 'h-6 w-6'}
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
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M9 12h6M9 15h4"
      />
    </svg>
  );
}

function DownloadIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className || 'h-4 w-4'}
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
  );
}

function PreviewIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className || 'h-4 w-4'}
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
  );
}

function SpinnerIcon({ className }: { className?: string }) {
  return (
    <svg
      className={`animate-spin ${className || 'h-4 w-4'}`}
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
  );
}

function AlertIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className || 'h-5 w-5'}
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
  );
}

function RefreshIcon({ className }: { className?: string }) {
  return (
    <svg
      className={className || 'h-4 w-4'}
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
    >
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
      />
    </svg>
  );
}

// ============================================================================
// Component
// ============================================================================

export function EnrollmentPdfPreview({
  form,
  pdfUrl: initialPdfUrl,
  onGeneratePdf,
  onDownload,
  disabled = false,
  variant = 'card',
  showFormInfo = true,
}: EnrollmentPdfPreviewProps) {
  const [pdfUrl, setPdfUrl] = useState<string | undefined>(initialPdfUrl);
  const [pdfData, setPdfData] = useState<PdfPreviewData | null>(
    initialPdfUrl ? { url: initialPdfUrl } : null
  );
  const [isLoading, setIsLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const childFullName = `${form.childFirstName} ${form.childLastName}`;
  const filename = pdfData?.filename || getDefaultFilename(form);

  /**
   * Generate or fetch the PDF URL.
   */
  const handleGeneratePdf = useCallback(async () => {
    if (!onGeneratePdf) {
      setError('PDF generation is not available');
      return;
    }

    setIsLoading(true);
    setError(null);

    try {
      const data = await onGeneratePdf(form.id);
      setPdfData(data);
      setPdfUrl(data.url);
    } catch (err) {
      const message = err instanceof Error ? err.message : 'Failed to generate PDF';
      setError(message);
    } finally {
      setIsLoading(false);
    }
  }, [form.id, onGeneratePdf]);

  /**
   * Open PDF in a new tab for preview.
   */
  const handlePreview = useCallback(async () => {
    if (disabled) return;

    let url = pdfUrl;

    // Generate PDF if not available
    if (!url && onGeneratePdf) {
      setIsLoading(true);
      setError(null);

      try {
        const data = await onGeneratePdf(form.id);
        setPdfData(data);
        setPdfUrl(data.url);
        url = data.url;
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Failed to generate PDF';
        setError(message);
        setIsLoading(false);
        return;
      } finally {
        setIsLoading(false);
      }
    }

    if (url) {
      window.open(url, '_blank', 'noopener,noreferrer');
    }
  }, [disabled, form.id, onGeneratePdf, pdfUrl]);

  /**
   * Download the PDF file.
   */
  const handleDownload = useCallback(async () => {
    if (disabled) return;

    let url = pdfUrl;
    let downloadFilename = filename;

    // Generate PDF if not available
    if (!url && onGeneratePdf) {
      setIsLoading(true);
      setError(null);

      try {
        const data = await onGeneratePdf(form.id);
        setPdfData(data);
        setPdfUrl(data.url);
        url = data.url;
        downloadFilename = data.filename || filename;
      } catch (err) {
        const message = err instanceof Error ? err.message : 'Failed to generate PDF';
        setError(message);
        setIsLoading(false);
        return;
      } finally {
        setIsLoading(false);
      }
    }

    if (url) {
      // Create a temporary anchor element to trigger download
      const link = document.createElement('a');
      link.href = url;
      link.download = downloadFilename;
      link.target = '_blank';
      link.rel = 'noopener noreferrer';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      // Notify parent component
      if (onDownload) {
        onDownload(form.id, downloadFilename);
      }
    }
  }, [disabled, filename, form.id, onDownload, onGeneratePdf, pdfUrl]);

  /**
   * Retry generating PDF after an error.
   */
  const handleRetry = useCallback(() => {
    setError(null);
    handleGeneratePdf();
  }, [handleGeneratePdf]);

  // Render error state
  if (error) {
    const errorContent = (
      <div className="flex items-start space-x-3 text-red-600">
        <AlertIcon className="h-5 w-5 flex-shrink-0 mt-0.5" />
        <div className="flex-1">
          <p className="text-sm font-medium">PDF Error</p>
          <p className="text-xs text-red-500 mt-1">{error}</p>
          {onGeneratePdf && (
            <button
              type="button"
              onClick={handleRetry}
              className="mt-2 inline-flex items-center text-xs font-medium text-red-600 hover:text-red-800"
              disabled={disabled}
            >
              <RefreshIcon className="mr-1 h-3 w-3" />
              Try Again
            </button>
          )}
        </div>
      </div>
    );

    if (variant === 'compact') {
      return <div className="p-2">{errorContent}</div>;
    }

    return (
      <div className={variant === 'card' ? 'card' : ''}>
        <div className={variant === 'card' ? 'card-body' : 'p-4'}>
          {errorContent}
        </div>
      </div>
    );
  }

  // Compact variant - just buttons
  if (variant === 'compact') {
    return (
      <div className="flex items-center gap-2">
        <button
          type="button"
          onClick={handlePreview}
          disabled={disabled || isLoading}
          className="btn btn-outline btn-sm"
          title="Preview PDF"
        >
          {isLoading ? (
            <SpinnerIcon className="h-4 w-4" />
          ) : (
            <PreviewIcon className="h-4 w-4" />
          )}
        </button>
        <button
          type="button"
          onClick={handleDownload}
          disabled={disabled || isLoading}
          className="btn btn-outline btn-sm"
          title="Download PDF"
        >
          {isLoading ? (
            <SpinnerIcon className="h-4 w-4" />
          ) : (
            <DownloadIcon className="h-4 w-4" />
          )}
        </button>
      </div>
    );
  }

  // Inline variant - horizontal layout
  if (variant === 'inline') {
    return (
      <div className="flex items-center justify-between gap-4 p-4 bg-gray-50 rounded-lg">
        <div className="flex items-center space-x-3">
          <div className="flex-shrink-0">
            <PdfIcon className="h-8 w-8 text-red-500" />
          </div>
          <div>
            <p className="text-sm font-medium text-gray-900">
              Enrollment Form PDF
            </p>
            {showFormInfo && (
              <p className="text-xs text-gray-500">
                {childFullName} • {form.formNumber}
              </p>
            )}
            {pdfData?.generatedAt && (
              <p className="text-xs text-gray-400">
                Generated: {formatDateTime(pdfData.generatedAt)}
              </p>
            )}
          </div>
        </div>

        <div className="flex items-center gap-2">
          <button
            type="button"
            onClick={handlePreview}
            disabled={disabled || isLoading}
            className="btn btn-outline text-sm"
          >
            {isLoading ? (
              <>
                <SpinnerIcon className="mr-2 h-4 w-4" />
                Loading...
              </>
            ) : (
              <>
                <PreviewIcon className="mr-2 h-4 w-4" />
                Preview
              </>
            )}
          </button>
          <button
            type="button"
            onClick={handleDownload}
            disabled={disabled || isLoading}
            className="btn btn-primary text-sm"
          >
            {isLoading ? (
              <>
                <SpinnerIcon className="mr-2 h-4 w-4" />
                Loading...
              </>
            ) : (
              <>
                <DownloadIcon className="mr-2 h-4 w-4" />
                Download
              </>
            )}
          </button>
        </div>
      </div>
    );
  }

  // Card variant (default) - full card layout
  return (
    <div className="card">
      <div className="card-body">
        <div className="flex items-start justify-between">
          {/* PDF info */}
          <div className="flex items-start space-x-4">
            {/* PDF icon */}
            <div className="flex-shrink-0">
              <div className="flex h-12 w-12 items-center justify-center rounded-full bg-red-50">
                <PdfIcon className="h-6 w-6 text-red-600" />
              </div>
            </div>

            {/* Document details */}
            <div className="flex-1 min-w-0">
              <h3 className="text-base font-semibold text-gray-900 truncate">
                Enrollment Form PDF
              </h3>
              {showFormInfo && (
                <>
                  <p className="mt-1 text-sm text-gray-500">
                    {childFullName}
                  </p>
                  <p className="mt-1 text-xs text-gray-400">
                    Form #{form.formNumber}
                    {form.version > 1 && (
                      <span className="ml-2">(v{form.version})</span>
                    )}
                  </p>
                </>
              )}

              {/* PDF generation info */}
              {pdfData?.generatedAt && (
                <div className="mt-2 flex items-center text-xs text-gray-400">
                  <svg
                    className="mr-1 h-3 w-3"
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
                  Generated: {formatDateTime(pdfData.generatedAt)}
                </div>
              )}

              {/* Expiry warning */}
              {pdfData?.expiresAt && (
                <div className="mt-1 flex items-center text-xs text-amber-600">
                  <AlertIcon className="mr-1 h-3 w-3" />
                  Expires: {formatDateTime(pdfData.expiresAt)}
                </div>
              )}
            </div>
          </div>

          {/* Status badge */}
          <div className="flex-shrink-0">
            <span className={`badge badge-default ${getStatusColor(form.status)}`}>
              {form.status}
            </span>
          </div>
        </div>

        {/* Actions */}
        <div className="mt-4 flex flex-wrap gap-2 border-t border-gray-100 pt-4">
          {/* Preview button */}
          <button
            type="button"
            onClick={handlePreview}
            disabled={disabled || isLoading}
            className="btn btn-outline text-sm"
          >
            {isLoading ? (
              <>
                <SpinnerIcon className="mr-2 h-4 w-4" />
                Generating...
              </>
            ) : (
              <>
                <PreviewIcon className="mr-2 h-4 w-4" />
                Preview PDF
              </>
            )}
          </button>

          {/* Download button */}
          <button
            type="button"
            onClick={handleDownload}
            disabled={disabled || isLoading}
            className="btn btn-primary text-sm"
          >
            {isLoading ? (
              <>
                <SpinnerIcon className="mr-2 h-4 w-4" />
                Generating...
              </>
            ) : (
              <>
                <DownloadIcon className="mr-2 h-4 w-4" />
                Download PDF
              </>
            )}
          </button>

          {/* Regenerate button (if PDF already exists and generator is available) */}
          {pdfUrl && onGeneratePdf && (
            <button
              type="button"
              onClick={handleGeneratePdf}
              disabled={disabled || isLoading}
              className="btn btn-outline text-sm text-gray-500 border-gray-300 hover:bg-gray-50"
              title="Generate a new PDF"
            >
              <RefreshIcon className="mr-2 h-4 w-4" />
              Regenerate
            </button>
          )}
        </div>

        {/* Info notice */}
        {!pdfUrl && !isLoading && (
          <div className="mt-3 rounded-md bg-blue-50 p-3">
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
              <p className="ml-3 text-sm text-blue-700">
                Click Preview or Download to generate the enrollment form PDF.
                The PDF will include all form sections and signatures.
              </p>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

// ============================================================================
// Exports
// ============================================================================

export type { PdfPreviewData, EnrollmentPdfPreviewProps };
