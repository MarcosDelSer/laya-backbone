import Link from 'next/link';
import { notFound } from 'next/navigation';
import type {
  InterventionPlan,
  InterventionPlanStatus,
  GoalStatus,
  ProgressLevel,
  StrengthCategory,
  NeedCategory,
  NeedPriority,
  ResponsibleParty,
  MonitoringMethod,
  ParentActivityType,
  SpecialistType,
} from '@/lib/types';

// Mock data for a complete intervention plan - will be replaced with API calls
const mockPlan: InterventionPlan = {
  id: 'plan-1',
  childId: 'child-1',
  createdBy: 'educator-1',
  title: 'Speech and Language Development Plan',
  status: 'active',
  version: 2,

  // Part 1 - Identification & History
  childName: 'Emma Thompson',
  dateOfBirth: '2021-03-15',
  diagnosis: ['Speech delay', 'Receptive language disorder'],
  medicalHistory: 'Normal development until age 2. Hearing test normal.',
  educationalHistory: 'Enrolled in early intervention program at age 2.5.',
  familyContext: 'Two-parent household. English spoken at home. Older sibling (age 6) with typical development.',

  // Review scheduling
  reviewSchedule: 'quarterly',
  nextReviewDate: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],

  // Dates
  effectiveDate: new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
  endDate: new Date(Date.now() + 275 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],

  // Parent signature
  parentSigned: false,
  parentSignatureDate: undefined,

  // Part 2 - Strengths
  strengths: [
    {
      id: 'strength-1',
      planId: 'plan-1',
      category: 'social' as StrengthCategory,
      description: 'Excellent social engagement and desire to communicate with peers',
      examples: 'Initiates play with other children, uses gestures effectively',
      order: 1,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'strength-2',
      planId: 'plan-1',
      category: 'cognitive' as StrengthCategory,
      description: 'Strong problem-solving skills and visual learning abilities',
      examples: 'Completes age-appropriate puzzles, follows visual schedules well',
      order: 2,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'strength-3',
      planId: 'plan-1',
      category: 'physical' as StrengthCategory,
      description: 'Good fine and gross motor development',
      examples: 'Age-appropriate handwriting skills, active in physical play',
      order: 3,
      createdAt: new Date().toISOString(),
    },
  ],

  // Part 3 - Needs
  needs: [
    {
      id: 'need-1',
      planId: 'plan-1',
      category: 'communication' as NeedCategory,
      description: 'Expressive language development - needs support forming complete sentences',
      priority: 'high' as NeedPriority,
      baseline: 'Currently uses 2-3 word phrases',
      order: 1,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'need-2',
      planId: 'plan-1',
      category: 'communication' as NeedCategory,
      description: 'Articulation support - difficulty with specific speech sounds',
      priority: 'medium' as NeedPriority,
      baseline: 'Difficulty with /r/, /s/, and blends',
      order: 2,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'need-3',
      planId: 'plan-1',
      category: 'social' as NeedCategory,
      description: 'Social communication - understanding turn-taking in conversation',
      priority: 'medium' as NeedPriority,
      baseline: 'Sometimes interrupts or talks over others',
      order: 3,
      createdAt: new Date().toISOString(),
    },
  ],

  // Part 4 - SMART Goals
  goals: [
    {
      id: 'goal-1',
      planId: 'plan-1',
      needId: 'need-1',
      title: 'Increase sentence length',
      description: 'Emma will use 4-5 word sentences to express wants and needs',
      measurementCriteria: '80% of opportunities across 3 consecutive sessions',
      measurementBaseline: '2-3 word phrases',
      measurementTarget: '4-5 word sentences',
      achievabilityNotes: 'Gradual increase with scaffolding',
      relevanceNotes: 'Essential for classroom participation',
      targetDate: new Date(Date.now() + 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      status: 'in_progress' as GoalStatus,
      progressPercentage: 45,
      order: 1,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'goal-2',
      planId: 'plan-1',
      needId: 'need-2',
      title: 'Improve /s/ sound production',
      description: 'Emma will correctly produce the /s/ sound in initial position of words',
      measurementCriteria: '75% accuracy in structured activities',
      measurementBaseline: '30% accuracy',
      measurementTarget: '75% accuracy',
      achievabilityNotes: 'Focus on isolation first, then initial position',
      relevanceNotes: 'High-frequency sound in daily communication',
      targetDate: new Date(Date.now() + 60 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      status: 'in_progress' as GoalStatus,
      progressPercentage: 60,
      order: 2,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'goal-3',
      planId: 'plan-1',
      needId: 'need-3',
      title: 'Practice conversation turn-taking',
      description: 'Emma will take appropriate turns in conversation without interrupting',
      measurementCriteria: '4 out of 5 opportunities in small group settings',
      measurementBaseline: '1-2 out of 5 opportunities',
      measurementTarget: '4 out of 5 opportunities',
      achievabilityNotes: 'Use visual cues and social stories',
      relevanceNotes: 'Important for peer relationships',
      targetDate: new Date(Date.now() + 120 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      status: 'in_progress' as GoalStatus,
      progressPercentage: 35,
      order: 3,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'goal-4',
      planId: 'plan-1',
      needId: 'need-1',
      title: 'Expand vocabulary',
      description: 'Emma will use 20 new vocabulary words in context',
      measurementCriteria: 'Demonstrate use in spontaneous conversation',
      measurementBaseline: 'Limited vocabulary for age',
      measurementTarget: '20 new functional words',
      achievabilityNotes: 'Introduce 2-3 new words per week',
      relevanceNotes: 'Vocabulary supports sentence development',
      targetDate: new Date(Date.now() + 90 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      status: 'not_started' as GoalStatus,
      progressPercentage: 0,
      order: 4,
      createdAt: new Date().toISOString(),
    },
  ],

  // Part 5 - Strategies
  strategies: [
    {
      id: 'strategy-1',
      planId: 'plan-1',
      goalId: 'goal-1',
      title: 'Expansion technique',
      description: 'Model expanded sentences by adding 1-2 words to Emmas utterances',
      responsibleParty: 'team' as ResponsibleParty,
      frequency: 'Throughout the day',
      materialsNeeded: 'None required',
      accommodations: 'Allow extra processing time',
      order: 1,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'strategy-2',
      planId: 'plan-1',
      goalId: 'goal-2',
      title: 'Articulation practice activities',
      description: 'Daily practice with /s/ sound using picture cards and games',
      responsibleParty: 'educator' as ResponsibleParty,
      frequency: '10 minutes daily',
      materialsNeeded: 'Articulation cards, mirror, speech games',
      accommodations: 'One-on-one setting initially',
      order: 2,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'strategy-3',
      planId: 'plan-1',
      goalId: 'goal-3',
      title: 'Visual turn-taking cues',
      description: 'Use a talking stick or visual card to indicate whose turn it is to speak',
      responsibleParty: 'educator' as ResponsibleParty,
      frequency: 'During group activities',
      materialsNeeded: 'Talking stick, turn cards, social stories',
      accommodations: 'Small group settings preferred',
      order: 3,
      createdAt: new Date().toISOString(),
    },
  ],

  // Part 6 - Monitoring
  monitoring: [
    {
      id: 'monitoring-1',
      planId: 'plan-1',
      goalId: 'goal-1',
      method: 'data_collection' as MonitoringMethod,
      description: 'Track sentence length during structured activities',
      frequency: 'Weekly',
      responsibleParty: 'educator' as ResponsibleParty,
      dataCollectionTools: 'Language sample recording, tally chart',
      successIndicators: 'Consistent 4+ word sentences in 80% of opportunities',
      order: 1,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'monitoring-2',
      planId: 'plan-1',
      goalId: 'goal-2',
      method: 'assessment' as MonitoringMethod,
      description: 'Articulation probe for /s/ sound',
      frequency: 'Bi-weekly',
      responsibleParty: 'therapist' as ResponsibleParty,
      dataCollectionTools: 'Standardized articulation assessment',
      successIndicators: 'Improved accuracy percentages',
      order: 2,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'monitoring-3',
      planId: 'plan-1',
      method: 'observation' as MonitoringMethod,
      description: 'Observe turn-taking in natural settings',
      frequency: 'Daily informal, weekly formal',
      responsibleParty: 'team' as ResponsibleParty,
      dataCollectionTools: 'Observation checklist',
      successIndicators: 'Reduced interruptions, appropriate wait time',
      order: 3,
      createdAt: new Date().toISOString(),
    },
  ],

  // Part 7 - Parent Involvement
  parentInvolvements: [
    {
      id: 'parent-1',
      planId: 'plan-1',
      activityType: 'home_activity' as ParentActivityType,
      title: 'Daily reading time',
      description: 'Read with Emma for 15-20 minutes daily, modeling sentence expansion',
      frequency: 'Daily',
      resourcesProvided: 'Book list, expansion technique guide',
      communicationMethod: 'Weekly progress notes',
      order: 1,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'parent-2',
      planId: 'plan-1',
      activityType: 'communication' as ParentActivityType,
      title: 'Speech practice at home',
      description: 'Practice /s/ words using provided picture cards during playtime',
      frequency: '5-10 minutes, 3 times per week',
      resourcesProvided: 'Picture cards, practice guide, example videos',
      communicationMethod: 'Communication log',
      order: 2,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'parent-3',
      planId: 'plan-1',
      activityType: 'meeting' as ParentActivityType,
      title: 'Monthly progress meetings',
      description: 'Attend monthly meetings to review progress and adjust strategies',
      frequency: 'Monthly',
      resourcesProvided: 'Progress reports, goal updates',
      communicationMethod: 'In-person or video conference',
      order: 3,
      createdAt: new Date().toISOString(),
    },
  ],

  // Part 8 - External Consultations
  consultations: [
    {
      id: 'consultation-1',
      planId: 'plan-1',
      specialistType: 'speech_therapist' as SpecialistType,
      specialistName: 'Dr. Sarah Mitchell',
      organization: 'Speech & Language Associates',
      purpose: 'Ongoing speech therapy and progress monitoring',
      recommendations: 'Weekly therapy sessions, focus on expressive language',
      consultationDate: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      nextConsultationDate: new Date(Date.now() + 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      notes: 'Good progress with /s/ sound in isolation',
      order: 1,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'consultation-2',
      planId: 'plan-1',
      specialistType: 'pediatrician' as SpecialistType,
      specialistName: 'Dr. James Park',
      organization: 'Childrens Health Clinic',
      purpose: 'Annual developmental assessment',
      recommendations: 'Continue current interventions, re-evaluate in 6 months',
      consultationDate: new Date(Date.now() - 60 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      nextConsultationDate: new Date(Date.now() + 120 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      notes: 'Overall development on track',
      order: 2,
      createdAt: new Date().toISOString(),
    },
  ],

  // Progress records
  progressRecords: [
    {
      id: 'progress-1',
      planId: 'plan-1',
      goalId: 'goal-1',
      recordedBy: 'Ms. Johnson',
      recordDate: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      progressNotes: 'Emma used 4-word sentences in 6 out of 10 opportunities today.',
      progressLevel: 'moderate' as ProgressLevel,
      measurementValue: '60%',
      barriers: 'Fatigue in afternoon sessions',
      nextSteps: 'Schedule practice during morning hours',
      createdAt: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString(),
    },
    {
      id: 'progress-2',
      planId: 'plan-1',
      goalId: 'goal-2',
      recordedBy: 'Dr. Mitchell',
      recordDate: new Date(Date.now() - 14 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      progressNotes: 'Excellent progress with /s/ in isolation. Moving to initial position practice.',
      progressLevel: 'significant' as ProgressLevel,
      measurementValue: '90% in isolation',
      barriers: undefined,
      nextSteps: 'Begin initial position practice with simple words',
      createdAt: new Date(Date.now() - 14 * 24 * 60 * 60 * 1000).toISOString(),
    },
    {
      id: 'progress-3',
      planId: 'plan-1',
      goalId: 'goal-3',
      recordedBy: 'Ms. Johnson',
      recordDate: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
      progressNotes: 'Emma is responding well to the visual turn-taking cues.',
      progressLevel: 'minimal' as ProgressLevel,
      measurementValue: '2 out of 5 opportunities',
      barriers: 'Gets excited and forgets to wait',
      nextSteps: 'Add more visual prompts, practice in smaller groups',
      createdAt: new Date(Date.now() - 3 * 24 * 60 * 60 * 1000).toISOString(),
    },
  ],

  // Version history
  versions: [
    {
      id: 'version-1',
      planId: 'plan-1',
      versionNumber: 1,
      changeSummary: 'Initial plan creation',
      createdBy: 'Ms. Johnson',
      createdAt: new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString(),
    },
    {
      id: 'version-2',
      planId: 'plan-1',
      versionNumber: 2,
      changeSummary: 'Updated goals based on quarterly review. Added vocabulary goal.',
      createdBy: 'Ms. Johnson',
      createdAt: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString(),
    },
  ],

  createdAt: new Date(Date.now() - 90 * 24 * 60 * 60 * 1000).toISOString(),
  updatedAt: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString(),
};

// Helper functions
function getStatusBadgeClass(status: InterventionPlanStatus): string {
  switch (status) {
    case 'active':
      return 'badge-success';
    case 'draft':
      return 'badge-warning';
    case 'under_review':
      return 'badge-info';
    case 'completed':
      return 'badge-primary';
    case 'archived':
      return 'badge-neutral';
    default:
      return 'badge-neutral';
  }
}

function getStatusLabel(status: InterventionPlanStatus): string {
  switch (status) {
    case 'active':
      return 'Active';
    case 'draft':
      return 'Draft';
    case 'under_review':
      return 'Under Review';
    case 'completed':
      return 'Completed';
    case 'archived':
      return 'Archived';
    default:
      return status;
  }
}

function getGoalStatusBadgeClass(status: GoalStatus): string {
  switch (status) {
    case 'achieved':
      return 'badge-success';
    case 'in_progress':
      return 'badge-info';
    case 'not_started':
      return 'badge-neutral';
    case 'modified':
      return 'badge-warning';
    case 'discontinued':
      return 'badge-error';
    default:
      return 'badge-neutral';
  }
}

function getGoalStatusLabel(status: GoalStatus): string {
  switch (status) {
    case 'achieved':
      return 'Achieved';
    case 'in_progress':
      return 'In Progress';
    case 'not_started':
      return 'Not Started';
    case 'modified':
      return 'Modified';
    case 'discontinued':
      return 'Discontinued';
    default:
      return status;
  }
}

function getProgressLevelBadgeClass(level: ProgressLevel): string {
  switch (level) {
    case 'significant':
      return 'badge-success';
    case 'moderate':
      return 'badge-info';
    case 'minimal':
      return 'badge-warning';
    case 'no_progress':
      return 'badge-neutral';
    case 'achieved':
      return 'badge-primary';
    default:
      return 'badge-neutral';
  }
}

function getProgressLevelLabel(level: ProgressLevel): string {
  switch (level) {
    case 'significant':
      return 'Significant Progress';
    case 'moderate':
      return 'Moderate Progress';
    case 'minimal':
      return 'Minimal Progress';
    case 'no_progress':
      return 'No Progress';
    case 'achieved':
      return 'Goal Achieved';
    default:
      return level;
  }
}

function getPriorityBadgeClass(priority: NeedPriority): string {
  switch (priority) {
    case 'critical':
      return 'badge-error';
    case 'high':
      return 'badge-warning';
    case 'medium':
      return 'badge-info';
    case 'low':
      return 'badge-neutral';
    default:
      return 'badge-neutral';
  }
}

function formatCategory(category: string): string {
  return category
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ');
}

function formatDate(dateString: string): string {
  return new Date(dateString).toLocaleDateString('en-US', {
    month: 'long',
    day: 'numeric',
    year: 'numeric',
  });
}

function formatRelativeDate(dateString: string): string {
  const date = new Date(dateString);
  const today = new Date();
  const diffDays = Math.ceil((date.getTime() - today.getTime()) / (1000 * 60 * 60 * 24));

  if (diffDays === 0) {
    return 'Today';
  } else if (diffDays === 1) {
    return 'Tomorrow';
  } else if (diffDays === -1) {
    return 'Yesterday';
  } else if (diffDays < 0) {
    return `${Math.abs(diffDays)} days ago`;
  } else if (diffDays <= 7) {
    return `In ${diffDays} days`;
  }

  return formatDate(dateString);
}

function calculateAge(dateOfBirth: string): string {
  const dob = new Date(dateOfBirth);
  const today = new Date();
  const years = today.getFullYear() - dob.getFullYear();
  const months = today.getMonth() - dob.getMonth();

  if (months < 0 || (months === 0 && today.getDate() < dob.getDate())) {
    return `${years - 1} years, ${12 + months} months`;
  }
  return `${years} years, ${months} months`;
}

// Section components
function SectionHeader({ title, icon }: { title: string; icon: React.ReactNode }) {
  return (
    <div className="flex items-center space-x-3 mb-4">
      <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100">
        {icon}
      </div>
      <h2 className="text-xl font-semibold text-gray-900">{title}</h2>
    </div>
  );
}

interface InterventionPlanDetailPageProps {
  params: Promise<{ id: string }>;
}

export default async function InterventionPlanDetailPage({
  params,
}: InterventionPlanDetailPageProps) {
  const { id } = await params;

  // In production, this would fetch from API
  // For now, use mock data
  const plan = id === 'plan-1' ? mockPlan : null;

  if (!plan) {
    notFound();
  }

  const isOverdue = plan.nextReviewDate && new Date(plan.nextReviewDate) < new Date();

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div>
            <div className="flex items-center space-x-3 mb-2">
              <h1 className="text-2xl font-bold text-gray-900">{plan.title}</h1>
              <span className={`badge ${getStatusBadgeClass(plan.status)}`}>
                {getStatusLabel(plan.status)}
              </span>
            </div>
            <p className="text-gray-600">
              {plan.childName} &bull; Version {plan.version}
            </p>
          </div>
          <Link href="/intervention-plans" className="btn btn-outline">
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

      {/* Alert for signature required */}
      {!plan.parentSigned && plan.status === 'active' && (
        <div className="mb-6 p-4 bg-warning-50 border border-warning-200 rounded-lg flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <svg
              className="h-6 w-6 text-warning-600"
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
            <div>
              <p className="font-medium text-warning-800">Parent Signature Required</p>
              <p className="text-sm text-warning-700">
                Please review and sign this intervention plan to acknowledge your agreement.
              </p>
            </div>
          </div>
          <button type="button" className="btn btn-warning btn-sm" disabled>
            Sign Plan
          </button>
        </div>
      )}

      {/* Plan Overview Card */}
      <div className="card mb-6">
        <div className="card-header">
          <SectionHeader
            title="Plan Overview"
            icon={
              <svg
                className="h-5 w-5 text-primary-600"
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
            }
          />
        </div>
        <div className="card-body">
          <div className="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-4">
            <div className="text-center p-3 bg-gray-50 rounded-lg">
              <p className="text-2xl font-bold text-primary-600">{plan.goals.length}</p>
              <p className="text-xs text-gray-500">
                Goal{plan.goals.length !== 1 ? 's' : ''}
              </p>
            </div>
            <div className="text-center p-3 bg-gray-50 rounded-lg">
              <p className="text-2xl font-bold text-success-600">
                {plan.progressRecords.length}
              </p>
              <p className="text-xs text-gray-500">
                Progress Record{plan.progressRecords.length !== 1 ? 's' : ''}
              </p>
            </div>
            <div className="text-center p-3 bg-gray-50 rounded-lg">
              <p className="text-2xl font-bold text-info-600">{plan.strategies.length}</p>
              <p className="text-xs text-gray-500">
                Strateg{plan.strategies.length !== 1 ? 'ies' : 'y'}
              </p>
            </div>
            <div className="text-center p-3 bg-gray-50 rounded-lg">
              <p className="text-2xl font-bold text-secondary-600">
                {plan.consultations.length}
              </p>
              <p className="text-xs text-gray-500">
                Consultation{plan.consultations.length !== 1 ? 's' : ''}
              </p>
            </div>
          </div>

          {plan.nextReviewDate && (
            <div
              className={`flex items-center justify-between p-3 rounded-lg ${
                isOverdue ? 'bg-error-50' : 'bg-info-50'
              }`}
            >
              <div className="flex items-center space-x-2">
                <svg
                  className={`h-5 w-5 ${isOverdue ? 'text-error-600' : 'text-info-600'}`}
                  fill="none"
                  stroke="currentColor"
                  viewBox="0 0 24 24"
                >
                  <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"
                  />
                </svg>
                <span
                  className={`text-sm font-medium ${
                    isOverdue ? 'text-error-700' : 'text-info-700'
                  }`}
                >
                  Next Review: {formatRelativeDate(plan.nextReviewDate)}
                </span>
              </div>
              {isOverdue && <span className="badge badge-error">Overdue</span>}
            </div>
          )}

          <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
              <span className="text-gray-500">Effective Date:</span>{' '}
              <span className="font-medium">
                {plan.effectiveDate ? formatDate(plan.effectiveDate) : 'Not set'}
              </span>
            </div>
            <div>
              <span className="text-gray-500">End Date:</span>{' '}
              <span className="font-medium">
                {plan.endDate ? formatDate(plan.endDate) : 'Ongoing'}
              </span>
            </div>
            <div>
              <span className="text-gray-500">Review Schedule:</span>{' '}
              <span className="font-medium capitalize">
                {plan.reviewSchedule.replace('_', ' ')}
              </span>
            </div>
            <div className="flex items-center space-x-2">
              <span className="text-gray-500">Parent Signed:</span>
              {plan.parentSigned ? (
                <span className="flex items-center text-success-600">
                  <svg
                    className="h-4 w-4 mr-1"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M5 13l4 4L19 7"
                    />
                  </svg>
                  Yes
                </span>
              ) : (
                <span className="flex items-center text-warning-600">
                  <svg
                    className="h-4 w-4 mr-1"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                  >
                    <path
                      strokeLinecap="round"
                      strokeLinejoin="round"
                      strokeWidth={2}
                      d="M12 8v4m0 4h.01"
                    />
                  </svg>
                  Pending
                </span>
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Part 1: Child Identification */}
      <div className="card mb-6">
        <div className="card-header">
          <SectionHeader
            title="Child Identification"
            icon={
              <svg
                className="h-5 w-5 text-primary-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                />
              </svg>
            }
          />
        </div>
        <div className="card-body">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <p className="text-sm text-gray-500">Name</p>
              <p className="font-medium">{plan.childName}</p>
            </div>
            {plan.dateOfBirth && (
              <div>
                <p className="text-sm text-gray-500">Age</p>
                <p className="font-medium">{calculateAge(plan.dateOfBirth)}</p>
              </div>
            )}
            {plan.diagnosis && plan.diagnosis.length > 0 && (
              <div className="sm:col-span-2">
                <p className="text-sm text-gray-500">Diagnosis</p>
                <div className="flex flex-wrap gap-2 mt-1">
                  {plan.diagnosis.map((d, i) => (
                    <span key={i} className="badge badge-info">
                      {d}
                    </span>
                  ))}
                </div>
              </div>
            )}
            {plan.medicalHistory && (
              <div className="sm:col-span-2">
                <p className="text-sm text-gray-500">Medical History</p>
                <p className="text-gray-700">{plan.medicalHistory}</p>
              </div>
            )}
            {plan.educationalHistory && (
              <div className="sm:col-span-2">
                <p className="text-sm text-gray-500">Educational History</p>
                <p className="text-gray-700">{plan.educationalHistory}</p>
              </div>
            )}
            {plan.familyContext && (
              <div className="sm:col-span-2">
                <p className="text-sm text-gray-500">Family Context</p>
                <p className="text-gray-700">{plan.familyContext}</p>
              </div>
            )}
          </div>
        </div>
      </div>

      {/* Part 2: Strengths */}
      <div className="card mb-6">
        <div className="card-header">
          <SectionHeader
            title="Strengths"
            icon={
              <svg
                className="h-5 w-5 text-primary-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"
                />
              </svg>
            }
          />
        </div>
        <div className="card-body">
          {plan.strengths.length > 0 ? (
            <div className="space-y-4">
              {plan.strengths.map((strength) => (
                <div
                  key={strength.id}
                  className="p-4 bg-success-50 border border-success-100 rounded-lg"
                >
                  <div className="flex items-center justify-between mb-2">
                    <span className="badge badge-success">
                      {formatCategory(strength.category)}
                    </span>
                  </div>
                  <p className="text-gray-800 font-medium">{strength.description}</p>
                  {strength.examples && (
                    <p className="text-sm text-gray-600 mt-2">
                      <span className="font-medium">Examples:</span> {strength.examples}
                    </p>
                  )}
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">No strengths documented yet.</p>
          )}
        </div>
      </div>

      {/* Part 3: Needs */}
      <div className="card mb-6">
        <div className="card-header">
          <SectionHeader
            title="Areas of Need"
            icon={
              <svg
                className="h-5 w-5 text-primary-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"
                />
              </svg>
            }
          />
        </div>
        <div className="card-body">
          {plan.needs.length > 0 ? (
            <div className="space-y-4">
              {plan.needs.map((need) => (
                <div
                  key={need.id}
                  className="p-4 bg-gray-50 border border-gray-200 rounded-lg"
                >
                  <div className="flex items-center justify-between mb-2">
                    <span className="badge badge-info">
                      {formatCategory(need.category)}
                    </span>
                    <span className={`badge ${getPriorityBadgeClass(need.priority)}`}>
                      {formatCategory(need.priority)} Priority
                    </span>
                  </div>
                  <p className="text-gray-800 font-medium">{need.description}</p>
                  {need.baseline && (
                    <p className="text-sm text-gray-600 mt-2">
                      <span className="font-medium">Baseline:</span> {need.baseline}
                    </p>
                  )}
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">No needs documented yet.</p>
          )}
        </div>
      </div>

      {/* Part 4: SMART Goals */}
      <div className="card mb-6">
        <div className="card-header">
          <SectionHeader
            title="SMART Goals"
            icon={
              <svg
                className="h-5 w-5 text-primary-600"
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
            }
          />
        </div>
        <div className="card-body">
          {plan.goals.length > 0 ? (
            <div className="space-y-4">
              {plan.goals.map((goal) => (
                <div key={goal.id} className="p-4 border border-gray-200 rounded-lg">
                  <div className="flex items-center justify-between mb-2">
                    <h3 className="font-semibold text-gray-900">{goal.title}</h3>
                    <span className={`badge ${getGoalStatusBadgeClass(goal.status)}`}>
                      {getGoalStatusLabel(goal.status)}
                    </span>
                  </div>
                  <p className="text-gray-700 mb-3">{goal.description}</p>

                  {/* Progress bar */}
                  <div className="mb-3">
                    <div className="flex items-center justify-between text-sm mb-1">
                      <span className="text-gray-500">Progress</span>
                      <span className="font-medium">{goal.progressPercentage}%</span>
                    </div>
                    <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                      <div
                        className="h-full bg-primary-500 rounded-full transition-all duration-300"
                        style={{ width: `${goal.progressPercentage}%` }}
                      />
                    </div>
                  </div>

                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                    <div>
                      <span className="text-gray-500">Measurement:</span>{' '}
                      <span className="text-gray-700">{goal.measurementCriteria}</span>
                    </div>
                    {goal.targetDate && (
                      <div>
                        <span className="text-gray-500">Target Date:</span>{' '}
                        <span className="text-gray-700">
                          {formatDate(goal.targetDate)}
                        </span>
                      </div>
                    )}
                    {goal.measurementBaseline && (
                      <div>
                        <span className="text-gray-500">Baseline:</span>{' '}
                        <span className="text-gray-700">{goal.measurementBaseline}</span>
                      </div>
                    )}
                    {goal.measurementTarget && (
                      <div>
                        <span className="text-gray-500">Target:</span>{' '}
                        <span className="text-gray-700">{goal.measurementTarget}</span>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">No goals documented yet.</p>
          )}
        </div>
      </div>

      {/* Part 5: Strategies */}
      <div className="card mb-6">
        <div className="card-header">
          <SectionHeader
            title="Intervention Strategies"
            icon={
              <svg
                className="h-5 w-5 text-primary-600"
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
            }
          />
        </div>
        <div className="card-body">
          {plan.strategies.length > 0 ? (
            <div className="space-y-4">
              {plan.strategies.map((strategy) => (
                <div
                  key={strategy.id}
                  className="p-4 bg-gray-50 border border-gray-200 rounded-lg"
                >
                  <div className="flex items-center justify-between mb-2">
                    <h3 className="font-semibold text-gray-900">{strategy.title}</h3>
                    <span className="badge badge-neutral">
                      {formatCategory(strategy.responsibleParty)}
                    </span>
                  </div>
                  <p className="text-gray-700 mb-3">{strategy.description}</p>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                    {strategy.frequency && (
                      <div>
                        <span className="text-gray-500">Frequency:</span>{' '}
                        <span className="text-gray-700">{strategy.frequency}</span>
                      </div>
                    )}
                    {strategy.materialsNeeded && (
                      <div>
                        <span className="text-gray-500">Materials:</span>{' '}
                        <span className="text-gray-700">{strategy.materialsNeeded}</span>
                      </div>
                    )}
                    {strategy.accommodations && (
                      <div className="sm:col-span-2">
                        <span className="text-gray-500">Accommodations:</span>{' '}
                        <span className="text-gray-700">{strategy.accommodations}</span>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">
              No strategies documented yet.
            </p>
          )}
        </div>
      </div>

      {/* Part 6: Monitoring */}
      <div className="card mb-6">
        <div className="card-header">
          <SectionHeader
            title="Monitoring & Assessment"
            icon={
              <svg
                className="h-5 w-5 text-primary-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                />
              </svg>
            }
          />
        </div>
        <div className="card-body">
          {plan.monitoring.length > 0 ? (
            <div className="space-y-4">
              {plan.monitoring.map((monitor) => (
                <div
                  key={monitor.id}
                  className="p-4 bg-gray-50 border border-gray-200 rounded-lg"
                >
                  <div className="flex items-center justify-between mb-2">
                    <span className="badge badge-info">
                      {formatCategory(monitor.method)}
                    </span>
                    <span className="text-sm text-gray-500">{monitor.frequency}</span>
                  </div>
                  <p className="text-gray-700 mb-3">{monitor.description}</p>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                    <div>
                      <span className="text-gray-500">Responsible:</span>{' '}
                      <span className="text-gray-700">
                        {formatCategory(monitor.responsibleParty)}
                      </span>
                    </div>
                    {monitor.dataCollectionTools && (
                      <div>
                        <span className="text-gray-500">Tools:</span>{' '}
                        <span className="text-gray-700">
                          {monitor.dataCollectionTools}
                        </span>
                      </div>
                    )}
                    {monitor.successIndicators && (
                      <div className="sm:col-span-2">
                        <span className="text-gray-500">Success Indicators:</span>{' '}
                        <span className="text-gray-700">{monitor.successIndicators}</span>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">
              No monitoring approaches documented yet.
            </p>
          )}
        </div>
      </div>

      {/* Part 7: Parent Involvement */}
      <div className="card mb-6">
        <div className="card-header">
          <SectionHeader
            title="Parent Involvement"
            icon={
              <svg
                className="h-5 w-5 text-primary-600"
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
            }
          />
        </div>
        <div className="card-body">
          {plan.parentInvolvements.length > 0 ? (
            <div className="space-y-4">
              {plan.parentInvolvements.map((involvement) => (
                <div
                  key={involvement.id}
                  className="p-4 bg-secondary-50 border border-secondary-100 rounded-lg"
                >
                  <div className="flex items-center justify-between mb-2">
                    <h3 className="font-semibold text-gray-900">{involvement.title}</h3>
                    <span className="badge badge-secondary">
                      {formatCategory(involvement.activityType)}
                    </span>
                  </div>
                  <p className="text-gray-700 mb-3">{involvement.description}</p>
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                    {involvement.frequency && (
                      <div>
                        <span className="text-gray-500">Frequency:</span>{' '}
                        <span className="text-gray-700">{involvement.frequency}</span>
                      </div>
                    )}
                    {involvement.communicationMethod && (
                      <div>
                        <span className="text-gray-500">Communication:</span>{' '}
                        <span className="text-gray-700">
                          {involvement.communicationMethod}
                        </span>
                      </div>
                    )}
                    {involvement.resourcesProvided && (
                      <div className="sm:col-span-2">
                        <span className="text-gray-500">Resources:</span>{' '}
                        <span className="text-gray-700">
                          {involvement.resourcesProvided}
                        </span>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">
              No parent involvement activities documented yet.
            </p>
          )}
        </div>
      </div>

      {/* Part 8: External Consultations */}
      <div className="card mb-6">
        <div className="card-header">
          <SectionHeader
            title="External Consultations"
            icon={
              <svg
                className="h-5 w-5 text-primary-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"
                />
              </svg>
            }
          />
        </div>
        <div className="card-body">
          {plan.consultations.length > 0 ? (
            <div className="space-y-4">
              {plan.consultations.map((consultation) => (
                <div
                  key={consultation.id}
                  className="p-4 bg-gray-50 border border-gray-200 rounded-lg"
                >
                  <div className="flex items-center justify-between mb-2">
                    <span className="badge badge-primary">
                      {formatCategory(consultation.specialistType)}
                    </span>
                    {consultation.nextConsultationDate && (
                      <span className="text-sm text-gray-500">
                        Next: {formatRelativeDate(consultation.nextConsultationDate)}
                      </span>
                    )}
                  </div>
                  {(consultation.specialistName || consultation.organization) && (
                    <h3 className="font-semibold text-gray-900 mb-2">
                      {consultation.specialistName}
                      {consultation.organization && (
                        <span className="font-normal text-gray-600">
                          {' '}
                          &bull; {consultation.organization}
                        </span>
                      )}
                    </h3>
                  )}
                  <p className="text-gray-700 mb-3">{consultation.purpose}</p>
                  {consultation.recommendations && (
                    <div className="text-sm mb-2">
                      <span className="text-gray-500">Recommendations:</span>{' '}
                      <span className="text-gray-700">{consultation.recommendations}</span>
                    </div>
                  )}
                  {consultation.notes && (
                    <div className="text-sm">
                      <span className="text-gray-500">Notes:</span>{' '}
                      <span className="text-gray-700">{consultation.notes}</span>
                    </div>
                  )}
                </div>
              ))}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">
              No external consultations documented yet.
            </p>
          )}
        </div>
      </div>

      {/* Progress History */}
      <div className="card mb-6">
        <div className="card-header">
          <SectionHeader
            title="Progress History"
            icon={
              <svg
                className="h-5 w-5 text-primary-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"
                />
              </svg>
            }
          />
        </div>
        <div className="card-body">
          {plan.progressRecords.length > 0 ? (
            <div className="space-y-4">
              {plan.progressRecords.map((progress) => {
                const relatedGoal = plan.goals.find((g) => g.id === progress.goalId);
                return (
                  <div
                    key={progress.id}
                    className="p-4 border border-gray-200 rounded-lg"
                  >
                    <div className="flex items-center justify-between mb-2">
                      <div className="flex items-center space-x-3">
                        <span
                          className={`badge ${getProgressLevelBadgeClass(
                            progress.progressLevel
                          )}`}
                        >
                          {getProgressLevelLabel(progress.progressLevel)}
                        </span>
                        {relatedGoal && (
                          <span className="text-sm text-gray-600">
                            {relatedGoal.title}
                          </span>
                        )}
                      </div>
                      <span className="text-sm text-gray-500">
                        {formatRelativeDate(progress.recordDate)}
                      </span>
                    </div>
                    <p className="text-gray-700 mb-2">{progress.progressNotes}</p>
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-gray-500">
                        Recorded by {progress.recordedBy}
                      </span>
                      {progress.measurementValue && (
                        <span className="font-medium text-primary-600">
                          {progress.measurementValue}
                        </span>
                      )}
                    </div>
                    {progress.nextSteps && (
                      <div className="mt-2 text-sm bg-info-50 p-2 rounded">
                        <span className="font-medium text-info-700">Next Steps:</span>{' '}
                        <span className="text-info-800">{progress.nextSteps}</span>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">
              No progress records yet.
            </p>
          )}
        </div>
      </div>

      {/* Version History */}
      <div className="card">
        <div className="card-header">
          <SectionHeader
            title="Version History"
            icon={
              <svg
                className="h-5 w-5 text-primary-600"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
            }
          />
        </div>
        <div className="card-body">
          {plan.versions.length > 0 ? (
            <div className="space-y-3">
              {plan.versions
                .sort((a, b) => b.versionNumber - a.versionNumber)
                .map((version) => (
                  <div
                    key={version.id}
                    className="flex items-center justify-between p-3 bg-gray-50 rounded-lg"
                  >
                    <div className="flex items-center space-x-3">
                      <span className="badge badge-neutral">v{version.versionNumber}</span>
                      <div>
                        <p className="text-sm font-medium text-gray-900">
                          {version.changeSummary || 'No summary provided'}
                        </p>
                        <p className="text-xs text-gray-500">
                          By {version.createdBy}
                        </p>
                      </div>
                    </div>
                    <span className="text-sm text-gray-500">
                      {formatRelativeDate(version.createdAt)}
                    </span>
                  </div>
                ))}
            </div>
          ) : (
            <p className="text-gray-500 text-center py-4">No version history available.</p>
          )}
        </div>
      </div>
    </div>
  );
}
