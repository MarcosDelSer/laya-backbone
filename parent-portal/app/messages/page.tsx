'use client';

import { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { MessageThread, ThreadPreview } from '@/components/MessageThread';
import { MessageComposer } from '@/components/MessageComposer';
import {
  getThreads,
  getMessages,
  sendTextMessage,
  markThreadAsRead,
} from '@/lib/messaging-client';
import { ApiError } from '@/lib/api';
import type {
  MessageThread as MessageThreadType,
  Message,
} from '@/lib/types';

// ============================================================================
// Type Adapters
// ============================================================================

/**
 * Convert API Message to component-expected format.
 * Maps API field names to the format expected by UI components.
 */
interface UIMessage {
  id: string;
  threadId: string;
  senderId: string;
  senderName: string;
  content: string;
  timestamp: string;
  read: boolean;
}

/**
 * Convert API thread to format expected by ThreadPreview component.
 */
interface UIThread {
  id: string;
  subject: string;
  participants: string[];
  lastMessage: UIMessage;
  unreadCount: number;
}

/**
 * Convert API Message to UI Message format.
 */
function toUIMessage(message: Message): UIMessage {
  return {
    id: message.id,
    threadId: message.threadId,
    senderId: message.senderId,
    senderName: message.senderName || 'Unknown',
    content: message.content,
    timestamp: message.createdAt || new Date().toISOString(),
    read: message.isRead,
  };
}

/**
 * Convert API Thread to UI Thread format.
 */
function toUIThread(thread: MessageThreadType, lastMessage?: UIMessage): UIThread {
  // Extract display names from participants
  const participantNames = thread.participants.map(
    (p) => p.displayName || `User ${p.userId.slice(0, 8)}`
  );

  // Create a placeholder last message if none exists
  const defaultLastMessage: UIMessage = lastMessage || {
    id: '',
    threadId: thread.id,
    senderId: thread.createdBy,
    senderName: participantNames[0] || 'Unknown',
    content: thread.lastMessage || 'No messages yet',
    timestamp: thread.lastMessageAt || thread.createdAt || new Date().toISOString(),
    read: true,
  };

  return {
    id: thread.id,
    subject: thread.subject,
    participants: participantNames,
    lastMessage: defaultLastMessage,
    unreadCount: thread.unreadCount,
  };
}

// ============================================================================
// Constants
// ============================================================================

/**
 * Current user ID - in a real app this would come from auth context.
 * TODO: Replace with actual auth context.
 */
const CURRENT_USER_ID = 'parent-1';

// ============================================================================
// Component
// ============================================================================

export default function MessagesPage() {
  // State for threads and messages
  const [threads, setThreads] = useState<UIThread[]>([]);
  const [messages, setMessages] = useState<Record<string, UIMessage[]>>({});
  const [selectedThreadId, setSelectedThreadId] = useState<string | null>(null);
  const [showMobileThread, setShowMobileThread] = useState(false);

  // Loading and error states
  const [isLoadingThreads, setIsLoadingThreads] = useState(true);
  const [isLoadingMessages, setIsLoadingMessages] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  // Derived state
  const selectedThread = threads.find((t) => t.id === selectedThreadId);
  const selectedMessages = selectedThreadId ? messages[selectedThreadId] || [] : [];
  const totalUnread = threads.reduce((sum, t) => sum + t.unreadCount, 0);

  // ============================================================================
  // Data Fetching
  // ============================================================================

  /**
   * Fetch all threads for the current user.
   */
  const fetchThreads = useCallback(async () => {
    try {
      setIsLoadingThreads(true);
      setError(null);

      const response = await getThreads({ isActive: true });
      const uiThreads = response.threads.map((thread) => toUIThread(thread));
      setThreads(uiThreads);

      // Auto-select first thread if none selected
      if (uiThreads.length > 0 && !selectedThreadId) {
        setSelectedThreadId(uiThreads[0].id);
      }
    } catch (err) {
      const apiError = err instanceof ApiError ? err : null;
      const errorMessage = apiError?.userMessage || 'Failed to load conversations';
      setError(errorMessage);
    } finally {
      setIsLoadingThreads(false);
    }
  }, [selectedThreadId]);

  /**
   * Fetch messages for a specific thread.
   */
  const fetchMessages = useCallback(async (threadId: string) => {
    try {
      setIsLoadingMessages(true);

      const response = await getMessages(threadId);
      const uiMessages = response.messages.map(toUIMessage);

      setMessages((prev) => ({
        ...prev,
        [threadId]: uiMessages,
      }));

      // Update thread's last message if we have messages
      if (uiMessages.length > 0) {
        const lastMsg = uiMessages[uiMessages.length - 1];
        setThreads((prev) =>
          prev.map((t) =>
            t.id === threadId ? { ...t, lastMessage: lastMsg } : t
          )
        );
      }
    } catch (err) {
      const apiError = err instanceof ApiError ? err : null;
      const errorMessage = apiError?.userMessage || 'Failed to load messages';
      setError(errorMessage);
    } finally {
      setIsLoadingMessages(false);
    }
  }, []);

  // Initial data load
  useEffect(() => {
    fetchThreads();
  }, [fetchThreads]);

  // Load messages when thread is selected
  useEffect(() => {
    if (selectedThreadId && !messages[selectedThreadId]) {
      fetchMessages(selectedThreadId);
    }
  }, [selectedThreadId, messages, fetchMessages]);

  // ============================================================================
  // Event Handlers
  // ============================================================================

  /**
   * Handle selecting a thread from the sidebar.
   */
  const handleSelectThread = async (threadId: string) => {
    setSelectedThreadId(threadId);
    setShowMobileThread(true);

    // Mark thread as read via API
    try {
      await markThreadAsRead(threadId);

      // Update local state to reflect read status
      setThreads((prev) =>
        prev.map((t) =>
          t.id === threadId ? { ...t, unreadCount: 0 } : t
        )
      );

      // Mark local messages as read
      setMessages((prev) => ({
        ...prev,
        [threadId]: prev[threadId]?.map((m) => ({ ...m, read: true })) || [],
      }));
    } catch (err) {
      // Non-critical error, just log it
      // Error silently handled - marking read is not critical
    }
  };

  /**
   * Handle sending a new message.
   */
  const handleSendMessage = async (content: string) => {
    if (!selectedThreadId || isSending) return;

    try {
      setIsSending(true);

      // Send message via API
      const newMessage = await sendTextMessage(selectedThreadId, content);
      const uiMessage = toUIMessage(newMessage);

      // Add message to local state
      setMessages((prev) => ({
        ...prev,
        [selectedThreadId]: [...(prev[selectedThreadId] || []), uiMessage],
      }));

      // Update thread's last message
      setThreads((prev) =>
        prev.map((t) =>
          t.id === selectedThreadId
            ? { ...t, lastMessage: uiMessage }
            : t
        )
      );
    } catch (err) {
      const apiError = err instanceof ApiError ? err : null;
      const errorMessage = apiError?.userMessage || 'Failed to send message';
      setError(errorMessage);
    } finally {
      setIsSending(false);
    }
  };

  /**
   * Handle going back to thread list on mobile.
   */
  const handleBackToList = () => {
    setShowMobileThread(false);
  };

  /**
   * Dismiss error message.
   */
  const handleDismissError = () => {
    setError(null);
  };

  // ============================================================================
  // Render
  // ============================================================================

  return (
    <div className="h-[calc(100vh-4rem)] flex flex-col">
      {/* Error Banner */}
      {error && (
        <div className="bg-red-50 border-b border-red-200 px-4 py-3">
          <div className="mx-auto max-w-7xl flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <svg
                className="h-5 w-5 text-red-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
              >
                <path
                  strokeLinecap="round"
                  strokeLinejoin="round"
                  strokeWidth={2}
                  d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                />
              </svg>
              <span className="text-sm text-red-700">{error}</span>
            </div>
            <button
              type="button"
              onClick={handleDismissError}
              className="text-red-400 hover:text-red-600"
            >
              <svg className="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>
      )}

      {/* Header */}
      <div className="border-b border-gray-200 bg-white px-4 py-4">
        <div className="mx-auto max-w-7xl">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <Link href="/" className="btn btn-outline btn-sm md:hidden">
                <svg
                  className="h-4 w-4"
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
              </Link>
              <div>
                <h1 className="text-xl font-bold text-gray-900">Messages</h1>
                <p className="text-sm text-gray-600">
                  {isLoadingThreads
                    ? 'Loading...'
                    : totalUnread > 0
                    ? `${totalUnread} unread message${totalUnread !== 1 ? 's' : ''}`
                    : 'All caught up'}
                </p>
              </div>
            </div>
            <button
              type="button"
              className="btn btn-primary"
              disabled
              title="Coming soon"
            >
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
                  d="M12 4v16m8-8H4"
                />
              </svg>
              New Message
            </button>
          </div>
        </div>
      </div>

      {/* Main content area */}
      <div className="flex flex-1 overflow-hidden">
        {/* Thread list (sidebar) */}
        <div
          className={`${
            showMobileThread ? 'hidden md:block' : 'block'
          } w-full md:w-80 lg:w-96 border-r border-gray-200 bg-white overflow-y-auto`}
        >
          {isLoadingThreads ? (
            <div className="flex h-full items-center justify-center p-8">
              <div className="text-center">
                <div className="mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-primary" />
                <p className="text-sm text-gray-500">Loading conversations...</p>
              </div>
            </div>
          ) : threads.length > 0 ? (
            <div>
              {threads.map((thread) => (
                <ThreadPreview
                  key={thread.id}
                  thread={thread}
                  isSelected={thread.id === selectedThreadId}
                  onClick={() => handleSelectThread(thread.id)}
                />
              ))}
            </div>
          ) : (
            <div className="flex h-full items-center justify-center p-8">
              <div className="text-center">
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
                      d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"
                    />
                  </svg>
                </div>
                <h3 className="text-lg font-medium text-gray-900">
                  No messages yet
                </h3>
                <p className="mt-2 text-sm text-gray-500">
                  Start a conversation with your child&apos;s teacher.
                </p>
              </div>
            </div>
          )}
        </div>

        {/* Message thread (main area) */}
        <div
          className={`${
            showMobileThread ? 'block' : 'hidden md:block'
          } flex-1 flex flex-col bg-gray-50`}
        >
          {selectedThread ? (
            <>
              {/* Thread header */}
              <div className="border-b border-gray-200 bg-white px-4 py-3">
                <div className="flex items-center space-x-3">
                  <button
                    type="button"
                    onClick={handleBackToList}
                    className="md:hidden p-1 text-gray-500 hover:text-gray-700"
                  >
                    <svg
                      className="h-6 w-6"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M15 19l-7-7 7-7"
                      />
                    </svg>
                  </button>
                  <div className="flex-shrink-0">
                    <div className="h-10 w-10 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-semibold">
                      {selectedThread.lastMessage.senderName.charAt(0).toUpperCase()}
                    </div>
                  </div>
                  <div className="flex-1 min-w-0">
                    <h2 className="text-base font-semibold text-gray-900 truncate">
                      {selectedThread.subject}
                    </h2>
                    <p className="text-xs text-gray-500 truncate">
                      {selectedThread.participants.join(', ')}
                    </p>
                  </div>
                  <button
                    type="button"
                    className="p-2 text-gray-400 hover:text-gray-600"
                    title="More options"
                  >
                    <svg
                      className="h-5 w-5"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"
                      />
                    </svg>
                  </button>
                </div>
              </div>

              {/* Messages */}
              {isLoadingMessages ? (
                <div className="flex flex-1 items-center justify-center p-8">
                  <div className="text-center">
                    <div className="mx-auto mb-4 h-8 w-8 animate-spin rounded-full border-4 border-gray-200 border-t-primary" />
                    <p className="text-sm text-gray-500">Loading messages...</p>
                  </div>
                </div>
              ) : (
                <MessageThread
                  messages={selectedMessages}
                  currentUserId={CURRENT_USER_ID}
                />
              )}

              {/* Message composer */}
              <MessageComposer
                onSendMessage={handleSendMessage}
                disabled={isSending}
                placeholder="Type a message to the teacher..."
              />
            </>
          ) : (
            <div className="flex h-full items-center justify-center p-8">
              <div className="text-center">
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
                      d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"
                    />
                  </svg>
                </div>
                <h3 className="text-lg font-medium text-gray-900">
                  {isLoadingThreads ? 'Loading...' : 'Select a conversation'}
                </h3>
                <p className="mt-2 text-sm text-gray-500">
                  {isLoadingThreads
                    ? 'Please wait while we load your conversations.'
                    : 'Choose a conversation from the list to view messages.'}
                </p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
