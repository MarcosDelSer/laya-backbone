/**
 * Color Contrast Tests
 * WCAG 2.1 AA Compliance Verification
 *
 * These tests ensure all color combinations in the app meet WCAG 2.1 AA standards:
 * - Normal text: 4.5:1 contrast ratio minimum
 * - Large text (18pt+ or 14pt+ bold): 3:1 contrast ratio minimum
 */

import { describe, it, expect } from 'vitest';
import {
  getContrastRatio,
  meetsWCAG_AA,
  hexToRgb,
  getRelativeLuminance,
  tailwindColors,
} from '../lib/color-contrast';

describe('Color Contrast Utilities', () => {
  describe('hexToRgb', () => {
    it('should convert 6-digit hex to RGB', () => {
      expect(hexToRgb('#ffffff')).toEqual({ r: 255, g: 255, b: 255 });
      expect(hexToRgb('#000000')).toEqual({ r: 0, g: 0, b: 0 });
      expect(hexToRgb('ff0000')).toEqual({ r: 255, g: 0, b: 0 });
    });

    it('should convert 3-digit hex to RGB', () => {
      expect(hexToRgb('#fff')).toEqual({ r: 255, g: 255, b: 255 });
      expect(hexToRgb('#000')).toEqual({ r: 0, g: 0, b: 0 });
      expect(hexToRgb('f00')).toEqual({ r: 255, g: 0, b: 0 });
    });

    it('should return null for invalid hex', () => {
      expect(hexToRgb('invalid')).toBeNull();
      expect(hexToRgb('#gg0000')).toBeNull();
    });
  });

  describe('getRelativeLuminance', () => {
    it('should calculate luminance for white', () => {
      const luminance = getRelativeLuminance({ r: 255, g: 255, b: 255 });
      expect(luminance).toBeCloseTo(1.0, 1);
    });

    it('should calculate luminance for black', () => {
      const luminance = getRelativeLuminance({ r: 0, g: 0, b: 0 });
      expect(luminance).toBe(0);
    });
  });

  describe('getContrastRatio', () => {
    it('should calculate 21:1 for black on white', () => {
      const ratio = getContrastRatio('#000000', '#ffffff');
      expect(ratio).toBeCloseTo(21, 0);
    });

    it('should calculate the same ratio regardless of order', () => {
      const ratio1 = getContrastRatio('#000000', '#ffffff');
      const ratio2 = getContrastRatio('#ffffff', '#000000');
      expect(ratio1).toBe(ratio2);
    });
  });

  describe('meetsWCAG_AA', () => {
    it('should pass for high contrast normal text', () => {
      const result = meetsWCAG_AA('#000000', '#ffffff');
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should pass for high contrast large text', () => {
      const result = meetsWCAG_AA('#000000', '#ffffff', { largeText: true });
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(3.0);
    });

    it('should fail for low contrast', () => {
      const result = meetsWCAG_AA('#cccccc', '#ffffff');
      expect(result.passes).toBe(false);
    });
  });
});

describe('WCAG 2.1 AA Compliance - Application Colors', () => {
  describe('Body and Main Text', () => {
    it('should meet contrast for body text on gray-50 background', () => {
      const result = meetsWCAG_AA(tailwindColors['gray-900'], tailwindColors['gray-50']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for card text on white background', () => {
      const result = meetsWCAG_AA(tailwindColors['gray-900'], tailwindColors.white);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });
  });

  describe('Navigation', () => {
    it('should meet contrast for inactive navigation links', () => {
      const result = meetsWCAG_AA(tailwindColors['gray-600'], tailwindColors.white);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for active navigation links', () => {
      const result = meetsWCAG_AA(tailwindColors['primary-700'], tailwindColors['primary-50']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for mobile navigation inactive', () => {
      const result = meetsWCAG_AA(tailwindColors['gray-500'], tailwindColors.white);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for mobile navigation active', () => {
      const result = meetsWCAG_AA(tailwindColors['primary-600'], tailwindColors.white);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for logo text (large text)', () => {
      const result = meetsWCAG_AA(tailwindColors.white, tailwindColors['primary-600'], {
        largeText: true,
      });
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(3.0);
    });
  });

  describe('Status Badges', () => {
    it('should meet contrast for success badge', () => {
      const result = meetsWCAG_AA(tailwindColors['green-800'], tailwindColors['green-100']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for warning badge', () => {
      const result = meetsWCAG_AA(tailwindColors['yellow-800'], tailwindColors['yellow-100']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for error badge', () => {
      const result = meetsWCAG_AA(tailwindColors['red-800'], tailwindColors['red-100']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for info badge', () => {
      const result = meetsWCAG_AA(tailwindColors['blue-800'], tailwindColors['blue-100']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for neutral badge', () => {
      const result = meetsWCAG_AA(tailwindColors['gray-800'], tailwindColors['gray-100']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });
  });

  describe('Buttons', () => {
    it('should meet contrast for primary button', () => {
      const result = meetsWCAG_AA(tailwindColors.white, tailwindColors['primary-600']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for secondary button', () => {
      const result = meetsWCAG_AA(tailwindColors['gray-700'], tailwindColors['gray-100']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for outline button', () => {
      const result = meetsWCAG_AA(tailwindColors['gray-700'], tailwindColors.white);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });
  });

  describe('Section Headings', () => {
    it('should meet contrast for section title (large text)', () => {
      const result = meetsWCAG_AA(tailwindColors['gray-900'], tailwindColors.white, {
        largeText: true,
      });
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(3.0);
    });

    it('should meet contrast for section subtitle', () => {
      const result = meetsWCAG_AA(tailwindColors['gray-500'], tailwindColors.white);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });
  });

  describe('Dashboard Stat Cards', () => {
    it('should meet contrast for green stat icon', () => {
      const result = meetsWCAG_AA(tailwindColors['green-600'], tailwindColors['green-100']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for blue stat icon', () => {
      const result = meetsWCAG_AA(tailwindColors['blue-600'], tailwindColors['blue-100']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for purple stat icon', () => {
      const result = meetsWCAG_AA(tailwindColors['purple-600'], tailwindColors['purple-100']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });

    it('should meet contrast for pink stat icon', () => {
      const result = meetsWCAG_AA(tailwindColors['pink-600'], tailwindColors['pink-100']);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });
  });

  describe('Focus Indicators', () => {
    it('should meet contrast for focus indicator on white', () => {
      const result = meetsWCAG_AA(tailwindColors['primary-500'], tailwindColors.white);
      expect(result.passes).toBe(true);
      expect(result.ratio).toBeGreaterThanOrEqual(4.5);
    });
  });
});

describe('Color Contrast Audit Summary', () => {
  it('should provide a complete audit report', () => {
    const combinations = [
      { name: 'Body text', fg: tailwindColors['gray-900'], bg: tailwindColors['gray-50'], large: false },
      { name: 'Card text', fg: tailwindColors['gray-900'], bg: tailwindColors.white, large: false },
      { name: 'Nav inactive', fg: tailwindColors['gray-600'], bg: tailwindColors.white, large: false },
      { name: 'Nav active', fg: tailwindColors['primary-700'], bg: tailwindColors['primary-50'], large: false },
      { name: 'Badge success', fg: tailwindColors['green-800'], bg: tailwindColors['green-100'], large: false },
      { name: 'Badge warning', fg: tailwindColors['yellow-800'], bg: tailwindColors['yellow-100'], large: false },
      { name: 'Badge error', fg: tailwindColors['red-800'], bg: tailwindColors['red-100'], large: false },
      { name: 'Badge info', fg: tailwindColors['blue-800'], bg: tailwindColors['blue-100'], large: false },
      { name: 'Primary button', fg: tailwindColors.white, bg: tailwindColors['primary-600'], large: false },
    ];

    const results = combinations.map((combo) => ({
      ...combo,
      result: meetsWCAG_AA(combo.fg, combo.bg, { largeText: combo.large }),
    }));

    const failures = results.filter((r) => !r.result.passes);

    console.log('\n' + '='.repeat(60));
    console.log('COLOR CONTRAST AUDIT SUMMARY');
    console.log('='.repeat(60));
    console.log(`Total combinations tested: ${results.length}`);
    console.log(`Passed: ${results.length - failures.length} ✅`);
    console.log(`Failed: ${failures.length} ${failures.length > 0 ? '❌' : '✅'}`);
    console.log('='.repeat(60) + '\n');

    expect(failures.length).toBe(0);
  });
});
