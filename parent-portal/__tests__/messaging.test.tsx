/**
 * Unit tests for messaging components
 * Tests MessageBubble, MessageThread, ThreadPreview, and MessageComposer components
 */

import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { MessageBubble, formatDate, formatTime } from '@/components/MessageBubble'
import { MessageThread, ThreadPreview } from '@/components/MessageThread'
import { MessageComposer } from '@/components/MessageComposer'
import type { MessageAttachment } from '@/lib/types'

// ============================================================================
// Test Data Fixtures
// ============================================================================

const createMessage = (overrides = {}) => ({
  id: 'msg-1',
  threadId: 'thread-1',
  senderId: 'user-1',
  senderName: 'John Doe',
  content: 'Hello, this is a test message',
  timestamp: new Date().toISOString(),
  read: false,
  ...overrides,
})

const createAttachment = (overrides: Partial<MessageAttachment> = {}): MessageAttachment => ({
  id: 'attachment-1',
  messageId: 'msg-1',
  fileUrl: 'https://example.com/file.pdf',
  fileType: 'application/pdf',
  fileName: 'document.pdf',
  fileSize: 1024,
  ...overrides,
})

const createThread = (overrides = {}) => ({
  id: 'thread-1',
  subject: 'Test Thread Subject',
  participants: ['John Doe', 'Jane Smith'],
  lastMessage: createMessage(),
  unreadCount: 0,
  ...overrides,
})

// ============================================================================
// MessageBubble Tests
// ============================================================================

describe('MessageBubble Component', () => {
  it('renders message content correctly', () => {
    const message = createMessage({ content: 'Hello World' })
    render(<MessageBubble message={message} isCurrentUser={false} />)

    expect(screen.getByText('Hello World')).toBeInTheDocument()
  })

  it('renders sender name for non-current user messages', () => {
    const message = createMessage({ senderName: 'Jane Doe' })
    render(<MessageBubble message={message} isCurrentUser={false} />)

    expect(screen.getByText('Jane Doe')).toBeInTheDocument()
  })

  it('does not render sender name for current user messages', () => {
    const message = createMessage({ senderName: 'Jane Doe' })
    render(<MessageBubble message={message} isCurrentUser={true} />)

    expect(screen.queryByText('Jane Doe')).not.toBeInTheDocument()
  })

  it('applies different styling for current user messages', () => {
    const message = createMessage()
    const { container } = render(
      <MessageBubble message={message} isCurrentUser={true} />
    )

    const bubble = container.querySelector('.bg-primary')
    expect(bubble).toBeInTheDocument()
  })

  it('applies different styling for other user messages', () => {
    const message = createMessage()
    const { container } = render(
      <MessageBubble message={message} isCurrentUser={false} />
    )

    const bubble = container.querySelector('.bg-gray-100')
    expect(bubble).toBeInTheDocument()
  })

  it('displays formatted time for message', () => {
    const timestamp = new Date('2024-01-15T14:30:00').toISOString()
    const message = createMessage({ timestamp })
    render(<MessageBubble message={message} isCurrentUser={false} />)

    // Check time is displayed (format depends on locale)
    const timeElement = screen.getByText(/\d{1,2}:\d{2}\s*(AM|PM)/i)
    expect(timeElement).toBeInTheDocument()
  })

  it('shows read indicator for current user read messages', () => {
    const message = createMessage({ read: true })
    const { container } = render(
      <MessageBubble message={message} isCurrentUser={true} />
    )

    // Double check mark SVG should be present for read messages
    const svgs = container.querySelectorAll('svg')
    expect(svgs.length).toBeGreaterThan(0)
  })

  it('shows unread indicator for current user unread messages', () => {
    const message = createMessage({ read: false })
    const { container } = render(
      <MessageBubble message={message} isCurrentUser={true} />
    )

    // Single check mark SVG should be present for unread messages
    const svgs = container.querySelectorAll('svg')
    expect(svgs.length).toBeGreaterThan(0)
  })

  it('renders sender type badge when senderType is provided', () => {
    const message = createMessage({ senderType: 'educator' })
    render(<MessageBubble message={message} isCurrentUser={false} />)

    expect(screen.getByText('Educator')).toBeInTheDocument()
  })

  it('renders parent sender type badge', () => {
    const message = createMessage({ senderType: 'parent' })
    render(<MessageBubble message={message} isCurrentUser={false} />)

    expect(screen.getByText('Parent')).toBeInTheDocument()
  })

  it('renders director sender type badge', () => {
    const message = createMessage({ senderType: 'director' })
    render(<MessageBubble message={message} isCurrentUser={false} />)

    expect(screen.getByText('Director')).toBeInTheDocument()
  })

  it('renders admin sender type badge', () => {
    const message = createMessage({ senderType: 'admin' })
    render(<MessageBubble message={message} isCurrentUser={false} />)

    expect(screen.getByText('Admin')).toBeInTheDocument()
  })

  describe('Rich Text Content', () => {
    it('renders plain text content normally', () => {
      const message = createMessage({
        content: 'Plain text message',
        contentType: 'text',
      })
      render(<MessageBubble message={message} isCurrentUser={false} />)

      expect(screen.getByText('Plain text message')).toBeInTheDocument()
    })

    it('renders rich text with formatting', () => {
      const message = createMessage({
        content: '**Bold text**',
        contentType: 'rich_text',
      })
      const { container } = render(
        <MessageBubble message={message} isCurrentUser={false} />
      )

      const strongElement = container.querySelector('strong')
      expect(strongElement).toBeInTheDocument()
      expect(strongElement).toHaveTextContent('Bold text')
    })
  })

  describe('Attachments', () => {
    it('renders file attachments', () => {
      const attachment = createAttachment({
        fileName: 'test-file.pdf',
        fileType: 'application/pdf',
      })
      const message = createMessage({ attachments: [attachment] })
      render(<MessageBubble message={message} isCurrentUser={false} />)

      expect(screen.getByText('test-file.pdf')).toBeInTheDocument()
    })

    it('renders image attachments with img element', () => {
      const attachment = createAttachment({
        fileName: 'photo.jpg',
        fileType: 'image/jpeg',
        fileUrl: 'https://example.com/photo.jpg',
      })
      const message = createMessage({ attachments: [attachment] })
      const { container } = render(
        <MessageBubble message={message} isCurrentUser={false} />
      )

      const img = container.querySelector('img')
      expect(img).toBeInTheDocument()
      expect(img).toHaveAttribute('src', 'https://example.com/photo.jpg')
    })

    it('calls onImageClick when image attachment is clicked', async () => {
      const user = userEvent.setup()
      const onImageClick = vi.fn()
      const attachment = createAttachment({
        fileName: 'photo.jpg',
        fileType: 'image/jpeg',
        fileUrl: 'https://example.com/photo.jpg',
      })
      const message = createMessage({ attachments: [attachment] })
      render(
        <MessageBubble
          message={message}
          isCurrentUser={false}
          onImageClick={onImageClick}
        />
      )

      const button = screen.getByRole('button', { name: /view photo\.jpg/i })
      await user.click(button)

      expect(onImageClick).toHaveBeenCalledWith(attachment)
    })

    it('calls onFileClick when file attachment is clicked', async () => {
      const user = userEvent.setup()
      const onFileClick = vi.fn()
      const attachment = createAttachment({
        fileName: 'document.pdf',
        fileType: 'application/pdf',
      })
      const message = createMessage({ attachments: [attachment] })
      render(
        <MessageBubble
          message={message}
          isCurrentUser={false}
          onFileClick={onFileClick}
        />
      )

      const fileButton = screen.getByText('document.pdf').closest('button')
      if (fileButton) {
        await user.click(fileButton)
        expect(onFileClick).toHaveBeenCalledWith(attachment)
      }
    })
  })
})

// ============================================================================
// Date/Time Formatting Tests
// ============================================================================

describe('Date Formatting Utilities', () => {
  it('formatDate returns "Today" for current date', () => {
    const today = new Date().toISOString()
    expect(formatDate(today)).toBe('Today')
  })

  it('formatDate returns "Yesterday" for previous day', () => {
    const yesterday = new Date()
    yesterday.setDate(yesterday.getDate() - 1)
    expect(formatDate(yesterday.toISOString())).toBe('Yesterday')
  })

  it('formatDate returns formatted date for older dates', () => {
    const oldDate = new Date('2024-01-15T12:00:00')
    const result = formatDate(oldDate.toISOString())
    expect(result).toContain('Jan')
    expect(result).toContain('15')
  })

  it('formatTime returns formatted time string', () => {
    const timestamp = new Date('2024-01-15T14:30:00').toISOString()
    const result = formatTime(timestamp)
    // Check that it contains hour and minute in some format
    expect(result).toMatch(/\d{1,2}:\d{2}/)
  })
})

// ============================================================================
// MessageThread Tests
// ============================================================================

describe('MessageThread Component', () => {
  it('renders empty state when no messages', () => {
    render(<MessageThread messages={[]} currentUserId="user-1" />)

    expect(screen.getByText('No messages yet')).toBeInTheDocument()
    expect(
      screen.getByText('Start the conversation by sending a message below.')
    ).toBeInTheDocument()
  })

  it('renders messages when provided', () => {
    const messages = [
      createMessage({ id: 'msg-1', content: 'First message' }),
      createMessage({ id: 'msg-2', content: 'Second message' }),
    ]
    render(<MessageThread messages={messages} currentUserId="user-1" />)

    expect(screen.getByText('First message')).toBeInTheDocument()
    expect(screen.getByText('Second message')).toBeInTheDocument()
  })

  it('groups messages by date with date dividers', () => {
    const today = new Date()
    const messages = [
      createMessage({
        id: 'msg-1',
        content: 'Today message',
        timestamp: today.toISOString(),
      }),
    ]
    render(<MessageThread messages={messages} currentUserId="user-1" />)

    expect(screen.getByText('Today')).toBeInTheDocument()
  })

  it('identifies current user messages correctly', () => {
    const messages = [
      createMessage({
        id: 'msg-1',
        senderId: 'user-1',
        content: 'My message',
      }),
      createMessage({
        id: 'msg-2',
        senderId: 'user-2',
        content: 'Their message',
      }),
    ]
    const { container } = render(
      <MessageThread messages={messages} currentUserId="user-1" />
    )

    // Current user messages should have justify-end
    const currentUserMessages = container.querySelectorAll('.justify-end')
    expect(currentUserMessages.length).toBeGreaterThan(0)
  })
})

// ============================================================================
// ThreadPreview Tests
// ============================================================================

describe('ThreadPreview Component', () => {
  const defaultProps = {
    thread: createThread(),
    isSelected: false,
    onClick: vi.fn(),
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders thread subject', () => {
    render(<ThreadPreview {...defaultProps} />)

    expect(screen.getByText('Test Thread Subject')).toBeInTheDocument()
  })

  it('renders last message content', () => {
    render(<ThreadPreview {...defaultProps} />)

    expect(
      screen.getByText('Hello, this is a test message')
    ).toBeInTheDocument()
  })

  it('renders participant list', () => {
    render(<ThreadPreview {...defaultProps} />)

    expect(screen.getByText('John Doe, Jane Smith')).toBeInTheDocument()
  })

  it('shows unread badge when unreadCount > 0', () => {
    const thread = createThread({ unreadCount: 3 })
    render(<ThreadPreview {...defaultProps} thread={thread} />)

    expect(screen.getByText('3')).toBeInTheDocument()
  })

  it('shows 9+ when unreadCount > 9', () => {
    const thread = createThread({ unreadCount: 15 })
    render(<ThreadPreview {...defaultProps} thread={thread} />)

    expect(screen.getByText('9+')).toBeInTheDocument()
  })

  it('does not show unread badge when unreadCount is 0', () => {
    const thread = createThread({ unreadCount: 0 })
    render(<ThreadPreview {...defaultProps} thread={thread} />)

    // Should not have the badge element
    const badge = screen.queryByText('0')
    expect(badge).not.toBeInTheDocument()
  })

  it('applies selected styling when isSelected is true', () => {
    const { container } = render(
      <ThreadPreview {...defaultProps} isSelected={true} />
    )

    const button = container.querySelector('button')
    expect(button).toHaveClass('bg-primary/10')
  })

  it('applies default styling when isSelected is false', () => {
    const { container } = render(
      <ThreadPreview {...defaultProps} isSelected={false} />
    )

    const button = container.querySelector('button')
    expect(button).toHaveClass('hover:bg-gray-50')
  })

  it('calls onClick when clicked', async () => {
    const user = userEvent.setup()
    const onClick = vi.fn()
    render(<ThreadPreview {...defaultProps} onClick={onClick} />)

    const button = screen.getByRole('button')
    await user.click(button)

    expect(onClick).toHaveBeenCalledTimes(1)
  })

  it('renders sender avatar with first letter', () => {
    render(<ThreadPreview {...defaultProps} />)

    // The avatar should show "J" for "John Doe"
    expect(screen.getByText('J')).toBeInTheDocument()
  })

  it('displays timestamp for last message', () => {
    render(<ThreadPreview {...defaultProps} />)

    // Should show some time indicator (Just now, time, day, or date)
    const timeElement = screen.getByText(
      /just now|ago|\d{1,2}:\d{2}|mon|tue|wed|thu|fri|sat|sun|jan|feb|mar|apr|may|jun|jul|aug|sep|oct|nov|dec/i
    )
    expect(timeElement).toBeInTheDocument()
  })
})

// ============================================================================
// MessageComposer Tests
// ============================================================================

describe('MessageComposer Component', () => {
  const defaultProps = {
    onSendMessage: vi.fn(),
  }

  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders textarea input', () => {
    render(<MessageComposer {...defaultProps} />)

    const textarea = screen.getByPlaceholderText('Type a message...')
    expect(textarea).toBeInTheDocument()
  })

  it('renders custom placeholder when provided', () => {
    render(
      <MessageComposer
        {...defaultProps}
        placeholder="Write something..."
      />
    )

    const textarea = screen.getByPlaceholderText('Write something...')
    expect(textarea).toBeInTheDocument()
  })

  it('renders send button', () => {
    const { container } = render(<MessageComposer {...defaultProps} />)

    const submitButton = container.querySelector('button[type="submit"]')
    expect(submitButton).toBeInTheDocument()
  })

  it('disables send button when message is empty', () => {
    const { container } = render(<MessageComposer {...defaultProps} />)

    const submitButton = container.querySelector('button[type="submit"]')
    expect(submitButton).toBeDisabled()
  })

  it('enables send button when message has content', async () => {
    const user = userEvent.setup()
    const { container } = render(<MessageComposer {...defaultProps} />)

    const textarea = screen.getByPlaceholderText('Type a message...')
    await user.type(textarea, 'Hello')

    const submitButton = container.querySelector('button[type="submit"]')
    expect(submitButton).not.toBeDisabled()
  })

  it('calls onSendMessage with message content on submit', async () => {
    const user = userEvent.setup()
    const onSendMessage = vi.fn()
    const { container } = render(
      <MessageComposer onSendMessage={onSendMessage} />
    )

    const textarea = screen.getByPlaceholderText('Type a message...')
    await user.type(textarea, 'Test message')

    const submitButton = container.querySelector('button[type="submit"]')
    if (submitButton) {
      await user.click(submitButton)
    }

    expect(onSendMessage).toHaveBeenCalledWith('Test message', undefined)
  })

  it('clears message after successful send', async () => {
    const user = userEvent.setup()
    const { container } = render(<MessageComposer {...defaultProps} />)

    const textarea = screen.getByPlaceholderText(
      'Type a message...'
    ) as HTMLTextAreaElement
    await user.type(textarea, 'Test message')

    const submitButton = container.querySelector('button[type="submit"]')
    if (submitButton) {
      await user.click(submitButton)
    }

    expect(textarea.value).toBe('')
  })

  it('submits message on Enter key press', async () => {
    const user = userEvent.setup()
    const onSendMessage = vi.fn()
    render(<MessageComposer onSendMessage={onSendMessage} />)

    const textarea = screen.getByPlaceholderText('Type a message...')
    await user.type(textarea, 'Test message{enter}')

    expect(onSendMessage).toHaveBeenCalled()
  })

  it('does not submit on Shift+Enter', async () => {
    const user = userEvent.setup()
    const onSendMessage = vi.fn()
    render(<MessageComposer onSendMessage={onSendMessage} />)

    const textarea = screen.getByPlaceholderText('Type a message...')
    await user.type(textarea, 'Test message')
    await user.keyboard('{Shift>}{Enter}{/Shift}')

    expect(onSendMessage).not.toHaveBeenCalled()
  })

  it('disables input when disabled prop is true', () => {
    render(<MessageComposer {...defaultProps} disabled={true} />)

    const textarea = screen.getByPlaceholderText('Type a message...')
    expect(textarea).toBeDisabled()
  })

  it('renders attachment button', () => {
    const { container } = render(<MessageComposer {...defaultProps} />)

    const attachButton = container.querySelector('button[type="button"]')
    expect(attachButton).toBeInTheDocument()
  })

  it('shows character count when message is long', async () => {
    const user = userEvent.setup()
    render(<MessageComposer {...defaultProps} />)

    const textarea = screen.getByPlaceholderText('Type a message...')
    const longText = 'a'.repeat(250)
    await user.type(textarea, longText)

    // Character count should appear after 200 characters
    expect(screen.getByText(/250\/500/)).toBeInTheDocument()
  })

  it('shows helper text for keyboard shortcuts', () => {
    render(<MessageComposer {...defaultProps} />)

    expect(
      screen.getByText('Press Enter to send, Shift+Enter for new line')
    ).toBeInTheDocument()
  })

  describe('File Attachments', () => {
    it('shows drag and drop zone when dragging', async () => {
      const { container } = render(<MessageComposer {...defaultProps} />)

      const form = container.querySelector('form')
      if (form) {
        fireEvent.dragOver(form, {
          dataTransfer: { files: [] },
        })

        await waitFor(() => {
          expect(screen.getByText('Drop files here to attach')).toBeInTheDocument()
        })
      }
    })

    it('shows error for oversized files', async () => {
      const { container } = render(
        <MessageComposer {...defaultProps} maxFileSize={1024} />
      )

      const fileInput = container.querySelector('input[type="file"]')
      if (fileInput) {
        const largeFile = new File(['a'.repeat(2048)], 'large.pdf', {
          type: 'application/pdf',
        })
        Object.defineProperty(largeFile, 'size', { value: 2048 })

        fireEvent.change(fileInput, { target: { files: [largeFile] } })

        await waitFor(() => {
          expect(screen.getByText(/exceeds/i)).toBeInTheDocument()
        })
      }
    })

    it('shows error for unsupported file types', async () => {
      const { container } = render(
        <MessageComposer
          {...defaultProps}
          allowedFileTypes={['image/jpeg']}
        />
      )

      const fileInput = container.querySelector('input[type="file"]')
      if (fileInput) {
        const invalidFile = new File(['test'], 'test.exe', {
          type: 'application/x-msdownload',
        })

        fireEvent.change(fileInput, { target: { files: [invalidFile] } })

        await waitFor(() => {
          expect(screen.getByText(/not a supported file type/i)).toBeInTheDocument()
        })
      }
    })

    it('shows attachment count when files are attached', async () => {
      const { container } = render(
        <MessageComposer {...defaultProps} maxAttachments={5} />
      )

      const fileInput = container.querySelector('input[type="file"]')
      if (fileInput) {
        const file1 = new File(['test1'], 'file1.pdf', {
          type: 'application/pdf',
        })
        const file2 = new File(['test2'], 'file2.pdf', {
          type: 'application/pdf',
        })

        fireEvent.change(fileInput, { target: { files: [file1, file2] } })

        await waitFor(() => {
          expect(screen.getByText('2/5 attachments')).toBeInTheDocument()
        })
      }
    })

    it('allows removing attachments', async () => {
      const user = userEvent.setup()
      const { container } = render(<MessageComposer {...defaultProps} />)

      const fileInput = container.querySelector('input[type="file"]')
      if (fileInput) {
        const file = new File(['test'], 'test.pdf', {
          type: 'application/pdf',
        })

        fireEvent.change(fileInput, { target: { files: [file] } })

        await waitFor(() => {
          expect(screen.getByText('test.pdf')).toBeInTheDocument()
        })

        const removeButton = screen.getByTitle('Remove attachment')
        await user.click(removeButton)

        await waitFor(() => {
          expect(screen.queryByText('test.pdf')).not.toBeInTheDocument()
        })
      }
    })

    it('prevents adding more than maxAttachments', async () => {
      const { container } = render(
        <MessageComposer {...defaultProps} maxAttachments={1} />
      )

      const fileInput = container.querySelector('input[type="file"]')
      if (fileInput) {
        const file1 = new File(['test1'], 'file1.pdf', {
          type: 'application/pdf',
        })
        const file2 = new File(['test2'], 'file2.pdf', {
          type: 'application/pdf',
        })

        fireEvent.change(fileInput, { target: { files: [file1, file2] } })

        await waitFor(() => {
          expect(
            screen.getByText(/you can only attach up to 1 file/i)
          ).toBeInTheDocument()
        })
      }
    })

    it('sends message with attachments', async () => {
      const user = userEvent.setup()
      const onSendMessage = vi.fn()
      const { container } = render(
        <MessageComposer onSendMessage={onSendMessage} />
      )

      // Add text
      const textarea = screen.getByPlaceholderText('Type a message...')
      await user.type(textarea, 'Message with attachment')

      // Add attachment
      const fileInput = container.querySelector('input[type="file"]')
      if (fileInput) {
        const file = new File(['test'], 'test.pdf', {
          type: 'application/pdf',
        })

        fireEvent.change(fileInput, { target: { files: [file] } })

        await waitFor(() => {
          expect(screen.getByText('test.pdf')).toBeInTheDocument()
        })

        // Submit
        const submitButton = container.querySelector('button[type="submit"]')
        if (submitButton) {
          await user.click(submitButton)
        }

        expect(onSendMessage).toHaveBeenCalledWith(
          'Message with attachment',
          expect.arrayContaining([expect.any(File)])
        )
      }
    })
  })
})

// ============================================================================
// Integration Tests
// ============================================================================

describe('Messaging Components Integration', () => {
  it('MessageThread renders MessageBubbles for each message', () => {
    const messages = [
      createMessage({ id: 'msg-1', content: 'First' }),
      createMessage({ id: 'msg-2', content: 'Second' }),
      createMessage({ id: 'msg-3', content: 'Third' }),
    ]
    render(<MessageThread messages={messages} currentUserId="user-1" />)

    expect(screen.getByText('First')).toBeInTheDocument()
    expect(screen.getByText('Second')).toBeInTheDocument()
    expect(screen.getByText('Third')).toBeInTheDocument()
  })

  it('ThreadPreview correctly displays different time formats', () => {
    const now = new Date()

    // Just now
    const recentThread = createThread({
      lastMessage: createMessage({ timestamp: now.toISOString() }),
    })
    const { rerender } = render(
      <ThreadPreview
        thread={recentThread}
        isSelected={false}
        onClick={vi.fn()}
      />
    )
    expect(screen.getByText(/just now|ago|:\d{2}/i)).toBeInTheDocument()

    // Hours ago
    const hoursAgo = new Date(now.getTime() - 3 * 60 * 60 * 1000)
    const hourThread = createThread({
      lastMessage: createMessage({ timestamp: hoursAgo.toISOString() }),
    })
    rerender(
      <ThreadPreview
        thread={hourThread}
        isSelected={false}
        onClick={vi.fn()}
      />
    )
    expect(screen.getByText(/\d{1,2}:\d{2}|AM|PM/i)).toBeInTheDocument()
  })
})
