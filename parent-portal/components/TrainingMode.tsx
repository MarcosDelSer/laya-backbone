'use client';

import { useState, useEffect, useCallback, useMemo } from 'react';
import type {
  TrainingExampleResponse,
  QualityIssue,
  MessageLanguage,
} from '@/lib/types';

interface TrainingModeProps {
  /** Training examples to practice with */
  examples?: TrainingExampleResponse[];
  /** Callback when training is completed */
  onComplete?: (results: TrainingResults) => void;
  /** Language for the training interface */
  language?: MessageLanguage;
  /** Filter by specific issue types */
  issueTypes?: QualityIssue[];
  /** Filter by difficulty level */
  difficultyLevel?: string;
  /** Whether the component is in loading state */
  isLoading?: boolean;
  /** Additional CSS classes */
  className?: string;
}

export interface TrainingResults {
  totalExamples: number;
  completedExamples: number;
  correctAnswers: number;
  accuracy: number;
  timeSpentSeconds: number;
  issuesCovered: QualityIssue[];
}

// Issue type labels with bilingual support
const issueTypeLabels: Record<QualityIssue, { en: string; fr: string }> = {
  accusatory_you: { en: 'Accusatory Language', fr: 'Langage accusatoire' },
  judgmental_label: { en: 'Judgmental Label', fr: 'Etiquette de jugement' },
  blame_shame: { en: 'Blame/Shame Pattern', fr: 'Blâme/Honte' },
  exaggeration: { en: 'Exaggeration', fr: 'Exagération' },
  alarmist: { en: 'Alarmist Language', fr: 'Langage alarmiste' },
  comparison: { en: 'Comparison', fr: 'Comparaison' },
  negative_tone: { en: 'Negative Tone', fr: 'Ton négatif' },
  missing_positive: { en: 'Missing Positive Opening', fr: 'Ouverture positive manquante' },
  missing_solution: { en: 'Missing Solution Focus', fr: 'Orientation solution manquante' },
  multiple_objectives: { en: 'Multiple Objectives', fr: 'Objectifs multiples' },
};

// Difficulty level labels
const difficultyLabels: Record<string, { en: string; fr: string; color: string }> = {
  beginner: { en: 'Beginner', fr: 'Débutant', color: 'bg-green-100 text-green-700' },
  intermediate: { en: 'Intermediate', fr: 'Intermédiaire', color: 'bg-yellow-100 text-yellow-700' },
  advanced: { en: 'Advanced', fr: 'Avancé', color: 'bg-red-100 text-red-700' },
};

// Mock training examples - will be replaced with API data
const mockExamples: TrainingExampleResponse[] = [
  {
    id: 'example-1',
    originalMessage: 'Your child never pays attention during story time and is always disruptive.',
    improvedMessage: "I've noticed Emma seems to have more energy during our story time sessions. I'd love to discuss some strategies we can try together to help her stay engaged.",
    issuesDemonstrated: ['accusatory_you', 'exaggeration', 'negative_tone'],
    explanation: "The original message uses accusatory 'you' language and exaggerations like 'never' and 'always'. The improved version uses 'I' language, focuses on observations, and suggests collaboration.",
    language: 'en',
    difficultyLevel: 'beginner',
  },
  {
    id: 'example-2',
    originalMessage: 'Sophie is a troublemaker and causes problems with other children every day.',
    improvedMessage: "I wanted to share an observation about Sophie's social interactions. I've noticed she's still learning how to share toys with her peers. Would you have time to discuss some strategies we can use at home and at the center?",
    issuesDemonstrated: ['judgmental_label', 'blame_shame'],
    explanation: "The original labels the child negatively. The improved version focuses on specific behaviors without labeling, maintains a positive tone, and invites collaboration.",
    language: 'en',
    difficultyLevel: 'intermediate',
  },
  {
    id: 'example-3',
    originalMessage: "Votre enfant ne mange jamais correctement et fait des crises à chaque repas.",
    improvedMessage: "J'ai remarqué que Lucas a parfois de la difficulté à terminer ses repas. Je documente ses préférences alimentaires pour mieux comprendre ses goûts. Pourriez-vous partager ce qu'il mange bien à la maison?",
    issuesDemonstrated: ['accusatory_you', 'exaggeration', 'alarmist'],
    explanation: "Le message original utilise un langage accusatoire et des exagérations. La version améliorée utilise le langage 'je', se concentre sur les faits et invite à la collaboration.",
    language: 'fr',
    difficultyLevel: 'beginner',
  },
];

function SectionHeader({ title, subtitle }: { title: string; subtitle?: string }) {
  return (
    <div className="border-b border-gray-200 pb-3 mb-4">
      <h4 className="font-medium text-gray-900">{title}</h4>
      {subtitle && <p className="text-sm text-gray-500 mt-1">{subtitle}</p>}
    </div>
  );
}

function LoadingState({ language }: { language: MessageLanguage }) {
  return (
    <div className="flex items-center justify-center py-12">
      <div className="flex flex-col items-center space-y-3">
        <svg
          className="h-8 w-8 animate-spin text-primary"
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
        <span className="text-sm text-gray-600">
          {language === 'fr' ? 'Chargement des exemples...' : 'Loading examples...'}
        </span>
      </div>
    </div>
  );
}

function EmptyState({ language }: { language: MessageLanguage }) {
  return (
    <div className="flex flex-col items-center justify-center py-12 text-center">
      <svg
        className="h-12 w-12 text-gray-300 mb-3"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={1.5}
          d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
        />
      </svg>
      <p className="text-sm text-gray-500">
        {language === 'fr'
          ? 'Aucun exemple de formation disponible'
          : 'No training examples available'}
      </p>
    </div>
  );
}

function ProgressBar({
  current,
  total,
  correct,
  language,
}: {
  current: number;
  total: number;
  correct: number;
  language: MessageLanguage;
}) {
  const percentage = total > 0 ? (current / total) * 100 : 0;
  const accuracy = current > 0 ? (correct / current) * 100 : 0;

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between text-sm">
        <span className="text-gray-600">
          {language === 'fr'
            ? `Exemple ${current} sur ${total}`
            : `Example ${current} of ${total}`}
        </span>
        <span className="text-gray-600">
          {language === 'fr' ? `Précision: ${accuracy.toFixed(0)}%` : `Accuracy: ${accuracy.toFixed(0)}%`}
        </span>
      </div>
      <div className="h-2 w-full bg-gray-200 rounded-full overflow-hidden">
        <div
          className="h-full bg-primary transition-all duration-300"
          style={{ width: `${percentage}%` }}
        />
      </div>
    </div>
  );
}

function IssueBadge({
  issue,
  language,
  selected,
  onClick,
}: {
  issue: QualityIssue;
  language: MessageLanguage;
  selected: boolean;
  onClick?: () => void;
}) {
  const label = issueTypeLabels[issue][language];

  return (
    <button
      type="button"
      onClick={onClick}
      disabled={!onClick}
      className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-medium transition-colors ${
        selected
          ? 'bg-primary text-white'
          : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
      } ${!onClick ? 'cursor-default' : 'cursor-pointer'}`}
    >
      {label}
    </button>
  );
}

function MessageDisplay({
  label,
  message,
  variant,
}: {
  label: string;
  message: string;
  variant: 'original' | 'improved';
}) {
  const styles = {
    original: 'bg-red-50 border-red-200 text-red-900',
    improved: 'bg-green-50 border-green-200 text-green-900',
  };

  const iconStyles = {
    original: 'text-red-500',
    improved: 'text-green-500',
  };

  return (
    <div className={`rounded-lg border p-4 ${styles[variant]}`}>
      <div className="flex items-center space-x-2 mb-2">
        {variant === 'original' ? (
          <svg className={`h-4 w-4 ${iconStyles[variant]}`} fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
              clipRule="evenodd"
            />
          </svg>
        ) : (
          <svg className={`h-4 w-4 ${iconStyles[variant]}`} fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
              clipRule="evenodd"
            />
          </svg>
        )}
        <span className="text-xs font-medium uppercase tracking-wider">{label}</span>
      </div>
      <p className="text-sm leading-relaxed">{message}</p>
    </div>
  );
}

function ExplanationCard({
  explanation,
  language,
}: {
  explanation: string;
  language: MessageLanguage;
}) {
  return (
    <div className="rounded-lg bg-blue-50 border border-blue-200 p-4">
      <div className="flex items-start space-x-3">
        <svg
          className="h-5 w-5 text-blue-600 flex-shrink-0 mt-0.5"
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
          <p className="text-xs font-medium text-blue-700 mb-1">
            {language === 'fr' ? 'Explication' : 'Explanation'}
          </p>
          <p className="text-sm text-blue-800">{explanation}</p>
        </div>
      </div>
    </div>
  );
}

function ResultsSummary({
  results,
  language,
  onRestart,
}: {
  results: TrainingResults;
  language: MessageLanguage;
  onRestart?: () => void;
}) {
  const getAccuracyColor = (accuracy: number): string => {
    if (accuracy >= 80) return 'text-green-600';
    if (accuracy >= 60) return 'text-yellow-600';
    return 'text-red-600';
  };

  const getAccuracyLabel = (accuracy: number): string => {
    if (language === 'fr') {
      if (accuracy >= 80) return 'Excellent!';
      if (accuracy >= 60) return 'Bon travail!';
      return 'Continuez à pratiquer';
    }
    if (accuracy >= 80) return 'Excellent!';
    if (accuracy >= 60) return 'Good job!';
    return 'Keep practicing';
  };

  const formatTime = (seconds: number): string => {
    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = seconds % 60;
    if (minutes > 0) {
      return language === 'fr'
        ? `${minutes}m ${remainingSeconds}s`
        : `${minutes}m ${remainingSeconds}s`;
    }
    return language === 'fr' ? `${seconds}s` : `${seconds}s`;
  };

  return (
    <div className="text-center space-y-6 py-6">
      <div className="flex items-center justify-center">
        <div className="flex h-24 w-24 items-center justify-center rounded-full bg-gradient-to-br from-primary-100 to-primary-200">
          <svg
            className="h-12 w-12 text-primary"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"
            />
          </svg>
        </div>
      </div>

      <div>
        <h3 className="text-2xl font-bold text-gray-900">
          {language === 'fr' ? 'Formation terminée!' : 'Training Complete!'}
        </h3>
        <p className={`text-lg font-medium mt-1 ${getAccuracyColor(results.accuracy)}`}>
          {getAccuracyLabel(results.accuracy)}
        </p>
      </div>

      <div className="grid grid-cols-2 gap-4 max-w-sm mx-auto">
        <div className="rounded-lg bg-gray-50 p-4">
          <p className="text-2xl font-bold text-gray-900">{results.correctAnswers}/{results.totalExamples}</p>
          <p className="text-xs text-gray-500">
            {language === 'fr' ? 'Réponses correctes' : 'Correct Answers'}
          </p>
        </div>
        <div className="rounded-lg bg-gray-50 p-4">
          <p className={`text-2xl font-bold ${getAccuracyColor(results.accuracy)}`}>
            {results.accuracy.toFixed(0)}%
          </p>
          <p className="text-xs text-gray-500">
            {language === 'fr' ? 'Précision' : 'Accuracy'}
          </p>
        </div>
      </div>

      <div className="text-sm text-gray-500">
        <p>
          {language === 'fr' ? 'Temps passé: ' : 'Time spent: '}
          <span className="font-medium text-gray-700">{formatTime(results.timeSpentSeconds)}</span>
        </p>
        <p className="mt-1">
          {language === 'fr' ? 'Problèmes couverts: ' : 'Issues covered: '}
          <span className="font-medium text-gray-700">{results.issuesCovered.length}</span>
        </p>
      </div>

      {onRestart && (
        <button
          type="button"
          onClick={onRestart}
          className="inline-flex items-center space-x-2 rounded-lg bg-primary px-6 py-3 text-sm font-medium text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors"
        >
          <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"
            />
          </svg>
          <span>{language === 'fr' ? 'Recommencer' : 'Restart Training'}</span>
        </button>
      )}
    </div>
  );
}

/**
 * TrainingMode component for educators to practice with example messages and feedback.
 * Implements Quebec 'Bonne Message' standards training with interactive exercises.
 */
export function TrainingMode({
  examples: providedExamples,
  onComplete,
  language = 'en',
  issueTypes,
  difficultyLevel,
  isLoading = false,
  className = '',
}: TrainingModeProps) {
  // Use provided examples or fall back to mock data
  const examples = useMemo(() => {
    let filtered = providedExamples || mockExamples;

    // Filter by language
    filtered = filtered.filter((ex) => ex.language === language);

    // Filter by issue types if specified
    if (issueTypes && issueTypes.length > 0) {
      filtered = filtered.filter((ex) =>
        ex.issuesDemonstrated.some((issue) => issueTypes.includes(issue))
      );
    }

    // Filter by difficulty level if specified
    if (difficultyLevel) {
      filtered = filtered.filter((ex) => ex.difficultyLevel === difficultyLevel);
    }

    return filtered;
  }, [providedExamples, language, issueTypes, difficultyLevel]);

  const [currentIndex, setCurrentIndex] = useState(0);
  const [selectedIssues, setSelectedIssues] = useState<QualityIssue[]>([]);
  const [showAnswer, setShowAnswer] = useState(false);
  const [correctAnswers, setCorrectAnswers] = useState(0);
  const [isCompleted, setIsCompleted] = useState(false);
  const [startTime] = useState(() => Date.now());
  const [issuesCovered, setIssuesCovered] = useState<Set<QualityIssue>>(new Set());

  const currentExample = examples[currentIndex];

  // All possible issues for selection
  const allIssues: QualityIssue[] = [
    'accusatory_you',
    'judgmental_label',
    'blame_shame',
    'exaggeration',
    'alarmist',
    'comparison',
    'negative_tone',
    'missing_positive',
    'missing_solution',
    'multiple_objectives',
  ];

  const handleIssueToggle = useCallback((issue: QualityIssue) => {
    setSelectedIssues((prev) =>
      prev.includes(issue)
        ? prev.filter((i) => i !== issue)
        : [...prev, issue]
    );
  }, []);

  const checkAnswer = useCallback(() => {
    if (!currentExample) return;

    // Calculate if answer is correct (at least 50% of issues correctly identified)
    const correctIssues = currentExample.issuesDemonstrated;
    const correctSelections = selectedIssues.filter((issue) =>
      correctIssues.includes(issue)
    );
    const incorrectSelections = selectedIssues.filter(
      (issue) => !correctIssues.includes(issue)
    );

    const isCorrect =
      correctSelections.length >= correctIssues.length / 2 &&
      incorrectSelections.length <= 1;

    if (isCorrect) {
      setCorrectAnswers((prev) => prev + 1);
    }

    // Track covered issues
    setIssuesCovered((prev) => {
      const newSet = new Set(prev);
      correctIssues.forEach((issue) => newSet.add(issue));
      return newSet;
    });

    setShowAnswer(true);
  }, [currentExample, selectedIssues]);

  const handleNext = useCallback(() => {
    if (currentIndex < examples.length - 1) {
      setCurrentIndex((prev) => prev + 1);
      setSelectedIssues([]);
      setShowAnswer(false);
    } else {
      // Training complete
      const results: TrainingResults = {
        totalExamples: examples.length,
        completedExamples: examples.length,
        correctAnswers,
        accuracy: examples.length > 0 ? (correctAnswers / examples.length) * 100 : 0,
        timeSpentSeconds: Math.floor((Date.now() - startTime) / 1000),
        issuesCovered: Array.from(issuesCovered),
      };
      setIsCompleted(true);
      onComplete?.(results);
    }
  }, [currentIndex, examples.length, correctAnswers, startTime, issuesCovered, onComplete]);

  const handleRestart = useCallback(() => {
    setCurrentIndex(0);
    setSelectedIssues([]);
    setShowAnswer(false);
    setCorrectAnswers(0);
    setIsCompleted(false);
    setIssuesCovered(new Set());
  }, []);

  // Handle keyboard navigation
  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Enter' && !showAnswer) {
        event.preventDefault();
        checkAnswer();
      } else if (event.key === 'Enter' && showAnswer) {
        event.preventDefault();
        handleNext();
      }
    };

    document.addEventListener('keydown', handleKeyDown);
    return () => document.removeEventListener('keydown', handleKeyDown);
  }, [showAnswer, checkAnswer, handleNext]);

  if (isLoading) {
    return (
      <div className={`bg-white rounded-lg border border-gray-200 shadow-sm ${className}`}>
        <LoadingState language={language} />
      </div>
    );
  }

  if (examples.length === 0) {
    return (
      <div className={`bg-white rounded-lg border border-gray-200 shadow-sm ${className}`}>
        <EmptyState language={language} />
      </div>
    );
  }

  if (isCompleted) {
    const results: TrainingResults = {
      totalExamples: examples.length,
      completedExamples: examples.length,
      correctAnswers,
      accuracy: examples.length > 0 ? (correctAnswers / examples.length) * 100 : 0,
      timeSpentSeconds: Math.floor((Date.now() - startTime) / 1000),
      issuesCovered: Array.from(issuesCovered),
    };

    return (
      <div className={`bg-white rounded-lg border border-gray-200 shadow-sm ${className}`}>
        <div className="px-4 py-3 border-b border-gray-200">
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
                d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
              />
            </svg>
            <h3 className="text-sm font-semibold text-gray-900">
              {language === 'fr' ? 'Mode Formation' : 'Training Mode'}
            </h3>
          </div>
        </div>
        <div className="p-4">
          <ResultsSummary results={results} language={language} onRestart={handleRestart} />
        </div>
      </div>
    );
  }

  return (
    <div className={`bg-white rounded-lg border border-gray-200 shadow-sm ${className}`}>
      {/* Header */}
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
              d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"
            />
          </svg>
          <h3 className="text-sm font-semibold text-gray-900">
            {language === 'fr' ? 'Mode Formation' : 'Training Mode'}
          </h3>
        </div>
        {currentExample?.difficultyLevel && (
          <span
            className={`text-xs px-2 py-0.5 rounded-full ${
              difficultyLabels[currentExample.difficultyLevel]?.color || 'bg-gray-100 text-gray-700'
            }`}
          >
            {difficultyLabels[currentExample.difficultyLevel]?.[language] ||
              currentExample.difficultyLevel}
          </span>
        )}
      </div>

      {/* Content */}
      <div className="p-4 space-y-4">
        {/* Progress */}
        <ProgressBar
          current={currentIndex + 1}
          total={examples.length}
          correct={correctAnswers}
          language={language}
        />

        {/* Instructions */}
        <div className="rounded-lg bg-gray-50 p-3">
          <p className="text-sm text-gray-700">
            {language === 'fr'
              ? "Lisez le message original ci-dessous et identifiez les problèmes de communication. Sélectionnez tous les problèmes que vous repérez."
              : 'Read the original message below and identify the communication issues. Select all issues you spot.'}
          </p>
        </div>

        {/* Original Message */}
        {currentExample && (
          <>
            <SectionHeader
              title={language === 'fr' ? 'Message à analyser' : 'Message to Analyze'}
            />
            <MessageDisplay
              label={language === 'fr' ? 'Message Original' : 'Original Message'}
              message={currentExample.originalMessage}
              variant="original"
            />

            {/* Issue Selection */}
            <div>
              <SectionHeader
                title={language === 'fr' ? 'Identifiez les problèmes' : 'Identify the Issues'}
                subtitle={
                  language === 'fr'
                    ? 'Cliquez sur tous les problèmes présents dans ce message'
                    : 'Click on all issues present in this message'
                }
              />
              <div className="flex flex-wrap gap-2">
                {allIssues.map((issue) => (
                  <IssueBadge
                    key={issue}
                    issue={issue}
                    language={language}
                    selected={selectedIssues.includes(issue)}
                    onClick={!showAnswer ? () => handleIssueToggle(issue) : undefined}
                  />
                ))}
              </div>
            </div>

            {/* Show Answer Section */}
            {showAnswer && (
              <div className="space-y-4 pt-4 border-t border-gray-200">
                {/* Correct Issues */}
                <div>
                  <p className="text-sm font-medium text-gray-700 mb-2">
                    {language === 'fr' ? 'Problèmes corrects:' : 'Correct Issues:'}
                  </p>
                  <div className="flex flex-wrap gap-2">
                    {currentExample.issuesDemonstrated.map((issue) => (
                      <span
                        key={issue}
                        className={`inline-flex items-center rounded-full px-3 py-1 text-xs font-medium ${
                          selectedIssues.includes(issue)
                            ? 'bg-green-100 text-green-700'
                            : 'bg-red-100 text-red-700'
                        }`}
                      >
                        {selectedIssues.includes(issue) && (
                          <svg
                            className="h-3 w-3 mr-1"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                          >
                            <path
                              fillRule="evenodd"
                              d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                              clipRule="evenodd"
                            />
                          </svg>
                        )}
                        {!selectedIssues.includes(issue) && (
                          <svg
                            className="h-3 w-3 mr-1"
                            fill="currentColor"
                            viewBox="0 0 20 20"
                          >
                            <path
                              fillRule="evenodd"
                              d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                              clipRule="evenodd"
                            />
                          </svg>
                        )}
                        {issueTypeLabels[issue][language]}
                      </span>
                    ))}
                  </div>
                </div>

                {/* Improved Message */}
                <MessageDisplay
                  label={language === 'fr' ? 'Message Amélioré' : 'Improved Message'}
                  message={currentExample.improvedMessage}
                  variant="improved"
                />

                {/* Explanation */}
                <ExplanationCard
                  explanation={currentExample.explanation}
                  language={language}
                />
              </div>
            )}

            {/* Action Buttons */}
            <div className="flex justify-end space-x-3 pt-4 border-t border-gray-200">
              {!showAnswer ? (
                <button
                  type="button"
                  onClick={checkAnswer}
                  disabled={selectedIssues.length === 0}
                  className="inline-flex items-center space-x-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                >
                  <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M5 13l4 4L19 7"
                    />
                  </svg>
                  <span>{language === 'fr' ? 'Vérifier' : 'Check Answer'}</span>
                </button>
              ) : (
                <button
                  type="button"
                  onClick={handleNext}
                  className="inline-flex items-center space-x-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-white hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 transition-colors"
                >
                  <span>
                    {currentIndex < examples.length - 1
                      ? language === 'fr'
                        ? 'Suivant'
                        : 'Next Example'
                      : language === 'fr'
                        ? 'Terminer'
                        : 'Finish Training'}
                  </span>
                  <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M9 5l7 7-7 7"
                    />
                  </svg>
                </button>
              )}
            </div>
          </>
        )}
      </div>
    </div>
  );
}

export default TrainingMode;
