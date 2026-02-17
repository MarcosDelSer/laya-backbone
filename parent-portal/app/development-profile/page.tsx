'use client';

import { useState } from 'react';
import Link from 'next/link';
import { DomainCard } from '@/components/DevelopmentProfile/DomainCard';
import { MonthlySnapshot } from '@/components/DevelopmentProfile/MonthlySnapshot';
import { GrowthTrajectory } from '@/components/DevelopmentProfile/GrowthTrajectory';
import { ObservationForm } from '@/components/DevelopmentProfile/ObservationForm';
import {
  DevelopmentalDomain,
  SkillAssessment,
  MonthlySnapshot as MonthlySnapshotType,
  GrowthTrajectory as GrowthTrajectoryType,
  CreateObservationRequest,
} from '@/lib/types';

/**
 * All 6 Quebec developmental domains in display order.
 */
const ALL_DOMAINS: DevelopmentalDomain[] = [
  'affective',
  'social',
  'language',
  'cognitive',
  'gross_motor',
  'fine_motor',
];

// Mock data for development profile - will be replaced with API calls
const mockSkillAssessments: SkillAssessment[] = [
  // Affective Domain
  {
    id: 'skill-1',
    profileId: 'profile-1',
    domain: 'affective',
    skillName: 'Emotional Expression',
    skillNameFr: 'Expression emotionnelle',
    status: 'can',
    evidence: 'Consistently identifies and names emotions',
    assessedAt: '2026-02-10',
  },
  {
    id: 'skill-2',
    profileId: 'profile-1',
    domain: 'affective',
    skillName: 'Self-Regulation',
    skillNameFr: 'Autoregulation',
    status: 'learning',
    evidence: 'Learning to calm down with adult support',
    assessedAt: '2026-02-12',
  },
  {
    id: 'skill-3',
    profileId: 'profile-1',
    domain: 'affective',
    skillName: 'Self-Confidence',
    skillNameFr: 'Confiance en soi',
    status: 'can',
    evidence: 'Shows confidence in trying new activities',
    assessedAt: '2026-02-08',
  },
  // Social Domain
  {
    id: 'skill-4',
    profileId: 'profile-1',
    domain: 'social',
    skillName: 'Peer Interactions',
    skillNameFr: 'Interactions avec les pairs',
    status: 'can',
    evidence: 'Plays cooperatively with classmates',
    assessedAt: '2026-02-10',
  },
  {
    id: 'skill-5',
    profileId: 'profile-1',
    domain: 'social',
    skillName: 'Turn-Taking',
    skillNameFr: 'Tour de role',
    status: 'learning',
    evidence: 'Working on waiting for turn during games',
    assessedAt: '2026-02-11',
  },
  {
    id: 'skill-6',
    profileId: 'profile-1',
    domain: 'social',
    skillName: 'Empathy',
    skillNameFr: 'Empathie',
    status: 'can',
    evidence: 'Shows concern when peers are upset',
    assessedAt: '2026-02-09',
  },
  // Language Domain
  {
    id: 'skill-7',
    profileId: 'profile-1',
    domain: 'language',
    skillName: 'Receptive Language',
    skillNameFr: 'Langage receptif',
    status: 'can',
    evidence: 'Follows multi-step instructions',
    assessedAt: '2026-02-10',
  },
  {
    id: 'skill-8',
    profileId: 'profile-1',
    domain: 'language',
    skillName: 'Expressive Language',
    skillNameFr: 'Langage expressif',
    status: 'can',
    evidence: 'Uses complete sentences',
    assessedAt: '2026-02-11',
  },
  {
    id: 'skill-9',
    profileId: 'profile-1',
    domain: 'language',
    skillName: 'Emergent Literacy',
    skillNameFr: 'Litteratie emergente',
    status: 'learning',
    evidence: 'Learning to recognize letters',
    assessedAt: '2026-02-12',
  },
  // Cognitive Domain
  {
    id: 'skill-10',
    profileId: 'profile-1',
    domain: 'cognitive',
    skillName: 'Problem-Solving',
    skillNameFr: 'Resolution de problemes',
    status: 'learning',
    evidence: 'Working on solving puzzles independently',
    assessedAt: '2026-02-10',
  },
  {
    id: 'skill-11',
    profileId: 'profile-1',
    domain: 'cognitive',
    skillName: 'Memory',
    skillNameFr: 'Memoire',
    status: 'can',
    evidence: 'Remembers daily routines',
    assessedAt: '2026-02-09',
  },
  {
    id: 'skill-12',
    profileId: 'profile-1',
    domain: 'cognitive',
    skillName: 'Number Concept',
    skillNameFr: 'Concept de nombre',
    status: 'learning',
    evidence: 'Learning to count to 20',
    assessedAt: '2026-02-13',
  },
  // Gross Motor Domain
  {
    id: 'skill-13',
    profileId: 'profile-1',
    domain: 'gross_motor',
    skillName: 'Balance',
    skillNameFr: 'Equilibre',
    status: 'can',
    evidence: 'Balances on one foot for 5 seconds',
    assessedAt: '2026-02-08',
  },
  {
    id: 'skill-14',
    profileId: 'profile-1',
    domain: 'gross_motor',
    skillName: 'Coordination',
    skillNameFr: 'Coordination',
    status: 'can',
    evidence: 'Catches and throws ball',
    assessedAt: '2026-02-10',
  },
  {
    id: 'skill-15',
    profileId: 'profile-1',
    domain: 'gross_motor',
    skillName: 'Body Awareness',
    skillNameFr: 'Conscience corporelle',
    status: 'not_yet',
    evidence: 'Working on spatial awareness',
    assessedAt: '2026-02-12',
  },
  // Fine Motor Domain
  {
    id: 'skill-16',
    profileId: 'profile-1',
    domain: 'fine_motor',
    skillName: 'Pencil Grip',
    skillNameFr: 'Prise du crayon',
    status: 'learning',
    evidence: 'Developing tripod grip',
    assessedAt: '2026-02-11',
  },
  {
    id: 'skill-17',
    profileId: 'profile-1',
    domain: 'fine_motor',
    skillName: 'Hand-Eye Coordination',
    skillNameFr: 'Coordination oeil-main',
    status: 'can',
    evidence: 'Strings beads independently',
    assessedAt: '2026-02-09',
  },
  {
    id: 'skill-18',
    profileId: 'profile-1',
    domain: 'fine_motor',
    skillName: 'Self-Care Skills',
    skillNameFr: 'Autonomie',
    status: 'can',
    evidence: 'Buttons and zips independently',
    assessedAt: '2026-02-10',
  },
];

const mockSnapshot: MonthlySnapshotType = {
  id: 'snapshot-1',
  profileId: 'profile-1',
  snapshotMonth: '2026-02',
  ageMonths: 48,
  overallProgress: 'on_track',
  domainSummaries: {
    affective: {
      domain: 'affective',
      skillsCan: 2,
      skillsLearning: 1,
      skillsNotYet: 0,
      progressPercentage: 83,
      keyObservations: ['Shows empathy towards peers'],
    },
    social: {
      domain: 'social',
      skillsCan: 2,
      skillsLearning: 1,
      skillsNotYet: 0,
      progressPercentage: 83,
      keyObservations: ['Enjoys cooperative play'],
    },
    language: {
      domain: 'language',
      skillsCan: 2,
      skillsLearning: 1,
      skillsNotYet: 0,
      progressPercentage: 83,
      keyObservations: ['Vocabulary expanding rapidly'],
    },
    cognitive: {
      domain: 'cognitive',
      skillsCan: 1,
      skillsLearning: 2,
      skillsNotYet: 0,
      progressPercentage: 67,
      keyObservations: ['Shows curiosity in problem-solving'],
    },
    gross_motor: {
      domain: 'gross_motor',
      skillsCan: 2,
      skillsLearning: 0,
      skillsNotYet: 1,
      progressPercentage: 67,
      keyObservations: ['Active during outdoor play'],
    },
    fine_motor: {
      domain: 'fine_motor',
      skillsCan: 2,
      skillsLearning: 1,
      skillsNotYet: 0,
      progressPercentage: 83,
      keyObservations: ['Improving pencil control'],
    },
  },
  strengths: [
    'Strong emotional intelligence and empathy',
    'Excellent language development for age',
    'Active participation in group activities',
  ],
  growthAreas: [
    'Building spatial awareness',
    'Developing patience during turn-taking',
    'Strengthening pencil grip',
  ],
  recommendations:
    'Continue encouraging cooperative play and problem-solving activities. Focus on activities that develop spatial awareness and fine motor control.',
  isParentShared: true,
};

const mockTrajectory: GrowthTrajectoryType = {
  profileId: 'profile-1',
  childId: 'child-1',
  dataPoints: [
    {
      month: '2025-11',
      ageMonths: 45,
      domainScores: {
        affective: 65,
        social: 60,
        language: 70,
        cognitive: 55,
        gross_motor: 60,
        fine_motor: 55,
      },
      overallScore: 61,
    },
    {
      month: '2025-12',
      ageMonths: 46,
      domainScores: {
        affective: 70,
        social: 68,
        language: 75,
        cognitive: 60,
        gross_motor: 62,
        fine_motor: 60,
      },
      overallScore: 66,
    },
    {
      month: '2026-01',
      ageMonths: 47,
      domainScores: {
        affective: 78,
        social: 75,
        language: 80,
        cognitive: 65,
        gross_motor: 65,
        fine_motor: 70,
      },
      overallScore: 72,
    },
    {
      month: '2026-02',
      ageMonths: 48,
      domainScores: {
        affective: 83,
        social: 83,
        language: 83,
        cognitive: 67,
        gross_motor: 67,
        fine_motor: 83,
      },
      overallScore: 78,
    },
  ],
  trendAnalysis:
    'Child shows consistent improvement across all developmental domains over the past 4 months, with particularly strong gains in language and affective development.',
  alerts: [],
};

/**
 * Tab options for the development profile page.
 */
type TabOption = 'overview' | 'snapshots' | 'trajectory' | 'observe';

export default function DevelopmentProfilePage() {
  const [activeTab, setActiveTab] = useState<TabOption>('overview');
  const [expandedDomain, setExpandedDomain] = useState<DevelopmentalDomain | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  /**
   * Get skills filtered by domain.
   */
  const getSkillsByDomain = (domain: DevelopmentalDomain): SkillAssessment[] => {
    return mockSkillAssessments.filter((skill) => skill.domain === domain);
  };

  /**
   * Handle domain card click to expand/collapse.
   */
  const handleDomainClick = (domain: DevelopmentalDomain) => {
    setExpandedDomain(expandedDomain === domain ? null : domain);
  };

  /**
   * Handle observation form submission.
   */
  const handleObservationSubmit = async (observation: CreateObservationRequest) => {
    setIsSubmitting(true);
    // TODO: Replace with actual API call
    await new Promise((resolve) => setTimeout(resolve, 1000));
    setIsSubmitting(false);
    // Show success feedback (would use toast in production)
  };

  /**
   * Tab button component.
   */
  const TabButton = ({
    tab,
    label,
    icon,
  }: {
    tab: TabOption;
    label: string;
    icon: React.ReactNode;
  }) => (
    <button
      type="button"
      onClick={() => setActiveTab(tab)}
      className={`flex items-center px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
        activeTab === tab
          ? 'bg-primary text-white'
          : 'bg-white text-gray-600 hover:bg-gray-100'
      }`}
    >
      {icon}
      <span className="ml-2">{label}</span>
    </button>
  );

  return (
    <div className="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold text-gray-900">
              Development Profile
            </h1>
            <p className="mt-1 text-gray-600">
              Track your child&apos;s developmental progress across 6 Quebec-aligned domains
            </p>
          </div>
          <Link href="/" className="btn btn-outline">
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
            Back
          </Link>
        </div>
      </div>

      {/* Tab Navigation */}
      <div className="mb-6 flex flex-wrap gap-2">
        <TabButton
          tab="overview"
          label="Domain Overview"
          icon={
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"
              />
            </svg>
          }
        />
        <TabButton
          tab="snapshots"
          label="Monthly Snapshots"
          icon={
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"
              />
            </svg>
          }
        />
        <TabButton
          tab="trajectory"
          label="Growth Trajectory"
          icon={
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path
                strokeLinecap="round"
                strokeLinejoin="round"
                strokeWidth={2}
                d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
              />
            </svg>
          }
        />
        <TabButton
          tab="observe"
          label="Add Observation"
          icon={
            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
          }
        />
      </div>

      {/* Tab Content */}
      {activeTab === 'overview' && (
        <div className="space-y-6">
          {/* Quick Stats */}
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div className="card p-4 text-center">
              <p className="text-3xl font-bold text-green-600">
                {mockSkillAssessments.filter((s) => s.status === 'can').length}
              </p>
              <p className="text-sm text-gray-600">Skills Mastered</p>
            </div>
            <div className="card p-4 text-center">
              <p className="text-3xl font-bold text-blue-600">
                {mockSkillAssessments.filter((s) => s.status === 'learning').length}
              </p>
              <p className="text-sm text-gray-600">Currently Learning</p>
            </div>
            <div className="card p-4 text-center">
              <p className="text-3xl font-bold text-amber-600">
                {mockSkillAssessments.filter((s) => s.status === 'not_yet').length}
              </p>
              <p className="text-sm text-gray-600">Not Yet Started</p>
            </div>
            <div className="card p-4 text-center">
              <p className="text-3xl font-bold text-primary-600">
                {Math.round(mockTrajectory.dataPoints[mockTrajectory.dataPoints.length - 1]?.overallScore || 0)}%
              </p>
              <p className="text-sm text-gray-600">Overall Progress</p>
            </div>
          </div>

          {/* Domain Cards Grid */}
          <div>
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-lg font-semibold text-gray-900">
                Developmental Domains
              </h2>
              <span className="text-sm text-gray-500">
                {ALL_DOMAINS.length} domains
              </span>
            </div>
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
              {ALL_DOMAINS.map((domain) => (
                <DomainCard
                  key={domain}
                  domain={domain}
                  skills={getSkillsByDomain(domain)}
                  onClick={() => handleDomainClick(domain)}
                  expanded={expandedDomain === domain}
                />
              ))}
            </div>
          </div>
        </div>
      )}

      {activeTab === 'snapshots' && (
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold text-gray-900">
              Monthly Developmental Snapshots
            </h2>
            <span className="text-sm text-gray-500">
              1 snapshot
            </span>
          </div>
          <MonthlySnapshot snapshot={mockSnapshot} expanded />

          {/* Empty state for no snapshots */}
          {/* Commented out since we have mock data
          <div className="card p-12 text-center">
            <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
              <svg
                className="h-8 w-8 text-gray-400"
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
            </div>
            <h3 className="text-lg font-medium text-gray-900">
              No snapshots yet
            </h3>
            <p className="mt-2 text-gray-500">
              Monthly snapshots will appear here once they are generated by your child&apos;s educator.
            </p>
          </div>
          */}
        </div>
      )}

      {activeTab === 'trajectory' && (
        <div className="space-y-6">
          <div className="flex items-center justify-between">
            <h2 className="text-lg font-semibold text-gray-900">
              Growth Trajectory Analysis
            </h2>
            <span className="text-sm text-gray-500">
              {mockTrajectory.dataPoints.length} data points
            </span>
          </div>
          <GrowthTrajectory trajectory={mockTrajectory} expanded />
        </div>
      )}

      {activeTab === 'observe' && (
        <div className="space-y-6">
          <div>
            <h2 className="text-lg font-semibold text-gray-900 mb-2">
              Share an Observation
            </h2>
            <p className="text-sm text-gray-600 mb-6">
              Help track your child&apos;s development by sharing behaviors you observe at home.
              Your observations provide valuable insights for the educators.
            </p>
          </div>

          <ObservationForm
            profileId="profile-1"
            onSubmit={handleObservationSubmit}
            disabled={isSubmitting}
            placeholder="Describe what you observed your child doing. For example: 'Today at dinner, she counted all the peas on her plate up to 12 before eating them.'"
          />

          {/* Tips Section */}
          <div className="card">
            <div className="card-header">
              <h3 className="font-semibold text-gray-900">
                Tips for Effective Observations
              </h3>
            </div>
            <div className="card-body">
              <ul className="space-y-3 text-sm text-gray-600">
                <li className="flex items-start space-x-3">
                  <svg
                    className="h-5 w-5 flex-shrink-0 text-green-500 mt-0.5"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clipRule="evenodd"
                    />
                  </svg>
                  <span>
                    <strong>Be specific:</strong> Describe exactly what you saw rather than
                    making interpretations
                  </span>
                </li>
                <li className="flex items-start space-x-3">
                  <svg
                    className="h-5 w-5 flex-shrink-0 text-green-500 mt-0.5"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clipRule="evenodd"
                    />
                  </svg>
                  <span>
                    <strong>Include context:</strong> Note where and when the behavior occurred
                  </span>
                </li>
                <li className="flex items-start space-x-3">
                  <svg
                    className="h-5 w-5 flex-shrink-0 text-green-500 mt-0.5"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clipRule="evenodd"
                    />
                  </svg>
                  <span>
                    <strong>Mark milestones:</strong> Flag significant achievements like
                    first words or new skills
                  </span>
                </li>
                <li className="flex items-start space-x-3">
                  <svg
                    className="h-5 w-5 flex-shrink-0 text-green-500 mt-0.5"
                    fill="currentColor"
                    viewBox="0 0 20 20"
                  >
                    <path
                      fillRule="evenodd"
                      d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                      clipRule="evenodd"
                    />
                  </svg>
                  <span>
                    <strong>Share concerns:</strong> Note any behaviors that worry you so
                    educators can provide support
                  </span>
                </li>
              </ul>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
