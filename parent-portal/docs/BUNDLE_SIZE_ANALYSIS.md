# Bundle Size Analysis Guide

## Overview

Bundle size analysis is a critical performance optimization technique that helps identify large dependencies, unnecessary code, and opportunities for code splitting. This guide explains how to use the bundle analyzer in the LAYA parent portal.

## Why Bundle Size Matters

### Performance Impact

Large JavaScript bundles negatively affect application performance:

- **Longer Download Times**: Larger files take longer to download, especially on slower networks
- **Slower Parse Time**: JavaScript must be parsed and compiled before execution
- **Delayed Interactivity**: Users wait longer before they can interact with the app
- **Poor Core Web Vitals**: Large bundles hurt FCP (First Contentful Paint) and TTI (Time to Interactive)

### Best Practice Bundle Sizes

| Bundle Type | Target Size | Maximum Size | Warning Level |
|-------------|-------------|--------------|---------------|
| Initial JS (First Load) | < 200 KB | < 300 KB | 🟢 Good |
| Total First Load | < 500 KB | < 1 MB | 🟡 Warning |
| Route Chunks | < 100 KB | < 200 KB | 🟢 Good |
| Shared Chunks | < 150 KB | < 250 KB | 🟡 Warning |

### Common Issues

1. **Unnecessary Dependencies**: Installing packages that are too large or not needed
2. **Duplicate Code**: Same code bundled multiple times across chunks
3. **Large Libraries**: Using entire libraries when only small parts are needed
4. **Lack of Code Splitting**: All code loaded upfront instead of on-demand
5. **Development Code in Production**: Debug code, console logs, or dev tools included

---

## Configuration

### next.config.js

The bundle analyzer is configured in `next.config.js`:

```javascript
const withBundleAnalyzer = require('@next/bundle-analyzer')({
  enabled: process.env.ANALYZE === 'true',
  openAnalyzer: true,
});

const nextConfig = {
  // ... other config
};

module.exports = withBundleAnalyzer(nextConfig);
```

**Configuration Options:**

- `enabled`: Controls when analysis runs (via ANALYZE environment variable)
- `openAnalyzer`: Automatically opens the visualization in your browser

---

## Running Bundle Analysis

### Basic Analysis

Analyze the entire production bundle (client + server):

```bash
cd parent-portal
npm run analyze
```

This will:
1. Build the production bundle with optimizations
2. Generate interactive HTML reports
3. Automatically open them in your browser

### Browser Bundle Only

Analyze only the client-side JavaScript:

```bash
npm run analyze:browser
```

### Server Bundle Only

Analyze only the server-side bundle:

```bash
npm run analyze:server
```

### Output Files

After running analysis, you'll find:

```
parent-portal/.next/
├── analyze/
│   ├── client.html          # Client bundle visualization
│   └── server.html          # Server bundle visualization
```

---

## Understanding the Reports

### Interactive Visualization

The bundle analyzer creates an interactive treemap where:

- **Box Size**: Represents the size of each module
- **Colors**: Different colors for different file types
- **Nested Boxes**: Show how modules are organized into chunks
- **Hover Info**: Shows exact file sizes and percentages

### Key Metrics

**Stat Size**: Size of the source code before any transformations
**Parsed Size**: Size after webpack processing but before compression
**Gzipped Size**: Size that will actually be transferred over the network

> **Focus on Gzipped Size** - This is what users actually download!

### What to Look For

#### 1. Large Individual Modules

Look for boxes that seem disproportionately large:

```
🔴 BAD:  moment.js (539 KB gzipped)
🟢 GOOD: date-fns (15 KB gzipped)
```

**Action**: Replace with lighter alternatives or use tree-shaking.

#### 2. Duplicate Dependencies

Same library appearing in multiple chunks:

```
🔴 BAD:  react-icons appears in 5 different chunks
🟢 GOOD: react-icons in shared chunk, used by all
```

**Action**: Optimize code splitting or move to shared chunk.

#### 3. Unused Code

Large dependencies with minimal usage:

```
🔴 BAD:  lodash (entire library) when only using 3 functions
🟢 GOOD: lodash-es with tree-shaking or lodash/function imports
```

**Action**: Use tree-shakeable imports or smaller alternatives.

#### 4. Development Dependencies

Dev-only code in production bundle:

```
🔴 BAD:  @storybook/react in production bundle
🟢 GOOD: Dev dependencies only in development mode
```

**Action**: Ensure proper package.json organization and conditional imports.

---

## Optimization Strategies

### 1. Replace Heavy Dependencies

Identify and replace large libraries with lighter alternatives:

```bash
# Before (heavy)
npm install moment          # 539 KB gzipped

# After (light)
npm install date-fns        # 15 KB gzipped
```

**Common Replacements:**

| Heavy Library | Size | Lighter Alternative | Size | Savings |
|---------------|------|---------------------|------|---------|
| moment | 539 KB | date-fns | 15 KB | 97% |
| lodash | 72 KB | lodash-es | 24 KB | 67% |
| axios | 33 KB | fetch API | 0 KB | 100% |
| react-icons (all) | 1.2 MB | react-icons/md (specific) | 50 KB | 96% |

### 2. Tree Shaking

Use named imports to enable tree shaking:

```javascript
// ❌ Bad: Imports entire library
import _ from 'lodash';
const result = _.debounce(fn, 300);

// ✅ Good: Only imports needed function
import debounce from 'lodash/debounce';
const result = debounce(fn, 300);

// ✅ Better: Use tree-shakeable version
import { debounce } from 'lodash-es';
const result = debounce(fn, 300);
```

### 3. Dynamic Imports

Load code only when needed:

```javascript
// ❌ Bad: Always loads PDF library
import { PDFDocument } from 'pdf-lib';

function ExportButton() {
  const handleExport = async () => {
    const doc = await PDFDocument.create();
    // ...
  };
}

// ✅ Good: Loads PDF library only when needed
function ExportButton() {
  const handleExport = async () => {
    const { PDFDocument } = await import('pdf-lib');
    const doc = await PDFDocument.create();
    // ...
  };
}
```

### 4. Code Splitting by Route

Next.js automatically splits code by route, but you can optimize:

```javascript
// app/admin/page.tsx
// ✅ Admin code only loads when visiting /admin
import { AdminDashboard } from '@/components/admin';

export default function AdminPage() {
  return <AdminDashboard />;
}
```

### 5. Component-Level Code Splitting

Split large components with dynamic imports:

```javascript
import dynamic from 'next/dynamic';

// ✅ PhotoGallery loads only when used
const PhotoGallery = dynamic(
  () => import('@/components/PhotoGallery'),
  {
    loading: () => <p>Loading gallery...</p>,
    ssr: false, // Disable server-side rendering if not needed
  }
);

function ActivityReport() {
  return (
    <div>
      <h1>Daily Activity</h1>
      {showGallery && <PhotoGallery photos={photos} />}
    </div>
  );
}
```

### 6. External Dependencies

For large libraries, consider using CDN:

```javascript
// next.config.js
module.exports = {
  experimental: {
    externalDir: true,
  },
};

// Or use the CDN approach for specific libraries
// (Not recommended for critical path dependencies)
```

---

## Analysis Workflow

### Step-by-Step Optimization Process

#### 1. Establish Baseline

Run initial analysis to understand current state:

```bash
npm run analyze
```

Document current sizes:
- Total First Load JS
- Largest chunks
- Largest individual modules

#### 2. Identify Issues

Look for:
- ✅ Modules > 50 KB gzipped
- ✅ Duplicate dependencies across chunks
- ✅ Dev dependencies in production
- ✅ Entire libraries when only parts are used

#### 3. Prioritize Optimizations

Focus on high-impact changes first:

**High Impact:**
- Replacing 500 KB library with 50 KB alternative (90% reduction)
- Removing unused dependencies
- Code splitting large features

**Medium Impact:**
- Tree-shaking individual functions
- Optimizing import statements
- Lazy loading components

**Low Impact:**
- Micro-optimizations
- Minification tweaks
- Variable renaming

#### 4. Implement Changes

Make one change at a time and test:

```bash
# Make optimization
# ...

# Re-run analysis
npm run analyze

# Compare before/after
# Document results
```

#### 5. Measure Impact

Compare metrics before and after:

```markdown
## Optimization: Replace moment with date-fns

### Before
- Total First Load: 487 KB
- moment.js: 539 KB stat / 289 KB gzipped

### After
- Total First Load: 213 KB (56% reduction)
- date-fns: 180 KB stat / 15 KB gzipped

### Savings
- 274 KB reduction in first load
- 274 KB less data transferred
- ~2s faster load on 3G
```

#### 6. Continuous Monitoring

Set up bundle size checks:

```json
// package.json
{
  "scripts": {
    "size-check": "npm run build && bundlesize"
  }
}
```

Consider adding to CI/CD pipeline.

---

## Common Patterns in LAYA Portal

### Current Bundle Structure

```
parent-portal (First Load JS: ~200-300 KB)
├── framework chunk (~150 KB)
│   ├── react
│   ├── react-dom
│   └── next runtime
├── main chunk (~50 KB)
│   ├── app layout
│   └── shared components
└── page chunks (~20-50 KB each)
    ├── /dashboard
    ├── /activities
    ├── /messages
    └── /profile
```

### Optimizations Applied

1. **Image Optimization**: Using next/image (saved ~68% on images)
2. **Tree Shaking**: Named imports for @heroicons/react and date-fns
3. **Code Splitting**: Automatic route-based splitting
4. **Lazy Loading**: Dynamic imports for heavy components

### Dependencies Analysis

**Large Dependencies to Monitor:**

| Package | Size | Usage | Optimization |
|---------|------|-------|--------------|
| react + react-dom | ~140 KB | Core framework | Required |
| next | ~50 KB | Framework | Required |
| date-fns | ~15 KB | Date formatting | Tree-shaken ✅ |
| @heroicons/react | ~10 KB | Icons | Tree-shaken ✅ |

---

## Best Practices

### Development

1. **Check Bundle Size Regularly**: Run analysis before major releases
2. **Review New Dependencies**: Check size before adding new packages
3. **Use Import Cost Extension**: VS Code extension showing import sizes in real-time
4. **Set Size Budgets**: Fail builds if bundle size exceeds thresholds

### Importing Libraries

```javascript
// ❌ Avoid default imports from large libraries
import _ from 'lodash';
import * as Icons from '@heroicons/react/24/outline';

// ✅ Use named imports for tree shaking
import { debounce } from 'lodash-es';
import { UserIcon, CogIcon } from '@heroicons/react/24/outline';

// ✅ Use direct path imports
import debounce from 'lodash/debounce';
```

### Dynamic Imports

```javascript
// ✅ Good patterns for dynamic imports

// Heavy libraries used occasionally
const handlePDF = async () => {
  const pdfLib = await import('pdf-lib');
  // Use pdfLib
};

// Large components not on critical path
const PhotoGallery = dynamic(() => import('./PhotoGallery'), {
  loading: () => <Skeleton />,
});

// Admin-only features
const AdminPanel = dynamic(() => import('./AdminPanel'), {
  ssr: false,
});
```

### Code Organization

```typescript
// ✅ Organize imports by size/necessity

// 1. Framework (small, required)
import React from 'react';
import { useState } from 'react';

// 2. Next.js (optimized by framework)
import Image from 'next/image';
import Link from 'next/link';

// 3. Small utilities (tree-shakeable)
import { format } from 'date-fns';
import { UserIcon } from '@heroicons/react/24/outline';

// 4. Local components (code-split by route)
import { Header } from '@/components/Header';
import { Footer } from '@/components/Footer';

// 5. Heavy libraries (dynamic import if possible)
// import { PDFDocument } from 'pdf-lib'; // ❌
// Use dynamic import instead ✅
```

---

## Troubleshooting

### Issue: Analyzer Won't Open

**Symptoms**: Build completes but browser doesn't open

**Solutions:**
1. Check that `openAnalyzer: true` in next.config.js
2. Manually open `.next/analyze/client.html` in browser
3. Try different browser
4. Check firewall settings

### Issue: Large Unexpected Dependencies

**Symptoms**: Unknown packages showing large size

**Solutions:**
1. Check all imports in your code
2. Look for `import *` statements
3. Review package.json dependencies
4. Use `npm ls <package-name>` to find where it's used

### Issue: Cannot Reduce Bundle Size

**Symptoms**: Optimization attempts don't reduce size

**Solutions:**
1. Clear `.next` folder: `rm -rf .next`
2. Clear node_modules: `rm -rf node_modules && npm install`
3. Check if production mode: `NODE_ENV=production npm run analyze`
4. Verify tree shaking is working (check webpack config)

### Issue: Duplicate Code in Multiple Chunks

**Symptoms**: Same module appearing in multiple chunks

**Solutions:**
1. Move shared code to `_app.tsx` or layout
2. Adjust webpack splitChunks configuration
3. Create shared chunk for common dependencies
4. Review dynamic import strategy

---

## Monitoring and Metrics

### Key Performance Indicators

Track these metrics over time:

```markdown
## Bundle Size KPIs

### First Load JS
- Target: < 200 KB
- Warning: 200-300 KB
- Critical: > 300 KB

### Total Bundle Size
- Target: < 500 KB
- Warning: 500 KB - 1 MB
- Critical: > 1 MB

### Largest Chunk
- Target: < 100 KB
- Warning: 100-200 KB
- Critical: > 200 KB

### Number of Chunks
- Target: 5-15 chunks
- Warning: 15-30 chunks
- Critical: > 30 chunks
```

### Integration with CI/CD

```yaml
# .github/workflows/bundle-size.yml
name: Bundle Size Check

on: [pull_request]

jobs:
  bundle-size:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      - name: Setup Node
        uses: actions/setup-node@v2
      - name: Install dependencies
        run: npm ci
      - name: Build and analyze
        run: npm run analyze
      - name: Check bundle size
        run: npm run size-check
```

### Real User Monitoring

```javascript
// pages/_app.tsx
export function reportWebVitals(metric) {
  // Track bundle load performance
  if (metric.name === 'FCP' || metric.name === 'TTI') {
    // Send to analytics
    console.log(metric);
  }
}
```

---

## Next Steps

After setting up bundle analysis:

1. ✅ **Run Initial Analysis**: Establish baseline metrics
2. ✅ **Document Current State**: Record bundle sizes and structure
3. ✅ **Identify Quick Wins**: Find and replace heavy dependencies
4. ✅ **Set Up Monitoring**: Add size checks to CI/CD
5. ✅ **Create Size Budget**: Define acceptable size thresholds
6. ✅ **Regular Reviews**: Monthly bundle size audit

---

## Related Documentation

- [Image Optimization Guide](./IMAGE_OPTIMIZATION.md)
- [Code Splitting Guide](./CODE_SPLITTING.md) (Task 043-3-2)
- [Performance Best Practices](./PERFORMANCE.md)

---

## External Resources

- [Next.js Bundle Analyzer](https://www.npmjs.com/package/@next/bundle-analyzer)
- [Webpack Bundle Analyzer](https://github.com/webpack-contrib/webpack-bundle-analyzer)
- [Google's Web Vitals](https://web.dev/vitals/)
- [Bundle Size Optimization Guide](https://web.dev/performance-optimizing-content-efficiency/)

---

**Last Updated:** 2026-02-16
**Task:** 043-3-1 - Bundle Size Analysis
**Maintained By:** LAYA Development Team
