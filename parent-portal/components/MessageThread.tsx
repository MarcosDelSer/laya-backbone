'use client';

import { useRef, useEffect } from 'react';
import { MessageBubble, formatDate } from './MessageBubble';

interface Message {
  id: string;
  threadId: string;
  senderId: string;
  senderName: string;
  content: string;
  timestamp: string;
  read: boolean;
}

interface MessageThreadProps {
  messages: Message[];
  currentUserId: string;
}

// Group messages by date
function groupMessagesByDate(messages: Message[]): Map<string, Message[]> {
  const groups = new Map<string, Message[]>();

  messages.forEach((message) => {
    const dateKey = new Date(message.timestamp).toDateString();
    if (!groups.has(dateKey)) {
      groups.set(dateKey, []);
    }
    groups.get(dateKey)!.push(message);
  });

  return groups;
}

export function MessageThread({ messages, currentUserId }: MessageThreadProps) {
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // Scroll to bottom when new messages arrive
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  if (messages.length === 0) {
    return (
      <div className="flex flex-1 items-center justify-center p-8">
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
            No messages yet
          </h3>
          <p className="mt-2 text-sm text-gray-500">
            Start the conversation by sending a message below.
          </p>
        </div>
      </div>
    );
  }

  const groupedMessages = groupMessagesByDate(messages);

  return (
    <div className="flex-1 overflow-y-auto p-4">
      {Array.from(groupedMessages.entries()).map(([dateKey, dayMessages]) => (
        <div key={dateKey}>
          {/* Date divider */}
          <div className="flex items-center justify-center my-6">
            <div className="border-t border-gray-200 flex-1" />
            <span className="px-4 text-xs font-medium text-gray-500 bg-white">
              {formatDate(dayMessages[0].timestamp)}
            </span>
            <div className="border-t border-gray-200 flex-1" />
          </div>

          {/* Messages for this day */}
          {dayMessages.map((message) => (
            <MessageBubble
              key={message.id}
              message={message}
              isCurrentUser={message.senderId === currentUserId}
            />
          ))}
        </div>
      ))}
      <div ref={messagesEndRef} />
    </div>
  );
}

// Thread preview component for the sidebar/list
interface ThreadPreviewProps {
  thread: {
    id: string;
    subject: string;
    participants: string[];
    lastMessage: Message;
    unreadCount: number;
  };
  isSelected: boolean;
  onClick: () => void;
}

export function ThreadPreview({ thread, isSelected, onClick }: ThreadPreviewProps) {
  const formatPreviewTime = (timestamp: string): string => {
    const date = new Date(timestamp);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffHours = diffMs / (1000 * 60 * 60);
    const diffDays = diffMs / (1000 * 60 * 60 * 24);

    if (diffHours < 1) {
      const diffMins = Math.floor(diffMs / (1000 * 60));
      return diffMins < 1 ? 'Just now' : `${diffMins}m ago`;
    } else if (diffHours < 24) {
      return date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
      });
    } else if (diffDays < 7) {
      return date.toLocaleDateString('en-US', { weekday: 'short' });
    } else {
      return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
      });
    }
  };

  return (
    <button
      onClick={onClick}
      className={`w-full text-left px-4 py-3 border-b border-gray-100 transition-colors ${
        isSelected
          ? 'bg-primary/10 border-l-4 border-l-primary'
          : 'hover:bg-gray-50'
      }`}
    >
      <div className="flex items-start space-x-3">
        {/* Avatar */}
        <div className="flex-shrink-0">
          <div className="h-12 w-12 rounded-full bg-gradient-to-br from-blue-400 to-blue-600 flex items-center justify-center text-white font-semibold text-lg">
            {thread.lastMessage.senderName.charAt(0).toUpperCase()}
          </div>
        </div>

        {/* Content */}
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between mb-1">
            <h4
              className={`text-sm font-medium truncate ${
                thread.unreadCount > 0 ? 'text-gray-900' : 'text-gray-700'
              }`}
            >
              {thread.subject}
            </h4>
            <span className="text-xs text-gray-500 flex-shrink-0 ml-2">
              {formatPreviewTime(thread.lastMessage.timestamp)}
            </span>
          </div>
          <div className="flex items-center justify-between">
            <p
              className={`text-sm truncate ${
                thread.unreadCount > 0 ? 'text-gray-900 font-medium' : 'text-gray-500'
              }`}
            >
              {thread.lastMessage.content}
            </p>
            {thread.unreadCount > 0 && (
              <span className="flex-shrink-0 ml-2 inline-flex items-center justify-center h-5 w-5 rounded-full bg-primary text-white text-xs font-medium">
                {thread.unreadCount > 9 ? '9+' : thread.unreadCount}
              </span>
            )}
          </div>
          <p className="text-xs text-gray-400 mt-1 truncate">
            {thread.participants.join(', ')}
          </p>
        </div>
      </div>
    </button>
  );
}
