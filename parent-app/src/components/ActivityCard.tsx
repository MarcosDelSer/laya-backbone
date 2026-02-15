/**
 * LAYA Parent App - ActivityCard Component
 *
 * A card component for displaying individual feed events in the
 * daily timeline. Shows event type icon, title, description,
 * time, and optional photo.
 */

import React from 'react';
import {
  StyleSheet,
  Text,
  View,
  Image,
  TouchableOpacity,
} from 'react-native';
import type {FeedEvent, FeedEventType} from '../types';
import {getEventIcon, getEventColor, formatEventTime} from '../api/feedApi';

interface ActivityCardProps {
  /** The feed event data to display */
  event: FeedEvent;
  /** Whether to show the timeline connector line */
  showConnector?: boolean;
  /** Whether this is the first item (no top connector) */
  isFirst?: boolean;
  /** Whether this is the last item (no bottom connector) */
  isLast?: boolean;
  /** Optional callback when the card is pressed */
  onPress?: (event: FeedEvent) => void;
}

/**
 * Theme colors used across the app
 */
const COLORS = {
  primary: '#4A90D9',
  background: '#FFFFFF',
  text: '#333333',
  textSecondary: '#666666',
  textLight: '#999999',
  border: '#E0E0E0',
  timelineConnector: '#E0E0E0',
};

/**
 * Get human-readable event type label
 */
function getEventTypeLabel(type: FeedEventType): string {
  const labels: Record<FeedEventType, string> = {
    check_in: 'Check In',
    check_out: 'Check Out',
    meal: 'Meal',
    nap: 'Nap',
    diaper: 'Diaper Change',
    activity: 'Activity',
    photo: 'Photo',
    incident: 'Incident',
    note: 'Note',
  };
  return labels[type] || 'Event';
}

/**
 * ActivityCard displays a single feed event in a timeline format.
 * Includes an icon, title, description, timestamp, and optional photo.
 */
function ActivityCard({
  event,
  showConnector = true,
  isFirst = false,
  isLast = false,
  onPress,
}: ActivityCardProps): React.JSX.Element {
  const eventColor = getEventColor(event.type);
  const eventIcon = getEventIcon(event.type);
  const formattedTime = formatEventTime(event.timestamp);

  const handlePress = () => {
    if (onPress) {
      onPress(event);
    }
  };

  const CardContent = (
    <>
      {/* Timeline column with icon and connector */}
      <View style={styles.timelineColumn}>
        {/* Top connector line */}
        {showConnector && !isFirst && (
          <View style={styles.connectorTop} />
        )}

        {/* Event icon */}
        <View
          style={[
            styles.iconContainer,
            {backgroundColor: eventColor},
          ]}
          accessibilityLabel={getEventTypeLabel(event.type)}>
          <Text style={styles.iconText}>{eventIcon}</Text>
        </View>

        {/* Bottom connector line */}
        {showConnector && !isLast && (
          <View style={styles.connectorBottom} />
        )}
      </View>

      {/* Content column */}
      <View style={styles.contentColumn}>
        <View style={styles.headerRow}>
          <Text style={styles.title} numberOfLines={1}>
            {event.title}
          </Text>
          <Text style={styles.time}>{formattedTime}</Text>
        </View>

        {event.description && (
          <Text style={styles.description} numberOfLines={3}>
            {event.description}
          </Text>
        )}

        {/* Photo thumbnail if available */}
        {event.photoUrl && (
          <View style={styles.photoContainer}>
            <Image
              source={{uri: event.photoUrl}}
              style={styles.photo}
              resizeMode="cover"
              accessibilityLabel="Event photo"
              accessibilityIgnoresInvertColors
            />
          </View>
        )}

        {/* Additional metadata display */}
        {event.metadata && renderMetadata(event.type, event.metadata)}
      </View>
    </>
  );

  if (onPress) {
    return (
      <TouchableOpacity
        style={styles.card}
        onPress={handlePress}
        activeOpacity={0.7}
        accessibilityRole="button"
        accessibilityLabel={`${event.title} at ${formattedTime}`}>
        {CardContent}
      </TouchableOpacity>
    );
  }

  return (
    <View
      style={styles.card}
      accessibilityRole="text"
      accessibilityLabel={`${event.title} at ${formattedTime}`}>
      {CardContent}
    </View>
  );
}

/**
 * Render event-specific metadata
 */
function renderMetadata(
  type: FeedEventType,
  metadata: Record<string, unknown>,
): React.JSX.Element | null {
  switch (type) {
    case 'meal':
      return renderMealMetadata(metadata);
    case 'nap':
      return renderNapMetadata(metadata);
    default:
      return null;
  }
}

/**
 * Render meal-specific metadata badges
 */
function renderMealMetadata(
  metadata: Record<string, unknown>,
): React.JSX.Element | null {
  const amount = metadata.amount as string | undefined;
  if (!amount) {
    return null;
  }

  const amountColors: Record<string, string> = {
    all: '#4CAF50',
    most: '#8BC34A',
    some: '#FF9800',
    none: '#F44336',
  };

  const amountLabels: Record<string, string> = {
    all: 'Ate All',
    most: 'Ate Most',
    some: 'Ate Some',
    none: 'Didn\'t Eat',
  };

  return (
    <View style={styles.metadataContainer}>
      <View
        style={[
          styles.badge,
          {backgroundColor: amountColors[amount] || '#757575'},
        ]}>
        <Text style={styles.badgeText}>{amountLabels[amount] || amount}</Text>
      </View>
    </View>
  );
}

/**
 * Render nap-specific metadata badges
 */
function renderNapMetadata(
  metadata: Record<string, unknown>,
): React.JSX.Element | null {
  const duration = metadata.duration as number | undefined;
  const quality = metadata.quality as string | undefined;

  if (!duration && !quality) {
    return null;
  }

  const qualityColors: Record<string, string> = {
    good: '#4CAF50',
    fair: '#FF9800',
    poor: '#F44336',
  };

  return (
    <View style={styles.metadataContainer}>
      {duration !== undefined && (
        <View style={[styles.badge, {backgroundColor: '#9C27B0'}]}>
          <Text style={styles.badgeText}>
            {duration >= 60
              ? `${Math.floor(duration / 60)}h ${duration % 60}m`
              : `${duration}m`}
          </Text>
        </View>
      )}
      {quality && (
        <View
          style={[
            styles.badge,
            {backgroundColor: qualityColors[quality] || '#757575'},
          ]}>
          <Text style={styles.badgeText}>
            {quality.charAt(0).toUpperCase() + quality.slice(1)} Sleep
          </Text>
        </View>
      )}
    </View>
  );
}

const styles = StyleSheet.create({
  card: {
    flexDirection: 'row',
    paddingRight: 16,
    paddingVertical: 8,
    minHeight: 80,
  },
  timelineColumn: {
    width: 56,
    alignItems: 'center',
  },
  connectorTop: {
    position: 'absolute',
    top: 0,
    width: 2,
    height: 16,
    backgroundColor: COLORS.timelineConnector,
  },
  connectorBottom: {
    position: 'absolute',
    bottom: 0,
    width: 2,
    flex: 1,
    backgroundColor: COLORS.timelineConnector,
  },
  iconContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    justifyContent: 'center',
    alignItems: 'center',
    marginTop: 8,
    // iOS shadow
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.15,
    shadowRadius: 2,
    // Android elevation
    elevation: 2,
  },
  iconText: {
    fontSize: 18,
  },
  contentColumn: {
    flex: 1,
    backgroundColor: COLORS.background,
    borderRadius: 12,
    padding: 12,
    marginLeft: 8,
    // iOS shadow
    shadowColor: '#000',
    shadowOffset: {width: 0, height: 1},
    shadowOpacity: 0.05,
    shadowRadius: 2,
    // Android elevation
    elevation: 1,
    borderWidth: 1,
    borderColor: COLORS.border,
  },
  headerRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'flex-start',
    marginBottom: 4,
  },
  title: {
    fontSize: 15,
    fontWeight: '600',
    color: COLORS.text,
    flex: 1,
    marginRight: 8,
  },
  time: {
    fontSize: 12,
    color: COLORS.textLight,
  },
  description: {
    fontSize: 14,
    color: COLORS.textSecondary,
    lineHeight: 20,
  },
  photoContainer: {
    marginTop: 8,
    borderRadius: 8,
    overflow: 'hidden',
  },
  photo: {
    width: '100%',
    height: 120,
    borderRadius: 8,
  },
  metadataContainer: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    marginTop: 8,
    gap: 6,
  },
  badge: {
    paddingHorizontal: 8,
    paddingVertical: 4,
    borderRadius: 4,
  },
  badgeText: {
    fontSize: 11,
    fontWeight: '600',
    color: '#FFFFFF',
  },
});

export default ActivityCard;
