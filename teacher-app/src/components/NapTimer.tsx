/**
 * LAYA Teacher App - NapTimer Component
 *
 * A visual timer component for displaying nap duration with
 * start/stop controls. Shows elapsed time in a large, easy-to-read
 * format with color-coded status indication.
 */

import React from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
  AccessibilityInfo,
} from 'react-native';
import type {NapTimerState} from '../hooks/useNapTimer';

/**
 * Props for the NapTimer component
 */
interface NapTimerProps {
  /** Timer state from useNapTimer hook */
  timerState: NapTimerState;
  /** Callback when start button is pressed */
  onStart: () => void;
  /** Callback when stop button is pressed */
  onStop: () => void;
  /** Whether the component is in a loading state */
  loading?: boolean;
  /** Whether the component is disabled */
  disabled?: boolean;
  /** Child's name for accessibility announcements */
  childName?: string;
  /** Size variant of the timer display */
  size?: 'compact' | 'full';
}

/**
 * NapTimer displays a visual timer with start/stop controls
 *
 * Features:
 * - Large, easy-to-read timer display
 * - Color-coded status (gray when stopped, blue when running)
 * - Start/Stop button with loading state
 * - Accessibility support with announcements
 * - Compact mode for list views
 */
function NapTimer({
  timerState,
  onStart,
  onStop,
  loading = false,
  disabled = false,
  childName,
  size = 'full',
}: NapTimerProps): React.JSX.Element {
  const {isRunning, formattedTime, elapsedMinutes} = timerState;

  const handlePress = () => {
    if (disabled || loading) {
      return;
    }

    if (isRunning) {
      onStop();
      if (childName) {
        AccessibilityInfo.announceForAccessibility(
          `Stopping nap for ${childName}. Duration: ${formattedTime}`,
        );
      }
    } else {
      onStart();
      if (childName) {
        AccessibilityInfo.announceForAccessibility(
          `Starting nap for ${childName}`,
        );
      }
    }
  };

  const buttonText = loading
    ? 'Loading...'
    : isRunning
    ? 'Stop Nap'
    : 'Start Nap';

  const accessibilityLabel = isRunning
    ? `Nap timer running for ${formattedTime}. Tap to stop.`
    : 'Nap timer stopped. Tap to start.';

  if (size === 'compact') {
    return (
      <View style={styles.compactContainer}>
        <View
          style={[
            styles.compactTimerDisplay,
            isRunning && styles.compactTimerDisplayRunning,
          ]}>
          <Text
            style={[
              styles.compactTimerText,
              isRunning && styles.compactTimerTextRunning,
            ]}>
            {formattedTime}
          </Text>
        </View>
        <TouchableOpacity
          style={[
            styles.compactButton,
            isRunning ? styles.compactButtonStop : styles.compactButtonStart,
            (disabled || loading) && styles.compactButtonDisabled,
          ]}
          onPress={handlePress}
          disabled={disabled || loading}
          activeOpacity={0.7}
          accessibilityRole="button"
          accessibilityLabel={accessibilityLabel}>
          <Text
            style={[
              styles.compactButtonText,
              isRunning
                ? styles.compactButtonTextStop
                : styles.compactButtonTextStart,
            ]}>
            {isRunning ? 'Stop' : 'Start'}
          </Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View style={styles.container}>
      {/* Timer Display */}
      <View
        style={[styles.timerDisplay, isRunning && styles.timerDisplayRunning]}>
        <Text
          style={[styles.timerText, isRunning && styles.timerTextRunning]}
          accessibilityLabel={`Timer: ${formattedTime}`}>
          {formattedTime}
        </Text>
        {isRunning && elapsedMinutes > 0 && (
          <Text style={styles.durationLabel}>
            {elapsedMinutes} minute{elapsedMinutes !== 1 ? 's' : ''}
          </Text>
        )}
      </View>

      {/* Status Indicator */}
      <View style={styles.statusContainer}>
        <View
          style={[
            styles.statusDot,
            isRunning ? styles.statusDotRunning : styles.statusDotStopped,
          ]}
        />
        <Text style={styles.statusText}>
          {isRunning ? 'Napping...' : 'Not napping'}
        </Text>
      </View>

      {/* Control Button */}
      <TouchableOpacity
        style={[
          styles.controlButton,
          isRunning ? styles.controlButtonStop : styles.controlButtonStart,
          (disabled || loading) && styles.controlButtonDisabled,
        ]}
        onPress={handlePress}
        disabled={disabled || loading}
        activeOpacity={0.7}
        accessibilityRole="button"
        accessibilityLabel={accessibilityLabel}
        accessibilityState={{disabled: disabled || loading}}>
        <Text
          style={[
            styles.controlButtonText,
            isRunning
              ? styles.controlButtonTextStop
              : styles.controlButtonTextStart,
          ]}>
          {buttonText}
        </Text>
      </TouchableOpacity>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    alignItems: 'center',
    padding: 16,
  },
  timerDisplay: {
    width: 180,
    height: 180,
    borderRadius: 90,
    backgroundColor: '#F5F5F5',
    justifyContent: 'center',
    alignItems: 'center',
    borderWidth: 4,
    borderColor: '#E0E0E0',
  },
  timerDisplayRunning: {
    backgroundColor: '#E3F2FD',
    borderColor: '#4A90D9',
  },
  timerText: {
    fontSize: 36,
    fontWeight: '700',
    color: '#666666',
    fontVariant: ['tabular-nums'],
  },
  timerTextRunning: {
    color: '#1976D2',
  },
  durationLabel: {
    fontSize: 14,
    color: '#4A90D9',
    marginTop: 4,
  },
  statusContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 16,
    marginBottom: 24,
  },
  statusDot: {
    width: 10,
    height: 10,
    borderRadius: 5,
    marginRight: 8,
  },
  statusDotRunning: {
    backgroundColor: '#4CAF50',
  },
  statusDotStopped: {
    backgroundColor: '#9E9E9E',
  },
  statusText: {
    fontSize: 16,
    color: '#666666',
  },
  controlButton: {
    paddingVertical: 16,
    paddingHorizontal: 48,
    borderRadius: 30,
    minWidth: 160,
    alignItems: 'center',
  },
  controlButtonStart: {
    backgroundColor: '#4A90D9',
  },
  controlButtonStop: {
    backgroundColor: '#FF5722',
  },
  controlButtonDisabled: {
    opacity: 0.5,
  },
  controlButtonText: {
    fontSize: 18,
    fontWeight: '600',
  },
  controlButtonTextStart: {
    color: '#FFFFFF',
  },
  controlButtonTextStop: {
    color: '#FFFFFF',
  },
  // Compact styles
  compactContainer: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
  },
  compactTimerDisplay: {
    paddingHorizontal: 12,
    paddingVertical: 6,
    borderRadius: 16,
    backgroundColor: '#F5F5F5',
    borderWidth: 1,
    borderColor: '#E0E0E0',
  },
  compactTimerDisplayRunning: {
    backgroundColor: '#E3F2FD',
    borderColor: '#4A90D9',
  },
  compactTimerText: {
    fontSize: 14,
    fontWeight: '600',
    color: '#666666',
    fontVariant: ['tabular-nums'],
  },
  compactTimerTextRunning: {
    color: '#1976D2',
  },
  compactButton: {
    paddingHorizontal: 16,
    paddingVertical: 8,
    borderRadius: 16,
    minWidth: 64,
    alignItems: 'center',
  },
  compactButtonStart: {
    backgroundColor: '#4A90D9',
  },
  compactButtonStop: {
    backgroundColor: '#FF5722',
  },
  compactButtonDisabled: {
    opacity: 0.5,
  },
  compactButtonText: {
    fontSize: 14,
    fontWeight: '600',
  },
  compactButtonTextStart: {
    color: '#FFFFFF',
  },
  compactButtonTextStop: {
    color: '#FFFFFF',
  },
});

export default NapTimer;
