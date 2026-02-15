/**
 * LAYA Parent App - Activity Card Component
 *
 * Displays activity entry information including activity name,
 * time, and optional description.
 *
 * Adapted from parent-portal/components/ActivityEntry.tsx for React Native.
 */

import React from 'react';
import {View, Text, StyleSheet} from 'react-native';

import type {ActivityEntry} from '../types';

// ============================================================================
// Props Interface
// ============================================================================

interface ActivityCardProps {
  activity: ActivityEntry;
}

// ============================================================================
// Component
// ============================================================================

/**
 * ActivityCard - displays a single activity entry with name and description.
 */
function ActivityCard({activity}: ActivityCardProps): React.JSX.Element {
  return (
    <View style={styles.container}>
      <View style={styles.iconContainer}>
        <Text style={styles.icon}>🎨</Text>
      </View>
      <View style={styles.content}>
        <View style={styles.header}>
          <Text style={styles.title}>{activity.name}</Text>
          <Text style={styles.time}>{activity.time}</Text>
        </View>
        {activity.description ? (
          <Text style={styles.description} numberOfLines={3}>
            {activity.description}
          </Text>
        ) : null}
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
    alignItems: 'flex-start',
    paddingVertical: 12,
    paddingHorizontal: 16,
    backgroundColor: '#FFFFFF',
  },
  iconContainer: {
    width: 40,
    height: 40,
    borderRadius: 20,
    backgroundColor: '#EDE9FE',
    justifyContent: 'center',
    alignItems: 'center',
    marginRight: 12,
  },
  icon: {
    fontSize: 20,
  },
  content: {
    flex: 1,
    minWidth: 0,
  },
  header: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    marginBottom: 4,
  },
  title: {
    fontSize: 16,
    fontWeight: '600',
    color: '#111827',
    flex: 1,
    marginRight: 8,
  },
  time: {
    fontSize: 14,
    color: '#6B7280',
  },
  description: {
    fontSize: 14,
    color: '#4B5563',
    lineHeight: 20,
  },
});

export default ActivityCard;
