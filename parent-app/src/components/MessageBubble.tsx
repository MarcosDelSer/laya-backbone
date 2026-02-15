/**
 * LAYA Parent App - MessageBubble Component
 *
 * Displays a single message bubble with sender info, content, and timestamp.
 * Styled differently for messages sent by the current user vs others.
 */

import React from 'react';
import {StyleSheet, Text, View} from 'react-native';
import type {Message} from '../types';
import {formatMessageTime} from '../api/messagingApi';

/**
 * Props for MessageBubble component
 */
interface MessageBubbleProps {
  message: Message;
  isCurrentUser: boolean;
  showSenderName?: boolean;
}

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  primaryLight: 'rgba(74, 144, 217, 0.1)',
  background: '#F5F5F5',
  cardBackground: '#FFFFFF',
  text: '#333333',
  textSecondary: '#666666',
  textLight: '#999999',
  messageSent: '#4A90D9',
  messageReceived: '#F0F0F0',
};

/**
 * MessageBubble displays a single message with appropriate styling
 * based on whether it was sent by the current user or received.
 */
function MessageBubble({
  message,
  isCurrentUser,
  showSenderName = true,
}: MessageBubbleProps): React.JSX.Element {
  const isRead = message.readAt !== null;

  return (
    <View
      style={[
        styles.container,
        isCurrentUser ? styles.containerSent : styles.containerReceived,
      ]}>
      <View
        style={[
          styles.bubble,
          isCurrentUser ? styles.bubbleSent : styles.bubbleReceived,
        ]}>
        {!isCurrentUser && showSenderName && (
          <Text style={styles.senderName}>{message.senderName}</Text>
        )}
        <Text
          style={[
            styles.messageContent,
            isCurrentUser ? styles.contentSent : styles.contentReceived,
          ]}>
          {message.content}
        </Text>
        <View style={styles.metaRow}>
          <Text
            style={[
              styles.timestamp,
              isCurrentUser ? styles.timestampSent : styles.timestampReceived,
            ]}>
            {formatMessageTime(message.sentAt)}
          </Text>
          {isCurrentUser && (
            <View style={styles.readIndicator}>
              {isRead ? (
                <Text style={styles.readIcon}>{'\u2713\u2713'}</Text>
              ) : (
                <Text style={styles.unreadIcon}>{'\u2713'}</Text>
              )}
            </View>
          )}
        </View>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    marginVertical: 4,
    paddingHorizontal: 16,
  },
  containerSent: {
    alignItems: 'flex-end',
  },
  containerReceived: {
    alignItems: 'flex-start',
  },
  bubble: {
    maxWidth: '80%',
    paddingHorizontal: 14,
    paddingVertical: 10,
  },
  bubbleSent: {
    backgroundColor: COLORS.messageSent,
    borderTopLeftRadius: 18,
    borderTopRightRadius: 18,
    borderBottomLeftRadius: 18,
    borderBottomRightRadius: 4,
  },
  bubbleReceived: {
    backgroundColor: COLORS.messageReceived,
    borderTopLeftRadius: 18,
    borderTopRightRadius: 18,
    borderBottomLeftRadius: 4,
    borderBottomRightRadius: 18,
  },
  senderName: {
    fontSize: 12,
    fontWeight: '600',
    color: COLORS.textSecondary,
    marginBottom: 4,
  },
  messageContent: {
    fontSize: 15,
    lineHeight: 20,
  },
  contentSent: {
    color: '#FFFFFF',
  },
  contentReceived: {
    color: COLORS.text,
  },
  metaRow: {
    flexDirection: 'row',
    justifyContent: 'flex-end',
    alignItems: 'center',
    marginTop: 4,
  },
  timestamp: {
    fontSize: 11,
  },
  timestampSent: {
    color: 'rgba(255, 255, 255, 0.7)',
  },
  timestampReceived: {
    color: COLORS.textLight,
  },
  readIndicator: {
    marginLeft: 4,
  },
  readIcon: {
    fontSize: 12,
    color: 'rgba(255, 255, 255, 0.7)',
  },
  unreadIcon: {
    fontSize: 12,
    color: 'rgba(255, 255, 255, 0.5)',
  },
});

export default MessageBubble;
