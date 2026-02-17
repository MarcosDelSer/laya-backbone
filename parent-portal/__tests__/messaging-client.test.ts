/**
 * Unit tests for messaging API client
 * Tests all messaging client functions with mocked API responses
 */

import { describe, it, expect, vi, beforeEach, Mock } from 'vitest'
import { aiServiceClient } from '@/lib/api'
import {
  getThreads,
  getThread,
  createThread,
  updateThread,
  archiveThread,
  getMessages,
  sendMessage,
  getMessage,
  markAsRead,
  markThreadAsRead,
  getUnreadCount,
  getNotificationPreferences,
  getNotificationPreference,
  createNotificationPreference,
  updateNotificationPreference,
  deleteNotificationPreference,
  createDefaultPreferences,
  setQuietHours,
  getThreadWithMessages,
  sendTextMessage,
  createDirectThread,
  type ListThreadsParams,
  type ListMessagesParams,
  type SetQuietHoursParams,
} from '@/lib/messaging-client'
import type {
  MessageThread,
  ThreadWithMessages,
  ThreadListResponse,
  Message,
  MessageListResponse,
  UnreadCountResponse,
  NotificationPreference,
  NotificationPreferenceListResponse,
  CreateThreadRequest,
  UpdateThreadRequest,
  SendMessageRequest,
  MarkAsReadRequest,
  NotificationPreferenceRequest,
} from '@/lib/types'

// ============================================================================
// Mock Setup
// ============================================================================

vi.mock('@/lib/api', () => ({
  aiServiceClient: {
    get: vi.fn(),
    post: vi.fn(),
    patch: vi.fn(),
    delete: vi.fn(),
  },
}))

// ============================================================================
// Test Data Fixtures
// ============================================================================

const createMockThread = (overrides: Partial<MessageThread> = {}): MessageThread => ({
  id: 'thread-1',
  subject: 'Test Thread',
  threadType: 'daily_log',
  childId: 'child-1',
  createdBy: 'parent-1',
  participants: [
    { userId: 'educator-1', userType: 'educator', displayName: 'Jane Educator' },
    { userId: 'parent-1', userType: 'parent', displayName: 'John Parent' },
  ],
  isActive: true,
  unreadCount: 0,
  lastMessage: 'Hello',
  lastMessageAt: '2024-01-15T10:00:00Z',
  createdAt: '2024-01-15T09:00:00Z',
  updatedAt: '2024-01-15T10:00:00Z',
  ...overrides,
})

const createMockMessage = (overrides: Partial<Message> = {}): Message => ({
  id: 'msg-1',
  threadId: 'thread-1',
  senderId: 'parent-1',
  senderType: 'parent',
  senderName: 'John Parent',
  content: 'Hello, this is a test message',
  contentType: 'text',
  isRead: false,
  attachments: [],
  createdAt: '2024-01-15T10:00:00Z',
  updatedAt: '2024-01-15T10:00:00Z',
  ...overrides,
})

const createMockPreference = (overrides: Partial<NotificationPreference> = {}): NotificationPreference => ({
  id: 'pref-1',
  parentId: 'parent-1',
  notificationType: 'message',
  channel: 'email',
  isEnabled: true,
  frequency: 'immediate',
  quietHoursStart: undefined,
  quietHoursEnd: undefined,
  createdAt: '2024-01-15T09:00:00Z',
  updatedAt: '2024-01-15T09:00:00Z',
  ...overrides,
})

// ============================================================================
// Thread Operations Tests
// ============================================================================

describe('Thread Operations', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('getThreads', () => {
    it('fetches threads without parameters', async () => {
      const mockResponse: ThreadListResponse = {
        threads: [createMockThread()],
        total: 1,
        skip: 0,
        limit: 20,
      }
      ;(aiServiceClient.get as Mock).mockResolvedValue(mockResponse)

      const result = await getThreads()

      expect(aiServiceClient.get).toHaveBeenCalledWith('/api/v1/messaging/threads', {
        params: undefined,
      })
      expect(result).toEqual(mockResponse)
    })

    it('fetches threads with pagination parameters', async () => {
      const mockResponse: ThreadListResponse = {
        threads: [createMockThread()],
        total: 50,
        skip: 10,
        limit: 10,
      }
      ;(aiServiceClient.get as Mock).mockResolvedValue(mockResponse)

      const params: ListThreadsParams = { skip: 10, limit: 10 }
      const result = await getThreads(params)

      expect(aiServiceClient.get).toHaveBeenCalledWith('/api/v1/messaging/threads', {
        params: { skip: 10, limit: 10 },
      })
      expect(result).toEqual(mockResponse)
    })

    it('fetches threads with filter parameters', async () => {
      const mockResponse: ThreadListResponse = {
        threads: [createMockThread({ threadType: 'urgent' })],
        total: 5,
        skip: 0,
        limit: 20,
      }
      ;(aiServiceClient.get as Mock).mockResolvedValue(mockResponse)

      const params: ListThreadsParams = {
        threadType: 'urgent',
        childId: 'child-1',
        isActive: true,
      }
      const result = await getThreads(params)

      expect(aiServiceClient.get).toHaveBeenCalledWith('/api/v1/messaging/threads', {
        params: { threadType: 'urgent', childId: 'child-1', isActive: true },
      })
      expect(result).toEqual(mockResponse)
    })
  })

  describe('getThread', () => {
    it('fetches a thread by ID without messages', async () => {
      const mockThread = createMockThread()
      ;(aiServiceClient.get as Mock).mockResolvedValue(mockThread)

      const result = await getThread('thread-1')

      expect(aiServiceClient.get).toHaveBeenCalledWith('/api/v1/messaging/threads/thread-1', {
        params: { include_messages: false },
      })
      expect(result).toEqual(mockThread)
    })

    it('fetches a thread by ID with messages', async () => {
      const mockThread: ThreadWithMessages = {
        ...createMockThread(),
        messages: [createMockMessage()],
      }
      ;(aiServiceClient.get as Mock).mockResolvedValue(mockThread)

      const result = await getThread('thread-1', true)

      expect(aiServiceClient.get).toHaveBeenCalledWith('/api/v1/messaging/threads/thread-1', {
        params: { include_messages: true },
      })
      expect(result).toEqual(mockThread)
    })
  })

  describe('createThread', () => {
    it('creates a new thread', async () => {
      const mockThread = createMockThread()
      ;(aiServiceClient.post as Mock).mockResolvedValue(mockThread)

      const request: CreateThreadRequest = {
        subject: 'Test Thread',
        threadType: 'daily_log',
        childId: 'child-1',
        participants: [{ userId: 'educator-1', userType: 'educator' }],
      }
      const result = await createThread(request)

      expect(aiServiceClient.post).toHaveBeenCalledWith('/api/v1/messaging/threads', request)
      expect(result).toEqual(mockThread)
    })

    it('creates a thread with initial message', async () => {
      const mockThread = createMockThread()
      ;(aiServiceClient.post as Mock).mockResolvedValue(mockThread)

      const request: CreateThreadRequest = {
        subject: 'Test Thread',
        participants: [{ userId: 'educator-1', userType: 'educator' }],
        initialMessage: 'Hello, I wanted to discuss...',
      }
      const result = await createThread(request)

      expect(aiServiceClient.post).toHaveBeenCalledWith('/api/v1/messaging/threads', request)
      expect(result).toEqual(mockThread)
    })
  })

  describe('updateThread', () => {
    it('updates a thread subject', async () => {
      const mockThread = createMockThread({ subject: 'Updated Subject' })
      ;(aiServiceClient.patch as Mock).mockResolvedValue(mockThread)

      const request: UpdateThreadRequest = { subject: 'Updated Subject' }
      const result = await updateThread('thread-1', request)

      expect(aiServiceClient.patch).toHaveBeenCalledWith(
        '/api/v1/messaging/threads/thread-1',
        request
      )
      expect(result).toEqual(mockThread)
    })

    it('updates thread active status', async () => {
      const mockThread = createMockThread({ isActive: false })
      ;(aiServiceClient.patch as Mock).mockResolvedValue(mockThread)

      const request: UpdateThreadRequest = { isActive: false }
      const result = await updateThread('thread-1', request)

      expect(aiServiceClient.patch).toHaveBeenCalledWith(
        '/api/v1/messaging/threads/thread-1',
        request
      )
      expect(result).toEqual(mockThread)
    })
  })

  describe('archiveThread', () => {
    it('archives a thread', async () => {
      ;(aiServiceClient.delete as Mock).mockResolvedValue(undefined)

      await archiveThread('thread-1')

      expect(aiServiceClient.delete).toHaveBeenCalledWith('/api/v1/messaging/threads/thread-1')
    })
  })
})

// ============================================================================
// Message Operations Tests
// ============================================================================

describe('Message Operations', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('getMessages', () => {
    it('fetches messages for a thread', async () => {
      const mockResponse: MessageListResponse = {
        messages: [createMockMessage()],
        total: 1,
        skip: 0,
        limit: 50,
      }
      ;(aiServiceClient.get as Mock).mockResolvedValue(mockResponse)

      const result = await getMessages('thread-1')

      expect(aiServiceClient.get).toHaveBeenCalledWith(
        '/api/v1/messaging/threads/thread-1/messages',
        { params: undefined }
      )
      expect(result).toEqual(mockResponse)
    })

    it('fetches messages with pagination', async () => {
      const mockResponse: MessageListResponse = {
        messages: [createMockMessage()],
        total: 100,
        skip: 20,
        limit: 20,
      }
      ;(aiServiceClient.get as Mock).mockResolvedValue(mockResponse)

      const params: ListMessagesParams = { skip: 20, limit: 20 }
      const result = await getMessages('thread-1', params)

      expect(aiServiceClient.get).toHaveBeenCalledWith(
        '/api/v1/messaging/threads/thread-1/messages',
        { params: { skip: 20, limit: 20 } }
      )
      expect(result).toEqual(mockResponse)
    })
  })

  describe('sendMessage', () => {
    it('sends a text message', async () => {
      const mockMessage = createMockMessage()
      ;(aiServiceClient.post as Mock).mockResolvedValue(mockMessage)

      const request: SendMessageRequest = {
        content: 'Hello',
        contentType: 'text',
      }
      const result = await sendMessage('thread-1', request)

      expect(aiServiceClient.post).toHaveBeenCalledWith(
        '/api/v1/messaging/threads/thread-1/messages',
        request
      )
      expect(result).toEqual(mockMessage)
    })

    it('sends a message with attachments', async () => {
      const mockMessage = createMockMessage({
        attachments: [
          {
            id: 'attach-1',
            messageId: 'msg-1',
            fileUrl: 'https://example.com/file.pdf',
            fileType: 'application/pdf',
            fileName: 'document.pdf',
            fileSize: 1024,
          },
        ],
      })
      ;(aiServiceClient.post as Mock).mockResolvedValue(mockMessage)

      const request: SendMessageRequest = {
        content: 'Please see attached',
        contentType: 'text',
        attachments: [
          {
            fileUrl: 'https://example.com/file.pdf',
            fileType: 'application/pdf',
            fileName: 'document.pdf',
            fileSize: 1024,
          },
        ],
      }
      const result = await sendMessage('thread-1', request)

      expect(aiServiceClient.post).toHaveBeenCalledWith(
        '/api/v1/messaging/threads/thread-1/messages',
        request
      )
      expect(result.attachments).toHaveLength(1)
    })
  })

  describe('getMessage', () => {
    it('fetches a single message by ID', async () => {
      const mockMessage = createMockMessage()
      ;(aiServiceClient.get as Mock).mockResolvedValue(mockMessage)

      const result = await getMessage('msg-1')

      expect(aiServiceClient.get).toHaveBeenCalledWith('/api/v1/messaging/messages/msg-1')
      expect(result).toEqual(mockMessage)
    })
  })

  describe('markAsRead', () => {
    it('marks messages as read', async () => {
      ;(aiServiceClient.patch as Mock).mockResolvedValue(undefined)

      const request: MarkAsReadRequest = { messageIds: ['msg-1', 'msg-2', 'msg-3'] }
      await markAsRead(request)

      expect(aiServiceClient.patch).toHaveBeenCalledWith('/api/v1/messaging/messages/read', request)
    })
  })

  describe('markThreadAsRead', () => {
    it('marks all messages in a thread as read', async () => {
      ;(aiServiceClient.patch as Mock).mockResolvedValue(undefined)

      await markThreadAsRead('thread-1')

      expect(aiServiceClient.patch).toHaveBeenCalledWith('/api/v1/messaging/threads/thread-1/read')
    })
  })

  describe('getUnreadCount', () => {
    it('fetches unread message count', async () => {
      const mockResponse: UnreadCountResponse = {
        totalUnread: 5,
        threadsWithUnread: 2,
      }
      ;(aiServiceClient.get as Mock).mockResolvedValue(mockResponse)

      const result = await getUnreadCount()

      expect(aiServiceClient.get).toHaveBeenCalledWith('/api/v1/messaging/messages/unread-count')
      expect(result).toEqual(mockResponse)
    })
  })
})

// ============================================================================
// Notification Preference Operations Tests
// ============================================================================

describe('Notification Preference Operations', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('getNotificationPreferences', () => {
    it('fetches all notification preferences for a parent', async () => {
      const mockResponse: NotificationPreferenceListResponse = {
        parentId: 'parent-1',
        preferences: [
          createMockPreference({ notificationType: 'message', channel: 'email' }),
          createMockPreference({ id: 'pref-2', notificationType: 'message', channel: 'push' }),
          createMockPreference({ id: 'pref-3', notificationType: 'urgent', channel: 'sms' }),
        ],
      }
      ;(aiServiceClient.get as Mock).mockResolvedValue(mockResponse)

      const result = await getNotificationPreferences('parent-1')

      expect(aiServiceClient.get).toHaveBeenCalledWith(
        '/api/v1/messaging/notification-preferences',
        { params: { parent_id: 'parent-1' } }
      )
      expect(result.preferences).toHaveLength(3)
    })
  })

  describe('getNotificationPreference', () => {
    it('fetches a single notification preference by ID', async () => {
      const mockPreference = createMockPreference()
      ;(aiServiceClient.get as Mock).mockResolvedValue(mockPreference)

      const result = await getNotificationPreference('pref-1')

      expect(aiServiceClient.get).toHaveBeenCalledWith(
        '/api/v1/messaging/notification-preferences/pref-1'
      )
      expect(result).toEqual(mockPreference)
    })
  })

  describe('createNotificationPreference', () => {
    it('creates a new notification preference', async () => {
      const mockPreference = createMockPreference()
      ;(aiServiceClient.post as Mock).mockResolvedValue(mockPreference)

      const request: NotificationPreferenceRequest = {
        parentId: 'parent-1',
        notificationType: 'message',
        channel: 'email',
        isEnabled: true,
        frequency: 'immediate',
      }
      const result = await createNotificationPreference(request)

      expect(aiServiceClient.post).toHaveBeenCalledWith(
        '/api/v1/messaging/notification-preferences',
        request
      )
      expect(result).toEqual(mockPreference)
    })

    it('creates a preference with quiet hours', async () => {
      const mockPreference = createMockPreference({
        quietHoursStart: '22:00',
        quietHoursEnd: '07:00',
      })
      ;(aiServiceClient.post as Mock).mockResolvedValue(mockPreference)

      const request: NotificationPreferenceRequest = {
        parentId: 'parent-1',
        notificationType: 'message',
        channel: 'push',
        isEnabled: true,
        frequency: 'immediate',
        quietHoursStart: '22:00',
        quietHoursEnd: '07:00',
      }
      const result = await createNotificationPreference(request)

      expect(aiServiceClient.post).toHaveBeenCalledWith(
        '/api/v1/messaging/notification-preferences',
        request
      )
      expect(result.quietHoursStart).toBe('22:00')
      expect(result.quietHoursEnd).toBe('07:00')
    })
  })

  describe('updateNotificationPreference', () => {
    it('updates notification preference enabled state', async () => {
      const mockPreference = createMockPreference({ isEnabled: false })
      ;(aiServiceClient.patch as Mock).mockResolvedValue(mockPreference)

      const request: Partial<NotificationPreferenceRequest> = { isEnabled: false }
      const result = await updateNotificationPreference('pref-1', request)

      expect(aiServiceClient.patch).toHaveBeenCalledWith(
        '/api/v1/messaging/notification-preferences/pref-1',
        request
      )
      expect(result.isEnabled).toBe(false)
    })

    it('updates notification frequency', async () => {
      const mockPreference = createMockPreference({ frequency: 'daily' })
      ;(aiServiceClient.patch as Mock).mockResolvedValue(mockPreference)

      const request: Partial<NotificationPreferenceRequest> = { frequency: 'daily' }
      const result = await updateNotificationPreference('pref-1', request)

      expect(aiServiceClient.patch).toHaveBeenCalledWith(
        '/api/v1/messaging/notification-preferences/pref-1',
        request
      )
      expect(result.frequency).toBe('daily')
    })
  })

  describe('deleteNotificationPreference', () => {
    it('deletes a notification preference', async () => {
      ;(aiServiceClient.delete as Mock).mockResolvedValue(undefined)

      await deleteNotificationPreference('pref-1')

      expect(aiServiceClient.delete).toHaveBeenCalledWith(
        '/api/v1/messaging/notification-preferences/pref-1'
      )
    })
  })

  describe('createDefaultPreferences', () => {
    it('creates default notification preferences for a parent', async () => {
      const mockResponse: NotificationPreferenceListResponse = {
        parentId: 'parent-1',
        preferences: [
          createMockPreference({ notificationType: 'message', channel: 'email' }),
          createMockPreference({ id: 'pref-2', notificationType: 'message', channel: 'push' }),
          createMockPreference({ id: 'pref-3', notificationType: 'urgent', channel: 'email' }),
          createMockPreference({ id: 'pref-4', notificationType: 'urgent', channel: 'push' }),
        ],
      }
      ;(aiServiceClient.post as Mock).mockResolvedValue(mockResponse)

      const result = await createDefaultPreferences('parent-1')

      expect(aiServiceClient.post).toHaveBeenCalledWith(
        '/api/v1/messaging/notification-preferences/defaults',
        { parent_id: 'parent-1' }
      )
      expect(result.preferences).toHaveLength(4)
    })
  })

  describe('setQuietHours', () => {
    it('sets quiet hours for all preferences', async () => {
      ;(aiServiceClient.patch as Mock).mockResolvedValue(undefined)

      const params: SetQuietHoursParams = {
        parentId: 'parent-1',
        quietHoursStart: '22:00',
        quietHoursEnd: '07:00',
      }
      await setQuietHours(params)

      expect(aiServiceClient.patch).toHaveBeenCalledWith(
        '/api/v1/messaging/notification-preferences/quiet-hours',
        {
          parent_id: 'parent-1',
          quiet_hours_start: '22:00',
          quiet_hours_end: '07:00',
        }
      )
    })
  })
})

// ============================================================================
// Convenience Functions Tests
// ============================================================================

describe('Convenience Functions', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  describe('getThreadWithMessages', () => {
    it('fetches a thread with all messages', async () => {
      const mockThread: ThreadWithMessages = {
        ...createMockThread(),
        messages: [
          createMockMessage({ id: 'msg-1', content: 'First message' }),
          createMockMessage({ id: 'msg-2', content: 'Second message' }),
        ],
      }
      ;(aiServiceClient.get as Mock).mockResolvedValue(mockThread)

      const result = await getThreadWithMessages('thread-1')

      expect(aiServiceClient.get).toHaveBeenCalledWith('/api/v1/messaging/threads/thread-1', {
        params: { include_messages: true },
      })
      expect(result.messages).toHaveLength(2)
    })
  })

  describe('sendTextMessage', () => {
    it('sends a plain text message', async () => {
      const mockMessage = createMockMessage({ content: 'Hello there!' })
      ;(aiServiceClient.post as Mock).mockResolvedValue(mockMessage)

      const result = await sendTextMessage('thread-1', 'Hello there!')

      expect(aiServiceClient.post).toHaveBeenCalledWith(
        '/api/v1/messaging/threads/thread-1/messages',
        {
          content: 'Hello there!',
          contentType: 'text',
        }
      )
      expect(result).toEqual(mockMessage)
    })
  })

  describe('createDirectThread', () => {
    it('creates a direct thread with an educator', async () => {
      const mockThread = createMockThread({
        subject: 'Question about today',
        threadType: 'daily_log',
      })
      ;(aiServiceClient.post as Mock).mockResolvedValue(mockThread)

      const result = await createDirectThread(
        'educator-1',
        'educator',
        'Question about today',
        undefined,
        'child-1'
      )

      expect(aiServiceClient.post).toHaveBeenCalledWith('/api/v1/messaging/threads', {
        subject: 'Question about today',
        threadType: 'daily_log',
        childId: 'child-1',
        participants: [{ userId: 'educator-1', userType: 'educator' }],
        initialMessage: undefined,
      })
      expect(result).toEqual(mockThread)
    })

    it('creates a direct thread with initial message', async () => {
      const mockThread = createMockThread()
      ;(aiServiceClient.post as Mock).mockResolvedValue(mockThread)

      const result = await createDirectThread(
        'director-1',
        'director',
        'Schedule Discussion',
        'Hello, I wanted to ask about the schedule...'
      )

      expect(aiServiceClient.post).toHaveBeenCalledWith('/api/v1/messaging/threads', {
        subject: 'Schedule Discussion',
        threadType: 'daily_log',
        childId: undefined,
        participants: [{ userId: 'director-1', userType: 'director' }],
        initialMessage: 'Hello, I wanted to ask about the schedule...',
      })
      expect(result).toEqual(mockThread)
    })

    it('creates a direct thread with admin', async () => {
      const mockThread = createMockThread()
      ;(aiServiceClient.post as Mock).mockResolvedValue(mockThread)

      const result = await createDirectThread('admin-1', 'admin', 'Billing Question')

      expect(aiServiceClient.post).toHaveBeenCalledWith('/api/v1/messaging/threads', {
        subject: 'Billing Question',
        threadType: 'daily_log',
        childId: undefined,
        participants: [{ userId: 'admin-1', userType: 'admin' }],
        initialMessage: undefined,
      })
      expect(result).toEqual(mockThread)
    })
  })
})

// ============================================================================
// Error Handling Tests
// ============================================================================

describe('Error Handling', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('propagates errors from API client', async () => {
    const error = new Error('Network error')
    ;(aiServiceClient.get as Mock).mockRejectedValue(error)

    await expect(getThreads()).rejects.toThrow('Network error')
  })

  it('propagates errors when creating thread', async () => {
    const error = new Error('Validation failed')
    ;(aiServiceClient.post as Mock).mockRejectedValue(error)

    const request: CreateThreadRequest = {
      subject: '',
      participants: [],
    }

    await expect(createThread(request)).rejects.toThrow('Validation failed')
  })

  it('propagates errors when sending message', async () => {
    const error = new Error('Thread not found')
    ;(aiServiceClient.post as Mock).mockRejectedValue(error)

    await expect(sendMessage('invalid-thread', { content: 'Hello' })).rejects.toThrow(
      'Thread not found'
    )
  })

  it('propagates errors when marking as read', async () => {
    const error = new Error('Unauthorized')
    ;(aiServiceClient.patch as Mock).mockRejectedValue(error)

    await expect(markAsRead({ messageIds: ['msg-1'] })).rejects.toThrow('Unauthorized')
  })

  it('propagates errors when deleting preference', async () => {
    const error = new Error('Preference not found')
    ;(aiServiceClient.delete as Mock).mockRejectedValue(error)

    await expect(deleteNotificationPreference('invalid-pref')).rejects.toThrow(
      'Preference not found'
    )
  })
})

// ============================================================================
// API Path Tests
// ============================================================================

describe('API Path Construction', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    ;(aiServiceClient.get as Mock).mockResolvedValue({})
    ;(aiServiceClient.post as Mock).mockResolvedValue({})
    ;(aiServiceClient.patch as Mock).mockResolvedValue({})
    ;(aiServiceClient.delete as Mock).mockResolvedValue({})
  })

  it('uses correct base path for all endpoints', async () => {
    await getThreads()
    expect(aiServiceClient.get).toHaveBeenCalledWith(
      expect.stringContaining('/api/v1/messaging/'),
      expect.anything()
    )
  })

  it('constructs correct thread path with ID', async () => {
    await getThread('test-thread-id')
    expect(aiServiceClient.get).toHaveBeenCalledWith(
      '/api/v1/messaging/threads/test-thread-id',
      expect.anything()
    )
  })

  it('constructs correct messages path with thread ID', async () => {
    await getMessages('test-thread-id')
    expect(aiServiceClient.get).toHaveBeenCalledWith(
      '/api/v1/messaging/threads/test-thread-id/messages',
      expect.anything()
    )
  })

  it('constructs correct single message path', async () => {
    await getMessage('test-message-id')
    expect(aiServiceClient.get).toHaveBeenCalledWith('/api/v1/messaging/messages/test-message-id')
  })

  it('constructs correct thread read path', async () => {
    await markThreadAsRead('test-thread-id')
    expect(aiServiceClient.patch).toHaveBeenCalledWith(
      '/api/v1/messaging/threads/test-thread-id/read'
    )
  })

  it('constructs correct notification preference path with ID', async () => {
    await getNotificationPreference('test-pref-id')
    expect(aiServiceClient.get).toHaveBeenCalledWith(
      '/api/v1/messaging/notification-preferences/test-pref-id'
    )
  })
})
