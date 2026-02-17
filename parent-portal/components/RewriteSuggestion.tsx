'use client';

import { useState } from 'react';
import type { RewriteSuggestion as RewriteSuggestionType } from '@/lib/types';

interface RewriteSuggestionProps {
  suggestion: RewriteSuggestionType;
  onApply?: (suggestion: RewriteSuggestionType) => void;
  onDismiss?: (suggestion: RewriteSuggestionType) => void;
  showConfidence?: boolean;
  variant?: 'default' | 'compact' | 'expanded';
}

/**
 * Format confidence score as a percentage string.
 */
function formatConfidence(score: number): string {
  return `${Math.round(score * 100)}%`;
}

/**
 * Get confidence level styling based on score.
 */
function getConfidenceStyle(score: number): { color: string; label: string } {
  if (score >= 0.9) {
    return { color: 'text-green-600', label: 'High confidence' };
  }
  if (score >= 0.7) {
    return { color: 'text-blue-600', label: 'Good confidence' };
  }
  if (score >= 0.5) {
    return { color: 'text-yellow-600', label: 'Moderate confidence' };
  }
  return { color: 'text-gray-500', label: 'Low confidence' };
}

/**
 * Badge component for feature indicators.
 */
function FeatureBadge({
  label,
  color,
  icon,
}: {
  label: string;
  color: 'green' | 'blue' | 'purple';
  icon: React.ReactNode;
}) {
  const colorClasses = {
    green: 'bg-green-100 text-green-700 border-green-200',
    blue: 'bg-blue-100 text-blue-700 border-blue-200',
    purple: 'bg-purple-100 text-purple-700 border-purple-200',
  };

  return (
    <span
      className={`inline-flex items-center space-x-1 text-xs px-2 py-0.5 rounded-full border ${colorClasses[color]}`}
    >
      {icon}
      <span>{label}</span>
    </span>
  );
}

/**
 * Text comparison component showing original vs suggested text.
 */
function TextComparison({
  originalText,
  suggestedText,
  variant,
}: {
  originalText: string;
  suggestedText: string;
  variant: 'default' | 'compact' | 'expanded';
}) {
  if (variant === 'compact') {
    return (
      <div className="space-y-1">
        <p className="text-sm text-gray-600 line-through">{originalText}</p>
        <p className="text-sm text-gray-900 font-medium">{suggestedText}</p>
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <div className="rounded-lg bg-red-50 border border-red-100 p-3">
        <div className="flex items-center space-x-2 mb-1">
          <svg
            className="h-4 w-4 text-red-500"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path
              fillRule="evenodd"
              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
              clipRule="evenodd"
            />
          </svg>
          <span className="text-xs font-medium text-red-600">Original</span>
        </div>
        <p className="text-sm text-red-800 line-through">{originalText}</p>
      </div>

      <div className="flex justify-center">
        <svg
          className="h-5 w-5 text-gray-400"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 14l-7 7m0 0l-7-7m7 7V3"
          />
        </svg>
      </div>

      <div className="rounded-lg bg-green-50 border border-green-100 p-3">
        <div className="flex items-center space-x-2 mb-1">
          <svg
            className="h-4 w-4 text-green-500"
            fill="currentColor"
            viewBox="0 0 20 20"
          >
            <path
              fillRule="evenodd"
              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
              clipRule="evenodd"
            />
          </svg>
          <span className="text-xs font-medium text-green-600">Suggested</span>
        </div>
        <p className="text-sm text-green-800">{suggestedText}</p>
      </div>
    </div>
  );
}

/**
 * Action buttons for apply/dismiss actions.
 */
function ActionButtons({
  onApply,
  onDismiss,
  isApplying,
  variant,
}: {
  onApply?: () => void;
  onDismiss?: () => void;
  isApplying: boolean;
  variant: 'default' | 'compact' | 'expanded';
}) {
  if (!onApply && !onDismiss) {
    return null;
  }

  const buttonSizeClass = variant === 'compact' ? 'py-1.5 px-3 text-xs' : 'py-2 px-4 text-sm';

  return (
    <div className={`flex items-center space-x-2 ${variant === 'expanded' ? 'mt-4' : 'mt-3'}`}>
      {onApply && (
        <button
          type="button"
          onClick={onApply}
          disabled={isApplying}
          className={`flex-1 flex items-center justify-center space-x-2 rounded-lg bg-green-600 font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${buttonSizeClass}`}
        >
          {isApplying ? (
            <svg
              className="h-4 w-4 animate-spin"
              fill="none"
              viewBox="0 0 24 24"
            >
              <circle
                className="opacity-25"
                cx="12"
                cy="12"
                r="10"
                stroke="currentColor"
                strokeWidth="4"
              />
              <path
                className="opacity-75"
                fill="currentColor"
                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
              />
            </svg>
          ) : (
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 13l4 4L19 7"
              />
            </svg>
          )}
          <span>{isApplying ? 'Applying...' : 'Apply'}</span>
        </button>
      )}
      {onDismiss && (
        <button
          type="button"
          onClick={onDismiss}
          disabled={isApplying}
          className={`flex items-center justify-center space-x-2 rounded-lg border border-gray-300 bg-white font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed ${buttonSizeClass} ${onApply ? '' : 'flex-1'}`}
        >
          <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M6 18L18 6M6 6l12 12"
            />
          </svg>
          <span>Dismiss</span>
        </button>
      )}
    </div>
  );
}

/**
 * RewriteSuggestion component displaying suggested rewrites with apply/dismiss actions.
 * Implements Quebec 'Bonne Message' standards with 'I' language and sandwich method.
 */
export function RewriteSuggestion({
  suggestion,
  onApply,
  onDismiss,
  showConfidence = false,
  variant = 'default',
}: RewriteSuggestionProps) {
  const [isApplying, setIsApplying] = useState(false);

  const handleApply = async () => {
    if (!onApply) return;
    setIsApplying(true);
    try {
      await onApply(suggestion);
    } finally {
      setIsApplying(false);
    }
  };

  const handleDismiss = () => {
    onDismiss?.(suggestion);
  };

  const confidenceStyle = getConfidenceStyle(suggestion.confidenceScore);

  // Compact variant for inline/minimal display
  if (variant === 'compact') {
    return (
      <div className="rounded-lg border border-green-200 bg-green-50 p-3">
        <div className="flex items-start justify-between mb-2">
          <div className="flex items-center space-x-2">
            <svg
              className="h-4 w-4 text-green-600"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
              />
            </svg>
            <span className="text-xs font-medium text-green-700">Suggestion</span>
          </div>
          {onDismiss && !onApply && (
            <button
              type="button"
              onClick={handleDismiss}
              className="p-1 text-gray-400 hover:text-gray-600 focus:outline-none"
              title="Dismiss suggestion"
            >
              <svg className="h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                <path
                  fillRule="evenodd"
                  d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                  clipRule="evenodd"
                />
              </svg>
            </button>
          )}
        </div>
        <TextComparison
          originalText={suggestion.originalText}
          suggestedText={suggestion.suggestedText}
          variant="compact"
        />
        <ActionButtons
          onApply={onApply ? handleApply : undefined}
          onDismiss={onDismiss ? handleDismiss : undefined}
          isApplying={isApplying}
          variant="compact"
        />
      </div>
    );
  }

  // Default and expanded variants
  return (
    <div className="rounded-lg border border-gray-200 bg-white shadow-sm overflow-hidden">
      {/* Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b border-gray-100 bg-gradient-to-r from-green-50 to-blue-50">
        <div className="flex items-center space-x-2">
          <svg
            className="h-5 w-5 text-green-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"
            />
          </svg>
          <span className="text-sm font-semibold text-gray-900">
            Suggested Rewrite
          </span>
        </div>
        {onDismiss && (
          <button
            type="button"
            onClick={handleDismiss}
            disabled={isApplying}
            className="p-1 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary rounded disabled:opacity-50"
            title="Dismiss suggestion"
          >
            <svg className="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
              <path
                fillRule="evenodd"
                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                clipRule="evenodd"
              />
            </svg>
          </button>
        )}
      </div>

      {/* Content */}
      <div className="p-4">
        {/* Feature badges */}
        <div className="flex items-center flex-wrap gap-2 mb-4">
          {suggestion.usesILanguage && (
            <FeatureBadge
              label="I-Language"
              color="green"
              icon={
                <svg className="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                  <path
                    fillRule="evenodd"
                    d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"
                    clipRule="evenodd"
                  />
                </svg>
              }
            />
          )}
          {suggestion.hasSandwichStructure && (
            <FeatureBadge
              label="Sandwich Method"
              color="blue"
              icon={
                <svg className="h-3 w-3" fill="currentColor" viewBox="0 0 20 20">
                  <path
                    fillRule="evenodd"
                    d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z"
                    clipRule="evenodd"
                  />
                </svg>
              }
            />
          )}
          {showConfidence && (
            <span className={`text-xs ${confidenceStyle.color}`}>
              {confidenceStyle.label} ({formatConfidence(suggestion.confidenceScore)})
            </span>
          )}
        </div>

        {/* Text comparison */}
        <TextComparison
          originalText={suggestion.originalText}
          suggestedText={suggestion.suggestedText}
          variant={variant}
        />

        {/* Explanation */}
        {suggestion.explanation && (
          <div className="mt-4 rounded-lg bg-gray-50 border border-gray-100 p-3">
            <div className="flex items-start space-x-2">
              <svg
                className="h-4 w-4 text-gray-500 flex-shrink-0 mt-0.5"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              <div>
                <p className="text-xs font-medium text-gray-700 mb-1">
                  Why this is better:
                </p>
                <p className="text-sm text-gray-600">{suggestion.explanation}</p>
              </div>
            </div>
          </div>
        )}

        {/* Action buttons */}
        <ActionButtons
          onApply={onApply ? handleApply : undefined}
          onDismiss={onDismiss && variant === 'expanded' ? handleDismiss : undefined}
          isApplying={isApplying}
          variant={variant}
        />
      </div>
    </div>
  );
}

export default RewriteSuggestion;
