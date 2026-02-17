'use client';

import { useState, useEffect, useCallback } from 'react';
import {
  MessageTemplateResponse,
  TemplateCategory,
  MessageLanguage,
} from '../lib/types';

interface MessageTemplateSelectorProps {
  /** Callback when a template is selected */
  onSelect: (template: MessageTemplateResponse) => void;
  /** Pre-selected category filter */
  category?: TemplateCategory;
  /** Language for templates */
  language?: MessageLanguage;
  /** Additional CSS classes */
  className?: string;
  /** Whether the selector is disabled */
  disabled?: boolean;
  /** Button variant */
  variant?: 'default' | 'compact';
}

// Mock data for templates - will be replaced with API call
const mockTemplates: MessageTemplateResponse[] = [
  {
    id: 'template-1',
    title: 'Daily Progress Update',
    content:
      "I'm happy to share that {child_name} had a wonderful day today. We observed positive engagement during our activities.",
    category: 'positive_opening',
    language: 'en',
    description: 'Start messages with positive observations',
    isSystem: true,
    usageCount: 45,
  },
  {
    id: 'template-2',
    title: 'Activity Observation',
    content:
      'During our morning activities, I noticed that {child_name} showed interest in {activity}. We documented this moment to share with you.',
    category: 'factual_observation',
    language: 'en',
    description: 'Share factual observations about activities',
    isSystem: true,
    usageCount: 32,
  },
  {
    id: 'template-3',
    title: 'Collaborative Discussion Request',
    content:
      "I would love to discuss how we can work together to support {child_name}'s development in {area}. Would you have time for a quick conversation?",
    category: 'solution_oriented',
    language: 'en',
    description: 'Invite parents to collaborate on solutions',
    isSystem: true,
    usageCount: 28,
  },
  {
    id: 'template-4',
    title: 'Mise à jour quotidienne',
    content:
      "Je suis heureuse de partager que {child_name} a passé une belle journée aujourd'hui. Nous avons observé un engagement positif lors de nos activités.",
    category: 'positive_opening',
    language: 'fr',
    description: 'Commencer les messages avec des observations positives',
    isSystem: true,
    usageCount: 38,
  },
  {
    id: 'template-5',
    title: 'Observation factuelle',
    content:
      "Pendant nos activités du matin, j'ai remarqué que {child_name} a montré de l'intérêt pour {activity}. Nous avons documenté ce moment pour le partager avec vous.",
    category: 'factual_observation',
    language: 'fr',
    description: 'Partager des observations factuelles sur les activités',
    isSystem: true,
    usageCount: 25,
  },
  {
    id: 'template-6',
    title: 'Demande de discussion collaborative',
    content:
      "J'aimerais discuter de la façon dont nous pouvons travailler ensemble pour soutenir le développement de {child_name} dans {area}. Auriez-vous le temps pour une brève conversation?",
    category: 'solution_oriented',
    language: 'fr',
    description: 'Inviter les parents à collaborer sur des solutions',
    isSystem: true,
    usageCount: 22,
  },
];

const categoryLabels: Record<TemplateCategory, { en: string; fr: string }> = {
  positive_opening: { en: 'Positive Opening', fr: 'Ouverture positive' },
  factual_observation: {
    en: 'Factual Observation',
    fr: 'Observation factuelle',
  },
  solution_oriented: { en: 'Solution Oriented', fr: 'Orienté solution' },
  collaborative_approach: {
    en: 'Collaborative Approach',
    fr: 'Approche collaborative',
  },
  general: { en: 'General', fr: 'Général' },
};

const categoryIcons: Record<TemplateCategory, JSX.Element> = {
  positive_opening: (
    <svg
      className="h-4 w-4"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
    >
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
      />
    </svg>
  ),
  factual_observation: (
    <svg
      className="h-4 w-4"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
    >
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
      />
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
      />
    </svg>
  ),
  solution_oriented: (
    <svg
      className="h-4 w-4"
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
  ),
  collaborative_approach: (
    <svg
      className="h-4 w-4"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
    >
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"
      />
    </svg>
  ),
  general: (
    <svg
      className="h-4 w-4"
      fill="none"
      stroke="currentColor"
      viewBox="0 0 24 24"
    >
      <path
        strokeLinecap="round"
        strokeLinejoin="round"
        strokeWidth={2}
        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
      />
    </svg>
  ),
};

export function MessageTemplateSelector({
  onSelect,
  category,
  language = 'en',
  className = '',
  disabled = false,
  variant = 'default',
}: MessageTemplateSelectorProps) {
  const [isOpen, setIsOpen] = useState(false);
  const [selectedCategory, setSelectedCategory] = useState<
    TemplateCategory | 'all'
  >(category || 'all');
  const [searchTerm, setSearchTerm] = useState('');
  const [templates, setTemplates] =
    useState<MessageTemplateResponse[]>(mockTemplates);
  const [isLoading, setIsLoading] = useState(false);

  // Filter templates based on category, language, and search term
  const filteredTemplates = templates.filter((template) => {
    const matchesCategory =
      selectedCategory === 'all' || template.category === selectedCategory;
    const matchesLanguage = template.language === language;
    const matchesSearch =
      searchTerm === '' ||
      template.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
      template.content.toLowerCase().includes(searchTerm.toLowerCase());
    return matchesCategory && matchesLanguage && matchesSearch;
  });

  // Group templates by category
  const groupedTemplates = filteredTemplates.reduce(
    (acc, template) => {
      if (!acc[template.category]) {
        acc[template.category] = [];
      }
      acc[template.category].push(template);
      return acc;
    },
    {} as Record<TemplateCategory, MessageTemplateResponse[]>
  );

  const handleSelect = useCallback(
    (template: MessageTemplateResponse) => {
      onSelect(template);
      setIsOpen(false);
      setSearchTerm('');
    },
    [onSelect]
  );

  const handleClose = useCallback(() => {
    setIsOpen(false);
    setSearchTerm('');
  }, []);

  // Close dropdown when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      const target = event.target as HTMLElement;
      if (isOpen && !target.closest('[data-template-selector]')) {
        handleClose();
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, [isOpen, handleClose]);

  // Close on escape key
  useEffect(() => {
    const handleEscape = (event: KeyboardEvent) => {
      if (event.key === 'Escape' && isOpen) {
        handleClose();
      }
    };

    document.addEventListener('keydown', handleEscape);
    return () => document.removeEventListener('keydown', handleEscape);
  }, [isOpen, handleClose]);

  const buttonClasses =
    variant === 'compact'
      ? 'flex items-center space-x-1 rounded-md border border-gray-200 bg-white px-2 py-1 text-xs font-medium text-gray-600 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50'
      : 'flex items-center space-x-2 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

  return (
    <div className={`relative ${className}`} data-template-selector>
      <button
        type="button"
        onClick={() => !disabled && setIsOpen(!isOpen)}
        disabled={disabled}
        className={buttonClasses}
        aria-label={
          language === 'fr' ? 'Sélectionner un modèle' : 'Select template'
        }
        aria-expanded={isOpen}
        aria-haspopup="listbox"
      >
        <svg
          className={variant === 'compact' ? 'h-3 w-3' : 'h-4 w-4'}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
          />
        </svg>
        <span className={variant === 'compact' ? 'hidden sm:inline' : ''}>
          {language === 'fr' ? 'Modèles' : 'Templates'}
        </span>
        <svg
          className={`${
            variant === 'compact' ? 'h-3 w-3' : 'h-4 w-4'
          } text-gray-400 transition-transform ${isOpen ? 'rotate-180' : ''}`}
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path
            strokeLinecap="round"
            strokeLinejoin="round"
            strokeWidth={2}
            d="M19 9l-7 7-7-7"
          />
        </svg>
      </button>

      {isOpen && (
        <div className="absolute right-0 z-20 mt-2 w-96 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
          {/* Search and Category Filter */}
          <div className="border-b border-gray-100 p-3">
            <div className="relative">
              <svg
                className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
              </svg>
              <input
                type="text"
                placeholder={
                  language === 'fr'
                    ? 'Rechercher des modèles...'
                    : 'Search templates...'
                }
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full rounded-md border border-gray-200 py-2 pl-9 pr-3 text-sm placeholder-gray-400 focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"
              />
            </div>

            {/* Category Tabs */}
            <div className="mt-3 flex flex-wrap gap-1">
              <button
                type="button"
                onClick={() => setSelectedCategory('all')}
                className={`rounded-full px-2.5 py-1 text-xs font-medium transition-colors ${
                  selectedCategory === 'all'
                    ? 'bg-primary-100 text-primary-700'
                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                }`}
              >
                {language === 'fr' ? 'Tous' : 'All'}
              </button>
              {(
                Object.keys(categoryLabels) as TemplateCategory[]
              ).map((cat) => (
                <button
                  key={cat}
                  type="button"
                  onClick={() => setSelectedCategory(cat)}
                  className={`flex items-center space-x-1 rounded-full px-2.5 py-1 text-xs font-medium transition-colors ${
                    selectedCategory === cat
                      ? 'bg-primary-100 text-primary-700'
                      : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
                  }`}
                >
                  {categoryIcons[cat]}
                  <span>{categoryLabels[cat][language]}</span>
                </button>
              ))}
            </div>
          </div>

          {/* Template List */}
          <div className="max-h-80 overflow-y-auto p-2" role="listbox">
            {isLoading ? (
              <div className="flex items-center justify-center py-8">
                <svg
                  className="h-6 w-6 animate-spin text-primary-500"
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
              </div>
            ) : filteredTemplates.length === 0 ? (
              <div className="py-8 text-center">
                <svg
                  className="mx-auto h-10 w-10 text-gray-300"
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                  />
                </svg>
                <p className="mt-2 text-sm text-gray-500">
                  {language === 'fr'
                    ? 'Aucun modèle trouvé'
                    : 'No templates found'}
                </p>
              </div>
            ) : selectedCategory === 'all' ? (
              // Show grouped by category when "All" is selected
              Object.entries(groupedTemplates).map(([cat, catTemplates]) => (
                <div key={cat} className="mb-3">
                  <p className="flex items-center space-x-1 px-3 py-1.5 text-xs font-semibold uppercase tracking-wider text-gray-500">
                    {categoryIcons[cat as TemplateCategory]}
                    <span>
                      {categoryLabels[cat as TemplateCategory][language]}
                    </span>
                  </p>
                  {catTemplates.map((template) => (
                    <TemplateItem
                      key={template.id}
                      template={template}
                      language={language}
                      onSelect={handleSelect}
                    />
                  ))}
                </div>
              ))
            ) : (
              // Show flat list when specific category is selected
              filteredTemplates.map((template) => (
                <TemplateItem
                  key={template.id}
                  template={template}
                  language={language}
                  onSelect={handleSelect}
                />
              ))
            )}
          </div>

          {/* Footer */}
          <div className="border-t border-gray-100 px-3 py-2">
            <p className="text-xs text-gray-400">
              {language === 'fr'
                ? `${filteredTemplates.length} modèle(s) disponible(s)`
                : `${filteredTemplates.length} template(s) available`}
            </p>
          </div>
        </div>
      )}
    </div>
  );
}

interface TemplateItemProps {
  template: MessageTemplateResponse;
  language: MessageLanguage;
  onSelect: (template: MessageTemplateResponse) => void;
}

function TemplateItem({ template, language, onSelect }: TemplateItemProps) {
  return (
    <button
      type="button"
      onClick={() => onSelect(template)}
      className="group flex w-full flex-col items-start rounded-md px-3 py-2 text-left transition-colors hover:bg-gray-50"
      role="option"
    >
      <div className="flex w-full items-center justify-between">
        <span className="text-sm font-medium text-gray-900 group-hover:text-primary-700">
          {template.title}
        </span>
        {template.isSystem && (
          <span className="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-500">
            {language === 'fr' ? 'Système' : 'System'}
          </span>
        )}
      </div>
      <p className="mt-0.5 line-clamp-2 text-xs text-gray-500">
        {template.content}
      </p>
      {template.description && (
        <p className="mt-1 text-[10px] italic text-gray-400">
          {template.description}
        </p>
      )}
      <div className="mt-1 flex items-center space-x-2 text-[10px] text-gray-400">
        <span className="flex items-center space-x-0.5">
          <svg
            className="h-3 w-3"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
            />
          </svg>
          <span>
            {language === 'fr'
              ? `${template.usageCount} utilisations`
              : `${template.usageCount} uses`}
          </span>
        </span>
      </div>
    </button>
  );
}
