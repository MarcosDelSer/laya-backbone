'use client';

import dynamic from 'next/dynamic';
import { ComponentType } from 'react';

/**
 * Loading component displayed while dynamic import is loading
 */
export function LoadingSpinner({ className = '' }: { className?: string }) {
  return (
    <div className={`flex items-center justify-center p-8 ${className}`}>
      <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
    </div>
  );
}

/**
 * Loading component for inline/small components
 */
export function LoadingPlaceholder({ className = '' }: { className?: string }) {
  return (
    <div className={`animate-pulse bg-gray-200 rounded ${className}`}>
      <div className="h-full w-full"></div>
    </div>
  );
}

/**
 * Error component displayed when dynamic import fails
 */
export function LoadingError({
  error,
  retry
}: {
  error?: Error;
  retry?: () => void;
}) {
  return (
    <div className="flex flex-col items-center justify-center p-8 text-center">
      <div className="text-red-600 mb-4">
        <svg
          className="h-12 w-12 mx-auto"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
          />
        </svg>
      </div>
      <p className="text-gray-700 mb-2 font-medium">Failed to load component</p>
      {error && (
        <p className="text-sm text-gray-500 mb-4">{error.message}</p>
      )}
      {retry && (
        <button
          onClick={retry}
          className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700"
        >
          Try Again
        </button>
      )}
    </div>
  );
}

/**
 * Options for dynamic import configuration
 */
export interface DynamicImportOptions {
  /**
   * Custom loading component
   */
  loading?: ComponentType<any>;

  /**
   * Whether to enable server-side rendering
   * @default true
   */
  ssr?: boolean;

  /**
   * Suspense mode (for React Suspense)
   * @default false
   */
  suspense?: boolean;
}

/**
 * Create a dynamically imported component with loading state
 *
 * @example
 * ```tsx
 * // Heavy component that should be code-split
 * const DocumentSignature = createDynamicImport(
 *   () => import('@/components/DocumentSignature'),
 *   { ssr: false } // Don't render on server (uses canvas)
 * );
 *
 * // Use in your component
 * <DocumentSignature documentToSign={doc} onClose={handleClose} />
 * ```
 */
export function createDynamicImport<P = any>(
  importFn: () => Promise<{ default: ComponentType<P> }>,
  options: DynamicImportOptions = {}
) {
  const {
    loading = LoadingSpinner,
    ssr = true,
    suspense = false,
  } = options;

  return dynamic(importFn, {
    loading,
    ssr,
    suspense,
  });
}

/**
 * Create a dynamically imported component without loading state
 * Useful for components that should render immediately once loaded
 *
 * @example
 * ```tsx
 * const PhotoGallery = createDynamicImportNoLoader(
 *   () => import('@/components/PhotoGallery')
 * );
 * ```
 */
export function createDynamicImportNoLoader<P = any>(
  importFn: () => Promise<{ default: ComponentType<P> }>,
  ssr = true
) {
  return dynamic(importFn, {
    ssr,
  });
}

/**
 * Pre-defined dynamic imports for common heavy components
 * These are automatically code-split and lazy-loaded
 */

// Document signing components (heavy - uses canvas)
export const DynamicDocumentSignature = createDynamicImport(
  () => import('@/components/DocumentSignature').then(mod => ({ default: mod.DocumentSignature })),
  { ssr: false, loading: LoadingSpinner }
);

export const DynamicSignatureCanvas = createDynamicImport(
  () => import('@/components/SignatureCanvas').then(mod => ({ default: mod.SignatureCanvas })),
  { ssr: false, loading: LoadingSpinner }
);

// Photo/media components (heavy - image processing)
export const DynamicPhotoGallery = createDynamicImport(
  () => import('@/components/PhotoGallery').then(mod => ({ default: mod.PhotoGallery })),
  { ssr: true, loading: LoadingPlaceholder }
);

// Document/invoice cards (heavy - complex rendering)
export const DynamicDocumentCard = createDynamicImport(
  () => import('@/components/DocumentCard').then(mod => ({ default: mod.DocumentCard })),
  { ssr: true, loading: LoadingPlaceholder }
);

export const DynamicInvoiceCard = createDynamicImport(
  () => import('@/components/InvoiceCard').then(mod => ({ default: mod.InvoiceCard })),
  { ssr: true, loading: LoadingPlaceholder }
);

// Messaging components (heavy - real-time features)
export const DynamicMessageThread = createDynamicImport(
  () => import('@/components/MessageThread').then(mod => ({ default: mod.MessageThread })),
  { ssr: true, loading: LoadingSpinner }
);

export const DynamicMessageComposer = createDynamicImport(
  () => import('@/components/MessageComposer').then(mod => ({ default: mod.MessageComposer })),
  { ssr: false, loading: LoadingPlaceholder }
);
