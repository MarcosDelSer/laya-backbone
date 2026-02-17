/**
 * Color Contrast Testing Page
 *
 * Development tool for testing and verifying color contrast ratios.
 * This page should only be accessible in development mode.
 *
 * Access: http://localhost:3000/dev/color-contrast
 */

import { ColorContrastChecker } from '@/components/ColorContrastChecker';
import Link from 'next/link';

export default function ColorContrastPage() {
  return (
    <main className="min-h-screen bg-gray-50 py-12">
      <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        {/* Header */}
        <div className="mb-8">
          <Link
            href="/"
            className="mb-4 inline-flex items-center text-sm text-primary-600 hover:text-primary-700"
          >
            <svg
              className="mr-2 h-4 w-4"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M10 19l-7-7m0 0l7-7m-7 7h18"
              />
            </svg>
            Back to Dashboard
          </Link>
          <h1 className="text-3xl font-bold text-gray-900">
            Color Contrast Testing Tool
          </h1>
          <p className="mt-2 text-gray-600">
            Verify color combinations meet WCAG 2.1 AA/AAA accessibility standards
          </p>
        </div>

        {/* Color Contrast Checker */}
        <ColorContrastChecker />

        {/* Current Application Colors */}
        <div className="mt-12">
          <h2 className="mb-6 text-2xl font-bold text-gray-900">
            Verified Application Colors
          </h2>
          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {/* Body Text */}
            <ColorCard
              title="Body Text"
              foreground="#18181b"
              background="#fafafa"
              description="Main body text on page background"
            />

            {/* Navigation */}
            <ColorCard
              title="Active Navigation"
              foreground="#0369a1"
              background="#f0f9ff"
              description="Active navigation link"
            />

            {/* Success Badge */}
            <ColorCard
              title="Success Badge"
              foreground="#166534"
              background="#dcfce7"
              description="Paid status, success messages"
            />

            {/* Warning Badge */}
            <ColorCard
              title="Warning Badge"
              foreground="#854d0e"
              background="#fef9c3"
              description="Pending status, warnings"
            />

            {/* Error Badge */}
            <ColorCard
              title="Error Badge"
              foreground="#991b1b"
              background="#fee2e2"
              description="Overdue status, errors"
            />

            {/* Primary Button */}
            <ColorCard
              title="Primary Button"
              foreground="#ffffff"
              background="#0284c7"
              description="Primary action buttons"
            />

            {/* Secondary Button */}
            <ColorCard
              title="Secondary Button"
              foreground="#3f3f46"
              background="#f4f4f5"
              description="Secondary action buttons"
            />

            {/* Subtle Text */}
            <ColorCard
              title="Subtle Text"
              foreground="#71717a"
              background="#ffffff"
              description="Timestamps, metadata"
            />

            {/* Section Title */}
            <ColorCard
              title="Section Title"
              foreground="#18181b"
              background="#ffffff"
              description="Large headings (18pt+)"
              isLargeText
            />
          </div>
        </div>

        {/* Documentation Link */}
        <div className="mt-12 rounded-lg border border-blue-200 bg-blue-50 p-6">
          <h3 className="mb-2 text-lg font-semibold text-blue-900">
            📚 Full Documentation
          </h3>
          <p className="mb-3 text-sm text-blue-800">
            For complete color contrast guidelines, implementation details, and best practices,
            see the full documentation.
          </p>
          <a
            href="/parent-portal/COLOR_CONTRAST_COMPLIANCE.md"
            className="text-sm font-medium text-blue-600 hover:text-blue-700"
          >
            View COLOR_CONTRAST_COMPLIANCE.md →
          </a>
        </div>
      </div>
    </main>
  );
}

interface ColorCardProps {
  title: string;
  foreground: string;
  background: string;
  description: string;
  isLargeText?: boolean;
}

function ColorCard({ title, foreground, background, description, isLargeText }: ColorCardProps) {
  // Calculate contrast ratio for display
  const getRatio = () => {
    try {
      const { getContrastRatio } = require('@/lib/color-contrast');
      return getContrastRatio(foreground, background).toFixed(2);
    } catch {
      return '—';
    }
  };

  return (
    <div className="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
      <h3 className="mb-2 text-sm font-semibold text-gray-900">{title}</h3>
      <div
        className="mb-3 rounded border border-gray-200 p-4"
        style={{ backgroundColor: background }}
      >
        <p
          className={isLargeText ? 'text-lg font-semibold' : 'text-sm'}
          style={{ color: foreground }}
        >
          Sample Text
        </p>
      </div>
      <div className="space-y-1 text-xs text-gray-600">
        <p>
          <strong>Ratio:</strong> {getRatio()}:1
        </p>
        <p>
          <strong>Type:</strong> {isLargeText ? 'Large text' : 'Normal text'}
        </p>
        <p className="text-gray-500">{description}</p>
      </div>
      <div className="mt-2 flex gap-2 text-xs">
        <span className="font-mono text-gray-600">{foreground}</span>
        <span className="text-gray-400">on</span>
        <span className="font-mono text-gray-600">{background}</span>
      </div>
    </div>
  );
}
