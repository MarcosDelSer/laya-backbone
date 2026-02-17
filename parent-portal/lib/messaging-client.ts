/**
 * Messaging API client for LAYA Parent Portal.
 *
 * Provides type-safe methods for interacting with the messaging service API,
 * including thread management, message operations, and notification preferences.
 */

import { aiServiceClient } from './api';
import type {
  Message,
  MessageThread,
  ThreadWithMessages,
  ThreadListResponse,
  MessageListResponse,
  UnreadCountResponse,
  SendMessageRequest,
  CreateThreadRequest,
  UpdateThreadRequest,
  MarkAsReadRequest,
  NotificationPreference,
  NotificationPreferenceRequest,
  NotificationPreferenceListResponse,
  ThreadType,
} from './types';

// ============================================================================
// API Path Constants
// ============================================================================

/**
 * Base path for messaging API endpoints.
 */
const MESSAGING_BASE_PATH = '/api/v1/messaging';

/**
 * API paths for messaging endpoints.
 */
const API_PATHS = {
  threads: `${MESSAGING_BASE_PATH}/threads`,
  thread: (id: string) => `${MESSAGING_BASE_PATH}/threads/${id}`,
  messages: (threadId: string) => `${MESSAGING_BASE_PATH}/threads/${threadId}/messages`,
  message: (id: string) => `${MESSAGING_BASE_PATH}/messages/${id}`,
  markMessagesRead: `${MESSAGING_BASE_PATH}/messages/read`,
  markThreadRead: (threadId: string) => `${MESSAGING_BASE_PATH}/threads/${threadId}/read`,
  unreadCount: `${MESSAGING_BASE_PATH}/messages/unread-count`,
  notificationPreferences: `${MESSAGING_BASE_PATH}/notification-preferences`,
  notificationPreference: (id: string) => `${MESSAGING_BASE_PATH}/notification-preferences/${id}`,
  createDefaultPreferences: `${MESSAGING_BASE_PATH}/notification-preferences/defaults`,
  quietHours: `${MESSAGING_BASE_PATH}/notification-preferences/quiet-hours`,
} as const;

// ============================================================================
// Query Parameter Types
// ============================================================================

/**
 * Parameters for listing threads.
 */
export interface ListThreadsParams {
  /** Number of items to skip for pagination */
  skip?: number;
  /** Maximum number of items to return */
  limit?: number;
  /** Filter by thread type */
  threadType?: ThreadType;
  /** Filter by child ID */
  childId?: string;
  /** Filter by active status (default: true) */
  isActive?: boolean;
}

/**
 * Parameters for listing messages.
 */
export interface ListMessagesParams {
  /** Number of items to skip for pagination */
  skip?: number;
  /** Maximum number of items to return */
  limit?: number;
}

/**
 * Parameters for setting quiet hours.
 */
export interface SetQuietHoursParams {
  /** Parent ID for quiet hours */
  parentId: string;
  /** Start time in HH:MM format */
  quietHoursStart: string;
  /** End time in HH:MM format */
  quietHoursEnd: string;
}

// ============================================================================
// Thread Operations
// ============================================================================

/**
 * List message threads for the current user.
 *
 * @param params - Optional query parameters for filtering and pagination
 * @returns Paginated list of threads
 */
export async function getThreads(params?: ListThreadsParams): Promise<ThreadListResponse> {
  return aiServiceClient.get<ThreadListResponse>(API_PATHS.threads, {
    params: params as Record<string, string | number | boolean | undefined>,
  });
}

/**
 * Get a single thread by ID.
 *
 * @param threadId - ID of the thread to retrieve
 * @param includeMessages - Whether to include messages in the response
 * @returns Thread details, optionally with messages
 */
export async function getThread(
  threadId: string,
  includeMessages: boolean = false
): Promise<MessageThread | ThreadWithMessages> {
  return aiServiceClient.get<MessageThread | ThreadWithMessages>(API_PATHS.thread(threadId), {
    params: { include_messages: includeMessages },
  });
}

/**
 * Create a new message thread.
 *
 * @param request - Thread creation request with subject, participants, etc.
 * @returns Newly created thread
 */
export async function createThread(request: CreateThreadRequest): Promise<MessageThread> {
  return aiServiceClient.post<MessageThread>(API_PATHS.threads, request);
}

/**
 * Update an existing thread.
 *
 * @param threadId - ID of the thread to update
 * @param request - Update request with fields to modify
 * @returns Updated thread
 */
export async function updateThread(
  threadId: string,
  request: UpdateThreadRequest
): Promise<MessageThread> {
  return aiServiceClient.patch<MessageThread>(API_PATHS.thread(threadId), request);
}

/**
 * Archive (soft delete) a thread.
 *
 * @param threadId - ID of the thread to archive
 */
export async function archiveThread(threadId: string): Promise<void> {
  await aiServiceClient.delete<void>(API_PATHS.thread(threadId));
}

// ============================================================================
// Message Operations
// ============================================================================

/**
 * List messages in a thread.
 *
 * @param threadId - ID of the thread to get messages from
 * @param params - Optional pagination parameters
 * @returns Paginated list of messages
 */
export async function getMessages(
  threadId: string,
  params?: ListMessagesParams
): Promise<MessageListResponse> {
  return aiServiceClient.get<MessageListResponse>(API_PATHS.messages(threadId), {
    params: params as Record<string, string | number | boolean | undefined>,
  });
}

/**
 * Send a message to a thread.
 *
 * @param threadId - ID of the thread to send message to
 * @param request - Message content and optional attachments
 * @returns Newly created message
 */
export async function sendMessage(
  threadId: string,
  request: SendMessageRequest
): Promise<Message> {
  return aiServiceClient.post<Message>(API_PATHS.messages(threadId), request);
}

/**
 * Get a single message by ID.
 *
 * @param messageId - ID of the message to retrieve
 * @returns Message details
 */
export async function getMessage(messageId: string): Promise<Message> {
  return aiServiceClient.get<Message>(API_PATHS.message(messageId));
}

/**
 * Mark specific messages as read.
 *
 * @param request - Request containing message IDs to mark as read
 */
export async function markAsRead(request: MarkAsReadRequest): Promise<void> {
  await aiServiceClient.patch<void>(API_PATHS.markMessagesRead, request);
}

/**
 * Mark all messages in a thread as read.
 *
 * @param threadId - ID of the thread to mark as read
 */
export async function markThreadAsRead(threadId: string): Promise<void> {
  await aiServiceClient.patch<void>(API_PATHS.markThreadRead(threadId));
}

/**
 * Get unread message count for the current user.
 *
 * @returns Total unread count and number of threads with unread messages
 */
export async function getUnreadCount(): Promise<UnreadCountResponse> {
  return aiServiceClient.get<UnreadCountResponse>(API_PATHS.unreadCount);
}

// ============================================================================
// Notification Preference Operations
// ============================================================================

/**
 * Get all notification preferences for a parent.
 *
 * @param parentId - ID of the parent
 * @returns List of notification preferences
 */
export async function getNotificationPreferences(
  parentId: string
): Promise<NotificationPreferenceListResponse> {
  return aiServiceClient.get<NotificationPreferenceListResponse>(API_PATHS.notificationPreferences, {
    params: { parent_id: parentId },
  });
}

/**
 * Get a specific notification preference by ID.
 *
 * @param preferenceId - ID of the preference to retrieve
 * @returns Notification preference details
 */
export async function getNotificationPreference(
  preferenceId: string
): Promise<NotificationPreference> {
  return aiServiceClient.get<NotificationPreference>(
    API_PATHS.notificationPreference(preferenceId)
  );
}

/**
 * Create or update a notification preference.
 *
 * @param request - Preference creation/update request
 * @returns Created or updated preference
 */
export async function createNotificationPreference(
  request: NotificationPreferenceRequest
): Promise<NotificationPreference> {
  return aiServiceClient.post<NotificationPreference>(
    API_PATHS.notificationPreferences,
    request
  );
}

/**
 * Update an existing notification preference.
 *
 * @param preferenceId - ID of the preference to update
 * @param request - Update request with fields to modify
 * @returns Updated preference
 */
export async function updateNotificationPreference(
  preferenceId: string,
  request: Partial<NotificationPreferenceRequest>
): Promise<NotificationPreference> {
  return aiServiceClient.patch<NotificationPreference>(
    API_PATHS.notificationPreference(preferenceId),
    request
  );
}

/**
 * Delete a notification preference.
 *
 * @param preferenceId - ID of the preference to delete
 */
export async function deleteNotificationPreference(preferenceId: string): Promise<void> {
  await aiServiceClient.delete<void>(API_PATHS.notificationPreference(preferenceId));
}

/**
 * Create default notification preferences for a parent.
 *
 * This creates a full set of default preferences for all notification types
 * and channels if they don't already exist.
 *
 * @param parentId - ID of the parent to create defaults for
 * @returns List of created preferences
 */
export async function createDefaultPreferences(
  parentId: string
): Promise<NotificationPreferenceListResponse> {
  return aiServiceClient.post<NotificationPreferenceListResponse>(
    API_PATHS.createDefaultPreferences,
    { parent_id: parentId }
  );
}

/**
 * Set quiet hours for all notification preferences.
 *
 * Quiet hours prevent notifications from being sent during specified times.
 *
 * @param params - Parent ID and quiet hours time range
 */
export async function setQuietHours(params: SetQuietHoursParams): Promise<void> {
  await aiServiceClient.patch<void>(API_PATHS.quietHours, {
    parent_id: params.parentId,
    quiet_hours_start: params.quietHoursStart,
    quiet_hours_end: params.quietHoursEnd,
  });
}

// ============================================================================
// Convenience Functions
// ============================================================================

/**
 * Get a thread with all its messages.
 *
 * Convenience function that calls getThread with includeMessages=true.
 *
 * @param threadId - ID of the thread to retrieve
 * @returns Thread with messages
 */
export async function getThreadWithMessages(threadId: string): Promise<ThreadWithMessages> {
  return getThread(threadId, true) as Promise<ThreadWithMessages>;
}

/**
 * Send a text message to a thread.
 *
 * Convenience function for sending plain text messages without attachments.
 *
 * @param threadId - ID of the thread to send message to
 * @param content - Text content of the message
 * @returns Newly created message
 */
export async function sendTextMessage(threadId: string, content: string): Promise<Message> {
  return sendMessage(threadId, {
    content,
    contentType: 'text',
  });
}

/**
 * Create a direct message thread with a single recipient.
 *
 * Convenience function for creating a simple 1:1 conversation thread.
 *
 * @param recipientId - User ID of the recipient
 * @param recipientType - Type of the recipient (educator, director, etc.)
 * @param subject - Subject of the thread
 * @param initialMessage - Optional initial message content
 * @param childId - Optional child ID to associate with the thread
 * @returns Newly created thread
 */
export async function createDirectThread(
  recipientId: string,
  recipientType: 'educator' | 'director' | 'admin',
  subject: string,
  initialMessage?: string,
  childId?: string
): Promise<MessageThread> {
  return createThread({
    subject,
    threadType: 'daily_log',
    childId,
    participants: [
      {
        userId: recipientId,
        userType: recipientType,
      },
    ],
    initialMessage,
  });
}
