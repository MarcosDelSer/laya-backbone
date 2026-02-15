/**
 * LAYA Parent App - Messages Screen
 *
 * Displays messaging interface with thread list and message detail view.
 * Parents can view message threads, read messages, and compose/send new messages.
 *
 * Features:
 * - Thread list with unread indicators
 * - Message conversation view
 * - Real-time message composition
 * - Pull-to-refresh for updates
 * - Mobile-optimized split view pattern
 *
 * Adapted from parent-portal/app/messages/page.tsx for React Native.
 */

import React, {useState, useCallback, useEffect, useMemo, useRef} from 'react';
import {
  SafeAreaView,
  View,
  Text,
  StyleSheet,
  FlatList,
  RefreshControl,
  TouchableOpacity,
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Animated,
  Dimensions,
} from 'react-native';

import type {MessagesScreenProps} from '../types/navigation';
import type {Message, MessageThread} from '../types';
import {useRefresh} from '../hooks/useRefresh';
import ThreadPreview from '../components/ThreadPreview';
import MessageBubble, {formatDate} from '../components/MessageBubble';
import MessageComposer from '../components/MessageComposer';

// ============================================================================
// Constants
// ============================================================================

const CURRENT_USER_ID = 'parent-1';
const {width: SCREEN_WIDTH} = Dimensions.get('window');

// ============================================================================
// Mock Data
// ============================================================================

const mockMessages: Record<string, Message[]> = {
  'thread-1': [
    {
      id: 'msg-1',
      threadId: 'thread-1',
      senderId: 'teacher-1',
      senderName: 'Ms. Johnson',
      content:
        'Hi! I wanted to let you know that Emma had a wonderful day today. She really enjoyed the art activity and made a beautiful painting.',
      timestamp: new Date(Date.now() - 3600000 * 2).toISOString(),
      read: true,
    },
    {
      id: 'msg-2',
      threadId: 'thread-1',
      senderId: CURRENT_USER_ID,
      senderName: 'You',
      content:
        "That's great to hear! She's been talking about painting at home too. Thank you for sharing!",
      timestamp: new Date(Date.now() - 3600000).toISOString(),
      read: true,
    },
    {
      id: 'msg-3',
      threadId: 'thread-1',
      senderId: 'teacher-1',
      senderName: 'Ms. Johnson',
      content:
        "You're welcome! We'll be doing more art projects this week. Also, just a reminder that picture day is next Tuesday.",
      timestamp: new Date(Date.now() - 1800000).toISOString(),
      read: false,
    },
  ],
  'thread-2': [
    {
      id: 'msg-4',
      threadId: 'thread-2',
      senderId: 'admin-1',
      senderName: 'Sunshine Daycare Admin',
      content:
        'Dear Parents, we wanted to inform you about the upcoming parent-teacher conference scheduled for February 28th. Please let us know your preferred time slot.',
      timestamp: new Date(Date.now() - 86400000).toISOString(),
      read: true,
    },
    {
      id: 'msg-5',
      threadId: 'thread-2',
      senderId: CURRENT_USER_ID,
      senderName: 'You',
      content:
        'Thank you for the information. Can we schedule for the afternoon, around 2 PM?',
      timestamp: new Date(Date.now() - 43200000).toISOString(),
      read: true,
    },
    {
      id: 'msg-6',
      threadId: 'thread-2',
      senderId: 'admin-1',
      senderName: 'Sunshine Daycare Admin',
      content:
        "The 2 PM slot is available. I've scheduled you for February 28th at 2:00 PM. You'll receive a calendar invite shortly.",
      timestamp: new Date(Date.now() - 36000000).toISOString(),
      read: true,
    },
  ],
  'thread-3': [
    {
      id: 'msg-7',
      threadId: 'thread-3',
      senderId: 'teacher-2',
      senderName: 'Mr. Davis',
      content:
        "Hello! I'm Emma's music teacher. I wanted to share that Emma has been showing great interest in learning the xylophone.",
      timestamp: new Date(Date.now() - 172800000).toISOString(),
      read: true,
    },
    {
      id: 'msg-8',
      threadId: 'thread-3',
      senderId: CURRENT_USER_ID,
      senderName: 'You',
      content:
        "That's wonderful! She does love music. Is there anything we can do at home to encourage this?",
      timestamp: new Date(Date.now() - 86400000).toISOString(),
      read: true,
    },
  ],
};

const createMockThreads = (): MessageThread[] => [
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

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Fetches threads data.
 */
async function fetchThreads(): Promise<MessageThread[]> {
  // Simulate network delay
  await new Promise<void>(resolve => setTimeout(resolve, 800));
  return createMockThreads();
}

/**
 * Group messages by date for rendering with date separators.
 * Reserved for future use when implementing date separators in message view.
 */
// eslint-disable-next-line @typescript-eslint/no-unused-vars
function _groupMessagesByDate(messages: Message[]): Array<{date: string; messages: Message[]}> {
  const groups: Record<string, Message[]> = {};

  messages.forEach(message => {
    const dateKey = formatDate(message.timestamp);
    if (!groups[dateKey]) {
      groups[dateKey] = [];
    }
    groups[dateKey].push(message);
  });

  return Object.entries(groups).map(([date, msgs]) => ({
    date,
    messages: msgs,
  }));
}

// ============================================================================
// Sub-components
// ============================================================================

interface HeaderProps {
  totalUnread: number;
  showBackButton?: boolean;
  onBack?: () => void;
  title?: string;
}

/**
 * Header component with title and unread count.
 */
function Header({
  totalUnread,
  showBackButton = false,
  onBack,
  title = 'Messages',
}: HeaderProps): React.JSX.Element {
  return (
    <View style={headerStyles.container}>
      {showBackButton && (
        <TouchableOpacity
          style={headerStyles.backButton}
          onPress={onBack}
          activeOpacity={0.7}
          accessibilityLabel="Go back"
          accessibilityRole="button">
          <Text style={headerStyles.backIcon}>←</Text>
        </TouchableOpacity>
      )}
      <View style={headerStyles.titleContainer}>
        <Text
          style={[
            headerStyles.title,
            showBackButton && headerStyles.titleCentered,
          ]}
          numberOfLines={1}>
          {title}
        </Text>
        {totalUnread > 0 && !showBackButton && (
          <View style={headerStyles.badge}>
            <Text style={headerStyles.badgeText}>
              {totalUnread > 9 ? '9+' : totalUnread}
            </Text>
          </View>
        )}
      </View>
      {!showBackButton && (
        <Text style={headerStyles.subtitle}>
          Communicate with your child's teachers
        </Text>
      )}
    </View>
  );
}

const headerStyles = StyleSheet.create({
  container: {
    paddingHorizontal: 16,
    paddingTop: 16,
    paddingBottom: 12,
    backgroundColor: '#FFFFFF',
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  backButton: {
    position: 'absolute',
    left: 16,
    top: 16,
    zIndex: 1,
    padding: 8,
  },
  backIcon: {
    fontSize: 24,
    color: '#3B82F6',
  },
  titleContainer: {
    flexDirection: 'row',
    alignItems: 'center',
  },
  title: {
    fontSize: 28,
    fontWeight: '700',
    color: '#111827',
  },
  titleCentered: {
    flex: 1,
    textAlign: 'center',
    fontSize: 18,
    fontWeight: '600',
  },
  badge: {
    marginLeft: 12,
    minWidth: 24,
    height: 24,
    borderRadius: 12,
    backgroundColor: '#3B82F6',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 8,
  },
  badgeText: {
    fontSize: 13,
    fontWeight: '600',
    color: '#FFFFFF',
  },
  subtitle: {
    fontSize: 15,
    color: '#6B7280',
    marginTop: 4,
  },
});

/**
 * Empty state for no threads.
 */
function EmptyState(): React.JSX.Element {
  return (
    <View style={emptyStyles.container}>
      <View style={emptyStyles.iconContainer}>
        <Text style={emptyStyles.icon}>💬</Text>
      </View>
      <Text style={emptyStyles.title}>No messages yet</Text>
      <Text style={emptyStyles.message}>
        Messages from your child's teachers will appear here.
      </Text>
    </View>
  );
}

const emptyStyles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
    marginTop: 48,
  },
  iconContainer: {
    width: 80,
    height: 80,
    borderRadius: 40,
    backgroundColor: '#F3F4F6',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  icon: {
    fontSize: 40,
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 8,
    textAlign: 'center',
  },
  message: {
    fontSize: 15,
    color: '#6B7280',
    textAlign: 'center',
    lineHeight: 22,
  },
});

/**
 * Error state with retry.
 */
function ErrorState({
  message,
  onRetry,
}: {
  message: string;
  onRetry: () => void;
}): React.JSX.Element {
  return (
    <View style={errorStyles.container}>
      <View style={errorStyles.iconContainer}>
        <Text style={errorStyles.icon}>!</Text>
      </View>
      <Text style={errorStyles.title}>Unable to load messages</Text>
      <Text style={errorStyles.message}>{message}</Text>
      <TouchableOpacity
        style={errorStyles.retryButton}
        onPress={onRetry}
        activeOpacity={0.7}>
        <Text style={errorStyles.retryText}>Try Again</Text>
      </TouchableOpacity>
    </View>
  );
}

const errorStyles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
    marginTop: 48,
  },
  iconContainer: {
    width: 64,
    height: 64,
    borderRadius: 32,
    backgroundColor: '#FEE2E2',
    justifyContent: 'center',
    alignItems: 'center',
    marginBottom: 16,
  },
  icon: {
    fontSize: 32,
    fontWeight: '700',
    color: '#DC2626',
  },
  title: {
    fontSize: 18,
    fontWeight: '600',
    color: '#111827',
    marginBottom: 8,
    textAlign: 'center',
  },
  message: {
    fontSize: 15,
    color: '#6B7280',
    textAlign: 'center',
    lineHeight: 22,
    marginBottom: 24,
  },
  retryButton: {
    backgroundColor: '#3B82F6',
    paddingVertical: 12,
    paddingHorizontal: 24,
    borderRadius: 8,
  },
  retryText: {
    color: '#FFFFFF',
    fontSize: 16,
    fontWeight: '600',
  },
});

/**
 * Loading state.
 */
function LoadingState(): React.JSX.Element {
  return (
    <View style={loadingStyles.container}>
      <ActivityIndicator size="large" color="#3B82F6" />
      <Text style={loadingStyles.text}>Loading messages...</Text>
    </View>
  );
}

const loadingStyles = StyleSheet.create({
  container: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 32,
  },
  text: {
    marginTop: 16,
    fontSize: 15,
    color: '#6B7280',
  },
});

/**
 * Date separator for message grouping.
 */
function DateSeparator({date}: {date: string}): React.JSX.Element {
  return (
    <View style={separatorStyles.container}>
      <View style={separatorStyles.line} />
      <Text style={separatorStyles.text}>{date}</Text>
      <View style={separatorStyles.line} />
    </View>
  );
}

const separatorStyles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'center',
    paddingHorizontal: 16,
    paddingVertical: 16,
  },
  line: {
    flex: 1,
    height: 1,
    backgroundColor: '#E5E7EB',
  },
  text: {
    marginHorizontal: 12,
    fontSize: 12,
    fontWeight: '500',
    color: '#6B7280',
  },
});

// ============================================================================
// Main Component
// ============================================================================

/**
 * Messages Screen - displays messaging interface with thread list and conversations.
 *
 * Uses a slide-based navigation pattern for mobile:
 * - Shows thread list by default
 * - Slides to show conversation when a thread is selected
 * - Back button returns to thread list
 */
function MessagesScreen(_props: MessagesScreenProps): React.JSX.Element {
  // Thread data management
  const {refreshing, data: threads, error, onRefresh, setData: setThreads} =
    useRefresh<MessageThread[]>(fetchThreads);

  // Message state (local mock data)
  const [messages, setMessages] = useState<Record<string, Message[]>>(mockMessages);

  // Selection state
  const [selectedThreadId, setSelectedThreadId] = useState<string | null>(null);
  const [showConversation, setShowConversation] = useState(false);

  // Animation for slide transition
  const slideAnim = useRef(new Animated.Value(0)).current;
  const flatListRef = useRef<FlatList>(null);

  // Initial load
  useEffect(() => {
    onRefresh();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // Calculate total unread
  const totalUnread = useMemo(() => {
    return threads?.reduce((sum, t) => sum + t.unreadCount, 0) || 0;
  }, [threads]);

  // Get selected thread data
  const selectedThread = useMemo(() => {
    return threads?.find(t => t.id === selectedThreadId);
  }, [threads, selectedThreadId]);

  // Get messages for selected thread
  const selectedMessages = useMemo(() => {
    if (!selectedThreadId) {
      return [];
    }
    return messages[selectedThreadId] || [];
  }, [messages, selectedThreadId]);

  /**
   * Handle thread selection
   */
  const handleSelectThread = useCallback(
    (thread: MessageThread) => {
      setSelectedThreadId(thread.id);
      setShowConversation(true);

      // Animate slide
      Animated.timing(slideAnim, {
        toValue: 1,
        duration: 250,
        useNativeDriver: true,
      }).start();

      // Mark thread as read
      if (threads) {
        setThreads(
          threads.map(t =>
            t.id === thread.id ? {...t, unreadCount: 0} : t,
          ),
        );
      }

      // Mark messages as read
      setMessages(prev => ({
        ...prev,
        [thread.id]: prev[thread.id]?.map(m => ({...m, read: true})) || [],
      }));
    },
    [threads, setThreads, slideAnim],
  );

  /**
   * Handle back to thread list
   */
  const handleBack = useCallback(() => {
    Animated.timing(slideAnim, {
      toValue: 0,
      duration: 250,
      useNativeDriver: true,
    }).start(() => {
      setShowConversation(false);
      setSelectedThreadId(null);
    });
  }, [slideAnim]);

  /**
   * Handle sending a new message
   */
  const handleSendMessage = useCallback(
    (content: string) => {
      if (!selectedThreadId) {
        return;
      }

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
      setMessages(prev => ({
        ...prev,
        [selectedThreadId]: [...(prev[selectedThreadId] || []), newMessage],
      }));

      // Update thread's last message
      if (threads) {
        setThreads(
          threads.map(t =>
            t.id === selectedThreadId ? {...t, lastMessage: newMessage} : t,
          ),
        );
      }

      // Scroll to bottom of messages
      setTimeout(() => {
        flatListRef.current?.scrollToEnd({animated: true});
      }, 100);
    },
    [selectedThreadId, threads, setThreads],
  );

  /**
   * Render thread item
   */
  const renderThreadItem = useCallback(
    ({item}: {item: MessageThread}) => (
      <ThreadPreview
        thread={item}
        isSelected={item.id === selectedThreadId}
        onPress={handleSelectThread}
      />
    ),
    [selectedThreadId, handleSelectThread],
  );

  /**
   * Render message item
   */
  const renderMessageItem = useCallback(
    ({item, index}: {item: Message; index: number}) => {
      // Check if we need a date separator
      const prevMessage = selectedMessages[index - 1];
      const showDateSeparator =
        !prevMessage ||
        formatDate(item.timestamp) !== formatDate(prevMessage.timestamp);

      return (
        <>
          {showDateSeparator && <DateSeparator date={formatDate(item.timestamp)} />}
          <MessageBubble
            message={item}
            isCurrentUser={item.senderId === CURRENT_USER_ID}
          />
        </>
      );
    },
    [selectedMessages],
  );

  /**
   * Key extractors
   */
  const threadKeyExtractor = useCallback((item: MessageThread) => item.id, []);
  const messageKeyExtractor = useCallback((item: Message) => item.id, []);

  // Animation transforms
  const threadListTransform = {
    transform: [
      {
        translateX: slideAnim.interpolate({
          inputRange: [0, 1],
          outputRange: [0, -SCREEN_WIDTH],
        }),
      },
    ],
  };

  const conversationTransform = {
    transform: [
      {
        translateX: slideAnim.interpolate({
          inputRange: [0, 1],
          outputRange: [SCREEN_WIDTH, 0],
        }),
      },
    ],
  };

  // Show loading state on initial load
  if (threads === null && refreshing && !error) {
    return (
      <SafeAreaView style={styles.container}>
        <Header totalUnread={0} />
        <LoadingState />
      </SafeAreaView>
    );
  }

  // Show error state
  if (error && threads === null) {
    return (
      <SafeAreaView style={styles.container}>
        <Header totalUnread={0} />
        <ErrorState
          message={error.message || 'Please check your connection and try again.'}
          onRetry={onRefresh}
        />
      </SafeAreaView>
    );
  }

  return (
    <SafeAreaView style={styles.container}>
      {/* Thread List View */}
      <Animated.View
        style={[styles.slideContainer, threadListTransform]}
        pointerEvents={showConversation ? 'none' : 'auto'}>
        <Header totalUnread={totalUnread} />
        <FlatList
          data={threads || []}
          renderItem={renderThreadItem}
          keyExtractor={threadKeyExtractor}
          contentContainerStyle={styles.listContent}
          showsVerticalScrollIndicator={false}
          ListEmptyComponent={<EmptyState />}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={onRefresh}
              tintColor="#3B82F6"
              colors={['#3B82F6']}
              title="Pull to refresh"
              titleColor="#6B7280"
            />
          }
        />
      </Animated.View>

      {/* Conversation View */}
      {showConversation && (
        <Animated.View style={[styles.slideContainer, conversationTransform]}>
          <KeyboardAvoidingView
            style={styles.keyboardAvoid}
            behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
            keyboardVerticalOffset={Platform.OS === 'ios' ? 0 : 24}>
            <Header
              totalUnread={0}
              showBackButton
              onBack={handleBack}
              title={selectedThread?.subject || 'Conversation'}
            />
            <FlatList
              ref={flatListRef}
              data={selectedMessages}
              renderItem={renderMessageItem}
              keyExtractor={messageKeyExtractor}
              contentContainerStyle={styles.messagesContent}
              showsVerticalScrollIndicator={false}
              inverted={false}
              onContentSizeChange={() => {
                flatListRef.current?.scrollToEnd({animated: false});
              }}
            />
            <MessageComposer onSendMessage={handleSendMessage} />
          </KeyboardAvoidingView>
        </Animated.View>
      )}
    </SafeAreaView>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#F9FAFB',
  },
  slideContainer: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: '#F9FAFB',
  },
  keyboardAvoid: {
    flex: 1,
  },
  listContent: {
    flexGrow: 1,
  },
  messagesContent: {
    paddingBottom: 8,
    flexGrow: 1,
  },
});

export default MessagesScreen;
