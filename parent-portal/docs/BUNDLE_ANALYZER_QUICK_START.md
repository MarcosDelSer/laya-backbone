# Bundle Analyzer Quick Start

## TL;DR

```bash
# Analyze your bundle
npm run analyze

# Browser will open with interactive visualization
# Look for large boxes = large dependencies
```

---

## Quick Commands

```bash
# Full analysis (client + server)
npm run analyze

# Browser bundle only
npm run analyze:browser

# Server bundle only
npm run analyze:server
```

---

## What to Look For

### 🔴 Red Flags

1. **Large dependencies > 100 KB**
   - Consider lighter alternatives
   - Use dynamic imports

2. **Duplicate modules**
   - Same code in multiple chunks
   - Optimize code splitting

3. **Dev dependencies in production**
   - Fix import paths
   - Check package.json

### 🟢 Good Signs

1. **Small chunks (< 50 KB each)**
2. **Framework chunk ~150 KB** (expected)
3. **Tree-shaken imports** (small icon libraries)

---

## Quick Wins

### Replace Heavy Dependencies

```bash
# Check size before installing
npx bundle-phobia <package-name>

# Common replacements
moment → date-fns          # 97% smaller
lodash → lodash-es         # 67% smaller
axios → fetch API          # 100% smaller
```

### Fix Imports

```javascript
// ❌ Bad (imports everything)
import _ from 'lodash';

// ✅ Good (tree-shakeable)
import { debounce } from 'lodash-es';
```

### Dynamic Imports

```javascript
// ❌ Bad (always loaded)
import { PDFDocument } from 'pdf-lib';

// ✅ Good (load when needed)
const { PDFDocument } = await import('pdf-lib');
```

---

## Bundle Size Targets

| Metric | Target | Max |
|--------|--------|-----|
| First Load JS | < 200 KB | < 300 KB |
| Page Chunks | < 50 KB | < 100 KB |
| Total Bundle | < 500 KB | < 1 MB |

---

## Need More Help?

- [Full Guide](./BUNDLE_SIZE_ANALYSIS.md)
- [Implementation Details](./BUNDLE_SIZE_ANALYSIS_IMPLEMENTATION.md)

---

**Pro Tip:** Run analysis before major releases and when adding new dependencies!
