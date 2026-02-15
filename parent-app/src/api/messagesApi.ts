/**
 * LAYA Parent App - Messages API
 *
 * API functions for messaging between parents and teachers.
 * Provides thread listing, message fetching, sending, and thread management
 * for parents to communicate with their children's educators.
 */

import {api} from './client';
import {API_CONFIG} from './config';
import type {
  ApiResponse,
  Message,
  MessageThread,
  SendMessageRequest,
  CreateThreadRequest,
} from '../types';

// ============================================================================
// Response Types
// ============================================================================

/**
 * Response type for message threads list endpoint
 */
export interface ThreadsListResponse {
  threads: MessageThread[];
  totalUnread: number;
}

/**
 * Response type for messages in a thread
 */
export interface ThreadMessagesResponse {
  messages: Message[];
  thread: MessageThread;
}

/**
 * Response type for sending a message
 */
export interface SendMessageResponse {
  message: Message;
  threadId: string;
}

/**
 * Response type for creating a new thread
 */
export interface CreateThreadResponse {
  thread: MessageThread;
  initialMessage: Message;
}

/**
 * Response type for marking a message as read
 */
export interface MarkReadResponse {
  success: boolean;
  messageId: string;
  readAt: string;
}

/**
 * Filter options for fetching message threads
 */
export interface ThreadsFilter {
  limit?: number;
  offset?: number;
  unreadOnly?: boolean;
}

/**
 * Filter options for fetching messages in a thread
 */
export interface MessagesFilter {
  limit?: number;
  offset?: number;
  before?: string;
  after?: string;
}

/**
 * Grouped messages by date
 */
export interface MessagesByDate {
  date: string;
  displayDate: string;
  messages: Message[];
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Get the current date in YYYY-MM-DD format
 */
function getCurrentDate(): string {
  const now = new Date();
  return now.toISOString().split('T')[0];
}

/**
 * Format timestamp for display (e.g., "2:30 PM")
 */
export function formatTimeForDisplay(timestamp: string): string {
  const date = new Date(timestamp);
  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

/**
 * Format date for display (e.g., "January 15, 2024")
 */
export function formatDateForDisplay(dateString: string): string {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
}

/**
 * Check if a timestamp is from today
 */
export function isToday(timestamp: string): boolean {
  const date = new Date(timestamp).toISOString().split('T')[0];
  return date === getCurrentDate();
}

/**
 * Check if a timestamp is from yesterday
 */
export function isYesterday(timestamp: string): boolean {
  const yesterday = new Date();
  yesterday.setDate(yesterday.getDate() - 1);
  const date = new Date(timestamp).toISOString().split('T')[0];
  return date === yesterday.toISOString().split('T')[0];
}

/**
 * Get relative date/time display for messages
 * - Today: Shows time (e.g., "2:30 PM")
 * - Yesterday: "Yesterday"
 * - Within week: Day name (e.g., "Monday")
 * - Older: Full date (e.g., "Jan 15")
 */
export function getRelativeTimeDisplay(timestamp: string): string {
  if (isToday(timestamp)) {
    return formatTimeForDisplay(timestamp);
  }
  if (isYesterday(timestamp)) {
    return 'Yesterday';
  }

  const date = new Date(timestamp);
  const now = new Date();
  const diffDays = Math.floor(
    (now.getTime() - date.getTime()) / (1000 * 60 * 60 * 24),
  );

  if (diffDays < 7) {
    return date.toLocaleDateString('en-US', {weekday: 'long'});
  }

  return date.toLocaleDateString('en-US', {month: 'short', day: 'numeric'});
}

/**
 * Get the date portion of a timestamp for grouping
 */
function getDateFromTimestamp(timestamp: string): string {
  return new Date(timestamp).toISOString().split('T')[0];
}

/**
 * Get relative date display for message grouping
 */
export function getRelativeDateDisplay(dateString: string): string {
  if (isToday(dateString + 'T00:00:00')) {
    return 'Today';
  }
  if (isYesterday(dateString + 'T00:00:00')) {
    return 'Yesterday';
  }
  return formatDateForDisplay(dateString);
}

/**
 * Truncate message content for preview
 */
export function truncateMessage(content: string, maxLength: number = 50): string {
  if (content.length <= maxLength) {
    return content;
  }
  return content.slice(0, maxLength - 3).trim() + '...';
}

// ============================================================================
// API Functions
// ============================================================================

/**
 * Fetch all message threads for the parent
 */
export async function fetchThreads(
  options?: ThreadsFilter,
): Promise<ApiResponse<ThreadsListResponse>> {
  const params: Record<string, string> = {};

  if (options?.limit !== undefined) {
    params.limit = String(options.limit);
  }
  if (options?.offset !== undefined) {
    params.offset = String(options.offset);
  }
  if (options?.unreadOnly) {
    params.unreadOnly = 'true';
  }

  return api.get<ThreadsListResponse>(
    API_CONFIG.endpoints.messages.threads,
    params,
  );
}

/**
 * Fetch messages for a specific thread
 */
export async function fetchThreadMessages(
  threadId: string,
  options?: MessagesFilter,
): Promise<ApiResponse<ThreadMessagesResponse>> {
  const params: Record<string, string> = {
    threadId,
  };

  if (options?.limit !== undefined) {
    params.limit = String(options.limit);
  }
  if (options?.offset !== undefined) {
    params.offset = String(options.offset);
  }
  if (options?.before) {
    params.before = options.before;
  }
  if (options?.after) {
    params.after = options.after;
  }

  return api.get<ThreadMessagesResponse>(
    API_CONFIG.endpoints.messages.threadMessages,
    params,
  );
}

/**
 * Send a message in an existing thread
 */
export async function sendMessage(
  request: SendMessageRequest,
): Promise<ApiResponse<SendMessageResponse>> {
  return api.post<SendMessageResponse>(
    API_CONFIG.endpoints.messages.send,
    request,
  );
}

/**
 * Create a new message thread
 */
export async function createThread(
  request: CreateThreadRequest,
): Promise<ApiResponse<CreateThreadResponse>> {
  return api.post<CreateThreadResponse>(
    API_CONFIG.endpoints.messages.createThread,
    request,
  );
}

/**
 * Mark a message as read
 */
export async function markMessageAsRead(
  messageId: string,
): Promise<ApiResponse<MarkReadResponse>> {
  return api.post<MarkReadResponse>(
    API_CONFIG.endpoints.messages.markRead,
    undefined,
    {id: messageId},
  );
}

/**
 * Mark all messages in a thread as read
 */
export async function markThreadAsRead(
  threadId: string,
): Promise<ApiResponse<{success: boolean; messagesUpdated: number}>> {
  return api.post<{success: boolean; messagesUpdated: number}>(
    `${API_CONFIG.endpoints.messages.threads}/${threadId}/read`,
    undefined,
  );
}

// ============================================================================
// Data Processing Functions
// ============================================================================

/**
 * Sort threads by last message timestamp (most recent first)
 */
export function sortThreadsByRecent(threads: MessageThread[]): MessageThread[] {
  return [...threads].sort((a, b) => {
    const timeA = new Date(a.lastMessage.timestamp).getTime();
    const timeB = new Date(b.lastMessage.timestamp).getTime();
    return timeB - timeA;
  });
}

/**
 * Filter threads with unread messages
 */
export function filterUnreadThreads(threads: MessageThread[]): MessageThread[] {
  return threads.filter(thread => thread.unreadCount > 0);
}

/**
 * Get total unread count across all threads
 */
export function getTotalUnreadCount(threads: MessageThread[]): number {
  return threads.reduce((total, thread) => total + thread.unreadCount, 0);
}

/**
 * Group messages by date for display
 */
export function groupMessagesByDate(messages: Message[]): MessagesByDate[] {
  const grouped = new Map<string, Message[]>();

  for (const message of messages) {
    const date = getDateFromTimestamp(message.timestamp);
    const existing = grouped.get(date) || [];
    existing.push(message);
    grouped.set(date, existing);
  }

  // Convert to array and sort by date (most recent first)
  const result: MessagesByDate[] = [];
  const sortedDates = Array.from(grouped.keys()).sort((a, b) => {
    return new Date(b).getTime() - new Date(a).getTime();
  });

  for (const date of sortedDates) {
    const dateMessages = grouped.get(date) || [];
    // Sort messages within the date by timestamp (oldest first for display)
    dateMessages.sort((a, b) => {
      return new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime();
    });
    result.push({
      date,
      displayDate: getRelativeDateDisplay(date),
      messages: dateMessages,
    });
  }

  return result;
}

/**
 * Sort messages by timestamp (oldest first for conversation view)
 */
export function sortMessagesByTimestamp(messages: Message[]): Message[] {
  return [...messages].sort((a, b) => {
    return new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime();
  });
}

/**
 * Get unread messages from a list
 */
export function getUnreadMessages(messages: Message[]): Message[] {
  return messages.filter(message => !message.read);
}

/**
 * Check if a message was sent by the current user
 */
export function isOwnMessage(message: Message, currentUserId: string): boolean {
  return message.senderId === currentUserId;
}

/**
 * Get the other participant's name from a thread
 * (Assumes two participants: current user and one other)
 */
export function getOtherParticipantName(
  thread: MessageThread,
  currentUserId: string,
): string {
  const otherParticipant = thread.participants.find(p => p !== currentUserId);
  return otherParticipant || 'Unknown';
}

/**
 * Search threads by subject or participant name
 */
export function searchThreads(
  threads: MessageThread[],
  query: string,
): MessageThread[] {
  const lowerQuery = query.toLowerCase();
  return threads.filter(thread => {
    const subjectMatch = thread.subject.toLowerCase().includes(lowerQuery);
    const participantMatch = thread.participants.some(p =>
      p.toLowerCase().includes(lowerQuery),
    );
    const lastMessageMatch = thread.lastMessage.content
      .toLowerCase()
      .includes(lowerQuery);
    return subjectMatch || participantMatch || lastMessageMatch;
  });
}

/**
 * Get preview text for a thread (last message content, truncated)
 */
export function getThreadPreview(thread: MessageThread): string {
  return truncateMessage(thread.lastMessage.content, 60);
}

/**
 * Check if thread has been updated since a given timestamp
 */
export function hasNewMessages(
  thread: MessageThread,
  sinceTimestamp: string,
): boolean {
  return new Date(thread.lastMessage.timestamp) > new Date(sinceTimestamp);
}
