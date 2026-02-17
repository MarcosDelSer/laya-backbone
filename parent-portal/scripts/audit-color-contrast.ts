/**
 * Color Contrast Audit Script
 *
 * This script audits all color combinations used in the LAYA Parent Portal
 * to ensure WCAG 2.1 AA compliance (4.5:1 for normal text, 3:1 for large text).
 *
 * Run with: npx tsx scripts/audit-color-contrast.ts
 */

import {
  auditColorCombination,
  tailwindColors,
  meetsWCAG_AA,
} from '../lib/color-contrast';

interface ColorCombination {
  name: string;
  foreground: string;
  background: string;
  isLargeText: boolean;
  location: string;
}

// Define all color combinations used in the application
const colorCombinations: ColorCombination[] = [
  // Body and main text
  {
    name: 'Body text',
    foreground: tailwindColors['gray-900'],
    background: tailwindColors['gray-50'],
    isLargeText: false,
    location: 'globals.css - body',
  },
  {
    name: 'Card text on white',
    foreground: tailwindColors['gray-900'],
    background: tailwindColors.white,
    isLargeText: false,
    location: 'All card components',
  },

  // Navigation
  {
    name: 'Navigation - inactive',
    foreground: tailwindColors['gray-600'],
    background: tailwindColors.white,
    isLargeText: false,
    location: 'Navigation.tsx - inactive links',
  },
  {
    name: 'Navigation - active',
    foreground: tailwindColors['primary-700'],
    background: tailwindColors['primary-50'],
    isLargeText: false,
    location: 'Navigation.tsx - active links',
  },
  {
    name: 'Navigation - mobile inactive',
    foreground: tailwindColors['gray-500'],
    background: tailwindColors.white,
    isLargeText: false,
    location: 'Navigation.tsx - mobile inactive',
  },
  {
    name: 'Navigation - mobile active',
    foreground: tailwindColors['primary-600'],
    background: tailwindColors.white,
    isLargeText: false,
    location: 'Navigation.tsx - mobile active',
  },
  {
    name: 'Logo text',
    foreground: tailwindColors.white,
    background: tailwindColors['primary-600'],
    isLargeText: true,
    location: 'Navigation.tsx - logo',
  },

  // Badges - Success
  {
    name: 'Badge Success',
    foreground: tailwindColors['green-800'],
    background: tailwindColors['green-100'],
    isLargeText: false,
    location: 'globals.css - .badge-success',
  },

  // Badges - Warning
  {
    name: 'Badge Warning',
    foreground: tailwindColors['yellow-800'],
    background: tailwindColors['yellow-100'],
    isLargeText: false,
    location: 'globals.css - .badge-warning',
  },

  // Badges - Error
  {
    name: 'Badge Error',
    foreground: tailwindColors['red-800'],
    background: tailwindColors['red-100'],
    isLargeText: false,
    location: 'globals.css - .badge-error',
  },

  // Badges - Info
  {
    name: 'Badge Info',
    foreground: tailwindColors['blue-800'],
    background: tailwindColors['blue-100'],
    isLargeText: false,
    location: 'globals.css - .badge-info',
  },

  // Badges - Neutral
  {
    name: 'Badge Neutral',
    foreground: tailwindColors['gray-800'],
    background: tailwindColors['gray-100'],
    isLargeText: false,
    location: 'globals.css - .badge-neutral',
  },

  // Buttons
  {
    name: 'Primary Button',
    foreground: tailwindColors.white,
    background: tailwindColors['primary-600'],
    isLargeText: false,
    location: 'globals.css - .btn-primary',
  },
  {
    name: 'Secondary Button',
    foreground: tailwindColors['gray-700'],
    background: tailwindColors['gray-100'],
    isLargeText: false,
    location: 'globals.css - .btn-secondary',
  },
  {
    name: 'Outline Button',
    foreground: tailwindColors['gray-700'],
    background: tailwindColors.white,
    isLargeText: false,
    location: 'globals.css - .btn-outline',
  },

  // Section titles
  {
    name: 'Section Title',
    foreground: tailwindColors['gray-900'],
    background: tailwindColors.white,
    isLargeText: true,
    location: 'globals.css - .section-title',
  },
  {
    name: 'Section Subtitle',
    foreground: tailwindColors['gray-500'],
    background: tailwindColors.white,
    isLargeText: false,
    location: 'globals.css - .section-subtitle',
  },

  // Dashboard stat cards
  {
    name: 'Stat Icon - Green',
    foreground: tailwindColors['green-600'],
    background: tailwindColors['green-100'],
    isLargeText: false,
    location: 'page.tsx - StatIcon green',
  },
  {
    name: 'Stat Icon - Blue',
    foreground: tailwindColors['blue-600'],
    background: tailwindColors['blue-100'],
    isLargeText: false,
    location: 'page.tsx - StatIcon blue',
  },
  {
    name: 'Stat Icon - Purple',
    foreground: tailwindColors['purple-600'],
    background: tailwindColors['purple-100'],
    isLargeText: false,
    location: 'page.tsx - StatIcon purple',
  },
  {
    name: 'Stat Icon - Pink',
    foreground: tailwindColors['pink-600'],
    background: tailwindColors['pink-100'],
    isLargeText: false,
    location: 'page.tsx - StatIcon pink',
  },

  // Focus indicators
  {
    name: 'Focus Indicator',
    foreground: tailwindColors['primary-500'],
    background: tailwindColors.white,
    isLargeText: false,
    location: 'globals.css - :focus-visible',
  },
];

// Run the audit
console.log('\n='.repeat(80));
console.log('COLOR CONTRAST AUDIT - WCAG 2.1 AA COMPLIANCE');
console.log('='.repeat(80));
console.log('\nStandards:');
console.log('  - Normal text: 4.5:1 minimum');
console.log('  - Large text (18pt+ or 14pt+ bold): 3:1 minimum');
console.log('  - AAA (enhanced): 7:1 for normal, 4.5:1 for large');
console.log('\n' + '='.repeat(80) + '\n');

let totalTests = 0;
let passedTests = 0;
let failedTests = 0;
const failures: any[] = [];

colorCombinations.forEach((combo) => {
  totalTests++;
  const audit = auditColorCombination(
    combo.name,
    combo.foreground,
    combo.background,
    combo.isLargeText
  );

  const status = audit.result.passes ? '✅ PASS' : '❌ FAIL';
  const textSize = combo.isLargeText ? 'Large' : 'Normal';

  console.log(`${status} ${audit.name}`);
  console.log(`  Foreground: ${combo.foreground}`);
  console.log(`  Background: ${combo.background}`);
  console.log(`  Text Size: ${textSize}`);
  console.log(`  Ratio: ${audit.result.ratio}:1 (Required: ${audit.result.required}:1)`);
  console.log(`  Level: ${audit.result.level}`);
  console.log(`  Location: ${combo.location}`);
  console.log('');

  if (audit.result.passes) {
    passedTests++;
  } else {
    failedTests++;
    failures.push(audit);
  }
});

console.log('='.repeat(80));
console.log('SUMMARY');
console.log('='.repeat(80));
console.log(`Total Tests: ${totalTests}`);
console.log(`Passed: ${passedTests} ✅`);
console.log(`Failed: ${failedTests} ${failedTests > 0 ? '❌' : '✅'}`);
console.log(`Pass Rate: ${Math.round((passedTests / totalTests) * 100)}%`);

if (failedTests > 0) {
  console.log('\n' + '='.repeat(80));
  console.log('FAILURES - ACTION REQUIRED');
  console.log('='.repeat(80));
  failures.forEach((failure) => {
    console.log(`\n❌ ${failure.name}`);
    console.log(`   Location: ${colorCombinations.find((c) => c.name === failure.name)?.location}`);
    console.log(`   Current Ratio: ${failure.result.ratio}:1`);
    console.log(`   Required: ${failure.result.required}:1`);
    console.log(`   Gap: ${(failure.result.required - failure.result.ratio).toFixed(2)}`);
  });
}

console.log('\n' + '='.repeat(80));
console.log('WCAG 2.1 AA COLOR CONTRAST COMPLIANCE: ' + (failedTests === 0 ? '✅ ACHIEVED' : '❌ NOT MET'));
console.log('='.repeat(80) + '\n');

// Exit with error code if there are failures
if (failedTests > 0) {
  process.exit(1);
}
