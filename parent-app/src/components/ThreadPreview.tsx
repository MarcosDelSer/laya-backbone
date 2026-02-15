/**
 * LAYA Parent App - Thread Preview Component
 *
 * Displays a preview of a message thread in a list, showing the subject,
 * last message, participant info, and unread count.
 *
 * Adapted from parent-portal/components/MessageThread.tsx ThreadPreview for React Native.
 */

import React, {useCallback} from 'react';
import {View, Text, StyleSheet, TouchableOpacity} from 'react-native';

import type {MessageThread} from '../types';

// ============================================================================
// Props Interface
// ============================================================================

interface ThreadPreviewProps {
  /** The message thread to display */
  thread: MessageThread;
  /** Whether this thread is currently selected */
  isSelected: boolean;
  /** Callback when the thread is pressed */
  onPress: (thread: MessageThread) => void;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Format a timestamp for preview display.
 * Shows relative time for recent messages, time for today, weekday for recent days.
 */
function formatPreviewTime(timestamp: string): string {
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
 * Get initials from a name for avatar display.
 */
function getInitials(name: string): string {
  const parts = name.trim().split(/\s+/);
  if (parts.length >= 2) {
    return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase();
  }
  return name.charAt(0).toUpperCase();
}

// ============================================================================
// Component
// ============================================================================

/**
 * ThreadPreview - displays a message thread preview in a list.
 *
 * Shows avatar, subject, last message preview, timestamp, participants,
 * and unread count badge.
 */
function ThreadPreview({
  thread,
  isSelected,
  onPress,
}: ThreadPreviewProps): React.JSX.Element {
  const handlePress = useCallback(() => {
    onPress(thread);
  }, [onPress, thread]);

  const senderInitial = getInitials(thread.lastMessage.senderName);
  const hasUnread = thread.unreadCount > 0;

  return (
    <TouchableOpacity
      style={[
        styles.container,
        isSelected && styles.containerSelected,
      ]}
      onPress={handlePress}
      activeOpacity={0.7}
      accessibilityRole="button"
      accessibilityLabel={`Message thread: ${thread.subject}. Last message: ${thread.lastMessage.content}. ${hasUnread ? `${thread.unreadCount} unread messages` : 'No unread messages'}`}
      accessibilityHint="Double tap to open this conversation">
      {/* Avatar */}
      <View style={styles.avatarContainer}>
        <View style={styles.avatar}>
          <Text style={styles.avatarText}>{senderInitial}</Text>
        </View>
      </View>

      {/* Content */}
      <View style={styles.content}>
        {/* Header row with subject and timestamp */}
        <View style={styles.headerRow}>
          <Text
            style={[
              styles.subject,
              hasUnread && styles.subjectUnread,
            ]}
            numberOfLines={1}>
            {thread.subject}
          </Text>
          <Text style={styles.timestamp}>
            {formatPreviewTime(thread.lastMessage.timestamp)}
          </Text>
        </View>

        {/* Message preview row with unread badge */}
        <View style={styles.previewRow}>
          <Text
            style={[
              styles.preview,
              hasUnread && styles.previewUnread,
            ]}
            numberOfLines={1}>
            {thread.lastMessage.content}
          </Text>
          {hasUnread && (
            <View style={styles.unreadBadge}>
              <Text style={styles.unreadBadgeText}>
                {thread.unreadCount > 9 ? '9+' : thread.unreadCount}
              </Text>
            </View>
          )}
        </View>

        {/* Participants row */}
        <Text style={styles.participants} numberOfLines={1}>
          {thread.participants.join(', ')}
        </Text>
      </View>
    </TouchableOpacity>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    paddingHorizontal: 16,
    paddingVertical: 12,
    backgroundColor: '#FFFFFF',
    borderBottomWidth: 1,
    borderBottomColor: '#F3F4F6',
  },
  containerSelected: {
    backgroundColor: 'rgba(59, 130, 246, 0.1)',
    borderLeftWidth: 4,
    borderLeftColor: '#3B82F6',
  },
  avatarContainer: {
    marginRight: 12,
  },
  avatar: {
    width: 48,
    height: 48,
    borderRadius: 24,
    backgroundColor: '#3B82F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarText: {
    fontSize: 18,
    fontWeight: '600',
    color: '#FFFFFF',
  },
  content: {
    flex: 1,
    minWidth: 0,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  subject: {
    flex: 1,
    fontSize: 15,
    fontWeight: '500',
    color: '#374151',
    marginRight: 8,
  },
  subjectUnread: {
    fontWeight: '600',
    color: '#111827',
  },
  timestamp: {
    fontSize: 12,
    color: '#6B7280',
    flexShrink: 0,
  },
  previewRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  preview: {
    flex: 1,
    fontSize: 14,
    color: '#6B7280',
    marginRight: 8,
  },
  previewUnread: {
    fontWeight: '500',
    color: '#111827',
  },
  unreadBadge: {
    minWidth: 20,
    height: 20,
    borderRadius: 10,
    backgroundColor: '#3B82F6',
    justifyContent: 'center',
    alignItems: 'center',
    paddingHorizontal: 6,
    flexShrink: 0,
  },
  unreadBadgeText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#FFFFFF',
  },
  participants: {
    fontSize: 12,
    color: '#9CA3AF',
  },
});

// Export helper function for use in other components
export {formatPreviewTime, getInitials};
export default ThreadPreview;
