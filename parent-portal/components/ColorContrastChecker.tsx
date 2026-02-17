'use client';

/**
 * Color Contrast Checker Component
 *
 * A visual tool for testing color contrast ratios against WCAG 2.1 AA/AAA standards.
 * This component is for development and accessibility auditing purposes.
 *
 * Usage:
 * - Add to a dev-only page or behind a feature flag
 * - Test color combinations visually
 * - See real-time contrast ratio calculations
 * - Verify WCAG compliance levels
 */

import { useState } from 'react';
import { meetsWCAG_AA, meetsWCAG_AAA, getContrastRatio } from '../lib/color-contrast';

interface ContrastResult {
  ratio: number;
  aa: {
    normalText: boolean;
    largeText: boolean;
  };
  aaa: {
    normalText: boolean;
    largeText: boolean;
  };
}

export function ColorContrastChecker() {
  const [foreground, setForeground] = useState('#000000');
  const [background, setBackground] = useState('#ffffff');
  const [result, setResult] = useState<ContrastResult | null>(null);

  const checkContrast = () => {
    try {
      const ratio = getContrastRatio(foreground, background);
      const aa = meetsWCAG_AA(foreground, background);
      const aaLarge = meetsWCAG_AA(foreground, background, { largeText: true });
      const aaa = meetsWCAG_AAA(foreground, background);
      const aaaLarge = meetsWCAG_AAA(foreground, background, { largeText: true });

      setResult({
        ratio,
        aa: {
          normalText: aa.passes,
          largeText: aaLarge.passes,
        },
        aaa: {
          normalText: aaa.passes,
          largeText: aaaLarge.passes,
        },
      });
    } catch (error) {
      console.error('Error calculating contrast:', error);
      setResult(null);
    }
  };

  // Auto-check on color change
  useState(() => {
    checkContrast();
  });

  return (
    <div className="mx-auto max-w-4xl rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
      <h2 className="mb-4 text-2xl font-bold text-gray-900">
        Color Contrast Checker
      </h2>
      <p className="mb-6 text-sm text-gray-600">
        Test color combinations for WCAG 2.1 AA/AAA compliance. Normal text requires 4.5:1 (AA) or
        7:1 (AAA). Large text requires 3:1 (AA) or 4.5:1 (AAA).
      </p>

      {/* Color Inputs */}
      <div className="mb-6 grid gap-4 md:grid-cols-2">
        <div>
          <label htmlFor="foreground" className="mb-2 block text-sm font-medium text-gray-700">
            Foreground (Text) Color
          </label>
          <div className="flex items-center gap-3">
            <input
              type="color"
              id="foreground"
              value={foreground}
              onChange={(e) => {
                setForeground(e.target.value);
                setTimeout(checkContrast, 0);
              }}
              className="h-12 w-16 cursor-pointer rounded border border-gray-300"
            />
            <input
              type="text"
              value={foreground}
              onChange={(e) => {
                setForeground(e.target.value);
                setTimeout(checkContrast, 0);
              }}
              className="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm font-mono"
              placeholder="#000000"
            />
          </div>
        </div>

        <div>
          <label htmlFor="background" className="mb-2 block text-sm font-medium text-gray-700">
            Background Color
          </label>
          <div className="flex items-center gap-3">
            <input
              type="color"
              id="background"
              value={background}
              onChange={(e) => {
                setBackground(e.target.value);
                setTimeout(checkContrast, 0);
              }}
              className="h-12 w-16 cursor-pointer rounded border border-gray-300"
            />
            <input
              type="text"
              value={background}
              onChange={(e) => {
                setBackground(e.target.value);
                setTimeout(checkContrast, 0);
              }}
              className="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm font-mono"
              placeholder="#ffffff"
            />
          </div>
        </div>
      </div>

      {/* Preview */}
      <div className="mb-6">
        <h3 className="mb-2 text-sm font-medium text-gray-700">Preview</h3>
        <div
          className="rounded-lg border border-gray-300 p-6"
          style={{ backgroundColor: background }}
        >
          <p className="mb-3 text-base" style={{ color: foreground }}>
            Normal text preview (16px) - The quick brown fox jumps over the lazy dog.
          </p>
          <p className="mb-3 text-lg font-semibold" style={{ color: foreground }}>
            Large text preview (18px, bold) - The quick brown fox jumps over the lazy dog.
          </p>
          <p className="text-2xl font-normal" style={{ color: foreground }}>
            Large text preview (24px) - The quick brown fox jumps over the lazy dog.
          </p>
        </div>
      </div>

      {/* Results */}
      {result && (
        <div className="space-y-4">
          {/* Contrast Ratio */}
          <div className="rounded-lg bg-gray-50 p-4">
            <div className="text-center">
              <div className="text-4xl font-bold text-gray-900">
                {result.ratio.toFixed(2)}:1
              </div>
              <div className="mt-1 text-sm text-gray-600">Contrast Ratio</div>
            </div>
          </div>

          {/* WCAG Compliance Grid */}
          <div className="grid gap-4 md:grid-cols-2">
            {/* AA Compliance */}
            <div className="rounded-lg border border-gray-200 bg-white p-4">
              <h4 className="mb-3 text-lg font-semibold text-gray-900">WCAG 2.1 Level AA</h4>
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-gray-700">Normal Text (4.5:1)</span>
                  <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                      result.aa.normalText
                        ? 'bg-green-100 text-green-800'
                        : 'bg-red-100 text-red-800'
                    }`}
                  >
                    {result.aa.normalText ? '✓ Pass' : '✗ Fail'}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm text-gray-700">Large Text (3:1)</span>
                  <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                      result.aa.largeText
                        ? 'bg-green-100 text-green-800'
                        : 'bg-red-100 text-red-800'
                    }`}
                  >
                    {result.aa.largeText ? '✓ Pass' : '✗ Fail'}
                  </span>
                </div>
              </div>
            </div>

            {/* AAA Compliance */}
            <div className="rounded-lg border border-gray-200 bg-white p-4">
              <h4 className="mb-3 text-lg font-semibold text-gray-900">
                WCAG 2.1 Level AAA
                <span className="ml-1 text-xs text-gray-500">(Enhanced)</span>
              </h4>
              <div className="space-y-2">
                <div className="flex items-center justify-between">
                  <span className="text-sm text-gray-700">Normal Text (7:1)</span>
                  <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                      result.aaa.normalText
                        ? 'bg-green-100 text-green-800'
                        : 'bg-gray-100 text-gray-600'
                    }`}
                  >
                    {result.aaa.normalText ? '✓ Pass' : '○ Not Met'}
                  </span>
                </div>
                <div className="flex items-center justify-between">
                  <span className="text-sm text-gray-700">Large Text (4.5:1)</span>
                  <span
                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                      result.aaa.largeText
                        ? 'bg-green-100 text-green-800'
                        : 'bg-gray-100 text-gray-600'
                    }`}
                  >
                    {result.aaa.largeText ? '✓ Pass' : '○ Not Met'}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Recommendations */}
          <div className="rounded-lg border-l-4 border-blue-500 bg-blue-50 p-4">
            <h4 className="mb-2 text-sm font-semibold text-blue-900">Recommendations</h4>
            <ul className="space-y-1 text-sm text-blue-800">
              {!result.aa.normalText && (
                <li>
                  • This combination does not meet WCAG 2.1 AA for normal text. Increase the
                  contrast to at least 4.5:1.
                </li>
              )}
              {result.aa.normalText && !result.aaa.normalText && (
                <li>
                  • This combination meets AA but not AAA for normal text. For enhanced
                  accessibility, aim for 7:1.
                </li>
              )}
              {result.aaa.normalText && (
                <li>• Excellent! This combination meets WCAG 2.1 AAA standards for all text.</li>
              )}
            </ul>
          </div>
        </div>
      )}

      {/* Quick Reference */}
      <div className="mt-6 rounded-lg bg-gray-50 p-4">
        <h4 className="mb-2 text-sm font-semibold text-gray-900">Quick Reference</h4>
        <div className="grid gap-2 text-xs text-gray-600 md:grid-cols-2">
          <div>
            <strong>Normal Text:</strong> Regular body text (less than 18pt or 14pt bold)
          </div>
          <div>
            <strong>Large Text:</strong> 18pt+ (24px+) or 14pt+ (18.66px+) bold
          </div>
          <div>
            <strong>AA Minimum:</strong> 4.5:1 normal, 3:1 large
          </div>
          <div>
            <strong>AAA Enhanced:</strong> 7:1 normal, 4.5:1 large
          </div>
        </div>
      </div>
    </div>
  );
}
