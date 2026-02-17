import { describe, it, expect } from 'vitest';
import { readFileSync, existsSync } from 'fs';
import { join } from 'path';

describe('Bundle Analyzer Configuration', () => {
  const rootDir = join(__dirname, '..');
  const packageJsonPath = join(rootDir, 'package.json');
  const nextConfigPath = join(rootDir, 'next.config.js');

  it('should have @next/bundle-analyzer installed in devDependencies', () => {
    const packageJson = JSON.parse(readFileSync(packageJsonPath, 'utf-8'));

    expect(packageJson.devDependencies).toBeDefined();
    expect(packageJson.devDependencies['@next/bundle-analyzer']).toBeDefined();
    expect(packageJson.devDependencies['@next/bundle-analyzer']).toMatch(/\d+\.\d+\.\d+/);
  });

  it('should have analyze script in package.json', () => {
    const packageJson = JSON.parse(readFileSync(packageJsonPath, 'utf-8'));

    expect(packageJson.scripts).toBeDefined();
    expect(packageJson.scripts.analyze).toBeDefined();
    expect(packageJson.scripts.analyze).toContain('ANALYZE=true');
    expect(packageJson.scripts.analyze).toContain('next build');
  });

  it('should have analyze:browser and analyze:server scripts', () => {
    const packageJson = JSON.parse(readFileSync(packageJsonPath, 'utf-8'));

    expect(packageJson.scripts['analyze:browser']).toBeDefined();
    expect(packageJson.scripts['analyze:browser']).toContain('BUNDLE_ANALYZE=browser');

    expect(packageJson.scripts['analyze:server']).toBeDefined();
    expect(packageJson.scripts['analyze:server']).toContain('BUNDLE_ANALYZE=server');
  });

  it('should have next.config.js with bundle analyzer integration', () => {
    expect(existsSync(nextConfigPath)).toBe(true);

    const nextConfig = readFileSync(nextConfigPath, 'utf-8');

    // Check that bundle analyzer is imported
    expect(nextConfig).toContain('@next/bundle-analyzer');
    expect(nextConfig).toContain('withBundleAnalyzer');
  });

  it('should configure bundle analyzer to run only when ANALYZE=true', () => {
    const nextConfig = readFileSync(nextConfigPath, 'utf-8');

    // Check configuration
    expect(nextConfig).toContain("enabled: process.env.ANALYZE === 'true'");
  });

  it('should export next config wrapped with bundle analyzer', () => {
    const nextConfig = readFileSync(nextConfigPath, 'utf-8');

    // Check that config is exported wrapped with analyzer
    expect(nextConfig).toContain('withBundleAnalyzer(nextConfig)');
  });
});

describe('Bundle Analyzer Documentation', () => {
  const docsDir = join(__dirname, '..', 'docs');
  const guideDocPath = join(docsDir, 'BUNDLE_SIZE_ANALYSIS.md');
  const implementationDocPath = join(docsDir, 'BUNDLE_SIZE_ANALYSIS_IMPLEMENTATION.md');

  it('should have bundle size analysis guide documentation', () => {
    expect(existsSync(guideDocPath)).toBe(true);

    const content = readFileSync(guideDocPath, 'utf-8');
    expect(content).toContain('Bundle Size Analysis');
    expect(content).toContain('npm run analyze');
    expect(content).toContain('Optimization');
  });

  it('should have bundle size analysis implementation documentation', () => {
    expect(existsSync(implementationDocPath)).toBe(true);

    const content = readFileSync(implementationDocPath, 'utf-8');
    expect(content).toContain('043-3-1');
    expect(content).toContain('Implementation Summary');
    expect(content).toContain('@next/bundle-analyzer');
  });
});

describe('Bundle Analyzer Usage', () => {
  it('should provide instructions for running basic analysis', () => {
    const packageJson = JSON.parse(readFileSync(join(__dirname, '..', 'package.json'), 'utf-8'));

    // Verify the analyze command exists and is properly formatted
    const analyzeCommand = packageJson.scripts.analyze;
    expect(analyzeCommand).toBe('ANALYZE=true next build');
  });

  it('should allow analyzing only browser bundle', () => {
    const packageJson = JSON.parse(readFileSync(join(__dirname, '..', 'package.json'), 'utf-8'));

    const browserCommand = packageJson.scripts['analyze:browser'];
    expect(browserCommand).toBe('BUNDLE_ANALYZE=browser next build');
  });

  it('should allow analyzing only server bundle', () => {
    const packageJson = JSON.parse(readFileSync(join(__dirname, '..', 'package.json'), 'utf-8'));

    const serverCommand = packageJson.scripts['analyze:server'];
    expect(serverCommand).toBe('BUNDLE_ANALYZE=server next build');
  });
});
