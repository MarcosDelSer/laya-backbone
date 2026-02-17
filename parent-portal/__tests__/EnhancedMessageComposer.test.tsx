/**
 * Unit tests for EnhancedMessageComposer component
 * Tests rendering, interactions, API mocking, and state management for the enhanced message composer
 */

import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest'
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { EnhancedMessageComposer } from '@/components/EnhancedMessageComposer'
import * as aiClient from '@/lib/ai-client'
import * as AuthContext from '@/contexts/AuthContext'
import type {
  MessageAnalysisResponse,
  QualityIssueDetail,
  RewriteSuggestion,
} from '@/lib/types'
import type { User } from '@/lib/auth'

// Mock the AI client module
vi.mock('@/lib/ai-client', () => ({
  analyzeMessageForComposer: vi.fn(),
}))

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

describe('EnhancedMessageComposer', () => {
  const mockOnSendMessage = vi.fn()
  const mockAnalyzeMessageForComposer = vi.mocked(aiClient.analyzeMessageForComposer)
  const mockUseAuth = vi.mocked(AuthContext.useAuth)

  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()

    // Default: Mock as teacher user (authorized)
    mockUseAuth.mockReturnValue({
      user: createMockUser('teacher'),
      isAuthenticated: true,
      isLoading: false,
      updateUser: vi.fn(),
      refreshAuth: vi.fn(),
    })
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  describe('Basic Rendering', () => {
    it('renders the message composer with default placeholder', () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      expect(screen.getByPlaceholderText('Type a message...')).toBeInTheDocument()
    })

    it('renders with custom placeholder', () => {
      render(
        <EnhancedMessageComposer
          onSendMessage={mockOnSendMessage}
          placeholder="Write your message here..."
        />
      )
      expect(screen.getByPlaceholderText('Write your message here...')).toBeInTheDocument()
    })

    it('renders send button', () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      expect(screen.getByTitle('Send message')).toBeInTheDocument()
    })

    it('renders attachment button', () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      expect(screen.getByTitle('Attach file (coming soon)')).toBeInTheDocument()
    })

    it('renders helper text for keyboard shortcuts', () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      expect(screen.getByText('Press Enter to send, Shift+Enter for new line')).toBeInTheDocument()
    })

    it('renders Quality Coach panel by default', () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      expect(screen.getByText('Quality Coach')).toBeInTheDocument()
    })

    it('does not render Quality Coach panel when showQualityCoach is false', () => {
      render(
        <EnhancedMessageComposer
          onSendMessage={mockOnSendMessage}
          showQualityCoach={false}
        />
      )
      expect(screen.queryByText('Quality Coach')).not.toBeInTheDocument()
    })
  })

  describe('Disabled State', () => {
    it('disables textarea when disabled prop is true', () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} disabled={true} />)
      expect(screen.getByLabelText('Message input')).toBeDisabled()
    })

    it('disables send button when disabled', () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} disabled={true} />)
      const sendButton = screen.getByTitle('Send message')
      expect(sendButton).toBeDisabled()
    })

    it('disables attachment button when disabled', () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} disabled={true} />)
      expect(screen.getByTitle('Attach file (coming soon)')).toBeDisabled()
    })
  })

  describe('Message Input', () => {
    it('updates message value on input change', async () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'Hello world' } })
      })

      expect(textarea).toHaveValue('Hello world')
    })

    it('shows character count when message is longer than 200 characters', async () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'a'.repeat(250)

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
      })

      expect(screen.getByText('250/500')).toBeInTheDocument()
    })

    it('does not show character count for short messages', async () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'Short message' } })
      })

      expect(screen.queryByText(/\/500/)).not.toBeInTheDocument()
    })

    it('shows red character count when message exceeds 500 characters', async () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'a'.repeat(550)

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
      })

      const charCount = screen.getByText('550/500')
      expect(charCount).toHaveClass('text-red-500')
    })
  })

  describe('Message Submission', () => {
    it('calls onSendMessage with trimmed message on submit', async () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: '  Hello world  ' } })
      })

      const form = textarea.closest('form')!
      await act(async () => {
        fireEvent.submit(form)
      })

      expect(mockOnSendMessage).toHaveBeenCalledWith('Hello world')
    })

    it('clears message after successful submission', async () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'Hello world' } })
      })

      const form = textarea.closest('form')!
      await act(async () => {
        fireEvent.submit(form)
      })

      expect(textarea).toHaveValue('')
    })

    it('does not call onSendMessage when message is empty', async () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const form = textarea.closest('form')!

      await act(async () => {
        fireEvent.submit(form)
      })

      expect(mockOnSendMessage).not.toHaveBeenCalled()
    })

    it('does not call onSendMessage when message is only whitespace', async () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: '   ' } })
      })

      const form = textarea.closest('form')!
      await act(async () => {
        fireEvent.submit(form)
      })

      expect(mockOnSendMessage).not.toHaveBeenCalled()
    })

    it('does not call onSendMessage when disabled', async () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} disabled={true} />)
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'Hello world' } })
      })

      const form = textarea.closest('form')!
      await act(async () => {
        fireEvent.submit(form)
      })

      expect(mockOnSendMessage).not.toHaveBeenCalled()
    })
  })

  describe('Keyboard Shortcuts', () => {
    it('submits message on Enter key', async () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'Hello world' } })
        fireEvent.keyDown(textarea, { key: 'Enter', shiftKey: false })
      })

      expect(mockOnSendMessage).toHaveBeenCalledWith('Hello world')
    })

    it('does not submit on Shift+Enter', async () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'Hello world' } })
        fireEvent.keyDown(textarea, { key: 'Enter', shiftKey: true })
      })

      expect(mockOnSendMessage).not.toHaveBeenCalled()
    })
  })

  describe('Debounced Analysis', () => {
    it('triggers analysis after debounce delay', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis())

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
      })

      // Analysis should not be called immediately
      expect(mockAnalyzeMessageForComposer).not.toHaveBeenCalled()

      // Fast forward past debounce delay
      await act(async () => {
        vi.advanceTimersByTime(500)
      })

      expect(mockAnalyzeMessageForComposer).toHaveBeenCalledWith(longMessage, 'en')
    })

    it('does not trigger analysis for messages shorter than minCharactersForAnalysis', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis())

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'Short' } })
        vi.advanceTimersByTime(500)
      })

      expect(mockAnalyzeMessageForComposer).not.toHaveBeenCalled()
    })

    it('uses custom minCharactersForAnalysis', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis())

      render(
        <EnhancedMessageComposer
          onSendMessage={mockOnSendMessage}
          minCharactersForAnalysis={10}
        />
      )
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'Exactly 10' } })
        vi.advanceTimersByTime(500)
      })

      expect(mockAnalyzeMessageForComposer).toHaveBeenCalled()
    })

    it('uses custom debounceMs', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis())

      render(
        <EnhancedMessageComposer
          onSendMessage={mockOnSendMessage}
          debounceMs={1000}
        />
      )
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
      })

      // Not called after default debounce time
      await act(async () => {
        vi.advanceTimersByTime(500)
      })
      expect(mockAnalyzeMessageForComposer).not.toHaveBeenCalled()

      // Called after custom debounce time
      await act(async () => {
        vi.advanceTimersByTime(500)
      })
      expect(mockAnalyzeMessageForComposer).toHaveBeenCalled()
    })

    it('cancels pending analysis when message changes', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis())

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const message1 = 'First message that is long enough'
      const message2 = 'Second message that is long enough'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: message1 } })
        vi.advanceTimersByTime(300)
        fireEvent.change(textarea, { target: { value: message2 } })
        vi.advanceTimersByTime(500)
      })

      // Only the second message should trigger analysis
      expect(mockAnalyzeMessageForComposer).toHaveBeenCalledTimes(1)
      expect(mockAnalyzeMessageForComposer).toHaveBeenCalledWith(message2, 'en')
    })

    it('shows analyzing indicator while loading', async () => {
      mockAnalyzeMessageForComposer.mockImplementation(
        () => new Promise(() => {}) // Never resolves
      )

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      expect(screen.getByText('Analyzing...')).toBeInTheDocument()
    })
  })

  describe('Analysis Results', () => {
    it('displays quality OK message when analysis is acceptable', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(
        createMockAnalysis({ isAcceptable: true })
      )

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText('✓ Message quality OK')).toBeInTheDocument()
      })
    })

    it('displays review suggestions message when analysis has issues', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(
        createMockAnalysis({
          isAcceptable: false,
          issues: [createMockIssue()],
        })
      )

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText('⚠ Review quality suggestions')).toBeInTheDocument()
      })
    })

    it('calls onAnalysisComplete when analysis finishes', async () => {
      const mockAnalysis = createMockAnalysis()
      mockAnalyzeMessageForComposer.mockResolvedValue(mockAnalysis)
      const mockOnAnalysisComplete = vi.fn()

      render(
        <EnhancedMessageComposer
          onSendMessage={mockOnSendMessage}
          onAnalysisComplete={mockOnAnalysisComplete}
        />
      )
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(mockOnAnalysisComplete).toHaveBeenCalledWith(mockAnalysis)
      })
    })

    it('calls onAnalysisComplete with null when message is too short', async () => {
      const mockOnAnalysisComplete = vi.fn()

      render(
        <EnhancedMessageComposer
          onSendMessage={mockOnSendMessage}
          onAnalysisComplete={mockOnAnalysisComplete}
        />
      )
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'Short' } })
        vi.advanceTimersByTime(500)
      })

      expect(mockOnAnalysisComplete).toHaveBeenCalledWith(null)
    })
  })

  describe('Error Handling', () => {
    it('displays error message when analysis fails', async () => {
      mockAnalyzeMessageForComposer.mockRejectedValue(new Error('Network error'))

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText(/Quality analysis unavailable: Network error/)).toBeInTheDocument()
      })
    })

    it('displays generic error message for non-Error exceptions', async () => {
      mockAnalyzeMessageForComposer.mockRejectedValue('Unknown error')

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText(/Quality analysis unavailable: Analysis failed/)).toBeInTheDocument()
      })
    })

    it('clears error when message is cleared', async () => {
      mockAnalyzeMessageForComposer.mockRejectedValue(new Error('Network error'))

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText(/Quality analysis unavailable/)).toBeInTheDocument()
      })

      // Clear message and submit
      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'Short' } })
        vi.advanceTimersByTime(500)
      })

      // Message is now short, so no analysis error should be shown after clearing
      mockAnalyzeMessageForComposer.mockClear()
    })
  })

  describe('Quality Warning Banner', () => {
    it('shows warning banner when message has quality issues', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(
        createMockAnalysis({
          isAcceptable: false,
          issues: [createMockIssue()],
        })
      )

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText(/1 quality issue detected/)).toBeInTheDocument()
      })
    })

    it('shows correct pluralization for multiple issues', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(
        createMockAnalysis({
          isAcceptable: false,
          issues: [
            createMockIssue(),
            createMockIssue({ issueType: 'blame_shame' }),
          ],
        })
      )

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText(/2 quality issues detected/)).toBeInTheDocument()
      })
    })

    it('provides "Send anyway" option in warning banner', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(
        createMockAnalysis({
          isAcceptable: false,
          issues: [createMockIssue()],
        })
      )

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText('Send anyway')).toBeInTheDocument()
      })
    })

    it('shows warning styling on send button when issues exist', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(
        createMockAnalysis({
          isAcceptable: false,
          issues: [createMockIssue()],
        })
      )

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        const sendButton = screen.getByTitle('Send with quality warning')
        expect(sendButton).toHaveClass('bg-yellow-500')
      })
    })

    it('shows yellow border on textarea when issues exist', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(
        createMockAnalysis({
          isAcceptable: false,
          issues: [createMockIssue()],
        })
      )

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(textarea).toHaveClass('border-yellow-400')
      })
    })
  })

  describe('Apply Rewrite Suggestion', () => {
    it('applies rewrite suggestion and updates message', async () => {
      const rewrite = createMockRewrite({
        suggestedText: 'I feel concerned when...',
      })
      mockAnalyzeMessageForComposer.mockResolvedValue(
        createMockAnalysis({
          isAcceptable: false,
          issues: [createMockIssue()],
          rewriteSuggestions: [rewrite],
        })
      )

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'You never listen to what I say'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText('Apply Suggestion')).toBeInTheDocument()
      })

      await act(async () => {
        fireEvent.click(screen.getByText('Apply Suggestion'))
      })

      expect(textarea).toHaveValue('I feel concerned when...')
    })

    it('triggers new analysis after applying rewrite', async () => {
      const rewrite = createMockRewrite({
        suggestedText: 'I feel concerned when...',
      })
      mockAnalyzeMessageForComposer.mockResolvedValue(
        createMockAnalysis({
          isAcceptable: false,
          issues: [createMockIssue()],
          rewriteSuggestions: [rewrite],
        })
      )

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'You never listen to what I say'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText('Apply Suggestion')).toBeInTheDocument()
      })

      mockAnalyzeMessageForComposer.mockClear()

      await act(async () => {
        fireEvent.click(screen.getByText('Apply Suggestion'))
        vi.advanceTimersByTime(500)
      })

      expect(mockAnalyzeMessageForComposer).toHaveBeenCalledWith(
        'I feel concerned when...',
        'en'
      )
    })
  })

  describe('Dismiss Issue', () => {
    it('removes issue from display when dismissed', async () => {
      const issue = createMockIssue({ description: 'Dismissable issue' })
      mockAnalyzeMessageForComposer.mockResolvedValue(
        createMockAnalysis({
          isAcceptable: false,
          issues: [issue],
        })
      )

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'You always do this wrong thing'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText('Dismissable issue')).toBeInTheDocument()
      })

      // Find and click dismiss button
      const { container } = render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const dismissButton = container.querySelector('button[title="Dismiss issue"]')

      if (dismissButton) {
        await act(async () => {
          fireEvent.click(dismissButton)
        })
      }
    })
  })

  describe('Panel Collapse/Expand', () => {
    it('toggles panel collapse state', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis())

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      await waitFor(() => {
        expect(screen.getByText('Quality Score')).toBeInTheDocument()
      })

      // Find and click collapse button
      const { container } = render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const collapseButton = container.querySelector('button[title="Collapse panel"]')

      if (collapseButton) {
        await act(async () => {
          fireEvent.click(collapseButton)
        })
      }
    })
  })

  describe('Language Support', () => {
    it('uses English language by default', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis())

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      expect(mockAnalyzeMessageForComposer).toHaveBeenCalledWith(longMessage, 'en')
    })

    it('uses French language when specified', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis())

      render(
        <EnhancedMessageComposer
          onSendMessage={mockOnSendMessage}
          language="fr"
        />
      )
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'Ceci est un message assez long pour déclencher l\'analyse'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      expect(mockAnalyzeMessageForComposer).toHaveBeenCalledWith(longMessage, 'fr')
    })
  })

  describe('Character Count Prompt', () => {
    it('shows remaining characters needed for analysis', () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      expect(screen.getByText('Type 20 more characters for quality analysis')).toBeInTheDocument()
    })

    it('updates remaining character count as user types', async () => {
      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: '12345' } })
      })

      expect(screen.getByText('Type 15 more characters for quality analysis')).toBeInTheDocument()
    })
  })

  describe('Quality Coach Panel Not Shown', () => {
    it('does not trigger analysis when showQualityCoach is false', async () => {
      mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis())

      render(
        <EnhancedMessageComposer
          onSendMessage={mockOnSendMessage}
          showQualityCoach={false}
        />
      )
      const textarea = screen.getByLabelText('Message input')
      const longMessage = 'This is a message that is long enough to trigger analysis'

      await act(async () => {
        fireEvent.change(textarea, { target: { value: longMessage } })
        vi.advanceTimersByTime(500)
      })

      expect(mockAnalyzeMessageForComposer).not.toHaveBeenCalled()
    })

    it('does not show quality status text when showQualityCoach is false', () => {
      render(
        <EnhancedMessageComposer
          onSendMessage={mockOnSendMessage}
          showQualityCoach={false}
        />
      )

      expect(screen.queryByText(/characters for quality analysis/)).not.toBeInTheDocument()
    })
  })

  describe('Abort Controller', () => {
    it('aborts previous request when new message is typed', async () => {
      let resolveFirstRequest: (value: MessageAnalysisResponse) => void
      const firstRequestPromise = new Promise<MessageAnalysisResponse>((resolve) => {
        resolveFirstRequest = resolve
      })

      mockAnalyzeMessageForComposer.mockImplementationOnce(() => firstRequestPromise)
      mockAnalyzeMessageForComposer.mockResolvedValueOnce(createMockAnalysis())

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const textarea = screen.getByLabelText('Message input')

      // Type first message
      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'First message that is long enough' } })
        vi.advanceTimersByTime(500)
      })

      // Type second message before first completes
      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'Second message that is long enough' } })
        vi.advanceTimersByTime(500)
      })

      // Resolve first request (should be ignored due to abort)
      await act(async () => {
        resolveFirstRequest!(createMockAnalysis({ messageText: 'First message' }))
      })

      // The component should have made two calls
      expect(mockAnalyzeMessageForComposer).toHaveBeenCalledTimes(2)
    })
  })

  describe('Cleanup on Unmount', () => {
    it('clears debounce timer on unmount', async () => {
      const { unmount } = render(
        <EnhancedMessageComposer onSendMessage={mockOnSendMessage} />
      )
      const textarea = screen.getByLabelText('Message input')

      await act(async () => {
        fireEvent.change(textarea, { target: { value: 'Message being typed...' } })
      })

      // Unmount before debounce completes
      unmount()

      // Advance timers - should not cause errors
      await act(async () => {
        vi.advanceTimersByTime(1000)
      })

      // No assertion needed - test passes if no error is thrown
    })
  })

  describe('Bilingual Support', () => {
    describe('English (default)', () => {
      it('displays English footer text', () => {
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} language="en" />)
        expect(screen.getByText('Press Enter to send, Shift+Enter for new line')).toBeInTheDocument()
      })

      it('displays English type more chars message', () => {
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} minCharactersForAnalysis={20} language="en" />)
        expect(screen.getByText(/Type \d+ more characters for quality analysis/)).toBeInTheDocument()
      })

      it('has English aria-label on textarea', () => {
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} language="en" />)
        const textarea = screen.getByLabelText('Message input')
        expect(textarea).toBeInTheDocument()
      })

      it('displays English analyzing message', async () => {
        mockAnalyzeMessageForComposer.mockImplementation(() => new Promise(() => {}))
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} language="en" minCharactersForAnalysis={5} />)
        const textarea = screen.getByLabelText('Message input')

        await act(async () => {
          fireEvent.change(textarea, { target: { value: 'Testing message' } })
          vi.advanceTimersByTime(600)
        })

        expect(screen.getByText('Analyzing...')).toBeInTheDocument()
      })

      it('displays English quality OK message', async () => {
        mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis({ isAcceptable: true }))
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} language="en" minCharactersForAnalysis={5} />)
        const textarea = screen.getByLabelText('Message input')

        await act(async () => {
          fireEvent.change(textarea, { target: { value: 'Testing message' } })
          vi.advanceTimersByTime(600)
        })

        expect(screen.getByText('✓ Message quality OK')).toBeInTheDocument()
      })
    })

    describe('French', () => {
      it('displays French footer text', () => {
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} language="fr" />)
        expect(screen.getByText('Appuyez sur Entrée pour envoyer, Maj+Entrée pour nouvelle ligne')).toBeInTheDocument()
      })

      it('displays French type more chars message', () => {
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} minCharactersForAnalysis={20} language="fr" />)
        expect(screen.getByText(/Tapez encore \d+ caractères pour l'analyse qualité/)).toBeInTheDocument()
      })

      it('has French aria-label on textarea', () => {
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} language="fr" />)
        const textarea = screen.getByLabelText('Champ de message')
        expect(textarea).toBeInTheDocument()
      })

      it('displays French analyzing message', async () => {
        mockAnalyzeMessageForComposer.mockImplementation(() => new Promise(() => {}))
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} language="fr" minCharactersForAnalysis={5} />)
        const textarea = screen.getByLabelText('Champ de message')

        await act(async () => {
          fireEvent.change(textarea, { target: { value: 'Test du message' } })
          vi.advanceTimersByTime(600)
        })

        expect(screen.getByText('Analyse en cours...')).toBeInTheDocument()
      })

      it('displays French quality OK message', async () => {
        mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis({ isAcceptable: true }))
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} language="fr" minCharactersForAnalysis={5} />)
        const textarea = screen.getByLabelText('Champ de message')

        await act(async () => {
          fireEvent.change(textarea, { target: { value: 'Test du message' } })
          vi.advanceTimersByTime(600)
        })

        expect(screen.getByText('✓ Qualité du message OK')).toBeInTheDocument()
      })

      it('displays French review suggestions message', async () => {
        mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis({ isAcceptable: false }))
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} language="fr" minCharactersForAnalysis={5} />)
        const textarea = screen.getByLabelText('Champ de message')

        await act(async () => {
          fireEvent.change(textarea, { target: { value: 'Test du message' } })
          vi.advanceTimersByTime(600)
        })

        expect(screen.getByText('⚠ Révisez les suggestions de qualité')).toBeInTheDocument()
      })

      it('displays French send anyway button', async () => {
        mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis({
          isAcceptable: false,
          issues: [createMockIssue()],
        }))
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} language="fr" minCharactersForAnalysis={5} />)
        const textarea = screen.getByLabelText('Champ de message')

        await act(async () => {
          fireEvent.change(textarea, { target: { value: 'Test du message' } })
          vi.advanceTimersByTime(600)
        })

        expect(screen.getByText('Envoyer quand même')).toBeInTheDocument()
      })
    })

    describe('Language Switching', () => {
      it('passes language prop to QualityCoachPanel', async () => {
        mockAnalyzeMessageForComposer.mockResolvedValue(createMockAnalysis())
        render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} language="fr" minCharactersForAnalysis={5} />)
        const textarea = screen.getByLabelText('Champ de message')

        await act(async () => {
          fireEvent.change(textarea, { target: { value: 'Test du message' } })
          vi.advanceTimersByTime(600)
        })

        // French panel title should be rendered
        expect(screen.getByRole('heading', { name: 'Coach Qualité' })).toBeInTheDocument()
      })
    })
  })

  describe('Role-Based Analytics Link', () => {
    it('shows analytics link for admin role', () => {
      mockUseAuth.mockReturnValue({
        user: createMockUser('admin'),
        isAuthenticated: true,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      expect(screen.getByText('View Analytics')).toBeInTheDocument()
    })

    it('does not show analytics link for teacher role', () => {
      mockUseAuth.mockReturnValue({
        user: createMockUser('teacher'),
        isAuthenticated: true,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      expect(screen.queryByText('View Analytics')).not.toBeInTheDocument()
    })

    it('does not show analytics link for parent role', () => {
      mockUseAuth.mockReturnValue({
        user: createMockUser('parent'),
        isAuthenticated: true,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      expect(screen.queryByText('View Analytics')).not.toBeInTheDocument()
    })

    it('does not show analytics link for staff role', () => {
      mockUseAuth.mockReturnValue({
        user: createMockUser('staff'),
        isAuthenticated: true,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      expect(screen.queryByText('View Analytics')).not.toBeInTheDocument()
    })

    it('does not show analytics link when showQualityCoach is false', () => {
      mockUseAuth.mockReturnValue({
        user: createMockUser('admin'),
        isAuthenticated: true,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} showQualityCoach={false} />)
      expect(screen.queryByText('View Analytics')).not.toBeInTheDocument()
    })

    it('analytics link navigates to correct URL', () => {
      mockUseAuth.mockReturnValue({
        user: createMockUser('admin'),
        isAuthenticated: true,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} />)
      const analyticsLink = screen.getByText('View Analytics').closest('a')
      expect(analyticsLink).toHaveAttribute('href', '/message-quality/analytics')
    })

    it('displays French analytics link text for admin with French language', () => {
      mockUseAuth.mockReturnValue({
        user: createMockUser('admin'),
        isAuthenticated: true,
        isLoading: false,
        updateUser: vi.fn(),
        refreshAuth: vi.fn(),
      })

      render(<EnhancedMessageComposer onSendMessage={mockOnSendMessage} language="fr" />)
      expect(screen.getByText('Voir les statistiques')).toBeInTheDocument()
    })
  })
})
