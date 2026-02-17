/**
 * Code Splitting Tests
 *
 * These tests verify the code splitting implementation for the LAYA Parent Portal.
 * They ensure webpack configuration, dynamic imports, and documentation are correct.
 */

import { describe, it, expect } from 'vitest';
import fs from 'fs';
import path from 'path';

describe('Code Splitting Implementation', () => {
  describe('Configuration', () => {
    it('should have webpack optimization in next.config.js', () => {
      const configPath = path.join(process.cwd(), 'next.config.js');
      const config = fs.readFileSync(configPath, 'utf-8');

      // Verify webpack configuration exists
      expect(config).toContain('webpack:');
      expect(config).toContain('splitChunks');

      // Verify cache groups
      expect(config).toContain('cacheGroups');
      expect(config).toContain('framework');
      expect(config).toContain('lib');
      expect(config).toContain('commons');

      // Verify optimization settings
      expect(config).toContain('minChunks');
      expect(config).toContain('priority');
      expect(config).toContain('reuseExistingChunk');
    });

    it('should configure framework chunk for React and Next.js', () => {
      const configPath = path.join(process.cwd(), 'next.config.js');
      const config = fs.readFileSync(configPath, 'utf-8');

      expect(config).toContain('framework');
      expect(config).toContain('react');
      expect(config).toContain('next');
      expect(config).toContain('priority: 40');
    });

    it('should configure library chunks for node_modules', () => {
      const configPath = path.join(process.cwd(), 'next.config.js');
      const config = fs.readFileSync(configPath, 'utf-8');

      expect(config).toContain('lib:');
      expect(config).toContain('node_modules');
      expect(config).toContain('priority: 30');
    });

    it('should configure commons chunk for shared code', () => {
      const configPath = path.join(process.cwd(), 'next.config.js');
      const config = fs.readFileSync(configPath, 'utf-8');

      expect(config).toContain('commons');
      expect(config).toContain('minChunks: 2');
      expect(config).toContain('priority: 20');
    });

    it('should set appropriate chunk size limits', () => {
      const configPath = path.join(process.cwd(), 'next.config.js');
      const config = fs.readFileSync(configPath, 'utf-8');

      expect(config).toContain('maxInitialRequests');
      expect(config).toContain('minSize');
    });
  });

  describe('Dynamic Import Utility', () => {
    it('should have dynamicImport.tsx utility file', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      expect(fs.existsSync(utilPath)).toBe(true);
    });

    it('should export createDynamicImport function', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('export function createDynamicImport');
      expect(content).toContain('importFn');
      expect(content).toContain('DynamicImportOptions');
    });

    it('should export loading components', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('export function LoadingSpinner');
      expect(content).toContain('export function LoadingPlaceholder');
      expect(content).toContain('export function LoadingError');
    });

    it('should provide pre-configured dynamic imports', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      // Heavy components should have pre-configured imports
      expect(content).toContain('DynamicDocumentSignature');
      expect(content).toContain('DynamicSignatureCanvas');
      expect(content).toContain('DynamicPhotoGallery');
      expect(content).toContain('DynamicDocumentCard');
      expect(content).toContain('DynamicInvoiceCard');
      expect(content).toContain('DynamicMessageThread');
      expect(content).toContain('DynamicMessageComposer');
    });

    it('should support SSR configuration', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('ssr');
      expect(content).toContain('ssr: false'); // For browser-only components
      expect(content).toContain('ssr: true');  // For server-compatible components
    });

    it('should support custom loading components', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('loading');
      expect(content).toContain('ComponentType');
    });

    it('should export createDynamicImportNoLoader variant', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('export function createDynamicImportNoLoader');
    });
  });

  describe('Loading Components', () => {
    it('should have LoadingSpinner with animation', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('LoadingSpinner');
      expect(content).toContain('animate-spin');
    });

    it('should have LoadingPlaceholder with pulse animation', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('LoadingPlaceholder');
      expect(content).toContain('animate-pulse');
    });

    it('should have LoadingError with retry functionality', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('LoadingError');
      expect(content).toContain('retry');
      expect(content).toContain('error');
    });
  });

  describe('Documentation', () => {
    it('should have CODE_SPLITTING.md documentation', () => {
      const docPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING.md');
      expect(fs.existsSync(docPath)).toBe(true);
    });

    it('should have CODE_SPLITTING_IMPLEMENTATION.md summary', () => {
      const docPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING_IMPLEMENTATION.md');
      expect(fs.existsSync(docPath)).toBe(true);
    });

    it('should document what is code splitting', () => {
      const docPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING.md');
      const content = fs.readFileSync(docPath, 'utf-8');

      expect(content).toContain('What is Code Splitting');
      expect(content).toContain('Benefits');
      expect(content).toContain('How It Works');
    });

    it('should document automatic route-based splitting', () => {
      const docPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING.md');
      const content = fs.readFileSync(docPath, 'utf-8');

      expect(content).toContain('Automatic Route-Based Splitting');
      expect(content).toContain('Next.js');
      expect(content).toContain('route');
    });

    it('should document manual component splitting', () => {
      const docPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING.md');
      const content = fs.readFileSync(docPath, 'utf-8');

      expect(content).toContain('Manual Component Splitting');
      expect(content).toContain('createDynamicImport');
      expect(content).toContain('dynamic import');
    });

    it('should document best practices', () => {
      const docPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING.md');
      const content = fs.readFileSync(docPath, 'utf-8');

      expect(content).toContain('Best Practices');
      expect(content).toContain('DO use for');
      expect(content).toContain("DON'T use for");
    });

    it('should document performance impact', () => {
      const docPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING.md');
      const content = fs.readFileSync(docPath, 'utf-8');

      expect(content).toContain('Performance Impact');
      expect(content).toContain('Bundle Size');
      expect(content).toContain('reduction');
    });

    it('should include troubleshooting guide', () => {
      const docPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING.md');
      const content = fs.readFileSync(docPath, 'utf-8');

      expect(content).toContain('Troubleshooting');
      expect(content).toContain('Problem');
      expect(content).toContain('Solution');
    });

    it('should include code examples', () => {
      const docPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING.md');
      const content = fs.readFileSync(docPath, 'utf-8');

      expect(content).toContain('```');
      expect(content).toContain('tsx');
      expect(content).toContain('import');
    });

    it('should document implementation details', () => {
      const implPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING_IMPLEMENTATION.md');
      const content = fs.readFileSync(implPath, 'utf-8');

      expect(content).toContain('Implementation');
      expect(content).toContain('Changes Made');
      expect(content).toContain('Performance Metrics');
    });

    it('should list all files created/modified', () => {
      const implPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING_IMPLEMENTATION.md');
      const content = fs.readFileSync(implPath, 'utf-8');

      expect(content).toContain('next.config.js');
      expect(content).toContain('dynamicImport.tsx');
      expect(content).toContain('CODE_SPLITTING.md');
    });

    it('should document performance improvements', () => {
      const implPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING_IMPLEMENTATION.md');
      const content = fs.readFileSync(implPath, 'utf-8');

      expect(content).toContain('Before Code Splitting');
      expect(content).toContain('After Code Splitting');
      expect(content).toContain('reduction');
      expect(content).toContain('improvement');
    });
  });

  describe('Examples', () => {
    it('should have dynamicImportExamples.tsx file', () => {
      const examplesPath = path.join(process.cwd(), 'lib', 'dynamicImportExamples.tsx');
      expect(fs.existsSync(examplesPath)).toBe(true);
    });

    it('should include basic dynamic import example', () => {
      const examplesPath = path.join(process.cwd(), 'lib', 'dynamicImportExamples.tsx');
      const content = fs.readFileSync(examplesPath, 'utf-8');

      expect(content).toContain('EXAMPLE 1');
      expect(content).toContain('Basic Dynamic Import');
      expect(content).toContain('createDynamicImport');
    });

    it('should include conditional loading example', () => {
      const examplesPath = path.join(process.cwd(), 'lib', 'dynamicImportExamples.tsx');
      const content = fs.readFileSync(examplesPath, 'utf-8');

      expect(content).toContain('EXAMPLE 2');
      expect(content).toContain('Conditional Loading');
      expect(content).toContain('useState');
    });

    it('should include route-based splitting example', () => {
      const examplesPath = path.join(process.cwd(), 'lib', 'dynamicImportExamples.tsx');
      const content = fs.readFileSync(examplesPath, 'utf-8');

      expect(content).toContain('EXAMPLE 3');
      expect(content).toContain('Route-Based');
    });

    it('should include custom loading state example', () => {
      const examplesPath = path.join(process.cwd(), 'lib', 'dynamicImportExamples.tsx');
      const content = fs.readFileSync(examplesPath, 'utf-8');

      expect(content).toContain('EXAMPLE 5');
      expect(content).toContain('Custom Loading State');
    });

    it('should include prefetching example', () => {
      const examplesPath = path.join(process.cwd(), 'lib', 'dynamicImportExamples.tsx');
      const content = fs.readFileSync(examplesPath, 'utf-8');

      expect(content).toContain('EXAMPLE 6');
      expect(content).toContain('Prefetch');
    });

    it('should document when to use code splitting', () => {
      const examplesPath = path.join(process.cwd(), 'lib', 'dynamicImportExamples.tsx');
      const content = fs.readFileSync(examplesPath, 'utf-8');

      expect(content).toContain('WHEN TO USE CODE SPLITTING');
      expect(content).toContain('DO use for');
      expect(content).toContain("DON'T use for");
    });

    it('should include performance tips', () => {
      const examplesPath = path.join(process.cwd(), 'lib', 'dynamicImportExamples.tsx');
      const content = fs.readFileSync(examplesPath, 'utf-8');

      expect(content).toContain('PERFORMANCE TIPS');
      expect(content).toContain('Analyze bundle');
      expect(content).toContain('npm run analyze');
    });
  });

  describe('TypeScript Support', () => {
    it('should have TypeScript types for dynamic import options', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('interface DynamicImportOptions');
      expect(content).toContain('loading?');
      expect(content).toContain('ssr?');
      expect(content).toContain('suspense?');
    });

    it('should use generic types for component props', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('<P = any>');
      expect(content).toContain('ComponentType<P>');
    });

    it('should type loading components correctly', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('className?: string');
      expect(content).toContain('error?: Error');
      expect(content).toContain('retry?:');
    });
  });

  describe('Heavy Component Identification', () => {
    it('should identify DocumentSignature as heavy component', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('DocumentSignature');
      expect(content).toContain('heavy - uses canvas');
    });

    it('should identify SignatureCanvas as heavy component', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('SignatureCanvas');
    });

    it('should identify PhotoGallery as heavy component', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      expect(content).toContain('PhotoGallery');
      expect(content).toContain('image processing');
    });

    it('should configure SSR appropriately for browser-only components', () => {
      const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
      const content = fs.readFileSync(utilPath, 'utf-8');

      // Canvas components should have SSR disabled
      const signatureImport = content.match(/DynamicDocumentSignature[\s\S]*?ssr: false/);
      expect(signatureImport).toBeTruthy();

      const canvasImport = content.match(/DynamicSignatureCanvas[\s\S]*?ssr: false/);
      expect(canvasImport).toBeTruthy();
    });
  });

  describe('Integration', () => {
    it('should maintain compatibility with existing bundle analyzer', () => {
      const configPath = path.join(process.cwd(), 'next.config.js');
      const config = fs.readFileSync(configPath, 'utf-8');

      expect(config).toContain('withBundleAnalyzer');
      expect(config).toContain('module.exports = withBundleAnalyzer(nextConfig)');
    });

    it('should not break existing next.config.js settings', () => {
      const configPath = path.join(process.cwd(), 'next.config.js');
      const config = fs.readFileSync(configPath, 'utf-8');

      // Verify existing settings still present
      expect(config).toContain('reactStrictMode');
      expect(config).toContain('images:');
      expect(config).toContain('compiler:');
      expect(config).toContain('experimental:');
    });

    it('should work with existing image optimization', () => {
      const configPath = path.join(process.cwd(), 'next.config.js');
      const config = fs.readFileSync(configPath, 'utf-8');

      expect(config).toContain('optimizePackageImports');
    });
  });

  describe('Performance', () => {
    it('should set appropriate priority levels for cache groups', () => {
      const configPath = path.join(process.cwd(), 'next.config.js');
      const config = fs.readFileSync(configPath, 'utf-8');

      // Framework should have highest priority
      expect(config).toContain('priority: 40');
      // Libraries should have medium priority
      expect(config).toContain('priority: 30');
      // Commons should have lowest priority
      expect(config).toContain('priority: 20');
    });

    it('should enforce chunk reuse', () => {
      const configPath = path.join(process.cwd(), 'next.config.js');
      const config = fs.readFileSync(configPath, 'utf-8');

      expect(config).toContain('reuseExistingChunk: true');
    });

    it('should set minimum chunk size', () => {
      const configPath = path.join(process.cwd(), 'next.config.js');
      const config = fs.readFileSync(configPath, 'utf-8');

      expect(config).toContain('minSize: 20000');
    });

    it('should limit initial requests for optimal loading', () => {
      const configPath = path.join(process.cwd(), 'next.config.js');
      const config = fs.readFileSync(configPath, 'utf-8');

      expect(config).toContain('maxInitialRequests: 25');
    });
  });
});

describe('Code Splitting Quality', () => {
  it('should have comprehensive documentation (>400 lines)', () => {
    const docPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING.md');
    const content = fs.readFileSync(docPath, 'utf-8');
    const lineCount = content.split('\n').length;

    expect(lineCount).toBeGreaterThan(400);
  });

  it('should have detailed implementation summary (>300 lines)', () => {
    const implPath = path.join(process.cwd(), 'docs', 'CODE_SPLITTING_IMPLEMENTATION.md');
    const content = fs.readFileSync(implPath, 'utf-8');
    const lineCount = content.split('\n').length;

    expect(lineCount).toBeGreaterThan(300);
  });

  it('should have multiple practical examples', () => {
    const examplesPath = path.join(process.cwd(), 'lib', 'dynamicImportExamples.tsx');
    const content = fs.readFileSync(examplesPath, 'utf-8');

    const exampleCount = (content.match(/EXAMPLE \d+:/g) || []).length;
    expect(exampleCount).toBeGreaterThanOrEqual(5);
  });

  it('should provide utility functions for developers', () => {
    const utilPath = path.join(process.cwd(), 'lib', 'dynamicImport.tsx');
    const content = fs.readFileSync(utilPath, 'utf-8');

    const functionCount = (content.match(/export (function|const)/g) || []).length;
    expect(functionCount).toBeGreaterThanOrEqual(10);
  });
});
