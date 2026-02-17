/**
 * Color Contrast Utilities
 * WCAG 2.1 AA Compliance Tools
 *
 * WCAG 2.1 AA Requirements:
 * - Normal text: 4.5:1 contrast ratio minimum
 * - Large text (18pt+ or 14pt+ bold): 3:1 contrast ratio minimum
 */

/**
 * Converts a hex color to RGB values
 */
export function hexToRgb(hex: string): { r: number; g: number; b: number } | null {
  // Remove # if present
  hex = hex.replace('#', '');

  // Parse hex values
  if (hex.length === 3) {
    hex = hex
      .split('')
      .map((char) => char + char)
      .join('');
  }

  const result = /^([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
  return result
    ? {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16),
      }
    : null;
}

/**
 * Calculates the relative luminance of a color
 * https://www.w3.org/TR/WCAG21/#dfn-relative-luminance
 */
export function getRelativeLuminance(rgb: { r: number; g: number; b: number }): number {
  // Convert RGB to sRGB
  const rsRGB = rgb.r / 255;
  const gsRGB = rgb.g / 255;
  const bsRGB = rgb.b / 255;

  // Apply gamma correction
  const r = rsRGB <= 0.03928 ? rsRGB / 12.92 : Math.pow((rsRGB + 0.055) / 1.055, 2.4);
  const g = gsRGB <= 0.03928 ? gsRGB / 12.92 : Math.pow((gsRGB + 0.055) / 1.055, 2.4);
  const b = bsRGB <= 0.03928 ? bsRGB / 12.92 : Math.pow((bsRGB + 0.055) / 1.055, 2.4);

  // Calculate relative luminance
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

/**
 * Calculates the contrast ratio between two colors
 * https://www.w3.org/TR/WCAG21/#dfn-contrast-ratio
 */
export function getContrastRatio(color1: string, color2: string): number {
  const rgb1 = hexToRgb(color1);
  const rgb2 = hexToRgb(color2);

  if (!rgb1 || !rgb2) {
    throw new Error('Invalid hex color provided');
  }

  const l1 = getRelativeLuminance(rgb1);
  const l2 = getRelativeLuminance(rgb2);

  // Ensure l1 is the lighter color
  const lighter = Math.max(l1, l2);
  const darker = Math.min(l1, l2);

  return (lighter + 0.05) / (darker + 0.05);
}

/**
 * Checks if a color combination meets WCAG 2.1 AA standards
 */
export function meetsWCAG_AA(
  foreground: string,
  background: string,
  options: {
    largeText?: boolean;
  } = {}
): {
  passes: boolean;
  ratio: number;
  required: number;
  level: 'AA' | 'AAA' | 'Fail';
} {
  const ratio = getContrastRatio(foreground, background);
  const required = options.largeText ? 3.0 : 4.5;
  const requiredAAA = options.largeText ? 4.5 : 7.0;

  return {
    passes: ratio >= required,
    ratio: Math.round(ratio * 100) / 100,
    required,
    level: ratio >= requiredAAA ? 'AAA' : ratio >= required ? 'AA' : 'Fail',
  };
}

/**
 * Checks if a color combination meets WCAG 2.1 AAA standards
 */
export function meetsWCAG_AAA(
  foreground: string,
  background: string,
  options: {
    largeText?: boolean;
  } = {}
): {
  passes: boolean;
  ratio: number;
  required: number;
} {
  const ratio = getContrastRatio(foreground, background);
  const required = options.largeText ? 4.5 : 7.0;

  return {
    passes: ratio >= required,
    ratio: Math.round(ratio * 100) / 100,
    required,
  };
}

/**
 * Converts Tailwind color classes to hex values
 * This is a utility for testing and auditing
 */
export const tailwindColors = {
  // Gray scale
  'gray-50': '#fafafa',
  'gray-100': '#f4f4f5',
  'gray-200': '#e4e4e7',
  'gray-300': '#d4d4d8',
  'gray-400': '#a1a1aa',
  'gray-500': '#71717a',
  'gray-600': '#52525b',
  'gray-700': '#3f3f46',
  'gray-800': '#27272a',
  'gray-900': '#18181b',

  // Primary (Sky Blue)
  'primary-50': '#f0f9ff',
  'primary-100': '#e0f2fe',
  'primary-200': '#bae6fd',
  'primary-300': '#7dd3fc',
  'primary-400': '#38bdf8',
  'primary-500': '#0ea5e9',
  'primary-600': '#0284c7',
  'primary-700': '#0369a1',
  'primary-800': '#075985',
  'primary-900': '#0c4a6e',
  'primary-950': '#082f49',

  // Status colors (from default Tailwind)
  'green-100': '#dcfce7',
  'green-600': '#16a34a',
  'green-800': '#166534',

  'yellow-100': '#fef9c3',
  'yellow-800': '#854d0e',

  'red-100': '#fee2e2',
  'red-800': '#991b1b',

  'blue-100': '#dbeafe',
  'blue-600': '#2563eb',
  'blue-800': '#1e40af',

  'purple-100': '#f3e8ff',
  'purple-600': '#9333ea',

  'pink-100': '#fce7f3',
  'pink-600': '#db2777',

  // Common colors
  white: '#ffffff',
  black: '#000000',
} as const;

/**
 * Audits a color combination and returns a report
 */
export function auditColorCombination(
  name: string,
  foreground: string,
  background: string,
  isLargeText: boolean = false
): {
  name: string;
  foreground: string;
  background: string;
  isLargeText: boolean;
  result: ReturnType<typeof meetsWCAG_AA>;
} {
  return {
    name,
    foreground,
    background,
    isLargeText,
    result: meetsWCAG_AA(foreground, background, { largeText: isLargeText }),
  };
}
