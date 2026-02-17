/**
 * Unit tests for QualityCoachPanel component
 * Tests rendering, interactions, and state management for the message quality coach
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { QualityCoachPanel } from '@/components/QualityCoachPanel'
import * as AuthContext from '@/contexts/AuthContext'
import type {
  MessageAnalysisResponse,
  QualityIssueDetail,
  RewriteSuggestion,
} from '@/lib/types'
import type { User } from '@/lib/auth'

// Mock the AuthContext
vi.mock('@/contexts/AuthContext', () => ({
  useAuth: vi.fn(),
}))

// Helper to create mock user
function createMockUser(role: string): User {
  return {
    id: `user-${role}`,
    email: `${role}@example.com`,
    role,
    firstName: 'Test',
    lastName: 'User',
  }
}

// Helper to create mock analysis data
function createMockAnalysis(
  overrides: Partial<MessageAnalysisResponse> = {}
): MessageAnalysisResponse {
  return {
    id: 'test-analysis-id',
    messageText: 'Test message',
    language: 'en',
    qualityScore: 75,
    isAcceptable: true,
    issues: [],
    rewriteSuggestions: [],
    hasPositiveOpening: true,
    hasFactualBasis: true,
    hasSolutionFocus: true,
    ...overrides,
  }
}

// Helper to create mock issue
function createMockIssue(
  overrides: Partial<QualityIssueDetail> = {}
): QualityIssueDetail {
  return {
    issueType: 'accusatory_you',
    severity: 'medium',
    description: 'Test issue description',
    originalText: 'you always do this',
    positionStart: 0,
    positionEnd: 20,
    suggestion: 'Consider using I-language',
    ...overrides,
  }
}

// Helper to create mock rewrite suggestion
function createMockRewrite(
  overrides: Partial<RewriteSuggestion> = {}
): RewriteSuggestion {
  return {
    originalText: 'You never listen',
    suggestedText: 'I feel unheard when...',
    explanation: 'Using I-language makes the message less confrontational',
    usesILanguage: true,
    hasSandwichStructure: false,
    confidenceScore: 0.85,
    ...overrides,
  }
}

describe('QualityCoachPanel', () => {
  const mockUseAuth = vi.mocked(AuthContext.useAuth)

  beforeEach(() => {
    // Default: Mock as teacher user (authorized)
    mockUseAuth.mockReturnValue({
      user: createMockUser('teacher'),
      isAuthenticated: true,
      isLoading: false,
      updateUser: vi.fn(),
      refreshAuth: vi.fn(),
    })
  })

  describe('Role-Based Access Control', () => {
    it('renders for teacher role', () => {
      mockUseAuth.mockReturnValue({
        user: createMockUser('teacher'),
        isAuthenticated: true,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      render(<QualityCoachPanel analysis={createMockAnalysis()} />)
      expect(screen.getByText('Quality Coach')).toBeInTheDocument()
    })

    it('renders for admin role', () => {
      mockUseAuth.mockReturnValue({
        user: createMockUser('admin'),
        isAuthenticated: true,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      render(<QualityCoachPanel analysis={createMockAnalysis()} />)
      expect(screen.getByText('Quality Coach')).toBeInTheDocument()
    })

    it('does not render for parent role', () => {
      mockUseAuth.mockReturnValue({
        user: createMockUser('parent'),
        isAuthenticated: true,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      const { container } = render(<QualityCoachPanel analysis={createMockAnalysis()} />)
      expect(container.firstChild).toBeNull()
    })

    it('does not render for staff role', () => {
      mockUseAuth.mockReturnValue({
        user: createMockUser('staff'),
        isAuthenticated: true,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      const { container } = render(<QualityCoachPanel analysis={createMockAnalysis()} />)
      expect(container.firstChild).toBeNull()
    })

    it('does not render for accountant role', () => {
      mockUseAuth.mockReturnValue({
        user: createMockUser('accountant'),
        isAuthenticated: true,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      const { container } = render(<QualityCoachPanel analysis={createMockAnalysis()} />)
      expect(container.firstChild).toBeNull()
    })

    it('does not render when user is null', () => {
      mockUseAuth.mockReturnValue({
        user: null,
        isAuthenticated: false,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      const { container } = render(<QualityCoachPanel analysis={createMockAnalysis()} />)
      expect(container.firstChild).toBeNull()
    })

    it('does not render when not authenticated', () => {
      mockUseAuth.mockReturnValue({
        user: null,
        isAuthenticated: false,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      const { container } = render(<QualityCoachPanel analysis={createMockAnalysis()} />)
      expect(container.firstChild).toBeNull()
    })
  })

  describe('Loading State', () => {
    it('renders loading state when isLoading is true', () => {
      render(<QualityCoachPanel analysis={null} isLoading={true} />)
      expect(screen.getByText('Analyzing message...')).toBeInTheDocument()
    })

    it('shows loading spinner animation', () => {
      const { container } = render(
        <QualityCoachPanel analysis={null} isLoading={true} />
      )
      const spinner = container.querySelector('.animate-spin')
      expect(spinner).toBeInTheDocument()
    })
  })

  describe('Empty State', () => {
    it('renders empty state when no analysis is provided', () => {
      render(<QualityCoachPanel analysis={null} />)
      expect(
        screen.getByText('Start typing to see quality analysis')
      ).toBeInTheDocument()
    })

    it('shows document icon in empty state', () => {
      const { container } = render(<QualityCoachPanel analysis={null} />)
      const svgIcon = container.querySelector('svg.text-gray-300')
      expect(svgIcon).toBeInTheDocument()
    })
  })

  describe('Panel Header', () => {
    it('renders Quality Coach title', () => {
      render(<QualityCoachPanel analysis={null} />)
      expect(screen.getByText('Quality Coach')).toBeInTheDocument()
    })

    it('shows "Ready to send" badge when message is acceptable', () => {
      const analysis = createMockAnalysis({ isAcceptable: true })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Ready to send')).toBeInTheDocument()
    })

    it('shows "Review suggested" badge when message needs review', () => {
      const analysis = createMockAnalysis({ isAcceptable: false })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Review suggested')).toBeInTheDocument()
    })
  })

  describe('Collapse/Expand Functionality', () => {
    it('renders collapse button when onToggleCollapse is provided', () => {
      const mockToggle = vi.fn()
      const { container } = render(
        <QualityCoachPanel
          analysis={createMockAnalysis()}
          onToggleCollapse={mockToggle}
        />
      )
      const collapseButton = container.querySelector('button[title="Collapse panel"]')
      expect(collapseButton).toBeInTheDocument()
    })

    it('calls onToggleCollapse when collapse button is clicked', () => {
      const mockToggle = vi.fn()
      const { container } = render(
        <QualityCoachPanel
          analysis={createMockAnalysis()}
          onToggleCollapse={mockToggle}
        />
      )
      const collapseButton = container.querySelector('button[title="Collapse panel"]')
      fireEvent.click(collapseButton!)
      expect(mockToggle).toHaveBeenCalledTimes(1)
    })

    it('hides content when collapsed is true', () => {
      render(
        <QualityCoachPanel
          analysis={createMockAnalysis()}
          collapsed={true}
          onToggleCollapse={vi.fn()}
        />
      )
      expect(screen.queryByText('Quality Score')).not.toBeInTheDocument()
    })

    it('shows expand button title when collapsed', () => {
      const { container } = render(
        <QualityCoachPanel
          analysis={createMockAnalysis()}
          collapsed={true}
          onToggleCollapse={vi.fn()}
        />
      )
      const expandButton = container.querySelector('button[title="Expand panel"]')
      expect(expandButton).toBeInTheDocument()
    })
  })

  describe('Quality Score Display', () => {
    it('displays the quality score value', () => {
      const analysis = createMockAnalysis({ qualityScore: 85 })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('85')).toBeInTheDocument()
    })

    it('displays "Excellent" label for score >= 80', () => {
      const analysis = createMockAnalysis({ qualityScore: 80 })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Excellent')).toBeInTheDocument()
    })

    it('displays "Good" label for score >= 60 and < 80', () => {
      const analysis = createMockAnalysis({ qualityScore: 65 })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Good')).toBeInTheDocument()
    })

    it('displays "Needs Improvement" label for score >= 40 and < 60', () => {
      const analysis = createMockAnalysis({ qualityScore: 45 })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Needs Improvement')).toBeInTheDocument()
    })

    it('displays "Requires Revision" label for score < 40', () => {
      const analysis = createMockAnalysis({ qualityScore: 30 })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Requires Revision')).toBeInTheDocument()
    })

    it('applies green color for excellent score', () => {
      const analysis = createMockAnalysis({ qualityScore: 85 })
      const { container } = render(<QualityCoachPanel analysis={analysis} />)
      const scoreText = screen.getByText('85')
      expect(scoreText).toHaveClass('text-green-600')
    })

    it('applies red color for low score', () => {
      const analysis = createMockAnalysis({ qualityScore: 25 })
      const { container } = render(<QualityCoachPanel analysis={analysis} />)
      const scoreText = screen.getByText('25')
      expect(scoreText).toHaveClass('text-red-600')
    })
  })

  describe('Quality Check Indicators', () => {
    it('shows Positive Opening check indicator', () => {
      render(<QualityCoachPanel analysis={createMockAnalysis()} />)
      expect(screen.getByText('Positive Opening')).toBeInTheDocument()
    })

    it('shows Factual Basis check indicator', () => {
      render(<QualityCoachPanel analysis={createMockAnalysis()} />)
      expect(screen.getByText('Factual Basis')).toBeInTheDocument()
    })

    it('shows Solution Focus check indicator', () => {
      render(<QualityCoachPanel analysis={createMockAnalysis()} />)
      expect(screen.getByText('Solution Focus')).toBeInTheDocument()
    })

    it('applies green color for passed checks', () => {
      const analysis = createMockAnalysis({ hasPositiveOpening: true })
      render(<QualityCoachPanel analysis={analysis} />)
      const positiveOpeningText = screen.getByText('Positive Opening')
      expect(positiveOpeningText).toHaveClass('text-green-700')
    })

    it('applies gray color for failed checks', () => {
      const analysis = createMockAnalysis({ hasFactualBasis: false })
      render(<QualityCoachPanel analysis={analysis} />)
      const factualBasisText = screen.getByText('Factual Basis')
      expect(factualBasisText).toHaveClass('text-gray-500')
    })
  })

  describe('Issues Display', () => {
    it('renders "Issues Detected" section when issues exist', () => {
      const analysis = createMockAnalysis({
        issues: [createMockIssue()],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Issues Detected')).toBeInTheDocument()
    })

    it('shows issue count in header', () => {
      const analysis = createMockAnalysis({
        issues: [createMockIssue(), createMockIssue({ issueType: 'blame_shame' })],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('2 issues')).toBeInTheDocument()
    })

    it('shows singular "issue" for single issue', () => {
      const analysis = createMockAnalysis({
        issues: [createMockIssue()],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('1 issue')).toBeInTheDocument()
    })

    it('renders issue description', () => {
      const analysis = createMockAnalysis({
        issues: [createMockIssue({ description: 'This is accusatory language' })],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('This is accusatory language')).toBeInTheDocument()
    })

    it('renders issue severity label', () => {
      const analysis = createMockAnalysis({
        issues: [createMockIssue({ severity: 'high' })],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('High')).toBeInTheDocument()
    })

    it('renders critical severity with red styling', () => {
      const analysis = createMockAnalysis({
        issues: [createMockIssue({ severity: 'critical' })],
      })
      const { container } = render(<QualityCoachPanel analysis={analysis} />)
      const criticalBadge = container.querySelector('.bg-red-50')
      expect(criticalBadge).toBeInTheDocument()
    })

    it('renders issue type label', () => {
      const analysis = createMockAnalysis({
        issues: [createMockIssue({ issueType: 'accusatory_you' })],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Accusatory Language')).toBeInTheDocument()
    })

    it('renders original text in issue', () => {
      const analysis = createMockAnalysis({
        issues: [createMockIssue({ originalText: 'you always do this' })],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText(/you always do this/)).toBeInTheDocument()
    })

    it('renders suggestion when provided', () => {
      const analysis = createMockAnalysis({
        issues: [createMockIssue({ suggestion: 'Try using I-statements' })],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Try using I-statements')).toBeInTheDocument()
    })

    it('sorts issues by severity (critical first)', () => {
      const analysis = createMockAnalysis({
        issues: [
          createMockIssue({ severity: 'low', description: 'Low issue' }),
          createMockIssue({ severity: 'critical', description: 'Critical issue' }),
          createMockIssue({ severity: 'medium', description: 'Medium issue' }),
        ],
      })
      const { container } = render(<QualityCoachPanel analysis={analysis} />)
      const issueCards = container.querySelectorAll('.rounded-lg.border.p-3')
      // First issue should be critical (red background)
      expect(issueCards[0]).toHaveClass('bg-red-50')
    })
  })

  describe('Issue Dismissal', () => {
    it('renders dismiss button when onDismissIssue is provided', () => {
      const analysis = createMockAnalysis({
        issues: [createMockIssue()],
      })
      const mockDismiss = vi.fn()
      const { container } = render(
        <QualityCoachPanel analysis={analysis} onDismissIssue={mockDismiss} />
      )
      const dismissButton = container.querySelector('button[title="Dismiss issue"]')
      expect(dismissButton).toBeInTheDocument()
    })

    it('does not render dismiss button when onDismissIssue is not provided', () => {
      const analysis = createMockAnalysis({
        issues: [createMockIssue()],
      })
      const { container } = render(<QualityCoachPanel analysis={analysis} />)
      const dismissButton = container.querySelector('button[title="Dismiss issue"]')
      expect(dismissButton).not.toBeInTheDocument()
    })

    it('calls onDismissIssue with issue when dismiss button is clicked', () => {
      const issue = createMockIssue()
      const analysis = createMockAnalysis({ issues: [issue] })
      const mockDismiss = vi.fn()
      const { container } = render(
        <QualityCoachPanel analysis={analysis} onDismissIssue={mockDismiss} />
      )
      const dismissButton = container.querySelector('button[title="Dismiss issue"]')
      fireEvent.click(dismissButton!)
      expect(mockDismiss).toHaveBeenCalledWith(issue)
    })
  })

  describe('Rewrite Suggestions Display', () => {
    it('renders "Suggested Rewrites" section when rewrites exist', () => {
      const analysis = createMockAnalysis({
        rewriteSuggestions: [createMockRewrite()],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Suggested Rewrites')).toBeInTheDocument()
    })

    it('shows rewrite count in header', () => {
      const analysis = createMockAnalysis({
        rewriteSuggestions: [createMockRewrite(), createMockRewrite()],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('2 issues')).toBeInTheDocument()
    })

    it('renders original text with strikethrough', () => {
      const analysis = createMockAnalysis({
        rewriteSuggestions: [
          createMockRewrite({ originalText: 'You never listen to me' }),
        ],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('You never listen to me')).toBeInTheDocument()
    })

    it('renders suggested text', () => {
      const analysis = createMockAnalysis({
        rewriteSuggestions: [
          createMockRewrite({ suggestedText: 'I feel unheard when...' }),
        ],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('I feel unheard when...')).toBeInTheDocument()
    })

    it('renders explanation', () => {
      const analysis = createMockAnalysis({
        rewriteSuggestions: [
          createMockRewrite({ explanation: 'This uses I-language pattern' }),
        ],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('This uses I-language pattern')).toBeInTheDocument()
    })

    it('shows I-language badge when usesILanguage is true', () => {
      const analysis = createMockAnalysis({
        rewriteSuggestions: [createMockRewrite({ usesILanguage: true })],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('I-language')).toBeInTheDocument()
    })

    it('shows Sandwich badge when hasSandwichStructure is true', () => {
      const analysis = createMockAnalysis({
        rewriteSuggestions: [createMockRewrite({ hasSandwichStructure: true })],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Sandwich')).toBeInTheDocument()
    })

    it('renders Apply Suggestion button when onApplyRewrite is provided', () => {
      const analysis = createMockAnalysis({
        rewriteSuggestions: [createMockRewrite()],
      })
      const mockApply = vi.fn()
      render(<QualityCoachPanel analysis={analysis} onApplyRewrite={mockApply} />)
      expect(screen.getByText('Apply Suggestion')).toBeInTheDocument()
    })

    it('does not render Apply Suggestion button when onApplyRewrite is not provided', () => {
      const analysis = createMockAnalysis({
        rewriteSuggestions: [createMockRewrite()],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.queryByText('Apply Suggestion')).not.toBeInTheDocument()
    })

    it('calls onApplyRewrite with suggestion when Apply button is clicked', () => {
      const rewrite = createMockRewrite()
      const analysis = createMockAnalysis({ rewriteSuggestions: [rewrite] })
      const mockApply = vi.fn()
      render(<QualityCoachPanel analysis={analysis} onApplyRewrite={mockApply} />)
      const applyButton = screen.getByText('Apply Suggestion')
      fireEvent.click(applyButton)
      expect(mockApply).toHaveBeenCalledWith(rewrite)
    })
  })

  describe('Analysis Notes', () => {
    it('renders analysis notes when provided', () => {
      const analysis = createMockAnalysis({
        analysisNotes: 'Consider adding more positive context',
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(
        screen.getByText('Consider adding more positive context')
      ).toBeInTheDocument()
    })

    it('shows "Analysis Notes:" label', () => {
      const analysis = createMockAnalysis({
        analysisNotes: 'Some notes here',
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Analysis Notes:')).toBeInTheDocument()
    })

    it('does not render notes section when analysisNotes is not provided', () => {
      const analysis = createMockAnalysis({ analysisNotes: undefined })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.queryByText('Analysis Notes:')).not.toBeInTheDocument()
    })
  })

  describe('Great Message State', () => {
    it('shows success message when no issues and message is acceptable', () => {
      const analysis = createMockAnalysis({
        issues: [],
        isAcceptable: true,
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('Great message!')).toBeInTheDocument()
    })

    it('shows Bonne Message standards text', () => {
      const analysis = createMockAnalysis({
        issues: [],
        isAcceptable: true,
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(
        screen.getByText(/Quebec.*Bonne Message.*standards/i)
      ).toBeInTheDocument()
    })

    it('does not show success message when there are issues', () => {
      const analysis = createMockAnalysis({
        issues: [createMockIssue()],
        isAcceptable: true,
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.queryByText('Great message!')).not.toBeInTheDocument()
    })
  })

  describe('Severity Badge Display', () => {
    it('shows severity badges for each severity type present', () => {
      const analysis = createMockAnalysis({
        issues: [
          createMockIssue({ severity: 'critical' }),
          createMockIssue({ severity: 'high' }),
          createMockIssue({ severity: 'medium' }),
        ],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('1 Critical')).toBeInTheDocument()
      expect(screen.getByText('1 High')).toBeInTheDocument()
      expect(screen.getByText('1 Medium')).toBeInTheDocument()
    })

    it('shows correct count for multiple issues of same severity', () => {
      const analysis = createMockAnalysis({
        issues: [
          createMockIssue({ severity: 'high' }),
          createMockIssue({ severity: 'high', issueType: 'blame_shame' }),
          createMockIssue({ severity: 'high', issueType: 'exaggeration' }),
        ],
      })
      render(<QualityCoachPanel analysis={analysis} />)
      expect(screen.getByText('3 High')).toBeInTheDocument()
    })
  })

  describe('Accessibility', () => {
    it('has accessible title for collapse button', () => {
      const { container } = render(
        <QualityCoachPanel
          analysis={createMockAnalysis()}
          onToggleCollapse={vi.fn()}
        />
      )
      const button = container.querySelector('button[title="Collapse panel"]')
      expect(button).toBeInTheDocument()
    })

    it('has accessible title for dismiss button', () => {
      const analysis = createMockAnalysis({ issues: [createMockIssue()] })
      const { container } = render(
        <QualityCoachPanel analysis={analysis} onDismissIssue={vi.fn()} />
      )
      const button = container.querySelector('button[title="Dismiss issue"]')
      expect(button).toBeInTheDocument()
    })

    it('uses semantic heading for panel title', () => {
      render(<QualityCoachPanel analysis={createMockAnalysis()} />)
      const heading = screen.getByRole('heading', { name: /quality coach/i })
      expect(heading).toBeInTheDocument()
    })
  })

  describe('Bilingual Support', () => {
    describe('English (default)', () => {
      it('displays English panel title', () => {
        render(<QualityCoachPanel analysis={createMockAnalysis()} language="en" />)
        const heading = screen.getByRole('heading', { name: 'Quality Coach' })
        expect(heading).toBeInTheDocument()
      })

      it('displays English ready to send badge', () => {
        render(<QualityCoachPanel analysis={createMockAnalysis({ isAcceptable: true })} language="en" />)
        expect(screen.getByText('Ready to send')).toBeInTheDocument()
      })

      it('displays English review suggested badge', () => {
        render(<QualityCoachPanel analysis={createMockAnalysis({ isAcceptable: false })} language="en" />)
        expect(screen.getByText('Review suggested')).toBeInTheDocument()
      })

      it('displays English severity labels', () => {
        const analysis = createMockAnalysis({
          issues: [createMockIssue({ severity: 'critical' })],
        })
        render(<QualityCoachPanel analysis={analysis} language="en" />)
        expect(screen.getByText('Critical')).toBeInTheDocument()
      })

      it('displays English quality checks', () => {
        render(<QualityCoachPanel analysis={createMockAnalysis()} language="en" />)
        expect(screen.getByText('Positive Opening')).toBeInTheDocument()
        expect(screen.getByText('Factual Basis')).toBeInTheDocument()
        expect(screen.getByText('Solution Focus')).toBeInTheDocument()
      })

      it('displays English great message text', () => {
        render(<QualityCoachPanel analysis={createMockAnalysis({ isAcceptable: true, issues: [] })} language="en" />)
        expect(screen.getByText('Great message!')).toBeInTheDocument()
      })
    })

    describe('French', () => {
      it('displays French panel title', () => {
        render(<QualityCoachPanel analysis={createMockAnalysis()} language="fr" />)
        const heading = screen.getByRole('heading', { name: 'Coach Qualité' })
        expect(heading).toBeInTheDocument()
      })

      it('displays French ready to send badge', () => {
        render(<QualityCoachPanel analysis={createMockAnalysis({ isAcceptable: true })} language="fr" />)
        expect(screen.getByText('Prêt à envoyer')).toBeInTheDocument()
      })

      it('displays French review suggested badge', () => {
        render(<QualityCoachPanel analysis={createMockAnalysis({ isAcceptable: false })} language="fr" />)
        expect(screen.getByText('Révision suggérée')).toBeInTheDocument()
      })

      it('displays French severity labels', () => {
        const analysis = createMockAnalysis({
          issues: [createMockIssue({ severity: 'critical' })],
        })
        render(<QualityCoachPanel analysis={analysis} language="fr" />)
        expect(screen.getByText('Critique')).toBeInTheDocument()
      })

      it('displays French quality checks', () => {
        render(<QualityCoachPanel analysis={createMockAnalysis()} language="fr" />)
        expect(screen.getByText('Ouverture positive')).toBeInTheDocument()
        expect(screen.getByText('Base factuelle')).toBeInTheDocument()
        expect(screen.getByText('Focus solution')).toBeInTheDocument()
      })

      it('displays French great message text', () => {
        render(<QualityCoachPanel analysis={createMockAnalysis({ isAcceptable: true, issues: [] })} language="fr" />)
        expect(screen.getByText('Excellent message !')).toBeInTheDocument()
      })

      it('displays French issue type labels', () => {
        const analysis = createMockAnalysis({
          issues: [createMockIssue({ issueType: 'accusatory_you' })],
        })
        render(<QualityCoachPanel analysis={analysis} language="fr" />)
        expect(screen.getByText('Langage accusateur')).toBeInTheDocument()
      })

      it('displays French section headers', () => {
        const analysis = createMockAnalysis({
          issues: [createMockIssue()],
        })
        render(<QualityCoachPanel analysis={analysis} language="fr" />)
        expect(screen.getByText('Problèmes détectés')).toBeInTheDocument()
      })

      it('displays French apply suggestion button', () => {
        const analysis = createMockAnalysis({
          rewriteSuggestions: [createMockRewrite()],
        })
        render(<QualityCoachPanel analysis={analysis} onApplyRewrite={vi.fn()} language="fr" />)
        expect(screen.getByRole('button', { name: 'Appliquer la suggestion' })).toBeInTheDocument()
      })
    })

    describe('Loading and Empty States', () => {
      it('displays English loading message by default', () => {
        render(<QualityCoachPanel analysis={null} isLoading={true} />)
        expect(screen.getByText('Analyzing message...')).toBeInTheDocument()
      })

      it('displays French loading message when language is fr', () => {
        render(<QualityCoachPanel analysis={null} isLoading={true} language="fr" />)
        expect(screen.getByText('Analyse en cours...')).toBeInTheDocument()
      })

      it('displays English empty state message by default', () => {
        render(<QualityCoachPanel analysis={null} isLoading={false} />)
        expect(screen.getByText('Start typing to see quality analysis')).toBeInTheDocument()
      })

      it('displays French empty state message when language is fr', () => {
        render(<QualityCoachPanel analysis={null} isLoading={false} language="fr" />)
        expect(screen.getByText("Commencez à taper pour voir l'analyse de qualité")).toBeInTheDocument()
      })
    })
  })
})
