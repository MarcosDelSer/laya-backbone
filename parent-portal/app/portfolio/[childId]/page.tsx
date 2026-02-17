'use client';

import Link from 'next/link';
import { useSearchParams } from 'next/navigation';
import { useState, useEffect } from 'react';
import { PortfolioCard } from '@/components/PortfolioCard';
import { PortfolioMediaGallery } from '@/components/PortfolioMediaGallery';
import { MilestoneTracker } from '@/components/MilestoneTracker';
import { ObservationCard } from '@/components/ObservationCard';
import { WorkSampleCard } from '@/components/WorkSampleCard';
import {
  Child,
  PortfolioItem,
  PortfolioSummary,
  Milestone,
  Observation,
  WorkSample,
} from '@/lib/types';

// Tab type definition
type TabType = 'all' | 'photos' | 'milestones' | 'observations' | 'work-samples';

// Mock children data - will be replaced with API calls
const mockChildren: Record<string, Child> = {
  'child-1': {
    id: 'child-1',
    firstName: 'Emma',
    lastName: 'Johnson',
    dateOfBirth: '2021-03-15',
    classroomId: 'classroom-1',
    classroomName: 'Sunshine Room',
    profilePhotoUrl: '',
  },
  'child-2': {
    id: 'child-2',
    firstName: 'Liam',
    lastName: 'Johnson',
    dateOfBirth: '2022-08-20',
    classroomId: 'classroom-2',
    classroomName: 'Rainbow Room',
    profilePhotoUrl: '',
  },
};

// Mock portfolio summary data - will be replaced with API calls
const mockPortfolioSummary: Record<string, PortfolioSummary> = {
  'child-1': {
    childId: 'child-1',
    totalItems: 24,
    totalObservations: 12,
    totalMilestones: 15,
    milestonesAchieved: 8,
    totalWorkSamples: 18,
    recentActivity: new Date().toISOString(),
  },
  'child-2': {
    childId: 'child-2',
    totalItems: 16,
    totalObservations: 8,
    totalMilestones: 10,
    milestonesAchieved: 4,
    totalWorkSamples: 10,
    recentActivity: new Date(Date.now() - 86400000).toISOString(),
  },
};

// Mock portfolio items - will be replaced with API calls
const mockPortfolioItems: Record<string, PortfolioItem[]> = {
  'child-1': [
    {
      id: 'item-1',
      childId: 'child-1',
      type: 'photo',
      title: 'First Day at Preschool',
      caption: 'Emma was so excited on her first day! She made new friends right away.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date().toISOString().split('T')[0],
      uploadedBy: 'Ms. Sarah',
      tags: ['milestone', 'first-day'],
      isPrivate: false,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'item-2',
      childId: 'child-1',
      type: 'artwork',
      title: 'Family Portrait Drawing',
      caption: 'A beautiful drawing of her family during art time.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date(Date.now() - 86400000).toISOString().split('T')[0],
      uploadedBy: 'Ms. Sarah',
      tags: ['art', 'creative', 'family'],
      isPrivate: false,
      createdAt: new Date(Date.now() - 86400000).toISOString(),
    },
    {
      id: 'item-3',
      childId: 'child-1',
      type: 'video',
      title: 'Learning to Count',
      caption: 'Emma practicing counting to 20 during circle time.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date(Date.now() - 172800000).toISOString().split('T')[0],
      uploadedBy: 'Ms. Sarah',
      tags: ['learning', 'math', 'achievement'],
      isPrivate: false,
      createdAt: new Date(Date.now() - 172800000).toISOString(),
    },
    {
      id: 'item-4',
      childId: 'child-1',
      type: 'document',
      title: 'Progress Report - Q1',
      caption: 'Quarterly progress report documenting development milestones.',
      mediaUrl: '',
      date: new Date(Date.now() - 259200000).toISOString().split('T')[0],
      uploadedBy: 'Ms. Sarah',
      tags: ['report', 'progress'],
      isPrivate: true,
      createdAt: new Date(Date.now() - 259200000).toISOString(),
    },
    {
      id: 'item-5',
      childId: 'child-1',
      type: 'photo',
      title: 'Outdoor Play Time',
      caption: 'Having fun on the playground with friends.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date(Date.now() - 345600000).toISOString().split('T')[0],
      uploadedBy: 'Ms. Sarah',
      tags: ['outdoor', 'play', 'social'],
      isPrivate: false,
      createdAt: new Date(Date.now() - 345600000).toISOString(),
    },
  ],
  'child-2': [
    {
      id: 'item-6',
      childId: 'child-2',
      type: 'photo',
      title: 'Playing with Blocks',
      caption: 'Liam built an impressive tower during free play.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date().toISOString().split('T')[0],
      uploadedBy: 'Ms. Katie',
      tags: ['play', 'building'],
      isPrivate: false,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'item-7',
      childId: 'child-2',
      type: 'artwork',
      title: 'Finger Painting',
      caption: 'Colorful exploration with finger paints.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date(Date.now() - 86400000).toISOString().split('T')[0],
      uploadedBy: 'Ms. Katie',
      tags: ['art', 'sensory'],
      isPrivate: false,
      createdAt: new Date(Date.now() - 86400000).toISOString(),
    },
  ],
};

// Mock milestones - will be replaced with API calls
const mockMilestones: Record<string, Milestone[]> = {
  'child-1': [
    {
      id: 'milestone-1',
      childId: 'child-1',
      domain: 'cognitive',
      title: 'Counts to 20',
      description: 'Can count from 1 to 20 independently with correct number sequence.',
      expectedAgeMonths: 48,
      status: 'achieved',
      achievedDate: new Date(Date.now() - 604800000).toISOString().split('T')[0],
      notes: 'Emma demonstrated this during morning circle time.',
      evidenceIds: ['item-3'],
      createdAt: new Date(Date.now() - 2592000000).toISOString(),
    },
    {
      id: 'milestone-2',
      childId: 'child-1',
      domain: 'social_emotional',
      title: 'Shares with Peers',
      description: 'Willingly shares toys and materials with other children.',
      expectedAgeMonths: 42,
      status: 'achieved',
      achievedDate: new Date(Date.now() - 1209600000).toISOString().split('T')[0],
      notes: 'Consistently shares during play time.',
      evidenceIds: [],
      createdAt: new Date(Date.now() - 2592000000).toISOString(),
    },
    {
      id: 'milestone-3',
      childId: 'child-1',
      domain: 'language',
      title: 'Uses Complete Sentences',
      description: 'Speaks in complete sentences with 5+ words.',
      expectedAgeMonths: 48,
      status: 'in_progress',
      notes: 'Making good progress, using 4-5 word sentences consistently.',
      evidenceIds: [],
      createdAt: new Date(Date.now() - 1728000000).toISOString(),
    },
    {
      id: 'milestone-4',
      childId: 'child-1',
      domain: 'physical',
      title: 'Hops on One Foot',
      description: 'Can hop on one foot for 5+ hops without losing balance.',
      expectedAgeMonths: 48,
      status: 'not_started',
      evidenceIds: [],
      createdAt: new Date(Date.now() - 1728000000).toISOString(),
    },
    {
      id: 'milestone-5',
      childId: 'child-1',
      domain: 'creative',
      title: 'Draws Recognizable Shapes',
      description: 'Can draw basic shapes like circles, squares, and triangles.',
      expectedAgeMonths: 42,
      status: 'achieved',
      achievedDate: new Date(Date.now() - 864000000).toISOString().split('T')[0],
      notes: 'Drew a beautiful family portrait with recognizable figures.',
      evidenceIds: ['item-2'],
      createdAt: new Date(Date.now() - 2160000000).toISOString(),
    },
  ],
  'child-2': [
    {
      id: 'milestone-6',
      childId: 'child-2',
      domain: 'cognitive',
      title: 'Stacks 6+ Blocks',
      description: 'Can stack at least 6 blocks without them falling.',
      expectedAgeMonths: 24,
      status: 'achieved',
      achievedDate: new Date().toISOString().split('T')[0],
      notes: 'Built an impressive tower today!',
      evidenceIds: ['item-6'],
      createdAt: new Date(Date.now() - 1296000000).toISOString(),
    },
    {
      id: 'milestone-7',
      childId: 'child-2',
      domain: 'creative',
      title: 'Explores Art Materials',
      description: 'Experiments with different art materials like paint and crayons.',
      expectedAgeMonths: 24,
      status: 'in_progress',
      notes: 'Loves finger painting!',
      evidenceIds: ['item-7'],
      createdAt: new Date(Date.now() - 1296000000).toISOString(),
    },
  ],
};

// Mock observations - will be replaced with API calls
const mockObservations: Record<string, Observation[]> = {
  'child-1': [
    {
      id: 'obs-1',
      childId: 'child-1',
      type: 'anecdotal',
      title: 'Helping a Friend',
      content: 'Today during free play, Emma noticed that her friend was struggling to put on her jacket. Without being asked, Emma walked over and helped zip up the jacket. She said, "I can help you! My mommy taught me how." This shows great empathy and social awareness.',
      date: new Date().toISOString().split('T')[0],
      observedBy: 'Ms. Sarah',
      domains: ['social_emotional'],
      linkedMilestones: ['milestone-2'],
      linkedWorkSamples: [],
      isPrivate: false,
      createdAt: new Date().toISOString(),
    },
    {
      id: 'obs-2',
      childId: 'child-1',
      type: 'learning_story',
      title: 'The Counting Journey',
      content: 'Over the past few weeks, Emma has been working hard on her counting skills. It started with counting blocks during building time, and now she counts everything - snack time crackers, steps on the playground, and friends at circle time. Her enthusiasm for numbers is infectious!',
      date: new Date(Date.now() - 172800000).toISOString().split('T')[0],
      observedBy: 'Ms. Sarah',
      domains: ['cognitive', 'language'],
      linkedMilestones: ['milestone-1'],
      linkedWorkSamples: [],
      isPrivate: false,
      createdAt: new Date(Date.now() - 172800000).toISOString(),
    },
    {
      id: 'obs-3',
      childId: 'child-1',
      type: 'running_record',
      title: 'Art Time Observation',
      content: '10:00 - Selected crayons and paper\n10:02 - Drew circle shapes repeatedly\n10:05 - Added arms and legs to circles "Making my family!"\n10:08 - Asked for more colors\n10:10 - Showed finished drawing to friends\n10:12 - Requested to hang on wall',
      date: new Date(Date.now() - 86400000).toISOString().split('T')[0],
      observedBy: 'Ms. Sarah',
      domains: ['creative', 'language'],
      linkedMilestones: ['milestone-5'],
      linkedWorkSamples: [],
      isPrivate: false,
      createdAt: new Date(Date.now() - 86400000).toISOString(),
    },
  ],
  'child-2': [
    {
      id: 'obs-4',
      childId: 'child-2',
      type: 'anecdotal',
      title: 'Block Tower Achievement',
      content: 'Liam spent 15 minutes focused on building a tower with blocks today. When it reached 8 blocks high, he clapped and said "Big tower!" When it fell, he laughed and immediately started rebuilding. Great persistence!',
      date: new Date().toISOString().split('T')[0],
      observedBy: 'Ms. Katie',
      domains: ['cognitive', 'physical'],
      linkedMilestones: ['milestone-6'],
      linkedWorkSamples: [],
      isPrivate: false,
      createdAt: new Date().toISOString(),
    },
  ],
};

// Mock work samples - will be replaced with API calls
const mockWorkSamples: Record<string, WorkSample[]> = {
  'child-1': [
    {
      id: 'ws-1',
      childId: 'child-1',
      type: 'drawing',
      title: 'My Family',
      description: 'A drawing of Emma\'s family including mom, dad, herself, and her dog.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date(Date.now() - 86400000).toISOString().split('T')[0],
      domains: ['creative', 'social_emotional'],
      teacherNotes: 'Emma showed great attention to detail, adding features like eyes, mouths, and hair to each family member.',
      isPrivate: false,
      createdAt: new Date(Date.now() - 86400000).toISOString(),
    },
    {
      id: 'ws-2',
      childId: 'child-1',
      type: 'writing',
      title: 'Name Practice',
      description: 'Emma\'s first successful attempt at writing her name.',
      mediaUrl: '',
      date: new Date(Date.now() - 432000000).toISOString().split('T')[0],
      domains: ['language', 'physical'],
      teacherNotes: 'Excellent pencil grip and letter formation. All letters are recognizable.',
      familyContribution: 'We practiced writing at home over the weekend!',
      isPrivate: false,
      createdAt: new Date(Date.now() - 432000000).toISOString(),
    },
    {
      id: 'ws-3',
      childId: 'child-1',
      type: 'craft',
      title: 'Paper Plate Sun',
      description: 'A craft project making a sun out of paper plates and tissue paper.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date(Date.now() - 604800000).toISOString().split('T')[0],
      domains: ['creative', 'physical'],
      teacherNotes: 'Emma worked independently and showed great fine motor skills when gluing the rays.',
      isPrivate: false,
      createdAt: new Date(Date.now() - 604800000).toISOString(),
    },
  ],
  'child-2': [
    {
      id: 'ws-4',
      childId: 'child-2',
      type: 'drawing',
      title: 'Finger Paint Creation',
      description: 'Liam\'s colorful finger painting exploration.',
      mediaUrl: '',
      thumbnailUrl: '',
      date: new Date(Date.now() - 86400000).toISOString().split('T')[0],
      domains: ['creative'],
      teacherNotes: 'Liam enjoyed exploring the textures and mixing colors together.',
      isPrivate: false,
      createdAt: new Date(Date.now() - 86400000).toISOString(),
    },
  ],
};

// Tab configuration
const tabs: { id: TabType; label: string; icon: React.ReactNode }[] = [
  {
    id: 'all',
    label: 'All',
    icon: (
      <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
      </svg>
    ),
  },
  {
    id: 'photos',
    label: 'Photos & Videos',
    icon: (
      <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
    ),
  },
  {
    id: 'milestones',
    label: 'Milestones',
    icon: (
      <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
      </svg>
    ),
  },
  {
    id: 'observations',
    label: 'Observations',
    icon: (
      <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
    ),
  },
  {
    id: 'work-samples',
    label: 'Work Samples',
    icon: (
      <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
      </svg>
    ),
  },
];

// Helper function to calculate age
function calculateAge(dateOfBirth: string): string {
  const today = new Date();
  const birthDate = new Date(dateOfBirth);
  let years = today.getFullYear() - birthDate.getFullYear();
  let months = today.getMonth() - birthDate.getMonth();

  if (months < 0) {
    years--;
    months += 12;
  }

  if (years < 1) {
    return `${months} month${months !== 1 ? 's' : ''}`;
  } else if (years < 2) {
    return months > 0 ? `${years} year, ${months} month${months !== 1 ? 's' : ''}` : `${years} year`;
  } else {
    return `${years} years${months > 0 ? `, ${months} month${months !== 1 ? 's' : ''}` : ''}`;
  }
}

interface PortfolioDetailPageProps {
  params: { childId: string };
}

export default function PortfolioDetailPage({ params }: PortfolioDetailPageProps) {
  const { childId } = params;
  const searchParams = useSearchParams();
  const tabParam = searchParams.get('tab') as TabType | null;
  const [activeTab, setActiveTab] = useState<TabType>(tabParam || 'all');

  // Update active tab when URL changes
  useEffect(() => {
    if (tabParam && tabs.some((t) => t.id === tabParam)) {
      setActiveTab(tabParam);
    }
  }, [tabParam]);

  // Get data for the child
  const child = mockChildren[childId];
  const summary = mockPortfolioSummary[childId];
  const portfolioItems = mockPortfolioItems[childId] || [];
  const milestones = mockMilestones[childId] || [];
  const observations = mockObservations[childId] || [];
  const workSamples = mockWorkSamples[childId] || [];

  // Handler functions
  const handleViewItem = (item: PortfolioItem) => {
    // Will navigate to item detail view
  };

  const handleEditItem = (item: PortfolioItem) => {
    // Will open edit modal
  };

  const handleDeleteItem = (item: PortfolioItem) => {
    // Will show confirmation and delete
  };

  const handleViewMilestone = (milestone: Milestone) => {
    // Will navigate to milestone detail view
  };

  const handleEditMilestone = (milestone: Milestone) => {
    // Will open edit modal
  };

  const handleDeleteMilestone = (milestone: Milestone) => {
    // Will show confirmation and delete
  };

  const handleAddEvidence = (milestone: Milestone) => {
    // Will open evidence upload modal
  };

  const handleViewObservation = (observation: Observation) => {
    // Will navigate to observation detail view
  };

  const handleEditObservation = (observation: Observation) => {
    // Will open edit modal
  };

  const handleDeleteObservation = (observation: Observation) => {
    // Will show confirmation and delete
  };

  const handleViewWorkSample = (workSample: WorkSample) => {
    // Will navigate to work sample detail view
  };

  const handleEditWorkSample = (workSample: WorkSample) => {
    // Will open edit modal
  };

  const handleDeleteWorkSample = (workSample: WorkSample) => {
    // Will show confirmation and delete
  };

  // Handle child not found
  if (!child) {
    return (
      <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
        <div className="card p-12 text-center">
          <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
            <svg
              className="h-8 w-8 text-red-600"
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
          </div>
          <h3 className="text-lg font-medium text-gray-900">Child Not Found</h3>
          <p className="mt-2 text-gray-500">
            We couldn&apos;t find the portfolio for this child.
          </p>
          <Link href="/portfolio" className="btn btn-primary mt-4">
            Back to Portfolio
          </Link>
        </div>
      </div>
    );
  }

  // Render tab content
  const renderTabContent = () => {
    switch (activeTab) {
      case 'photos':
        const mediaItems = portfolioItems.filter(
          (item) => item.type === 'photo' || item.type === 'video'
        );
        return (
          <div className="space-y-6">
            <h2 className="text-lg font-semibold text-gray-900">Photos & Videos</h2>
            {mediaItems.length > 0 ? (
              <>
                <PortfolioMediaGallery items={portfolioItems} maxDisplay={12} />
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                  {mediaItems.map((item) => (
                    <PortfolioCard
                      key={item.id}
                      item={item}
                      onView={handleViewItem}
                      onEdit={handleEditItem}
                      onDelete={handleDeleteItem}
                    />
                  ))}
                </div>
              </>
            ) : (
              <EmptyState
                title="No photos or videos yet"
                description="Photos and videos will appear here once they are added."
              />
            )}
          </div>
        );

      case 'milestones':
        return (
          <MilestoneTracker
            milestones={milestones}
            childName={child.firstName}
            onView={handleViewMilestone}
            onEdit={handleEditMilestone}
            onDelete={handleDeleteMilestone}
            onAddEvidence={handleAddEvidence}
          />
        );

      case 'observations':
        return (
          <div className="space-y-6">
            <h2 className="text-lg font-semibold text-gray-900">Observations</h2>
            {observations.length > 0 ? (
              <div className="space-y-6">
                {observations.map((observation) => (
                  <ObservationCard
                    key={observation.id}
                    observation={observation}
                    onView={handleViewObservation}
                    onEdit={handleEditObservation}
                    onDelete={handleDeleteObservation}
                  />
                ))}
              </div>
            ) : (
              <EmptyState
                title="No observations yet"
                description="Observations will appear here once they are recorded."
              />
            )}
          </div>
        );

      case 'work-samples':
        return (
          <div className="space-y-6">
            <h2 className="text-lg font-semibold text-gray-900">Work Samples</h2>
            {workSamples.length > 0 ? (
              <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                {workSamples.map((workSample) => (
                  <WorkSampleCard
                    key={workSample.id}
                    workSample={workSample}
                    onView={handleViewWorkSample}
                    onEdit={handleEditWorkSample}
                    onDelete={handleDeleteWorkSample}
                  />
                ))}
              </div>
            ) : (
              <EmptyState
                title="No work samples yet"
                description="Work samples will appear here once they are collected."
              />
            )}
          </div>
        );

      case 'all':
      default:
        return (
          <div className="space-y-8">
            {/* Recent Media Gallery */}
            <div>
              <div className="mb-4 flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-900">Recent Photos & Videos</h2>
                <button
                  type="button"
                  onClick={() => setActiveTab('photos')}
                  className="text-sm font-medium text-primary-600 hover:text-primary-700"
                >
                  View all
                </button>
              </div>
              <PortfolioMediaGallery items={portfolioItems} maxDisplay={6} />
            </div>

            {/* Recent Portfolio Items */}
            <div>
              <div className="mb-4 flex items-center justify-between">
                <h2 className="text-lg font-semibold text-gray-900">Recent Items</h2>
              </div>
              {portfolioItems.length > 0 ? (
                <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                  {portfolioItems.slice(0, 4).map((item) => (
                    <PortfolioCard
                      key={item.id}
                      item={item}
                      onView={handleViewItem}
                      onEdit={handleEditItem}
                      onDelete={handleDeleteItem}
                    />
                  ))}
                </div>
              ) : (
                <EmptyState
                  title="No portfolio items yet"
                  description="Portfolio items will appear here once they are added."
                />
              )}
            </div>

            {/* Recent Observations */}
            {observations.length > 0 && (
              <div>
                <div className="mb-4 flex items-center justify-between">
                  <h2 className="text-lg font-semibold text-gray-900">Recent Observations</h2>
                  <button
                    type="button"
                    onClick={() => setActiveTab('observations')}
                    className="text-sm font-medium text-primary-600 hover:text-primary-700"
                  >
                    View all
                  </button>
                </div>
                <div className="space-y-4">
                  {observations.slice(0, 2).map((observation) => (
                    <ObservationCard
                      key={observation.id}
                      observation={observation}
                      onView={handleViewObservation}
                    />
                  ))}
                </div>
              </div>
            )}

            {/* Milestone Summary */}
            {milestones.length > 0 && (
              <div>
                <div className="mb-4 flex items-center justify-between">
                  <h2 className="text-lg font-semibold text-gray-900">Milestone Progress</h2>
                  <button
                    type="button"
                    onClick={() => setActiveTab('milestones')}
                    className="text-sm font-medium text-primary-600 hover:text-primary-700"
                  >
                    View all
                  </button>
                </div>
                <MilestoneTracker
                  milestones={milestones}
                  childName={child.firstName}
                  onView={handleViewMilestone}
                />
              </div>
            )}
          </div>
        );
    }
  };

  return (
    <div className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
      {/* Header */}
      <div className="mb-8">
        <div className="flex items-center justify-between">
          <div className="flex items-center space-x-4">
            <Link href="/portfolio" className="btn btn-outline">
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
      </div>

      {/* Child Info Card */}
      <div className="card mb-8 p-6">
        <div className="flex items-center space-x-4">
          <div className="flex h-20 w-20 items-center justify-center rounded-full bg-primary-100">
            {child.profilePhotoUrl ? (
              <img
                src={child.profilePhotoUrl}
                alt={`${child.firstName} ${child.lastName}`}
                className="h-20 w-20 rounded-full object-cover"
              />
            ) : (
              <span className="text-3xl font-bold text-primary-600">
                {child.firstName[0]}{child.lastName[0]}
              </span>
            )}
          </div>
          <div className="flex-1">
            <h1 className="text-2xl font-bold text-gray-900">
              {child.firstName} {child.lastName}&apos;s Portfolio
            </h1>
            <p className="text-sm text-gray-600">{child.classroomName}</p>
            <p className="text-sm text-gray-500">Age: {calculateAge(child.dateOfBirth)}</p>
          </div>
        </div>

        {/* Summary Stats */}
        {summary && (
          <div className="mt-6 grid grid-cols-2 gap-4 border-t border-gray-100 pt-6 sm:grid-cols-5">
            <div className="text-center">
              <p className="text-2xl font-bold text-gray-900">{summary.totalItems}</p>
              <p className="text-xs text-gray-500">Media Items</p>
            </div>
            <div className="text-center">
              <p className="text-2xl font-bold text-gray-900">{summary.totalObservations}</p>
              <p className="text-xs text-gray-500">Observations</p>
            </div>
            <div className="text-center">
              <p className="text-2xl font-bold text-gray-900">
                {summary.milestonesAchieved}/{summary.totalMilestones}
              </p>
              <p className="text-xs text-gray-500">Milestones</p>
            </div>
            <div className="text-center">
              <p className="text-2xl font-bold text-gray-900">{summary.totalWorkSamples}</p>
              <p className="text-xs text-gray-500">Work Samples</p>
            </div>
            <div className="text-center col-span-2 sm:col-span-1">
              <p className="text-sm font-medium text-gray-900">
                {new Date(summary.recentActivity).toLocaleDateString('en-US', {
                  month: 'short',
                  day: 'numeric',
                })}
              </p>
              <p className="text-xs text-gray-500">Last Activity</p>
            </div>
          </div>
        )}
      </div>

      {/* Tabs Navigation */}
      <div className="mb-6 border-b border-gray-200">
        <nav className="-mb-px flex space-x-4 overflow-x-auto" aria-label="Tabs">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => setActiveTab(tab.id)}
              className={`flex items-center whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium transition-colors ${
                activeTab === tab.id
                  ? 'border-primary-500 text-primary-600'
                  : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'
              }`}
            >
              <span className="mr-2">{tab.icon}</span>
              {tab.label}
            </button>
          ))}
        </nav>
      </div>

      {/* Tab Content */}
      {renderTabContent()}
    </div>
  );
}

// Empty state component
function EmptyState({ title, description }: { title: string; description: string }) {
  return (
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
            d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"
          />
        </svg>
      </div>
      <h3 className="text-lg font-medium text-gray-900">{title}</h3>
      <p className="mt-2 text-gray-500">{description}</p>
    </div>
  );
}
