/**
 * LAYA Parent App - ConversationScreen
 *
 * Screen for viewing and participating in a conversation thread.
 * Shows message history grouped by date with message composer at bottom.
 */

import React, {useState, useCallback, useEffect, useRef} from 'react';
import {
  StyleSheet,
  Text,
  View,
  FlatList,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
} from 'react-native';
import {useRoute, useNavigation} from '@react-navigation/native';
import type {RouteProp} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import type {Message} from '../types';
import type {RootStackParamList} from '../types';
import MessageBubble from '../components/MessageBubble';
import MessageComposer from '../components/MessageComposer';
import {
  fetchMessages,
  sendMessage,
  markConversationAsRead,
  getMockMessages,
  formatDateHeader,
} from '../api/messagingApi';

/**
 * Navigation and route types
 */
type ConversationRouteProp = RouteProp<RootStackParamList, 'Conversation'>;
type ConversationNavigationProp = NativeStackNavigationProp<
  RootStackParamList,
  'Conversation'
>;

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  background: '#F5F5F5',
  cardBackground: '#FFFFFF',
  text: '#333333',
  textSecondary: '#666666',
  textLight: '#999999',
  border: '#E0E0E0',
};

// Current user ID - in production, this would come from auth context
const CURRENT_USER_ID = 'parent-1';

/**
 * Message item with date grouping info
 */
interface MessageItem {
  type: 'message' | 'date-header';
  message?: Message;
  dateKey?: string;
}

/**
 * Group messages by date for rendering
 */
function groupMessagesByDate(messages: Message[]): MessageItem[] {
  const items: MessageItem[] = [];
  let currentDateKey: string | null = null;

  // Process messages in chronological order
  const sortedMessages = [...messages].sort(
    (a, b) => new Date(a.sentAt).getTime() - new Date(b.sentAt).getTime(),
  );

  sortedMessages.forEach(message => {
    const dateKey = new Date(message.sentAt).toDateString();

    if (dateKey !== currentDateKey) {
      items.push({
        type: 'date-header',
        dateKey,
        message: undefined,
      });
      currentDateKey = dateKey;
    }

    items.push({
      type: 'message',
      message,
      dateKey: undefined,
    });
  });

  return items;
}

/**
 * ConversationScreen displays a message thread with composer
 */
function ConversationScreen(): React.JSX.Element {
  const route = useRoute<ConversationRouteProp>();
  const navigation = useNavigation<ConversationNavigationProp>();
  const flatListRef = useRef<FlatList<MessageItem>>(null);

  const {conversationId} = route.params;

  const [messages, setMessages] = useState<Message[]>([]);
  const [groupedMessages, setGroupedMessages] = useState<MessageItem[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSending, setIsSending] = useState(false);
  const [error, setError] = useState<string | null>(null);

  /**
   * Load messages from API
   */
  const loadMessages = useCallback(async () => {
    setIsLoading(true);
    setError(null);

    try {
      const response = await fetchMessages({conversationId});

      if (response.success && response.data) {
        setMessages(response.data.messages);
        setGroupedMessages(groupMessagesByDate(response.data.messages));
      } else {
        // Use mock data for development
        const mockData = getMockMessages(conversationId);
        setMessages(mockData.messages);
        setGroupedMessages(groupMessagesByDate(mockData.messages));
      }

      // Mark conversation as read
      await markConversationAsRead(conversationId);
    } catch (err) {
      // Use mock data for development when API is not available
      const mockData = getMockMessages(conversationId);
      setMessages(mockData.messages);
      setGroupedMessages(groupMessagesByDate(mockData.messages));
    } finally {
      setIsLoading(false);
    }
  }, [conversationId]);

  /**
   * Initial load
   */
  useEffect(() => {
    loadMessages();
  }, [loadMessages]);

  /**
   * Scroll to bottom when messages change
   */
  useEffect(() => {
    if (groupedMessages.length > 0) {
      setTimeout(() => {
        flatListRef.current?.scrollToEnd({animated: true});
      }, 100);
    }
  }, [groupedMessages.length]);

  /**
   * Handle sending a message
   */
  const handleSend = useCallback(
    async (content: string) => {
      setIsSending(true);

      try {
        const response = await sendMessage({conversationId, content});

        if (response.success && response.data) {
          // Add the new message to the list
          const newMessages = [...messages, response.data];
          setMessages(newMessages);
          setGroupedMessages(groupMessagesByDate(newMessages));
        } else {
          // Create a local message for development
          const newMessage: Message = {
            id: `msg-${Date.now()}`,
            conversationId,
            senderId: CURRENT_USER_ID,
            senderName: 'You',
            content,
            sentAt: new Date().toISOString(),
            readAt: null,
            attachments: [],
          };
          const newMessages = [...messages, newMessage];
          setMessages(newMessages);
          setGroupedMessages(groupMessagesByDate(newMessages));
        }
      } catch (err) {
        // Create a local message for development
        const newMessage: Message = {
          id: `msg-${Date.now()}`,
          conversationId,
          senderId: CURRENT_USER_ID,
          senderName: 'You',
          content,
          sentAt: new Date().toISOString(),
          readAt: null,
          attachments: [],
        };
        const newMessages = [...messages, newMessage];
        setMessages(newMessages);
        setGroupedMessages(groupMessagesByDate(newMessages));
      } finally {
        setIsSending(false);
      }
    },
    [conversationId, messages],
  );

  /**
   * Render a message item or date header
   */
  const renderItem = ({item}: {item: MessageItem}): React.JSX.Element | null => {
    if (item.type === 'date-header' && item.dateKey) {
      return (
        <View style={styles.dateHeader}>
          <View style={styles.dateHeaderLine} />
          <Text style={styles.dateHeaderText}>
            {formatDateHeader(item.dateKey)}
          </Text>
          <View style={styles.dateHeaderLine} />
        </View>
      );
    }

    if (item.type === 'message' && item.message) {
      return (
        <MessageBubble
          message={item.message}
          isCurrentUser={item.message.senderId === CURRENT_USER_ID}
        />
      );
    }

    return null;
  };

  /**
   * Key extractor for FlatList
   */
  const keyExtractor = useCallback((item: MessageItem, index: number) => {
    if (item.type === 'date-header') {
      return `date-${item.dateKey}-${index}`;
    }
    return item.message?.id || `item-${index}`;
  }, []);

  /**
   * Render empty state
   */
  const renderEmptyState = (): React.JSX.Element => (
    <View style={styles.emptyState}>
      <Text style={styles.emptyStateIcon}>{'\u2709'}</Text>
      <Text style={styles.emptyStateTitle}>No Messages Yet</Text>
      <Text style={styles.emptyStateText}>
        Start the conversation by sending a message below.
      </Text>
    </View>
  );

  if (isLoading) {
    return (
      <View style={styles.loadingContainer}>
        <ActivityIndicator size="large" color={COLORS.primary} />
        <Text style={styles.loadingText}>Loading messages...</Text>
      </View>
    );
  }

  if (error) {
    return (
      <View style={styles.errorContainer}>
        <Text style={styles.errorIcon}>!</Text>
        <Text style={styles.errorTitle}>Something went wrong</Text>
        <Text style={styles.errorText}>{error}</Text>
      </View>
    );
  }

  return (
    <KeyboardAvoidingView
      style={styles.container}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={Platform.OS === 'ios' ? 90 : 0}>
      <View style={styles.messagesContainer}>
        <FlatList
          ref={flatListRef}
          data={groupedMessages}
          renderItem={renderItem}
          keyExtractor={keyExtractor}
          ListEmptyComponent={renderEmptyState}
          contentContainerStyle={
            groupedMessages.length === 0
              ? styles.listContentEmpty
              : styles.listContent
          }
          showsVerticalScrollIndicator={false}
          inverted={false}
          onContentSizeChange={() => {
            flatListRef.current?.scrollToEnd({animated: false});
          }}
        />
      </View>
      <MessageComposer onSend={handleSend} disabled={isSending} />
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: COLORS.background,
  },
  loadingContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: COLORS.background,
  },
  loadingText: {
    marginTop: 12,
    fontSize: 16,
    color: COLORS.textSecondary,
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: COLORS.background,
    padding: 20,
  },
  errorIcon: {
    fontSize: 32,
    fontWeight: '700',
    color: '#C62828',
    marginBottom: 12,
  },
  errorTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: COLORS.text,
    marginBottom: 8,
  },
  errorText: {
    fontSize: 14,
    color: COLORS.textSecondary,
    textAlign: 'center',
  },
  messagesContainer: {
    flex: 1,
  },
  listContent: {
    paddingVertical: 12,
  },
  listContentEmpty: {
    flexGrow: 1,
  },
  dateHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingVertical: 16,
    paddingHorizontal: 16,
  },
  dateHeaderLine: {
    flex: 1,
    height: 1,
    backgroundColor: COLORS.border,
  },
  dateHeaderText: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textLight,
    paddingHorizontal: 12,
    textTransform: 'uppercase',
  },
  emptyState: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 40,
  },
  emptyStateIcon: {
    fontSize: 48,
    marginBottom: 16,
    color: COLORS.textLight,
  },
  emptyStateTitle: {
    fontSize: 18,
    fontWeight: '600',
    color: COLORS.text,
    marginBottom: 8,
  },
  emptyStateText: {
    fontSize: 14,
    color: COLORS.textSecondary,
    textAlign: 'center',
    lineHeight: 20,
  },
});

export default ConversationScreen;
