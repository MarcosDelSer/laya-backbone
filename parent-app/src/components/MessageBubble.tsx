/**
 * LAYA Parent App - Message Bubble Component
 *
 * Displays a single message in a conversation thread with proper styling
 * based on whether it's from the current user or another participant.
 *
 * Adapted from parent-portal/components/MessageBubble.tsx for React Native.
 */

import React from 'react';
import {View, Text, StyleSheet} from 'react-native';

import type {Message} from '../types';

// ============================================================================
// Props Interface
// ============================================================================

interface MessageBubbleProps {
  /** The message to display */
  message: Message;
  /** Whether the message is from the current user */
  isCurrentUser: boolean;
}

// ============================================================================
// Helper Functions
// ============================================================================

/**
 * Format a timestamp to a readable time string.
 */
function formatTime(timestamp: string): string {
  const date = new Date(timestamp);
  return date.toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  });
}

/**
 * Format a timestamp to a readable date string.
 * Returns 'Today', 'Yesterday', or a formatted date.
 */
function formatDate(timestamp: string): string {
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

// ============================================================================
// Component
// ============================================================================

/**
 * MessageBubble - displays a single message with appropriate styling.
 *
 * Shows the message content, sender name (for received messages), timestamp,
 * and read status indicator (for sent messages).
 */
function MessageBubble({
  message,
  isCurrentUser,
}: MessageBubbleProps): React.JSX.Element {
  return (
    <View
      style={[
        styles.container,
        isCurrentUser ? styles.containerRight : styles.containerLeft,
      ]}>
      <View
        style={[
          styles.bubble,
          isCurrentUser ? styles.bubbleSent : styles.bubbleReceived,
        ]}>
        {/* Sender name for received messages */}
        {!isCurrentUser && (
          <Text style={styles.senderName}>{message.senderName}</Text>
        )}

        {/* Message content */}
        <Text
          style={[
            styles.content,
            isCurrentUser ? styles.contentSent : styles.contentReceived,
          ]}>
          {message.content}
        </Text>

        {/* Footer with timestamp and read status */}
        <View style={styles.footer}>
          <Text
            style={[
              styles.timestamp,
              isCurrentUser ? styles.timestampSent : styles.timestampReceived,
            ]}>
            {formatTime(message.timestamp)}
          </Text>
          {isCurrentUser && (
            <View style={styles.readIndicator}>
              {message.read ? (
                <Text style={styles.readIcon}>✓✓</Text>
              ) : (
                <Text style={styles.unreadIcon}>✓</Text>
              )}
            </View>
          )}
        </View>
      </View>
    </View>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  container: {
    flexDirection: 'row',
    marginBottom: 16,
    paddingHorizontal: 16,
  },
  containerLeft: {
    justifyContent: 'flex-start',
  },
  containerRight: {
    justifyContent: 'flex-end',
  },
  bubble: {
    maxWidth: '75%',
    paddingHorizontal: 16,
    paddingVertical: 12,
  },
  bubbleSent: {
    backgroundColor: '#3B82F6',
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    borderBottomLeftRadius: 16,
    borderBottomRightRadius: 4,
  },
  bubbleReceived: {
    backgroundColor: '#F3F4F6',
    borderTopLeftRadius: 16,
    borderTopRightRadius: 16,
    borderBottomLeftRadius: 4,
    borderBottomRightRadius: 16,
  },
  senderName: {
    fontSize: 12,
    fontWeight: '500',
    color: '#6B7280',
    marginBottom: 4,
  },
  content: {
    fontSize: 15,
    lineHeight: 20,
  },
  contentSent: {
    color: '#FFFFFF',
  },
  contentReceived: {
    color: '#111827',
  },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'flex-end',
    marginTop: 4,
  },
  timestamp: {
    fontSize: 11,
  },
  timestampSent: {
    color: 'rgba(255, 255, 255, 0.7)',
  },
  timestampReceived: {
    color: '#6B7280',
  },
  readIndicator: {
    marginLeft: 4,
  },
  readIcon: {
    fontSize: 11,
    color: 'rgba(255, 255, 255, 0.7)',
    letterSpacing: -2,
  },
  unreadIcon: {
    fontSize: 11,
    color: 'rgba(255, 255, 255, 0.5)',
  },
});

// Export helper functions for use in other components
export {formatDate, formatTime};
export default MessageBubble;
