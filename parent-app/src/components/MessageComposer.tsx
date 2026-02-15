/**
 * LAYA Parent App - Message Composer Component
 *
 * Text input component for composing and sending messages in a thread.
 * Features include:
 * - Auto-expanding textarea
 * - Character count warning
 * - Send on Enter (or button press)
 * - Disabled state support
 *
 * Adapted from parent-portal/components/MessageComposer.tsx for React Native.
 */

import React, {useState, useCallback, useRef} from 'react';
import {
  View,
  TextInput,
  Text,
  StyleSheet,
  TouchableOpacity,
  Keyboard,
  Platform,
  NativeSyntheticEvent,
  TextInputContentSizeChangeEventData,
} from 'react-native';

// ============================================================================
// Constants
// ============================================================================

const MAX_CHARACTER_COUNT = 500;
const CHARACTER_WARNING_THRESHOLD = 200;
const MIN_INPUT_HEIGHT = 40;
const MAX_INPUT_HEIGHT = 120;

// ============================================================================
// Props Interface
// ============================================================================

interface MessageComposerProps {
  /** Callback when a message is sent */
  onSendMessage: (content: string) => void;
  /** Whether the composer is disabled */
  disabled?: boolean;
  /** Placeholder text for the input */
  placeholder?: string;
}

// ============================================================================
// Component
// ============================================================================

/**
 * MessageComposer - input component for composing messages.
 *
 * Provides a text input with auto-expanding height, character count,
 * and a send button. Supports keyboard submit via Enter key.
 */
function MessageComposer({
  onSendMessage,
  disabled = false,
  placeholder = 'Type a message...',
}: MessageComposerProps): React.JSX.Element {
  const [message, setMessage] = useState('');
  const [inputHeight, setInputHeight] = useState(MIN_INPUT_HEIGHT);
  const inputRef = useRef<TextInput>(null);

  /**
   * Handle content size change to auto-expand input
   */
  const handleContentSizeChange = useCallback(
    (event: NativeSyntheticEvent<TextInputContentSizeChangeEventData>) => {
      const {height} = event.nativeEvent.contentSize;
      const newHeight = Math.min(Math.max(height, MIN_INPUT_HEIGHT), MAX_INPUT_HEIGHT);
      setInputHeight(newHeight);
    },
    [],
  );

  /**
   * Handle message submission
   */
  const handleSend = useCallback(() => {
    const trimmedMessage = message.trim();
    if (trimmedMessage && !disabled) {
      onSendMessage(trimmedMessage);
      setMessage('');
      setInputHeight(MIN_INPUT_HEIGHT);
      Keyboard.dismiss();
    }
  }, [message, disabled, onSendMessage]);

  /**
   * Handle key press for submit on Enter
   * Note: On iOS, we rely on returnKeyType="send" and onSubmitEditing
   */
  const handleSubmitEditing = useCallback(() => {
    handleSend();
  }, [handleSend]);

  const canSend = message.trim().length > 0 && !disabled;
  const showCharacterCount = message.length > CHARACTER_WARNING_THRESHOLD;
  const isOverLimit = message.length > MAX_CHARACTER_COUNT;

  return (
    <View style={styles.container}>
      <View style={styles.inputRow}>
        {/* Attachment button placeholder */}
        <TouchableOpacity
          style={styles.attachButton}
          disabled={disabled}
          activeOpacity={0.7}
          accessibilityLabel="Attach file"
          accessibilityHint="Attachment feature coming soon">
          <Text style={[styles.attachIcon, disabled && styles.attachIconDisabled]}>
            📎
          </Text>
        </TouchableOpacity>

        {/* Message input */}
        <View style={styles.inputContainer}>
          <TextInput
            ref={inputRef}
            style={[
              styles.input,
              {height: inputHeight},
              disabled && styles.inputDisabled,
            ]}
            value={message}
            onChangeText={setMessage}
            onContentSizeChange={handleContentSizeChange}
            onSubmitEditing={handleSubmitEditing}
            placeholder={placeholder}
            placeholderTextColor="#9CA3AF"
            multiline
            editable={!disabled}
            returnKeyType="send"
            blurOnSubmit={false}
            textAlignVertical="center"
            accessibilityLabel="Message input"
            accessibilityHint="Type your message here"
          />
          {/* Character count */}
          {showCharacterCount && (
            <Text
              style={[
                styles.characterCount,
                isOverLimit && styles.characterCountOver,
              ]}>
              {message.length}/{MAX_CHARACTER_COUNT}
            </Text>
          )}
        </View>

        {/* Send button */}
        <TouchableOpacity
          style={[styles.sendButton, !canSend && styles.sendButtonDisabled]}
          onPress={handleSend}
          disabled={!canSend}
          activeOpacity={0.7}
          accessibilityLabel="Send message"
          accessibilityHint={canSend ? 'Sends your message' : 'Enter a message to send'}
          accessibilityRole="button">
          <Text style={[styles.sendIcon, !canSend && styles.sendIconDisabled]}>
            ➤
          </Text>
        </TouchableOpacity>
      </View>

      {/* Helper text */}
      <Text style={styles.helperText}>
        Press Send button or use Return key
      </Text>
    </View>
  );
}

// ============================================================================
// Styles
// ============================================================================

const styles = StyleSheet.create({
  container: {
    backgroundColor: '#FFFFFF',
    borderTopWidth: 1,
    borderTopColor: '#E5E7EB',
    paddingHorizontal: 12,
    paddingTop: 12,
    paddingBottom: Platform.OS === 'ios' ? 24 : 12,
  },
  inputRow: {
    flexDirection: 'row',
    alignItems: 'flex-end',
  },
  attachButton: {
    width: 36,
    height: 36,
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 8,
    marginBottom: 2,
  },
  attachIcon: {
    fontSize: 20,
  },
  attachIconDisabled: {
    opacity: 0.4,
  },
  inputContainer: {
    flex: 1,
    position: 'relative',
    marginRight: 8,
  },
  input: {
    backgroundColor: '#F3F4F6',
    borderRadius: 20,
    paddingHorizontal: 16,
    paddingVertical: 10,
    paddingRight: 50,
    fontSize: 15,
    color: '#111827',
    minHeight: MIN_INPUT_HEIGHT,
    maxHeight: MAX_INPUT_HEIGHT,
  },
  inputDisabled: {
    backgroundColor: '#E5E7EB',
    color: '#9CA3AF',
  },
  characterCount: {
    position: 'absolute',
    right: 12,
    bottom: 8,
    fontSize: 11,
    color: '#9CA3AF',
  },
  characterCountOver: {
    color: '#DC2626',
    fontWeight: '500',
  },
  sendButton: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#3B82F6',
    justifyContent: 'center',
    alignItems: 'center',
  },
  sendButtonDisabled: {
    backgroundColor: '#D1D5DB',
  },
  sendIcon: {
    fontSize: 18,
    color: '#FFFFFF',
    transform: [{rotate: '0deg'}],
  },
  sendIconDisabled: {
    color: '#9CA3AF',
  },
  helperText: {
    marginTop: 8,
    fontSize: 11,
    color: '#9CA3AF',
    textAlign: 'center',
  },
});

export default MessageComposer;
