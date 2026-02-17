/**
 * Code Splitting Examples for Parent Portal
 *
 * This file demonstrates how to use dynamic imports for code splitting
 * in the LAYA Parent Portal application.
 */

'use client';

import { createDynamicImport, LoadingSpinner, LoadingPlaceholder } from './dynamicImport';

/**
 * EXAMPLE 1: Basic Dynamic Import
 * ------------------------------
 * Import a heavy component that users may not always need
 */

// Before (no code splitting):
// import { DocumentSignature } from '@/components/DocumentSignature';

// After (with code splitting):
const DocumentSignature = createDynamicImport(
  () => import('@/components/DocumentSignature').then(mod => ({ default: mod.DocumentSignature })),
  {
    ssr: false, // Don't render on server (uses canvas API)
    loading: LoadingSpinner,
  }
);

// Usage in component:
export function DocumentsPageExample() {
  return (
    <div>
      {/* Component is only loaded when needed */}
      <DocumentSignature
        documentToSign={null}
        isOpen={false}
        onClose={() => {}}
        onSubmit={() => {}}
      />
    </div>
  );
}

/**
 * EXAMPLE 2: Conditional Loading
 * ------------------------------
 * Only load component when user action triggers it
 */

// Import heavy component
const PhotoGallery = createDynamicImport(
  () => import('@/components/PhotoGallery').then(mod => ({ default: mod.PhotoGallery })),
  { loading: LoadingPlaceholder }
);

export function ConditionalLoadingExample() {
  const [showGallery, setShowGallery] = useState(false);

  return (
    <div>
      <button onClick={() => setShowGallery(true)}>
        View Photos
      </button>

      {/* Gallery code is only downloaded when user clicks button */}
      {showGallery && (
        <PhotoGallery photos={[]} />
      )}
    </div>
  );
}

/**
 * EXAMPLE 3: Route-Based Code Splitting
 * --------------------------------------
 * Next.js automatically splits code by route, but you can optimize further
 */

// In app/documents/page.tsx:
const DocumentCard = createDynamicImport(
  () => import('@/components/DocumentCard').then(mod => ({ default: mod.DocumentCard })),
  { loading: LoadingPlaceholder }
);

export function DocumentsRouteExample() {
  const documents = []; // from API

  return (
    <div>
      {documents.map((doc: any) => (
        <DocumentCard key={doc.id} document={doc} />
      ))}
    </div>
  );
}

/**
 * EXAMPLE 4: Multiple Component Splitting
 * ----------------------------------------
 * Split multiple heavy components on the same page
 */

const InvoiceCard = createDynamicImport(
  () => import('@/components/InvoiceCard').then(mod => ({ default: mod.InvoiceCard })),
  { loading: LoadingPlaceholder }
);

const DailyReportCard = createDynamicImport(
  () => import('@/components/DailyReportCard').then(mod => ({ default: mod.DailyReportCard })),
  { loading: LoadingPlaceholder }
);

export function DashboardExample() {
  return (
    <div>
      {/* Each component loads independently */}
      <section>
        <h2>Recent Invoices</h2>
        <InvoiceCard invoice={null} />
      </section>

      <section>
        <h2>Daily Reports</h2>
        <DailyReportCard report={null} />
      </section>
    </div>
  );
}

/**
 * EXAMPLE 5: Custom Loading State
 * --------------------------------
 * Provide custom loading component for better UX
 */

function CustomInvoiceLoader() {
  return (
    <div className="border rounded-lg p-6 animate-pulse">
      <div className="h-4 bg-gray-200 rounded w-1/4 mb-4"></div>
      <div className="h-6 bg-gray-200 rounded w-1/2 mb-2"></div>
      <div className="h-4 bg-gray-200 rounded w-3/4"></div>
    </div>
  );
}

const InvoiceWithCustomLoader = createDynamicImport(
  () => import('@/components/InvoiceCard').then(mod => ({ default: mod.InvoiceCard })),
  { loading: CustomInvoiceLoader }
);

/**
 * EXAMPLE 6: Prefetching
 * ----------------------
 * Prefetch components before user needs them
 */

export function PrefetchExample() {
  // Prefetch on hover (common pattern)
  const prefetchDocuments = () => {
    import('@/components/DocumentCard');
  };

  return (
    <button onMouseEnter={prefetchDocuments}>
      View Documents
    </button>
  );
}

/**
 * WHEN TO USE CODE SPLITTING
 * --------------------------
 *
 * ✅ DO use for:
 * - Large third-party libraries (charts, editors, etc.)
 * - Components only shown conditionally (modals, drawers)
 * - Heavy components with canvas, video, or media processing
 * - Route-specific functionality
 * - Components below the fold
 *
 * ❌ DON'T use for:
 * - Small components (<5KB)
 * - Components always visible on page load
 * - Critical above-the-fold content
 * - Simple presentational components
 */

/**
 * PERFORMANCE TIPS
 * ----------------
 *
 * 1. Analyze bundle size first: npm run analyze
 * 2. Identify large chunks in the bundle analyzer
 * 3. Apply dynamic imports to heavy components
 * 4. Test loading states work correctly
 * 5. Monitor with Lighthouse and Web Vitals
 * 6. Consider prefetching for important routes
 */

// This is just an example file - import useState for demo
import { useState } from 'react';
