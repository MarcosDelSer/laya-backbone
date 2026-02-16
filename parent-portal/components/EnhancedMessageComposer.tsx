'use client';

import { useState, useRef, useEffect, useCallback } from 'react';
import { QualityCoachPanel } from './QualityCoachPanel';
import { analyzeMessageForComposer } from '@/lib/ai-client';
import type {
  MessageAnalysisResponse,
  RewriteSuggestion,
  QualityIssueDetail,
  MessageLanguage,
} from '@/lib/types';

interface EnhancedMessageComposerProps {
  onSendMessage: (content: string) => void;
  disabled?: boolean;
  placeholder?: string;
  language?: MessageLanguage;
  showQualityCoach?: boolean;
  minCharactersForAnalysis?: number;
  debounceMs?: number;
  onAnalysisComplete?: (analysis: MessageAnalysisResponse | null) => void;
}

/**
 * Enhanced message composer with integrated Quality Coach panel.
 * Provides real-time message quality analysis based on Quebec 'Bonne Message' standards.
 */
export function EnhancedMessageComposer({
  onSendMessage,
  disabled = false,
  placeholder = 'Type a message...',
  language = 'en',
  showQualityCoach = true,
  minCharactersForAnalysis = 20,
  debounceMs = 500,
  onAnalysisComplete,
}: EnhancedMessageComposerProps) {
  const [message, setMessage] = useState('');
  const [analysis, setAnalysis] = useState<MessageAnalysisResponse | null>(null);
  const [isAnalyzing, setIsAnalyzing] = useState(false);
  const [isPanelCollapsed, setIsPanelCollapsed] = useState(false);
  const [analysisError, setAnalysisError] = useState<string | null>(null);

  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const debounceTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const abortControllerRef = useRef<AbortController | null>(null);

  // Auto-resize textarea based on content
  useEffect(() => {
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto';
      textareaRef.current.style.height = `${Math.min(
        textareaRef.current.scrollHeight,
        150
      )}px`;
    }
  }, [message]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }
    };
  }, []);

  // Analyze message with debouncing
  const analyzeMessage = useCallback(
    async (text: string) => {
      // Cancel any pending analysis
      if (abortControllerRef.current) {
        abortControllerRef.current.abort();
      }

      // Skip analysis for short messages
      if (text.trim().length < minCharactersForAnalysis) {
        setAnalysis(null);
        setAnalysisError(null);
        onAnalysisComplete?.(null);
        return;
      }

      // Create new abort controller for this request
      abortControllerRef.current = new AbortController();

      setIsAnalyzing(true);
      setAnalysisError(null);

      try {
        const result = await analyzeMessageForComposer(text, language);

        // Only update if request wasn't aborted
        if (!abortControllerRef.current.signal.aborted) {
          setAnalysis(result);
          onAnalysisComplete?.(result);
        }
      } catch (error) {
        // Only handle error if request wasn't aborted
        if (!abortControllerRef.current?.signal.aborted) {
          const errorMessage = error instanceof Error ? error.message : 'Analysis failed';
          setAnalysisError(errorMessage);
          setAnalysis(null);
          onAnalysisComplete?.(null);
        }
      } finally {
        if (!abortControllerRef.current?.signal.aborted) {
          setIsAnalyzing(false);
        }
      }
    },
    [language, minCharactersForAnalysis, onAnalysisComplete]
  );

  // Handle message change with debounced analysis
  const handleMessageChange = useCallback(
    (newMessage: string) => {
      setMessage(newMessage);

      // Clear existing debounce timer
      if (debounceTimerRef.current) {
        clearTimeout(debounceTimerRef.current);
      }

      // Set up new debounce timer for analysis
      if (showQualityCoach) {
        debounceTimerRef.current = setTimeout(() => {
          analyzeMessage(newMessage);
        }, debounceMs);
      }
    },
    [analyzeMessage, debounceMs, showQualityCoach]
  );

  // Apply rewrite suggestion
  const handleApplyRewrite = useCallback(
    (suggestion: RewriteSuggestion) => {
      // Replace the message with the suggested text
      const newMessage = suggestion.suggestedText;
      setMessage(newMessage);

      // Clear current analysis since message has changed
      setAnalysis(null);

      // Focus textarea
      textareaRef.current?.focus();

      // Trigger new analysis after a brief delay
      if (showQualityCoach) {
        debounceTimerRef.current = setTimeout(() => {
          analyzeMessage(newMessage);
        }, debounceMs);
      }
    },
    [analyzeMessage, debounceMs, showQualityCoach]
  );

  // Dismiss issue (removes it from the current analysis display)
  const handleDismissIssue = useCallback((issue: QualityIssueDetail) => {
    if (!analysis) return;

    setAnalysis({
      ...analysis,
      issues: analysis.issues.filter(
        (i) => i.issueType !== issue.issueType || i.positionStart !== issue.positionStart
      ),
    });
  }, [analysis]);

  // Toggle panel collapse
  const handleToggleCollapse = useCallback(() => {
    setIsPanelCollapsed((prev) => !prev);
  }, []);

  // Handle form submission
  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const trimmedMessage = message.trim();
    if (trimmedMessage && !disabled) {
      onSendMessage(trimmedMessage);
      setMessage('');
      setAnalysis(null);
      setAnalysisError(null);
      // Reset textarea height
      if (textareaRef.current) {
        textareaRef.current.style.height = 'auto';
      }
    }
  };

  // Handle keyboard shortcuts
  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    // Submit on Enter (without Shift)
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(e);
    }
  };

  // Determine if message is ready to send
  const canSend = message.trim().length > 0 && !disabled;
  const hasQualityWarning = analysis && !analysis.isAcceptable;

  return (
    <div className="space-y-4">
      {/* Quality Coach Panel */}
      {showQualityCoach && (
        <QualityCoachPanel
          analysis={analysis}
          isLoading={isAnalyzing}
          onApplyRewrite={handleApplyRewrite}
          onDismissIssue={handleDismissIssue}
          collapsed={isPanelCollapsed}
          onToggleCollapse={handleToggleCollapse}
        />
      )}

      {/* Error display */}
      {analysisError && (
        <div className="rounded-lg border border-red-200 bg-red-50 p-3">
          <div className="flex items-center space-x-2">
            <svg
              className="h-4 w-4 text-red-600 flex-shrink-0"
              fill="currentColor"
              viewBox="0 0 20 20"
            >
              <path
                fillRule="evenodd"
                d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                clipRule="evenodd"
              />
            </svg>
            <p className="text-sm text-red-700">
              Quality analysis unavailable: {analysisError}
            </p>
          </div>
        </div>
      )}

      {/* Message Composer */}
      <form onSubmit={handleSubmit} className="bg-white border-t border-gray-200 p-4">
        <div className="flex items-end space-x-3">
          {/* Attachment button (placeholder) */}
          <button
            type="button"
            className="flex-shrink-0 p-2 text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-primary rounded-full"
            disabled={disabled}
            title="Attach file (coming soon)"
          >
            <svg
              className="h-5 w-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"
              />
            </svg>
          </button>

          {/* Message input */}
          <div className="relative flex-1">
            <textarea
              ref={textareaRef}
              value={message}
              onChange={(e) => handleMessageChange(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder={placeholder}
              disabled={disabled}
              rows={1}
              className={`w-full resize-none rounded-2xl border bg-gray-50 px-4 py-3 pr-12 text-sm focus:bg-white focus:outline-none focus:ring-1 disabled:bg-gray-100 disabled:text-gray-500 ${
                hasQualityWarning
                  ? 'border-yellow-400 focus:border-yellow-500 focus:ring-yellow-500'
                  : 'border-gray-300 focus:border-primary focus:ring-primary'
              }`}
              aria-label="Message input"
            />
            {/* Character count */}
            {message.length > 200 && (
              <span
                className={`absolute bottom-2 right-14 text-xs ${
                  message.length > 500 ? 'text-red-500' : 'text-gray-400'
                }`}
              >
                {message.length}/500
              </span>
            )}
            {/* Analyzing indicator */}
            {isAnalyzing && (
              <span className="absolute bottom-2 right-14 flex items-center space-x-1">
                <svg
                  className="h-3 w-3 animate-spin text-primary"
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
              </span>
            )}
          </div>

          {/* Send button */}
          <button
            type="submit"
            disabled={!canSend}
            className={`flex-shrink-0 rounded-full p-3 text-white transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:cursor-not-allowed ${
              hasQualityWarning
                ? 'bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-500 disabled:bg-gray-300'
                : 'bg-primary hover:bg-primary-dark focus:ring-primary disabled:bg-gray-300'
            }`}
            title={hasQualityWarning ? 'Send with quality warning' : 'Send message'}
          >
            <svg
              className="h-5 w-5"
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"
              />
            </svg>
          </button>
        </div>

        {/* Footer text */}
        <div className="mt-2 flex items-center justify-between">
          <p className="text-xs text-gray-400">
            Press Enter to send, Shift+Enter for new line
          </p>
          {showQualityCoach && (
            <p className="text-xs text-gray-400">
              {message.trim().length < minCharactersForAnalysis
                ? `Type ${minCharactersForAnalysis - message.trim().length} more characters for quality analysis`
                : analysis?.isAcceptable
                ? '✓ Message quality OK'
                : analysis && !analysis.isAcceptable
                ? '⚠ Review quality suggestions'
                : isAnalyzing
                ? 'Analyzing...'
                : ''}
            </p>
          )}
        </div>

        {/* Quality warning banner */}
        {hasQualityWarning && (
          <div className="mt-3 flex items-center justify-between rounded-lg border border-yellow-200 bg-yellow-50 p-3">
            <div className="flex items-center space-x-2">
              <svg
                className="h-5 w-5 text-yellow-600 flex-shrink-0"
                fill="currentColor"
                viewBox="0 0 20 20"
              >
                <path
                  fillRule="evenodd"
                  d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                  clipRule="evenodd"
                />
              </svg>
              <span className="text-sm text-yellow-800">
                {analysis.issues.length} quality{' '}
                {analysis.issues.length === 1 ? 'issue' : 'issues'} detected.
                Review suggestions before sending.
              </span>
            </div>
            <button
              type="submit"
              className="text-sm font-medium text-yellow-700 hover:text-yellow-900 focus:outline-none focus:underline"
            >
              Send anyway
            </button>
          </div>
        )}
      </form>
    </div>
  );
}

export default EnhancedMessageComposer;
