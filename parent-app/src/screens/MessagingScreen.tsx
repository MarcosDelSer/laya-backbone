/**
 * LAYA Parent App - MessagingScreen
 *
 * Main screen displaying a list of conversation threads.
 * Shows conversation previews with participant info, last message,
 * and unread indicators.
 */

import React, {useState, useCallback, useEffect} from 'react';
import {
  StyleSheet,
  Text,
  View,
  FlatList,
  TouchableOpacity,
  RefreshControl,
  ActivityIndicator,
} from 'react-native';
import {useNavigation} from '@react-navigation/native';
import type {NativeStackNavigationProp} from '@react-navigation/native-stack';
import type {Conversation} from '../types';
import type {RootStackParamList} from '../types';
import {
  fetchConversations,
  getMockConversations,
  formatPreviewTime,
  getParticipantName,
  getInitials,
} from '../api/messagingApi';

/**
 * Navigation prop type
 */
type MessagingScreenNavigationProp = NativeStackNavigationProp<
  RootStackParamList,
  'Messages'
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
  unreadBadge: '#4A90D9',
  avatarBlue: '#4A90D9',
};

// Current user ID - in production, this would come from auth context
const CURRENT_USER_ID = 'parent-1';

/**
 * ConversationItem displays a single conversation preview
 */
interface ConversationItemProps {
  conversation: Conversation;
  onPress: () => void;
}

function ConversationItem({
  conversation,
  onPress,
}: ConversationItemProps): React.JSX.Element {
  const participantName = getParticipantName(conversation, CURRENT_USER_ID);
  const initials = getInitials(participantName);
  const hasUnread = conversation.unreadCount > 0;

  return (
    <TouchableOpacity
      style={[styles.conversationItem, hasUnread && styles.conversationUnread]}
      onPress={onPress}
      activeOpacity={0.7}
      accessibilityLabel={`Conversation with ${participantName}`}
      accessibilityRole="button">
      <View style={styles.avatar}>
        <Text style={styles.avatarText}>{initials}</Text>
      </View>
      <View style={styles.conversationContent}>
        <View style={styles.conversationHeader}>
          <Text
            style={[
              styles.participantName,
              hasUnread && styles.participantNameUnread,
            ]}
            numberOfLines={1}>
            {participantName}
          </Text>
          <Text style={styles.timestamp}>
            {conversation.lastMessage
              ? formatPreviewTime(conversation.lastMessage.sentAt)
              : ''}
          </Text>
        </View>
        <View style={styles.conversationPreview}>
          <Text
            style={[
              styles.lastMessage,
              hasUnread && styles.lastMessageUnread,
            ]}
            numberOfLines={2}>
            {conversation.lastMessage?.content || 'No messages yet'}
          </Text>
          {hasUnread && (
            <View style={styles.unreadBadge}>
              <Text style={styles.unreadBadgeText}>
                {conversation.unreadCount > 9 ? '9+' : conversation.unreadCount}
              </Text>
            </View>
          )}
        </View>
      </View>
    </TouchableOpacity>
  );
}

/**
 * MessagingScreen displays a list of conversation threads
 */
function MessagingScreen(): React.JSX.Element {
  const navigation = useNavigation<MessagingScreenNavigationProp>();
  const [conversations, setConversations] = useState<Conversation[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [error, setError] = useState<string | null>(null);

  /**
   * Load conversations from API
   */
  const loadConversations = useCallback(async (showRefresh = false) => {
    if (showRefresh) {
      setIsRefreshing(true);
    } else {
      setIsLoading(true);
    }
    setError(null);

    try {
      const response = await fetchConversations();

      if (response.success && response.data) {
        setConversations(response.data.conversations);
      } else {
        // Use mock data for development
        const mockData = getMockConversations();
        setConversations(mockData.conversations);
      }
    } catch (err) {
      // Use mock data for development when API is not available
      const mockData = getMockConversations();
      setConversations(mockData.conversations);
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  }, []);

  /**
   * Initial load
   */
  useEffect(() => {
    loadConversations();
  }, [loadConversations]);

  /**
   * Handle pull-to-refresh
   */
  const handleRefresh = useCallback(() => {
    loadConversations(true);
  }, [loadConversations]);

  /**
   * Navigate to conversation screen
   */
  const handleConversationPress = useCallback(
    (conversationId: string) => {
      navigation.navigate('Conversation', {conversationId});
    },
    [navigation],
  );

  /**
   * Render a conversation item
   */
  const renderConversationItem = ({
    item,
  }: {
    item: Conversation;
  }): React.JSX.Element => (
    <ConversationItem
      conversation={item}
      onPress={() => handleConversationPress(item.id)}
    />
  );

  /**
   * Key extractor for FlatList
   */
  const keyExtractor = useCallback(
    (item: Conversation) => item.id,
    [],
  );

  /**
   * Render empty state
   */
  const renderEmptyState = (): React.JSX.Element => (
    <View style={styles.emptyState}>
      <Text style={styles.emptyStateIcon}>{'\u2709'}</Text>
      <Text style={styles.emptyStateTitle}>No Messages Yet</Text>
      <Text style={styles.emptyStateText}>
        When you receive messages from your child's educators, they will appear
        here.
      </Text>
    </View>
  );

  /**
   * Render separator between items
   */
  const renderSeparator = (): React.JSX.Element => (
    <View style={styles.separator} />
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
    <View style={styles.container}>
      <FlatList
        data={conversations}
        renderItem={renderConversationItem}
        keyExtractor={keyExtractor}
        ItemSeparatorComponent={renderSeparator}
        ListEmptyComponent={renderEmptyState}
        contentContainerStyle={
          conversations.length === 0
            ? styles.listContentEmpty
            : styles.listContent
        }
        showsVerticalScrollIndicator={false}
        refreshControl={
          <RefreshControl
            refreshing={isRefreshing}
            onRefresh={handleRefresh}
            tintColor={COLORS.primary}
            colors={[COLORS.primary]}
          />
        }
      />
    </View>
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
  listContent: {
    paddingVertical: 8,
  },
  listContentEmpty: {
    flexGrow: 1,
  },
  conversationItem: {
    flexDirection: 'row',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: COLORS.cardBackground,
  },
  conversationUnread: {
    backgroundColor: 'rgba(74, 144, 217, 0.05)',
  },
  avatar: {
    width: 52,
    height: 52,
    borderRadius: 26,
    backgroundColor: COLORS.avatarBlue,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  avatarText: {
    fontSize: 18,
    fontWeight: '600',
    color: '#FFFFFF',
  },
  conversationContent: {
    flex: 1,
    justifyContent: 'center',
  },
  conversationHeader: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  participantName: {
    flex: 1,
    fontSize: 16,
    fontWeight: '500',
    color: COLORS.text,
    marginRight: 8,
  },
  participantNameUnread: {
    fontWeight: '700',
  },
  timestamp: {
    fontSize: 12,
    color: COLORS.textLight,
  },
  conversationPreview: {
    flexDirection: 'row',
    alignItems: 'flex-start',
  },
  lastMessage: {
    flex: 1,
    fontSize: 14,
    color: COLORS.textSecondary,
    lineHeight: 20,
  },
  lastMessageUnread: {
    fontWeight: '600',
    color: COLORS.text,
  },
  unreadBadge: {
    minWidth: 22,
    height: 22,
    borderRadius: 11,
    backgroundColor: COLORS.unreadBadge,
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 6,
    marginLeft: 8,
  },
  unreadBadgeText: {
    fontSize: 12,
    fontWeight: '600',
    color: '#FFFFFF',
  },
  separator: {
    height: 1,
    backgroundColor: COLORS.border,
    marginLeft: 80,
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

export default MessagingScreen;
