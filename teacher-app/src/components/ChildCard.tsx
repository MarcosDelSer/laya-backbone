/**
 * LAYA Teacher App - ChildCard Component
 *
 * A reusable card component for displaying child information with
 * tap-to-check-in/check-out functionality. Designed for quick teacher
 * interactions with large touch targets.
 */

import React, {useCallback} from 'react';
import {
  StyleSheet,
  Text,
  View,
  TouchableOpacity,
  Image,
  AccessibilityInfo,
} from 'react-native';
import StatusBadge from './StatusBadge';
import type {Child, AttendanceStatus} from '../types';

interface ChildCardProps {
  /** Child data to display */
  child: Child;
  /** Current attendance status */
  status: AttendanceStatus;
  /** Check-in time if checked in */
  checkInTime?: string | null;
  /** Check-out time if checked out */
  checkOutTime?: string | null;
  /** Callback when check-in is triggered */
  onCheckIn: (childId: string) => void;
  /** Callback when check-out is triggered */
  onCheckOut: (childId: string) => void;
  /** Whether the card is in a loading/disabled state */
  disabled?: boolean;
  /** Whether an action is currently in progress */
  loading?: boolean;
}

/**
 * Format time string for display
 */
function formatTime(timeString: string | null | undefined): string | null {
  if (!timeString) {
    return null;
  }
  try {
    // Handle ISO date string or time-only string
    const date = new Date(timeString);
    if (isNaN(date.getTime())) {
      // Try parsing as time only (HH:MM:SS)
      const [hours, minutes] = timeString.split(':');
      const hour = parseInt(hours, 10);
      const minute = parseInt(minutes, 10);
      const ampm = hour >= 12 ? 'PM' : 'AM';
      const displayHour = hour % 12 || 12;
      return `${displayHour}:${minute.toString().padStart(2, '0')} ${ampm}`;
    }
    return date.toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'});
  } catch {
    return null;
  }
}

/**
 * Get initials from child name for placeholder avatar
 */
function getInitials(firstName: string, lastName: string): string {
  return `${firstName.charAt(0)}${lastName.charAt(0)}`.toUpperCase();
}

/**
 * ChildCard displays a child's information with tap-to-toggle attendance.
 * Single tap checks in (if not checked in) or checks out (if checked in).
 */
function ChildCard({
  child,
  status,
  checkInTime,
  checkOutTime,
  onCheckIn,
  onCheckOut,
  disabled = false,
  loading = false,
}: ChildCardProps): React.JSX.Element {
  const isCheckedIn = status === 'present' || status === 'late';
  const isCheckedOut = checkOutTime !== null && checkOutTime !== undefined;

  const handlePress = useCallback(() => {
    if (disabled || loading) {
      return;
    }

    if (!isCheckedIn) {
      onCheckIn(child.id);
      AccessibilityInfo.announceForAccessibility(
        `Checking in ${child.firstName} ${child.lastName}`,
      );
    } else if (!isCheckedOut) {
      onCheckOut(child.id);
      AccessibilityInfo.announceForAccessibility(
        `Checking out ${child.firstName} ${child.lastName}`,
      );
    }
  }, [child, isCheckedIn, isCheckedOut, onCheckIn, onCheckOut, disabled, loading]);

  const getActionLabel = (): string => {
    if (isCheckedOut) {
      return 'Checked out';
    }
    if (isCheckedIn) {
      return 'Tap to check out';
    }
    return 'Tap to check in';
  };

  const formattedCheckIn = formatTime(checkInTime);
  const formattedCheckOut = formatTime(checkOutTime);

  return (
    <TouchableOpacity
      style={[
        styles.card,
        isCheckedIn && styles.cardCheckedIn,
        isCheckedOut && styles.cardCheckedOut,
        (disabled || loading) && styles.cardDisabled,
      ]}
      onPress={handlePress}
      disabled={disabled || loading || isCheckedOut}
      activeOpacity={0.7}
      accessibilityRole="button"
      accessibilityLabel={`${child.firstName} ${child.lastName}, ${status}`}
      accessibilityHint={getActionLabel()}>
      {/* Avatar Section */}
      <View style={styles.avatarContainer}>
        {child.photoUrl ? (
          <Image
            source={{uri: child.photoUrl}}
            style={styles.avatar}
            accessibilityIgnoresInvertColors
          />
        ) : (
          <View style={[styles.avatar, styles.avatarPlaceholder]}>
            <Text style={styles.avatarInitials}>
              {getInitials(child.firstName, child.lastName)}
            </Text>
          </View>
        )}
        {loading && <View style={styles.loadingOverlay} />}
      </View>

      {/* Info Section */}
      <View style={styles.infoContainer}>
        <Text style={styles.name} numberOfLines={1}>
          {child.firstName} {child.lastName}
        </Text>

        <View style={styles.timeContainer}>
          {formattedCheckIn && (
            <Text style={styles.timeText}>
              In: {formattedCheckIn}
            </Text>
          )}
          {formattedCheckOut && (
            <Text style={styles.timeText}>
              Out: {formattedCheckOut}
            </Text>
          )}
        </View>

        {child.allergies.length > 0 && (
          <View style={styles.allergyIndicator}>
            <Text style={styles.allergyText}>
              {child.allergies.length} {child.allergies.length === 1 ? 'allergy' : 'allergies'}
            </Text>
          </View>
        )}
      </View>

      {/* Status Section */}
      <View style={styles.statusContainer}>
        <StatusBadge status={status} size="medium" />
        <Text style={styles.actionHint}>{getActionLabel()}</Text>
      </View>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: '#FFFFFF',
    borderRadius: 12,
    padding: 16,
    marginHorizontal: 16,
    marginVertical: 6,
    // iOS shadow
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 2},
    shadowOpacity: 0.1,
    shadowRadius: 4,
    // Android elevation
    elevation: 3,
    // Minimum touch target size (44x44pt)
    minHeight: 80,
  },
  cardCheckedIn: {
    borderLeftWidth: 4,
    borderLeftColor: '#4CAF50',
  },
  cardCheckedOut: {
    backgroundColor: '#F5F5F5',
    opacity: 0.8,
  },
  cardDisabled: {
    opacity: 0.5,
  },
  avatarContainer: {
    position: 'relative',
  },
  avatar: {
    width: 56,
    height: 56,
    borderRadius: 28,
  },
  avatarPlaceholder: {
    backgroundColor: '#4A90D9',
    justifyContent: 'center',
    alignItems: 'center',
  },
  avatarInitials: {
    color: '#FFFFFF',
    fontSize: 20,
    fontWeight: '600',
  },
  loadingOverlay: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    bottom: 0,
    backgroundColor: 'rgba(255, 255, 255, 0.7)',
    borderRadius: 28,
  },
  infoContainer: {
    flex: 1,
    marginLeft: 12,
    justifyContent: 'center',
  },
  name: {
    fontSize: 16,
    fontWeight: '600',
    color: '#333333',
    marginBottom: 4,
  },
  timeContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
  },
  timeText: {
    fontSize: 12,
    color: '#666666',
  },
  allergyIndicator: {
    flexDirection: 'row',
    alignItems: 'center',
    marginTop: 4,
  },
  allergyText: {
    fontSize: 11,
    color: '#FF5722',
    fontWeight: '500',
  },
  statusContainer: {
    alignItems: 'flex-end',
    justifyContent: 'center',
  },
  actionHint: {
    fontSize: 10,
    color: '#999999',
    marginTop: 4,
  },
});

export default ChildCard;
