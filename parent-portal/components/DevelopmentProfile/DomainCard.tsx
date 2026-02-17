import {
  DevelopmentalDomain,
  SkillAssessment,
  SkillStatus,
} from '../../lib/types';

/**
 * Props for the DomainCard component.
 */
export interface DomainCardProps {
  domain: DevelopmentalDomain;
  skills: SkillAssessment[];
  /** Optional click handler for when the card is clicked */
  onClick?: () => void;
  /** Whether to show all skills or just a summary */
  expanded?: boolean;
}

/**
 * Domain display information with bilingual support.
 */
interface DomainInfo {
  name: string;
  nameFr: string;
  description: string;
  colorClass: string;
  bgColorClass: string;
  progressColorClass: string;
  icon: React.ReactNode;
}

/**
 * Get display information for a developmental domain.
 */
function getDomainInfo(domain: DevelopmentalDomain): DomainInfo {
  switch (domain) {
    case 'affective':
      return {
        name: 'Affective Development',
        nameFr: 'Developpement affectif',
        description: 'Emotional expression, self-regulation, attachment, self-confidence',
        colorClass: 'text-pink-600',
        bgColorClass: 'bg-pink-100',
        progressColorClass: 'bg-pink-500',
        icon: (
          <svg
            className="h-6 w-6 text-pink-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"
            />
          </svg>
        ),
      };
    case 'social':
      return {
        name: 'Social Development',
        nameFr: 'Developpement social',
        description: 'Peer interactions, turn-taking, empathy, group participation',
        colorClass: 'text-blue-600',
        bgColorClass: 'bg-blue-100',
        progressColorClass: 'bg-blue-500',
        icon: (
          <svg
            className="h-6 w-6 text-blue-600"
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
      };
    case 'language':
      return {
        name: 'Language & Communication',
        nameFr: 'Langage et communication',
        description: 'Receptive/expressive language, speech clarity, emergent literacy',
        colorClass: 'text-purple-600',
        bgColorClass: 'bg-purple-100',
        progressColorClass: 'bg-purple-500',
        icon: (
          <svg
            className="h-6 w-6 text-purple-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
            />
          </svg>
        ),
      };
    case 'cognitive':
      return {
        name: 'Cognitive Development',
        nameFr: 'Developpement cognitif',
        description: 'Problem-solving, memory, attention, classification, number concept',
        colorClass: 'text-amber-600',
        bgColorClass: 'bg-amber-100',
        progressColorClass: 'bg-amber-500',
        icon: (
          <svg
            className="h-6 w-6 text-amber-600"
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
      };
    case 'gross_motor':
      return {
        name: 'Physical - Gross Motor',
        nameFr: 'Physique - Motricite globale',
        description: 'Balance, coordination, body awareness, outdoor skills',
        colorClass: 'text-green-600',
        bgColorClass: 'bg-green-100',
        progressColorClass: 'bg-green-500',
        icon: (
          <svg
            className="h-6 w-6 text-green-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M13 10V3L4 14h7v7l9-11h-7z"
            />
          </svg>
        ),
      };
    case 'fine_motor':
      return {
        name: 'Physical - Fine Motor',
        nameFr: 'Physique - Motricite fine',
        description: 'Hand-eye coordination, pencil grip, manipulation, self-care',
        colorClass: 'text-teal-600',
        bgColorClass: 'bg-teal-100',
        progressColorClass: 'bg-teal-500',
        icon: (
          <svg
            className="h-6 w-6 text-teal-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"
            />
          </svg>
        ),
      };
    default:
      return {
        name: 'Unknown Domain',
        nameFr: 'Domaine inconnu',
        description: '',
        colorClass: 'text-gray-600',
        bgColorClass: 'bg-gray-100',
        progressColorClass: 'bg-gray-500',
        icon: (
          <svg
            className="h-6 w-6 text-gray-600"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
            />
          </svg>
        ),
      };
  }
}

/**
 * Get display information for a skill status.
 */
function getStatusInfo(status: SkillStatus): {
  label: string;
  colorClass: string;
  bgColorClass: string;
  icon: React.ReactNode;
} {
  switch (status) {
    case 'can':
      return {
        label: 'Mastered',
        colorClass: 'text-green-700',
        bgColorClass: 'bg-green-100',
        icon: (
          <svg className="h-4 w-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
              clipRule="evenodd"
            />
          </svg>
        ),
      };
    case 'learning':
      return {
        label: 'Learning',
        colorClass: 'text-blue-700',
        bgColorClass: 'bg-blue-100',
        icon: (
          <svg className="h-4 w-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z"
              clipRule="evenodd"
            />
          </svg>
        ),
      };
    case 'not_yet':
      return {
        label: 'Not Yet',
        colorClass: 'text-amber-700',
        bgColorClass: 'bg-amber-100',
        icon: (
          <svg className="h-4 w-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 000 2h6a1 1 0 100-2H7z"
              clipRule="evenodd"
            />
          </svg>
        ),
      };
    case 'na':
      return {
        label: 'N/A',
        colorClass: 'text-gray-500',
        bgColorClass: 'bg-gray-100',
        icon: (
          <svg className="h-4 w-4 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
            <path
              fillRule="evenodd"
              d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
              clipRule="evenodd"
            />
          </svg>
        ),
      };
    default:
      return {
        label: 'Unknown',
        colorClass: 'text-gray-500',
        bgColorClass: 'bg-gray-100',
        icon: null,
      };
  }
}

/**
 * Calculate progress percentage for a domain based on skill assessments.
 * Only counts 'can' and 'learning' skills, excluding 'na' skills from the total.
 */
function calculateProgress(skills: SkillAssessment[]): {
  percentage: number;
  canCount: number;
  learningCount: number;
  notYetCount: number;
  naCount: number;
  totalTracked: number;
} {
  const canCount = skills.filter((s) => s.status === 'can').length;
  const learningCount = skills.filter((s) => s.status === 'learning').length;
  const notYetCount = skills.filter((s) => s.status === 'not_yet').length;
  const naCount = skills.filter((s) => s.status === 'na').length;
  const totalTracked = skills.length - naCount;

  // Calculate percentage: 'can' = 100%, 'learning' = 50%
  const weightedScore = canCount * 100 + learningCount * 50;
  const maxScore = totalTracked * 100;
  const percentage = maxScore > 0 ? Math.round(weightedScore / maxScore * 100) : 0;

  return {
    percentage,
    canCount,
    learningCount,
    notYetCount,
    naCount,
    totalTracked,
  };
}

/**
 * Single skill item display component.
 */
function SkillItem({ skill }: { skill: SkillAssessment }) {
  const statusInfo = getStatusInfo(skill.status);

  return (
    <div className="flex items-center justify-between py-2 border-b border-gray-100 last:border-b-0">
      <div className="flex-1 min-w-0">
        <p className="text-sm font-medium text-gray-900 truncate">
          {skill.skillName}
        </p>
        {skill.skillNameFr && (
          <p className="text-xs text-gray-500 truncate italic">
            {skill.skillNameFr}
          </p>
        )}
      </div>
      <div className="flex-shrink-0 ml-3">
        <span
          className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusInfo.bgColorClass} ${statusInfo.colorClass}`}
        >
          {statusInfo.icon}
          <span className="ml-1">{statusInfo.label}</span>
        </span>
      </div>
    </div>
  );
}

/**
 * Progress bar component.
 */
function ProgressBar({
  percentage,
  colorClass,
}: {
  percentage: number;
  colorClass: string;
}) {
  return (
    <div className="w-full bg-gray-200 rounded-full h-2.5">
      <div
        className={`h-2.5 rounded-full transition-all duration-300 ${colorClass}`}
        style={{ width: `${Math.min(100, Math.max(0, percentage))}%` }}
      />
    </div>
  );
}

/**
 * Empty state when no skills are tracked.
 */
function EmptyState() {
  return (
    <div className="text-center py-6">
      <svg
        className="mx-auto h-12 w-12 text-gray-400"
        fill="none"
        stroke="currentColor"
        viewBox="0 0 24 24"
      >
        <path
          strokeLinecap="round"
          strokeLinejoin="round"
          strokeWidth={2}
          d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
        />
      </svg>
      <p className="mt-2 text-sm text-gray-500">No skills assessed yet</p>
    </div>
  );
}

/**
 * DomainCard component displays a developmental domain with its progress
 * indicator and list of skill assessments.
 *
 * Used in the parent portal to visualize a child's developmental progress
 * in one of the 6 Quebec-aligned developmental domains.
 */
export function DomainCard({
  domain,
  skills,
  onClick,
  expanded = false,
}: DomainCardProps) {
  const domainInfo = getDomainInfo(domain);
  const progress = calculateProgress(skills);

  // Filter skills by domain (in case mixed skills are passed)
  const domainSkills = skills.filter((s) => s.domain === domain);
  const displaySkills = expanded ? domainSkills : domainSkills.slice(0, 3);
  const hasMoreSkills = !expanded && domainSkills.length > 3;

  const isClickable = !!onClick;

  return (
    <div
      className={`card ${isClickable ? 'cursor-pointer hover:shadow-md transition-shadow' : ''}`}
      onClick={onClick}
      role={isClickable ? 'button' : undefined}
      tabIndex={isClickable ? 0 : undefined}
      onKeyDown={
        isClickable
          ? (e) => {
              if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                onClick?.();
              }
            }
          : undefined
      }
    >
      {/* Card Header */}
      <div className="card-header">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div
              className={`flex h-12 w-12 items-center justify-center rounded-full ${domainInfo.bgColorClass}`}
            >
              {domainInfo.icon}
            </div>
            <div>
              <h3 className="text-lg font-semibold text-gray-900">
                {domainInfo.name}
              </h3>
              <p className="text-sm text-gray-500">{domainInfo.description}</p>
            </div>
          </div>
          {isClickable && (
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
                d="M9 5l7 7-7 7"
              />
            </svg>
          )}
        </div>
      </div>

      <div className="card-body">
        {/* Progress Section */}
        <div className="mb-4">
          <div className="flex items-center justify-between mb-2">
            <span className="text-sm font-medium text-gray-700">Progress</span>
            <span className="text-sm font-semibold text-gray-900">
              {progress.percentage}%
            </span>
          </div>
          <ProgressBar
            percentage={progress.percentage}
            colorClass={domainInfo.progressColorClass}
          />
        </div>

        {/* Stats Summary */}
        <div className="grid grid-cols-4 gap-2 mb-4">
          <div className="text-center p-2 bg-green-50 rounded-lg">
            <p className="text-lg font-bold text-green-700">{progress.canCount}</p>
            <p className="text-xs text-green-600">Mastered</p>
          </div>
          <div className="text-center p-2 bg-blue-50 rounded-lg">
            <p className="text-lg font-bold text-blue-700">{progress.learningCount}</p>
            <p className="text-xs text-blue-600">Learning</p>
          </div>
          <div className="text-center p-2 bg-amber-50 rounded-lg">
            <p className="text-lg font-bold text-amber-700">{progress.notYetCount}</p>
            <p className="text-xs text-amber-600">Not Yet</p>
          </div>
          <div className="text-center p-2 bg-gray-50 rounded-lg">
            <p className="text-lg font-bold text-gray-500">{progress.naCount}</p>
            <p className="text-xs text-gray-500">N/A</p>
          </div>
        </div>

        {/* Skills List */}
        {domainSkills.length > 0 ? (
          <div>
            <div className="flex items-center justify-between border-b border-gray-200 pb-2 mb-2">
              <h4 className="font-medium text-gray-900">Skills</h4>
              <span className="text-sm text-gray-500">
                {domainSkills.length} {domainSkills.length === 1 ? 'skill' : 'skills'}
              </span>
            </div>
            <div className="space-y-0">
              {displaySkills.map((skill) => (
                <SkillItem key={skill.id} skill={skill} />
              ))}
            </div>
            {hasMoreSkills && (
              <div className="mt-3 text-center">
                <span className="text-sm text-primary-600 font-medium">
                  +{domainSkills.length - 3} more skills
                </span>
              </div>
            )}
          </div>
        ) : (
          <EmptyState />
        )}
      </div>
    </div>
  );
}
