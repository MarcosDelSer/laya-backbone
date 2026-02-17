'use client';

import { useState, useRef, useEffect } from 'react';
import { DevelopmentalDomain, CreateObservationRequest } from '../../lib/types';

/**
 * Props for the ObservationForm component.
 */
export interface ObservationFormProps {
  /** The profile ID to associate the observation with */
  profileId: string;
  /** Callback when observation is submitted */
  onSubmit: (observation: CreateObservationRequest) => void;
  /** Whether the form is disabled (e.g., during submission) */
  disabled?: boolean;
  /** Optional pre-selected domain */
  initialDomain?: DevelopmentalDomain;
  /** Placeholder text for the behavior description */
  placeholder?: string;
}

/**
 * Domain display information with bilingual support.
 */
interface DomainOption {
  value: DevelopmentalDomain;
  label: string;
  labelFr: string;
  description: string;
}

/**
 * Get all developmental domain options with display information.
 */
function getDomainOptions(): DomainOption[] {
  return [
    {
      value: 'affective',
      label: 'Affective Development',
      labelFr: 'Developpement affectif',
      description: 'Emotional expression, self-regulation, attachment',
    },
    {
      value: 'social',
      label: 'Social Development',
      labelFr: 'Developpement social',
      description: 'Peer interactions, turn-taking, empathy',
    },
    {
      value: 'language',
      label: 'Language & Communication',
      labelFr: 'Langage et communication',
      description: 'Speech, vocabulary, emergent literacy',
    },
    {
      value: 'cognitive',
      label: 'Cognitive Development',
      labelFr: 'Developpement cognitif',
      description: 'Problem-solving, memory, attention',
    },
    {
      value: 'gross_motor',
      label: 'Physical - Gross Motor',
      labelFr: 'Physique - Motricite globale',
      description: 'Balance, coordination, body awareness',
    },
    {
      value: 'fine_motor',
      label: 'Physical - Fine Motor',
      labelFr: 'Physique - Motricite fine',
      description: 'Hand-eye coordination, pencil grip',
    },
  ];
}

/**
 * Get domain color class for visual distinction.
 */
function getDomainColorClass(domain: DevelopmentalDomain): string {
  switch (domain) {
    case 'affective':
      return 'border-pink-500 bg-pink-50 focus:ring-pink-500';
    case 'social':
      return 'border-blue-500 bg-blue-50 focus:ring-blue-500';
    case 'language':
      return 'border-purple-500 bg-purple-50 focus:ring-purple-500';
    case 'cognitive':
      return 'border-amber-500 bg-amber-50 focus:ring-amber-500';
    case 'gross_motor':
      return 'border-green-500 bg-green-50 focus:ring-green-500';
    case 'fine_motor':
      return 'border-teal-500 bg-teal-50 focus:ring-teal-500';
    default:
      return 'border-gray-300 bg-gray-50 focus:ring-primary';
  }
}

/**
 * Get domain icon SVG.
 */
function DomainIcon({ domain }: { domain: DevelopmentalDomain }) {
  const iconClass = 'h-5 w-5';

  switch (domain) {
    case 'affective':
      return (
        <svg className={`${iconClass} text-pink-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
        </svg>
      );
    case 'social':
      return (
        <svg className={`${iconClass} text-blue-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
      );
    case 'language':
      return (
        <svg className={`${iconClass} text-purple-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
        </svg>
      );
    case 'cognitive':
      return (
        <svg className={`${iconClass} text-amber-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
        </svg>
      );
    case 'gross_motor':
      return (
        <svg className={`${iconClass} text-green-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
      );
    case 'fine_motor':
      return (
        <svg className={`${iconClass} text-teal-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
        </svg>
      );
    default:
      return (
        <svg className={`${iconClass} text-gray-600`} fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
      );
  }
}

/**
 * ObservationForm component for parents to submit observations about their child.
 *
 * Allows parents to document observable behaviors across the 6 Quebec-aligned
 * developmental domains, with options to mark milestones or concerns.
 */
export function ObservationForm({
  profileId,
  onSubmit,
  disabled = false,
  initialDomain,
  placeholder = 'Describe what you observed...',
}: ObservationFormProps) {
  const [domain, setDomain] = useState<DevelopmentalDomain | ''>(initialDomain || '');
  const [behaviorDescription, setBehaviorDescription] = useState('');
  const [context, setContext] = useState('');
  const [isMilestone, setIsMilestone] = useState(false);
  const [isConcern, setIsConcern] = useState(false);
  const [observedAt, setObservedAt] = useState<string>(
    new Date().toISOString().split('T')[0]
  );
  const [showAdvanced, setShowAdvanced] = useState(false);

  const textareaRef = useRef<HTMLTextAreaElement>(null);

  // Auto-resize textarea based on content
  useEffect(() => {
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto';
      textareaRef.current.style.height = `${Math.min(
        textareaRef.current.scrollHeight,
        200
      )}px`;
    }
  }, [behaviorDescription]);

  const domainOptions = getDomainOptions();
  const isFormValid = domain !== '' && behaviorDescription.trim().length >= 10;

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!isFormValid || disabled) return;

    const observation: CreateObservationRequest = {
      profileId,
      domain: domain as DevelopmentalDomain,
      behaviorDescription: behaviorDescription.trim(),
      context: context.trim() || undefined,
      isMilestone,
      isConcern,
      observedAt,
      observerType: 'parent',
    };

    onSubmit(observation);

    // Reset form
    setBehaviorDescription('');
    setContext('');
    setIsMilestone(false);
    setIsConcern(false);
    setShowAdvanced(false);
    if (textareaRef.current) {
      textareaRef.current.style.height = 'auto';
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    // Submit on Ctrl+Enter or Cmd+Enter
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      e.preventDefault();
      handleSubmit(e);
    }
  };

  return (
    <form onSubmit={handleSubmit} className="bg-white rounded-lg border border-gray-200 p-4">
      <div className="space-y-4">
        {/* Domain Selection */}
        <div>
          <label
            htmlFor="domain-select"
            className="block text-sm font-medium text-gray-700 mb-2"
          >
            Developmental Domain <span className="text-red-500">*</span>
          </label>
          <div className="grid grid-cols-2 sm:grid-cols-3 gap-2">
            {domainOptions.map((option) => (
              <button
                key={option.value}
                type="button"
                onClick={() => setDomain(option.value)}
                disabled={disabled}
                className={`relative flex items-center p-3 rounded-lg border-2 text-left transition-all ${
                  domain === option.value
                    ? getDomainColorClass(option.value)
                    : 'border-gray-200 bg-white hover:border-gray-300'
                } ${disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'}`}
              >
                <DomainIcon domain={option.value} />
                <div className="ml-2 flex-1 min-w-0">
                  <p className="text-xs font-medium text-gray-900 truncate">
                    {option.label}
                  </p>
                </div>
                {domain === option.value && (
                  <svg
                    className="h-4 w-4 text-green-600 flex-shrink-0"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clipRule="evenodd"
                    />
                  </svg>
                )}
              </button>
            ))}
          </div>
          {domain && (
            <p className="mt-2 text-xs text-gray-500">
              {domainOptions.find((d) => d.value === domain)?.description}
            </p>
          )}
        </div>

        {/* Behavior Description */}
        <div>
          <label
            htmlFor="behavior-description"
            className="block text-sm font-medium text-gray-700 mb-2"
          >
            What did you observe? <span className="text-red-500">*</span>
          </label>
          <div className="relative">
            <textarea
              ref={textareaRef}
              id="behavior-description"
              value={behaviorDescription}
              onChange={(e) => setBehaviorDescription(e.target.value)}
              onKeyDown={handleKeyDown}
              placeholder={placeholder}
              disabled={disabled}
              rows={3}
              minLength={10}
              maxLength={1000}
              className="w-full resize-none rounded-lg border border-gray-300 bg-gray-50 px-4 py-3 text-sm focus:border-primary focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary disabled:bg-gray-100 disabled:text-gray-500"
            />
            {behaviorDescription.length > 0 && (
              <span
                className={`absolute bottom-2 right-3 text-xs ${
                  behaviorDescription.length < 10
                    ? 'text-red-500'
                    : behaviorDescription.length > 900
                      ? 'text-amber-500'
                      : 'text-gray-400'
                }`}
              >
                {behaviorDescription.length}/1000
              </span>
            )}
          </div>
          {behaviorDescription.length > 0 && behaviorDescription.length < 10 && (
            <p className="mt-1 text-xs text-red-500">
              Please provide at least 10 characters
            </p>
          )}
        </div>

        {/* Milestone/Concern Quick Selection */}
        <div className="flex flex-wrap gap-3">
          <label
            className={`inline-flex items-center px-4 py-2 rounded-full border-2 cursor-pointer transition-all ${
              isMilestone
                ? 'border-green-500 bg-green-50 text-green-700'
                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
            } ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
          >
            <input
              type="checkbox"
              checked={isMilestone}
              onChange={(e) => setIsMilestone(e.target.checked)}
              disabled={disabled}
              className="sr-only"
            />
            <svg
              className={`h-5 w-5 mr-2 ${isMilestone ? 'text-green-600' : 'text-gray-400'}`}
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"
              />
            </svg>
            <span className="text-sm font-medium">Milestone</span>
          </label>

          <label
            className={`inline-flex items-center px-4 py-2 rounded-full border-2 cursor-pointer transition-all ${
              isConcern
                ? 'border-amber-500 bg-amber-50 text-amber-700'
                : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
            } ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`}
          >
            <input
              type="checkbox"
              checked={isConcern}
              onChange={(e) => setIsConcern(e.target.checked)}
              disabled={disabled}
              className="sr-only"
            />
            <svg
              className={`h-5 w-5 mr-2 ${isConcern ? 'text-amber-600' : 'text-gray-400'}`}
              fill="none"
              stroke="currentColor"
              viewBox="0 0 24 24"
            >
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
              />
            </svg>
            <span className="text-sm font-medium">Concern</span>
          </label>
        </div>

        {/* Advanced Options Toggle */}
        <button
          type="button"
          onClick={() => setShowAdvanced(!showAdvanced)}
          className="text-sm text-primary-600 hover:text-primary-700 font-medium flex items-center"
          disabled={disabled}
        >
          <svg
            className={`h-4 w-4 mr-1 transition-transform ${showAdvanced ? 'rotate-90' : ''}`}
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
          </svg>
          {showAdvanced ? 'Hide details' : 'Add more details'}
        </button>

        {/* Advanced Options */}
        {showAdvanced && (
          <div className="space-y-4 pt-2 border-t border-gray-100">
            {/* Context */}
            <div>
              <label
                htmlFor="context"
                className="block text-sm font-medium text-gray-700 mb-2"
              >
                Context (optional)
              </label>
              <input
                type="text"
                id="context"
                value={context}
                onChange={(e) => setContext(e.target.value)}
                placeholder="e.g., During dinner, At the park, While playing with siblings"
                disabled={disabled}
                maxLength={200}
                className="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2 text-sm focus:border-primary focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary disabled:bg-gray-100 disabled:text-gray-500"
              />
            </div>

            {/* Observation Date */}
            <div>
              <label
                htmlFor="observed-at"
                className="block text-sm font-medium text-gray-700 mb-2"
              >
                When did you observe this?
              </label>
              <input
                type="date"
                id="observed-at"
                value={observedAt}
                onChange={(e) => setObservedAt(e.target.value)}
                disabled={disabled}
                max={new Date().toISOString().split('T')[0]}
                className="w-full rounded-lg border border-gray-300 bg-gray-50 px-4 py-2 text-sm focus:border-primary focus:bg-white focus:outline-none focus:ring-1 focus:ring-primary disabled:bg-gray-100 disabled:text-gray-500"
              />
            </div>
          </div>
        )}

        {/* Submit Button */}
        <div className="flex items-center justify-between pt-2">
          <p className="text-xs text-gray-400">
            Press Ctrl+Enter to submit
          </p>
          <button
            type="submit"
            disabled={disabled || !isFormValid}
            className="inline-flex items-center px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium transition-colors hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:bg-gray-300 disabled:cursor-not-allowed"
          >
            <svg
              className="h-5 w-5 mr-2"
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
            Submit Observation
          </button>
        </div>
      </div>
    </form>
  );
}
