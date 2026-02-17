/**
 * Unit tests for Development Profile components
 * Tests the DomainCard, ObservationForm, MonthlySnapshot, and GrowthTrajectory components
 * Following Quebec-aligned 6-domain developmental tracking
 */

import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { DomainCard } from '@/components/DevelopmentProfile/DomainCard';
import { ObservationForm } from '@/components/DevelopmentProfile/ObservationForm';
import { MonthlySnapshot } from '@/components/DevelopmentProfile/MonthlySnapshot';
import { GrowthTrajectory } from '@/components/DevelopmentProfile/GrowthTrajectory';
import type {
  DevelopmentalDomain,
  SkillAssessment,
  MonthlySnapshot as MonthlySnapshotType,
  GrowthTrajectory as GrowthTrajectoryType,
  DomainSummary,
  GrowthDataPoint,
} from '@/lib/types';

// ============================================================================
// Test Data Factories
// ============================================================================

/**
 * Create a mock skill assessment for testing.
 */
function createMockSkillAssessment(
  overrides: Partial<SkillAssessment> = {}
): SkillAssessment {
  return {
    id: `skill-${Math.random().toString(36).substring(7)}`,
    profileId: 'profile-123',
    domain: 'affective',
    skillName: 'Identifies own emotions',
    skillNameFr: 'Identifie ses propres émotions',
    status: 'can',
    evidence: 'Can name feelings like happy, sad, angry',
    assessedAt: '2026-02-15',
    assessedById: 'educator-123',
    createdAt: '2026-02-15T10:00:00Z',
    updatedAt: '2026-02-15T10:00:00Z',
    ...overrides,
  };
}

/**
 * Create a mock domain summary for testing.
 */
function createMockDomainSummary(
  overrides: Partial<DomainSummary> = {}
): DomainSummary {
  return {
    domain: 'affective',
    skillsCan: 3,
    skillsLearning: 2,
    skillsNotYet: 1,
    progressPercentage: 67,
    keyObservations: ['Shows empathy towards peers'],
    ...overrides,
  };
}

/**
 * Create a mock monthly snapshot for testing.
 */
function createMockMonthlySnapshot(
  overrides: Partial<MonthlySnapshotType> = {}
): MonthlySnapshotType {
  return {
    id: 'snapshot-123',
    profileId: 'profile-123',
    snapshotMonth: '2026-02',
    ageMonths: 36,
    overallProgress: 'on_track',
    domainSummaries: {
      affective: createMockDomainSummary({ domain: 'affective' }),
      social: createMockDomainSummary({ domain: 'social', progressPercentage: 75 }),
      language: createMockDomainSummary({ domain: 'language', progressPercentage: 80 }),
      cognitive: createMockDomainSummary({ domain: 'cognitive', progressPercentage: 60 }),
      gross_motor: createMockDomainSummary({ domain: 'gross_motor', progressPercentage: 85 }),
      fine_motor: createMockDomainSummary({ domain: 'fine_motor', progressPercentage: 70 }),
    },
    strengths: ['Strong emotional regulation', 'Excellent fine motor skills'],
    growthAreas: ['Language development', 'Social interactions'],
    recommendations: 'Continue focusing on language activities',
    isParentShared: true,
    createdAt: '2026-02-15T10:00:00Z',
    updatedAt: '2026-02-15T10:00:00Z',
    ...overrides,
  };
}

/**
 * Create a mock growth data point for testing.
 */
function createMockGrowthDataPoint(
  overrides: Partial<GrowthDataPoint> = {}
): GrowthDataPoint {
  return {
    month: '2026-02',
    ageMonths: 36,
    overallScore: 70,
    domainScores: {
      affective: 67,
      social: 75,
      language: 80,
      cognitive: 60,
      gross_motor: 85,
      fine_motor: 70,
    },
    ...overrides,
  };
}

/**
 * Create a mock growth trajectory for testing.
 */
function createMockGrowthTrajectory(
  overrides: Partial<GrowthTrajectoryType> = {}
): GrowthTrajectoryType {
  return {
    profileId: 'profile-123',
    childId: 'child-123',
    dataPoints: [
      createMockGrowthDataPoint({ month: '2025-12', overallScore: 55 }),
      createMockGrowthDataPoint({ month: '2026-01', overallScore: 62 }),
      createMockGrowthDataPoint({ month: '2026-02', overallScore: 70 }),
    ],
    trendAnalysis: 'Showing consistent improvement across all domains',
    alerts: ['Language development is slightly below age expectations'],
    ...overrides,
  };
}

// ============================================================================
// DomainCard Component Tests
// ============================================================================

describe('DomainCard Component', () => {
  const domains: DevelopmentalDomain[] = [
    'affective',
    'social',
    'language',
    'cognitive',
    'gross_motor',
    'fine_motor',
  ];

  describe('renders correctly for all 6 Quebec developmental domains', () => {
    it.each(domains)('renders %s domain card correctly', (domain) => {
      const skills = [
        createMockSkillAssessment({ domain, status: 'can' }),
        createMockSkillAssessment({ domain, status: 'learning' }),
      ];

      render(<DomainCard domain={domain} skills={skills} />);

      // Check that a card element is rendered
      const card = document.querySelector('.card');
      expect(card).toBeInTheDocument();
    });
  });

  it('displays domain name and description', () => {
    const skills = [createMockSkillAssessment({ domain: 'affective' })];

    render(<DomainCard domain="affective" skills={skills} />);

    expect(screen.getByText('Affective Development')).toBeInTheDocument();
    expect(
      screen.getByText(/Emotional expression, self-regulation, attachment/)
    ).toBeInTheDocument();
  });

  it('calculates and displays progress correctly', () => {
    const skills = [
      createMockSkillAssessment({ domain: 'affective', status: 'can' }),
      createMockSkillAssessment({ domain: 'affective', status: 'can' }),
      createMockSkillAssessment({ domain: 'affective', status: 'learning' }),
      createMockSkillAssessment({ domain: 'affective', status: 'not_yet' }),
    ];

    render(<DomainCard domain="affective" skills={skills} />);

    // Progress should be: (2*100 + 1*50) / (4*100) * 100 = 250/400 * 100 = 62.5% -> 63%
    expect(screen.getByText('63%')).toBeInTheDocument();
  });

  it('displays skill status counts correctly', () => {
    const skills = [
      createMockSkillAssessment({ domain: 'affective', status: 'can' }),
      createMockSkillAssessment({ domain: 'affective', status: 'can' }),
      createMockSkillAssessment({ domain: 'affective', status: 'learning' }),
      createMockSkillAssessment({ domain: 'affective', status: 'not_yet' }),
      createMockSkillAssessment({ domain: 'affective', status: 'na' }),
    ];

    render(<DomainCard domain="affective" skills={skills} expanded />);

    // Check for status labels
    expect(screen.getByText('Mastered')).toBeInTheDocument();
    expect(screen.getByText('Learning')).toBeInTheDocument();
    expect(screen.getByText('Not Yet')).toBeInTheDocument();
    expect(screen.getByText('N/A')).toBeInTheDocument();
  });

  it('shows skill list when skills are provided', () => {
    const skills = [
      createMockSkillAssessment({
        domain: 'affective',
        skillName: 'Identifies own emotions',
      }),
    ];

    render(<DomainCard domain="affective" skills={skills} expanded />);

    expect(screen.getByText('Identifies own emotions')).toBeInTheDocument();
    expect(screen.getByText('Skills')).toBeInTheDocument();
  });

  it('shows empty state when no skills are provided', () => {
    render(<DomainCard domain="affective" skills={[]} />);

    expect(screen.getByText('No skills assessed yet')).toBeInTheDocument();
  });

  it('filters skills by domain', () => {
    const skills = [
      createMockSkillAssessment({ domain: 'affective', skillName: 'Affective Skill' }),
      createMockSkillAssessment({ domain: 'social', skillName: 'Social Skill' }),
    ];

    render(<DomainCard domain="affective" skills={skills} expanded />);

    // Should only show affective skill, not social
    expect(screen.getByText('Affective Skill')).toBeInTheDocument();
    expect(screen.queryByText('Social Skill')).not.toBeInTheDocument();
  });

  it('handles click events when onClick is provided', async () => {
    const handleClick = vi.fn();
    const skills = [createMockSkillAssessment({ domain: 'affective' })];

    render(<DomainCard domain="affective" skills={skills} onClick={handleClick} />);

    const card = document.querySelector('.card');
    expect(card).toHaveAttribute('role', 'button');
    expect(card).toHaveAttribute('tabIndex', '0');

    fireEvent.click(card!);
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('supports keyboard navigation with Enter key', () => {
    const handleClick = vi.fn();
    const skills = [createMockSkillAssessment({ domain: 'affective' })];

    render(<DomainCard domain="affective" skills={skills} onClick={handleClick} />);

    const card = document.querySelector('.card');
    fireEvent.keyDown(card!, { key: 'Enter' });
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('supports keyboard navigation with Space key', () => {
    const handleClick = vi.fn();
    const skills = [createMockSkillAssessment({ domain: 'affective' })];

    render(<DomainCard domain="affective" skills={skills} onClick={handleClick} />);

    const card = document.querySelector('.card');
    fireEvent.keyDown(card!, { key: ' ' });
    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('shows bilingual skill names when available', () => {
    const skills = [
      createMockSkillAssessment({
        domain: 'affective',
        skillName: 'Identifies emotions',
        skillNameFr: 'Identifie les émotions',
      }),
    ];

    render(<DomainCard domain="affective" skills={skills} expanded />);

    expect(screen.getByText('Identifies emotions')).toBeInTheDocument();
    expect(screen.getByText('Identifie les émotions')).toBeInTheDocument();
  });

  it('shows limited skills in non-expanded mode', () => {
    const skills = Array.from({ length: 5 }, (_, i) =>
      createMockSkillAssessment({
        domain: 'affective',
        skillName: `Skill ${i + 1}`,
        id: `skill-${i}`,
      })
    );

    render(<DomainCard domain="affective" skills={skills} expanded={false} />);

    // Should show first 3 skills and a "more" indicator
    expect(screen.getByText('Skill 1')).toBeInTheDocument();
    expect(screen.getByText('Skill 2')).toBeInTheDocument();
    expect(screen.getByText('Skill 3')).toBeInTheDocument();
    expect(screen.queryByText('Skill 4')).not.toBeInTheDocument();
    expect(screen.getByText('+2 more skills')).toBeInTheDocument();
  });

  it('shows all skills in expanded mode', () => {
    const skills = Array.from({ length: 5 }, (_, i) =>
      createMockSkillAssessment({
        domain: 'affective',
        skillName: `Skill ${i + 1}`,
        id: `skill-${i}`,
      })
    );

    render(<DomainCard domain="affective" skills={skills} expanded />);

    // Should show all skills
    expect(screen.getByText('Skill 1')).toBeInTheDocument();
    expect(screen.getByText('Skill 2')).toBeInTheDocument();
    expect(screen.getByText('Skill 3')).toBeInTheDocument();
    expect(screen.getByText('Skill 4')).toBeInTheDocument();
    expect(screen.getByText('Skill 5')).toBeInTheDocument();
    expect(screen.queryByText(/more skills/)).not.toBeInTheDocument();
  });

  it('excludes N/A skills from progress calculation', () => {
    const skills = [
      createMockSkillAssessment({ domain: 'affective', status: 'can' }),
      createMockSkillAssessment({ domain: 'affective', status: 'na' }),
      createMockSkillAssessment({ domain: 'affective', status: 'na' }),
    ];

    render(<DomainCard domain="affective" skills={skills} />);

    // Progress should be 100% since only 1 skill is tracked and it's mastered
    expect(screen.getByText('100%')).toBeInTheDocument();
  });
});

// ============================================================================
// ObservationForm Component Tests
// ============================================================================

describe('ObservationForm Component', () => {
  const defaultProps = {
    profileId: 'profile-123',
    onSubmit: vi.fn(),
  };

  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('renders the form with domain selection', () => {
    render(<ObservationForm {...defaultProps} />);

    expect(screen.getByText('Developmental Domain')).toBeInTheDocument();
    expect(screen.getByText('Affective Development')).toBeInTheDocument();
    expect(screen.getByText('Social Development')).toBeInTheDocument();
    expect(screen.getByText('Language & Communication')).toBeInTheDocument();
    expect(screen.getByText('Cognitive Development')).toBeInTheDocument();
    expect(screen.getByText('Physical - Gross Motor')).toBeInTheDocument();
    expect(screen.getByText('Physical - Fine Motor')).toBeInTheDocument();
  });

  it('renders behavior description textarea', () => {
    render(<ObservationForm {...defaultProps} />);

    expect(screen.getByText(/What did you observe/)).toBeInTheDocument();
    expect(
      screen.getByPlaceholderText('Describe what you observed...')
    ).toBeInTheDocument();
  });

  it('renders milestone and concern toggles', () => {
    render(<ObservationForm {...defaultProps} />);

    expect(screen.getByText('Milestone')).toBeInTheDocument();
    expect(screen.getByText('Concern')).toBeInTheDocument();
  });

  it('renders submit button', () => {
    render(<ObservationForm {...defaultProps} />);

    expect(screen.getByRole('button', { name: /Submit Observation/i })).toBeInTheDocument();
  });

  it('allows selecting a domain', async () => {
    render(<ObservationForm {...defaultProps} />);

    const affectiveButton = screen.getByText('Affective Development').closest('button');
    fireEvent.click(affectiveButton!);

    // Should show domain description
    expect(
      screen.getByText(/Emotional expression, self-regulation, attachment/)
    ).toBeInTheDocument();
  });

  it('validates form - requires domain and description', async () => {
    const onSubmit = vi.fn();
    render(<ObservationForm {...defaultProps} onSubmit={onSubmit} />);

    const submitButton = screen.getByRole('button', { name: /Submit Observation/i });

    // Button should be disabled when form is invalid
    expect(submitButton).toBeDisabled();
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it('shows validation error for short description', async () => {
    render(<ObservationForm {...defaultProps} />);

    const textarea = screen.getByPlaceholderText('Describe what you observed...');
    fireEvent.change(textarea, { target: { value: 'Short' } });

    expect(screen.getByText('Please provide at least 10 characters')).toBeInTheDocument();
  });

  it('enables submit when form is valid', async () => {
    const onSubmit = vi.fn();
    render(<ObservationForm {...defaultProps} onSubmit={onSubmit} />);

    // Select domain
    const affectiveButton = screen.getByText('Affective Development').closest('button');
    fireEvent.click(affectiveButton!);

    // Enter valid description
    const textarea = screen.getByPlaceholderText('Describe what you observed...');
    fireEvent.change(textarea, {
      target: { value: 'Child showed great emotional regulation when sharing toys with peers.' },
    });

    const submitButton = screen.getByRole('button', { name: /Submit Observation/i });
    expect(submitButton).not.toBeDisabled();
  });

  it('submits observation with correct data', async () => {
    const onSubmit = vi.fn();
    render(<ObservationForm {...defaultProps} onSubmit={onSubmit} />);

    // Select domain
    const affectiveButton = screen.getByText('Affective Development').closest('button');
    fireEvent.click(affectiveButton!);

    // Enter description
    const textarea = screen.getByPlaceholderText('Describe what you observed...');
    fireEvent.change(textarea, {
      target: { value: 'Child showed empathy when friend was sad.' },
    });

    // Submit form
    const submitButton = screen.getByRole('button', { name: /Submit Observation/i });
    fireEvent.click(submitButton);

    expect(onSubmit).toHaveBeenCalledTimes(1);
    expect(onSubmit).toHaveBeenCalledWith(
      expect.objectContaining({
        profileId: 'profile-123',
        domain: 'affective',
        behaviorDescription: 'Child showed empathy when friend was sad.',
        observerType: 'parent',
      })
    );
  });

  it('allows toggling milestone checkbox', async () => {
    const onSubmit = vi.fn();
    render(<ObservationForm {...defaultProps} onSubmit={onSubmit} />);

    // Select domain and enter description
    fireEvent.click(screen.getByText('Affective Development').closest('button')!);
    fireEvent.change(screen.getByPlaceholderText('Describe what you observed...'), {
      target: { value: 'First time child identified their emotions.' },
    });

    // Toggle milestone
    const milestoneLabel = screen.getByText('Milestone').closest('label');
    fireEvent.click(milestoneLabel!);

    // Submit and verify
    fireEvent.click(screen.getByRole('button', { name: /Submit Observation/i }));

    expect(onSubmit).toHaveBeenCalledWith(
      expect.objectContaining({
        isMilestone: true,
      })
    );
  });

  it('allows toggling concern checkbox', async () => {
    const onSubmit = vi.fn();
    render(<ObservationForm {...defaultProps} onSubmit={onSubmit} />);

    // Select domain and enter description
    fireEvent.click(screen.getByText('Affective Development').closest('button')!);
    fireEvent.change(screen.getByPlaceholderText('Describe what you observed...'), {
      target: { value: 'Child had difficulty regulating emotions.' },
    });

    // Toggle concern
    const concernLabel = screen.getByText('Concern').closest('label');
    fireEvent.click(concernLabel!);

    // Submit and verify
    fireEvent.click(screen.getByRole('button', { name: /Submit Observation/i }));

    expect(onSubmit).toHaveBeenCalledWith(
      expect.objectContaining({
        isConcern: true,
      })
    );
  });

  it('shows advanced options when toggled', () => {
    render(<ObservationForm {...defaultProps} />);

    // Click to show advanced options
    fireEvent.click(screen.getByText('Add more details'));

    expect(screen.getByText('Context (optional)')).toBeInTheDocument();
    expect(screen.getByText('When did you observe this?')).toBeInTheDocument();
  });

  it('includes context in submission when provided', async () => {
    const onSubmit = vi.fn();
    render(<ObservationForm {...defaultProps} onSubmit={onSubmit} />);

    // Fill required fields
    fireEvent.click(screen.getByText('Social Development').closest('button')!);
    fireEvent.change(screen.getByPlaceholderText('Describe what you observed...'), {
      target: { value: 'Child initiated play with another child.' },
    });

    // Expand and fill context
    fireEvent.click(screen.getByText('Add more details'));
    const contextInput = screen.getByPlaceholderText(
      /During dinner, At the park/
    );
    fireEvent.change(contextInput, { target: { value: 'At the playground' } });

    // Submit
    fireEvent.click(screen.getByRole('button', { name: /Submit Observation/i }));

    expect(onSubmit).toHaveBeenCalledWith(
      expect.objectContaining({
        context: 'At the playground',
      })
    );
  });

  it('resets form after successful submission', async () => {
    const onSubmit = vi.fn();
    render(<ObservationForm {...defaultProps} onSubmit={onSubmit} />);

    // Fill and submit form
    fireEvent.click(screen.getByText('Cognitive Development').closest('button')!);
    const textarea = screen.getByPlaceholderText('Describe what you observed...');
    fireEvent.change(textarea, {
      target: { value: 'Child completed puzzle independently.' },
    });

    fireEvent.click(screen.getByRole('button', { name: /Submit Observation/i }));

    // Textarea should be cleared
    expect(textarea).toHaveValue('');
  });

  it('disables form when disabled prop is true', () => {
    render(<ObservationForm {...defaultProps} disabled />);

    const textarea = screen.getByPlaceholderText('Describe what you observed...');
    expect(textarea).toBeDisabled();

    const submitButton = screen.getByRole('button', { name: /Submit Observation/i });
    expect(submitButton).toBeDisabled();
  });

  it('uses initial domain when provided', () => {
    render(<ObservationForm {...defaultProps} initialDomain="language" />);

    // Language domain should be selected - check for checkmark or selected state
    expect(
      screen.getByText(/Speech, vocabulary, emergent literacy/)
    ).toBeInTheDocument();
  });

  it('uses custom placeholder when provided', () => {
    render(
      <ObservationForm
        {...defaultProps}
        placeholder="What behavior did you notice today?"
      />
    );

    expect(
      screen.getByPlaceholderText('What behavior did you notice today?')
    ).toBeInTheDocument();
  });

  it('shows character count for description', () => {
    render(<ObservationForm {...defaultProps} />);

    const textarea = screen.getByPlaceholderText('Describe what you observed...');
    fireEvent.change(textarea, { target: { value: 'This is a test' } });

    expect(screen.getByText('14/1000')).toBeInTheDocument();
  });
});

// ============================================================================
// MonthlySnapshot Component Tests
// ============================================================================

describe('MonthlySnapshot Component', () => {
  it('renders snapshot month correctly', () => {
    const snapshot = createMockMonthlySnapshot();
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('February 2026')).toBeInTheDocument();
  });

  it('displays overall progress status badge', () => {
    const snapshot = createMockMonthlySnapshot({ overallProgress: 'on_track' });
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('On Track')).toBeInTheDocument();
  });

  it('displays needs_support progress status', () => {
    const snapshot = createMockMonthlySnapshot({ overallProgress: 'needs_support' });
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('Needs Support')).toBeInTheDocument();
  });

  it('displays excelling progress status', () => {
    const snapshot = createMockMonthlySnapshot({ overallProgress: 'excelling' });
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('Excelling')).toBeInTheDocument();
  });

  it('displays age when provided', () => {
    const snapshot = createMockMonthlySnapshot({ ageMonths: 36 });
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('Age: 3 years')).toBeInTheDocument();
  });

  it('displays domain progress for all 6 domains', () => {
    const snapshot = createMockMonthlySnapshot();
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('Affective')).toBeInTheDocument();
    expect(screen.getByText('Social')).toBeInTheDocument();
    expect(screen.getByText('Language')).toBeInTheDocument();
    expect(screen.getByText('Cognitive')).toBeInTheDocument();
    expect(screen.getByText('Gross Motor')).toBeInTheDocument();
    expect(screen.getByText('Fine Motor')).toBeInTheDocument();
  });

  it('displays summary statistics', () => {
    const snapshot = createMockMonthlySnapshot();
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('Skills Mastered')).toBeInTheDocument();
    expect(screen.getByText('Currently Learning')).toBeInTheDocument();
    expect(screen.getByText('Not Yet Started')).toBeInTheDocument();
  });

  it('displays strengths section', () => {
    const snapshot = createMockMonthlySnapshot({
      strengths: ['Excellent communication', 'Strong motor skills'],
    });
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('Strengths')).toBeInTheDocument();
    expect(screen.getByText('Excellent communication')).toBeInTheDocument();
    expect(screen.getByText('Strong motor skills')).toBeInTheDocument();
  });

  it('displays growth areas section', () => {
    const snapshot = createMockMonthlySnapshot({
      growthAreas: ['Social interactions', 'Emotional regulation'],
    });
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('Growth Areas')).toBeInTheDocument();
    expect(screen.getByText('Social interactions')).toBeInTheDocument();
    expect(screen.getByText('Emotional regulation')).toBeInTheDocument();
  });

  it('displays educator recommendations', () => {
    const snapshot = createMockMonthlySnapshot({
      recommendations: 'Focus on language activities and peer interactions.',
    });
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('Educator Recommendations')).toBeInTheDocument();
    expect(
      screen.getByText('Focus on language activities and peer interactions.')
    ).toBeInTheDocument();
  });

  it('shows parent sharing status', () => {
    const snapshot = createMockMonthlySnapshot({ isParentShared: true });
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('Shared with parents')).toBeInTheDocument();
  });

  it('handles click events when onClick is provided', () => {
    const handleClick = vi.fn();
    const snapshot = createMockMonthlySnapshot();

    render(<MonthlySnapshot snapshot={snapshot} onClick={handleClick} />);

    const card = document.querySelector('.card');
    fireEvent.click(card!);

    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('has keyboard accessibility with Enter key', () => {
    const handleClick = vi.fn();
    const snapshot = createMockMonthlySnapshot();

    render(<MonthlySnapshot snapshot={snapshot} onClick={handleClick} />);

    const card = document.querySelector('.card');
    fireEvent.keyDown(card!, { key: 'Enter' });

    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('limits displayed items in non-expanded mode', () => {
    const snapshot = createMockMonthlySnapshot({
      strengths: ['Strength 1', 'Strength 2', 'Strength 3', 'Strength 4', 'Strength 5'],
    });

    render(<MonthlySnapshot snapshot={snapshot} expanded={false} />);

    expect(screen.getByText('Strength 1')).toBeInTheDocument();
    expect(screen.getByText('Strength 2')).toBeInTheDocument();
    expect(screen.getByText('Strength 3')).toBeInTheDocument();
    expect(screen.queryByText('Strength 4')).not.toBeInTheDocument();
    expect(screen.getByText('+2 more')).toBeInTheDocument();
  });

  it('shows all items in expanded mode', () => {
    const snapshot = createMockMonthlySnapshot({
      strengths: ['Strength 1', 'Strength 2', 'Strength 3', 'Strength 4', 'Strength 5'],
    });

    render(<MonthlySnapshot snapshot={snapshot} expanded />);

    expect(screen.getByText('Strength 1')).toBeInTheDocument();
    expect(screen.getByText('Strength 2')).toBeInTheDocument();
    expect(screen.getByText('Strength 3')).toBeInTheDocument();
    expect(screen.getByText('Strength 4')).toBeInTheDocument();
    expect(screen.getByText('Strength 5')).toBeInTheDocument();
    expect(screen.queryByText(/more/)).not.toBeInTheDocument();
  });

  it('shows empty state for missing strengths', () => {
    const snapshot = createMockMonthlySnapshot({ strengths: [] });
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('No strengths noted')).toBeInTheDocument();
  });

  it('shows empty state for missing growth areas', () => {
    const snapshot = createMockMonthlySnapshot({ growthAreas: [] });
    render(<MonthlySnapshot snapshot={snapshot} />);

    expect(screen.getByText('No growth areas noted')).toBeInTheDocument();
  });
});

// ============================================================================
// GrowthTrajectory Component Tests
// ============================================================================

describe('GrowthTrajectory Component', () => {
  it('renders component header', () => {
    const trajectory = createMockGrowthTrajectory();
    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(screen.getByText('Growth Trajectory')).toBeInTheDocument();
    expect(screen.getByText('Development trends over time')).toBeInTheDocument();
  });

  it('displays trend indicator for improving trajectory', () => {
    const trajectory = createMockGrowthTrajectory({
      dataPoints: [
        createMockGrowthDataPoint({ month: '2026-01', overallScore: 50 }),
        createMockGrowthDataPoint({ month: '2026-02', overallScore: 70 }),
      ],
    });

    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(screen.getByText('Improving')).toBeInTheDocument();
  });

  it('displays trend indicator for stable trajectory', () => {
    const trajectory = createMockGrowthTrajectory({
      dataPoints: [
        createMockGrowthDataPoint({ month: '2026-01', overallScore: 65 }),
        createMockGrowthDataPoint({ month: '2026-02', overallScore: 68 }),
      ],
    });

    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(screen.getByText('Stable')).toBeInTheDocument();
  });

  it('displays trend indicator for declining trajectory', () => {
    const trajectory = createMockGrowthTrajectory({
      dataPoints: [
        createMockGrowthDataPoint({ month: '2026-01', overallScore: 75 }),
        createMockGrowthDataPoint({ month: '2026-02', overallScore: 55 }),
      ],
    });

    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(screen.getByText('Needs Attention')).toBeInTheDocument();
  });

  it('displays empty state when no data points', () => {
    const trajectory = createMockGrowthTrajectory({ dataPoints: [] });
    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(
      screen.getByText(/No growth data available yet/)
    ).toBeInTheDocument();
  });

  it('displays current score', () => {
    const trajectory = createMockGrowthTrajectory();
    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(screen.getByText('70%')).toBeInTheDocument();
    expect(screen.getByText('Current Score')).toBeInTheDocument();
  });

  it('displays data points count', () => {
    const trajectory = createMockGrowthTrajectory();
    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(screen.getByText('3')).toBeInTheDocument();
    expect(screen.getByText('Data Points')).toBeInTheDocument();
  });

  it('displays current age when available', () => {
    const trajectory = createMockGrowthTrajectory();
    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(screen.getByText('3yr')).toBeInTheDocument();
    expect(screen.getByText('Current Age')).toBeInTheDocument();
  });

  it('displays age-appropriate expectations', () => {
    const trajectory = createMockGrowthTrajectory({
      dataPoints: [
        createMockGrowthDataPoint({ month: '2026-02', ageMonths: 36 }),
      ],
    });

    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(screen.getByText('Age-Appropriate Expectations')).toBeInTheDocument();
    expect(
      screen.getByText(/Preschoolers develop problem-solving/)
    ).toBeInTheDocument();
  });

  it('displays progress by domain section', () => {
    const trajectory = createMockGrowthTrajectory();
    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(screen.getByText('Progress by Domain')).toBeInTheDocument();
  });

  it('displays all 6 domains in progress section', () => {
    const trajectory = createMockGrowthTrajectory();
    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(screen.getByText('Affective')).toBeInTheDocument();
    expect(screen.getByText('Social')).toBeInTheDocument();
    expect(screen.getByText('Language')).toBeInTheDocument();
    expect(screen.getByText('Cognitive')).toBeInTheDocument();
    expect(screen.getByText('Gross Motor')).toBeInTheDocument();
    expect(screen.getByText('Fine Motor')).toBeInTheDocument();
  });

  it('displays trend analysis when available', () => {
    const trajectory = createMockGrowthTrajectory({
      trendAnalysis: 'Child shows consistent improvement in all domains.',
    });

    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(screen.getByText('Trend Analysis')).toBeInTheDocument();
    expect(
      screen.getByText('Child shows consistent improvement in all domains.')
    ).toBeInTheDocument();
  });

  it('displays alerts when available', () => {
    const trajectory = createMockGrowthTrajectory({
      alerts: ['Language development needs attention', 'Consider speech therapy assessment'],
    });

    render(<GrowthTrajectory trajectory={trajectory} />);

    expect(screen.getByText('Alerts & Recommendations')).toBeInTheDocument();
    expect(screen.getByText('Language development needs attention')).toBeInTheDocument();
    expect(screen.getByText('Consider speech therapy assessment')).toBeInTheDocument();
  });

  it('handles click events when onClick is provided', () => {
    const handleClick = vi.fn();
    const trajectory = createMockGrowthTrajectory();

    render(<GrowthTrajectory trajectory={trajectory} onClick={handleClick} />);

    const card = document.querySelector('.card');
    fireEvent.click(card!);

    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('has keyboard accessibility with Enter key', () => {
    const handleClick = vi.fn();
    const trajectory = createMockGrowthTrajectory();

    render(<GrowthTrajectory trajectory={trajectory} onClick={handleClick} />);

    const card = document.querySelector('.card');
    fireEvent.keyDown(card!, { key: 'Enter' });

    expect(handleClick).toHaveBeenCalledTimes(1);
  });

  it('limits alerts in non-expanded mode', () => {
    const trajectory = createMockGrowthTrajectory({
      alerts: ['Alert 1', 'Alert 2', 'Alert 3', 'Alert 4', 'Alert 5'],
    });

    render(<GrowthTrajectory trajectory={trajectory} expanded={false} />);

    expect(screen.getByText('Alert 1')).toBeInTheDocument();
    expect(screen.getByText('Alert 2')).toBeInTheDocument();
    expect(screen.getByText('Alert 3')).toBeInTheDocument();
    expect(screen.queryByText('Alert 4')).not.toBeInTheDocument();
    expect(screen.getByText('+2 more alerts')).toBeInTheDocument();
  });

  it('shows all alerts in expanded mode', () => {
    const trajectory = createMockGrowthTrajectory({
      alerts: ['Alert 1', 'Alert 2', 'Alert 3', 'Alert 4', 'Alert 5'],
    });

    render(<GrowthTrajectory trajectory={trajectory} expanded />);

    expect(screen.getByText('Alert 1')).toBeInTheDocument();
    expect(screen.getByText('Alert 2')).toBeInTheDocument();
    expect(screen.getByText('Alert 3')).toBeInTheDocument();
    expect(screen.getByText('Alert 4')).toBeInTheDocument();
    expect(screen.getByText('Alert 5')).toBeInTheDocument();
    expect(screen.queryByText(/more alerts/)).not.toBeInTheDocument();
  });

  it('shows detailed timeline in expanded mode', () => {
    const trajectory = createMockGrowthTrajectory();
    render(<GrowthTrajectory trajectory={trajectory} expanded />);

    expect(screen.getByText('Detailed Timeline')).toBeInTheDocument();
  });

  it('respects selectedDomains filter', () => {
    const trajectory = createMockGrowthTrajectory();
    render(
      <GrowthTrajectory
        trajectory={trajectory}
        selectedDomains={['affective', 'social']}
      />
    );

    // Check domain legend
    expect(screen.getAllByText('Affective').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Social').length).toBeGreaterThan(0);
  });

  it('counts improving domains correctly', () => {
    const trajectory = createMockGrowthTrajectory({
      dataPoints: [
        createMockGrowthDataPoint({
          month: '2026-01',
          domainScores: {
            affective: 50,
            social: 50,
            language: 50,
            cognitive: 50,
            gross_motor: 50,
            fine_motor: 50,
          },
        }),
        createMockGrowthDataPoint({
          month: '2026-02',
          domainScores: {
            affective: 70, // +20 (improving)
            social: 65,    // +15 (improving)
            language: 55,  // +5 (stable)
            cognitive: 60, // +10 (improving)
            gross_motor: 50, // 0 (stable)
            fine_motor: 40, // -10 (declining)
          },
        }),
      ],
    });

    render(<GrowthTrajectory trajectory={trajectory} />);

    // Should show 3 improving domains
    expect(screen.getByText('Improving Domains')).toBeInTheDocument();
    expect(screen.getByText('3')).toBeInTheDocument();
  });
});

// ============================================================================
// Integration Tests - Component Interactions
// ============================================================================

describe('Development Profile Integration', () => {
  it('all components render without crashing', () => {
    const skills = [createMockSkillAssessment()];
    const snapshot = createMockMonthlySnapshot();
    const trajectory = createMockGrowthTrajectory();

    expect(() => render(<DomainCard domain="affective" skills={skills} />)).not.toThrow();
    expect(() =>
      render(<ObservationForm profileId="profile-123" onSubmit={vi.fn()} />)
    ).not.toThrow();
    expect(() => render(<MonthlySnapshot snapshot={snapshot} />)).not.toThrow();
    expect(() => render(<GrowthTrajectory trajectory={trajectory} />)).not.toThrow();
  });

  it('components support both English and French domain names', () => {
    const skills = [
      createMockSkillAssessment({
        skillName: 'Identifies emotions',
        skillNameFr: 'Identifie les émotions',
      }),
    ];

    render(<DomainCard domain="affective" skills={skills} expanded />);

    // Check bilingual support
    expect(screen.getByText('Identifies emotions')).toBeInTheDocument();
    expect(screen.getByText('Identifie les émotions')).toBeInTheDocument();
  });

  it('all components handle empty/null data gracefully', () => {
    const emptySnapshot = createMockMonthlySnapshot({
      domainSummaries: undefined,
      strengths: undefined,
      growthAreas: undefined,
      recommendations: undefined,
    });

    const emptyTrajectory = createMockGrowthTrajectory({
      dataPoints: [],
      alerts: [],
      trendAnalysis: undefined,
    });

    expect(() => render(<DomainCard domain="affective" skills={[]} />)).not.toThrow();
    expect(() => render(<MonthlySnapshot snapshot={emptySnapshot} />)).not.toThrow();
    expect(() => render(<GrowthTrajectory trajectory={emptyTrajectory} />)).not.toThrow();
  });
});
