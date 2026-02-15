/**
 * LAYA Parent App - Messaging API
 *
 * API functions for managing conversations and messages between
 * parents and educators. Follows patterns from Messenger module.
 */

import {api} from './client';
import {API_CONFIG} from './config';
import type {
  ApiResponse,
  Conversation,
  Message,
  PaginatedResponse,
} from '../types';

/**
 * Response type for conversations list endpoint
 */
interface ConversationsListResponse {
  conversations: Conversation[];
}

/**
 * Response type for messages list endpoint
 */
interface MessagesListResponse {
  messages: Message[];
  hasMore: boolean;
}

/**
 * Parameters for fetching messages
 */
interface FetchMessagesParams {
  conversationId: string;
  before?: string; // Message ID for pagination
  limit?: number;
}

/**
 * Parameters for sending a message
 */
interface SendMessageParams {
  conversationId: string;
  content: string;
}

/**
 * Fetch all conversations for the current user
 */
export async function fetchConversations(): Promise<
  ApiResponse<ConversationsListResponse>
> {
  return api.get<ConversationsListResponse>(
    API_CONFIG.endpoints.messaging.conversations,
  );
}

/**
 * Fetch messages for a specific conversation
 */
export async function fetchMessages(
  params: FetchMessagesParams,
): Promise<ApiResponse<MessagesListResponse>> {
  const endpoint = API_CONFIG.endpoints.messaging.messages.replace(
    ':conversationId',
    params.conversationId,
  );

  const queryParams: Record<string, string> = {};
  if (params.before) {
    queryParams.before = params.before;
  }
  if (params.limit !== undefined) {
    queryParams.limit = params.limit.toString();
  }

  return api.get<MessagesListResponse>(endpoint, queryParams);
}

/**
 * Send a message in a conversation
 */
export async function sendMessage(
  params: SendMessageParams,
): Promise<ApiResponse<Message>> {
  const endpoint = API_CONFIG.endpoints.messaging.send.replace(
    ':conversationId',
    params.conversationId,
  );

  return api.post<Message>(endpoint, {content: params.content});
}

/**
 * Mark a conversation as read
 */
export async function markConversationAsRead(
  conversationId: string,
): Promise<ApiResponse<void>> {
  const endpoint = API_CONFIG.endpoints.messaging.markRead.replace(
    ':conversationId',
    conversationId,
  );

  return api.post<void>(endpoint);
}

/**
 * Format a timestamp for display in message previews
 */
export function formatPreviewTime(timestamp: string): string {
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
    return date.toLocaleDateString('en-US', {weekday: 'short'});
  } else {
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
    });
  }
}

/**
 * Format a timestamp for message display
 */
export function formatMessageTime(timestamp: string): string {
  const date = new Date(timestamp);
  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

/**
 * Format a date for message group headers
 */
export function formatDateHeader(timestamp: string): string {
  const date = new Date(timestamp);
  const today = new Date();
  const yesterday = new Date(today);
  yesterday.setDate(yesterday.getDate() - 1);

  if (date.toDateString() === today.toDateString()) {
    return 'Today';
  } else if (date.toDateString() === yesterday.toDateString()) {
    return 'Yesterday';
  } else {
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      year: date.getFullYear() !== today.getFullYear() ? 'numeric' : undefined,
    });
  }
}

/**
 * Get participant display name (excluding current user)
 */
export function getParticipantName(
  conversation: Conversation,
  currentUserId: string,
): string {
  const otherParticipants = conversation.participants.filter(
    p => p.id !== currentUserId,
  );
  return otherParticipants.map(p => p.name).join(', ') || 'Unknown';
}

/**
 * Get initials from a name for avatar display
 */
export function getInitials(name: string): string {
  const parts = name.split(' ').filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase();
  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
}

/**
 * Generate mock conversation data for development
 */
export function getMockConversations(): ConversationsListResponse {
  const mockConversations: Conversation[] = [
    {
      id: 'conv-1',
      participants: [
        {
          id: 'parent-1',
          name: 'Current User',
          role: 'parent',
          photoUrl: null,
        },
        {
          id: 'teacher-1',
          name: 'Ms. Johnson',
          role: 'teacher',
          photoUrl: null,
        },
      ],
      lastMessage: {
        id: 'msg-1',
        conversationId: 'conv-1',
        senderId: 'teacher-1',
        senderName: 'Ms. Johnson',
        content:
          "Just a reminder that picture day is next Tuesday. Please have Emma wear something nice!",
        sentAt: new Date(Date.now() - 1800000).toISOString(), // 30 min ago
        readAt: null,
        attachments: [],
      },
      unreadCount: 1,
      updatedAt: new Date(Date.now() - 1800000).toISOString(),
    },
    {
      id: 'conv-2',
      participants: [
        {
          id: 'parent-1',
          name: 'Current User',
          role: 'parent',
          photoUrl: null,
        },
        {
          id: 'admin-1',
          name: 'Sunshine Daycare Admin',
          role: 'admin',
          photoUrl: null,
        },
      ],
      lastMessage: {
        id: 'msg-4',
        conversationId: 'conv-2',
        senderId: 'admin-1',
        senderName: 'Sunshine Daycare Admin',
        content:
          "The 2 PM slot is available. I've scheduled you for February 28th at 2:00 PM.",
        sentAt: new Date(Date.now() - 36000000).toISOString(), // 10 hours ago
        readAt: new Date(Date.now() - 35000000).toISOString(),
        attachments: [],
      },
      unreadCount: 0,
      updatedAt: new Date(Date.now() - 36000000).toISOString(),
    },
    {
      id: 'conv-3',
      participants: [
        {
          id: 'parent-1',
          name: 'Current User',
          role: 'parent',
          photoUrl: null,
        },
        {
          id: 'teacher-2',
          name: 'Mr. Davis',
          role: 'teacher',
          photoUrl: null,
        },
      ],
      lastMessage: {
        id: 'msg-7',
        conversationId: 'conv-3',
        senderId: 'parent-1',
        senderName: 'Current User',
        content:
          "That's wonderful! She does love music. Is there anything we can do at home to encourage this?",
        sentAt: new Date(Date.now() - 86400000).toISOString(), // Yesterday
        readAt: new Date(Date.now() - 86000000).toISOString(),
        attachments: [],
      },
      unreadCount: 0,
      updatedAt: new Date(Date.now() - 86400000).toISOString(),
    },
  ];

  return {conversations: mockConversations};
}

/**
 * Generate mock messages for a conversation
 */
export function getMockMessages(conversationId: string): MessagesListResponse {
  const currentUserId = 'parent-1';

  const messagesByConversation: Record<string, Message[]> = {
    'conv-1': [
      {
        id: 'msg-1-1',
        conversationId: 'conv-1',
        senderId: 'teacher-1',
        senderName: 'Ms. Johnson',
        content:
          "Hi! I wanted to let you know that Emma had a wonderful day today. She really enjoyed the art activity and made a beautiful painting.",
        sentAt: new Date(Date.now() - 3600000 * 2).toISOString(),
        readAt: new Date(Date.now() - 3500000).toISOString(),
        attachments: [],
      },
      {
        id: 'msg-1-2',
        conversationId: 'conv-1',
        senderId: currentUserId,
        senderName: 'You',
        content:
          "That's great to hear! She's been talking about painting at home too. Thank you for sharing!",
        sentAt: new Date(Date.now() - 3600000).toISOString(),
        readAt: new Date(Date.now() - 3500000).toISOString(),
        attachments: [],
      },
      {
        id: 'msg-1-3',
        conversationId: 'conv-1',
        senderId: 'teacher-1',
        senderName: 'Ms. Johnson',
        content:
          "Just a reminder that picture day is next Tuesday. Please have Emma wear something nice!",
        sentAt: new Date(Date.now() - 1800000).toISOString(),
        readAt: null,
        attachments: [],
      },
    ],
    'conv-2': [
      {
        id: 'msg-2-1',
        conversationId: 'conv-2',
        senderId: 'admin-1',
        senderName: 'Sunshine Daycare Admin',
        content:
          'Dear Parents, we wanted to inform you about the upcoming parent-teacher conference scheduled for February 28th. Please let us know your preferred time slot.',
        sentAt: new Date(Date.now() - 86400000).toISOString(),
        readAt: new Date(Date.now() - 85000000).toISOString(),
        attachments: [],
      },
      {
        id: 'msg-2-2',
        conversationId: 'conv-2',
        senderId: currentUserId,
        senderName: 'You',
        content:
          'Thank you for the information. Can we schedule for the afternoon, around 2 PM?',
        sentAt: new Date(Date.now() - 43200000).toISOString(),
        readAt: new Date(Date.now() - 42000000).toISOString(),
        attachments: [],
      },
      {
        id: 'msg-2-3',
        conversationId: 'conv-2',
        senderId: 'admin-1',
        senderName: 'Sunshine Daycare Admin',
        content:
          "The 2 PM slot is available. I've scheduled you for February 28th at 2:00 PM. You'll receive a calendar invite shortly.",
        sentAt: new Date(Date.now() - 36000000).toISOString(),
        readAt: new Date(Date.now() - 35000000).toISOString(),
        attachments: [],
      },
    ],
    'conv-3': [
      {
        id: 'msg-3-1',
        conversationId: 'conv-3',
        senderId: 'teacher-2',
        senderName: 'Mr. Davis',
        content:
          "Hello! I'm Emma's music teacher. I wanted to share that Emma has been showing great interest in learning the xylophone.",
        sentAt: new Date(Date.now() - 172800000).toISOString(),
        readAt: new Date(Date.now() - 170000000).toISOString(),
        attachments: [],
      },
      {
        id: 'msg-3-2',
        conversationId: 'conv-3',
        senderId: currentUserId,
        senderName: 'You',
        content:
          "That's wonderful! She does love music. Is there anything we can do at home to encourage this?",
        sentAt: new Date(Date.now() - 86400000).toISOString(),
        readAt: new Date(Date.now() - 86000000).toISOString(),
        attachments: [],
      },
    ],
  };

  return {
    messages: messagesByConversation[conversationId] || [],
    hasMore: false,
  };
}
