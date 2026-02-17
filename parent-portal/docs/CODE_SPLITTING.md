# Code Splitting Guide - LAYA Parent Portal

## Overview

Code splitting is a performance optimization technique that breaks your JavaScript bundle into smaller chunks that can be loaded on demand. This guide explains how code splitting is implemented in the LAYA Parent Portal.

## Table of Contents

- [What is Code Splitting?](#what-is-code-splitting)
- [Automatic Route-Based Splitting](#automatic-route-based-splitting)
- [Manual Component Splitting](#manual-component-splitting)
- [Implementation](#implementation)
- [Best Practices](#best-practices)
- [Performance Impact](#performance-impact)
- [Troubleshooting](#troubleshooting)

## What is Code Splitting?

Code splitting allows you to split your code into smaller bundles that can be loaded on demand or in parallel. This reduces the initial load time by only loading the code needed for the current page.

### Benefits

- **Faster Initial Load**: Smaller initial bundle size
- **Better Caching**: Unchanged chunks stay cached
- **Improved Performance**: Only load what you need
- **Better UX**: Faster time to interactive

### How It Works

```
Before Code Splitting:
┌─────────────────────────────────┐
│   Single Bundle (500KB)         │
│   - Framework                   │
│   - All Pages                   │
│   - All Components              │
│   - All Libraries               │
└─────────────────────────────────┘
        ↓ (Long initial load)
    User sees page


After Code Splitting:
┌──────────────┐  ┌─────────────┐  ┌─────────────┐
│ Framework    │  │ Page Chunk  │  │ Component   │
│ (150KB)      │  │ (40KB)      │  │ (20KB)      │
└──────────────┘  └─────────────┘  └─────────────┘
      ↓                ↓                  ↓
  Immediate        As needed         On demand
```

## Automatic Route-Based Splitting

Next.js automatically splits code by route. Each page in the `app/` directory becomes its own chunk.

### Current Routes and Chunks

```
app/
├── page.tsx              → home chunk (~40KB)
├── daily-reports/
│   └── page.tsx         → daily-reports chunk (~35KB)
├── documents/
│   └── page.tsx         → documents chunk (~38KB)
├── invoices/
│   └── page.tsx         → invoices chunk (~42KB)
└── messages/
    └── page.tsx         → messages chunk (~41KB)
```

### Webpack Optimization

The `next.config.js` includes webpack optimization for better chunk splitting:

```javascript
webpack: (config, { isServer }) => {
  if (!isServer) {
    config.optimization.splitChunks = {
      chunks: 'all',
      cacheGroups: {
        // Framework chunk (React, Next.js)
        framework: {
          name: 'framework',
          test: /[\\/]node_modules[\\/](react|react-dom|scheduler|next)[\\/]/,
          priority: 40,
        },
        // Library chunks (node_modules)
        lib: {
          test: /[\\/]node_modules[\\/]/,
          name: 'npm.[name]',
          priority: 30,
        },
        // Shared components
        commons: {
          name: 'commons',
          minChunks: 2,
          priority: 20,
        },
      },
    };
  }
  return config;
}
```

## Manual Component Splitting

For heavy components, use dynamic imports to split them into separate chunks.

### Using the Dynamic Import Utility

```tsx
import { createDynamicImport } from '@/lib/dynamicImport';

// Create dynamically imported component
const DocumentSignature = createDynamicImport(
  () => import('@/components/DocumentSignature').then(mod => ({
    default: mod.DocumentSignature
  })),
  {
    ssr: false,        // Don't render on server (uses canvas)
    loading: LoadingSpinner,  // Show while loading
  }
);

// Use like a normal component
function DocumentsPage() {
  return (
    <DocumentSignature
      documentToSign={document}
      onClose={handleClose}
    />
  );
}
```

### Pre-Configured Dynamic Imports

Heavy components are available as pre-configured imports:

```tsx
import {
  DynamicDocumentSignature,
  DynamicSignatureCanvas,
  DynamicPhotoGallery,
  DynamicDocumentCard,
  DynamicInvoiceCard,
  DynamicMessageThread,
  DynamicMessageComposer,
} from '@/lib/dynamicImport';

// Use directly - already optimized
function MyPage() {
  return <DynamicDocumentSignature {...props} />;
}
```

## Implementation

### Step 1: Identify Heavy Components

Run bundle analysis to identify large components:

```bash
npm run analyze
```

Look for:
- Components over 20KB
- Third-party libraries
- Components with heavy dependencies
- Conditionally rendered components

### Step 2: Apply Dynamic Imports

For components identified as heavy:

```tsx
// Before
import { HeavyComponent } from '@/components/HeavyComponent';

// After
const HeavyComponent = createDynamicImport(
  () => import('@/components/HeavyComponent').then(mod => ({
    default: mod.HeavyComponent
  })),
  { loading: LoadingSpinner }
);
```

### Step 3: Conditional Loading

Only load components when needed:

```tsx
function MyPage() {
  const [showModal, setShowModal] = useState(false);

  return (
    <div>
      <button onClick={() => setShowModal(true)}>
        Open Modal
      </button>

      {/* Modal code only loads when user clicks button */}
      {showModal && <DynamicModal {...props} />}
    </div>
  );
}
```

### Step 4: Custom Loading States

Provide better UX with custom loaders:

```tsx
function InvoiceLoader() {
  return (
    <div className="animate-pulse p-6">
      <div className="h-4 bg-gray-200 rounded w-1/4 mb-4"></div>
      <div className="h-6 bg-gray-200 rounded w-1/2"></div>
    </div>
  );
}

const InvoiceCard = createDynamicImport(
  () => import('@/components/InvoiceCard'),
  { loading: InvoiceLoader }
);
```

## Best Practices

### When to Use Code Splitting

✅ **DO use for:**
- Large third-party libraries (>20KB)
- Components shown conditionally (modals, drawers, tooltips)
- Heavy components (canvas, video, charts)
- Route-specific functionality
- Below-the-fold content

❌ **DON'T use for:**
- Small components (<5KB)
- Always-visible components
- Critical above-the-fold content
- Simple presentational components

### Loading States

Always provide loading states for dynamic components:

```tsx
// Good - with loading state
const Component = createDynamicImport(
  () => import('./Component'),
  { loading: LoadingSpinner }
);

// Bad - no loading state (shows blank space)
const Component = dynamic(() => import('./Component'));
```

### Server-Side Rendering

Disable SSR for components that use browser APIs:

```tsx
// Good - SSR disabled for canvas component
const SignatureCanvas = createDynamicImport(
  () => import('./SignatureCanvas'),
  { ssr: false }
);

// Bad - SSR enabled (will error on server)
const SignatureCanvas = createDynamicImport(
  () => import('./SignatureCanvas')
);
```

### Prefetching

Prefetch components before users need them:

```tsx
function Navigation() {
  // Prefetch on hover
  const prefetchDocuments = () => {
    import('@/components/DocumentCard');
  };

  return (
    <Link
      href="/documents"
      onMouseEnter={prefetchDocuments}
    >
      Documents
    </Link>
  );
}
```

## Performance Impact

### Bundle Size Reduction

With proper code splitting, you should see:

- **Initial Load**: 250KB → 150KB (40% reduction)
- **Time to Interactive**: 3.5s → 2.1s (40% faster)
- **First Contentful Paint**: Improved by 30%

### Chunk Breakdown (Target)

```
Framework Chunk:    ~150KB (React, Next.js core)
Home Page:          ~40KB  (landing page code)
Documents Page:     ~38KB  (documents route)
Invoices Page:      ~42KB  (invoices route)
Messages Page:      ~41KB  (messages route)
DocumentSignature:  ~28KB  (lazy-loaded on demand)
PhotoGallery:       ~25KB  (lazy-loaded on demand)
```

### Monitoring Performance

Check performance with:

```bash
# Bundle analysis
npm run analyze

# Lighthouse audit
npm run build && npm run start
# Then run Lighthouse in Chrome DevTools
```

## Troubleshooting

### Component Not Loading

**Problem**: Dynamic component shows loading state forever

**Solution**: Check import path and ensure component is exported

```tsx
// Wrong
const Component = createDynamicImport(
  () => import('./Component') // Missing .then()
);

// Correct
const Component = createDynamicImport(
  () => import('./Component').then(mod => ({ default: mod.Component }))
);
```

### SSR Errors

**Problem**: Component errors on server-side rendering

**Solution**: Disable SSR for browser-only components

```tsx
const BrowserComponent = createDynamicImport(
  () => import('./BrowserComponent'),
  { ssr: false } // Disable SSR
);
```

### Flash of Loading State

**Problem**: Loading state flashes even for cached components

**Solution**: Use suspense mode or no-loader variant for fast components

```tsx
const FastComponent = createDynamicImportNoLoader(
  () => import('./FastComponent')
);
```

### Large Bundle Size

**Problem**: Bundle still too large after code splitting

**Solution**:
1. Run `npm run analyze` to identify large dependencies
2. Check for duplicate dependencies
3. Use tree-shaking compatible imports
4. Consider alternative lighter libraries

## Examples

See `lib/dynamicImportExamples.tsx` for complete examples of:
- Basic dynamic imports
- Conditional loading
- Route-based splitting
- Multiple component splitting
- Custom loading states
- Prefetching strategies

## Additional Resources

- [Next.js Code Splitting Docs](https://nextjs.org/docs/app/building-your-application/optimizing/lazy-loading)
- [React.lazy Documentation](https://react.dev/reference/react/lazy)
- [Web.dev Code Splitting Guide](https://web.dev/code-splitting/)
- [Bundle Analyzer Tool](https://github.com/vercel/next.js/tree/canary/packages/next-bundle-analyzer)

## Summary

Code splitting is automatically enabled for routes in Next.js. Enhance it by:

1. **Analyze bundle**: `npm run analyze`
2. **Identify heavy components**: Look for >20KB chunks
3. **Apply dynamic imports**: Use `createDynamicImport()` utility
4. **Test loading states**: Verify UX during component load
5. **Monitor performance**: Use Lighthouse and bundle analyzer
6. **Iterate**: Continuously optimize based on metrics

For questions or issues, refer to the troubleshooting section or check the examples file.
