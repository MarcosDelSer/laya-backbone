'use client';

import { useState } from 'react';
import Link from 'next/link';
import { useTranslations, useLocale } from 'next-intl';
import { MessageThread, ThreadPreview } from '@/components/MessageThread';
import { MessageComposer } from '@/components/MessageComposer';

// Type definitions for messaging
interface Message {
  id: string;
  threadId: string;
  senderId: string;
  senderName: string;
  content: string;
  timestamp: string;
  read: boolean;
}

interface MessageThreadData {
  id: string;
  subject: string;
  participants: string[];
  lastMessage: Message;
  unreadCount: number;
}

// Mock data for conversation threads - will be replaced with API calls
const CURRENT_USER_ID = 'parent-1';

const mockMessages: Record<string, Message[]> = {
  'thread-1': [
    {
      id: 'msg-1',
      threadId: 'thread-1',
      senderId: 'teacher-1',
      senderName: 'Ms. Johnson',
      content: 'Hi! I wanted to let you know that Emma had a wonderful day today. She really enjoyed the art activity and made a beautiful painting.',
      timestamp: new Date(Date.now() - 3600000 * 2).toISOString(), // 2 hours ago
      read: true,
    },
    {
      id: 'msg-2',
      threadId: 'thread-1',
      senderId: CURRENT_USER_ID,
      senderName: 'You',
      content: 'That\'s great to hear! She\'s been talking about painting at home too. Thank you for sharing!',
      timestamp: new Date(Date.now() - 3600000).toISOString(), // 1 hour ago
      read: true,
    },
    {
      id: 'msg-3',
      threadId: 'thread-1',
      senderId: 'teacher-1',
      senderName: 'Ms. Johnson',
      content: 'You\'re welcome! We\'ll be doing more art projects this week. Also, just a reminder that picture day is next Tuesday.',
      timestamp: new Date(Date.now() - 1800000).toISOString(), // 30 min ago
      read: false,
    },
  ],
  'thread-2': [
    {
      id: 'msg-4',
      threadId: 'thread-2',
      senderId: 'admin-1',
      senderName: 'Sunshine Daycare Admin',
      content: 'Dear Parents, we wanted to inform you about the upcoming parent-teacher conference scheduled for February 28th. Please let us know your preferred time slot.',
      timestamp: new Date(Date.now() - 86400000).toISOString(), // Yesterday
      read: true,
    },
    {
      id: 'msg-5',
      threadId: 'thread-2',
      senderId: CURRENT_USER_ID,
      senderName: 'You',
      content: 'Thank you for the information. Can we schedule for the afternoon, around 2 PM?',
      timestamp: new Date(Date.now() - 43200000).toISOString(), // 12 hours ago
      read: true,
    },
    {
      id: 'msg-6',
      threadId: 'thread-2',
      senderId: 'admin-1',
      senderName: 'Sunshine Daycare Admin',
      content: 'The 2 PM slot is available. I\'ve scheduled you for February 28th at 2:00 PM. You\'ll receive a calendar invite shortly.',
      timestamp: new Date(Date.now() - 36000000).toISOString(), // 10 hours ago
      read: true,
    },
  ],
  'thread-3': [
    {
      id: 'msg-7',
      threadId: 'thread-3',
      senderId: 'teacher-2',
      senderName: 'Mr. Davis',
      content: 'Hello! I\'m Emma\'s music teacher. I wanted to share that Emma has been showing great interest in learning the xylophone.',
      timestamp: new Date(Date.now() - 172800000).toISOString(), // 2 days ago
      read: true,
    },
    {
      id: 'msg-8',
      threadId: 'thread-3',
      senderId: CURRENT_USER_ID,
      senderName: 'You',
      content: 'That\'s wonderful! She does love music. Is there anything we can do at home to encourage this?',
      timestamp: new Date(Date.now() - 86400000).toISOString(), // Yesterday
      read: true,
    },
  ],
};

const mockThreads: MessageThreadData[] = [
  {
    id: 'thread-1',
    subject: 'Daily Update - Emma',
    participants: ['Ms. Johnson', 'You'],
    lastMessage: mockMessages['thread-1'][mockMessages['thread-1'].length - 1],
    unreadCount: 1,
  },
  {
    id: 'thread-2',
    subject: 'Parent-Teacher Conference',
    participants: ['Sunshine Daycare Admin', 'You'],
    lastMessage: mockMessages['thread-2'][mockMessages['thread-2'].length - 1],
    unreadCount: 0,
  },
  {
    id: 'thread-3',
    subject: 'Music Class Progress',
    participants: ['Mr. Davis', 'You'],
    lastMessage: mockMessages['thread-3'][mockMessages['thread-3'].length - 1],
    unreadCount: 0,
  },
];

/**
 * Messages page with internationalization support.
 *
 * Displays a messaging interface for parent-teacher communication.
 * All text is translated using next-intl.
 */
export default function MessagesPage() {
  const t = useTranslations();
  const locale = useLocale();

  const [threads, setThreads] = useState<MessageThreadData[]>(mockThreads);
  const [messages, setMessages] = useState<Record<string, Message[]>>(mockMessages);
  const [selectedThreadId, setSelectedThreadId] = useState<string | null>(
    mockThreads[0]?.id || null
  );
  const [showMobileThread, setShowMobileThread] = useState(false);

  const selectedThread = threads.find((t) => t.id === selectedThreadId);
  const selectedMessages = selectedThreadId ? messages[selectedThreadId] || [] : [];

  // Calculate total unread count
  const totalUnread = threads.reduce((sum, t) => sum + t.unreadCount, 0);

  const handleSelectThread = (threadId: string) => {
    setSelectedThreadId(threadId);
    setShowMobileThread(true);

    // Mark thread as read
    setThreads((prev) =>
      prev.map((t) =>
        t.id === threadId ? { ...t, unreadCount: 0 } : t
      )
    );

    // Mark messages as read
    setMessages((prev) => ({
      ...prev,
      [threadId]: prev[threadId]?.map((m) => ({ ...m, read: true })) || [],
    }));
  };

  const handleSendMessage = (content: string) => {
    if (!selectedThreadId) return;

    const newMessage: Message = {
      id: `msg-${Date.now()}`,
      threadId: selectedThreadId,
      senderId: CURRENT_USER_ID,
      senderName: 'You',
      content,
      timestamp: new Date().toISOString(),
      read: true,
    };

    // Add message to thread
    setMessages((prev) => ({
      ...prev,
      [selectedThreadId]: [...(prev[selectedThreadId] || []), newMessage],
    }));

    // Update thread's last message
    setThreads((prev) =>
      prev.map((t) =>
        t.id === selectedThreadId
          ? { ...t, lastMessage: newMessage }
          : t
      )
    );
  };

  const handleBackToList = () => {
    setShowMobileThread(false);
  };

  return (
    <div className="h-[calc(100vh-4rem)] flex flex-col">
      {/* Header */}
      <div className="border-b border-gray-200 bg-white px-4 py-4">
        <div className="mx-auto max-w-7xl">
          <div className="flex items-center justify-between">
            <div className="flex items-center space-x-4">
              <Link href={`/${locale}`} className="btn btn-outline btn-sm md:hidden">
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
                <h1 className="text-xl font-bold text-gray-900">{t('messages.title')}</h1>
                <p className="text-sm text-gray-600">
                  {totalUnread > 0
                    ? t('messages.unreadCount', { count: totalUnread })
                    : t('messages.allCaughtUp')}
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
              {t('messages.newMessage')}
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
          {threads.length > 0 ? (
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
                  {t('messages.noMessagesTitle')}
                </h3>
                <p className="mt-2 text-sm text-gray-500">
                  {t('messages.noMessagesDescription')}
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
                    title={t('messages.moreOptions')}
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
              <MessageThread
                messages={selectedMessages}
                currentUserId={CURRENT_USER_ID}
              />

              {/* Message composer */}
              <MessageComposer
                onSendMessage={handleSendMessage}
                placeholder={t('messages.composer.placeholderTeacher')}
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
                  {t('messages.selectConversation')}
                </h3>
                <p className="mt-2 text-sm text-gray-500">
                  {t('messages.selectConversationDescription')}
                </p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
