/**
 * LAYA Parent App - MessageComposer Component
 *
 * Input component for composing and sending messages.
 * Includes text input and send button with validation.
 */

import React, {useState, useCallback} from 'react';
import {
  StyleSheet,
  View,
  TextInput,
  TouchableOpacity,
  Text,
  Keyboard,
  Platform,
} from 'react-native';

/**
 * Props for MessageComposer component
 */
interface MessageComposerProps {
  onSend: (content: string) => void;
  placeholder?: string;
  disabled?: boolean;
}

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
  disabled: '#CCCCCC',
};

/**
 * MessageComposer provides a text input and send button for composing messages.
 * Handles text state, validation, and keyboard dismissal.
 */
function MessageComposer({
  onSend,
  placeholder = 'Type a message...',
  disabled = false,
}: MessageComposerProps): React.JSX.Element {
  const [message, setMessage] = useState('');
  const [isFocused, setIsFocused] = useState(false);

  const trimmedMessage = message.trim();
  const canSend = trimmedMessage.length > 0 && !disabled;

  /**
   * Handle sending the message
   */
  const handleSend = useCallback(() => {
    if (!canSend) return;

    onSend(trimmedMessage);
    setMessage('');
    Keyboard.dismiss();
  }, [canSend, onSend, trimmedMessage]);

  /**
   * Handle text change
   */
  const handleChangeText = useCallback((text: string) => {
    setMessage(text);
  }, []);

  /**
   * Handle focus state
   */
  const handleFocus = useCallback(() => {
    setIsFocused(true);
  }, []);

  const handleBlur = useCallback(() => {
    setIsFocused(false);
  }, []);

  return (
    <View style={styles.container}>
      <View
        style={[
          styles.inputContainer,
          isFocused && styles.inputContainerFocused,
        ]}>
        <TextInput
          style={styles.input}
          value={message}
          onChangeText={handleChangeText}
          placeholder={placeholder}
          placeholderTextColor={COLORS.textLight}
          multiline
          maxLength={2000}
          editable={!disabled}
          onFocus={handleFocus}
          onBlur={handleBlur}
          returnKeyType="default"
          blurOnSubmit={false}
          textAlignVertical="center"
        />
        <TouchableOpacity
          style={[styles.sendButton, !canSend && styles.sendButtonDisabled]}
          onPress={handleSend}
          disabled={!canSend}
          activeOpacity={0.7}
          accessibilityLabel="Send message"
          accessibilityRole="button"
          accessibilityState={{disabled: !canSend}}>
          <Text
            style={[styles.sendIcon, !canSend && styles.sendIconDisabled]}>
            {'\u2191'}
          </Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    paddingHorizontal: 12,
    paddingVertical: 8,
    paddingBottom: Platform.OS === 'ios' ? 24 : 8,
    backgroundColor: COLORS.cardBackground,
    borderTopWidth: 1,
    borderTopColor: COLORS.border,
  },
  inputContainer: {
    flexDirection: 'row',
    alignItems: 'flex-end',
    backgroundColor: COLORS.background,
    borderRadius: 24,
    borderWidth: 1,
    borderColor: COLORS.border,
    paddingLeft: 16,
    paddingRight: 4,
    paddingVertical: 4,
    minHeight: 44,
  },
  inputContainerFocused: {
    borderColor: COLORS.primary,
  },
  input: {
    flex: 1,
    fontSize: 16,
    color: COLORS.text,
    maxHeight: 120,
    paddingVertical: Platform.OS === 'ios' ? 8 : 4,
    paddingRight: 8,
  },
  sendButton: {
    width: 36,
    height: 36,
    borderRadius: 18,
    backgroundColor: COLORS.primary,
    justifyContent: 'center',
    alignItems: 'center',
  },
  sendButtonDisabled: {
    backgroundColor: COLORS.disabled,
  },
  sendIcon: {
    fontSize: 20,
    fontWeight: '700',
    color: '#FFFFFF',
  },
  sendIconDisabled: {
    color: 'rgba(255, 255, 255, 0.5)',
  },
});

export default MessageComposer;
