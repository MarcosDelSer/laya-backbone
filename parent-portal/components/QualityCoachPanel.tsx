'use client';

import { useMemo } from 'react';
import type {
  MessageAnalysisResponse,
  QualityIssueDetail,
  IssueSeverity,
  RewriteSuggestion,
} from '@/lib/types';

interface QualityCoachPanelProps {
  analysis: MessageAnalysisResponse | null;
  isLoading?: boolean;
  onApplyRewrite?: (suggestion: RewriteSuggestion) => void;
  onDismissIssue?: (issue: QualityIssueDetail) => void;
  collapsed?: boolean;
  onToggleCollapse?: () => void;
}

// Severity configuration for visual indicators
const severityConfig: Record<
  IssueSeverity,
  { color: string; bgColor: string; label: string; icon: React.ReactNode }
> = {
  critical: {
    color: 'text-red-700',
    bgColor: 'bg-red-50 border-red-200',
    label: 'Critical',
    icon: (
      <svg className="h-4 w-4 text-red-600" fill="currentColor" viewBox="0 0 20 20">
        <path
          fillRule="evenodd"
          d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
          clipRule="evenodd"
        />
      </svg>
    ),
  },
  high: {
    color: 'text-orange-700',
    bgColor: 'bg-orange-50 border-orange-200',
    label: 'High',
    icon: (
      <svg className="h-4 w-4 text-orange-600" fill="currentColor" viewBox="0 0 20 20">
        <path
          fillRule="evenodd"
          d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
          clipRule="evenodd"
        />
      </svg>
    ),
  },
  medium: {
    color: 'text-yellow-700',
    bgColor: 'bg-yellow-50 border-yellow-200',
    label: 'Medium',
    icon: (
      <svg className="h-4 w-4 text-yellow-600" fill="currentColor" viewBox="0 0 20 20">
        <path
          fillRule="evenodd"
          d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z"
          clipRule="evenodd"
        />
      </svg>
    ),
  },
  low: {
    color: 'text-blue-700',
    bgColor: 'bg-blue-50 border-blue-200',
    label: 'Low',
    icon: (
      <svg className="h-4 w-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
        <path
          fillRule="evenodd"
          d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
          clipRule="evenodd"
        />
      </svg>
    ),
  },
};

// Quality score color configuration
function getScoreColor(score: number): string {
  if (score >= 80) return 'text-green-600';
  if (score >= 60) return 'text-yellow-600';
  if (score >= 40) return 'text-orange-600';
  return 'text-red-600';
}

function getScoreBackgroundColor(score: number): string {
  if (score >= 80) return 'bg-green-100';
  if (score >= 60) return 'bg-yellow-100';
  if (score >= 40) return 'bg-orange-100';
  return 'bg-red-100';
}

function getScoreLabel(score: number): string {
  if (score >= 80) return 'Excellent';
  if (score >= 60) return 'Good';
  if (score >= 40) return 'Needs Improvement';
  return 'Requires Revision';
}

// Issue type labels for display
const issueTypeLabels: Record<string, string> = {
  accusatory_you: 'Accusatory Language',
  judgmental_label: 'Judgmental Label',
  blame_shame: 'Blame/Shame Pattern',
  exaggeration: 'Exaggeration',
  alarmist: 'Alarmist Language',
  comparison: 'Comparison',
  negative_tone: 'Negative Tone',
  missing_positive: 'Missing Positive Opening',
  missing_solution: 'Missing Solution Focus',
  multiple_objectives: 'Multiple Objectives',
};

function SectionHeader({ title, count }: { title: string; count?: number }) {
  return (
    <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-3">
      <h4 className="text-sm font-medium text-gray-900">{title}</h4>
      {count !== undefined && count > 0 && (
        <span className="text-xs text-gray-500">
          {count} {count === 1 ? 'issue' : 'issues'}
        </span>
      )}
    </div>
  );
}

function LoadingState() {
  return (
    <div className="flex items-center justify-center py-6">
      <div className="flex items-center space-x-3">
        <svg
          className="h-5 w-5 animate-spin text-primary"
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
        <span className="text-sm text-gray-600">Analyzing message...</span>
      </div>
    </div>
  );
}

function EmptyState() {
  return (
    <div className="flex flex-col items-center justify-center py-6 text-center">
      <svg
        className="h-10 w-10 text-gray-300 mb-2"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={1.5}
          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
        />
      </svg>
      <p className="text-sm text-gray-500">
        Start typing to see quality analysis
      </p>
    </div>
  );
}

function QualityScoreIndicator({ score }: { score: number }) {
  const scoreColor = getScoreColor(score);
  const scoreBgColor = getScoreBackgroundColor(score);
  const scoreLabel = getScoreLabel(score);

  return (
    <div className="flex items-center space-x-3">
      <div
        className={`flex h-14 w-14 items-center justify-center rounded-full ${scoreBgColor}`}
      >
        <span className={`text-xl font-bold ${scoreColor}`}>{score}</span>
      </div>
      <div>
        <p className={`text-sm font-medium ${scoreColor}`}>{scoreLabel}</p>
        <p className="text-xs text-gray-500">Quality Score</p>
      </div>
    </div>
  );
}

function QualityCheckIndicator({
  label,
  passed,
}: {
  label: string;
  passed: boolean;
}) {
  return (
    <div className="flex items-center space-x-2">
      {passed ? (
        <svg className="h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
          <path
            fillRule="evenodd"
            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
            clipRule="evenodd"
          />
        </svg>
      ) : (
        <svg className="h-4 w-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
          <path
            fillRule="evenodd"
            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
            clipRule="evenodd"
          />
        </svg>
      )}
      <span className={`text-xs ${passed ? 'text-green-700' : 'text-gray-500'}`}>
        {label}
      </span>
    </div>
  );
}

function IssueItem({
  issue,
  onDismiss,
}: {
  issue: QualityIssueDetail;
  onDismiss?: () => void;
}) {
  const config = severityConfig[issue.severity];
  const issueLabel = issueTypeLabels[issue.issueType] || issue.issueType;

  return (
    <div className={`rounded-lg border p-3 ${config.bgColor}`}>
      <div className="flex items-start justify-between">
        <div className="flex items-start space-x-2">
          <span className="flex-shrink-0 mt-0.5">{config.icon}</span>
          <div className="flex-1 min-w-0">
            <div className="flex items-center space-x-2">
              <span className={`text-xs font-medium ${config.color}`}>
                {config.label}
              </span>
              <span className="text-xs text-gray-400">|</span>
              <span className="text-xs text-gray-600">{issueLabel}</span>
            </div>
            <p className="text-sm text-gray-700 mt-1">{issue.description}</p>
            {issue.originalText && (
              <p className="text-xs text-gray-500 mt-1 italic">
                &ldquo;{issue.originalText}&rdquo;
              </p>
            )}
            {issue.suggestion && (
              <div className="mt-2 flex items-start space-x-2">
                <svg
                  className="h-4 w-4 text-green-600 flex-shrink-0 mt-0.5"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M9 5l7 7-7 7"
                  />
                </svg>
                <p className="text-xs text-green-700">{issue.suggestion}</p>
              </div>
            )}
          </div>
        </div>
        {onDismiss && (
          <button
            type="button"
            onClick={onDismiss}
            className="flex-shrink-0 ml-2 p-1 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary rounded"
            title="Dismiss issue"
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
    </div>
  );
}

function RewriteSuggestionCard({
  suggestion,
  onApply,
}: {
  suggestion: RewriteSuggestion;
  onApply?: () => void;
}) {
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
          <span className="text-xs font-medium text-green-700">
            Suggested Rewrite
          </span>
        </div>
        <div className="flex items-center space-x-1">
          {suggestion.usesILanguage && (
            <span className="text-xs bg-green-100 text-green-700 px-1.5 py-0.5 rounded">
              I-language
            </span>
          )}
          {suggestion.hasSandwichStructure && (
            <span className="text-xs bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded">
              Sandwich
            </span>
          )}
        </div>
      </div>

      <div className="space-y-2">
        <div>
          <p className="text-xs text-gray-500 mb-1">Original:</p>
          <p className="text-sm text-gray-600 line-through">
            {suggestion.originalText}
          </p>
        </div>
        <div>
          <p className="text-xs text-gray-500 mb-1">Suggested:</p>
          <p className="text-sm text-gray-900">{suggestion.suggestedText}</p>
        </div>
        {suggestion.explanation && (
          <p className="text-xs text-green-700 italic mt-2">
            {suggestion.explanation}
          </p>
        )}
      </div>

      {onApply && (
        <button
          type="button"
          onClick={onApply}
          className="mt-3 w-full flex items-center justify-center space-x-2 rounded-lg bg-green-600 px-3 py-2 text-sm font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors"
        >
          <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M5 13l4 4L19 7"
            />
          </svg>
          <span>Apply Suggestion</span>
        </button>
      )}
    </div>
  );
}

export function QualityCoachPanel({
  analysis,
  isLoading = false,
  onApplyRewrite,
  onDismissIssue,
  collapsed = false,
  onToggleCollapse,
}: QualityCoachPanelProps) {
  // Sort issues by severity (critical first, then high, medium, low)
  const sortedIssues = useMemo(() => {
    if (!analysis?.issues) return [];
    const severityOrder: Record<IssueSeverity, number> = {
      critical: 0,
      high: 1,
      medium: 2,
      low: 3,
    };
    return [...analysis.issues].sort(
      (a, b) => severityOrder[a.severity] - severityOrder[b.severity]
    );
  }, [analysis?.issues]);

  // Count issues by severity
  const issueCounts = useMemo(() => {
    return sortedIssues.reduce(
      (acc, issue) => {
        acc[issue.severity] = (acc[issue.severity] || 0) + 1;
        return acc;
      },
      {} as Record<IssueSeverity, number>
    );
  }, [sortedIssues]);

  const hasIssues = sortedIssues.length > 0;
  const hasRewrites =
    analysis?.rewriteSuggestions && analysis.rewriteSuggestions.length > 0;

  return (
    <div className="bg-white rounded-lg border border-gray-200 shadow-sm">
      {/* Panel Header */}
      <div className="flex items-center justify-between px-4 py-3 border-b border-gray-200">
        <div className="flex items-center space-x-2">
          <svg
            className="h-5 w-5 text-primary"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"
            />
          </svg>
          <h3 className="text-sm font-semibold text-gray-900">Quality Coach</h3>
          {analysis && !isLoading && (
            <span
              className={`text-xs px-2 py-0.5 rounded-full ${
                analysis.isAcceptable
                  ? 'bg-green-100 text-green-700'
                  : 'bg-yellow-100 text-yellow-700'
              }`}
            >
              {analysis.isAcceptable ? 'Ready to send' : 'Review suggested'}
            </span>
          )}
        </div>
        {onToggleCollapse && (
          <button
            type="button"
            onClick={onToggleCollapse}
            className="p-1 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary rounded"
            title={collapsed ? 'Expand panel' : 'Collapse panel'}
          >
            <svg
              className={`h-5 w-5 transition-transform ${
                collapsed ? 'rotate-180' : ''
              }`}
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 15l7-7 7 7"
              />
            </svg>
          </button>
        )}
      </div>

      {/* Panel Content */}
      {!collapsed && (
        <div className="p-4">
          {isLoading ? (
            <LoadingState />
          ) : !analysis ? (
            <EmptyState />
          ) : (
            <div className="space-y-4">
              {/* Quality Score Section */}
              <div className="flex items-start justify-between">
                <QualityScoreIndicator score={analysis.qualityScore} />
                <div className="flex flex-col space-y-1">
                  <QualityCheckIndicator
                    label="Positive Opening"
                    passed={analysis.hasPositiveOpening}
                  />
                  <QualityCheckIndicator
                    label="Factual Basis"
                    passed={analysis.hasFactualBasis}
                  />
                  <QualityCheckIndicator
                    label="Solution Focus"
                    passed={analysis.hasSolutionFocus}
                  />
                </div>
              </div>

              {/* Issue Summary Badges */}
              {hasIssues && (
                <div className="flex items-center space-x-2 flex-wrap">
                  {(Object.keys(issueCounts) as IssueSeverity[]).map((severity) => {
                    const config = severityConfig[severity];
                    return (
                      <span
                        key={severity}
                        className={`inline-flex items-center space-x-1 text-xs px-2 py-1 rounded-full ${config.bgColor}`}
                      >
                        {config.icon}
                        <span className={config.color}>
                          {issueCounts[severity]} {config.label}
                        </span>
                      </span>
                    );
                  })}
                </div>
              )}

              {/* Issues List */}
              {hasIssues && (
                <div>
                  <SectionHeader title="Issues Detected" count={sortedIssues.length} />
                  <div className="space-y-2">
                    {sortedIssues.map((issue, index) => (
                      <IssueItem
                        key={`${issue.issueType}-${index}`}
                        issue={issue}
                        onDismiss={
                          onDismissIssue ? () => onDismissIssue(issue) : undefined
                        }
                      />
                    ))}
                  </div>
                </div>
              )}

              {/* Rewrite Suggestions */}
              {hasRewrites && (
                <div>
                  <SectionHeader
                    title="Suggested Rewrites"
                    count={analysis.rewriteSuggestions.length}
                  />
                  <div className="space-y-2">
                    {analysis.rewriteSuggestions.map((suggestion, index) => (
                      <RewriteSuggestionCard
                        key={index}
                        suggestion={suggestion}
                        onApply={
                          onApplyRewrite
                            ? () => onApplyRewrite(suggestion)
                            : undefined
                        }
                      />
                    ))}
                  </div>
                </div>
              )}

              {/* Analysis Notes */}
              {analysis.analysisNotes && (
                <div className="rounded-lg bg-gray-50 p-3">
                  <p className="text-xs text-gray-500 mb-1">Analysis Notes:</p>
                  <p className="text-sm text-gray-700">{analysis.analysisNotes}</p>
                </div>
              )}

              {/* No Issues State */}
              {!hasIssues && analysis.isAcceptable && (
                <div className="flex items-center space-x-3 rounded-lg bg-green-50 border border-green-200 p-4">
                  <svg
                    className="h-6 w-6 text-green-600"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clipRule="evenodd"
                    />
                  </svg>
                  <div>
                    <p className="text-sm font-medium text-green-800">
                      Great message!
                    </p>
                    <p className="text-xs text-green-600">
                      Your message follows Quebec &apos;Bonne Message&apos; standards.
                    </p>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

export default QualityCoachPanel;
