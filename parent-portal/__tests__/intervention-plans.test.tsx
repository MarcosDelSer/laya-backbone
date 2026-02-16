/**
 * Component tests for Intervention Plan UI
 * Tests SMARTGoalProgress, GoalsSummary, ParentSignature, SignatureSuccess, and SignatureCanvas components
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { SMARTGoalProgress, GoalsSummary } from '@/components/SMARTGoalProgress'
import { ParentSignature, SignatureSuccess } from '@/components/ParentSignature'
import { SignatureCanvas } from '@/components/SignatureCanvas'
import type {
  InterventionGoal,
  InterventionProgress,
  SignInterventionPlanResponse,
} from '@/lib/types'

// Mock the intervention-plan-client module
vi.mock('@/lib/intervention-plan-client', () => ({
  signInterventionPlan: vi.fn(),
  getErrorMessage: vi.fn((err) => err?.message || 'An error occurred'),
}))

// ============================================================================
// Test Data
// ============================================================================

const mockGoalInProgress: InterventionGoal = {
  id: 'goal-1',
  planId: 'plan-1',
  needId: 'need-1',
  title: 'Improve Communication Skills',
  description: 'Child will express needs verbally using complete sentences',
  measurementCriteria: 'Frequency of verbal requests',
  measurementBaseline: '5 per day',
  measurementTarget: '15 per day',
  achievabilityNotes: 'Achievable with speech therapy support',
  relevanceNotes: 'Critical for classroom participation',
  targetDate: '2026-06-30',
  status: 'in_progress',
  progressPercentage: 45,
  order: 1,
  createdAt: '2026-01-15T10:00:00Z',
}

const mockGoalAchieved: InterventionGoal = {
  id: 'goal-2',
  planId: 'plan-1',
  needId: 'need-2',
  title: 'Follow Two-Step Instructions',
  description: 'Child will follow two-step verbal instructions independently',
  measurementCriteria: 'Percentage of successful completions',
  measurementBaseline: '40%',
  measurementTarget: '80%',
  status: 'achieved',
  progressPercentage: 100,
  order: 2,
  createdAt: '2026-01-15T10:00:00Z',
}

const mockGoalNotStarted: InterventionGoal = {
  id: 'goal-3',
  planId: 'plan-1',
  title: 'Participate in Group Activities',
  description: 'Child will participate in small group activities for 10 minutes',
  measurementCriteria: 'Duration of participation',
  status: 'not_started',
  progressPercentage: 0,
  order: 3,
  createdAt: '2026-01-15T10:00:00Z',
}

const mockProgressRecords: InterventionProgress[] = [
  {
    id: 'progress-1',
    planId: 'plan-1',
    goalId: 'goal-1',
    recordedBy: 'educator-1',
    recordDate: '2026-02-10T14:00:00Z',
    progressNotes: 'Child showed significant improvement in verbal requests',
    progressLevel: 'significant',
    measurementValue: '10 per day',
    createdAt: '2026-02-10T14:00:00Z',
  },
  {
    id: 'progress-2',
    planId: 'plan-1',
    goalId: 'goal-1',
    recordedBy: 'educator-1',
    recordDate: '2026-02-01T14:00:00Z',
    progressNotes: 'Moderate progress observed',
    progressLevel: 'moderate',
    measurementValue: '8 per day',
    createdAt: '2026-02-01T14:00:00Z',
  },
]

const mockUnsignedPlan = {
  id: 'plan-1',
  title: 'Communication Development Plan',
  childName: 'John Smith',
  status: 'active' as const,
  parentSigned: false,
}

const mockSignedPlan = {
  id: 'plan-2',
  title: 'Behavior Support Plan',
  childName: 'Jane Doe',
  status: 'active' as const,
  parentSigned: true,
}

const mockSignatureResponse: SignInterventionPlanResponse = {
  planId: 'plan-1',
  parentSigned: true,
  parentSignatureDate: '2026-02-16T10:30:00Z',
  message: 'Plan signed successfully',
}

// ============================================================================
// SMARTGoalProgress Component Tests
// ============================================================================

describe('SMARTGoalProgress Component', () => {
  it('renders goal title correctly', () => {
    render(<SMARTGoalProgress goal={mockGoalInProgress} />)
    expect(screen.getByText('Improve Communication Skills')).toBeInTheDocument()
  })

  it('renders progress bar with correct percentage', () => {
    render(<SMARTGoalProgress goal={mockGoalInProgress} />)
    expect(screen.getByText('45%')).toBeInTheDocument()
    expect(screen.getByText('Progress')).toBeInTheDocument()
  })

  it('renders in_progress status badge correctly', () => {
    render(<SMARTGoalProgress goal={mockGoalInProgress} />)
    expect(screen.getByText('In Progress')).toBeInTheDocument()
  })

  it('applies badge-info class for in_progress status', () => {
    const { container } = render(<SMARTGoalProgress goal={mockGoalInProgress} />)
    const badge = container.querySelector('.badge-info')
    expect(badge).toBeInTheDocument()
    expect(badge).toHaveTextContent('In Progress')
  })

  it('renders achieved status badge correctly', () => {
    render(<SMARTGoalProgress goal={mockGoalAchieved} />)
    expect(screen.getByText('Achieved')).toBeInTheDocument()
  })

  it('applies badge-success class for achieved status', () => {
    const { container } = render(<SMARTGoalProgress goal={mockGoalAchieved} />)
    const badge = container.querySelector('.badge-success')
    expect(badge).toBeInTheDocument()
    expect(badge).toHaveTextContent('Achieved')
  })

  it('renders not_started status badge correctly', () => {
    render(<SMARTGoalProgress goal={mockGoalNotStarted} />)
    expect(screen.getByText('Not Started')).toBeInTheDocument()
  })

  it('applies badge-neutral class for not_started status', () => {
    const { container } = render(<SMARTGoalProgress goal={mockGoalNotStarted} />)
    const badge = container.querySelector('.badge-neutral')
    expect(badge).toBeInTheDocument()
    expect(badge).toHaveTextContent('Not Started')
  })

  it('shows SMART criteria when showDetails is true', () => {
    render(<SMARTGoalProgress goal={mockGoalInProgress} showDetails={true} />)
    expect(screen.getByText('Specific')).toBeInTheDocument()
    expect(screen.getByText('Measurable')).toBeInTheDocument()
  })

  it('hides SMART criteria when showDetails is false', () => {
    render(<SMARTGoalProgress goal={mockGoalInProgress} showDetails={false} />)
    expect(screen.queryByText('Specific')).not.toBeInTheDocument()
    expect(screen.queryByText('Measurable')).not.toBeInTheDocument()
  })

  it('shows goal description in SMART criteria', () => {
    render(<SMARTGoalProgress goal={mockGoalInProgress} showDetails={true} />)
    expect(
      screen.getByText('Child will express needs verbally using complete sentences')
    ).toBeInTheDocument()
  })

  it('shows achievability notes when present', () => {
    render(<SMARTGoalProgress goal={mockGoalInProgress} showDetails={true} />)
    expect(screen.getByText('Achievable with speech therapy support')).toBeInTheDocument()
  })

  it('shows relevance notes when present', () => {
    render(<SMARTGoalProgress goal={mockGoalInProgress} showDetails={true} />)
    expect(screen.getByText('Critical for classroom participation')).toBeInTheDocument()
  })

  it('renders progress timeline with records', () => {
    render(
      <SMARTGoalProgress
        goal={mockGoalInProgress}
        progressRecords={mockProgressRecords}
        showDetails={true}
      />
    )
    expect(screen.getByText('Recent Progress')).toBeInTheDocument()
    expect(screen.getByText('Significant Progress')).toBeInTheDocument()
  })

  it('shows progress notes in timeline', () => {
    render(
      <SMARTGoalProgress
        goal={mockGoalInProgress}
        progressRecords={mockProgressRecords}
        showDetails={true}
      />
    )
    expect(
      screen.getByText('Child showed significant improvement in verbal requests')
    ).toBeInTheDocument()
  })

  it('shows measurement value in progress timeline', () => {
    render(
      <SMARTGoalProgress
        goal={mockGoalInProgress}
        progressRecords={mockProgressRecords}
        showDetails={true}
      />
    )
    expect(screen.getByText('Measured: 10 per day')).toBeInTheDocument()
  })

  it('renders compact view when compact is true', () => {
    const { container } = render(<SMARTGoalProgress goal={mockGoalInProgress} compact={true} />)
    // Compact view uses bg-gray-50 rounded-lg container
    const compactContainer = container.querySelector('.bg-gray-50.rounded-lg')
    expect(compactContainer).toBeInTheDocument()
  })

  it('shows goal title in compact view', () => {
    render(<SMARTGoalProgress goal={mockGoalInProgress} compact={true} />)
    expect(screen.getByText('Improve Communication Skills')).toBeInTheDocument()
  })

  it('shows progress percentage in compact view', () => {
    render(<SMARTGoalProgress goal={mockGoalInProgress} compact={true} />)
    expect(screen.getByText('45%')).toBeInTheDocument()
  })

  it('does not show SMART criteria in compact view', () => {
    render(<SMARTGoalProgress goal={mockGoalInProgress} compact={true} />)
    expect(screen.queryByText('Specific')).not.toBeInTheDocument()
    expect(screen.queryByText('Measurable')).not.toBeInTheDocument()
  })

  it('includes svg icon elements', () => {
    const { container } = render(<SMARTGoalProgress goal={mockGoalInProgress} />)
    const svgs = container.querySelectorAll('svg')
    expect(svgs.length).toBeGreaterThan(0)
  })

  it('shows target date when present', () => {
    render(<SMARTGoalProgress goal={mockGoalInProgress} showDetails={true} />)
    expect(screen.getByText('Time-bound')).toBeInTheDocument()
  })
})

// ============================================================================
// GoalsSummary Component Tests
// ============================================================================

describe('GoalsSummary Component', () => {
  it('shows empty state when no goals', () => {
    render(<GoalsSummary goals={[]} />)
    expect(screen.getByText('No goals defined yet')).toBeInTheDocument()
  })

  it('displays total goals count', () => {
    const goals = [mockGoalInProgress, mockGoalAchieved, mockGoalNotStarted]
    render(<GoalsSummary goals={goals} />)
    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.getByText('Total Goals')).toBeInTheDocument()
  })

  it('displays achieved goals count', () => {
    const goals = [mockGoalInProgress, mockGoalAchieved, mockGoalNotStarted]
    render(<GoalsSummary goals={goals} />)
    expect(screen.getByText('1')).toBeInTheDocument()
    expect(screen.getByText('Achieved')).toBeInTheDocument()
  })

  it('displays in progress goals count', () => {
    const goals = [mockGoalInProgress, mockGoalAchieved, mockGoalNotStarted]
    render(<GoalsSummary goals={goals} />)
    expect(screen.getByText('In Progress')).toBeInTheDocument()
  })

  it('displays average progress percentage', () => {
    const goals = [mockGoalInProgress, mockGoalAchieved] // 45% + 100% = 145% / 2 = 73% (rounded)
    render(<GoalsSummary goals={goals} />)
    expect(screen.getByText('Avg. Progress')).toBeInTheDocument()
  })

  it('shows svg icon in empty state', () => {
    const { container } = render(<GoalsSummary goals={[]} />)
    const svg = container.querySelector('svg')
    expect(svg).toBeInTheDocument()
  })

  it('applies correct color classes for statistics', () => {
    const goals = [mockGoalInProgress, mockGoalAchieved]
    const { container } = render(<GoalsSummary goals={goals} />)

    // Check for success color on achieved count
    const successText = container.querySelector('.text-success-600')
    expect(successText).toBeInTheDocument()

    // Check for info color on in progress count
    const infoText = container.querySelector('.text-info-600')
    expect(infoText).toBeInTheDocument()
  })

  it('renders in a grid layout', () => {
    const goals = [mockGoalInProgress]
    const { container } = render(<GoalsSummary goals={goals} />)
    const grid = container.querySelector('.grid')
    expect(grid).toBeInTheDocument()
    expect(grid).toHaveClass('grid-cols-2', 'sm:grid-cols-4')
  })
})

// ============================================================================
// ParentSignature Component Tests
// ============================================================================

describe('ParentSignature Component', () => {
  const defaultProps = {
    plan: mockUnsignedPlan,
    isOpen: true,
    onClose: vi.fn(),
    onSuccess: vi.fn(),
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns null when modal is not open', () => {
    const { container } = render(
      <ParentSignature {...defaultProps} isOpen={false} />
    )
    expect(container.firstChild).toBeNull()
  })

  it('returns null when plan is null', () => {
    const { container } = render(
      <ParentSignature {...defaultProps} plan={null} />
    )
    expect(container.firstChild).toBeNull()
  })

  it('shows already signed state when plan is signed', () => {
    render(<ParentSignature {...defaultProps} plan={mockSignedPlan} />)
    expect(screen.getByText('Already Signed')).toBeInTheDocument()
    expect(
      screen.getByText('This intervention plan has already been signed.')
    ).toBeInTheDocument()
  })

  it('shows close button in already signed state', () => {
    render(<ParentSignature {...defaultProps} plan={mockSignedPlan} />)
    expect(screen.getByRole('button', { name: 'Close' })).toBeInTheDocument()
  })

  it('calls onClose when close button clicked in already signed state', () => {
    const onClose = vi.fn()
    render(<ParentSignature {...defaultProps} plan={mockSignedPlan} onClose={onClose} />)
    fireEvent.click(screen.getByRole('button', { name: 'Close' }))
    expect(onClose).toHaveBeenCalled()
  })

  it('shows modal header with title', () => {
    render(<ParentSignature {...defaultProps} />)
    expect(screen.getByText('Sign Intervention Plan')).toBeInTheDocument()
  })

  it('shows plan title in modal', () => {
    render(<ParentSignature {...defaultProps} />)
    expect(screen.getAllByText('Communication Development Plan').length).toBeGreaterThan(0)
  })

  it('shows plan child name', () => {
    render(<ParentSignature {...defaultProps} />)
    expect(screen.getByText(/For: John Smith/)).toBeInTheDocument()
  })

  it('shows plan status', () => {
    render(<ParentSignature {...defaultProps} />)
    expect(screen.getByText(/Status:/)).toBeInTheDocument()
  })

  it('shows information notice about reviewing the plan', () => {
    render(<ParentSignature {...defaultProps} />)
    expect(
      screen.getByText(/Please review the intervention plan carefully/)
    ).toBeInTheDocument()
  })

  it('shows Your Signature label', () => {
    render(<ParentSignature {...defaultProps} />)
    expect(screen.getByText('Your Signature')).toBeInTheDocument()
  })

  it('renders agreement checkbox', () => {
    render(<ParentSignature {...defaultProps} />)
    const checkbox = screen.getByRole('checkbox')
    expect(checkbox).toBeInTheDocument()
    expect(checkbox).not.toBeChecked()
  })

  it('shows agreement text', () => {
    render(<ParentSignature {...defaultProps} />)
    expect(
      screen.getByText(/I have reviewed this intervention plan/)
    ).toBeInTheDocument()
  })

  it('shows timestamp notice', () => {
    render(<ParentSignature {...defaultProps} />)
    expect(
      screen.getByText(/Your signature will be timestamped/)
    ).toBeInTheDocument()
  })

  it('shows cancel and sign buttons', () => {
    render(<ParentSignature {...defaultProps} />)
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Sign Plan' })).toBeInTheDocument()
  })

  it('disables sign button when terms not agreed', () => {
    render(<ParentSignature {...defaultProps} />)
    const signButton = screen.getByRole('button', { name: 'Sign Plan' })
    expect(signButton).toBeDisabled()
  })

  it('calls onClose when cancel button clicked', () => {
    const onClose = vi.fn()
    render(<ParentSignature {...defaultProps} onClose={onClose} />)
    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    expect(onClose).toHaveBeenCalled()
  })

  it('toggles checkbox when clicked', () => {
    render(<ParentSignature {...defaultProps} />)
    const checkbox = screen.getByRole('checkbox')
    expect(checkbox).not.toBeChecked()
    fireEvent.click(checkbox)
    expect(checkbox).toBeChecked()
  })

  it('renders signature canvas component', () => {
    const { container } = render(<ParentSignature {...defaultProps} />)
    const signatureContainer = container.querySelector('.signature-canvas-container')
    expect(signatureContainer).toBeInTheDocument()
  })

  it('shows close button in header', () => {
    const { container } = render(<ParentSignature {...defaultProps} />)
    // Close button has an X icon SVG
    const closeButton = container.querySelector('button[type="button"]')
    expect(closeButton).toBeInTheDocument()
  })
})

// ============================================================================
// SignatureSuccess Component Tests
// ============================================================================

describe('SignatureSuccess Component', () => {
  const defaultProps = {
    plan: mockUnsignedPlan,
    response: mockSignatureResponse,
    onClose: vi.fn(),
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows success message', () => {
    render(<SignatureSuccess {...defaultProps} />)
    expect(screen.getByText('Plan Signed Successfully')).toBeInTheDocument()
  })

  it('shows plan title', () => {
    render(<SignatureSuccess {...defaultProps} />)
    expect(screen.getByText('Communication Development Plan')).toBeInTheDocument()
  })

  it('shows thank you message with child name', () => {
    render(<SignatureSuccess {...defaultProps} />)
    expect(
      screen.getByText(/Thank you for signing the intervention plan for John Smith/)
    ).toBeInTheDocument()
  })

  it('shows signature date', () => {
    render(<SignatureSuccess {...defaultProps} />)
    // The date is formatted with toLocaleDateString
    expect(screen.getByText(/Signed on/)).toBeInTheDocument()
  })

  it('shows Done button', () => {
    render(<SignatureSuccess {...defaultProps} />)
    expect(screen.getByRole('button', { name: 'Done' })).toBeInTheDocument()
  })

  it('calls onClose when Done button clicked', () => {
    const onClose = vi.fn()
    render(<SignatureSuccess {...defaultProps} onClose={onClose} />)
    fireEvent.click(screen.getByRole('button', { name: 'Done' }))
    expect(onClose).toHaveBeenCalled()
  })

  it('shows checkmark icon', () => {
    const { container } = render(<SignatureSuccess {...defaultProps} />)
    const svg = container.querySelector('svg')
    expect(svg).toBeInTheDocument()
  })

  it('has success icon with green styling', () => {
    const { container } = render(<SignatureSuccess {...defaultProps} />)
    const iconContainer = container.querySelector('.bg-green-100')
    expect(iconContainer).toBeInTheDocument()
  })

  it('calls onClose when backdrop clicked', () => {
    const onClose = vi.fn()
    const { container } = render(<SignatureSuccess {...defaultProps} onClose={onClose} />)
    const backdrop = container.querySelector('.bg-black.bg-opacity-50')
    expect(backdrop).toBeInTheDocument()
    if (backdrop) {
      fireEvent.click(backdrop)
      expect(onClose).toHaveBeenCalled()
    }
  })
})

// ============================================================================
// SignatureCanvas Component Tests
// ============================================================================

describe('SignatureCanvas Component', () => {
  const defaultProps = {
    onSignatureChange: vi.fn(),
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders canvas element', () => {
    const { container } = render(<SignatureCanvas {...defaultProps} />)
    const canvas = container.querySelector('canvas')
    expect(canvas).toBeInTheDocument()
  })

  it('shows placeholder text when empty', () => {
    render(<SignatureCanvas {...defaultProps} />)
    expect(screen.getByText('Sign here')).toBeInTheDocument()
  })

  it('shows "Draw your signature above" instruction', () => {
    render(<SignatureCanvas {...defaultProps} />)
    expect(screen.getByText('Draw your signature above')).toBeInTheDocument()
  })

  it('renders clear button', () => {
    render(<SignatureCanvas {...defaultProps} />)
    expect(screen.getByRole('button', { name: 'Clear' })).toBeInTheDocument()
  })

  it('clear button is disabled when no signature', () => {
    render(<SignatureCanvas {...defaultProps} />)
    const clearButton = screen.getByRole('button', { name: 'Clear' })
    expect(clearButton).toBeDisabled()
  })

  it('renders with default dimensions', () => {
    const { container } = render(<SignatureCanvas {...defaultProps} />)
    const canvas = container.querySelector('canvas')
    expect(canvas).toHaveStyle({ width: '400px', height: '200px' })
  })

  it('renders with custom dimensions', () => {
    const { container } = render(
      <SignatureCanvas {...defaultProps} width={300} height={100} />
    )
    const canvas = container.querySelector('canvas')
    expect(canvas).toHaveStyle({ width: '300px', height: '100px' })
  })

  it('renders container with signature-canvas-container class', () => {
    const { container } = render(<SignatureCanvas {...defaultProps} />)
    const signatureContainer = container.querySelector('.signature-canvas-container')
    expect(signatureContainer).toBeInTheDocument()
  })

  it('shows X mark indicator', () => {
    render(<SignatureCanvas {...defaultProps} />)
    expect(screen.getByText('×')).toBeInTheDocument()
  })

  it('canvas has touch-none and cursor-crosshair classes', () => {
    const { container } = render(<SignatureCanvas {...defaultProps} />)
    const canvas = container.querySelector('canvas')
    expect(canvas).toHaveClass('touch-none', 'cursor-crosshair')
  })

  it('has signature line indicator', () => {
    const { container } = render(<SignatureCanvas {...defaultProps} />)
    const signatureLine = container.querySelector('.border-b.border-gray-300')
    expect(signatureLine).toBeInTheDocument()
  })
})

// ============================================================================
// Integration Tests
// ============================================================================

describe('Intervention Plan UI Integration', () => {
  it('renders multiple goals in GoalsSummary correctly', () => {
    const goals = [mockGoalInProgress, mockGoalAchieved, mockGoalNotStarted]
    render(<GoalsSummary goals={goals} />)

    // Total: 3
    expect(screen.getByText('3')).toBeInTheDocument()
    // Achieved: 1
    expect(screen.getByText('1')).toBeInTheDocument()
    // In Progress: 1
    expect(screen.getByText('In Progress')).toBeInTheDocument()
  })

  it('renders goal with 100% progress correctly', () => {
    render(<SMARTGoalProgress goal={mockGoalAchieved} />)
    expect(screen.getByText('100%')).toBeInTheDocument()
    expect(screen.getByText('Achieved')).toBeInTheDocument()
  })

  it('renders goal with 0% progress correctly', () => {
    render(<SMARTGoalProgress goal={mockGoalNotStarted} />)
    expect(screen.getByText('0%')).toBeInTheDocument()
    expect(screen.getByText('Not Started')).toBeInTheDocument()
  })

  it('filters progress records by goal ID', () => {
    const otherGoalProgress: InterventionProgress = {
      ...mockProgressRecords[0],
      id: 'progress-other',
      goalId: 'other-goal-id',
      progressNotes: 'This should not appear',
    }

    render(
      <SMARTGoalProgress
        goal={mockGoalInProgress}
        progressRecords={[...mockProgressRecords, otherGoalProgress]}
        showDetails={true}
      />
    )

    // Should show progress for goal-1
    expect(
      screen.getByText('Child showed significant improvement in verbal requests')
    ).toBeInTheDocument()

    // Should not show progress for other goal
    expect(screen.queryByText('This should not appear')).not.toBeInTheDocument()
  })
})
